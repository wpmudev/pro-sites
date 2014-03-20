<?php

/*
 * Pro Sites (Gateway: Manual Payments Gateway)
 */

class ProSites_Gateway_2Checkout {
	var $complete_message = false;

	function ProSites_Gateway_2Checkout() {
		$this->__construct();
	}

	function __construct() {
		global $psts;

		require $psts->plugin_dir . "gateways/gateway-2checkout-files/Twocheckout.php";

		//settings
		add_action( 'psts_gateway_settings', array( &$this, 'settings' ) );
		//add_action('psts_settings_process', array(&$this, 'settings_process'));

		//checkout stuff
		add_action( 'psts_checkout_page_load', array( &$this, 'process_checkout' ) );
		add_filter( 'psts_checkout_output', array( &$this, 'checkout_screen' ), 10, 2 );
		add_filter( 'psts_force_ssl', array( &$this, 'force_ssl' ) );
		/*

		//handle webhook notifications
		add_action('wp_ajax_nopriv_psts_stripe_webhook', array(&$this, 'webhook_handler'));

		//sync levels with Stripe
		add_action('update_site_option_psts_levels', array(&$this, 'update_psts_levels'), 10, 3);

		//plug management page
		add_action('psts_subscription_info', array(&$this, 'subscription_info'));
		add_action('psts_subscriber_info', array(&$this, 'subscriber_info'));
		add_action('psts_modify_form', array(&$this, 'modify_form'));
		add_action('psts_modify_process', array(&$this, 'process_modify'));
		add_action('psts_transfer_pro', array(&$this, 'process_transfer'), 10, 2);

		//filter payment info
		add_action('psts_payment_info', array(&$this, 'payment_info'), 10, 2);

		//return next payment date for emails
		add_filter('psts_next_payment', array(&$this, 'next_payment'));

		//cancel subscriptions on blog deletion
		add_action('delete_blog', array(&$this, 'cancel_blog_subscription'));*/
	}

	function settings() {
		global $psts;
		?>
		<div class="postbox">
			<h3 class="hndle" style="cursor:auto;"><span><?php _e( '2Checkout', 'psts' ) ?></span> -
				<span class="description"><?php _e( '', 'psts' ); ?></span></h3>

			<div class="inside">
				<p class="description"><?php _e( ".", 'psts' ); ?>
					<a href="https://www.2checkout.com" target="_blank"><?php _e( 'More Info &raquo;', 'psts' ) ?></a></p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php _e( '2Checkout Mode', 'psts' ) ?></th>
						<td>
							<select name="psts[2co_checkout_mode]">
								<option value="N"<?php selected( $psts->get_setting( '2co_checkout_mode' ), 'live' ); ?>><?php _e( 'Live Site', 'psts' ) ?></option>
								<option value="Y"<?php selected( $psts->get_setting( '2co_checkout_mode' ), 'test' ); ?>><?php _e( 'Test Mode (Sandbox)', 'psts' ) ?></option>
							</select></td>
					</tr>
					<tr>
						<th scope="row"><?php _e( '2Checkout Currency', 'psts' ) ?></th>
						<td>
							<select name="psts[2co_currency]">
								<?php
								$sel_currency = $psts->get_setting( "2co_currency", 'USD' );
								$currencies = array(
										"AED" => 'AED: United Arab Emirates Dirham',
										"ARS" => 'ARS: Argentine Peso*',
										"AUD" => 'AUD: Australian Dollar*',
										"BRL" => 'BRL: Brazilian Real*',
										"CAD" => 'CAD: Canadian Dollar*',
										"CHF" => 'CHF: Swiss Franc',
										"DKK" => 'DKK: Danish Krone',
										"EUR" => 'EUR: Euro',
										"GBP" => 'GBP: British Pound',
										"HKD" => 'HKD: Hong Kong Dollar',
										"INR" => 'INR: Indian Rupee*',
										"JPY" => 'JPY: Japanese Yen',
										"LTL" => 'LTL: Lithuanian Litas',
										"MXN" => 'MXN: Mexican Peso*',
										"MYR" => 'MYR: Malaysian Ringgit',
										"NOK" => 'NOK: Norwegian Krone',
										"NZD" => 'NZD: New Zealand Dollar',
										"PHP" => 'PHP: Philippine Peso',
										"RON" => 'RON: Romanian Leu',
										"RUB" => 'RUB: Russian Ruble',
										"SEK" => 'SEK: Swedish Krona',
										"SGD" => 'SGD: Singapore Dollar',
										"TRY" => 'TRY: Turkish Lira',
										"USD" => 'USD: United States Dollar',
								);

								foreach ( $currencies as $k => $v ) {
									echo '		<option value="' . $k . '"' . ( $k == $sel_currency ? ' selected' : '' ) . '>' . esc_html( $v ) . '</option>' . "\n";
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( '2Checkout Language', 'psts' ) ?></th>
						<td>
							<select name="psts[2co_language]">
								<?php
								$sel_language = $psts->get_setting( "2co_language", 'en' );
								$languages = array(
										"zh"    => "Chinese",
										"da"    => "Danish",
										"nl"    => "Dutch",
										"fr"    => "French",
										"gr"    => "German",
										"el"    => "Greek",
										"it"    => "Italian",
										"jp"    => "Japanese",
										"no"    => "Norwegian",
										"pt"    => "Portuguese",
										"sl"    => "Slovenian",
										"es_ib" => "Spanish (European)",
										"es_la" => "Spanish (Latin)",
										"sv"    => "Swedish",
										"en"    => "English"
								);
								foreach ( $languages as $k => $v ) {
									echo '		<option value="' . $k . '"' . ( $k == $sel_language ? ' selected' : '' ) . '>' . esc_html( $v ) . '</option>' . "\n";
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( '2Checkout API Credentials', 'psts' ) ?></th>
						<td>
							<p>
								<label><?php _e( 'Account Number', 'psts' ) ?></label><br />
								<input type="text" name="psts[2co_acc_number]" value="<?php esc_attr_e( $psts->get_setting( "2co_acc_number" ) ); ?>">
							</p>

							<p>
								<label><?php _e( 'Username', 'psts' ) ?></label><br />
								<input type="text" name="psts[2co_api_username]" value="<?php esc_attr_e( $psts->get_setting( "2co_api_username" ) ); ?>">
							</p>

							<p>
								<label><?php _e( 'Password', 'psts' ) ?></label><br />
								<input type="password" name="psts[2co_api_password]" value="<?php esc_attr_e( $psts->get_setting( "2co_api_password" ) ); ?>">
							</p>
						<td />
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Thank You Message', 'psts' ) ?></th>
						<td>
							<textarea name="psts[2co_thankyou]" type="text" rows="4" wrap="soft" id="stripe_thankyou" style="width: 95%" /><?php echo esc_textarea( $psts->get_setting( '2co_thankyou' ) ); ?></textarea>
							<br /><?php _e( 'Displayed on the page after successful checkout with this gateway. This is also a good place to paste any conversion tracking scripts like from Google Analytics. - HTML allowed', 'psts' ) ?>
						</td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	}

	function process_checkout( $blog_id ) {
		global $current_site, $current_user, $psts, $wpdb;

		if ( isset( $_POST['2co_checkout_button'] ) ) {
			if ( ! $this->check_nonce() )
				$psts->errors->add( 'general', __( 'Whoops, looks like you may have tried to submit your payment twice so we prevented it. Check your subscription info below to see if it was created. If not, please try again.', 'psts' ) );

			if ( empty( $_POST['period'] ) || empty( $_POST['level'] ) ) {
				$psts->errors->add( 'general', __( 'Please choose your desired level and payment plan.', 'psts' ) );
				return;
			}

			//start to build the params and send to 2checkout,the time has come
			$args                    = array(
					'sid'                => $psts->get_setting( '2co_acc_number' ),
					'mode'               => '2CO',
					'li_0_duration'      => 'Forever',
					'demo'               => $psts->get_setting( '2co_checkout_mode' ),
					'currency_code'      => $psts->get_setting( '2co_currency' ),
					'lang'               => $psts->get_setting( '2co_language' ),
					'x_receipt_link_url' => 'http://createdn.com/2co.php'//$psts->checkout_url( $blog_id )
			);
			$args['li_0_recurrence'] = $_POST['period'] == 1 ? $_POST['period'] . ' month' : $_POST['period'] . ' months';
			//build the price
			$discountAmt = false;
			$currency    = $psts->get_setting( '2co_currency' );
			if ( $_POST['period'] == 1 ) {
				$paymentAmount = $psts->get_level_setting( $_POST['level'], 'price_1' );
				if ( isset( $_SESSION['COUPON_CODE'] ) && $psts->check_coupon( $_SESSION['COUPON_CODE'], $blog_id, $_POST['level'] ) ) {
					$coupon_value = $psts->coupon_value( $_SESSION['COUPON_CODE'], $paymentAmount );
					$discountAmt  = $coupon_value['new_total'];
					$desc         = $current_site->site_name . ' ' . $psts->get_level_setting( $_POST['level'], 'name' ) . ': ' . sprintf( __( '%1$s for the first month, then %2$s each month', 'psts' ), $psts->format_currency( $currency, $discountAmt ), $psts->format_currency( $currency, $paymentAmount ) );
				} else {
					$desc = $current_site->site_name . ' ' . $psts->get_level_setting( $_POST['level'], 'name' ) . ': ' . sprintf( __( '%1$s %2$s each month', 'psts' ), $psts->format_currency( $currency, $paymentAmount ), $currency );
				}
			} else if ( $_POST['period'] == 3 ) {
				$paymentAmount = $psts->get_level_setting( $_POST['level'], 'price_3' );
				if ( isset( $_SESSION['COUPON_CODE'] ) && $psts->check_coupon( $_SESSION['COUPON_CODE'], $blog_id, $_POST['level'] ) ) {
					$coupon_value = $psts->coupon_value( $_SESSION['COUPON_CODE'], $paymentAmount );
					$discountAmt  = $coupon_value['new_total'];
					$desc         = $current_site->site_name . ' ' . $psts->get_level_setting( $_POST['level'], 'name' ) . ': ' . sprintf( __( '%1$s for the first 3 month period, then %2$s every 3 months', 'psts' ), $psts->format_currency( $currency, $discountAmt ), $psts->format_currency( $currency, $paymentAmount ) );
				} else {
					$desc = $current_site->site_name . ' ' . $psts->get_level_setting( $_POST['level'], 'name' ) . ': ' . sprintf( __( '%1$s %2$s every 3 months', 'psts' ), $psts->format_currency( $currency, $paymentAmount ), $currency );
				}
			} else if ( $_POST['period'] == 12 ) {
				$paymentAmount = $psts->get_level_setting( $_POST['level'], 'price_12' );
				if ( isset( $_SESSION['COUPON_CODE'] ) && $psts->check_coupon( $_SESSION['COUPON_CODE'], $blog_id, $_POST['level'] ) ) {
					$coupon_value = $psts->coupon_value( $_SESSION['COUPON_CODE'], $paymentAmount );
					$discountAmt  = $coupon_value['new_total'];
					$desc         = $current_site->site_name . ' ' . $psts->get_level_setting( $_POST['level'], 'name' ) . ': ' . sprintf( __( '%1$s for the first 12 month period, then %2$s every 12 months', 'psts' ), $psts->format_currency( $currency, $discountAmt ), $psts->format_currency( $currency, $paymentAmount ) );
				} else {
					$desc = $current_site->site_name . ' ' . $psts->get_level_setting( $_POST['level'], 'name' ) . ': ' . sprintf( __( '%1$s %2$s every 12 months', 'psts' ), $psts->format_currency( $currency, $paymentAmount ), $currency );
				}
			}
			//assign the cost and name
			$args['li_0_name']  = $desc;
			$args['li_0_price'] = $paymentAmount;
			$checkout_link      = Twocheckout_Charge::link( $args );
			wp_redirect( $checkout_link );
			echo $checkout_link;
		}
	}

	function checkout_screen( $content, $blog_id ) {
		global $psts, $wpdb, $current_site, $current_user;
		if ( ! $blog_id )
			return $content;


		//build the checkout form
		$content .= $this->build_checkout_form_html( $blog_id );
		return $content;
	}

	function build_checkout_form_html( $blog_id ) {
		global $psts;
		$html = '<form action="' . $psts->checkout_url( $blog_id ) . '" method="post" autocomplete="off"  id="payment-form">';
		//checkout grid
		$html .= $psts->checkout_grid( $blog_id );
		$html .= $this->nonce_field();
		$html .= '<input type="submit" id="cc_checkout" name="2co_checkout_button" value="' . __( 'Subscribe', 'psts' ) . ' &raquo;" class="submit-button"/>';
		$html .= '</form>';
		return $html;
	}

	function force_ssl() {
		//always ssl active
		//return true;

		//dev mode
		return false;
	}

	function nonce_field() {
		$user  = wp_get_current_user();
		$uid   = (int) $user->ID;
		$nonce = wp_hash( wp_rand() . 'pstsnonce' . $uid, 'nonce' );
		update_user_meta( $uid, '_psts_nonce', $nonce );
		return '<input type="hidden" name="_psts_nonce" value="' . $nonce . '" />';
	}

	//check the nonce
	function check_nonce() {
		$user  = wp_get_current_user();
		$uid   = (int) $user->ID;
		$nonce = get_user_meta( $uid, '_psts_nonce', true );
		if ( ! $nonce )
			return false;

		if ( $_POST['_psts_nonce'] == $nonce ) {
			delete_user_meta( $uid, '_psts_nonce' );
			return true;
		} else {
			return false;
		}
	}
}

//register the gateway
psts_register_gateway( 'ProSites_Gateway_2Checkout', __( '2Checkout', 'psts' ), __( 'Description update later', 'psts' ) );