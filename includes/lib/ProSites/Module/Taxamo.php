<?php

if ( ! class_exists( 'ProSites_Module_Taxamo' ) ) {

	class ProSites_Module_Taxamo {

		// Module name for registering
		public static function get_name() {
			return __( 'Taxamo Integration', 'psts' );
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

			if ( self::is_active() ) {
				// Include the library
				if ( ! class_exists( 'Taxamo' ) ) {
					require( dirname( __FILE__ ) . '/Taxamo/lib/Taxamo.php' );
				}
			}

			add_filter( 'prosite_checkout_tax_apply', array( get_class(), 'apply_tax' ), 10, 4 );
			add_filter( 'prosite_checkout_tax_percentage', array( get_class(), 'tax_percentage' ), 10, 4 );

			add_filter( 'prosites_get_tax_object', array( get_class(), 'get_tax_object' ) );
			add_filter( 'prosites_get_tax_evidence_string', array( get_class(), 'get_evidence_string' ), 10, 2 );
			add_filter( 'prosites_tax_evidence_from_json_data', array(
				get_class(),
				'get_evidence_from_json_data'
			), 10, 2 );
			add_filter( 'prosites_tax_country_from_data', array( get_class(), 'get_country_from_data' ), 10, 3 );
			add_filter( 'prosites_tax_ip_from_data', array( get_class(), 'get_ip_from_data' ), 10, 3 );

			add_action( 'prosites_transaction_record', array( get_class(), 'record_transaction' ) );

		}


		public static function get_tax_object( $object ) {

			if ( 'taxamo' == $object->type ) {
				$object->tax_rate  = $object->evidence->tax_percentage / 100; // so that we can just multiply
				$object->apply_tax = $object->evidence->tax_supported;
				$object->ip        = $object->evidence->buyer_ip;
				$object->evidence  = $object->evidence->evidence;
			}

			return $object;
		}

		public static function get_evidence_string( $evidence_string, $object ) {

			if ( 'taxamo' == $object->type ) {
				$pieces = array();
				foreach ( $object->evidence as $evidence ) {
					//if ( $evidence->used ) {
					$pieces[] = $evidence;
					//}
				}

				$evidence_string = json_encode( array(
					'tax_type' => $object->type,
					'evidence' => $pieces,
					'tax_rate' => $object->tax_rate,
				) );
			}

			return $evidence_string;
		}

		public static function get_evidence_from_json_data( $evidence, $data ) {

			if ( ! isset( $data->tax_type ) || 'taxamo' != $data->tax_type ) {
				return $evidence;
			}

			foreach ( $data->evidence as $piece ) {

				$obj_key = str_replace( '-', '_', $piece->evidence_type );

				$evidence->$obj_key                        = new stdClass();
				$evidence->$obj_key->used                  = $piece->used;
				$evidence->$obj_key->resolved_country_code = $piece->resolved_country_code;
				$evidence->$obj_key->evidence_type         = $piece->evidence_type;
				$evidence->$obj_key->evidence_value            = $piece->evidence_value;
			}

			return $evidence;

		}

		public static function get_country_from_data( $country_code, $data, $object ) {

			if ( ! isset( $data->tax_type ) || 'taxamo' != $data->tax_type ) {
				return $country_code;
			}

			if ( isset( $object->evidence ) && isset( $object->evidence->by_billing ) ) {
				$country_code = $object->evidence->by_billing->resolved_country_code;
			} else {
				// Don't reinvent the wheel!
				$evidence     = self::get_evidence_from_json_data( new stdClass(), $data );
				$country_code = $evidence->by_billing->resolved_country_code;
			}

			return $country_code;
		}

		public static function get_ip_from_data( $ip, $data, $object ) {

			if ( ! isset( $data->tax_type ) || 'taxamo' != $data->tax_type ) {
				return $ip;
			}

			if ( isset( $object->evidence ) && isset( $object->evidence->by_ip ) ) {
				$ip = $object->evidence->by_ip->evidence_value;
			} else {
				// Don't reinvent the wheel!
				$evidence = self::get_evidence_from_json_data( new stdClass(), $data );
				if( isset( $evidence->by_ip ) ) {
					$ip = $evidence->by_ip->evidence_value;
				}
			}

			return $ip;
		}

		public static function record_transaction( $transaction ) {
			global $psts;

			$token = $psts->get_setting( 'taxamo_private_token' );

			if ( $token && isset( $transaction->billing_country_code ) && ProSites_Helper_Geolocation::is_EU( $transaction->billing_country_code ) ) {

				$taxamo = new Taxamo( new APIClient( $token, 'https://api.taxamo.com' ) );

				// Convert to Taxamo types (because of Swagger lib)
				$t = new Input_transaction();

				// Add easy items (and avoid custom ones)
				$t_types = array_keys( get_object_vars( $t ) );
				foreach( $transaction as $key => $value ) {
					if( ! is_object( $value ) && in_array( $key, $t_types ) ) {
						$t->$key = $value;
					}
				}

				// Convert line items
				$lines = array();
				foreach( $transaction->transaction_lines as $line ) {
					$l = new Input_transaction_line();
					foreach( $line as $key => $value ) {
						if( ! is_object( $value ) ) {
							$l->$key = $value;
						}
					}
					$lines[] = $l;
				}
				$t->transaction_lines = $lines;
				//
				//// Evidence
				$t->evidence = new Evidence();
				foreach( $transaction->evidence as $ek => $ev ) {
					$t->evidence->$ek = new Evidence_schema();
					foreach( $ev as $k => $v ) {
						$t->evidence->$ek->$k = $v;
					}
				}

				$resp = $taxamo->createTransaction( array( 'transaction' => $t ) );
				if( isset( $resp ) && isset( $resp->transaction ) && isset( $resp->transaction->key ) ) {
					$taxamo->confirmTransaction($resp->transaction->key, null);
				}

			}

		}

	}
}