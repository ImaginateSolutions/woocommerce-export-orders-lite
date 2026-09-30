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
		require_once EOWC_PLUGIN_PATH . 'includes/class-eowc-abilities.php';
		require_once EOWC_PLUGIN_PATH . 'includes/class-eowc-mcp-server-factory.php';
		require_once EOWC_PLUGIN_PATH . 'includes/class-eowc-mcp-server.php';
		require_once EOWC_PLUGIN_PATH . 'includes/class-eowc-mcp-cli.php';
		require_once EOWC_PLUGIN_PATH . 'includes/class-eowc-mcp-rest.php';
		require_once EOWC_PLUGIN_PATH . 'includes/class-eowc-oauth.php';
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks(): void {
		EOWC_Abilities::init();
		EOWC_MCP_CLI::register();
		EOWC_MCP_REST::register();
		EOWC_OAuth::register();

		if ( is_admin() ) {
			new EOWC_Admin();
		}
	}
}
