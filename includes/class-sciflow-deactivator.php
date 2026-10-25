<?php
/**
 * Runs on plugin deactivation (kept for backwards compatibility).
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Deactivator
{
    public function deactivate()
    {
        SciFlow_Installer::deactivate();
    }
}
