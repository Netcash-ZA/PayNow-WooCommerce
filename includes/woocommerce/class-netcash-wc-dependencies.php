<?php
/**
 * Load WooCommerce dependencies.
 *
 * @package    Netcash_WooCommerce_Gateway_PayNow
 * @since      1.0.0
 */

/**
 * WC Dependency Checker
 *
 * Checks if WooCommerce is enabled
 */
class Netcash_WC_Dependencies {

	/**
	 * The active plugins
	 *
	 * @var array
	 */
	private static $active_plugins;

	/**
	 * Init
	 */
	public static function init() {

		self::$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			self::$active_plugins = array_merge( self::$active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}
	}

	/**
	 * Check if WC is active
	 *
	 * @return bool
	 */
	public static function woocommerce_active_check() {

		if ( ! self::$active_plugins ) {
			self::init();
		}

		return in_array( 'woocommerce/woocommerce.php', self::$active_plugins, true ) || array_key_exists( 'woocommerce/woocommerce.php', self::$active_plugins );
	}

	/**
	 * Check if WooCommerce Subscriptions is active
	 *
	 * @return bool
	 */
	public static function woocommerce_subscriptions_active_check() {

		if ( ! self::$active_plugins ) {
			self::init();
		}

		// Class exists 'WC_Subscriptions_Order'?
		return in_array( 'woocommerce-subscriptions/woocommerce-subscriptions.php', self::$active_plugins, true )
			|| array_key_exists( 'woocommerce-subscriptions/woocommerce-subscriptions.php', self::$active_plugins );
	}
}
