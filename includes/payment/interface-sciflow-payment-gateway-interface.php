<?php
/**
 * Payment gateway interface.
 */

if (!defined('ABSPATH')) {
    exit;
}

interface SciFlow_Payment_Gateway_Interface
{

    /**
     * Create a Pix charge.
     *
     * @param float $amount   Charge amount in BRL.
     * @param array $metadata Additional data (post_id, user_id, etc.).
     * @return array { txid: string, qr_code: string, pix_copia_cola: string }
     */
    public function create_charge(float $amount, array $metadata): array;

    /**
     * Verify a payment by its transaction ID.
     *
     * @param string $txid Transaction ID.
     * @return bool True if confirmed.
     */
    public function verify_payment(string $txid): bool;

    /**
     * Handle incoming webhook payload.
     *
     * @param array $payload The parsed webhook body.
     * @return bool Whether the payment was confirmed.
     */
    public function handle_webhook(array $payload): bool;

    /**
     * Register REST routes for webhooks.
     */
    public function register_routes(): void;
}
