<?php

if ( ! class_exists( 'ProSites_Model_Data' ) ) {

	class ProSites_Model_Data {

		public static $currencies = array();

		public static function load_currencies( $gateway_slug, $gateway ) {
			$slug = $gateway_slug;
			$class = $gateway['class'];

			$currencies = array();
			if( method_exists( $class, 'get_supported_currencies' ) ) {
				$currencies = call_user_func( $class . '::get_supported_currencies' );
			}
			foreach( $currencies as $key => $currency ) {
				$c_keys = array_keys(self::$currencies);
				if( ! in_array( $key, $c_keys ) ) {
					self::$currencies[ $key ] = array(
						'name' => $currency[0],
						'symbol' => $currency[1],
						'supported_by' => array( $slug ),
					);
				} else {
					self::$currencies[ $key ]['supported_by'][] = $slug;
				}
			}
			asort( self::$currencies );
		}

		public static function add_currency( $gateway, $currency, $symbol ) {


		}



	}

}