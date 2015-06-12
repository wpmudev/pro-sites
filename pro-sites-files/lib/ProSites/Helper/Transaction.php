<?php
/**
 * @copyright Incsub (http://incsub.com/)
 *
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,
 * MA 02110-1301 USA
 *
 */

if ( ! class_exists( 'ProSites_Helper_Transaction' ) ) {

	class ProSites_Helper_Transaction {

		public static function record( $transaction ) {
			// Record locally...
			self::record_to_database( $transaction );

			// Allow hooks (used for taxamo and for receipt)
			do_action( 'prosites_transaction_record', $transaction );
		}

		public static function object_from_data( $data, $gateway ) {

			$object = new stdClass();
			return apply_filters( 'prosites_transaction_object_create', $object, $data, $gateway );

		}

		public static function evidence_from_json( $json ) {

			$data = json_decode( $json );

			// Wrong JSON, bail
			if ( ! $data && ! isset( $data->tax_type ) ) {
				return null;
			}

			$evidence = new stdClass();

			// Hook it in case we add different TAX services
			return apply_filters( 'prosites_tax_evidence_from_json_data', $evidence, $data );

		}

		public static function country_code_from_data( $json, $object ) {

			$data = json_decode( $json );

			// Wrong JSON, bail
			if ( ! $data && ! isset( $data->tax_type ) ) {
				return null;
			}

			// Hook it in case we add different TAX services
			return apply_filters( 'prosites_tax_country_from_data', '', $data, $object );
		}

		public static function country_ip_from_data( $json, $object ) {

			$data = json_decode( $json );

			// Wrong JSON, bail
			if ( ! $data && ! isset( $data->tax_type ) ) {
				return null;
			}

			// Hook it in case we add different TAX services
			return apply_filters( 'prosites_tax_ip_from_data', '', $data, $object );
		}

		public static function record_to_database( $transaction ) {

			global $wpdb;

			// Prepare for caluclating if we need to
			$tax_rate = isset( $transaction->tax_percent ) ? $transaction->tax_percent : 0;
			$total = isset( $transaction->total ) ? $transaction->total : false;
			$subtotal = isset( $transaction->subtotal ) ? $transaction->subtotal : false;
			$tax = isset( $transaction->tax ) ? $transaction->tax : false;

			// Calculate!
			if( false === $total && $subtotal ) {
				$total = ( $subtotal * $tax_rate ) + $subtotal;
			}
			if( false === $subtotal && $total ) {
				// Explanation
				// $total = ( $subtotal * $tax_rate ) + $subtotal;
				// $total = $subtotal * ( $tax_rate + 1 ); // substitution
				// $total / ( $tax_rate + 1 ) = $subtotal
				$subtotal = $total / ( $tax_rate + 1 );
			}
			if( false === $tax && $total && $subtotal ) {
				$tax = $total - $subtotal;
			}

			$sql = $wpdb->prepare(
				"INSERT INTO {$wpdb->base_prefix}pro_sites_transactions(transaction_id, transaction_date, items, total, sub_total, tax_amount, tax_percentage, country, currency, meta)
				 VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s )",
				$transaction->invoice_number,
				$transaction->invoice_date,
				maybe_serialize( $transaction->transaction_lines ),
				$total,
				$subtotal,
				$tax,
				$tax_rate,
				$transaction->billing_country_code,
				$transaction->currency_code,
				maybe_serialize( $transaction->evidence )
			);

			// ... and record.
			$wpdb->query( $sql );

		}


	}

}