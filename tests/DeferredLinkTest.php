<?php
/**
 * Catch-up linking on a later request.
 *
 * @package WC_PCR
 */

namespace WC_PCR\Tests;

use Brain\Monkey\Functions;
use WC_PCR_Pending_Link;

/**
 * Covers customers who become authenticated somewhere the plugin cannot hook.
 *
 * Social login, auto-login and "set your password" plugins all sign a customer
 * in without firing anything the plugin listens to, and a customer can simply
 * log in from a second tab while the thank-you page is still open. The
 * template_redirect pass picks those up on the next page load.
 */
class DeferredLinkTest extends TestCase {

	/**
	 * A customer who authenticates elsewhere still gets their order.
	 */
	public function test_pending_order_links_on_a_later_authenticated_request() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );

		// Next request: already signed in, by some route the plugin never saw.
		$this->authenticate_as( $user );
		$plugin->maybe_link_pending_orders();

		$this->assertSame( 8306, $order->get_customer_id() );
		$this->assertCount( 1, $this->notices( 'success' ) );
	}

	/**
	 * Subsequent page loads must not repeat the work or the notice.
	 */
	public function test_subsequent_requests_do_not_relink_or_renotify() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );
		$this->authenticate_as( $user );

		$plugin->maybe_link_pending_orders();
		$saves = $order->save_calls;
		$plugin->maybe_link_pending_orders();

		$this->assertSame( $saves, $order->save_calls );
		$this->assertCount( 1, $this->notices( 'success' ) );
		$this->assertArrayNotHasKey( WC_PCR_Pending_Link::COOKIE, $_COOKIE );
	}

	/**
	 * The catch-up pass is silent about failures it was not asked about.
	 */
	public function test_catch_up_pass_stays_silent_on_a_terminal_failure() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'someone@example.test' );
		$order  = $this->make_order( 654676, 'other.person@example.test' );

		$this->follow_link_prompt( $plugin, $order );
		$this->authenticate_as( $user );

		$plugin->maybe_link_pending_orders();

		$this->assertSame( 0, $order->get_customer_id() );
		$this->assertSame( array(), $this->notices( 'error' ) );
		$this->assertArrayNotHasKey( WC_PCR_Pending_Link::COOKIE, $_COOKIE, 'A refused request must not retry forever.' );
	}

	/**
	 * Logged-out visitors are untouched.
	 */
	public function test_nothing_happens_while_logged_out() {
		$plugin = $this->make_plugin();
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );

		$plugin->maybe_link_pending_orders();

		$this->assertSame( 0, $order->get_customer_id() );
		$this->assertArrayHasKey( WC_PCR_Pending_Link::COOKIE, $_COOKIE, 'The request must survive for the eventual login.' );
	}

	/**
	 * Several guest orders in one browser all get linked.
	 */
	public function test_multiple_pending_orders_are_all_linked() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$first  = $this->make_order( 654676, 'customer@example.test' );
		$second = $this->make_order( 654677, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $first );
		$this->follow_link_prompt( $plugin, $second );

		$this->authenticate_as( $user );
		$plugin->maybe_link_pending_orders();

		$this->assertSame( 8306, $first->get_customer_id() );
		$this->assertSame( 8306, $second->get_customer_id() );
		$this->assertCount( 2, $this->notices( 'success' ) );
	}

	/**
	 * One bad entry must not block the good ones beside it.
	 */
	public function test_one_refused_order_does_not_block_the_others() {
		$plugin  = $this->make_plugin();
		$user    = $this->make_user( 8306, 'customer@example.test' );
		$mine    = $this->make_order( 654676, 'customer@example.test' );
		$foreign = $this->make_order( 654677, 'other.person@example.test' );

		$this->follow_link_prompt( $plugin, $foreign );
		$this->follow_link_prompt( $plugin, $mine );

		$this->authenticate_as( $user );
		$plugin->maybe_link_pending_orders();

		$this->assertSame( 8306, $mine->get_customer_id() );
		$this->assertSame( 0, $foreign->get_customer_id() );
	}

	/**
	 * Marks the current visitor as signed in.
	 *
	 * @param \WP_User $user The signed-in user.
	 */
	protected function authenticate_as( $user ) {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'wp_get_current_user' )->justReturn( $user );
	}

	/**
	 * Walks the customer through the thank-you prompt into a pending request.
	 *
	 * @param \Run_WC_PCR $plugin Plugin instance.
	 * @param \WC_Order   $order  The guest order.
	 * @return string The order token issued by the prompt.
	 */
	protected function follow_link_prompt( $plugin, $order ) {
		Functions\when( 'get_option' )->justReturn( 'no' );

		$token = $this->invoke( $plugin, 'get_order_token', array( $order ) );

		$_GET = array(
			'link_order_id' => (string) $order->get_id(),
			'login_token'   => $token,
		);

		$plugin->maybe_store_order_data();

		$_GET = array();

		return $token;
	}
}
