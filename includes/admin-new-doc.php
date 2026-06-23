<?php
/**
 * Zuno Docs Engine — Add New Doc Page
 *
 * @package zuno_docs
 */

defined( 'ABSPATH' ) || exit;

function zuno_docs_admin_new_doc_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions.', 'zuno-docs' ) );
    }

    $categories = get_terms( array(
        'taxonomy'   => 'zuno_doc_category',
        'hide_empty' => false,
    ) );

    /* ----- Handle form submission ----- */
    $saved = false;
    if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['zuno_docs_new_doc_nonce'] ) ) {
        if ( wp_verify_nonce( $_POST['zuno_docs_new_doc_nonce'], 'zuno_docs_new_doc' ) ) {
            $title   = sanitize_text_field( $_POST['zuno_docs_title'] ?? '' );
            $content = wp_kses_post( $_POST['zuno_docs_content'] ?? '' );
            $cat_id  = (int) ( $_POST['zuno_docs_category'] ?? 0 );

            if ( $title ) {
                $post_id = wp_insert_post( array(
                    'post_type'    => 'zuno_doc',
                    'post_title'   => $title,
                    'post_content' => $content,
                    'post_status'  => 'publish',
                ) );

                if ( $post_id && ! is_wp_error( $post_id ) ) {
                    if ( $cat_id && term_exists( $cat_id, 'zuno_doc_category' ) ) {
                        wp_set_object_terms( $post_id, (int) $cat_id, 'zuno_doc_category' );
                    }
                    $saved = true;
                    $edit_link = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
                }
            }
        }
    }
    ?>
    <div class="wrap zuno-docs-new-doc">
        <h1><?php esc_html_e( 'Add New Doc', 'zuno-docs' ); ?></h1>

        <?php if ( $saved ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'Doc created successfully.', 'zuno-docs' ); ?>
                <a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edit with Gutenberg', 'zuno-docs' ); ?></a></p>
            </div>
        <?php endif; ?>

        <div class="zuno-docs-new-doc-layout">
            <!-- Quick-create form -->
            <div class="zuno-docs-quick-form">
                <h2><?php esc_html_e( 'Quick Create', 'zuno-docs' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Write a title and content below, or use the full editor for advanced formatting.', 'zuno-docs' ); ?></p>

                <form method="post" action="">
                    <?php wp_nonce_field( 'zuno_docs_new_doc', 'zuno_docs_new_doc_nonce' ); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="zuno_docs_title"><?php esc_html_e( 'Title', 'zuno-docs' ); ?></label></th>
                            <td><input type="text" id="zuno_docs_title" name="zuno_docs_title" class="regular-text" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zuno_docs_category"><?php esc_html_e( 'Category', 'zuno-docs' ); ?></label></th>
                            <td>
                                <select id="zuno_docs_category" name="zuno_docs_category">
                                    <option value=""><?php esc_html_e( '— Select —', 'zuno-docs' ); ?></option>
                                    <?php foreach ( $categories as $cat ) : ?>
                                        <option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zuno_docs_content"><?php esc_html_e( 'Content', 'zuno-docs' ); ?></label></th>
                            <td>
                                <?php
                                wp_editor(
                                    '',
                                    'zuno_docs_content',
                                    array(
                                        'textarea_rows' => 20,
                                        'media_buttons' => true,
                                        'teeny'         => false,
                                        'quicktags'     => true,
                                    )
                                );
                                ?>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Create Doc', 'zuno-docs' ); ?></button>
                    </p>
                </form>
            </div>

            <!-- Link to full editor -->
            <div class="zuno-docs-full-editor-link">
                <h2><?php esc_html_e( 'Full Editor', 'zuno-docs' ); ?></h2>
                <p><?php esc_html_e( 'Use the full Gutenberg block editor for rich content, embeds, and advanced layouts.', 'zuno-docs' ); ?></p>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=zuno_doc' ) ); ?>" class="button button-primary button-hero">
                    <?php esc_html_e( 'Open Gutenberg Editor', 'zuno-docs' ); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
