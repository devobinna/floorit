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
        Schema::create('textures', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Oak Light"
            $table->string('slug')->unique(); // "oak-light"
            $table->enum('category', [
                'hardwood', 
                'tile', 
                'carpet', 
                'vinyl', 
                'laminate', 
                'marble', 
                'concrete',
                'stone',
                'bamboo',
                'other'
            ]); 
            $table->string('file_path'); // assets/textures/hardwood/oak-light.jpg
            $table->string('thumbnail_path')->nullable(); // assets/textures/hardwood/thumbnails/oak-light.jpg
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // color_tone, style, finish_type, etc.
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['category', 'is_active']);
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('textures');
    }
};
