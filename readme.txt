=== Netcash Pay Now - Payment Gateway for WooCommerce ===
Contributors: @netcashpaynow
Tags: woocommerce, payment, gateway, south-africa, netcash
Requires at least: 4.7
Tested up to: 6.3
Stable tag: 4.0.14
Requires PHP: 7.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

This is the Netcash Pay Now plugin for WooCommerce giving you the ability to accept recurring and credit card payments in your WooCommerce store.

== Description ==

A payment gateway for Netcash Pay Now. A Netcash Pay Now merchant account and service key are required for this gateway to function.

**Note:** An SSL certificate is recommended for additional safety and security for your customers.

This plugin integrates your WooCommerce store with Netcash Pay Now and will send your users to our website (https://paynow.netcash.co.za/site/paynow.aspx) in order for use to capture the payment.
After the payment the user will be redirected back to your store.

### Prerequisites:

You will need:

* A [Netcash](https://netcash.co.za/) account
* Pay Now service activated
* Netcash account login credentials (with the appropriate permissions setup)
* Netcash - Pay Now Service key
* Cart admin login credentials

== Installation ==

### Netcash Account Configuration Steps:

1. Log into your [Netcash account](https://merchant.netcash.co.za/SiteLogin.aspx)
2. Type in your Username, Password, and PIN
3. Click on ACCOUNT PROFILE on the top menu
4. Select NETCONNECTOR from the left side menu
5. Click on PAY NOW from the subsection
6. ACTIVATE the Pay Now service
7. Type in your EMAIL address
8. It is highly advisable to activate test mode & ignore errors while testing
9. Select the PAYMENT OPTIONS required (only the options selected will be displayed to the end user)
10. Remember to remove the "Make Test Mode Active" indicator to accept live payments
11. Click SAVE and COPY your Pay Now Service Key

### Netcash Pay Now Callback

Use the following URLs for the callbacks.

* Accept, Decline, and Redirect URLs:
	https://YOUR_DOMAIN.co.za/
* Notify URL:
    https://YOUR_DOMAIN.co.za/wp-content/plugins/gateway-paynow/notify-callback.php

> **NOTE:** If your WordPress installation is in a sub-directory, use
> https://your_domain_name.co.za/SUBDIRECTORY_NAME/wp-content/plugins/gateway-paynow/notify-callback.php

**Important:** Please make sure you use the correct URL for the redirects.

> For immediate assistance contact Netcash on 0861 338 338

### WordPress and WooCommerce Installation

In order to use WooCommerce you need to have a working installation of WordPress.

1. Install WordPress
2. Log into WordPress as an administrator (wp-admin folder)
3. Go go to Plugins / Add New
3. Search for "woocommerce" and once it's found click on 'Install Now'
4. Activate WooCommerce

### Netcash Pay Now Plugin Installation and Activation

5. Login to your WordPress website as admin (wp-admin folder)
6. Click "Plugins" / "Upload", "Browse", and selected the downloaded file
7. Click 'Install' and Activate the Plugin.

### WooCommerce Configuration

8. Select "WooCommerce" in the admin menu, click on "Settings" and under "General" select "Currency South African Rand" and Save Changes.
9. Select "Payment Gateways", "PayNow", and tick "Enable PayNow" and Save Changes.
10. Enter your Netcash Service key

== Frequently Asked Questions ==

= Do I need a Netcash account? =

Yes. A Netcash Pay Now merchant account and service key are required for this gateway to function.

= Can I use recurring payments?? =

Yes. Netcash Pay Now for WooCommerce integrates with WooCommerce Subscriptions.

= What should the redirect URL be set to? =

In the WooCommerce setting a notice with the heading "Netcash Connecter URLs" will display the full URL to the redirect URLs.

= Where can I find the debug log? =

If debugging is enabled we will log to the default PHP error log. Please ask you system administrator for the exact location.

== Screenshots ==

1. Screenshot of Pay Now settings inside WooCommerce

== Changelog ==

= 4.0.14 =

* Fix quarterly subscription period

= 4.0.12 =

* Netcash PHP updates

= 4.0 =
* Use Github Tags
* Implement subscriptions
* Use same URL for all post backs
* Implement Pay Now SDK
* Fix EFT payment issues

== Upgrade Notice ==

Please completely remove the current plugin files from `/wp-content/plugins/Archive` or `/wp-content/plugins/WooCommerce-PayNow` and remove the `paynow_callback.php` from the website root.

= 3.0 =
* Rebrand
