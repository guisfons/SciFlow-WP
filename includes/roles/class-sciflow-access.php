<?php
/**
 * Handles backend data access restrictions for different roles.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Access
{

    public function __construct()
    {
        add_action('pre_get_posts', array($this, 'restrict_submissions_query'));
    }

    /**
     * Filter the submissions list for event-specific roles.
     * 
     * @param WP_Query $query
     */
    public function restrict_submissions_query($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, array('enfrute_trabalhos', 'senco_trabalhos'))) {
            return;
        }

        $current_user = wp_get_current_user();
        if (!$current_user || in_array('administrator', $current_user->roles)) {
            return;
        }

        $roles = (array) $current_user->roles;
        $meta_query = $query->get('meta_query') ?: array();

        // 1. Filter by Post Type based on Role
        $is_senco_role = in_array('sciflow_senco_editor', $roles) || in_array('sciflow_senco_revisor', $roles);
        $is_enfrute_role = in_array('sciflow_enfrute_editor', $roles) || in_array('sciflow_enfrute_revisor', $roles);

        if ($is_senco_role && !$is_enfrute_role) {
            $query->set('post_type', 'senco_trabalhos');
        } elseif ($is_enfrute_role && !$is_senco_role) {
            $query->set('post_type', 'enfrute_trabalhos');
        }

        // 2. Filter out Drafts (post_status and meta _sciflow_status)
        if ($is_senco_role || $is_enfrute_role) {
            // Force status to publish
            $query->set('post_status', 'publish');

            // Explicitly exclude rascunho meta
            $meta_query[] = array(
                'key' => '_sciflow_status',
                'value' => 'rascunho',
                'compare' => '!=',
            );
            $query->set('meta_query', $meta_query);
        }
    }
}
