<?php
/**
 * Export Orders Template
 *
 * @package Export_Orders_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="eowc-header">
	<div class="eowc-header-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
	</div>
	<div>
		<h2 class="eowc-title"><?php esc_html_e( 'Export Orders', 'woocommerce-export-orders' ); ?></h2>
		<p class="eowc-subtitle"><?php esc_html_e( 'Filter, configure and download your WooCommerce orders.', 'woocommerce-export-orders' ); ?></p>
	</div>
</div>
<hr class="wp-header-end">

<form id="eowc-export-form" class="eowc-form" novalidate>

	<!-- Step 1: Config -->
	<div class="eowc-step" id="eowc-step-config">

		<!-- Filters -->
		<div class="eowc-card">
			<div class="eowc-card-head">
				<span class="eowc-card-icon">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
				</span>
				<h3><?php esc_html_e( 'Filters', 'woocommerce-export-orders' ); ?></h3>
			</div>

			<div class="eowc-fields-row">
				<!-- Order Status -->
				<div class="eowc-field">
					<label for="eowc-status"><?php esc_html_e( 'Order Status', 'woocommerce-export-orders' ); ?></label>
					<div class="eowc-select-wrap">
						<select name="eowc_status[]" id="eowc-status" class="eowc-select" multiple >
							<?php
							$eowc_statuses = wc_get_order_statuses();
							foreach ( $eowc_statuses as $eowc_status_key => $eowc_status_label ) :
								?>
								<option value="<?php echo esc_attr( $eowc_status_key ); ?>"><?php echo esc_html( $eowc_status_label ); ?></option>
							<?php endforeach; ?>
						</select>						
					</div>
				</div>

				<!-- Date From -->
				<div class="eowc-field eowc-field--required">
					<label for="eowc-date-from">
						<?php esc_html_e( 'Date From', 'woocommerce-export-orders' ); ?>
						<span class="eowc-required-star" aria-hidden="true">*</span>
					</label>
					<input type="date" name="eowc_date_from" id="eowc-date-from" class="eowc-input" required>
					<span class="eowc-field-error"><?php esc_html_e( 'Please select a start date.', 'woocommerce-export-orders' ); ?></span>
				</div>

				<!-- Date To -->
				<div class="eowc-field eowc-field--required">
					<label for="eowc-date-to">
						<?php esc_html_e( 'Date To', 'woocommerce-export-orders' ); ?>
						<span class="eowc-required-star" aria-hidden="true">*</span>
					</label>
					<input type="date" name="eowc_date_to" id="eowc-date-to" class="eowc-input" required>
					<span class="eowc-field-error"><?php esc_html_e( 'Please select an end date.', 'woocommerce-export-orders' ); ?></span>
				</div>
			</div>

			<div class="eowc-validation-banner" id="eowc-date-range-error" style="display:none;">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
				<?php esc_html_e( '"Date To" must be on or after "Date From".', 'woocommerce-export-orders' ); ?>
			</div>
		</div>

		<div class="eowc-card">
			<div class="eowc-card-head">
				<span class="eowc-card-icon">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="2" x2="9" y2="6"/><line x1="15" y1="2" x2="15" y2="6"/></svg>
				</span>
				<h3><?php esc_html_e( 'Export Format', 'woocommerce-export-orders' ); ?></h3>
			</div>

			<div class="eowc-fields-row eowc-fields-row--radio">
				<div class="eowc-field">
					<label><?php esc_html_e( 'Choose Format', 'woocommerce-export-orders' ); ?></label>
					<div class="eowc-radio-group" style="display:flex; gap:18px;">
						<label class="eowc-radio-item">
							<input type="radio" name="eowc_export_format" value="csv" checked>
							<span class="eowc-radio-check">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
							</span>
							<span class="eowc-radio-label"><?php esc_html_e( 'CSV', 'woocommerce-export-orders' ); ?></span>
						</label>
						<label class="eowc-radio-item">
							<input type="radio" name="eowc_export_format" value="xml">
							<span class="eowc-radio-check">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
							</span>
							<span class="eowc-radio-label"><?php esc_html_e( 'XML', 'woocommerce-export-orders' ); ?></span>
						</label>
						<label class="eowc-radio-item">
							<input type="radio" name="eowc_export_format" value="xlsx">
							<span class="eowc-radio-check">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
							</span>
							<span class="eowc-radio-label"><?php esc_html_e( 'Excel (XLSX)', 'woocommerce-export-orders' ); ?></span>
						</label>
						<label class="eowc-radio-item">
							<input type="radio" name="eowc_export_format" value="pdf">
							<span class="eowc-radio-check">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
							</span>
							<span class="eowc-radio-label"><?php esc_html_e( 'PDF', 'woocommerce-export-orders' ); ?></span>
						</label>
						<label class="eowc-radio-item">
							<input type="radio" name="eowc_export_format" value="json">
							<span class="eowc-radio-check">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
							</span>
							<span class="eowc-radio-label"><?php esc_html_e( 'JSON', 'woocommerce-export-orders' ); ?></span>
						</label>
					</div>
				</div>
			</div>
		</div>

		<!-- Export Columns -->
		<div class="eowc-card">
			<div class="eowc-card-head">
				<span class="eowc-card-icon">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
				</span>
				<h3><?php esc_html_e( 'Export Columns', 'woocommerce-export-orders' ); ?></h3>
				<div class="eowc-column-actions">
					<button type="button" class="eowc-link-btn" id="eowc-select-all"><?php esc_html_e( 'Select All', 'woocommerce-export-orders' ); ?></button>
					<span class="eowc-dot-sep">·</span>
					<button type="button" class="eowc-link-btn" id="eowc-deselect-all"><?php esc_html_e( 'Deselect All', 'woocommerce-export-orders' ); ?></button>
				</div>
			</div>

			<p class="eowc-card-desc"><?php esc_html_e( 'Choose fields to include, then drag them into the export order you want.', 'woocommerce-export-orders' ); ?></p>

			<?php
			$eowc_column_groups = array(
				__( 'Order Info', 'woocommerce-export-orders' ) => array(
					'order_id'       => __( 'Order ID', 'woocommerce-export-orders' ),
					'order_status'   => __( 'Status', 'woocommerce-export-orders' ),
					'order_date'     => __( 'Order Date', 'woocommerce-export-orders' ),
					'order_total'    => __( 'Order Total', 'woocommerce-export-orders' ),
					'order_subtotal' => __( 'Subtotal', 'woocommerce-export-orders' ),
					'order_discount' => __( 'Discount', 'woocommerce-export-orders' ),
					'order_tax'      => __( 'Tax', 'woocommerce-export-orders' ),
					'shipping_total' => __( 'Shipping Total', 'woocommerce-export-orders' ),
					'payment_method' => __( 'Payment Method', 'woocommerce-export-orders' ),
					'transaction_id' => __( 'Transaction ID', 'woocommerce-export-orders' ),
					'customer_note'  => __( 'Customer Note', 'woocommerce-export-orders' ),
					'coupon_codes'   => __( 'Coupon Codes', 'woocommerce-export-orders' ),
				),
				__( 'Customer', 'woocommerce-export-orders' ) => array(
					'customer_id'        => __( 'Customer ID', 'woocommerce-export-orders' ),
					'customer_email'     => __( 'Email', 'woocommerce-export-orders' ),
					'customer_phone'     => __( 'Phone', 'woocommerce-export-orders' ),
					'billing_first_name' => __( 'First Name (Billing)', 'woocommerce-export-orders' ),
					'billing_last_name'  => __( 'Last Name (Billing)', 'woocommerce-export-orders' ),
					'billing_company'    => __( 'Company (Billing)', 'woocommerce-export-orders' ),
					'billing_address_1'  => __( 'Address 1 (Billing)', 'woocommerce-export-orders' ),
					'billing_address_2'  => __( 'Address 2 (Billing)', 'woocommerce-export-orders' ),
					'billing_city'       => __( 'City (Billing)', 'woocommerce-export-orders' ),
					'billing_state'      => __( 'State (Billing)', 'woocommerce-export-orders' ),
					'billing_postcode'   => __( 'Postcode (Billing)', 'woocommerce-export-orders' ),
					'billing_country'    => __( 'Country (Billing)', 'woocommerce-export-orders' ),
				),
				__( 'Shipping', 'woocommerce-export-orders' ) => array(
					'shipping_first_name' => __( 'First Name (Shipping)', 'woocommerce-export-orders' ),
					'shipping_last_name'  => __( 'Last Name (Shipping)', 'woocommerce-export-orders' ),
					'shipping_company'    => __( 'Company (Shipping)', 'woocommerce-export-orders' ),
					'shipping_address_1'  => __( 'Address 1 (Shipping)', 'woocommerce-export-orders' ),
					'shipping_address_2'  => __( 'Address 2 (Shipping)', 'woocommerce-export-orders' ),
					'shipping_city'       => __( 'City (Shipping)', 'woocommerce-export-orders' ),
					'shipping_state'      => __( 'State (Shipping)', 'woocommerce-export-orders' ),
					'shipping_postcode'   => __( 'Postcode (Shipping)', 'woocommerce-export-orders' ),
					'shipping_country'    => __( 'Country (Shipping)', 'woocommerce-export-orders' ),
					'shipping_method'     => __( 'Shipping Method', 'woocommerce-export-orders' ),
				),
				__( 'Products', 'woocommerce-export-orders' ) => array(
					'product_names'      => __( 'Product Names', 'woocommerce-export-orders' ),
					'product_skus'       => __( 'Product SKUs', 'woocommerce-export-orders' ),
					'product_quantities' => __( 'Quantities', 'woocommerce-export-orders' ),
					'product_totals'     => __( 'Line Totals', 'woocommerce-export-orders' ),
				),
			);

			foreach ( $eowc_column_groups as $eowc_group_label => $eowc_columns ) :
				?>
				<div class="eowc-column-group">
					<div class="eowc-column-group-label"><?php echo esc_html( $eowc_group_label ); ?></div>
					<div class="eowc-checkbox-grid">
						<?php foreach ( $eowc_columns as $eowc_col_key => $eowc_col_label ) : ?>
							<label class="eowc-checkbox-item">
								<span class="eowc-drag-handle" aria-hidden="true">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/></svg>
								</span>
								<input type="checkbox" name="eowc_columns[]" value="<?php echo esc_attr( $eowc_col_key ); ?>" checked>
								<span class="eowc-checkmark">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
								</span>
								<span class="eowc-checkbox-label"><?php echo esc_html( $eowc_col_label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<div class="eowc-validation-banner" id="eowc-columns-error" style="display:none;">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
				<?php esc_html_e( 'Please select at least one column to export.', 'woocommerce-export-orders' ); ?>
			</div>
		</div>

		<!-- Actions -->
		<div class="eowc-actions">
			<button type="button" class="eowc-btn eowc-btn--primary" id="eowc-proceed-btn">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
				<?php esc_html_e( 'Review & Export', 'woocommerce-export-orders' ); ?>
			</button>
		</div>
	</div>

	<!-- Step 2: Confirmation -->
	<div class="eowc-step eowc-step--hidden" id="eowc-step-confirm">
		<div class="eowc-card eowc-card--confirm">
			<div class="eowc-card-head">
				<span class="eowc-card-icon eowc-card-icon--confirm">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
				</span>
				<h3><?php esc_html_e( 'Confirm Export', 'woocommerce-export-orders' ); ?></h3>
			</div>
			<p class="eowc-card-desc"><?php esc_html_e( 'Review your export settings before proceeding.', 'woocommerce-export-orders' ); ?></p>

			<div class="eowc-summary" id="eowc-summary">
				<!-- Populated by JS -->
			</div>
		</div>

		<div class="eowc-actions">
			<button type="button" class="eowc-btn eowc-btn--ghost" id="eowc-back-btn">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
				<?php esc_html_e( 'Go Back', 'woocommerce-export-orders' ); ?>
			</button>
			<button type="submit" class="eowc-btn eowc-btn--primary eowc-export-submit">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
				<?php esc_html_e( 'Start Export', 'woocommerce-export-orders' ); ?>
			</button>
		</div>
	</div>

	<!-- Progress Overlay -->
	<div class="eowc-overlay" style="display:none;">
		<div class="eowc-overlay-content">
			<div class="eowc-spinner"></div>
			<div class="eowc-progress-wrapper">
				<div class="eowc-progress-track">
					<div class="eowc-progress-bar"></div>
				</div>
				<span class="eowc-progress-pct">0%</span>
			</div>
			<p class="eowc-progress-text"><?php esc_html_e( 'Preparing export…', 'woocommerce-export-orders' ); ?></p>
		</div>
	</div>

</form>
