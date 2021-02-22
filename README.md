Netcash Pay Now Gateway for WooCommerce 4.0.0
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

### Prerequisites:

You will need:
* Netcash account
* Pay Now service activated
* Netcash account login credentials (with the appropriate permissions setup)
* Netcash - Pay Now Service key
* Cart admin login credentials

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

- Accept, Decline, and Redirect URLs:
	https://YOUR_DOMAIN.co.za/
- Notify URL: 
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
