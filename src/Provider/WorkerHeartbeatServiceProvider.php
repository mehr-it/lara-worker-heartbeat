<?php


	namespace MehrIt\LaraWorkerHeartbeat\Provider;


	use Illuminate\Contracts\Debug\ExceptionHandler;
	use Illuminate\Support\ServiceProvider;
	use MehrIt\LaraWorkerHeartbeat\Console\WorkCommand as QueueWorkCommand;
	use MehrIt\LaraWorkerHeartbeat\Queue\Worker;

	class WorkerHeartbeatServiceProvider extends ServiceProvider
	{

		protected $defer = true;

		/**
		 * Register the service provider.
		 *
		 * @return void
		 */
		public function register() {
			$this->registerWorker();
			$this->registerQueueWorkCommand();
		}

		/**
		 * Register the queue worker.
		 *
		 * @return void
		 */
		protected function registerWorker() {
			$this->app->extend('queue.worker', function () {
				return new Worker(
					$this->app['queue'], $this->app['events'], $this->app[ExceptionHandler::class]
				);
			});
		}

		/**
		 * Registers the queue work command
		 */
		protected function registerQueueWorkCommand() {
			$this->app->extend('command.queue.work', function () {
				return new QueueWorkCommand($this->app['queue.worker']);
			});
		}

		/**
		 * Get the services provided by the provider.
		 *
		 * @return array
		 */
		public function provides() {
			return [
				'queue.worker',
				'command.queue.work',
			];
		}
	}