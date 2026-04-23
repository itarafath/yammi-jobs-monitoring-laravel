<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Job\Repository;

/**
 * Abstraction over pending/reserved job storage.
 *
 * Horizon stores pending jobs in Redis sorted sets; the database driver
 * stores them in the `jobs` table. This contract unifies both so the
 * application layer does not depend on a specific queue backend.
 */
interface PendingJobRepository
{
    /**
     * Fetch a paginated list of pending jobs, optionally filtered by queue.
     *
     * Each entry is a normalized stdClass with at minimum:
     *   - id          string  Job identifier (UUID for Horizon, int for DB)
     *   - name        string  Display name / job class
     *   - queue       string  Queue name
     *   - tags        array   Tag list (may be empty)
     *   - pushed_at   float   Unix timestamp when the job was pushed
     *   - status      string  "pending" | "reserved"
     *   - payload     string  Raw JSON payload
     *
     * @return list<object>
     */
    public function getPendingJobs(?string $queue, int $offset = 0, int $limit = 50): array;

    /**
     * Count pending jobs, optionally filtered by queue.
     */
    public function countPendingJobs(?string $queue = null): int;

    /**
     * Fetch a single job by its ID.
     *
     * Returns null if the job no longer exists.
     */
    public function getJobById(string $jobId): ?object;

    /**
     * Delete a pending (unreserved) job by ID.
     *
     * Returns true if the job was found and was still pending.
     * Returns false if the job was not found or was already reserved.
     */
    public function deletePendingJob(string $jobId): bool;

    /**
     * Force-stop a reserved (running) job by ID.
     *
     * Removes the job from the reserved queue and marks it as failed.
     * Returns true if the job was found and was reserved.
     * Returns false if the job was not found or was not reserved.
     */
    public function forceStopJob(string $jobId): bool;

    /**
     * Return the list of available queue names.
     *
     * @return list<string>
     */
    public function availableQueues(): array;

    /**
     * Whether this repository backend is available (e.g. Horizon is installed,
     * or the `jobs` table exists).
     */
    public function isAvailable(): bool;
}
