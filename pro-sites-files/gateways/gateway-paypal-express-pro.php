<?php
/*
Pro Sites (Gateway: Paypal Express/Pro Payment Gateway)
*/
class ProSites_Gateway_PayPalExpressPro {

	var $complete_message = false;
	
	function ProSites_Gateway_PayPalExpressPro() {
		$this->__construct();
	}

  function __construct() {
    //settings
		add_action( 'psts_gateway_settings', array(&$this, 'settings') );
		add_filter( 'psts_settings_filter', array(&$this, 'settings_process') );
		
		//checkout stuff
		add_action( 'psts_checkout_page_load', array(&$this, 'process_checkout') );
		add_filter( 'psts_checkout_output', array(&$this, 'checkout_screen'), 10, 2 );
		add_filter( 'psts_force_ssl', array(&$this, 'force_ssl') );
		
		//handle IPN notifications
		add_action( 'wp_ajax_nopriv_psts_pypl_ipn', array(&$this, 'ipn_handler') );
		
		//plug management page
		add_action( 'psts_subscription_info', array(&$this, 'subscription_info') );
		add_action( 'psts_subscriber_info', array(&$this, 'subscriber_info') );
		add_action( 'psts_modify_form', array(&$this, 'modify_form') );
		add_action( 'psts_modify_process', array(&$this, 'process_modify') );
		add_action( 'psts_transfer_pro', array(&$this, 'process_transfer'), 10, 2 );
		
		//filter payment info
		add_action( 'psts_payment_info', array(&$this, 'payment_info'), 10, 2 );
		
		//cancel subscriptions on blog deletion
		add_action( 'delete_blog', array(&$this, 'cancel_blog_subscription') );
		
    /* This sets the default prefix to the paypal custom field,
		 * in case you use the same account for multiple IPN requiring scripts,
		 * and want to setup your own forwarding script somewhere to pass IPNs to
		 * the proper location. If that is the case you will also need to define
		 * PSTS_IPN_PASSWORD and post "inc_pass" along with the IPN string.
		 */
    if ( !defined('PSTS_PYPL_PREFIX') )
      define('PSTS_PYPL_PREFIX', 'psts');

	}

	function settings() {
	  global $psts;
		?>
		<div class="postbox">
	  <h3 class='hndle'><span><?php _e('Paypal Express/Pro', 'psts') ?></span> - <span class="description"><?php _e('Express Checkout is PayPal\'s premier checkout solution, which streamlines the checkout process for buyers and keeps them on your site after making a purchase.', 'psts'); ?></span></h3>
    <div class="inside">
        <p><?php _e('Unlike PayPal Pro, there are no additional fees to use Express Checkout, though you may need to do a free upgrade to a business account. <a target="_blank" href="https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_api_ECGettingStarted">More Info &raquo;</a>', 'psts'); ?></p>
        <p><?php printf(__('To use PayPal Express Checkout or Pro you must <a href="https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_admin_IPNSetup#id089EG030E5Z" target="_blank">manually turn on IPN notifications</a> and enter your IPN url (<strong>%s</strong>) in your PayPal profile (you must also do this in your sandbox account when testing).', 'psts'), network_site_url('wp-admin/admin-ajax.php?action=psts_pypl_ipn', 'admin')); ?></p>
			  <table class="form-table">
			  <tr valign="top">
			  <th scope="row"><?php _e('PayPal Site', 'psts') ?></th>
			  <td><select name="psts[pypl_site]">
			  <?php
	      $paypal_site = $psts->get_setting('pypl_site');
	      $sel_locale = empty($paypal_site) ? 'US' : $paypal_site;
	      $locales = array(
					'AR'	=> 'Argentina',
					'AU'	=> 'Australia',
					'AT'	=> 'Austria',
					'BE'	=> 'Belgium',
					'BR'	=> 'Brazil',
					'CA'	=> 'Canada',
					'CN'	=> 'China',
					'FR'	=> 'France',
					'DE'	=> 'Germany',
					'HK'	=> 'Hong Kong',
					'IT'	=> 'Italy',
					'JP'	=> 'Japan',
					'MX'	=> 'Mexico',
					'NL'	=> 'Netherlands',
					'PL'	=> 'Poland',
					'SG'	=> 'Singapore',
					'ES'	=> 'Spain',
					'SE'	=> 'Sweden',
					'CH'	=> 'Switzerland',
					'TR' 	=> 'Turkey',
					'GB'	=> 'United Kingdom',
					'US'	=> 'United States'
				);

	      foreach ($locales as $k => $v) {
	          echo '		<option value="' . $k . '"' . selected($k, $sel_locale, false) . '>' . esc_attr($v) . '</option>' . "\n";
	      }
			  ?>
			  </select>
				</td>
			  </tr>
			  <tr valign="top">
			  <th scope="row"><?php _e('Paypal Currency', 'psts') ?></th>
			  <td><select name="psts[pypl_currency]">
			  <?php
			  $currency = $psts->get_setting('pypl_currency');
	      $sel_currency = empty($currency) ? $psts->get_setting('currency') : $currency;
	      $currencies = array(
					'AUD' => 'AUD - Australian Dollar',
					'BRL' => 'BRL - Brazilian Real',
					'CAD' => 'CAD - Canadian Dollar',
					'CHF' => 'CHF - Swiss Franc',
					'CZK' => 'CZK - Czech Koruna',
					'DKK' => 'DKK - Danish Krone',
					'EUR' => 'EUR - Euro',
					'GBP' => 'GBP - Pound Sterling',
					'ILS' => 'ILS - Israeli Shekel',
					'HKD' => 'HKD - Hong Kong Dollar',
					'HUF' => 'HUF - Hungarian Forint',
					'JPY' => 'JPY - Japanese Yen',
					'MYR' => 'MYR - Malaysian Ringgits',
					'MXN' => 'MXN - Mexican Peso',
					'NOK' => 'NOK - Norwegian Krone',
					'NZD' => 'NZD - New Zealand Dollar',
					'PHP' => 'PHP - Philippine Pesos',
					'PLN' => 'PLN - Polish Zloty',
					'SEK' => 'SEK - Swedish Krona',
					'SGD' => 'SGD - Singapore Dollar',
					'TWD' => 'TWD - Taiwan New Dollars',
					'THB' => 'THB - Thai Baht',
					'TRY' => 'TRY - Turkish lira',
					'USD' => 'USD - U.S. Dollar'
	      );

	      foreach ($currencies as $k => $v) {
	          echo '		<option value="' . $k . '"' . selected($k, $sel_currency, false) . '>' . esc_attr($v) . '</option>' . "\n";
	      }
			  ?>
			  </select></td>
			  </tr>
			  <tr valign="top">
			  <th scope="row"><?php _e('PayPal Mode', 'psts') ?></th>
			  <td><select name="psts[pypl_status]">
			  <option value="live"<?php selected($psts->get_setting('pypl_status'), 'live'); ?>><?php _e('Live Site', 'psts') ?></option>
			  <option value="test"<?php selected($psts->get_setting('pypl_status'), 'test'); ?>><?php _e('Test Mode (Sandbox)', 'psts') ?></option>
			  </select>
			  </td>
			  </tr>
			  <tr>
				<th scope="row"><?php _e('PayPal API Credentials', 'psts') ?></th>
				<td>
					<span class="description"><?php _e('You must login to PayPal and create an API signature to get your credentials. <a target="_blank" href="https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_ECAPICredentials">Instructions &raquo;</a>', 'psts') ?></span>
			    <p><label><?php _e('API Username', 'psts') ?><br />
			    <input value="<?php esc_attr_e($psts->get_setting("pypl_api_user")); ?>" size="30" name="psts[pypl_api_user]" type="text" />
			    </label></p>
			    <p><label><?php _e('API Password', 'psts') ?><br />
			    <input value="<?php esc_attr_e($psts->get_setting("pypl_api_pass")); ?>" size="20" name="psts[pypl_api_pass]" type="text" />
			    </label></p>
			    <p><label><?php _e('Signature', 'psts') ?><br />
			    <input value="<?php esc_attr_e($psts->get_setting("pypl_api_sig")); ?>" size="70" name="psts[pypl_api_sig]" type="text" />
			    </label></p>
			  </td>
			  </tr>
			  <th scope="row"><?php _e('Enable PayPal Pro', 'psts') ?></th>
				<td>
					<span class="description"><?php _e('PayPal Website Payments Pro allows you to seemlessly accept credit cards on your site, and gives you the most professional look with a widely accepted payment method. There are a few requirements you must meet to use PayPal Website Payments Pro:', 'psts') ?></span>
			    <ul style="list-style:disc outside none;margin-left:25px;">
			     <li><?php _e('You must signup (and pay the monthly fees) for <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_wp-pro-overview-outside" target="_blank">PayPal Website Payments Pro</a>.', 'psts') ?></li>
			     <li><?php _e('You must signup (and pay the monthly fees) for the <a href="https://www.paypal.com/cgi-bin/webscr?cmd=xpt/Marketing/general/ProRecurringPayments-outside" target="_blank">PayPal Website Payments Pro Recurring Payments addon</a>.', 'psts') ?></li>
			     <li><?php _e('You must have an SSL certificate setup for your main blog/site where the checkout form will be displayed.', 'psts') ?></li>
			     <li><?php _e('You additionaly must be <a href="https://www.paypal.com/pcicompliance" target="_blank">PCI compliant</a>, which means your server must meet security requirements for collecting and transmitting payment data.', 'psts') ?></li>
			     <li><?php _e('The checkout form will be added to a page on your main site. You may need to adjust your theme stylesheet for it to look nice with your theme.', 'psts') ?></li>
			     <li><?php _e('Due to PayPal policies, PayPal Express will always be offered in addition to credit card payments.', 'psts') ?></li>
			     <li><?php _e('Be aware that PayPal Website Payments Pro only supports PayPal accounts in select countries.', 'psts') ?></li>
					 <li><?php _e('Tip: When testing you will need to setup a preconfigured Website Payments Pro seller account in your sandbox.', 'psts') ?></li>
			    </ul>
			    <label><input type="checkbox" name="psts[pypl_enable_pro]" value="1"<?php echo checked($psts->get_setting("pypl_enable_pro"), 1); ?> /> <?php _e('Enable PayPal Pro', 'psts') ?><br />
			    </label>
			  </td>
			  </tr>
			  <tr>
				<th scope="row"><?php _e('PayPal Header Image (optional)', 'psts') ?></th>
				<td>
					<span class="description"><?php _e('URL for an image you want to appear at the top left of the payment page. The image has a maximum size of 750 pixels wide by 90 pixels high. PayPal recommends that you provide an image that is stored on a secure (https) server. If you do not specify an image, the business name is displayed.', 'psts') ?></span>
			    <p>
			    <input value="<?php esc_attr_e($psts->get_setting("pypl_header_img")); ?>" size="40" name="psts[pypl_header_img]" type="text" />
			    </p>
			  </td>
			  </tr>
			  <tr>
				<th scope="row"><?php _e('PayPal Header Border Color (optional)', 'psts') ?></th>
				<td>
					<span class="description"><?php _e('Sets the border color around the header of the payment page. The border is a 2-pixel perimeter around the header space, which is 750 pixels wide by 90 pixels high. By default, the color is black. The value should be a 6 digit hex color.', 'psts') ?></span>
			    <p>
			    <input value="<?php esc_attr_e($psts->get_setting("pypl_header_border")); ?>" size="6" maxlength="6" name="psts[pypl_header_border]" type="text" />
			    </p>
			  </td>
			  </tr>
			  <tr>
				<th scope="row"><?php _e('PayPal Header Background Color (optional)', 'psts') ?></th>
				<td>
					<span class="description"><?php _e('Sets the background color for the header of the payment page. By default, the color is white. The value should be a 6 digit hex color.', 'psts') ?></span>
			    <p>
			    <input value="<?php esc_attr_e($psts->get_setting("pypl_header_back")); ?>" size="6" maxlength="6" name="psts[pypl_header_back]" type="text" />
			    </p>
			  </td>
			  </tr>
			  <tr>
				<th scope="row"><?php _e('PayPal Page Background Color (optional)', 'psts') ?></th>
				<td>
					<span class="description"><?php _e('Sets the background color for the payment page. Darker colors may not be allowed by PayPal. By default, the color is white. The value should be a 6 digit hex color.', 'psts') ?></span>
			    <p>
			    <input value="<?php esc_attr_e($psts->get_setting("pypl_page_back")); ?>" size="6" maxlength="6" name="psts[pypl_page_back]" type="text" />
			    </p>
			  </td>
			  </tr>
			  <tr valign="top">
			  <th scope="row"><?php _e('Thank You Message', 'psts') ?></th>
			  <td>
			    <textarea name="psts[pypl_thankyou]" type="text" rows="4" wrap="soft" id="pypl_thankyou" style="width: 95%"/><?php echo esc_textarea($psts->get_setting('pypl_thankyou')); ?></textarea>
			    <br /><?php _e('Displayed on the page after successful checkout with this gateway. This is also a good place to paste any conversion tracking scripts like from Google Analytics. - HTML allowed', 'psts') ?>
			  </td>
			  </tr>
			  </table>
		  </div>
		</div>
	  <?php
	}

	function settings_process($settings) {
	  $settings['pypl_enable_pro'] = isset($settings['pypl_enable_pro']) ? $settings['pypl_enable_pro'] : 0;
	  return $settings;
	}

	//filters the ssl on checkout page
	function force_ssl() {
	  global $psts;
    if ($psts->get_setting('pypl_enable_pro') && $psts->get_setting('pypl_status') == 'live')
      return true;
		else
		  return false;
	}

	//prints a hidden form field to prevent multiple form submits during checkout
  function nonce_field() {
	  $user = wp_get_current_user();
		$uid = (int) $user->id;
	  $nonce = wp_hash(wp_rand().'pstsnonce'.$uid, 'nonce');
	  update_user_meta($uid, '_psts_nonce', $nonce);
		return '<input type="hidden" name="_psts_nonce" value="'.$nonce.'" />';
	}

	//check the nonce
	function check_nonce() {
	  $user = wp_get_current_user();
		$uid = (int) $user->id;
		$nonce = get_user_meta($uid, '_psts_nonce', true);
		if (!$nonce)
		  return false;

		if ($_POST['_psts_nonce'] == $nonce) {
	    delete_user_meta($uid, '_psts_nonce');
	    return true;
		} else {
	  	return false;
	  }
	}

  function manual_cancel_email($blog_id, $old_gateway) {
    global $psts, $current_user;

    $message = '';

    //show instructions for old gateways
    if ($old_gateway == 'PayPal') {
      $message = sprintf(__("Thank you for modifying your subscription!

We want to remind you that because of billing system upgrades, we were unable to cancel your old subscription automatically, so it is important that you cancel the old one yourself in your PayPal account, otherwise the old payments will continue along with new ones!

Cancel your subscription in your PayPal account:
%s

You can also cancel following these steps:
https://www.paypal.com/webapps/helpcenter/article/?articleID=94044#canceling_recurring_paymemt_subscription_automatic_billing", 'psts'), 'https://www.paypal.com/cgi-bin/webscr?cmd=_subscr-find&alias=' . urlencode(get_site_option("supporter_paypal_email")));
		} else if ($old_gateway == 'Amazon') {
      $message = __("Thank you for modifying your subscription!

We want to remind you that because of billing system upgrades, we were unable to cancel your old subscription automatically, so it is important that you cancel the old one yourself in your Amazon Payments account, otherwise the old payments will continue along with new ones!

To cancel your subscription:

Simply go to https://payments.amazon.com/, click Your Account at the top of the page, log in to your Amazon Payments account (if asked), and then click the Your Subscriptions link. This page displays your subscriptions, showing the most recent, active subscription at the top. To view the details of a specific subscription, click Details. Then cancel your subscription by clicking the Cancel Subscription button on the Subscription Details page.", 'psts');
		}

		$email = isset($current_user->user_email) ? $current_user->user_email : get_blog_option($blog_id, 'admin_email');

  	wp_mail( $email, __("Don't forget to cancel your old subscription!", 'psts'), $message);

    $psts->log_action( $blog_id, sprintf(__('Reminder to cancel previous %s subscription sent to %s', 'psts'), $old_gateway, get_blog_option($blog_id, 'admin_email')) );
  }

  function year_dropdown($sel='') {
    $minYear = date('Y');
    $maxYear = $minYear + 15;

    if (empty($sel)) {
      $sel = $minYear + 1;
		}

    $output = "<option value=''>--</option>";
    for ( $i = $minYear; $i < $maxYear; $i++ ) {
      $output .= "<option value='". substr($i, 0, 4) ."'".($sel==(substr($i, 0, 4))?' selected':'').">". $i ."</option>";
    }
    return $output;
  }

  function month_dropdown($sel='') {
    if (empty($sel)) {
      $sel = date('n');
		}
    $output =  "<option value=''>--</option>";
    $output .=  "<option" . ($sel==1?' selected':'') . " value='01'>01 - ".__('Jan', 'psts')."</option>";
    $output .=  "<option" . ($sel==2?' selected':'') . " value='02'>02 - ".__('Feb', 'psts')."</option>";
    $output .=  "<option" . ($sel==3?' selected':'') . " value='03'>03 - ".__('Mar', 'psts')."</option>";
    $output .=  "<option" . ($sel==4?' selected':'') . " value='04'>04 - ".__('Apr', 'psts')."</option>";
    $output .=  "<option" . ($sel==5?' selected':'') . " value='05'>05 - ".__('May', 'psts')."</option>";
    $output .=  "<option" . ($sel==6?' selected':'') . " value='06'>06 - ".__('Jun', 'psts')."</option>";
    $output .=  "<option" . ($sel==7?' selected':'') . " value='07'>07 - ".__('Jul', 'psts')."</option>";
    $output .=  "<option" . ($sel==8?' selected':'') . " value='08'>08 - ".__('Aug', 'psts')."</option>";
    $output .=  "<option" . ($sel==9?' selected':'') . " value='09'>09 - ".__('Sep', 'psts')."</option>";
    $output .=  "<option" . ($sel==10?' selected':'') . " value='10'>10 - ".__('Oct', 'psts')."</option>";
    $output .=  "<option" . ($sel==11?' selected':'') . " value='11'>11 - ".__('Nov', 'psts')."</option>";
    $output .=  "<option" . ($sel==12?' selected':'') . " value='12'>12 - ".__('Dec', 'psts')."</option>";

    return $output;
  }
  
  function payment_info($payment_info, $blog_id) {
  	global $psts;

    $profile_id = $this->get_profile_id($blog_id);
    if ($profile_id) {
			$resArray = $this->GetRecurringPaymentsProfileDetails($profile_id);

	    if (($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') && $resArray['STATUS']=='Active') {

        if ($resArray['NEXTBILLINGDATE'])
          $next_billing = date_i18n(get_blog_option($blog_id, 'date_format'), strtotime($resArray['NEXTBILLINGDATE']));
        else
          $next_billing = __("None", 'psts');

        $payment_info = sprintf(__('Subscription Description: %s', 'psts'), stripslashes($resArray['DESC']))."\n\n";

        if ($resArray['ACCT']) { //credit card
          $month = substr($resArray['EXPDATE'], 0, 2);
          $year = substr($resArray['EXPDATE'], 2, 4);
          $payment_info .= sprintf(__('Payment Method: %1$s Card ending in %2$s. Expires %3$s', 'psts'), $resArray['CREDITCARDTYPE'], $resArray['ACCT'], $month.'/'.$year)."\n";
        } else { //paypal
          $payment_info .= __('Payment Method: PayPal Account', 'psts')."\n";
        }

        if ($last_payment = $psts->last_transaction($blog_id)) {
          $payment_info .= sprintf(__('Payment Date: %s', 'psts'), date_i18n(get_blog_option($blog_id, 'date_format')))."\n";
          $payment_info .= sprintf(__('Payment Amount: %s', 'psts'), $last_payment['amount'] . ' ' . $psts->get_setting('currency'))."\n";
          $payment_info .= sprintf(__('Payment Transaction ID: %s', 'psts'), $last_payment['txn_id'])."\n\n";
        }
        $payment_info .= sprintf(__('Next Scheduled Payment Date: %s', 'psts'), $next_billing)."\n";

      }
    }
    return $payment_info;
  }
  
  function subscription_info($blog_id) {
    global $psts;
    
    $profile_id = $this->get_profile_id($blog_id);
    
    if ($profile_id) {
			$resArray = $this->GetRecurringPaymentsProfileDetails($profile_id);

	    if (($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') && $resArray['STATUS']=='Active') {

	      $active_member = true;

	      if (isset($resArray['LASTPAYMENTDATE'])) {
	        $prev_billing = date_i18n(get_option('date_format'), strtotime($resArray['LASTPAYMENTDATE']));
	      } else if ($last_payment = $psts->last_transaction($blog_id)) {
	        $prev_billing = date_i18n(get_option('date_format'), $last_payment['timestamp']);
	      } else {
	        $prev_billing = __("None yet with this subscription <small>(only initial separate single payment has been made, or they recently modified their subscription)</small>", 'psts');
	      }

	      if (isset($resArray['NEXTBILLINGDATE']))
	        $next_billing = date_i18n(get_option('date_format'), strtotime($resArray['NEXTBILLINGDATE']));
	      else
	        $next_billing = __("None", 'psts');

	      $next_payment_timestamp = strtotime($resArray['NEXTBILLINGDATE']);

	      echo '<ul>';
	      echo '<li>'.sprintf(__('Subscription Description: <strong>%s</strong>', 'psts'), stripslashes($resArray['DESC'])).'</li>';
	      echo '<li>'.sprintf(__('PayPal Profile ID: <strong>%s</strong>', 'psts'), '<a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id='.$profile_id.'&mp_id='.$profile_id.'&return_to=merchant&flag_flow=merchant#name1" target="_blank" title="View in PayPal &raquo;">'.$profile_id.'</a>').'</li>';

	      if (isset($resArray['ACCT'])) { //credit card
	        $month = substr($resArray['EXPDATE'], 0, 2);
	        $year = substr($resArray['EXPDATE'], 2, 4);
	        echo '<li>'.sprintf(__('Payment Method: <strong>%1$s Card</strong> ending in <strong>%2$s</strong>. Expires <strong>%3$s</strong>', 'psts'), $resArray['CREDITCARDTYPE'], $resArray['ACCT'], $month.'/'.$year).'</li>';
	      } else { //paypal
	        echo '<li>'.__('Payment Method: <strong>Their PayPal Account</strong>', 'psts').'</li>';
	      }

	      echo '<li>'.sprintf(__('Last Payment Date: <strong>%s</strong>', 'psts'), $prev_billing).'</li>';
	      if ($last_payment = $psts->last_transaction($blog_id)) {
	        echo '<li>'.sprintf(__('Last Payment Amount: <strong>%s</strong>', 'psts'), $psts->format_currency(false, $last_payment['amount'])).'</li>';
	        echo '<li>'.sprintf(__('Last Payment Transaction ID: <a target="_blank" href="https://www.paypal.com/vst/id=%s"><strong>%s</strong></a>', 'psts'), $last_payment['txn_id'], $last_payment['txn_id']).'</li>';
	      }
	      echo '<li>'.sprintf(__('Next Payment Date: <strong>%s</strong>', 'psts'), $next_billing).'</li>';
	      echo '<li>'.sprintf(__('Payments Made With This Subscription: <strong>%s</strong>', 'psts'), $resArray['NUMCYCLESCOMPLETED']).' *</li>';
	      echo '<li>'.sprintf(__('Aggregate Total With This Subscription: <strong>%s</strong>', 'psts'), $psts->format_currency(false, $resArray['AGGREGATEAMT'])).' *</li>';
	      echo '</ul>';
	      echo '<small>* ('.__('This does not include the initial payment at signup, or payments before the last payment method/plan change.', 'psts').')</small>';

	    } else if (($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') && $resArray['STATUS']=='Cancelled') {

	      $canceled_member = true;

	      $end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
	      echo '<strong>'.__('The Subscription Has Been Cancelled in PayPal', 'psts').'</strong>';
	      echo '<ul><li>'.sprintf(__('They should continue to have access until %s.', 'psts'), $end_date).'</li>';
				echo '<li>'.sprintf(__('PayPal Profile ID: <strong>%s</strong>', 'psts'), '<a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id='.$profile_id.'&mp_id='.$profile_id.'&return_to=merchant&flag_flow=merchant#name1" target="_blank" title="View in PayPal &raquo;">'.$profile_id.'</a>').'</li>';

	      if (isset($resArray['LASTPAYMENTDATE'])) {
	        $prev_billing = date_i18n(get_option('date_format'), strtotime($resArray['LASTPAYMENTDATE']));
	      } else if ($last_payment = $psts->last_transaction($blog_id)) {
	        $prev_billing = date_i18n(get_option('date_format'), $last_payment['timestamp']);
	      } else {
	        $prev_billing = __('None yet with this subscription <small>(only initial separate single payment has been made, or they recently modified their subscription)</small>', 'psts');
	      }

	      echo '<li>'.sprintf(__('Last Payment Date: <strong>%s</strong>', 'psts'), $prev_billing).'</li>';
	      if ($last_payment = $psts->last_transaction($blog_id)) {
	        echo '<li>'.sprintf(__('Last Payment Amount: <strong>%s</strong>', 'psts'), $psts->format_currency(false, $last_payment['amount'])).'</li>';
	        echo '<li>'.sprintf(__('Last Payment Transaction ID: <a target="_blank" href="https://www.paypal.com/vst/id=%s"><strong>%s</strong></a>', 'psts'), $last_payment['txn_id'], $last_payment['txn_id']).'</li>';
	      }
	      echo '</ul>';
				
      } else if (($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') && $resArray['STATUS']=='Suspended') {

	      $active_member = true;

	      $end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
	      echo '<strong>'.__('The Subscription Has Been Suspended in PayPal', 'psts').'</strong>';
	      echo '<p><a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id='.$profile_id.'&mp_id='.$profile_id.'&return_to=merchant&flag_flow=merchant#name1" target="_blank" title="View in PayPal &raquo;">'.__('Please check your PayPal account for more information.', 'psts').'</a></p>';
				echo '<ul><li>'.sprintf(__('They should continue to have access until %s.', 'psts'), $end_date).'</li>';
				echo '<li>'.sprintf(__('PayPal Profile ID: <strong>%s</strong>', 'psts'), '<a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id='.$profile_id.'&mp_id='.$profile_id.'&return_to=merchant&flag_flow=merchant#name1" target="_blank" title="View in PayPal &raquo;">'.$profile_id.'</a>').'</li>';

	      if (isset($resArray['LASTPAYMENTDATE'])) {
	        $prev_billing = date_i18n(get_option('date_format'), strtotime($resArray['LASTPAYMENTDATE']));
	      } else if ($last_payment = $psts->last_transaction($blog_id)) {
	        $prev_billing = date_i18n(get_option('date_format'), $last_payment['timestamp']);
	      } else {
	        $prev_billing = __('None yet with this subscription <small>(only initial separate single payment has been made, or they recently modified their subscription)</small>', 'psts');
	      }

	      echo '<li>'.sprintf(__('Last Payment Date: <strong>%s</strong>', 'psts'), $prev_billing).'</li>';
	      if ($last_payment = $psts->last_transaction($blog_id)) {
	        echo '<li>'.sprintf(__('Last Payment Amount: <strong>%s</strong>', 'psts'), $psts->format_currency(false, $last_payment['amount'])).'</li>';
	        echo '<li>'.sprintf(__('Last Payment Transaction ID: <a target="_blank" href="https://www.paypal.com/vst/id=%s"><strong>%s</strong></a>', 'psts'), $last_payment['txn_id'], $last_payment['txn_id']).'</li>';
	      }
	      echo '</ul>';
				
      } else if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {
				
        echo '<p>'.sprintf(__('The Subscription profile status is currently: <strong>%s</strong>', 'psts'), $resArray['STATUS']).'</p>';
        echo '<p><a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id='.$profile_id.'&mp_id='.$profile_id.'&return_to=merchant&flag_flow=merchant#name1" target="_blank" title="View in PayPal &raquo;">'.__('Please check your PayPal account for more information.', 'psts').'</a></p>';
			
			} else {
	      echo '<div id="message" class="error fade"><p>'.sprintf( __("Whoops! There was a problem accessing this site's subscription information: %s", 'psts'), $this->parse_error_string($resArray) ).'</p></div>';
	    }
			
			//show past profiles if they exists
			$profile_history = $this->get_profile_id($blog_id, true);
			if (is_array($profile_history) && count($profile_history)) {
				$history_lines = array();
				foreach ($profile_history as $profile) {
					$history_lines[] = '<a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id='.$profile['profile_id'].'&mp_id='.$profile['profile_id'].'&return_to=merchant&flag_flow=merchant#name1" target="_blank" title="'.sprintf(__('Last used on %s', 'psts'), date_i18n(get_option('date_format'), $profile['timestamp'])).'">'.$profile['profile_id'].'</a>'; 
				}
				echo __('Profile History:', 'psts') . ' <small>' . implode(', ', $history_lines) . '</small>';
			}
    } else if ($old_info = get_blog_option($blog_id, 'pypl_old_last_info')) {
			
			if (isset($old_info['payment_date']))
				$prev_billing = date_i18n(get_option('date_format'), strtotime($old_info['payment_date']));

			$profile_id = $old_info['subscr_id'];
			
			$supporter_paypal_site = get_site_option( "supporter_paypal_site" );
      $locale = strtolower(empty($supporter_paypal_site) ? 'US' : $supporter_paypal_site);
			
			echo '<ul>';
			echo '<li>'.__('Old Supporter PayPal Gateway', 'psts').'</li>';
			echo '<li>'.sprintf(__('PayPal Profile ID: <strong>%s</strong>', 'psts'), '<a href="https://www.paypal.com/'.$locale.'/cgi-bin/webscr?cmd=_profile-recurring-payments&encrypted_profile_id='.$profile_id.'&mp_id='.$profile_id.'&return_to=merchant&flag_flow=merchant#name1" target="_blank" title="View in PayPal &raquo;">'.$profile_id.'</a>').'</li>';
			echo '<li>'.sprintf(__('Last Payment Date: <strong>%s</strong>', 'psts'), $prev_billing).'</li>';
			echo '<li>'.sprintf(__('Last Payment Amount: <strong>%s</strong>', 'psts'), $psts->format_currency($old_info['mc_currency'], $old_info['payment_gross'])).'</li>';
			echo '<li>'.sprintf(__('Last Payment Transaction ID: <a target="_blank" href="https://www.paypal.com/vst/id=%s"><strong>%s</strong></a>', 'psts'), $old_info['txn_id'], $old_info['txn_id']).'</li>';
			echo '</ul>';
			
		} else {
      echo '<p>'.__("This site is using an older gateway so their information is not accessible until the next payment comes through.", 'psts').'</p>';
		}
	}

  function subscriber_info($blog_id) {
    global $psts;
    
    $profile_id = $this->get_profile_id($blog_id);

    if ($profile_id) {
			$resArray = $this->GetRecurringPaymentsProfileDetails($profile_id);

	    //get user details
	    if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {

	      echo '<p><strong>' . stripslashes($resArray['SUBSCRIBERNAME']) . '</strong><br />';

	      if (isset($resArray['ACCT'])) { //credit card
	        echo stripslashes($resArray['STREET']) . '<br />';
	        echo stripslashes($resArray['CITY']) . ', ' . stripslashes($resArray['STATE']) . ' ' . stripslashes($resArray['ZIP']) . '<br />';
	        echo stripslashes($resArray['COUNTRY']) . '</p>';

	        echo '<p>' . stripslashes($resArray['EMAIL']) . '</p>';
	      }
	    }
		} else if ($old_info = get_blog_option($blog_id, 'pypl_old_last_info')) {
			
			echo '<p>';
			if (isset($old_info['first_name']))
				echo '<strong>' . stripslashes($old_info['first_name']) . ' ' . stripslashes($old_info['last_name']) . '</strong>';
			if (isset($old_info['address_street']))
				echo '<br />' . stripslashes($old_info['address_street']);
			if (isset($old_info['address_city']))
				echo '<br />' . stripslashes($old_info['address_city']) . ', ' . stripslashes($old_info['address_state']) . ' ' . stripslashes($old_info['address_zip']) . '<br />' . stripslashes($old_info['address_country_code']);
			else
				echo '<br />' . stripslashes($old_info['residence_country']);
			echo '</p>';
			
			if (isset($old_info['payer_email']))
				echo '<p>' . stripslashes($old_info['payer_email']) . '</p>';
			
		} else {
      echo '<p>'.__("This site is using an older gateway so their information is not accessible until the next payment comes through.", 'psts').'</p>';
		}
	}
	
  function modify_form($blog_id) {
    global $psts, $wpdb;
    $active_member = false;
		$canceled_member = false;
		
    //get subscription info
    $profile_id = $this->get_profile_id($blog_id);

    if ($profile_id) {
			$resArray = $this->GetRecurringPaymentsProfileDetails($profile_id);

	    if (($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') && ($resArray['STATUS']=='Active' || $resArray['STATUS']=='Suspended')) {
	      $active_member = true;
	      $next_payment_timestamp = strtotime($resArray['NEXTBILLINGDATE']);
	    } else if (($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') && $resArray['STATUS']=='Cancelled') {
	      $canceled_member = true;
      }
    }
    
		$end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
		
		if ($active_member) { ?>
	    <h4><?php _e('Cancelations:', 'psts'); ?></h4>
	    <label><input type="radio" name="pypl_mod_action" value="cancel" /> <?php _e('Cancel Subscription Only', 'psts'); ?> <small>(<?php printf(__('Their access will expire on %s', 'psts'), $end_date); ?>)</small></label><br />
	    <?php if ($last_payment = $psts->last_transaction($blog_id)) {
	      $days_left = (($next_payment_timestamp - time()) / 60 / 60 / 24);
	      $period = $wpdb->get_var("SELECT term FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'");
				$refund = (intval($period)) ? round( ($days_left / (intval($period) * 30.4166)) * $last_payment['amount'], 2 ) : 0;
	      if ($refund > $last_payment['amount'])
	        $refund = $last_payment['amount'];
	    ?>
	    <label><input type="radio" name="pypl_mod_action" value="cancel_refund" /> <?php printf(__('Cancel Subscription and Refund Full (%s) Last Payment', 'psts'), $psts->format_currency(false, $last_payment['amount'])); ?> <small>(<?php printf(__('Their access will expire on %s', 'psts'), $end_date); ?>)</small></label><br />
	    <?php if ($refund) { ?>
			<label><input type="radio" name="pypl_mod_action" value="cancel_refund_pro" /> <?php printf(__('Cancel Subscription and Refund Prorated (%s) Last Payment', 'psts'), $psts->format_currency(false, $refund)); ?> <small>(<?php printf(__('Their access will expire on %s', 'psts'), $end_date); ?>)</small></label><br />
			<?php } ?>
			
	    <h4><?php _e('Refunds:', 'psts'); ?></h4>
	    <label><input type="radio" name="pypl_mod_action" value="refund" /> <?php printf(__('Refund Full (%s) Last Payment', 'psts'), $psts->format_currency(false, $last_payment['amount'])); ?> <small>(<?php _e('Their subscription and access will continue', 'psts'); ?>)</small></label><br />
	    <label><input type="radio" name="pypl_mod_action" value="partial_refund" /> <?php printf(__('Refund a Partial %s Amount of Last Payment', 'psts'), $psts->format_currency().'<input type="text" name="refund_amount" size="4" value="'.$last_payment['amount'].'" />'); ?> <small>(<?php _e('Their subscription and access will continue', 'psts'); ?>)</small></label><br />

	    <?php }
	  } else if ($canceled_member && ($last_payment = $psts->last_transaction($blog_id))) {
	    ?>
	    <h4><?php _e('Refunds:', 'psts'); ?></h4>
	    <label><input type="radio" name="pypl_mod_action" value="refund" /> <?php printf(__('Refund Full (%s) Last Payment', 'psts'), $psts->format_currency(false, $last_payment['amount'])); ?> <small>(<?php _e('Their subscription and access will continue', 'psts'); ?>)</small></label><br />
	    <label><input type="radio" name="pypl_mod_action" value="partial_refund" /> <?php printf(__('Refund a Partial %s Amount of Last Payment', 'psts'), $psts->format_currency().'<input type="text" name="refund_amount" size="4" value="'.$last_payment['amount'].'" />'); ?> <small>(<?php _e('Their subscription and access will continue', 'psts'); ?>)</small></label><br />
	    <?php
	  } else {
	    ?>
	    <p><small style="color:red;"><?php _e('Note: This <strong>will not</strong> cancel their PayPal subscription or refund any payments made. You will have to do it from your PayPal account for this site.', 'psts'); ?></small></p>
	    <?php
	  }

  }

	function process_modify($blog_id) {
    global $psts, $current_user, $wpdb;
    
		if ( isset($_POST['pypl_mod_action']) ) {
		
    	$profile_id = $this->get_profile_id($blog_id);
    
	    //handle different cases
	    switch ($_POST['pypl_mod_action']) {

	      case 'cancel':
	        $end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));

	        if ($profile_id)
	          $resArray = $this->ManageRecurringPaymentsProfileStatus($profile_id, 'Cancel', sprintf(__('Your subscription has been cancelled by an admin. You should continue to have access until %s', 'psts'), $end_date));

	        if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {

	          //record stat
	          $psts->record_stat($blog_id, 'cancel');

	      		$psts->log_action( $blog_id, sprintf(__('Subscription successfully cancelled by %1$s. They should continue to have access until %2$s', 'psts'), $current_user->display_name, $end_date) );
	          $success_msg = sprintf(__('Subscription successfully cancelled. They should continue to have access until %s.', 'psts'), $end_date);

	        } else {
	          $psts->log_action( $blog_id, sprintf(__('Attempt to Cancel Subscription by %1$s failed with an error: %2$s', 'psts'), $current_user->display_name, $this->parse_error_string($resArray) ) );
	          $error_msg = sprintf(__('Whoops, PayPal returned an error when attempting to cancel the subscription. Nothing was completed: %s', 'psts'), $this->parse_error_string($resArray) );
	        }
	        break;

	      case 'cancel_refund':
	        if ($last_payment = $psts->last_transaction($blog_id)) {
	          $end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
	          $refund = $last_payment['amount'];

	          if ($profile_id)
	            $resArray = $this->ManageRecurringPaymentsProfileStatus($profile_id, 'Cancel', sprintf(__('Your subscription has been cancelled by an admin. You should continue to have access until %s.', 'psts'), $end_date) );

	          if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {

	            //record stat
       				$psts->record_stat($blog_id, 'cancel');

	            //refund last transaction
	            $resArray2 = $this->RefundTransaction($last_payment['txn_id'], false, __('This is a full refund of your last subscription payment.', 'psts'));
	            if ($resArray2['ACK']=='Success' || $resArray2['ACK']=='SuccessWithWarning') {
	              $psts->log_action( $blog_id, sprintf(__('Subscription cancelled and full (%1$s) refund of last payment completed by %2$s. They should continue to have access until %3$s.', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name, $end_date) );
	              $success_msg = sprintf(__('Subscription cancelled and full (%1$s) refund of last payment were successfully completed. They should continue to have access until %2$s.', 'psts'), $psts->format_currency(false, $refund), $end_date);
                $psts->record_refund_transaction($blog_id, $last_payment['txn_id'], $refund);
							} else {
	              $psts->log_action( $blog_id, sprintf(__('Subscription cancelled, but full (%1$s) refund of last payment by %2$s returned an error: %3$s', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name, $this->parse_error_string($resArray) ) );
	              $error_msg = sprintf(__('Subscription cancelled, but full (%1$s) refund of last payment returned an error: %2$s', 'psts'), $psts->format_currency(false, $refund), $this->parse_error_string($resArray) );
	            }
	          } else {
	            $psts->log_action( $blog_id, sprintf(__('Attempt to Cancel Subscription and Refund Full (%1$s) Last Payment by %2$s failed with an error: ', 'psts'), $psts->format_currency(false, $refund), $this->parse_error_string($resArray) ) );
	            $error_msg = sprintf(__('Whoops, PayPal returned an error when attempting to cancel the subscription. Nothing was completed: %s', 'psts'), $this->parse_error_string($resArray) );
	          }
	        }
	        break;

	      case 'cancel_refund_pro':
	        if ($last_payment = $psts->last_transaction($blog_id)) {

	          //get next payment date
	          $resArray = $this->GetRecurringPaymentsProfileDetails($profile_id);
	          if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {
	            $next_payment_timestamp = strtotime($resArray['NEXTBILLINGDATE']);
	          } else {
	            $psts->log_action( $blog_id, sprintf(__('Attempt to Cancel Subscription and Refund Prorated (%1$s) Last Payment by %2$s failed with an error: %3$s', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name, $this->parse_error_string($resArray) ) );
	            $error_msg = sprintf(__('Whoops, PayPal returned an error when attempting to cancel the subscription. Nothing was completed: %s', 'psts'), $this->parse_error_string($resArray) );
	            break;
	          }
						
						$days_left = (($next_payment_timestamp - time()) / 60 / 60 / 24);
						$period = $wpdb->get_var("SELECT term FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'");
						$refund = (intval($period)) ? round( ($days_left / (intval($period) * 30.4166)) * $last_payment['amount'], 2 ) : 0;
						if ($refund > $last_payment['amount'])
							$refund = $last_payment['amount'];

	          if ($profile_id)
	            $resArray = $this->ManageRecurringPaymentsProfileStatus($profile_id, 'Cancel', __('Your subscription has been cancelled by an admin.', 'psts'));

	          if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {

	            //record stat
              $psts->record_stat($blog_id, 'cancel');

	            //refund last transaction
	            $resArray2 = $this->RefundTransaction($last_payment['txn_id'], $refund, __('This is a prorated refund of the unused portion of your last subscription payment.', 'psts'));
	            if ($resArray2['ACK']=='Success' || $resArray2['ACK']=='SuccessWithWarning') {
	              $psts->log_action( $blog_id, sprintf(__('Subscription cancelled and a prorated (%1$s) refund of last payment completed by %2$s', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name) );
	              $success_msg = sprintf(__('Subscription cancelled and a prorated (%s) refund of last payment were successfully completed.', 'psts'), $psts->format_currency(false, $refund));
                $psts->record_refund_transaction($blog_id, $last_payment['txn_id'], $refund);
							} else {
	              $psts->log_action( $blog_id, sprintf(__('Subscription cancelled, but prorated (%1$s) refund of last payment by %2$s returned an error: %3$s', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name, $this->parse_error_string($resArray) ) );
	              $error_msg = sprintf(__('Subscription cancelled, but prorated (%1$s) refund of last payment returned an error: %2$s', 'psts'), $psts->format_currency(false, $refund), $this->parse_error_string($resArray) );
	            }
	          } else {
	            $psts->log_action( $blog_id, sprintf(__('Attempt to Cancel Subscription and Refund Prorated (%1$s) Last Payment by %2$s failed with an error: %3$s', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name, $this->parse_error_string($resArray) ) );
	            $error_msg = sprintf(__('Whoops, PayPal returned an error when attempting to cancel the subscription. Nothing was completed: %s', 'psts'), $this->parse_error_string($resArray) );
	          }
	        }
	        break;

	      case 'refund':
	        if ($last_payment = $psts->last_transaction($blog_id)) {
	          $refund = $last_payment['amount'];

	          //refund last transaction
	          $resArray2 = $this->RefundTransaction($last_payment['txn_id'], false, __('This is a full refund of your last subscription payment.', 'psts'));
	          if ($resArray2['ACK']=='Success' || $resArray2['ACK']=='SuccessWithWarning') {
	            $psts->log_action( $blog_id, sprintf(__('A full (%1$s) refund of last payment completed by %2$s The subscription was not cancelled.', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name) );
	            $success_msg = sprintf(__('A full (%s) refund of last payment was successfully completed. The subscription was not cancelled.', 'psts'), $psts->format_currency(false, $refund));
              $psts->record_refund_transaction($blog_id, $last_payment['txn_id'], $refund);
						} else {
	            $psts->log_action( $blog_id, sprintf(__('Attempt to issue a full (%1$s) refund of last payment by %2$s returned an error: %3$s', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name, $this->parse_error_string($resArray) ) );
	            $error_msg = sprintf(__('Attempt to issue a full (%1$s) refund of last payment returned an error: %2$s', 'psts'), $psts->format_currency(false, $refund), $this->parse_error_string($resArray) );
	          }
	        }
	        break;

	      case 'partial_refund':
	        if (($last_payment = $psts->last_transaction($blog_id)) && round($_POST['refund_amount'], 2)) {
	          $refund = (round($_POST['refund_amount'], 2) < $last_payment['amount']) ? round($_POST['refund_amount'], 2) : $last_payment['amount'];

	          //refund last transaction
	          $resArray2 = $this->RefundTransaction($last_payment['txn_id'], false, __('This is a partial refund of your last payment.', 'psts'));
	          if ($resArray2['ACK']=='Success' || $resArray2['ACK']=='SuccessWithWarning') {
	            $psts->log_action( $blog_id, sprintf(__('A partial (%1$s) refund of last payment completed by %2$s The subscription was not cancelled.', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name) );
	            $success_msg = sprintf(__('A partial (%s) refund of last payment was successfully completed. The subscription was not cancelled.', 'psts'), $psts->format_currency(false, $refund) );
              $psts->record_refund_transaction($blog_id, $last_payment['txn_id'], $refund);
						} else {
	            $psts->log_action( $blog_id, sprintf(__('Attempt to issue a partial (%1$s) refund of last payment by %2$s returned an error: %3$s', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name, $this->parse_error_string($resArray) ) );
	            $error_msg = sprintf(__('Attempt to issue a partial (%1$s) refund of last payment returned an error: %2$s', 'psts'), $psts->format_currency(false, $refund), $this->parse_error_string($resArray) );
	          }
	        }
	        break;
	    }

	    //display resulting message
	    if ($success_msg)
			  echo '<div class="updated fade"><p>' . $success_msg . '</p></div>';
	    else if ($error_msg)
	      echo '<div class="error fade"><p>' . $error_msg . '</p></div>';
		}
	}
	
	//handle transferring pro status from one blog to another
	function process_transfer($from_id, $to_id) {
		global $psts, $wpdb;
		
		$profile_id = $this->get_profile_id($from_id);
		$current = $wpdb->get_row("SELECT * FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$to_id'");
		$custom = PSTS_PYPL_PREFIX . '_' . $to_id . '_' . $current->level . '_' . $current->term . '_' . $current->amount . '_' . $psts->get_setting('pypl_currency') . '_' . time();
		
		//update the profile id in paypal so that future payments are applied to the new site
		$this->UpdateRecurringPaymentsProfile($profile_id, $custom);
		
		//move profileid to new blog
		$this->set_profile_id($to_id, $profile_id);
		
		//delete the old profilid
	  $trans_meta = get_blog_option($from_id, 'psts_paypal_profile_id');
	  unset($trans_meta[$profile_id]);
	  update_blog_option($from_id, 'psts_paypal_profile_id', $trans_meta);
	}
	
  function process_checkout($blog_id) {
	  global $current_site, $current_user, $psts, $wpdb;

	  //add scripts
	  add_action( 'wp_head', array(&$this, 'checkout_js') );
	  wp_enqueue_script(array('jquery'));

	  //process paypal express checkout
	  if (isset($_POST['pypl_checkout_x']) || isset($_POST['pypl_checkout'])) {

	    //check for level
	    if (empty($_POST['period']) || empty($_POST['level'])) {
      	$psts->errors->add('general', __('Please choose your desired level and payment plan.', 'psts'));
      	return;
			}
					
			//prepare vars
			$discountAmt = false;
      if ($_POST['period'] == 1) {
        $paymentAmount = $psts->get_level_setting($_POST['level'], 'price_1');
    		if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $_POST['level']) ) {
     			$coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
					$discountAmt = $coupon_value['new_total'];
					$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s for the first month, then %2$s each month', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $discountAmt), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount));
				} else {
     			$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s %2$s each month', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount), $psts->get_setting('pypl_currency'));
				}
      } else if ($_POST['period'] == 3) {
        $paymentAmount = $psts->get_level_setting($_POST['level'], 'price_3');
        if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $_POST['level']) ) {
     			$coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
					$discountAmt = $coupon_value['new_total'];
					$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s for the first 3 month period, then %2$s every 3 months', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $discountAmt), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount));
				} else {
     			$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s %2$s every 3 months', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount), $psts->get_setting('pypl_currency'));
				}
			} else if ($_POST['period'] == 12) {
        $paymentAmount = $psts->get_level_setting($_POST['level'], 'price_12');
        if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $_POST['level']) ) {
     			$coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
					$discountAmt = $coupon_value['new_total'];
					$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s for the first 12 month period, then %2$s every 12 months', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $discountAmt), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount));
				} else {
     			$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s %2$s every 12 months', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount), $psts->get_setting('pypl_currency'));
				}
			}
			$desc = apply_filters('psts_pypl_checkout_desc', $desc, $_POST['period'], $_POST['level'], $paymentAmount, $discountAmt, $blog_id);
			
	    $resArray = $this->SetExpressCheckout($paymentAmount, $desc, $blog_id);
	  	if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning')	{
	  		$token = $resArray["TOKEN"];
	  		$_SESSION['TOKEN'] = $token;
	  		$_SESSION['PERIOD'] = $_POST['period'];
	  		$_SESSION['LEVEL'] = $_POST['level'];
	  		$this->RedirectToPayPal($token);
	  	} else {
	      $psts->errors->add('paypal', sprintf(__('There was a problem setting up the paypal payment:<br />"<strong>%s</strong>"<br />Please try again.', 'psts'), $this->parse_error_string($resArray) ) );
	    }
	  }

    /* ------------------- PayPal Checkout ----------------- */
    //check for return from Express Checkout
    if (isset($_GET['token']) && isset($_GET['PayerID']) && isset($_SESSION['PERIOD']) && isset($_SESSION['LEVEL'])) {

      //prepare vars
			$discountAmt = false;
      if ($_SESSION['PERIOD'] == 1) {
        $paymentAmount = $psts->get_level_setting($_SESSION['LEVEL'], 'price_1');
    		if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $_SESSION['LEVEL']) ) {
     			$coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
					$discountAmt = $coupon_value['new_total'];
					$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_SESSION['LEVEL'], 'name') . ': ' . sprintf(__('%1$s for the first month, then %2$s each month', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $discountAmt), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount));
				} else {
     			$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_SESSION['LEVEL'], 'name') . ': ' . sprintf(__('%1$s %2$s each month', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount), $psts->get_setting('pypl_currency'));
				}
      } else if ($_SESSION['PERIOD'] == 3) {
        $paymentAmount = $psts->get_level_setting($_SESSION['LEVEL'], 'price_3');
        if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $_SESSION['LEVEL']) ) {
     			$coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
					$discountAmt = $coupon_value['new_total'];
					$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_SESSION['LEVEL'], 'name') . ': ' . sprintf(__('%1$s for the first 3 month period, then %2$s every 3 months', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $discountAmt), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount));
				} else {
     			$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_SESSION['LEVEL'], 'name') . ': ' . sprintf(__('%1$s %2$s every 3 months', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount), $psts->get_setting('pypl_currency'));
				}
			} else if ($_SESSION['PERIOD'] == 12) {
        $paymentAmount = $psts->get_level_setting($_SESSION['LEVEL'], 'price_12');
        if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $_SESSION['LEVEL']) ) {
     			$coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
					$discountAmt = $coupon_value['new_total'];
					$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_SESSION['LEVEL'], 'name') . ': ' . sprintf(__('%1$s for the first 12 month period, then %2$s every 12 months', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $discountAmt), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount));
				} else {
     			$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_SESSION['LEVEL'], 'name') . ': ' . sprintf(__('%1$s %2$s every 12 months', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount), $psts->get_setting('pypl_currency'));
				}
			}
			$desc = apply_filters('psts_pypl_checkout_desc', $desc, $_SESSION['PERIOD'], $_SESSION['LEVEL'], $paymentAmount, $discountAmt, $blog_id);
			
			//get coupon payment amount
      if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $_SESSION['LEVEL']) ) {
	      $coupon = true;
	      $coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
				$initAmount = $coupon_value['new_total'];
	    } else {
				$coupon = false;
        $initAmount = $paymentAmount;
			}

			//check for modifiying
			if (is_pro_site($blog_id) && !is_pro_trial($blog_id)) {
			  $modify = $psts->get_expire($blog_id);
			  //check for a upgrade and get new first payment date
			  if ($upgrade = $psts->calc_upgrade($blog_id, $initAmount, $_SESSION['LEVEL'], $_SESSION['PERIOD'])) {
      		$modify = $upgrade;
				} else {
					$upgrade = false;
				}
      } else {
				$modify = false;
			}
      
      if ($modify) {

        //create the recurring profile
	      $resArray = $this->CreateRecurringPaymentsProfileExpress($_GET['token'], $paymentAmount, $_SESSION['PERIOD'], $desc, $blog_id, $_SESSION['LEVEL'], $modify);
	      if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {
	        $new_profile_id = $resArray["PROFILEID"];

	        $end_date = date_i18n(get_blog_option($blog_id, 'date_format'), $modify);
	        $psts->log_action( $blog_id, sprintf(__('User modifying subscription via PayPal Express: New subscription created (%1$s), first payment will be made on %2$s - %3$s', 'psts'), $desc, $end_date, $new_profile_id) );

	        //cancel old subscription
	        $old_gateway = $wpdb->get_var("SELECT gateway FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'");
         	if ($profile_id = $this->get_profile_id($blog_id)) {
	          $resArray = $this->ManageRecurringPaymentsProfileStatus($profile_id, 'Cancel', sprintf(__('Your %1$s subscription has been modified. This previous subscription has been canceled, and your new subscription (%2$s) will begin on %3$s.', 'psts'), $psts->get_setting('rebrand'), $desc, $end_date) );
            if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning')
							$psts->log_action( $blog_id, sprintf(__('User modifying subscription via PayPal Express: Old subscription canceled - %s', 'psts'), $profile_id) );
	        } else {
	          $this->manual_cancel_email($blog_id, $old_gateway); //send email for old paypal system
	        }

          //change expiration if upgrading
	        if ( $_SESSION['LEVEL'] > ($old_level = $psts->get_level($blog_id)) ) {
            $expire_sql = $upgrade ? " expire = '$upgrade'," : '';
						$wpdb->query( $wpdb->prepare("UPDATE {$wpdb->base_prefix}pro_sites SET$expire_sql level = %d, term = %d WHERE blog_ID = %d", $_SESSION['LEVEL'], $_SESSION['PERIOD'], $blog_id) );
            unset($psts->level[$blog_id]); //clear cache
          	$psts->log_action( $blog_id, sprintf( __('Pro Site level upgraded from "%s" to "%s".', 'psts'), $psts->get_level_setting($old_level, 'name'), $psts->get_level_setting($_SESSION['LEVEL'], 'name') ) );
		    		do_action('psts_upgrade', $blog_id, $_SESSION['LEVEL'], $old_level);
						$psts->record_stat( $blog_id, 'upgrade' );
	        } else {
	          $psts->record_stat( $blog_id, 'modify' );
	        }

	        //use coupon
	        if ($coupon)
	          $psts->use_coupon($_SESSION['COUPON_CODE'], $blog_id);

          //save new profile_id
          $this->set_profile_id($blog_id, $new_profile_id);
					
					//save new period/term
					$wpdb->query( $wpdb->prepare("UPDATE {$wpdb->base_prefix}pro_sites SET term = %d WHERE blog_ID = %d", $_SESSION['PERIOD'], $blog_id) );
					 
	        //show confirmation page
	        $this->complete_message = sprintf(__('Your PayPal subscription modification was successful for %s.', 'psts'), $desc);
					
					//display GA ecommerce in footer
					$psts->create_ga_ecommerce($blog_id, $_SESSION['PERIOD'], $initAmount, $_SESSION['LEVEL']);

					//show instructions for old gateways
          if ($old_gateway == 'PayPal') {
            $this->complete_message .= '<p><strong>'.__('Because of billing system upgrades, we were unable to cancel your old subscription automatically, so it is important that you cancel the old one yourself in your PayPal account, otherwise the old payments will continue along with new ones! Note this is the only time you will have to do this.', 'psts').'</strong></p>';
						$this->complete_message .= '<p><a target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_subscr-find&alias='.urlencode(get_site_option("supporter_paypal_email")).'"><img src="'.$psts->plugin_url. 'images/cancel_subscribe_gen.gif" /></a><br /><small>'.__('You can also cancel following <a href="https://www.paypal.com/webapps/helpcenter/article/?articleID=94044#canceling_recurring_paymemt_subscription_automatic_billing">these steps</a>.', 'psts').'</small></p>';
          } else if ($old_gateway == 'Amazon') {
            $this->complete_message .= '<p><strong>'.__('Because of billing system upgrades, we were unable to cancel your old subscription automatically, so it is important that you cancel the old one yourself in your Amazon Payments account, otherwise the old payments will continue along with new ones! Note this is the only time you will have to do this.', 'psts').'</strong></p>';
						$this->complete_message .= '<p>'.__('To view your subscriptions, simply go to <a target="_blank" href="https://payments.amazon.com/">https://payments.amazon.com/</a>, click Your Account at the top of the page, log in to your Amazon Payments account (if asked), and then click the Your Subscriptions link. This page displays your subscriptions, showing the most recent, active subscription at the top. To view the details of a specific subscription, click Details. Then cancel your subscription by clicking the Cancel Subscription button on the Subscription Details page.', 'psts').'</p>';
					}
					
          unset($_SESSION['COUPON_CODE']);
					unset($_SESSION['PERIOD']);
					unset($_SESSION['LEVEL']);
          
	    	} else {
	        $psts->errors->add('general', sprintf(__('There was a problem setting up the Paypal payment:<br />"<strong>%s</strong>"<br />Please try again.', 'psts'), $this->parse_error_string($resArray) ) );
	      	$psts->log_action( $blog_id, sprintf(__('User modifying subscription via PayPal Express: PayPal returned an error: %s', 'psts'), $this->parse_error_string($resArray) ) );
	      }

			} else { //new or expired signup
			
				$resArray = $this->DoExpressCheckoutPayment($_GET['token'], $_GET['PayerID'], $paymentAmount, $_SESSION['PERIOD'], $desc, $blog_id, $_SESSION['LEVEL']);
				if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {
	        //get result
	        $payment_status = $resArray['PAYMENTSTATUS'];
          $amount = $resArray['AMT'];
          $init_transaction = $resArray['TRANSACTIONID'];
          
          $psts->log_action( $blog_id, sprintf(__('User creating new subscription via PayPal Express: Initial payment successful (%1$s) - Transaction ID: %2$s', 'psts'), $desc, $init_transaction) );

	        //use coupon
	        if ($coupon)
	          $psts->use_coupon($_SESSION['COUPON_CODE'], $blog_id);

        	//just in case, try to cancel any old subscription
         	if ($profile_id = $this->get_profile_id($blog_id)) {
	          $resArray = $this->ManageRecurringPaymentsProfileStatus($profile_id, 'Cancel', sprintf(__('Your %s subscription has been modified. This previous subscription has been canceled.', 'psts'), $psts->get_setting('rebrand')) );
	        }
					
	        //create the recurring profile
	        $resArray = $this->CreateRecurringPaymentsProfileExpress($_GET['token'], $paymentAmount, $_SESSION['PERIOD'], $desc, $blog_id, $_SESSION['LEVEL']);
	      	if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {

						//save new profile_id
            $this->set_profile_id($blog_id, $resArray["PROFILEID"]);

            $psts->log_action( $blog_id, sprintf(__('User creating new subscription via PayPal Express: Subscription created (%1$s) - Profile ID: %2$s', 'psts'), $desc, $resArray["PROFILEID"]) );
            
	      	} else {
	          $this->complete_message = __('Your initial PayPal transaction was successful, but there was a problem creating the subscription so you may need to renew when the first period is up. Your site should be upgraded shortly.', 'psts') . '<br />"<strong>'.$this->parse_error_string($resArray).'</strong>"';
	        	$psts->log_action( $blog_id, sprintf(__('User creating new subscription via PayPal Express: Problem creating the subscription after successful initial payment. User may need to renew when the first period is up: %s', 'psts'), $this->parse_error_string($resArray) ) );
          }
					
					//now get the details of the transaction to see if initial payment went through already
					if ($payment_status == 'Completed' || $payment_status == 'Processed') {

						$psts->extend($blog_id, $_SESSION['PERIOD'], 'PayPal Express/Pro', $_SESSION['LEVEL'], $paymentAmount);

						$psts->record_stat($blog_id, 'signup');

						$psts->email_notification($blog_id, 'success');

						//record last payment
						$psts->record_transaction($blog_id, $init_transaction, $amount);
						
						// Added for affiliate system link
						do_action('supporter_payment_processed', $blog_id, $amount, $_SESSION['PERIOD'], $_SESSION['LEVEL']);
						
						if (empty($this->complete_message))
							$this->complete_message = __('Your PayPal subscription was successful! You should be receiving an email receipt shortly.', 'psts');
					} else {
						update_blog_option($blog_id, 'psts_waiting_step', 1);
					}
					
					//display GA ecommerce in footer
					$psts->create_ga_ecommerce($blog_id, $_SESSION['PERIOD'], $amount, $_SESSION['LEVEL']);
					
          unset($_SESSION['COUPON_CODE']);
					unset($_SESSION['PERIOD']);
					unset($_SESSION['LEVEL']);

	    	} else {
	        $psts->errors->add('general', sprintf(__('There was a problem setting up the Paypal payment:<br />"<strong>%s</strong>"<br />Please try again.', 'psts'), $this->parse_error_string($resArray) ) );
          $psts->log_action( $blog_id, sprintf(__('User creating new subscription via PayPal Express: PayPal returned an error: %s', 'psts'), $this->parse_error_string($resArray) ) );
				}
	      
			}
    }

		/* ------------ CC Checkout ----------------- */
    if (isset($_POST['cc_checkout'])) {

      //check for level
	    if (empty($_POST['period']) || empty($_POST['level'])) {
      	$psts->errors->add('general', __('Please choose your desired level and payment plan.', 'psts'));
      	return;
			}

      //process form
      if (isset($_POST['cc_form'])) {
				
				//clean up $_POST
				$cc_cardtype = isset($_POST['cc_card-type']) ? $_POST['cc_card-type'] : '';
				$cc_number = isset($_POST['cc_number']) ? stripslashes($_POST['cc_number']) : '';
				$cc_month = isset($_POST['cc_month']) ? $_POST['cc_month'] : '';
				$cc_year = isset($_POST['cc_year']) ? $_POST['cc_year'] : '';
				$cc_firstname = isset($_POST['cc_firstname']) ? stripslashes($_POST['cc_firstname']) : '';
				$cc_lastname = isset($_POST['cc_lastname']) ? stripslashes($_POST['cc_lastname']) : '';
				$cc_address = isset($_POST['cc_address']) ? stripslashes($_POST['cc_address']) : '';
				$cc_address2 = isset($_POST['cc_address2']) ? stripslashes($_POST['cc_address2']) : '';
				$cc_city = isset($_POST['cc_city']) ? stripslashes($_POST['cc_city']) : '';
				$cc_state = isset($_POST['cc_state']) ? stripslashes($_POST['cc_state']) : '';
				$cc_zip = isset($_POST['cc_zip']) ? stripslashes($_POST['cc_zip']) : '';
				$cc_country = isset($_POST['cc_country']) ? stripslashes($_POST['cc_country']) : '';
				
        $cc_number = preg_replace('/[^0-9]/', '', $cc_number); //strip any slashes
        $_POST['cc_cvv2'] = preg_replace('/[^0-9]/', '', $_POST['cc_cvv2']);

        //check nonce
		    if (!$this->check_nonce())
		  		$psts->errors->add('general', __('Whoops, looks like you may have tried to submit your payment twice so we prevented it. Check your subscription info below to see if it was created. If not, please try again.', 'psts'));

        if (empty($cc_cardtype))
      		$psts->errors->add('card-type', __('Please choose a Card Type.', 'psts'));

        if (empty($cc_number))
      		$psts->errors->add('number', __('Please enter a valid Credit Card Number.', 'psts'));

        if (empty($cc_month) || empty($cc_year))
      		$psts->errors->add('expiration', __('Please choose an expiration date.', 'psts'));

        if (strlen($_POST['cc_cvv2']) < 3 || strlen($_POST['cc_cvv2']) > 4)
      		$psts->errors->add('cvv2', __('Please enter a valid card security code. This is the 3 digits on the signature panel, or 4 digits on the front of Amex cards.', 'psts'));

        if (empty($cc_firstname))
      		$psts->errors->add('firstname', __('Please enter your First Name.', 'psts'));

        if (empty($cc_lastname))
      		$psts->errors->add('lastname', __('Please enter your Last Name.', 'psts'));

        if (empty($cc_address))
      		$psts->errors->add('address', __('Please enter your billing Street Address.', 'psts'));

        if (empty($_POST['cc_city']))
      		$psts->errors->add('city', __('Please enter your billing City.', 'psts'));

        if (($cc_country == 'US' || $cc_country == 'CA') && empty($cc_state))
      		$psts->errors->add('state', __('Please enter your billing State/Province.', 'psts'));

        if (empty($cc_zip))
      		$psts->errors->add('zip', __('Please enter your billing Zip/Postal Code.', 'psts'));

        if (empty($cc_country) || strlen($cc_country) != 2)
      		$psts->errors->add('country', __('Please enter your billing Country.', 'psts'));

        //no errors
      	if (!$psts->errors->get_error_code()) {

					//prepare vars
					$discountAmt = false;
		      if ($_POST['period'] == 1) {
		        $paymentAmount = $psts->get_level_setting($_POST['level'], 'price_1');
		    		if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $_POST['level']) ) {
		     			$coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
							$discountAmt = $coupon_value['new_total'];
							$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s for the first month, then %2$s each month', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $discountAmt), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount));
						} else {
		     			$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s %2$s each month', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount), $psts->get_setting('pypl_currency'));
						}
		      } else if ($_POST['period'] == 3) {
		        $paymentAmount = $psts->get_level_setting($_POST['level'], 'price_3');
		        if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $_POST['level']) ) {
		     			$coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
							$discountAmt = $coupon_value['new_total'];
							$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s for the first 3 month period, then %2$s every 3 months', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $discountAmt), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount));
						} else {
		     			$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s %2$s every 3 months', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount), $psts->get_setting('pypl_currency'));
						}
					} else if ($_POST['period'] == 12) {
		        $paymentAmount = $psts->get_level_setting($_POST['level'], 'price_12');
		        if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $_POST['level']) ) {
		     			$coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
							$discountAmt = $coupon_value['new_total'];
							$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s for the first 12 month period, then %2$s every 12 months', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $discountAmt), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount));
						} else {
		     			$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s %2$s every 12 months', 'psts'), $psts->format_currency($psts->get_setting('pypl_currency'), $paymentAmount), $psts->get_setting('pypl_currency'));
						}
					}
					$desc = apply_filters('psts_pypl_checkout_desc', $desc, $_POST['period'], $_POST['level'], $paymentAmount, $discountAmt, $blog_id);
					
          //get coupon payment amount
		      if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $_SESSION['LEVEL']) ) {
			      $coupon = true;
			      $coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
						$initAmount = $coupon_value['new_total'];
			    } else {
						$coupon = false;
		        $initAmount = $paymentAmount;
					}

					//check for modifiying
					if (is_pro_site($blog_id) && !is_pro_trial($blog_id)) {
					  $modify = $psts->get_expire($blog_id);
					  //check for a upgrade and get new first payment date
					  if ($upgrade = $psts->calc_upgrade($blog_id, $initAmount, $_SESSION['LEVEL'], $_SESSION['PERIOD'])) {
		      		$modify = $upgrade;
						} else {
							$upgrade = false;
						}
		      } else {
						$modify = false;
					}

		      if ($modify) {

            //create the recurring profile
			      $resArray = $this->CreateRecurringPaymentsProfileDirect($paymentAmount, $_POST['period'], $desc, $blog_id, $_POST['level'], $cc_cardtype, $cc_number, $cc_month.$cc_year, $_POST['cc_cvv2'], $cc_firstname, $cc_lastname, $cc_address, $cc_address2, $cc_city, $cc_state, $cc_zip, $cc_country, $current_user->user_email, $modify);
	          if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {
			        $new_profile_id = $resArray["PROFILEID"];

			        $end_date = date_i18n(get_blog_option($blog_id, 'date_format'), $modify);
			        $psts->log_action( $blog_id, sprintf(__('User modifying subscription via CC: New subscription created (%1$s), first payment will be made on %2$s - %3$s', 'psts'), $desc, $end_date, $new_profile_id) );

			        //cancel old subscription
			        $old_gateway = $wpdb->get_var("SELECT gateway FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'");
		         	if ($profile_id = $this->get_profile_id($blog_id)) {
			          $resArray = $this->ManageRecurringPaymentsProfileStatus($profile_id, 'Cancel', sprintf(__('Your %1$s subscription has been modified. This previous subscription has been canceled, and your new subscription (%2$s) will begin on %3$s.', 'psts'), $psts->get_setting('rebrand'), $desc, $end_date) );
                if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning')
									$psts->log_action( $blog_id, sprintf(__('User modifying subscription via CC: Old subscription canceled - %s', 'psts'), $profile_id) );
			        } else {
			          $this->manual_cancel_email($blog_id, $old_gateway); //send email for old paypal system
			        }

		          //change expiration if upgrading
			        if ( $_POST['level'] > ($old_level = $psts->get_level($blog_id)) ) {
                $expire_sql = $upgrade ? " expire = '$upgrade'," : '';
								$wpdb->query( $wpdb->prepare("UPDATE {$wpdb->base_prefix}pro_sites SET$expire_sql level = %d, term = %d WHERE blog_ID = %d", $_POST['level'], $_POST['period'], $blog_id) );
	            	unset($psts->level[$blog_id]); //clear cache
	            	$psts->log_action( $blog_id, sprintf( __('Pro Site level upgraded from "%s" to "%s".', 'psts'), $psts->get_level_setting($old_level, 'name'), $psts->get_level_setting($_POST['level'], 'name') ) );
		    				do_action('psts_upgrade', $blog_id, $_POST['level'], $old_level);
								$psts->record_stat( $blog_id, 'upgrade' );
			        } else {
			          $psts->record_stat( $blog_id, 'modify' );
			        }

			        //use coupon
			        if ($coupon)
			          $psts->use_coupon($_SESSION['COUPON_CODE'], $blog_id);

		          //save new profile_id
		          $this->set_profile_id($blog_id, $new_profile_id);
							
							//save new period/term
							$wpdb->query( $wpdb->prepare("UPDATE {$wpdb->base_prefix}pro_sites SET term = %d WHERE blog_ID = %d", $_POST['period'], $blog_id) );
					
			        //show confirmation page
			        $this->complete_message = sprintf(__('Your Credit Card subscription modification was successful for %s.', 'psts'), $desc);
							
							//display GA ecommerce in footer
							$psts->create_ga_ecommerce($blog_id, $_POST['period'], $initAmount, $_POST['level'], $cc_city, $cc_state, $cc_country);
							
							//show instructions for old gateways
		          if ($old_gateway == 'PayPal') {
		            $this->complete_message .= '<p><strong>'.__('Because of billing system upgrades, we were unable to cancel your old subscription automatically, so it is important that you cancel the old one yourself in your PayPal account, otherwise the old payments will continue along with new ones! Note this is the only time you will have to do this.', 'psts').'</strong></p>';
								$this->complete_message .= '<p><a target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_subscr-find&alias='.urlencode(get_site_option("supporter_paypal_email")).'"><img src="'.$psts->plugin_url. 'images/cancel_subscribe_gen.gif" /></a><br /><small>'.__('You can also cancel following <a href="https://www.paypal.com/helpcenter/main.jsp;jsessionid=SCPbTbhRxL6QvdDMvshNZ4wT2DH25d01xJHj6cBvNJPGFVkcl6vV!795521328?t=solutionTab&ft=homeTab&ps=&solutionId=27715&locale=en_US&_dyncharset=UTF-8&countrycode=US&cmd=_help-ext">these steps</a>.', 'psts').'</small></p>';
		          } else if ($old_gateway == 'Amazon') {
		            $this->complete_message .= '<p><strong>'.__('Because of billing system upgrades, we were unable to cancel your old subscription automatically, so it is important that you cancel the old one yourself in your Amazon Payments account, otherwise the old payments will continue along with new ones! Note this is the only time you will have to do this.', 'psts').'</strong></p>';
								$this->complete_message .= '<p>'.__('To view your subscriptions, simply go to <a target="_blank" href="https://payments.amazon.com/">https://payments.amazon.com/</a>, click Your Account at the top of the page, log in to your Amazon Payments account (if asked), and then click the Your Subscriptions link. This page displays your subscriptions, showing the most recent, active subscription at the top. To view the details of a specific subscription, click Details. Then cancel your subscription by clicking the Cancel Subscription button on the Subscription Details page.', 'psts').'</p>';
							}

              unset($_SESSION['COUPON_CODE']);
              
			    	} else {
			        $psts->errors->add('general', sprintf(__('There was a problem with your Credit Card information:<br />"<strong>%s</strong>"<br />Please try again.', 'psts'), $this->parse_error_string($resArray) ) );
			      	$psts->log_action( $blog_id, sprintf(__('User modifying subscription via CC: PayPal returned a problem with Credit Card info: %s', 'psts'), $this->parse_error_string($resArray) ) );
			      }

					} else { //new or expired signup
					
	          //attempt initial direct payment
	          $resArray = $this->DoDirectPayment($paymentAmount, $_POST['period'], $desc, $blog_id, $_POST['level'], $cc_cardtype, $cc_number, $cc_month.$cc_year, $_POST['cc_cvv2'], $cc_firstname, $cc_lastname, $cc_address, $cc_address2, $cc_city, $cc_state, $cc_zip, $cc_country, $current_user->user_email);
	        	if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {
	            $init_transaction = $resArray["TRANSACTIONID"];

	            //just in case, try to cancel any old subscription
		         	if ($profile_id = $this->get_profile_id($blog_id)) {
			          $resArray = $this->ManageRecurringPaymentsProfileStatus($profile_id, 'Cancel', sprintf(__('Your %s subscription has been modified. This previous subscription has been canceled.', 'psts'), $psts->get_setting('rebrand')) );
			        }

              $psts->log_action( $blog_id, sprintf(__('User creating new subscription via CC: Initial payment successful (%1$s) - Transaction ID: %2$s', 'psts'), $desc, $init_transaction) );

			        //use coupon
			        if ($coupon)
			          $psts->use_coupon($_SESSION['COUPON_CODE'], $blog_id);
							
	            //now attempt to create the subscription
	            $resArray = $this->CreateRecurringPaymentsProfileDirect($paymentAmount, $_POST['period'], $desc, $blog_id, $_POST['level'], $cc_cardtype, $cc_number, $cc_month.$cc_year, $_POST['cc_cvv2'], $cc_firstname, $cc_lastname, $cc_address, $cc_address2, $cc_city, $cc_state, $cc_zip, $cc_country, $current_user->user_email);
	          	if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {

              	//save new profile_id
	          		$this->set_profile_id($blog_id, $resArray["PROFILEID"]);

                $psts->log_action( $blog_id, sprintf(__('User creating new subscription via CC: Subscription created (%1$s) - Profile ID: %2$s', 'psts'), $desc, $resArray["PROFILEID"]) );
	              
	          	} else {
	              $this->complete_message = __('Your initial payment was successful, but there was a problem creating the subscription with your credit card so you may need to renew when the first period is up. Your site should be upgraded shortly.', 'psts') . '<br />"<strong>'.$this->parse_error_string($resArray).'</strong>"';
	            	$psts->log_action( $blog_id, sprintf(__('User creating new subscription via CC: Problem creating the subscription after successful initial payment. User may need to renew when the first period is up: %s', 'psts'), $this->parse_error_string($resArray) ) );
          		}
							
							//now get the details of the transaction to see if initial payment went through
							$result = $this->GetTransactionDetails($init_transaction);
							if ($result['PAYMENTSTATUS'] == 'Completed' || $result['PAYMENTSTATUS'] == 'Processed') {

								$psts->extend($blog_id, $_POST['period'], 'PayPal Express/Pro', $_POST['level'], $paymentAmount);

								$psts->record_stat($blog_id, 'signup');

								$psts->email_notification($blog_id, 'success');
								
								//record last payment
								$psts->record_transaction($blog_id, $init_transaction, $result['AMT']);
						
								// Added for affiliate system link
								do_action('supporter_payment_processed', $blog_id, $result['AMT'], $_POST['period'], $_POST['level']);
								
								if (empty($this->complete_message))
									$this->complete_message = sprintf(__('Your Credit Card subscription was successful! You should be receiving an email receipt at %s shortly.', 'psts'), get_blog_option($blog_id, 'admin_email'));
							} else {
								update_blog_option($blog_id, 'psts_waiting_step', 1);
							}
							
							//display GA ecommerce in footer
							$psts->create_ga_ecommerce($blog_id, $_POST['period'], $initAmount, $_POST['level'], $cc_city, $cc_state, $cc_country);
							
             	unset($_SESSION['COUPON_CODE']);
             	
	        	} else {
	            $psts->errors->add('general', sprintf(__('There was a problem with your credit card information:<br />"<strong>%s</strong>"<br />Please check all fields and try again.', 'psts'), $this->parse_error_string($resArray) ));
	          }
	          
					}
					
      	} else {
          $psts->errors->add('general', __('There was a problem with your credit card information. Please check all fields and try again.', 'psts'));
        }
      }
		}
	}

	//js to be printed only on checkout page
	function checkout_js() {
	  ?><script type="text/javascript"> jQuery(document).ready( function() { jQuery("a#pypl_cancel").click( function() { if ( confirm( "<?php echo __('Please note that if you cancel your subscription you will not be immune to future price increases. The price of un-canceled subscriptions will never go up!\n\nAre you sure you really want to cancel your subscription?\nThis action cannot be undone!', 'psts'); ?>" ) ) return true; else return false; }); });</script><?php
	}

	function checkout_screen($content, $blog_id) {
	  global $psts, $wpdb, $current_site, $current_user;

	  if (!$blog_id)
	    return $content;

    $img_base = $psts->plugin_url. 'images/';
    $pp_active = false;

    //hide top part of content if its a pro blog
		if ( is_pro_site($blog_id) || $psts->errors->get_error_message('coupon') )
			$content = '';
			
		if ($errmsg = $psts->errors->get_error_message('general')) {
			$content = '<div id="psts-general-error" class="psts-error">'.$errmsg.'</div>'; //hide top part of content if theres an error 
		}
		
	  //if transaction was successful display a complete message and skip the rest
	  if ($this->complete_message) {
	    $content = '<div id="psts-complete-msg">' . $this->complete_message . '</div>';
	    $content .= '<p>' . $psts->get_setting('pypl_thankyou') . '</p>';
	    $content .= '<p><a href="' . get_admin_url($blog_id, '', 'http') . '">' . __('Visit your newly upgraded site &raquo;', 'psts') . '</a></p>';
	    return $content;
	  }
		
    //check if pro/express user
    if ($profile_id = $this->get_profile_id($blog_id)) {
    
			$content .= '<div id="psts_existing_info">';
			$cancel_content = '';
			
			$end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
			$level = $psts->get_level_setting($psts->get_level($blog_id), 'name');
			
      //cancel subscription
      if (isset($_GET['action']) && $_GET['action']=='cancel' && wp_verify_nonce($_GET['_wpnonce'], 'psts-cancel')) {

        $resArray = $this->ManageRecurringPaymentsProfileStatus($profile_id, 'Cancel', sprintf(__('Your %1$s subscription has been canceled. You should continue to have access until %2$s.', 'psts'), $current_site->site_name . ' ' . $psts->get_setting('rebrand'), $end_date));

        if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {
          $content .= '<div id="message" class="updated fade"><p>'.sprintf(__('Your %1$s subscription has been canceled. You should continue to have access until %2$s.', 'psts'), $current_site->site_name . ' ' . $psts->get_setting('rebrand'), $end_date).'</p></div>';

					 //record stat
	        $psts->record_stat($blog_id, 'cancel');
	        
	        $psts->email_notification($blog_id, 'canceled');

	      	$psts->log_action( $blog_id, sprintf(__('Subscription successfully canceled by the user. They should continue to have access until %s', 'psts'), $end_date) );

				} else {
          $content .= '<div id="message" class="error fade"><p>'.__('There was a problem canceling your subscription, please contact us for help: ', 'psts').$this->parse_error_string($resArray).'</p></div>';
        }
      }

      //show sub details
      $resArray = $this->GetRecurringPaymentsProfileDetails($profile_id);
      if (($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') && $resArray['STATUS']=='Active') {

				if (isset($resArray['LASTPAYMENTDATE'])) {
	        $prev_billing = date_i18n(get_option('date_format'), strtotime($resArray['LASTPAYMENTDATE']));
	      } else if ($last_payment = $psts->last_transaction($blog_id)) {
	        $prev_billing = date_i18n(get_option('date_format'), $last_payment['timestamp']);
	      } else {
	        $prev_billing = __("None yet with this subscription <small>(only initial separate single payment has been made, or you've recently modified your subscription)</small>", 'psts');
	      }

        if (isset($resArray['NEXTBILLINGDATE']))
          $next_billing = date_i18n(get_option('date_format'), strtotime($resArray['NEXTBILLINGDATE']));
        else
          $next_billing = __("None", 'psts');

        $content .= '<h3>' . stripslashes($resArray['DESC']) . '</h3><ul>';

				if ( is_pro_site($blog_id) ) {
          $content .= '<li>'.__('Level:', 'psts').' <strong>'.$level.'</strong></li>';
				}

        if (isset($resArray['ACCT'])) { //credit card
          $month = substr($resArray['EXPDATE'], 0, 2);
          $year = substr($resArray['EXPDATE'], 2, 4);
          $content .= '<li>'.sprintf(__('Payment Method: <strong>%1$s Card</strong> ending in <strong>%2$s</strong>. Expires <strong>%3$s</strong>', 'psts'), $resArray['CREDITCARDTYPE'], $resArray['ACCT'], $month.'/'.$year).'</li>';
        } else { //paypal
          $content .= '<li>'.__('Payment Method: <strong>Your PayPal Account</strong>', 'psts').'</li>';
        }

        $content .= '<li>'.__('Last Payment Date:', 'psts').' <strong>'.$prev_billing.'</strong></li>';
        $content .= '<li>'.__('Next Payment Date:', 'psts').' <strong>'.$next_billing.'</strong></li>';
        $content .= '</ul><br />';

        $cancel_content .= '<h3>'.__('Cancel Your Subscription', 'psts').'</h3>';
        if (is_pro_site($blog_id))
        	$cancel_content .= '<p>'.sprintf(__('If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts'), $level, $end_date).'</p>';
        $cancel_content .= '<p><a id="pypl_cancel" href="' . wp_nonce_url($psts->checkout_url($blog_id) . '&action=cancel', 'psts-cancel') . '" title="'.__('Cancel Your Subscription', 'psts').'"><img src="'.$img_base.'cancel_subscribe_gen.gif" /></a></p>';

				$pp_active = true;
				
      } else if (($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') && $resArray['STATUS']=='Cancelled') {

        $content .= '<h3>'.__('Your subscription has been canceled', 'psts').'</h3>';
        $content .= '<p>'.sprintf(__('This site should continue to have %1$s features until %2$s.', 'psts'), $psts->get_setting('rebrand'), $end_date).'</p>';
				
      } else if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {

        $content .= '<h3>'.sprintf(__('Your subscription is: %s', 'psts'), $resArray['STATUS']).'</h3>';
        $content .= '<p>'.__('Please update your payment information below to resolve this.', 'psts').'</p>';

        $cancel_content .= '<h3>'.__('Cancel Your Subscription', 'psts').'</h3>';
        if (is_pro_site($blog_id))
					$cancel_content .= '<p>'.sprintf(__('If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts'), $level, $end_date).'</p>';
        $cancel_content .= '<p><a id="pypl_cancel" href="' . wp_nonce_url($psts->checkout_url($blog_id) . '&action=cancel', 'psts-cancel') . '" title="'.__('Cancel Your Subscription', 'psts').'"><img src="'.$img_base.'cancel_subscribe_gen.gif" /></a></p>';
        $pp_active = true;
      } else {
        $content .= '<div class="psts-error">'.__("There was a problem accessing your subscription information: ", 'psts') . $this->parse_error_string($resArray).'</div>';
      }
			
			//print receipt send form
			$content .= $psts->receipt_form($blog_id);
			
			if ( !defined('PSTS_CANCEL_LAST') )
				$content .= $cancel_content;
			
      $content .= '</div>';
			
    } else if (is_pro_site($blog_id)) {
			
			$end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
			$level = $psts->get_level_setting($psts->get_level($blog_id), 'name');
			$old_gateway = $wpdb->get_var("SELECT gateway FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'");

			$content .= '<div id="psts_existing_info">';
			$content .= '<h3>'.__('Your Subscription Information', 'psts').'</h3><ul>';
			$content .= '<li>'.__('Level:', 'psts').' <strong>'.$level.'</strong></li>';
			
			if ($old_gateway == 'PayPal')
				$content .= '<li>'.__('Payment Method: <strong>Your PayPal Account</strong>', 'psts').'</li>';
			else if ($old_gateway == 'Amazon')
				$content .= '<li>'.__('Payment Method: <strong>Your Amazon Account</strong>', 'psts').'</li>';
			else if ($psts->get_expire($blog_id) >= 9999999999)
				$content .= '<li>'.__('Expire Date: <strong>Never</strong>', 'psts').'</li>';
			else
				$content .= '<li>'.sprintf(__('Expire Date: <strong>%s</strong>', 'psts'), $end_date).'</li>';
				
			$content .= '</ul><br />';
			if ($old_gateway == 'PayPal' || $old_gateway == 'Amazon') {
				$cancel_content .= '<h3>'.__('Cancel Your Subscription', 'psts').'</h3>';
				$cancel_content .= '<p>'.sprintf(__('If your subscription is still active your next scheduled payment should be %1$s.', 'psts'), $end_date).'</p>';
				$cancel_content .= '<p>'.sprintf(__('If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts'), $level, $end_date).'</p>';
				//show instructions for old gateways
				if ($old_gateway == 'PayPal') {
					$cancel_content .= '<p><a id="pypl_cancel" target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_subscr-find&alias='.urlencode(get_site_option("supporter_paypal_email")).'" title="'.__('Cancel Your Subscription', 'psts').'"><img src="'.$psts->plugin_url. 'images/cancel_subscribe_gen.gif" /></a><br /><small>'.__('You can also cancel following <a href="https://www.paypal.com/helpcenter/main.jsp;jsessionid=SCPbTbhRxL6QvdDMvshNZ4wT2DH25d01xJHj6cBvNJPGFVkcl6vV!795521328?t=solutionTab&ft=homeTab&ps=&solutionId=27715&locale=en_US&_dyncharset=UTF-8&countrycode=US&cmd=_help-ext">these steps</a>.', 'psts').'</small></p>';
				} else if ($old_gateway == 'Amazon') {
					$cancel_content .= '<p>'.__('To cancel your subscription, simply go to <a id="pypl_cancel" target="_blank" href="https://payments.amazon.com/">https://payments.amazon.com/</a>, click Your Account at the top of the page, log in to your Amazon Payments account (if asked), and then click the Your Subscriptions link. This page displays your subscriptions, showing the most recent, active subscription at the top. To view the details of a specific subscription, click Details. Then cancel your subscription by clicking the Cancel Subscription button on the Subscription Details page.', 'psts').'</p>';
				}
			}
			
			//print receipt send form
			$content .= $psts->receipt_form($blog_id);
			
			if ( !defined('PSTS_CANCEL_LAST') )
				$content .= $cancel_content;
			
			$content .= '</div>';
			
		}
		
    if ($pp_active) {
      $content .= '<h2>' . __('Change Your Plan or Payment Details', 'psts') . '</h2>
          <p>' . __('You can modify or upgrade your plan or just change your payment method or information below. Your new subscription will automatically go into effect when your next payment is due.', 'psts') . '</p>';
    } else if (!$psts->get_setting('pypl_enable_pro')) {
			$content .= '<p>' . __('Please choose your desired plan then click the checkout button below.', 'psts') . '</p>';
		}
    
    $content .= '<form action="'.$psts->checkout_url($blog_id).'" method="post" autocomplete="off">';
    
    //print the checkout grid
    $content .= $psts->checkout_grid($blog_id);
    
    $content .= '<div id="psts-paypal-checkout">
			<h2>' . __('Checkout With PayPal', 'psts') . '</h2>
			<input type="image" src="https://fpdbs.paypal.com/dynamicimageweb?cmd=_dynamic-image&locale=' . get_locale() . '" border="0" name="pypl_checkout" alt="' . __('PayPal - The safer, easier way to pay online!', 'psts') . '">
			</div>';

		if ($psts->get_setting('pypl_enable_pro')) {
			
			//clean up $_POST
			$cc_cardtype = isset($_POST['cc_card-type']) ? $_POST['cc_card-type'] : '';
			$cc_number = isset($_POST['cc_number']) ? stripslashes($_POST['cc_number']) : '';
			$cc_month = isset($_POST['cc_month']) ? $_POST['cc_month'] : '';
			$cc_year = isset($_POST['cc_year']) ? $_POST['cc_year'] : '';
			$cc_firstname = isset($_POST['cc_firstname']) ? stripslashes($_POST['cc_firstname']) : '';
			$cc_lastname = isset($_POST['cc_lastname']) ? stripslashes($_POST['cc_lastname']) : '';
			$cc_address = isset($_POST['cc_address']) ? stripslashes($_POST['cc_address']) : '';
			$cc_address2 = isset($_POST['cc_address2']) ? stripslashes($_POST['cc_address2']) : '';
			$cc_city = isset($_POST['cc_city']) ? stripslashes($_POST['cc_city']) : '';
			$cc_state = isset($_POST['cc_state']) ? stripslashes($_POST['cc_state']) : '';
			$cc_zip = isset($_POST['cc_zip']) ? stripslashes($_POST['cc_zip']) : '';
			$cc_country = isset($_POST['cc_country']) ? stripslashes($_POST['cc_country']) : '';
			
	    $content .= '<div id="psts-cc-checkout">
	    <h2>' . __('Or Pay Directly By Credit Card', 'psts') . '</h2>';
	    if ($errmsg = $psts->errors->get_error_message('processcard'))
	      $content .= '<div id="psts-processcard-error" class="psts-error">'.$errmsg.'</div>';
      $content .= $this->nonce_field();
			$content .= '
	      <input type="hidden" name="cc_form" value="1" />
	        <table id="psts-cc-table">
	        <tbody>
	        <tr><td colspan="2"><h3>' . __('Credit Card Info:', 'psts') . '</h3></td></tr>
	    		<!-- Credit Card Type -->
	          <tr>
	    			<td class="pypl_label" align="right">' . __('Card Type:', 'psts') . '&nbsp;</td>
	    			<td>';
	    if ($errmsg = $psts->errors->get_error_message('card-type')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
	    $content .= '<label class="cc-image" title="Visa"><input type="radio" name="cc_card-type" value="Visa"' . (($cc_cardtype=='Visa') ? ' checked="checked"' : '') . ' /><img src="' . $img_base . 'visa.png" alt="Visa" /></label>
	          <label class="cc-image" title="MasterCard"><input type="radio" name="cc_card-type" value="MasterCard"' . (($cc_cardtype=='MasterCard') ? ' checked="checked"' : '') . ' /><img src="' . $img_base . 'mc.png" alt="MasterCard" /></label>
	          <label class="cc-image" title="American Express"><input type="radio" name="cc_card-type" value="Amex"' . (($cc_cardtype=='Amex') ? ' checked="checked"' : '') . ' /><img src="' . $img_base . 'amex.png" alt="American Express" /></label>
	          <label class="cc-image" title="Discover"><input type="radio" name="cc_card-type" value="Discover"' . (($cc_cardtype=='Discover') ? ' checked="checked"' : '') . ' /><img src="' . $img_base . 'discover.png" alt="Discover" /></label>
	          </td>
	    			</tr>

	          <tr>
	    			<td class="pypl_label" align="right">' . __('Card Number:', 'psts') . '&nbsp;</td>
	    			<td>';
	    if ($errmsg = $psts->errors->get_error_message('number')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
	    $content .= '<input name="cc_number" type="text" class="cctext" value="' . esc_attr($cc_number) . '" size="23" />
	    			</td>
	    			</tr>

	    			<tr>
	    			<td class="pypl_label" align="right">' . __('Expiration Date:', 'psts') . '&nbsp;</td>
	    			<td valign="middle">';
	    if ($errmsg = $psts->errors->get_error_message('expiration')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
	  	$content .= '<select name="cc_month">'.$this->month_dropdown($cc_month).'</select>&nbsp;/&nbsp;<select name="cc_year">'.$this->year_dropdown($cc_year).'</select>
	    			</td>
	    			</tr>

	    	    <!-- Card Security Code -->
	    	    <tr>
	            <td class="pypl_label" align="right"><nobr>' . __('Card Security Code:', 'psts') . '</nobr>&nbsp;</td>
	            <td valign="middle">';
	    if ($errmsg = $psts->errors->get_error_message('cvv2')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
	    $content .= '<label><input name="cc_cvv2" size="5" maxlength="4" type="password" class="cctext" title="' . __('Please enter a valid card security code. This is the 3 digits on the signature panel, or 4 digits on the front of Amex cards.', 'psts') . '" />
	            <img src="' . $img_base . 'buy-cvv.gif" height="27" width="42" title="' . __('Please enter a valid card security code. This is the 3 digits on the signature panel, or 4 digits on the front of Amex cards.', 'psts') . '" /></label>
	            </td>
	    			</tr>

	        <tr><td colspan="2"><h3>' . __('Billing Address:', 'psts') . '</h3></td></tr>
	    		<tr>
	    		<td class="pypl_label" align="right">' . __('First Name:', 'psts') . '*&nbsp;</td><td>';
	    if ($errmsg = $psts->errors->get_error_message('firstname')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
	    $content .= '<input name="cc_firstname" type="text" class="cctext" value="' . esc_attr($cc_firstname) . '" size="25" /> </td>
	    		</tr>
	    		<tr>
	    		<td class="pypl_label" align="right">' . __('Last Name:', 'psts') . '*&nbsp;</td><td>';
	    if ($errmsg = $psts->errors->get_error_message('lastname')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
	    $content .= '<input name="cc_lastname" type="text" class="cctext" value="' . esc_attr($cc_lastname) . '" size="25" /></td>
	    		</tr>
	    		<tr>

	    		<td class="pypl_label" align="right">' . __('Address:', 'psts') . '*&nbsp;</td><td>';
	    if ($errmsg = $psts->errors->get_error_message('address')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
	    $content .= '<input size="45" name="cc_address" type="text" class="cctext" value="' . esc_attr($cc_address) . '" /></td>
	    		</tr>
	    		<tr>

	    		<td class="pypl_label" align="right">' . __('Address 2:', 'psts') . '&nbsp;</td><td>
	        <input size="45" name="cc_address2" type="text" class="cctext" value="' . esc_attr($cc_address2) . '" /></td>
	    		</tr>
	    		<tr>
	    		<td class="pypl_label" align="right">' . __('City:', 'psts') . '*&nbsp;</td><td>';
	    if ($errmsg = $psts->errors->get_error_message('city')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
	    if ($errmsg = $psts->errors->get_error_message('state')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
	    $content .= '<input size="20" name="cc_city" type="text" class="cctext" value="' . esc_attr($cc_city) . '" />&nbsp;&nbsp; ' . __('State/Province:', 'psts') . '*&nbsp;<input size="5" name="cc_state" type="text" class="cctext" value="' . esc_attr($cc_state) . '" /></td>
	    		</tr>
	    		<tr>
	    		<td class="pypl_label" align="right">' . __('Postal/Zip Code:', 'psts') . '*&nbsp;</td><td>';
	    if ($errmsg = $psts->errors->get_error_message('zip')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
	    $content .= '<input size="10" name="cc_zip" type="text" class="cctext" value="' . esc_attr($cc_zip) . '" /> </td>
	    		</tr>
	    		<tr>

	    		<td class="pypl_label" align="right">' . __('Country:', 'psts') . '*&nbsp;</td><td>';
	    if ($errmsg = $psts->errors->get_error_message('country')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
	    //default to USA
	    if (empty($cc_country))
	      $cc_country = 'US';
	    $content .= '<select name="cc_country">';
	        foreach ($psts->countries as $key => $value) {
	          $content .= '<option value="' . $key . '"' . (($cc_country==$key) ? ' selected="selected"' : '') . '>' . esc_attr($value) . '</option>';
	        }
	    $content .= '</select>
	        </td>
	    		</tr>
	      </tbody></table>
	    	<p>
	        <input type="submit" id="cc_checkout" name="cc_checkout" value="' . __('Subscribe', 'psts') . ' &raquo;" />
	      </p>
				</div>';
		}

    $content .= '</form>';
		
		//put cancel button at end
		if ( defined('PSTS_CANCEL_LAST') )
			$content .= $cancel_content;
		
	  return $content;
	}

	function ipn_handler() {
    global $psts;

    if ( !isset($_POST['rp_invoice_id']) && !isset($_POST['custom']) ) {

			die('Error: Missing POST variables. Identification is not possible.');

    } else if ( defined('PSTS_IPN_PASSWORD') && $_POST['inc_pass'] != PSTS_IPN_PASSWORD ) {

		  header("HTTP/1.1 401 Authorization Required");
			die('Error: Missing a valid IPN forwarding password. Identification is not possible.');

		} else {

			//if not using an IPN forwarder check the request
			if ( !defined('PSTS_IPN_PASSWORD') ) {
        if ($psts->get_setting('pypl_status') == 'live') {
					$domain = 'https://www.paypal.com/cgi-bin/webscr';
				} else {
	        $domain = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
				}

				$req = 'cmd=_notify-validate';
				foreach ($_POST as $k => $v) {
					if (get_magic_quotes_gpc()) $v = stripslashes($v);
					$req .= '&' . $k . '=' . urlencode($v);
				}

      	$args['user-agent'] = "Pro Sites: http://premium.wpmudev.org/project/pro-sites | PayPal Express/Pro Gateway";
	      $args['body'] = $req;
	      $args['sslverify'] = false;
	      $args['timeout'] = 60;

	      //use built in WP http class to work with most server setups
	    	$response = wp_remote_post($domain, $args);

	    	//check results
	    	if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200 || $response['body'] != 'VERIFIED') {
	        header("HTTP/1.1 503 Service Unavailable");
	        die(__('There was a problem verifying the IPN string with PayPal. Please try again.', 'psts'));
	      }
			}

		  $custom = (isset($_POST['rp_invoice_id'])) ? $_POST['rp_invoice_id'] : $_POST['custom'];

			// get custom field values
			@list($pre, $blog_id, $level, $period, $amount, $currency, $timestamp) = explode('_', $custom);

			// process PayPal response
			$new_status = false;

			$profile_string = (isset($_POST['recurring_payment_id'])) ? ' - ' . $_POST['recurring_payment_id'] : '';

			$payment_status = (isset($_POST['initial_payment_status'])) ? $_POST['initial_payment_status'] : $_POST['payment_status'];

		  switch ($payment_status) {

		    case 'Canceled-Reversal':
		      $psts->log_action( $blog_id, sprintf(__('PayPal IPN "%s" received: A reversal has been canceled; for example, when you win a dispute and the funds for the reversal have been returned to you.', 'psts'), $payment_status) . $profile_string );
					break;

		    case 'Expired':
		      $psts->log_action( $blog_id, sprintf(__('PayPal IPN "%s" received: The authorization period for this payment has been reached.', 'psts'), $payment_status) . $profile_string );
					break;

		    case 'Voided':
		      $psts->log_action( $blog_id, sprintf(__('PayPal IPN "%s" received: An authorization for this transaction has been voided.', 'psts'), $payment_status) . $profile_string );
					break;

		    case 'Failed':
		      $psts->log_action( $blog_id, sprintf(__('PayPal IPN "%s" received: The payment has failed. This happens only if the payment was made from your customer\'s bank account.', 'psts'), $payment_status) . $profile_string );
          $psts->email_notification($blog_id, 'failed');
					break;

				case 'Partially-Refunded':
		      $psts->log_action( $blog_id, sprintf(__('PayPal IPN "%s" received: The payment has been partially refunded with %s.', 'psts'), $payment_status, $psts->format_currency($_POST['mc_currency'], $_POST['mc_gross'])) . $profile_string );
          $psts->record_refund_transaction($blog_id, $_POST['txn_id'], abs($_POST['mc_gross']));
					break;

				case 'In-Progress':
		      $psts->log_action( $blog_id, sprintf(__('PayPal IPN "%s" received: The transaction has not terminated, e.g. an authorization may be awaiting completion.', 'psts'), $payment_status) . $profile_string );
					break;

				case 'Reversed':
					$status = __('A payment was reversed due to a chargeback or other type of reversal. The funds have been removed from your account balance: ', 'psts');
		      $reverse_reasons = array(
		        'none' => '',
		        'chargeback' => __('A reversal has occurred on this transaction due to a chargeback by your customer.', 'psts'),
		        'chargeback_reimbursement' => __('A reversal has occurred on this transaction due to a reimbursement of a chargeback.', 'psts'),
		        'chargeback_settlement' => __('A reversal has occurred on this transaction due to settlement of a chargeback.', 'psts'),
		        'guarantee' => __('A reversal has occurred on this transaction due to your customer triggering a money-back guarantee.', 'psts'),
		        'buyer_complaint' => __('A reversal has occurred on this transaction due to a complaint about the transaction from your customer.', 'psts'),
						'unauthorized_claim' => __('A reversal has occurred on this transaction due to the customer claiming it as an unauthorized payment.', 'psts'),
		        'refund' => __('A reversal has occurred on this transaction because you have given the customer a refund.', 'psts'),
		        'other' => __('A reversal has occurred on this transaction due to an unknown reason.', 'psts')
		        );
		      $status .= $reverse_reasons[$_POST["reason_code"]];
		      $psts->log_action( $blog_id, sprintf(__('PayPal IPN "%s" received: %s', 'psts'), $payment_status, $status) . $profile_string );
		      $psts->withdraw($blog_id, $period);
		      $psts->record_refund_transaction($blog_id, $_POST['txn_id'], abs($_POST['mc_gross']));
					break;

				case 'Refunded':
					$psts->log_action( $blog_id, sprintf(__('PayPal IPN "%s" received: You refunded the payment with %s.', 'psts'), $payment_status, $psts->format_currency($_POST['mc_currency'], $_POST['mc_gross'])) . $profile_string );
          $psts->record_refund_transaction($blog_id, $_POST['txn_id'], abs($_POST['mc_gross']));
					break;

				case 'Denied':
					$psts->log_action( $blog_id, sprintf(__('PayPal IPN "%s" received: You denied the payment when it was marked as pending.', 'psts'), $payment_status) . $profile_string );
        	$psts->withdraw($blog_id, $period);
					break;

				case 'Completed':
				case 'Processed':
					// case: successful payment

		      //receipts and record new transaction
		      if ($_POST['txn_type'] == 'recurring_payment' || $_POST['txn_type'] == 'express_checkout' || $_POST['txn_type'] == 'web_accept') {
		        $psts->record_transaction($blog_id, $_POST['txn_id'], $_POST['mc_gross']);
		        $psts->log_action( $blog_id, sprintf(__('PayPal IPN "%s" received: %s %s payment received, transaction ID %s', 'psts'), $payment_status, $psts->format_currency($_POST['mc_currency'], $_POST['mc_gross']), $_POST['txn_type'], $_POST['txn_id']) . $profile_string );
            
						//extend only if a recurring payment, first payments are handled below
						if ($_POST['txn_type'] == 'recurring_payment')
							$psts->extend($blog_id, $period, 'PayPal Express/Pro', $level, $_POST['mc_gross']);
						
            //in case of new member send notification
			    	if (get_blog_option($blog_id, 'psts_waiting_step') && $_POST['txn_type'] == 'express_checkout') {
							$psts->extend($blog_id, $period, 'PayPal Express/Pro', $level, $_POST['mc_gross']);
			        $psts->email_notification($blog_id, 'success');
			        $psts->record_stat($blog_id, 'signup');
              update_blog_option($blog_id, 'psts_waiting_step', 0);
            }

						$psts->email_notification($blog_id, 'receipt');
		      }

					break;

				case 'Pending':
					// case: payment is pending
		      $pending_str = array(
		  			'address' => __('The payment is pending because your customer did not include a confirmed shipping address and your Payment Receiving Preferences is set such that you want to manually accept or deny each of these payments. To change your preference, go to the Preferences  section of your Profile.', 'psts'),
		  			'authorization' => __('The payment is pending because it has been authorized but not settled. You must capture the funds first.', 'psts'),
		  			'echeck' => __('The payment is pending because it was made by an eCheck that has not yet cleared.', 'psts'),
		  			'intl' => __('The payment is pending because you hold a non-U.S. account and do not have a withdrawal mechanism. You must manually accept or deny this payment from your Account Overview.', 'psts'),
		  			'multi-currency' => __('You do not have a balance in the currency sent, and you do not have your Payment Receiving Preferences set to automatically convert and accept this payment. You must manually accept or deny this payment.', 'psts'),
		        'order' => __('The payment is pending because it is part of an order that has been authorized but not settled.', 'psts'),
		        'paymentreview' => __('The payment is pending while it is being reviewed by PayPal for risk.', 'psts'),
		        'unilateral' => __('The payment is pending because it was made to an email address that is not yet registered or confirmed.', 'psts'),
		  			'upgrade' => __('The payment is pending because it was made via credit card and you must upgrade your account to Business or Premier status in order to receive the funds. It can also mean that you have reached the monthly limit for transactions on your account.', 'psts'),
		  			'verify' => __('The payment is pending because you are not yet verified. You must verify your account before you can accept this payment.', 'psts'),
		  			'other' => __('The payment is pending for an unknown reason. For more information, contact PayPal customer service.', 'psts'),
		        '*' => ''
					);
					$reason = @$_POST['pending_reason'];
					$psts->log_action( $blog_id, sprintf(__('PayPal IPN "%s" received: Last payment is pending (%s). Reason: %s', 'psts'), $payment_status, $_POST['txn_id'], $pending_str[$reason]) . $profile_string );
					break;

				default:
					// case: various error cases

			}

			// handle exceptions from the subscription specific fields
			if (in_array($_POST['txn_type'], array(/*'subscr_cancel', */'subscr_failed', 'subscr_eot'))) {
		    $psts->log_action( $blog_id, sprintf(__('PayPal subscription IPN "%s" received.', 'psts'), $_POST['txn_type']) . $profile_string );
			}

		  //new subscriptions (after cancelation)
			if ($_POST['txn_type'] == 'recurring_payment_profile_created') {

		    $psts->log_action( $blog_id, sprintf(__('PayPal subscription IPN "%s" received.', 'psts'), $_POST['txn_type']) . $profile_string );

        //save new profile_id
        $this->set_profile_id($blog_id, $_POST['recurring_payment_id']);

		    //failed initial payment
		    if ($_POST['initial_payment_status'] == 'Failed') {
		      $psts->email_notification($blog_id, 'failed');
		    }
		  }

		  //cancelled subscriptions
			if ($_POST['txn_type'] == 'subscr_cancel') {
		    $psts->log_action( $blog_id, sprintf(__('PayPal subscription IPN "%s" received. The subscription has been canceled.', 'psts'), $_POST['txn_type']) . $profile_string );

    		//$psts->email_notification($blog_id, 'canceled');
        $psts->record_stat($blog_id, 'cancel');
			}
		}
		exit;
	}
	
	function cancel_blog_subscription($blog_id) {
		global $psts;
		
		//check if pro/express user
    if ($profile_id = $this->get_profile_id($blog_id)) {
  
			$resArray = $this->ManageRecurringPaymentsProfileStatus($profile_id, 'Cancel', __('Your subscription was canceled because the blog was deleted.', 'psts'));

			if ($resArray['ACK']=='Success' || $resArray['ACK']=='SuccessWithWarning') {
				 //record stat
				$psts->record_stat($blog_id, 'cancel');
				
				$psts->email_notification($blog_id, 'canceled');

				$psts->log_action( $blog_id, __('Subscription successfully canceled because the blog was deleted.', 'psts') );
			}
		}
	}
	
	//record last payment
	function set_profile_id($blog_id, $profile_id) {
	  $trans_meta = get_blog_option($blog_id, 'psts_paypal_profile_id');

	  $trans_meta[$profile_id]['profile_id'] = $profile_id;
	  $trans_meta[$profile_id]['timestamp'] = time();
	  update_blog_option($blog_id, 'psts_paypal_profile_id', $trans_meta);
	}
	
	function get_profile_id($blog_id, $history = false) {
	  $trans_meta = get_blog_option($blog_id, 'psts_paypal_profile_id');
		
	  if ( is_array( $trans_meta ) ) {
			$last = array_pop( $trans_meta );
			if ( $history ) {
				return $trans_meta;
			} else {
				return $last['profile_id'];
			}
	  } else if ( !empty($trans_meta) ) {
	    return $trans_meta;
	  } else {
			return false;
		}
	}
	
	/**** PayPal API methods *****/

	function SetExpressCheckout($paymentAmount, $desc, $blog_id) {
    global $psts;
	  $nvpstr = "&CURRENCYCODE=" . $psts->get_setting('pypl_currency');
	  $nvpstr .= "&AMT=" . ($paymentAmount * 2); //enough to authorize first payment and subscription amt
	  $nvpstr .= "&L_BILLINGTYPE0=RecurringPayments";
	  $nvpstr .= "&L_BILLINGAGREEMENTDESCRIPTION0=".urlencode(html_entity_decode($desc, ENT_COMPAT, "UTF-8"));
	  $nvpstr .= "&LOCALECODE=" . $psts->get_setting('pypl_site');
	  //$nvpstr .= "&LANDINGPAGE=Billing";
		$nvpstr .= "&NOSHIPPING=1";
		$nvpstr .= "&ALLOWNOTE=0";
		$nvpstr .= "&RETURNURL=" . urlencode($psts->checkout_url($blog_id) . '&action=complete');
		$nvpstr .= "&CANCELURL=" . urlencode($psts->checkout_url($blog_id) . '&action=canceled');
		$nvpstr .= "&PAYMENTACTION=Sale";
	  //$nvpstr .= "&ALLOWEDPAYMENTMETHOD=InstantPaymentOnly";

	  //formatting
		$nvpstr .= "&HDRIMG=" . urlencode($psts->get_setting('pypl_header_img'));
	  $nvpstr .= "&HDRBORDERCOLOR=" . urlencode($psts->get_setting('pypl_header_border'));
		$nvpstr .= "&HDRBACKCOLOR=" . urlencode($psts->get_setting('pypl_header_back'));
	  $nvpstr .= "&PAYFLOWCOLOR=" . urlencode($psts->get_setting('pypl_page_back'));

	  $resArray = $this->api_call("SetExpressCheckout", $nvpstr);

	  return $resArray;
	}

	function DoExpressCheckoutPayment($token, $payer_id, $paymentAmount, $frequency, $desc, $blog_id, $level, $modify = false) {
    global $psts;

    if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $level) ) {
      $coupon = true;
      $coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
    } else {
			$coupon = false;
		}

    $nvpstr = "&TOKEN=" .urlencode($token);
    $nvpstr .= "&PAYERID=" . urlencode($payer_id);

    //handle discounts
    if ($coupon && !$modify) { // already expired
      $nvpstr .= "&AMT=".round($coupon_value['new_total'], 2);
    } else if (!$modify) { // normal checkout
      $nvpstr .= "&AMT=$paymentAmount";
    }
		$nvpstr .= "&L_BILLINGTYPE0=RecurringPayments";
		$nvpstr .= "&PAYMENTACTION=Sale";
		$nvpstr .= "&CURRENCYCODE=" . $psts->get_setting('pypl_currency');
		$nvpstr .= "&DESC=".urlencode(html_entity_decode($desc, ENT_COMPAT, "UTF-8"));
		$nvpstr .= "&CUSTOM=" . PSTS_PYPL_PREFIX . '_' . $blog_id . '_' . $level . '_' . $frequency . '_' . $paymentAmount . '_' . $psts->get_setting('pypl_currency') . '_' . time();

	  $resArray = $this->api_call("DoExpressCheckoutPayment", $nvpstr);

		return $resArray;
	}

	function CreateRecurringPaymentsProfileExpress($token, $paymentAmount, $frequency, $desc, $blog_id, $level, $modify = false) {
    global $psts;
    
    if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $level) ) {
      $coupon = true;
      $coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
    } else {
			$coupon = false;
		}

    $nvpstr = "&TOKEN=" . $token;
    $nvpstr .= "&AMT=$paymentAmount";

    //handle discounts
    if ($coupon && $modify) { // expiration is in the future
      $nvpstr .= "&TRIALBILLINGPERIOD=Month";
  		$nvpstr .= "&TRIALBILLINGFREQUENCY=$frequency";
  		$nvpstr .= "&TRIALTOTALBILLINGCYCLES=1";
  		$nvpstr .= "&TRIALAMT=".round($coupon_value['new_total'], 2);
    }

	  $nvpstr .= "&CURRENCYCODE=" . $psts->get_setting('pypl_currency');
		$nvpstr .= "&PROFILESTARTDATE=".(($modify) ? $this->modStartDate($modify) : $this->startDate($frequency));
		$nvpstr .= "&BILLINGPERIOD=Month";
		$nvpstr .= "&BILLINGFREQUENCY=$frequency";
		$nvpstr .= "&DESC=".urlencode(html_entity_decode($desc, ENT_COMPAT, "UTF-8"));
		$nvpstr .= "&MAXFAILEDPAYMENTS=1";
	  $nvpstr .= "&PROFILEREFERENCE=" . PSTS_PYPL_PREFIX . '_' . $blog_id . '_' . $level . '_' . $frequency . '_' . $paymentAmount . '_' . $psts->get_setting('pypl_currency') . '_' . time();
    
	  $resArray = $this->api_call("CreateRecurringPaymentsProfile", $nvpstr);

		return $resArray;
	}

	function CreateRecurringPaymentsProfileDirect($paymentAmount, $frequency, $desc, $blog_id, $level, $cctype, $acct, $expdate, $cvv2, $firstname, $lastname, $street, $street2, $city, $state, $zip, $countrycode, $email, $modify = false) {
    global $psts;
		
    if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $level) ) {
      $coupon = true;
      $coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
    } else {
			$coupon = false;
		}

    $nvpstr = "&AMT=$paymentAmount";

    //handle discounts
    if ($coupon && $modify) { // expiration is in the future
      $nvpstr .= "&TRIALBILLINGPERIOD=Month";
  		$nvpstr .= "&TRIALBILLINGFREQUENCY=$frequency";
  		$nvpstr .= "&TRIALTOTALBILLINGCYCLES=1";
  		$nvpstr .= "&TRIALAMT=".round($coupon_value['new_total'], 2);
    }

	  $nvpstr .= "&CURRENCYCODE=" . $psts->get_setting('pypl_currency');
		$nvpstr .= "&PROFILESTARTDATE=".(($modify) ? $this->modStartDate($modify) : $this->startDate($frequency));
		$nvpstr .= "&BILLINGPERIOD=Month";
		$nvpstr .= "&BILLINGFREQUENCY=$frequency";
		$nvpstr .= "&DESC=".urlencode(html_entity_decode($desc, ENT_COMPAT, "UTF-8"));
		$nvpstr .= "&MAXFAILEDPAYMENTS=1";
		$nvpstr .= "&PROFILEREFERENCE=" . PSTS_PYPL_PREFIX . '_' . $blog_id . '_' . $level . '_' . $frequency . '_' . $paymentAmount . '_' . $psts->get_setting('pypl_currency') . '_' . time();
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

	  $resArray = $this->api_call("CreateRecurringPaymentsProfile", $nvpstr);

		return $resArray;
	}

	function DoDirectPayment($paymentAmount, $frequency, $desc, $blog_id, $level, $cctype, $acct, $expdate, $cvv2, $firstname, $lastname, $street, $street2, $city, $state, $zip, $countrycode, $email, $modify = false) {
    global $psts;

    if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $level) ) {
      $coupon = true;
      $coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
    } else {
			$coupon = false;
		}

    //handle discounts
    if ($coupon && !$modify) { // already expired
      $nvpstr = "&AMT=".round($coupon_value['new_total'], 2);
    } else if (!$modify) { // normal checkout
      $nvpstr = "&AMT=$paymentAmount";
    }
    
    $nvpstr .= "&IPADDRESS=" . $_SERVER['REMOTE_ADDR'];
    $nvpstr .= "&PAYMENTACTION=Sale";
    $nvpstr .= "&CURRENCYCODE=" . $psts->get_setting('pypl_currency');
		$nvpstr .= "&DESC=".urlencode(html_entity_decode($desc, ENT_COMPAT, "UTF-8"));
		$nvpstr .= "&CUSTOM=" . PSTS_PYPL_PREFIX . '_' . $blog_id . '_' . $level . '_' . $frequency . '_' . $paymentAmount . '_' . $psts->get_setting('pypl_currency') . '_' . time();
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

	  $resArray = $this->api_call("DoDirectPayment", $nvpstr);

		return $resArray;
	}

	function GetTransactionDetails($transaction_id) {

	  $nvpstr = "&TRANSACTIONID=" . $transaction_id;

	  $resArray = $this->api_call("GetTransactionDetails", $nvpstr);

		return $resArray;
	}

	function GetRecurringPaymentsProfileDetails($profile_id) {

	  $nvpstr = "&PROFILEID=" . $profile_id;

	  $resArray = $this->api_call("GetRecurringPaymentsProfileDetails", $nvpstr);

		return $resArray;
	}

	function ManageRecurringPaymentsProfileStatus($profile_id, $action, $note) {

	  $nvpstr = "&PROFILEID=" . $profile_id;
	  $nvpstr .= "&ACTION=$action"; //Should be Cancel, Suspend, Reactivate
	  $nvpstr .= "&NOTE=".urlencode(html_entity_decode($desc, ENT_COMPAT, "UTF-8"));

	  $resArray = $this->api_call("ManageRecurringPaymentsProfileStatus", $nvpstr);

		return $resArray;
	}

	function UpdateRecurringPaymentsProfile($profile_id, $custom) {

	  $nvpstr = "&PROFILEID=" . $profile_id;
		$nvpstr .= "&PROFILEREFERENCE=$custom";

	  $resArray = $this->api_call("UpdateRecurringPaymentsProfile", $nvpstr);

		return $resArray;
	}
	
	function RefundTransaction($transaction_id, $partial_amt = false, $note = '') {
		global $psts;
    $nvpstr = "&TRANSACTIONID=" . $transaction_id;

    if ($partial_amt) {
      $nvpstr .= "&REFUNDTYPE=Partial";
      $nvpstr .= "&AMT=".urlencode($partial_amt);
      $nvpstr .= "&CURRENCYCODE=". $psts->get_setting('pypl_currency');
    } else {
      $nvpstr .= "&REFUNDTYPE=Full";
    }

    if ($note)
      $nvpstr .= "&NOTE=".urlencode($note);

    $resArray = $this->api_call("RefundTransaction", $nvpstr);

		return $resArray;
	}
	
	function api_call($methodName, $nvpStr) {
    global $psts;
    
	  //set api urls
		if ($psts->get_setting('pypl_status') == 'live')	{
			$API_Endpoint = "https://api-3t.paypal.com/nvp";
		} else {
			$API_Endpoint = "https://api-3t.sandbox.paypal.com/nvp";
		}

	  //NVPRequest for submitting to server
		$query_string = "METHOD=" . urlencode($methodName) . "&VERSION=63.0&PWD=" . urlencode($psts->get_setting('pypl_api_pass')) . "&USER=" . urlencode($psts->get_setting('pypl_api_user')) . "&SIGNATURE=" . urlencode($psts->get_setting('pypl_api_sig')) . $nvpStr;

	  //print_r(deformatNVP($query_string));

	  //build args
		$args['user-agent'] = "Pro Sites: http://premium.wpmudev.org/project/pro-sites | PayPal Express/Pro Gateway";
	  $args['body'] = $query_string;
	  $args['sslverify'] = false;
	  $args['timeout'] = 60;

	  //use built in WP http class to work with most server setups
		$response = wp_remote_post($API_Endpoint, $args);

		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
			trigger_error( 'Pro Sites: Problem contacting PayPal API - ' . $response->get_error_message(), E_USER_WARNING );
			return false;
	  } else {
	    //convert NVPResponse to an Associative Array
		  $nvpResArray = $this->deformatNVP($response['body']);
		  return $nvpResArray;
	  }
	}

	function RedirectToPayPal($token) {
	  global $psts;
	  
	  //set api urls
  	if ($psts->get_setting('pypl_status') == 'live')	{
			$paypalURL = "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";
		} else {
			$paypalURL = "https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=";
		}

		// Redirect to paypal.com here
		$url = $paypalURL . $token;
		wp_redirect($url);
	  exit;
	}

	//This function will take NVPString and convert it to an Associative Array and it will decode the response.
	function deformatNVP($nvpstr) {
		parse_str($nvpstr, $nvpArray);
		return $nvpArray;
	}
	
	function parse_error_string($resArray, $sep = ', ') {
		$errors = array();
		for ($i = 0; $i < 10; $i++) {
			if (isset($resArray["L_LONGMESSAGE$i"]))
				$errors[] = $resArray["L_LONGMESSAGE$i"];
		}
		return implode($sep, $errors);
	}
	
	function startDate($frequency) {
	  $result = strtotime("+$frequency month");
	  return urlencode(gmdate('Y-m-d\TH:i:s.00\Z', $result));
	}

	function modStartDate($expire_stamp) {
	  return urlencode(gmdate('Y-m-d\TH:i:s.00\Z', $expire_stamp));
	}

}

//register the gateway
psts_register_gateway( 'ProSites_Gateway_PayPalExpressPro', __('Paypal Express/Pro', 'psts'), __('Express Checkout is PayPal\'s premier checkout solution, which streamlines the checkout process for buyers and keeps them on your site after making a purchase. Enabling the optional PayPal Pro allows you to seamlessly accept credit cards on your site, and gives you the most professional look with a widely accepted payment method.', 'psts') );
?>