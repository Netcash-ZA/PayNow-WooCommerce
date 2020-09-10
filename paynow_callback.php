<?php
/**
 * Receives the Pay Now callback response.
 *
 * @category   Payment Gateways
 * @package    WooCommerce
 * @subpackage WC_Gateway_PayNow
 * @author     Netcash
 */

/**
 * Load whatever is necessary for the current system to function
 */
function pn_load_system() {
	require( '../../../wp-load.php' );
}

/**
 * Get the URL we'll redirect users to when coming back from the gateway (for when they choose EFT/Retail)
 */
function pn_get_redirect_url() {
	$url_for_redirect = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );
	return $url_for_redirect;
}

/**
 * Log a string to the error log
 *
 * @param string $value The value to log.
 */
function pnlog( $value = '' ) {
	error_log( "[PayNow] {$value}" );
}

// Load System.
pn_load_system();

// Redirect URL for users using EFT/Retail payments to notify them the order's pending.
$url_for_redirect = pn_get_redirect_url() . '/my-account/';

pnlog( __FILE__ . ' POST: ' . print_r( $_REQUEST, true ) );

$response    = new Netcash\PayNowSDK\Response( $_POST );
$was_offline = $response->wasOfflineTransaction();
pnlog( __FILE__ . 'IS OFFLINE? ' . ( $was_offline ? 'Yes' : 'No' ) );

if ( isset( $_POST ) && ! empty( $_POST ) && ! $was_offline ) {

	// This is the notification coming in!
	// Act as an IPN request and forward request to Credit Card method.
	// Logic is exactly the same.

	$paynow = new WC_Gateway_PayNow();
	$paynow->check_ipn_response();
	die();

} else {
	// Probably calling the "redirect" URL.
	pnlog( __FILE__ . ' Probably calling the "redirect" URL' );

	if ( $url_for_redirect ) {
		header( "Location: {$url_for_redirect}" );
	} else {
		die( "No 'redirect' URL set." );
	}
}

die( 'Invalid request. Cannot call file directly.' );
