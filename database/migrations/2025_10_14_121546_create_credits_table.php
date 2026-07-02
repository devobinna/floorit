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
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedInteger('balance')->default(0);
            $table->unsignedInteger('reserved')->default(0); // Credits reserved for processing jobs
            $table->unsignedInteger('total_earned')->default(0);
            $table->unsignedInteger('total_spent')->default(0);
            $table->timestamp('updated_at');
            
            $table->index('user_id');
            $table->index('balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
