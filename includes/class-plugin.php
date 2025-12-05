<?php
/**
 * Main plugin class.
 *
 * @package Headless_Elementor
 */

namespace Headless_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin main class - singleton pattern.
 */
final class Plugin {

    /**
     * Plugin instance.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Plugin settings.
     *
     * @var array
     */
    private $settings = array();

    /**
     * REST Fields handler.
     *
     * @var Rest_Fields
     */
    public $rest_fields;

    /**
     * Asset Collector.
     *
     * @var Asset_Collector
     */
    public $asset_collector;

    /**
     * Config Generator.
     *
     * @var Config_Generator
     */
    public $config_generator;

    /**
     * Admin Settings.
     *
     * @var Admin_Settings
     */
    public $admin_settings;

    /**
     * Get plugin instance.
     *
     * @return Plugin
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->load_settings();
        $this->init_components();
        $this->init_hooks();
    }

    /**
     * Load plugin settings.
     */
    private function load_settings() {
        $defaults = array(
            'enabled_post_types' => array( 'post', 'page' ),
            'cors_origins'       => '',
        );

        $this->settings = wp_parse_args(
            get_option( 'headless_elementor_settings', array() ),
            $defaults
        );
    }

    /**
     * Initialize plugin components.
     */
    private function init_components() {
        $this->asset_collector  = new Asset_Collector();
        $this->config_generator = new Config_Generator();
        $this->rest_fields      = new Rest_Fields( $this );

        if ( is_admin() ) {
            $this->admin_settings = new Admin_Settings( $this );
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'rest_api_init', array( $this, 'add_cors_headers' ) );
        add_filter( 'rest_pre_serve_request', array( $this, 'handle_cors_preflight' ), 10, 4 );
    }

    /**
     * Load plugin text domain.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'headless-elementor',
            false,
            dirname( plugin_basename( HEADLESS_ELEMENTOR_FILE ) ) . '/languages'
        );
    }

    /**
     * Get plugin setting.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get_setting( $key, $default = null ) {
        return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
    }

    /**
     * Get all settings.
     *
     * @return array
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Reload settings from database.
     */
    public function reload_settings() {
        $this->load_settings();
    }

    /**
     * Get enabled post types.
     *
     * @return array
     */
    public function get_enabled_post_types() {
        $enabled = $this->get_setting( 'enabled_post_types', array( 'post', 'page' ) );
        return is_array( $enabled ) ? $enabled : array( 'post', 'page' );
    }

    /**
     * Add CORS headers to REST API responses.
     */
    public function add_cors_headers() {
        $origins = $this->get_setting( 'cors_origins', '' );

        if ( empty( $origins ) ) {
            return;
        }

        $allowed_origins = array_filter( array_map( 'trim', explode( "\n", $origins ) ) );

        if ( empty( $allowed_origins ) ) {
            return;
        }

        $request_origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

        if ( in_array( '*', $allowed_origins, true ) ) {
            header( 'Access-Control-Allow-Origin: *' );
        } elseif ( in_array( $request_origin, $allowed_origins, true ) ) {
            header( 'Access-Control-Allow-Origin: ' . $request_origin );
            header( 'Vary: Origin' );
        }

        header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type, Authorization' );
    }

    /**
     * Handle CORS preflight requests.
     *
     * @param bool             $served  Whether the request has been served.
     * @param \WP_HTTP_Response $result  Result to send.
     * @param \WP_REST_Request  $request Request used to generate the response.
     * @param \WP_REST_Server   $server  Server instance.
     * @return bool
     */
    public function handle_cors_preflight( $served, $result, $request, $server ) {
        if ( 'OPTIONS' === $request->get_method() ) {
            $this->add_cors_headers();
            header( 'Access-Control-Max-Age: 86400' );
            exit;
        }
        return $served;
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }
}
