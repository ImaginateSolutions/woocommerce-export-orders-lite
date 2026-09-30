<?php
/**
 * Native MCP server factory for Export Orders.
 *
 * Creates the EOWC MCP server definition used by the native STDIO
 * implementation. This does not require the WordPress MCP Adapter plugin.
 *
 * @package Export_Orders_For_WooCommerce
 */

namespace EOWC\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Factory for creating the native Export Orders MCP server.
 *
 * This is intentionally independent of the MCP Adapter plugin.
 *
 * The factory is responsible for defining:
 * - Server metadata.
 * - MCP tools.
 * - Tool input schemas.
 * - Tool callbacks.
 *
 * The STDIO transport/JSON-RPC loop remains in EOWC_MCP_CLI.
 */
class EOWC_MCP_Server_Factory {

	/**
	 * Create the default EOWC MCP server definition.
	 *
	 * The returned structure is consumed by EOWC_MCP_CLI.
	 *
	 * @return array
	 */
	public static function create_default_server(): array {
		return array(
			'id'               => 'eowc-export-orders',
			'name'             => 'Export Orders MCP Server',
			'description'      => 'MCP tools for discovering, previewing, and generating WooCommerce order exports.',
			'version'          => defined( 'EOWC_VERSION' ) ? EOWC_VERSION : '1.0.0',
			'protocol_version' => '2025-11-25',
			'capabilities'     => array(
				'tools' => array(),
			),
			'tools'            => self::create_tools(),
		);
	}

	/**
	 * Create the MCP tool definitions.
	 *
	 * Tool names intentionally use hyphens because they are MCP-safe names.
	 *
	 * @param bool $oauth Oauth.
	 *
	 * @return array
	 */
	public static function create_tools( bool $oauth = false ): array {
		$tools = array(
			self::create_tool(
				'eowc-get-fields',
				'Get the available WooCommerce order export fields.',
				self::get_fields_schema()
			),
			self::create_tool(
				'eowc-get-formats',
				'Get the available WooCommerce order export formats.',
				self::empty_schema()
			),
			self::create_tool(
				'eowc-query',
				'Query WooCommerce orders or export data using the Export Orders plugin.',
				self::query_schema()
			),
			self::create_tool(
				'eowc-generate-export',
				'Generate a WooCommerce order export.',
				self::generate_export_schema()
			),
			self::create_tool(
				'eowc-get-export-status',
				'Get the status of a WooCommerce order export.',
				self::export_status_schema()
			),
			self::create_tool(
				'eowc-export-orders',
				'Export WooCommerce orders using the Export Orders plugin.',
				self::export_orders_schema()
			),
			self::create_tool(
				'eowc-ai-export-orders',
				'Create an AI-friendly WooCommerce order export.',
				self::ai_export_orders_schema()
			),
		);
		if ( $oauth ) {
			foreach ( $tools as &$tool ) {
				$tool['securitySchemes'] = array(
					array(
						'type'   => 'oauth2',
						'scopes' => array( EOWC_OAuth::SCOPE ),
					),
				);
			}
			unset( $tool );
		}
		return $tools;
	}

	/**
	 * Return tool callback mappings.
	 *
	 * The CLI server can use this map when processing tools/call.
	 *
	 * @return array
	 */
	public static function get_tool_callbacks(): array {
		return array(
			'eowc-get-fields'        => 'get_fields',
			'eowc-get-formats'       => 'get_formats',
			'eowc-query'             => 'query_exports',
			'eowc-generate-export'   => 'generate_export',
			'eowc-get-export-status' => 'get_export_status',
			'eowc-export-orders'     => 'execute_export_orders',
			'eowc-ai-export-orders'  => 'execute_ai_export_orders',
		);
	}

	/**
	 * Create one MCP tool definition.
	 *
	 * @param string $name        Tool name.
	 * @param string $description Tool description.
	 * @param array  $schema      JSON Schema.
	 * @return array
	 */
	private static function create_tool( string $name, string $description, array $schema ): array {
		return array(
			'name'        => $name,
			'description' => $description,
			'inputSchema' => $schema,
		);
	}

	/**
	 * Empty MCP input schema.
	 *
	 * @return array
	 */
	private static function empty_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
		);
	}

	/**
	 * Fields tool schema.
	 *
	 * @return array
	 */
	private static function get_fields_schema(): array {
		return self::empty_schema();
	}

	/**
	 * Query schema.
	 *
	 * Keep this intentionally permissive until the final eowc/query
	 * ability contract is fixed.
	 *
	 * @return array
	 */
	private static function query_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'date_from' => array(
					'type'   => 'string',
					'format' => 'date',
				),
				'date_to'   => array(
					'type'   => 'string',
					'format' => 'date',
				),
				'status'    => array(
					'type'  => 'array',
					'items' => array(
						'type' => 'string',
					),
				),
			),
			'additionalProperties' => true,
		);
	}

	/**
	 * Generate-export schema.
	 *
	 * @return array
	 */
	private static function generate_export_schema(): array {
		return self::export_orders_schema();
	}

	/**
	 * Export-status schema.
	 *
	 * @return array
	 */
	private static function export_status_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'export_id' => array(
					'type' => 'string',
				),
			),
			'required'             => array( 'export_id' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Existing batch export schema.
	 *
	 * This mirrors the current EOWC export ability contract.
	 *
	 * @return array
	 */
	private static function export_orders_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'eowc_status'        => array(
					'type'  => 'array',
					'items' => array(
						'type' => 'string',
					),
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
					'enum' => array(
						'csv',
						'json',
						'xlsx',
						'pdf',
						'xml',
					),
				),
				'eowc_columns'       => array(
					'type'     => 'array',
					'minItems' => 1,
					'items'    => array(
						'type' => 'string',
					),
				),
				'offset'             => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'export_id'          => array(
					'type' => 'string',
				),
			),
			'required'             => array(
				'eowc_date_from',
				'eowc_date_to',
				'eowc_columns',
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * AI export schema.
	 *
	 * Keep this aligned with the actual eowc/ai-export-orders ability
	 * when that ability contract is finalized.
	 *
	 * @return array
	 */
	private static function ai_export_orders_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'date_from' => array(
					'type'   => 'string',
					'format' => 'date',
				),
				'date_to'   => array(
					'type'   => 'string',
					'format' => 'date',
				),
				'status'    => array(
					'type'  => 'array',
					'items' => array(
						'type' => 'string',
					),
				),
				'format'    => array(
					'type' => 'string',
					'enum' => array(
						'csv',
						'json',
						'xlsx',
						'pdf',
						'xml',
					),
				),
				'columns'   => array(
					'type'     => 'array',
					'items'    => array(
						'type' => 'string',
					),
					'minItems' => 1,
				),
			),
			'required'             => array(
				'date_from',
				'date_to',
			),
			'additionalProperties' => true,
		);
	}

	/**
	 * Check whether a requested tool exists.
	 *
	 * @param string $tool_name Tool name.
	 * @return bool
	 */
	public static function has_tool( string $tool_name ): bool {
		return isset( self::get_tool_callbacks()[ $tool_name ] );
	}

	/**
	 * Get the callback for a tool.
	 *
	 * @param string $tool_name Tool name.
	 * @return string|null
	 */
	public static function get_tool_callback( string $tool_name ): ?string {
		$callbacks = self::get_tool_callbacks();

		return $callbacks[ $tool_name ] ?? null;
	}
}
