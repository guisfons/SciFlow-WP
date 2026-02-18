<?php
/**
 * Runs on plugin deactivation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Deactivator
{

    public function deactivate()
    {
        // Clear scheduled cron events.
        wp_clear_scheduled_hook('sciflow_check_confirmation_deadlines');

        // Flush rewrite rules.
        flush_rewrite_rules();
    }
}
