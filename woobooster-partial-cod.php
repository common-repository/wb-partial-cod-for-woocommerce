<?php
/*
Plugin Name: WooBooster Partial COD for WooCommerce
Description: Allows partial payment via Cash on Delivery (COD) in WooCommerce.
* Contributors: pradeepku041, wooboostercom
*  Author: WooBooster
*  Author URI: https://www.woobooster.com/contact-us/
* Tested up to: 6.6.1
* Stable tag: 1.1
* Version: 1.1
* Text Domain: cvwb-partial-cod
* Copyright: (c) 2023-2024 WooBooster.com.
* License: GPL v2 or later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_action( 'wp_enqueue_scripts', 'woobooster_enqueue_scripts' );
function woobooster_enqueue_scripts() {
    // Enqueue your script
    wp_enqueue_script( 'woobooster-partial-cod-script', plugins_url( '/js/partial-cod.js', __FILE__ ), array('jquery'), '1.0', true );

    // Localize the script with custom URL
    wp_localize_script( 'woobooster-partial-cod-script', 'partial_cod_params', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ), // Example URL, replace with your custom URL
        'nonce'    => wp_create_nonce( 'partial-payment-nonce' ),
    ) );
    
}
// Add custom settings to WooCommerce settings page
add_filter( 'woocommerce_settings_tabs_array', 'woobooster_partial_cod_add_settings_tab', 50 );
function woobooster_partial_cod_add_settings_tab( $tabs ) {
    $tabs['partial_cod'] = __( 'Partial COD', 'cvwb-partial-cod' );
    return $tabs;
}

add_action( 'woocommerce_settings_tabs_partial_cod', 'woobooster_partial_cod_settings_tab' );
function woobooster_partial_cod_settings_tab() {
    woocommerce_admin_fields( woobooster_partial_cod_settings_fields() );
}

add_action( 'woocommerce_update_options_partial_cod', 'woobooster_partial_cod_update_settings' );
function woobooster_partial_cod_update_settings() {
    woocommerce_update_options( woobooster_partial_cod_settings_fields() );
}

function woobooster_partial_cod_add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=partial_cod">' . __('Settings', 'cvwb-partial-cod') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'woobooster_partial_cod_add_settings_link');

function woobooster_partial_cod_settings_fields() {
    $settings = array(
        'section_title' => array(
            'name'     => __( 'Partial COD Settings', 'cvwb-partial-cod' ),
            'type'     => 'title',
            'desc'     => '',
            'id'       => 'partial_cod_section_title'
        ),
        'partial_cod_enabled' => array(
            'name'     => __( 'Enable Partial COD', 'cvwb-partial-cod' ),
            'type'     => 'checkbox',
            'desc'     => __( 'Enable partial COD payment', 'cvwb-partial-cod' ),
            'id'       => 'woobooster_partial_cod_enabled',
            'default'  => 'no'
        ),
        'partial_title' => array(
            'name'     => __( 'Partial COD Title Text', 'cvwb-partial-cod' ),
            'type'     => 'text',
            'desc'     => __( 'Enter Partial COD Title Text', 'cvwb-partial-cod' ),
            'id'       => 'woobooster_partial_cod_title_text',
            'default'  => 'Cash On Delivery'
        ),
        'partial_amount' => array(
            'name'     => __( 'Partial Amount', 'cvwb-partial-cod' ),
            'type'     => 'number',
            'desc'     => __( 'Enter the partial amount', 'cvwb-partial-cod' ),
            'id'       => 'woobooster_partial_cod_amount',
            'default'  => '50'
        ),
        
        'partial_amount_type' => array(
            'name'     => __( 'Partial Amount Type', 'cvwb-partial-cod' ),
            'type'     => 'select',
            'desc'     => __( 'Select the type of partial amount', 'cvwb-partial-cod' ),
            'id'       => 'woobooster_partial_cod_amount_type',
            'options'  => array(
                'fixed' => __( 'Fixed Amount', 'cvwb-partial-cod' ),
                'percentage' => __( 'Percentage', 'cvwb-partial-cod' )
            ),
            'default' => 'fixed'
        ),
        'section_end' => array(
            'type'     => 'sectionend',
            'id'       => 'partial_cod_section_end'
        )
    );

    return apply_filters( 'woocommerce_partial_cod_settings_fields', $settings );
}

// Display partial payment checkbox before the Checkout button
add_action( 'woocommerce_review_order_before_payment', 'woobooster_partial_cod_display_checkbox' );
function woobooster_partial_cod_display_checkbox() {
    if( is_checkout()) {
    $partial_cod_enabled = get_option( 'woobooster_partial_cod_enabled', 'no' );
    $partial_cod_title_text = get_option( 'woobooster_partial_cod_title_text', 'Partial Cash On Delivey' );
    if ( 'yes' === $partial_cod_enabled ) {
        $partial_payment = WC()->session->get('partial_payment');
        $checkoption="";
        if ($partial_payment === '1') {
            $checkoption="checked";
        }
        ?>
        <div id="partial-payment-checkbox">
            <label for="partial_payment">
                <input type="checkbox" class="input-checkbox" name="partial_payment" id="partial_payment" value="1" <?php echo $checkoption;?>/>
                <?php  echo sprintf(esc_html__('%s', 'cvwb-partial-cod'), esc_html($partial_cod_title_text)); ?>

            </label>
        </div>
        <?php
    }
}
}

// Remove the COD gateway option if partial payment is selected
add_filter( 'woocommerce_available_payment_gateways', 'woobooster_partial_cod_remove_cod_gateway' );
function woobooster_partial_cod_remove_cod_gateway( $gateways ) {
    $partial_cod_enabled = get_option( 'woobooster_partial_cod_enabled', 'no' );

    if ( 'yes' === $partial_cod_enabled ) {
        unset( $gateways['cod'] );
    }
    return $gateways;
}



// Update order meta with partial amount and type
add_action( 'woocommerce_checkout_create_order', 'woobooster_partial_cod_checkout_create_order', 10, 2 );
function woobooster_partial_cod_checkout_create_order( $order, $data ) {
    $partial_cod_enabled = get_option( 'woobooster_partial_cod_enabled', 'no' );
    if ( 'yes' === $partial_cod_enabled ) {
        $payment_type = "";
        $order->update_meta_data( '_partial_payment', $payment_type );
        $partial_payment = WC()->session->get('partial_payment');

        if (isset($partial_payment) && $partial_payment == '1') {

           $partial_total_amount = WC()->session->get('partial_total_amount');
           $partial_rest_cod = WC()->session->get('partial_rest_cod');

            $payment_type = "yes";
            $order->update_meta_data( '_partial_payment', $payment_type );
             
            $partial_amount = $order->get_total();

            $order->update_meta_data( 'partial_amount', get_option( 'woobooster_partial_cod_amount', 0 ) );
            $order->update_meta_data( 'partial_amount_type', get_option( 'woobooster_partial_cod_amount_type', 'fixed' ) );

            $order->update_meta_data( 'partial_cod_total_amount', $partial_total_amount );
            $order->update_meta_data( 'partial_cod_paid_amount', $partial_amount );
            $order->update_meta_data( 'partial_cod_pending_amount', $partial_rest_cod );

// Add order note
            $note = sprintf(
                'Partial Cash on Delivery (COD) applied. Total amount: %s, Paid amount: %s, Remaining amount to pay in COD: %s',
                wc_price($partial_total_amount),
                wc_price($partial_amount),
                wc_price($partial_rest_cod)
            );
            $order->add_order_note( $note );
            
WC()->session->__unset( 'partial_payment' );
WC()->session->__unset( 'partial_total_amount' );
WC()->session->__unset( 'partial_rest_cod' );


        }
    }
}

function woobooster_partial_cod_calculate_partial_amount( $total ) {
    $amount_type = get_option( 'woobooster_partial_cod_amount_type', 'fixed' );
    $partial_amount = 0;

    if ( 'fixed' === $amount_type ) {

        $partial_amount = get_option( 'woobooster_partial_cod_amount', 0 );
    } elseif ( 'percentage' === $amount_type ) {
        $percentage = get_option( 'woobooster_partial_cod_amount', 0 );
        $partial_amount = ( $total *($percentage/100)) ;
    }

    return $partial_amount;
}


add_action('woocommerce_cart_calculate_fees', 'woobooster_partial_cod_calculate_partial_payment_fee');
if(!function_exists('woobooster_partial_cod_calculate_partial_payment_fee'))
{
function woobooster_partial_cod_calculate_partial_payment_fee($cart) {
global $woocommerce;

if(is_checkout())
{  

    if ( is_admin() && ! defined('DOING_AJAX') || ! is_checkout() )
        return;
    $partial_payment = WC()->session->get('partial_payment');
    
    if ($partial_payment === '1') {
       
        $nongstTotal=$woocommerce->cart->get_subtotal() + $woocommerce->cart->get_shipping_total();
        $totalGSTAmt=$woocommerce->cart->get_subtotal_tax()+$woocommerce->cart->get_shipping_tax();
       $cart_total = $nongstTotal + $totalGSTAmt;
                $cart_total  = (float) preg_replace( '/[^.\d]/', '', $cart_total  );

                   $partial_amount = woobooster_partial_cod_calculate_partial_amount( $nongstTotal );
            
          if($nongstTotal>$partial_amount){
            $remaining_amount = $nongstTotal - $partial_amount;
        }else{
            $remaining_amount=0;
        }

         $totalTaxPer=round((($totalGSTAmt/$nongstTotal)*100),2);

         $toalPartialAmtWithTax=round($partial_amount+($partial_amount*($totalTaxPer/100)),2);

        $totalPendingCODAmt=$cart_total-$toalPartialAmtWithTax;
        

 // Concatenate total order amount and remaining amount to pay in COD into a single string
       // $display_text = sprintf('Your Total Order Amount: %s, Remaining Amount to Pay in COD: %s', wp_strip_all_tags(wc_price($cart_total)), wp_strip_all_tags(wc_price($totalPendingCODAmt)));
       
       $display_text = "Partial COD Discount";

            // Add fee for remaining COD amount
        if($remaining_amount>0){
            WC()->cart->add_fee($display_text, -$remaining_amount );
            WC()->session->set('partial_total_amount', $cart_total);
            WC()->session->set('partial_payable_amount', $partial_amount);
            WC()->session->set('partial_rest_cod', $totalPendingCODAmt);
        }
    }
}
}
}




// Display custom partial COD information after the order total
add_action('woocommerce_review_order_after_order_total', 'woobooster_display_partial_cod_info_after_total');
function woobooster_display_partial_cod_info_after_total() {
    $partial_payment = WC()->session->get('partial_payment');
    if ($partial_payment === '1') {
        $cart_total = WC()->session->get('partial_total_amount');
        $totalPendingCODAmt = WC()->session->get('partial_rest_cod');
        if ($cart_total && $totalPendingCODAmt) {
            echo '<tr class="partial-cod-info" style="background: #f6f6f6;">
                <th>' . __('Your Total Order Amount', 'cvwb-partial-cod') . '</th>
                <td>' . wc_price($cart_total) . '</td>
            </tr>';
            
            echo '<tr class="partial-cod-info" style="background: #f6f6f6;">
                <th>' . __('Remaining Amount to Pay in COD', 'cvwb-partial-cod') . '</th>
                <td>' . wc_price($totalPendingCODAmt) . '</td>
            </tr>';
        }
    }
}

// Display in order emails
add_action('woocommerce_email_after_order_table', 'woobooster_partial_cod_display_in_emails', 10, 4);
function woobooster_partial_cod_display_in_emails($order, $sent_to_admin, $plain_text, $email) {
    $is_partial = $order->get_meta('_partial_payment');
    if ( $is_partial === 'yes' ) {
        $partial_order_amount = $order->get_meta('partial_cod_total_amount');
        $partial_cod_pending_amount = $order->get_meta('partial_cod_pending_amount');
        echo '<h2>' . __('Partial COD Information', 'cvwb-partial-cod') . '</h2>';
        echo '<p>' . __('Total Order Amount: ', 'cvwb-partial-cod') . wc_price($partial_order_amount) . '</p>';
         echo '<p>' . __('Partial Paid Amount: ', 'cvwb-partial-cod') . wc_price(($partial_order_amount-$partial_cod_pending_amount)) . '</p>';
        echo '<p>' . __('Remaining Amount to Pay in COD: ', 'cvwb-partial-cod') . wc_price($partial_cod_pending_amount) . '</p>';
    }
}

// Display in customer order details page
add_action('woocommerce_order_details_after_order_table', 'woobooster_partial_cod_display_in_order_details');
function woobooster_partial_cod_display_in_order_details($order) {
    $is_partial = $order->get_meta('_partial_payment');
    if ( $is_partial === 'yes' ) {
        $partial_order_amount = $order->get_meta('partial_cod_total_amount');
        $partial_cod_pending_amount = $order->get_meta('partial_cod_pending_amount');
        echo '<h2>' . __('Partial COD Information', 'cvwb-partial-cod') . '</h2>';
        echo '<p>' . __('Total Order Amount: ', 'cvwb-partial-cod') . wc_price($partial_order_amount) . '</p>';
         echo '<p>' . __('Partial Paid Amount: ', 'cvwb-partial-cod') . wc_price(($partial_order_amount-$partial_cod_pending_amount)) . '</p>';
        echo '<p>' . __('Remaining Amount to Pay in COD: ', 'cvwb-partial-cod') . wc_price($partial_cod_pending_amount) . '</p>';
    }
}

add_action('wp_ajax_update_partial_payment', 'woobooster_partial_cod_update_partial_payment_callback');
add_action('wp_ajax_nopriv_update_partial_payment', 'woobooster_partial_cod_update_partial_payment_callback');
function woobooster_partial_cod_update_partial_payment_callback() {
    if ( isset( $_POST['partial_payment'] ) && isset( $_POST['security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'partial-payment-nonce' ) ) {
        $partial_payment = sanitize_text_field($_POST['partial_payment']);
        WC()->session->set('partial_payment', $partial_payment); // Store partial payment status in session

        echo wp_json_encode(array('success' => true));
    } else {
        echo wp_json_encode(array('success' => false));
    }
    exit;
}






function woobooster_partial_cod_add_order_profit_column_header($columns)
{
    $new_columns = array();
    foreach ($columns as $column_name => $column_info) {
        $new_columns[$column_name] = $column_info;
        if ('order_total' === $column_name) {
            $new_columns['partial_cod'] = __('Partial COD', 'cvwb-partial-cod');
        }
    }
    return $new_columns;
}
add_filter('manage_edit-shop_order_columns', 'woobooster_partial_cod_add_order_profit_column_header');


function woobooster_partial_cod_add_order_profit_column_content( $column ) {
    global $post;
    if ( 'partial_cod' === $column ) {


        $order    = wc_get_order( $post->ID );
      $total    = (float) $order->get_total();
       $is_partial = get_post_meta( $post->ID , '_partial_payment', true );
        if ( $is_partial === 'yes' ) {
            // Retrieve the partial order amount
            $partial_order_amount = get_post_meta( $post->ID , 'partial_cod_total_amount', true );
             $partial_cod_pending_amount = get_post_meta( $post->ID , 'partial_cod_pending_amount', true );

        echo "Total Amount: ".wc_price($partial_order_amount)."<br> <span style='color:green'>Paid COD Amt: ".wc_price($total )."</span><br> <span style='color:red'>Pending COD Amt: ".wc_price($partial_cod_pending_amount)."</span>";
    }
}
}
add_action( 'manage_shop_order_posts_custom_column', 'woobooster_partial_cod_add_order_profit_column_content' );





add_action( 'admin_menu', 'woobooster_partial_cod_register_partial_payment_menu' );
function woobooster_partial_cod_register_partial_payment_menu() {
    $menu_slug =add_menu_page(
        'Partial Payment Orders',
        'View Payments',
        'manage_options',
        'partial-payments',
        'woobooster_partial_cod_partial_payment_orders_page',
         'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" version="1.0" width="20" height="20" viewBox="0 0 633.000000 628.000000" preserveAspectRatio="xMidYMid meet">

<g transform="translate(0.000000,628.000000) scale(0.100000,-0.100000)" fill="#fff" stroke="none">
<path d="M1900 6083 c-78 -6 -199 -56 -243 -101 -51 -53 -102 -141 -117 -204 -15 -59 -14 -83 20 -2228 10 -679 21 -1325 24 -1435 3 -110 6 -261 6 -336 l0 -136 32 -49 c60 -90 115 -136 208 -172 47 -18 95 -32 107 -32 11 0 77 17 145 39 134 42 353 158 491 259 40 29 144 97 233 150 89 53 200 123 249 155 48 32 130 80 183 108 110 56 237 130 299 174 23 17 72 48 108 70 94 57 197 125 292 192 45 32 109 77 142 100 77 55 123 112 161 201 28 66 31 83 33 194 2 148 -6 171 -90 255 -50 49 -88 72 -244 150 -102 51 -244 113 -315 138 -186 67 -278 103 -374 145 -47 21 -119 52 -160 69 -41 18 -88 38 -105 46 -16 8 -77 35 -135 60 -245 108 -389 195 -434 261 -52 76 -60 122 -52 296 4 87 2 423 -4 746 l-11 587 -26 68 c-29 75 -79 139 -126 164 -41 21 -168 61 -209 66 -18 2 -58 2 -88 0z"/>
<path d="M894 5890 c-125 -13 -192 -53 -257 -155 -54 -85 -55 -91 -66 -1030 -6 -478 -18 -1428 -27 -2110 -18 -1413 -18 -1468 15 -1540 45 -102 132 -157 278 -176 76 -10 100 -9 173 5 47 10 114 29 150 43 91 37 461 277 484 315 9 14 -3 31 -65 95 -136 142 -225 270 -254 365 -42 140 -42 186 -20 1993 24 1997 24 1929 -4 2003 -34 89 -131 167 -230 183 -25 4 -57 9 -71 12 -14 3 -62 1 -106 -3z"/>
<path d="M5630 3398 c-36 -13 -94 -43 -130 -68 -67 -45 -651 -447 -1445 -994 -247 -170 -659 -453 -914 -629 -652 -448 -746 -503 -897 -517 -45 -4 -122 -4 -171 1 -92 8 -258 35 -274 45 -24 15 -32 -58 -30 -291 2 -260 11 -323 60 -445 73 -178 212 -296 346 -294 82 2 146 30 289 128 67 45 432 296 811 557 380 262 1140 785 1690 1163 550 379 1015 704 1033 725 18 20 48 65 65 101 27 56 32 76 32 140 0 68 -4 82 -40 149 -107 200 -266 285 -425 229z"/>
</g>
</svg>'),10
    );
    
    
    $submenu_slug = add_submenu_page(
        'partial-payments', // Parent slug
        'Partial COD Setting', // Page title
        'Settings', // Menu title
        'manage_options', // Capability
        'admin.php?page=wc-settings&tab=partial_cod' 
    );
     global $menu;
    
    foreach ( $menu as $key => $item ) {
        
        if ( $item[2] === "partial-payments" ) {
         
            $menu[$key][0] = 'Partial COD';
        }
    }
    
}

function woobooster_partial_cod_partial_payment_orders_page() {
$orderstatus=array();
$order_statuses = wc_get_order_statuses();

foreach ( $order_statuses as $status_key => $status_label ) {
    array_push($orderstatus, $status_key);
    
}

$args = array(
    'status' => $orderstatus,
    'limit'    => -1,
    'orderby'  => 'id',
    'order'    => 'DESC',
    'meta_query' => array(
            array(
                'key' => '_partial_payment',
                'value' => 'yes',
                'compare' => '='
            ),
)
);
$getorders = wc_get_orders( $args );



class woobooster_partial_cod_Data_List_Table extends WP_List_Table {
    private $data;

    public function __construct( $data ) {
        parent::__construct([
            'singular' => __( 'Partial COD', 'wb' ), // Singular name of the listed records
            'plural'   => __( 'Partial COD', 'wb' ), // Plural name of the listed records
            'ajax'     => false // Should this table support ajax?
        ]);

        $this->data = $data;
    }

    public function get_columns() {
        $columns = [
            'id'=> __( 'Order ID', 'wb' ),
            'orderlink'           => __( 'Customer Name', 'wb' ),
            'order_date'     => __( 'Order Date', 'wb' ),
            'order_status'     => __( 'Order Status', 'wb' ),
            'total_cod_amount' => __( 'Total Order Amount', 'wb' ),
            'total_paid_cod' => __( 'Paid COD Amount', 'wb' ),
            'pending_cod_amount' => __( 'Pending COD Amount', 'wb' )
        ];

        return $columns;
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = [];

        $this->_column_headers = [ $columns, $hidden, $sortable ];

        usort( $this->data, [ $this, 'usort_reorder' ] );

        $per_page     = 10;
        $current_page = $this->get_pagenum();
        $total_items  = count( $this->data );

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        $this->items = array_slice( $this->data, ( ( $current_page - 1 ) * $per_page ), $per_page );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
            case 'orderlink':
            case 'order_date':
            case 'order_status':
            case 'total_cod_amount':
            case 'total_paid_cod':
            case 'pending_cod_amount':
                return $item[ $column_name ];
            default:
                return print_r( $item, true );
        }
    }

    private function usort_reorder( $a, $b ) {
        $orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'id';
$order   = ( ! empty( $_REQUEST['order'] ) ) ? sanitize_text_field( $_REQUEST['order'] ) : 'desc';
        $result  = strcmp( $a[ $orderby ], $b[ $orderby ] );

        return ( $order === 'asc' ) ? $result : -$result;
    }
}
  
   
$data = [];
    if ( $getorders) {
        

       foreach ( $getorders as $order ) {
           $odata  = $order->get_data(); // The Order data

$order_id        = $odata['id'];
           
             $total    = (float) $order->get_total();
             $partial_order_amount = $order->get_meta('partial_cod_total_amount');
             $partial_cod_pending_amount = $order->get_meta('partial_cod_pending_amount');
              $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
              $order_date = $order->order_date;
              $order_status = $order->get_status();
              
              $data[] = [
                  'id'=> '<a href="admin.php?page=wc-orders&action=edit&id=186"'.$order_id .'">#'.$order_id.'</a>',
            'orderlink'  => '<a href="admin.php?page=wc-orders&action=edit&id=186"'.$order_id .'">' . esc_html( $customer_name ) . '</a>',
            'order_date'     => $order->get_date_created()->format ('M j, Y H:i'),
            'order_status'=>'<span>'.wc_get_order_status_name($order_status).'</span>',
            'total_cod_amount' => '<span style="color:black;font-weight:bold;">'.wc_price($partial_order_amount).'</span>',
            'total_paid_cod' => '<span style="color:green;font-weight:bold;">'.wc_price($total).'</span>',
            'pending_cod_amount' => '<span style="color:red;font-weight:bold;">'.wc_price($partial_cod_pending_amount).'</span>'
        ];
       }  
       
       

           
    } 
    
     $list_table = new woobooster_partial_cod_Data_List_Table($data);

    // Prepare the items
    $list_table->prepare_items();
    ?>
    <div class="wrap">
        <h1>Partial Cash On Delivery (COD) Payments</h1>
        <form method="post">
            <?php
    echo $list_table->display();
?>
 </form>
    </div>
    <?php
    wp_reset_postdata();
}
