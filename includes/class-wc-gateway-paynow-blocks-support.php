<?php
/**
 * WooCommerce Blocks integration for Netcash Pay Now
 *
 * @package    Netcash_WooCommerce_Gateway_PayNow
 * @since      4.1.7
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Netcash Pay Now Blocks integration
 *
 * @since 4.1.7
 */
final class WC_Gateway_PayNow_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'paynow';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_paynow_settings', array() );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		$payment_gateways_class   = WC()->payment_gateways();
		$payment_gateways         = $payment_gateways_class->payment_gateways();

		return isset( $payment_gateways['paynow'] ) && $payment_gateways['paynow']->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$plugin_dir        = dirname( __DIR__ );
		$script_path       = 'assets/js/blocks.js';
		$script_asset_path = $plugin_dir . '/assets/js/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => '1.0.2',
			);

		// Build the script URL using plugin_dir_url on the main plugin file
		$plugin_url = plugin_dir_url( $plugin_dir . '/gateway-paynow.php' );
		$script_url = $plugin_url . $script_path;

		// Explicit dependencies required for WooCommerce Blocks
		$dependencies = array(
			'wc-blocks-registry',
			'wc-settings',
			'wp-element',
			'wp-html-entities',
			'wp-i18n',
		);

		// Merge with any additional dependencies from the asset file
		if ( ! empty( $script_asset['dependencies'] ) ) {
			$dependencies = array_merge( $dependencies, $script_asset['dependencies'] );
		}

		wp_register_script(
			'wc-paynow-blocks',
			$script_url,
			$dependencies,
			$script_asset['version'],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-paynow-blocks', 'wc_paynow', $plugin_dir . '/languages/' );
		}

		return array( 'wc-paynow-blocks' );
	}

	/**
	 * An array of scripts to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'wc-paynow-blocks' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => array_filter( $this->get_supported_features(), array( $this, 'get_gateway' ) ),
		);
	}

	/**
	 * Get the gateway instance.
	 *
	 * @return Netcash_WooCommerce_Gateway_PayNow|null
	 */
	private function get_gateway() {
		$payment_gateways_class = WC()->payment_gateways();
		$payment_gateways       = $payment_gateways_class->payment_gateways();

		return isset( $payment_gateways['paynow'] ) ? $payment_gateways['paynow'] : null;
	}

	/**
	 * Get supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		$gateway = $this->get_gateway();

		if ( ! $gateway ) {
			return array();
		}

		return $gateway->supports;
	}
}
