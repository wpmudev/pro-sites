<?php

if ( ! class_exists( 'ProSites_View_Settings' ) ) {
	class ProSites_View_Settings {

		public static function render_page() {

			if ( ! is_super_admin() ) {
				echo "<p>" . __( 'Nice Try...', 'psts' ) . "</p>"; //If accessed properly, this message doesn't appear.
				return false;
			}

			// Might move this to a controller, not sure if needed yet.
			ProSites_Model_Settings::process_form();

			?>
			<form method="post" action="">
				<?php

				$page_header_options = array(
					'title'       => __( 'Pro Sites Settings', 'psts' ),
					'desc'        => __( '', 'psts' ),
					'page_header' => true,
				);

				$options = array(
					'header_save_button'  => true,
					'section_save_button' => true,
					'nonce_name'          => 'psts_settings',
					'button_name'         => 'settings',
				);

				ProSites_Helper_Tabs_Settings::render( get_class(), $page_header_options, $options );

				?>

			</form>
		<?php

		}

		/**
		 * General Settings
		 *
		 * @return string
		 */
		public static function render_tab_general() {
			global $psts;
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			?>

			<div class="inside">
				<table class="form-table">
					<tr valign="top">
						<th scope="row"
						    class="psts-help-div psts-rebrand-pro"><?php echo __( 'Rebrand Pro Sites', 'psts' ) . ProSites_Helper_UI::help_text( __( 'Rename "Pro Sites" for users to whatever you want like "Pro" or "Plus".', 'psts' ) ); ?></th>
						<td>
							<input type="text" name="psts[rebrand]"
							       value="<?php echo esc_attr( $psts->get_setting( 'rebrand' ) ); ?>"/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Admin Menu Button Labels', 'psts' ) ?></th>
						<td>
							<label>
								<span class="psts-label psts-label-notpro"><?php _e( 'Not Pro', 'psts' ); ?></span>
								<input type="text" name="psts[lbl_signup]"
								       value="<?php echo esc_attr( $psts->get_setting( 'lbl_signup' ) ); ?>"/>
							</label><br/>
							<label>
								<span
									class="psts-label psts-label-currentpro"><?php _e( 'Current Pro', 'psts' ); ?></span>
								<input type="text" name="psts[lbl_curr]"
								       value="<?php echo esc_attr( $psts->get_setting( 'lbl_curr' ) ); ?>"/>
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Hide Admin Menu', 'psts' ); ?></th>
						<td>
							<label><input type="checkbox" name="psts[hide_adminmenu]"
							              value="1"<?php checked( $psts->get_setting( 'hide_adminmenu' ) ); ?> />
								<?php _e( 'Remove the Pro Sites upgrade menu item', 'psts' ); ?></label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Hide Admin Bar Button', 'psts' ); ?></th>
						<td>
							<label><input type="checkbox" name="psts[hide_adminbar]"
							              value="1"<?php checked( $psts->get_setting( 'hide_adminbar' ) ); ?> />
								<?php _e( 'Remove the Pro Sites upgrade menu button from the admin bar', 'psts' ); ?>
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Hide Pro Status for Superadmin', 'psts' ); ?></th>
						<td>
							<label><input type="checkbox" name="psts[hide_adminbar_super]"
							              value="1"<?php checked( $psts->get_setting( 'hide_adminbar_super' ) ); ?> />
								<?php _e( 'Remove the Super Admin Pro Site status menu from the admin bar', 'psts' ); ?>
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"
						    class="psts-free-level psts-help-div"><?php echo __( 'Free Level', 'psts' ) . ProSites_Helper_UI::help_text( __( 'Pro Sites has a built-in free level by default. Configure how this level is displayed on the checkout form:', 'psts' ) ); ?></th>
						<td>
							<label>
								<span class="psts-label psts-label-name"><?php _e( 'Name', 'psts' ); ?></span>
								<input type="text" name="psts[free_name]"
								       value="<?php echo esc_attr( $psts->get_setting( 'free_name' ) ); ?>"/>
							</label><br/>
							<label>
								<span class="psts-label psts-label-message"><?php _e( 'Message', 'psts' ); ?></span>
								<input type="text" size="50" name="psts[free_msg]"
								       value="<?php echo esc_attr( $psts->get_setting( 'free_msg' ) ); ?>"/>
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"
						    class="pay-for-signup"><?php echo __( 'Allow checkout on Signup', 'psts' ) . ProSites_Helper_UI::help_text( __( 'Enables the user to checkout after signing up, If user opts for Pro Site, blog setup takes place only after the user have made the payment.', 'psts' ) ); ?></th>
						<td>
							<label><input type="checkbox" name="psts[show_signup]"
							              value="1"<?php checked( $psts->get_setting( 'show_signup' ) ); ?> />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"
						    class="psts-help-div psts-signup-message"><?php echo __( 'Signup Message', 'psts' ) . ProSites_Helper_UI::help_text( __( 'Optional - HTML allowed - This message is displayed on the signup page if the box is checked above.', 'psts' ) ); ?></th>
						<td>
							<textarea name="psts[signup_message]" rows="3" wrap="soft" id="signup_message"
							          style="width: 95%"><?php echo esc_textarea( $psts->get_setting( 'signup_message' ) ); ?></textarea>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"
						    class="psts-help-div psts-checkout-page"><?php echo __( 'Checkout Page', 'psts' ) . ProSites_Helper_UI::help_text( __( 'If checkout page is not found, a new checkout page is generated upon saving the settings. The slug and title is based on the rebrand option above.', 'psts' ) ); ?></th>
						<td>
							<?php if ( empty( $checkout_link ) ) { ?>
								<?php _e( 'There was a problem finding the Checkout Page. Please follow the directions below to regenerate it:', 'psts' ); ?>
							<?php } else { ?>
								<a href="<?php echo $checkout_link; ?>"
								   title="<?php _e( 'Edit Checkout Page &raquo;', 'psts' ); ?>"><?php _e( 'Edit Checkout Page &raquo;', 'psts' ); ?></a>
							<?php } ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Checkout Permissions', 'psts' ) ?></th>
						<td><?php

							$roles          = get_editable_roles();
							$checkout_roles = $psts->get_setting( 'checkout_roles', 'not_set' );

							foreach ( $roles as $role_key => $role ) {
								$checked = '';
								//Default keep all applicable roles checked
								if ( ( is_array( $checkout_roles ) && in_array( $role_key, $checkout_roles ) ) || $checkout_roles == 'not_set' ) {
									$checked = 'checked="checked"';
								}
								if ( ! empty ( $role['capabilities']['manage_options'] ) || ! empty( $role['capabilities']['edit_pages'] ) ) {
									?>
									<label>
										<input type="checkbox" name="psts[checkout_roles][]"
										       value="<?php echo $role_key; ?>" <?php echo $checked; ?>/><?php echo $role['name']; ?>
									</label> <?php
								}
							}

							?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"
						    class="psts-help-div psts-feature-message"><?php echo __( 'Pro Site Feature Message', 'psts' ) . ProSites_Helper_UI::help_text( __( 'Required - No HTML - This message is displayed when a feature is accessed on a site that does not have access to it. "LEVEL" will be replaced with the needed level name for the feature.', 'psts' ) ); ?></th>
						<td>
							<input name="psts[feature_message]" type="text" id="feature_message"
							       value="<?php echo esc_attr( $psts->get_setting( 'feature_message' ) ); ?>"
							       style="width: 95%"/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"
						    class="psts-free-trial psts-help-div"><?php echo __( 'Free Trial', 'psts' ) . ProSites_Helper_UI::help_text( __( 'Free days for all new sites', 'psts' ) ); ?></th>
						<td><select name="psts[trial_days]">
								<?php
								$trial_days         = $psts->get_setting( 'trial_days' );
								$trial_days_options = '';

								for ( $counter = 0; $counter <= 365; $counter ++ ) {
									$trial_days_options .= '<option value="' . $counter . '"' . ( $counter == $trial_days ? ' selected' : '' ) . '>' . ( ( $counter ) ? $counter : __( 'Disabled', 'psts' ) ) . '</option>' . "\n";
								}

								//allow plugins to modify the trial days options (some people want to display as years, more than one year, etc)
								echo apply_filters( 'psts_trial_days_options', $trial_days_options );
								?>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"
						    class="psts-trial-message psts-help-div"><?php echo __( 'Free Trial Message', 'psts' ) . ProSites_Helper_UI::help_text( __( 'Required - This message is displayed on the dashboard notifying how many days left in their free trial. "DAYS" will be replaced with the number of days left in the trial. "LEVEL" will be replaced with the needed level name.', 'psts' ) ); ?></th>
						<td>
							<input type="text" name="psts[trial_message]" id="trial_message"
							       value="<?php esc_attr_e( $psts->get_setting( 'trial_message' ) ); ?>"
							       style="width: 95%"/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"
						    class="psts-cancellation psts-help-div"><?php echo __( 'Cancellation Message', 'psts' ) . ProSites_Helper_UI::help_text( __( 'This message is displayed on the checkout screen notifying FREE TRIAL and NEW customers of your cancellation policy. "DAYS" will be replaced with the number of "Cancellation Days" set above.', 'psts' ) ); ?></th>
						<td>
							<textarea style="width:95%" wrap="soft" rows="3"
							          name="psts[cancel_message]"><?php echo $psts->get_setting( 'cancel_message', __( 'Your DAYS day trial begins once you click "Subscribe" below. We perform a $1 pre-authorization to ensure your credit card is valid, but we won\'t actually charge your card until the end of your trial. If you don\'t cancel by day DAYS, your card will be charged for the subscription amount shown above. You can cancel your subscription at any time.', 'psts' ) ); ?></textarea><br/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"
						    class="psts-help-div psts-setup-fee"><?php echo __( 'Setup Fee', 'psts' ) . ProSites_Helper_UI::help_text( __( 'If "Apply setup fee to upgrades" is left unchecked then only <strong>free sites</strong> will be charged a setup fee. Otherwise, all levels will be charged a setup fee upon upgrading to a higher level.', 'psts' ) ); ?></th>
						<td>
							<label><?php echo $psts->format_currency(); ?></label><input type="text"
							                                                             name="psts[setup_fee]" size="4"
							                                                             value="<?php echo ( $setup_fee = $psts->get_setting( 'setup_fee', false ) ) ? number_format( (float) $setup_fee, 2, '.', '' ) : ''; ?>"/>
							&nbsp;<br/><br/>
							<label for="psts-apply-setup-fee-upgrade">
								<input type="checkbox" name="psts[apply_setup_fee_upgrade]"
								       id="psts-apply-setup-fee-upgrade"
								       value="1" <?php checked( $psts->get_setting( 'apply_setup_fee_upgrade', 0 ), 1 ); ?> />
								<label
									for="psts-apply-setup-fee-upgrade"><?php _e( 'Apply setup fee to upgrades', 'psts' ); ?></label>
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"
						    class="psts-help-div psts-recurring"><?php echo __( 'Recurring Subscriptions', 'psts' ) . ProSites_Helper_UI::help_text( __( 'Disabling recurring subscriptions will force users to have to manually re-subscribe after their term has expired.', 'psts' ) ); ?></th>
						<td>
							<label for="psts-recurring-subscriptions-on" style="margin-right:10px">
								<input type="radio" name="psts[recurring_subscriptions]"
								       id="psts-recurring-subscriptions-on"
								       value="1" <?php checked( $psts->get_setting( 'recurring_subscriptions', 1 ), 1 ); ?> /> <?php _e( 'Enable', 'psts' ); ?>
							</label>
							<label for="psts-subscriptions-off">
								<input type="radio" name="psts[recurring_subscriptions]"
								       id="psts-recurring-subscriptions-off"
								       value="0" <?php checked( $psts->get_setting( 'recurring_subscriptions', 1 ), 0 ); ?> /> <?php _e( 'Disable', 'psts' ); ?>
							</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Google Analytics Ecommerce Tracking', 'psts' ) ?></th>
						<td>
							<select name="psts[ga_ecommerce]">
								<option
									value="none"<?php selected( $psts->get_setting( 'ga_ecommerce' ), 'none' ) ?>><?php _e( 'None', 'psts' ) ?></option>
								<option
									value="new"<?php selected( $psts->get_setting( 'ga_ecommerce' ), 'new' ) ?>><?php _e( 'Asynchronous Tracking Code', 'psts' ) ?></option>
								<option
									value="old"<?php selected( $psts->get_setting( 'ga_ecommerce' ), 'old' ) ?>><?php _e( 'Old Tracking Code', 'psts' ) ?></option>
							</select>
							<br/><span
								class="description"><?php _e( 'If you already use Google Analytics for your website, you can track detailed ecommerce information by enabling this setting. Choose whether you are using the new asynchronous or old tracking code. Before Google Analytics can report ecommerce activity for your website, you must enable ecommerce tracking on the profile settings page for your website. <a href="http://analytics.blogspot.com/2009/05/how-to-use-ecommerce-tracking-in-google.html" target="_blank">More information &raquo;</a>', 'psts' ) ?></span>
						</td>
					</tr>
					<?php do_action( 'psts_general_settings' ); ?>
				</table>
			</div>




			<?php
			return ob_get_clean();
		}

		/**
		 * E-mail Settings
		 *
		 * @return string
		 */
		public static function render_tab_email() {
			global $psts;
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			?>

			<div class="inside">
				<table class="form-table">
					<tr>
						<th scope="row" class="psts-help-div psts-pro-signup"><?php echo __( 'Pro Site Signup', 'psts' ) . ProSites_Helper_UI::help_text( __( 'Pro Site signup confirmation email sent to user', 'psts' ) ); ?></th>
						<td>
							<label><?php _e( 'Subject:', 'psts' ); ?><br/>
								<input type="text" class="pp_emails_sub" name="psts[success_subject]" value="<?php echo esc_attr( $psts->get_setting( 'success_subject' ) ); ?>" maxlength="150" style="width: 95%"/></label><br/>
							<label><?php _e( 'Message:', 'psts' ); ?><br/>
								<textarea class="pp_emails_txt" name="psts[success_msg]" style="width: 95%"><?php echo esc_textarea( $psts->get_setting( 'success_msg' ) ); ?></textarea>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row" class="psts-help-div psts-pro-site-cancelled"><?php echo __( 'Pro Site Canceled', 'psts' ) . ProSites_Helper_UI::help_text( __( 'Membership cancellation email sent to user, "ENDDATE" will be replaced with the date when their Pro Site access ends.', 'psts' ) ); ?></th>
						<td>
							<label><?php _e( 'Subject:', 'psts' ); ?><br/>
								<input type="text" class="pp_emails_sub" name="psts[canceled_subject]" value="<?php echo esc_attr( $psts->get_setting( 'canceled_subject' ) ); ?>" maxlength="150" style="width: 95%"/></label><br/>
							<label><?php _e( 'Message:', 'psts' ); ?><br/>
								<textarea class="pp_emails_txt" name="psts[canceled_msg]" style="width: 95%"><?php echo esc_textarea( $psts->get_setting( 'canceled_msg' ) ); ?></textarea>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row" class="psts-help-div psts-payment-reciept"><?php echo __( 'Payment Receipt', 'psts' ) . ProSites_Helper_UI::help_text( __( 'Payment confirmation receipt. You must include the "PAYMENTINFO" code which will be replaced with payment details.', 'psts' ) ); ?></th>
						<td>
							<label><?php _e( 'Subject:', 'psts' ); ?><br/>
								<input type="text" class="pp_emails_sub" name="psts[receipt_subject]" value="<?php echo esc_attr( $psts->get_setting( 'receipt_subject' ) ); ?>" maxlength="150" style="width: 95%"/></label><br/>
							<label><?php _e( 'Message:', 'psts' ); ?><br/>
								<textarea class="pp_emails_txt" name="psts[receipt_msg]" style="width: 95%"><?php echo esc_textarea( $psts->get_setting( 'receipt_msg' ) ); ?></textarea></label><br/>
							<label><?php _e( 'Header Image URL (for PDF attachment):', 'psts' ); ?><br/>
								<input type="text" class="pp_emails_img" name="psts[receipt_image]" value="<?php echo esc_attr( $psts->get_setting( 'receipt_image' ) ); ?>" maxlength="150" style="width: 65%"/></label>
						</td>
					</tr>
					<tr>
						<th scope="row" class="psts-help-div psts-expiration-mail"><?php echo __( 'Expiration Email', 'psts' ) . ProSites_Helper_UI::help_text( __( 'Pro Site expiration email sent to user. "CHECKOUTURL" will be replaced with the url to upgrade the site.', 'psts' ) ); ?></th>
						<td>
							<label><?php _e( 'Subject:', 'psts' ); ?><br/>
								<input type="text" class="pp_emails_sub" name="psts[expired_subject]" value="<?php echo esc_attr( $psts->get_setting( 'expired_subject' ) ); ?>" maxlength="150" style="width: 95%"/></label><br/>
							<label><?php _e( 'Message:', 'psts' ); ?><br/>
								<textarea class="pp_emails_txt" name="psts[expired_msg]" style="width: 95%"><?php echo esc_textarea( $psts->get_setting( 'expired_msg' ) ); ?></textarea>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row" class="psts-help-div psts-payment-problem"><?php echo __( 'Payment Problem', 'psts' ) . ProSites_Helper_UI::help_text( __( 'The email text sent to your customer when a scheduled payment fails.', 'psts' ) ); ?></th>
						<td>
							<label><?php _e( 'Subject:', 'psts' ); ?><br/>
								<input type="text" class="pp_emails_sub" name="psts[failed_subject]" value="<?php echo esc_attr( $psts->get_setting( 'failed_subject' ) ); ?>" maxlength="150" style="width: 95%"/></label><br/>
							<label><?php _e( 'Message:', 'psts' ); ?><br/>
								<textarea class="pp_emails_txt" name="psts[failed_msg]" style="width: 95%"><?php echo esc_textarea( $psts->get_setting( 'failed_msg' ) ); ?></textarea>
							</label>
						</td>
					</tr>
					<?php do_action( 'psts_email_settings' ); ?>
				</table>
			</div>

			<?php
			return ob_get_clean();
		}

		/**
		 * 'Payment Settings'
		 *
		 * @return string
		 */
		public static function render_tab_payment() {
			global $psts;
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			?>
			<div class="inside">
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e( 'Currency Symbol', 'psts' ) ?></th>
						<td>
							<select id="psts-currency-select" name="psts[currency]">
								<?php
								foreach ( $psts->currencies as $key => $value ) {
									?>
									<option value="<?php echo $key; ?>"<?php selected( $psts->get_setting( 'currency' ), $key ); ?>><?php echo esc_attr( $value[0] ) . ' - ' . $psts->format_currency( $key ); ?></option><?php
								}
								?>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Currency Symbol Position', 'psts' ) ?></th>
						<td>
							<label><input value="1" name="psts[curr_symbol_position]" type="radio"<?php checked( $psts->get_setting( 'curr_symbol_position', 1 ), 1 ); ?>>
								<?php echo $psts->format_currency(); ?>100</label><br/>
							<label><input value="2" name="psts[curr_symbol_position]" type="radio"<?php checked( $psts->get_setting( 'curr_symbol_position' ), 2 ); ?>>
								<?php echo $psts->format_currency(); ?> 100</label><br/>
							<label><input value="3" name="psts[curr_symbol_position]" type="radio"<?php checked( $psts->get_setting( 'curr_symbol_position' ), 3 ); ?>>
								100<?php echo $psts->format_currency(); ?></label><br/>
							<label><input value="4" name="psts[curr_symbol_position]" type="radio"<?php checked( $psts->get_setting( 'curr_symbol_position' ), 4 ); ?>>
								100 <?php echo $psts->format_currency(); ?></label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Show Decimal in Prices', 'psts' ) ?></th>
						<td>
							<label><input value="1" name="psts[curr_decimal]" type="radio"<?php checked( $psts->get_setting( 'curr_decimal', 1 ), 1 ); ?>>
								<?php _e( 'Yes', 'psts' ) ?></label>
							<label><input value="0" name="psts[curr_decimal]" type="radio"<?php checked( $psts->get_setting( 'curr_decimal' ), 0 ); ?>>
								<?php _e( 'No', 'psts' ) ?></label>
						</td>
					</tr>
				</table>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * 'Advertising'
		 *
		 * @return string
		 */
		public static function render_tab_ads() {
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			$module = new ProSites_Module_Ads();
			echo $module->settings();
			return ob_get_clean();
		}

		/**
		 * 'Automated Email Responses'
		 *
		 * @return string
		 */
//		public static function render_tab_messages_automated() {
//			ob_start();
//			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
//			return ob_get_clean();
//		}

		/**
		 * 'Pro Sites Widget'
		 *
		 * @return string
		 */
		public static function render_tab_prowidget() {
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			$module = new ProSites_Module_ProWidget();
			echo $module->settings();
			return ob_get_clean();
		}


		/**
		 * 'BuddyPress Features'
		 *
		 * @return string
		 */
		public static function render_tab_buddypress() {
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			$module = new ProSites_Module_BP();
			echo $module->settings();
			return ob_get_clean();
		}


		/**
		 * 'Bulk Upgrades'
		 *
		 * @return string
		 */
		public static function render_tab_bulkupgrades() {
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			$module = new ProSites_Module_BulkUpgrades();
			echo $module->settings();
			return ob_get_clean();
		}


		/**
		 * 'Pay to Blog'
		 *
		 * @return string
		 */
		public static function render_tab_paytoblog() {
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			$module = new ProSites_Module_PayToBlog();
			echo $module->settings();
			return ob_get_clean();
		}


		/**
		 * 'Post/Page Throttling'
		 *
		 * @return string
		 */
		public static function render_tab_throttling() {
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			$module = new ProSites_Module_PostThrottling();
			echo $module->renderModuleSettings();
			return ob_get_clean();
		}


		/**
		 * 'Post/Page Quotas'
		 *
		 * @return string
		 */
		public static function render_tab_quotas() {
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			$module = new ProSites_Module_PostingQuota();
			echo $module->settings();
			return ob_get_clean();
		}


		/**
		 * 'Rename Plugin/Theme Features'
		 *
		 * @return string
		 */
		public static function render_tab_renaming() {
			global $psts;
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );

			$modules = $psts->get_setting( 'modules_enabled' );
			$modules = ! empty( $modules ) ? $modules : array();

			if ( in_array( 'ProSites_Module_PremiumThemes', $modules ) ) {
				$module = new ProSites_Module_PremiumThemes();
				echo $module->settings();
			}
			if ( in_array( 'ProSites_Module_Plugins', $modules ) ) {
				$module = new ProSites_Module_Plugins();
				echo $module->settings();
			}
			return ob_get_clean();
		}


		/**
		 * 'Premium Support'
		 *
		 * @return string
		 */
		public static function render_tab_support() {
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			$module = new ProSites_Module_Support();
			echo $module->settings();
			return ob_get_clean();
		}


		/**
		 * 'Upload Quotas'
		 *
		 * @return string
		 */
		public static function render_tab_upload_quota() {
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			$module = new ProSites_Module_Quota();
			echo $module->settings();
			return ob_get_clean();
		}

		/**
		 * 'Upgrade Admin Links'
		 *
		 * @return string
		 */
		public static function render_tab_upgrade_admin_links() {
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			$module = new ProSites_Module_UpgradeAdminLinks();
			echo $module->settings();
			return ob_get_clean();
		}

		/**
		 * 'Content/HTML Filter'
		 *
		 * @return string
		 */
		public static function render_tab_filters() {
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			$module = new ProSites_Module_UnfilterHtml();
			echo $module->settings();
			return ob_get_clean();
		}


		/**
		 * 'Publishing Limits'
		 *
		 * @return string
		 */
		public static function render_tab_writing() {
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			$module = new ProSites_Module_Writing();
			echo $module->settings();
			return ob_get_clean();
		}


		/**
		 * 'Restrict XML-RPC'
		 *
		 * @return string
		 */
		public static function render_tab_xmlrpc() {
			ob_start();
			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Settings::get_active_tab() );
			$module = new ProSites_Module_XMLRPC();
			echo $module->settings();
			return ob_get_clean();
		}


	}
}