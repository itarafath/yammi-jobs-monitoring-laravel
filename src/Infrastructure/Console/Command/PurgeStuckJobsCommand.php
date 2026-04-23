<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Console\Command;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Yammi\JobsMonitor\Application\Action\PurgeStuckJobsAction;

final class PurgeStuckJobsCommand extends Command
{
    /** @var string */
    protected $signature = 'jobs-monitor:purge-stuck
        {--older-than= : Mark processing jobs stuck for longer than this many seconds as failed (default: queue retry_after + 60)}
        {--dry-run : Show how many records would be affected without making changes}';

    /** @var string */
    protected $description = 'Mark stuck processing jobs as failed. A job is "stuck" when it has been processing longer than the retry_after threshold.';

    public function handle(PurgeStuckJobsAction $action, ConfigRepository $config): int
    {
        $olderThan = $this->option('older-than');

        if ($olderThan !== null) {
            $seconds = max(1, (int) $olderThan);
        } else {
            // Default: queue retry_after (90s) + 60s buffer
            $retryAfter = (int) $config->get('queue.connections.'.$config->get('queue.default').'.retry_after', 90);
            $seconds = $retryAfter + 60;
        }

        if ($this->option('dry-run')) {
            $count = $action->preview($seconds);
            $this->info(sprintf('[dry-run] %d job(s) would be marked as failed (stuck > %ds).', $count, $seconds));

            return self::SUCCESS;
        }

        $purged = $action($seconds);
        $this->info(sprintf('Marked %d stuck job(s) as failed (threshold: %ds).', $purged, $seconds));

        return self::SUCCESS;
    }
}
