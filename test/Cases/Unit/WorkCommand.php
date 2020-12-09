<?php


	namespace MehrItLaraWorkerHeartbeatTest\Cases\Unit;


	use Illuminate\Console\Command;
	use MehrIt\LaraWorkerHeartbeat\Console\WorkCommand as QueueWorkCommand;
	use Symfony\Component\Console\Input\ArrayInput;
	use Symfony\Component\Console\Output\BufferedOutput;

	trait WorkCommand
	{
		protected $lastOutput = null;

		protected function getLastWorkCommandOutput() {
			return $this->lastOutput;
		}

		protected function callWorkCommand($parameters = []) {

			/** @var Command $cmd */
			$cmd = new QueueWorkCommand(app('queue.worker'), cache()->store());
			$cmd->setLaravel(app());


			$output = $output = new BufferedOutput();

			$exitCode = $cmd->run(
				new ArrayInput($parameters),
				$output
			);

			$this->lastOutput = $output->fetch();

			return $exitCode;
		}

		protected function assertWorkCommandSuccess($parameters = []) {
			$this->assertEquals(0, $this->callWorkCommand($parameters), 'Execution of command "queue:work" should succeed. Output: ' . $this->getLastWorkCommandOutput());
		}

		protected function assertWorkCommandFail($parameters = []) {
			$this->assertGreaterThan(0, $this->callWorkCommand($parameters), 'Execution of command "queue:work" should fail. Output: ' . $this->getLastWorkCommandOutput());
		}

		protected function assertLastWorkCommandOutContains($expected) {
			$out = $this->getLastWorkCommandOutput();

			$this->assertContains($expected, $out);
		}
	}