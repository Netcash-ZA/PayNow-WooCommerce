<?php

/**
 * Load whatever is neccessarry for the current system to function
 */
function pn_load_system() {
	require( '../../../wp-load.php' );
	// require( './wp-load.php' );
}

/**
 * Load PayNow functions/files
 */
function pn_load_paynow() {
	return;
}

/**
 * Get the URL we'll redirect users to when coming back from the gateway (for when they choose EFT/Retail)
 */
function pn_get_redirect_url() {
	// $url_for_redirect = pn_full_url($_SERVER);
	// $url_for_redirect = str_ireplace(basename(__FILE__), "index.php", $url_for_redirect);
	$url_for_redirect = get_permalink( get_option('woocommerce_myaccount_page_id') );;
	return $url_for_redirect;
}

function pnlog($value=''){
	error_log($value);
}

/**
 * Check if this is a 'callback' stating the transaction is pending.
 */
function pn_is_pending() {
	return isset($_POST['TransactionAccepted'])
		&& $_POST['TransactionAccepted'] == 'false'
		&& stristr($_POST['Reason'], 'pending');
}

// Load System
pn_load_system();

// Load PayNow
pn_load_paynow();

// Redirect URL for users using EFT/Retail payments to notify them the order's pending
$url_for_redirect = pn_get_redirect_url() . "/my-account/";

pnlog(__FILE__ . " POST: " . print_r($_REQUEST, true) );

if( isset($_POST) && !empty($_POST) && !pn_is_pending() ) {

	// This is the notification coming in!
	// Act as an IPN request and forward request to Credit Card method.
	// Logic is exactly the same

	// do_action('valid-paynow-standard-ipn-request');

	// DO action not working??
	// do_action('woocommerce_api_wc_gateway_paynow');

	$PN = new WC_Gateway_PayNow();
	$PN->check_ipn_response();
	die();

} else {
	// Probably calling the "redirect" URL

	pnlog(__FILE__ . ' Probably calling the "redirect" URL');

	if( $url_for_redirect ) {
		header ( "Location: {$url_for_redirect}" );
	} else {
	    die( "No 'redirect' URL set." );
	}
}

die( PN_ERR_BAD_ACCESS );