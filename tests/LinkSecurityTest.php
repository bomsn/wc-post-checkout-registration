<?php
/**
 * Ownership and scope rules for order linking.
 *
 * @package WC_PCR
 */

namespace WC_PCR\Tests;

use Brain\Monkey\Functions;
use WC_Data_Store;
use WC_PCR_Pending_Link;

/**
 * Covers who may claim an order, and how much gets claimed.
 */
class LinkSecurityTest extends TestCase {

	/**
	 * An order placed under a different email must not attach to this account.
	 */
	public function test_email_mismatch_is_refused() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'someone@example.test' );
		$order  = $this->make_order( 654676, 'other.person@example.test' );

		$this->follow_link_prompt( $plugin, $order );
		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 0, $order->get_customer_id() );
		$this->assertSame( array(), $this->notices( 'success' ) );
		$this->assertCount( 1, $this->notices( 'error' ) );
	}

	/**
	 * The comparison ignores case and surrounding whitespace.
	 */
	public function test_email_match_is_case_insensitive() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'Customer@Example.test' );
		$order  = $this->make_order( 654676, ' customer@example.TEST ' );

		$this->follow_link_prompt( $plugin, $order );
		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 8306, $order->get_customer_id() );
	}

	/**
	 * An order with no billing email cannot be claimed by anyone.
	 */
	public function test_order_without_a_billing_email_is_refused() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, '' );

		$this->follow_link_prompt( $plugin, $order );
		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 0, $order->get_customer_id() );
	}

	/**
	 * Stores with a legitimate exception can relax the rule.
	 */
	public function test_email_match_can_be_relaxed_by_filter() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'someone@example.test' );
		$order  = $this->make_order( 654676, 'other.person@example.test' );

		$this->follow_link_prompt( $plugin, $order );

		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $value ) {
				return 'wc_pcr_require_email_match' === $hook ? false : $value;
			}
		);

		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 8306, $order->get_customer_id() );
	}

	/**
	 * An order that already belongs to someone must never be reassigned.
	 */
	public function test_an_order_owned_by_another_account_is_never_reassigned() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );
		$order->set_customer_id( 999 );

		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 999, $order->get_customer_id() );
		$this->assertSame( array(), $this->notices( 'success' ) );
	}

	/**
	 * A forged or stale browser secret does not link.
	 */
	public function test_a_forged_secret_is_refused() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );

		$_COOKIE[ WC_PCR_Pending_Link::COOKIE ] = '654676:forgedsecret';

		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 0, $order->get_customer_id() );
		$this->assertCount( 1, $this->notices( 'error' ) );
	}

	/**
	 * Expired state is refused and cleaned up rather than retried forever.
	 */
	public function test_expired_state_is_refused_and_discarded() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );
		$order->update_meta_data( WC_PCR_Pending_Link::META_EXPIRES, time() - 1 );

		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 0, $order->get_customer_id() );
		$this->assertArrayNotHasKey( WC_PCR_Pending_Link::COOKIE, $_COOKIE );
	}

	/**
	 * A pending reference to a deleted order is discarded quietly enough.
	 */
	public function test_a_missing_order_is_discarded() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );

		$_COOKIE[ WC_PCR_Pending_Link::COOKIE ] = '999999:somesecret';

		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertArrayNotHasKey( WC_PCR_Pending_Link::COOKIE, $_COOKIE );
		$this->assertCount( 1, $this->notices( 'error' ) );
	}

	/**
	 * Only the requested order is claimed.
	 *
	 * WooCommerce's wc_update_new_customer_past_orders() sweeps every unassigned order sharing
	 * the account email, which is broader than what the prompt promised.
	 */
	public function test_only_the_requested_order_is_claimed() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );
		$other  = $this->make_order( 111111, 'customer@example.test' );

		Functions\expect( 'wc_update_new_customer_past_orders' )->never();

		$this->follow_link_prompt( $plugin, $order );
		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 8306, $order->get_customer_id() );
		$this->assertSame( 0, $other->get_customer_id(), 'Unrelated guest orders stay unlinked.' );
	}

	/**
	 * Stores that want the old sweep can opt back in.
	 */
	public function test_the_legacy_sweep_can_be_opted_into() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );

		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $value ) {
				return 'wc_pcr_link_all_past_orders' === $hook ? true : $value;
			}
		);
		Functions\expect( 'wc_update_new_customer_past_orders' )->once()->with( 8306 );

		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 8306, $order->get_customer_id() );
	}

	/**
	 * A store can veto linking outright.
	 */
	public function test_linking_can_be_vetoed_by_filter() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );

		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $value ) {
				return 'wc_pcr_can_link_order' === $hook ? false : $value;
			}
		);

		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( 0, $order->get_customer_id() );
	}

	/**
	 * Download permissions are rebuilt for the new owner.
	 */
	public function test_downloadable_permissions_are_rebuilt_for_the_new_owner() {
		$plugin              = $this->make_plugin();
		$user                = $this->make_user( 8306, 'customer@example.test' );
		$order               = $this->make_order( 654676, 'customer@example.test' );
		$order->downloadable = true;

		Functions\expect( 'wc_downloadable_product_permissions' )->once()->with( 654676, true );

		$this->follow_link_prompt( $plugin, $order );
		$plugin->link_previous_orders( 'customer8306', $user );

		$this->assertSame( array( 654676 ), WC_Data_Store::$deleted_order_ids );
	}

	/**
	 * My Account order totals are invalidated so the new order shows up.
	 *
	 * WooCommerce 9.0 moved these keys behind a site-specific suffix, so clearing
	 * only the bare key would leave stale counts on a current install.
	 */
	public function test_customer_order_stats_are_invalidated_for_both_key_shapes() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$this->follow_link_prompt( $plugin, $order );
		$plugin->link_previous_orders( 'customer8306', $user );

		$keys = array();
		foreach ( $this->user_meta_writes as $write ) {
			$keys[] = $write[2];
		}

		$this->assertContains( 'wc_order_count', $keys );
		$this->assertContains( 'wc_order_count_wp_testprefix', $keys );
		$this->assertContains( 'wc_money_spent', $keys );
		$this->assertContains( 'wc_money_spent_wp_testprefix', $keys );
		$this->assertContains( 'wc_last_order', $keys );
		$this->assertContains( 'wc_last_order_wp_testprefix', $keys );
	}

	/**
	 * A follow-up link URL cannot replay a token that has already been used.
	 */
	public function test_the_order_token_is_single_use() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		$token = $this->follow_link_prompt( $plugin, $order );
		$plugin->link_previous_orders( 'customer8306', $user );

		$_GET = array(
			'link_order_id' => '654676',
			'login_token'   => $token,
		);

		$plugin->maybe_store_order_data();

		$this->assertSame( array(), WC_PCR_Pending_Link::get_all(), 'A used token must not open a new request.' );
	}

	/**
	 * An invalid link URL is ignored silently, so it cannot be used as an oracle.
	 */
	public function test_an_invalid_link_url_registers_nothing_and_says_nothing() {
		$plugin = $this->make_plugin();
		$order  = $this->make_order( 654676, 'customer@example.test' );

		Functions\when( 'get_option' )->justReturn( 'no' );
		$this->invoke( $plugin, 'get_order_token', array( $order ) );

		$_GET = array(
			'link_order_id' => '654676',
			'login_token'   => 'guessed-token',
		);

		$plugin->maybe_store_order_data();

		$this->assertSame( array(), WC_PCR_Pending_Link::get_all() );
		$this->assertSame( array(), $this->notices( 'error' ) );
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
