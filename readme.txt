=== Netcash WooCommerce Payment Gateway ===
Contributors: @netcashpaynow
Tags: woocommerce, payment, gateway, south-africa, netcash
Requires at least: 4.7
Tested up to: 6.5
Stable tag: 4.0.26
Requires PHP: 7.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

This is the Netcash Pay Now plugin for WooCommerce giving you the ability to accept recurring and credit card payments in your WooCommerce store.

== Description ==

Let your customers ‘Pay Now’ any way they want to, with the Netcash Payment Gateway. Seamlessly integrate Pay Now with WooCommerce and business software suites to make payments simpler, safer, and faster than ever. Take a tour with our [checkout demo](https://netcash.co.za/demos/checkout.html?utm_medium=affiliate&utm_source=listing&utm_campaign=woocommerce_plugin).

- Easy setup and installation
- Custom integration supported via API
- Friendly customer service with a dedicated account manager
- Choose when to get paid out and receive your funds in 24 hours.
- 3D Secure and PCI DSS Level 1 Compliant

Need to sign up as a Netcash merchant? [Register now.](https://netcash.co.za/apply-for-your-netcash-account/?utm_medium=affiliate&utm_source=listing&utm_campaign=woocommerce_plugin)

### Why Choose Netcash?

**Multiple payment methods:** Accept payment via all major credit and debit cards, QR and digital wallets, bank EFT, Instant EFT, Payflex, retail payments, and more. We give you everything in one, no need for multiple sign ups and fees.

**Streamlined checkout experience:** Ensure quick and hassle-free transactions, reduce cart abandonment rates and boost sales on desktop and mobile.

**Payment requests:** Send a payment link to anyone, anywhere - no code required. Share a request via email, SMS, QR code or WhatsApp to get paid instantly and securely.

**Invoicing:** Get paid faster, with less admin. Easily connect Pay Now to your existing payroll or accounting software, and offer customers multiple ways to pay at a click, with every invoice you send.

**Subscriptions:** Earn recurring revenue for your business with Woo Subscriptions or use our custom API integration.

### Prerequisites:

You will need:

* A [Netcash](https://netcash.co.za/?utm_medium=affiliate&utm_source=listing&utm_campaign=woocommerce_plugin) account
* Pay Now service activated
* Netcash account login credentials (with the appropriate permissions setup)
* Netcash - Pay Now Service key
* Cart admin login credentials

== Installation ==

### Netcash Account Configuration Steps:

1. Log into your [Netcash account](https://merchant.netcash.co.za/SiteLogin.aspx?utm_medium=affiliate&utm_source=listing&utm_campaign=woocommerce_plugin)
2. Type in your Username, Password, and PIN
3. Click on **Account Profile** on the top menu
4. Select **Netconnector** from the left side menu
5. Click on **Pay Now** from the subsection
6. **Activate** the Pay Now service
7. Type in your email address
8. It is highly advisable to activate test mode & ignore errors while testing
9. Select the payment options required (only the options selected will be displayed to the end user)
10. Remember to remove the "Make Test Mode Active" indicator to accept live payments
11. Click "Save" and copy your Pay Now Service Key

### Netcash Pay Now Callback

Use the following URLs for the callbacks.

* Accept, Decline, and Redirect URLs:
	https://YOUR_DOMAIN.co.za/
* Notify URL:
    https://YOUR_DOMAIN.co.za/wp-content/plugins/gateway-paynow/notify-callback.php

> **NOTE:** If your WordPress installation is in a sub-directory, use
> https://your_domain_name.co.za/SUBDIRECTORY_NAME/wp-content/plugins/gateway-paynow/notify-callback.php

**Important:** Please make sure you use the correct URL for the redirects.

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

= Does this require a Netcash merchant account? =

Yes, you can register for a new account [here](https://netcash.co.za/apply-for-your-netcash-account/?utm_medium=affiliate&utm_source=listing&utm_campaign=woocommerce_plugin) or use your existing Netcash account to login and activate the service.

= I need support with this plugin. Who can I contact? =

You can reach out to [support@netcash.co.za](mailto:support@netcash.co.za) with any queries.

= Can I accept foreign currency through Pay Now? =

Unfortunately, all transactions are processed in ZAR. You can however accept foreign credit and debit cards, as long as they are 3D secured.

= What are the transaction fees? =

To receive our latest fees, please email [sales@netcash.co.za](mailto:sales@netcash.co.za).

= Does Netcash support custom integration? =

Yes, you can use our developer-friendly API to create custom integrations for your online store. Please contact [support@netcash.co.za](mailto:support@netcash.co.za) for assistance and documentation.

= Can I use recurring payments?? =

Yes. Netcash Pay Now for WooCommerce integrates with WooCommerce Subscriptions.

= What should the redirect URL be set to? =

In the WooCommerce setting a notice with the heading "Netcash Connecter URLs" will display the full URL to the redirect URLs.

= Where can I find the debug log? =

If debugging is enabled we will log to the default PHP error log. Please ask you system administrator for the exact location.

== Screenshots ==

1. Screenshot of Pay Now settings inside WooCommerce
2. Netcash Credit Card Payment
4. Netcash's various payment methods
4. Netcash Scan to Pay

== Changelog ==

= 4.0.26 =

* Add auto redirect
* Declare incompatibility for WooCommerce checkout blocks
* Update WooCommerce linting

= 4.0.25 =

* Update "tested up to"

= 4.0.20 =

* Update readme links
* Add ability for customer to cancel subscription from the frontend
* Add ability to create subscriptions with a free trial and no signup fee.

= 4.0.19 =

* Fix some orders not being reconciled correctly
* Fix deprecated magic properties

= 4.0.18 =

* Update to quarterly subscription period

= 4.0.17 =

* Update banners, screenshots, FAQs

= 4.0.15 =

* WordPress.org release

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
