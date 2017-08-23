<?php
/**
 * Register the custom product type after init
 */
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}
/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    function register_gift_coupon_product_type() {
        // Extend the WC_Prduct_Simple Class for register our own product type 'gift_coupon'
        class WC_Product_Gift_Coupon extends WC_Product {
            public function __construct( $product ) {
                $this->product_type = 'gift_coupon';
                $this->supports[]   = 'ajax_add_to_cart';
                parent::__construct( $product );
            }

            public function get_type() {
                return 'gift_coupon';
            }

            /**
             * Set if should be sold individually.
             *
             * @since 3.0.0
             * @param bool
             */
            public function set_sold_individually( $sold_individually ) {
                $this->set_prop( 'sold_individually', true );
                if ( false === $sold_individually ) {
                    $this->error( 'product_gift_coupon_invalid_manage_stock', __( 'Gift card can only be sold individually', 'woocommerce' ) );
                }
            }
        }
    }
    add_action( 'plugins_loaded', 'register_gift_coupon_product_type' );

    /**
     * Add to product type drop down.
     * Hooked on product_type_selector
     */
    function add_gift_coupon_product( $types ){
        // Key should be exactly the same as in the class
        $types[ 'gift_coupon' ] = __( 'Chèque Cadeau' );
        return $types;
    }
    add_filter( 'product_type_selector', 'add_gift_coupon_product' );

    /**
     * Hide Attributes data panel.
     */
    function wcs_hide_attributes_data_panel( $tabs) {

        // Other default values for 'attribute' are; general, inventory, shipping, linked_product, variations, advanced
        $tabs['attribute']['class'][] = 'hide_if_gift_coupon';
        $tabs['shipping']['class'][] = 'hide_if_gift_coupon';
        $tabs['inventory']['class'][] = 'show_if_gift_coupon';

        return $tabs;

    }
    add_filter( 'woocommerce_product_data_tabs', 'wcs_hide_attributes_data_panel', 10,1 );

    if (! function_exists( 'woocommerce_gift_coupon_add_to_cart' ) ) {

      /**
      * Output the simple product add to cart area.
      *
      * @subpackage Product
      */

      function gift_coupon_add_to_cart() {
        wc_get_template( 'single-product/add-to-cart/simple.php' );
      }

      add_action('woocommerce_gift_coupon_add_to_cart',  'gift_coupon_add_to_cart');
    }

    /**
     * Show pricing fields for gift_coupon product.
     */
    function gift_coupon_custom_js() {
        if ( 'product' != get_post_type() ) :
            return;
        endif;
        ?><script type='text/javascript'>
            jQuery( '.options_group.pricing' ).addClass( 'show_if_gift_coupon' );
            jQuery( '.show_if_simple' ).addClass( 'show_if_gift_coupon' );
        </script><?php
    }
    add_action( 'admin_footer', 'gift_coupon_custom_js' );
}