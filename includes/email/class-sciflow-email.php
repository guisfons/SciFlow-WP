<?php
/**
 * Email notification system.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Email
{

    /**
     * Get the editor email for a given event.
     */
    private function get_editor_email($event)
    {
        $settings = get_option('sciflow_settings', array());
        $key = $event . '_editor_email';
        $raw = $settings[$key] ?? get_option('admin_email');

        // Support multiple emails separated by comma.
        $emails = explode(',', $raw);
        $emails = array_map('trim', $emails);
        return array_filter($emails);
    }

    /**
     * Get event label.
     */
    private function get_event_label($event)
    {
        $labels = array(
            'enfrute' => 'Enfrute — Congresso Nacional',
            'senco' => 'Senco — Seminário Catarinense de Olericultura',
        );
        return $labels[$event] ?? $event;
    }

    /**
     * Get the dashboard URL.
     */
    private function get_dashboard_url()
    {
        $settings = get_option('sciflow_settings', array());
        return $settings['dashboard_url'] ?? home_url();
    }

    /**
     * Build common template variables.
     */
    private function get_template_vars($post_id)
    {
        $post = get_post($post_id);
        $event = get_post_meta($post_id, '_sciflow_event', true);
        $status = get_post_meta($post_id, '_sciflow_status', true);
        $sm = new SciFlow_Status_Manager();

        return array(
            'titulo' => $post->post_title,
            'evento' => $this->get_event_label($event),
            'status' => $sm->get_status_label($status),
            'link' => $this->get_dashboard_url(),
            'site_name' => get_bloginfo('name'),
        );
    }

    /**
     * Send an email.
     */
    private function send($to, $subject, $template, $vars)
    {
        $html = $this->render_template($template, $vars);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        return wp_mail($to, $subject, $html, $headers);
    }

    /**
     * Render an email template.
     */
    private function render_template($template_name, $vars)
    {
        $template_path = SCIFLOW_PATH . 'public/templates/email/' . $template_name . '.php';

        if (!file_exists($template_path)) {
            // Fallback to simple text.
            return $this->render_fallback($vars);
        }

        extract($vars, EXTR_SKIP);
        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    /**
     * Fallback plain HTML.
     */
    private function render_fallback($vars)
    {
        $html = '<div style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px;">';
        $html .= '<h2 style="color:#2c5530;">' . esc_html($vars['site_name']) . '</h2>';
        $html .= '<p><strong>Trabalho:</strong> ' . esc_html($vars['titulo']) . '</p>';
        $html .= '<p><strong>Evento:</strong> ' . esc_html($vars['evento']) . '</p>';
        $html .= '<p><strong>Status:</strong> ' . esc_html($vars['status']) . '</p>';
        if (!empty($vars['message'])) {
            $html .= '<p>' . wp_kses_post($vars['message']) . '</p>';
        }
        $html .= '<p><a href="' . esc_url($vars['link']) . '" style="display:inline-block;padding:10px 20px;background-color:#2c5530;color:#fff;text-decoration:none;border-radius:5px;">Acessar Área Logada</a></p>';
        $html .= '</div>';
        return $html;
    }

    // ─── Specific email methods ───

    /**
     * 1. New submission → Editor.
     */
    public function send_new_submission($post_id, $event)
    {
        $vars = $this->get_template_vars($post_id);
        $vars['message'] = __('Um novo trabalho foi submetido e requer sua atenção.', 'sciflow-wp');

        $editor_email = $this->get_editor_email($event);
        $subject = sprintf(__('[%s] Novo Trabalho Submetido: %s', 'sciflow-wp'), $vars['evento'], $vars['titulo']);

        $this->send($editor_email, $subject, 'new-submission', $vars);
    }

    /**
     * 2. Reviewer assigned → Reviewer.
     */
    public function send_assigned_reviewer($post_id, $reviewer_id)
    {
        $vars = $this->get_template_vars($post_id);
        $vars['message'] = __('Você foi designado para avaliar o trabalho abaixo.', 'sciflow-wp');

        $reviewer = get_userdata($reviewer_id);
        if (!$reviewer)
            return;

        $subject = sprintf(__('[%s] Trabalho Atribuído Para Revisão: %s', 'sciflow-wp'), $vars['evento'], $vars['titulo']);

        $this->send($reviewer->user_email, $subject, 'assigned-reviewer', $vars);
    }

    /**
     * 3. Review complete → Editor.
     */
    public function send_review_complete($post_id)
    {
        $vars = $this->get_template_vars($post_id);
        $event = get_post_meta($post_id, '_sciflow_event', true);
        $vars['message'] = __('Um revisor completou a avaliação do trabalho abaixo.', 'sciflow-wp');

        $editor_email = $this->get_editor_email($event);
        $subject = sprintf(__('[%s] Revisão Concluída: %s', 'sciflow-wp'), $vars['evento'], $vars['titulo']);

        $this->send($editor_email, $subject, 'review-complete', $vars);
    }

    /**
     * 4. Editorial decision → Author.
     */
    public function send_editorial_decision($post_id, $decision, $notes)
    {
        $vars = $this->get_template_vars($post_id);
        $author_id = get_post_meta($post_id, '_sciflow_author_id', true);
        $author = get_userdata($author_id);

        if (!$author)
            return;

        $decision_labels = array(
            'approve' => __('Aprovado', 'sciflow-wp'),
            'reject' => __('Reprovado', 'sciflow-wp'),
            'return_to_author' => __('Devolvido para Correções', 'sciflow-wp'),
        );

        $vars['decision_label'] = $decision_labels[$decision] ?? $decision;
        $vars['notes'] = $notes;
        $vars['message'] = sprintf(
            __('O editor tomou uma decisão sobre seu trabalho: <strong>%s</strong>.', 'sciflow-wp'),
            $vars['decision_label']
        );

        // The editor's notes are meant for the reviewer, not the author.


        $subject = sprintf(__('[%s] Decisão Editorial: %s', 'sciflow-wp'), $vars['evento'], $vars['titulo']);

        $this->send($author->user_email, $subject, 'editorial-decision', $vars);
    }

    /**
     * 5. Approval + poster request → Author.
     */
    public function send_poster_request($post_id)
    {
        $vars = $this->get_template_vars($post_id);
        $author_id = get_post_meta($post_id, '_sciflow_author_id', true);
        $author = get_userdata($author_id);

        if (!$author)
            return;

        $vars['message'] = __('Seu trabalho foi aprovado! Por favor, envie o pôster em formato PDF.', 'sciflow-wp');

        $subject = sprintf(__('[%s] Trabalho Aprovado — Envie Seu Pôster: %s', 'sciflow-wp'), $vars['evento'], $vars['titulo']);

        $this->send($author->user_email, $subject, 'poster-request', $vars);
    }

    /**
     * 6. Confirmation needed (ranking selection) → Author.
     */
    public function send_confirmation_needed($post_id)
    {
        $vars = $this->get_template_vars($post_id);
        $author_id = get_post_meta($post_id, '_sciflow_author_id', true);
        $author = get_userdata($author_id);

        if (!$author)
            return;

        $deadline = get_post_meta($post_id, '_sciflow_confirmation_deadline', true);
        $vars['deadline'] = $deadline ? wp_date('d/m/Y H:i', strtotime($deadline)) : '---';
        $vars['message'] = sprintf(
            __('Seu trabalho foi selecionado para apresentação! Confirme até <strong>%s</strong> (3 dias).', 'sciflow-wp'),
            $vars['deadline']
        );

        $subject = sprintf(__('[%s] Confirmação de Apresentação: %s', 'sciflow-wp'), $vars['evento'], $vars['titulo']);

        $this->send($author->user_email, $subject, 'confirmation-needed', $vars);
    }
}
