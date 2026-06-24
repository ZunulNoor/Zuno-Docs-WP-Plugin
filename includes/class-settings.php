<?php
/**
 * Zuno Docs Engine — Centralized Settings Service
 *
 * Single source of truth for all plugin settings.
 * Backward-compatible with the existing zuno_docs_get_settings() function.
 *
 * @package zuno_docs
 */

defined( 'ABSPATH' ) || exit;

class Zuno_Docs_Settings {

    private static $instance = null;
    private $settings = array();
    private $loaded = false;

    private function __construct() {}

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get a single setting value by key.
     *
     * @param string $key     The setting key.
     * @param mixed  $default Fallback value if key is not found.
     * @return mixed
     */
    public function get( $key, $default = null ) {
        $this->ensure_loaded();
        return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
    }

    /**
     * Get all settings as an associative array.
     *
     * @return array
     */
    public function all() {
        $this->ensure_loaded();
        return $this->settings;
    }

    /**
     * Reload settings from the database (after save, for example).
     *
     * @return void
     */
    public function reload() {
        $this->loaded = false;
        $this->settings = array();
        $this->ensure_loaded();
    }

    /**
     * Get the default settings array.
     *
     * @return array
     */
    public static function get_defaults() {
        return array(
            'zuno_docs_theme_color'         => '#2563EB',
            'h1_size'                       => 28,
            'h2_size'                       => 26,
            'h3_size'                       => 24,
            'h4_size'                       => 22,
            'h5_size'                       => 20,
            'h6_size'                       => 18,
            'p_size'                        => 16,
            'line_height'                   => 1.7,
            'toc_depth'                     => 6,
            'toc_position'                  => 'left',
            'sidebar_width'                 => 30,
            'toc_bg'                        => '#ffffff',
            'toc_text'                      => '#6B7280',
            'toc_hover'                     => '#EEF2FF',
            'toc_active_text'               => '#111827',
            'enable_active_bg'              => 'no',
            'toc_active_bg'                 => '#ffffff',
            'toc_active_bar'                => '#2563EB',
            'enable_heading_bg'             => 'no',
            'toc_heading_bg'                => '#f0f2f5',
            'highlight_bg'                  => '#FEF3C7',
            'highlight_text'                => '#111827',
            'show_admin_hint'               => 'yes',
            'zuno_docs_show_search'         => 'yes',
            'zuno_docs_show_breadcrumbs'    => 'yes',
            'zuno_docs_show_previous'       => 'yes',
            'zuno_docs_show_next'           => 'yes',
            'zuno_docs_show_navigation'     => 'yes',
            'zuno_docs_show_toc'            => 'yes',
            'zuno_docs_show_categories'     => 'yes',
            'zuno_docs_show_related_articles' => 'yes',
            'zuno_docs_show_reading_progress' => 'no',
        );
    }

    /**
     * Save settings to the database.
     *
     * @param array $new_settings The settings to save.
     * @return void
     */
    public function save( $new_settings ) {
        $defaults = self::get_defaults();
        $sanitized = array();

        foreach ( $defaults as $key => $default_value ) {
            if ( isset( $new_settings[ $key ] ) ) {
                $sanitized[ $key ] = $new_settings[ $key ];
            } else {
                $sanitized[ $key ] = $default_value;
            }
        }

        update_option( 'zuno_docs_settings', $sanitized );
        $this->reload();
    }

    private function ensure_loaded() {
        if ( $this->loaded ) {
            return;
        }

        $saved = get_option( 'zuno_docs_settings', array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        $this->settings = array_merge( self::get_defaults(), $saved );
        $this->loaded = true;
    }
}

/**
 * Backward-compatible wrapper.
 *
 * @return array
 */
function zuno_docs_get_settings() {
    return Zuno_Docs_Settings::get_instance()->all();
}
