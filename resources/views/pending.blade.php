@extends('jobs-monitor::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight flex items-center gap-2">
                <i data-lucide="clock" class="text-brand text-[22px]"></i>
                Pending Jobs
            </h1>
            <p class="text-sm text-muted-foreground mt-1">
                Jobs sitting in the queue waiting to be picked up by a worker.
                Auto-refreshes every 15s.
            </p>
        </div>

        @if(!$backendAvailable)
            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-warning/30 bg-warning/10 text-warning text-xs font-medium">
                <i data-lucide="alert-triangle" class="text-[14px]"></i>
                No queue backend available — install Horizon or configure the database queue driver
            </div>
        @endif
    </div>

    <div id="pending-live" class="space-y-6">
        @include('jobs-monitor::partials.pending-content')
    </div>
</div>

@include('jobs-monitor::partials.kebab-script')
@include('jobs-monitor::partials.confirm-modal')

<script>
(function () {
    var container = document.getElementById('pending-live');
    if (!container) return;

    var endpoint = @json(route('jobs-monitor.pending.summary'));
    var intervalMs = 15000;

    function refresh() {
        if (document.hidden) return;

        var params = new URLSearchParams(window.location.search);
        var url = endpoint + (params.toString() ? '?' + params.toString() : '');

        fetch(url, { headers: { 'Accept': 'text/html' } })
            .then(function (r) { return r.ok ? r.text() : Promise.reject(r); })
            .then(function (html) {
                container.innerHTML = html;
                if (typeof lucide !== 'undefined' && lucide.createIcons) {
                    lucide.createIcons();
                }
            })
            .catch(function () { /* keep previous content on error */ });
    }

    function start() {
        setInterval(refresh, intervalMs);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
</script>
@endsection
