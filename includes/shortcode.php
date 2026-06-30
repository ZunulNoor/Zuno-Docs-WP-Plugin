<?php
/**
 * Zuno Docs Engine – Shortcode Handler
 *
 * Uses precomputed doc graph for zero-latency rendering.
 *
 * @package zuno_docs
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'zuno_docs', 'zuno_docs_render_shortcode' );

function zuno_docs_render_shortcode( $atts ) {

    $atts = shortcode_atts(
        array(
            'product'   => '',
            'doc_id'    => '',
            'toc_depth' => '6',
        ),
        (array) $atts,
        'zuno_docs'
    );

    $product   = sanitize_key( $atts['product'] );
    $doc_id    = (int) $atts['doc_id'];
    $toc_depth = max( 2, min( 6, (int) $atts['toc_depth'] ) );

    /* ---------- Load settings early (needed by display toggles + JS config) ---------- */
    $settings = zuno_docs_get_settings();

    /* ---------- Resolve the doc to display ---------- */
    $page_content = '';
    $page_title   = __( 'Documentation', 'zuno-docs' );

    if ( $doc_id ) {
        $doc_obj = get_post( $doc_id );
        if ( $doc_obj && 'publish' === $doc_obj->post_status && 'zuno_doc' === $doc_obj->post_type ) {
            $page_content = apply_filters( 'the_content', $doc_obj->post_content );
            $page_title   = get_the_title( $doc_obj );
            if ( ! $product ) {
                $terms = wp_get_post_terms( $doc_id, 'zuno_product', array( 'fields' => 'slugs' ) );
                $product = $terms[0] ?? '';
            }
        }
    } elseif ( $product ) {
        $tree   = zuno_docs_get_product_graph( $product );
        $list   = ! empty( $tree['flat_list'] ) ? $tree['flat_list'] : array();
        if ( ! empty( $list ) ) {
            $first = reset( $list );
            $doc_obj = get_post( $first['id'] );
            if ( $doc_obj && 'publish' === $doc_obj->post_status ) {
                $page_content = apply_filters( 'the_content', $doc_obj->post_content );
                $page_title   = get_the_title( $doc_obj );
                $doc_id       = $first['id'];
            }
        }
    }

    /* ---------- Breadcrumbs ---------- */
    $breadcrumbs = array();
    $adjacent    = array( 'prev' => null, 'next' => null );
    $related     = array();

    if ( $doc_id ) {
        $graph       = zuno_docs_get_graph();
        $breadcrumbs = zuno_docs_get_breadcrumbs( $doc_id, $graph );
        if ( $product ) {
            $adjacent = zuno_docs_get_adjacent( $doc_id, $product );
            $related  = zuno_docs_get_related( $doc_id, $product );
        }
    }

    /* ---------- Enqueue assets ---------- */
    wp_enqueue_style( 'zuno-docs' );
    wp_enqueue_script( 'zuno-docs' );

    /* ---------- Google Font ---------- */
    if ( 'google' === ( $settings['zuno_docs_font_family'] ?? 'inherit' ) && ! empty( $settings['zuno_docs_google_font'] ) ) {
        $gf = trim( $settings['zuno_docs_google_font'] );
        $gf_slug = sanitize_title( $gf );
        $gf_url = 'https://fonts.googleapis.com/css2?family=' . str_replace( ' ', '+', $gf ) . ':wght@400;500;600;700&display=swap';
        wp_enqueue_style( 'zuno-docs-font-' . $gf_slug, $gf_url, array(), ZUNO_DOCS_VERSION );
    }

    /* ---------- Pass config to JS ---------- */
    $graph = zuno_docs_get_graph();
    $search_data = array();
    if ( $product && isset( $graph['search_index'] ) ) {
        $search_data = zuno_docs_get_product_search_data( $product );
    }

    $display_settings = array(
        'show_breadcrumbs' => 'yes' === ( $settings['zuno_docs_show_breadcrumbs'] ?? 'yes' ),
        'show_previous'    => 'yes' === ( $settings['zuno_docs_show_previous'] ?? 'yes' ),
        'show_next'        => 'yes' === ( $settings['zuno_docs_show_next'] ?? 'yes' ),
        'show_navigation'  => 'yes' === ( $settings['zuno_docs_show_navigation'] ?? 'yes' ),
        'show_related'     => 'yes' === ( $settings['zuno_docs_show_related_articles'] ?? 'yes' ),
    );

    $config = array(
        'product'         => $product,
        'docId'           => $doc_id,
        'tocDepth'        => $toc_depth,
        'restUrl'         => esc_url_raw( rest_url( 'zuno-docs/v1/search' ) ),
        'restNonce'       => wp_create_nonce( 'wp_rest' ),
        'searchIndex'     => $search_data,
        'breadcrumbs'     => $breadcrumbs,
        'adjacent'        => $adjacent,
        'related'         => $related,
        'display'         => $display_settings,
        'settings'        => $settings,
        'i18n'            => array(
            'searchPlaceholder' => __( 'Search documentation…', 'zuno-docs' ),
            'noResults'         => __( 'No results found.', 'zuno-docs' ),
            'tocLabel'          => __( 'On this page', 'zuno-docs' ),
            'prev'              => __( 'Previous', 'zuno-docs' ),
            'next'              => __( 'Next', 'zuno-docs' ),
            'related'           => __( 'Related articles', 'zuno-docs' ),
            'tocNoResults'      => __( 'No matching sections found for "{query}"', 'zuno-docs' ),
        ),
    );

    wp_localize_script( 'zuno-docs', 'ZUNODocsConfig', $config );

    /* ---------- Dynamic CSS ---------- */
    $css_vars = zuno_docs_get_dynamic_css( $settings );

    /* ---------- Render ---------- */
    ob_start();
    echo '<style>' . $css_vars . '</style>';
    include ZUNO_DOCS_TEMPLATES . 'layout.php';
    return ob_get_clean();
}

/**
 * Build a lightweight search index for a specific product, optimized for JS.
 */
function zuno_docs_get_product_search_data( $product_slug ) {
    $graph = zuno_docs_get_graph();
    if ( ! isset( $graph['doc_tree'][ $product_slug ] ) ) {
        return array(
            'docs'   => array(),
            'tokens' => array(),
        );
    }

    $tree = $graph['doc_tree'][ $product_slug ];
    $docs = array();

    foreach ( $tree['flat_list'] as $id => $info ) {
        $docs[ $id ] = array(
            'id'      => $id,
            'title'   => $info['title'],
            'excerpt' => $info['excerpt'],
        );
    }

    return array(
        'docs'   => $docs,
        'tokens' => array(),
    );
}
