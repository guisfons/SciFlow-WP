<?php
/**
 * Plugin Name:       SciFlow WP
 * Plugin URI:        https://github.com/guisfons/SciFlow-WP
 * Description:       Submissão, avaliação por pares, ranking, pôsteres, certificados e anais para eventos científicos.
 * Version:           1.1.0
 * Author:            Doppio
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       sciflow-wp
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Update URI:        false
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SCIFLOW_VERSION', '1.1.0');
define('SCIFLOW_FILE', __FILE__);
define('SCIFLOW_PATH', plugin_dir_path(__FILE__));
define('SCIFLOW_URL', plugin_dir_url(__FILE__));
define('SCIFLOW_BASENAME', plugin_basename(__FILE__));

/**
 * Autoloader for SciFlow classes.
 *
 * Converts class names like SciFlow_Status_Manager to
 * includes/workflow/class-sciflow-status-manager.php (with directory mapping).
 */
spl_autoload_register(function ($class) {
    $prefix = 'SciFlow_';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $class_file = 'class-' . strtolower(str_replace('_', '-', $class)) . '.php';

    // Directory mapping for organized class loading.
    $directories = array(
        'includes/',
        'includes/post-types/',
        'includes/roles/',
        'includes/meta/',
        'includes/workflow/',
        'includes/ranking/',
        'includes/email/',
        'includes/payment/',
        'includes/certificates/',
        'includes/upload/',
        'includes/frontend/',
        'includes/woocommerce/',
        'includes/admin/',
        'admin/',
    );

    foreach ($directories as $dir) {
        $file = SCIFLOW_PATH . $dir . $class_file;
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

require_once SCIFLOW_PATH . 'includes/sciflow-functions.php';

/**
 * Plugin activation (supports multisite network activation).
 *
 * @param bool $network_wide
 */
function sciflow_activate($network_wide = false)
{
    SciFlow_Installer::activate($network_wide);
}
register_activation_hook(__FILE__, 'sciflow_activate');

/**
 * Plugin deactivation. Data is preserved.
 */
function sciflow_deactivate()
{
    SciFlow_Installer::deactivate();
}
register_deactivation_hook(__FILE__, 'sciflow_deactivate');

/**
 * New sites created on a network where SciFlow is network-active.
 *
 * @param WP_Site $site
 */
add_action('wp_initialize_site', function ($site) {
    if (!function_exists('is_plugin_active_for_network')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (is_plugin_active_for_network(SCIFLOW_BASENAME)) {
        switch_to_blog($site->blog_id);
        SciFlow_Installer::install_site();
        restore_current_blog();
    }
}, 20);

// Security hooks must run in every context (front, admin, REST, AJAX).
SciFlow_Security::init();
SciFlow_Branding::init();
add_action('wp_roles_init', array('SciFlow_Roles', 'localize_role_names'));

/**
 * Bootstrap the plugin.
 */
function sciflow_init()
{
    $loader = new SciFlow_Loader();
    $loader->run();
}
add_action('plugins_loaded', 'sciflow_init');

// Upgrades run on init (after translations are available) and before CPTs register.
add_action('init', array('SciFlow_Installer', 'maybe_upgrade'), 1);

/**
 * Register plugin-side page templates so they appear in the WordPress
 * "Page Attributes → Template" dropdown and are correctly loaded.
 */
add_filter('theme_page_templates', function ($templates) {
    $templates['sciflow-confirm-presentation'] = __('SciFlow — Confirmar Apresentação', 'sciflow-wp');
    return $templates;
});

add_filter('template_include', function ($template) {
    if (is_page()) {
        $page_template = get_post_meta(get_the_ID(), '_wp_page_template', true);
        if ($page_template === 'sciflow-confirm-presentation') {
            $plugin_tpl = SCIFLOW_PATH . 'public/templates/template-confirm-presentation.php';
            if (file_exists($plugin_tpl)) {
                return $plugin_tpl;
            }
        }
    }
    return $template;
});

/**
 * TinyMCE: prevent Enter/line-breaks in the sciflow_content abstract field.
 * The `setup` callback fires at TinyMCE init time and overrides the core
 * InsertParagraph / InsertLineBreak commands so they do nothing.
 */
add_filter('tiny_mce_before_init', function ($init, $editor_id) {
    if ($editor_id !== 'sciflow_content') {
        return $init;
    }

    // Merge into existing setup if one was already defined.
    $existing_setup = !empty($init['setup']) ? $init['setup'] : '';

    $no_enter_setup = "function(ed) {
        ed.addCommand('InsertParagraph', function() { return false; });
        ed.addCommand('InsertLineBreak', function() { return false; });

        // Block Enter key at the lowest level.
        ed.on('keydown', function(e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return false;
            }
        });

        // Intercept paste at the native browser level.
        // We take full ownership: get plain text, strip ALL line breaks, insert.
        // This fires before TinyMCE's own paste processing so nothing slips through.
        ed.on('paste', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var clipData = e.clipboardData || (e.originalEvent && e.originalEvent.clipboardData) || window.clipboardData;
            if (!clipData) { return; }
            var text = clipData.getData('text/plain') || clipData.getData('Text') || '';
            // Replace every kind of line break / vertical whitespace with a single space.
            text = text.replace(/[\\r\\n\\v\\f\\u2028\\u2029]+/g, ' ').replace(/\\s+/g, ' ').trim();
            if (text) {
                ed.insertContent(ed.dom.encode(text));
            }
        });

        // Belt-and-suspenders: also clean via PastePreProcess (rich paste).
        ed.on('PastePreProcess', function(e) {
            e.content = e.content
                .replace(/<br\\s*\\/?>/gi, ' ')
                .replace(/<\\/p>\\s*<p[^>]*>/gi, ' ')
                .replace(/<p[^>]*>/gi, '')
                .replace(/<\\/p>/gi, ' ')
                .replace(/\\s+/g, ' ')
                .trim();
        });

        " . $existing_setup . "
    }";

    $init['setup'] = $no_enter_setup;

    // Also intercept via paste_preprocess (TinyMCE Paste plugin option).
    // This fires before PastePreProcess and handles HTML-to-text conversion.
    $init['paste_preprocess'] = "function(plugin, args) {
        args.content = args.content
            .replace(/<br\\s*\\/?>/gi, ' ')
            .replace(/<\\/p>\\s*<p[^>]*>/gi, ' ')
            .replace(/<p[^>]*>/gi, '')
            .replace(/<\\/p>/gi, ' ')
            .replace(/\\s+/g, ' ')
            .trim();
    }";

    return $init;
}, 10, 2);

