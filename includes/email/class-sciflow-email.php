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
     * Get editor emails for a given event by querying users with the matching role(s).
     *
     * Roles checked:
     *   - enfrute → sciflow_enfrute_editor + sciflow_editor
     *   - semco   → sciflow_semco_editor  + sciflow_editor
     */
    private function get_editor_email($event)
    {
        // Map each event to the relevant editor roles.
        $role_map = array(
            'enfrute' => array('sciflow_enfrute_editor', 'sciflow_editor'),
            'semco' => array('sciflow_semco_editor', 'sciflow_editor'),
        );

        $roles = isset($role_map[$event]) ? $role_map[$event] : array('sciflow_editor');

        $emails = array();
        foreach ($roles as $role) {
            $users = get_users(array(
                'role' => $role,
                'fields' => array('user_email'),
            ));
            foreach ($users as $user) {
                if (!empty($user->user_email) && !in_array($user->user_email, $emails, true)) {
                    $emails[] = $user->user_email;
                }
            }
        }

        // Always include admins.
        $admins = get_users(array(
            'role' => 'administrator',
            'fields' => array('user_email'),
        ));
        foreach ($admins as $admin) {
            if (!empty($admin->user_email) && !in_array($admin->user_email, $emails, true)) {
                $emails[] = $admin->user_email;
            }
        }

        // Final fallback: use admin_email if still empty.
        if (empty($emails)) {
            $emails[] = get_option('admin_email');
        }

        return array_filter($emails);
    }

    /**
     * Get event label.
     */
    private function get_event_label($event)
    {
        $labels = array(
            'enfrute' => 'Enfrute — Encontro Nacional sobre Fruticultura de Clima Temperado',
            'semco' => 'Semco — Seminário Catarinense de Olericultura',
        );
        return $labels[$event] ?? $event;
    }

    /**
     * Get the dashboard URL.
     */
    private function get_dashboard_url($post_id = 0)
    {
        $base_url = home_url('/');
        $settings = get_option('sciflow_settings', array());

        if (!empty($settings['dashboard_url'])) {
            $base_url = $settings['dashboard_url'];
        } elseif (function_exists('wc_get_page_id')) {
            // Fallback to WooCommerce my account page if it exists
            $my_account = wc_get_page_id('myaccount');
            if ($my_account && $my_account > 0) {
                $base_url = get_permalink($my_account);
            }
        }

        // Include the article_id parameter if a post_id is provided
        if ($post_id > 0) {
            $detail_page = get_pages(array('meta_key' => '_wp_page_template', 'meta_value' => 'page-templates/template-article-detail.php'));
            if (!empty($detail_page)) {
                $base_url = get_permalink($detail_page[0]->ID);
            }
            return add_query_arg('article_id', $post_id, $base_url);
        }

        return $base_url;
    }

    /**
     * Get emails of the submitter and correspondent author.
     */
    private function get_author_recipients($post_id)
    {
        $recipients = array();

        // 1. Submitter (Inscrito/Usuário Logado no momento da submissão)
        $author_id = get_post_meta($post_id, '_sciflow_author_id', true);
        if ($author_id) {
            $author = get_userdata($author_id);
            if ($author && is_email($author->user_email)) {
                $recipients[] = $author->user_email;
            }
        }

        // 2. Autor Principal Email
        $main_email = get_post_meta($post_id, '_sciflow_main_author_email', true);
        if (is_email($main_email)) {
            $recipients[] = $main_email;
        }

        // 3. Autor Apresentador Email
        $presenting_author = get_post_meta($post_id, '_sciflow_presenting_author', true);
        if (is_numeric($presenting_author)) {
            $coauthors = get_post_meta($post_id, '_sciflow_coauthors', true);
            if (isset($coauthors[$presenting_author]['email']) && is_email($coauthors[$presenting_author]['email'])) {
                $recipients[] = $coauthors[$presenting_author]['email'];
            }
        }

        return array_unique($recipients);
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
            'link' => $this->get_dashboard_url($post_id),
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

        // Capture wp_mail errors for debugging
        $mail_error_data = null;
        $error_handler = function ($wp_error) use (&$mail_error_data) {
            $mail_error_data = $wp_error;
        };
        add_action('wp_mail_failed', $error_handler);

        $result = wp_mail($to, $subject, $html, $headers);

        remove_action('wp_mail_failed', $error_handler);

        if (!$result || $mail_error_data) {
            $to_str = is_array($to) ? implode(', ', $to) : $to;
            $error_msg = $mail_error_data ? $mail_error_data->get_error_message() : 'wp_mail returned false';
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error(
                    "SciFlow Email Failed. To: [{$to_str}] Subject: [{$subject}] Error: {$error_msg}",
                    array('source' => 'sciflow_email')
                );
            } elseif (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log("SciFlow Email Failed. To: [{$to_str}] Subject: [{$subject}] Error: {$error_msg}");
            }
        }

        return $result;
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
     * 1. New submission → All Editors.
     */
    public function send_new_submission($post_id, $event)
    {
        $vars = $this->get_template_vars($post_id);
        $vars['message'] = __('Um novo trabalho foi submetido e requer sua atenção.', 'sciflow-wp');

        $editor_emails = $this->get_editor_email($event);

        if (empty($editor_emails)) {
            return;
        }

        $subject = sprintf(__('[%s] Novo Trabalho Submetido: %s', 'sciflow-wp'), $vars['evento'], $vars['titulo']);

        $this->send($editor_emails, $subject, 'new-submission', $vars);
    }

    /**
     * Get all editor emails across all configured events.
     */
    private function get_all_editor_emails()
    {
        $all_emails = array();
        $events = array('enfrute', 'semco');
        foreach ($events as $event) {
            $emails = $this->get_editor_email($event);
            $all_emails = array_merge($all_emails, $emails);
        }
        return array_unique($all_emails);
    }

    /**
     * Confirmation email for authors.
     */
    public function send_submission_confirmation($post_id)
    {
        $vars = $this->get_template_vars($post_id);
        $recipients = $this->get_author_recipients($post_id);

        if (empty($recipients)) {
            return;
        }

        $vars['message'] = __('Recebemos sua submissão com sucesso! Você pode acompanhar o status pelo seu painel.', 'sciflow-wp');
        $subject = sprintf(__('[%s] Confirmação de Submissão: %s', 'sciflow-wp'), $vars['evento'], $vars['titulo']);

        $this->send($recipients, $subject, 'submission-confirmation', $vars);
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
        $recipients = $this->get_author_recipients($post_id);

        if (empty($recipients)) {
            return;
        }

        $decision_labels = array(
            'approve' => __('Aprovado', 'sciflow-wp'),
            'reject' => __('Reprovado', 'sciflow-wp'),
            'return_to_author' => __('Devolvido para Alterações', 'sciflow-wp'),
            'approved_with_considerations' => __('Necessita Alterações', 'sciflow-wp'),
            // Poster decisions.
            'approve_poster'     => __('Pôster Aprovado', 'sciflow-wp'),
            'reject_poster'      => __('Pôster Reprovado', 'sciflow-wp'),
            'request_new_poster' => __('Pôster Necessita Correção', 'sciflow-wp'),
        );

        // Only notify author for these specific decisions.
        if (!isset($decision_labels[$decision])) {
            return;
        }

        $vars['decision_label'] = $decision_labels[$decision];
        $vars['notes'] = $notes;
        $vars['message'] = sprintf(
            __('O editor tomou uma decisão sobre seu trabalho: <strong>%s</strong>.', 'sciflow-wp'),
            $vars['decision_label']
        );

        $subject = sprintf(__('[%s] Decisão Editorial: %s', 'sciflow-wp'), $vars['evento'], $vars['titulo']);

        $this->send($recipients, $subject, 'editorial-decision', $vars);
    }

    /**
     * 5. Returned to reviewer → Reviewer.
     */
    public function send_returned_to_reviewer($post_id)
    {
        $vars = $this->get_template_vars($post_id);
        $vars['message'] = __('O editor solicitou que você reavalie o trabalho abaixo após as alterações ou considerações editoriais.', 'sciflow-wp');

        $reviewer_id = get_post_meta($post_id, '_sciflow_reviewer_id', true);
        $reviewer = get_userdata($reviewer_id);

        if (!$reviewer)
            return;

        $subject = sprintf(__('[%s] Reavaliação Solicitada: %s', 'sciflow-wp'), $vars['evento'], $vars['titulo']);

        $this->send($reviewer->user_email, $subject, 'assigned-reviewer', $vars);
    }

    /**
     * 6. Approval + poster request → Author.
     */
    public function send_poster_request($post_id)
    {
        $vars = $this->get_template_vars($post_id);
        $vars['link'] = $this->get_poster_upload_url(); // Specific URL for poster upload
        $recipients = $this->get_author_recipients($post_id);

        if (empty($recipients)) {
            return;
        }

        $vars['message'] = __('Seu trabalho foi aprovado! Por favor, envie o pôster em formato PDF.', 'sciflow-wp');

        $subject = sprintf(__('[%s] Trabalho Aprovado — Envie Seu Pôster: %s', 'sciflow-wp'), $vars['evento'], $vars['titulo']);

        $this->send($recipients, $subject, 'poster-request', $vars);
    }

    /**
     * Get the poster upload URL.
     */
    private function get_poster_upload_url()
    {
        $pages = get_pages(array(
            'meta_key' => '_wp_page_template',
            'meta_value' => 'template-poster-upload.php',
            'number' => 1,
            'post_status' => 'publish'
        ));

        if (!empty($pages)) {
            return get_permalink($pages[0]->ID);
        }

        // Fallback to dashboard
        return $this->get_dashboard_url();
    }

    /**
     * 7. Confirmation needed (ranking selection) → Author.
     */
    public function send_confirmation_needed($post_id)
    {
        $vars = $this->get_template_vars($post_id);
        $recipients = $this->get_author_recipients($post_id);

        if (empty($recipients)) {
            return;
        }

        $deadline = get_post_meta($post_id, '_sciflow_confirmation_deadline', true);
        $vars['deadline'] = $deadline ? wp_date('d/m/Y H:i', strtotime($deadline)) : '---';
        $vars['message'] = sprintf(
            __('Seu trabalho foi selecionado para apresentação! Confirme até <strong>%s</strong> (3 dias).', 'sciflow-wp'),
            $vars['deadline']
        );

        $subject = sprintf(__('[%s] Confirmação de Apresentação: %s', 'sciflow-wp'), $vars['evento'], $vars['titulo']);

        $this->send($recipients, $subject, 'confirmation-needed', $vars);
    }

    /**
     * 8. Status Changed → Editor.
     * Triggered for various status transitions where the editor needs to be informed,
     * such as when a reviewer returns an evaluation.
     */
    public function send_status_change_to_editor($post_id, $new_status, $old_status)
    {
        // Only notify the editor when they need to take action.
        // - 'aguardando_decisao'    → reviewer submitted a review, editor must decide
        // - 'submetido_com_revisao' → author resubmitted after correction, editor must review again
        // New submissions ('submetido') are already covered by send_new_submission().
        $action_required_statuses = array(
            'aguardando_decisao',
            'submetido_com_revisao',
            'poster_enviado',
            'poster_reenviado',
        );

        if (!in_array($new_status, $action_required_statuses, true)) {
            return;
        }

        $vars = $this->get_template_vars($post_id);
        $event = get_post_meta($post_id, '_sciflow_event', true);

        if ($new_status === 'poster_enviado') {
            $vars['message'] = __('O autor enviou o pôster final para este trabalho.', 'sciflow-wp');
        } elseif ($new_status === 'poster_reenviado') {
            $vars['message'] = __('O autor reenviou o pôster após as correções solicitadas.', 'sciflow-wp');
        }

        $editor_email = $this->get_editor_email($event);
        if (empty($editor_email)) {
            return;
        }

        $sm = new SciFlow_Status_Manager();
        $vars['old_status_label'] = $sm->get_status_label($old_status);

        $subject = sprintf(__('[%s] Alteração de Status: %s', 'sciflow-wp'), $vars['evento'], $vars['titulo']);

        $this->send($editor_email, $subject, 'editor-status-change', $vars);
    }
}
