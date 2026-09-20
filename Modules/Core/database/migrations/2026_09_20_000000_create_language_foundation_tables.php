<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 35)->unique();
            $table->string('name', 100);
            $table->string('native_name', 100);
            $table->enum('text_direction', ['ltr', 'rtl']);
            $table->boolean('is_active')->default(false);
            $table->timestamp('installed_at');
            $table->timestamps();
        });

        Schema::create('language_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('base_language_id')->unique()->constrained('languages')->restrictOnDelete();
            $table->foreignId('frontend_default_language_id')->constrained('languages')->restrictOnDelete();
            $table->foreignId('backoffice_default_language_id')->constrained('languages')->restrictOnDelete();
            $table->timestamps();
        });

        $this->createSingletonTriggers();

        $now = now();
        $languageId = DB::table('languages')->insertGetId([
            'locale' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'text_direction' => 'ltr',
            'is_active' => true,
            'installed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('language_settings')->insert([
            'id' => 1,
            'base_language_id' => $languageId,
            'frontend_default_language_id' => $languageId,
            'backoffice_default_language_id' => $languageId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $this->dropSingletonTriggers();
        Schema::dropIfExists('language_settings');
        Schema::dropIfExists('languages');
    }

    private function createSingletonTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER language_settings_singleton_insert
                BEFORE INSERT ON language_settings
                WHEN NEW.id <> 1
                BEGIN
                    SELECT RAISE(ABORT, 'language_settings.id must be 1');
                END
            SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER language_settings_singleton_update
                BEFORE UPDATE OF id ON language_settings
                WHEN NEW.id <> 1
                BEGIN
                    SELECT RAISE(ABORT, 'language_settings.id must be 1');
                END
            SQL);

            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER language_settings_singleton_insert
            BEFORE INSERT ON language_settings
            FOR EACH ROW
            BEGIN
                IF NEW.id <> 1 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'language_settings.id must be 1';
                END IF;
            END
        SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER language_settings_singleton_update
            BEFORE UPDATE ON language_settings
            FOR EACH ROW
            BEGIN
                IF NEW.id <> 1 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'language_settings.id must be 1';
                END IF;
            END
        SQL);
    }

    private function dropSingletonTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS language_settings_singleton_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS language_settings_singleton_update');
    }
};
