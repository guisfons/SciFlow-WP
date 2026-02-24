<?php
/**
 * Sicredi Pix payment gateway adapter.
 *
 * Uses Sicredi API with OAuth2 (Client ID / Client Secret).
 * Documentation: Portal do Desenvolvedor Sicredi.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load the interface.
require_once SCIFLOW_PATH . 'includes/payment/interface-sciflow-payment-gateway-interface.php';

class SciFlow_Sicredi_Pix implements SciFlow_Payment_Gateway_Interface
{

    /**
     * API endpoints.
     */
    private const SANDBOX_URL = 'https://api-parceiro.sicredi.com.br/sb/openbanking/v2';
    private const PROD_URL = 'https://api-parceiro.sicredi.com.br/openbanking/v2';
    private const TOKEN_SANDBOX = 'https://api-parceiro.sicredi.com.br/auth/openbanking/token';
    private const TOKEN_PROD = 'https://api-parceiro.sicredi.com.br/auth/openbanking/token';

    /**
     * Get settings.
     */
    private function get_settings()
    {
        $settings = get_option('sciflow_settings', array());
        return array(
            'client_id' => $settings['sicredi_client_id'] ?? '',
            'client_secret' => $settings['sicredi_client_secret'] ?? '',
            'chave_pix' => $settings['sicredi_chave_pix'] ?? '',
            'ambiente' => $settings['sicredi_ambiente'] ?? 'sandbox',
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
     * Get OAuth2 access token.
     */
    private function get_access_token()
    {
        $cached = get_transient('sciflow_sicredi_token');
        if ($cached) {
            return $cached;
        }

        $s = $this->get_settings();
        $url = $s['ambiente'] === 'producao' ? self::TOKEN_PROD : self::TOKEN_SANDBOX;

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($s['client_id'] . ':' . $s['client_secret']),
            ),
            'body' => array(
                'grant_type' => 'client_credentials',
                'scope' => 'cob.read cob.write pix.read',
            ),
            'sslverify' => true,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            error_log('SciFlow Sicredi Token Error: ' . $response->get_error_message());
            return false;
        }

        $body_content = wp_remote_retrieve_body($response);
        $body = json_decode($body_content, true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200 || empty($body['access_token'])) {
            error_log('SciFlow Sicredi Token Error: HTTP ' . $code . ' - Response: ' . $body_content);
            return false;
        }

        $ttl = ($body['expires_in'] ?? 3600) - 60; // Buffer.
        set_transient('sciflow_sicredi_token', $body['access_token'], $ttl);

        return $body['access_token'];
    }

    /**
     * Create a Pix charge (cobrança imediata).
     */
    public function create_charge(float $amount, array $metadata): array
    {
        $token = $this->get_access_token();
        if (!$token) {
            return array('error' => __('Falha na autenticação com o Sicredi.', 'sciflow-wp'));
        }

        $s = $this->get_settings();
        $txid = 'sciflow' . time() . wp_rand(1000, 9999);

        $payload = array(
            'calendario' => array(
                'expiracao' => 3600, // 1 hour.
            ),
            'devedor' => array(),
            'valor' => array(
                'original' => number_format($amount, 2, '.', ''),
            ),
            'chave' => $s['chave_pix'],
            'solicitacaoPagador' => sprintf(
                'Submissão SciFlow #%d',
                $metadata['post_id'] ?? 0
            ),
            'infoAdicionais' => array(
                array('nome' => 'post_id', 'valor' => (string) ($metadata['post_id'] ?? '')),
            ),
        );

        $url = $this->get_api_url() . '/cob/' . $txid;

        $response = wp_remote_request($url, array(
            'method' => 'PUT',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ),
            'body' => wp_json_encode($payload),
            'sslverify' => true,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            error_log('SciFlow Sicredi Charge Error: ' . $response->get_error_message());
            return array('error' => $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['txid'])) {
            // Save txid to post meta.
            if (!empty($metadata['post_id'])) {
                update_post_meta($metadata['post_id'], '_sciflow_payment_id', $body['txid']);
            }

            return array(
                'txid' => $body['txid'],
                'pix_copia_cola' => $body['pixCopiaECola'] ?? '',
                'qr_code' => $body['qrCode'] ?? ($body['loc']['location'] ?? ''),
                'status' => $body['status'] ?? 'ATIVA',
            );
        }

        return array('error' => __('Erro ao criar cobrança Pix.', 'sciflow-wp'));
    }

    /**
     * Verify payment by txid.
     */
    public function verify_payment(string $txid): bool
    {
        $token = $this->get_access_token();
        if (!$token)
            return false;

        $url = $this->get_api_url() . '/cob/' . $txid;

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
            'sslverify' => true,
            'timeout' => 30,
        ));

        if (is_wp_error($response))
            return false;

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return ($body['status'] ?? '') === 'CONCLUIDA';
    }

    /**
     * Handle webhook notification from Sicredi.
     */
    public function handle_webhook(array $payload): bool
    {
        if (empty($payload['pix']) || !is_array($payload['pix'])) {
            return false;
        }

        foreach ($payload['pix'] as $pix) {
            $txid = $pix['txid'] ?? '';
            if (empty($txid))
                continue;

            // Find the post with this txid.
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
            }
        }

        return true;
    }

    /**
     * Register REST route for payment webhook.
     */
    public function register_routes(): void
    {
        register_rest_route('sciflow/v1', '/payment-webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'webhook_callback'),
            'permission_callback' => '__return_true', // Webhooks must be open.
        ));
    }

    /**
     * REST webhook callback.
     */
    public function webhook_callback(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();

        if (empty($payload)) {
            $payload = $request->get_body_params();
        }

        $success = $this->handle_webhook($payload);

        return new WP_REST_Response(
            array('success' => $success),
            $success ? 200 : 400
        );
    }

    /**
     * Manual payment confirmation (admin fallback).
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
     * Get the charge amount from settings.
     */
    public function get_amount()
    {
        $s = $this->get_settings();
        return $s['valor'];
    }
}
