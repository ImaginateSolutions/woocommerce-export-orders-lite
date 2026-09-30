<?php
/**
 * Universal OAuth authorization server and MCP resource authentication (ChatGPT & Claude).
 *
 * Public clients use authorization code + S256 PKCE and explicit user consent.
 * Supports ChatGPT Automatic Client Metadata Discovery, Dynamic Client Registration (Claude),
 * and standard OAuth 2.0 PKCE.
 * Tokens are opaque, stored hashed, audience-bound, and never authenticate unrelated WordPress endpoints.
 *
 * @package Export_Orders_For_WooCommerce
 */

namespace EOWC\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * OAuth Class
 */
class EOWC_OAuth {

	const SCOPE           = 'eowc:exports';
	const PREFIX          = 'eowc_oauth_record_';
	const EPOCH           = 'eowc_oauth_epoch';
	const HTACCESS_MARKER = 'EOWC OAuth';
	const RULES_OPTION    = 'eowc_oauth_root_rules';

	/**
	 * Previous_user.
	 *
	 * @var int $previous_user
	 */
	private static $previous_user = null;

	/**
	 * Pinned metadata locations for hosted Claude and the native Claude Code client.
	 *
	 * @var array $claude_clients
	 */
	private static $claude_clients = array(
		'https://claude.ai/api/oauth/mcp-oauth-client-metadata',
		'https://claude.ai/oauth/mcp-oauth-client-metadata',
		'https://claude.ai/oauth/claude-code-client-metadata',
		'claude',
		'claude-desktop',
		'claude-code',
	);

	/**
	 * Register Function
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'init', array( __CLASS__, 'discovery' ), 0 );
		add_action( 'admin_post_eowc_oauth_authorize', array( __CLASS__, 'authorize' ) );
		add_action( 'admin_post_nopriv_eowc_oauth_authorize', array( __CLASS__, 'authorize' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_eowc_oauth_disconnect', array( __CLASS__, 'disconnect' ) );
		add_action( 'admin_post_eowc_oauth_diagnostics', array( __CLASS__, 'diagnostics_control' ) );
		add_action( 'eowc_oauth_cleanup', array( __CLASS__, 'cleanup' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'response_headers' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'restore_user' ), 10, 3 );
		add_action( 'init', array( __CLASS__, 'handle_root_authorization_fallback' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_install_root_rules' ) );
		if ( ! wp_next_scheduled( 'eowc_oauth_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'eowc_oauth_cleanup' );
		}
	}

	/**
	 * Issuer Function
	 */
	public static function issuer(): string {
		return untrailingslashit( home_url( '/' . rest_get_url_prefix() . '/eowc-mcp/v1/oauth' ) );
	}

	/**
	 * Server Metadata URL Function
	 */
	public static function server_metadata_url(): string {
		return self::issuer() . '/.well-known/openid-configuration';
	}

	/**
	 * Resource Function
	 */
	public static function resource(): string {
		return rest_url( 'eowc-mcp/v1/mcp' );
	}

	/**
	 * Metadata URL Function
	 */
	public static function metadata_url(): string {
		return rest_url( 'eowc-mcp/v1/oauth/resource' );
	}

	/**
	 * Authorize URL Function
	 */
	public static function authorize_url(): string {
		return admin_url( 'admin-post.php?action=eowc_oauth_authorize' );
	}

	/**
	 * Secure Function
	 */
	public static function secure(): bool {
		foreach ( array( self::issuer(), self::resource(), self::authorize_url() ) as $url ) {
			if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
				return false;
			}
		}
		return is_ssl();
	}

	/**
	 * Routes Function
	 */
	public static function routes(): void {
		foreach ( array(
			'resource' => 'resource_metadata',
			'server'   => 'server_metadata',
		) as $route => $method ) {
			register_rest_route(
				'eowc-mcp/v1',
				'/oauth/' . $route,
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, $method ),
					'permission_callback' => '__return_true',
				)
			);
		}
		register_rest_route(
			'eowc-mcp/v1',
			'/oauth/\.well-known/openid-configuration',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'server_metadata' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'eowc-mcp/v1',
			'/oauth/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'register_client' ),
				'permission_callback' => '__return_true',
			)
		);
		foreach ( array(
			'token'  => 'token',
			'revoke' => 'revoke',
		) as $route => $method ) {
			register_rest_route(
				'eowc-mcp/v1',
				'/oauth/' . $route,
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, $method ),
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	/**
	 * Resource Metadata Function
	 */
	public static function resource_metadata(): array {
		self::diagnostic( 'resource_metadata', 200 );
		return array(
			'resource'                 => self::resource(),
			'authorization_servers'    => array( self::issuer() ),
			'scopes_supported'         => array( self::SCOPE ),
			'bearer_methods_supported' => array( 'header' ),
			'resource_name'            => 'WooCommerce Export Orders',
		);
	}

	/**
	 * Server Metadata Function
	 */
	public static function server_metadata(): array {
		self::diagnostic( 'server_metadata', 200 );
		return array(
			'issuer'                                     => self::issuer(),
			'authorization_endpoint'                     => self::authorize_url(),
			'token_endpoint'                             => rest_url( 'eowc-mcp/v1/oauth/token' ),
			'registration_endpoint'                      => rest_url( 'eowc-mcp/v1/oauth/register' ),
			'revocation_endpoint'                        => rest_url( 'eowc-mcp/v1/oauth/revoke' ),
			'response_types_supported'                   => array( 'code' ),
			'grant_types_supported'                      => array( 'authorization_code', 'refresh_token' ),
			'token_endpoint_auth_methods_supported'      => array( 'none' ),
			'revocation_endpoint_auth_methods_supported' => array( 'none' ),
			'code_challenge_methods_supported'           => array( 'S256' ),
			'scopes_supported'                           => array( self::SCOPE ),
			'client_id_metadata_document_supported'      => true,
			'authorization_response_iss_parameter_supported' => true,
		);
	}

	/**
	 * Discovery Path Function
	 */
	public static function discovery_paths(): array {
		$issuer_path = (string) wp_parse_url( self::issuer(), PHP_URL_PATH );
		$home_path   = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		$paths       = array();
		// RFC 8414 / OIDC path-insertion forms, at the domain root and under the WordPress subfolder (if any).
		foreach ( array( '', $home_path ) as $base ) {
			$paths[] = $base . '/.well-known/oauth-authorization-server' . $issuer_path;
			$paths[] = $base . '/.well-known/openid-configuration' . $issuer_path;
		}
		// Bare forms, used by clients that drop the subfolder and look at the domain root.
		foreach ( array( '', $home_path ) as $base ) {
			$paths[] = $base . '/.well-known/oauth-authorization-server';
			$paths[] = $base . '/.well-known/openid-configuration';
		}
		// Path-appending forms.
		$paths[] = $issuer_path . '/.well-known/openid-configuration';
		$paths[] = $issuer_path . '/.well-known/oauth-authorization-server';
		return array_values( array_unique( $paths ) );
	}

	/** RFC 9728 protected-resource metadata paths (path-insertion form). */
	public static function resource_discovery_paths(): array {
		$resource_path = (string) wp_parse_url( self::resource(), PHP_URL_PATH );
		$home_path     = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		$paths         = array();
		foreach ( array( '', $home_path ) as $base ) {
			$paths[] = $base . '/.well-known/oauth-protected-resource' . $resource_path;
			$paths[] = $base . '/.well-known/oauth-protected-resource';
		}
		return array_values( array_unique( $paths ) );
	}

	/**
	 * Discovery Function
	 */
	public static function discovery(): void {
		$path        = untrailingslashit( (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ) ) );
		$is_server   = in_array( $path, self::discovery_paths(), true );
		$is_resource = ! $is_server && in_array( $path, self::resource_discovery_paths(), true );
		if ( ! $is_server && ! $is_resource ) {
			return;
		}
		if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', array( 'GET', 'HEAD' ), true ) ) {
			status_header( 405 );
			header( 'Allow: GET, HEAD' );
			exit;
		}
		nocache_headers();
		header( 'Access-Control-Allow-Origin: *' );
		wp_send_json( $is_server ? self::server_metadata() : self::resource_metadata() );
	}

	/**
	 * Fallback for clients that skip metadata discovery and use the MCP spec default
	 * endpoints (/authorize, /register, /token) on the site root.
	 * Only useful when the request actually reaches WordPress (root install, or a
	 * web-server rewrite from the domain root to the WordPress subfolder).
	 */
	public static function handle_root_authorization_fallback(): void {
		$path   = untrailingslashit( (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ) ) );
		$home   = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		$method = $_SERVER['REQUEST_METHOD'] ?? ''; // phpcs:ignore
		$type   = strtolower( (string) ( $_SERVER['CONTENT_TYPE'] ?? '' ) );// phpcs:ignore

		foreach ( array_unique( array( '', $home ) ) as $base ) {
			if ( $base . '/authorize' === $path && 'GET' === $method ) {
				$query  = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY ); // phpcs:ignore
				$target = self::authorize_url();
				if ( $query ) {
					$target .= '&' . $query;
				}
				// phpcs:ignore
				wp_redirect( $target, 302 );
				exit;
			}
			// Only JSON POSTs, so a real /register page or form is never hijacked.
			if ( $base . '/register' === $path && 'POST' === $method && false !== strpos( $type, 'application/json' ) ) {
				self::serve_rest_fallback( '/eowc-mcp/v1/oauth/register' );
			}
			// Only OAuth-style token POSTs.
			// phpcs:ignore
			if ( $base . '/token' === $path && 'POST' === $method && false !== strpos( $type, 'application/x-www-form-urlencoded' ) && isset( $_POST['grant_type'] ) ) {
				self::serve_rest_fallback( '/eowc-mcp/v1/oauth/token' );
			}
		}
	}

	/**
	 * Serve Rest Function
	 *
	 * @param string $route Route.
	 */
	private static function serve_rest_fallback( string $route ): void {
		$request = new \WP_REST_Request( 'POST', $route );
		$type    = (string) ( $_SERVER['CONTENT_TYPE'] ?? '' ); // phpcs:ignore
		$raw     = (string) file_get_contents( 'php://input' );
		$request->set_header( 'content-type', $type );
		$request->set_body( $raw );
		if ( false !== stripos( $type, 'application/x-www-form-urlencoded' ) ) {
			parse_str( $raw, $params );
			$request->set_body_params( is_array( $params ) ? $params : array() );
		}
		$response = rest_do_request( $request );
		nocache_headers();
		wp_send_json( rest_get_server()->response_to_data( $response, false ), $response->get_status() );
	}

	/**
	 * Handles Dynamic Client Registration (RFC 7591) requested by Claude and other MCP clients.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function register_client( \WP_REST_Request $request ): \WP_REST_Response {
		self::diagnostic( 'registration_attempt', 200 );

		// Accept JSON (RFC 7591) and, defensively, form-encoded bodies.
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		$body = is_array( $body ) ? $body : array();

		$client_name = isset( $body['client_name'] ) && is_string( $body['client_name'] ) ? sanitize_text_field( $body['client_name'] ) : 'Claude MCP Client';
		$requested   = isset( $body['redirect_uris'] ) && is_array( $body['redirect_uris'] ) ? $body['redirect_uris'] : array();
		$redirects   = array();
		foreach ( $requested as $uri ) {
			if ( ! is_string( $uri ) || ! self::trusted_redirect( 'dynamic', $uri ) ) {
				return self::error( 'invalid_redirect_uri', 'Redirect URI is not an allowed Claude callback.' );
			}
			$redirects[] = $uri;
		}

		// Fallback default redirects if not provided.
		if ( empty( $redirects ) ) {
			$redirects = array( 'https://claude.ai/api/mcp/auth_callback', 'https://claude.com/api/mcp/auth_callback' );
		}

		$client_id = 'eowc_claude_' . bin2hex( random_bytes( 8 ) );
		$data      = array(
			'client_id'                  => $client_id,
			'client_id_issued_at'        => time(),
			'client_name'                => $client_name,
			'redirect_uris'              => $redirects,
			'token_endpoint_auth_method' => 'none',
			'grant_types'                => array( 'authorization_code', 'refresh_token' ),
			'response_types'             => array( 'code' ),
			'scope'                      => self::SCOPE,
		);

		// Clients keep their client_id for the life of the 30-day session, so keep the registration that long.
		set_transient( 'eowc_oauth_client_' . hash( 'sha256', 'v2:' . $client_id ), $data, 30 * DAY_IN_SECONDS );

		return new \WP_REST_Response(
			$data,
			201,
			array(
				'Cache-Control' => 'no-store',
				'Pragma'        => 'no-cache',
			)
		);
	}

	/**
	 * Detect ChatGPT client ID vs Claude / standard client ID.
	 *
	 * @param string $client_id Client_id.
	 */
	public static function is_chatgpt_client( string $client_id ): bool {
		return (bool) preg_match( '~^https://chatgpt\.com/oauth/(?:[A-Za-z0-9_-]{1,128}/)?client\.json$~D', $client_id );
	}

	/**
	 * Client.
	 *
	 * @param string $client_id Client_id.
	 */
	public static function client( string $client_id ) {
		// 1. Direct match for known Claude identifiers or dynamically generated clients.
		if ( in_array( $client_id, self::$claude_clients, true ) || 0 === strpos( $client_id, 'eowc_claude_' ) ) {
			$key    = 'eowc_oauth_client_' . hash( 'sha256', 'v2:' . $client_id );
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				self::diagnostic( 'client_cached' );
				return $cached;
			}
			return array(
				'client_id'     => $client_id,
				'redirect_uris' => array(
					'https://claude.ai/api/mcp/auth_callback',
					'https://claude.com/api/mcp/auth_callback',
				),
			);
		}

		// 2. ChatGPT CIMD Client.
		if ( self::is_chatgpt_client( $client_id ) ) {
			$key    = 'eowc_oauth_client_' . hash( 'sha256', 'v2:' . $client_id );
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				self::diagnostic( 'client_cached' );
				return $cached;
			}
			$response = wp_safe_remote_get(
				$client_id,
				array(
					'timeout'             => 10,
					'redirection'         => 0,
					'limit_response_size' => 32768,
				)
			);
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				self::diagnostic( 'client_fetch_failed', is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response ) );
				return new \WP_Error( 'invalid_client', 'Could not verify the AI client metadata. Reconnect when the provider is reachable.' );
			}
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) || ( $data['client_id'] ?? '' ) !== $client_id || empty( $data['redirect_uris'] ) || ! is_array( $data['redirect_uris'] ) ) {
				self::diagnostic( 'client_document_invalid' );
				return new \WP_Error( 'invalid_client', 'Invalid AI client metadata.' );
			}
			foreach ( $data['redirect_uris'] as $uri ) {
				if ( ! is_string( $uri ) || ! self::trusted_redirect( $client_id, $uri ) ) {
					self::diagnostic( 'client_callback_invalid' );
					return new \WP_Error( 'invalid_client', 'Untrusted AI client redirect URI.' );
				}
			}
			set_transient( $key, $data, 5 * MINUTE_IN_SECONDS );
			self::diagnostic( 'client_verified', 200 );
			return $data;
		}

		self::diagnostic( 'client_unknown' );
		return new \WP_Error( 'invalid_client', 'An official ChatGPT or Claude client ID is required.' );
	}

	/**
	 * Allows ChatGPT, hosted Claude.ai, and Claude Desktop/CLI redirects.
	 *
	 * @param string $client_id Client_id.
	 * @param string $uri uri.
	 */
	private static function trusted_redirect( string $client_id, string $uri ): bool {
		if ( self::is_chatgpt_client( $client_id ) ) {
			return (bool) preg_match( '~^https://chatgpt\.com/(?:connector_platform_oauth_redirect|connector/oauth/[A-Za-z0-9_-]{1,128})$~D', $uri );
		}

		// Hosted Claude.ai callback endpoints.
		if ( in_array( $uri, array( 'https://claude.ai/api/mcp/auth_callback', 'https://claude.com/api/mcp/auth_callback' ), true ) ) {
			return true;
		}

		// Localhost / loopback redirects for Claude Desktop / CLI.
		return (bool) preg_match( '~^(http://(?:localhost|127\.0\.0\.1)(?::[0-9]{1,5})?/callback|claude://[A-Za-z0-9_/-]+)$~D', $uri );
	}

	/**
	 * Redirect Matches.
	 *
	 * @param string $client_id Client_id.
	 * @param string $uri uri.
	 * @param array  $registered Registered.
	 */
	private static function redirect_matches( string $client_id, string $uri, array $registered ): bool {
		if ( ! self::trusted_redirect( $client_id, $uri ) ) {
			return false;
		}
		// ChatGPT: unchanged behaviour (trusted-redirect allowlist only).
		if ( self::is_chatgpt_client( $client_id ) ) {
			return true;
		}
		// Pinned first-party Claude client IDs: the trusted-redirect allowlist is the pin.
		if ( in_array( $client_id, self::$claude_clients, true ) ) {
			return true;
		}
		if ( in_array( $uri, $registered, true ) ) {
			return true;
		}
		// Allow dynamic ports for local development/clients.
		$without_port = preg_replace( '~^(http://(?:localhost|127\.0\.0\.1)):[0-9]+/~', '$1/', $uri );
		foreach ( $registered as $candidate ) {
			if ( is_string( $candidate ) && preg_replace( '~^(http://(?:localhost|127\.0\.0\.1)):[0-9]+/~', '$1/', $candidate ) === $without_port ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resource Matches.
	 *
	 * @param string $value value.
	 */
	private static function resource_matches( string $value ): bool {
		return '' === $value || untrailingslashit( $value ) === untrailingslashit( self::resource() );
	}

	/**
	 * Params.
	 *
	 * @param array  $input input.
	 * @param string $key key.
	 */
	private static function param( array $input, string $key ): string {
		return isset( $input[ $key ] ) && is_string( $input[ $key ] ) ? $input[ $key ] : '';
	}

	/**
	 * Validate authorization.
	 *
	 * @param array $input input.
	 */
	public static function validate_authorization( array $input ) {
		$keys = array( 'client_id', 'redirect_uri', 'response_type', 'scope', 'state', 'resource', 'code_challenge', 'code_challenge_method' );
		$args = array();
		foreach ( $keys as $key ) {
			$args[ $key ] = self::param( $input, $key );
		}

		if ( empty( $args['client_id'] ) ) {
			return new \WP_Error( 'invalid_request', 'Missing client_id.' );
		}

		$client = self::client( $args['client_id'] );
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		if ( ! self::redirect_matches( $args['client_id'], $args['redirect_uri'], $client['redirect_uris'] ?? array() ) ) {
			self::diagnostic( 'redirect_mismatch' );
			return new \WP_Error( 'invalid_request', 'The redirect URI does not match the verified AI client.' );
		}

		if ( ! self::resource_matches( $args['resource'] ) ) {
			self::diagnostic( 'invalid_target' );
			return new \WP_Error( 'invalid_target', 'The resource must equal the MCP endpoint URL.' );
		}
		if ( 'code' !== $args['response_type'] || 'S256' !== $args['code_challenge_method'] || ! preg_match( '/^[A-Za-z0-9_-]{43}$/D', $args['code_challenge'] ) || strlen( $args['state'] ) > 2048 ) {
			return new \WP_Error( 'invalid_request', 'Expected authorization code and S256 PKCE.' );
		}

		return $args;
	}

	/**
	 * Authorize.
	 */
	public static function authorize(): void {
		self::diagnostic( 'authorization_started' );
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		$style_nonce = base64_encode( random_bytes( 16 ) ); // phpcs:ignore
		header( "Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'nonce-" . $style_nonce . "'; form-action 'self' https://chatgpt.com https://claude.ai https://claude.com http://localhost:* http://127.0.0.1:*; frame-ancestors 'none'; base-uri 'none'" );
		header( 'X-Frame-Options: DENY' );

		if ( ! self::secure() ) {
			wp_die( 'OAuth requires HTTPS. Check the WordPress URLs and proxy HTTPS configuration.', 'HTTPS required', array( 'response' => 400 ) );
		}

		$method = $_SERVER['REQUEST_METHOD'] ?? ''; //phpcs:ignore
		if ( ! in_array( $method, array( 'GET', 'POST' ), true ) ) {
			wp_die( 'Method not allowed.', '', array( 'response' => 405 ) );
		}

		if ( ! is_user_logged_in() ) {
			self::diagnostic( 'login_required' );
			if ( 'POST' === $method ) {
				wp_die( 'Your session expired. Start the connection again.', '', array( 'response' => 401 ) );
			}
			auth_redirect();
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'An administrator account is required to authorize order exports.', '', array( 'response' => 403 ) );
		}

		if ( 'POST' === $method ) {
			check_admin_referer( 'eowc_oauth_consent' );
			$pending = isset( $_POST['pending'] ) && is_string( $_POST['pending'] ) ? sanitize_text_field( wp_unslash( $_POST['pending'] ) ) : '';
			$record  = self::read( 'consent', $pending );
			if ( ! $record || get_current_user_id() !== $record['user_id'] || ! self::consume( 'consent', $pending ) ) {
				wp_die( 'Consent expired or was already submitted. Start again from your AI client.', '', array( 'response' => 400 ) );
			}

			$args = self::validate_authorization( $record['args'] );
			if ( is_wp_error( $args ) ) {
				wp_die( esc_html( $args->get_error_message() ), 'Invalid OAuth request', array( 'response' => 400 ) );
			}
			$result = array(
				'iss'   => self::issuer(),
				'state' => $args['state'],
			);

			if ( 'allow' !== sanitize_text_field( wp_unslash( $_POST['decision'] ?? '' ) ) ) {
				$result['error'] = 'access_denied';
			} else {
				self::diagnostic( 'consent_approved' );
				$args['user_id'] = get_current_user_id();
				$args['epoch']   = self::epoch( get_current_user_id() );
				$result['code']  = self::store( 'code', $args, 5 * MINUTE_IN_SECONDS );
			}
			// phpcs:ignore
			wp_redirect( add_query_arg( $result, $args['redirect_uri'] ), 302, 'Export Orders OAuth' );
			exit;
		}

		$args = self::validate_authorization( wp_unslash( $_GET ) );
		if ( is_wp_error( $args ) ) {
			wp_die( esc_html( $args->get_error_message() ), 'Invalid OAuth request', array( 'response' => 400 ) );
		}

		$pending = self::store(
			'consent',
			array(
				'args'    => $args,
				'user_id' => get_current_user_id(),
			),
			10 * MINUTE_IN_SECONDS
		);

		header( 'Content-Type: text/html; charset=utf-8' );

		$css = <<<'CSS'
:root{--bg:#f0f2f5;--card:#fff;--text:#1d2327;--muted:#646970;--border:#dcdcde;--code:#f6f7f7;--primary:#2271b1;--primary-hover:#135e96;--on-primary:#fff;--warn-bg:#fcf9e8;--warn-border:#dba617;--shadow:0 4px 24px rgba(0,0,0,.08)}
@media (prefers-color-scheme:dark){:root{--bg:#101317;--card:#1b1f24;--text:#e6e8eb;--muted:#a0a7b0;--border:#333a42;--code:#12161a;--primary:#72aee6;--primary-hover:#9ec7f0;--on-primary:#0b1a2a;--warn-bg:#2b2410;--shadow:0 4px 24px rgba(0,0,0,.4)}}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--bg);color:var(--text);font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased}
.card{width:100%;max-width:480px;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:32px;box-shadow:var(--shadow)}
.icon{display:flex;align-items:center;justify-content:center;width:52px;height:52px;margin:0 auto 16px;border-radius:14px;background:var(--code);border:1px solid var(--border);color:var(--primary)}
h1{margin:0 0 6px;font-size:1.4rem;line-height:1.3;text-align:center}
.store{margin:0 0 24px;text-align:center;color:var(--muted);font-size:.95rem}
.store strong{color:var(--text)}
.section-title{margin:0 0 8px;font-size:.75rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
p{margin:0 0 16px}
.box{margin:0 0 20px;padding:14px 16px;background:var(--code);border:1px solid var(--border);border-radius:10px}
.box p{margin:0;font-size:.95rem}
.redirect{margin:0 0 20px}
.redirect code{display:block;padding:10px 12px;background:var(--code);border:1px solid var(--border);border-radius:8px;font:.85rem/1.4 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;word-break:break-all}
.notice{margin:0 0 20px;padding:12px 14px;background:var(--warn-bg);border-left:4px solid var(--warn-border);border-radius:6px;font-size:.92rem}
.hint{margin:0 0 24px;color:var(--muted);font-size:.88rem}
.actions{display:flex;gap:12px}
.btn{flex:1;padding:12px 16px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);font:inherit;font-weight:600;cursor:pointer;transition:background .15s,border-color .15s}
.btn:hover{background:var(--code)}
.btn:focus-visible{outline:3px solid var(--primary);outline-offset:2px}
.btn-primary{background:var(--primary);border-color:var(--primary);color:var(--on-primary)}
.btn-primary:hover{background:var(--primary-hover);border-color:var(--primary-hover)}
@media (max-width:480px){body{padding:12px;align-items:flex-start}.card{padding:24px 20px}.actions{flex-direction:column-reverse}}
CSS;

		$is_local = 'http' === wp_parse_url( $args['redirect_uri'], PHP_URL_SCHEME );

		echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light dark"><title>Connect AI Assistant</title>';
		echo '<style nonce="' . esc_attr( $style_nonce ) . '">' . $css . '</style></head><body><main class="card">'; // phpcs:ignore WordPress.Security.EscapeOutput -- static CSS.
		echo '<div class="icon" aria-hidden="true"><img width="50" height="50" src="' . esc_url( EOWC_PLUGIN_IMG_URL ) . '"/></div>';
		echo '<h1>Connect AI Assistant to Export Orders</h1>';
		echo '<p class="store">Store: <strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong></p>';

		echo '<div class="box"><p class="section-title">What this allows</p>';
		echo '<p>Your AI client will be able to read WooCommerce order and customer information, preview orders, and create export files using your administrator account.</p></div>';

		echo '<div class="redirect"><p class="section-title">Return address</p><code>' . esc_html( $args['redirect_uri'] ) . '</code></div>';

		if ( $is_local ) {
			echo '<div class="notice" role="alert">This connection returns to a program on your computer. Continue only if you started it from Claude Code.</div>';
		}

		echo '<p class="hint">You can revoke this access anytime under Export Orders → AI Connections.</p>';
		echo '<form method="post" action="' . esc_url( self::authorize_url() ) . '">';
		wp_nonce_field( 'eowc_oauth_consent' );
		echo '<input type="hidden" name="pending" value="' . esc_attr( $pending ) . '">';
		echo '<div class="actions"><button class="btn" name="decision" value="deny">Cancel</button><button class="btn btn-primary" name="decision" value="allow">Allow access</button></div>';
		echo '</form></main></body></html>';
		exit;
	}

	/**
	 * Store.
	 *
	 * @param string $kind kind.
	 * @param array  $data data.
	 * @param int    $ttl ttl.
	 *
	 * @throws \RuntimeException Unable to persist OAuth authorization.
	 */
	private static function store( string $kind, array $data, int $ttl ): string {
		$expires         = time() + $ttl;
		$token           = $expires . '.' . bin2hex( random_bytes( 32 ) );
		$data['expires'] = $expires;
		if ( ! add_option( self::key( $kind, $token ), $data, '', false ) ) {
			throw new \RuntimeException( 'Unable to persist OAuth authorization.' );
		}
		return $token;
	}

	/**
	 * Key.
	 *
	 * @param string $kind kind.
	 * @param string $token token.
	 */
	private static function key( string $kind, string $token ): string {
		if ( ! preg_match( '/^([0-9]{10})\.[a-f0-9]{64}$/D', $token, $match ) ) {
			return '';
		}
		return self::PREFIX . $match[1] . '_' . $kind . '_' . hash( 'sha256', $token );
	}

	/**
	 * Read.
	 *
	 * @param string $kind kind.
	 * @param string $token token.
	 */
	private static function read( string $kind, string $token ) {
		$key  = self::key( $kind, $token );
		$data = $key ? get_option( $key ) : false;
		return is_array( $data ) && ( $data['expires'] ?? 0 ) > time() ? $data : false;
	}

	/**
	 * Consume.
	 *
	 * @param string $kind kind.
	 * @param string $token token.
	 */
	private static function consume( string $kind, string $token ): bool {
		$key = self::key( $kind, $token );
		return $key && delete_option( $key );
	}

	/**
	 * Epoch.
	 *
	 * @param int $user_id user_id.
	 */
	private static function epoch( int $user_id ): string {
		return 'verified-clients-v2:' . (string) get_user_meta( $user_id, self::EPOCH, true );
	}

	/**
	 * Owner Valid.
	 *
	 * @param array $data data.
	 */
	private static function owner_valid( array $data ): bool {
		return ! empty( $data['user_id'] ) && user_can( $data['user_id'], 'manage_options' ) && hash_equals( self::epoch( $data['user_id'] ), $data['epoch'] ?? '' );
	}

	/**
	 * Error.
	 *
	 * @param string $code code.
	 * @param string $description description.
	 * @param int    $status status.
	 */
	private static function error( string $code, string $description, int $status = 400 ): \WP_REST_Response {
		self::diagnostic( $code, $status );
		return new \WP_REST_Response(
			array(
				'error'             => $code,
				'error_description' => $description,
			),
			$status,
			array(
				'Cache-Control' => 'no-store',
				'Pragma'        => 'no-cache',
			)
		);
	}

	/**
	 * Token.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function token( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! self::secure() ) {
			return self::error( 'invalid_request', 'HTTPS is required.' );
		}
		$input  = $request->get_body_params();
		$client = self::param( $input, 'client_id' );
		$target = self::param( $input, 'resource' );

		if ( ! self::resource_matches( $target ) ) {
			return self::error( 'invalid_target', 'The resource must equal the MCP endpoint URL.' );
		}

		$grant = self::param( $input, 'grant_type' );
		if ( 'authorization_code' === $grant ) {
			$code      = self::param( $input, 'code' );
			$data      = self::read( 'code', $code );
			$verifier  = self::param( $input, 'code_verifier' );
			$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore

			if ( ! $data || $data['client_id'] !== $client || ! self::owner_valid( $data ) || ! preg_match( '/^[A-Za-z0-9._~-]{43,128}$/D', $verifier ) || ! hash_equals( $data['code_challenge'], $challenge ) || ! self::consume( 'code', $code ) ) {
				return self::error( 'invalid_grant', 'Invalid, expired, or previously used authorization code or PKCE verifier.' );
			}
			$session = self::store(
				'session',
				array(
					'user_id'   => $data['user_id'],
					'epoch'     => $data['epoch'],
					'client_id' => $client,
					'resource'  => self::resource(),
					'scope'     => self::SCOPE,
				),
				30 * DAY_IN_SECONDS
			);
			return self::issue_tokens( $session );
		}
		if ( 'refresh_token' === $grant ) {
			$refresh = self::param( $input, 'refresh_token' );
			$data    = self::read( 'refresh', $refresh );
			if ( ! $data ) {
				$spent = self::read( 'spent', $refresh );
				if ( $spent && $spent['client_id'] === $client ) {
					self::consume( 'session', $spent['session'] );
				}
				return self::error( 'invalid_grant', 'Invalid or previously used refresh token.' );
			}
			$session = self::read( 'session', $data['session'] );
			$scope   = self::param( $input, 'scope' );

			if ( ! $session || $session['client_id'] !== $client || ! self::owner_valid( $session ) || ( '' !== $scope && self::SCOPE !== $scope ) ) {
				return self::error( 'invalid_grant', 'The connection expired or was revoked.' );
			}

			if ( ! add_option(
				self::key( 'spent', $refresh ),
				array(
					'session'   => $data['session'],
					'client_id' => $client,
					'expires'   => $data['expires'],
				),
				'',
				false
			) || ! self::consume( 'refresh', $refresh ) ) {
				self::consume( 'session', $data['session'] );
				return self::error( 'invalid_grant', 'Refresh token replay detected.' );
			}
			return self::issue_tokens( $data['session'] );
		}
		return self::error( 'unsupported_grant_type', 'Use authorization_code or refresh_token.' );
	}

	/**
	 * Issue tokens.
	 *
	 * @param string $session_token Session_token.
	 */
	private static function issue_tokens( string $session_token ): \WP_REST_Response {
		$session = self::read( 'session', $session_token );
		if ( ! $session || ! self::owner_valid( $session ) ) {
			return self::error( 'invalid_grant', 'The connection was revoked.' );
		}
		$remaining = $session['expires'] - time();
		$ttl       = min( HOUR_IN_SECONDS, $remaining );
		$data      = array( 'session' => $session_token );
		self::diagnostic( 'tokens_issued', 200 );
		return new \WP_REST_Response(
			array(
				'access_token'  => self::store( 'access', $data, $ttl ),
				'token_type'    => 'Bearer',
				'expires_in'    => $ttl,
				'refresh_token' => self::store( 'refresh', $data, $remaining ),
				'scope'         => self::SCOPE,
			),
			200,
			array(
				'Cache-Control' => 'no-store',
				'Pragma'        => 'no-cache',
			)
		);
	}

	/**
	 * Authenticate.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function authenticate( \WP_REST_Request $request ) {
		$header = $request->get_header( 'authorization' );
		if ( ! preg_match( '/^Bearer\b/i', $header ) ) {
			return null;
		}
		$data    = preg_match( '/^Bearer ([0-9]{10}\.[a-f0-9]{64})$/Di', $header, $match ) ? self::read( 'access', $match[1] ) : false;
		$session = $data ? self::read( 'session', $data['session'] ) : false;
		if ( ! self::secure() || ! $session || self::resource() !== $session['resource'] || ! self::owner_valid( $session ) ) {
			return new \WP_Error( 'eowc_oauth_invalid_token', 'Access token is invalid, expired, or revoked.', array( 'status' => 401 ) );
		}
		if ( null === self::$previous_user ) {
			self::$previous_user = get_current_user_id();
		}
		wp_set_current_user( $session['user_id'] );
		return true;
	}

	/**
	 * Restore User
	 *
	 * @param string $response response.
	 * @param string $handler handler.
	 * @param string $request request.
	 */
	public static function restore_user( $response, $handler, $request ) {
		if ( '/eowc-mcp/v1/mcp' === $request->get_route() && null !== self::$previous_user ) {
			wp_set_current_user( self::$previous_user );
			self::$previous_user = null;
		}
		return $response;
	}

	/**
	 * Request
	 *
	 * @param \WP_REST_Request $request request.
	 */
	public static function revoke( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! self::secure() ) {
			return self::error( 'invalid_request', 'HTTPS is required.' );
		}
		$input = $request->get_body_params();
		$token = self::param( $input, 'token' );
		foreach ( array( 'access', 'refresh', 'spent' ) as $kind ) {
			$data    = self::read( $kind, $token );
			$session = $data ? self::read( 'session', $data['session'] ) : false;
			if ( $session && self::param( $input, 'client_id' ) === $session['client_id'] ) {
				self::consume( 'session', $data['session'] );
			}
		}
		return new \WP_REST_Response( null, 200, array( 'Cache-Control' => 'no-store' ) );
	}

	/**
	 * Response Header
	 *
	 * @param string $response response.
	 * @param string $server server.
	 * @param string $request request.
	 */
	public static function response_headers( $response, $server, $request ) {
		$route  = $request->get_route();
		$events = array(
			'/eowc-mcp/v1/mcp'            => 'mcp_request',
			'/eowc-mcp/v1/oauth/token'    => 'token_request',
			'/eowc-mcp/v1/oauth/register' => 'registration_attempt',
		);
		if ( isset( $events[ $route ] ) ) {
			self::diagnostic( $events[ $route ], $response->get_status() );
		}
		if ( '/eowc-mcp/v1/mcp' === $route || 0 === strpos( $route, '/eowc-mcp/v1/oauth/' ) ) {
			$response->header( 'Cache-Control', 'no-store' );
			$response->header( 'Pragma', 'no-cache' );
			if ( '/eowc-mcp/v1/mcp' === $route && 401 === $response->get_status() ) {
				$response->header( 'WWW-Authenticate', 'Bearer resource_metadata="' . self::metadata_url() . '", scope="' . self::SCOPE . '"' );
			}
		}
		return $response;
	}

	/**
	 * Menu.
	 */
	public static function menu(): void {
		add_submenu_page( 'eowc-export-orders', 'AI Connections', 'AI Connections', 'manage_options', 'eowc-ai-connections', array( __CLASS__, 'settings' ) );
	}

	/**
	 * Settings.
	 */
	public static function settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>AI Assistant Connections</h1>';
		if ( ! self::secure() ) {
			echo '<div class="notice notice-error"><p>HTTPS is required. Check your WordPress URLs and HTTPS proxy configuration before connecting.</p></div>';
		}
		echo '<p>' . esc_html__( 'Use this server URL for both ChatGPT and Claude / MCP clients:', 'woocommerce-export-orders' ) . '</p>';
		echo '<p style="display: flex; align-items: center; gap: 8px;">';
		echo '<code>' . esc_html( self::resource() ) . '</code>';
		echo '<button type="button" class="button button-secondary" onclick="navigator.clipboard.writeText(\'' . esc_js( self::resource() ) . '\'); this.innerText=\'Copied!\'; const b=this; setTimeout(function(){ b.innerText=\'Copy\'; }, 2000);"><span class="dashicons dashicons-admin-page"></span>' . esc_html__( 'Copy', 'woocommerce-export-orders' ) . '</button>';
		echo '</p>';
		echo '<p>Use automatic OAuth client discovery. Leave manual client credentials blank. Both ChatGPT and Claude are supported.</p>';
		echo '<p>Discovery check: <a target="_blank" rel="noopener noreferrer" href="' . esc_url( self::server_metadata_url() ) . '">Authorization server metadata</a>.</p>';
		self::render_root_rules_status();
		echo '<h2>Disconnect your account</h2><p>This revokes all active ChatGPT and Claude connections created by your current WordPress user.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="eowc_oauth_disconnect">';
		wp_nonce_field( 'eowc_oauth_disconnect' );
		submit_button( 'Revoke my AI connections', 'secondary' );
		echo '</form>';
		?>
		<div class="eowc-mcp-guide" style="margin-top: 25px; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 6px;">
			<h2>How to Connect Your AI Assistant</h2>
			<p>Use the details below to add your WordPress store as an MCP (Model Context Protocol) server in ChatGPT or Claude.</p>

			<!-- ChatGPT Instructions -->
			<div style="margin-bottom: 25px;">
				<h3><span class="dashicons dashicons-admin-generic"></span> Connecting to ChatGPT</h3>
				<ol>
					<li>Open <strong>ChatGPT</strong> and go to <strong>Settings &gt; Connectors / Apps &amp; Connectors</strong>.</li>
					<li>Enable <strong>Developer Mode</strong> (or <em>Advanced Settings</em>) if required.</li>
					<li>Click <strong>Create Connector</strong> or <strong>Add MCP Server</strong>.</li>
					<li>Fill in the connector configuration:
						<ul>
							<li><strong>Name:</strong> Easy Orders WooCommerce MCP</li>
							<li><strong>Server URL:</strong> <code><?php echo esc_html( self::resource() ); ?></code></li>
							<li><strong>Authentication:</strong> Select <strong>OAuth 2.0</strong></li>
						</ul>
					</li>
					<li>Click <strong>Connect</strong> or <strong>Save</strong>.</li>
					<li>You will be redirected to your WordPress login screen. Log in with your WooCommerce admin account and click <strong>Authorize</strong>.</li>
				</ol>
			</div>

			<hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;" />

			<!-- Claude Instructions -->
			<div>
				<h3><span class="dashicons dashicons-share"></span> Connecting to Claude (Claude.ai or Claude Desktop)</h3>
				<ol>
					<li>Open <strong>Claude.ai</strong> (or Claude Desktop) and navigate to <strong>Settings &gt; Integrations / Connectors</strong>.</li>
					<li>Click <strong>Add Integration</strong> or <strong>Add MCP Server</strong>.</li>
					<li>Enter the following server details:
						<ul>
							<li><strong>Integration Name:</strong> Easy Orders WooCommerce</li>
							<li><strong>MCP Server URL:</strong> <code><?php echo esc_html( self::resource() ); ?></code></li>
						</ul>
					</li>
					<li>Click <strong>Connect</strong>.</li>
					<li>Claude will detect the OAuth requirement automatically and open your WordPress authorization page.</li>
					<li>Log in to your WordPress site and click <strong>Authorize</strong> to allow Claude to connect.</li>
				</ol>
			</div>
		</div>
		<?php
		echo '<h2>Connection diagnostics</h2><p>Start a 10-minute recording, retry the failed connection once, then refresh this page. Only step names, UTC times and HTTP statuses are recorded.</p>';
		$until = (int) get_transient( 'eowc_oauth_diagnostic_until' );
		echo '<p>' . ( $until > time() ? 'Recording is active for up to 10 minutes.' : 'Recording is off.' ) . ' Results expire after one hour. This view is available only to administrators.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="eowc_oauth_diagnostics">';
		wp_nonce_field( 'eowc_oauth_diagnostics' );
		echo '<button class="button" name="diagnostic_action" value="start">Start new diagnostic recording</button> <button class="button" name="diagnostic_action" value="clear">Stop and clear diagnostics</button></form>';
		$events = get_transient( 'eowc_oauth_diagnostic_events' );
		echo '<p>Retry in Claude before opening the metadata link.</p>';
		echo '<textarea readonly rows="12" class="large-text code" aria-label="OAuth diagnostic results">' . esc_textarea( is_array( $events ) && $events ? wp_json_encode( $events, JSON_PRETTY_PRINT ) : 'No events recorded.' ) . '</textarea></div>';
	}

	/**
	 * Build the Apache rules that forward root-level OAuth discovery requests to this WordPress install.
	 * Needed only when WordPress lives in a subfolder (e.g. https://example.com/shop/).
	 *
	 * @param string $home_path Home Path.
	 */
	public static function root_rules_block( string $home_path ): string {
		$slug  = preg_quote( trim( $home_path, '/' ), '~' );
		$index = $home_path . '/index.php';
		return implode(
			"\n",
			array(
				'# BEGIN ' . self::HTACCESS_MARKER,
				'# Managed by Export Orders: lets AI clients (Claude) find OAuth metadata at the domain root.',
				'<IfModule mod_rewrite.c>',
				'RewriteEngine On',
				'RewriteRule ^\.well-known/(oauth-authorization-server|openid-configuration|oauth-protected-resource)(/' . $slug . '(/.*)?)?$ ' . $index . ' [L]',
				'RewriteCond %{REQUEST_METHOD} POST',
				'RewriteCond %{HTTP:Content-Type} application/json [NC]',
				'RewriteRule ^register/?$ ' . $index . ' [L]',
				'RewriteCond %{REQUEST_METHOD} POST',
				'RewriteCond %{HTTP:Content-Type} application/x-www-form-urlencoded [NC]',
				'RewriteRule ^token/?$ ' . $index . ' [L]',
				'RewriteCond %{REQUEST_METHOD} GET',
				'RewriteCond %{QUERY_STRING} (^|&)client_id=',
				'RewriteCond %{QUERY_STRING} (^|&)code_challenge=',
				'RewriteRule ^authorize/?$ ' . $index . ' [L]',
				'</IfModule>',
				'# END ' . self::HTACCESS_MARKER,
			)
		);
	}

	/**
	 * Homepath.
	 */
	private static function home_path(): string {
		return untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
	}

	/**
	 * Returns: not_needed | disabled | unsupported | unwritable | installed
	 */
	public static function install_root_rules(): string {
		$home = self::home_path();
		if ( '' === $home ) {
			return 'not_needed';
		}
		if ( ( defined( 'EOWC_OAUTH_NO_HTACCESS' ) && EOWC_OAUTH_NO_HTACCESS ) || ! apply_filters( 'eowc_oauth_write_root_rules', true ) ) {
			return 'disabled';
		}
		$root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? untrailingslashit( (string) sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) ) : '';
		if ( empty( $GLOBALS['is_apache'] ) || '' === $root || ! is_dir( $root ) || ! file_exists( $root . $home . '/index.php' ) ) {
			return 'unsupported';
		}
		$file     = $root . '/.htaccess';
		$existing = file_exists( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore
		$block    = self::root_rules_block( $home );
		$clean    = preg_replace( '~# BEGIN ' . preg_quote( self::HTACCESS_MARKER, '~' ) . '.*?# END ' . preg_quote( self::HTACCESS_MARKER, '~' ) . '\R?~s', '', $existing );
		$new      = $block . "\n" . $clean; // Prepend so the rules run before WordPress' own catch-all rules.
		if ( $new === $existing ) {
			return 'installed';
		}
		// phpcs:ignore
		if ( file_exists( $file ) ? ! is_writable( $file ) : ! is_writable( $root ) ) {
			return 'unwritable';
		}
		if ( '' !== $existing && ! file_exists( $file . '.eowc-backup' ) ) {
			@copy( $file, $file . '.eowc-backup' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		return false === file_put_contents( $file, $new, LOCK_EX ) ? 'unwritable' : 'installed'; // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/** Call from the plugin's deactivation hook to clean up. */
	public static function remove_root_rules(): void {
		$root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? untrailingslashit( (string) sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) ) : '';
		$file = $root . '/.htaccess';
		// phpcs:ignore
		if ( '' === $root || ! is_writable( $file ) ) {
			return;
		}
		// phpcs:ignore
		$existing = (string) file_get_contents( $file );
		$clean    = preg_replace( '~# BEGIN ' . preg_quote( self::HTACCESS_MARKER, '~' ) . '.*?# END ' . preg_quote( self::HTACCESS_MARKER, '~' ) . '\R?~s', '', $existing );
		if ( null !== $clean && $clean !== $existing ) {
			file_put_contents( $file, $clean, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		delete_option( self::RULES_OPTION );
	}

	/** May be intall root. */
	public static function maybe_install_root_rules(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$home    = self::home_path();
		$saved   = get_option( self::RULES_OPTION, array() );
		$on_page = isset( $_GET['page'] ) && 'eowc-ai-connections' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification
		if ( is_array( $saved ) && ( $saved['home'] ?? null ) === $home && in_array( $saved['status'] ?? '', array( 'installed', 'not_needed', 'disabled' ), true ) && ! $on_page ) {
			return;
		}
		update_option(
			self::RULES_OPTION,
			array(
				'home'   => $home,
				'status' => self::install_root_rules(),
			),
			false
		);
	}

	/** Render root Rules. */
	private static function render_root_rules_status(): void {
		$home = self::home_path();
		if ( '' === $home ) {
			return;
		}
		$saved  = get_option( self::RULES_OPTION, array() );
		$status = is_array( $saved ) ? (string) ( $saved['status'] ?? '' ) : '';

		$parts  = wp_parse_url( home_url() );
		$origin = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
		$check  = wp_remote_get(
			$origin . '/.well-known/oauth-authorization-server' . wp_parse_url( self::issuer(), PHP_URL_PATH ),
			array(
				'timeout'     => 8,
				'redirection' => 0,
			)
		);
		$json   = is_wp_error( $check ) ? null : json_decode( wp_remote_retrieve_body( $check ), true );
		$ok     = ! is_wp_error( $check ) && 200 === wp_remote_retrieve_response_code( $check ) && is_array( $json ) && isset( $json['issuer'] );

		echo '<h2>Claude discovery (subfolder install)</h2>';
		if ( $ok ) {
			echo '<div class="notice notice-success inline"><p>Root discovery is working. Claude can find this server.</p></div>';
			return;
		}
		echo '<div class="notice notice-warning inline"><p>';
		if ( 'installed' === $status ) {
			echo 'Root rules were added to <code>.htaccess</code>, but the check could not confirm them from this server (this can be a false alarm if the server cannot call itself). Try connecting from Claude.';
		} elseif ( 'unwritable' === $status ) {
			echo 'Your site is in a subfolder and the root <code>.htaccess</code> is not writable, so the rules could not be added automatically. Add the rules below at the top of the domain-root <code>.htaccess</code>.';
		} elseif ( 'disabled' === $status ) {
			echo 'Automatic root rules are disabled. Add the rules below at the top of the domain-root <code>.htaccess</code>.';
		} else {
			echo 'Your site is in a subfolder and this server type could not be configured automatically. Forward the paths in the rules below to <code>' . esc_html( $home ) . '/index.php</code> in your web server configuration.';
		}
		echo '</p></div><textarea readonly rows="10" class="large-text code" aria-label="Root rewrite rules">' . esc_textarea( self::root_rules_block( $home ) ) . '</textarea>';
	}

	/**
	 * Diagnostic.
	 *
	 * @param string $event event.
	 * @param int    $status status.
	 */
	public static function diagnostic( string $event, int $status = 0 ): void {
		$allowed = array( 'recording_started', 'resource_metadata', 'server_metadata', 'mcp_request', 'token_request', 'registration_attempt', 'authorization_started', 'login_required', 'consent_approved', 'client_unknown', 'client_cached', 'client_fetch_failed', 'client_document_invalid', 'client_callback_invalid', 'client_verified', 'redirect_mismatch', 'invalid_target', 'invalid_grant', 'invalid_request', 'unsupported_grant_type', 'tokens_issued' );
		if ( (int) get_transient( 'eowc_oauth_diagnostic_until' ) <= time() || ! in_array( $event, $allowed, true ) ) {
			return;
		}
		$events   = get_transient( 'eowc_oauth_diagnostic_events' );
		$events   = is_array( $events ) ? $events : array();
		$events[] = array(
			'time_utc'    => gmdate( 'Y-m-d H:i:s' ),
			'step'        => $event,
			'http_status' => $status >= 100 && $status <= 599 ? $status : null,
		);
		set_transient( 'eowc_oauth_diagnostic_events', array_slice( $events, -30 ), HOUR_IN_SECONDS );
	}

	/**
	 * Diagnostic Control.
	 */
	public static function diagnostics_control(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'eowc_oauth_diagnostics' );
		// phpcs:ignore
		if ( 'start' === ( sanitize_text_field( wp_unslash( $_POST['diagnostic_action'] ) ) ?? '' ) ) {
			set_transient( 'eowc_oauth_diagnostic_until', time() + 10 * MINUTE_IN_SECONDS, 10 * MINUTE_IN_SECONDS );
			delete_transient( 'eowc_oauth_diagnostic_events' );
			self::diagnostic( 'recording_started' );
		} else {
			delete_transient( 'eowc_oauth_diagnostic_until' );
			delete_transient( 'eowc_oauth_diagnostic_events' );
		}
		// phpcs:ignore
		wp_redirect( admin_url( 'admin.php?page=eowc-ai-connections' ) );
		exit;
	}

	/**
	 *  Disconnect.
	 */
	public static function disconnect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'eowc_oauth_disconnect' );
		update_user_meta( get_current_user_id(), self::EPOCH, bin2hex( random_bytes( 32 ) ) );
		wp_safe_redirect( admin_url( 'admin.php?page=eowc-ai-connections' ) );
		exit;
	}

	/**
	 *  Cleanup.
	 */
	public static function cleanup(): void {
		global $wpdb;
		// phpcs:ignore
		$keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name < %s LIMIT 1000",
				$wpdb->esc_like( self::PREFIX ) . '%',
				self::PREFIX . time() . '_'
			)
		);
		foreach ( $keys as $key ) {
			delete_option( $key );
		}
	}
}