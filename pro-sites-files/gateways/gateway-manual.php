<?php

/*
Pro Sites (Gateway: Manual Payments Gateway)
*/

class ProSites_Gateway_Manual {

	public static $complete_message = false;
	public static $cancel_message = '';

	public static function get_slug() {
		return 'manual';
	}

	function __construct() {
		//checkout stuff
		add_filter( 'psts_checkout_output', array( &$this, 'checkout_screen' ), 10, 3 );
	}

	function settings() {
		global $psts;
		$show_form = $psts->get_setting( 'mp_show_form', 0 );
		$show_form = $show_form || $show_form == 'on' ? 1 : 0;
		?>
		<div class="inside">
			<table class="form-table">
				<tr>
					<th scope="row" class="psts-help-div psts-method-name"><?php echo __( 'Method Name', 'psts' ) . $psts->help_text( __( 'Enter a public name for this payment method that is displayed to users - No HTML', 'psts' ) ); ?></th>
					<td>
						<span class="description"><?php ?></span>

						<p>
							<input value="<?php echo esc_attr( $psts->get_setting( "mp_name" ) ); ?>" style="width: 100%;" name="psts[mp_name]" type="text"/>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="psts-help-div psts-user-instruction"><?php echo __( 'User Instructions', 'psts' ) . $psts->help_text( __( 'Manual payment instructions to display on the checkout screen - HTML allowed', 'psts' ) ); ?></th>
					<td>
						<textarea name="psts[mp_instructions]" type="text" rows="4" wrap="soft" id="mp_instructions" style="width: 100%;"/><?php echo esc_textarea( stripslashes( $psts->get_setting( 'mp_instructions' ) ) ); ?></textarea>
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

	/**
	 * Checkout Screen for Manual Payment
	 *
	 * @param $content
	 * @param $blog_id
	 * @param string $domain
	 *
	 * @return string
	 */
	public static function checkout_screen( $content, $blog_id, $domain = '' ) {
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
		if ( self::$complete_message ) {
			$content = '<div id="psts-complete-msg">' . self::$complete_message . '</div>';

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

		$content .= '<div id="psts-manual-instructions">' . stripslashes( do_shortcode( $psts->get_setting( 'mp_instructions' ) ) ) . '</div>';

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

	public static function render_gateway( $render_data = array(), $args, $blog_id, $domain, $prefer_cc = false ) {
		global $psts;
		$content = '';

		$session_keys = array( 'new_blog_details', 'upgraded_blog_details' );
		foreach ( $session_keys as $key ) {
			$render_data[ $key ] = isset( $render_data[ $key ] ) ? $render_data[ $key ] : ProSites_Helper_Session::session( $key );
		}

		$period = isset( $args['period'] ) && ! empty( $args['period'] ) ? $args['period'] : 1;

		$level = isset( $render_data['new_blog_details'] ) && isset( $render_data['new_blog_details']['level'] ) ? (int) $render_data['new_blog_details']['level'] : 0;
		$level = isset( $render_data['upgraded_blog_details'] ) && isset( $render_data['upgraded_blog_details']['level'] ) ? (int) $render_data['upgraded_blog_details']['level'] : $level;

		$content .= '<form action="' . $psts->checkout_url( $blog_id ) . '" method="post">';

		$content .= '<input type="hidden" name="level" value="' . $level . '" />
					<input type="hidden" name="period" value="' . $period . '" />';

		$name = self::get_name();
		$name = array_pop( $name );
		$content .= '<div id="psts-manual-checkout"><h2>' . $name . '</h2>';

		$content .= '<div id="psts-manual-instructions">' . stripslashes( do_shortcode( $psts->get_setting( 'mp_instructions' ) ) ) . '</div>';

		if ( $psts->get_setting( 'mp_show_form' ) ) {
			$prefill = isset( $_POST['psts_mp_text'] ) ? esc_textarea( stripslashes( $_POST['psts_mp_text'] ) ) : '';
			$content .= '<textarea id="psts-manual-textarea" name="psts_mp_text">' . $prefill . '</textarea>';
		}
		$content .= '<p><input id="psts-manual-submit" type="submit" name="psts_mp_submit" value="' . esc_attr__( 'Submit', 'psts' ) . '"></p>';
		$content .= '</div></form>';

		return $content;
	}

	public static function process_checkout_form( $process_data = array(), $blog_id, $domain ) {
		global $psts;

		$session_keys = array( 'new_blog_details', 'upgraded_blog_details', 'COUPON_CODE', 'activation_key' );
		foreach ( $session_keys as $key ) {
			$process_data[ $key ] = isset( $process_data[ $key ] ) ? $process_data[ $key ] : ProSites_Helper_Session::session( $key );
		}

		if ( isset( $_POST['psts_mp_submit'] ) ) {

			//check for level
			if ( ! isset( $_POST['level'] ) || ! isset( $_POST['period'] ) ) {
				$psts->errors->add( 'general', __( 'Please choose your desired level and payment plan.', 'psts' ) );

				return;
			}

			if ( is_user_logged_in() ) {
				$user     = wp_get_current_user();
				$email    = $user->user_email;
				$username = $user->user_login;
			} else if ( isset( $process_data['new_blog_details'] ) ) {
				if ( isset( $process_data['new_blog_details']['email'] ) ) {
					$email = sanitize_email( $process_data['new_blog_details']['email'] );
				}
				if ( isset( $process_data['new_blog_details']['username'] ) ) {
					$username = sanitize_text_field( $process_data['new_blog_details']['username'] );
				}
			}
			if ( empty( $email ) ) {
				$psts->errors->add( 'general', __( 'No valid email given.', 'psts' ) );

				return;
			}

			// Get the blog id... try the session or get it from the database
			$blog_id = isset( $process_data['upgraded_blog_details']['blog_id'] ) ? $process_data['upgraded_blog_details']['blog_id'] : 0;
			$blog_id = ! empty( $blog_id ) ? $blog_id : isset( $process_data['new_blog_details']['blog_id'] ) ? $process_data['new_blog_details']['blog_id'] : isset( $process_data['new_blog_details']['blogname'] ) ? get_id_from_blogname( $process_data['new_blog_details']['blogname'] ) : 0;

			switch_to_blog( $blog_id );
			$blog_admin_url = admin_url();
			restore_current_blog();

			if ( $blog_admin_url == admin_url() ) {
				$blog_admin_url = __( 'Not activated yet.', 'psts' );
			}

			$activation_key = '';
			if ( isset( $process_data['activation_key'] ) ) {
				$activation_key = $process_data['activation_key'];
			}
			$subject = __( 'Pro Sites Manual Payment Submission', 'psts' );

			$message_fields = apply_filters( 'prosites_manual_payment_email_info_fields', array(
				'username'       => $username,
				'level'          => intval( $_POST['level'] ),
				'level_name'     => $psts->get_level_setting( intval( $_POST['level'] ), 'name' ),
				'period'         => intval( $_POST['period'] ),
				'user_email'     => $email,
				'activation_key' => $activation_key,
				'site_address'   => get_home_url(),
				'manage_link'    => $blog_admin_url
			) );

			$message_parts = apply_filters( 'prosites_manual_payment_email_info', array(
				'description'     => sprintf( __( 'The user "%s" has submitted a manual payment request via the Pro Sites checkout form.', 'psts' ), $message_fields['username'] ) . "\n",
				'level_text'      => __( 'Level: ', 'psts' ) . $message_fields['level'] . ' - ' . $message_fields['level_name'],
				'period_text'     => __( 'Period: ', 'psts' ) . sprintf( __( 'Every %d Months', 'psts' ), $message_fields['period'] ),
				'email_text'      => sprintf( __( "User Email: %s", 'psts' ), $message_fields['user_email'] ),
				'activation_text' => sprintf( __( "Activation Key: %s", 'psts' ), $message_fields['activation_key'] ),
				'site_text'       => sprintf( __( "Site Address: %s", 'psts' ), $message_fields['site_address'] ),
				'manage_text'     => sprintf( __( "Manage Site: %s", 'psts' ), $blog_admin_url ),
			), $message_fields );

			if ( ! empty( $_POST['psts_mp_text'] ) ) {
				$message_parts['mp_text'] = __( 'User-Entered Comments:', 'psts' ) . "\n";
				$message_parts['mp_text'] .= wp_specialchars_decode( stripslashes( wp_filter_nohtml_kses( $_POST['psts_mp_text'] ) ), ENT_QUOTES );
			}

			$message = apply_filters( 'prosites_manual_payment_email_body', implode( "\n", $message_parts ) . "\n", $message_parts, $message_fields );

			wp_mail( $psts->get_setting( 'mp_email', get_site_option( "admin_email" ) ), $subject, $message );

			add_action( 'prosites_manual_payment_email_sent', $message, $message_parts, $message_fields );

			ProSites_Helper_Session::session( array(
				'new_blog_details',
				'reserved_message'
			), __( 'Manual payment request submitted.', 'psts' ) );
			// Payment pending...
			ProSites_Helper_Session::session( array( 'new_blog_details', 'manual_submitted' ), true );

		}

	}

	public static function process_on_render() {
		return true;
	}

	public static function get_existing_user_information( $blog_id, $domain, $get_all = true ) {
		global $psts;
		$args     = array();
		$img_base = $psts->plugin_url . 'images/';

		$trialing = ProSites_Helper_Registration::is_trial( $blog_id );
		if ( $trialing ) {
			$args['trial'] = '<div id="psts-general-error" class="psts-warning">' . __( 'You are still within your trial period. Once your trial finishes your account will be automatically charged.', 'psts' ) . '</div>';
		}

		// Pending information
		if ( ! empty( $blog_id ) && 1 == get_blog_option( $blog_id, 'psts_stripe_waiting' ) ) {
			$args['pending'] = '<div id="psts-general-error" class="psts-warning">' . __( 'There are pending changes to your account. This message will disappear once these pending changes are completed.', 'psts' ) . '</div>';
		}

		return empty( $content ) ? array() : $content;
	}

	/**
	 * Cancel Blog Subscription
	 *
	 * @param $blog_id
	 * @param bool $display_message
	 */
	public static function cancel_subscription( $blog_id, $display_message = false ) {
		global $psts, $current_user, $current_site;

		$site_name = $current_site->site_name;

		//record stat
		$psts->record_stat( $blog_id, 'cancel' );

		$psts->email_notification( $blog_id, 'canceled' );
		update_blog_option( $blog_id, 'psts_is_canceled', 1 );

		$end_date = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
		$psts->log_action( $blog_id, sprintf( __( 'Subscription successfully cancelled by %1$s. They should continue to have access until %2$s', 'psts' ), $current_user->display_name, $end_date ) );

		//Do not display message for add action
		if ( $display_message ) {
			self::$cancel_message = '<div id="message" class="updated fade"><p>' . sprintf( __( 'Your %1$s subscription has been canceled. You should continue to have access until %2$s.', 'psts' ), $site_name . ' ' . $psts->get_setting( 'rebrand' ), $end_date ) . '</p></div>';

		}
	}


}

//register the gateway
psts_register_gateway( 'ProSites_Gateway_Manual', __( 'Manual Payments', 'psts' ), __( 'Record payments manually, such as by Cash, Check, EFT, or an unsupported gateway.', 'psts' ) );
?>