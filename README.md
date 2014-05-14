Sage Pay Now WooCommerce Credit Card Payment Module 1.0.4
=========================================================

Introduction
------------
WooCommerce is an open source e-commerce plugin for WordPress.

This is the Sage Pay Now module which gives you the ability to take credit card transactions online.

Download Instructions
-------------------------

Download the files from the location below to a temporary location on your computer:
* http://woocommerce.gatewaymodules.com/wp-content/plugins/woocommerce-gateway-paynow.zip

Configuration
-------------

Prerequisites:

You will need:
* Sage Pay Now login credentials
* Sage Pay Now Service key
* WooCommerce admin login credentials

Sage Pay Now Gateway Server Configuration Steps:

1. Log into your Sage Pay Now Gateway Server configuration page:
	https://merchant.sagepay.co.za/SiteLogin.aspx
2. Go to Account / Profile
3. Click Sage Connect
4. Click Pay Now
5. Make a note of your Service key

Sage Pay Now Callback

6. Choose both the following URLs for your Accept and Decline URLs:
	http://woo_commerce_installation

WordPress and WooCommerce Installation

In order to use WooCommerce you need to have a working installation of WordPress.

1. Install WordPress
2. Log into WordPress as an administrator (wp-admin folder)
3. Go go to Plugins / Add New
3. Search for "woocommerce" and once it's found click on 'Install Now'
4. Activate WooCommerce

Sage Pay Now Plugin Installation and Activation

5. Login to your WordPress website as admin (wp-admin folder)
6. Click "Plugins" / "Upload", "Browse", and selected the downloaded file
7. Click 'Install' and Activate the Plugin.

WooCommerce Configuration

8. Select "WooCommerce" in the admin menu, click on "Settings" and under "General" select "Currency South African Rand" and Save Changes.
9. Select "Payment Gateways", "PayNow", and tick "Enable PayNow" and Save Changes.
10. Enter your Sage Pay Service key

Here is a screenshot of what the osCommerce settings screen for the Sage Pay Now configuration:
![alt tag](http://woocommerce.gatewaymodules.com/woocommerce_screenshot1.png)

Revision History
----------------

* 14 May 2014/1.0.4 More detailed setup documentation
* 13 May 2014/1.0.3 Added debug function with e-mail functionality
* 10 May 2014/1.0.2 Added image to README.md
* 9 May 2014/1.0.1
** New documentation
** Session had to be transferred back to callback

Tested with Wordpress 3.9.x and WooCommerce version 2.1.8

Demo Site
---------
Here is a demo site if you want to see WooCommerce and the Pay Now gateway in action:
http://woocommerce.gatewaymodules.com

Issues & Feature Requests
-------------------------
Please log any issues or feature requests on GitHub or contact Sage Pay South Africa
