<?php
/**
 * Minimal WC_Order test double.
 *
 * Records meta writes and saves so tests can assert on what the plugin
 * persisted, without needing a WooCommerce install or a database.
 *
 * @package WC_PCR
 */

/**
 * Stand-in for WooCommerce's order object.
 */
class WC_Order {

	/**
	 * Order ID.
	 *
	 * @var int
	 */
	protected $id;

	/**
	 * Meta data keyed by meta key.
	 *
	 * @var array
	 */
	protected $meta = array();

	/**
	 * Assigned customer ID.
	 *
	 * @var int
	 */
	protected $customer_id = 0;

	/**
	 * Billing email.
	 *
	 * @var string
	 */
	protected $billing_email = '';

	/**
	 * Whether the order contains a downloadable item.
	 *
	 * @var bool
	 */
	public $downloadable = false;

	/**
	 * Order key.
	 *
	 * @var string
	 */
	public $order_key = 'wc_order_teststub';

	/**
	 * Creation date, or null when unknown.
	 *
	 * @var \DateTime|null
	 */
	public $date_created;

	/**
	 * Number of times save() was called.
	 *
	 * @var int
	 */
	public $save_calls = 0;

	/**
	 * Number of times save_meta_data() was called.
	 *
	 * @var int
	 */
	public $save_meta_calls = 0;

	/**
	 * Constructor.
	 *
	 * @param int    $id            Order ID.
	 * @param string $billing_email Billing email.
	 * @param array  $meta          Initial meta.
	 * @param int    $customer_id   Initial customer ID.
	 */
	public function __construct( $id, $billing_email = '', array $meta = array(), $customer_id = 0 ) {
		$this->id            = (int) $id;
		$this->billing_email = $billing_email;
		$this->meta          = $meta;
		$this->customer_id   = (int) $customer_id;
	}

	/**
	 * Order ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Order number.
	 *
	 * @return string
	 */
	public function get_order_number() {
		return (string) $this->id;
	}

	/**
	 * Read a meta value.
	 *
	 * @param string $key Meta key.
	 * @return mixed Empty string when unset, mirroring WC_Data::get_meta().
	 */
	public function get_meta( $key ) {
		return array_key_exists( $key, $this->meta ) ? $this->meta[ $key ] : '';
	}

	/**
	 * Write a meta value.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 */
	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	/**
	 * Delete a meta value.
	 *
	 * @param string $key Meta key.
	 */
	public function delete_meta_data( $key ) {
		unset( $this->meta[ $key ] );
	}

	/**
	 * Whether a meta key is present.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	public function has_meta( $key ) {
		return array_key_exists( $key, $this->meta );
	}

	/**
	 * Persist meta.
	 */
	public function save_meta_data() {
		++$this->save_meta_calls;
	}

	/**
	 * Persist the order.
	 *
	 * @return int
	 */
	public function save() {
		++$this->save_calls;
		return $this->id;
	}

	/**
	 * Assigned customer ID.
	 *
	 * @return int
	 */
	public function get_customer_id() {
		return $this->customer_id;
	}

	/**
	 * Assign a customer.
	 *
	 * @param int $customer_id Customer ID.
	 */
	public function set_customer_id( $customer_id ) {
		$this->customer_id = (int) $customer_id;
	}

	/**
	 * Billing email.
	 *
	 * @return string
	 */
	public function get_billing_email() {
		return $this->billing_email;
	}

	/**
	 * Whether the order has a downloadable item.
	 *
	 * @return bool
	 */
	public function has_downloadable_item() {
		return $this->downloadable;
	}

	/**
	 * Order key.
	 *
	 * @return string
	 */
	public function get_order_key() {
		return $this->order_key;
	}

	/**
	 * Creation date.
	 *
	 * @return \DateTime|null
	 */
	public function get_date_created() {
		return $this->date_created;
	}
}
