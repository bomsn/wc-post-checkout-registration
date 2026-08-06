<?php
/**
 * Pending order-linking state.
 *
 * @package WC_PCR
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores "this browser asked to link order X" across the multi-request
 * authentication journeys a customer may take after checkout.
 *
 * The customer who clicks "Log in" on the thank-you page frequently does not
 * authenticate on the very next request: they may reset a forgotten password
 * (three redirects and a round trip through their email client), open a second
 * tab, or come back later. WooCommerce's password reset authenticates through
 * `wc_set_customer_auth_cookie()`, which never fires `wp_login`, so linking
 * cannot be driven off a single login form submission.
 *
 * The browser holds only `order_id:secret`; the order holds `wp_hash( secret )`.
 * That keeps the order's long-lived registration token out of the browser, makes
 * each pending request single-use, and anchors the authoritative state in HPOS
 * order meta so it survives an object-cache flush or eviction.
 *
 * @since 2.1.0
 */
class WC_PCR_Pending_Link {

	/**
	 * Cookie holding the opaque pending-link references.
	 *
	 * @since 2.1.0
	 * @var string
	 */
	const COOKIE = 'wc_pcr_pending_link';

	/**
	 * Order meta holding the hash of the browser's secret.
	 *
	 * @since 2.1.0
	 * @var string
	 */
	const META_SECRET = '_wc_pcr_link_secret';

	/**
	 * Order meta holding the pending request's expiry timestamp.
	 *
	 * @since 2.1.0
	 * @var string
	 */
	const META_EXPIRES = '_wc_pcr_link_expires';

	/**
	 * How long a pending link request stays valid, in seconds.
	 *
	 * @since 2.1.0
	 * @var int
	 */
	const TTL = 21600; // 6 * HOUR_IN_SECONDS.

	/**
	 * Maximum number of pending orders tracked at once, newest first.
	 *
	 * @since 2.1.0
	 * @var int
	 */
	const MAX = 5;

	/**
	 * Register a pending link request for an order.
	 *
	 * Generates a fresh single-use secret, stores its hash on the order and
	 * references it from the browser cookie.
	 *
	 * @since 2.1.0
	 *
	 * @param \WC_Order $order The order the customer asked to link.
	 * @return bool True when the request was registered.
	 */
	public static function add( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$secret = wp_generate_password( 32, false );

		$order->update_meta_data( self::META_SECRET, wp_hash( $secret ) );
		$order->update_meta_data( self::META_EXPIRES, time() + self::TTL );
		$order->save_meta_data();

		$entries = self::get_all();
		unset( $entries[ $order->get_id() ] );

		// Newest first, so the cap drops the stalest request rather than the current one.
		$entries = array( $order->get_id() => $secret ) + $entries;
		$entries = array_slice( $entries, 0, self::MAX, true );

		self::write_cookie( $entries );

		return true;
	}

	/**
	 * Read every pending link reference held by this browser.
	 *
	 * Malformed entries are skipped rather than raising notices: the cookie is
	 * attacker-controlled input and a bad value must simply mean "nothing pending".
	 *
	 * @since 2.1.0
	 *
	 * @return array Map of order ID => secret.
	 */
	public static function get_all() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return array();
		}

		$raw     = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		$entries = array();

		foreach ( explode( '|', $raw ) as $pair ) {
			if ( false === strpos( $pair, ':' ) ) {
				continue;
			}

			list( $order_id, $secret ) = explode( ':', $pair, 2 );

			$order_id = absint( $order_id );
			$secret   = preg_replace( '/[^A-Za-z0-9]/', '', $secret );

			if ( ! $order_id || '' === $secret ) {
				continue;
			}

			$entries[ $order_id ] = $secret;

			if ( count( $entries ) >= self::MAX ) {
				break;
			}
		}

		return $entries;
	}

	/**
	 * Look up the pending secret for a single order.
	 *
	 * @since 2.1.0
	 *
	 * @param int $order_id Order ID.
	 * @return string The secret, or an empty string when nothing is pending.
	 */
	public static function get( $order_id ) {
		$entries = self::get_all();

		return isset( $entries[ (int) $order_id ] ) ? $entries[ (int) $order_id ] : '';
	}

	/**
	 * Drop a single pending request from the browser.
	 *
	 * @since 2.1.0
	 *
	 * @param int $order_id Order ID.
	 */
	public static function remove( $order_id ) {
		$entries = self::get_all();

		if ( ! isset( $entries[ (int) $order_id ] ) ) {
			return;
		}

		unset( $entries[ (int) $order_id ] );

		if ( empty( $entries ) ) {
			self::clear();
			return;
		}

		self::write_cookie( $entries );
	}

	/**
	 * Expire the cookie entirely.
	 *
	 * @since 2.1.0
	 */
	public static function clear() {
		self::set_cookie( '', time() - YEAR_IN_SECONDS );

		unset( $_COOKIE[ self::COOKIE ] );
	}

	/**
	 * Remove the server-side half of a pending request.
	 *
	 * @since 2.1.0
	 *
	 * @param \WC_Order $order Order to clean up. Not saved by this method.
	 */
	public static function forget_order_state( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$order->delete_meta_data( self::META_SECRET );
		$order->delete_meta_data( self::META_EXPIRES );
	}

	/**
	 * Validate a browser-supplied secret against an order.
	 *
	 * @since 2.1.0
	 *
	 * @param \WC_Order $order  Order to check.
	 * @param string    $secret Secret supplied by the browser.
	 * @return bool True when the secret is valid and unexpired.
	 */
	public static function verify( $order, $secret ) {
		if ( ! $order instanceof WC_Order || '' === $secret ) {
			return false;
		}

		$stored = (string) $order->get_meta( self::META_SECRET );

		if ( '' === $stored ) {
			return false;
		}

		$expires = (int) $order->get_meta( self::META_EXPIRES );

		if ( $expires && $expires < time() ) {
			return false;
		}

		return hash_equals( $stored, wp_hash( $secret ) );
	}

	/**
	 * Serialise the entries and hand them to the browser.
	 *
	 * @since 2.1.0
	 *
	 * @param array $entries Map of order ID => secret.
	 */
	protected static function write_cookie( $entries ) {
		$pairs = array();

		foreach ( $entries as $order_id => $secret ) {
			$pairs[] = $order_id . ':' . $secret;
		}

		self::set_cookie( implode( '|', $pairs ), time() + self::TTL );
	}

	/**
	 * Builds the cookie attributes.
	 *
	 * HttpOnly: nothing in the browser needs to read this, and it references a
	 * capability to attach an order to an account.
	 *
	 * SameSite=Lax is deliberate. Every hop of the journey (thank-you page to My
	 * Account, the password reset link opened from an email client, the reset
	 * form POST) is a top-level navigation or a same-site submission, so the
	 * cookie is sent. Strict would drop it on the click-through from the email,
	 * which is exactly the case this state exists to survive.
	 *
	 * @since 2.1.0
	 *
	 * @param int $expires Expiry timestamp.
	 * @return array Options for setcookie().
	 */
	protected static function cookie_options( $expires ) {
		return array(
			'expires'  => $expires,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);
	}

	/**
	 * Write the cookie and mirror it into `$_COOKIE` for same-request reads.
	 *
	 * @since 2.1.0
	 *
	 * @param string $value   Cookie value.
	 * @param int    $expires Expiry timestamp.
	 */
	protected static function set_cookie( $value, $expires ) {
		if ( ! headers_sent() ) {
			setcookie( self::COOKIE, $value, self::cookie_options( $expires ) );
		}

		if ( '' === $value ) {
			unset( $_COOKIE[ self::COOKIE ] );
		} else {
			$_COOKIE[ self::COOKIE ] = $value;
		}
	}
}
