<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            // Update enum to include 'huggingface'
            DB::statement("ALTER TABLE generations MODIFY COLUMN processing_method ENUM('basic', 'ai', 'ai_imgbb', 'huggingface') DEFAULT 'basic' COMMENT 'Processing method: basic (PHP), ai (Replicate Direct), ai_imgbb (Replicate + ImgBB), huggingface (HF AI)'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            // Revert enum to remove 'huggingface'
            DB::statement("ALTER TABLE generations MODIFY COLUMN processing_method ENUM('basic', 'ai', 'ai_imgbb') DEFAULT 'basic' COMMENT 'Processing method: basic (PHP), ai (Replicate Direct), ai_imgbb (Replicate + ImgBB)'");
        });
    }
};
