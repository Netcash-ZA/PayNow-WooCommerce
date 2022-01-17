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
	public $version = '4.0.5';

	/**
	 * The gateway name / id.
	 *
	 * @var string
	 */
	public $id = 'paynow';

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

		$this->id = 'paynow';
		// $this->notify_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_PayNow', home_url( '/' ) ) );
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

			// Not adding this flag can cause subscriptions to be incorrectly suspended when the gateway’s schedule does not precede the WooCommerce schedule.
			'gateway_scheduled_payments', // The gateway handles schedule.,
			'subscription_payment_method_change', // gateway is presented as a payment option when the customer is changing the payment;
			// 'subscription_payment_method_change_admin',
			// 'subscription_payment_method_change_customer',
			// Note: If we can support token based billing without 3D-secure we can rely on Subscriptions’ scheduled payment hooks to charge each recurring payment, we can then support all of the available features.

			// Required to put 'on-hold'
			'subscription_suspension',
			'subscription_reactivation',
			// 'subscription_cancellation',
			// 'subscription_amount_changes',
			// 'subscription_date_changes',
			// 'subscription_payment_method_change',
			// 'subscription_payment_method_change_customer',
			// 'subscription_payment_method_change_admin',
			// 'multiple_subscriptions',
		);

		// For WooCommerce Subscriptions
		// to update the payment method when a customer is making a payment in lieu of an automatic renewal payment that
		// previously failed.
		add_action(
			'woocommerce_subscription_failing_payment_method_updated_' . $this->id,
			array(
				$this,
				'update_failing_payment_method',
				10,
				2,
			)
		);
		add_action(
			'handle_subscription_renewal_payment_failed',
			array(
				$this,
				'woocommerce_subscription_renewal_payment_failed',
				10,
				2,
			)
		);
		add_action(
			'woocommerce_scheduled_subscription_payment_' . $this->id,
			array(
				$this,
				'handle_scheduled_subscription_payment',
			),
			10,
			2
		);
		add_action(
			'updated_users_subscription',
			array(
				$this,
				'handle_updated_users_subscription',
			),
			10,
			2
		);

		add_action(
			'woocommerce_subscription_before_actions',
			array(
				$this,
				'show_paynow_subscription_management_notice',
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

		$is_settings_page  = isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'];
		$is_checkout_tab   = isset( $_GET['tab'] ) && 'checkout' === $_GET['tab'];
		$is_paynow_section = isset( $_GET['section'] ) && 'paynow' === $_GET['section'];

		if ( ! $is_settings_page || ! $is_checkout_tab || ! $is_paynow_section ) {
			// Only show on Pay Now settings page
			return;
		}

		$url        = home_url( '/' );
		$plugin_url = str_replace( 'includes/', '', plugin_dir_url( __FILE__ ) );

		$s = '';

		$msg = '<strong>Netcash Connecter URLs:</strong><br>';
		// $msg .= 'Use the following URLs:<br>';
		$msg .= "<strong>Accept</strong>, <strong>Decline</strong>, and <strong>Redirect</strong> URL: <code style='{$s}'>{$url}</code><br>";
		$msg .= "<strong>Notify</strong> URL: <code style='{$s}'>{$plugin_url}notify-callback.php</code><br>";
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
	 * @throws ReflectionException|\Netcash\PayNow\Exceptions\ValidationException When validation fails.
	 * @throws \Exception When exception occurs.
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

		$form->setOrderID( $order->get_id() );
		$form->setDescription( "{$customer_name} ({$order->get_order_number()})" );
		$form->setAmount( $order->get_total() );
			
		// Show Budget period dropdown on gateway
		$form->setBudget( true );

		try {
			$form->setCellphone( $order->get_billing_phone() );
		} catch(\Exception $e) {
			// invalid phone number
		}

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

				$subscription_interval = (int) $first_subscription->get_billing_interval(); // wcs_cart_pluck( WC()->cart, 'subscription_interval' ); // (int) WC_Subscriptions_Product::get_interval( $post_id );

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
							$form->setSubscriptionFrequency( \Netcash\PayNow\Types\SubscriptionFrequency::DAILY );
							// throw new \Exception( "Unsupported Pay Now Frequency '{$period}'." );
							break;
						case 'week':
							if ( 2 === $subscription_interval ) {
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
							if ( 6 === $subscription_interval ) {
								$form->setSubscriptionFrequency( \Netcash\PayNow\Types\SubscriptionFrequency::SIX_MONTHLY );
							} elseif ( 4 === $subscription_interval ) {
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

		if ( is_woocommerce_subscriptions_active() && wcs_is_subscription( $order_id ) ) {
			// When your gateway’s process_payment() is called with the ID of a subscription, it means the request is to change the payment method on the subscription.
		}

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
		$this->log( 'check_ipn_response called' );

		$paynow   = new Netcash\PayNow\PayNow();
		$response = new Netcash\PayNow\Response( $_POST );
		$order_id  = esc_attr( $response->getOrderID() );
		$order     = new WC_Order( $order_id );
		$order_key = esc_attr( $response->getExtra( 3 ) );

		// We're always going to get the _original_ order ID for our IPN requests.
		$order_total = $order->get_total();
		$is_subscription = is_woocommerce_subscriptions_active() ? WC_Subscriptions_Order::order_contains_subscription( $order->get_id() ) : false;

		$this->log(
			'check_ipn_response',
			array(
				'response_order_id' => $response->getOrderID(),
				'response_amount'   => $response->getAmount(),

				'order_order_id' 	=> $order->get_id(),
				'order_amount'		=> $order_total,

				'is_subscription' 	=> $is_subscription ? 'Yes' : 'No'
			)
		);

		if ( $is_subscription ) {
			$this->log( 'check_ipn_response - is subscription ' );
			$subscriptions_in_order     = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'parent' ) ); // WC_Subscriptions_Order::get_recurring_items( $order );
			$last_subscription_id       = key( $subscriptions_in_order ); // The last subscription item id
			$last_subscription          = new WC_Subscription( $last_subscription_id );
			$subscription_payment_count = $last_subscription->get_payment_count(); // WC_Subscriptions_Manager::get_subscriptions_completed_payment_count( $subscription_key );

			if ( $subscription_payment_count > 0 ) {
				// Subsequent payment
				$this->log( 'check_ipn_response - is subsequent payment' );
				$order_total = WC_Subscriptions_Order::get_recurring_total( $order );
			}
		}

		if ( !$paynow->checkEqualAmounts($response->getAmount(), $order_total) ) {
			$msg = 'Order and response amount mismatch';
			$this->log( $msg );
			return self::PN_ERROR_AMOUNT_MISMATCH;
		}

		if ( strval($response->getOrderID()) !== strval($order->get_id()) ) {
			$msg = 'Order and response ID mismatch';
			$this->log( $msg );
			return self::PN_ERROR_KEY_MISMATCH;
		}

		$completed_statusses = array( 'completed', 'processing' );
		if ( ! $is_subscription && in_array( $order->get_status(), $completed_statusses, true ) ) {
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

		$order_id       = $response->getOrderID();
		$transaction_id = $posted['RequestTrace'];

		$order = new WC_Order( $order_id );
		// $order_return_url = $this->get_return_url( $order );
		// $order_key = $response->getExtra(3);

		if ( $order->get_status() === 'completed' ) {
			throw new \Exception( 'order exists...' );
		}

		// $cancel_redirect_url = $response->getExtra( 2 );

		$this->log( '- current order status: ' . $order->get_status() );

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
					// $subscriptions_in_order = WC_Subscriptions_Order::get_recurring_items( $order );
					// $subscription_item      = array_pop( $subscriptions_in_order );
					// $subscription_key       = WC_Subscriptions_Manager::get_subscription_key( $order->get_id(), $subscription_item['id'] );
					// WC_Subscriptions_Manager::process_subscription_payment_failure( $order->customer_user, $subscription_key );
					WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order );
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
					// $order_return_url = html_entity_decode( $order->get_cancel_order_url() );
					$order->update_status( self::ORDER_STATUS_CANCELLED, __( 'Payment canceled by user.', 'woothemes' ) );
				}
			} elseif ( $response->wasAccepted() ) {
				$this->log( "\t Transaction accepted" );

				// $subscription_key           = null;
				$is_subscription_payment    = false;
				$first_subscription_payment = false;

				if ( is_woocommerce_subscriptions_active() ) {
					if ( WC_Subscriptions_Order::order_contains_subscription( $order ) ) {
						$is_subscription_payment = true;

						$subscriptions_in_order = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'parent' ) ); // WC_Subscriptions_Order::get_recurring_items( $order );

						$subscription_item    = end( $subscriptions_in_order ); // Get the last item.
						$last_subscription_id = key( $subscriptions_in_order ); // The last subscription item id.

						// $subscription_key     = WC_Subscriptions_Manager::get_subscription_key( $order->get_id(), $subscription_item->get_id() );
						// $subscription_key           = WC_Subscriptions_Manager::get_subscription_key( $order->get_id() );
						$last_subscription          = new WC_Subscription( $last_subscription_id );
						$subscription_payment_count = $last_subscription->get_payment_count(); // WC_Subscriptions_Manager::get_subscriptions_completed_payment_count( $subscription_key );

						// First payment on order, process payment & activate subscription.
						if ( ! $subscription_payment_count ) {
							$first_subscription_payment = true;
						} else {
							// Check if there are renewal orders.
							// Create a renewal order if it doesn't yet exist.
							// see woocommerce-subscriptions/includes/class-wc-subscriptions-renewal-order.php:221.
							$last_subscription = new WC_Subscription( $last_subscription_id );

							// Make sure the subscription is set back to auto-renewal again.
							// (Might have been set to manual because of failed payment.)
							if ( $last_subscription && $last_subscription->get_requires_manual_renewal() ) {
								$this->log( "\t Set to manually renew. {$last_subscription_id}" );
								$last_subscription->set_requires_manual_renewal( false );
								$last_subscription->save();
							}

							// Put it on hold temporarily. It will be reactivated shortly.
							// when WC_Subscriptions_Manager::process_subscription_payments_on_order is called.
							// If we don't put it on hold, the renewal date isn't updated and no "woocommerce_scheduled_subscription_payment" scheduled action is created.
							try {
								$last_subscription->update_status( 'on-hold' );
							} catch ( \Exception $e ) {
								// Skip
								$this->log( "\t Failed to set subscription #{$last_subscription_id} to 'on-hold'." );
							}

							$renewal_order = wcs_create_renewal_order( $last_subscription );
							$renewal_order->set_payment_method( $this->id );
							$renewal_order->save();

							if ( is_wp_error( $renewal_order ) ) {
								$this->log( "\t Failed to created a renewal order for subscription: {$subscription_item->get_id()}. Message: {$renewal_order->get_error_message()}" );
							} else {
								$this->log( "\t Created a renewal order for subscription: {$subscription_item->get_id()}." );
							}
						}
					}
				}

				if ( $is_subscription_payment ) {
					if ( $first_subscription_payment ) {
						$this->log( "\t First subscription payment recorded for order {$order_id}." );
						// First payment on order, process payment & activate subscription.
						$order->payment_complete( $transaction_id );
						WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
					} else {
						$this->log( "\t Subsequent subscription payment recorded for order {$order_id}." );
						// Subsequent subscription payments are recorded here.
						// https://docs.woocommerce.com/document/subscriptions/develop/payment-gateway-integration/#recording-payments.
						// WC_Subscriptions_Manager::process_subscription_payment( $order->customer_user, $subscription_key );
						WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
						$order->add_order_note( 'Subscription payment recorded.' );
					}
				} else {
					// Regular order payment.
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
			}
		}
	}

	/**
	 * Log system processes.
	 *
	 * @param string $message The log message.
	 * @param array  $extra  Additional log data.
	 *
	 * @since 1.0.0
	 */
	public static function log( $message, $extra = array() ) {
		$date = gmdate( 'Y-m-d' );
		$dir  = realpath( dirname( __FILE__ ) . '/../logs' );
		$file = $dir . "/general-{$date}.log";

		$fh = null;
		// If file doesn't exist, create it.
		if ( ! $fh ) {
			if ( ! is_dir( $dir ) ) {
				@mkdir( $dir );
			}
			$fh = fopen( $file, 'a+' );
		}

		if ( ! is_writeable( $file ) ) {
			error_log( $message );
			return;
		}

		// If file was successfully opened.
		if ( $fh ) {
			if ( is_bool( $extra ) ) {
				$data_string = ' | ' . ( $extra ? 'True' : 'False' );
			} elseif ( is_string( $extra ) ) {
				$data_string = ' | ' . $extra;
			} else {
				$data_string = empty( $extra ) ? '' : ' | ' . print_r( $extra, true );
			}
			$line = sprintf(
				"%s: %s %s \n",
				gmdate( 'Y-m-d H:i:s' ),
				$message,
				$data_string
			);

			if ( false === fwrite( $fh, $line ) ) {
				error_log( $message );
				return;
			}

			fclose( $fh );
		}

	}

	/**
	 * Handles the 'callback' URL from Netchash. Called with http://yoursite.com?wc-api=paynowcallback
	 * The old "paynow_callback.php" file
	 */
	public function handle_return_url() {
		// This is for accept/decline and redirect.
		// The Notify URL will go to ../notify-callback.php which will manage WooCommerce.
		// Here, we just redirect the user.

		$response = new Netcash\PayNow\Response( $_POST );
		$this->log(
			'handle_return_url ',
			array(
				'order'        => $response->getOrderID(),
				'offline'      => $response->wasOfflineTransaction() ? 'Yes' : 'No',

				'wasAccepted'  => $response->wasAccepted() ? 'Yes' : 'No',
				'isPending'    => $response->isPending() ? 'Yes' : 'No',
				'wasDeclined'  => $response->wasDeclined() ? 'Yes' : 'No',
				'wasCancelled' => $response->wasCancelled() ? 'Yes' : 'No',
			)
		);

		$redirect_url = '';
		$notice = null;

		$order_id = esc_attr( $response->getOrderID() );
		$order    = new WC_Order( $order_id );

		if ( $response->wasAccepted() ) {
			// Just redirect to success. The IPN request will trigger the payment success and order status changes.
			$redirect_url = $this->get_return_url( $order );
		} else {
			// Oops. Something went wrong.
			if ( $response->wasCancelled() ) {
				$order->update_status( 'cancelled', 'Cancelled by customer.' );
				$redirect_url = html_entity_decode( $order->get_cancel_order_url() );
				$notice = [ "Your transaction has been cancelled.", 'notice' ];
			} else {
				$redirect_url = isset( $_POST['Extra2'] ) ? $_POST['Extra2'] : '';
			}

			if($response->wasDeclined()) {
				$reason = $response->getReason();
				$notice = [ "Your transaction was unsuccessful. Reason: {$reason}", 'error' ];
			}

			// Validation failed because the order has been completed.
			// But if this is a pending request, just redirect to the order page.
			if ( $response->isPending() ) {
				$redirect_url = $this->get_return_url( $order );
			}
		}

		if ( ! $redirect_url ) {
			// Probably calling the "redirect" URL.
			$this->log( 'handle_return_url Probably calling the "redirect" URL' );
			$redirect_url  = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );
			$redirect_url .= '/my-account/';
		}

		// Append any notices to redirect URL which we'll show after the redirect
		if($notice) {
			$has_query = parse_url($redirect_url, PHP_URL_QUERY);
			$notice_query = http_build_query([
				'pnotice'=>urlencode($notice[0]),
				'ptype'=>$notice[1]
			]);
			$redirect_url .= ($has_query ? '&' : '?') . $notice_query;
		}

		$this->log( 'handle_return_url Redirecting to ' . $redirect_url );

		// WordPress redirect.
		wp_redirect( $redirect_url );
		// JavaScript redirect.
		echo "<script>window.location='$redirect_url'</script>";
		exit();

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
	 * @param int $post_id The subscription product/post id.
	 *
	 * @return bool|string|null True if it is supported. A string with the error message if it is not supported.
	 */
	public static function is_subscription_supported( $post_id ) {
		if ( ! $post_id || ! WC_Subscriptions_Product::is_subscription( $post_id ) ) {
			// Not a subscription.
			return true;
		}

		// $subscription = new WC_Product_Subscription( $post_id );.
		$subscription_interval = (int) WC_Subscriptions_Product::get_interval( $post_id );
		$subscription_period   = WC_Subscriptions_Product::get_period( $post_id );
		$subscription_length   = (int) WC_Subscriptions_Product::get_length( $post_id );

		$product_title = get_the_title( $post_id );
		$reason        = '';
		$supported     = true;
		if ( 0 === $subscription_length ) {
			// Does not support infinite length.
			// TODO: Set to 9999?.
			$supported = false;
			/* translators: %s is the product_title */
			$reason = sprintf( __( 'Infinite subscription lengths are not supported for %s.', 'paynow' ), $product_title );
		}

		if ( 'week' === $subscription_period && $subscription_interval > 2 ) {
			// Only supports every week or every 2 weeks.
			$supported = false;
			/* translators: %d is the subscription_interval, %s is the product_title */
			$reason = sprintf( __( "Every '%1\$d' weeks is an unsupported subscription cycle for '%2\$s'.", 'paynow' ), $subscription_interval, $product_title );
		}

		if ( 'month' === $subscription_period && ! in_array( $subscription_interval, array( 1, 4, 6 ), true ) ) {
			// Only supports every month, every 4 months (quarterly), or every 6 months.
			$supported = false;
			/* translators: %d is the subscription_interval, %s is the product_title */
			$reason = sprintf( __( "Every '%1\$d' months is an unsupported subscription cycle for '%2\$s'.", 'paynow' ), $subscription_interval, $product_title );
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
			// Don't run in backend (admin).
			return true;
		}

		$subscription_id = null;

		if ( WC()->cart ) {
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
			if ( function_exists( 'wc_add_notice' ) ) {
				/* translators: %s is the message */
				wc_add_notice( sprintf( __( 'Pay Now: %s', 'paynow' ), $supported_or_reason ), 'error' );
			}
			$this->log( 'Pay Now payment method removed from cart due to "' . $supported_or_reason . '"' );
			return false;
		}

		return true;
	}

	/**
	 * Hook into the WordPress page display hook
	 *
	 * @param int    $post_id The post id.
	 * @param object $post The post.
	 * @param bool   $update ?.
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
			// Not a subscription.
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


	/**
	 * Scheduled_subscription_payment function.
	 *
	 * @param float|int $amount_to_charge The amount to charge.
	 * @param WC_Order  $renewal_order A WC_Order object created to record the renewal payment.
	 */
	public function handle_scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$this->log( "[handle_scheduled_subscription_payment] Called for renewal order {$renewal_order->get_id()} with amount {$amount_to_charge}" );
	}

	/**
	 * Scheduled_subscription_payment function.
	 *
	 * @param string $subscription_key The key.
	 * @param array  $new_subscription_details The details.
	 */
	public function handle_updated_users_subscription( $subscription_key, $new_subscription_details ) {
		$this->log( "[handle_updated_users_subscription] Called for subscription {$subscription_key} " );
		$this->log( '- new_subscription_details ' . print_r( $new_subscription_details, true ) );
	}

	/**
	 * Called by action to update the payment method when a customer is making a payment in lieu of an automatic renewal payment that previously failed
	 *
	 * @param WC_Order $original_order Original order.
	 * @param WC_Order $new_renewal_order New order.
	 */
	public static function update_failing_payment_method( $original_order, $new_renewal_order ) {

		// You do not need to update the the payment method or anything else on the original order, Subscriptions will
		// handle that, simply make sure the original order has whatever meta data is required to correctly handle future
		// payments and manage the subscription.

		update_post_meta( $original_order->get_id(), '_update_failing_payment_method_called', 1 );
		update_post_meta( $original_order->get_id(), '_your_gateway_customer_token_id', get_post_meta( $new_renewal_order->get_id(), '_your_gateway_customer_token_id', true ) );

	}

	/**
	 * Triggered when a renewal payment fails for a subscription.
	 *
	 * @param WC_Subscription $subscription The subscription.
	 */
	public static function handle_subscription_renewal_payment_failed( $subscription ) {
		self::log( "[handle_subscription_renewal_payment_failed] Called for subscription {$subscription->get_id()} " );

		// Netcash won't retry the payment if it failed
		// The customer should manually pay. In order to show "Pay" button on renewal order on "Customer's View" the order
		// must be set to manual renew. https://docs.woocommerce.com/document/subscriptions/customers-view/.
		if ( $subscription->get_payment_method() === WC_Gateway_PayNow::$id ) {
			self::log( "\t Set to manually renew. {$subscription->get_id()}" );
			$subscription->set_requires_manual_renewal( true );
			$subscription->save();
		}
	}
}
