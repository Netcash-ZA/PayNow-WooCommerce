Sage Pay Now WooCommerce Credit Card Payment Module 2.0.0
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
	http://www.your_domain_name.co.za

7. Choose both the following URLs for your Notify and Redirect URLs:
	http://www.your_domain_name.co.za/wp-content/plugins/PayNow-WooCommerce/paynow_callback.php

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

Here is a screen shot of what the WooCommerce settings screen for the Sage Pay Now configuration:
![alt tag](http://woocommerce.gatewaymodules.com/woocommerce_screenshot1.png)

Revision History
----------------

* 19 Aug 2015/2.0.0 Added additional fields. Allow for retail & EFT payments.
* 18 Aug 2014/1.0.6 Modified P3 to display client name (#order id)
                    Updated WordPress to 3.9.2 and WooCommerce to 2.1.12
* 14 May 2014/1.0.5 Now using built-in WordPress debugging.
* 14 May 2014/1.0.4 More detailed setup documentation
* 13 May 2014/1.0.3 Added debug function with e-mail functionality
* 10 May 2014/1.0.2 Added image to README.md
* 09 May 2014/1.0.1
** New documentation
** Session had to be transferred back to callback

Tested with WordPress 3.9.2 and WooCommerce version 2.1.12
Tested with WordPress 3.9.x and WooCommerce version 2.1.8

Demo Site
---------
Here is a demo site if you want to see WooCommerce and the Pay Now gateway in action:
http://woocommerce.gatewaymodules.com

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

Please do not hesitate to contact Sage Pay if you have any suggestions or comments or log an issue on GitHub.
