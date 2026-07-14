<?php
/**
 * Plugin Name:  Zuno Docs Engine
 * Plugin URI:   https://github.com/ZunulNoor/Zuno-Docs-WP-Plugin
 * Description:  Full documentation CMS with custom post types, categories, TOC,
 *               client-side search, and multi-product support.
 * Version:      2.1.0
 * Author:       Zun Ul Noor
 * Author URI:   https://zunulnoor.vercel.app
 * Text Domain:  zuno-docs
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

/* -----------------------------------------------------------------------
 * Constants
 * --------------------------------------------------------------------- */
define( 'ZUNO_DOCS_VERSION',     '2.1.0' );
define( 'ZUNO_DOCS_DIR',         plugin_dir_path( __FILE__ ) );
define( 'ZUNO_DOCS_URL',         plugin_dir_url( __FILE__ ) );
define( 'ZUNO_DOCS_ASSETS',      ZUNO_DOCS_URL . 'assets/' );
define( 'ZUNO_DOCS_TEMPLATES',   ZUNO_DOCS_DIR . 'templates/' );
define( 'ZUNO_DOCS_INCLUDES',    ZUNO_DOCS_DIR . 'includes/' );

/* -----------------------------------------------------------------------
 * Safely load includes
 * --------------------------------------------------------------------- */
$zuno_docs_includes = array(
    'class-settings.php',
    'class-capabilities.php',
    'post-type.php',
    'doc-graph.php',
    'shortcode.php',
    'admin-dashboard.php',
    'admin-new-doc.php',
    'admin-categories.php',
    'admin-settings.php',
    'admin-meta-box.php',
);

foreach ( $zuno_docs_includes as $file ) {
    $path = ZUNO_DOCS_INCLUDES . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

/* -----------------------------------------------------------------------
 * Register Custom Post Type + Taxonomy
 * --------------------------------------------------------------------- */
add_action( 'init', 'zuno_docs_register_post_type' );
add_action( 'init', 'zuno_docs_register_taxonomy' );
add_action( 'init', 'zuno_docs_register_product_taxonomy' );

/* -----------------------------------------------------------------------
 * Flush rewrite rules on activation so CPT slugs work immediately
 * --------------------------------------------------------------------- */
register_activation_hook( __FILE__, 'zuno_docs_activate' );
function zuno_docs_activate() {
    zuno_docs_register_post_type();
    zuno_docs_register_taxonomy();
    zuno_docs_register_product_taxonomy();
    zuno_docs_seed_default_terms();
    zuno_docs_seed_settings();
    zuno_docs_register_capabilities();
    update_option( 'zuno_docs_version', ZUNO_DOCS_VERSION );
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'zuno_docs_deactivate' );
function zuno_docs_deactivate() {
    flush_rewrite_rules();
}

/* -----------------------------------------------------------------------
 * Seed default settings into the database (safe — preserves existing).
 * --------------------------------------------------------------------- */
function zuno_docs_seed_settings() {
    $existing = get_option( 'zuno_docs_settings', null );
    if ( null === $existing || false === $existing ) {
        update_option( 'zuno_docs_settings', Zuno_Docs_Settings::get_defaults() );
    } else {
        if ( ! is_array( $existing ) ) {
            $existing = array();
        }
        // Only add keys from defaults that do not yet exist in stored settings.
        // This preserves user-configured values while safely introducing new keys.
        $defaults = Zuno_Docs_Settings::get_defaults();
        foreach ( $defaults as $key => $value ) {
            if ( ! array_key_exists( $key, $existing ) ) {
                $existing[ $key ] = $value;
            }
        }
        update_option( 'zuno_docs_settings', $existing );
    }
    Zuno_Docs_Settings::get_instance()->reload();
}

/* -----------------------------------------------------------------------
 * Cache safety: clear all external page caches.
 * Loads WP-Optimize functions manually in admin (where advanced-cache.php
 * is skipped) so cache purging works from any context.
 * --------------------------------------------------------------------- */
function zuno_docs_purge_page_cache() {
    // Ensure WP-Optimize cache functions are available (loaded via
    // advanced-cache.php on front-end, but not in the admin area).
    if ( ! function_exists( 'wpo_cache_flush' ) ) {
        $wpo_cache_funcs = WP_PLUGIN_DIR . '/wp-optimize/cache/file-based-page-cache-functions.php';
        if ( file_exists( $wpo_cache_funcs ) ) {
            require_once $wpo_cache_funcs;
        }
    }

    // Purge WP-Optimize page cache.
    if ( function_exists( 'wpo_cache_flush' ) ) {
        wpo_cache_flush();
    }

    // Purge legacy WP Super Cache if active.
    if ( function_exists( 'wp_cache_clear_cache' ) ) {
        wp_cache_clear_cache();
    }
}

add_action( 'zuno_docs_settings_saved', 'zuno_docs_clear_settings_cache' );
function zuno_docs_clear_settings_cache() {
    wp_cache_delete( Zuno_Docs_Settings::OPTION_NAME, 'options' );
    zuno_docs_purge_page_cache();
}

/* -----------------------------------------------------------------------
 * Version upgrade routine — seeds categories on update, runs migrations
 * --------------------------------------------------------------------- */
add_action( 'admin_init', 'zuno_docs_version_upgrade' );
function zuno_docs_version_upgrade() {
    $stored = get_option( 'zuno_docs_version', '' );
    if ( $stored === ZUNO_DOCS_VERSION ) {
        return;
    }

    // Register post types and taxonomies if they don't exist yet.
    if ( ! post_type_exists( 'zuno_doc' ) ) {
        zuno_docs_register_post_type();
    }
    if ( ! taxonomy_exists( 'zuno_doc_category' ) ) {
        zuno_docs_register_taxonomy();
    }
    if ( ! taxonomy_exists( 'zuno_product' ) ) {
        zuno_docs_register_product_taxonomy();
    }

    // Seed default terms on fresh install.
    zuno_docs_seed_default_terms();

    // Ensure all settings keys exist (safe migration for any version).
    zuno_docs_seed_settings();

    // Register capabilities for any new or updated roles.
    zuno_docs_register_capabilities();

    // Run version-specific migrations.
    $version_map = array(
        '1.0.0' => 'zuno_docs_upgrade_100_to_200',
    );

    foreach ( $version_map as $version => $callback ) {
        if ( version_compare( $stored, $version, '<' ) && function_exists( $callback ) ) {
            call_user_func( $callback );
        }
    }

    update_option( 'zuno_docs_version', ZUNO_DOCS_VERSION );
    flush_rewrite_rules();
}

/**
 * Migration: 1.0.0 → 2.0.0
 * Ensures settings defaults are present for all new 2.0.0 keys.
 */
function zuno_docs_upgrade_100_to_200() {
    $settings = get_option( 'zuno_docs_settings', array() );
    if ( ! is_array( $settings ) ) {
        $settings = array();
    }
    $defaults = Zuno_Docs_Settings::get_defaults();
    $settings = array_merge( $defaults, $settings );
    update_option( 'zuno_docs_settings', $settings );
}

/* -----------------------------------------------------------------------
 * Admin menu — appears in WordPress sidebar immediately
 * --------------------------------------------------------------------- */
add_action( 'admin_menu', 'zuno_docs_register_admin_menu' );
function zuno_docs_register_admin_menu() {

    add_menu_page(
        'Zuno Docs',
        'Zuno Docs',
        'zuno_docs_read',
        'zuno-docs',
        'zuno_docs_admin_dashboard',
        'dashicons-book',
        25
    );

    add_submenu_page(
        'zuno-docs',
        'All Docs',
        'All Docs',
        'zuno_docs_read',
        'zuno-docs',
        'zuno_docs_admin_dashboard'
    );

    add_submenu_page(
        'zuno-docs',
        'Add New Doc',
        'Add New',
        'zuno_docs_create',
        'zuno-docs-new',
        'zuno_docs_admin_new_doc_page'
    );

    add_submenu_page(
        'zuno-docs',
        'Categories',
        'Categories',
        'zuno_docs_read',
        'zuno-docs-categories',
        'zuno_docs_admin_categories_page'
    );

    add_submenu_page(
        'zuno-docs',
        'Settings',
        'Settings',
        'zuno_docs_manage_settings',
        'zuno-docs-settings',
        'zuno_docs_admin_settings_page'
    );
}

/* -----------------------------------------------------------------------
 * Register front-end assets (deferred — only enqueued when shortcode fires)
 * --------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'zuno_docs_register_assets' );
function zuno_docs_register_assets() {
    wp_register_style(
        'zuno-docs',
        ZUNO_DOCS_ASSETS . 'docs.css',
        array(),
        ZUNO_DOCS_VERSION
    );

    wp_register_script(
        'zuno-docs',
        ZUNO_DOCS_ASSETS . 'docs.js',
        array(),
        ZUNO_DOCS_VERSION,
        true
    );
}

/* -----------------------------------------------------------------------
 * Enqueue admin styles
 * --------------------------------------------------------------------- */
add_action( 'admin_enqueue_scripts', 'zuno_docs_admin_enqueue' );
function zuno_docs_admin_enqueue( $hook ) {
    if ( strpos( $hook, 'zuno-docs' ) === false ) {
        return;
    }

    wp_enqueue_style(
        'zuno-docs-admin',
        ZUNO_DOCS_ASSETS . 'admin.css',
        array(),
        ZUNO_DOCS_VERSION
    );
}

/* -----------------------------------------------------------------------
 * Helper: get product label
 * --------------------------------------------------------------------- */
function zuno_docs_product_label( $product ) {
    $labels = array(
        'shipox'  => 'Shipox',
        'express' => 'Shipox Express',
        'storfox' => 'Storfox',
    );
    return isset( $labels[ $product ] ) ? $labels[ $product ] : 'Shipox';
}

/* -----------------------------------------------------------------------
 * Generate dynamic CSS variables from settings
 * --------------------------------------------------------------------- */
function zuno_docs_get_dynamic_css( $settings = null ) {
    if ( null === $settings ) {
        $settings = zuno_docs_get_settings();
    }

    $sidebar_w = (int) $settings['sidebar_width'];
    $content_w = 100 - $sidebar_w;
    $direction = 'left' === $settings['toc_position'] ? 'row' : 'row-reverse';
    $active_bg = 'yes' === $settings['enable_active_bg'] ? $settings['toc_active_bg'] : 'transparent';
    $heading_bg_rule = 'yes' === $settings['enable_heading_bg']
        ? '.zuno-docs .zuno-docs-toc li[data-depth="1"] > .zuno-docs-toc-link { background: ' . esc_attr( $settings['toc_heading_bg'] ) . '; border-radius: 6px; }'
        : '';

    $theme_color = $settings['zuno_docs_theme_color'];
    $theme_rgb = zuno_docs_hex_to_rgb( $theme_color );
    $theme_hover = zuno_docs_darken_color( $theme_color, 0.85 );

    $font_family = 'inherit';
    if ( 'google' === $settings['zuno_docs_font_family'] && ! empty( $settings['zuno_docs_google_font'] ) ) {
        $font_family = "'" . esc_attr( $settings['zuno_docs_google_font'] ) . "', sans-serif";
    }

    $css = '
.zuno-docs {
    --zuno-primary: ' . esc_attr( $theme_color ) . ';
    --zuno-primary-rgb: ' . esc_attr( $theme_rgb ) . ';
    --zuno-theme-color: ' . esc_attr( $theme_color ) . ';
    --zuno-theme-color-rgb: ' . esc_attr( $theme_rgb ) . ';
    --zuno-text: #000000;
    --zuno-text-link: ' . esc_attr( $theme_color ) . ';
    --zuno-docs-primary-hover: ' . esc_attr( $theme_hover ) . ';
    --zuno-docs-h1-size: ' . (int) $settings['h1_size'] . 'px;
    --zuno-docs-h2-size: ' . (int) $settings['h2_size'] . 'px;
    --zuno-docs-h3-size: ' . (int) $settings['h3_size'] . 'px;
    --zuno-docs-h4-size: ' . (int) $settings['h4_size'] . 'px;
    --zuno-docs-h5-size: ' . (int) $settings['h5_size'] . 'px;
    --zuno-docs-h6-size: ' . (int) $settings['h6_size'] . 'px;
    --zuno-docs-p-size: ' . (int) $settings['p_size'] . 'px;
    --zuno-docs-line-height: ' . esc_attr( $settings['line_height'] ) . ';
    --zuno-docs-toc-bg: ' . esc_attr( $settings['toc_bg'] ) . ';
    --zuno-docs-toc-text: ' . esc_attr( $settings['toc_text'] ) . ';
    --zuno-docs-toc-hover: ' . esc_attr( $settings['toc_hover'] ) . ';
    --zuno-docs-toc-active-text: ' . esc_attr( $settings['toc_active_text'] ) . ';
    --zuno-docs-toc-active-bg: ' . esc_attr( $active_bg ) . ';
    --zuno-docs-toc-active-bar: ' . esc_attr( $settings['toc_active_bar'] ) . ';
    --zuno-docs-highlight-bg: ' . esc_attr( $settings['highlight_bg'] ) . ';
    --zuno-docs-highlight-text: ' . esc_attr( $settings['highlight_text'] ) . ';
    --zuno-docs-sidebar-w: ' . $sidebar_w . '%;
    --zuno-font: ' . $font_family . ';
    flex-direction: ' . esc_attr( $direction ) . ';
}
.zuno-docs-content-wrap {
    width: ' . $content_w . '%;
}
' . $heading_bg_rule . '
.zuno-docs.zuno-docs-has-admin-bar .zuno-docs-sidebar {
    top: calc(var(--zuno-offset, 0px) + 24px);
    height: calc(100vh - var(--zuno-offset, 0px));
}';

    return $css;
}

/* -----------------------------------------------------------------------
 * Helper: convert hex color to RGB string
 * --------------------------------------------------------------------- */
function zuno_docs_hex_to_rgb( $hex ) {
    $hex = ltrim( $hex, '#' );
    if ( strlen( $hex ) === 3 ) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if ( strlen( $hex ) !== 6 ) {
        return '37, 99, 235';
    }
    $r = hexdec( substr( $hex, 0, 2 ) );
    $g = hexdec( substr( $hex, 2, 2 ) );
    $b = hexdec( substr( $hex, 4, 2 ) );
    return "{$r}, {$g}, {$b}";
}

/* -----------------------------------------------------------------------
 * Helper: darken a hex color by a factor (0.0 = black, 1.0 = unchanged)
 * --------------------------------------------------------------------- */
function zuno_docs_darken_color( $hex, $factor = 0.8 ) {
    $hex = ltrim( $hex, '#' );
    if ( strlen( $hex ) === 3 ) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if ( strlen( $hex ) !== 6 ) {
        return '#1d4ed8';
    }
    $r = round( hexdec( substr( $hex, 0, 2 ) ) * $factor );
    $g = round( hexdec( substr( $hex, 2, 2 ) ) * $factor );
    $b = round( hexdec( substr( $hex, 4, 2 ) ) * $factor );
    return '#' . sprintf( '%02x%02x%02x', $r, $g, $b );
}

/* -----------------------------------------------------------------------
 * REST API endpoint for instant search
 * --------------------------------------------------------------------- */
add_action( 'rest_api_init', 'zuno_docs_register_search_route' );
function zuno_docs_register_search_route() {
    register_rest_route( 'zuno-docs/v1', '/search', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'zuno_docs_rest_search',
        'permission_callback' => '__return_true',
        'args'                => array(
            'q' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'product' => array(
                'sanitize_callback' => 'sanitize_key',
            ),
        ),
    ) );
}

function zuno_docs_rest_search( $request ) {
    $query   = $request->get_param( 'q' );
    $product = $request->get_param( 'product' );

    if ( mb_strlen( trim( $query ) ) < 2 ) {
        return new WP_REST_Response( array( 'results' => array() ), 200 );
    }

    $results = zuno_docs_search( $query, $product );

    return new WP_REST_Response( array(
        'query'   => $query,
        'results' => array_values( $results ),
        'total'   => count( $results ),
    ), 200 );
}

/* -----------------------------------------------------------------------
 * Helper: show error notice to editors/admins
 * --------------------------------------------------------------------- */
function zuno_docs_error( $message ) {
    if ( ! current_user_can( 'zuno_docs_edit' ) ) {
        return '';
    }
    return sprintf(
        '<div class="zuno-docs-error" style="border:1px solid #c00;padding:1rem;color:#c00;font-family:monospace;">'
        . '<strong>[Zuno Docs Engine]</strong> %s</div>',
        esc_html( $message )
    );
}

/* -----------------------------------------------------------------------
 * Centralized debug logger — production-safe, never outputs to browser.
 *
 * Usage:
 *   zuno_docs_debug( 'Some message' );
 *   zuno_docs_debug( $some_array );
 *   zuno_docs_debug( $some_object, 'Optional label' );
 *
 * Respects WP_DEBUG and ZUNO_DOCS_DEBUG.
 * Writes only to wp-content/debug.log (via error_log).
 * Safe for AJAX, REST, JSON, redirects, and CLI contexts.
 * --------------------------------------------------------------------- */
function zuno_docs_debug( $data, $label = '' ) {
    if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
        return;
    }
    if ( defined( 'ZUNO_DOCS_DEBUG' ) && ! ZUNO_DOCS_DEBUG ) {
        return;
    }

    $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );
    $caller = isset( $trace[0] ) ? $trace[0] : array();
    $file   = isset( $caller['file'] ) ? $caller['file'] : '';
    $line   = isset( $caller['line'] ) ? $caller['line'] : '';

    $message = '[Zuno Docs Debug]';
    if ( $file ) {
        $message .= ' ' . basename( $file );
    }
    if ( $line ) {
        $message .= ':' . $line;
    }
    if ( $label ) {
        $message .= ' (' . $label . ')';
    }
    $message .= "\n";

    if ( is_string( $data ) ) {
        $message .= $data;
    } else {
        $message .= print_r( $data, true );
    }

    error_log( $message );
}
