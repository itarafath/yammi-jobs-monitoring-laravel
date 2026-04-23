<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** @internal */
final class HorizonController extends Controller
{
    public function pause(): RedirectResponse
    {
        try {
            /** @var object $repo */
            $repo = app('Laravel\Horizon\Contracts\MasterSupervisorRepository');
            /** @var object $queue */
            $queue = app('Laravel\Horizon\Contracts\HorizonCommandQueue');

            $masters = $repo->all();

            if (empty($masters)) {
                return redirect()->route('jobs-monitor.workers')
                    ->with('error', 'No running Horizon master supervisors found.');
            }

            foreach ($masters as $master) {
                $queue->push('master:'.$master->name, 'Laravel\Horizon\SupervisorCommands\Pause');
            }
        } catch (\Throwable $e) {
            return redirect()->route('jobs-monitor.workers')
                ->with('error', 'Could not pause Horizon: '.$e->getMessage());
        }

        return redirect()->route('jobs-monitor.workers', ['_ha' => '1'])
            ->with('status', 'Horizon paused. Workers will finish current jobs then stop.');
    }

    public function continue(): RedirectResponse
    {
        try {
            /** @var object $repo */
            $repo = app('Laravel\Horizon\Contracts\MasterSupervisorRepository');
            /** @var object $queue */
            $queue = app('Laravel\Horizon\Contracts\HorizonCommandQueue');

            $masters = $repo->all();

            if (empty($masters)) {
                return redirect()->route('jobs-monitor.workers')
                    ->with('error', 'No running Horizon master supervisors found.');
            }

            foreach ($masters as $master) {
                $queue->push('master:'.$master->name, 'Laravel\Horizon\SupervisorCommands\ContinueWorking');
            }
        } catch (\Throwable $e) {
            return redirect()->route('jobs-monitor.workers')
                ->with('error', 'Could not resume Horizon: '.$e->getMessage());
        }

        return redirect()->route('jobs-monitor.workers', ['_ha' => '1'])
            ->with('status', 'Horizon resumed. Workers are now active.');
    }

    public function pauseSupervisor(Request $request): RedirectResponse
    {
        $name = trim((string) $request->input('name', ''));

        if ($name === '') {
            return redirect()->route('jobs-monitor.workers')->with('error', 'Supervisor name is required.');
        }

        try {
            /** @var object $repo */
            $repo = app('Laravel\Horizon\Contracts\SupervisorRepository');
            /** @var object $queue */
            $queue = app('Laravel\Horizon\Contracts\HorizonCommandQueue');

            if ($repo->find($name) === null) {
                return redirect()->route('jobs-monitor.workers')
                    ->with('error', "Supervisor \"{$name}\" not found.");
            }

            $queue->push($name, 'Laravel\Horizon\SupervisorCommands\Pause');
        } catch (\Throwable $e) {
            return redirect()->route('jobs-monitor.workers')
                ->with('error', "Could not pause \"{$name}\": ".$e->getMessage());
        }

        return redirect()->route('jobs-monitor.workers', ['_ha' => '1'])
            ->with('status', "Supervisor \"{$name}\" paused.");
    }

    public function continueSupervisor(Request $request): RedirectResponse
    {
        $name = trim((string) $request->input('name', ''));

        if ($name === '') {
            return redirect()->route('jobs-monitor.workers')->with('error', 'Supervisor name is required.');
        }

        try {
            /** @var object $repo */
            $repo = app('Laravel\Horizon\Contracts\SupervisorRepository');
            /** @var object $queue */
            $queue = app('Laravel\Horizon\Contracts\HorizonCommandQueue');

            if ($repo->find($name) === null) {
                return redirect()->route('jobs-monitor.workers')
                    ->with('error', "Supervisor \"{$name}\" not found.");
            }

            $queue->push($name, 'Laravel\Horizon\SupervisorCommands\ContinueWorking');
        } catch (\Throwable $e) {
            return redirect()->route('jobs-monitor.workers')
                ->with('error', "Could not resume \"{$name}\": ".$e->getMessage());
        }

        return redirect()->route('jobs-monitor.workers', ['_ha' => '1'])
            ->with('status', "Supervisor \"{$name}\" resumed.");
    }
}
