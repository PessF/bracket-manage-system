import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

const source = readFileSync(new URL('../../resources/js/bracket-search.js', import.meta.url), 'utf8');
function element(dataset = {}) {
    const classes = new Set();
    return {
        dataset, value: '', disabled: false, textContent: '', listeners: {},
        classList: {
            toggle(name, enabled) { enabled ? classes.add(name) : classes.delete(name); },
            add(name) { classes.add(name); }, remove(name) { classes.delete(name); },
            contains(name) { return classes.has(name); },
        },
        addEventListener(name, listener) { this.listeners[name] = listener; },
        focus() {}, scrollIntoView() {},
    };
}
function boot(values, missingControl = null) {
    const controls = Object.fromEntries(['input', 'status', 'next', 'clear'].map(name => [name, element()]));
    controls.status.dataset = { hint: 'Search', count: ':count matches', empty: 'No matches' };
    const slots = values.map(participantSearch => element({ participantSearch }));
    const listeners = {};
    const search = { querySelector(selector) {
        const name = selector.match(/data-bracket-search-(.+)\]/)[1];
        return name === missingControl ? null : controls[name];
    } };
    runInNewContext(source, {
        document: {
            querySelector: () => search, querySelectorAll: () => slots,
            addEventListener(name, listener) { listeners[name] = listener; },
        },
        matchMedia: () => ({ matches: false }),
    });
    return { controls, slots, listeners };
}

test('invalid JSON, non-array values and non-string names do not interrupt search', () => {
    const { controls, slots } = boot(['{bad', 'null', '{}', '42', '[null, {}, 7]', '["Alpha", null]']);
    controls.input.value = 'alpha';
    controls.input.listeners.input();
    assert.equal(controls.status.textContent, '1 matches');
    assert.equal(controls.next.disabled, false);
    assert.equal(slots[5].classList.contains('is-search-match'), true);
    controls.next.listeners.click();
    assert.equal(slots[5].classList.contains('is-search-current'), true);
    controls.clear.listeners.click();
    assert.equal(controls.status.textContent, 'Search');
    assert.equal(controls.next.disabled, true);
});

test('partial search controls do not throw during initialization', () => {
    for (const name of ['input', 'status', 'next', 'clear']) assert.doesNotThrow(() => boot([], name));
});

test('live updates recover after a malformed slot is replaced', () => {
    const { controls, slots, listeners } = boot(['bad']);
    controls.input.value = 'alpha';
    controls.input.listeners.input();
    assert.equal(controls.status.textContent, 'No matches');
    slots[0].dataset.participantSearch = '["Alpha"]';
    listeners['easykids:live-content-updated']();
    assert.equal(controls.status.textContent, '1 matches');
});
