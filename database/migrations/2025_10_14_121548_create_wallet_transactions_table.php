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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->unsignedBigInteger('user_id');
            $table->string('transaction_id')->unique(); // Generated via getTrx()
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->enum('type', ['deposit', 'withdrawal', 'purchase', 'refund'])->default('deposit');
            $table->enum('payment_method', ['stripe', 'paypal', 'bank_transfer', 'admin'])->nullable();
            $table->string('payment_id')->nullable(); // Stripe/PayPal transaction ID
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'cancelled'])->default('pending');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Payment gateway response
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('transaction_id');
            $table->index(['payment_method', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
