<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Application\Action\ClearMetricsAction;
use Yammi\JobsMonitor\Application\Action\ClearQueueAction;
use Yammi\JobsMonitor\Application\Action\ForgetJobAction;
use Yammi\JobsMonitor\Application\Action\KillJobsByClassAction;
use Yammi\JobsMonitor\Application\Action\PurgeStuckJobsAction;
use Yammi\JobsMonitor\Domain\Job\Enum\JobStatus;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/**
 * @internal
 */
final class QueueControlApiController extends Controller
{
    // POST /queue/clear
    public function clearQueue(
        Request $request,
        ClearQueueAction $action,
    ): JsonResponse {
        $queueName = trim((string) $request->input('queue', ''));
        $connection = trim((string) $request->input('connection', '')) ?: null;

        if ($queueName === '') {
            return new JsonResponse(['error' => 'queue is required'], 422);
        }

        $result = $action($queueName, $connection);

        return new JsonResponse([
            'data' => $result,
            'message' => $result['driver_error'] !== null
                ? 'Queue driver clear failed, but monitoring records were cleaned up.'
                : 'Queue cleared successfully.',
        ]);
    }

    // POST /jobs/{uuid}/forget
    public function forgetJob(
        string $uuid,
        ForgetJobAction $action,
    ): JsonResponse {
        $deleted = $action(new JobIdentifier($uuid));

        if ($deleted === 0) {
            return new JsonResponse(['error' => 'No records found for this UUID.'], 404);
        }

        return new JsonResponse([
            'data' => ['deleted' => $deleted],
            'message' => 'Job records deleted.',
        ]);
    }

    // POST /jobs/kill-class
    public function killByClass(
        Request $request,
        KillJobsByClassAction $action,
    ): JsonResponse {
        $class = trim((string) $request->input('class', ''));
        $statusOption = trim((string) $request->input('status', ''));

        if ($class === '') {
            return new JsonResponse(['error' => 'class is required'], 422);
        }

        $statusFilter = null;
        if ($statusOption !== '') {
            $statusFilter = JobStatus::tryFrom($statusOption);
            if ($statusFilter === null) {
                return new JsonResponse(['error' => 'Invalid status. Use: processing, processed, failed.'], 422);
            }
        }

        $result = $action($class, $statusFilter);

        return new JsonResponse([
            'data' => $result,
            'message' => "Deleted {$result['deleted']} record(s) for class.",
        ]);
    }

    // GET /jobs/purge-stuck/preview
    public function purgeStuckPreview(
        Request $request,
        PurgeStuckJobsAction $action,
    ): JsonResponse {
        $olderThan = max(1, (int) $request->query('older_than', '150'));

        return new JsonResponse([
            'data' => [
                'count' => $action->preview($olderThan),
                'older_than_seconds' => $olderThan,
            ],
        ]);
    }

    // POST /jobs/purge-stuck
    public function purgeStuck(
        Request $request,
        PurgeStuckJobsAction $action,
    ): JsonResponse {
        $olderThan = max(1, (int) $request->input('older_than', 150));
        $purged = $action($olderThan);

        return new JsonResponse([
            'data' => ['purged' => $purged],
            'message' => "Marked {$purged} stuck job(s) as failed.",
        ]);
    }

    // POST /metrics/clear
    public function clearMetrics(
        Request $request,
        ClearMetricsAction $action,
    ): JsonResponse {
        $period = trim((string) $request->input('period', ''));
        $validPeriods = ['', '24h', '7d', '30d', 'all'];

        if (! in_array($period, $validPeriods, true)) {
            return new JsonResponse(['error' => 'Invalid period. Use: 24h, 7d, 30d, all, or omit.'], 422);
        }

        $result = $action($period !== '' ? $period : null);

        return new JsonResponse([
            'data' => $result,
            'message' => 'Metrics cleared.',
        ]);
    }
}
