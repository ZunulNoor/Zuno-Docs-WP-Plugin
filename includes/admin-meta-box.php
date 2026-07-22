<?php
/**
 * Zuno Docs Engine — Doc Settings Meta Box
 *
 * @package zuno_docs
 */

defined( 'ABSPATH' ) || exit;

/* -----------------------------------------------------------------------
 * Register meta box
 * --------------------------------------------------------------------- */
add_action( 'add_meta_boxes', 'zuno_docs_add_meta_box' );
function zuno_docs_add_meta_box() {
    add_meta_box(
        'zuno_docs_doc_settings',
        __( 'Doc Settings', 'zuno-docs-engine' ),
        'zuno_docs_render_meta_box',
        'zuno_doc',
        'side',
        'high'
    );
}

/* -----------------------------------------------------------------------
 * Render meta box
 * --------------------------------------------------------------------- */
function zuno_docs_render_meta_box( $post ) {
    wp_nonce_field( 'zuno_docs_save_doc_settings', 'zuno_docs_doc_settings_nonce' );

    /* ---- Get all categories ---- */
    $categories = get_terms( array(
        'taxonomy'   => 'zuno_doc_category',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ) );

    /* ---- Current values ---- */
    $current_categories = wp_get_post_terms( $post->ID, 'zuno_doc_category', array( 'fields' => 'ids' ) );
    $current_order      = get_post_meta( $post->ID, '_zuno_doc_order', true );
    ?>
    <style>
        #zuno_docs_doc_settings .zuno-docs-mb-field {
            margin-bottom: 14px;
        }
        #zuno_docs_doc_settings .zuno-docs-mb-field label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 12px;
            text-transform: uppercase;
            color: #50575e;
        }
        #zuno_docs_doc_settings .zuno-docs-mb-field select,
        #zuno_docs_doc_settings .zuno-docs-mb-field input[type="number"] {
            width: 100%;
        }
    </style>

    <div class="zuno-docs-mb-field">
        <label for="zuno-docs-mb-category"><?php esc_html_e( 'Category', 'zuno-docs-engine' ); ?></label>
        <select id="zuno-docs-mb-category" name="zuno_docs_category">
            <option value=""><?php esc_html_e( '— Select Category —', 'zuno-docs-engine' ); ?></option>
            <?php foreach ( $categories as $c ) : ?>
                <option value="<?php echo esc_attr( $c->term_id ); ?>" <?php selected( in_array( $c->term_id, $current_categories, true ) ); ?>>
                    <?php echo esc_html( $c->name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="zuno-docs-mb-field">
        <label for="zuno-docs-mb-order"><?php esc_html_e( 'Order', 'zuno-docs-engine' ); ?></label>
        <input
            type="number"
            id="zuno-docs-mb-order"
            name="zuno_docs_order"
            value="<?php echo esc_attr( $current_order ?: '0' ); ?>"
            min="0"
            step="1"
        />
        <p class="description" style="margin:2px 0 0;font-size:11px;">
            <?php esc_html_e( 'Lower numbers appear first.', 'zuno-docs-engine' ); ?>
        </p>
    </div>
    <?php
}

/* -----------------------------------------------------------------------
 * Save meta box data
 * --------------------------------------------------------------------- */
add_action( 'save_post', 'zuno_docs_save_meta_box' );
function zuno_docs_save_meta_box( $post_id ) {

    if ( ! isset( $_POST['zuno_docs_doc_settings_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['zuno_docs_doc_settings_nonce'], 'zuno_docs_save_doc_settings' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( 'zuno_doc' !== ( $_POST['post_type'] ?? '' ) ) {
        return;
    }

    /* ---- Save category term ---- */
    $category_id = (int) ( $_POST['zuno_docs_category'] ?? 0 );
    if ( $category_id && term_exists( $category_id, 'zuno_doc_category' ) ) {
        wp_set_object_terms( $post_id, array( $category_id ), 'zuno_doc_category' );
    } else {
        wp_set_object_terms( $post_id, array(), 'zuno_doc_category' );
    }

    /* ---- Save order ---- */
    $order = (int) ( $_POST['zuno_docs_order'] ?? 0 );
    update_post_meta( $post_id, '_zuno_doc_order', max( 0, $order ) );
}
