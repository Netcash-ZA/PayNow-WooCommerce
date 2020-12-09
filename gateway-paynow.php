<?php
/**
 * Setup the plugin in WordPress/WooCommerce
 *
 * @category   Payment Gateways
 * @package    WC_Gateway_PayNow
 * @since      1.0.0
 */

/*
	Plugin Name: Netcash Pay Now Gateway for WooCommerce
	Plugin URI: https://github.com/Netcash-ZA/PayNow-WooCommerce
	Description: A payment gateway for South African payment system, Netcash Pay Now.
	Version: 4.0.0
	Author: Netcash
	Author URI: http://www.netcash.co.za/
	Requires at least: 3.5
	Tested up to: 5.5.1
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	/**
	 * Require WooCommerce functions
	 */
	require_once( 'includes/woocommerce/woo-functions.php' );
}

/**
 * Plugin updates
 */
load_plugin_textdomain( 'wc_paynow', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

add_action( 'plugins_loaded', 'woocommerce_paynow_init', 0 );

// Show warning if subscription period or cycle is not supported.
add_action(
	'admin_init',
	array( 'WC_Gateway_PayNow', 'admin_show_unsupported_message' )
);


/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function woocommerce_paynow_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	/**
	 * Require Pay Now Gateway
	 */
	require_once( plugin_basename( 'includes/class-wc-gateway-paynow.php' ) );

	/**
	 * Include Pay Now SDK autoloader
	 */
	require_once( plugin_basename( 'vendor/netcash/paynow-php/AutoLoader.php' ) );
	// Autoload the SDK.
	\Netcash\PayNow\AutoLoader::register();

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_paynow_add_gateway' );

}

/**
 * Add the gateway to WooCommerce
 *
 * @param array $methods The available payment methods.
 * @since 1.0.0
 */
function woocommerce_paynow_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_PayNow';
	return $methods;
}
