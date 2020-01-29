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
* Netcash login credentials
* Netcash Pay Now Service key
* WooCommerce admin login credentials

Netcash Pay Now Gateway Server Configuration Steps:

1. Log into your Netcash Pay account
	https://merchant.netcash.co.za/SiteLogin.aspx
2. Go to Account / Profile
3. Click on NetConnector
4. Click on Pay Now
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
