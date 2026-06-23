<?php
/**
 * Zuno Docs Engine – Shortcode Handler
 *
 * @package zuno_docs
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'zuno_docs', 'zuno_docs_render_shortcode' );

function zuno_docs_render_shortcode( $atts ) {

    $atts = shortcode_atts(
        array(
            'product'   => 'shipox',
            'doc_id'    => '',
            'toc_depth' => '6',
        ),
        (array) $atts,
        'zuno_docs'
    );

    $allowed_products = array( 'shipox', 'express', 'storfox' );
    $product          = sanitize_key( $atts['product'] );

    if ( ! in_array( $product, $allowed_products, true ) ) {
        return zuno_docs_error( "Unknown product {$product}. Allowed: " . implode( ', ', $allowed_products ) );
    }

    $toc_depth = max( 2, min( 6, (int) $atts['toc_depth'] ) );

    /* ---------- Resolve the doc to display ------------------------- */
    $doc_id       = (int) $atts['doc_id'];
    $page_content = '';
    $page_title   = zuno_docs_product_label( $product );

    if ( $doc_id ) {
        $doc_obj = get_post( $doc_id );
        if ( $doc_obj && 'publish' === $doc_obj->post_status && 'zuno_doc' === $doc_obj->post_type ) {
            $page_content = apply_filters( 'the_content', $doc_obj->post_content );
            $page_title   = get_the_title( $doc_obj );
        }
    } else {
        $term = get_term_by( 'slug', $product, 'zuno_product' );
        if ( $term ) {

            $cache_key = 'zuno_docs_query_' . $product;
            $doc_id    = get_transient( $cache_key );

            if ( false === $doc_id ) {
                $query = new WP_Query( array(
                    'post_type'      => 'zuno_doc',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'orderby'        => 'menu_order title',
                    'order'          => 'ASC',
                    'fields'         => 'ids',
                    'tax_query'      => array(
                        array(
                            'taxonomy' => 'zuno_product',
                            'field'    => 'slug',
                            'terms'    => $product,
                        ),
                    ),
                ) );
                $doc_id = $query->have_posts() ? $query->posts[0] : 0;
                set_transient( $cache_key, $doc_id, HOUR_IN_SECONDS * 6 );
            }

            if ( $doc_id ) {
                $doc_obj      = get_post( $doc_id );
                $page_content = apply_filters( 'the_content', $doc_obj->post_content );
                $page_title   = get_the_title( $doc_obj );
            }
        }
    }

    wp_enqueue_style( 'zuno-docs' );
    wp_enqueue_script( 'zuno-docs' );

    /* ----- Pass config and settings to JS ----- */
    wp_localize_script(
        'zuno-docs',
        'ZUNODocsConfig',
        array(
            'product'   => $product,
            'tocDepth'  => $toc_depth,
            'i18n'      => array(
                'searchPlaceholder' => __( 'Search docs…', 'zuno-docs' ),
                'noResults'         => __( 'No results found.', 'zuno-docs' ),
                'tocLabel'          => __( 'On this page', 'zuno-docs' ),
            ),
        )
    );

    /* ----- Pass dynamic settings to JS ----- */
    $settings = zuno_docs_get_settings();
    wp_localize_script(
        'zuno-docs',
        'ZUNO_SETTINGS',
        $settings
    );

    /* ----- Inject dynamic CSS variables inline ----- */
    $css_vars = zuno_docs_get_dynamic_css( $settings );

    ob_start();
    echo '<style>' . $css_vars . '</style>';
    include ZUNO_DOCS_TEMPLATES . 'layout.php';
    return ob_get_clean();
}
