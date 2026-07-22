<?php

defined( 'ABSPATH' ) || exit;

function zuno_docs_get_all_capabilities() {
    return array(
        'zuno_docs_read'              => __( 'Read Documentation', 'zuno-docs-engine' ),
        'zuno_docs_create'            => __( 'Create Documentation', 'zuno-docs-engine' ),
        'zuno_docs_edit'              => __( 'Edit Documentation', 'zuno-docs-engine' ),
        'zuno_docs_publish'           => __( 'Publish Documentation', 'zuno-docs-engine' ),
        'zuno_docs_delete'            => __( 'Delete Documentation', 'zuno-docs-engine' ),
        'zuno_docs_manage_categories' => __( 'Manage Categories', 'zuno-docs-engine' ),
        'zuno_docs_manage_settings'   => __( 'Manage Settings', 'zuno-docs-engine' ),
        'zuno_docs_import'            => __( 'Import Documentation', 'zuno-docs-engine' ),
        'zuno_docs_export'            => __( 'Export Documentation', 'zuno-docs-engine' ),
        'zuno_docs_manage_plugin'     => __( 'Manage Plugin', 'zuno-docs-engine' ),
    );
}

function zuno_docs_get_capability_keys() {
    return array_keys( zuno_docs_get_all_capabilities() );
}

function zuno_docs_get_editor_capabilities() {
    return array(
        'zuno_docs_read',
        'zuno_docs_create',
        'zuno_docs_edit',
        'zuno_docs_publish',
    );
}

function zuno_docs_add_caps_to_role( $role_name, $caps = null ) {
    $role = get_role( $role_name );
    if ( ! $role ) {
        return;
    }
    if ( null === $caps ) {
        $caps = zuno_docs_get_capability_keys();
    }
    foreach ( $caps as $cap ) {
        $role->add_cap( $cap );
    }
}

function zuno_docs_remove_caps_from_role( $role_name, $caps = null ) {
    $role = get_role( $role_name );
    if ( ! $role ) {
        return;
    }
    if ( null === $caps ) {
        $caps = zuno_docs_get_capability_keys();
    }
    foreach ( $caps as $cap ) {
        $role->remove_cap( $cap );
    }
}

function zuno_docs_create_editor_role() {
    $role = get_role( 'zuno_docs_editor' );
    if ( $role ) {
        zuno_docs_remove_caps_from_role( 'zuno_docs_editor', zuno_docs_get_capability_keys() );
    } else {
        $role = add_role(
            'zuno_docs_editor',
            __( 'Zuno Docs Editor', 'zuno-docs-engine' ),
            array(
                'read'         => true,
                'upload_files' => true,
            )
        );
    }

    if ( ! $role ) {
        return;
    }

    foreach ( zuno_docs_get_editor_capabilities() as $cap ) {
        $role->add_cap( $cap );
    }
}

function zuno_docs_register_capabilities() {
    zuno_docs_add_caps_to_role( 'administrator' );
    zuno_docs_create_editor_role();
    zuno_docs_sync_editor_role_caps();
}

function zuno_docs_sync_editor_role_caps() {
    $settings      = Zuno_Docs_Settings::get_instance();
    $allow_editors = 'yes' === $settings->get( 'zuno_docs_allow_editors', 'no' );

    if ( $allow_editors ) {
        zuno_docs_add_caps_to_role( 'editor', zuno_docs_get_editor_capabilities() );
    } else {
        zuno_docs_remove_caps_from_role( 'editor', zuno_docs_get_editor_capabilities() );
    }
}

add_action( 'zuno_docs_settings_saved', 'zuno_docs_sync_editor_role_caps' );
