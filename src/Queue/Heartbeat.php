<?php


	namespace MehrIt\LaraWorkerHeartbeat\Queue;


	use Illuminate\Contracts\Queue\Job;
	use Illuminate\Contracts\Queue\Queue;
	use Illuminate\Queue\WorkerOptions;
	use MehrIt\LaraWorkerHeartbeat\Events\NoJobReceived;
	use MehrIt\LaraWorkerHeartbeat\Events\WorkerTimeoutUpdated;
	use MehrIt\LaraWorkerHeartbeat\Events\WorkerSleep;

	/**
	 * Implements the heartbeat events for workers
	 * @package MehrIt\LaraWorkerHeartbeat\Queue
	 */
	trait Heartbeat
	{

		/**
		 * Get the next job from the queue connection.
		 *
		 * @param Queue $connection
		 * @param string $queue
		 * @return Job|null
		 */
		protected function getNextJob($connection, $queue) {


			// call parent
			$job = parent::getNextJob($connection, $queue);

			// raise event, if no job received
			if (!$job)
				$this->raiseNoJobReceivedEvent($connection->getConnectionName());

			return $job;
		}

		/**
		 * Register the worker timeout handler.
		 *
		 * @param Job|null $job
		 * @param WorkerOptions $options
		 * @return void
		 */
		protected function registerTimeoutHandler($job, WorkerOptions $options) {
			parent::registerTimeoutHandler($job, $options);

			$timeout = $this->timeoutForJob($job, $options);

			// only emit timeout event, if there is any timeout
			if ($timeout) {
				// if no job is received, this method is called anyways - but we do not emit the event in such case
				if ($job)
					$this->raiseWorkerTimeoutUpdatedEvent($timeout);
			}
		}

		/**
		 * Sleep the script for a given number of seconds.
		 *
		 * @param int|float $seconds
		 * @return void
		 */
		public function sleep($seconds) {

			if ($seconds)
				$this->raiseWorkerSleepEvent($seconds);

			parent::sleep($seconds);
		}

		/**
		 * Raise the no job received event
		 *
		 * @param string $connectionName
		 * @return void
		 */
		protected function raiseNoJobReceivedEvent(string $connectionName) {
			$this->events->dispatch(new NoJobReceived($connectionName));
		}

		/**
		 * Raise the process timeout set event
		 *
		 * @param int $timeout The timeout in seconds
		 * @return void
		 */
		protected function raiseWorkerTimeoutUpdatedEvent(int $timeout) {
			$this->events->dispatch(new WorkerTimeoutUpdated($timeout));
		}

		/**
		 * Raise the worker sleep event
		 *
		 * @param int|float $duration The duration in seconds
		 * @return void
		 */
		protected function raiseWorkerSleepEvent($duration) {
			$this->events->dispatch(new WorkerSleep($duration));
		}



	}