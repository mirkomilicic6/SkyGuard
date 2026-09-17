<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data-only migration: fill station_id on existing detections from
     * whatever it can be inferred from, in order of confidence. Rows that
     * still can't be resolved (bare manual entries with no flight/camera and
     * created by a super admin) are left null — the same "null = global"
     * convention already used on users/drones/flights.
     */
    public function up(): void
    {
        // 1) From the linked flight's station.
        DB::statement(<<<'SQL'
            UPDATE detections d
            INNER JOIN flights f ON f.id = d.flight_id
            SET d.station_id = f.station_id
            WHERE d.station_id IS NULL AND f.station_id IS NOT NULL
        SQL);

        // 2) From the linked hunting camera's station (camera_id still exists at this point).
        DB::statement(<<<'SQL'
            UPDATE detections d
            INNER JOIN hunting_cameras hc ON hc.id = d.camera_id
            SET d.station_id = hc.station_id
            WHERE d.station_id IS NULL AND hc.station_id IS NOT NULL
        SQL);

        // 3) Best-effort fallback: the creating user's own station.
        DB::statement(<<<'SQL'
            UPDATE detections d
            INNER JOIN users u ON u.id = d.user_id
            SET d.station_id = u.station_id
            WHERE d.station_id IS NULL AND u.station_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        // Data-only migration — nothing structural to reverse. Leaving the
        // backfilled station_id values in place on rollback is harmless.
    }
};
