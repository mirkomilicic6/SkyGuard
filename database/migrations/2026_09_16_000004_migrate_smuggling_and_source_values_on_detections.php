<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Prije promjene popisa vrijednosti (enum) stare vrijednosti se preslikavaju u nove,
    // jer MySQL ne prihvaća vrijednosti izvan novog popisa
    public function up(): void
    {
        DB::statement("ALTER TABLE detections MODIFY source ENUM('drone','camera','manual','trail_camera','ground_observation','other') NOT NULL DEFAULT 'drone'");

        DB::table('detections')->where('type', 'smuggling')->update(['type' => 'other']);
        DB::table('detections')->where('source', 'camera')->update(['source' => 'trail_camera']);
        DB::table('detections')->where('source', 'manual')->update(['source' => 'ground_observation']);
    }

    public function down(): void
    {
        // stare vrijednosti se ne mogu vratiti, pa ovdje nema ništa
    }
};
