<?php
/** Pro feature comparison for the Lite edition. */
defined( 'ABSPATH' ) || exit;
$eowc_features = array(
	array( __( 'CSV, XLSX, PDF, XML and JSON', 'woocommerce-export-orders' ), true ),
	array( __( 'Date/status filters and draggable columns', 'woocommerce-export-orders' ), true ),
	array( __( 'AI Export Assistant and core ChatGPT/Claude MCP connections', 'woocommerce-export-orders' ), true ),
	array( __( 'WooCommerce HPOS support', 'woocommerce-export-orders' ), true ),
	array( __( 'Hourly, daily, weekly and monthly schedules', 'woocommerce-export-orders' ), false ),
	array( __( 'Saved column templates and built-in field layouts', 'woocommerce-export-orders' ), false ),
	array( __( 'Custom order and order-item metadata', 'woocommerce-export-orders' ), false ),
	array( __( 'Background export history and repeat downloads', 'woocommerce-export-orders' ), false ),
	array( __( 'FTP, Dropbox and Google Drive delivery', 'woocommerce-export-orders' ), false ),
	array( __( 'Completion emails with optional attachments', 'woocommerce-export-orders' ), false ),
	array( __( 'Schedule run status and delivery results', 'woocommerce-export-orders' ), false ),
	array( __( 'Google Sheets new-order sync and Zapier notifications', 'woocommerce-export-orders' ), false ),
);
?>
<div class="wrap eowc-export-orders eowc-upgrade-page">
	<div class="eowc-header"><div class="eowc-header-icon" aria-hidden="true"><span class="dashicons dashicons-chart-line"></span></div><div><h1 class="eowc-title"><?php esc_html_e( 'Upgrade to Pro', 'woocommerce-export-orders' ); ?></h1><p class="eowc-subtitle"><?php esc_html_e( 'Automate your WooCommerce order reports.', 'woocommerce-export-orders' ); ?></p></div></div>
	<p><?php esc_html_e( 'Move recurring reports into scheduled workflows with Export Orders Pro. Save column layouts, include custom metadata and deliver files to your team automatically.', 'woocommerce-export-orders' ); ?></p>
	<p><a class="eowc-btn eowc-btn--primary" href="<?php echo esc_url( \EOWC\Includes\EOWC_Admin::pro_url( 'lite_upgrade_page_top' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Pro Features & Pricing', 'woocommerce-export-orders' ); ?></a> <a class="eowc-btn eowc-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=eowc-export-orders' ) ); ?>"><?php esc_html_e( 'Continue With Lite', 'woocommerce-export-orders' ); ?></a></p>
	<h2><?php esc_html_e( 'Lite and Pro at a glance', 'woocommerce-export-orders' ); ?></h2>
	<table class="widefat striped eowc-comparison">
		<thead><tr><th scope="col"><?php esc_html_e( 'Feature', 'woocommerce-export-orders' ); ?></th><th scope="col"><?php esc_html_e( 'Lite', 'woocommerce-export-orders' ); ?></th><th scope="col"><?php esc_html_e( 'Pro', 'woocommerce-export-orders' ); ?></th></tr></thead>
		<tbody><?php foreach ( $eowc_features as $eowc_feature ) : ?>
			<tr><th scope="row"><?php echo esc_html( $eowc_feature[0] ); ?></th><td><?php echo $eowc_feature[1] ? esc_html__( 'Included', 'woocommerce-export-orders' ) : esc_html__( 'Pro only', 'woocommerce-export-orders' ); ?></td><td><?php esc_html_e( 'Included', 'woocommerce-export-orders' ); ?></td></tr>
		<?php endforeach; ?></tbody>
	</table>
	<p><?php esc_html_e( 'Keep using Lite for everyday exports, or choose Pro to save time with scheduled reports, reusable templates and automatic delivery.', 'woocommerce-export-orders' ); ?></p>
	<p><a class="eowc-btn eowc-btn--primary" href="<?php echo esc_url( \EOWC\Includes\EOWC_Admin::pro_url( 'lite_upgrade_page_bottom' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Explore Pro Automation', 'woocommerce-export-orders' ); ?></a></p>
</div>
