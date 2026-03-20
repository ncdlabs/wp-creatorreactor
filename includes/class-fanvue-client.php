<?php
/**
 * Fanvue API client: OAuth 2.0 access token + list subscribers and followers.
 *
 * @package FanBridge
 * @author  ncdLabs
 * @company ncdLabs
 */

namespace FanBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fanvue_Client {

	public static function get_access_token() {
		return Fanvue_OAuth::get_access_token();
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
					$headers['X-Fanvue-API-Version'] = $version_header;
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
		return self::fetch_first_non_404_response( $base, $token, $api_version, [ '/me', '/creator/me', '/creators/me' ] );
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
		$client_id = ! empty( $opts['fanvue_oauth_client_id'] );
		$client_secret = ! empty( $opts['fanvue_oauth_client_secret'] );
		$base = rtrim( $opts['fanvue_api_base_url'] ?? 'https://api.fanvue.com', '/' );

		$checks[] = [
			'label' => __( 'OAuth Client ID', 'fanbridge' ),
			'pass' => $client_id,
			'message' => $client_id ? __( 'Configured', 'fanbridge' ) : __( 'Not configured', 'fanbridge' ),
		];
		if ( ! $client_id ) {
			$all_passed = false;
		}

		$checks[] = [
			'label' => __( 'OAuth Client Secret', 'fanbridge' ),
			'pass' => $client_secret,
			'message' => $client_secret ? __( 'Configured', 'fanbridge' ) : __( 'Not configured', 'fanbridge' ),
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
					$api_message = sprintf( __( '%1$s — HTTP %2$d (reachable, auth required)', 'fanbridge' ), $base, $code );
				} else {
					$api_message = sprintf( __( '%1$s — HTTP %2$d', 'fanbridge' ), $base, $code );
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
			'label' => __( 'API Endpoint Reachable', 'fanbridge' ),
			'pass' => $api_reachable,
			'message' => $api_message ?: ( $api_reachable ? __( 'OK', 'fanbridge' ) : __( 'Failed', 'fanbridge' ) ),
		];

		$token = self::get_access_token();
		$checks[] = [
			'label' => __( 'OAuth Token', 'fanbridge' ),
			'pass' => ! empty( $token ),
			'message' => ! empty( $token ) ? __( 'Valid', 'fanbridge' ) : __( 'Not authenticated', 'fanbridge' ),
		];
		if ( ! $token ) {
			$all_passed = false;
		}

		if ( $token ) {
			$api_version = isset( $opts['fanvue_api_version'] ) ? $opts['fanvue_api_version'] : '2025-06-26';
			$creator_id = isset( $opts['fanvue_creator_id'] ) ? trim( (string) $opts['fanvue_creator_id'] ) : '';
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
							$oauth_message = sprintf( __( 'Connected as %s', 'fanbridge' ), $name );
						} else {
							$oauth_message = __( 'Authenticated', 'fanbridge' );
						}
						if ( ! is_string( $version_header ) || $version_header === '' ) {
							$oauth_message .= __( ' (without API version header)', 'fanbridge' );
						}
					} elseif ( $code === 401 ) {
						$oauth_message = __( 'Token expired or invalid', 'fanbridge' );
						$all_passed = false;
					} elseif ( $code === 403 ) {
						$oauth_valid = true;
						$oauth_message = sprintf( __( 'Authenticated, but access denied for %s (HTTP 403)', 'fanbridge' ), $endpoint ?: '/me' );
					} elseif ( $code === 404 ) {
						$oauth_message = sprintf( __( 'No OAuth verification endpoint found (last tried %s)', 'fanbridge' ), $endpoint ?: '/me' );
						$all_passed = false;
					} else {
						$oauth_message = sprintf( __( 'HTTP %d', 'fanbridge' ), $code );
						$all_passed = false;
					}
				}
			} catch ( \Throwable $e ) {
				$oauth_message = $e->getMessage();
				$all_passed = false;
			}
			$checks[] = [
				'label' => __( 'OAuth Credentials', 'fanbridge' ),
				'pass' => $oauth_valid,
				'message' => $oauth_message,
			];
		}

		return [
			'success' => $all_passed,
			'message' => $all_passed ? __( 'All checks passed', 'fanbridge' ) : __( 'Some checks failed', 'fanbridge' ),
			'checks' => $checks,
		];
	}

	public function list_subscribers( $page = 1, $size = 50 ) {
		try {
			$token = $this->get_access_token();
			if ( ! $token ) {
				Admin_Settings::set_last_error( __( 'No OAuth token. Connect to Fanvue in the OAuth tab, then run Sync again.', 'fanbridge' ) );
				return null;
			}

			$opts       = Admin_Settings::get_options();
			$base       = rtrim( $opts['fanvue_api_base_url'] ?? 'https://api.fanvue.com', '/' );
			$creator_id = isset( $opts['fanvue_creator_id'] ) ? trim( (string) $opts['fanvue_creator_id'] ) : '';

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
					'headers' => [
						'Authorization'        => 'Bearer ' . $token,
						'X-Fanvue-API-Version' => '2025-06-26',
						'Content-Type'         => 'application/json',
					],
				]
			);

			$code          = wp_remote_retrieve_response_code( $response );
			$body_response = wp_remote_retrieve_body( $response );
			if ( $code !== 200 ) {
				$snippet = is_string( $body_response ) && $body_response !== '' ? substr( wp_strip_all_tags( $body_response ), 0, 500 ) : '';
				Admin_Settings::set_last_error( 'List subscribers: HTTP ' . $code . ( $snippet !== '' ? '. Response: ' . $snippet : '' ) );
				return null;
			}

			$data = json_decode( $body_response, true );
			if ( ! is_array( $data ) ) {
				$snippet = is_string( $body_response ) && $body_response !== '' ? substr( wp_strip_all_tags( $body_response ), 0, 500 ) : '';
				Admin_Settings::set_last_error( 'Invalid JSON from subscribers API.' . ( $snippet !== '' ? ' Response: ' . $snippet : '' ) );
				return null;
			}

			return [
				'data'       => isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : [],
				'pagination' => isset( $data['pagination'] ) && is_array( $data['pagination'] ) ? $data['pagination'] : [ 'page' => 1, 'size' => 0, 'hasMore' => false ],
			];
		} catch ( \Throwable $e ) {
			Admin_Settings::set_critical_error( __( 'List subscribers failed:', 'fanbridge' ) . ' ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
			Admin_Settings::set_last_error( $e->getMessage() );
			return null;
		}
	}

	public function list_followers( $page = 1, $size = 50 ) {
		try {
			$token = $this->get_access_token();
			if ( ! $token ) {
				Admin_Settings::set_last_error( __( 'No OAuth token. Connect to Fanvue in the OAuth tab, then run Sync again.', 'fanbridge' ) );
				return null;
			}

			$opts = Admin_Settings::get_options();
			$base = rtrim( $opts['fanvue_api_base_url'] ?? 'https://api.fanvue.com', '/' );
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
					'headers' => [
						'Authorization'        => 'Bearer ' . $token,
						'X-Fanvue-API-Version' => '2025-06-26',
						'Content-Type'         => 'application/json',
					],
				]
			);

			$code          = wp_remote_retrieve_response_code( $response );
			$body_response = wp_remote_retrieve_body( $response );
			if ( $code !== 200 ) {
				$snippet = is_string( $body_response ) && $body_response !== '' ? substr( wp_strip_all_tags( $body_response ), 0, 500 ) : '';
				Admin_Settings::set_last_error( 'List followers: HTTP ' . $code . ( $snippet !== '' ? '. Response: ' . $snippet : '' ) );
				return null;
			}

			$data = json_decode( $body_response, true );
			if ( ! is_array( $data ) ) {
				$snippet = is_string( $body_response ) && $body_response !== '' ? substr( wp_strip_all_tags( $body_response ), 0, 500 ) : '';
				Admin_Settings::set_last_error( 'Invalid JSON from followers API.' . ( $snippet !== '' ? ' Response: ' . $snippet : '' ) );
				return null;
			}

			return [
				'data'       => isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : [],
				'pagination' => isset( $data['pagination'] ) && is_array( $data['pagination'] ) ? $data['pagination'] : [ 'page' => 1, 'size' => 0, 'hasMore' => false ],
			];
		} catch ( \Throwable $e ) {
			Admin_Settings::set_critical_error( __( 'List followers failed:', 'fanbridge' ) . ' ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
			Admin_Settings::set_last_error( $e->getMessage() );
			return null;
		}
	}

	public function get_profile() {
		try {
			$token = $this->get_access_token();
			if ( ! $token ) {
				return null;
			}

			$opts = Admin_Settings::get_options();
			$base = rtrim( $opts['fanvue_api_base_url'] ?? 'https://api.fanvue.com', '/' );

			$api_version = isset( $opts['fanvue_api_version'] ) ? $opts['fanvue_api_version'] : '2025-06-26';
			$profile_result = self::fetch_profile_response( $base, $token, $api_version );
			$response = $profile_result['response'];

			if ( is_wp_error( $response ) ) {
				return null;
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( $code !== 200 ) {
				return null;
			}

			return json_decode( wp_remote_retrieve_body( $response ), true );
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

	public function sync_subscribers_to_table( $cache_ttl_seconds = 900 ) {
		try {
			$expires_at        = gmdate( 'Y-m-d H:i:s', time() + $cache_ttl_seconds );
			$active_uuids      = [];
			$seen_tiers        = [];
			$tier_definitions  = [];
			$page              = 1;
			$size              = 50;
			$any_ok            = false;

			do {
				$result = $this->list_subscribers( $page, $size );
				if ( $result === null ) {
					break;
				}
				$any_ok = true;
				$items  = $result['data'];
				foreach ( $items as $item ) {
					$uuid = isset( $item['uuid'] ) ? $item['uuid'] : null;
					if ( ! $uuid ) {
						continue;
					}
					$active_uuids[] = $uuid;
					$email          = isset( $item['email'] ) ? $item['email'] : '';
					$tier_raw       = isset( $item['tier'] ) ? $item['tier'] : null;
					$tier           = self::normalize_tier( $tier_raw );
					$display_name   = self::item_display_name( $item );
					if ( $tier !== null ) {
						$seen_tiers[ $tier ] = true;
						$def                 = self::tier_definition_from_item( $tier_raw );
						if ( $def !== null && ! isset( $tier_definitions[ $def['id'] ] ) ) {
							$tier_definitions[ $def['id'] ] = $def;
						}
					}
					Entitlements::upsert_by_fanvue_uuid( $uuid, Entitlements::STATUS_ACTIVE, $expires_at, null, $email, $tier, $display_name );
				}
				$pagination = $result['pagination'];
				$has_more   = ! empty( $pagination['hasMore'] );
				$page++;
			} while ( $has_more && count( $items ) === $size );

			Entitlements::mark_missing_as_inactive( $active_uuids, $expires_at );

			if ( $any_ok ) {
				$existing = get_option( Admin_Settings::OPTION_TIERS, [] );
				$merged   = is_array( $existing ) ? $existing : [];
				foreach ( array_keys( $seen_tiers ) as $t ) {
					$merged[ $t ] = true;
				}
				update_option( Admin_Settings::OPTION_TIERS, array_keys( $merged ) );
				if ( ! empty( $tier_definitions ) ) {
					update_option( Admin_Settings::OPTION_SUBSCRIPTION_TIERS, array_values( $tier_definitions ) );
				}
			}

			$followers_page = 1;
			$followers_size = 50;
			do {
				$followers_result = $this->list_followers( $followers_page, $followers_size );
				if ( $followers_result === null ) {
					break;
				}
				$followers = $followers_result['data'];
				foreach ( $followers as $follower ) {
					$uuid = isset( $follower['uuid'] ) ? $follower['uuid'] : ( isset( $follower['id'] ) ? $follower['id'] : null );
					if ( ! $uuid || ! is_string( $uuid ) ) {
						continue;
					}
					$email        = isset( $follower['email'] ) ? (string) $follower['email'] : '';
					$display_name = self::item_display_name( $follower );
					Entitlements::upsert_by_fanvue_uuid(
						$uuid,
						Entitlements::STATUS_INACTIVE,
						$expires_at,
						null,
						$email,
						Entitlements::TIER_FOLLOWER,
						$display_name
					);
				}
				if ( count( $followers ) > 0 ) {
					$any_ok = true;
				}
				$pagination = $followers_result['pagination'];
				$has_more   = ! empty( $pagination['hasMore'] );
				$followers_page++;
			} while ( $has_more && count( $followers ) === $followers_size );

			return $any_ok;
		} catch ( \Throwable $e ) {
			Admin_Settings::set_critical_error( __( 'Sync subscribers failed:', 'fanbridge' ) . ' ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
			Admin_Settings::set_last_error( $e->getMessage() );
			return false;
		}
	}
}
