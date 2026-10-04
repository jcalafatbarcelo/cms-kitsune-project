<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $idType = DB::getDriverName() === 'sqlite' ? 'INTEGER' : 'BIGINT UNSIGNED';

        DB::statement("CREATE TABLE admin_audit_events (
            id {$idType} PRIMARY KEY ".(DB::getDriverName() === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT').",
            occurred_at DATETIME NOT NULL,
            operation VARCHAR(100) NOT NULL,
            entity_type VARCHAR(100) NOT NULL,
            entity_id {$idType} NOT NULL,
            actor_type VARCHAR(20) NOT NULL,
            actor_id {$idType} NULL,
            actor_label VARCHAR(100) NOT NULL,
            origin VARCHAR(20) NOT NULL,
            before_state TEXT NULL,
            after_state TEXT NULL,
            schema_version INTEGER NOT NULL
        )");
        DB::statement('CREATE INDEX admin_audit_events_occurred_at_index ON admin_audit_events (occurred_at)');
        DB::statement('CREATE INDEX admin_audit_events_entity_index ON admin_audit_events (entity_type, entity_id)');
        DB::statement('CREATE INDEX admin_audit_events_operation_index ON admin_audit_events (operation)');

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER admin_audit_events_no_update BEFORE UPDATE ON admin_audit_events BEGIN SELECT RAISE(ABORT, 'Admin audit events are immutable'); END");
            DB::unprepared("CREATE TRIGGER admin_audit_events_no_delete BEFORE DELETE ON admin_audit_events BEGIN SELECT RAISE(ABORT, 'Admin audit events are immutable'); END");

            return;
        }

        DB::unprepared("CREATE TRIGGER admin_audit_events_no_update BEFORE UPDATE ON admin_audit_events FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Admin audit events are immutable'; END");
        DB::unprepared("CREATE TRIGGER admin_audit_events_no_delete BEFORE DELETE ON admin_audit_events FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Admin audit events are immutable'; END");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS admin_audit_events_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS admin_audit_events_no_delete');
        Schema::dropIfExists('admin_audit_events');
    }
};
