<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            // Allow detections without a flight (camera/manual source)
            $table->unsignedBigInteger('flight_id')->nullable()->change();

            // Hunting camera source
            $table->foreignId('camera_id')
                ->nullable()
                ->constrained('hunting_cameras')
                ->nullOnDelete()
                ->after('flight_id');

            // How was it detected
            $table->enum('source', ['drone', 'camera', 'manual'])
                ->default('drone')
                ->after('camera_id');

            // AI analysis fields
            $table->boolean('confirmed')->nullable()->after('notes');
            $table->string('action_taken', 30)->nullable()->after('confirmed');
            $table->unsignedSmallInteger('heading_deg')->nullable()->after('action_taken');
            $table->string('weather_condition', 20)->nullable()->after('heading_deg');
            $table->unsignedTinyInteger('escalation_level')->default(0)->after('weather_condition');
        });
    }

    public function down(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            $table->dropForeign(['camera_id']);
            $table->dropColumn(['camera_id', 'source', 'confirmed', 'action_taken',
                                'heading_deg', 'weather_condition', 'escalation_level']);
            $table->unsignedBigInteger('flight_id')->nullable(false)->change();
        });
    }
};
