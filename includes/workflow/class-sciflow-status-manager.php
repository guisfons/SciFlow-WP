<?php
/**
 * Status lifecycle manager.
 *
 * Valid statuses:
 *  rascunho, aguardando_pagamento, submetido, em_avaliacao,
 *  aguardando_decisao, em_correcao, aprovado, reprovado,
 *  poster_enviado, aguardando_confirmacao, confirmado
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Status_Manager
{

    /**
     * All valid statuses with labels.
     */
    public function get_statuses()
    {
        return array(
            'rascunho' => __('Rascunho', 'sciflow-wp'),
            'aguardando_pagamento' => __('Aguardando Pagamento', 'sciflow-wp'),
            'submetido' => __('Submetido', 'sciflow-wp'),
            'submetido_com_revisao' => __('SUBMETIDO COM ALTERAÇÕES', 'sciflow-wp'),
            'em_avaliacao' => __('Em Avaliação', 'sciflow-wp'),
            'aguardando_decisao' => __('Aguardando Parecer do Editor', 'sciflow-wp'),
            'em_correcao' => __('Necessita Alterações', 'sciflow-wp'),
            'aprovado' => __('Aprovado / Aguardando Pôster', 'sciflow-wp'),
            'reprovado' => __('Reprovado', 'sciflow-wp'),
            'aprovado_com_consideracoes' => __('Aprovado com Considerações', 'sciflow-wp'),
            'apto_revisao' => __('Apto para Revisão', 'sciflow-wp'),
            'apto_publicacao' => __('Aprovado / Concluído', 'sciflow-wp'),
            'poster_enviado' => __('Pôster Enviado', 'sciflow-wp'),
            'poster_aprovado' => __('Pôster Aprovado', 'sciflow-wp'),
            'poster_em_correcao' => __('Pôster em Correção', 'sciflow-wp'),
            'poster_reenviado' => __('Pôster Reenviado', 'sciflow-wp'),
            'poster_reprovado' => __('Pôster Reprovado', 'sciflow-wp'),
            'aguardando_confirmacao' => __('Aguardando Confirmação', 'sciflow-wp'),
            'confirmado' => __('Confirmado', 'sciflow-wp'),
            // Virtual display-only status (not stored in DB).
            'aguardando_poster' => __('Aguardando Envio do Pôster', 'sciflow-wp'),
        );
    }

    /**
     * Valid transitions: current_status => array of allowed next statuses.
     */
    private function get_transitions()
    {
        return array(
            'rascunho' => array('submetido'), // Draft -> Submetido
            'submetido' => array('em_avaliacao', 'em_revisao', 'aprovado', 'reprovado', 'aguardando_decisao', 'em_correcao', 'poster_enviado', 'poster_aprovado', 'poster_reprovado', 'poster_em_correcao', 'poster_reenviado'),
            'em_avaliacao' => array('aguardando_decisao', 'em_revisao', 'aprovado', 'reprovado', 'aprovado_com_consideracoes', 'poster_enviado', 'poster_aprovado', 'poster_reprovado', 'poster_em_correcao', 'poster_reenviado'),
            'aguardando_decisao' => array('em_avaliacao', 'em_revisao', 'aprovado', 'reprovado', 'aprovado_com_consideracoes', 'em_correcao', 'poster_enviado', 'poster_aprovado', 'poster_reprovado', 'poster_em_correcao', 'poster_reenviado'),
            'em_revisao' => array('aprovado', 'reprovado', 'aprovado_com_consideracoes'),
            'aprovado_com_consideracoes' => array('em_correcao'),
            'aprovado' => array('apto_publicacao', 'poster_enviado', 'poster_reprovado', 'poster_em_correcao'),
            'aguardando_poster' => array('apto_publicacao', 'poster_enviado', 'poster_reprovado', 'poster_em_correcao'),
            'reprovado' => array(),
            'apto_publicacao' => array('aprovado'),
            'em_correcao' => array('submetido', 'submetido_com_revisao', 'poster_enviado', 'poster_aprovado', 'poster_reprovado', 'poster_em_correcao', 'reprovado'),
            'submetido_com_revisao' => array('em_avaliacao', 'em_revisao', 'aprovado', 'reprovado', 'aprovado_com_consideracoes', 'aguardando_decisao', 'em_correcao'),
            'aguardando_confirmacao' => array('confirmado'),
            'poster_enviado' => array('poster_aprovado', 'poster_em_correcao', 'poster_reprovado', 'poster_enviado', 'apto_publicacao'),
            'poster_em_correcao' => array('poster_enviado', 'poster_em_correcao', 'poster_reenviado', 'poster_reprovado'),
            'poster_reenviado' => array('poster_aprovado', 'poster_em_correcao', 'poster_reprovado', 'poster_reenviado', 'apto_publicacao'),
            'poster_reprovado' => array('poster_em_correcao', 'poster_aprovado', 'poster_reprovado'),
            'poster_aprovado' => array('poster_em_correcao', 'poster_reprovado', 'poster_aprovado', 'apto_publicacao'),
        );
    }

    /**
     * Attempt a status transition.
     *
     * @return bool|WP_Error
     */
    public function transition($post_id, $new_status)
    {
        $current = $this->get_status($post_id);
        $allowed = $this->get_transitions();

        if (!isset($allowed[$current]) || !in_array($new_status, $allowed[$current], true)) {
            return new WP_Error(
                'invalid_transition',
                sprintf(
                __('Transição inválida: %s → %s', 'sciflow-wp'),
                $current,
                $new_status
            )
                );
        }

        update_post_meta($post_id, '_sciflow_status', $new_status);

        /**
         * Fires after a status transition.
         *
         * @param int    $post_id    Post ID.
         * @param string $new_status New status.
         * @param string $old_status Previous status.
         */
        do_action('sciflow_status_changed', $post_id, $new_status, $current);

        return true;
    }

    /**
     * Get current status.
     */
    public function get_status($post_id)
    {
        $status = get_post_meta($post_id, '_sciflow_status', true);
        return $status ? $status : 'rascunho';
    }

    /**
     * Get human-readable label for a status.
     */
    public function get_status_label($status)
    {
        $statuses = $this->get_statuses();
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }

    /**
     * Get Bootstrap color class for a status.
     */
    public function get_status_color($status)
    {
        $colors = array(
            'rascunho' => 'secondary',
            'aguardando_pagamento' => 'warning',
            'submetido' => 'primary',
            'submetido_com_revisao' => 'primary',
            'em_avaliacao' => 'info',
            'aguardando_decisao' => 'warning',
            'em_correcao' => 'warning',
            'aprovado' => 'success',
            'reprovado' => 'danger',
            'aprovado_com_consideracoes' => 'success',
            'apto_revisao' => 'primary',
            'apto_publicacao' => 'success',
            'poster_enviado' => 'info',
            'poster_reenviado' => 'info',
            'poster_aprovado' => 'success',
            'poster_em_correcao' => 'warning',
            'poster_reprovado' => 'danger',
            'aguardando_confirmacao' => 'warning',
            'confirmado' => 'success',
            // Virtual display-only.
            'aguardando_poster' => 'warning',
        );

        return isset($colors[$status]) ? $colors[$status] : 'secondary';
    }

    /**
     * Get HTML badge for a status.
     */
    public function get_status_badge($status)
    {
        $label = $this->get_status_label($status);
        $color = $this->get_status_color($status);

        return sprintf(
            '<span class="badge bg-%s text-white rounded-pill px-3 py-2">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }

    /**
     * Get the post type for a given event.
     */
    public static function get_post_type_for_event($event)
    {
        $map = array(
            'enfrute' => 'enfrute_trabalhos',
            'semco' => 'semco_trabalhos',
        );
        return isset($map[$event]) ? $map[$event] : false;
    }

    /**
     * Get the event from a post type.
     */
    public static function get_event_from_post_type($post_type)
    {
        $map = array(
            'enfrute_trabalhos' => 'enfrute',
            'semco_trabalhos' => 'semco',
        );
        return isset($map[$post_type]) ? $map[$post_type] : false;
    }

    /**
     * Get or generate a visual sequential ID for an article.
     * The ID is per-post-type (Enfrute or Semco) starting from 1.
     */
    public static function get_visual_id($post_id)
    {
        $visual_id = get_post_meta($post_id, '_sciflow_visual_id', true);
        if ($visual_id) {
            return $visual_id;
        }

        $post_type = get_post_type($post_id);
        if (!in_array($post_type, array('enfrute_trabalhos', 'semco_trabalhos', 'sciflow_palestra'))) {
            return '';
        }

        global $wpdb;
        // Find the highest visual ID for this post type
        $max_id = $wpdb->get_var($wpdb->prepare("
            SELECT MAX(CAST(m.meta_value AS UNSIGNED))
            FROM {$wpdb->postmeta} m
            INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
            WHERE m.meta_key = '_sciflow_visual_id'
            AND p.post_type = %s
        ", $post_type));

        $visual_id = intval($max_id) + 1;
        update_post_meta($post_id, '_sciflow_visual_id', $visual_id);

        return $visual_id;
    }

    /**
     * Sanitize title with italic support.
     */
    public static function sanitize_title($title)
    {
        return wp_kses($title, array(
            'i'  => array(),
            'em' => array(),
        ));
    }

    /**
     * Render title with italic support.
     */
    public static function render_title($title)
    {
        return self::sanitize_title($title);
    }

    /**
     * Check corrections deadlines and reject overdue papers.
     */
    public function check_corrections_deadlines()
    {
        $settings = get_option('sciflow_settings', array());
        $deadline_str = $settings['corrections_deadline'] ?? '';

        if (empty($deadline_str)) {
            return;
        }

        $deadline_time = strtotime($deadline_str);
        if (current_time('timestamp') <= $deadline_time) {
            return;
        }

        foreach (array('enfrute_trabalhos', 'semco_trabalhos') as $pt) {
            $query = new WP_Query(array(
                'post_type'      => $pt,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'meta_query'     => array(
                    array(
                        'key'   => '_sciflow_status',
                        'value' => 'em_correcao',
                    ),
                ),
            ));

            if (!empty($query->posts)) {
                if (!class_exists('SciFlow_Email')) {
                    require_once SCIFLOW_PATH . 'includes/email/class-sciflow-email.php';
                }
                if (!class_exists('SciFlow_Editorial')) {
                    require_once SCIFLOW_PATH . 'includes/workflow/class-sciflow-editorial.php';
                }
                $email = new SciFlow_Email();
                $editorial = new SciFlow_Editorial($this, $email);

                foreach ($query->posts as $post) {
                    $result = $this->transition($post->ID, 'reprovado');
                    if (!is_wp_error($result)) {
                        $editorial->add_message($post->ID, 'admin', __('Trabalho rejeitado por não cumprir o prazo de revisão.', 'sciflow-wp'));
                    }
                }
            }
        }
    }

    /**
     * Check poster deadlines and reject overdue posters.
     */
    public function check_poster_deadlines()
    {
        $settings = get_option('sciflow_settings', array());
        $deadline_str = $settings['poster_submission_deadline'] ?? '';

        if (empty($deadline_str)) {
            return;
        }

        $deadline_time = strtotime($deadline_str);
        if (current_time('timestamp') <= $deadline_time) {
            return;
        }

        foreach (array('enfrute_trabalhos', 'semco_trabalhos') as $pt) {
            $query = new WP_Query(array(
                'post_type'      => $pt,
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'meta_query'     => array(
                    array(
                        'key'     => '_sciflow_status',
                        'value'   => array('aprovado', 'poster_em_correcao'),
                        'compare' => 'IN'
                    ),
                ),
            ));

            if (!empty($query->posts)) {
                if (!class_exists('SciFlow_Email')) {
                    require_once SCIFLOW_PATH . 'includes/email/class-sciflow-email.php';
                }
                if (!class_exists('SciFlow_Editorial')) {
                    require_once SCIFLOW_PATH . 'includes/workflow/class-sciflow-editorial.php';
                }
                $email = new SciFlow_Email();
                $editorial = new SciFlow_Editorial($this, $email);

                foreach ($query->posts as $post) {
                    $result = $this->transition($post->ID, 'poster_reprovado');
                    if (!is_wp_error($result)) {
                        $editorial->add_message($post->ID, 'admin', __('Pôster rejeitado por não cumprir o prazo de envio/revisão.', 'sciflow-wp'));
                    }
                }
            }
        }
    }
}