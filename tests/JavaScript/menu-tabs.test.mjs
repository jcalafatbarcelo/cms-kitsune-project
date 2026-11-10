import assert from 'node:assert/strict';
import test from 'node:test';
import {
    insertionOptions,
    parentOptions,
    initializeMenuPositions,
    initializeMenuTabs,
} from '../../resources/js/admin/menu-tabs.js';

class Element {
    constructor(attributes = {}) {
        this.attributes = new Map(Object.entries(attributes));
        this.id = attributes.id ?? '';
        this.hidden = false;
        this.listeners = new Map();
        this.tabIndex = 0;
    }

    addEventListener(type, callback) {
        this.listeners.set(type, callback);
    }

    dispatch(type, event = {}) {
        const dispatch = { preventDefaultCalls: 0 };
        this.listeners.get(type)?.({
            ...event,
            preventDefault() {
                dispatch.preventDefaultCalls += 1;
            },
        });

        return dispatch;
    }

    focus() {
        for (const element of this.focusGroup ?? []) {
            element.focused = false;
        }
        this.focused = true;
    }

    getAttribute(name) {
        return this.attributes.get(name);
    }

    setAttribute(name, value) {
        this.attributes.set(name, value);
    }
}

function assertTabState(tabs, panels, selectedIndex, focusedIndex) {
    assert.deepEqual(panels.map((panel) => panel.hidden), panels.map((_, index) => index !== selectedIndex));
    assert.deepEqual(panels.map((panel) => panel.getAttribute('aria-hidden')), panels.map((_, index) => String(index !== selectedIndex)));
    assert.deepEqual(tabs.map((tab) => tab.getAttribute('aria-selected')), tabs.map((_, index) => String(index === selectedIndex)));
    assert.deepEqual(tabs.map((tab) => tab.tabIndex), tabs.map((_, index) => index === selectedIndex ? 0 : -1));
    assert.deepEqual(tabs.map((tab) => Boolean(tab.focused)), tabs.map((_, index) => index === focusedIndex));
}

function dispatchFromFocusedTab(tabs, index, type, event = {}) {
    tabs.forEach((tab) => { tab.focused = false; });
    tabs[index].focus();

    return tabs[index].dispatch(type, event);
}

test('three menu tabs preserve fallback panels and synchronize click and all keyboard navigation', () => {
    const tabs = ['items-panel', 'add-panel', 'remove-panel'].map((panel, index) => new Element({ id: ['items-tab', 'add-item-tab', 'remove-home-tab'][index], 'aria-controls': panel }));
    tabs.forEach((tab) => { tab.focusGroup = tabs; });
    const panels = tabs.map(() => new Element());
    const container = {
        dataset: {},
        classList: { add(name) { container.enhanced = name; } },
        querySelectorAll: () => tabs,
        ownerDocument: { getElementById: (id) => panels[['items-panel', 'add-panel', 'remove-panel'].indexOf(id)] },
    };

    assert.deepEqual(panels.map((panel) => panel.hidden), [false, false, false]);
    initializeMenuTabs(container);

    assert.equal(container.enhanced, 'is-enhanced');
    assertTabState(tabs, panels, 0, -1);

    const click = dispatchFromFocusedTab(tabs, 1, 'click');
    assert.equal(click.preventDefaultCalls, 0);
    assertTabState(tabs, panels, 1, 1);

    const arrowRight = dispatchFromFocusedTab(tabs, 1, 'keydown', { key: 'ArrowRight' });
    assert.equal(arrowRight.preventDefaultCalls, 1);
    assertTabState(tabs, panels, 2, 2);

    const arrowDown = dispatchFromFocusedTab(tabs, 2, 'keydown', { key: 'ArrowDown' });
    assert.equal(arrowDown.preventDefaultCalls, 1);
    assertTabState(tabs, panels, 0, 0);

    const arrowLeft = dispatchFromFocusedTab(tabs, 0, 'keydown', { key: 'ArrowLeft' });
    assert.equal(arrowLeft.preventDefaultCalls, 1);
    assertTabState(tabs, panels, 2, 2);

    const arrowUp = dispatchFromFocusedTab(tabs, 2, 'keydown', { key: 'ArrowUp' });
    assert.equal(arrowUp.preventDefaultCalls, 1);
    assertTabState(tabs, panels, 1, 1);

    const home = dispatchFromFocusedTab(tabs, 1, 'keydown', { key: 'Home' });
    assert.equal(home.preventDefaultCalls, 1);
    assertTabState(tabs, panels, 0, 0);

    const end = dispatchFromFocusedTab(tabs, 0, 'keydown', { key: 'End' });
    assert.equal(end.preventDefaultCalls, 1);
    assertTabState(tabs, panels, 2, 2);
});

test('a recovered Add item error initializes its tab instead of Items', () => {
    const tabs = ['items-panel', 'add-panel', 'remove-panel'].map((panel, index) => new Element({ id: ['items-tab', 'add-item-tab', 'remove-home-tab'][index], 'aria-controls': panel }));
    const panels = tabs.map(() => new Element());
    const container = {
        dataset: { activeTab: 'add-item-tab' },
        classList: { add() {} },
        querySelectorAll: () => tabs,
        ownerDocument: { getElementById: (id) => panels[['items-panel', 'add-panel', 'remove-panel'].indexOf(id)] },
    };

    initializeMenuTabs(container);

    assert.deepEqual(panels.map((panel) => panel.hidden), [true, false, true]);
    assert.deepEqual(tabs.map((tab) => tab.tabIndex), [-1, 0, -1]);
});

test('parent and position options follow locale, parent and moving item exclusions', () => {
    const items = [
        { id: 1, locale: 'en', parentId: null, position: 1, label: 'First' },
        { id: 2, locale: 'en', parentId: null, position: 2, label: 'Second' },
        { id: 3, locale: 'en', parentId: 1, position: 1, label: 'Child' },
        { id: 4, locale: 'es_ES', parentId: null, position: 1, label: 'Español' },
    ];
    const labels = { root: '-- None --', first: 'First position', after: 'After :item', last: 'Last position', lastAfter: 'Last position, after :item' };

    assert.deepEqual(parentOptions(items, 'en', [1, 3], labels), [
        { value: '', label: '-- None --' },
        { value: '2', label: 'Second (#2)' },
    ]);
    assert.deepEqual(parentOptions(items, 'es_ES', [], labels), [
        { value: '', label: '-- None --' },
        { value: '4', label: 'Español (#4)' },
    ]);
    assert.deepEqual(insertionOptions(items, 'en', null, null, labels), [
        { value: '1', label: 'First position' },
        { value: '2', label: 'After First (#1)' },
        { value: '3', label: 'Last position, after Second (#2)' },
    ]);
    assert.deepEqual(insertionOptions(items, 'en', null, 2, labels), [
        { value: '1', label: 'First position' },
        { value: '2', label: 'Last position, after First (#1)' },
    ]);
    assert.deepEqual(insertionOptions([], 'en', null, null, labels), [
        { value: '1', label: 'Last position' },
    ]);
    assert.equal(Number(insertionOptions(items, 'en', 1, null, labels).at(-1).value), 2);
});

test('locale and parent changes rebuild valid options and select the final integer gap', () => {
    const document = {
        createElement: () => ({ selected: false, textContent: '', value: '' }),
    };
    const select = (value = '') => ({
        listeners: new Map(),
        ownerDocument: document,
        value,
        addEventListener(type, callback) { this.listeners.set(type, callback); },
        dispatch(type) { this.listeners.get(type)?.(); },
        replaceChildren(...options) {
            this.options = options;
            const selected = options.find((option) => option.selected) ?? options[0];
            this.value = selected?.value ?? '';
        },
    });
    const locale = select('en');
    const parent = select('');
    const position = select('');
    const form = {
        dataset: { excludedIds: '[]' },
        querySelector(selector) {
            return {
                '[data-menu-locale]': locale,
                '[data-menu-parent]': parent,
                '[data-menu-position]': position,
            }[selector];
        },
    };
    const data = [
        { id: 1, locale: 'en', parentId: null, position: 1, label: 'English' },
        { id: 2, locale: 'en', parentId: 1, position: 1, label: 'Child' },
        { id: 3, locale: 'es_ES', parentId: null, position: 1, label: 'Español' },
    ];
    const labels = { root: '-- None --', first: 'First position', after: 'After :item', last: 'Last position', lastAfter: 'Last position, after :item' };
    const container = {
        querySelector(selector) {
            return {
                '[data-menu-items]': { textContent: JSON.stringify(data) },
                '[data-menu-position-labels]': { textContent: JSON.stringify(labels) },
            }[selector];
        },
        querySelectorAll: () => [form],
    };

    initializeMenuPositions(container);
    locale.value = 'es_ES';
    locale.dispatch('change');

    assert.deepEqual(parent.options.map((option) => option.textContent), ['-- None --', 'Español (#3)']);
    assert.equal(position.value, '2');
    assert.equal(Number.isInteger(Number(position.value)), true);

    locale.value = 'en';
    locale.dispatch('change');
    parent.value = '1';
    parent.dispatch('change');

    assert.deepEqual(position.options.map((option) => option.value), ['1', '2']);
    assert.equal(position.value, '2');
});
