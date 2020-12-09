<?php


	namespace MehrItLaraWorkerHeartbeatTest\Cases\Unit\Console;



	use Illuminate\Support\Facades\Event;
	use MehrIt\LaraWorkerHeartbeat\Events\WorkerObserverStarted;
	use MehrItLaraWorkerHeartbeatTest\Cases\Unit\FakeQueue;
	use MehrItLaraWorkerHeartbeatTest\Cases\Unit\TestCase;
	use MehrItLaraWorkerHeartbeatTest\Cases\Unit\ForkedProcesses;
	use MehrItLaraWorkerHeartbeatTest\Cases\Unit\WorkCommand;

	class WorkCommandTest extends TestCase
	{
		use FakeQueue;
		use ForkedProcesses;
		use WorkCommand;

		protected $observerPid;

		protected function getEnvironmentSetUp($app) {

			parent::getEnvironmentSetUp($app);

			// we set the cache to file, so we can stop workers using the queue:restart command
			$app['config']->set('cache.default', 'file');
		}

		/**
		 * Stops all currently running workers
		 */
		protected function stopWorkers() {
			$this->artisan('queue:restart');
		}

		/**
		 * Fetches the observer PID sent by the child process
		 */
		protected function fetchObserverPid() {
			// extract observer PID
			$msgData = explode(':', $this->getNextMessage());
			$this->assertEquals('Observer started', $msgData[0]);
			$this->observerPid = (int)trim($msgData[1]);
		}

		protected function assertObserverStopped($timeout = 2) {
			$this->assertDurationLessThan($timeout, function() use ($timeout) {
				$this->waitFor(function() {
					return !posix_kill($this->observerPid, 0);
				}, $timeout * 1000000);
			});
		}

		public function testDaemonWithHeartbeatObserver() {

			$queueMock = $this->fakeQueue();
			$queueMock
				->method('pop')
				->with('default')
				->willReturnOnConsecutiveCalls(
					$this->fakeJob(
						function () {
							$this->sendMessage('Job Executed');
						}
					)
				);

			$this->fork(
				function() {
					try {
						// fetch the observer PID
						$this->fetchObserverPid();

						// assert that the job was executed as expected
						$this->assertNextMessage('Job Executed', 2000000);
					}
					finally {
						$this->stopWorkers();
					}

					$this->assertObserverStopped();
				},
				function() {
					// send observer PID
					Event::listen(WorkerObserverStarted::class, function(WorkerObserverStarted $event) {
						$this->sendMessage("Observer started: {$event->getPid()}");
					});

					$this->assertWorkCommandSuccess([
						'connection'          => 'testing',
						'--queue'             => 'default',
						'--sleep'             => 0,
						'--heartbeat-timeout' => 3,
					]);

				}
			);



		}

		public function testDaemonWithoutHeartbeatObserver() {

			$queueMock = $this->fakeQueue();
			$queueMock
				->method('pop')
				->with('default')
				->willReturnCallback(function() {
					sleep(4);
					$this->sendMessage('Not killed');
				});

			$this->fork(
				function() {
					try {
						// even the pop operation takes more than 4s, the worker process must not be stopped
						// because no observer should be started for this worker
						$this->assertDurationGreaterThan(3, function() {
							$this->assertNextMessage('Not killed', 5000000);
						});
					}
					finally {
						$this->stopWorkers();
					}
				},
				function() {
					// send observer PID (this should not be called in this test case)
					Event::listen(WorkerObserverStarted::class, function (WorkerObserverStarted $event) {
						$this->sendMessage("Observer started: {$event->getPid()}");
					});

					$this->assertWorkCommandSuccess([
						'connection'          => 'testing',
						'--queue'             => 'default',
						'--sleep'             => 0,
						'--heartbeat-timeout' => 0,
					]);

				}
			);

		}

		public function testObserverDoesNotKillWorkerWhileJobProcessing() {

			$queueMock = $this->fakeQueue();
			$queueMock
				->method('pop')
				->with('default')
				->willReturnOnConsecutiveCalls(
					$this->fakeJob(
						function () {
							sleep(2);
							$this->sendMessage('Job Executed');
						},
						3
					)
				);

			$this->fork(
				function() {
					try {
						// fetch the observer PID
						$this->fetchObserverPid();

						// the job should be executed, even if the heartbeat timeout is 1s. The observer should not kill
						// worker during processing a job because the observer timeout should be increased by the job
						// timeout
						$this->assertNextMessage('Job Executed', 4000000);
					}
					finally {
						$this->stopWorkers();
					}

					$this->assertObserverStopped();
				},
				function() {
					// send observer PID
					Event::listen(WorkerObserverStarted::class, function (WorkerObserverStarted $event) {
						$this->sendMessage("Observer started: {$event->getPid()}");
					});

					$this->assertWorkCommandSuccess([
						'connection'          => 'testing',
						'--queue'             => 'default',
						'--sleep'             => 0,
						'--heartbeat-timeout' => 1,
					]);

				}
			);

		}

		public function testObserverDoesNotKillWorkerWhileSleeping() {

			$queueMock = $this->fakeQueue();
			$queueMock
				->method('pop')
				->with('default')
				->willReturnOnConsecutiveCalls(
					null,
					$this->fakeJob(
						function () {
							$this->sendMessage('Job Executed');
						},
						1
					)
				);

			$this->fork(
				function() {
					try {
						// fetch the observer PID
						$this->fetchObserverPid();

						// even worker sleeps 3s after first cycle before receiving job in second cycle, the worker process must
						// not be stopped because the observer timeout should be increased by the sleep duration
						$this->assertDurationGreaterThan(2.9, function () {
							$this->assertNextMessage('Job Executed', 4000000);
						});
					}
					finally {
						$this->stopWorkers();
					}

					// increased timeout, because the worker and observer are running longer due to sleep after job processing
					$this->assertObserverStopped(5);
				},
				function() {
					// send observer PID
					Event::listen(WorkerObserverStarted::class, function (WorkerObserverStarted $event) {
						$this->sendMessage("Observer started: {$event->getPid()}");
					});

					$this->assertWorkCommandSuccess([
						'connection'          => 'testing',
						'--queue'             => 'default',
						'--sleep'             => 3,
						'--heartbeat-timeout' => 1,
					]);

				}
			);

		}

		public function testObserverKillsWorkerWhenStuckWhileJobProcessing() {

			$queueMock = $this->fakeQueue();
			$queueMock
				->method('pop')
				->with('default')
				->willReturnOnConsecutiveCalls(
					$this->fakeJob(
						function () {
							// Turn off internal worker timeout handler, to simulate a stuck worker process
							// which does not terminate itself after job timeout
							pcntl_alarm(0);

							sleep(10);
							$this->sendMessage('Job Executed');
						},
						1
					)
				);


			$this->fork(
				function () {
					try {
						// fetch the observer PID
						$this->fetchObserverPid();

						// Wait for the worker process to be killed. This should happen between 2s and 4s due to heartbeat timeout of 2s
						$this->assertDurationGreaterThan(2, function() {
							$this->waitForChild(4000000);
						});

						// Since the worker was killed, it should not have sent any message
						$this->assertNoMessage(1);
					}
					finally {
						$this->stopWorkers();
					}

					$this->assertObserverStopped();
				},
				function () {
					// send observer PID
					Event::listen(WorkerObserverStarted::class, function (WorkerObserverStarted $event) {
						$this->sendMessage("Observer started: {$event->getPid()}");
					});

					$this->assertWorkCommandSuccess([
						'connection'          => 'testing',
						'--queue'             => 'default',
						'--sleep'             => 0,
						'--heartbeat-timeout' => 2,
					]);
				}
			);

		}

		public function testObserverKillsWorkerWhenStuckDuringQueuePop() {

			$queueMock = $this->fakeQueue();
			$queueMock
				->method('pop')
				->with('default')
				->willReturnCallback(function() {
					// let the queue pop operation last 10s
					sleep(10);
				});


			$this->fork(
				function () {
					try {
						// fetch the observer PID
						$this->fetchObserverPid();

						// Wait for the worker process to be killed. This should happen between 2s and 3s due to heartbeat timeout of 2s
						$this->assertDurationGreaterThan(2, function () {
							$this->waitForChild(3000000);
						});

						$this->assertTrue(true);
					}
					finally {
						$this->stopWorkers();
					}

					$this->assertObserverStopped();
				},
				function () {
					// send observer PID
					Event::listen(WorkerObserverStarted::class, function (WorkerObserverStarted $event) {
						$this->sendMessage("Observer started: {$event->getPid()}");
					});

					$this->assertWorkCommandSuccess([
						'connection'          => 'testing',
						'--queue'             => 'default',
						'--sleep'             => 0,
						'--heartbeat-timeout' => 2,
					]);
				}
			);

		}

		public function testObserverStopsWhenWorkerStoppingUnexpectedly() {

			$queueMock = $this->fakeQueue();
			$queueMock
				->method('pop')
				->with('default')
				->willReturnCallback(function() {
					// let the worker die on first pop operation
					die(1);
				});


			$this->fork(
				function () {
					try {
						// fetch the observer PID
						$this->fetchObserverPid();

						// Wait for the worker process to stop. This should happen instantly after starting
						$this->waitForChild(2000000);
					}
					finally {
						$this->stopWorkers();
					}

					// the observer now should also stop promptly
					$this->assertObserverStopped();
				},
				function () {
					// send observer PID
					Event::listen(WorkerObserverStarted::class, function (WorkerObserverStarted $event) {
						$this->sendMessage("Observer started: {$event->getPid()}");
					});

					$this->assertWorkCommandSuccess([
						'connection'          => 'testing',
						'--queue'             => 'default',
						'--sleep'             => 0,
						'--heartbeat-timeout' => 20,
					]);
				}
			);

		}

		public function testObserverDoesNotKillWorkerWhileJobProcessingWithoutTimeout() {

			$queueMock = $this->fakeQueue();
			$queueMock
				->method('pop')
				->with('default')
				->willReturnOnConsecutiveCalls(
					$this->fakeJob(
						function () {
							sleep(3);
							$this->sendMessage('Job Executed');
						}
					)
				);

			$this->fork(
				function () {
					try {
						// fetch the observer PID
						$this->fetchObserverPid();

						// the job should be executed, even if the heartbeat timeout is 1s. The observer should not kill
						// worker during processing a job because even if there is no timeout (neither for job nor for worker)
						$this->assertNextMessage('Job Executed', 4000000);
					}
					finally {
						$this->stopWorkers();
					}

					$this->assertObserverStopped();
				},
				function () {
					// send observer PID
					Event::listen(WorkerObserverStarted::class, function (WorkerObserverStarted $event) {
						$this->sendMessage("Observer started: {$event->getPid()}");
					});

					$this->assertWorkCommandSuccess([
						'connection'          => 'testing',
						'--queue'             => 'default',
						'--sleep'             => 0,
						'--timeout'           => 0,
						'--heartbeat-timeout' => 1,
					]);

				}
			);

		}

		public function testObserverDoesAdaptTimeoutOverMultipleCyclesAndDifferentTimeouts() {

			$queueMock = $this->fakeQueue();
			$queueMock
				->method('pop')
				->with('default')
				->willReturnOnConsecutiveCalls(
					$this->fakeJob(
						function () {
							sleep(1);
							$this->sendMessage('Job 1 Executed');
						},
						2
					),
					$this->fakeJob(
						function () {
							sleep(3);
							$this->sendMessage('Job 2 Executed');
						}
						// no timeout
					),
					$this->fakeJob(
						function () {
							// Turn off internal worker timeout handler, to simulate a stuck worker process
							// which does not terminate itself after job timeout
							pcntl_alarm(0);

							sleep(3);
							$this->sendMessage('Job 3 Executed');
						},
						1
					)
				);

			$this->fork(
				function () {
					try {
						// fetch the observer PID
						$this->fetchObserverPid();

						// the job should be executed, even if the heartbeat timeout is 1s. The observer should not kill
						// worker during processing
						$this->assertNextMessage('Job 1 Executed', 3000000);
						$this->assertNextMessage('Job 2 Executed', 4000000);

						// worker should be killed before last job finished
						$this->assertNoMessage(4);
					}
					finally {
						$this->stopWorkers();
					}

					$this->assertObserverStopped();
				},
				function () {
					// send observer PID
					Event::listen(WorkerObserverStarted::class, function (WorkerObserverStarted $event) {
						$this->sendMessage("Observer started: {$event->getPid()}");
					});

					$this->assertWorkCommandSuccess([
						'connection'          => 'testing',
						'--queue'             => 'default',
						'--sleep'             => 0,
						'--timeout'           => 0,
						'--heartbeat-timeout' => 1,
					]);

				}
			);

		}

	}