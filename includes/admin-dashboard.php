<?php
/**
 * Zuno Docs Engine — Admin Dashboard
 *
 * @package zuno_docs
 */

defined( 'ABSPATH' ) || exit;

function zuno_docs_admin_dashboard() {
    if ( ! current_user_can( 'zuno_docs_read' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'zuno-docs-engine' ) );
    }

    /* ----- Handle delete ----- */
    if ( isset( $_GET['action'], $_GET['doc'] ) && 'delete' === $_GET['action'] ) {
        if ( ! current_user_can( 'zuno_docs_delete' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions.', 'zuno-docs-engine' ) );
        }
        $doc_id = (int) $_GET['doc'];
        if ( $doc_id && wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ?? '' ), 'delete_doc_' . $doc_id ) ) {
            wp_delete_post( $doc_id, true );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Doc deleted.', 'zuno-docs-engine' ) . '</p></div>';
        }
    }

    /* ----- Filter values ----- */
    $filter_category = isset( $_GET['zuno_category'] ) ? (int) $_GET['zuno_category'] : 0;
    $paged           = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
    $per_page        = 20;

    /* ----- Stats via wp_count_posts (efficient, no full query) ----- */
    $counts    = (array) wp_count_posts( 'zuno_doc' );
    $total     = (int) ( $counts['publish'] ?? 0 ) + (int) ( $counts['draft'] ?? 0 ) + (int) ( $counts['pending'] ?? 0 );
    $published = (int) ( $counts['publish'] ?? 0 );
    $drafts    = (int) ( $counts['draft'] ?? 0 );

    $graph      = zuno_docs_get_graph();
    $graph_total = 0;
    if ( isset( $graph['doc_tree'] ) && is_array( $graph['doc_tree'] ) ) {
        foreach ( $graph['doc_tree'] as $slug => $tree ) {
            $graph_total += isset( $tree['flat_list'] ) ? count( $tree['flat_list'] ) : 0;
        }
    }

    /* ----- Build paginated query args ----- */
    $args = array(
        'post_type'      => 'zuno_doc',
        'post_status'    => array( 'publish', 'draft', 'pending' ),
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    );

    if ( $filter_category ) {
        $args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            array(
                'taxonomy' => 'zuno_doc_category',
                'field'    => 'term_id',
                'terms'    => $filter_category,
            ),
        );
    }

    $query = new WP_Query( $args );
    $docs  = $query->posts;
    $total_pages = $query->max_num_pages;

    /* ----- Taxonomy terms for filter dropdown ----- */
    $categories = get_terms( array(
        'taxonomy'   => 'zuno_doc_category',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ) );
    ?>
    <div class="wrap zuno-docs-dashboard">
        <h1>
            <?php esc_html_e( 'Zuno Docs', 'zuno-docs-engine' ); ?>
            <?php if ( current_user_can( 'zuno_docs_create' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=zuno-docs-new' ) ); ?>" class="page-title-action">
                    <?php esc_html_e( 'Add New Doc', 'zuno-docs-engine' ); ?>
                </a>
            <?php endif; ?>
        </h1>

        <!-- Stats cards -->
        <div class="zuno-docs-stats-grid">
            <div class="zuno-docs-stat-card">
                <span class="zuno-docs-stat-number"><?php echo esc_html( $total ); ?></span>
                <span class="zuno-docs-stat-label"><?php esc_html_e( 'Total Docs', 'zuno-docs-engine' ); ?></span>
            </div>
            <div class="zuno-docs-stat-card zuno-docs-stat-published">
                <span class="zuno-docs-stat-number"><?php echo esc_html( $published ); ?></span>
                <span class="zuno-docs-stat-label"><?php esc_html_e( 'Published', 'zuno-docs-engine' ); ?></span>
            </div>
            <div class="zuno-docs-stat-card zuno-docs-stat-draft">
                <span class="zuno-docs-stat-number"><?php echo esc_html( $drafts ); ?></span>
                <span class="zuno-docs-stat-label"><?php esc_html_e( 'Drafts', 'zuno-docs-engine' ); ?></span>
            </div>
            <div class="zuno-docs-stat-card zuno-docs-stat-cats">
                <span class="zuno-docs-stat-number"><?php echo esc_html( count( $categories ) ); ?></span>
                <span class="zuno-docs-stat-label"><?php esc_html_e( 'Categories', 'zuno-docs-engine' ); ?></span>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="zuno-docs-filter-bar">
            <form method="get" action="">
                <input type="hidden" name="page" value="zuno-docs" />

                <label for="zuno-docs-filter-category" class="zuno-docs-sr-admin">
                    <?php esc_html_e( 'Filter by category', 'zuno-docs-engine' ); ?>
                </label>
                <select id="zuno-docs-filter-category" name="zuno_category">
                    <option value=""><?php esc_html_e( 'All Categories', 'zuno-docs-engine' ); ?></option>
                    <?php foreach ( $categories as $c ) : ?>
                        <option value="<?php echo esc_attr( $c->term_id ); ?>" <?php selected( $filter_category, $c->term_id ); ?>>
                            <?php echo esc_html( $c->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="button"><?php esc_html_e( 'Filter', 'zuno-docs-engine' ); ?></button>

                <?php if ( $filter_category ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=zuno-docs' ) ); ?>" class="button">
                        <?php esc_html_e( 'Clear', 'zuno-docs-engine' ); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Docs table -->
        <div class="zuno-docs-docs-list-wrap">
            <?php if ( empty( $docs ) ) : ?>
                <div class="zuno-docs-empty-admin">
                    <p><?php esc_html_e( 'No documentation articles found.', 'zuno-docs-engine' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=zuno-docs-new' ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Create your first doc', 'zuno-docs-engine' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped zuno-docs-docs-table">
                    <thead>
                        <tr>
                            <th scope="col" class="column-title"><?php esc_html_e( 'Title', 'zuno-docs-engine' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Category', 'zuno-docs-engine' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Status', 'zuno-docs-engine' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Order', 'zuno-docs-engine' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Updated', 'zuno-docs-engine' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Actions', 'zuno-docs-engine' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $docs as $doc ) :
                            $doc_cats     = wp_get_post_terms( $doc->ID, 'zuno_doc_category', array( 'fields' => 'names' ) );
                            $doc_order    = get_post_meta( $doc->ID, '_zuno_doc_order', true );
                            $status_label = 'publish' === $doc->post_status ? __( 'Published', 'zuno-docs-engine' ) : ucfirst( $doc->post_status );
                            $status_class = 'publish' === $doc->post_status ? 'zuno-docs-status-published' : 'zuno-docs-status-draft';
                            $edit_link    = admin_url( 'post.php?post=' . $doc->ID . '&action=edit' );
                            $view_link    = get_permalink( $doc->ID );
                            $delete_link  = wp_nonce_url(
                                admin_url( 'admin.php?page=zuno-docs&action=delete&doc=' . $doc->ID ),
                                'delete_doc_' . $doc->ID
                            );
                        ?>
                        <tr>
                            <td class="column-title">
                                <strong><a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $doc->post_title ); ?></a></strong>
                            </td>
                            <td><?php echo esc_html( $doc_cats ? implode( ', ', $doc_cats ) : '—' ); ?></td>
                            <td><span class="zuno-docs-status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
                            <td><?php echo esc_html( $doc_order ?: '—' ); ?></td>
                            <td><?php echo esc_html( get_the_modified_date( 'Y-m-d', $doc->ID ) ); ?></td>
                            <td class="zuno-docs-actions">
                                <a href="<?php echo esc_url( $edit_link ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'zuno-docs-engine' ); ?></a>
                                <a href="<?php echo esc_url( $view_link ); ?>" class="button button-small" target="_blank"><?php esc_html_e( 'View', 'zuno-docs-engine' ); ?></a>
                                <?php if ( current_user_can( 'zuno_docs_delete' ) ) : ?>
                                    <a href="<?php echo esc_url( $delete_link ); ?>" class="button button-small button-link-delete zuno-docs-delete-doc" data-confirm="<?php esc_attr_e( 'Delete this doc permanently?', 'zuno-docs-engine' ); ?>"><?php esc_html_e( 'Delete', 'zuno-docs-engine' ); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ( $total_pages > 1 ) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo wp_kses_post( paginate_links( array(
                                'base'      => add_query_arg( 'paged', '%#%' ),
                                'format'    => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total'     => $total_pages,
                                'current'   => $paged,
                                'type'      => 'plain',
                            ) ) );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
