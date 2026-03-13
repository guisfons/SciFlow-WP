<?php
/**
 * Plugin Name:       SciFlow WP
 * Plugin URI:        https://github.com/guisfons/SciFlow-WP
 * Description:       Plugin de submissão e avaliação de artigos científicos para Enfrute e Semco.
 * Version:           1.0.0
 * Author:            Guilherme Silva Fonseca
 * Text Domain:       sciflow-wp
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SCIFLOW_VERSION', '1.0.1');
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

/**
 * Plugin activation.
 */
function sciflow_activate()
{
    $activator = new SciFlow_Activator();
    $activator->activate();
}
register_activation_hook(__FILE__, 'sciflow_activate');

/**
 * Plugin deactivation.
 */
function sciflow_deactivate()
{
    $deactivator = new SciFlow_Deactivator();
    $deactivator->deactivate();
}
register_deactivation_hook(__FILE__, 'sciflow_deactivate');

/**
 * Bootstrap the plugin.
 */
function sciflow_init()
{
    $loader = new SciFlow_Loader();
    $loader->run();

    // Check for version upgrade and refresh roles.
    $installed_ver = get_option('sciflow_version');
    if ($installed_ver !== SCIFLOW_VERSION) {
        $roles = new SciFlow_Roles();
        $roles->refresh_roles();
        update_option('sciflow_version', SCIFLOW_VERSION);
    }
}
add_action('plugins_loaded', 'sciflow_init');

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

