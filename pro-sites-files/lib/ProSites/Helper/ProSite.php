<?php

if ( ! class_exists( 'ProSites_Helper_ProSite' ) ) {

	class ProSites_Helper_ProSite {

		public static $last_site = false;

		public static function get_site( $blog_id ) {
			global $wpdb;
			self::$last_site = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id ) );
			return self::$last_site;
		}

		public static function last_gateway( $blog_id ) {
			// Try to avoid another load
			if( ! empty( self::$last_site ) && self::$last_site->blog_ID = $blog_id ) {
				$site = self::$last_site;
			} else {
				$site = self::get_site( $blog_id );
			}

			if( ! empty( $site ) ) {
				return strtolower( $site->gateway );
			} else {
				return false;
			}

		}

		public static function get_activation_key( $blog_id ) {
			global $wpdb;
			$bloginfo = get_blog_details( $blog_id );
			return $wpdb->get_var( $wpdb->prepare( "SELECT activation_key FROM $wpdb->signups WHERE domain = %s AND path = %s", $bloginfo->domain, $bloginfo->path ) );
		}

		public static function get_blog_id( $activation_key ) {
			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE activation_key = %d", $activation_key ) );
			$blog_id           = domain_exists( $row->domain, $row->path, $wpdb->siteid );
			// As a fallback, try the site domain
			if( empty( $blog_id ) ) {
				$domain = $wpdb->get_var( $wpdb->prepare( "SELECT domain FROM $wpdb->site WHERE id = %d", $wpdb->siteid ) );
				$blog_id = domain_exists( $domain, $row->path, $wpdb->siteid );
			}
			return $blog_id;
		}

		public static function redirect_signup_page() {
			global $pagenow, $psts;
			$show_signup = $psts->get_setting( 'show_signup' );

			if( 'wp-signup.php' == $pagenow && $show_signup ) {
				wp_redirect( $psts->checkout_url() );
				exit();
			}
		}


	}
}
