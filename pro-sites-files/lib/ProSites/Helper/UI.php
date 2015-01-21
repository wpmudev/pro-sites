<?php

if ( ! class_exists( 'ProSites_Helper_UI' ) ) {

	class ProSites_Helper_UI {

		public static function help_text( $message = '' ) {
			global $psts;

			if( empty( $message ) ){
				return false;
			}
			return '<img width="16" height="16" src="' . $psts->plugin_url . 'images/help.png" class="help_tip"><div class="psts-help-text-wrapper period-desc"><div class="psts-help-arrow-wrapper"><div class="psts-help-arrow"></div></div><div class="psts-help-text">' . $message . '</div></div>';
		}

	}

}