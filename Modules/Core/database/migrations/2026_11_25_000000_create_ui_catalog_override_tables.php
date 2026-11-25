<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ui_catalog_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 35);
            $table->string('key', 191);
            $table->text('value');
            $table->string('created_by', 191);
            $table->string('updated_by', 191);
            $table->timestamps();
            $table->unique(['locale', 'key']);
            $table->index('locale');
            $table->foreign('locale')->references('locale')->on('languages')->restrictOnDelete();
        });

        Schema::create('ui_catalog_override_state', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('version')->default(1);
            $table->string('updated_by', 191);
            $table->timestamps();
        });

        $this->createStateSingletonTriggers();

        $now = now();
        DB::table('ui_catalog_override_state')->insert([
            'id' => 1,
            'enabled' => true,
            'version' => 1,
            'updated_by' => 'system',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS ui_catalog_override_state_singleton_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS ui_catalog_override_state_singleton_update');
        DB::unprepared('DROP TRIGGER IF EXISTS ui_catalog_override_state_singleton_delete');
        Schema::dropIfExists('ui_catalog_override_state');
        Schema::dropIfExists('ui_catalog_overrides');
    }

    private function createStateSingletonTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER ui_catalog_override_state_singleton_insert BEFORE INSERT ON ui_catalog_override_state WHEN NEW.id <> 1 BEGIN SELECT RAISE(ABORT, 'ui_catalog_override_state.id must be 1'); END");
            DB::unprepared("CREATE TRIGGER ui_catalog_override_state_singleton_update BEFORE UPDATE OF id ON ui_catalog_override_state WHEN NEW.id <> 1 BEGIN SELECT RAISE(ABORT, 'ui_catalog_override_state.id must be 1'); END");
            DB::unprepared("CREATE TRIGGER ui_catalog_override_state_singleton_delete BEFORE DELETE ON ui_catalog_override_state BEGIN SELECT RAISE(ABORT, 'ui_catalog_override_state cannot be deleted'); END");

            return;
        }

        DB::unprepared("CREATE TRIGGER ui_catalog_override_state_singleton_insert BEFORE INSERT ON ui_catalog_override_state FOR EACH ROW BEGIN IF NEW.id <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ui_catalog_override_state.id must be 1'; END IF; END");
        DB::unprepared("CREATE TRIGGER ui_catalog_override_state_singleton_update BEFORE UPDATE ON ui_catalog_override_state FOR EACH ROW BEGIN IF NEW.id <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ui_catalog_override_state.id must be 1'; END IF; END");
        DB::unprepared("CREATE TRIGGER ui_catalog_override_state_singleton_delete BEFORE DELETE ON ui_catalog_override_state FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ui_catalog_override_state cannot be deleted'; END");
    }
};
