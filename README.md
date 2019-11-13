Netcash Pay Now WooCommerce Credit Card Payment Module 3.0.0
=========================================================

Introduction
------------
WooCommerce is an open source e-commerce plugin for WordPress.

This is the Netcash Pay Now module which gives you the ability to take credit card transactions online.

Download Instructions
-------------------------

Download the files from the location below to a temporary location on your computer.

Configuration
-------------

Prerequisites:

You will need:
* Netcash Pay Now login credentials
* Netcash Pay Now Service key
* WooCommerce admin login credentials

Netcash Pay Now Gateway Server Configuration Steps:

1. Log into your Netcash Pay Now Gateway Server configuration page:
	https://merchant.netcash.co.za/SiteLogin.aspx
2. Go to Account / Profile
3. Click Sage Connect
4. Click Pay Now
5. Make a note of your Service key

Netcash Pay Now Callback

6. Choose both the following URLs for your Accept and Decline URLs:
	http://www.your_domain_name.co.za/

7. Choose both the following URLs for your Notify and Redirect URLs:
	http://www.your_domain_name.co.za/wp-content/plugins/PayNow-WooCommerce/paynow_callback.php

WordPress and WooCommerce Installation

In order to use WooCommerce you need to have a working installation of WordPress.

1. Install WordPress
2. Log into WordPress as an administrator (wp-admin folder)
3. Go go to Plugins / Add New
3. Search for "woocommerce" and once it's found click on 'Install Now'
4. Activate WooCommerce

Netcash Pay Now Plugin Installation and Activation

5. Login to your WordPress website as admin (wp-admin folder)
6. Click "Plugins" / "Upload", "Browse", and selected the downloaded file
7. Click 'Install' and Activate the Plugin.

WooCommerce Configuration

8. Select "WooCommerce" in the admin menu, click on "Settings" and under "General" select "Currency South African Rand" and Save Changes.
9. Select "Payment Gateways", "PayNow", and tick "Enable PayNow" and Save Changes.
10. Enter your Netcash Service key


Issues / Feedback / Feature Requests
------------------------------------

Please do the following should you encounter any problems:

* Ensure at Sage that your Accept and Decline URLs are just the site name, without a trialing slash. Do not add the name of any pages.
For example, if your site is 'www.mysite.co.za', use:
http://www.mysite.co.za
WooCommerce will redirect to it's own success and fail pages after updating the order.
* There are three steps that will enable maximum debugging
** Enable Debugging in the Pay Now module
Add the following TWO lines to your wp-config.php:
** define('WP_DEBUG', true);
** define('WP_DEBUG_LOG',true);

If you add both these lines a log file will be generated in your wp-content folder called 'debug.log'.

Turn OFF debugging when you are in a production environment.

We welcome your feedback and suggestions.

Please do not hesitate to contact Netcash if you have any suggestions or comments or log an issue on GitHub.
