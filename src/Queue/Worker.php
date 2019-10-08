<?php


	namespace MehrIt\LaraWorkerHeartbeat\Queue;


	/**
	 * Queue worker with heartbeat events
	 * @package MehrIt\LaraWorkerHeartbeat\Queue
	 */
	class Worker extends \Illuminate\Queue\Worker
	{
		use Heartbeat;
	}