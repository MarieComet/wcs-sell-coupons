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
        class WC_Product_Gift_Coupon extends WC_Product_Simple {
            public function __construct( $product ) {
                $this->product_type = 'gift_coupon';
                parent::__construct( $product );
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
     * Show pricing fields for gift_coupon product.
     */
    function gift_coupon_custom_js() {
        if ( 'product' != get_post_type() ) :
            return;
        endif;
        ?><script type='text/javascript'>
            jQuery( document ).ready( function() {
                jQuery( '.options_group.pricing' ).addClass( 'show_if_gift_coupon' ).show();
            });
        </script><?php
    }
    add_action( 'admin_footer', 'gift_coupon_custom_js' );
}