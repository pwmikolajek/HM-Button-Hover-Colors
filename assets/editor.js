(function () {
	'use strict';

	var addFilter                           = wp.hooks.addFilter;
	var createElement                       = wp.element.createElement;
	var Fragment                            = wp.element.Fragment;
	var createHigherOrderComponent          = wp.compose.createHigherOrderComponent;
	var InspectorControls                   = wp.blockEditor.InspectorControls;
	var ColorGradientSettingsDropdown       = wp.blockEditor.__experimentalColorGradientSettingsDropdown;
	var useMultipleOriginColorsAndGradients = wp.blockEditor.__experimentalUseMultipleOriginColorsAndGradients;
	// useStyleOverride injects CSS into the editor iframe (WP 6.3+).
	// Fall back to a no-op so it can always be called unconditionally.
	var useStyleOverride = wp.blockEditor.useStyleOverride || function () {};

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
	// Filter 2: Add hover controls inside Styles > Color section
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

}());
