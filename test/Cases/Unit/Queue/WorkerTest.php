<?php


	namespace MehrItLaraWorkerHeartbeatTest\Cases\Unit\Queue;


	use Illuminate\Contracts\Debug\ExceptionHandler;
	use Illuminate\Queue\Events\JobExceptionOccurred;
	use Illuminate\Queue\Events\JobProcessed;
	use Illuminate\Queue\Events\JobProcessing;
	use Illuminate\Queue\Events\WorkerStopping;
	use Illuminate\Queue\WorkerOptions;
	use MehrIt\LaraWorkerHeartbeat\Events\NoJobReceived;
	use MehrIt\LaraWorkerHeartbeat\Events\WorkerTimeoutUpdated;
	use MehrIt\LaraWorkerHeartbeat\Events\WorkerSleep;
	use MehrIt\LaraWorkerHeartbeat\Queue\Worker;
	use MehrItLaraWorkerHeartbeatTest\Cases\Unit\EventLog;
	use MehrItLaraWorkerHeartbeatTest\Cases\Unit\FakeQueue;
	use MehrItLaraWorkerHeartbeatTest\Cases\Unit\TestCase;

	class WorkerTest extends TestCase
	{
		use FakeQueue;
		use EventLog;

		public function testDaemon_singleJob_2cycles() {

			$this->logEvents([
				JobProcessing::class,
				JobProcessed::class,
				JobExceptionOccurred::class,
				WorkerStopping::class,
				WorkerSleep::class,
				NoJobReceived::class,
				WorkerTimeoutUpdated::class,
			]);

			$queueMock = $this->fakeQueue();
			$queueMock
				->method('pop')
				->with('default')
				->willReturnOnConsecutiveCalls(
					$this->fakeJob(),
					null
				);

			$worker = new Worker(
				app('queue'),
				app('events'),
				app(ExceptionHandler::class),
				function() {
					return false;
				}
			);

			$this->runWorkerDaemon($worker, 2);

			$this->assertSame([

				WorkerTimeoutUpdated::class,
				JobProcessing::class,
				JobProcessed::class,
				NoJobReceived::class,

			], $this->getEventLog());

		}

		public function testDaemon_singleJob_3cycles_withSleep() {

			$this->logEvents([
				JobProcessing::class,
				JobProcessed::class,
				JobExceptionOccurred::class,
				WorkerStopping::class,
				WorkerSleep::class,
				NoJobReceived::class,
				WorkerTimeoutUpdated::class,
			]);

			$queueMock = $this->fakeQueue();
			$queueMock
				->method('pop')
				->with('default')
				->willReturnOnConsecutiveCalls(
					$this->fakeJob(),
					null,
					null
				);

			$worker = new Worker(
				app('queue'),
				app('events'),
				app(ExceptionHandler::class),
				function () {
					return false;
				}
			);

			$this->runWorkerDaemon($worker, 3, new WorkerOptions(0, 128, 60, 0.01));

			$this->assertSame([

				WorkerTimeoutUpdated::class,
				JobProcessing::class,
				JobProcessed::class,
				NoJobReceived::class,
				WorkerSleep::class,
				NoJobReceived::class,
				WorkerSleep::class,

			], $this->getEventLog());

		}

		public function testDaemon_multipleJobs_3cycles() {

			$this->logEvents([
				JobProcessing::class,
				JobProcessed::class,
				JobExceptionOccurred::class,
				WorkerStopping::class,
				WorkerSleep::class,
				NoJobReceived::class,
				WorkerTimeoutUpdated::class,
			]);

			$queueMock = $this->fakeQueue();
			$queueMock
				->method('pop')
				->with('default')
				->willReturnOnConsecutiveCalls(
					$this->fakeJob(),
					$this->fakeJob(),
					null
				);

			$worker = new Worker(
				app('queue'),
				app('events'),
				app(ExceptionHandler::class),
				function () {
					return false;
				}
			);

			$this->runWorkerDaemon($worker, 3);

			$this->assertSame([

				WorkerTimeoutUpdated::class,
				JobProcessing::class,
				JobProcessed::class,
				WorkerTimeoutUpdated::class,
				JobProcessing::class,
				JobProcessed::class,
				NoJobReceived::class,

			], $this->getEventLog());

		}

		public function testDaemon_noJobs_3cycles() {

			$this->logEvents([
				JobProcessing::class,
				JobProcessed::class,
				JobExceptionOccurred::class,
				WorkerStopping::class,
				WorkerSleep::class,
				NoJobReceived::class,
				WorkerTimeoutUpdated::class,
			]);

			$queueMock = $this->fakeQueue();
			$queueMock
				->method('pop')
				->with('default')
				->willReturn(null);

			$worker = new Worker(
				app('queue'),
				app('events'),
				app(ExceptionHandler::class),
				function () {
					return false;
				}
			);

			$this->runWorkerDaemon($worker, 3);

			$this->assertSame([
				NoJobReceived::class,
				NoJobReceived::class,
				NoJobReceived::class,

			], $this->getEventLog());

		}

		public function testDaemon_singleJobWithException_2cycles() {

			$this->logEvents([
				JobProcessing::class,
				JobProcessed::class,
				JobExceptionOccurred::class,
				WorkerStopping::class,
				WorkerSleep::class,
				NoJobReceived::class,
				WorkerTimeoutUpdated::class,
			]);

			$queueMock = $this->fakeQueue();
			$queueMock
				->method('pop')
				->with('default')
				->willReturnOnConsecutiveCalls(
					$this->fakeJob(function() {
						throw new \Exception('exception thrown');
					}),
					null
				);

			$worker = new Worker(
				app('queue'),
				app('events'),
				app(ExceptionHandler::class),
				function() {
					return false;
				}
			);

			$this->runWorkerDaemon($worker, 2);

			$this->assertSame([
				WorkerTimeoutUpdated::class,
				JobProcessing::class,
				JobExceptionOccurred::class,
				NoJobReceived::class,

			], $this->getEventLog());

		}
	}