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
				$gateway_class   = isset( $_POST['gateway'] ) ? sanitize_text_field( $_POST['gateway'] ) : "";
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

				if( empty( $gateway_class ) ) {
					$post_settings = (array) $_POST['psts'];
					if( ! isset( $post_settings['gateway_pref_use_manual'] ) ) {
						$old_settings['gateway_pref_use_manual'] = 'off';
					}
				}

				$settings     = array_merge( $old_settings, apply_filters( 'psts_settings_filter', $_POST['psts'], $gateway_class ) );
				update_site_option( 'psts_settings', $settings );

				do_action( 'update_site_option_psts_levels' );
				do_action( 'psts_settings_process', $gateway_class );
				do_action( 'supporter_settings_process' ); //deprecated

				echo '<div id="message" class="updated fade"><p>' . __( 'Gateways Saved!', 'psts' ) . '</p></div>';

			}


		}

	}

}