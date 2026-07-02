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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('billing_period', ['monthly', 'yearly', 'lifetime'])->default('monthly');
            $table->unsignedInteger('credits_per_month')->default(0);
            $table->unsignedInteger('max_generations_per_day')->nullable();
            $table->json('features')->nullable();
            $table->boolean('api_access')->default(false);
            $table->boolean('priority_processing')->default(false);
            $table->unsignedInteger('api_rate_limit')->default(10); // requests per minute
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['is_active', 'sort_order']);
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
