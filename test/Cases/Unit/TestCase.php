<?php


	namespace MehrItLaraWorkerHeartbeatTest\Cases\Unit;


	use MehrIt\LaraWorkerHeartbeat\Provider\WorkerHeartbeatServiceProvider;

	class TestCase extends \Orchestra\Testbench\TestCase
	{
		protected function getPackageProviders($app) {

			return [
				WorkerHeartbeatServiceProvider::class,
			];
		}
	}