<?php


	namespace MehrItLaraWorkerHeartbeatTest\Cases\Unit;


	use Exception;
	use Illuminate\Contracts\Queue\Job;
	use Illuminate\Contracts\Queue\Queue;
	use Illuminate\Queue\Connectors\ConnectorInterface;
	use Illuminate\Queue\Events\Looping;
	use Illuminate\Queue\QueueManager;
	use Illuminate\Queue\Worker;
	use Illuminate\Queue\WorkerOptions;
	use Illuminate\Support\Facades\Event;
	use MehrItLaraWorkerHeartbeatTest\Helpers\DummyJob;
	use PHPUnit\Framework\MockObject\MockObject;

	trait FakeQueue
	{


		/**
		 * Registers a new fake queue
		 * @param string $driver The driver name
		 * @return Queue|MockObject
		 */
		protected function fakeQueue(string $driver = 'dummy') {

			config()->set('queue.connections.testing', ['driver' => $driver]);

			$connectionName = null;

			$mockQueue = $this->getMockBuilder(Queue::class)
				->setMethods(['setContainer'])
				->getMockForAbstractClass();
			$mockQueue
				->method('setConnectionName')
				->willReturnCallback(function($name) use (&$connectionName, $mockQueue) {
					$connectionName = $name;

					return $mockQueue;
				});
			$mockQueue
				->method('getConnectionName')
				->willReturnCallback(function() use (&$connectionName){
					return $connectionName;
				});
			$mockQueue
				->method('setContainer')
				->willReturnSelf();

			/** @var MockObject|ConnectorInterface $mockConnector */
			$mockConnector = $this->getMockBuilder(ConnectorInterface::class)->getMock();
			$mockConnector
				->method('connect')
				->willReturn($mockQueue);

			/** @var QueueManager $manager */
			$manager = app('queue');
			$manager->extend($driver, function() use ($mockConnector) {

				return $mockConnector;
			});

			return $mockQueue;
		}

		protected function fakeJob($callback = null, $timeout = null, $connection = 'testing', $queue = 'default') {

			if (!$callback)
				$callback = function() { };

			return new DummyJob($callback, $connection, $queue, $timeout);

		}

		/**
		 * Runs the worker daemon for the given number of loops
		 * @param Worker $worker The worker
		 * @param int $loops The number of loops
		 * @param string $connectionName The connection name
		 * @param string $queue The queue
		 * @param WorkerOptions|null $options The worker options
		 */
		protected function runWorkerDaemon(Worker $worker, int $loops, $options = null, $connectionName = 'testing', $queue = 'default') {
			$loopCount = 0;
			Event::listen(Looping::class, function () use (&$loopCount, $loops, $worker) {

				if (++$loopCount > $loops)
					throw new Exception('WORKER_STOPPED');

			});

			// create default worker options (but without sleep to speed up tests)
			if (!$options)
				$options = new WorkerOptions(0, 128, 60, 0, 0);

			try {
				$worker->daemon($connectionName, $queue, $options);
			}
			catch (Exception $ex) {
				if ($ex->getMessage() !== 'WORKER_STOPPED')
					throw $ex;
			}
		}
	}