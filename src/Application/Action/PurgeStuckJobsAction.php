<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;

/**
 * Marks all processing records older than the given threshold as failed.
 *
 * A job is considered "stuck" when its started_at is older than
 * $olderThanSeconds and it still has status=processing — meaning either
 * the worker crashed before it could update the record, or the job is
 * running far beyond its expected duration.
 *
 * This only mutates monitoring records; it cannot interrupt a running
 * PHP process.
 */
final class PurgeStuckJobsAction
{
    private const PURGE_EXCEPTION = 'Forcibly purged by jobs-monitor: job was stuck in processing state.';

    public function __construct(
        private readonly JobRecordRepository $repository,
    ) {}

    public function preview(int $olderThanSeconds): int
    {
        return $this->repository->countStuckProcessing($olderThanSeconds);
    }

    public function __invoke(int $olderThanSeconds): int
    {
        return $this->repository->markStuckAsFailed($olderThanSeconds, self::PURGE_EXCEPTION);
    }
}
