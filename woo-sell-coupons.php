<?php
/*
 * Plugin Name: WooCommerce Sell Coupons
 * Text Domain: wcs-sell-coupons
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 3.6.2
 * Plugin URI: https://github.com/MarieComet/wcs-sell-coupons
 * Description: This plugin create a new WooCommerce product type and add possibilty to sell Coupons as Gift Card in front-office. Please visit WooCommerce > Settings > General once activated !
 * Version: 1.0.2
 * Author: Marie Comet
 * Author URI: https://www.mariecomet.fr/
 * License: GPLv2 or later
 * @package WooCommerce Sell Coupons
 */
/**
 * Register the custom product type after init
 */
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

/**
 *
 * Check if WooCommerce is active
 *
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    // define plugin url
    define( 'WSC__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

    // require register coupon product type class
    require_once( WSC__PLUGIN_DIR . 'wcs-register-coupon.php' );
	require_once __DIR__ . '/vendor/autoload.php';

	/**
	 * Class Woo_Sell_Coupons
	 */
    class Woo_Sell_Coupons {
        private static $instance;
	    private $pluginlocale;

	    /**
	     * Register function.
	     */
        public static function register() {
            if ( null === self::$instance ) {
                self::$instance = new Woo_Sell_Coupons();
            }
        }

	    /**
	     * Load textdomain
	     */
        public static function load_textdomain() {
            load_plugin_textdomain( 'wcs-sell-coupons', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

	    /**
	     *
	     */
        public function wcs_admin_notices() {
            if ( '' !== get_option( 'wcs_gift_coupon_prefix' ) ) {
                return;
            }
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <a href="<?php echo( admin_url( 'admin.php?page=wc-settings' ) ) ?>">
                        <?php _e( 'Please visit WooCommerce > Settings > General to setup WooCommerce Sell Coupons !', 'wcs-sell-coupons' ); ?>
                    </a>
                </p>
            </div>
            <?php
        }

	    /**
	     * Woo_Sell_Coupons constructor.
	     */
        public function __construct() {
	        add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
	        add_action( 'admin_notices', array($this, 'wcs_admin_notices'));
	        add_action( 'wp_enqueue_scripts', array( $this, 'wcs_register_plugin_styles' ) );

	        // Add custom option field in woocommerce general setting
	        add_filter( 'woocommerce_general_settings', array( $this, 'wc_coupon_setting_page') );
	        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'wcs_email_friend'), 10);
	        add_action( 'woocommerce_add_to_cart_validation', array( $this, 'wcs_check_custom_fields'), 10, 5 );
	        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'wcs_add_cart_item_custom_data'), 10, 2 );
	        add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'wcs_get_cart_items_from_session'), 1, 3 );
	        add_filter( 'woocommerce_cart_item_name', array( $this, 'wcs_add_user_custom_session' ), 1, 3 );
	        add_filter( 'woocommerce_order_item_name', array( $this, 'wcs_woocommerce_order_custom_session' ), 10, 3 );
	        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'wcs_add_values_to_order_item_meta' ), 10, 4 );
	        add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'wcs_add_order_formatted_key' ), 10, 2 );
	        add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'wcs_hide_my_item_meta' ), 10, 1 );
	        add_action( 'woocommerce_before_cart_item_quantity_zero',array( $this, 'wcs_remove_user_custom_data_options_from_cart' ) ,1,1);

	        add_action( 'woocommerce_cart_calculate_fees', array( $this, 'wcs_add_surcharge' ) );

	        add_action( 'woocommerce_order_status_completed', array( $this, 'wcs_create_coupon_on_order_complete' ) );

            //locale management for translations
            add_action ( 'switch_locale', array( $this, 'wcs_switch_locale') , 10, 1 );
            add_filter( 'plugin_locale', array( $this, 'wcs_correct_locale') , 100, 2 );
        }

        /**
         * Function used to check if a product is a gift_coupon type.
         *
         * @param int  $product_id   The product ID to check
         *
         * @return boolean
         */
        function check_if_coupon_gift($product_id) {
            $terms = get_the_terms($product_id, 'product_type');
            if($terms) {
                foreach ( $terms as $term ) {
                    if($term->slug === 'gift_coupon') {
                        return true;
                    }
                }
            }
            return false;
        }

	    /**
	     * Register and enqueue style sheet.
	     */
	    public function wcs_register_plugin_styles() {

		    wp_enqueue_style( 'wcs-sell-coupons', plugin_dir_url( __FILE__) . 'woo-sell-coupons.css' );

		    if ( is_singular( 'product' ) && $this->check_if_coupon_gift( get_the_ID() ) ) {
		    	wp_enqueue_script( 'wcs-sell-coupons-js', plugin_dir_url( __FILE__) . 'js/wcs-sell-coupons.js', [ 'jquery' ] );
		    }
	    }

        /**
         *
         * @param $settings
         *
         * Add custom option field in woocommerce general setting
         *
         * Hooked on woocommerce_general_settings
         *
         * @return array
         */
        function wc_coupon_setting_page( $settings ) {
	        $settings[] = array(
		        'name' => __( 'Gift cards', 'wcs-sell-coupons' ),
		        'type' => 'title',
		        'desc' => '',
		        'id'   => 'wcs_gift_coupon',
	        );

	        $settings[] = array(
		        'title'       => __( 'Duration of gift cars', 'wcs-sell-coupons' ),
		        'desc'        => '',
		        'id'          => 'wcs_gift_coupon_duration',
		        'desc_tip'    => __( 'Validity period of gift cards (in days)', 'wcs-sell-coupons' ),
		        'type'        => 'number',
		        'default'     => '',
		        'css'         => 'min-width:300px;',
		        'placeholder' => __( '30', 'wcs-sell-coupons' ),
	        );

	        $settings[] = array(
		        'title'       => __( 'Gift cards code prefix', 'wcs-sell-coupons' ),
		        'desc'        => '',
		        'id'          => 'wcs_gift_coupon_prefix',
		        'desc_tip'    => __( 'Prefix used for coupon codes', 'wcs-sell-coupons' ),
		        'type'        => 'text',
		        'default'     => '',
		        'css'         => 'min-width:300px;',
		        'placeholder' => __( 'GF', 'wcs-sell-coupons' ),
	        );

	        $settings[] = array(
		        'title'       => __( 'Gift cards shipping price', 'wcs-sell-coupons' ),
		        'desc'        => __( 'Shipping price per gift card (product)', 'wcs-sell-coupons' ),
		        'id'          => 'wcs_gift_coupon_ship_price',
		        'desc_tip'    => '',
		        'type'        => 'number',
		        'default'     => '',
		        'css'         => 'min-width:300px;',
		        'placeholder' => __( '10', 'wcs-sell-coupons' ),
	        );

	        $settings[] = array(
	                'type' => 'sectionend',
                    'id'   => 'wcs_gift_coupon'
            );

            return $settings;
        }

	    /**
	     * Add two custom fields on the single product page before add to cart button.
	     *
	     * Hooked on woocommerce_before_add_to_cart_button
	     */
        function wcs_email_friend() {
            global $woocommerce, $post;
            if ( $this->check_if_coupon_gift( $post->ID ) ) {

            	$wcs_gift_coupon_ship_price = get_option( 'wcs_gift_coupon_ship_price' );
            	if ( !empty( $wcs_gift_coupon_ship_price ) ) {
            		$option_mail_price = sprintf(
            			'(%1$s%2$s)',
            			$wcs_gift_coupon_ship_price,
            			get_woocommerce_currency_symbol()
            		);
            	}
                ?>
                <div class="wcs-data">
                	<p>Les champs dotés d'une * sont requis.</p>
                    <p>
                    	<label for="wcs_send_method"><?php _e( 'Choose the shipping method :', 'wcs-sell-coupons' ); ?></label>
                    	<select name="wcs_send_method" id="wcs_send_method">
                    		<option value="wcs_send_method_email"><?php _e( 'Email', 'wcs-sell-coupons' ); ?></option>
                    		<option value="wcs_send_method_mail"><?php _e( 'Mail', 'wcs-sell-coupons' ); ?> <?php esc_html_e( $option_mail_price ); ?></option>
                    		<option value="wcs_send_method_pos"><?php _e( 'Restaurant withdrawal', 'wcs-sell-coupons' ); ?></option>
                    	</select>
                	</p>
                    <p class="wcs-email-friend">
                    	<label for="wcs_email_friend"><?php _e( 'Recipient e-mail address :', 'wcs-sell-coupons' ); ?>
	                        <abbr class="required" title="<?php _e( 'required', 'wcs-sell-coupons' ); ?>">*
	                        </abbr>                 	
	                        <br>
                	        <input type="email" id="wcs_email_friend" name="wcs_email_friend" placeholder="email@mail.com" required/>
                	    </label>
                	</p>
                    <p class="wcs-address-friend">
                    	<label for="wcs_address_friend"><?php _e( 'Recipient address :', 'wcs-sell-coupons' ); ?>
	                        <abbr class="required" title="<?php _e( 'required', 'wcs-sell-coupons' ); ?>">*
	                        </abbr>                 	
	                        <br>
                	        <input type="text" id="wcs_address_friend" name="wcs_address_friend" placeholder="<?php _e( 'Number, Street, City, Zipcode', 'wcs-sell-coupons' ); ?>"/>
                	    </label>
                	</p>
                	<p>
	                    <label for="wcs_name_friend"><?php _e( 'Name of the recipient', 'wcs-sell-coupons' ) ?>:
	                        <abbr class="required" title="<?php _e( 'required', 'wcs-sell-coupons' ); ?>">*</abbr>
	                    </label>
	                	<br>
	                    <input type="text" id="wcs_name_friend" name="wcs_name_friend"
	                           placeholder="<?php _e( 'John Doe', 'wcs-sell-coupons' ) ?>" required/>
	                </p>
	                <p>
	                    <label for="wcs_gift_message"> <?php _e( 'Gift message', 'wcs-sell-coupons' ) ?>: </label>
	                	<br>
	                
			            <?php
			            $gift_message = __( 'Sending you this gift coupon with best wishes', 'wcs-sell-coupons' );
			            $thumbnail    = wp_get_attachment_image( get_post_thumbnail_id(), 'thumbnail' );
			            if ( $thumbnail ) {
				            $gift_message .= '<br />' . $thumbnail;
			            }
			            $gift_message = apply_filters( 'wcs_gift_message', $gift_message );
			            $gift_message_input = '<textarea id="wcs_gift_message" name="wcs_gift_message" placeholder="' . __( 'Add your gift message here.', 'wcs-sell-coupons' ) . '"></textarea>';
			            // use add_filter('wcs_gift_message_input', 'custom_input', 10, 2); to override input type
			            $gift_message_input = apply_filters( 'wcs_gift_message_input', $gift_message_input, $gift_message );
			            echo $gift_message_input;
			            ?>
		          	</p>
                </div>
                <?php
            }
        }

	    /**
         * Add a validation when a gift_coupon is added to cart
	     * Check if our two custom fields are not empty, if there are display a WC notice error
	     *
	     * Hooked on woocommerce_add_to_cart_validation
	     *
	     * @param $passed
	     * @param $product_id
	     * @param $quantity
	     *
	     * @return bool
	     */
	    function wcs_check_custom_fields( $passed, $product_id, $quantity ) {
		    if ( $this->check_if_coupon_gift( $product_id ) ) {

		    	$wcs_send_method = $_POST['wcs_send_method'];
		    	$wcs_email_friend = $_POST['wcs_email_friend'];
		    	$wcs_name_friend = $_POST['wcs_name_friend'];
		    	$wcs_address_friend = $_POST['wcs_address_friend'];

		    	if ( 'wcs_send_method_mail' === $wcs_send_method && ! empty( $wcs_address_friend ) && ! empty( $wcs_name_friend ) ) {
		    		$passed = true;
		    	} elseif ( 'wcs_send_method_pos' === $wcs_send_method && ! empty( $wcs_name_friend ) ) {
		    		$passed = true;
		    	} elseif ( 'wcs_send_method_email' === $wcs_send_method && ! empty( $wcs_email_friend ) && ! empty( $wcs_name_friend ) ) {
		    		$passed = true;
		    	} else {
		    		wc_add_notice( __( 'Please fill in the required fields', 'wcs-sell-coupons' ), 'error' );
		    		$passed = false;
		    	}
		    	
		    } else {
			    $passed = true;
		    }

		    return $passed;

	    }

	    /**
	     * Add our two custom field values storent in SESSION to the cart item object
	     *
	     * Hooked on woocommerce_add_cart_item_data
	     *
	     * @param $cart_item_meta
	     * @param $product_id
	     *
	     * @return mixed
	     */
	    function wcs_add_cart_item_custom_data( $cart_item_meta, $product_id ) {
		    global $woocommerce;

		    if ( $this->check_if_coupon_gift( $product_id ) ) {

		    	$wcs_send_method = $_POST['wcs_send_method'];
		    	$wcs_email_friend = $_POST['wcs_email_friend'];
		    	$wcs_name_friend = $_POST['wcs_name_friend'];
		    	$wcs_address_friend = $_POST['wcs_address_friend'];
		    	$wcs_gift_message = $_POST['wcs_gift_message'];

		    	if ( ! empty( $wcs_name_friend ) ) {
		    		$cart_item_meta['wcs_name_friend']  = sanitize_text_field( $wcs_name_friend );
		    	}
		    	if ( ! empty( $wcs_gift_message ) ) {
		    		$cart_item_meta['wcs_gift_message']  = wp_kses_post( $wcs_gift_message );
		    	}
		    	if ( ! empty( $wcs_send_method ) ) {
		    		$cart_item_meta['wcs_send_method']  = sanitize_text_field( $wcs_send_method );
		    		// set human readable shipping method
		    		switch ( $wcs_send_method ) {
		    			case 'wcs_send_method_email' :
		    				$wcs_send_method_label = __( 'Email', 'wcs-sell-coupons' );
		    				break;

		    			case 'wcs_send_method_mail' :
		    				$wcs_send_method_label = __( 'Mail', 'wcs-sell-coupons' );
		    				break;

		    			case 'wcs_send_method_pos' :
		    				$wcs_send_method_label = __( 'Restaurant withdrawal', 'wcs-sell-coupons' );
		    				break;
		    			
		    			default:
		    				$wcs_send_method_label = __( 'None', 'wcs-sell-coupons' );
		    				break;
		    		}
		    		$cart_item_meta['wcs_send_method_label']  = $wcs_send_method_label;
		    	}
		    	if ( ! empty( $wcs_email_friend ) ) {
		    		$cart_item_meta['wcs_email_friend']  = sanitize_email( $wcs_email_friend );
		    	}
		    	if ( ! empty( $wcs_address_friend ) ) {
		    		$cart_item_meta['wcs_address_friend']  = sanitize_text_field( $wcs_address_friend );
		    	}
		    }

		    return $cart_item_meta;
	    }

	    /**
         * Get our custom field values in cart from session
         *
	     * Hooked on woocommerce_get_cart_item_from_session
         *
	     * @param $item
	     * @param $values
	     * @param $key
	     *
	     * @return mixed
	     */
	    function wcs_get_cart_items_from_session( $item, $values, $key ) {

		    if ( array_key_exists( 'wcs_email_friend', $values ) ) {
			    $item['wcs_email_friend'] = $values['wcs_email_friend'];
		    }
		    if ( array_key_exists( 'wcs_name_friend', $values ) ) {
			    $item['wcs_name_friend'] = $values['wcs_name_friend'];
		    }
		    if ( array_key_exists( 'wcs_gift_message', $values ) ) {
			    $item['wcs_gift_message'] = $values['wcs_gift_message'];
		    }
	        if ( array_key_exists( 'wcs_address_friend', $values ) ) {
	    	    $item['wcs_address_friend'] = $values['wcs_address_friend'];
	        }
            if ( array_key_exists( 'wcs_send_method', $values ) ) {
        	    $item['wcs_send_method'] = $values['wcs_send_method'];
            }
            if ( array_key_exists( 'wcs_send_method_label', $values ) ) {
        	    $item['wcs_send_method_label'] = $values['wcs_send_method_label'];
            }

		    return $item;
	    }

	    /**
         * Display the custom field values next to the Product name in the cart
	     *
	     * Hooked on woocommerce_cart_item_name
         *
	     * @param $product_name
	     * @param $values
	     * @param $cart_item_key
	     *
	     * @return string
	     */
        function wcs_add_user_custom_session( $product_name, $values, $cart_item_key ) {

        	if ( $this->check_if_coupon_gift($values['product_id'] ) && isset( $values['wcs_name_friend'] ) && ! empty( $values['wcs_name_friend'] ) ) {
        		$return_string = '<b>' . $product_name . '</b><br/><span>' . __( 'To :', 'wcs-sell-coupons' ) . ' ' . $values['wcs_name_friend'] . '</span>';
        		if ( isset( $values['wcs_email_friend'] ) && ! empty( $values['wcs_email_friend'] ) ) {
        			$return_string .= '<br/><span>' . __( 'Email :', 'wcs-sell-coupons' ) . ' ' . $values['wcs_email_friend'] . '</span>';
        		}
        		if ( isset( $values['wcs_address_friend'] ) && ! empty( $values['wcs_address_friend'] ) ) {
        			$return_string .= '<br/><span>' . __( 'Address :', 'wcs-sell-coupons' ) . ' ' . $values['wcs_address_friend'] . '</span>';
        		}
        		if ( isset( $values['wcs_gift_message'] ) && $values['wcs_gift_message'] && !empty( $values['wcs_gift_message'] ) ) {
        		    $return_string .= '<br/><span>' . stripslashes($values['wcs_gift_message']) . '</span>';
        		}
        		if ( isset( $values['wcs_send_method_label'] ) && $values['wcs_send_method_label'] && !empty( $values['wcs_send_method_label'] ) ) {
        		    $return_string .= '<br/><span>' . __( 'Shipping method :', 'wcs-sell-coupons' ) . ' '  . $values['wcs_send_method_label'] . '</span><br/>';
        		}
        		return $return_string;
        	} else {
                return $product_name;
            }

        }

	    /**
	     * Display custom field values in the order confirmation page
	     *
	     * Hooked on woocommerce_order_item_name
	     *
	     * @param $name
	     * @param $item
	     *
	     * @return string
	     */
        function wcs_woocommerce_order_custom_session( $name, $item ) {

        	if ( $this->check_if_coupon_gift( $item['product_id'] ) && isset( $item['name_to'] ) && ! empty( $item['name_to'] ) ) {
        		$return_string = '<b>' . $name . '</b><br/><span>' . __( 'To :', 'wcs-sell-coupons' ) . ' ' . $item['name_to'] . '</span>';
        		if ( isset( $item['mail_to'] ) && ! empty( $item['mail_to'] ) ) {
        			$return_string .= '<br/><span>' . __( 'Email :', 'wcs-sell-coupons' ) . ' ' . $item['mail_to'] . '</span>';
        		}
        		if ( isset( $item['address_to'] ) && ! empty( $item['address_to'] ) ) {
        			$return_string .= '<br/><span>' . __( 'Address :', 'wcs-sell-coupons' ) . ' ' . $item['address_to'] . '</span>';
        		}
        		if ( isset( $item['gift_message'] ) && $item['gift_message'] && !empty( $item['gift_message'] ) ) {
        		    $return_string .= '<br/><span>' . __( 'Message :', 'wcs-sell-coupons' ) . ' ' . stripslashes($item['gift_message']) . '</span>';
        		}
        		if ( isset( $item['ship_method_label'] ) && $item['ship_method_label'] && !empty( $item['ship_method_label'] ) ) {
        		    $return_string .= '<br/><span>' . __( 'Shipping method :', 'wcs-sell-coupons' ) . ' '  . $item['ship_method_label'] . '</span><br/>';
        		}
        		return $return_string;
        	} else {
                return $name;
            }
        }

	    /**
         * Add our custom fields values as order item values, it can be seen in the order admin page and we can get it later
	     *
	     * Hooked on woocommerce_add_order_item_meta
	     *
	     * @param $item
	     * @param $cart_item_key
	     * @param $values
	     * @param $order
	     *
	     */
        function wcs_add_values_to_order_item_meta( $item, $cart_item_key, $values, $order ) {

            if ( $this->check_if_coupon_gift( $values['product_id'] ) && isset( $values['wcs_name_friend'] ) && !empty( $values['wcs_name_friend'] ) ) {
                // lets add the meta data to the order
                $item->update_meta_data( '_name_to', $values['wcs_name_friend'] );

                if ( isset( $values['wcs_email_friend'] ) && !empty( $values['wcs_email_friend'] ) ) {
                	$item->update_meta_data( '_mail_to', $values['wcs_email_friend'] );
                }
                if ( isset( $values['wcs_gift_message'] ) && !empty( $values['wcs_gift_message'] ) ) {
                	$item->update_meta_data( '_gift_message', $values['wcs_gift_message'] );
                }
                if ( isset( $values['wcs_address_friend'] ) && !empty( $values['wcs_address_friend'] ) ) {
                	$item->update_meta_data( '_address_to', $values['wcs_address_friend'] );
                }
                if ( isset( $values['wcs_send_method_label'] ) && !empty( $values['wcs_send_method_label'] ) ) {
                	$item->update_meta_data( '_ship_method_label', $values['wcs_send_method_label'] );
                }
                if ( isset( $values['wcs_send_method'] ) && !empty( $values['wcs_send_method'] ) ) {
                	$item->update_meta_data( '_ship_method', $values['wcs_send_method'] );
                }
                
            }
        }

	    /**
         * Add $display_key for display it in admin
	     *
	     * Hooked on woocommerce_order_item_display_meta_key
	     *
	     * @param $display_key
	     * @param $meta
	     *
         * @return string
	     */
	    function wcs_add_order_formatted_key( $display_key, $meta ) {
		    if ( $meta->key === '_name_to' ) {
			    $display_key = __( 'Recipient', 'wcs-sell-coupons' );
		    }
		    if ( $meta->key === '_mail_to' ) {
			    $display_key = __( 'E-mail', 'wcs-sell-coupons' );
		    }
	        if ( $meta->key === '_address_to' ) {
	    	    $display_key = __( 'Address', 'wcs-sell-coupons' );
	        }
            if ( $meta->key === '_ship_method_label' ) {
        	    $display_key = __( 'Shipping method', 'wcs-sell-coupons' );
            }
		    if ( $meta->key === '_gift_message' ) {
			    $display_key = __( 'Message', 'wcs-sell-coupons' );
		    }
		    if ( $meta->key === '_gift_code' ) {
			    $display_key = __( 'Coupon', 'wcs-sell-coupons' );
		    }

		    return $display_key;
	    }

	    /**
	     * Hide custom item meta from order view
	     */
	    function wcs_hide_my_item_meta( $hidden_meta ) {

	    	// hide the _ship_method meta
	    	$hidden_meta[] = '_ship_method';
	    	  
	    	return $hidden_meta;
	    }

	    /**
         * Remove the cart content custom values when a product is deleted
	     *
	     * Hooked on woocommerce_before_cart_item_quantity_zero
	     *
	     * @param $cart_item_key
        */
        function wcs_remove_user_custom_data_options_from_cart( $cart_item_key ) {
            global $woocommerce;
            // Get cart
            $cart = $woocommerce->cart->get_cart();
            // For each item in cart, if item is upsell of deleted product, delete it
            foreach( $cart as $key => $values ) {
            if ( $values['name_to'] === $cart_item_key ||  $values['mail_to'] === $cart_item_key || $values['gift_message'] == $cart_item_key || $values['address_to'] == $cart_item_key || $values['ship_method'] == $cart_item_key )
                unset( $woocommerce->cart->cart_contents[ $key ] );
            }
        }

        /*
         * Add cart fee is there is gift card product sent by mail
         * Doc : https://docs.woocommerce.com/document/add-a-surcharge-to-cart-and-checkout-uses-fees-api/#section-2
         */
        function wcs_add_surcharge( $cart ) {
        	global $woocommerce; 

        	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) 
        	return;
        	
        	$wcs_gift_coupon_ship_price = get_option( 'wcs_gift_coupon_ship_price' );

        	if ( !empty( $wcs_gift_coupon_ship_price ) ) {
        		$fee_count = 0;

        		// Loop through cart items
    		    foreach ( $cart->get_cart() as $item ) {
    		    	// if product has mail send method, add fee count
    		    	if ( isset( $item[ 'wcs_send_method' ] ) && 'wcs_send_method_mail' === $item[ 'wcs_send_method' ] ) {
    		    		$fee_count++;
    		    	}
    		    }
    		    // if there is fee count, add fees for each product
    		    if ( $fee_count > 0 ) {
    		    	$fee = $fee_count * $wcs_gift_coupon_ship_price;
    		    	$woocommerce->cart->add_fee( __( 'Gift shipping fees', 'wcs-sell-coupons' ), $fee, false, '' ); 
    		    }
        	}
        }

	    /**
	     * @param $order_id
	     *
	     * @throws Exception
	     *
	     *
	     * Finally when an order is completed we create a coupon with the previously custom values
	     * This function is hooked on woocommerce_order_status_completed, when a payment is OK and NOT before.
	     * TODO: Orders currently go to Processing on payment, manual move to completion before sending coupon
	     *       is unnecessary:  for example if other goods are ordered at the same time
	     *       order will not be completed until it is confirmed they have arrived
	     */
        function wcs_create_coupon_on_order_complete( $order_id ) {
            // Instancy a new WC_Order class.
            $order = new WC_Order( $order_id );

            // Get each product in order.
            $order_items =  $order->get_items();

	        $duration_gift_coupon = get_option( 'wcs_gift_coupon_duration' );
	        $prefix_gift_coupon   = get_option( 'wcs_gift_coupon_prefix' );

            $today = time();
            if ( ! empty( $duration_gift_coupon ) ) {
                $date_expire = strtotime( "+".$duration_gift_coupon." days", $today );
            } else { // default 30 days
                $date_expire = strtotime( "+30 days", $today );
            }

            $expiry_date = wc_format_datetime( $date_expire );

            foreach( $order_items as $order_product_detail => $values ) {
                // check if the product is a gift_coupon !
                if ( $this->check_if_coupon_gift( $values['product_id'] ) )  {

                    // Get the customer order values
	                $client_first_name = get_post_meta( $order_id, '_billing_first_name', true );
	                $client_last_name  = get_post_meta( $order_id, '_billing_last_name', true );

                    // Create a nice name...
                    $client_name = $client_first_name .' '. $client_last_name;

                    // Get the product price (gift amount)
	                $product       = wc_get_product( $values['product_id'] );
	                $product_price = $product->get_regular_price( 'edit' );
                    // Amount coupon code
                    $amount = $product_price; // Amount

                    $send_email = false;
                    $friend_email = '';
                    // Get the custom order values : friend and email
                    if ( isset( $values['item_meta']['_mail_to'] ) && !empty( $values['item_meta']['_mail_to'] ) ) { 
	                	$friend_email   = $values['item_meta']['_mail_to'];
	                }

	                // if gift shipping method == email
	                if ( !empty( $friend_email ) && 'wcs_send_method_email' === $values['item_meta'][ '_ship_method' ] ) {
	                	$send_email = true;
	                }

	                $friend_name    = $values['item_meta']['_name_to'];
	                $friend_message = isset( $values['item_meta']['_gift_message'] ) ? stripslashes( $values['item_meta']['_gift_message'] ) : '';

                    //if coupon already issued, get it, don't issue again by changing the status repeatedly
	                $coupon_code   = wc_get_order_item_meta( $order_product_detail, '_gift_code' );
	                $new_coupon_id = ( $coupon_code ) ? post_exists( $coupon_code ) : 0;
	                if ( ! $new_coupon_id ){
                        // Generate a random code
                        $coupon_code = strtolower( $prefix_gift_coupon.'_'.$this->wsc_random_number() );

                        // Construct our coupon post
		                $coupon = array(
			                'post_title'   => $coupon_code,
			                'post_content' => '',
			                'post_excerpt' => __( 'To:', 'wcs-sell-coupons' ) . ' ' . $friend_name . ' ' . __( '- Sent to:', 'wcs-sell-coupons' ) . ' ' . $friend_email,
			                'post_status'  => 'publish',
			                'post_author'  => 1,
			                'post_type'    => 'shop_coupon',
		                );
                        $new_coupon_id = wp_insert_post( $coupon );
                    }
                        
                    $discount_type = 'fixed_cart'; // Type: fixed_cart, percent, fixed_product, percent_product
                    /**
                     * Filters the coupon rules meta data to create.
                     *
                     * @param array $coupon_meta keyed array of coupon attributes.
                     */
                    $coupon_meta = apply_filters('wcs_gift_coupon_meta', array(
                        'discount_type' => $discount_type ,
                        'coupon_amount' => $amount ,
                        'expiry_date' => $expiry_date,
                        'date_expires' => $date_expire,
                        'individual_use' => 'yes' ,
                        'usage_limit' => '1' ,
                        'usage_limit_per_user' => '1' ,
                        'limit_usage_to_x_items' => '1',
                        //record some information to help trace this later
                        '_name_to'    => $friend_name,
                        '_mail_to'    => $friend_email,
                        '_gift_message' => $friend_message,
                        '_gift_from'  => $client_name,
                        '_gift_order'      => $order_id                            
                    ), $new_coupon_id, $coupon_code);

                    // Insert coupon meta
                    foreach ( $coupon_meta as $meta_key => $meta_value ) {
                        update_post_meta( $new_coupon_id, $meta_key, $meta_value );
                    }
                        
                    //attach the coupon reference to the order after to the coupon is correctly created
                    //$item_id, $meta_key, $meta_value, $unique
                    wc_add_order_item_meta( $order_product_detail, '_gift_code', $coupon_code, TRUE );
                    
                    // Finally send an email to the receiver with the coupon ID, client name, receiver email and name
                    if ( $send_email ) {
                    	error_log(print_r('send email', true));
                    	$this->wcs_sendEmail($order, $new_coupon_id, $client_name, $friend_email, $friend_name, $friend_message, $coupon_code);
                    }
                }
            }
        }

	    /**
	     * @param $order WC_Order
	     * @param $post
	     * @param $client_name
	     * @param $email
	     * @param $name
	     * @param $friend_message
	     * @param $coupon_code
	     *
	     * TODO: $post is currently post of type coupon but may need order for language and copy original customer..
	     * TODO: better to do all from order, then can implement resend on order screen
	     * TODO: use WC_Coupon class rather than post for coupon data
	     *
	     */
        public function wcs_sendEmail ( $order, $post, $client_name, $email, $name, $friend_message, $coupon_code ) {
	        if ( function_exists( 'pll_get_post_language' ) ) {
		        $locale = pll_get_post_language( $order->get_id(), 'locale' );
		        switch_to_locale( $locale );
	        }

            // Get the coupon code amount
            $coupon_amount = get_post_meta($post, 'coupon_amount', true);
            $coupon_expire = get_post_meta($post, 'date_expires', true);
            $coupon_has_expired = false;
	        if ( $coupon_expire && strtotime( $coupon_expire ) && ( current_time( 'timestamp', true ) > strtotime( $coupon_expire ) ) ) {
		        $coupon_has_expired = true;
	        }
            $usage_count = get_post_meta($post, 'usage_count', true);
            //coupons are in shop base currency not current user/order currency so get unfiltered base ccy
            $formatted_price = wc_price($coupon_amount, array('currency' => get_option( 'woocommerce_currency' ), ));
                
            /**
             * allow theme to apply special formatting including link to auto-add-coupon to basket
             * ( ?apply_coupon=coupon_code requires plugin, not implemented in woocommerce core)
             * could also add fancy formatting / additional message and QR codes
             *
             * @param string $formatted_coupon_code default formatting
             * @param string $coupon_code           raw coupon code
             * @param string $coupon_amount         raw coupon amount
             * @param string $formatted_price       formatted coupon amount
             */
            $formatted_coupon_code = apply_filters('wcs_format_gift_coupon', 
                '<h2>' . $coupon_code . '</h2>', 
                $coupon_code, $coupon_amount, $formatted_price);

            // Construct email datas
            //get_current_site()->site_name; //wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
            $blogname       = get_bloginfo( 'name', 'display' ); 
            $blogurl        = get_home_url();
            $shopurl        = wc_get_page_permalink ('shop');
            $subject        = '[' . $blogname . '] ' . $client_name . ' ' . __(' offers you a gift card !', 'wcs-sell-coupons' ) ;
            $sendEmail      = get_bloginfo( 'admin_email' );
            $headers        = array('Content-Type: text/html; charset=UTF-8');


            // create folder to store pdf (if not exists)
	        global $wp_filesystem;
	        $wp_upload_dir = wp_upload_dir();

	        if ( ! file_exists( $wp_upload_dir['basedir'] . '/invitations' ) ) {
		        mkdir( $wp_upload_dir['basedir'] . '/invitations', 0777, true );
	        }
	        $order_id = trim(str_replace('#', '', $order->get_order_number()));
            // create a pdf
            $mpdf = new\Mpdf\Mpdf();
            $pdf_name= 'invitation-'. $order_id . '-' . sanitize_title( $name ) . '-' . $coupon_code . '.pdf';
	        ob_start();
            require 'views/invitation.php';
            $html = ob_get_flush();
            $mpdf->WriteHTML( $html );
            $mpdf->Output( $wp_upload_dir['basedir'] . '/invitations/'. $pdf_name, \Mpdf\Output\Destination::FILE );

            // Instancy a new WC Mail class
            $mailer         = WC()->mailer();

            ob_start();
            echo '<style >';
            wc_get_template( 'emails/email-styles.php' );
            echo '</style>';
            $messageStyle        = ob_get_clean();

            $email_heading  = '<a style="text-align: center;" href="' . $blogurl .'">Votre cadeau à <br>'. $blogname . '</a>';
            $toEmail        = $email;

            ob_start();
            ?>
            <h2>Bonjour <?php echo $name;?></h2>
            <p><?php echo $client_name; ?> vous a offert un repas au Restaurant La Maison Tourangelle en vous laissant ce message :</p>
            <p><?php echo $friend_message; ?></p>
            <p>Pour en profiter, présentez le document joint à cet e-mail avant le <?php echo date_i18n( 'd F Y', $coupon_expire ) ?>.</p>
            <p>A bientôt dans notre restaurant.</p>
            <p>Frédéric ARNAULT</p>


<?php
            $theMessage = ob_get_flush();

            $messageBody = $mailer->wrap_message( $email_heading, $theMessage );
            $attachment  = $wp_upload_dir['basedir'] . '/invitations/'. $pdf_name;

            // register in media lib
	        $this->wcs_register_pdf_in_library( $order, $attachment );

            // Send the email
            $mailer->send( $toEmail, $subject, $messageStyle . $messageBody, $headers, $attachment );

            //message to forward
            $forwardedMessage = wptexturize( '<br />-------------' . __( 'Copy of Message', 'wcs-sell-coupons' ) . '-------------<br />' .
                __( 'To:', 'wcs-sell-coupons' ) . ' ' . $name . ' &lt;' . $toEmail . '&gt;<br />' .
                __( 'Subject:', 'wcs-sell-coupons' ) . ' ' . $subject . '<br /><br />')
            . $messageBody;

            // Send copy of email to client
            $custEmail   = $order->get_billing_email();
            $custSubject = __( 'Your gift coupon was sent to: ','wcs-sell-coupons' ) . $name;
	        $mailer->send( $custEmail, $custSubject, $messageStyle . $custSubject . $forwardedMessage, $headers, $attachment );
            
            // Send copy of email to shop admin
            //move to class implementation as $this->get_option( 'recipient', get_option( 'admin_email' ) );
            $shopEmail = get_option( 'admin_email' );
            $shopSubject = __( 'Gift coupon was issued for order: ','wcs-sell-coupons' ) . $order->get_id();
	        $mailer->send( $shopEmail, $shopSubject, $messageStyle . $shopSubject . $forwardedMessage, $headers, $attachment );
        }

	    /**
	     *
	     * Function to generate custom number used by wcs_create_coupon_on_order_complete and use wp_generate_password function.
	     *
	     * @return string
	     */
        function wsc_random_number() {
            $random_number = wp_rand( 100000, 999999 );
            return $random_number;
        }

        /**
         * Fires when the locale is switched.
         *
         * @since 4.7.0
         *
         * @param string $locale The new locale.
         */
        public function wcs_switch_locale( $locale ){
            $this->pluginlocale = $locale;
            $this->load_textdomain();
        }

	    /**
	     * @param $locale
	     * @param $domain
	     *
	     *
	     *
	     * @return mixed
	     */
        public function wcs_correct_locale( $locale, $domain ){
            if ( $this->pluginlocale ){
                return $this->pluginlocale;
            } else {
                return $locale;
            }
        }

        public function wcs_invitation_skip_images_sizes( $payload, $orig_w, $orig_h, $dest_w, $dest_h, $crop ) {
		 return false;
		}

        public function wcs_register_pdf_in_library( $order, $attachment_path ){

        	add_filter( 'image_resize_dimensions', array( $this, 'wcs_invitation_skip_images_sizes' ), 10, 6 );

	        $filetype      = wp_check_filetype( $attachment_path , null );
            $attachment = [
	            'guid'           => $attachment_path,
	            'post_mime_type' => $filetype['type'],
	            'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $attachment_path ) ),
	            'post_content'   => '',
	            'post_status'    => 'inherit',
            ];
	        $attachment_id = wp_insert_attachment( $attachment, $attachment_path, $order->get_id() );

	        update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'gift card for' . $order->get_id() );

	        // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
	        require_once ABSPATH . 'wp-admin/includes/image.php';

	        // Generate the metadata for the attachment, and update the database record.
	        $attach_data = wp_generate_attachment_metadata( $attachment_id, $attachment_path );
	        wp_update_attachment_metadata( $attachment_id, $attach_data );

	        remove_filter( 'image_resize_dimensions', array( $this, 'wcs_invitation_skip_images_sizes' ), 10, 6 );

	        return $attachment_id;
        }
    }
    Woo_Sell_Coupons::register();
}