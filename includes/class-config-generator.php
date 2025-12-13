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
 * Generates Elementor frontend configuration objects.
 */
class Config_Generator {

	/**
	 * Get Elementor frontend config.
	 *
	 * @param int $post_id Post ID.
	 * @return array Frontend config array.
	 */
	public function get_frontend_config( $post_id ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return array();
		}

		try {
			$frontend   = \Elementor\Plugin::instance()->frontend;
			$reflection = new \ReflectionClass( $frontend );

			if ( ! $reflection->hasMethod( 'get_init_settings' ) ) {
				return array();
			}

			$method = $reflection->getMethod( 'get_init_settings' );
			$method->setAccessible( true );
			$settings = $method->invoke( $frontend );
		} catch ( \Exception $e ) {
			return array();
		}

		// Add post data.
		$settings['post'] = array(
			'id'      => $post_id,
			'title'   => get_the_title( $post_id ),
			'excerpt' => get_the_excerpt( $post_id ),
		);

		return $settings;
	}

	/**
	 * Get Elementor Pro frontend config.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Pro config array or null if Pro not active.
	 */
	public function get_pro_config( $post_id ) {
		if ( ! class_exists( '\ElementorPro\Plugin' ) ) {
			return null;
		}

		$assets_url = defined( 'ELEMENTOR_PRO_ASSETS_URL' ) ? ELEMENTOR_PRO_ASSETS_URL : '';
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using Elementor Pro's filter.
		$assets_url = apply_filters( 'elementor_pro/frontend/assets_url', $assets_url );

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

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using Elementor Pro's filter.
		$locale_settings = apply_filters( 'elementor_pro/frontend/localize_settings', $locale_settings );

		return $locale_settings;
	}
}
