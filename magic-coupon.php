<?php
/**
 * Plugin Name:          Magic URL Coupon for WooCommerce
 * Plugin URI:
 * Description:          Pass a WooCommerce coupon code via URL and display the product prices as if the coupon has been applied to them. Coupon is automatically added to the cart alongside the products.
 * Version:              2.1
 * Author:               Naked Cat Plugins (by Webdados)
 * Author URI:           https://nakedcatplugins.com
 * Text Domain:          magic-coupon
 * Requires at least:    5.8
 * Tested up to:         6.9
 * Requires PHP:         7.2
 * WC requires at least: 7.1
 * WC tested up to:      9.9
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
