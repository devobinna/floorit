<?php

namespace App\Services;

use App\Models\User;
use App\Models\Credit;
use App\Models\CreditTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

class CreditService
{
    /**
     * Get or create user credits
     */
    public function getOrCreate(User $user): Credit
    {
        return Credit::firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 90,
                'reserved' => 0,
                'total_earned' => 0,
                'total_spent' => 0,
            ]
        );
    }

    /**
     * Add credits to user account
     */
    public function add(
        User $user,
        int $amount,
        string $type = 'purchase',
        string $source = 'manual',
        ?string $description = null,
        $reference = null
    ): CreditTransaction {
        return DB::transaction(function () use ($user, $amount, $type, $source, $description, $reference) {
            $credit = $this->getOrCreate($user);

            // Update balance
            $credit->increment('balance', $amount);
            $credit->increment('total_earned', $amount);
            $credit->touch();

            // Create transaction record
            return CreditTransaction::create([
                'user_id' => $user->id,
                'credit_id' => $credit->id,
                'amount' => $amount,
                'balance_after' => $credit->fresh()->balance,
                'type' => $type,
                'source' => $source,
                'description' => $description ?? "Added {$amount} credits",
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->id,
            ]);
        });
    }

    /**
     * Deduct credits from user account (atomic)
     */
    public function deduct(
        User $user,
        int $amount,
        string $source = 'generation',
        ?string $description = null,
        $reference = null
    ): CreditTransaction {
        return DB::transaction(function () use ($user, $amount, $source, $description, $reference) {
            $credit = $this->getOrCreate($user);

            // Check sufficient balance
            if ($credit->balance < $amount) {
                throw new Exception("Insufficient credits. Required: {$amount}, Available: {$credit->balance}");
            }

            // Deduct balance
            $credit->decrement('balance', $amount);
            $credit->increment('total_spent', $amount);
            $credit->touch();

            // Create transaction record
            return CreditTransaction::create([
                'user_id' => $user->id,
                'credit_id' => $credit->id,
                'amount' => -$amount,
                'balance_after' => $credit->fresh()->balance,
                'type' => 'usage',
                'source' => $source,
                'description' => $description ?? "Deducted {$amount} credits",
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->id,
            ]);
        });
    }

    /**
     * Refund credits to user account
     */
    public function refund(
        User $user,
        int $amount,
        string $reason,
        $reference = null
    ): CreditTransaction {
        return $this->add(
            user: $user,
            amount: $amount,
            type: 'refund',
            source: 'system',
            description: "Refund: {$reason}",
            reference: $reference
        );
    }

    /**
     * Reserve credits for processing
     */
    public function reserve(User $user, int $amount): void
    {
        DB::transaction(function () use ($user, $amount) {
            $credit = $this->getOrCreate($user);

            if ($credit->getAvailableBalance() < $amount) {
                throw new Exception("Insufficient available credits");
            }

            $credit->increment('reserved', $amount);
        });
    }

    /**
     * Release reserved credits
     */
    public function release(User $user, int $amount): void
    {
        DB::transaction(function () use ($user, $amount) {
            $credit = $this->getOrCreate($user);
            $credit->decrement('reserved', $amount);
        });
    }

    /**
     * Transfer credits between users (admin function)
     */
    public function transfer(User $from, User $to, int $amount, string $reason): array
    {
        return DB::transaction(function () use ($from, $to, $amount, $reason) {
            // Deduct from sender
            $deductTransaction = $this->deduct(
                user: $from,
                amount: $amount,
                source: 'transfer',
                description: "Transfer to {$to->name}: {$reason}"
            );

            // Add to receiver
            $addTransaction = $this->add(
                user: $to,
                amount: $amount,
                type: 'bonus',
                source: 'transfer',
                description: "Transfer from {$from->name}: {$reason}"
            );

            return [
                'from' => $deductTransaction,
                'to' => $addTransaction,
            ];
        });
    }

    /**
     * Get user credit balance
     */
    public function getBalance(User $user): int
    {
        return $this->getOrCreate($user)->balance;
    }

    /**
     * Get user credit balance (alias)
     */
    public function getCreditBalance(User $user): int
    {
        return $this->getBalance($user);
    }

    /**
     * Check if user has enough credits
     */
    public function hasEnough(User $user, int $amount): bool
    {
        return $this->getBalance($user) >= $amount;
    }

    /**
     * Get credit history
     */
    public function getHistory(User $user, int $limit = 20)
    {
        return CreditTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
