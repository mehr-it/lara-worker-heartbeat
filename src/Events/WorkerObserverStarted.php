<?php


	namespace MehrIt\LaraWorkerHeartbeat\Events;


	class WorkerObserverStarted
	{

		/**
		 * @var int
		 */
		protected $pid;

		/**
		 * Creates a new instance
		 * @param int $pid The observer process' PID
		 */
		public function __construct(int $pid) {
			$this->pid = $pid;
		}

		/**
		 * @return int
		 */
		public function getPid(): int {
			return $this->pid;
		}


	}