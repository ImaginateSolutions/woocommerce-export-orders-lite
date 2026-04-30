<?php
/**
 * Plugin Class
 *
 * @package Export_Orders_For_WooCommerce
 */

namespace EOWC\Includes;

use EOWC\Includes\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class plugin
 */
class EOWC_Plugin {

	/**
	 * Run the plugin.
	 */
	public function run(): void {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load dependencies.
	 */
	private function load_dependencies(): void {
		require_once EOWC_PLUGIN_PATH . 'includes/class-eowc-admin.php';
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks(): void {
		if ( is_admin() ) {
			new EOWC_Admin();
		}
	}
}
