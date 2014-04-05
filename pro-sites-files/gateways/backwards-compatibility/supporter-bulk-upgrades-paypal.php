<?php
/*
Pro Sites (Feature: Bulk upgrades IPN handler pre 3.0 backwards compatibility)

If you previously used Bulk Upgrades with a recurring subscription, it is important to place this file in your webroot.
*/

/* 
Copyright 2007-2011 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


if (!isset($_POST['payment_status'])) {
	// Did not find expected POST variables. Possible access attempt from a non PayPal site.
	header('Status: 404 Not Found');
	exit;
} else if (!isset($_POST['custom'])) {
	echo 'Error: Missing POST variables. Identification is not possible.';
	exit;
} else {
	
	require_once( dirname(__FILE__) . '/wp-load.php' );

	global $psts, $ProSites_Module_BulkUpgrades;

	if ( $psts->get_setting('bu_status') == 'live' ) {
		$domain = 'https://www.paypal.com/cgi-bin/webscr';
	} else {
		$domain = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
	}

	$req = 'cmd=_notify-validate';
	foreach ($_POST as $k => $v) {
		if (get_magic_quotes_gpc()) $v = stripslashes($v);
		$req .= '&' . $k . '=' . urlencode($v);
	}

	$args['user-agent'] = "Pro Sites/{$psts->version}: http://premium.wpmudev.org/project/pro-sites | PayPal Bulk Upgrades/{$psts->version}";
	$args['body'] = $req;
	$args['sslverify'] = false;

	//use built in WP http class to work with most server setups
	$response = wp_remote_post($domain, $args);

	//check results
	if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200 || $response['body'] != 'VERIFIED') {
		header("HTTP/1.1 503 Service Unavailable");
		_e( 'There was a problem verifying the IPN string with PayPal. Please try again.', 'psts' );
		exit;
	}

	// process PayPal response
	switch ($_POST['payment_status']) {
		case 'Partially-Refunded':
			break;

		case 'In-Progress':
			break;

		case 'Completed':
		case 'Processed':
			// case: successful payment
			list($bid, $uid, $credits, $amount, $currency, $stamp) = explode('_', $_POST['custom']);
			//supporter_insert_update_transaction($bid, $_POST['txn_id'], $_POST['payment_type'], $stamp, $amount, $currency, $_POST['payment_status']);
			$ProSites_Module_BulkUpgrades->credit_credits($uid, $credits);
			$ProSites_Module_BulkUpgrades->update_note($uid, '');
			break;

		case 'Reversed':
			// case: charge back
			$note = __('Last transaction has been reversed. Reason: Payment has been reversed (charge back)', 'psts');
			list($bid, $uid, $credits, $amount, $currency, $stamp) = explode('_', $_POST['custom']);
			//supporter_insert_update_transaction($bid, $_POST['parent_txn_id'], $_POST['payment_type'], $stamp, $amount, $currency, $_POST['payment_status']);
			$ProSites_Module_BulkUpgrades->debit_credits($uid, $credits);
			$ProSites_Module_BulkUpgrades->update_note($uid, $note);
			break;

		case 'Refunded':
			// case: refund
			$note = __('Last transaction has been reversed. Reason: Payment has been refunded', 'psts');
			list($bid, $uid, $credits, $amount, $currency, $stamp) = explode('_', $_POST['custom']);
			//supporter_insert_update_transaction($bid, $_POST['parent_txn_id'], $_POST['payment_type'], $stamp, $amount, $currency, $_POST['payment_status']);
			$ProSites_Module_BulkUpgrades->debit_credits($uid, $credits);
			$ProSites_Module_BulkUpgrades->update_note($uid, $note);
			break;

		case 'Denied':
			// case: denied
			$note = __('Last transaction has been reversed. Reason: Payment Denied', 'psts');
			list($bid, $uid, $credits, $amount, $currency, $stamp) = explode('_', $_POST['custom']);
			$ProSites_Module_BulkUpgrades->update_note($uid, $note);
			break;

		case 'Pending':
			// case: payment is pending
			$pending_str = array(
				'address' => __('Customer did not include a confirmed shipping address', 'psts'),
				'authorization' => __('Funds not captured yet', 'psts'),
				'echeck' => __('eCheck that has not cleared yet', 'psts'),
				'intl' => __('Payment waiting for aproval by service provider', 'psts'),
				'multi-currency' => __('Payment waiting for service provider to handle multi-currency process', 'psts'),
				'unilateral' => __('Customer did not register or confirm his/her email yet', 'psts'),
				'upgrade' => __('Waiting for service provider to upgrade the PayPal account', 'psts'),
				'verify' => __('Waiting for service provider to verify his/her PayPal account', 'psts'),
				'*' => ''
				);
			$reason = @$_POST['pending_reason'];
			$note = __('Last transaction is pending. Reason: ', 'psts') . (isset($pending_str[$reason]) ? $pending_str[$reason] : $pending_str['*']);
			list($bid, $uid, $credits, $amount, $currency, $stamp) = explode('_', $_POST['custom']);
			//supporter_insert_update_transaction($bid, $_POST['txn_id'], $_POST['payment_type'], $stamp, $amount, $currency, $_POST['payment_status']);
			$ProSites_Module_BulkUpgrades->update_note($uid, $note);
			break;

		default:
			// case: various error cases
	}
	die('IPN Recorded');
}
?>