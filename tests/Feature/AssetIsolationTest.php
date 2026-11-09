<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Modules\Pages\Services\PageManager;

uses(RefreshDatabase::class);

function swapViteToHotFile(): string
{
    $hot = tempnam(sys_get_temp_dir(), 'kitsune-vite-hot-');
    file_put_contents($hot, 'http://localhost:5173');

    $vite = new Vite;
    $vite->useHotFile($hot);
    test()->swap(Vite::class, $vite);
    Illuminate\Support\Facades\Vite::clearResolvedInstance();

    return $hot;
}

test('login renders the administrative stylesheet and no administrative scripts', function () {
    $hot = swapViteToHotFile();

    try {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('http://localhost:5173/resources/css/admin.css', false)
            ->assertDontSee('page-edit', false)
            ->assertDontSee('menu-tabs', false)
            ->assertDontSee('tinymce', false);
    } finally {
        @unlink($hot);
    }
});

test('the public presentation renders only its stylesheet and hides the internal name', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'asset-check', 'Public heading', name: 'Secret Internal');
    $pages->publish($page->page_id, 'en');
    $hot = swapViteToHotFile();

    try {
        $this->get('/asset-check')
            ->assertOk()
            ->assertSee('http://localhost:5173/resources/css/public.css', false)
            ->assertSee('Public heading')
            ->assertDontSee('Secret Internal')
            ->assertDontSee('admin.css', false)
            ->assertDontSee('page-edit', false)
            ->assertDontSee('tinymce', false)
            ->assertDontSee('menu-tabs', false);
    } finally {
        @unlink($hot);
    }
});
