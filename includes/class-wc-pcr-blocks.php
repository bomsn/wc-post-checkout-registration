<?php
/**
 * Block registration and management
 *
 * @package WC_PCR
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles block registration and asset management
 */
class WC_PCR_Blocks {

	/**
	 * Initialize block registration
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register all plugin blocks
	 */
	public function register_blocks() {
		register_block_type(
			plugin_dir_path( __DIR__ ) . 'blocks/registration-prompt/build'
		);
	}
}

new WC_PCR_Blocks();
