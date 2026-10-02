<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ml-service zone-membership query (get_flight_zone_membership in
     * main.py) narrows gpx_points by a lat/lon bounding box per DBSCAN zone
     * before computing the precise distance — without an index on these
     * columns that's still a full table scan per zone (37 zones × 1.7M+
     * rows was timing out in production). A composite index lets MySQL use
     * an index range scan on the latitude bound instead.
     */
    public function up(): void
    {
        Schema::table('gpx_points', function (Blueprint $table) {
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('gpx_points', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
        });
    }
};
