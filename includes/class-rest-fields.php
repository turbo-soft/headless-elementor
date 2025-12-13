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

        $current_step = 'initializing';

        try {
            $asset_collector  = $this->plugin->asset_collector;
            $config_generator = $this->plugin->config_generator;

            // Enable and collect assets.
            $current_step = 'enabling_page_assets';
            $asset_collector->enable_page_assets( $post_id );

            $current_step = 'collecting_styles';
            $style_links = $asset_collector->collect_styles( $post_id );

            $current_step = 'collecting_inline_css';
            $inline_css  = $asset_collector->get_inline_css( $post_id );

            $current_step = 'collecting_scripts';
            $scripts     = $asset_collector->collect_scripts( $post_id );

            // Get kit (global styles) data.
            $current_step = 'collecting_kit_data';
            $kit_data = $asset_collector->get_kit_data();

            // Generate configs.
            $current_step = 'generating_frontend_config';
            $config = $config_generator->get_frontend_config( $post_id );

            // Only include Pro config if enabled in settings (exposes nonce).
            $pro_config = null;
            if ( $this->plugin->get_setting( 'include_pro_config', false ) ) {
                $current_step = 'generating_pro_config';
                $pro_config = $config_generator->get_pro_config( $post_id );
            }

            $response = array(
                'isElementor' => true,
                'styleLinks'  => $style_links,
                'inlineCss'   => $inline_css,
                'scripts'     => $scripts,
                'config'      => $config,
                'proConfig'   => $pro_config,
                'kit'         => $kit_data,
            );

        } catch ( \Exception $e ) {
            $response          = $this->get_empty_response( true );
            $response['error'] = array(
                'code'    => 'asset_collection_failed',
                'message' => $e->getMessage(),
                'context' => array(
                    'post_id' => $post_id,
                    'step'    => $current_step,
                ),
            );
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
            'config'      => array(),
            'proConfig'   => null,
            'kit'         => array(
                'id'        => null,
                'cssUrl'    => null,
                'inlineCss' => '',
            ),
        );
    }

    /**
     * Get REST field schema.
     *
     * @return array
     */
    private function get_schema() {
        return array(
            'description' => __( 'Elementor assets for headless rendering.', 'headless-elementor' ),
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
                    'type'        => 'object',
                    'description' => __( 'Elementor frontend configuration object.', 'headless-elementor' ),
                ),
                'proConfig' => array(
                    'type'        => array( 'object', 'null' ),
                    'description' => __( 'Elementor Pro frontend configuration (if Pro is active).', 'headless-elementor' ),
                ),
                'kit' => array(
                    'type'        => 'object',
                    'description' => __( 'Elementor Kit (global styles) data.', 'headless-elementor' ),
                    'properties'  => array(
                        'id' => array(
                            'type'        => array( 'integer', 'null' ),
                            'description' => __( 'Kit post ID for wrapper class (elementor-kit-{id}).', 'headless-elementor' ),
                        ),
                        'cssUrl' => array(
                            'type'        => array( 'string', 'null' ),
                            'description' => __( 'Kit CSS file URL (if using external file mode).', 'headless-elementor' ),
                            'format'      => 'uri',
                        ),
                        'inlineCss' => array(
                            'type'        => 'string',
                            'description' => __( 'Kit inline CSS (if using inline mode).', 'headless-elementor' ),
                        ),
                    ),
                ),
                'error' => array(
                    'type'        => array( 'object', 'null' ),
                    'description' => __( 'Error details if asset collection failed.', 'headless-elementor' ),
                    'properties'  => array(
                        'code' => array(
                            'type'        => 'string',
                            'description' => __( 'Error code identifier.', 'headless-elementor' ),
                        ),
                        'message' => array(
                            'type'        => 'string',
                            'description' => __( 'Human-readable error message.', 'headless-elementor' ),
                        ),
                        'context' => array(
                            'type'        => 'object',
                            'description' => __( 'Additional context for debugging.', 'headless-elementor' ),
                            'properties'  => array(
                                'post_id' => array(
                                    'type'        => 'integer',
                                    'description' => __( 'Post ID being processed.', 'headless-elementor' ),
                                ),
                                'step' => array(
                                    'type'        => 'string',
                                    'description' => __( 'Processing step where error occurred.', 'headless-elementor' ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
    }
}
