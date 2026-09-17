<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('border_police_stations', function (Blueprint $table) {
            // Optional, manually-entered note about a nearby natural landmark
            // (e.g. "uz rijeku Dunav") — appended to the AI chat assistant's
            // location descriptions when present. Never inferred/guessed by
            // the app; left null until someone with local knowledge fills it in.
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
