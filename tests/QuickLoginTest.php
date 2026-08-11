<?php
/**
 * The login form offered on the order received page.
 *
 * @package WC_PCR
 */

namespace WC_PCR\Tests;

use Brain\Monkey\Functions;
use WC_Order;
use WC_PCR_Pending_Link;

/**
 * Covers the pending link behind the inline login form.
 *
 * The form itself is printed part way down the page, by which point no cookie
 * can be sent, so the request has to be registered earlier in the page load.
 */
class QuickLoginTest extends TestCase {

	/**
	 * The request is registered while the headers are still open.
	 */
	public function test_a_pending_link_is_registered_for_the_form() {
		$plugin = $this->make_plugin();
		$order  = $this->make_receipt_order();

		Functions\when( 'get_user_by' )->justReturn( $this->make_user( 8306, 'customer@example.test' ) );

		$plugin->maybe_store_quick_login_order();

		$this->assertNotSame( '', WC_PCR_Pending_Link::get( 654676 ) );
		$this->assertTrue( WC_PCR_Pending_Link::verify( $order, WC_PCR_Pending_Link::get( 654676 ) ) );
	}

	/**
	 * Reloading the page keeps the request it already made.
	 */
	public function test_reloading_the_page_keeps_the_existing_request() {
		$plugin = $this->make_plugin();
		$order  = $this->make_receipt_order();

		Functions\when( 'get_user_by' )->justReturn( $this->make_user( 8306, 'customer@example.test' ) );

		$plugin->maybe_store_quick_login_order();
		$secret = WC_PCR_Pending_Link::get( 654676 );
		$writes = $order->save_meta_calls;

		$plugin->maybe_store_quick_login_order();

		$this->assertSame( $secret, WC_PCR_Pending_Link::get( 654676 ) );
		$this->assertSame( $writes, $order->save_meta_calls );
	}

	/**
	 * Nothing is registered unless the form is actually offered.
	 *
	 * @dataProvider settings_that_hide_the_form
	 *
	 * @param array $options Plugin settings for the scenario.
	 */
	public function test_no_pending_link_without_the_form( array $options ) {
		$plugin = $this->make_plugin();

		$this->make_receipt_order();
		$this->options = array_merge( $this->options, $options );

		Functions\when( 'get_user_by' )->justReturn( $this->make_user( 8306, 'customer@example.test' ) );

		$plugin->maybe_store_quick_login_order();

		$this->assertSame( array(), WC_PCR_Pending_Link::get_all() );
	}

	/**
	 * Settings combinations that mean no inline form is rendered.
	 *
	 * @return array
	 */
	public function settings_that_hide_the_form() {
		return array(
			'feature off'     => array( array( 'woocommerce_enable_post_checkout_registration' => 'no' ) ),
			'quick form off'  => array( array( 'wc_pcr_quick_form' => 'no' ) ),
			'auto linking on' => array( array( 'wc_pcr_auto_linking' => 'yes' ) ),
		);
	}

	/**
	 * A signed-in shopper has nothing to link.
	 */
	public function test_no_pending_link_for_a_signed_in_shopper() {
		$plugin = $this->make_plugin();

		$this->make_receipt_order();

		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'get_user_by' )->justReturn( $this->make_user( 8306, 'customer@example.test' ) );

		$plugin->maybe_store_quick_login_order();

		$this->assertSame( array(), WC_PCR_Pending_Link::get_all() );
	}

	/**
	 * An order with no matching account has nothing to link to.
	 */
	public function test_no_pending_link_without_a_matching_account() {
		$plugin = $this->make_plugin();

		$this->make_receipt_order();

		Functions\when( 'get_user_by' )->justReturn( false );

		$plugin->maybe_store_quick_login_order();

		$this->assertSame( array(), WC_PCR_Pending_Link::get_all() );
	}

	/**
	 * An order that already belongs to an account is left alone.
	 */
	public function test_no_pending_link_for_an_owned_order() {
		$plugin = $this->make_plugin();

		$this->make_receipt_order( 8306 );

		Functions\when( 'get_user_by' )->justReturn( $this->make_user( 8306, 'customer@example.test' ) );

		$plugin->maybe_store_quick_login_order();

		$this->assertSame( array(), WC_PCR_Pending_Link::get_all() );
	}

	/**
	 * The order key is checked before anything is registered.
	 */
	public function test_no_pending_link_when_the_order_key_is_wrong() {
		$plugin = $this->make_plugin();

		$this->make_receipt_order();
		$_GET['key'] = 'wc_order_guessed';

		Functions\when( 'get_user_by' )->justReturn( $this->make_user( 8306, 'customer@example.test' ) );

		$plugin->maybe_store_quick_login_order();

		$this->assertSame( array(), WC_PCR_Pending_Link::get_all() );
	}

	/**
	 * Away from the order received page there is nothing to do.
	 */
	public function test_nothing_happens_away_from_the_receipt_page() {
		$plugin = $this->make_plugin();

		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_user_by' )->justReturn( $this->make_user( 8306, 'customer@example.test' ) );

		$plugin->maybe_store_quick_login_order();

		$this->assertSame( array(), WC_PCR_Pending_Link::get_all() );
	}

	/**
	 * Put the request on the order received page with the quick form enabled.
	 *
	 * @param int $customer_id Account the order already belongs to, if any.
	 * @return WC_Order
	 */
	protected function make_receipt_order( $customer_id = 0 ) {
		$order = $this->make_order( 654676, 'customer@example.test', array(), $customer_id );

		$this->options = array(
			'woocommerce_enable_post_checkout_registration' => 'yes',
			'wc_pcr_quick_form'   => 'yes',
			'wc_pcr_auto_linking' => 'no',
		);

		Functions\when( 'get_query_var' )->justReturn( $order->get_id() );

		$_GET['key'] = $order->get_order_key();

		return $order;
	}
}
