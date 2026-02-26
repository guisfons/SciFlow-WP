<?php
/**
 * Handles the editorial decision workflow.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Editorial
{

    private $status_manager;
    private $email;

    public function __construct(SciFlow_Status_Manager $status_manager, SciFlow_Email $email)
    {
        $this->status_manager = $status_manager;
        $this->email = $email;
    }

    /**
     * Assign a reviewer to an article.
     */
    public function assign_reviewer($post_id, $reviewer_id)
    {
        if (!current_user_can('assign_sciflow_reviewers')) {
            return new WP_Error('unauthorized', __('Permissão insuficiente.', 'sciflow-wp'));
        }

        $current = $this->status_manager->get_status($post_id);
        if (!in_array($current, array('submetido', 'apto_revisao'), true)) {
            return new WP_Error('invalid_status', __('O trabalho precisa estar com status "Submetido" ou "Apto para Revisão".', 'sciflow-wp'));
        }

        // Verify reviewer role.
        $user = get_userdata($reviewer_id);
        if (!$user) {
            return new WP_Error('invalid_user', __('Usuário não encontrado.', 'sciflow-wp'));
        }

        $allowed_roles = array('sciflow_revisor', 'sciflow_editor', 'administrator', 'sciflow_senco_revisor', 'sciflow_enfrute_revisor');
        if (!array_intersect($allowed_roles, $user->roles)) {
            return new WP_Error('not_reviewer', __('O usuário não tem papel de revisor.', 'sciflow-wp'));
        }

        update_post_meta($post_id, '_sciflow_reviewer_id', $reviewer_id);
        $result = $this->status_manager->transition($post_id, 'em_avaliacao');

        if (!is_wp_error($result)) {
            $this->email->send_assigned_reviewer($post_id, $reviewer_id);
        }

        return $result;
    }

    /**
     * Editor makes a decision on the article.
     *
     * @param string $decision 'approve', 'reject', or 'return_to_author'.
     */
    public function make_decision($post_id, $decision, $notes = '')
    {
        if (!current_user_can('manage_sciflow')) {
            return new WP_Error('unauthorized', __('Permissão insuficiente.', 'sciflow-wp'));
        }

        $current = $this->status_manager->get_status($post_id);
        if ($current !== 'aguardando_decisao') {
            return new WP_Error('invalid_status', __('O trabalho não está aguardando decisão.', 'sciflow-wp'));
        }

        if (!empty($notes)) {
            $this->add_message($post_id, 'editor', $notes);
        }

        update_post_meta($post_id, '_sciflow_editorial_notes', wp_kses_post($notes)); // Keep for BC for now

        $status_map = array(
            'approve' => 'aprovado',
            'reject' => 'reprovado',
            'return_to_author' => 'em_correcao',
            'approved_with_considerations' => 'aprovado_com_consideracoes',
            'return_to_reviewer' => 'em_avaliacao',
            'apto_revisao' => 'apto_revisao',
            'apto_publicacao' => 'apto_publicacao',
        );

        if (!isset($status_map[$decision])) {
            return new WP_Error('invalid_decision', __('Decisão inválida.', 'sciflow-wp'));
        }

        $new_status = $status_map[$decision];
        update_post_meta($post_id, '_sciflow_decision', $decision);

        $result = $this->status_manager->transition($post_id, $new_status);

        if (!is_wp_error($result)) {
            $this->email->send_editorial_decision($post_id, $decision, $notes);

            if ($decision === 'return_to_reviewer') {
                $this->email->send_returned_to_reviewer($post_id);
            }

            if ($decision === 'approve') {
                $this->email->send_poster_request($post_id);
            }
        }

        return $result;
    }

    /**
     * Get all articles for a specific event that need editorial attention.
     */
    public function get_event_articles($event, $status = null)
    {
        $post_type = SciFlow_Status_Manager::get_post_type_for_event($event);
        if (!$post_type) {
            return array();
        }

        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => 'any',
        );

        if ($status) {
            $args['meta_query'] = array(
                array(
                    'key' => '_sciflow_status',
                    'value' => $status,
                ),
            );
        }

        $query = new WP_Query($args);
        return $query->posts;
    }

    /**
     * Add a message to the history.
     */
    public function add_message($post_id, $role, $content)
    {
        $history = get_post_meta($post_id, '_sciflow_message_history', true);
        if (!is_array($history)) {
            $history = array();
        }

        $history[] = array(
            'role' => $role,
            'content' => wp_kses_post($content),
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
        );

        update_post_meta($post_id, '_sciflow_message_history', $history);
    }

    /**
     * Get message history.
     */
    public static function get_message_history($post_id)
    {
        $history = get_post_meta($post_id, '_sciflow_message_history', true);
        return is_array($history) ? $history : array();
    }

    /**
     * Get all reviewers available.
     */
    public function get_reviewers()
    {
        return get_users(array(
            'role__in' => array('sciflow_revisor', 'sciflow_editor'),
            'orderby' => 'display_name',
        ));
    }
}
