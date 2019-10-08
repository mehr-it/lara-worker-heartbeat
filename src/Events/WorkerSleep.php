<?php


	namespace MehrIt\LaraWorkerHeartbeat\Events;


	class WorkerSleep
	{
		/**
		 * @var int|float
		 */
		protected $duration;

		/**
		 * ProcessTimeoutSet constructor.
		 * @param int|float $duration The duration in seconds
		 */
		public function __construct($duration) {
			$this->duration = $duration;
		}

		/**
		 * Gets the duration in seconds
		 * @return int|float The duration in seconds
		 */
		public function getDuration() {
			return $this->duration;
		}
	}