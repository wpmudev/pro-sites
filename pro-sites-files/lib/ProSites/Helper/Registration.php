<?php

if ( ! class_exists( 'ProSites_Helper_Registration' ) ) {

	class ProSites_Helper_Registration {

		/**
		 * Add a blog to *_signups table.
		 *
		 * Copied from core because we need the activation key and
		 * we need to avoid the blog activation email. This can/is done
		 * via a hook, but the double AJAX call kills our AJAX registration
		 * so its left out here.
		 *
		 * @param $domain
		 * @param $path
		 * @param $title
		 * @param $user
		 * @param $user_email
		 * @param array $meta
		 *
		 * @return string $key Activation key
		 */
		public static function signup_blog( $domain, $path, $title, $user, $user_email, $meta = array() )  {
			global $wpdb;

			// Reset the session if the user is signing up another blog
			$_SESSION['blog_activation_key'] = false;

			$key = substr( md5( time() . rand() . $domain ), 0, 16 );
			$meta = serialize($meta);

			$wpdb->insert( $wpdb->signups, array(
				'domain' => $domain,
				'path' => $path,
				'title' => $title,
				'user_login' => $user,
				'user_email' => $user_email,
				'registered' => current_time('mysql', true),
				'activation_key' => $key,
				'meta' => $meta
			) );

			$_SESSION['blog_activation_key'] = $key;
			return $key;
		}

		public static function activate_blog( $key, $trial = false, $period = 1, $level = 1, $expire = false ) {
			global $psts, $wpdb;

			// Activate the user signup
			$result = wpmu_activate_signup( $key );

			if( ! is_wp_error( $result ) ) {
				if( isset( $_SESSION['new_blog_details'] ) && is_array( $_SESSION['new_blog_details'] ) ) {
					$_SESSION['new_blog_details']['user_pass'] = strrev( $result['password'] );
				}
			}

			// If the blog has already been activated, we still need some information from the signup table
			if( is_wp_error( $result ) ) {
				$result = array();
				$signup = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE activation_key = %s", $key ) );

				if ( empty( $signup ) ) {
					return 0;
				}

				$user_id           = username_exists( $signup->user_login );
				$blog_id           = domain_exists( $signup->domain, $signup->path, $wpdb->siteid );
				$result['user_id'] = $user_id;
				$result['blog_id'] = $blog_id;
			}

			/**
			 * @todo: Make sure we dont over extend
			 */
			//Set Trial
			if ( $trial ) {
				$trial_days = $psts->get_setting( 'trial_days', 0 );
				// Set to first level for $trial_days
				$psts->extend( $result['blog_id'], $period, 'Trial', $level, '', strtotime( '+ ' . $trial_days . ' days' ) );

				//Redirect to checkout on next signup
				/**
				 * @todo May not be needed here anymore
				 */
				//update_blog_option( $result['blog_id'], 'psts_signed_up', 1 );
			}

//			// Clear SESSION
//			$_SESSION['new_blog_details'] = array();

			return $result['blog_id'];
		}

		public static function is_trial( $blog_id ) {
			$meta = ProSites::get_prosite_meta( $blog_id );

			if ( $meta ) {
				return ! isset( $meta['trialing'] ) && empty( $meta['trialing'] ) ? false : true;
			} else {
				return false;
			}
		}

		public static function set_trial( $blog_id, $value ) {
			$meta = ProSites::get_prosite_meta( $blog_id );
			if( empty( $meta ) ) {
				$meta = array();
			}
			$meta['trialing'] = $value;
			ProSites::update_prosite_meta( $blog_id, $meta );
		}

		public static function get_all_trial_blogs() {
			global $wpdb;
			//SELECT * FROM `wp_pro_sites` WHERE `meta` LIKE '%"trialing";i:1;%'
			$postids=$wpdb->get_col( $wpdb->prepare(
				"SELECT blog_ID FROM {$wpdb->base_prefix}pro_sites WHERE meta LIKE %s",
				'%\"trialing\";i:1;%'
			));

			return $postids;
		}

	}

}