<?php
/**
 * Poster PDF upload handler.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Poster_Upload
{

    private $status_manager;
    private $email;


    public function __construct(SciFlow_Status_Manager $status_manager, SciFlow_Email $email)
    {
        $this->status_manager = $status_manager;
        $this->email = $email;
    }

    /**
     * Handle poster upload.
     *
     * @param int   $post_id The article post ID.
     * @param array $file    The $_FILES entry.
     * @return int|WP_Error  Attachment ID on success.
     */
    public function upload($post_id, $file)
    {
        $is_admin = SciFlow_Security::can_manage_submission($post_id);

        if (!SciFlow_Security::is_author($post_id) && !$is_admin) {
            return new WP_Error('unauthorized', __('Apenas o autor pode enviar o pôster.', 'sciflow-wp'));
        }

        $status = $this->status_manager->get_status($post_id);
        if (!in_array($status, array('aprovado', 'poster_em_correcao', 'aguardando_poster', 'aguardando_confirmacao', 'confirmado'), true)) {
            return new WP_Error('invalid_status', __('O trabalho não está em um status que permite o envio de pôster.', 'sciflow-wp'));
        }

        // Check poster deadline.
        if (!$is_admin) {
            $settings = get_option('sciflow_settings', array());
            $poster_deadline_str = $settings['poster_submission_deadline'] ?? '';
            if (!empty($poster_deadline_str)) {
                $poster_deadline_time = strtotime(str_replace('/', '-', $poster_deadline_str));
                if ($poster_deadline_time && current_time('timestamp') > $poster_deadline_time) {
                    return new WP_Error('deadline_exceeded', __('O prazo para envio de pôsteres foi encerrado.', 'sciflow-wp'));
                }
            }
        }

        // Validate upload, size and real content type.
        $mimes = array('pdf' => 'application/pdf');
        $max_bytes = (int) SciFlow_Settings::get('poster_max_mb', 5) * MB_IN_BYTES;
        $valid = SciFlow_Security::validate_upload($file, $mimes, $max_bytes);
        if (is_wp_error($valid)) {
            return $valid;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if ('application/pdf' !== $finfo->file($file['tmp_name'])) {
            return new WP_Error('invalid_file', __('Apenas arquivos PDF são aceitos.', 'sciflow-wp'));
        }

        // Use WP upload.
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('wp_insert_attachment')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $upload = wp_handle_upload($file, array('test_form' => false, 'mimes' => $mimes));

        if (isset($upload['error'])) {
            return new WP_Error('upload_error', $upload['error']);
        }

        // Create attachment.
        $attachment_id = wp_insert_attachment(array(
            'post_title' => sanitize_file_name($file['name']),
            'post_mime_type' => $upload['type'],
            'post_status' => 'inherit',
        ), $upload['file'], $post_id);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Delete old poster if exists.
        $old_poster_id = get_post_meta($post_id, '_sciflow_poster_id', true);
        if ($old_poster_id) {
            wp_delete_attachment($old_poster_id, true);
        }

        // Save attachment reference.
        update_post_meta($post_id, '_sciflow_poster_id', $attachment_id);

        // Transition status.
        if ($status === 'poster_em_correcao') {
            $this->status_manager->transition($post_id, 'poster_reenviado');
        } elseif ($status === 'aprovado' || $status === 'aguardando_poster') {
            $this->status_manager->transition($post_id, 'poster_enviado');
        }

        return $attachment_id;
    }
}
