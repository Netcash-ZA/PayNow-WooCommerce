<?php
/**
 * Setup the plugin in WordPress/WooCommerce
 *
 * @package    Netcash_WooCommerce_Gateway_PayNow
 * @since      1.0.0
 */

/*
	Plugin Name: Netcash Pay Now - Payment Gateway for WooCommerce
	Plugin URI: https://github.com/Netcash-ZA/PayNow-WooCommerce
	Description: A payment gateway for South African payment system, Netcash Pay Now.
	License: GPL v3
	Version: 4.0.26
	Author: Netcash
	Author URI: http://www.netcash.co.za/
	Requires at least: 3.5
	Tested up to: 6.5

*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Plugin updates
 */
load_plugin_textdomain( 'wc_paynow', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

add_action( 'plugins_loaded', 'netcash_paynow_woocommerce_init', 0 );

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function netcash_paynow_woocommerce_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	/**
	 * Require Pay Now Gateway
	 */
	require_once plugin_basename( 'includes/class-wc-gateway-paynow.php' );

	require_once plugin_basename( 'includes/woocommerce/woo-functions.php' );

	/**
	 * Include Pay Now SDK autoloader
	 */
	require_once plugin_basename( 'vendor/netcash/paynow-php/AutoLoader.php' );
	// Autoload the SDK.
	\Netcash\PayNow\AutoLoader::register();

	add_filter( 'woocommerce_payment_gateways', 'netcash_paynow_woocommerce_add_gateway' );

	// Show warning if subscription period or cycle is not supported.
	add_action(
		'admin_init',
		array( 'Netcash_WooCommerce_Gateway_PayNow', 'admin_show_unsupported_message' )
	);

	// Show payment errors after redirect.
	add_action(
		'init',
		function () {
			$notice = isset( $_GET['pnotice'] ) ? esc_url_raw( wp_unslash( $_GET['pnotice'] ) ) : null;
			if ( $notice ) {
				$type = isset( $_GET['ptype'] ) ? esc_url_raw( wp_unslash( $_GET['ptype'] ) ) : 'notice';
				wc_add_notice( urldecode( $notice ), $type );
			}
		}
	);
}

/**
 * Add the gateway to WooCommerce
 *
 * @param array $methods The available payment methods.
 * @since 1.0.0
 */
function netcash_paynow_woocommerce_add_gateway( $methods ) {
	$methods[] = 'Netcash_WooCommerce_Gateway_PayNow';
	return $methods;
}


// WooCommerce Checkout Blocks. See more info on integration.
// - https://developer.woocommerce.com/2023/11/06/faq-extending-cart-and-checkout-blocks/
// - https://github.com/woocommerce/woocommerce/blob/f93a05c467188081f482c3b843bdab70001de1f2/docs/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration.md
add_action(
	'before_woocommerce_init',
	function () {
		// Not supported currently.
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
		}
	}
);
