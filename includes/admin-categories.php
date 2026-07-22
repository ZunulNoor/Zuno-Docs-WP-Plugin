<?php
/**
 * Zuno Docs Engine — Admin Categories Page
 *
 * @package zuno_docs
 */

defined( 'ABSPATH' ) || exit;

function zuno_docs_admin_categories_page() {
    if ( ! current_user_can( 'zuno_docs_read' ) ) {
        wp_die( __( 'You do not have sufficient permissions.', 'zuno-docs' ) );
    }

    $can_manage = current_user_can( 'zuno_docs_manage_categories' );
    $taxonomy   = 'zuno_doc_category';

    /* ----- Handle form actions ----- */
    $message = '';

    // Add category
    if ( $can_manage && isset( $_POST['zuno_docs_add_cat_nonce'] ) && wp_verify_nonce( $_POST['zuno_docs_add_cat_nonce'], 'zuno_docs_add_cat' ) ) {
        $name = sanitize_text_field( $_POST['zuno_docs_cat_name'] ?? '' );
        $slug = sanitize_title( $_POST['zuno_docs_cat_slug'] ?? '' );
        $parent = (int) ( $_POST['zuno_docs_cat_parent'] ?? 0 );

        if ( $name ) {
            $args = array( 'slug' => $slug ?: sanitize_title( $name ) );
            if ( $parent && term_exists( $parent, $taxonomy ) ) {
                $args['parent'] = $parent;
            }
            $result = wp_insert_term( $name, $taxonomy, $args );
            if ( is_wp_error( $result ) ) {
                $message = '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
            } else {
                $message = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Category added.', 'zuno-docs' ) . '</p></div>';
            }
        }
    }

    // Edit category
    if ( $can_manage && isset( $_POST['zuno_docs_edit_cat_nonce'] ) && wp_verify_nonce( $_POST['zuno_docs_edit_cat_nonce'], 'zuno_docs_edit_cat' ) ) {
        $cat_id = (int) ( $_POST['zuno_docs_cat_id'] ?? 0 );
        $name   = sanitize_text_field( $_POST['zuno_docs_cat_name'] ?? '' );
        $slug   = sanitize_title( $_POST['zuno_docs_cat_slug'] ?? '' );

        if ( $cat_id && $name ) {
            $args = array( 'name' => $name );
            if ( $slug ) {
                $args['slug'] = $slug;
            }
            $result = wp_update_term( $cat_id, $taxonomy, $args );
            if ( is_wp_error( $result ) ) {
                $message = '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
            } else {
                $message = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Category updated.', 'zuno-docs' ) . '</p></div>';
            }
        }
    }

    // Delete category — pre_delete_term filter in post-type.php handles last-category protection
    if ( $can_manage && isset( $_GET['action'], $_GET['cat_id'] ) && 'delete' === $_GET['action'] ) {
        $cat_id = (int) $_GET['cat_id'];
        if ( $cat_id && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'delete_cat_' . $cat_id ) ) {
            $result = wp_delete_term( $cat_id, $taxonomy );
            if ( is_wp_error( $result ) ) {
                $message = '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
            } else {
                $message = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Category deleted.', 'zuno-docs' ) . '</p></div>';
            }
        }
    }

    $categories = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ) );

    $edit_cat = null;
    if ( $can_manage && isset( $_GET['action'], $_GET['cat_id'] ) && 'edit' === $_GET['action'] ) {
        $edit_cat = get_term( (int) $_GET['cat_id'], $taxonomy );
    }
    ?>
    <div class="wrap zuno-docs-categories">
        <h1><?php esc_html_e( 'Doc Categories', 'zuno-docs' ); ?></h1>
        <?php echo $message; ?>

        <div class="zuno-docs-cats-layout">
            <?php if ( $can_manage ) : ?>
            <!-- Add / Edit form -->
            <div class="zuno-docs-cats-form">
                <?php if ( $edit_cat && ! is_wp_error( $edit_cat ) ) : ?>
                    <h2><?php esc_html_e( 'Edit Category', 'zuno-docs' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=zuno-docs-categories' ) ); ?>">
                        <?php wp_nonce_field( 'zuno_docs_edit_cat', 'zuno_docs_edit_cat_nonce' ); ?>
                        <input type="hidden" name="zuno_docs_cat_id" value="<?php echo esc_attr( $edit_cat->term_id ); ?>" />
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="zuno_docs_cat_name"><?php esc_html_e( 'Name', 'zuno-docs' ); ?></label></th>
                                <td><input type="text" id="zuno_docs_cat_name" name="zuno_docs_cat_name" value="<?php echo esc_attr( $edit_cat->name ); ?>" class="regular-text" required /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="zuno_docs_cat_slug"><?php esc_html_e( 'Slug', 'zuno-docs' ); ?></label></th>
                                <td><input type="text" id="zuno_docs_cat_slug" name="zuno_docs_cat_slug" value="<?php echo esc_attr( $edit_cat->slug ); ?>" class="regular-text" /></td>
                            </tr>
                        </table>
                        <?php submit_button( __( 'Update Category', 'zuno-docs' ) ); ?>
                    </form>
                <?php else : ?>
                    <h2><?php esc_html_e( 'Add New Category', 'zuno-docs' ); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'zuno_docs_add_cat', 'zuno_docs_add_cat_nonce' ); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="zuno_docs_cat_name"><?php esc_html_e( 'Name', 'zuno-docs' ); ?></label></th>
                                <td><input type="text" id="zuno_docs_cat_name" name="zuno_docs_cat_name" class="regular-text" required /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="zuno_docs_cat_slug"><?php esc_html_e( 'Slug', 'zuno-docs' ); ?></label></th>
                                <td><input type="text" id="zuno_docs_cat_slug" name="zuno_docs_cat_slug" class="regular-text" placeholder="<?php esc_attr_e( 'Auto-generated', 'zuno-docs' ); ?>" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="zuno_docs_cat_parent"><?php esc_html_e( 'Parent', 'zuno-docs' ); ?></label></th>
                                <td>
                                    <select id="zuno_docs_cat_parent" name="zuno_docs_cat_parent">
                                        <option value=""><?php esc_html_e( '— None —', 'zuno-docs' ); ?></option>
                                        <?php foreach ( $categories as $cat ) : ?>
                                            <option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button( __( 'Add Category', 'zuno-docs' ) ); ?>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Categories list -->
            <div class="zuno-docs-cats-list">
                <h2><?php esc_html_e( 'All Categories', 'zuno-docs' ); ?></h2>
                <?php if ( empty( $categories ) ) : ?>
                    <p><?php esc_html_e( 'No categories yet.', 'zuno-docs' ); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Name', 'zuno-docs' ); ?></th>
                                <th><?php esc_html_e( 'Slug', 'zuno-docs' ); ?></th>
                                <th><?php esc_html_e( 'Docs Count', 'zuno-docs' ); ?></th>
                                <?php if ( $can_manage ) : ?>
                                <th><?php esc_html_e( 'Actions', 'zuno-docs' ); ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $total_cats = count( $categories ); ?>
                            <?php foreach ( $categories as $index => $cat ) :
                                $edit_link   = admin_url( 'admin.php?page=zuno-docs-categories&action=edit&cat_id=' . $cat->term_id );
                                $delete_link = wp_nonce_url(
                                    admin_url( 'admin.php?page=zuno-docs-categories&action=delete&cat_id=' . $cat->term_id ),
                                    'delete_cat_' . $cat->term_id
                                );
                                $is_last = $total_cats <= 1;
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $cat->name ); ?></strong></td>
                                <td><code><?php echo esc_html( $cat->slug ); ?></code></td>
                                <td><?php echo esc_html( $cat->count ); ?></td>
                                <?php if ( $can_manage ) : ?>
                                <td class="zuno-docs-actions">
                                    <a href="<?php echo esc_url( $edit_link ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'zuno-docs' ); ?></a>
                                    <?php if ( $is_last ) : ?>
                                        <span class="button button-small button-link-delete zuno-docs-btn-disabled" title="<?php esc_attr_e( 'At least one documentation category is required.', 'zuno-docs' ); ?>"><?php esc_html_e( 'Delete', 'zuno-docs' ); ?></span>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url( $delete_link ); ?>" class="button button-small button-link-delete zuno-docs-delete-cat" data-confirm="<?php esc_attr_e( 'Delete this category? This action cannot be undone.', 'zuno-docs' ); ?>"><?php esc_html_e( 'Delete', 'zuno-docs' ); ?></a>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
