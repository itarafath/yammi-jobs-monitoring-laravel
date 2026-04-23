<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Repository;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Arr;
use Yammi\JobsMonitor\Domain\Job\Repository\PendingJobRepository;

/**
 * Horizon (Redis) implementation of PendingJobRepository.
 *
 * Reads pending jobs from Horizon's sorted sets and Redis hashes,
 * and deletes/force-stops them via direct Redis operations.
 */
final class HorizonPendingJobRepository implements PendingJobRepository
{
    public function __construct(
        private readonly RedisFactory $redis,
    ) {}

    public function isAvailable(): bool
    {
        return class_exists(\Laravel\Horizon\Horizon::class);
    }

    public function getPendingJobs(?string $queue = null, int $offset = 0, int $limit = 50): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        try {
            /** @var \Laravel\Horizon\Contracts\JobRepository $repo */
            $repo = app(\Laravel\Horizon\Contracts\JobRepository::class);

            // Horizon paginates via "starting_at" index, not offset.
            // We fetch enough pages to cover the requested offset+limit.
            $startingAt = -1;
            $allJobs = collect();
            $fetched = 0;
            $needed = $offset + $limit;

            while ($fetched < $needed) {
                $chunk = $repo->getPending($startingAt);
                if ($chunk->isEmpty()) {
                    break;
                }
                $allJobs = $allJobs->merge($chunk);
                $startingAt = $chunk->last()->index ?? $startingAt + 50;
                $fetched = $allJobs->count();
            }

            // Filter by queue if specified
            if ($queue !== null && $queue !== '') {
                $allJobs = $allJobs->filter(
                    static fn (object $job): bool => ($job->queue ?? '') === $queue,
                );
            }

            // Apply offset/limit
            $jobs = $allJobs->slice($offset, $limit)->values();

            return $jobs->map(function (object $job): object {
                $payload = json_decode($job->payload ?? '{}', false);

                $tags = [];
                if (is_array($payload->tags ?? null)) {
                    $tags = $payload->tags;
                }

                $pushedAt = null;
                if (isset($job->pushedAt) && is_numeric($job->pushedAt)) {
                    $pushedAt = (float) $job->pushedAt;
                } elseif (isset($payload->pushedAt) && is_numeric($payload->pushedAt)) {
                    $pushedAt = (float) $payload->pushedAt;
                }

                $name = $payload->displayName ?? $payload->data->commandName ?? $job->name ?? 'Unknown';

                return (object) [
                    'id'        => $job->id ?? '',
                    'name'      => $name,
                    'queue'     => $job->queue ?? '',
                    'tags'      => $tags,
                    'pushed_at' => $pushedAt,
                    'status'    => $job->status ?? 'pending',
                    'payload'   => $job->payload ?? '{}',
                ];
            })->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function countPendingJobs(?string $queue = null): int
    {
        if (! $this->isAvailable()) {
            return 0;
        }

        try {
            /** @var \Laravel\Horizon\Contracts\JobRepository $repo */
            $repo = app(\Laravel\Horizon\Contracts\JobRepository::class);

            if ($queue === null || $queue === '') {
                return $repo->countPending();
            }

            // Count only jobs on the given queue
            $allJobs = collect();
            $startingAt = -1;

            do {
                $chunk = $repo->getPending($startingAt);
                if ($chunk->isEmpty()) {
                    break;
                }
                $allJobs = $allJobs->merge($chunk);
                $startingAt = $chunk->last()->index ?? $startingAt + 50;
            } while (true);

            return $allJobs->filter(
                static fn (object $job): bool => ($job->queue ?? '') === $queue,
            )->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function getJobById(string $jobId): ?object
    {
        if (! $this->isAvailable()) {
            return null;
        }

        try {
            /** @var \Laravel\Horizon\Contracts\JobRepository $repo */
            $repo = app(\Laravel\Horizon\Contracts\JobRepository::class);
            $jobs = $repo->getJobs([$jobId]);

            if ($jobs->isEmpty()) {
                return null;
            }

            $job = $jobs->first();
            $payload = json_decode($job->payload ?? '{}', false);

            $tags = [];
            if (is_array($payload->tags ?? null)) {
                $tags = $payload->tags;
            }

            $pushedAt = null;
            if (isset($job->pushedAt) && is_numeric($job->pushedAt)) {
                $pushedAt = (float) $job->pushedAt;
            } elseif (isset($payload->pushedAt) && is_numeric($payload->pushedAt)) {
                $pushedAt = (float) $payload->pushedAt;
            }

            $name = $payload->displayName ?? $payload->data->commandName ?? $job->name ?? 'Unknown';

            return (object) [
                'id'         => $job->id ?? $jobId,
                'name'       => $name,
                'queue'      => $job->queue ?? '',
                'connection' => $job->connection ?? '',
                'tags'       => $tags,
                'pushed_at'  => $pushedAt,
                'status'     => $job->status ?? 'pending',
                'payload'    => $job->payload ?? '{}',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function deletePendingJob(string $jobId): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        try {
            $prefix = config('horizon.prefix', 'horizon:');
            $connection = $this->redis->connection('horizon');

            $status = $connection->hget($jobId, 'status');
            $queue = $connection->hget($jobId, 'queue');
            $payload = $connection->hget($jobId, 'payload');

            if ($status === null || $status === false) {
                return false;
            }

            if ($status !== 'pending') {
                return false;
            }

            // Remove from Horizon sorted sets
            $connection->zrem('pending_jobs', $jobId);
            $connection->zrem('recent_jobs', $jobId);

            // Delete the job hash
            $connection->del($jobId);

            // Remove from the actual Redis queue list
            if ($queue && $payload) {
                $queueKey = $prefix . 'queue:' . $queue;
                $connection->lrem($queueKey, 0, $payload);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function forceStopJob(string $jobId): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        try {
            $prefix = config('horizon.prefix', 'horizon:');
            $connection = $this->redis->connection('horizon');

            $status = $connection->hget($jobId, 'status');
            $queue = $connection->hget($jobId, 'queue');
            $payload = $connection->hget($jobId, 'payload');

            if ($status === null || $status === false) {
                return false;
            }

            if ($status !== 'reserved') {
                return false;
            }

            // Remove from the Redis reserved queue list
            if ($queue && $payload) {
                $reservedKey = $prefix . 'queue:' . $queue . ':reserved';
                $connection->lrem($reservedKey, 0, $payload);

                $delayedKey = $prefix . 'queue:' . $queue . ':delayed';
                $connection->lrem($delayedKey, 0, $payload);
            }

            // Remove from Horizon sorted sets
            $connection->zrem('pending_jobs', $jobId);
            $connection->zrem('recent_jobs', $jobId);

            // Mark as failed in Horizon metadata
            $now = str_replace(',', '.', microtime(true));
            $connection->hmset($jobId, [
                'status'     => 'failed',
                'exception'  => 'Force-stopped by operator via Yammi Jobs Monitor',
                'failed_at'  => $now,
                'updated_at' => $now,
            ]);

            // Add to failed_jobs and recent_failed_jobs sorted sets
            $connection->zadd('failed_jobs', $now * -1, $jobId);
            $connection->zadd('recent_failed_jobs', $now * -1, $jobId);

            // Remove from pending/completed/silenced sets (safety)
            $connection->zrem('completed_jobs', $jobId);
            $connection->zrem('silenced_jobs', $jobId);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function availableQueues(): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        try {
            /** @var array<string, array{queue?: list<string>}> $defaults */
            $defaults = (array) config('horizon.defaults', []);

            $queues = [];
            foreach ($defaults as $worker) {
                $workerQueues = (array) ($worker['queue'] ?? []);
                foreach ($workerQueues as $queue) {
                    if (is_string($queue) && $queue !== '') {
                        $queues[$queue] = true;
                    }
                }
            }

            $list = array_keys($queues);
            sort($list);

            return $list;
        } catch (\Throwable) {
            return [];
        }
    }
}
