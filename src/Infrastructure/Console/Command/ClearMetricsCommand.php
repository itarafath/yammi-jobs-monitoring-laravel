<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Console\Command;

use Illuminate\Console\Command;
use Yammi\JobsMonitor\Application\Action\ClearMetricsAction;

final class ClearMetricsCommand extends Command
{
    /** @var string */
    protected $signature = 'jobs-monitor:clear-metrics
        {--jobs= : Also delete job records. Options: 24h, 7d, 30d, all}
        {--dry-run : Show what would be deleted without making changes}';

    /** @var string */
    protected $description = 'Delete duration baselines and anomaly records. Optionally delete job records for a given period.';

    public function handle(ClearMetricsAction $action): int
    {
        $jobsPeriod = $this->option('jobs');
        $period = null;

        if (is_string($jobsPeriod) && $jobsPeriod !== '') {
            if (! in_array($jobsPeriod, ['24h', '7d', '30d', 'all'], true)) {
                $this->error(sprintf('Invalid --jobs value "%s". Use: 24h, 7d, 30d, all.', $jobsPeriod));

                return self::FAILURE;
            }
            $period = $jobsPeriod;
        }

        if ($this->option('dry-run')) {
            $this->info('[dry-run] Would delete: duration baselines, duration anomalies'
                .($period !== null ? ", job records ({$period})" : '').'.') ;

            return self::SUCCESS;
        }

        $result = $action($period);

        $this->info(sprintf('Deleted %d duration baseline(s).', $result['baselines_deleted']));
        $this->info(sprintf('Deleted %d anomaly record(s).', $result['anomalies_deleted']));

        if ($result['jobs_deleted'] > 0) {
            $this->info(sprintf('Deleted %d job record(s).', $result['jobs_deleted']));
        }

        return self::SUCCESS;
    }
}
