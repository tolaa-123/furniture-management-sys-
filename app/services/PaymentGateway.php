<?php
/**
 * Payment Gateway Service
 * Handles payment processing - currently supports manual/bank transfer
 * with Stripe integration ready (requires composer require stripe/stripe-php)
 */
class PaymentGateway {
    private $db;
    private $stripeKey;
    private $useStripe = false;

    public function __construct($db = null) {
        $this->db        = $db;
        $this->stripeKey = defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : null;
        $this->useStripe = $this->stripeKey && class_exists('\Stripe\Stripe');
    }

    /**
     * Create a payment intent (Stripe) or return a manual payment reference
     */
    public function createPaymentIntent($amount, $orderId, $description = '') {
        if ($this->useStripe) {
            return $this->stripeCreateIntent($amount, $orderId, $description);
        }
        // Fallback: generate a local reference for manual payment
        return [
            'success'   => true,
            'method'    => 'manual',
            'reference' => 'PAY-' . strtoupper(substr(md5($orderId . time()), 0, 10)),
            'amount'    => $amount,
            'order_id'  => $orderId,
        ];
    }

    /**
     * Confirm / verify a payment
     */
    public function confirmPayment($intentId, $orderId = null) {
        if ($this->useStripe && strpos($intentId, 'pi_') === 0) {
            return $this->stripeConfirmPayment($intentId);
        }
        // Manual: just mark as confirmed
        return ['success' => true, 'transactionId' => $intentId, 'method' => 'manual'];
    }

    /**
     * Record a confirmed payment in the database
     */
    public function recordPayment($orderId, $amount, $transactionId, $method = 'manual', $type = 'deposit') {
        if (!$this->db) return false;
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO furn_payments (order_id, amount, transaction_id, payment_method, payment_type, status, created_at)
                 VALUES (?, ?, ?, ?, ?, 'completed', NOW())
                 ON DUPLICATE KEY UPDATE status='completed', updated_at=NOW()"
            );
            return $stmt->execute([$orderId, $amount, $transactionId, $method, $type]);
        } catch (PDOException $e) {
            error_log("PaymentGateway::recordPayment error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process a refund
     */
    public function refund($transactionId, $amount = null) {
        if ($this->useStripe && strpos($transactionId, 'pi_') === 0) {
            return $this->stripeRefund($transactionId, $amount);
        }
        return ['success' => true, 'refundId' => 'REF-' . strtoupper(substr(md5($transactionId . time()), 0, 8)), 'method' => 'manual'];
    }

    // -------------------------------------------------------
    // Stripe Methods (only called when Stripe SDK is available)
    // -------------------------------------------------------
    private function stripeCreateIntent($amount, $orderId, $description) {
        try {
            \Stripe\Stripe::setApiKey($this->stripeKey);
            $intent = \Stripe\PaymentIntent::create([
                'amount'      => (int)($amount * 100),
                'currency'    => 'usd',
                'description' => $description ?: "Order #{$orderId}",
                'metadata'    => ['order_id' => $orderId],
            ]);
            return ['success' => true, 'method' => 'stripe', 'clientSecret' => $intent->client_secret, 'intentId' => $intent->id];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Stripe createIntent error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function stripeConfirmPayment($intentId) {
        try {
            \Stripe\Stripe::setApiKey($this->stripeKey);
            $intent = \Stripe\PaymentIntent::retrieve($intentId);
            if ($intent->status === 'succeeded') {
                return ['success' => true, 'transactionId' => $intent->id, 'amount' => $intent->amount / 100, 'method' => 'stripe'];
            }
            return ['success' => false, 'error' => 'Payment status: ' . $intent->status];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Stripe confirmPayment error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function stripeRefund($transactionId, $amount) {
        try {
            \Stripe\Stripe::setApiKey($this->stripeKey);
            $params = ['payment_intent' => $transactionId];
            if ($amount) $params['amount'] = (int)($amount * 100);
            $refund = \Stripe\Refund::create($params);
            return ['success' => true, 'refundId' => $refund->id, 'method' => 'stripe'];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Stripe refund error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
