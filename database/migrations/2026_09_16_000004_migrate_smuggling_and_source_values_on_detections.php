<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data-only migration, must run before the enum columns are redefined —
     * MySQL enums reject values outside the new list, so existing rows have
     * to be remapped first. The `source` column is temporarily widened to
     * accept both the old and new value lists so the UPDATE below doesn't
     * get truncated; the following migration narrows it to the final list.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE detections MODIFY source ENUM('drone','camera','manual','trail_camera','ground_observation','other') NOT NULL DEFAULT 'drone'");

        DB::table('detections')->where('type', 'smuggling')->update(['type' => 'other']);
        DB::table('detections')->where('source', 'camera')->update(['source' => 'trail_camera']);
        DB::table('detections')->where('source', 'manual')->update(['source' => 'ground_observation']);
    }

    public function down(): void
    {
        // Original type/source values aren't recoverable (the mapping isn't
        // reversible — e.g. 'other' could have been 'smuggling' or genuinely
        // 'other'). Nothing to do here; the column-level down() in the
        // migration that redefines the enums restores the old value lists.
    }
};
