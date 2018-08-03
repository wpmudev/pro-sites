<?php
class ProSites_Gateway_Trial {
	public static function get_existing_user_information( $blog_id, $domain, $get_all = true ) {
		global $psts;
		$end_date     = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
		$level        = $psts->get_level_setting( $psts->get_level( $blog_id ), 'name' );

		$args = array();

		$args['level'] = $level;
		$args['expires'] = $end_date;
		$args['trial'] = '<div id="psts-general-error" class="psts-warning">' . sprintf( __( 'You currently have a trial site. Your features will expire in the future. Please upgrade your site to continue to enjoy the features of your %s level; or choose a plan that more accurately meets your needs.', 'psts' ), $level ) . '</div>';

		return $args;
	}
}