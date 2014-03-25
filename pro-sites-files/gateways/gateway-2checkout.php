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

		//plug management page
		add_action( 'psts_subscription_info', array( &$this, 'subscription_info' ) );
		add_action( 'psts_subscriber_info', array( &$this, 'subscriber_info' ) );

		//handle webhook notifications
		//add_action( 'wp_ajax_nopriv_psts_2co_webhook', array( &$this, 'webhook_handler' ) );

		//plug management page
		//add_action('psts_modify_form', array(&$this, 'modify_form'));
		//add_action('psts_modify_process', array(&$this, 'process_modify'));
		//add_action('psts_transfer_pro', array(&$this, 'process_transfer'), 10, 2);

		//filter payment info
		add_action( 'psts_payment_info', array( &$this, 'payment_info' ), 10, 2 );

		//cancel subscriptions on blog deletion
		//add_action('delete_blog', array(&$this, 'cancel_blog_subscription'));
	}

	function settings() {
		global $psts;
		?>
		<div class="postbox">
			<h3 class="hndle" style="cursor:auto;"><span><?php _e( '2Checkout', 'psts' ) ?></span> -
				<span class="description"><?php _e( 'Accept Payments Globally', 'psts' ); ?></span></h3>

			<div class="inside">
				<p class="description"><?php _e( "Accept Credit Cards, PayPal, and Debit Cards", 'psts' ); ?>
					<a href="https://www.2checkout.com" target="_blank"><?php _e( 'More Info &raquo;', 'psts' ) ?></a></p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php _e( '2Checkout Mode', 'psts' ) ?></th>
						<td>
							<select name="psts[2co_checkout_mode]">
								<option value="N"<?php selected( $psts->get_setting( '2co_checkout_mode' ), 'N' ); ?>><?php _e( 'Live Site', 'psts' ) ?></option>
								<option value="Y"<?php selected( $psts->get_setting( '2co_checkout_mode' ), 'Y' ); ?>><?php _e( 'Test Mode (Sandbox)', 'psts' ) ?></option>
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

							<p>
								<label><?php _e( 'Secret Word', 'psts' ) ?></label><br />
								<input type="text" name="psts[2co_secret_word]" value="<?php esc_attr_e( $psts->get_setting( "2co_secret_word" ) ); ?>">
							</p>
						<td />
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Thank You Message', 'psts' ) ?></th>
						<td>
							<textarea name="psts[2co_thankyou]" type="text" rows="4" wrap="soft" id="2co_thankyou" style="width: 95%" /><?php echo esc_textarea( $psts->get_setting( '2co_thankyou' ) ); ?></textarea>
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

		//add scripts
		add_action( 'wp_head', array( &$this, 'checkout_js' ) );
		wp_enqueue_script( array( 'jquery' ) );

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
					'x_receipt_link_url' => $psts->checkout_url( $blog_id )
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
			//wp_redirect( $checkout_link );
			echo '<a href=""' . $checkout_link . '>' . __( 'Click here if you are not redirect', 'psts' ) . '</a>';
			echo '<script type="text/javascript">location.href="' . $checkout_link . '"</script>';
		} elseif ( isset( $_REQUEST['credit_card_processed'] ) && strtolower( $_REQUEST['credit_card_processed'] ) == 'y' ) {
			//this is the case return back after checkout
			$hashSecretWord = $psts->get_setting( '2co_secret_word' );
			$hashSid        = $psts->get_setting( '2co_acc_number' );
			$hashTotal      = $_REQUEST['total'];
			if ( $_REQUEST['demo'] == 'Y' )
				$hashOrder = '1';
			else
				$hashOrder = $_REQUEST['order_number'];
			$StringToHash = strtoupper( md5( $hashSecretWord . $hashSid . $hashOrder . $hashTotal ) );
			if ( $StringToHash != $_REQUEST['key'] ) {
				$psts->errors->add( 'general', __( 'There was a problem validating the 2Checkout payment:<br /><strong>MD5 Hash did not match!</strong><br />Please contact the seller directly for assistance.', 'psts' ), 'psts' );

			} else {
				_e( 'Processing payment data,please do not turn of the tab...', 'psts' );
				$desc = $_GET['li_0_name'];
				$this->set_profile_id( $blog_id, $hashOrder );
				$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via 2Checkout: Subscription created (%1$s) - Profile ID: %2$s', 'psts' ), $desc, $hashOrder ) );
				$psts->extend( $blog_id, $_REQUEST['period'], '2Checkout', $_REQUEST['level'], $hashTotal );
				$psts->email_notification( $blog_id, 'success' );

				//record last payment
				$psts->record_transaction( $blog_id, $_REQUEST['invoice_id'], $hashTotal );

				// Added for affiliate system link
				do_action( 'supporter_payment_processed', $blog_id, $hashTotal, $_REQUEST['period'], $_REQUEST['level'] );

				$psts->create_ga_ecommerce( $blog_id, $_REQUEST['period'], $hashTotal, $_REQUEST['level'] );
				//redirect immediatly to remove a bunch of sensitive data on url
				//because we redirect,the completemessage will lost effect,use SESSION instead
				$_SERVER['2co_complete_message'] = __( 'Your 2Checkout subscription was successful! You should be receiving an email receipt shortly.', 'psts' );
				echo '<script type="text/javascript">location.href="' . $psts->checkout_url( $blog_id ) . '"</script>';
			}
		}
		//$_SESSION['2co_complete_message'] = __( 'Your 2Checkout subscription was successful! You should be receiving an email receipt shortly.', 'psts' );
	}

	function checkout_js() {
		?>
		<script type="text/javascript"> jQuery(document).ready(function () {
				jQuery("a#twocheckout_cancel").click(function () {
					if (confirm("<?php echo __('Please note that if you cancel your subscription you will not be immune to future price increases. The price of un-canceled subscriptions will never go up!\n\nAre you sure you really want to cancel your subscription?\nThis action cannot be undone!', 'psts'); ?>")) return true; else return false;
				});
			});</script><?php
	}

	function checkout_screen( $content, $blog_id ) {
		global $psts, $wpdb, $current_site, $current_user;
		if ( ! $blog_id )
			return $content;

		$img_base           = $psts->plugin_url . 'images/';
		$twocheckout_active = false;

		//hide top part of content if its a pro blog
		if ( is_pro_site( $blog_id ) || $psts->errors->get_error_message( 'coupon' ) )
			$content = '';

		if ( $errmsg = $psts->errors->get_error_message( 'general' ) ) {
			$content = '<div id="psts-general-error" class="psts-error">' . $errmsg . '</div>'; //hide top part of content if theres an error
		}

		//display the complete message if having

		if ( isset( $_SESSION['2co_complete_message'] ) && ! empty( $_SESSION['2co_complete_message'] ) ) {
			$content = '<div id="psts-complete-msg">' . $_SESSION['2co_complete_message'] . '</div>';
			$content .= '<p>' . $psts->get_setting( '2co_thankyou' ) . '</p>';
			$content .= '<p><a href="' . get_admin_url( $blog_id, '', 'http' ) . '">' . __( 'Visit your newly upgraded site &raquo;', 'psts' ) . '</a></p>';
			//remove session
			unset($_SESSION['2co_complete_message']);
			return $content;
		}

		//check if pro/express user
		if ( $profile_id = $this->get_profile_id( $blog_id ) ) {
			$content .= '<div id="psts_existing_info">';
			$cancel_content = '';
			$end_date       = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
			$level          = $psts->get_level_setting( $psts->get_level( $blog_id ), 'name' );

			//cancel subscription
			if ( isset( $_GET['action'] ) && $_GET['action'] == 'cancel' && wp_verify_nonce( $_GET['_wpnonce'], 'psts-cancel' ) ) {

				$resArray = $this->ManageRecurringPaymentsProfileStatus( $profile_id );

				if ( $resArray['response_code'] == 'OK' ) {
					$content .= '<div id="message" class="updated fade"><p>' . sprintf( __( 'Your %1$s subscription has been canceled. You should continue to have access until %2$s.', 'psts' ), $current_site->site_name . ' ' . $psts->get_setting( 'rebrand' ), $end_date ) . '</p></div>';
					//record stat
					$psts->record_stat( $blog_id, 'cancel' );
					$psts->email_notification( $blog_id, 'canceled' );
					$psts->log_action( $blog_id, sprintf( __( 'Subscription successfully canceled by the user. They should continue to have access until %s', 'psts' ), $end_date ) );
				} else {
					$content .= '<div id="message" class="error fade"><p>' . __( 'There was a problem canceling your subscription, please contact us for help: ', 'psts' ) . ( $resArray2['errors'][0]['message'] ) . '</p></div>';
				}
			}

			//show sub details
			$resArray         = $this->GetRecurringPaymentsProfileDetails( $profile_id );
			$active_recurring = $this->get_recurring_lineitems( $resArray );
			$lineitem         = $active_recurring[0];

			if ( ( $resArray['response_code'] == 'OK' && isset( $lineitem ) ) ) {

				if ( isset( $lineitem['date_placed'] ) ) {
					$prev_billing = date_i18n( get_option( 'date_format' ), strtotime( $lineitem['date_placed'] ) );
				} else if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					$prev_billing = date_i18n( get_option( 'date_format' ), $last_payment['timestamp'] );
				} else {
					$prev_billing = __( "None yet with this subscription <small>(only initial separate single payment has been made, or you've recently modified your subscription)</small>", 'psts' );
				}

				if ( isset( $lineitem['date_next'] ) ) {
					$next_billing = date_i18n( get_option( 'date_format' ), strtotime( $lineitem['date_next'] ) );
				} else {
					$next_billing = __( "Invoice not deposited yet or not scheduled to recur.", 'psts' );
				}

				if ( is_pro_site( $blog_id ) ) {
					$content .= '<li>' . __( 'Level:', 'psts' ) . ' <strong>' . $level . '</strong></li>';
				}

				$content .= '<li>' . __( 'Last Payment Date:', 'psts' ) . ' <strong>' . $prev_billing . '</strong></li>';
				$content .= '<li>' . __( 'Next Payment Date:', 'psts' ) . ' <strong>' . $next_billing . '</strong></li>';
				$content .= '</ul><br />';

				$cancel_content .= '<h3>' . __( 'Cancel Your Subscription', 'psts' ) . '</h3>';
				if ( is_pro_site( $blog_id ) ) {
					$cancel_content .= '<p>' . sprintf( __( 'If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts' ), $level, $end_date ) . '</p>';
				}
				$cancel_content .= '<p><a id="twocheckout_cancel" href="' . wp_nonce_url( $psts->checkout_url( $blog_id ) . '&action=cancel', 'psts-cancel' ) . '" title="' . __( 'Cancel Your Subscription', 'psts' ) . '"><img src="' . $img_base . 'cancel_subscribe_gen.gif" /></a></p>';

				$twocheckout_active = true;

			} else if ( ( $resArray['response_code'] == 'OK' && ! isset( $lineitem ) ) ) {
				$content .= '<h3>' . __( 'Your subscription has been canceled', 'psts' ) . '</h3>';
				$content .= '<p>' . sprintf( __( 'This site should continue to have %1$s features until %2$s.', 'psts' ), $psts->get_setting( 'rebrand' ), $end_date ) . '</p>';

			} else if ( $resArray['response_code'] == 'OK' || $resArray['status'] == 'declined' ) {
				$content .= '<h3>' . sprintf( __( 'Your subscription is: %s', 'psts' ), $resArray['status'] ) . '</h3>';
				$content .= '<p>' . __( 'Please update your payment information below to resolve this.', 'psts' ) . '</p>';
				$cancel_content .= '<h3>' . __( 'Cancel Your Subscription', 'psts' ) . '</h3>';

				if ( is_pro_site( $blog_id ) ) {
					$cancel_content .= '<p>' . sprintf( __( 'If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts' ), $level, $end_date ) . '</p>';
				}

				$cancel_content .= '<p><a id="twocheckout_cancel" href="' . wp_nonce_url( $psts->checkout_url( $blog_id ) . '&action=cancel', 'psts-cancel' ) . '" title="' . __( 'Cancel Your Subscription', 'psts' ) . '"><img src="' . $img_base . 'cancel_subscribe_gen.gif" /></a></p>';
				$twocheckout_active = true;

			} else {
				$content .= '<div class="psts-error">' . __( "There was a problem accessing your subscription information: ", 'psts' ) . $resArray2['errors'][0]['message'] . '</div>';
			}

			//print receipt send form
			$content .= $psts->receipt_form( $blog_id );

			if ( ! defined( 'PSTS_CANCEL_LAST' ) ) {
				$content .= $cancel_content;
			}

			$content .= '</div>';

		} else if ( is_pro_site( $blog_id ) ) {

			// $end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
			$level       = $psts->get_level_setting( $psts->get_level( $blog_id ), 'name' );
			$old_gateway = $wpdb->get_var( "SELECT gateway FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'" );

			$content .= '<div id="psts_existing_info">';
			$content .= '<h3>' . __( 'Your Subscription Information', 'psts' ) . '</h3><ul>';
			$content .= '<li>' . __( 'Level:', 'psts' ) . ' <strong>' . $level . '</strong></li>';

			if ( $old_gateway == 'PayPal' )
				$content .= '<li>' . __( 'Payment Method: <strong>Your PayPal Account</strong>', 'psts' ) . '</li>';
			else if ( $old_gateway == 'Amazon' )
				$content .= '<li>' . __( 'Payment Method: <strong>Your Amazon Account</strong>', 'psts' ) . '</li>';
			else if ( $psts->get_expire( $blog_id ) >= 9999999999 )
				$content .= '<li>' . __( 'Expire Date: <strong>Never</strong>', 'psts' ) . '</li>';
			else
				$content .= '<li>' . sprintf( __( 'Expire Date: <strong>%s</strong>', 'psts' ), $end_date ) . '</li>';

			$content .= '</ul><br />';
			$cancel_content = '';
			if ( $old_gateway == 'PayPal' || $old_gateway == 'Amazon' ) {
				$cancel_content .= '<h3>' . __( 'Cancel Your Subscription', 'psts' ) . '</h3>';
				$cancel_content .= '<p>' . sprintf( __( 'If your subscription is still active your next scheduled payment should be %1$s.', 'psts' ), $end_date ) . '</p>';
				$cancel_content .= '<p>' . sprintf( __( 'If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts' ), $level, $end_date ) . '</p>';
				//show instructions for old gateways
				if ( $old_gateway == 'PayPal' ) {
					$cancel_content .= '<p><a id="twocheckout_cancel" target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_subscr-find&alias=' . urlencode( get_site_option( "supporter_paypal_email" ) ) . '" title="' . __( 'Cancel Your Subscription', 'psts' ) . '"><img src="' . $psts->plugin_url . 'images/cancel_subscribe_gen.gif" /></a><br /><small>' . __( 'You can also cancel following <a href="https://www.paypal.com/helpcenter/main.jsp;jsessionid=SCPbTbhRxL6QvdDMvshNZ4wT2DH25d01xJHj6cBvNJPGFVkcl6vV!795521328?t=solutionTab&ft=homeTab&ps=&solutionId=27715&locale=en_US&_dyncharset=UTF-8&countrycode=US&cmd=_help-ext">these steps</a>.', 'psts' ) . '</small></p>';
				} else if ( $old_gateway == 'Amazon' ) {
					$cancel_content .= '<p>' . __( 'To cancel your subscription, simply go to <a id="twocheckout_cancel" target="_blank" href="https://payments.amazon.com/">https://payments.amazon.com/</a>, click Your Account at the top of the page, log in to your Amazon Payments account (if asked), and then click the Your Subscriptions link. This page displays your subscriptions, showing the most recent, active subscription at the top. To view the details of a specific subscription, click Details. Then cancel your subscription by clicking the Cancel Subscription button on the Subscription Details page.', 'psts' ) . '</p>';
				}
			}

			if ( $old_gateway == '2Checkout' ) {
				$cancel_content .= '<h3>' . __( 'Cancel Your Subscription', 'psts' ) . '</h3>';
				$cancel_content .= '<p>' . sprintf( __( 'If your subscription is still active your next scheduled payment should be %1$s.', 'psts' ), $end_date ) . '</p>';
				$cancel_content .= '<p>' . sprintf( __( 'If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts' ), $level, $end_date ) . '</p>';
			}

			//print receipt send form
			$content .= $psts->receipt_form( $blog_id );

			if ( ! defined( 'PSTS_CANCEL_LAST' ) ) {
				$content .= $cancel_content;
			}

			$content .= '</div>';

		}
		if ( $twocheckout_active ) {
			$content .= '<h2>' . __( 'Change Your Plan or Payment Details', 'psts' ) . '</h2>
	          <p>' . __( 'You can modify or upgrade your plan by placing a new subscription and canceling your previous subscription. Your new subscription expire time will be prorated for the first installment.', 'psts' ) . '</p>';
		} else {
			$content .= '<p>' . __( 'Please choose your desired plan then click the checkout button below.', 'psts' ) . '</p>';
		}
		//build the checkout form
		$content .= $this->build_checkout_form_html( $blog_id );
		//put cancel button at end
		if ( defined( 'PSTS_CANCEL_LAST' ) )
			$content .= $cancel_content;

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

	//record last payment
	function set_profile_id( $blog_id, $profile_id ) {
		$trans_meta = get_blog_option( $blog_id, 'psts_2co_profile_id' );

		$trans_meta[$profile_id]['profile_id'] = $profile_id;
		$trans_meta[$profile_id]['timestamp']  = time();
		update_blog_option( $blog_id, 'psts_2co_profile_id', $trans_meta );
	}

	function get_profile_id( $blog_id, $history = false ) {
		$trans_meta = get_blog_option( $blog_id, 'psts_2co_profile_id' );

		if ( is_array( $trans_meta ) ) {
			$last = array_pop( $trans_meta );
			if ( $history ) {
				return $trans_meta;
			} else {
				return $last['profile_id'];
			}
		} else if ( ! empty( $trans_meta ) ) {
			return $trans_meta;
		} else {
			return false;
		}
	}

	function subscription_info( $blog_id ) {
		global $psts;

		$profile_id = $this->get_profile_id( $blog_id );

		if ( $profile_id ) {
			$resArray         = $this->GetRecurringPaymentsProfileDetails( $profile_id );
			$active_recurring = $this->get_recurring_lineitems( $resArray );
			$lineitem         = $active_recurring[0];

			if ( ( $resArray['response_code'] == 'OK' ) ) {

				$active_member = true;

				if ( isset( $lineitem['date_placed'] ) ) {
					$prev_billing = date_i18n( get_option( 'date_format' ), strtotime( $lineitem['date_placed'] ) );
				} else if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					$prev_billing = date_i18n( get_option( 'date_format' ), $last_payment['timestamp'] );
				} else {
					$prev_billing = __( "None yet with this subscription <small>(only initial separate single payment has been made, or they recently modified their subscription)</small>", 'psts' );
				}

				if ( isset( $lineitem['date_next'] ) ) {
					$next_billing = date_i18n( get_option( 'date_format' ), strtotime( $lineitem['date_next'] ) );
				} else {
					$next_billing = __( "None", 'psts' );
				}

				$next_payment_timestamp = strtotime( $lineitem['date_next'] );

				echo '<ul>';
				echo '<li>' . sprintf( __( 'Last Payment Date: <strong>%s</strong>', 'psts' ), $prev_billing ) . '</li>';

				if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					echo '<li>' . sprintf( __( 'Last Payment Amount: <strong>%s</strong>', 'psts' ), $psts->format_currency( false, $last_payment['amount'] ) ) . '</li>';
					echo '<li>' . sprintf( __( 'Last Payment Transaction ID: %s', 'psts' ), $last_payment['txn_id'] ) . '</li>';
				}
				echo '<li>' . sprintf( __( 'Next Payment Date: <strong>%s</strong>', 'psts' ), $next_billing ) . '</li>';
				echo '<li>' . sprintf( __( 'Payments Made With This Subscription: <strong>%s</strong>', 'psts' ), $lineitem['installment'] ) . ' *</li>';
				echo '</ul>';

			} else if ( ( $resArray['response_code'] == 'OK' && $lineitem['status'] == 'stopped' ) ) {
				$canceled_member = true;
				$end_date        = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
				echo '<strong>' . __( 'The Subscription Has Been Cancelled with 2Checkout', 'psts' ) . '</strong>';
				echo '<ul><li>' . sprintf( __( 'They should continue to have access until %s.', 'psts' ), $end_date ) . '</li>';

				if ( isset( $lineitem['date_placed'] ) ) {
					$prev_billing = date_i18n( get_option( 'date_format' ), strtotime( $lineitem['date_placed'] ) );
				} else if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					$prev_billing = date_i18n( get_option( 'date_format' ), $last_payment['timestamp'] );
				} else {
					$prev_billing = __( 'None yet with this subscription <small>(only initial separate single payment has been made, or they recently modified their subscription)</small>', 'psts' );
				}

				echo '<li>' . sprintf( __( 'Last Payment Date: <strong>%s</strong>', 'psts' ), $prev_billing ) . '</li>';

				if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					echo '<li>' . sprintf( __( 'Last Payment Amount: <strong>%s</strong>', 'psts' ), $psts->format_currency( false, $last_payment['amount'] ) ) . '</li>';
				}
				echo '</ul>';

			} else if ( ( $resArray['response_code'] == 'OK' && $lineitem['status'] == 'declined' ) ) {

				$active_member = true;
				$end_date      = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
				echo '<strong>' . __( 'The Subscription Has Been Suspended', 'psts' ) . '</strong>';
				echo '<ul><li>' . sprintf( __( 'They should continue to have access until %s.', 'psts' ), $end_date ) . '</li>';

				if ( isset( $lineitem['date_placed'] ) ) {
					$prev_billing = date_i18n( get_option( 'date_format' ), strtotime( $lineitem['date_placed'] ) );
				} else if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					$prev_billing = date_i18n( get_option( 'date_format' ), $last_payment['timestamp'] );
				} else {
					$prev_billing = __( 'None yet with this subscription <small>(only initial separate single payment has been made, or they recently modified their subscription)</small>', 'psts' );
				}

				echo '<li>' . sprintf( __( 'Last Payment Date: <strong>%s</strong>', 'psts' ), $prev_billing ) . '</li>';
				if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					echo '<li>' . sprintf( __( 'Last Payment Amount: <strong>%s</strong>', 'psts' ), $psts->format_currency( false, $last_payment['amount'] ) ) . '</li>';
				}
				echo '</ul>';
			} else {
				echo '<div id="message" class="error fade"><p>' . sprintf( __( "Whoops! There was a problem accessing this site's subscription information: %s", 'psts' ), $resArray2['errors'][0]['message'] ) . '</p></div>';
			}
		} else {
			echo '<p>' . __( "This site is using an older gateway so their information is not accessible until the next payment comes through.", 'psts' ) . '</p>';
		}
	}

	function subscriber_info( $blog_id ) {
		global $psts;

		$profile_id = $this->get_profile_id( $blog_id );

		if ( $profile_id ) {
			$resArray = $this->GetRecurringPaymentsProfileDetails( $profile_id );

			//get user details
			if ( ( $resArray['response_code'] == 'OK' ) ) {
				echo '<p><strong>' . stripslashes( $resArray['sale']['customer']['cardholder_name'] ) . '</strong><br />';
				echo stripslashes( $resArray['sale']['customer']['address_1'] ) . '<br />';
				echo stripslashes( $resArray['sale']['customer']['city'] ) . ', ' . stripslashes( $resArray['sale']['customer']['state'] ) . ' ' . stripslashes( $resArray['sale']['customer']['zip'] ) . '<br />';
				echo stripslashes( $resArray['sale']['customer']['email'] ) . '</p>';
			}
		} else {
			echo '<p>' . __( "This site is using an older gateway so their information is not accessible until the next payment comes through.", 'psts' ) . '</p>';
		}
	}

	function payment_info( $payment_info, $blog_id ) {
		global $psts;

		$profile_id = $this->get_profile_id( $blog_id );
		if ( $profile_id ) {
			$resArray         = $this->GetRecurringPaymentsProfileDetails( $profile_id );
			$active_recurring = $this->get_recurring_lineitems( $resArray );
			$lineitem         = $active_recurring[0];

			if ( ( $resArray['response_code'] == 'OK' && $lineitem['status'] == 'active' ) ) {

				if ( $lineitem['date_next'] ) {
					$next_billing = date_i18n( get_blog_option( $blog_id, 'date_format' ), strtotime( $lineitem['date_next'] ) );
				} else {
					$next_billing = __( "None", 'psts' );
				}

				$payment_info = sprintf( __( 'Subscription Description: %s', 'psts' ), stripslashes( $resArray['DESC'] ) ) . "\n\n";

				if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					$payment_info .= sprintf( __( 'Payment Date: %s', 'psts' ), date_i18n( get_blog_option( $blog_id, 'date_format' ) ) ) . "\n";
					$payment_info .= sprintf( __( 'Payment Amount: %s', 'psts' ), $last_payment['amount'] . ' ' . $psts->get_setting( 'currency' ) ) . "\n";
					$payment_info .= sprintf( __( 'Payment Transaction ID: %s', 'psts' ), $last_payment['txn_id'] ) . "\n\n";
				}
				$payment_info .= sprintf( __( 'Next Scheduled Payment Date: %s', 'psts' ), $next_billing ) . "\n";
			}
		}
		return $payment_info;
	}

	/**** 2Checkout API methods *****/

	function GetRecurringPaymentsProfileDetails( $profile_id ) {
		$params            = array();
		$params['sale_id'] = $profile_id;
		return $this->api_call( 'https://www.2checkout.com/api/sales/detail_sale', $params );
	}

	function ManageRecurringPaymentsProfileStatus( $profile_id ) {
		$params            = array();
		$params['sale_id'] = $profile_id;
		$resArray          = $this->api_call( 'https://www.2checkout.com/api/sales/detail_sale', $params );
		$lineitemData      = $this->get_recurring_lineitems( $resArray );
		if ( isset( $lineitemData[0] ) ) {
			foreach ( $lineitemData as $value ) {
				$params = array( 'lineitem_id' => $value['lineitem_id'] );
				$result = $this->api_call( 'https://www.2checkout.com/api/sales/stop_lineitem_recurring', $params );
			}
		}
		return $result;
	}

	function RefundTransaction( $transaction_id, $partial_amt = false, $note ) {
		global $psts;
		$params               = array();
		$params['invoice_id'] = $transaction_id;
		$params['comment']    = $note;
		$params['category']   = 5;

		if ( $partial_amt ) {
			$params['amount']   = $partial_amt;
			$params['category'] = 5;
			$params['currency'] = 'vendor';
		}

		$resArray = $this->api_call( "https://www.2checkout.com/api/sales/refund_invoice", $params );

		return $resArray;
	}

	function get_recurring_lineitems( $saleDetail ) {
		$i           = 0;
		$invoiceData = array();

		while ( isset( $saleDetail['sale']['invoices'][$i] ) ) {
			$invoiceData[$i] = $saleDetail['sale']['invoices'][$i];
			$i ++;
		}
		if ( count( $invoiceData ) ) {
			$invoice      = max( $invoiceData );
			$i            = 0;
			$lineitemData = array();

			while ( isset( $invoice['lineitems'][$i] ) ) {
				if ( $invoice['lineitems'][$i]['billing']['recurring_status'] == "active" ) {
					$lineitemData[$i] = array( 'lineitem_id' => $invoice['lineitems'][$i]['billing']['lineitem_id'],
																		 'status'      => $invoice['lineitems'][$i]['billing']['recurring_status'],
																		 'installment' => $invoice['lineitems'][$i]['installment'],
																		 'date_placed' => $invoice['date_placed'],
																		 'date_next'   => $invoice['lineitems'][$i]['billing']['date_next'] );
				}
				$i ++;
			};

			return $lineitemData;
		} else {
			echo 'Something go wrong with the connection to 2checkout';
		}

	}

	function api_call( $url, $params ) {
		global $psts;

		$args            = array();
		$args['headers'] = array(
				'Authorization' => 'Basic ' . base64_encode( $psts->get_setting( 'twocheckout_api_user' ) . ':' . $psts->get_setting( 'twocheckout_api_pass' ) ),
				'Accept'        => 'application/json',
		);

		$args['user-agent'] = "ProSites | 2CO Payment plugin/0.1";
		$args['body']       = $params;
		$args['sslverify']  = false;
		$args['timeout']    = 30;

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) || ( wp_remote_retrieve_response_code( $response ) != 200 && wp_remote_retrieve_response_code( $response ) != 400 ) ) {
			print '<div class="alert alert-error">' . __( 'There was a problem connecting to 2CO. Please try again.', 'prosites' ) . '</div>';
		} else {
			return json_decode( $response['body'], true );
		}
	}

	function startDate( $frequency ) {
		$result = strtotime( "+$frequency month" );
		return urlencode( gmdate( 'Y-m-d\TH:i:s.00\Z', $result ) );
	}

	function modStartDate( $expire_stamp ) {
		return urlencode( gmdate( 'Y-m-d\TH:i:s.00\Z', $expire_stamp ) );
	}
}

//register the gateway
psts_register_gateway( 'ProSites_Gateway_2Checkout', __( '2Checkout', 'psts' ), __( 'Description update later', 'psts' ) );