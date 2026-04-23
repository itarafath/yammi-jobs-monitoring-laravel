<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\Arr;

/**
 * Clears all pending jobs from a Horizon queue.
 *
 * Mirrors Horizon's own ClearCommand logic:
 *  1. Resolves the queue connection from horizon.defaults config
 *  2. Purges job metadata via Horizon's JobRepository
 *  3. Clears the actual Redis queue via QueueManager
 */
final class ClearQueueAction
{
    public function __construct(private readonly QueueFactory $queue) {}

    /**
     * @return array{horizon_cleared: bool|null, queue: string, purged: int}
     */
    public function __invoke(string $queue): array
    {
        if (! class_exists(\Laravel\Horizon\Horizon::class)) {
            return ['horizon_cleared' => null, 'queue' => $queue, 'purged' => 0];
        }

        try {
            /** @var string $connection */
            $connection = Arr::first(config('horizon.defaults', []))['connection'] ?? 'redis';

            // Purge Horizon job metadata (same as horizon:clear step 1)
            $purged = 0;
            try {
                /** @var object $jobRepository */
                $jobRepository = app(\Laravel\Horizon\Contracts\JobRepository::class);
                if (method_exists($jobRepository, 'purge')) {
                    $jobRepository->purge($queue);
                    $purged = 1;
                }
            } catch (\Throwable) {
                // purge is best-effort
            }

            // Clear the actual Redis queue (same as horizon:clear step 2)
            $this->queue->connection($connection)->clear($queue);

            return ['horizon_cleared' => true, 'queue' => $queue, 'purged' => $purged];
        } catch (\Throwable) {
            return ['horizon_cleared' => false, 'queue' => $queue, 'purged' => 0];
        }
    }
}
