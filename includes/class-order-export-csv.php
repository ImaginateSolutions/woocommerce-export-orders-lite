<?php

namespace WooExport;

if (!defined('ABSPATH')) {
    exit;
}

class WC_Order_Export_CSV {
    public static function export_csv() {
        global $wpdb;
        $batch_size = 2000;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $file = WP_CONTENT_DIR . '/uploads/orders_export.csv';

        // Clear file if starting from scratch
        if ($offset == 0 && file_exists($file)) {
            unlink($file);
        }

        $orders = wc_get_container()->has(\Automattic\WooCommerce\Checkout\Orders\DataStore::class) ?
            wc_get_orders(['limit' => $batch_size, 'offset' => $offset, 'return' => 'ids']) :
            $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' ORDER BY ID ASC LIMIT %d OFFSET %d",
                    $batch_size,
                    $offset
                )
            );

        if (empty($orders)) {
            wp_send_json_success(["batch_size" => 0, "file_url" => content_url('/uploads/orders_export.csv')]);
        }

        $fp = fopen($file, 'a');
        stream_filter_append($fp, 'convert.iconv.UTF-8/UTF-8'); // Ensure UTF-8 encoding

        // Write header row if it's the first batch
        if ($offset == 0) {
            fputcsv($fp, ["Order ID", "Order Status", "Customer Name", "Customer Email", "Customer Phone", "Product Name", "Amount", "Order Date"]);
        }

        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order_id       = $order->get_id();
                $order_status   = ucfirst($order->get_status());
                $customer_name  = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                $customer_email = $order->get_billing_email();
                $customer_phone = '"' . $order->get_billing_phone() . '"'; // Wrap phone number in double quotes
                $order_total    = $order->get_total();
                $order_date     = $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '';

                // Get all product names
                $product_names = [];
                foreach ($order->get_items() as $item) {
                    $product_names[] = $item->get_name();
                }
                $product_names_str = implode(', ', $product_names);

                // Write to CSV
                fputcsv($fp, [
                    $order_id,
                    $order_status,
                    $customer_name,
                    $customer_email,
                    $customer_phone,
                    $product_names_str,
                    $order_total,
                    $order_date,
                ]);
            }
        }

        fclose($fp);

        wp_send_json_success(["batch_size" => count($orders), "file_url" => content_url('/uploads/orders_export.csv')]);
    }
}
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    WC_Order_Export_CSV::export_csv();
}
