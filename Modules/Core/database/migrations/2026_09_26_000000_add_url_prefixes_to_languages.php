<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefixes = DB::table('languages')->orderBy('id')->get(['id', 'locale'])->mapWithKeys(
            fn (object $language) => [$language->id => $this->prefixFor($language->locale)],
        );

        if ($prefixes->count() !== $prefixes->unique()->count()) {
            throw new RuntimeException('Cannot derive unique URL prefixes from installed locales.');
        }

        Schema::table('languages', function (Blueprint $table) {
            $table->string('url_prefix', 5)->nullable()->unique()->after('locale');
            $table->boolean('is_url_general')->default(false)->after('is_active');
        });

        foreach ($prefixes as $id => $prefix) {
            DB::table('languages')->where('id', $id)->update(['url_prefix' => $prefix]);
        }

        Schema::table('languages', function (Blueprint $table) {
            $table->string('url_prefix', 5)->nullable(false)->change();
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER languages_text_direction_insert BEFORE INSERT ON languages WHEN NEW.text_direction NOT IN ('ltr', 'rtl') BEGIN SELECT RAISE(ABORT, 'languages.text_direction is invalid'); END");
            DB::unprepared("CREATE TRIGGER languages_text_direction_update BEFORE UPDATE OF text_direction ON languages WHEN NEW.text_direction NOT IN ('ltr', 'rtl') BEGIN SELECT RAISE(ABORT, 'languages.text_direction is invalid'); END");
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS languages_text_direction_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS languages_text_direction_update');
        Schema::table('languages', function (Blueprint $table) {
            $table->dropUnique(['url_prefix']);
            $table->dropColumn(['url_prefix', 'is_url_general']);
        });
    }

    private function prefixFor(string $locale): string
    {
        if (preg_match('/^([a-z]{2})(?:_([A-Z]{2}))?$/D', $locale, $matches) !== 1) {
            throw new RuntimeException("Locale [$locale] cannot be represented as a URL prefix.");
        }

        return isset($matches[2]) ? $matches[1].'-'.strtolower($matches[2]) : $matches[1];
    }
};
