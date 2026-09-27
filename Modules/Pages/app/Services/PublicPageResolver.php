<?php

namespace Modules\Pages\Services;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Core\Language\Models\Language;
use Modules\Core\Language\Models\LanguageSetting;
use Modules\Core\Localization\Exceptions\CatalogValidationException;
use Modules\Core\Template\Exceptions\TemplateOperationException;
use Modules\Core\Template\Services\TemplateUiCatalogs;
use Modules\Pages\Exceptions\PageOperationException;

class PublicPageResolver
{
    public function __construct(private readonly PageManager $pages) {}

    public function handle(Request $request, string $path = ''): Response|RedirectResponse|View
    {
        $segments = $path === '' ? [] : explode('/', $path);
        $rawPath = (string) parse_url((string) $request->server('REQUEST_URI'), PHP_URL_PATH);
        $hasTrailingSlash = str_ends_with($rawPath, '/') && $segments !== [];
        if (array_filter($segments, fn (string $segment) => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $segment) !== 1)) {
            abort(404);
        }

        $default = LanguageSetting::query()->with('frontendDefaultLanguage')->findOrFail(1)->frontendDefaultLanguage;
        [$language, $slugs, $canonicalPrefix, $explicit] = $this->languageFor($segments, $default);

        if ($explicit) {
            $request->session()->put('public_locale', $language->locale);
            if ($language->id === $default->id) {
                return $this->redirect($this->url(null, $slugs));
            }
            if ($segments[0] !== $canonicalPrefix) {
                return $this->redirect($this->url($language->id === $default->id ? null : $canonicalPrefix, $slugs));
            }
            if (($slugs === [] && ! $hasTrailingSlash) || ($slugs !== [] && $hasTrailingSlash)) {
                return $this->permanentRedirect($this->url($canonicalPrefix, $slugs));
            }
        } elseif ($segments === []) {
            $sessionLanguage = $this->sessionLanguage($request);
            $language = $sessionLanguage ?? $this->negotiate($request) ?? $default;
            $request->session()->put('public_locale', $language->locale);
            if ($language->id !== $default->id) {
                return $this->redirect($this->url($this->canonicalPrefix($language), []));
            }
        } else {
            $request->session()->put('public_locale', $default->locale);
            if ($hasTrailingSlash) {
                return $this->permanentRedirect($this->url(null, $slugs));
            }
        }

        try {
            ['translation' => $translation, 'template' => $template] = $this->pages->resolve($language->locale, $slugs);
        } catch (PageOperationException) {
            abort(404);
        } catch (CatalogValidationException|TemplateOperationException) {
            abort(503);
        }

        $locale = $language->locale;
        $templateUi = app(TemplateUiCatalogs::class);

        return view()->file(
            base_path('Templates/'.$template->directory.'/Resources/views/public/page/standard.blade.php'),
            compact('translation', 'template', 'templateUi', 'locale'),
        );
    }

    /** @return array{Language, array<int, string>, string, bool} */
    private function languageFor(array $segments, Language $default): array
    {
        if ($segments === []) {
            return [$default, [], '', false];
        }
        $prefix = $segments[0];
        $language = Language::query()->where('is_active', true)->where('url_prefix', $prefix)->first();
        if ($language !== null) {
            return [$language, array_slice($segments, 1), $this->canonicalPrefix($language), true];
        }
        if (preg_match('/^[a-z]{2}$/D', $prefix) === 1) {
            $family = Language::query()->where('is_active', true)->where('url_prefix', 'like', $prefix.'%')->get()
                ->filter(fn (Language $candidate) => substr($candidate->url_prefix, 0, 2) === $prefix);
            $general = $family->firstWhere('is_url_general', true);
            if ($general !== null || $family->count() === 1) {
                $language = $general ?? $family->sole();

                return [$language, array_slice($segments, 1), $prefix, true];
            }
        }

        return [$default, $segments, '', false];
    }

    private function canonicalPrefix(Language $language): string
    {
        $family = substr($language->url_prefix, 0, 2);
        $active = Language::query()->where('is_active', true)->get()
            ->filter(fn (Language $candidate) => substr($candidate->url_prefix, 0, 2) === $family);

        return ($language->is_url_general || $active->count() === 1) ? $family : $language->url_prefix;
    }

    private function sessionLanguage(Request $request): ?Language
    {
        $locale = $request->session()->get('public_locale');

        return is_string($locale) ? Language::query()->where('locale', $locale)->where('is_active', true)->first() : null;
    }

    private function negotiate(Request $request): ?Language
    {
        $choices = [];
        foreach (explode(',', (string) $request->header('Accept-Language')) as $position => $choice) {
            [$tag, $quality] = array_pad(explode(';q=', trim($choice), 2), 2, '1');
            $quality = is_numeric($quality) ? (float) $quality : 0;
            if (preg_match('/^([A-Za-z]{2})(?:-([A-Za-z]{2}))?$/D', $tag, $matches) === 1 && $quality > 0) {
                $base = strtolower($matches[1]);
                $choices[] = ['locale' => isset($matches[2]) ? $base.'_'.strtoupper($matches[2]) : $base, 'base' => $base, 'quality' => $quality, 'position' => $position];
            }
        }
        usort($choices, fn (array $a, array $b) => $b['quality'] <=> $a['quality'] ?: $a['position'] <=> $b['position']);
        $active = Language::query()->where('is_active', true)->get();
        foreach ($choices as $choice) {
            $exact = $active->firstWhere('locale', $choice['locale']);
            if ($exact !== null) {
                return $exact;
            }
            $base = $active->filter(fn (Language $language) => substr($language->url_prefix, 0, 2) === $choice['base']);
            if ($base->count() === 1) {
                return $base->sole();
            }
        }

        return null;
    }

    private function url(?string $prefix, array $slugs): string
    {
        $path = '/'.implode('/', array_filter([$prefix, ...$slugs]));

        return $prefix !== null && $slugs === [] ? $path.'/' : $path;
    }

    private function redirect(string $url): RedirectResponse
    {
        return (new RedirectResponse($url, 302))->header('Cache-Control', 'private, no-store');
    }

    private function permanentRedirect(string $url): RedirectResponse
    {
        return new RedirectResponse($url, 301);
    }
}
