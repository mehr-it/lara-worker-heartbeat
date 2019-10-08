# Queue worker heartbeat for Laravel
This package implements a queue worker heartbeat which is observed by another process to detect
stuck or hanging queue worker processes.


## Why is a heartbeat necessary?
Laravel has a built in timeout handling for worker processes which uses SIGALRM to let worker
processes terminate themselves when they reach a given timeout. However this cannot handle edge
cases when the whole process is stuck in a way that signals are not processed anymore or the worker
gets stuck before the signal handler was registered.

Even supervisord is of no help in such cases because the worker process might still be running, but
not doing anything anymore.


## How does it work?
This package extends the `queue:work` command by the ability to fork an observer process which
monitors an implemented worker heartbeat. Laravel's queue worker is extended to send regular
heartbeat signals and status information to the observer process. When the observer process does
not receive a heartbeat signal within the expected period, it will kill the worker process.


## Installation

    composer require mehr-it/lara-worker-heartbeat
	
This package uses Laravel's package auto-discovery, so the service provider will be loaded 
automatically.

Make sure the PHP extensions `posix` and `pcntl` are loaded. Otherwise queue workers cannot fork
the required observer process and will throw an error.


## Usage
You don't have to make any changes to your application code to use this package. You only
have to pass the `--heartbeat-timeout` option to the queue work command:

    artisan queue:work default --heartbeat-timeout=5
    
This will start an observer thread expecting a heartbeat signal within every 5 seconds. Of course
no heartbeat is expected while the worker process is sleeping or handling a job taking longer
than 5s.

Choose the heartbeat timeout depending on the expected worker cycle duration (without any sleep
time). Usually this is a little more than the time it takes to query the queue for new jobs. So
most of the time 5s is a safe value. But in cases when the pop operation takes longer (eg. when
using AWS SQS with long polling) you have to increase the timeout.

## Implementation details
Heartbeat signals are sent on each iteration of the worker loop, which is looking for
new jobs in the queue.

When the worker is going to sleep or starts processing a job, it does not send any heartbeat
signals for some time. The worker will notify the observer about the period it will not be 
able to send any heartbeat and the observer will respect that.

If neither a timeout is set for the worker process nor for the currently processed job, the 
observer process does expect any heartbeat until the job is finished. In this situation the
observer cannot detect hung or stuck processes, because it does not know when to expect the
next heartbeat signal. 

The observer process automatically stops when the observed worker process is not running
anymore.

