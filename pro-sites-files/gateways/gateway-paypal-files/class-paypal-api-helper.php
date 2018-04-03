<?php
/**
 * Paypal API function
 */
if ( ! class_exists( 'PaypalApiHelper' ) ) {
	class PaypalApiHelper {

		public static function SetExpressCheckout( $paymentAmount, $desc, $blog_id = '', $domain = '', $force_recurring = false ) {
			global $psts;

			$recurring = $psts->get_setting( 'recurring_subscriptions' );
			$nvpstr    = '';

			//Force recurring is used for Non recurring subscriptions with trial
			$recurring = $recurring ? $recurring : $force_recurring;

			if ( $recurring ) {
				$nvpstr .= "&L_BILLINGAGREEMENTDESCRIPTION0=" . urlencode( html_entity_decode( $desc, ENT_COMPAT, "UTF-8" ) );
				$nvpstr .= "&L_BILLINGTYPE0=RecurringPayments";
			} else {
				$nvpstr .= "&PAYMENTREQUEST_0_DESC=" . urlencode( html_entity_decode( $desc, ENT_COMPAT, "UTF-8" ) );
			}

			$checkout_url = $psts->checkout_url( $blog_id, $domain );

			// Make sure the URL is valid...
			$site_url      = str_replace( array( 'http://', 'https://' ), '', site_url() );
			$site_url      = str_replace( '/', '\/', $site_url );
			$test_checkout = preg_replace( '/' . $site_url . '$/', '', $checkout_url );
			if ( ! preg_match( '/' . $site_url . '/', $test_checkout ) ) {
				$scheme       = ( is_ssl() || force_ssl_admin() ? 'https' : 'http' );
				$checkout_url = site_url( $checkout_url, $scheme );
			}

			$checkout_url = add_query_arg(
				array(
					'action' => 'complete'
				),
				$checkout_url
			);
			$cancel_url   = add_query_arg(
				array(
					'action' => 'canceled'
				),
				$checkout_url
			);
			$nvpstr .= "&PAYMENTREQUEST_0_CURRENCYCODE=" . ProSites_Gateway_PayPalExpressPro::currency();
			$nvpstr .= "&PAYMENTREQUEST_0_AMT=" . $paymentAmount;
			$nvpstr .= "&PAYMENTREQUEST_0_PAYMENTACTION=Sale";
			$nvpstr .= "&LOCALECODE=" . $psts->get_setting( 'pypl_site' );
			$nvpstr .= "&NOSHIPPING=1";
			$nvpstr .= "&ALLOWNOTE=0";
			$nvpstr .= "&RETURNURL=" . urlencode( $checkout_url );
			$nvpstr .= "&CANCELURL=" . urlencode( $cancel_url );

			//formatting
			$nvpstr .= "&HDRIMG=" . urlencode( $psts->get_setting( 'pypl_header_img' ) );
			$nvpstr .= "&HDRBORDERCOLOR=" . urlencode( $psts->get_setting( 'pypl_header_border' ) );
			$nvpstr .= "&HDRBACKCOLOR=" . urlencode( $psts->get_setting( 'pypl_header_back' ) );
			$nvpstr .= "&PAYFLOWCOLOR=" . urlencode( $psts->get_setting( 'pypl_page_back' ) );

			$resArray = self::api_call( "SetExpressCheckout", $nvpstr );

			return $resArray;
		}

		/**
		 * Direct Payment for one time charges
		 *
		 * @param $token Obtained from setExpressCheckout call
		 * @param $payer_id
		 * @param $paymentAmount - Total Amount to be charged -> SetupFee + One Time payment
		 * @param $frequency
		 * @param $desc
		 * @param $blog_id
		 * @param $level
		 * @param string $activation_key
		 * @param string $tax
		 *
		 * @return bool
		 */
		public static function DoExpressCheckoutPayment( $token, $payer_id, $paymentAmount, $frequency, $desc, $blog_id, $level, $activation_key = '', $tax = '' ) {
			$req_amount = $paymentAmount + $tax;

			$nvpstr = "&TOKEN=" . urlencode( $token );
			$nvpstr .= "&PAYERID=" . urlencode( $payer_id );
			if ( ! defined( 'PSTS_NO_BN' ) ) {
				$nvpstr .= "&BUTTONSOURCE=incsub_SP";
			}
			$nvpstr .= "&PAYMENTREQUEST_0_AMT=$req_amount";
			$nvpstr .= "&PAYMENTREQUEST_0_ITEMAMT=$paymentAmount";
			$nvpstr .= "&L_BILLINGTYPE0=RecurringPayments";
			$nvpstr .= "&PAYMENTACTION=Sale";
			$nvpstr .= "&PAYMENTREQUEST_0_CURRENCYCODE=" . ProSites_Gateway_PayPalExpressPro::currency();
			$nvpstr .= "&PAYMENTREQUEST_0_DESC=" . urlencode( html_entity_decode( $desc, ENT_COMPAT, "UTF-8" ) );

			$nvpstr .= "&PAYMENTREQUEST_0_CUSTOM=" . PSTS_PYPL_PREFIX . '_' . $blog_id . '_' . $level . '_' . $frequency . '_' . $paymentAmount . '_' . ProSites_Gateway_PayPalExpressPro::currency() . '_' . time() . '_' . $activation_key;

			//Creates issue with IPN forwarder
//			$nvpstr .= "&PAYMENTREQUEST_0_NOTIFYURL=" . urlencode( network_site_url( 'wp-admin/admin-ajax.php?action=psts_pypl_ipn', 'admin' ) );

			if ( ! empty( $tax ) ) {
				$nvpstr .= "&PAYMENTREQUEST_0_TAXAMT=" . $tax;
			}
			$resArray = self::api_call( "DoExpressCheckoutPayment", $nvpstr );

			return $resArray;
		}

		/**
		 * Creates Recurring profile and charges a init amount if specified
		 *
		 * @param $token
		 * @param $paymentAmount - Subscription charges to be carried over
		 * @param $initAmount - Initial Charges - Includes setup fee if any, firs period subscription charges with/without discounted price
		 * @param $frequency
		 * @param $desc
		 * @param $blog_id
		 * @param $level
		 * @param bool|false $modify
		 * @param string $activation_key
		 * @param string $total_billing_cycle
		 * @param bool|false $tax
		 *
		 * @return bool
		 */
		public static function CreateRecurringPaymentsProfileExpress( $token, $paymentAmount, $frequency, $desc, $blog_id, $level, $modify = false, $activation_key = '', $total_billing_cycle = '', $tax = false, $has_trial = false ) {
			global $psts;

			$setup_fee = self::init_amount($blog_id, $level );

			$trial_days = $psts->get_setting( 'trial_days', 0 );

			// Update trial days if user is already on a trial (we don't want them to cheat into having longer free trials!)
			if( is_pro_trial( $blog_id ) ) {
				global $wpdb;

				$now    = time();
				if ( ! empty( $blog_id ) ) {
					$exists = $wpdb->get_var( $wpdb->prepare( "SELECT expire FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id ) );
				}
				$exists = $exists && $exists > $now ? $now - $exists : false;
				if ( $exists && $exists > 0 ) {
					$elapsed = floor( $exists / ( 60 * 60 * 24 ) );

					// Calculate remaining trial days
					if ( $elapsed <= $trial_days ) {
						$trial_days = $elapsed;
					} else {
						$trial_days = 0;
						$has_trial  = false;
					}
				}
			}


			$nvpstr = "&TOKEN=" . $token;
			$nvpstr .= "&AMT=" . $paymentAmount;

			//handle free trials
			if ( $has_trial ) {
				$nvpstr .= "&TRIALBILLINGPERIOD=Day";
				$nvpstr .= "&TRIALBILLINGFREQUENCY=" . $trial_days;
				$nvpstr .= "&TRIALTOTALBILLINGCYCLES=1";
				$nvpstr .= "&TRIALAMT=0.00";
			} //handle modification
			elseif ( $modify ) { // expiration is in the future\
				$nvpstr .= "&TRIALBILLINGPERIOD=Month";
				$nvpstr .= "&TRIALBILLINGFREQUENCY=$frequency";
				$nvpstr .= "&TRIALTOTALBILLINGCYCLES=1";
				//@todo:handle this mess
				$nvpstr .= "&TRIALAMT=" . round( $setup_fee, 2 );
			}
			if ( $has_trial ) {
				$nvpstr .= "&PROFILESTARTDATE=" . gmdate( 'Y-m-d\TH:i:s.00\Z', strtotime('now') );
			} else {
				$profile_start_date = ( ( $modify ) ? self::modStartDate( $modify ) : self::startDate( $frequency, 'month', $has_trial ) );

				$nvpstr .= "&PROFILESTARTDATE=" . $profile_start_date;
			}

			$nvpstr .= "&CURRENCYCODE=" . ProSites_Gateway_PayPalExpressPro::currency();
			$nvpstr .= "&BILLINGPERIOD=Month";
			$nvpstr .= "&BILLINGFREQUENCY=$frequency";

			//Non recurring subscription with trial
			if ( ! empty( $total_billing_cycle ) && $total_billing_cycle == 1 ) {
				$nvpstr .= "&TOTALBILLINGCYCLES=$total_billing_cycle";
			}
			$nvpstr .= "&DESC=" . urlencode( html_entity_decode( $desc, ENT_COMPAT, "UTF-8" ) );
			$nvpstr .= "&MAXFAILEDPAYMENTS=1";
			$nvpstr .= "&PROFILEREFERENCE=" . PSTS_PYPL_PREFIX . '_' . $blog_id . '_' . $level . '_' . $frequency . '_' . $paymentAmount . '_' . ProSites_Gateway_PayPalExpressPro::currency() . '_' . time() . '_' . $activation_key;

			//Tax Calculated for each payment
			if ( $tax ) {
				$nvpstr .= "&TAXAMT=" . $tax;
			}

			$resArray = self::api_call( "CreateRecurringPaymentsProfile", $nvpstr );

			return $resArray;
		}

		public static function CreateRecurringPaymentsProfileDirect( $paymentAmount, $frequency, $desc, $blog_id, $level, $cctype, $acct, $expdate, $cvv2, $firstname, $lastname, $street, $street2, $city, $state, $zip, $countrycode, $email, $modify = false, $activation_key = '', $total_billing_cycle = '', $tax = false ) {
			global $psts;

			$trial_days = $psts->get_setting( 'trial_days', 0 );
			$has_trial  = $psts->is_trial_allowed( $blog_id );
			$setup_fee = self::init_amount( $blog_id, $level );

			$nvpstr = "&AMT=" . $paymentAmount;

			//handle free trials
			if ( $has_trial ) {

				$nvpstr .= "&TRIALBILLINGPERIOD=Day";
				$nvpstr .= "&TRIALBILLINGFREQUENCY=" . $trial_days;
				$nvpstr .= "&TRIALTOTALBILLINGCYCLES=1";
				$nvpstr .= "&TRIALAMT=0.00";
				//handle modifications
			} elseif ( $modify ) { // expiration is in the future
				$nvpstr .= "&TRIALBILLINGPERIOD=Month";
				$nvpstr .= "&TRIALBILLINGFREQUENCY=$frequency";
				$nvpstr .= "&TRIALTOTALBILLINGCYCLES=1";
				//@todo:handle this mess
				$nvpstr .= "&TRIALAMT=" . round( $setup_fee, 2 );
			}
			if ( $has_trial ) {
				$nvpstr .= "&PROFILESTARTDATE=" . gmdate( 'Y-m-d\TH:i:s.00\Z', strtotime('now') );
			} else {
				$nvpstr .= "&PROFILESTARTDATE=" . ( ( $modify ) ? self::modStartDate( $modify ) : self::startDate( $frequency, 'month', $has_trial ) );
			}

			$nvpstr .= "&CURRENCYCODE=" . ProSites_Gateway_PayPalExpressPro::currency();
			$nvpstr .= "&BILLINGPERIOD=Month";
			$nvpstr .= "&BILLINGFREQUENCY=$frequency";

			//Non recurring subscription with trial
			if ( ! empty( $total_billing_cycle ) && $total_billing_cycle == 1 ) {
				$nvpstr .= "&TOTALBILLINGCYCLES=$total_billing_cycle";
			}

			$nvpstr .= "&DESC=" . urlencode( html_entity_decode( $desc, ENT_COMPAT, "UTF-8" ) );
			$nvpstr .= "&MAXFAILEDPAYMENTS=1";
			$nvpstr .= "&PROFILEREFERENCE=" . PSTS_PYPL_PREFIX . '_' . $blog_id . '_' . $level . '_' . $frequency . '_' . $paymentAmount . '_' . ProSites_Gateway_PayPalExpressPro::currency() . '_' . time() . '_' . $activation_key;
			$nvpstr .= "&CREDITCARDTYPE=$cctype";
			$nvpstr .= "&ACCT=$acct";
			$nvpstr .= "&EXPDATE=$expdate";
			$nvpstr .= "&CVV2=$cvv2";
			$nvpstr .= "&FIRSTNAME=$firstname";
			$nvpstr .= "&LASTNAME=$lastname";
			$nvpstr .= "&STREET=$street";
			$nvpstr .= "&STREET2=$street2";
			$nvpstr .= "&CITY=$city";
			$nvpstr .= "&STATE=$state";
			$nvpstr .= "&ZIP=$zip";
			$nvpstr .= "&COUNTRYCODE=$countrycode";
			$nvpstr .= "&EMAIL=$email";

			//Tax Calculated for payment
			if ( $tax ) {
				$nvpstr .= "&TAXAMT=" . $tax;
			}

			$resArray = self::api_call( "CreateRecurringPaymentsProfile", $nvpstr );

			return $resArray;
		}

		/**
		 * Direct payment for non recurring subscription using Credit Card
		 *
		 * @param $paymentAmount  Total Amount - including any setup fee + Discounted price for subscription
		 * @param $frequency
		 * @param $desc
		 * @param $blog_id
		 * @param $level
		 * @param $cctype
		 * @param $acct
		 * @param $expdate
		 * @param $cvv2
		 * @param $firstname
		 * @param $lastname
		 * @param $street
		 * @param $street2
		 * @param $city
		 * @param $state
		 * @param $zip
		 * @param $countrycode
		 * @param $email
		 * @param string $activation_key
		 * @param bool|false $tax
		 *
		 * @return bool
		 */
		public static function DoDirectPayment( $paymentAmount, $frequency, $desc, $blog_id, $level, $cctype, $acct, $expdate, $cvv2, $firstname, $lastname, $street, $street2, $city, $state, $zip, $countrycode, $email, $activation_key = '', $tax = false ) {

			$req_amount = $paymentAmount + $tax;
			$nvpstr = "&AMT=$req_amount";
			$nvpstr .= "&ITEMAMT=$paymentAmount";
			if ( ! defined( 'PSTS_NO_BN' ) ) {
				$nvpstr .= "&BUTTONSOURCE=incsub_SP";
			}
			$nvpstr .= "&IPADDRESS=" . $_SERVER['REMOTE_ADDR'];
			$nvpstr .= "&PAYMENTACTION=Sale";
			$nvpstr .= "&CURRENCYCODE=" . ProSites_Gateway_PayPalExpressPro::currency();
			$nvpstr .= "&DESC=" . urlencode( html_entity_decode( $desc, ENT_COMPAT, "UTF-8" ) );

			$nvpstr .= "&CUSTOM=" . PSTS_PYPL_PREFIX . '_' . $blog_id . '_' . $level . '_' . $frequency . '_' . $paymentAmount . '_' . ProSites_Gateway_PayPalExpressPro::currency() . '_' . time() . '_' . $activation_key;

			$nvpstr .= "&CREDITCARDTYPE=$cctype";
			$nvpstr .= "&ACCT=$acct";
			$nvpstr .= "&EXPDATE=$expdate";
			$nvpstr .= "&CVV2=$cvv2";
			$nvpstr .= "&FIRSTNAME=$firstname";
			$nvpstr .= "&LASTNAME=$lastname";
			$nvpstr .= "&STREET=$street";
			$nvpstr .= "&STREET2=$street2";
			$nvpstr .= "&CITY=$city";
			$nvpstr .= "&STATE=$state";
			$nvpstr .= "&ZIP=$zip";
			$nvpstr .= "&COUNTRYCODE=$countrycode";
			$nvpstr .= "&EMAIL=$email";
			//Tax Calculated for payment
			if ( $tax ) {
				$nvpstr .= "&TAXAMT=" . $tax;
			}

			$resArray = self::api_call( "DoDirectPayment", $nvpstr );

			return $resArray;
		}

		public static function GetExpressCheckoutDetails( $token ) {
			$nvpstr = "&TOKEN=" . $token;

			return self::api_call( 'GetExpressCheckoutDetails', $nvpstr );
		}

		public static function GetTransactionDetails( $transaction_id ) {

			$nvpstr = "&TRANSACTIONID=" . $transaction_id;

			$resArray = self::api_call( "GetTransactionDetails", $nvpstr );

			return $resArray;
		}

		public static function GetRecurringPaymentsProfileDetails( $profile_id ) {

			$nvpstr = "&PROFILEID=" . $profile_id;

			$resArray = self::api_call( "GetRecurringPaymentsProfileDetails", $nvpstr );

			return $resArray;
		}

		public static function ManageRecurringPaymentsProfileStatus( $profile_id, $action, $note ) {

			$nvpstr = "&PROFILEID=" . $profile_id;
			$nvpstr .= "&ACTION=$action"; //Should be Cancel, Suspend, Reactivate
			$nvpstr .= "&NOTE=" . urlencode( html_entity_decode( $note, ENT_COMPAT, "UTF-8" ) );

			$resArray = self::api_call( "ManageRecurringPaymentsProfileStatus", $nvpstr );

			return $resArray;
		}

		/**
		 * Retrieve the details of a previously created recurring payments profile.
		 *
		 * @access    public
		 *
		 * @param    array    call config data
		 *
		 * @return    array
		 */


		public static function GetRecurringPaymentsProfileStatus( $profile_id ) {

			$PayPalResult  = self::GetRecurringPaymentsProfileDetails( $profile_id );
			$PayPalErrors  = $PayPalResult['ERRORS'];
			$ProfileStatus = isset( $PayPalResult['STATUS'] ) ? $PayPalResult['STATUS'] : 'Unknown';

			$ResponseArray = array(
				'PayPalResult'  => $PayPalResult,
				'ProfileStatus' => $ProfileStatus
			);

			return $ResponseArray;
		}

		public static function UpdateRecurringPaymentsProfile( $profile_id, $custom ) {

			$nvpstr = "&PROFILEID=" . $profile_id;
			$nvpstr .= "&PROFILEREFERENCE=$custom";

			$resArray = self::api_call( "UpdateRecurringPaymentsProfile", $nvpstr );

			return $resArray;
		}

		public static function RefundTransaction( $transaction_id, $partial_amt = false, $note = '' ) {
			$nvpstr = "&TRANSACTIONID=" . $transaction_id;

			if ( $partial_amt ) {
				$nvpstr .= "&REFUNDTYPE=Partial";
				$nvpstr .= "&AMT=" . urlencode( $partial_amt );
				$nvpstr .= "&CURRENCYCODE=" . ProSites_Gateway_PayPalExpressPro::currency();
			} else {
				$nvpstr .= "&REFUNDTYPE=Full";
			}

			if ( $note ) {
				$nvpstr .= "&NOTE=" . urlencode( $note );
			}

			$resArray = self::api_call( "RefundTransaction", $nvpstr );

			return $resArray;
		}

		public static function api_call( $methodName, $nvpStr ) {
			global $psts;

			//set api urls
			if ( $psts->get_setting( 'pypl_status' ) == 'live' ) {
				$API_Endpoint = "https://api-3t.paypal.com/nvp";
			} else {
				$API_Endpoint = "https://api-3t.sandbox.paypal.com/nvp";
			}

			//NVPRequest for submitting to server
			$query_string = "METHOD=" . urlencode( $methodName ) . "&VERSION=63.0&PWD=" . urlencode( $psts->get_setting( 'pypl_api_pass' ) ) . "&USER=" . urlencode( $psts->get_setting( 'pypl_api_user' ) ) . "&SIGNATURE=" . urlencode( $psts->get_setting( 'pypl_api_sig' ) ) . $nvpStr;

			//build args
			$args['user-agent']  = "Pro Sites: http://premium.wpmudev.org/project/pro-sites | PayPal Express Gateway";
			$args['body']        = $query_string;
			$args['sslverify']   = false; //many servers don't have an updated CA bundle
			$args['timeout']     = 60;
			$args['httpversion'] = '1.1';

			//use built in WP http class to work with most server setups
			$response = wp_remote_post( $API_Endpoint, $args );

			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
				trigger_error( 'Pro Sites: Problem contacting PayPal API - ' . ( is_wp_error( $response ) ? $response->get_error_message() : 'Response code: ' . wp_remote_retrieve_response_code( $response ) ), E_USER_WARNING );

				return false;
			} else {
				//convert NVPResponse to an Associative Array
				$nvpResArray = self::deformatNVP( $response['body'] );

				return $nvpResArray;
			}
		}

		public static function RedirectToPayPal( $token ) {
			global $psts;

			//set api urls
			if ( $psts->get_setting( 'pypl_status' ) == 'live' ) {
				$paypalURL = "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout";
			} else {
				$paypalURL = "https://www.sandbox.paypal.com/webscr?cmd=_express-checkout";
			}

			// Redirect to paypal.com here
			$paypalURL = add_query_arg(
				array(
					'token' => $token
				),
				$paypalURL
			);
			wp_redirect( $paypalURL );
			exit;
		}

		//This function will take NVPString and convert it to an Associative Array and it will decode the response.
		public static function deformatNVP( $nvpstr ) {
			parse_str( $nvpstr, $nvpArray );

			return $nvpArray;
		}

		/**
		 * @param $frequency
		 * @param string $period
		 *
		 * @return string
		 */
		public static function startDate( $frequency, $period = 'month' ) {

			//As profile activation may take upto 24 hours, we do a initial payment and start profile fro next billing date
			$result = strtotime( "+$frequency $period" );

			$date = gmdate( 'Y-m-d\TH:i:s.00\Z', $result );

			return urlencode( $date );
		}

		/**
		 * @param $expire_stamp
		 *
		 * @return string
		 */
		public static function modStartDate( $expire_stamp ) {
			return urlencode( gmdate( 'Y-m-d\TH:i:s.00\Z', $expire_stamp ) );
		}

		/**
		 * Check trial and setup fee, and adds a init amount for recurring subs
		 *
		 * @param $nvpstr
		 * @param $has_trial
		 * @param $paymentAmount
		 * @param $initAmount
		 * @param $level
		 *
		 * @return string
		 */
		private static function init_amount( $blog_id, $level ) {
			global $psts;
			//If there is some init amount (Setup Fee)

				//apply setup fee (if applicable)
				$setup_fee = $psts->get_setting( 'setup_fee', 0 );

				if ( empty( $blog_id ) ) {
					if ( $level != 0 ) {
						$has_setup_fee = false;
					} else {
						$has_setup_fee = true;
					}
				} else {
					$has_setup_fee = $psts->has_setup_fee( $blog_id, $level );
				}

				if ( $has_setup_fee ) {
					$setup_fee = ! empty( $setup_fee ) ? round( $setup_fee, 2 ) : 0;
				} else {
					$setup_fee = 0;
				}


			return $setup_fee;
		}
	}
}