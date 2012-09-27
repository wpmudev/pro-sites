<?php
/*
Plugin Name: Pro Sites (Feature: Bulk Upgrades)
*/
class ProSites_Module_BulkUpgrades {

	function ProSites_Module_BulkUpgrades() {
		$this->__construct();
	}

  function __construct() {
		add_action( 'psts_settings_page', array(&$this, 'settings') );
		add_action( 'admin_menu', array(&$this, 'plug_page'), 110 );
		
		// Edit profile
		add_action( 'profile_update', array(&$this, 'user_profile_update') );
		add_action( 'edit_user_profile', array(&$this, 'user_profile_fields') );
		add_action( 'show_user_profile', array(&$this, 'user_profile_fields') );
		
		//checkout form message
		add_filter( 'psts_checkout_grid_before_free', array(&$this, 'checkout_grid_msg'), 10, 4 );
		
		//handle IPN notifications
		add_action( 'wp_ajax_nopriv_psts_bu_ipn', array(&$this, 'ipn_handler') );
	}
	
	function plug_page() {
	  global $psts;
	  //add it under the pro blogs menu
	  if ( !is_main_site() ) {
			$page = add_submenu_page('psts-checkout', __('Bulk Upgrades', 'psts'), __('Bulk Upgrades', 'psts'), 'manage_options', 'psts-bulk-upgrades', array(&$this, 'bulk_upgrades') );
      add_action('admin_print_styles-' . $page, array(&$this, 'admin_css') );
		}
	}

	function get_credits($uid) {
		$credits = get_user_meta( $uid, "supporter_credits", true );
		if ( empty( $credits ) || $credits < 0 ) {
			$credits = 0;
		}
		return $credits;
	}

	function debit_credits($uid, $credits) {
		$old_credits = get_user_meta( $uid, "supporter_credits", true );
		if ( empty( $old_credits ) || $old_credits < 0 ) {
			$old_credits = 0;
		}
		$new_credits = $old_credits - $credits;
		if ( empty( $new_credits ) || $new_credits < 0 ) {
			$new_credits = 0;
		}
		update_user_meta($uid, 'supporter_credits', $new_credits);
	}

	function credit_credits($uid, $credits) {
		$old_credits = get_user_meta( $uid, "supporter_credits", true );
		if ( empty( $old_credits ) || $old_credits < 0 ) {
			$old_credits = 0;
		}
		$new_credits = $old_credits + $credits;
		if ( empty( $new_credits ) || $new_credits < 0 ) {
			$new_credits = 0;
		}
		update_user_meta($uid, 'supporter_credits', $new_credits);
	}

	function get_note($uid) {
		return get_user_meta( $uid, 'update_note', true );
	}

	function update_note($uid, $note = '') {
		update_user_meta($uid, 'update_note', $note);
	}

  function ipn_handler() {
    global $psts;

    if (!isset($_POST['payment_status'])) {
			// Did not find expected POST variables. Possible access attempt from a non PayPal site.
			header('Status: 404 Not Found');
			exit;
		} else if (!isset($_POST['custom'])) {
			echo 'Error: Missing POST variables. Identification is not possible.';
			exit;
		} else {

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
					do_action('supporter_payment_processed', $bid, $amount, 'bulk');
					$this->credit_credits($uid, $credits);
					$this->update_note($uid, '');
					break;

				case 'Reversed':
					// case: charge back
					$note = __('Last transaction has been reversed. Reason: Payment has been reversed (charge back)', 'psts');
					list($bid, $uid, $credits, $amount, $currency, $stamp) = explode('_', $_POST['custom']);
					//supporter_insert_update_transaction($bid, $_POST['parent_txn_id'], $_POST['payment_type'], $stamp, $amount, $currency, $_POST['payment_status']);
					$this->debit_credits($uid, $credits);
					$this->update_note($uid, $note);
					break;

				case 'Refunded':
					// case: refund
					$note = __('Last transaction has been reversed. Reason: Payment has been refunded', 'psts');
					list($bid, $uid, $credits, $amount, $currency, $stamp) = explode('_', $_POST['custom']);
					//supporter_insert_update_transaction($bid, $_POST['parent_txn_id'], $_POST['payment_type'], $stamp, $amount, $currency, $_POST['payment_status']);
					$this->debit_credits($uid, $credits);
					$this->update_note($uid, $note);
					break;

				case 'Denied':
					// case: denied
					$note = __('Last transaction has been reversed. Reason: Payment Denied', 'psts');
					list($bid, $uid, $credits, $amount, $currency, $stamp) = explode('_', $_POST['custom']);
					$paypal_ID = $_POST['parent_txn_id'];
					if ( empty( $paypal_ID ) ) {
						$paypal_ID = $_POST['txn_id'];
					}
					$this->update_note($uid, $note);
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
					$this->update_note($uid, $note);
					break;

				default:
					// case: various error cases
			}
			die('IPN Recorded');
		}
	}

  function user_profile_update() {
    global $current_user;
    $user_id =  $_REQUEST['user_id'];

    if ( is_super_admin() && isset($_POST['psts_credits']) ) {
      $this->credit_credits($user_id, intval($_POST['psts_credits']));
      $this->update_note($user_id, sprintf(__('%s bulk upgrade credits were manually added to your account by an admin.', 'psts'), intval($_POST['psts_credits'])));
    }
  }

	//------------------------------------------------------------------------//
	//---Output Functions-----------------------------------------------------//
	//------------------------------------------------------------------------//

  function user_profile_fields() {
    global $current_user;

		//only super admins can manually give credits
		if ( !is_super_admin() )
		  return;

    if (isset($_REQUEST['user_id'])) {
      $user_id = $_REQUEST['user_id'];
    } else {
      $user_id = $current_user->ID;
    }
    ?>
    <h3><?php _e('Pro Site Bulk Upgrade Credits', 'psts'); ?></h3>
    <table class="form-table">
      <tr>
        <th align="right"><?php _e('Current Credits:', 'psts'); ?></th>
				<td><?php printf(__('This user has %s upgrade credits in their account.', 'psts'), '<strong>'.number_format_i18n($this->get_credits($user_id)).'</strong>') . '</p>'; ?></td>
      </tr>
      <tr>
        <th align="right"><label for="psts_bulk_credits"><?php _e('Manually Grant Credits:', 'psts'); ?></label></th>
				<td>
        	<select name="psts_credits" id="psts_bulk_credits">
					<?php
					for ( $counter = 0; $counter <= 100; $counter++ ) {
		        echo '<option value="' . $counter . '">' . number_format_i18n($counter) . '</option>' . "\n";
					}
          ?>
          </select>
				</td>
      </tr>
    </table>
    <?php
  }

	function settings() {
    global $psts;
		$levels = (array)get_site_option( 'psts_levels' );
		?>
		<div class="postbox">
      <h3 class='hndle'><span><?php _e('Bulk Upgrades', 'psts') ?></span> - <span class="description"><?php _e('Allows you to sell Pro Site level upgrades in bulk packages.', 'psts') ?></span></h3>
      <div class="inside">
				<?php if (get_site_option("supporter_bulk_upgrades_paypal_payment_type") == 'recurring') { ?>
				<p><?php _e('Important - If you were previously using Bulk Upgrades and subscriptions with Supporter 2.x, you must copy and overwrite the <em>/pro-sites/pro-sites-files/gateways/backwards-compatibility/<strong>supporter-bulk-upgrades-paypal.php</strong></em> file to the webroot of this site to prevent problems with payments from existing subscriptions being applied.', 'psts') ?></p>
				<?php } ?>
				<table class="form-table">
	        <tr>
					<th scope="row"><?php _e('PayPal Email', 'psts') ?></th>
					<td><input value="<?php echo esc_attr($psts->get_setting('bu_email')); ?>" size="50" name="psts[bu_email]" type="text" /></td>
				  </tr>
				  <tr valign="top">
				  <th scope="row"><?php _e('PayPal Site', 'psts') ?></th>
				  <td><select name="psts[bu_site]">
				  <?php
		      $paypal_site = $psts->get_setting('bu_site');
		      $sel_locale = empty($paypal_site) ? 'US' : $paypal_site;
		      $locales = array(
						'AU'	=> 'Australia',
						'AT'	=> 'Austria',
						'BE'	=> 'Belgium',
						'CA'	=> 'Canada',
						'CN'	=> 'China',
						'FR'	=> 'France',
						'DE'	=> 'Germany',
						'HK'	=> 'Hong Kong',
						'IT'	=> 'Italy',
						'MX'	=> 'Mexico',
						'NL'	=> 'Netherlands',
						'PL'	=> 'Poland',
						'SG'	=> 'Singapore',
						'ES'	=> 'Spain',
						'SE'	=> 'Sweden',
						'CH'	=> 'Switzerland',
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
				  <td><select name="psts[bu_currency]">
				  <?php
				  $currency = $psts->get_setting('bu_currency');
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
				  <td><select name="psts[bu_status]">
				  <option value="live"<?php selected($psts->get_setting('bu_status'), 'live'); ?>><?php _e('Live Site', 'psts') ?></option>
				  <option value="test"<?php selected($psts->get_setting('bu_status'), 'test'); ?>><?php _e('Test Mode (Sandbox)', 'psts') ?></option>
				  </select>
				  </td>
				  </tr>
	        <tr valign="top">
	        <th scope="row"><?php _e('PayPal Payment Type', 'psts') ?></th>
	        <td><select name="psts[bu_payment_type]">
	        <option value="single"<?php selected($psts->get_setting('bu_payment_type'), 'single'); ?>><?php _e('Single', 'psts') ?></option>
	        <option value="recurring"<?php selected($psts->get_setting('bu_payment_type'), 'recurring'); ?>><?php _e('Recurring', 'psts') ?></option>
	        </select>
	        <br /><?php _e('Recurring = PayPal 12 month subscription', 'psts') ?></td>
	        </tr>
	        <tr valign="top">
	        <th scope="row"><?php _e('Credit Level', 'psts') ?></th>
	        <td>
					<select name="psts[bu_level]">
						<?php
						foreach ($levels as $level => $value) {
						?><option value="<?php echo $level; ?>"<?php selected($psts->get_setting('bu_level', 1), $level) ?>><?php echo $level . ': ' . esc_attr($value['name']); ?></option><?php
						}
						?>
	        </select>
	        <br /><?php _e('What Pro Site level credits will upgrade to.', 'psts') ?></td>
	        </tr>
	        <tr valign="top">
	        <th scope="row"><?php _e('Option 1 Settings', 'psts') ?></th>
	        <td><label><?php _e('Credits', 'psts') ?>:
					<select name="psts[bu_credits_1]">
					<?php
						$credits_1 = $psts->get_setting('bu_credits_1', 10);
						for ( $counter = 1; $counter <= 900; $counter++ ) {
			        echo '<option value="' . $counter . '"' . ($counter == $credits_1 ? ' selected' : '') . '>' . number_format_i18n($counter) . '</option>' . "\n";
						}
          ?>
          </select></label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<label><?php _e('Price', 'psts') ?>:
          <?php echo $psts->format_currency(); ?><input value="<?php echo ($psts->get_setting('bu_price_1')) ? number_format( (float)$psts->get_setting('bu_price_1'), 2, '.', '' ) : ''; ?>" size="4" name="psts[bu_price_1]" type="text" />
          </label>
					<br /><?php _e('One credit allows for one site to be upgraded for one year.', 'psts'); ?>
					</td>
          </tr>
	        <tr valign="top">
	        <th scope="row"><?php _e('Option 2 Settings', 'psts') ?></th>
	        <td><label><?php _e('Credits', 'psts') ?>:
					<select name="psts[bu_credits_2]">
          <option value="0"><?php _e('Disabled', 'psts') ?></option>
					<?php
						$credits_2 = $psts->get_setting('bu_credits_2');
						for ( $counter = 1; $counter <= 900; $counter++ ) {
			        echo '<option value="' . $counter . '"' . ($counter == $credits_2 ? ' selected' : '') . '>' . number_format_i18n($counter) . '</option>' . "\n";
						}
          ?>
          </select></label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<label><?php _e('Price', 'psts') ?>:
          <?php echo $psts->format_currency(); ?><input value="<?php echo ($psts->get_setting('bu_price_2')) ? number_format( (float)$psts->get_setting('bu_price_2'), 2, '.', '' ) : ''; ?>" size="4" name="psts[bu_price_2]" type="text" />
          </label>
					<br /><?php _e('One credit allows for one site to be upgraded for one year.', 'psts'); ?>
					</td>
          </tr>
	        <tr valign="top">
	        <th scope="row"><?php _e('Option 3 Settings', 'psts') ?></th>
	        <td><label><?php _e('Credits', 'psts') ?>:
					<select name="psts[bu_credits_3]">
          <option value="0"><?php _e('Disabled', 'psts') ?></option>
					<?php
						$credits_3 = $psts->get_setting('bu_credits_3');
						for ( $counter = 1; $counter <= 900; $counter++ ) {
			        echo '<option value="' . $counter . '"' . ($counter == $credits_3 ? ' selected' : '') . '>' . number_format_i18n($counter) . '</option>' . "\n";
						}
          ?>
          </select></label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<label><?php _e('Price', 'psts') ?>:
          <?php echo $psts->format_currency(); ?><input value="<?php echo ($psts->get_setting('bu_price_3')) ? number_format( (float)$psts->get_setting('bu_price_3'), 2, '.', '' ) : ''; ?>" size="4" name="psts[bu_price_3]" type="text" />
          </label>
					<br /><?php _e('One credit allows for one site to be upgraded for one year.', 'psts'); ?>
					</td>
          </tr>
	        <tr valign="top">
	        <th scope="row"><?php _e('Option 4 Settings', 'psts') ?></th>
	        <td><label><?php _e('Credits', 'psts') ?>:
					<select name="psts[bu_credits_4]">
          <option value="0"><?php _e('Disabled', 'psts') ?></option>
					<?php
						$credits_4 = $psts->get_setting('bu_credits_4');
						for ( $counter = 1; $counter <= 900; $counter++ ) {
			        echo '<option value="' . $counter . '"' . ($counter == $credits_4 ? ' selected' : '') . '>' . number_format_i18n($counter) . '</option>' . "\n";
						}
          ?>
          </select></label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<label><?php _e('Price', 'psts') ?>:
          <?php echo $psts->format_currency(); ?><input value="<?php echo ($psts->get_setting('bu_price_4')) ? number_format( (float)$psts->get_setting('bu_price_4'), 2, '.', '' ) : ''; ?>" size="4" name="psts[bu_price_4]" type="text" />
          </label>
					<br /><?php _e('One credit allows for one site to be upgraded for one year.', 'psts'); ?>
					</td>
          </tr>
          <tr valign="top">
	        <th scope="row"><?php _e('Option Message', 'psts') ?></th>
	        <td>
        	<input type="text" name="psts[bu_option_msg]" value="<?php echo esc_attr($psts->get_setting('bu_option_msg')); ?>" style="width: 95%" />
	        <br /><?php _e('The keywords CREDITS, PRICE, and LEVEL will be replaced with their respective values.', 'psts') ?>
	        </td>
	        </tr>
	        <tr valign="top">
	        <th scope="row"><?php _e('Checkout Message', 'psts') ?></th>
	        <td>
	        <textarea name="psts[bu_checkout_msg]" rows="10" wrap="soft" style="width: 95%"><?php echo esc_textarea($psts->get_setting('bu_checkout_msg')); ?></textarea>
	        <br /><?php _e('Required - HTML allowed - This message is displayed at the top of the "Bulk Upgrades" page.', 'psts') ?></td>
	        </tr>
	        <tr valign="top">
	        <th scope="row"><?php _e('Payment Message', 'psts') ?></th>
	        <td>
        	<input type="text" name="psts[bu_payment_msg]" value="<?php echo esc_attr($psts->get_setting('bu_payment_msg')); ?>" style="width: 95%" />
	        <br /></td>
	        </tr>
					<tr valign="top">
					<th scope="row"><?php _e('Checkout Form Settings', 'psts') ?></th>
					<td>
						<span class="description"><?php _e('Configure how the Bulk Upgrades option is displayed on the main checkout form:', 'psts') ?></span><br />
						<label><input type="text" name="psts[bu_name]" value="<?php echo esc_attr($psts->get_setting('bu_name')); ?>" /> <?php _e('Name', 'psts'); ?></label><br />
						<label><input type="text" size="60" name="psts[bu_link_msg]" value="<?php echo esc_attr($psts->get_setting('bu_link_msg')); ?>" /> <?php _e('Link Message', 'psts'); ?></label>
					</td>
					</tr>
		    </table>
		    <span class="description"><?php _e('Note - You can manually grant Bulk Upgrade credits to users by editing their profile.', 'psts') ?></span>
		  </div>
		</div>
	  <?php
	}

	function paypal_button_output($option) {
		global $wpdb, $current_site, $psts, $user_ID;

		if ($psts->get_setting('bu_status') == 'live') {
			$action = 'https://www.paypal.com/cgi-bin/webscr';
		} else {
			$action = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
		}
		
		if ( $option == '1' ) {
			$credits = $psts->get_setting('bu_credits_1');
			$amount = $psts->get_setting('bu_price_1');
		} else if ( $option == '2' ) {
   		$credits = $psts->get_setting('bu_credits_2');
			$amount = $psts->get_setting('bu_price_2');
		} else if ( $option == '3' ) {
   		$credits = $psts->get_setting('bu_credits_3');
			$amount = $psts->get_setting('bu_price_3');
		} else if ( $option == '4' ) {
   		$credits = $psts->get_setting('bu_credits_4');
			$amount = $psts->get_setting('bu_price_4');
		}
		
		$name = sprintf(__('%1$s %2$s %3$s Bulk Upgrade Credits', 'psts'), $credits, $current_site->site_name, $psts->get_setting('rebrand'));
		
		if ( $psts->get_setting('bu_payment_type') == 'single' ) {
			$button = '
			<form action="' . $action . '" method="post">
				<input type="hidden" name="cmd" value="_xclick">
				<input type="hidden" name="business" value="' . $psts->get_setting('bu_email') . '">
				<input type="hidden" name="item_name" value="' . esc_attr($name) . '">
				<input type="hidden" name="item_number" value="' . $user_ID . '_' . $credits . '">
				<input type="hidden" name="amount" value="' . $amount . '">
				<input type="hidden" name="no_shipping" value="1">
				<input type="hidden" name="return" value="' . admin_url('admin.php?page=psts-bulk-upgrades&msg=' . urlencode(__('Transaction Complete!', 'psts'))) . '">
				<input type="hidden" name="cancel_return" value="' . admin_url('admin.php?page=psts-bulk-upgrades&msg=' . urlencode(__('Transaction Cancelled!', 'psts'))) . '">
    		<input type="hidden" name="notify_url" value="' . network_site_url('wp-admin/admin-ajax.php?action=psts_bu_ipn', 'admin') . '">
				<input type="hidden" name="no_note" value="1">
				<input type="hidden" name="currency_code" value="' . $psts->get_setting('bu_currency') . '">
				<input type="hidden" name="lc" value="' . $psts->get_setting('bu_site') . '">
				<input type="hidden" name="custom" value="' . $wpdb->blogid . '_' . $user_ID . '_' . $credits . '_' . $amount . '_' . $psts->get_setting('bu_currency') . '_' . time() . '">
				<input type="hidden" name="bn" value="PP-BuyNowBF">
	      <input type="image" src="https://www.paypal.com/en_US/i/btn/btn_paynowCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
			</form>
			';
		} else {
			/*
			a3 - amount to billed each recurrence
			p3 - number of time periods between each recurrence
			t3 - time period (D=days, W=weeks, M=months, Y=years)
			*/
			$button = '
			<form action="' . $action . '" method="post">
				<input type="hidden" name="cmd" value="_xclick-subscriptions">
				<input type="hidden" name="business" value="' . $psts->get_setting('bu_email') . '">
    		<input type="hidden" name="item_name" value="' . esc_attr($name) . '">
				<input type="hidden" name="item_number" value="' . $user_ID . '_' . $credits . '">
				<input type="hidden" name="no_shipping" value="1">
				<input type="hidden" name="return" value="' . admin_url('admin.php?page=psts-bulk-upgrades&msg=' . urlencode(__('Transaction Complete!', 'psts'))) . '">
				<input type="hidden" name="cancel_return" value="' . admin_url('admin.php?page=psts-bulk-upgrades&msg=' . urlencode(__('Transaction Cancelled!', 'psts'))) . '">
				<input type="hidden" name="notify_url" value="' . network_site_url('wp-admin/admin-ajax.php?action=psts_bu_ipn', 'admin') . '">
				<input type="hidden" name="no_note" value="1">
				<input type="hidden" name="currency_code" value="' . $psts->get_setting('bu_currency') . '">
				<input type="hidden" name="lc" value="' . $psts->get_setting('bu_site') . '">
				<input type="hidden" name="custom" value="' . $wpdb->blogid . '_' . $user_ID . '_' . $credits . '_' . $amount . '_' . $psts->get_setting('bu_currency') . '_' . time() . '">
				<input type="hidden" name="a3" value="' . $amount . '">
				<input type="hidden" name="p3" value="1">
				<input type="hidden" name="t3" value="Y">
				<input type="hidden" name="src" value="1">
				<input type="hidden" name="sra" value="1">
	      <input type="image" src="https://www.paypal.com/en_US/i/btn/btn_subscribeCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
			</form>
			';
		}
		return $button;
	}
	
	function checkout_grid_msg($content, $blog_id, $periods, $free_width) {
		global $psts;
		
		$content .= '<tr class="psts_level level-bulk">
			<td valign="middle" class="level-name"><h3>'.$psts->get_setting('bu_name').'</h3></td>';
		$content .= '<td class="level-option" colspan="'.count($periods).'">';
		$content .= '<a class="pblg-checkout-opt" style="width: '.$free_width.'" id="psts-bulk-option" href="'.get_admin_url($blog_id, 'admin.php?page=psts-bulk-upgrades', 'http').'">'.$psts->get_setting('bu_link_msg').'</a>';
		$content .= '</td></tr>';
		
		return $content;
	}
	
	//------------------------------------------------------------------------//
	//---Page Output Functions------------------------------------------------//
	//------------------------------------------------------------------------//

	function admin_css() {
		?>
		<style type="text/css">
	    .supporterlist h4 {
	      font-family:"Lucida Grande",Verdana,Arial,"Bitstream Vera Sans",sans-serif;
	      font-size:1.4em;
	      font-weight:normal;
	      margin:1em 0 0;
	    }
	    .supporterlist h4 span {
	      font-family:Georgia,Helvetica,Sans-serif;
	      font-size:14px;
	      font-variant: small-caps;
	    }
	    .supporterlist p {
	      color:#666666;
	      font-size:1em;
	      line-height:1.5em;
	      margin-top:0.3em;
	    }
	    table tr.supporterlist td {
	      border-bottom:1px solid #DDDDDD;
	    }
	    .supporterlist h4.supportercost {
	      color:#000000;
	      font-size:1.4em;
	      margin-top:0.7em;
	      font-family:Georgia,Helvetica,Sans-serif;
	    }
	    .supporterlist span.supportercosthead {
	      border-bottom:1px solid #DDDDDD;
	      color:#999999;
	      font-size:12px;
	      padding:10px 5px 5px;
	      text-align:center;
	    }
	    .supporterlist p.supportercostperday {
	      color:#666666;
	      font-family:"Lucida Sans","Lucida Sans Unicode",Tahoma,Verdana,sans-serif;
	      font-size:10px;
	      font-style:italic;
	      line-height:1.2em;
	      margin:0;
	      margin-top:10px;
	      text-align:center;
	    }
	    p.sponsor-message {
	      font-family:Georgia,Helvetica,Sans-serif;
	      font-size:14px;
	      color:#666666;
	      text-align:center;
	      margin-bottom:5px;
	    }
	    p.cancel-instructions {
	      font-size:12px;
	      margin-top:0px;
	      padding-top:5px;
	    }
	    table.supporter_buttons tr td {
	      border: 0;
	      font-weight: bold;
	      width: auto;
	      padding-top: 5px;
	      text-align: right;
	    }
	    div#psts-msg {
				margin-bottom: 20px;
			}
	  </style>
		<?php
	}

	function bulk_upgrades() {
		global $wpdb, $psts, $current_user, $user_ID;

		if ( !current_user_can('manage_options') ) {
			echo "<p>" . __('Nice Try...', 'psts') . "</p>";  //If accessed properly, this message doesn't appear.
			return;
		}
		
		if (isset($_GET['msg'])) {
			?><div id="message" class="updated fade"><p><?php echo urldecode($_GET['msg']); ?></p></div><?php
		}

    //handle adding new blogs
		if (isset($_POST['submit_process'])) {
			$credits = $this->get_credits($user_ID);
			if ( $credits < 1 ) {
				wp_die( __('You must purchase more Pro Site credits in order to upgrade sites.', 'psts') );
			}
			$upgraded_blogs = 0;
			$now = time();
			$upgrade_hist = get_user_meta($user_ID, 'psts_upgraded', true);
			$blogs = (array)$_POST['blogs'];
			foreach ( $blogs as $blog_id => $value ) {
				if ( $credits > 0 && $value == '1' ) {
					if ( !is_pro_site($blog_id) ) {
						$credits--;
						$upgraded_blogs++;
						$psts->extend($blog_id, 12, 'Bulk Upgrade', $psts->get_setting('bu_level'));
						$upgrade_hist[$blog_id] = $now;
						$psts->log_action( $blog_id, sprintf(__('A bulk upgrade credit was applied by %s.', 'psts'), $current_user->display_name) );
					}
				}
			}
			$this->debit_credits($user_ID, $upgraded_blogs);
			update_user_meta($user_ID, 'psts_upgraded', $upgrade_hist); //save history of blogs this blog has upgraded
      echo '<div id="message" class="updated fade"><p>'.sprintf(__('%s Sites Upgraded.', 'psts'), $upgraded_blogs).'</p></div>';
		}

		$now = time();
		$note = $this->get_note($user_ID);
		$upgrade_credits = $this->get_credits($user_ID);
		$message = $psts->get_setting('bu_checkout_msg');
		?>
		<div class="wrap">
		<div class="icon32"><img src="<?php echo $psts->plugin_url . 'images/import.png'; ?>" /></div>
		<h2><?php _e('Bulk Upgrades', 'psts') ?></h2>

    
		<div class="metabox-holder">
		<?php
		if ( !isset($_POST['submit_search']) ) {
			if ( !empty( $message ) ) {
				echo '<div id="psts-msg">'.$message.'</div>';
			}
		}
		?>
		<div class="postbox">
      <h3 class='hndle'><span><?php _e('Your Credits', 'psts') ?></span></h3>
      <div class="inside">
			<p>
     	<?php
			if ($upgrade_credits)
				printf(__('You have %s upgrade credits ready to apply.', 'psts'), '<strong>'.number_format_i18n($upgrade_credits).'</strong>') . '';
			else
			  _e('You have no upgrade credits. You may purchase them below.', 'psts');
			?>
			</p>
      <?php
			if ( !empty( $note ) ) {
				echo '<p>';
				echo __('Note', 'psts') . ': <strong>' . $note;
				echo '</strong></p>';
			}
			?>
			</div>
		</div>
		
		<?php if ( !isset($_POST['submit_search']) ) { ?>
    <div class="postbox">
      <h3 class='hndle'><span><?php _e('Purchase Bulk Upgrades', 'psts') ?></span></h3>
      <div class="inside">
			<?php
			echo '<table width="100%">';
			
	    $payment_message = str_replace( array('CREDITS', 'PRICE', 'LEVEL'), array($psts->get_setting('bu_credits_1'), $psts->get_setting('bu_price_1'), $psts->get_level_setting($psts->get_setting('bu_level'), 'name')), $psts->get_setting('bu_option_msg') );
	  	echo '<tr class="supporterlist"><td valign="middle"><h4>'.$psts->format_currency(false, $psts->get_setting('bu_price_1')/$psts->get_setting('bu_credits_1')).'<span> '.__('Per Site', 'psts').'</span></h4><p>'.$payment_message.'</p></td><td align="center" valign="middle"><h4 style="margin-bottom: 0px;" class="supportercost">'.$psts->format_currency(false, $psts->get_setting('bu_price_1')).'</h4><span class="supportercosthead">'.__('Per Year', 'psts').'</span><p class="supportercostperday">'.sprintf(__('For %d Sites', 'psts'), $psts->get_setting('bu_credits_1')).'</p></td><td align="right">';
	    echo $this->paypal_button_output(1);
	  	echo '</td></tr>';

	  	if ($psts->get_setting('bu_credits_2')) {
	      $payment_message = str_replace( array('CREDITS', 'PRICE', 'LEVEL'), array($psts->get_setting('bu_credits_2'), $psts->get_setting('bu_price_2'), $psts->get_level_setting($psts->get_setting('bu_level'), 'name')), $psts->get_setting('bu_option_msg') );
	    	echo '<tr class="supporterlist"><td valign="middle"><h4>'.$psts->format_currency(false, $psts->get_setting('bu_price_2')/$psts->get_setting('bu_credits_2')).'<span> '.__('Per Site', 'psts').'</span></h4><p>'.$payment_message.'</p></td><td align="center" valign="middle"><h4 style="margin-bottom: 0px;" class="supportercost">'.$psts->format_currency(false, $psts->get_setting('bu_price_2')).'</h4><span class="supportercosthead">'.__('Per Year', 'psts').'</span><p class="supportercostperday">'.sprintf(__('For %d Sites', 'psts'), $psts->get_setting('bu_credits_2')).'</p></td><td align="right">';
	      echo $this->paypal_button_output(2);
	    	echo '</td></tr>';
	  	}

	  	if ($psts->get_setting('bu_credits_3')) {
	    	$payment_message = str_replace( array('CREDITS', 'PRICE', 'LEVEL'), array($psts->get_setting('bu_credits_3'), $psts->get_setting('bu_price_3'), $psts->get_level_setting($psts->get_setting('bu_level'), 'name')), $psts->get_setting('bu_option_msg') );
	    	echo '<tr class="supporterlist"><td valign="middle"><h4>'.$psts->format_currency(false, $psts->get_setting('bu_price_3')/$psts->get_setting('bu_credits_3')).'<span> '.__('Per Site', 'psts').'</span></h4><p>'.$payment_message.'</p></td><td align="center" valign="middle"><h4 style="margin-bottom: 0px;" class="supportercost">'.$psts->format_currency(false, $psts->get_setting('bu_price_3')).'</h4><span class="supportercosthead">'.__('Per Year', 'psts').'</span><p class="supportercostperday">'.sprintf(__('For %d Sites', 'psts'), $psts->get_setting('bu_credits_3')).'</p></td><td align="right">';
	      echo $this->paypal_button_output(3);
	    	echo '</td></tr>';
	  	}

	  	if ($psts->get_setting('bu_credits_4')) {
	    	$payment_message = str_replace( array('CREDITS', 'PRICE', 'LEVEL'), array($psts->get_setting('bu_credits_4'), $psts->get_setting('bu_price_4'), $psts->get_level_setting($psts->get_setting('bu_level'), 'name')), $psts->get_setting('bu_option_msg') );
	    	echo '<tr class="supporterlist"><td valign="middle"><h4>'.$psts->format_currency(false, $psts->get_setting('bu_price_4')/$psts->get_setting('bu_credits_4')).'<span> '.__('Per Site', 'psts').'</span></h4><p>'.$payment_message.'</p></td><td align="center" valign="middle"><h4 style="margin-bottom: 0px;" class="supportercost">'.$psts->format_currency(false, $psts->get_setting('bu_price_4')).'</h4><span class="supportercosthead">'.__('Per Year', 'psts').'</span><p class="supportercostperday">'.sprintf(__('For %d Sites', 'psts'), $psts->get_setting('bu_credits_4')).'</p></td><td align="right">';
	      echo $this->paypal_button_output(4);
	    	echo '</td></tr>';
	  	}

	  	echo '</table>';
	  	echo '<p class="sponsor-message cancel-instructions">'.$psts->get_setting('bu_payment_msg').'</p>';
			?>
			</div>
	  </div>
		<?php } ?>
		
		<form method="post" action="">
  	<?php if ( $upgrade_credits > 0 ) { ?>
	    <div class="postbox">
	      <h3 class='hndle'><span><?php _e('Find Sites', 'psts') ?></span> - <span class="description"><?php _e('Search for a site to apply an upgrade to.', 'psts') ?></span></h3>
	      <div class="inside">
          <?php
          $curr_blogs = get_blogs_of_user(get_current_user_id());
				  if (!isset($_POST['submit_search']) && $curr_blogs) {
				  ?>
          <h4><?php _e('Choose a site you are a member of:', 'psts'); ?></h4>
					<table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
						<thead><tr>
       				<th scope='col' width='50px'><?php _e('Upgrade', 'psts'); ?></th>
							<th scope='col'><?php _e('Site', 'psts'); ?></th>
							<th scope='col'><?php _e('Expiration', 'psts'); ?></th>
						</tr></thead>
						<tbody id='the-list'>
						<?php
						$class = '';
						foreach ($curr_blogs as $blog_id => $blog) {
						  if ($res = $psts->get_expire($blog_id)) {
								if ($res >= 9999999999)
								  $expire = __('Permanent', 'psts');
								else
								  $expire = date_i18n(get_option('date_format'), $res);
							} else {
                $expire = __('Never Upgraded', 'psts');
							}
	       			//=========================================================//
      	 			echo "<tr class='" . $class . "'>";
							if ( is_pro_site($blog_id) ) {
								echo "<td valign='top'><center><input name='blogs[$blog_id]' id='blog_{$blog_id}' value='1' type='checkbox' disabled='disabled'></center></td>";
							} else {
								echo "<td valign='top'><center><input name='blogs[$blog_id]' id='blog_{$blog_id}' value='1' type='checkbox'></center></td>";
							}
							if ( is_pro_site($blog_id) ) {
        				echo "<td valign='top' style='color:#666666;'><strong>" . $blog->blogname . " (<em>" . $blog->domain . "</em>): " . __('Already Upgraded', 'psts') . "</strong></td>";
							} else {
								echo "<td valign='top'><label for='blog_{$blog_id}'><strong>" . $blog->blogname . " (<em>" . $blog->domain . "</em>)</strong></label></td>";
							}
							echo "<td valign='top'>" . $expire . "</td>";
							echo "</tr>";
							$class = ('alternate' == $class) ? '' : 'alternate';
							//=========================================================//
						}
						?>
						</tbody></table>
            <p class="submit">
            <input type="submit" name="submit_process" value="<?php _e('Upgrade Sites', 'psts') ?> &raquo;" />
            </p>
     		<?php } ?>

     		  <h4><?php _e('Search for a site:', 'psts'); ?></h4>
     			<p><input type="text" name="search" value="" size="30" /><br />
          <?php _e('Enter the site domain here. Example - for "ablog.edublogs.org" you would search for "ablog".', 'psts') ?>
          </p>
          <p class="submit">
          	<input type="submit" name="submit_search" value="<?php _e('Search', 'psts') ?> &raquo;" />
          </p>
	      </div>
	    </div>
	    <?php } ?>

			<?php if ( isset($_POST['submit_search']) ) { ?>

			  <div class="postbox">
		      <h3 class='hndle'><span><?php _e('Search Results', 'psts'); ?></span></h3>
		      <div class="inside">
           <?php
						$query = "SELECT b.blog_id, s.expire FROM {$wpdb->blogs} b LEFT JOIN {$wpdb->base_prefix}pro_sites s ON b.blog_id = s.blog_ID WHERE ( b.domain LIKE '%" . $wpdb->escape($_POST['search']) . "%' OR b.path LIKE '%" . $wpdb->escape($_POST['search']) . "%' ) LIMIT 150";
						$blogs = $wpdb->get_results( $query, ARRAY_A );
						if ( count( $blogs ) > 0 ) {
							if ( count( $blogs ) >= 150 ) {
								?>
		            <span class="description"><?php _e('Over 150 sites were found matching the provided search criteria. If you do not find the site you are looking for in the selection below please try refining your search.', 'psts') ?></span>
		            <?php
							}
						?>
					 <p>
					 <table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
						<thead><tr>
							<th scope='col' width='50px'><?php _e('Upgrade', 'psts'); ?></th>
							<th scope='col'><?php _e('Site', 'psts'); ?></th>
							<th scope='col'><?php _e('Expiration', 'psts'); ?></th>
						</tr></thead>
						<tbody id='the-list'>
						<?php
						$class = '';
						foreach ($blogs as $blog) {
							$blog_details = get_blog_details( $blog['blog_id'] );
							if (isset($blog['expire'])) {
								if ($blog['expire'] >= 9999999999)
								  $expire = __('Permanent', 'psts');
								else
								  $expire = date_i18n(get_option('date_format'), $blog['expire']);
							} else {
                $expire = __('Never Upgraded', 'psts');
							}
	       			//=========================================================//
							echo "<tr class='" . $class . "'>";
							if ( is_pro_site($blog['blog_id']) ) {
								echo "<td valign='top'><center><input name='blogs[" . $blog['blog_id'] . "]' id='blog_{$blog['blog_id']}' value='1' type='checkbox' disabled='disabled'></center></td>";
							} else {
								echo "<td valign='top'><center><input name='blogs[" . $blog['blog_id'] . "]' id='blog_{$blog['blog_id']}' value='1' type='checkbox'></center></td>";
							}
							if ( is_pro_site($blog['blog_id']) ) {
        				echo "<td valign='top' style='color:#666666;'><strong>" . $blog_details->blogname . " (<em>" . $blog_details->domain . "</em>): " . __('Already Upgraded', 'psts') . "</strong></td>";
							} else {
								echo "<td valign='top'><label for='blog_{$blog['blog_id']}'><strong>" . $blog_details->blogname . " (<em>" . $blog_details->domain . "</em>)</strong></label></td>";
							}
							echo "<td valign='top'>" . $expire . "</td>";
							echo "</tr>";
							$class = ('alternate' == $class) ? '' : 'alternate';
							//=========================================================//
						}
						?>
            </tbody></table></p>
            <p class="submit">
            <input type="submit" name="back" value="&laquo; <?php _e('Back', 'psts') ?>" />
            <input type="submit" name="submit_process" value="<?php _e('Upgrade Sites', 'psts') ?> &raquo;" />
	          <?php } else { ?>
            <p><?php _e('No sites found matching your search criteria.', 'psts') ?></p>
            <?php } ?>
		      </div>
		    </div>

			<?php } else { ?>

        <?php
				$blogs = get_user_meta($user_ID, 'psts_upgraded', true);
				if ( is_array($blogs) && count($blogs) ) { ?>
		    <div class="postbox">
		      <h3 class='hndle'><span><?php _e('Previously Upgraded Sites', 'psts'); ?></span> - <span class="description"><?php _e('These are sites that you have previously upgraded in the past.', 'psts'); ?></span></h3>
		      <div class="inside">
					 <p>
					 <table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
						<thead><tr>
						  <?php if ( $upgrade_credits > 0 ) { ?>
       				<th scope='col' width='50px'><?php _e('Upgrade', 'psts'); ?></th>
       				<?php } ?>
							<th scope='col'><?php _e('Site', 'psts'); ?></th>
							<th scope='col'><?php _e('Expiration', 'psts'); ?></th>
							<th scope='col'><?php _e('Upgraded', 'psts'); ?></th>
						</tr></thead>
						<tbody id='the-list'>
						<?php
						$class = '';
						foreach ($blogs as $blog_id => $date) {
						  if (!$blog_id) continue;
       				$blog_details = get_blog_details( $blog_id );
       				if ($res = $psts->get_expire($blog_id)) {
								if ($res >= 9999999999)
								  $expire = __('Permanent', 'psts');
								else
								  $expire = date_i18n(get_option('date_format'), $res);
							} else {
                $expire = __('Never Upgraded', 'psts');
							}
	       			//=========================================================//
							echo "<tr class='" . $class . "'>";
							if ( $upgrade_credits > 0 ) {
								if ( is_pro_site($blog_id) ) {
									echo "<td valign='top'><center><input name='blogs[$blog_id]' id='blog_{$blog_id}' value='1' type='checkbox' disabled='disabled'></center></td>";
								} else {
									echo "<td valign='top'><center><input name='blogs[$blog_id]' id='blog_{$blog_id}' value='1' type='checkbox'></center></td>";
								}
							}
							if ( is_pro_site($blog_id) ) {
        				echo "<td valign='top' style='color:#666666;'><strong>" . $blog_details->blogname . " (<em>" . $blog_details->domain . "</em>): " . __('Already Upgraded', 'psts') . "</strong></td>";
							} else {
								echo "<td valign='top'><label for='blog_{$blog_id}'><strong>" . $blog_details->blogname . " (<em>" . $blog_details->domain . "</em>)</strong></label></td>";
							}
							echo "<td valign='top'>" . $expire . "</td>";
							echo "<td valign='top'>" . date_i18n(get_option('date_format'), $date) . "</td>";
							echo "</tr>";
							$class = ('alternate' == $class) ? '' : 'alternate';
							//=========================================================//
						}
						?>
						</tbody>
						</table></p>
						<?php if ( $upgrade_credits > 0 ) { ?>
	          <p class="submit">
	          <input type="submit" id="submit_process" name="submit_process" value="<?php _e('Upgrade Sites', 'psts') ?> &raquo;" />
	          </p>
						<?php } ?>
		      </div>
		    </div>
        <?php } ?>
      <?php }

		echo '</form></div></div>';
	}
}

//register the module
psts_register_module( 'ProSites_Module_BulkUpgrades', __('Bulk Upgrades', 'psts'), __('Allows you to sell Pro Site level upgrades in bulk packages.', 'psts') );
?>