import assert from 'node:assert/strict';
import test from 'node:test';
import { initializePageEdit } from '../../resources/js/admin/page-edit-controller.js';

class Element {
    constructor({ dataset = {}, text = '', value = '' } = {}) {
        this.attributes = new Map();
        this.dataset = dataset;
        this.hidden = false;
        this.listeners = new Map();
        this.text = text;
        this.value = value;
        this.tabIndex = 0;
    }

    addEventListener(type, callback) {
        this.listeners.set(type, callback);
    }

    dispatch(type, event = {}) {
        this.listeners.get(type)?.({ preventDefault() {}, ...event });
    }

    focus() {
        this.focused = true;
    }

    setAttribute(name, value) {
        this.attributes.set(name, value);
    }
}

class Panel extends Element {
    constructor(locale, textarea, status) {
        super({ dataset: { translationPanel: locale } });
        this.status = new Element({ text: status });
        this.textarea = textarea;
    }

    querySelectorAll() {
        return this.textarea ? [this.textarea] : [];
    }
}

class PageEdit extends Element {
    constructor(locales, { withEditor = true, withSelector = true } = {}) {
        super({ dataset: { activeLocale: locales[0] } });
        this.classList = { add: (name) => { this.enhanced = name; } };
        this.tabs = locales.map((locale) => new Element({ dataset: { translationTab: locale } }));
        this.panels = locales.map((locale) => new Panel(
            locale,
            withEditor ? new Element({ value: `${locale} saved` }) : null,
            locale === 'en' ? 'Published' : 'Unpublished',
        ));
        this.languageSearch = withSelector ? new Element() : null;
        this.languageSelect = withSelector ? new Element() : null;
        if (this.languageSelect) {
            this.languageSelect.options = locales.map((locale) => ({ text: `${locale} Language`, hidden: false }));
        }
    }

    querySelectorAll(selector) {
        if (selector === '[data-translation-panel]') {
            return this.panels;
        }

        if (selector === '[data-translation-tab]') {
            return this.tabs;
        }

        return [];
    }

    querySelector(selector) {
        if (selector === '[data-language-select]') {
            return this.languageSelect;
        }

        if (selector === '[data-language-search]') {
            return this.languageSearch;
        }

        return null;
    }
}

function editorHarness() {
    const active = new Set();
    const initialized = [];
    const destroyed = [];

    return {
        active,
        destroyed,
        initialized,
        initialize: async (textarea) => {
            active.add(textarea);
            initialized.push(textarea);
        },
        saveAndDestroy: (textarea) => {
            if (! active.has(textarea)) {
                return;
            }

            textarea.value = textarea.draft ?? textarea.value;
            active.delete(textarea);
            destroyed.push(textarea);
        },
    };
}

test('filters native language options and activates tabs with the keyboard', async () => {
    const pageEdit = new PageEdit(['en', 'es_ES', 'fr'], { withEditor: false });
    const controller = initializePageEdit(pageEdit, async () => editorHarness());
    await controller.whenIdle();

    assert.equal(pageEdit.enhanced, 'is-enhanced');
    assert.equal(pageEdit.panels.find((panel) => ! panel.hidden).status.text, 'Published');

    pageEdit.languageSearch.value = 'es';
    pageEdit.languageSearch.dispatch('input');
    assert.deepEqual(pageEdit.languageSelect.options.map((option) => option.hidden), [true, false, true]);

    pageEdit.tabs[0].dispatch('keydown', { key: 'ArrowRight' });
    await controller.whenIdle();
    assert.equal(pageEdit.panels[1].hidden, false);
    assert.equal(pageEdit.panels[0].hidden, true);
    assert.equal(pageEdit.panels.find((panel) => ! panel.hidden).status.text, 'Unpublished');
    assert.equal(pageEdit.tabs[1].focused, true);
    assert.equal(pageEdit.tabs[1].attributes.get('aria-selected'), 'true');
});

test('synchronizes and destroys the prior translation before activating the next one', async () => {
    const pageEdit = new PageEdit(['en', 'es_ES']);
    const editor = editorHarness();
    const controller = initializePageEdit(pageEdit, async () => editor);
    await controller.whenIdle();

    const english = pageEdit.panels[0].textarea;
    english.draft = 'Unsaved English draft';
    pageEdit.tabs[1].dispatch('click');
    await controller.whenIdle();

    assert.equal(english.value, 'Unsaved English draft');
    assert.deepEqual(editor.destroyed, [english]);
    assert.deepEqual([...editor.active], [pageEdit.panels[1].textarea]);
    assert.equal(pageEdit.panels[0].hidden, true);
});

test('discards stale dynamic editor work during rapid locale changes', async () => {
    const pageEdit = new PageEdit(['en', 'es_ES', 'fr']);
    const editor = editorHarness();
    let resolveEditor;
    const editorImport = new Promise((resolve) => { resolveEditor = resolve; });
    const controller = initializePageEdit(pageEdit, () => editorImport);

    pageEdit.tabs[1].dispatch('click');
    pageEdit.tabs[2].dispatch('click');
    resolveEditor(editor);
    await controller.whenIdle();

    assert.deepEqual(editor.initialized, [pageEdit.panels[2].textarea]);
    assert.deepEqual([...editor.active], [pageEdit.panels[2].textarea]);
    assert.equal(pageEdit.panels[2].hidden, false);
    assert.equal(pageEdit.panels[0].hidden, true);
    assert.equal(pageEdit.panels[1].hidden, true);
});

test('destroys a stale pending initialization before it can outlive the active translation', async () => {
    const pageEdit = new PageEdit(['en', 'es_ES']);
    const editor = editorHarness();
    const english = pageEdit.panels[0].textarea;
    const initializeImmediately = editor.initialize;
    let resolveEnglishInitialization;
    let signalEnglishInitialization;
    const englishInitializationStarted = new Promise((resolve) => { signalEnglishInitialization = resolve; });
    editor.initialize = (textarea) => {
        if (textarea !== english) {
            return initializeImmediately(textarea);
        }

        editor.active.add(textarea);
        editor.initialized.push(textarea);
        signalEnglishInitialization();

        return new Promise((resolve) => { resolveEnglishInitialization = resolve; });
    };
    const controller = initializePageEdit(pageEdit, async () => editor);
    await englishInitializationStarted;

    english.draft = 'Draft retained after stale initialization';
    pageEdit.tabs[1].dispatch('click');
    resolveEnglishInitialization();
    await controller.whenIdle();

    assert.equal(english.value, 'Draft retained after stale initialization');
    assert.deepEqual(editor.destroyed, [english]);
    assert.deepEqual([...editor.active], [pageEdit.panels[1].textarea]);
    assert.equal(pageEdit.panels[0].hidden, true);
});

test('keeps the final English selection when a pending initialization is interrupted by English to Spanish to English', async () => {
    const pageEdit = new PageEdit(['en', 'es_ES']);
    const editor = editorHarness();
    const english = pageEdit.panels[0].textarea;
    const initializeImmediately = editor.initialize;
    let firstInitialization = true;
    let resolveEnglishInitialization;
    let signalEnglishInitialization;
    const englishInitializationStarted = new Promise((resolve) => { signalEnglishInitialization = resolve; });
    editor.initialize = (textarea) => {
        if (textarea !== english || ! firstInitialization) {
            return initializeImmediately(textarea);
        }

        firstInitialization = false;
        editor.active.add(textarea);
        editor.initialized.push(textarea);
        signalEnglishInitialization();

        return new Promise((resolve) => { resolveEnglishInitialization = resolve; });
    };
    const controller = initializePageEdit(pageEdit, async () => editor);
    await englishInitializationStarted;

    english.draft = 'English draft wins';
    pageEdit.tabs[1].dispatch('click');
    pageEdit.tabs[0].dispatch('click');
    resolveEnglishInitialization();
    await controller.whenIdle();

    assert.equal(english.value, 'English draft wins');
    assert.deepEqual(editor.destroyed, [english]);
    assert.deepEqual([...editor.active], [english]);
    assert.equal(pageEdit.panels[0].hidden, false);
    assert.equal(pageEdit.panels[1].hidden, true);
});

test('keeps textarea fallback usable and changes translation when editor loading fails', async () => {
    const pageEdit = new PageEdit(['en', 'es_ES']);
    let attempts = 0;
    const controller = initializePageEdit(pageEdit, async () => {
        attempts += 1;
        throw new Error('Editor unavailable');
    });

    await assert.doesNotReject(controller.whenIdle());
    pageEdit.tabs[1].dispatch('click');
    await assert.doesNotReject(controller.whenIdle());

    assert.equal(pageEdit.panels[0].textarea.value, 'en saved');
    assert.equal(pageEdit.panels[1].textarea.value, 'es_ES saved');
    assert.equal(pageEdit.panels[0].hidden, true);
    assert.equal(pageEdit.panels[1].hidden, false);
    assert.equal(attempts, 3);
});

test('retries a rejected editor import and caches the later successful module', async () => {
    const { createEditorLoader } = await import('../../resources/js/admin/page-edit.js');
    const editor = editorHarness();
    let attempts = 0;
    const loadEditor = createEditorLoader(async () => {
        attempts += 1;
        if (attempts === 1) {
            throw new Error('Transient import failure');
        }

        return editor;
    });

    await assert.rejects(loadEditor(), /Transient import failure/);
    assert.equal(await loadEditor(), editor);
    assert.equal(await loadEditor(), editor);
    assert.equal(attempts, 2);
});
