<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Console\Command;

use Illuminate\Console\Command;
use Yammi\JobsMonitor\Application\Action\ClearQueueAction;

final class ClearQueueCommand extends Command
{
    /** @var string */
    protected $signature = 'jobs-monitor:clear-queue
        {queue : The name of the queue to clear}';

    /** @var string */
    protected $description = 'Clear all pending jobs from a Horizon queue.';

    public function handle(ClearQueueAction $action): int
    {
        $queue = (string) $this->argument('queue');

        $result = $action($queue);

        if ($result['horizon_cleared'] === true) {
            $this->info(sprintf('Horizon queue "%s" cleared.', $result['queue']));
            if ($result['purged']) {
                $this->info('Horizon job metadata purged.');
            }
        } elseif ($result['horizon_cleared'] === false) {
            $this->error('Horizon queue clear failed.');

            return self::FAILURE;
        } else {
            $this->error('Horizon is not installed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
