<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $idType = DB::getDriverName() === 'sqlite' ? 'INTEGER' : 'BIGINT UNSIGNED';
        DB::statement("CREATE TABLE administration_access (
            singleton INTEGER PRIMARY KEY CHECK (singleton = 1),
            super_admin_user_id {$idType} NULL UNIQUE,
            FOREIGN KEY (super_admin_user_id) REFERENCES users(id) ON DELETE RESTRICT
        )");
        DB::table('administration_access')->insert(['singleton' => 1]);

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER administration_access_singleton_insert BEFORE INSERT ON administration_access WHEN NEW.singleton <> 1 BEGIN SELECT RAISE(ABORT, 'Invalid administration singleton'); END");
            DB::unprepared("CREATE TRIGGER administration_access_singleton_update BEFORE UPDATE OF singleton ON administration_access WHEN NEW.singleton <> 1 BEGIN SELECT RAISE(ABORT, 'Invalid administration singleton'); END");
            DB::unprepared("CREATE TRIGGER administration_access_singleton_delete BEFORE DELETE ON administration_access BEGIN SELECT RAISE(ABORT, 'Administration singleton cannot be deleted'); END");
        } else {
            DB::unprepared("CREATE TRIGGER administration_access_singleton_insert BEFORE INSERT ON administration_access FOR EACH ROW BEGIN IF NEW.singleton <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid administration singleton'; END IF; END");
            DB::unprepared("CREATE TRIGGER administration_access_singleton_update BEFORE UPDATE ON administration_access FOR EACH ROW BEGIN IF NEW.singleton <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid administration singleton'; END IF; END");
            DB::unprepared("CREATE TRIGGER administration_access_singleton_delete BEFORE DELETE ON administration_access FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Administration singleton cannot be deleted'; END");
        }
    }

    public function down(): void
    {
        foreach (['insert', 'update', 'delete'] as $operation) {
            DB::unprepared('DROP TRIGGER IF EXISTS administration_access_singleton_'.$operation);
        }
        Schema::dropIfExists('administration_access');
    }
};
