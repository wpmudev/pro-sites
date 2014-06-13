<?php
/*
Pro Sites (Gateway: Stripe Payment Gateway)
*/

class ProSites_Gateway_Stripe {

	var $complete_message = false;
	var $stripe_plans = array();

	function __construct() {
		global $psts;

		//setup the Stripe API
		if ( ! class_exists( 'Stripe' ) ) {
			require_once( $psts->plugin_dir . "gateways/gateway-stripe-files/lib/Stripe.php" );
		}
		$stripe_secret_key = $psts->get_setting( 'stripe_secret_key' );
		Stripe::setApiKey( $stripe_secret_key );
		Stripe::setApiVersion( '2013-08-13' ); //make sure everyone is using the same API version. we can update this if/when necessary.

		if ( ! is_admin() ) {
			add_action( 'wp_enqueue_scripts', array( &$this, 'do_scripts' ) );
		}
			
	  //settings
		add_action( 'psts_gateway_settings', array(&$this, 'settings') );
		add_action( 'psts_settings_process', array(&$this, 'settings_process') );
		
		//checkout stuff
		add_action( 'psts_checkout_page_load', array(&$this, 'process_checkout') );
		add_filter( 'psts_checkout_output', array(&$this, 'checkout_screen'), 10, 3 );
		add_filter( 'psts_force_ssl', array(&$this, 'force_ssl') );
		
		//handle webhook notifications
		add_action( 'wp_ajax_nopriv_psts_stripe_webhook', array(&$this, 'webhook_handler') );
		add_action( 'wp_ajax_psts_stripe_webhook', array(&$this, 'webhook_handler') );
		
		//sync levels with Stripe
		add_action( 'update_site_option_psts_levels', array(&$this, 'update_psts_levels'), 10, 3 );
		
		//plug management page
		add_action( 'psts_subscription_info', array(&$this, 'subscription_info') );
		add_action( 'psts_subscriber_info', array(&$this, 'subscriber_info') );
		add_action( 'psts_modify_form', array(&$this, 'modify_form') );
		add_action( 'psts_modify_process', array(&$this, 'process_modify') );
		add_action( 'psts_transfer_pro', array(&$this, 'process_transfer'), 10, 2 );
		
		//filter payment info
		add_action( 'psts_payment_info', array(&$this, 'payment_info'), 10, 2 );
		
		//return next payment date for emails
		add_filter( 'psts_next_payment', array(&$this, 'next_payment') );
		
		//cancel subscriptions on blog deletion
		add_action( 'delete_blog', array(&$this, 'cancel_blog_subscription') );
		
		//display admin notices
		add_action( 'admin_notices', array(&$this, 'admin_notices') );
		
		//update install script if necessary
		if ($psts->get_setting('stripe_version') != $psts->version) {
			$this->install();
		}
	}

	function do_scripts() {
		global $psts;
		
		if ( get_the_ID() != $psts->get_setting('checkout_page') )
			return;
		
		$stripe_secret_key = $psts->get_setting('stripe_secret_key');
		$stripe_publishable_key = $psts->get_setting('stripe_publishable_key');
		
		wp_enqueue_script('jquery');
		wp_enqueue_script( 'js-stripe', 'https://js.stripe.com/v2/', array('jquery') );	
		wp_enqueue_script( 'stripe-token', $psts->plugin_url . 'gateways/gateway-stripe-files/stripe_token.js', array('js-stripe', 'jquery') );
		wp_localize_script( 'stripe-token', 'stripe', array('publisher_key' => $stripe_publishable_key,
																												'name' =>__('Please enter the full Cardholder Name.', 'psts'),
																												'number' => __('Please enter a valid Credit Card Number.', 'psts'),
																												'expiration' => __('Please choose a valid expiration date.', 'psts'),
																												'cvv2' => __('Please enter a valid card security code. This is the 3 digits on the signature panel, or 4 digits on the front of Amex cards.', 'psts')
																												) );			
		add_action('wp_head', array(&$this, 'checkout_js'));		
	}
	
	function install() {
		global $wpdb, $psts;
		
		$table1 = "CREATE TABLE `{$wpdb->base_prefix}pro_sites_stripe_customers` (
		  blog_id bigint(20) NOT NULL,
			customer_id char(20) NOT NULL,
			PRIMARY KEY  (blog_id),
			UNIQUE KEY ix_customer_id (customer_id)
		) DEFAULT CHARSET=utf8;";
		
		if ( !defined('DO_NOT_UPGRADE_GLOBAL_TABLES') || (defined('DO_NOT_UPGRADE_GLOBAL_TABLES') && !DO_NOT_UPGRADE_GLOBAL_TABLES) ) {
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($table1);
		}
		
		if ( $stripe_secret_key = $psts->get_setting('stripe_secret_key') ) {
			$psts->update_setting('stripe_version', $psts->version);
			
			if ( $psts->get_setting('stripe_plan_ids_updated', false) ) {
				$this->update_psts_levels( 'psts_levels', get_site_option('psts_levels'), get_site_option('psts_levels') );
			} else {
				$this->update_plan_ids_v2();
			}
		}
	}
	
	//display admin notices (if applicable)
	function admin_notices() {
		$blog_id = get_current_blog_id();
		
		if ( 1 == get_blog_option($blog_id, 'psts_stripe_waiting') ) {
			echo '<div class="updated"><p><strong>' . __('There are pending changes to your account. This message will disappear once these pending changes are completed.', 'psts') . '</strong></p></div>';
		}
	}
		
	//update plan ids from old "level_period" convention to new "domain_level_period" convention
	function update_plan_ids_v2() {
		global $psts;
		
		$this->get_stripe_plans();
		
		$levels = (array)get_site_option( 'psts_levels' );
		$periods = array(1, 3, 12);
		
		foreach ( $levels as $level_id => $level ) {
			foreach ( $periods as $period ) {
				$plan_id = $level_id . '_' . $period;
				$plan = $this->get_plan_details($plan_id);
				
				if ( $this->plan_exists($plan_id) ) {
					try {
						$this->delete_plan($plan_id);
					} catch(Exception $e) {
						//oh well
					}
				}
				
				$this->add_plan($this->get_plan_id($level_id, $period), $plan->interval, $plan->interval_count, $plan->name, ($plan->amount / 100));
			}
		}
		
		$psts->update_setting('stripe_plan_ids_updated', true);
	}
	
	function settings() {
	  global $psts;
	  ?>
		<div class="postbox">
			<h3 class="hndle" style="cursor:auto;"><span><?php _e('Stripe', 'psts') ?></span> - <span class="description"><?php _e('Stripe makes it easy to start accepting credit cards directly on your site with full PCI compliance', 'psts'); ?></span></h3>
			<div class="inside">
				<p class="description"><?php _e("Accept Visa, MasterCard, American Express, Discover, JCB, and Diners Club cards directly on your site. You don't need a merchant account or gateway. Stripe handles everything, including storing cards, subscriptions, and direct payouts to your bank account. Credit cards go directly to Stripe's secure environment, and never hit your servers so you can avoid most PCI requirements.", 'psts'); ?> <a href="https://stripe.com/" target="_blank"><?php _e('More Info &raquo;', 'psts') ?></a></p>
				<p><?php printf(__('To use Stripe you must <a href="https://manage.stripe.com/#account/webhooks" target="_blank">enter this webook url</a> (<strong>%s</strong>) in your account.', 'psts'), network_site_url('wp-admin/admin-ajax.php?action=psts_stripe_webhook', 'admin')); ?></p>
				<table class="form-table">
					<tr valign="top">
					<th scope="row"><?php _e('Stripe Mode', 'psts') ?></th>
					<td>
						<span class="description"><?php _e('When in live mode Stripe recommends you have an SSL certificate setup for your main blog/site where the checkout form will be displayed.', 'psts'); ?> <a href="https://stripe.com/help/ssl" target="_blank"><?php _e('More Info &raquo;', 'psts') ?></a></span><br/>
						<select name="psts[stripe_ssl]">
						<option value="1"<?php selected($psts->get_setting('stripe_ssl'), 1); ?>><?php _e('Force SSL (Live Site)', 'psts') ?></option>
						<option value="0"<?php selected($psts->get_setting('stripe_ssl'), 0); ?>><?php _e('No SSL (Testing)', 'psts') ?></option>
						</select>		
					</td>
					</tr>
					<tr>
					<th scope="row"><?php _e('Stripe API Credentials', 'psts') ?></th>
					<td>
						<span class="description"><?php _e('You must login to Stripe to <a target="_blank" href="https://manage.stripe.com/#account/apikeys">get your API credentials</a>. You can enter your test credentials, then live ones when ready. When switching from test to live API credentials, if you were testing on a site that will be used in live mode, you need to manually clear the associated row from the *_pro_sites_stripe_customers table for the given blogid to prevent errors on checkout or management of the site.', 'psts') ?></span>
						<p><label><?php _e('Secret key', 'psts') ?><br />
						<input value="<?php esc_attr_e($psts->get_setting("stripe_secret_key")); ?>" size="70" name="psts[stripe_secret_key]" type="text" />
						</label></p>
						<p><label><?php _e('Publishable key', 'psts') ?><br />
						<input value="<?php esc_attr_e($psts->get_setting("stripe_publishable_key")); ?>" size="70" name="psts[stripe_publishable_key]" type="text" />
						</label></p>
					</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Stripe Currency', 'psts') ?></th>
						<td>
							<span class="description"><?php _e('The currency must match the currency of your Stripe account.', 'psts'); ?></span><br />
							<select name="psts[stripe_currency]">
							<?php
							$sel_currency = $psts->get_setting("stripe_currency", 'USD');
							$currencies = array(
								"AUD" => 'AUD - Australian Dollar',
								"CAD" => 'CAD - Canadian Dollar',
								"EUR" => 'EUR - Euro',
								"GBP" => 'GBP - Pounds Sterling',
								"USD" => 'USD - U.S. Dollar',
							);
	
							foreach ($currencies as $k => $v) {
									echo '		<option value="' . $k . '"' . ($k == $sel_currency ? ' selected' : '') . '>' . esc_html ($v, true) . '</option>' . "\n";
							}
							?>
							</select>
						</td>
	        </tr>
					<tr valign="top">
					<th scope="row"><?php _e('Thank You Message', 'psts') ?></th>
					<td>
						<textarea name="psts[stripe_thankyou]" type="text" rows="4" wrap="soft" id="stripe_thankyou" style="width: 95%"/><?php echo esc_textarea($psts->get_setting('stripe_thankyou')); ?></textarea>
						<br /><?php _e('Displayed on the page after successful checkout with this gateway. This is also a good place to paste any conversion tracking scripts like from Google Analytics. - HTML allowed', 'psts') ?>
					</td>
					</tr>
				</table>    
			</div>
		</div>
    <?php 
	}
	
	function settings_process() {
		$this->update_psts_levels( 'psts_levels', get_site_option('psts_levels'), get_site_option('psts_levels') );
	}

	//filters the ssl on checkout page
	function force_ssl() {
	  global $psts;
    return (bool)$psts->get_setting('stripe_ssl', false);
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

    $customer_id = $this->get_customer_id($blog_id);
    $next_billing = __('None', 'psts');
    
    if ($customer_id) {
    	/*if ($psts->get_setting('recurring_billing', true))
				$next_billing = date_i18n(get_blog_option($blog_id, 'date_format'), $psts->get_expire($blog_id));*/
			
			// !TODO - append extra details to $payment_info
		}
		
    return $payment_info;
  }
  
  function subscription_info($blog_id) {
    global $psts;
    $customer_id = $this->get_customer_id($blog_id);
    
    if ($customer_id) {
			echo '<ul>';
			
			if (get_blog_option($blog_id, 'psts_stripe_canceled')) {
				$end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
				echo '<li><strong>'.__('The Subscription Has Been Cancelled in Stripe', 'psts').'</strong></li>';
				echo '<li>'.sprintf(__('They should continue to have access until %s.', 'psts'), $end_date).'</li>';
			}
	
			echo '<li>'.sprintf(__('Stripe Customer ID: <strong><a href="https://manage.stripe.com/#test/customers/%s" target="_blank">%s</a></strong>', 'psts'), $customer_id, $customer_id).'</li>';
			
			try {
				$existing_invoice_object = Stripe_Invoice::all(array( "customer" => $customer_id, "count" => 1) ); 
			} catch (Exception $e) {
				$error = $e->getMessage();
			}
			
			try {
				$customer_object = Stripe_Customer::retrieve($customer_id);
			} catch (Exception $e) {
				$error = $e->getMessage();
			}
			
			if (isset($customer_object->active_card)) {
				$active_card = $customer_object->active_card->type;
				$last4 = $customer_object->active_card->last4;
				$exp_year = $customer_object->active_card->exp_year;
				$exp_month = $customer_object->active_card->exp_month;
				echo '<li>'.sprintf(__('Payment Method: <strong>%1$s Card</strong> ending in <strong>%2$s</strong>. Expires <strong>%3$s</strong>', 'psts'), $active_card, $last4, $exp_month.'/'.$exp_year).'</li>';
			}
			
			if (isset($existing_invoice_object->data[0])) {
				$prev_billing = date_i18n(get_option('date_format'), $existing_invoice_object->data[0]->date);
				echo '<li>'.sprintf(__('Last Payment Date: <strong>%s</strong>', 'psts'), $prev_billing).'</li>';
				$total = $existing_invoice_object->data[0]->total / 100;
				echo '<li>'.sprintf(__('Last Payment Amount: <strong>%s</strong>', 'psts'), $psts->format_currency($psts->get_setting("stripe_currency", 'USD'), $total)).'</li>';
				echo '<li>'.sprintf(__('Last Payment Invoice ID: <strong>%s</strong>', 'psts'), $existing_invoice_object->data[0]->id).'</li>';
			}
			
			if (isset($invoice_object->next_payment_attempt)) {
				$next_billing = date_i18n(get_option('date_format'), $invoice_object->next_payment_attempt);
				echo '<li>'.sprintf(__('Next Payment Date: <strong>%s</strong>', 'psts'), $next_billing).'</li>';
			}
			
			echo '</ul>';
			echo '<small>* ('.__('This does not include the initial payment at signup, or payments before the last payment method/plan change.', 'psts').')</small>';

		} else {
      echo '<p>'.__("This site is using different gateway so their information is not accessible.", 'psts').'</p>';
		}
	}

  function subscriber_info($blog_id) {
    global $psts;
    
    $customer_id = $this->get_customer_id($blog_id);

    if ($customer_id) {
			try {
				$custom_information = Stripe_Customer::retrieve($customer_id);
	
				echo '<p><strong>' . stripslashes($custom_information->description) . '</strong><br />';
	
				if (isset($custom_information->active_card)) { //credit card
					echo stripslashes($custom_information->active_card['name']) . '<br />';
					echo stripslashes($custom_information->active_card['country']) . '</p>';
				
					echo '<p>' . stripslashes($custom_information->email) . '</p>';
				}
			} catch (Exception $e) {
				echo '<p>'.__("Stripe returned an error retrieving the customer:", 'psts') . ' ' . stripslashes($e->getMessage()) . '</p>';
			}
		} else {
      echo '<p>'.__("This site is using a different gateway so their information is not accessible.", 'psts').'</p>';
		}
	}
	
	//return timestamp of next payment if subscription active, else return false
	function next_payment($blog_id) {
  	global $psts;
		
		$next_billing = false;
    $customer_id = $this->get_customer_id($blog_id); 
    if ($customer_id) {
			
			if (get_blog_option($blog_id, 'psts_stripe_canceled')) {
				return false;
			}
	
			try {
				$invoice_object = Stripe_Invoice::upcoming(array("customer" => $customer_id));
			} catch (Exception $e) {
				$error = $e->getMessage();
			}
			
			$next_amount = $invoice_object->total / 100;
			
			if (isset($invoice_object->next_payment_attempt)) {
				$next_billing = $invoice_object->next_payment_attempt;
			}
			
		}
    return $next_billing;
  }
	
  function modify_form($blog_id) {  
		global $psts, $wpdb;
		$active_member = false;
		$canceled_member = false;
 
 		$end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
		$customer_id = $this->get_customer_id($blog_id);
		
		try {
			$existing_invoice_object = Stripe_Invoice::all(array( "customer" => $customer_id, "count" => 1) );
			$last_payment = isset($existing_invoice_object->data[0]->total) ? ($existing_invoice_object->data[0]->total / 100) : '';
			
			$cancel_status = get_blog_option($blog_id, 'psts_stripe_canceled');
		
			if ($last_payment != '') {
		
				if ($cancel_status == 0 && $cancel_status != '') {
					?>
					<h4><?php _e('Cancelations:', 'psts'); ?></h4>
					<label><input type="radio" name="stripe_mod_action" value="cancel" /> <?php _e('Cancel Subscription Only', 'psts'); ?> <small>(<?php printf(__('Their access will expire on %s', 'psts'), $end_date); ?>)</small></label><br />
					
					<label><input type="radio" name="stripe_mod_action" value="cancel_refund" /> <?php printf(__('Cancel Subscription and Refund Full (%s) Last Payment', 'psts'), $psts->format_currency(false, $last_payment)); ?> <small>(<?php printf(__('Their access will expire on %s', 'psts'), $end_date); ?>)</small></label><br />
				<?php
				}
				?>
						
				<h4><?php _e('Refunds:', 'psts'); ?></h4>
				<label><input type="radio" name="stripe_mod_action" value="refund" /> <?php printf(__('Refund Full (%s) Last Payment', 'psts'), $psts->format_currency(false, $last_payment)); ?> <small>(<?php _e('Their subscription and access will continue', 'psts'); ?>)</small></label><br />
				<label><input type="radio" name="stripe_mod_action" value="partial_refund" /> <?php printf(__('Refund a Partial %s Amount of Last Payment', 'psts'), $psts->format_currency().'<input type="text" name="refund_amount" size="4" value="'.$last_payment.'" />'); ?> <small>(<?php _e('Their subscription and access will continue', 'psts'); ?>)</small></label><br />
				<?php	  
			} 
	
		}	catch (Exception $e) {
			echo $e->getMessage();
		}
  }	
	
	function process_modify($blog_id) {
		global $psts, $current_user;
	  $success_msg = $error_msg = '';
		
	  if (isset($_POST['stripe_mod_action']) ) {
			
    	$customer_id = $this->get_customer_id($blog_id);
			$exitsing_invoice_object = Stripe_Invoice::all(array( "customer" => $customer_id, "count" => 1) );
			$last_payment = $exitsing_invoice_object->data[0]->total / 100;		
			$refund_value = $_POST['refund_amount'];
			$refund_amount = $refund_value * 100;
			$refund_amount = (int) $refund_amount;
			$refund = $last_payment;
			
			switch ($_POST['stripe_mod_action']) {
				case 'cancel':
					$end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
				
					try {
						$cu = Stripe_Customer::retrieve($customer_id); 
						$cu->cancelSubscription();
						//record stat
						$psts->record_stat($blog_id, 'cancel');
						$psts->log_action( $blog_id, sprintf(__('Subscription successfully cancelled by %1$s. They should continue to have access until %2$s', 'psts'), $current_user->display_name, $end_date) );
						$success_msg = sprintf(__('Subscription successfully cancelled. They should continue to have access until %s.', 'psts'), $end_date);
						update_blog_option($blog_id, 'psts_stripe_canceled', 1);
					} catch (Exception $e) {
					 $error_msg = $e->getMessage();
					 $psts->log_action( $blog_id, sprintf(__('Attempt to Cancel Subscription by %1$s failed with an error: %2$s', 'psts'), $current_user->display_name, $error_msg));
					}	
					break;
				
				case 'cancel_refund':
					$end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
				
					$cancellation_success = false;
					try {
						$cu = Stripe_Customer::retrieve($customer_id); 
						$cu->cancelSubscription();
						$cancellation_success = true;
							//record stat
					}
					catch (Exception $e) {
					 $error_msg = $e->getMessage();	
					}
					
					if ($cancellation_success == false) {
						$psts->log_action( $blog_id, sprintf(__('Attempt to Cancel Subscription and Refund Prorated (%1$s) Last Payment by %2$s failed with an error: %3$s', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name, $error_msg));
						$error_msg = sprintf(__('Whoops, Stripe returned an error when attempting to cancel the subscription. Nothing was completed: %s', 'psts'), $error_msg);			
						break;	 
					}
		
					$refund_success = false;
					if ($cancellation_success == true) {
						try {
							$charge_object = Stripe_Charge::all(array("count" => 1, "customer" => $customer_id));
							$charge_id = $charge_object->data[0]->id;
							$ch = Stripe_Charge::retrieve($charge_id); 
							$ch->refund();
							$refund_success = true;
						}
						catch (Exception $e) {
							 $error_msg = $e->getMessage();
						}
					}		
					
					if ($refund_success == true) {
						$psts->log_action( $blog_id, sprintf(__('Subscription cancelled and a prorated (%1$s) refund of last payment completed by %2$s', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name) );
						$success_msg = sprintf(__('Subscription cancelled and a prorated (%s) refund of last payment were successfully completed.', 'psts'), $psts->format_currency(false, $refund));
						update_blog_option($blog_id, 'psts_stripe_canceled', 1);
					} else {
						$psts->log_action( $blog_id, sprintf(__('Subscription cancelled, but prorated (%1$s) refund of last payment by %2$s returned an error: %3$s', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name, $error_msg ) );
						$error_msg = sprintf(__('Subscription cancelled, but prorated (%1$s) refund of last payment returned an error: %2$s', 'psts'), $psts->format_currency(false, $refund), $error_msg);
						update_blog_option($blog_id, 'psts_stripe_canceled', 1);
					}
					break;
				
				case 'refund':
					try {
						$charge_object = Stripe_Charge::all(array("count" => 1, "customer" => $customer_id));
						$charge_id = $charge_object->data[0]->id;
						$ch = Stripe_Charge::retrieve($charge_id); 
						$ch->refund();
						$psts->log_action( $blog_id, sprintf(__('A full (%1$s) refund of last payment completed by %2$s The subscription was not cancelled.', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name) );
						$success_msg = sprintf(__('A full (%s) refund of last payment was successfully completed. The subscription was not cancelled.', 'psts'), $psts->format_currency(false, $refund));
						$psts->record_refund_transaction($blog_id, $charge_id, $refund);
					}	
					catch (Exception $e) {
						$error_msg = $e->getMessage();
						$psts->log_action( $blog_id, sprintf(__('Attempt to issue a full (%1$s) refund of last payment by %2$s returned an error: %3$s', 'psts'), $psts->format_currency(false, $refund), $current_user->display_name, $error_msg));
						$error_msg = sprintf(__('Attempt to issue a full (%1$s) refund of last payment returned an error: %2$s', 'psts'), $psts->format_currency(false, $refund), $error_msg);
					}
					break;
				 
				case 'partial_refund':
					try {
						$charge_object = Stripe_Charge::all(array("count" => 1, "customer" => $customer_id));
						$charge_id = $charge_object->data[0]->id;
						$ch = Stripe_Charge::retrieve($charge_id); 
						$ch->refund(array("amount" => $refund_amount));
						$psts->log_action( $blog_id, sprintf(__('A partial (%1$s) refund of last payment completed by %2$s The subscription was not cancelled.', 'psts'), $psts->format_currency(false, $refund_value), $current_user->display_name) );
						$success_msg = sprintf(__('A partial (%s) refund of last payment was successfully completed. The subscription was not cancelled.', 'psts'), $psts->format_currency(false, $refund_value));
						$psts->record_refund_transaction($blog_id, $charge_id, $refund);
					}	
					catch (Exception $e) {
						$error_msg = $e->getMessage();
						$psts->log_action( $blog_id, sprintf(__('Attempt to issue a partial (%1$s) refund of last payment by %2$s returned an error: %3$s', 'psts'), $psts->format_currency(false, $refund_value), $current_user->display_name, $error_msg));
						$error_msg = sprintf(__('Attempt to issue a partial (%1$s) refund of last payment returned an error: %2$s', 'psts'), $psts->format_currency(false, $refund_value), $error_msg);
					}
					break;			 
			 
			}
		
		}
		
	 //display resulting message
		if ($success_msg)
			echo '<div class="updated fade"><p>' . $success_msg . '</p></div>';
		else if ($error_msg)
			echo '<div class="error fade"><p>' . $error_msg . '</p></div>';
	}
	
	//handle transferring pro status from one blog to another
	function process_transfer($from_id, $to_id) {
		global $wpdb;
		$wpdb->query($wpdb->prepare("UPDATE {$wpdb->base_prefix}pro_sites_stripe_customers SET blog_id = %d WHERE blog_id = %d", $to_id, $from_id) );
	}
	
	//get all plans from Stripe
	function get_stripe_plans( $count = 100, $offset = 0 ) {
		if ( wp_cache_get('stripe_plans_cached', 'psts')) return $this->stripe_plans;
		
		try {
			$plans = Stripe_Plan::all(array('count' => $count, 'offset' => $offset));
		} catch( Exception $e ) {
			return;
		}
		
		$data = $plans->data;
		
		if ( count($data) > 0 ) {
			$newoffset = $offset + $count + 1;
			$this->stripe_plans = array_merge($data, $this->stripe_plans);
			
			if ( $newoffset < $plans->count ) {
				$this->get_stripe_plans($count, $newoffset);
				return;
			}
		}
		
		wp_cache_set('stripe_plans_cached', true, 'psts');
	}
	
	//check if plan exists on stripe
	function plan_exists( $plan_id ) {
		$this->get_stripe_plans();
		
		foreach ( $this->stripe_plans as $plan ) {
			if ( $plan_id == $plan->id ) {
				return true;
			}
		}
		
		return false;
	}
	
	//get a plan details from Stripe
	function get_plan_details( $plan_id ) {
		$this->get_stripe_plans();
		
		foreach ( $this->stripe_plans as $plan ) {
			if ( $plan_id == $plan->id ) {
				return $plan;
			}
		}
		
		return false;
	}
	
	//gets a unique identifier for plans
	function get_plan_uid() {
		return str_replace(array('http://', 'https://', '/', '.'), array('', '', '', '_'), network_home_url());
	}
	
	//get a plan id based upon a given level and period
	function get_plan_id($level, $period) {
		return $this->get_plan_uid() . '_' . $level . '_' . $period;
		//return $level . '_' . $period;
	}
	
	function update_psts_levels($option, $new_levels, $old_levels) {
	  global $psts;
	  
		//deleting
		if (count($old_levels) > count($new_levels)) {
			$level_id = count($old_levels);
			$periods = array(1, 3, 12);
			
			foreach ( $periods as $period ) {
				$stripe_plan_id = $this->get_plan_id($level_id, $period);
				$this->delete_plan($stripe_plan_id);
			}
			
			return; // no further processing required
		}
		
		//update levels
		$periods = (array)$psts->get_setting('enabled_periods');
		foreach ($new_levels as $level_id => $level) {
			$level_name = $level['name'];
			$plans = array(
				1 => array(
					'int' => 'month',
					'int_count' => 1,
					'desc' => 'Monthly',
					'price' => $level['price_1'],
				),
				3 => array(
					'int' => 'month',
					'int_count' => 3,
					'desc' => 'Monthly',
					'price' => $level['price_3'],
				),
				12 => array(
					'int' => 'year',
					'int_count' => 1,
					'desc' => 'Yearly',
					'price' => $level['price_12'],
				),
			);
			
			foreach ( $plans as $period => $plan ) {
				$stripe_plan_id = $this->get_plan_id($level_id, $period);
				$plan_name = $level_name . ': ' . $plan['desc'];
				
				if ( $this->plan_exists($stripe_plan_id) ) {
					$plan_existing = $this->get_plan_details($stripe_plan_id);

					if ( $plan_existing->amount == ($plan['price'] * 100) && $plan_existing->name == $plan_name ) continue; //price and name are the same, nothing to update
					if ( $plan_existing->amount == ($plan['price'] * 100) ) {
						//plan price is staying the same, but name is changing - we can use update function
						$this->update_plan($stripe_plan_id, $plan_name);
						continue;
					}
					
					//plan can't be updated - delete the plan and re-add
					$this->delete_plan($stripe_plan_id);
				}

				$this->add_plan($stripe_plan_id, $plan['int'], $plan['int_count'], $plan_name, $plan['price']);
			}
		}
	}
	
	//retrieve a plan from Stripe
	function retrieve_plan( $plan_id ) {
		$this->get_stripe_plans();
		
		foreach ( $this->stripe_plans as $plan ) {
			if ( $plan['id'] == $plan_id ) {
				return $plan;
			}
		}
	}
	
	//update a plan (only name can be updated)
	function update_plan( $plan_id, $plan_name ) {
		try {
			$plan = $this->retrieve_plan($plan_id);
			$plan->name = $plan_name;
			$plan->save();
		} catch (Exception $e) {
			//oh well
		}
	}
	
	//delete a plan from Stripe
	function delete_plan($stripe_plan_id, $retry = true)	{
		try {
			$plan = $this->retrieve_plan($stripe_plan_id); 
			$plan->delete();
		} catch (Exception $e) {
			//oh well
		}
	}
	
	function add_plan($stripe_plan_id, $int, $int_count, $name, $level_price)	{
		global $psts;
		try {
			Stripe_Plan::create(array( 
						 "amount" => round($level_price * 100),
						 "interval" => $int,
						 "interval_count" => $int_count,
						 "name" => "$name", 
						 "currency" => $psts->get_setting("stripe_currency", 'USD'), 
						 "id" => "$stripe_plan_id"));
		} catch (Exception $e) {
			//oh well
		}
	}
	
  function process_checkout($blog_id) {
		global $current_site, $current_user, $psts, $wpdb;

    if (isset($_POST['cc_checkout']) && empty($_POST['coupon_code'])) {
			
      //check for level
	    if (empty($_POST['level']) || empty($_POST['period'])) {
				$psts->errors->add('general', __('Please choose your desired level and payment plan.', 'psts'));
				return;
			} else if (!isset($_POST['stripeToken']) && empty($_POST['wp_password'])) {
				$psts->errors->add('general', __('The Stripe Token was not generated correctly. Please try again.', 'psts'));
				return;
			}
			
			$error = '';
			$success = '';
			$plan = $this->get_plan_id($_POST['level'], $_POST['period']);
			$customer_id = $this->get_customer_id($blog_id);		
			$email = isset($current_user->user_email) ? $current_user->user_email : get_blog_option($blog_id, 'admin_email');
			
			if ( !$this->plan_exists($plan) ) {
				$psts->errors->add('general', sprintf(__('Stripe plan %1$s does not exist.', 'psts'), $plan));
				return;	
			}
			
			try {
					
				if (!$customer_id) {
					try {
						$c = Stripe_Customer::create(array(
							'email' => $email,
							'description' => sprintf(__('%s Pro Site - BlogID: %d', 'psts'), $current_site->site_name, $blog_id),
							'card' => $_POST['stripeToken'],
						));
					} catch (Exception $e) {
						$psts->errors->add('general', __('The Stripe customer could not be created. Please try again.', 'psts'));
						return;
					}
					
					$this->set_customer_id($blog_id, $c->id);
					$customer_id = $c->id;
					$new = true;
				} else {
					try {
						$c = Stripe_Customer::retrieve($customer_id);
					} catch(Exception $e) {
						$psts->errors->add('general', __('The Stripe customer could not be retrieved. Please try again.', 'psts'));
						return;							
					}
					
					$c->description = sprintf(__('%s Pro Site - BlogID: %d', 'psts'), $current_site->site_name, $blog_id);
					$c->email = $email;
					
					if ( empty($_POST['wp_password']) )
						$c->card = $_POST['stripeToken'];
					
					$c->save();
					$new = false;
					
					//validate wp password (if applicable)
					if ( !empty($_POST['wp_password']) && !wp_check_password($_POST['wp_password'], $current_user->data->user_pass, $current_user->ID) ) {
						$psts->errors->add('general', __('The password you entered is incorrect.', 'psts'));
						return;
					}
				}
								
				//prepare vars
				$currency = $psts->get_setting('stripe_currency', 'USD');
				$amount_off = false;
				$paymentAmount = $initAmount = $psts->get_level_setting($_POST['level'], 'price_' . $_POST['period']);
				$trial_days = $psts->get_setting('trial_days', 0);
				$cp_code = false;
				$setup_fee = (float) $psts->get_setting('setup_fee', 0);
				$has_coupon = (isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $_POST['level'])) ? true : false;
				$has_setup_fee = $psts->has_setup_fee($blog_id, $_POST['level']);
				$recurring = $psts->get_setting('recurring_subscriptions', 1);
				
				if ( $has_setup_fee ) {
					$initAmount = $setup_fee + $paymentAmount;
				}
					
				if ( $has_coupon || $has_setup_fee ) {
					if ( $has_coupon ) {
						//apply coupon
						$coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
						$amount_off = $paymentAmount - $coupon_value['new_total'];
						$initAmount -= $amount_off;
						
						try {
							$cpn = Stripe_Coupon::create(array(
								'amount_off' => ($amount_off * 100),
								'duration' => 'once',
								'max_redemptions' => 1
							));
						} catch(Exception $e) {
							$psts->errors->add('general', __('Temporary Stripe coupon could not be generated correctly. Please try again.', 'psts'));
							return;	
						}
						
						$cp_code = $cpn->id;
					}
					
					if ( $recurring ) {
						if ( $_POST['period'] == 1 ) {
							$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s for the first month, then %2$s each month', 'psts'), $psts->format_currency($currency, $initAmount), $psts->format_currency($currency, $paymentAmount));					
						} else {
							$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s for the first %2$s month period, then %3$s every %4$s months', 'psts'), $psts->format_currency($currency, $initAmount), $_POST['period'], $psts->format_currency($currency, $paymentAmount), $_POST['period']);
						}
					} else {
						$initAmount = $psts->calc_upgrade_cost($blog_id, $_POST['level'], $initAmount);
						if ( $_POST['period'] == 1 ) {
							$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s for 1 month', 'psts'), $psts->format_currency($currency, $initAmount));					
						} else {
							$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s for %2$s months', 'psts'), $psts->format_currency($currency, $initAmount), $_POST['period']);
						}						
					}
				} elseif ( $recurring ) {
					if ( $_POST['period'] == 1 ) {
						$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s %2$s each month', 'psts'), $psts->format_currency($currency, $paymentAmount), $currency);
					} else {
						$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s %2$s every %3$s months', 'psts'), $psts->format_currency($currency, $paymentAmount), $currency, $_POST['period']);
					}
				} else {
					$paymentAmount = $psts->calc_upgrade_cost($blog_id, $_POST['level'], $_POST['period'], $paymentAmount);
					if ( $_POST['period'] == 1 ) {
						$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s for 1 month', 'psts'), $psts->format_currency($currency, $paymentAmount));					
					} else {
						$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s for %2$s months', 'psts'), $psts->format_currency($currency, $paymentAmount), $_POST['period']);
					}						
				}

				$desc = apply_filters('psts_stripe_checkout_desc', $desc, $_POST['period'], $_POST['level'], $paymentAmount, $initAmount, $blog_id);
				
				if ( $recurring ) {	//this is a recurring subscription
					//assign the new plan to the customer
					$args = array(
						"plan" => $plan, 
						"prorate" => true, 
					);
					
					//add coupon if set
					if ($cp_code)
						$args["coupon"] = $cp_code;
						
					//add trial days for new signups with expiration in the future (manually extended or from another gateway)
					if ( is_pro_site($blog_id) && !is_pro_trial($blog_id) && ($new || $psts->is_blog_canceled($blog_id)) )
						$args["trial_end"] = $psts->get_expire($blog_id);
					//add trial days for new customers that are upgrading (if applicable)
					elseif ( ($trial_days > 0 && !is_pro_site($blog_id) && !$psts->is_blog_canceled($blog_id)) || ($trial_days > 0 && is_pro_site($blog_id) && is_pro_trial($blog_id) && !$psts->is_blog_canceled($blog_id)) )
						$args['trial_end'] = strtotime('+ ' . $trial_days . ' days');
					//customer is upgrading from an existing trial - carry over expiration date
					elseif ( isset($c->subscription->status) && $c->subscription->status == 'trialing' )
						$args['trial_end'] = $psts->get_expire($blog_id);
					
					/***** DETERMINE TRIAL END (IF APPLICABLE) *****/
					
					if ( $psts->is_trial_allowed($blog_id) ) {
						if ( !$psts->is_existing($blog_id) ) {
							//customer is new - add trial days
							$args['trial_end'] = strtotime('+ ' . $trial_days . ' days');					
						} elseif ( is_pro_trial($blog_id) && $psts->get_expire($blog_id) > time() ) {
							//customer's trial is still valid - carry over existing expiration date
							$args['trial_end'] = $psts->get_expire($blog_id);
						}
                                        }
	
					if ( $has_setup_fee ) {  //add the setup fee onto the next invoice
						try {
							Stripe_InvoiceItem::create(array(
								'customer' => $customer_id,
								'amount' => ($setup_fee * 100),
								'currency' => $currency,
								'description' => __('One-time setup fee', 'psts')
							));
						} catch (Exception $e) {
							wp_mail(
								get_blog_option($blog_id, 'admin_email'),
								__('Error charging setup fee. Attention required!', 'psts'),
								sprintf(__('An error occurred while charging a setup fee of %1$s to Stripe customer %2$s. You will need to manually process this amount.', 'psts'), $this->format_currency($currency, $setup_fee), $customer_id)
							);
						}
					}
					
					try {
						$c->updateSubscription($args);
					} catch (Exception $e) {
						$body = $e->getJsonBody();
						$error = $body['error'];
						$psts->errors->add('general', $error['message']);
						return;
					}
				} else {  //do not create the subscription, just charge credit card for 1 term
					try {
						$initAmount = $psts->calc_upgrade_cost($blog_id, $_POST['level'], $_POST['period'], $initAmount);

						Stripe_Charge::create(array(
							'customer' => $customer_id,
							'amount' => ($initAmount * 100),
							'currency' => $currency,
							'description' => $desc,
						));
						
						if ( $current_plan = $this->get_current_plan($blog_id) )
							list($current_plan_level, $current_plan_period) = explode('_', $current_plan);
						
						$old_expire = $psts->get_expire($blog_id);
						$new_expire = ($old_expire && $old_expire > time()) ? $old_expire : false;
						$psts->extend($blog_id, $_POST['period'], 'Stripe', $_POST['level'], $psts->get_level_setting($_SESSION['LEVEL'], 'price_' . $_SESSION['PERIOD']), $new_expire, false);
						$psts->email_notification($blog_id, 'receipt');
						
						if ( isset($current_plan_level) ) {
							if ( $current_plan_level > $_POST['level'] ) {
								$psts->record_stat($blog_id, 'upgrade');
							} else {
								$psts->record_stat($blog_id, 'modify');
							}
						} else {
							$psts->record_stat($blog_id, 'signup');
						}
					} catch (Stripe_CardError $e) {
						$body = $e->getJsonBody();
						$err = $body['error'];
						$psts->errors->add('general', $e['message']);
					} catch (Exception $e) {
						$psts->errors->add('general', __('An unknown error occurred while processing your payment. Please try again.', 'psts'));
					}
				} 
				
				//delete the temporary coupon code
				if ($cp_code) {
					try {
						$cpn = Stripe_Coupon::retrieve($cp_code);
						$cpn->delete();
					} catch (Exception $e) {
						wp_mail(
							get_blog_option($blog_id, 'admin_email'),
							__('Error deleting temporary Stripe coupon code. Attention required!.', 'psts'),
							sprintf(__('An error occurred when attempting to delete temporary Stripe coupon code %1$s. You will need to manually delete this coupon via your Stripe account.', 'psts'), $cp_code)
						);
					}
					
					$psts->use_coupon($_SESSION['COUPON_CODE'], $blog_id);
				}
				
				if ( $new || $psts->is_blog_canceled($blog_id) ) {
					// Added for affiliate system link
					$psts->log_action($blog_id, sprintf(__('User creating new subscription via CC: Subscription created (%1$s) - Customer ID: %2$s', 'psts'), $desc, $customer_id));					
					do_action('supporter_payment_processed', $blog_id, $paymentAmount, $_POST['period'], $_POST['level']);
				} else {
					$psts->log_action($blog_id, sprintf(__('User modifying subscription via CC: Plan changed to (%1$s) - %2$s', 'psts'), $desc, $customer_id));					
				}
				
				//display GA ecommerce in footer
				$psts->create_ga_ecommerce($blog_id, $_POST['period'], $initAmount, $_POST['level']);

				update_blog_option($blog_id, 'psts_stripe_canceled', 0);
				
				/* 	some times there is a lag receiving webhooks from Stripe. we want to be able to check for that
						and display an appropriate message to the customer (e.g. there are changes pending to your account) */			
				update_blog_option($blog_id, 'psts_stripe_waiting', 1);
				
				if (empty($this->complete_message))
					$this->complete_message = __('Your subscription was successful! You should be receiving an email receipt shortly.', 'psts');
					
			} catch (Exception $e) {
				$psts->errors->add('general', $e->getMessage());
			}					
		}
	}

	//js to be printed only on checkout page
	function checkout_js() {
	  ?><script type="text/javascript"> jQuery(document).ready( function() { jQuery("a#stripe_cancel").click( function() { if ( confirm( "<?php echo __('Please note that if you cancel your subscription you will not be immune to future price increases. The price of un-canceled subscriptions will never go up!\n\nAre you sure you really want to cancel your subscription?\nThis action cannot be undone!', 'psts'); ?>" ) ) return true; else return false; }); });</script><?php
	}

	function checkout_screen( $content, $blog_id = '', $domain = 'false' ) {
		global $psts, $wpdb, $current_site, $current_user;
		if ( ! $blog_id && ! $domain ) {
			return $content;
		}

		//cancel subscription
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'cancel' && wp_verify_nonce( $_GET['_wpnonce'], 'psts-cancel' ) ) {
			$error = '';

			try {
				$customer_id = $this->get_customer_id( $blog_id );
				$cu          = Stripe_Customer::retrieve( $customer_id );
				$cu->cancelSubscription();
			} catch ( Exception $e ) {
				$error = $e->getMessage();
			}

			if ( $error != '' ) {
				$content .= '<div id="message" class="error fade"><p>' . __( 'There was a problem canceling your subscription, please contact us for help: ', 'psts' ) . $error . '</p></div>';
			} else {
				//record stat
				$psts->record_stat( $blog_id, 'cancel' );
				$psts->email_notification( $blog_id, 'canceled' );
				update_blog_option( $blog_id, 'psts_stripe_canceled', 1 );

				$end_date = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
				$psts->log_action( $blog_id, sprintf( __( 'Subscription successfully cancelled by %1$s. They should continue to have access until %2$s', 'psts' ), $current_user->display_name, $end_date ) );
				$content .= '<div id="message" class="updated fade"><p>' . sprintf( __( 'Your %1$s subscription has been canceled. You should continue to have access until %2$s.', 'psts' ), $current_site->site_name . ' ' . $psts->get_setting( 'rebrand' ), $end_date ) . '</p></div>';
			}
		}

		$cancel_status  = get_blog_option( $blog_id, 'psts_stripe_canceled' );
		$cancel_content = '';

		$img_base  = $psts->plugin_url . 'images/';
		$pp_active = false;

		//hide top part of content if its a pro blog
		if ( $domain || is_pro_site( $blog_id ) || $psts->errors->get_error_message( 'coupon' ) ) {
			$content = '';
		}

		if ( $errmsg = $psts->errors->get_error_message( 'general' ) ) {
			$content = '<div id="psts-general-error" class="psts-error">' . $errmsg . '</div>'; //hide top part of content if theres an error
		}

		//if transaction was successful display a complete message and skip the rest
		if ( $this->complete_message ) {
			$content = '<div id="psts-complete-msg">' . $this->complete_message . '</div>';
			$content .= '<p>' . $psts->get_setting( 'stripe_thankyou' ) . '</p>';
			$content .= '<p><a href="' . get_admin_url( $blog_id, '', 'http' ) . '">' . __( 'Visit your newly upgraded site &raquo;', 'psts' ) . '</a></p>';

			return $content;
		}

		if ( 1 == get_blog_option( $blog_id, 'psts_stripe_waiting' ) ) {
			$content .= '<div id="psts-general-error" class="psts-warning">' . __( 'There are pending changes to your account. This message will disappear once these pending changes are completed.', 'psts' ) . '</div>';
		}


		if ( $customer_id = $this->get_customer_id( $blog_id ) ) {

			try {
				$customer_object = Stripe_Customer::retrieve( $customer_id );
			} catch ( Exception $e ) {
				$error = $e->getMessage();
			}

			$content .= '<div id="psts_existing_info">';
			$end_date     = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
			$level        = $psts->get_level_setting( $psts->get_level( $blog_id ), 'name' );
			$is_recurring = false;

			try {
				$invoice_object = Stripe_Invoice::upcoming( array( "customer" => $customer_id ) );
			} catch ( Exception $e ) {
				$is_recurring = $psts->is_blog_recurring( $blog_id );
				if ( $is_recurring )
					$cancel_status = 1;
			}

			try {
				$existing_invoice_object = Stripe_Invoice::all( array( "customer" => $customer_id, "count" => 1 ) );
			} catch ( Exception $e ) {
				$error = $e->getMessage();
			}

			if ( $cancel_status == 1 ) {
				$content .= '<h3>' . __( 'Your subscription has been canceled', 'psts' ) . '</h3>';
				$content .= '<p>' . sprintf( __( 'This site should continue to have %1$s features until %2$s.', 'psts' ), $level, $end_date ) . '</p>';
			}

			if ( $cancel_status == 0 ) {
				$content .= '<ul>';
				if ( is_pro_site( $blog_id ) ) {
					$content .= '<li>' . __( 'Level:', 'psts' ) . ' <strong>' . $level . '</strong></li>';
				}

				if ( isset( $customer_object->cards->data[0] ) && isset( $customer_object->default_card ) ) {
					foreach ( $customer_object->cards->data as $tmpcard ) {
						if ( $tmpcard->id == $customer_object->default_card ) {
							$card = $tmpcard;
							break;
						}
					}
				} elseif ( isset( $customer_object->active_card ) ) { //for API pre 2013-07-25
					$card = $customer_object->active_card;
				}

				$content .= '<li>' . sprintf( __( 'Payment Method: <strong>%1$s Card</strong> ending in <strong>%2$s</strong>. Expires <strong>%3$s/%4$s</strong>', 'psts' ), $card->type, $card->last4, $card->exp_month, $card->exp_year ) . '</li>';

				if ( isset( $existing_invoice_object->data[0] ) && $customer_object->subscription->status != 'trialing' )
					$content .= '<li>' . __( 'Last Payment Date:', 'psts' ) . ' <strong>' . date_i18n( get_option( 'date_format' ), $existing_invoice_object->data[0]->date ) . '</strong></li>';

				if ( isset( $invoice_object->next_payment_attempt ) )
					$content .= '<li>' . __( 'Next Payment Date:', 'psts' ) . ' <strong>' . date_i18n( get_option( 'date_format' ), $invoice_object->next_payment_attempt ) . '</strong></li>';

				if ( ! $is_recurring )
					$content .= '<li>' . __( 'Subscription Expires On:', 'psts' ) . ' <strong>' . $end_date . '</strong></li>';

				$content .= "</ul>";

				$pp_active = false;

				if ( $is_recurring ) {
					$cancel_content .= '<h3>' . __( 'Cancel Your Subscription', 'psts' ) . '</h3>';

					if ( is_pro_site( $blog_id ) ) {
						$cancel_content .= '<p>' . sprintf( __( 'If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts' ), $level, $end_date ) . '</p>';
						$cancel_content .= '<p><a id="stripe_cancel" href="' . wp_nonce_url( $psts->checkout_url( $blog_id ) . '&action=cancel', 'psts-cancel' ) . '" title="' . __( 'Cancel Your Subscription', 'psts' ) . '"><img src="' . $img_base . 'cancel_subscribe_gen.gif" /></a></p>';
						$pp_active = true;
					}
				}

				//print receipt send form
				$content .= $psts->receipt_form( $blog_id );

				if ( ! defined( 'PSTS_CANCEL_LAST' ) || ( defined( 'PSTS_CANCEL_LAST' ) && ! PSTS_CANCEL_LAST ) )
					$content .= $cancel_content;

				$content .= "<br>";
				$content .= '</div>';

			}
		}
		if ( ! $cancel_status && is_pro_site( $blog_id ) && ! is_pro_trial( $blog_id ) ) {
			$content .= '<h2>' . __( 'Change Your Plan or Payment Details', 'psts' ) . '</h2>
        <p>' . __( 'You can modify or upgrade your plan or just change your payment method or information below. Your new subscription will automatically go into effect when your next payment is due.', 'psts' ) . '</p>';
		} else if ( ! is_pro_site( $blog_id ) || is_pro_trial( $blog_id ) || $domain ) {
			$content .= '<p>' . __( 'Please choose your desired plan then click the checkout button below.', 'psts' ) . '</p>';
		}

		$content .= '<form action="' . $psts->checkout_url( $blog_id ) . '" method="post" autocomplete="off"  id="payment-form">';

		//print the checkout grid
		$content .= $psts->checkout_grid( $blog_id );

		//if existing customer, offer ability to checkout using saved credit card info
		if ( isset( $customer_object ) ) {
			$card_object = $this->get_default_card( $customer_object );
			$content .= '
	    		<div id="psts-stripe-checkout-existing">
						<h2>' . __( 'Checkout Using Existing Credit Card', 'psts' ) . '</h2>
						<table id="psts-cc-table-existing">
							<tr>
								<td class="pypl_label" align="right">' . __( 'Last 4 Digits:', 'psts' ) . '</td>
								<td>' . $card_object->last4 . '</td>
							</tr>
							<tr>
								<td class="pypl_label" align="right">' . __( 'WordPress Password:', 'psts' ) . '</td>
								<td><input id="wp_password" name="wp_password" size="15" type="password" class="cctext" title="' . __( 'Enter the WordPress password that you login with.', 'psts' ) . '" /></td>
							</tr>
						</table>
					</div>';
		}

		$content .= '<div id="psts-stripe-checkout">
			<h2>' . __( 'Checkout With a Credit Card:', 'psts' ) . '</h2>';

		$content .= '<div id="psts-processcard-error"></div>';

		$content .= '
				<table id="psts-cc-table">
				<tbody>
				<!-- Credit Card Type -->
				<tr>
				<td class="pypl_label" align="right">' . __( 'Cardholder Name:', 'psts' ) . '&nbsp;</td><td>';
		if ( $errmsg = $psts->errors->get_error_message( 'name' ) )
			$content .= '<div class="psts-error">' . $errmsg . '</div>';
		$content .= '<input id="cc_name" type="text" class="cctext card-first-name" value="" size="25" /> </td>
				</tr>
					<tr>
					<td class="pypl_label" align="right">' . __( 'Card Number:', 'psts' ) . '&nbsp;</td>
					<td>';
		if ( $errmsg = $psts->errors->get_error_message( 'number' ) )
			$content .= '<div class="psts-error">' . $errmsg . '</div>';
		$content .= '<input id="cc_number" type="text" class="cctext card-number" value="" size="23" /><br /><img src="' . $img_base . 'stripe-cards.png" />
					</td>
					</tr>

					<tr>
					<td class="pypl_label" align="right">' . __( 'Expiration Date:', 'psts' ) . '&nbsp;</td>
					<td valign="middle">';
		if ( $errmsg = $psts->errors->get_error_message( 'expiration' ) )
			$content .= '<div class="psts-error">' . $errmsg . '</div>';
		$content .= '<select id="cc_month" class="card-expiry-month">' . $this->month_dropdown() . '</select>&nbsp;/&nbsp;<select id="cc_year" class="card-expiry-year">' . $this->year_dropdown() . '</select>
					</td>
					</tr>

					<!-- Card Security Code -->
					<tr>
						<td class="pypl_label" align="right"><nobr>' . __( 'Card Security Code:', 'psts' ) . '</nobr>&nbsp;</td>
						<td valign="middle">';
		if ( $errmsg = $psts->errors->get_error_message( 'cvv2' ) )
			$content .= '<div class="psts-error">' . $errmsg . '</div>';
		$content .= '<label><input id="cc_cvv2" size="5" maxlength="4" type="password" class="cctext card-cvc" title="' . __( 'Please enter a valid card security code. This is the 3 digits on the signature panel, or 4 digits on the front of Amex cards.', 'psts' ) . '" />
						<img src="' . $img_base . 'buy-cvv.gif" height="27" width="42" title="' . __( 'Please enter a valid card security code. This is the 3 digits on the signature panel, or 4 digits on the front of Amex cards.', 'psts' ) . '" /></label>
						</td>
					</tr>
				

				</table>
				</tbody></table>
				<input type="hidden" name="cc_checkout" value="1" />';

		$content .= '
			<p>
				<input type="submit" id="cc_checkout" name="stripe_checkout_button" value="' . __( 'Subscribe', 'psts' ) . ' &raquo;" class="submit-button"/>
				<span id="stripe_processing" style="display: none;float: right;"><img src="' . $img_base . 'loading.gif" /> ' . __( 'Processing...', 'psts' ) . '</span>
			</p>
			</div>';

		$content .= '</form>';

		if ( ! defined( 'PSTS_CANCEL_LAST' ) || ( defined( 'PSTS_CANCEL_LAST' ) && ! PSTS_CANCEL_LAST ) )
			$content .= $cancel_content;

		return $content;
	}

	//store the latest customer id in the table
	function set_customer_id($blog_id, $customer_id) {
		global $wpdb;
		
		$exists = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->base_prefix}pro_sites_stripe_customers WHERE blog_id = %d", $blog_id) );
		if ( $exists ) {
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->base_prefix}pro_sites_stripe_customers SET customer_id = %s WHERE blog_id = %d", $customer_id, $blog_id) );
		} else {
			$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->base_prefix}pro_sites_stripe_customers(blog_id, customer_id) VALUES (%d, %s)", $blog_id, $customer_id) );
		}
	}
	
	function get_customer_id($blog_id) {
		global $wpdb;
	  return $wpdb->get_var( $wpdb->prepare("SELECT customer_id FROM {$wpdb->base_prefix}pro_sites_stripe_customers WHERE blog_id = %d", $blog_id) );
	}

	function get_blog_id($customer_id) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare("SELECT blog_id FROM {$wpdb->base_prefix}pro_sites_stripe_customers WHERE customer_id = %s", $customer_id) );
	}
	
	function get_current_plan( $blog_id ) {
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare("SELECT CONCAT_WS('_', level, term) FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id));
	}
	
	function get_default_card( $customer_object ) {
		if ( !isset($customer_object->cards) )
			return false;
			
		foreach ( $customer_object->cards->data as $card ) {
			if ( $card->id == $customer_object->default_card )
				return $card;
		}
		
		return false;
	}

	/**
	 * maybe_extend()
	 * Checks if a pro site should be extended and, if so, extends it
	 * @param int $blog_id The blog ID to extend
	 * @param int $period The new plan's period
	 * @param string $gateway The gateway
	 * @param int $level The new plan's level
	 * @param float $amount The new plan's amount
	 * @param int $expire The new plan's expiration date
	 * @param bool $is_payment Whether or not this is an invoice payment
	 * @return bool
	 */
	 
	function maybe_extend( $blog_id, $period, $gateway, $level, $amount, $expire = false, $is_payment = false, $is_recurring = true ) {
		global $psts;

		$current_plan = $this->get_current_plan($blog_id);
		$new_plan = ($level . '_' . $period);
		
		if ( $current_plan == $new_plan ) {
			if ( !$is_payment) {
				//is not a payment, nothing to do
				return false;
			}
		
			$extend_window = (int) get_blog_option($blog_id, 'psts_stripe_last_webhook_extend') + 300;	//last extended + 5 minutes
			
			if ( time() < $extend_window ) {
				/* blog has already been extended by another webhook within the past
					 5 minutes - don't extend again */
				return false;
			}
		}
		
		$psts->extend($blog_id, $period, $gateway, $level, $amount, $expire, $is_recurring);
		
		//send receipt email - this needs to be done AFTER extend is called
		$psts->email_notification($blog_id, 'receipt');
		
		update_blog_option($blog_id, 'psts_stripe_last_webhook_extend', time());
		
		return true;
	}
	
	function webhook_handler() {
		global $wpdb, $psts;

		try {
			// retrieve the request's body and parse it as JSON
			$body = @file_get_contents('php://input');
			$event_json = json_decode($body);
			
			if ( !isset($event_json->data->object->customer) ) {
				return false;
			}
			
			$customer_id = $event_json->data->object->customer;
			$blog_id = $this->get_blog_id($customer_id);
			$current_site = get_current_site();
			
			if ($blog_id) {
				$date = date_i18n(get_option('date_format'), $event_json->created);
				$event_type = $event_json->type;
				$amount = $amount_formatted = $plan_amount = 0;
				$level = $period = $plan = '';
				$is_trial = false;
				$plan_end = false;
				
				switch ( $event_type ) {
					case 'invoice.payment_succeeded' :
					case 'invoice.payment_failed' :
						foreach ( (array) $event_json->data->object->lines->data as $line ) {
							$amount += ($line->amount / 100);
							
							switch ( $line->type ) {
								case 'subscription' :
									$plan = $line->plan->id;
									$is_trial = ( empty($line->amount) ) ? true : false;
									$plan_end = $line->period->end;
									$plan_amount = $is_trial ? ($line->plan->amount / 100) : ($line->amount / 100);
								break;
							}
						}
					break;
					
					case 'customer.subscription.created' :
					case 'customer.subscription.updated' :
						$plan = $event_json->data->object->plan->id;
						$amount = $plan_amount = ($event_json->data->object->plan->amount / 100);
						$is_trial = ( !empty($event_json->data->object->trial_end) ) ? true : false;
						$plan_end = ( $is_trial ) ? $event_json->data->object->trial_end : $event_json->data->object->current_period_end;
					break;
				}
				
				$gateway = ( $is_trial ) ? 'Trial' : 'Stripe';
				$amount_formatted = $psts->format_currency(false, $amount);
				$charge_id = ( isset($event_json->data->object->charge) ) ? $event_json->data->object->charge : $event_json->data->object->id;
				
				if ( !empty($plan) ) {
					$plan_parts = explode('_', $plan);
					$period = array_pop($plan_parts);
					$level = array_pop($plan_parts);
				}
				
				/* 	reset the waiting status (this is used on the checkout screen to display a
						notice to customers that actions are pending on their account) */
				update_blog_option($blog_id, 'psts_stripe_waiting', 0);
				
				switch ( $event_type ) {
					case 'invoice.payment_succeeded' :
						$psts->log_action( $blog_id, sprintf(__('Stripe webhook "%s" received: The %s payment was successfully received. Date: "%s", Charge ID "%s"', 'psts'), $event_type, $amount_formatted, $date, $charge_id) );
						$this->maybe_extend($blog_id, $period, $gateway, $level, $plan_amount, $plan_end, true);						
					break;
					
					case 'customer.subscription.created' :
						$period_string = ( $period == 1 ) ? 'month' : 'months';
						$psts->record_stat($blog_id, 'signup');
						$psts->log_action($blog_id, sprintf(__('Stripe webhook "%1$s" received: Customer successfully subscribed to %2$s %3$s: %4$s every %5$s %6$s.', 'psts'), $event_type, $current_site->site_name, $psts->get_level_setting($level, 'name'), $psts->format_currency(false, $plan_amount), number_format_i18n($period), $period_string));						
						$this->maybe_extend($blog_id, $period, $gateway, $level, $plan_amount, $plan_end);
					break;
					
					case 'customer.subscription.updated' :
						$period_string = ( $period == 1 ) ? 'month' : 'months';					
						$current_plan = $this->get_current_plan($blog_id);
						$plan_parts = explode('_', $current_plan);
						$current_plan_period = array_pop($plan_parts);
						$current_plan_level = array_pop($plan_parts);
						
						if ( $current_plan_period != $period || $current_plan_level != $level ) {
							if ( $current_plan_level < $level ) {
								$psts->record_stat($blog_id, 'upgrade');
							} else {
								$psts->record_stat($blog_id, 'modify');
							}
						}

						$psts->log_action( $blog_id, sprintf(__('Stripe webhook "%s" received. The customer\'s subscription was successfully updated to %2$s %3$s: %4$s every %5$s %6$s.', 'psts'), $event_type, $current_site->site_name, $psts->get_level_setting($level, 'name'), $psts->format_currency(false, $plan_amount), number_format_i18n($period), $period_string));
						$this->maybe_extend($blog_id, $period, $gateway, $level, $plan_amount, $plan_end);
					break;
					
					case 'invoice.payment_failed' :
						$psts->log_action( $blog_id, sprintf(__('Stripe webhook "%s" received: The %s payment has failed. Date: "%s", Charge ID "%s"', 'psts'), $event_type, $amount_formatted, $date, $charge_id) );
						$psts->email_notification($blog_id, 'failed');
					break;
						
					case 'charge.disputed' :
						$psts->log_action( $blog_id, sprintf(__('Stripe webhook "%s" received: The customer disputed a charge with their bank (chargeback), Charge ID "%s"', 'psts'), $event_type, $charge_id) );
						$psts->withdraw($blog_id);
					break;
					
					case 'customer.subscription.deleted' :
						update_blog_option($blog_id, 'psts_stripe_canceled', 1);
						$psts->log_action( $blog_id, sprintf(__('Stripe webhook "%s" received: The subscription has been canceled', 'psts'), $event_type) );
					break;
						
					default :
						$text = sprintf(__('Stripe webhook "%s" received', 'psts'), $event_type);
						
						if ($customer_id) {
							$text .= sprintf(__(': Customer ID: %s', 'psts'), $customer_id);
						}
						
						$psts->log_action( $blog_id, $text );
					break;
				}
			}
			die(1);
		}
		catch (Exception $ex) {
			$message = $ex->getMessage();
			die($message);
		}
		
	}
	
	function cancel_blog_subscription($blog_id) {
		global $psts;
		
		$error = '';
		$customer_id = $this->get_customer_id($blog_id);
		if ($customer_id) {
			try {
				$cu = Stripe_Customer::retrieve($customer_id); 
				$cu->cancelSubscription();
			}
			catch (Exception $e) {
				$error = $e->getMessage();
			}
			
			if (empty($error)) {
				//record stat
				$psts->record_stat($blog_id, 'cancel');
				$psts->email_notification($blog_id, 'canceled');
				update_blog_option($blog_id, 'psts_stripe_canceled', 1);
				$psts->log_action( $blog_id, __('Subscription successfully canceled because the blog was deleted.', 'psts') );
			}
		}
	}
	
}

//register the gateway
psts_register_gateway( 'ProSites_Gateway_Stripe', __('Stripe (beta)', 'psts'), __('Stripe handles everything, including storing cards, subscriptions, and direct payouts to your bank account.', 'psts') );
?>
