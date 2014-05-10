Sage Pay Now WooCommerce Module
===============================

Revision 1.0.1

Introduction
------------

Sage Pay South Africa's Pay Now third party gateway integration for WooCommerce.

Installation Instructions
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

WooCommerce Steps:

1. Login to your Wordpress website as admin (wp-admin folder).
2. Click "Plugins" / "Upload", "Browse", and selected the downloaded file.
3. Select "WooCommerce" in the admin menu, click on "Settings" and under "General" select "Currency South African Rand" and Save Changes.
4. Select "Payment Gateways", "PayNow", and tick "Enable PayNow" and Save Changes.
5. Enter your Sage Pay Service key

Here is a screenshot of what the osCommerce settings screen for the Sage Pay Now configuration:
![alt tag](http://woocommerce.gatewaymodules.com/woocommerce_screenshot1.png)

Revision History
----------------

* 9 May 2014/1.0.1
** New documentation
** Session had to be transferred back to callback

Tested with Wordpress 3.9.x and WooCommerce version 2.1.8

Demo Site
---------
Here is a demo site if you want to see OpenCart and the Sage Pay Now gateway in action:
http://opencart.gatewaymodules.com

Issues & Feature Requests
-------------------------
Please log any issues or feature requests on GitHub or contact Sage Pay South Africa
