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

    /**
     * Max file size in bytes (10 MB).
     */
    private const MAX_SIZE = 10485760;

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
        $user_id = get_current_user_id();
        $author = (int) get_post_meta($post_id, '_sciflow_author_id', true);

        if ($user_id !== $author) {
            return new WP_Error('unauthorized', __('Apenas o autor pode enviar o pôster.', 'sciflow-wp'));
        }

        $status = $this->status_manager->get_status($post_id);
        if ($status !== 'aprovado' && $status !== 'poster_enviado') {
            return new WP_Error('invalid_status', __('O trabalho precisa estar aprovado.', 'sciflow-wp'));
        }

        // Validate file type.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if ($mime !== 'application/pdf') {
            return new WP_Error('invalid_file', __('Apenas arquivos PDF são aceitos.', 'sciflow-wp'));
        }

        // Validate size.
        if ($file['size'] > self::MAX_SIZE) {
            return new WP_Error('file_too_large', __('O arquivo excede o limite de 10 MB.', 'sciflow-wp'));
        }

        // Use WP upload.
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('wp_insert_attachment')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $upload = wp_handle_upload($file, array('test_form' => false));

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
        if ($status === 'aprovado') {
            $this->status_manager->transition($post_id, 'poster_enviado');
        }

        return $attachment_id;
    }
}
