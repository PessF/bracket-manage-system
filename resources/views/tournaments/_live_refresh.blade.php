@php
    $liveRefreshInterval = max(1, (int) ($interval ?? 1));
    $liveStateUrl = request()->routeIs('public.tournaments.*')
        ? route('public.tournaments.live-state', ['tournament' => $tournament->public_token])
        : route('tournaments.live-state', $tournament);
@endphp
<div class="live-refresh" data-live-refresh data-interval="{{ $liveRefreshInterval }}" data-live-state-url="{{ $liveStateUrl }}" @if(isset($refreshTarget)) data-refresh-target="{{ $refreshTarget }}" @endif role="status" aria-live="polite">
    <span class="live-dot" aria-hidden="true"></span>
    <strong>{{ __('ui.live_updates') }}</strong>
    <span class="muted">{{ __('ui.refresh_in') }} <span data-refresh-countdown>{{ $liveRefreshInterval }}</span> {{ __('ui.seconds_short') }}</span>
    <button class="btn secondary small" type="button" data-refresh-now>{{ __('ui.refresh_now') }}</button>
</div>

@once
@push('scripts')
<script>
(() => {
    const toolbar = document.querySelector('[data-live-refresh]');
    if (!toolbar) return;
    const interval = Number(toolbar.dataset.interval || 30);
    const refreshTarget = toolbar.dataset.refreshTarget;
    const liveStateUrl = toolbar.dataset.liveStateUrl;
    const countdown = toolbar.querySelector('[data-refresh-countdown]');
    const storageKey = `easykids-view:${location.pathname}`;
    let remaining = interval;
    let refreshing = false;
    let checking = false;
    let observedVersion = null;

    const rememberPosition = () => {
        const brackets = [...document.querySelectorAll('.bracket-viewport')].map((element) => element.scrollLeft);
        sessionStorage.setItem(storageKey, JSON.stringify({ y: window.scrollY, brackets }));
    };
    const reloadPage = () => {
        rememberPosition();
        location.reload();
    };
    const refresh = async () => {
        if (!refreshTarget) {
            reloadPage();
            return true;
        }
        if (refreshing) return false;

        refreshing = true;
        try {
            const response = await fetch(location.href, {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error(`Live refresh failed (${response.status})`);

            const documentCopy = new DOMParser().parseFromString(await response.text(), 'text/html');
            const current = document.querySelector(refreshTarget);
            const replacement = documentCopy.querySelector(refreshTarget);
            if (!current || !replacement) throw new Error('Live refresh target is missing');

            if (current.innerHTML !== replacement.innerHTML) {
                const scrollPositions = [...current.querySelectorAll('.table-wrap')].map((element) => element.scrollLeft);
                const bracketPositions = [...current.querySelectorAll('.bracket-viewport')].map((element) => ({
                    left: element.scrollLeft,
                    top: element.scrollTop,
                }));
                current.replaceChildren(...replacement.cloneNode(true).childNodes);
                document.dispatchEvent(new CustomEvent('easykids:live-content-updated', {
                    detail: { target: current },
                }));
                current.querySelectorAll('.table-wrap').forEach((element, index) => {
                    element.scrollLeft = Number(scrollPositions[index] || 0);
                });
                requestAnimationFrame(() => {
                    current.querySelectorAll('.bracket-viewport').forEach((element, index) => {
                        element.scrollLeft = Number(bracketPositions[index]?.left || 0);
                        element.scrollTop = Number(bracketPositions[index]?.top || 0);
                    });
                });
            }
            return true;
        } catch (_) {
            // Keep the current results visible and retry on the next interval.
            return false;
        } finally {
            remaining = interval;
            if (countdown) countdown.textContent = String(interval);
            refreshing = false;
        }
    };

    const checkForUpdates = async () => {
        if (!liveStateUrl || checking || refreshing) return;
        checking = true;
        try {
            const response = await fetch(liveStateUrl, {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error(`Live state failed (${response.status})`);

            const version = String((await response.json()).version || '');
            if (!version) return;
            if (observedVersion === null) {
                observedVersion = version;
                if (refreshTarget && !(await refresh())) observedVersion = null;
            } else if (version !== observedVersion) {
                if (await refresh()) observedVersion = version;
            }
        } catch (_) {
            // Leave the current content visible and retry on the next interval.
        } finally {
            checking = false;
        }
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

    toolbar.querySelector('[data-refresh-now]')?.addEventListener('click', () => void refresh());
    window.setInterval(() => {
        if (document.hidden) return;
        remaining -= 1;
        if (countdown) countdown.textContent = String(Math.max(remaining, 0));
        if (remaining <= 0) {
            remaining = interval;
            if (liveStateUrl) void checkForUpdates();
            else void refresh();
        }
    }, 1000);
})();
</script>
@endpush
@endonce
