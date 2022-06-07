<?php
if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('Run_WC_PCR')) {
	class Run_WC_PCR
	{

		public $version = '1.0.1';
		public function __construct()
		{
			$this->load_dependencies();
			$this->define_hooks();
		}

		/**
		 * Load all dependencies here.
		 *
		 * @since    1.0.0
		 * @access   private
		 */
		private function load_dependencies()
		{
			require plugin_dir_path(__FILE__) . 'partials/helper-functions.php';
		}
		/**
		 * Register all of the hooks related to the admin and public functionality
		 * of the plugin.
		 *
		 * @since    1.0.0
		 * @access   private
		 */
		private function define_hooks()
		{

			# Load translations
			add_action('plugins_loaded', array($this, 'load_textdomain'));

			# General hooks
			add_filter('woocommerce_account_settings', array($this, 'add_pcr_enable_fields'));

			if (get_option('woocommerce_enable_post_checkout_registration', false)) {

				// maybe render the prompt on the "thank you" page
				// we don't use the woocommerce_thankyou action as we can't consistently add a notice for immediate display
				add_filter('woocommerce_thankyou_order_received_text', array($this, 'maybe_show_registration_notice'), 10, 2);
			}

			// if the registration link is clicked, validate and register the customer
			add_action('template_redirect', array($this, 'maybe_register_new_customer'));
			// Store order ID and token so it's linked to the user account after login
			add_action('template_redirect', array($this, 'maybe_store_order_data'));

			// add login form fields to indicate when we should link previous orders
			add_action('woocommerce_login_form', array($this, 'add_custom_tracking_fields'));

			// if the link orders link is clicked, potentially link previous orders
			add_action('wp_login', array($this, 'link_previous_orders'), 10, 2);

			// Add shortcode ( to use for custom thank you pages )
			add_shortcode('wc_pcr_message', array($this, 'get_registration_notice'));
		}

		/**
		 * Enqueue style and javascript files
		 * of the plugin.
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function enqueue_styles_public($hook)
		{
			wp_enqueue_style('wc-pcr-css', plugin_dir_url(__FILE__) . 'assets/css/wc-post-checkout-registration-public.css', array(), $this->version, 'all');
		}
		/**
		 * Loads the plugin's translated strings.
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function load_textdomain()
		{

			# Get translations path relative to the plugins directory, which in our case is `ali-khallad/languages`
			$plugin_rel_path = basename(dirname(__DIR__)) . '/languages';
			# Load the translated strings
			load_plugin_textdomain('wc-pcr', false, $plugin_rel_path);
		}
		/**
		 * Add an option to enable post-checkout registration to the account settings.
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function add_pcr_enable_fields($settings)
		{

			$updated_settings = array();

			foreach ($settings as $section) {


				$updated_settings[] = $section;

				// after the account registration options section
				if (
					isset($section['id']) && 'account_registration_options' == $section['id'] &&
					isset($section['type']) && 'sectionend' == $section['type']
				) {

					$updated_settings[] = array(
						'title' => __('Post-checkout registration', 'wc-pcr'),
						'type'  => 'title',
						'id'    => 'wc_pcr_options',
					);

					$updated_settings[] = array(
						'title'         => __('Enable', 'wc-pcr'),
						'desc'      	=> __('Enable one-click customer registration on the "Order Received" page.', 'wc-pcr'),
						'desc_tip'      => __('Adds an option to the order-recieved page to allow guest users to register with a single click using the data from their order.', 'wc-pcr'),
						'id'            => 'woocommerce_enable_post_checkout_registration',
						'default'       => 'no',
						'type'          => 'checkbox',
						'autoload'      => true,
					);

					$updated_settings[] = array(
						'title'    => __('New account message', 'wc-pcr'),
						'desc_tip' => __('Define the message that should appear when the user doesn`t have an account.', 'wc-pcr'),
						'id'       => 'wc_pcr_new_account_msg',
						'type'     => 'textarea',
						'css'      => 'min-width: 50%; height: 75px;',
						'default'  => $this->get_default_new_account_msg(),
					);
					$updated_settings[] = array(
						'title'    => __('Existing account message', 'wc-pcr'),
						'desc_tip' => __('Define the message that should appear when the user have an account already.', 'wc-pcr'),
						'id'       => 'wc_pcr_existing_account_msg',
						'type'     => 'textarea',
						'css'      => 'min-width: 50%; height: 75px;',
						'default'  => $this->get_default_existing_account_msg(),
					);

					$updated_settings[] = array(
						'sectionend'    => 'wc_pcr_options',
						'type'          => 'sectionend',
					);
				}
			}

			return $updated_settings;
		}

		/**
		 * Checks the WooCommerce thankyou page to render registration or login prompt immediately.
		 *
		 * @since 1.0.0
		 *
		 * @param string $text the thankyou page message text
		 * @param \WC_Order $order the placed order object
		 * @return string the updated text
		 */
		public function maybe_show_registration_notice($text, $order)
		{

			// sanity check & send away!
			if ($order instanceof WC_Order) {

				$existing_user = get_user_by('email', $order->get_billing_email());

				if (!is_user_logged_in()) {

					// do not use a nonce, favoring order-specific validation
					// this way, a user can't just get a valid nonce, then change the order ID in the registration link
					$token = wc_pcr_generate_random_token(32);
					$order->update_meta_data('_wc_pcr_post_checkout_registration', $token);
					$order->save_meta_data();

					$message = $existing_user ? $this->render_link_order_prompt($order, $token) : $this->render_registration_prompt($order, $token);
					$text    = $message . $text;
				}
			}

			return $text;
		}


		/**
		 * Renders a prompt to log in to link this existing order.
		 *
		 * @since 1.0.0
		 *
		 * @param \WC_Order $order the currently placed order
		 * @param string $token the login token to prompt linking old orders
		 * @return string the login prompt message
		 */
		protected function render_link_order_prompt($order, $token)
		{

			$url = add_query_arg(
				[
					'link_order_id' => $order->get_id(),
					'login_token'   => $token,
				],
				trailingslashit(wc_get_page_permalink('myaccount'))
			);

			$message  = get_option('wc_pcr_existing_account_msg', $this->get_default_existing_account_msg());
			$message .= ' <a class="button" href="' . esc_url($url) . '">' . esc_html__('Log in', 'wc-pcr') . '</a>';

			return "<div class='woocommerce-info'>{$message}</div>";
		}

		/**
		 * Renders the registration prompt on the thankyou page
		 *
		 * @since 1.0.0
		 *
		 * @param \WC_Order $order the order object
		 * @param string $token the registration token for the order
		 * @return string the message to render
		 */
		protected function render_registration_prompt($order, $token)
		{

			$url = add_query_arg(
				[
					'registration_order_id' => $order->get_id(),
					'registration_token'    => $token,
				],
				trailingslashit(wc_get_page_permalink('myaccount'))
			);

			$message  = get_option('wc_pcr_new_account_msg', $this->get_default_new_account_msg());
			$message .= ' <a class="button" href="' . esc_url($url) . '">' . esc_html__('Create Account', 'wc-pcr') . '</a>';

			return "<div class='woocommerce-info'>{$message}</div>";
		}

		/**
		 * Outputs hidden fields to POST the login token and associated order.
		 *
		 * @since 1.0.0
		 */
		public function add_custom_tracking_fields()
		{

			if (isset($_GET['link_order_id'], $_GET['login_token']) || isset($_COOKIE['link-order-data'])) {

				if (isset($_GET['link_order_id'], $_GET['login_token'])) {
					$order_data = array(
						'link_order_id' => $_GET['link_order_id'],
						'login_token' => $_GET['login_token'],
					);
				} else {
					$data = explode('|', $_COOKIE['link-order-data']);
					$order_data = array(
						'link_order_id' => $data[0],
						'login_token' => $data[1],
					);
				}

				$order_id = (int)$order_data['link_order_id'];
				$token    = wc_clean($order_data['login_token']);

				ob_start();

?>
				<p class="form-row">
					<input class="woocommerce-Input input-hidden" type="hidden" name="wc_pcr_link_order_id" id="wc_pcr_link_order_id" value="<?php echo esc_attr($order_id); ?>" />
					<input class="woocommerce-Input input-hidden" type="hidden" name="wc_pcr_login_token" id="wc_pcr_login_token" value="<?php echo esc_attr($token); ?>" />
				</p>
<?php

				echo ob_get_clean();
			}
		}


		/**
		 * Links previous orders upon customer login
		 *
		 * @since 1.0.0
		 *
		 * @param string $username the username, unused
		 * @param \WP_User $user the logged in user
		 */
		public function link_previous_orders($username, $user)
		{

			// ensure all data is set
			if (!isset($_POST['wc_pcr_link_order_id'], $_POST['wc_pcr_login_token'])) {
				return;
			}

			// Unset order data cookie if it exists
			if (isset($_COOKIE['link-order-data'])) {
				unset($_COOKIE['link-order-data']);
				setcookie('link-order-data', null, -1, COOKIEPATH, COOKIE_DOMAIN);
			}

			$order_id = (int) $_POST['wc_pcr_link_order_id'];
			$token    = wc_clean($_POST['wc_pcr_login_token']);
			$order    = wc_get_order($order_id);

			if (!$order instanceof WC_Order) {
				wc_add_notice(__('Error linking your previous order.', 'wc-pcr'), 'error');
				return;
			}

			$stored_token = $order->get_meta('_wc_pcr_post_checkout_registration');

			// check the token in the URL with the order's stored token
			if (!$stored_token || $token !== $stored_token) {
				wc_add_notice(__('Error linking your previous order.', 'wc-pcr'), 'error');
				return;
			}

			// We're clear! Link this order and previous ones to the account
			wc_update_new_customer_past_orders($user->ID);

			/* translators: Placeholders: %s - order number */
			wc_add_notice(sprintf(__('Order #%s has been linked to your account!', 'wc-pcr'), $order->get_order_number()), 'success');
		}

		/**
		 * Registers a new customer if "create" link is valid.
		 *
		 * @since 1.0.0
		 */
		public function maybe_register_new_customer()
		{
			if (!is_account_page() || !isset($_REQUEST['registration_order_id'])) {
				return;
			}

			// now we have the order ID param, but not a token, boot this faker!
			if (!isset($_REQUEST['registration_token'])) {
				wc_add_notice(__('Whoops, looks like this registration link is not valid.', 'wc-pcr'), 'error');
				return;
			}

			$order_id = (int) $_REQUEST['registration_order_id'];
			$token    = wc_clean($_REQUEST['registration_token']);

			try {

				$user = $this->process_post_checkout_registration($order_id, $token);

				/* translators: Placeholder: %1$s - first name, %2$s - <a> tag, %3$s - </a> tag */
				wc_add_notice(sprintf(
					__('Welcome, %1$s! Your %2$saccount information%3$s has been saved.', 'wc-pcr'),
					$user->first_name,
					'<strong><a href="' . wc_get_endpoint_url('edit-address') . '">',
					'</a></strong>'
				), 'success');

				return;
			} catch (Exception $e) {

				wc_add_notice($e->getMessage(), 'error');
				return;
			}
		}
		/**
		 * Store order id and login token as a cookie to be able to login page without URL query args
		 *
		 * @since 1.0.1
		 */
		public function maybe_store_order_data()
		{
			if (!isset($_GET['link_order_id']) || !isset($_GET['login_token'])) {
				return;
			}

			setcookie('link-order-data', $_GET['link_order_id'] . '|' . $_GET['login_token'], time() + (3600 * 6), COOKIEPATH, COOKIE_DOMAIN);
		}


		/**
		 * Validate the create account token for the order, and create a customer if valid.
		 *
		 * @since 1.0.0
		 *
		 * @param int $order_id ID of the order ID we should pull customer info for
		 * @param string $token the registration token to validate for the order
		 * @throws Exception when the user can't be created
		 * @return WP_User the newly created user
		 * @throws Exception
		 */
		protected function process_post_checkout_registration($order_id, $token)
		{

			$order = wc_get_order($order_id);

			if (!$order instanceof \WC_Order) {
				throw new Exception(__('This order does not exist; it may have been deleted. Please register manually.', 'wc-pcr'));
			}

			$stored_token = $order->get_meta('_wc_pcr_post_checkout_registration');

			// check the token in the URL with the order's stored token
			if (!$stored_token || $token !== $stored_token) {
				throw new Exception(__('Invalid registration link. Please register manually.', 'wc-pcr'));
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
			do_action('wc_pcr_before_post_checkout_registration', $order_id, $email);

			// force username + password generation
			add_filter('woocommerce_registration_generate_username', [$this, '__return_yes_string']);
			add_filter('woocommerce_registration_generate_password', [$this, '__return_yes_string']);

			// Make sure the a link to set the password is sent in the confirmation email even if this option is disabled.
			$woocommerce_registration_generate_password = null;
			if ('yes' !== get_option('woocommerce_registration_generate_password')) {
				$woocommerce_registration_generate_password = get_option('woocommerce_registration_generate_password');
				update_option('woocommerce_registration_generate_password', 'yes');
			}

			$user_id = wc_create_new_customer($email);

			// Restore the existing value
			if (null !== $woocommerce_registration_generate_password) {
				update_option('woocommerce_registration_generate_password', $woocommerce_registration_generate_password);
			}

			if (is_wp_error($user_id)) {
				throw new Exception($user_id->get_error_message());
			}

			// stop forcing
			remove_filter('woocommerce_registration_generate_username', [$this, '__return_yes_string']);
			remove_filter('woocommerce_registration_generate_password', [$this, '__return_yes_string']);

			wp_set_current_user($user_id);
			wc_set_customer_auth_cookie($user_id);

			// multisite: ensure user exists on current site, if not, add them before allowing login
			if ($user_id && is_multisite() && is_user_logged_in() && !is_user_member_of_blog()) {
				add_user_to_blog(get_current_blog_id(), $user_id, 'customer');
			}

			// link this order to the customer
			$order->set_customer_id($user_id);
			$order->save();

			// security note: don't link previous orders automatically here, as someone *could* checkout with another
			// person's email and use this flow, gaining access to the previous purchase history. For privacy, we
			// don't want to then give them access to all previous orders placed with this initial registration.

			// save the customer data from the order
			$this->add_customer_data($user_id, $order);


			$user = get_userdata($user_id);

			/** this hook is documented in wp-includes/user.php */
			do_action('wp_login', $user->user_login, $user);

			/**
			 * Fires after creating a new customer via the Order Received page.
			 *
			 * @since 1.0.0
			 *
			 * @param int $order_id the order ID
			 * @param \WP_User $user the newly created user
			 */
			do_action('wc_pcr_after_post_checkout_registration', $order_id, $user);

			return $user;
		}


		/**
		 * Save customer's user data from the order.
		 *
		 * We're using usermeta functions here since the customer functions were added in WC 3.0+
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id the user ID to which we should add data
		 * @param \WC_Order $order the order from which we're pulling customer data
		 */
		protected function add_customer_data($user_id, $order)
		{

			$address_fields = [
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
			];

			// core WP Fields
			update_user_meta($user_id, 'first_name', $order->get_billing_first_name());
			update_user_meta($user_id, 'last_name', $order->get_billing_last_name());

			// WC customer fields
			update_user_meta($user_id, 'paying_customer', 1);

			foreach ($address_fields as $field) {

				if (is_callable([$order, "get_billing_{$field}"])) {

					update_user_meta($user_id, "billing_{$field}", $order->{"get_billing_{$field}"}());
				}

				if ('phone' !== $field && is_callable([$order, "get_shipping_{$field}"])) {

					update_user_meta($user_id, "shipping_{$field}", $order->{"get_shipping_{$field}"}());
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
		public function __return_yes_string()
		{
			return 'yes';
		}
		/**
		 * Force generata a username or password for a new customer
		 *
		 * @since 1.0.1
		 *
		 * @return string
		 */
		public function get_default_new_account_msg()
		{
			return __('Ensure checkout is fast and easy next time! Create an account and we\'ll save your address details from this order.', 'wc-pcr');
		}
		/**
		 * Force generata a username or password for a new customer
		 *
		 * @since 1.0.1
		 *
		 * @return string
		 */
		public function get_default_existing_account_msg()
		{
			return __('Looks like you already have an account! You can link this order to it by clicking here to log in:', 'wc-pcr');
		}
		/**
		 * Retrieve the markup for registration notice
		 *
		 * @since 1.0.1
		 *
		 * @return string
		 */
		public function get_registration_notice($atts)
		{
			$order = isset($_GET['order_id']) ? wc_get_order($_GET['order_id']) : false;
			return $this->maybe_show_registration_notice('', $order);
		}
	}
}
