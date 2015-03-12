<?php

if ( ! class_exists( 'ProSites_View_Gateways' ) ) {
	class ProSites_View_Gateways {

		public static function render_page() {

			if ( ! is_super_admin() ) {
				echo "<p>" . __( 'Nice Try...', 'psts' ) . "</p>"; //If accessed properly, this message doesn't appear.
				return false;
			}

			// Might move this to a controller, not sure if needed yet.
			ProSites_Model_Gateways::process_form();

			?>
			<form method="post" action="">
				<?php

				$page_header_options = array(
					'title'       => __( 'Pro Sites Gateway Settings', 'psts' ),
					'desc'        => __( '', 'psts' ),
					'page_header' => true,
				);

				$options = array(
					'header_save_button'  => true,
					'section_save_button' => true,
					'nonce_name'          => 'psts_gateways',
					'button_name'         => 'gateways',
				);

				ProSites_Helper_Tabs_Gateways::render( get_class(), $page_header_options, $options );

				?>

			</form>
			<?php

		}

		/**
		 * Multi-Gateway Preferences
		 */
		public static function render_tab_gateway_prefs() {
			global $psts;
			$active_count = 0;

			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Gateways::get_active_tab() );
			?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php _e( 'Active gateways', 'psts' ) ?></th>
					<td>
						<?php
							$active_gateways = (array) $psts->get_setting('gateways_enabled');
							$active_count = count( $active_gateways );
						?>
						<?php echo esc_html( $active_count ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e( 'Primary gateway', 'psts' ); ?></th>
					<td>
						<select name="psts[gateway_pref_primary]" class="chosen">
							<?php
							$setting = 'gateway_pref_primary';
							echo self::gateway_input_options( $active_gateways, $setting );
							?>
						</select><br />
						<?php echo '<span>' . __( 'Checkout label', 'psts') . '</span>'; ?>
						<input type="text" name="psts[checkout_gateway_primary_label]" value="<?php echo $psts->get_setting( 'checkout_gateway_primary_label', __( 'Payment', 'psts' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e( 'Secondary gateway', 'psts' ); ?></th>
					<td>
						<select name="psts[gateway_pref_secondary]" class="chosen">
							<?php
							$setting = 'gateway_pref_secondary';
							echo self::gateway_input_options( $active_gateways, $setting, true );
							?>
						</select><br />
						<?php echo '<span>' . __( 'Checkout label', 'psts') . '</span>'; ?>
						<input type="text" name="psts[checkout_gateway_secondary_label]" value="<?php echo $psts->get_setting( 'checkout_gateway_secondary_label', __( 'Alternate Payment', 'psts' ) ); ?>" />
					</td>
				</tr>
				<?php
					if( $active_count > 2 && in_array( 'ProSites_Gateway_Manual', $active_gateways ) ) {
						$manual_checked = $psts->get_setting( 'gateway_pref_use_manual' );
						$value = isset( $manual_checked ) && 'on' == $manual_checked ? 'on' : 'off';
					?>
						<tr>
							<th scope="row"><?php _e( 'Manual Gateway preference', 'psts' ); ?></th>
							<td>
								<input type="checkbox" name="psts[gateway_pref_use_manual]" <?php checked( $manual_checked, 'on'  ); ?> />
								<?php esc_html_e( 'Use the Manual Gateway as a third option.', 'psts' ); ?><br />
								<?php echo '<span>' . __( 'Checkout label', 'psts') . '</span>'; ?>
								<input type="text" name="psts[checkout_gateway_manual_label]" value="<?php echo $psts->get_setting( 'checkout_gateway_manual_label', __( 'Offline Payment', 'psts' ) ); ?>" />
							</td>
						</tr>
					<?php
					}
				?>
			</table>
			<?php

		}

		/**
		 * 2Checkout
		 *
		 * @return string
		 */
		public static function render_tab_twocheckout() {
			global $psts;

			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Gateways::get_active_tab() );

			$class_name = 'ProSites_Gateway_2Checkout';
			$active_gateways = (array) $psts->get_setting('gateways_enabled');
			$checked = in_array( $class_name, $active_gateways ) ? 'on' : 'off';

			?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php _e( 'Enable Gateway', 'psts' ) ?></th>
					<td>
						<input type="hidden" name="gateway" value="<?php echo esc_attr( $class_name ); ?>" />
						<input type="checkbox" name="gateway_active" value="1" <?php checked( $checked, 'on' ); ?> />
					</td>
				</tr>
			</table>
			<?php
			$gateway = new ProSites_Gateway_2Checkout();
			echo $gateway->settings();

		}

		/**
		 * PayPal Pro/Express
		 *
		 * @return string
		 */
		public static function render_tab_paypal() {
			global $psts;

			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Gateways::get_active_tab() );
			$class_name = 'ProSites_Gateway_PayPalExpressPro';
			$active_gateways = (array) $psts->get_setting('gateways_enabled');
			$checked = in_array( $class_name, $active_gateways ) ? 'on' : 'off';

			?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php _e( 'Enable Gateway', 'psts' ) ?></th>
					<td>
						<input type="hidden" name="gateway" value="<?php echo esc_attr( $class_name ); ?>" />
						<input type="checkbox" name="gateway_active" value="1" <?php checked( $checked, 'on' ); ?> />
					</td>
				</tr>
			</table>
			<?php
			$gateway = new ProSites_Gateway_PayPalExpressPro();
			echo $gateway->settings();

		}

		/**
		 * Stripe
		 *
		 * @return string
		 */
		public static function render_tab_stripe() {
			global $psts;

			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Gateways::get_active_tab() );
			$class_name = 'ProSites_Gateway_Stripe';
			$active_gateways = (array) $psts->get_setting('gateways_enabled');
			$checked = in_array( $class_name, $active_gateways ) ? 'on' : 'off';

			?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php _e( 'Enable Gateway', 'psts' ) ?></th>
					<td>
						<input type="hidden" name="gateway" value="<?php echo esc_attr( $class_name ); ?>" />
						<input type="checkbox" name="gateway_active" value="1" <?php checked( $checked, 'on' ); ?> />
					</td>
				</tr>
			</table>
			<?php
			$gateway = new ProSites_Gateway_Stripe();
			echo $gateway->settings();

		}

		/**
		 * Manual Payments
		 *
		 * @return string
		 */
		public static function render_tab_manual() {
			global $psts;

			ProSites_Helper_Settings::settings_header( ProSites_Helper_Tabs_Gateways::get_active_tab() );
			$class_name = 'ProSites_Gateway_Manual';
			$active_gateways = (array) $psts->get_setting('gateways_enabled');
			$checked = in_array( $class_name, $active_gateways ) ? 'on' : 'off';

			?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php _e( 'Enable Gateway', 'psts' ) ?></th>
					<td>
						<input type="hidden" name="gateway" value="<?php echo esc_attr( $class_name ); ?>" />
						<input type="checkbox" name="gateway_active" value="1" <?php checked( $checked, 'on' ); ?> />
					</td>
				</tr>
			</table>
			<?php
			$gateway = new ProSites_Gateway_Manual();
			echo $gateway->settings();

		}

		public static function gateway_input_options( $active_gateways, $setting, $allow_none = false ) {
			global $psts;

			$names = array();
			foreach( $active_gateways as $gateway ) {
				$name = call_user_func( $gateway . '::get_name' );
				$names = array_merge( $names, $name );
			}
			ksort( $names );

			// Make sure 'Manual' is last.
			if( isset( $names['manual'] ) ) {
				$temp = array( 'manual' => $names['manual'] );
				unset( $names['manual'] );
				$names = array_merge( $names, $temp );
			}

			// Give a 'None' value if required
			if( $allow_none ) {
				$names = array_merge( array( 'none' => 'None' ), $names );
			}

			// And if its empty...
			if( empty( $names ) || ( $allow_none && 1 == count( $names ) ) ) {
				$names = array( 'not_enabled' => __( 'No gateways enabled' ) );
				$default_value = 'not_enabled';
			}

			reset( $names );
			$default_value = key( $names );

			$saved_setting = $psts->get_setting( $setting );
			$saved_setting = null !== $saved_setting ? $saved_setting : '';

			foreach( $names as $key => $value ) {
			?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $saved_setting, $key ); ?>><?php echo esc_html( $value ); ?></option>
			<?php
			}

		}

	}
}