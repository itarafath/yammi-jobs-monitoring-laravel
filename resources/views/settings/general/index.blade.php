@extends('jobs-monitor::layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-end justify-between gap-3 flex-wrap">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand/10 text-brand ring-1 ring-inset ring-brand/20">
                <i data-lucide="settings" class="text-[18px]"></i>
            </span>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">General Settings</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Feature toggles and operational tuning. Changes saved to the database override config file values.
                    Source badges show where each value comes from.
                </p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            @include('jobs-monitor::partials.button', [
                'variant' => 'secondary',
                'icon' => 'rotate-ccw',
                'label' => 'Reset to defaults',
                'attrs' => 'onclick="document.getElementById(\'jm-reset-modal\').classList.remove(\'hidden\'); if(window.__jmRefreshIcons) window.__jmRefreshIcons();"',
            ])
            <a href="{{ route('jobs-monitor.settings') }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
                <i data-lucide="arrow-left" class="text-[14px]"></i>
                Back to settings
            </a>
        </div>
    </div>

    @if(session('jobs_monitor_status'))
        <div class="flex items-start gap-3 rounded-lg border border-success/25 bg-success/10 text-success px-4 py-3 text-sm">
            <i data-lucide="check-circle-2" class="text-[16px] mt-0.5"></i>
            <div>{{ session('jobs_monitor_status') }}</div>
        </div>
    @endif

    @if($errors->any())
        <div class="flex items-start gap-3 rounded-lg border border-destructive/25 bg-destructive/10 text-destructive px-4 py-3 text-sm">
            <i data-lucide="alert-circle" class="text-[16px] mt-0.5"></i>
            <div>
                <p class="font-medium">Validation failed</p>
                <ul class="mt-1 list-disc list-inside text-xs">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('jobs-monitor.settings.general.update') }}" class="space-y-6">
        @csrf

        @foreach($groups as $group)
            @include('jobs-monitor::settings.general._group', ['group' => $group])
        @endforeach

        <div class="flex justify-end">
            @include('jobs-monitor::partials.button', [
                'variant' => 'brand',
                'as' => 'submit',
                'icon' => 'save',
                'label' => 'Save all settings',
            ])
        </div>
    </form>

    {{-- Danger zone --}}
    <div class="rounded-xl border border-destructive/30 bg-card overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-3.5 border-b border-destructive/20 bg-destructive/5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-destructive/15 text-destructive ring-1 ring-inset ring-destructive/20">
                <i data-lucide="triangle-alert" class="text-[16px]"></i>
            </span>
            <div>
                <h2 class="text-sm font-semibold text-destructive">Danger Zone</h2>
                <p class="text-xs text-muted-foreground">Irreversible operations. These cannot be undone.</p>
            </div>
        </div>
        <div class="divide-y divide-border">
            {{-- Clear duration baselines & anomalies --}}
            <div class="flex items-center justify-between gap-4 px-5 py-4">
                <div>
                    <p class="text-sm font-medium">Clear metrics</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Delete all duration baselines and anomaly records. Baselines rebuild automatically on the next scheduled run.</p>
                </div>
                <button type="button"
                        onclick="__jmClearMetrics({{ json_encode(route('jobs-monitor.settings.metrics.clear')) }}, null)"
                        class="shrink-0 inline-flex items-center gap-1.5 h-8 px-3 text-xs font-semibold rounded-md border border-destructive/30 text-destructive hover:bg-destructive/10 transition-colors">
                    <i data-lucide="bar-chart-2" class="text-[13px]"></i>
                    Clear metrics
                </button>
            </div>
            {{-- Clear job records (with period picker) --}}
            <div class="flex items-center justify-between gap-4 px-5 py-4">
                <div>
                    <p class="text-sm font-medium">Clear job records</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Delete job monitoring records for a given period. Also clears metrics.</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <select id="jm-clear-period"
                            class="h-8 rounded-md border border-input bg-card text-xs text-foreground px-2 focus:outline-none focus:ring-2 focus:ring-ring">
                        <option value="24h">Last 24h</option>
                        <option value="7d">Last 7 days</option>
                        <option value="30d">Last 30 days</option>
                        <option value="all">All records</option>
                    </select>
                    <button type="button"
                            onclick="__jmClearMetrics({{ json_encode(route('jobs-monitor.settings.metrics.clear')) }}, document.getElementById('jm-clear-period').value)"
                            class="inline-flex items-center gap-1.5 h-8 px-3 text-xs font-semibold rounded-md bg-destructive text-destructive-foreground hover:bg-destructive/90 transition-colors">
                        <i data-lucide="trash-2" class="text-[13px]"></i>
                        Clear
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function __jmClearMetrics(url, period) {
    var label = period ? 'job records (' + period + ') and metrics' : 'all duration baselines and anomalies';
    window.__jmOpenConfirm({
        action: url,
        method: 'POST',
        title: 'Clear ' + label + '?',
        body: 'This permanently deletes the selected data and cannot be undone.',
        submitLabel: 'Clear',
        icon: 'trash-2',
        variant: 'danger',
    });
    var form = document.querySelector('[data-jm-confirm-form]');
    if (form) {
        var el = form.querySelector('input[name="period"]');
        if (el) el.remove();
        if (period) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'period';
            input.value = period;
            form.appendChild(input);
        }
    }
}
</script>

{{-- Reset confirmation modal --}}
<div id="jm-reset-modal"
     class="hidden fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="jm-reset-title" role="dialog" aria-modal="true">
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="fixed inset-0 bg-background/80 backdrop-blur-sm transition-opacity"
             onclick="document.getElementById('jm-reset-modal').classList.add('hidden')"></div>

        <div class="relative w-full max-w-md transform overflow-hidden rounded-xl bg-popover text-popover-foreground shadow-2xl ring-1 ring-border transition-all animate-slide-down">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-warning/10 text-warning ring-1 ring-inset ring-warning/20">
                        <i data-lucide="rotate-ccw" class="text-[18px]"></i>
                    </div>
                    <div class="flex-1">
                        <h3 id="jm-reset-title" class="text-base font-semibold">Reset all settings to defaults?</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            This will remove all database overrides and restore every setting to its
                            package default value. Config file values (<code class="px-1 py-0.5 rounded bg-muted text-[11px]">.env</code>)
                            will still take effect where set.
                        </p>
                    </div>
                </div>
            </div>
            <div class="bg-muted/40 px-6 py-3 flex justify-end gap-2 border-t border-border">
                @include('jobs-monitor::partials.button', [
                    'variant' => 'secondary',
                    'label' => 'Cancel',
                    'attrs' => 'onclick="document.getElementById(\'jm-reset-modal\').classList.add(\'hidden\')"',
                ])
                <form method="POST" action="{{ route('jobs-monitor.settings.general.reset') }}" class="m-0">
                    @csrf
                    @include('jobs-monitor::partials.button', [
                        'variant' => 'warning',
                        'as' => 'submit',
                        'icon' => 'rotate-ccw',
                        'label' => 'Reset to defaults',
                    ])
                </form>
            </div>
        </div>
    </div>
</div>

@include('jobs-monitor::partials.confirm-modal')

<script>
(function () {
    // Close reset modal on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.getElementById('jm-reset-modal')?.classList.add('hidden');
        }
    });
    document.querySelectorAll('[data-jm-select-custom]').forEach(function (root) {
        var applyBtn = root.querySelector('[data-jm-custom-apply]');
        var customInput = root.querySelector('[data-jm-custom-input]');
        if (!applyBtn || !customInput) return;

        customInput.addEventListener('keydown', function (e) {
            e.stopPropagation();
            if (e.key === 'Enter') {
                e.preventDefault();
                applyBtn.click();
            }
        });

        applyBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var val = customInput.value.trim();
            if (!val) return;

            var hiddenInput = root.querySelector('[data-jm-select-input]');
            var label = root.querySelector('[data-jm-select-label]');
            if (hiddenInput) hiddenInput.value = val;
            if (label) {
                label.textContent = 'Custom: ' + val;
                label.classList.add('font-medium');
                label.classList.remove('text-muted-foreground/90');
            }

            root.querySelectorAll('[data-jm-select-option]').forEach(function (o) {
                o.setAttribute('aria-selected', 'false');
                o.classList.remove('bg-brand/10', 'font-medium');
                o.classList.add('hover:bg-accent');
                var tick = o.querySelector('span [data-lucide]');
                if (tick) tick.remove();
            });

            var dd = root.querySelector('[data-jm-select-dropdown]');
            if (dd) dd.classList.add('hidden');
            var tr = root.querySelector('[data-jm-select-trigger]');
            if (tr) tr.setAttribute('aria-expanded', 'false');
            var ct = root.querySelector('[data-jm-select-caret]');
            if (ct) ct.style.transform = '';
        });
    });
})();
</script>
@endsection
