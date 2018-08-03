<?php

if ( ! class_exists( 'ProSites_Helper_Integration' ) ) {

	class ProSites_Helper_Integration {

		public static function init() {

			/** BuddyPress Integration */
			ProSites_Helper_Integration_BuddyPress::init();

		}


	}

}