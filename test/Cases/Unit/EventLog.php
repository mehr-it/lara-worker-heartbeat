<?php


	namespace MehrItLaraWorkerHeartbeatTest\Cases\Unit;


	use Illuminate\Support\Facades\Event;

	trait EventLog
	{

		protected $eventLog = [];


		/**
		 * Logs the given events
		 * @param string[]|string $events The event(s)
		 */
		protected function logEvents($events) {

			Event::listen($events, function($event) {
				$this->eventLog[] = get_class($event);
			});
		}

		/**
		 * Gets the event log
		 * @return string[] The event log
		 */
		protected function getEventLog(): array {
			return $this->eventLog;
		}

	}