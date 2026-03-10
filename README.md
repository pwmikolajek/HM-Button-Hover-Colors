# Button Hover Colors

Gutenberg plugin that adds per-block and global hover colors (text & background) to `core/button` blocks, with controls integrated into the native Styles → Color panel.

## Features

- **Text Hover** and **Background Hover** color controls appear directly inside the existing **Styles → Color** panel — no extra panels or tabs
- Per-block colors override global defaults; blocks without a per-block color fall back to the global setting
- Smooth CSS transition on hover, both on the frontend and inside the editor
- Global defaults configurable under **Settings → Button Hover Colors**
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

### Global defaults

Go to **Settings → Button Hover Colors** to set site-wide fallback hover colors for Fill and Outline buttons. These apply to any button that has no per-block color set.

### Priority

```
Per-block color  >  Global default  >  No change
```

## How it works

- **CSS custom properties** — per-block colors are injected as `--bhc-hover-bg` / `--bhc-hover-text` inline on the `<a>` tag at render time via `render_block_core/button`
- **Global defaults** are output as `:root` CSS variables in `<head>` via `wp_head`
- **Editor preview** uses `wp.blockEditor.useStyleOverride` to inject scoped CSS into the editor iframe
- No core files are modified — hooks and filters only

## File structure

```
button-hover-colors/
├── button-hover-colors.php   # Main plugin: hooks, render filter, settings page
├── assets/
│   ├── editor.js             # Block editor filters (plain IIFE, no build)
│   └── frontend.css          # Hover CSS using custom properties
```

## License

GPL-2.0-or-later
