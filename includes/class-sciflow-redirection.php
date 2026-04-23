<?php
/**
 * Handles redirection for different user roles.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Redirection
{
    private $woocommerce;

    public function __construct(SciFlow_WooCommerce $woocommerce = null)
    {
        $this->woocommerce = $woocommerce;

        // Front-end redirects (Home)
        add_action('template_redirect', array($this, 'redirect_home'));

        // Back-end redirects (Admin)
        add_action('admin_init', array($this, 'redirect_admin'));

        // Login redirects
        add_filter('login_redirect', array($this, 'handle_login_redirect'), 10, 3);
        add_filter('woocommerce_login_redirect', array($this, 'handle_woo_login_redirect'), 10, 2);
    }

    /**
     * Get the redirect URL based on user roles.
     */
    private function get_redirect_url_by_role($user)
    {
        if (!$user || !isset($user->roles)) {
            return false;
        }

        $roles = (array) $user->roles;

        $editor_roles = array(
            'sciflow_editor',
            'sciflow_semco_editor',
            'sciflow_enfrute_editor'
        );

        $reviewer_roles = array(
            'sciflow_revisor',
            'sciflow_semco_revisor',
            'sciflow_enfrute_revisor'
        );

        $settings = get_option('sciflow_settings', array());
        $editor_url = !empty($settings['editor_dashboard_url']) ? $settings['editor_dashboard_url'] : home_url('/editor/dashboard/');
        $reviewer_url = !empty($settings['reviewer_dashboard_url']) ? $settings['reviewer_dashboard_url'] : home_url('/revisor/dashboard/');

        foreach ($editor_roles as $role) {
            if (in_array($role, $roles, true)) {
                return $editor_url;
            }
        }

        // --- AUTHOR EXCEPTION ---
        // If the user is a reviewer but is ALREADY on an author page, don't redirect them away.
        $current_url = home_url(add_query_arg(array(), $GLOBALS['wp']->request));
        $author_pages = array('/meus-artigos', '/submissao', '/meus-trabalhos');
        foreach ($author_pages as $page) {
            if (strpos($current_url, $page) !== false) {
                return false;
            }
        }
        // --- /AUTHOR EXCEPTION ---

        foreach ($reviewer_roles as $role) {
            if (in_array($role, $roles, true)) {
                return $reviewer_url;
            }
        }

        if (in_array('sciflow_inscrito', $roles, true) || $user->has_cap('sciflow_tecnico_epagri')) {
            if ($this->woocommerce && $this->woocommerce->has_paid_registration($user->ID)) {
                return home_url('/meus-artigos');
            }
        }

        return false;
    }

    /**
     * Redirect users from Home to the Articles page based on their role.
     */
    public function redirect_home()
    {
        if (!is_front_page() && !is_home()) {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();
        $redirect_url = $this->get_redirect_url_by_role($user);

        if ($redirect_url) {
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Handle WP Login redirect.
     */
    public function handle_login_redirect($redirect_to, $request, $user)
    {
        $role_redirect = $this->get_redirect_url_by_role($user);
        return $role_redirect ? $role_redirect : $redirect_to;
    }

    /**
     * Handle WooCommerce Login redirect.
     */
    public function handle_woo_login_redirect($redirect_to, $user)
    {
        $role_redirect = $this->get_redirect_url_by_role($user);
        return $role_redirect ? $role_redirect : $redirect_to;
    }

    /**
     * Redirect Editors and Reviewers from Admin to the Articles page.
     */
    public function redirect_admin()
    {
        if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return;
        }

        // Avoid infinite loop if somehow this hits a permitted admin page, 
        // but the goal is to keep them out of admin entirely.

        $user = wp_get_current_user();
        
        // Administrators should always have access to the dashboard.
        if (in_array('administrator', (array) $user->roles, true)) {
            return;
        }

        $redirect_url = $this->get_redirect_url_by_role($user);

        if ($redirect_url) {
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}
