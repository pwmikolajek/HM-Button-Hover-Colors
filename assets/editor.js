(function () {
	'use strict';

	var addFilter                           = wp.hooks.addFilter;
	var createElement                       = wp.element.createElement;
	var Fragment                            = wp.element.Fragment;
	var useState                            = wp.element.useState;
	var useEffect                           = wp.element.useEffect;
	var useCallback                         = wp.element.useCallback;
	var createPortal                        = wp.element.createPortal;
	var createHigherOrderComponent          = wp.compose.createHigherOrderComponent;
	var InspectorControls                   = wp.blockEditor.InspectorControls;
	var ColorGradientSettingsDropdown       = wp.blockEditor.__experimentalColorGradientSettingsDropdown;
	var useMultipleOriginColorsAndGradients = wp.blockEditor.__experimentalUseMultipleOriginColorsAndGradients;
	var useStyleOverride                    = wp.blockEditor.useStyleOverride || function () {};
	var registerPlugin                      = wp.plugins.registerPlugin;
	var apiFetch                            = wp.apiFetch;

	// -------------------------------------------------------------------------
	// Filter 1: Register hover color attributes on core/button
	// -------------------------------------------------------------------------

	addFilter(
		'blocks.registerBlockType',
		'bhc/add-hover-attributes',
		function ( settings, name ) {
			if ( name !== 'core/button' ) {
				return settings;
			}

			return Object.assign( {}, settings, {
				attributes: Object.assign( {}, settings.attributes, {
					hoverBackgroundColor: {
						type: 'string',
						default: '',
					},
					hoverTextColor: {
						type: 'string',
						default: '',
					},
				} ),
			} );
		}
	);

	// -------------------------------------------------------------------------
	// Filter 2: Add hover controls inside Styles > Color section (block editor)
	// -------------------------------------------------------------------------

	addFilter(
		'editor.BlockEdit',
		'bhc/hover-color-controls',
		createHigherOrderComponent( function ( BlockEdit ) {
			return function ( props ) {
				if ( props.name !== 'core/button' ) {
					return createElement( BlockEdit, props );
				}

				var hoverBg   = props.attributes.hoverBackgroundColor || '';
				var hoverText = props.attributes.hoverTextColor || '';

				// Inject hover CSS into the editor iframe, scoped to this block.
				var editorCss = '';
				if ( hoverBg || hoverText ) {
					editorCss +=
						'[data-block="' + props.clientId + '"] .wp-block-button__link {' +
						'transition: background-color 0.2s ease, color 0.2s ease;' +
						'}';
				}
				if ( hoverBg ) {
					editorCss +=
						'[data-block="' + props.clientId + '"] .wp-block-button__link:hover {' +
						'background-color: ' + hoverBg + ' !important;' +
						'}';
				}
				if ( hoverText ) {
					editorCss +=
						'[data-block="' + props.clientId + '"] .wp-block-button__link:hover {' +
						'color: ' + hoverText + ' !important;' +
						'}';
				}
				useStyleOverride( { id: 'bhc-' + props.clientId, css: editorCss } );

				// useMultipleOriginColorsAndGradients supplies the theme color palette
				var colorProps = useMultipleOriginColorsAndGradients
					? useMultipleOriginColorsAndGradients()
					: {};

				var settings = [
					{
						label: 'Text Hover',
						colorValue: hoverText,
						onColorChange: function ( color ) {
							props.setAttributes( { hoverTextColor: color || '' } );
						},
						isShownByDefault: true,
						resetAllFilter: function () {
							props.setAttributes( { hoverTextColor: '' } );
						},
					},
					{
						label: 'Background Hover',
						colorValue: hoverBg,
						onColorChange: function ( color ) {
							props.setAttributes( { hoverBackgroundColor: color || '' } );
						},
						isShownByDefault: true,
						resetAllFilter: function () {
							props.setAttributes( { hoverBackgroundColor: '' } );
						},
					},
				];

				var colorControls;

				if ( ColorGradientSettingsDropdown && colorProps.colors ) {
					// Native-style dropdowns matching core Text / Background rows
					colorControls = createElement(
						InspectorControls,
						{ group: 'color' },
						settings.map( function ( setting, index ) {
							return createElement( ColorGradientSettingsDropdown, Object.assign(
								{},
								colorProps,
								{
									key: index,
									panelId: props.clientId,
									settings: [ setting ],
									__experimentalIsRenderedInSidebar: true,
									disableCustomColors: false,
									disableCustomGradients: true,
								}
							) );
						} )
					);
				} else {
					// Graceful fallback: simple ColorPalette controls
					var ColorPalette = wp.components.ColorPalette;
					colorControls = createElement(
						InspectorControls,
						{ group: 'color' },
						createElement(
							'div',
							{ style: { padding: '8px 16px' } },
							createElement( 'p', { style: { fontWeight: 600, marginBottom: 4 } }, 'Text Hover' ),
							createElement( ColorPalette, {
								value: hoverText,
								onChange: function ( c ) { props.setAttributes( { hoverTextColor: c || '' } ); },
								clearable: true,
							} ),
							createElement( 'p', { style: { fontWeight: 600, marginTop: 12, marginBottom: 4 } }, 'Background Hover' ),
							createElement( ColorPalette, {
								value: hoverBg,
								onChange: function ( c ) { props.setAttributes( { hoverBackgroundColor: c || '' } ); },
								clearable: true,
							} )
						)
					);
				}

				return createElement(
					Fragment,
					null,
					createElement( BlockEdit, props ),
					colorControls
				);
			};
		}, 'bhcHoverColorControls' )
	);

	// -------------------------------------------------------------------------
	// Filter 3: Persist hover vars in saved block props (static render fallback)
	// -------------------------------------------------------------------------

	addFilter(
		'blocks.getSaveContent.extraProps',
		'bhc/save-hover-props',
		function ( props, blockType, attributes ) {
			if ( blockType.name !== 'core/button' ) {
				return props;
			}

			var extra = {};
			if ( attributes.hoverBackgroundColor ) {
				extra['--bhc-hover-bg'] = attributes.hoverBackgroundColor;
			}
			if ( attributes.hoverTextColor ) {
				extra['--bhc-hover-text'] = attributes.hoverTextColor;
			}

			if ( ! Object.keys( extra ).length ) {
				return props;
			}

			return Object.assign( {}, props, {
				style: Object.assign( {}, props.style || {}, extra ),
			} );
		}
	);

	// -------------------------------------------------------------------------
	// Global hover color panel — renders inside Styles → Blocks → Button
	// via a React portal mounted into the ScreenBlock container.
	// -------------------------------------------------------------------------

	/**
	 * The color picker panel content — same ColorGradientSettingsDropdown
	 * interface as the per-block controls but reads/writes wp_options via REST.
	 */
	function GlobalHoverPanel( props ) {
		var colorProps = useMultipleOriginColorsAndGradients
			? useMultipleOriginColorsAndGradients()
			: {};

		var panelId = 'bhc-global-hover';

		var settings = [
			{
				label: 'Text Hover',
				colorValue: props.hoverText,
				onColorChange: function ( color ) {
					props.onChange( { hoverText: color || '', hoverBg: props.hoverBg } );
				},
				isShownByDefault: true,
				resetAllFilter: function () {
					props.onChange( { hoverText: '', hoverBg: props.hoverBg } );
				},
			},
			{
				label: 'Background Hover',
				colorValue: props.hoverBg,
				onColorChange: function ( color ) {
					props.onChange( { hoverBg: color || '', hoverText: props.hoverText } );
				},
				isShownByDefault: true,
				resetAllFilter: function () {
					props.onChange( { hoverBg: '', hoverText: props.hoverText } );
				},
			},
		];

		if ( ColorGradientSettingsDropdown && colorProps.colors ) {
			return createElement(
				// Wrap in a div matching WP's tools panel structure so it blends in
				'div',
				{ className: 'bhc-global-hover-panel' },
				createElement(
					wp.components.__experimentalToolsPanel,
					{
						label: 'Hover Colors',
						resetAll: function () {
							props.onChange( { hoverText: '', hoverBg: '' } );
						},
						panelId: panelId,
					},
					settings.map( function ( setting, index ) {
						return createElement( ColorGradientSettingsDropdown, Object.assign(
							{},
							colorProps,
							{
								key: index,
								panelId: panelId,
								settings: [ setting ],
								__experimentalIsRenderedInSidebar: true,
								disableCustomColors: false,
								disableCustomGradients: true,
							}
						) );
					} )
				)
			);
		}

		// Fallback
		var ColorPalette = wp.components.ColorPalette;
		return createElement(
			'div',
			{ style: { padding: '16px' } },
			createElement( 'h2', { style: { fontSize: '11px', fontWeight: 500, textTransform: 'uppercase', marginBottom: '12px' } }, 'Hover Colors' ),
			createElement( 'p', { style: { fontWeight: 600, marginBottom: 4, fontSize: '12px' } }, 'Text Hover' ),
			createElement( ColorPalette, {
				value: props.hoverText,
				onChange: function ( c ) { props.onChange( { hoverText: c || '', hoverBg: props.hoverBg } ); },
				clearable: true,
			} ),
			createElement( 'p', { style: { fontWeight: 600, marginTop: 12, marginBottom: 4, fontSize: '12px' } }, 'Background Hover' ),
			createElement( ColorPalette, {
				value: props.hoverBg,
				onChange: function ( c ) { props.onChange( { hoverBg: c || '', hoverText: props.hoverText } ); },
				clearable: true,
			} )
		);
	}

	/**
	 * Plugin component — mounts a portal into the Styles → Blocks → Button
	 * screen by observing the DOM for the block preview panel container.
	 *
	 * Detection: the preview panel div has class
	 * `edit-site-global-styles__block-preview-panel` and its sibling
	 * `.edit-site-global-styles-screen` contains all the style panels.
	 * We look for the heading text "Button" in the screen header to confirm
	 * we're on the core/button styles screen.
	 */
	function BhcGlobalStylesPlugin() {
		var _useState   = useState( null );
		var portalTarget = _useState[0];
		var setPortalTarget = _useState[1];

		var _useState2  = useState( ( window.bhcData && window.bhcData.fillHoverText ) || '' );
		var hoverText   = _useState2[0];
		var setHoverText = _useState2[1];

		var _useState3  = useState( ( window.bhcData && window.bhcData.fillHoverBg ) || '' );
		var hoverBg     = _useState3[0];
		var setHoverBg  = _useState3[1];

		// Mutable ref for debounce timer (avoids re-renders)
		var saveTimerRef = wp.element.useRef( null );

		var save = function ( values ) {
			var newText = values.hoverText !== undefined ? values.hoverText : hoverText;
			var newBg   = values.hoverBg   !== undefined ? values.hoverBg   : hoverBg;

			// Update local state immediately for responsive UI
			if ( values.hoverText !== undefined ) setHoverText( newText );
			if ( values.hoverBg   !== undefined ) setHoverBg( newBg );

			// Debounced REST save
			clearTimeout( saveTimerRef.current );
			saveTimerRef.current = setTimeout( function () {
				var data = window.bhcData || {};
				apiFetch( {
					url: data.restUrl,
					method: 'POST',
					headers: { 'X-WP-Nonce': data.nonce },
					data: {
						fillHoverText:  newText,
						fillHoverBg:    newBg,
						outlineHoverBg: data.outlineHoverBg || '',
					},
				} );
			}, 600 );
		};

		useEffect( function () {
			var container = null;

			function getButtonScreen() {
				// ScreenBlock renders as a Fragment inside a navigator screen div:
				// .edit-site-global-styles-sidebar__navigator-screen
				// We detect it by finding the h2.edit-site-global-styles-header
				// whose text is "Button" and that has a sibling block preview panel.
				var headings = document.querySelectorAll( 'h2.edit-site-global-styles-header' );
				for ( var i = 0; i < headings.length; i++ ) {
					if ( headings[ i ].textContent.trim() === 'Button' ) {
						var screen = headings[ i ].closest(
							'.edit-site-global-styles-sidebar__navigator-screen'
						);
						if ( screen && screen.querySelector( '.edit-site-global-styles__block-preview-panel' ) ) {
							return screen;
						}
					}
				}
				return null;
			}

			function mountPortal() {
				var screen = getButtonScreen();

				if ( ! screen ) {
					if ( container ) {
						container.remove();
						container = null;
						setPortalTarget( null );
					}
					return;
				}

				// Already mounted in this screen
				if ( container && screen.contains( container ) ) return;

				// Remove stale container if screen changed
				if ( container ) {
					container.remove();
					container = null;
					setPortalTarget( null );
				}

				container = document.createElement( 'div' );
				container.className = 'bhc-global-styles-portal';
				screen.appendChild( container );
				setPortalTarget( container );
			}

			// Watch for DOM changes to detect navigation between screens
			var observer = new MutationObserver( mountPortal );
			observer.observe( document.body, { childList: true, subtree: true } );

			// Run once immediately in case we start on the button screen
			mountPortal();

			return function () {
				observer.disconnect();
				if ( container ) container.remove();
			};
		}, [] );

		if ( ! portalTarget ) {
			return null;
		}

		return createPortal(
			createElement( GlobalHoverPanel, {
				hoverText: hoverText,
				hoverBg:   hoverBg,
				onChange:  save,
			} ),
			portalTarget
		);
	}

	registerPlugin( 'bhc-global-styles', {
		render: BhcGlobalStylesPlugin,
	} );

}());
