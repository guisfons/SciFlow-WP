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
            'em_avaliacao' => __('Em Avaliação', 'sciflow-wp'),
            'aguardando_decisao' => __('Aguardando Parecer do Editor', 'sciflow-wp'),
            'em_correcao' => __('Em Correção', 'sciflow-wp'),
            'aprovado' => __('Aprovado', 'sciflow-wp'),
            'reprovado' => __('Reprovado', 'sciflow-wp'),
            'aprovado_com_consideracoes' => __('Aprovado com Considerações', 'sciflow-wp'),
            'apto_revisao' => __('Apto para Revisão', 'sciflow-wp'),
            'apto_publicacao' => __('Apto para Publicação', 'sciflow-wp'),
            'poster_enviado' => __('Pôster Enviado', 'sciflow-wp'),
            'aguardando_confirmacao' => __('Aguardando Confirmação', 'sciflow-wp'),
            'confirmado' => __('Confirmado', 'sciflow-wp'),
        );
    }

    /**
     * Valid transitions: current_status => array of allowed next statuses.
     */
    private function get_transitions()
    {
        return array(
            'rascunho' => array('aguardando_pagamento', 'submetido'),
            'aguardando_pagamento' => array('submetido'),
            'submetido' => array('em_avaliacao', 'apto_revisao', 'aguardando_decisao'),
            'apto_revisao' => array('em_avaliacao'),
            'em_avaliacao' => array('aguardando_decisao'),
            'aguardando_decisao' => array('em_correcao', 'aprovado', 'reprovado', 'aprovado_com_consideracoes', 'apto_revisao', 'apto_publicacao', 'em_avaliacao'),
            'aprovado_com_consideracoes' => array('submetido'), // Permite correção
            'reprovado' => array('submetido'), // Conforme solicitado: pode fazer ajustes
            'apto_publicacao' => array('aprovado'),
            'em_correcao' => array('submetido'),
            'aprovado' => array('poster_enviado', 'aguardando_confirmacao'),
            'aguardando_confirmacao' => array('confirmado'),
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
            'em_avaliacao' => 'info',
            'aguardando_decisao' => 'warning',
            'em_correcao' => 'warning',
            'aprovado' => 'success',
            'reprovado' => 'danger',
            'aprovado_com_consideracoes' => 'success',
            'apto_revisao' => 'primary',
            'apto_publicacao' => 'success',
            'poster_enviado' => 'success',
            'aguardando_confirmacao' => 'warning',
            'confirmado' => 'success',
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
            'senco' => 'senco_trabalhos',
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
            'senco_trabalhos' => 'senco',
        );
        return isset($map[$post_type]) ? $map[$post_type] : false;
    }
}
