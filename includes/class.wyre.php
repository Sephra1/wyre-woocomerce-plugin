<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPWOO_Wyre_Plugin extends WC_Payment_Gateway {

    public $allowed_currencies=array('GHS');

    public function __construct(){
       
        $this->id 					= 'wyre-woo-plugin';
        $this->icon 				= apply_filters('wpwoo_wyre_icon', plugins_url( 'assets/pay-via-wyre.png' , WPWOO_WYRE_BASE ) );
        $this->has_fields 			= true;
        $this->order_button_text 	= 'Make Payment';
        $this->url 		         	= 'https://e-order.wyre.tech/sysapi/';
        $this->url_eorder 		    = 'https://e-order.wyre.tech/';
        $this->notify_url        	= WC()->api_request_url( 'wyre_callback' );
        $this->method_title     	= 'Wyre';
        $this->method_description  	= 'Accept Mobile Money and Debit card payment directly on your store with the Wyre Tech payment gateway for WooCommerce.';

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
        add_action( 'init', array( $this, 'check_wyre_response' ) );
        add_action( 'woocommerce_api_wyre_callback', array( $this, 'check_wyre_response' ) );
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
                'title' 		=> 'Merchant API Key',
                'type' 			=> 'text',
                'description' 	=> 'Enter Your Merchant API Key, this can be gotten on your settings page when you login on Wyre at https://my.wyre.tech' ,
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
            $this->msg = 'Wyre doesn\'t support your store currency ('.get_woocommerce_currency().'), you can update your currency <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=general">here</a>';
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
        wp_enqueue_script( 'wpwoo_wyre', $this->url_eorder.'woo/js/wyre.js', array( 'jquery' ));
        wp_enqueue_script( 'wpwoo_wyre_inline', plugins_url( 'assets/wyre-woo.js', WPWOO_WYRE_BASE ), array( 'jquery', 'wpwoo_wyre' ));

    }

    /**
     * Get Wyre args
     **/
    public function get_wyre_args( $order ) {

        $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
        $wyre_args = array(
            'api_key' 		        =>  $this->api_key,
            'cur' 					=> get_woocommerce_currency(),
            'desc'					=> "Payment for Order #$order_id on ". get_bloginfo('name'),
            'notify_url'			=> $this->notify_url,
            'shop_name'             => get_bloginfo('name'),
            'success_url'			=> $this->get_return_url( $order ),
            'fail_url'				=> $this->get_return_url( $order ),
            'order_id'              => $order_id,
        );

        $first_name  	= method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name;
        $last_name  	= method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name;
        $wyre_args['name']=$first_name.' '.$last_name;
       
        $wyre_args['email']=method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;
        $wyre_args['phone']=method_exists( $order, 'get_billing_phone' ) ? $order->get_billing_phone() : $order->billing_phone;
        $billing_address 	= $order->get_formatted_billing_address();
        $billing_address 	= esc_html( preg_replace( '#<br\s*/?>#i', ', ', $billing_address ) );
        $address_split= explode(',',$billing_address);
        if(isset($address_split[1])) $wyre_args['address'] =$address_split[1];
        if(isset($address_split[2])) $wyre_args['city'] =$address_split[2];
        if(isset($address_split[3])) $wyre_args['state'] =$address_split[3];
        if(isset($address_split[4])) $wyre_args['zipcode'] =$address_split[4];
        
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

        $wyre_args['subtotal'] = number_format( $order->get_total() - round( $order->get_total_shipping() + $order->get_shipping_tax(), 2 ) + $order->get_total_discount(), 2, '.', '' );
		$wyre_args['shipping_cost'] = number_format( $order->get_total_shipping() + $order->get_shipping_tax(), 2, '.', '' );
		$wyre_args['tax_amount'] = $order->get_shipping_tax();
        $wyre_args['total'] = $order->get_total();
        $wyre_args['ordered_items'] = $products;
        
        $wyre_args = apply_filters( 'wpwoo_wyre_args', $wyre_args );

        return $wyre_args;
    }

    /**
     * Admin Panel Options
     */
    public function admin_options() { ?>
        <h2>Wyre Settings <a href="https://my.wyre.tech/settings" target="_blank" style="
                        line-height: 0;
                        background: white;
                        padding: 8px;
                        padding-left: 14px;
                        padding-right: 14px;
                        border-radius: 5px;
                        text-decoration: none;
                        font-size: 14px;
                        box-shadow: 0px 0px 1px;
                    ">Open My Settings</a>
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
            <div class="inline error"><p><strong>Wyre Payment Gateway Disabled</strong>: <?php echo $this->msg ?></p></div>

        <?php }

    }


    /**
     * Process the payment and return the result
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

            if($response==-2) wc_add_notice( "Invalid Plugin Configuration, ask Merchant to check API Key", 'error' );
            else if($response==-1) wc_add_notice( "Merchant API Key is not present", 'error' );
            else wc_add_notice( "Unable to complete payment request", 'error' );
           
            return array(
                'result' 	=> 'fail',
                'redirect'	=> ''
            );

        }
    }
   
    /**
     * Send order details over to Wyre
     **/
    public function get_payment_link( $order_id ) {
        $order = wc_get_order( $order_id );
        $wyre_args = $this->get_wyre_args( $order );
        $wyre_redirect  = $this->url."process_wp"; // Wyre Wordpress Order Endpoint ...

        $request = wp_remote_post($wyre_redirect, array(
            'body' => $wyre_args
        ));

        $valid_url = strpos( $request['body'], $this->url_eorder); // Check contains URL ...
        
        if ( ! is_wp_error( $request ) && $valid_url !== false) {
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
     * Displays the payment page
     */
    public function receipt_page( $order_id ) {
 
        $order = wc_get_order( $order_id );

        if(isset($_GET['e_order']))
        {
            
            $url=$this->url_eorder.'pay/i/'.sanitize_text_field($_GET['e_order']);
            echo '<p>Thank you for your order, please click the '.$this->order_button_text.' button below to proceed.</p>';
            echo '<div>
                    <form id="order_review" method="post" action="'. WC()->api_request_url( 'wyre_callback' ) .'"></form>
                    <button class="button alt" onclick="wp_inline(\''.$url.'\',\''.$order->get_cancel_order_url().'\',\''.$this->get_return_url( $order ).'\')">'.$this->order_button_text.'</button> 
                    <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">Cancel order</a>
                  </div>';
        }
        else
        {
            ?>
            <a class="button cancel" href="<?php echo esc_url( $order->get_cancel_order_url() ); ?>">Cancel order</a>
            <?php
        }
    }

    // Last item to look at ... Comming soon
    public function check_wyre_response() {

        die(json_encode($_POST));
        error_log("Hey there. SEE the CALLBACK");
        error_log(json_encode($_POST));
        if( isset( $_POST['transaction'] ) ) {

            $transaction = $_POST['transaction'];
            
            foreach($transaction as $key =>$val) $transaction[$key]=sanitize_text_field($val);
            
            $transaction_id = $transaction['transaction_id'];
            $order_id 		= (int) $transaction['order_id'];
            $order 			= wc_get_order($order_id);
            $order_total	= $order->get_total();
            $amount_paid_currency 	= $transaction['currency'];
            $amount_paid = $transaction['amount_paid'];

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
                        $order->add_order_note('Look into this order. <br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was '.$amount_paid_currency.' '.$amount_paid.' while the total order amount is '.$amount_paid_currency.' '.$order_total.'<br />Wyre Transaction ID: '.$transaction_id);

                        add_post_meta( $order_id, 'transaction_id', $transaction_id, true );

                        // Reduce stock levels
                        $order->reduce_order_stock();

                        // Empty cart
                        wc_empty_cart();

                        echo 'Total amount mis-match';

                    } else {

                        $order->payment_complete( $transaction_id );

                        //Add admin order note
                        $order->add_order_note( 'Payment Via Wyre.<br />Transaction ID: '.$transaction_id );

                        $message = 'Payment was successful.';
                        $message_type = 'success';

                        // Empty cart
                        wc_empty_cart();

                        echo 'OK';
                    }
                }

                $wyre_message = array(
                    'message'		=> $message,
                    'message_type' 	=> $message_type
                );

                update_post_meta( $order_id, 'message', $wyre_message );

            } else {

                $message = 'Payment failed.';
                $message_type = 'error';

                $transaction_id = $transaction['transaction_id'];

                //Add Admin Order Note
                $order->add_order_note($message.'<br />Wyre Transaction ID: '.$transaction_id.'<br/>Reason: '.$transaction['response_message']);

                //Update the order status
                $order->update_status( 'failed', '' );

                $wyre_message = array(
                    'message'		=> $message,
                    'message_type' 	=> $message_type
                );

                update_post_meta( $order_id, 'message', $wyre_message );
                add_post_meta( $order_id, 'transaction_id', $transaction_id, true );
                echo "OK";
            }

        } else echo 'Failed to process';

        die();
    }
}