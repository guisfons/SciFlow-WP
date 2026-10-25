<?php
/**
 * Runs on plugin activation (kept for backwards compatibility).
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Activator
{
    public function activate()
    {
        SciFlow_Installer::activate(false);
    }
}
