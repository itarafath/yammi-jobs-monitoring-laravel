@php
    $baseParams = array_filter([
        'queue' => $queue,
    ]);

    $formatTimestamp = static function (?float $timestamp): string {
        if ($timestamp === null) return '—';
        $dt = \Carbon\Carbon::createFromTimestampUTC($timestamp);
        return $dt->format('Y-m-d H:i:s');
    };

    $timeAgo = static function (?float $timestamp): string {
        if ($timestamp === null) return '';
        $dt = \Carbon\Carbon::createFromTimestampUTC($timestamp);
        return $dt->diffForHumans(short: true);
    };
@endphp

{{-- Summary cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
        <div class="flex items-center justify-between">
            <span class="text-xs uppercase tracking-wide text-muted-foreground">Pending</span>
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-warning/10 text-warning"><i data-lucide="clock" class="text-[14px]"></i></span>
        </div>
        <div class="mt-2 text-2xl font-bold tracking-tight tabular-nums {{ $total > 0 ? 'text-warning' : 'text-foreground' }}">{{ number_format($total) }}</div>
        <p class="mt-1 text-xs text-muted-foreground">Jobs waiting in queue.</p>
    </div>
    <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
        <div class="flex items-center justify-between">
            <span class="text-xs uppercase tracking-wide text-muted-foreground">Delayed</span>
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-info/10 text-info"><i data-lucide="timer" class="text-[14px]"></i></span>
        </div>
        @php $delayedCount = collect($jobs)->filter(fn ($j) => $j['delayed'])->count(); @endphp
        <div class="mt-2 text-2xl font-bold tracking-tight tabular-nums {{ $delayedCount > 0 ? 'text-info' : 'text-foreground' }}">{{ number_format($delayedCount) }}</div>
        <p class="mt-1 text-xs text-muted-foreground">Scheduled for later execution.</p>
    </div>
    <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
        <div class="flex items-center justify-between">
            <span class="text-xs uppercase tracking-wide text-muted-foreground">Queues</span>
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-muted text-muted-foreground"><i data-lucide="layers" class="text-[14px]"></i></span>
        </div>
        @php $uniqueQueues = collect($jobs)->pluck('queue')->unique()->filter()->count(); @endphp
        <div class="mt-2 text-2xl font-bold tracking-tight tabular-nums text-foreground">{{ number_format($uniqueQueues) }}</div>
        <p class="mt-1 text-xs text-muted-foreground">Distinct queues with pending jobs.</p>
    </div>
    <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
        <div class="flex items-center justify-between">
            <span class="text-xs uppercase tracking-wide text-muted-foreground">Page</span>
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-muted text-muted-foreground"><i data-lucide="file-text" class="text-[14px]"></i></span>
        </div>
        <div class="mt-2 text-2xl font-bold tracking-tight tabular-nums text-foreground">{{ $page }} <span class="text-sm font-normal text-muted-foreground">/ {{ $lastPage }}</span></div>
        <p class="mt-1 text-xs text-muted-foreground">{{ $perPage }} jobs per page.</p>
    </div>
</div>

{{-- Queue filter --}}
@if(count($availableQueues) > 0)
<div class="rounded-xl border border-border bg-card p-4 shadow-xs">
    <form method="GET" action="{{ route('jobs-monitor.pending') }}" class="flex flex-wrap items-center gap-2">
        @include('jobs-monitor::partials.select', [
            'name' => 'queue', 'value' => $queue,
            'options' => ['' => 'All queues'] + array_combine($availableQueues, $availableQueues),
            'placeholder' => 'All queues',
        ])
        <button type="submit"
                class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-md border border-brand/30 bg-brand/10 text-brand text-sm font-medium hover:bg-brand/15 hover:border-brand/40 transition-colors">
            <i data-lucide="filter" class="text-[14px]"></i>
            Apply
        </button>
        @if($queue !== '')
            <a href="{{ route('jobs-monitor.pending') }}"
               class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground px-2 py-1.5">
                <i data-lucide="x" class="text-[13px]"></i>
                Clear
            </a>
        @endif
    </form>
</div>
@endif

{{-- Pending jobs table --}}
<section class="rounded-xl border border-border bg-card overflow-hidden">
    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-border bg-warning/5">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-warning/15 text-warning ring-1 ring-inset ring-warning/20">
            <i data-lucide="clock" class="text-[16px]"></i>
        </span>
        <div class="flex-1">
            <h2 class="text-sm font-semibold">Pending Jobs</h2>
            <p class="text-xs text-muted-foreground">{{ number_format($total) }} total · {{ $perPage }} per page</p>
        </div>
    </div>

    @if(count($jobs) === 0)
        <div class="px-5 py-12">
            <div class="max-w-xl mx-auto text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground mb-3">
                    <i data-lucide="check-circle-2" class="text-xl"></i>
                </div>
                <p class="text-sm font-medium text-foreground">No pending jobs</p>
                <p class="text-xs text-muted-foreground mt-2 leading-relaxed">
                    @if($queue !== '')
                        There are no pending jobs in the <code class="px-1.5 py-0.5 rounded bg-muted text-[11px] font-mono">{{ $queue }}</code> queue.
                    @else
                        All queues are clear — every job has been picked up by a worker.
                    @endif
                </p>
            </div>
        </div>
    @else
        <div>
            <table class="w-full text-sm">
                <thead class="bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                    <tr>
                        <th class="px-5 py-2.5 text-left font-medium">Job</th>
                        <th class="hidden md:table-cell px-5 py-2.5 text-left font-medium w-[150px]">Queue</th>
                        <th class="hidden lg:table-cell px-5 py-2.5 text-left font-medium w-[200px]">Tags</th>
                        <th class="px-5 py-2.5 text-left font-medium w-[170px]">Queued</th>
                        <th class="px-5 py-2.5 text-left font-medium w-[110px]">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($jobs as $job)
                        <tr class="{{ $loop->even ? 'bg-muted/40' : 'bg-card' }} hover:bg-warning/5 transition-colors">
                            <td class="px-5 py-3 min-w-0">
                                <a href="{{ route('jobs-monitor.pending.detail', ['jobId' => $job['id']]) }}"
                                   class="font-medium truncate hover:text-brand transition-colors" title="{{ $job['name'] }}">{{ $job['short_name'] }}</a>
                                <div class="text-[11px] text-muted-foreground font-mono truncate mt-0.5">{{ $job['id'] }}</div>
                            </td>
                            <td class="hidden md:table-cell px-5 py-3 w-[150px]">
                                @if($job['queue'] !== '')
                                    <code class="rounded bg-muted px-1.5 py-0.5 text-[11px] font-mono">{{ $job['queue'] }}</code>
                                @else
                                    <span class="text-muted-foreground">—</span>
                                @endif
                            </td>
                            <td class="hidden lg:table-cell px-5 py-3 text-xs text-muted-foreground w-[200px]">
                                @if(count($job['tags']) > 0)
                                    <span class="truncate block">{{ implode(', ', $job['tags']) }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-muted-foreground tabular-nums w-[170px]">
                                {{ $formatTimestamp($job['pushed_at']) }}
                                @if($job['pushed_at'] !== null)
                                    <span class="block text-[10px] text-muted-foreground/70">{{ $timeAgo($job['pushed_at']) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 w-[110px]">
                                @if($job['delayed'])
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border bg-info/10 text-info border-info/20 whitespace-nowrap">
                                        <i data-lucide="timer" class="text-[12px]"></i>
                                        Delayed
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border bg-warning/10 text-warning border-warning/20 whitespace-nowrap">
                                        <i data-lucide="clock" class="text-[12px]"></i>
                                        Waiting
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($lastPage > 1)
            @include('jobs-monitor::partials.pagination', [
                'routeName' => 'jobs-monitor.pending',
                'currentPage' => $page,
                'lastPage' => $lastPage,
                'pageParam' => 'page',
                'extraParams' => $baseParams,
            ])
        @endif
    @endif
</section>
