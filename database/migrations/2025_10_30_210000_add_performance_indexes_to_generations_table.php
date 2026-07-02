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
            // Add indexes for performance optimization
            $table->index('external_id', 'generations_external_id_index');
            $table->index('processing_method', 'generations_processing_method_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropIndex('generations_external_id_index');
            $table->dropIndex('generations_processing_method_index');
        });
    }
};