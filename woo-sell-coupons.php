<?php
/*
 * Plugin Name: WooCommerce Sell Coupons
 * Text Domain: wcs-sell-coupons
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 3.1.2
 * Plugin URI: https://github.com/MarieComet/wcs-sell-coupons
 * Description: This plugin create a new WooCommerce product type and add possibilty to sell Coupons as Gift Card in front-office. Please visit WooCommerce > Settings > General once activated !
 * Version: 1.0.2
 * Author: Marie Comet
 * Author URI: https://www.mariecomet.fr/
 * License: GPLv2 or later
 * @package WooCommerce Sell Coupons
 * @version 1.6
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

        private $pluginlocale;
    /**
		 * load translations
		 */
		public static function load_textdomain() {
			load_plugin_textdomain( 'wcs-sell-coupons', false, 'wcs-sell-coupons/languages/' );
		}

        public function wcs_admin_notices() {
            if (get_option('wcs_gift_coupon_prefix')!=''){return;}
            ?>
            <div class="notice notice-success is-dismissible">
              <p><a href="<?php echo(admin_url( 'admin.php?page=wc-settings')) ?>"><?php _e( 'Please visit WooCommerce > Settings > General to setup WooCommerce Sell Coupons !', 'wcs-sell-coupons' ); ?></a></p>
            </div>
            <?php
        }

        public function __construct() {
			add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
            add_action('admin_notices', array($this, 'wcs_admin_notices'));
            add_action('wp_enqueue_scripts', array( $this, 'wcs_register_plugin_styles' ) );
            // Add custom option field in woocommerce general setting
            add_filter( 'woocommerce_general_settings', array($this, 'wc_coupon_setting_page'));
            add_action('woocommerce_before_add_to_cart_button', array($this, 'wcs_email_friend'), 10);
            add_action('woocommerce_add_to_cart_validation', array($this, 'wcs_check_custom_fields'), 10, 5 );         
            add_action('wp_ajax_custom_data', array($this, 'wcs_custom_data_callback'));
            add_action('wp_ajax_nopriv_custom_data', array($this, 'wcs_custom_data_callback'));
            add_filter('woocommerce_add_cart_item_data', array($this, 'wcs_add_cart_item_custom_data'), 10, 2 );
            add_filter('woocommerce_get_cart_item_from_session', array($this, 'wcs_get_cart_items_from_session'), 1, 3 );
            add_filter('woocommerce_cart_item_name', array($this, 'wcs_add_user_custom_session'),1,3);
            add_filter('woocommerce_order_item_name', array($this, 'wcs_woocommerce_order_custom_session'), 10, 3 );
            add_action('woocommerce_add_order_item_meta', array($this, 'wcs_add_values_to_order_item_meta'),1,2);
            add_filter('woocommerce_order_item_display_meta_key', array($this, 'wcs_add_order_formatted_key'), 10, 2);
            add_action('woocommerce_before_cart_item_quantity_zero',array($this, 'wcs_remove_user_custom_data_options_from_cart') ,1,1);
            add_action('woocommerce_order_status_completed', array($this, 'wcs_create_coupon_on_order_complete') );

            //locale management for translations
            add_action ('switch_locale', array($this, 'wcs_switch_locale'), 10, 1 );
            add_filter('plugin_locale', array($this, 'wcs_correct_locale'), 100, 2);            
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

        /**
         * Register and enqueue style sheet.
         */
        public function wcs_register_plugin_styles() {
            /*
            wp_register_style( 'wcs-sell-coupons', plugins_url( 'wcs-sell-coupons/wcs-sell-coupons.css',  dirname(__FILE__)  ) );
            wp_enqueue_style( 'wcs-sell-coupons' );
             * 
             */
        }

        /**
         * Add custom option field in woocommerce general setting
         * Hooked on woocommerce_general_settings
         * @return array
         */
        function wc_coupon_setting_page($settings) {
            $settings[] = array( 'name' => __( 'Bons cadeaux', 'wcs-sell-coupons' ), 'type' => 'title', 'desc' => '', 'id' => 'wcs_gift_coupon' );

            $settings[] = array(
                'title'     => __( 'Durée des bons cadeaux', 'wcs-sell-coupons' ),
                'desc'      => '',
                'id'        => 'wcs_gift_coupon_duration',
                'desc_tip'      => __( 'La durée limite des bons cadeaux (en jours)', 'wcs-sell-coupons' ),
                'type'      => 'number',
                'default'   => '',
                'css'      => 'min-width:300px;',
                'placeholder' => __( '30', 'wcs-sell-coupons' ),
            );
            $settings[] = array(
                'title'     => __( 'Préfixe des codes bons cadeaux', 'wcs-sell-coupons' ),
                'desc'      => '',
                'id'        => 'wcs_gift_coupon_prefix',
                'desc_tip'      => __( 'Le préfixe utilisé dans les codes promos', 'wcs-sell-coupons' ),
                'type'      => 'text',
                'default'   => '',
                'css'      => 'min-width:300px;',
                'placeholder' => __( 'GF', 'wcs-sell-coupons' ),
            );

            $settings[] = array( 'type' => 'sectionend', 'id' => 'wcs_gift_coupon');
            return $settings;
        }

        /**
        *   Add two custom fields on the single product page before add to cart button.
        *   Hooked on woocommerce_before_add_to_cart_button 
        */
        function wcs_email_friend() {
            global $woocommerce, $post;
            if( $this->check_if_coupon_gift($post->ID)) {
                echo '<div class="wcs-data">';
                echo '<label for="wcs_email_friend">' . __('E-mail du destinataire', 'wcs-sell-coupons') . ': <abbr class="required" title="requis">*</abbr></label>';
                echo '<input type="email" id="wcs_email_friend" name="wcs_email_friend" placeholder="email@mail.com" />';
                echo '<label for="wcs_name_friend">' . __('Nom et/ou prénom du destinataire', 'wcs-sell-coupons') . ': <abbr class="required" title="requis">*</abbr></label>';
                echo '<input type="text" id="wcs_name_friend" name="wcs_name_friend" placeholder="Jean Dupont"/>';
                echo '</div>';

                echo '<label for="wcs_gift_message">' . __('Gift message', 'wcs-sell-coupons') . ': </label>';
                $gift_message = __('Sending you this gift coupon with best wishes', 'wcs-sell-coupons');
                $thumbnail = wp_get_attachment_image( get_post_thumbnail_id(), 'thumbnail');
                if  ( $thumbnail ) { $gift_message .= '<br />' . $thumbnail; }
                $gift_message = apply_filters('wcs_gift_message', $gift_message);
                /*wp_editor($gift_message , 'wcs_gift_message', array(
                    'media_buttons'    => false,
                    'teeny'             => true,
                    'quicktags' => false,
                    'tinymce' => false,
                    'editor_height' => '150'
                ) );*/
                echo '<textarea id="wcs_gift_message" name="wcs_gift_message" placeholder="Add your gift message here."></textarea>';
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

            WC()->session->set('wcs_email_friend', $_POST['wcs_email_friend']);
            WC()->session->set('wcs_name_friend', $_POST['wcs_email_friend']);
            WC()->session->set('wcs_gift_message', $_POST['wcs_email_friend']);
            // RIP
            die();
        }

        /**
        *   Add a validation when a gift_coupon is added to cart
        *   Check if our two custom fields are not empty, if there are display a WC notice error
        *   Hooked on woocommerce_add_to_cart_validation
        */
        function wcs_check_custom_fields($passed, $product_id, $quantity) {
            if( $this->check_if_coupon_gift($product_id ) )
                if (!empty($_POST['wcs_email_friend']) && !empty($_POST['wcs_name_friend'])) {
                    $passed = true;
                } else {
                    wc_add_notice( __( 'Renseignez les champs obligatoires.', 'wcs-sell-coupons' ), 'error' );
                    $passed = false;
            } else {
                $passed = true;
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
                $cart_item_meta['wcs_email_friend'] = sanitize_email($_POST['wcs_email_friend']);
                $cart_item_meta['wcs_name_friend'] = sanitize_text_field($_POST['wcs_name_friend']);
                $cart_item_meta['wcs_gift_message'] = wp_kses_post($_POST['wcs_gift_message']);
            }
            return $cart_item_meta; 
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
            if ( array_key_exists( 'wcs_gift_message', $values ) )
                $item[ 'wcs_gift_message' ] = $values['wcs_gift_message'];
            return $item;
        }

        /**
        *   Display the custom field values next to the Product name in the cart
        *   Hooked on woocommerce_cart_item_name
        */
        function wcs_add_user_custom_session($product_name, $values, $cart_item_key ) {
            if( $this->check_if_coupon_gift($values['product_id'] ) && isset($values['wcs_name_friend']) && isset($values['wcs_email_friend'])) {
                $return_string = $product_name . "</br><span>" . __('To', 'wcs-sell-coupons') . ": " . $values['wcs_name_friend'] . " (" . $values['wcs_email_friend'] . ')</span>';
                if ( isset($values['wcs_gift_message']) && $values['wcs_gift_message'] ){
                    $return_string .= "<br /><span>" . stripslashes($values['wcs_gift_message']) . '</span>';
                }
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
                if ( isset($item['gift_message']) && $item['gift_message'] ){
                    $return_string .= "<br /><span>" . stripslashes($item['gift_message']) . '</span>';
                }
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
                wc_add_order_item_meta($item_id,'_gift_message', $values['wcs_gift_message']);
            }
        }
        
        /**
        *   Add $display_key for display it in admin
        *   Hooked on woocommerce_order_item_display_meta_key
        */
        function wcs_add_order_formatted_key($display_key, $meta) {

            if($meta->key === '_name_to') {
                $display_key = __('Destinataire', 'wcs-sell-coupons');
            }
            if($meta->key === '_mail_to') {
                $display_key = __('E-mail', 'wcs-sell-coupons');
            }
            if($meta->key === '_gift_message') {
                $display_key = __('Message', 'wcs-sell-coupons');
            }
            if($meta->key === '_gift_code') {
                $display_key = __('Coupon', 'wcs-sell-coupons');
            }
            return $display_key;
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
            if ( $values['name_to'] == $cart_item_key ||  $values['mail_to'] == $cart_item_key 
                 ||  $values['gift_message'] == $cart_item_key)
                unset( $woocommerce->cart->cart_contents[ $key ] );
            }
        }

        /**
        *   Finally when an order is completed we create a coupon with the previously custom values
        *   This function is hooked on woocommerce_order_status_completed, when a payment is OK and NOT before.
        *   TODO: Orders currently go to Processing on payment, manual move to completion before sending coupon 
        *         is unnecessary:  for example if other goods are ordered at the same time 
        *         order will not be completed until it is confirmed they have arrived
        */
        function wcs_create_coupon_on_order_complete($order_id) {

            // Instancy a new WC_Order class.
            $order = new WC_Order( $order_id );

            // Get each product in order.
            $order_items =  $order->get_items(); 

            $duration_gift_coupon = get_option('wcs_gift_coupon_duration');
            $prefix_gift_coupon = get_option('wcs_gift_coupon_prefix');

            $today = time();
            if( !empty($duration_gift_coupon) ) {
                $date_expire = strtotime("+".$duration_gift_coupon." days", $today);

            } else { // default 30 days
                $date_expire = strtotime("+30 days", $today);
            }
            $expiry_date = wc_format_datetime($date_expire);

            foreach($order_items as $order_product_detail => $values ) {

                // check if the product is a gift_coupon !
                if( $this->check_if_coupon_gift($values['product_id']) )  {

                    // Get the customer order values
                    $client_first_name = get_post_meta($order_id, '_billing_first_name', true);
                    $client_last_name = get_post_meta($order_id, '_billing_last_name', true);

                    // Create a nice name...
                    $client_name = $client_first_name .' '. $client_last_name;

                    // Get the product price (gift amount)
                    $product = wc_get_product($values['product_id']);
                    $product_price = $product->get_regular_price('edit');
                    // Amount coupon code
                    $amount = $product_price; // Amount

                    // Get the custom order values : friend and email 
                    $friend_email = $values['item_meta']['_mail_to'];
                    $friend_name = $values['item_meta']['_name_to'];
                    $friend_message = isset($values['item_meta']['_gift_message']) ? 
                        stripslashes( $values['item_meta']['_gift_message'] ) : '';

                    //if coupon already issued, get it, don't issue again by changing the status repeatedly
                    $coupon_code = wc_get_order_item_meta($order_product_detail, '_gift_code');                    
                    $new_coupon_id = ($coupon_code) ? post_exists($coupon_code) : 0;  
                    if (! $new_coupon_id){
                        // Generate a random code
                        $coupon_code = strtolower($prefix_gift_coupon.'_'.$this->wsc_random_number());

                        // Construct our coupon post
                        $coupon = array(
                            'post_title' => $coupon_code,
                            'post_content' => '',
                            'post_excerpt' => __('Pour:', 'wcs-sell-coupons').' ' . $friend_name . ' ' . 
                                              __('- Envoyé à:', 'wcs-sell-coupons') . ' '. $friend_email . 
                                              ' '. $friend_message,
                            'post_status' => 'publish',
                            'post_author' => 1,
                            'post_type'   => 'shop_coupon',
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
                    wc_add_order_item_meta($order_product_detail, '_gift_code', $coupon_code, TRUE);
                    
                    // Finally send an email to the receiver with the coupon ID, client name, receiver email and name
                    $this->wcs_sendEmail($order, $new_coupon_id, $client_name, $friend_email, $friend_name, $friend_message, $coupon_code);
                }
            }
        }

        // Send an email to the receiver, params passed previously.
        // TODO: $post is currently post of type coupon but may need order for language and copy original customer..
        // TODO: better to do all from order, then can implement resend on order screen
        // TODO: use WC_Coupon class rather than post for coupon data
        public function wcs_sendEmail ($order, $post, $client_name, $email, $name, $friend_message, $coupon_code ) {

            if (function_exists('pll_get_post_language')){
                $locale = pll_get_post_language($order->get_id(), 'locale'); 
                switch_to_locale($locale );
            }

            
            // Get the coupon code amount
            $coupon_amount = get_post_meta($post, 'coupon_amount', true);
            $coupon_expire = get_post_meta($post, 'date_expires', true);
            $coupon_has_expired = false;
            if ($coupon_expire && strtotime($coupon_expire) && 
                (current_time('timestamp', true) > strtotime($coupon_expire)) ){
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
            $subject        = '[' . $blogname . '] ' . $client_name . ' ' . __(' vous offre un chèque cadeau !', 'wcs-sell-coupons' ) ;
            $sendEmail      = get_bloginfo( 'admin_email' );
            $headers        = array('Content-Type: text/html; charset=UTF-8');


            // Instancy a new WC Mail class
            $mailer         = WC()->mailer();

            ob_start();
            echo '<style >';
            wc_get_template( 'emails/email-styles.php' );
            echo '</style>';
            $messageStyle        = ob_get_clean();

            $email_heading  = __( 'Votre chèque cadeau à utiliser sur ', 'wcs-sell-coupons' ) . ' <a href="' . $blogurl .'">'. $blogname . '</a>';
            $toEmail        = $email;
            
            
            $theMessage     = $friend_message . ' <h2>' . __('Bonjour ', 'wcs-sell-coupons' ) . ' ' . $name . ',</h2><p>'. $client_name . ' ' .
                   __(' vous offre un chèque cadeau de ', 'wcs-sell-coupons' ) . ' ' . $formatted_price . ' ' .
                   __(' à utiliser sur ', 'wcs-sell-coupons') . ' <a href="' . $shopurl .'">'. $blogname . '</a>.</p><br />';

            if ($usage_count || $coupon_has_expired){
                $theMessage .= '<h2>' . $coupon_code . '</h2> ';
            } else {
                $theMessage .= '<p>' . __("Pour l'utiliser entrez ce code: ", 'wcs-sell-coupons' ) . 
                    ' <strong>' . $coupon_code . '</strong> ' . 
                __('dans votre panier lors de votre achat.', 'wcs-sell-coupons') . '</p>';
                $theMessage .= $formatted_coupon_code;
            }
            if ($coupon_expire){
                $formatted_coupon_expire = date("Y-m-d", $coupon_expire);
                if ($coupon_has_expired){
                    $theMessage .= '<p>' . sprintf(__('Please note: this coupon expired on %s and cannot be used, this email is for information only.', 'wcs-sell-coupons'), $formatted_coupon_expire) . '</p>';                                    
                } else {
                $theMessage .= '<p>' . __("Attention, ce chèque cadeau est valable seulement jusqu'au ", 
                        'wcs-sell-coupons') . ' ' . $formatted_coupon_expire .' !</p>';
                }
            }
            if ($usage_count){
                $theMessage .= '<p>' . __('Please note: this coupon is already used and cannot be used again, this email is for information only.', 'wcs-sell-coupons') . '</p>';                
            }
            $theMessage .= '<h3>' . __('A  bientôt sur ','wcs-sell-coupons') . ' <a href="' . $blogurl .'">'. $blogname . '</a> !</h3>';

            $messageBody = $mailer->wrap_message( $email_heading, $theMessage );
            
            $attachment = '';

            // Send the email
            $mailer->send( $toEmail, $subject, $messageStyle . $messageBody, $headers, $attachment );

            //message to forward
            $forwardedMessage = wptexturize('<br />-------------' . __('Copy of Message', 'wcs-sell-coupons') . '-------------<br />' . 
                __('To:', 'wcs-sell-coupons') . ' ' . $name . ' &lt;' . $toEmail . '&gt;<br />' .
                __('Subject:', 'wcs-sell-coupons') . ' ' . $subject . '<br /><br />')
            . $messageBody;

            // Send copy of email to client
            $custEmail = $order->get_billing_email();
            $custSubject = __('Your gift coupon was sent to:','wcs-sell-coupons') . ' ' . $name;
            $mailer->send( $custEmail, $custSubject, $messageStyle . $custSubject . $forwardedMessage, 
                $headers, $attachment );
            
            // Send copy of email to shop admin
            //move to class implementation as $this->get_option( 'recipient', get_option( 'admin_email' ) );
            $shopEmail = get_option( 'admin_email' );
            $shopSubject = __('Gift coupon was issued for order:','wcs-sell-coupons') . ' ' . $order->get_id();
            $mailer->send( $shopEmail, $shopSubject, $messageStyle . $shopSubject . $forwardedMessage, 
                $headers, $attachment );
        }

        // Function to generate custom number used by wcs_create_coupon_on_order_complete and use wp_generate_password function
        function wsc_random_number() {
            $random_number = wp_generate_password( 15, false );
            return $random_number;
        }

        
        
		/**
		 * Fires when the locale is switched.
		 *
		 * @since 4.7.0
		 *
		 * @param string $locale The new locale.
		 */
        public function wcs_switch_locale($locale){
            $this->pluginlocale = $locale;
            $this->load_textdomain();
        }        
        /**
         * Filters a plugin's locale.
         *
         * @since 3.0.0
         *
         * @param string $locale The plugin's current locale.
         * @param string $domain Text domain. Unique identifier for retrieving translated strings.
         */
        public function wcs_correct_locale($locale, $domain){
            if ($this->pluginlocale){
                return $this->pluginlocale;
            } else {
                return $locale;
            }
        }        
    }
    Woo_Sell_Coupons::register();
}