<?php
/**
 * Plugin Name:       SciFlow WP
 * Plugin URI:        https://github.com/guisfons/SciFlow-WP
 * Description:       Plugin de submissão e avaliação de artigos científicos para Enfrute e Senco.
 * Version:           1.0.0
 * Author:            Guilherme Silva Fonseca
 * Text Domain:       sciflow-wp
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SCIFLOW_VERSION', '1.0.0' );
define( 'SCIFLOW_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCIFLOW_URL', plugin_dir_url( __FILE__ ) );
define( 'SCIFLOW_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for SciFlow classes.
 *
 * Converts class names like SciFlow_Status_Manager to
 * includes/workflow/class-sciflow-status-manager.php (with directory mapping).
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'SciFlow_';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    $class_file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

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
        'admin/',
    );

    foreach ( $directories as $dir ) {
        $file = SCIFLOW_PATH . $dir . $class_file;
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }
});

/**
 * Plugin activation.
 */
function sciflow_activate() {
    $activator = new SciFlow_Activator();
    $activator->activate();
}
register_activation_hook( __FILE__, 'sciflow_activate' );

/**
 * Plugin deactivation.
 */
function sciflow_deactivate() {
    $deactivator = new SciFlow_Deactivator();
    $deactivator->deactivate();
}
register_deactivation_hook( __FILE__, 'sciflow_deactivate' );

/**
 * Bootstrap the plugin.
 */
function sciflow_init() {
    $loader = new SciFlow_Loader();
    $loader->run();
}
add_action( 'plugins_loaded', 'sciflow_init' );
