<?php

if ( ! class_exists( 'ProSites_Model_Registration' ) ) {

	class ProSites_Model_Registration {

		public static function process_form() {
//			global $psts, $psts_gateways;


		}

		public static function add_ajax_hook() {
			add_action( 'wp_ajax_check_prosite_blog', array( 'ProSites_Model_Registration', 'ajax_check_prosite_blog' ) );
		}

		public static function ajax_check_prosite_blog() {
			add_filter( 'registration_errors', array( 'ProSites_Model_Registration', 'prosite_blog_check_only' ), 10, 3 );

			// replace $_POST with array data
			$params = array();
			parse_str( $_POST['data'], $params);
			$_POST = $params;

			$doing_ajax    = defined( 'DOING_AJAX' ) && DOING_AJAX ? true : false;
			$ajax_response = array();

			if ( $doing_ajax ) {

				$user_name = sanitize_text_field( $_POST['user_name'] );
				$user_email = sanitize_email( $_POST['user_email'] );

				// Attempt to create a new user (knowing that it will fail, but it should only have our error)
				$validation = wpmu_validate_user_signup( $user_name, $user_email );  // nicer errors, but doesn't deal with custom fields
				$user_id = register_new_user( $user_name, $user_email ); // checks custom fields, but ugly errors

				$user_id->errors = array_merge( $user_id->errors, $validation['errors']->errors );

				// Now check the blog...
				$blogname = sanitize_text_field( $_POST['blogname'] );
				$blog_title = sanitize_text_field( $_POST['blog_title'] );
				$validation = wpmu_validate_blog_signup( $blogname, $blog_title );
				$user_id->errors = array_merge( $user_id->errors, $validation['errors']->errors );

				if( ! empty( $user_id->errors ) && 1 == count( $user_id->errors ) ) {
					// All good!  We're ready to create the user
					$keys = array_keys( $user_id->errors );

					$ajax_response['user_available'] = true;

					if( ! in_array( 'availability_check_only', $keys ) ) {
						// Something went wrong!
						$ajax_response['user_available'] = false;
					}
				} else {
					// We had registration errors, redraw the form displaying errors
					if( ! empty( $user_id ) && isset( $user_id->errors ) ) {
						$ajax_response['form'] = ProSites_View_Front_Registration::render_signup_form( $user_id );
						$ajax_response['user_available'] = false;
					}
				}

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