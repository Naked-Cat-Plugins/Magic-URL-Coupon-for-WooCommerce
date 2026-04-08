=== Magic URL Coupon for WooCommerce ===
Contributors: nakedcatplugins, webdados
Tags: woocommerce, coupons, promotions, marketing
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 2.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Share WooCommerce discount links: pass a coupon code via URL, display sale prices on product pages, and auto-apply the coupon at checkout.

== Description ==

Want to share a WooCommerce discount link that automatically applies a coupon? Magic URL Coupon lets you embed any coupon code directly in a URL — perfect for email campaigns, affiliate links, and social media promotions.

When a customer visits your store via the magic link, the coupon code is stored in a cookie for a configurable amount of time. No manual code entry needed.

Pass the coupon via the `mcoupon` URL parameter (customizable via the `magic_coupon_url_parameter` filter). While the cookie is active:

* Discounted prices shown upfront — eligible product pages display the post-coupon price automatically, so customers see exactly what they’ll pay before adding to cart;
* Custom promotional message — show a personalized HTML message under the product price, anywhere on the page via hooks, or inline via the `[magic_coupon_html_message]` shortcode;
* Coupon auto-applied at checkout — when the customer adds a product to the cart, the coupon is applied automatically, no code entry required;
* The coupon remains freely usable: customers can still enter it manually at any time, and this plugin will not restrict its regular usage.

Variable product support is experimental and may be moved to a premium add-on in the future.

= Other (premium) plugins =

Already know our other WooCommerce (premium) plugins?

* [Shop as Client for WooCommerce](https://nakedcatplugins.com/product/shop-as-client-for-woocommerce-pro-add-on/) - Quickly create orders on behalf of your customers
* [Simple Checkout Fields Manager for WooCommerce](https://nakedcatplugins.com/product/simple-custom-fields-for-woocommerce-blocks-checkout/) - Add custom fields and manage (remove, make required or optional) core fields on the new WooCommerce Block-based Checkout
* [Advanced Coupon Restrictions for WooCommerce](https://nakedcatplugins.com/product/advanced-coupon-restrictions-for-woocommerce/) - Create coupons for any Product Taxonomy, User details, and Order destination.
* [Simple WooCommerce Order Approval](https://nakedcatplugins.com/product/simple-woocommerce-order-approval/) - The hassle-free solution for WooCommerce order approval before payment
* [Taxonomy/Term and Role based Discounts for WooCommerce](https://nakedcatplugins.com/product/taxonomy-term-and-role-based-discounts-for-woocommerce-pro-add-on/) - Easily create bulk discount rules for products based on any taxonomy terms (built-in or custom)
* [DPD / SEUR / Geopost Pickup and Lockers network for WooCommerce](https://nakedcatplugins.com/product/dpd-seur-geopost-pickup-and-lockers-network-for-woocommerce/) - Deliver your WooCommerce orders on the DPD and SEUR Pickup network of Parcelshops and Lockers in 21 European countries

== Installation ==

* Use the included automatic install feature on your WordPress admin panel and search for “Magic URL Coupon WooCommerce”;
* On each coupon you want to activate this plugin features, you must go to the “Magic coupon” tab and check “Enable”;

== Frequently Asked Questions ==

= Can I change the URL parameter name from `mcoupon` to something else? =

Yes. Use the `magic_coupon_url_parameter` filter.

= Can I show the HTML message somewhere else on the product page? =

Sure you can. Use the `magic_coupon_html_message_action_hook` filter to change the hook and the `magic_coupon_html_message_action_priority` filter to change the priority.

If you don’t know what we’re talking about, you should probably stop now and [hire us](https://www.webdados.pt/contactos/) to do it for you :-)

You can also show it directly on the product description by using the `[magic_coupon_html_message]` shortcode.

= Is it possible to add dynamic information to the HTML message? =

You bet! Use one of the following placeholders on your message:

* `{product_id}` will be replaced by the Product ID;
* `{coupon}` will be replaced by the coupon code;
* `{cookie_expire_timestamp}` will be replaced by the cookie expiration Unix timestamp;
* `{cookie_validity_minutes}` will be replaced by the cookie validity in minutes;
* `{cookie_validity_hours_minutes}` will be replaced by the cookie validity in hours or minutes (in the `x hours` or `x minutes` format), depending on the validity being more or less than one hour;

You can also add you own placeholders with the `magic_coupon_html_message_replace_tags` filter. See an example [here](https://gist.github.com/webdados/c6094429e1e53306d767ee0b7255f4ea).

And because you can also use shortcodes on the HTML message field, you can feed the product id, coupon, the cookie expire timestamp or validity, or any other variable you set via your own placeholders as a shortcode argument, you can do whatever you want with your custom message. [Go crazy](https://gist.github.com/webdados/57682c9f7e4dad416f1ab0ec4b7476d5), or [hire us](https://www.webdados.pt/contactos/) to develop a custom solution for you.

= Can this plugin have issues with caching plugins? =

Yes. The page output has to be changed to set the discounted product prices based on the user cookie. That’s incompatible with a server-side caching system.

We set the following constants to prevent caching by some plugins, on the moment the customer reaches the page with a coupon code:

* `DONOTCACHEPAGE`
* `DONOTCACHEOBJECT`
* `DONOTCACHEDB`

This will ensure the page with the discounted price is not cached on that moment (and no other users will see a version of the page with the discount), but will not disable the cache that might already exist.

Anyway, we’ve experimented a bit with the [WP-Optimize](https://wordpress.org/plugins/wp-optimize/) “Cookies which, if present, will prevent caching” setting, by entering the name of our cookie (`mcoupon` by default), and we had good results. Regular users see the cached page and the ones with the coupon set on the cookie see non-cached pages.

= Is this plugin compatible with the new WooCommerce High-Performance Order Storage? =

Yes.

= Is this plugin compatible with the new WooCommerce block-based Cart and Checkout? =

Yes.

= I need help, can I get technical support? =

This is a free plugin. It’s our way of giving back to the wonderful WordPress community.

There’s a support tab on the top of this page, where you can ask the community for help. We’ll try to keep an eye on the forums but we cannot promise to answer support tickets.

If you reach us by email or any other direct contact method, we’ll assume you need premium, paid-for support.

= Where do I report security vulnerabilities found in this plugin? =  
 
You can report any security bugs found in the source code of this plugin through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/magic-coupon). The Patchstack team will assist you with verification, CVE assignment and take care of notifying the developers of this plugin.

== Screenshots ==

1. Magic coupon settings

== Changelog ==

= 2.2 - 2026-04-08 =
* [NEW] Free plugin ownership transferred from [Marco Almeida | Webdados](https://profiles.wordpress.org/webdados/) to [Naked Cat Plugins](https://profiles.wordpress.org/nakedcatplugins/) on WordPress.org - No worries, we’re the same people :-)
* [DEV] Remove `load_plugin_textdomain` call as it’s no longer needed
* [DEV] Move development to GitHub
* [DEV] Tested up to WordPress 7.0-RC2-62197 and WooCommerce 10.7.0-beta.1

= 2.1 - 2025-06-14 =
* [NEW] We are now called Naked Cat Plugins 😻
* [FIX] Copy coupon URL was submitting the coupon form on the backend
* [DEV] Apply WordPress Coding Standards
* [DEV] Requires WordPress 5.8, WooCommerce 7.1 and PHP 7.2
* [DEV] Tested up to WordPress 6.9-alpha-60301 and WooCommerce 9.9.3

= 2.0 - 2023-12-13 =
* Beta compatibility with Subscription Products (not variable) and the "Recurring Product Discount" and "Recurring Product % Discount" coupon types
* Declare WooCommerce block-based Cart and Checkout compatibility
* Requires WordPress 5.4
* Tested up to WordPress 6.5-alpha-57159 and WooCommerce 8.4.0

= 1.9 - 2023-07-07 =
* Requires WooCommerce 5.0
* Tested up to WordPress 6.3-beta3-56143 and WooCommerce 7.9.0-rc.3

= 1.8 - 2022-06-29 =
* New brand: PT Woo Plugins 🥳
* Requires WordPress 5.0, WooCommerce 3.0 and PHP 7.0
* Tested up to WordPress 6.1-alpha-53556 and WooCommerce 6.7.0-beta.2

= 1.7 =
* Compatibility with “Percentage Coupon per Product for WooCommerce” 0.6 (experimental, sponsored by [https://masterswiss.com/](https://masterswiss.com/))
* Tested up to WordPress 5.7-beta2-50285 and WooCommerce 5.0

= 1.6 =
* New filter on the "is on sale" own function for better [Percentage Coupon per Product for WooCommerce](https://wordpress.org/plugins/product-percentage-coupon-woo/) integration (sponsored by: [Master Swiss](https://masterswiss.com/))
* Tested up to WordPress 5.6-alpha-48937 and WooCommerce 4.5.1

= 1.5 =
* Compatibility with our upcoming plugin “Percentage Coupon per Product for WooCommerce” plugin
* Tested up to WordPress 5.5-RC1-48708 and WooCommerce 4.4.0-rc.1

= 1.4 =
* Show discount prices on [WooCommerce Tiered Price Table](https://wordpress.org/plugins/tier-pricing-table/) (sponsored by: [Master Swiss](https://masterswiss.com/))

= 1.3.1 =
* Bugfix on categories inclusion/exclusion for variable products
* Fix PHP notice when product has no price

= 1.3 =
* Experimental support for variable products (may be moved to a premium add-on in the future)
* Check if coupon needs to be applied when cart quantities are updated (thanks [UsoMascara.pt](https://usomascara.pt))
* Code refactor for flexibility

= 1.2.3.1 =
* Plugin name changed from “Magic Coupon for WooCommerce” to “Magic URL Coupon for WooCommerce”

= 1.2.3 =
* Better UX on the “Magic coupon” tab, including a button to copy the shop URL with the coupon parameter on it
* Technical support clarification

= 1.2.2 =
* Fix small bug displaying the sale price when the discount value was below 1 (thanks [ProdutosLimpeza.pt](https://produtoslimpeza.pt))

= 1.2.1 =
* Fix PHP notice when the discount is neither “Percentage discount” or “Fixed product discount” (thanks @alordiel)

= 1.2 =
* New `{cookie_validity_hours_minutes}` placeholder on the HTML message that will show the cookie validity time in hours or minutes; 
* Some fixes on the way the coupon is added to the cart to ensure it only happens after the product is already there
* Avoid duplicating the discount on the cart and checkout pages (Oops...)

= 1.1.1 =
* Small bugfix

= 1.1 =
* New `[magic_coupon_html_message]` shortcode to show the HTML message on the product description if the theme is custom and does not call the `woocommerce_single_product_summary` action
* Bugfix on the moment the coupon is checked from the cookie to avoid errors on some configurations
* Clarification of operation in conjunction with cache plugins
* Tested up to WordPress 5.5-alpha-47748 and WooCommerce 4.1.0-rc.2

= 1.0.2 =
* Tested up to WordPress 5.2.5-alpha and WooCommerce 3.8.0

= 1.0.1 =
* Small readme.txt fixes

= 1.0 =
* First released version (sponsored by: [muchogrowth.com](http://muchogrowth.com))