<?php
/*
	Plugin Name: WooCommerce Pay Now Gateway
	Plugin URI: https://github.com/Netcash-ZA/PayNow-WooCommerce
	Description: A payment gateway for South African payment system, Netcash Pay Now.
	Version: 3.0.0
	Author: Netcash
	Author URI: http://www.netcash.co.za/
	Requires at least: 3.5
	Tested up to: 3.8
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( 'includes/woocommerce/woo-functions.php' );
}

/**
 * Plugin updates
 */
load_plugin_textdomain( 'wc_paynow', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

add_action( 'plugins_loaded', 'woocommerce_paynow_init', 0 );

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function woocommerce_paynow_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once( plugin_basename( 'includes/paynow.class.php' ) );

	// Include the SDK's autoloader
	require_once( plugin_basename( 'vendor/netcash/paynow-php-sdk/AutoLoader.php' ) );
	// Autoload the SDK
	\Netcash\PayNowSDK\AutoLoader::register();

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_paynow_add_gateway' );

} // End woocommerce_paynow_init()

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 */
function woocommerce_paynow_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_PayNow';
	return $methods;
} // End woocommerce_paynow_add_gateway()
