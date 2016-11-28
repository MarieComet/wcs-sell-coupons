<?php
/**
 * @package WooCommerce Sell Coupons
 * @version 1.6
 */
/*
Plugin Name: WooCommerce Sell Coupons
*/
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
    // define plugin url
    define( 'WSC__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

    // require register coupon product type class
    require_once( WSC__PLUGIN_DIR . 'wcs-register-coupon.php' );

    // Declare our Woo_Sell_Coupons class
    class Woo_Sell_Coupons {
        private static $instance;
        public static function register() {
            if (self::$instance == null) {
                self::$instance = new Woo_Sell_Coupons();
            }
        }

        public function __construct() {
            add_filter('post_class', array($this, 'wcs_product_type_class' ));
            add_action('wp_enqueue_scripts', array( $this, 'wcs_register_plugin_styles' ) );
            add_filter('woocommerce_quantity_input_max', array($this, 'wcs_remove_qty_input'), 10, 2);
            add_filter('woocommerce_cart_item_quantity', array($this, 'wcs_remove_cart_qty_input'), 10, 3);
            add_action('woocommerce_before_add_to_cart_button', array($this, 'wcs_email_friend'), 10);
            add_action('woocommerce_add_to_cart_validation', array($this, 'wcs_check_custom_fields'), 10, 5 );         
            add_action('wp_ajax_custom_data', array($this, 'wcs_custom_data_callback'));
            add_action('wp_ajax_nopriv_custom_data', array($this, 'wcs_custom_data_callback'));
            add_filter('woocommerce_add_cart_item_data', array($this, 'wcs_add_cart_item_custom_data'), 10, 2 );
            add_filter('woocommerce_get_cart_item_from_session', array($this, 'wcs_get_cart_items_from_session'), 1, 3 );
            add_filter('woocommerce_cart_item_name', array($this, 'wcs_add_user_custom_session'),1,3);
            add_filter('woocommerce_order_item_name', array($this, 'wcs_woocommerce_order_custom_session'), 10, 3 );
            add_action('woocommerce_add_order_item_meta', array($this, 'wcs_add_values_to_order_item_meta'),1,2);
            add_action('woocommerce_before_cart_item_quantity_zero',array($this, 'wcs_remove_user_custom_data_options_from_cart') ,1,1);
            add_action('woocommerce_order_status_completed', array($this, 'wcs_create_coupon_on_order_complete') );
        }

        /**
         * Function used to check if a product is a gift_coupon type. 
         * @param int  $product_id   The product ID to check
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

        // Add a custom class to our custom product type. WooCommerce don't do that automatically.
        function wcs_product_type_class( $classes ) {
            global $post;

            if( $this->check_if_coupon_gift($post->ID)) {
                $classes[] = 'product-coupon-gift';
            }
            return $classes;
        }

        /**
         * Register and enqueue style sheet.
         */
        public function wcs_register_plugin_styles() {
            wp_register_style( 'woo-sell-coupons', plugins_url( 'woo-sell-coupons/woo-sell-coupons.css',  dirname(__FILE__)  ) );
            wp_enqueue_style( 'woo-sell-coupons' );
        }

        /**
        * Set a quantity input max value on the product page
        * Need to do that because we need to add ONE email and name friend for EACH product
        * Hooked on woocommerce_quantity_input_max see WooCommerce docs.
        * @param int  $var   The max quantity
        * @param mixed  $product   The current product
        */
        function wcs_remove_qty_input($var, $product) {
            if( $this->check_if_coupon_gift($product->ID)) {
                $var = '1';
            }
            return $var;

        }

        /**
        * Set a quantity input max value on the cart page
        * Need to do that because we need to add ONE email and name friend for EACH product
        * Hooked on woocommerce_cart_item_quantity see WooCommerce docs.
        */
         function wcs_remove_cart_qty_input($produc_quantity, $cart_item_key, $cart_item) {
            if( $this->check_if_coupon_gift($cart_item['product_id'])) {
                $var = '1';
            }
            return $var;

        }

        /**
        *   Add two custom fields on the single product page before add to cart button.
        *   Hooked on woocommerce_before_add_to_cart_button 
        */
        function wcs_email_friend() {
            global $woocommerce, $post;
            if( $this->check_if_coupon_gift($post->ID)) {
                echo '<div class="wcs-data">';
                echo '<label for="wcs_email_friend">E-mail du destinataire : <abbr class="required" title="requis">*</abbr></label>';
                echo '<input type="email" id="wcs_email_friend" name="wcs_email_friend" placeholder="email@mail.com" />';
                echo '<label for="wcs_name_friend">Nom et/ou prénom du destinataire : <abbr class="required" title="requis">*</abbr></label>';
                echo '<input type="text" id="wcs_name_friend" name="wcs_name_friend" placeholder="Jean Dupont"/>';
                echo '</div>';
            }
        }

        /**
        *   Our callback function receive our two custom fields value
        *   We create a new session with session_start();
        *   and create two new session values : wcs_email_friend and wcs_name_friend
        *   Hooked on wp_ajax_nopriv_custom_data and wp_ajax_custom_data See ajax in WordPress
        */
        function wcs_custom_data_callback() {
            // We'll use this to post back the data the server received
            print_r($_POST);
            // Lets store the data in the current session
            session_start();
            $_SESSION['wcs_email_friend']  = $_POST['wcs_email_friend']; 
            $_SESSION['wcs_name_friend']  = $_POST['wcs_name_friend'];
            // RIP
            die();
        }

        /**
        *   Add a validation when a gift_coupon is added to cart
        *   Check if our two custom fields are not empty, if there are display a WC notice error
        *   Hooked on woocommerce_add_to_cart_validation
        */
        function wcs_check_custom_fields($passed, $product_id, $quantity) {
            if( $this->check_if_coupon_gift($product_id ) && !empty($_POST['wcs_email_friend']) && !empty($_POST['wcs_name_friend'])) {
                $passed = true;
            } else {
                wc_add_notice( __( 'Renseignez les champs obligatoires.', 'woocommerce' ), 'error' );
                $passed = false;
            }
            return $passed;

        }
        
        /**
        *   Add our two custom field values storent in SESSION to the cart item object
        *   Hooked on woocommerce_add_cart_item_data
        */
        function wcs_add_cart_item_custom_data( $cart_item_meta, $product_id ) {
          global $woocommerce;
          if( $this->check_if_coupon_gift($product_id ) && !empty($_POST['wcs_email_friend']) && !empty($_POST['wcs_name_friend'])) {
              $cart_item_meta['wcs_email_friend'] = $_POST['wcs_email_friend'];
              $cart_item_meta['wcs_name_friend'] = $_POST['wcs_name_friend'];
              return $cart_item_meta; 
          }
        }

        /**
        *   Get our custom field values in cart from session
        *   Hooked on woocommerce_get_cart_item_from_session
        */
        function wcs_get_cart_items_from_session( $item, $values, $key ) {
            if ( array_key_exists( 'wcs_email_friend', $values ) )
                $item[ 'wcs_email_friend' ] = $values['wcs_email_friend'];
            if ( array_key_exists( 'wcs_name_friend', $values ) )
                $item[ 'wcs_name_friend' ] = $values['wcs_name_friend'];
            return $item;
        }

        /**
        *   Display the custom field values next to the Product name in the cart
        *   Hooked on woocommerce_cart_item_name
        */
        function wcs_add_user_custom_session($product_name, $values, $cart_item_key ) {
            if( $this->check_if_coupon_gift($values['product_id'] ) && isset($values['wcs_name_friend']) && isset($values['wcs_email_friend'])) {
                $return_string = $product_name . "<br />" . $values['wcs_name_friend'] . "<br />" . $values['wcs_email_friend'];
                return $return_string;
            } else {
                return $product_name;
            }
        }

        /**
        *   Display custom field values in the order confirmation page
        *   Hooked on woocommerce_order_item_name
        */
        function wcs_woocommerce_order_custom_session($name, $item) {
            if( $this->check_if_coupon_gift($item['product_id'] ) && isset($item['name_to']) && isset($item['mail_to'])) {
                $return_string = $name . "<br />" . $item['name_to'] . "<br />" . $item['mail_to'];
                return $return_string;
            } else {
                return $name;
            }
        }

        /**
        *   Add our custom fields values as order item values, it can be seen in the order admin page and we can get it later
        *   Hooked on woocommerce_add_order_item_meta
        */       
        function wcs_add_values_to_order_item_meta($item_id, $values) {
            global $woocommerce,$wpdb;

            if( $this->check_if_coupon_gift($values['product_id'] ) && isset($values['wcs_name_friend']) && isset($values['wcs_email_friend'])) {
                // lets add the meta data to the order!
                wc_add_order_item_meta($item_id,'_name_to', $values['wcs_name_friend']);
                wc_add_order_item_meta($item_id,'_mail_to', $values['wcs_email_friend']);
            }
        }
        
        /**
        *   Remove the cart content custom values when a product is deleted
        *   Hooked on woocommerce_before_cart_item_quantity_zero
        */        
        function wcs_remove_user_custom_data_options_from_cart($cart_item_key) {
            global $woocommerce;
            // Get cart
            $cart = $woocommerce->cart->get_cart();
            // For each item in cart, if item is upsell of deleted product, delete it
            foreach( $cart as $key => $values) {
            if ( $values['name_to'] == $cart_item_key ||  $values['mail_to'] == $cart_item_key)
                unset( $woocommerce->cart->cart_contents[ $key ] );
            }
        }

        /**
        *   Finally when an order is completed we create a coupon with the previously custom values
        *   This function is hooked on woocommerce_order_status_completed, when a payment is OK and NOT before.
        */
        function wcs_create_coupon_on_order_complete($order_id) {

            // Instancy a new WC_Order class.
            $order = new WC_Order( $order_id );

            // Get each product in order.
            $order_items =  $order->get_items(); 

            foreach($order_items as $order_product_detail => $values ) {

                // check if the product is a gift_coupon !
                if( $this->check_if_coupon_gift($values['product_id']) )  {

                    // Get the customer order values
                    $client_first_name = get_post_meta($order->id, '_billing_first_name', true);
                    $client_last_name = get_post_meta($order->id, '_billing_last_name', true);

                    // Create a nice name...
                    $client_name = $client_first_name .' '. $client_last_name;

                    // Get the product price (gift amount)
                    $product = wc_get_product($values['product_id']);
                    $product_price = $product->get_price();

                    // Get the custom order values : friend and email 
                    $friend_email = $values['item_meta']['_mail_to']['0'];
                    $friend_name = $values['item_meta']['_name_to']['0'];

                    // Generate a random code
                    $coupon_code = $this->wsc_random_number();
                    // Amount coupon code
                    $amount = $product_price; // Amount
                    $discount_type = 'fixed_cart'; // Type: fixed_cart, percent, fixed_product, percent_product

                    // Construct our coupon post
                    $coupon = array(
                        'post_title' => $coupon_code,
                        'post_content' => '',
                        'post_excerpt' => 'Pour: ' . $friend_name . ' - Envoyé à: '. $friend_email,
                        'post_status' => 'publish',
                        'post_author' => 1,
                        'post_type'     => 'shop_coupon'
                    );

                    // Check if coupon code exist, low probability but... If yes juste update values
                    if(post_exists($coupon_code)) {
                        $new_coupon_id = $coupon_code;          
                        update_post_meta( $new_coupon_id, 'discount_type', $discount_type );
                        update_post_meta( $new_coupon_id, 'coupon_amount', $amount );
                        update_post_meta( $new_coupon_id, 'individual_use', 'yes' );
                        update_post_meta( $new_coupon_id, 'usage_limit', '1' );
                        update_post_meta( $new_coupon_id, 'usage_limit_per_user', '1' );
                    // If not, create a new coupon post !
                    } else {
                        $new_coupon_id = wp_insert_post( $coupon );
                        update_post_meta( $new_coupon_id, 'discount_type', $discount_type );
                        update_post_meta( $new_coupon_id, 'coupon_amount', $amount );
                        update_post_meta( $new_coupon_id, 'individual_use', 'yes' );
                        update_post_meta( $new_coupon_id, 'usage_limit', '1' );
                        update_post_meta( $new_coupon_id, 'usage_limit_per_user', '1' );
                    }

                    // Finally send an email to the receiver with the coupon ID, client name, receiver email and name
                    $this->wcs_sendEmail($new_coupon_id, $client_name, $friend_email, $friend_name);
                }
            }
        }

        // Send an email to the receiver, params passed previously.
        public function wcs_sendEmail ( $post, $client_name, $email, $name ) {

            // Get the coupon code amount
            $counpon_amount = get_post_meta($post, 'coupon_amount', true);

            // Construct email datas
            $blogname       = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
            $blogurl        = wp_specialchars_decode(get_option('home'), ENT_QUOTES);
            $subject        = '[' . $blogname . '] ' . $client_name . ' vous offre un chèque cadeau !';
            $sendEmail      = get_bloginfo( 'admin_email' );
            $headers        = array('Content-Type: text/html; charset=UTF-8');

            ob_start();

            // Instancy a new WC Mail class
            $mailer         = WC()->mailer();

            echo '<style >';
            wc_get_template( 'emails/email-styles.php' );
            echo '</style>';

            $email_heading  = __( 'Votre chèque cadeau à utiliser sur ', 'rpgiftcards' ) . '<a href="' . $blogurl .'">'. $blogname . '</a>';
            $toEmail        = $email;

            $theMessage     = '<h2>Bonjour ' . $name . ',</h2><p>'. $client_name . ' vous offre un chèque cadeau de '. $counpon_amount . '€ à utiliser sur <a href="' . $blogurl .'">'. $blogname . '</a>.</p></br><p>Pour l\'utiliser entrez ce code: <bold>' . get_the_title($post) . '</bold> dans votre panier lors de votre achat.</p><h3>A  bientôt sur <a href="' . $blogurl .'">'. $blogname . '</a> !';

            echo $mailer->wrap_message( $email_heading, $theMessage );

            $message        = ob_get_clean();
            $attachment = '';

            // Send the email
            $mailer->send( $toEmail, $subject, $message, $headers, $attachment );

        }

        // Function to generate custom number used by wcs_create_coupon_on_order_complete and use wp_generate_password function
        function wsc_random_number() {
            $random_number = wp_generate_password( 15, false );
            return $random_number;
        }
    }
    Woo_Sell_Coupons::register();
}
?>