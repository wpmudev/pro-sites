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
				$used = array();
				foreach ( $object->evidence as $evidence ) {
					if ( $evidence->used ) {
						$used[] = array(
							'country_code' => $evidence->resolved_country_code,
							'value'        => $evidence->evidence_value,
							'type'         => $evidence->evidence_type,
						);
					}
				}

				return json_encode( $used );

			}

			return $evidence_string;
		}

		public static function record_transaction( $transaction, $date, $line_items, $total, $sub_total, $tax_amount, $tax_percent, $currency, $evidence ) {
			//global $psts;
			//
			//$token  = $psts->get_setting( 'taxamo_private_token' );
			//
			//$taxamo = new Taxamo( new APIClient( $token, 'https://api.taxamo.com' ) );
			//
			//$item_array = array();
			//foreach( $line_items as $item ) {
			//	//error_log( print_r( $item, true ) );
			//
			//	$new_item = new Input_transaction_line();
			//	$new_item->amount = $item->amount;
			//	$new_item->line_key = $item->line_key;
			//	$new_item->custom_id = $item->custom_id;
			//
			//	$item_array[] = $new_item;
			//
			//}
			//
			//$transaction = new Input_transaction();
			//$transaction->currency_code = $currency;
			//
			////propagate customer's IP address when calling API server-side
			//$transaction->buyer_ip = $_SERVER['REMOTE_ADDR'];
			//$transaction->billing_country_code = $evidence[0]->country_code;
			//$transaction->force_country_code = $evidence[1]->country_code;
			//$transaction->transaction_lines = $item_array;
			//
			//$resp = $taxamo->createTransaction(array('transaction' => $transaction));

		}

	}
}