<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Application\Action\ClearMetricsAction;
use Yammi\JobsMonitor\Application\Action\ClearQueueAction;

/**
 * @internal
 */
final class QueueControlApiController extends Controller
{
    // POST /queue/clear
    public function clearQueue(
        Request $request,
        ClearQueueAction $action,
    ): JsonResponse|RedirectResponse {
        $queueName = trim((string) $request->input('queue', ''));

        if ($queueName === '') {
            if ($request->expectsJson()) {
                return new JsonResponse(['error' => 'queue is required'], 422);
            }

            return redirect()->back()->with('error', 'Queue name is required.');
        }

        $result = $action($queueName);

        if ($result['horizon_cleared'] === true) {
            $message = "Horizon queue \"{$result['queue']}\" cleared.";
        } elseif ($result['horizon_cleared'] === false) {
            $message = 'Horizon queue clear failed.';
        } else {
            $message = 'Horizon is not installed.';
        }

        if ($request->expectsJson()) {
            return new JsonResponse([
                'data' => $result,
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('status', $message);
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
