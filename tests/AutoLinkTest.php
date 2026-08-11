<?php
/**
 * Automatic linking on the order received page.
 *
 * @package WC_PCR
 */

namespace WC_PCR\Tests;

use Brain\Monkey\Functions;

/**
 * Covers linking an order without asking the customer to log in first.
 *
 * WooCommerce hides the order received page from anyone who is not signed in as
 * the order's customer, so assigning the order has to leave the buyer with both
 * their receipt and an explanation of where the order went.
 */
class AutoLinkTest extends TestCase {

	/**
	 * The order is assigned to the matching account straight away.
	 */
	public function test_order_is_linked_to_the_matching_account() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		Functions\expect( 'wc_update_new_customer_past_orders' )->once()->with( 8306 );

		$this->show_notice( $plugin, $order, $user );

		$this->assertTrue( (bool) $order->get_meta( '_wc_pcr_order_linked' ) );
	}

	/**
	 * The buyer is told what happened, and given a way in.
	 */
	public function test_customer_is_told_the_order_was_linked() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		Functions\when( 'wc_update_new_customer_past_orders' )->justReturn( 1 );

		$output = $this->show_notice( $plugin, $order, $user );

		$this->assertStringContainsString( 'This order is linked to it', $output );
		$this->assertStringContainsString( 'https://example.test/my-account/', $output );
		$this->assertStringContainsString( 'Log in', $output );
	}

	/**
	 * The page must not reload, or WooCommerce takes the receipt away.
	 */
	public function test_the_page_is_not_redirected() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		Functions\when( 'wc_update_new_customer_past_orders' )->justReturn( 1 );
		Functions\expect( 'wp_safe_redirect' )->never();

		$this->show_notice( $plugin, $order, $user );
	}

	/**
	 * An order that already belongs to an account is not linked twice.
	 */
	public function test_an_already_linked_order_is_not_relinked() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test', array( '_wc_pcr_order_linked' => true ) );

		Functions\expect( 'wc_update_new_customer_past_orders' )->never();

		$output = $this->show_notice( $plugin, $order, $user );

		$this->assertStringContainsString( 'This order is linked to it', $output );
	}

	/**
	 * An order already owned by an account reports that, rather than offering to link it.
	 */
	public function test_an_owned_order_reports_the_link_instead_of_offering_one() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test', array(), 8306 );

		Functions\expect( 'wc_update_new_customer_past_orders' )->never();

		$output = $this->show_notice( $plugin, $order, $user, 'no' );

		$this->assertStringContainsString( 'This order is linked to it', $output );
		$this->assertStringNotContainsString( 'link_order_id', $output );
	}

	/**
	 * With the setting off, the customer is still asked to log in to link the order.
	 */
	public function test_the_link_prompt_is_unchanged_when_the_setting_is_off() {
		$plugin = $this->make_plugin();
		$user   = $this->make_user( 8306, 'customer@example.test' );
		$order  = $this->make_order( 654676, 'customer@example.test' );

		Functions\expect( 'wc_update_new_customer_past_orders' )->never();

		$output = $this->show_notice( $plugin, $order, $user, 'no' );

		$this->assertStringContainsString( 'link_order_id=654676', $output );
		$this->assertStringNotContainsString( 'This order is linked to it', $output );
	}

	/**
	 * An order owned by an account whose email no longer matches says nothing.
	 *
	 * Telling that shopper they have an account would be a guess, and offering to
	 * link an order that already has an owner would be wrong.
	 */
	public function test_nothing_is_claimed_when_no_account_matches_the_order_email() {
		$plugin = $this->make_plugin();
		$order  = $this->make_order( 654676, 'customer@example.test', array(), 8306 );

		Functions\expect( 'wc_update_new_customer_past_orders' )->never();

		$output = $this->show_notice( $plugin, $order, false );

		$this->assertSame( '', $output );
	}

	/**
	 * A guest with no account still gets the registration prompt.
	 */
	public function test_a_guest_without_an_account_is_offered_registration() {
		$plugin = $this->make_plugin();
		$order  = $this->make_order( 654676, 'guest@example.test' );

		Functions\expect( 'wc_update_new_customer_past_orders' )->never();

		$output = $this->show_notice( $plugin, $order, false );

		$this->assertStringContainsString( 'registration_order_id=654676', $output );
	}

	/**
	 * Renders the thank-you notice for an order.
	 *
	 * @param \Run_WC_PCR    $plugin        Plugin instance.
	 * @param \WC_Order      $order         The order being confirmed.
	 * @param \WP_User|false $existing_user The account matching the order email.
	 * @param string         $auto_linking  Value of the automatic linking setting.
	 * @return string The rendered markup.
	 */
	protected function show_notice( $plugin, $order, $existing_user, $auto_linking = 'yes' ) {
		$this->options['wc_pcr_auto_linking'] = $auto_linking;

		Functions\when( 'get_user_by' )->justReturn( $existing_user );
		Functions\when( 'wc_get_page_permalink' )->justReturn( 'https://example.test/my-account/' );
		Functions\when( 'add_query_arg' )->alias(
			function ( $args, $url ) {
				return $url . '?' . http_build_query( $args );
			}
		);
		Functions\when( 'trailingslashit' )->returnArg();

		ob_start();
		$plugin->maybe_show_registration_notice( $order->get_id() );

		return ob_get_clean();
	}
}
