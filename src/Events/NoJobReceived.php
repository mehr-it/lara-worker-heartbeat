<?php


	namespace MehrIt\LaraWorkerHeartbeat\Events;


	class NoJobReceived
	{

		protected $connectionName;

		/**
		 * NoJobReceived constructor.
		 * @param string $connectionName The connection name
		 */
		public function __construct(?string $connectionName) {
			$this->connectionName = $connectionName;
		}

		/**
		 * Gets the connection name
		 * @return string|null The connection name
		 */
		public function getConnectionName(): ?string {
			return $this->connectionName;
		}
		
		


	}