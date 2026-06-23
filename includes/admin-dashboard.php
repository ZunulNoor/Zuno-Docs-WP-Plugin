<?php
/**
 * Zuno Docs Engine — Admin Dashboard
 *
 * @package zuno_docs
 */

defined( 'ABSPATH' ) || exit;

function zuno_docs_admin_dashboard() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions.', 'zuno-docs' ) );
    }

    /* ----- Handle delete ----- */
    if ( isset( $_GET['action'], $_GET['doc'] ) && 'delete' === $_GET['action'] ) {
        $doc_id = (int) $_GET['doc'];
        if ( $doc_id && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'delete_doc_' . $doc_id ) ) {
            wp_delete_post( $doc_id, true );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Doc deleted.', 'zuno-docs' ) . '</p></div>';
        }
    }

    /* ----- Filter values ----- */
    $filter_product  = isset( $_GET['zuno_product'] ) ? sanitize_key( $_GET['zuno_product'] ) : '';
    $filter_category = isset( $_GET['zuno_category'] ) ? (int) $_GET['zuno_category'] : 0;

    /* ----- Build query args ----- */
    $args = array(
        'post_type'      => 'zuno_doc',
        'post_status'    => array( 'publish', 'draft', 'pending' ),
        'posts_per_page' => -1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    );

    $tax_query = array( 'relation' => 'AND' );

    if ( $filter_product ) {
        $tax_query[] = array(
            'taxonomy' => 'zuno_product',
            'field'    => 'slug',
            'terms'    => $filter_product,
        );
    }

    if ( $filter_category ) {
        $tax_query[] = array(
            'taxonomy' => 'zuno_doc_category',
            'field'    => 'term_id',
            'terms'    => $filter_category,
        );
    }

    if ( count( $tax_query ) > 1 ) {
        $args['tax_query'] = $tax_query;
    }

    $query = new WP_Query( $args );
    $docs  = $query->posts;

    /* ----- Stats from precomputed graph (fast) ----- */
    $graph      = zuno_docs_get_graph();
    $total      = 0;
    $published  = 0;
    $drafts     = 0;

    foreach ( $docs as $doc ) {
        $total++;
        if ( 'publish' === $doc->post_status ) {
            $published++;
        } elseif ( 'draft' === $doc->post_status ) {
            $drafts++;
        }
    }

    $graph_total = 0;
    foreach ( $graph['doc_tree'] as $slug => $tree ) {
        $graph_total += count( $tree['flat_list'] );
    }

    /* ----- Taxonomy terms for filter dropdowns ----- */
    $products   = get_terms( array( 'taxonomy' => 'zuno_product', 'hide_empty' => false ) );
    $categories = get_terms( array( 'taxonomy' => 'zuno_doc_category', 'hide_empty' => false ) );
    ?>
    <div class="wrap zuno-docs-dashboard">
        <h1>
            <?php esc_html_e( 'Zuno Docs', 'zuno-docs' ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=zuno-docs-new' ) ); ?>" class="page-title-action">
                <?php esc_html_e( 'Add New Doc', 'zuno-docs' ); ?>
            </a>
        </h1>

        <!-- Stats cards -->
        <div class="zuno-docs-stats-grid">
            <div class="zuno-docs-stat-card">
                <span class="zuno-docs-stat-number"><?php echo esc_html( $total ); ?></span>
                <span class="zuno-docs-stat-label"><?php esc_html_e( 'Total Docs', 'zuno-docs' ); ?></span>
            </div>
            <div class="zuno-docs-stat-card zuno-docs-stat-published">
                <span class="zuno-docs-stat-number"><?php echo esc_html( $published ); ?></span>
                <span class="zuno-docs-stat-label"><?php esc_html_e( 'Published', 'zuno-docs' ); ?></span>
            </div>
            <div class="zuno-docs-stat-card zuno-docs-stat-draft">
                <span class="zuno-docs-stat-number"><?php echo esc_html( $drafts ); ?></span>
                <span class="zuno-docs-stat-label"><?php esc_html_e( 'Drafts', 'zuno-docs' ); ?></span>
            </div>
            <div class="zuno-docs-stat-card zuno-docs-stat-cats">
                <span class="zuno-docs-stat-number"><?php echo esc_html( count( $categories ) ); ?></span>
                <span class="zuno-docs-stat-label"><?php esc_html_e( 'Categories', 'zuno-docs' ); ?></span>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="zuno-docs-filter-bar">
            <form method="get" action="">
                <input type="hidden" name="page" value="zuno-docs" />

                <label for="zuno-docs-filter-product" class="zuno-docs-sr-admin">
                    <?php esc_html_e( 'Filter by product', 'zuno-docs' ); ?>
                </label>
                <select id="zuno-docs-filter-product" name="zuno_product">
                    <option value=""><?php esc_html_e( 'All Products', 'zuno-docs' ); ?></option>
                    <?php foreach ( $products as $p ) : ?>
                        <option value="<?php echo esc_attr( $p->slug ); ?>" <?php selected( $filter_product, $p->slug ); ?>>
                            <?php echo esc_html( $p->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="zuno-docs-filter-category" class="zuno-docs-sr-admin">
                    <?php esc_html_e( 'Filter by category', 'zuno-docs' ); ?>
                </label>
                <select id="zuno-docs-filter-category" name="zuno_category">
                    <option value=""><?php esc_html_e( 'All Categories', 'zuno-docs' ); ?></option>
                    <?php foreach ( $categories as $c ) : ?>
                        <option value="<?php echo esc_attr( $c->term_id ); ?>" <?php selected( $filter_category, $c->term_id ); ?>>
                            <?php echo esc_html( $c->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="button"><?php esc_html_e( 'Filter', 'zuno-docs' ); ?></button>

                <?php if ( $filter_product || $filter_category ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=zuno-docs' ) ); ?>" class="button">
                        <?php esc_html_e( 'Clear', 'zuno-docs' ); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Docs table -->
        <div class="zuno-docs-docs-list-wrap">
            <?php if ( empty( $docs ) ) : ?>
                <div class="zuno-docs-empty-admin">
                    <p><?php esc_html_e( 'No documentation articles found.', 'zuno-docs' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=zuno-docs-new' ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Create your first doc', 'zuno-docs' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped zuno-docs-docs-table">
                    <thead>
                        <tr>
                            <th scope="col" class="column-title"><?php esc_html_e( 'Title', 'zuno-docs' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Product', 'zuno-docs' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Category', 'zuno-docs' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Status', 'zuno-docs' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Order', 'zuno-docs' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Updated', 'zuno-docs' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Actions', 'zuno-docs' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $docs as $doc ) :
                            $doc_products = wp_get_post_terms( $doc->ID, 'zuno_product', array( 'fields' => 'names' ) );
                            $doc_cats     = wp_get_post_terms( $doc->ID, 'zuno_doc_category', array( 'fields' => 'names' ) );
                            $doc_order    = get_post_meta( $doc->ID, '_zuno_doc_order', true );
                            $status_label = 'publish' === $doc->post_status ? __( 'Published', 'zuno-docs' ) : ucfirst( $doc->post_status );
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
                            <td><?php echo esc_html( $doc_products ? implode( ', ', $doc_products ) : '—' ); ?></td>
                            <td><?php echo esc_html( $doc_cats ? implode( ', ', $doc_cats ) : '—' ); ?></td>
                            <td><span class="zuno-docs-status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
                            <td><?php echo esc_html( $doc_order ?: '—' ); ?></td>
                            <td><?php echo esc_html( get_the_modified_date( 'Y-m-d', $doc->ID ) ); ?></td>
                            <td class="zuno-docs-actions">
                                <a href="<?php echo esc_url( $edit_link ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'zuno-docs' ); ?></a>
                                <a href="<?php echo esc_url( $view_link ); ?>" class="button button-small" target="_blank"><?php esc_html_e( 'View', 'zuno-docs' ); ?></a>
                                <a href="<?php echo esc_url( $delete_link ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Delete this doc permanently?', 'zuno-docs' ); ?>');"><?php esc_html_e( 'Delete', 'zuno-docs' ); ?></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
