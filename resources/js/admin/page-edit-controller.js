export function initializePageEdit(pageEdit, loadEditor) {
    const panels = Array.from(pageEdit.querySelectorAll('[data-translation-panel]'));
    const tabs = Array.from(pageEdit.querySelectorAll('[data-translation-tab]'));
    const languageSelect = pageEdit.querySelector('[data-language-select]');
    const languageSearch = pageEdit.querySelector('[data-language-search]');
    let activePanel = null;
    let generation = 0;
    let queue = Promise.resolve();
    let requestedPanel = null;

    const textareas = (panel) => Array.from(panel.querySelectorAll('textarea[data-editor="tinymce"]'));

    const closePanel = async (panel) => {
        const fields = textareas(panel);

        if (fields.length === 0) {
            return;
        }

        let editor;
        try {
            editor = await loadEditor();
        } catch {
            return;
        }

        const { saveAndDestroy } = editor;
        fields.forEach(saveAndDestroy);
    };

    const openPanel = async (panel, token) => {
        const fields = textareas(panel);

        if (fields.length === 0) {
            return;
        }

        let editor;
        try {
            editor = await loadEditor();
        } catch {
            return;
        }

        const { initialize, saveAndDestroy } = editor;
        if (token !== generation || panel !== activePanel) {
            return;
        }

        await Promise.all(fields.map((textarea) => initialize(textarea)));
        if (token !== generation || panel !== activePanel) {
            fields.forEach(saveAndDestroy);
        }
    };

    const selectPanel = (panel) => {
        const locale = panel.dataset.translationPanel;

        panels.forEach((candidate) => {
            const selected = candidate === panel;
            candidate.hidden = ! selected;
            candidate.setAttribute('aria-hidden', String(! selected));
        });
        tabs.forEach((tab) => {
            const selected = tab.dataset.translationTab === locale;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        if (languageSelect) {
            languageSelect.value = locale;
        }
    };

    const activate = (locale) => {
        const nextPanel = panels.find((panel) => panel.dataset.translationPanel === locale);

        if (! nextPanel || (nextPanel === activePanel && requestedPanel === null)) {
            return queue;
        }

        const token = ++generation;
        requestedPanel = nextPanel;
        queue = queue.catch(() => undefined).then(async () => {
            if (token !== generation) {
                return;
            }

            if (activePanel) {
                await closePanel(activePanel);
            }
            if (token !== generation) {
                return;
            }

            selectPanel(nextPanel);
            activePanel = nextPanel;
            await openPanel(nextPanel, token);
            if (token === generation) {
                requestedPanel = null;
            }
        });

        return queue;
    };

    pageEdit.classList.add('is-enhanced');
    activate(pageEdit.dataset.activeLocale);

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab.dataset.translationTab));
        tab.addEventListener('keydown', (event) => {
            const keys = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
            let nextIndex = index;

            if (event.key in keys) {
                event.preventDefault();
                nextIndex = (index + keys[event.key] + tabs.length) % tabs.length;
            } else if (event.key === 'Home') {
                event.preventDefault();
                nextIndex = 0;
            } else if (event.key === 'End') {
                event.preventDefault();
                nextIndex = tabs.length - 1;
            } else {
                return;
            }

            activate(tabs[nextIndex].dataset.translationTab);
            tabs[nextIndex].focus();
        });
    });

    languageSelect?.addEventListener('change', () => activate(languageSelect.value));
    languageSearch?.addEventListener('input', () => {
        const query = languageSearch.value.trim().toLocaleLowerCase();

        Array.from(languageSelect.options).forEach((option) => {
            option.hidden = query !== '' && ! option.text.toLocaleLowerCase().includes(query);
        });
    });

    return { activate, whenIdle: () => queue };
}
