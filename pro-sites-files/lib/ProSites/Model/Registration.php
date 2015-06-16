<?php

if ( ! class_exists( 'ProSites_Model_Registration' ) ) {

	class ProSites_Model_Registration {

		/**
		 * @todo: redundant maybe
		 */
		public static function process_form() {
		}

		public static function add_ajax_hook() {
			add_action( 'wp_ajax_check_prosite_blog', array( 'ProSites_Model_Registration', 'ajax_check_prosite_blog' ) );
			add_action( 'wp_ajax_nopriv_check_prosite_blog', array( 'ProSites_Model_Registration', 'ajax_check_prosite_blog' ) );
		}

		public static function ajax_check_prosite_blog() {
			global $psts, $current_site;

			$blog_data = array();

			// Add ajax session var
			ProSites_Helper_Session::session('psts_ajax_session_activated', true );

			// Introduce a fake error because we don't want to actually create the blog yet.
			add_filter( 'registration_errors', array( 'ProSites_Model_Registration', 'prosite_blog_check_only' ), 10, 3 );

			// replace $_POST with array data
			$params = array();
			parse_str( $_POST['data'], $params );

			$period = (int) $_POST['period'];
			$level  = 'free' == $_POST['level'] ? $_POST['level'] : (int) $_POST['level'];
			$_POST  = $params;

			$doing_ajax    = defined( 'DOING_AJAX' ) && DOING_AJAX ? true : false;
			$ajax_response = array();

			if ( $doing_ajax ) {

				$user_name  = sanitize_text_field( $_POST['user_name'] );
				$user_email = sanitize_email( $_POST['user_email'] );

				$blogname        = sanitize_text_field( $_POST['blogname'] );
				$blog_title      = sanitize_text_field( urldecode( $_POST['blog_title'] ) );

				// Process some cleaning up if needed
				do_action( 'prosite_register_blog_pre_validation', $user_name, $user_email, $blogname );

				$blog_validation = wpmu_validate_blog_signup( $blogname, $blog_title );

				// Attempt to create a new user (knowing that it will fail, but it should only have our error)
				if( ! isset( $_POST['new_blog' ] ) ) {
					$validation = wpmu_validate_user_signup( $user_name, $user_email );  // nicer errors, but doesn't deal with custom fields

					$user_check    = register_new_user( $user_name, $user_email ); // checks custom fields, but ugly errors

					$user_check->errors = array_merge( $user_check->errors, $validation['errors']->errors );

					$user_check->errors = array_merge( $user_check->errors, $blog_validation['errors']->errors );
				} else {
					$user_check = new WP_Error();
					$user_check->errors = array_merge( $user_check->errors, $blog_validation['errors']->errors );
				}

				// Replaced session vars to make it semi-stateless, will pick these up in a session later
				$blog_data['new_blog_details']             = array();
				$blog_data['new_blog_details']['username'] = $user_name;
				$blog_data['new_blog_details']['email']    = $user_email;
				$blog_data['new_blog_details']['blogname'] = $blogname;
				$blog_data['new_blog_details']['title']    = $blog_title;
				$blog_data['new_blog_details']['level']    = $level;
				$blog_data['new_blog_details']['period']   = $period;

				$username_available  = true;
				$email_available     = true;
				$blogname_available  = true;
				$blogtitle_available = true;

				// Checking passed...
				if( ( ! empty( $user_check->errors ) && 1 == count( $user_check->errors ) && ! isset( $_POST['new_blog'] ) ) || ( 0 == count( $user_check->errors ) && isset( $_POST['new_blog'] ) ) ) {
					$keys = array_keys( $user_check->errors );

					if( $keys && ! in_array( 'availability_check_only', $keys ) && ! isset( $_POST['new_blog'] ) ) {
						// Something went wrong!
						$ajax_response['user_available'] = false;
					} else {
						// All good!  We're ready to create the user/site

						/** User is validated using register_new_user so that we can use the hooks and make them available,
						 * but we still need to actually create and activate the signup to get the $user_id. */
						$blog       = $blog_validation;
						$domain     = $blog['domain'];
						$path       = $blog['path'];
						$blogname   = $blog['blogname'];
						$blog_title = $blog['blog_title'];
						$errors     = $blog['errors'];

						// Privacy setting
						$public = (int) $_POST['blog_public'];
						$signup_meta = array ('lang_id' => 1, 'public' => $public);

						// Create the signup
						$meta                        = apply_filters( 'add_signup_meta', $signup_meta );
						$result                      = ProSites_Helper_Registration::signup_blog( $domain, $path, $blog_title, $user_name, $user_email, $meta );
						$blog_data['activation_key'] = $result['activation_key'];

						if ( isset( $result['user_pass'] ) && ! empty( $result['user_pass'] ) ) {
							$blog_data['new_blog_details']['user_pass'] = $result['user_pass'];
						}

						$trial_days = $psts->get_setting( 'trial_days', 0 );
						$trial_active = ! empty( $trial_days );

						$site_name = '';
						if ( !is_subdomain_install() ) {
							$site_name = $current_site->domain . $current_site->path . $blogname;
						} else {
							$site_name = $blogname . '.' . ( $site_domain = preg_replace( '|^www\.|', '', $current_site->domain ) );
						}

						if( $trial_active ) {
							$recurring = $psts->get_setting( 'recurring_subscriptions', 1 );

							if( $recurring ) {
								$blog_data['new_blog_details']['reserved_message'] = sprintf( '<div class="reserved_msg"><h2>' . __( 'Activate your site', 'psts' ) . '</h2>' . __( '<p>Your site <strong>(%s)</strong> has been reserved but is not yet activated.</p><p>Once payment information has been verified your trial period will begin. When your trial ends you will be automatically upgraded to your chosen plan. Your reservation only last for 48 hours upon which your site name will become available again.</p><p>Please use the form below to setup your payment information.</p>' , 'psts' ) . '</div>', $site_name );
							} else {
								// Non-recurring sites really should not do anything at checkout other than activate.
								$result = ProSites_Helper_Registration::activate_blog( $blog_data, true, $period, $level );
								$blog_id = $result['blog_id'];
								if( isset( $result['password'] ) ) {
									$blog_data['new_blog_details']['user_pass'] = $result['password'];
								}
								ProSites_Helper_Registration::set_trial( $blog_id, 1 );
								//Update Activation Key for blog
								ProSites_Helper_Registration::update_activation_key( $blog_id, $blog_data['activation_key']);
								$psts->record_stat( $blog_id, 'signup' );
								$ajax_response['show_finish'] = true;
								$ajax_response['finish_content'] = ProSites_View_Front_Gateway::render_payment_submitted( $blog_data, true );
							}

						} else {
							$blog_data['new_blog_details']['reserved_message'] = sprintf( '<div class="reserved_msg"><h2>' . __( 'Activate your site', 'psts' ) . '</h2>' . __( '<p>Your site <strong>(%s)</strong> has been reserved but is not yet activated.</p><p>Once payment has been processed your site will become active with your chosen plan. Your reservation only last for 48 hours upon which your site name will become available again.</p><p>Please use the form below to setup your payment information.</p>' , 'psts' ) . '</div>', $site_name );
						}

						// FREE basic site
						if( 'free' == $blog_data['new_blog_details']['level'] ) {
							if( isset( $blog_data['new_blog_details']['reserved_message'] ) ) {
								unset( $blog_data['new_blog_details']['reserved_message'] );
							}
							$result = ProSites_Helper_Registration::activate_blog( $blog_data, false, false, false );
							$blog_data['new_blog_details']['blog_id'] = $result['blog_id'];
							if( isset( $result['password'] ) ) {
								$blog_data['new_blog_details']['user_pass'] = $result['password'];
							}
							$ajax_response['show_finish'] = true;
							$ajax_response['finish_content'] = ProSites_View_Front_Gateway::render_free_confirmation( $blog_data );
						}

						if( isset( $blog_data['new_blog_details']['reserved_message'] ) ) {
							$ajax_response['reserved_message'] = $blog_data['new_blog_details']['reserved_message'];
						}

					}
					// If WP 4.0+ and user is logged in it will use WP_Session_Tokens, else $_SESSION
					ProSites_Helper_Session::session( 'new_blog_details', $blog_data['new_blog_details'] );
					ProSites_Helper_Session::session( 'activation_key', $blog_data['activation_key'] );

					$ajax_response['gateways_form'] = ProSites_View_Front_Gateway::render_checkout( $blog_data );
				} else {
					// We had registration errors, redraw the form displaying errors
					if( ! empty( $user_check ) && isset( $user_check->errors ) ) {
						$ajax_response['form'] = ProSites_View_Front_Registration::render_signup_form( $blog_data, $user_check );
						$ajax_response['user_available'] = false;
					}

					// Isolate which standard fields are valid
					$error_keys = array_keys( $user_check->errors );

					foreach( $error_keys as $key ) {
						if( preg_match( '/username|user_name/', $key ) ) {
							$username_available = false;
						}
						if( preg_match( '/email/', $key ) ) {
							$email_available = false;
						}
						if( preg_match( '/blogname/', $key ) ) {
							$blogname_available= false;
						}
						if( preg_match( '/blog_title/', $key ) ) {
							$blogtitle_available = false;
						}
					}
				}
				$ajax_response['username_available'] = $username_available;
				$ajax_response['email_available'] = $email_available;
				$ajax_response['blogname_available'] = $blogname_available;
				$ajax_response['blog_title_available'] = $blogtitle_available;

				$response = array(
					'what'   => 'response',
					'action' => 'check_prosite_blog',
					'id'     => 1, // success status
					'data'   => json_encode( $ajax_response ),
				);

				// No longer need ajax session
				ProSites_Helper_Session::unset_session( 'psts_ajax_session_activated' );

				// Buffer used to isolate AJAX response from unexpected output
				@ob_end_clean();
				ob_start();
				$xmlResponse = new WP_Ajax_Response( $response );
				$xmlResponse->send();
				ob_end_flush();
			}

		}

		public static function prosite_blog_check_only( $errors = false, $user, $email ) {
			if( empty( $errors ) ) {
				$errors = new WP_Error();
			}

			$errors->add( 'availability_check_only', __( 'User/Blog availability check only.', 'psts' ) );

			return $errors;
		}

		/**
		 * @todo: UNCOMMENT AND FINISH
		 *
		 * @param $user_name
		 * @param $user_email
		 * @param $blogname
		 */
		public static function cleanup_unused_user( $user_name, $user_email, $blogname ) {
//			global $wpdb;
//
//			$user_id = username_exists( $user_name );
//
//			if( ! empty( $user_id ) ) {
//
//				// Is this a new user? Did they ever have a blog or do they have recent signups?
//				$blogs = get_blogs_of_user( $user_id, true );
//				$blog_count = count( $blogs );
//
//				$registration_date = strtotime( get_userdata( $user_id )->user_registered );
//				$older_than_two = current_time( 'timestamp', true ) - $registration_date;
//				$older_than_two = $older_than_two > 2 * DAY_IN_SECONDS;
//
//				$signup = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $wpdb->signups WHERE user_login = %s", $user_name ) );
//				if ( ! empty($signup) ) {
//					$diff = current_time( 'timestamp', true ) - mysql2date( 'U', $signup->registered );
//					// If registered more than two days ago, cancel registration and let this signup go through.
//					if ( $diff > 2 * DAY_IN_SECONDS ) {
//						$wpdb->delete( $wpdb->signups, array( 'domain' => $mydomain, 'path' => $path ) );
//					}
//				}
//
//				// User with no blogs, likely registration that didn't get activated
//				if( empty( $blog_count ) && $older_than_two ) {
//
//				}
//
//			}

		}

	}

}