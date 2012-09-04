<?php
/*
Pro Sites (Gateway: Stripe Payment Gateway)
*/

class ProSites_Gateway_Stripe {

	var $complete_message = false;
	
	function ProSites_Gateway_Stripe() {									
		$this->__construct();		
	}

  function __construct() {  	
		global $psts;

		//setup the Stripe API
		require $psts->plugin_dir . "gateways/gateway-stripe-files/lib/Stripe.php";		
		$stripe_secret_key = $psts->get_setting('stripe_secret_key');	    			
		Stripe::setApiKey($stripe_secret_key);	
  
	    //settings
		add_action( 'psts_gateway_settings', array(&$this, 'settings') );
		add_filter( 'psts_settings_filter', array(&$this, 'settings_process') );
		
		//checkout stuff
		add_action( 'psts_checkout_page_load', array(&$this, 'process_checkout') );
		add_filter( 'psts_checkout_output', array(&$this, 'checkout_screen'), 10, 2 );
		add_filter( 'psts_force_ssl', array(&$this, 'force_ssl') );
		
		//handle webhook notifications
		add_action( 'wp_ajax_nopriv_psts_stripe_webhook', array(&$this, 'webhook_handler') );
		
		//sync levels with Stripe
		add_action( 'network_admin_notices', array(&$this, 'levels_notice') );
		add_action( 'update_site_option_psts_levels', array(&$this, 'update_psts_levels'), 10, 3 );
		add_filter( 'psts_setting_enabled_periods', array(&$this, 'disable_period_3') );
		
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
		
		//update install script if necessary
		if ($psts->get_setting('stripe_version') != $psts->version) {
			$this->install();
		}
	}
	
	function install() {
		global $wpdb, $psts;
		
		$table1 = "CREATE TABLE `{$wpdb->base_prefix}pro_sites_stripe_customers` (
		  `blog_id` bigint(20) NOT NULL,
			`customer_id` char(20) NOT NULL,
			PRIMARY KEY ( `blog_id` ),
			UNIQUE ( `customer_id` )
		) DEFAULT CHARSET=utf8;";
		
		if ( !defined('DO_NOT_UPGRADE_GLOBAL_TABLES') ) {
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($table1);
		}
		$psts->update_setting('stripe_version', $psts->version);
		
		$this->update_psts_levels( 'psts_levels', get_site_option('psts_levels'), get_site_option('psts_levels') );
	}
	
	function settings() {
	  global $psts;
	  ?>
		<div class="postbox">
			<h3 class='hndle'><span><?php _e('Stripe', 'psts') ?></span> - <span class="description"><?php _e('Stripe makes it easy to start accepting credit cards directly on your site with full PCI compliance', 'psts'); ?></span></h3>
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
						<span class="description"><?php _e('You must login to Stripe to <a target="_blank" href="https://manage.stripe.com/#account/apikeys">get your API credentials</a>. You can enter your test credentials, then live ones when ready.', 'psts') ?></span>
						<p><label><?php _e('Secret key', 'psts') ?><br />
						<input value="<?php esc_attr_e($psts->get_setting("stripe_secret_key")); ?>" size="70" name="psts[stripe_secret_key]" type="text" />
						</label></p>
						<p><label><?php _e('Publishable key', 'psts') ?><br />
						<input value="<?php esc_attr_e($psts->get_setting("stripe_publishable_key")); ?>" size="70" name="psts[stripe_publishable_key]" type="text" />
						</label></p>
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
	
	function settings_process($settings) {
	  return $settings;
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
    if ($customer_id) {
			echo "r";
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
				$exitsing_invoice_object = Stripe_Invoice::all(array( "customer" => $customer_id, "count" => 1) ); 
			} catch (Exception $e) {
				$error = $e->getMessage();
			}
			
			try {
				$invoice_object = Stripe_Invoice::upcoming(array("customer" => $customer_id));
			} catch (Exception $e) {
				$error = $e->getMessage();
			}
						                        		
			$next_amount = $invoice_object->total / 100;
			
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
			
			if (isset($exitsing_invoice_object->data[0])) {
				$prev_billing = date_i18n(get_option('date_format'), $exitsing_invoice_object->data[0]->date);
				echo '<li>'.sprintf(__('Last Payment Date: <strong>%s</strong>', 'psts'), $prev_billing).'</li>';
				$total = $exitsing_invoice_object->data[0]->total / 100;
				echo '<li>'.sprintf(__('Last Payment Amount: <strong>%s</strong>', 'psts'), $psts->format_currency('USD', $total)).'</li>';
				echo '<li>'.sprintf(__('Last Payment Invoice ID: <strong>%s</strong>', 'psts'), $exitsing_invoice_object->data[0]->id).'</li>';
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
	
  function modify_form($blog_id) {  
		global $psts, $wpdb;
		$active_member = false;
		$canceled_member = false;
 
 		$end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
		$customer_id = $this->get_customer_id($blog_id);
		
		try {
			$existing_invoice_object = Stripe_Invoice::all(array( "customer" => $customer_id, "count" => 1) ); 		
			$last_payment = $existing_invoice_object->data[0]->total / 100;		
			
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
	
	//disables the 3 month option while stripe is active (unsupported)
	function disable_period_3($periods) {
		foreach ($periods as $key => $period) {
			if ($period == 3) unset($periods[$key]);
		}
		return $periods;
	}
	
	function levels_notice() {
		global $current_screen;
		if ( $current_screen->id != 'pro-sites_page_psts-levels-network' )
			return;
		
		echo '<div class="updated fade"><p>' . __('Note: Because of limitations with the Stripe gateway, the 3 month payment option has been disabled.', 'psts') . '</p></div>';
	}
	
	function update_psts_levels($option, $new_levels, $old_levels) {
	  global $psts;								
		
		//deleting
		if (count($old_levels) > count($new_levels)) {
			$level_id = count($old_levels);
			
			$stripe_plan_id = $level_id . "_1";
			$this->delete_plan($stripe_plan_id);
			
			$stripe_plan_id = $level_id . "_12";
			$this->delete_plan($stripe_plan_id);
		}
		
		//update levels
		$periods = (array)$psts->get_setting('enabled_periods');
		foreach ($new_levels as $level_id => $level) {		
			$level_name = $level['name'];
			$price_1 = $level['price_1'];										
			$price_12 = $level['price_12'];															
			
			$stripe_plan_id = $level_id . "_1";
			$this->delete_plan($stripe_plan_id);
			if ( in_array(1, $periods) )
				$this->add_plan($stripe_plan_id, 'month', $level_name.': Monthly', $price_1);
			
			$stripe_plan_id = $level_id . "_12";
			$this->delete_plan($stripe_plan_id);
			if ( in_array(12, $periods) )
				$this->add_plan($stripe_plan_id, 'year', $level_name.': Yearly', $price_12);
		}				
	}
	
	function delete_plan($stripe_plan_id)	{
		try {
			$plan = Stripe_Plan::retrieve($stripe_plan_id); 
			$plan->delete();							
		} catch (Exception $e) {
			//oh well
		}																		
	}
	
	function add_plan($stripe_plan_id, $int, $name, $level_price)	{
		try {
			Stripe_Plan::create(array( 
						 "amount" => round($level_price * 100),
						 "interval" => $int, 
						 "name" => "$name", 
						 "currency" => "usd", 
						 "id" => "$stripe_plan_id"));							
		} catch (Exception $e) {
			//oh well
		}																		
	}
	
  function process_checkout($blog_id) {
		global $current_site, $current_user, $psts, $wpdb;

	  //add scripts
	  add_action( 'wp_head', array(&$this, 'checkout_js') );

		$stripe_secret_key = $psts->get_setting('stripe_secret_key');	    	
		$stripe_publishable_key = $psts->get_setting('stripe_publishable_key');	    		
		
		wp_enqueue_script( 'js-stripe', 'https://js.stripe.com/v1/', array('jquery') );	
		wp_enqueue_script( 'stripe-token', $psts->plugin_url . 'gateways/gateway-stripe-files/stripe_token.js', array('js-stripe', 'jquery') );			
		wp_localize_script( 'stripe-token', 'stripe', array('publisher_key' => $stripe_publishable_key,
																												'name' =>__('Please enter the full Cardholder Name.', 'psts'),
																												'number' => __('Please enter a valid Credit Card Number.', 'psts'),
																												'expiration' => __('Please choose a valid expiration date.', 'psts'),
																												'cvv2' => __('Please enter a valid card security code. This is the 3 digits on the signature panel, or 4 digits on the front of Amex cards.', 'psts')
																												) );	
	
    if (isset($_POST['cc_checkout']) && empty($_POST['coupon_code'])) {
			
      //check for level
	    if (empty($_POST['level']) || empty($_POST['period'])) {
				$psts->errors->add('general', __('Please choose your desired level and payment plan.', 'psts'));
				return;
			} else if (!isset($_POST['stripeToken'])) {
				$psts->errors->add('general', __('The Stripe Token was not generated correctly. Please try again.', 'psts'));
				return;
			}
			
			$error = '';
			$success = '';
			 
			$plan = $_POST['level'] . '_' . $_POST['period'];
						
			$customer_id = $this->get_customer_id($blog_id);		
						 
			$email = isset($current_user->user_email) ? $current_user->user_email : get_blog_option($blog_id, 'admin_email');			
				
			try {
					
				if (!$customer_id) {
					$c = Stripe_Customer::create(array("email" => $email, "description" => sprintf(__('%s Pro Site - BlogID: %d', 'psts'), $current_site->site_name, $blog_id) ));				 
					$this->set_customer_id($blog_id, $c->id);				 
					$customer_id = $c->id;
					$new = true;
				} else {
					$c = Stripe_Customer::retrieve($customer_id);
					$c->description = sprintf(__('%s Pro Site - BlogID: %d', 'psts'), $current_site->site_name, $blog_id);
					$c->email = $email;
					$c->save();
					$new = false;
				}				
								
				//prepare vars
				$discountAmt = false;
				if ($_POST['period'] == 1) {
					$paymentAmount = $psts->get_level_setting($_POST['level'], 'price_1');
					if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $_POST['level']) ) {
						$coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
						$discountAmt = $coupon_value['new_total'];
						$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s for the first month, then %2$s each month', 'psts'), $psts->format_currency('USD', $discountAmt), $psts->format_currency('USD', $paymentAmount));
					} else {
						$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s %2$s each month', 'psts'), $psts->format_currency('USD', $paymentAmount), 'USD');
					}
				} else if ($_POST['period'] == 12) {
					$paymentAmount = $psts->get_level_setting($_POST['level'], 'price_12');
					if ( isset($_SESSION['COUPON_CODE']) && $psts->check_coupon($_SESSION['COUPON_CODE'], $blog_id, $_POST['level']) ) {
						$coupon_value = $psts->coupon_value($_SESSION['COUPON_CODE'], $paymentAmount);
						$discountAmt = $coupon_value['new_total'];
						$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s for the first 12 month period, then %2$s every 12 months', 'psts'), $psts->format_currency('USD', $discountAmt), $psts->format_currency('USD', $paymentAmount));
					} else {
						$desc = $current_site->site_name . ' ' . $psts->get_level_setting($_POST['level'], 'name') . ': ' . sprintf(__('%1$s %2$s every 12 months', 'psts'), $psts->format_currency('USD', $paymentAmount), 'USD');
					}
				}
				$desc = apply_filters('psts_pypl_checkout_desc', $desc, $_POST['period'], $_POST['level'], $paymentAmount, $discountAmt, $blog_id);
				
				//create temporary stripe coupon
				if ($discountAmt) {
					$pct = ($discountAmt <= 0) ? 100 : 100 - round( ($discountAmt/$paymentAmount) * 100 ); //get whole number percent
					$cpn = Stripe_Coupon::create( array( "percent_off" => $pct, "duration" => "once", "max_redemptions" => 1 ) );
					$cp_code = $cpn->id;
					$initAmount = $discountAmt;
				} else {
					$cp_code = false;
					$initAmount = $paymentAmount;
				}
				
				//assign the new plan to the customer
				$args = array(
					"plan" => $plan, 
					"prorate" => true, 
					"card" => $_POST['stripeToken']
				);
				//add coupon if set
				if ($cp_code)
					$args["coupon"] = $cp_code;
				//add trial days for new signups with expiration in the future (manually extended or from another gateway)
				if ( is_pro_site($blog_id) && !is_pro_trial($blog_id) && ($new || get_blog_option($blog_id, 'psts_stripe_canceled')) )
					$args["trial_end"] = $psts->get_expire($blog_id);
					
				$c->updateSubscription($args);
				
				//delete the temporary coupon code
				if ($cp_code) {
					$cpn = Stripe_Coupon::retrieve($cp_code);
					$cpn->delete();
					$psts->use_coupon($_SESSION['COUPON_CODE'], $blog_id);
				}
				
				if ( $new || get_blog_option($blog_id, 'psts_stripe_canceled') ) {										
					$psts->log_action( $blog_id, sprintf(__('User creating new subscription via CC: Subscription created (%1$s) - Customer ID: %2$s', 'psts'), $desc, $customer_id) );
					//$psts->extend($blog_id, $_POST['period'], 'Stripe', $_POST['level'], $paymentAmount); //let the IPN handle that
					$psts->record_stat($blog_id, 'signup');
					$psts->email_notification($blog_id, 'success');
					
					// Added for affiliate system link
					do_action('supporter_payment_processed', $blog_id, $paymentAmount, $_POST['period'], $_POST['level']);
				} else {
					$psts->log_action( $blog_id, sprintf(__('User modifying subscription via CC: Plan changed to (%1$s) - %2$s', 'psts'), $desc, $customer_id) );				
				}
				
				//display GA ecommerce in footer
				$psts->create_ga_ecommerce($blog_id, $_POST['period'], $initAmount, $_POST['level']);
				
				update_blog_option($blog_id, 'psts_stripe_canceled', 0);
									
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

	function checkout_screen($content, $blog_id) {
	  global $psts, $wpdb, $current_site, $current_user;  		
	  
	  if (!$blog_id)
	    return $content;
		
		//cancel subscription
		if (isset($_GET['action']) && $_GET['action']=='cancel' && wp_verify_nonce($_GET['_wpnonce'], 'psts-cancel')) {		
			$error = '';
			
			try {
				$customer_id = $this->get_customer_id($blog_id);
				$cu = Stripe_Customer::retrieve($customer_id); 
				$cu->cancelSubscription();		 		 
			}
			catch (Exception $e) {
				$error = $e->getMessage();
			}			
			
			if ($error != '') {
				$content .= '<div id="message" class="error fade"><p>'.__('There was a problem canceling your subscription, please contact us for help: ', 'psts').$error.'</p></div>';
			}	else {
				//record stat
				$psts->record_stat($blog_id, 'cancel');
				$psts->email_notification($blog_id, 'canceled');
				update_blog_option($blog_id, 'psts_stripe_canceled', 1);
				
				$end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
				$psts->log_action( $blog_id, sprintf(__('Subscription successfully cancelled by %1$s. They should continue to have access until %2$s', 'psts'), $current_user->display_name, $end_date) );
				$content .= '<div id="message" class="updated fade"><p>'.sprintf(__('Your %1$s subscription has been canceled. You should continue to have access until %2$s.', 'psts'), $current_site->site_name . ' ' . $psts->get_setting('rebrand'), $end_date).'</p></div>';
			}
		}	
		
		$cancel_status = get_blog_option($blog_id, 'psts_stripe_canceled');
		$cancel_content = '';
		
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
	    $content .= '<p>' . $psts->get_setting('stripe_thankyou') . '</p>';
	    $content .= '<p><a href="' . get_admin_url($blog_id, '', 'http') . '">' . __('Visit your newly upgraded site &raquo;', 'psts') . '</a></p>';
	    return $content;
	  }
		
    if ($customer_id = $this->get_customer_id($blog_id)) {
			
			try {
				$customer_object = Stripe_Customer::retrieve($customer_id);		
			} catch (Exception $e) {
				$error = $e->getMessage();
			}
			
			$content .= '<div id="psts_existing_info">';
			$end_date = date_i18n(get_option('date_format'), $psts->get_expire($blog_id));
			$level = $psts->get_level_setting($psts->get_level($blog_id), 'name');		
			
			try {
				$invoice_object = Stripe_Invoice::upcoming(array("customer" => $customer_id));
			} catch (Exception $e) {
				$cancel_status = 1;
			}
			
			try {
				$existing_invoice_object = Stripe_Invoice::all(array( "customer" => $customer_id, "count" => 1) ); 
			} catch (Exception $e) {
				$error = $e->getMessage();
			}								
			
			if ($cancel_status == 1) {
				$content .= '<h3>'.__('Your subscription has been canceled', 'psts').'</h3>';
				$content .= '<p>'.sprintf(__('This site should continue to have %1$s features until %2$s.', 'psts'), $psts->get_setting('rebrand'), $end_date).'</p>';
			}
			
			if ($cancel_status == 0) {			
				$content .= '<ul>';
				if ( is_pro_site($blog_id) ) {
					$content .= '<li>'.__('Level:', 'psts').' <strong>'.$level.'</strong></li>';
				}
				if (isset($customer_object->active_card))
					$content .= '<li>'.__('Payment Method: <strong>'. $customer_object->active_card->type .' Card</strong> ending in <strong>'. $customer_object->active_card->last4 .'</strong>. Expires <strong>'. $customer_object->active_card->exp_month. '/'. $customer_object->active_card->exp_year. '</strong>', 'psts').'</li>'		;
				
				if (isset($exitsing_invoice_object->data[0]))
					$content .= '<li>'.__('Last Payment Date:', 'psts').' <strong>'.date_i18n(get_option('date_format'), $existing_invoice_object->data[0]->date).'</strong></li>';
				
				if (isset($invoice_object->next_payment_attempt))
					$content .= '<li>'.__('Next Payment Date:', 'psts').' <strong>'.date_i18n(get_option('date_format'), $invoice_object->next_payment_attempt).'</strong></li>';		
				
				$content .= "</ul>";
	
				$cancel_content .= '<h3>'.__('Cancel Your Subscription', 'psts').'</h3>';
			
				$pp_active = false;
			
				if (is_pro_site($blog_id)) {
					$cancel_content .= '<p>'.sprintf(__('If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts'), $level, $end_date).'</p>';
					$cancel_content .= '<p><a id="stripe_cancel" href="' . wp_nonce_url($psts->checkout_url($blog_id) . '&action=cancel', 'psts-cancel') . '" title="'.__('Cancel Your Subscription', 'psts').'"><img src="'.$img_base.'cancel_subscribe_gen.gif" /></a></p>';
					$pp_active = true;		
				}		
				
				//print receipt send form
				$content .= $psts->receipt_form($blog_id);
				
				if ( !defined('PSTS_CANCEL_LAST') )
					$content .= $cancel_content;
				
				$content .= "<br>";
				$content .= '</div>';
				
			} 
		}	

    if (!$cancel_status && is_pro_site($blog_id) && !is_pro_trial($blog_id)) {
    	$content .= '<h2>' . __('Change Your Plan or Payment Details', 'psts') . '</h2>
        <p>' . __('You can modify or upgrade your plan or just change your payment method or information below. Your new subscription will automatically go into effect when your next payment is due.', 'psts') . '</p>';
    } else if (!is_pro_site($blog_id) || is_pro_trial($blog_id)) {
			$content .= '<p>' . __('Please choose your desired plan then click the checkout button below.', 'psts') . '</p>';
    } 		

    $content .= '<form action="'.$psts->checkout_url($blog_id).'" method="post" autocomplete="off"  id="payment-form">';
	
    //print the checkout grid
    $content .= $psts->checkout_grid($blog_id);      
			 
    $content .= '<div id="psts-stripe-checkout">
			<h2>' . __('Checkout With a Credit Card:', 'psts') . '</h2>';
		
		$content .= '<div id="psts-processcard-error"></div>';	
					
		$content .= '
				<table id="psts-cc-table">
				<tbody>
				<!-- Credit Card Type -->
				<tr>
				<td class="pypl_label" align="right">' . __('Cardholder Name:', 'psts') . '&nbsp;</td><td>';
				if ($errmsg = $psts->errors->get_error_message('name')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
				$content .= '<input id="cc_name" type="text" class="cctext card-first-name" value="" size="25" /> </td>
				</tr>
					<tr>
					<td class="pypl_label" align="right">' . __('Card Number:', 'psts') . '&nbsp;</td>
					<td>';
		if ($errmsg = $psts->errors->get_error_message('number')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
		$content .= '<input id="cc_number" type="text" class="cctext card-number" value="" size="23" /><br /><img src="'.$img_base.'stripe-cards.png" />
					</td>
					</tr>

					<tr>
					<td class="pypl_label" align="right">' . __('Expiration Date:', 'psts') . '&nbsp;</td>
					<td valign="middle">';
		if ($errmsg = $psts->errors->get_error_message('expiration')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
		$content .= '<select id="cc_month" class="card-expiry-month">'.$this->month_dropdown().'</select>&nbsp;/&nbsp;<select id="cc_year" class="card-expiry-year">'.$this->year_dropdown().'</select>
					</td>
					</tr>

					<!-- Card Security Code -->
					<tr>
						<td class="pypl_label" align="right"><nobr>' . __('Card Security Code:', 'psts') . '</nobr>&nbsp;</td>
						<td valign="middle">';
		if ($errmsg = $psts->errors->get_error_message('cvv2')) $content .= '<div class="psts-error">'.$errmsg.'</div>';
		$content .= '<label><input id="cc_cvv2" size="5" maxlength="4" type="password" class="cctext card-cvc" title="' . __('Please enter a valid card security code. This is the 3 digits on the signature panel, or 4 digits on the front of Amex cards.', 'psts') . '" />
						<img src="' . $img_base . 'buy-cvv.gif" height="27" width="42" title="' . __('Please enter a valid card security code. This is the 3 digits on the signature panel, or 4 digits on the front of Amex cards.', 'psts') . '" /></label>
						</td>
					</tr>
				

				</table>
				</tbody></table>
				<input type="hidden" name="cc_checkout" value="1" />
			<p>
				<input type="submit" id="cc_checkout" name="stripe_checkout_button" value="' . __('Subscribe', 'psts') . ' &raquo;" class="submit-button"/>
				<span id="stripe_processing" style="display: none;float: right;"><img src="' . $img_base . 'loading.gif" /> ' . __('Processing...', 'psts') . '</span>
			</p>
			</div>';

    $content .= '</form>';
		
		if ( defined('PSTS_CANCEL_LAST') )
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
	
	function webhook_handler() {
		global $wpdb, $psts;	
		try {
				
			// retrieve the request's body and parse it as JSON
			$body = @file_get_contents('php://input');
			$event_json = json_decode($body);
			$customer_id = $event_json->data->object->customer;
			
			$blog_id = $this->get_blog_id($customer_id);
			
			if ($blog_id) {
				$date = date_i18n(get_option('date_format'), $event_json->created);
				
				$amount = $event_json->data->object->lines->subscriptions[0]->amount / 100;		
				$amount = $psts->format_currency(false, $amount);
				
				if (isset($event_json->data->object->lines->subscriptions[0]->plan->id)) {
					$plan = $event_json->data->object->lines->subscriptions[0]->plan->id;	
					@list($level, $period) = explode('_', $plan);
				}
				
				$charge_id = isset($event_json->data->object->charge) ? $event_json->data->object->charge : $event_json->data->object->id;
				
				$event_type = $event_json->type;
									
				if ($event_type == 'invoice.payment_succeeded') {
					$psts->log_action( $blog_id, sprintf(__('Stripe webhook "%s" received: The %s payment was successfully received. Date: "%s", Charge ID "%s"', 'psts'), $event_type, $amount, $date, $charge_id) );	   
					$psts->extend($blog_id, $period, 'Stripe', $level, $amount);		  
				} else if ($event_type == 'invoice.payment_failed') {	   
					$psts->log_action( $blog_id, sprintf(__('Stripe webhook "%s" received: The %s payment has failed. Date: "%s", Charge ID "%s"', 'psts'), $event_type, $amount, $date, $charge_id) );	   
					$psts->email_notification($blog_id, 'failed');
				} else if ($event_type == 'charge.disputed') {
					$psts->log_action( $blog_id, sprintf(__('Stripe webhook "%s" received: The customer disputed a charge with their bank (chargeback), Charge ID "%s"', 'psts'), $event_type, $charge_id) );
					$psts->withdraw($blog_id);
				} else if ($event_type == 'customer.subscription.deleted') {
					update_blog_option($blog_id, 'psts_stripe_canceled', 1);
					$psts->log_action( $blog_id, sprintf(__('Stripe webhook "%s" received: The subscription has been canceled', 'psts'), $event_type) );
				} else {
					$text = sprintf(__('Stripe webhook "%s" received', 'psts'), $event_type);
					if ($customer_id)
						$text .= sprintf(__(': Customer ID: %s', 'psts'), $customer_id);
						
					$psts->log_action( $blog_id, $text );	   
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