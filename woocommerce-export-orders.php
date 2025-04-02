<?php
/**
 * Plugin Name: Export Orders for WooCommerce
 * Plugin URI: https://imaginate-solutions.com/
 * Description: This plugin lets store owners export orders.
 * Version: 1.2.0
 * Author: Imaginate Solutions
 * Author URI: https://imaginate-solutions.com/
 * Requires PHP: 8.0
 * WC requires at least: 3.0.0
 * WC tested up to: 8.0.0
 */

if (!defined('ABSPATH')) exit;

// Activation check for WooCommerce
register_activation_hook(__FILE__, function () {
	if (!class_exists('WooCommerce')) {
		deactivate_plugins(plugin_basename(__FILE__));
		wp_die('<p><strong>WooCommerce Export Orders</strong> requires WooCommerce. <a href="' . esc_url(admin_url('plugins.php')) . '">Go back</a></p>', 'Plugin Activation Error');
	}
});

// Declare HPOS compatibility
add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

// Autoloader
foreach (glob(plugin_dir_path(__FILE__) . 'includes/*.php') as $file) {
	require_once $file;
}

// Initialize Plugin
add_action('plugins_loaded', ['WooExport\Admin', 'init']);