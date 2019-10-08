<?php


	namespace MehrItLaraWorkerHeartbeatTest\Cases\Unit\Provider;


	use MehrIt\LaraWorkerHeartbeat\Console\WorkCommand;
	use MehrIt\LaraWorkerHeartbeat\Queue\Worker;
	use MehrItLaraWorkerHeartbeatTest\Cases\Unit\TestCase;

	class WorkerHeartbeatServiceProviderTest extends TestCase
	{

		public function testWorkCommandRegistered() {

			$resolved = app('command.queue.work');
			$this->assertInstanceOf(WorkCommand::class, $resolved);
			$this->assertSame($resolved, app('command.queue.work'));
			$this->assertSame($resolved, app(\Illuminate\Queue\Console\WorkCommand::class));
			$this->assertSame($resolved, app(WorkCommand::class));

		}

		public function testWorkerRegistered() {

			$resolved = app('queue.worker');
			$this->assertInstanceOf(Worker::class, $resolved);
			$this->assertSame($resolved, app('queue.worker'));
			$this->assertSame($resolved, app(\Illuminate\Queue\Worker::class));
			$this->assertSame($resolved, app(Worker::class));

		}

	}