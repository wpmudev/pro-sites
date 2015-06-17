<?php

if ( ! class_exists( 'ProSites_Helper_Geolocation' ) ) {

	class ProSites_Helper_Geolocation {

		private static $EU_countries = array(
			'AT' => array( 'Austria', 'AUT' ),
			'BE' => array( 'Belgium', 'BEL' ),
			'BG' => array( 'Bulgaria', 'BGR' ),
			'CY' => array( 'Cyprus', 'CYP' ),
			'CZ' => array( 'Czech Republic', 'CZE' ),
			'DE' => array( 'Germany', 'DEU' ),
			'DK' => array( 'Denmark', 'DNK' ),
			'ES' => array( 'Spain', 'ESP' ),
			'EE' => array( 'Estonia', 'EST' ),
			'FI' => array( 'Finland', 'FIN' ),
			'FR' => array( 'France', 'FRA' ),
			'GB' => array( 'United Kingdom', 'GBR' ),
			'GR' => array( 'Greece', 'GRC' ),
			'HR' => array( 'Croatia', 'HRV' ),
			'HU' => array( 'Hungary', 'HUN' ),
			'IE' => array( 'Ireland', 'IRL' ),
			'IT' => array( 'Italy', 'ITA' ),
			'LT' => array( 'Lithuania', 'LTU' ),
			'LU' => array( 'Luxembourg', 'LUX' ),
			'LV' => array( 'Latvia', 'LVA' ),
			'MT' => array( 'Malta', 'MLT' ),
			'NL' => array( 'Netherlands', 'NLD' ),
			'PL' => array( 'Poland', 'POL' ),
			'PT' => array( 'Portugal', 'PRT' ),
			'RO' => array( 'Romania', 'ROU' ),
			'SK' => array( 'Slovakia', 'SVK' ),
			'SI' => array( 'Slovenia', 'SVN' ),
			'SE' => array( 'Sweden', 'SWE' ),
		);

		public static function get_EU_countries() {
			return self::$EU_countries;
		}

		public static function getIPInfo( $ip = false ) {

			if( ! $ip ) {
				$ip = self::_get_IP();
			}
			$geo = false;

			// Attempt GeoLocation data from freegeoip.net
			$response_object = wp_remote_get( 'http://freegeoip.net/json/' . $ip );
			if( $response_object && ! is_wp_error( $response_object ) && 200 == (int) $response_object['response']['code'] ) {
				$geo_obj = json_decode( $response_object['body'] );
				$geo = new stdClass();
				$geo->country_code = $geo_obj->country_code;
				$geo->country_name = $geo_obj->country_name;
				$geo->region = $geo_obj->region_code;
				$geo->locality = $geo_obj->city;
				$geo->latitude = $geo_obj->latitude;
				$geo->longitude = $geo_obj->longitude;
				$geo->is_EU = self::is_EU( $geo_obj->country_code );
				$geo->ip = $ip;
			} else {
				//Use DataScienceToolKit as a backup
				$response_object = wp_remote_get( 'http://www.datasciencetoolkit.org/ip2coordinates/' . $ip );
				if( $response_object && ! is_wp_error( $response_object ) && 200 == (int) $response_object['response']['code'] ) {
					$geo_obj = json_decode( $response_object['body'] );
					$geo = new stdClass();
					if( !empty( $geo_obj->$ip ) ) {
						$geo->country_code = $geo_obj->$ip->country_code;
						$geo->country_name = $geo_obj->$ip->country_name;
						$geo->region       = $geo_obj->$ip->region;
						$geo->locality     = $geo_obj->$ip->locality;
						$geo->latitude     = $geo_obj->$ip->latitude;
						$geo->longitude    = $geo_obj->$ip->longitude;
						$geo->is_EU        = self::is_EU( $geo_obj->$ip->country_code );
					}
					$geo->ip = $ip;
				}
			}

			return $geo;
		}

		private static function _get_IP() {
			return getenv('HTTP_CLIENT_IP')?:
					getenv('HTTP_X_FORWARDED_FOR')?:
					getenv('HTTP_X_FORWARDED')?:
					getenv('HTTP_FORWARDED_FOR')?:
					getenv('HTTP_FORWARDED')?:
					getenv('REMOTE_ADDR');
		}

		public static function is_EU( $country_code ) {
			return in_array( $country_code, array_keys( self::$EU_countries ) );
		}

		/**
		 * Initialize GeoLocation based on IP and store it in a session.
		 */
		public static function init_geolocation() {
			$geodata = ProSites_Helper_Session::session( 'geodata' );
			if( empty( $geodata ) ) {
				$geodata = ProSites_Helper_Geolocation::getIPInfo();
				ProSites_Helper_Session::session( 'geodata', $geodata );
			}
			return $geodata;
		}

		/**
		 * Get geo data (or force init and return geo data).
		 */
		public static function get_geodata() {
			$geodata = ProSites_Helper_Session::session( 'geodata' );
			if( empty( $geodata ) ) {
				$geodata = self::init_geolocation();
			}
			return $geodata;
		}

	}
}
