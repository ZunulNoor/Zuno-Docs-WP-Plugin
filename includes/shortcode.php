<?php
/**
 * Zuno Docs Engine – Shortcode Handler
 *
 * Uses precomputed doc graph for zero-latency rendering.
 * The `product` attribute resolves against zuno_doc_category slugs.
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

    /* ---------- Validate category if product attribute is provided ---------- */
    if ( $product && ! $doc_id ) {
        $cat_exists = term_exists( $product, 'zuno_doc_category' );
        if ( ! $cat_exists ) {
            if ( current_user_can( 'zuno_docs_edit' ) ) {
                return zuno_docs_error(
                    sprintf(
                        /* translators: %s: category slug */
                        __( 'The category "%s" does not exist. Please create it under Zuno Docs → Categories or use a valid category slug.', 'zuno-docs-engine' ),
                        esc_html( $product )
                    )
                );
            }
            return '<p>' . esc_html__( 'Documentation is not available.', 'zuno-docs-engine' ) . '</p>';
        }
    }

    /* ---------- Resolve the doc to display ---------- */
    $page_content = '';
    $page_title   = __( 'Documentation', 'zuno-docs-engine' );

    if ( $doc_id ) {
        $doc_obj = get_post( $doc_id );
        if ( $doc_obj && 'publish' === $doc_obj->post_status && 'zuno_doc' === $doc_obj->post_type ) {
            $page_content = apply_filters( 'the_content', $doc_obj->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            $page_title   = get_the_title( $doc_obj );
            if ( ! $product ) {
                $terms = wp_get_post_terms( $doc_id, 'zuno_doc_category', array( 'fields' => 'slugs' ) );
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
                $page_content = apply_filters( 'the_content', $doc_obj->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
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
    if ( 'google' === $settings['zuno_docs_font_family'] && ! empty( $settings['zuno_docs_google_font'] ) ) {
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

    $display_settings = Zuno_Docs_Settings::get_display_settings();

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
            'searchPlaceholder' => __( 'Search documentation…', 'zuno-docs-engine' ),
            'noResults'         => __( 'No results found.', 'zuno-docs-engine' ),
            'tocLabel'          => __( 'On this page', 'zuno-docs-engine' ),
            'prev'              => __( 'Previous', 'zuno-docs-engine' ),
            'next'              => __( 'Next', 'zuno-docs-engine' ),
            'related'           => __( 'Related articles', 'zuno-docs-engine' ),
            'tocNoResults'      => __( 'No matching sections found for "{query}"', 'zuno-docs-engine' ),
        ),
    );

    wp_localize_script( 'zuno-docs', 'ZUNODocsConfig', $config );

    /* ---------- Dynamic CSS ---------- */
    $css_vars = zuno_docs_get_dynamic_css( $settings );
    wp_add_inline_style( 'zuno-docs', $css_vars );

    /* ---------- Render ---------- */
    ob_start();
    include ZUNO_DOCS_TEMPLATES . 'layout.php';

    return ob_get_clean();
}

/**
 * Build a lightweight search index for a specific category, optimized for JS.
 */
function zuno_docs_get_product_search_data( $category_slug ) {
    $graph = zuno_docs_get_graph();
    if ( ! isset( $graph['doc_tree'][ $category_slug ] ) ) {
        return array(
            'docs'   => array(),
            'tokens' => array(),
        );
    }

    $tree = $graph['doc_tree'][ $category_slug ];
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
