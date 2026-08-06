<?php
/**
 * Pending-link state storage.
 *
 * @package WC_PCR
 */

namespace WC_PCR\Tests;

use WC_PCR_Pending_Link;

/**
 * Covers the opaque browser state that carries a link request across requests.
 */
class PendingLinkTest extends TestCase {

	/**
	 * A pending request round-trips through the cookie.
	 */
	public function test_add_stores_secret_hash_on_order_and_reference_in_browser() {
		$order  = $this->make_order( 101 );
		$secret = $this->make_pending( $order );

		$this->assertNotSame( '', $secret );
		$this->assertSame(
			wp_hash( $secret ),
			$order->get_meta( WC_PCR_Pending_Link::META_SECRET ),
			'The order must store only the hash of the secret.'
		);
		$this->assertGreaterThan( time(), (int) $order->get_meta( WC_PCR_Pending_Link::META_EXPIRES ) );
		$this->assertSame( array( 101 => $secret ), WC_PCR_Pending_Link::get_all() );
	}

	/**
	 * The order's own registration token must never reach the browser.
	 */
	public function test_cookie_never_carries_the_order_token() {
		$order = $this->make_order( 101, 'guest@example.test', array( '_wc_pcr_post_checkout_registration' => 'ORDERTOKEN123' ) );

		$this->make_pending( $order );

		$this->assertStringNotContainsString( 'ORDERTOKEN123', $_COOKIE[ WC_PCR_Pending_Link::COOKIE ] );
	}

	/**
	 * The cookie is not readable by scripts and is scoped correctly.
	 */
	public function test_cookie_attributes_are_hardened() {
		$options = $this->invoke_static( 'cookie_options', array( time() + 60 ) );

		$this->assertTrue( $options['httponly'], 'Nothing in the browser reads this cookie.' );
		$this->assertTrue( $options['secure'], 'Must be HTTPS-only when the request is HTTPS.' );
		$this->assertSame( 'Lax', $options['samesite'], 'Strict would drop the cookie on the password-reset email click-through.' );
		$this->assertSame( COOKIEPATH, $options['path'] );
		$this->assertSame( COOKIE_DOMAIN, $options['domain'] );
	}

	/**
	 * `secure` follows the actual request scheme rather than being hardcoded.
	 */
	public function test_cookie_is_not_marked_secure_over_plain_http() {
		\Brain\Monkey\Functions\when( 'is_ssl' )->justReturn( false );

		$options = $this->invoke_static( 'cookie_options', array( time() + 60 ) );

		$this->assertFalse( $options['secure'] );
	}

	/**
	 * Several guest orders can be pending at once, newest first.
	 */
	public function test_multiple_pending_orders_are_kept_newest_first_and_capped() {
		for ( $i = 1; $i <= WC_PCR_Pending_Link::MAX + 2; $i++ ) {
			$this->make_pending( $this->make_order( 100 + $i ) );
		}

		$entries = WC_PCR_Pending_Link::get_all();

		$this->assertCount( WC_PCR_Pending_Link::MAX, $entries );
		$this->assertSame( 100 + WC_PCR_Pending_Link::MAX + 2, array_key_first( $entries ), 'Newest request must survive the cap.' );
		$this->assertArrayNotHasKey( 101, $entries, 'Stalest request is the one dropped.' );
	}

	/**
	 * Re-requesting the same order replaces rather than duplicates its entry.
	 */
	public function test_re_requesting_the_same_order_rotates_the_secret() {
		$order  = $this->make_order( 101 );
		$first  = $this->make_pending( $order );
		$second = $this->make_pending( $order );

		$this->assertNotSame( $first, $second );
		$this->assertCount( 1, WC_PCR_Pending_Link::get_all() );
		$this->assertFalse( WC_PCR_Pending_Link::verify( $order, $first ), 'The superseded secret must stop working.' );
		$this->assertTrue( WC_PCR_Pending_Link::verify( $order, $second ) );
	}

	/**
	 * The cookie is attacker-controlled input; garbage must simply mean "nothing pending".
	 *
	 * @dataProvider malformed_cookies
	 *
	 * @param string $value Raw cookie value.
	 */
	public function test_malformed_cookie_yields_no_entries( $value ) {
		$_COOKIE[ WC_PCR_Pending_Link::COOKIE ] = $value;

		$this->assertSame( array(), WC_PCR_Pending_Link::get_all() );
	}

	/**
	 * Malformed cookie values.
	 *
	 * @return array
	 */
	public function malformed_cookies() {
		return array(
			'no separator'   => array( 'garbage' ),
			'missing secret' => array( '101:' ),
			'missing id'     => array( ':secret' ),
			'zero id'        => array( '0:secret' ),
			'empty'          => array( '|||' ),
		);
	}

	/**
	 * A hostile cookie cannot smuggle markup through into the secret.
	 */
	public function test_secret_is_reduced_to_alphanumerics() {
		$order = $this->make_order( 101 );
		$this->make_pending( $order );

		$_COOKIE[ WC_PCR_Pending_Link::COOKIE ] = "101:sec<script>alert(1)</script>\n";

		$entries = WC_PCR_Pending_Link::get_all();

		$this->assertSame( 'secscriptalert1script', $entries[101] );
		$this->assertFalse( WC_PCR_Pending_Link::verify( $order, $entries[101] ) );
	}

	/**
	 * A tampered secret does not validate.
	 */
	public function test_verify_rejects_a_wrong_secret() {
		$order = $this->make_order( 101 );
		$this->make_pending( $order );

		$this->assertFalse( WC_PCR_Pending_Link::verify( $order, 'not-the-secret' ) );
		$this->assertFalse( WC_PCR_Pending_Link::verify( $order, '' ) );
	}

	/**
	 * State older than the TTL is refused even if the browser still holds it.
	 */
	public function test_verify_rejects_expired_state() {
		$order  = $this->make_order( 101 );
		$secret = $this->make_pending( $order );

		$order->update_meta_data( WC_PCR_Pending_Link::META_EXPIRES, time() - 1 );

		$this->assertFalse( WC_PCR_Pending_Link::verify( $order, $secret ) );
	}

	/**
	 * An order with no pending request never validates.
	 */
	public function test_verify_rejects_an_order_with_no_pending_state() {
		$order = $this->make_order( 101 );

		$this->assertFalse( WC_PCR_Pending_Link::verify( $order, 'anything' ) );
	}

	/**
	 * Removing one entry leaves the others intact.
	 */
	public function test_remove_drops_only_the_named_order() {
		$this->make_pending( $this->make_order( 101 ) );
		$keep = $this->make_pending( $this->make_order( 202 ) );

		WC_PCR_Pending_Link::remove( 101 );

		$this->assertSame( array( 202 => $keep ), WC_PCR_Pending_Link::get_all() );
	}

	/**
	 * Removing the last entry expires the cookie entirely.
	 */
	public function test_removing_the_last_entry_clears_the_cookie() {
		$this->make_pending( $this->make_order( 101 ) );

		WC_PCR_Pending_Link::remove( 101 );

		$this->assertArrayNotHasKey( WC_PCR_Pending_Link::COOKIE, $_COOKIE );
		$this->assertSame( array(), WC_PCR_Pending_Link::get_all() );
	}

	/**
	 * The server-side half can be dropped independently.
	 */
	public function test_forget_order_state_removes_the_server_side_meta() {
		$order = $this->make_order( 101 );
		$this->make_pending( $order );

		WC_PCR_Pending_Link::forget_order_state( $order );

		$this->assertFalse( $order->has_meta( WC_PCR_Pending_Link::META_SECRET ) );
		$this->assertFalse( $order->has_meta( WC_PCR_Pending_Link::META_EXPIRES ) );
	}
}
