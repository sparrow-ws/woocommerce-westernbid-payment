<?php

/**
 * Plugin Name: WooCommerce WesternBid Payment
 * Description: WooCommerce PayPal Payments with WesternBid
 * Author: SPARROW STUDIO
 * Author URI: https://sparrow.ws/home
 * Version: 1.0.0
 *
 * Copyright: (c) 2023 SPARROW
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check active WooCommerce
// if ( ! is_woocommerce_active() ) {
//	 return;
// }

define( 'WC_WESTERNBID_VERSION', '1.0.0' );
define( 'WC_WESTERNBID_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_WESTERNBID_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'wc_westernbid_init_gateway_class' );
function wc_westernbid_init_gateway_class() {
	// wc_westernbid_load_textdomain();

	include_once( WC_WESTERNBID_PLUGIN_PATH . 'class-westernbid.php' );
}

// registers php class as woocommerce payment gateway
add_filter( 'woocommerce_payment_gateways', 'wc_westernbid_add_gateway_class' );
function wc_westernbid_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Gateway_Westernbid';

	return $gateways;
}

/* translations
function wc_westernbid_load_textdomain() {
	$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
	$locale = apply_filters( 'plugin_locale', $locale, 'westernbid' );
	load_textdomain( 'westernbid', WP_LANG_DIR . '/woocommerce-westernbid-payment/languages/' . $locale . '.mo' );
	load_plugin_textdomain( 'westernbid', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
}
*/

if ( ! function_exists( 'dump' ) ) {
	function dump() {
		if ( get_current_user_id() == 6 && func_num_args() > 0 ) {
			$args = func_get_args();

			foreach ( $args as $arg ) {
				if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
					print_r( $arg );
				} else {
					echo '<pre>';
					var_dump( $arg );
					echo '</pre>';
				}
			}
		}
	}
}

