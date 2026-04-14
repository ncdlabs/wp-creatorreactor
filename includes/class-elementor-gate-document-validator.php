<?php
/**
 * Elementor document: detect more than one CreatorReactor content gate in the same layout node
 * (same rule as the editor canvas script). Prevents ambiguous layouts for server strip / guests.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Elementor_Gate_Document_Validator {

	public const META_VIOLATIONS = '_creatorreactor_elementor_gate_violations';

	public static function init() {
		add_action( 'elementor/document/save', [ __CLASS__, 'on_document_save' ], 99, 2 );
		add_action( 'admin_notices', [ __CLASS__, 'maybe_admin_notice' ] );
	}

	/**
	 * @param mixed $document Elementor document.
	 * @param mixed $data     Save payload.
	 */
	public static function on_document_save( $document, $data = null ): void {
		if ( ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
			return;
		}
		$post_id = (int) $document->get_main_id();
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$elements = null;
		if ( is_array( $data ) && isset( $data['elements'] ) && is_array( $data['elements'] ) ) {
			$elements = $data['elements'];
		} elseif ( method_exists( $document, 'get_elements_data' ) ) {
			$elements = $document->get_elements_data();
		}
		if ( ! is_array( $elements ) || $elements === [] ) {
			delete_post_meta( $post_id, self::META_VIOLATIONS );
			return;
		}

		$violations = self::scan_elements_tree( $elements );
		if ( $violations === [] ) {
			delete_post_meta( $post_id, self::META_VIOLATIONS );
			return;
		}
		update_post_meta( $post_id, self::META_VIOLATIONS, wp_json_encode( $violations ) );
	}

	public static function maybe_admin_notice(): void {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'post' || $screen->parent_base !== 'edit' ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display.
		$post_id = isset( $_GET['post'] ) ? (int) wp_unslash( $_GET['post'] ) : 0;
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = get_post_meta( $post_id, self::META_VIOLATIONS, true );
		if ( ! is_string( $raw ) || $raw === '' ) {
			return;
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || $decoded === [] ) {
			return;
		}
		$count = count( $decoded );
		$msg   = sprintf(
			/* translators: %d: number of Elementor containers with duplicate gates */
			_n(
				'CreatorReactor: %d Elementor container has more than one content gate. Guests may see wrong or empty content until you keep a single gate per container (Fanvue login widgets do not count as a gate).',
				'CreatorReactor: %d Elementor containers have more than one content gate. Guests may see wrong or empty content until you keep a single gate per container (Fanvue login widgets do not count as a gate).',
				$count,
				'wp-creatorreactor'
			),
			$count
		);
		echo '<div class="notice notice-warning"><p>' . esc_html( $msg ) . '</p></div>';
	}

	/**
	 * @param list<array<string, mixed>> $elements Top-level or child `elements` array from Elementor JSON.
	 * @return list<array{id: string, gates: int}>
	 */
	public static function scan_elements_tree( array $elements ): array {
		$violations = [];
		foreach ( $elements as $node ) {
			if ( is_array( $node ) ) {
				$violations = array_merge( $violations, self::scan_node( $node ) );
			}
		}
		return $violations;
	}

	/**
	 * @param array<string, mixed> $node Elementor element node.
	 * @return list<array{id: string, gates: int}>
	 */
	private static function scan_node( array $node ): array {
		$violations = [];
		$children   = isset( $node['elements'] ) && is_array( $node['elements'] ) ? $node['elements'] : [];
		if ( $children !== [] ) {
			$gates = 0;
			foreach ( $children as $child ) {
				if ( is_array( $child )
					&& ( $child['elType'] ?? '' ) === 'widget'
					&& self::is_content_gate_widget_type( isset( $child['widgetType'] ) ? (string) $child['widgetType'] : '' ) ) {
					++$gates;
				}
			}
			if ( $gates > 1 ) {
				$violations[] = [
					'id'    => isset( $node['id'] ) ? (string) $node['id'] : '',
					'gates' => $gates,
				];
			}
			foreach ( $children as $child ) {
				if ( is_array( $child ) && isset( $child['elements'] ) && is_array( $child['elements'] ) && $child['elements'] !== [] ) {
					$violations = array_merge( $violations, self::scan_node( $child ) );
				}
			}
		}
		return $violations;
	}

	private static function is_content_gate_widget_type( string $widget_type ): bool {
		if ( $widget_type === '' || strpos( $widget_type, 'creatorreactor_' ) !== 0 ) {
			return false;
		}
		return $widget_type !== 'creatorreactor_fanvue_oauth';
	}
}
