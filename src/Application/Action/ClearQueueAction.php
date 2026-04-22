<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;

/**
 * Clears all pending jobs from a queue in both the actual queue driver
 * and the monitoring records table.
 *
 * Queue::clear() is not supported by all drivers (e.g. SQS). When the
 * driver throws, we surface the driver error but still clean up the
 * monitoring records so the dashboard stays consistent.
 */
final class ClearQueueAction
{
    public function __construct(
        private readonly JobRecordRepository $repository,
        private readonly QueueFactory $queue,
    ) {}

    /**
     * @return array{driver_cleared: bool, driver_error: string|null, monitor_deleted: int}
     */
    public function __invoke(string $queue, ?string $connection = null): array
    {
        $driverCleared = false;
        $driverError = null;

        try {
            $conn = $connection !== null && $connection !== ''
                ? $this->queue->connection($connection)
                : $this->queue->connection();

            $conn->clear($queue);
            $driverCleared = true;
        } catch (\Throwable $e) {
            $driverError = $e->getMessage();
        }

        $monitorDeleted = $this->repository->deleteByQueue($queue, $connection);

        return [
            'driver_cleared' => $driverCleared,
            'driver_error' => $driverError,
            'monitor_deleted' => $monitorDeleted,
        ];
    }
}
