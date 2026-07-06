<?php
/**
 * Zuno Docs Engine — Centralized Settings Service
 *
 * Single source of truth for all plugin settings.
 * No component should call get_option() directly.
 *
 * @package zuno_docs
 */

defined( 'ABSPATH' ) || exit;

class Zuno_Docs_Settings {

    const OPTION_NAME = 'zuno_docs_settings';

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
     */
    public function get( $key, $default = null ) {
        $this->ensure_loaded();
        return array_key_exists( $key, $this->settings ) ? $this->settings[ $key ] : $default;
    }

    /**
     * Get all settings as an associative array.
     */
    public function all() {
        $this->ensure_loaded();
        return $this->settings;
    }

    /**
     * Reload settings from the database.
     */
    public function reload() {
        $this->loaded = false;
        $this->settings = array();
        $this->ensure_loaded();
    }

    /**
     * Get the canonical default settings array.
     * This is the single source of truth for all default values.
     */
    public static function get_defaults() {
        return array(
            // Appearance
            'zuno_docs_theme_color'         => '#2563EB',
            'zuno_docs_show_reading_progress' => 'no',

            // Typography
            'h1_size'         => 32,
            'h2_size'         => 24,
            'h3_size'         => 19,
            'h4_size'         => 17,
            'h5_size'         => 16,
            'h6_size'         => 15,
            'p_size'          => 14,
            'line_height'     => 1.7,
            'zuno_docs_font_family' => 'inherit',
            'zuno_docs_google_font' => '',

            // Layout
            'toc_depth'       => 6,
            'toc_position'    => 'left',
            'sidebar_width'   => 30,

            // TOC Colors
            'toc_bg'           => '#f8f9fb',
            'toc_text'         => '#475569',
            'toc_hover'        => '#f0f2f5',
            'toc_active_text'  => '#993C1D',
            'enable_active_bg' => 'no',
            'toc_active_bg'    => '#fef0e9',
            'toc_active_bar'   => '#E8500A',
            'enable_heading_bg' => 'no',
            'toc_heading_bg'   => '#f0f2f5',

            // Highlight
            'highlight_bg'     => '#ffcc00',
            'highlight_text'   => '#000000',

            // Behavior
            'zuno_docs_allow_editors'  => 'no',
            'show_admin_hint'          => 'yes',

            // Display toggles
            'zuno_docs_show_search'          => 'yes',
            'zuno_docs_show_breadcrumbs'     => 'yes',
            'zuno_docs_show_previous'        => 'yes',
            'zuno_docs_show_next'            => 'yes',
            'zuno_docs_show_navigation'      => 'yes',
            'zuno_docs_show_toc'             => 'yes',
            'zuno_docs_show_categories'      => 'yes',
            'zuno_docs_show_related_articles'  => 'yes',
            'zuno_docs_show_navigation_rail'   => 'yes',
        );
    }

    /**
     * Get the list of keys that are boolean toggles (yes/no).
     */
    public static function get_toggle_keys() {
        return array(
            'zuno_docs_show_reading_progress',
            'zuno_docs_allow_editors',
            'show_admin_hint',
            'enable_active_bg',
            'enable_heading_bg',
            'zuno_docs_show_search',
            'zuno_docs_show_breadcrumbs',
            'zuno_docs_show_previous',
            'zuno_docs_show_next',
            'zuno_docs_show_navigation',
            'zuno_docs_show_toc',
            'zuno_docs_show_categories',
            'zuno_docs_show_related_articles',
            'zuno_docs_show_navigation_rail',
        );
    }

    /**
     * Get display toggle settings pre-parsed as booleans.
     * Useful for JavaScript localization.
     */
    public static function get_display_settings() {
        $settings = self::get_instance()->all();
        return array(
            'show_breadcrumbs' => 'yes' === $settings['zuno_docs_show_breadcrumbs'],
            'show_previous'    => 'yes' === $settings['zuno_docs_show_previous'],
            'show_next'        => 'yes' === $settings['zuno_docs_show_next'],
            'show_navigation'  => 'yes' === $settings['zuno_docs_show_navigation'],
            'show_related'         => 'yes' === $settings['zuno_docs_show_related_articles'],
            'show_navigation_rail' => 'yes' === $settings['zuno_docs_show_navigation_rail'],
        );
    }

    /**
     * Save settings to the database.
     *
     * Accepts raw input from the settings form.
     * Handles sanitization, validation, and checkbox defaults.
     *
     * @param array $input Raw settings data (typically $_POST).
     * @return void
     */
    public function save( $input ) {
        $defaults = self::get_defaults();
        $toggle_keys = self::get_toggle_keys();
        $sanitized = array();

        // Process each known setting.
        foreach ( $defaults as $key => $default_value ) {
            $value = array_key_exists( $key, $input ) ? $input[ $key ] : null;

            // Checkbox toggles: if missing from input, set to 'no'.
            if ( null === $value && in_array( $key, $toggle_keys, true ) ) {
                $value = 'no';
            }

            // If still null, use default.
            if ( null === $value ) {
                $sanitized[ $key ] = $default_value;
                continue;
            }

            $sanitized[ $key ] = $this->sanitize_field( $key, $value, $default_value );
        }

        update_option( self::OPTION_NAME, $sanitized );
        $this->reload();

        /**
         * Fires after plugin settings have been saved.
         * Use this hook to clear any external caches.
         */
        do_action( 'zuno_docs_settings_saved', $sanitized );
    }

    /**
     * Sanitize a single field value based on its key.
     */
    private function sanitize_field( $key, $value, $default_value ) {
        switch ( true ) {
            // Heading sizes (int 12-72).
            case in_array( $key, array( 'h1_size', 'h2_size', 'h3_size', 'h4_size', 'h5_size', 'h6_size', 'p_size' ), true ):
                $min = 10;
                $max = 72;
                if ( 'h1_size' === $key ) { $min = 14; $max = 72; }
                if ( 'h2_size' === $key ) { $min = 14; $max = 60; }
                if ( 'h3_size' === $key ) { $min = 12; $max = 48; }
                if ( 'h4_size' === $key ) { $min = 12; $max = 40; }
                if ( 'h5_size' === $key ) { $min = 12; $max = 40; }
                if ( 'h6_size' === $key ) { $min = 12; $max = 40; }
                if ( 'p_size'  === $key ) { $min = 10; $max = 32; }
                return max( $min, min( $max, (int) $value ) );

            // Line height (float 1.0-3.0).
            case 'line_height' === $key:
                return round( max( 1.0, min( 3.0, (float) $value ) ), 1 );

            // TOC depth (int 2-6).
            case 'toc_depth' === $key:
                return max( 2, min( 6, (int) $value ) );

            // TOC position (left|right).
            case 'toc_position' === $key:
                return in_array( (string) $value, array( 'left', 'right' ), true ) ? $value : 'left';

            // Sidebar width (int 20-50).
            case 'sidebar_width' === $key:
                return max( 20, min( 50, (int) $value ) );

            // Color fields.
            case in_array( $key, array(
                'zuno_docs_theme_color',
                'toc_bg', 'toc_text', 'toc_hover', 'toc_active_text',
                'toc_active_bg', 'toc_active_bar', 'toc_heading_bg',
                'highlight_bg', 'highlight_text',
            ), true ):
                $sanitized = sanitize_hex_color( (string) $value );
                return $sanitized ?: $default_value;

            // Font family (inherit|google).
            case 'zuno_docs_font_family' === $key:
                return in_array( (string) $value, array( 'inherit', 'google' ), true ) ? $value : 'inherit';

            // Google font name.
            case 'zuno_docs_google_font' === $key:
                return sanitize_text_field( (string) $value );

            // Boolean toggles (yes/no).
            case in_array( $key, self::get_toggle_keys(), true ):
                return ! empty( $value ) && 'yes' === (string) $value ? 'yes' : 'no';

            // Fallback: sanitize as text field.
            default:
                return sanitize_text_field( (string) $value );
        }
    }

    /**
     * Ensure settings are loaded from the database.
     */
    private function ensure_loaded() {
        if ( $this->loaded ) {
            return;
        }

        $saved = get_option( self::OPTION_NAME, array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        $this->settings = array_merge( self::get_defaults(), $saved );
        $this->loaded = true;
    }

    /**
     * Register the setting with WordPress Settings API.
     * Call this on admin_init.
     */
    public static function register() {
        $group = 'zuno_docs_settings_group';
        register_setting( $group, self::OPTION_NAME, array(
            'sanitize_callback' => array( self::class, 'sanitize_callback' ),
            'default'           => self::get_defaults(),
            'show_in_rest'      => false,
        ) );
    }

    /**
     * Settings API sanitize callback.
     * Used when WordPress auto-saves via settings_fields().
     */
    public static function sanitize_callback( $input ) {
        if ( ! is_array( $input ) ) {
            return self::get_defaults();
        }
        $instance = self::get_instance();
        $defaults = self::get_defaults();
        $toggle_keys = self::get_toggle_keys();
        $sanitized = array();

        foreach ( $defaults as $key => $default_value ) {
            $value = array_key_exists( $key, $input ) ? $input[ $key ] : null;

            if ( null === $value && in_array( $key, $toggle_keys, true ) ) {
                $value = 'no';
            }

            if ( null === $value ) {
                $sanitized[ $key ] = $default_value;
                continue;
            }

            $sanitized[ $key ] = $instance->sanitize_field( $key, $value, $default_value );
        }

        return $sanitized;
    }

    /**
     * Get a single default value by key.
     */
    public static function get_default( $key ) {
        $defaults = self::get_defaults();
        return isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
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
