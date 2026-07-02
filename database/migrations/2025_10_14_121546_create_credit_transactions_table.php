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
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('credit_id');
            $table->integer('amount'); // Can be negative for deductions
            $table->unsignedInteger('balance_after');
            $table->enum('type', ['purchase', 'usage', 'refund', 'bonus', 'admin_adjustment'])->default('usage');
            $table->string('source')->nullable(); // plan, wallet, referral, admin, generation
            $table->text('description')->nullable();
            $table->string('reference_type')->nullable(); // Generation, Plan, Wallet, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('created_at');
            
            $table->index(['user_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
