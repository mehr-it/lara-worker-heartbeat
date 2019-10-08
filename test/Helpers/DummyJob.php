<?php


	namespace MehrItLaraWorkerHeartbeatTest\Helpers;


	use Illuminate\Queue\Jobs\Job;
	use Illuminate\Support\Str;

	class DummyJob extends Job implements \Illuminate\Contracts\Queue\Job
	{
		protected $id;

		protected $callback;

		protected $att = 0;

		protected $timeout;

		/**
		 * DummyJob constructor.
		 */
		public function __construct($callback, $connectionName, $queue, $timeout = null) {

			$this->id = (string)Str::uuid();

			$this->callback       = $callback;
			$this->queue          = $queue;
			$this->connectionName = $connectionName;
			$this->timeout        = $timeout;
		}

		public function fire() {
			++$this->att;
			call_user_func($this->callback);
		}


		/**
		 * Get the job identifier.
		 *
		 * @return string
		 */
		public function getJobId() {
			return $this->id;
		}

		/**
		 * Get the raw body string for the job.
		 *
		 * @return string
		 */
		public function getRawBody() {
			return json_encode([
				'job'     => get_class($this),
				'timeout' => $this->timeout,
			]);
		}

		public function attempts() {
			return $this->att;
		}


	}