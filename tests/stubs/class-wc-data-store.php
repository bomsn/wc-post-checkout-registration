<?php
/**
 * Minimal WC_Data_Store test double.
 *
 * Only the customer-download store is exercised, via the downloadable-order
 * branch of order linking.
 *
 * @package WC_PCR
 */

/**
 * Stand-in for WooCommerce's data store loader.
 */
class WC_Data_Store {

	/**
	 * Order IDs passed to delete_by_order_id().
	 *
	 * @var int[]
	 */
	public static $deleted_order_ids = array();

	/**
	 * Load a data store.
	 *
	 * @param string $name Store name.
	 * @return self
	 */
	public static function load( $name ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- mirrors the WooCommerce signature.
		return new self();
	}

	/**
	 * Record a download-permission purge.
	 *
	 * @param int $order_id Order ID.
	 */
	public function delete_by_order_id( $order_id ) {
		self::$deleted_order_ids[] = (int) $order_id;
	}
}
