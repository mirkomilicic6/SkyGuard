<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            $table->dropColumn(['confirmed', 'action_taken', 'escalation_level']);
        });
    }

    public function down(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            $table->boolean('confirmed')->nullable()->after('notes');
            $table->string('action_taken', 30)->nullable()->after('confirmed');
            $table->unsignedTinyInteger('escalation_level')->default(0)->after('weather_condition');
        });
    }
};
