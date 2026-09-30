<?php
/**
 * Uninstall Export Orders for WooCommerce
 *
 * @package Export_Orders_For_WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove OAuth grants, cached public client metadata and per-user revocation state.
wp_clear_scheduled_hook( 'eowc_oauth_cleanup' );
delete_transient( 'eowc_oauth_diagnostic_until' );
delete_transient( 'eowc_oauth_diagnostic_events' );
global $wpdb;
do {
	// phpcs:ignore
	$eowc_oauth_options = $wpdb->get_col( 
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s LIMIT 500",
			$wpdb->esc_like( 'eowc_oauth_record_' ) . '%',
			$wpdb->esc_like( '_transient_eowc_oauth_client_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_eowc_oauth_client_' ) . '%'
		)
	);
	$eowc_oauth_deleted = 0;
	foreach ( $eowc_oauth_options as $eowc_oauth_option ) {
		$eowc_oauth_deleted += (int) delete_option( $eowc_oauth_option );
	}
	// phpcs:ignore
} while ( count( $eowc_oauth_options ) === 500 && $eowc_oauth_deleted > 0 );
delete_metadata( 'user', 0, 'eowc_oauth_epoch', '', true );

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
