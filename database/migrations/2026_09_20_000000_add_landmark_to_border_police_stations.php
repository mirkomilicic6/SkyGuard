<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('border_police_stations', function (Blueprint $table) {
            // neobavezna napomena o prirodnoj oznaci u blizini (npr. "uz rijeku Dunav"),
            // asistent je dodaje opisu lokacije, a upisuje je korisnik
            $table->string('landmark')->nullable()->after('boundary');
        });
    }

    public function down(): void
    {
        Schema::table('border_police_stations', function (Blueprint $table) {
            $table->dropColumn('landmark');
        });
    }
};
