import tinymce from 'tinymce/tinymce';
import 'tinymce/icons/default';
import 'tinymce/themes/silver';
import 'tinymce/models/dom';
import 'tinymce/skins/ui/oxide/skin.min.css';
import 'tinymce/skins/content/default/content.min.css';
import 'tinymce/plugins/lists';
import 'tinymce/plugins/link';
import 'tinymce/plugins/table';
import 'tinymce/plugins/code';

export function initialize(textarea) {
    return tinymce.init({
        target: textarea,
        license_key: 'gpl',
        skin: false,
        content_css: false,
        branding: false,
        promotion: false,
        plugins: 'lists link table code',
        toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link | table | code',
        menubar: false,
        height: 320,
    });
}

export function saveAndDestroy(textarea) {
    const editor = tinymce.get(textarea.id);

    if (editor) {
        editor.save();
        editor.remove();
    }
}
