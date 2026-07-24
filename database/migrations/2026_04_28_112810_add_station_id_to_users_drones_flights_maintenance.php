<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->constrained('border_police_stations')->nullOnDelete();
        });

        Schema::table('drones', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->constrained('border_police_stations')->nullOnDelete();
        });

        Schema::table('flights', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->constrained('border_police_stations')->nullOnDelete();
        });

        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->constrained('border_police_stations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->dropForeign(['station_id']);
            $table->dropColumn('station_id');
        });
        Schema::table('flights', function (Blueprint $table) {
            $table->dropForeign(['station_id']);
            $table->dropColumn('station_id');
        });
        Schema::table('drones', function (Blueprint $table) {
            $table->dropForeign(['station_id']);
            $table->dropColumn('station_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['station_id']);
            $table->dropColumn('station_id');
        });
    }
};
