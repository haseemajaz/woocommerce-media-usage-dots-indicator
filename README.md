# Media Dot Indicator

A lightweight WordPress plugin that shows a **green dot** on images in the Media Library when those images are used in WooCommerce products — either as a featured image or inside a product gallery.

Hovering over the dot reveals a tooltip listing every product that uses the image, with a direct link to each product's edit screen.

---

## Features

- 🟢 Green dot overlaid on media thumbnails that are in use by WooCommerce products
- 🔗 Clickable links in the tooltip — each product opens in a new tab
- 🏷️ Draft/private products are shown with a status badge
- ⚡ Results are cached via WordPress transients (1 hour) and auto-invalidated on product save/delete
- 🧠 MutationObserver + polling ensures dots appear even after the media grid lazy-loads
- Zero dependencies — no external libraries, no build step

---

## Requirements

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+

---

## Installation

### Option A — Manual upload

1. Download [`ppearls-dots.php`](ppearls-dots.php)
2. Upload it to your site's `/wp-content/plugins/ppearls-dots/` directory (create the folder)
3. Go to **Plugins → Installed Plugins** and activate.

### Option B — Upload via WordPress admin

1. Download the repo as a ZIP (`Code → Download ZIP`)
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP and activate

---

## How It Works

On the **Media Library** screen (`/wp-admin/upload.php`), the plugin:

1. Queries `wp_postmeta` for all products that have a `_thumbnail_id` or `_product_image_gallery` referencing each attachment
2. Encodes the resulting map as JSON and injects it into the admin footer
3. A small vanilla-JS script stamps a green dot onto every `.attachment[data-id]` element whose ID appears in the map
4. A single shared tooltip `<div>` (appended to `<body>`) is reused for all dots, with a 120 ms hide-delay so the cursor can travel from dot to tooltip without it disappearing

### Why a body-level tooltip?

The earlier approach (tooltip as a child of the dot) caused the links to be unclickable — moving the mouse off the dot to click a link would trigger `mouseleave`, collapsing the tooltip before the click registered. Moving the tooltip to `<body>` and bridging the gap with a short timer fixes this.

---

## Debugging

Open the browser console on the Media Library page and run:

```js
ppearls_debug()
```

This prints:
- How many `.attachment[data-id]` elements were found
- How many image IDs are in the product map
- Whether the first attachment's ID is present in the map

---

## Changelog

### 1.1
- **Fix:** Tooltip links were not clickable — tooltip is now a single shared element appended to `<body>` instead of being a child of each dot
- **Fix:** Added 120 ms hide-delay so the cursor can move from dot to tooltip without it closing
- Added `pointer-events: auto` consistently on tooltip

### 1.0
- Initial release

---

## License

[GPL-2.0-or-later](LICENSE)
