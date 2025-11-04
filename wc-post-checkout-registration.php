<?php
/**
 * Plugin Name:       Post Checkout Registration for WooCommerce
 * Description:       Allows you to add an option to register with one-click after checkout
 * Version:           2.0.0
 * Author:            Ali Khallad
 * Author URI:        https://alikhallad.com
 * Text Domain:       wc-pcr
 * Domain Path:       /languages
 * Requires at least: 6.5
 * Tested up to:      6.7
 * Requires PHP:      7.4
 * WC requires at least: 8.2
 * WC tested up to:   9.6
 *
 * @package WC_PCR
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Declare compatibility with WooCommerce features.
 *
 * Declares compatibility with:
 * - High-Performance Order Storage (HPOS)
 * - WooCommerce Cart and Checkout Blocks
 *
 * @since 2.0.0
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * The core plugin class that is used to define all related hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wc-post-checkout-registration.php';

/**
 * Load block registration class for Gutenberg support.
 *
 * @since 2.0.0
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wc-pcr-blocks.php';

/**
 * Execute the plugin class to kick-off all related functionality,
 * that is registered via hooks.
 *
 * Store instance in global variable for access by blocks and other components.
 *
 * @since    1.0.0
 */
global $Run_WC_PCR;
$Run_WC_PCR = new Run_WC_PCR();
