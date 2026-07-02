<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

class WalletService
{
    /**
     * Get or create user wallet
     */
    public function getOrCreate(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 0,
                'currency' => 'USD',
            ]
        );
    }

    /**
     * Add funds to wallet
     */
    public function deposit(
        User $user,
        float $amount,
        string $paymentMethod,
        ?string $paymentId = null,
        ?string $description = null,
        ?array $metadata = null
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amount, $paymentMethod, $paymentId, $description, $metadata) {
            $wallet = $this->getOrCreate($user);

            // Update wallet balance
            $wallet->increment('balance', $amount);

            // Create transaction record
            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'transaction_id' => getTrx(),
                'amount' => $amount,
                'balance_after' => $wallet->fresh()->balance,
                'type' => 'deposit',
                'payment_method' => $paymentMethod,
                'payment_id' => $paymentId,
                'status' => 'completed',
                'description' => $description ?? "Deposit via {$paymentMethod}",
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Withdraw funds from wallet
     */
    public function withdraw(
        User $user,
        float $amount,
        string $paymentMethod,
        ?string $description = null
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amount, $paymentMethod, $description) {
            $wallet = $this->getOrCreate($user);

            // Check sufficient balance
            if (!$wallet->hasSufficientBalance($amount)) {
                throw new Exception("Insufficient wallet balance. Required: \${$amount}, Available: \${$wallet->balance}");
            }

            // Deduct balance
            $wallet->decrement('balance', $amount);

            // Create transaction record
            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'transaction_id' => getTrx(),
                'amount' => $amount,
                'balance_after' => $wallet->fresh()->balance,
                'type' => 'withdrawal',
                'payment_method' => $paymentMethod,
                'status' => 'pending',
                'description' => $description ?? "Withdrawal via {$paymentMethod}",
            ]);
        });
    }

    /**
     * Process credit purchase from wallet
     */
    public function purchaseCredits(
        User $user,
        float $amount,
        int $credits
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amount, $credits) {
            $wallet = $this->getOrCreate($user);

            // Check sufficient balance
            if (!$wallet->hasSufficientBalance($amount)) {
                throw new Exception("Insufficient wallet balance");
            }

            // Deduct from wallet
            $wallet->decrement('balance', $amount);

            // Create transaction record
            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'transaction_id' => getTrx(),
                'amount' => $amount,
                'balance_after' => $wallet->fresh()->balance,
                'type' => 'purchase',
                'payment_method' => 'wallet',
                'status' => 'completed',
                'description' => "Purchased {$credits} credits",
                'metadata' => ['credits' => $credits],
            ]);
        });
    }

    /**
     * Refund transaction
     */
    public function refund(
        WalletTransaction $transaction,
        string $reason
    ): WalletTransaction {
        if ($transaction->status === 'refunded') {
            throw new Exception("Transaction already refunded");
        }

        return DB::transaction(function () use ($transaction, $reason) {
            $wallet = $transaction->wallet;

            // Add funds back
            $wallet->increment('balance', $transaction->amount);

            // Update original transaction
            $transaction->update(['status' => 'refunded']);

            // Create refund transaction
            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $transaction->user_id,
                'transaction_id' => getTrx(),
                'amount' => $transaction->amount,
                'balance_after' => $wallet->fresh()->balance,
                'type' => 'refund',
                'payment_method' => $transaction->payment_method,
                'status' => 'completed',
                'description' => "Refund: {$reason}",
                'metadata' => [
                    'original_transaction_id' => $transaction->transaction_id,
                    'reason' => $reason,
                ],
            ]);
        });
    }

    /**
     * Get wallet balance
     */
    public function getBalance(User $user): float
    {
        return $this->getOrCreate($user)->balance;
    }

    /**
     * Get transaction history
     */
    public function getHistory(User $user, int $limit = 20)
    {
        return WalletTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get formatted balance
     */
    public function getFormattedBalance(User $user): string
    {
        $wallet = $this->getOrCreate($user);
        return $wallet->formatted_balance;
    }
}
