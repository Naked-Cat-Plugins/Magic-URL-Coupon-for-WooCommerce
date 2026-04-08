<?php
/**
 * Magic Coupon Core Class
 *
 * This file contains the main Magic_Coupon class that powers the Magic Coupon plugin,
 * enabling automatic application of WooCommerce coupon discounts via URL parameters.
 *
 * The class handles the following core functionalities:
 * - Processing coupon codes from URL parameters
 * - Storing active coupons in browser cookies
 * - Price manipulation to display discounted prices on product pages
 * - Automatic coupon application when products are added to cart
 * - Custom HTML messages on product pages
 * - Integration with WooCommerce Subscriptions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Magic_Coupon class
 */
class Magic_Coupon {

	// phpcs:disable

	/* Variables */
	public $url_parameter                    = 'mcoupon';
	public $default_cookie_duration          = 30; // in minutes
	public $html_message_action_hook         = 'woocommerce_single_product_summary';
	public $html_message_action_priority     = 15;
	public $woocommerce_subscriptions_active = false;

	/* Current coupon */
	public $coupon   = false;
	public $validity = 0;

	// phpcs:enable

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks
	 */
	public function init_hooks() {
		add_action( 'init', array( $this, 'init_vars' ) );
		add_action( 'init', array( $this, 'check_mcoupon' ), 11 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'add_to_cart' ), PHP_INT_MAX, 6 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'after_cart_item_quantity_update' ), PHP_INT_MAX, 3 );
		add_filter( 'woocommerce_coupon_data_tabs', array( $this, 'woocommerce_coupon_data_tabs' ) );
		add_action( 'woocommerce_coupon_data_panels', array( $this, 'woocommerce_coupon_data_panels' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_save', array( $this, 'woocommerce_coupon_options_save' ), 10, 2 );
		add_action( 'wp', array( $this, 'init_html_message_action' ) );
	}

	/**
	 * Init vars
	 */
	public function init_vars() {
		// If someone wants to change the GET and COOKIE name
		$this->url_parameter = apply_filters( 'magic_coupon_url_parameter', $this->url_parameter );
		// Or the HTML position and priority
		$this->html_message_action_hook     = apply_filters( 'magic_coupon_html_message_action_hook', $this->html_message_action_hook );
		$this->html_message_action_priority = apply_filters( 'magic_coupon_html_message_action_priority', $this->html_message_action_priority );
		// WC Subscriptions
		$this->woocommerce_subscriptions_active = class_exists( 'WC_Subscriptions' );
	}

	/**
	 * Check URL and cookies for Magic Coupon codes
	 *
	 * Checks both URL parameters and browser cookies for valid coupon codes.
	 * URL parameters take precedence over cookies. When a coupon is found in
	 * URL parameters, it's processed and stored in cookies for persistence.
	 * If no URL parameter exists but a cookie is found, the coupon from the
	 * cookie is processed instead.
	 *
	 * @return void
	 */
	public function check_mcoupon() {
		// Check URL
		$mcoupon = isset( $_GET[ $this->url_parameter ] ) ? trim( sanitize_text_field( wp_unslash( $_GET[ $this->url_parameter ] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $mcoupon ) ) {
			$this->process_mcoupon( $mcoupon, true );
		} else {
			$mcoupon = isset( $_COOKIE[ $this->url_parameter ] ) ? trim( sanitize_text_field( wp_unslash( $_COOKIE[ $this->url_parameter ] ) ) ) : '';
			$this->process_mcoupon( $mcoupon );
		}
	}

	/**
	 * Process a Magic Coupon code
	 *
	 * Validates the coupon code, verifies it's eligible for Magic Coupon usage,
	 * sets up the necessary cookies, and configures the appropriate WooCommerce
	 * filters to modify product pricing and display.
	 *
	 * @param string $mcoupon   The coupon code to process.
	 * @param bool   $from_get  Whether the coupon came from URL parameters (true) or cookies (false)
	 *                          When true, the coupon is stored in a browser cookie for persistence
	 *                          When false, the coupon validity is read from existing cookies.
	 * @return void
	 */
	public function process_mcoupon( $mcoupon, $from_get = false ) {
		$coupon_id = wc_get_coupon_id_by_code( $mcoupon );
		if ( ! empty( $coupon_id ) ) {
			$coupon = new WC_Coupon( $mcoupon );
			if ( $this->coupon_is_valid( $coupon ) ) {
				// Set coupon
				$this->coupon = $coupon;
				// Set cookie
				if ( $from_get ) {
					// $this->validity will be set inside set_cookie
					$this->set_cookie( $mcoupon );
				} else {
					// On cookie, set validity
					$this->validity = isset( $_COOKIE[ $this->url_parameter . '_validity' ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $this->url_parameter . '_validity' ] ) ) : 0;
				}
				unset( $coupon );
				// Set actions
				$this->add_get_price_filter();
				$this->add_on_sale_filter();
				// Disable server side cache on some plugins
				$this->set_nocache();
			} else {
				$this->unset_cookie();
			}
		}
	}


	/**
	 * Adds price manipulation filters to WooCommerce
	 *
	 * Registers filters to modify product prices when a valid coupon is active,
	 * affecting regular products, variations, and compatibility with third-party
	 * price plugins like WooCommerce Tiered Price Table.
	 *
	 * @return void
	 */
	public function add_get_price_filter() {
		add_filter( 'woocommerce_product_get_price', array( $this, 'manipulate_get_price' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'manipulate_get_price' ), 10, 2 );
		// add_filter( 'woocommerce_get_variation_price', array( $this, 'manipulate_get_variation_price' ), 10, 4 ); // https://woocommerce.wordpress.com/2015/09/14/caching-and-dynamic-pricing-upcoming-changes-to-the-get_variation_prices-method/
		add_filter( 'woocommerce_variation_prices', array( $this, 'variation_prices' ), 10, 3 );
		// WooCommerce Tiered Price Table - https://wordpress.org/plugins/tier-pricing-table/
		add_filter( 'tier_pricing_table/price/product_price_rules', array( $this, 'tier_pricing_table_price_product_price_rules' ), 10, 3 );
	}

	/**
	 * Adds filter to mark products as "on sale" when coupon applies
	 *
	 * When a valid coupon is active, this filter causes eligible products to
	 * display the sale badge and apply sale styling, providing visual indication
	 * of the discount to customers.
	 *
	 * @return void
	 */
	public function add_on_sale_filter() {
		add_filter( 'woocommerce_product_is_on_sale', array( $this, 'manipulate_is_on_sale' ), 10, 2 );
	}

	/**
	 * Removes the "on sale" filter
	 *
	 * Temporarily removes the on-sale filter during certain validation operations
	 * to prevent infinite recursion when checking if a product is eligible for a coupon
	 * that excludes sale items.
	 *
	 * @return void
	 */
	public function remove_on_sale_filter() {
		remove_filter( 'woocommerce_product_is_on_sale', array( $this, 'manipulate_is_on_sale' ), 10, 2 );
	}

	/**
	 * Set constants to prevent caching by some plugins
	 */
	public static function set_nocache() {
		wc_maybe_define_constant( 'DONOTCACHEPAGE', true );
		wc_maybe_define_constant( 'DONOTCACHEOBJECT', true );
		wc_maybe_define_constant( 'DONOTCACHEDB', true );
		nocache_headers();
	}

	/**
	 * Validates if a coupon is eligible for Magic Coupon functionality
	 *
	 * Performs comprehensive validation checks on a WooCommerce coupon to determine
	 * if it can be used with the Magic Coupon plugin. Checks include:
	 * - Coupon existence and validity
	 * - Magic Coupon feature enabled for this coupon
	 * - Non-zero discount amount
	 * - Expiration date
	 * - Usage limits (global and per-user)
	 *
	 * This validation is more limited than WooCommerce's standard validation as some
	 * cart-specific checks (minimum spend, etc.) can't be performed at the product level.
	 *
	 * @param WC_Coupon $coupon  The coupon object to validate.
	 * @return bool  True if the coupon is valid for Magic Coupon use, false otherwise
	 */
	public function coupon_is_valid( $coupon ) {
		// Checks based on class-wc-discounts.php
		if ( $coupon ) {
			// Exists
			if ( ! $coupon->get_id() && ! $coupon->get_virtual() ) {
				return false;
			}
			// Enabled for us?
			if ( $coupon->get_meta( 'magic_coupon_enable' ) !== 'yes' ) {
				return false;
			}
			// Coupon amount
			if ( $coupon->get_amount() <= 0 ) {
				return false;
			}
			// Expired?
			if ( $coupon->get_date_expires() && time() > $coupon->get_date_expires()->getTimestamp() ) {
				return false;
			}
			// Usage limit
			if ( $coupon->get_usage_limit() > 0 && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) {
				return false;
			}
			// Usage limit per user - maybe doesn't make sense?
			$user_id = get_current_user_id();
			if ( $user_id && $coupon->get_usage_limit_per_user() > 0 && $coupon->get_data_store() ) {
				$date_store  = $coupon->get_data_store();
				$usage_count = $date_store->get_usage_by_user_id( $coupon, $user_id );
				if ( $usage_count >= $coupon->get_usage_limit_per_user() ) {
					return false;
				}
			}
			// Discount for product? //We can accept all types of coupons I gues...
			// if ( ! in_array( $coupon->get_discount_type(), array( 'percent', 'fixed_product' ) ) )
			// return false;
			// Minimum amount and maximum amount doesn't make a lot of sense here because the product is still not on the cart
			// OK then
			return apply_filters( 'magic_coupon_coupon_is_valid', true, $coupon );
		}
		return false;
	}

	/**
	 * Checks if a coupon is valid for a specific product
	 *
	 * Performs comprehensive validation to determine if a coupon can be applied
	 * to a specific product. This includes:
	 * - Basic coupon validity checks
	 * - Product type compatibility (especially for subscriptions)
	 * - Category inclusion/exclusion rules
	 * - Sale item exclusion rules
	 * - Product inclusion/exclusion rules
	 * - Special handling for product variations
	 *
	 * @param WC_Coupon $coupon      The coupon object to check.
	 * @param int       $product_id  The product ID to validate against the coupon.
	 * @return bool     True if the coupon is valid for the product, false otherwise
	 */
	public function coupon_is_valid_for_product( $coupon, $product_id ) {
		if ( $coupon ) {

			if ( ! $this->coupon_is_valid( $coupon ) ) {
				return false;
			}

			$product      = wc_get_product( $product_id );
			$variation    = null;
			$variation_id = null;
			// Variation?
			$product_parent_id = $product->get_parent_id();
			if ( ! empty( $product_parent_id ) ) {
				$variation    = $product;
				$product      = wc_get_product( $product_parent_id );
				$product_id   = $product->get_id();
				$variation_id = $variation->get_id();
			}

			if (
				$this->woocommerce_subscriptions_active
				&&
				in_array(
					$coupon->get_discount_type(),
					array(
						'recurring_fee',
						'recurring_percent',
						// 'sign_up_fee',
						// 'sign_up_fee_percent',
						// 'renewal_fee',
						// 'renewal_percent',
						// 'renewal_cart',
						// 'initial_cart'
					),
					true
				)
			) {
				// Single Subscription - no support for Variable Subscriptions yet
				if ( ! is_a( $product, 'WC_Product_Subscription' ) ) {
					return false;
				}
			}

			// All checks based on class-wc-discounts.php

			// Categories - Check product belongs to category
			$product_cats = null;
			if ( count( $coupon->get_product_categories() ) ) {
				$product_cats = wc_get_product_cat_ids( $product_id );
				if ( ! count( array_intersect( $product_cats, $coupon->get_product_categories() ) ) > 0 ) {
					return false;
				}
			}
			// Excluded Categories - Check product does not belongs to category
			if ( count( $coupon->get_excluded_product_categories() ) ) {
				if ( ! $product_cats ) {
					$product_cats = wc_get_product_cat_ids( $product_id );
				}
				if ( count( array_intersect( $product_cats, $coupon->get_excluded_product_categories() ) ) > 0 ) {
					return false;
				}
			}

			// Variation?
			if ( $variation ) {

				// It's a variation

				// On sale? - Variation on sale
				if ( $coupon->get_exclude_sale_items() ) {
					$this->remove_on_sale_filter();
					if ( $variation->is_on_sale() ) {
						$this->add_on_sale_filter();
						return false;
					} else {
						$this->add_on_sale_filter();
					}
				}
				// Product - Check variation is allowed
				if ( count( $coupon->get_product_ids() ) > 0 ) {
					if ( ! in_array( intval( $variation_id ), $coupon->get_product_ids() ) ) { // no third true argument because get_product_ids probably returns strings - to check
						// Check product
						if ( ! in_array( intval( $product_id ), $coupon->get_product_ids() ) ) { // no third true argument because get_product_ids probably returns strings - to check
							return false;
						}
					}
				}
				// Excluded product - Check if variation is excluded
				if ( count( $coupon->get_excluded_product_ids() ) > 0 ) {
					if ( in_array( intval( $variation_id ), $coupon->get_excluded_product_ids() ) ) { // no third true argument because get_excluded_product_ids probably returns strings - to check
						return false;
					} elseif ( in_array( intval( $product_id ), $coupon->get_excluded_product_ids() ) ) { // no third true argument because get_excluded_product_ids probably returns strings - to check
							return false;
					}
				}
			} else {

				// It's a single product

				// On sale?
				if ( $coupon->get_exclude_sale_items() ) {
					$this->remove_on_sale_filter();
					if ( $product->is_on_sale() ) {
						$this->add_on_sale_filter();
						return false;
					} else {
						$this->add_on_sale_filter();
					}
				}
				// Product - Check product is allowed
				if ( count( $coupon->get_product_ids() ) > 0 ) {
					if ( ! in_array( intval( $product_id ), $coupon->get_product_ids() ) ) { // no third true argument because get_product_ids probably returns strings - to check
						return false;
					}
				}
				// Excluded product - Check if product is excluded
				if ( count( $coupon->get_excluded_product_ids() ) > 0 ) {
					if ( in_array( intval( $product_id ), $coupon->get_excluded_product_ids() ) ) { // no third true argument because get_excluded_product_ids probably returns strings - to checkly returns strings - to check
						return false;
					}
				}
			}

			// OK then
			return apply_filters( 'magic_coupon_coupon_is_valid_for_product', true, $coupon, $product_id );

		}

		return false;
	}

	/**
	 * Valid location?
	 */
	private function is_valid_location() {
		if (
			// No discount on cart - to avoid duplication
			is_cart()
			||
			// No discount on checkout - to avoid duplication
			is_checkout()
			||
			// No discount on WP Admin
			( is_admin() && ! wp_doing_ajax() )
			// Should we allow discounts anywhere else?
		) {
			return false;
		}
		return true;
	}

	/**
	 * Calculates discounted price based on coupon type
	 *
	 * Applies the appropriate discount calculation based on the coupon's discount type:
	 * - Percentage discounts (regular and recurring)
	 * - Fixed amount discounts (per product and recurring fees)
	 * - Third-party coupon type compatibility (e.g., Percentage Coupon per Product)
	 *
	 * @param float      $base_price    The original product price.
	 * @param WC_Product $product       The product object.
	 * @param int|null   $variation_id  Optional variation ID if the product is a variation.
	 * @return float     The discounted price after applying the coupon.
	 */
	private function calculate_discounted_price( $base_price, $product, $variation_id = null ) {
		if ( is_numeric( $base_price ) && floatval( $base_price ) > 0 ) {
			$coupon_amount = $this->coupon->get_amount();
			$discount      = null;
			switch ( $this->coupon->get_discount_type() ) {
				case 'percent':
				case 'recurring_percent':
					$discount = $base_price * ( $coupon_amount / 100 );
					break;
				case 'fixed_product':
				case 'recurring_fee':
					$discount = $coupon_amount;
					break;
				// “Percentage Coupon per Product for WooCommerce” compatibility
				case 'percent_per_product':
					if ( function_exists( 'Woo_Product_Percentage_Coupon' ) ) {
						$discount = Woo_Product_Percentage_Coupon()->get_discount_amount( $base_price, $this->coupon, $product, $variation_id ? wc_get_product( $variation_id ) : null );
					}
					break;
				default:
					$discount = $coupon_amount;
					break;
			}
			if ( $discount ) {
				$base_price = $discount < $base_price ? $base_price - $discount : 0;
			}
		}
		return $base_price;
	}

	/**
	 * Applies coupon discounts to product prices
	 *
	 * This method is hooked to WooCommerce's price filters and modifies product prices
	 * to reflect coupon discounts when a valid Magic Coupon is active. It first checks
	 * if the current page location is appropriate for price manipulation, then verifies
	 * if the product is eligible for the coupon before calculating the discounted price.
	 *
	 * @param float      $base_price  The original product price.
	 * @param WC_Product $_product  The product object.
	 * @return float  The modified price with any applicable discounts applied
	 */
	public function manipulate_get_price( $base_price, $_product ) {
		if ( $this->is_valid_location() ) {
			if ( $this->coupon_is_valid_for_product( $this->coupon, $_product->get_id() ) ) {
				$base_price = $this->calculate_discounted_price( $base_price, $_product );
			}
		}
		return $base_price;
	}

	/**
	 * Modifies variation prices to apply coupon discounts
	 *
	 * This method is hooked to WooCommerce's variation prices filter and applies
	 * coupon discounts to all eligible product variations. It iterates through each
	 * variation price and applies the discount if the variation is valid for the
	 * current coupon. This ensures that variable products correctly display discounted
	 * prices in price ranges and variation selection forms.
	 *
	 * @param array      $prices       Associative array of variation prices.
	 * @param WC_Product $product      The parent variable product.
	 * @param bool       $for_display  Whether the prices are for display purposes.
	 * @return array     Modified prices array with discounts applied.
	 */
	public function variation_prices( $prices, $product, $for_display ) {
		if ( $this->is_valid_location() ) {
			if ( is_array( $prices ) && isset( $prices['price'] ) && is_array( $prices['price'] ) ) {
				foreach ( $prices['price'] as $variation_id => $price ) {
					if ( $this->coupon_is_valid_for_product( $this->coupon, $variation_id ) ) {
						$prices['price'][ $variation_id ] = $this->calculate_discounted_price( floatval( $price ), $product, $variation_id );
					}
				}
			}
		}
		return $prices;
	}

	/**
	 * Marks products as "on sale" when a valid coupon applies
	 *
	 * This method is hooked to WooCommerce's product on-sale filter and modifies
	 * the sale status of products when they have a valid Magic Coupon applied.
	 * Marking products as "on sale" allows them to display sale badges and
	 * styling even when the discount comes from a coupon rather than a
	 * standard product sale price.
	 *
	 * @param bool       $is_on_sale  The original on-sale status of the product.
	 * @param WC_Product $product     The product object being checked.
	 * @return bool      Modified on-sale status including coupon-based discounts.
	 */
	public function manipulate_is_on_sale( $is_on_sale, $product ) {
		if ( $this->is_valid_location() ) {
			if ( $this->coupon_is_valid_for_product( $this->coupon, $product->get_id() ) ) {
				$is_on_sale = true;
			}
		}
		// Apply filter specific to the product and a global one with the proper arguments
		return apply_filters( 'magic_coupon_product_' . $product->get_id() . '_is_on_sale', apply_filters( 'magic_coupon_product_is_on_sale', $is_on_sale, $product, $this->coupon ) );
	}

	/**
	 * Applies coupon discounts to WooCommerce Tiered Price Table pricing
	 *
	 * This method integrates with the WooCommerce Tiered Price Table plugin to apply
	 * Magic Coupon discounts to tiered pricing rules. For each quantity tier defined,
	 * it calculates the appropriate discounted price when a valid coupon applies to
	 * the product.
	 *
	 * @param array  $rules      The original tiered pricing rules array (quantity => price).
	 * @param int    $product_id The product ID these pricing rules apply to.
	 * @param string $type      The type of pricing rule.
	 * @return array Modified pricing rules with discounts applied.
	 */
	public function tier_pricing_table_price_product_price_rules( $rules, $product_id, $type ) {
		if ( $this->is_valid_location() ) {
			if ( $this->coupon_is_valid_for_product( $this->coupon, $product_id ) ) {
				foreach ( $rules as $qty => $price ) {
					$rules[ $qty ] = $this->calculate_discounted_price( $price, wc_get_product( $product_id ) );
				}
			}
		}
		return $rules;
	}

	/**
	 * Sets browser cookies to store the active coupon code
	 *
	 * Creates cookies to preserve the coupon code and its validity period between page views.
	 * The duration is determined by either the coupon-specific setting or the plugin default.
	 * Also sets the internal validity timestamp for use elsewhere in the plugin.
	 *
	 * @param string $mcoupon The coupon code to store in the cookie.
	 * @return void
	 */
	public function set_cookie( $mcoupon ) {
		$duration = ( intval( $this->coupon->get_meta( 'magic_coupon_cookie_minutes' ) ) > 0 ? intval( $this->coupon->get_meta( 'magic_coupon_cookie_minutes' ) ) : $this->default_cookie_duration ) * 60;
		setcookie( $this->url_parameter, $mcoupon, time() + $duration, COOKIEPATH, COOKIE_DOMAIN );
		setcookie( $this->url_parameter . '_validity', time() + $duration, time() + $duration, COOKIEPATH, COOKIE_DOMAIN );
		// On get, set validity
		$this->validity = time() + $duration;
	}

	/**
	 * Removes the coupon cookies
	 *
	 * Deletes any existing Magic Coupon cookies by setting their expiration time
	 * to the past, effectively removing the stored coupon code and validity period
	 * from the user's browser.
	 *
	 * @return void
	 */
	public function unset_cookie() {
		setcookie( $this->url_parameter, '', time() - 1, COOKIEPATH, COOKIE_DOMAIN );
		setcookie( $this->url_parameter . '_validity', '', time() - 1, COOKIEPATH, COOKIE_DOMAIN );
	}

	/**
	 * Automatically applies valid coupons when products are added to cart
	 *
	 * When a product is added to the cart, this method checks if the product
	 * is eligible for the currently active Magic Coupon. If valid, it applies
	 * the coupon to the entire cart if not already applied, ensuring the
	 * customer receives their discount without having to manually enter the code.
	 *
	 * @param string $cart_item_key The cart item key for the added product.
	 * @param int    $product_id    The ID of the product being added.
	 * @param int    $quantity      The quantity of the product being added.
	 * @param int    $variation_id  The variation ID (if the product is a variable product).
	 * @param array  $variation     The variation data.
	 * @param array  $cart_item_data Additional cart item data.
	 * @return string The cart item key (unchanged).
	 */
	public function add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		if ( $this->coupon ) {
			// For variable products, check the variation ID if available
			$check_product_id = ( ! empty( $variation_id ) ) ? $variation_id : $product_id;

			if ( $this->coupon_is_valid_for_product( $this->coupon, $check_product_id ) ) {
				if ( ! in_array( $this->coupon->get_code(), WC()->cart->get_applied_coupons(), true ) ) {
					WC()->cart->add_discount( $this->coupon->get_code() );
				}
			}
		}
		return $cart_item_key;
	}

	/**
	 * Handles coupon application after a cart item's quantity is updated.
	 *
	 * When an item quantity increases, this method checks if the product is eligible
	 * for the currently loaded coupon. If valid, it applies the coupon to the cart
	 * if not already applied.
	 *
	 * @param string $cart_item_key The cart item key of the product being updated.
	 * @param int    $quantity      The new quantity of the item.
	 * @param int    $old_quantity  The previous quantity of the item.
	 */
	public function after_cart_item_quantity_update( $cart_item_key, $quantity, $old_quantity ) {
		if ( $this->coupon ) {
			if ( $quantity > $old_quantity ) {
				$item       = WC()->cart->get_cart_item( $cart_item_key );
				$product_id = isset( $item['variation_id'] ) && intval( $item['variation_id'] ) > 0 ? $item['variation_id'] : $item['product_id'];
				if ( $this->coupon_is_valid_for_product( $this->coupon, $product_id ) ) {
					if ( ! in_array( $this->coupon->get_code(), WC()->cart->get_applied_coupons(), true ) ) {
						WC()->cart->add_discount( $this->coupon->get_code() );
					}
				}
			}
		}
	}

	/**
	 * Initializes the HTML message functionality for Magic Coupon.
	 *
	 * This method handles the initialization of HTML messages that can be displayed on product pages
	 * or other locations when a valid coupon is present. It:
	 * 1. Checks if we're on a product page or if the filter allows display elsewhere
	 * 2. Verifies the coupon is valid for the current product
	 * 3. Confirms the coupon has a custom HTML message set
	 * 4. Registers the appropriate action hook and shortcode
	 *
	 * If conditions aren't met, it registers an empty shortcode to prevent display issues.
	 *
	 * @uses $this->coupon_is_valid_for_product() Validates if coupon applies to product
	 * @uses $this->html_message() Callback for displaying the HTML message
	 * @uses $this->html_message_shortcode() Callback for the shortcode implementation
	 * @return void
	 */
	public function init_html_message_action() {
		if ( ( is_product() || apply_filters( 'magic_coupon_show_html_message_outside_product_page', false ) ) && $this->coupon ) {
			global $post;
			if ( $this->coupon_is_valid_for_product( $this->coupon, $post->ID ) && $this->coupon->get_meta( 'magic_coupon_html_message' ) !== '' ) {
				add_action( $this->html_message_action_hook, array( $this, 'html_message' ), $this->html_message_action_priority );
				add_shortcode( 'magic_coupon_html_message', array( $this, 'html_message_shortcode' ) );
			} else {
				add_shortcode(
					'magic_coupon_html_message',
					function () {
						return '';
					}
				);
			}
		} else {
			add_shortcode(
				'magic_coupon_html_message',
				function () {
					return '';
				}
			);
		}
	}

	/**
	 * Generates and outputs an HTML message with information about a coupon.
	 *
	 * This method processes the coupon's HTML message by replacing placeholder tags with actual values.
	 * It handles time formatting (hours/minutes until expiration) and supports various dynamic values
	 * such as product ID, coupon code, and expiration information.
	 *
	 * The method performs the following operations:
	 * 1. Retrieves the current product if on a product page
	 * 2. Formats the remaining validity time into hours or minutes
	 * 3. Prepares replacement tags with dynamic values
	 * 4. Replaces all placeholder tags in the message template
	 * 5. Processes the message through 'the_content' filter and outputs it
	 *
	 * @uses apply_filters() Filters 'magic_coupon_html_message_replace_tags' to modify replacement tags
	 * @uses apply_filters() Filters 'the_content' to process the final message
	 * @uses is_product() Checks if current page is a product page
	 */
	public function html_message() {
		if ( is_product() ) {
			global $product;
		}
		// Hours or minutes
		$hours_minutes = '';
		$hours         = $this->validity > 0 ? ( $this->validity - time() ) / 60 / 60 : '';
		if ( ! empty( $hours ) ) {
			if ( $hours > 1 ) {
				// hours
				$hours_minutes = sprintf(
					/* translators: %d is the number of hours */
					__( '%d hours', 'magic-coupon' ),
					round( $hours, 0 )
				);
			} else {
				// minutes
				$minutes       = $hours * 60;
				$hours_minutes = sprintf(
					/* translators: %d is the number of minutes */
					__( '%d minutes', 'magic-coupon' ),
					round( $minutes, 0 )
				);
			}
		}
		// Tags must use internal variables so that we can set them from both cookie and get
		$replace_tags = apply_filters(
			'magic_coupon_html_message_replace_tags',
			array(
				'product_id'                    => is_product() ? $product->get_id() : '',
				'coupon'                        => $this->coupon->get_code(),
				'cookie_expire_timestamp'       => $this->validity > 0 ? $this->validity : '',
				'cookie_validity_minutes'       => $this->validity > 0 ? round( ( $this->validity - time() ) / 60 ) : '',
				'cookie_validity_hours_minutes' => $hours_minutes,
			),
			$this->coupon,
			$this->validity
		);
		$message      = trim( $this->coupon->get_meta( 'magic_coupon_html_message' ) );
		// We'll replace two times because of tags that can be used by other external tags, like shortcodes
		for ( $i = 1; $i <= 2; $i++ ) {
			foreach ( $replace_tags as $tag => $value ) {
				$message = str_replace( '{' . $tag . '}', $value, $message );
			}
		}
		echo wp_kses_post( apply_filters( 'the_content', $message ) );
	}

	/**
	 * Shortcode function for displaying HTML messages.
	 *
	 * This method acts as a wrapper for the html_message() function to make it usable as a shortcode.
	 * It uses output buffering to capture the HTML output and return it as a string.
	 *
	 * @since 1.0.0
	 * @return string The HTML message content.
	 */
	public function html_message_shortcode() {
		ob_start();
		$this->html_message();
		return ob_get_clean();
	}

	/**
	 * Adds a custom tab to the WooCommerce coupon data interface.
	 *
	 * This method hooks into the WooCommerce coupon admin interface to add a new "Magic coupon" tab.
	 * The tab allows for configuration of Magic Coupon specific settings when editing or creating coupons.
	 *
	 * @param array $coupon_data_tabs The existing coupon data tabs array.
	 * @return array Modified array of coupon data tabs including the Magic Coupon tab.
	 */
	public function woocommerce_coupon_data_tabs( $coupon_data_tabs ) {
		$coupon_data_tabs['magic_coupon'] = array(
			'label'  => __( 'Magic coupon', 'magic-coupon' ),
			'target' => 'magic_coupon_coupon_data',
			'class'  => 'magic_coupon_coupon_data',
		);
		return $coupon_data_tabs;
	}

	/**
	 * Adds Magic Coupon data panel to the WooCommerce coupon admin interface.
	 *
	 * This method creates a custom panel in the coupon edit screen with the following features:
	 * - Enable/disable option for adding coupon via URL parameter
	 * - Cookie duration field to control how long the coupon remains active
	 * - Custom HTML message field for product page display
	 * - Copy to clipboard functionality for coupon URL
	 *
	 * The panel includes JavaScript functionality to:
	 * - Toggle visibility of related fields based on the "Enable" checkbox
	 * - Generate and update the coupon URL dynamically
	 * - Copy the generated URL to clipboard
	 *
	 * @param int       $coupon_id The ID of the coupon being edited.
	 * @param WC_Coupon $coupon    The coupon object being edited.
	 */
	public function woocommerce_coupon_data_panels( $coupon_id, $coupon ) {
		?>
		<div id="magic_coupon_coupon_data" class="panel woocommerce_options_panel">
			<?php
				// Enable?
				$copy_button = '
				<br/>
				<button type="button" class="button button-small magic_coupon_show_hide" id="magic_coupon_copy">' . __( 'Copy shop URL with coupon', 'magic-coupon' ) . '</button>
				<span id="magic_coupon_copy_text"></span>
				<span id="magic_coupon_copy_success">' . __( 'URL copied to clipboard', 'magic-coupon' ) . '</span>';
				woocommerce_wp_checkbox(
					array(
						'id'          => 'magic_coupon_enable',
						'label'       => __( 'Enable', 'magic-coupon' ),
						'description' => sprintf(
							/* translators: %s is the URL parameter name */
							__( 'Check this box to be able to add this coupon via URL (with parameter %s=couponcode) and show product prices reflecting this coupon discounts', 'magic-coupon' ),
							$this->url_parameter
						) . $copy_button,
						'value'       => wc_bool_to_string( $coupon->get_meta( 'magic_coupon_enable', true, 'edit' ) ),
					)
				);
				// Minutes in cookie
				$magic_coupon_cookie_minutes = intval( $coupon->get_meta( 'magic_coupon_cookie_minutes', true, 'edit' ) );
				woocommerce_wp_text_input(
					array(
						'id'                => 'magic_coupon_cookie_minutes',
						'label'             => __( 'Cookie minutes', 'magic-coupon' ),
						'placeholder'       => $this->default_cookie_duration,
						'description'       => sprintf(
							/* translators: %d is the default cookie duration in minutes */
							__( 'How many minutes to keep the coupon in a cookie to show product prices reflecting this coupon discounts and automatically apply it to the cart (default: %d minutes)', 'magic-coupon' ),
							$this->default_cookie_duration
						),
						'type'              => 'number',
						'desc_tip'          => true,
						'class'             => 'short',
						'wrapper_class'     => 'magic_coupon_show_hide',
						'custom_attributes' => array(
							'step' => 1,
							'min'  => 1,
						),
						'value'             => $magic_coupon_cookie_minutes > 0 ? $magic_coupon_cookie_minutes : '',
					)
				);
				// HTML message on the product page
				woocommerce_wp_textarea_input(
					array(
						'id'            => 'magic_coupon_html_message',
						'label'         => __( 'HTML message on the product page', 'magic-coupon' ),
						'description'   => __( 'Optional HTML message to show on the product page, below the product price (the action hook and priority can be overridden with filters, check the FAQs)', 'magic-coupon' ),
						'value'         => $coupon->get_meta( 'magic_coupon_html_message', true, 'edit' ),
						'desc_tip'      => true,
						'wrapper_class' => 'magic_coupon_show_hide',
					)
				)
			?>
			<script type="text/javascript">
			jQuery( function( $ ) {
				$( document ).ready(function() {
					//Hide copy URL spans
					$( '#magic_coupon_copy_text' ).hide();
					$( '#magic_coupon_copy_success' ).hide();
					//Show / hide
					function magic_coupon_toggle_fields() {
						if ( $( '#magic_coupon_enable' ).is( ':checked' ) ) {
							$( '.magic_coupon_show_hide' ).show();
						} else {
							$( '.magic_coupon_show_hide' ).hide();
						}
					}
					magic_coupon_toggle_fields();
					$( '#magic_coupon_enable' ).change( function() {
						magic_coupon_toggle_fields();
					} );
					//Update coupon URL
					function magic_coupon_update_url() {
						<?php
						$url = add_query_arg(
							array( $this->url_parameter => '%coupon%' ),
							get_permalink( wc_get_page_id( 'shop' ) )
						);
						?>
						var url = '<?php echo esc_attr( $url ); ?>';
						url = url.replace( '%coupon%', $.trim( $( 'input[name=post_title]#title' ).val() ) );
						$( '#magic_coupon_copy_text' ).html( url );
					}
					magic_coupon_update_url();
					//Copy
					$( '#magic_coupon_copy' ).click( function( ev ) {
						ev.preventDefault();
						magic_coupon_update_url();
						if ( magic_coupon_copyToClipboard( document.getElementById( 'magic_coupon_copy_text' ) ) ) {
							$( '#magic_coupon_copy_success' ).fadeIn();
							setTimeout( function() {
								$( '#magic_coupon_copy_success' ).fadeOut();
							}, 3000 );
						} else {
							
						}
					} );
					function magic_coupon_copyToClipboard( elem ) {
						// create hidden text element, if it doesn't already exist
						var targetId = "_hiddenCopyText_";
						var isInput = elem.tagName === "INPUT" || elem.tagName === "TEXTAREA";
						var origSelectionStart, origSelectionEnd;
						if (isInput) {
							// can just use the original source element for the selection and copy
							target = elem;
							origSelectionStart = elem.selectionStart;
							origSelectionEnd = elem.selectionEnd;
						} else {
							// must use a temporary form element for the selection and copy
							target = document.getElementById(targetId);
							if (!target) {
								var target = document.createElement("textarea");
								target.style.position = "absolute";
								target.style.left = "-9999px";
								target.style.top = "0";
								target.id = targetId;
								document.body.appendChild(target);
							}
							target.textContent = elem.textContent;
						}
						// select the content
						var currentFocus = document.activeElement;
						target.focus();
						target.setSelectionRange(0, target.value.length);
						
						// copy the selection
						var succeed;
						try {
								succeed = document.execCommand("copy");
						} catch(e) {
							succeed = false;
						}
						// restore original focus
						if (currentFocus && typeof currentFocus.focus === "function") {
							currentFocus.focus();
						}
						
						if (isInput) {
							// restore prior selection
							elem.setSelectionRange(origSelectionStart, origSelectionEnd);
						} else {
							// clear temporary content
							target.textContent = "";
						}
						console.log( succeed );
						return succeed;
					}
				} );
			} );
			</script>
		</div>
		<?php
	}

	/**
	 * Save Magic Coupon specific options when a WooCommerce coupon is saved.
	 *
	 * This method is hooked into WooCommerce's coupon saving process and handles
	 * the storage of custom Magic Coupon settings in the coupon's meta data:
	 * - Whether the Magic Coupon functionality is enabled
	 * - The expiration time for the cookie in minutes
	 * - The HTML message to display with the coupon
	 *
	 * @param int     $post_id The ID of the coupon being saved.
	 * @param WP_Post $post    The post object representing the coupon.
	 */
	public function woocommerce_coupon_options_save( $post_id, $post ) {
		// WooCommerce is taking care of the nonce check, so we don't need to do it here
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$coupon = new WC_Coupon( $post_id );
		$coupon->update_meta_data( 'magic_coupon_enable', isset( $_POST['magic_coupon_enable'] ) ? 'yes' : 'no' );
		$coupon->update_meta_data( 'magic_coupon_cookie_minutes', isset( $_POST['magic_coupon_cookie_minutes'] ) ? intval( $_POST['magic_coupon_cookie_minutes'] ) : 0 );
		$coupon->update_meta_data( 'magic_coupon_html_message', isset( $_POST['magic_coupon_html_message'] ) ? trim( stripslashes_deep( sanitize_text_field( wp_unslash( $_POST['magic_coupon_html_message'] ) ) ) ) : '' );
		$coupon->save();
		// phpcs:enable
	}
}

/* If you’re reading this you must know what you’re doing ;-) Greetings from sunny Portugal! */