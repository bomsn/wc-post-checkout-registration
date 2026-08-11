<?php
/**
 * Access to the order received page for a linked order.
 *
 * @package WC_PCR
 */

namespace WC_PCR\Tests;

use Brain\Monkey\Functions;
use WC_Order;

/**
 * Covers the allowance that keeps a just-linked receipt readable.
 *
 * WooCommerce hides the order received page from anyone who is not signed in as
 * the order's customer. Linking the order must not take the receipt away from
 * the guest who placed it, but it must not open the page up any further either.
 */
class LinkedReceiptAccessTest extends TestCase {

	/**
	 * The buyer keeps their receipt for the length of WooCommerce's own window.
	 */
	public function test_buyer_keeps_access_within_the_grace_period() {
		$plugin = $this->make_plugin();
		$order  = $this->make_linked_order();

		$this->request_receipt( $order );
		$this->session->set( 'customer', array( 'email' => 'customer@example.test' ) );

		$this->assertFalse( $plugin->maybe_allow_linked_order_receipt( true ) );
	}

	/**
	 * The address is matched the way WooCommerce matches it.
	 */
	public function test_session_email_match_ignores_case() {
		$plugin = $this->make_plugin();
		$order  = $this->make_linked_order();

		$this->request_receipt( $order );
		$this->session->set( 'customer', array( 'email' => 'Customer@Example.TEST' ) );

		$this->assertFalse( $plugin->maybe_allow_linked_order_receipt( true ) );
	}

	/**
	 * Once the window closes, WooCommerce's check applies again.
	 */
	public function test_access_expires_with_the_grace_period() {
		$plugin = $this->make_plugin();
		$order  = $this->make_linked_order( 11 * MINUTE_IN_SECONDS );

		$this->request_receipt( $order );
		$this->session->set( 'customer', array( 'email' => 'customer@example.test' ) );

		$this->assertTrue( $plugin->maybe_allow_linked_order_receipt( true ) );
	}

	/**
	 * Someone else's session does not open the page.
	 */
	public function test_a_different_shopper_is_refused() {
		$plugin = $this->make_plugin();
		$order  = $this->make_linked_order();

		$this->request_receipt( $order );
		$this->session->set( 'customer', array( 'email' => 'someone.else@example.test' ) );

		$this->assertTrue( $plugin->maybe_allow_linked_order_receipt( true ) );
	}

	/**
	 * With no checkout session there is nothing to identify the visitor.
	 */
	public function test_a_visitor_without_a_session_is_refused() {
		$plugin = $this->make_plugin();
		$order  = $this->make_linked_order();

		$this->request_receipt( $order );

		$this->assertTrue( $plugin->maybe_allow_linked_order_receipt( true ) );
	}

	/**
	 * The order key still has to check out, so an order ID cannot be guessed.
	 */
	public function test_a_wrong_order_key_is_refused() {
		$plugin = $this->make_plugin();
		$order  = $this->make_linked_order();

		$this->request_receipt( $order, 'wc_order_guessed' );
		$this->session->set( 'customer', array( 'email' => 'customer@example.test' ) );

		$this->assertTrue( $plugin->maybe_allow_linked_order_receipt( true ) );
	}

	/**
	 * Orders this plugin did not link are left to WooCommerce.
	 */
	public function test_an_order_linked_elsewhere_is_left_alone() {
		$plugin = $this->make_plugin();
		$order  = $this->make_order( 654676, 'customer@example.test', array(), 8306 );

		$order->date_created = new \DateTime( '@' . ( time() - 60 ) );

		$this->request_receipt( $order );
		$this->session->set( 'customer', array( 'email' => 'customer@example.test' ) );

		$this->assertTrue( $plugin->maybe_allow_linked_order_receipt( true ) );
	}

	/**
	 * Outside the order received page there is nothing to relax.
	 */
	public function test_nothing_changes_away_from_the_receipt_page() {
		$plugin = $this->make_plugin();

		Functions\when( 'get_query_var' )->justReturn( 0 );

		$this->assertTrue( $plugin->maybe_allow_linked_order_receipt( true ) );
	}

	/**
	 * A signed-in visitor is WooCommerce's business, not ours.
	 */
	public function test_a_signed_in_visitor_is_left_to_woocommerce() {
		$plugin = $this->make_plugin();
		$order  = $this->make_linked_order();

		$this->request_receipt( $order );
		Functions\when( 'is_user_logged_in' )->justReturn( true );

		$this->assertTrue( $plugin->maybe_allow_linked_order_receipt( true ) );
	}

	/**
	 * Another party having already waived the check is respected.
	 */
	public function test_an_existing_waiver_is_preserved() {
		$plugin = $this->make_plugin();

		$this->assertFalse( $plugin->maybe_allow_linked_order_receipt( false ) );
	}

	/**
	 * Build an order that this plugin has linked.
	 *
	 * @param int $age How long ago the order was placed, in seconds.
	 * @return WC_Order
	 */
	protected function make_linked_order( $age = 60 ) {
		$order = $this->make_order(
			654676,
			'customer@example.test',
			array( '_wc_pcr_order_linked' => true ),
			8306
		);

		$order->date_created = new \DateTime( '@' . ( time() - $age ) );

		return $order;
	}

	/**
	 * Put the request on the order received page for an order.
	 *
	 * @param WC_Order    $order The order being viewed.
	 * @param string|null $key   Order key supplied in the URL, defaults to the real one.
	 */
	protected function request_receipt( WC_Order $order, $key = null ) {
		Functions\when( 'get_query_var' )->justReturn( $order->get_id() );

		$_GET['key'] = null === $key ? $order->get_order_key() : $key;
	}
}
