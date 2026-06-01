<?php
/**
 * Uninstall Export Orders for WooCommerce
 *
 * @package Export_Orders_For_WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete user-specific temp export files
 */
$eowc_upload_dir = wp_upload_dir();
$eowc_base_dir   = $eowc_upload_dir['basedir'];

$eowc_files = glob( $eowc_base_dir . '/eowc-orders-*.{csv,xlsx,json,xml,pdf}', GLOB_BRACE );

if ( ! empty( $eowc_files ) ) {
	foreach ( $eowc_files as $eowc_file ) {
		if ( file_exists( $eowc_file ) ) {
			wp_delete_file( $eowc_file );
		}
	}
}
