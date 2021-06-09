<?php
/**
 * Plugin Name:       Post Checkout Registration for WooCommerce
 * Plugin URI:        https://alikhallad.com
 * Description:       Allows you to add an option to register with one-click after checkout
 * Version:           1.0.0
 * Author:            ALI KHALLAD
 * Author URI:        https://alikhallad.com
 * Text Domain:       wc-pcr
 * Domain Path:       /languages
 */
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
/**
 * The core plugin class that is used to define all related hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wc-post-checkout-registration.php';

/**
 * Execute the plugin class to kick-off all related functionality,
 * that is registered via hooks.
 *
 * @since    1.0.0
 */
function Run_WC_PCR() {
	$plugin = new Run_WC_PCR();
}
Run_WC_PCR();
