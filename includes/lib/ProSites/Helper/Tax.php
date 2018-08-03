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

if ( ! class_exists( 'ProSites_Helper_Tax' ) ) {

	class ProSites_Helper_Tax {


		public static function init_tax() {

			do_action( 'prosites_tax_init' );

			self::setup_hooks();
		}

		public static function setup_hooks() {

			// @todo split out taxamo and make it conditional
			add_action( 'wp_enqueue_scripts', array( 'ProSites_Helper_Tax', 'enqueue_tax_scripts' ) );
			add_filter( 'prosites_render_checkout_page', array( 'ProSites_Helper_Tax', 'append_tax_api' ), 10, 3 );
			add_filter( 'prosites_post_pricing_table_content', array( 'ProSites_Helper_Tax', 'tax_checkout_notice' ) );
			//add_filter( 'prosites_post_pricing_table_content', array( 'ProSites_Helper_Tax', 'eu_tax_warning_notice' ) );

			// Hook IMSI helper
			ProSites_Helper_IMSI::init();
			add_action( 'wp_ajax_validate_imsi', array( 'ProSites_Helper_IMSI', 'validate_imsi_ajax' ) );
			add_action( 'wp_ajax_nopriv_validate_imsi', array( 'ProSites_Helper_IMSI', 'validate_imsi_ajax' ) );

			do_action( 'prosites_tax_hooks_loaded' );
		}

		public static function append_tax_api( $content, $blog_id, $domain ) {

			global $psts;

			$token = $psts->get_setting( 'taxamo_token' );
			$taxamo_enabled = $psts->get_setting( 'taxamo_status', 0 );
			if( ! empty( $token ) && ! empty( $taxamo_enabled ) ) {
				// Move this to its own class later
				$taxamo = '<script type="text/javascript" src="https://api.taxamo.com/js/v1/taxamo.all.js"></script>';
				$taxamo .= '<script type="text/javascript">
					Taxamo.initialize(\'' . $token . '\');
					tokenOK = false;
			        Taxamo.verifyToken(function(data){ tokenOK = data.tokenOK; });
			        //Taxamo.setBillingCountry(\'00\');
			        //Taxamo.setFirstLoad( true );
			        Taxamo.detectCountry();
				</script>';
				$content = $content . $taxamo;
			}

			return apply_filters( 'prosites_checkout_append_tax', $content );
		}

		public static function enqueue_tax_scripts() {
			global $psts;

			wp_enqueue_script( 'psts-tax', $psts->plugin_url . 'js/tax.js', array( 'jquery' ), $psts->version );

			$translation_array = apply_filters( 'prosites_tax_script_translations', array(
				'taxamo_missmatch' => __( 'EU VAT: Your location evidence is not matching. Additional evidence required. If you are travelling in another country or using a VPN, please provide as much information as possible and ensure that it is accurate.', 'psts' ),
				'taxamo_imsi_short' => __( 'Your IMSI number for your SIM card must be 15 characters long.', 'psts' ),
				'taxamo_imsi_invalid' => __( 'The IMSI number you provided is invalid for a EU country.', 'psts' ),
				'taxamo_overlay_non_eu' => __( 'Resident of non-EU country.', 'psts' ),
				'taxamo_overlay_detected' => __( 'Detected VAT country:', 'psts' ),
				'taxamo_overlay_learn_more' => __( 'Learn more.', 'psts' ),
				'taxamo_overlay_country_set' => __( 'VAT country is set to:', 'psts' ),
			) );

			wp_localize_script( 'psts-tax', 'psts_tax', $translation_array );
		}

		public static function tax_checkout_notice( $content ) {

			global $psts;

			$token = $psts->get_setting( 'taxamo_token' );
			$taxamo_enabled = $psts->get_setting( 'taxamo_status', 0 );
			$use_taxamo = false;
			if( ! empty( $token ) && ! empty( $taxamo_enabled ) ) {
				$use_taxamo = true;
			}

			$new_content = $content . '<div class="tax-checkout-notice hidden">' .
				sprintf( __( 'Note: Amounts displayed includes taxes of %s%%.', 'psts' ), '<span class="tax-percentage"></span>' ) .
				'</div>';

			if( $use_taxamo ) {
				$new_content .= '<div class="tax-checkout-evidence hidden">' .
	                __( 'EU VAT: Your location evidence is not matching. Additional evidence required. If you are travelling in another country or using a VPN, please provide as much information as possible and ensure that it is accurate.', 'psts' ) .
	                '<br />' .
	                sprintf( '<strong>%s</strong>%s', __( 'SIM card IMSI number', 'psts'), __( '(available from carrier upon request)', 'psts' ) ) .
	                '<br /><input type="textbox" name="tax-evidence-imsi" />' .
	                //sprintf( __( 'VAT number (if available)', 'psts' ) ) .
	                //'<br /><input type="textbox" name="tax-evidence-vatnumber" /><br />' .
	                '<input type="button" name="tax-evidence-update" value="' . __( 'Update Evidence', 'psts' ) . '" />' .
	                '</div>';
			}

			return $new_content;
		}

		public static function eu_tax_warning_notice( $content ) {

			global $psts;

			$token = $psts->get_setting( 'taxamo_token' );
			$taxamo_enabled = $psts->get_setting( 'taxamo_status', 0 );
			$geodata = ProSites_Helper_Geolocation::get_geodata();
			if( ( empty( $token ) || empty( $taxamo_enabled ) ) && $geodata->is_EU ) {
				return $content . '<div class="tax-checkout-warning">' .
				       __( 'It appears that you are in an European Union country. Unfortunately we do not currently support sites for EU countries.', 'psts' ).
				       '</div>';

			}

			return $content;
		}

		public static function get_tax_object() {

			$evidence = ! empty( $_POST['tax-evidence'] ) ? json_decode( str_replace( '\"', '"', $_POST['tax-evidence'] ) ) : '';
			$type     = ! empty( $_POST['tax-type'] ) ? sanitize_text_field( $_POST['tax-type'] ) : '';
			$country  = ! empty( $_POST['tax-country'] ) ? sanitize_text_field( $_POST['tax-country'] ) : '';

			$obj = new stdClass();
			$obj->country = $country;
			$obj->type = $type;
			$obj->tax_rate = false;
			$obj->apply_tax = false;
			$obj->ip = false;
			$obj->evidence = $evidence;

			$tax_object = apply_filters( 'prosites_get_tax_object', $obj );

			return $tax_object;
		}

		public static function get_evidence_string( $object ) {

			return apply_filters( 'prosites_get_tax_evidence_string', json_encode( $object-> evidence ), $object );

		}

	}

}