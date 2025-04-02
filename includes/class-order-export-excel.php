<?php
namespace WooExport;

if (!defined('ABSPATH')) {
    exit;
}

use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use \PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use \PhpOffice\PhpSpreadsheet\IOFactory;

class WC_Order_Export_Excel {
    private static $batch_size = 2000;

    public static function export_excel() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once plugin_dir_path(__DIR__) . 'vendor/autoload.php';

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $file = WP_CONTENT_DIR . '/uploads/orders_export.xlsx';
        $is_first_batch = ($offset === 0);

        if (!is_writable(WP_CONTENT_DIR . '/uploads')) {
            wp_send_json_error(["message" => "Upload directory is not writable."]);
        }

        try {
            if ($is_first_batch) {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Orders');

                $sheet->fromArray([
                    [
                        'Order ID',
                        'Order Status',
                        'Customer Name',
                        'Customer Email',
                        'Customer Phone',
                        'Product Names',
                        'Total',
                        'Order Date',
                    ]
                ], NULL, 'A1');
            } else {
                if (!file_exists($file)) {
                    throw new \Exception("Spreadsheet file not found for subsequent batch processing.");
                }
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
                $sheet = $spreadsheet->getActiveSheet();
            }

            $orders = wc_get_orders([
                'limit'  => self::$batch_size,
                'offset' => $offset,
                'return' => 'ids',
            ]);

            if (empty($orders)) {
                wp_send_json_success([
                    "batch_size" => 0,
                    "file_url" => content_url('/uploads/orders_export.xlsx'),
                ]);
            }

            $lastRow = $sheet->getHighestRow() + 1;

            foreach ($orders as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $order_status = ucfirst($order->get_status());
                    $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                    $customer_email = $order->get_billing_email();
                    $customer_phone = $order->get_billing_phone();
                    $order_total = $order->get_total();
                    $order_date = $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : '';

                    $product_names = [];
                    foreach ($order->get_items() as $item) {
                        $product_names[] = $item->get_name();
                    }
                    $product_names_str = implode(', ', $product_names);

                    $sheet->setCellValue("A$lastRow", $order->get_id());
                    $sheet->setCellValue("B$lastRow", $order_status);
                    $sheet->setCellValue("C$lastRow", $customer_name);
                    $sheet->setCellValue("D$lastRow", $customer_email);

                    // Explicitly set the customer phone as a string
                    $sheet->setCellValueExplicit("E$lastRow", $customer_phone, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $sheet->setCellValue("F$lastRow", $product_names_str);
                    $sheet->setCellValue("G$lastRow", $order_total);
                    $sheet->setCellValue("H$lastRow", $order_date);

                    $lastRow++;
                }
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($file);

            wp_send_json_success([
                "batch_size" => count($orders),
                "file_url" => content_url('/uploads/orders_export.xlsx'),
            ]);
        } catch (\Exception $e) {
            error_log("Excel Export Error: " . $e->getMessage());
            wp_send_json_error([
                "message" => "Error processing Excel export: " . $e->getMessage(),
            ]);
        }
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    WC_Order_Export_Excel::export_excel();
}