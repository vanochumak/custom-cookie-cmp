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

// Remove per-user dismissal meta (both old and renamed key).
delete_metadata( 'user', 0, 'ccc_donation_dismissed', '', true );
delete_metadata( 'user', 0, 'customcookiecmp_donation_dismissed', '', true );
