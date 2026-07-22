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

// Check the plugin's own preserve_data setting.
$zuno_settings = get_option( 'zuno_docs_settings', array() );
if ( ! empty( $zuno_settings['zuno_docs_preserve_data'] ) && 'yes' === $zuno_settings['zuno_docs_preserve_data'] ) {
    return;
}

/* -----------------------------------------------------------------------
 * Clean up custom role and capabilities
 * --------------------------------------------------------------------- */
$zuno_caps = array(
    'zuno_docs_read',
    'zuno_docs_create',
    'zuno_docs_edit',
    'zuno_docs_publish',
    'zuno_docs_delete',
    'zuno_docs_manage_categories',
    'zuno_docs_manage_settings',
    'zuno_docs_import',
    'zuno_docs_export',
    'zuno_docs_manage_plugin',
);

// Remove Zuno Docs capabilities from all roles.
global $wp_roles;
if ( ! isset( $wp_roles ) ) {
    $wp_roles = new WP_Roles();
}
foreach ( $wp_roles->roles as $role_name => $role_info ) {
    $role = get_role( $role_name );
    if ( $role ) {
        foreach ( $zuno_caps as $cap ) {
            $role->remove_cap( $cap );
        }
    }
}

// Remove the custom Zuno Docs Editor role.
remove_role( 'zuno_docs_editor' );

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
