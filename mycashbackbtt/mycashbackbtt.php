<?php
/**
 * @package mycashbackbtt
 */
/*
Plugin Name: myCashback Button
Plugin URI: https://www.mycashback.io/woocommerce/plugin
Description: Plugin that lets customer get cashback in 1 day through myCashback platform.
Version: 0.0.1
Author: myCashback
Author URI: https://www.mycashback.io
*/

/* if ( ! defined( 'ABSPATH' ) ) {
    die;
} */

// defined ( 'ABSPATH' ) or die ( 'Permission denied. You cant access it directly this way' );

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

$cookie_name = "cbb_cart";
$cookie_temp_name = "cbb_cart_temp";
if(isset($_COOKIE[$cookie_temp_name])) {
    $cookie_cart_temp = json_decode($_COOKIE[$cookie_temp_name], true);
    if(isset($_COOKIE[$cookie_name])) {
        $cookie_cart = json_decode($_COOKIE[$cookie_name], true);
    } else {
        $cookie_cart = [];
    }
    $found = 0;
    if (count($cookie_cart) > 0) {
        foreach($cookie_cart as $i => $cart) {
            if ($cart["pid"] == $cookie_cart_temp[0]["pid"]){
                $found = 1;
            }
        }
    }
    if ($found == 0) {
        $clickObj = (object) [
            "pid" => $cookie_cart_temp[0]["pid"],
            "trackingid" => $cookie_cart_temp[0]["trackingid"]
        ];
        $cookie_cart[] = $clickObj;
        setcookie($cookie_name, json_encode($cookie_cart), time()+3600, "/");
    }
    setcookie($cookie_temp_name, "", time()-(60*60*24*7),"/");
    unset($_COOKIE[$cookie_temp_name]);
}

class myCashbackbttPlugin
{
    function registerAssets () {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue') );
    }

    function add_cbb_campaign_menu(){
        add_menu_page( 'myCashback Campaigns', 'myCashback', 'manage_options', 'mycashback-campaign', array( $this, 'cbb_campaign'), 'dashicons-money-alt', 56 );
    }

    function cbb_campaign() {
        echo '<div class="wrap"><h1 class="wp-heading-inline">Campaign</h1>
        <a href="'.admin_url("post-new.php?post_type=cashback_campaign").'" class="page-title-action">Add New</a>';
    }

    public static function initAPICheck() {
        $mycashbackbttPlugin = new myCashbackbttPlugin();
        $mycashback_secret = $mycashbackbttPlugin->get_cbb_key('wc_settings_mycashback_secret');
        if ( isset($mycashback_secret[0]->option_value) == false) {
            function admin_notice_key_missing() {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><?php _e( 'Registration for myCashback is incomplete. <a href="/wp-admin/admin.php?page=wc-settings&tab=cashback_wc_tab">Click here to complete your registration.</a>', 'cashback-wc-tab' ); ?></p>
                </div>
                <?php
            }
            add_action( 'load-index.php', 
                function(){
                    add_action( 'admin_notices', 'admin_notice_key_missing' );
                }
            );
        }
    }

    function initCookie() {
        add_action( 'init', array( $this, 'setting_cashback_cookie'));
    }

    function setting_cashback_cookie() {
        $mycashbackbttPlugin = new myCashbackbttPlugin();
        $cookie_valid = $mycashbackbttPlugin->get_cbb_key('wc_settings_mycashback_cookie_period');
        if(isset($cookie_valid)) {
            if($cookie_valid[0]->option_value == "") {
                $cookie_valid = 86400;
            } else {
                $cookie_valid = $cookie_valid[0]->option_value;
            }
        } else {
            $cookie_valid = 86400;
        }
        $cookie_name = "myCashback";
        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')   
            $url = "https://";   
        else  
            $url = "http://";    
        $url.= $_SERVER['HTTP_HOST'];   
        $url.= $_SERVER['REQUEST_URI'];   
        $url_components = parse_url($url);
        if(isset($url_components['query'])){
            parse_str($url_components['query'], $cookie_value);
            if(isset($cookie_value['clickId'])){
                setcookie($cookie_name, $cookie_value['clickId'], (time() + $cookie_valid), "/");
            }
        }
    }

    public static function initSettingMenu() {
        add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
        add_action( 'woocommerce_settings_tabs_cashback_wc_tab', __CLASS__ . '::settings_tab' );
        add_action( 'woocommerce_update_options_cashback_wc_tab', __CLASS__ . '::update_settings' );
    }
    
    public static function add_settings_tab( $settings_tabs ) {
        $settings_tabs['cashback_wc_tab'] = __( 'Cashback', 'woocommerce-settings-tab-mycashback' );
        return $settings_tabs;
    }

    public static function settings_tab() {
        woocommerce_admin_fields( self::get_settings() );
    }

    public static function update_settings() {
        woocommerce_update_options( self::get_settings() );
    }

    public static function get_settings() {
        $settings = array(
            'section_title' => array(
                'name'     => __( 'Settings', 'woocommerce-settings-tab-mycashback' ),
                'type'     => 'title',
                'desc'     => '',
                'id'       => 'wc_settings_tab_mycashback_section_title'
            ),
            'secret' => array(
                'name' => __( 'Secret', 'woocommerce-settings-tab-mycashback' ),
                'type' => 'text',
                'desc' => __( 'myCashback Secret for authentication. <a href="https://aams.mycashback.io/account/register">Click here to Register</a>', 'woocommerce-settings-tab-mycashback' ),
                'id'   => 'wc_settings_mycashback_secret'
            ),
            'validity' => array(
                'name' => __( 'Token Validity', 'woocommerce-settings-tab-mycashback' ),
                'type' => 'text',
                'desc' => __( 'The number of seconds that customer can shop and recieve cashback on website before expiring. Default is set to 24 hours', 'woocommerce-settings-tab-mycashback' ),
                'id'   => 'wc_settings_mycashback_cookie_period'
            ),
            'section_end' => array(
                 'type' => 'sectionend',
                 'id' => 'wc_cashback_wc_tab_section_end'
            )
        );

        return apply_filters( 'wc_settings_tab_mycashback_settings', $settings );
    }

    function get_cbb_key( $value ){
        global $wpdb;
        $sql = $wpdb->prepare( "SELECT * FROM wp_options WHERE option_name = %s", $value );
        return $wpdb->get_results( $sql );
    }

    function mycashback_css_icon(){
        echo '<style>
        #woocommerce-product-data ul.wc-tabs li.Cashback_options.Cashback_tab a:before{
            content: "\f18e";
        }
        </style>';
    }

    function registerProductTab () {
        add_filter('woocommerce_product_data_tabs', array( $this, 'mycashback_product_settings_tabs') );
    }

    function mycashback_product_settings_tabs ( $tabs ) {
        $tabs['Cashback'] = array(
            'label'    => 'Cashback',
            'target'   => 'mycashback_product_data',
            'class'    => array('show_if_simple'),
            'priority' => 21,
        );
        return $tabs;
    }
    
    function registerProductPanel () {
        add_action( 'woocommerce_product_data_panels', array( $this, 'mycashback_product_panels') );
    }

    function mycashback_icon () {
        add_action('admin_head', array( $this, 'mycashback_css_icon'));
    }

    function product_update_complete() {
        add_action( 'save_post', array($this, 'wc_create_or_update_product' ), 10, 3);
    }

    function order_payment_complete() {
        add_action( 'woocommerce_order_status_completed', array( $this, 'so_payment_complete'), 10, 5);
    }

    function order_checkout_complete() {
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'so_checkout_complete'), 10, 5);
    }

    function order_status_rejected(){
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'so_order_rejected'), 10, 5);
    }

    function order_status_failed(){
        add_action( 'woocommerce_order_status_failed', array( $this, 'so_order_failed'), 10, 5);
    }

    function order_status_refunded(){
        add_action( 'woocommerce_order_refunded', array( $this, 'so_order_refunded'), 10, 5);
    }

    function activate () {
        flush_rewrite_rules();
    }

    function deactivate () {
        flush_rewrite_rules();
    }

    /* function uninstall () {

    } */

    function mycashback_product_panels(){
        echo '<div id="mycashback_product_data" class="panel woocommerce_options_panel hidden">';

        woocommerce_wp_checkbox( array(
            'id'      => 'mycashback_product_enable',
            'value'   => get_post_meta( get_the_ID(), 'mycashback_product_enable', true ),
            'label'   => 'Enable Cashback',
            'desc_tip' => true,
            'description' => 'Select this if you want to give cashback for your customers when they purchase this product',
        ) );

        woocommerce_wp_text_input( array(
            'id'                => 'mycashback_plugin_campaign_name',
            'value'             => get_post_meta( get_the_ID(), 'mycashback_plugin_campaign_name', true ),
            'label'             => 'Campaign Name',
            'description'       => 'Give a name for your cashback campaign.'
        ) );

        woocommerce_wp_text_input( array(
            'id'                => 'mycashback_plugin_campaign_desc',
            'value'             => get_post_meta( get_the_ID(), 'mycashback_plugin_campaign_desc', true ),
            'label'             => 'Campaign Description',
            'description'       => 'Provide us a short description of your cashback campaign.'
        ) );
 
        woocommerce_wp_text_input( array(
            'id'                => 'mycashback_plugin_amount',
            'value'             => get_post_meta( get_the_ID(), 'mycashback_plugin_amount', true ),
            'label'             => 'Cashback amount (%)',
            'description'       => 'Set the amount of cashback you would like to give to your customer.'
        ) );

        woocommerce_wp_text_input( array(
            'id'                => 'mycashback_plugin_start_date',
            'value'             => get_post_meta( get_the_ID(), 'mycashback_plugin_start_date', true ),
            'label'             => 'Campaign Start Date',
            'type'              => 'date',
            'description'       => 'Set the date when the campaign starts.'
        ) );

        woocommerce_wp_text_input( array(
            'id'                => 'mycashback_plugin_end_date',
            'value'             => get_post_meta( get_the_ID(), 'mycashback_plugin_end_date', true ),
            'label'             => 'Campaign End Date',
            'type'              => 'date',
            'description'       => 'Set the date when the campaign ends.'
        ) );
    
        echo '</div>';
    }

    function mycashback_save_meta_fields( $id, $post ){
        update_post_meta( $id, 'mycashback_product_enable', $_POST['mycashback_product_enable']);
        update_post_meta( $id, 'mycashback_plugin_campaign_name', $_POST['mycashback_plugin_campaign_name']);
        update_post_meta( $id, 'mycashback_plugin_campaign_desc', $_POST['mycashback_plugin_campaign_desc']);
        update_post_meta( $id, 'mycashback_plugin_amount', $_POST['mycashback_plugin_amount']);
        update_post_meta( $id, 'mycashback_plugin_start_date', $_POST['mycashback_plugin_start_date']);
        update_post_meta( $id, 'mycashback_plugin_end_date', $_POST['mycashback_plugin_end_date']);
    }

    function register_mycashback_fields () {
        add_action( 'woocommerce_process_product_meta', array( $this, 'mycashback_save_meta_fields'), 10, 2);
    }

    function display_cashback_field() {
        add_action( 'woocommerce_after_add_to_cart_button', array( $this,'create_cashback_field') );
    }

    function cb_click_woocommerce_add_to_cart () {
        add_filter( 'woocommerce_add_to_cart_validation', array( $this,'cb_woocommerce_add_to_cart_validation'), 10, 5 );
    }

    function cb_before_checkout_create_order() {
        add_action('woocommerce_checkout_create_order', array( $this,'cb_add_metadata_to_order'), 10, 2);
    }

    function cb_woocommerce_add_to_cart_validation( $passed, $product_id, $quantity, $variation_id = null, $variations = null ) {
        $product = wc_get_product( $product_id );
        $cashback_status = $product->get_meta( 'mycashback_product_enable' );
        $cashback_amount = $product->get_meta( 'mycashback_plugin_amount' );
        if( $cashback_status == 'yes' && $cashback_amount ) {
            $mycashbackbttPlugin = new myCashbackbttPlugin();
            $campaignid = $product->get_meta( 'mycashback_plugin_campaign' );
            $url = 'https://adms-dev.mycashback.io/api/tracking/cbb/'.$campaignid.'/campaignId';
            $response = wp_remote_post( $url, array(
                'method' => 'GET',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array('Authorization' => $token),
                'body' => array(),
                'cookies' => array()  
            ));
            $clickObj = new stdClass();
            $clickObj->pid = $product_id;
            $clickObj->trackingid = json_decode($response['body'])->data->trackingId;
            $cookie_name = "cbb_cart_temp";
            $cookie_cart = [];
            $cookie_cart[] = $clickObj;
            setcookie($cookie_name, json_encode($cookie_cart), time()+3600, "/");
        }
        return $passed;
    }

    function create_cashback_field() {
        global $post;
        // Check for the custom field value
        $product = wc_get_product( $post->ID );
        $cashback_status = $product->get_meta( 'mycashback_product_enable' );
        $cashback_amount = $product->get_meta( 'mycashback_plugin_amount' );
        $cookie_name = "myCashback";
        if(isset($_COOKIE[$cookie_name])) {
            if( $cashback_status == 'yes' && $cashback_amount  ) {
                // Only display our field if we've got a value for the field amount and status is yes
                printf('<div class="mycashbackbtt button">Cashback: %s%%</div>', esc_html( $cashback_amount ));
            }
        }
    }

    function wc_create_or_update_product($post_id, $post, $update){
        if ($post->post_status != 'publish' || $post->post_type != 'product') {
            return;
        }
    
        if (!$product = wc_get_product( $post )) {
            return;
        }
    }

    function add_product_campagin($post_id, $post, $offerid, $token){
        $mycashbackbttPlugin = new myCashbackbttPlugin();
        $product = wc_get_product( $post );
        $url = 'https://adms-dev.mycashback.io/api/campaign/'.$offerid;
        $startdate = $product->get_meta( 'mycashback_plugin_start_date' ).'T04:58:26.776Z';
        $expiredate = $product->get_meta( 'mycashback_plugin_end_date' ).'T04:58:26.776Z';
        $response = wp_remote_post( $url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array('Authorization' => $token),
            'body' => array( 'name' => $product->get_meta( 'mycashback_plugin_campaign_name' ), 'url' => get_permalink( $post_id ), 'description' => $product->get_meta( 'mycashback_plugin_campaign_desc' ), 'startDate' => $startdate, 'expiryDate' => $expiredate),
            'cookies' => array()  
        ));
        update_post_meta( $post_id, 'mycashback_plugin_campaign', json_decode($response['body'])->data->data->id);
        return;
    }

    function update_existing_offer($post_id, $post, $update, $token){
        $mycashbackbttPlugin = new myCashbackbttPlugin();
        //Product updated and published
        $product = wc_get_product( $post );
        $product_name = $product->get_name().' - '.$post_id;
        $offerid = $product->get_meta( 'mycashback_plugin_offer' );
        $url = 'https://adms-dev.mycashback.io/api/offer/'.$offerid;
        $response = wp_remote_post( $url, array(
            'method' => 'PATCH',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array('Authorization' => $token),
            'body' => array( 'name' => $product_name, 'logo' => $product->get_image('','src'), 'categories' => $product->get_categories(), 'payoutTypes' => ['cps'], 'platforms' => ['web'], "countries" => ["thailand"], "description" => "This is a new offer test.", "url" => get_permalink( $post_id )),
            'cookies' => array()  
        ));
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            return $error_message;
        } else {
            return $response;
        }
    }

    function update_existing_campaign($post_id, $post, $update, $token){
        $mycashbackbttPlugin = new myCashbackbttPlugin();
        //Product updated and published
        $product = wc_get_product( $post );
        $campaignid = $product->get_meta( 'mycashback_plugin_campaign' );
        $url = 'https://adms-dev.mycashback.io/api/campaign/'.$campaignid;
        $startdate = $product->get_meta( 'mycashback_plugin_start_date' ).'T04:58:26.776Z';
        $expiredate = $product->get_meta( 'mycashback_plugin_end_date' ).'T04:58:26.776Z';
        $response = wp_remote_post( $url, array(
            'method' => 'PATCH',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array('Authorization' => $token),
            'body' => array( 'name' => $product->get_meta( 'mycashback_plugin_campaign_name' ), 'url' => get_permalink( $post_id ), 'description' => $product->get_meta( 'mycashback_plugin_campaign_desc' ), 'startDate' => $startdate, 'expiryDate' => $expiredate),
            'cookies' => array()  
        ));
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            return $error_message;
        } else {
            return $response;
        }
    }

    function create_default_campaign($token){
        $mycashbackbttPlugin = new myCashbackbttPlugin();
        $url = 'https://adms-dev.mycashback.io/api/campaign';
        $response = wp_remote_post( $url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array('Authorization' => $token),
            'body' => array(),
            'cookies' => array()  
        ));
    }

    function cb_add_metadata_to_order ( $order, $data ) {
        $cookie_name = "myCashback";
        if(isset($_COOKIE[$cookie_name])) {
            $order->update_meta_data( '_cbb_clickid', $_COOKIE[$cookie_name] );
            $order->save();
            unset($_COOKIE[$cookie_name]);
        }
    }

    function so_checkout_complete($order_id) {
        $order = wc_get_order( $order_id );
        $clickid = $order->get_meta('_cbb_clickid');
        $billingEmail = $order->billing_email;
        $products = $order->get_items();
        $mycashbackbttPlugin = new myCashbackbttPlugin();
        $tokenWP = $mycashbackbttPlugin->get_cbb_key('wc_settings_mycashback_secret');
        $token = $tokenWP[0]->option_value;
        foreach($products as $prod){
            $id = $prod['product_id'];
            $_product = wc_get_product( $id );
            $cashback_status = $_product->get_meta( 'mycashback_product_enable' );
            $cashback_amount = $_product->get_meta( 'mycashback_plugin_amount' );
            if( $cashback_status == 'yes' && $cashback_amount && $clickid) {
                $items[$prod['product_id']] = $prod['name'];
                $tokenWP = $mycashbackbttPlugin->get_cbb_key('wc_settings_mycashback_secret');
                $token = $tokenWP[0]->option_value;
                $product_id = $prod['product_id'];
                $categories = $_product->get_categories();
                $pcategory = get_the_terms($product_id,'product_cat');
                $category_name = '';
                foreach( $pcategory as $category ) {
                    $category_name .= ",".$category->name;
                } 
                $category_name = substr($category_name, 1);
                $quanity = $prod['qty'];
                $net_price   = $_product->get_price();
                date_default_timezone_set("Asia/Bangkok");
                $cdate = $_product->get_date_created();
                $checkoutdate = date_format(date_create($cdate), 'c');
                $order_item = array(
                    (object) [
                      'orderId' => $order_id,
                      'productTitle' => $prod['name'],
                      'category' => $category_name,
                      'quantity' => $quanity,
                      'commission' => $cashback_amount,
                      'netPrice' => $net_price,
                      'conversionDate' => $checkoutdate
                    ]
                );
                $url = 'https://aams.mycashback.io/api/postback';
                $body = array(
                    'clickId' => $clickid,
                    'status' => "PENDING",
                    'orders' => $order_item
                );
                $response = wp_remote_post( $url, array(
                    'method' => 'POST',
                    'timeout' => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => array('aams' => $token),
                    'headers' => array(
                        'aams' => $token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ),
                    'body' => json_encode($body),
                    'cookies' => array()  
                ));
            }
        }
    }

    function so_payment_complete($order_id) {
        $order = wc_get_order( $order_id );
        $clickid = $order->get_meta('_cbb_clickid');
        $billingEmail = $order->billing_email;
        $products = $order->get_items();
        $mycashbackbttPlugin = new myCashbackbttPlugin();
        $tokenWP = $mycashbackbttPlugin->get_cbb_key('wc_settings_mycashback_secret');
        $token = $tokenWP[0]->option_value;
        foreach($products as $prod){
            $id = $prod['product_id'];
            $_product = wc_get_product( $id );
            $cashback_status = $_product->get_meta( 'mycashback_product_enable' );
            $cashback_amount = $_product->get_meta( 'mycashback_plugin_amount' );
            if( $cashback_status == 'yes' && $cashback_amount && $clickid) {
                $items[$prod['product_id']] = $prod['name'];
                $tokenWP = $mycashbackbttPlugin->get_cbb_key('wc_settings_mycashback_secret');
                $token = $tokenWP[0]->option_value;
                $product_id = $prod['product_id'];
                $categories = $_product->get_categories();
                $pcategory = get_the_terms($product_id,'product_cat');
                $category_name = '';
                foreach( $pcategory as $category ) {
                    $category_name .= ",".$category->name;
                } 
                $category_name = substr($category_name, 1);
                $quanity = $prod['qty'];
                $net_price   = $_product->get_price();
                $order_item = array(
                    (object) []
                );
                date_default_timezone_set("Asia/Bangkok");
                $cdate = $_product->get_date_created();
                $checkoutdate = date_format(date_create($cdate), 'c');
                $url = 'https://aams.mycashback.io/api/postback';
                $body = array(
                    'clickId' => $clickid,
                    'status' => "APPROVED",
                    'orders' => $order_item
                );
                $response = wp_remote_post( $url, array(
                    'method' => 'POST',
                    'timeout' => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => array('aams' => $token),
                    'headers' => array(
                        'aams' => $token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ),
                    'body' => json_encode($body),
                    'cookies' => array()  
                ));
            }
        }
    }

    function so_order_rejected($order_id) {
        $order = wc_get_order( $order_id );
        $clickid = $order->get_meta('_cbb_clickid');
        $billingEmail = $order->billing_email;
        $products = $order->get_items();
        $mycashbackbttPlugin = new myCashbackbttPlugin();
        $tokenWP = $mycashbackbttPlugin->get_cbb_key('wc_settings_mycashback_secret');
        $token = $tokenWP[0]->option_value;
        foreach($products as $prod){
            $id = $prod['product_id'];
            $_product = wc_get_product( $id );
            $cashback_status = $_product->get_meta( 'mycashback_product_enable' );
            $cashback_amount = $_product->get_meta( 'mycashback_plugin_amount' );
            if( $cashback_status == 'yes' && $cashback_amount && $clickid) {
                $items[$prod['product_id']] = $prod['name'];
                $tokenWP = $mycashbackbttPlugin->get_cbb_key('wc_settings_mycashback_secret');
                $token = $tokenWP[0]->option_value;
                $product_id = $prod['product_id'];
                $categories = $_product->get_categories();
                $pcategory = get_the_terms($product_id,'product_cat');
                $category_name = '';
                foreach( $pcategory as $category ) {
                    $category_name .= ",".$category->name;
                } 
                $category_name = substr($category_name, 1);
                $quanity = $prod['qty'];
                $net_price   = $_product->get_price();
                $order_item = array(
                    (object) []
                );
                $url = 'https://aams.mycashback.io/api/postback';
                $body = array(
                    'clickId' => $clickid,
                    'status' => "REJECTED",
                    'orders' => $order_item
                );
                $response = wp_remote_post( $url, array(
                    'method' => 'POST',
                    'timeout' => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => array('aams' => $token),
                    'headers' => array(
                        'aams' => $token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ),
                    'body' => json_encode($body),
                    'cookies' => array()  
                ));
            }
        }
    }

    function so_order_failed($order_id){
        $order = wc_get_order( $order_id );
        $clickid = $order->get_meta('_cbb_clickid');
        $billingEmail = $order->billing_email;
        $products = $order->get_items();
        $mycashbackbttPlugin = new myCashbackbttPlugin();
        $tokenWP = $mycashbackbttPlugin->get_cbb_key('wc_settings_mycashback_secret');
        $token = $tokenWP[0]->option_value;
        foreach($products as $prod){
            $id = $prod['product_id'];
            $_product = wc_get_product( $id );
            $cashback_status = $_product->get_meta( 'mycashback_product_enable' );
            $cashback_amount = $_product->get_meta( 'mycashback_plugin_amount' );
            if( $cashback_status == 'yes' && $cashback_amount && $clickid) {
                $items[$prod['product_id']] = $prod['name'];
                $tokenWP = $mycashbackbttPlugin->get_cbb_key('wc_settings_mycashback_secret');
                $token = $tokenWP[0]->option_value;
                $product_id = $prod['product_id'];
                $categories = $_product->get_categories();
                $pcategory = get_the_terms($product_id,'product_cat');
                $category_name = '';
                foreach( $pcategory as $category ) {
                    $category_name .= ",".$category->name;
                } 
                $category_name = substr($category_name, 1);
                $quanity = $prod['qty'];
                $net_price   = $_product->get_price();
                $order_item = array(
                    (object) []
                );
                $url = 'https://aams.mycashback.io/api/postback';
                $body = array(
                    'clickId' => $clickid,
                    'status' => "REJECTED",
                    'orders' => $order_item
                );
                $response = wp_remote_post( $url, array(
                    'method' => 'POST',
                    'timeout' => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => array('aams' => $token),
                    'headers' => array(
                        'aams' => $token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ),
                    'body' => json_encode($body),
                    'cookies' => array()  
                ));
            }
        }
    }

    function so_order_refunded($order_id){
        $order = wc_get_order( $order_id );
        $clickid = $order->get_meta('_cbb_clickid');
        $billingEmail = $order->billing_email;
        $products = $order->get_items();
        $mycashbackbttPlugin = new myCashbackbttPlugin();
        $tokenWP = $mycashbackbttPlugin->get_cbb_key('wc_settings_mycashback_secret');
        $token = $tokenWP[0]->option_value;
        foreach($products as $prod){
            $id = $prod['product_id'];
            $_product = wc_get_product( $id );
            $cashback_status = $_product->get_meta( 'mycashback_product_enable' );
            $cashback_amount = $_product->get_meta( 'mycashback_plugin_amount' );
            if( $cashback_status == 'yes' && $cashback_amount && $clickid) {
                $items[$prod['product_id']] = $prod['name'];
                $tokenWP = $mycashbackbttPlugin->get_cbb_key('wc_settings_mycashback_secret');
                $token = $tokenWP[0]->option_value;
                $product_id = $prod['product_id'];
                $categories = $_product->get_categories();
                $pcategory = get_the_terms($product_id,'product_cat');
                $category_name = '';
                foreach( $pcategory as $category ) {
                    $category_name .= ",".$category->name;
                } 
                $category_name = substr($category_name, 1);
                $quanity = $prod['qty'];
                $net_price   = $_product->get_price();
                $order_item = array(
                    (object) []
                );
                $url = 'https://aams.mycashback.io/api/postback';
                $body = array(
                    'clickId' => $clickid,
                    'status' => "REJECTED",
                    'orders' => $order_item
                );
                $response = wp_remote_post( $url, array(
                    'method' => 'POST',
                    'timeout' => 45,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking' => true,
                    'headers' => array('aams' => $token),
                    'headers' => array(
                        'aams' => $token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ),
                    'body' => json_encode($body),
                    'cookies' => array()  
                ));
            }
        }
    }

    function enqueue() {
        wp_enqueue_style( 'mycashbackstyle' , plugins_url( '/assets/mycashbackstyle.css', __FILE__ ) );
        wp_enqueue_script('jquery');
        wp_enqueue_script( 'mycashbackscript' , plugins_url( '/assets/mycashbackscript.js', __FILE__ ) );
    }
}

if ( class_exists( 'myCashbackbttPlugin' ) ) {
    $mycashbackbttPlugin = new myCashbackbttPlugin();
    $mycashbackbttPlugin->initSettingMenu();
    $mycashbackbttPlugin->initAPICheck();
    $mycashbackbttPlugin->initCookie();
    $mycashbackbttPlugin->registerAssets();
    $mycashbackbttPlugin->registerProductTab();
    $mycashbackbttPlugin->mycashback_icon();
    $mycashbackbttPlugin->registerProductPanel();
    $mycashbackbttPlugin->register_mycashback_fields();
    $mycashbackbttPlugin->display_cashback_field();
    $mycashbackbttPlugin->product_update_complete();
    $mycashbackbttPlugin->cb_before_checkout_create_order();
    $mycashbackbttPlugin->order_checkout_complete();
    $mycashbackbttPlugin->order_payment_complete();
    $mycashbackbttPlugin->order_status_rejected();
    $mycashbackbttPlugin->order_status_failed();
    $mycashbackbttPlugin->order_status_refunded();
}

//activation
register_activation_hook( __FILE__, array( $mycashbackbttPlugin, 'activate' ) );

//deactivation
register_deactivation_hook( __FILE__, array( $mycashbackbttPlugin, 'deactivate' ) );

//uninstall
//register_uninstall_hook( __FILE__, array( $mycashbackbttPlugin, 'uninstall' ) );