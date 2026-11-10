import { initializePageEdit } from './page-edit-controller.js';

export function createEditorLoader(importEditor = () => import('./tinymce')) {
    let editorModule;

    return () => {
        editorModule ??= importEditor().catch((error) => {
            editorModule = undefined;
            throw error;
        });

        return editorModule;
    };
}

if (typeof document !== 'undefined') {
    const loadEditor = createEditorLoader();

    document.querySelectorAll('[data-page-edit]').forEach((pageEdit) => {
        initializePageEdit(pageEdit, loadEditor);
    });
}
