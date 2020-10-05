=== Netcash Pay Now Gateway for WooCommerce ===
Contributors: iedmdev001
Tags: woocommerce, payments, south africa, netcash
Requires at least: 3.5
Tested up to: 5.5.1
Requires PHP: 5.6
Stable tag: 4.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A payment gateway for Netcash Pay Now. A Netcash Pay Now merchant account and service key are required for this gateway to function.

== Description ==

A payment gateway for Netcash Pay Now. A Netcash Pay Now merchant account and service key are required for this gateway to function.

**Note:** An SSL certificate is recommended for additional safety and security for your customers.

== Prerequisites: ==

You will need:

* Netcash account
* Pay Now service activated
* Netcash account login credentials (with the appropriate permissions setup)
* Netcash - Pay Now Service key
* Cart admin login credentials

### A. Netcash Account Configuration Steps:

1. Log into your [Netcash account](https://merchant.netcash.co.za/SiteLogin.aspx)
2. Type in your Username, Password, and PIN
2. Click on ACCOUNT PROFILE on the top menu
3. Select NETCONNECTOR from tghe left side menu
4. Click on PAY NOW from the subsection
5. ACTIVATE the Pay Now service
6. Type in your EMAIL address
7. It is highly advisable to activate test mode & ignore errors while testing
8. Select the PAYMENT OPTIONS required (only the options selected will be displayed to the end user)
9. Remember to remove the "Make Test Mode Active" indicator to accept live payments
10. Click SAVE and COPY your Pay Now Service Key

###  Netcash Pay Now Callback

11. Enter both the following URLs for your Accept, Decline, Notify, and Redirect URLs:
	https://your_domain_name.co.za/

**Important:** Please make sure you use the correct URL for the redirects.

== Installation ==

#### Netcash Pay Now Plugin Installation and Activation

* Login to your WordPress website as admin (wp-admin folder)
* Click "Plugins" / "Upload", "Browse", and selected the downloaded file
* Click "Install" and Activate the Plugin.

#### WooCommerce Configuration

* Select "WooCommerce" in the admin menu, click on "Settings" and under "General" select "Currency South African Rand" and Save Changes.
* Select "Payment Gateways", "PayNow", and tick "Enable PayNow" and Save Changes.
* Enter your Netcash Service key

== Frequently Asked Questions ==

= What should the redirect URL be set to? =

In the WooCommerce setting a notice with the heading "Netcash Connecter URLs" will display the full URL to the redirect URLs.

= Where can I find the debug log? =

If debugging is enabled we will log to the default PHP error log. Please ask you system administrator for the exact location.

== Changelog ==

= 4.0 =
* Use same URL for all post backs
* Implement Pay Now SDK
* Fix EFT payment issues

== Upgrade Notice ==

Please completely remove the current plugin files from `/wp-content/plugins/Archive` or `/wp-content/plugins/WooCommerce-PayNow`
 and remove the `paynow_callback.php` from the website root.

= 3.0 =
* Rebrand
