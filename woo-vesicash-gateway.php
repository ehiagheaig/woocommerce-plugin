<?php
/**
 * Plugin Name: Vesicash Escrow Plugin for WooCommerce
 * Plugin URI: https://www.vesicash.com/plugins/woocommerce
 * Description: Take secure escrow payments on your store using Vesicash.
 * Version: 1.0.0
 * Author: vesicash
 * Author URI: https://www.vesicash.com/
 * Developer: vesicash
 * Text Domain: vesicash-gateway
 * Copyright: @ 2019 Vesicash.com
 */

// Prevent plugin from being accessed outside of WordPress.
defined('ABSPATH') or exit;

// Ensures WooCommerce is installed and active.
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/*
* This action hook registers our PHP class as a WooCommerce payment gateway
*/
add_filter( 'woocommerce_payment_gateways', 'add_vesicash_gateway_class' );
function add_vesicash_gateway_class( $gateways ) {
	$gateways[] = 'WC_Vesicash_Gateway'; // your class name is here
	return $gateways;
}

/**
 * Handle plugin activation.
 */
add_action('activated_plugin', 'detect_vesicash_plugin_activated', 10, 2);
function detect_vesicash_plugin_activated($plugin, $network_activation) {
    if (strpos($plugin, 'woo-vesicash-gateway') !== false) {
        vesicash_customer_notification('activate');
    }
}

/**
 * Handle plugin deactivation.
 */
add_action('deactivated_plugin', 'detect_vesicash_plugin_deactivated', 10, 2);
function detect_vesicash_plugin_deactivated($plugin, $network_activation) {
    if (strpos($plugin, 'woo-vesicash-gateway') !== false) {
        vesicash_customer_notification('deactivate');
    }
}

/**
 * Notify Vesicash team of plugin status.
 */
function vesicash_customer_notification($event) {

    try {     
        // Get logged in user.
        $current_user = wp_get_current_user();

        // Get the user agent for this plugin.
        $user_agent = "VesicashPlugin/WooCommerce/3.8.0 WooCommerce/" . WC()->version . " WordPress/" . get_bloginfo('version') . " PHP/" . PHP_VERSION;
        
        // Build request.
        $request = array(
            'url'            => get_home_url(),
            'email'          => $current_user->user_email,
            'event'          => $event,
            'plugin_name'    => 'WooCommerce',
            'plugin_details' => $user_agent
        );
        // Send the notification to us.
        $send_notice = wp_remote_post( 'https://api.vesicash.com/v1/notifications/plugins/plugin_event', array(
            'method' => 'POST',
            'headers' => array(
                'Accept' => 'application/json'
            ),
        'sslverify' => false,
        'timeout' => 15,
        'body' => json_encode($request)
        ));

        if ( is_wp_error( $send_notice ) ) {
            wc_add_notice( __('Plugin Events error:', 'vesicash') . $send_notice->get_error_message(), 'error' );
            return false;
        }

        $send_notice = json_decode($send_notice['body']);

        if( $send_notice && $send_notice->status == "ok" ) {
            return true;
        }
    
    } catch (Exception $e) {
        // If it fails, do other things.
    }
}

/*
 * Initialize the plugin class for the gateway.
 */
add_action('plugins_loaded', 'wc_vesicash_gateway_init', 11);
function wc_vesicash_gateway_init() {
    class WC_Vesicash_Gateway extends WC_Payment_Gateway {

        // Define API Settings.
        private $v_private_key        = "";
        private $api_url        = "";
        
        // Define Checkout Settings.
        private $enable_when           = "";
        
        // Define Transaction Settings.
        private $currency              = "";
        private $escrow_charge_bearer      = "";
        private $inspection_period     = "";
        private $shipping_fee           = null;
        private $transaction_type      = "";
        private $due_date      = "";
        private $trans_details;
        private $business_id = "";

        /**
		 * Class constructor, more about it in Step 3
		*/
		public function __construct() {

			$this->id = 'vesicash'; // payment gateway plugin ID
			$this->icon = 'https://vesicash.com/backend/img/vesi-logo.png'; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = false; // in case you need a custom credit card form
			$this->method_title = 'Vesicash Escrow';
			$this->method_description = 'No more pay-on-delivery, reach more customers with Vesicash Escrow' ; // will be displayed on the options page
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->enable_when           = $this->get_option('enable_when', $this->enable_when);
            
            // Values to be configured from the vesicash settings page.
            $this->api_url        = $this->get_option('api_url', $this->api_url);  
            $this->business_id        = $this->get_option('business_id', $this->business_id);  
            $this->v_private_key        = $this->get_option('v_private_key', $this->v_private_key);
            $this->currency= $this->get_option('currency', $this->currency);
            $this->escrow_charge_bearer      = $this->get_option('escrow_charge_bearer', $this->escrow_charge_bearer);
            $this->inspection_period     = $this->get_option('inspection_period', $this->inspection_period);
            $this->shipping_fee           = $this->get_option('shipping_fee', $this->shipping_fee);
            $this->transaction_type      = $this->get_option('transaction_type', $this->transaction_type);
            $this->due_date     = $this->get_option('due_date', $this->due_date);

			// This action hook saves the settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		 
			// You can also register a webhook here
            add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
            
            // Method with all the options fields
			$this->init_form_fields();
		 
			// Load the settings.
			$this->init_settings();

        }

        /*
         * Returns setting indicating when to display Vesicash payment option on checkout page.
         *
         */
        public function get_enable_when() {
            return $this->enable_when;
        }

        
        /**
		 * Plugin setting options
		*/
        public function init_form_fields(){

			// $this->form_fields = array(
            $this->form_fields = apply_filters('wc_escrow_form_fields', array(

				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable Vesicash Escrow',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
                ),
                'enable_when' => array(
                    'title' => 'Enable When',
                    'type' => 'select',
                    'description' => 'Determines whether to show this payment option on the checkout page. \'Enable Always\' shows the Vesicash payment option at all times. \'Enable Only When All Items can be escrowed\' shows the Vesicash payment option when all items in the cart have the \'escrowable\' custom product attribute set to \'true\'.',
                    'default' => 'always',
                    'desc_tip' => true,
                    'options' => array(
                        'always' => 'Enable Always',
                        'all_items' => 'Enable Only When All Items can be Escrowed'
                    )
                ),
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'Pay with Escrow',
					'desc_tip'    => true,
				),
				'description'     => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'css'         => 'height:100px; width:400px;',
                    'description' => 'Payment method description that the customer will see on your checkout page.',
                    'default'     => 'When you pay with Vesicash Escrow, the seller does not receive the fund untill you have received your order. When you place your order, an account will be created on Vesicash through which you will be able to complete the payment. Click on link below to continue your order.',
                    'desc_tip'    => true
                ),
				'v_private_key'   => array(
                    'title'       => 'Vesicash Private API Key', 
                    'type'        => 'password',
                    'description' => 'Enter your Vesicash Private API Key for the configured environment. If you have configured the plugin to call production, use a production API Key. If you have configured the plugin to call sandbox, use a sandbox API Key.',
                   'desc_tip'     => true
                ),
                'business_id'   => array(
                    'title'       => 'Vesicash Business ID', 
                    'type'        => 'password',
                    'description' => 'Enter your Vesicash Account ID. You can find it in the dashboard when you login to Vesicash.com.',
                   'desc_tip'     => true
				),
				'api_url' => array(
                    'title'       => 'API Environment URL',
                    'type'        => 'select',
                    'description' => 'Select the version of the Vesicash API that you wish to use. URLs with api.Vesicash.com are for production use. URLs with sandbox.api.vesicash.com are for testing. Make sure you update the vesicash Email and vesicash API Key to match the selected environment.',
                    'default'     => 'https://sandbox.api.vesicash.com/v1/',
                    'desc_tip'    => true,
                    'options'     => array(
                        'https://api.vesicash.com/v1/' => 'https://api.vesicash.com/v1/',
                        'https://sandbox.api.vesicash.com/v1/' => 'https://sandbox.api.vesicash.com/v1/',
                    )
				),
				'currency' => array(
                    'title' => 'Currency',
                    'type' => 'select',
                    'description' => 'Select the currency you wish to use for all transactions created via this plugin.',
                    'default' => 'NGN',
                    'desc_tip' => true,
                    'options' => array(
                        'NGN' => 'NGN',
                        'USD' => 'USD',
                    )
                ),
                'escrow_charge_bearer' => array(
                    'title' => 'Vesicash Escrow Fee Paid By',
                    'type' => 'select',
                    'description' => 'Select whether the vesicash fee is to be paid by the buyer, or the seller (that is you).',
                    'default' => 'seller',
                    'desc_tip' => true,
                    'options' => array(
                        'buyer' => 'Buyer',
                        'seller' => 'Seller',
                    )
                ),
                'inspection_period' => array(
                    'title' => 'Inspection Period',
                    'type' => 'select',
                    'description' => 'Select the inspection period for all transactions created via this plugin.',
                    'default' => '1 day',
                    'desc_tip' => true,
                    'options' => array(
                        '1 day' => '1 day',
                        '2 days' => '2 days',
                        '3 days' => '3 days',
                        '4 days' => '4 days',
                        '5 days' => '5 days',
                        '6 days' => '6 days',
                        '7 days' => '7 days',
                        '8 days' => '8 days',
                        '9 days' => '9 days',
                        '10 days' => '10 days',
                        '11 days' => '11 days',
                        '12 days' => '12 days',
                        '13 days' => '13 days',
                        '14 days' => '14 days',
                        '15 days' => '15 days',
                        '16 days' => '16 days',
                        '17 days' => '17 days',
                        '18 days' => '18 days',
                        '19 days' => '19 days',
                        '20 days' => '20 days'
                    )
                ),
                'shipping_fee' => array(
                    'title' => 'Shipping Fee',
                    'type' => 'number',
                    'description' => 'Set a custom shipping fee for all orders created via this plugin.',
                    'desc_tip' => true,
                ),
                'transaction_type' => array(
                    'title' => 'Transaction Type',
                    'type' => 'select',
                    'description' => 'Select the transaction type for all transactions created via this plugin.',
                    'default' => 'product',
                    'desc_tip' => true,
                    'options' => array(
                        'product' => 'Product'
                    )
                ),
                'due_date' => array(
                    'title' => 'Due Date',
                    'type' => 'select',
                    'description' => 'Set a custom due date for all transactions created via this plugin. This is the duration your customers should receive the order.',
                    'default' => '1 day',
                    'desc_tip' => true,
                    'options' => array(
                        '1 day' => '1 day',
                        '2 days' => '2 days',
                        '3 days' => '3 days',
                        '4 days' => '4 days',
                        '5 days' => '5 days',
                        '1 week' => '1 week',
                        '2 weeks' => '2 weeks',
                        '3 weeks' => '3 weeks',
                        '1 month' => '1 month',
                        '2 months' => '2 months',
                        '3 months' => '3 months',
                        '6 months' => '6 months',
                    )
                )
            ));

        }
        
        /**
         * Process the payment and return order receipt success page redirect.
         */
        public function process_payment($order_id) {

            // Get the order from the given ID.
            $order = wc_get_order($order_id);
            // Get the single-vendor non-broker request for the order.
            $request = $this->get_store_order($order);
            
            // Create a draft transaction on vesicash API.
            $response = $this->call_vesicash_api($request, 'transactions/create');



            if ($response == false && !$this->trans_details) {
                return;
            } elseif( !$response && @$this->trans_details->status == "error" ) {
                
                // Return related API error to user
                $errmsg = current( current( $this->trans_details->message ) );

                wc_add_notice( sprintf( "%s %s", __('Payment error:', 'vesicash'), $errmsg ), 'error' );
                return;
            }
            
            // Submit the order to Vesicash API with to update the order status.
            // $this->post_process_order($order, $response);
            
            // Reduce the stock levels and clear the cart.
            // $this->post_process_cart($order_id);

            //Redirect to appropriate checkout
            if ($this->api_url == 'https://api.vesicash.com/v1/') {
                return array(
                    'result' => 'success',
                    'redirect' => sprintf( "%s%s", 'https://admin.vesicash.com/checkout/', $this->trans_details->message->transaction->transaction_id )
                );
            }
            if ($this->api_url == 'https://sandbox.api.vesicash.com/v1/') {
                return array(
                    'result' => 'success',
                    'redirect' => sprintf( "%s%s", 'https://sandbox.vesicash.com/checkout/', $this->trans_details->message->transaction->transaction_id )
                );
            }
        }


        /**
         * Gets transaction request that will be posted to the vesicash API.
         *
         */
        private function get_store_order($order) {

            $v_private_key = $this->v_private_key;
            $api_url = $this->api_url;
            $seller_id = $this->business_id;
            $buyer_id = '';
            $charge_bearer = '';
            
            // Get properties from the order.
            $customer_email = $order->get_billing_email();
            $products = $order->get_items();
            $product_title  = get_bloginfo( 'title' ) . ' order ' . $order->get_order_number();

            // Build items array.
            $item_array = [];
            foreach ($products as $item_id => $item_data) {
                
                // Get the properties of the current item.
                $product       = wc_get_product($item_data['product_id']);
                $item_name     = $product->get_title();
                $item_quantity = wc_get_order_item_meta($item_id, '_qty', true);
                $item_total    = wc_get_order_item_meta($item_id, '_line_total', true);

                array_push($item_array, array(
                    'quantity' => (int) $item_quantity,
                    'amount'    => (float) $item_total,
                    'title' => $item_name,
                    )
                );
            }

            // Capture buyer details.
            $buyer_details = array(
                'email_address' => $customer_email,
                'firstname'    => $order->get_billing_first_name(),
                'lastname'     => $order->get_billing_last_name(),
                'phone_number'  => $order->get_billing_phone(),
                'username'  => $order->get_billing_first_name(),
                'password'  => $order->get_billing_phone()
            );

            //Try to Login buyer and retrieve account_id
            $login_buyer = wp_remote_post( $api_url . 'auth/login', array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'V-PRIVATE-KEY'=> $v_private_key,
                ),
                'sslverify' => true,
                'timeout' => 15,
                'body' => json_encode(array(
                    'username'  => $order->get_billing_first_name(),
                    'password'  => $order->get_billing_phone()
                ))
            ));
            
            $login_buyer = json_decode($login_buyer['body'], true);
            /**
             * if login_buyer has a value of account_id, get the value. 
             * Else sign user up
             */

            if( is_null ($login_buyer['user']['account_id'])) {
                
                //Create buyer profile and retrive the id
                $reg_buyer = wp_remote_post( $api_url . 'auth/signup', array(
                    'method' => 'POST',
                    'headers' => array(
                        // 'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'V-PRIVATE-KEY'=> $v_private_key
                    ),
                    'sslverify' => false,
                    'timeout' => 15,
                    'body' => json_encode($buyer_details)
                ));

                $reg_buyer = json_decode($reg_buyer['body']);

                if ( is_wp_error( $reg_buyer ) ) {
                    wc_add_notice( __('Signup error:', 'vesicash') . $reg_buyer->get_error_message(), 'error' );
                    return false;
                }

                if( $reg_buyer && $reg_buyer->status == "ok" ) {
                    $buyer_id = $reg_buyer->message->user->account_id;
                }
            }
            else{
                //Get the account_id of the logged buyer
                $buyer_id = $login_buyer['user']['account_id'];
            }
            if ($this->escrow_charge_bearer == 'buyer') {
                $charge_bearer = $buyer_id;
            }
            if ($this->escrow_charge_bearer == 'seller') {
                $charge_bearer = $seller_id;
            }
            
            // Build parties array for the transaction.
            $parties = array(array(
                    "buyer" => (int) $buyer_id,
                    "sender" => (int) $seller_id,
                    "seller" => (int) $seller_id,
                    "recipient" => (int) $buyer_id,
                    "charge_bearer" => (int) $charge_bearer
            ));

            // Build request.
            $request = array(
                'title' => $product_title,
                'description' => $product_title,
                'type' => $this->transaction_type,
                'products' => $item_array,
                'parties' => $parties,
                'currency' => $this->currency,
                'inspection_period' => $this->inspection_period,
                'shipping_fee' => (int) $this->shipping_fee,
                'due_date' => $this->due_date,
                'return_url' => $order->get_view_order_url()
            );

            // Return populated request.
            return $request;
        }

        /**
         * Make a post request to the Vesicash API.
         *
         */
        private function call_vesicash_api($request, $endpoint) {
            
            // Get properties relevant to the API call.
            $v_private_key = $this->v_private_key;
            $api_url = $this->api_url;
            
            $response = wp_remote_post( $api_url . $endpoint, array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'V-PRIVATE-KEY'=> $v_private_key,
                ),
                'sslverify' => true,
                'body' => json_encode($request)
            ));
            
            if ( is_wp_error( $response ) ) {
                wc_add_notice( __('Transaction Request error:', 'vesicash') . $response->get_error_message(), 'error' );
                return false;
            }

           $body = json_decode($response['body']);
        
           $this->trans_details = $body;

           if( $body && $body->status == "ok" ) {
                return true;
           }

           return false;
        }
        
        /**
         * Reduces stock levels and clears cart since order was successfully created.
         */
        private function post_process_cart($order_id) {
            
            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();
        }

        /** TO-DO - Version 1.0.1
         * Get results from API response to update order status in WC order.
         */
        private function post_process_order($order, $pay_response) {
            // Get response from API .
        }

    }
}