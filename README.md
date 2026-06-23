# Zuno Docs Engine

**Version:** 1.0.0  
**Author:** Zun Ul Noor  
**URI:** [https://zunulnoor.vercel.app](https://zunulnoor.vercel.app)  
**License:** GPL-2.0+  
**Requires at least:** WordPress 5.8  
**Tested up to:** WordPress 6.4  
**Requires PHP:** 7.4+

---

A standalone documentation CMS for WordPress that lets you create, manage, and display product documentation — powered by custom post types, taxonomies, client-side search, a sticky table of contents, and a fully themeable frontend. No page builders, no jQuery, no external dependencies.

---

## Features

- **Custom Post Type** (`zuno_doc`) — dedicated docs content type, independent of WordPress Pages
- **Product Taxonomies** (`zuno_product`) — tag docs by product (e.g., Shipox, Storfox)
- **Category Taxonomies** (`zuno_doc_category`) — organize docs into sections (Getting Started, Guides, Troubleshooting)
- **Client-Side Search** — TreeWalker-based text-node–safe highlight engine, debounced at 250ms, no server requests
- **Sticky Table of Contents** — auto-generated from headings, scroll-spy with IntersectionObserver, collapsible nested tree
- **Admin Dashboard** — stats cards, product/category filters, full doc table with Edit/View/Delete
- **Categories CRUD** — add, edit, delete categories with nonce verification
- **Settings Panel** — tabbed UI for typography, layout, TOC colors, highlight colors, and behavior
- **Meta Box** — assign Product, Category, and Order directly in the Gutenberg/post editor
- **Dynamic CSS** — settings-driven CSS variables injected inline, no rebuild required
- **Transient Caching** — 6-hour TTL on shortcode queries, cleared on doc save
- **Mobile Responsive** — collapsible sidebar, full-width content on smaller screens
- **No Dependencies** — zero jQuery, zero external libraries, zero page builders

---

## Installation

1. Download the plugin and upload the `zuno-docs-engine` folder to `/wp-content/plugins/`
2. Activate **Zuno Docs Engine** from the WordPress **Plugins** screen
3. Seed terms (products & categories) are created automatically on activation
4. Go to **Zuno Docs → Add New** to create your first documentation article
5. Insert the shortcode on any page or post

---

## Usage

### Shortcode

```
[zuno_docs product="shipox"]
```

| Attribute  | Description                                        | Default |
| ---------- | -------------------------------------------------- | ------- |
| `product`  | Product slug — filters docs by `zuno_product` term | —       |
| `doc_id`   | Specific doc ID to display                         | —       |
| `category` | Category slug — filters by `zuno_doc_category`     | —       |

If no `product` is given, all docs are shown.

### Admin Menu

| Menu Item      | Description                              |
| -------------- | ---------------------------------------- |
| Zuno Docs      | Dashboard — stats, filters, doc table    |
| Add New        | Quick-create form or link to Gutenberg   |
| Categories     | Manage doc categories (CRUD)             |
| Settings       | Typography, layout, TOC colors, behavior |

### Post Type & Taxonomies

| Name                | Slug                    | REST Base             |
| ------------------- | ----------------------- | --------------------- |
| Docs (CPT)          | `zuno_doc`              | `zuno-docs`           |
| Doc Categories      | `zuno_doc_category`     | `zuno-doc-categories` |
| Products            | `zuno_product`          | `zuno-products`       |

---

## Screenshots

*Coming soon.*

---

## Frequently Asked Questions

### Can I use this alongside other documentation plugins?

Yes. Zuno Docs Engine uses its own custom post type (`zuno_doc`) and does not interfere with WordPress Pages or other post types.

### Is the frontend compatible with any theme?

Yes. The shortcode only modifies the content area — your theme's header, footer, and sidebar remain untouched. CSS is scoped to `.zuno-docs-*` classes.

### Does search require a server request?

No. Search is fully client-side. It uses a TreeWalker to safely highlight matches in text nodes without breaking HTML.

### How do I add a new product?

Products are terms in the `zuno_product` taxonomy. You can add them via **Posts → Products** in the admin or programmatically.

---

## Changelog

### 1.0.0
- Initial release
- Custom post type `zuno_doc` with REST API support
- `zuno_product` and `zuno_doc_category` taxonomies
- Shortcode `[zuno_docs]` with product, category, and doc_id filters
- Client-side search with TreeWalker-based highlight engine
- Auto-generated sticky table of contents with scroll spy
- Admin dashboard with stats, filters, and doc management
- Categories CRUD page (add, edit, delete)
- Settings panel (typography, layout, TOC colors, highlight, behavior)
- Doc Settings meta box (product, category, order)
- Transient caching (6-hour TTL, cleared on save)
- Dynamic CSS via CSS custom properties
- Mobile-responsive layout with collapsible sidebar
- Seed terms: Shipox, Shipox Express, Storfox; Getting Started, Guides, Troubleshooting

---

## Development

The plugin follows WordPress coding standards and is compatible with PHP 7.4+.

### File Structure

```
zuno-docs-engine/
├── assets/
│   ├── admin.css          # Admin dashboard & settings styles
│   ├── docs.css           # Frontend documentation styles
│   └── docs.js            # Frontend JS (TOC, search, scroll spy, mobile sidebar)
├── includes/
│   ├── admin-categories.php   # Categories CRUD admin page
│   ├── admin-dashboard.php    # Dashboard with stats & doc table
│   ├── admin-meta-box.php     # Doc Settings meta box
│   ├── admin-new-doc.php      # Add New Doc page
│   ├── admin-settings.php     # Settings panel & defaults
│   ├── post-type.php          # CPT & taxonomy registration
│   └── shortcode.php          # Shortcode handler, cache, template
├── templates/
│   └── layout.php             # Frontend two-column layout template
├── zuno-docs-engine.php       # Main plugin bootstrap
└── README.md
```

---

## License

This plugin is licensed under the GPL-2.0+ license.
>>>>>>> 808e070 (Initial release v1.0.0 — Zuno Docs Engine)
