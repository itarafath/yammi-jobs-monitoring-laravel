<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Job\Repository\PendingJobRepository;

/**
 * Force-stops a running (reserved) job and marks it as failed.
 *
 * Delegates to PendingJobRepository, which handles the backend-specific
 * logic (Redis/Horizon or database queue).
 *
 * This cannot kill the actual PHP process running the job — that would
 * require posix_kill() with the worker PID. Instead, this removes the
 * job from the reserved queue. The worker will eventually notice and
 * release it. The job will NOT be retried.
 */
final class ForceStopJobAction
{
    public function __construct(
        private readonly PendingJobRepository $repository,
    ) {}

    /**
     * @return array{stopped: bool, error?: string, job_id: string, status?: string}
     */
    public function __invoke(string $jobId): array
    {
        if (! $this->repository->isAvailable()) {
            return ['stopped' => false, 'error' => 'Queue backend not available', 'job_id' => $jobId];
        }

        try {
            // Check current status first
            $job = $this->repository->getJobById($jobId);

            if ($job === null) {
                return ['stopped' => false, 'error' => 'Job not found', 'job_id' => $jobId];
            }

            $status = $job->status ?? 'unknown';

            if ($status === 'pending') {
                return [
                    'stopped' => false,
                    'error' => 'Job is still pending (not yet running). Use Delete instead.',
                    'job_id' => $jobId,
                    'status' => $status,
                ];
            }

            if ($status !== 'reserved') {
                return [
                    'stopped' => false,
                    'error' => "Job is in '{$status}' status and cannot be force-stopped.",
                    'job_id' => $jobId,
                    'status' => $status,
                ];
            }

            $stopped = $this->repository->forceStopJob($jobId);

            if (! $stopped) {
                return [
                    'stopped' => false,
                    'error' => 'Job could not be force-stopped — it may have already completed or failed.',
                    'job_id' => $jobId,
                    'status' => $status,
                ];
            }

            return [
                'stopped' => true,
                'job_id'  => $jobId,
                'status'  => $status,
            ];
        } catch (\Throwable $e) {
            return ['stopped' => false, 'error' => $e->getMessage(), 'job_id' => $jobId];
        }
    }
}
