<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/**
 * Deletes all monitoring records for a given job UUID.
 *
 * Note: this only removes the monitoring record. If the job is currently
 * being processed by a worker it will continue to run — the worker just
 * will not update the monitoring table when it finishes.
 */
final class ForgetJobAction
{
    public function __construct(
        private readonly JobRecordRepository $repository,
    ) {}

    public function __invoke(JobIdentifier $id): int
    {
        return $this->repository->deleteByIdentifier($id);
    }
}
