<?php
/**
 * Plugin Name: Headless Elementor
 * Plugin URI: https://github.com/turbo-soft/headless-elementor
 * Description: REST API extension for headless Elementor content delivery. Provides CSS, JS, and configuration data needed to render Elementor pages on external frontends.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Miroslav Pantoš
 * Author URI: https://xcentric.it
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: headless-elementor
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'HEADLESS_ELEMENTOR_VERSION', '1.0.0' );
define( 'HEADLESS_ELEMENTOR_FILE', __FILE__ );
define( 'HEADLESS_ELEMENTOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'HEADLESS_ELEMENTOR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check minimum requirements before loading the plugin.
 */
function headless_elementor_requirements_check() {
    $php_version = '7.4';
    $wp_version  = '5.8';

    if ( version_compare( PHP_VERSION, $php_version, '<' ) ) {
        add_action( 'admin_notices', function() use ( $php_version ) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: 1: Required PHP version, 2: Current PHP version */
                    esc_html__( 'Headless Elementor requires PHP %1$s or higher. Your current version is %2$s.', 'headless-elementor' ),
                    esc_html( $php_version ),
                    esc_html( PHP_VERSION )
                )
            );
        });
        return false;
    }

    global $wp_version;
    $required_wp_version = '5.8';
    if ( version_compare( $wp_version, $required_wp_version, '<' ) ) {
        add_action( 'admin_notices', function() use ( $required_wp_version, $wp_version ) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: 1: Required WordPress version, 2: Current WordPress version */
                    esc_html__( 'Headless Elementor requires WordPress %1$s or higher. Your current version is %2$s.', 'headless-elementor' ),
                    esc_html( $required_wp_version ),
                    esc_html( $wp_version )
                )
            );
        });
        return false;
    }

    return true;
}

/**
 * Check if Elementor is active.
 */
function headless_elementor_is_elementor_active() {
    return did_action( 'elementor/loaded' );
}

/**
 * Show admin notice if Elementor is not active.
 */
function headless_elementor_missing_elementor_notice() {
    if ( headless_elementor_is_elementor_active() ) {
        return;
    }

    printf(
        '<div class="notice notice-warning"><p>%s</p></div>',
        esc_html__( 'Headless Elementor requires Elementor to be installed and activated.', 'headless-elementor' )
    );
}
add_action( 'admin_notices', 'headless_elementor_missing_elementor_notice' );

/**
 * Autoloader for plugin classes.
 */
spl_autoload_register( function( $class ) {
    $prefix = 'Headless_Elementor\\';

    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, strlen( $prefix ) );
    $file = HEADLESS_ELEMENTOR_PATH . 'includes/class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
});

/**
 * Initialize the plugin.
 */
function headless_elementor_init() {
    if ( ! headless_elementor_requirements_check() ) {
        return;
    }

    if ( ! headless_elementor_is_elementor_active() ) {
        return;
    }

    \Headless_Elementor\Plugin::instance();
}
add_action( 'plugins_loaded', 'headless_elementor_init', 20 );

/**
 * Plugin activation hook.
 */
function headless_elementor_activate() {
    $defaults = array(
        'enabled_post_types' => array( 'post', 'page' ),
        'cors_origins'       => '',
        'output_format'      => 'script_tags',
    );

    if ( ! get_option( 'headless_elementor_settings' ) ) {
        add_option( 'headless_elementor_settings', $defaults );
    }

    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'headless_elementor_activate' );

/**
 * Plugin deactivation hook.
 */
function headless_elementor_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'headless_elementor_deactivate' );
