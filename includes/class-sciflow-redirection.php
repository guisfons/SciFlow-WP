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
        $roles = (array) $user->roles;

        // 1. Inscritos with paid registration
        if (in_array('sciflow_inscrito', $roles, true)) {
            if ($this->woocommerce && $this->woocommerce->has_paid_registration($user->ID)) {
                wp_safe_redirect(home_url('/artigos-publicados'));
                exit;
            }
        }

        // 2. Editors and Reviewers
        $editorial_roles = array(
            'sciflow_editor',
            'sciflow_revisor',
            'sciflow_senco_editor',
            'sciflow_senco_revisor',
            'sciflow_enfrute_editor',
            'sciflow_enfrute_revisor'
        );

        foreach ($editorial_roles as $role) {
            if (in_array($role, $roles, true)) {
                wp_safe_redirect(home_url('/artigos-publicados'));
                exit;
            }
        }
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
        $roles = (array) $user->roles;

        // Administrators should always have access to the dashboard.
        if (in_array('administrator', $roles, true)) {
            return;
        }

        $editorial_roles = array(
            'sciflow_editor',
            'sciflow_revisor',
            'sciflow_senco_editor',
            'sciflow_senco_revisor',
            'sciflow_enfrute_editor',
            'sciflow_enfrute_revisor'
        );

        foreach ($editorial_roles as $role) {
            if (in_array($role, $roles, true)) {
                wp_safe_redirect(home_url('/artigos-publicados'));
                exit;
            }
        }
    }
}
