<?php
/**
 * PHPUnit bootstrap.
 *
 * The plugin is exercised against Brain Monkey rather than a live WordPress
 * install, so the handful of WordPress/WooCommerce constants and classes the
 * plugin touches are stubbed here. WordPress *functions* are not stubbed: they
 * are declared per-test through Brain Monkey so each test states exactly which
 * calls it expects.
 *
 * @package WC_PCR
 */

define( 'WC_PCR_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'ABSPATH', WC_PCR_PLUGIN_DIR );
define( 'COOKIEPATH', '/' );
define( 'COOKIE_DOMAIN', 'example.test' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'YEAR_IN_SECONDS', 31536000 );

require_once WC_PCR_PLUGIN_DIR . 'vendor/autoload.php';

require_once __DIR__ . '/stubs/class-wc-order.php';
require_once __DIR__ . '/stubs/class-wp-user.php';
require_once __DIR__ . '/stubs/class-wc-data-store.php';
require_once __DIR__ . '/stubs/class-wpdb-stub.php';
require_once __DIR__ . '/stubs/class-wc-session-stub.php';

require_once WC_PCR_PLUGIN_DIR . 'includes/partials/helper-functions.php';
require_once WC_PCR_PLUGIN_DIR . 'includes/class-wc-pcr-pending-link.php';
require_once WC_PCR_PLUGIN_DIR . 'includes/class-wc-post-checkout-registration.php';
