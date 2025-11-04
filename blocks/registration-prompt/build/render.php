<?php
/**
 * Dynamic rendering for registration prompt block
 *
 * @package WC_PCR
 * @since 2.0.0
 *
 * @var array    $attributes Block attributes
 * @var string   $content    Block content
 * @var WP_Block $block      Block instance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get block attributes
$display_mode     = $attributes['displayMode'] ?? 'auto';
$show_quick_login = $attributes['showQuickLogin'] ?? true;

// Get order ID from context (WooCommerce thank you page)
$order_id = get_query_var( 'order-received', 0 );

if ( ! $order_id && isset( $_GET['order_id'] ) ) {
	$order_id = absint( $_GET['order_id'] );
}

// Display logic based on mode
$should_display = false;

switch ( $display_mode ) {
	case 'always':
		$should_display = true;
		break;
	case 'never':
		$should_display = false;
		break;
	case 'auto':
	default:
		$should_display = $order_id && ! is_user_logged_in();
		break;
}

if ( ! $should_display ) {
	return '';
}

// Get existing plugin instance to reuse logic
global $Run_WC_PCR;
if ( ! $Run_WC_PCR || ! method_exists( $Run_WC_PCR, 'maybe_show_registration_notice' ) ) {
	return '';
}

// Check if automatic display is enabled and we're on WooCommerce thank you page
$auto_display_enabled = get_option( 'woocommerce_enable_post_checkout_registration', 'no' ) === 'yes';
$is_thank_you_page    = is_wc_endpoint_url( 'order-received' ) || ( isset( $_GET['order_id'] ) && $order_id );

// If automatic display is enabled and we're on thank you page, don't display block
// (the automatic hook will handle it to avoid duplication)
if ( $auto_display_enabled && $is_thank_you_page && $display_mode === 'auto' ) {
	return '';
}

// Get wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                   => 'wc-pcr-registration-prompt-block',
		'data-order-id'           => $order_id,
		'data-show-quick-login'   => $show_quick_login ? 'true' : 'false',
	)
);

// Buffer output
ob_start();
?>
<div <?php echo $wrapper_attributes; ?>>
	<?php
	// Reuse existing shortcode logic
	$Run_WC_PCR->maybe_show_registration_notice( $order_id, $show_quick_login );
	?>
</div>
<?php
return ob_get_clean();
