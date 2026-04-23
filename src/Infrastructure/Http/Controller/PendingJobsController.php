<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Application\Action\PendingJobsAction;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\Repository\PendingJobRepository;

/** @internal */
final class PendingJobsController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly PendingJobsAction $pendingJobs,
        private readonly PendingJobRepository $pendingRepo,
        private readonly JobRecordRepository $jobs,
    ) {}

    public function __invoke(Request $request): View
    {
        $page = max(1, (int) $request->query('page', '1'));
        $queue = trim((string) $request->query('queue', ''));
        $startingAt = ($page - 1) * self::PER_PAGE - 1;

        $result = ($this->pendingJobs)($startingAt, self::PER_PAGE, $queue);

        $lastPage = max(1, (int) ceil($result['total'] / self::PER_PAGE));

        return view('jobs-monitor::pending', [
            'jobs'              => $result['jobs'],
            'total'             => $result['total'],
            'page'              => $page,
            'lastPage'          => $lastPage,
            'perPage'           => self::PER_PAGE,
            'queue'             => $queue,
            'availableQueues'   => $this->availableQueues(),
            'backendAvailable'  => $this->pendingRepo->isAvailable(),
        ]);
    }

    /**
     * Returns only the inner content partial (no layout) for auto-refresh.
     */
    public function summary(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', '1'));
        $queue = trim((string) $request->query('queue', ''));
        $startingAt = ($page - 1) * self::PER_PAGE - 1;

        $result = ($this->pendingJobs)($startingAt, self::PER_PAGE, $queue);

        $lastPage = max(1, (int) ceil($result['total'] / self::PER_PAGE));

        $html = view('jobs-monitor::partials.pending-content', [
            'jobs'            => $result['jobs'],
            'total'           => $result['total'],
            'page'            => $page,
            'lastPage'        => $lastPage,
            'perPage'         => self::PER_PAGE,
            'queue'           => $queue,
            'availableQueues' => $this->availableQueues(),
        ])->render();

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    /**
     * @return list<string>
     */
    private function availableQueues(): array
    {
        // Merge queues from the pending job repository (Horizon or DB)
        // with the historical queues from jobs_monitor table.
        $queues = $this->pendingRepo->availableQueues();

        $historical = $this->jobs->distinctQueues();

        $queues = array_unique(array_merge($queues, $historical));
        sort($queues);

        return $queues;
    }
}
