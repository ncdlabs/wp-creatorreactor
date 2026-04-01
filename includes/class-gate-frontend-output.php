<?php
/**
 * Remove gated layout regions from HTML when the current visitor does not match,
 * so paid/restricted content is not present in “View Source” (CSS-only hiding is not security).
 *
 * Elementor: each gate marker is tied to the widget that rendered it. When a gate fails for the
 * current visitor, only that widget’s subtree is stripped (not an entire shared {@see e-con-inner}),
 * so mixed gates in one section (e.g. Follower + Logged out) do not blank each other for guests.
 *
 * @package CreatorReactor
 * @author  Lou Grossi
 * @company ncdLabs
 */

namespace CreatorReactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Gate_Frontend_Output {

	public static function init() {
		add_filter( 'elementor/frontend/the_content', [ __CLASS__, 'strip_unauthorized_gated_regions' ], 50 );
		add_filter( 'the_content', [ __CLASS__, 'strip_unauthorized_gated_regions' ], 99 );
	}

	/**
	 * @param string $content HTML.
	 * @return string
	 */
	public static function strip_unauthorized_gated_regions( $content ) {
		if ( ! is_string( $content ) || $content === '' ) {
			return $content;
		}
		// Security target for this pass: prevent gated paid markup from being present for public visitors.
		// Logged-in responses can involve role/preview states and should not be destructively stripped.
		if ( is_user_logged_in() || Role_Impersonation::effective_is_logged_in_for_creatorreactor_gates() ) {
			return $content;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $content;
		}
		if ( wp_is_json_request() ) {
			return $content;
		}
		if ( class_exists( __NAMESPACE__ . '\\Editor_Context' ) && Editor_Context::is_elementor_preview_request() ) {
			return $content;
		}
		if ( strpos( $content, 'creatorreactor-elementor-gate-marker' ) === false
			&& strpos( $content, 'creatorreactor-gutenberg-gate-marker' ) === false ) {
			return $content;
		}

		$stripped = self::strip_via_dom( $content );
		return is_string( $stripped ) ? $stripped : $content;
	}

	/**
	 * @param string $html HTML fragment.
	 * @return string|null
	 */
	private static function strip_via_dom( $html ) {
		if ( ! class_exists( '\DOMDocument' ) ) {
			return null;
		}

		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$dom->encoding = 'UTF-8';

		$wrapped = '<div id="creatorreactor-gate-strip-root">' . $html . '</div>';
		$loaded  = $dom->loadHTML(
			'<?xml encoding="utf-8"?>' . $wrapped,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		if ( ! $loaded ) {
			return null;
		}

		$xpath = new \DOMXPath( $dom );
		$nodes = $xpath->query(
			'//span[contains(@class,"creatorreactor-elementor-gate-marker") or contains(@class,"creatorreactor-gutenberg-gate-marker")]'
		);
		if ( ! $nodes || $nodes->length === 0 ) {
			return $html;
		}

		/** @var array<int, array{container:\DOMElement, markers: list<array{match:string, logic:string}>}> $groups */
		$groups = [];

		for ( $i = 0; $i < $nodes->length; $i++ ) {
			$marker = $nodes->item( $i );
			if ( ! $marker instanceof \DOMElement ) {
				continue;
			}
			$container = self::resolve_container_for_marker( $marker );
			if ( ! $container instanceof \DOMElement ) {
				continue;
			}
			$oid = spl_object_id( $container );
			if ( ! isset( $groups[ $oid ] ) ) {
				$groups[ $oid ] = [
					'container' => $container,
					'markers'   => [],
				];
			}
			$groups[ $oid ]['markers'][] = [
				'match' => $marker->getAttribute( 'data-creatorreactor-gate-match' ) === '1' ? '1' : '0',
				'logic' => $marker->getAttribute( 'data-creatorreactor-gate-logic' ) === 'or' ? 'or' : 'and',
			];
		}

		foreach ( $groups as $group ) {
			if ( ! self::container_should_show_content( $group['markers'] ) ) {
				self::remove_all_child_nodes( $group['container'] );
			}
		}

		$root = $dom->getElementById( 'creatorreactor-gate-strip-root' );
		if ( ! $root ) {
			return null;
		}

		$out = '';
		foreach ( $root->childNodes as $child ) {
			$out .= $dom->saveHTML( $child );
		}
		return $out;
	}

	/**
	 * @param list<array{match:string, logic:string}> $markers
	 */
	private static function container_should_show_content( array $markers ): bool {
		if ( $markers === [] ) {
			return true;
		}
		$logic = $markers[0]['logic'] ?? 'and';
		if ( $logic === 'or' ) {
			foreach ( $markers as $m ) {
				if ( ( $m['match'] ?? '0' ) === '1' ) {
					return true;
				}
			}
			return false;
		}
		foreach ( $markers as $m ) {
			if ( ( $m['match'] ?? '0' ) !== '1' ) {
				return false;
			}
		}
		return true;
	}

	private static function remove_all_child_nodes( \DOMElement $el ): void {
		while ( $el->firstChild ) {
			$el->removeChild( $el->firstChild );
		}
	}

	/**
	 * Match front-end gate inheritance: Gutenberg walks up (column → group → columns), then block root;
	 * Elementor uses the CreatorReactor widget wrapper, then legacy fallbacks.
	 */
	private static function resolve_container_for_marker( \DOMElement $marker ): ?\DOMElement {
		$class = $marker->getAttribute( 'class' );
		if ( strpos( $class, 'creatorreactor-gutenberg-gate-marker' ) !== false ) {
			return self::resolve_gutenberg_container( $marker );
		}
		if ( strpos( $class, 'creatorreactor-elementor-gate-marker' ) !== false ) {
			return self::resolve_elementor_container( $marker );
		}
		return null;
	}

	private static function resolve_gutenberg_container( \DOMElement $marker ): ?\DOMElement {
		$el = $marker->parentNode;
		while ( $el && $el->nodeType === XML_ELEMENT_NODE ) {
			if ( $el instanceof \DOMElement ) {
				$c = $el->getAttribute( 'class' );
				if ( $el !== $marker && strpos( $c, 'wp-block-column' ) !== false ) {
					return $el;
				}
				if ( $el !== $marker && strpos( $c, 'wp-block-group' ) !== false ) {
					return $el;
				}
				if ( $el !== $marker && strpos( $c, 'wp-block-columns' ) !== false ) {
					return $el;
				}
			}
			$el = $el->parentNode;
		}
		$closest = self::dom_closest_class_substring( $marker, 'wp-block-creatorreactor-' );
		return $closest instanceof \DOMElement ? $closest : null;
	}

	private static function resolve_elementor_container( \DOMElement $marker ): ?\DOMElement {
		$widget = self::dom_closest_creatorreactor_elementor_widget( $marker );
		if ( $widget instanceof \DOMElement ) {
			return $widget;
		}
		// Unusual markup: fall back to legacy wide containers (older behavior).
		$inner = self::dom_closest_class( $marker, 'e-con-inner' );
		if ( $inner instanceof \DOMElement ) {
			return $inner;
		}
		$legacy = self::dom_closest_class( $marker, 'elementor-container' );
		if ( $legacy instanceof \DOMElement ) {
			return $legacy;
		}
		$fallback = self::dom_closest_class( $marker, 'elementor-element' );
		return $fallback instanceof \DOMElement ? $fallback : null;
	}

	/**
	 * Nearest Elementor widget root for a CreatorReactor gate (marker lives inside this element).
	 */
	private static function dom_closest_creatorreactor_elementor_widget( \DOMElement $from ): ?\DOMElement {
		$el = $from->parentNode;
		while ( $el && $el->nodeType === XML_ELEMENT_NODE ) {
			if ( $el instanceof \DOMElement ) {
				$c  = $el->getAttribute( 'class' );
				$wt = $el->getAttribute( 'data-widget_type' );
				if ( strpos( $c, 'elementor-widget-creatorreactor' ) !== false
					|| ( is_string( $wt ) && strpos( $wt, 'creatorreactor' ) === 0 ) ) {
					return $el;
				}
			}
			$el = $el->parentNode;
		}
		return null;
	}

	private static function dom_closest_class( \DOMElement $from, string $class_token ): ?\DOMElement {
		$el = $from->parentNode;
		while ( $el && $el->nodeType === XML_ELEMENT_NODE ) {
			if ( $el instanceof \DOMElement ) {
				$c = $el->getAttribute( 'class' );
				if ( preg_match( '/(^|\s)' . preg_quote( $class_token, '/' ) . '(\s|$)/', $c ) ) {
					return $el;
				}
			}
			$el = $el->parentNode;
		}
		return null;
	}

	private static function dom_closest_class_substring( \DOMElement $from, string $needle ): ?\DOMElement {
		$el = $from->parentNode;
		while ( $el && $el->nodeType === XML_ELEMENT_NODE ) {
			if ( $el instanceof \DOMElement ) {
				$c = $el->getAttribute( 'class' );
				if ( strpos( $c, $needle ) !== false ) {
					return $el;
				}
			}
			$el = $el->parentNode;
		}
		return null;
	}
}
