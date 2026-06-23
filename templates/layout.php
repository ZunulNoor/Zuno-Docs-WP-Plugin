<?php
/**
 * Zuno Docs Engine – Layout Template
 *
 * @package zuno_docs
 */

defined( 'ABSPATH' ) || exit;
?>

<div
    class="zuno-docs-wrap"
    data-product="<?php echo esc_attr( $product ); ?>"
    data-toc-depth="<?php echo esc_attr( $toc_depth ); ?>"
    role="main"
    aria-label="<?php echo esc_attr( $page_title ); ?> documentation"
>

    <!-- ============================================================
         LEFT SIDEBAR
         ============================================================ -->
    <aside class="zuno-docs-sidebar" aria-label="Documentation navigation">

        <!-- Mobile toggle button (visible only on small screens) -->
        <button
            class="zuno-docs-sidebar-toggle"
            aria-expanded="false"
            aria-controls="zuno-docs-sidebar-inner"
            aria-label="<?php esc_attr_e( 'Toggle navigation', 'zuno-docs' ); ?>"
        >
            <span class="zuno-docs-toggle-icon" aria-hidden="true"></span>
            <?php esc_html_e( 'Contents', 'zuno-docs' ); ?>
        </button>

        <div class="zuno-docs-sidebar-inner" id="zuno-docs-sidebar-inner">

            <!-- Search (fixed at top of sidebar) -->
            <div class="zuno-docs-search-wrap" role="search">
                <label for="zuno-docs-search" class="zuno-docs-sr-only">
                    <?php esc_html_e( 'Search documentation', 'zuno-docs' ); ?>
                </label>
                <span class="zuno-docs-search-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </span>
                <input
                    type="search"
                    id="zuno-docs-search"
                    class="zuno-docs-search-input"
                    placeholder="<?php esc_attr_e( 'Search docs…', 'zuno-docs' ); ?>"
                    autocomplete="off"
                    spellcheck="false"
                    aria-label="<?php esc_attr_e( 'Search documentation', 'zuno-docs' ); ?>"
                />
                <button
                    class="zuno-docs-search-clear zuno-docs-hidden"
                    aria-label="<?php esc_attr_e( 'Clear search', 'zuno-docs' ); ?>"
                    tabindex="0"
                >✕</button>
            </div>

            <!-- No-results message (shown by JS) -->
            <p class="zuno-docs-no-results zuno-docs-hidden" role="status" aria-live="polite">
                <?php esc_html_e( 'No results found.', 'zuno-docs' ); ?>
            </p>

            <!-- TOC (built by JS from content headings) -->
            <nav
                class="zuno-docs-toc"
                id="zuno-docs-toc"
                aria-label="<?php esc_attr_e( 'On this page', 'zuno-docs' ); ?>"
            >
                <p class="zuno-docs-toc-label" aria-hidden="true">
                    <?php esc_html_e( 'On this page', 'zuno-docs' ); ?>
                </p>
                <!-- <ul> injected here by docs.js -->
            </nav>

        </div><!-- /.zuno-docs-sidebar-inner -->
    </aside><!-- /.zuno-docs-sidebar -->

    <!-- ============================================================
         MAIN CONTENT AREA
         ============================================================ -->
    <div class="zuno-docs-content-wrap">
        <article class="zuno-docs-content" id="zuno-docs-content">
            <?php
            if ( $page_content ) {
                echo $page_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                ?>
                <div class="zuno-docs-empty-state">
                    <p>
                        <?php
                        printf(
                            esc_html__( 'Documentation for "%s" is coming soon.', 'zuno-docs' ),
                            esc_html( zuno_docs_product_label( $product ) )
                        );
                        ?>
                    </p>
                    <?php
                    $show_hint = isset( $settings['show_admin_hint'] ) ? $settings['show_admin_hint'] : 'yes';
                    if ( current_user_can( 'edit_posts' ) && 'yes' === $show_hint ) :
                    ?>
                        <p class="zuno-docs-admin-hint">
                            <?php
                            printf(
                                esc_html__( 'Create a doc tagged with product "%s" (Zuno Docs → Add New) to populate this section.', 'zuno-docs' ),
                                esc_html( $product )
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php
            }
            ?>
        </article>
    </div><!-- /.zuno-docs-content-wrap -->

</div><!-- /.zuno-docs-wrap -->
