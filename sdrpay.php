<?php
/**
* Plugin Name: SolidarPay
* Plugin URI: https://solidar.it
* Description: A plugin to split payments between Solidar (FB bot payment) and fiat currencies
* Author: Hendrik Richter
* Version: 1.0
* License: GPLv2
*/
//require_once 'sdr-wc-cart.php';
// Display Fields
add_action('woocommerce_product_options_general_product_data', 'solidar_sdrprice_field');
function solidar_sdrprice_field() {
  global $woocommerce, $post;
  echo '<div class="product_custom_field">';
  //Merchant field
  woocommerce_wp_text_input(
    array(
      'id' => '_sdrmerchant_field',
      'placeholder' => 'Solidar Merchant Field',
      'label' => __('t.me/SolidarMerchant_bot ID:'),
      'type' => 'number',
      'custom_attributes' => array(
        'step' => 'any',
      ),
    )
  );
  echo '</div>';
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
// Save Fields
add_action('woocommerce_process_product_meta', 'solidar_sdrprice_field_save');
function solidar_sdrprice_field_save($post_id) {
  // Custom Product Number Field
  $woocommerce_sdrprice_field = $_POST['_sdrprice_field'];
  $woocommerce_sdrmerchant_field = $_POST['_sdrmerchant_field'];
  if (!empty($woocommerce_sdrmerchant_field)) {
    update_post_meta($post_id, '_sdrprice_field', esc_attr($woocommerce_sdrprice_field));
    update_post_meta($post_id, '_sdrmerchant_field', esc_attr($woocommerce_sdrmerchant_field));
  }
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
    'key'     => __('SDR'),
    'value'   => $solidar_price,
    'display' => $solidar_display,
  );
  return $item_data;
}
// add subtotal Solidar amount per merchant to cart
add_filter('woocommerce_cart_subtotal', 'add_solidar_subtotal');
function add_solidar_subtotal($product_subtotal) {
  $solidar_total = 0;
  $solidar_order = array();
  foreach( WC()->cart->get_cart() as $cart_item ){
    $solidar_total = 0;
    $product_id = $cart_item['product_id'];
    $product_qty = $cart_item['quantity'];
    $solidar_price = get_post_meta($product_id, '_sdrprice_field', true);
    $solidar_total = $solidar_total + $solidar_price * $product_qty;
    $solidar_per_item = $solidar_price * $product_qty;
    $solidar_merchant = get_post_meta($product_id, '_sdrmerchant_field', true);
    if( empty($solidar_order['solidarpay']['merchant'][$solidar_merchant]) ) {
      $solidar_order['solidarpay']['merchant'][$solidar_merchant] = $solidar_per_item;
    }
    else {
    $solidar_order['solidarpay']['merchant'][$solidar_merchant] = $solidar_order['solidarpay']['merchant'][$solidar_merchant] + $solidar_per_item;
    }
  }
  //Don't save solidar price if no Solidar price
  if($solidar_total == 0) {
    $rem_cont_arr = WC()->cart->get_removed_cart_contents();
    unset($rem_cont_arr['solidarpay']['merchant']);
    unset($rem_cont_arr['solidarpay']['pay_id']);
    WC()->cart->set_removed_cart_contents($rem_cont_arr);
    return $product_subtotal;
  }

  $rem_cont_arr = WC()->cart->get_removed_cart_contents();
  unset($rem_cont_arr['solidarpay']['merchant']);
  $new_cont_arr = array_merge($rem_cont_arr, $solidar_order);

  WC()->cart->set_removed_cart_contents($new_cont_arr);
  return $product_subtotal . '<br/>SDR: ' . $solidar_total;
}


//Deny place order until solidar amount is payed
add_action( 'woocommerce_order_button_html', 'filter_woocommerce_order_button_html', 10, 1 );
function filter_woocommerce_order_button_html( $button_place_order ) {
  $removed_contents =  WC()->cart->get_removed_cart_contents();
  $solidarpay = $removed_contents['solidarpay'];
  if (empty($solidarpay['pay_id']) && empty($solidarpay['merchant'])) {
    return $button_place_order;
  }
  $pay_array = array();

  if(!empty($solidarpay['merchant'])) {
    foreach($solidarpay['merchant'] as $merchant => $amount) {
      $temp_array = json_decode(file_get_contents("https://solidar.it/merchant/generatePaymentID.php?merchant=" . $merchant . "&amount=" . $amount));
      $pay_array['solidarpay']['pay_id']['error'] = $temp_array;
      if ( !empty($temp_array->error)) {
        $error = $temp_array->error;
        $pay_array['solidarpay']['pay_id']['error'] = "Error: $error, contact site Support!";
      } else {
        $pay_array['solidarpay']['pay_id'][$merchant . "_" . $amount] = $temp_array->paymentid;
      }
    }

    unset($removed_contents['solidarpay']['pay_id']);
    unset($removed_contents['solidarpay']['merchant']);
    $new_cont_arr = array_merge($removed_contents, $pay_array);
    WC()->cart->set_removed_cart_contents($new_cont_arr);
  }

  $get_payed_array =  WC()->cart->get_removed_cart_contents();

  echo 'To continue please first settle payments at m.me/solidar.winc:';
  $sync_pay_array = $get_payed_array;

  $final_string;
  foreach ($get_payed_array['solidarpay']['pay_id'] as $merchant => $pay_id) {
     $requestURL = "https://solidar.it/merchant/checkPaymentID.php?paymentid=";
     $array_ids = json_decode(file_get_contents($requestURL . $pay_id));
     if($array_ids->executed == true) {
		  $sync_pay_array['solidarpay']['payed_id'][$merchant] = $pay_id;
		  unset($sync_pay_array['solidarpay']['pay_id'][$merchant]);
     } else {
		if (strpos($pay_id, 'Error') !== false) {
		  $final_string = "</br>" . $pay_id;
		  break;
		} else {
		  $final_string = $final_string . "</br>$pay_id ;";
		}
     }
  }
  echo $final_string;

  WC()->cart->set_removed_cart_contents($sync_pay_array);
  $reload_checkout = get_permalink( wc_get_page_id( 'checkout' ) );
  echo '<a href="' . $reload_checkout . '"> </br>Continue to PLACE ORDER</a>';

  //add solidarpay ids to session
  $payed_ids = "";
  foreach( $sync_pay_array['solidarpay']['payed_id'] as $merchant => $payed_id ) {
    $payed_ids = $payed_ids . "Solidar_MerchantID_amount= $merchant; PayID: $payed_id\n";
  }
  WC()->session->set('solidarpay', $payed_ids);

  return;
}

// SAVE payment ids to order meta
add_action('woocommerce_checkout_create_order', 'save_solidarpay_ids', 10, 2);
function save_solidarpay_ids ( $order, $data ) {
  $payed_ids = WC()->session->get('solidarpay');
  if ( empty( $payed_ids ) ) {
    return;
  }
  $order->add_meta_data('Solidarpay', $payed_ids);
}

//show payment ids in Customer order detais view
add_action( 'woocommerce_order_details_after_order_table', 'display_pay_ids_in_order_details', 10, 1 );
function display_pay_ids_in_order_details($order){
  echo get_post_meta( $order->get_order_number(), 'Solidarpay', true) . "</br>";
}

?>
