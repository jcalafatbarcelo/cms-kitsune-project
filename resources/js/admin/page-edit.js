import { initializePageEdit } from './page-edit-controller';

let editorModule;

const loadEditor = () => {
    editorModule ??= import('./tinymce');

    return editorModule;
};

document.querySelectorAll('[data-page-edit]').forEach((pageEdit) => {
    initializePageEdit(pageEdit, loadEditor);
});
