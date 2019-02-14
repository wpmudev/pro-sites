<?php

if ( ! class_exists( 'ProSites_Helper_Registration' ) ) {

	class ProSites_Helper_Registration {

		public static $temp_pass = false;

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

			$key = substr( md5( time() . rand() . $domain ), 0, 16 );
			$meta = serialize($meta);

			$result = $wpdb->insert( $wpdb->signups, array(
				'domain'         => $domain,
				'path'           => sanitize_text_field( $path ),
				'title'          => sanitize_text_field( $title ),
				'user_login'     => sanitize_text_field( $user ),
				'user_email'     => sanitize_email( $user_email ),
				'registered'     => current_time( 'mysql', true ),
				'activation_key' => $key,
				'meta'           => $meta
			) );

			$password = false;

			// Activate the user and attempt a login (because we want WP sessions)
			$user_id = username_exists( $user );

			if ( ! $user_id ) {
				$password = wp_generate_password( 12, false );
				$user_id  = wpmu_create_user( $user, $password, $user_email );

				$creds    = array(
					'user_login'    => $user,
					'user_password' => $password,
					'remember'      => true,
				);
				$user     = wp_signon( $creds );

			}

			$result = array(
				'activation_key' => $key,
				'user_pass' => $password,
			);

			return $result;
		}

		public static function activate_blog( $data, $trial = false, $period = 1, $level = 1, $expire = false, $extend = true, $recurring = true ) {
			global $psts, $wpdb;

			$user_pass = false;

			if( ! is_array( $data ) ) {
				$key = $data;
			} else {
				$key = isset( $data['activation_key'] ) ? $data['activation_key'] : false;
				$user_pass = isset( $data['new_blog_details']['user_pass'] ) ? $data['new_blog_details']['user_pass'] : false;
			}
			if( empty( $key ) ) {
				return false;
			}

			// In case we're in session
			$session_data[ 'new_blog_details' ] = ProSites_Helper_Session::session( 'new_blog_details' );
			$user_pass = empty( $user_pass ) && isset( $session_data['new_blog_details']['user_pass'] ) ? $session_data['new_blog_details']['user_pass'] : $user_pass;

			// Set password to session.
			if ( ! empty( $user_pass ) ) {
				$session_data[ 'new_blog_details' ]['user_pass'] = $user_pass;
				ProSites_Helper_Session::session( 'new_blog_details', $session_data[ 'new_blog_details' ] );
			}

			// Activate the user signup.
			$result = wpmu_activate_signup( $key );

			// Make sure the user password is the one we send in email.
			if ( ! is_wp_error( $result ) && ! empty( $result['user_id'] ) && ! empty( $result['password'] ) && ! is_user_logged_in() ) {
				$user_pass = empty( $user_pass ) ? $result['password'] : $user_pass;
				// Update the password to make sure.
				wp_set_password( $user_pass, $result['user_id'] );
			}

			$signup = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE activation_key = %s", $key ) );

			// If the blog has already been activated, we still need some information from the signup table
			if( is_wp_error( $result ) ) {
				$result = array();

				if ( empty( $signup ) ) {
					return 0;
				}

				$user_id           = username_exists( $signup->user_login );
				$blog_id           = domain_exists( $signup->domain, $signup->path, $wpdb->siteid );
				// As a fallback, try the site domain
				if( empty( $blog_id ) ) {
					$domain = $wpdb->get_var( $wpdb->prepare( "SELECT domain FROM $wpdb->site WHERE id = %d", $wpdb->siteid ) );
					$blog_id = domain_exists( $domain, $signup->path, $wpdb->siteid );
				}
				$result['user_id'] = $user_id;
				$result['blog_id'] = (int) $blog_id;

				$newblog_details = ProSites_Helper_Session::session( 'new_blog_details' );
				$newblog_details['site_activated'] = true;
				$newblog_details['blog_id'] = (int) $blog_id;
				$newblog_details['activation_key'] = $key;
				ProSites_Helper_Session::session( 'new_blog_details', $newblog_details );

			} else {

				if( isset( $signup->user_login ) && ! empty( $user_pass ) && ! is_user_logged_in() ) {
					$creds = array(
							'user_login'    => $signup->user_login,
							'user_password' => $user_pass
					);
					$user  = wp_signon( $creds, true );
					if( ! is_wp_error( $user ) ) {
						wp_set_current_user( $user->ID );
					}

				}

				$newblog_details = ProSites_Helper_Session::session( 'new_blog_details' );
				$newblog_details['site_activated'] = true;
				ProSites_Helper_Session::session( 'new_blog_details', $newblog_details );

			}

			/**
			 * Update coupon information
			 */
			if( ! empty( $signup ) ) {
//				$blog_id = $result['blog_id'];
//				$signup_meta = maybe_unserialize( $signup->meta );
//
//				// Unlikely that this will have a coupon, but make sure
//				$used = (array) get_blog_option( $blog_id, 'psts_used_coupons' );
//
//				// Is there a coupon stored in the signup_meta?
//				if( isset( $signup_meta['psts_used_coupons'] ) && ! empty( $signup_meta['psts_used_coupons'] ) && is_array( $signup_meta['psts_used_coupons'] ) ) {
//					// Merge and make sure we don't record the same coupon twice
//					$used = array_merge( $used, $signup_meta['psts_used_coupons'] );
//					$used = array_unique( $used );
//					// Remove from signup meta
//					unset( $signup_meta['psts_used_coupons'] );
//					$psts->update_signup_meta( $signup_meta, $key );
//				}
//				if( ! empty( $used ) ) {
//					// Add to blog options
//					update_blog_option( $blog_id, 'psts_used_coupons', $used );
//				}
			}

			/**
			 * @todo: Make sure we dont over extend
			 */
			//Set Trial
			if ( $trial && $extend ) {
				$trial_days = $psts->get_setting( 'trial_days', 0 );
				// Set to first level for $trial_days
				$psts->extend( $result['blog_id'], $period, 'trial', $level, '', strtotime( '+ ' . $trial_days . ' days' ), $recurring );

				//Redirect to checkout on next signup
				/**
				 * @todo May not be needed here anymore
				 */
				//update_blog_option( $result['blog_id'], 'psts_signed_up', 1 );
			}

			if( ! empty( $user_pass ) ) {
				$result['password'] = $user_pass;
			}
			// Contains $result['password'] for new users
			return $result;
		}

		public static function is_trial( $blog_id ) {
			$meta = ProSites::get_prosite_meta( $blog_id );

			if ( $meta ) {
				return !empty( $meta['trialing'] ) && $meta['trialing'] == 1 ? true : false;
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

		// Avoid sending users passwords that wont work (for users with multiple blogs)
		public static function alter_welcome_for_existing_users( $welcome_email, $blog_id, $user_id, $password, $title, $meta ) {
			$blogs_of_user = get_blogs_of_user( $user_id );

			if( count( $blogs_of_user )  > 1 ) {
				$welcome_email = str_replace( $password, __( '(your current password)', 'psts' ), $welcome_email );
			}
			return $welcome_email;
		}

		/**
		 * Make sure we are sending correct password in email.
		 *
		 * @param string $welcome_email Email content.
		 * @param int    $blog_id       Blog ID.
		 * @param int    $user_id       User ID.
		 * @param string $password      Password.
		 *
		 * @return mixed
		 */
		public static function update_welcome_email( $welcome_email, $blog_id, $user_id, $password ) {
			// Get the session data.
			$session_data = ProSites_Helper_Session::session( 'new_blog_details' );
			// If password is set in session, update email password.
			if( ! empty( $session_data['user_pass'] ) ) {
				$welcome_email = str_replace( $password, $session_data['user_pass'], $welcome_email );
			}
			return $welcome_email;
		}

		/**
		 * Update activation key for the blog id in pro sites table
		 * @param $blog_id
		 * @param $activation_key
		 */
		public static function update_activation_key( $blog_id, $activation_key ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->base_prefix . 'pro_sites',
				array(
					'identifier' => $activation_key,
				),
				array(
					'blog_ID' => $blog_id
				)
			);
		}


	}

}
