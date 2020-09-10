<?php

use Netcash\PayNowSDK\Response;

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

	public static $ORDER_STATUS_COMPLETED = 'completed';
	public static $ORDER_STATUS_ON_HOLD = 'on-hold';
	public static $ORDER_STATUS_PROCESSING = 'processing';
	public static $ORDER_STATUS_PENDING = 'pending';
	public static $ORDER_STATUS_CANCELLED = 'cancelled';
	public static $ORDER_STATUS_FAILED = 'failed';
	public static $ORDER_STATUS_REFUNDED = 'refunded';

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
		self::error_notice_general("We've noticed that you <em>do not</em> have the PHP <a href=\"http://php.net/manual/en/book.soap.php\" target=\"_blank\">SOAP extension</a> installed. Without this extension, this module won't function.");
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
			$Validator = new Netcash\PayNowSDK\KeysValidator();
			$Validator->setVendorKey('7f7a86f8-5642-4595-8824-aa837fc584f2');

			try {
				$result = $Validator->validatePaynowServiceKey($account_number, $service_key);

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

		$customerName = "{$order->get_billing_first_name()} {$order->get_billing_last_name()}";
		$customerID = $order->get_user_id();
		$netcashGUID = "7f7a86f8-5642-4595-8824-aa837fc584f2";

		$tokenize = (bool) $this->settings['do_tokenization'];


		$form = new \Netcash\PayNowSDK\Form($this->settings ['service_key']);

		$form->setField('m2', $netcashGUID);
		$form->setField('m3', $netcashGUID);

		$form->setOrderID($order->get_id());
		$form->setDescription("{$customerName} ({$order->get_order_number()})");
		$form->setAmount($order->get_total());

//		$form->setCellphone($order->get_);
		$form->setEmail($order->get_billing_email());

		$form->setExtraField($customerID, 1); // m4
		$form->setExtraField($order->get_cancel_order_url(), 2); // m5
		$form->setExtraField($order->get_order_key(), 3); // m6

		$form->setReturnCardDetail($tokenize); // m14

		$form->setReturnString('wc-api=WC_Gateway_PayNow');

		// Output the HTML form
		$theForm = $form->makeForm(true, __( 'Pay via Pay Now', 'woothemes' ));

		$this->log ( "Netcash Pay Now form post paynow_args_array: " . print_r ( $form->getFields(), true ) );

		$x = $theForm . '<a class="button cancel" href="' . $order->get_cancel_order_url () . '">' . __ ( 'Cancel order &amp; restore cart', 'woothemes' ) . '</a>
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
						jQuery( "#netcash-paynow-submit" ).click();
					});
				</script>';

		return $x;
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
	 * Check Pay Now IPN response
	 *
	 * @since 1.0.0
	 */
	function check_ipn_response() {
		$this->log ( "check_ipn_response starting" );

		$paynow = new Netcash\PayNowSDK\PayNow();
		$response = new Netcash\PayNowSDK\Response($_POST);

		$order_id = esc_attr ( $response->getOrderID() );
		$order = new WC_Order ( $order_id );
		$order_key = esc_attr ( $response->getExtra(3) );

		if (!$paynow->validateResponse($_POST, $order->get_id(), $order->get_total())) {
			$this->log ("OK:valid Pay Now response");

			$completed_statusses = ['completed', 'processing'];

			if (in_array($order->get_status(), $completed_statusses)) {
				$this->log ( 'Order has already been completed/processed. Current status: '.$order->get_status() );
				// Error
				return false;
			}

			if ($order->get_order_key() !== $order_key) {
				$this->log ( "Order key object: " . $order->get_order_key() );
				$this->log ( "Order key variable: " . $order_key );
				$this->log ( "order->order_key != order_key so exiting" );

				// Error
				return false;
			}

			do_action ( 'valid-paynow-standard-ipn-request', $response->getData() );
		} else {
			$error = "System failed checking ipn_request_valid";
			$this->log ($error);
			$this->log ("Something went wrong! Redirecting to order cancelled.");

			$order = new WC_Order ( $order_id );
			if( $order ) {
				$order->update_status ( self::$ORDER_STATUS_ON_HOLD, $error );
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

		$response = new Response($posted);

		$order_id = $response->getOrderID();
		$order = new WC_Order ( $order_id );
//		$order_key = $response->getExtra(3);

		if ($order->get_status() === 'completed') {
			die('xxx123');
			throw new \Exception('order exists...');
		}

		$cancel_redirect_url = $response->getExtra(2);

		$this->log("- current order status: ".$order->get_status());
		$order_return_url = $this->get_return_url($order);

		if ($response->isPending()) {
			// Still waiting... (E.g., EFT, or in-store)
			// Mark order as "Pending" payment
			$order->add_order_note ( __ ( 'Netcash response received. Payment pending', 'woothemes' ) );
			$order->update_status ( self::$ORDER_STATUS_PENDING, 'Pending payment');
		} else {

			$order->add_order_note ( __ ( 'IPN payment completed', 'woothemes' ) );

			// An actual request
			if ($response->wasDeclined() || $response->wasCancelled() ) {

				$order->add_order_note ( __ ( 'Payment was cancelled or declined', 'woothemes' ) );

				if($response->wasDeclined()) {
					$order->update_status ( self::$ORDER_STATUS_FAILED, sprintf ( __ ( 'Payment failure reason "%s".', 'woothemes' ), strtolower ( self::escape ( $response->getReason() ) ) ) );
				}
				if($response->wasCancelled()) {
					// If the user cancelled, redirect to cancel URL.
					$this->log("Order cancelled by user.");
					$order_return_url = html_entity_decode($order->get_cancel_order_url());
					$order->update_status ( self::$ORDER_STATUS_CANCELLED, __ ('Payment canceled by user.', 'woothemes') );
				}

			} else if ($response->wasAccepted()) {
				// Success. Mark Order as "Paid"
				$order->payment_complete();

				if($response->wasCreditCardTransaction()) {
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
			} else {
				// No status detected...
				// Hold order
				// TODO Hold order not used
				$order->update_status ( self::$ORDER_STATUS_ON_HOLD, sprintf ( __ ( 'Payment failure reason2 "%s".', 'woothemes' ), strtolower ( self::escape ( $posted ['Reason'] ) ) ) );

				wp_redirect($cancel_redirect_url);
				echo "<script>window.location='$cancel_redirect_url'</script>";
				exit ();
			}
		}

		$this->log("Redirecting to $order_return_url");
		// WordPress redirect
		 wp_redirect ( $order_return_url );
		// JavaScript redirect
		echo "<script>window.location='$order_return_url'</script>";
		exit ();

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
	 * @param float $amount1 Float
	 *        	1st amount for comparison
	 * @param float $amount2 Float
	 *        	2nd amount for comparison
	 * @since 1.0.0
	 */
	function amounts_equal($amount1, $amount2) {
		$epsilon = 0.01;
		if (abs ( floatval ( $amount1 ) - floatval ( $amount2 ) ) > $epsilon) {
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
