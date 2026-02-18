<?php
/**
 * Fired when the plugin is uninstalled.
 * Cleans up all data created by SciFlow.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove roles.
remove_role('sciflow_inscrito');
remove_role('sciflow_revisor');
remove_role('sciflow_editor');

// Remove capabilities from administrator.
$admin_role = get_role('administrator');
if ($admin_role) {
    $caps = array(
        'edit_enfrute_trabalhos',
        'edit_others_enfrute_trabalhos',
        'publish_enfrute_trabalhos',
        'read_private_enfrute_trabalhos',
        'delete_enfrute_trabalhos',
        'delete_others_enfrute_trabalhos',
        'edit_senco_trabalhos',
        'edit_others_senco_trabalhos',
        'publish_senco_trabalhos',
        'read_private_senco_trabalhos',
        'delete_senco_trabalhos',
        'delete_others_senco_trabalhos',
        'manage_sciflow',
        'assign_sciflow_reviewers',
    );
    foreach ($caps as $cap) {
        $admin_role->remove_cap($cap);
    }
}

// Remove options.
delete_option('sciflow_version');
delete_option('sciflow_settings');

// Remove all post meta.
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_sciflow_%'");

// Remove posts of both CPTs.
$post_types = array('enfrute_trabalhos', 'senco_trabalhos');
foreach ($post_types as $pt) {
    $posts = get_posts(array(
        'post_type' => $pt,
        'posts_per_page' => -1,
        'post_status' => 'any',
        'fields' => 'ids',
    ));
    foreach ($posts as $post_id) {
        wp_delete_post($post_id, true);
    }
}
