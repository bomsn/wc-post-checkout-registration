<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Run_WC_PCR' ) ) {
	class Run_WC_PCR {


		public $version = '2.1.0';

		/**
		 * Order meta holding the order-specific registration/linking token.
		 *
		 * @since 2.1.0
		 * @var string
		 */
		const TOKEN_META = '_wc_pcr_post_checkout_registration';

		/**
		 * Order meta holding the registration/linking token's expiry timestamp.
		 *
		 * @since 2.1.0
		 * @var string
		 */
		const TOKEN_EXPIRES_META = '_wc_pcr_token_expires';

		/**
		 * Track if registration notice has been displayed to prevent duplication.
		 *
		 * @since 2.0.0
		 * @var bool
		 */
		private static $notice_displayed = false;

		/**
		 * Order IDs already linked during this request, to keep notices unique.
		 *
		 * @since 2.1.0
		 * @var int[]
		 */
		private static $linked_this_request = array();

		/**
		 * Order ID and token for the quick login form rendered in this request.
		 *
		 * Request-scoped on purpose. This used to live in the object cache, which
		 * on a site with a persistent backend is shared between every visitor and
		 * would serve one customer's pending order into another customer's login
		 * form.
		 *
		 * @since 2.1.0
		 * @var array
		 */
		private $quick_form_prompt = array();

		public function __construct() {
			$this->load_dependencies();
			$this->define_hooks();
		}

		/**
		 * Load all dependencies here.
		 *
		 * @since    1.0.0
		 * @access   private
		 */
		private function load_dependencies() {
			require plugin_dir_path( __FILE__ ) . 'partials/helper-functions.php';
		}
		/**
		 * Register all of the hooks related to the admin and public functionality
		 * of the plugin.
		 *
		 * @since    1.0.0
		 * @access   private
		 */
		private function define_hooks() {

			// Load translations
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

			// General hooks
			add_filter( 'woocommerce_account_settings', array( $this, 'add_pcr_enable_fields' ) );

			// Add plugin settings link to plugin action links
			add_filter( 'plugin_action_links_wc-post-checkout-registration/wc-post-checkout-registration.php', array( $this, 'settings_link' ) );

			if ( get_option( 'woocommerce_enable_post_checkout_registration', 'no' ) === 'yes' ) {
				// maybe render the prompt on the "thank you" page
				add_action( 'woocommerce_before_thankyou', array( $this, 'maybe_show_registration_notice' ), 10, 1 );
			}

			// if the registration link is clicked, validate and register the customer
			add_action( 'template_redirect', array( $this, 'maybe_register_new_customer' ) );
			// Store order ID and token so it's linked to the user account after login
			add_action( 'template_redirect', array( $this, 'maybe_store_order_data' ) );

			// add login form fields to indicate when we should link previous orders
			add_action( 'woocommerce_login_form', array( $this, 'add_custom_tracking_fields' ) );

			/*
			 * Apply pending link requests on authentication rather than on a login
			 * form submission. A customer who forgot their password never touches
			 * the login form: WooCommerce authenticates them from the reset form via
			 * wc_set_customer_auth_cookie(), which does not fire `wp_login`.
			 */
			add_action( 'wp_login', array( $this, 'link_previous_orders' ), 10, 2 );
			// WooCommerce's own reset handler, fired after auto-login and before the redirect.
			add_action( 'woocommerce_customer_reset_password', array( $this, 'link_after_password_reset' ), 10, 1 );
			// Core wp-login.php resets, and WooCommerce 10.9+ which fires both.
			add_action( 'after_password_reset', array( $this, 'link_after_password_reset' ), 10, 1 );
			// Catch-up for any other way a customer may end up authenticated: a later
			// request, a second tab, a social login or auto-login plugin. Runs after
			// maybe_store_order_data() so an already-authenticated customer following
			// the prompt is served within the same request.
			add_action( 'template_redirect', array( $this, 'maybe_link_pending_orders' ), 20 );

			// Add shortcode ( to use for custom thank you pages )
			add_shortcode( 'wc_pcr_message', array( $this, 'get_registration_notice' ) );
		}


		/**
		 * Loads the plugin's translated strings.
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function load_textdomain() {

			// Get translations path relative to the plugins directory, which in our case is `ali-khallad/languages`
			$plugin_rel_path = basename( dirname( __DIR__ ) ) . '/languages';
			// Load the translated strings
			load_plugin_textdomain( 'wc-pcr', false, $plugin_rel_path );
		}
		/**
		 * Add an option to enable post-checkout registration to the account settings.
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function add_pcr_enable_fields( $settings ) {

			$updated_settings = array();

			foreach ( $settings as $section ) {

				$updated_settings[] = $section;

				// after the account registration options section
				if (
					isset( $section['id'] ) && 'account_registration_options' == $section['id'] &&
					isset( $section['type'] ) && 'sectionend' == $section['type']
				) {

					$updated_settings[] = array(
						'title' => '',
						'type'  => 'title',
						'desc'  => '<div id="account_registration_options"></div><hr>',
						'id'    => 'wc_pcr_line',
					);

					$updated_settings[] = array(
						'sectionend' => 'wc_pcr_line',
						'type'       => 'sectionend',
					);

					$updated_settings[] = array(
						'title' => __( 'Post-checkout registration', 'wc-pcr' ),
						'type'  => 'title',
						'desc'  => '<div id="account_registration_options"></div>',
						'id'    => 'wc_pcr_options',
					);

					$updated_settings[] = array(
						'title'    => __( 'Enable', 'wc-pcr' ),
						'desc'     => __( 'Enable post-checkout registration.', 'wc-pcr' ),
						'desc_tip' => __( 'Adds an option to "thank you" page to allow guest users to register with a single click using the data from their order. It will also allow existing customers to link their orders upon login with a single click. Automated linking for existing users can be enabled as well from the options below.', 'wc-pcr' ),
						'id'       => 'woocommerce_enable_post_checkout_registration',
						'default'  => 'no',
						'type'     => 'checkbox',
						'autoload' => true,
					);

					$updated_settings[] = array(
						'title'    => __( 'Automatically link orders', 'wc-pcr' ),
						'desc'     => __( 'Link orders to existing accounts automatically.', 'wc-pcr' ),
						'desc_tip' => __( 'Automatically link orders to any existing account with the same order email. This option will override all the options below and will force the user to login to view their order.', 'wc-pcr' ),
						'id'       => 'wc_pcr_auto_linking',
						'default'  => 'no',
						'type'     => 'checkbox',
						'autoload' => true,
					);

					$updated_settings[] = array(
						'title'    => __( 'New account message', 'wc-pcr' ),
						'desc_tip' => __( 'Define the message that should appear when the user doesn`t have an account.', 'wc-pcr' ),
						'id'       => 'wc_pcr_new_account_msg',
						'type'     => 'textarea',
						'css'      => 'min-width: 50%; height: 75px;',
						'default'  => $this->get_default_new_account_msg(),
					);
					$updated_settings[] = array(
						'title'    => __( 'Existing account message', 'wc-pcr' ),
						'desc_tip' => __( 'Define the message that should appear when the user have an account already.', 'wc-pcr' ),
						'id'       => 'wc_pcr_existing_account_msg',
						'type'     => 'textarea',
						'css'      => 'min-width: 50%; height: 75px;',
						'default'  => $this->get_default_existing_account_msg(),
					);

					$updated_settings[] = array(
						'title'    => __( 'Quick login', 'wc-pcr' ),
						'desc'     => __( 'Enable quick login form.', 'wc-pcr' ),
						'desc_tip' => __( 'This option will display the login form right below the "Existing account message". Note that this will work only when "Automatically link orders" is disabled.', 'wc-pcr' ),
						'id'       => 'wc_pcr_quick_form',
						'default'  => 'no',
						'type'     => 'checkbox',
						'autoload' => true,
					);

					$updated_settings[] = array(
						'sectionend' => 'wc_pcr_options',
						'type'       => 'sectionend',
					);

					$updated_settings[] = array(
						'title' => '',
						'type'  => 'title',
						'desc'  => '<hr>',
						'id'    => 'wc_pcr_line',
					);

					$updated_settings[] = array(
						'sectionend' => 'wc_pcr_line',
						'type'       => 'sectionend',
					);
				}
			}

			return $updated_settings;
		}

		/**
		 * Add 'settings' links to the plugin action links
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function settings_link( $links ) {
			// Build and escape the URL.
			$url = esc_url(
				add_query_arg(
					array(
						'page' => 'wc-settings',
						'tab'  => 'account#account_registration_options',
					),
					admin_url( 'admin.php' )
				)
			);
			// Create the link.
			$settings_link = '<a href="' . $url . '">' . esc_html__( 'Settings', 'wc-pcr' ) . '</a>';
			// Adds the link to the end of the array.
			array_push(
				$links,
				$settings_link
			);
			return $links;
		}
		/**
		 * Checks the WooCommerce thankyou page to render registration or login prompt immediately.
		 *
		 * @since 1.0.0
		 *
		 * @param string    $text the thankyou page message text
		 * @param \WC_Order $order the placed order object
		 * @return string the updated text
		 */
		public function maybe_show_registration_notice( $order_id, $print_notices = true ) {
			// Prevent duplicate display on the same page
			if ( self::$notice_displayed ) {
				return;
			}

			$order = wc_get_order( $order_id );
			// sanity check
			if ( $order instanceof WC_Order ) {

				if ( ! is_user_logged_in() ) {
					// Mark as displayed to prevent duplication
					self::$notice_displayed = true;

					$existing_user = get_user_by( 'email', $order->get_billing_email() );

					if ( $existing_user && get_option( 'wc_pcr_auto_linking', 'no' ) === 'yes' && ! $order->get_meta( '_wc_pcr_order_linked' ) ) {
						// If not already linked, link any non-assigned orders with the customer email to their account
						wc_update_new_customer_past_orders( $existing_user->ID );
						$order->update_meta_data( '_wc_pcr_order_linked', true );
						$order->save_meta_data();
						// Refresh the page
						$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
						wp_safe_redirect( esc_url_raw( $current_url ) );
						exit;
					} else {
						// do not use a nonce, favoring order-specific validation
						// this way, a user can't just get a valid nonce, then change the order ID in the registration link
						$token = $this->get_order_token( $order );

						if ( $existing_user ) {
							$quick_login_form_enabled = get_option( 'wc_pcr_quick_form', 'no' ) === 'yes';
							$message                  = $this->render_link_order_prompt( $order, $token, ! $quick_login_form_enabled );
							echo $message;

							if ( $quick_login_form_enabled ) {
								/*
								 * The inline form logs in on this same page, so register the
								 * pending request now. That also covers the customer who
								 * detours through "Lost your password?" from here.
								 */
								WC_PCR_Pending_Link::add( $order );

								$this->quick_form_prompt = array(
									'order_id' => $order->get_id(),
									'token'    => $token,
								);

								if ( $print_notices ) {
									wc_print_notices(); // Print Woo notices
								}
								woocommerce_login_form(); // Print Woo login form
							}
						} else {
							$message = $this->render_registration_prompt( $order, $token );
							echo $message;
						}
					}
				}
			}
		}


		/**
		 * Renders a prompt to log in to link this existing order.
		 *
		 * @since 1.0.0
		 *
		 * @param \WC_Order $order the currently placed order
		 * @param string    $token the login token to prompt linking old orders
		 * @return string the login prompt message
		 */
		protected function render_link_order_prompt( $order, $token, $show_button = true ) {

			$url = add_query_arg(
				array(
					'link_order_id' => $order->get_id(),
					'login_token'   => $token,
				),
				trailingslashit( wc_get_page_permalink( 'myaccount' ) )
			);

			$message = get_option( 'wc_pcr_existing_account_msg', $this->get_default_existing_account_msg() );
			if ( $show_button ) {
				$message .= ' <a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Log in', 'wc-pcr' ) . '</a>';
			}

			return "<div class='woocommerce-info'>{$message}</div>";
		}

		/**
		 * Renders the registration prompt on the thankyou page
		 *
		 * @since 1.0.0
		 *
		 * @param \WC_Order $order the order object
		 * @param string    $token the registration token for the order
		 * @return string the message to render
		 */
		protected function render_registration_prompt( $order, $token ) {

			$url = add_query_arg(
				array(
					'registration_order_id' => $order->get_id(),
					'registration_token'    => $token,
				),
				trailingslashit( wc_get_page_permalink( 'myaccount' ) )
			);

			$message  = get_option( 'wc_pcr_new_account_msg', $this->get_default_new_account_msg() );
			$message .= ' <a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Create Account', 'wc-pcr' ) . '</a>';

			return "<div class='woocommerce-info'>{$message}</div>";
		}

		/**
		 * Outputs hidden fields to POST the login token and associated order.
		 *
		 * @since 1.0.0
		 */
		public function add_custom_tracking_fields() {

			if ( ! empty( $this->quick_form_prompt ) ) {
				$order_id = (int) $this->quick_form_prompt['order_id'];
				$token    = (string) $this->quick_form_prompt['token'];
			} elseif ( isset( $_GET['link_order_id'], $_GET['login_token'] ) ) {
				$order_id = absint( $_GET['link_order_id'] );
				$token    = wc_clean( wp_unslash( $_GET['login_token'] ) );
			} else {
				return;
			}

			if ( ! $order_id || '' === $token ) {
				return;
			}

			?>
			<p class="form-row">
				<input class="woocommerce-Input input-hidden" type="hidden" name="wc_pcr_link_order_id" id="wc_pcr_link_order_id" value="<?php echo esc_attr( $order_id ); ?>" />
				<input class="woocommerce-Input input-hidden" type="hidden" name="wc_pcr_login_token" id="wc_pcr_login_token" value="<?php echo esc_attr( $token ); ?>" />
			</p>
			<?php
		}


		/**
		 * Links the pending order upon customer login.
		 *
		 * @since 1.0.0
		 *
		 * @param string   $username the username, unused
		 * @param \WP_User $user the logged in user
		 */
		public function link_previous_orders( $username, $user ) {

			if ( ! $user instanceof WP_User ) {
				return;
			}

			$linked = $this->process_pending_links( $user, true );

			/*
			 * Legacy path: a custom login template may post these fields directly
			 * without ever having been through maybe_store_order_data(), so there is
			 * no browser secret to check. Fall back to the order's own token.
			 */
			if ( ! $linked && isset( $_POST['wc_pcr_link_order_id'], $_POST['wc_pcr_login_token'] ) ) {
				$order_id = absint( $_POST['wc_pcr_link_order_id'] );
				$token    = wc_clean( wp_unslash( $_POST['wc_pcr_login_token'] ) );
				$order    = wc_get_order( $order_id );

				if ( ! $order instanceof WC_Order || ! $this->token_matches( $order, $token ) ) {
					$this->add_notice( __( 'Error linking your previous order.', 'wc-pcr' ), 'error' );
					return;
				}

				$this->link_order( $order, $user );
			}
		}

		/**
		 * Links the pending order after a password reset.
		 *
		 * WooCommerce authenticates the customer inside its reset handler via
		 * wc_set_customer_auth_cookie(), which never fires `wp_login`. Without this
		 * hook a customer who forgot their password would complete the entire
		 * prompted flow and still end up with an unlinked order.
		 *
		 * Safe to run twice: WooCommerce 10.9+ fires both `after_password_reset` and
		 * `woocommerce_customer_reset_password`, and linking clears its own state.
		 *
		 * @since 2.1.0
		 *
		 * @param \WP_User $user The user whose password was reset.
		 */
		public function link_after_password_reset( $user ) {

			if ( ! $user instanceof WP_User ) {
				return;
			}

			$this->process_pending_links( $user, true );
		}

		/**
		 * Applies any pending link request left over from an earlier request.
		 *
		 * Covers authentication routes the plugin cannot hook directly: auto-login
		 * plugins, social login, "set your password" links, or simply the customer
		 * logging in from a second tab while the thank-you page is still open.
		 *
		 * @since 2.1.0
		 */
		public function maybe_link_pending_orders() {

			if ( ! is_user_logged_in() || empty( $_COOKIE[ WC_PCR_Pending_Link::COOKIE ] ) ) {
				return;
			}

			$user = wp_get_current_user();

			if ( ! $user instanceof WP_User || ! $user->ID ) {
				return;
			}

			$this->process_pending_links( $user, false );
		}

		/**
		 * Applies every pending link request this browser holds.
		 *
		 * Entries are dropped from the browser whether they succeed or fail
		 * terminally, so a rejected request never retries on each subsequent page
		 * load and the cookie cleans itself up.
		 *
		 * @since 2.1.0
		 *
		 * @param \WP_User $user     The authenticated user.
		 * @param bool     $announce Whether to surface a notice for terminal failures.
		 * @return int Number of orders linked.
		 */
		protected function process_pending_links( $user, $announce = false ) {

			$pending = WC_PCR_Pending_Link::get_all();

			if ( empty( $pending ) ) {
				return 0;
			}

			$linked = 0;

			foreach ( $pending as $order_id => $secret ) {

				$order = wc_get_order( $order_id );

				if ( $order instanceof WC_Order && WC_PCR_Pending_Link::verify( $order, $secret ) ) {
					if ( $this->link_order( $order, $user, $announce ) ) {
						++$linked;
					}
				} elseif ( $announce ) {
					$this->add_notice( __( 'Error linking your previous order.', 'wc-pcr' ), 'error' );
				}

				WC_PCR_Pending_Link::remove( $order_id );
			}

			return $linked;
		}

		/**
		 * Assigns a guest order to an account.
		 *
		 * Deliberately narrower than wc_update_new_customer_past_orders(), which
		 * claims every unassigned order sharing the account email. The customer
		 * asked to link one specific order and that is what the prompt promises,
		 * so linking anything else would be a surprise — and on a large store that
		 * unbounded query is expensive. Stores that want the old sweep can opt in
		 * through `wc_pcr_link_all_past_orders`.
		 *
		 * @since 2.1.0
		 *
		 * @param \WC_Order $order    The order to link.
		 * @param \WP_User  $user     The account to link it to.
		 * @param bool      $announce Whether to surface a notice on terminal failure.
		 * @return bool True when the order was linked by this call.
		 */
		protected function link_order( $order, $user, $announce = true ) {

			$order_id = $order->get_id();

			// Already ours, or already someone else's. Never reassign an owned order.
			if ( 0 !== $order->get_customer_id() ) {
				return false;
			}

			if ( in_array( $order_id, self::$linked_this_request, true ) ) {
				return false;
			}

			/**
			 * Filters whether the order's billing email must match the account email.
			 *
			 * The billing email is the only thing tying a guest order to a person, and
			 * it is the same rule WooCommerce core applies. Relaxing this lets anyone
			 * holding a link request attach the order — and its address and contents —
			 * to an arbitrary account.
			 *
			 * @since 2.1.0
			 *
			 * @param bool      $required whether the emails must match
			 * @param \WC_Order $order    the order being linked
			 * @param \WP_User  $user     the account it would be linked to
			 */
			$require_email_match = apply_filters( 'wc_pcr_require_email_match', true, $order, $user );

			if ( $require_email_match ) {
				$order_email = strtolower( trim( (string) $order->get_billing_email() ) );
				$user_email  = strtolower( trim( (string) $user->user_email ) );

				if ( '' === $order_email || $order_email !== $user_email ) {
					if ( $announce ) {
						$this->add_notice( __( 'We could not link that order to your account because it was placed with a different email address.', 'wc-pcr' ), 'error' );
					}
					return false;
				}
			}

			/**
			 * Filters whether an order may be linked to an account.
			 *
			 * @since 2.1.0
			 *
			 * @param bool      $can_link whether linking is allowed
			 * @param \WC_Order $order    the order being linked
			 * @param \WP_User  $user     the account it would be linked to
			 */
			if ( ! apply_filters( 'wc_pcr_can_link_order', true, $order, $user ) ) {
				return false;
			}

			$order->set_customer_id( $user->ID );

			// One-shot: the token and the browser secret must not survive their use.
			$order->delete_meta_data( self::TOKEN_META );
			$order->delete_meta_data( self::TOKEN_EXPIRES_META );
			WC_PCR_Pending_Link::forget_order_state( $order );

			$order->update_meta_data( '_wc_pcr_order_linked', true );
			$order->save();

			self::$linked_this_request[] = $order_id;

			// Mirror the housekeeping WooCommerce performs when it claims a past order.
			if ( $order->has_downloadable_item() ) {
				$data_store = WC_Data_Store::load( 'customer-download' );
				$data_store->delete_by_order_id( $order_id );
				wc_downloadable_product_permissions( $order_id, true );
			}

			$this->reset_customer_order_stats( $user->ID );

			/** This action is documented in woocommerce/includes/wc-user-functions.php */
			do_action( 'woocommerce_update_new_customer_past_order', $order_id, $user );

			/**
			 * Fires after a guest order has been linked to an existing account.
			 *
			 * @since 2.1.0
			 *
			 * @param \WC_Order $order the linked order
			 * @param \WP_User  $user  the account it was linked to
			 */
			do_action( 'wc_pcr_order_linked', $order, $user );

			/**
			 * Filters whether to also claim every other guest order sharing the account email.
			 *
			 * This was the behaviour before 2.1.0. It is off by default because it
			 * links orders the customer never asked about and runs an unbounded query.
			 *
			 * @since 2.1.0
			 *
			 * @param bool     $link_all whether to sweep remaining guest orders
			 * @param \WP_User $user     the account being linked to
			 */
			if ( apply_filters( 'wc_pcr_link_all_past_orders', false, $user ) ) {
				wc_update_new_customer_past_orders( $user->ID );
			}

			/* translators: Placeholders: %s - order number */
			$this->add_notice( sprintf( __( 'Order #%s has been linked to your account!', 'wc-pcr' ), $order->get_order_number() ), 'success' );

			return true;
		}

		/**
		 * Invalidates the cached order count and spend for a customer.
		 *
		 * Without this, My Account keeps showing the totals from before the order
		 * was attached. WooCommerce signals "recount" by blanking the values rather
		 * than deleting them.
		 *
		 * WooCommerce 9.0 moved these keys behind a site-specific suffix
		 * (`wc_order_count_wp_prefix`) to make them multisite-aware, so both key
		 * shapes are cleared. The suffix is derived the same way WooCommerce derives
		 * it, since the helper that owns it lives in an `Internal` namespace and is
		 * not part of the public API.
		 *
		 * @since 2.1.0
		 *
		 * @param int $user_id The customer's user ID.
		 */
		protected function reset_customer_order_stats( $user_id ) {

			global $wpdb;

			$suffix = '_' . rtrim( $wpdb->get_blog_prefix(), '_' );

			foreach ( array( 'wc_order_count', 'wc_money_spent' ) as $key ) {
				update_user_meta( $user_id, $key, '' );
				update_user_meta( $user_id, $key . $suffix, '' );
			}

			delete_user_meta( $user_id, 'wc_last_order' );
			delete_user_meta( $user_id, 'wc_last_order' . $suffix );
		}

		/**
		 * Adds a WooCommerce notice when there is somewhere to put it.
		 *
		 * `after_password_reset` also fires on wp-login.php and on WP-CLI, where
		 * there is no customer session. wc_add_notice() would discard the notice
		 * and emit a _doing_it_wrong() warning into the response.
		 *
		 * @since 2.1.0
		 *
		 * @param string $message The notice text.
		 * @param string $type    The notice type.
		 */
		protected function add_notice( $message, $type = 'success' ) {

			if ( ! function_exists( 'WC' ) || ! WC()->session ) {
				return;
			}

			wc_add_notice( $message, $type );
		}

		/**
		 * Returns the order's linking token, generating one if needed.
		 *
		 * Reusing a live token matters because the thank-you page can be rendered
		 * many times: regenerating on every view would invalidate the link the
		 * customer already has open in another tab or in their order email.
		 *
		 * @since 2.1.0
		 *
		 * @param \WC_Order $order The order to issue a token for.
		 * @return string The token.
		 */
		protected function get_order_token( $order ) {

			$token   = (string) $order->get_meta( self::TOKEN_META );
			$expires = (int) $order->get_meta( self::TOKEN_EXPIRES_META );

			if ( '' !== $token && ( ! $expires || $expires > time() ) ) {
				return $token;
			}

			$token = wc_pcr_generate_random_token( 32 );

			$order->update_meta_data( self::TOKEN_META, $token );
			$order->update_meta_data( self::TOKEN_EXPIRES_META, time() + WC_PCR_Pending_Link::TTL );
			$order->save_meta_data();

			return $token;
		}

		/**
		 * Validates a supplied token against the order's stored token.
		 *
		 * @since 2.1.0
		 *
		 * @param \WC_Order $order The order to check.
		 * @param string    $token The token supplied by the request.
		 * @return bool True when the token is valid and unexpired.
		 */
		protected function token_matches( $order, $token ) {

			$stored = (string) $order->get_meta( self::TOKEN_META );

			if ( '' === $stored || '' === $token ) {
				return false;
			}

			$expires = (int) $order->get_meta( self::TOKEN_EXPIRES_META );

			if ( $expires && $expires < time() ) {
				return false;
			}

			return hash_equals( $stored, $token );
		}

		/**
		 * Registers a new customer if "create" link is valid.
		 *
		 * @since 1.0.0
		 */
		public function maybe_register_new_customer() {
			if ( ! is_account_page() || ! isset( $_REQUEST['registration_order_id'] ) ) {
				return;
			}

			// now we have the order ID param, but not a token, boot this faker!
			if ( ! isset( $_REQUEST['registration_token'] ) ) {
				wc_add_notice( __( 'Whoops, looks like this registration link is not valid.', 'wc-pcr' ), 'error' );
				return;
			}

			$order_id = (int) $_REQUEST['registration_order_id'];
			$token    = wc_clean( $_REQUEST['registration_token'] );

			try {

				$user = $this->process_post_checkout_registration( $order_id, $token );

				/* translators: Placeholder: %1$s - first name, %2$s - <a> tag, %3$s - </a> tag */
				wc_add_notice(
					sprintf(
						__( 'Welcome, %1$s! Your %2$saccount information%3$s has been saved.', 'wc-pcr' ),
						$user->first_name,
						'<strong><a href="' . wc_get_endpoint_url( 'edit-address' ) . '">',
						'</a></strong>'
					),
					'success'
				);

				return;
			} catch ( Exception $e ) {

				wc_add_notice( $e->getMessage(), 'error' );
				return;
			}
		}
		/**
		 * Registers a pending link request when the customer follows the prompt.
		 *
		 * The URL token is exchanged here, once, for a fresh single-use secret. That
		 * keeps the order's long-lived token out of the browser and lets the request
		 * survive however many redirects the customer's authentication takes.
		 *
		 * @since 1.0.1
		 */
		public function maybe_store_order_data() {
			if ( ! isset( $_GET['link_order_id'], $_GET['login_token'] ) ) {
				return;
			}

			$order_id = absint( $_GET['link_order_id'] );
			$token    = wc_clean( wp_unslash( $_GET['login_token'] ) );
			$order    = $order_id ? wc_get_order( $order_id ) : false;

			// Fail closed and silently: an invalid link must not be an oracle.
			if ( ! $order instanceof WC_Order || 0 !== $order->get_customer_id() ) {
				return;
			}

			if ( ! $this->token_matches( $order, $token ) ) {
				return;
			}

			WC_PCR_Pending_Link::add( $order );
		}


		/**
		 * Validate the create account token for the order, and create a customer if valid.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $order_id ID of the order ID we should pull customer info for
		 * @param string $token the registration token to validate for the order
		 * @throws Exception when the user can't be created
		 * @return WP_User the newly created user
		 * @throws Exception
		 */
		protected function process_post_checkout_registration( $order_id, $token ) {

			$order = wc_get_order( $order_id );

			if ( ! $order instanceof \WC_Order ) {
				throw new Exception( __( 'This order does not exist; it may have been deleted. Please register manually.', 'wc-pcr' ) );
			}

			// check the token in the URL with the order's stored token
			if ( ! $this->token_matches( $order, $token ) ) {
				throw new Exception( __( 'Invalid registration link. Please register manually.', 'wc-pcr' ) );
			}

			$email = $order->get_billing_email();

			/**
			 * Fires before creating a new customer via the Order Received page.
			 *
			 * @since 1.0.0
			 *
			 * @param int $order_id the order ID
			 * @param string $email the billing email for the new customer
			 */
			do_action( 'wc_pcr_before_post_checkout_registration', $order_id, $email );

			// force username + password generation
			add_filter( 'woocommerce_registration_generate_username', array( $this, '__return_yes_string' ) );
			add_filter( 'woocommerce_registration_generate_password', array( $this, '__return_yes_string' ) );

			// Make sure the a link to set the password is sent in the confirmation email even if this option is disabled.
			$woocommerce_registration_generate_password = null;
			if ( 'yes' !== get_option( 'woocommerce_registration_generate_password' ) ) {
				$woocommerce_registration_generate_password = get_option( 'woocommerce_registration_generate_password' );
				update_option( 'woocommerce_registration_generate_password', 'yes' );
			}

			$user_id = wc_create_new_customer( $email );

			// Restore the existing value
			if ( null !== $woocommerce_registration_generate_password ) {
				update_option( 'woocommerce_registration_generate_password', $woocommerce_registration_generate_password );
			}

			if ( is_wp_error( $user_id ) ) {
				throw new Exception( $user_id->get_error_message() );
			}

			// stop forcing
			remove_filter( 'woocommerce_registration_generate_username', array( $this, '__return_yes_string' ) );
			remove_filter( 'woocommerce_registration_generate_password', array( $this, '__return_yes_string' ) );

			wp_set_current_user( $user_id );
			wc_set_customer_auth_cookie( $user_id );

			// multisite: ensure user exists on current site, if not, add them before allowing login
			if ( $user_id && is_multisite() && is_user_logged_in() && ! is_user_member_of_blog() ) {
				add_user_to_blog( get_current_blog_id(), $user_id, 'customer' );
			}

			// link this order to the customer, and retire the one-shot registration token
			$order->set_customer_id( $user_id );
			$order->delete_meta_data( self::TOKEN_META );
			$order->delete_meta_data( self::TOKEN_EXPIRES_META );
			WC_PCR_Pending_Link::forget_order_state( $order );
			$order->update_meta_data( '_wc_pcr_order_linked', true );
			$order->save();

			self::$linked_this_request[] = $order->get_id();
			WC_PCR_Pending_Link::remove( $order->get_id() );

			// security note: don't link previous orders automatically here, as someone *could* checkout with another
			// person's email and use this flow, gaining access to the previous purchase history. For privacy, we
			// don't want to then give them access to all previous orders placed with this initial registration.

			// save the customer data from the order
			$this->add_customer_data( $user_id, $order );

			$user = get_userdata( $user_id );

			/** this hook is documented in wp-includes/user.php */
			do_action( 'wp_login', $user->user_login, $user );

			/**
			 * Fires after creating a new customer via the Order Received page.
			 *
			 * @since 1.0.0
			 *
			 * @param int $order_id the order ID
			 * @param \WP_User $user the newly created user
			 */
			do_action( 'wc_pcr_after_post_checkout_registration', $order_id, $user );

			return $user;
		}


		/**
		 * Save customer's user data from the order.
		 *
		 * We're using usermeta functions here since the customer functions were added in WC 3.0+
		 *
		 * @since 1.0.0
		 *
		 * @param int       $user_id the user ID to which we should add data
		 * @param \WC_Order $order the order from which we're pulling customer data
		 */
		protected function add_customer_data( $user_id, $order ) {

			$address_fields = array(
				'first_name',
				'last_name',
				'company',
				'phone',
				'address_1',
				'address_2',
				'postcode',
				'city',
				'state',
				'country',
			);

			// core WP Fields
			update_user_meta( $user_id, 'first_name', $order->get_billing_first_name() );
			update_user_meta( $user_id, 'last_name', $order->get_billing_last_name() );

			// WC customer fields
			update_user_meta( $user_id, 'paying_customer', 1 );

			foreach ( $address_fields as $field ) {

				if ( is_callable( array( $order, "get_billing_{$field}" ) ) ) {

					update_user_meta( $user_id, "billing_{$field}", $order->{"get_billing_{$field}"}() );
				}

				if ( 'phone' !== $field && is_callable( array( $order, "get_shipping_{$field}" ) ) ) {

					update_user_meta( $user_id, "shipping_{$field}", $order->{"get_shipping_{$field}"}() );
				}
			}
		}


		/**
		 * Force generata a username or password for a new customer
		 *
		 * @since 1.0.0
		 *
		 * @return string Always 'yes'
		 */
		public function __return_yes_string() {
			return 'yes';
		}
		/**
		 * Force generata a username or password for a new customer
		 *
		 * @since 1.0.1
		 *
		 * @return string
		 */
		public function get_default_new_account_msg() {
			return __( 'Ensure checkout is fast and easy next time! Create an account and we\'ll save your address details from this order.', 'wc-pcr' );
		}
		/**
		 * Force generata a username or password for a new customer
		 *
		 * @since 1.0.1
		 *
		 * @return string
		 */
		public function get_default_existing_account_msg() {
			return __( 'Looks like you already have an account! You can link this order to it by clicking here to log in:', 'wc-pcr' );
		}
		/**
		 * Retrieve the markup for registration notice
		 *
		 * @since 1.0.1
		 *
		 * @return string
		 */
		public function get_registration_notice( $atts ) {
			$order = false;
			$atts  = shortcode_atts(
				array(
					'order_id'      => isset( $_GET['order_id'] ) ? $_GET['order_id'] : false,
					'print_notices' => isset( $_GET['print_notices'] ) ? (bool) $_GET['print_notices'] : true,
				),
				$atts,
				'wc_pcr_message'
			);

			$this->maybe_show_registration_notice( $atts['order_id'], $atts['print_notices'] );
		}
	}
}
