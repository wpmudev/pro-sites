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
		 * 2Checkout
		 *
		 * @return string
		 */
		public static function render_tab_twocheckout() {
			global $psts;
			ob_start();
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

			return ob_get_clean();
		}

		/**
		 * PayPal Pro/Express
		 *
		 * @return string
		 */
		public static function render_tab_paypal() {
			global $psts;
			ob_start();
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

			return ob_get_clean();
		}

		/**
		 * Stripe
		 *
		 * @return string
		 */
		public static function render_tab_stripe() {
			global $psts;
			ob_start();
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

			return ob_get_clean();
		}

		/**
		 * Manual Payments
		 *
		 * @return string
		 */
		public static function render_tab_manual() {
			global $psts;
			ob_start();
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

			return ob_get_clean();
		}

	}
}