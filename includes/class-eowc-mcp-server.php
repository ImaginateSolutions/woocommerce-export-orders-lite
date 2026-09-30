<?php
/**
 * Native MCP server implementation.
 *
 * @package Export_Orders_For_WooCommerce
 */

namespace EOWC\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Class EOWC_MCP_Server
 */
class EOWC_MCP_Server {

	/**
	 * Handle one MCP JSON-RPC request.
	 *
	 * Used by both STDIO and HTTP transports.
	 *
	 * @param array $request MCP JSON-RPC request.
	 * @return array|null
	 */
	public static function handle_request( array $request ) {

		$id     = $request['id'] ?? null;
		$method = isset( $request['method'] ) ? (string) $request['method'] : '';
		$params = isset( $request['params'] ) && is_array( $request['params'] )
			? $request['params']
			: array();

		/*
		 * Notifications do not receive a response.
		 */
		if ( 0 === strpos( $method, 'notifications/' ) ) {
			return null;
		}

		switch ( $method ) {

			case 'initialize':
				return self::result(
					$id,
					array(
						'protocolVersion' => '2025-11-25',
						'capabilities'    => array(
							'tools' => (object) array(),
						),
						'serverInfo'      => array(
							'name'    => 'eowc-export-orders',
							'version' => EOWC_VERSION,
						),
					)
				);

			case 'tools/list':
				return self::result(
					$id,
					array(
						'tools' => EOWC_MCP_Server_Factory::create_tools( true ),
					)
				);

			case 'tools/call':
				return self::call_tool( $id, $params );

			default:
				return self::error_response(
					$id,
					-32601,
					'Method not found.'
				);
		}
	}

	/**
	 * Execute an MCP tool.
	 *
	 * @param mixed $id     JSON-RPC request ID.
	 * @param array $params Tool parameters.
	 * @return array
	 */
	private static function call_tool( $id, array $params ): array {

		$name = isset( $params['name'] )
			? (string) $params['name']
			: '';

		$input = isset( $params['arguments'] ) && is_array( $params['arguments'] )
			? $params['arguments']
			: array();

		$callbacks = EOWC_MCP_Server_Factory::get_tool_callbacks();

		$method = $callbacks[ $name ] ?? null;

		if ( ! $method ) {
			return self::error_response(
				$id,
				-32602,
				'Unknown export tool.'
			);
		}

		/*
		 * Protect all export tools.
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::error_response(
				$id,
				-32010,
				'User lacks manage_options capability.'
			);
		}

		if ( ! method_exists( EOWC_Abilities::class, $method ) ) {
			return self::error_response(
				$id,
				-32603,
				'Tool callback does not exist.'
			);
		}

		try {
			$result = call_user_func(
				array( EOWC_Abilities::class, $method ),
				$input
			);
		} catch ( \Throwable $e ) {
			return self::error_response(
				$id,
				-32603,
				$e->getMessage()
			);
		}

		if ( is_wp_error( $result ) ) {
			return self::error_response(
				$id,
				-32000,
				$result->get_error_message()
			);
		}

		return self::result(
			$id,
			array(
				'content'           => array(
					array(
						'type' => 'text',
						'text' => wp_json_encode( $result ),
					),
				),
				'structuredContent' => $result,
			)
		);
	}

	/**
	 * Build successful JSON-RPC response.
	 *
	 * @param mixed $id     Request ID.
	 * @param mixed $result Result.
	 * @return array
	 */
	private static function result( $id, $result ): array {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * Build JSON-RPC error response.
	 *
	 * @param mixed  $id      Request ID.
	 * @param int    $code    Error code.
	 * @param string $message Error message.
	 * @return array
	 */
	private static function error_response(
		$id,
		int $code,
		string $message
	): array {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}
}
