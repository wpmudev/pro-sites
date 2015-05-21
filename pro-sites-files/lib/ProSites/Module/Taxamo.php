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

			add_filter( 'prosite_checkout_tax_apply', array( get_class(), 'apply_tax' ), 10, 3 );
			add_filter( 'prosite_checkout_tax_percentage', array( get_class(), 'tax_percentage' ), 10, 3 );

		}

		public static function apply_tax( $apply, $type, $country ) {
			// @todo Use API to check evidence
			return $apply;
		}

		public static function tax_percentage( $percentage, $type, $country ) {
			// @todo Use API to calculate percentage
			return $percentage;
		}

	}
}