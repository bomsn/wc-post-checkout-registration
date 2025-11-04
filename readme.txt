=== One-Click Post Checkout Registration for WooCommerce ===
Contributors: alikhallad
Tags: woocommerce registration, post-checkout registration, woocommerce abandonment, woocommerce marketing, gutenberg block
Requires at least: 6.5
Tested up to: 6.7
Stable tag: 2.0.0
Requires PHP: 7.4
WC requires at least: 8.2
WC tested up to: 9.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Reduce abandonment and allows guest users to register after checkout with one-click.

== Description ==

This plugin provides seamless registration option for your guest customers. When they reach the order confirmation page after checkout, they'll be prompted to create an account with a single click, only if they don't have one already.

The plugin will automatically create a user account from the guest details, generate a password for them, logging them in. Moreover, it will trigger a 'new-account' email, and redirect the user to their dashboard.

The registration prompt appears automatically on the default WooCommerce order confirmation page. For custom thank you pages, use the Gutenberg block or the `[wc_pcr_message]` shortcode to place the prompt manually.

= How to use =

- Install the plugin and activate it
- Head to WooCommerce settings from WP admin dashboard
- Open the "Accounts & Privacy" tab
- Find and check the "Post-checkout registration" option
- Save changes

= Manual Placement =

For custom thank you pages, you can manually place the registration prompt:

**Using the Block:**
1. Edit your page in the block editor
2. Search for "Post Checkout Registration" block
3. Configure display settings and publish

**Using the Shortcode:**
Add `[wc_pcr_message]` where you want the prompt to appear. Pass the order ID via URL parameter (eg: `/?order_id=1`) or shortcode attribute (eg: `[wc_pcr_message order_id="1"]`).

== Screenshots ==

1. Settings
2. Order Received Page
3. Account Page

== Frequently Asked Questions ==

= Can I change the prompt message? =

Yes, you can. You have the option to set your own message in WC account settings.

= What if I'm using a custom thank you page? =

The plugin automatically displays on the default WooCommerce order confirmation page. For custom pages, use the "Post Checkout Registration" block in the block editor, or add the `[wc_pcr_message]` shortcode. Make sure to pass the order ID via URL parameter (eg: `/?order_id=1`) or shortcode attribute (eg: `[wc_pcr_message order_id="1"]`).

== Changelog ==

= 2.0.0 =
* Added Gutenberg block for manual placement on custom pages
* Updated minimum requirements: WordPress 6.5+, WooCommerce 8.2+, PHP 7.4+

= 1.0.0 =
* First release

= 1.0.1 =
* Added shortcode `[wc_pcr_message]` to allow displaying prompt on custom checkout page
* Added options to change prompt message text.
* Link order to existing account even after the user moves outside the login page and sign in later within 6 hours
* Clean-up

= 1.0.2 =
* Tested with latest WordPress and WooCommerce versions

= 1.0.3 =
* Prevent error when "When creating an account, send the new user a link to set their password" option is disabled.

= 1.0.4 =
* Allow passing the order id in the shortcode as an attribute (eg; [wc_pcr_message order_id="1"])

= 1.0.5 =
* Fixed: order ID was not being pulled correctly in some cases when passed as a shortcode attribute.

= 1.0.6 =
* Added the ability to auto link orders to the customer if they have an existing account.
* Add an option to show login form below the post-checkout notice.

= 1.0.7 =
* Bug fix ( issue with [wc_pcr_message] shortcode on custom thank you pages )

= 1.0.8 =
* Bug fix ( fix woocommerce notices for the login form )