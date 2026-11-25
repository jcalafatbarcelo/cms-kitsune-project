<?php

namespace Modules\Core\Localization\Services;

use Modules\Core\Localization\Exceptions\CatalogValidationException;

final class UiCatalogValueValidator
{
    public const MAX_VALUE_BYTES = 16_384;

    private const BIDI_OVERRIDES = '/[\x{202A}-\x{202E}]/u';

    public static function assertValue(string $key, string $value): void
    {
        if ($value === ''
            || strlen($value) > self::MAX_VALUE_BYTES
            || ! mb_check_encoding($value, 'UTF-8')
            || str_contains($value, '<')
            || str_contains($value, '>')
            || self::containsUnsafeCharacters($value)) {
            throw new CatalogValidationException("UI catalog value [$key] is invalid.");
        }

        self::assertPluralization($key, $value);
    }

    public static function assertCompatible(string $key, string $baseValue, string $value): void
    {
        if (self::placeholders($baseValue) !== self::placeholders($value)) {
            throw new CatalogValidationException("UI catalog placeholders differ for [$key].");
        }

        if (str_contains($baseValue, '|') !== str_contains($value, '|')) {
            throw new CatalogValidationException("UI catalog pluralization differs for [$key].");
        }
    }

    public static function containsUnsafeCharacters(string $value): bool
    {
        return preg_match('/\p{Cc}/u', $value) === 1
            || preg_match(self::BIDI_OVERRIDES, $value) === 1;
    }

    /** @return list<string> */
    public static function placeholders(string $value): array
    {
        preg_match_all('/:([A-Za-z][A-Za-z0-9_]*)/', $value, $matches);
        $placeholders = array_values(array_unique(array_map('strtolower', $matches[1])));
        sort($placeholders);

        return $placeholders;
    }

    private static function assertPluralization(string $key, string $value): void
    {
        if (! str_contains($value, '|')) {
            return;
        }

        if (! in_array('count', self::placeholders($value), true)) {
            throw new CatalogValidationException("Pluralized UI catalog value [$key] requires :count.");
        }

        foreach (explode('|', $value) as $segment) {
            $segment = trim($segment);

            if ($segment === '' || (($segment[0] === '{' || $segment[0] === '[')
                && ! preg_match('/^[{[][-?\d*,.]+[}\]]\s*.+$/sD', $segment))) {
                throw new CatalogValidationException("UI catalog pluralization is invalid for [$key].");
            }
        }
    }
}
