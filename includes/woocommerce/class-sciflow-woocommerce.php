<?php
/**
 * WooCommerce integration — assigns sciflow_inscrito role on product purchase.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_WooCommerce
{

    /**
     * @param bool $register_hooks Pass false to use only the helper methods.
     */
    public function __construct($register_hooks = true)
    {
        if (!$register_hooks) {
            return;
        }
        // Assign role when order status changes to completed or processing.
        add_action('woocommerce_order_status_completed', array($this, 'assign_role_on_purchase'));
        add_action('woocommerce_order_status_processing', array($this, 'assign_role_on_purchase'));

        // Visitors log in on "Minha conta" (same look as the rest of the site), not on wp-login.php.
        // WooCommerce itself reads ?redirect_to= there and only follows local URLs after login.
        add_filter('login_url', array($this, 'account_login_url'), 10, 3);
    }

    /**
     * Point front-end login links to the WooCommerce "Minha conta" page,
     * returning to the original page after login. wp-admin, re-authentication
     * and admin destinations keep the WordPress login screen.
     *
     * @param string $login_url
     * @param string $redirect
     * @param bool   $force_reauth
     * @return string
     */
    public function account_login_url($login_url, $redirect, $force_reauth)
    {
        if ($force_reauth || is_admin() || !function_exists('wc_get_page_id') || wc_get_page_id('myaccount') <= 0) {
            return $login_url;
        }
        if ($redirect && 0 === strpos($redirect, admin_url())) {
            return $login_url;
        }

        $account = wc_get_page_permalink('myaccount');
        return $redirect ? add_query_arg('redirect_to', rawurlencode($redirect), $account) : $account;
    }

    /**
     * Get the product IDs that grant the inscrito role.
     *
     * @return array Product IDs.
     */
    private function get_inscription_product_ids()
    {
        $settings = get_option('sciflow_settings', array());
        $raw = $settings['woo_product_ids'] ?? '';

        if (empty($raw)) {
            return array();
        }

        // Support comma-separated IDs.
        return array_filter(array_map('absint', explode(',', $raw)));
    }

    /**
     * Get the product/variation IDs that grant the tecnico epagri role.
     *
     * @return array Product IDs.
     */
    private function get_tecnico_epagri_product_ids()
    {
        $settings = get_option('sciflow_settings', array());
        $raw = $settings['woo_tecnico_epagri_product_ids'] ?? '';

        if (empty($raw)) {
            return array();
        }

        return array_filter(array_map('absint', explode(',', $raw)));
    }

    /**
     * Get the product/variation IDs that grant the speaker role.
     *
     * @return array Product IDs.
     */
    private function get_speaker_product_ids()
    {
        $settings = get_option('sciflow_settings', array());
        $raw = $settings['woo_speaker_product_ids'] ?? '';

        if (empty($raw)) {
            return array();
        }

        return array_filter(array_map('absint', explode(',', $raw)));
    }

    /**
     * Assign sciflow_inscrito role when a qualifying order is completed.
     *
     * @param int $order_id WooCommerce order ID.
     */
    public function assign_role_on_purchase($order_id)
    {
        $product_ids = $this->get_inscription_product_ids();
        if (empty($product_ids)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            return; // Guest checkout — cannot assign role.
        }

        // Check if order contains any qualifying product.
        $found_inscrito = false;
        $found_speaker = false;
        $found_tecnico = false;
        $speaker_ids = $this->get_speaker_product_ids();
        $tecnico_ids = $this->get_tecnico_epagri_product_ids();

        foreach ($order->get_items() as $item) {
            $pid = $item->get_product_id();
            $variation_id = $item->get_variation_id();

            // Check for inscrito
            if (in_array($pid, $product_ids, true) || ($variation_id && in_array($variation_id, $product_ids, true)) || ($variation_id && in_array($pid, $product_ids, true))) {
                $found_inscrito = true;
            }

            // Check for speaker
            if (in_array($pid, $speaker_ids, true) || ($variation_id && in_array($variation_id, $speaker_ids, true)) || ($variation_id && in_array($pid, $speaker_ids, true))) {
                $found_speaker = true;
            }

            // Check for tecnico epagri
            if (in_array($pid, $tecnico_ids, true) || ($variation_id && in_array($variation_id, $tecnico_ids, true)) || ($variation_id && in_array($pid, $tecnico_ids, true))) {
                $found_tecnico = true;
            }

            if ($found_inscrito && $found_speaker && $found_tecnico) {
                break;
            }
        }

        if (!$found_inscrito && !$found_speaker && !$found_tecnico) {
            return;
        }

        // Assign the role.
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        if ($found_inscrito && !in_array('sciflow_inscrito', $user->roles, true)) {
            $user->add_role('sciflow_inscrito');
            do_action('sciflow_role_assigned_via_woo', $user_id, $order_id, 'sciflow_inscrito');
        }

        if ($found_speaker && !in_array('sciflow_speaker', $user->roles, true)) {
            $user->add_role('sciflow_speaker');
            do_action('sciflow_role_assigned_via_woo', $user_id, $order_id, 'sciflow_speaker');
        }

        if ($found_tecnico && !$user->has_cap('sciflow_tecnico_epagri')) {
            $user->add_role('sciflow_tecnico_epagri');
            do_action('sciflow_role_assigned_via_woo', $user_id, $order_id, 'sciflow_tecnico_epagri');
        }
    }

    /**
     * Check if a user has a paid registration (completed order for inscription product).
     *
     * @param int $user_id User ID.
     * @return bool True if paid, false otherwise.
     */
    public function has_paid_registration($user_id)
    {
        if (!$user_id) {
            return false;
        }

        if ('1' !== (string) SciFlow_Settings::get('require_paid_registration', '1')) {
            return true;
        }

        // Admin, Editor, Reviewer, Speaker and Tecnico Epagri roles bypass the payment requirement
        if (user_can($user_id, 'manage_sciflow') || user_can($user_id, 'manage_options') || user_can($user_id, 'sciflow_speaker') || user_can($user_id, 'sciflow_review')) {
            return true;
        }

        // Tecnico Epagri counts as a paid registration
        $user_obj = get_userdata($user_id);
        if ($user_obj && $user_obj->has_cap('sciflow_tecnico_epagri')) {
            return true;
        }

        $product_ids = $this->get_inscription_product_ids();
        if (empty($product_ids)) {
            return true; // If no products are defined, assume it's open (or we can't check).
        }

        // Check for completed or processing orders.
        $orders = wc_get_orders(array(
            'customer' => $user_id,
            'status' => array('wc-completed', 'wc-processing'),
            'limit' => -1,
        ));

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $pid = $item->get_product_id();
                if (in_array($pid, $product_ids, true)) {
                    return true;
                }

                // Variation check.
                $vid = $item->get_variation_id();
                if ($vid && in_array($vid, $product_ids, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
