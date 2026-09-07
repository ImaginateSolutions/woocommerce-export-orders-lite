<?php
/**
 * Plugin Name: Export Orders for WooCommerce
 * Plugin URI: https://imaginate-solutions.com/
 * Description: This plugin lets store owners export orders.
 * Version: 2.0.0
 * Author: Imaginate Solutions
 * Author URI: https://imaginate-solutions.com/
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0.0
 * WC tested up to: 10.7.0
 * License: GPL-2.0+
 * textdomain: woocommerce-export-orders
 *
 * @package Export_Orders_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EOWC_VERSION', '2.0.0' );
define( 'EOWC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'EOWC_PLUGIN_FILE', __FILE__ );
define( 'EOWC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'EOWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);


register_activation_hook( __FILE__, 'eowc_free_activate' );

/**
 * Register activation callback.
 */
function eowc_free_activate() {

	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$pro_plugin = 'woocommerce-export-orders-pro/woocommerce-export-orders-pro.php';

	if ( is_plugin_active( $pro_plugin ) ) {
		deactivate_plugins( __FILE__ );

		wp_die(
			esc_html__( 'WooCommerce Export Orders Pro is already active. Please deactivate the Pro version before activating the free version.', 'woocommerce-export-orders' ),
			esc_html__( 'Plugin activation failed', 'woocommerce-export-orders' ),
			array( 'back_link' => true )
		);
	}
}

/**
 * Load main plugin.
 * This file is responsible for initializing the plugin and loading the main plugin class.
 */
require_once EOWC_PLUGIN_PATH . 'includes/class-eowc-plugin.php';

/**
 * Initialize the plugin.
 */
function eowc_init() {
	$plugin = new \EOWC\Includes\EOWC_Plugin();
	$plugin->run();
}

eowc_init();
