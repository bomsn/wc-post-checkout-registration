=== One-Click Post Checkout Registration for WooCommerce ===
Contributors: alikhallad
Tags: woocommerce registration, post-checkout registration, woocommerce abandonment, woocommerce marketing
Requires at least: 5.6
Tested up to: 6.4.2
Stable tag: 1.0.8
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Reduce abandonment and allows guest users to register after checkout with one-click.

== Description ==

This plugin provides seamless registration option for your guest customers. When they reach the order confirmation page after checkout, they’ll be prompted to create an account with a single click, only if they don't have one already.

The plugin will automatically create a user account from the guest details, generate a password for them, logging them in. Moreover, it will trigger a 'new-account' email, and redirect the user to their dashboard.

= How to use =

- Install the plugin and activate it
- Head to WooCommerce settings from WP admin dashboard
- Open the "Accounts & Privacy" tab
- Find and check the "Post-checkout registration" option
- Save changes

= Development =

If you'd like to view the source code and contribute to this plugin, check out the plugin's [github repo](https://github.com/bomsn/wc-post-checkout-registration).

== Screenshots ==

1. Settings
2. Order Received Page
3. Account Page

== Frequently Asked Questions ==

= Can I change the prompt message? =

Yes, you can. You have the option to set your own message in WC account settings.

= What if I'm using a custom thank you page? =

That's easy, just use the `[wc_pcr_message]` shortcode where you want the prompt to appear. Just make sure to pass the order id in the URL (eg: /?order_id=1), or as a shortcode attribute (eg: [wc_pcr_message order_id="1"])

== Changelog ==

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