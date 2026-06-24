<?php
/**
 * Zuno Docs Engine — Uninstall Handler
 *
 * Cleans up plugin data when the plugin is deleted via WordPress Admin.
 *
 * @package zuno_docs
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Allow sites to preserve data by defining this constant in wp-config.php.
if ( defined( 'ZUNO_DOCS_PRESERVE_DATA' ) && ZUNO_DOCS_PRESERVE_DATA ) {
    return;
}

/* -----------------------------------------------------------------------
 * Delete options
 * --------------------------------------------------------------------- */
delete_option( 'zuno_docs_graph' );
delete_option( 'zuno_docs_settings' );
delete_option( 'zuno_docs_version' );

/* -----------------------------------------------------------------------
 * Delete all zuno_doc posts (any status)
 * --------------------------------------------------------------------- */
$posts = get_posts( array(
    'post_type'      => 'zuno_doc',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
) );

if ( ! empty( $posts ) ) {
    foreach ( $posts as $post_id ) {
        wp_delete_post( $post_id, true );
    }
}

/* -----------------------------------------------------------------------
 * Delete taxonomy terms
 * --------------------------------------------------------------------- */
$taxonomies = array( 'zuno_doc_category', 'zuno_product' );

foreach ( $taxonomies as $taxonomy ) {
    $terms = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'fields'     => 'ids',
    ) );

    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term_id ) {
            wp_delete_term( $term_id, $taxonomy );
        }
    }
}
