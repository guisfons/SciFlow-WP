<?php
/**
 * Handles article submission logic.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Submission
{

    private $status_manager;
    private $email;

    public function __construct(SciFlow_Status_Manager $status_manager, SciFlow_Email $email)
    {
        $this->status_manager = $status_manager;
        $this->email = $email;
    }

    /**
     * Process a new submission from the frontend form.
     *
     * @param array $data Sanitized form data.
     * @return int|WP_Error Post ID on success.
     */
    public function create($data)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', __('Você precisa estar logado.', 'sciflow-wp'));
        }

        // Validate event.
        $event = sanitize_text_field($data['event'] ?? '');
        $post_type = SciFlow_Status_Manager::get_post_type_for_event($event);
        if (!$post_type) {
            return new WP_Error('invalid_event', __('Evento inválido.', 'sciflow-wp'));
        }

        // Max 2 submissions per author.
        if ($this->count_submissions_by_author($user_id) >= 2) {
            return new WP_Error('max_submissions', __('Limite de 2 resumos por autor atingido.', 'sciflow-wp'));
        }

        // Validate content length (3000-4000 chars).
        $title = sanitize_text_field($data['title'] ?? '');
        $content = wp_kses_post($data['content'] ?? '');
        $full_text = $title . ' ' . wp_strip_all_tags($content);

        // Include authors in char count.
        $authors_text = sanitize_text_field($data['authors_text'] ?? '');
        $full_text .= ' ' . $authors_text;

        $char_count = mb_strlen($full_text);
        if ($char_count < 3000 || $char_count > 4000) {
            return new WP_Error(
                'char_limit',
                sprintf(
                    __('O texto deve ter entre 3.000 e 4.000 caracteres (atual: %d).', 'sciflow-wp'),
                    $char_count
                )
            );
        }

        // Validate keywords (3-5).
        $keywords = array_filter(array_map('sanitize_text_field', (array) ($data['keywords'] ?? array())));
        if (count($keywords) < 3 || count($keywords) > 5) {
            return new WP_Error('keywords', __('Informe de 3 a 5 palavras-chave.', 'sciflow-wp'));
        }

        // Validate co-authors (max 8 on the form, max 6 total authors including main).
        $coauthors = $this->sanitize_coauthors($data['coauthors'] ?? array());
        $total_authors = 1 + count($coauthors); // main + co-authors.
        if ($total_authors > 6) {
            return new WP_Error('max_authors', __('Máximo de 6 autores por resumo.', 'sciflow-wp'));
        }

        // Validate language.
        $language = sanitize_text_field($data['language'] ?? 'pt');
        if (!in_array($language, array('pt', 'en', 'es'), true)) {
            $language = 'pt';
        }

        // Create the post.
        $post_id = wp_insert_post(array(
            'post_type' => $post_type,
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_author' => $user_id,
        ), true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Save meta.
        update_post_meta($post_id, '_sciflow_status', 'aguardando_pagamento');
        update_post_meta($post_id, '_sciflow_event', $event);
        update_post_meta($post_id, '_sciflow_author_id', $user_id);
        update_post_meta($post_id, '_sciflow_coauthors', $coauthors);
        update_post_meta($post_id, '_sciflow_keywords', $keywords);
        update_post_meta($post_id, '_sciflow_language', $language);
        update_post_meta($post_id, '_sciflow_payment_status', 'pending');

        /**
         * Fires after a new submission is created.
         */
        do_action('sciflow_submission_created', $post_id, $event);

        return $post_id;
    }

    /**
     * Mark payment as confirmed and transition to "submetido".
     */
    public function confirm_payment($post_id)
    {
        update_post_meta($post_id, '_sciflow_payment_status', 'confirmed');
        $result = $this->status_manager->transition($post_id, 'submetido');

        if (!is_wp_error($result)) {
            $event = get_post_meta($post_id, '_sciflow_event', true);
            $this->email->send_new_submission($post_id, $event);
        }

        return $result;
    }

    /**
     * Author resubmits after corrections.
     */
    public function resubmit($post_id, $data)
    {
        $user_id = get_current_user_id();
        $author = get_post_meta($post_id, '_sciflow_author_id', true);

        if ((int) $user_id !== (int) $author) {
            return new WP_Error('unauthorized', __('Apenas o autor pode reenviar.', 'sciflow-wp'));
        }

        $current_status = $this->status_manager->get_status($post_id);
        if ($current_status !== 'em_correcao') {
            return new WP_Error('invalid_status', __('O trabalho não está em fase de correção.', 'sciflow-wp'));
        }

        // Update content.
        $title = sanitize_text_field($data['title'] ?? '');
        $content = wp_kses_post($data['content'] ?? '');

        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => $title,
            'post_content' => $content,
        ));

        // Update keywords if provided.
        if (!empty($data['keywords'])) {
            $keywords = array_filter(array_map('sanitize_text_field', (array) $data['keywords']));
            update_post_meta($post_id, '_sciflow_keywords', $keywords);
        }

        // Update co-authors if provided.
        if (isset($data['coauthors'])) {
            $coauthors = $this->sanitize_coauthors($data['coauthors']);
            update_post_meta($post_id, '_sciflow_coauthors', $coauthors);
        }

        // Transition back to submetido.
        $result = $this->status_manager->transition($post_id, 'submetido');

        if (!is_wp_error($result)) {
            $event = get_post_meta($post_id, '_sciflow_event', true);
            $this->email->send_new_submission($post_id, $event);
        }

        return $result;
    }

    /**
     * Count submissions by a specific author across both CPTs.
     */
    private function count_submissions_by_author($user_id)
    {
        $count = 0;
        foreach (array('enfrute_trabalhos', 'senco_trabalhos') as $pt) {
            $query = new WP_Query(array(
                'post_type' => $pt,
                'author' => $user_id,
                'posts_per_page' => -1,
                'post_status' => 'any',
                'fields' => 'ids',
            ));
            $count += $query->found_posts;
        }
        return $count;
    }

    /**
     * Get all submissions for the current user.
     */
    public function get_user_submissions($user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $posts = array();
        foreach (array('enfrute_trabalhos', 'senco_trabalhos') as $pt) {
            $query = new WP_Query(array(
                'post_type' => $pt,
                'author' => $user_id,
                'posts_per_page' => -1,
                'post_status' => 'any',
            ));
            $posts = array_merge($posts, $query->posts);
        }

        return $posts;
    }

    /**
     * Sanitize the co-authors array.
     */
    private function sanitize_coauthors($raw)
    {
        $clean = array();
        if (!is_array($raw)) {
            return $clean;
        }

        foreach ($raw as $i => $author) {
            if ($i >= 8)
                break; // Max 8 co-authors.
            $clean[] = array(
                'name' => sanitize_text_field($author['name'] ?? ''),
                'email' => sanitize_email($author['email'] ?? ''),
                'institution' => sanitize_text_field($author['institution'] ?? ''),
            );
        }

        return array_filter($clean, function ($a) {
            return !empty($a['name']);
        });
    }
}
