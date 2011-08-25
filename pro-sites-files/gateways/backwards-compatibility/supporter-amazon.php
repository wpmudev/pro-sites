<?php
/*
Pro Sites (Amazon Payments Gateway IPN handler for backwards compatibility)

If you have existing subscriptions using the old pre 3.0 Supporter Paypal gateway, then it is important to
overwrite the supporter-amazon.php file in your webroot with this one to prevent a lapse in subscription
payments being applied.
*/

if (!isset($_POST['signature'])) {
	// Did not find expected POST variables. Possible access attempt from a non Amazon site.
	writeToLog('Invalid visit to the IPN script from IP ' . $_SERVER['REMOTE_ADDR'] . "\n" . var_export($_POST, true));
	header('Status: 404 Not Found');
	exit;
} else {
	define('ABSPATH', dirname(__FILE__) . '/');
	require_once(ABSPATH . 'wp-load.php');
  global $wpdb, $psts;
  
	$secret_key = get_site_option("supporter_amazon_secretkey");
	$access_key = get_site_option("supporter_amazon_accesskey");
	$utils = new Amazon_FPS_SignatureUtilsForOutbound($aws_access_key, $aws_secret_key);

	$self_address = 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

	$valid = $utils->validateRequest($_POST, $self_address, "POST");
	if (!$valid) {
		header('Status: 401 Unauthorized');
		exit();
	}

  list($blog_id, $period, $amount, $type, $stamp) = explode('_', $_POST['referenceId']);

	// process Amazon response
	switch ($_POST['status']) {

		case 'PI':
			// case: in-progress
			$note = 'Amazon payment has been initiated. It will take between five seconds and 48 hours to complete, based on the availability of external payment networks and the riskiness of the transaction.';
      $psts->log_action( $blog_id, $note );
			break;

    case 'PendingUserAction':
			// case: payment is pending
			$note = 'Amazon tried to collect a payment which failed due to a payment method error. The subscriber has been advised to adjust the method. Amazon will retry the payment after 6 days.';
			$psts->log_action( $blog_id, $note );
			break;

		case 'PaymentRescheduled':
			// case: payment is pending
			$note = 'Amazon tried to collect a payment which failed due to an error not involving a payment method. Amazon will retry the payment after 6 days.';
			$psts->log_action( $blog_id, $note );
			break;

		case 'PS':
		case 'PaymentSuccess':
			// case: successful payment
      $amount = str_replace('USD ', '', $_POST['transactionAmount']);
   		$psts->extend($blog_id, $period, 'Amazon', 1, $amount);
			$note = 'Amazon subscription payment by '.$_POST['paymentMethod'].' received and applied.';
   		$psts->log_action( $blog_id, $note );

			// Added for affiliate system link
			do_action('supporter_payment_processed', $bid, $amount, $period);
			break;

		case 'PF':
			// case: failed payment
			$note = 'The Amazon payment transaction failed and the money was not transferred.';

   		$psts->withdraw($blog_id, $period);
   		$psts->log_action( $blog_id, $note );

			break;

		case 'PaymentCancelled':
			// case: Cancelled
			$note = 'Amazon has failed to collect a payment, and will not make any more attempts. Other subscription payments will be attempted on schedule.';

   		$psts->withdraw($blog_id, $period);
   		$psts->log_action( $blog_id, $note );

			break;

		case 'RS':
			// case: refund

			//check for partial refund and remove only that amount from subscription
			$refund_amount = ltrim($_POST['transactionAmount'], 'USD ');
			if ($amount > $refund_amount) {
			  $percent = $refund_amount / $amount;
        $extend = $period * 31 * 24 * 60 * 60;
        $period = round($extend * $percent);
      }
      $note = "The Amazon refund transaction was successful for $refund_amount.";

      $psts->withdraw($blog_id, $period);
   		$psts->log_action( $blog_id, $note );

			break;

    //check for subscription details
    case 'SubscriptionSuccessful':
		  break;

		case 'SubscriptionCanceled':
		case 'SubscriptionCompleted':

			$psts->log_action( $blog_id, 'The Amazon subscription has been canceled.' );

	    //only send email if it's not a modification
			if (!get_blog_option($blog_id, 'psts_paypal_profile_id')) {
			  $psts->record_stat($blog_id, 'cancel');
	      $psts->email_notification($blog_id, 'canceled');
			}
		  break;

		default:
			// case: various error cases
	}


}


/*******************************************************************************
 *	Copyright 2008-2010 Amazon Technologies, Inc.
 *	Licensed under the Apache License, Version 2.0 (the 'License');
 *
 *	You may not use this file except in compliance with the License.
 *	You may obtain a copy of the License at: http://aws.amazon.com/apache2.0
 *	This file is distributed on an 'AS IS' BASIS, WITHOUT WARRANTIES OR
 *	CONDITIONS OF ANY KIND, either express or implied. See the License for the
 *	specific language governing permissions and limitations under the License.
 ******************************************************************************/

if (!class_exists('Amazon_FPS_SignatureUtilsForOutbound')) {
	class Amazon_FPS_SignatureUtilsForOutbound {

	    const SIGNATURE_KEYNAME = "signature";
	    const SIGNATURE_METHOD_KEYNAME = "signatureMethod";
	    const SIGNATURE_VERSION_KEYNAME = "signatureVersion";
	    const SIGNATURE_VERSION_2 = "2";
	    const CERTIFICATE_URL_KEYNAME = "certificateUrl";
	    const FPS_PROD_ENDPOINT = 'https://fps.amazonaws.com/';
	    const FPS_SANDBOX_ENDPOINT = 'https://fps.sandbox.amazonaws.com/';
	    const USER_AGENT_IDENTIFIER = 'SigV2_MigrationSampleCode_PHP-2010-09-13';


		//Your AWS access key
		var $aws_access_key;

		//Your AWS secret key. Required only for ipn or return url verification signed using signature version1.
		var $aws_secret_key;

	    function __construct ($aws_access_key = null, $aws_secret_key = null) {
	        $this->aws_access_key = $aws_access_key;
	        $this->aws_secret_key = $aws_secret_key;
	    }

	    function Amazon_FPS_SignatureUtilsForOutbound ($aws_access_key = null, $aws_secret_key = null) {
	    	$this->__construct($aws_access_key, $aws_secret_key);
	    }

	    /**
	     * Validates the request by checking the integrity of its parameters.
	     * @param parameters - all the http parameters sent in IPNs or return urls.
	     * @param urlEndPoint should be the url which recieved this request.
	     * @param httpMethod should be either POST (IPNs) or GET (returnUrl redirections)
	     */
	    function validateRequest ($parameters, $urlEndPoint, $httpMethod)  {
	        return $this->validateSignatureV2($parameters, $urlEndPoint, $httpMethod);
	    }

	    /**
	     * Verifies the signature.
	     * Only default algorithm OPENSSL_ALGO_SHA1 is supported.
	     */
	    function validateSignatureV2 ($parameters, $urlEndPoint, $httpMethod) {
		    $signature = $parameters[self::SIGNATURE_KEYNAME];
		    if (!isset($signature)) return false; //'signature' is missing from the parameters.
		    $signatureMethod = $parameters[self::SIGNATURE_METHOD_KEYNAME];

		    if (!isset($signatureMethod)) return false; // 'signatureMethod' is missing from the parameters.
		    $signatureAlgorithm = self::getSignatureAlgorithm($signatureMethod);

		    if (!isset($signatureAlgorithm)) return false; // 'signatureMethod' present in parameters is invalid. Valid values are: RSA-SHA1
		    $certificateUrl = $parameters[self::CERTIFICATE_URL_KEYNAME];

		    if (!isset($certificateUrl)) return false; // 'certificateUrl' is missing from the parameters.
		    elseif((stripos($parameters[self::CERTIFICATE_URL_KEYNAME], self::FPS_PROD_ENDPOINT) !== 0)
		        && (stripos($parameters[self::CERTIFICATE_URL_KEYNAME], self::FPS_SANDBOX_ENDPOINT) !== 0)) return false; // The `certificateUrl` value must begin with self::FPS_PROD_ENDPOINT or self::FPS_SANDBOX_ENDPOINT

		    $verified = $this->verifySignature($parameters, $urlEndPoint);
		    if (!$verified) return false; // Certificate could not be verified by the FPS service

		     return $verified;

		}

		function httpsRequest ($url){
			// Compose the cURL request
			$curlHandle = curl_init();
			curl_setopt($curlHandle, CURLOPT_URL, $url);
			curl_setopt($curlHandle, CURLOPT_FILETIME, false);
			curl_setopt($curlHandle, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 0);
			//curl_setopt($curlHandle, CURLOPT_CAINFO, getcwd().'/ca-bundle.crt');
			curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, false);
			curl_setopt($curlHandle, CURLOPT_MAXREDIRS, 0);
			curl_setopt($curlHandle, CURLOPT_HEADER, true);
			curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curlHandle, CURLOPT_NOSIGNAL, true);
			curl_setopt($curlHandle, CURLOPT_USERAGENT, self::USER_AGENT_IDENTIFIER);

			// Execute the request
			$response = curl_exec($curlHandle);

			// Grab only the body
			$headerSize = curl_getinfo($curlHandle, CURLINFO_HEADER_SIZE);
			$responseBody = substr($response, $headerSize);

			curl_close($curlHandle);
			return $responseBody;
		}

		/**
		 * Method: verify_signature
		 */
		function verifySignature ($parameters, $urlEndPoint){
			// Switch hostnames
			if (stripos($parameters[self::CERTIFICATE_URL_KEYNAME], self::FPS_SANDBOX_ENDPOINT) === 0){
				$fpsServiceEndPoint = self::FPS_SANDBOX_ENDPOINT;
			}
			elseif (stripos($parameters[self::CERTIFICATE_URL_KEYNAME], self::FPS_PROD_ENDPOINT) === 0){
				$fpsServiceEndPoint = self::FPS_PROD_ENDPOINT;
			}

			$url = $fpsServiceEndPoint . '?Action=VerifySignature&UrlEndPoint=' . rawurlencode($urlEndPoint);

			$queryString = rawurlencode(http_build_query($parameters, '', '&'));

			$url .= '&HttpParameters=' . $queryString . '&Version=2008-09-17';

			$response = $this->httpsRequest($url);
			/*
			$xml = new SimpleXMLElement($response);
			$result = (string) $xml->VerifySignatureResult->VerificationStatus;

			return ($result === 'Success');
			*/

			// We're avoiding SimpleXMLElement
			return preg_match('~' . preg_quote('<VerificationStatus>') . '\s*Success\s*' . preg_quote('</VerificationStatus>') . '~', $response);
		}



	    static function getSignatureAlgorithm ($signatureMethod) {
	        if ("RSA-SHA1" == $signatureMethod) {
	            return OPENSSL_ALGO_SHA1;
	        }
	        return null;
	    }

	}
}
?>