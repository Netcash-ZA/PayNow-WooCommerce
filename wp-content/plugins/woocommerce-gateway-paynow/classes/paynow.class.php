<?php
/**
 * Pay Now Payment Gateway
 *
 * Provides a Pay Now Payment Gateway.
 *
 * @class 		woocommerce_paynow
 * @package		WooCommerce
 * @category	Payment Gateways
 * @author		Gateway Modules
 *
 *
 * Table Of Contents
 *
 * __construct()
 * init_form_fields()
 * add_testmode_admin_settings_notice()
 * plugin_url()
 * add_currency()
 * add_currency_symbol()
 * is_valid_for_use()
 * admin_options()
 * payment_fields()
 * generate_payfast_form()
 * process_payment()
 * receipt_page()
 * check_itn_request_is_valid()
 * check_itn_response()
 * successful_request()
 * setup_constants()
 * log()
 * validate_signature()
 * validate_ip()
 * validate_response_data()
 * amounts_equal()
 */
class WC_Gateway_PayNow extends WC_Payment_Gateway {

	public $version = '1.0';

	public function __construct() {
        global $woocommerce;
        $this->id			= 'paynow';
        $this->method_title = __( 'Pay Now', 'woothemes' );
        $this->icon 		= $this->plugin_url() . '/assets/images/icon.png';
        $this->has_fields 	= true;
        $this->debug_email 	= get_option( 'admin_email' );

		// Setup available countries.
		$this->available_countries = array( 'ZA' );

		// Setup available currency codes.
		$this->available_currencies = array( 'ZAR' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Setup constants.
		$this->setup_constants();

		// Setup default merchant data.		
		$this->service_key = $this->settings['service_key'];
		//$this->url = 'https://www.payfast.co.za/eng/process?aff=woo-free';
		$this->url = 'https://paynow.sagepay.co.za/site/paynow.aspx';
		$this->validate_url = 'https://www.payfast.co.za/eng/query/validate';
		$this->title = $this->settings['title'];

		// Setup the test data, if in test mode.
		if ( $this->settings['testmode'] == 'yes' ) {
			$this->add_testmode_admin_settings_notice();
			$this->url = 'https://sandbox.payfast.co.za/eng/process?aff=woo-free';
			$this->validate_url = 'https://sandbox.payfast.co.za/eng/query/validate';
		}

		$this->response_url	= add_query_arg( 'wc-api', 'WC_Gateway_PayNow', home_url( '/' ) );

		add_action( 'woocommerce_api_wc_gateway_paynow', array( $this, 'check_itn_response' ) );
		add_action( 'valid-paynow-standard-itn-request', array( $this, 'successful_request' ) );

		/* 1.6.6 */
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );

		/* 2.0.0 */
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_receipt_paynow', array( $this, 'receipt_page' ) );

		// Check if the base currency supports this gateway.
		if ( ! $this->is_valid_for_use() )
			$this->enabled = false;
    }

	/**
     * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
     */
    function init_form_fields () {

    	$this->form_fields = array(
    						'enabled' => array(
											'title' => __( 'Enable/Disable', 'woothemes' ),
											'label' => __( 'Enable Pay Now', 'woothemes' ),
											'type' => 'checkbox',
											'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'woothemes' ),
											'default' => 'yes'
										),
    						'title' => array(
    										'title' => __( 'Title', 'woothemes' ),
    										'type' => 'text',
    										'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
    										'default' => __( 'PayFast', 'woothemes' )
    									),
							'description' => array(
											'title' => __( 'Description', 'woothemes' ),
											'type' => 'text',
											'description' => __( 'This controls the description which the user sees during checkout.', 'woothemes' ),
											'default' => ''
										),														
							'service_key' => array(
											'title' => __( 'Service Key', 'woothemes' ),
											'type' => 'text',
											'description' => __( 'This is the service key, received from Pay Now.', 'woothemes' ),
											'default' => ''
										),
							'send_email_confirm' => array(
											'title' => __( 'Send Email Confirmations', 'woothemes' ),
											'type' => 'checkbox',
											'label' => __( 'An email confirmation will be sent from the Pay Now gateway to the client after each transaction.', 'woothemes' ),
											'default' => 'yes'
										)							
							);

    } // End init_form_fields()

    /**
     * add_testmode_admin_settings_notice()
     *
     * Add a notice to the service_key when in test mode.
     *
     * @since 1.0.0
     */
    function add_testmode_admin_settings_notice () {    	
    	$this->form_fields['service_key']['description'] .= ' <strong>' . __( 'PayNow Sandbox Merchant Key currently in use.', 'woothemes' ) . ' ( 46f0cd694581a )</strong>';
    } // End add_testmode_admin_settings_notice()

    /**
	 * Get the plugin URL
	 *
	 * @since 1.0.0
	 */
	function plugin_url() {
		if( isset( $this->plugin_url ) )
			return $this->plugin_url;

		if ( is_ssl() ) {
			return $this->plugin_url = str_replace( 'http://', 'https://', WP_PLUGIN_URL ) . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		} else {
			return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		}
	} // End plugin_url()

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

        $user_currency = get_option( 'woocommerce_currency' );

        $is_available_currency = in_array( $user_currency, $this->available_currencies );

		if ( $is_available_currency && $this->enabled == 'yes' && $this->settings['service_key'] != '' )
			$is_available = true;

        return $is_available;
	} // End is_valid_for_use()

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		// Make sure to empty the log file if not in test mode.
		if ( $this->settings['testmode'] != 'yes' ) {
			$this->log( '' );
			$this->log( '', true );
		}

    	?>
    	<h3><?php _e( 'Pay Now', 'woothemes' ); ?></h3>
    	<p><?php printf( __( 'Pay Now works by sending the user to %sPayNow%s to enter their payment information.', 'woothemes' ), '<a href="http://www.sagepasa.co.za/">', '</a>' ); ?></p>

    	<?php
    	if ( 'ZAR' == get_option( 'woocommerce_currency' ) ) {
    		?><table class="form-table"><?php
			// Generate the HTML For the settings form.
    		$this->generate_settings_html();
    		?></table><!--/.form-table--><?php
		} else {
			?>
			<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woothemes' ); ?></strong> <?php echo sprintf( __( 'Choose South African Rands as your store currency in <a href="%s">Pricing Options</a> to enable the PayFast Gateway.', 'woocommerce' ), admin_url( '?page=woocommerce&tab=catalog' ) ); ?></p></div>
		<?php
		} // End check currency
		?>
    	<?php
    } // End admin_options()

    /**
	 * There are no payment fields for PayFast, but we want to show the description if set.
	 *
	 * @since 1.0.0
	 */
    function payment_fields() {
    	if ( isset( $this->settings['description'] ) && ( '' != $this->settings['description'] ) ) {
    		echo wpautop( wptexturize( $this->settings['description'] ) );
    	}
    } // End payment_fields()

	/**
	 * Generate the PayFast button link.
	 *
	 * @since 1.0.0
	 */
    public function generate_payfast_form( $order_id ) {

		global $woocommerce;

		$order = new WC_Order( $order_id );

		$shipping_name = explode(' ', $order->shipping_method);

		// Construct variables for post
	    $this->data_to_send = array(
	        // Merchant details	        
	        'service_key' => $this->settings['service_key'],
	        'return_url' => $this->get_return_url( $order ),
	        'cancel_url' => $order->get_cancel_order_url(),
	        'notify_url' => $this->response_url,

			// Billing details
			'name_first' => $order->billing_first_name,
			'name_last' => $order->billing_last_name,
			// 'email_address' => $order->billing_email,

	        // Item details
	        'm_payment_id' => ltrim( $order->get_order_number(), __( '#', 'hash before order number', 'woothemes' ) ),
	        'amount' => $order->order_total,
	    	'item_name' => get_bloginfo( 'name' ) .' purchase, Order ' . $order->get_order_number(),
	    	'item_description' => sprintf( __( 'New order from %s', 'woothemes' ), get_bloginfo( 'name' ) ),

	    	// Custom strings
	    	'custom_str1' => $order->order_key,
	    	'custom_str2' => 'WooCommerce/' . $woocommerce->version . '; ' . get_site_url(),
	    	'custom_str3' => $order->id,
	    	'source' => 'WooCommerce-Free-Plugin'
	   	);

	   	// Override service_key if the gateway is in test mode.
	   	if ( $this->settings['testmode'] == 'yes' ) {	   		
	   		$this->data_to_send['service_key'] = '46f0cd694581a';
	   	}

		$paynow_args_array = array();

		foreach ($this->data_to_send as $key => $value) {
			$paynow_args_array[] = '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
		}

		return '<form action="' . $this->url . '" method="post" id="paynow_payment_form">
				' . implode('', $paynow_args_array) . '
				<input type="submit" class="button-alt" id="submit_paynow_payment_form" value="' . __( 'Pay via PayFast', 'woothemes' ) . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'woothemes' ) . '</a>
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{
								message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" />' . __( 'Thank you for your order. We are now redirecting you to PayFast to make payment.', 'woothemes' ) . '",
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

	} // End generate_payfast_form()

	/**
	 * Process the payment and return the result.
	 *
	 * @since 1.0.0
	 */
	function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );

		return array(
			'result' 	=> 'success',
			'redirect'	=> $order->get_checkout_payment_url( true )
		);

	}

	/**
	 * Reciept page.
	 *
	 * Display text and a button to direct the user to PayFast.
	 *
	 * @since 1.0.0
	 */
	function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with PayFast.', 'woothemes' ) . '</p>';

		echo $this->generate_payfast_form( $order );
	} // End receipt_page()

	/**
	 * Check PayFast ITN validity.
	 *
	 * @param array $data
	 * @since 1.0.0
	 */
	function check_itn_request_is_valid( $data ) {
		global $woocommerce;

		$pnError = false;
		$pfDone = false;
		$pnDebugEmail = $this->settings['debug_email'];

		if ( ! is_email( $pnDebugEmail ) ) {
			$pnDebugEmail = get_option( 'admin_email' );
		}

		$sessionid = $data['custom_str1'];
        $transaction_id = $data['pf_payment_id'];
        $vendor_name = get_option( 'blogname' );
        $vendor_url = home_url( '/' );

		$order_id = (int) $data['custom_str3'];
		$order_key = esc_attr( $sessionid );
		$order = new WC_Order( $order_id );

		$data_string = '';
		$data_array = array();

		// Dump the submitted variables and calculate security signature
	    foreach( $data as $key => $val ) {
	    	if( $key != 'signature' ) {
	    		$data_string .= $key .'='. urlencode( $val ) .'&';
	    		$data_array[$key] = $val;
	    	}
	    }

	    // Remove the last '&' from the parameter string
	    $data_string = substr( $data_string, 0, -1 );
	    $signature = md5( $data_string );

		$this->log( "\n" . '----------' . "\n" . 'PayFast ITN call received' );

		// Notify PayFast that information has been received
        if( ! $pnError && ! $pfDone ) {
            header( 'HTTP/1.0 200 OK' );
            flush();
        }

        // Get data sent by PayFast
        if ( ! $pnError && ! $pfDone ) {
        	$this->log( 'Get posted data' );

            $this->log( 'Pay Now Data: '. print_r( $data, true ) );

            if ( $data === false ) {
                $pnError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }

        // Verify security signature
        if( ! $pnError && ! $pfDone ) {
            $this->log( 'Verify security signature' );

            // If signature different, log for debugging
            if( ! $this->validate_signature( $data, $signature ) ) {
                $pnError = true;
                $pfErrMsg = PF_ERR_INVALID_SIGNATURE;
            }
        }

        // Verify source IP (If not in debug mode)
        if( ! $pnError && ! $pfDone && $this->settings['testmode'] != 'yes' ) {
            $this->log( 'Verify source IP' );

            if( ! $this->validate_ip( $_SERVER['REMOTE_ADDR'] ) ) {
                $pnError = true;
                $pfErrMsg = PF_ERR_BAD_SOURCE_IP;
            }
        }

        // Get internal order and verify it hasn't already been processed
        if( ! $pnError && ! $pfDone ) {

            $this->log( "Purchase:\n". print_r( $order, true )  );

            // Check if order has already been processed
            if( $order->status == 'completed' ) {
                $this->log( 'Order has already been processed' );
                $pfDone = true;
            }
        }

        // Verify data received
        if( ! $pnError ) {
            $this->log( 'Verify data received' );

            $pfValid = $this->validate_response_data( $data_array );

            if( ! $pfValid ) {
                $pnError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }

        // Check data against internal order
        if( ! $pnError && ! $pfDone ) {
            $this->log( 'Check data against internal order' );

            // Check order amount
            if( ! $this->amounts_equal( $data['amount_gross'], $order->order_total ) ) {
                $pnError = true;
                $pfErrMsg = PF_ERR_AMOUNT_MISMATCH;
            }
            // Check session ID
            elseif( strcasecmp( $data['custom_str1'], $order->order_key ) != 0 )
            {
                $pnError = true;
                $pfErrMsg = PF_ERR_SESSIONID_MISMATCH;
            }
        }

        // Check status and update order
        if( ! $pnError && ! $pfDone ) {
            $this->log( 'Check status and update order' );

		if ( $order->order_key !== $order_key ) { exit; }

    		switch( $data['payment_status'] ) {
                case 'COMPLETE':
                    $this->log( '- Complete' );

                   // Payment completed
					$order->add_order_note( __( 'ITN payment completed', 'woothemes' ) );
					$order->payment_complete();

                    if( $this->settings['testmode'] == 'yes' && $this->settings['send_debug_email'] == 'yes' ) {
                        $subject = "PayFast ITN on your site";
                        $body =
                            "Hi,\n\n".
                            "A PayFast transaction has been completed on your website\n".
                            "------------------------------------------------------------\n".
                            "Site: ". $vendor_name ." (". $vendor_url .")\n".
                            "Purchase ID: ". $data['m_payment_id'] ."\n".
                            "Pay Now Transaction ID: ". $data['pf_payment_id'] ."\n".
                            "Pay Now Payment Status: ". $data['payment_status'] ."\n".
                            "Order Status Code: ". $order->status;
                        wp_mail( $pnDebugEmail, $subject, $body );
                    }
                    break;

    			case 'FAILED':
                    $this->log( '- Failed' );

                    $order->update_status( 'failed', sprintf(__('Payment %s via ITN.', 'woothemes' ), strtolower( sanitize( $data['payment_status'] ) ) ) );

					if( $this->settings['testmode'] == 'yes' && $this->settings['send_debug_email'] == 'yes' ) {
	                    $subject = "Pay Now ITN Transaction on your site";
	                    $body =
	                        "Hi,\n\n".
	                        "A failed Pay Now transaction on your website requires attention\n".
	                        "------------------------------------------------------------\n".
	                        "Site: ". $vendor_name ." (". $vendor_url .")\n".
	                        "Purchase ID: ". $order->id ."\n".
	                        "User ID: ". $order->user_id ."\n".
	                        "PayFast Transaction ID: ". $data['pf_payment_id'] ."\n".
	                        "PayFast Payment Status: ". $data['payment_status'];
	                    wp_mail( $pnDebugEmail, $subject, $body );
                    }
        			break;

    			case 'PENDING':
                    $this->log( '- Pending' );

                    // Need to wait for "Completed" before processing
        			$order->update_status( 'pending', sprintf(__('Payment %s via ITN.', 'woothemes' ), strtolower( sanitize( $data['payment_status'] ) ) ) );
        			break;

    			default:
                    // If unknown status, do nothing (safest course of action)
    			break;
            }
        }

        // If an error occurred
        if( $pnError ) {
            $this->log( 'Error occurred: '. $pfErrMsg );

            if( $this->settings['testmode'] == 'yes' && $this->settings['send_debug_email'] == 'yes' ) {
	            $this->log( 'Sending email notification' );

	             // Send an email
	            $subject = "Pay Now ITN error: ". $pfErrMsg;
	            $body =
	                "Hi,\n\n".
	                "An invalid Pay Now transaction on your website requires attention\n".
	                "------------------------------------------------------------\n".
	                "Site: ". $vendor_name ." (". $vendor_url .")\n".
	                "Remote IP Address: ".$_SERVER['REMOTE_ADDR']."\n".
	                "Remote host name: ". gethostbyaddr( $_SERVER['REMOTE_ADDR'] ) ."\n".
	                "Purchase ID: ". $order->id ."\n".
	                "User ID: ". $order->user_id ."\n";
	            if( isset( $data['pf_payment_id'] ) )
	                $body .= "PayFast Transaction ID: ". $data['pf_payment_id'] ."\n";
	            if( isset( $data['payment_status'] ) )
	                $body .= "PayFast Payment Status: ". $data['payment_status'] ."\n";
	            $body .=
	                "\nError: ". $pfErrMsg ."\n";

	            switch( $pfErrMsg ) {
	                case PF_ERR_AMOUNT_MISMATCH:
	                    $body .=
	                        "Value received : ". $data['amount_gross'] ."\n".
	                        "Value should be: ". $order->order_total;
	                    break;

	                case PF_ERR_ORDER_ID_MISMATCH:
	                    $body .=
	                        "Value received : ". $data['custom_str3'] ."\n".
	                        "Value should be: ". $order->id;
	                    break;

	                case PF_ERR_SESSION_ID_MISMATCH:
	                    $body .=
	                        "Value received : ". $data['custom_str1'] ."\n".
	                        "Value should be: ". $order->id;
	                    break;

	                // For all other errors there is no need to add additional information
	                default:
	                    break;
	            }

	            wp_mail( $pnDebugEmail, $subject, $body );
            }
        }

        // Close log
        $this->log( '', true );

    	return $pnError;
    } // End check_itn_request_is_valid()

	/**
	 * Check PayFast ITN response.
	 *
	 * @since 1.0.0
	 */
	function check_itn_response() {
		$_POST = stripslashes_deep( $_POST );

		if ( $this->check_itn_request_is_valid( $_POST ) ) {
			do_action( 'valid-payfast-standard-itn-request', $_POST );
		}
	} // End check_itn_response()

	/**
	 * Successful Payment!
	 *
	 * @since 1.0.0
	 */
	function successful_request( $posted ) {
		if ( ! isset( $posted['custom_str3'] ) && ! is_numeric( $posted['custom_str3'] ) ) { return false; }

		$order_id = (int) $posted['custom_str3'];
		$order_key = esc_attr( $posted['custom_str1'] );
		$order = new WC_Order( $order_id );

		if ( $order->order_key !== $order_key ) { exit; }

		if ( $order->status !== 'completed' ) {
			// We are here so lets check status and do actions
			switch ( strtolower( $posted['payment_status'] ) ) {
				case 'completed' :
					// Payment completed
					$order->add_order_note( __( 'ITN payment completed', 'woothemes' ) );
					$order->payment_complete();
				break;
				case 'denied' :
				case 'expired' :
				case 'failed' :
				case 'voided' :
					// Failed order
					$order->update_status( 'failed', sprintf(__('Payment %s via ITN.', 'woothemes' ), strtolower( sanitize( $posted['payment_status'] ) ) ) );
				break;
				default:
					// Hold order
					$order->update_status( 'on-hold', sprintf(__('Payment %s via ITN.', 'woothemes' ), strtolower( sanitize( $posted['payment_status'] ) ) ) );
				break;
			} // End SWITCH Statement

			wp_redirect( $this->get_return_url( $order ) );
			exit;
		} // End IF Statement

		exit;
	}

	/**
	 * Setup constants.
	 *
	 * Setup common values and messages used by the PayFast gateway.
	 *
	 * @since 1.0.0
	 */
	function setup_constants () {
		global $woocommerce;
		//// Create user agent string
		// User agent constituents (for cURL)
		define( 'PF_SOFTWARE_NAME', 'WooCommerce' );
		define( 'PF_SOFTWARE_VER', $woocommerce->version );
		define( 'PF_MODULE_NAME', 'WooCommerce-PayFast-Free' );
		define( 'PF_MODULE_VER', $this->version );

		// Features
		// - PHP
		$pfFeatures = 'PHP '. phpversion() .';';

		// - cURL
		if( in_array( 'curl', get_loaded_extensions() ) )
		{
		    define( 'PF_CURL', '' );
		    $pfVersion = curl_version();
		    $pfFeatures .= ' curl '. $pfVersion['version'] .';';
		}
		else
		    $pfFeatures .= ' nocurl;';

		// Create user agrent
		define( 'PF_USER_AGENT', PF_SOFTWARE_NAME .'/'. PF_SOFTWARE_VER .' ('. trim( $pfFeatures ) .') '. PF_MODULE_NAME .'/'. PF_MODULE_VER );

		// General Defines
		define( 'PF_TIMEOUT', 15 );
		define( 'PF_EPSILON', 0.01 );

		// Messages
		    // Error
		define( 'PF_ERR_AMOUNT_MISMATCH', __( 'Amount mismatch', 'woothemes' ) );
		define( 'PF_ERR_BAD_ACCESS', __( 'Bad access of page', 'woothemes' ) );
		define( 'PF_ERR_BAD_SOURCE_IP', __( 'Bad source IP address', 'woothemes' ) );
		define( 'PF_ERR_CONNECT_FAILED', __( 'Failed to connect to PayFast', 'woothemes' ) );
		define( 'PF_ERR_INVALID_SIGNATURE', __( 'Security signature mismatch', 'woothemes' ) );		
		define( 'PF_ERR_NO_SESSION', __( 'No saved session found for ITN transaction', 'woothemes' ) );
		define( 'PF_ERR_ORDER_ID_MISSING_URL', __( 'Order ID not present in URL', 'woothemes' ) );
		define( 'PF_ERR_ORDER_ID_MISMATCH', __( 'Order ID mismatch', 'woothemes' ) );
		define( 'PF_ERR_ORDER_INVALID', __( 'This order ID is invalid', 'woothemes' ) );
		define( 'PF_ERR_ORDER_NUMBER_MISMATCH', __( 'Order Number mismatch', 'woothemes' ) );
		define( 'PF_ERR_ORDER_PROCESSED', __( 'This order has already been processed', 'woothemes' ) );
		define( 'PF_ERR_PDT_FAIL', __( 'PDT query failed', 'woothemes' ) );
		define( 'PF_ERR_PDT_TOKEN_MISSING', __( 'PDT token not present in URL', 'woothemes' ) );
		define( 'PF_ERR_SESSIONID_MISMATCH', __( 'Session ID mismatch', 'woothemes' ) );
		define( 'PF_ERR_UNKNOWN', __( 'Unkown error occurred', 'woothemes' ) );

		    // General
		define( 'PF_MSG_OK', __( 'Payment was successful', 'woothemes' ) );
		define( 'PF_MSG_FAILED', __( 'Payment has failed', 'woothemes' ) );
		define( 'PF_MSG_PENDING',
		    __( 'The payment is pending. Please note, you will receive another Instant', 'woothemes' ).
		    __( ' Transaction Notification when the payment status changes to', 'woothemes' ).
		    __( ' "Completed", or "Failed"', 'woothemes' ) );
	} // End setup_constants()

	/**
	 * log()
	 *
	 * Log system processes.
	 *
	 * @since 1.0.0
	 */

	function log ( $message, $close = false ) {
		if ( ( $this->settings['testmode'] != 'yes' && ! is_admin() ) ) { return; }

		static $fh = 0;

		if( $close ) {
            @fclose( $fh );
        } else {
            // If file doesn't exist, create it
            if( !$fh ) {
                $pathinfo = pathinfo( __FILE__ );
                $dir = str_replace( '/classes', '/logs', $pathinfo['dirname'] );
                $fh = @fopen( $dir .'/paynow.log', 'w' );
            }

            // If file was successfully created
            if( $fh ) {
                $line = $message ."\n";

                fwrite( $fh, $line );
            }
        }
	} // End log()

	/**
	 * validate_signature()
	 *
	 * Validate the signature against the returned data.
	 *
	 * @param array $data
	 * @param string $signature
	 * @since 1.0.0
	 */

	function validate_signature ( $data, $signature ) {

	    $result = ( $data['signature'] == $signature );

	    $this->log( 'Signature = '. ( $result ? 'valid' : 'invalid' ) );

	    return( $result );
	} // End validate_signature()

	/**
	 * validate_ip()
	 *
	 * Validate the IP address to make sure it's coming from PayFast.
	 *
	 * @param array $data
	 * @since 1.0.0
	 */

	function validate_ip( $sourceIP ) {
	    // Variable initialization
	    $validHosts = array(
	        'www.payfast.co.za',
	        'sandbox.payfast.co.za',
	        'w1w.payfast.co.za',
	        'w2w.payfast.co.za',
	        );

	    $validIps = array();

	    foreach( $validHosts as $pfHostname ) {
	        $ips = gethostbynamel( $pfHostname );

	        if( $ips !== false )
	            $validIps = array_merge( $validIps, $ips );
	    }

	    // Remove duplicates
	    $validIps = array_unique( $validIps );

	    $this->log( "Valid IPs:\n". print_r( $validIps, true ) );

	    if( in_array( $sourceIP, $validIps ) ) {
	        return( true );
	    } else {
	        return( false );
	    }
	} // End validate_ip()

	/**
	 * validate_response_data()
	 *
	 * @param $pfHost String Hostname to use
	 * @param $pfParamString String Parameter string to send
	 * @param $proxy String Address of proxy to use or NULL if no proxy
	 * @since 1.0.0
	 */
	function validate_response_data( $pfParamString, $pfProxy = null ) {
		global $woocommerce;
	    $this->log( 'Host = '. $this->validate_url );
	    $this->log( 'Params = '. print_r( $pfParamString, true ) );

		if ( ! is_array( $pfParamString ) ) { return false; }

		$post_data = $pfParamString;

		$url = $this->validate_url;

		$response = wp_remote_post( $url, array(
       				'method' => 'POST',
        			'body' => $post_data,
        			'timeout' => 70,
        			'sslverify' => true,
        			'user-agent' => PF_USER_AGENT //'WooCommerce/' . $woocommerce->version . '; ' . get_site_url()
    			));

		if ( is_wp_error( $response ) ) throw new Exception( __( 'There was a problem connecting to the payment gateway.', 'woothemes' ) );

		if( empty( $response['body'] ) ) throw new Exception( __( 'Empty PayFast response.', 'woothemes' ) );

		parse_str( $response['body'], $parsed_response );

		$response = $parsed_response;

	    $this->log( "Response:\n". print_r( $response, true ) );

	    // Interpret Response
	    if ( is_array( $response ) && in_array( 'VALID', array_keys( $response ) ) ) {
	    	return true;
	    } else {
	    	return false;
	    }
	} // End validate_responses_data()

	/**
	 * amounts_equal()
	 *
	 * Checks to see whether the given amounts are equal using a proper floating
	 * point comparison with an Epsilon which ensures that insignificant decimal
	 * places are ignored in the comparison.
	 *
	 * eg. 100.00 is equal to 100.0001
	 *
	 * @author Jonathan Smit
	 * @param $amount1 Float 1st amount for comparison
	 * @param $amount2 Float 2nd amount for comparison
	 * @since 1.0.0
	 */
	function amounts_equal ( $amount1, $amount2 ) {
		if( abs( floatval( $amount1 ) - floatval( $amount2 ) ) > PF_EPSILON ) {
			return( false );
		} else {
			return( true );
		}
	} // End amounts_equal()

} // End Class