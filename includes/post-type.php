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
        'name'                  => _x( 'Docs', 'Post Type General Name', 'zuno-docs' ),
        'singular_name'         => _x( 'Doc', 'Post Type Singular Name', 'zuno-docs' ),
        'menu_name'             => __( 'Docs', 'zuno-docs' ),
        'name_admin_bar'        => __( 'Doc', 'zuno-docs' ),
        'archives'              => __( 'Doc Archives', 'zuno-docs' ),
        'attributes'            => __( 'Doc Attributes', 'zuno-docs' ),
        'all_items'             => __( 'All Docs', 'zuno-docs' ),
        'add_new_item'          => __( 'Add New Doc', 'zuno-docs' ),
        'add_new'               => __( 'Add New', 'zuno-docs' ),
        'new_item'              => __( 'New Doc', 'zuno-docs' ),
        'edit_item'             => __( 'Edit Doc', 'zuno-docs' ),
        'update_item'           => __( 'Update Doc', 'zuno-docs' ),
        'view_item'             => __( 'View Doc', 'zuno-docs' ),
        'view_items'            => __( 'View Docs', 'zuno-docs' ),
        'search_items'          => __( 'Search Doc', 'zuno-docs' ),
        'not_found'             => __( 'Not found', 'zuno-docs' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'zuno-docs' ),
        'insert_into_item'      => __( 'Insert into doc', 'zuno-docs' ),
        'uploaded_to_this_item' => __( 'Uploaded to this doc', 'zuno-docs' ),
        'items_list'            => __( 'Docs list', 'zuno-docs' ),
        'item_published'        => __( 'Doc published.', 'zuno-docs' ),
        'item_updated'          => __( 'Doc updated.', 'zuno-docs' ),
    );

    $args = array(
        'label'               => __( 'Docs', 'zuno-docs' ),
        'description'         => __( 'Documentation articles', 'zuno-docs' ),
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
        'capability_type'     => 'post',
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
        'name'                       => _x( 'Doc Categories', 'Taxonomy General Name', 'zuno-docs' ),
        'singular_name'              => _x( 'Doc Category', 'Taxonomy Singular Name', 'zuno-docs' ),
        'menu_name'                  => __( 'Categories', 'zuno-docs' ),
        'all_items'                  => __( 'All Categories', 'zuno-docs' ),
        'parent_item'                => __( 'Parent Category', 'zuno-docs' ),
        'parent_item_colon'          => __( 'Parent Category:', 'zuno-docs' ),
        'new_item_name'              => __( 'New Category Name', 'zuno-docs' ),
        'add_new_item'               => __( 'Add New Category', 'zuno-docs' ),
        'edit_item'                  => __( 'Edit Category', 'zuno-docs' ),
        'update_item'                => __( 'Update Category', 'zuno-docs' ),
        'view_item'                  => __( 'View Category', 'zuno-docs' ),
        'separate_items_with_commas' => __( 'Separate categories with commas', 'zuno-docs' ),
        'add_or_remove_items'        => __( 'Add or remove categories', 'zuno-docs' ),
        'choose_from_most_used'      => __( 'Choose from the most used', 'zuno-docs' ),
        'popular_items'              => __( 'Popular Categories', 'zuno-docs' ),
        'search_items'               => __( 'Search Categories', 'zuno-docs' ),
        'not_found'                  => __( 'Not Found', 'zuno-docs' ),
        'no_terms'                   => __( 'No categories', 'zuno-docs' ),
        'items_list'                 => __( 'Categories list', 'zuno-docs' ),
        'items_list_navigation'      => __( 'Categories list navigation', 'zuno-docs' ),
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
        'name'                       => _x( 'Products', 'Taxonomy General Name', 'zuno-docs' ),
        'singular_name'              => _x( 'Product', 'Taxonomy Singular Name', 'zuno-docs' ),
        'menu_name'                  => __( 'Products', 'zuno-docs' ),
        'all_items'                  => __( 'All Products', 'zuno-docs' ),
        'new_item_name'              => __( 'New Product', 'zuno-docs' ),
        'add_new_item'               => __( 'Add New Product', 'zuno-docs' ),
        'edit_item'                  => __( 'Edit Product', 'zuno-docs' ),
        'update_item'                => __( 'Update Product', 'zuno-docs' ),
        'view_item'                  => __( 'View Product', 'zuno-docs' ),
        'separate_items_with_commas' => __( 'Separate products with commas', 'zuno-docs' ),
        'add_or_remove_items'        => __( 'Add or remove products', 'zuno-docs' ),
        'choose_from_most_used'      => __( 'Choose from the most used', 'zuno-docs' ),
        'popular_items'              => __( 'Popular Products', 'zuno-docs' ),
        'search_items'               => __( 'Search Products', 'zuno-docs' ),
        'not_found'                  => __( 'Not Found', 'zuno-docs' ),
        'no_terms'                   => __( 'No products', 'zuno-docs' ),
        'items_list'                 => __( 'Products list', 'zuno-docs' ),
        'items_list_navigation'      => __( 'Products list navigation', 'zuno-docs' ),
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
 * Seed default product terms and categories (called on activation/upgrade)
 * --------------------------------------------------------------------- */
function zuno_docs_seed_default_terms() {

    /* ----- Seed product terms (shipox, express, storfox) ----- */
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

    /* ----- Seed default categories ----- */
    $existing_cats = get_terms( array(
        'taxonomy'   => 'zuno_doc_category',
        'hide_empty' => false,
        'fields'     => 'ids',
    ) );

    if ( empty( $existing_cats ) || is_wp_error( $existing_cats ) ) {
        $defaults = array(
            'getting-started' => 'Getting Started',
            'guides'          => 'Guides',
            'troubleshooting' => 'Troubleshooting',
        );

        foreach ( $defaults as $slug => $name ) {
            if ( ! term_exists( $slug, 'zuno_doc_category' ) ) {
                wp_insert_term( $name, 'zuno_doc_category', array( 'slug' => $slug ) );
            }
        }
    }
}
