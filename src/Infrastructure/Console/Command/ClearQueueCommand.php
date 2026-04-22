<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Console\Command;

use Illuminate\Console\Command;
use Yammi\JobsMonitor\Application\Action\ClearQueueAction;

final class ClearQueueCommand extends Command
{
    /** @var string */
    protected $signature = 'jobs-monitor:clear-queue
        {queue : The name of the queue to clear}
        {--connection= : The queue connection name (uses default when omitted)}
        {--dry-run : Preview how many monitoring records would be deleted without making changes}';

    /** @var string */
    protected $description = 'Delete all jobs from the specified queue and remove their monitoring records.';

    public function handle(ClearQueueAction $action): int
    {
        $queue = (string) $this->argument('queue');
        $connection = $this->option('connection') !== null ? (string) $this->option('connection') : null;

        if ($this->option('dry-run')) {
            $this->info(sprintf('[dry-run] Would clear queue "%s"%s.', $queue, $connection ? " on connection \"{$connection}\"" : ''));

            return self::SUCCESS;
        }

        $result = $action($queue, $connection);

        if ($result['driver_error'] !== null) {
            $this->warn(sprintf('Queue driver clear failed: %s', $result['driver_error']));
            $this->warn('Monitoring records were still cleaned up.');
        } else {
            $this->info('Queue driver cleared successfully.');
        }

        $this->info(sprintf('Deleted %d monitoring record(s) for queue "%s".', $result['monitor_deleted'], $queue));

        return self::SUCCESS;
    }
}
