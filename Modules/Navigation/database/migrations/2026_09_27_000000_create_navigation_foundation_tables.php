<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 100)->unique();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->restrictOnDelete();
            $table->foreignId('language_id')->constrained('languages')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->restrictOnDelete();
            $table->foreignId('page_id')->constrained('pages')->restrictOnDelete();
            $table->string('label', 255);
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->index(['menu_id', 'language_id', 'parent_id', 'position']);
        });
        Schema::create('menu_item_homes', function (Blueprint $table) {
            $table->foreignId('language_id')->primary()->constrained('languages')->restrictOnDelete();
            $table->foreignId('menu_item_id')->unique()->constrained('menu_items')->restrictOnDelete();
            $table->timestamps();
        });
        $this->createPositionChecks();

        $language = DB::table('languages')->where('locale', 'en')->first();
        $translation = DB::table('page_translations')->where('language_id', $language->id)->where('slug', 'home')->first();
        if ($translation !== null) {
            $now = now();
            $menuId = DB::table('menus')->insertGetId([
                'identifier' => 'home',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $itemId = DB::table('menu_items')->insertGetId([
                'menu_id' => $menuId,
                'language_id' => $language->id,
                'parent_id' => null,
                'page_id' => $translation->page_id,
                'label' => 'Home',
                'position' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('menu_item_homes')->insert([
                'language_id' => $language->id,
                'menu_item_id' => $itemId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS menu_items_position_insert');
        DB::statement('DROP TRIGGER IF EXISTS menu_items_position_update');
        Schema::dropIfExists('menu_item_homes');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }

    private function createPositionChecks(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER menu_items_position_insert BEFORE INSERT ON menu_items WHEN NEW.position < 1 BEGIN SELECT RAISE(ABORT, 'menu item position must be at least 1'); END");
            DB::statement("CREATE TRIGGER menu_items_position_update BEFORE UPDATE OF position ON menu_items WHEN NEW.position < 1 BEGIN SELECT RAISE(ABORT, 'menu item position must be at least 1'); END");

            return;
        }

        DB::statement("CREATE TRIGGER menu_items_position_insert BEFORE INSERT ON menu_items FOR EACH ROW BEGIN IF NEW.position < 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'menu item position must be at least 1'; END IF; END");
        DB::statement("CREATE TRIGGER menu_items_position_update BEFORE UPDATE ON menu_items FOR EACH ROW BEGIN IF NEW.position < 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'menu item position must be at least 1'; END IF; END");
    }
};
