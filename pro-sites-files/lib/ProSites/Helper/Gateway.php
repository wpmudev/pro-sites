<?php

if ( ! class_exists( 'ProSites_Helper_Gateway' ) ) {

	class ProSites_Helper_Gateway {

		public static function get_gateways() {
			global $psts;

			$gateways = array();
			$active_gateways = (array) $psts->get_setting( 'gateways_enabled' );
			foreach( $active_gateways as $active_gateway ) {
				if( method_exists( $active_gateway, 'get_name' ) ) {
					$name = call_user_func( $active_gateway . '::get_name' );
					$gateways[ key( $name ) ] = array(
						'name'  => array_pop( $name ),
						'class' => $active_gateway
					);
				}
			}
			return $gateways;
		}

		public static function get_nice_name( $gateway_key ) {
			$gateway_key = strtolower( $gateway_key ); //picking up some legacy
			$gateways = self::get_gateways();
			$keys = array_keys( $gateways );
			if( in_array( $gateway_key, $keys ) ) {
				return $gateways[$gateway_key]['name'];
			} else {
				return 'trial' == $gateway_key ? __('Trial', 'psts') : $gateway_key;
			}
		}
		
		public static function is_only_active( $gateway_key ) {
			$gateways = self::get_gateways();
			$gateway_keys = array_keys( $gateways );

			return in_array( $gateway_key, $gateway_keys ) && 1 == count( $gateway_keys );
		}

	}
}