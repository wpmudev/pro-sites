<?php

if ( ! class_exists( 'ProSites_Model_Registration' ) ) {

	class ProSites_Model_Registration {

		public static function process_form() {
//			global $psts, $psts_gateways;

		}

		public static function add_ajax_hook() {
			add_action( 'wp_ajax_check_prosite_blog', array( 'ProSites_Model_Registration', 'ajax_check_prosite_blog' ) );
			add_action( 'wp_ajax_nopriv_check_prosite_blog', array( 'ProSites_Model_Registration', 'ajax_check_prosite_blog' ) );
		}

		public static function ajax_check_prosite_blog() {
			global $psts, $current_site;

			add_filter( 'registration_errors', array( 'ProSites_Model_Registration', 'prosite_blog_check_only' ), 10, 3 );

			// replace $_POST with array data
			$params = array();
			parse_str( $_POST['data'], $params);
			$period = (int) $_POST['period'];
			$level = (int) $_POST['level'];
			$_POST = $params;

			$doing_ajax    = defined( 'DOING_AJAX' ) && DOING_AJAX ? true : false;
			$ajax_response = array();

			if ( $doing_ajax ) {

				$user_name  = sanitize_text_field( $_POST['user_name'] );
				$user_email = sanitize_email( $_POST['user_email'] );

				$blogname        = sanitize_text_field( $_POST['blogname'] );
				$blog_title      = sanitize_text_field( $_POST['blog_title'] );
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

				// Set session vars...
				$_SESSION['new_blog_details'] = array();
				$_SESSION['new_blog_details']['username'] = $user_name;
				$_SESSION['new_blog_details']['email'] = $user_email;
				$_SESSION['new_blog_details']['blogname'] = $blogname;
				$_SESSION['new_blog_details']['title'] = $blog_title;

				$username_available  = true;
				$email_available     = true;
				$blogname_available  = true;
				$blogtitle_available = true;

				if( ( ! empty( $user_check->errors ) && 1 == count( $user_check->errors ) && ! isset( $_POST['new_blog'] ) ) || ( 0 == count( $user_check->errors ) && isset( $_POST['new_blog'] ) ) ) {
					$keys = array_keys( $user_check->errors );

					if( $keys && ! in_array( 'availability_check_only', $keys ) && ! isset( $_POST['new_blog'] ) ) {
						// Something went wrong!
						$ajax_response['user_available'] = false;
					} else {
						// All good!  We're ready to create the user/site

						/** User is validated using register_new_user so that we can use the hooks and make them available,
						 * but we still need to actually create and activate the signup to get the $user_id. */
						$blog = $blog_validation;
						$domain = $blog['domain'];
						$path = $blog['path'];
						$blogname = $blog['blogname'];
						$blog_title = $blog['blog_title'];
						$errors = $blog['errors'];

						// Privacy setting
						$public = (int) $_POST['blog_public'];
						$signup_meta = array ('lang_id' => 1, 'public' => $public);

						// Create the signup
						$meta = apply_filters( 'add_signup_meta', $signup_meta );
						$activation_key = ProSites_Helper_Registration::signup_blog($domain, $path, $blog_title, $user_name, $user_email, $meta);

						$level_list = get_site_option( 'psts_levels' );

						$trial_days = $psts->get_setting( 'trial_days', 0 );
						$trial_active = ! empty( $trial_days );

						$site_name = '';
						if ( !is_subdomain_install() ) {
							$site_name = $current_site->domain . $current_site->path . $blogname;
						} else {
							$site_name = $blogname . ( $site_domain = preg_replace( '|^www\.|', '', $current_site->domain ) );
						}

						if( $trial_active ) {
							$recurring = $psts->get_setting( 'recurring_subscriptions', 1 );

							if( $recurring ) {
								$ajax_response['reserved_message'] = sprintf( '<div class="reserved_msg"><h2>' . __( 'Activate your site', 'psts' ) . '</h2>' . __( '<p>Your site <strong>(%s)</strong> has been reserved but is not yet activated.</p><p>Once payment information has been verified your trial period will begin. When your trial ends you will be automatically upgraded to your chosen plan. Your reservation only last for 48 hours upon which your site name will become available again.</p><p>Please use the form below to setup your payment information.</p>' , 'psts' ) . '</div>', $site_name );
							} else {
								// Non-recurring sites really should not do anything at checkout other than activate.
								$blog_id = ProSites_Helper_Registration::activate_blog( $activation_key, true, $period, $level );
								ProSites_Helper_Registration::set_trial( $blog_id, 1 );
								$psts->record_stat( $blog_id, 'signup' );
								$ajax_response['show_finish'] = true;
								$ajax_response['finish_content'] = ProSites_View_Front_Gateway::render_payment_submitted( true );
							}

						} else {
							$ajax_response['reserved_message'] = sprintf( '<div class="reserved_msg"><h2>' . __( 'Activate your site', 'psts' ) . '</h2>' . __( '<p>Your site <strong>(%s)</strong> has been reserved but is not yet activated.</p><p>Once payment has been processed your site will become active with your chosen plan. Your reservation only last for 48 hours upon which your site name will become available again.</p><p>Please use the form below to setup your payment information.</p>' , 'psts' ) . '</div>', $site_name );
						}

						// Keep this in session because we'll use it again
						$_SESSION['new_blog_details']['site_name'] = $blog_title;
						$_SESSION['new_blog_details']['reserved_message'] = $ajax_response['reserved_message'];

						$ajax_response['gateways_form'] = ProSites_View_Front_Gateway::render_checkout( '', '' );
					}
				} else {
					// We had registration errors, redraw the form displaying errors
					if( ! empty( $user_check ) && isset( $user_check->errors ) ) {
						$ajax_response['form'] = ProSites_View_Front_Registration::render_signup_form( $user_check );
						$ajax_response['user_available'] = false;
					}

					$ajax_response['gateways_form'] = '<div id="gateways" class="hidden"></div>';

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

				// Buffer used to isolate AJAX response from unexpected output
				ob_end_clean();
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

		public static function ajax_create_prosite_blog() {

			// ADD NONCE STUFF

			$doing_ajax    = defined( 'DOING_AJAX' ) && DOING_AJAX ? true : false;
			$ajax_response = array();

			if ( $doing_ajax ) {




				$response = array(
					'what'   => 'response',
					'action' => 'create_prosite_blog',
					'id'     => 1, // success status
					'data'   => json_encode( $ajax_response ),
				);

				// Buffer used to isolate AJAX response from unexpected output
				ob_end_clean();
				ob_start();
				$xmlResponse = new WP_Ajax_Response( $response );
				$xmlResponse->send();
				ob_end_flush();
			}

		}

	}

}