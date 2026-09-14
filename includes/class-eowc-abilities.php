<?php
/**
 * Abilities API integration.
 *
 * @package Export_Orders_For_WooCommerce
 */

namespace EOWC\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register export capabilities for WordPress automation and AI clients.
 */
class EOWC_Abilities {

	/**
	 * Register hooks for the Abilities API.
	 */
	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	/**
	 * Register the export category.
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'export-orders',
			array(
				'label'       => __( 'Export Orders', 'woocommerce-export-orders' ),
				'description' => __( 'Abilities for exporting WooCommerce order data.', 'woocommerce-export-orders' ),
			)
		);
	}

	/**
	 * Register export abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'eowc/export-orders',
			array(
				'label'               => __( 'Export WooCommerce Orders', 'woocommerce-export-orders' ),
				'description'         => __( 'Exports a batch of WooCommerce orders using date, status, format, and column filters. Repeat with next_offset and the same export_id until done is true.', 'woocommerce-export-orders' ),
				'category'            => 'export-orders',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'eowc_status'        => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'eowc_date_from'     => array( 'type' => 'string', 'format' => 'date' ),
						'eowc_date_to'       => array( 'type' => 'string', 'format' => 'date' ),
						'eowc_export_format' => array(
							'type' => 'string',
							'enum' => array( 'csv', 'json', 'xlsx', 'pdf', 'xml' ),
						),
						'eowc_columns'       => array(
							'type'     => 'array',
							'items'    => array( 'type' => 'string' ),
							'minItems' => 1,
						),
						'offset'             => array( 'type' => 'integer', 'minimum' => 0 ),
						'export_id'          => array( 'type' => 'string' ),
					),
					'required'   => array( 'eowc_date_from', 'eowc_date_to', 'eowc_columns' ),
				),
				'output_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'done'         => array( 'type' => 'boolean' ),
						'next_offset'  => array( 'type' => 'integer' ),
						'processed'    => array( 'type' => 'integer' ),
						'total'        => array( 'type' => 'integer' ),
						'file_url'     => array( 'type' => 'string' ),
						'download_url' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'execute_export_orders' ),
				'permission_callback' => array( __CLASS__, 'can_export_orders' ),
				'meta'               => array(
					'public'       => true,
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);
	}

	/**
	 * Check whether the current user can export orders.
	 *
	 * @param mixed $input Ability input.
	 * @return bool
	 */
	public static function can_export_orders( $input = null ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Execute one export batch.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute_export_orders( $input ) {
		return EOWC_Admin::process_export_batch( $input );
	}
}
