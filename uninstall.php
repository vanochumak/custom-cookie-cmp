<?php
/**
 * Uninstall Custom Cookie CMP
 *
 * Fired when the plugin is uninstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'custom_cookie_cmp_options' );
