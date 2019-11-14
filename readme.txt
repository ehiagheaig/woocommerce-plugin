=== Plugin Name ===

Contributors: Ehi Aig
Plugin Name: Vesicash Escrow Plugin for WooCommerce
Plugin URI: https://www.vesicash.com/plugins/woocommerce/
Tags: wp, escrow, payment
Author URI: https://vesicash.com/
Author: vesicash
Requires WordPress Version: 4.0 or higher
Compatible up to: 5.2.4
Requires PHP Version: 5.6 or higher 
Version: 1.0.0


== Description ==

Vesicash provides secure, instant, digital escrow payment option for your website, marketplace and classifieds so that you can do your business seamlessly and not worry about chargebacks, ever.

Vesicash Escrow is important in situations where buyers and sellers do not know or trust each other and needs a way to guarantee payment security for their transaction. 

This plugin allows you to add Vesicash Escrow as a payment option on your checkout page in WooCommerce.


== Installation ==

You are expected to have created a vesicash business account (www.vesicash.com/signup) in order to successfully install this plugin.

    Installation of the Vesicash Escrow Plugin for WooCommerce can be done directly via the WordPress plugin directory or download the latest from our plugin page on WordPress.org and upload the woo-vesicash-gateway.zip file via the WordPress plugin upload page.
    Activate the plugin through the Plugins menu in WordPress.
    Once the plugin has been activated, navigate to the settings page under WooCommerce / Settings / Payments / Vesicash Escrow.
    The settings page allows you to configure the plugin to your liking.
        Supply your API secret key
        Supply your Business ID in your vesicash dashboard
        Make sure to select the right API Environment URL (and set the equivalent API secret key) where all orders are to be sent to. 
            For example: 
                Set the API Environment URL to sandbox (and set the Sanbox secret key) to use the sandbox environment.
                Set the API Environment URL to live (and set the Live secret key) use the live environment.
    Enable the Vesicash Escrow payment option on your checkout page by checking the Enable Vesicash Escrow Payment Method check box on the settings page.
    Click Save at the bottom of the screen.


== Upgrade Notice ==
All vendors who integrate the Vesicash Escrow Plugin will receive email notification in their vesicash business email whenever there is a upgrade to the Vesicash Escrow Plugin for Woocommerce.


== Changelog ==

1.0.0
    First Release


== Frequently Asked Questions ==

What kind of order can be carried out using this plugin?

For now, our plugin supports only product transaction for purchase of physical goods.

Who is the buyer, seller and broker?

As a WooCommerce store owner, you are either the seller in the transaction in a single-vendor platform or the broker in a multi-vendor platform. In both scenarios, you will be required to complete each order from the Vesicash dashboard. In multi-vendor platforms, each vendor will also be required to log into vesicash.com dashboard to move a transaction for which they are a party to the next stage. In both scenarios, the customer who checkouts using this plugin is always the buyer. 

What happens after an order has been placed?

Once an order has been placed using the Vesicash Escrow Payment option, the buyer will be instructed to complete the payment by logging into vesicash.com. The seller is also notified via email of the new payment.

What if there is an error during checkout using the Vesicash Escrow payment option.

If an error occurs when a user places an order with the Vesicash Escrow payment option selected, open the WooCommerce admin and navigate to Status and then Logs. This plugin writes debug and error messages to these logs. They may be under fatal- or log-. If a 401 or 403 error is being returned from the Vesicash API, then there is a problem with the Vesicash Escrow credentials. If a 500 error is being returned, there is a problem on the Vesicash server. When that happens, please email ehi@vesicash.com and it will be investigated.
