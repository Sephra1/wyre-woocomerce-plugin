<?php
/**
 * Plugin Name
 *
 * @package     WyreWoo
 * @author      Wyre Technologies LLC.
 * 
 *
 * @wordpress-plugin
 * Plugin Name: Wyre WooCommerce
 * Plugin URI:  https://github.com/Wyre-Tech-Group/wyre-woocomerce-plugin
 * Description: Wyre plugin for WooCommerce.
 * Version:     1.0.0
 * Author:      Wyre Technologies LLC.
 * Author URI:  https://wyre.tech
 * Text Domain: wyre-woo
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

if ( ! defined( 'ABSPATH' ) )
    exit;

define( 'WPWOO_WYRE_BASE', __FILE__ );
define( 'WPWOO_WYRE_VERSION', '1.0.0' );

function wpwoo_wyre_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;
 
    require_once dirname( __FILE__ ) . '/includes/class.wyre.php';

}
add_action( 'plugins_loaded', 'wpwoo_wyre_init', 99 );

/**
 * Add Settings link to the plugin entry in the plugins menu
 **/
function WPWOO_Wyre_Plugin_action_links( $links ) {

    $settings_link = array(
        'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wyre-woo-plugin' ) . '" title="Wyre Settings">Settings</a>'
    );

    return array_merge( $links, $settings_link );

}
add_filter('plugin_action_links_' . plugin_basename( __FILE__ ), 'WPWOO_Wyre_Plugin_action_links' );


/**
 * Add Wyre Gateway to WC
 **/
function wpwoo_add_wyre_gateway($methods) {
    $methods[] = 'WPWOO_Wyre_Plugin';
    return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'wpwoo_add_wyre_gateway' );


function message() {

    if( get_query_var( 'order-received' ) ){

        $order_id 		= absint( get_query_var( 'order-received' ) );
        $order 			= wc_get_order( $order_id );
        $payment_method = method_exists( $order, 'get_payment_method' ) ? $order->get_payment_method() : $order->payment_method;

        if( is_order_received_page() &&  ( 'wpwoo_gateway' == $payment_method ) ) {

            $wyre_message 	= get_post_meta( $order_id, 'message', true );

            if( ! empty( $wyre_message ) ) {

                $message 			= $wyre_message['message'];
                $message_type 		= $wyre_message['message_type'];

                delete_post_meta( $order_id, 'message' );

                wc_add_notice( $message, $message_type );

            }
        }

    }

}
add_action( 'wp', 'message' );

/**
 * Check if wyre settings are filled
 */
function wpwoo_admin_notices() {

    $settings = get_option( 'woocommerce_wyre-woo-plugin_settings' );

    if ( $settings['enabled'] == 'no' ) {
        return;
    }

    // Check required fields
    if ( empty($settings['api_key'])) {
        echo '<div class="error"><p>' . sprintf( 'Please enter your Wyre API Key <a href="%s">here</a> to be able to use the payment plugin.', admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wyre-woocommerce-plugin' ) ) . '</p></div>';
        return;
    }

}
add_action( 'admin_notices', 'wpwoo_admin_notices' );
