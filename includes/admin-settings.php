<?php
/**
 * Zuno Docs Engine — Admin Settings Panel
 *
 * @package zuno_docs
 */

defined( 'ABSPATH' ) || exit;

/* -----------------------------------------------------------------------
 * Settings page
 * --------------------------------------------------------------------- */
function zuno_docs_admin_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions.', 'zuno-docs' ) );
    }

    $saved_notice = '';

    if ( isset( $_POST['zuno_docs_rebuild_cache'] ) && wp_verify_nonce( $_POST['zuno_docs_rebuild_cache_nonce'], 'zuno_docs_rebuild_cache' ) ) {
        zuno_docs_rebuild_graph();
        $saved_notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Documentation cache rebuilt successfully.', 'zuno-docs' ) . '</p></div>';
    }

    if ( isset( $_POST['zuno_docs_settings_nonce'] ) && wp_verify_nonce( $_POST['zuno_docs_settings_nonce'], 'zuno_docs_save_settings' ) ) {

        $s = array();

        $s['h1_size']       = max( 14, min( 72, (int) ( $_POST['h1_size'] ?? 32 ) ) );
        $s['h2_size']       = max( 14, min( 60, (int) ( $_POST['h2_size'] ?? 24 ) ) );
        $s['h3_size']       = max( 12, min( 48, (int) ( $_POST['h3_size'] ?? 19 ) ) );
        $s['h4_size']       = max( 12, min( 40, (int) ( $_POST['h4_size'] ?? 17 ) ) );
        $s['h5_size']       = max( 12, min( 40, (int) ( $_POST['h5_size'] ?? 16 ) ) );
        $s['h6_size']       = max( 12, min( 40, (int) ( $_POST['h6_size'] ?? 15 ) ) );
        $s['p_size']        = max( 10, min( 32, (int) ( $_POST['p_size'] ?? 14 ) ) );
        $s['line_height']   = round( max( 1.0, min( 3.0, (float) ( $_POST['line_height'] ?? 1.7 ) ) ), 1 );

        $s['toc_depth']     = max( 2, min( 6, (int) ( $_POST['toc_depth'] ?? 6 ) ) );

        $s['toc_position']  = in_array( $_POST['toc_position'] ?? '', array( 'left', 'right' ), true ) ? $_POST['toc_position'] : 'left';
        $s['sidebar_width'] = max( 20, min( 50, (int) ( $_POST['sidebar_width'] ?? 30 ) ) );

        $s['toc_bg']           = sanitize_hex_color( $_POST['toc_bg'] ?? '#f8f9fb' ) ?: '#f8f9fb';
        $s['toc_text']         = sanitize_hex_color( $_POST['toc_text'] ?? '#475569' ) ?: '#475569';
        $s['toc_hover']        = sanitize_hex_color( $_POST['toc_hover'] ?? '#f0f2f5' ) ?: '#f0f2f5';
        $s['toc_active_text']  = sanitize_hex_color( $_POST['toc_active_text'] ?? '#993C1D' ) ?: '#993C1D';
        $s['enable_active_bg'] = ! empty( $_POST['enable_active_bg'] ) ? 'yes' : 'no';
        $s['toc_active_bg']    = sanitize_hex_color( $_POST['toc_active_bg'] ?? '#fef0e9' ) ?: '#fef0e9';
        $s['toc_active_bar']   = sanitize_hex_color( $_POST['toc_active_bar'] ?? '#E8500A' ) ?: '#E8500A';
        $s['enable_heading_bg'] = ! empty( $_POST['enable_heading_bg'] ) ? 'yes' : 'no';
        $s['toc_heading_bg']   = sanitize_hex_color( $_POST['toc_heading_bg'] ?? '#f0f2f5' ) ?: '#f0f2f5';

        $s['highlight_bg']     = sanitize_hex_color( $_POST['highlight_bg'] ?? '#ffcc00' ) ?: '#ffcc00';
        $s['highlight_text']   = sanitize_hex_color( $_POST['highlight_text'] ?? '#000000' ) ?: '#000000';

        $s['zuno_docs_theme_color'] = sanitize_hex_color( $_POST['zuno_docs_theme_color'] ?? '#2563EB' ) ?: '#2563EB';

        $s['show_admin_hint']  = ! empty( $_POST['show_admin_hint'] ) ? 'yes' : 'no';

        $display_toggles = array(
            'zuno_docs_show_search',
            'zuno_docs_show_breadcrumbs',
            'zuno_docs_show_previous',
            'zuno_docs_show_next',
            'zuno_docs_show_navigation',
            'zuno_docs_show_toc',
            'zuno_docs_show_categories',
            'zuno_docs_show_related_articles',
            'zuno_docs_show_reading_progress',
        );
        foreach ( $display_toggles as $key ) {
            $s[ $key ] = ! empty( $_POST[ $key ] ) ? 'yes' : 'no';
        }

        update_option( 'zuno_docs_settings', $s );
        $saved_notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'zuno-docs' ) . '</p></div>';
    }

    $s = zuno_docs_get_settings();

    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    ?>
    <div class="wrap zuno-docs-settings-page">
        <h1><?php esc_html_e( 'Zuno Docs Settings', 'zuno-docs' ); ?></h1>
        <?php echo $saved_notice; ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'zuno_docs_save_settings', 'zuno_docs_settings_nonce' ); ?>

            <div class="zuno-docs-settings-tabs">
                <nav class="zuno-docs-tab-nav">
                    <a href="#zuno-docs-tab-appearance" class="zuno-docs-tab-active"><?php esc_html_e( 'Appearance', 'zuno-docs' ); ?></a>
                    <a href="#zuno-docs-tab-typography"><?php esc_html_e( 'Typography', 'zuno-docs' ); ?></a>
                    <a href="#zuno-docs-tab-layout"><?php esc_html_e( 'Layout', 'zuno-docs' ); ?></a>
                    <a href="#zuno-docs-tab-toc"><?php esc_html_e( 'TOC Colors', 'zuno-docs' ); ?></a>
                    <a href="#zuno-docs-tab-highlight"><?php esc_html_e( 'Highlight', 'zuno-docs' ); ?></a>
                    <a href="#zuno-docs-tab-behavior"><?php esc_html_e( 'Behavior', 'zuno-docs' ); ?></a>
                    <a href="#zuno-docs-tab-display"><?php esc_html_e( 'Display', 'zuno-docs' ); ?></a>
                </nav>

                <!-- APPEARANCE -->
                <section id="zuno-docs-tab-appearance" class="zuno-docs-tab-panel zuno-docs-tab-active">
                    <h2><?php esc_html_e( 'Appearance', 'zuno-docs' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Control the visual appearance of your documentation.', 'zuno-docs' ); ?></p>

                    <table class="form-table">
                        <tr>
                            <th><label for="zuno_docs_theme_color"><?php esc_html_e( 'Theme Accent Color', 'zuno-docs' ); ?></label></th>
                            <td>
                                <input type="text" id="zuno_docs_theme_color" name="zuno_docs_theme_color" value="<?php echo esc_attr( $s['zuno_docs_theme_color'] ); ?>" class="zuno-docs-color-picker" data-default-color="#2563EB" />
                                <p class="description"><?php esc_html_e( 'Controls active TOC indicators, progress bar color, search focus, and link colors.', 'zuno-docs' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Reading Progress Bar', 'zuno-docs' ); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="zuno_docs_show_reading_progress" value="yes" <?php checked( $s['zuno_docs_show_reading_progress'], 'yes' ); ?> />
                                        <?php esc_html_e( 'Display a reading progress bar at the top of the page', 'zuno-docs' ); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </section>

                <!-- TYPOGRAPHY -->
                <section id="zuno-docs-tab-typography" class="zuno-docs-tab-panel">
                    <h2><?php esc_html_e( 'Typography', 'zuno-docs' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Control font sizes and line height for documentation content.', 'zuno-docs' ); ?></p>

                    <table class="form-table">
                        <tr>
                            <th><label for="h1_size"><?php esc_html_e( 'H1 Size', 'zuno-docs' ); ?></label></th>
                            <td><input type="number" id="h1_size" name="h1_size" value="<?php echo esc_attr( $s['h1_size'] ); ?>" min="14" max="72" class="small-text" /> px</td>
                        </tr>
                        <tr>
                            <th><label for="h2_size"><?php esc_html_e( 'H2 Size', 'zuno-docs' ); ?></label></th>
                            <td><input type="number" id="h2_size" name="h2_size" value="<?php echo esc_attr( $s['h2_size'] ); ?>" min="14" max="60" class="small-text" /> px</td>
                        </tr>
                        <tr>
                            <th><label for="h3_size"><?php esc_html_e( 'H3 Size', 'zuno-docs' ); ?></label></th>
                            <td><input type="number" id="h3_size" name="h3_size" value="<?php echo esc_attr( $s['h3_size'] ); ?>" min="12" max="48" class="small-text" /> px</td>
                        </tr>
                        <tr>
                            <th><label for="h4_size"><?php esc_html_e( 'H4 Size', 'zuno-docs' ); ?></label></th>
                            <td><input type="number" id="h4_size" name="h4_size" value="<?php echo esc_attr( $s['h4_size'] ); ?>" min="12" max="40" class="small-text" /> px</td>
                        </tr>
                        <tr>
                            <th><label for="h5_size"><?php esc_html_e( 'H5 Size', 'zuno-docs' ); ?></label></th>
                            <td><input type="number" id="h5_size" name="h5_size" value="<?php echo esc_attr( $s['h5_size'] ); ?>" min="12" max="40" class="small-text" /> px</td>
                        </tr>
                        <tr>
                            <th><label for="h6_size"><?php esc_html_e( 'H6 Size', 'zuno-docs' ); ?></label></th>
                            <td><input type="number" id="h6_size" name="h6_size" value="<?php echo esc_attr( $s['h6_size'] ); ?>" min="12" max="40" class="small-text" /> px</td>
                        </tr>
                        <tr>
                            <th><label for="p_size"><?php esc_html_e( 'Paragraph Size', 'zuno-docs' ); ?></label></th>
                            <td><input type="number" id="p_size" name="p_size" value="<?php echo esc_attr( $s['p_size'] ); ?>" min="10" max="32" class="small-text" /> px</td>
                        </tr>
                        <tr>
                            <th><label for="line_height"><?php esc_html_e( 'Line Height', 'zuno-docs' ); ?></label></th>
                            <td><input type="number" id="line_height" name="line_height" value="<?php echo esc_attr( $s['line_height'] ); ?>" min="1.0" max="3.0" step="0.1" class="small-text" /></td>
                        </tr>
                    </table>
                </section>

                <!-- LAYOUT -->
                <section id="zuno-docs-tab-layout" class="zuno-docs-tab-panel">
                    <h2><?php esc_html_e( 'Layout', 'zuno-docs' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Control sidebar position, widths, and TOC depth.', 'zuno-docs' ); ?></p>

                    <table class="form-table">
                        <tr>
                            <th><label for="toc_position"><?php esc_html_e( 'TOC Position', 'zuno-docs' ); ?></label></th>
                            <td>
                                <select id="toc_position" name="toc_position">
                                    <option value="left" <?php selected( $s['toc_position'], 'left' ); ?>><?php esc_html_e( 'Left', 'zuno-docs' ); ?></option>
                                    <option value="right" <?php selected( $s['toc_position'], 'right' ); ?>><?php esc_html_e( 'Right', 'zuno-docs' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sidebar_width"><?php esc_html_e( 'Sidebar Width', 'zuno-docs' ); ?></label></th>
                            <td>
                                <input type="number" id="sidebar_width" name="sidebar_width" value="<?php echo esc_attr( $s['sidebar_width'] ); ?>" min="20" max="50" class="small-text" /> %
                                <p class="description"><?php esc_html_e( 'Content area fills the remaining width automatically.', 'zuno-docs' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="toc_depth"><?php esc_html_e( 'TOC Depth', 'zuno-docs' ); ?></label></th>
                            <td>
                                <select id="toc_depth" name="toc_depth">
                                    <?php for ( $i = 2; $i <= 6; $i++ ) : ?>
                                        <option value="<?php echo esc_attr( $i ); ?>" <?php selected( $s['toc_depth'], $i ); ?>>
                                            <?php echo esc_html( sprintf( __( 'H1–H%s', 'zuno-docs' ), $i ) ); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </section>

                <!-- TOC COLORS -->
                <section id="zuno-docs-tab-toc" class="zuno-docs-tab-panel">
                    <h2><?php esc_html_e( 'TOC Colors', 'zuno-docs' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Customize the sidebar table of contents appearance.', 'zuno-docs' ); ?></p>

                    <table class="form-table">
                        <tr>
                            <th><label for="toc_bg"><?php esc_html_e( 'Sidebar Background', 'zuno-docs' ); ?></label></th>
                            <td><input type="color" id="toc_bg" name="toc_bg" value="<?php echo esc_attr( $s['toc_bg'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="toc_text"><?php esc_html_e( 'TOC Text Color', 'zuno-docs' ); ?></label></th>
                            <td><input type="color" id="toc_text" name="toc_text" value="<?php echo esc_attr( $s['toc_text'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="toc_hover"><?php esc_html_e( 'TOC Hover Background', 'zuno-docs' ); ?></label></th>
                            <td><input type="color" id="toc_hover" name="toc_hover" value="<?php echo esc_attr( $s['toc_hover'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="toc_active_text"><?php esc_html_e( 'Active Item Text', 'zuno-docs' ); ?></label></th>
                            <td><input type="color" id="toc_active_text" name="toc_active_text" value="<?php echo esc_attr( $s['toc_active_text'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="toc_active_bar"><?php esc_html_e( 'Active Bar Color', 'zuno-docs' ); ?></label></th>
                            <td><input type="color" id="toc_active_bar" name="toc_active_bar" value="<?php echo esc_attr( $s['toc_active_bar'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="enable_active_bg"><?php esc_html_e( 'Active Background Highlight', 'zuno-docs' ); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="enable_active_bg" name="enable_active_bg" value="yes" <?php checked( $s['enable_active_bg'], 'yes' ); ?> />
                                    <?php esc_html_e( 'Enable active heading background', 'zuno-docs' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="toc_active_bg"><?php esc_html_e( 'Active Item Background', 'zuno-docs' ); ?></label></th>
                            <td><input type="color" id="toc_active_bg" name="toc_active_bg" value="<?php echo esc_attr( $s['toc_active_bg'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="enable_heading_bg"><?php esc_html_e( 'Heading Background Blocks', 'zuno-docs' ); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="enable_heading_bg" name="enable_heading_bg" value="yes" <?php checked( $s['enable_heading_bg'], 'yes' ); ?> />
                                    <?php esc_html_e( 'Show background blocks on TOC headings', 'zuno-docs' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="toc_heading_bg"><?php esc_html_e( 'Heading Block Background', 'zuno-docs' ); ?></label></th>
                            <td><input type="color" id="toc_heading_bg" name="toc_heading_bg" value="<?php echo esc_attr( $s['toc_heading_bg'] ); ?>" /></td>
                        </tr>
                    </table>
                </section>

                <!-- HIGHLIGHT -->
                <section id="zuno-docs-tab-highlight" class="zuno-docs-tab-panel">
                    <h2><?php esc_html_e( 'Search Highlight', 'zuno-docs' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Control the color of search result highlights in the documentation content.', 'zuno-docs' ); ?></p>

                    <table class="form-table">
                        <tr>
                            <th><label for="highlight_bg"><?php esc_html_e( 'Highlight Background', 'zuno-docs' ); ?></label></th>
                            <td><input type="color" id="highlight_bg" name="highlight_bg" value="<?php echo esc_attr( $s['highlight_bg'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th><label for="highlight_text"><?php esc_html_e( 'Highlight Text Color', 'zuno-docs' ); ?></label></th>
                            <td><input type="color" id="highlight_text" name="highlight_text" value="<?php echo esc_attr( $s['highlight_text'] ); ?>" /></td>
                        </tr>
                    </table>
                </section>

                <!-- BEHAVIOR -->
                <section id="zuno-docs-tab-behavior" class="zuno-docs-tab-panel">
                    <h2><?php esc_html_e( 'Behavior', 'zuno-docs' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Miscellaneous plugin behavior options.', 'zuno-docs' ); ?></p>

                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Admin Hints', 'zuno-docs' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="show_admin_hint" value="yes" <?php checked( $s['show_admin_hint'], 'yes' ); ?> />
                                    <?php esc_html_e( 'Show hints to admins when a doc section is empty', 'zuno-docs' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </section>
                <!-- DISPLAY CONTROLS -->
                <section id="zuno-docs-tab-display" class="zuno-docs-tab-panel">
                    <h2><?php esc_html_e( 'Display Controls', 'zuno-docs' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Show or hide frontend UI elements without editing code.', 'zuno-docs' ); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Search Bar', 'zuno-docs' ); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="zuno_docs_show_search" value="yes" <?php checked( $s['zuno_docs_show_search'], 'yes' ); ?> />
                                        <?php esc_html_e( 'Show search bar in the documentation sidebar', 'zuno-docs' ); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Breadcrumbs', 'zuno-docs' ); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="zuno_docs_show_breadcrumbs" value="yes" <?php checked( $s['zuno_docs_show_breadcrumbs'], 'yes' ); ?> />
                                        <?php esc_html_e( 'Display breadcrumb navigation above documentation content', 'zuno-docs' ); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Previous Navigation', 'zuno-docs' ); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="zuno_docs_show_previous" value="yes" <?php checked( $s['zuno_docs_show_previous'], 'yes' ); ?> />
                                        <?php esc_html_e( 'Show the Previous button in the page navigation', 'zuno-docs' ); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Next Navigation', 'zuno-docs' ); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="zuno_docs_show_next" value="yes" <?php checked( $s['zuno_docs_show_next'], 'yes' ); ?> />
                                        <?php esc_html_e( 'Show the Next button in the page navigation', 'zuno-docs' ); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Navigation Block', 'zuno-docs' ); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="zuno_docs_show_navigation" value="yes" <?php checked( $s['zuno_docs_show_navigation'], 'yes' ); ?> />
                                        <?php esc_html_e( 'Show the entire Previous / Next navigation section at the bottom of each doc', 'zuno-docs' ); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Table of Contents', 'zuno-docs' ); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="zuno_docs_show_toc" value="yes" <?php checked( $s['zuno_docs_show_toc'], 'yes' ); ?> />
                                        <?php esc_html_e( 'Show the table of contents in the sidebar', 'zuno-docs' ); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Doc Categories', 'zuno-docs' ); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="zuno_docs_show_categories" value="yes" <?php checked( $s['zuno_docs_show_categories'], 'yes' ); ?> />
                                        <?php esc_html_e( 'Show the category section in the documentation sidebar', 'zuno-docs' ); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Related Articles', 'zuno-docs' ); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="zuno_docs_show_related_articles" value="yes" <?php checked( $s['zuno_docs_show_related_articles'], 'yes' ); ?> />
                                        <?php esc_html_e( 'Show the related articles section at the bottom of each doc', 'zuno-docs' ); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </section>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save Settings', 'zuno-docs' ); ?></button>
            </p>
        </form>

        <hr />

        <div class="zuno-docs-settings-info">
            <h2><?php esc_html_e( 'Shortcode Usage', 'zuno-docs' ); ?></h2>
            <pre><code>[zuno_docs product="shipox"]</code></pre>
            <p>
                <?php esc_html_e( 'The', 'zuno-docs' ); ?>
                <code>product</code>
                <?php esc_html_e( 'attribute matches a', 'zuno-docs' ); ?>
                <code>zuno_product</code>
                <?php esc_html_e( 'term slug. Use', 'zuno-docs' ); ?>
                <code>doc_id="123"</code>
                <?php esc_html_e( 'to show a specific doc by ID.', 'zuno-docs' ); ?>
            </p>

            <h2><?php esc_html_e( 'Plugin Info', 'zuno-docs' ); ?></h2>
            <table class="widefat fixed" style="width:auto">
                <tr><td><strong><?php esc_html_e( 'Version', 'zuno-docs' ); ?></strong></td><td><?php echo esc_html( ZUNO_DOCS_VERSION ); ?></td></tr>
                <tr><td><strong><?php esc_html_e( 'Post Type', 'zuno-docs' ); ?></strong></td><td><code>zuno_doc</code></td></tr>
                <tr><td><strong><?php esc_html_e( 'Categories', 'zuno-docs' ); ?></strong></td><td><code>zuno_doc_category</code></td></tr>
                <tr><td><strong><?php esc_html_e( 'Products', 'zuno-docs' ); ?></strong></td><td><code>zuno_product</code></td></tr>
                <tr><td><strong><?php esc_html_e( 'PHP Required', 'zuno-docs' ); ?></strong></td><td>7.4+</td></tr>
            </table>

            <h2><?php esc_html_e( 'Documentation Cache', 'zuno-docs' ); ?></h2>
            <?php
            $graph = zuno_docs_get_graph();
            $cache_time = isset( $graph['built'] ) ? $graph['built'] : 0;
            $total_docs = 0;
            if ( isset( $graph['doc_tree'] ) && is_array( $graph['doc_tree'] ) ) {
                foreach ( $graph['doc_tree'] as $slug => $tree ) {
                    $total_docs += isset( $tree['flat_list'] ) ? count( $tree['flat_list'] ) : 0;
                }
            }
            ?>
            <p><?php esc_html_e( 'The plugin uses a precomputed documentation graph for instant page loads. The cache is rebuilt automatically when docs are saved.', 'zuno-docs' ); ?></p>
            <table class="widefat fixed" style="width:auto">
                <tr><td><strong><?php esc_html_e( 'Cached Docs', 'zuno-docs' ); ?></strong></td><td><?php echo esc_html( $total_docs ); ?></td></tr>
                <tr><td><strong><?php esc_html_e( 'Last Built', 'zuno-docs' ); ?></strong></td><td><?php echo $cache_time ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $cache_time ) ) : '—'; ?></td></tr>
            </table>
            <br>
            <form method="post" action="" style="display:inline">
                <?php wp_nonce_field( 'zuno_docs_rebuild_cache', 'zuno_docs_rebuild_cache_nonce' ); ?>
                <button type="submit" name="zuno_docs_rebuild_cache" class="button"><?php esc_html_e( 'Rebuild Cache Now', 'zuno-docs' ); ?></button>
            </form>
        </div>
    </div>

    <script>
    (function() {
        var tabs = document.querySelectorAll('.zuno-docs-tab-nav a');
        var panels = document.querySelectorAll('.zuno-docs-tab-panel');

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                var target = this.getAttribute('href');

                tabs.forEach(function(t) { t.classList.remove('zuno-docs-tab-active'); });
                this.classList.add('zuno-docs-tab-active');

                panels.forEach(function(p) { p.classList.remove('zuno-docs-tab-active'); });
                document.querySelector(target).classList.add('zuno-docs-tab-active');

                history.pushState(null, '', target);
            });
        });

        if ( window.location.hash ) {
            var hashTab = document.querySelector('.zuno-docs-tab-nav a[href="' + window.location.hash + '"]');
            if ( hashTab ) hashTab.click();
        }

        if (typeof jQuery !== 'undefined' && jQuery.fn.wpColorPicker) {
            jQuery('.zuno-docs-color-picker').wpColorPicker();
        }
    })();
    </script>
    <?php
}
