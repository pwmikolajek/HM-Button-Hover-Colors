<?php
/**
 * Plugin Name: Button Hover Colors
 * Description: Per-block and global hover background colors for core/button blocks.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 7.2
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Button_Hover_Colors {

	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'wp_enqueue_scripts',          array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_head',                     array( $this, 'output_global_css' ), 20 );
		add_filter( 'render_block_core/button',    array( $this, 'inject_hover_style' ), 10, 2 );
		add_action( 'rest_api_init',               array( $this, 'register_rest_routes' ) );
	}

	// -------------------------------------------------------------------------
	// Editor assets
	// -------------------------------------------------------------------------

	public function enqueue_editor_assets() {
		wp_enqueue_script(
			'bhc-editor',
			plugin_dir_url( __FILE__ ) . 'assets/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-compose', 'wp-hooks', 'wp-plugins', 'wp-api-fetch', 'wp-data' ),
			'1.0.0',
			true
		);

		wp_localize_script( 'bhc-editor', 'bhcData', array(
			'fillHoverBg'      => get_option( 'bhc_fill_hover_bg', '' ),
			'outlineHoverBg'   => get_option( 'bhc_outline_hover_bg', '' ),
			'fillHoverText'    => get_option( 'bhc_fill_hover_text', '' ),
			'restUrl'          => rest_url( 'bhc/v1/globals' ),
			'nonce'            => wp_create_nonce( 'wp_rest' ),
		) );
	}

	// -------------------------------------------------------------------------
	// REST API — read / write global hover options
	// -------------------------------------------------------------------------

	public function register_rest_routes() {
		register_rest_route( 'bhc/v1', '/globals', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_globals' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_save_globals' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
				'args' => array(
					'fillHoverBg'    => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_hex_color', 'default' => '' ),
					'outlineHoverBg' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_hex_color', 'default' => '' ),
					'fillHoverText'  => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_hex_color', 'default' => '' ),
				),
			),
		) );
	}

	public function rest_get_globals() {
		return rest_ensure_response( array(
			'fillHoverBg'    => get_option( 'bhc_fill_hover_bg', '' ),
			'outlineHoverBg' => get_option( 'bhc_outline_hover_bg', '' ),
			'fillHoverText'  => get_option( 'bhc_fill_hover_text', '' ),
		) );
	}

	public function rest_save_globals( $request ) {
		update_option( 'bhc_fill_hover_bg',    $request->get_param( 'fillHoverBg' ) );
		update_option( 'bhc_outline_hover_bg', $request->get_param( 'outlineHoverBg' ) );
		update_option( 'bhc_fill_hover_text',  $request->get_param( 'fillHoverText' ) );
		return rest_ensure_response( array( 'success' => true ) );
	}

	// -------------------------------------------------------------------------
	// Frontend assets
	// -------------------------------------------------------------------------

	public function enqueue_frontend_assets() {
		wp_enqueue_style(
			'bhc-frontend',
			plugin_dir_url( __FILE__ ) . 'assets/frontend.css',
			array(),
			'1.0.0'
		);
	}

	// -------------------------------------------------------------------------
	// Global CSS custom properties in <head>
	// -------------------------------------------------------------------------

	public function output_global_css() {
		$fill_hover_bg   = sanitize_hex_color( get_option( 'bhc_fill_hover_bg', '' ) );
		$outline_hover   = sanitize_hex_color( get_option( 'bhc_outline_hover_bg', '' ) );
		$fill_hover_text = sanitize_hex_color( get_option( 'bhc_fill_hover_text', '' ) );

		if ( ! $fill_hover_bg && ! $outline_hover && ! $fill_hover_text ) {
			return;
		}

		$props = '';
		if ( $fill_hover_bg ) {
			$props .= '--bhc-fill-hover-bg:' . $fill_hover_bg . ';';
		}
		if ( $outline_hover ) {
			$props .= '--bhc-outline-hover-bg:' . $outline_hover . ';';
		}
		if ( $fill_hover_text ) {
			$props .= '--bhc-fill-hover-text:' . $fill_hover_text . ';';
		}

		echo '<style id="bhc-global-defaults">:root{' . $props . '}</style>' . "\n";
	}

	// -------------------------------------------------------------------------
	// Inject per-block hover color into rendered HTML
	// -------------------------------------------------------------------------

	public function inject_hover_style( $block_content, $block ) {
		$bg_color   = isset( $block['attrs']['hoverBackgroundColor'] )
			? sanitize_hex_color( $block['attrs']['hoverBackgroundColor'] )
			: '';
		$text_color = isset( $block['attrs']['hoverTextColor'] )
			? sanitize_hex_color( $block['attrs']['hoverTextColor'] )
			: '';

		if ( ! $bg_color && ! $text_color ) {
			return $block_content;
		}

		$inline = '';
		if ( $bg_color ) {
			$inline .= '--bhc-hover-bg:' . $bg_color . ';';
		}
		if ( $text_color ) {
			$inline .= '--bhc-hover-text:' . $text_color . ';';
		}

		// Inject CSS vars onto the <a> tag so they live on the same element
		// as WordPress's inline background-color — required for !important override.
		if ( preg_match( '/<a\b[^>]*style="([^"]*)"/i', $block_content, $m ) ) {
			// Append to existing style attribute.
			$block_content = preg_replace(
				'/(<a\b[^>]*style=")([^"]*)(")/i',
				'$1$2' . $inline . '$3',
				$block_content,
				1
			);
		} else {
			// Insert a new style attribute before the class attribute on the <a>.
			$block_content = preg_replace(
				'/(<a\b)/i',
				'$1 style="' . $inline . '"',
				$block_content,
				1
			);
		}

		return $block_content;
	}

}

new Button_Hover_Colors();
