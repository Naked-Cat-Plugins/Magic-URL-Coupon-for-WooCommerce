<?php
/**
 * Plugin Name:          Magic URL Coupon for WooCommerce
 * Plugin URI:
 * Description:          Share WooCommerce discount links: pass a coupon code via URL, display sale prices on product pages, and auto-apply the coupon at checkout.
 * Version:              2.1
 * Author:               Naked Cat Plugins (by Webdados)
 * Author URI:           https://nakedcatplugins.com
 * Text Domain:          magic-coupon
 * Requires at least:    5.8
 * Tested up to:         7.0
 * Requires PHP:         7.2
 * WC requires at least: 7.1
 * WC tested up to:      10.7
 * Requires Plugins:     woocommerce
 **/

/* WooCommerce CRUD and HPOS ok */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/* HPOS Compatible */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

// Our main class
require_once plugin_dir_path( __FILE__ ) . '/includes/class-magic-coupon.php';
return new Magic_Coupon();

/* If you’re reading this you must know what you’re doing ;-) Greetings from sunny Portugal! */
