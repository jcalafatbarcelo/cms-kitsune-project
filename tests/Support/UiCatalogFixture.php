<?php

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;

final class UiCatalogFixture
{
    /** @var array<string, string> */
    private const CORE_TRANSLATIONS = [
        'core::language.installed' => 'Idioma :locale instalado.',
    ];

    /** @var array<string, string> */
    private const BASE_TRANSLATIONS = [
        'base::admin.login.title' => 'Acceso de administración',
        'base::admin.menus.add-item' => 'Añadir elemento',
        'base::admin.menus.created' => 'Menú creado.',
        'base::admin.pages.published-badge' => 'Publicada',
        'base::admin.pages.unpublished-badge' => 'Despublicada',
    ];

    public static function core(): string
    {
        $directory = self::directory();

        self::writeCatalogs(
            $directory,
            base_path('Modules/Core/Resources/lang'),
            self::CORE_TRANSLATIONS,
        );

        return $directory;
    }

    public static function baseTemplateRoot(): string
    {
        $root = self::directory();
        $catalog = $root.DIRECTORY_SEPARATOR.'Base'
            .DIRECTORY_SEPARATOR.'Resources'
            .DIRECTORY_SEPARATOR.'lang';

        mkdir($catalog, recursive: true);
        self::writeCatalogs(
            $catalog,
            base_path('Templates/Base/Resources/lang'),
            self::BASE_TRANSLATIONS,
        );

        return $root;
    }

    public static function remove(string $path): void
    {
        File::deleteDirectory($path);
    }

    private static function directory(): string
    {
        $directory = storage_path('framework/testing/ui-catalog-'.bin2hex(random_bytes(6)));
        mkdir($directory, recursive: true);

        return $directory;
    }

    /** @param array<string, string> $translations */
    private static function writeCatalogs(string $destination, string $source, array $translations): void
    {
        $sourcePath = $source.DIRECTORY_SEPARATOR.'en.json';
        $base = json_decode(
            (string) file_get_contents($sourcePath),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (! is_array($base) || array_is_list($base)) {
            throw new RuntimeException("Base UI catalog [$sourcePath] is not a JSON object.");
        }

        copy($sourcePath, $destination.DIRECTORY_SEPARATOR.'en.json');

        $localized = $base;

        foreach ($translations as $key => $value) {
            if (! array_key_exists($key, $base)) {
                throw new RuntimeException("Fixture UI key [$key] does not exist in [$sourcePath].");
            }

            $localized[$key] = $value;
        }

        file_put_contents(
            $destination.DIRECTORY_SEPARATOR.'es_ES.json',
            json_encode(
                $localized,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
            )."\n",
        );
    }
}
