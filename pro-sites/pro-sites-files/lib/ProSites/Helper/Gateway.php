<?php

if ( ! class_exists( 'ProSites_Helper_Gateway' ) ) {

	class ProSites_Helper_Gateway {

		public static function get_gateways() {
			global $psts;

			$gateways        = array();
			$active_gateways = (array) $psts->get_setting( 'gateways_enabled' );

			//Get the enabled Modules
			$modules = $psts->get_setting( 'modules_enabled' );
			$modules = ! empty( $modules ) ? $modules : array();

			//Check if Bulk Upgrade is enabled
			if ( in_array( 'ProSites_Module_BulkUpgrades', $modules ) ) {
				$active_gateways[] = "ProSites_Module_BulkUpgrades";
			}

			// Force manual if no gateways are active
			if ( empty( $active_gateways ) ) {
				$active_gateways = array( 'ProSites_Gateway_Manual' );
			}

			foreach ( $active_gateways as $active_gateway ) {
				if ( method_exists( $active_gateway, 'get_name' ) ) {
					$name = call_user_func( $active_gateway . '::get_name' );

					//Used for displaying info o site management page
					if ( $active_gateway == "ProSites_Module_BulkUpgrades" ) {
						$name = array( "bulk upgrade" => $name );
					}
					$gateways[ key( $name ) ] = array(
						'name'  => array_pop( $name ),
						'class' => $active_gateway
					);
				}
			}

			return $gateways;
		}

		/**
		 * 3.4 compatibility
		 */
		public static function convert_legacy( $gateway ) {

			$old_values = array( 'paypal' => 'paypal express/pro', 'paypal2' => 'bulk upgrades' );

			if ( false !== $key = array_search( strtolower( $gateway ), $old_values ) ) {
				$gateway = str_replace( array( 'paypal2' ), 'paypal', $key );  // fix legacy paypal
				return $gateway;
			} else {
				return strtolower( $gateway );
			}

		}

		public static function get_nice_name( $gateway_key ) {
			$gateway_key = self::convert_legacy( $gateway_key ); //picking up some legacy
			$gateways    = self::get_gateways();
			$keys        = array_keys( $gateways );

			if ( in_array( $gateway_key, $keys ) ) {
				return $gateways[ $gateway_key ]['name'];
			} else {
				return 'trial' == $gateway_key ? __( 'Trial', 'psts' ) : $gateway_key;
			}
		}

		public static function get_nice_name_from_class( $classname ) {
			$gateways = self::get_gateways();

			$nicename = '';
			foreach ( $gateways as $gateway ) {
				if ( $gateway['class'] == $classname ) {
					$nicename = $gateway['name'];
				}
			}

			return $nicename;
		}

		public static function is_only_active( $gateway_key ) {
			$gateways     = self::get_gateways();
			$gateway_keys = array_keys( $gateways );

			return in_array( $gateway_key, $gateway_keys ) && 1 == count( $gateway_keys );
		}

		public static function is_last_gateway_used( $blog_id, $gateway_key ) {
			$last_gateway = ProSites_Helper_ProSite::last_gateway( $blog_id );

			if ( ! empty( $last_gateway ) && $last_gateway == $gateway_key ) {
				return true;
			} else {
				return false;
			}
		}

		public static function load_gateway_currencies() {
			$gateways = ProSites_Helper_Gateway::get_gateways();

			foreach ( $gateways as $key => $gateway ) {
				ProSites_Model_Data::load_currencies( $key, $gateway );
			}

		}

		public static function supports_currency( $currency_code, $gateway_slug ) {
			$currencies = ProSites_Model_Data::$currencies;
			$found      = false;

			$c_keys = array_keys( $currencies );
			if ( in_array( $currency_code, $c_keys ) ) {
				$found = in_array( $gateway_slug, $currencies[ $currency_code ]['supported_by'] );
			}

			return $found;
		}

	}
}