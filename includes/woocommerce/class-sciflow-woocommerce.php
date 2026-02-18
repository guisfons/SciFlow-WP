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
        $found = false;
        foreach ($order->get_items() as $item) {
            $pid = $item->get_product_id();
            if (in_array($pid, $product_ids, true)) {
                $found = true;
                break;
            }

            // Also check variation parent.
            $variation_id = $item->get_variation_id();
            if ($variation_id && in_array($pid, $product_ids, true)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            return;
        }

        // Assign the role.
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        if (!in_array('sciflow_inscrito', $user->roles, true)) {
            $user->add_role('sciflow_inscrito');

            /**
             * Fires after sciflow_inscrito role is assigned via WooCommerce purchase.
             *
             * @param int $user_id  User ID.
             * @param int $order_id Order ID.
             */
            do_action('sciflow_role_assigned_via_woo', $user_id, $order_id);
        }
    }
}
