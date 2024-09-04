<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Load WooCommerce Dependencies.
 *
 * @package    Netcash_WooCommerce_Gateway_PayNow
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Functions used by plugins
 */
if ( ! class_exists( 'Netcash_WC_Dependencies' ) ) {
	require_once 'class-netcash-wc-dependencies.php';
}

/**
 * WC Detection
 */
if ( ! function_exists( 'netcash_is_woocommerce_active' ) ) {
	/**
	 * Checks whether Woocommerce is active.
	 *
	 * @return bool
	 */
	function netcash_is_woocommerce_active() {
		return Netcash_WC_Dependencies::woocommerce_active_check();
	}
}

/**
 * WooCommerce Subscriptions Detection
 */
if ( ! function_exists( 'netcash_is_woocommerce_subscriptions_active' ) ) {
	/**
	 * Checks whether Woocommerce Subscriptions is active.
	 *
	 * @return bool
	 */
	function netcash_is_woocommerce_subscriptions_active() {
		return Netcash_WC_Dependencies::woocommerce_subscriptions_active_check();
	}
}
