<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Job\Repository\DurationBaselineRepository;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;

/**
 * Clears duration baselines, anomaly records, and optionally job records
 * within a given period.
 *
 * Baselines are always cleared — they can be rebuilt by running
 * jobs-monitor:refresh-duration-baselines.
 */
final class ClearMetricsAction
{
    public function __construct(
        private readonly JobRecordRepository $jobRepository,
        private readonly DurationBaselineRepository $baselineRepository,
    ) {}

    /**
     * @return array{baselines_deleted: int, anomalies_deleted: int, jobs_deleted: int}
     */
    public function __invoke(?string $period = null): array
    {
        $baselinesDeleted = $this->baselineRepository->deleteAllBaselines();
        $anomaliesDeleted = $this->baselineRepository->deleteAllAnomalies();

        $jobsDeleted = match ($period) {
            'all' => $this->jobRepository->deleteAll(),
            '30d' => $this->jobRepository->deleteBefore(new \DateTimeImmutable('-30 days')),
            '7d' => $this->jobRepository->deleteBefore(new \DateTimeImmutable('-7 days')),
            '24h' => $this->jobRepository->deleteBefore(new \DateTimeImmutable('-24 hours')),
            default => 0,
        };

        return [
            'baselines_deleted' => (int) $baselinesDeleted,
            'anomalies_deleted' => (int) $anomaliesDeleted,
            'jobs_deleted' => $jobsDeleted,
        ];
    }
}
