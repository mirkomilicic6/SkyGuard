<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renames go through raw ALTER TABLE (not Schema::renameColumn) so this
     * doesn't depend on doctrine/dbal being installed, and to redefine the
     * enum value lists in the same statement as the rename.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE detections CHANGE type detection_type ENUM('person','group','vehicle','other') NOT NULL");
        DB::statement("ALTER TABLE detections CHANGE count entity_count SMALLINT UNSIGNED NOT NULL DEFAULT 1");
        DB::statement("ALTER TABLE detections CHANGE notes note TEXT NULL");
        DB::statement("ALTER TABLE detections CHANGE user_id created_by BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE detections MODIFY source ENUM('drone','trail_camera','ground_observation','other') NOT NULL DEFAULT 'drone'");

        Schema::table('detections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('camera_id');
            $table->dropColumn(['heading_deg', 'weather_condition']);
        });
    }

    public function down(): void
    {
        Schema::table('detections', function (Blueprint $table) {
            $table->foreignId('camera_id')->nullable()->after('flight_id')
                ->constrained('hunting_cameras')->nullOnDelete();
            $table->unsignedSmallInteger('heading_deg')->nullable();
            $table->string('weather_condition', 20)->nullable();
        });

        DB::statement("ALTER TABLE detections MODIFY source ENUM('drone','camera','manual') NOT NULL DEFAULT 'drone'");
        DB::statement("ALTER TABLE detections CHANGE created_by user_id BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE detections CHANGE note notes TEXT NULL");
        DB::statement("ALTER TABLE detections CHANGE entity_count count SMALLINT UNSIGNED NOT NULL DEFAULT 1");
        DB::statement("ALTER TABLE detections CHANGE detection_type type ENUM('person','group','vehicle','smuggling','other') NOT NULL");

        // Note: this does not restore the original camera_id/source values
        // remapped by the preceding data migration (trail_camera→camera,
        // ground_observation→manual, other←smuggling) — that mapping isn't
        // reversible. The FK constraint on user_id (added back above via
        // constrained()) is intentionally omitted here since the original
        // table defined it inline at creation, not via a separate migration.
    }
};
