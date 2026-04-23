@extends('jobs-monitor::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight flex items-center gap-2">
                <i data-lucide="cpu" class="text-brand text-[22px]"></i>
                Workers
            </h1>
            <p class="text-sm text-muted-foreground mt-1">
                Live heartbeat view. Silent after {{ $vm->silentAfterSeconds }}s of no pulse.
                Auto-refreshes every {{ $vm->silentAfterSeconds }}s.
            </p>
        </div>

        @if($horizonInstalled)
            <div class="flex items-center gap-3">
                {{-- Status badge --}}
                @php
                    $hBadge = match($horizonStatus) {
                        'running'  => ['label' => 'Horizon running',  'class' => 'bg-success/10 text-success border-success/20',       'icon' => 'activity'],
                        'paused'   => ['label' => 'Horizon paused',   'class' => 'bg-warning/10 text-warning border-warning/20',       'icon' => 'pause-circle'],
                        'inactive' => ['label' => 'Horizon inactive', 'class' => 'bg-muted text-muted-foreground border-border',       'icon' => 'minus-circle'],
                        default    => ['label' => 'Horizon unknown',  'class' => 'bg-muted text-muted-foreground border-border',       'icon' => 'help-circle'],
                    };
                @endphp
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border {{ $hBadge['class'] }}">
                    <i data-lucide="{{ $hBadge['icon'] }}" class="text-[12px]"></i>
                    {{ $hBadge['label'] }}
                </span>

                {{-- Pause / Resume --}}
                @if($horizonStatus === 'paused')
                    <form method="POST" action="{{ route('jobs-monitor.workers.horizon.continue') }}" class="m-0">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 h-9 px-4 rounded-md bg-success text-success-foreground text-sm font-semibold hover:bg-success/90 transition-colors shadow-xs">
                            <i data-lucide="play" class="text-[14px]"></i>
                            Resume Horizon
                        </button>
                    </form>
                @elseif(in_array($horizonStatus, ['running', 'unknown']))
                    <form method="POST" action="{{ route('jobs-monitor.workers.horizon.pause') }}" class="m-0">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 h-9 px-4 rounded-md bg-warning text-warning-foreground text-sm font-semibold hover:bg-warning/90 transition-colors shadow-xs">
                            <i data-lucide="pause" class="text-[14px]"></i>
                            Pause Horizon
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    @if($horizonInstalled && count($horizonSupervisors) > 0)
        <div class="rounded-xl border border-border bg-card text-card-foreground shadow-xs overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-3.5 border-b border-border">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand/10 text-brand ring-1 ring-inset ring-brand/20">
                    <i data-lucide="layers" class="text-[16px]"></i>
                </span>
                <div>
                    <h2 class="text-sm font-semibold">Horizon supervisors</h2>
                    <p class="text-xs text-muted-foreground">{{ count($horizonSupervisors) }} supervisor group(s)</p>
                </div>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-muted/40 text-[11px] uppercase tracking-wider text-muted-foreground">
                        <th class="text-left font-medium px-5 py-2.5">Name</th>
                        <th class="hidden md:table-cell text-left font-medium px-5 py-2.5">ID</th>
                        <th class="text-left font-medium px-5 py-2.5">Status</th>
                        <th class="py-2.5 w-[160px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($horizonSupervisors as $supervisor)
                        <tr class="{{ $loop->even ? 'bg-muted/40' : 'bg-card' }}">
                            <td class="px-5 py-3 font-mono text-xs">{{ $supervisor['display_name'] }}</td>
                            <td class="hidden md:table-cell px-5 py-3 font-mono text-xs text-muted-foreground">{{ $supervisor['machine_id'] }}</td>
                            <td class="px-5 py-3">
                                @if($supervisor['status'] === 'paused')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-warning/10 text-warning border border-warning/20">
                                        <i data-lucide="pause-circle" class="text-[11px]"></i> Paused
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-success/10 text-success border border-success/20">
                                        <i data-lucide="activity" class="text-[11px]"></i> Running
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if($supervisor['status'] === 'paused')
                                    <form method="POST" action="{{ route('jobs-monitor.workers.horizon.supervisor.continue') }}" class="m-0 inline">
                                        @csrf
                                        <input type="hidden" name="name" value="{{ $supervisor['name'] }}">
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 h-7 px-3 rounded-md bg-success text-success-foreground text-xs font-semibold hover:bg-success/90 transition-colors">
                                            <i data-lucide="play" class="text-[11px]"></i> Resume
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('jobs-monitor.workers.horizon.supervisor.pause') }}" class="m-0 inline">
                                        @csrf
                                        <input type="hidden" name="name" value="{{ $supervisor['name'] }}">
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 h-7 px-3 rounded-md bg-warning text-warning-foreground text-xs font-semibold hover:bg-warning/90 transition-colors">
                                            <i data-lucide="pause" class="text-[11px]"></i> Pause
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div id="workers-live" class="space-y-6">
        @include('jobs-monitor::partials.workers-content')
    </div>
</div>

@include('jobs-monitor::partials.kebab-script')
@include('jobs-monitor::partials.confirm-modal')
@include('jobs-monitor::partials.workers-auto-refresh')

<script>
(function () {
    var params = new URLSearchParams(window.location.search);
    if (!params.get('_ha')) return;

    // Strip _ha from URL so a user manual-refresh doesn't re-trigger
    params.delete('_ha');
    var clean = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    history.replaceState(null, '', clean);

    // Horizon processes commands on its heartbeat (~5s); reload once after 5s
    setTimeout(function () { window.location.reload(); }, 5000);
})();
</script>
@endsection
