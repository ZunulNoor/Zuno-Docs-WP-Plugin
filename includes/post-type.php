<?php
/**
 * Zuno Docs Engine — Custom Post Type & Taxonomy Registration
 *
 * @package zuno_docs
 */

defined( 'ABSPATH' ) || exit;

/* -----------------------------------------------------------------------
 * Register Post Type: zuno_doc
 * --------------------------------------------------------------------- */
function zuno_docs_register_post_type() {

    $labels = array(
        'name'                  => _x( 'Docs', 'Post Type General Name', 'zuno-docs-engine' ),
        'singular_name'         => _x( 'Doc', 'Post Type Singular Name', 'zuno-docs-engine' ),
        'menu_name'             => __( 'Docs', 'zuno-docs-engine' ),
        'name_admin_bar'        => __( 'Doc', 'zuno-docs-engine' ),
        'archives'              => __( 'Doc Archives', 'zuno-docs-engine' ),
        'attributes'            => __( 'Doc Attributes', 'zuno-docs-engine' ),
        'all_items'             => __( 'All Docs', 'zuno-docs-engine' ),
        'add_new_item'          => __( 'Add New Doc', 'zuno-docs-engine' ),
        'add_new'               => __( 'Add New', 'zuno-docs-engine' ),
        'new_item'              => __( 'New Doc', 'zuno-docs-engine' ),
        'edit_item'             => __( 'Edit Doc', 'zuno-docs-engine' ),
        'update_item'           => __( 'Update Doc', 'zuno-docs-engine' ),
        'view_item'             => __( 'View Doc', 'zuno-docs-engine' ),
        'view_items'            => __( 'View Docs', 'zuno-docs-engine' ),
        'search_items'          => __( 'Search Doc', 'zuno-docs-engine' ),
        'not_found'             => __( 'Not found', 'zuno-docs-engine' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'zuno-docs-engine' ),
        'insert_into_item'      => __( 'Insert into doc', 'zuno-docs-engine' ),
        'uploaded_to_this_item' => __( 'Uploaded to this doc', 'zuno-docs-engine' ),
        'items_list'            => __( 'Docs list', 'zuno-docs-engine' ),
        'item_published'        => __( 'Doc published.', 'zuno-docs-engine' ),
        'item_updated'          => __( 'Doc updated.', 'zuno-docs-engine' ),
    );

    $args = array(
        'label'               => __( 'Docs', 'zuno-docs-engine' ),
        'description'         => __( 'Documentation articles', 'zuno-docs-engine' ),
        'labels'              => $labels,
        'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'hierarchical'        => false,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => false,
        'show_in_nav_menus'   => false,
        'show_in_admin_bar'   => true,
        'menu_position'       => null,
        'menu_icon'           => 'dashicons-book',
        'can_export'          => true,
        'has_archive'         => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => true,
        'capability_type'     => 'zuno_doc',
        'capabilities'        => array(
            'edit_post'              => 'zuno_docs_edit',
            'read_post'              => 'zuno_docs_read',
            'delete_post'            => 'zuno_docs_delete',
            'edit_posts'             => 'zuno_docs_edit',
            'edit_others_posts'      => 'zuno_docs_edit',
            'publish_posts'          => 'zuno_docs_publish',
            'read_private_posts'     => 'zuno_docs_read',
            'create_posts'           => 'zuno_docs_create',
            'delete_posts'           => 'zuno_docs_delete',
            'delete_private_posts'   => 'zuno_docs_delete',
            'delete_published_posts' => 'zuno_docs_delete',
            'delete_others_posts'    => 'zuno_docs_delete',
            'edit_private_posts'     => 'zuno_docs_edit',
            'edit_published_posts'   => 'zuno_docs_edit',
        ),
        'map_meta_cap'        => false,
        'show_in_rest'        => true,
        'rest_base'           => 'zuno-docs',
    );

    register_post_type( 'zuno_doc', $args );
}

/* -----------------------------------------------------------------------
 * Register Taxonomy: zuno_doc_category
 * --------------------------------------------------------------------- */
function zuno_docs_register_taxonomy() {

    $labels = array(
        'name'                       => _x( 'Doc Categories', 'Taxonomy General Name', 'zuno-docs-engine' ),
        'singular_name'              => _x( 'Doc Category', 'Taxonomy Singular Name', 'zuno-docs-engine' ),
        'menu_name'                  => __( 'Categories', 'zuno-docs-engine' ),
        'all_items'                  => __( 'All Categories', 'zuno-docs-engine' ),
        'parent_item'                => __( 'Parent Category', 'zuno-docs-engine' ),
        'parent_item_colon'          => __( 'Parent Category:', 'zuno-docs-engine' ),
        'new_item_name'              => __( 'New Category Name', 'zuno-docs-engine' ),
        'add_new_item'               => __( 'Add New Category', 'zuno-docs-engine' ),
        'edit_item'                  => __( 'Edit Category', 'zuno-docs-engine' ),
        'update_item'                => __( 'Update Category', 'zuno-docs-engine' ),
        'view_item'                  => __( 'View Category', 'zuno-docs-engine' ),
        'separate_items_with_commas' => __( 'Separate categories with commas', 'zuno-docs-engine' ),
        'add_or_remove_items'        => __( 'Add or remove categories', 'zuno-docs-engine' ),
        'choose_from_most_used'      => __( 'Choose from the most used', 'zuno-docs-engine' ),
        'popular_items'              => __( 'Popular Categories', 'zuno-docs-engine' ),
        'search_items'               => __( 'Search Categories', 'zuno-docs-engine' ),
        'not_found'                  => __( 'Not Found', 'zuno-docs-engine' ),
        'no_terms'                   => __( 'No categories', 'zuno-docs-engine' ),
        'items_list'                 => __( 'Categories list', 'zuno-docs-engine' ),
        'items_list_navigation'      => __( 'Categories list navigation', 'zuno-docs-engine' ),
    );

    $args = array(
        'labels'            => $labels,
        'hierarchical'      => true,
        'public'            => false,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => false,
        'show_tagcloud'     => false,
        'show_in_rest'      => true,
        'rest_base'         => 'zuno-doc-categories',
        'meta_box_cb'       => false,
    );

    register_taxonomy( 'zuno_doc_category', array( 'zuno_doc' ), $args );
}

/* -----------------------------------------------------------------------
 * Register Taxonomy: zuno_product (non-hierarchical — for product tagging)
 * --------------------------------------------------------------------- */
function zuno_docs_register_product_taxonomy() {

    $labels = array(
        'name'                       => _x( 'Products', 'Taxonomy General Name', 'zuno-docs-engine' ),
        'singular_name'              => _x( 'Product', 'Taxonomy Singular Name', 'zuno-docs-engine' ),
        'menu_name'                  => __( 'Products', 'zuno-docs-engine' ),
        'all_items'                  => __( 'All Products', 'zuno-docs-engine' ),
        'new_item_name'              => __( 'New Product', 'zuno-docs-engine' ),
        'add_new_item'               => __( 'Add New Product', 'zuno-docs-engine' ),
        'edit_item'                  => __( 'Edit Product', 'zuno-docs-engine' ),
        'update_item'                => __( 'Update Product', 'zuno-docs-engine' ),
        'view_item'                  => __( 'View Product', 'zuno-docs-engine' ),
        'separate_items_with_commas' => __( 'Separate products with commas', 'zuno-docs-engine' ),
        'add_or_remove_items'        => __( 'Add or remove products', 'zuno-docs-engine' ),
        'choose_from_most_used'      => __( 'Choose from the most used', 'zuno-docs-engine' ),
        'popular_items'              => __( 'Popular Products', 'zuno-docs-engine' ),
        'search_items'               => __( 'Search Products', 'zuno-docs-engine' ),
        'not_found'                  => __( 'Not Found', 'zuno-docs-engine' ),
        'no_terms'                   => __( 'No products', 'zuno-docs-engine' ),
        'items_list'                 => __( 'Products list', 'zuno-docs-engine' ),
        'items_list_navigation'      => __( 'Products list navigation', 'zuno-docs-engine' ),
    );

    $args = array(
        'labels'            => $labels,
        'hierarchical'      => false,
        'public'            => false,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => false,
        'show_tagcloud'     => false,
        'show_in_rest'      => true,
        'rest_base'         => 'zuno-products',
        'meta_box_cb'       => false,
    );

    register_taxonomy( 'zuno_product', array( 'zuno_doc' ), $args );
}

/* -----------------------------------------------------------------------
 * Seed default terms (called on activation/upgrade)
 * Creates only ONE default category: "General".
 * --------------------------------------------------------------------- */
function zuno_docs_seed_default_terms() {

    /* ----- Seed default categories: only "General" ----- */
    $existing_cats = get_terms( array(
        'taxonomy'   => 'zuno_doc_category',
        'hide_empty' => false,
        'fields'     => 'ids',
    ) );

    if ( empty( $existing_cats ) || is_wp_error( $existing_cats ) ) {
        if ( ! term_exists( 'general', 'zuno_doc_category' ) ) {
            wp_insert_term(
                'General',
                'zuno_doc_category',
                array( 'slug' => 'general' )
            );
        }
    }

    /* ----- Seed product terms (shipox, express, storfox) for backward compat ----- */
    $existing_products = get_terms( array(
        'taxonomy'   => 'zuno_product',
        'hide_empty' => false,
        'fields'     => 'ids',
    ) );

    if ( empty( $existing_products ) || is_wp_error( $existing_products ) ) {
        $products = array(
            'shipox'  => 'Shipox',
            'express' => 'Shipox Express',
            'storfox' => 'Storfox',
        );

        foreach ( $products as $slug => $name ) {
            if ( ! term_exists( $slug, 'zuno_product' ) ) {
                wp_insert_term( $name, 'zuno_product', array( 'slug' => $slug ) );
            }
        }
    }
}

/* -----------------------------------------------------------------------
 * Prevent deletion of the LAST remaining category.
 * Uses pre_delete_term action (fires BEFORE deletion) to stop the delete.
 * --------------------------------------------------------------------- */
add_action( 'pre_delete_term', 'zuno_docs_prevent_delete_last_category', 10, 2 );
function zuno_docs_prevent_delete_last_category( $term, $taxonomy ) {
    if ( 'zuno_doc_category' !== $taxonomy ) {
        return;
    }
    if ( ! taxonomy_exists( 'zuno_doc_category' ) ) {
        return;
    }
    $existing = get_terms( array(
        'taxonomy'   => 'zuno_doc_category',
        'hide_empty' => false,
        'fields'     => 'ids',
    ) );
    if ( is_array( $existing ) && count( $existing ) <= 1 ) {
        wp_die( esc_html__( 'At least one documentation category is required. Create a new category before deleting the last one.', 'zuno-docs-engine' ) );
    }
}

/* -----------------------------------------------------------------------
 * Hide the Delete action for the last remaining category in admin row actions.
 * --------------------------------------------------------------------- */
add_filter( 'zuno_doc_category_row_actions', 'zuno_docs_hide_delete_last_category_action', 10, 2 );
function zuno_docs_hide_delete_last_category_action( $actions, $term ) {
    if ( ! taxonomy_exists( 'zuno_doc_category' ) ) {
        return $actions;
    }
    $existing = get_terms( array(
        'taxonomy'   => 'zuno_doc_category',
        'hide_empty' => false,
        'fields'     => 'ids',
    ) );
    if ( is_array( $existing ) && count( $existing ) <= 1 && isset( $actions['delete'] ) ) {
        unset( $actions['delete'] );
    }
    return $actions;
}

/* -----------------------------------------------------------------------
 * Ensure at least one category exists — only when ZERO categories exist
 * (fresh install or all categories removed via direct DB manipulation).
 * NEVER recreates a default if other categories exist.
 * --------------------------------------------------------------------- */
add_action( 'admin_init', 'zuno_docs_ensure_default_category_exists' );
function zuno_docs_ensure_default_category_exists() {
    if ( ! taxonomy_exists( 'zuno_doc_category' ) ) {
        return;
    }
    $existing = get_terms( array(
        'taxonomy'   => 'zuno_doc_category',
        'hide_empty' => false,
        'fields'     => 'ids',
    ) );
    if ( empty( $existing ) || is_wp_error( $existing ) ) {
        if ( ! term_exists( 'general', 'zuno_doc_category' ) ) {
            wp_insert_term(
                'General',
                'zuno_doc_category',
                array( 'slug' => 'general' )
            );
        }
    }
}
