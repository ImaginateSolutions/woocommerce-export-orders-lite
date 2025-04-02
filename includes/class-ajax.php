<?php
namespace WooExport;

class Ajax {
	public static function init() {
		add_action('wp_ajax_fetch_paginated_orders', [__CLASS__, 'fetch_orders']);
		add_action('wp_ajax_woocommerce_order_export_total', [__CLASS__, 'total_orders']);
		add_action('wp_ajax_woocommerce_order_export', [WC_Order_Export_CSV::class, 'export_csv']);
		add_action('wp_ajax_woocommerce_order_export_pdf', [WC_Order_Export_PDF::class, 'export_pdf']);
		add_action('wp_ajax_woocommerce_order_export_excel', [WC_Order_Export_Excel::class, 'export_excel']);
	}

	public static function fetch_orders() {
		check_ajax_referer('woo_export_nonce', 'nonce'); // Verify nonce
	
		$page       = isset($_POST['page']) ? absint($_POST['page']) : 1;
		$per_page   = isset($_POST['per_page']) ? absint($_POST['per_page']) : 10;
		$search_term = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
	
		// Base query
		$args = [
			'limit'  => $per_page,
			'offset' => ($page - 1) * $per_page,
			'return' => 'objects',
		];
	
		// Add search logic
		if (!empty($search_term)) {
			$args['search'] = '*' . $search_term . '*';
			$args['search_columns'] = ['billing_first_name', 'billing_last_name', 'billing_email', 'id'];
		}
	
		// Fetch orders
		$orders_query = wc_get_orders($args);
		$total = wc_get_orders(['paginate' => true])->total;
		$total_pages = ceil($total / $per_page);
	
		// Prepare response data
		$data = [];
		foreach ($orders_query as $order) {
			// Gather products into a string
			$products = array_map(function ($item) {
				return $item->get_name();
			}, $order->get_items());
			$product_names_str = implode(', ', $products);
	
			// Add order data to response
			$data[] = [
				'id'            => $order->get_id(),
				'status'        => ucfirst($order->get_status()),
				'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'customer_email'=> $order->get_billing_email(),
				'customer_phone'=> $order->get_billing_phone(),
				'products'      => $product_names_str,
				'total'         => wc_price($order->get_total()),
				'date'          => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : '',
				'view_link'     => admin_url('post.php?post=' . $order->get_id() . '&action=edit'),
			];
		}
	
		wp_send_json_success([
			'orders'      => $data,
			'total_pages' => $total_pages,
		]);
	}

	public static function total_orders() {
		check_ajax_referer('woo_export_nonce', 'nonce');
		$total = wc_get_orders(['paginate' => true])->total;
		wp_send_json_success(['total' => $total]);
	}
}