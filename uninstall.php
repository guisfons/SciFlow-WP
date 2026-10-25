<?php
/**
 * Fired when the plugin is deleted.
 *
 * By default nothing is removed. When "Remover configurações ao excluir"
 * is enabled (SciFlow → Configurações → Avançado), options, roles,
 * scheduled events and transients are removed. Submissions, lectures,
 * attachments and user accounts are NEVER deleted here.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function sciflow_uninstall_site()
{
    $settings = get_option('sciflow_settings', array());
    if (!is_array($settings) || empty($settings['delete_data_on_uninstall']) || '1' !== (string) $settings['delete_data_on_uninstall']) {
        return;
    }

    foreach (array('sciflow_check_confirmation_deadlines', 'sciflow_check_corrections_deadlines', 'sciflow_check_poster_deadlines') as $hook) {
        wp_clear_scheduled_hook($hook);
    }

    $roles = array(
        'sciflow_inscrito', 'sciflow_speaker', 'sciflow_revisor', 'sciflow_editor',
        'sciflow_semco_editor', 'sciflow_semco_revisor', 'sciflow_enfrute_editor',
        'sciflow_enfrute_revisor', 'sciflow_tecnico_epagri', 'sciflow_tecnico_admin',
    );
    foreach ($roles as $role) {
        remove_role($role);
    }

    $admin = get_role('administrator');
    if ($admin) {
        foreach (array('manage_sciflow', 'manage_sciflow_tecnicos', 'assign_sciflow_reviewers', 'sciflow_review') as $cap) {
            $admin->remove_cap($cap);
        }
    }

    foreach (array('sciflow_settings', 'sciflow_version', 'sciflow_migrations', 'sciflow_installed_at', 'sciflow_show_onboarding') as $option) {
        delete_option($option);
    }

    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_sciflow\\_%' OR option_name LIKE '\\_transient\\_timeout\\_sciflow\\_%'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

if (is_multisite()) {
    foreach (get_sites(array('fields' => 'ids', 'number' => 0)) as $site_id) {
        switch_to_blog($site_id);
        sciflow_uninstall_site();
        restore_current_blog();
    }
} else {
    sciflow_uninstall_site();
}
