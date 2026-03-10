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
	}

	// -------------------------------------------------------------------------
	// Editor assets
	// -------------------------------------------------------------------------

	public function enqueue_editor_assets() {
		wp_enqueue_script(
			'bhc-editor',
			plugin_dir_url( __FILE__ ) . 'assets/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-compose', 'wp-hooks' ),
			'1.0.0',
			true
		);
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
			$block_content = preg_replace(
				'/(<a\b[^>]*style=")([^"]*)(")/i',
				'$1$2' . $inline . '$3',
				$block_content,
				1
			);
		} else {
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
