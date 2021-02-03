<?php
/**
 * Provides a Netcash Pay Now Payment Gateway.
 *
 * @category   Payment Gateways
 * @package    WooCommerce
 * @subpackage WC_Gateway_PayNow
 * @author     Netcash
 * @license    https://www.gnu.org/licenses/gpl-3.0.txt GNU/GPLv3
 * @link       https://netcash.co.za
 * @since      1.0.0
 */

use Netcash\PayNow\Response;

/**
 * Class WC_Gateway_PayNow
 *
 * The main Gateway Class
 */
class WC_Gateway_PayNow extends WC_Payment_Gateway {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	public $version = '4.0.0';

	const ORDER_STATUS_COMPLETED  = 'completed';
	const ORDER_STATUS_ON_HOLD    = 'on-hold';
	const ORDER_STATUS_PROCESSING = 'processing';
	const ORDER_STATUS_PENDING    = 'pending';
	const ORDER_STATUS_CANCELLED  = 'cancelled';
	const ORDER_STATUS_FAILED     = 'failed';
	const ORDER_STATUS_REFUNDED   = 'refunded';

	const PN_ERROR_KEY_MISMATCH          = 'order key mismatch';
	const PN_ERROR_AMOUNT_MISMATCH       = 'order amount mismatch';
	const PN_ERROR_ORDER_ALREADY_HANDLED = 'order already completed/processed';
	const PN_ERROR_GENERAL_ERROR         = 'something went wrong';

	/**
	 * Whether SOAP extension is installed
	 *
	 * @var bool
	 */
	private $soap_installed = false;

	/**
	 * WC_Gateway_PayNow constructor.
	 * Init Class
	 */
	public function __construct() {

		if ( class_exists( 'SoapClient' ) ) {
			// We can continue, SOAP is installed.
			$this->soap_installed = true;
		}

		// $this->notify_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_PayNow', home_url( '/' ) ) );

		$this->id                 = 'paynow';
		$this->method_title       = __( 'Pay Now', 'woothemes' );
		$this->method_description = __( 'A payment gateway for South African payment system, Netcash Pay Now.', 'woothemes' );
		$this->icon               = $this->plugin_url() . '/assets/images/netcash.png';
		$this->has_fields         = true;
		$this->debug_email        = get_option( 'admin_email' );

		// Setup available countries.
		$this->available_countries = array(
			'ZA',
		);

		// Setup available currency codes.
		$this->available_currencies = array(
			'ZAR',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Setup default merchant data.
		$this->service_key = $this->settings ['service_key'];
		$this->url         = 'https://paynow.netcash.co.za/site/paynow.aspx';
		$this->title       = $this->settings ['title'];

		// Register support for subscriptions.
		$this->supports = array(
			'products',
			'subscriptions',

			// Not adding this flag can cause subscriptions to be incorrectly suspended when the gateway’s schedule
			// does not precede the WooCommerce schedule.
			'gateway_scheduled_payments', // The gateway handles schedule.

			// Note: If we can support token based billing without 3D-secure we can rely on Subscriptions’ scheduled payment
			// hooks to charge each recurring payment, we can then support all of the available features.

			// 'subscription_cancellation',
			// 'subscription_suspension',
			// 'subscription_reactivation',
			// 'subscription_amount_changes',
			// 'subscription_date_changes',
			// 'subscription_payment_method_change',
			// 'subscription_payment_method_change_customer',
			// 'subscription_payment_method_change_admin',
			// 'multiple_subscriptions',
		);

		add_action(
			'woocommerce_subscription_before_actions',
			array(
				$this,
				'show_paynow_subscription_management_notice',
			)
		);

		add_action(
			'paynow_request_validated',
			array(
				$this,
				'successful_request',
			)
		);

		/* 1.6.6 */
		add_action(
			'woocommerce_update_options_payment_gateways',
			array(
				$this,
				'custom_process_admin_options',
			)
		);

		/* 2.0.0 */
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'custom_process_admin_options',
			)
		);

		add_action(
			'woocommerce_receipt_paynow',
			array(
				$this,
				'receipt_page',
			)
		);

		// Add SOAP notices.
		add_action(
			'admin_notices',
			array(
				$this,
				'netcash_notice_urls',
			)
		);

		// Called via http://yoursite.com/?wc-api=paynowcallback.
		add_action(
			'woocommerce_api_paynowcallback',
			array(
				$this,
				'handle_return_url',
			)
		);

		if ( ! $this->soap_installed ) {
			// Add SOAP notices.
			add_action(
				'admin_notices',
				array(
					$this,
					'error_notice_soap',
				)
			);
		}

		// Check if the base currency supports this gateway.
		if ( ! $this->is_valid_for_use() || ! $this->cart_can_support_subscription_period() ) {
			$this->enabled = false;
		}
	}

	/**
	 * Alias for error_notice_general to show SOAP error message
	 */
	public static function error_notice_soap() {
		self::error_notice_general( "We've noticed that you <em>do not</em> have the PHP <a href=\"http://php.net/manual/en/book.soap.php\" target=\"_blank\">SOAP extension</a> installed. Without this extension, this module won't function." );
	}

	/**
	 * Alias for error_notice_general to show URL notices
	 */
	public static function netcash_notice_urls() {

		$url = home_url( '/' );

		$s = '';

		$msg  = '<strong>Netcash Connecter URLs:</strong><br>';
		$msg .= 'Use the following URLs:<br>';
		$msg .= "<strong>Accept</strong>, <strong>Decline</strong>, <strong>Notify</strong>, and <strong>Redirect</strong> URL: <code style='{$s}'>{$url}</code><br>";
		// $msg .= "<strong>Notify</strong> and <strong>Redirect</strong> URL: <code style='{$s}'>{$url2}</code>";
		self::error_notice_general( $msg, 'info' );
	}

	/**
	 * Show an error notice
	 *
	 * @param string $message The message to show.
	 * @param string $class   The notice class name.
	 */
	public static function error_notice_general( $message = '', $class = 'error' ) {
		?>
		<div class="notice notice-<?php echo $class; ?>">
			<p><strong>[Pay Now WooCommerce]</strong> <?php echo $message; ?></p>
		</div>
		<?php
	}

	/**
	 * Processes and saves options.
	 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
	 *
	 * @return bool was anything saved?
	 */
	public function custom_process_admin_options() {

		// NOTE: Not too sure how to show error messages to user as the 'process_admin_options' method.
		// simply skips errored (return false) fields and continues to save.
		// So, we're adding errors and then showing them (display_errors()).

		if ( ! $this->soap_installed ) {
			// Can't validate without SOAP.
			return false;
		}

		$post_data   = $this->get_post_data();
		$form_fields = $this->get_form_fields();

		// Let's check the account number first. If it's correct, validate the service key.
		// Otherwise, bail.
		$field_account_number = $form_fields['account_number'];
		$account_number       = $this->get_field_value( 'account_number', $field_account_number, $post_data );
		if ( ! $account_number ) {
			$this->add_error( '<strong>Account Number</strong> An account number is required.' );
		}

		// Valid account number.
		$field_service_key = $form_fields['service_key'];
		$service_key       = $this->get_field_value( 'service_key', $field_service_key, $post_data );
		if ( ! $service_key ) {
			$this->add_error( '<strong>Service Key</strong> A service key is required.' );
		}

		if ( empty( $this->get_errors() ) ) {
			// No errors thus far, so Validate Service Keys here.
			$validator = new Netcash\PayNow\KeysValidator();
			$validator->setVendorKey( '7f7a86f8-5642-4595-8824-aa837fc584f2' );

			try {
				$result = $validator->validatePaynowServiceKey( $account_number, $service_key );

				if ( true !== $result ) {
					$this->add_error( $result[ $service_key ] ? $result[ $service_key ] : "<strong>Service Key</strong> {$result}" );
				}
			} catch ( \Exception $e ) {
				$this->add_error( $e->getMessage() );
			}
		}

		if ( ! empty( $this->get_errors() ) ) {
			// Errors encountered. Return false.
			// NOTE: If users get 'Headers already sent issues', remove this line.
			$this->display_errors();
			return false;
		}

		return parent::process_admin_options();
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 *
	 * @since 1.0.0
	 */
	function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __( 'Enable/Disable', 'woothemes' ),
				'label'       => __( 'Enable Pay Now', 'woothemes' ),
				'type'        => 'checkbox',
				'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'woothemes' ),
				'default'     => 'yes',
			),
			'title'              => array(
				'title'       => __( 'Title', 'woothemes' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
				'default'     => __( 'Secure online Payments via Netcash', 'woothemes' ),
			),
			'description'        => array(
				'title'       => __( 'Description', 'woothemes' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woothemes' ),
				'default'     => 'Secure online Payments via Netcash',
			),
			'account_number'     => array(
				'title'       => __( 'Account Number', 'woothemes' ),
				'type'        => 'text',
				'description' => __( 'This is the Netcash Account Number, received from the Netcash website.', 'woothemes' ),
				'default'     => '',
			),
			'service_key'        => array(
				'title'       => __( 'Service Key', 'woothemes' ),
				'type'        => 'text',
				'description' => __( 'This is the Pay Now service key, received from the Netcash Connect Section on your Netcash Account.', 'woothemes' ),
				'default'     => '',
			),
			'send_email_confirm' => array(
				'title'   => __( 'Send Email Confirmations', 'woothemes' ),
				'type'    => 'checkbox',
				'label'   => __( 'An email confirmation will be sent from the Pay Now gateway to the client after each transaction.', 'woothemes' ),
				'default' => 'yes',
			),
			'do_tokenization'    => array(
				'title'   => __( 'Enable Credit Card Tokenization', 'woothemes' ),
				'type'    => 'checkbox',
				'label'   => __( 'If enabled, Netcash will return a Tokenized Credit Card value in the order notes.', 'woothemes' ),
				'default' => 'no',
			),
			'send_debug_email'   => array(
				'title'   => __( 'Enable Debug', 'woothemes' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send debug e-mails for transactions and creates a log file in WooCommerce log folder called netcashnow.log', 'woothemes' ),
				'default' => 'yes',
			),
			'debug_email'        => array(
				'title'       => __( 'Who Receives Debug Emails?', 'woothemes' ),
				'type'        => 'text',
				'description' => __( 'The e-mail address to which debugging error e-mails are sent when debugging is on.', 'woothemes' ),
				'default'     => get_option( 'admin_email' ),
			),
		);
	}

	/**
	 * Get the plugin URL
	 *
	 * @since 1.0.0
	 */
	function plugin_url() {
		if ( is_ssl() ) {
			$this->plugin_url = str_replace( 'http://', 'https://', WP_PLUGIN_URL ) . '/' . plugin_basename( dirname( dirname( __FILE__ ) ) );
		} else {
			$this->plugin_url = WP_PLUGIN_URL . '/' . plugin_basename( dirname( dirname( __FILE__ ) ) );
		}
		return $this->plugin_url;
	}

	/**
	 * Check if this gateway is enabled and available in the base currency being traded with.
	 *
	 * @since 1.0.0
	 */
	function is_valid_for_use() {

		$is_available  = false;
		$user_currency = get_option( 'woocommerce_currency' );

		$is_available_currency = in_array( $user_currency, $this->available_currencies, true );

		if ( $is_available_currency ) {
			$is_available = true;
		}

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
			?>
			<div class="inline error"><p><strong><?php _e( 'Gateway disabled', 'woocommerce' ); ?></strong>: <?php _e( 'Pay Now does not support your store currency.', 'woocommerce' ); ?></p></div>
			<?php
		}
	}

	/**
	 * There are no payment fields for Netcash Pay Now, but we want to show the description if set.
	 *
	 * @since 1.0.0
	 */
	function payment_fields() {
		if ( isset( $this->settings ['description'] ) && ( '' !== $this->settings ['description'] ) ) {
			echo wpautop( wptexturize( $this->settings ['description'] ) );
		}
	}

	/**
	 * Generate the Netcash Pay Now button link.
	 *
	 * @param int $order_id The WooCommerce Order ID.
	 *
	 * @return string
	 * @throws ReflectionException|\Netcash\PayNow\Exceptions\ValidationException
	 * @since 1.0.0
	 */
	public function generate_paynow_form( $order_id ) {
		global $woocommerce;

		$this->log( 'The Pay Now debugger started a new session' );

		$order = new WC_Order( $order_id );

		$customer_name = "{$order->get_billing_first_name()} {$order->get_billing_last_name()}";
		$customer_id   = $order->get_user_id();
		$netcash_guid  = '7f7a86f8-5642-4595-8824-aa837fc584f2';

		$should_tokenize = (bool) $this->settings['do_tokenization'];

		$form = new \Netcash\PayNow\Form( $this->settings ['service_key'] );

		$form->setField( 'm2', $netcash_guid );
		$form->setField( 'm3', $netcash_guid );

		$form->setOrderID( $order->get_id() );
		$form->setDescription( "{$customer_name} ({$order->get_order_number()})" );
		$form->setAmount( $order->get_total() );

		$form->setCellphone( $order->get_billing_phone() );
		$form->setEmail( $order->get_billing_email() );

		$form->setExtraField( $customer_id, 1 );
		$form->setExtraField( $order->get_cancel_order_url(), 2 );
		$form->setExtraField( $order->get_order_key(), 3 );

		$form->setReturnCardDetail( $should_tokenize );
		$form->setReturnString( 'wc-api=paynowcallback' );

		// Subscription?.
		if ( is_woocommerce_subscriptions_active() ) {
			if ( WC_Subscriptions_Order::order_contains_subscription( $order_id ) ) {
				$this->log( 'Order contains a subscription' );

				$subscriptions      = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'parent' ) );
				$first_subscription = $subscriptions && count( $subscriptions ) ? array_shift( $subscriptions ) : null;

				if ( ! $first_subscription ) {
					throw new \Exception( 'Expected subscription.' );
				}

				$subscription_start = gmdate( 'Y-m-d', $first_subscription->get_time( 'next_payment', 'site' ) );
				$period             = $first_subscription->get_billing_period();

				$subscription_interval = (int) $first_subscription->get_billing_interval(); //wcs_cart_pluck( WC()->cart, 'subscription_interval' ); // (int) WC_Subscriptions_Product::get_interval( $post_id );

				// Initial payment and sign up fee is already taken into account in $order->get_total().
				$price_per_period          = WC_Subscriptions_Order::get_recurring_total( $order );
				$subscription_installments = wcs_cart_pluck( WC()->cart, 'subscription_length' );

				// We have a recurring payment.
				$infinite_installments = intval( $subscription_installments ) === 0;

				if ( intval( $subscription_installments ) > 1 || $infinite_installments ) {
					$form->setIsSubscription( true );

					// Set a better description for subscriptions.
					$desc = trim(
						strip_tags(
							sprintf(
								'Subsr %s. %s now. Then %s',
								$order->get_order_number(),
								wc_price(
									$order->get_total(),
									array(
										'currency'     => $order->get_currency(),
										'ex_tax_label' => false,
									)
								),
								$first_subscription->get_formatted_order_total()
							)
						)
					);

					$form->setDescription( substr( $desc, 0, 50 ) ); // Pay Now limits to 50 chars.

					switch ( strtolower( $period ) ) {
						case 'day':
							// $form->setSubscriptionFrequency(\Netcash\PayNow\SubscriptionFrequency::DAILY);
							throw new \Exception( "Unsupported Pay Now Frequency '{$period}'." );
							break;
						case 'week':
							if ($subscription_interval === 2) {
								$form->setSubscriptionFrequency( \Netcash\PayNow\Types\SubscriptionFrequency::BI_WEEKLY );
							} else {
								$form->setSubscriptionFrequency( \Netcash\PayNow\Types\SubscriptionFrequency::WEEKLY );
							}
							break;
						case 'year':
							$form->setSubscriptionFrequency( \Netcash\PayNow\Types\SubscriptionFrequency::ANNUALLY );
							break;
						case 'month':
							// fall through
						default:
							if ($subscription_interval === 6) {
								$form->setSubscriptionFrequency( \Netcash\PayNow\Types\SubscriptionFrequency::SIX_MONTHLY );
							} elseif ($subscription_interval === 4) {
								$form->setSubscriptionFrequency( \Netcash\PayNow\Types\SubscriptionFrequency::QUARTERLY );
							} else {
								$form->setSubscriptionFrequency( \Netcash\PayNow\Types\SubscriptionFrequency::MONTHLY );
							}
							break;
					}

					$form->setSubscriptionStartDate( $subscription_start );
					$form->setSubscriptionAmount( $price_per_period );
					$form->setSubscriptionCycle( $subscription_installments );

					$subscription_data = array(
						'amount_now'       => $form->getField( \Netcash\PayNow\Types\FieldType::AMOUNT ),
						'description'      => $form->getField( \Netcash\PayNow\Types\FieldType::DESCRIPTION ),
						'start'            => $form->getField( \Netcash\PayNow\Types\FieldType::SUBSCRIPTION_START_DATE ),
						'recurring_amount' => $form->getField( \Netcash\PayNow\Types\FieldType::SUBSCRIPTION_RECURRING_AMOUNT ),
						'frequency'        => $form->getField( \Netcash\PayNow\Types\FieldType::SUBSCRIPTION_FREQUENCY ),
						'cycles'           => $form->getField( \Netcash\PayNow\Types\FieldType::SUBSCRIPTION_CYCLE ),
					);

					$this->log( 'Subscription set.' );
					$this->log( implode( '\r\n', $subscription_data ) );
				}
			}
		}

		// Output the HTML form.
		$the_form = $form->makeForm( true, __( 'Pay via Pay Now', 'woothemes' ) );

		$this->log( 'Netcash Pay Now form post paynow_args_array: ' . print_r( $form->getFields(), true ) );

		return $the_form . '<a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'woothemes' ) . '</a>
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{
								message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" />' . __( 'Thank you for your order. We are now redirecting you to Netcash Pay Now to make payment.', 'woothemes' ) . '",
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
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 *
	 * @since 1.0.0
	 */
	function process_payment( $order_id ) {
		$order = new WC_Order( $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Receipt page.
	 *
	 * Display text and a button to direct the user to Pay Now.
	 *
	 * @param WC_Order $order The order.
	 *
	 * @since 1.0.0
	 */
	function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with Pay Now.', 'woothemes' ) . '</p>';
		echo $this->generate_paynow_form( $order );
	}

	/**
	 * Check Pay Now IPN response.
	 *
	 * @since 1.0.0
	 *
	 * @return true|string True on success. An "PN_ERROR_" message on failure
	 */
	function check_ipn_response() {
		$this->log( 'check_ipn_response starting' );

		$paynow   = new Netcash\PayNow\PayNow();
		$response = new Netcash\PayNow\Response( $_POST );

		$order_id  = esc_attr( $response->getOrderID() );
		$order     = new WC_Order( $order_id );
		$order_key = esc_attr( $response->getExtra( 3 ) );

		if ( ! $paynow->validateResponse( $_POST, $order->get_id(), $order->get_total() ) ) {
			$this->log( 'OK:valid Pay Now response' );

			$completed_statusses = array( 'completed', 'processing' );

			if ( in_array( $order->get_status(), $completed_statusses, true ) ) {
				$msg = 'Order has already been completed/processed. Current status: ' . $order->get_status();
				$this->log( $msg );
				return self::PN_ERROR_ORDER_ALREADY_HANDLED;
			}

			if ( $order->get_order_key() !== $order_key ) {
				$this->log( 'Order key object: ' . $order->get_order_key() );
				$this->log( 'Order key variable: ' . $order_key );
				$this->log( 'order->order_key != order_key so exiting' );
				return self::PN_ERROR_KEY_MISMATCH;
			}

			do_action( 'paynow_request_validated', $response->getData() );
		} else {
			$this->log( 'System failed checking ipn_request_valid' );
			return self::PN_ERROR_GENERAL_ERROR;
		}

		return true;
	}

	/**
	 * Successful Payment!
	 *
	 * @param array $posted The posted array.
	 *
	 * @since 1.0.0
	 * @throws \Exception If order is already completed.
	 */
	function successful_request( $posted ) {
		$this->log( 'successful_request is called' );

		$response = new Response( $posted );

		$order_id = $response->getOrderID();
		$order    = new WC_Order( $order_id );
		// $order_key = $response->getExtra(3);

		if ( $order->get_status() === 'completed' ) {
			throw new \Exception( 'order exists...' );
		}

		$cancel_redirect_url = $response->getExtra( 2 );

		$this->log( '- current order status: ' . $order->get_status() );
		$order_return_url = $this->get_return_url( $order );

		if ( $response->isPending() ) {
			// Still waiting... (E.g., EFT, or in-store).
			// Mark order as "Pending" payment.
			$order->add_order_note( __( 'Netcash response received. Payment pending', 'woothemes' ) );
			$order->update_status( self::ORDER_STATUS_PENDING, 'Pending payment' );
		} else {

			$order->add_order_note( __( 'IPN payment completed', 'woothemes' ) );

			// An actual request.
			if ( $response->wasDeclined() || $response->wasCancelled() ) {

				$order->add_order_note( __( 'Payment was cancelled or declined', 'woothemes' ) );

				if ( is_woocommerce_subscriptions_active() ) {
					// A subscription’s status does not change when a payment fails. However, you should still record failed payments.
					$subscriptions_in_order = WC_Subscriptions_Order::get_recurring_items( $order );
					$subscription_item      = array_pop( $subscriptions_in_order );
					$subscription_key       = WC_Subscriptions_Manager::get_subscription_key( $order->get_id(), $subscription_item['id'] );
					WC_Subscriptions_Manager::process_subscription_payment_failure( $order->customer_user, $subscription_key );
				}

				if ( $response->wasDeclined() ) {
					$this->log( "\t Transaction declined" );
					// translators: Reason is from gateway.
					$reason = sprintf(
						'Payment failure reason "%s".',
						strtolower( $response->getReason() )
					);
					$order->update_status( self::ORDER_STATUS_FAILED, $reason );
				}
				if ( $response->wasCancelled() ) {
					$this->log( "\t Transaction cancelled" );
					// If the user cancelled, redirect to cancel URL.
					$this->log( 'Order cancelled by user.' );
					$order_return_url = html_entity_decode( $order->get_cancel_order_url() );
					$order->update_status( self::ORDER_STATUS_CANCELLED, __( 'Payment canceled by user.', 'woothemes' ) );
				}
			} elseif ( $response->wasAccepted() ) {
				$this->log( "\t Transaction accepted" );

				$subscription_key = null;
				$is_subscription_payment = false;
				$first_subscription_payment = false;

				if ( is_woocommerce_subscriptions_active() ) {
					if ( WC_Subscriptions_Order::order_contains_subscription( $order ) ) {
						$is_subscription_payment = true;

						$subscriptions_in_order = WC_Subscriptions_Order::get_recurring_items( $order );
						$subscription_item      = array_pop( $subscriptions_in_order );
						$subscription_key       = WC_Subscriptions_Manager::get_subscription_key( $order->get_id(), $subscription_item['id'] );
						$subscription           = WC_Subscriptions_Manager::get_subscription( $subscription_key, $order->customer_user );
						// First payment on order, process payment & activate subscription
						if(empty( $subscription['completed_payments'] )) {
							$first_subscription_payment = true;
						}
					}
				}

				if($is_subscription_payment) {
					if($first_subscription_payment) {
						$this->log( "\t First subscription payment recorded for order {$order_id}." );
						// First payment on order, process payment & activate subscription
						$order->payment_complete( $transaction_id );
						WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
					} else {
						$this->log( "\t Subsequent subscription payment recorded for order {$order_id}." );
						// Subsequent subscription payments are recorded here
						// https://docs.woocommerce.com/document/subscriptions/develop/payment-gateway-integration/#recording-payments
//						WC_Subscriptions_Manager::process_subscription_payment( $order->customer_user, $subscription_key );
						WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
						$order->add_order_note( "Subscription payment recorded." );
					}
				} else {
					// Regular order payment
					$order->payment_complete( $transaction_id );
				}

				if ( $response->wasCreditCardTransaction() ) {
					// It was a CC transaction.
					if ( isset( $posted['ccHolder'] ) ) {
						// We have CC detail.
						$cc_detail  = "Tokenized credit card detail: \r\n";
						$cc_detail .= "Credit card name: {$posted['ccHolder']} \r\n";
						$cc_detail .= "Credit card number: {$posted['ccMasked']} \r\n";
						$cc_detail .= "Expiry date: {$posted['ccExpiry']} \r\n";
						$cc_detail .= "Card token: {$posted['ccToken']} \r\n";

						// Translators: Add CC detail as note.
						$order->add_order_note( $cc_detail );
					} else {
						$order->add_order_note( __( 'Paid with credit card but tokenized detail was not received.', 'woothemes' ) );
					}
				}
			} else {
				$this->log( "\t Transaction status could not be determined..." );

				// No status detected. Default to on hold.
				// Translators: Reason is text from Gateway.
				$reason = sprintf( __( 'Payment failure reason "%s".', 'woothemes' ), strtolower( $response->getReason() ) );
				$order->update_status( self::ORDER_STATUS_ON_HOLD, $reason );

				wp_redirect( $cancel_redirect_url );
				echo "<script>window.location='$cancel_redirect_url'</script>";
				exit();
			}
		}

		$this->log( "Redirecting to $order_return_url" );
		// WordPress redirect.
		wp_redirect( $order_return_url );
		// JavaScript redirect.
		echo "<script>window.location='$order_return_url'</script>";
		exit();
	}

	/**
	 * Log system processes.
	 *
	 * @param string $message The log message.
	 *
	 * @since 1.0.0
	 */
	function log( $message, $extra = [] ) {
		if ( ( 'yes' !== $this->settings['send_debug_email'] && ! is_admin() ) ) {
			return;
		}

		$date = date('Y-m-d');
		$DIR = realpath(dirname(__FILE__) . "/../logs");
		$FILE = $DIR . "/general-{$date}.log";

		$fh = null;
		// If file doesn't exist, create it
		if (!$fh) {
			if( !is_dir($DIR) ) {
				@mkdir($DIR);
			}
			$fh = fopen ( $FILE, 'a+' );
		}

		if (!is_writeable($FILE)) {
			error_log($message);
			return;
		}

		// If file was successfully opened
		if ($fh) {
			if( is_bool($extra) ) {
				$data_string = " | " . ($extra ? 'True' : 'False');
			} elseif( is_string($extra) ) {
				$data_string = " | " . $extra;
			} else {
				$data_string = empty( $extra ) ? "" : " | " . print_r( $extra, true );
			}
			$line = sprintf("%s: %s %s \n",
				date( 'Y-m-d H:i:s' ),
				$message,
				$data_string
			);

			if ( false === fwrite ( $fh, $line ) ) {
				error_log($message);
				return;
			}

			fclose ( $fh );
		}

	}

	/**
	 * Handles the 'callback' URL from Netchash. Called with http://yoursite.com?wc-api=paynowcallback
	 * The old "paynow_callback.php" file
	 */
	public function handle_return_url() {
		$url_for_redirect  = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );
		$url_for_redirect .= '/my-account/';
		$this->log( 'handle_return_url POST: ' . print_r( $_REQUEST, true ) );

		$response    = new Netcash\PayNow\Response( $_POST );
		$was_offline = $response->wasOfflineTransaction();

		$this->log( 'handle_return_url IS OFFLINE? ' . ( $was_offline ? 'Yes' : 'No' ) );

		if ( isset( $_POST ) && ! empty( $_POST ) ) {

			// This is the notification coming in!
			// Act as an IPN request and forward request to Credit Card method.
			// Logic is exactly the same.

			$paynow                    = new WC_Gateway_PayNow();
			$validation_success_or_msg = $paynow->check_ipn_response();

			if ( true !== $validation_success_or_msg ) {
				// Oops. Something went wrong.
				$redirect_url = isset( $_POST['Extra2'] ) ? $_POST['Extra2'] : $url_for_redirect;

				$this->log( 'Something went wrong! Redirecting to order cancelled.' );
				$this->log( $validation_success_or_msg );

				// Validation failed because the order has been completed.
				// But if this is a pending request, just redirect to the order page.
				$is_pending = $response->isPending();
				if ( $is_pending ) {
					$order_id     = $response->getOrderID();
					$order        = new WC_Order( $order_id );
					$redirect_url = $this->get_return_url( $order );
				}

				/*
				Should order status be changed?
				if ( self::PN_ERROR_ORDER_ALREADY_HANDLED !== $validation_success_or_msg ) {
				$order->update_status( self::ORDER_STATUS_FAILED, $validation_success_or_msg );
				}
				*/

				if ( $redirect_url ) {
					wp_redirect( $redirect_url );
				}
			}
		} else {

			// Probably calling the "redirect" URL.
			$this->log( 'handle_return_url Probably calling the "redirect" URL' );

			if ( $url_for_redirect ) {
				header( "Location: {$url_for_redirect}" );
			} else {
				die( "No 'redirect' URL set." );
			}
		}
	}

	/**
	 * Show a notice regarding managing subscriptions.
	 *
	 * @param WC_Subscription $subscription The subscription.
	 *
	 * @return void
	 */
	public static function show_paynow_subscription_management_notice( $subscription ) {
		$admin_email = get_option( 'admin_email' );

		if ( 'paynow' !== $subscription->get_payment_method() ) {
			return;
		}
		?>
		<tr>
			<td colspan="2">
				To cancel or manage your subscription, please <a href="mailto:<?php echo $admin_email; ?>">contact us</a> or
				visit the <a target="_blank" href="https://netcash.co.za/">Netcash Pay Now</a> website.
			</td>
		</tr>
		<?php
	}

	/**
	 * Check whether an individual subscription can be supported via Pay Now
	 *
	 * @param int $post_id The subscription product/post id
	 *
	 * @return bool|string|null True if it is supported. A string with the error message if it is not supported.
	 *                          Null if it is not a subscription product.
	 */
	public static function is_subscription_supported( $post_id ) {
		if ( ! $post_id || ! WC_Subscriptions_Product::is_subscription( $post_id ) ) {
			// Not a subscription
			return null;
		}

		// $subscription = new WC_Product_Subscription( $post_id );
		$subscription_interval = (int) WC_Subscriptions_Product::get_interval( $post_id );
		$subscription_period   = WC_Subscriptions_Product::get_period( $post_id );
		$subscription_length   = (int) WC_Subscriptions_Product::get_length( $post_id );

		$product_title = get_the_title( $post_id );

		$reason    = '';
		$supported = true;
		if ( $subscription_length === 0 ) {
			// Does not support infinite length
			// TODO: Set to 9999?
			$supported = false;
			$reason    = __( "Infinite subscription lengths are not supported for {$product_title}.", 'paynow' );
		}
		if ( $subscription_period === 'day' ) {
			// Does not support day
			$supported = false;
			$reason    = __( "The period '{$subscription_period}' is an unsupported subscription period for '{$product_title}'.", 'paynow' );
		}

		if ($subscription_period === 'week' && $subscription_interval > 2) {
			// Only supports every week or every 2 weeks
			$supported = false;
			$reason    = __( "Every '{$subscription_interval}' weeks is an unsupported subscription cycle for '{$product_title}'.", 'paynow' );
		}

		if ($subscription_period === 'month' && !in_array($subscription_interval, array(1, 4, 6) )) {
			// Only supports every month, every 4 months (quarterly), or every 6 months
			$supported = false;
			$reason    = __( "Every '{$subscription_period}' months is an unsupported subscription cycle for '{$product_title}'.", 'paynow' );
		}

		if ( ! $supported ) {
			return $reason;
		}

		return true;
	}

	/**
	 * Check whether the cart supports the subscription models
	 *
	 * @return bool
	 */
	function cart_can_support_subscription_period() {

		if ( ! is_woocommerce_subscriptions_active() ) {
			// No need to run if subscriptions plugin isn't active.
			return true;
		}

		if ( is_admin() ) {
			// Not in backend (admin).
			return true;
		}

		$subscription_id = null;

		if (WC()->cart) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( isset( $item['product_id'] ) ) {
					if ( WC_Subscriptions_Product::is_subscription( $item['product_id'] ) ) {
						$subscription_id = $item['product_id'];
						break;
					}
				}
			}
		}

		$supported_or_reason = self::is_subscription_supported( $subscription_id );

		if ( true !== $supported_or_reason ) {
			// unset( $available_gateways['paynow'] );
			if (function_exists('wc_add_notice')) {
				wc_add_notice( __( "Pay Now: {$supported_or_reason}", 'paynow' ), 'error' );
			}
			$this->log( 'Pay Now payment method removed from cart due to "' . $supported_or_reason . '"' );
			return false;
		}

		return true;
	}

	/**
	 * Hook into the WordPress save hook
	 *
	 * @param int $post_id The post id
	 */
	public static function admin_show_unsupported_message( $post_id = null, $post = null, $update = null ) {

		if ( ! is_woocommerce_subscriptions_active() ) {
			// No need to run if subscriptions plugin isn't active.
			return;
		}

		if ( ! $post_id ) {
			$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : null;
		}

		if ( ! is_admin() ) {
			return;
		}

		if ( ! $post_id || ! WC_Subscriptions_Product::is_subscription( $post_id ) ) {
			// Not a subscription
			return;
		}

		$supported_or_reason = self::is_subscription_supported( $post_id );

		if ( true !== $supported_or_reason ) {
			$message  = "Please note Pay Now is not supported for subscription {$post_id} due to the following:\r\n";
			$message .= "{$supported_or_reason}";
			add_action(
				'admin_notices',
				function() use ( $message ) {
					WC_Gateway_PayNow::error_notice_general( $message );
				}
			);
		}

	}
}
