<?php
/**
* Plugin Name: Simple Stripe WooCommerce
* Plugin URI: https://wordpress.org/plugins/simple-stripe-woocommerce/
* Description: This plugin adds a payment option in WooCommerce for customers to pay with their Credit Cards Via Stripe, creation of customers on stripe, relational to wp db, and triggerable charges
* Version: 1.0.0
* Author: Constantine Kiriaze
* Author URI: http://kiriaze.com/
* License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
function stripe_init() {

	if ( !class_exists('\Stripe\Stripe') ) {
		require_once( plugin_dir_path( __FILE__ ) . '/vendor/autoload.php');
	}

	function add_stripe_gateway_class( $methods ) {
		$methods[] = 'WC_Stripe_Gateway';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'add_stripe_gateway_class' );

	if ( class_exists('WC_Payment_Gateway') ) {
		class WC_Stripe_Gateway extends WC_Payment_Gateway {

			public function __construct() {

				$this->id                          = 'stripe';
				$this->icon                        = plugins_url( 'images/stripe.png' , __FILE__ ) ;
				$this->has_fields                  = true;
				$this->method_title                = 'Stripe Cards Settings';
				$this->init_form_fields();
				$this->init_settings();

				$this->supports                    = array( 'default_credit_card_form','products','refunds');

				$this->title                       = $this->get_option( 'stripe_title' );

				$this->stripe_test_secret_key      = $this->get_option( 'stripe_test_secret_key' );
				$this->stripe_live_secret_key      = $this->get_option( 'stripe_live_secret_key' );

				$this->stripe_test_publishable_key = $this->get_option( 'stripe_test_publishable_key' );
				$this->stripe_live_publishable_key = $this->get_option( 'stripe_live_publishable_key' );

				$this->stripe_sandbox              = $this->get_option( 'stripe_sandbox' );

				$this->stripe_publishable_key      = $this->stripe_sandbox ? $this->stripe_test_publishable_key : $this->stripe_live_publishable_key;
				$this->stripe_secret_key           = $this->stripe_sandbox ? $this->stripe_test_secret_key : $this->stripe_live_secret_key;

				$this->stripe_authorize_only       = $this->get_option( 'stripe_authorize_only' );
				$this->stripe_storecurrency        = $this->get_option( 'stripe_storecurrency' );
				$this->stripe_cardtypes            = $this->get_option( 'stripe_cardtypes');
				$this->stripe_enable_for_methods   = $this->get_option( 'stripe_enable_for_methods', array() );

				$this->stripe_zerodecimalcurrency  = array("BIF","CLP","DJF","GNF","JPY","KMF","KRW","MGA","PYG","RWF","VND","VUV","XAF","XOF","XPF");

				// if set to authorize only, charges will only be capturable through stripes dashboard
				if ( !defined("STRIPE_TRANSACTION_MODE") ) {
					define("STRIPE_TRANSACTION_MODE"  , ( $this->stripe_authorize_only == 'yes' ? false : true ));
				}

				// init stripe api
				\Stripe\Stripe::setApiKey($this->stripe_secret_key);

				if ( is_admin() ) {
					add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				}

			}

			public function admin_options() {
				?>
				<h3><?php _e( 'Simple Stripe for Woocommerce', 'woocommerce' ); ?></h3>
				<p><?php  _e( 'Stripe is a company that provides a way for individuals and businesses to accept payments over the Internet.', 'woocommerce' ); ?></p>
				<table class="form-table">
					<?php $this->generate_settings_html(); ?>
				</table>
				<?php
			}

			public function init_form_fields() {

				$shipping_methods = array();

				if ( is_admin() ) {
					foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
						$shipping_methods[ $method->id ] = $method->get_title();
					}
				}

				$this->form_fields = array(

					'enabled' => array(
						'title'   => __( 'Enable/Disable', 'woocommerce' ),
						'type'    => 'checkbox',
						'label'   => __( 'Enable Stripe', 'woocommerce' ),
						'default' => 'yes'
					),

					'stripe_title' => array(
						'title'       => __( 'Title', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
						'default'     => __( 'Stripe', 'woocommerce' ),
						'desc_tip'    => true
					),

					'stripe_test_secret_key' => array(
						'title'       => __( 'Test Secret Key', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'This is the Test Secret Key found in API Keys in Account Dashboard.', 'woocommerce' ),
						'default'     => '',
						'desc_tip'    => true,
						'placeholder' => 'Stripe Test Secret Key'
					),

					'stripe_test_publishable_key' => array(
						'title'       => __( 'Test Publishable Key', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'This is the Test Publishable Key found in API Keys in Account Dashboard.', 'woocommerce' ),
						'default'     => '',
						'desc_tip'    => true,
						'placeholder' => 'Stripe Test Publishable Key'
					),

					'stripe_live_secret_key' => array(
						'title'       => __( 'Live Secret Key', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'This is the Live Secret Key found in API Keys in Account Dashboard.', 'woocommerce' ),
						'default'     => '',
						'desc_tip'    => true,
						'placeholder' => 'Stripe Live Secret Key'
					),

					'stripe_live_publishable_key' => array(
						'title'       => __( 'Live Publishable Key', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'This is the Live Publishable Key found in API Keys in Account Dashboard.', 'woocommerce' ),
						'default'     => '',
						'desc_tip'    => true,
						'placeholder' => 'Stripe Live Publishable Key'
					),

					'stripe_storecurrency'    => array(
						'title'        => __('Fund Receiving Currency'),
						'type'     	   => 'select',
						'class'        => 'select',
						'css'          => 'width: 350px;',
						'desc_tip'     => __( 'Select the currency in which you like to receive payment the currency that has (*) is unsupported on  American Express Cards.This is independent of store base currency so please update your cart price accordingly.', 'woocommerce' ),
						'options'      => array( 'USD'=>' United States Dollar','AED'=>'United Arab Emirates Dirham','AFN'=>' Afghan Afghani*','ALL'=>' Albanian Lek','AMD'=>' Armenian Dram','ANG'=>' Netherlands Antillean Gulden','AOA'=>' Angolan Kwanza*','ARS'=>' Argentine Peso*','AUD'=>' Australian Dollar','AWG'=>' Aruban Florin','AZN'=>' Azerbaijani Manat','BAM'=>' Bosnia & Herzegovina Convertible Mark','BBD'=>' Barbadian Dollar','BDT'=>' Bangladeshi Taka','BGN'=>' Bulgarian Lev','BIF'=>' Burundian Franc','BMD'=>' Bermudian Dollar','BND'=>' Brunei Dollar','BOB'=>' Bolivian Boliviano*','BRL'=>' Brazilian Real*','BSD'=>' Bahamian Dollar','BWP'=>' Botswana Pula','BZD'=>' Belize Dollar','CAD'=>' Canadian Dollar','CDF'=>' Congolese Franc','CHF'=>' Swiss Franc','CLP'=>' Chilean Peso*','CNY'=>' Chinese Renminbi Yuan','COP'=>' Colombian Peso*','CRC'=>' Costa Rican Colón*','CVE'=>' Cape Verdean Escudo*','CZK'=>' Czech Koruna*','DJF'=>' Djiboutian Franc*','DKK'=>' Danish Krone','DOP'=>' Dominican Peso','DZD'=>' Algerian Dinar','EEK'=>' Estonian Kroon*','EGP'=>' Egyptian Pound','ETB'=>' Ethiopian Birr','EUR'=>' Euro','FJD'=>' Fijian Dollar','FKP'=>' Falkland Islands Pound*','GBP'=>' British Pound','GEL'=>' Georgian Lari','GIP'=>' Gibraltar Pound','GMD'=>' Gambian Dalasi','GNF'=>' Guinean Franc*','GTQ'=>' Guatemalan Quetzal*','GYD'=>' Guyanese Dollar','HKD'=>' Hong Kong Dollar','HNL'=>' Honduran Lempira*','HRK'=>' Croatian Kuna','HTG'=>' Haitian Gourde','HUF'=>' Hungarian Forint*','IDR'=>' Indonesian Rupiah','ILS'=>' Israeli New Sheqel','INR'=>' Indian Rupee*','ISK'=>' Icelandic Króna','JMD'=>' Jamaican Dollar','JPY'=>' Japanese Yen','KES'=>' Kenyan Shilling','KGS'=>' Kyrgyzstani Som','KHR'=>' Cambodian Riel','KMF'=>' Comorian Franc','KRW'=>' South Korean Won','KYD'=>' Cayman Islands Dollar','KZT'=>' Kazakhstani Tenge','LAK'=>' Lao Kip*','LBP'=>' Lebanese Pound','LKR'=>' Sri Lankan Rupee','LRD'=>' Liberian Dollar','LSL'=>' Lesotho Loti','LTL'=>' Lithuanian Litas','LVL'=>' Latvian Lats','MAD'=>' Moroccan Dirham','MDL'=>' Moldovan Leu','MGA'=>' Malagasy Ariary','MKD'=>' Macedonian Denar','MNT'=>' Mongolian Tögrög','MOP'=>' Macanese Pataca','MRO'=>' Mauritanian Ouguiya','MUR'=>' Mauritian Rupee*','MVR'=>' Maldivian Rufiyaa','MWK'=>' Malawian Kwacha','MXN'=>' Mexican Peso*','MYR'=>' Malaysian Ringgit','MZN'=>' Mozambican Metical','NAD'=>' Namibian Dollar','NGN'=>' Nigerian Naira','NIO'=>' Nicaraguan Córdoba*','NOK'=>' Norwegian Krone','NPR'=>' Nepalese Rupee','NZD'=>' New Zealand Dollar','PAB'=>' Panamanian Balboa*','PEN'=>' Peruvian Nuevo Sol*','PGK'=>' Papua New Guinean Kina','PHP'=>' Philippine Peso','PKR'=>' Pakistani Rupee','PLN'=>' Polish Złoty','PYG'=>' Paraguayan Guaraní*','QAR'=>' Qatari Riyal','RON'=>' Romanian Leu','RSD'=>' Serbian Dinar','RUB'=>' Russian Ruble','RWF'=>' Rwandan Franc','SAR'=>' Saudi Riyal','SBD'=>' Solomon Islands Dollar','SCR'=>' Seychellois Rupee','SEK'=>' Swedish Krona','SGD'=>' Singapore Dollar','SHP'=>' Saint Helenian Pound*','SLL'=>' Sierra Leonean Leone','SOS'=>' Somali Shilling','SRD'=>' Surinamese Dollar*','STD'=>' São Tomé and Príncipe Dobra','SVC'=>' Salvadoran Colón*','SZL'=>' Swazi Lilangeni','THB'=>' Thai Baht','TJS'=>' Tajikistani Somoni','TOP'=>' Tongan Paʻanga','TRY'=>' Turkish Lira','TTD'=>' Trinidad and Tobago Dollar','TWD'=>' New Taiwan Dollar','TZS'=>' Tanzanian Shilling','UAH'=>' Ukrainian Hryvnia','UGX'=>' Ugandan Shilling','UYU'=>' Uruguayan Peso*','UZS'=>' Uzbekistani Som','VND'=>' Vietnamese Đồng','VUV'=>' Vanuatu Vatu','WST'=>' Samoan Tala','XAF'=>' Central African Cfa Franc','XCD'=>' East Caribbean Dollar','XOF'=>' West African Cfa Franc*','XPF'=>' Cfp Franc*','YER'=>' Yemeni Rial','ZAR'=>' South African Rand','ZMW'=>' Zambian Kwacha'),
						'description'  => "<span style='color:red;'>Select the currency in which you like to receive payment the currency that has (*) is unsupported on  American Express Cards.This is independent of store base currency so please update your cart price accordingly.</span>",
						'default' => 'USD',
					),

					'stripe_sandbox' => array(
						'title'       => __( 'Stripe Sandbox', 'woocommerce' ),
						'type'        => 'checkbox',
						'label'       => __( 'Enable stripe sandbox (Live Mode if Unchecked)', 'woocommerce' ),
						'description' => __( 'If checked its in sanbox mode and if unchecked its in live mode', 'woocommerce' ),
						'desc_tip'    => true,
						'default'     => 'no'
					),

					'stripe_authorize_only' => array(
						'title'       => __( 'Authorize Only', 'woocommerce' ),
						'type'        => 'checkbox',
						'label'       => __( 'Enable Authorize Only Mode (Authorize & Capture If Unchecked)', 'woocommerce' ),
						'description' => __( 'If checked will only authorize the credit card only upon checkout.', 'woocommerce' ),
						'desc_tip'    => true,
						'default'     => 'no'
					),

					'stripe_cardtypes' => array(
						'title'    => __( 'Accepted Cards', 'woocommerce' ),
						'type'     => 'multiselect',
						'class'    => 'chosen_select',
						'css'      => 'width: 350px;',
						'desc_tip' => __( 'Select the card types to accept.', 'woocommerce' ),
						'options'  => array(
							'mastercard'       => 'MasterCard',
							'visa'             => 'Visa',
							'discover'         => 'Discover',
							'amex' 		       => 'American Express',
							'jcb'		       => 'JCB',
							'dinersclub'       => 'Dinners Club',
							),
						'default' => array( 'mastercard', 'visa', 'discover', 'amex' )
					),

					'stripe_enable_for_methods' => array(
						'title'             => __( 'Enable for shipping methods', 'woocommerce' ),
						'type'              => 'multiselect',
						'class'             => 'wc-enhanced-select',
						'css'               => 'width: 450px;',
						'default'           => '',
						'description'       => __( 'If Stripe is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce' ),
						'options'           => $shipping_methods,
						'desc_tip'          => true,
						'custom_attributes' => array(
							'data-placeholder' => __( 'Select shipping methods', 'woocommerce' )
						)
					)

				);
			} // end init_form_fields()

			// Get Card Types
			function get_card_type($number) {

				$number = preg_replace('/[^\d]/', '', $number);

				if ( preg_match('/^3[47][0-9]{13}$/', $number) ) {
					return 'amex';
				} elseif ( preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/', $number) ) {
					return 'dinersclub';
				} elseif ( preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/', $number) ) {
					return 'discover';
				} elseif ( preg_match('/^(?:2131|1800|35\d{3})\d{11}$/', $number) ) {
					return 'jcb';
				} elseif ( preg_match('/^5[1-5][0-9]{14}$/', $number) ) {
					return 'mastercard';
				} elseif ( preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number) ) {
					return 'visa';
				} else {
					return 'unknown card';
				}

			} // end get_card_type()

			// Function to check IP
			function get_client_ip() {

				$ipaddress = '';

				if ( getenv('HTTP_CLIENT_IP') )
					$ipaddress = getenv('HTTP_CLIENT_IP');
				else if ( getenv('HTTP_X_FORWARDED_FOR') )
					$ipaddress = getenv('HTTP_X_FORWARDED_FOR');
				else if ( getenv('HTTP_X_FORWARDED') )
					$ipaddress = getenv('HTTP_X_FORWARDED');
				else if ( getenv('HTTP_FORWARDED_FOR') )
					$ipaddress = getenv('HTTP_FORWARDED_FOR');
				else if ( getenv('HTTP_FORWARDED') )
					$ipaddress = getenv('HTTP_FORWARDED');
				else if ( getenv('REMOTE_ADDR') )
					$ipaddress = getenv('REMOTE_ADDR');
				else
					$ipaddress = '0.0.0.0';

				return $ipaddress;

			} // end get_client_ip()

			// Is available
			public function is_available() {

				$order = null;

				if ( ! empty( $this->stripe_enable_for_methods ) ) {

					// Only apply if all packages are being shipped via local pickup
					$chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

					if ( isset( $chosen_shipping_methods_session ) ) {
						$chosen_shipping_methods = array_unique( $chosen_shipping_methods_session );
					} else {
						$chosen_shipping_methods = array();
					}

					$check_method = false;

					if ( is_object( $order ) ) {
						if ( $order->shipping_method ) {
							$check_method = $order->shipping_method;
						}
					} elseif ( empty( $chosen_shipping_methods ) || sizeof( $chosen_shipping_methods ) > 1 ) {
						$check_method = false;
					} elseif ( sizeof( $chosen_shipping_methods ) == 1 ) {
						$check_method = $chosen_shipping_methods[0];
					}

					if ( ! $check_method ) {
						return false;
					}

					$found = false;

					foreach ( $this->stripe_enable_for_methods as $method_id ) {
						if ( strpos( $check_method, $method_id ) === 0 ) {
							$found = true;
							break;
						}
					}

					if ( ! $found ) {
						return false;
					}
				}

				return parent::is_available();

			} // end is_available()

			// Get icon
			public function get_icon() {

				$icon = '';

				if ( is_array($this->stripe_cardtypes) ) {
					foreach ( $this->stripe_cardtypes as $card_type ) {
						if ( $url = $this->stripe_get_active_card_logo_url( $card_type ) ) {
							$icon .= '<img src="'.esc_url( $url ).'" alt="'.esc_attr( strtolower( $card_type ) ).'" />';
						}
					}
				} else {
					$icon .= '<img src="'.esc_url( plugins_url( 'images/stripe.png' , __FILE__ ) ).'" alt="Stripe Gateway" />';
				}

				return apply_filters( 'woocommerce_stripe_icon', $icon, $this->id );
			}

			public function stripe_get_active_card_logo_url( $type ) {
				$image_type = strtolower( $type );
				return  WC_HTTPS::force_https_url( plugins_url( 'images/' . $image_type . '.jpg' , __FILE__ ) );
			}

			// Process Payment
			public function process_payment( $order_id ) {

				global $error;
				global $woocommerce;

				$wc_order 	    = wc_get_order( $order_id );
				$grand_total 	= $wc_order->order_total;

				if ( in_array( $this->stripe_storecurrency, $this->stripe_zerodecimalcurrency ) ) {
					$amount = number_format( $grand_total, 0, ".", "");
				} else {
					$amount = $grand_total * 100;
				}

				$cardtype = $this->get_card_type(sanitize_text_field( str_replace(' ', '', $_POST['stripe-card-number']) ));

				$current_user = wp_get_current_user();
				$customerID   = get_post_meta( $current_user->ID, 'customer_id', true );

				if ( !in_array( $cardtype, $this->stripe_cardtypes ) ) {
					wc_add_notice('Merchant does not support accepting in '. $cardtype,  $notice_type = 'error' );
					return array (
						'result'   => 'success',
						'redirect' => WC()->cart->get_checkout_url()
					);
					die;
				}

				try {

					$exp_date         = explode( "/", sanitize_text_field( $_POST['stripe-card-expiry'] ));
					$exp_month        = str_replace( ' ', '', $exp_date[0]);
					$exp_year         = str_replace( ' ', '', $exp_date[1]);

					if ( strlen( $exp_year ) == 2 ) {
						$exp_year += 2000;
					}

					// create token for customer/buyer credit card
					$token = \Stripe\Token::create(array(
						"card" => array(
							'number' 	     	=> sanitize_text_field( str_replace(' ', '', $_POST['stripe-card-number']) ),
							'cvc' 				=> sanitize_text_field( $_POST['stripe-card-cvc'] ),
							'exp_month' 		=> $exp_month,
							'exp_year' 			=> $exp_year,
							'name'  			=> $wc_order->billing_first_name . ' ' . $wc_order->billing_last_name,
							'address_line1'		=> $wc_order->billing_address_1 ,
							'address_line2'		=> $wc_order->billing_address_2,
							'address_city'		=> $wc_order->billing_city,
							'address_state'		=> $wc_order->billing_state,
							'address_zip'		=> $wc_order->billing_postcode,
							'address_country'	=> $wc_order->billing_country
						)
					));

					// check if user already has a customer ID
					// if they dont, create customer
					if ( empty( $customerID ) ) {
						// Create a Customer
						$customer = \Stripe\Customer::create(array(
							'email'       => $wc_order->billing_email,
							'source'      => $token,
							'description' => $current_user->first_name . ' ' . $current_user->last_name
							// username: $current_user->user_login
						));
						// save customer_id for current user meta
						update_post_meta( $current_user->ID, 'customer_id', $customer->id );
					}

					// if card valid, and if charge paid, add note of payment completion, empty cart, and redirect to order summary - else error notice
					if ( $token != '' ) {

						// add check here if card entered is different than whats tied to the customer, and if it is - add it or replace current card? then update customer on stripe with new card(s)

						// We handle the charge outside the payment gateway in order for charges to happen when we want them to, but we must fake payment_complete passing in the transaction id in order for woocommerce to move along

						// when payment is complete, this function is called payment_complete()
						// Most of the time this should mark an order as 'processing' so that admin can process/post the items
						// If the cart contains only downloadable items then the order is 'completed' since the admin needs to take no action
						// Stock levels are reduced at this point
						// Sales are also recorded for products
						// Finally, record the date of payment
						$wc_order->payment_complete($customerID); // originally set to $chargeid
						WC()->cart->empty_cart();

						return array (
							'result'   => 'success',
							'redirect' => $this->get_return_url( $wc_order )
						);

					}

				} // end try

				catch ( Exception $e ) {

					$body         = $e->getJsonBody();
					$error        = $body['error']['message'];

					$wc_order->add_order_note( __( 'Stripe payment failed due to.' . $error, 'woocommerce' ) );
					wc_add_notice($error, $notice_type = 'error' );

				}

			} // end of function process_payment()

			// process refund function
			public function process_refund( $order_id, $amount = NULL, $reason = '' ) {

				if ( $amount > 0 ) {

					$CHARGE_ID 		= get_post_meta( $order_id , '_transaction_id', true );
					$charge 		= \Stripe\Charge::retrieve($CHARGE_ID);

					$refund 		= $charge->refunds->create(
						array(
							'amount' 		=> $amount * 100,
							'metadata'	=> array(
								'Order #' 		=> $order_id,
								'Refund reason' => $reason
							)
						)
					);

					if ( $refund ) {

						$repoch      = $refund->created;
						$rdt         = new DateTime("@$repoch");
						$rtimestamp  = $rdt->format('Y-m-d H:i:s e');
						$refundid    = $refund->id;
						$wc_order    = new WC_Order( $order_id );
						$wc_order->add_order_note( __( 'Stripe Refund completed at. '. $rtimestamp .' with Refund ID = '. $refundid , 'woocommerce' ) );

						return true;

					} else {
						return false;
					}

				} else {
					return false;
				}

			} // end of  process_refund()

		} // end of class WC_Stripe_Gateway

	} // end of if class exist WC_Gateway




// \Stripe\Stripe::setApiKey("sk_test_2DcoV11I0PQl4ygpFUuuQOMa");
// // $cu = \Stripe\Customer::retrieve("cus_74ivWhDdtvd0N5");
// // $cu->description = "test update desc";
// // $cu->save();

// $cu = \Stripe\Customer::retrieve("cus_74ivWhDdtvd0N5");
// $card = $cu->sources->retrieve("card_16qZTZDSe6V7KL4aKPyxVbOf");
// $card->name = "Constantine Kiriaze";
// $card->save();


	add_action('woocommerce_order_status_completed',  'simple_stripe_order_status_completed' );
	function simple_stripe_order_status_completed( $order_id = null ) {
		
		global $woocommerce;
		global $error;
		$wc_order = new WC_Order( $order_id );
		
		$options = get_option('woocommerce_stripe_settings', array());
		$data    = get_post_meta( $order_id );
		$tid     = $data['_transaction_id'][0];
		$total   = $data['_order_total'][0] * 100;
		$authcap = $options['stripe_authorize_only'] == 'yes' ? false : true;
		$s_key   = $options['stripe_sandbox'][0] ? $options['stripe_test_secret_key'] : $options['stripe_live_secret_key'];
		$params  = array();
		
		// if authorize and capture enabled ( default ), capture charges once order is completed
		\Stripe\Stripe::setApiKey($s_key);
		try {

			$charge = \Stripe\Charge::create(array(
				'amount' 	     		=> $total,
				'currency' 				=> 'USD', // $option['stripe_storecurrency'][0]
				'customer'				=> $tid, // transaction id is same as customer id meta
				'capture'				=> $authcap,
				'statement_descriptor'  => 'Order#' . $wc_order->get_order_number(),
				'metadata' 				=> array(
					'Order #' 	  		=> $wc_order->get_order_number(),
					'Total Tax'      	=> $wc_order->get_total_tax(),
					'Total Shipping' 	=> $wc_order->get_total_shipping(),
					'WP customer #'  	=> $wc_order->user_id,
					'Billing Email'  	=> $wc_order->billing_email,
				),
				'receipt_email'         => $wc_order->billing_email,
				'description'  			=> get_bloginfo('blogname') . ' Order #' . $wc_order->get_order_number(),
				'shipping' 		    	=> array(
					'address' => array(
						'line1'			=> $wc_order->shipping_address_1,
						'line2'			=> $wc_order->shipping_address_2,
						'city'			=> $wc_order->shipping_city,
						'state'			=> $wc_order->shipping_state,
						'country'		=> $wc_order->shipping_country,
						'postal_code'	=> $wc_order->shipping_postcode
					),
					'name' => $wc_order->shipping_first_name . ' ' . $wc_order->shipping_last_name,
					'phone'=> $wc_order->billing_phone
				)
			));

			if ( $charge->paid == true ) {

				$epoch     = $charge->created;
				$dt        = new DateTime("@$epoch");
				$timestamp = $dt->format('Y-m-d H:i:s e');
				$chargeid  = $charge->id;

				$wc_order->add_order_note(__( 'Stripe payment completed at-'. $timestamp .'-with Charge ID='. $chargeid ,'woocommerce'));

			} else {
				$wc_order->add_order_note( __( 'Stripe payment failed.'. $error, 'woocommerce' ) );
				wc_add_notice($error, $notice_type = 'error' );
			}

		}
		catch( \Stripe\Error $e ) {
			// There was an error
			$body = $e->getJsonBody();
			$err  = $body['error'];
		
			if ( $this->logger )
			$this->logger->add('striper', 'Stripe Error:' . $err['message']);
			
			wc_add_notice(__('Payment error:', 'striper') . $err['message'], 'error');
			
			return null;
		}
		return true;
	}


} // stripe_init


// Activation hook
add_action( 'plugins_loaded', 'stripe_init' );

function simple_stripe_woocommerce_activate() {
	if ( !function_exists('curl_exec') ) {
		wp_die( '<pre>This plugin requires PHP CURL library installled in order to be activated </pre>' );
	}
}
register_activation_hook( __FILE__, 'simple_stripe_woocommerce_activate' );
// end activation hook

// Plugin Settings Link
function simple_stripe_woocommerce_settings_link( $links ) {
	$settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_stripe_gateway">' . __( 'Settings' ) . '</a>';
	array_push( $links, $settings_link );
	return $links;
}

$plugin = plugin_basename( __FILE__ );

add_filter( "plugin_action_links_$plugin", 'simple_stripe_woocommerce_settings_link' );
// Plugin Settings Link
