<?php
/**
 * Zuno Docs Engine – Layout Template
 *
 * @package zuno_docs
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/* ---------- Resolve display settings ---------- */
$show_search          = 'yes' === $settings['zuno_docs_show_search'];
$show_breadcrumbs     = 'yes' === $settings['zuno_docs_show_breadcrumbs'];
$show_previous        = 'yes' === $settings['zuno_docs_show_previous'];
$show_next            = 'yes' === $settings['zuno_docs_show_next'];
$show_navigation      = 'yes' === $settings['zuno_docs_show_navigation'];
$show_toc             = 'yes' === $settings['zuno_docs_show_toc'];
$show_categories      = 'yes' === $settings['zuno_docs_show_categories'];
$show_related         = 'yes' === $settings['zuno_docs_show_related_articles'];
$show_reading_progress = 'yes' === $settings['zuno_docs_show_reading_progress'];
$show_nav_rail         = 'yes' === $settings['zuno_docs_show_navigation_rail'];
$toc_position          = $settings['toc_position'] ?? 'left';
$rail_side             = $toc_position === 'left' ? 'right' : 'left';
$mobile_toc_position   = $settings['mobile_toc_position'] ?? 'top';
$show_sidebar          = $show_search || $show_toc;
?>
<div
    class="zuno-docs"
    data-product="<?php echo esc_attr( $product ); ?>"
    data-doc-id="<?php echo esc_attr( $doc_id ?? 0 ); ?>"
    data-toc-depth="<?php echo esc_attr( $toc_depth ); ?>"
    data-show-toc="<?php echo $show_toc ? '1' : '0'; ?>"
    data-show-search="<?php echo $show_search ? '1' : '0'; ?>"
    data-show-sidebar="<?php echo $show_sidebar ? '1' : '0'; ?>"
    data-show-reading-progress="<?php echo $show_reading_progress ? '1' : '0'; ?>"
    role="main"
    aria-label="<?php echo esc_attr( $page_title ); ?> documentation"
>

    <?php if ( $show_reading_progress ) : ?>
    <div class="zuno-docs-progress-bar" aria-hidden="true">
        <div class="zuno-docs-progress-bar-fill"></div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         MOBILE TOC (visible only on mobile)
         ============================================================ -->
    <?php if ( $show_sidebar ) : ?>
    <div class="zuno-docs-mobile-toc" data-mobile-toc-position="<?php echo esc_attr( $mobile_toc_position ); ?>">
        <button
            class="zuno-docs-mobile-toc-trigger"
            aria-expanded="false"
            aria-label="<?php esc_attr_e( 'Table of Contents', 'zuno-docs-engine' ); ?>"
        >
            <span class="zuno-docs-mobile-toc-label"><?php esc_html_e( 'Table of Contents', 'zuno-docs-engine' ); ?></span>
            <svg class="zuno-docs-mobile-toc-chevron" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
        </button>
        <div class="zuno-docs-mobile-toc-backdrop"></div>
        <div class="zuno-docs-mobile-toc-panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Table of Contents', 'zuno-docs-engine' ); ?>">
            <div class="zuno-docs-mobile-toc-panel-header">
                <h2 class="zuno-docs-mobile-toc-panel-title"><?php esc_html_e( 'Table of Contents', 'zuno-docs-engine' ); ?></h2>
                <button class="zuno-docs-mobile-toc-close" aria-label="<?php esc_attr_e( 'Close', 'zuno-docs-engine' ); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="zuno-docs-mobile-toc-panel-body">
                <!-- Search, suggestions, and TOC are cloned from sidebar by JS -->
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         SIDEBAR (desktop only on mobile, used as source for mobile TOC)
         ============================================================ -->
    <?php if ( $show_sidebar ) : ?>
    <aside class="zuno-docs-sidebar" aria-label="<?php esc_attr_e( 'Documentation navigation', 'zuno-docs-engine' ); ?>">

        <button
            class="zuno-docs-sidebar-toggle"
            aria-expanded="false"
            aria-label="<?php esc_attr_e( 'Toggle navigation', 'zuno-docs-engine' ); ?>"
        >
            <span class="zuno-docs-toggle-icon" aria-hidden="true"></span>
            <?php esc_html_e( 'Contents', 'zuno-docs-engine' ); ?>
        </button>

        <div class="zuno-docs-sidebar-inner">

            <?php if ( $show_search ) : ?>
            <!-- Search -->
            <div class="zuno-docs-search-wrap" role="search">
                <label for="zuno-docs-search" class="zuno-docs-sr-only">
                    <?php esc_html_e( 'Search documentation', 'zuno-docs-engine' ); ?>
                </label>
                <span class="zuno-docs-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                         stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </span>
                <input
                    type="search"
                    id="zuno-docs-search"
                    class="zuno-docs-search-input"
                    placeholder="<?php esc_attr_e( 'Search documentation…', 'zuno-docs-engine' ); ?>"
                    autocomplete="off"
                    spellcheck="false"
                    aria-label="<?php esc_attr_e( 'Search documentation', 'zuno-docs-engine' ); ?>"
                    data-min-query="2"
                />
                <button
                    class="zuno-docs-search-clear zuno-docs-hidden"
                    aria-label="<?php esc_attr_e( 'Clear search', 'zuno-docs-engine' ); ?>"
                >✕</button>
            </div>

            <!-- Suggestions dropdown -->
            <div class="zuno-docs-suggestions zuno-docs-hidden" role="listbox" aria-label="<?php esc_attr_e( 'Search suggestions', 'zuno-docs-engine' ); ?>"></div>

            <!-- No results -->
            <p class="zuno-docs-no-results zuno-docs-hidden" role="status" aria-live="polite">
                <?php esc_html_e( 'No results found.', 'zuno-docs-engine' ); ?>
            </p>
            <?php endif; ?>

            <?php if ( $show_toc ) : ?>
            <!-- TOC -->
            <nav class="zuno-docs-toc" id="zuno-docs-toc"
                 aria-label="<?php esc_attr_e( 'On this page', 'zuno-docs-engine' ); ?>">
                <p class="zuno-docs-toc-empty zuno-docs-hidden" aria-live="polite"></p>
            </nav>
            <?php endif; ?>

        </div>
    </aside>
    <?php endif; ?>

    <!-- ============================================================
         NAVIGATION RAIL (fixed, desktop only, opposite side of TOC)
         ============================================================ -->
<?php if ( $show_nav_rail ) : ?>
<nav class="zuno-docs-nav-rail zuno-docs-nav-rail--<?php echo esc_attr( $rail_side ); ?>"
         aria-label="<?php esc_attr_e( 'Section navigation', 'zuno-docs-engine' ); ?>"
         data-rail-side="<?php echo esc_attr( $rail_side ); ?>"></nav>
    <?php endif; ?>

    <!-- ============================================================
         CONTENT
         ============================================================ -->
    <div class="zuno-docs-content-wrap">
        <article class="zuno-docs-content" id="zuno-docs-content">

            <?php if ( $show_breadcrumbs ) : ?>
            <!-- Breadcrumbs -->
            <nav class="zuno-docs-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'zuno-docs-engine' ); ?>">
                <!-- Injected by JS -->
            </nav>
            <?php endif; ?>

            <?php
            if ( $page_content ) {
                echo $page_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                ?>
                <div class="zuno-docs-empty-state">
                    <p>
                        <?php
                        if ( $product ) {
                            printf(
                                /* translators: %s: product label */
                                esc_html__( 'Documentation for "%s" is coming soon.', 'zuno-docs-engine' ),
                                esc_html( zuno_docs_product_label( $product ) )
                            );
                        } else {
                            esc_html_e( 'No documentation selected.', 'zuno-docs-engine' );
                        }
                        ?>
                    </p>
                    <?php
                    $show_hint = $settings['show_admin_hint'];
                    if ( current_user_can( 'zuno_docs_edit' ) && 'yes' === $show_hint ) :
                    ?>
                        <p class="zuno-docs-admin-hint">
                            <?php
                            printf(
                                /* translators: %s: product slug */
                                esc_html__( 'Create a doc tagged with product "%s" (Zuno Docs → Add New) to populate this section.', 'zuno-docs-engine' ),
                                esc_html( $product )
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php
            }
            ?>

            <?php if ( $show_related ) : ?>
            <!-- Related articles -->
            <div class="zuno-docs-related-wrap" aria-label="<?php esc_attr_e( 'Related articles', 'zuno-docs-engine' ); ?>">
                <h3 class="zuno-docs-related-title"><?php esc_html_e( 'Related articles', 'zuno-docs-engine' ); ?></h3>
                <ul class="zuno-docs-related-list"></ul>
            </div>
            <?php endif; ?>

        </article>

        <?php if ( $show_navigation ) : ?>
        <!-- Prev / Next footer -->
        <footer class="zuno-docs-page-nav" aria-label="<?php esc_attr_e( 'Doc navigation', 'zuno-docs-engine' ); ?>"
                data-show-prev="<?php echo $show_previous ? '1' : '0'; ?>"
                data-show-next="<?php echo $show_next ? '1' : '0'; ?>">
            <!-- Injected by JS -->
        </footer>
        <?php endif; ?>
    </div>

</div>
