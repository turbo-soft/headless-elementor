<?php
/**
 * REST API fields registration.
 *
 * @package Headless_Elementor
 */

namespace Headless_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles REST API field registration for Elementor data.
 */
class Rest_Fields {

    /**
     * Plugin instance.
     *
     * @var Plugin
     */
    private $plugin;

    /**
     * Constructor.
     *
     * @param Plugin $plugin Plugin instance.
     */
    public function __construct( Plugin $plugin ) {
        $this->plugin = $plugin;
        add_action( 'rest_api_init', array( $this, 'register_fields' ) );
    }

    /**
     * Register REST API fields for enabled post types.
     */
    public function register_fields() {
        $post_types = $this->plugin->get_enabled_post_types();

        foreach ( $post_types as $post_type ) {
            register_rest_field(
                $post_type,
                'elementor_data',
                array(
                    'get_callback'    => array( $this, 'get_elementor_data' ),
                    'update_callback' => null,
                    'schema'          => $this->get_schema(),
                )
            );
        }
    }

    /**
     * Get Elementor data for a post.
     *
     * @param array $object Post object array.
     * @return array|null
     */
    public function get_elementor_data( $object ) {
        $post_id = $object['id'];

        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return $this->get_empty_response( false );
        }

        $elementor = \Elementor\Plugin::instance();
        $document  = $elementor->documents->get( $post_id );

        if ( ! $document || ! $document->is_built_with_elementor() ) {
            return $this->get_empty_response( false );
        }

        // Set up the post context for proper data generation.
        global $post;
        $original_post = $post;
        $post = get_post( $post_id );
        setup_postdata( $post );

        try {
            $asset_collector  = $this->plugin->asset_collector;
            $config_generator = $this->plugin->config_generator;

            // Enable and collect assets.
            $asset_collector->enable_page_assets( $post_id );
            $style_links = $asset_collector->collect_styles( $post_id );
            $inline_css  = $asset_collector->get_inline_css( $post_id );
            $scripts     = $asset_collector->collect_scripts( $post_id );

            // Generate configs.
            $use_script_tags = $this->plugin->use_script_tags();
            $config          = $config_generator->get_frontend_config( $post_id, $use_script_tags );
            $pro_config      = $config_generator->get_pro_config( $post_id, $use_script_tags );

            $response = array(
                'isElementor' => true,
                'styleLinks'  => $style_links,
                'inlineCss'   => $inline_css,
                'scripts'     => $scripts,
                'config'      => $config,
                'proConfig'   => $pro_config,
            );

        } catch ( \Exception $e ) {
            $response = $this->get_empty_response( true );
            $response['error'] = $e->getMessage();
        }

        // Restore original post context.
        $post = $original_post;
        if ( $original_post ) {
            setup_postdata( $original_post );
        } else {
            wp_reset_postdata();
        }

        return $response;
    }

    /**
     * Get empty response structure.
     *
     * @param bool $is_elementor Whether the post is built with Elementor.
     * @return array
     */
    private function get_empty_response( $is_elementor ) {
        return array(
            'isElementor' => $is_elementor,
            'styleLinks'  => array(),
            'inlineCss'   => '',
            'scripts'     => array(),
            'config'      => $this->plugin->use_script_tags() ? '' : new \stdClass(),
            'proConfig'   => $this->plugin->use_script_tags() ? '' : null,
        );
    }

    /**
     * Get REST field schema.
     *
     * @return array
     */
    private function get_schema() {
        return array(
            'description' => __( 'Elementor assets and configuration data for headless rendering.', 'headless-elementor' ),
            'type'        => 'object',
            'context'     => array( 'view', 'embed' ),
            'properties'  => array(
                'isElementor' => array(
                    'type'        => 'boolean',
                    'description' => __( 'Whether the post is built with Elementor.', 'headless-elementor' ),
                ),
                'styleLinks' => array(
                    'type'        => 'array',
                    'description' => __( 'Array of CSS file URLs to load.', 'headless-elementor' ),
                    'items'       => array( 'type' => 'string', 'format' => 'uri' ),
                ),
                'inlineCss' => array(
                    'type'        => 'string',
                    'description' => __( 'Inline CSS specific to this post.', 'headless-elementor' ),
                ),
                'scripts' => array(
                    'type'        => 'array',
                    'description' => __( 'Array of JavaScript file URLs to load.', 'headless-elementor' ),
                    'items'       => array( 'type' => 'string', 'format' => 'uri' ),
                ),
                'config' => array(
                    'type'        => array( 'string', 'object' ),
                    'description' => __( 'Elementor frontend configuration.', 'headless-elementor' ),
                ),
                'proConfig' => array(
                    'type'        => array( 'string', 'object', 'null' ),
                    'description' => __( 'Elementor Pro frontend configuration (if Pro is active).', 'headless-elementor' ),
                ),
            ),
        );
    }
}
