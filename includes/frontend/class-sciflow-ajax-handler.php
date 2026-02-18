<?php
/**
 * AJAX endpoints for SciFlow frontend forms.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Ajax_Handler
{

    private $submission;
    private $review;
    private $editorial;
    private $poster_upload;
    private $payment;

    public function __construct(
        SciFlow_Submission $submission,
        SciFlow_Review $review,
        SciFlow_Editorial $editorial,
        SciFlow_Poster_Upload $poster_upload,
        SciFlow_Sicredi_Pix $payment
    ) {
        $this->submission = $submission;
        $this->review = $review;
        $this->editorial = $editorial;
        $this->poster_upload = $poster_upload;
        $this->payment = $payment;

        // Submission.
        add_action('wp_ajax_sciflow_submit', array($this, 'handle_submit'));

        // Resubmission.
        add_action('wp_ajax_sciflow_resubmit', array($this, 'handle_resubmit'));

        // Review.
        add_action('wp_ajax_sciflow_submit_review', array($this, 'handle_submit_review'));

        // Editorial.
        add_action('wp_ajax_sciflow_assign_reviewer', array($this, 'handle_assign_reviewer'));
        add_action('wp_ajax_sciflow_editorial_decision', array($this, 'handle_editorial_decision'));

        // Poster upload.
        add_action('wp_ajax_sciflow_upload_poster', array($this, 'handle_upload_poster'));

        // Payment.
        add_action('wp_ajax_sciflow_create_payment', array($this, 'handle_create_payment'));
        add_action('wp_ajax_sciflow_check_payment', array($this, 'handle_check_payment'));

        // Confirmation.
        add_action('wp_ajax_sciflow_confirm_presentation', array($this, 'handle_confirm_presentation'));
    }

    /**
     * Verify nonce and user authentication.
     */
    private function verify_request()
    {
        if (!check_ajax_referer('sciflow_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sessão expirada. Recarregue a página.', 'sciflow-wp')));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Você precisa estar logado.', 'sciflow-wp')));
        }
    }

    /**
     * Handle new submission.
     */
    public function handle_submit()
    {
        $this->verify_request();

        $data = array(
            'event' => sanitize_text_field($_POST['event'] ?? ''),
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'content' => wp_kses_post($_POST['content'] ?? ''),
            'authors_text' => sanitize_text_field($_POST['authors_text'] ?? ''),
            'keywords' => array_map('sanitize_text_field', (array) ($_POST['keywords'] ?? array())),
            'coauthors' => $_POST['coauthors'] ?? array(),
            'language' => sanitize_text_field($_POST['language'] ?? 'pt'),
        );

        $result = $this->submission->create($data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Trabalho submetido com sucesso! Aguardando pagamento.', 'sciflow-wp'),
            'post_id' => $result,
        ));
    }

    /**
     * Handle resubmission.
     */
    public function handle_resubmit()
    {
        $this->verify_request();

        $post_id = absint($_POST['post_id'] ?? 0);

        $data = array(
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'content' => wp_kses_post($_POST['content'] ?? ''),
            'keywords' => array_map('sanitize_text_field', (array) ($_POST['keywords'] ?? array())),
            'coauthors' => $_POST['coauthors'] ?? array(),
        );

        $result = $this->submission->resubmit($post_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Trabalho reenviado com sucesso!', 'sciflow-wp')));
    }

    /**
     * Handle review submission.
     */
    public function handle_submit_review()
    {
        $this->verify_request();

        $post_id = absint($_POST['post_id'] ?? 0);

        $data = array(
            'scores' => $_POST['scores'] ?? array(),
            'decision' => sanitize_text_field($_POST['decision'] ?? ''),
            'notes' => wp_kses_post($_POST['notes'] ?? ''),
        );

        $result = $this->review->submit_review($post_id, $data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Avaliação enviada com sucesso!', 'sciflow-wp')));
    }

    /**
     * Handle reviewer assignment.
     */
    public function handle_assign_reviewer()
    {
        $this->verify_request();

        $post_id = absint($_POST['post_id'] ?? 0);
        $reviewer_id = absint($_POST['reviewer_id'] ?? 0);

        $result = $this->editorial->assign_reviewer($post_id, $reviewer_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Revisor atribuído com sucesso!', 'sciflow-wp')));
    }

    /**
     * Handle editorial decision.
     */
    public function handle_editorial_decision()
    {
        $this->verify_request();

        $post_id = absint($_POST['post_id'] ?? 0);
        $decision = sanitize_text_field($_POST['decision'] ?? '');
        $notes = wp_kses_post($_POST['notes'] ?? '');

        $result = $this->editorial->make_decision($post_id, $decision, $notes);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Decisão registrada com sucesso!', 'sciflow-wp')));
    }

    /**
     * Handle poster upload.
     */
    public function handle_upload_poster()
    {
        $this->verify_request();

        $post_id = absint($_POST['post_id'] ?? 0);

        if (empty($_FILES['poster'])) {
            wp_send_json_error(array('message' => __('Nenhum arquivo enviado.', 'sciflow-wp')));
        }

        $result = $this->poster_upload->upload($post_id, $_FILES['poster']);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Pôster enviado com sucesso!', 'sciflow-wp')));
    }

    /**
     * Handle payment creation.
     */
    public function handle_create_payment()
    {
        $this->verify_request();

        $post_id = absint($_POST['post_id'] ?? 0);
        $amount = $this->payment->get_amount();

        $result = $this->payment->create_charge($amount, array('post_id' => $post_id));

        if (!empty($result['error'])) {
            wp_send_json_error(array('message' => $result['error']));
        }

        wp_send_json_success($result);
    }

    /**
     * Handle payment status check.
     */
    public function handle_check_payment()
    {
        $this->verify_request();

        $post_id = absint($_POST['post_id'] ?? 0);
        $txid = get_post_meta($post_id, '_sciflow_payment_id', true);

        if (!$txid) {
            wp_send_json_error(array('message' => __('Cobrança não encontrada.', 'sciflow-wp')));
        }

        $confirmed = $this->payment->verify_payment($txid);

        if ($confirmed) {
            $current_payment = get_post_meta($post_id, '_sciflow_payment_status', true);
            if ($current_payment !== 'confirmed') {
                $this->submission->confirm_payment($post_id);
            }
        }

        wp_send_json_success(array(
            'confirmed' => $confirmed,
            'message' => $confirmed
                ? __('Pagamento confirmado!', 'sciflow-wp')
                : __('Pagamento ainda não confirmado.', 'sciflow-wp'),
        ));
    }

    /**
     * Handle presentation confirmation.
     */
    public function handle_confirm_presentation()
    {
        $this->verify_request();

        $post_id = absint($_POST['post_id'] ?? 0);
        $ranking = new SciFlow_Ranking();
        $result = $ranking->confirm_presentation($post_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Apresentação confirmada!', 'sciflow-wp')));
    }
}
