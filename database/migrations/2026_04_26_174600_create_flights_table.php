<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('flight_date');
            $table->integer('duration_minutes')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->decimal('max_altitude_m', 8, 2)->nullable();
            $table->decimal('avg_speed_kmh', 8, 2)->nullable();
            $table->string('gpx_file_path')->nullable();
            $table->string('location')->nullable();
            $table->text('purpose')->nullable();
            $table->enum('status', ['completed', 'aborted'])->default('completed');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
