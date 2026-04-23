<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Job\Repository\PendingJobRepository;

/**
 * Fetches pending jobs via the PendingJobRepository contract.
 *
 * Works with both Horizon (Redis) and database queue drivers.
 */
final class PendingJobsAction
{
    public function __construct(
        private readonly PendingJobRepository $repository,
    ) {}

    /**
     * @return array{jobs: list<array{id: string, name: string, short_name: string, queue: string, tags: list<string>, pushed_at: float|null, delayed: bool}>, total: int}
     */
    public function __invoke(int $startingAt = -1, int $limit = 50, string $queue = ''): array
    {
        if (! $this->repository->isAvailable()) {
            return ['jobs' => [], 'total' => 0];
        }

        try {
            $offset = $startingAt >= 0 ? $startingAt + 1 : 0;
            $queueFilter = $queue !== '' ? $queue : null;

            $rawJobs = $this->repository->getPendingJobs($queueFilter, $offset, $limit);
            $total = $this->repository->countPendingJobs($queueFilter);

            $jobs = array_map(function (object $job): array {
                $payload = is_string($job->payload) ? json_decode($job->payload) : $job->payload;

                $name = $job->name ?? $payload->displayName ?? 'Unknown';
                $tags = is_array($job->tags) ? $job->tags : [];
                $pushedAt = $job->pushed_at ?? null;

                // Detect delayed jobs
                $delayed = false;
                if (isset($payload->data->command)) {
                    try {
                        $unserialized = @unserialize($payload->data->command);
                        if ($unserialized && property_exists($unserialized, 'delay') && $unserialized->delay) {
                            $delayed = true;
                        }
                    } catch (\Throwable) {
                        // not a serialized PHP object — ignore
                    }
                }

                return [
                    'id'         => $job->id ?? '',
                    'name'       => $name,
                    'short_name' => $this->shortClass($name),
                    'queue'      => $job->queue ?? '',
                    'tags'       => array_slice($tags, 0, 5),
                    'pushed_at'  => $pushedAt !== null ? (float) $pushedAt : null,
                    'delayed'    => $delayed,
                ];
            }, $rawJobs);

            return [
                'jobs'  => $jobs,
                'total' => $total,
            ];
        } catch (\Throwable) {
            return ['jobs' => [], 'total' => 0];
        }
    }

    private function shortClass(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts) ?: $class;
    }
}
