<?php 
/*
Plugin Name: WooCommerce Export Orders
Plugin URI: https://imaginate-solutions.com/
Description: This plugin lets store owners export orders.
Version: 1.1.0
Author: Dhruvin Shah
Author URI: https://imaginate-solutions.com/
Requires PHP: 8.0
WC requires at least: 3.0.0
WC tested up to: 8.0.0
*/

/**
 * Check if WooCommerce is activated
 */
if ( ! function_exists( 'is_woocommerce_activated' ) ) {
    function is_woocommerce_activated() {
        return class_exists( 'WooCommerce' );
    }
}

// Prevent activation if WooCommerce is not active
function woo_export_orders_activation() {
    if (!is_woocommerce_activated()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('<p><strong>WooCommerce Export Orders</strong> requires WooCommerce to be installed and active. <a href="' . esc_url(admin_url('plugins.php')) . '">Go back</a></p>', 'Plugin Activation Error', ['back_link' => true]);
    }
}
register_activation_hook(__FILE__, 'woo_export_orders_activation');

/**
 * Declare HPOS Compatibility
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action( 'plugins_loaded', function () {
    if (!is_woocommerce_activated()) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>WooCommerce Export Orders</strong> requires WooCommerce to be installed and active.</p></div>';
        });

        // Deactivate the plugin if WooCommerce is not active
        add_action('admin_init', function () {
            deactivate_plugins(plugin_basename(__FILE__));
        });
        return;
    }

    if (!class_exists('Woo_Export')) {
        class Woo_Export {
            private array $order_status = []; 

            public function __construct() {
                $this->order_status = array(
                    'completed'   => __('Completed', 'woo-export-order'),
                    'cancelled'   => __('Cancelled', 'woo-export-order'),
                    'failed'      => __('Failed', 'woo-export-order'),
                    'refunded'    => __('Refunded', 'woo-export-order'),
                    'processing'  => __('Processing', 'woo-export-order'),
                    'pending'     => __('Pending', 'woo-export-order'),
                    'on-hold'     => __('On Hold', 'woo-export-order'),
                );

                add_action('admin_menu', [$this, 'woo_export_orders_menu']);
                add_action('admin_enqueue_scripts', [$this, 'export_enqueue_scripts']);
            }

            function export_enqueue_scripts($hook) {
                if (isset($_GET['page']) && $_GET['page'] === 'export_orders_page') {
                    wp_enqueue_style('semantic', plugins_url('/css/semantic.min.css', __FILE__));
                    wp_enqueue_style('semanticDataTable', plugins_url('/css/dataTables.semanticui.min.css', __FILE__));
                    wp_enqueue_style('semanticButtons', plugins_url('/css/buttons.semanticui.min.css', __FILE__));
                    wp_enqueue_style('dataTable', plugins_url('/css/data.table.css', __FILE__));

                    wp_enqueue_script('dataTable', plugins_url('/js/jquery.dataTables.js', __FILE__), ['jquery'], null, true);
                    wp_enqueue_script('dataTableSemantic', plugins_url('/js/dataTables.semanticui.min.js', __FILE__), ['jquery'], null, true);
                    wp_enqueue_script('dataTableButtons', plugins_url('/js/dataTables.buttons.min.js', __FILE__), ['jquery'], null, true);
                    wp_enqueue_script('buttonsSemantic', plugins_url('/js/buttons.semanticui.min.js', __FILE__), ['jquery'], null, true);
                    wp_enqueue_script('woo_pdfmake', plugins_url('/js/pdfmake.min.js', __FILE__), [], null, true);
                    wp_enqueue_script('jszip', plugins_url('/js/jszip.min.js', __FILE__), [], null, true);
                    wp_enqueue_script('vfsfonts', plugins_url('/js/vfs_fonts.js', __FILE__), [], null, true);
                    wp_enqueue_script('buttonsHTML5', plugins_url('/js/buttons.html5.min.js', __FILE__), ['jquery'], null, true);
                }
            }

            function woo_export_orders_menu() {
                add_menu_page('Export Orders', 'Export Orders', 'manage_woocommerce', 'export_orders_page', [$this, 'export_orders_page'], 'dashicons-media-spreadsheet', 55.7);
            }

            function export_orders_page() {
				$args = ['limit' => -1];
				$orders = wc_get_orders($args);
				$var = '';
				
				if (!empty($orders)) {
					foreach ($orders as $order) {
						$order_id = $order->get_id();
						$order_status = $order->get_status();
						$status_label = isset($this->order_status[$order_status]) ? esc_html($this->order_status[$order_status]) : esc_html__('Unknown', 'woo-export-order');
						$customer_name = esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
						$customer_email = esc_html($order->get_billing_email());
						$customer_phone = esc_html($order->get_billing_phone());
						$order_date = esc_html($order->get_date_created()->date('Y-m-d H:i:s'));
						$order_url = esc_url(admin_url("post.php?post=$order_id&action=edit"));
				
						foreach ($order->get_items() as $item) {
							$product_name = esc_html($item->get_name());
							$product_total = esc_html(strip_tags(wc_price($item->get_total()))); 
				
							$var .= "<tr>
								<td>{$order_id}</td>
								<td>{$status_label}</td>
								<td>{$customer_name}</td>
								<td>{$customer_email}</td>
								<td>{$customer_phone}</td>
								<td>{$product_name}</td>
								<td>{$product_total}</td>
								<td>{$order_date}</td>
								<td><a href='{$order_url}'>" . __('View Order', 'woo-export-order') . "</a></td>
							</tr>";
						}
					}
				} else {
					$var .= "<tr><td colspan='9' style='text-align: center;'>" . __('No orders found.', 'woo-export-order') . "</td></tr>";
				}
				
                ?>
                <br>
                <h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
                    <a href="admin.php?page=export_orders_page" class="nav-tab nav-tab-active"> <?php _e('Export Orders', 'woo-export-order'); ?> </a>
                </h2>
                <div>
                    <table id="order_history" class="ui celled table" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th><?php _e('Order ID', 'woo-export-order'); ?></th>
                                <th><?php _e('Order Status', 'woo-export-order'); ?></th>
                                <th><?php _e('Customer Name', 'woo-export-order'); ?></th>
                                <th><?php _e('Customer Email', 'woo-export-order'); ?></th>
                                <th><?php _e('Customer Phone', 'woo-export-order'); ?></th>
                                <th><?php _e('Product Name', 'woo-export-order'); ?></th>
                                <th><?php _e('Amount', 'woo-export-order'); ?></th>
                                <th><?php _e('Order Date', 'woo-export-order'); ?></th>
                                <th><?php _e('Action', 'woo-export-order'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php echo $var; ?>
                        </tbody>
                    </table>
                </div>
                <script>
                   jQuery(document).ready(function() {
					jQuery('#order_history').DataTable({
						"bSortClasses": false,
						"aaSorting": [[0, 'desc']],
						"bAutoWidth": false,
						"bInfo": true,
						"bScrollCollapse": true,
						"sPaginationType": "full_numbers",
						"bRetrieve": true,
						"oLanguage": {
							"sSearch": "Search:",
							"sInfo": "Showing _START_ to _END_ of _TOTAL_ Products",
							"sInfoEmpty": "Showing 0 to 0 of 0 entries",
							"sZeroRecords": "No Products to display",
							"sInfoFiltered": "(filtered from _MAX_ total entries)",
							"sEmptyTable": "No data available",
							"sLengthMenu": "Number of records to show: _MENU_",
							"oPaginate": {
								"sFirst": "First",
								"sPrevious": "Previous",
								"sNext": "Next",
								"sLast": "Last"
							}
						},
						"dom": 'Blfrtip',
						"buttons": [
							'copyHtml5',
							'excelHtml5',
							{
								extend: 'csvHtml5',
								fieldBoundary: '',
								fieldSeparator: ',',
								charset: 'utf-8',
								bom: true,
								format: {
									body: function(data, row, column, node) {
										// Strip HTML tags and decode HTML entities
										var decodedData = jQuery('<div>').html(data).text();
										// Remove currency symbols and format numbers
										return decodedData.replace(/[^\d.-]/g, '');
									}
								}
							},
							{
								extend: 'pdfHtml5',
								orientation: 'landscape', 
								pageSize: 'A4',
								customize: function(doc) {
									doc.content[1].table.widths = ['10%', '10%', '15%', '15%', '10%', '15%', '10%', '10%', '10%']; 
									doc.defaultStyle.fontSize = 8; 
									}
							}
						]
					});
				});
                </script>
                <?php
            }
        }
        new Woo_Export();
    }
}); 
