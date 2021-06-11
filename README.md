## One-Click Post Checkout Registration for WooCommerce

This plugin provides seamless registration option for your guest customers. When they reach the order confirmation page after checkout, they’ll be prompted to create an account with a single click, only if they don't have one already.

The plugin will automatically create a user account from the guest details, generate a password for them, logging them in. Moreover, it will trigger a 'new-account' email, and redirect the user to their dashboard.

### How to use
- Install the plugin and activate it
- Head to WooCommerce settings from WP admin dashboard
- Open the "Accounts & Privacy" tab
- Find and check the "Post-checkout registration" option
- Save changes

## Changelog

##### 1.0.0 
* First release
##### 1.0.1
* Added shortcode `[wc_pcr_message]` to allow displaying prompt on custom checkout page
* Added options to change prompt message text.
* Link order to existing account even after the user moves outside the login page and sign in later within 6 hours
* Clean-up