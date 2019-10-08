<?php


	namespace MehrIt\LaraWorkerHeartbeat\Console;


	use Illuminate\Queue\QueueManager;
	use Illuminate\Queue\Worker;
	use MehrIt\LaraWorkerHeartbeat\HeartbeatObserver;
	use RuntimeException;

	/**
	 * Extended queue work command starting a heartbeat observer if heartbeat is enabled
	 * @package MehrIt\LaraWorkerHeartbeat\Console
	 */
	class WorkCommand extends \Illuminate\Queue\Console\WorkCommand
	{

		public function __construct(Worker $worker) {

			// here we append the observer option to our signature
			$this->signature .= ' {--heartbeat-timeout=0 : The seconds without heartbeat signal after which the worker should be killed. If empty heartbeat observation is disabled.}';

			parent::__construct($worker);
		}

		/**
		 * Execute the console command.
		 *
		 * @return void
		 */
		public function handle() {

			// start observer if should
			$this->startObserverIfShould();

			parent::handle();
		}


		/**
		 * Starts a worker observer if it should be started
		 */
		protected function startObserverIfShould() {

			$heartbeatTimeout = $this->option('heartbeat-timeout');

			if ($heartbeatTimeout) {

				if (!extension_loaded('pcntl'))
					throw new RuntimeException('Extension "pcntl" is required to start a worker observer process.');
				if (!extension_loaded('posix'))
					throw new RuntimeException('Extension "posix" is required to start a worker observer process.');

				/** @var HeartbeatObserver $observer */
				$observer = app(HeartbeatObserver::class);
				$observer->start($heartbeatTimeout);
			}

		}
	}