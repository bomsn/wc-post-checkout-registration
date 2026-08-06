<?php
/**
 * Regression cover for the reported production failure.
 *
 * @package WC_PCR
 */

namespace WC_PCR\Tests;

use Brain\Monkey\Functions;
use WC_PCR_Pending_Link;

/**
 * A customer who forgot their password never touches the login form.
 *
 * WooCommerce signs them in from inside its reset handler with
 * wc_set_customer_auth_cookie(), which does not fire `wp_login` and carries no
 * POST fields from the plugin. Before 2.1.0 that meant the entire prompted flow
 * completed and the order was silently left unlinked.
 */
class LinkOnPasswordResetTest extends TestCase {

	/**
	 * The reported failure: guest checkout, prompt, lost password, reset, link.
	 */
	public function test_order_is_linked_when_the_customer_resets_their_password() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		// Step 1: the customer follows the "Log in" prompt from the thank-you page.
		$this->follow_link_prompt( $plugin, $order );

		// Step 2: they never reach the login form. WooCommerce authenticates them
		// from the reset form and fires woocommerce_customer_reset_password.
		$plugin->link_after_password_reset( $user );

		$this->assertSame( 8306, $order->get_customer_id() );
		$this->assertNotEmpty( $this->notices( 'success' ) );
		$this->assertSame( array(), $this->notices( 'error' ) );
	}

	/**
	 * `wp_login` genuinely does not fire on this path, so it must not be required.
	 */
	public function test_linking_does_not_depend_on_login_form_post_fields() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );

		$this->assertSame( array(), $_POST, 'The reset form posts none of the plugin fields.' );

		$plugin->link_after_password_reset( $user );

		$this->assertSame( 8306, $order->get_customer_id() );
	}

	/**
	 * WooCommerce 10.9+ fires both after_password_reset and
	 * woocommerce_customer_reset_password, so the handler must be idempotent.
	 */
	public function test_double_fired_reset_hooks_link_once_and_notify_once() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );

		$plugin->link_after_password_reset( $user );
		$plugin->link_after_password_reset( $user );

		$this->assertSame( 8306, $order->get_customer_id() );
		$this->assertCount( 1, $this->notices( 'success' ) );
		$this->assertSame( array(), $this->notices( 'error' ) );
	}

	/**
	 * A password reset with no pending request must not link anything.
	 *
	 * Resetting a password is not by itself a request to claim guest orders.
	 */
	public function test_password_reset_without_a_pending_request_links_nothing() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$plugin->link_after_password_reset( $user );

		$this->assertSame( 0, $order->get_customer_id() );
		$this->assertSame( array(), $this->notices( 'success' ) );
		$this->assertSame( array(), $this->notices( 'error' ) );
	}

	/**
	 * Successful linking retires both halves of the state.
	 */
	public function test_state_is_cleaned_up_after_a_successful_link() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );
		$plugin->link_after_password_reset( $user );

		$this->assertArrayNotHasKey( WC_PCR_Pending_Link::COOKIE, $_COOKIE );
		$this->assertFalse( $order->has_meta( WC_PCR_Pending_Link::META_SECRET ) );
		$this->assertFalse( $order->has_meta( \Run_WC_PCR::TOKEN_META ), 'The token is one-shot.' );
		$this->assertTrue( (bool) $order->get_meta( '_wc_pcr_order_linked' ) );
		$this->assertGreaterThan( 0, $order->save_calls );
	}

	/**
	 * Non-user arguments are ignored rather than fatal.
	 */
	public function test_handler_ignores_a_non_user_argument() {
		$plugin = $this->make_plugin();
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );
		$plugin->link_after_password_reset( null );

		$this->assertSame( 0, $order->get_customer_id() );
	}

	/**
	 * `after_password_reset` also fires on wp-login.php, where there is no session.
	 *
	 * The order must still be linked; only the notice is skipped, because
	 * wc_add_notice() would discard it and warn into the response.
	 */
	public function test_link_still_happens_when_there_is_no_woocommerce_session() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );

		$this->wc->session = null;
		Functions\expect( 'wc_add_notice' )->never();

		$plugin->link_after_password_reset( $user );

		$this->assertSame( 8306, $order->get_customer_id() );
	}

	/**
	 * Walks the customer through the thank-you prompt into a pending request.
	 *
	 * @param \Run_WC_PCR $plugin Plugin instance.
	 * @param \WC_Order   $order  The guest order.
	 */
	protected function follow_link_prompt( $plugin, $order ) {
		Functions\when( 'get_option' )->justReturn( 'no' );

		// The thank-you page issues the order token.
		$token = $this->invoke( $plugin, 'get_order_token', array( $order ) );

		// The customer clicks through to the My Account link-order URL.
		$_GET = array(
			'link_order_id' => (string) $order->get_id(),
			'login_token'   => $token,
		);

		$plugin->maybe_store_order_data();

		$_GET = array();
	}
}
