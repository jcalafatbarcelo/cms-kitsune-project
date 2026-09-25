<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Localization\Exceptions\CatalogValidationException;
use Modules\Core\Template\Exceptions\TemplateOperationException;
use Modules\Core\Template\Models\CmsTemplate;
use Modules\Core\Template\Services\TemplateManager;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->templateRoot = storage_path('framework/testing/templates-'.bin2hex(random_bytes(6)));
    mkdir($this->templateRoot, recursive: true);
    writeTemplate($this->templateRoot, 'Base', 'base');
    $this->templates = new TemplateManager($this->templateRoot);
    $this->app->instance(TemplateManager::class, $this->templates);
});

afterEach(function () {
    removeTemplateTree($this->templateRoot);
});

test('a clean installation registers Base and enforces the template settings singleton', function () {
    $base = DB::table('cms_templates')->where('identifier', 'base')->sole();
    $settings = DB::table('cms_template_settings')->sole();

    expect($base->directory)->toBe('Base')
        ->and($base->directory_key)->toBe('base')
        ->and((bool) $base->is_active)->toBeTrue()
        ->and($settings->default_template_id)->toBe($base->id)
        ->and(fn () => DB::table('cms_template_settings')->insert([
            'id' => 2,
            'default_template_id' => $base->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('cms_template_settings')->where('id', 1)->delete())
        ->toThrow(QueryException::class);
})->group('database-integration');

test('sync registers a valid deployed template and preserves transactional safety', function () {
    file_put_contents($this->templateRoot.DIRECTORY_SEPARATOR.'README.md', 'not a package');
    mkdir($this->templateRoot.DIRECTORY_SEPARATOR.'.metadata');
    writeTemplate($this->templateRoot, 'Acme', 'acme');

    $this->templates->sync();

    expect(CmsTemplate::query()->where('identifier', 'acme')->sole()->is_active)->toBeFalse();

    writeTemplate($this->templateRoot, 'Broken', 'broken', presentations: ['public.page.missing']);
    unlink($this->templateRoot.DIRECTORY_SEPARATOR.'Broken'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'page'.DIRECTORY_SEPARATOR.'missing.blade.php');

    expect(fn () => $this->templates->sync())
        ->toThrow(TemplateOperationException::class)
        ->and(CmsTemplate::query()->where('identifier', 'broken')->exists())->toBeFalse();
});

test('sync rejects a standard presentation without its required template UI catalog', function () {
    writeTemplate($this->templateRoot, 'Acme', 'acme');
    unlink($this->templateRoot.DIRECTORY_SEPARATOR.'Acme'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.'en.json');

    expect(fn () => $this->templates->sync())
        ->toThrow(CatalogValidationException::class)
        ->and(CmsTemplate::query()->where('identifier', 'acme')->exists())->toBeFalse();
});

test('activation and default selection reject a changed manifest identity', function () {
    writeTemplate($this->templateRoot, 'Acme', 'acme');
    $this->templates->sync();

    writeTemplate($this->templateRoot, 'Acme', 'changed');

    expect(fn () => $this->templates->activate('acme'))
        ->toThrow(TemplateOperationException::class)
        ->and(fn () => $this->templates->setDefault('acme'))
        ->toThrow(TemplateOperationException::class);
});

test('manifest validation counts multibyte names by character count', function () {
    writeTemplate($this->templateRoot, 'Acme', 'acme');
    $manifest = $this->templateRoot.DIRECTORY_SEPARATOR.'Acme'.DIRECTORY_SEPARATOR.'template.json';
    $data = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
    $data['name'] = str_repeat("\xC3\xA1", 100);
    file_put_contents($manifest, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

    $this->templates->sync();

    expect(CmsTemplate::query()->where('identifier', 'acme')->sole()->name)->toBe($data['name']);
});

test('manifest validation rejects symlinked view components', function () {
    writeTemplate($this->templateRoot, 'Acme', 'acme');

    $views = $this->templateRoot.DIRECTORY_SEPARATOR.'Acme'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'views';
    $target = $this->templateRoot.DIRECTORY_SEPARATOR.'outside';
    mkdir($target);
    rename($views.DIRECTORY_SEPARATOR.'public', $target.DIRECTORY_SEPARATOR.'public');

    symlink($target.DIRECTORY_SEPARATOR.'public', $views.DIRECTORY_SEPARATOR.'public');

    expect(fn () => $this->templates->sync())->toThrow(TemplateOperationException::class);
})->skip(PHP_OS_FAMILY === 'Windows', 'Symbolic link tests run in CI on Linux.');

test('template transitions preserve active and default invariants', function () {
    writeTemplate($this->templateRoot, 'Acme', 'acme');
    $this->templates->sync();

    expect(fn () => $this->templates->setDefault('acme'))->toThrow(TemplateOperationException::class);

    $this->templates->activate('acme');
    $this->templates->setDefault('acme');

    expect(fn () => $this->templates->disable('acme'))->toThrow(TemplateOperationException::class);

    $this->templates->setDefault('base');
    $this->templates->disable('acme');

    expect(CmsTemplate::query()->where('identifier', 'acme')->sole()->is_active)->toBeFalse()
        ->and(fn () => $this->templates->disable('base'))->toThrow(TemplateOperationException::class);
});

test('template commands expose deterministic operations without manifest content', function () {
    writeTemplate($this->templateRoot, 'Acme', 'acme');

    $this->artisan('cms:template:sync')
        ->expectsOutputToContain('synchronized')
        ->assertSuccessful();
    $this->artisan('cms:template:activate', ['identifier' => 'acme'])
        ->expectsOutputToContain('acme')
        ->assertSuccessful();
    $this->artisan('cms:template:set-default', ['identifier' => 'acme'])
        ->expectsOutputToContain('default')
        ->assertSuccessful();
    $this->artisan('cms:template:disable', ['identifier' => 'acme'])
        ->expectsOutputToContain('cannot be disabled')
        ->assertFailed();
});

function writeTemplate(string $root, string $directory, string $identifier, array $presentations = ['public.page.standard']): void
{
    $path = $root.DIRECTORY_SEPARATOR.$directory;
    $views = $path.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'page';
    is_dir($views) || mkdir($views, recursive: true);

    file_put_contents($path.DIRECTORY_SEPARATOR.'template.json', json_encode([
        'schema_version' => 1,
        'identifier' => $identifier,
        'name' => ucfirst($identifier),
        'presentations' => $presentations,
    ], JSON_THROW_ON_ERROR));

    $catalog = $path.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'lang';
    is_dir($catalog) || mkdir($catalog, recursive: true);
    file_put_contents($catalog.DIRECTORY_SEPARATOR.'en.json', json_encode([
        $identifier.'::page.home.under-construction.heading' => 'Under construction',
        $identifier.'::page.home.under-construction.message' => 'This site is being prepared.',
    ], JSON_THROW_ON_ERROR));

    foreach ($presentations as $presentation) {
        $view = $path.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.str_replace('.', DIRECTORY_SEPARATOR, $presentation).'.blade.php';
        $parent = dirname($view);
        is_dir($parent) || mkdir($parent, recursive: true);
        file_put_contents($view, '<main></main>');
    }
}

function removeTemplateTree(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $entry) {
        $entry->isLink() || ! $entry->isDir()
            ? unlink($entry->getPathname())
            : rmdir($entry->getPathname());
    }

    rmdir($path);
}
