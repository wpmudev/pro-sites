<?php

/*
Pro Sites (Gateway: Paypal Express/Pro Payment Gateway)
*/

class ProSites_Gateway_PayPalExpressPro {

	public static $pending_str = array();
	private static $complete_message = false;
	private static $cancel_message = false;

	public static function get_slug() {
		return 'paypal';
	}

	function __construct() {
		global $psts;

		//Paypal Functions
		if ( ! class_exists( 'PaypalApiHelper' ) ) {
			require_once( $psts->plugin_dir . "gateways/gateway-paypal-files/class-paypal-api-helper.php" );
		}
		if ( ! is_admin() ) {
			add_action( 'wp_enqueue_scripts', array( &$this, 'do_scripts' ) );
		}

		add_filter( 'psts_settings_filter', array( &$this, 'settings_process' ), 10, 2 );

		//checkout stuff
		add_filter( 'psts_force_ssl', array( &$this, 'force_ssl' ) );

		//handle IPN notifications
		add_action( 'wp_ajax_nopriv_psts_pypl_ipn', array( &$this, 'ipn_handler' ) );

		//plug management page
		add_action( 'psts_subscription_info', array( &$this, 'subscription_info' ) );
		add_action( 'psts_subscriber_info', array( &$this, 'subscriber_info' ) );
		add_action( 'psts_modify_form', array( &$this, 'modify_form' ) );
		add_action( 'psts_modify_process', array( &$this, 'process_modify' ) );
		add_action( 'psts_transfer_pro', array( &$this, 'process_transfer' ), 10, 2 );

		//filter payment info
		add_action( 'psts_payment_info', array( &$this, 'payment_info' ), 10, 2 );

		//return next payment date for emails
		add_filter( 'psts_next_payment', array( &$this, 'next_payment' ) );

		//cancel subscriptions on blog deletion
		add_action( 'delete_blog', array( &$this, 'cancel_subscription' ) );

		/* This sets the default prefix to the paypal custom field,
		 * in case you use the same account for multiple IPN requiring scripts,
		 * and want to setup your own forwarding script somewhere to pass IPNs to
		 * the proper location. If that is the case you will also need to define
		 * PSTS_IPN_PASSWORD and post "inc_pass" along with the IPN string.
		 */
		if ( ! defined( 'PSTS_PYPL_PREFIX' ) ) {
			define( 'PSTS_PYPL_PREFIX', 'psts' );
		}

		add_action( 'psts_checkout_page_load', array( $this, 'process_checkout_form' ) );
		self::$pending_str = array(
			'address'        => __( 'The payment is pending because your customer did not include a confirmed shipping address and your Payment Receiving Preferences is set such that you want to manually accept or deny each of these payments. To change your preference, go to the Preferences  section of your Profile.', 'psts' ),
			'authorization'  => __( 'The payment is pending because it has been authorized but not settled. You must capture the funds first.', 'psts' ),
			'echeck'         => __( 'The payment is pending because it was made by an eCheck that has not yet cleared.', 'psts' ),
			'intl'           => __( 'The payment is pending because you hold a non-U.S. account and do not have a withdrawal mechanism. You must manually accept or deny this payment from your Account Overview.', 'psts' ),
			'multi-currency' => __( 'You do not have a balance in the currency sent, and you do not have your Payment Receiving Preferences set to automatically convert and accept this payment. You must manually accept or deny this payment.', 'psts' ),
			'order'          => __( 'The payment is pending because it is part of an order that has been authorized but not settled.', 'psts' ),
			'paymentreview'  => __( 'The payment is pending while it is being reviewed by PayPal for risk.', 'psts' ),
			'unilateral'     => __( 'The payment is pending because it was made to an email address that is not yet registered or confirmed.', 'psts' ),
			'upgrade'        => __( 'The payment is pending because it was made via credit card and you must upgrade your account to Business or Premier status in order to receive the funds. It can also mean that you have reached the monthly limit for transactions on your account.', 'psts' ),
			'verify'         => __( 'The payment is pending because you are not yet verified. You must verify your account before you can accept this payment.', 'psts' ),
			'other'          => __( 'The payment is pending for an unknown reason. For more information, contact PayPal customer service.', 'psts' ),
			'*'              => ''
		);
	}

	function do_scripts() {
		global $psts;
		/** get_the_ID() gives a notice on wordpress files as get_post() returns null, a ticket is on the way */
		if ( ! is_page() || get_the_ID() != $psts->get_setting( 'checkout_page' ) ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		add_action( 'wp_head', array( &$this, 'checkout_js' ) );
	}

	/**
	 * List of locales supported by Paypal
	 * @return array
	 */
	function get_locales() {
		return array(
			'AR' => 'Argentina',
			'AU' => 'Australia',
			'AT' => 'Austria',
			'BE' => 'Belgium',
			'BR' => 'Brazil',
			'CA' => 'Canada',
			'CN' => 'China',
			'FI' => 'Finland',
			'FR' => 'France',
			'DE' => 'Germany',
			'HK' => 'Hong Kong',
			'IL' => 'Israel',
			'IT' => 'Italy',
			'JP' => 'Japan',
			'MX' => 'Mexico',
			'NL' => 'Netherlands',
			'NZ' => 'New Zealand',
			'PL' => 'Poland',
			'RU' => 'Russia',
			'SG' => 'Singapore',
			'ES' => 'Spain',
			'SE' => 'Sweden',
			'CH' => 'Switzerland',
			'TH' => 'Thailand',
			'TR' => 'Turkey',
			'GB' => 'United Kingdom',
			'US' => 'United States'
		);
	}

	function settings() {
		global $psts;
		?>
		<div class="inside">
			<p><?php _e( 'Unlike PayPal Pro, there are no additional fees to use Express Checkout, though you may need to do a free upgrade to a business account. <a target="_blank" href="https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_api_ECGettingStarted">More Info &raquo;</a>', 'psts' ); ?></p>

			<p><?php printf( __( 'To use PayPal Express Checkout or Pro you must <a href="https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_admin_IPNSetup#id089EG030E5Z" target="_blank">manually turn on IPN notifications</a> and enter your IPN url (<strong>%s</strong>) in your PayPal profile (you must also do this in your sandbox account when testing).', 'psts' ), network_site_url( 'wp-admin/admin-ajax.php?action=psts_pypl_ipn', 'admin' ) ); ?></p>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e( 'PayPal Site', 'psts' ) ?></th>
					<td><select name="psts[pypl_site]" class="chosen">
							<?php
							$paypal_site = $psts->get_setting( 'pypl_site' );
							$sel_locale  = empty( $paypal_site ) ? 'US' : $paypal_site;
							$locales     = $this->get_locales();

							foreach ( $locales as $k => $v ) {
								echo '		<option value="' . $k . '"' . selected( $k, $sel_locale, false ) . '>' . esc_attr( $v ) . '</option>' . "\n";
							}
							?>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Paypal Currency', 'psts' ) ?></th>
					<?php
					//Get the general currency set in Pro Sites
					$currency = $psts->get_setting( 'currency' );

					//Get the currency selected for Paypal
					$paypal_currency = ProSites_Helper_Gateway::supports_currency( $currency, 'paypal' );

					//Choose the selected currency
					$sel_currency = empty( $paypal_currency ) ? $psts->get_setting( 'pypl_currency' ) : $paypal_currency;

					//List of currencies
					$supported_currencies = self::get_supported_currencies(); ?>
					<td><select name="psts[pypl_currency]" class="chosen">
							<?php
							foreach ( $supported_currencies as $k => $v ) {
								echo '<option value="' . $k . '"' . selected( $k, $sel_currency, false ) . '>' . esc_attr( $v[0] ) . '</option>' . "\n";
							}
							?>
						</select></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'PayPal Mode', 'psts' ) ?></th>
					<td><select name="psts[pypl_status]" class="chosen">
							<option value="live"<?php selected( $psts->get_setting( 'pypl_status' ), 'live' ); ?>><?php _e( 'Live Site', 'psts' ) ?></option>
							<option value="test"<?php selected( $psts->get_setting( 'pypl_status' ), 'test' ); ?>><?php _e( 'Test Mode (Sandbox)', 'psts' ) ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e( 'PayPal API Credentials', 'psts' ) ?></th>
					<td>
						<span class="description"><?php _e( 'You must login to PayPal and create an API signature to get your credentials. <a target="_blank" href="https://www.x.com/developers/paypal/documentation-tools/express-checkout/integration-guide/ECAPICredentials">Instructions &raquo;</a>', 'psts' ) ?></span>

						<p><label><?php _e( 'API Username', 'psts' ) ?><br/>
								<input value="<?php esc_attr_e( $psts->get_setting( "pypl_api_user" ) ); ?>" style="width: 100%; max-width: 500px;" name="psts[pypl_api_user]" type="text"/>
							</label></p>

						<p><label><?php _e( 'API Password', 'psts' ) ?><br/>
								<input value="<?php esc_attr_e( $psts->get_setting( "pypl_api_pass" ) ); ?>" style="width: 100%; max-width: 500px;" name="psts[pypl_api_pass]" type="text"/>
							</label></p>

						<p><label><?php _e( 'Signature', 'psts' ) ?><br/>
								<input value="<?php esc_attr_e( $psts->get_setting( "pypl_api_sig" ) ); ?>" style="width: 100%; max-width: 500px;" name="psts[pypl_api_sig]" type="text"/>
							</label></p>
					</td>
				</tr>
				<th scope="row"><?php _e( 'Enable PayPal Pro', 'psts' ) ?></th>
				<td>
					<span class="description"><?php _e( 'PayPal Website Payments Pro 3.0 allows you to seemlessly accept credit cards on your site, and gives you the most professional look with a widely accepted payment method. There are a few requirements you must meet to use PayPal Website Payments Pro:', 'psts' ) ?></span>
					<ul style="list-style:disc outside none;margin-left:25px;">
						<li><?php _e( 'You must signup (and pay the monthly fees) for <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_wp-pro-overview-outside" target="_blank">PayPal Website Payments Pro</a>. Note this uses the older Website Payments Pro 3.0 API, you will have to contact PayPal and have them manually setup or create a new account that supports Website Payments Pro 3.0', 'psts' ) ?></li>
						<li><?php _e( 'You must signup (and pay the monthly fees) for the <a href="https://www.paypal.com/cgi-bin/webscr?cmd=xpt/Marketing/general/ProRecurringPayments-outside" target="_blank">PayPal Website Payments Pro Recurring Payments addon</a>.', 'psts' ) ?></li>
						<li><?php _e( 'You must have an SSL certificate setup for your main blog/site where the checkout form will be displayed.', 'psts' ) ?></li>
						<li><?php _e( 'You additionaly must be <a href="https://www.paypal.com/pcicompliance" target="_blank">PCI compliant</a>, which means your server must meet security requirements for collecting and transmitting payment data.', 'psts' ) ?></li>
						<li><?php _e( 'The checkout form will be added to a page on your main site. You may need to adjust your theme stylesheet for it to look nice with your theme.', 'psts' ) ?></li>
						<li><?php _e( 'Due to PayPal policies, PayPal Express will always be offered in addition to credit card payments.', 'psts' ) ?></li>
						<li><?php _e( 'Be aware that PayPal Website Payments Pro only supports PayPal accounts in select countries.', 'psts' ) ?></li>
						<li><?php _e( 'Tip: When testing you will need to setup a preconfigured Website Payments Pro seller account in your sandbox.', 'psts' ) ?></li>
					</ul>
					<label><input type="checkbox" name="psts[pypl_enable_pro]" value="1"<?php echo checked( $psts->get_setting( "pypl_enable_pro" ), 1 ); ?> /> <?php _e( 'Enable PayPal Pro', 'psts' ) ?>
						<br/>
					</label>
				</td>
				</tr>
				<tr>
					<th scope="row" class="psts-help-div psts-paypal-header"><?php echo __( 'PayPal Header Image (optional)', 'psts' ) . $psts->help_text( __( 'https url of an 750 x 90 image displayed at the top left of the payment page. If a image is not specified, the business name is displayed.', 'psts' ) ); ?></th>
					<td>
						<p>
							<input value="<?php esc_attr_e( $psts->get_setting( "pypl_header_img" ) ); ?>" size="40" name="psts[pypl_header_img]" type="text"/>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row" class="psts-help-div psts-paypal-header-border"><?php echo __( 'PayPal Header Border Color (optional)', 'psts' ) . $psts->help_text( __( '6 character hex color for border around the header of the payment page.', 'psts' ) ); ?></th>
					<td>
						<p>
							<input value="<?php esc_attr_e( $psts->get_setting( "pypl_header_border" ) ); ?>" size="6" maxlength="6" name="psts[pypl_header_border]" type="text"/>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row" class="psts-help-div psts-paypal-header-background"><?php echo __( 'PayPal Header Background Color (optional)', 'psts' ) . $psts->help_text( __( '6 character hex color for header background of the payment page.', 'psts' ) ); ?></th>
					<td>
						<p>
							<input value="<?php esc_attr_e( $psts->get_setting( "pypl_header_back" ) ); ?>" size="6" maxlength="6" name="psts[pypl_header_back]" type="text"/>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row" class="psts-help-div psts-paypal-background"><?php echo __( 'PayPal Page Background Color (optional)', 'psts' ) . $psts->help_text( __( '6 character hex color for payment page background. Darker colors may not be allowed by PayPal.', 'psts' ) ) ?></th>
					<td>
						<p>
							<input value="<?php esc_attr_e( $psts->get_setting( "pypl_page_back" ) ); ?>" size="6" maxlength="6" name="psts[pypl_page_back]" type="text"/>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="psts-help-div psts-paypal-thank-you"><?php echo __( 'Thank You Message', 'psts' ) . $psts->help_text( __( 'Displayed on the page after successful checkout. This is also a good place to paste any conversion tracking scripts like from Google Analytics. - HTML allowed', 'psts' ) ); ?></th>
					<td>
						<textarea name="psts[pypl_thankyou]" type="text" rows="4" wrap="soft" id="pypl_thankyou" style="width: 95%"/><?php echo esc_textarea( $psts->get_setting( 'pypl_thankyou' ) ); ?></textarea>
					</td>
				</tr>
			</table>
		</div>
		<!--		</div>-->
	<?php
	}

	/**
	 * Check Paypal Pro is enabled
	 *
	 * @param $settings
	 * @param $gateway_class
	 *
	 * @return mixed
	 */
	function settings_process( $settings, $gateway_class ) {

		if ( get_class() == $gateway_class ) {
			$settings['pypl_enable_pro'] = isset( $settings['pypl_enable_pro'] ) ? $settings['pypl_enable_pro'] : 0;
		}

		return $settings;
	}

	//filters the ssl on checkout page
	function force_ssl() {
		global $psts;
		if ( $psts->get_setting( 'pypl_enable_pro' ) && $psts->get_setting( 'pypl_status' ) == 'live' ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Prints a hidden form field to prevent multiple form submits during checkout
	 *
	 * @return string
	 */
	public static function nonce_field() {
		$user  = wp_get_current_user();
		$uid   = ( int ) $user->ID;
		$nonce = wp_hash( wp_rand() . 'pstsnonce' . $uid, 'nonce' );
		ProSites_Helper_Session::session( '_psts_nonce', $nonce );

		return '<input type="hidden" name="_psts_nonce" value="' . $nonce . '" />';
	}

	/**
	 * Check nonce value
	 * @return bool
	 */
	public static function check_nonce() {

		$_psts_nonce = ProSites_Helper_Session::session( '_psts_nonce' );
		if ( empty( $_psts_nonce ) ) {
			return false;
		}

		if ( $_POST['_psts_nonce'] == $_psts_nonce ) {
			ProSites_Helper_Session::unset_session( '_psts_nonce' );

			return true;
		} else {
			return false;
		}
	}

	function manual_cancel_email( $blog_id, $old_gateway ) {
		global $psts, $current_user;

		$message = '';

		//show instructions for old gateways
		if ( $old_gateway == 'PayPal' ) {
			$message = sprintf( __( "Thank you for modifying your subscription!

We want to remind you that because of billing system upgrades, we were unable to cancel your old subscription automatically, so it is important that you cancel the old one yourself in your PayPal account, otherwise the old payments will continue along with new ones!

Cancel your subscription in your PayPal account:
%s

You can also cancel following these steps:
https://www.paypal.com/webapps/helpcenter/article/?articleID=94044#canceling_recurring_paymemt_subscription_automatic_billing", 'psts' ), 'https://www.paypal.com/cgi-bin/webscr?cmd=_subscr-find&alias=' . urlencode( get_site_option( "supporter_paypal_email" ) ) );
		} else if ( $old_gateway == 'Amazon' ) {
			$message = __( "Thank you for modifying your subscription!

We want to remind you that because of billing system upgrades, we were unable to cancel your old subscription automatically, so it is important that you cancel the old one yourself in your Amazon Payments account, otherwise the old payments will continue along with new ones!

To cancel your subscription:

Simply go to https://payments.amazon.com/, click Your Account at the top of the page, log in to your Amazon Payments account (if asked), and then click the Your Subscriptions link. This page displays your subscriptions, showing the most recent, active subscription at the top. To view the details of a specific subscription, click Details. Then cancel your subscription by clicking the Cancel Subscription button on the Subscription Details page.", 'psts' );
		}

		$email = isset( $current_user->user_email ) ? $current_user->user_email : get_blog_option( $blog_id, 'admin_email' );

		wp_mail( $email, __( "Don't forget to cancel your old subscription!", 'psts' ), $message );

		$psts->log_action( $blog_id, sprintf( __( 'Reminder to cancel previous %s subscription sent to %s', 'psts' ), $old_gateway, get_blog_option( $blog_id, 'admin_email' ) ) );
	}

	public static function year_dropdown( $sel = '' ) {
		$minYear = date( 'Y' );
		$maxYear = $minYear + 15;

		if ( empty( $sel ) ) {
			$sel = $minYear + 1;
		}

		$output = "<option value=''>--</option>";
		for ( $i = $minYear; $i < $maxYear; $i ++ ) {
			$output .= "<option value='" . substr( $i, 0, 4 ) . "'" . ( $sel == ( substr( $i, 0, 4 ) ) ? ' selected' : '' ) . ">" . $i . "</option>";
		}

		return $output;
	}

	public static function month_dropdown( $sel = '' ) {
		if ( empty( $sel ) ) {
			$sel = date( 'n' );
		}
		$output = "<option value=''>--</option>";
		$output .= "<option" . ( $sel == 1 ? ' selected' : '' ) . " value='01'>01 - " . __( 'Jan', 'psts' ) . "</option>";
		$output .= "<option" . ( $sel == 2 ? ' selected' : '' ) . " value='02'>02 - " . __( 'Feb', 'psts' ) . "</option>";
		$output .= "<option" . ( $sel == 3 ? ' selected' : '' ) . " value='03'>03 - " . __( 'Mar', 'psts' ) . "</option>";
		$output .= "<option" . ( $sel == 4 ? ' selected' : '' ) . " value='04'>04 - " . __( 'Apr', 'psts' ) . "</option>";
		$output .= "<option" . ( $sel == 5 ? ' selected' : '' ) . " value='05'>05 - " . __( 'May', 'psts' ) . "</option>";
		$output .= "<option" . ( $sel == 6 ? ' selected' : '' ) . " value='06'>06 - " . __( 'Jun', 'psts' ) . "</option>";
		$output .= "<option" . ( $sel == 7 ? ' selected' : '' ) . " value='07'>07 - " . __( 'Jul', 'psts' ) . "</option>";
		$output .= "<option" . ( $sel == 8 ? ' selected' : '' ) . " value='08'>08 - " . __( 'Aug', 'psts' ) . "</option>";
		$output .= "<option" . ( $sel == 9 ? ' selected' : '' ) . " value='09'>09 - " . __( 'Sep', 'psts' ) . "</option>";
		$output .= "<option" . ( $sel == 10 ? ' selected' : '' ) . " value='10'>10 - " . __( 'Oct', 'psts' ) . "</option>";
		$output .= "<option" . ( $sel == 11 ? ' selected' : '' ) . " value='11'>11 - " . __( 'Nov', 'psts' ) . "</option>";
		$output .= "<option" . ( $sel == 12 ? ' selected' : '' ) . " value='12'>12 - " . __( 'Dec', 'psts' ) . "</option>";

		return $output;
	}

	function payment_info( $payment_info, $blog_id ) {
		global $psts;

		$profile_id = $this->get_profile_id( $blog_id );
		if ( $profile_id ) {
			$resArray = PaypalApiHelper::GetRecurringPaymentsProfileDetails( $profile_id );

			if ( ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) && $resArray['STATUS'] == 'Active' ) {

				if ( isset( $resArray['NEXTBILLINGDATE'] ) ) {
					$next_billing = date_i18n( get_blog_option( $blog_id, 'date_format' ), strtotime( $resArray['NEXTBILLINGDATE'] ) );
				} else {
					$next_billing = __( "None", 'psts' );
				}

				$payment_info = sprintf( __( 'Subscription Description: %s', 'psts' ), stripslashes( $resArray['DESC'] ) ) . "\n\n";

				if ( isset( $resArray['ACCT'] ) ) { //credit card
					$month = substr( $resArray['EXPDATE'], 0, 2 );
					$year  = substr( $resArray['EXPDATE'], 2, 4 );
					$payment_info .= sprintf( __( 'Payment Method: %1$s Card ending in %2$s. Expires %3$s', 'psts' ), $resArray['CREDITCARDTYPE'], $resArray['ACCT'], $month . '/' . $year ) . "\n";
				} else { //paypal
					$payment_info .= __( 'Payment Method: PayPal Account', 'psts' ) . "\n";
				}

				if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					$payment_info .= sprintf( __( 'Payment Date: %s', 'psts' ), date_i18n( get_blog_option( $blog_id, 'date_format' ), $last_payment['timestamp'] ) ) . "\n";
					$payment_info .= sprintf( __( 'Payment Amount: %s', 'psts' ), $last_payment['amount'] . ' ' . $psts->get_setting( 'currency' ) ) . "\n";
					$payment_info .= sprintf( __( 'Payment Transaction ID: %s', 'psts' ), $last_payment['txn_id'] ) . "\n\n";
				}
				$payment_info .= sprintf( __( 'Next Scheduled Payment Date: %s', 'psts' ), $next_billing ) . "\n";

			}
		}

		return $payment_info;
	}

	function subscription_info( $blog_id ) {
		global $psts;

		if ( ! ProSites_Helper_Gateway::is_last_gateway_used( $blog_id, self::get_slug() ) ) {
			return false;
		}

		$profile_id = $this->get_profile_id( $blog_id );

		if ( $profile_id ) {
			$resArray = PaypalApiHelper::GetRecurringPaymentsProfileDetails( $profile_id );

			if ( ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) && $resArray['STATUS'] == 'Active' ) {

				$active_member = true;

				if ( isset( $resArray['LASTPAYMENTDATE'] ) ) {
					$prev_billing = date_i18n( get_option( 'date_format' ), strtotime( $resArray['LASTPAYMENTDATE'] ) );
				} else if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					$prev_billing = date_i18n( get_option( 'date_format' ), $last_payment['timestamp'] );
				} else {
					$prev_billing = __( "None yet with this subscription <small>(only initial separate single payment has been made, or they recently modified their subscription)</small>", 'psts' );
				}

				if ( isset( $resArray['NEXTBILLINGDATE'] ) ) {
					$next_billing = date_i18n( get_option( 'date_format' ), strtotime( $resArray['NEXTBILLINGDATE'] ) );
				} else {
					$next_billing = __( "None", 'psts' );
				}

				$next_payment_timestamp = strtotime( $resArray['NEXTBILLINGDATE'] );

				echo '<ul>';
				echo '<li>' . sprintf( __( 'Subscription Description: <strong>%s</strong>', 'psts' ), stripslashes( $resArray['DESC'] ) ) . '</li>';
				echo '<li>' . sprintf( __( 'PayPal Profile ID: <strong>%s</strong>', 'psts' ), '<a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id=' . $profile_id . '&mp_id=' . $profile_id . '&return_to=merchant&flag_flow=merchant#name1" target="_blank" title="View in PayPal &raquo;">' . $profile_id . '</a>' ) . '</li>';

				if ( isset( $resArray['ACCT'] ) ) { //credit card
					$month = substr( $resArray['EXPDATE'], 0, 2 );
					$year  = substr( $resArray['EXPDATE'], 2, 4 );
					echo '<li>' . sprintf( __( 'Payment Method: <strong>%1$s Card</strong> ending in <strong>%2$s</strong>. Expires <strong>%3$s</strong>', 'psts' ), $resArray['CREDITCARDTYPE'], $resArray['ACCT'], $month . '/' . $year ) . '</li>';
				} else { //paypal
					echo '<li>' . __( 'Payment Method: <strong>Their PayPal Account</strong>', 'psts' ) . '</li>';
				}

				echo '<li>' . sprintf( __( 'Last Payment Date: <strong>%s</strong>', 'psts' ), $prev_billing ) . '</li>';
				if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					echo '<li>' . sprintf( __( 'Last Payment Amount: <strong>%s</strong>', 'psts' ), $psts->format_currency( false, $last_payment['amount'] ) ) . '</li>';
					echo '<li>' . sprintf( __( 'Last Payment Transaction ID: <a target="_blank" href="https://www.paypal.com/vst/id=%s"><strong>%s</strong></a>', 'psts' ), $last_payment['txn_id'], $last_payment['txn_id'] ) . '</li>';
				}
				echo '<li>' . sprintf( __( 'Next Payment Date: <strong>%s</strong>', 'psts' ), $next_billing ) . '</li>';
				echo '<li>' . sprintf( __( 'Payments Made With This Subscription: <strong>%s</strong>', 'psts' ), $resArray['NUMCYCLESCOMPLETED'] ) . ' *</li>';
				echo '<li>' . sprintf( __( 'Aggregate Total With This Subscription: <strong>%s</strong>', 'psts' ), $psts->format_currency( false, $resArray['AGGREGATEAMT'] ) ) . ' *</li>';
				echo '</ul>';
				echo '<small>* (' . __( 'This does not include the initial payment at signup, or payments before the last payment method/plan change.', 'psts' ) . ')</small>';

			} else if ( ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) && $resArray['STATUS'] == 'Cancelled' ) {

				$canceled_member = true;

				$end_date = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
				echo '<strong>' . __( 'The Subscription Has Been Cancelled in PayPal', 'psts' ) . '</strong>';
				echo '<ul><li>' . sprintf( __( 'They should continue to have access until %s.', 'psts' ), $end_date ) . '</li>';
				echo '<li>' . sprintf( __( 'PayPal Profile ID: <strong>%s</strong>', 'psts' ), '<a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id=' . $profile_id . '&mp_id=' . $profile_id . '&return_to=merchant&flag_flow=merchant#name1" target="_blank" title="View in PayPal &raquo;">' . $profile_id . '</a>' ) . '</li>';

				if ( isset( $resArray['LASTPAYMENTDATE'] ) ) {
					$prev_billing = date_i18n( get_option( 'date_format' ), strtotime( $resArray['LASTPAYMENTDATE'] ) );
				} else if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					$prev_billing = date_i18n( get_option( 'date_format' ), $last_payment['timestamp'] );
				} else {
					$prev_billing = __( 'None yet with this subscription <small>(only initial separate single payment has been made, or they recently modified their subscription)</small>', 'psts' );
				}

				echo '<li>' . sprintf( __( 'Last Payment Date: <strong>%s</strong>', 'psts' ), $prev_billing ) . '</li>';
				if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					echo '<li>' . sprintf( __( 'Last Payment Amount: <strong>%s</strong>', 'psts' ), $psts->format_currency( false, $last_payment['amount'] ) ) . '</li>';
					echo '<li>' . sprintf( __( 'Last Payment Transaction ID: <a target="_blank" href="https://www.paypal.com/vst/id=%s"><strong>%s</strong></a>', 'psts' ), $last_payment['txn_id'], $last_payment['txn_id'] ) . '</li>';
				}
				echo '</ul>';

			} else if ( ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) && $resArray['STATUS'] == 'Suspended' ) {

				$active_member = true;

				$end_date = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
				echo '<strong>' . __( 'The Subscription Has Been Suspended in PayPal', 'psts' ) . '</strong>';
				echo '<p><a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id=' . $profile_id . '&mp_id=' . $profile_id . '&return_to=merchant&flag_flow=merchant#name1" target="_blank" title="View in PayPal &raquo;">' . __( 'Please check your PayPal account for more information.', 'psts' ) . '</a></p>';
				echo '<ul><li>' . sprintf( __( 'They should continue to have access until %s.', 'psts' ), $end_date ) . '</li>';
				echo '<li>' . sprintf( __( 'PayPal Profile ID: <strong>%s</strong>', 'psts' ), '<a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id=' . $profile_id . '&mp_id=' . $profile_id . '&return_to=merchant&flag_flow=merchant#name1" target="_blank" title="View in PayPal &raquo;">' . $profile_id . '</a>' ) . '</li>';

				if ( isset( $resArray['LASTPAYMENTDATE'] ) ) {
					$prev_billing = date_i18n( get_option( 'date_format' ), strtotime( $resArray['LASTPAYMENTDATE'] ) );
				} else if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					$prev_billing = date_i18n( get_option( 'date_format' ), $last_payment['timestamp'] );
				} else {
					$prev_billing = __( 'None yet with this subscription <small>(only initial separate single payment has been made, or they recently modified their subscription)</small>', 'psts' );
				}

				echo '<li>' . sprintf( __( 'Last Payment Date: <strong>%s</strong>', 'psts' ), $prev_billing ) . '</li>';
				if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					echo '<li>' . sprintf( __( 'Last Payment Amount: <strong>%s</strong>', 'psts' ), $psts->format_currency( false, $last_payment['amount'] ) ) . '</li>';
					echo '<li>' . sprintf( __( 'Last Payment Transaction ID: <a target="_blank" href="https://www.paypal.com/vst/id=%s"><strong>%s</strong></a>', 'psts' ), $last_payment['txn_id'], $last_payment['txn_id'] ) . '</li>';
				}
				echo '</ul>';

			} else if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {

				echo '<p>' . sprintf( __( 'The Subscription profile status is currently: <strong>%s</strong>', 'psts' ), $resArray['STATUS'] ) . '</p>';
				echo '<p><a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id=' . $profile_id . '&mp_id=' . $profile_id . '&return_to=merchant&flag_flow=merchant#name1" target="_blank" title="View in PayPal &raquo;">' . __( 'Please check your PayPal account for more information.', 'psts' ) . '</a></p>';

			} else {
				echo '<div id="message" class="error fade"><p>' . sprintf( __( "Whoops! There was a problem accessing this site's subscription information: %s", 'psts' ), $this->parse_error_string( $resArray ) ) . '</p></div>';
			}

			//show past profiles if they exists
			$profile_history = $this->get_profile_id( $blog_id, true );
			if ( is_array( $profile_history ) && count( $profile_history ) ) {
				$history_lines = array();
				foreach ( $profile_history as $profile ) {
					$history_lines[] = '<a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id=' . $profile['profile_id'] . '&mp_id=' . $profile['profile_id'] . '&return_to=merchant&flag_flow=merchant#name1" target="_blank" title="' . sprintf( __( 'Last used on %s', 'psts' ), date_i18n( get_option( 'date_format' ), $profile['timestamp'] ) ) . '">' . $profile['profile_id'] . '</a>';
				}
				echo __( 'Profile History:', 'psts' ) . ' <small>' . implode( ', ', $history_lines ) . '</small>';
			}
		} else if ( $old_info = get_blog_option( $blog_id, 'pypl_old_last_info' ) ) {

			if ( isset( $old_info['payment_date'] ) ) {
				$prev_billing = date_i18n( get_option( 'date_format' ), strtotime( $old_info['payment_date'] ) );
			}

			$profile_id = $old_info['subscr_id'];

			$supporter_paypal_site = get_site_option( "supporter_paypal_site" );
			$locale                = strtolower( empty( $supporter_paypal_site ) ? 'US' : $supporter_paypal_site );

			echo '<ul>';
			echo '<li>' . __( 'Old Supporter PayPal Gateway', 'psts' ) . '</li>';
			echo '<li>' . sprintf( __( 'PayPal Profile ID: <strong>%s</strong>', 'psts' ), '<a href="https://www.paypal.com/' . $locale . '/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id=' . $profile_id . '&mp_id=' . $profile_id . '&return_to=merchant&flag_flow=merchant#name1" target="_blank" title="View in PayPal &raquo;">' . $profile_id . '</a>' ) . '</li>';
			echo '<li>' . sprintf( __( 'Last Payment Date: <strong>%s</strong>', 'psts' ), $prev_billing ) . '</li>';
			echo '<li>' . sprintf( __( 'Last Payment Amount: <strong>%s</strong>', 'psts' ), $psts->format_currency( $old_info['mc_currency'], $old_info['payment_gross'] ) ) . '</li>';
			echo '<li>' . sprintf( __( 'Last Payment Transaction ID: <a target="_blank" href="https://www.paypal.com/vst/id=%s"><strong>%s</strong></a>', 'psts' ), $old_info['txn_id'], $old_info['txn_id'] ) . '</li>';
			echo '</ul>';

		} else if ( ProSites_Helper_Gateway::is_only_active( self::get_slug() ) ) {
			echo '<p>' . __( "This site is using an older gateway so their information is not accessible until the next payment comes through.", 'psts' ) . '</p>';
		}
	}

	function subscriber_info( $blog_id ) {
		global $psts;

		if ( ! ProSites_Helper_Gateway::is_last_gateway_used( $blog_id, self::get_slug() ) ) {
			return false;
		}

		$profile_id = $this->get_profile_id( $blog_id );

		if ( $profile_id ) {
			$resArray = PaypalApiHelper::GetRecurringPaymentsProfileDetails( $profile_id );

			//get user details
			if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {

				echo '<p><strong>' . stripslashes( $resArray['SUBSCRIBERNAME'] ) . '</strong><br />';

				if ( isset( $resArray['ACCT'] ) ) { //credit card
					echo stripslashes( $resArray['STREET'] ) . '<br />';
					echo stripslashes( $resArray['CITY'] ) . ', ' . stripslashes( $resArray['STATE'] ) . ' ' . stripslashes( $resArray['ZIP'] ) . '<br />';
					echo stripslashes( $resArray['COUNTRY'] ) . '</p>';

					echo '<p>' . stripslashes( $resArray['EMAIL'] ) . '</p>';
				}
			}
		} else if ( $old_info = get_blog_option( $blog_id, 'pypl_old_last_info' ) ) {

			echo '<p>';
			if ( isset( $old_info['first_name'] ) ) {
				echo '<strong>' . stripslashes( $old_info['first_name'] ) . ' ' . stripslashes( $old_info['last_name'] ) . '</strong>';
			}
			if ( isset( $old_info['address_street'] ) ) {
				echo '<br />' . stripslashes( $old_info['address_street'] );
			}
			if ( isset( $old_info['address_city'] ) ) {
				echo '<br />' . stripslashes( $old_info['address_city'] ) . ', ' . stripslashes( $old_info['address_state'] ) . ' ' . stripslashes( $old_info['address_zip'] ) . '<br />' . stripslashes( $old_info['address_country_code'] );
			} else {
				echo '<br />' . stripslashes( $old_info['residence_country'] );
			}
			echo '</p>';

			if ( isset( $old_info['payer_email'] ) ) {
				echo '<p>' . stripslashes( $old_info['payer_email'] ) . '</p>';
			}

		} else if ( ProSites_Helper_Gateway::is_only_active( self::get_slug() ) ) {
			echo '<p>' . __( "This site is using an older gateway so their information is not accessible until the next payment comes through.", 'psts' ) . '</p>';
		}
	}

	//return timestamp of next payment if subscription active, else return false
	function next_payment( $blog_id ) {
		global $psts;

		$next_billing = false;
		$profile_id   = $this->get_profile_id( $blog_id );
		if ( $profile_id ) {
			$resArray = PaypalApiHelper::GetRecurringPaymentsProfileDetails( $profile_id );
			if ( ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) && $resArray['STATUS'] == 'Active' ) {

				if ( ! empty( $resArray['NEXTBILLINGDATE'] ) ) {
					$next_billing = strtotime( $resArray['NEXTBILLINGDATE'] );
				}
			}
		}

		return $next_billing;
	}

	function modify_form( $blog_id ) {
		global $psts, $wpdb;

		if ( ! ProSites_Helper_Gateway::is_last_gateway_used( $blog_id, self::get_slug() ) ) {
			return false;
		}

		$active_member   = false;
		$canceled_member = false;

		//get subscription info
		$profile_id = $this->get_profile_id( $blog_id );

		if ( $profile_id ) {
			$resArray = PaypalApiHelper::GetRecurringPaymentsProfileDetails( $profile_id );

			if ( ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) && ( $resArray['STATUS'] == 'Active' || $resArray['STATUS'] == 'Suspended' ) ) {
				$active_member          = true;
				$next_payment_timestamp = strtotime( $resArray['NEXTBILLINGDATE'] );
			} else if ( ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) && $resArray['STATUS'] == 'Cancelled' ) {
				$canceled_member = true;
			}
		}

		$end_date = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );

		if ( $active_member ) {
			?>
			<h4><?php _e( 'Cancelations:', 'psts' ); ?></h4>
			<label><input type="radio" name="pypl_mod_action" value="cancel"/> <?php _e( 'Cancel Subscription Only', 'psts' ); ?>
				<small>(<?php printf( __( 'Their access will expire on %s', 'psts' ), $end_date ); ?>)</small>
			</label><br/>
			<?php
			if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
				$days_left = ( ( $next_payment_timestamp - time() ) / 60 / 60 / 24 );
				$period    = $wpdb->get_var( "SELECT term FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'" );
				$refund    = ( intval( $period ) ) ? round( ( $days_left / ( intval( $period ) * 30.4166 ) ) * $last_payment['amount'], 2 ) : 0;
				if ( $refund > $last_payment['amount'] ) {
					$refund = $last_payment['amount'];
				}
				?>
				<label><input type="radio" name="pypl_mod_action" value="cancel_refund"/> <?php printf( __( 'Cancel Subscription and Refund Full (%s) Last Payment', 'psts' ), $psts->format_currency( false, $last_payment['amount'] ) ); ?>
					<small>(<?php printf( __( 'Their access will expire on %s', 'psts' ), $end_date ); ?>)</small>
				</label><br/>
				<?php if ( $refund ) { ?>
					<label><input type="radio" name="pypl_mod_action" value="cancel_refund_pro"/> <?php printf( __( 'Cancel Subscription and Refund Prorated (%s) Last Payment', 'psts' ), $psts->format_currency( false, $refund ) ); ?>
						<small>(<?php printf( __( 'Their access will expire on %s', 'psts' ), $end_date ); ?>)</small>
					</label><br/>
				<?php } ?>

				<h4><?php _e( 'Refunds:', 'psts' ); ?></h4>
				<label><input type="radio" name="pypl_mod_action" value="refund"/> <?php printf( __( 'Refund Full (%s) Last Payment', 'psts' ), $psts->format_currency( false, $last_payment['amount'] ) ); ?>
					<small>(<?php _e( 'Their subscription and access will continue', 'psts' ); ?>)</small>
				</label><br/>
				<label><input type="radio" name="pypl_mod_action" value="partial_refund"/> <?php printf( __( 'Refund a Partial %s Amount of Last Payment', 'psts' ), $psts->format_currency() . '<input type="text" name="refund_amount" size="4" value="' . $last_payment['amount'] . '" />' ); ?>
					<small>(<?php _e( 'Their subscription and access will continue', 'psts' ); ?>)</small>
				</label><br/>

			<?php
			}
		} else if ( $canceled_member && ( $last_payment = $psts->last_transaction( $blog_id ) ) ) {
			?>
			<h4><?php _e( 'Refunds:', 'psts' ); ?></h4>
			<label><input type="radio" name="pypl_mod_action" value="refund"/> <?php printf( __( 'Refund Full (%s) Last Payment', 'psts' ), $psts->format_currency( false, $last_payment['amount'] ) ); ?>
				<small>(<?php _e( 'Their subscription and access will continue', 'psts' ); ?>)</small>
			</label><br/>
			<label><input type="radio" name="pypl_mod_action" value="partial_refund"/> <?php printf( __( 'Refund a Partial %s Amount of Last Payment', 'psts' ), $psts->format_currency() . '<input type="text" name="refund_amount" size="4" value="' . $last_payment['amount'] . '" />' ); ?>
				<small>(<?php _e( 'Their subscription and access will continue', 'psts' ); ?>)</small>
			</label><br/>
		<?php
		} else {
			?>
			<p>
				<small style="color:red;"><?php _e( 'Note: This <strong>will not</strong> cancel their PayPal subscription or refund any payments made. You will have to do it from your PayPal account for this site.', 'psts' ); ?></small>
			</p>
		<?php
		}
	}

	function process_modify( $blog_id ) {
		global $psts, $current_user, $wpdb;

		if ( isset( $_POST['pypl_mod_action'] ) ) {

			$profile_id = $this->get_profile_id( $blog_id );

			//handle different cases
			switch ( $_POST['pypl_mod_action'] ) {

				case 'cancel':
					$end_date = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );

					if ( $profile_id ) {
						$resArray = PaypalApiHelper::ManageRecurringPaymentsProfileStatus( $profile_id, 'Cancel', sprintf( __( 'Your subscription has been cancelled by an admin. You should continue to have access until %s', 'psts' ), $end_date ) );
					}

					if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {

						//record stat
						$psts->record_stat( $blog_id, 'cancel' );

						$psts->log_action( $blog_id, sprintf( __( 'Subscription successfully cancelled by %1$s. They should continue to have access until %2$s', 'psts' ), $current_user->display_name, $end_date ) );
						$success_msg = sprintf( __( 'Subscription successfully cancelled. They should continue to have access until %s.', 'psts' ), $end_date );

					} else {
						$psts->log_action( $blog_id, sprintf( __( 'Attempt to Cancel Subscription by %1$s failed with an error: %2$s', 'psts' ), $current_user->display_name, $this->parse_error_string( $resArray ) ) );
						$error_msg = sprintf( __( 'Whoops, PayPal returned an error when attempting to cancel the subscription. Nothing was completed: %s', 'psts' ), $this->parse_error_string( $resArray ) );
					}
					break;

				case 'cancel_refund':
					if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
						$end_date = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
						$refund   = $last_payment['amount'];

						if ( $profile_id ) {
							$resArray = PaypalApiHelper::ManageRecurringPaymentsProfileStatus( $profile_id, 'Cancel', sprintf( __( 'Your subscription has been cancelled by an admin. You should continue to have access until %s.', 'psts' ), $end_date ) );
						}

						if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {

							//record stat
							$psts->record_stat( $blog_id, 'cancel' );

							//refund last transaction
							$resArray2 = PaypalApiHelper::RefundTransaction( $last_payment['txn_id'], false, __( 'This is a full refund of your last subscription payment.', 'psts' ) );
							if ( $resArray2['ACK'] == 'Success' || $resArray2['ACK'] == 'SuccessWithWarning' ) {
								$psts->log_action( $blog_id, sprintf( __( 'Subscription cancelled and full (%1$s) refund of last payment completed by %2$s. They should continue to have access until %3$s.', 'psts' ), $psts->format_currency( false, $refund ), $current_user->display_name, $end_date ) );
								$success_msg = sprintf( __( 'Subscription cancelled and full (%1$s) refund of last payment were successfully completed. They should continue to have access until %2$s.', 'psts' ), $psts->format_currency( false, $refund ), $end_date );
								$psts->record_refund_transaction( $blog_id, $last_payment['txn_id'], $refund );
							} else {
								$psts->log_action( $blog_id, sprintf( __( 'Subscription cancelled, but full (%1$s) refund of last payment by %2$s returned an error: %3$s', 'psts' ), $psts->format_currency( false, $refund ), $current_user->display_name, $this->parse_error_string( $resArray ) ) );
								$error_msg = sprintf( __( 'Subscription cancelled, but full (%1$s) refund of last payment returned an error: %2$s', 'psts' ), $psts->format_currency( false, $refund ), $this->parse_error_string( $resArray ) );
							}
						} else {
							$psts->log_action( $blog_id, sprintf( __( 'Attempt to Cancel Subscription and Refund Full (%1$s) Last Payment by %2$s failed with an error: ', 'psts' ), $psts->format_currency( false, $refund ), $this->parse_error_string( $resArray ) ) );
							$error_msg = sprintf( __( 'Whoops, PayPal returned an error when attempting to cancel the subscription. Nothing was completed: %s', 'psts' ), $this->parse_error_string( $resArray ) );
						}
					}
					break;

				case 'cancel_refund_pro':
					if ( $last_payment = $psts->last_transaction( $blog_id ) ) {

						$refund = '';
						//get next payment date
						$resArray = PaypalApiHelper::GetRecurringPaymentsProfileDetails( $profile_id );
						if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {
							$next_payment_timestamp = strtotime( $resArray['NEXTBILLINGDATE'] );
						} else {
							$psts->log_action( $blog_id, sprintf( __( 'Attempt to Cancel Subscription and Refund Prorated (%1$s) Last Payment by %2$s failed with an error: %3$s', 'psts' ), $psts->format_currency( false, $refund ), $current_user->display_name, $this->parse_error_string( $resArray ) ) );
							$error_msg = sprintf( __( 'Whoops, PayPal returned an error when attempting to cancel the subscription. Nothing was completed: %s', 'psts' ), $this->parse_error_string( $resArray ) );
							break;
						}
						$days_left = ( ( $next_payment_timestamp - time() ) / 60 / 60 / 24 );
						$period    = $wpdb->get_var( "SELECT term FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'" );
						$refund    = ( intval( $period ) ) ? round( ( $days_left / ( intval( $period ) * 30.4166 ) ) * $last_payment['amount'], 2 ) : 0;
						if ( $refund > $last_payment['amount'] ) {
							$refund = $last_payment['amount'];
						}

						if ( $profile_id ) {
							$resArray = PaypalApiHelper::ManageRecurringPaymentsProfileStatus( $profile_id, 'Cancel', __( 'Your subscription has been cancelled by an admin.', 'psts' ) );
						}

						if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {

							//record stat
							$psts->record_stat( $blog_id, 'cancel' );

							//refund last transaction
							$resArray2 = PaypalApiHelper::RefundTransaction( $last_payment['txn_id'], $refund, __( 'This is a prorated refund of the unused portion of your last subscription payment.', 'psts' ) );
							if ( $resArray2['ACK'] == 'Success' || $resArray2['ACK'] == 'SuccessWithWarning' ) {
								$psts->log_action( $blog_id, sprintf( __( 'Subscription cancelled and a prorated (%1$s) refund of last payment completed by %2$s', 'psts' ), $psts->format_currency( false, $refund ), $current_user->display_name ) );
								$success_msg = sprintf( __( 'Subscription cancelled and a prorated (%s) refund of last payment were successfully completed.', 'psts' ), $psts->format_currency( false, $refund ) );
								$psts->record_refund_transaction( $blog_id, $last_payment['txn_id'], $refund );
							} else {
								$psts->log_action( $blog_id, sprintf( __( 'Subscription cancelled, but prorated (%1$s) refund of last payment by %2$s returned an error: %3$s', 'psts' ), $psts->format_currency( false, $refund ), $current_user->display_name, $this->parse_error_string( $resArray ) ) );
								$error_msg = sprintf( __( 'Subscription cancelled, but prorated (%1$s) refund of last payment returned an error: %2$s', 'psts' ), $psts->format_currency( false, $refund ), $this->parse_error_string( $resArray ) );
							}
						} else {
							$psts->log_action( $blog_id, sprintf( __( 'Attempt to Cancel Subscription and Refund Prorated (%1$s) Last Payment by %2$s failed with an error: %3$s', 'psts' ), $psts->format_currency( false, $refund ), $current_user->display_name, $this->parse_error_string( $resArray ) ) );
							$error_msg = sprintf( __( 'Whoops, PayPal returned an error when attempting to cancel the subscription. Nothing was completed: %s', 'psts' ), $this->parse_error_string( $resArray ) );
						}
					}
					break;

				case 'refund':
					if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
						$refund = $last_payment['amount'];

						//refund last transaction
						$resArray2 = PaypalApiHelper::RefundTransaction( $last_payment['txn_id'], false, __( 'This is a full refund of your last subscription payment.', 'psts' ) );
						if ( $resArray2['ACK'] == 'Success' || $resArray2['ACK'] == 'SuccessWithWarning' ) {
							$psts->log_action( $blog_id, sprintf( __( 'A full (%1$s) refund of last payment completed by %2$s The subscription was not cancelled.', 'psts' ), $psts->format_currency( false, $refund ), $current_user->display_name ) );
							$success_msg = sprintf( __( 'A full (%s) refund of last payment was successfully completed. The subscription was not cancelled.', 'psts' ), $psts->format_currency( false, $refund ) );
							$psts->record_refund_transaction( $blog_id, $last_payment['txn_id'], $refund );
						} else {
							$psts->log_action( $blog_id, sprintf( __( 'Attempt to issue a full (%1$s) refund of last payment by %2$s returned an error: %3$s', 'psts' ), $psts->format_currency( false, $refund ), $current_user->display_name, $this->parse_error_string( $resArray2 ) ) );
							$error_msg = sprintf( __( 'Attempt to issue a full (%1$s) refund of last payment returned an error: %2$s', 'psts' ), $psts->format_currency( false, $refund ), $this->parse_error_string( $resArray ) );
						}
					}
					break;

				case 'partial_refund':
					if ( ( $last_payment = $psts->last_transaction( $blog_id ) ) && round( $_POST['refund_amount'], 2 ) ) {
						$refund = ( round( $_POST['refund_amount'], 2 ) < $last_payment['amount'] ) ? round( $_POST['refund_amount'], 2 ) : $last_payment['amount'];

						//refund last transaction
						$resArray2 = PaypalApiHelper::RefundTransaction( $last_payment['txn_id'], false, __( 'This is a partial refund of your last payment.', 'psts' ) );
						if ( $resArray2['ACK'] == 'Success' || $resArray2['ACK'] == 'SuccessWithWarning' ) {
							$psts->log_action( $blog_id, sprintf( __( 'A partial (%1$s) refund of last payment completed by %2$s The subscription was not cancelled.', 'psts' ), $psts->format_currency( false, $refund ), $current_user->display_name ) );
							$success_msg = sprintf( __( 'A partial (%s) refund of last payment was successfully completed. The subscription was not cancelled.', 'psts' ), $psts->format_currency( false, $refund ) );
							$psts->record_refund_transaction( $blog_id, $last_payment['txn_id'], $refund );
						} else {
							$psts->log_action( $blog_id, sprintf( __( 'Attempt to issue a partial (%1$s) refund of last payment by %2$s returned an error: %3$s', 'psts' ), $psts->format_currency( false, $refund ), $current_user->display_name, $this->parse_error_string( $resArray2 ) ) );
							$error_msg = sprintf( __( 'Attempt to issue a partial (%1$s) refund of last payment returned an error: %2$s', 'psts' ), $psts->format_currency( false, $refund ), $this->parse_error_string( $resArray2 ) );
						}
					}
					break;
			}

			//display resulting message
			if ( $success_msg ) {
				echo '<div class="updated fade"><p>' . $success_msg . '</p></div>';
			} else if ( $error_msg ) {
				echo '<div class="error fade"><p>' . $error_msg . '</p></div>';
			}
		}
	}

	//handle transferring pro status from one blog to another
	function process_transfer( $from_id, $to_id ) {
		global $psts, $wpdb;

		$profile_id = $this->get_profile_id( $from_id );
		$current    = $wpdb->get_row( "SELECT * FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$to_id'" );
		$custom     = PSTS_PYPL_PREFIX . '_' . $to_id . '_' . $current->level . '_' . $current->term . '_' . $current->amount . '_' . $psts->get_setting( 'pypl_currency' ) . '_' . time();

		//update the profile id in paypal so that future payments are applied to the new site
		PaypalApiHelper::UpdateRecurringPaymentsProfile( $profile_id, $custom );

		//move profileid to new blog
		$this->set_profile_id( $to_id, $profile_id );

		//delete the old profilid
		$trans_meta = get_blog_option( $from_id, 'psts_paypal_profile_id' );

		unset( $trans_meta[ $profile_id ] );

		update_blog_option( $from_id, 'psts_paypal_profile_id', $trans_meta );
	}

	//js to be printed only on checkout page
	function checkout_js() {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function ($) {
				$('form').submit(function () {
					$('#cc_paypal_checkout').hide();
					$('#paypal_processing').show();
				});
				$("a#pypl_cancel").click(function (e) {
					if (!confirm("<?php echo __('Please note that if you cancel your subscription you will not be immune to future price increases. The price of un-canceled subscriptions will never go up!\n\nAre you sure you really want to cancel your subscription?\nThis action cannot be undone!', 'psts'); ?>"))
						e.preventDefault();
				});
			});
		</script><?php
	}

	/**
	 * Handle the IPN sent by Paypal
	 */
	function ipn_handler() {
		global $psts;

		if ( ! isset( $_POST['rp_invoice_id'] ) && ! isset( $_POST['custom'] ) ) {

			die( 'Error: Missing POST variables. Identification is not possible.' );

		} else if ( defined( 'PSTS_IPN_PASSWORD' ) && $_POST['inc_pass'] != PSTS_IPN_PASSWORD ) {

			header( "HTTP/1.1 401 Authorization Required" );
			die( 'Error: Missing a valid IPN forwarding password. Identification is not possible.' );

		} else {

			//if not using an IPN forwarder check the request
			if ( ! defined( 'PSTS_IPN_PASSWORD' ) ) {
				if ( $psts->get_setting( 'pypl_status' ) == 'live' ) {
					$paypal_domain = 'https://www.paypal.com/cgi-bin/webscr';
				} else {
					$paypal_domain = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
				}

				$req = 'cmd=_notify-validate';
				foreach ( $_POST as $k => $v ) {
					if ( get_magic_quotes_gpc() ) {
						$v = stripslashes( $v );
					}
					$req .= '&' . $k . '=' . urlencode( $v );
				}

				$args['user-agent']  = "Pro Sites: http://premium.wpmudev.org/project/pro-sites | PayPal Express/Pro Gateway";
				$args['body']        = $req;
				$args['sslverify']   = false;
				$args['timeout']     = 60;
				$args['httpversion'] = '1.1';

				//use built in WP http class to work with most server setups
				$response = wp_remote_post( $paypal_domain, $args );

				//check results
				if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 || $response['body'] != 'VERIFIED' ) {
					header( "HTTP/1.1 503 Service Unavailable" );
					die( __( 'There was a problem verifying the IPN string with PayPal. Please try again.', 'psts' ) );
				}
			}

			$custom = ( isset( $_POST['rp_invoice_id'] ) ) ? $_POST['rp_invoice_id'] : $_POST['custom'];

			// get custom field values
			@list( $pre, $blog_id, $level, $period, $amount, $currency, $timestamp, $activation_key ) = explode( '_', $custom );

			if ( empty( $blog_id ) || $blog_id == 0 ) {
				//Get it from Pro sites table, if not there, try to get it from signup table
				$blog_id = self::blog_id_from_activation_key( $activation_key );
			}

			// process PayPal response
			$new_status = false;

			$profile_string = ( isset( $_POST['recurring_payment_id'] ) ) ? ' - ' . $_POST['recurring_payment_id'] : '';

			$payment_status = ( isset( $_POST['initial_payment_status'] ) ) ? $_POST['initial_payment_status'] : $_POST['payment_status'];

			switch ( $payment_status ) {

				case 'Canceled-Reversal':
					$psts->log_action( $blog_id, sprintf( __( 'PayPal IPN "%s" received: A reversal has been canceled; for example, when you win a dispute and the funds for the reversal have been returned to you.', 'psts' ), $payment_status ) . $profile_string );
					break;

				case 'Expired':
					$psts->log_action( $blog_id, sprintf( __( 'PayPal IPN "%s" received: The authorization period for this payment has been reached.', 'psts' ), $payment_status ) . $profile_string );
					break;

				case 'Voided':
					$psts->log_action( $blog_id, sprintf( __( 'PayPal IPN "%s" received: An authorization for this transaction has been voided.', 'psts' ), $payment_status ) . $profile_string );
					break;

				case 'Failed':
					$psts->log_action( $blog_id, sprintf( __( 'PayPal IPN "%s" received: The payment has failed. This happens only if the payment was made from your customer\'s bank account.', 'psts' ), $payment_status ) . $profile_string );
					break;

				case 'Partially-Refunded':
					$psts->log_action( $blog_id, sprintf( __( 'PayPal IPN "%s" received: The payment has been partially refunded with %s.', 'psts' ), $payment_status, $psts->format_currency( $_POST['mc_currency'], $_POST['mc_gross'] ) ) . $profile_string );
					$psts->record_refund_transaction( $blog_id, $_POST['txn_id'], abs( $_POST['mc_gross'] ) );
					break;

				case 'In-Progress':
					$psts->log_action( $blog_id, sprintf( __( 'PayPal IPN "%s" received: The transaction has not terminated, e.g. an authorization may be awaiting completion.', 'psts' ), $payment_status ) . $profile_string );
					break;

				case 'Reversed':
					$status          = __( 'A payment was reversed due to a chargeback or other type of reversal. The funds have been removed from your account balance: ', 'psts' );
					$reverse_reasons = array(
						'none'                     => '',
						'chargeback'               => __( 'A reversal has occurred on this transaction due to a chargeback by your customer.', 'psts' ),
						'chargeback_reimbursement' => __( 'A reversal has occurred on this transaction due to a reimbursement of a chargeback.', 'psts' ),
						'chargeback_settlement'    => __( 'A reversal has occurred on this transaction due to settlement of a chargeback.', 'psts' ),
						'guarantee'                => __( 'A reversal has occurred on this transaction due to your customer triggering a money-back guarantee.', 'psts' ),
						'buyer_complaint'          => __( 'A reversal has occurred on this transaction due to a complaint about the transaction from your customer.', 'psts' ),
						'unauthorized_claim'       => __( 'A reversal has occurred on this transaction due to the customer claiming it as an unauthorized payment.', 'psts' ),
						'refund'                   => __( 'A reversal has occurred on this transaction because you have given the customer a refund.', 'psts' ),
						'other'                    => __( 'A reversal has occurred on this transaction due to an unknown reason.', 'psts' )
					);
					$status .= $reverse_reasons[ $_POST["reason_code"] ];

					$psts->log_action( $blog_id, sprintf( __( 'PayPal IPN "%s" received: %s', 'psts' ), $payment_status, $status ) . $profile_string );

					$psts->withdraw( $blog_id, $period );
					$psts->record_refund_transaction( $blog_id, $_POST['txn_id'], abs( $_POST['mc_gross'] ) );
					break;

				case 'Refunded':

					$psts->log_action( $blog_id, sprintf( __( 'PayPal IPN "%s" received: You refunded the payment with %s.', 'psts' ), $payment_status, $psts->format_currency( $_POST['mc_currency'], $_POST['mc_gross'] ) ) . $profile_string );
					$psts->record_refund_transaction( $blog_id, $_POST['txn_id'], abs( $_POST['mc_gross'] ) );
					break;

				case 'Denied':

					$psts->log_action( $blog_id, sprintf( __( 'PayPal IPN "%s" received: You denied the payment when it was marked as pending.', 'psts' ), $payment_status ) . $profile_string );
					$psts->withdraw( $blog_id, $period );
					break;

				case 'Completed':
				case 'Processed':
					// case: successful payment
					$is_trialing = ( isset( $_POST['period_type'] ) && trim( $_POST['period_type'] ) == 'Trial' ) ? true : false;
					$recurring   = $psts->get_setting( 'recurring_subscriptions', true );

					//Activate the blog
					if ( empty( $blog_id ) || $blog_id === 0 ) {
						$result  = ProSites_Helper_Registration::activate_blog( $activation_key, $is_trialing, $period, $level );
						$blog_id = ! empty( $result['blog_id'] ) ? $result['blog_id'] : '';
					}

					//receipts and record new transaction
					if ( ! $is_trialing && $recurring && ! empty( $blog_id ) && $blog_id !== 0 ) {

						if ( $_POST['txn_type'] == 'recurring_payment' || $_POST['txn_type'] == 'express_checkout' || $_POST['txn_type'] == 'web_accept' ) {
							$psts->record_transaction( $blog_id, $_POST['txn_id'], $_POST['mc_gross'] );
							$psts->log_action( $blog_id, sprintf( __( 'PayPal IPN "%s" received: %s %s payment received, transaction ID %s', 'psts' ), $payment_status, $psts->format_currency( $_POST['mc_currency'], $_POST['mc_gross'] ), $_POST['txn_type'], $_POST['txn_id'] ) . $profile_string );

							//extend only if a recurring payment, first payments are handled below
							if ( ! get_blog_option( $blog_id, 'psts_waiting_step' ) ) {
								$psts->extend( $blog_id, $period, self::get_slug(), $level, $_POST['mc_gross'] );
							}

							//in case of new member send notification
							if ( get_blog_option( $blog_id, 'psts_waiting_step' ) && $_POST['txn_type'] == 'express_checkout' ) {
								$psts->extend( $blog_id, $period, self::get_slug(), $level, $_POST['mc_gross'] );
								$psts->email_notification( $blog_id, 'success' );
								$psts->record_stat( $blog_id, 'signup' );
								update_blog_option( $blog_id, 'psts_waiting_step', 0 );
							}
							$psts->email_notification( $blog_id, 'receipt' );
						}
					}

					break;

				case 'Pending':
					// case: payment is pending
					$reason = @$_POST['pending_reason'];
					$psts->log_action( $blog_id, sprintf( __( 'PayPal IPN "%s" received: Last payment is pending (%s). Reason: %s', 'psts' ), $payment_status, $_POST['txn_id'], self::$pending_str[ $reason ] ) . $profile_string );
					break;

				default:
					// case: various error cases

			}

			// handle exceptions from the subscription specific fields
			if ( in_array( $_POST['txn_type'], array( /*'subscr_cancel', */
				'subscr_failed',
				'subscr_eot'
			) )
			) {
				$psts->log_action( $blog_id, sprintf( __( 'PayPal subscription IPN "%s" received.', 'psts' ), $_POST['txn_type'] ) . $profile_string, $blog_id );
			}

			//new subscriptions (after cancelation)
			if ( $_POST['txn_type'] == 'recurring_payment_profile_created' ) {

				$psts->log_action( $blog_id, sprintf( __( 'PayPal subscription IPN "%s" received.', 'psts' ), $_POST['txn_type'] ) . $profile_string );

				//save new profile_id
				$this->set_profile_id( $blog_id, $_POST['recurring_payment_id'] );

				//failed initial payment
				if ( $_POST['initial_payment_status'] == 'Failed' ) {
					$psts->email_notification( $blog_id, 'failed' );
				}
			}

			//cancelled subscriptions
			if ( $_POST['txn_type'] == 'subscr_cancel' ) {
				$psts->log_action( $blog_id, sprintf( __( 'PayPal subscription IPN "%s" received. The subscription has been canceled.', 'psts' ), $_POST['txn_type'] ) . $profile_string );

				//$psts->email_notification($blog_id, 'canceled');
				$psts->record_stat( $blog_id, 'cancel' );
			}
		}
		exit;
	}

	public static function cancel_subscription( $blog_id, $display_message = false ) {
		global $psts, $current_user, $current_site;

		$site_name = $current_site->site_name;

		//check if pro/express user
		if ( $profile_id = self::get_profile_id( $blog_id ) ) {

			$resArray = PaypalApiHelper::ManageRecurringPaymentsProfileStatus( $profile_id, 'Cancel', __( 'Your subscription was canceled because the blog was deleted.', 'psts' ) );

			if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {
				//record stat
				$psts->record_stat( $blog_id, 'cancel' );

				$psts->email_notification( $blog_id, 'canceled' );
				update_blog_option( $blog_id, 'psts_is_canceled', 1 );

				$end_date = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
				$psts->log_action( $blog_id, sprintf( __( 'Subscription successfully cancelled by %1$s. They should continue to have access until %2$s', 'psts' ), $current_user->display_name, $end_date ) );

				//Do not display message for add action
				if ( $display_message ) {
					self::$cancel_message = '<div id="message" class="updated fade"><p>' . sprintf( __( 'Your %1$s subscription has been canceled. You should continue to have access until %2$s.', 'psts' ), $site_name . ' ' . $psts->get_setting( 'rebrand' ), $end_date ) . '</p></div>';

				}
			}
		}
	}

	//record last payment for Blog id or domain
	public static function set_profile_id( $blog_id, $profile_id, $domain = false ) {
		global $psts;
		if ( ! empty( $blog_id ) ) {
			$trans_meta = get_blog_option( $blog_id, 'psts_paypal_profile_id' );

			$trans_meta[ $profile_id ]['profile_id'] = $profile_id;
			$trans_meta[ $profile_id ]['timestamp']  = time();
			update_blog_option( $blog_id, 'psts_paypal_profile_id', $trans_meta );
		} else {
			//Store transaction details in signup meta
			$signup_meta                                                        = $psts->get_signup_meta( $domain );
			$signup_meta['psts_paypal_profile_id'][ $profile_id ]['profile_id'] = $profile_id;
			$signup_meta['psts_paypal_profile_id'][ $profile_id ]['timestamp']  = time();
			$psts->update_signup_meta( $signup_meta, $domain );
		}
	}

	public static function get_profile_id( $blog_id, $history = false, $domain = false ) {
		global $psts;
		$trans_meta = '';
		if ( ! empty( $blog_id ) ) {
			$trans_meta = get_blog_option( $blog_id, 'psts_paypal_profile_id' );

		} else {
			$signup_meta = $psts->get_signup_meta( $domain );
			if ( isset( $signup_meta['psts_paypal_profile_id'] ) ) {
				$trans_meta = $signup_meta['psts_paypal_profile_id'];
			}
		}

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

	public static function get_free_trial_desc( $trial_days ) {
		return ' (billed after ' . $trial_days . ' day free trial)';
	}

	public static function parse_error_string( $resArray, $sep = ', ' ) {
		$errors = array();
		for ( $i = 0; $i < 10; $i ++ ) {
			if ( isset( $resArray["L_LONGMESSAGE$i"] ) ) {
				$errors[] = $resArray["L_LONGMESSAGE$i"];
			}
		}

		return implode( $sep, $errors );
	}

	public static function get_name() {
		return array(
			'paypal' => __( 'PayPal Express/Pro', 'psts' ),
		);
	}

	/**
	 * Display the Paypal Payment Options
	 *
	 * @param $args
	 * @param $blog_id
	 * @param $domain
	 * @param bool $prefer_cc
	 *
	 * @return string|void
	 */
	public static function render_gateway( $render_data = array(), $args, $blog_id, $domain, $prefer_cc = true ) {

		global $psts, $current_site;
		$content   = '';
		$site_name = $current_site->site_name;
		$img_base  = $psts->plugin_url . 'images/';

		$button_url = "https://fpdbs.paypal.com/dynamicimageweb?cmd=_dynamic-image&locale=" . get_locale();
		$button_url = apply_filters( 'psts_pypl_checkout_image_url', $button_url );

		$period = isset( $args['period'] ) && ! empty( $args['period'] ) ? $args['period'] : 1;
		$level  = isset( $render_data['new_blog_details'] ) && isset( $render_data['new_blog_details']['level'] ) ? (int) $render_data['new_blog_details']['level'] : 0;
		$level  = isset( $render_data['upgraded_blog_details'] ) && isset( $render_data['upgraded_blog_details']['level'] ) ? (int) $render_data['upgraded_blog_details']['level'] : $level;

		$content .= '<form action="' . $psts->checkout_url( $blog_id ) . '" method="post" autocomplete="off" id="paypal-payment-form">

			<input type="hidden" name="level" value="' . $level . '" />
			<input type="hidden" name="period" value="' . $period . '" />
			<input type="hidden" name="tax-type" value="none" />';

		if ( isset( $_POST['new_blog'] ) || ( isset( $_GET['action'] ) && 'new_blog' == $_GET['action'] ) ) {
			$content .= '<input type="hidden" name="new_blog" value="1" />';
		}

		if ( isset( $_GET['bid'] ) ) {
			$content .= '<input type="hidden" name="bid" value="' . (int) $_GET['bid'] . '" />';
		}

		// This is a new blog
		if ( isset( $render_dataf['blog_activation_key'] ) ) {
			$content .= '<input type="hidden" name="activation" value="' . $render_data['blog_activation_key'] . '" />';

			if ( isset( $render_data['new_blog_details'] ) ) {
				$user_name  = $render_data['new_blog_details']['username'];
				$user_email = $render_data['new_blog_details']['email'];
				$blogname   = $render_data['new_blog_details']['blogname'];
				$blog_title = $render_data['new_blog_details']['title'];

				$content .= '<input type="hidden" name="blog_username" value="' . $user_name . '" />';
				$content .= '<input type="hidden" name="blog_email" value="' . $user_email . '" />';
				$content .= '<input type="hidden" name="blog_name" value="' . $blogname . '" />';
				$content .= '<input type="hidden" name="blog_title" value="' . $blog_title . '" />';
			}
		}

		$content .= '<div id="psts-paypal-checkout">
			<h2>' . __( 'Checkout With PayPal', 'psts' ) . '</h2>';
		echo "<pre>";
		print_r( $psts->errors );
		echo "</pre>";
		$errmsg = ! empty( $psts->errors ) ? $psts->errors->get_error_message( 'general' ) : false;
		echo "<pre> Error Message";
		print_r( $errmsg );
		echo "</pre>";
		if ( $errmsg ) {
			$content .= '<div id="psts-processcard-error" class="psts-error">' . $errmsg . '</div>';
		}

		$content .= '<input type="image" src="' . $button_url . '" border="0" name="paypal_checkout" alt="' . __( 'PayPal - The safer, easier way to pay online!', 'psts' ) . '">
			</div>';

		if ( $psts->get_setting( 'pypl_enable_pro' ) ) {

			//clean up $_POST
			$cc_cardtype  = isset( $_POST['cc_card-type'] ) ? $_POST['cc_card-type'] : '';
			$cc_number    = isset( $_POST['cc_number'] ) ? stripslashes( $_POST['cc_number'] ) : '';
			$cc_month     = isset( $_POST['cc_month'] ) ? $_POST['cc_month'] : '';
			$cc_year      = isset( $_POST['cc_year'] ) ? $_POST['cc_year'] : '';
			$cc_firstname = isset( $_POST['cc_firstname'] ) ? stripslashes( $_POST['cc_firstname'] ) : '';
			$cc_lastname  = isset( $_POST['cc_lastname'] ) ? stripslashes( $_POST['cc_lastname'] ) : '';
			$cc_address   = isset( $_POST['cc_address'] ) ? stripslashes( $_POST['cc_address'] ) : '';
			$cc_address2  = isset( $_POST['cc_address2'] ) ? stripslashes( $_POST['cc_address2'] ) : '';
			$cc_city      = isset( $_POST['cc_city'] ) ? stripslashes( $_POST['cc_city'] ) : '';
			$cc_state     = isset( $_POST['cc_state'] ) ? stripslashes( $_POST['cc_state'] ) : '';
			$cc_zip       = isset( $_POST['cc_zip'] ) ? stripslashes( $_POST['cc_zip'] ) : '';
			$cc_country   = isset( $_POST['cc_country'] ) ? stripslashes( $_POST['cc_country'] ) : '';

			$content .= '<div id="psts-cc-checkout">
			<h2>' . __( 'Or Pay Directly By Credit Card', 'psts' ) . '</h2>';
			$content .= self::nonce_field();
			$content .= '<input type="hidden" name="cc_form" value="1" />
			<table id="psts-cc-table">
				<tbody>
					<tr>
						<td colspan="2">
							<h3>' . __( 'Credit Card Info:', 'psts' ) . '</h3>
						</td>
					</tr>';

			$content = apply_filters( 'psts_pp_pro_form_before_first_input', $content, $psts );

			$content .= '<!-- Credit Card Type -->
			         <tr>
						<td class="pypl_label" align="right">' . __( 'Card Type:', 'psts' ) . '&nbsp;</td>
						<td>';
			$errmsg = ! empty( $psts->errors ) ? $psts->errors->get_error_message( 'card-type' ) : false;
			if ( $errmsg ) {
				$content .= '<div class="psts-error">' . $errmsg . '</div>';
			}
			$content .= '<label class="cc-image" title="Visa"><input type="radio" name="cc_card-type" value="Visa"' . ( ( $cc_cardtype == 'Visa' ) ? ' checked="checked"' : '' ) . ' /><img src="' . $img_base . 'visa.png" alt="Visa" /></label>
			  <label class="cc-image" title="MasterCard"><input type="radio" name="cc_card-type" value="MasterCard"' . ( ( $cc_cardtype == 'MasterCard' ) ? ' checked="checked"' : '' ) . ' /><img src="' . $img_base . 'mc.png" alt="MasterCard" /></label>
			  <label class="cc-image" title="American Express"><input type="radio" name="cc_card-type" value="Amex"' . ( ( $cc_cardtype == 'Amex' ) ? ' checked="checked"' : '' ) . ' /><img src="' . $img_base . 'amex.png" alt="American Express" /></label>
			  <label class="cc-image" title="Discover"><input type="radio" name="cc_card-type" value="Discover"' . ( ( $cc_cardtype == 'Discover' ) ? ' checked="checked"' : '' ) . ' /><img src="' . $img_base . 'discover.png" alt="Discover" /></label>
			  </td>
			</tr>

		    <tr>
				<td class="pypl_label" align="right">' . __( 'Card Number:', 'psts' ) . '&nbsp;</td>
				<td>';
			$errmsg = ! empty( $psts->errors ) ? $psts->errors->get_error_message( 'number' ) : false;
			if ( $errmsg ) {
				$content .= '<div class="psts-error">' . $errmsg . '</div>';
			}
			$content .= '<input name="cc_number" type="text" class="cctext" value="' . esc_attr( $cc_number ) . '" size="23" />
				</td>
			</tr>

			<tr>
				<td class="pypl_label" align="right">' . __( 'Expiration Date:', 'psts' ) . '&nbsp;</td>
				<td valign="middle">';
			$errmsg = ! empty( $psts->errors ) ? $psts->errors->get_error_message( 'expiration' ) : false;
			if ( $errmsg ) {
				$content .= '<div class="psts-error">' . $errmsg . '</div>';
			}
			$content .= '<select name="cc_month">' . self::month_dropdown( $cc_month ) . '</select>&nbsp;/&nbsp;<select name="cc_year">' . self::year_dropdown( $cc_year ) . '</select>
				</td>
			</tr>

			<!-- Card Security Code -->
			<tr>
				<td class="pypl_label" align="right"><nobr>' . __( 'Card Security Code:', 'psts' ) . '</nobr>&nbsp;</td>
				<td valign="middle">';
			$errmsg = ! empty( $psts->errors ) ? $psts->errors->get_error_message( 'cvv2' ) : false;
			if ( $errmsg ) {
				$content .= '<div class="psts-error">' . $errmsg . '</div>';
			}
			$content .= '<label><input name="cc_cvv2" size="5" maxlength="4" type="password" class="cctext" title="' . __( 'Please enter a valid card security code. This is the 3 digits on the signature panel, or 4 digits on the front of Amex cards.', 'psts' ) . '" />
				<img src="' . $img_base . 'buy-cvv.gif" height="27" width="42" title="' . __( 'Please enter a valid card security code. This is the 3 digits on the signature panel, or 4 digits on the front of Amex cards.', 'psts' ) . '" /></label>
				</td>
			</tr>

			<tr>
				<td colspan="2"><h3>' . __( 'Billing Address:', 'psts' ) . '</h3></td>
			</tr>
			<tr>
				<td class="pypl_label" align="right">' . __( 'First Name:', 'psts' ) . '*&nbsp;</td>
				<td>';
			$errmsg = ! empty( $psts->errors ) ? $psts->errors->get_error_message( 'firstname' ) : false;
			if ( $errmsg ) {
				$content .= '<div class="psts-error">' . $errmsg . '</div>';
			}
			$content .= '<input name="cc_firstname" type="text" class="cctext" value="' . esc_attr( $cc_firstname ) . '" size="25" /> </td>
			</tr>
			<tr>
				<td class="pypl_label" align="right">' . __( 'Last Name:', 'psts' ) . '*&nbsp;</td><td>';
			$errmsg = ! empty( $psts->errors ) ? $psts->errors->get_error_message( 'lastname' ) : false;
			if ( $errmsg ) {
				$content .= '<div class="psts-error">' . $errmsg . '</div>';
			}
			$content .= '<input name="cc_lastname" type="text" class="cctext" value="' . esc_attr( $cc_lastname ) . '" size="25" /></td>
			</tr>
			<tr>

				<td class="pypl_label" align="right">' . __( 'Address:', 'psts' ) . '*&nbsp;</td><td>';
			$errmsg = ! empty( $psts->errors ) ? $psts->errors->get_error_message( 'address' ) : false;
			if ( $errmsg ) {
				$content .= '<div class="psts-error">' . $errmsg . '</div>';
			}
			$content .= '<input size="45" name="cc_address" type="text" class="cctext" value="' . esc_attr( $cc_address ) . '" /></td>
			</tr>
			<tr>

				<td class="pypl_label" align="right">' . __( 'Address 2:', 'psts' ) . '&nbsp;</td>
				<td><input size="45" name="cc_address2" type="text" class="cctext" value="' . esc_attr( $cc_address2 ) . '" /></td>
			</tr>
			<tr>
				<td class="pypl_label" align="right">' . __( 'City:', 'psts' ) . '*&nbsp;</td>
				<td>';
			$errmsg = ! empty( $psts->errors ) ? $psts->errors->get_error_message( 'city' ) : false;
			if ( $errmsg ) {
				$content .= '<div class="psts-error">' . $errmsg . '</div>';
			}
			$errmsg = ! empty( $psts->errors ) ? $psts->errors->get_error_message( 'state' ) : false;
			if ( $errmsg ) {
				$content .= '<div class="psts-error">' . $errmsg . '</div>';
			}
			$content .= '<input size="20" name="cc_city" type="text" class="cctext" value="' . esc_attr( $cc_city ) . '" />&nbsp;&nbsp; ' . __( 'State/Province:', 'psts' ) . '*&nbsp;<input size="5" name="cc_state" type="text" class="cctext" value="' . esc_attr( $cc_state ) . '" /></td>
			</tr>
			<tr>
				<td class="pypl_label" align="right">' . __( 'Postal/Zip Code:', 'psts' ) . '*&nbsp;</td><td>';
			$errmsg = ! empty( $psts->errors ) ? $psts->errors->get_error_message( 'zip' ) : false;
			if ( $errmsg ) {
				$content .= '<div class="psts-error">' . $errmsg . '</div>';
			}
			$content .= '<input size="10" name="cc_zip" type="text" class="cctext" value="' . esc_attr( $cc_zip ) . '" /> </td>
			</tr>
			<tr>

				<td class="pypl_label" align="right">' . __( 'Country:', 'psts' ) . '*&nbsp;</td><td>';
			$errmsg = ! empty( $psts->errors ) ? $psts->errors->get_error_message( 'country' ) : false;
			if ( $errmsg ) {
				$content .= '<div class="psts-error">' . $errmsg . '</div>';
			}
			//default to USA
			if ( empty( $cc_country ) ) {
				$cc_country = 'US';
			}
			$content .= '<select name="cc_country">';
			foreach ( $psts->countries as $key => $value ) {
				$content .= '<option value="' . $key . '"' . ( ( $cc_country == $key ) ? ' selected="selected"' : '' ) . '>' . esc_attr( $value ) . '</option>';
			}
			$content .= '</select>
				</td>
			</tr>
        </tbody>
    </table>
	<p>
		<input type="submit" id="cc_paypal_checkout" name="cc_paypal_checkout" value="' . __( 'Subscribe', 'psts' ) . ' &raquo;" class="submit-button" />
		<div id="paypal_processing" style="display: none;float: right;"><img src="' . $img_base . 'loading.gif" /> ' . __( 'Processing...', 'psts' ) . '</div>
    </p>
	</div>';
		}

		$content .= '</form>';

		return $content;
	}

	/**
	 * Handles the form processing for Paypal button and express payments
	 */
	public static function process_checkout_form() {

		global $current_site, $current_user, $psts, $wpdb;

		$domain      = $path = '';
		$discountAmt = $has_coupon = false;
		$new_blog    = true;

		//Blog id, Level Period
		$blog_id = ! empty( $_POST['bid'] ) ? $_POST['bid'] : 0;
		$level   = ! empty( $_POST['level'] ) ? $_POST['level'] : '';
		$period  = ! empty( $_POST['period'] ) ? $_POST['period'] : '';

		// Try going stateless, or check the session
		$process_data = array();
		$session_keys = array( 'new_blog_details', 'upgraded_blog_details', 'COUPON_CODE', 'activation_key' );
		foreach ( $session_keys as $key ) {
			$process_data[ $key ] = ! empty( $process_data[ $key ] ) ? $process_data[ $key ] : ProSites_Helper_Session::session( $key );
		}

		// Get blog_id from the session
		if ( isset( $process_data['new_blog_details'] ) && isset( $process_data['new_blog_details']['blog_id'] ) ) {
			$blog_id = $process_data['new_blog_details']['blog_id'];
		}
		$blog_id = ! empty( $blog_id ) ? $blog_id : ( ! empty( $_GET['bid'] ) ? (int) $_GET['bid'] : 0 );

		//Get domain details, if activation is set, runs when user submits the form for blog signup
		if ( ! empty( $_POST['activation'] ) || ! empty( $process_data['activation_key'] ) ) {

			$activation_key = ! empty( $_POST['activation'] ) ? $_POST['activation'] : $process_data['activation_key'];

			//For New Signup
			$signup_details = $wpdb->get_row( $wpdb->prepare( "SELECT `domain`, `path` FROM $wpdb->signups WHERE activation_key = %s", $activation_key ) );

			if ( $signup_details ) {

				$domain = $signup_details->domain;
				$path   = $signup_details->path;

				//Store values in session or custom variable, to be used after user returns from Paypal Payment
				$process_data['new_blog_details']['domain'] = $domain;
				$process_data['new_blog_details']['path']   = $path;
			}
			$process_data['activation_key'] = $activation_key;
		}
		//Set Level and period in upgraded blog details, if blog id is set, for upgrades
		if ( ! empty( $blog_id ) ) {
			$new_blog = false;
			if ( ! empty( $level ) && ! empty( $period ) ) {
				$process_data['upgraded_blog_details']['level']  = $level;
				$process_data['upgraded_blog_details']['period'] = $period;
			}
		}

		$signup_type = $new_blog ? 'new_blog_details' : 'upgraded_blog_details';

		// Update the session data with the changed process data.
		ProSites_Helper_Session::session( 'new_blog_details', $process_data['new_blog_details'] );
		ProSites_Helper_Session::session( 'upgraded_blog_details', $process_data['upgraded_blog_details'] );
		ProSites_Helper_Session::session( 'activation_key', $process_data['activation_key'] );


		//After user is redirected back from Paypal
		if ( isset( $_GET['token'] ) ) {
			//Check if blog id is set, If yes -> Upgrade, else  -> New Setup
			$_POST['level']  = ! empty( $process_data[ $signup_type ] ) ? $process_data[ $signup_type ]['level'] : '';
			$_POST['period'] = ! empty( $process_data[ $signup_type ] ) ? $process_data[ $signup_type ]['period'] : '';
		}

		//Process The submitted form and redirect user to Paypal for payment or process when the user comes back
		if ( isset( $_POST['paypal_checkout'] ) ||
		     isset( $_POST['paypal_checkout_x'] ) ||
		     isset( $_POST['cc_paypal_checkout'] ) ||
		     isset( $_GET['token'] )
		) {

			//Check for level
			if ( empty( $_POST['level'] ) || empty( $_POST['period'] ) ) {
				$psts->errors->add( 'general', __( 'Please choose your desired level and payment plan.', 'psts' ) );
			}

			//prepare vars
			$currency   = $psts->get_setting( "pypl_currency", 'USD' );
			$trial_days = $psts->get_setting( 'trial_days', 0 );
			$is_trial   = $psts->is_trial_allowed( $blog_id );
			$setup_fee  = (float) $psts->get_setting( 'setup_fee', 0 );
			$trial_desc = ( $is_trial ) ? ProSites_Gateway_PayPalExpressPro::get_free_trial_desc( $trial_days ) : '';
			$recurring  = $psts->get_setting( 'recurring_subscriptions', true );

			//If free level is selected, activate a trial
			if ( isset( $_POST['level'] ) && isset( $_POST['period'] ) ) {
				if ( ! empty ( $domain ) && ! $psts->prevent_dismiss() && '0' === $_POST['level'] && '0' === $_POST['period'] ) {
					$esc_domain = esc_url( $domain );
					ProSites_Helper_Registration::activate_blog( $process_data['activation_key'], $is_trial, $process_data[ $signup_type ]['period'], $process_data[ $signup_type ]['level'] );

					//Set complete message
					self::$complete_message = __( 'Your trial blog has been setup at <a href="' . $esc_domain . '">' . $esc_domain . '</a>', 'psts' );

					return;
				}

			}
			//Current site name as per the payment procedure
			$current_site_name = ! empty ( $domain ) ? $domain : ( ! empty( $process_data[ $signup_type ]['domain'] ) ? $process_data[ $signup_type ]['domain'] : $current_site->site_name );

			$paymentAmount = $initAmount = $psts->get_level_setting( $_POST['level'], 'price_' . $_POST['period'] );
			$has_setup_fee = $psts->has_setup_fee( $blog_id, $_POST['level'] );
			$has_coupon    = ( isset( $process_data['COUPON_CODE'] ) && ProSites_Helper_Coupons::check_coupon( $process_data['COUPON_CODE'], $blog_id, $_POST['level'], $_POST['period'], $domain ) ) ? true : false;

			if ( $has_setup_fee ) {
				$initAmount += $setup_fee;
			}

			if ( $has_coupon || $has_setup_fee ) {
				if ( $has_coupon ) {
					$adjusted_values = ProSites_Helper_Coupons::get_adjusted_level_amounts( $process_data['COUPON_CODE'] );

					$coupon_value = $adjusted_values[ $_POST['level'] ][ 'price_' . $_POST['period'] ];
					$amount_off   = $paymentAmount - $coupon_value;
					$initAmount -= $amount_off;
					$initAmount = 0 > $initAmount ? 0 : $initAmount; // avoid negative
				}

				if ( $recurring ) {
					//Recurring
					if ( $_POST['period'] == 1 ) {
						$desc = $current_site_name . ' ' . $psts->get_level_setting( $_POST['level'], 'name' ) . ': ' . sprintf( __( '%1$s for the first month%3$s, then %2$s each month', 'psts' ), $psts->format_currency( $currency, $initAmount ), $psts->format_currency( $currency, $paymentAmount ), $trial_desc );
					} else {
						$desc = $current_site_name . ' ' . $psts->get_level_setting( $_POST['level'], 'name' ) . ': ' . sprintf( __( '%1$s for the first %2$s month period%5$s, then %3$s every %4$s months', 'psts' ), $psts->format_currency( $currency, $initAmount ), $_POST['period'], $psts->format_currency( $currency, $paymentAmount ), $_POST['period'], $trial_desc );
					}
				} else {
					//One Time Subscription
					$initAmount = $psts->calc_upgrade_cost( $blog_id, $_POST['level'], $_POST['period'], $initAmount );
					if ( $_POST['period'] == 1 ) {
						$desc = $current_site_name . ' ' . $psts->get_level_setting( $_POST['level'], 'name' ) . ': ' . sprintf( __( '%1$s %2$s for 1 month (non recurring)', 'psts' ), $psts->format_currency( $currency, $initAmount ), $currency );
					} else {
						$desc = $current_site_name . ' ' . $psts->get_level_setting( $_POST['level'], 'name' ) . ': ' . sprintf( __( '%1$s %2$s for %3$s months (non recurring)', 'psts' ), $psts->format_currency( $currency, $initAmount ), $currency, $_POST['period'] );
					}
				}
			} else {
				//No Setup fee or Coupon
				if ( $recurring ) {
					if ( $_POST['period'] == 1 ) {
						$desc = $current_site_name . ' ' . $psts->get_level_setting( $_POST['level'], 'name' ) . ': ' . sprintf( __( '%1$s %2$s each month', 'psts' ), $psts->format_currency( $currency, $paymentAmount ), $currency );
					} else {
						$desc = $current_site_name . ' ' . $psts->get_level_setting( $_POST['level'], 'name' ) . ': ' . sprintf( __( '%1$s %2$s every %3$s months', 'psts' ), $psts->format_currency( $currency, $paymentAmount ), $currency, $_POST['period'] );
					}
				} else {
					$paymentAmount = $psts->calc_upgrade_cost( $blog_id, $_POST['level'], $_POST['period'], $paymentAmount );
					if ( $_POST['period'] == 1 ) {
						$desc = $current_site_name . ' ' . $psts->get_level_setting( $_POST['level'], 'name' ) . ': ' . sprintf( __( '%1$s %2$s for 1 month (non recurring)', 'psts' ), $psts->format_currency( $currency, $paymentAmount ), $currency );
					} else {
						$desc = $current_site_name . ' ' . $psts->get_level_setting( $_POST['level'], 'name' ) . ': ' . sprintf( __( '%1$s %2$s for %3$s months (non recurring)', 'psts' ), $psts->format_currency( $currency, $paymentAmount ), $currency, $_POST['period'] );
					}
				}
			}

			$desc = apply_filters( 'psts_pypl_checkout_desc', $desc, $_POST['period'], $_POST['level'], $paymentAmount, $initAmount, $blog_id, $domain );
		}

		//Runs just after the paypal button click, process paypal express checkout
		if ( isset( $_POST['paypal_checkout'] ) || isset( $_POST['paypal_checkout_x'] ) ) {
			//check for level
			if ( ! isset( $_POST['period'] ) || ! isset( $_POST['level'] ) ) {
				$psts->errors->add( 'general', __( 'Please choose your desired level and payment plan.', 'psts' ) );

				return;
			}
			if ( $is_trial ) {
				$resArray = PaypalApiHelper::SetExpressCheckout( ( $initAmount - $paymentAmount ), $desc, $blog_id, $domain, $path );
			} else {
				$resArray = PaypalApiHelper::SetExpressCheckout( $initAmount, $desc, $blog_id, $domain );
			}

			if ( isset( $resArray['ACK'] ) && ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) ) {
				$token = $resArray["TOKEN"];
				PaypalApiHelper::RedirectToPayPal( $token );
			} else {
				$psts->errors->add( 'general', sprintf( __( 'There was a problem setting up the paypal payment:<br />"<strong>%s</strong>"<br />Please try again.', 'psts' ), self::parse_error_string( $resArray ) ) );
			}
		}

		/* ------------------- PayPal Checkout ----------------- */
		//!check for return from Express Checkout
		if ( isset( $_GET['token'] ) && isset( $_POST['period'] ) && isset( $_POST['level'] ) ) {

			//Set payerID if missing
			if ( ! isset( $_GET['PayerID'] ) ) {

				$details = PaypalApiHelper::GetExpressCheckoutDetails( $_GET['token'] );

				if ( isset( $details['PAYERID'] ) ) {
					$_GET['PayerID'] = $details['PAYERID'];
				}

			}

			$domain         = ! empty( $domain ) ? $domain : ( ! empty( $process_data[ $signup_type ]['domain'] ) ? $process_data[ $signup_type ]['domain'] : '' );
			$path           = ! empty( $path ) ? $path : ( ! empty( $process_data[ $signup_type ]['path'] ) ? $process_data[ $signup_type ]['path'] : '' );
			$activation_key = ! empty( $process_data['activation_key'] ) ? $process_data['activation_key'] : '';

			//check for modifiying
			if ( ! empty( $blog_id ) && is_pro_site( $blog_id ) ) {
				$modify = $psts->calc_upgrade( $blog_id, $initAmount, $_POST['level'], $_POST['period'] );
				$modify = $modify ? $modify : $psts->get_expire( $blog_id );
			} else {
				$modify = false;
			}

			//Non Recurring - Handle One time payment for new signups and upgrades, Paypal doesn't supports trial for one time payments, so we create a subscription
			// with 1 billing cycle
			if ( ! $recurring ) {

				//Non recurring Upgrades, Non Recurring signup without trial
				if ( $modify || ! $is_trial ) {
					$resArray = PaypalApiHelper::DoExpressCheckoutPayment( $_GET['token'], $_GET['PayerID'], $initAmount, $_POST['period'], $desc, $blog_id, $_POST['level'], '', $domain, $path );

					if ( $resArray['PAYMENTINFO_0_ACK'] == 'Success' || $resArray['PAYMENTINFO_0_ACK'] == 'SuccessWithWarning' ) {
						$payment_status   = $resArray['PAYMENTINFO_0_PAYMENTSTATUS'];
						$paymentAmount    = $resArray['PAYMENTINFO_0_AMT'];
						$init_transaction = $resArray['PAYMENTINFO_0_TRANSACTIONID'];

						if ( ! $modify ) {
							//Plan Update
							$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via PayPal Express: Initial payment successful (%1$s) - Transaction ID: %2$s', 'psts' ), $desc, $init_transaction ) );
						} else {
							//New Signup, Activate blog
							$site_details = ProSites_Helper_Registration::activate_blog( $activation_key, $is_trial, $_POST['period'], $_POST['level'] );
							$blog_id      = ! empty( $site_details ) ? $site_details['blog_id'] : $blog_id;

							$psts->log_action( $blog_id, sprintf( __( 'User creating modifying subscription via PayPal Express: Payment successful (%1$s) - Transaction ID: %2$s', 'psts' ), $desc, $init_transaction ) );
						}

						//just in case, try to cancel any old subscription
						if ( $profile_id = self::get_profile_id( $blog_id ) ) {
							$resArray = PaypalApiHelper::ManageRecurringPaymentsProfileStatus( $profile_id, 'Cancel', sprintf( __( 'Your %s subscription has been modified. This previous subscription has been canceled.', 'psts' ), $psts->get_setting( 'rebrand' ) ) );
						}

						//now get the details of the transaction to see if initial payment went through already
						if ( $resArray['PAYMENTINFO_0_PAYMENTSTATUS'] == 'Completed' || $resArray['PAYMENTINFO_0_PAYMENTSTATUS'] == 'Processed' ) {
							$old_expire = $psts->get_expire( $blog_id );
							$new_expire = ( $old_expire > time() ) ? $old_expire : false;
							$psts->extend( $blog_id, $_POST['period'], self::get_slug(), $_POST['level'], $psts->get_level_setting( $_POST['level'], 'price_' . $_POST['period'] ), $new_expire, false );
							$psts->email_notification( $blog_id, 'success' );
							$psts->record_transaction( $blog_id, $init_transaction, $paymentAmount );

							if ( $modify ) {
								if ( $process_data[ $signup_type ]['level'] > ( $old_level = $psts->get_level( $blog_id ) ) ) {
									$psts->record_stat( $blog_id, 'upgrade' );
								} else {
									$psts->record_stat( $blog_id, 'modify' );
								}
							} else {
								$psts->record_stat( $blog_id, 'signup' );
							}

							do_action( 'supporter_payment_processed', $blog_id, $paymentAmount, $_POST['period'], $_POST['level'] );

							if ( empty( self::$complete_message ) ) {
								self::$complete_message = __( 'Your PayPal subscription was successful! You should be receiving an email receipt shortly.', 'psts' );
							}
						} else {

							//If payment is pending for some reason, store the details, to display it on Checkout screen later
							if ( $payment_status == 'Pending' ) {
								//log reason
								if ( ! empty( $resArray['PAYMENTINFO_0_PENDINGREASON'] ) ) {

									$psts->log_action( $blog_id, sprintf( __( 'PayPal response: Last payment is pending (%s). Reason: %s', 'psts' ), $payment_status, self::$pending_str[ $resArray['PAYMENTINFO_0_PENDINGREASON'] ] ) . '. Payer ID: ' . $_GET['PayerID'] );

									//Store Payment status and reason in pro site meta
									$payment_details = array(
										'payment_status' => $payment_status,
										'pending_reason' => self::$pending_str[ $resArray['PAYMENTINFO_0_PENDINGREASON'] ]
									);
									update_user_meta( get_current_user_id(), 'psts_payment_details', $payment_details );

								}
							}
							update_blog_option( $blog_id, 'psts_waiting_step', 1 );
						}
						self::update_session_vars( $process_data, array(
							'blog_id' => $blog_id,
							'level'   => $level,
							'period'  => $period
						) );
					}//End of Check if transaction was successful, for non recurring signup and upgrade
					//End of if no trial, or non recurring upgrade
				} else {
					//If there is a trial, create a subscription with total 1 billing cycle
					$ack_success    = true;
					$payment_status = '';

					//create the recurring profile, with 1 total billing cycle
					$resArray = PaypalApiHelper::CreateRecurringPaymentsProfileExpress( $_GET['token'], $paymentAmount, $initAmount, $_POST['period'], $desc, $blog_id, $_POST['level'], '', $activation_key, 1 );

					//If Profile is created
					if ( isset( $resArray['ACK'] ) && ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) ) {

						//Blog might be already active
						$site_details = ProSites_Helper_Registration::activate_blog( $activation_key, $is_trial, $_POST['period'], $_POST['level'] );
						$blog_id      = ! empty( $site_details ) ? $site_details['blog_id'] : $blog_id;

						if ( ! empty( $blog_id ) ) {
							//save new profile_id
							self::set_profile_id( $blog_id, $resArray["PROFILEID"] );

							//update the blog id in paypal custom so that future payments are applied to the proper blog id
							$custom = PSTS_PYPL_PREFIX . '_' . $blog_id . "_" . $_POST['level'] . '_' . $_POST['period'] . '_' . $initAmount . '_' . $psts->get_setting( 'pypl_currency' ) . '_' . time() . '_' . $activation_key;
							PaypalApiHelper::UpdateRecurringPaymentsProfile( $resArray["PROFILEID"], $custom );

							$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via PayPal Express: Subscription created (%1$s) - Profile ID: %2$s', 'psts' ), $desc, $resArray["PROFILEID"] ) );

							//Store Payment , for rendering the confirmation on checkout page
							self::update_session_vars( $process_data, array(
								'blog_id' => $blog_id,
								'level'   => $level,
								'period'  => $period
							) );

						} else {
							//Store in signup meta for domain
							self::set_profile_id( '', $resArray["PROFILEID"], $domain );
							$psts->log_action( '', sprintf( __( 'User creating new subscription via PayPal Express: Subscription created (%1$s) - Profile ID: %2$s', 'psts' ), $desc, $resArray["PROFILEID"] ), $domain );

						}
					} elseif ( ! empty( $resArray['ACK'] ) ) {
						//If payment was declined, or user returned
						$psts->errors->add( 'general', sprintf( __( 'There was a problem processing the Paypal payment:<br />"<strong>%s</strong>"<br />Please try again.', 'psts' ), self::parse_error_string( $resArray ) ) );
						$message = sprintf( __( 'User creating subscription via PayPal Express: PayPal returned an error: %s', 'psts' ), self::parse_error_string( $resArray ) );
					} else {
						self::$complete_message = __( 'Your initial PayPal transaction was successful, but there was a problem creating the subscription so you may need to renew when the first period is up. Your site should be upgraded shortly.', 'psts' ) . '<br />"<strong>' . self::parse_error_string( $resArray ) . '</strong>"';
						$message                = sprintf( __( 'User creating new subscription via PayPal Express: Problem creating the subscription after successful initial payment. User may need to renew when the first period is up: %s', 'psts' ), self::parse_error_string( $resArray ) );
					}

					//Log action
					$psts->log_action( $blog_id, $message, $domain );

					//now get the details of the transaction to see if initial payment went through already
					if ( $is_trial || $payment_status == 'Completed' || $payment_status == 'Processed' ) {

						//If we have domain details, activate the blog, It will be extended later in the same code block
						if ( ! empty( $domain ) ) {
							$site_details = ProSites_Helper_Registration::activate_blog( $activation_key, $is_trial, $_POST['period'], $_POST['level'] );
						}
						//If we have blog id, Extend the blog expiry
						if ( ! empty( $blog_id ) ) {
							//Trial is allowed, set expiry date to n number of days from today, where n is number of days for trial
							$paymentAmount = '';
							$trial         = strtotime( '+ ' . $trial_days . ' days' );
							//Extend the Blog expiry as per Trial or not
							$psts->extend( $blog_id, $_POST['period'], self::get_slug(), $_POST['level'], $paymentAmount, $trial );
						}
						$psts->record_stat( $blog_id, 'signup' );
						$psts->email_notification( $blog_id, 'success' );

						// Added for affiliate system link
						do_action( 'supporter_payment_processed', $blog_id, $paymentAmount, $_POST['period'], $_POST['level'] );

						if ( empty( self::$complete_message ) ) {
							self::$complete_message = __( 'Your PayPal subscription was successful! You should be receiving an email receipt shortly.', 'psts' );
						}
					} else {
						//If we have blog id
						if ( ! empty ( $blog_id ) ) {
							//Set expiry for 4 hours from now, and set waiting step as 1, until payment is confirmed from Paypal
							$expiry = strtotime( '+ 4 Hours' );
							update_blog_option( $blog_id, 'psts_waiting_step', 1 );

							//Extend the Blog expiry as per Trial or not
							$psts->extend( $blog_id, $_POST['period'], self::get_slug(), $_POST['level'], $paymentAmount, $expiry );
						} else {
							//Update Domain meta
							$signup_meta = '';
							$signup      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE domain = %s", $domain ) );
							if ( ! empty( $signup ) ) {
								$signup_meta = maybe_unserialize( $signup->meta );
							}
							$signup_meta['psts_waiting_step'] = 1;
							$wpdb->update(
								$wpdb->signups,
								array(
									'meta' => serialize( $signup_meta ), // string
								),
								array(
									'domain' => $domain
								)
							);
						}

					}//End of else, if its trial or payment completed

					if ( ! empty( $blog_id ) ) {

						self::update_session_vars( $process_data, array(
							'blog_id' => $blog_id,
							'level'   => $level,
							'period'  => $period
						) );

						//Store activation key in Pro sites table
						self::set_blog_identifier( $activation_key, $blog_id );
					}
				}
				//End of Non Recurring subscriptions
			} else {
				//Recurring Subscription
				//Upgrade
				if ( $modify ) {
					//! create the recurring profile
					$resArray = PaypalApiHelper::CreateRecurringPaymentsProfileExpress( $_GET['token'], $paymentAmount, $initAmount, $_POST['period'], $desc, $blog_id, $_POST['level'], $modify );
					if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {
						$new_profile_id = $resArray["PROFILEID"];

						$end_date = date_i18n( get_blog_option( $blog_id, 'date_format' ), $modify );
						$psts->log_action( $blog_id, sprintf( __( 'User modifying subscription via PayPal Express: New subscription created (%1$s), first payment will be made on %2$s - %3$s', 'psts' ), $desc, $end_date, $new_profile_id ) );

						//cancel old subscription
						$old_gateway = $wpdb->get_var( "SELECT gateway FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'" );
						if ( $profile_id = self::get_profile_id( $blog_id ) ) {
							$resArray = PaypalApiHelper::ManageRecurringPaymentsProfileStatus( $profile_id, 'Cancel', sprintf( __( 'Your %1$s subscription has been modified. This previous subscription has been canceled, and your new subscription (%2$s) will begin on %3$s.', 'psts' ), $psts->get_setting( 'rebrand' ), $desc, $end_date ) );
							if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {
								$psts->log_action( $blog_id, sprintf( __( 'User modifying subscription via PayPal Express: Old subscription canceled - %s', 'psts' ), $profile_id ) );
							}
						} else {
							self::manual_cancel_email( $blog_id, $old_gateway ); //send email for old paypal system
						}

						if ( $process_data['upgraded_blog_details']['level'] > ( $old_level = $psts->get_level( $blog_id ) ) ) {
							$psts->record_stat( $blog_id, 'upgrade' );
						} else {
							$psts->record_stat( $blog_id, 'modify' );
						}
						$psts->extend( $blog_id, $process_data['upgraded_blog_details']['period'], self::get_slug(), $process_data['upgraded_blog_details']['level'], $paymentAmount, false, true );

						//use coupon
						if ( $has_coupon ) {
							$psts->use_coupon( $process_data['COUPON_CODE'], $blog_id );
						}

						//save new profile_id
						self::set_profile_id( $blog_id, $new_profile_id );

						//save new period/term
						$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->base_prefix}pro_sites SET term = %d WHERE blog_ID = %d", $process_data['upgraded_blog_details']['period'], $blog_id ) );

						//show confirmation page
						self::$complete_message = sprintf( __( 'Your PayPal subscription modification was successful for %s.', 'psts' ), $desc );

						//display GA ecommerce in footer
						if ( ! $is_trial ) {
							$psts->create_ga_ecommerce( $blog_id, $process_data['upgraded_blog_details']['period'], $initAmount, $process_data['upgraded_blog_details']['level'] );
						}
						//show instructions for old gateways
						if ( $old_gateway == 'PayPal' ) {
							self::$complete_message .= '<p><strong>' . __( 'Because of billing system upgrades, we were unable to cancel your old subscription automatically, so it is important that you cancel the old one yourself in your PayPal account, otherwise the old payments will continue along with new ones! Note this is the only time you will have to do this.', 'psts' ) . '</strong></p>';
							self::$complete_message .= '<p><a target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_subscr-find&alias=' . urlencode( get_site_option( "supporter_paypal_email" ) ) . '"><img src="' . $psts->plugin_url . 'images/cancel_subscribe_gen.gif" /></a><br /><small>' . __( 'You can also cancel following <a href="https://www.paypal.com/webapps/helpcenter/article/?articleID=94044#canceling_recurring_paymemt_subscription_automatic_billing">these steps</a>.', 'psts' ) . '</small></p>';
						} else if ( $old_gateway == 'Amazon' ) {
							self::$complete_message .= '<p><strong>' . __( 'Because of billing system upgrades, we were unable to cancel your old subscription automatically, so it is important that you cancel the old one yourself in your Amazon Payments account, otherwise the old payments will continue along with new ones! Note this is the only time you will have to do this.', 'psts' ) . '</strong></p>';
							self::$complete_message .= '<p>' . __( 'To view your subscriptions, simply go to <a target="_blank" href="https://payments.amazon.com/">https://payments.amazon.com/</a>, click Your Account at the top of the page, log in to your Amazon Payments account (if asked), and then click the Your Subscriptions link. This page displays your subscriptions, showing the most recent, active subscription at the top. To view the details of a specific subscription, click Details. Then cancel your subscription by clicking the Cancel Subscription button on the Subscription Details page.', 'psts' ) . '</p>';
						}
					} else {
						$psts->errors->add( 'general', sprintf( __( 'There was a problem setting up the Paypal payment:<br />"<strong>%s</strong>"<br />Please try again.', 'psts' ), self::parse_error_string( $resArray ) ) );
						$psts->log_action( $blog_id, sprintf( __( 'User modifying subscription via PayPal Express: PayPal returned an error: %s', 'psts' ), self::parse_error_string( $resArray ) ) );
					}
				} else {
					//Handle the new signups
					$ack_success    = true;
					$payment_status = '';

					if ( ! $is_trial ) {
						//if no trial is set, initiate the payment
						$resArray = PaypalApiHelper::DoExpressCheckoutPayment( $_GET['token'], $_GET['PayerID'], $initAmount, $_POST['period'], $desc, $blog_id, $_POST['level'], '', $activation_key );

						$init_transaction = isset( $resArray['PAYMENTINFO_0_TRANSACTIONID'] ) ? $init_transaction = $resArray['PAYMENTINFO_0_TRANSACTIONID'] : '';

						//Get payment status
						if ( isset( $resArray['PAYMENTINFO_0_ACK'] ) && ( $resArray['PAYMENTINFO_0_ACK'] == 'Success' || $resArray['PAYMENTINFO_0_ACK'] == 'SuccessWithWarning' ) ) {
							$payment_status = $resArray['PAYMENTINFO_0_PAYMENTSTATUS'];
							$paymentAmount  = $resArray['PAYMENTINFO_0_AMT'];
							$ack_success    = true;

						}
						//Activate blog
						$site_details = ProSites_Helper_Registration::activate_blog( $activation_key, $is_trial, $_POST['period'], $_POST['level'] );
						$blog_id      = ! empty( $site_details ) ? $site_details['blog_id'] : $blog_id;

						//If payment is pending for some reason, store the details, to display it on Checkout screen later
						if ( $payment_status == 'Pending' ) {
							//log reason
							if ( ! empty( $resArray['PAYMENTINFO_0_PENDINGREASON'] ) ) {

								$psts->log_action( $blog_id, sprintf( __( 'PayPal response: Last payment is pending (%s). Reason: %s', 'psts' ), $payment_status, self::$pending_str[ $resArray['PAYMENTINFO_0_PENDINGREASON'] ] ) . '. Payer ID: ' . $_GET['PayerID'] );

								//Store Payment status and reason in pro site meta
								$payment_details = array(
									'payment_status' => $payment_status,
									'pending_reason' => self::$pending_str[ $resArray['PAYMENTINFO_0_PENDINGREASON'] ]
								);
								update_user_meta( get_current_user_id(), 'psts_payment_details', $payment_details );

							}
						}
					}

					if ( $is_trial || $ack_success ) {
						if ( ! $is_trial ) {
							if ( ! empty( $blog_id ) ) {
								//Store transaction detail
								$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via PayPal Express: Initial payment successful (%1$s) - Transaction ID: %2$s', 'psts' ), $desc, $init_transaction ) );
							} else {
								$psts->log_action( '', sprintf( __( 'User creating new subscription via PayPal Express: Initial payment successful (%1$s) - Transaction ID: %2$s', 'psts' ), $desc, $init_transaction ), $domain );
							}
						}

						//use coupon
						if ( $has_coupon ) {
							$psts->use_coupon( $process_data['COUPON_CODE'], $blog_id );
						}

						//just in case, try to cancel any old subscription
						if ( ! empty( $blog_id ) && ( $profile_id = self::get_profile_id( $blog_id ) ) ) {
							PaypalApiHelper::ManageRecurringPaymentsProfileStatus( $profile_id, 'Cancel', sprintf( __( 'Your %s subscription has been modified. This previous subscription has been canceled.', 'psts' ), $psts->get_setting( 'rebrand' ) ) );
						}

						//create the recurring profile
						$resArray = PaypalApiHelper::CreateRecurringPaymentsProfileExpress( $_GET['token'], $paymentAmount, $initAmount, $_POST['period'], $desc, $blog_id, $_POST['level'], '', $activation_key );


						//If Profile is created
						if ( isset( $resArray['ACK'] ) && ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) ) {

							//Blog might be already active
							$site_details = ProSites_Helper_Registration::activate_blog( $activation_key, $is_trial, $_POST['period'], $_POST['level'] );
							$blog_id      = ! empty( $site_details ) ? $site_details['blog_id'] : $blog_id;

							if ( ! empty( $blog_id ) ) {
								//save new profile_id
								self::set_profile_id( $blog_id, $resArray["PROFILEID"] );

								//update the blog id in paypal custom so that future payments are applied to the proper blog id
								$custom  = PSTS_PYPL_PREFIX . '_' . $blog_id . "_" . $_POST['level'] . '_' . $_POST['period'] . '_' . $initAmount . '_' . $psts->get_setting( 'pypl_currency' ) . '_' . time() . '_' . $activation_key;
								$updated = PaypalApiHelper::UpdateRecurringPaymentsProfile( $resArray["PROFILEID"], $custom );

								$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via PayPal Express: Subscription created (%1$s) - Profile ID: %2$s', 'psts' ), $desc, $resArray["PROFILEID"] ) );

								//Store Payment , for rendering the confirmation on checkout page
								self::update_session_vars( $process_data, array(
									'blog_id' => $blog_id,
									'level'   => $level,
									'period'  => $period
								) );

							} else {
								//Store in signup meta for domain
								self::set_profile_id( '', $resArray["PROFILEID"], $domain );
								$psts->log_action( '', sprintf( __( 'User creating new subscription via PayPal Express: Subscription created (%1$s) - Profile ID: %2$s', 'psts' ), $desc, $resArray["PROFILEID"] ), $domain );

							}
						} elseif ( ! empty( $resArray['ACK'] ) ) {
							//If payment was declined, or user returned
							$psts->errors->add( 'general', sprintf( __( 'There was a problem processing the Paypal payment:<br />"<strong>%s</strong>"<br />Please try again.', 'psts' ), self::parse_error_string( $resArray ) ) );
							$psts->log_action( $blog_id, sprintf( __( 'User creating subscription via PayPal Express: PayPal returned an error: %s', 'psts' ), self::parse_error_string( $resArray ) ), $domain );
						} else {
							self::$complete_message = __( 'Your initial PayPal transaction was successful, but there was a problem creating the subscription so you may need to renew when the first period is up. Your site should be upgraded shortly.', 'psts' ) . '<br />"<strong>' . self::parse_error_string( $resArray ) . '</strong>"';
							$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via PayPal Express: Problem creating the subscription after successful initial payment. User may need to renew when the first period is up: %s', 'psts' ), self::parse_error_string( $resArray ) ), $domain );
						}
						//now get the details of the transaction to see if initial payment went through already
						if ( $is_trial || $payment_status == 'Completed' || $payment_status == 'Processed' ) {

							//If we have domain details, activate the blog, It will be extended later in the same code block
							if ( ! empty( $domain ) ) {
								$site_details = ProSites_Helper_Registration::activate_blog( $activation_key, $is_trial, $_POST['period'], $_POST['level'] );
							}
							//If we have blog id, Extend the blog expiry
							if ( ! empty( $blog_id ) ) {
								//If trial is allowed, set expiry date to n number of days from today, where n is number of days for trial
								if ( $is_trial ) {
									$paymentAmount = '';
									$trial         = strtotime( '+ ' . $trial_days . ' days' );
								} else {
									$trial = '';
								}
								//Extend the Blog expiry as per Trial or not
								$psts->extend( $blog_id, $_POST['period'], self::get_slug(), $_POST['level'], $paymentAmount, $trial );
							}
							$psts->record_stat( $blog_id, 'signup' );
							$psts->email_notification( $blog_id, 'success' );

							//record last payment
							if ( ! $is_trial ) {
								$psts->record_transaction( $blog_id, $init_transaction, $paymentAmount );
							}

							// Added for affiliate system link
							do_action( 'supporter_payment_processed', $blog_id, $paymentAmount, $_POST['period'], $_POST['level'] );

							if ( empty( self::$complete_message ) ) {
								self::$complete_message = __( 'Your PayPal subscription was successful! You should be receiving an email receipt shortly.', 'psts' );
							}
						} else {
							//If we have blog id
							if ( ! empty ( $blog_id ) ) {
								//Set expiry for 4 hours from now, and set waiting step as 1, until payment is confirmed from Paypal
								$expiry = strtotime( '+ 4 Hours' );
								update_blog_option( $blog_id, 'psts_waiting_step', 1 );

								//Extend the Blog expiry as per Trial or not
								$psts->extend( $blog_id, $_POST['period'], self::get_slug(), $_POST['level'], $paymentAmount, $expiry );
							} else {
								//Update Domain meta
								$signup_meta = '';
								$signup      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE domain = %s", $domain ) );
								if ( ! empty( $signup ) ) {
									$signup_meta = maybe_unserialize( $signup->meta );
								}
								$signup_meta['psts_waiting_step'] = 1;
								$wpdb->update(
									$wpdb->signups,
									array(
										'meta' => serialize( $signup_meta ), // string
									),
									array(
										'domain' => $domain
									)
								);
							}

						}

						if ( ! empty( $blog_id ) ) {
							//Store activation key in Pro sites table
							self::set_blog_identifier( $activation_key, $blog_id );
						}

						//display GA ecommerce in footer
						if ( ! $is_trial ) {
							$psts->create_ga_ecommerce( $blog_id, $_POST['period'], $paymentAmount, $_POST['period'] );
						}
					} else {
						$psts->errors->add( 'general', sprintf( __( 'There was a problem setting up the Paypal payment:<br />"<strong>%s</strong>"<br />Please try again.', 'psts' ), self::parse_error_string( $resArray ) ) );
						$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via PayPal Express: PayPal returned an error: %s', 'psts' ), self::parse_error_string( $resArray ) ), $domain );
					}
				}
			}//End of recurring payments, modify or upgrade
		}

		/*! ------------ CC Checkout ----------------- */
		if ( isset( $_POST['cc_paypal_checkout'] ) ) {

			//check for level
			if ( ! isset( $_POST['period'] ) || ! isset( $_POST['level'] ) ) {
				$psts->errors->add( 'general', __( 'Please choose your desired level and payment plan.', 'psts' ) );

				return;
			}

			//process form
			if ( isset( $_POST['cc_form'] ) ) {

				$error_message    = array(
					'general'    => __( 'Whoops, looks like you may have tried to submit your payment twice so we prevented it. Check your subscription info below to see if it was created. If not, please try again.', 'psts' ),
					'card-type'  => __( 'Please choose a Card Type.', 'psts' ),
					'number'     => __( 'Please enter a valid Credit Card Number.', 'psts' ),
					'expiration' => __( 'Please choose an expiration date.', 'psts' ) ,
					'cvv2'       => __( 'Please enter a valid card security code. This is the 3 digits on the signature panel, or 4 digits on the front of Amex cards.', 'psts' ) ,
					'firstname'  => __( 'Please enter your First Name.', 'psts' ),
					'lastname'   => __( 'Please enter your Last Name.', 'psts' ),
					'address'    => __( 'Please enter your billing Street Address.', 'psts' ),
					'city'       => __( 'Please enter your billing City.', 'psts' ) ,
					'state'      => __( 'Please enter your billing State/Province.', 'psts' ),
					'zip'        => __( 'Please enter your billing Zip/Postal Code.', 'psts' ),
					'country'    => __( 'Please enter your billing Country.', 'psts' )

				);

				//clean up $_POST
				$cc_cardtype  = isset( $_POST['cc_card-type'] ) ? $_POST['cc_card-type'] : '';
				$cc_number    = isset( $_POST['cc_number'] ) ? stripslashes( $_POST['cc_number'] ) : '';
				$cc_month     = isset( $_POST['cc_month'] ) ? $_POST['cc_month'] : '';
				$cc_year      = isset( $_POST['cc_year'] ) ? $_POST['cc_year'] : '';
				$cc_firstname = isset( $_POST['cc_firstname'] ) ? stripslashes( $_POST['cc_firstname'] ) : '';
				$cc_lastname  = isset( $_POST['cc_lastname'] ) ? stripslashes( $_POST['cc_lastname'] ) : '';
				$cc_address   = isset( $_POST['cc_address'] ) ? stripslashes( $_POST['cc_address'] ) : '';
				$cc_address2  = isset( $_POST['cc_address2'] ) ? stripslashes( $_POST['cc_address2'] ) : '';
				$cc_city      = isset( $_POST['cc_city'] ) ? stripslashes( $_POST['cc_city'] ) : '';
				$cc_state     = isset( $_POST['cc_state'] ) ? stripslashes( $_POST['cc_state'] ) : '';
				$cc_zip       = isset( $_POST['cc_zip'] ) ? stripslashes( $_POST['cc_zip'] ) : '';
				$cc_country   = isset( $_POST['cc_country'] ) ? stripslashes( $_POST['cc_country'] ) : '';

				$cc_number        = preg_replace( '/[^0-9]/', '', $cc_number ); //strip any slashes
				$_POST['cc_cvv2'] = preg_replace( '/[^0-9]/', '', $_POST['cc_cvv2'] );

				//check nonce
				if ( ! self::check_nonce() ) {
					$psts->errors->add( 'general', $error_message['general'] );
				}

				if ( empty( $cc_cardtype ) ) {
					$psts->errors->add( 'card-type', $error_message['card-type'] );
				}

				if ( empty( $cc_number ) ) {
					$psts->errors->add( 'number', $error_message['number'] );
				}

				if ( empty( $cc_month ) || empty( $cc_year ) ) {
					$psts->errors->add( 'expiration', $error_message['expiration']);
				}

				if ( strlen( $_POST['cc_cvv2'] ) < 3 || strlen( $_POST['cc_cvv2'] ) > 4 ) {
					$psts->errors->add( 'cvv2', $error_message['cvv2']);
				}

				if ( empty( $cc_firstname ) ) {
					$psts->errors->add( 'firstname', $error_message['firstname']);
				}

				if ( empty( $cc_lastname ) ) {
					$psts->errors->add( 'lastname', $error_message['lastname']);
				}

				if ( empty( $cc_address ) ) {
					$psts->errors->add( 'address', $error_message['address']);
				}

				if ( empty( $_POST['cc_city'] ) ) {
					$psts->errors->add( 'city', $error_message['city']);
				}

				if ( ( $cc_country == 'US' || $cc_country == 'CA' ) && empty( $cc_state ) ) {
					$psts->errors->add( 'state', $error_message['state'] );
				}

				if ( empty( $cc_zip ) ) {
					$psts->errors->add( 'zip', $error_message['zip'] );
				}

				if ( empty( $cc_country ) || strlen( $cc_country ) != 2 ) {
					$psts->errors->add( 'country', $error_message['country'] );
				}

				//All fields are Proper, process Card
				if ( ! $psts->errors->get_error_code() ) {
					//check for modifying
					if ( is_pro_site( $blog_id ) && ! is_pro_trial( $blog_id ) ) {
						$modify = $psts->get_expire( $blog_id );

						//check for a upgrade and get new first payment date
						if ( $upgrade = $psts->calc_upgrade( $blog_id, $initAmount, $_POST['level'], $_POST['period'] ) ) {
							$modify = $upgrade;
						} else {
							$upgrade = false;
						}
					} else {
						$modify = false;
					}

					if ( ! $recurring ) {
						//Only Upgrades or signup without trial
						if ( $modify || ! $is_trial ) {
							$resArray = PaypalApiHelper::DoDirectPayment( $initAmount, $_POST['period'], $desc, $blog_id, $_POST['level'], $cc_cardtype, $cc_number, $cc_month . $cc_year, $_POST['cc_cvv2'], $cc_firstname, $cc_lastname, $cc_address, $cc_address2, $cc_city, $cc_state, $cc_zip, $cc_country, $current_user->user_email );

							if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {
								$init_transaction = $resArray["TRANSACTIONID"];
								$paymentAmount    = $resArray['AMT'];

								if ( ! $modify ) {
									$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via PayPal Direct Payment: Initial payment successful (%1$s) - Transaction ID: %2$s', 'psts' ), $desc, $init_transaction ) );
								} else {
									$psts->log_action( $blog_id, sprintf( __( 'User creating modifying subscription via PayPal Direct Payment: Payment successful (%1$s) - Transaction ID: %2$s', 'psts' ), $desc, $init_transaction ) );
								}

								//just in case, try to cancel any old subscription
								if ( $profile_id = self::get_profile_id( $blog_id ) ) {
									$resArray = PaypalApiHelper::ManageRecurringPaymentsProfileStatus( $profile_id, 'Cancel', sprintf( __( 'Your %s subscription has been modified. This previous subscription has been canceled.', 'psts' ), $psts->get_setting( 'rebrand' ) ) );
								}

								//Calculate the new expiry and extend the blog expiry
								$old_expire = $psts->get_expire( $blog_id );
								$new_expire = ( $old_expire && $old_expire > time() ) ? $old_expire : false;

								$psts->extend( $blog_id, $_POST['period'], self::get_slug(), $_POST['level'], $psts->get_level_setting( $_POST['level'], 'price_' . $_POST['period'] ), $new_expire, false );

								//Notify blog user
								$psts->email_notification( $blog_id, 'success' );

								//Update Log
								$psts->record_transaction( $blog_id, $init_transaction, $paymentAmount );

								if ( $modify ) {
									if ( $_POST['level'] > ( $old_level = $psts->get_level( $blog_id ) ) ) {
										$psts->record_stat( $blog_id, 'upgrade' );
									} else {
										$psts->record_stat( $blog_id, 'modify' );
									}
								} else {
									//New Signup
									$psts->record_stat( $blog_id, 'signup' );

									//Update Activation Key
									self::set_blog_identifier( $activation_key, $blog_id );
								}

								do_action( 'supporter_payment_processed', $blog_id, $paymentAmount, $_POST['period'], $_POST['level'] );

								if ( empty( self::$complete_message ) ) {
									self::$complete_message = __( 'Your PayPal subscription was successful! You should be receiving an email receipt shortly.', 'psts' );
								}
								//Set Payment Success in session var
							} elseif( $resArray['ACK'] == 'Failure' && !empty( $resArray['L_SHORTMESSAGE0'] ) ) {
								$psts->errors->add( 'general', $resArray['L_SHORTMESSAGE0'] );
							}else{
								update_blog_option( $blog_id, 'psts_waiting_step', 1 );
							}
							//End of if upgrade or not trial
						} else {
							//Non Recurring signup with trial - Direct Payment, Create a subscription with total 1 billing cycle
							//just in case, try to cancel any old subscription
							if ( ! empty( $blog_id ) && $profile_id = self::get_profile_id( $blog_id ) ) {
								$resArray = PaypalApiHelper::ManageRecurringPaymentsProfileStatus( $profile_id, 'Cancel', sprintf( __( 'Your %s subscription has been modified. This previous subscription has been canceled.', 'psts' ), $psts->get_setting( 'rebrand' ) ) );
							}

							//use coupon
							if ( $has_coupon ) {
								$psts->use_coupon( $process_data['COUPON_CODE'], $blog_id, $domain );
							}

							//now attempt to create the subscription
							$resArray = PaypalApiHelper::CreateRecurringPaymentsProfileDirect( $paymentAmount, $initAmount, $_POST['period'], $desc, $blog_id, $_POST['level'], $cc_cardtype, $cc_number, $cc_month . $cc_year, $_POST['cc_cvv2'], $cc_firstname, $cc_lastname, $cc_address, $cc_address2, $cc_city, $cc_state, $cc_zip, $cc_country, $current_user->user_email, '', $activation_key, 1 );

							//If recurring profile was created successfully
							if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {
								$blog_id = ProSites_Helper_Registration::activate_blog( $activation_key, $is_trial, $_POST['period'], $_POST['level'] );

								if ( ! empty( $blog_id ) ) {
									//save new profile_id
									self::set_profile_id( $blog_id, $resArray["PROFILEID"] );

									//update the profile id in paypal so that future payments are applied to the proper blog id
									$custom = PSTS_PYPL_PREFIX . ':' . $blog_id . ':' . '' . ':' . $_POST['level'] . ':' . $_POST['period'] . ':' . $initAmount . ':' . $psts->get_setting( 'pypl_currency' ) . ':' . time();
									PaypalApiHelper::UpdateRecurringPaymentsProfile( $resArray["PROFILEID"], $custom );

									$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via CC: Subscription created (%1$s) - Profile ID: %2$s', 'psts' ), $desc, $resArray["PROFILEID"] ), $domain );

									//Store Payment , for rendering the confirmation on checkout page
									self::update_session_vars( $process_data, array(
										'blog_id' => $blog_id,
										'level'   => $level,
										'period'  => $period
									) );

									//Store activation key in Pro Sites table
									self::set_blog_identifier( $activation_key, $blog_id );

								} else {
									//Store in signup meta for domain
									self::set_profile_id( '', $resArray["PROFILEID"], $domain );
									$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via CC: Subscription created (%1$s) - Profile ID: %2$s', 'psts' ), $desc, $resArray["PROFILEID"] ) );

								}

							} else {
								self::$complete_message = __( 'Your initial payment was successful, but there was a problem creating the subscription with your credit card so you may need to renew when the first period is up. Your site should be upgraded shortly.', 'psts' ) . '<br />"<strong>' . self::parse_error_string( $resArray ) . '</strong>"';
								$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via CC: Problem creating the subscription after successful initial payment. User may need to renew when the first period is up: %s', 'psts' ), self::parse_error_string( $resArray ) ), $domain );
							}

							if ( ! empty( $blog_id ) ) {
								$psts->email_notification( $blog_id, 'success' );
								$psts->record_stat( $blog_id, 'signup' );
							}
							//now get the details of the transaction to see if initial payment went through
							if ( $init_transaction ) {
								$result = PaypalApiHelper::GetTransactionDetails( $init_transaction );
								if ( $result['PAYMENTSTATUS'] == 'Completed' || $result['PAYMENTSTATUS'] == 'Processed' ) {
									//Activate the domain , user signup for
									if ( ! empty( $domain ) ) {
										//Activate the blog
										$blog_id = ProSites_Helper_Registration::activate_blog( $activation_key, $is_trial, $_POST['period'], $_POST['level'] );
									}
									$psts->extend( $blog_id, $_POST['period'], self::get_slug(), $_POST['level'], $paymentAmount );

									//record last payment
									$psts->record_transaction( $blog_id, $init_transaction, $result['AMT'] );

									// Added for affiliate system link
									do_action( 'supporter_payment_processed', $blog_id, $result['AMT'], $_POST['period'], $_POST['level'] );

									if ( empty( self::$complete_message ) ) {
										self::$complete_message = sprintf( __( 'Your Credit Card subscription was successful! You should be receiving an email receipt at %s shortly.', 'psts' ), get_blog_option( $blog_id, 'admin_email' ) );
									}
								} else {
									update_blog_option( $blog_id, 'psts_waiting_step', 1 );
								}
							} else {
								$psts->extend( $blog_id, $_POST['period'], self::get_slug(), $_POST['level'], '', strtotime( '+ ' . $trial_days . ' days' ) );

								if ( empty( self::$complete_message ) ) {
									self::$complete_message = sprintf( __( 'Your Credit Card subscription was successful! You should be receiving an email receipt at %s shortly.', 'psts' ), get_blog_option( $blog_id, 'admin_email' ) );
								}
							}

						}
					} elseif ( $modify ) {
						$old_profile = false;
						if ( $profile_id = self::get_profile_id( $blog_id ) ) {
							$old_profile = PaypalApiHelper::GetRecurringPaymentsProfileDetails( $profile_id );
							if ( strtotime( $old_profile['PROFILESTARTDATE'] ) > gmdate( 'U' ) && (int) $old_profile['TRIALAMTPAID'] == 0 ) {
								$is_trial = true;
							}
						}

						//create the recurring profile
						$resArray = PaypalApiHelper::CreateRecurringPaymentsProfileDirect( $paymentAmount, $initAmount, $_POST['period'], $desc, $blog_id, $_POST['level'], $cc_cardtype, $cc_number, $cc_month . $cc_year, $_POST['cc_cvv2'], $cc_firstname, $cc_lastname, $cc_address, $cc_address2, $cc_city, $cc_state, $cc_zip, $cc_country, $current_user->user_email, $modify );
						if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {
							$new_profile_id = $resArray["PROFILEID"];

							$end_date = date_i18n( get_blog_option( $blog_id, 'date_format' ), $modify );
							$psts->log_action( $blog_id, sprintf( __( 'User modifying subscription via CC: New subscription created (%1$s), first payment will be made on %2$s - %3$s', 'psts' ), $desc, $end_date, $new_profile_id ) );

							//cancel old subscription
							$old_gateway = $wpdb->get_var( "SELECT gateway FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'" );
							if ( $old_profile ) {
								$resArray = PaypalApiHelper::ManageRecurringPaymentsProfileStatus( $profile_id, 'Cancel', sprintf( __( 'Your %1$s subscription has been modified. This previous subscription has been canceled, and your new subscription (%2$s) will begin on %3$s.', 'psts' ), $psts->get_setting( 'rebrand' ), $desc, $end_date ) );
								if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {
									$psts->log_action( $blog_id, sprintf( __( 'User modifying subscription via CC: Old subscription canceled - %s', 'psts' ), $profile_id ) );
								}
							} else {
								self::manual_cancel_email( $blog_id, $old_gateway ); //send email for old paypal system
							}

							//extend
							$psts->extend( $blog_id, $_POST['period'], self::get_slug(), $_POST['level'], ( $is_trial ? '' : $paymentAmount ), ( $is_trial ? $psts->get_expire( $blog_id ) : false ) );

							//use coupon
							if ( $has_coupon ) {
								$psts->use_coupon( $process_data['COUPON_CODE'], $blog_id );
							}

							//save new profile_id
							self::set_profile_id( $blog_id, $new_profile_id );

							//show confirmation page
							self::$complete_message = sprintf( __( 'Your Credit Card subscription modification was successful for %s.', 'psts' ), $desc );

							//display GA ecommerce in footer
							$psts->create_ga_ecommerce( $blog_id, $_POST['period'], $initAmount, $_POST['level'], $cc_city, $cc_state, $cc_country );

							//show instructions for old gateways
							if ( $old_gateway == 'PayPal' ) {
								self::$complete_message .= '<p><strong>' . __( 'Because of billing system upgrades, we were unable to cancel your old subscription automatically, so it is important that you cancel the old one yourself in your PayPal account, otherwise the old payments will continue along with new ones! Note this is the only time you will have to do this.', 'psts' ) . '</strong></p>';
								self::$complete_message .= '<p><a target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_subscr-find&alias=' . urlencode( get_site_option( "supporter_paypal_email" ) ) . '"><img src="' . $psts->plugin_url . 'images/cancel_subscribe_gen.gif" /></a><br /><small>' . __( 'You can also cancel following <a href="https://www.paypal.com/helpcenter/main.jsp;jsessionid=SCPbTbhRxL6QvdDMvshNZ4wT2DH25d01xJHj6cBvNJPGFVkcl6vV!795521328?t=solutionTab&ft=homeTab&ps=&solutionId=27715&locale=en_US&_dyncharset=UTF-8&countrycode=US&cmd=_help-ext">these steps</a>.', 'psts' ) . '</small></p>';
							} else if ( $old_gateway == 'Amazon' ) {
								self::$complete_message .= '<p><strong>' . __( 'Because of billing system upgrades, we were unable to cancel your old subscription automatically, so it is important that you cancel the old one yourself in your Amazon Payments account, otherwise the old payments will continue along with new ones! Note this is the only time you will have to do this.', 'psts' ) . '</strong></p>';
								self::$complete_message .= '<p>' . __( 'To view your subscriptions, simply go to <a target="_blank" href="https://payments.amazon.com/">https://payments.amazon.com/</a>, click Your Account at the top of the page, log in to your Amazon Payments account (if asked), and then click the Your Subscriptions link. This page displays your subscriptions, showing the most recent, active subscription at the top. To view the details of a specific subscription, click Details. Then cancel your subscription by clicking the Cancel Subscription button on the Subscription Details page.', 'psts' ) . '</p>';
							}

						} else {
							$psts->errors->add( 'general', sprintf( __( 'There was a problem with your Credit Card information:<br />"<strong>%s</strong>"<br />Please try again.', 'psts' ), self::parse_error_string( $resArray ) ) );
							$psts->log_action( $blog_id, sprintf( __( 'User modifying subscription via CC: PayPal returned a problem with Credit Card info: %s', 'psts' ), self::parse_error_string( $resArray ) ) );
						}

					} else { //new or expired signup

						//attempt initial direct payment
						$success        = $init_transaction = false;
						$domain         = ! empty( $domain ) ? $domain : ( ! empty( $process_data[ $signup_type ]['domain'] ) ? $process_data[ $signup_type ]['domain'] : '' );
						$path           = ! empty( $path ) ? $path : ( ! empty( $process_data[ $signup_type ]['path'] ) ? $process_data[ $signup_type ]['path'] : '' );
						$activation_key = ! empty( $process_data['activation'] ) ? $process_data['activation'] : '';

						if ( ! $is_trial ) {
							$resArray = PaypalApiHelper::DoDirectPayment( $initAmount, $_POST['period'], $desc, $blog_id, $_POST['level'], $cc_cardtype, $cc_number, $cc_month . $cc_year, $_POST['cc_cvv2'], $cc_firstname, $cc_lastname, $cc_address, $cc_address2, $cc_city, $cc_state, $cc_zip, $cc_country, $current_user->user_email, '', $activation_key );
							//Check if transaction was successful, store payment status after blog is activated
							if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {
								$init_transaction = $resArray["TRANSACTIONID"];
								$success          = true;
							}
						}
						//If Is trial or the payment transaction was successful
						if ( $is_trial || $success ) {
							//just in case, try to cancel any old subscription
							if ( ! empty( $blog_id ) && $profile_id = self::get_profile_id( $blog_id ) ) {
								$resArray = PaypalApiHelper::ManageRecurringPaymentsProfileStatus( $profile_id, 'Cancel', sprintf( __( 'Your %s subscription has been modified. This previous subscription has been canceled.', 'psts' ), $psts->get_setting( 'rebrand' ) ) );
							}

							//If transaction id is set ( No trial )
							if ( $init_transaction && ! empty( $blog_id ) ) {
								$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via CC: Initial payment successful (%1$s) - Transaction ID: %2$s', 'psts' ), $desc, $init_transaction ), $domain );
							} elseif ( $init_transaction && ! empty ( $domain ) ) {
								$psts->log_action( '', sprintf( __( 'User creating new subscription via CC: Initial payment successful (%1$s) - Transaction ID: %2$s', 'psts' ), $desc, $init_transaction ), $domain );
							}

							//use coupon
							if ( $has_coupon ) {
								$psts->use_coupon( $process_data['COUPON_CODE'], $blog_id, $domain );
							}

							//now attempt to create the subscription
							$resArray = PaypalApiHelper::CreateRecurringPaymentsProfileDirect( $paymentAmount, $initAmount, $_POST['period'], $desc, $blog_id, $_POST['level'], $cc_cardtype, $cc_number, $cc_month . $cc_year, $_POST['cc_cvv2'], $cc_firstname, $cc_lastname, $cc_address, $cc_address2, $cc_city, $cc_state, $cc_zip, $cc_country, $current_user->user_email, '', $activation_key );

							//If recurring profile was created successfully
							if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {
								$blog_id = ProSites_Helper_Registration::activate_blog( $activation_key, $is_trial, $_POST['period'], $_POST['level'] );

								if ( ! empty( $blog_id ) ) {
									//save new profile_id
									self::set_profile_id( $blog_id, $resArray["PROFILEID"] );

									//update the profile id in paypal so that future payments are applied to the proper blog id
									$custom = PSTS_PYPL_PREFIX . ':' . $blog_id . ':' . '' . ':' . $_POST['level'] . ':' . $_POST['period'] . ':' . $initAmount . ':' . $psts->get_setting( 'pypl_currency' ) . ':' . time();
									PaypalApiHelper::UpdateRecurringPaymentsProfile( $resArray["PROFILEID"], $custom );

									$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via CC: Subscription created (%1$s) - Profile ID: %2$s', 'psts' ), $desc, $resArray["PROFILEID"] ), $domain );

									//Store Payment , for rendering the confirmation on checkout page
									self::update_session_vars( $process_data, array(
										'blog_id' => $blog_id,
										'level'   => $level,
										'period'  => $period
									) );

									//Store activation key in Pro Sites table
									self::set_blog_identifier( $activation_key, $blog_id );

								} else {
									//Store in signup meta for domain
									self::set_profile_id( '', $resArray["PROFILEID"], $domain );
									$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via CC: Subscription created (%1$s) - Profile ID: %2$s', 'psts' ), $desc, $resArray["PROFILEID"] ) );

								}

							} else {
								self::$complete_message = __( 'Your initial payment was successful, but there was a problem creating the subscription with your credit card so you may need to renew when the first period is up. Your site should be upgraded shortly.', 'psts' ) . '<br />"<strong>' . self::parse_error_string( $resArray ) . '</strong>"';
								$psts->log_action( $blog_id, sprintf( __( 'User creating new subscription via CC: Problem creating the subscription after successful initial payment. User may need to renew when the first period is up: %s', 'psts' ), self::parse_error_string( $resArray ) ), $domain );
							}

							if ( ! empty( $blog_id ) ) {
								$psts->email_notification( $blog_id, 'success' );
								$psts->record_stat( $blog_id, 'signup' );
							}
							//now get the details of the transaction to see if initial payment went through
							if ( $init_transaction ) {
								$result = PaypalApiHelper::GetTransactionDetails( $init_transaction );
								if ( $result['PAYMENTSTATUS'] == 'Completed' || $result['PAYMENTSTATUS'] == 'Processed' ) {
									//Activate the domain , user signup for
									if ( ! empty( $domain ) ) {
										//Activate the blog
										$blog_id = ProSites_Helper_Registration::activate_blog( $activation_key, $is_trial, $_POST['period'], $_POST['level'] );
									}
									$psts->extend( $blog_id, $_POST['period'], self::get_slug(), $_POST['level'], $paymentAmount );

									//record last payment
									$psts->record_transaction( $blog_id, $init_transaction, $result['AMT'] );

									// Added for affiliate system link
									do_action( 'supporter_payment_processed', $blog_id, $result['AMT'], $_POST['period'], $_POST['level'] );

									if ( empty( self::$complete_message ) ) {
										self::$complete_message = sprintf( __( 'Your Credit Card subscription was successful! You should be receiving an email receipt at %s shortly.', 'psts' ), get_blog_option( $blog_id, 'admin_email' ) );
									}
								} else {
									update_blog_option( $blog_id, 'psts_waiting_step', 1 );
								}
							} else {
								$psts->extend( $blog_id, $_POST['period'], self::get_slug(), $_POST['level'], '', strtotime( '+ ' . $trial_days . ' days' ) );

								if ( empty( self::$complete_message ) ) {
									self::$complete_message = sprintf( __( 'Your Credit Card subscription was successful! You should be receiving an email receipt at %s shortly.', 'psts' ), get_blog_option( $blog_id, 'admin_email' ) );
								}
							}

							//display GA ecommerce in footer
							if ( ! $is_trial ) {
								$psts->create_ga_ecommerce( $blog_id, $_POST['period'], $initAmount, $_POST['level'], $cc_city, $cc_state, $cc_country );
							}

						} else {
							$psts->errors->add( 'general', sprintf( __( 'There was a problem with your credit card information:<br />"<strong>%s</strong>"<br />Please check all fields and try again.', 'psts' ), self::parse_error_string( $resArray ) ) );
						}

					}

				} else {
					$psts->errors->add( 'general', __( 'There was a problem with your credit card information. Please check all fields and try again.', 'psts' ) );
				}
			}
		}
	}

	public static function get_existing_user_information( $blog_id, $domain, $get_all = true ) {

		global $psts, $wpdb;
		$args         = array();
		$prev_billing = $next_billing = $content = '';

		if ( ! $blog_id ) {
			return;
		}

		$img_base     = $psts->plugin_url . 'images/';
		$cancel_label = __( 'Cancel Your Subscription', 'psts' );

		//Check if blog is still under trial
		$trialing = ProSites_Helper_Registration::is_trial( $blog_id );

		if ( $trialing ) {
			$args['trial'] = '<div id="psts-general-error" class="psts-warning">' . __( 'You are still within your trial period. Once your trial finishes your account will be automatically charged.', PSTS_TEXT_DOMAIN ) . '</div>';
		}

		//Check if payment is not yet confirmed
		if ( ! empty( $blog_id ) && 1 == get_blog_option( $blog_id, 'psts_waiting_step' ) ) {
			//Fetch, if payment status is pending and reason is stored
			$psts_payment_details = get_user_meta( get_current_user_id(), 'psts_payment_details', true );
			if ( ! empty( $psts_payment_details ) ) {
				$args['pending'] = '<div id="psts-general-error" class="psts-warning message">' . $psts_payment_details['pending_reason'] . __( '<br /> If you have enabled payment review for PayPal, make sure you accept the payment to avail the premium services.', PSTS_TEXT_DOMAIN ) . '</div>';
			} else {
				$args['pending'] = '<div id="psts-general-error" class="psts-warning">' . __( 'There are pending changes to your account. This message will disappear once these pending changes are completed.', PSTS_TEXT_DOMAIN ) . '</div>';
			}
		}

		//Check if its a recurring subscription or not
		$is_recurring      = $psts->is_blog_recurring( $blog_id );
		$args['recurring'] = $is_recurring;

		//if Payment was successful display a complete message
		if ( self::$complete_message ) {
			$content = '<div id="psts-complete-msg">' . self::$complete_message . '</div>';

			$args['complete_message']   = $content;
			$args['thanks_message']     = '<p>' . $psts->get_setting( 'pypl_thankyou' ) . '</p>';
			$args['visit_site_message'] = '<p><a href="' . get_admin_url( $blog_id, '', 'http' ) . '">' . __( 'Visit your newly upgraded site »', 'psts' ) . '</a></p>';
		}

		// Cancellation message
		if ( self::$cancel_message ) {
			$args['cancel']               = true;
			$args['cancellation_message'] = self::$cancel_message;
			self::$cancel_message         = false;
		}

		//check if pro/express user
		if ( $profile_id = self::get_profile_id( $blog_id ) ) {

			$end_date = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
			$level    = $psts->get_level_setting( $psts->get_level( $blog_id ), 'name' );

			$content = '<div id="psts_existing_info">';
			//show sub details
			$resArray = PaypalApiHelper::GetRecurringPaymentsProfileDetails( $profile_id );
			if ( ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) && $resArray['STATUS'] == 'Active' ) {

				if ( isset( $resArray['LASTPAYMENTDATE'] ) ) {
					$prev_billing = strtotime( $resArray['LASTPAYMENTDATE'] );
				} else if ( $last_payment = $psts->last_transaction( $blog_id ) ) {
					$prev_billing = $last_payment['timestamp'];
				} else {
//					$prev_billing = __( "None yet with this subscription <small>(only initial separate single payment has been made, or you've recently modified your subscription)</small>", 'psts' );
				}

				if ( isset( $resArray['NEXTBILLINGDATE'] ) ) {
					$next_billing = strtotime( $resArray['NEXTBILLINGDATE'] );
				} else {
//					$next_billing = __( "None", 'psts' );
				}

				$content .= '<h3>' . stripslashes( $resArray['DESC'] ) . '</h3><ul>';

				if ( is_pro_site( $blog_id ) ) {
					$args['level'] = $level;
				}

				if ( isset( $resArray['ACCT'] ) ) { //credit card
					$month                       = substr( $resArray['EXPDATE'], 0, 2 );
					$year                        = substr( $resArray['EXPDATE'], 2, 4 );
					$args['card_type']           = $resArray['CREDITCARDTYPE'];
					$args['card_acct']           = $resArray['ACCT'];
					$args['card_digit_location'] = 'end';
					$args['card_expire_month']   = $month;
					$args['card_expire_year']    = $year;
				} else { //paypal
					$content .= '<li>' . __( 'Payment Method: <strong>Your PayPal Account</strong>', 'psts' ) . '</li>';
				}

				$args['last_payment_date'] = $prev_billing;
				$args['next_payment_date'] = $next_billing;

				// Cancellation link
				if ( $is_recurring ) {
					if ( is_pro_site( $blog_id ) ) {
						$args['cancel_info'] = '<p class="prosites-cancel-description">' . sprintf( __( 'If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts' ), $level, $end_date ) . '</p>';
						// CSS class of <a> is important to handle confirmations
						$args['cancel_link'] = '<p class="prosites-cancel-link"><a class="cancel-prosites-plan button" href="' . wp_nonce_url( $psts->checkout_url( $blog_id ) . '&action=cancel', 'psts-cancel' ) . '" title="' . esc_attr( $cancel_label ) . '">' . esc_html( $cancel_label ) . '</a></p>';
					}
				}

			} else if ( ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) && $resArray['STATUS'] == 'Cancelled' ) {

				if ( $is_recurring ) {
					$args['cancel']               = true;
					$args['cancellation_message'] = '<div class="psts-cancel-notification">
													<p class="label"><strong>' . __( 'Your subscription has been canceled', 'psts' ) . '</strong></p>
													<p>' . sprintf( __( 'This site should continue to have %1$s features until %2$s.', 'psts' ), $level, $end_date ) . '</p>';
				}

			} else if ( $resArray['ACK'] == 'Success' || $resArray['ACK'] == 'SuccessWithWarning' ) {

				$content .= '<h3>' . sprintf( __( 'Your subscription is: %s', 'psts' ), $resArray['STATUS'] ) . '</h3>';
				$content .= '<p>' . __( 'Please update your payment information below to resolve this.', 'psts' ) . '</p>';

				if ( is_pro_site( $blog_id ) ) {
					$args['cancel_info'] = '<p>' . sprintf( __( 'If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts' ), $level, $end_date ) . '</p>';
				}
				$args['cancel_link'] = '<p><a id="pypl_cancel" href="' . wp_nonce_url( $psts->checkout_url( $blog_id ) . '&action=cancel', 'psts-cancel' ) . '" title="' . $cancel_label . '"><img src="' . $img_base . 'cancel_subscribe_gen.gif" /></a></p>';
			} else {
				$content .= '<div class="psts-error">' . __( "There was a problem accessing your subscription information: ", 'psts' ) . self::parse_error_string( $resArray ) . '</div>';
			}

			//print receipt send form
			$args['receipt_form'] = $psts->receipt_form( $blog_id );

		} else if ( is_pro_site( $blog_id ) ) {

			$end_date    = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
			$level       = $psts->get_level_setting( $psts->get_level( $blog_id ), 'name' );
			$old_gateway = $wpdb->get_var( "SELECT gateway FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'" );

			$content .= '<div id="psts_existing_info">';
			$content .= '<h3>' . __( 'Your Subscription Information', 'psts' ) . '</h3><ul>';
			$args['level'] = $level;

			if ( $old_gateway == 'PayPal' ) {
				$content .= '<li>' . __( 'Payment Method: <strong>Your PayPal Account</strong>', 'psts' ) . '</li>';
			} else if ( $old_gateway == 'Amazon' ) {
				$content .= '<li>' . __( 'Payment Method: <strong>Your Amazon Account</strong>', 'psts' ) . '</li>';
			} else if ( $psts->get_expire( $blog_id ) >= 9999999999 ) {
				$content .= '<li>' . __( 'Expire Date: <strong>Never</strong>', 'psts' ) . '</li>';
			} else {
				$content .= '<li>' . sprintf( __( 'Expire Date: <strong>%s</strong>', 'psts' ), $end_date ) . '</li>';
			}

			$content .= '</ul><br />';

			if ( $old_gateway == 'PayPal' || $old_gateway == 'Amazon' ) {
				$args['cancel_info'] = '<p>' . sprintf( __( 'If your subscription is still active your next scheduled payment should be %1$s.', 'psts' ), $end_date ) . '</p>';
				$args['cancel_info'] .= '<p>' . sprintf( __( 'If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts' ), $level, $end_date ) . '</p>';
				//show instructions for old gateways
				if ( $old_gateway == 'PayPal' ) {
					$args['cancel_link'] = '<p><a id="pypl_cancel" target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_subscr-find&alias=' . urlencode( get_site_option( "supporter_paypal_email" ) ) . '" title="' . $cancel_label . '"><img src="' . $psts->plugin_url . 'images/cancel_subscribe_gen.gif" /></a><br /><small>' . __( 'You can also cancel following <a href="https://www.paypal.com/helpcenter/main.jsp;jsessionid=SCPbTbhRxL6QvdDMvshNZ4wT2DH25d01xJHj6cBvNJPGFVkcl6vV!795521328?t=solutionTab&ft=homeTab&ps=&solutionId=27715&locale=en_US&_dyncharset=UTF-8&countrycode=US&cmd=_help-ext">these steps</a>.', 'psts' ) . '</small></p>';
				} else if ( $old_gateway == 'Amazon' ) {
					$args['cancel_link'] = '<p>' . __( 'To cancel your subscription, simply go to <a id="pypl_cancel" target="_blank" href="https://payments.amazon.com/">https://payments.amazon.com/</a>, click Your Account at the top of the page, log in to your Amazon Payments account (if asked), and then click the Your Subscriptions link. This page displays your subscriptions, showing the most recent, active subscription at the top. To view the details of a specific subscription, click Details. Then cancel your subscription by clicking the Cancel Subscription button on the Subscription Details page.', 'psts' ) . '</p>';
				}
			}

			//print receipt send form
			$args['receipt_form'] = $psts->receipt_form( $blog_id );

		}

		// Show all is true
		$args['all_fields'] = true;

		return $args;
	}

	/**
	 * Don't process PayPal on gateway render.
	 *
	 * It is called using 'psts_checkout_page_load' action because it requires redirection to Paypal
	 * @return bool
	 */
	public static function process_on_render() {
		return false;
	}

	/**
	 * Returns an array of currencies supported by Paypal
	 * @return array
	 */
	public static function get_supported_currencies() {

		return array(
			'AUD' => array( 'Australian Dollar', '24' ),
			'BRL' => array( 'Brazilian Real', '52, 24' ),
			'CAD' => array( 'Canadian Dollar', '24' ),
			'CHF' => array( 'Swiss Franc', '43, 48, 46' ),
			'CZK' => array( 'Czech Koruna', '4b, 10d' ),
			'DKK' => array( 'Danish Krone', '6b, 72' ),
			'EUR' => array( 'Euro', '20ac' ),
			'GBP' => array( 'British Pound', 'a3' ),
			'HKD' => array( 'Hong Kong Dollar', '24' ),
			'HUF' => array( 'Hungarian Forint', '46, 74' ),
			'ILS' => array( 'Israeli New Sheqel', '20aa' ),
			'JPY' => array( 'Japanese Yen', 'a5' ),
			'MXN' => array( 'Mexican Peso', '24' ),
			'MYR' => array( 'Malaysian Ringgit', '52, 4d' ),
			'NOK' => array( 'Norwegian Krone', '6b, 72' ),
			'NZD' => array( 'New Zealand Dollar', '24' ),
			'PHP' => array( 'Philippine Peso', '20b1' ),
			'PLN' => array( 'Polish Złoty', '7a, 142' ),
			'SEK' => array( 'Swedish Krona', '6b, 72' ),
			'SGD' => array( 'Singapore Dollar', '24' ),
			'THB' => array( 'Thai Baht', 'e3f' ),
			'TRY' => array( 'Turkish Lira', '20a4' ),
			'TWD' => array( 'New Taiwan Dollar', '4e, 54, 24' ),
			'USD' => array( 'United States Dollar', '24' ),
		);

	}

	/**
	 * Update activation key in Pro sites table,
	 * used for fetching blog id from activation key for Paypal IPN
	 *
	 * @param $key (Activation Key)
	 * @param $blog_id
	 *
	 * @return bool
	 */
	public static function set_blog_identifier( $key, $blog_id ) {
		global $wpdb;
		if ( empty ( $blog_id ) || empty( $key ) ) {
			return false;
		}
		//Store activation key in Pro Sites table
		$query = "UPDATE {$wpdb->base_prefix}pro_sites SET identifier='%s' WHERE blog_id='%d'";

		return $wpdb->query( $wpdb->prepare( $query, $key, $blog_id ) );
	}

	/**
	 * Checks pro site table for blog id against matching activation key, if not found
	 * fetches it from signup table, or return null
	 *
	 * @param $activation_key
	 */
	public static function blog_id_from_activation_key( $activation_key ) {
		global $wpdb;
		$query   = "SELECT blog_id from {$wpdb->base_prefix}pro_sites WHERE identifier='%s'";
		$blog_id = $wpdb->get_var( $wpdb->prepare( $query, $activation_key ) );

		//Try to get it from signup table
		if ( empty( $blog_id ) || ! is_wp_error( $blog_id ) ) {
			$blog_id = ProSites_Helper_ProSite::get_blog_id( $activation_key );
		}

		return $blog_id;
	}

	/**
	 * Update session variables for new signup or upgrade
	 *
	 * @param $process_data
	 * @param array $data
	 *
	 * @return bool|void
	 */
	public static function update_session_vars( $process_data, $data = array() ) {
		if ( empty( $process_data ) || empty( $data ) || empty( $data['blog_id'] ) ) {
			return false;
		}

		$blog_id = $data['blog_id'];

		//Store Payment , for rendering the confirmation on checkout page
		if ( isset( $process_data['new_blog_details'] ) ) {
			ProSites_Helper_Session::session( array( 'new_blog_details', 'blog_id' ), $blog_id );
			ProSites_Helper_Session::session( array( 'new_blog_details', 'payment_success' ), true );
		} else {
			if ( empty( $data['level'] ) || empty( $data['period'] ) ) {
				return;
			}
			$level  = $data['level'];
			$period = $data['period'];

			ProSites_Helper_Session::session( 'upgrade_blog_details', array() );
			ProSites_Helper_Session::session( array( 'upgrade_blog_details', 'blog_id' ), $blog_id );
			ProSites_Helper_Session::session( array( 'upgrade_blog_details', 'level' ), $level );
			ProSites_Helper_Session::session( array( 'upgrade_blog_details', 'period' ), $period );
			ProSites_Helper_Session::session( array(
				'upgrade_blog_details',
				'payment_success'
			), true );
		}
	}
}

//register the gateway
psts_register_gateway( 'ProSites_Gateway_PayPalExpressPro', __( 'Paypal Express/Pro', 'psts' ), __( 'Express Checkout is PayPal\'s premier checkout solution, which streamlines the checkout process for buyers and keeps them on your site after making a purchase. Enabling the optional PayPal Pro allows you to seamlessly accept credit cards on your site, and gives you the most professional look with a widely accepted payment method.', 'psts' ) );
