<?php

if ( ! class_exists( 'ProSites_Model_Settings' ) ) {

	class ProSites_Model_Settings {

		public static function process_form() {
			//process form
			if ( isset( $_POST['submit_settings'] ) || isset( $_POST['submit_settings_section'] ) || isset( $_POST['submit_settings_header'] ) ) {
				//check nonce
				check_admin_referer( 'psts_settings' );

				//strip slashes from all inputs
				$_POST['psts'] = isset( $_POST['psts'] ) ? stripslashes_deep( $_POST['psts'] ) : array();

				$active_tab = sanitize_text_field( $_POST['active_tab'] );

				switch( $active_tab ) {
					case 'general':
						$_POST['psts']['hide_adminmenu']          = isset( $_POST['psts']['hide_adminmenu'] ) ? $_POST['psts']['hide_adminmenu'] : 0; //handle checkbox
						$_POST['psts']['hide_adminbar']           = isset( $_POST['psts']['hide_adminbar'] ) ? $_POST['psts']['hide_adminbar'] : 0; //handle checkbox
						$_POST['psts']['hide_adminbar_super']     = isset( $_POST['psts']['hide_adminbar_super'] ) ? $_POST['psts']['hide_adminbar_super'] : 0; //handle checkbox
						$_POST['psts']['show_signup']             = isset( $_POST['psts']['show_signup'] ) ? $_POST['psts']['show_signup'] : 0; //handle checkbox
						$_POST['psts']['free_signup']             = isset( $_POST['psts']['free_signup'] ) ? $_POST['psts']['free_signup'] : 0; //handle checkbox
						$_POST['psts']['multiple_signup']         = isset( $_POST['psts']['multiple_signup'] ) ? $_POST['psts']['multiple_signup'] : 0; //handle checkbox
						$_POST['psts']['apply_setup_fee_upgrade'] = isset( $_POST['psts']['apply_setup_fee_upgrade'] ) ? $_POST['psts']['apply_setup_fee_upgrade'] : 0; //handle checkbox
						$_POST['psts']['checkout_roles']          = isset( $_POST['psts']['checkout_roles'] ) ? $_POST['psts']['checkout_roles'] : ''; //handle checkbox
						break;
					case 'taxes':
						$_POST['psts']['taxamo_status']           = isset( $_POST['psts']['taxamo_status'] ) ? $_POST['psts']['taxamo_status'] : 0; //handle checkbox
						break;
				}

				$_POST['psts']['pt_sortthemes']           = isset( $_POST['psts']['pt_sortthemes'] ) ? $_POST['psts']['pt_sortthemes'] : ''; //handle checkbox

				//merge settings
				$old_settings = get_site_option( 'psts_settings' );

				// update levels?
				$update_gateway_levels = false;
				if( isset( $_POST['psts']['currency'] ) ) {
					$new_currency = sanitize_text_field( $_POST['psts']['currency'] );
					if( strtolower( $old_settings['currency'] ) != strtolower( $new_currency ) ) {
						$update_gateway_levels = true;
					}
				}

				$settings     = array_merge( $old_settings, apply_filters( 'psts_settings_filter', $_POST['psts'], $active_tab ) );
				update_site_option( 'psts_settings', $settings );

				if( $update_gateway_levels ) {
					do_action( 'update_site_option_psts_levels' );
				}

				do_action( 'psts_settings_process', $active_tab );
				do_action( 'supporter_settings_process' ); //deprecated

				//create a checkout page if not existing
				self::_create_checkout_page();

				echo '<div id="message" class="updated fade"><p>' . __( 'Settings Saved!', 'psts' ) . '</p></div>';
			}
		}

		private static function _create_checkout_page() {
			// Move logic here later...
			global $psts;
			$psts->create_checkout_page();
		}


	}

}