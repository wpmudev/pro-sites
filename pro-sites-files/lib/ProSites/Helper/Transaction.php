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

			// Allow hooks
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


	}

}