<?php
namespace WooExport;

class Admin {
	public static function init() {
		add_action('admin_menu', [__CLASS__, 'add_menu']);
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
		Ajax::init(); // Register AJAX functions
	}

	public static function add_menu() {
		add_menu_page(
			'Export Orders',
			'Export Orders',
			'manage_options',
			'export_orders',
			[__CLASS__, 'render_page'],
			'dashicons-media-spreadsheet',
			56
		);
	}

	public static function render_page() {
		require_once plugin_dir_path(__DIR__) . 'templates/admin-export-page.php';
	}

	public static function enqueue_assets($hook) {
		if ($hook !== 'toplevel_page_export_orders') return;

		wp_enqueue_style('woo-export-admin', plugins_url('../assets/css/admin-style.css', __FILE__));
		wp_enqueue_script('woo-export-admin', plugins_url('../assets/js/admin-script.js', __FILE__), ['jquery'], null, true);

		wp_localize_script('woo-export-admin', 'WooExportData', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('woo_export_nonce')
		]);
	}
}