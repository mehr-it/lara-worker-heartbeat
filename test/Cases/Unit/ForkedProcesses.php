<?php


	namespace MehrItLaraWorkerHeartbeatTest\Cases\Unit;


	trait ForkedProcesses
	{
		protected $childPid;

		protected $ipcSocket;

		/**
		 * Forks the process and executes the given callbacks
		 * @param callable $parent The parent callback
		 * @param callable $child The child callback
		 */
		protected function fork(callable $parent, callable $child) {

			$sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
			$pid = pcntl_fork();
			if ($pid === -1)
				$this->fail('Failed to fork child process');

			if ($pid) {
				// parent

				$this->childPid = $pid;

				// let children die instantly (avoid zombies)
				pcntl_signal(SIGCHLD, SIG_IGN);

				// close socket
				fclose($sockets[0]);
				$this->ipcSocket = $sockets[1];

				// non-blocking read
				stream_set_blocking($sockets[1], false);

				call_user_func($parent, $sockets[1], $pid);

				$this->waitForChild();
			}
			else {
				// child

				// close socket
				fclose($sockets[1]);
				$this->ipcSocket = $sockets[0];

				stream_set_blocking($sockets[0], false);

				call_user_func($child, $sockets[0]);

				// close socket
				fclose($sockets[0]);

				die();
			}
		}

		protected function waitFor(callable $callback, $timeout = 5000000, $interval = 100000) {
			$start = microtime(true);

			while(!call_user_func($callback)) {
				if (microtime(true) - $start > $timeout / 1000000) {
					$this->fail('Timeout waiting for callback');
					break;
				}

				usleep($interval);
			}
		}

		protected function waitForChild($timeout = 10000000, $interval = 100000) {

			$this->waitFor(function() {
				return !posix_kill($this->childPid, 0);
			}, $timeout, $interval);

		}

		protected function assertDurationLessThan($expectedMicroTime, callable $fn) {
			$ts = microtime(true);

			call_user_func($fn);

			$duration = microtime(true) - $ts;

			if ($duration > $expectedMicroTime)
				$this->fail("Operation should not take less than {$expectedMicroTime}s, but took " . round($duration, 3) . 's');
		}

		protected function assertDurationGreaterThan($expectedMicroTime, callable $fn) {
			$ts = microtime(true);

			call_user_func($fn);

			$duration = microtime(true) - $ts;

			if ($duration < $expectedMicroTime)
				$this->fail("Operation should take more than {$expectedMicroTime}s, but took " . round($duration, 3) . 's');
		}

		protected function getNextMessage($timeout = 1000000) {
			$timeWaited = 0;

			while (!($lastRead = fgets($this->ipcSocket))) {
				usleep(50000);
				$timeWaited += 50000;
				if ($timeWaited > $timeout)
					$this->fail('Timeout waiting for message.');
			}

			return $lastRead;
		}

		protected function assertNextMessage($expected, $timeout = 1000000) {
			$timeWaited = 0;

			while (!($lastRead = fgets($this->ipcSocket))) {
				usleep(50000);
				$timeWaited += 50000;
				if ($timeWaited > $timeout)
					$this->fail('Timeout waiting for message "' . $expected . '"');
			}
			$this->assertEquals($expected, $lastRead);
		}

		protected function assertNoMessage($forDurationSeconds) {
			sleep($forDurationSeconds);
			$this->assertEquals(null, fgets($this->ipcSocket));
		}


		protected function sendMessage($message) {
			fwrite($this->ipcSocket, $message);
		}
	}