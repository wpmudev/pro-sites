<?php
/*
Pro Sites (Gateway: Manual Payments Gateway)
*/
class ProSites_Gateway_Manual {
	
	var $complete_message = false;

	function __construct() {
		//settings
		add_action( 'psts_gateway_settings', array( &$this, 'settings' ) );

		//checkout stuff
		add_filter( 'psts_checkout_output', array( &$this, 'checkout_screen' ), 10, 2 );
		add_action( 'psts_checkout_page_load', array( &$this, 'process_checkout' ) );
	}

	function settings() {
        global $psts;
        ?>
        <div class="postbox">
                    <h3 class="hndle" style="cursor:auto;"><span><?php _e( 'Manual Payments', 'psts' ) ?></span> - <span class="description"><?php _e( 'Record payments manually, such as by Cash, Check, EFT, or an unsupported gateway.', 'psts' ); ?></span></h3>
                    <div class="inside">
                        <table class="form-table">
			  <tr>
				<th scope="row"><?php _e('Method Name', 'psts') ?></th>
				<td>
					<span class="description"><?php _e('Enter a public name for this payment method that is displayed to users - No HTML', 'psts') ?></span>
			    <p>
			    <input value="<?php echo esc_attr($psts->get_setting("mp_name")); ?>" size="100" name="psts[mp_name]" type="text" />
			    </p>
			  </td>
			  </tr>
			  <tr valign="top">
			  <th scope="row"><?php _e('User Instructions', 'psts') ?></th>
			  <td>
					<span class="description"><?php _e('These are the manual payment instructions to display on the payments screen - HTML allowed', 'psts') ?></span>
			    <textarea name="psts[mp_instructions]" type="text" rows="4" wrap="soft" id="mp_instructions" style="width: 95%"/><?php echo esc_textarea($psts->get_setting('mp_instructions')); ?></textarea>
			  </td>
			  </tr>
				<tr valign="top">
				<th scope="row"><?php _e('Show Submission Form', 'psts') ?></th>
				<td>
					<span class="description"><?php _e('Shows a submission form where the user can select the desired level and enter open-ended text according to your instructions. Example if you instruct users to pay via wire, they could then submit the level they desire and confirmation number. The form submission will come to the network admin email address.', 'psts'); ?></span><br/>
					<label><input type="radio" name="psts[mp_show_form] value="1"<?php checked($psts->get_setting('mp_show_form', 0), 1); ?>> <?php _e('Yes', 'psts') ?></label>&nbsp;&nbsp;
					<label><input type="radio" name="psts[mp_show_form] value="0"<?php checked($psts->get_setting('mp_show_form', 0), 0); ?>> <?php _e('No', 'psts') ?></label>
				</td>
				</tr>
				<tr valign="top">
				  <th scope="row"><?php _e('Submission Form Email', 'psts') ?></th>
				  <td>
				  <input type="text" name="psts[mp_email]" id="mp_email" value="<?php echo esc_attr($psts->get_setting('mp_email', get_site_option("admin_email"))); ?>" size="40" />
				  <br /><span class="description"><?php _e('The email address to send manual payment form submissions to.', 'psts') ?></span></td>
				  </tr>
			 </table>
		  </div>
		</div>
	  <?php
	}

  function payment_info($payment_info, $blog_id) {
  	global $psts;

    $payment_info .= __('Payment Method: PayPal Account', 'psts')."\n";
    $payment_info .= sprintf(__('Next Payment Date: %s', 'psts'), date_i18n(get_option('date_format'), $psts->get_expire($blog_id)))."\n";

    return $payment_info;
  }
	
	function process_checkout($blog_id) {
		global $psts, $current_user;
		
		//check for level
		if (empty($_POST['level']) || empty($_POST['period'])) {
			$psts->errors->add('general', __('Please choose your desired level and payment plan.', 'psts'));
			return;
		}
		
		$subject = __('Pro Sites Manual Payment Submission', 'psts');
		$message = sprintf(__('The user "%s" has submitted a manual payment request via the Pro Sites checkout form.', 'psts'), $current_user->user_login) . "\n\n";
		$message .= __('Level: ', 'psts') . intval($_POST['level']) . ' - ' . $psts->get_level_setting(intval($_POST['level']), 'name') . "\n";
		$message .= __('Period: ', 'psts') . sprintf( __('Every %d Months', 'psts'), intval($_POST['period']) ) . "\n";
		$message .= sprintf(__("User Email: %s", 'psts'), $current_user->user_email) . "\n";
		$message .= sprintf(__("Site Address: %s", 'psts'), get_home_url($blog_id)) . "\n";
		$message .= sprintf(__("Manage Site: %s", 'psts'), network_admin_url('admin.php?page=psts&bid=' . $blog_id)) . "\n\n";
		
		if (!empty($_POST['psts_mp_text'])) {
			$message .= __('User-Entered Comments:', 'psts')."\n";
			$message .= wp_specialchars_decode(stripslashes(wp_filter_nohtml_kses($_POST['psts_mp_text'])), ENT_QUOTES);
		}

		wp_mail($psts->get_setting('mp_email', get_site_option("admin_email")), $subject, $message);
		
		//send email with text and desired level/plan
		$this->complete_message = __('Thank you, we have been notified of your request.', 'psts');	
	}
	
	function checkout_screen($content, $blog_id) {
	  global $psts, $wpdb, $current_site, $current_user;

	  if (!$blog_id)
	    return $content;
    
    //hide top part of content if its a pro blog
		if ( is_pro_site($blog_id) )
			$content = '';
			
		if ($errmsg = $psts->errors->get_error_message('general')) {
			$content .= '<div id="psts-general-error" class="psts-error">'.$errmsg.'</div>';
		}
    
		//if transaction was successful display a complete message and skip the rest
	  if ($this->complete_message) {
	    $content = '<div id="psts-complete-msg">' . $this->complete_message . '</div>';
	    return $content;
	  }
		
		if ( is_pro_site($blog_id) ) {
			
			$end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
			$level = $psts->get_level_setting($psts->get_level($blog_id), 'name');
			$old_gateway = $wpdb->get_var("SELECT gateway FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'");
			
			$content .= '<div id="psts_existing_info">';
			$content .= '<h3>'.__('Your Account Information', 'psts').'</h3><ul>';
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
				$content .= '<h3>'.__('Cancel Your Subscription', 'psts').'</h3>';
				$content .= '<p>'.sprintf(__('If your subscription is still active your next scheduled payment should be %1$s.', 'psts'), $end_date).'</p>';
				$content .= '<p>'.sprintf(__('If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts'), $level, $end_date).'</p>';
				//show instructions for old gateways
				if ($old_gateway == 'PayPal') {
					$content .= '<p><a id="pypl_cancel" target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_subscr-find&alias='.urlencode(get_site_option("supporter_paypal_email")).'" title="'.__('Cancel Your Subscription', 'psts').'"><img src="'.$psts->plugin_url. 'images/cancel_subscribe_gen.gif" /></a><br /><small>'.__('You can also cancel following <a href="https://www.paypal.com/webapps/helpcenter/article/?articleID=94044#canceling_recurring_paymemt_subscription_automatic_billing">these steps</a>.', 'psts').'</small></p>';
				} else if ($old_gateway == 'Amazon') {
					$content .= '<p>'.__('To cancel your subscription, simply go to <a id="pypl_cancel" target="_blank" href="https://payments.amazon.com/">https://payments.amazon.com/</a>, click Your Account at the top of the page, log in to your Amazon Payments account (if asked), and then click the Your Subscriptions link. This page displays your subscriptions, showing the most recent, active subscription at the top. To view the details of a specific subscription, click Details. Then cancel your subscription by clicking the Cancel Subscription button on the Subscription Details page.', 'psts').'</p>';
				}
			}
			$content .= '</div>';
			
		}
		
    $content .= '<form action="'.$psts->checkout_url($blog_id).'" method="post">';
    
    //print the checkout grid
    $content .= $psts->checkout_grid($blog_id);
    
    $content .= '<div id="psts-manual-checkout"><h2>' . $psts->get_setting('mp_name') . '</h2>';
		
		$content .= '<div id="psts-manual-instructions">' . do_shortcode( $psts->get_setting('mp_instructions') ) . '</div>';
		
		if ( $psts->get_setting('mp_show_form') ) {
			$prefill = isset($_POST['psts_mp_text']) ? esc_textarea(stripslashes($_POST['psts_mp_text'])) : '';
			$content .= '<textarea id="psts-manual-textarea" name="psts_mp_text">' . $prefill . '</textarea>';
			$content .= '<p><input id="psts-manual-submit" type="submit" name="psts_mp_submit" value="' . esc_attr__('Submit', 'psts') . '"></p>';
		}
		
    $content .= '</div></form>';

	  return $content;
	}
}

//register the gateway
psts_register_gateway( 'ProSites_Gateway_Manual', __('Manual Payments', 'psts'), __('Record payments manually, such as by Cash, Check, EFT, or an unsupported gateway.', 'psts') );
?>