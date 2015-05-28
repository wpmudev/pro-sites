<?php

if( !class_exists( 'ProSites_Module_Taxamo' ) ) {

	class ProSites_Module_Taxamo {

		// Module name for registering
		public static function get_name() {
			return __('Taxamo Integration', 'psts' );
		}

		// Module description for registering
		public static function get_description() {
			return __( 'Integrates Taxamo with your payment gateways.', 'psts' );
		}

		public static function is_active() {
			// @todo Make this a setting
			return true;
		}

		/**
		 * Add hooks
		 */
		public static function init() {

			if( self::is_active() ) {
				// Include the library
				if( ! class_exists( 'Taxamo' ) ) {
					require(dirname(__FILE__) . '/Taxamo/lib/Taxamo.php');
				}
			}

			add_filter( 'prosite_checkout_tax_apply', array( get_class(), 'apply_tax' ), 10, 4 );
			add_filter( 'prosite_checkout_tax_percentage', array( get_class(), 'tax_percentage' ), 10, 4 );

			add_filter( 'prosites_get_tax_object', array( get_class(), 'get_tax_object' ) );
			add_filter( 'prosites_get_tax_evidence_sting', array( get_class(), 'get_evidence_string' ), 10, 2 );


		}

		public static function apply_tax( $apply, $type, $country, $data ) {
			$data = json_decode( $data );
			if( isset( $data->tax_supported ) && 'taxamo' == $type ) {
				return $data->tax_supported;
			}
			return $apply;
		}

		public static function tax_percentage( $percentage, $type, $country, $data ) {
			$data = json_decode( $data );
			if( isset( $data->tax_percentage ) && 'taxamo' == $type ) {
				return $data->tax_percentage;
			}
			return $percentage;
		}

		public static function get_tax_object( $object ) {

			if ( 'taxamo' == $object->type ) {
				$object->tax_rate = $object->evidence->tax_percentage / 100; // so that we can just multiply
				$object->apply_tax = $object->evidence->tax_supported;
				$object->ip = $object->evidence->buyer_ip;
				$object->evidence = $object->evidence->evidence;
			}

			return $object;
		}

		public static function get_evidence_string( $evidence_string, $object ) {

			if( 'taxamo' == $object->type ) {
				$used = array();
				foreach( $object->evidence as $evidence ) {
					if( $evidence->used ) {
						$used[] = array(
							'country_code' => $evidence->resolved_country_code,
							'value' => $evidence->evidence_value,
							'type' => $evidence->evidence_type,
						);
					}
				}
				return json_encode( $used );

			}

			return $evidence_string;
		}

	}
}