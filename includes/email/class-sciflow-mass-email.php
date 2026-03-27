<?php
/**
 * Mass email logic for SciFlow.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Mass_Email
{
    /**
     * Get recipients by role and optionally event.
     */
    public function get_recipients($role = '', $event = '')
    {
        $args = array(
            'fields' => array('ID', 'user_email', 'display_name', 'user_login'),
        );

        if (!empty($role)) {
            $args['role'] = $role;
        } else {
            // Default to all sciflow roles if none specified
            $args['role__in'] = array(
                'sciflow_inscrito',
                'sciflow_tecnico_epagri',
                'sciflow_revisor',
                'sciflow_speaker',
                'sciflow_editor',
                'sciflow_semco_editor',
                'sciflow_semco_revisor',
                'sciflow_enfrute_editor',
                'sciflow_enfrute_revisor',
                'administrator'
            );
        }

        $event_author_ids = array();
        if (!empty($event)) {
            global $wpdb;
            $query = $wpdb->prepare("
                SELECT DISTINCT pm_author.meta_value 
                FROM {$wpdb->postmeta} pm_author
                INNER JOIN {$wpdb->postmeta} pm_event ON pm_author.post_id = pm_event.post_id
                INNER JOIN {$wpdb->posts} p ON pm_author.post_id = p.ID
                WHERE p.post_type IN ('enfrute_trabalhos', 'semco_trabalhos')
                  AND p.post_status != 'trash'
                  AND pm_author.meta_key = '_sciflow_author_id'
                  AND pm_event.meta_key = '_sciflow_event'
                  AND pm_event.meta_value = %s
            ", $event);
            
            $results = $wpdb->get_col($query);
            $event_author_ids = array_map('intval', $results);
        }

        $users = get_users($args);
        $filtered = array();

        foreach ($users as $user) {
            if (!empty($event)) {
                $user_id = $user->ID;
                $user_obj = get_userdata($user_id);
                $roles = $user_obj->roles;
                
                $match = false;
                foreach ($roles as $r) {
                    if (strpos($r, $event) !== false) {
                        $match = true;
                        break;
                    }
                }
                
                // Also check if they are authors of works in that event
                if (!$match) {
                    if (in_array($user_id, $event_author_ids, true)) {
                        $match = true;
                    }
                }

                if (!$match) continue;
            }

            if (is_email($user->user_email)) {
                $filtered[] = array(
                    'id' => $user->ID,
                    'email' => $user->user_email,
                    'name' => $user->display_name,
                    'login' => $user->user_login
                );
            }
        }

        return $filtered;
    }

    /**
     * Send individual email with placeholder replacement.
     */
    public function send_individual_mail($user_data, $subject, $content)
    {
        $placeholders = array(
            '{{name}}'       => $user_data['name'],
            '{{first_name}}' => explode(' ', $user_data['name'])[0],
            '{{site_url}}'   => home_url(),
            '{{login_url}}'  => wp_login_url(),
            '{{email}}'      => $user_data['email'],
        );

        $final_subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
        $final_content = str_replace(array_keys($placeholders), array_values($placeholders), $content);

        // Wrap in site styling
        $html = $this->wrap_content($final_content);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );

        return wp_mail($user_data['email'], $final_subject, $html, $headers);
    }

    /**
     * Wrap content in a basic HTML template.
     */
    private function wrap_content($content)
    {
        $site_name = get_bloginfo('name');
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #eee; border-radius: 8px; }
                .header { border-bottom: 2px solid #2c5530; margin-bottom: 20px; padding-bottom: 10px; }
                .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #eee; font-size: 12px; color: #888; text-align: center; }
                h1, h2, h3 { color: #2c5530; }
                .button { display: inline-block; padding: 10px 20px; background-color: #2c5530; color: #fff; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2><?php echo esc_html($site_name); ?></h2>
                </div>
                <div class="content">
                    <?php echo $content; ?>
                </div>
                <div class="footer">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?>. Todos os direitos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
