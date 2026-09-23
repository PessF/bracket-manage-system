// Shared custom dropdown enhancement for bundled and fallback pages.
(() => {
    const instances = new Map();
    let nextId = 0;
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
        instance.trigger.disabled = instance.select.matches(':disabled');
        instance.trigger.setAttribute('aria-required', String(instance.select.required));
        instance.trigger.setAttribute('aria-invalid', String(instance.select.matches(':user-invalid')));
        if (instance.trigger.disabled && activeSelect === instance) closeSelect(instance);
        instance.options.forEach((option, index) => {
            const sourceOption = instance.select.options[index];
            const unavailable = Boolean(sourceOption?.disabled || sourceOption?.hidden || sourceOption?.parentElement.matches('optgroup:disabled, optgroup[hidden]'));
            const isSelected = index === selectedIndex;
            option.querySelector('span').textContent = sourceOption?.textContent.trim() || '';
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
        syncSelect(instance);
        if (instance.trigger.disabled) return;
        if (activeSelect && activeSelect !== instance) closeSelect(activeSelect);
        activeSelect = instance;
        instance.menu.classList.add('open');
        instance.trigger.setAttribute('aria-expanded', 'true');
        positionSelect(instance);

        const available = instance.options.filter((option) => !option.disabled);
        const selected = available.findIndex((option) => option.classList.contains('selected'));
        const destination = direction < 0 ? available.length - 1 : Math.max(0, selected);
        requestAnimationFrame(() => { if (activeSelect === instance) focusOption(instance, destination); });
    };

    const chooseOption = (instance, index) => {
        const sourceOption = instance.select.options[index];
        if (!sourceOption || instance.options[index]?.disabled) return;
        instance.select.selectedIndex = index;
        instance.select.dispatchEvent(new Event('input', { bubbles: true }));
        instance.select.dispatchEvent(new Event('change', { bubbles: true }));
        syncSelect(instance);
        closeSelect(instance, true);
    };

    const enhanceSelect = (select) => {
        if (select.dataset.smartSelect === 'true') return;
        select.dataset.smartSelect = 'true';

        const wrapper = document.createElement('div');
        wrapper.className = 'smart-select';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        select.classList.add('native-select-enhanced');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        const menuId = `smart-select-menu-${++nextId}`;
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'smart-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', menuId);
        const label = select.labels?.[0]?.textContent.trim() || select.getAttribute('aria-label') || select.closest('.field')?.querySelector('label')?.textContent.trim() || select.name || 'Options';
        trigger.setAttribute('aria-label', label);
        if (select.hasAttribute('aria-describedby')) trigger.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
        trigger.innerHTML = `<span class="smart-select-value"></span>${chevron}`;
        wrapper.appendChild(trigger);

        const menu = document.createElement('div');
        menu.id = menuId;
        menu.className = 'smart-select-popover';
        menu.setAttribute('role', 'listbox');
        menu.setAttribute('aria-label', label);
        document.body.appendChild(menu);

        const instance = {
            select,
            wrapper,
            trigger,
            value: trigger.querySelector('.smart-select-value'),
            menu,
            options: [],
        };

        const buildOptions = () => {
            menu.replaceChildren();
            instance.options = [];
            Array.from(select.options).forEach((sourceOption, optionIndex) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.tabIndex = -1;
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
                        closeSelect(instance, true);
                    }
                });
                menu.appendChild(option);
                instance.options.push(option);
            });
        };
        buildOptions();

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
                const match = options.findIndex((option) => !option.disabled && !option.hidden && !option.parentElement.matches('optgroup:disabled, optgroup[hidden]') && option.textContent.trim().toLocaleLowerCase().startsWith(typeahead));
                if (match >= 0) {
                    select.selectedIndex = match;
                    select.dispatchEvent(new Event('input', { bubbles: true }));
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
                typeaheadTimer = setTimeout(() => { typeahead = ''; }, 650);
            }
        });

        select.addEventListener('change', () => syncSelect(instance));
        select.addEventListener('invalid', (event) => {
            event.preventDefault();
            trigger.setAttribute('aria-invalid', 'true');
            trigger.focus();
        });
        const observer = new MutationObserver((records) => {
            if (records.some((record) => record.type === 'childList')) buildOptions();
            syncSelect(instance);
        });
        observer.observe(select, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['disabled', 'hidden', 'selected', 'label', 'required'] });
        instance.observer = observer;
        instances.set(select, instance);

        syncSelect(instance);
    };

    const initialize = () => {
        for (const [select, instance] of instances) {
            if (!select.isConnected) {
                closeSelect(instance);
                instance.observer.disconnect();
                instance.menu.remove();
                instances.delete(select);
            }
        }
        document.querySelectorAll('select:not([multiple])').forEach(enhanceSelect);
        instances.forEach(syncSelect);
    };
    initialize();
    document.addEventListener('easykids:live-content-updated', initialize);
    document.addEventListener('reset', (event) => setTimeout(() => {
        instances.forEach((instance) => {
            if (instance.select.form === event.target) syncSelect(instance);
        });
    }));
    document.addEventListener('click', (event) => {
        const label = event.target.closest('label');
        const instance = label?.control && instances.get(label.control);
        if (instance && !instance.trigger.disabled) {
            event.preventDefault();
            instance.trigger.focus();
        }
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
