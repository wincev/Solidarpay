<?php /** * Plugin Name: SolidarPay * Plugin URI: https://solidar.it * 
Description: A plugin to split payments between Solidar bot and fiat 
currencies via PayPal. * Author: Hendrik Richter * Version: 1.0 * 
License: GPLv2 */

// Display Fields
add_action('woocommerce_product_options_general_product_data', 'solidar_sdrprice_field');

// Save Fields
add_action('woocommerce_process_product_meta', 'solidar_sdrprice_field_save');

function solidar_sdrprice_field() {
  global $woocommerce, $post;
  echo '<div class="product_custom_field">';
  //Solidar Price Field
  woocommerce_wp_text_input(
    array(
      'id' => '_sdrprice_field',
      'placeholder' => 'Soldiar Price Field',
      'label' => __('Solidar price:'),
      'type' => 'number',
      'custom_attributes' => array(
        'step' => 'any',
        'min' => '0',
      ),
    )
  );
  echo '</div>';
}

function solidar_sdrprice_field_save($post_id) {
  // Custom Product Number Field
  $woocommerce_sdrprice_field = $_POST['_sdrprice_field'];
  if (!empty($woocommerce_sdrprice_field))
    update_post_meta($post_id, '_sdrprice_field', esc_attr($woocommerce_sdrprice_field));
}

// add solidar price on product page
add_action('woocommerce_get_price_html', 'solidar_show_sdrprice');
// add solidar price on shop overview page
add_action('woocommerce_after_shop_loop_item', 'solidar_show_sdrprice', 9 );

function solidar_show_sdrprice( $price ) {
  $price_field = get_post_meta(get_the_ID(), '_sdrprice_field', true);
  if (empty($price_field)) {
    return;
  }
  return $price .  "<span> <br/> Solidar: $price_field";
}

// Make sure WooCommerce is active

add_action('plugins_loaded', 'solidar_pay_gateway_init' );

function solidar_pay_gateway_init() {
    //if condition use to do nothin while WooCommerce is not installed
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	class solidar_pay_Gateway extends WC_Payment_Gateway {
	function __construct() {
		$this->id = "solidar_pay";
		$this->method_title = __( "Solidar split payment", 'solidar-pay-gateway' );
		$this->method_description = __( "Soliar split payments", 'solidar-pay-gateway' );
		$this->title = __( "Solidar split payment", 'solidar-pay-gateway' );
		$this->icon = null;
		$this->has_fields = true;
		$this->supports = array( 'products'); //default_credit_card_form' );
		$this->init_form_fields();
		$this->init_settings();
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
	} // Here is the  End __construct()

	// administration fields for specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'cwoa-authorizenet-aim' ),
				'label'		=> __( 'Enable this payment gateway', 'cwoa-authorizenet-aim' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'cwoa-authorizenet-aim' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title of checkout process.', 'cwoa-authorizenet-aim' ),
				'default'	=> __( 'Solidar split payment', 'cwoa-authorizenet-aim' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'cwoa-authorizenet-aim' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment title of checkout process.', 'cwoa-authorizenet-aim' ),
				'default'	=> __( 'Solidar payment received.', 'cwoa-authorizenet-aim' ),
				'css'		=> 'max-width:450px;'
			),
			'merchant_id' => array(
				'title'		=> __( 'Telegram merchant ID', 'cwoa-authorizenet-aim' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Please start a group chat with our Telegram Solidar_Merchant_bot to open a merchant account.', 'cwoa-authorizenet-aim' ),
			),
                        'solidar_payed_url' => array(
                                'title'         => __( 'Bot URL to send payment confirmations to.', 'cwoa-authorizenet-aim' ),
                                'type'          => 'text',
                                'desc_tip'      => __( 'URL where the Bot will send a confirmation to when the amount is payed.', 'cwoa-authorizenet-aim' ),
                        ),

			'environment' => array(
				'title'		=> __( 'Authorize.net Test Mode', 'cwoa-authorizenet-aim' ),
				'label'		=> __( 'Enable Test Mode', 'cwoa-authorizenet-aim' ),
				'type'		=> 'checkbox',
				'description' => __( 'This is the test mode of gateway.', 'cwoa-authorizenet-aim' ),
				'default'	=> 'no',
			)
		);
	}
	}
	//include_once( 'solidar-gateway-class.php' );
	// class add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'solidar_add_payment_gateway' );
	function solidar_add_payment_gateway( $methods ) {
		$methods[] = 'solidar_pay_Gateway';
		return $methods;
	}
}
// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'cwoa_authorizenet_aim_action_links' );
function cwoa_authorizenet_aim_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'cwoa-authorizenet-aim' ) . '</a>',
	);
	return array_merge( $plugin_links, $links );
}


//add total product price in cart
add_filter('woocommerce_get_item_data', 'display_cart_item_solidar_price', 11, 2);
function display_cart_item_solidar_price($item_data, $cart_item) {
  $item_id = $cart_item['variation_id'];
  if($item_id == 0) $item_id = $cart_item['product_id'];
  $product_qty = $cart_item['quantity'];
  $product = wc_get_product($item_id);
  $solidar_price = get_post_meta($item_id, '_sdrprice_field', true);
  $solidar_display = $solidar_price * $product_qty;
  $solidar_total = $solidar_price * $product_qty;

  $item_data[] = array(
    'key'     => __('SDR', 'woocommerce'),
    'value'   => $solidar_price,
    'display' => $solidar_display,
  );

  return $item_data;
}

// add subtotal Solidar amount to cart
add_filter('woocommerce_cart_subtotal', 'add_solidar_subtotal');
function add_solidar_subtotal($product_subtotal) {
  $solidar_total = 0;
  foreach( WC()->cart->get_cart() as $cart_item ){
    $product_id = $cart_item['product_id'];
    $product_qty = $cart_item['quantity'];
    $solidar_price = get_post_meta($product_id, '_sdrprice_field', true);
    $solidar_total = $solidar_total + $solidar_price * $product_qty;
  }
  if($solidar_total == 0)   return $product_subtotal;

  return $product_subtotal . '<br/>SDR: ' . $solidar_total;
}

//add solidar price to order
add_action( 'woocommerce_checkout_create_order_line_item', 'add_solidar_amount_to_order', 10, 4 );
function add_solidar_amount_to_order( $item, $cart_item_key, $values, $order ) {

  if ( empty( $values['iconic-engraving'] ) ) {
    return;
  }

    $item->add_meta_data( __( 'Engraving', 'iconic' ), $values['iconic-engraving'] );
}

//remove place order until solidar amount is payed
add_filter( 'woocommerce_order_button_html', 'filter_woocommerce_order_button_html', 10, 1 );
function filter_woocommerce_order_button_html( $input_type_submit_class_button_alt_name_woocommerce_chec$
    // make filter magic happen here...
    return; // $input_type_submit_class_button_alt_name_woocommerce_checkout_place_order_id_place_order_$
};

