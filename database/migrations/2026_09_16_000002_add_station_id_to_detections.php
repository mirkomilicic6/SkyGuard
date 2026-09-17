<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->after('flight_id')
                ->constrained('border_police_stations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('station_id');
        });
    }
};
