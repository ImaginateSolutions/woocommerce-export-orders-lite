<?php
/**
 * Native MCP Streamable HTTP endpoint.
 *
 * @package Export_Orders_For_WooCommerce
 */

namespace EOWC\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Class EOWC_MCP_REST
 */
class EOWC_MCP_REST {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'eowc-mcp/v1';

	/**
	 * REST route.
	 *
	 * @var string
	 */
	private const ROUTE = '/mcp';

	/**
	 * Register REST API endpoint.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	/**
	 * Register MCP REST route.
	 */
	public static function register_route(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => array( 'POST', 'GET', 'DELETE' ),
				'callback'            => array( __CLASS__, 'handle_request' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
	}

	/**
	 * Check whether the current request is allowed.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return bool|\WP_Error
	 */
	public static function check_permission( \WP_REST_Request $request ) {
		$oauth = EOWC_OAuth::authenticate( $request );
		if ( null !== $oauth ) {
			return $oauth;
		}

		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'eowc_mcp_auth_required',
				__( 'Authentication is required.', 'woocommerce-export-orders' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'eowc_mcp_forbidden',
				__( 'You do not have permission to use the Export Orders MCP server.', 'woocommerce-export-orders' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle MCP request.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_request( \WP_REST_Request $request ) {
		// Stateless transport: GET probes receive the OAuth challenge from the
		// permission callback; authenticated SSE/session requests are unsupported.
		if ( 'POST' !== $request->get_method() ) {
			return new \WP_REST_Response( array( 'message' => 'Use POST for this stateless MCP endpoint.' ), 405, array( 'Allow' => 'POST' ) );
		}

		$body = $request->get_body();

		if ( empty( $body ) ) {
			return new \WP_Error(
				'eowc_mcp_empty_request',
				__( 'Empty MCP request.', 'woocommerce-export-orders' ),
				array( 'status' => 400 )
			);
		}

		$data = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new \WP_Error(
				'eowc_mcp_invalid_json',
				__( 'Invalid MCP JSON request.', 'woocommerce-export-orders' ),
				array( 'status' => 400 )
			);
		}

		$response = EOWC_MCP_Server::handle_request( $data );

		if ( null === $response ) {
			return new \WP_REST_Response( null, 202 );
		}

		return new \WP_REST_Response(
			$response,
			200
		);
	}
}
