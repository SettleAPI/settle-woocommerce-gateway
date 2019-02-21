<?php
/*
  Description: Extends WooCommerce by Adding Settle Gateway.
  Author: Settle AS
  License: The MIT License (MIT)
*/

if (! defined('ABSPATH') ) {
    exit; // Exit if accessed directly
}

global $settle_express_settings;
$settle_express_settings = get_option( 'woocommerce_settle_express_settings' );

require_once  'settle-woocommerce.php' ;
class Settle_Express_Woocommerce extends Settle_Woocommerce
{

    // Setup our Gateway's id, description and other values
    function __construct()
    {
        $this->id = "settle_express";
        $this->method_title = __("Settle Express", 'settle-woocommerce');
        $this->method_description = __("Settle Express Payment Gateway Plug-in for WooCommerce. Please notice that this needs free shipping.", 'settle-woocommerce');
        $this->title = __("Settle Express", 'settle-woocommerce');
        $this->init();

        add_action('wp_enqueue_scripts', array($this, 'settle_express_woocommerce_init_styles'));
        add_action('woocommerce_api_' . strtolower(get_class()), array( $this, 'settle_express_checkout' ));

        if ( $this->enabled && ($this->get_option('show_on_cart', 'bottom' ) === 'bottom')) {
            add_action('woocommerce_after_cart', array( $this, 'settle_express_button'), 20 );
        }

        if ( $this->enabled && ( $this->get_option('show_on_checkout', 'no') === 'yes')) {
            add_action('woocommerce_before_checkout_form', array( $this, 'settle_express_button'), 6 );
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
        add_action('woocommerce_review_order_before_submit', array( $this, 'hide_settle_express_on_checkout_page' ));
        }

    public function init_form_fields()
    {
        global $settle_settings;
        $this->form_fields = array(
            'enabled' => array(
                'title'     => __('Enable / Disable', 'settle-woocommerce-gateway'),
                'label'     => __('Enable this payment gateway', 'settle-woocommerce-gateway'),
                'type'      => 'checkbox',
                'default'   => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'settle-woocommerce-gateway'),
                'type'        => 'text',
                'description' => __('Payment title the customer will see during the checkout process.', 'settle-woocommerce-gateway'),
                'default'     => $this->method_title,
            ),
            'description' => array(
                'title'       => __('Description', 'settle-woocommerce-gateway'),
                'type'        => 'textarea',
                'description' => __('Payment description the customer will see during the checkout process.', 'settle-woocommerce-gateway'),
                'default'     => sprintf(__('Pay with %s', 'settle-woocommerce-gateway'), $this->method_title),
                'css'         => 'max-width:350px;'
            ),
            'copy_values_from_settle_gateway' => array(
                'title'     => __('Copy values from Settle plugin settings', 'settle-woocommerce-gateway'),
                'label'     => __('Copy values from Settle plugin settings', 'settle-woocommerce-gateway'),
                'type'      => 'checkbox',
                'description'  => __('If set to "yes", merchant id, merchant user id, key pair will be copied from Settle plugin settings.', 'settle-woocommerce-gateway'),
                'default'   => 'yes',
            ),
            'mid' => array(
                'title'     => __('merchant id', 'settle-woocommerce-gateway'),
                'type'      => 'text',
                'description'  => sprintf(__('This is the merchant id that was provided by Settle when you signed up for an account at %shttps://business.settle.eu/%s .', 'settle-woocommerce-gateway'), '<a href="https://business.settle.eu/">', '</a>'),
            ),
            'uid' => array(
                'title'     => __('merchant user id', 'settle-woocommerce-gateway'),
                'type'      => 'text',
                'description'  => sprintf(__('The merchant user created by you at %shttps://business.settle.eu/%s .', 'settle-woocommerce-gateway'), '<a href="https://business.settle.eu/">', '</a>'),
            ),
            'generate_new_rsa_keys' => array(
                'title'     => __('Generate new RSA keys', 'settle-woocommerce-gateway'),
                'label'     => __('Generate new RSA keys', 'settle-woocommerce-gateway'),
                'type'      => 'checkbox',
                'description'  => sprintf(__('If set to "yes", new keys will be generated, and you need to copy the public key to %shttps://business.settle.eu/%s .', 'settle-woocommerce-gateway'), '<a href="https://business.settle.eu/">', '</a>'),
                'default'   => 'no',
            ),
            'priv_key' => array(
                'title'     => __('Private RSA key', 'settle-woocommerce-gateway'),
                'type'      => 'textarea',
                'description'  => __('Your private RSA key. Keep it secret.', 'settle-woocommerce-gateway'),
                'css'       => 'max-width:600px; height: 350px;',
            ),
            'pub_key' => array(
                'title'     => __('Public RSA key', 'settle-woocommerce-gateway'),
                'type'      => 'textarea',
                'description'  => sprintf(__('Your public RSA key. Copy this to the corresponding field for your merchant user at %shttps://business.settle.eu/%s .', 'settle-woocommerce-gateway'), '<a href="https://business.settle.eu/">', '</a>'),
                'css'       => 'max-width:600px; height: 120px;',
            ),
            'autocapture' => array(
                'title'     => __('autocapture', 'settle-woocommerce-gateway'),
                'label'     => __('Capture an authorized payment automatically', 'settle-woocommerce-gateway'),
                'type'      => 'checkbox',
                'description' => __('Capture an authorized payment automatically. If not set, capture needs to be done in the order view within 72 hours, else the auth will expire and the money will be refunded.', 'settle-woocommerce-gateway'),
                'default'   => 'yes',
            ),
            'testmode' => array(
                'title'     => __('Test Mode', 'settle-woocommerce-gateway'),
                'label'     => __('Enable Test Mode', 'settle-woocommerce-gateway'),
                'type'      => 'checkbox',
                'description' => __('Place the payment gateway in test mode.', 'settle-woocommerce-gateway'),
                'default'   => 'no',
            ),
            'logging' => array(
                'title'     => __('Log Mode', 'settle-woocommerce-gateway'),
                'label'     => __('Enable logging', 'settle-woocommerce-gateway'),
                'type'      => 'checkbox',
                'default'   => 'no',
            ),
            'testbed_token' => array(
                'title'     => __('testbed_token', 'settle-woocommerce-gateway'),
                'type'      => 'text',
                'description'  => sprintf(__('When using Settle %stest environment%s , this token needs to be set', 'settle-woocommerce-gateway'), '<a href="https://sandbox.settle.eu/testbed/">', '</a>'),
                'disabled'  =>  ( $this->get_option('testmode', 'no') == 'no' ) ? true : false,
            ),
            'test_server' => array(
                'title'     => __('test_server', 'settle-woocommerce-gateway'),
                'type'      => 'text',
                'default'   => 'https://api-dot-settle-core-demo.appspot.com',
                'disabled'  => ( $this->get_option('testmode', 'no') == 'no' ) ? true : false,
                'description'  =>  __('Only concerns developers', 'settle-woocommerce-gateway')
            ),
            'show_on_cart' => array(
                'title' => __( 'Cart Page', 'settle-woocommerce-gateway'),
                'type' => 'select',
                'options' => array(
                    'top' => __( 'Display at the top of page.' , 'settle-woocommerce-gateway'),
                    'bottom' => __( 'Display at the bottom of page.' , 'settle-woocommerce-gateway') ),
                'default' => 'top',
				'description' => __( 'Display the express button on page' )
            ),
            'show_on_checkout' => array(
                'title' => __( 'Checkout Page', 'settle-woocommerce-gateway'),
                'type'      => 'checkbox',
                'default' => 'yes',
				'description' => __( 'Display the express button on page' )
            ),
            'min_amount' => array(
                'title'     => __('Minimum amount', 'settle-woocommerce-gateway'),
                'type'      => 'text',
                'default'   => '0.00',
                'description'  =>  __('Only show Settle Express button, when cart amount is bigger then this value', 'settle-woocommerce-gateway')
            ),
            'btn_picture' => array(
                'title' => __( 'Select picture for Settle Express button', 'settle-woocommerce-gateway'),
                'type' => 'select',
                'options' => array(
                    '/assets/images/settle_button_blue.svg'       => __( 'blue' , 'settle-woocommerce-gateway'),
                    '/assets/images/settle_button_purple.svg'     => __( 'purple' , 'settle-woocommerce-gateway'),
                    '/assets/images/settle_button_red.svg'        => __( 'red' , 'settle-woocommerce-gateway'),
                    '/assets/images/settle_button_white.svg'      => __( 'white' , 'settle-woocommerce-gateway') ),
                'default' => '/assets/images/settle_button_blue.svg'
            ),
        );
    }

    public function process_admin_options()
    {
        parent::process_admin_options();
        // Load form_field settings
        $settle_settings = get_option( 'woocommerce_settle_settings' );
        $settings = get_option($this->plugin_id . $this->id . '_settings', null);
        if ($settings['copy_values_from_settle_gateway'] == 'yes') {
            $settings['copy_values_from_settle_gateway'] = 'no';
            $settings['generate_new_rsa_keys'] = 'no';
            $settings['pub_key'] = $settle_settings['pub_key'];
            $settings['priv_key'] = $settle_settings['priv_key'];
            $settings['mid'] = $settle_settings['mid'];
            $settings['uid'] = $settle_settings['uid'];
            $settings['autocapture'] = $settle_settings['autocapture'];
            $settings['testmode'] = $settle_settings['testmode'];
            $settings['logging'] = $settle_settings['logging'];
            $settings['testbed_token'] = $settle_settings['testbed_token'];
            $settings['test_server'] = $settle_settings['test_server'];
        } elseif ($settings['generate_new_rsa_keys'] == 'yes') {
            $keyPair = $this->generate_key_pair();
            $settings['generate_new_rsa_keys'] = 'no';
            $settings['pub_key'] = $keyPair['pubKey'];
            $settings['priv_key'] = $keyPair['privKey'];
        }
        update_option($this->plugin_id . $this->id . '_settings', $settings);
        $this->log('process_admin_options() ' . $this->plugin_id . $this->id . '_settings');
    }

    // We have an Settle Express button on the previus page. We dont support Settle Expresss option checkout page
    function hide_settle_express_on_checkout_page()
    {
        ?>
        <style>.payment_method_settle_express {display:none !important;}</style>
        <?php
    }


    function settle_express_checkout($posted = null)
    {
        $this->log('settle_express_checkout()');
        if (!empty($posted) || ( isset( $_GET['action'] ) && $_GET['action'] == 'expresscheckout' && sizeof(WC()->cart->get_cart()) > 0) ) {
            if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) )
                define( 'WOOCOMMERCE_CHECKOUT', true );
            WC()->cart->calculate_totals();
            $order_id = WC()->checkout()->create_order();
            $order = wc_get_order($order_id);
            $rate = new WC_Shipping_Rate('free_shipping', __( 'Free Shipping', 'woocommerce' ), 0, array(), 'free_shipping');
            $shipping_id = $order->add_shipping( $rate );
            update_post_meta($order_id, '_payment_method',   $this->id);
            update_post_meta($order_id, '_payment_method_title',  $this->title);
            $required_scope = 'openid phone email shipping_address';
            $return = $this->payment_request($order_id, $required_scope);
            if( is_wp_error( $return ) ) {
                $this->log('settle_express_checkout()' . $return->get_error_message());
                wp_redirect(home_url() . '/error?m=' . urlencode($return->get_error_message()));
                exit();
            }else {
                wp_redirect($return->uri);
                exit();
            }
        }
        wp_redirect(get_permalink(wc_get_page_id('cart')));
        exit();
    }


    static function settle_express_button()
    {
        global $settle_express_settings;
        $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
        if (isset( $payment_gateways['settle_express'] ) && WC()->cart->total > (float) @$settle_express_settings['min_amount']) {
            echo '<div id="settle_express_button">';
            echo '<a class="settle_express_button" href="' . add_query_arg('action', 'expresscheckout', add_query_arg('wc-api', get_class(), home_url('/'))) . '">';
            echo '<img width="194" height="44" alt="Settle Express" src="' . WP_PLUGIN_URL . "/" . plugin_basename(dirname(dirname(__FILE__))) . @$settle_express_settings['btn_picture'] . '">';
            echo "</a>";
            echo '</div>';
        }
    }


    static function settle_express_cart_button_top()
    {
        wp_enqueue_style('settle_express', plugins_url('/assets/css/settle_express.css',  dirname(__FILE__)));
        global $settle_express_settings;
        $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
        if (isset( $payment_gateways['settle_express'] ) && (WC()->cart->total > (float) @$settle_express_settings['min_amount']) && @$settle_express_settings['show_on_cart']=='top') {
            echo '<div id="settle_express_button">';
            echo '<a class="settle_express_button" href="' . add_query_arg('action', 'expresscheckout', add_query_arg('wc-api', get_class(), home_url('/'))) . '">';
            echo '<img width="194" height="44" alt="Settle Express" src="' . WP_PLUGIN_URL . "/" . plugin_basename(dirname(dirname(__FILE__))) . @$settle_express_settings['btn_picture'] . '">';
            echo "</a>";
            echo '</div>';
        }
    }


    function settle_express_woocommerce_init_styles()
    {
        $this->log('settle_express_woocommerce_init_styles()');
        wp_register_script('settle_express', plugins_url('/assets/js/settle_express.js',  dirname(__FILE__)), array( 'jquery' ), WC_VERSION, true);
        wp_enqueue_style('settle_express', plugins_url('/assets/css/settle_express.css',  dirname(__FILE__)));
        wp_enqueue_script('settle_express');
    }


} // End of Settle_Express_Woocommerce
