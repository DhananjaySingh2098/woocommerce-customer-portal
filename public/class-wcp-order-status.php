<?php
/**
 * Order status presentation.
 *
 * WooCommerce lets stores register their own order statuses, and plugins do it
 * constantly -- "awaiting-shipment", "partially-refunded", whatever a workflow
 * needs. So this class never assumes it knows the full set. It maps the seven
 * core statuses to a visual tone and lets everything else fall through to a
 * neutral tone with WooCommerce's own label. An unknown status renders
 * correctly and safely; it simply does not get a bespoke colour.
 *
 * @package WooCommerce_Customer_Portal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Maps order statuses to labels and visual tones.
 */
class WCP_Order_Status {

	/**
	 * Tone applied to any status this class does not recognise.
	 */
	const DEFAULT_TONE = 'neutral';

	/**
	 * Tones understood by the stylesheet.
	 *
	 * Anything outside this list is rejected in favour of the default, so a
	 * filtered tone can never inject an arbitrary class name.
	 *
	 * @var string[]
	 */
	private static $tones = array( 'success', 'info', 'warning', 'danger', 'neutral' );

	/**
	 * Core status slug to tone.
	 *
	 * Keys are unprefixed: WooCommerce stores `wc-processing` but reports
	 * `processing` from `WC_Order::get_status()`.
	 *
	 * @var array<string,string>
	 */
	private static $map = array(
		'completed'  => 'success',
		'processing' => 'info',
		'on-hold'    => 'warning',
		'pending'    => 'warning',
		'cancelled'  => 'neutral',
		'refunded'   => 'neutral',
		'failed'     => 'danger',
		'draft'      => 'neutral',
	);

	/**
	 * Normalise a status slug by dropping WooCommerce's `wc-` prefix.
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	public static function normalize( $status ) {
		$status = sanitize_key( (string) $status );

		return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
	}

	/**
	 * Visual tone for a status.
	 *
	 * @param string $status Status slug.
	 * @return string One of the known tones.
	 */
	public static function tone( $status ) {
		$status = self::normalize( $status );
		$tone   = isset( self::$map[ $status ] ) ? self::$map[ $status ] : self::DEFAULT_TONE;

		/**
		 * Filter the visual tone applied to an order status.
		 *
		 * Lets a store give its custom statuses a colour without touching CSS.
		 *
		 * @param string $tone   One of: success, info, warning, danger, neutral.
		 * @param string $status Normalised status slug.
		 */
		$tone = (string) apply_filters( 'wcp_order_status_tone', $tone, $status );

		return in_array( $tone, self::$tones, true ) ? $tone : self::DEFAULT_TONE;
	}

	/**
	 * Human-readable label for a status.
	 *
	 * Defers to WooCommerce so custom statuses registered by the store or
	 * another plugin get their proper names. Falls back to a title-cased slug
	 * if WooCommerce has nothing registered, which beats showing a raw slug.
	 *
	 * @param string $status Status slug.
	 * @return string
	 */
	public static function label( $status ) {
		$status = self::normalize( $status );

		if ( '' === $status ) {
			return '';
		}

		if ( function_exists( 'wc_get_order_status_name' ) ) {
			$label = (string) wc_get_order_status_name( $status );

			// WooCommerce hands the slug straight back when it has no label
			// registered, so "unchanged" is the signal that nothing was found.
			if ( '' !== $label && $label !== $status ) {
				return $label;
			}
		}

		return ucwords( str_replace( array( '-', '_' ), ' ', $status ) );
	}

	/**
	 * Glyph for a status pill, so no status is conveyed by colour alone.
	 *
	 * Core statuses get a glyph that says what the word says; anything else
	 * falls back to the glyph for its tone, which is still distinct per tone.
	 *
	 * @var array<string,string>
	 */
	private static $icons = array(
		'completed'  => 'check',
		'processing' => 'refresh',
		'on-hold'    => 'pause',
		'pending'    => 'clock',
		'cancelled'  => 'close',
		'refunded'   => 'undo',
		'failed'     => 'alert',
		'draft'      => 'minus',
	);

	/**
	 * Icon name for a status.
	 *
	 * @param string $status Status slug.
	 * @return string An icon name known to `wcp_icon()`.
	 */
	public static function icon( $status ) {
		$status = self::normalize( $status );

		if ( isset( self::$icons[ $status ] ) ) {
			return self::$icons[ $status ];
		}

		$by_tone = array(
			'success' => 'check',
			'info'    => 'refresh',
			'warning' => 'clock',
			'danger'  => 'alert',
			'neutral' => 'minus',
		);

		$tone = self::tone( $status );

		return isset( $by_tone[ $tone ] ) ? $by_tone[ $tone ] : 'minus';
	}

	/**
	 * Everything a template needs to render a status pill.
	 *
	 * @param string $status Status slug.
	 * @return array{slug:string,label:string,tone:string,class:string,icon:string}
	 */
	public static function describe( $status ) {
		$slug = self::normalize( $status );
		$tone = self::tone( $slug );

		return array(
			'slug'  => $slug,
			'label' => self::label( $slug ),
			'tone'  => $tone,
			'class' => sanitize_html_class( 'wcp-order-status--' . $tone ),
			'icon'  => self::icon( $slug ),
		);
	}

	/**
	 * Progress steps derivable from the current status alone.
	 *
	 * Deliberately conservative. WooCommerce does not record a status history
	 * on the order, so anything resembling a delivery timeline would be
	 * invented. What *is* certain is the current state and whether the order
	 * reached a terminal one, so the timeline shows exactly that and stops.
	 *
	 * Returns an empty array for statuses where a linear progression would be
	 * misleading -- a cancelled, failed or refunded order did not "progress".
	 *
	 * @param string $status Status slug.
	 * @return array<int,array{key:string,label:string,state:string}>
	 */
	public static function timeline( $status ) {
		$status = self::normalize( $status );

		$linear = array( 'pending', 'processing', 'completed' );

		if ( ! in_array( $status, $linear, true ) ) {
			return array();
		}

		$position = array_search( $status, $linear, true );

		$labels = array(
			'pending'    => __( 'Order placed', 'woocommerce-customer-portal' ),
			'processing' => __( 'Processing', 'woocommerce-customer-portal' ),
			'completed'  => __( 'Completed', 'woocommerce-customer-portal' ),
		);

		$steps = array();

		foreach ( $linear as $index => $key ) {
			if ( $index < $position ) {
				$state = 'done';
			} elseif ( $index === $position ) {
				$state = 'current';
			} else {
				$state = 'upcoming';
			}

			$steps[] = array(
				'key'   => $key,
				'label' => $labels[ $key ],
				'state' => $state,
			);
		}

		return $steps;
	}
}
