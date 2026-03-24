<?php
/**
 * CreatorReactor API client: OAuth 2.0 access token + list subscribers and followers.
 *
 * @package CreatorReactor
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreatorReactor_Client {

	public static function get_access_token() {
		return CreatorReactor_OAuth::get_access_token();
	}

	private static function fetch_first_non_404_response( $base, $token, $api_version, $endpoints ) {
		$version_headers = [];
		if ( is_string( $api_version ) && $api_version !== '' ) {
			$version_headers[] = $api_version;
		}
		$version_headers[] = null;
		$last_response = null;
		$last_endpoint = null;
		$last_version_header = null;

		foreach ( $endpoints as $endpoint ) {
			foreach ( $version_headers as $version_header ) {
				$headers = [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				];
				if ( is_string( $version_header ) && $version_header !== '' ) {
					$headers['X-CreatorReactor-API-Version'] = $version_header;
					$headers['X-Fanvue-API-Version']         = $version_header;
				}

				$response = wp_remote_get(
					$base . $endpoint,
					[
						'timeout' => 15,
						'headers' => $headers,
					]
				);

				if ( is_wp_error( $response ) ) {
					return [
						'response' => $response,
						'endpoint' => $endpoint,
						'api_version_header' => $version_header,
					];
				}

				$code = wp_remote_retrieve_response_code( $response );
				$last_response = $response;
				$last_endpoint = $endpoint;
				$last_version_header = $version_header;
				if ( $code !== 404 ) {
					break 2;
				}
			}
		}

		return [
			'response' => $last_response,
			'endpoint' => $last_endpoint,
			'api_version_header' => $last_version_header,
		];
	}

	private static function fetch_profile_response( $base, $token, $api_version = '2025-06-26' ) {
		return self::fetch_first_non_404_response( $base, $token, $api_version, [ '/users/me', '/me', '/creator/me', '/creators/me' ] );
	}

	private static function fetch_oauth_probe_response( $base, $token, $api_version = '2025-06-26', $creator_id = '' ) {
		$profile_result = self::fetch_profile_response( $base, $token, $api_version );
		$profile_response = $profile_result['response'];
		if ( is_wp_error( $profile_response ) ) {
			return $profile_result;
		}

		$profile_code = wp_remote_retrieve_response_code( $profile_response );
		if ( $profile_code !== 404 ) {
			return $profile_result;
		}

		$probe_endpoints = [];
		if ( is_string( $creator_id ) && $creator_id !== '' ) {
			$probe_endpoints[] = '/creators/' . rawurlencode( $creator_id ) . '/subscribers?page=1&size=1';
		}
		$probe_endpoints[] = '/subscribers?page=1&size=1';
		$probe_endpoints[] = '/followers?page=1&size=1';

		return self::fetch_first_non_404_response( $base, $token, $api_version, $probe_endpoints );
	}

	public static function test_connection() {
		$checks = [];
		$all_passed = true;

		$opts = Admin_Settings::get_options();
		$client_id = ! empty( $opts['creatorreactor_oauth_client_id'] );
		$client_secret = ! empty( $opts['creatorreactor_oauth_client_secret'] );
		$base = rtrim( $opts['creatorreactor_api_base_url'] ?? CreatorReactor_OAuth::API_BASE_URL, '/' );

		$checks[] = [
			'label' => __( 'OAuth Client ID', 'creatorreactor' ),
			'pass' => $client_id,
			'message' => $client_id ? __( 'Configured', 'creatorreactor' ) : __( 'Not configured', 'creatorreactor' ),
		];
		if ( ! $client_id ) {
			$all_passed = false;
		}

		$checks[] = [
			'label' => __( 'OAuth Client Secret', 'creatorreactor' ),
			'pass' => $client_secret,
			'message' => $client_secret ? __( 'Configured', 'creatorreactor' ) : __( 'Not configured', 'creatorreactor' ),
		];
		if ( ! $client_secret ) {
			$all_passed = false;
		}

		$api_reachable = false;
		$api_message = '';
		try {
			$response = wp_remote_get(
				$base . '/health',
				[
					'timeout' => 10,
					'sslverify' => true,
				]
			);
			if ( is_wp_error( $response ) ) {
				$api_message = $response->get_error_message();
				$all_passed = false;
			} else {
				$code = wp_remote_retrieve_response_code( $response );
				$api_reachable = $code > 0;
				if ( $code === 401 ) {
					$api_message = sprintf( __( '%1$s — HTTP %2$d (reachable, auth required)', 'creatorreactor' ), $base, $code );
				} else {
					$api_message = sprintf( __( '%1$s — HTTP %2$d', 'creatorreactor' ), $base, $code );
				}
				if ( ! $api_reachable ) {
					$all_passed = false;
				}
			}
		} catch ( \Throwable $e ) {
			$api_message = $e->getMessage();
			$all_passed = false;
		}
		$checks[] = [
			'label' => __( 'API Endpoint Reachable', 'creatorreactor' ),
			'pass' => $api_reachable,
			'message' => $api_message ?: ( $api_reachable ? __( 'OK', 'creatorreactor' ) : __( 'Failed', 'creatorreactor' ) ),
		];

		$token = self::get_access_token();
		$checks[] = [
			'label' => __( 'OAuth Token', 'creatorreactor' ),
			'pass' => ! empty( $token ),
			'message' => ! empty( $token ) ? __( 'Valid', 'creatorreactor' ) : __( 'Not authenticated', 'creatorreactor' ),
		];
		if ( ! $token ) {
			$all_passed = false;
		}

		if ( $token ) {
			$api_version = isset( $opts['creatorreactor_api_version'] ) ? $opts['creatorreactor_api_version'] : '2025-06-26';
			$creator_id = isset( $opts['creatorreactor_creator_id'] ) ? trim( (string) $opts['creatorreactor_creator_id'] ) : '';
			$oauth_valid = false;
			$oauth_message = '';
			try {
				$profile_result = self::fetch_oauth_probe_response( $base, $token, $api_version, $creator_id );
				$response = $profile_result['response'];
				$endpoint = $profile_result['endpoint'];
				$version_header = $profile_result['api_version_header'];
				if ( is_wp_error( $response ) ) {
					$oauth_message = $response->get_error_message();
					$all_passed = false;
				} else {
					$code = wp_remote_retrieve_response_code( $response );
					if ( $code === 200 ) {
						$oauth_valid = true;
						$body = json_decode( wp_remote_retrieve_body( $response ), true );
						$name = isset( $body['displayName'] ) ? $body['displayName'] : ( isset( $body['handle'] ) ? $body['handle'] : null );
						if ( $name ) {
							$oauth_message = sprintf( __( 'Connected as %s', 'creatorreactor' ), $name );
						} else {
							$oauth_message = __( 'Authenticated', 'creatorreactor' );
						}
						if ( ! is_string( $version_header ) || $version_header === '' ) {
							$oauth_message .= __( ' (without API version header)', 'creatorreactor' );
						}
					} elseif ( $code === 401 ) {
						$oauth_message = __( 'Token expired or invalid', 'creatorreactor' );
						$all_passed = false;
					} elseif ( $code === 403 ) {
						$oauth_valid = true;
						$oauth_message = sprintf( __( 'Authenticated, but access denied for %s (HTTP 403)', 'creatorreactor' ), $endpoint ?: '/me' );
					} elseif ( $code === 404 ) {
						$oauth_message = sprintf( __( 'No OAuth verification endpoint found (last tried %s)', 'creatorreactor' ), $endpoint ?: '/me' );
						$all_passed = false;
					} else {
						$oauth_message = sprintf( __( 'HTTP %d', 'creatorreactor' ), $code );
						$all_passed = false;
					}
				}
			} catch ( \Throwable $e ) {
				$oauth_message = $e->getMessage();
				$all_passed = false;
			}
			$checks[] = [
				'label' => __( 'OAuth Credentials', 'creatorreactor' ),
				'pass' => $oauth_valid,
				'message' => $oauth_message,
			];
		}

		return [
			'success' => $all_passed,
			'message' => $all_passed ? __( 'All checks passed', 'creatorreactor' ) : __( 'Some checks failed', 'creatorreactor' ),
			'checks' => $checks,
		];
	}

	/**
	 * Headers for Fanvue list endpoints (docs require X-Fanvue-API-Version; alias kept for compatibility).
	 *
	 * @param string $token Bearer access token.
	 * @return array<string, string>
	 */
	private static function list_get_headers( $token ) {
		$opts = Admin_Settings::get_options();
		$ver  = isset( $opts['creatorreactor_api_version'] ) && (string) $opts['creatorreactor_api_version'] !== ''
			? (string) $opts['creatorreactor_api_version']
			: '2025-06-26';

		return [
			'Authorization'                => 'Bearer ' . $token,
			'X-Fanvue-API-Version'         => $ver,
			'X-CreatorReactor-API-Version' => $ver,
			'Content-Type'                 => 'application/json',
		];
	}

	/**
	 * Short hint when Fanvue returns 403 insufficient scopes for list APIs (read:fan).
	 */
	public static function get_insufficient_scopes_hint_text() {
		return __( 'Add read:fan under Advanced → Scopes (if missing), save, then disconnect OAuth and connect again—existing tokens do not pick up new scopes. Subscriber and follower lists require read:fan on both the Fanvue app and this field.', 'creatorreactor' );
	}

	/**
	 * @param string $list_label e.g. "List subscribers".
	 */
	private static function format_list_endpoint_http_error( $list_label, $code, $body_response ) {
		if ( (int) $code === 403 && self::response_indicates_insufficient_scopes( $body_response ) ) {
			return $list_label . ': HTTP 403 — ' . __( 'Insufficient OAuth scopes.', 'creatorreactor' ) . ' ' . self::get_insufficient_scopes_hint_text();
		}
		$snippet = is_string( $body_response ) && $body_response !== '' ? substr( wp_strip_all_tags( $body_response ), 0, 500 ) : '';
		return $list_label . ': HTTP ' . $code . ( $snippet !== '' ? '. Response: ' . $snippet : '' );
	}

	private static function response_indicates_insufficient_scopes( $body_response ) {
		if ( ! is_string( $body_response ) || $body_response === '' ) {
			return false;
		}
		if ( stripos( $body_response, 'Insufficient scopes' ) !== false ) {
			return true;
		}
		$decoded = json_decode( $body_response, true );
		return is_array( $decoded ) && isset( $decoded['error'] ) && stripos( (string) $decoded['error'], 'Insufficient scopes' ) !== false;
	}

	/**
	 * Broker proxy returns the same JSON shape as direct API calls; WP_Error means not connected or HTTP error.
	 *
	 * @param array|\WP_Error|null $result Raw API response.
	 * @return array{data: array, pagination: array}|null
	 */
	public static function normalize_broker_list_response( $result ) {
		if ( $result === null || is_wp_error( $result ) ) {
			if ( is_wp_error( $result ) ) {
				Admin_Settings::set_last_error( $result->get_error_message() );
			}
			return null;
		}
		if ( ! is_array( $result ) ) {
			return null;
		}

		return [
			'data'       => isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : [],
			'pagination' => isset( $result['pagination'] ) && is_array( $result['pagination'] ) ? $result['pagination'] : [ 'page' => 1, 'size' => 0, 'hasMore' => false ],
		];
	}

	public function list_subscribers( $page = 1, $size = 50, $quiet = false ) {
		try {
			$token = $this->get_access_token();
			if ( ! $token ) {
				if ( ! $quiet ) {
					Admin_Settings::set_last_error( __( 'No OAuth token. Connect to creatorreactor in the OAuth tab, then run Sync again.', 'creatorreactor' ) );
				}
				return null;
			}

			$opts       = Admin_Settings::get_options();
			$base       = rtrim( $opts['creatorreactor_api_base_url'] ?? CreatorReactor_OAuth::API_BASE_URL, '/' );
			$creator_id = isset( $opts['creatorreactor_creator_id'] ) ? trim( (string) $opts['creatorreactor_creator_id'] ) : '';

			if ( $creator_id !== '' ) {
				$url = $base . '/creators/' . rawurlencode( $creator_id ) . '/subscribers';
			} else {
				$url = $base . '/subscribers';
			}

			$url = add_query_arg(
				[
					'page' => max( 1, (int) $page ),
					'size' => max( 1, min( 50, (int) $size ) ),
				],
				$url
			);

			$response = wp_remote_get(
				$url,
				[
					'timeout' => 20,
					'headers' => self::list_get_headers( $token ),
				]
			);

			$code          = wp_remote_retrieve_response_code( $response );
			$body_response = wp_remote_retrieve_body( $response );
			if ( $code !== 200 ) {
				if ( ! $quiet ) {
					Admin_Settings::set_last_error( self::format_list_endpoint_http_error( 'List subscribers', $code, $body_response ) );
				}
				return null;
			}

			$data = json_decode( $body_response, true );
			if ( ! is_array( $data ) ) {
				if ( ! $quiet ) {
					$snippet = is_string( $body_response ) && $body_response !== '' ? substr( wp_strip_all_tags( $body_response ), 0, 500 ) : '';
					Admin_Settings::set_last_error( 'Invalid JSON from subscribers API.' . ( $snippet !== '' ? ' Response: ' . $snippet : '' ) );
				}
				return null;
			}

			return [
				'data'       => isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : [],
				'pagination' => isset( $data['pagination'] ) && is_array( $data['pagination'] ) ? $data['pagination'] : [ 'page' => 1, 'size' => 0, 'hasMore' => false ],
			];
		} catch ( \Throwable $e ) {
			if ( ! $quiet ) {
				Admin_Settings::set_critical_error( __( 'List subscribers failed:', 'creatorreactor' ) . ' ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
				Admin_Settings::set_last_error( $e->getMessage() );
			}
			return null;
		}
	}

	public function list_followers( $page = 1, $size = 50, $quiet = false ) {
		try {
			$token = $this->get_access_token();
			if ( ! $token ) {
				if ( ! $quiet ) {
					Admin_Settings::set_last_error( __( 'No OAuth token. Connect to creatorreactor in the OAuth tab, then run Sync again.', 'creatorreactor' ) );
				}
				return null;
			}

			$opts = Admin_Settings::get_options();
			$base = rtrim( $opts['creatorreactor_api_base_url'] ?? CreatorReactor_OAuth::API_BASE_URL, '/' );
			$url  = $base . '/followers';

			$url = add_query_arg(
				[
					'page' => max( 1, (int) $page ),
					'size' => max( 1, min( 50, (int) $size ) ),
				],
				$url
			);

			$response = wp_remote_get(
				$url,
				[
					'timeout' => 20,
					'headers' => self::list_get_headers( $token ),
				]
			);

			$code          = wp_remote_retrieve_response_code( $response );
			$body_response = wp_remote_retrieve_body( $response );
			if ( $code !== 200 ) {
				if ( ! $quiet ) {
					Admin_Settings::set_last_error( self::format_list_endpoint_http_error( 'List followers', $code, $body_response ) );
				}
				return null;
			}

			$data = json_decode( $body_response, true );
			if ( ! is_array( $data ) ) {
				if ( ! $quiet ) {
					$snippet = is_string( $body_response ) && $body_response !== '' ? substr( wp_strip_all_tags( $body_response ), 0, 500 ) : '';
					Admin_Settings::set_last_error( 'Invalid JSON from followers API.' . ( $snippet !== '' ? ' Response: ' . $snippet : '' ) );
				}
				return null;
			}

			return [
				'data'       => isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : [],
				'pagination' => isset( $data['pagination'] ) && is_array( $data['pagination'] ) ? $data['pagination'] : [ 'page' => 1, 'size' => 0, 'hasMore' => false ],
			];
		} catch ( \Throwable $e ) {
			if ( ! $quiet ) {
				Admin_Settings::set_critical_error( __( 'List followers failed:', 'creatorreactor' ) . ' ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
				Admin_Settings::set_last_error( $e->getMessage() );
			}
			return null;
		}
	}

	public function get_profile() {
		try {
			$token = $this->get_access_token();
			if ( ! $token ) {
				return null;
			}

			return self::fetch_profile_with_access_token( $token );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * GET /me (or fallbacks) with an arbitrary bearer token (e.g. fan OAuth).
	 *
	 * @param string $access_token OAuth access token.
	 * @return array<string, mixed>|null Decoded JSON or null.
	 */
	public static function fetch_profile_with_access_token( $access_token ) {
		$access_token = is_string( $access_token ) ? trim( $access_token ) : '';
		if ( $access_token === '' ) {
			return null;
		}

		try {
			$opts = Admin_Settings::get_options();
			$base = rtrim( $opts['creatorreactor_api_base_url'] ?? CreatorReactor_OAuth::API_BASE_URL, '/' );

			$api_version = isset( $opts['creatorreactor_api_version'] ) ? $opts['creatorreactor_api_version'] : '2025-06-26';
			$profile_result = self::fetch_profile_response( $base, $access_token, $api_version );
			$response      = $profile_result['response'];

			if ( is_wp_error( $response ) ) {
				return null;
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( $code !== 200 ) {
				return null;
			}

			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
			return is_array( $decoded ) ? $decoded : null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	public static function item_display_name( $item ) {
		if ( ! is_array( $item ) ) {
			return null;
		}
		if ( ! empty( $item['displayName'] ) && is_string( $item['displayName'] ) ) {
			return trim( $item['displayName'] );
		}
		if ( ! empty( $item['handle'] ) && is_string( $item['handle'] ) ) {
			$h = trim( $item['handle'] );
			return $h !== '' ? ( strpos( $h, '@' ) === 0 ? $h : '@' . $h ) : null;
		}
		if ( ! empty( $item['nickname'] ) && is_string( $item['nickname'] ) ) {
			return trim( $item['nickname'] );
		}
		return null;
	}

	public static function normalize_tier( $tier ) {
		if ( $tier === null || $tier === '' ) {
			return null;
		}
		if ( is_string( $tier ) ) {
			return sanitize_text_field( $tier );
		}
		if ( is_array( $tier ) ) {
			if ( ! empty( $tier['id'] ) && is_string( $tier['id'] ) ) {
				return sanitize_text_field( $tier['id'] );
			}
			if ( ! empty( $tier['name'] ) && is_string( $tier['name'] ) ) {
				return sanitize_text_field( $tier['name'] );
			}
		}
		return null;
	}

	public static function tier_definition_from_item( $tier_raw ) {
		if ( $tier_raw === null || $tier_raw === '' ) {
			return null;
		}
		if ( is_string( $tier_raw ) ) {
			$s = sanitize_text_field( $tier_raw );
			return $s !== '' ? [ 'id' => $s, 'name' => $s ] : null;
		}
		if ( is_array( $tier_raw ) ) {
			$id   = isset( $tier_raw['id'] ) && is_string( $tier_raw['id'] ) ? sanitize_text_field( $tier_raw['id'] ) : '';
			$name = isset( $tier_raw['name'] ) && is_string( $tier_raw['name'] ) ? sanitize_text_field( $tier_raw['name'] ) : ( $id !== '' ? $id : '' );
			if ( $id === '' && $name === '' ) {
				return null;
			}
			return [
				'id'   => $id !== '' ? $id : $name,
				'name' => $name !== '' ? $name : $id,
			];
		}
		return null;
	}

	/**
	 * JSON blob of Fanvue list fields not stored in dedicated entitlement columns (handle, registeredAt, tier object, etc.).
	 *
	 * @param array<string, mixed> $item Subscriber or follower row from the API.
	 * @return string|null JSON or null when nothing extra to store.
	 */
	public static function fanvue_list_item_sync_snapshot_json( $item ) {
		if ( ! is_array( $item ) ) {
			return null;
		}
		$skip_email = [ 'email', 'Email' ];
		$extra      = [];
		foreach ( $item as $k => $v ) {
			if ( ! is_string( $k ) || $k === '' ) {
				continue;
			}
			if ( $k === 'uuid' ) {
				continue;
			}
			if ( in_array( $k, $skip_email, true ) ) {
				continue;
			}
			if ( is_scalar( $v ) || $v === null ) {
				$extra[ $k ] = $v;
			} elseif ( is_array( $v ) ) {
				$extra[ $k ] = $v;
			}
		}
		if ( empty( $extra ) ) {
			return null;
		}
		$json = wp_json_encode( $extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) || $json === '' || $json === '[]' || $json === '{}' ) {
			return null;
		}
		return $json;
	}

	/**
	 * Whether a subscriber or follower list item is the fan who just completed OAuth (UUID and/or email).
	 *
	 * @param array<string, mixed> $item List row from Fanvue API.
	 */
	private static function fan_list_item_matches_fan( array $item, $fan_uuid_norm, $email_norm ) {
		$fan_uuid_norm = is_string( $fan_uuid_norm ) ? strtolower( trim( $fan_uuid_norm ) ) : '';
		$email_norm    = is_string( $email_norm ) ? strtolower( trim( $email_norm ) ) : '';
		if ( $fan_uuid_norm !== '' ) {
			$u = isset( $item['uuid'] ) ? strtolower( trim( (string) $item['uuid'] ) ) : '';
			if ( $u !== '' && $u === $fan_uuid_norm ) {
				return true;
			}
		}
		if ( $email_norm !== '' && ! empty( $item['email'] ) && is_string( $item['email'] ) ) {
			$ie = strtolower( trim( $item['email'] ) );
			if ( $ie !== '' && $ie === $email_norm ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * After Fanvue visitor OAuth: find this user in the site creator's subscriber/follower lists and upsert entitlements with wp_user_id.
	 *
	 * Uses the creator OAuth token stored in settings (same app as fan login). Requires read:fan on that token for list APIs.
	 *
	 * @param string $fan_uuid     Fanvue UUID from /me (may be empty).
	 * @param int    $wp_user_id  WordPress user ID.
	 * @param string $fan_email   Fan email (used for list matching and row storage).
	 * @param string $display_name Fallback display name.
	 * @return bool True if the user was found on a list and the entitlement row was updated.
	 */
	public static function sync_entitlement_for_fan_after_login( $fan_uuid, $wp_user_id, $fan_email, $display_name ) {
		if ( Admin_Settings::is_broker_mode() ) {
			return false;
		}
		$wp_user_id = (int) $wp_user_id;
		if ( $wp_user_id <= 0 ) {
			return false;
		}
		$fan_email = is_string( $fan_email ) ? sanitize_email( $fan_email ) : '';
		if ( $fan_email === '' || ! is_email( $fan_email ) ) {
			return false;
		}
		$email_norm    = strtolower( $fan_email );
		$fan_uuid_norm = is_string( $fan_uuid ) ? strtolower( trim( $fan_uuid ) ) : '';

		$token = self::get_access_token();
		if ( ! $token ) {
			return false;
		}

		$opts       = Admin_Settings::get_options();
		$ttl        = (int) ( $opts['entitlement_cache_ttl_seconds'] ?? 900 );
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + max( 60, $ttl ) );
		$client     = new self();
		$size       = 50;

		$page = 1;
		do {
			$result = $client->list_subscribers( $page, $size, true );
			if ( $result === null ) {
				break;
			}
			foreach ( $result['data'] as $item ) {
				if ( ! is_array( $item ) || ! self::fan_list_item_matches_fan( $item, $fan_uuid_norm, $email_norm ) ) {
					continue;
				}
				$row_uuid = isset( $item['uuid'] ) ? sanitize_text_field( (string) $item['uuid'] ) : '';
				if ( $row_uuid === '' ) {
					continue;
				}
				$row_email    = ! empty( $item['email'] ) && is_string( $item['email'] ) ? sanitize_email( $item['email'] ) : $fan_email;
				$tier_raw     = isset( $item['tier'] ) ? $item['tier'] : null;
				$tier         = self::normalize_tier( $tier_raw );
				$stored_tier  = Entitlements::tier_stored_for_subscriber( Entitlements::PRODUCT_FANVUE, $tier );
				$disp         = self::item_display_name( $item );
				$disp_stored  = is_string( $disp ) && $disp !== '' ? $disp : ( is_string( $display_name ) ? sanitize_text_field( $display_name ) : '' );
				$def          = self::tier_definition_from_item( $tier_raw );
				$snapshot     = self::fanvue_list_item_sync_snapshot_json( $item );
				Entitlements::upsert_by_creatorreactor_uuid(
					$row_uuid,
					Entitlements::STATUS_ACTIVE,
					$expires_at,
					$wp_user_id,
					$row_email !== '' ? $row_email : $fan_email,
					$stored_tier,
					$disp_stored !== '' ? $disp_stored : null,
					Entitlements::PRODUCT_FANVUE,
					$snapshot
				);
				update_user_meta( $wp_user_id, Entitlements::USERMETA_CREATORREACTOR_UUID, $row_uuid );
				$existing = get_option( Admin_Settings::OPTION_TIERS, [] );
				$merged   = is_array( $existing ) ? $existing : [];
				$merged[ $stored_tier ] = true;
				update_option( Admin_Settings::OPTION_TIERS, array_keys( $merged ) );
				if ( $def !== null ) {
					$subs_tiers = get_option( Admin_Settings::OPTION_SUBSCRIPTION_TIERS, [] );
					$by_id      = [];
					if ( is_array( $subs_tiers ) ) {
						foreach ( $subs_tiers as $row ) {
							if ( is_array( $row ) && ! empty( $row['id'] ) ) {
								$by_id[ (string) $row['id'] ] = $row;
							}
						}
					}
					$by_id[ $def['id'] ] = $def;
					update_option( Admin_Settings::OPTION_SUBSCRIPTION_TIERS, array_values( $by_id ) );
				}
				return true;
			}
			$pagination = $result['pagination'];
			$has_more   = ! empty( $pagination['hasMore'] );
			$items      = $result['data'];
			++$page;
		} while ( $has_more && count( $items ) === $size );

		$page = 1;
		do {
			$result = $client->list_followers( $page, $size, true );
			if ( $result === null ) {
				break;
			}
			foreach ( $result['data'] as $item ) {
				if ( ! is_array( $item ) || ! self::fan_list_item_matches_fan( $item, $fan_uuid_norm, $email_norm ) ) {
					continue;
				}
				$row_uuid = isset( $item['uuid'] ) ? sanitize_text_field( (string) $item['uuid'] ) : '';
				if ( $row_uuid === '' && isset( $item['id'] ) && is_string( $item['id'] ) ) {
					$row_uuid = sanitize_text_field( $item['id'] );
				}
				if ( $row_uuid === '' ) {
					continue;
				}
				$row_email   = ! empty( $item['email'] ) && is_string( $item['email'] ) ? sanitize_email( $item['email'] ) : $fan_email;
				$disp        = self::item_display_name( $item );
				$disp_stored = is_string( $disp ) && $disp !== '' ? $disp : ( is_string( $display_name ) ? sanitize_text_field( $display_name ) : '' );
				$snapshot    = self::fanvue_list_item_sync_snapshot_json( $item );
				Entitlements::upsert_by_creatorreactor_uuid(
					$row_uuid,
					Entitlements::STATUS_ACTIVE,
					$expires_at,
					$wp_user_id,
					$row_email !== '' ? $row_email : $fan_email,
					Entitlements::tier_stored_for_follower( Entitlements::PRODUCT_FANVUE ),
					$disp_stored !== '' ? $disp_stored : null,
					Entitlements::PRODUCT_FANVUE,
					$snapshot
				);
				update_user_meta( $wp_user_id, Entitlements::USERMETA_CREATORREACTOR_UUID, $row_uuid );
				return true;
			}
			$pagination = $result['pagination'];
			$has_more   = ! empty( $pagination['hasMore'] );
			$items      = $result['data'];
			++$page;
		} while ( $has_more && count( $items ) === $size );

		return false;
	}

	public function sync_subscribers_to_table( $cache_ttl_seconds = 900 ) {
		return self::sync_subscribers_to_table_with_listers(
			$cache_ttl_seconds,
			function ( $page, $size ) {
				return $this->list_subscribers( $page, $size );
			},
			function ( $page, $size ) {
				return $this->list_followers( $page, $size );
			}
		);
	}

	/**
	 * Sync subscribers and followers into the entitlements table. List callbacks must return the same shape as
	 * {@see list_subscribers()} / {@see list_followers()} or null on failure.
	 *
	 * @param int                                                                 $cache_ttl_seconds Cache TTL for expires_at.
	 * @param callable(int,int): (array{data: array, pagination: array}|null) $list_subscribers  Page/size → subscriber page.
	 * @param callable(int,int): (array{data: array, pagination: array}|null) $list_followers    Page/size → follower page.
	 * @return bool True if at least one API page succeeded.
	 */
	public static function sync_subscribers_to_table_with_listers( $cache_ttl_seconds, callable $list_subscribers, callable $list_followers ) {
		try {
			Entitlements::clear_fanvue_sync_user_resolve_cache();

			$expires_at           = gmdate( 'Y-m-d H:i:s', time() + $cache_ttl_seconds );
			$active_uuids         = [];
			$active_follower_uuids = [];
			$seen_tiers           = [];
			$tier_definitions     = [];
			$page                 = 1;
			$size                 = 50;
			$any_ok               = false;

			do {
				$result = $list_subscribers( $page, $size );
				if ( $result === null ) {
					break;
				}
				$any_ok = true;
				$items  = $result['data'];
				foreach ( $items as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$uuid = isset( $item['uuid'] ) ? $item['uuid'] : null;
					if ( ! $uuid || ! is_string( $uuid ) ) {
						continue;
					}
					$active_uuids[] = $uuid;
					$email_raw      = isset( $item['email'] ) ? $item['email'] : '';
					$email          = is_string( $email_raw ) ? sanitize_email( $email_raw ) : '';
					$tier_raw       = isset( $item['tier'] ) ? $item['tier'] : null;
					$tier           = self::normalize_tier( $tier_raw );
					$display_name   = self::item_display_name( $item );
					$stored_tier    = Entitlements::tier_stored_for_subscriber( Entitlements::PRODUCT_FANVUE, $tier );
					if ( $tier !== null ) {
						$seen_tiers[ $stored_tier ] = true;
						$def                        = self::tier_definition_from_item( $tier_raw );
						if ( $def !== null && ! isset( $tier_definitions[ $def['id'] ] ) ) {
							$tier_definitions[ $def['id'] ] = $def;
						}
					} else {
						$seen_tiers[ $stored_tier ] = true;
					}
					$wp_uid    = Entitlements::resolve_wp_user_id_for_fanvue_sync( $email, $uuid );
					$snapshot  = self::fanvue_list_item_sync_snapshot_json( $item );
					Entitlements::upsert_by_creatorreactor_uuid(
						$uuid,
						Entitlements::STATUS_ACTIVE,
						$expires_at,
						$wp_uid,
						$email,
						$stored_tier,
						$display_name,
						Entitlements::PRODUCT_FANVUE,
						$snapshot
					);
				}
				$pagination = $result['pagination'];
				$has_more   = ! empty( $pagination['hasMore'] );
				$page++;
			} while ( $has_more && count( $items ) === $size );

			Entitlements::mark_missing_subscribers_as_inactive( $active_uuids, $expires_at, Entitlements::PRODUCT_FANVUE );

			if ( $any_ok ) {
				$existing = get_option( Admin_Settings::OPTION_TIERS, [] );
				$merged   = is_array( $existing ) ? $existing : [];
				foreach ( array_keys( $seen_tiers ) as $st ) {
					$merged[ $st ] = true;
				}
				update_option( Admin_Settings::OPTION_TIERS, array_keys( $merged ) );
				if ( ! empty( $tier_definitions ) ) {
					update_option( Admin_Settings::OPTION_SUBSCRIPTION_TIERS, array_values( $tier_definitions ) );
				}
			}

			$followers_page = 1;
			$followers_size = 50;
			do {
				$followers_result = $list_followers( $followers_page, $followers_size );
				if ( $followers_result === null ) {
					break;
				}
				$followers = $followers_result['data'];
				foreach ( $followers as $follower ) {
					if ( ! is_array( $follower ) ) {
						continue;
					}
					$uuid = isset( $follower['uuid'] ) ? $follower['uuid'] : ( isset( $follower['id'] ) ? $follower['id'] : null );
					if ( ! $uuid || ! is_string( $uuid ) ) {
						continue;
					}
					$active_follower_uuids[] = $uuid;
					$email_raw               = isset( $follower['email'] ) ? $follower['email'] : '';
					$email                   = is_string( $email_raw ) ? sanitize_email( $email_raw ) : '';
					$display_name            = self::item_display_name( $follower );
					$wp_uid                  = Entitlements::resolve_wp_user_id_for_fanvue_sync( $email, $uuid );
					$snapshot                = self::fanvue_list_item_sync_snapshot_json( $follower );
					Entitlements::upsert_by_creatorreactor_uuid(
						$uuid,
						Entitlements::STATUS_ACTIVE,
						$expires_at,
						$wp_uid,
						$email,
						Entitlements::tier_stored_for_follower( Entitlements::PRODUCT_FANVUE ),
						$display_name,
						Entitlements::PRODUCT_FANVUE,
						$snapshot
					);
				}
				if ( count( $followers ) > 0 ) {
					$any_ok = true;
				}
				$pagination = $followers_result['pagination'];
				$has_more   = ! empty( $pagination['hasMore'] );
				$followers_page++;
			} while ( $has_more && count( $followers ) === $followers_size );

			Entitlements::mark_missing_followers_as_inactive( $active_follower_uuids, $expires_at, Entitlements::PRODUCT_FANVUE );

			return $any_ok;
		} catch ( \Throwable $e ) {
			Admin_Settings::set_critical_error( __( 'Sync subscribers failed:', 'creatorreactor' ) . ' ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
			Admin_Settings::set_last_error( $e->getMessage() );
			return false;
		}
	}
}
