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
					<th scope="row"
					    class="psts-help-div psts-method-name"><?php echo __( 'Method Name', 'psts' ) . $psts->help_text( __( 'Enter a public name for this payment method that is displayed to users - No HTML', 'psts' ) ); ?></th>
					<td>
						<span class="description"><?php ?></span>

						<p>
							<input value="<?php echo esc_attr( $psts->get_setting( "mp_name" ) ); ?>"
							       style="width: 100%;" name="psts[mp_name]" type="text"/>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"
					    class="psts-help-div psts-user-instruction"><?php echo __( 'User Instructions', 'psts' ) . $psts->help_text( __( 'Manual payment instructions to display on the checkout screen - HTML allowed', 'psts' ) ); ?></th>
					<td>
						<textarea name="psts[mp_instructions]" type="text" rows="4" wrap="soft" id="mp_instructions"
						          style="width: 100%;"/><?php echo esc_textarea( stripslashes( $psts->get_setting( 'mp_instructions' ) ) ); ?></textarea>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"
					    class="psts-help-div psts-show-submission"><?php echo __( 'Show Submission Form', 'psts' ) . $psts->help_text( __( 'Displays a textarea to allow user to enter payment details. The form submission will come to the network admin email address.', 'psts' ) ); ?></th>
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
					<th scope="row"
					    class="psts-help-div psts-submission-form-email"><?php echo __( 'Submission Form Email', 'psts' ) . $psts->help_text( __( 'The email address to send manual payment form submissions to.', 'psts' ) ); ?></th>
					<td>
						<input type="text" name="psts[mp_email]" id="mp_email"
						       value="<?php echo esc_attr( $psts->get_setting( 'mp_email', get_site_option( "admin_email" ) ) ); ?>"
						       size="40"/>
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

		$period = isset( $args['period'] ) && ! empty( $args['period'] ) ? $args['period'] : ProSites_Helper_ProSite::default_period();

		$level = isset( $render_data['new_blog_details'] ) && isset( $render_data['new_blog_details']['level'] ) ? (int) $render_data['new_blog_details']['level'] : 0;
		$level = isset( $render_data['upgraded_blog_details'] ) && isset( $render_data['upgraded_blog_details']['level'] ) ? (int) $render_data['upgraded_blog_details']['level'] : $level;

		$content .= '<form action="' . $psts->checkout_url( $blog_id ) . '" method="post">';

		$content .= '<input type="hidden" name="level" value="' . $level . '" />
					<input type="hidden" name="period" value="' . $period . '" />';

		$name = $psts->get_setting( "mp_name", self::get_name() );
		$name = is_array( $name ) ? array_pop( $name ) : $name;
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
		global $psts, $wpdb;

		if ( isset( $_POST['psts_mp_submit'] ) ) {

			$new_blog = true;

			//check for level
			if ( ! isset( $_POST['level'] ) || ! isset( $_POST['period'] ) ) {
				$psts->errors->add( 'general', __( 'Please choose your desired level and payment plan.', 'psts' ) );

				return;
			}

			// Try going stateless, or check the session
			$process_data = array();
			$session_keys = array( 'new_blog_details', 'upgraded_blog_details', 'COUPON_CODE', 'activation_key' );
			foreach ( $session_keys as $key ) {
				$process_data[ $key ] = ! empty( $process_data[ $key ] ) ? $process_data[ $key ] : ProSites_Helper_Session::session( $key );
			}

			// Get blog_id from the session
			if ( isset( $process_data['new_blog_details'] ) && isset( $process_data['new_blog_details']['blog_id'] ) ) {
				$blog_id = $process_data['new_blog_details']['blog_id'];
			}
			$activation_key = '';
			//Get domain details, if activation is set, runs when user submits the form for blog signup
			if ( ! empty( $_POST['activation'] ) || ! empty( $process_data['activation_key'] ) ) {

				$activation_key = ! empty( $_POST['activation'] ) ? $_POST['activation'] : $process_data['activation_key'];

				//For New Signup
				$signup_details = $wpdb->get_row( $wpdb->prepare( "SELECT `domain`, `path` FROM $wpdb->signups WHERE activation_key = %s", $activation_key ) );

				if ( $signup_details ) {

					$domain = $signup_details->domain;
					$path   = $signup_details->path;

					//Store values in session or custom variable, to be used after user returns from Paypal Payment
					$process_data['new_blog_details']['domain'] = $domain;
					$process_data['new_blog_details']['path']   = $path;
				}
				$process_data['activation_key'] = $activation_key;
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
			$blog_id = ! empty( $blog_id ) ? $blog_id : ( ! empty( $_GET['bid'] ) ? (int) $_GET['bid'] : 0 );
			$blog_id = empty( $blog_id ) && isset( $process_data['upgraded_blog_details']['blog_id'] ) ? $process_data['upgraded_blog_details']['blog_id'] : $blog_id;
			$blog_id = ! empty( $blog_id ) ? $blog_id : ( ! empty( $process_data['new_blog_details']['blog_id'] ) ? $process_data['new_blog_details']['blog_id'] : ( ! empty( $process_data['new_blog_details']['blogname'] ) ? get_id_from_blogname( $process_data['new_blog_details']['blogname'] ) : 0 ) );

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

			//Set Level and period in upgraded blog details, if blog id is set, for upgrades
			if ( ! empty( $blog_id ) ) {
				$new_blog = false;
				if ( ! empty( $level ) && ! empty( $period ) ) {
					$process_data['upgraded_blog_details']['level']  = $level;
					$process_data['upgraded_blog_details']['period'] = $period;
				}
				$current = $wpdb->get_row( "SELECT * FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = '$blog_id'" );
			}

			$signup_type = $new_blog ? 'new_blog_details' : 'upgraded_blog_details';

			// Update the session data with the changed process data.
			ProSites_Helper_Session::session( 'new_blog_details', $process_data['new_blog_details'] );
			ProSites_Helper_Session::session( 'upgraded_blog_details', $process_data['upgraded_blog_details'] );
			ProSites_Helper_Session::session( 'activation_key', $process_data['activation_key'] );

			if ( isset( $_GET['token'] ) ) {
				//Check if blog id is set, If yes -> Upgrade, else  -> New Setup
				$_POST['level']  = ! empty( $process_data[ $signup_type ] ) ? $process_data[ $signup_type ]['level'] : '';
				$_POST['period'] = ! empty( $process_data[ $signup_type ] ) ? $process_data[ $signup_type ]['period'] : '';
			}

			$current_payment = self::calculate_cost( $blog_id, $_POST['level'], $_POST['period'], $process_data['COUPON_CODE'] );
			$modify          = self::is_modifying( $blog_id, $_POST, $current_payment );
			if ( $modify ) {
				//Plan Update
				$psts->log_action( $blog_id, sprintf( __( 'User submitted Manual Payment for blog upgrade from %s to %s.', 'psts' ), $psts->get_level_setting( intval( $current->level ), 'name' ), $psts->get_level_setting( intval( $_POST['level'] ), 'name' ) ) );
				$updated = array(
					'render'      => true,
					'blog_id'     => $blog_id,
					'level'       => $_POST['level'],
					'period'      => $_POST['period'],
					'prev_level'  => ! empty( $current->level ) ? $current->level : '',
					'prev_period' => ! empty( $current->term ) ? $current->term : '',
					'gateway'     => 'manual'
				);
				ProSites_Helper_Session::session( 'plan_updated', $updated );
				$subject = __( 'Pro Sites Manual Payment Submission for Plan update', 'psts' );

				$message_fields = apply_filters( 'prosites_manual_payment_email_info_fields', array(
					'username'     => $username,
					'level'        => intval( $_POST['level'] ),
					'level_name'   => $psts->get_level_setting( intval( $_POST['level'] ), 'name' ),
					'period'       => intval( $_POST['period'] ),
					'user_email'   => $email,
					'site_address' => get_home_url(),
					'manage_link'  => $blog_admin_url,
					'coupon_code'  => ! empty( $process_data['COUPON_CODE'] ) ? $process_data['COUPON_CODE'] : ''
				) );

				$message_parts = apply_filters( 'prosites_manual_payment_email_info', array(
					'description'    => sprintf( __( 'The user "%s" has submitted a manual payment request via the Pro Sites checkout form.', 'psts' ), $message_fields['username'] ) . "\n",
					'level_text'     => __( 'Level: ', 'psts' ) . $message_fields['level'] . ' - ' . $message_fields['level_name'],
					'period_text'    => __( 'Period: ', 'psts' ) . sprintf( __( 'Every %d Months', 'psts' ), $message_fields['period'] ),
					'email_text'     => sprintf( __( "User Email: %s", 'psts' ), $message_fields['user_email'] ),
					'site_text'      => sprintf( __( "Site Address: %s", 'psts' ), $message_fields['site_address'] ),
					'manage_text'    => sprintf( __( "Manage Site: %s", 'psts' ), $blog_admin_url ),
					'coupon_used'    => sprintf( __( "Coupon Used: %s", 'psts' ), $message_fields['coupon_code'] ),
					'payment_amount' => sprintf( __( "Payment Amount: %s", 'psts' ), $current_payment ),
				), $message_fields );

				if ( ! empty( $_POST['psts_mp_text'] ) ) {
					$message_parts['mp_text'] = __( 'User-Entered Comments:', 'psts' ) . "\n";
					$message_parts['mp_text'] .= wp_specialchars_decode( stripslashes( wp_filter_nohtml_kses( $_POST['psts_mp_text'] ) ), ENT_QUOTES );
				}

				$message = apply_filters( 'prosites_manual_payment_email_body', implode( "\n", $message_parts ) . "\n", $message_parts, $message_fields );

				wp_mail( $psts->get_setting( 'mp_email', get_site_option( "admin_email" ) ), $subject, $message );

			} else {

				$subject = __( 'Pro Sites Manual Payment Submission', 'psts' );

				$activate_url = '';

				//Form the activation URL only if we have the activation key
				if ( ! empty( $activation_key ) ) {
					// Send email with activation link.
					if ( class_exists( 'BuddyPress' ) ) {
						// Set up activation link
						$activate_url = bp_get_activation_page() . "?key=$activation_key";
					} elseif ( ! is_subdomain_install() || get_current_site()->id != 1 ) {
						$activate_url = network_site_url( "wp-activate.php?key=$activation_key" );
					} else {
						$activate_url = ! empty( $path ) && ! empty( $domain ) ? "http://{$domain}{$path}wp-activate.php?key=$activation_key" : ''; // @todo use *_url() API
					}

					$activate_url = esc_url( $activate_url );
				}

				$message_fields = apply_filters( 'prosites_manual_payment_email_info_fields', array(
					'username'        => $username,
					'level'           => intval( $_POST['level'] ),
					'level_name'      => $psts->get_level_setting( intval( $_POST['level'] ), 'name' ),
					'period'          => intval( $_POST['period'] ),
					'user_email'      => $email,
					'activation_key'  => $activation_key,
					'activation_link' => $activate_url,
					'site_address'    => get_home_url(),
					'manage_link'     => $blog_admin_url,
					'coupon_code'     => ! empty( $process_data['COUPON_CODE'] ) ? $process_data['COUPON_CODE'] : ''
				) );

				$message_parts = apply_filters( 'prosites_manual_payment_email_info', array(
					'description'     => sprintf( __( 'The user "%s" has submitted a manual payment request via the Pro Sites checkout form.', 'psts' ), $message_fields['username'] ) . "\n",
					'level_text'      => __( 'Level: ', 'psts' ) . $message_fields['level'] . ' - ' . $message_fields['level_name'],
					'period_text'     => __( 'Period: ', 'psts' ) . sprintf( __( 'Every %d Months', 'psts' ), $message_fields['period'] ),
					'email_text'      => sprintf( __( "User Email: %s", 'psts' ), $message_fields['user_email'] ),
					'activation_text' => ! empty( $message_fields['activation_key'] ) ? sprintf( __( "Activation Key: %s", 'psts' ), $message_fields['activation_key'] ) : '',
					'activation_link' => ! empty( $message_fields['activation_link'] ) ? sprintf( __( "Activation Link: %s", 'psts' ), $message_fields['activation_link'] ) : '',
					'site_text'       => sprintf( __( "Site Address: %s", 'psts' ), $message_fields['site_address'] ),
					'manage_text'     => sprintf( __( "Manage Site: %s", 'psts' ), $blog_admin_url ),
					'coupon_used'     => ! empty( $message_fields['coupon_code'] ) ? sprintf( __( "Coupon Used: %s", 'psts' ), $message_fields['coupon_code'] ) : '',
					'payment_amount'  => sprintf( __( "Payment Amount: %s", 'psts' ), $current_payment ),
				), $message_fields );

				if ( ! empty( $_POST['psts_mp_text'] ) ) {
					$message_parts['mp_text'] = __( 'User-Entered Comments:', 'psts' ) . "\n";
					$message_parts['mp_text'] .= wp_specialchars_decode( stripslashes( wp_filter_nohtml_kses( $_POST['psts_mp_text'] ) ), ENT_QUOTES );
				}

				$message = apply_filters( 'prosites_manual_payment_email_body', implode( "\n", $message_parts ) . "\n", $message_parts, $message_fields );

				wp_mail( $psts->get_setting( 'mp_email', get_site_option( "admin_email" ) ), $subject, $message );
				ProSites_Helper_Session::session( array(
					'new_blog_details',
					'reserved_message'
				), __( 'Manual payment request submitted.', 'psts' ) );
				// Payment pending...
				ProSites_Helper_Session::session( array( 'new_blog_details', 'manual_submitted' ), true );
			}
			do_action( 'prosites_manual_payment_email_sent', $message, $message_parts, $message_fields );

			$recurring = $psts->get_setting( 'recurring_subscriptions', true );

			//Store level and period in Signup meta
			$manual_signup = array(
				'level'     => $_POST['level'],
				'period'    => $_POST['period'],
				'gateway'   => self::get_slug(),
				'amount'    => $current_payment,
				'recurring' => $recurring
			);
			//Get signup meta
			$signup_meta                           = $psts->get_signup_meta( $activation_key );
			$signup_meta['pro_site_manual_signup'] = $manual_signup;

			//Update
			$psts->update_signup_meta( $signup_meta, $activation_key );

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

		$last_gateway = ProSites_Helper_ProSite::last_gateway( $blog_id );
		if ( ! empty( $last_gateway ) && $last_gateway == self::get_slug() ) {
			$psts->email_notification( $blog_id, 'canceled' );
		}
		update_blog_option( $blog_id, 'psts_is_canceled', 1 );

		$end_date = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
		$psts->log_action( $blog_id, sprintf( __( 'Subscription successfully cancelled by %1$s. They should continue to have access until %2$s', 'psts' ), $current_user->display_name, $end_date ) );

		//Do not display message for add action
		if ( $display_message ) {
			self::$cancel_message = '<div id="message" class="updated fade"><p>' . sprintf( __( 'Your %1$s subscription has been canceled. You should continue to have access until %2$s.', 'psts' ), $site_name . ' ' . $psts->get_setting( 'rebrand' ), $end_date ) . '</p></div>';

		}
	}

	/**
	 * Calculate the payment made for the current purchase
	 *
	 * @param $blog_id
	 * @param $level
	 * @param $period
	 * @param $coupon_code
	 *
	 * @return null|string
	 */
	public static function calculate_cost( $blog_id, $level, $period, $coupon_code ) {
		global $psts;
		$setup_fee     = (float) $psts->get_setting( 'setup_fee', 0 );
		$recurring     = $psts->get_setting( 'recurring_subscriptions', true );
		$paymentAmount = $psts->get_level_setting( $level, 'price_' . $period );
		$has_setup_fee = $psts->has_setup_fee( $blog_id, $level );
		$has_coupon    = ( isset( $coupon_code ) && ProSites_Helper_Coupons::check_coupon( $coupon_code, $blog_id, $level, $period, '' ) ) ? true : false;

		//Add setup fee to init amount
		if ( $has_setup_fee ) {
			$paymentAmount += $setup_fee;
		}
		if ( ! $recurring && ! empty( $blog_id ) ) {
			//Calculate Upgrade or downgrade cost, use original cost to calculate the Upgrade cost
			$paymentAmount = $psts->calc_upgrade_cost( $blog_id, $level, $period, $paymentAmount );
		}
		if ( $has_coupon ) {
			//apply coupon
			$adjusted_values = ProSites_Helper_Coupons::get_adjusted_level_amounts( $coupon_code );
			$coupon_value    = $adjusted_values[ $level ][ 'price_' . $period ];
			$amount_off      = $paymentAmount - $coupon_value;

			//Round the value to two digits
			$amount_off    = number_format( $amount_off, 2, '.', '' );
			$paymentAmount = $paymentAmount - $amount_off;
		}

		return $paymentAmount;
	}

	/**
	 * Check if user is upgrading or downgrading
	 *
	 * @param $blog_id
	 * @param $post
	 */
	private static function is_modifying( $blog_id, $post, $initAmount ) {
		global $psts;

		$modify = false;
		$level  = ! empty( $post['level'] ) ? $post['level'] : '';
		$period = ! empty( $post['period'] ) ? $post['period'] : '';
		if ( empty( $blog_id ) || empty( $level ) || empty( $period ) ) {
			return false;
		}
		//check for modifying
		if ( ! empty( $blog_id ) && is_pro_site( $blog_id ) && ! is_pro_trial( $blog_id ) ) {
			$modify = $psts->calc_upgrade( $blog_id, $initAmount, $level, $period );
			$modify = $modify ? $modify : $psts->get_expire( $blog_id );
		} else {
			$modify = false;
		}

		return $modify;
	}

	public static function render_account_modified( $content, $blog_id, $domain ) {
		global $psts;

		$render_data['plan_updated'] = ProSites_Helper_Session::session( 'plan_updated' );

		// Exit as if this never happened
		if ( ! isset( $render_data['plan_updated'] ) || false == $render_data['plan_updated']['render'] ) {
			return $content;
		}

		$level_list = get_site_option( 'psts_levels' );

		$periods = array(
			1  => __( 'monthly', 'psts' ),
			3  => __( 'quarterly', 'psts' ),
			12 => __( 'anually', 'psts' ),
		);

		$previous = '<strong>' . $level_list[ $render_data['plan_updated']['prev_level'] ]['name'] . '</strong> (' . $periods[ $render_data['plan_updated']['prev_period'] ] . ')';
		$current  = '<strong>' . $level_list[ $render_data['plan_updated']['level'] ]['name'] . '</strong> (' . $periods[ $render_data['plan_updated']['period'] ] . ')';

		$blog_id = (int) $render_data['plan_updated']['blog_id'];

		$content = '<div id="psts-payment-info-received">';

		$user  = wp_get_current_user();
		$email = $user->user_email;

		$content .= '<h2>' . esc_html__( 'Plan update...', 'psts' ) . '</h2>';
		$content .= '<p>' . sprintf( esc_html__( 'We have recieved your request to update plan from %s to %s. Once we verify the payment details, the plan will be updated.', 'psts' ), $previous, $current ) . '</p>';
		$content .= '<a href="' . $psts->checkout_url( $blog_id ) . '">' . esc_html__( 'Go back to your account.', 'psts' ) . '</a>';
		$content .= '</div>';

		ProSites_Helper_Session::unset_session( 'plan_updated' );

		return $content;
	}

	/**
	 * Returns list of all the currencies for Manual Gateway
	 *
	 * @return array
	 */
	public static function get_supported_currencies() {

		return array(
			'AED' => array( 'United Arab Emirates Dirham', '62F, 2E ,625' ),
			'AFN' => array( 'Afghan Afghani', '60b' ),
			'ALL' => array( 'Albanian Lek', '4c, 65, 6b' ),
			'AMD' => array( 'Armenian Dram', '58F' ),
			'ANG' => array( 'Netherlands Antillean Gulden', '192' ),
			'AOA' => array( 'Angolan Kwanza', '4B, 7A' ),
			'ARS' => array( 'Argentine Peso', '24' ),
			'AUD' => array( 'Australian Dollar', '24' ),
			'AWG' => array( 'Aruban Florin', '192' ),
			'AZN' => array( 'Azerbaijani Manat', '43c, 430, 43d' ),
			'BAM' => array( 'Bosnia & Herzegovina Convertible Mark', '4b, 4d' ),
			'BBD' => array( 'Barbadian Dollar', '24' ),
			'BDT' => array( 'Bangladeshi Taka', '09F3' ),
			'BGN' => array( 'Bulgarian Lev', '43b, 432' ),
			'BIF' => array( 'Burundian Franc', '46, 42, 75' ),
			'BMD' => array( 'Bermudian Dollar', '24' ),
			'BND' => array( 'Brunei Dollar', '24' ),
			'BOB' => array( 'Bolivian Boliviano', '24, 62' ),
			'BRL' => array( 'Brazilian Real', '52, 24' ),
			'BSD' => array( 'Bahamian Dollar', '24' ),
			'BWP' => array( 'Botswana Pula', '50' ),
			'BZD' => array( 'Belize Dollar', '42, 5a, 24' ),
			'CAD' => array( 'Canadian Dollar', '24' ),
			'CDF' => array( 'Congolese Franc', '46, 43' ),
			'CHF' => array( 'Swiss Franc', '43, 48, 46' ),
			'CLP' => array( 'Chilean Peso', '24' ),
			'CNY' => array( 'Chinese Renminbi Yuan', 'a5' ),
			'COP' => array( 'Colombian Peso', '24' ),
			'CRC' => array( 'Costa Rican Colón', '20a1' ),
			'CVE' => array( 'Cape Verdean Escudo', '24' ),
			'CZK' => array( 'Czech Koruna', '4b, 10d' ),
			'DJF' => array( 'Djiboutian Franc', '46, 64, 6A' ),
			'DKK' => array( 'Danish Krone', '6b, 72' ),
			'DOP' => array( 'Dominican Peso', '52, 44, 24' ),
			'DZD' => array( 'Algerian Dinar', '62F, 62C' ),
			'EEK' => array( 'Estonian Kroon', '6b, 72' ),
			'EGP' => array( 'Egyptian Pound', 'a3' ),
			'ETB' => array( 'Ethiopian Birr', '1265, 122D' ),
			'EUR' => array( 'Euro', '20ac' ),
			'FJD' => array( 'Fijian Dollar', '24' ),
			'FKP' => array( 'Falkland Islands Pound', 'a3' ),
			'GBP' => array( 'British Pound', 'a3' ),
			'GEL' => array( 'Georgian Lari', '10DA' ),
			'GIP' => array( 'Gibraltar Pound', 'a3' ),
			'GMD' => array( 'Gambian Dalasi', '44' ),
			'GNF' => array( 'Guinean Franc', '46, 47' ),
			'GTQ' => array( 'Guatemalan Quetzal', '51' ),
			'GYD' => array( 'Guyanese Dollar', '24' ),
			'HKD' => array( 'Hong Kong Dollar', '24' ),
			'HNL' => array( 'Honduran Lempira', '4c' ),
			'HRK' => array( 'Croatian Kuna', '6b, 6e' ),
			'HTG' => array( 'Haitian Gourde', '47' ),
			'HUF' => array( 'Hungarian Forint', '46, 74' ),
			'IDR' => array( 'Indonesian Rupiah', '52, 70' ),
			'ILS' => array( 'Israeli New Sheqel', '20aa' ),
			'INR' => array( 'Indian Rupee', '20B9' ),
			'ISK' => array( 'Icelandic Króna', '6b, 72' ),
			'JMD' => array( 'Jamaican Dollar', '4a, 24' ),
			'JPY' => array( 'Japanese Yen', 'a5' ),
			'KES' => array( 'Kenyan Shilling', '4B, 53, 68' ),
			'KGS' => array( 'Kyrgyzstani Som', '43b, 432' ),
			'KHR' => array( 'Cambodian Riel', '17db' ),
			'KMF' => array( 'Comorian Franc', '43, 46' ),
			'KRW' => array( 'South Korean Won', '20a9' ),
			'KYD' => array( 'Cayman Islands Dollar', '24' ),
			'KZT' => array( 'Kazakhstani Tenge', '43b, 432' ),
			'LAK' => array( 'Lao Kip', '20ad' ),
			'LBP' => array( 'Lebanese Pound', 'a3' ),
			'LKR' => array( 'Sri Lankan Rupee', '20a8' ),
			'LRD' => array( 'Liberian Dollar', '24' ),
			'LSL' => array( 'Lesotho Loti', '4C' ),
			'LTL' => array( 'Lithuanian Litas', '4c, 74' ),
			'LVL' => array( 'Latvian Lats', '4c, 73' ),
			'MAD' => array( 'Moroccan Dirham', '62F, 2E, 645, 2E' ),
			'MDL' => array( 'Moldovan Leu', '6C, 65, 69' ),
			'MGA' => array( 'Malagasy Ariary', '41, 72' ),
			'MKD' => array( 'Macedonian Denar', '434, 435, 43d' ),
			'MNT' => array( 'Mongolian Tögrög', '20ae' ),
			'MOP' => array( 'Macanese Pataca', '4D, 4F, 50, 24' ),
			'MRO' => array( 'Mauritanian Ouguiya', '55, 4D' ),
			'MUR' => array( 'Mauritian Rupee', '20a8' ),
			'MVR' => array( 'Maldivian Rufiyaa', '52, 66' ),
			'MWK' => array( 'Malawian Kwacha', '4D, 4B' ),
			'MXN' => array( 'Mexican Peso', '24' ),
			'MYR' => array( 'Malaysian Ringgit', '52, 4d' ),
			'MZN' => array( 'Mozambican Metical', '4d, 54' ),
			'NAD' => array( 'Namibian Dollar', '24' ),
			'NGN' => array( 'Nigerian Naira', '20a6' ),
			'NIO' => array( 'Nicaraguan Córdoba', '43, 24' ),
			'NOK' => array( 'Norwegian Krone', '6b, 72' ),
			'NPR' => array( 'Nepalese Rupee', '20a8' ),
			'NZD' => array( 'New Zealand Dollar', '24' ),
			'PAB' => array( 'Panamanian Balboa', '42, 2f, 2e' ),
			'PEN' => array( 'Peruvian Nuevo Sol', '53, 2f, 2e' ),
			'PGK' => array( 'Papua New Guinean Kina', '4B' ),
			'PHP' => array( 'Philippine Peso', '20b1' ),
			'PKR' => array( 'Pakistani Rupee', '20a8' ),
			'PLN' => array( 'Polish Złoty', '7a, 142' ),
			'PYG' => array( 'Paraguayan Guaraní', '47, 73' ),
			'QAR' => array( 'Qatari Riyal', 'fdfc' ),
			'RON' => array( 'Romanian Leu', '6c, 65, 69' ),
			'RSD' => array( 'Serbian Dinar', '414, 438, 43d, 2e' ),
			'RUB' => array( 'Russian Ruble', '440, 443, 431' ),
			'RWF' => array( 'Rwandan Franc', '52, 20A3' ),
			'SAR' => array( 'Saudi Riyal', 'fdfc' ),
			'SBD' => array( 'Solomon Islands Dollar', '24' ),
			'SCR' => array( 'Seychellois Rupee', '20a8' ),
			'SEK' => array( 'Swedish Krona', '6b, 72' ),
			'SGD' => array( 'Singapore Dollar', '24' ),
			'SHP' => array( 'Saint Helenian Pound', 'a3' ),
			'SLL' => array( 'Sierra Leonean Leone', '4C, 65' ),
			'SOS' => array( 'Somali Shilling', '53' ),
			'SRD' => array( 'Surinamese Dollar', '24' ),
			'STD' => array( 'São Tomé and Príncipe Dobra', '44, 62' ),
			'SVC' => array( 'Salvadoran Colón', '24' ),
			'SZL' => array( 'Swazi Lilangeni', '45' ),
			'THB' => array( 'Thai Baht', 'e3f' ),
			'TJS' => array( 'Tajikistani Somoni', '73, 6F, 6D, 6F, 6E, 69' ),
			'TOP' => array( 'Tongan Paʻanga', '54, 24' ),
			'TRY' => array( 'Turkish Lira', '20BA' ),
			'TTD' => array( 'Trinidad and Tobago Dollar', '54, 54, 24' ),
			'TWD' => array( 'New Taiwan Dollar', '4e, 54, 24' ),
			'TZS' => array( 'Tanzanian Shilling', '78, 2F, 79' ),
			'UAH' => array( 'Ukrainian Hryvnia', '20b4' ),
			'UGX' => array( 'Ugandan Shilling', '55, 53, 68' ),
			'USD' => array( 'United States Dollar', '24' ),
			'UYU' => array( 'Uruguayan Peso', '24, 55' ),
			'UZS' => array( 'Uzbekistani Som', '43b, 432' ),
			'VND' => array( 'Vietnamese Đồng', '20ab' ),
			'VUV' => array( 'Vanuatu Vatu', '56, 54' ),
			'WST' => array( 'Samoan Tala', '24' ),
			'XAF' => array( 'Central African Cfa Franc', '46, 43, 46, 41' ),
			'XCD' => array( 'East Caribbean Dollar', '24' ),
			'XOF' => array( 'West African Cfa Franc', '43, 46, 41' ),
			'XPF' => array( 'Cfp Franc', '46' ),
			'YER' => array( 'Yemeni Rial', 'fdfc' ),
			'ZAR' => array( 'South African Rand', '52' ),
			'ZMW' => array( 'Zambian Kwacha', '4B' ),
		);
	}
}

//register the gateway
psts_register_gateway( 'ProSites_Gateway_Manual', __( 'Manual Payments', 'psts' ), __( 'Record payments manually, such as by Cash, Check, EFT, or an unsupported gateway.', 'psts' ) );
?>