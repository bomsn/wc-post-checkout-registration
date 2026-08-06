<?php
/**
 * Minimal WP_User test double.
 *
 * @package WC_PCR
 */

/**
 * Stand-in for WordPress' user object.
 */
class WP_User {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $ID = 0; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase -- mirrors WP_User.

	/**
	 * User email.
	 *
	 * @var string
	 */
	public $user_email = '';

	/**
	 * User login.
	 *
	 * @var string
	 */
	public $user_login = '';

	/**
	 * First name.
	 *
	 * @var string
	 */
	public $first_name = '';

	/**
	 * Constructor.
	 *
	 * @param int    $id    User ID.
	 * @param string $email User email.
	 * @param string $login User login.
	 */
	public function __construct( $id = 0, $email = '', $login = '' ) {
		$this->ID         = (int) $id;
		$this->user_email = $email;
		$this->user_login = $login;
	}
}
