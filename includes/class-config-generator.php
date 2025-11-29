<?php
/**
 * Config generator for Elementor frontend configuration.
 *
 * @package Headless_Elementor
 */

namespace Headless_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generates Elementor and Elementor Pro frontend configurations.
 */
class Config_Generator {

    /**
     * Get Elementor frontend configuration.
     *
     * Uses the public get_settings() method from Elementor's Frontend class,
     * which internally calls the protected get_init_settings() via lazy loading.
     *
     * @param int  $post_id         Post ID.
     * @param bool $as_script_tag   Whether to return as script tag.
     * @return string|array Script tag or config array.
     */
    public function get_frontend_config( $post_id, $as_script_tag = true ) {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return $as_script_tag ? '' : array();
        }

        // Use the public get_settings() method - no reflection needed.
        // This method is defined in Base_Object and calls get_init_settings() internally.
        $settings = \Elementor\Plugin::instance()->frontend->get_settings();

        // If settings are empty (edge case), use fallback.
        if ( empty( $settings ) ) {
            $settings = $this->get_fallback_settings();
        }

        // Add post-specific data.
        $post             = get_post( $post_id );
        $settings['post'] = array(
            'id'      => $post_id,
            'title'   => $post ? get_the_title( $post ) : '',
            'excerpt' => $post ? get_the_excerpt( $post ) : '',
        );

        if ( $as_script_tag ) {
            return '<script>var elementorFrontendConfig = ' . wp_json_encode( $settings ) . ';</script>';
        }

        return $settings;
    }

    /**
     * Get Elementor Pro frontend configuration.
     *
     * Uses the same filter that Elementor Pro modules use to inject their settings,
     * ensuring we get complete configuration including popup, lottie, woocommerce, etc.
     *
     * @param int  $post_id         Post ID.
     * @param bool $as_script_tag   Whether to return as script tag.
     * @return string|array|null Script tag, config array, or null if Pro not active.
     */
    public function get_pro_config( $post_id, $as_script_tag = true ) {
        if ( ! class_exists( '\ElementorPro\Plugin' ) ) {
            return $as_script_tag ? '' : null;
        }

        if ( ! defined( 'ELEMENTOR_PRO_ASSETS_URL' ) ) {
            return $as_script_tag ? '' : null;
        }

        $assets_url = ELEMENTOR_PRO_ASSETS_URL;
        $assets_url = apply_filters( 'elementor_pro/frontend/assets_url', $assets_url );

        // Build base config matching Elementor Pro's enqueue_frontend_scripts().
        $locale_settings = array(
            'ajaxurl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'elementor-pro-frontend' ),
            'urls'     => array(
                'assets' => $assets_url,
                'rest'   => get_rest_url(),
            ),
            'settings' => array(
                'lazy_load_background_images' => ( '1' === get_option( 'elementor_lazy_load_background_images', '1' ) ),
            ),
        );

        // Apply the same filter Pro uses - this gets all module configs
        // (popup, lottie, woocommerce, share buttons, facebook SDK, etc.)
        $locale_settings = apply_filters( 'elementor_pro/frontend/localize_settings', $locale_settings );

        if ( $as_script_tag ) {
            return '<script>var ElementorProFrontendConfig = ' . wp_json_encode( $locale_settings ) . ';</script>';
        }

        return $locale_settings;
    }

    /**
     * Get fallback settings if reflection fails.
     *
     * @return array
     */
    private function get_fallback_settings() {
        $elementor = \Elementor\Plugin::instance();

        return array(
            'environmentMode' => array(
                'edit'          => false,
                'wpPreview'     => false,
                'isScriptDebug' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
            ),
            'i18n'            => array(),
            'is_rtl'          => is_rtl(),
            'breakpoints'     => array(
                'xs'  => 0,
                'sm'  => 480,
                'md'  => 768,
                'lg'  => 1025,
                'xl'  => 1440,
                'xxl' => 1600,
            ),
            'version'         => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
            'is_static'       => false,
            'urls'            => array(
                'assets'    => defined( 'ELEMENTOR_ASSETS_URL' ) ? ELEMENTOR_ASSETS_URL : '',
                'ajaxurl'   => admin_url( 'admin-ajax.php' ),
                'uploadUrl' => wp_upload_dir()['baseurl'],
            ),
            'swiperClass'     => 'swiper',
            'settings'        => array(),
            'kit'             => array(),
        );
    }
}
