<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Localization\Services\UiCatalogRepository;

return new class extends Migration
{
    public function up(): void
    {
        $catalog = (new UiCatalogRepository(base_path('Templates/Base/Resources/lang'), 'base'))->snapshot('en');
        foreach (['base::page.home.under-construction.heading', 'base::page.home.under-construction.message'] as $key) {
            if (! isset($catalog->lines[$key])) {
                throw new RuntimeException("Base UI catalog is missing [$key].");
            }
        }

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->restrictOnDelete();
            $table->boolean('uses_explicit_template')->default(false);
            $table->foreignId('explicit_template_id')->nullable()->constrained('cms_templates')->restrictOnDelete();
            $table->string('presentation_key', 100)->default('public.page.standard');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
        Schema::create('page_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->restrictOnDelete();
            $table->foreignId('language_id')->constrained('languages')->restrictOnDelete();
            $table->string('title', 255);
            $table->string('slug', 100);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->unique(['page_id', 'language_id']);
            $table->unique(['language_id', 'slug']);
        });
        Schema::create('page_language_homes', function (Blueprint $table) {
            $table->foreignId('language_id')->primary()->constrained('languages')->restrictOnDelete();
            $table->foreignId('page_translation_id')->unique()->constrained('page_translations')->restrictOnDelete();
            $table->timestamps();
        });
        $this->createTemplateSelectionTriggers();

        $language = DB::table('languages')->where('locale', 'en')->first();
        $now = now();
        $pageId = DB::table('pages')->insertGetId(['uses_explicit_template' => false, 'presentation_key' => 'public.page.standard', 'is_published' => true, 'created_at' => $now, 'updated_at' => $now]);
        $translationId = DB::table('page_translations')->insertGetId(['page_id' => $pageId, 'language_id' => $language->id, 'title' => 'Under construction', 'slug' => 'home', 'is_published' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('page_language_homes')->insert(['language_id' => $language->id, 'page_translation_id' => $translationId, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS pages_template_selection_insert');
        DB::statement('DROP TRIGGER IF EXISTS pages_template_selection_update');
        Schema::dropIfExists('page_language_homes');
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('pages');
    }

    private function createTemplateSelectionTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER pages_template_selection_insert BEFORE INSERT ON pages WHEN (NEW.uses_explicit_template = 0 AND NEW.explicit_template_id IS NOT NULL) OR (NEW.uses_explicit_template = 1 AND NEW.explicit_template_id IS NULL) BEGIN SELECT RAISE(ABORT, 'pages template selection is invalid'); END");
            DB::statement("CREATE TRIGGER pages_template_selection_update BEFORE UPDATE ON pages WHEN (NEW.uses_explicit_template = 0 AND NEW.explicit_template_id IS NOT NULL) OR (NEW.uses_explicit_template = 1 AND NEW.explicit_template_id IS NULL) BEGIN SELECT RAISE(ABORT, 'pages template selection is invalid'); END");

            return;
        }

        DB::statement("CREATE TRIGGER pages_template_selection_insert BEFORE INSERT ON pages FOR EACH ROW BEGIN IF (NEW.uses_explicit_template = 0 AND NEW.explicit_template_id IS NOT NULL) OR (NEW.uses_explicit_template = 1 AND NEW.explicit_template_id IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'pages template selection is invalid'; END IF; END");
        DB::statement("CREATE TRIGGER pages_template_selection_update BEFORE UPDATE ON pages FOR EACH ROW BEGIN IF (NEW.uses_explicit_template = 0 AND NEW.explicit_template_id IS NOT NULL) OR (NEW.uses_explicit_template = 1 AND NEW.explicit_template_id IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'pages template selection is invalid'; END IF; END");
    }
};
