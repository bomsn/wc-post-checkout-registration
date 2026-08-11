<?php
/**
 * Shared test case.
 *
 * @package WC_PCR
 */

namespace WC_PCR\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use WC_Data_Store;
use WC_Order;
use WC_PCR_Pending_Link;
use WP_User;

/**
 * Base class wiring Brain Monkey and the WordPress functions the plugin relies on.
 */
abstract class TestCase extends PHPUnitTestCase {

	/**
	 * Notices added during the test, keyed by type.
	 *
	 * @var array
	 */
	protected $notices = array();

	/**
	 * Orders registered with the wc_get_order() stub, keyed by ID.
	 *
	 * @var WC_Order[]
	 */
	protected $orders = array();

	/**
	 * Stand-in for the WooCommerce singleton returned by WC().
	 *
	 * @var object
	 */
	protected $wc;

	/**
	 * Stand-in for the customer session held by WC()->session.
	 *
	 * @var WcSessionStub
	 */
	protected $session;

	/**
	 * Option values served by the get_option() stub.
	 *
	 * @var array
	 */
	protected $options = array();

	/**
	 * User meta writes recorded during the test.
	 *
	 * Each entry is array( 'update'|'delete', user ID, meta key, value ).
	 *
	 * @var array
	 */
	protected $user_meta_writes = array();

	/**
	 * Set up Brain Monkey and the baseline WordPress function stubs.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$_GET    = array();
		$_POST   = array();
		$_COOKIE = array();

		$this->notices          = array();
		$this->orders           = array();
		$this->options          = array();
		$this->user_meta_writes = array();

		WC_Data_Store::$deleted_order_ids = array();

		// WooCommerce derives its site-scoped user meta keys from the table prefix.
		$GLOBALS['wpdb'] = new WpdbStub(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the test suite owns this global.

		$this->reset_request_state();

		Functions\when( 'is_ssl' )->justReturn( true );
		Functions\when( 'wp_generate_password' )->alias(
			function ( $length = 12 ) {
				static $counter = 0;
				++$counter;
				return substr( str_repeat( 'secret' . $counter, 10 ), 0, $length );
			}
		);
		Functions\when( 'wp_hash' )->alias(
			function ( $data ) {
				return hash( 'sha256', 'salt' . $data );
			}
		);
		Functions\when( 'wp_rand' )->alias(
			function ( $min = 0 ) {
				return $min;
			}
		);
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'wc_clean' )->alias(
			function ( $value ) {
				return is_string( $value ) ? trim( $value ) : $value;
			}
		);
		Functions\when( 'absint' )->alias(
			function ( $value ) {
				return abs( (int) $value );
			}
		);
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();

		Functions\when( 'get_option' )->alias(
			function ( $name, $fallback = false ) {
				return array_key_exists( $name, $this->options ) ? $this->options[ $name ] : $fallback;
			}
		);

		// A customer-facing request with a live WooCommerce session, by default.
		$this->session = new WcSessionStub();
		$this->wc      = (object) array( 'session' => $this->session );

		Functions\when( 'WC' )->alias(
			function () {
				return $this->wc;
			}
		);

		Functions\when( 'wc_add_notice' )->alias(
			function ( $message, $type = 'success' ) {
				$this->notices[ $type ][] = $message;
			}
		);

		Functions\when( 'wc_get_order' )->alias(
			function ( $order_id ) {
				return isset( $this->orders[ (int) $order_id ] ) ? $this->orders[ (int) $order_id ] : false;
			}
		);

		// wc_downloadable_product_permissions() is intentionally not stubbed here:
		// only the downloadable-order test exercises it, and a blanket stub would
		// shadow that test's expectation.
		Functions\when( 'delete_user_meta' )->alias(
			function ( $user_id, $key ) {
				$this->user_meta_writes[] = array( 'delete', (int) $user_id, $key, null );
				return true;
			}
		);
		Functions\when( 'update_user_meta' )->alias(
			function ( $user_id, $key, $value ) {
				$this->user_meta_writes[] = array( 'update', (int) $user_id, $key, $value );
				return true;
			}
		);
		Functions\when( 'is_user_logged_in' )->justReturn( false );

		// Filters pass their value through and actions do nothing unless a test says otherwise.
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'do_action' )->justReturn( null );
	}

	/**
	 * Tear down Brain Monkey.
	 */
	protected function tearDown(): void {
		$this->reset_request_state();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Clear the plugin's per-request static state between tests.
	 */
	protected function reset_request_state() {
		$reflection = new \ReflectionClass( \Run_WC_PCR::class );

		$linked = $reflection->getProperty( 'linked_this_request' );
		$linked->setAccessible( true );
		$linked->setValue( null, array() );

		$displayed = $reflection->getProperty( 'notice_displayed' );
		$displayed->setAccessible( true );
		$displayed->setValue( null, false );
	}

	/**
	 * Build an order and register it with the wc_get_order() stub.
	 *
	 * @param int    $id            Order ID.
	 * @param string $billing_email Billing email.
	 * @param array  $meta          Initial meta.
	 * @param int    $customer_id   Initial customer ID.
	 * @return WC_Order
	 */
	protected function make_order( $id, $billing_email = 'guest@example.test', array $meta = array(), $customer_id = 0 ) {
		$order               = new WC_Order( $id, $billing_email, $meta, $customer_id );
		$this->orders[ $id ] = $order;

		return $order;
	}

	/**
	 * Build a user.
	 *
	 * @param int    $id    User ID.
	 * @param string $email User email.
	 * @return WP_User
	 */
	protected function make_user( $id = 42, $email = 'guest@example.test' ) {
		return new WP_User( $id, $email, 'customer' . $id );
	}

	/**
	 * Register a pending link for an order and put the secret in the browser.
	 *
	 * @param WC_Order $order Order to make pending.
	 * @return string The secret handed to the browser.
	 */
	protected function make_pending( WC_Order $order ) {
		WC_PCR_Pending_Link::add( $order );

		return WC_PCR_Pending_Link::get( $order->get_id() );
	}

	/**
	 * Build the plugin instance without registering any WordPress hooks.
	 *
	 * @return \Run_WC_PCR
	 */
	protected function make_plugin() {
		$reflection = new \ReflectionClass( \Run_WC_PCR::class );
		$plugin     = $reflection->newInstanceWithoutConstructor();

		return $plugin;
	}

	/**
	 * Call a protected/private method on the plugin.
	 *
	 * @param object $target Target object.
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	protected function invoke( $target, $method, array $args = array() ) {
		$reflection = new \ReflectionMethod( $target, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $target, $args );
	}

	/**
	 * Call a protected/private static method on the pending-link store.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	protected function invoke_static( $method, array $args = array() ) {
		$reflection = new \ReflectionMethod( WC_PCR_Pending_Link::class, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( null, $args );
	}

	/**
	 * Notices of a given type.
	 *
	 * @param string $type Notice type.
	 * @return array
	 */
	protected function notices( $type ) {
		return isset( $this->notices[ $type ] ) ? $this->notices[ $type ] : array();
	}
}
