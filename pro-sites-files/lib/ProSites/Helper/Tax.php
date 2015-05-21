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

			do_action( 'prosites_tax_hooks_loaded' );
		}

		public static function append_tax_api( $content, $blog_id, $domain ) {

			// Move this to its own class later
			$taxamo = 	'<script type="text/javascript" src="https://api.taxamo.com/js/v1/taxamo.all.js"></script>';
			$taxamo .= '<script type="text/javascript">
				Taxamo.initialize(\'public_test_gm0VCBeZX2VDy2Sh1wX2daKbDBlRu0XZ6ePj0NjxMVA\');
				tokenOK = false;
		        Taxamo.verifyToken(function(data){ tokenOK = data.tokenOK; });
		        if( tokenOK ) {
					Taxamo.setCurrencyCode(\'AUD\');
					//Taxamo.scanPrices(\'.price-plain, .monthly-price-hidden, .savings-price-hidden\', {
					//"priceTemplate": "<div class=\"tax-total\">${totalAmount}</div><div class=\"tax-amount\">${taxAmount}</div><div class=\"tax-rate\">${taxRate}</div><div class=\"tax-base\">${amount}</div>",
					//"noTaxTitle": "", //set titles to false to disable title attribute update
					//"taxTitle": ""});
					//Taxamo.detectButtons();
					Taxamo.detectCountry();
					Taxamo.setBillingCountry(\'AU\');
				}
				</script>';

			$content = $content . $taxamo;

			return apply_filters( 'prosites_checkout_append_tax', $content );
		}

		public static function enqueue_tax_scripts() {
			global $psts;

			wp_enqueue_script( 'psts-tax', $psts->plugin_url . 'js/tax.js', array( 'jquery' ), $psts->version );

			$translation_array = apply_filters( 'prosites_tax_script_translations', array(
				'taxamo_missmatch' => __( 'EU VAT Warning: Your location evidence is not matching. You may be using a VPN or have provided inaccurate information. Your credit card will be used to determine your location upon checkout and the amount you are charged may include tax not shown here. Please make sure you have provided accurate information.', 'psts' ),
			) );

			wp_localize_script( 'psts-tax', 'psts_tax', $translation_array );
		}

		public static function tax_checkout_notice( $content ) {

			return $content . '<div class="tax-checkout-notice hidden">' .
			       sprintf( __( 'Note: Amounts displayed includes taxes of %s%%.', 'psts' ), '<span class="tax-percentage"></span>' ).
				'</div>';
		}

	}

}