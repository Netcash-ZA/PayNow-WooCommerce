<?php
/*
	Plugin Name: WooCommerce Pay Now Gateway
	Plugin URI: http://woocommerce.com/
	Description: A payment gateway for South African payment system, Pay Now.
	Version: 1.0
	Author: Gateway Modules
	Author URI: http://gatewaymodules.com/
	Requires at least: 3.5
	Tested up to: 3.8
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '557bf07293ad916f20c207c6c9cd15ff', '18596' );

load_plugin_textdomain( 'wc_paynow', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

add_action( 'plugins_loaded', 'woocommerce_paynow_init', 0 );

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function woocommerce_paynow_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	require_once( plugin_basename( 'classes/paynow.class.php' ) );

	add_filter('woocommerce_payment_gateways', 'woocommerce_paynow_add_gateway' );

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