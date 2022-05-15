=== One-Click Post Checkout Registration for WooCommerce ===
Contributors: alikhallad
Tags: woocommerce registration, post-checkout registration, woocommerce abandonment, woocommerce marketing
Requires at least: 5.6
Tested up to: 5.9.3
Stable tag: 1.0.2
Requires PHP: 5.2.4
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

That's easy, just use the `[wc_pcr_message]` shortcode where you want the prompt to appear.

== Changelog ==

= 1.0.0 =
* First release

= 1.0.1 =
* Added shortcode `[wc_pcr_message]` to allow displaying prompt on custom checkout page
* Added options to change prompt message text.
* Link order to existing account even after the user moves outside the login page and sign in later within 6 hours
* Clean-up