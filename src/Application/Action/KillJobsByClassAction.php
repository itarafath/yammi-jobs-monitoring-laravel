<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;

/**
 * Deletes monitoring records for all jobs of a given class, optionally
 * filtered to a specific status.
 *
 * Also releases any ShouldBeUnique cache lock held by the class so the
 * job can be re-dispatched immediately after the kill.
 */
final class KillJobsByClassAction
{
    public function __construct(
        private readonly JobRecordRepository $repository,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * @return array{deleted: int, lock_released: bool}
     */
    public function __invoke(string $jobClass, ?JobStatus $statusFilter = null): array
    {
        $deleted = $this->repository->deleteByClass($jobClass, $statusFilter);

        // Release ShouldBeUnique atomic lock so the class can be re-dispatched.
        $lockKey = 'laravel_unique_job:'.$jobClass;
        $lockReleased = $this->cache->forget($lockKey);

        return [
            'deleted' => $deleted,
            'lock_released' => $lockReleased,
        ];
    }
}
