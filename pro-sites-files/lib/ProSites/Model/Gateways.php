<?php

if ( ! class_exists( 'ProSites_Model_Gateways' ) ) {

	class ProSites_Model_Gateways {

		public static function process_form() {
			global $psts, $psts_gateways;
			//process form
			if ( isset( $_POST['submit_gateways'] ) || isset( $_POST['submit_gateways_section'] ) || isset( $_POST['submit_gateways_header'] ) ) {

				//check nonce
				check_admin_referer( 'psts_gateways' );
				//which gateway am I editing?
				$gateway_class   = sanitize_text_field( $_POST['gateway'] );
				$active_gateways = $active_gateways = (array) $psts->get_setting( 'gateways_enabled' );

				if ( in_array( $gateway_class, $active_gateways ) && ! isset( $_POST['gateway_active'] ) ) {
					foreach ( $active_gateways as $key => $value ) {
						if ( $value == $gateway_class ) {
							unset( $active_gateways[ $key ] );
						}
					}
				} else if ( ! in_array( $gateway_class, $active_gateways ) && isset( $_POST['gateway_active'] ) ){
					$active_gateways[] = $gateway_class;
				}

				$active_gateways = array_values( $active_gateways );
				$old_settings = get_site_option( 'psts_settings' );
				$old_settings['gateways_enabled'] = $active_gateways;
				$settings     = array_merge( $old_settings, apply_filters( 'psts_settings_filter', $_POST['psts'] ) );
				update_site_option( 'psts_settings', $settings );

				do_action( 'psts_settings_process' );
				do_action( 'supporter_settings_process' ); //depreciated

				echo '<div id="message" class="updated fade"><p>' . __( 'Settings Saved!', 'psts' ) . '</p></div>';

			}


		}

	}

}