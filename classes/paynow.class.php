<?php
/**
 * Netcash Pay Now Payment Gateway
 *
 * Provides a Netcash Pay Now Payment Gateway.
 *
 * @class 		woocommerce_paynow
 * @package		WooCommerce
 * @category	Payment Gateways
 * @author		Gateway Modules
 *
 * Note:
 *  All references to mysql_real_escape_string replaced with $this->escape()
 *
 */
class WC_Gateway_PayNow extends WC_Payment_Gateway {
	public $version = '3.0.0';

	private $SOAP_INSTALLED = false;

	public function __construct() {
		global $woocommerce;

		if(class_exists('SoapClient')) {
			// We can continue, SOAP is installed
			$this->SOAP_INSTALLED = true;
		}

		// $this->notify_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_PayNow', home_url( '/' ) ) );

		$this->id = 'paynow';
		$this->method_title = __ ( 'Pay Now', 'woothemes' );
		$this->method_description = __ ( 'A payment gateway for South African payment system, Netcash Pay Now.', 'woothemes' );
		$this->icon = $this->plugin_url () . '/assets/images/netcash.png';
		$this->has_fields = true;
		$this->debug_email = get_option ( 'admin_email' );

		// Setup available countries.
		$this->available_countries = array (
				'ZA'
		);

		// Setup available currency codes.
		$this->available_currencies = array (
				'ZAR'
		);

		// Load the form fields.
		$this->init_form_fields ();

		// Load the settings.
		$this->init_settings ();

		// Setup constants.
		$this->setup_constants ();

		// Setup default merchant data.
		$this->service_key = $this->settings ['service_key'];

		$this->url = 'https://paynow.netcash.co.za/site/paynow.aspx';

		$this->title = $this->settings ['title'];

		$this->response_url = add_query_arg ( 'wc-api', 'WC_Gateway_PayNow', home_url ( '/' ) );

		add_action ( 'woocommerce_api_wc_gateway_paynow', array (
				$this,
				'check_ipn_response'
		) );

		add_action ( 'valid-paynow-standard-ipn-request', array (
				$this,
				'successful_request'
		) );

		/* 1.6.6 */
		add_action ( 'woocommerce_update_options_payment_gateways', array (
				$this,
				'custom_process_admin_options'
		) );

		/* 2.0.0 */
		add_action ( 'woocommerce_update_options_payment_gateways_' . $this->id, array (
				$this,
				'custom_process_admin_options'
		) );

		add_action ( 'woocommerce_receipt_paynow', array (
				$this,
				'receipt_page'
		) );

		if( !$this->SOAP_INSTALLED ) {
			// Add SOAP notices
			add_action( 'admin_notices', array(
				$this,
				'error_notice_soap'
			) );
		}

		// Check if the base currency supports this gateway.
		if (! $this->is_valid_for_use ())
			$this->enabled = false;
	}

	public static function error_notice_soap() {
		$this->error_notice_general("We've noticed that you <em>do not</em> have the PHP <a href=\"http://php.net/manual/en/book.soap.php\" target=\"_blank\">SOAP extension</a> installed. Without this extension, this module won't function.");
	}

	public static function error_notice_general($message = '') {
		?>
	    <div class="notice notice-error">
	        <p><strong>[Pay Now WooCommerce]</strong> <?php echo $message; ?></p>
	    </div>
	    <?php
	}

	/**
     * Processes and saves options.
     * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
     * @return bool was anything saved?
     */
	public function custom_process_admin_options() {

		// NOTE: Not too sure how to show error messages to user as the 'process_admin_options' method
		// simply skips errored (return false) fields and continues to save
		// So, we're adding errors and then showing them (display_errors())

		if( !$this->SOAP_INSTALLED ) {
			// Can't validate without SOAP.
			return false;
		}

		$post_data = $this->get_post_data();
		$form_fields = $this->get_form_fields();

		// Let's check the account number first. If it's correct, validate the service key.
		// Otherwise, bail
		$field_account_number = $form_fields['account_number'];
		$account_number = $this->get_field_value( 'account_number', $field_account_number, $post_data );
		if( !$account_number ) {
			$this->add_error( '<strong>Account Number</strong> An account number is required.' );
		}

		// Valid account numb
		$field_service_key = $form_fields['service_key'];
		$service_key = $this->get_field_value( 'service_key', $field_service_key, $post_data );
		if( !$service_key ) {
			$this->add_error( '<strong>Service Key</strong> A service key is required.' );
		}

		if(empty($this->get_errors())) {
			// No errors thus far, so Validate Service Keys here
			require(dirname(__FILE__).'/PayNowValidator.php');
			$Validator = new Netcash\PayNowValidator();
			$Validator->setVendorKey('7f7a86f8-5642-4595-8824-aa837fc584f2');

			try {
				$result = $Validator->validate_paynow_service_key($account_number, $service_key);

				if( $result !== true ) {
					$this->add_error($result[$service_key] ? $result[$service_key] : "<strong>Service Key</strong> {$result}");
				}
			} catch(\Exception $e) {
				$this->add_error($e->getMessage());
			}
		}

		if(!empty($this->get_errors())) {
			// Errors encountered. Return false.
			// NOTE: If users get 'Headers already sent issues, remove this line.'
			$this->display_errors();
			return false;
		}

		return parent::process_admin_options();
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * @since 1.0.0
	 */
	function init_form_fields() {
		$this->form_fields = array (
			'enabled' => array (
					'title' => __ ( 'Enable/Disable', 'woothemes' ),
					'label' => __ ( 'Enable Pay Now', 'woothemes' ),
					'type' => 'checkbox',
					'description' => __ ( 'This controls whether or not this gateway is enabled within WooCommerce.', 'woothemes' ),
					'default' => 'yes'
			),
			'title' => array (
					'title' => __ ( 'Title', 'woothemes' ),
					'type' => 'text',
					'description' => __ ( 'This controls the title which the user sees during checkout.', 'woothemes' ),
					'default' => __ ( 'Secure online Payments via Netcash', 'woothemes' )
			),
			'description' => array (
					'title' => __ ( 'Description', 'woothemes' ),
					'type' => 'text',
					'description' => __ ( 'This controls the description which the user sees during checkout.', 'woothemes' ),
					'default' => 'Secure online Payments via Netcash'
			),
			'account_number' => array (
					'title' => __ ( 'Account Number', 'woothemes' ),
					'type' => 'text', // text
					'description' => __ ( 'This is the Netcash Account Number, received from the Netcash website.', 'woothemes' ),
					'default' => ''
			),
			'service_key' => array (
					'title' => __ ( 'Service Key', 'woothemes' ),
					'type' => 'text', // text
					'description' => __ ( 'This is the Pay Now service key, received from the Netcash Connect Section on your Netcash Account.', 'woothemes' ),
					'default' => ''
			),
			'send_email_confirm' => array (
					'title' => __ ( 'Send Email Confirmations', 'woothemes' ),
					'type' => 'checkbox',
					'label' => __ ( 'An email confirmation will be sent from the Pay Now gateway to the client after each transaction.', 'woothemes' ),
					'default' => 'yes'
			),
			'do_tokenization' => array(
					'title' => __( 'Enable Credit Card Tokenization', 'woothemes' ),
					'type' => 'checkbox',
					'label' => __( 'If enabled, Netcash will return a Tokenized Credit Card value in the order notes.', 'woothemes' ),
					'default' => 'no'
			),
			'send_debug_email' => array(
					'title' => __( 'Enable Debug', 'woothemes' ),
					'type' => 'checkbox',
					'label' => __( 'Send debug e-mails for transactions and creates a log file in WooCommerce log folder called netcashnow.log', 'woothemes' ),
					'default' => 'yes'
			),
			'debug_email' => array(
					'title' => __( 'Who Receives Debug Emails?', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'The e-mail address to which debugging error e-mails are sent when debugging is on.', 'woothemes' ),
					'default' => get_option( 'admin_email' )
			)
		);
	}

	/**
	 * Get the plugin URL
	 *
	 * @since 1.0.0
	 */
	function plugin_url() {
		if (isset ( $this->plugin_url ))
			return $this->plugin_url;

		if (is_ssl ()) {
			return $this->plugin_url = str_replace ( 'http://', 'https://', WP_PLUGIN_URL ) . "/" . plugin_basename ( dirname ( dirname ( __FILE__ ) ) );
		} else {
			return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename ( dirname ( dirname ( __FILE__ ) ) );
		}
	}

	/**
	 * is_valid_for_use()
	 *
	 * Check if this gateway is enabled and available in the base currency being traded with.
	 *
	 * @since 1.0.0
	 */
	function is_valid_for_use() {
		global $woocommerce;

		$is_available = false;

		$user_currency = get_option ( 'woocommerce_currency' );

		$is_available_currency = in_array ( $user_currency, $this->available_currencies );

		if ($is_available_currency && $this->enabled == 'yes')
			$is_available = true;

		return $is_available;
	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		if ( $this->is_valid_for_use() ) {
            parent::admin_options();
        } else {
            ?><div class="inline error"><p><strong><?php _e( 'Gateway disabled', 'woocommerce' ); ?></strong>: <?php _e( 'Pay Now does not support your store currency.', 'woocommerce' ); ?></p></div> <?php
        }
	}

	/**
	 * There are no payment fields for Netcash Pay Now, but we want to show the description if set.
	 *
	 * @since 1.0.0
	 */
	function payment_fields() {
		if (isset ( $this->settings ['description'] ) && ('' != $this->settings ['description'])) {
			echo wpautop ( wptexturize ( $this->settings ['description'] ) );
		}
	}

	/**
	 * Generate the Netcash Pay Now button link.
	 *
	 * @since 1.0.0
	 */
	public function generate_paynow_form($order_id) {
		global $woocommerce;

		$this->log("The Pay Now debugger started a new session");

		$order = new WC_Order ( $order_id );

		$shipping_name = explode ( ' ', $order->get_shipping_method() );

		// Create unique order ID
		$order_id_unique = $order->get_id() . "_" . date("Ymds");

		$customerName = "{$order->get_billing_first_name()} {$order->get_billing_last_name()}";
		$orderID = $order_id;
		$customerID = $order->get_user_id();
		$netcashGUID = "7f7a86f8-5642-4595-8824-aa837fc584f2";

		$tokenize = (bool) $this->settings['do_tokenization'];

		// Construct variables for post
		$this->data_to_send = array (
			// Merchant details
			'm1' => $this->settings ['service_key'],
			// m2 is Netcash Pay Now internal key to distinguish their various portfolios
			'm2' => $netcashGUID,

			// Item details
			'p2' => $order_id_unique, // Reference
            // p3 modified to be Client Name (#Order ID) instead of Site name + Order ID
			'p3' => "{$customerName} ({$orderID})",
			'p4' => $order->get_total(),

			'm3' => $netcashGUID,

			// Extra fields
			'm4' => "{$customerID}", // Extra1
			'm5' => $order->get_cancel_order_url (), // Extra2
			'm6' => $order->get_order_key(), // Extra3

			'm9' => $order->get_billing_email(),
			'm10' => 'wc-api=WC_Gateway_PayNow',

			// Unused but useful reference fields for debugging
			'return_url' => $this->get_return_url ( $order ),
			'cancel_url' => $order->get_cancel_order_url (),
			'notify_url' => $this->response_url,

			'm14' => $tokenize ? '1' : '0',
		);

		$paynow_args_array = array ();

		foreach ( $this->data_to_send as $key => $value ) {
			$paynow_args_array [] = '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
		}

		$this->log ( "Netcash Pay Now form post paynow_args_array: " . print_r ( $paynow_args_array, true ) );

		return '<form action="' . $this->url . '" method="post" id="paynow_payment_form">
				' . implode ( '', $paynow_args_array ) . '
				<input type="submit" class="button-alt" id="submit_paynow_payment_form" value="' . __ ( 'Pay via Pay Now', 'woothemes' ) . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url () . '">' . __ ( 'Cancel order &amp; restore cart', 'woothemes' ) . '</a>
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{
								message: "<img src=\"' . $woocommerce->plugin_url () . '/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" />' . __ ( 'Thank you for your order. We are now redirecting you to Netcash Pay Now to make payment.', 'woothemes' ) . '",
								overlayCSS:
								{
									background: "#fff",
									opacity: 0.6
								},
								css: {
							        padding:        20,
							        textAlign:      "center",
							        color:          "#555",
							        border:         "3px solid #aaa",
							        backgroundColor:"#fff",
							        cursor:         "wait"
							    }
							});
						jQuery( "#submit_paynow_payment_form" ).click();
					});
				</script>
			</form>';
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @since 1.0.0
	 */
	function process_payment($order_id) {
		$order = new WC_Order ( $order_id );

		return array (
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url ( true )
		);
	}

	/**
	 * Receipt page.
	 *
	 * Display text and a button to direct the user to Pay Now.
	 *
	 * @since 1.0.0
	 */
	function receipt_page($order) {
		echo '<p>' . __ ( 'Thank you for your order, please click the button below to pay with Pay Now.', 'woothemes' ) . '</p>';
		echo $this->generate_paynow_form ( $order );
	}

	/**
	 * Check Pay Now IPN validity.
	 *
	 * @param array $data
	 *
	 * @since 1.0.0
	 */
	function check_ipn_request_is_valid($data) {
		global $woocommerce;

		$this->log("A callback was received from Netcash Pay Now...");

		$this->log ( "check_ipn_request_is_valid starting" );

		$pnError = false;
		$pnDone = false;
		$pnDebugEmail = $this->settings ['debug_email'];

		if (! is_email ( $pnDebugEmail )) {
			$pnDebugEmail = get_option ( 'admin_email' );
		}

		$sessionid = $data ['Extra3'];
		$transaction_id = $data ['RequestTrace'];
		$vendor_name = get_option ( 'blogname' );
		$vendor_url = home_url ( '/' );

		$order_id = ( int ) $data ['Reference'];
		// Convert unique reference back to actual order ID
		$pieces = explode("_", $order_id);
		$order_id = $pieces[0];

		$order_key = esc_attr ( $sessionid );
		$order = new WC_Order ( $order_id );

		$data_string = '';
		$data_array = array ();

		// Dump the submitted variables and calculate security signature
		foreach ( $data as $key => $val ) {
			$data_string .= $key . '=' . urlencode ( $val ) . '&';
			$data_array [$key] = $val;
		}

		// Remove the last '&' from the parameter string
		$data_string = substr ( $data_string, 0, - 1 );

		$this->log ( "\n" . '---------------------' . "\n" . 'Pay Now IPN call received' );

		// Notify Netcash Pay Now that information has been received
		if (! $pnError && ! $pnDone) {
			header ( 'HTTP/1.0 200 OK' );
			flush ();
		}

		// Get data sent by Netcash Pay Now
		if (! $pnError && ! $pnDone) {
			$this->log ( 'Pay Now Data from POST: ' . print_r ( $data, true ) );

			if ($data === false) {
				$pnError = true;
				$pnErrMsg = PN_ERR_BAD_ACCESS;
			}
		}

		// Get internal order and verify it hasn't already been processed
		if (! $pnError && ! $pnDone) {
			// $this->log ( "Purchase information: \n" . print_r ( $order, true ) );

			// Check if order has already been processed
			if ($order->get_status() == 'completed') {
				$this->log ( 'Order has already been processed' );
				$pnDone = true;
			}
		}

		// Check data against internal order
		if (! $pnError && ! $pnDone) {
			$this->log ( 'Check data against internal order' );

			// Check order amount
			if (! $this->amounts_equal ( $data['Amount'], $order->get_total() )) {
				$pnError = true;
				$pnErrMsg = PN_ERR_AMOUNT_MISMATCH;
				$pnErrMsg .= "Recieved: {$data['Amount']} but expected {$order->get_total()}";
			}			// Check session ID
			elseif (strcasecmp ( $data ['Extra3'], $order->get_order_key() ) != 0) {
				$pnError = true;
				$pnErrMsg = PN_ERR_SESSIONID_MISMATCH;
			}
		}

		// Checking status and updating order
		if (! $pnError && ! $pnDone) {
			$this->log ( 'Checking status and updating order' );

			if ($order->get_order_key() !== $order_key) {
				$this->log ( "Order key object: " . $order->get_order_key() );
				$this->log ( "Order key variable: " . $order_key );
				$this->log ( "order->order_key != order_key so exiting" );
				$pnError = true;
				$pnErrMsg = PN_ERR_SESSIONID_MISMATCH;
			}

			switch ($data ['TransactionAccepted']) {
				case 'true' :
					$this->log ( '- Complete' );

					// Payment completed
					$order->add_order_note ( __ ( 'IPN payment completed', 'woothemes' ) );
					// $order->payment_complete ();
					$this->log ( 'Note added to order' );

					if ($this->settings ['send_debug_email'] == 'yes') {
						$this->log ( 'Debug on so sending email' );
						$subject = "Netcash Pay Now Successful Transaction on your site";
						$body = "A Netcash Pay Now transaction has been completed successfully on your website\n" . "------------------------------------------------------------\n" . "Site: " . $vendor_name . " (" . $vendor_url . ")\n" . "Unique Reference: " . $data ['Reference'] . "\n" . "Request Trace: " . $data ['RequestTrace'] . "\n" . "Payment Status: " . $data ['TransactionAccepted'] . "\n" . "Order Status Code: " . $order->get_status();
						wp_mail ( $pnDebugEmail, $subject, $body );
						$this->log("Done sending success email");
					} else {
						$this->log ( 'Debug off so not success sending email' );
					}

					break;

				case 'false' :
					$this->log ( '- Failed, updating status with failed message: ' . $data['Reason'] );

					$order->update_status ( 'failed', sprintf ( __ ( 'Payment failure reason: "%s".', 'woothemes' ), strtolower ( self::escape ( $data ['Reason'] ) ) ) );
					$this->log("Checking if mail must be sent");
					if ($this->settings ['send_debug_email'] == 'yes') {
						$this->log("Debug on so sending mail that transaction failed.");
						$subject = "Netcash Pay Now Failed Transaction on your site";
						$body = "Hi,\n\n" . "A failed Netcash Pay Now transaction on your website requires attention\n" . "------------------------------------------------------------\n" . "Site: " . $vendor_name . " (" . $vendor_url . ")\n" . "Purchase ID: " . $order->get_id() . "\n" . "User ID: " . $order->get_user_id() . "\n" . "RequestTrace: " . $data ['RequestTrace'] . "\n" . "Payment Status: " . $data ['TransactionAccepted'] . "\n" . "Order Status Code: " . $order->get_status() . "\n" . "Failure Reason: " . $data ['Reason'];
						wp_mail ( $pnDebugEmail, $subject, $body );
						$this->log("Done sending failed email");
					} else {
						$this->log("Debug off so not sending failed email");
					}
					break;

				default :
					// If unknown status, do nothing (safest course of action)
					break;
			}
		}

		// If an error occurred
		if ($pnError) {
			$this->log ( 'Error occurred: ' . $pnErrMsg );

			if ($this->settings ['send_debug_email'] == 'yes') {
				$this->log ( 'Debug on so sending email notification' );

				// Send an email
				$subject = "Netcash Pay Now Processing Error: " . $pnErrMsg;
				$body = "Hi,\n\n" . "An invalid Pay Now transaction on your website requires attention\n" . "------------------------------------------------------------\n" . "Site: " . $vendor_name . " (" . $vendor_url . ")\n" . "Remote IP Address: " . $_SERVER ['REMOTE_ADDR'] . "\n" . "Remote host name: " . gethostbyaddr ( $_SERVER ['REMOTE_ADDR'] ) . "\n" . "Purchase ID: " . $order->get_id() . "\n" . "User ID: " . $order->get_user_id() . "\n";
				if (isset ( $data ['RequestTrace'] ))
					$body .= "Pay Now RequestTrace: " . $data ['RequestTrace'] . "\n";
				if (isset ( $data ['Reason'] ))
					$body .= "Pay Now Payment Transaction Failed Reason: " . $data ['Reason'] . "\n";
				$body .= "\nError: " . $pnErrMsg . "\n";

				switch ($pnErrMsg) {
					case PN_ERR_AMOUNT_MISMATCH :
						$body .= "Value received : " . $data ['Amount'] . "\n" . "Value should be: " . $order->get_total();
						break;

					case PN_ERR_ORDER_ID_MISMATCH :
						$body .= "Value received : " . $data ['Reference'] . "\n" . "Value should be: " . $order->get_id();
						break;

					case PN_ERR_SESSION_ID_MISMATCH :
						$body .= "Value received : " . $data ['Extra3'] . "\n" . "Value should be: " . $order->get_order_key();
						break;

					// For all other errors there is no need to add additional information
					default :
						break;
				}
				$this->log("Done sending error email");
				wp_mail ( $pnDebugEmail, $subject, $body );
			}
		}
		$this->log ( "Looks like we're almost done with check_ipn_request_is_valid");

		$this->log("Returning pnError value of '$pnError'");
		return $pnError;
	}

	/**
	 * Check Pay Now IPN response
	 *
	 * @since 1.0.0
	 */
	function check_ipn_response() {
		$this->log ( "check_ipn_response starting" );

		$strippedPOST = stripslashes_deep ( $_POST );

		if (!$this->check_ipn_request_is_valid ( $_POST )) {
			$this->log ("OK:ipn_request_is_valid");
			do_action ( 'valid-paynow-standard-ipn-request', $strippedPOST );
		} else {
			$error = "System failed checking ipn_request_valid";
			$this->log ($error);
			$this->log ("Something went wrong! Redirecting to order cancelled.");

			$order_id = ( int ) $_POST ['Reference'];
			// Convert unique reference back to actual order ID
			$pieces = explode("_", $order_id);
			$order_id = $pieces[0];
			$order = new WC_Order ( $order_id );
			if( $order ) {
				$order->update_status ( 'on-hold', $error );
			}
			wp_redirect($_POST['Extra2']);
		}
	}

	/**
	 * Successful Payment!
	 *
	 * @since 1.0.0
	 */
	function successful_request($posted) {
		$this->log("successful_request is called");

		$order_id = ( int ) $posted ['Reference'];
		// Convert unique reference back to actual order ID
		$pieces = explode("_", $order_id);
		$order_id = $pieces[0];

		$order_key = esc_attr ( $posted ['Extra3'] );
		$order = new WC_Order ( $order_id );

		if ($order->get_order_key() !== $order_key) {
			$error =  "Key problem. Redirecting to cancelled";
			$this->log($error);
			$order->update_status ( 'on-hold', $error );
			wp_redirect($_POST['Extra2']);
			exit ();
		}

		if ($order->get_status() !== 'completed') {
			// We are here so lets check status and do actions
			switch (strtolower ( $posted ['TransactionAccepted'] )) {
				case 'true' :
					// Payment completed
					$order->add_order_note ( __ ( 'IPN payment completed', 'woothemes' ) );
					$order->payment_complete ();

					if($posted['Method'] == '1') {
						// It was a CC transaction

						if(isset($posted['ccHolder'])) {
							// We have CC detail
							$pnCreditCardDetail = "";
							$pnCreditCardDetail .= "Credit card name: {$posted['ccHolder']} \r\n";
							$pnCreditCardDetail .= "Credit card number: {$posted['ccMasked']} \r\n";
							$pnCreditCardDetail .= "Expiry date: {$posted['ccExpiry']} \r\n";
							$pnCreditCardDetail .= "Card token: {$posted['ccToken']} \r\n";

							// Add CC detail as note
							$order->add_order_note ( __ ( "Tokenized credit card detail: \r\n{$pnCreditCardDetail}", 'woothemes' ) );
						} else {
							$order->add_order_note ( __ ( "Paid with credit card but tokenized detail was not received.", 'woothemes' ) );
						}
					}

					break;
				case 'false' :
					// Failed order
					$order->update_status ( 'failed', sprintf ( __ ( 'Payment failure reason1 "%s".', 'woothemes' ), strtolower ( self::escape ( $posted ['Reason'] ) ) ) );
					break;
// 				case 'denied' :
// 				case 'expired' :
// 				case 'failed' :
				default :
					// Hold order
					// TODO Hold order not used
					$order->update_status ( 'on-hold', sprintf ( __ ( 'Payment failure reason2 "%s".', 'woothemes' ), strtolower ( self::escape ( $posted ['Reason'] ) ) ) );
					break;
			}
			$order_return_url = $this->get_return_url($order);
			$this->log("All good, about to redirect to $order_return_url");
			// WordPress redirect
			// wp_redirect ( $order_return_url );
			// JavaScript redirect
			echo "<script>window.location='$order_return_url'</script>";
			exit ();

		} elseif ($order->get_status() == 'completed') {
			$order_return_url = $this->get_return_url($order);
			$this->log("Order already completed. We're redirecting to $order_return_url");
			// WordPress redirect
			// wp_redirect ( $order_return_url );
			// JavaScript redirect
			echo "<script>window.location='$order_return_url'</script>";
			exit ();
		}

		// This order is already completed
		$error =  "Error. Redirecting to cancelled";
		$this->log($error);
		$order->update_status ( 'on-hold', $error );
		wp_redirect($_POST['Extra2']);

		exit ();
	}

	/**
	 * Setup constants.
	 *
	 * Setup common values and messages used by the Netcash Pay Now gateway.
	 *
	 * @since 1.0.0
	 */
	function setup_constants() {
		global $woocommerce;
		// // Create user agent string
		// User agent constituents (for cURL)
		define ( 'PN_SOFTWARE_NAME', 'WooCommerce' );
		define ( 'PN_SOFTWARE_VER', $woocommerce->version );
		define ( 'PN_MODULE_NAME', 'WooCommerce-PayNow-Free' );
		define ( 'PN_MODULE_VER', $this->version );

		// Features
		// - PHP
		$pnFeatures = 'PHP ' . phpversion () . ';';

		// - cURL
		if (in_array ( 'curl', get_loaded_extensions () )) {
			define ( 'PN_CURL', '' );
			$pnVersion = curl_version ();
			$pnFeatures .= ' curl ' . $pnVersion ['version'] . ';';
		} else
			$pnFeatures .= ' nocurl;';

			// Create user agrent
		define ( 'PN_USER_AGENT', PN_SOFTWARE_NAME . '/' . PN_SOFTWARE_VER . ' (' . trim ( $pnFeatures ) . ') ' . PN_MODULE_NAME . '/' . PN_MODULE_VER );

		// General Defines
		define ( 'PN_TIMEOUT', 15 );
		define ( 'PN_EPSILON', 0.01 );

		// Messages
		// Error
		define ( 'PN_ERR_AMOUNT_MISMATCH', __ ( 'Amount mismatch', 'woothemes' ) );
		define ( 'PN_ERR_BAD_ACCESS', __ ( 'Bad access of page', 'woothemes' ) );
		define ( 'PN_ERR_BAD_SOURCE_IP', __ ( 'Bad source IP address', 'woothemes' ) );
		define ( 'PN_ERR_CONNECT_FAILED', __ ( 'Failed to connect to Netcash Pay Now', 'woothemes' ) );
		define ( 'PN_ERR_INVALID_SIGNATURE', __ ( 'Security signature mismatch', 'woothemes' ) );
		define ( 'PN_ERR_NO_SESSION', __ ( 'No saved session found for IPN transaction', 'woothemes' ) );
		define ( 'PN_ERR_ORDER_ID_MISSING_URL', __ ( 'Order ID not present in URL', 'woothemes' ) );
		define ( 'PN_ERR_ORDER_ID_MISMATCH', __ ( 'Order ID mismatch', 'woothemes' ) );
		define ( 'PN_ERR_ORDER_INVALID', __ ( 'This order ID is invalid', 'woothemes' ) );
		define ( 'PN_ERR_ORDER_NUMBER_MISMATCH', __ ( 'Order Number mismatch', 'woothemes' ) );
		define ( 'PN_ERR_ORDER_PROCESSED', __ ( 'This order has already been processed', 'woothemes' ) );
		define ( 'PN_ERR_PDT_FAIL', __ ( 'PDT query failed', 'woothemes' ) );
		define ( 'PN_ERR_PDT_TOKEN_MISSING', __ ( 'PDT token not present in URL', 'woothemes' ) );
		define ( 'PN_ERR_SESSIONID_MISMATCH', __ ( 'Session ID mismatch', 'woothemes' ) );
		define ( 'PN_ERR_UNKNOWN', __ ( 'Unkown error occurred', 'woothemes' ) );

		// General
		define ( 'PN_MSG_OK', __ ( 'Payment was successful', 'woothemes' ) );
		define ( 'PN_MSG_FAILED', __ ( 'Payment has failed', 'woothemes' ) );
		define ( 'PN_MSG_PENDING', __ ( 'The payment is pending. Please note, you will receive another Instant', 'woothemes' ) . __ ( ' Transaction Notification when the payment status changes to', 'woothemes' ) . __ ( ' "Completed", or "Failed"', 'woothemes' ) );
	}

	/**
	 * log()
	 *
	 * Log system processes.
	 *
	 * @since 1.0.0
	 */
	function log($message, $close = false) {
		if ( ( $this->settings['send_debug_email'] != 'yes' && ! is_admin() ) ) { return; }

		error_log($message);
	}

	/**
	 * amounts_equal()
	 *
	 * Checks to see whether the given amounts are equal using a proper floating
	 * point comparison with an Epsilon which ensures that insignificant decimal
	 * places are ignored in the comparison.
	 *
	 * eg. 100.00 is equal to 100.0001
	 *
	 * @param $amount1 Float
	 *        	1st amount for comparison
	 * @param $amount2 Float
	 *        	2nd amount for comparison
	 * @since 1.0.0
	 */
	function amounts_equal($amount1, $amount2) {
		if (abs ( floatval ( $amount1 ) - floatval ( $amount2 ) ) > PN_EPSILON) {
			return (false);
		} else {
			return (true);
		}
	}

	/**
	 * replace any non-ascii character with its hex code.
	 * @param  string $value
	 * @return string
	 */
	private static function escape($value) {
	    $return = '';
	    for($i = 0; $i < strlen($value); ++$i) {
	        $char = $value[$i];
	        $ord = ord($char);
	        if($char !== "'" && $char !== "\"" && $char !== '\\' && $ord >= 32 && $ord <= 126)
	            $return .= $char;
	        else
	            $return .= '\\x' . dechex($ord);
	    }
	    return $return;
	}

}
