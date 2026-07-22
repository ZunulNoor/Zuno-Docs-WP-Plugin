=== Zuno Docs Engine ===
Contributors: zunulnoor
Donate link: https://zunulnoor.vercel.app
Tags: documentation, docs, knowledge base, documentation management, docs engine
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Full documentation CMS with custom post types, categories, hierarchical TOC, client-side search, and multi-product support.

== Description ==

Zuno Docs Engine transforms WordPress into a full-featured documentation platform. Perfect for product documentation, knowledge bases, help centers, and developer docs.

= Key Features =

* **Custom Post Type** — Dedicated `zuno_doc` post type keeps docs separate from blog posts.
* **Category Taxonomies** — Organize docs with hierarchical categories, default category protection, per-doc ordering.
* **Navigation Rail** — Fixed-position floating rail with group indicators, hover preview panel, dynamic content-boundary visibility.
* **Chapter Engine** — H1-based chapter management with smooth transitions, URL hash sync, independent scroll tracking.
* **Hierarchical Table of Contents** — Auto-generated from heading tags (H1-H6), collapsible sections, scroll-spy with IntersectionObserver.
* **Client-Side Search** — Instant on-page search with keyboard-navigable suggestions, in-content highlighting, TOC filtering, REST API fallback.
* **Precomputed Doc Graph** — Zero-latency page loads via cached data structures rebuilt on save. Single-query builder.
* **Reading Progress Bar** — Optional per-chapter progress indicator at the top of each doc.
* **Breadcrumbs** — Automatic breadcrumb navigation shows category > doc path.
* **Prev / Next Navigation** — Sequential doc navigation through the category tree.
* **Related Articles** — Auto-suggested related docs from the same category.
* **Fully Customizable** — Theme color, typography, sidebar width, TOC depth, TOC colors, all adjustable from 8-tab Settings panel.
* **Shortcode** — Simple `[zuno_docs product="shipox"]` embeds documentation anywhere.
* **Gutenberg Compatible** — Full block editor support for rich doc content.
* **REST API** — Public search endpoint for headless or AJAX integration.
* **Custom Capabilities** — Fine-grained roles: `zuno_docs_editor` role, read/create/edit/delete/manage permissions.
* **Uninstall Cleanup** — Full data removal on deletion, with settings toggle and `ZUNO_DOCS_PRESERVE_DATA` constant support.
* **Deactivation Flow** — Branded modal with Keep Data / Remove All Data options.
* **Modular Admin JS** — Custom modal system (`ZunoDocsPopup`), delete confirmations, keyboard-navigable dialogs.
* **Security Hardened** — Stored XSS prevention, nonce sanitization, POST filtering, output escaping throughout.

= Shortcode Examples =

`[zuno_docs product="shipox"]`
`[zuno_docs product="express" doc_id="123"]`
`[zuno_docs product="storfox" toc_depth="4"]`

= Privacy =

This plugin does not collect or transmit any personal data. All data stays on your server.

== Installation ==

1. Upload the `zuno-docs-engine` folder to `/wp-content/plugins/` or install via WordPress plugin admin.
2. Activate the plugin through the 'Plugins' screen.
3. Go to Zuno Docs → Settings to configure appearance.
4. Use `[zuno_docs product="shipox"]` shortcode on any page/post to display documentation.
5. Create docs under Zuno Docs → Add New, assign product tags.

== Frequently Asked Questions ==

= Can I use multiple products? =

Yes. Create product terms under Zuno Docs → Products, then tag each doc with the appropriate product.

= Does this work with caching plugins? =

Yes. The precomputed doc graph avoids dynamic queries, making it compatible with most caching solutions.

= Can I customize colors? =

Yes. All visual settings are available under Zuno Docs → Settings, including theme color, TOC colors, typography, and highlight colors.

= Is it translation-ready? =

Yes. All frontend strings use WordPress i18n functions and the `zuno-docs-engine` text domain.

== Screenshots ==

1. Frontend documentation layout with sidebar TOC, search, and content
2. Admin dashboard with stats and doc management
3. Settings panel with tabbed interface
4. Meta box for per-doc product, category, and ordering

== Changelog ==

= 2.2.0 =
* New: Navigation Rail — ChatGPT-style floating rail with dot indicators, hover preview panel, smooth slide transitions, dynamic content-boundary detection
* New: Activation redirect — auto-redirect to Zuno Docs Dashboard after plugin activation
* New: Deactivation flow — branded modal with Keep Data / Remove All Data options, AJAX preference storage
* New: Custom capabilities system — zuno_docs_editor role, fine-grained doc/settings/category permissions
* New: Admin modal system — ZunoDocsPopup replaces native confirm() across admin with branded dialogs
* Enhancement: Security hardening — stored XSS fix in search suggestions, nonce sanitization with sanitize_key(), POST filtering via array_intersect_key()
* Enhancement: Performance — N+1 query elimination in graph builder (single WP_Query), dashboard pagination (20/page), wp_count_posts() for stats
* Enhancement: Dead code removal — unused get_default() method, empty doc_index field, orphaned transient cleanup
* Fix: Memory leaks — admin.js keydown listener no longer accumulates, ChapterEngine callbacks reset on init
* Dev: Version bumped to 2.2.0

= 2.1.0 =
* Major: Full CSS namespace isolation — no conflicts with themes or page builders
* Major: Premium mobile TOC with floating sticky card, overlay panel, backdrop blur, body scroll lock
* Major: JavaScript isolation — all DOM operations scoped to plugin wrapper
* New: Google Font integration — inherit theme font or load any Google Font
* Enhancement: Refined mobile typography — optimized heading sizes for small screens
* Enhancement: Active heading auto-scrolls into view in mobile TOC panel
* Tweak: Admin bar offset now uses scoped class instead of global `.admin-bar`

= 2.0.0 =
* Major: SaaS-grade upgrade with hierarchical TOC, collapsible sections, scroll-spy
* Major: Precomputed doc graph for instant page loads
* Major: Client-side search with content highlighting
* Major: Multi-product support
* Major: Reading progress bar, breadcrumbs, prev/next navigation, related articles
* Enhancement: Mobile-responsive sidebar with drawer behavior
* Enhancement: Tabbed settings panel with color pickers
* Enhancement: Admin dashboard with stats and filters
* Enhancement: Gutenberg editor meta box for doc settings
* Security: Nonce protection on all admin actions
* Security: Input sanitization and output escaping throughout

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.0.0 =
This is a major upgrade with significant database changes. The doc graph is rebuilt automatically on activation. Settings from v1.0.0 are preserved.
