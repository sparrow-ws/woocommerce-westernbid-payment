<?php

class WC_Gateway_Westernbid extends WC_Payment_Gateway {

	// constructor
	public function __construct() {

		$this->id = 'westernbid_payment';
		// $this->icon               = WC_WESTERNBID_PLUGIN_URL . 'assets/img/westernbid-logo.png';
		$this->has_fields         = true;
		$this->method_title       = __( 'WesternBid Payment', 'westernbid' );
		$this->method_description = __( 'PayPal secure payment via WesternBid service', 'westernbid' );
		// method with all the options fields
		$this->init_form_fields();
		// load settings
		$this->init_settings();

		$this->title            = $this->get_option( 'title' );
		$this->description      = $this->get_option( 'description' );
		$this->enabled          = $this->get_option( 'enabled' );
		$this->wb_login         = $this->get_option( 'login_token' );
		$this->token       		= $this->get_option( 'token' );
		$this->telegram         = $this->get_option( 'telegram' );
		$this->telegram_api     = $this->get_option( 'telegram_api' );
		$this->telegram_id      = $this->get_option( 'telegram_id' );
		$this->gateway_endpoint = 'https://shop.westernbid.info/';

		// action hook saves settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		// register webhook
		add_action( 'woocommerce_api_wc_gateway_westernbid', array( $this, 'check_response' ) );

	}

	// settings
	public function init_form_fields() {
		$this->form_fields = apply_filters( 'wc_westernbid_form_fields', array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'westernbid' ),
				'label'   => __( 'Enable Westernbid Payment', 'westernbid' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'title'       => array(
				'title'       => __( 'Title', 'westernbid' ),
				'default'     => __( 'Online payment PayPal', 'westernbid' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __( 'Title on checkout page.', 'westernbid' ),
			),
			'description' => array(
				'title'       => __( 'Description', 'westernbid' ),
				'default'     => __( "Pay with PayPal. You can pay with a credit or debit card if you don't have a PayPal account.", 'westernbid' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __( 'Description that buyers will see when choosing a payment method.', 'westernbid' ),
			),
			'login_token' => array(
				'title'       => 'Merchant account',
				'type'        => 'text',
			),
			'token'  => array(
				'title' => __( 'You secret key', 'westernbid' ),
				'type'  => 'text',
			),
			//'test_mode'  => array(
			//	'title'   => __( 'Test mode', 'westernbid' ),
			//	'label'   => __( 'Enable Test Mode', 'westernbid' ),
			//	'type'    => 'checkbox',
			//	'default' => 'yes',
			//),
            'telegram' => array(
                'title' => __( 'Enable/Disable Telegram notifications', 'womono' ),
                'type' => 'checkbox',
                'label' => __( 'Enable Telegram notifications', 'womono' ),
                'default' => 'no',
            ),
            'telegram_api' => array(
                'title' => __( 'API Token Telegram', 'womono' ),
                'type' => 'text',
                'description' => __( 'Token of your Telegram bot.', 'womono' ),
                'default' => '',
            ),
            'telegram_id' => array(
                'title' => __( 'Chat ID Telegram', 'womono' ),
                'type' => 'text',
                'description' => __( 'The ID of the chat with the bot to which it will send messages.', 'womono' ),
                'default' => '',
            ),
		) );
	}

    public function get_icon() {
        $plugin_dir = plugin_dir_url(__FILE__);
        $icon_html = '<img src="'.$plugin_dir.'assets/img/westernbid-logo.png" style="width: 85px;" alt="WesternBid" />';
        return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    }

	// processing payments
	public function process_payment( $order_id ) {

		global $woocommerce, $woocommerce_wpml;

		if ( ! $order_id ) {
			return;
		}

		if ( ! is_object( $woocommerce_wpml ) && class_exists( 'woocommerce_wpml' ) ) {
			$wc_wpml = new woocommerce_wpml();
		} else {
			$wc_wpml = $woocommerce_wpml;
		}

		$order = new WC_Order( $order_id );

		$chr_en = "a-zA-Z0-9\s`~!@#$%^&*()_+-={}|:;<>?,.\/\"\'\\\[\]";
		if (!preg_match("/^[$chr_en]+$/", $order->get_billing_first_name())) {
			wc_add_notice( '<p>To pay via PayPal, all fields must be completed in English!</p>', 'error' );
			return;
		}
		if (!preg_match("/^[$chr_en]+$/", $order->get_billing_last_name())) {
			wc_add_notice( '<p>To pay via PayPal, all fields must be completed in English!</p>', 'error' );
			return;
		}

		$bank_currency = json_decode(file_get_contents('https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?json'));
    	if($bank_currency){
	        foreach ($bank_currency as $currency) {
	            if ($currency->r030 == '840') {
	                $currency_usd = $currency->rate;
	                $currency_usd = $currency_usd * 100;
	            }
	        }
	    } else {
	    	$currency_usd = 3700;
	    }

	    //$order_total = $order->get_total();
	    //$order_total = round(($order_total*100)/$currency_usd);
	    //$order_total = $order_total/100;

		// $order_total = ( is_object( $wc_wpml ) ? $wc_wpml->multi_currency->prices->convert_price_amount_by_currencies( $order->get_total(), $order->get_currency(), 'USD' ) : $order->get_total() );
		// $order_discount = ( is_object( $wc_wpml ) ? $wc_wpml->multi_currency->prices->convert_price_amount_by_currencies( $order->get_total_discount(), $order->get_currency(), 'USD' ) : $order->get_total_discount() );

		// request values
		$payload = array(
			'charset'       => 'utf-8',
			'wb_login'      => $this->wb_login,
			//'wb_hash'       => md5( $this->wb_login . $this->token . $order_total . $order->get_id() ),
			'invoice'       => $order->get_id(),
			'first_name'    => $order->get_billing_first_name(),
			'last_name'     => $order->get_billing_last_name(),
			//'address1'      => $order->get_billing_address_1(),
			//'country'       => $order->get_billing_country(),
			//'city'          => $order->get_billing_city(),
			//'state'         => $order->get_billing_state(),
			//'zip'           => $order->get_billing_postcode(),
			'phone'         => $order->get_billing_phone(),
			'email'         => $order->get_billing_email(),
			//'amount'        => $order_total,
			//'discount_amount_cart' => $order_discount,
			//'currency_code' => $order->get_currency(),
			'currency_code' => 'USD',
			'shipping'      => $order->get_shipping_total(),
			'return'        => $order->get_checkout_order_received_url(),
			'cancel_return' => $order->get_cancel_order_url_raw(),
			'notify_url'    => $woocommerce->api_request_url( 'wc_gateway_westernbid' ),
		);

		// cart list
		$i = 1;
        $cart_info = $order->get_items();
        $basket_info = array();

        if ( WC()->cart->get_cart_discount_total() <> 0 ) {

            $count_cart_item = WC()->cart->get_cart_contents_count();
            $order_total = $order->get_total();
	    	$order_total = round((($order_total*100)/$currency_usd)*100);
            $item_price = round($order_total/$count_cart_item);
            $item_price = $item_price/100;
	    	$order_total = $item_price * $count_cart_item;

            foreach ($cart_info as $item_id => $item_data) {

				$count = $i++;
                $product = $item_data->get_product();
                $product_name = $product->get_name();
                if (!preg_match("/^[$chr_en]+$/", $product_name)) {
                	$product_name = $this->translit($product_name);
                }

				$payload["item_name_".$count] = $product_name;
				$payload["item_number_".$count] = $product->get_id();
				$payload["url_".$count] = $product->get_permalink();
				$payload["description_".$count] = $product->get_short_description() ? $product->get_short_description() : get_bloginfo( 'name' );
				$payload["amount_".$count] = $item_price;
				$payload["quantity_".$count] = $item_data->get_quantity();
				
				$basket_info[] = $product->get_name() . " x" . $item_data->get_quantity();

            }

        } else {

        	$order_total = 0;

            foreach ($cart_info as $item_id => $item_data) {

				$count = $i++;
                $product = $item_data->get_product();
                $item_price = wc_get_price_including_tax( $product );
	    		$item_price = round((($item_price*100)/$currency_usd)*100);
	    		$item_price = $item_price/100;
	    		$order_total = $order_total + $item_price;
	    		$product_name = $product->get_name();
                if (!preg_match("/^[$chr_en]+$/", $product_name)) {
                	$product_name = $this->translit($product_name);
                }

				$payload["item_name_".$count] = $product_name;
				$payload["item_number_".$count] = $product->get_id();
				$payload["url_".$count] = $product->get_permalink();
				$payload["description_".$count] = $product->get_short_description() ? $product->get_short_description() : get_bloginfo( 'name' );
				$payload["amount_".$count] = $item_price;
				$payload["quantity_".$count] = $item_data->get_quantity();
				
				$basket_info[] = $product->get_name() . " x" . $item_data->get_quantity();

            }

        }

        $payload['amount'] = $order_total;
        $payload['wb_hash'] = md5( $this->wb_login . $this->token . $order_total . $order->get_id() );
		$payload['item_name'] = implode( ', ', $basket_info );

		//if ( current_user_can( 'administrator' ) ) {
		//	dump( $payload );
		//	die();
		//}

		// send to westernbid for processing
		$response = wp_remote_post( $this->gateway_endpoint, array(
			'method'    => 'POST',
			'body'      => http_build_query( $payload ),
			'timeout'   => 90,
			'sslverify' => false,
		) );
		
		if ( is_wp_error( $response ) ) {
			wc_add_notice( 'Connection error.', 'error' );
		
			return;
		}
		
		//$check_currency_code = $order->get_currency();
		//if ( $check_currency_code != 'USD' ) {
		//	wc_add_notice( '<p>Оплата через PayPal можлива лише у доларах на англійській версії сайту!</p><p><a href="/en/checkout"><ins>Перейти на англійську версію сайту</ins></a></p>', 'error' );
		//	return;
		//}
		
		// get response body if there are no errors
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		//file_put_contents( WP_CONTENT_DIR . '/wb_log.txt', print_r( ' ! '.$response_body.' ! ', true ), FILE_APPEND );
		//file_put_contents( WP_CONTENT_DIR . '/wb_log.txt', '---- // ---- // ---- // ----', FILE_APPEND );

		if ( $response_code != 200 ) {
			wc_add_notice( $response_body, 'error' );
			//wc_add_notice( 'An error occurred while getting data from WesternBid. Try to make a purchase later.', 'error' );
			return;
		}
		if ( empty( $response_body ) ) {
			wc_add_notice( 'An error occurred while getting data from WesternBid. Try to make a purchase later.', 'error' );
			return;
		}
		
		$paypal_args       = $this->get_inputs( $response_body );
		$paypal_action_url = $this->get_action_url( $response_body );
		$woocommerce->cart->empty_cart();

		$this->send_telegram_messege("\xF0\x9F\x99\x8C Нове замовлення\n\nНомер замовлення: ".$order->get_id().".\nСума замовлення: ".$order_total." USD.\nЧас: ".date("d-m-Y H:i:s").".");

		return array(
			'result'   => 'success',
			'redirect' => $paypal_action_url.'?'.http_build_query($paypal_args, '', '&'),
		);

	}

	// getting and parsing form for input fields
	private function get_action_url( $content ) {
		$content = preg_replace( "/&(?!(?:apos|quot|[gl]t|amp);|#)/", '&amp;', $content );
		$doc     = new DOMDocument();
		@$doc->loadHTML( $content );
		$xpath = new DomXPath( $doc );
		$items = $xpath->query( '//form' );
		foreach ( $items as $item ) {
			if ( $item->getAttribute( 'name' ) == 'process' ) {
				return trim( $this->has_attribute( $item, 'action' ) );
			}
		}
		return '';
	}

	// fetch and parse form for input fields
	private function get_inputs( $content ) {
		$content = preg_replace( "/&(?!(?:apos|quot|[gl]t|amp);|#)/", '&amp;', $content );
		$doc     = new DOMDocument();
		@$doc->loadHTML( $content );
		$xpath = new DomXPath( $doc );
		$list  = array();
		$items = $xpath->query( '//input | //select' );
		foreach ( $items as $item ) {
			if ( $item->getAttribute( 'type' ) == 'hidden' ) {
				$list[ trim( $this->has_attribute( $item, 'name' ) ) ] = trim( $this->has_attribute( $item, 'value' ) );
			}
		}
		return $list;
	}

	// checks if the DOM being parsed has an attribute
	public function has_attribute( $obj, $attribute ) {
		if ( $obj->hasAttribute( $attribute ) ) {
			return $obj->getAttribute( $attribute );
		} else {
			return '';
		}
	}

	// check response (webhook)
	public function check_response() {
		$order_id = str_replace( $this->wb_login . '-', '', $_POST['invoice'] );
		add_post_meta( $order_id, '_westernbid_transaction_response', $_POST );
		
		if ( $_POST['wb_result'] === 'VERIFIED' && $_POST['wb_hash'] === md5( $this->wb_login . $_POST['wb_result'] . $this->token . $_POST['mc_gross'] . $_POST['invoice'] ) ) {
			$payment_status = strtolower( $_POST['payment_status'] );
			$order = wc_get_order( $order_id );
			update_post_meta( $order_id, '_westernbid_transaction_id', $_POST['transaction_id'] );

			//file_put_contents( WP_CONTENT_DIR . '/wb_log.txt', print_r( ' --\\-- '.$order_id.' : '.$payment_status.' : '.$_POST['transaction_id'].' --\\-- ', true ), FILE_APPEND );

			switch ( $payment_status ) {
				/*
				case 'pending' :
					$order->update_status( 'processing' );
					$this->send_telegram_messege("\xE2\x9C\x85 Успішна оплата\n\nНомер замовлення: ".$order_id.".\nНомер транзакції: ".$_POST['transaction_id'].".\nСума замовлення: ".$_POST['payment_gross']." USD.\nЧас: ".$_POST['payment_date'].".");
					break;
				*/
				case 'completed' :
					// $order->update_status( 'completed' );
					$order->update_status( 'processing' );
					$this->send_telegram_messege("\xE2\x9C\x85 Успішна оплата\n\nНомер замовлення: ".$order_id.".\nНомер транзакції: ".$_POST['transaction_id'].".\nСума замовлення: ".$_POST['payment_gross']." USD.\nЧас: ".$_POST['payment_date'].".");
					/*
					if($order->get_status() === 'processing') {
	        			$order->update_status( 'completed' );
	        			$this->send_telegram_messege("\xF0\x9F\x92\xB0 Кошти успішно додано до балансу вашого рахунку\n\nНомер замовлення: ".$order_id.".\nНомер транзакції: ".$_POST['transaction_id'].".\nСума замовлення: ".$_POST['payment_gross']." USD.\nЧас: ".$_POST['payment_date'].".");
	    			} else { $order->add_order_note( 'Payment status: completed' ); }
	    			*/
					break;
				default:
					$order->add_order_note('Payment status: '.$payment_status);
					$this->send_telegram_messege("\xE2\x9D\x97 Увага, статус замовлення ".$order_id."(".$_POST['transaction_id'].") змінено на: ".$payment_status.".");
			}

		}

	}

	private function send_telegram_messege( $messegTelegram ) {

		$telegram_api = "";
		$telegram_api = $this->telegram_api;
        $getTelegram = array(
            'chat_id' => $this->telegram_id,
            'text' => $messegTelegram,
            'parse_mode' => 'HTML',
        );

        if($this->telegram == "yes" && $telegram_api != "") {
            $telegram = curl_init('https://api.telegram.org/bot'.$telegram_api.'/sendMessage?'.http_build_query($getTelegram));
            curl_setopt($telegram, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($telegram, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($telegram, CURLOPT_HEADER, false);
            $resultTelegram = curl_exec($telegram);
            curl_close($telegram);
        }

	}

	private function translit( $s ) {

		$s = trim($s);
		$s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
		$s = strtr($s, array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'shch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya','ъ'=>'','ь'=>'','і'=>'i','ї'=>'i'));
		return $s;
	
	}


}

