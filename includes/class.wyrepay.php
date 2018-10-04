<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPWOO_Wyrepay_Plugin extends WC_Payment_Gateway {

    public $allowed_currencies=array('USD','GHS','GBP','EUR','ZAR');

    public function __construct(){
       
        $this->id 					= 'woo-wyrepay-plugin';
        $this->icon 				= apply_filters('wpwoo_wyrepay_icon', plugins_url( 'assets/pay-via-wyrepay.png' , WPWOO_WYREPAY_BASE ) );
        $this->has_fields 			= true;
        $this->order_button_text 	= 'Make Payment';
        $this->url 		         	= 'https://e-order.wyre.tech/sysapi/';
        $this->url_eorder 		    = 'https://e-order.wyre.tech/';
        $this->notify_url        	= WC()->api_request_url( 'WPWOO_Wyrepay_Plugin' );
        $this->method_title     	= 'Wyre';
        $this->method_description  	= 'Wyre provide services for to accept online payments from local and international customers using Mastercard, Visa, Verve Cards and other payment options';

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define user set variables
        $this->title 					= $this->get_option( 'title' );
        $this->description 				= $this->get_option( 'description' );
        $this->api_key 		            = $this->get_option( 'api_key' );
        $this->enabled            	    = $this->get_option( 'enabled' )=='yes'?true:false;
        $this->method                   = 'inline';

        // Check if the gateway can be used
        if ( ! $this->is_valid_for_use() ) {
            $this->enabled = false;
        }

        //Hooks
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

        // Payment listener/API hook
        add_action( 'woocommerce_api_wpwoo_wyrepay_plugin', array( $this, 'check_wyrepay_response' ) );
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields() {

        $form_fields = array(
            'enabled' => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable Wyre',
                'type'        => 'checkbox',
                'description' => 'Enable as a payment option on the checkout page.',
                'default'     => 'yes',
                'desc_tip'    => true
            ),
            'api_key' => array(
                'title' 		=> 'Wyre API Key',
                'type' 			=> 'text',
                'description' 	=> 'Enter Your Wyre API Key, this can be gotten on your settings page when you login on Wyre at https://my.wyre.tech' ,
                'default' 		=> '',
                'desc_tip'      => true
            ),
            'title' => array(
                'title' 		=> 'Title',
                'type' 			=> 'text',
                'description' 	=> 'Payment title on checkout page.',
                'desc_tip'      => true,
                'default' 		=> 'Wyre'
            ),
            'description' => array(
                'title' 		=> 'Description',
                'type' 			=> 'textarea',
                'description' 	=> 'Payment description on checkout page.',
                'desc_tip'      => true,
                'default' 		=> 'Make payment using Wyre'
            )
        );

        $this->form_fields = $form_fields;

    }

    public function is_valid_for_use() {

        if( ! in_array( get_woocommerce_currency(), $this->allowed_currencies ) ) {
            $this->msg = 'Wyrepay doesn\'t support your store currency ('.get_woocommerce_currency().'), you can update your currency <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=general">here</a>';
            return false;
        }


        return true;
    }

    /**
     * Check if this gateway is enabled
     */
    public function is_available() {

        if ( $this->enabled == "yes" ) {
            return true;
        }

        return false;
    }

    public function payment_scripts()
    {

        if (!is_checkout_pay_page() || !$this->enabled) {
            return;
        }

        wp_enqueue_script( 'jquery' );

        wp_enqueue_script( 'wpwoo_wyrepay', $this->url_eorder.'woo/js/wyre.js', array( 'jquery' ));
        wp_enqueue_script( 'wpwoo_wyrepay_inline', plugins_url( 'assets/woo-wyrepay.js', WPWOO_WYREPAY_BASE ), array( 'jquery', 'wpwoo_wyrepay' ));

    }

    /**
     * Get wyrepay args
     **/
    public function get_wyrepay_args( $order ) {

        $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
        $wyrepay_args = array(
            'api_key' 		        =>  $this->api_key,
            'cur' 					=> get_woocommerce_currency(),
            'desc'					=> "Payment for Order ID:: $order_id on ". get_bloginfo('name'),
            'merchant_ref'			=> $order_id.'-'.get_woocommerce_currency().'-'.$order->get_total(),
            'notify_url'			=> $this->notify_url,
            'shop_name'             => get_bloginfo('name'),
            'success_url'			=> $this->get_return_url( $order ),
            'fail_url'				=> $this->get_return_url( $order )
        );

        $first_name  	= method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name;
        $last_name  	= method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name;
        $wyrepay_args['name']=$first_name.' '.$last_name;
       
        $wyrepay_args['email']=method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;
        $wyrepay_args['phone']=method_exists( $order, 'get_billing_phone' ) ? $order->get_billing_phone() : $order->billing_phone;
        $billing_address 	= $order->get_formatted_billing_address();
        $billing_address 	= esc_html( preg_replace( '#<br\s*/?>#i', ', ', $billing_address ) );
        $address_split= explode(',',$billing_address);
        if(isset($address_split[1])) $wyrepay_args['address'] =$address_split[1];
        if(isset($address_split[2])) $wyrepay_args['city'] =$address_split[2];
        if(isset($address_split[3])) $wyrepay_args['state'] =$address_split[3];
        if(isset($address_split[4])) $wyrepay_args['zipcode'] =$address_split[4];
        
        $products = array();
		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				if ( ! $item['qty'] ) {
					continue;
				}
				$item_loop ++;
				$product   = $order->get_product_from_item( $item );
				$item_name = $item['name'];

				$products[] = array(
									'code' => $product->get_sku(),
				                    'name' => $item_name,
				                    'unit' => $product->get_price(),
				                    'qty' =>  $item['qty'],
				                    'line_total' =>  $order->get_item_subtotal( $item, false )
									);

			}
        }

        $wyrepay_args['subtotal'] = number_format( $order->get_total() - round( $order->get_total_shipping() + $order->get_shipping_tax(), 2 ) + $order->get_total_discount(), 2, '.', '' );
		$wyrepay_args['shipping_cost'] = number_format( $order->get_total_shipping() + $order->get_shipping_tax(), 2, '.', '' );
		$wyrepay_args['tax_amount'] = $order->get_shipping_tax();
        $wyrepay_args['total'] = $order->get_total();
        $wyrepay_args['ordered_items'] = $products;
        
        $wyrepay_args = apply_filters( 'wpwoo_wyrepay_args', $wyrepay_args );

        return $wyrepay_args;
    }

    /**
     * Admin Panel Options
     */
    public function admin_options() { ?>
        <h2>Wyre Settings
            <?php
            if ( function_exists( 'wc_back_link' ) ) {
                wc_back_link( 'Return to payments', admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
            }
            ?>
        </h2>

        <?php
        if ( $this->is_valid_for_use() ){

            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';

        }
        else {	 ?>
            <div class="inline error"><p><strong>Wyrepay Payment Gateway Disabled</strong>: <?php echo $this->msg ?></p></div>

        <?php }

    }


    /**
     * Process the payment and return the result [TODO]
     **/
    public function process_payment( $order_id ) {

        // TODO: Work on this based on tests ...
        $response = $this->get_payment_link( $order_id );

        if( 'success' == $response['result'] ) {

            return array(
                'result' => 'success',
                'redirect' => $response['redirect']
            );

        } else {

            if($response==-1||$response==-4) wc_add_notice( "Invalid configuration, confirm your merchant API Key", 'error' );
            else if($response==-3||$response==-14) wc_add_notice( "Merchant API Key is incorrect", 'error' );
            else wc_add_notice( "Unable to complete payment request", 'error' );
           
            return array(
                'result' 	=> 'fail',
                'redirect'	=> ''
            );

        }
    }
   
    /**
     * Get Wyrepay payment link [TODO]
     **/
    public function get_payment_link( $order_id ) {
       $order = wc_get_order( $order_id );

        $wyrepay_args = $this->get_wyrepay_args( $order );
        $wyrepay_redirect  = $this->url."test?";
        $wyrepay_redirect .= http_build_query( $wyrepay_args );

        $args = array(
            'timeout'   => 60
        );

        $request = wp_remote_post( $wyrepay_redirect, $args );

        if ( ! is_wp_error( $request )) {
            wc_add_notice( "See body ".$request['body'], 'error' );

            $redirect_url=$request['body'];
            $e_order=array_pop(explode('/',$redirect_url));
            $redirect_url=$order->get_checkout_payment_url( true ).'&e_order='.$e_order;
            $response = array(
                'result'	=> 'success',
                'redirect'	=> $redirect_url
            );
        } else {
             
            //Check response for response error codes 
            $s2s_code=trim($request['body']);
            if(is_numeric($s2s_code)) return $s2s_code;

            $response = array(
                'result'	=> 'fail',
                'redirect'	=> ''
            );
        }

        return $response;
    }

    /**
     * Displays the payment page [TODO]
     */
    public function receipt_page( $order_id ) {
 
        $order = wc_get_order( $order_id );
        error_log("Checkout the GET array ".$_GET);

        if(isset($_GET['e_order']))
        {
            
            $url=$this->url.'pay/i/'.sanitize_text_field($_GET['e_order']);
            echo '<p>Thank you for your order, please click the '.$this->order_button_text.' button below to proceed.</p>';
            echo '<div>
                    <form id="order_review" method="post" action="'. WC()->api_request_url( 'WPWOO_Wyrepay_Plugin' ) .'"></form>
                    <button class="button alt" onclick="wp_inline(\''.$url.'\',\''.$order->get_cancel_order_url().'\',\''.$this->get_return_url( $order ).'\')">'.$this->order_button_text.'</button> 
                    <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">Cancel order</a>
                  </div>';
        }
        else
        {
            wc_add_notice( "Unable to complete payment", 'error' );
            ?>
            <a class="button cancel" href="<?php echo esc_url( $order->get_cancel_order_url() ); ?>">Cancel order</a>
            <?php
        }
    }

    // Check payment callback ... [TODO]
    public function check_wyrepay_response() {

        if( isset( $_POST['transaction_id'] ) ) {

            $transaction_id = sanitize_text_field($_POST['transaction_id']);

            $args = array( 'timeout' => 60 );

            if( $this->demo ) {

                $json = wp_remote_get( $this->url .'?v_transaction_id='.$transaction_id.'&type=json&demo=true', $args );

            } else {

                $json = wp_remote_get( $this->url .'?v_transaction_id='.$transaction_id.'&type=json', $args );

            }

            $transaction 	= json_decode( $json['body'], true );
            
            foreach($transaction as $key =>$val) $transaction[$key]=sanitize_text_field($val);
            
            $transaction_id = $transaction['transaction_id'];
            $ref_split 		= explode('-', $transaction['merchant_ref'] );
            
           
            $order_id 		= (int) $ref_split[0];

            $order 			= wc_get_order($order_id);
            $order_total	= $order->get_total();

            $amount_paid_currency 	= $ref_split[1];
            $amount_paid 	= $ref_split[2];

          
            if( $transaction['status'] == 'Approved' ) {

                if( $transaction['merchant_id'] != $this->merchant_id && $transaction['merchant_id']!='demo' ) {

                    //Update the order status
                    $order->update_status('on-hold', ''); 

                    //Error Note
                    $message = 'Thank you for shopping with us.<br />Your payment transaction was successful, but the amount was paid to the wrong merchant account. <br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
                    $message_type = 'notice';

                    //Add Admin Order Note
                    $order->add_order_note('Look into this order. <br />This order is currently on hold.<br />Reason: Possible fradulent attempt. Transaction ID: '.$transaction_id);

                    add_post_meta( $order_id, 'transaction_id', $transaction_id, true );

                    // Reduce stock levels
                    $order->reduce_order_stock();

                    // Empty cart
                    wc_empty_cart();

                    echo 'Merchant ID mis-match';

                } else {

                    // check if the amount paid is equal to the order amount.
                    if( $amount_paid < $order_total ) {

                        //Update the order status
                        $order->update_status( 'on-hold', '' );

                        //Error Note
                        $message = 'Thank you for shopping with us.<br />Your payment transaction was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
                        $message_type = 'notice';

                        //Add Admin Order Note
                        $order->add_order_note('Look into this order. <br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was '.$amount_paid_currency.' '.$amount_paid.' while the total order amount is '.$amount_paid_currency.' '.$order_total.'<br />Wyrepay Transaction ID: '.$transaction_id);

                        add_post_meta( $order_id, 'transaction_id', $transaction_id, true );

                        // Reduce stock levels
                        $order->reduce_order_stock();

                        // Empty cart
                        wc_empty_cart();

                        echo 'Total amount mis-match';

                    } else {

                        $order->payment_complete( $transaction_id );

                        //Add admin order note
                        $order->add_order_note( 'Payment Via Wyrepay.<br />Transaction ID: '.$transaction_id );

                        $message = 'Payment was successful.';
                        $message_type = 'success';

                        // Empty cart
                        wc_empty_cart();

                        echo 'OK';
                    }
                }

                $wyrepay_message = array(
                    'message'		=> $message,
                    'message_type' 	=> $message_type
                );

                update_post_meta( $order_id, 'message', $wyrepay_message );



            } else {

                $message = 'Payment failed.';
                $message_type = 'error';

                $transaction_id = $transaction['transaction_id'];

                //Add Admin Order Note
                $order->add_order_note($message.'<br />Wyre Transaction ID: '.$transaction_id.'<br/>Reason: '.$transaction['response_message']);

                //Update the order status
                $order->update_status( 'failed', '' );

                $wyrepay_message = array(
                    'message'		=> $message,
                    'message_type' 	=> $message_type
                );

                update_post_meta( $order_id, 'message', $wyrepay_message );

                add_post_meta( $order_id, 'transaction_id', $transaction_id, true );

                echo "OK";
            }

        } else echo 'Failed to process';

        die();
    }

    



}
