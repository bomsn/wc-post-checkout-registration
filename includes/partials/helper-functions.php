<?php
/**
 * Helper functions for WC Post Checkout Registration
 *
 * @package WC_PCR
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wc_pcr_generate_random_token' ) ) :
	/**
	 * Generate a random token string.
	 *
	 * @param int $length Length of the token to generate.
	 * @return string Random token.
	 */
	function wc_pcr_generate_random_token( $length ) {
		$length = (int) $length;

		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$token = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$token .= substr( $chars, wp_rand( 0, strlen( $chars ) - 1 ), 1 );
		}

		return $token;
	}

endif;
