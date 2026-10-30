<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Indeks na koordinatama GPS točaka, da upit o letovima kroz žarišta ne pretražuje cijelu tablicu
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
