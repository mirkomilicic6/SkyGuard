<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE maintenance_logs MODIFY COLUMN status ENUM('pending_review','open','in_progress','resolved') NOT NULL DEFAULT 'pending_review'");
    }

    public function down(): void
    {
        DB::statement("UPDATE maintenance_logs SET status='open' WHERE status='pending_review'");
        DB::statement("ALTER TABLE maintenance_logs MODIFY COLUMN status ENUM('open','in_progress','resolved') NOT NULL DEFAULT 'open'");
    }
};
