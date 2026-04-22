<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Console\Command;

use Illuminate\Console\Command;
use Yammi\JobsMonitor\Application\Action\KillJobsByClassAction;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;

final class KillJobsByClassCommand extends Command
{
    /** @var string */
    protected $signature = 'jobs-monitor:kill-class
        {class : The fully-qualified job class name}
        {--status= : Restrict to a specific status: processing, processed, failed}
        {--dry-run : Preview without making changes}';

    /** @var string */
    protected $description = 'Delete all monitoring records for a given job class.';

    public function handle(KillJobsByClassAction $action): int
    {
        $class = (string) $this->argument('class');
        $statusOption = $this->option('status');
        $statusFilter = null;

        if (is_string($statusOption) && $statusOption !== '') {
            $statusFilter = JobStatus::tryFrom($statusOption);
            if ($statusFilter === null) {
                $this->error(sprintf('Invalid status "%s". Use: processing, processed, failed.', $statusOption));

                return self::FAILURE;
            }
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf('[dry-run] Would kill all%s records for class "%s".', $statusFilter ? " {$statusFilter->value}" : '', $class));

            return self::SUCCESS;
        }

        $result = $action($class, $statusFilter);

        $this->info(sprintf('Deleted %d record(s) for class "%s".', $result['deleted'], $class));

        if ($result['lock_released']) {
            $this->info('ShouldBeUnique lock released.');
        }

        return self::SUCCESS;
    }
}
