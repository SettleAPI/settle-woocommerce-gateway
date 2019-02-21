<?php
/*
  Plugin Name: Settle - WooCommerce Gateway
  Plugin URI: http://www.settle.eu
  Description: Extends WooCommerce by adding Settle Payment Gateway.
  Version: 0.5
  Author: Auka AS
  License: The MIT License (MIT)
*/

add_action('init', 'settle_woocommerce_real_init', 0);
function settle_woocommerce_real_init() {
    $domain = 'settle-woocommerce-gateway';
    load_plugin_textdomain($domain, false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'settle_woocommerce_init', 0);
function settle_woocommerce_init()
{
    // If the parent WC_Payment_Gateway class doesn't exist
    // it means WooCommerce is not installed on the site
    // so do nothing
    if (! class_exists('WC_Payment_Gateway') ) { return;
    }

    // If we made it this far, then include our Gateway Class
    include_once  'classes/settle-woocommerce.php' ;

    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter('woocommerce_payment_gateways', 'add_settle_woocommerce_gateway');
    function add_settle_woocommerce_gateway( $methods )
    {
        $methods[] = 'Settle_Woocommerce';
        return $methods;
    }

    add_action('woocommerce_order_actions', 'settle_woocommerce_order_actions');
    function settle_woocommerce_order_actions($actions)
    {
        $actions['settle_capture'] = "Manually capture Settle payment";
        return $actions;
    }

    add_action('woocommerce_order_action_settle_capture', 'settle_manually_capture_payment');
    function settle_manually_capture_payment($order)
    {
        $payment_gateway = new Settle_Woocommerce();
        $payment_gateway->manually_capture_payment($order);
    }


    // If we made it this far, then include our Gateway Class
    include_once  'classes/settle-express-woocommerce.php' ;

    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter('woocommerce_payment_gateways', 'add_settle_express_woocommerce_gateway');
    function add_settle_express_woocommerce_gateway( $methods )
    {
        $methods[] = 'Settle_Express_Woocommerce';
        return $methods;
    }

    add_action('woocommerce_before_cart', array('Settle_Express_Woocommerce', 'settle_express_cart_button_top'), 14);

}

// Add custom action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'settle_woocommerce_action_links');
function settle_woocommerce_action_links( $links )
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Settings', 'settle-woocommerce-gateway') . '</a>',
    );

    // Merge our new link with the default ones
    return array_merge($plugin_links, $links);
}

