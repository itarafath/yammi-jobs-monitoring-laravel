<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Action;

use Yammi\JobsMonitor\Domain\Job\Repository\PendingJobRepository;

/**
 * Deletes a single pending job by its ID.
 *
 * Delegates to PendingJobRepository, which handles the backend-specific
 * logic (Redis/Horizon or database queue).
 *
 * Safety: verifies the job is still in "pending" status before deleting.
 * If the job has been picked up (status=reserved), aborts and suggests
 * using ForceStopJobAction instead.
 */
final class DeletePendingJobAction
{
    public function __construct(
        private readonly PendingJobRepository $repository,
    ) {}

    /**
     * @return array{deleted: bool, error?: string, job_id: string, status?: string}
     */
    public function __invoke(string $jobId): array
    {
        if (! $this->repository->isAvailable()) {
            return ['deleted' => false, 'error' => 'Queue backend not available', 'job_id' => $jobId];
        }

        try {
            // Check current status first
            $job = $this->repository->getJobById($jobId);

            if ($job === null) {
                return ['deleted' => false, 'error' => 'Job not found', 'job_id' => $jobId];
            }

            $status = $job->status ?? 'unknown';

            if ($status === 'reserved') {
                return [
                    'deleted' => false,
                    'error' => 'Job has been picked up by a worker (status=reserved). Use Force Stop instead.',
                    'job_id' => $jobId,
                    'status' => $status,
                ];
            }

            if ($status !== 'pending') {
                return [
                    'deleted' => false,
                    'error' => "Job is in '{$status}' status and cannot be deleted as pending.",
                    'job_id' => $jobId,
                    'status' => $status,
                ];
            }

            $deleted = $this->repository->deletePendingJob($jobId);

            if (! $deleted) {
                // Race condition: job was picked up between our check and delete
                return [
                    'deleted' => false,
                    'error' => 'Job could not be deleted — it may have been picked up by a worker. Try Force Stop.',
                    'job_id' => $jobId,
                    'status' => $status,
                ];
            }

            return [
                'deleted' => true,
                'job_id' => $jobId,
                'status' => $status,
            ];
        } catch (\Throwable $e) {
            return ['deleted' => false, 'error' => $e->getMessage(), 'job_id' => $jobId];
        }
    }
}
