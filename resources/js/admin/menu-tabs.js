const itemLabel = (item) => `${item.label} (#${item.id})`;

export function parentOptions(items, locale, excludedIds, labels) {
    const excluded = new Set(excludedIds.map(Number));

    return [
        { value: '', label: labels.root },
        ...items
            .filter((item) => item.locale === locale && ! excluded.has(Number(item.id)))
            .map((item) => ({ value: String(item.id), label: itemLabel(item) })),
    ];
}

export function insertionOptions(items, locale, parentId, excludedId, labels) {
    const normalizedParent = parentId === '' || parentId === null ? null : Number(parentId);
    const siblings = items
        .filter((item) => item.locale === locale
            && (item.parentId === null ? null : Number(item.parentId)) === normalizedParent
            && Number(item.id) !== Number(excludedId))
        .sort((first, second) => first.position - second.position || first.id - second.id);

    if (siblings.length === 0) {
        return [{ value: '1', label: labels.last }];
    }

    return [
        { value: '1', label: labels.first },
        ...siblings.slice(0, -1).map((item, index) => ({
            value: String(index + 2),
            label: labels.after.replace(':item', itemLabel(item)),
        })),
        { value: String(siblings.length + 1), label: labels.lastAfter.replace(':item', itemLabel(siblings.at(-1))) },
    ];
}

export function initializeMenuTabs(container) {
    const tabs = Array.from(container.querySelectorAll('[role="tab"]'));
    const panels = tabs.map((tab) => container.ownerDocument.getElementById(tab.getAttribute('aria-controls')));

    const select = (nextIndex) => {
        tabs.forEach((tab, index) => {
            const selected = index === nextIndex;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
            panels[index].hidden = ! selected;
            panels[index].setAttribute('aria-hidden', String(! selected));
        });
    };

    const initialIndex = Math.max(0, tabs.findIndex((tab) => tab.id === container.dataset.activeTab));

    container.classList.add('is-enhanced');
    select(initialIndex);

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => select(index));
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

            select(nextIndex);
            tabs[nextIndex].focus();
        });
    });
}

function replaceOptions(select, options, selectedValue = null) {
    select.replaceChildren(...options.map((option) => {
        const element = select.ownerDocument.createElement('option');
        element.value = option.value;
        element.textContent = option.label;
        element.selected = selectedValue !== null && option.value === String(selectedValue);

        return element;
    }));
}

export function initializeMenuPositions(container) {
    const data = JSON.parse(container.querySelector('[data-menu-items]').textContent);
    const labels = JSON.parse(container.querySelector('[data-menu-position-labels]').textContent);

    for (const form of container.querySelectorAll('[data-menu-position-form]')) {
        const localeSelect = form.querySelector('[data-menu-locale]');
        const parentSelect = form.querySelector('[data-menu-parent]');
        const positionSelect = form.querySelector('[data-menu-position]');
        const excludedIds = JSON.parse(form.dataset.excludedIds || '[]');
        const excludedId = form.dataset.itemId || null;
        const locale = () => localeSelect?.value ?? form.dataset.locale;

        const refreshPosition = (selectLast = true) => {
            const options = insertionOptions(data, locale(), parentSelect.value, excludedId, labels);
            replaceOptions(positionSelect, options, selectLast ? options.at(-1).value : positionSelect.value);
        };
        const refreshParent = () => {
            replaceOptions(parentSelect, parentOptions(data, locale(), excludedIds, labels), '');
            refreshPosition();
        };

        localeSelect?.addEventListener('change', refreshParent);
        parentSelect.addEventListener('change', () => refreshPosition());
    }
}

if (typeof document !== 'undefined') {
    for (const container of document.querySelectorAll('[data-menu-tabs]')) {
        initializeMenuTabs(container);
        initializeMenuPositions(container);
    }
}
