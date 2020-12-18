<?php


	namespace MehrIt\LaraWorkerHeartbeat;


	use Illuminate\Contracts\Events\Dispatcher;
	use Illuminate\Queue\Events\JobExceptionOccurred;
	use Illuminate\Queue\Events\JobProcessed;
	use Illuminate\Queue\Events\JobProcessing;
	use Illuminate\Queue\Events\WorkerStopping;
	use InvalidArgumentException;
	use MehrIt\LaraWorkerHeartbeat\Events\NoJobReceived;
	use MehrIt\LaraWorkerHeartbeat\Events\WorkerObserverStarted;
	use MehrIt\LaraWorkerHeartbeat\Events\WorkerTimeoutUpdated;
	use MehrIt\LaraWorkerHeartbeat\Events\WorkerSleep;
	use RuntimeException;

	/**
	 * Observer for work commands
	 * @package MehrIt\LaraWorkerHeartbeat
	 */
	class HeartbeatObserver
	{
		const OBSERVER_POLL_MICRO_TIME = 1000000; // 1s

		const MESSAGE_TYPE_PID = 'PID';
		const MESSAGE_TYPE_CYCLE = 'CYCLE';
		const MESSAGE_TYPE_PROCESSING_TIMEOUT = 'PROCESSING_TIMEOUT';
		const MESSAGE_TYPE_PROCESSING = 'PROCESSING';
		const MESSAGE_TYPE_PROCESSED = 'PROCESSED';
		const MESSAGE_TYPE_SLEEP = 'SLEEP';
		const MESSAGE_TYPE_STOPPING = 'STOPPING';


		/**
		 * @var resource The inter process communication socket
		 */
		protected $socket;

		/**
		 * @var string The buffer used to read the message stream
		 */
		protected $readBuffer = '';

		/**
		 * @var int|null The worker process' PID
		 */
		protected $workerPid;

		/**
		 * @var int|false The timestamp after which the observer should kill the worker process. False to disable killing of worker process
		 */
		protected $observerTimeoutTs = false;

		/**
		 * @var int The default timeout for killing the worker process
		 */
		protected $defaultTimeout = 30;

		/**
		 * @var int|false The timeout for the next processed job
		 */
		protected $nextProcessingTimeout = false;

		/**
		 * @var string|null
		 */
		protected $observedJobId = null;

		/**
		 * @var Dispatcher
		 */
		protected $events;

		/**
		 * Creates a new instance
		 * @param Dispatcher $events The events dispatcher
		 */
		public function __construct(Dispatcher $events) {
			$this->events = $events;
		}


		/**
		 * Stars the observer process
		 * @param int $observerTimeout The timeout after which to kill the worker when observer does not receive an expected message
		 */
		public function start(int $observerTimeout) {
			$sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
			$pid     = pcntl_fork();

			if ($pid === -1)
				throw new RuntimeException('Failed to fork worker heartbeat observer');

			logger($pid);

			if ($pid) {
				// parent (this process will run the worker)

				// avoid zombies
				pcntl_signal(SIGCHLD, SIG_IGN);


				// pick socket
				$this->socket = $sockets[1];
				fclose($sockets[0]);

				// send own PID to observer
				$this->send(self::MESSAGE_TYPE_PID, $this->getPid());


				// add listeners for job events sending them to the observer
				$this->events->listen(NoJobReceived::class, function() {
					// for empty job receives, we simply send a cycle message
					$this->send(self::MESSAGE_TYPE_CYCLE, microtime(true));
				});
				$this->events->listen(WorkerSleep::class, function(WorkerSleep $event) {
					// for empty job receives, we simply send a cycle message
					$this->send(self::MESSAGE_TYPE_SLEEP, microtime(true) + $event->getDuration());
				});
				$this->events->listen(WorkerStopping::class, function() {
					// for empty job receives, we simply send a cycle message
					$this->send(self::MESSAGE_TYPE_STOPPING, '', true);
				});
				$this->events->listen(WorkerTimeoutUpdated::class, function(WorkerTimeoutUpdated $event) {
					$this->send(self::MESSAGE_TYPE_PROCESSING_TIMEOUT, microtime(true) + $event->getTimeout());
				});
				$this->events->listen(JobProcessing::class, function(JobProcessing $event) {
					$this->send(self::MESSAGE_TYPE_PROCESSING, $event->job->getJobId());
				});
				$this->events->listen([JobProcessed::class, JobExceptionOccurred::class], function() {
					$this->send(self::MESSAGE_TYPE_PROCESSED, microtime(true));
				});

				// notify that we started an observer process
				$this->events->dispatch(new WorkerObserverStarted($pid));
			}
			else {
				// child (the observer)

				// pick socket
				$this->socket = $sockets[0];
				fclose($sockets[1]);

				// non-blocking read
				stream_set_blocking($sockets[0], false);


				// initialize the observer timeout
				$this->defaultTimeout    = $observerTimeout;
				$this->observerTimeoutTs = microtime(true) + $observerTimeout;


				// run the observer loop
				while(true) {

					if ($msg = $this->readNext()) {
						// handle any messages
						$this->handleMessage($msg);
					}
					else {
						// stop observer if worker has stopped
						if ($this->workerPid && !$this->isWorkerRunning())
							exit(0);

						// kill worker if timeout elapsed
						if ($this->observerTimeoutTs !== false) {

							if (microtime(true) > $this->observerTimeoutTs)
								$this->killWorkerAndStop();

						}

						usleep(self::OBSERVER_POLL_MICRO_TIME);
					}

				}
			}
		}

		/**
		 * Kills the worker process and stops the current (observer) process
		 */
		protected function killWorkerAndStop() {

			if (!$this->workerPid) {
				logger('Cannot kill queue worker because it\'s PID is unknown.');
				exit(1);
			}

			logger("Killing queue worker with PID {$this->workerPid} " . ($this->observedJobId ? "processing job {$this->observedJobId} " : '') . "because heartbeat timeout elapsed.");
			@posix_kill($this->workerPid, SIGKILL);

			exit(0);
		}

		/**
		 * Returns if the worker is running
		 * @return bool True if worker process is running. Else false.
		 */
		protected function isWorkerRunning() : bool {
			if (!$this->workerPid)
				throw new RuntimeException('Worker PID is unknown. Cannot check if process is running.');

			return @posix_kill($this->workerPid, 0) !== false;
		}


		/**
		 * Handles the given message from worker
		 * @param string $message The message
		 */
		protected function handleMessage(string $message) {

			// parse the message
			$split   = explode(' ', $message, 2);
			$type    = $split[0];
			$data    = $split[1] ?? '';


			switch($type) {

				case self::MESSAGE_TYPE_PID:
					// remember the PID of the worker
					$this->workerPid = (int)$data;
					break;

				case self::MESSAGE_TYPE_CYCLE:
				case self::MESSAGE_TYPE_PROCESSED:
					// after processing a job or next cycle, we increment the observer timeout by the default timeout
					$this->observerTimeoutTs = $data + $this->defaultTimeout;
					$this->observedJobId = null;
					break;

				case self::MESSAGE_TYPE_SLEEP:
					// worker is going to sleep => increment observer timeout by sleep duration
					$this->observerTimeoutTs = $data + $this->defaultTimeout;
					break;

				case self::MESSAGE_TYPE_STOPPING:
					// the worker is stopping => observer is not needed anymore. stop here
					exit(0);

				case self::MESSAGE_TYPE_PROCESSING_TIMEOUT:
					// worker has set it's timeout for the processing of the next job => remember it here
					$this->nextProcessingTimeout = $data;
					$this->observedJobId = null;
					break;

				case self::MESSAGE_TYPE_PROCESSING:
					// worker starts job processing

					$this->observedJobId = $data;

					if ($this->nextProcessingTimeout !== false) {
						// apply timeout for next job processing
						$this->observerTimeoutTs     = $this->nextProcessingTimeout + $this->defaultTimeout;
						$this->nextProcessingTimeout = false;
					}
					else {
						// disable timeout while processing
						$this->observerTimeoutTs = false;
					}
					break;



				default:
					logger("Unexpected messages from observed worker: \"{$message}\"");
			}

		}


		/**
		 * Sends a message to the observer
		 * @param string $msgType The message type
		 * @param string|mixed $data The message data
		 * @param bool $silent True if to silently fail
		 */
		protected function send(string $msgType, $data = '', bool $silent = false) {

			if (strpos($data, ';') !== false)
				throw new InvalidArgumentException("Data must not contain \";\", got \"{$data}\".");

			if (@fwrite($this->socket, "{$msgType} {$data};") === false) {
				if (!$silent)
					logger("Failed to send \"{$msgType}\" message to worker observer. Observer seams not be running anymore.");
			}
		}

		/**
		 * Reads the next complete message from socket. If message was read incomplete, the method will continue reading data from stream up to 50ms.
		 * @return string|null The next complete message or null if none available
		 */
		protected function readNext(): ?string {

			// if read buffer does not contain a complete message, we try to read
			// data for up to 0.05s until a complete message is received
			if (strpos($this->readBuffer, ';') === false) {
				$rounds = 0;
				do {
					$data = fgets($this->socket);
					if ($data !== false) {
						$this->readBuffer .= $data;

						// stop if message complete
						if (strpos($data, ';') !== false)
							break;
					}
					else {
						// buffer and stream are empty => we stop here
						if ($this->readBuffer !== '')
							return null;
					}

					++$rounds;
					usleep(5000); // sleep 1ms
				} while ($rounds < 10);
			}


			// search for a complete message in buffer
			$split = explode(';', $this->readBuffer, 2);
			if (count($split) == 2) {

				// extract the next complete message and return it
				$this->readBuffer = $split[1];
				return $split[0];
			}

			return null;
		}



		/**
		 * Gets the PID of the current process
		 * @return int The PID
		 */
		protected function getPid(): int {
			$pid = getmypid();
			if ($pid === false)
				throw new RuntimeException('Failed to get own process PID');

			return $pid;
		}
	}