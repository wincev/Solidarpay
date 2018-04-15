
 
<?php
/**
* Plugin Name: SolidarPay
* Plugin URI: https://solidar.it
* Description: A plugin to split payments between Solidar bot and fiat currencies via PayPal.
* Author: Hendrik Richter
* Version: 1.0
* License: GPLv2
*/

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

add_action('woocommerce_before_add_to_cart_button', 'solidar_show_sdrprice');

function solidar_show_sdrprice() {
  $price_field = get_post_meta(get_the_ID(), '_sdrprice_field', true);
  if (empty($price_field)) {
    return;
  }
  echo esc_attr('Solidar: ');
  echo esc_attr($price_field);
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
        $this->supports = array( 'default_credit_card_form' );
        $this->init_form_fields();
        $this->init_settings();
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }

        add_action( 'admin_notices', array( $this,    'do_ssl_check' ) );
        if ( is_admin() ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }
    } // Here is the  End __construct()

    // administration fields for specific Gateway
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'        => __( 'Enable / Disable', 'cwoa-authorizenet-aim' ),
                'label'        => __( 'Enable this payment gateway', 'cwoa-authorizenet-aim' ),
                'type'        => 'checkbox',
                'default'    => 'no',
            ),
            'title' => array(
                'title'        => __( 'Title', 'cwoa-authorizenet-aim' ),
                'type'        => 'text',
                'desc_tip'    => __( 'Payment title of checkout process.', 'cwoa-authorizenet-aim' ),
                'default'    => __( 'Solidar split payment', 'cwoa-authorizenet-aim' ),
            ),
            'description' => array(
                'title'        => __( 'Description', 'cwoa-authorizenet-aim' ),
                'type'        => 'textarea',
                'desc_tip'    => __( 'Payment title of checkout process.', 'cwoa-authorizenet-aim' ),
                'default'    => __( 'Solidar payment received.', 'cwoa-authorizenet-aim' ),
                'css'        => 'max-width:450px;'
            ),
            'merchant_id' => array(
                'title'        => __( 'Telegram merchant ID', 'cwoa-authorizenet-aim' ),
                'type'        => 'text',
                'desc_tip'    => __( 'Please start a group chat with our Telegram Solidar_Merchant_bot to open a merchant account.', 'cwoa-authorizenet-aim' ),
            ),
                        'solidar_payed_url' => array(
                                'title'         => __( 'Bot URL to send payment confirmations to.', 'cwoa-authorizenet-aim' ),
                                'type'          => 'text',
                                'desc_tip'      => __( 'URL where the Bot will send a confirmation to when the amount is payed.', 'cwoa-authorizenet-aim' ),
                        ),

            'environment' => array(
                'title'        => __( 'Authorize.net Test Mode', 'cwoa-authorizenet-aim' ),
                'label'        => __( 'Enable Test Mode', 'cwoa-authorizenet-aim' ),
                'type'        => 'checkbox',
                'description' => __( 'This is the test mode of gateway.', 'cwoa-authorizenet-aim' ),
                'default'    => 'no',
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
