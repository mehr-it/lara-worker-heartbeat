<?php


	namespace MehrItLaraWorkerHeartbeatTest\Helpers;


	use Illuminate\Queue\Jobs\Job;
	use Illuminate\Support\Str;

	class DummyJob extends Job implements \Illuminate\Contracts\Queue\Job
	{
		public $id;

		public $callback;

		public $att = 0;

		public $timeout;


		public function setQueue($queue) {
			$this->queue = $queue;
		}
		public function setConnection($v) {
			$this->connectionName = $v;
		}

		public function setTimeout($v) {
			$this->timeout = $v;
		}

		public function setContainer($v) {
			$this->container = $v;
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