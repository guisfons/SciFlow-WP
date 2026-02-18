<?php
/**
 * Runs on plugin activation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Activator
{

    public function activate()
    {
        // Register roles and capabilities.
        $roles = new SciFlow_Roles();
        $roles->add_roles();
        $roles->add_caps();

        // Flush rewrite rules so CPT permalinks work.
        $enfrute = new SciFlow_Enfrute_CPT();
        $enfrute->register();
        $senco = new SciFlow_Senco_CPT();
        $senco->register();
        flush_rewrite_rules();

        // Store version for future upgrades.
        update_option('sciflow_version', SCIFLOW_VERSION);
    }
}
