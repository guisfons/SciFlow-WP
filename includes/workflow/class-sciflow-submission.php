<?php
/**
 * Handles article submission logic.
 */

if (!defined('ABSPATH')) {
    exit;
}

#[AllowDynamicProperties]
class SciFlow_Submission
{

    private $status_manager;
    private $email;
    private $woocommerce;

    public function __construct(SciFlow_Status_Manager $status_manager, SciFlow_Email $email, SciFlow_WooCommerce $woocommerce = null)
    {
        $this->status_manager = $status_manager;
        $this->email = $email;
        $this->woocommerce = $woocommerce;
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

        // Check for paid registration if WooCommerce is active and not a draft.
        $is_draft = !empty($data['is_draft']);
        if (!$is_draft && $this->woocommerce && !$this->woocommerce->has_paid_registration($user_id)) {
            return new WP_Error('payment_required', __('Você precisa ter uma inscrição paga para submeter um trabalho.', 'sciflow-wp'));
        }

        // Validate event.
        $event = sanitize_text_field($data['event'] ?? '');
        $post_type = SciFlow_Status_Manager::get_post_type_for_event($event);
        if (!$post_type) {
            return new WP_Error('invalid_event', __('Evento inválido.', 'sciflow-wp'));
        }

        // Max 2 submissions per author per event.
        if (empty($data['post_id']) && $this->count_submissions_by_author($user_id, $event) >= 2) {
            return new WP_Error('max_submissions', sprintf(__('Limite de 2 resumos para o evento %s atingido.', 'sciflow-wp'), ucfirst($event)));
        }

        $is_draft = !empty($data['is_draft']);

        // Validate content length (3000-4000 chars). Skip if it's a draft.
        $title = sanitize_text_field($data['title'] ?? '');
        $content = wp_kses_post($data['content'] ?? '');

        if (!$is_draft) {
            // Title limit.
            if (mb_strlen($title) > 180) {
                return new WP_Error('title_limit', __('O título deve ter no máximo 180 caracteres.', 'sciflow-wp'));
            }

            // Combined Title + Content.
            $char_count = mb_strlen($title) + mb_strlen(trim(wp_strip_all_tags($content)));

            if ($char_count < 3000 || $char_count > 4000) {
                return new WP_Error(
                    'char_limit',
                    sprintf(
                        __('O título + resumo deve ter entre 3.000 e 4.000 caracteres (atual: %d).', 'sciflow-wp'),
                        $char_count
                    )
                );
            }

            // Validate keywords (3-5) and check for duplicates.
            $keywords = array_filter(array_map('sanitize_text_field', (array) ($data['keywords'] ?? array())));
            if (count($keywords) < 3 || count($keywords) > 5) {
                return new WP_Error('keywords', __('Informe de 3 a 5 palavras-chave.', 'sciflow-wp'));
            }
            
            $unique_keywords = array_unique(array_map('mb_strtolower', $keywords));
            if (count($unique_keywords) !== count($keywords)) {
                return new WP_Error('duplicate_keywords', __('As palavras-chave não podem ser repetidas.', 'sciflow-wp'));
            }
        } else {
            $keywords = array_filter(array_map('sanitize_text_field', (array) ($data['keywords'] ?? array())));
        }

        // Validate co-authors (max 8 on the form, max 6 total authors including main).
        $coauthors = $this->sanitize_coauthors($data['coauthors'] ?? array());
        $total_authors = 1 + count($coauthors); // main + co-authors.
        if ($total_authors > 6) {
            return new WP_Error('max_authors', __('Máximo de 6 autores por resumo.', 'sciflow-wp'));
        }

        // Link blocking check.
        $fields_to_check = array(
            'title' => $title,
            'content' => $content,
            'authors_text' => $data['authors_text'] ?? '',
            'instituicao' => $data['main_author_instituicao'] ?? '',
        );

        foreach ($fields_to_check as $field => $val) {
            if ($this->contains_links($val)) {
                return new WP_Error('no_links', sprintf(__('O campo %s não pode conter links/URLs.', 'sciflow-wp'), $field));
            }
        }

        foreach ($keywords as $kw) {
            if ($this->contains_links($kw)) {
                return new WP_Error('no_links', __('Palavras-chave não podem conter links/URLs.', 'sciflow-wp'));
            }
        }

        foreach ($coauthors as $ca) {
            if ($this->contains_links($ca['name']) || $this->contains_links($ca['institution'])) {
                return new WP_Error('no_links', __('Dados de coautores não podem conter links/URLs.', 'sciflow-wp'));
            }
        }

        // Validate language.
        $language = sanitize_text_field($data['language'] ?? 'pt');
        if (!in_array($language, array('pt', 'en', 'es'), true)) {
            $language = 'pt';
        }

        // Create or Update the post.
        $post_data = array(
            'post_type' => $post_type,
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $is_draft ? 'draft' : 'publish',
            'post_author' => $user_id,
        );

        if (!empty($data['post_id'])) {
            $existing_post = get_post($data['post_id']);
            if (!$existing_post || (int) $existing_post->post_author !== (int) $user_id) {
                return new WP_Error('invalid_post', __('Você não tem permissão para editar este trabalho.', 'sciflow-wp'));
            }

            // ONLY permit updates if the status is 'rascunho' or in a correction phase.
            $current_status = get_post_meta($data['post_id'], '_sciflow_status', true);
            $allowed_edit_statuses = array('rascunho', 'em_correcao', 'aprovado_com_consideracoes', 'reprovado');
            if (!in_array($current_status, $allowed_edit_statuses, true)) {
                return new WP_Error('already_submitted', __('Este trabalho já foi submetido e não pode ser editado.', 'sciflow-wp'));
            }

            // If it was in correction, we'll notify the editor after update.
            $was_in_correction = in_array($current_status, array('em_correcao', 'aprovado_com_consideracoes', 'reprovado'), true);

            $post_data['ID'] = $data['post_id'];
            $post_id = wp_update_post($post_data, true);
        } else {
            $post_id = wp_insert_post($post_data, true);
        }



        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if ($is_draft) {
            if (empty($was_in_correction)) {
                update_post_meta($post_id, '_sciflow_status', 'rascunho');
            }
        } else {
            // If it was in correction, transition to 'submetido_com_revisao' (Submetido com alterações)
            if (!empty($was_in_correction)) {
                update_post_meta($post_id, '_sciflow_status', 'submetido_com_revisao');
            } else {
                update_post_meta($post_id, '_sciflow_status', 'submetido');
            }
            // Notify editor on new submission (if not a draft).
            $this->email->send_new_submission($post_id, $event);
            $this->email->send_submission_confirmation($post_id);
        }

        update_post_meta($post_id, '_sciflow_event', $event);

        update_post_meta($post_id, '_sciflow_author_id', $user_id);
        update_post_meta($post_id, '_sciflow_main_author_name', sanitize_text_field($data['authors_text'] ?? ''));
        update_post_meta($post_id, '_sciflow_main_author_instituicao', sanitize_text_field($data['main_author_instituicao'] ?? ''));
        
        $cpf = preg_replace('/[^0-9.-]/', '', $data['main_author_cpf'] ?? '');
        update_post_meta($post_id, '_sciflow_main_author_cpf', $cpf);
        
        update_post_meta($post_id, '_sciflow_main_author_email', sanitize_email($data['main_author_email'] ?? ''));
        
        $phone = preg_replace('/[^0-9() -]/', '', $data['main_author_telefone'] ?? '');
        update_post_meta($post_id, '_sciflow_main_author_telefone', $phone);
        update_post_meta($post_id, '_sciflow_coauthors', $coauthors);

        $presenting_author = sanitize_text_field($data['presenting_author'] ?? 'main');
        update_post_meta($post_id, '_sciflow_presenting_author', $presenting_author);

        $cultura = sanitize_text_field($data['cultura'] ?? '');
        $knowledge_area = sanitize_text_field($data['knowledge_area'] ?? '');
        update_post_meta($post_id, '_sciflow_cultura', $cultura);
        update_post_meta($post_id, '_sciflow_knowledge_area', $knowledge_area);

        update_post_meta($post_id, '_sciflow_keywords', $keywords);
        update_post_meta($post_id, '_sciflow_language', $language);
        update_post_meta($post_id, '_sciflow_payment_status', 'confirmed');

        // If resubmitting from correction, add a system message.
        if (!empty($was_in_correction) && !$is_draft) {
            $editorial = new SciFlow_Editorial($this->status_manager, $this->email);
            $editorial->add_message($post_id, 'autor', __('O autor enviou as alterações solicitadas.', 'sciflow-wp'));
            // Note: send_new_submission was already called above if not a draft.
        }

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
     * Author resubmits after alterations.
     */
    public function resubmit($post_id, $data)
    {
        $user_id = get_current_user_id();
        $author = get_post_meta($post_id, '_sciflow_author_id', true);

        if ((int) $user_id !== (int) $author) {
            return new WP_Error('unauthorized', __('Apenas o autor pode reenviar.', 'sciflow-wp'));
        }

        $current_status = $this->status_manager->get_status($post_id);
        $allowed_edit_statuses = array('em_correcao', 'aprovado_com_consideracoes', 'reprovado');
        if (!in_array($current_status, $allowed_edit_statuses, true)) {
            return new WP_Error('invalid_status', __('O trabalho não pode ser editado no status atual.', 'sciflow-wp'));
        }

        // Update content.
        $title = sanitize_text_field($data['title'] ?? '');
        $content = wp_kses_post($data['content'] ?? '');

        // Re-validate limits on resubmit as well
        if (mb_strlen($title) > 180) {
            return new WP_Error('title_limit', __('O título deve ter no máximo 180 caracteres.', 'sciflow-wp'));
        }
        $char_count = mb_strlen($title) + mb_strlen(trim(wp_strip_all_tags($content)));
        if ($char_count < 3000 || $char_count > 4000) {
            return new WP_Error('char_limit', sprintf(__('O título + resumo deve ter entre 3.000 e 4.000 caracteres (atual: %d).', 'sciflow-wp'), $char_count));
        }

        // Link blocking check.
        if ($this->contains_links($title) || $this->contains_links($content)) {
            return new WP_Error('no_links', __('Título ou Resumo não podem conter links/URLs.', 'sciflow-wp'));
        }

        if ($this->contains_links($data['authors_text'] ?? '') || $this->contains_links($data['main_author_instituicao'] ?? '')) {
            return new WP_Error('no_links', __('Dados do autor não podem conter links/URLs.', 'sciflow-wp'));
        }

        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => $title,
            'post_content' => $content,
        ));

        // Update keywords if provided.
        if (!empty($data['keywords'])) {
            $keywords = array_filter(array_map('sanitize_text_field', (array) $data['keywords']));
            
            // Check for duplicates
            $unique_keywords = array_unique(array_map('mb_strtolower', $keywords));
            if (count($unique_keywords) !== count($keywords)) {
                return new WP_Error('duplicate_keywords', __('As palavras-chave não podem ser repetidas.', 'sciflow-wp'));
            }
            
            update_post_meta($post_id, '_sciflow_keywords', $keywords);
        }

        if (isset($data['authors_text'])) {
            update_post_meta($post_id, '_sciflow_main_author_name', sanitize_text_field($data['authors_text']));
        }

        update_post_meta($post_id, '_sciflow_main_author_instituicao', sanitize_text_field($data['main_author_instituicao'] ?? ''));
        
        $cpf = preg_replace('/[^0-9.-]/', '', $data['main_author_cpf'] ?? '');
        update_post_meta($post_id, '_sciflow_main_author_cpf', $cpf);
        
        update_post_meta($post_id, '_sciflow_main_author_email', sanitize_email($data['main_author_email'] ?? ''));
        
        $phone = preg_replace('/[^0-9() -]/', '', $data['main_author_telefone'] ?? '');
        update_post_meta($post_id, '_sciflow_main_author_telefone', $phone);

        // Update co-authors if provided.
        if (isset($data['coauthors'])) {
            $coauthors = $this->sanitize_coauthors($data['coauthors']);
            
            foreach ($coauthors as $ca) {
                if ($this->contains_links($ca['name']) || $this->contains_links($ca['institution'])) {
                    return new WP_Error('no_links', __('Dados de coautores não podem conter links/URLs.', 'sciflow-wp'));
                }
            }
            
            update_post_meta($post_id, '_sciflow_coauthors', $coauthors);
        }

        if (isset($data['presenting_author'])) {
            $presenting_author = sanitize_text_field($data['presenting_author']);
            update_post_meta($post_id, '_sciflow_presenting_author', $presenting_author);
        }

        if (isset($data['cultura'])) {
            update_post_meta($post_id, '_sciflow_cultura', sanitize_text_field($data['cultura']));
        }

        if (isset($data['knowledge_area'])) {
            update_post_meta($post_id, '_sciflow_knowledge_area', sanitize_text_field($data['knowledge_area']));
        }

        if (isset($data['event'])) {
            update_post_meta($post_id, '_sciflow_event', sanitize_text_field($data['event']));
        }

        if (isset($data['language'])) {
            update_post_meta($post_id, '_sciflow_language', sanitize_text_field($data['language']));
        }

        // Transition to the new submetido com revisao status.
        $result = $this->status_manager->transition($post_id, 'submetido_com_revisao');

        if (!is_wp_error($result)) {
            $editorial = new SciFlow_Editorial($this->status_manager, $this->email);
            $editorial->add_message($post_id, 'autor', __('O autor enviou as alterações solicitadas.', 'sciflow-wp'));

            $event = get_post_meta($post_id, '_sciflow_event', true);
            $this->email->send_new_submission($post_id, $event);
            $this->email->send_submission_confirmation($post_id);
        }

        return $result;
    }

    /**
     * Count submissions by a specific author for a specific event.
     */
    private function count_submissions_by_author($user_id, $event = null)
    {
        $count = 0;
        $post_types = $event ? array(SciFlow_Status_Manager::get_post_type_for_event($event)) : array('enfrute_trabalhos', 'semco_trabalhos');

        foreach ($post_types as $pt) {
            if (!$pt)
                continue;
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
        foreach (array('enfrute_trabalhos', 'semco_trabalhos') as $pt) {
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
    private function sanitize_coauthors($raw, $is_draft = false)
    {
        $clean = array();
        if (!is_array($raw)) {
            return $clean;
        }

        foreach ($raw as $i => $author) {
            if ($i >= 8)
                break; // Max 8 co-authors.

            $has_any_data = !empty($author['name']) || !empty($author['email']) || !empty($author['institution']) || !empty($author['telefone']);

            if ($has_any_data) {
                $clean[] = array(
                    'name' => sanitize_text_field($author['name'] ?? ''),
                    'email' => sanitize_email($author['email'] ?? ''),
                    'institution' => sanitize_text_field($author['institution'] ?? ''),
                    'telefone' => preg_replace('/[^0-9() -]/', '', $author['telefone'] ?? ''),
                );
            }
        }

        return $clean;
    }

    /**
     * Check if a text contains URLs/links.
     */
    private function contains_links($text)
    {
        if (empty($text)) {
            return false;
        }
        // Basic check for http, https or www.
        return preg_match('/(https?:\/\/[^\s]+|www\.[^\s]+)/i', $text);
    }
}
