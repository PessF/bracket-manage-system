import './bootstrap';
import './responsive';

const toastRegion = document.querySelector('[data-toast-region]');
const showToast = (message, tone = 'success') => {
    if (!toastRegion || !message) return;
    const toast = document.createElement('div');
    toast.className = `toast ${tone}`;
    toast.setAttribute('role', tone === 'error' ? 'alert' : 'status');
    toast.textContent = message;
    toastRegion.appendChild(toast);
    window.requestAnimationFrame(() => toast.classList.add('visible'));
    window.setTimeout(() => {
        toast.classList.remove('visible');
        toast.addEventListener('transitionend', () => toast.remove(), { once: true });
        window.setTimeout(() => toast.remove(), 250);
    }, 2600);
};

const tournamentSort = document.querySelector('[data-tournament-sort]');
if (tournamentSort) {
    let draggedCard = null;
    let suppressCardClick = false;
    let orderBeforeDrag = [];
    const cards = () => [...tournamentSort.querySelectorAll('[data-tournament-card]')];
    const status = document.querySelector('[data-order-status]');
    const currentOrder = () => cards().map((item) => item.dataset.tournamentId);
    const refreshMoveButtons = () => cards().forEach((card, index, allCards) => {
        const up = card.querySelector('[data-order-move="-1"]');
        const down = card.querySelector('[data-order-move="1"]');
        if (up) up.disabled = index === 0;
        if (down) down.disabled = index === allCards.length - 1;
    });
    const restoreOrder = (order) => order.forEach((id) => {
        const card = cards().find((item) => item.dataset.tournamentId === id);
        if (card) tournamentSort.appendChild(card);
    });
    const saveOrder = async (previousOrder) => {
        tournamentSort.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(tournamentSort.dataset.orderUrl, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ order: currentOrder() }),
            });
            if (!response.ok) throw new Error('Order update failed');
            if (status) status.textContent = status.dataset.success;
            showToast(status?.dataset.success);
        } catch (_) {
            restoreOrder(previousOrder);
            refreshMoveButtons();
            if (status) status.textContent = status.dataset.error;
            showToast(status?.dataset.error, 'error');
        } finally {
            tournamentSort.removeAttribute('aria-busy');
        }
    };

    cards().forEach((card) => {
        card.addEventListener('click', (event) => {
            if (!suppressCardClick) return;
            event.preventDefault();
            event.stopPropagation();
            suppressCardClick = false;
        });
        card.addEventListener('dragstart', (event) => {
            draggedCard = card;
            orderBeforeDrag = currentOrder();
            suppressCardClick = true;
            card.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => {
            draggedCard = null;
            cards().forEach((item) => item.classList.remove('is-dragging', 'is-drag-over'));
            window.setTimeout(() => { suppressCardClick = false; }, 300);
        });
        card.addEventListener('dragover', (event) => {
            if (!draggedCard || draggedCard === card) return;
            event.preventDefault();
            card.classList.add('is-drag-over');
        });
        card.addEventListener('dragleave', () => card.classList.remove('is-drag-over'));
        card.addEventListener('drop', (event) => {
            event.preventDefault();
            if (!draggedCard || draggedCard === card) return;
            const rect = card.getBoundingClientRect();
            const insertBefore = event.clientY < rect.top + rect.height / 2;
            tournamentSort.insertBefore(draggedCard, insertBefore ? card : card.nextSibling);
            card.classList.remove('is-drag-over');
            refreshMoveButtons();
            saveOrder(orderBeforeDrag);
        });
        card.querySelectorAll('[data-order-move]').forEach((button) => button.addEventListener('click', () => {
            const previousOrder = currentOrder();
            const siblings = cards();
            const currentIndex = siblings.indexOf(card);
            const destination = currentIndex + Number(button.dataset.orderMove);
            if (destination < 0 || destination >= siblings.length) return;
            if (destination < currentIndex) tournamentSort.insertBefore(card, siblings[destination]);
            else tournamentSort.insertBefore(card, siblings[destination].nextSibling);
            refreshMoveButtons();
            card.querySelector(`[data-order-move="${button.dataset.orderMove}"]`)?.focus();
            saveOrder(previousOrder);
        }));
    });
    refreshMoveButtons();
}

// Base tournament UI interactions extracted from layouts/app.blade.php.
document.addEventListener('submit', (event) => {
    const form = event.target;
    const message = event.target.dataset.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
        return;
    }
    if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }
    const button = event.submitter?.matches('.btn') ? event.submitter : null;
    if (!button) return;
    form.dataset.submitting = 'true';
    window.requestAnimationFrame(() => {
        button.dataset.originalText = button.textContent;
        button.disabled = true;
        button.classList.add('is-submitting');
        button.setAttribute('aria-busy', 'true');
        button.textContent = button.dataset.submitting || document.body.dataset.processingLabel;
    });
});
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-target]');
    if (!button) return;
    const source = document.querySelector(button.dataset.copyTarget);
    if (!source) return;
    const value = 'value' in source ? source.value : source.textContent;
    try {
        await navigator.clipboard.writeText(value);
    } catch (_) {
        if ('select' in source) {
            source.select();
            document.execCommand('copy');
            source.setSelectionRange(0, 0);
        }
    }
    const originalLabel = button.textContent;
    button.textContent = button.dataset.copied;
    showToast(button.dataset.copied);
    window.setTimeout(() => { button.textContent = originalLabel; }, 2200);
});

document.addEventListener('click', (event) => {
    const dismissButton = event.target.closest('[data-dismiss-alert]');
    if (!dismissButton) return;
    const alert = dismissButton.closest('.alert');
    alert?.classList.add('is-dismissing');
    window.setTimeout(() => alert?.remove(), 180);
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = button.closest('.password-control')?.querySelector('input');
        if (!input) return;
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.classList.toggle('showing', !showing);
        button.setAttribute('aria-label', showing ? button.dataset.showLabel : button.dataset.hideLabel);
        input.focus({ preventScroll: true });
    });
});

document.querySelectorAll('[data-dirty-guard]').forEach((form) => {
    const serialize = () => [...new FormData(form).entries()]
        .filter(([name]) => !['_token', '_method'].includes(name))
        .map(([name, value]) => `${name}:${value instanceof File ? value.name : value}`)
        .join('|');
    const initialState = serialize();
    let submitted = false;
    const hasChanges = () => serialize() !== initialState;
    form.addEventListener('submit', (event) => { if (!event.defaultPrevented) submitted = true; });
    window.addEventListener('beforeunload', (event) => {
        if (submitted || !hasChanges()) return;
        event.preventDefault();
        event.returnValue = form.dataset.unsavedMessage;
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
    if (event.target.matches('input, textarea, select, [contenteditable="true"]')) return;
    const search = document.querySelector('[data-competition-search]');
    if (!search) return;
    event.preventDefault();
    search.focus();
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting="true"]').forEach((form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('.is-submitting').forEach((button) => {
            button.disabled = false;
            button.classList.remove('is-submitting');
            button.removeAttribute('aria-busy');
            if (button.dataset.originalText) button.textContent = button.dataset.originalText;
        });
    });
});
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-score-step]');
    if (!button) return;
    const input = button.closest('.score-stepper')?.querySelector('input[type="number"]');
    if (!input) return;
    const direction = Number(button.dataset.scoreStep);
    const current = Number(input.value || 0);
    const minimum = input.min === '' ? 0 : Number(input.min);
    input.value = String(Math.max(minimum, current + direction));
    input.dispatchEvent(new Event('input', { bubbles: true }));
});
document.addEventListener('click', (event) => {
    document.querySelectorAll('.language-menu[open], .mobile-menu[open]').forEach((menu) => {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') document.querySelectorAll('.language-menu[open], .mobile-menu[open]').forEach((menu) => {
        menu.removeAttribute('open');
        menu.querySelector('summary')?.focus();
    });
});

document.querySelectorAll('.tabs').forEach((tabs) => {
    const active = tabs.querySelector('a.active');
    if (!active || tabs.scrollWidth <= tabs.clientWidth) return;
    window.requestAnimationFrame(() => {
        tabs.scrollLeft = Math.max(0, active.offsetLeft - ((tabs.clientWidth - active.offsetWidth) / 2));
    });
});

(() => {
    let activeSelect = null;
    let typeahead = '';
    let typeaheadTimer = null;

    const chevron = '<svg class="smart-select-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>';
    const check = '<svg class="smart-select-option-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>';

    const closeSelect = (instance, restoreFocus = false) => {
        if (!instance) return;
        instance.menu.classList.remove('open');
        instance.trigger.setAttribute('aria-expanded', 'false');
        instance.options.forEach((option) => option.classList.remove('focused'));
        if (activeSelect === instance) activeSelect = null;
        if (restoreFocus) instance.trigger.focus();
    };

    const positionSelect = (instance) => {
        const rect = instance.trigger.getBoundingClientRect();
        const gutter = 8;
        const width = Math.min(Math.max(rect.width, 180), window.innerWidth - (gutter * 2));
        const left = Math.max(gutter, Math.min(rect.left, window.innerWidth - width - gutter));

        instance.menu.style.width = `${width}px`;
        instance.menu.style.left = `${left}px`;
        instance.menu.style.top = `${rect.bottom + 5}px`;
        instance.menu.style.bottom = 'auto';
        instance.menu.style.maxHeight = '280px';

        const wantedHeight = Math.min(instance.menu.scrollHeight, 280);
        const below = window.innerHeight - rect.bottom - 13;
        const above = rect.top - 13;

        if (below >= Math.min(wantedHeight, 180) || below >= above) {
            instance.menu.style.maxHeight = `${Math.max(80, below)}px`;
        } else {
            instance.menu.style.top = 'auto';
            instance.menu.style.bottom = `${window.innerHeight - rect.top + 5}px`;
            instance.menu.style.maxHeight = `${Math.max(80, above)}px`;
        }
    };

    const syncSelect = (instance) => {
        const selectedIndex = instance.select.selectedIndex;
        const selected = instance.select.options[selectedIndex];
        instance.value.textContent = selected?.textContent.trim() || '';
        instance.trigger.title = selected?.textContent.trim() || '';
        instance.trigger.disabled = instance.select.disabled;
        instance.options.forEach((option, index) => {
            const sourceOption = instance.select.options[index];
            const unavailable = Boolean(sourceOption?.disabled || sourceOption?.hidden);
            const isSelected = index === selectedIndex;
            option.disabled = unavailable;
            option.hidden = unavailable;
            option.classList.toggle('selected', isSelected);
            option.setAttribute('aria-selected', String(isSelected));
        });
    };

    const focusOption = (instance, index) => {
        const available = instance.options.filter((option) => !option.disabled);
        if (!available.length) return;
        const bounded = (index + available.length) % available.length;
        instance.options.forEach((option) => option.classList.remove('focused'));
        available[bounded].classList.add('focused');
        available[bounded].focus({ preventScroll: true });
        available[bounded].scrollIntoView({ block: 'nearest' });
    };

    const openSelect = (instance, direction = 0) => {
        if (instance.trigger.disabled) return;
        if (activeSelect && activeSelect !== instance) closeSelect(activeSelect);
        activeSelect = instance;
        instance.menu.classList.add('open');
        instance.trigger.setAttribute('aria-expanded', 'true');
        positionSelect(instance);

        const available = instance.options.filter((option) => !option.disabled);
        const selected = available.findIndex((option) => option.classList.contains('selected'));
        const destination = direction < 0 ? available.length - 1 : Math.max(0, selected);
        requestAnimationFrame(() => focusOption(instance, destination));
    };

    const chooseOption = (instance, index) => {
        const sourceOption = instance.select.options[index];
        if (!sourceOption || sourceOption.disabled) return;
        instance.select.selectedIndex = index;
        instance.select.dispatchEvent(new Event('change', { bubbles: true }));
        syncSelect(instance);
        closeSelect(instance, true);
    };

    document.querySelectorAll('select:not([multiple]):not([data-native-select])').forEach((select, selectIndex) => {
        if (select.dataset.smartSelect === 'true') return;
        select.dataset.smartSelect = 'true';

        const wrapper = document.createElement('div');
        wrapper.className = 'smart-select';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        select.classList.add('native-select-enhanced');

        const menuId = `smart-select-menu-${selectIndex}`;
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'smart-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', menuId);
        trigger.setAttribute('aria-label', select.labels?.[0]?.textContent.trim() || select.getAttribute('aria-label') || select.name);
        trigger.innerHTML = `<span class="smart-select-value"></span>${chevron}`;
        wrapper.appendChild(trigger);

        const menu = document.createElement('div');
        menu.id = menuId;
        menu.className = 'smart-select-popover';
        menu.setAttribute('role', 'listbox');
        menu.setAttribute('aria-label', select.getAttribute('aria-label') || select.name || 'Options');
        document.body.appendChild(menu);

        const instance = {
            select,
            wrapper,
            trigger,
            value: trigger.querySelector('.smart-select-value'),
            menu,
            options: [],
        };

        Array.from(select.options).forEach((sourceOption, optionIndex) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'smart-select-option';
            option.setAttribute('role', 'option');
            option.disabled = sourceOption.disabled;
            option.innerHTML = `<span></span>${check}`;
            option.querySelector('span').textContent = sourceOption.textContent.trim();
            option.addEventListener('click', () => chooseOption(instance, optionIndex));
            option.addEventListener('keydown', (event) => {
                const available = instance.options.filter((item) => !item.disabled);
                const current = available.indexOf(option);
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    focusOption(instance, current + (event.key === 'ArrowDown' ? 1 : -1));
                } else if (event.key === 'Home' || event.key === 'End') {
                    event.preventDefault();
                    focusOption(instance, event.key === 'Home' ? 0 : available.length - 1);
                } else if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    option.click();
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    closeSelect(instance, true);
                } else if (event.key === 'Tab') {
                    closeSelect(instance);
                }
            });
            menu.appendChild(option);
            instance.options.push(option);
        });

        trigger.addEventListener('click', () => {
            if (activeSelect === instance) closeSelect(instance);
            else openSelect(instance);
        });
        trigger.addEventListener('keydown', (event) => {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
                event.preventDefault();
                openSelect(instance, event.key === 'ArrowUp' ? -1 : 1);
                return;
            }

            if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                clearTimeout(typeaheadTimer);
                typeahead += event.key.toLocaleLowerCase();
                const options = Array.from(select.options);
                const match = options.findIndex((option) => !option.disabled && option.textContent.trim().toLocaleLowerCase().startsWith(typeahead));
                if (match >= 0) {
                    select.selectedIndex = match;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
                typeaheadTimer = setTimeout(() => { typeahead = ''; }, 650);
            }
        });

        select.addEventListener('change', () => syncSelect(instance));
        select.form?.addEventListener('reset', () => setTimeout(() => syncSelect(instance)));
        if (select.id) {
            document.querySelectorAll(`label[for="${CSS.escape(select.id)}"]`).forEach((label) => {
                label.addEventListener('click', (event) => {
                    event.preventDefault();
                    trigger.focus();
                });
            });
        }

        syncSelect(instance);
    });

    document.addEventListener('click', (event) => {
        if (activeSelect && !activeSelect.wrapper.contains(event.target) && !activeSelect.menu.contains(event.target)) closeSelect(activeSelect);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && activeSelect) closeSelect(activeSelect, true);
    });
    window.addEventListener('resize', () => activeSelect && closeSelect(activeSelect));
    document.addEventListener('scroll', (event) => {
        if (activeSelect && !activeSelect.menu.contains(event.target)) closeSelect(activeSelect);
    }, true);
})();
