## One-Click Post Checkout Registration for WooCommerce

This plugin provides seamless registration option for your guest customers. When they reach the order confirmation page after checkout, they’ll be prompted to create an account with a single click, only if they don't have one already.

The plugin will automatically create a user account from the guest details, generate a password for them, logging them in. Moreover, it will trigger a 'new-account' email, and redirect the user to their dashboard. If the user already exists, the plugin will prompt them to login to link their order, or automatically link it for them based on the settings.

### How to use
- Install the plugin and activate it
- Head to WooCommerce settings from WP admin dashboard
- Open the "Accounts & Privacy" tab
- Find and check the "Post-checkout registration" option
- Save changes

## Changelog

#####  1.0.1 
* Added shortcode `[wc_pcr_message]` to allow displaying prompt on custom checkout page
* Added options to change prompt message text.
* Link order to existing account even after the user moves outside the login page and sign in later within 6 hours
* Clean-up

#####  1.0.2 
* Tested with latest WordPress and WooCommerce versions

#####  1.0.3 
* Prevent error when "When creating an account, send the new user a link to set their password" option is disabled.

#####  1.0.4 
* Allow passing the order id in the shortcode as an attribute (eg; [wc_pcr_message order_id="1"])

#####  1.0.5 
* Fixed: order ID was not being pulled correctly in some cases when passed as a shortcode attribute
* Added the ability to auto link orders to the customer if they have an existing account.
* Add an option to show login form below the post-checkout notice.