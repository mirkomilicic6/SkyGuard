<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('border_police_stations', function (Blueprint $table) {
            // ucrtana granica postaje (popis točaka [lat, lon]);
            // dok nije ucrtana, koristi se krug od 10 km oko postaje
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
