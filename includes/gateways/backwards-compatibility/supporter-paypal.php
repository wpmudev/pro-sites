<?php
/*
Pro Sites (Paypal Gateway IPN handler for backwards compatibility)

If you have existing subscriptions using the old pre 3.0 Supporter Paypal gateway, then it is important to
overwrite the supporter-paypal.php file in your webroot with this one to prevent a lapse in subscription
payments being applied.
*/
if ( ( isset( $_POST['payment_status'] ) || isset( $_POST['txn_type'] ) ) && isset( $_POST['custom'] ) ) {

	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
	require_once( ABSPATH . 'wp-load.php' );
	global $wpdb, $psts;

	if ( get_site_option( "supporter_paypal_status" ) == 'test' ) {
		$domain = 'https://www.sandbox.paypal.com';
		$host   = 'www.sandbox.paypal.com';
	} else {
		$domain = 'https://www.paypal.com';
		$host   = 'www.paypal.com';
	}

	$req = 'cmd=_notify-validate';
	foreach ( $_POST as $k => $v ) {
		if ( get_magic_quotes_gpc() ) {
			$v = stripslashes( $v );
		}
		$req .= '&' . $k . '=' . urlencode( $v );
	}

	$header = 'POST /cgi-bin/webscr HTTP/1.1' . "\r\n"
	          . 'Content-Type: application/x-www-form-urlencoded' . "\r\n"
	          . 'Content-Length: ' . strlen( $req ) . "\r\n"
	          . "Host: $host\r\n"
	          . "\r\n";

	@set_time_limit( 60 );
	if ( $conn = @fsockopen( $domain, 80, $errno, $errstr, 30 ) ) {
		fputs( $conn, $header . $req );
		socket_set_timeout( $conn, 30 );

		$response         = '';
		$close_connection = false;
		while ( true ) {
			if ( feof( $conn ) || $close_connection ) {
				fclose( $conn );
				break;
			}

			$st = @fgets( $conn, 4096 );
			if ( $st === false ) {
				$close_connection = true;
				continue;
			}

			$response .= $st;
		}

		$error = '';
		$lines = explode( "\n", str_replace( "\r\n", "\n", $response ) );
		// looking for: HTTP/1.1 200 OK
		if ( count( $lines ) == 0 ) {
			$error = 'Response Error: Header not found';
		} else if ( substr( $lines[0], - 7 ) != ' 200 OK' ) {
			$error = 'Response Error: Unexpected HTTP response';
		} else {
			// remove HTTP header
			while ( count( $lines ) > 0 && trim( $lines[0] ) != '' ) {
				array_shift( $lines );
			}

			// first line will be empty, second line will have the result
			if ( count( $lines ) < 2 ) {
				$error = 'Response Error: No content found in transaction response';
			} else if ( strtoupper( trim( $lines[1] ) ) != 'VERIFIED' ) {
				$error = 'Response Error: Unexpected transaction response';
			}
		}

		if ( $error != '' ) {
			echo $error;
			exit;
		}
	}

	//get custom value
	list( $blog_id, $period, $amount, $currency, $stamp ) = explode( '_', $_POST['custom'] );

	// process PayPal response
	$new_status = false;

	$profile_string = ( isset( $_POST['recurring_payment_id'] ) ) ? ' - ' . $_POST['recurring_payment_id'] : '';
	$payment_status = $_POST['payment_status'];

	switch ( $payment_status ) {

		case 'Canceled-Reversal':
			$psts->log_action( $blog_id, sprintf( __( 'Old PayPal IPN "%s" received: A reversal has been canceled; for example, when you win a dispute and the funds for the reversal have been returned to you.', 'psts' ), $payment_status ) . $profile_string );
			break;

		case 'Expired':
			$psts->log_action( $blog_id, sprintf( __( 'Old PayPal IPN "%s" received: The authorization period for this payment has been reached.', 'psts' ), $payment_status ) . $profile_string );
			break;

		case 'Voided':
			$psts->log_action( $blog_id, sprintf( __( 'Old PayPal IPN "%s" received: An authorization for this transaction has been voided.', 'psts' ), $payment_status ) . $profile_string );
			break;

		case 'Failed':
			$psts->log_action( $blog_id, sprintf( __( 'Old PayPal IPN "%s" received: The payment has failed. This happens only if the payment was made from your customer\'s bank account.', 'psts' ), $payment_status ) . $profile_string );
			$psts->email_notification( $blog_id, 'failed' );
			break;

		case 'Partially-Refunded':
			$psts->log_action( $blog_id, sprintf( __( 'Old PayPal IPN "%s" received: The payment has been partially refunded with %s.', 'psts' ), $payment_status, $psts->format_currency( $_POST['mc_currency'], $_POST['mc_gross'] ) ) . $profile_string );
			break;

		case 'In-Progress':
			$psts->log_action( $blog_id, sprintf( __( 'Old PayPal IPN "%s" received: The transaction has not terminated, e.g. an authorization may be awaiting completion.', 'psts' ), $payment_status ) . $profile_string );
			break;

		case 'Reversed':
			$status          = __( 'A payment was reversed due to a chargeback or other type of reversal. The funds have been removed from your account balance and returned to the buyer: ', 'psts' );
			$reverse_reasons = array(
				'none'                     => '',
				'chargeback'               => __( 'A reversal has occurred on this transaction due to a chargeback by your customer.', 'psts' ),
				'chargeback_reimbursement' => __( 'A reversal has occurred on this transaction due to a reimbursement of a chargeback.', 'psts' ),
				'chargeback_settlement'    => __( 'A reversal has occurred on this transaction due to settlement of a chargeback.', 'psts' ),
				'guarantee'                => __( 'A reversal has occurred on this transaction due to your customer triggering a money-back guarantee.', 'psts' ),
				'buyer-complaint'          => __( 'A reversal has occurred on this transaction due to a complaint about the transaction from your customer.', 'psts' ),
				'refund'                   => __( 'A reversal has occurred on this transaction because you have given the customer a refund.', 'psts' ),
				'other'                    => __( 'A reversal has occurred on this transaction due to an unknown reason.', 'psts' )
			);
			$status .= $reverse_reasons[ $_POST["reason_code"] ];
			$psts->log_action( $blog_id, sprintf( __( 'Old PayPal IPN "%s" received: %s', 'psts' ), $payment_status, $status ) . $profile_string );
			$psts->withdraw( $blog_id, $period );
			break;

		case 'Refunded':
			$psts->log_action( $blog_id, sprintf( __( 'Old PayPal IPN "%s" received: You refunded the payment with %s.', 'psts' ), $payment_status, $psts->format_currency( $_POST['mc_currency'], $_POST['mc_gross'] ) ) . $profile_string );
			break;

		case 'Denied':
			$psts->log_action( $blog_id, sprintf( __( 'Old PayPal IPN "%s" received: You denied the payment when it was marked as pending.', 'psts' ), $payment_status ) . $profile_string );
			$psts->withdraw( $blog_id, $period );
			break;

		case 'Completed':
		case 'Processed':
			// case: successful payment

			//receipts and record new transaction
			if ( $_POST['txn_type'] == 'subscr_payment' || $_POST['txn_type'] == 'recurring_payment' || $_POST['txn_type'] == 'express_checkout' ) {
				$psts->log_action( $blog_id, sprintf( __( 'Old PayPal IPN "%s" received: %s %s payment received, transaction ID %s', 'psts' ), $payment_status, $psts->format_currency( $_POST['mc_currency'], $_POST['mc_gross'] ), $_POST['txn_type'], $_POST['txn_id'] ) . $profile_string );
				$psts->extend( $blog_id, $period, 'PayPal', 1, $_POST['mc_gross'] );
				update_blog_option( $blog_id, 'pypl_old_last_info', $_POST );
			}

			break;

		case 'Pending':
			// case: payment is pending
			$pending_str = array(
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
			$reason      = @$_POST['pending_reason'];
			$psts->log_action( $blog_id, sprintf( __( 'PayPal IPN "%s" received: Last payment is pending (%s). Reason: %s', 'psts' ), $payment_status, $_POST['txn_id'], $pending_str[ $reason ] ) . $profile_string );
			break;

		default:
			// case: various error cases

	}

	//cancelled subscriptions
	if ( $_POST['txn_type'] == 'subscr_cancel' ) {
		$psts->log_action( $blog_id, sprintf( __( 'Old PayPal subscription IPN "%s" received. The subscription has been canceled.', 'psts' ), $_POST['txn_type'] ) . $profile_string );


		//only send email if it's not a modification
		if ( ! get_blog_option( $blog_id, 'psts_paypal_profile_id' ) ) {
			$psts->record_stat( $blog_id, 'cancel' );
			$psts->email_notification( $blog_id, 'canceled' );
		}
	}

} else {
	// Did not find expected POST variables. Possible access attempt from a non PayPal site.
	header( 'Status: 404 Not Found' );
	echo 'Error: Missing POST variables. Identification is not possible.';
	exit;
}

?>