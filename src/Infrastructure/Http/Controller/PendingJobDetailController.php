<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Application\Action\DeletePendingJobAction;
use Yammi\JobsMonitor\Application\Action\ForceStopJobAction;
use Yammi\JobsMonitor\Application\Action\PendingJobDetailAction;

/** @internal */
final class PendingJobDetailController extends Controller
{
    public function __construct(
        private readonly PendingJobDetailAction $detailAction,
        private readonly DeletePendingJobAction $deleteAction,
        private readonly ForceStopJobAction $forceStopAction,
    ) {}

    public function __invoke(string $jobId): View
    {
        $job = ($this->detailAction)($jobId);

        if ($job === null) {
            abort(404, 'Pending job not found.');
        }

        return view('jobs-monitor::pending-detail', [
            'job' => $job,
        ]);
    }

    public function delete(Request $request, string $jobId): RedirectResponse
    {
        $result = ($this->deleteAction)($jobId);

        if ($result['deleted']) {
            return redirect()
                ->route('jobs-monitor.pending')
                ->with('status', "Job {$jobId} deleted successfully.");
        }

        return redirect()
            ->route('jobs-monitor.pending.detail', ['jobId' => $jobId])
            ->with('error', $result['error'] ?? 'Failed to delete job.');
    }

    public function forceStop(Request $request, string $jobId): RedirectResponse
    {
        $result = ($this->forceStopAction)($jobId);

        if ($result['stopped']) {
            return redirect()
                ->route('jobs-monitor.pending')
                ->with('status', "Job {$jobId} force-stopped and marked as failed.");
        }

        return redirect()
            ->route('jobs-monitor.pending.detail', ['jobId' => $jobId])
            ->with('error', $result['error'] ?? 'Failed to force-stop job.');
    }
}
