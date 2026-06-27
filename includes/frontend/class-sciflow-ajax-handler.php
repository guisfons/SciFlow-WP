<?php
/**
 * AJAX endpoints for SciFlow frontend forms.
 */

if (!defined('ABSPATH')) {
    exit;
}

#[AllowDynamicProperties]
class SciFlow_Ajax_Handler
{

    private $submission;
    private $review;
    private $editorial;
    private $poster_upload;
    private $payment;
    private $woocommerce;

    public function __construct(
        SciFlow_Submission $submission,
        SciFlow_Review $review,
        SciFlow_Editorial $editorial,
        SciFlow_Poster_Upload $poster_upload,
        SciFlow_PayGo_Gateway $payment,
        ?SciFlow_WooCommerce $woocommerce = null
    ) {
        $this->submission = $submission;
        $this->review = $review;
        $this->editorial = $editorial;
        $this->poster_upload = $poster_upload;
        $this->payment = $payment;
        $this->woocommerce = $woocommerce;

        // Submission.
        add_action('wp_ajax_sciflow_submit', array($this, 'handle_submit'));

        // Resubmission.
        add_action('wp_ajax_sciflow_resubmit', array($this, 'handle_resubmit'));

        // Speaker Talk Submission.
        add_action('wp_ajax_sciflow_submit_speaker_talk', array($this, 'handle_submit_speaker_talk'));

        // Review.
        add_action('wp_ajax_sciflow_submit_review', array($this, 'handle_submit_review'));

        // Editorial.
        add_action('wp_ajax_sciflow_assign_reviewer', array($this, 'handle_assign_reviewer'));
        add_action('wp_ajax_sciflow_editorial_decision', array($this, 'handle_editorial_decision'));

        // Poster upload.
        add_action('wp_ajax_sciflow_upload_poster', array($this, 'handle_upload_poster'));
        add_action('wp_ajax_sciflow_poster_decision', array($this, 'handle_poster_decision'));

        // Payment.
        add_action('wp_ajax_sciflow_create_payment', array($this, 'handle_create_payment'));
        add_action('wp_ajax_sciflow_check_payment', array($this, 'handle_check_payment'));
        add_action('wp_ajax_sciflow_confirm_payment_admin', array($this, 'handle_confirm_payment_admin'));

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
            'title' => SciFlow_Status_Manager::sanitize_title($_POST['title'] ?? ''),
            'content' => wp_kses_post($_POST['content'] ?? ''),
            'authors_text' => sanitize_text_field($_POST['authors_text'] ?? ''),
            'keywords' => array_map('sanitize_text_field', (array) ($_POST['keywords'] ?? array())),
            'coauthors' => $_POST['coauthors'] ?? array(),
            'language' => sanitize_text_field($_POST['language'] ?? 'pt'),
            'cultura' => sanitize_text_field($_POST['cultura'] ?? ''),
            'knowledge_area' => sanitize_text_field($_POST['knowledge_area'] ?? ''),
            'presenting_author' => sanitize_text_field($_POST['presenting_author'] ?? 'main'),
            'main_author_instituicao' => sanitize_text_field($_POST['main_author_instituicao'] ?? ''),
            'main_author_cpf' => sanitize_text_field($_POST['main_author_cpf'] ?? ''),
            'main_author_email' => sanitize_email($_POST['main_author_email'] ?? ''),
            'main_author_telefone' => sanitize_text_field($_POST['main_author_telefone'] ?? ''),
            'is_draft' => !empty($_POST['is_draft']),
            'post_id' => !empty($_POST['post_id']) ? absint($_POST['post_id']) : 0,
            'acknowledgement' => sanitize_textarea_field($_POST['acknowledgement'] ?? ''),
        );
        $result = $this->submission->create($data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $is_draft = !empty($_POST['is_draft']);
        wp_send_json_success(array(
            'message' => $is_draft ? __('Rascunho salvo com sucesso!', 'sciflow-wp') : __('Trabalho submetido com sucesso! Redirecionando...', 'sciflow-wp'),
            'post_id' => $result,
            'is_draft' => $is_draft,
            'redirect_url' => home_url('/meus-artigos/')
        ));
    }

    public function handle_submit_speaker_talk()
    {
        if (!check_ajax_referer('sciflow_speaker_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sessão expirada. Recarregue a página.', 'sciflow-wp')));
        }

        if (!is_user_logged_in() || (!current_user_can('sciflow_speaker') && !current_user_can('manage_sciflow'))) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'sciflow-wp')));
        }

        $event = sanitize_text_field($_POST['event'] ?? '');
        $title = SciFlow_Status_Manager::sanitize_title($_POST['title'] ?? '');

        if (empty($title)) {
            wp_send_json_error(array('message' => __('O título é obrigatório.', 'sciflow-wp')));
        }

        if (empty($_FILES['speaker_file']) || $_FILES['speaker_file']['error'] === UPLOAD_ERR_NO_FILE) {
            wp_send_json_error(array('message' => __('O arquivo da palestra é obrigatório.', 'sciflow-wp')));
        }

        $file = $_FILES['speaker_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => sprintf(__('Erro no upload do arquivo (Erro %s).', 'sciflow-wp'), $file['error'])));
        }

        if ($file['size'] > 52428800) {
            wp_send_json_error(array('message' => __('O arquivo excede o limite de 50 MB.', 'sciflow-wp')));
        }

        // Validate file type (Word)
        $allowed_mimes = array(
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
        $file_info = wp_check_filetype(basename($file['name']), $allowed_mimes);

        if (!$file_info['ext']) {
            wp_send_json_error(array('message' => __('Apenas arquivos Word (.doc, .docx) são aceitos.', 'sciflow-wp')));
        }

        // Link blocking only applies to title.
        $regex = '/(https?:\/\/[^\s]+|www\.[^\s]+)/i';
        if (preg_match($regex, $title)) {
            wp_send_json_error(array('message' => __('O título não pode conter links/URLs.', 'sciflow-wp')));
        }

        $post_data = array(
            'post_title'   => $title,
            'post_content' => '', // Content is now in the attachment
            'post_type'    => 'sciflow_palestra',
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id()
        );

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => __('Erro ao salvar a palestra.', 'sciflow-wp')));
        }

        // Handle File Upload
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('wp_insert_attachment')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $upload = wp_handle_upload($file, array('test_form' => false));

        if (isset($upload['error'])) {
            wp_delete_post($post_id, true);
            wp_send_json_error(array('message' => $upload['error']));
        }

        // Create attachment
        $attachment_id = wp_insert_attachment(array(
            'post_title'     => sanitize_file_name($file['name']),
            'post_mime_type' => $upload['type'],
            'post_status'    => 'inherit',
        ), $upload['file'], $post_id);

        if (is_wp_error($attachment_id) || !$attachment_id) {
            wp_delete_post($post_id, true);
            $err_msg = is_wp_error($attachment_id) ? $attachment_id->get_error_message() : __('Erro desconhecido ao criar anexo.', 'sciflow-wp');
            wp_send_json_error(array('message' => __('Erro ao processar o anexo: ', 'sciflow-wp') . $err_msg));
        }

        // Update meta
        update_post_meta($post_id, '_sciflow_attachment_id', $attachment_id);

        if ($event) {
            update_post_meta($post_id, '_sciflow_event', $event);
        }

        $duration = sanitize_text_field($_POST['duration'] ?? '40');
        update_post_meta($post_id, '_sciflow_duration', $duration);

        // Generate and assign visual ID immediately
        SciFlow_Status_Manager::get_visual_id($post_id);

        wp_send_json_success(array(
            'message' => __('Palestra enviada com sucesso.', 'sciflow-wp'),
            'post_id' => $post_id
        ));
    }

    /**
     * Handle resubmission.
     */
    public function handle_resubmit()
    {
        $this->verify_request();

        $post_id = absint($_POST['post_id'] ?? 0);
        
        $current_time = current_time('timestamp');
        $settings = get_option('sciflow_settings', array());
        $deadline_str = $settings['corrections_deadline'] ?? '';
        
        if (!empty($deadline_str)) {
            $deadline_time = strtotime($deadline_str);
            if ($current_time > $deadline_time) {
                $status_manager = new SciFlow_Status_Manager();
                $status = $status_manager->get_status($post_id);
                $event = get_post_meta($post_id, '_sciflow_event', true);
                if ($status === 'em_correcao' && in_array(strtolower($event), array('enfrute', 'semco', 'senco'), true)) {
                    wp_send_json_error(array('message' => __('O prazo para envio de correções foi encerrado.', 'sciflow-wp')));
                }
            }
        }

        $data = array(
            'title' => SciFlow_Status_Manager::sanitize_title($_POST['title'] ?? ''),
            'content' => wp_kses_post($_POST['content'] ?? ''),
            'keywords' => array_map('sanitize_text_field', (array) ($_POST['keywords'] ?? array())),
            'coauthors' => $_POST['coauthors'] ?? array(),
            'event' => sanitize_text_field($_POST['event'] ?? ''),
            'language' => sanitize_text_field($_POST['language'] ?? 'pt'),
            'cultura' => sanitize_text_field($_POST['cultura'] ?? ''),
            'knowledge_area' => sanitize_text_field($_POST['knowledge_area'] ?? ''),
            'presenting_author' => sanitize_text_field($_POST['presenting_author'] ?? 'main'),
            'main_author_instituicao' => sanitize_text_field($_POST['main_author_instituicao'] ?? ''),
            'main_author_cpf' => sanitize_text_field($_POST['main_author_cpf'] ?? ''),
            'main_author_email' => sanitize_email($_POST['main_author_email'] ?? ''),
            'main_author_telefone' => sanitize_text_field($_POST['main_author_telefone'] ?? ''),
            'acknowledgement' => sanitize_textarea_field($_POST['acknowledgement'] ?? ''),
        );

        $result = $this->submission->resubmit($post_id, $data);

        if (is_wp_error($result)) {
            error_log('SciFlow Resubmit Error: ' . $result->get_error_message());
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // If the author left a message for the editor, record it in message history.
        $author_message = wp_kses_post(trim($_POST['author_message'] ?? ''));
        if (!empty($author_message)) {
            $this->editorial->add_message($post_id, 'autor', $author_message);
        }

        error_log('SciFlow Resubmit Success. Post ID: ' . $post_id);
        wp_send_json_success(array(
            'message' => __('Trabalho reenviado com sucesso! Redirecionando...', 'sciflow-wp'),
            'redirect_url' => home_url('/meus-artigos/')
        ));
    }

    /**
     * Handle review submission.
     */
    public function handle_submit_review()
    {
        $this->verify_request();

        $post_id = absint($_POST['post_id'] ?? 0);

        $raw_scores = $_POST['scores'] ?? array();
        $sanitized_scores = array();
        if (is_array($raw_scores)) {
            foreach ($raw_scores as $key => $val) {
                if (is_string($val)) {
                    $val = str_replace(',', '.', $val);
                }
                $sanitized_scores[sanitize_key($key)] = floatval($val);
            }
        }

        $data = array(
            'scores' => $sanitized_scores,
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

        // Notify the author that their poster was received.
        $email = new SciFlow_Email();
        $email->send_poster_submitted($post_id);

        wp_send_json_success(array('message' => __('Pôster enviado com sucesso!', 'sciflow-wp')));
    }

    /**
     * Handle poster editorial decision.
     */
    public function handle_poster_decision()
    {
        $this->verify_request();

        if (!current_user_can('manage_sciflow')) {
            wp_send_json_error(array('message' => __('Permissão insuficiente.', 'sciflow-wp')));
        }

        $post_id  = absint($_POST['post_id'] ?? 0);
        $decision = sanitize_text_field($_POST['decision'] ?? '');
        $notes    = wp_kses_post($_POST['notes'] ?? '');

        $result = $this->editorial->make_poster_decision($post_id, $decision, $notes);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Decisão do pôster registrada com sucesso!', 'sciflow-wp')));
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
     * Handle manual payment confirmation by an admin/editor.
     */
    public function handle_confirm_payment_admin()
    {
        $this->verify_request();

        if (!current_user_can('administrator')) {
            wp_send_json_error(array('message' => __('Permissão insuficiente. Somente administradores podem confirmar pagamentos manualmente.', 'sciflow-wp')));
        }

        $post_id = absint($_POST['post_id'] ?? 0);
        $current_payment = get_post_meta($post_id, '_sciflow_payment_status', true);

        if ($current_payment !== 'confirmed') {
            $result = $this->submission->confirm_payment($post_id);
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
        }

        wp_send_json_success(array('message' => __('Pagamento confirmado com sucesso!', 'sciflow-wp')));
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
