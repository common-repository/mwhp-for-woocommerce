<?php

/**
 * Plugin Name: Merchant Warrior PAY.Link for WooCommerce
 * Plugin URI: https://www.merchantwarrior.com/
 * Description: Uses Merchant Warrior's PAY.Link to provide hosted payments.
 * Version: 0.0.1
 * Requires at least: 6.0
 * Requires PHP: 7.3
 * Author: BAKKBONE Australia
 * Author URI: https://www.bakkbone.com.au/
 * License: GNU General Public License (GPL) 3.0
 * License URI: https://www.gnu.org/licenses/gpl.html
 * Text Domain: mwhp-for-woocommerce
**/

if (!defined("WPINC")){
	die;
}

define("MHP_EXEC",true);

define("MHP_DEBUG",false);

define("MHP_FILE",__FILE__);

define("MHP_PATH",dirname(__FILE__));

define("MHP_URL",plugins_url("/",__FILE__));

add_filter( 'plugin_action_links_mwhp-for-woocommerce/mwhp-for-woocommerce.php', 'mhp_settings_link' );

if (!in_array("woocommerce/woocommerce.php", apply_filters("active_plugins", get_option("active_plugins")))){
	add_action("admin_notices", array($this,"mhpWoocommerceNotice"));
} else {
	add_filter( 'woocommerce_payment_gateways', 'mhp_add_gateway_class' );
	add_action( 'plugins_loaded', 'mhp_init_gateway_class' );

	function mhp_add_gateway_class( $gateways ) {
		$gateways[] = 'MW_PAYLink';
		return $gateways;
	}

	function mhp_settings_link( $links ) {
		$url = esc_url( add_query_arg( array( 'page' => 'wc-settings','tab' => 'checkout', 'section' => 'mwhp' ), admin_url('admin.php') ) );
		$settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}

	function mhp_init_gateway_class() {

		class MW_PAYLink extends WC_Payment_Gateway {

	 		public function __construct() {

				$this->id = 'mwhp';
				$this->icon = '';
				$this->has_fields = false;
				$this->method_title = __('Merchant Warrior PAY.Link','mwhp-for-woocommerce');
				$this->method_description = __('Create a branded hosted payments experience using PAY.Link','mwhp-for-woocommerce');

				$this->supports = array(
					'products'
				);

				$this->init_form_fields();

				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->enabled = $this->get_option( 'enabled' );
				$this->sandbox = 'yes' === $this->get_option( 'sandbox' );
				$this->uuid = $this->sandbox ? $this->get_option( 'sandbox_uuid' ) : $this->get_option( 'uuid' );
				$this->api_key = $this->sandbox ? $this->get_option( 'sandbox_api_key' ) : $this->get_option( 'api_key' );
				$this->api_passphrase = $this->sandbox ? $this->get_option( 'sandbox_api_passphrase' ) : $this->get_option( 'api_passphrase' );
				$this->uri = $this->sandbox ? 'https://base.merchantwarrior.com/paylink/' : 'https://api.merchantwarrior.com/paylink/';
				$this->init_settings();

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				add_action( 'woocommerce_api_mwhp', array( $this, 'webhook' ) );

	 		}

	 		public function init_form_fields(){

				$this->form_fields = array(
					'enabled' => array(
						'title'       => __('Enable/Disable','mwhp-for-woocommerce'),
						'label'       => __('Enable Merchant Warrior PAY.Link','mwhp-for-woocommerce'),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no'
					),
					'title' => array(
						'title'       => __('Title','mwhp-for-woocommerce'),
						'type'        => 'text',
						'description' => __('This controls the title which the user sees during checkout.','mwhp-for-woocommerce'),
						'default'     => __('Credit/Debit Card','mwhp-for-woocommerce'),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __('Description','mwhp-for-woocommerce'),
						'type'        => 'textarea',
						'description' => __('This controls the description which the user sees during checkout.','mwhp-for-woocommerce'),
						'default'     => __('Pay with your credit or debit card via our payments portal.','mwhp-for-woocommerce'),
					),
					'sandbox' => array(
						'title'       => __('Sandbox','mwhp-for-woocommerce'),
						'label'       => __('Enable Sandbox Mode','mwhp-for-woocommerce'),
						'type'        => 'checkbox',
						'description' => __('Place the payment gateway in sandbox mode using your sandbox MW API keys.','mwhp-for-woocommerce'),
						'default'     => 'yes',
						'desc_tip'    => true,
					),
					'sandbox_uuid' => array(
						'title'		  => __('Sandbox Merchant UUID','mwhp-for-woocommerce'),
						'type'		  => 'text'
					),
					'sandbox_api_key' => array(
						'title'       => __('Sandbox API Key','mwhp-for-woocommerce'),
						'type'        => 'text'
					),
					'sandbox_api_passphrase' => array(
						'title'       => __('Sandbox API Passphrase','mwhp-for-woocommerce'),
						'type'        => 'password',
					),
					'uuid' => array(
						'title'		  => __('Merchant UUID','mwhp-for-woocommerce'),
						'type'		  => 'text'
					),
					'api_key' => array(
						'title'       => __('Production API Key','mwhp-for-woocommerce'),
						'type'        => 'text'
					),
					'api_passphrase' => array(
						'title'       => __('Production API Passphrase','mwhp-for-woocommerce'),
						'type'        => 'password'
					)
				);
	
		 	}

			public function process_payment( $order_id ) {
				global $woocommerce;
				$order = new WC_Order($order_id);
				$method = 'processCard';
				$merchantUUID = $this->uuid;
				$apiKey = $this->api_key;
				$transactionAmount = $order->get_total();
				$transactionCurrency = $order->get_currency();
				$transactionProduct = get_bloginfo($show = 'name').' '.__('Order','mwhp-for-woocommerce');
				$referenceText = $order_id;
				$returnURL = $this->get_return_url($order);
				$notifyURL = site_url('/wc-api/mwhp');
				$urlHash = md5(md5(strtolower($this->api_passphrase)).strtolower($merchantUUID).strtolower($returnURL).strtolower($notifyURL));
				$hashSalt = 'selkjgbler';
				$hash = md5(md5(strtolower($this->api_passphrase)).strtolower($merchantUUID).$transactionAmount.strtolower($transactionCurrency));
				$customerName = $order->get_billing_first_name().' '.$order->get_billing_last_name();
				$customerCountry = $order->get_billing_country();
				$customerState = $order->get_billing_state();
				$customerCity = $order->get_billing_city();
				$customerAddress = $order->get_billing_address_2() !== null && $order->get_billing_address_2() !== '' ? $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() : $order->get_billing_address_1();
				$customerPostCode = $order->get_billing_postcode();
				$customerPhone = $order->get_billing_phone();
				$customerEmail = $order->get_billing_email();
			
				$args = array(
					'body'					=>	array(
						'method'				=>	$method,
						'merchantUUID'			=>	$merchantUUID,
						'apiKey'				=>	$apiKey,
						'transactionAmount'		=>	$transactionAmount,
						'transactionCurrency'	=>	$transactionCurrency,
						'transactionProduct'	=>	$transactionProduct,
						'referenceText'			=>	$referenceText,
						'returnURL'				=>	$returnURL,
						'notifyURL'				=>	$notifyURL,
						'urlHash'				=>	$urlHash,
						'hashSalt'				=>	$hashSalt,
						'hash'					=>	$hash,
						'customerName'			=>	$customerName,
						'customerCountry'		=>	$customerCountry,
						'customerState'			=>	$customerState,
						'customerCity'			=>	$customerCity,
						'customerAddress'		=>	$customerAddress,
						'customerPostCode'		=>	$customerPostCode,
						'customerPhone'			=>	$customerPhone,
						'customerEmail'			=>	$customerEmail,
						'custom1'				=>	$order_id
					)
				);
			
				$paylink = wp_remote_post($this->uri, $args);
				$throughput = simplexml_load_string($paylink['body']);
				$array = (array)$throughput;
				$order->update_meta_data('MW_PAY_Link',$array['linkURL']);
				if(!is_wp_error($paylink)){
		   			return array(
		   				'result' => 'success',
		   				'redirect' => $array['linkURL']
		   			);
				} else {
					wc_add_notice(  __('Connection error.','mwhp-for-woocommerce'), 'error' );
					return;
				}
			
		 	}
		
			public function webhook() {
			
				$input = file_get_contents('php://input');
				$xml = simplexml_load_string($input);
				$output = (array)$xml;
				$order = new WC_Order( $output['custom1'] );
				if($output['authResponseCode'] == "0"){
					$order->payment_complete();
	   				$order->add_order_note( __('Merchant Warrior Payment Successful, ID #','mwhp-for-woocommerce').$output['transactionID']."<br>".__('Receipt Number: ','mwhp-for-woocommerce').$output['receiptNo'].'<br>'.__('Response from MW: ','mwhp-for-woocommerce').$output['responseMessage'], false );
				} else {
					$order->add_order_note( __('Payment not successful. Response from MW: ', 'mwhp-for-woocommerce').$output['responseMessage'],false);
				}

			}
	 	}
	}	
}

function mhpWoocommerceNotice( $admin_notice){
	$plugin_data = get_plugin_data(MHP_FILE);
	echo '<div id="message-woocommerce" class="error notice is-dismissible">
		<p>'. sprintf(__('<strong>%s</strong> requires WooCommerce to be installed and activated on your site.','mwhp-for-woocommerce'), $plugin_data["Name"]).'</p>
	</div>';
	
}