<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Language\Models\LanguageSetting;
use Modules\Core\Template\Services\TemplateUiCatalogs;
use Modules\Pages\Exceptions\PageOperationException;
use Modules\Pages\Services\PageManager;

Route::get('/', function (PageManager $pages, TemplateUiCatalogs $templateUi) {
    $locale = LanguageSetting::query()->findOrFail(1)->frontendDefaultLanguage->locale;

    try {
        ['translation' => $translation, 'template' => $template] = $pages->home($locale);
    } catch (PageOperationException) {
        abort(404);
    }

    return view()->file(
        base_path('Templates/'.$template->directory.'/Resources/views/public/page/standard.blade.php'),
        compact('translation', 'template', 'templateUi', 'locale'),
    );
});
