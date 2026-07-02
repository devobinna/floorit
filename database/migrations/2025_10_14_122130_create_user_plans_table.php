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
        Schema::create('user_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('plan_id');
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->enum('status', ['active', 'expired', 'cancelled', 'suspended'])->default('active');
            $table->string('stripe_subscription_id')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('expires_at');
            $table->unique(['user_id', 'plan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_plans');
    }
};
