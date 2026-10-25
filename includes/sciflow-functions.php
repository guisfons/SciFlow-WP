<?php
/**
 * Public helper functions (stable API for themes and integrations).
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('sciflow_get_page_url')) {
    /**
     * URL of a SciFlow page role (see SciFlow_Pages::definitions()).
     *
     * @param string $role
     * @return string
     */
    function sciflow_get_page_url($role)
    {
        return SciFlow_Pages::url($role);
    }
}

if (!function_exists('sciflow_request_setup_wizard')) {
    /**
     * Ask SciFlow to open the setup wizard on the next admin page load
     * (used by the SciFlow theme on activation). No-op once setup is done.
     */
    function sciflow_request_setup_wizard()
    {
        if (current_user_can('manage_options') && !in_array(get_option(SciFlow_Setup_Wizard::STATUS_OPTION, ''), array('completed', 'legacy'), true)) {
            SciFlow_Setup_Wizard::request_redirect();
        }
    }
}

if (!function_exists('sciflow_get_setting')) {
    /**
     * Read a SciFlow setting.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    function sciflow_get_setting($key, $default = null)
    {
        return SciFlow_Settings::get($key, $default);
    }
}

if (!function_exists('sciflow_event_label')) {
    /**
     * Short label of an event slug ('enfrute' / 'semco').
     *
     * @param string $slug
     * @return string
     */
    function sciflow_event_label($slug)
    {
        return SciFlow_Events::label($slug);
    }
}

if (!function_exists('sciflow_user_has_paid_registration')) {
    /**
     * Whether a user may submit works (paid registration or bypass role).
     *
     * @param int $user_id
     * @return bool
     */
    function sciflow_user_has_paid_registration($user_id = 0)
    {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        if (!$user_id) {
            return false;
        }
        if ('1' !== (string) SciFlow_Settings::get('require_paid_registration', '1') || !class_exists('WooCommerce')) {
            return true;
        }
        return (new SciFlow_WooCommerce(false))->has_paid_registration($user_id);
    }
}

if (!function_exists('sciflow_can_view_submission')) {
    /**
     * Whether a user may view a submission's detail page.
     *
     * @param int $post_id
     * @param int $user_id
     * @return bool
     */
    function sciflow_can_view_submission($post_id, $user_id = 0)
    {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0 || !SciFlow_Security::is_submission($post_id)) {
            return false;
        }
        if (SciFlow_Security::is_author($post_id, $user_id)
            || SciFlow_Security::is_assigned_reviewer($post_id, $user_id)
            || SciFlow_Security::can_manage_submission($post_id, $user_id)) {
            return true;
        }

        // Event reviewers/editors may read published, non-draft works of their event.
        $post = get_post($post_id);
        if ($post && 'publish' === $post->post_status && 'rascunho' !== get_post_meta($post_id, '_sciflow_status', true)) {
            $event = SciFlow_Status_Manager::get_event_from_post_type($post->post_type);
            $user = get_userdata($user_id);
            $event_roles = array('sciflow_' . $event . '_editor', 'sciflow_' . $event . '_revisor');
            if ($user && array_intersect($event_roles, (array) $user->roles)) {
                return true;
            }
        }
        return false;
    }
}
