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
            $table->enum('processing_method', ['basic', 'ai', 'ai_imgbb'])->default('basic')->after('status');
            $table->decimal('ai_cost', 8, 4)->nullable()->after('processing_method')->comment('Cost in USD for AI processing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropColumn(['processing_method', 'ai_cost']);
        });
    }
};
