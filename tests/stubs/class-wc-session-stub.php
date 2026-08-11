<?php
/**
 * Minimal WooCommerce session test double.
 *
 * @package WC_PCR
 */

namespace WC_PCR\Tests;

/**
 * Stand-in for the customer session held by WC()->session.
 */
class WcSessionStub {

	/**
	 * Session data.
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * Read a session value.
	 *
	 * @param string $key      Session key.
	 * @param mixed  $fallback Value returned when the key is unset.
	 * @return mixed
	 */
	public function get( $key, $fallback = null ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $fallback;
	}

	/**
	 * Write a session value.
	 *
	 * @param string $key   Session key.
	 * @param mixed  $value Session value.
	 */
	public function set( $key, $value ) {
		$this->data[ $key ] = $value;
	}
}
