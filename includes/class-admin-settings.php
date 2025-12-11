<?php
/**
 * Admin settings page handler.
 *
 * @package Headless_Elementor
 */

namespace Headless_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the plugin settings page in WordPress admin.
 */
class Admin_Settings {

    /**
     * Plugin instance.
     *
     * @var Plugin
     */
    private $plugin;

    /**
     * Option name for settings.
     */
    const OPTION_NAME = 'headless_elementor_settings';

    /**
     * Settings page slug.
     */
    const PAGE_SLUG = 'headless-elementor';

    /**
     * Constructor.
     *
     * @param Plugin $plugin Plugin instance.
     */
    public function __construct( Plugin $plugin ) {
        $this->plugin = $plugin;

        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( HEADLESS_ELEMENTOR_FILE ), array( $this, 'add_settings_link' ) );
    }

    /**
     * Add settings page to admin menu.
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Headless Elementor', 'headless-elementor' ),
            __( 'Headless Elementor', 'headless-elementor' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Add settings link to plugins page.
     *
     * @param array $links Plugin action links.
     * @return array
     */
    public function add_settings_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'options-general.php?page=' . self::PAGE_SLUG ),
            __( 'Settings', 'headless-elementor' )
        );

        array_unshift( $links, $settings_link );

        return $links;
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'headless_elementor_settings_group',
            self::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => array(
                    'enabled_post_types' => array( 'post', 'page' ),
                    'cors_origins'       => '',
                ),
            )
        );

        // Post Types Section.
        add_settings_section(
            'headless_elementor_post_types',
            __( 'Post Types', 'headless-elementor' ),
            array( $this, 'render_post_types_section' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'enabled_post_types',
            __( 'Enable for Post Types', 'headless-elementor' ),
            array( $this, 'render_post_types_field' ),
            self::PAGE_SLUG,
            'headless_elementor_post_types'
        );

        // CORS Section.
        add_settings_section(
            'headless_elementor_cors',
            __( 'CORS Settings', 'headless-elementor' ),
            array( $this, 'render_cors_section' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'cors_origins',
            __( 'Allowed Origins', 'headless-elementor' ),
            array( $this, 'render_cors_field' ),
            self::PAGE_SLUG,
            'headless_elementor_cors'
        );

        // Pro Config Section.
        add_settings_section(
            'headless_elementor_pro',
            __( 'Elementor Pro Settings', 'headless-elementor' ),
            array( $this, 'render_pro_section' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'include_pro_config',
            __( 'Include Pro Config', 'headless-elementor' ),
            array( $this, 'render_pro_config_field' ),
            self::PAGE_SLUG,
            'headless_elementor_pro'
        );
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input Raw input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        // Sanitize post types.
        $sanitized['enabled_post_types'] = array();
        if ( isset( $input['enabled_post_types'] ) && is_array( $input['enabled_post_types'] ) ) {
            foreach ( $input['enabled_post_types'] as $post_type ) {
                $sanitized['enabled_post_types'][] = sanitize_key( $post_type );
            }
        }

        // Sanitize CORS origins.
        $sanitized['cors_origins'] = '';
        if ( isset( $input['cors_origins'] ) ) {
            $origins = explode( "\n", $input['cors_origins'] );
            $clean   = array();

            foreach ( $origins as $origin ) {
                $origin = trim( $origin );
                if ( '*' === $origin || filter_var( $origin, FILTER_VALIDATE_URL ) ) {
                    $clean[] = $origin;
                }
            }

            $sanitized['cors_origins'] = implode( "\n", $clean );
        }

        // Sanitize Pro config toggle.
        $sanitized['include_pro_config'] = ! empty( $input['include_pro_config'] );

        return $sanitized;
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        include HEADLESS_ELEMENTOR_PATH . 'admin/views/settings-page.php';
    }

    /**
     * Render post types section description.
     */
    public function render_post_types_section() {
        echo '<p>' . esc_html__( 'Select which post types should include Elementor data in their REST API responses.', 'headless-elementor' ) . '</p>';
    }

    /**
     * Render post types field.
     */
    public function render_post_types_field() {
        $settings     = $this->plugin->get_settings();
        $enabled      = isset( $settings['enabled_post_types'] ) ? $settings['enabled_post_types'] : array( 'post', 'page' );
        $post_types   = $this->get_elementor_post_types();

        foreach ( $post_types as $post_type => $label ) {
            $checked = in_array( $post_type, $enabled, true ) ? 'checked' : '';
            printf(
                '<label style="display: block; margin-bottom: 5px;"><input type="checkbox" name="%s[enabled_post_types][]" value="%s" %s> %s</label>',
                esc_attr( self::OPTION_NAME ),
                esc_attr( $post_type ),
                esc_attr( $checked ),
                esc_html( $label )
            );
        }
    }

    /**
     * Render CORS section description.
     */
    public function render_cors_section() {
        echo '<p>' . esc_html__( 'Configure Cross-Origin Resource Sharing (CORS) to allow your frontend application to access the REST API.', 'headless-elementor' ) . '</p>';
    }

    /**
     * Render CORS origins field.
     */
    public function render_cors_field() {
        $settings           = $this->plugin->get_settings();
        $origins            = isset( $settings['cors_origins'] ) ? $settings['cors_origins'] : '';
        $include_pro_config = ! empty( $settings['include_pro_config'] );
        $has_wildcard       = false !== strpos( $origins, '*' );

        printf(
            '<textarea name="%s[cors_origins]" id="headless_elementor_cors_origins" rows="5" cols="50" class="large-text code" placeholder="%s">%s</textarea>',
            esc_attr( self::OPTION_NAME ),
            esc_attr( "https://example.com\nhttps://staging.example.com" ),
            esc_textarea( $origins )
        );

        echo '<p class="description">' . esc_html__( 'Enter one origin per line. Use * to allow all origins (not recommended for production).', 'headless-elementor' ) . '</p>';

        // Show security warning if wildcard is used with Pro config enabled.
        if ( $has_wildcard && $include_pro_config ) {
            echo '<div class="notice notice-warning inline" style="margin-top: 10px; padding: 10px;">';
            echo '<strong>' . esc_html__( 'Security Warning:', 'headless-elementor' ) . '</strong> ';
            echo esc_html__( 'Using wildcard (*) CORS with Pro Config enabled exposes authentication nonces to any website. This could allow malicious sites to perform authenticated AJAX actions. Consider either restricting CORS origins or disabling Pro Config below.', 'headless-elementor' );
            echo '</div>';
        }
    }

    /**
     * Render Pro section description.
     */
    public function render_pro_section() {
        echo '<p>' . esc_html__( 'Configure Elementor Pro integration settings.', 'headless-elementor' ) . '</p>';
    }

    /**
     * Render Pro config field.
     */
    public function render_pro_config_field() {
        $settings           = $this->plugin->get_settings();
        $include_pro_config = ! empty( $settings['include_pro_config'] );
        $origins            = isset( $settings['cors_origins'] ) ? $settings['cors_origins'] : '';
        $has_wildcard       = false !== strpos( $origins, '*' );

        printf(
            '<label><input type="checkbox" name="%s[include_pro_config]" id="headless_elementor_pro_config" value="1" %s> %s</label>',
            esc_attr( self::OPTION_NAME ),
            checked( $include_pro_config, true, false ),
            esc_html__( 'Include Elementor Pro configuration in API responses', 'headless-elementor' )
        );

        echo '<p class="description">' . esc_html__( 'When enabled, includes ElementorProFrontendConfig with AJAX URL and authentication nonce. Required for Pro widgets that use AJAX (forms, popup triggers, etc.).', 'headless-elementor' ) . '</p>';

        if ( $has_wildcard && $include_pro_config ) {
            echo '<p class="description" style="color: #d63638;">';
            echo '<span class="dashicons dashicons-warning" style="color: #d63638;"></span> ';
            echo esc_html__( 'Warning: Nonce is currently exposed to all origins due to wildcard CORS.', 'headless-elementor' );
            echo '</p>';
        }
    }

    /**
     * Get post types that support Elementor.
     *
     * @return array Post type slug => label pairs.
     */
    private function get_elementor_post_types() {
        $post_types = array();

        // Get all public post types.
        $types = get_post_types(
            array(
                'public'       => true,
                'show_in_rest' => true,
            ),
            'objects'
        );

        foreach ( $types as $type ) {
            // Skip attachments.
            if ( 'attachment' === $type->name ) {
                continue;
            }

            $post_types[ $type->name ] = $type->label;
        }

        return $post_types;
    }
}
