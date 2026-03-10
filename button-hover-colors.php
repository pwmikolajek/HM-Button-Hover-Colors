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
		add_action( 'admin_menu',                  array( $this, 'register_settings_page' ) );
		add_action( 'admin_init',                  array( $this, 'register_settings' ) );
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

		wp_localize_script( 'bhc-editor', 'bhcData', array(
			'fillHoverBg'      => get_option( 'bhc_fill_hover_bg', '' ),
			'outlineHoverBg'   => get_option( 'bhc_outline_hover_bg', '' ),
			'fillHoverText'    => get_option( 'bhc_fill_hover_text', '' ),
		) );
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

	// -------------------------------------------------------------------------
	// Admin settings page
	// -------------------------------------------------------------------------

	public function register_settings_page() {
		add_options_page(
			__( 'Button Hover Colors', 'button-hover-colors' ),
			__( 'Button Hover Colors', 'button-hover-colors' ),
			'manage_options',
			'button-hover-colors',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'bhc_settings', 'bhc_fill_hover_bg', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '',
		) );

		register_setting( 'bhc_settings', 'bhc_outline_hover_bg', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '',
		) );

		register_setting( 'bhc_settings', 'bhc_fill_hover_text', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '',
		) );

		add_settings_section(
			'bhc_main',
			__( 'Global Defaults', 'button-hover-colors' ),
			array( $this, 'render_section_description' ),
			'button-hover-colors'
		);

		add_settings_field(
			'bhc_fill_hover_bg',
			__( 'Fill Button Hover Background', 'button-hover-colors' ),
			array( $this, 'render_fill_field' ),
			'button-hover-colors',
			'bhc_main'
		);

		add_settings_field(
			'bhc_outline_hover_bg',
			__( 'Outline Button Hover Background', 'button-hover-colors' ),
			array( $this, 'render_outline_field' ),
			'button-hover-colors',
			'bhc_main'
		);

		add_settings_field(
			'bhc_fill_hover_text',
			__( 'Button Hover Text Color', 'button-hover-colors' ),
			array( $this, 'render_fill_text_field' ),
			'button-hover-colors',
			'bhc_main'
		);
	}

	public function render_section_description() {
		echo '<p>' . esc_html__( 'Set site-wide hover background colors for button blocks. Individual buttons can override these defaults using the Hover Color panel in the block sidebar.', 'button-hover-colors' ) . '</p>';
	}

	public function render_fill_field() {
		$value = get_option( 'bhc_fill_hover_bg', '' );
		$this->render_color_field( 'bhc_fill_hover_bg', $value );
	}

	public function render_outline_field() {
		$value = get_option( 'bhc_outline_hover_bg', '' );
		$this->render_color_field( 'bhc_outline_hover_bg', $value );
	}

	public function render_fill_text_field() {
		$value = get_option( 'bhc_fill_hover_text', '' );
		$this->render_color_field( 'bhc_fill_hover_text', $value );
	}

	private function render_color_field( $name, $value ) {
		$id          = esc_attr( $name );
		$safe_value  = esc_attr( $value );
		$input_value = $value ? $safe_value : '#000000';
		?>
		<div style="display:flex;align-items:center;gap:10px;">
			<input
				type="color"
				id="<?php echo $id; ?>_picker"
				value="<?php echo esc_attr( $input_value ); ?>"
				<?php if ( ! $value ) : ?>data-empty="1"<?php endif; ?>
				style="width:50px;height:36px;padding:2px;border:1px solid #8c8f94;border-radius:3px;cursor:pointer;"
			>
			<input
				type="text"
				id="<?php echo $id; ?>"
				name="<?php echo $id; ?>"
				value="<?php echo $safe_value; ?>"
				placeholder="<?php esc_attr_e( 'e.g. #FF5733 — leave blank to disable', 'button-hover-colors' ); ?>"
				style="width:220px;"
				class="regular-text"
			>
			<button type="button" class="button bhc-clear-color" data-target="<?php echo $id; ?>"><?php esc_html_e( 'Clear', 'button-hover-colors' ); ?></button>
		</div>
		<script>
		(function() {
			var picker = document.getElementById('<?php echo $id; ?>_picker');
			var text   = document.getElementById('<?php echo $id; ?>');
			if (!picker || !text) return;

			picker.addEventListener('input', function() {
				text.value = picker.value;
				picker.removeAttribute('data-empty');
			});

			text.addEventListener('input', function() {
				var v = text.value.trim();
				if (/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/.test(v)) {
					picker.value = v;
					picker.removeAttribute('data-empty');
				}
			});

			var clearBtn = document.querySelector('.bhc-clear-color[data-target="<?php echo $id; ?>"]');
			if (clearBtn) {
				clearBtn.addEventListener('click', function() {
					text.value = '';
					picker.value = '#000000';
					picker.setAttribute('data-empty', '1');
				});
			}
		})();
		</script>
		<?php
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'These colors act as site-wide defaults. You can override the hover color for any individual button block using the "Hover Color" panel in the block editor sidebar.', 'button-hover-colors' ); ?></p>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'bhc_settings' );
				do_settings_sections( 'button-hover-colors' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}

new Button_Hover_Colors();
