@extends('jobs-monitor::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="mb-4">
        <a href="{{ route('jobs-monitor.pending', array_filter(['queue' => request()->query('queue')])) }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
            <i data-lucide="arrow-left" class="text-[14px]"></i>
            Back to Pending Jobs
        </a>
    </div>

    {{-- Header --}}
    <div class="rounded-xl border border-border bg-card text-card-foreground shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-warning/10 text-warning ring-1 ring-inset ring-warning/20">
                    <i data-lucide="clock" class="text-[18px]"></i>
                </span>
                <div>
                    <h1 class="text-lg font-semibold tracking-tight">{{ $job['short_name'] }}</h1>
                    <div class="mt-1">
                        @if($job['delayed'])
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border bg-info/10 text-info border-info/20">
                                <i data-lucide="timer" class="text-[12px]"></i>
                                Delayed
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border bg-warning/10 text-warning border-warning/20">
                                <i data-lucide="clock" class="text-[12px]"></i>
                                Waiting
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Action buttons --}}
            <div class="flex items-center gap-2 flex-wrap">
                @if($job['status'] === 'pending')
                    <button type="button"
                            onclick="__jmOpenConfirm({
                                action: '{{ route('jobs-monitor.pending.delete', ['jobId' => $job['id']]) }}',
                                method: 'POST',
                                title: 'Delete pending job?',
                                body: 'This will remove the job from the queue before any worker picks it up. The job will NOT be retried. This cannot be undone.',
                                submitLabel: 'Delete Job',
                                icon: 'trash-2',
                                variant: 'warning'
                            })"
                            class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-md border border-warning/30 bg-warning/10 text-warning text-sm font-medium hover:bg-warning/15 hover:border-warning/40 transition-colors">
                        <i data-lucide="trash-2" class="text-[14px]"></i>
                        Delete
                    </button>
                @endif

                @if($job['status'] === 'reserved')
                    <button type="button"
                            onclick="__jmOpenConfirm({
                                action: '{{ route('jobs-monitor.pending.force-stop', ['jobId' => $job['id']]) }}',
                                method: 'POST',
                                title: 'Force stop running job?',
                                bodyHtml: 'This will remove the job from the worker\'s reserved queue and mark it as <strong>failed</strong>. The worker process will eventually release it. The job will <strong>NOT</strong> be retried.<br><br><span class=\'text-warning\'>Warning:</span> This cannot kill the PHP process — the worker may briefly continue executing before noticing the job is gone.',
                                submitLabel: 'Force Stop',
                                icon: 'octagon-x',
                                variant: 'danger'
                            })"
                            class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-md border border-destructive/30 bg-destructive/10 text-destructive text-sm font-medium hover:bg-destructive/15 hover:border-destructive/40 transition-colors">
                        <i data-lucide="octagon-x" class="text-[14px]"></i>
                        Force Stop
                    </button>
                @endif
            </div>
        </div>

        {{-- Job metadata --}}
        <div class="px-6 py-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-4">
                <div>
                    <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Job ID</span>
                    <p class="text-sm font-mono break-all mt-1">{{ $job['id'] }}</p>
                </div>
                <div>
                    <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Full Class</span>
                    <p class="text-sm font-mono break-all mt-1">{{ $job['name'] }}</p>
                </div>
                <div>
                    <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Queue</span>
                    <p class="text-sm mt-1">
                        @if($job['queue'] !== '')
                            <code class="rounded bg-muted px-1.5 py-0.5 text-[11px] font-mono">{{ $job['queue'] }}</code>
                        @else
                            <span class="text-muted-foreground">—</span>
                        @endif
                    </p>
                </div>
                <div>
                    <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Connection</span>
                    <p class="text-sm mt-1">
                        @if($job['connection'] !== '')
                            <code class="rounded bg-muted px-1.5 py-0.5 text-[11px] font-mono">{{ $job['connection'] }}</code>
                        @else
                            <span class="text-muted-foreground">—</span>
                        @endif
                    </p>
                </div>
                <div>
                    <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Pushed At</span>
                    <p class="text-sm tabular-nums mt-1">
                        @if($job['pushed_at'] !== null)
                            {{ \Carbon\Carbon::createFromTimestampUTC($job['pushed_at'])->format('Y-m-d H:i:s') }}
                            <span class="text-muted-foreground text-xs ml-1">({{ \Carbon\Carbon::createFromTimestampUTC($job['pushed_at'])->diffForHumans(short: true) }})</span>
                        @else
                            <span class="text-muted-foreground">—</span>
                        @endif
                    </p>
                </div>
                @if($job['delayed'] && $job['delayed_until'])
                    <div>
                        <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Delayed Until</span>
                        <p class="text-sm tabular-nums mt-1 text-info">{{ $job['delayed_until'] }}</p>
                    </div>
                @endif
                <div>
                    <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Status</span>
                    <p class="text-sm mt-1">{{ ucfirst($job['status']) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Tags --}}
    @if(count($job['tags']) > 0)
        <div class="rounded-xl border border-border bg-card overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-3.5 border-b border-border">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand/10 text-brand ring-1 ring-inset ring-brand/20">
                    <i data-lucide="tag" class="text-[16px]"></i>
                </span>
                <h2 class="text-sm font-semibold">Tags</h2>
            </div>
            <div class="px-5 py-4 flex flex-wrap gap-2">
                @foreach($job['tags'] as $tag)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-muted text-foreground border border-border">
                        {{ $tag }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Data / Payload --}}
    @if($job['data'] !== null)
        <div class="rounded-xl border border-border bg-card overflow-hidden">
            <div class="flex items-center gap-3 px-5 py-3.5 border-b border-border">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    <i data-lucide="code-2" class="text-[16px]"></i>
                </span>
                <h2 class="text-sm font-semibold">Job Data</h2>
            </div>
            <div class="p-5">
                <pre class="bg-muted/50 border border-border rounded-lg p-4 text-xs overflow-x-auto whitespace-pre-wrap break-words font-mono">{{ json_encode($job['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    @endif

    {{-- Raw Payload (decoded) --}}
    @if($job['pretty_payload'] !== null)
        <div class="rounded-xl border border-border bg-card overflow-hidden" data-collapsible="raw-payload">
            <button type="button"
                    class="w-full flex items-center gap-3 px-5 py-3.5 border-b border-border text-left bg-muted/30 hover:bg-muted/50 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    onclick="__jmToggleCollapsible('raw-payload')"
                    data-collapsible-trigger>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    <i data-lucide="file-json" class="text-[16px]"></i>
                </span>
                <div class="flex-1">
                    <h2 class="text-sm font-semibold">Raw Payload</h2>
                    <p class="text-xs text-muted-foreground">Full decoded payload with unserialized command data</p>
                </div>
                <span class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground" data-collapsible-label>Show</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:text-foreground transition-transform" data-collapsible-caret>
                    <i data-lucide="chevron-up" class="text-[16px]"></i>
                </span>
            </button>
            <div data-collapsible-body class="hidden">
                <div class="p-5">
                    <pre class="bg-muted/50 border border-border rounded-lg p-4 text-xs overflow-x-auto whitespace-pre-wrap break-words font-mono">{{ json_encode($job['pretty_payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        </div>
    @endif
</div>

@include('jobs-monitor::partials.confirm-modal')

<script>
    function __jmToggleCollapsible(key) {
        var root = document.querySelector('[data-collapsible="' + key + '"]');
        if (!root) return;
        var collapsed = root.getAttribute('data-collapsed') === '1';
        __jmSetCollapsed(root, !collapsed);
        try { localStorage.setItem('jm-collapsed-' + key, collapsed ? '0' : '1'); } catch (e) {}
    }
    function __jmSetCollapsed(root, collapsed) {
        var body = root.querySelector('[data-collapsible-body]');
        var caret = root.querySelector('[data-collapsible-caret]');
        var label = root.querySelector('[data-collapsible-label]');
        root.setAttribute('data-collapsed', collapsed ? '1' : '0');
        if (body) body.classList.toggle('hidden', collapsed);
        if (caret) caret.style.transform = collapsed ? 'rotate(180deg)' : 'rotate(0deg)';
        if (label) label.textContent = collapsed ? 'Show' : 'Hide';
    }
    (function () {
        function hydrate() {
            document.querySelectorAll('[data-collapsible]').forEach(function (root) {
                var key = root.getAttribute('data-collapsible');
                var stored = null;
                try { stored = localStorage.getItem('jm-collapsed-' + key); } catch (e) {}
                if (stored === '1') __jmSetCollapsed(root, true);
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', hydrate);
        } else {
            hydrate();
        }
    })();
</script>
@endsection
