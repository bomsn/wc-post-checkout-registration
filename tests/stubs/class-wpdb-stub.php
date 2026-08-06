<?php
/**
 * Minimal $wpdb test double.
 *
 * Only the blog prefix is needed, to build WooCommerce's site-scoped user meta
 * keys.
 *
 * @package WC_PCR
 */

namespace WC_PCR\Tests;

/**
 * Stand-in for WordPress' database handle.
 */
class WpdbStub {

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_testprefix_';

	/**
	 * Blog-specific table prefix.
	 *
	 * @param int|null $blog_id Blog ID.
	 * @return string
	 */
	public function get_blog_prefix( $blog_id = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- mirrors the WordPress signature.
		return $this->prefix;
	}
}
