# Zuno Docs Engine

**Version:** 2.1.0  
**Author:** Zun Ul Noor  
**URI:** [https://zunulnoor.vercel.app](https://zunulnoor.vercel.app)  
**License:** GPL-2.0+  
**Requires at least:** WordPress 5.8  
**Tested up to:** WordPress 6.7  
**Requires PHP:** 7.4+

---

A standalone documentation CMS for WordPress that lets you create, manage, and display product documentation — powered by custom post types, taxonomies, client-side search, a sticky table of contents, and a fully themeable frontend. No page builders, no jQuery, no external dependencies.

---

## Features

- **Custom Post Type** (`zuno_doc`) — dedicated docs content type, independent of WordPress Pages
- **Product Taxonomies** (`zuno_product`) — tag docs by product (e.g., Shipox, Storfox)
- **Category Taxonomies** (`zuno_doc_category`) — organize docs into sections (Getting Started, Guides, Troubleshooting)
- **Precomputed Doc Graph** — hierarchical tree + inverted search index cached in `wp_options`, zero-latency frontend
- **Client-Side Search** — instant suggestions, in-content highlighting with TreeWalker, TOC filtering, AJAX fallback
- **Hierarchical Table of Contents** — auto-generated from heading tags, collapsible sections, scroll-spy with IntersectionObserver
- **Admin Dashboard** — stats cards, product/category filters, full doc table with Edit/View/Delete
- **Categories CRUD** — add, edit, delete categories with nonce verification
- **Tabbed Settings Panel** — appearance, typography, layout, TOC colors, highlight, behavior, display toggles
- **Meta Box** — assign Product, Category, and Order directly in the Gutenberg/post editor
- **Dynamic CSS** — settings-driven CSS variables injected inline, no rebuild required
- **Reading Progress Bar** — optional progress indicator at the top of each doc
- **Breadcrumbs** — automatic product > category > doc navigation
- **Prev / Next Navigation** — sequential doc navigation through the product tree
- **Related Articles** — auto-suggested same-category docs
- **REST API** — `GET /wp-json/zuno-docs/v1/search?q=term&product=slug`
- **Centralized Settings** — singleton service class with lazy loading, backward compatible
- **Version Upgrade Support** — automated migration callbacks (1.0.0 → 2.0.0+)
- **Uninstall Cleanup** — full data removal on plugin deletion, with `ZUNO_DOCS_PRESERVE_DATA` constant
- **Full CSS Namespace Isolation** — all styles scoped under `.zuno-docs`, zero conflicts with themes or page builders
- **Premium Mobile TOC** — floating sticky card with overlay panel, backdrop blur, body scroll lock, smooth animations
- **Google Font Support** — choose between theme-inherited font or any Google Font via settings
- **Mobile Responsive** — collapsible sidebar drawer, full-width content on smaller screens
- **Accessibility** — ARIA labels, keyboard-navigable search, focus management, screen reader support
- **No Dependencies** — zero jQuery, zero external libraries, zero page builders

---

## Installation

1. Upload the `zuno-docs-engine` folder to `/wp-content/plugins/` or install via WordPress plugin admin
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
| `toc_depth` | Maximum heading depth for TOC (2–6)               | `6`     |

If no `product` is given and no `doc_id` is specified, a fallback message is displayed.

### Admin Menu

| Menu Item      | Description                              |
| -------------- | ---------------------------------------- |
| Zuno Docs      | Dashboard — stats, filters, doc table    |
| Add New        | Quick-create form or link to Gutenberg   |
| Categories     | Manage doc categories (CRUD)             |
| Settings       | Tabbed settings panel                    |

### Post Type & Taxonomies

| Name                | Slug                    | REST Base             |
| ------------------- | ----------------------- | --------------------- |
| Docs (CPT)          | `zuno_doc`              | `zuno-docs`           |
| Doc Categories      | `zuno_doc_category`     | `zuno-doc-categories` |
| Products            | `zuno_product`          | `zuno-products`       |

---

## Frequently Asked Questions

### Can I use this alongside other documentation plugins?

Yes. Zuno Docs Engine uses its own custom post type (`zuno_doc`) and does not interfere with WordPress Pages or other post types.

### Is the frontend compatible with any theme?

Yes. The shortcode only modifies the content area — your theme's header, footer, and sidebar remain untouched. CSS is scoped to `.zuno-docs-*` classes.

### Does search require a server request?

No. Search is fully client-side for small-to-medium doc sets. For large indexes (1000+ docs), the plugin automatically falls back to the REST API search endpoint.

### How do I add a new product?

Products are terms in the `zuno_product` taxonomy. You can add them via Zuno Docs → Products or programmatically.

### Will my data be preserved if I delete the plugin?

By default, all plugin data is removed on deletion. To preserve data, define `define('ZUNO_DOCS_PRESERVE_DATA', true);` in your `wp-config.php`.

---

## Changelog

### 2.1.0
- **Full CSS Namespace Isolation** — every selector prefixed under `.zuno-docs`, no leakage to/from themes or plugins
- **JavaScript Isolation** — all DOM queries scoped to plugin wrapper, delegated events, no globals
- **Premium Mobile TOC Redesign** — sticky floating trigger card, overlay panel with backdrop blur, body scroll lock, independent scrolling, smooth open/close animations
- **Premium Mobile TOC Search** — search field fixed at top of overlay panel, only TOC list scrolls
- **Active Heading Tracking** — active TOC item auto-scrolls into view in the mobile panel
- **Admin Bar Namespace** — `.admin-bar` dependency replaced with scoped `.zuno-docs-has-admin-bar` class
- **Google Font Integration** — new Typography setting to inherit theme font or load a Google Font with automatic enqueueing
- **Refined Mobile Typography** — adjusted mobile heading sizes (H1: 28px, H2: 26px, H3: 24px, H4: 22px, H5: 20px, H6: 18px, P: 14px)

### 2.0.0
- **Precomputed Documentation Graph** — hierarchical doc tree + inverted search index, zero heavy queries on frontend
- **Ranked Inverted Index Search** — fuzzy matching, partial input (≥2 chars), weighted results (title 5×, heading 3×, content 1×)
- **Instant Search Suggestions** — dropdown with keyboard navigation, AJAX fallback for large indexes
- **Hierarchical TOC** — collapsible nested sections, scroll-spy with IntersectionObserver
- **REST API Search Endpoint** — `GET /wp-json/zuno-docs/v1/search`
- **Breadcrumb, Prev/Next, Related Articles** — auto-generated navigation
- **Reading Progress Bar** — optional top-of-page progress indicator
- **Centralized Settings Service** — `Zuno_Docs_Settings` singleton, lazy-loaded
- **Version Upgrade Support** — automated migration callbacks
- **Uninstall Cleanup** — full data removal with `ZUNO_DOCS_PRESERVE_DATA` constant
- **Security Hardening** — PHP 8.0+ multibyte safety, escaped dynamic CSS, single `wp_localize_script`
- **Accessibility** — `aria-expanded` on toggles, keyboard-only focus indicators, hash-activated scroll-spy
- **WordPress.org Submission** — `readme.txt`, coding standards compliance
- **Performance** — precomputed graph eliminates dynamic queries

### 1.0.0
- Initial release
- Custom post type `zuno_doc` with REST API support
- `zuno_product` and `zuno_doc_category` taxonomies
- Shortcode `[zuno_docs]` with product and doc_id filters
- Client-side search with TreeWalker-based highlight engine
- Auto-generated table of contents with scroll spy
- Admin dashboard with stats, filters, and doc management
- Categories CRUD page
- Settings panel
- Doc Settings meta box
- Transient caching
- Dynamic CSS via CSS custom properties
- Mobile-responsive layout
- Seed terms: Shipox, Shipox Express, Storfox

---

## Development

The plugin follows WordPress coding standards and is compatible with PHP 7.4+.

### File Structure

```
zuno-docs-engine/
├── assets/
│   ├── admin.css              # Admin dashboard & settings styles
│   ├── docs.css               # Frontend documentation styles
│   └── docs.js                # Frontend JS (TOC, search, scroll spy, mobile)
├── includes/
│   ├── class-settings.php     # Centralized settings service (Zuno_Docs_Settings)
│   ├── post-type.php          # CPT & taxonomy registration
│   ├── doc-graph.php          # Precomputed doc graph builder & search index
│   ├── shortcode.php          # Shortcode handler, JS config, rendering
│   ├── admin-dashboard.php    # Dashboard with stats & doc table
│   ├── admin-new-doc.php      # Add New Doc page
│   ├── admin-categories.php   # Categories CRUD admin page
│   ├── admin-settings.php     # Settings panel
│   └── admin-meta-box.php     # Doc Settings meta box
├── templates/
│   └── layout.php             # Frontend two-column layout template
├── uninstall.php              # Full data cleanup on plugin deletion
├── readme.txt                 # WordPress.org plugin readme
├── README.md
└── zuno-docs-engine.php       # Main plugin bootstrap
```

---

## License

This plugin is licensed under the GPL-2.0+ license.
