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
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->uuid('uuid')->unique();
            $table->string('title')->nullable();
            $table->enum('room_type', ['living_room', 'bedroom', 'kitchen', 'bathroom', 'hallway', 'office', 'dining_room', 'basement', 'other'])->nullable();
            $table->enum('flooring_type', ['hardwood', 'tile', 'carpet', 'laminate', 'vinyl', 'stone', 'concrete', 'bamboo'])->nullable();
            $table->string('style', 100)->nullable();
            $table->json('input_data')->nullable(); // Store all generation parameters
            $table->string('original_image')->nullable();
            $table->string('generated_image')->nullable();
            $table->unsignedInteger('credits_used')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('processing_time')->nullable(); // milliseconds
            $table->json('metadata')->nullable(); // Additional generation data
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'status']);
            $table->index('uuid');
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
