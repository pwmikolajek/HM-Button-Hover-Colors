# Button Hover Colors

Gutenberg plugin that adds per-block hover colors (text & background) to `core/button` blocks, with controls integrated into the native Styles → Color panel.

## Features

- **Text Hover** and **Background Hover** color controls appear directly inside the existing **Styles → Color** panel — no extra panels or tabs
- Smooth CSS transition on hover, both on the frontend and inside the editor
- Supports both **Fill** and **Outline** button styles
- Works with Full Site Editing (FSE) and the Twenty Twenty-Five theme
- No build step — plain PHP and a single IIFE JavaScript file

## Requirements

- WordPress 6.7+
- PHP 7.2+

## Installation

1. Upload the `button-hover-colors` folder to `/wp-content/plugins/`
2. Activate the plugin in **Plugins → Installed Plugins**

## Usage

### Per-block

1. Select a Button block in the editor
2. Open the **Styles** tab (half-circle icon) in the block sidebar
3. Expand the **Color** section — **Text Hover** and **Background Hover** rows appear alongside the native Text and Background controls
4. Pick a color; the hover effect is immediately previewed in the editor

## How it works

- **CSS custom properties** — per-block colors are injected as `--bhc-hover-bg` / `--bhc-hover-text` inline on the `<a>` tag at render time via `render_block_core/button`
- `!important` is used on hover rules to override WordPress's own inline `style="background-color:..."` on the `<a>` tag
- **Editor preview** uses `wp.blockEditor.useStyleOverride` to inject scoped CSS into the editor iframe
- No core files are modified — hooks and filters only

## Known limitations

### Global defaults (Styles → Blocks → Button)

There is currently no clean way to add hover color controls inside the **Elements** panel on the Styles → Blocks → Button screen in the Site Editor.

The `ColorPanel` / `ColorToolsPanel` components that render the Text and Background rows in that screen use React context for item registration (`ToolsPanel` / `ToolsPanelItem`). React context only flows through the React component tree — it cannot be bridged from an external plugin component into an existing panel's tree. WordPress's `ColorPanel.children` prop is the correct extension point, but it is only accessible to the caller of `ColorPanel` (i.e. WordPress core's `ScreenBlock`), not to third-party plugins.

Using private APIs (`unlock(wp.blockEditor.privateApis)`) would allow this, but those APIs are deliberately gated to WordPress core modules and can be removed without notice in any future release.

**We are actively looking for a clean, stable solution** — if WordPress exposes a public API for this in a future release, global defaults will be added to the Site Editor.

## File structure

```
button-hover-colors/
├── button-hover-colors.php   # Main plugin: hooks, render filter
├── assets/
│   ├── editor.js             # Block editor filters (plain IIFE, no build)
│   └── frontend.css          # Hover CSS using custom properties
```

## License

GPL-2.0-or-later
