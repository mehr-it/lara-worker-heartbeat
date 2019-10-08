<?php


	namespace MehrIt\LaraWorkerHeartbeat\Events;


	class WorkerTimeoutUpdated
	{
		/**
		 * @var int
		 */
		protected $timeout;

		/**
		 * ProcessTimeoutSet constructor.
		 * @param int $timeout The timeout in seconds
		 */
		public function __construct(int $timeout) {
			$this->timeout = $timeout;
		}

		/**
		 * Gets the timeout in seconds
		 * @return int The timeout in seconds
		 */
		public function getTimeout(): int {
			return $this->timeout;
		}




	}