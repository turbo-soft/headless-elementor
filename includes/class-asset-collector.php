<?php
/**
 * Asset collector for Elementor CSS and JS.
 *
 * @package Headless_Elementor
 */

namespace Headless_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Collects and manages Elementor assets (CSS/JS).
 */
class Asset_Collector {

    /**
     * Cache for used widget types per post.
     *
     * @var array
     */
    private $widget_cache = array();

    /**
     * Enable page-specific assets in Elementor.
     *
     * This method replicates what Elementor's Frontend::handle_page_assets() does,
     * enabling conditional assets based on which widgets are used on the page.
     *
     * @param int $post_id Post ID.
     */
    public function enable_page_assets( $post_id ) {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return;
        }

        $elementor = \Elementor\Plugin::instance();

        // Enable page-specific assets from meta (stored by Elementor when page is saved).
        // This includes widget-specific styles and scripts like 'e-swiper', animations, etc.
        if ( class_exists( '\Elementor\Core\Base\Elements_Iteration_Actions\Assets' ) ) {
            $page_assets = get_post_meta(
                $post_id,
                \Elementor\Core\Base\Elements_Iteration_Actions\Assets::ASSETS_META_KEY,
                true
            );

            if ( ! empty( $page_assets ) ) {
                // Enable assets via the assets loader if available.
                if ( isset( $elementor->assets_loader ) ) {
                    $elementor->assets_loader->enable_assets( $page_assets );
                }

                // Also manually enqueue styles/scripts from page assets.
                if ( ! empty( $page_assets['styles'] ) ) {
                    foreach ( $page_assets['styles'] as $style_handle ) {
                        wp_enqueue_style( $style_handle );
                    }
                }
                if ( ! empty( $page_assets['scripts'] ) ) {
                    foreach ( $page_assets['scripts'] as $script_handle ) {
                        wp_enqueue_script( $script_handle );
                    }
                }
            }
        }

        // Register and enqueue frontend styles.
        $elementor->frontend->register_styles();
        $elementor->frontend->enqueue_styles();

        // Check for widgets that need special styles (e.g., Swiper).
        $document = $elementor->documents->get( $post_id );
        if ( $document && $document->is_built_with_elementor() ) {
            $used_widgets   = $this->get_used_widget_types( $document );
            $swiper_widgets = array( 'image-carousel', 'testimonial-carousel', 'slides', 'media-carousel' );

            if ( array_intersect( $used_widgets, $swiper_widgets ) ) {
                wp_enqueue_style( 'swiper' );
            }
        }
    }

    /**
     * Collect all enqueued style URLs.
     *
     * Includes enqueued WordPress styles, the generated Elementor post CSS file,
     * and the active Kit CSS file (global colors, typography, theme styles).
     *
     * @param int $post_id Post ID.
     * @return array Array of CSS file URLs.
     */
    public function collect_styles( $post_id = 0 ) {
        $wp_styles = wp_styles();
        $collected = array();

        // Collect Kit (Global) CSS first - this provides CSS variables and theme styles.
        $kit_css_url = $this->get_kit_css_url();
        if ( $kit_css_url ) {
            $collected[] = $kit_css_url;
        }

        // Collect enqueued styles from WordPress.
        foreach ( $wp_styles->queue as $handle ) {
            if ( ! isset( $wp_styles->registered[ $handle ] ) ) {
                continue;
            }

            $src = $wp_styles->registered[ $handle ]->src;

            if ( empty( $src ) ) {
                continue;
            }

            $collected[] = $this->normalize_url( $src );
        }

        // Also check for generated CSS file (when using external file mode).
        // Elementor can output CSS as either inline or external file based on
        // the 'elementor_css_print_method' option.
        if ( $post_id && class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
            $css_file = \Elementor\Core\Files\CSS\Post::create( $post_id );
            $meta     = $css_file->get_meta();

            // If status is 'file', there's an external CSS file to include.
            if ( isset( $meta['status'] ) && 'file' === $meta['status'] ) {
                $css_url = $css_file->get_url();
                if ( $css_url && ! in_array( $css_url, $collected, true ) ) {
                    $collected[] = $css_url;
                }
            }
        }

        return array_values( array_unique( $collected ) );
    }

    /**
     * Get the active Kit data including ID and CSS.
     *
     * The Kit contains global styles: colors, typography, buttons, form fields, etc.
     * These are site-wide styles that apply to all Elementor pages via a wrapper class.
     *
     * @return array Kit data with 'id', 'cssUrl', and 'inlineCss' keys.
     */
    public function get_kit_data() {
        $data = array(
            'id'        => null,
            'cssUrl'    => null,
            'inlineCss' => '',
        );

        if ( ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
            return $data;
        }

        $kit = \Elementor\Plugin::instance()->kits_manager->get_active_kit_for_frontend();

        if ( ! $kit || ! $kit->get_id() ) {
            return $data;
        }

        $data['id'] = $kit->get_id();

        $css_file = \Elementor\Core\Files\CSS\Post::create( $kit->get_id() );
        $meta     = $css_file->get_meta();

        if ( isset( $meta['status'] ) && 'file' === $meta['status'] ) {
            // External file mode - return URL.
            $data['cssUrl'] = $css_file->get_url();
        } else {
            // Inline mode - return CSS content.
            $data['inlineCss'] = $css_file->get_content();
        }

        return $data;
    }

    /**
     * Get the active Kit's CSS file URL.
     *
     * The Kit contains global styles: colors, typography, buttons, form fields, etc.
     * These are site-wide styles that apply to all Elementor pages.
     *
     * @return string|null Kit CSS URL if available, null otherwise.
     */
    private function get_kit_css_url() {
        $kit_data = $this->get_kit_data();
        return $kit_data['cssUrl'];
    }

    /**
     * Get inline CSS for a specific post.
     *
     * Returns CSS content when Elementor is configured to use inline CSS,
     * or when the CSS file hasn't been generated yet. If using external
     * file mode and the file exists, returns empty string (the URL is
     * included in collect_styles instead).
     *
     * @param int $post_id Post ID.
     * @return string Inline CSS content.
     */
    public function get_inline_css( $post_id ) {
        if ( ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
            return '';
        }

        $css_file = \Elementor\Core\Files\CSS\Post::create( $post_id );
        $meta     = $css_file->get_meta();

        // If using external file mode and file exists, don't return inline CSS
        // (the file URL is already included via collect_styles).
        if ( isset( $meta['status'] ) && 'file' === $meta['status'] ) {
            return '';
        }

        // Return inline CSS content for inline mode or when file not generated.
        return $css_file->get_content();
    }

    /**
     * Collect all required JavaScript URLs.
     *
     * @param int $post_id Post ID.
     * @return array Array of JS file URLs.
     */
    public function collect_scripts( $post_id ) {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return array();
        }

        $elementor = \Elementor\Plugin::instance();
        $document  = $elementor->documents->get( $post_id );

        if ( ! $document || ! $document->is_built_with_elementor() ) {
            return array();
        }

        // Register Elementor Pro scripts if available.
        if ( class_exists( '\ElementorPro\Plugin' ) ) {
            $elementor_pro = \ElementorPro\Plugin::instance();
            if ( method_exists( $elementor_pro, 'enqueue_frontend_scripts' ) ) {
                $elementor_pro->enqueue_frontend_scripts();
            }
            if ( method_exists( $elementor_pro, 'register_frontend_scripts' ) ) {
                $elementor_pro->register_frontend_scripts();
            }
        }

        // Register Elementor core scripts.
        $elementor->frontend->register_scripts();

        // Core required handles.
        $required_handles = array(
            'elementor-webpack-runtime',
            'elementor-frontend-modules',
            'elementor-frontend',
        );

        // Add Pro handles if Pro is active.
        if ( class_exists( '\ElementorPro\Plugin' ) ) {
            $required_handles = array_merge(
                $required_handles,
                array(
                    'elementor-pro-webpack-runtime',
                    'elementor-pro-frontend',
                    'pro-elements-handlers',
                )
            );
        }

        // Add widget-specific script dependencies.
        $used_widgets = $this->get_used_widget_types( $document );

        foreach ( $used_widgets as $widget_type ) {
            $widget = $elementor->widgets_manager->get_widget_types( $widget_type );

            if ( $widget && method_exists( $widget, 'get_script_depends' ) ) {
                $widget_scripts = $widget->get_script_depends();

                foreach ( $widget_scripts as $script_handle ) {
                    $required_handles[] = $script_handle;
                }
            }
        }

        // Resolve all dependencies and collect URLs.
        $wp_scripts = wp_scripts();
        $urls       = array();
        $resolved   = array();

        foreach ( $required_handles as $handle ) {
            $this->resolve_script_dependencies( $handle, $wp_scripts, $urls, $resolved );
        }

        return array_values( array_unique( $urls ) );
    }

    /**
     * Recursively resolve script dependencies.
     *
     * @param string     $handle     Script handle.
     * @param \WP_Scripts $wp_scripts WordPress scripts object.
     * @param array      $urls       Collected URLs (by reference).
     * @param array      $resolved   Already resolved handles (by reference).
     */
    private function resolve_script_dependencies( $handle, $wp_scripts, &$urls, &$resolved ) {
        if ( isset( $resolved[ $handle ] ) ) {
            return;
        }

        if ( ! isset( $wp_scripts->registered[ $handle ] ) ) {
            $resolved[ $handle ] = true;
            return;
        }

        $script = $wp_scripts->registered[ $handle ];

        // Resolve dependencies first.
        foreach ( $script->deps as $dep ) {
            $this->resolve_script_dependencies( $dep, $wp_scripts, $urls, $resolved );
        }

        // Add this script's URL.
        if ( ! empty( $script->src ) ) {
            $urls[] = $this->normalize_url( $script->src );
        }

        $resolved[ $handle ] = true;
    }

    /**
     * Get all widget types used in a document.
     *
     * @param \Elementor\Core\Base\Document $document Elementor document.
     * @return array Array of widget type names.
     */
    public function get_used_widget_types( $document ) {
        $post_id = $document->get_main_id();

        // Return cached result if available.
        if ( isset( $this->widget_cache[ $post_id ] ) ) {
            return $this->widget_cache[ $post_id ];
        }

        $used = array();
        $data = $document->get_elements_data();

        $this->collect_widgets_recursive( $data, $used );

        $widget_types = array_keys( $used );

        // Cache the result.
        $this->widget_cache[ $post_id ] = $widget_types;

        return $widget_types;
    }

    /**
     * Recursively collect widget types from elements data.
     *
     * @param array $elements Elements data.
     * @param array $used     Used widgets (by reference).
     */
    private function collect_widgets_recursive( $elements, &$used ) {
        foreach ( $elements as $element ) {
            if ( ! empty( $element['widgetType'] ) ) {
                $used[ $element['widgetType'] ] = true;
            }

            if ( ! empty( $element['elements'] ) ) {
                $this->collect_widgets_recursive( $element['elements'], $used );
            }
        }
    }

    /**
     * Normalize URL to absolute form.
     *
     * @param string $url URL to normalize.
     * @return string Normalized URL.
     */
    private function normalize_url( $url ) {
        if ( strpos( $url, 'http' ) === 0 || strpos( $url, '//' ) === 0 ) {
            return $url;
        }

        return site_url( $url );
    }

    /**
     * Clear the widget cache.
     */
    public function clear_cache() {
        $this->widget_cache = array();
    }
}
