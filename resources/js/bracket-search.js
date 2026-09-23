const search = document.querySelector('[data-bracket-search]');
if (search) {
    const input = search.querySelector('[data-bracket-search-input]');
    const status = search.querySelector('[data-bracket-search-status]');
    const next = search.querySelector('[data-bracket-search-next]');
    const clear = search.querySelector('[data-bracket-search-clear]');
    const normalize = (value) => value.normalize('NFC').trim().toLocaleLowerCase();
    let matches = [];
    let current = -1;
    let composing = false;
    const update = () => {
        const term = normalize(input.value);
        matches = [];
        current = -1;
        document.querySelectorAll('[data-live-bracket] [data-participant-search]').forEach((slot) => {
            const names = JSON.parse(slot.dataset.participantSearch);
            const matched = !!term && names.some((name) => normalize(name).includes(term));
            slot.classList.toggle('is-search-match', matched);
            slot.classList.remove('is-search-current');
            if (matched) matches.push(slot);
        });
        status.textContent = !term ? status.dataset.hint : matches.length
            ? status.dataset.count.replace(':count', matches.length) : status.dataset.empty;
        next.disabled = matches.length === 0;
        clear.disabled = input.value.length === 0;
    };
    const locateNext = () => {
        if (!matches.length) return;
        matches[current]?.classList.remove('is-search-current');
        current = (current + 1) % matches.length;
        const slot = matches[current];
        slot.classList.add('is-search-current');
        slot.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'center', inline: 'center' });
    };
    input.addEventListener('compositionstart', () => { composing = true; });
    input.addEventListener('compositionend', () => { composing = false; update(); });
    input.addEventListener('input', () => { if (!composing) update(); });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.isComposing) { event.preventDefault(); locateNext(); }
        if (event.key === 'Escape') { input.value = ''; update(); }
    });
    next.addEventListener('click', locateNext);
    clear.addEventListener('click', () => { input.value = ''; update(); input.focus(); });
    document.addEventListener('easykids:live-content-updated', update);
    update();
}
