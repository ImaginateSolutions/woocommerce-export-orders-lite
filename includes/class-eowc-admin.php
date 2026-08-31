<?php
/**
 * Admin Class
 *
 * @package Export_Orders_For_WooCommerce
 */

namespace EOWC\Includes;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Class
 */
class EOWC_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'plugin_action_links_' . EOWC_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ), 20, 1 );
		add_filter( 'admin_enqueue_scripts', array( $this, 'export_enqueue_scripts' ) );
		add_filter( 'admin_menu', array( $this, 'order_export_page' ) );
		add_action( 'admin_footer', array( $this, 'render_export_modal' ) );
		// add_action( 'woocommerce_order_list_table_extra_tablenav', array( $this, 'add_export_button' ), 20, 1 );
		add_action( 'admin_head', array( $this, 'add_order_page_export_button' ) );
		add_action( 'wp_ajax_eowc_export_orders', array( $this, 'handle_export_orders' ) );
		add_action( 'admin_post_eowc_download_file', array( $this, 'download_file' ) );
		add_action( 'admin_init', array( $this, 'save_installation_date' ) );
		add_action( 'admin_notices', array( $this, 'show_review_notice' ) );
		add_action( 'wp_ajax_eowc_dismiss_review_notice', array( $this, 'eowc_dismiss_review_notice' ) );	

	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @param array $links Plugin Action links.
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=wc-orders' ) ) . '" aria-label="' . esc_attr__( 'Export Orders', 'woocommerce-export-orders' ) . '">' . esc_html__( 'Export Orders', 'woocommerce-export-orders' ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}

	/**
	 * Enqueue scripts and styles for the admin page.
	 */
	public function export_enqueue_scripts() {

		$screen = get_current_screen();

		if ( isset( $screen->id ) && ( 'woocommerce_page_wc-orders' === $screen->id || 'toplevel_page_eowc-export-orders' === $screen->id ) ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_style( 'woocommerce_admin_styles' );

			wp_enqueue_style(
				'eowc-admin-css',
				EOWC_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				EOWC_VERSION
			);
			wp_enqueue_script( 'eowc-export-orders', EOWC_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'selectWoo', 'jquery-ui-sortable' ), EOWC_VERSION, true );
			wp_localize_script(
				'eowc-export-orders',
				'eowc_export_orders_params',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'eowc-export-orders' ),
				)
			);
		}
	}

	/**
	 * Render the export modal.
	 */
	public function render_export_modal() {
		$screen = get_current_screen();
		if ( isset( $screen->id ) && ( 'woocommerce_page_wc-orders' === $screen->id || 'toplevel_page_eowc-export-orders' === $screen->id ) ) {
			?>
			<div id="eowc-export-modal" style="display:none;">
				<div class="eowc-modal-overlay"></div>
				<div class="eowc-modal-content">
					<span class="eowc-close">&times;</span>
					<?php include EOWC_PLUGIN_PATH . 'template-parts/export-orders.php'; ?>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Add export button to the orders list table.
	 *
	 * @param string $which The table to add the button to.
	 */
	public function add_export_button( $which ) {
		if ( 'shop_order' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<button type="button" class="button button-primary eowc-export-btn">
				<?php esc_html_e( 'Export Orders', 'woocommerce-export-orders' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Add export button to the orders page.
	 */
	public function add_order_page_export_button() {
		global $pagenow;

		$is_orders_page = (
			'admin.php' === $pagenow
			&& isset( $_GET['page'] )
			&& 'wc-orders' === $_GET['page']
		);

		if ( ! $is_orders_page ) {
			return;
		}
		?>
		<script>
			jQuery(function($) {
				const button = $('<button>', {
					text: 'Export Orders',
					class: 'button button-primary eowc-export-btn',
					type: 'button'
				})

				$('.wrap .page-title-action').first().after(button);
			});
		</script>
		<?php
	}

	/**
	 * Add export page to the admin menu.
	 */
	public function order_export_page() {
		add_menu_page(
			__( 'Export Orders', 'woocommerce-export-orders' ),
			__( 'Export Orders', 'woocommerce-export-orders' ),
			'manage_options',
			'eowc-export-orders',
			array( $this, 'render_export_page' ),
			'dashicons-download',
			25
		);
	}

	/**
	 * Render the export page.
	 */
	public function render_export_page() {
		?>
		<div class="wrap eowc-export-orders">
			<?php include EOWC_PLUGIN_PATH . 'template-parts/export-orders.php'; ?>
		</div>
		<?php
	}

	/**
	 * Handle export orders AJAX request.
	 */
	public function handle_export_orders() {

		check_ajax_referer( 'eowc-export-orders', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$offset = intval( $_POST['offset'] ?? 0 );
		$limit  = 100;

		// Use a unique session key sent from JS to keep the file name static across batches.
		$export_id     = isset( $_POST['export_id'] ) ? sanitize_file_name( wp_unslash( $_POST['export_id'] ) ) : date( 'YmdHis' );
		$raw_status    = isset( $_POST['eowc_status'] ) ? wp_unslash( $_POST['eowc_status'] ) : ''; // phpcs:ignore
		$status        = is_array( $raw_status ) ? array_map( 'sanitize_text_field', $raw_status ) : ( $raw_status ? array( sanitize_text_field( $raw_status ) ) : array() );
		$date_from     = isset( $_POST['eowc_date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['eowc_date_from'] ) ) : '';
		$date_to       = isset( $_POST['eowc_date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['eowc_date_to'] ) ) : '';
		$export_format = isset( $_POST['eowc_export_format'] ) ? sanitize_text_field( wp_unslash( $_POST['eowc_export_format'] ) ) : 'csv';

		// === 1. Get selected columns.
		$selected_columns = isset( $_POST['eowc_columns'] ) && is_array( $_POST['eowc_columns'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['eowc_columns'] ) ) : array();

		if ( empty( $selected_columns ) ) {
			wp_send_json_error( array( 'message' => 'No columns specified.' ) );
		}

		// === 2. Map column keys to output labels and value callbacks.
		$fields_map = array(
			'order_id'            => array(
				'Order ID',
				function ( $order ) {
										return $order->get_id(); },
			),
			'order_status'        => array(
				'Status',
				function ( $order ) {
										return wc_get_order_status_name( $order->get_status() ); },
			),
			'order_date'          => array(
				'Order Date',
				function ( $order ) {
										return $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : ''; },
			),
			'order_total'         => array(
				'Order Total',
				function ( $order ) {
										return wc_format_decimal( $order->get_total(), 2 ); },
			),
			'order_subtotal'      => array(
				'Subtotal',
				function ( $order ) {
										return wc_format_decimal( $order->get_subtotal(), 2 ); },
			),
			'order_discount'      => array(
				'Discount',
				function ( $order ) {
										return wc_format_decimal( $order->get_discount_total(), 2 ); },
			),
			'order_tax'           => array(
				'Tax',
				function ( $order ) {
										return wc_format_decimal( $order->get_total_tax(), 2 ); },
			),
			'shipping_total'      => array(
				'Shipping Total',
				function ( $order ) {
										return wc_format_decimal( $order->get_shipping_total(), 2 ); },
			),
			'payment_method'      => array(
				'Payment Method',
				function ( $order ) {
										return $order->get_payment_method_title(); },
			),
			'transaction_id'      => array(
				'Transaction ID',
				function ( $order ) {
										return $order->get_transaction_id(); },
			),
			'customer_note'       => array(
				'Customer Note',
				function ( $order ) {
										return $order->get_customer_note(); },
			),
			'coupon_codes'        => array(
				'Coupon Codes',
				function ( $order ) {
										return implode( ', ', $order->get_coupon_codes() ); },
			),
			'customer_id'         => array(
				'Customer ID',
				function ( $order ) {
										return $order->get_customer_id(); },
			),
			'customer_email'      => array(
				'Email',
				function ( $order ) {
										return $order->get_billing_email(); },
			),
			'customer_phone'      => array(
				'Phone',
				function ( $order ) {
										return $order->get_billing_phone(); },
			),
			'billing_first_name'  => array(
				'First Name (Billing)',
				function ( $order ) {
										return $order->get_billing_first_name(); },
			),
			'billing_last_name'   => array(
				'Last Name (Billing)',
				function ( $order ) {
										return $order->get_billing_last_name(); },
			),
			'billing_company'     => array(
				'Company (Billing)',
				function ( $order ) {
										return $order->get_billing_company(); },
			),
			'billing_address_1'   => array(
				'Address 1 (Billing)',
				function ( $order ) {
										return $order->get_billing_address_1(); },
			),
			'billing_address_2'   => array(
				'Address 2 (Billing)',
				function ( $order ) {
										return $order->get_billing_address_2(); },
			),
			'billing_city'        => array(
				'City (Billing)',
				function ( $order ) {
										return $order->get_billing_city(); },
			),
			'billing_state'       => array(
				'State (Billing)',
				function ( $order ) {
										return $order->get_billing_state(); },
			),
			'billing_postcode'    => array(
				'Postcode (Billing)',
				function ( $order ) {
										return $order->get_billing_postcode(); },
			),
			'billing_country'     => array(
				'Country (Billing)',
				function ( $order ) {
										return $order->get_billing_country(); },
			),
			'shipping_first_name' => array(
				'First Name (Shipping)',
				function ( $order ) {
										return $order->get_shipping_first_name(); },
			),
			'shipping_last_name'  => array(
				'Last Name (Shipping)',
				function ( $order ) {
										return $order->get_shipping_last_name(); },
			),
			'shipping_company'    => array(
				'Company (Shipping)',
				function ( $order ) {
										return $order->get_shipping_company(); },
			),
			'shipping_address_1'  => array(
				'Address 1 (Shipping)',
				function ( $order ) {
										return $order->get_shipping_address_1(); },
			),
			'shipping_address_2'  => array(
				'Address 2 (Shipping)',
				function ( $order ) {
										return $order->get_shipping_address_2(); },
			),
			'shipping_city'       => array(
				'City (Shipping)',
				function ( $order ) {
										return $order->get_shipping_city(); },
			),
			'shipping_state'      => array(
				'State (Shipping)',
				function ( $order ) {
										return $order->get_shipping_state(); },
			),
			'shipping_postcode'   => array(
				'Postcode (Shipping)',
				function ( $order ) {
										return $order->get_shipping_postcode(); },
			),
			'shipping_country'    => array(
				'Country (Shipping)',
				function ( $order ) {
										return $order->get_shipping_country(); },
			),
			'shipping_method'     => array(
				'Shipping Method',
				function ( $order ) {
					$methods = array();
					foreach ( $order->get_shipping_methods() as $item ) {
						$methods[] = $item->get_name();
					}
					return implode( ', ', $methods );
				},
			),
			'product_names'       => array(
				'Product Names',
				function ( $order ) {
					$names = array();
					foreach ( $order->get_items( 'line_item' ) as $item ) {
						$names[] = $item->get_name();
					}
					return implode( ', ', $names );
				},
			),
			'product_skus'        => array(
				'Product SKUs',
				function ( $order ) {
					$skus = array();
					foreach ( $order->get_items( 'line_item' ) as $item ) {
						$product = $item->get_product();
						$skus[]  = $product ? $product->get_sku() : '';
					}
					return implode( ', ', $skus );
				},
			),
			'product_quantities'  => array(
				'Quantities',
				function ( $order ) {
					$qty = array();
					foreach ( $order->get_items( 'line_item' ) as $item ) {
						$qty[] = $item->get_quantity();
					}
					return implode( ', ', $qty );
				},
			),
			'product_totals'      => array(
				'Line Totals',
				function ( $order ) {
					$totals = array();
					foreach ( $order->get_items( 'line_item' ) as $item ) {
						$totals[] = wc_format_decimal( $item->get_total(), 2 );
					}
					return implode( ', ', $totals );
				},
			),
		);

		// === 3. Validate requested columns against allowed.
		$column_callbacks = array();
		$headers          = array();
		foreach ( $selected_columns as $col ) {
			if ( isset( $fields_map[ $col ] ) ) {
				$headers[]          = $fields_map[ $col ][0];
				$column_callbacks[] = $fields_map[ $col ][1];
			}
		}
		if ( empty( $headers ) ) {
			wp_send_json_error( array( 'message' => 'Invalid columns specified.' ) );
		}

		// === 4. Build order query.
		$args = array(
			'limit'        => $limit,
			'offset'       => $offset,
			'status'       => $status,
			'date_created' => ( $date_from && $date_to ) ? $date_from . '...' . $date_to : '',
			'paginate'     => true,
			'return'       => 'ids',
		);

		$result    = wc_get_orders( $args );
		$order_ids = $result->orders;
		$total     = $result->total;

		if ( empty( $total ) ) {
			wp_send_json_error( array( 'message' => 'Nothing to export. Please, adjust your filters.' ) );
		}

		$upload_dir = wp_upload_dir();
		$user_id    = get_current_user_id();

		// Stable filename based on unique batch ID.
		$filename   = "eowc-orders-{$user_id}-{$export_id}";
		$extensions = array(
			'csv'  => 'csv',
			'json' => 'json',
			'xlsx' => 'xlsx',
			'pdf'  => 'pdf',
			'xml'  => 'xml',
		);

		$file_ext  = isset( $extensions[ $export_format ] ) ? $extensions[ $export_format ] : 'txt';
		$file_url  = $upload_dir['baseurl'] . "/{$filename}.{$file_ext}";
		$file_path = $upload_dir['basedir'] . "/{$filename}.{$file_ext}";
		$is_new    = ( 0 === $offset ) || ! file_exists( $file_path );

		// ---- EXPORT FORMAT HANDLING ----

		if ( 'csv' === $export_format ) {
			// phpcs:ignore
			$file = fopen( $file_path, $is_new ? 'w' : 'a' );
			if ( $is_new ) {
				fwrite( $file, "\xEF\xBB\xBF" ); // UTF-8 BOM
				fputcsv( $file, $headers );
			}

			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( $order_id );
				$row   = array();
				foreach ( $column_callbacks as $cb ) {
					$row[] = is_callable( $cb ) ? call_user_func( $cb, $order ) : '';
				}
				fputcsv( $file, $row );
			}
			// phpcs:ignore
			fclose( $file );

		} elseif ( 'json' === $export_format ) {
			$new_rows = array();
			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( $order_id );
				$row   = array();
				foreach ( $column_callbacks as $i => $cb ) {
					$row[ $headers[ $i ] ] = is_callable( $cb ) ? call_user_func( $cb, $order ) : '';
				}
				$new_rows[] = $row;
			}

			$existing = array();
			if ( ! $is_new && file_exists( $file_path ) ) {
				$raw_json = file_get_contents( $file_path );
				$existing = $raw_json ? json_decode( $raw_json, true ) : array();
			}
			$all_rows = array_merge( (array) $existing, $new_rows );
			file_put_contents( $file_path, wp_json_encode( $all_rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

		} elseif ( 'xml' === $export_format ) {
			$xml_root_name = 'orders';

			if ( ! $is_new && file_exists( $file_path ) ) {
				$xml = \simplexml_load_file( $file_path );
			} else {
				$xml = new \SimpleXMLElement( "<?xml version=\"1.0\" encoding=\"UTF-8\"?><{$xml_root_name}></{$xml_root_name}>" );
			}

			foreach ( $order_ids as $order_id ) {
				$order     = wc_get_order( $order_id );
				$order_xml = $xml->addChild( 'order' );
				foreach ( $column_callbacks as $i => $cb ) {
					$slug = preg_replace( '/[^a-z0-9_]/i', '_', strtolower( $headers[ $i ] ) );
					$order_xml->addChild( $slug, htmlspecialchars( is_callable( $cb ) ? call_user_func( $cb, $order ) : '', ENT_QUOTES | ENT_XML1, 'UTF-8' ) );
				}
			}
			file_put_contents( $file_path, $xml->asXML() );

		} elseif ( 'xlsx' === $export_format ) {
			if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
				require_once EOWC_PLUGIN_PATH . '/vendor/autoload.php';
			}

			if ( $is_new || ! file_exists( $file_path ) ) {
				$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
				$sheet       = $spreadsheet->getActiveSheet();
				foreach ( $headers as $col_idx => $title ) {
					$sheet->setCellValueByColumnAndRow( $col_idx + 1, 1, $title );
					$column_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col_idx + 1 );
					$width         = max( strlen( $title ) + 5, 15 );
					$sheet->getColumnDimension( $column_letter )->setWidth( $width );
				}
				$row_start = 2;
			} else {
				$reader      = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
				$spreadsheet = $reader->load( $file_path );
				$sheet       = $spreadsheet->getActiveSheet();
				$row_start   = $sheet->getHighestRow() + 1;
			}

			foreach ( $order_ids as $i => $order_id ) {
				$order = wc_get_order( $order_id );
				foreach ( $column_callbacks as $col_idx => $cb ) {
					$value = is_callable( $cb ) ? call_user_func( $cb, $order ) : '';
					$sheet->setCellValueByColumnAndRow( $col_idx + 1, $row_start + $i, $value );
				}
			}

			$sheet->getStyle( '1:1' )->getFont()->setBold( true );
			$sheet->getStyle( '1:1' )->getAlignment()->setHorizontal( \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER );

			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
			$writer->save( $file_path );

		} elseif ( 'pdf' === $export_format ) {
			if ( ! class_exists( '\Mpdf\Mpdf' ) ) {
				require_once EOWC_PLUGIN_PATH . '/vendor/autoload.php';
			}

			// Store rows temporarily until batch finishes, then render final PDF on the last chunk.
			$tmp_json_path = $file_path . '.tmp.json';
			$prepared_rows = array();

			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					continue;
				}

				$row = array();
				foreach ( $column_callbacks as $cb ) {
					$row[] = is_callable( $cb ) ? call_user_func( $cb, $order ) : '';
				}
				$prepared_rows[] = $row;
			}

			$existing_rows = array();
			if ( ! $is_new && file_exists( $tmp_json_path ) ) {
				$existing_rows = json_decode( file_get_contents( $tmp_json_path ), true ) ?: array();
			}

			$all_rows = array_merge( $existing_rows, $prepared_rows );
			file_put_contents( $tmp_json_path, wp_json_encode( $all_rows ) );

			// Generate PDF only on the final batch call.
			if ( ( $offset + $limit ) >= $total || empty( $order_ids ) ) {
				$max_columns_per_page = 8;
				$max_rows_per_page    = 50;

				$column_indexes = array_keys( $headers );
				$column_chunks  = array_chunk( $column_indexes, $max_columns_per_page );
				$row_batches    = array_chunk( $all_rows, $max_rows_per_page );

				$mpdf_temp_dir = trailingslashit( $upload_dir['basedir'] ) . 'eowc-mpdf-tmp';
				wp_mkdir_p( $mpdf_temp_dir );

				$mpdf = new \Mpdf\Mpdf(
					array(
						'mode'                 => 'utf-8',
						'format'               => 'A4-L',
						'tempDir'              => $mpdf_temp_dir,
						'default_font'         => 'dejavusanscondensed',
						'simpleTables'         => true,
						'packTableData'        => true,
						'useSubstitutions'     => true,
						'shrink_tables_to_fit' => 1,
					)
				);

				$page_number = 1;
				foreach ( $row_batches as $batch_index => $current_batch ) {
					foreach ( $column_chunks as $chunk_indexes ) {
						$mpdf->AddPage();
						$html  = '<h3>Orders Export - Page ' . $page_number . '</h3>';
						$html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%" style="border-collapse:collapse;">';
						$html .= '<thead><tr>';
						foreach ( $chunk_indexes as $col_index ) {
							$html .= '<th style="background:#f2f2f2;">' . esc_html( $headers[ $col_index ] ) . '</th>';
						}
						$html .= '</tr></thead><tbody>';

						foreach ( $current_batch as $row ) {
							$html .= '<tr>';
							foreach ( $chunk_indexes as $col_index ) {
								$html .= '<td>' . esc_html( $row[ $col_index ] ) . '</td>';
							}
							$html .= '</tr>';
						}
						$html .= '</tbody></table>';

						$mpdf->WriteHTML( $html );
						++$page_number;
					}
				}

				$mpdf->Output( $file_path, 'F' );
				if ( file_exists( $tmp_json_path ) ) {
					unlink( $tmp_json_path );
				}
			}
		}

		$download_url = admin_url( 'admin-post.php?action=eowc_download_file&file=' . basename( $file_path ) . '&nonce=' . wp_create_nonce( 'eowc_download_file' ) );

		// === 5. Done/next batch.
		if ( empty( $order_ids ) || ( $offset + $limit ) >= $total ) {
			wp_send_json_success(
				array(
					'done'         => true,
					'total'        => $total,
					'file_url'     => $file_url,
					'download_url' => $download_url,
				)
			);
		} else {
			wp_send_json_success(
				array(
					'done'         => false,
					'next_offset'  => $offset + $limit,
					'processed'    => count( $order_ids ),
					'total'        => $total,
					'file_url'     => $file_url,
					'download_url' => $download_url,
				)
			);
		}
	}

	/**
	 * Download the exported file.
	 */
	public function download_file() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		if ( ! isset( $_GET['nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'eowc_download_file' ) ) {

			wp_die( esc_html__( 'Invalid request.', 'woocommerce-export-orders' ) );
		}

		$file = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( $_GET['file'] ) ) : '';

		if ( empty( $file ) ) {
			wp_die( 'File not found' );
		}

		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['basedir'] . '/' . basename( $file );

		if ( ! file_exists( $file_path ) ) {
			wp_die( 'File does not exist' );
		}

		$mime = mime_content_type( $file_path );

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Pragma: public' );
		// phpcs:ignore
		readfile( $file_path );
		exit;
	}

	/**
	 * Save options
	 */
	public function save_installation_date() {
		if ( ! get_option( 'eowc_install_date' ) ) {
			update_option( 'eowc_install_date', current_time( 'timestamp' ) );
		}
	}

	/**
	 * Show Review Notice
	 */
	public function show_review_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( isset( $screen->id ) && ( 'woocommerce_page_wc-orders' === $screen->id || 'toplevel_page_eowc-export-orders' === $screen->id ) ) {

			if ( get_option( 'eowc_review_notice_dismissed' ) ) {
				return;
			}

			$install_time = get_option( 'eowc_install_date', 0 );
			if ( ! $install_time || ( time() - intval( $install_time ) < 604800 ) ) { // 604800 = 1 week
				return;
			}

			$nonce = wp_create_nonce( 'eowc_dismiss_notice_nonce' );
			?>

			<div class="notice notice-success is-dismissible eowc-review-notice">
				<p>
					🎉 <strong>Thanks for using Export Orders For WooCommerce!</strong> 🎉<br><br>
					We hope it has been helpful! Would you consider taking a moment to <a href="https://wordpress.org/support/plugin/woocommerce-export-orders/reviews/?rate=5#new-post" target="_blank">leave us a 5-star review</a>?<br>
					Your feedback keeps us going 💖
				</p>
			</div>
			<script>
				(function($) {
					$(document).on('click', '.eowc-review-notice .notice-dismiss', function() {
						$.post(ajaxurl, {
							action: 'eowc_dismiss_review_notice',
							_nonce: '<?php echo esc_js($nonce); ?>'
						});
					});
				})(jQuery);
			</script>

			<?php
		}
	}

	/**
	 * Dismiss review notice.
	 */
	public function eowc_dismiss_review_notice() {
		if ( ! isset( $_POST['_nonce'] ) || ! wp_verify_nonce( $_POST['_nonce'], 'eowc_dismiss_notice_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce', 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		update_option( 'eowc_review_notice_dismissed', 1 );
		wp_send_json_success( 'Notice dismissed' );
	}

}
