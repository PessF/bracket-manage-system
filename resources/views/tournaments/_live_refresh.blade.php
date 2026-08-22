<div class="live-refresh" data-live-refresh data-interval="30" role="status" aria-live="polite">
    <span class="live-dot" aria-hidden="true"></span>
    <strong>{{ __('ui.live_updates') }}</strong>
    <span class="muted">{{ __('ui.refresh_in') }} <span data-refresh-countdown>30</span> {{ __('ui.seconds_short') }}</span>
    <button class="btn secondary small" type="button" data-refresh-now>{{ __('ui.refresh_now') }}</button>
</div>

@once
@push('scripts')
<script>
(() => {
    const toolbar = document.querySelector('[data-live-refresh]');
    if (!toolbar) return;
    const interval = Number(toolbar.dataset.interval || 30);
    const countdown = toolbar.querySelector('[data-refresh-countdown]');
    const storageKey = `easykids-view:${location.pathname}`;
    let remaining = interval;

    const rememberPosition = () => {
        const brackets = [...document.querySelectorAll('.bracket-viewport')].map((element) => element.scrollLeft);
        sessionStorage.setItem(storageKey, JSON.stringify({ y: window.scrollY, brackets }));
    };
    const refresh = () => {
        rememberPosition();
        location.reload();
    };

    try {
        const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
        if (saved) requestAnimationFrame(() => {
            window.scrollTo(0, Number(saved.y || 0));
            document.querySelectorAll('.bracket-viewport').forEach((element, index) => {
                element.scrollLeft = Number(saved.brackets?.[index] || 0);
            });
            sessionStorage.removeItem(storageKey);
        });
    } catch (_) {}

    toolbar.querySelector('[data-refresh-now]')?.addEventListener('click', refresh);
    window.setInterval(() => {
        if (document.hidden) return;
        remaining -= 1;
        if (countdown) countdown.textContent = String(Math.max(remaining, 0));
        if (remaining <= 0) refresh();
    }, 1000);
})();
</script>
@endpush
@endonce
