<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Template\Services\TemplateManifestValidator;

return new class extends Migration
{
    public function up(): void
    {
        $snapshot = (new TemplateManifestValidator(base_path('Templates')))->inspect('Base');
        $now = now();

        Schema::create('cms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('directory', 100);
            $table->string('directory_key', 100)->unique();
            $table->string('identifier', 100)->unique();
            $table->string('name', 100);
            $table->char('manifest_hash', 64);
            $table->boolean('is_active')->default(false);
            $table->timestamp('registered_at');
            $table->timestamps();
        });

        Schema::create('cms_template_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('default_template_id')->constrained('cms_templates')->restrictOnDelete();
            $table->timestamps();
        });

        $this->createSingletonTriggers();
        $templateId = DB::table('cms_templates')->insertGetId([
            ...$snapshot,
            'is_active' => true,
            'registered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('cms_template_settings')->insert([
            'id' => 1,
            'default_template_id' => $templateId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS cms_template_settings_singleton_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS cms_template_settings_singleton_update');
        DB::unprepared('DROP TRIGGER IF EXISTS cms_template_settings_singleton_delete');
        Schema::dropIfExists('cms_template_settings');
        Schema::dropIfExists('cms_templates');
    }

    private function createSingletonTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER cms_template_settings_singleton_insert BEFORE INSERT ON cms_template_settings WHEN NEW.id <> 1 BEGIN SELECT RAISE(ABORT, 'cms_template_settings.id must be 1'); END");
            DB::unprepared("CREATE TRIGGER cms_template_settings_singleton_update BEFORE UPDATE OF id ON cms_template_settings WHEN NEW.id <> 1 BEGIN SELECT RAISE(ABORT, 'cms_template_settings.id must be 1'); END");
            DB::unprepared("CREATE TRIGGER cms_template_settings_singleton_delete BEFORE DELETE ON cms_template_settings BEGIN SELECT RAISE(ABORT, 'cms_template_settings cannot be deleted'); END");

            return;
        }

        DB::unprepared("CREATE TRIGGER cms_template_settings_singleton_insert BEFORE INSERT ON cms_template_settings FOR EACH ROW BEGIN IF NEW.id <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'cms_template_settings.id must be 1'; END IF; END");
        DB::unprepared("CREATE TRIGGER cms_template_settings_singleton_update BEFORE UPDATE ON cms_template_settings FOR EACH ROW BEGIN IF NEW.id <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'cms_template_settings.id must be 1'; END IF; END");
        DB::unprepared("CREATE TRIGGER cms_template_settings_singleton_delete BEFORE DELETE ON cms_template_settings FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'cms_template_settings cannot be deleted'; END");
    }
};
