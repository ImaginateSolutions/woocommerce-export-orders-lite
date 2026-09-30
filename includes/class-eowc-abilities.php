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
		add_action( 'eowc_process_export_job', array( __CLASS__, 'process_async_job' ) );
		add_action( 'eowc_expire_export_job', array( __CLASS__, 'expire_export_job' ) );
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
	 * Return metadata for fields supported by the exporter.
	 *
	 * @param array $input input data.
	 * @return array
	 */
	public static function get_fields( $input = array() ): array {
		$input  = is_array( $input ) ? $input : array();
		$fields = self::field_metadata();
		$group  = isset( $input['group'] ) ? sanitize_key( $input['group'] ) : '';
		$search = isset( $input['search'] ) ? strtolower( sanitize_text_field( $input['search'] ) ) : '';

		$fields = array_values(
			array_filter(
				$fields,
				function ( $field ) use ( $group, $search ) {
					return ( ! $group || $group === $field['group'] ) && ( ! $search || false !== strpos( strtolower( $field['key'] . ' ' . $field['label'] ), $search ) );
				}
			)
		);

		return array(
			'fields'         => $fields,
			'schema_version' => '1.0',
		);
	}

	/**
	 * Return enabled file formats and constraints.
	 *
	 * @param array $input input data.
	 * @return array
	 */
	// phpcs:ignore
	public static function get_formats( $input = array() ): array {
		return array(
			'formats'        => array(
				array(
					'id'              => 'csv',
					'label'           => 'CSV',
					'mime_type'       => 'text/csv',
					'edition'         => 'free',
					'extension'       => 'csv',
					'batch_supported' => true,
					'limits'          => array( 'max_rows_per_batch' => 100 ),
				),
				array(
					'id'              => 'json',
					'label'           => 'JSON',
					'mime_type'       => 'application/json',
					'edition'         => 'free',
					'extension'       => 'json',
					'batch_supported' => true,
					'limits'          => array( 'max_rows_per_batch' => 100 ),
				),
				array(
					'id'              => 'xlsx',
					'label'           => 'Excel (XLSX)',
					'mime_type'       => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
					'edition'         => 'free',
					'extension'       => 'xlsx',
					'batch_supported' => true,
					'limits'          => array( 'max_rows_per_batch' => 100 ),
				),
				array(
					'id'              => 'pdf',
					'label'           => 'PDF',
					'mime_type'       => 'application/pdf',
					'edition'         => 'free',
					'extension'       => 'pdf',
					'batch_supported' => true,
					'limits'          => array( 'max_rows_per_batch' => 100 ),
				),
				array(
					'id'              => 'xml',
					'label'           => 'XML',
					'mime_type'       => 'application/xml',
					'edition'         => 'free',
					'extension'       => 'xml',
					'batch_supported' => true,
					'limits'          => array( 'max_rows_per_batch' => 100 ),
				),
			),
			'default'        => 'csv',
			'max_batch_size' => 100,
			'schema_version' => '1.0',
		);
	}

	/**
	 * Preview matching orders and sample rows.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function query_exports( $input ) {
		$input = self::normalize_filters( $input, false );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		$fields = $input['eowc_columns'];

		$args             = self::order_query_args( $input, min( 100, max( 1, absint( $input['limit'] ?? 20 ) ) ) );
		$args['paginate'] = true;
		$args['return']   = 'ids';
		$result           = wc_get_orders( $args );
		$rows             = array();

		$sample_limit = min( 20, max( 0, absint( $input['sample_limit'] ?? 20 ) ) );
		foreach ( array_slice( $result->orders, 0, $sample_limit ) as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$row = array();
				foreach ( $fields as $field ) {
					$row[ $field ] = self::get_order_field_value( $order, $field );
				}
				$rows[] = $row;
			}
		}

		$response                         = array(
			'total'             => (int) $result->total,
			'sample_size'       => count( $rows ),
			'fields'            => $fields,
			'rows'              => $rows,
			'interpreted_range' => array(
				'from'     => $input['eowc_date_from'],
				'to'       => $input['eowc_date_to'],
				'timezone' => wp_timezone_string(),
			),
		);
		$response['aggregates']           = self::aggregate_orders( $input, $result->total );
		$response['aggregate_definition'] = array(
			'order_total'    => 'WooCommerce order total as stored; refunds are not subtracted again.',
			'order_subtotal' => 'WooCommerce order subtotal as stored.',
			'order_tax'      => 'WooCommerce total tax, reported separately from order_total.',
			'shipping_total' => 'WooCommerce shipping total, reported separately from order_total.',
		);
		return $response;
	}

	/**
	 * Create the first batch of an immediate export job.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function generate_export( $input ) {
		$input = self::normalize_filters( $input, true );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		$idempotency_key = sanitize_text_field( $input['idempotency_key'] );
		if ( '' === $idempotency_key ) {
			return new \WP_Error( 'eowc_missing_idempotency_key', 'A unique idempotency_key is required.' );
		}
		$existing = get_transient( self::idempotency_key( $idempotency_key ) );
		if ( is_array( $existing ) ) {
			return $existing;
		}
		$job_id             = 'exp_' . wp_generate_uuid4();
		$input['export_id'] = $job_id;
		$input['offset']    = absint( $input['offset'] ?? 0 );
		$input['job_id']    = $job_id;
		$total              = self::count_matching_orders( $input );
		if ( is_wp_error( $total ) ) {
			return $total;
		}
		$extension = sanitize_key( $input['eowc_export_format'] );
		$state     = array(
			'export_id'        => $job_id,
			'status'           => $total > 100 ? 'queued' : 'processing',
			'file_name'        => 'eowc-orders-' . get_current_user_id() . '-' . $job_id . '.' . $extension,
			'matched_records'  => $total,
			'processed'        => 0,
			'total'            => $total,
			'progress_percent' => 0,
			'download'         => null,
			'error_code'       => null,
			'created_at'       => gmdate( 'c' ),
			'updated_at'       => gmdate( 'c' ),
			'status_reference' => array(
				'ability'   => 'eowc/get-export-status',
				'export_id' => $job_id,
			),
		);
		set_transient( self::job_key( $job_id ), $state, self::retention_seconds() );
		wp_schedule_single_event( time() + self::retention_seconds(), 'eowc_expire_export_job', array( $job_id, get_current_user_id() ) );
		if ( $total > 100 ) {
			$state['input'] = $input;
			set_transient( self::job_key( $job_id ), $state, self::retention_seconds() );
			wp_schedule_single_event( time() + 1, 'eowc_process_export_job', array( $job_id, get_current_user_id() ) );
			set_transient( self::idempotency_key( $idempotency_key ), $state, self::retention_seconds() );
			unset( $state['input'] );
			return $state;
		}
		$result = EOWC_Admin::process_export_batch( $input );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$status = array(
			'export_id'        => $job_id,
			'status'           => $result['done'] ? 'completed' : 'processing',
			'total'            => (int) $result['total'],
			'processed'        => $result['done'] ? (int) $result['total'] : (int) $result['processed'],
			'progress_percent' => $result['done'] ? 100 : round( ( $result['processed'] / max( 1, $result['total'] ) ) * 100, 2 ),
			'download'         => $result['done'] ? array(
				'url'      => $result['download_url'],
				'file_url' => $result['file_url'],
			) : null,
			'created_at'       => gmdate( 'c' ),
			'updated_at'       => gmdate( 'c' ),
			'file_name'        => 'eowc-orders-' . get_current_user_id() . '-' . $job_id . '.' . sanitize_key( $input['eowc_export_format'] ),
			'matched_records'  => (int) $result['total'],
			'status_reference' => array(
				'ability'   => 'eowc/get-export-status',
				'export_id' => $job_id,
			),
		);
		set_transient( self::job_key( $job_id ), $status, self::retention_seconds() );
		set_transient( self::idempotency_key( $idempotency_key ), $status, self::retention_seconds() );

		return $status;
	}

	/**
	 * Get a user-owned export job status.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function get_export_status( $input ) {
		$job_id = isset( $input['export_id'] ) ? sanitize_file_name( $input['export_id'] ) : '';
		$status = $job_id ? get_transient( self::job_key( $job_id ) ) : false;

		if ( ! is_array( $status ) ) {
			return array(
				'export_id'        => $job_id,
				'status'           => 'expired',
				'processed'        => 0,
				'total'            => null,
				'progress_percent' => 0,
				'error_code'       => 'eowc_job_expired',
				'download'         => null,
			);
		}

		unset( $status['input'] );
		return $status;
	}

	/**
	 * Check read access.
	 *
	 * @return bool
	 */
	public static function can_read_exports(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check create access.
	 *
	 * @return bool
	 */
	public static function can_create_exports(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Process one queued export batch from WP-Cron.
	 *
	 * @param string $export_id Export identifier.
	 * @param int    $user_id Owner identifier.
	 */
	public static function process_async_job( $export_id, $user_id ): void {
		$state = get_transient( self::job_key( $export_id, $user_id ) );
		if ( ! is_array( $state ) || empty( $state['input'] ) ) {
			return;
		}
		$idempotency_key = $state['input']['idempotency_key'] ?? '';
		wp_set_current_user( absint( $user_id ) );
		$result = EOWC_Admin::process_export_batch( $state['input'] );
		if ( is_wp_error( $result ) ) {
			$state['status']     = 'failed';
			$state['error_code'] = $result->get_error_code();
		} else {
			$state['processed']        = min( $state['total'], (int) ( $state['processed'] + ( $result['processed'] ?? $state['total'] ) ) );
			$state['progress_percent'] = round( ( $state['processed'] / max( 1, $state['total'] ) ) * 100, 2 );
			if ( $result['done'] ) {
				$state['status']   = 'completed';
				$state['download'] = array(
					'url'      => $result['download_url'],
					'file_url' => $result['file_url'],
				);
				unset( $state['input'] );
			} else {
				$state['status']          = 'processing';
				$state['input']['offset'] = (int) $result['next_offset'];
				wp_schedule_single_event( time() + 1, 'eowc_process_export_job', array( $export_id, $user_id ) );
			}
		}
		$state['updated_at'] = gmdate( 'c' );
		set_transient( self::job_key( $export_id, $user_id ), $state, self::retention_seconds() );
		if ( $idempotency_key ) {
			set_transient( self::idempotency_key( $idempotency_key, $user_id ), $state, self::retention_seconds() );
		}
	}

	/**
	 * Remove an expired export file and its job state.
	 *
	 * @param string $export_id Export identifier.
	 * @param int    $user_id Owner identifier.
	 */
	public static function expire_export_job( $export_id, $user_id ): void {
		$state = get_transient( self::job_key( $export_id, $user_id ) );
		if ( is_array( $state ) && ! empty( $state['file_name'] ) ) {
			$upload_dir = wp_upload_dir();
			$file_path  = trailingslashit( $upload_dir['basedir'] ) . sanitize_file_name( $state['file_name'] );
			if ( file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}
			if ( file_exists( $file_path . '.tmp.json' ) ) {
				wp_delete_file( $file_path . '.tmp.json' );
			}
		}
		delete_transient( self::job_key( $export_id, $user_id ) );
	}

	/**
	 * Normalize nested or legacy filters and validate entitlement inputs.
	 *
	 * @param array $input Input data.
	 * @param bool  $require_fields Whether fields and format are required.
	 * @return array|\WP_Error
	 */
	private static function normalize_filters( $input, $require_fields ) {
		$input   = is_array( $input ) ? $input : array();
		$filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : $input;
		$fields  = isset( $input['fields'] ) ? $input['fields'] : ( $input['eowc_columns'] ?? array() );
		if ( ! empty( $input['field_order'] ) && is_array( $input['field_order'] ) ) {
			$fields = $input['field_order'];
		}
		$format        = isset( $input['format'] ) ? $input['format'] : ( $input['eowc_export_format'] ?? 'csv' );
		$fields        = is_array( $fields ) ? array_values( array_unique( array_map( 'sanitize_key', $fields ) ) ) : array();
		$field_aliases = array(
			'billing_email' => 'customer_email',
			'product_sku'   => 'product_skus',
			'quantity'      => 'product_quantities',
		);
		$fields        = array_map(
			function ( $field ) use ( $field_aliases ) {
				return $field_aliases[ $field ] ?? $field;
			},
			$fields
		);
		$valid         = self::valid_field_keys( $fields );
		if ( count( $valid ) !== count( $fields ) ) {
			return new \WP_Error( 'eowc_unknown_field', 'One or more requested fields are not available in this edition.' );
		}
		if ( empty( $fields ) && $require_fields ) {
			return new \WP_Error( 'eowc_missing_fields', 'At least one export field is required.' );
		}
		$format  = sanitize_key( $format );
		$formats = array_column( self::get_formats()['formats'], 'id' );
		if ( ! in_array( $format, $formats, true ) ) {
			return new \WP_Error( 'eowc_unknown_format', 'The requested export format is not enabled.' );
		}
		$status         = isset( $filters['statuses'] ) ? $filters['statuses'] : ( $filters['eowc_status'] ?? array() );
		$status         = is_array( $status ) ? array_values( array_unique( array_map( 'sanitize_key', $status ) ) ) : array();
		$known_statuses = array_map( 'sanitize_key', array_keys( wc_get_order_statuses() ) );
		$known_statuses = array_map(
			function ( $value ) {
				return preg_replace( '/^wc-/', '', $value );
			},
			$known_statuses
		);
		$status         = array_map(
			function ( $value ) {
				return preg_replace( '/^wc-/', '', $value );
			},
			$status
		);
		if ( array_diff( $status, $known_statuses ) ) {
			return new \WP_Error( 'eowc_unknown_status', 'One or more requested order statuses are not registered.' );
		}
		$date_from = $filters['date_from'] ?? ( $filters['eowc_date_from'] ?? '' );
		$date_to   = $filters['date_to'] ?? ( $filters['eowc_date_to'] ?? '' );
		$date_from = self::normalize_date_bound( $date_from, false );
		$date_to   = self::normalize_date_bound( $date_to, true );
		if ( is_wp_error( $date_from ) ) {
			return $date_from;
		}
		if ( is_wp_error( $date_to ) ) {
			return $date_to;
		}
		if ( $date_from && $date_to && $date_from > $date_to ) {
			return new \WP_Error( 'eowc_invalid_date_range', 'The export start date must be before the end date.' );
		}
		$input['eowc_columns']       = $fields;
		$input['eowc_export_format'] = $format;
		$input['eowc_status']        = $status;
		$input['eowc_date_from']     = $date_from;
		$input['eowc_date_to']       = $date_to;
		return $input;
	}

	/**
	 * Normalize an ISO or date-only bound into the store timezone.
	 *
	 * @param string $value Date value.
	 * @param bool   $end Whether this is an inclusive end bound.
	 * @return string|\WP_Error
	 */
	private static function normalize_date_bound( $value, $end ) {
		if ( '' === $value || null === $value ) {
			return '';
		}
		try {
			$raw  = sanitize_text_field( $value );
			$date = new \DateTimeImmutable( $raw, new \DateTimeZone( 'UTC' ) );
			$date = $date->setTimezone( wp_timezone() );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
				return $date->format( 'Y-m-d' ) . ( $end ? ' 23:59:59' : ' 00:00:00' );
			}
			return $date->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $exception ) {
			return new \WP_Error( 'eowc_invalid_date', 'Dates must use YYYY-MM-DD or ISO-8601 format.' );
		}
	}

	/**
	 * Return metadata for read-only abilities.
	 *
	 * @return array
	 */
	private static function read_only_meta(): array {
		return array(
			'public'       => true,
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		);
	}

	/**
	 * Return metadata for file-creating abilities.
	 *
	 * @return array
	 */
	private static function write_meta(): array {
		return array(
			'public'       => true,
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			),
		);
	}

	/**
	 * Build shared filter schema.
	 *
	 * @param bool $for_generate Whether fields are required for generation.
	 * @return array
	 */
	private static function export_filter_schema( $for_generate ): array {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'filters'            => array( 'type' => 'object' ),
				'fields'             => array(
					'type'     => 'array',
					'items'    => array( 'type' => 'string' ),
					'minItems' => 1,
				),
				'field_order'        => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'format'             => array(
					'type' => 'string',
					'enum' => array( 'csv', 'json', 'xlsx', 'pdf', 'xml' ),
				),
				'idempotency_key'    => array(
					'type'      => 'string',
					'minLength' => 1,
				),
				'file_options'       => array( 'type' => 'object' ),
				'eowc_status'        => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'eowc_date_from'     => array(
					'type'   => 'string',
					'format' => 'date',
				),
				'eowc_date_to'       => array(
					'type'   => 'string',
					'format' => 'date',
				),
				'eowc_export_format' => array(
					'type' => 'string',
					'enum' => array( 'csv', 'json', 'xlsx', 'pdf', 'xml' ),
				),
				'eowc_columns'       => array(
					'type'     => 'array',
					'items'    => array( 'type' => 'string' ),
					'minItems' => 1,
				),
				'offset'             => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'job_id'             => array( 'type' => 'string' ),
			),
		);
		if ( $for_generate ) {
			$schema['required'] = array( 'fields', 'format', 'idempotency_key' );
		}
		return $schema;
	}

	/**
	 * Build the query input schema.
	 *
	 * @return array
	 */
	private static function query_schema(): array {
		$schema                               = self::export_filter_schema( false );
		$schema['properties']['limit']        = array(
			'type'    => 'integer',
			'minimum' => 1,
			'maximum' => 100,
		);
		$schema['properties']['sample_limit'] = array(
			'type'    => 'integer',
			'minimum' => 0,
			'maximum' => 20,
		);
		$schema['properties']['aggregate']    = array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		);
		return $schema;
	}

	/**
	 * Schema for query responses.
	 *
	 * @return array
	 */
	private static function query_output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'total'             => array( 'type' => 'integer' ),
				'sample_size'       => array( 'type' => 'integer' ),
				'fields'            => array( 'type' => 'array' ),
				'rows'              => array( 'type' => 'array' ),
				'aggregates'        => array( 'type' => 'object' ),
				'interpreted_range' => array( 'type' => 'object' ),
			),
		);
	}

	/**
	 * Schema for generation responses.
	 *
	 * @return array
	 */
	private static function job_output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'export_id'        => array( 'type' => 'string' ),
				'status'           => array(
					'type' => 'string',
					'enum' => array( 'queued', 'processing', 'completed', 'failed', 'cancelled', 'expired' ),
				),
				'file_name'        => array( 'type' => 'string' ),
				'matched_records'  => array( 'type' => 'integer' ),
				'processed'        => array( 'type' => 'integer' ),
				'total'            => array( 'type' => 'integer' ),
				'progress_percent' => array( 'type' => 'number' ),
				'download'         => array(),
				'error_code'       => array( 'type' => array( 'string', 'null' ) ),
				'created_at'       => array( 'type' => 'string' ),
				'updated_at'       => array( 'type' => 'string' ),
			),
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
			'eowc/get-fields',
			array(
				'label'               => __( 'Get Export Fields', 'woocommerce-export-orders' ),
				'description'         => __( 'Lists the registered export fields and their metadata.', 'woocommerce-export-orders' ),
				'category'            => 'export-orders',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'group'              => array( 'type' => 'string' ),
						'search'             => array( 'type' => 'string' ),
						'include_pro_locked' => array( 'type' => 'boolean' ),
						'context'            => array( 'type' => 'string' ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'fields'         => array( 'type' => 'array' ),
						'schema_version' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_fields' ),
				'permission_callback' => array( __CLASS__, 'can_read_exports' ),
				'meta'                => self::read_only_meta(),
			)
		);

		wp_register_ability(
			'eowc/get-formats',
			array(
				'label'               => __( 'Get Export Formats', 'woocommerce-export-orders' ),
				'description'         => __( 'Lists enabled export formats and their constraints.', 'woocommerce-export-orders' ),
				'category'            => 'export-orders',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'context' => array( 'type' => 'string' ) ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'formats'        => array( 'type' => 'array' ),
						'default'        => array( 'type' => 'string' ),
						'max_batch_size' => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_formats' ),
				'permission_callback' => array( __CLASS__, 'can_read_exports' ),
				'meta'                => self::read_only_meta(),
			)
		);

		wp_register_ability(
			'eowc/query',
			array(
				'label'               => __( 'Preview Order Export', 'woocommerce-export-orders' ),
				'description'         => __( 'Previews matching orders with an aggregate count and up to 20 sample rows. It never creates a file.', 'woocommerce-export-orders' ),
				'category'            => 'export-orders',
				'input_schema'        => self::export_filter_schema( false ),
				'output_schema'       => self::query_output_schema(),
				'execute_callback'    => array( __CLASS__, 'query_exports' ),
				'permission_callback' => array( __CLASS__, 'can_read_exports' ),
				'meta'                => self::read_only_meta(),
			)
		);

		wp_register_ability(
			'eowc/generate-export',
			array(
				'label'               => __( 'Generate Order Export', 'woocommerce-export-orders' ),
				'description'         => __( 'Creates a validated export job. Large jobs are queued and processed asynchronously.', 'woocommerce-export-orders' ),
				'category'            => 'export-orders',
				'input_schema'        => self::export_filter_schema( true ),
				'output_schema'       => self::job_output_schema(),
				'execute_callback'    => array( __CLASS__, 'generate_export' ),
				'permission_callback' => array( __CLASS__, 'can_create_exports' ),
				'meta'                => self::write_meta(),
			)
		);

		wp_register_ability(
			'eowc/get-export-status',
			array(
				'label'               => __( 'Get Export Status', 'woocommerce-export-orders' ),
				'description'         => __( 'Returns progress and the secure result reference for an export job owned by the current user.', 'woocommerce-export-orders' ),
				'category'            => 'export-orders',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'export_id' => array(
							'type'      => 'string',
							'minLength' => 1,
						),
					),
					'required'   => array( 'export_id' ),
				),
				'output_schema'       => self::job_output_schema(),
				'execute_callback'    => array( __CLASS__, 'get_export_status' ),
				'permission_callback' => array( __CLASS__, 'can_read_exports' ),
				'meta'                => self::read_only_meta(),
			)
		);

		wp_register_ability(
			'eowc/export-orders',
			array(
				'label'               => __( 'Export WooCommerce Orders', 'woocommerce-export-orders' ),
				'description'         => __( 'Exports all matching WooCommerce orders using date, status, format, and column filters. Large exports are processed internally in batches and return one complete file.', 'woocommerce-export-orders' ),
				'category'            => 'export-orders',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'eowc_status'        => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'eowc_date_from'     => array(
							'type'   => 'string',
							'format' => 'date',
						),
						'eowc_date_to'       => array(
							'type'   => 'string',
							'format' => 'date',
						),
						'eowc_export_format' => array(
							'type' => 'string',
							'enum' => array( 'csv', 'json', 'xlsx', 'pdf', 'xml' ),
						),
						'eowc_columns'       => array(
							'type'     => 'array',
							'items'    => array( 'type' => 'string' ),
							'minItems' => 1,
						),
						'offset'             => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'export_id'          => array( 'type' => 'string' ),
					),
					'required'   => array( 'eowc_date_from', 'eowc_date_to', 'eowc_columns' ),
				),
				'output_schema'       => array(
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
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
					),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'eowc/ai-export-orders',
			array(
				'label'               => __( 'AI Export Assistant', 'woocommerce-export-orders' ),
				'description'         => __( 'Turns a merchant export request into a validated WooCommerce order export. Use clear dates, an optional format, statuses, and field names.', 'woocommerce-export-orders' ),
				'category'            => 'export-orders',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'request'   => array(
							'type'        => 'string',
							'description' => __( 'Example: Export completed orders from 2026-01-01 to 2026-01-31 as CSV with order ID, customer email, and total.', 'woocommerce-export-orders' ),
							'minLength'   => 10,
						),
						'offset'    => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'export_id' => array( 'type' => 'string' ),
					),
					'required'   => array( 'request' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'interpretation' => array( 'type' => 'object' ),
						'result'         => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'execute_ai_export_orders' ),
				'permission_callback' => array( __CLASS__, 'can_export_orders' ),
				'meta'                => array(
					'public'       => true,
					'mcp'          => array(
						'public' => true,
					),
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
	// phpcs:ignore
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
		$input              = is_array( $input ) ? $input : array();
		$input['offset']    = absint( $input['offset'] ?? 0 );
		$input['export_id'] = isset( $input['export_id'] ) ? sanitize_file_name( $input['export_id'] ) : 'eowc_' . wp_generate_uuid4();
		$total              = 0;
		$result             = array();
		$batch_count        = 0;

		while ( true ) {
			++$batch_count;
			if ( $batch_count > 10000 ) {
				return new \WP_Error( 'eowc_batch_limit_exceeded', 'The export exceeded the maximum number of batches.' );
			}

			$result = EOWC_Admin::process_export_batch( $input );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$total = (int) $result['total'];
			if ( $result['done'] ) {
				$result['processed'] = $total;
				$result['total']     = $total;
				return $result;
			}

			$input['offset'] = (int) $result['next_offset'];
		}
	}

	/**
	 * Convert a merchant request into a validated export batch.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute_ai_export_orders( $input ) {
		$request = isset( $input['request'] ) ? sanitize_text_field( $input['request'] ) : '';
		$config  = self::interpret_request( $request );

		if ( is_wp_error( $config ) ) {
			return $config;
		}

		$export_input = array_merge(
			$config,
			array(
				'offset'    => absint( $input['offset'] ?? 0 ),
				'export_id' => isset( $input['export_id'] ) ? sanitize_file_name( $input['export_id'] ) : '',
			)
		);
		$result       = EOWC_Admin::process_export_batch( $export_input );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'interpretation' => $config,
			'result'         => $result,
		);
	}

	/**
	 * Interpret a request without starting an export.
	 *
	 * @param string $request Merchant export request.
	 * @return array|\WP_Error
	 */
	public static function suggest_export( $request ) {
		return self::interpret_request( sanitize_text_field( $request ) );
	}

	/**
	 * Parse the deliberately small, deterministic assistant vocabulary.
	 *
	 * @param string $request Merchant export request.
	 * @return array|\WP_Error
	 */
	private static function interpret_request( $request ) {
		$date_range = self::resolve_date_range( $request );
		if ( is_wp_error( $date_range ) ) {
			return $date_range;
		}

		$date_from = $date_range['from'];
		$date_to   = $date_range['to'];
		if ( $date_from > $date_to ) {
			return new \WP_Error( 'eowc_ai_invalid_date_range', 'The export start date must be on or before the end date.' );
		}

		$format = 'csv';
		if ( preg_match( '/\b(csv|json|xlsx|pdf|xml)\b/i', $request, $format_match ) ) {
			$format = strtolower( $format_match[1] );
		}

		$status     = array();
		$status_map = array(
			'pending'    => 'pending',
			'processing' => 'processing',
			'on-hold'    => 'on-hold',
			'on hold'    => 'on-hold',
			'completed'  => 'completed',
			'cancelled'  => 'cancelled',
			'canceled'   => 'cancelled',
			'failed'     => 'failed',
			'refunded'   => 'refunded',
		);
		foreach ( $status_map as $label => $value ) {
			if ( preg_match( '/\b' . preg_quote( $label, '/' ) . '\b/i', $request ) ) {
				$status[] = $value;
			}
		}

		$column_map = array(
			'order id'         => 'order_id',
			'order status'     => 'order_status',
			'order date'       => 'order_date',
			'date'             => 'order_date',
			'total'            => 'order_total',
			'order total'      => 'order_total',
			'subtotal'         => 'order_subtotal',
			'discount'         => 'order_discount',
			'tax'              => 'order_tax',
			'shipping total'   => 'shipping_total',
			'billing country'  => 'billing_country',
			'payment method'   => 'payment_method',
			'email'            => 'customer_email',
			'customer email'   => 'customer_email',
			'phone'            => 'customer_phone',
			'customer phone'   => 'customer_phone',
			'billing address'  => 'billing_address_1',
			'shipping address' => 'shipping_address_1',
			'product names'    => 'product_names',
			'product skus'     => 'product_skus',
			'product sku'      => 'product_skus',
			'sku'              => 'product_skus',
			'quantities'       => 'product_quantities',
			'quantity'         => 'product_quantities',
		);
		$columns    = array();
		foreach ( $column_map as $label => $value ) {
			if ( preg_match( '/\b' . preg_quote( $label, '/' ) . '\b/i', $request ) && ! in_array( $value, $columns, true ) ) {
				$columns[] = $value;
			}
		}

		if ( empty( $columns ) ) {
			$columns = array( 'order_id', 'order_status', 'order_date', 'order_total' );
		}

		return array(
			'eowc_status'        => $status,
			'eowc_date_from'     => $date_from,
			'eowc_date_to'       => $date_to,
			'eowc_export_format' => $format,
			'eowc_columns'       => $columns,
		);
	}

	/**
	 * Resolve explicit ISO dates and supported relative date phrases.
	 *
	 * Relative dates use the site's configured WordPress timezone.
	 *
	 * @param string $request Merchant export request.
	 * @return array|\WP_Error
	 */
	private static function resolve_date_range( $request ) {
		if ( preg_match( '/(\d{4}-\d{2}-\d{2}).*?(\d{4}-\d{2}-\d{2})/', $request, $dates ) ) {
			return array(
				'from' => $dates[1],
				'to'   => $dates[2],
			);
		}

		$month_words   = array(
			'one'    => 1,
			'two'    => 2,
			'three'  => 3,
			'four'   => 4,
			'five'   => 5,
			'six'    => 6,
			'seven'  => 7,
			'eight'  => 8,
			'nine'   => 9,
			'ten'    => 10,
			'eleven' => 11,
			'twelve' => 12,
		);
		$month_pattern = implode( '|', array_keys( $month_words ) );

		if ( preg_match( '/\b(?:last|past)\s+(?:(\d{1,2})|(' . $month_pattern . '))\s+months?\b/i', $request, $month_match ) ) {
			$month_count = ! empty( $month_match[1] ) ? absint( $month_match[1] ) : $month_words[ strtolower( $month_match[2] ) ];
			if ( $month_count < 1 || $month_count > 12 ) {
				return new \WP_Error( 'eowc_ai_invalid_month_count', 'Please request between 1 and 12 complete months.' );
			}

			return self::get_previous_months_range( $month_count );
		}

		if ( preg_match( '/\b(?:last|past)\s+month\b/i', $request ) ) {
			return self::get_previous_months_range( 1 );
		}

		if ( preg_match( '/\b(?:previous|last|past)\s+week\b/i', $request ) ) {
			return self::get_previous_week_range();
		}

		if ( preg_match( '/\byesterday\b/i', $request ) ) {
			return self::get_yesterday_range();
		}

		if ( preg_match( '/\bthis\s+quarter\b/i', $request ) ) {
			return self::get_current_quarter_range();
		}

		return new \WP_Error( 'eowc_ai_missing_dates', 'Please provide a date range such as "yesterday", "this quarter", "previous week", "last month", or two dates in YYYY-MM-DD format.' );
	}

	/**
	 * Get complete calendar months before the current month.
	 *
	 * @param int $month_count Number of months to include.
	 * @return array
	 */
	private static function get_previous_months_range( $month_count ) {
		$today         = new \DateTimeImmutable( 'now', wp_timezone() );
		$current_month = $today->modify( 'first day of this month' );
		$date_from     = $current_month->modify( '-' . $month_count . ' months' );
		$date_to       = $current_month->modify( '-1 day' );

		return array(
			'from' => $date_from->format( 'Y-m-01' ),
			'to'   => $date_to->format( 'Y-m-d' ),
		);
	}

	/**
	 * Get the previous complete calendar week in the site timezone.
	 *
	 * @return array
	 */
	private static function get_previous_week_range() {
		$today        = new \DateTimeImmutable( 'now', wp_timezone() );
		$current_week = $today->modify( 'monday this week' );
		$date_from    = $current_week->modify( '-7 days' );
		$date_to      = $current_week->modify( '-1 day' );

		return array(
			'from' => $date_from->format( 'Y-m-d' ),
			'to'   => $date_to->format( 'Y-m-d' ),
		);
	}

	/**
	 * Get yesterday in the site timezone.
	 *
	 * @return array
	 */
	private static function get_yesterday_range() {
		$yesterday = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->modify( '-1 day' )->format( 'Y-m-d' );

		return array(
			'from' => $yesterday,
			'to'   => $yesterday,
		);
	}

	/**
	 * Get the current calendar quarter in the site timezone.
	 *
	 * @return array
	 */
	private static function get_current_quarter_range() {
		$today         = new \DateTimeImmutable( 'now', wp_timezone() );
		$current_month = (int) $today->format( 'n' );
		$quarter_start = (int) ( floor( ( $current_month - 1 ) / 3 ) * 3 ) + 1;
		$date_from     = $today->setDate( (int) $today->format( 'Y' ), $quarter_start, 1 );
		$date_to       = $date_from->modify( '+3 months -1 day' );

		return array(
			'from' => $date_from->format( 'Y-m-d' ),
			'to'   => $date_to->format( 'Y-m-d' ),
		);
	}

	/**
	 * Describe the fields available to exports.
	 *
	 * @return array
	 */
	private static function field_metadata(): array {
		$fields   = array(
			'order_id'            => 'Order ID',
			'order_status'        => 'Status',
			'order_date'          => 'Order Date',
			'order_total'         => 'Order Total',
			'order_subtotal'      => 'Subtotal',
			'order_discount'      => 'Discount',
			'order_tax'           => 'Tax',
			'shipping_total'      => 'Shipping Total',
			'payment_method'      => 'Payment Method',
			'transaction_id'      => 'Transaction ID',
			'customer_note'       => 'Customer Note',
			'coupon_codes'        => 'Coupon Codes',
			'customer_id'         => 'Customer ID',
			'customer_email'      => 'Email',
			'billing_email'       => 'Billing email',
			'customer_phone'      => 'Phone',
			'billing_first_name'  => 'First Name (Billing)',
			'billing_last_name'   => 'Last Name (Billing)',
			'billing_company'     => 'Company (Billing)',
			'billing_address_1'   => 'Address 1 (Billing)',
			'billing_address_2'   => 'Address 2 (Billing)',
			'billing_city'        => 'City (Billing)',
			'billing_state'       => 'State (Billing)',
			'billing_postcode'    => 'Postcode (Billing)',
			'billing_country'     => 'Country (Billing)',
			'shipping_first_name' => 'First Name (Shipping)',
			'shipping_last_name'  => 'Last Name (Shipping)',
			'shipping_company'    => 'Company (Shipping)',
			'shipping_address_1'  => 'Address 1 (Shipping)',
			'shipping_address_2'  => 'Address 2 (Shipping)',
			'shipping_city'       => 'City (Shipping)',
			'shipping_state'      => 'State (Shipping)',
			'shipping_postcode'   => 'Postcode (Shipping)',
			'shipping_country'    => 'Country (Shipping)',
			'shipping_method'     => 'Shipping Method',
			'product_names'       => 'Product Names',
			'product_skus'        => 'Product SKUs',
			'product_sku'         => 'Product SKU',
			'product_quantities'  => 'Quantities',
			'quantity'            => 'Quantity',
			'product_totals'      => 'Line Totals',
		);
		$metadata = array();
		foreach ( $fields as $key => $label ) {
			$group       = ( 0 === strpos( $key, 'billing_' ) || 'billing_email' === $key ) ? 'customer' : ( 0 === strpos( $key, 'shipping_' ) ? 'shipping' : ( 0 === strpos( $key, 'product_' ) || 'quantity' === $key ? 'product' : ( false !== strpos( $key, 'customer' ) ? 'customer' : 'order' ) ) );
			$type        = ( 'order_id' === $key || 'customer_id' === $key ) ? 'integer' : ( in_array( $key, array( 'order_total', 'order_subtotal', 'order_discount', 'order_tax', 'shipping_total', 'product_totals' ), true ) ? 'number' : ( 'quantity' === $key ? 'number' : 'string' ) );
			$sensitivity = ( false !== strpos( $key, 'email' ) || false !== strpos( $key, 'phone' ) || false !== strpos( $key, 'address' ) || false !== strpos( $key, 'name' ) ) ? 'personal' : 'low';
			$metadata[]  = array(
				'key'           => $key,
				'label'         => $label,
				'group'         => $group,
				'type'          => $type,
				'edition'       => 'free',
				'sensitivity'   => $sensitivity,
				'repeatability' => 'per_order',
				'availability'  => 'enabled',
			);
		}
		return $metadata;
	}

	/**
	 * Keep only known field keys.
	 *
	 * @param array $fields Requested fields.
	 * @return array
	 */
	private static function valid_field_keys( array $fields ): array {
		$valid = array_column( self::field_metadata(), 'key' );
		return array_values( array_unique( array_intersect( $fields, $valid ) ) );
	}

	/**
	 * Build the WooCommerce order query for a preview.
	 *
	 * @param array $input Query input.
	 * @param int   $limit Maximum result count.
	 * @return array
	 */
	private static function order_query_args( array $input, $limit ): array {
		$status = isset( $input['eowc_status'] ) && is_array( $input['eowc_status'] ) ? array_map( 'sanitize_text_field', $input['eowc_status'] ) : array();
		$limit  = -1 === (int) $limit ? -1 : min( 100, max( 1, absint( $limit ) ) );
		return array(
			'limit'        => $limit,
			'status'       => $status,
			'date_created' => ( ! empty( $input['eowc_date_from'] ) && ! empty( $input['eowc_date_to'] ) ) ? sanitize_text_field( $input['eowc_date_from'] ) . '...' . sanitize_text_field( $input['eowc_date_to'] ) : '',
		);
	}

	/**
	 * Count matching orders.
	 *
	 * @param array $input Normalized filters.
	 * @return int|\WP_Error
	 */
	private static function count_matching_orders( array $input ) {
		$args             = self::order_query_args( $input, 1 );
		$args['paginate'] = true;
		$args['return']   = 'ids';
		$result           = wc_get_orders( $args );
		return (int) $result->total;
	}

	/**
	 * Return deterministic aggregate values over all matching orders.
	 * Totals include refunds as stored by WooCommerce and include tax and shipping separately.
	 *
	 * @param array $input Normalized filters.
	 * @param int   $total Matching count.
	 * @return array
	 */
	private static function aggregate_orders( array $input, $total ) {
		$aggregate = array( 'count' => (int) $total );
		$requested = isset( $input['aggregate'] ) && is_array( $input['aggregate'] ) ? array_map( 'sanitize_key', $input['aggregate'] ) : array();
		if ( empty( $requested ) ) {
			return $aggregate;
		}
		$args             = self::order_query_args( $input, -1 );
		$args['paginate'] = false;
		$args['return']   = 'ids';
		$ids              = wc_get_orders( $args );
		$sums             = array(
			'order_total'    => 0,
			'order_subtotal' => 0,
			'order_discount' => 0,
			'order_tax'      => 0,
			'shipping_total' => 0,
		);
		foreach ( $ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			$sums['order_total']    += (float) $order->get_total();
			$sums['order_subtotal'] += (float) $order->get_subtotal();
			$sums['order_discount'] += (float) $order->get_discount_total();
			$sums['order_tax']      += (float) $order->get_total_tax();
			$sums['shipping_total'] += (float) $order->get_shipping_total();
		}
		foreach ( $requested as $key ) {
			if ( isset( $sums[ $key ] ) ) {
				$aggregate[ $key . '_sum' ] = round( $sums[ $key ], 2 );
			}
		}
		return $aggregate;
	}

	/**
	 * Read a field value from an order for query samples.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $field Field key.
	 * @return string|int|float
	 */
	private static function get_order_field_value( $order, $field ) {
		$values           = array(
			'order_id'            => $order->get_id(),
			'order_status'        => wc_get_order_status_name( $order->get_status() ),
			'order_date'          => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
			'order_total'         => wc_format_decimal( $order->get_total(), 2 ),
			'order_subtotal'      => wc_format_decimal( $order->get_subtotal(), 2 ),
			'order_discount'      => wc_format_decimal( $order->get_discount_total(), 2 ),
			'order_tax'           => wc_format_decimal( $order->get_total_tax(), 2 ),
			'shipping_total'      => wc_format_decimal( $order->get_shipping_total(), 2 ),
			'payment_method'      => $order->get_payment_method_title(),
			'transaction_id'      => $order->get_transaction_id(),
			'customer_note'       => $order->get_customer_note(),
			'coupon_codes'        => implode( ', ', $order->get_coupon_codes() ),
			'customer_id'         => $order->get_customer_id(),
			'customer_email'      => $order->get_billing_email(),
			'customer_phone'      => $order->get_billing_phone(),
			'billing_first_name'  => $order->get_billing_first_name(),
			'billing_last_name'   => $order->get_billing_last_name(),
			'billing_company'     => $order->get_billing_company(),
			'billing_address_1'   => $order->get_billing_address_1(),
			'billing_address_2'   => $order->get_billing_address_2(),
			'billing_city'        => $order->get_billing_city(),
			'billing_state'       => $order->get_billing_state(),
			'billing_postcode'    => $order->get_billing_postcode(),
			'billing_country'     => $order->get_billing_country(),
			'shipping_first_name' => $order->get_shipping_first_name(),
			'shipping_last_name'  => $order->get_shipping_last_name(),
			'shipping_company'    => $order->get_shipping_company(),
			'shipping_address_1'  => $order->get_shipping_address_1(),
			'shipping_address_2'  => $order->get_shipping_address_2(),
			'shipping_city'       => $order->get_shipping_city(),
			'shipping_state'      => $order->get_shipping_state(),
			'shipping_postcode'   => $order->get_shipping_postcode(),
			'shipping_country'    => $order->get_shipping_country(),
			'shipping_method'     => '',
		);
		$shipping_methods = array();
		foreach ( $order->get_shipping_methods() as $shipping_item ) {
			$shipping_methods[] = $shipping_item->get_name();
		}
		$values['shipping_method'] = implode( ', ', $shipping_methods );
		if ( in_array( $field, array( 'product_names', 'product_skus', 'product_quantities', 'product_totals' ), true ) ) {
			$values[ $field ] = array();
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$product            = $item->get_product();
				$values[ $field ][] = 'product_names' === $field ? $item->get_name() : ( 'product_skus' === $field ? ( $product ? $product->get_sku() : '' ) : ( 'product_quantities' === $field ? $item->get_quantity() : wc_format_decimal( $item->get_total(), 2 ) ) );
			}
			$values[ $field ] = implode( ', ', $values[ $field ] );
		}
		return $values[ $field ] ?? '';
	}

	/**
	 * Build a per-user job transient key.
	 *
	 * @param string $job_id Job identifier.
	 * @param string $user_id User Id.
	 * @return string
	 */
	private static function job_key( $job_id, $user_id = null ): string {
		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
		return 'eowc_export_job_' . $user_id . '_' . sanitize_key( $job_id );
	}

	/**
	 * Build the idempotency key for the current user.
	 *
	 * @param string $key     Client key.
	 * @param string $user_id User Id.
	 * @return string
	 */
	private static function idempotency_key( $key, $user_id = null ): string {
		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
		return 'eowc_export_idem_' . $user_id . '_' . md5( $key );
	}

	/**
	 * Get configured file retention.
	 *
	 * @return int
	 */
	private static function retention_seconds(): int {
		return max( HOUR_IN_SECONDS, (int) apply_filters( 'eowc_export_retention_seconds', DAY_IN_SECONDS ) );
	}
}
