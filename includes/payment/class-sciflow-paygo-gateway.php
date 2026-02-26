<?php
/**
 * PayGo Pix payment gateway adapter.
 *
 * Uses PayGo Gate2all API.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load the interface.
require_once SCIFLOW_PATH . 'includes/payment/interface-sciflow-payment-gateway-interface.php';

class SciFlow_PayGo_Gateway implements SciFlow_Payment_Gateway_Interface
{

    /**
     * API endpoints.
     */
    private const SANDBOX_URL = 'https://api.gate2all.com.br/v1/transactions';
    private const PROD_URL = 'https://api.gate2all.com.br/v1/transactions';

    /**
     * Get settings.
     */
    private function get_settings()
    {
        $settings = get_option('sciflow_settings', array());

        // We will try to get these from SciFlow settings or WooCommerce fallback.
        $wc_settings = get_option('woocommerce_paygo_settings', array());

        return array(
            'api_key' => !empty($settings['paygo_integration_key']) ? $settings['paygo_integration_key'] : ($wc_settings['api_key'] ?? ''),
            'auth_key' => !empty($settings['paygo_token']) ? $settings['paygo_token'] : ($wc_settings['auth_key'] ?? ''),
            'pix_key' => !empty($settings['paygo_pix_key']) ? $settings['paygo_pix_key'] : ($wc_settings['pix_key'] ?? ''),
            'ambiente' => !empty($settings['paygo_ambiente']) ? $settings['paygo_ambiente'] : (isset($wc_settings['sandbox']) && $wc_settings['sandbox'] === 'yes' ? 'sandbox' : 'producao'),
            'valor' => floatval($settings['submission_price'] ?? 50.00),
        );
    }

    /**
     * Get the base API URL.
     */
    private function get_api_url()
    {
        $s = $this->get_settings();
        return $s['ambiente'] === 'producao' ? self::PROD_URL : self::SANDBOX_URL;
    }

    /**
     * Create a Pix charge.
     */
    public function create_charge(float $amount, array $metadata): array
    {
        $s = $this->get_settings();
        if (empty($s['api_key']) || empty($s['auth_key'])) {
            return array('error' => __('Credenciais PayGo não configuradas.', 'sciflow-wp'));
        }

        $url = $this->get_api_url();

        $payload = array(
            'amount' => round($amount * 100), // Many APIs use cents.
            'currency' => 'BRL',
            'orderId' => 'sciflow_' . ($metadata['post_id'] ?? time()),
            'payment' => array(
                'pix' => array(
                    'provider' => 'C6BANK',
                ),
            ),
            'postBackUrl' => home_url('/wp-json/sciflow/v1/payment-webhook'),
        );

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'authenticationApi' => $s['api_key'],
                'authenticationKey' => $s['auth_key'],
            ),
            'body' => wp_json_encode($payload),
            'sslverify' => true,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            error_log('SciFlow PayGo Charge Error: ' . $response->get_error_message());
            return array('error' => $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 400 || !empty($body['error'])) {
            $msg = $body['message'] ?? ($body['error']['message'] ?? 'Erro desconhecido PayGo');
            error_log('SciFlow PayGo API Error: ' . $msg);
            return array('error' => $msg);
        }

        // Handle successful response.
        // PayGo typically returns qrCode content or a location.
        $pix_data = $body['payment']['pix'] ?? array();

        if (!empty($pix_data['qrCode'])) {
            if (!empty($metadata['post_id'])) {
                update_post_meta($metadata['post_id'], '_sciflow_payment_id', $body['id'] ?? '');
            }

            return array(
                'txid' => $body['id'] ?? '',
                'pix_copia_cola' => $pix_data['qrCode'] ?? '',
                'qr_code' => $pix_data['qrCode'] ?? '', // Many libraries can render this string as QR.
                'status' => $body['status'] ?? 'PENDING',
            );
        }

        return array('error' => __('Erro ao gerar QR Code Pix via PayGo.', 'sciflow-wp'));
    }

    /**
     * Verify payment by transaction ID.
     */
    public function verify_payment(string $txid): bool
    {
        $s = $this->get_settings();
        $url = $this->get_api_url() . '/' . $txid;

        $response = wp_remote_get($url, array(
            'headers' => array(
                'authenticationApi' => $s['api_key'],
                'authenticationKey' => $s['auth_key'],
            ),
            'sslverify' => true,
            'timeout' => 30,
        ));

        if (is_wp_error($response))
            return false;

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return ($body['status'] ?? '') === 'PAID' || ($body['status'] ?? '') === 'CONFIRMED';
    }

    /**
     * Handle webhook notification.
     */
    public function handle_webhook(array $payload): bool
    {
        $txid = $payload['id'] ?? '';
        $status = $payload['status'] ?? '';

        if (empty($txid) || !($status === 'PAID' || $status === 'CONFIRMED')) {
            return false;
        }

        // Find post by txid.
        $query = new WP_Query(array(
            'post_type' => array('enfrute_trabalhos', 'senco_trabalhos'),
            'posts_per_page' => 1,
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => '_sciflow_payment_id',
                    'value' => $txid,
                ),
            ),
        ));

        if ($query->have_posts()) {
            $post_id = $query->posts[0]->ID;
            $current_payment = get_post_meta($post_id, '_sciflow_payment_status', true);

            if ($current_payment !== 'confirmed') {
                $submission = new SciFlow_Submission(
                    new SciFlow_Status_Manager(),
                    new SciFlow_Email()
                );
                $submission->confirm_payment($post_id);
            }
            return true;
        }

        return false;
    }

    /**
     * Register REST routes.
     */
    public function register_routes(): void
    {
        register_rest_route('sciflow/v1', '/payment-webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'webhook_callback'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * REST callback.
     */
    public function webhook_callback(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();
        if (empty($payload)) {
            $payload = $request->get_body_params();
        }

        $success = $this->handle_webhook($payload);

        return new WP_REST_Response(array('success' => $success), 200);
    }

    /**
     * Manual confirmation.
     */
    public function manual_confirm($post_id)
    {
        if (!current_user_can('manage_sciflow')) {
            return new WP_Error('unauthorized', __('Permissão insuficiente.', 'sciflow-wp'));
        }

        $submission = new SciFlow_Submission(
            new SciFlow_Status_Manager(),
            new SciFlow_Email()
        );

        return $submission->confirm_payment($post_id);
    }

    /**
     * Get amount.
     */
    public function get_amount()
    {
        $s = $this->get_settings();
        return $s['valor'];
    }
}
