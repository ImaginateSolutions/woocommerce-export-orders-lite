<?php
namespace WooExport;

if (!defined('ABSPATH')) {
    exit;
}

class WC_Order_Export_PDF {
    public static function export_pdf() {
        global $wpdb;

        require_once plugin_dir_path(__DIR__) . 'vendor/tecnickcom/tcpdf/tcpdf.php';

        $batch_size = 2000;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $temp_file = WP_CONTENT_DIR . '/uploads/orders_export_temp.json';
        $pdf_file = WP_CONTENT_DIR . '/uploads/orders_export.pdf';

        $orders_data = [];
        if ($offset > 0 && file_exists($temp_file)) {
            $orders_data = json_decode(file_get_contents($temp_file), true);
        }

        $orders = wc_get_orders([
            'limit' => $batch_size,
            'offset' => $offset,
            'return' => 'ids'
        ]);

        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $product_names = [];
                foreach ($order->get_items() as $item) {
                    $product_names[] = $item->get_name();
                }
                $product_names_str = implode(", ", array_map(function($name) {
                    return mb_strimwidth($name, 0, 50, "..."); // Limit to 50 characters
                }, $product_names));
                
                $orders_data[] = [
                    'id'            => $order->get_id(),
                    'status'        => ucfirst($order->get_status()),
                    'customer_name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                    'email'         => $order->get_billing_email(),
                    'phone'         => $order->get_billing_phone(),
                    'products'      => $product_names_str,
                    'total'         => $order->get_total(),
                    'date'          => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : ''
                ];
            }
        }

        file_put_contents($temp_file, json_encode($orders_data));

        if (count($orders) < $batch_size) {
            $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('Your Store Name');
            $pdf->SetTitle('WooCommerce Orders Export');
            $pdf->SetAutoPageBreak(true, 20);
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 6); // Smaller font to fit more data

            // Column Widths Adjusted for A3 Size
            $col_widths = [
                'id' => 20,
                'status' => 30,
                'customer_name' => 30,
                'email' => 40,
                'phone' => 40,
                'products' => 50, // Wider space for products
                'total' => 30,
                'date' => 50
            ];

            // Table Header
            $pdf->SetFillColor(200, 220, 255);
            foreach ($col_widths as $col_name => $width) {
                $pdf->Cell($width, 10, ucfirst(str_replace('_', ' ', $col_name)), 1, 0, 'C', 1);
            }
            $pdf->Ln();

            // Add rows
            foreach ($orders_data as $row) {
                $pdf->Cell($col_widths['id'], 8, $row['id'], 1, 0, 'C');
                $pdf->Cell($col_widths['status'], 8, $row['status'], 1, 0, 'C');
                $pdf->Cell($col_widths['customer_name'], 8, $row['customer_name'], 1, 0, 'C');
                $pdf->Cell($col_widths['email'], 8, $row['email'], 1, 0, 'C');
                $pdf->Cell($col_widths['phone'], 8, $row['phone'], 1, 0, 'C');

                $currentX = $pdf->GetX();
                $currentY = $pdf->GetY();
                $pdf->MultiCell($col_widths['products'], 8, $row['products'], 1, 'L', 0);
                $pdf->SetXY($currentX + $col_widths['products'], $currentY);

                $pdf->Cell($col_widths['total'], 8, $row['total'], 1, 0, 'C');
                $pdf->Cell($col_widths['date'], 8, $row['date'], 1, 1, 'C');
            }

            $pdf->Output($pdf_file, 'F');
            unlink($temp_file);
        }

        wp_send_json_success([
            "batch_size" => count($orders),
            "file_url" => count($orders) < $batch_size ? content_url('/uploads/orders_export.pdf') : null
        ]);
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    WC_Order_Export_PDF::export_pdf();
}
