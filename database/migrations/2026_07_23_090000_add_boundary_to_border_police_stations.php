<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('border_police_stations', function (Blueprint $table) {
            // Hand-drawn polygon (array of [lat, lon] pairs) marking the station's
            // actual territory. Null until an admin/viewer for that station draws
            // one on the map — camera placement falls back to the 10km radius
            // circle until then.
            $table->json('boundary')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('border_police_stations', function (Blueprint $table) {
            $table->dropColumn('boundary');
        });
    }
};
