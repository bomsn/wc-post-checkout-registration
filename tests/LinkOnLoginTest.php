<?php
/**
 * The pre-existing direct-login path must keep working.
 *
 * @package WC_PCR
 */

namespace WC_PCR\Tests;

use Brain\Monkey\Functions;
use WC_PCR_Pending_Link;

/**
 * Covers login-form linking and the legacy POST-field contract.
 */
class LinkOnLoginTest extends TestCase {

	/**
	 * The customer remembers their password and logs in normally.
	 */
	public function test_order_is_linked_on_a_normal_login() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );

		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 8306, $order->get_customer_id() );
		$this->assertCount( 1, $this->notices( 'success' ) );
	}

	/**
	 * A custom login template that posts the old fields directly still links.
	 *
	 * Those templates never went through maybe_store_order_data(), so there is no
	 * browser secret and the order's own token is the only thing to check.
	 */
	public function test_legacy_post_fields_still_link_without_a_pending_cookie() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		Functions\when( 'get_option' )->justReturn( 'no' );
		$token = $this->invoke( $plugin, 'get_order_token', array( $order ) );

		$_POST = array(
			'wc_pcr_link_order_id' => (string) $order->get_id(),
			'wc_pcr_login_token'   => $token,
		);

		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 8306, $order->get_customer_id() );
		$this->assertCount( 1, $this->notices( 'success' ) );
	}

	/**
	 * A bad legacy token is reported rather than silently linking.
	 */
	public function test_legacy_post_fields_with_a_bad_token_are_refused() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		Functions\when( 'get_option' )->justReturn( 'no' );
		$this->invoke( $plugin, 'get_order_token', array( $order ) );

		$_POST = array(
			'wc_pcr_link_order_id' => (string) $order->get_id(),
			'wc_pcr_login_token'   => 'wrong-token',
		);

		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 0, $order->get_customer_id() );
		$this->assertCount( 1, $this->notices( 'error' ) );
	}

	/**
	 * When both the cookie and the POST fields are present, the order links once.
	 */
	public function test_cookie_and_post_fields_together_link_exactly_once() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$token = $this->follow_link_prompt( $plugin, $order );

		$_POST = array(
			'wc_pcr_link_order_id' => (string) $order->get_id(),
			'wc_pcr_login_token'   => $token,
		);

		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 8306, $order->get_customer_id() );
		$this->assertCount( 1, $this->notices( 'success' ) );
		$this->assertSame( array(), $this->notices( 'error' ) );
	}

	/**
	 * Logging in again later must not re-link or re-notify.
	 */
	public function test_a_second_login_does_not_relink() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );
		$plugin->link_previous_orders( 'customer8306', $user );

		$saves = $order->save_calls;

		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( $saves, $order->save_calls );
		$this->assertCount( 1, $this->notices( 'success' ) );
	}

	/**
	 * A plain login with nothing pending must stay silent.
	 */
	public function test_login_without_a_pending_request_is_a_no_op() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 0, $order->get_customer_id() );
		$this->assertSame( array(), $this->notices( 'success' ) );
		$this->assertSame( array(), $this->notices( 'error' ) );
	}

	/**
	 * The hidden login-form fields are rendered from the request, not shared state.
	 *
	 * These used to be read out of the object cache, which on a persistent backend
	 * is shared between visitors and would serve one customer's pending order into
	 * another customer's login form.
	 */
	public function test_tracking_fields_render_from_the_current_request_only() {
		$plugin = $this->make_plugin();

		$_GET = array(
			'link_order_id' => '654676',
			'login_token'   => 'TOKEN123',
		);

		ob_start();
		$plugin->add_custom_tracking_fields();
		$markup = ob_get_clean();

		$this->assertStringContainsString( 'name="wc_pcr_link_order_id" id="wc_pcr_link_order_id" value="654676"', $markup );
		$this->assertStringContainsString( 'name="wc_pcr_login_token" id="wc_pcr_login_token" value="TOKEN123"', $markup );
	}

	/**
	 * With no request context there is nothing to render.
	 */
	public function test_tracking_fields_render_nothing_without_request_context() {
		$plugin = $this->make_plugin();

		$_COOKIE[ WC_PCR_Pending_Link::COOKIE ] = '654676:somesecret';

		ob_start();
		$plugin->add_custom_tracking_fields();
		$markup = ob_get_clean();

		$this->assertSame( '', $markup, 'The cookie alone must not seed another visitor\'s form.' );
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
