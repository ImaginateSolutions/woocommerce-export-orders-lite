<?php
/**
 * Native MCP STDIO server for Export Orders.
 *
 * @package Export_Orders_For_WooCommerce
 */

namespace EOWC\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serve the plugin abilities over MCP JSON-RPC without an adapter plugin.
 */
class EOWC_MCP_CLI {

	/**
	 * Register the WP-CLI command when WP-CLI is available.
	 */
	public static function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'eowc-mcp', array( __CLASS__, 'command' ) );
		}
	}

	/**
	 * Run the MCP STDIO server.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public static function command( $args, $assoc_args ): void {
		if ( empty( $args[0] ) || 'serve' !== $args[0] ) {
			\WP_CLI::error( 'Usage: wp eowc-mcp serve [--user=<id|login|email>]' );
		}

		if ( ! empty( $assoc_args['user'] ) ) {
			$user = get_user_by( 'login', $assoc_args['user'] );
			if ( ! $user ) {
				$user = get_user_by( 'email', $assoc_args['user'] );
			}
			if ( ! $user && is_numeric( $assoc_args['user'] ) ) {
				$user = get_user_by( 'id', absint( $assoc_args['user'] ) );
			}
			if ( ! $user ) {
				\WP_CLI::error( 'User not found.' );
			}
			wp_set_current_user( $user->ID );
		}

		// phpcs:ignore
		while ( false !== ( $line = fgets( STDIN ) ) ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$request = json_decode( $line, true );
			if ( ! is_array( $request ) ) {
				self::write_response(
					null,
					null,
					array(
						'code'    => -32700,
						'message' => 'Parse error.',
					)
				);
				continue;
			}
			$response = self::handle_request( $request );
			if ( null !== $response ) {
				// phpcs:ignore
				fwrite( STDOUT, wp_json_encode( $response ) . "\n" );
				fflush( STDOUT );
			}
		}
	}

	/**
	 * Handle one MCP JSON-RPC request.
	 *
	 * @param array $request JSON-RPC request.
	 * @return array|null
	 */
	private static function handle_request( array $request ) {
		$id     = $request['id'] ?? null;
		$method = $request['method'] ?? '';
		$params = isset( $request['params'] ) && is_array( $request['params'] ) ? $request['params'] : array();

		if ( 0 === strpos( $method, 'notifications/' ) ) {
			return null;
		}

		switch ( $method ) {
			case 'initialize':
				return self::result(
					$id,
					array(
						'protocolVersion' => '2025-11-25',
						'capabilities'    => array( 'tools' => array() ),
						'serverInfo'      => array(
							'name'    => 'eowc-export-orders',
							'version' => EOWC_VERSION,
						),
					)
				);
			case 'tools/list':
				return self::result( $id, array( 'tools' => self::tools() ) );
			case 'tools/call':
				return self::call_tool( $id, $params );
			default:
				return self::error_response( $id, -32601, 'Method not found.' );
		}
	}

	/**
	 * Execute an MCP tool.
	 *
	 * @param mixed $id JSON-RPC request ID.
	 * @param array $params Tool parameters.
	 * @return array
	 */
	private static function call_tool( $id, array $params ): array {
		$name   = isset( $params['name'] ) ? sanitize_key( $params['name'] ) : '';
		$input  = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
		$tools  = self::tool_callbacks();
		$method = $tools[ $name ] ?? null;

		if ( ! $method ) {
			return self::error_response( $id, -32602, 'Unknown export tool.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::error_response( $id, -32010, 'User lacks manage_options capability.' );
		}

		$result = call_user_func( array( EOWC_Abilities::class, $method ), $input );
		if ( is_wp_error( $result ) ) {
			return self::error_response( $id, -32000, $result->get_error_message() );
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
	 * Return MCP tool definitions.
	 *
	 * @return array
	 */
	private static function tools(): array {
		return EOWC_MCP_Server_Factory::create_tools();
	}

	/**
	 * Map MCP-safe tool names to ability callbacks.
	 *
	 * Tool names use hyphens (e.g. eowc-get-fields) matching the factory map.
	 *
	 * @return array
	 */
	private static function tool_callbacks(): array {
		return EOWC_MCP_Server_Factory::get_tool_callbacks();
	}

	/**
	 * Build a successful JSON-RPC response.
	 *
	 * @param mixed $id Request ID.
	 * @param mixed $result Response result.
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
	 * Build an error JSON-RPC response.
	 *
	 * @param mixed  $id Request ID.
	 * @param int    $code Error code.
	 * @param string $message Error message.
	 * @return array
	 */
	private static function error_response( $id, $code, $message ): array {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}

	/**
	 * Write a response to STDOUT.
	 *
	 * @param mixed $id Request ID.
	 * @param mixed $result Result.
	 * @param mixed $error Error payload.
	 */
	private static function write_response( $id, $result, $error ): void {
		$response = array(
			'jsonrpc' => '2.0',
			'id'      => $id,
		);
		if ( null !== $error ) {
			$response['error'] = $error;
		} else {
			$response['result'] = $result;
		}
		// phpcs:ignore
		fwrite( STDOUT, wp_json_encode( $response ) . "\n" );
		fflush( STDOUT );
	}
}
