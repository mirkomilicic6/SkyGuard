<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Popunjava postaju na postojećim detekcijama iz onoga što je poznato, redom po pouzdanosti
    public function up(): void
    {
        // 1) postaja leta
        DB::statement(<<<'SQL'
            UPDATE detections d
            INNER JOIN flights f ON f.id = d.flight_id
            SET d.station_id = f.station_id
            WHERE d.station_id IS NULL AND f.station_id IS NOT NULL
        SQL);

        // 2) postaja lovačke kamere
        DB::statement(<<<'SQL'
            UPDATE detections d
            INNER JOIN hunting_cameras hc ON hc.id = d.camera_id
            SET d.station_id = hc.station_id
            WHERE d.station_id IS NULL AND hc.station_id IS NOT NULL
        SQL);

        // 3) postaja korisnika koji je unio detekciju
        DB::statement(<<<'SQL'
            UPDATE detections d
            INNER JOIN users u ON u.id = d.user_id
            SET d.station_id = u.station_id
            WHERE d.station_id IS NULL AND u.station_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        // mijenjaju se samo podaci, pa nema što vraćati
    }
};
