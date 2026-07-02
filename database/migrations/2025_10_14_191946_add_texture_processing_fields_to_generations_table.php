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
        Schema::table('generations', function (Blueprint $table) {
            $table->foreignId('texture_id')->nullable()->after('flooring_type')->constrained('textures')->onDelete('set null');
            $table->json('floor_polygon')->nullable()->after('input_data'); // User-drawn floor area coordinates
            $table->json('homography_matrix')->nullable()->after('floor_polygon'); // Cached perspective transform
            $table->string('preview_path')->nullable()->after('generated_image'); // assets/generations/previews/uuid.jpg
            $table->string('hd_path')->nullable()->after('preview_path'); // assets/generations/hd/uuid.jpg
        });
    }

    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropForeign(['texture_id']);
            $table->dropColumn(['texture_id', 'floor_polygon', 'homography_matrix', 'preview_path', 'hd_path']);
        });
    }
};
