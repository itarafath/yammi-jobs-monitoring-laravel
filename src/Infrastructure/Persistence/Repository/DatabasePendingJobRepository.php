<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Persistence\Repository;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Yammi\JobsMonitor\Domain\Job\Repository\PendingJobRepository;

/**
 * Database implementation of PendingJobRepository.
 *
 * Queries Laravel's native `jobs` table (created by `queue:table`).
 * This works for the `database` queue driver without Horizon.
 *
 * Laravel's `jobs` table schema:
 *   id           bigint PK (auto-increment)
 *   queue        string
 *   payload      longText (JSON)
 *   attempts     unsignedInteger
 *   reserved_at  unsignedInteger nullable (null = pending, set = reserved)
 *   available_at unsignedInteger
 *   created_at   unsignedInteger
 */
final class DatabasePendingJobRepository implements PendingJobRepository
{
    private function table(): Builder
    {
        return DB::connection($this->connectionName())->table($this->tableName());
    }

    private function connectionName(): ?string
    {
        return config('queue.connections.database.connection');
    }

    private function tableName(): string
    {
        return config('queue.connections.database.table', 'jobs');
    }

    public function isAvailable(): bool
    {
        try {
            $table = $this->tableName();
            $conn = DB::connection($this->connectionName());

            // Check the queue driver is database
            $driver = config('queue.default');
            if ($driver !== 'database') {
                // Also available if Horizon is not installed but driver is database
                // Allow even if Horizon is installed — user might have mixed queues
            }

            // Check the table exists
            return $conn->getSchemaBuilder()->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    public function getPendingJobs(?string $queue = null, int $offset = 0, int $limit = 50): array
    {
        try {
            $query = $this->table()
                ->orderBy('created_at', 'asc')
                ->offset($offset)
                ->limit($limit);

            if ($queue !== null && $queue !== '') {
                $query->where('queue', $queue);
            }

            $rows = $query->get();

            return $rows->map(function (object $row): object {
                $payload = json_decode($row->payload ?? '{}', false);

                $name = $payload->displayName
                    ?? $payload->data->commandName
                    ?? $payload->job
                    ?? 'Unknown';

                $tags = [];
                if (is_array($payload->tags ?? null)) {
                    $tags = $payload->tags;
                }

                $pushedAt = null;
                if (isset($row->created_at) && is_numeric($row->created_at)) {
                    $pushedAt = (float) $row->created_at;
                }

                $isReserved = $row->reserved_at !== null;

                return (object) [
                    'id'        => (string) $row->id,
                    'name'      => $name,
                    'queue'     => $row->queue ?? '',
                    'tags'      => $tags,
                    'pushed_at' => $pushedAt,
                    'status'    => $isReserved ? 'reserved' : 'pending',
                    'payload'   => $row->payload ?? '{}',
                ];
            })->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function countPendingJobs(?string $queue = null): int
    {
        try {
            $query = $this->table();

            if ($queue !== null && $queue !== '') {
                $query->where('queue', $queue);
            }

            return $query->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function getJobById(string $jobId): ?object
    {
        try {
            $row = $this->table()->where('id', (int) $jobId)->first();

            if ($row === null) {
                return null;
            }

            $payload = json_decode($row->payload ?? '{}', false);

            $name = $payload->displayName
                ?? $payload->data->commandName
                ?? $payload->job
                ?? 'Unknown';

            $tags = [];
            if (is_array($payload->tags ?? null)) {
                $tags = $payload->tags;
            }

            $pushedAt = null;
            if (isset($row->created_at) && is_numeric($row->created_at)) {
                $pushedAt = (float) $row->created_at;
            }

            $isReserved = $row->reserved_at !== null;

            return (object) [
                'id'         => (string) $row->id,
                'name'       => $name,
                'queue'      => $row->queue ?? '',
                'connection' => 'database',
                'tags'       => $tags,
                'pushed_at'  => $pushedAt,
                'status'     => $isReserved ? 'reserved' : 'pending',
                'payload'    => $row->payload ?? '{}',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function deletePendingJob(string $jobId): bool
    {
        try {
            // Only delete if the job is NOT reserved (still pending)
            $deleted = $this->table()
                ->where('id', (int) $jobId)
                ->whereNull('reserved_at')
                ->delete();

            return $deleted > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function forceStopJob(string $jobId): bool
    {
        try {
            // Only force-stop if the job IS reserved (running)
            $deleted = $this->table()
                ->where('id', (int) $jobId)
                ->whereNotNull('reserved_at')
                ->delete();

            return $deleted > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function availableQueues(): array
    {
        try {
            /** @var list<string> $queues */
            $queues = $this->table()
                ->distinct()
                ->orderBy('queue')
                ->pluck('queue')
                ->values()
                ->all();

            return $queues;
        } catch (\Throwable) {
            return [];
        }
    }
}
