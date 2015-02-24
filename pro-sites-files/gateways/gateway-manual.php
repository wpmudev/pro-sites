<?php

/*
Pro Sites (Gateway: Manual Payments Gateway)
*/

class ProSites_Gateway_Manual {

	var $complete_message = false;

	function __construct() {
		//settings
//		add_action( 'psts_gateway_settings', array( &$this, 'settings' ) );

		//checkout stuff
		add_filter( 'psts_checkout_output', array( &$this, 'checkout_screen' ), 10, 3 );
		add_action( 'psts_checkout_page_load', array( &$this, 'process_checkout' ), '', 2 );
	}

	function settings() {
		global $psts;
		$show_form = $psts->get_setting( 'mp_show_form', 0 );
		$show_form = $show_form || $show_form == 'on' ? 1 : 0;
		?>
<!--		<div class="postbox">-->
<!--			<h3 class="hndle" style="cursor:auto;"><span>--><?php //_e( 'Manual Payments', 'psts' ) ?><!--</span> --->
<!--				<span class="description">--><?php //_e( 'Record payments manually, such as by Cash, Check, EFT, or an unsupported gateway.', 'psts' ); ?><!--</span>-->
<!--			</h3>-->

			<div class="inside">
				<table class="form-table">
					<tr>
						<th scope="row" class="psts-help-div psts-method-name"><?php echo __( 'Method Name', 'psts' ) . $psts->help_text ( __( 'Enter a public name for this payment method that is displayed to users - No HTML', 'psts' ) ); ?></th>
						<td>
							<span class="description"><?php  ?></span>

							<p>
								<input value="<?php echo esc_attr( $psts->get_setting( "mp_name" ) ); ?>"  style="width: 100%;" name="psts[mp_name]" type="text"/>
							</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="psts-help-div psts-user-instruction"><?php echo __( 'User Instructions', 'psts' ) . $psts->help_text( __( 'Manual payment instructions to display on the checkout screen - HTML allowed', 'psts' ) ); ?></th>
						<td>
							<textarea name="psts[mp_instructions]" type="text" rows="4" wrap="soft" id="mp_instructions" style="width: 100%;" /><?php echo esc_textarea( $psts->get_setting( 'mp_instructions' ) ); ?></textarea>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="psts-help-div psts-show-submission"><?php echo __( 'Show Submission Form', 'psts' ) . $psts->help_text( __( 'Displays a textarea to allow user to enter payment details. The form submission will come to the network admin email address.', 'psts' ) ); ?></th>
						<td>
							<label>
								<input type="radio" name="psts[mp_show_form]" value="1"<?php checked( $show_form, 1 ); ?>>
								<?php _e( 'Yes', 'psts' ) ?>
							</label>&nbsp;&nbsp;

							<label>
								<input type="radio" name="psts[mp_show_form]" value="0"<?php checked( $show_form, 0 ); ?>>
								<?php _e( 'No', 'psts' ) ?>
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="psts-help-div psts-submission-form-email"><?php echo __( 'Submission Form Email', 'psts' ) . $psts->help_text( __( 'The email address to send manual payment form submissions to.', 'psts' ) ); ?></th>
						<td>
							<input type="text" name="psts[mp_email]" id="mp_email" value="<?php echo esc_attr( $psts->get_setting( 'mp_email', get_site_option( "admin_email" ) ) ); ?>" size="40"/>
						</td>
					</tr>
				</table>
			</div>
<!--		</div>-->
	<?php
	}

	function payment_info( $payment_info, $blog_id ) {
		global $psts;

		$payment_info .= __( 'Payment Method: PayPal Account', 'psts' ) . "\n";
		$payment_info .= sprintf( __( 'Next Payment Date: %s', 'psts' ), date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) ) ) . "\n";

		return $payment_info;
	}

	function process_checkout( $blog_id, $domain = '' ) {
		global $psts, $current_user;

		//Check if form was submitted
		if ( ! isset ( $_POST['psts_mp_submit'] ) ) {
			return;
		}
		//check for level
		if ( ! isset( $_POST['level'] ) || ! isset( $_POST['period'] ) ) {
			$psts->errors->add( 'general', __( 'Please choose your desired level and payment plan.', 'psts' ) );

			return;
		}
		//Activate Trial Blog for any selected level or period
		if ( empty( $blog_id ) ) {
			$blog_id = $psts->activate_user_blog( $domain, 'trial' );
		}

		$subject = __( 'Pro Sites Manual Payment Submission', 'psts' );
		$message = sprintf( __( 'The user "%s" has submitted a manual payment request via the Pro Sites checkout form.', 'psts' ), $current_user->user_login ) . "\n\n";
		$message .= __( 'Level: ', 'psts' ) . intval( $_POST['level'] ) . ' - ' . $psts->get_level_setting( intval( $_POST['level'] ), 'name' ) . "\n";
		$message .= __( 'Period: ', 'psts' ) . sprintf( __( 'Every %d Months', 'psts' ), intval( $_POST['period'] ) ) . "\n";
		$message .= sprintf( __( "User Email: %s", 'psts' ), $current_user->user_email ) . "\n";
		$message .= sprintf( __( "Site Address: %s", 'psts' ), get_home_url( $blog_id ) ) . "\n";
		$message .= sprintf( __( "Manage Site: %s", 'psts' ), network_admin_url( 'admin.php?page=psts&bid=' . $blog_id ) ) . "\n\n";

		if ( ! empty( $_POST['psts_mp_text'] ) ) {
			$message .= __( 'User-Entered Comments:', 'psts' ) . "\n";
			$message .= wp_specialchars_decode( stripslashes( wp_filter_nohtml_kses( $_POST['psts_mp_text'] ) ), ENT_QUOTES );
		}

		wp_mail( $psts->get_setting( 'mp_email', get_site_option( "admin_email" ) ), $subject, $message );

		//Complete Message displayed on checkout screen for manual payment
		if ( ! empty ( $domain ) && 0 != $_POST['level'] ) {
			//If user has selected a paid level
			$this->complete_message = __( 'Your trial blog has been setup at <a href="' . $domain . '">' . $domain . '</a>, we have been notified of your payment request.', 'psts' );
		} elseif ( ! empty ( $domain ) && 0 == $_POST['level'] ) {
			//If user has selected a free blog signup
			$this->complete_message = __( 'Your trial blog has been setup at <a href="' . $domain . '">' . $domain . '</a>', 'psts' );
		} else {
			//If user is paying for a existing blog
			$this->complete_message = __( 'Thank you, we have been notified of your request.', 'psts' );
		}
	}

	/**
	 * Checkout Screen for Manual Payment
	 *
	 * @param $content
	 * @param $blog_id
	 * @param string $domain
	 *
	 * @return string
	 */
	function checkout_screen( $content, $blog_id, $domain = '' ) {
		global $psts, $wpdb, $current_site, $current_user;

		if ( ! $blog_id && ! $domain ) {
			return $content;
		}

		//hide top part of content if its a pro blog
		if ( is_pro_site( $blog_id ) ) {
			$content = '';
		}

		if ( $errmsg = $psts->errors->get_error_message( 'general' ) ) {
			$content .= '<div id="psts-general-error" class="psts-error">' . $errmsg . '</div>';
		}

		//if transaction was successful display a complete message and skip the rest
		if ( $this->complete_message ) {
			$content = '<div id="psts-complete-msg">' . $this->complete_message . '</div>';

			return $content;
		}

		if ( is_pro_site( $blog_id ) ) {

			$end_date    = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
			$level       = $psts->get_level_setting( $psts->get_level( $blog_id ), 'name' );
			$old_gateway = $wpdb->get_var( "SELECT gateway FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'" );

			$content .= '<div id="psts_existing_info">';
			$content .= '<h3>' . __( 'Your Account Information', 'psts' ) . '</h3><ul>';
			$content .= '<li>' . __( 'Level:', 'psts' ) . ' <strong>' . $level . '</strong></li>';

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
				$content .= '<h3>' . __( 'Cancel Your Subscription', 'psts' ) . '</h3>';
				$content .= '<p>' . sprintf( __( 'If your subscription is still active your next scheduled payment should be %1$s.', 'psts' ), $end_date ) . '</p>';
				$content .= '<p>' . sprintf( __( 'If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts' ), $level, $end_date ) . '</p>';
				//show instructions for old gateways
				if ( $old_gateway == 'PayPal' ) {
					$content .= '<p><a id="pypl_cancel" target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_subscr-find&alias=' . urlencode( get_site_option( "supporter_paypal_email" ) ) . '" title="' . __( 'Cancel Your Subscription', 'psts' ) . '"><img src="' . $psts->plugin_url . 'images/cancel_subscribe_gen.gif" /></a><br /><small>' . __( 'You can also cancel following <a href="https://www.paypal.com/webapps/helpcenter/article/?articleID=94044#canceling_recurring_paymemt_subscription_automatic_billing">these steps</a>.', 'psts' ) . '</small></p>';
				} else if ( $old_gateway == 'Amazon' ) {
					$content .= '<p>' . __( 'To cancel your subscription, simply go to <a id="pypl_cancel" target="_blank" href="https://payments.amazon.com/">https://payments.amazon.com/</a>, click Your Account at the top of the page, log in to your Amazon Payments account (if asked), and then click the Your Subscriptions link. This page displays your subscriptions, showing the most recent, active subscription at the top. To view the details of a specific subscription, click Details. Then cancel your subscription by clicking the Cancel Subscription button on the Subscription Details page.', 'psts' ) . '</p>';
				}
			}
			$content .= '</div>';

		}

		$content .= '<form action="' . $psts->checkout_url( $blog_id ) . '" method="post">';

		//print the checkout grid
//		$content .= $psts->checkout_grid( $blog_id, $domain );

		$content .= '<div id="psts-manual-checkout"><h2>' . $psts->get_setting( 'mp_name' ) . '</h2>';

		$content .= '<div id="psts-manual-instructions">' . do_shortcode( $psts->get_setting( 'mp_instructions' ) ) . '</div>';

		if ( $psts->get_setting( 'mp_show_form' ) ) {
			$prefill = isset( $_POST['psts_mp_text'] ) ? esc_textarea( stripslashes( $_POST['psts_mp_text'] ) ) : '';
			$content .= '<textarea id="psts-manual-textarea" name="psts_mp_text">' . $prefill . '</textarea>';
		}
		$content .= '<p><input id="psts-manual-submit" type="submit" name="psts_mp_submit" value="' . esc_attr__( 'Submit', 'psts' ) . '"></p>';
		$content .= '</div></form>';

		return $content;
	}

	public static function get_name() {
		return array(
			'manual' => __( 'Manual Payments', 'psts' ),
		);
	}

	public static function render_gateway( $args, $blog_id, $domain, $prefer_cc = false ) {


		$content = __( 'Manual Gateway', 'psts' );

		return $content;
	}

	public static function process_checkout_form( $blog_id, $domain ) {

	}

	public static function get_existing_user_information() {

		$content = '';


		return empty( $content ) ? false : $content;
	}


}

//register the gateway
psts_register_gateway( 'ProSites_Gateway_Manual', __( 'Manual Payments', 'psts' ), __( 'Record payments manually, such as by Cash, Check, EFT, or an unsupported gateway.', 'psts' ) );
?>