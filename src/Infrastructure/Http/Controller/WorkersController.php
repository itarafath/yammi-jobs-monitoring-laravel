<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Worker\Repository\WorkerRepository;
use Yammi\JobsMonitor\Presentation\ViewModel\WorkersViewModel;

/** @internal */
final class WorkersController extends Controller
{
    public function __invoke(Request $request, WorkerRepository $workers, ConfigRepository $config, JobRecordRepository $jobs): View
    {
        return view('jobs-monitor::workers', [
            'vm'                => $this->buildVm($request, $workers, $config),
            'queues'            => $this->horizonQueues($config, $jobs),
            'connections'       => $jobs->distinctConnections(),
            'horizonInstalled'  => $this->horizonInstalled(),
            'horizonStatus'     => $this->horizonStatus(),
            'horizonSupervisors'=> $this->horizonSupervisors(),
        ]);
    }

    /**
     * Returns only the inner content partial (no layout) so the JS
     * auto-refresh can swap the entire block without a full page load.
     */
    public function summary(Request $request, WorkerRepository $workers, ConfigRepository $config, JobRecordRepository $jobs): Response
    {
        $html = view('jobs-monitor::partials.workers-content', [
            'vm' => $this->buildVm($request, $workers, $config),
            'queues' => $this->horizonQueues($config, $jobs),
            'connections' => $jobs->distinctConnections(),
        ])->render();

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    private function buildVm(
        Request $request,
        WorkerRepository $workers,
        ConfigRepository $config,
    ): WorkersViewModel {
        return WorkersViewModel::build(
            repository: $workers,
            silentAfterSeconds: (int) $config->get('jobs-monitor.workers.silent_after_seconds', 120),
            expected: $this->parseExpected($config),
            now: new DateTimeImmutable,
            alivePage: max(1, (int) $request->query('page', '1')),
            silentPage: max(1, (int) $request->query('spage', '1')),
            deadPage: max(1, (int) $request->query('dpage', '1')),
            coveragePage: max(1, (int) $request->query('ppage', '1')),
        );
    }

    private function horizonInstalled(): bool
    {
        return class_exists('Laravel\Horizon\Horizon');
    }

    /**
     * @return list<array{name: string, status: string}>
     */
    private function horizonSupervisors(): array
    {
        if (! $this->horizonInstalled()) {
            return [];
        }

        try {
            /** @var object $repo */
            $repo = app('Laravel\Horizon\Contracts\SupervisorRepository');
            $supervisors = $repo->all();

            $mapped = array_map(static function (object $s): array {
                $parts = explode(':', $s->name, 2);

                return [
                    'name'         => $s->name,
                    'machine_id'   => $parts[0] ?? $s->name,
                    'display_name' => $parts[1] ?? $s->name,
                    'status'       => $s->status ?? 'unknown',
                ];
            }, $supervisors);

            usort($mapped, static fn (array $a, array $b) => strcmp($a['display_name'], $b['display_name']));

            return $mapped;
        } catch (\Throwable) {
            return [];
        }
    }

    private function horizonStatus(): string
    {
        if (! $this->horizonInstalled()) {
            return 'unknown';
        }

        try {
            /** @var object $repo */
            $repo = app('Laravel\Horizon\Contracts\MasterSupervisorRepository');
            $masters = $repo->all();

            if (empty($masters)) {
                return 'inactive';
            }

            foreach ($masters as $master) {
                if (($master->status ?? '') !== 'paused') {
                    return 'running';
                }
            }

            return 'paused';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * @return list<string>
     */
    private function horizonQueues(ConfigRepository $config, JobRecordRepository $jobs): array
    {
        if (! $this->horizonInstalled()) {
            return $jobs->distinctQueues();
        }

        /** @var array<string, array{queue?: list<string>}> $defaults */
        $defaults = (array) $config->get('horizon.defaults', []);

        $queues = [];
        foreach ($defaults as $worker) {
            $workerQueues = (array) ($worker['queue'] ?? []);
            foreach ($workerQueues as $queue) {
                if (is_string($queue) && $queue !== '') {
                    $queues[$queue] = true;
                }
            }
        }

        $list = array_keys($queues);
        sort($list);

        return $list;
    }

    /**
     * @return array<string, int>
     */
    private function parseExpected(ConfigRepository $config): array
    {
        /** @var array<mixed, mixed> $raw */
        $raw = (array) $config->get('jobs-monitor.workers.expected', []);

        $expected = [];
        foreach ($raw as $key => $value) {
            if (is_string($key) && $key !== '') {
                $expected[$key] = max(0, (int) $value);
            }
        }

        return $expected;
    }
}
