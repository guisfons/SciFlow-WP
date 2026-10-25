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
        // SciFlow settings (or wp-config constants) first, WooCommerce gateway settings as fallback.
        $wc_settings = get_option('woocommerce_paygo_settings', array());
        if (!is_array($wc_settings)) {
            $wc_settings = array();
        }

        $api_key = (string) SciFlow_Settings::get('paygo_integration_key', '');
        $auth_key = (string) SciFlow_Settings::get('paygo_token', '');
        $pix_key = (string) SciFlow_Settings::get('paygo_pix_key', '');
        $stored = get_option(SciFlow_Settings::OPTION, array());

        return array(
            'api_key' => '' !== $api_key ? $api_key : ($wc_settings['api_key'] ?? ''),
            'auth_key' => '' !== $auth_key ? $auth_key : ($wc_settings['auth_key'] ?? ''),
            'pix_key' => '' !== $pix_key ? $pix_key : ($wc_settings['pix_key'] ?? ''),
            'ambiente' => !empty($stored['paygo_ambiente']) ? $stored['paygo_ambiente'] : (isset($wc_settings['sandbox']) && $wc_settings['sandbox'] === 'yes' ? 'sandbox' : 'producao'),
            'valor' => (float) SciFlow_Settings::get('submission_price', 50.00),
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
            'postBackUrl' => $this->get_webhook_url(),
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
            error_log('SciFlow PayGo: charge request failed (' . $response->get_error_code() . ').');
            return array('error' => __('Não foi possível contatar o serviço de pagamento. Tente novamente.', 'sciflow-wp'));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 400 || !empty($body['error'])) {
            error_log('SciFlow PayGo: API returned HTTP ' . (int) $code . '.');
            return array('error' => __('O serviço de pagamento recusou a cobrança. Contate a organização.', 'sciflow-wp'));
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
        if ('' === $txid || empty($s['api_key']) || empty($s['auth_key'])) {
            return false;
        }
        $url = $this->get_api_url() . '/' . rawurlencode($txid);

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
        $txid = isset($payload['id']) && is_scalar($payload['id']) ? sanitize_text_field((string) $payload['id']) : '';
        $status = isset($payload['status']) && is_scalar($payload['status']) ? (string) $payload['status'] : '';

        if (empty($txid) || !($status === 'PAID' || $status === 'CONFIRMED')) {
            return false;
        }

        // Find post by txid.
        $query = new WP_Query(array(
            'post_type' => array('enfrute_trabalhos', 'semco_trabalhos'),
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

            // Never trust the notification body: confirm the status with the API.
            if ($current_payment !== 'confirmed' && !$this->verify_payment($txid)) {
                return false;
            }

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
            // Public by design (called by PayGo). Authenticity is enforced by
            // the optional shared secret and by re-checking the API.
            'permission_callback' => array($this, 'webhook_permission'),
        ));
    }

    /**
     * Webhook permission: when a secret is configured, require it in the
     * `token` query argument or the `X-SciFlow-Token` header.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function webhook_permission(WP_REST_Request $request)
    {
        $secret = (string) SciFlow_Settings::get('paygo_webhook_secret', '');
        if ('' === $secret) {
            return true;
        }
        $given = (string) ($request->get_header('x_sciflow_token') ?: $request->get_param('token'));
        if ('' !== $given && hash_equals($secret, $given)) {
            return true;
        }
        return new WP_Error('sciflow_forbidden', 'Forbidden', array('status' => 403));
    }

    /**
     * URL PayGo must call (includes the secret when configured).
     */
    public function get_webhook_url()
    {
        $url = rest_url('sciflow/v1/payment-webhook');
        $secret = (string) SciFlow_Settings::get('paygo_webhook_secret', '');
        return '' !== $secret ? add_query_arg('token', rawurlencode($secret), $url) : $url;
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
        if (!SciFlow_Security::can_manage_submission($post_id)) {
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
