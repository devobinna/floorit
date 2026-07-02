<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Exception;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected WalletService $walletService;
    protected CreditService $creditService;

    public function __construct(
        WalletService $walletService,
        CreditService $creditService
    ) {
        $this->walletService = $walletService;
        $this->creditService = $creditService;
        
        // Initialize Stripe
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create Stripe payment intent
     */
    public function createStripePaymentIntent(
        User $user,
        float $amount,
        string $currency = 'usd',
        ?array $metadata = null
    ): array {
        try {
            $intent = PaymentIntent::create([
                'amount' => $amount * 100, // Convert to cents
                'currency' => $currency,
                'customer_email' => $user->email,
                'metadata' => array_merge([
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ], $metadata ?? []),
                'description' => "Payment for {$user->name}",
            ]);

            return [
                'client_secret' => $intent->client_secret,
                'intent_id' => $intent->id,
                'amount' => $amount,
            ];
        } catch (Exception $e) {
            Log::error('Stripe payment intent creation failed', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Failed to create payment intent: ' . $e->getMessage());
        }
    }

    /**
     * Handle Stripe webhook events
     */
    public function handleStripeWebhook(string $payload, string $signature): bool
    {
        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Invalid webhook signature');
        }

        // Handle different event types
        switch ($event->type) {
            case 'payment_intent.succeeded':
                return $this->handlePaymentSuccess($event->data->object);

            case 'payment_intent.payment_failed':
                return $this->handlePaymentFailed($event->data->object);

            case 'charge.refunded':
                return $this->handleRefund($event->data->object);

            default:
                Log::info('Unhandled webhook event', ['type' => $event->type]);
                return true;
        }
    }

    /**
     * Handle successful payment
     */
    protected function handlePaymentSuccess($paymentIntent): bool
    {
        try {
            $userId = $paymentIntent->metadata->user_id ?? null;
            $type = $paymentIntent->metadata->type ?? 'wallet_deposit';

            if (!$userId) {
                Log::error('Payment success without user_id', [
                    'payment_intent_id' => $paymentIntent->id,
                ]);
                return false;
            }

            $user = User::find($userId);
            if (!$user) {
                Log::error('User not found for payment', ['user_id' => $userId]);
                return false;
            }

            $amount = $paymentIntent->amount / 100; // Convert from cents

            if ($type === 'wallet_deposit') {
                // Add funds to wallet
                $this->walletService->deposit(
                    user: $user,
                    amount: $amount,
                    paymentMethod: 'stripe',
                    paymentId: $paymentIntent->id,
                    description: 'Wallet deposit via Stripe',
                    metadata: [
                        'payment_intent' => $paymentIntent->id,
                        'payment_method' => $paymentIntent->payment_method,
                    ]
                );

                // Send email notification
                sendMail($user, 'WALLET_DEPOSIT', [
                    'amount' => $amount,
                    'balance' => $this->walletService->getFormattedBalance($user),
                ]);
            } elseif ($type === 'credit_purchase') {
                // Direct credit purchase
                $credits = $paymentIntent->metadata->credits ?? 0;
                
                $this->creditService->purchaseCredits(
                    user: $user,
                    amount: (int) $credits,
                    price: $amount,
                    source: 'stripe'
                );

                // Email sent by CreditService
            }

            Log::info('Payment processed successfully', [
                'user_id' => $userId,
                'amount' => $amount,
                'type' => $type,
                'payment_intent_id' => $paymentIntent->id,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to process payment success', [
                'payment_intent_id' => $paymentIntent->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle failed payment
     */
    protected function handlePaymentFailed($paymentIntent): bool
    {
        try {
            $userId = $paymentIntent->metadata->user_id ?? null;

            if (!$userId) {
                return false;
            }

            $user = User::find($userId);
            if (!$user) {
                return false;
            }

            // Send failure notification
            sendMail($user, 'PAYMENT_FAILED', [
                'amount' => $paymentIntent->amount / 100,
                'reason' => $paymentIntent->last_payment_error->message ?? 'Unknown error',
            ]);

            Log::warning('Payment failed', [
                'user_id' => $userId,
                'payment_intent_id' => $paymentIntent->id,
                'error' => $paymentIntent->last_payment_error->message ?? 'Unknown',
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to process payment failure', [
                'payment_intent_id' => $paymentIntent->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle refund
     */
    protected function handleRefund($charge): bool
    {
        try {
            $paymentIntentId = $charge->payment_intent;
            
            // Find transaction by payment_id
            $transaction = WalletTransaction::where('payment_id', $paymentIntentId)
                ->where('type', 'deposit')
                ->first();

            if (!$transaction) {
                Log::warning('Transaction not found for refund', [
                    'payment_intent_id' => $paymentIntentId,
                ]);
                return false;
            }

            // Process refund
            $this->walletService->refund(
                transaction: $transaction,
                reason: 'Stripe refund processed'
            );

            // Send refund notification
            sendMail($transaction->user, 'PAYMENT_REFUNDED', [
                'amount' => $transaction->amount,
                'transaction_id' => $transaction->transaction_id,
            ]);

            Log::info('Refund processed', [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $transaction->amount,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to process refund', [
                'charge_id' => $charge->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create checkout session for subscription plans
     */
    public function createCheckoutSession(
        User $user,
        string $planSlug,
        string $successUrl,
        string $cancelUrl
    ): array {
        try {
            // Get plan details
            $plan = \App\Models\Plan::where('slug', $planSlug)
                ->where('is_active', true)
                ->firstOrFail();

            $session = \Stripe\Checkout\Session::create([
                'customer_email' => $user->email,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $plan->name,
                            'description' => $plan->description,
                        ],
                        'unit_amount' => $plan->price * 100,
                        'recurring' => [
                            'interval' => $plan->billing_period === 'yearly' ? 'year' : 'month',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'plan_slug' => $plan->slug,
                ],
            ]);

            return [
                'session_id' => $session->id,
                'url' => $session->url,
            ];
        } catch (Exception $e) {
            Log::error('Checkout session creation failed', [
                'user_id' => $user->id,
                'plan_slug' => $planSlug,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Failed to create checkout session: ' . $e->getMessage());
        }
    }

    /**
     * Get payment methods for user
     */
    public function getPaymentMethods(User $user): array
    {
        return [
            'stripe' => [
                'enabled' => !empty(config('services.stripe.secret')),
                'name' => 'Credit/Debit Card',
            ],
            'paypal' => [
                'enabled' => !empty(config('services.paypal.client_id')),
                'name' => 'PayPal',
            ],
            'wallet' => [
                'enabled' => true,
                'name' => 'Wallet Balance',
                'balance' => $this->walletService->getFormattedBalance($user),
            ],
        ];
    }
}
