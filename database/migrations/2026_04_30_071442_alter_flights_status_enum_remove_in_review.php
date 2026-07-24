<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE flights SET status = 'completed' WHERE status = 'in_review'");
        DB::statement("ALTER TABLE flights MODIFY COLUMN status ENUM('completed','aborted') NOT NULL DEFAULT 'completed'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE flights MODIFY COLUMN status ENUM('completed','aborted','in_review') NOT NULL DEFAULT 'completed'");
    }
};
