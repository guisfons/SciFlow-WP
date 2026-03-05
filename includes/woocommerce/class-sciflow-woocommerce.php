<?php
/**
 * WooCommerce integration — assigns sciflow_inscrito role on product purchase.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_WooCommerce
{

    public function __construct()
    {
        // Assign role when order status changes to completed or processing.
        add_action('woocommerce_order_status_completed', array($this, 'assign_role_on_purchase'));
        add_action('woocommerce_order_status_processing', array($this, 'assign_role_on_purchase'));
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
        $speaker_ids = $this->get_speaker_product_ids();

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

            if ($found_inscrito && $found_speaker) {
                break;
            }
        }

        if (!$found_inscrito && !$found_speaker) {
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

        // Admin and Editor roles bypass the payment requirement so they can also act as authors
        if (user_can($user_id, 'manage_sciflow') || user_can($user_id, 'manage_options')) {
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
