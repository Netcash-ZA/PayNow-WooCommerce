<?php
/**
 * Load whatever is neccessarry for the current system to function
 */
function pn_load_system() {
	require( '../../../wp-load.php' );
	// require( './wp-load.php' );
}

// Load System
pn_load_system();

$PN = new WC_Gateway_PayNow();
$paynow   = new Netcash\PayNow\PayNow();
$response = new Netcash\PayNow\Response( $_POST );

$PN->log('==== NOTIFY START =====');
$PN->log("Notify Callback called", print_r($_REQUEST, true) );

$order_id = $response->getOrderID();
$order    = new WC_Order( $order_id );

$PN->is_notify = true;

$success_or_message = $PN->check_ipn_response();
// This is a IPN request. No need to redirect.
if (true !== $success_or_message) {
	$PN->log('Notify failed. Reason: ' . $success_or_message);
} else {
	$PN->log('Notify success');
	$PN->successful_request($response->getData() );
}
$PN->log('==== NOTIFY END =====');

// Handled. Send OK
header("HTTP/1.1 200 OK");
