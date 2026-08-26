(() => {
    'use strict';

    let activeSelect = null;
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
        const below = window.innerHeight - rect.bottom - 13;
        const above = rect.top - 13;

        instance.menu.style.width = `${width}px`;
        instance.menu.style.left = `${left}px`;
        instance.menu.style.top = `${rect.bottom + 5}px`;
        instance.menu.style.bottom = 'auto';
        instance.menu.style.maxHeight = `${Math.max(96, Math.min(280, below))}px`;

        if (below < 160 && above > below) {
            instance.menu.style.top = 'auto';
            instance.menu.style.bottom = `${window.innerHeight - rect.top + 5}px`;
            instance.menu.style.maxHeight = `${Math.max(96, Math.min(280, above))}px`;
        }
    };

    const syncSelect = (instance) => {
        const selectedIndex = instance.select.selectedIndex;
        const selected = instance.select.options[selectedIndex];
        const label = selected?.textContent.trim() || '';

        instance.value.textContent = label;
        instance.trigger.title = label;
        instance.trigger.disabled = instance.select.disabled;
        instance.options.forEach((option, index) => {
            const sourceOption = instance.select.options[index];
            const unavailable = Boolean(sourceOption?.disabled || sourceOption?.hidden);
            option.disabled = unavailable;
            option.hidden = unavailable;
            option.classList.toggle('selected', index === selectedIndex);
            option.setAttribute('aria-selected', String(index === selectedIndex));
        });
    };

    const availableOptions = (instance) => instance.options.filter((option) => !option.disabled && !option.hidden);

    const focusOption = (instance, index) => {
        const available = availableOptions(instance);
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

        const available = availableOptions(instance);
        const selectedIndex = available.findIndex((option) => option.classList.contains('selected'));
        focusOption(instance, direction < 0 ? available.length - 1 : Math.max(0, selectedIndex));
    };

    const chooseOption = (instance, index) => {
        const sourceOption = instance.select.options[index];
        if (!sourceOption || sourceOption.disabled || sourceOption.hidden) return;
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

        const menuId = `fallback-smart-select-${selectIndex}`;
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'smart-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', menuId);
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
            option.innerHTML = `<span></span>${check}`;
            option.querySelector('span').textContent = sourceOption.textContent.trim();
            option.addEventListener('click', () => chooseOption(instance, optionIndex));
            option.addEventListener('keydown', (event) => {
                const available = availableOptions(instance);
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
            } else if (event.key === 'Escape') {
                closeSelect(instance);
            }
        });

        select.addEventListener('change', () => syncSelect(instance));
        select.form?.addEventListener('reset', () => window.setTimeout(() => syncSelect(instance)));
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
        if (activeSelect && !activeSelect.wrapper.contains(event.target) && !activeSelect.menu.contains(event.target)) {
            closeSelect(activeSelect);
        }
    });
    window.addEventListener('resize', () => activeSelect && closeSelect(activeSelect));
    document.addEventListener('scroll', (event) => {
        if (activeSelect && !activeSelect.menu.contains(event.target)) closeSelect(activeSelect);
    }, true);
})();
