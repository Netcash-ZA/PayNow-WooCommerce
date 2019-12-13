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
	error_log("[PayNow] {$value}");
}

/**
 * Check if this is a 'callback' stating the transaction is pending.
 */
function pn_is_pending() {

	pnlog('Checking if pending: ' . print_r(array(
		"isset" => (bool) isset($_POST['TransactionAccepted']),
		"isFalse" => (bool) $_POST['TransactionAccepted'] === 'false',
		"pendingSet" => (bool) stristr($_POST['Reason'], 'pending') !== false,
		"pending" => stristr($_POST['Reason'], 'pending'),
		"reason" => $_POST['Reason'],
	), true));

	return isset($_POST['TransactionAccepted'])
		&& $_POST['TransactionAccepted'] === 'false'
		&& stristr($_POST['Reason'], 'pending') !== false;
}
/**
 * Check if this is a 'offline' payment like EFT or retail
 */
function pn_is_offline() {

	/*
	Returns 2 for EFT
	Returns 3 for Retail
	*/
	$offline_methods = [2, 3];

	// If !$accepted, means it's the callback.
	// If $accepted, and in array, means it's the actual called response
	$accepted = isset($_POST['TransactionAccepted']) ? $_POST['TransactionAccepted'] == 'true' : false;
	$method = isset($_POST['Method']) ? (int) $_POST['Method'] : null;
	pnlog('Checking if offline: ' . print_r(array(
		"isset" => (bool) isset($_POST['Method']),
		"Method" => (int) $_POST['Method'],
	), true));

	return !$accepted && in_array($method, $offline_methods);
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
	error_log("[PayNow] {$value}");
}

// Load System
pn_load_system();

// Load PayNow
pn_load_paynow();

// Redirect URL for users using EFT/Retail payments to notify them the order's pending
$url_for_redirect = pn_get_redirect_url() . "/my-account/";

pnlog(__FILE__ . " POST: " . print_r($_REQUEST, true) );

$pending = pn_is_pending();
$offline = pn_is_offline();
pnlog(__FILE__ . "IS PENDING? " . ($pending ? 'Yes' : 'No') );
pnlog(__FILE__ . "IS OFFLINE? " . ($offline ? 'Yes' : 'No') );

if( isset($_POST) && !empty($_POST) && !$pending && !$offline ) {

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
