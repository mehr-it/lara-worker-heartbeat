<?php


	namespace MehrIt\LaraWorkerHeartbeat\Provider;


	use Illuminate\Contracts\Debug\ExceptionHandler;
	use Illuminate\Queue\Console\WorkCommand as LaravelWorkCommand;
	use Illuminate\Queue\Worker as LaravelWorker;
	use Illuminate\Support\ServiceProvider;
	use MehrIt\LaraWorkerHeartbeat\Console\WorkCommand;
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
			$this->app->extend(LaravelWorker::class, function () {
				return $this->app['queue.worker'];
			});
			$this->app->singleton(Worker::class, function () {
				return $this->app['queue.worker'];
			});
		}

		/**
		 * Registers the queue work command
		 */
		protected function registerQueueWorkCommand() {
			$this->app->extend('command.queue.work', function () {
				return new WorkCommand($this->app['queue.worker']);
			});
			$this->app->extend(LaravelWorkCommand::class, function () {
				return $this->app['command.queue.work'];
			});
			$this->app->singleton(WorkCommand::class, function () {
				return $this->app['command.queue.work'];
			});
		}

		/**
		 * Get the services provided by the provider.
		 *
		 * @return array
		 */
		public function provides() {
			return [
				LaravelWorkCommand::class,
				LaravelWorker::class,
				Worker::class,
				WorkCommand::class,
				'queue.worker',
				'command.queue.work',
			];
		}
	}