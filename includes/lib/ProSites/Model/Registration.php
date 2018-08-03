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
			add_action( 'wp_ajax_update_nbt_templates', array( 'ProSites_Model_Registration', 'update_nbt_templates' ) );
			add_action( 'wp_ajax_nopriv_update_nbt_templates', array( 'ProSites_Model_Registration', 'update_nbt_templates' ) );
			add_action( 'wp_ajax_update_nbt_levels', array( 'ProSites_Model_Registration', 'update_levels_by_template' ) );
			add_action( 'wp_ajax_nopriv_update_nbt_levels', array( 'ProSites_Model_Registration', 'update_levels_by_template' ) );

			// Add extra content to checkout.
			add_filter( 'prosites_render_checkout_page', array( 'ProSites_Model_Registration', 'prosites_hidden_fields' ) );
		}

		public static function ajax_check_prosite_blog() {
			global $psts, $current_site;

			$blog_data   = array();
			$show_finish = false;

			// Add ajax session var
			ProSites_Helper_Session::session( 'psts_ajax_session_activated', true );

			// Introduce a fake error because we don't want to actually create the blog yet.
			add_filter( 'registration_errors', array(
				'ProSites_Model_Registration',
				'prosite_blog_check_only'
			), 10, 3 );

			// replace $_POST with array data
			$params = array();
			parse_str( $_POST['data'], $params );

			$period = (int) $_POST['period'];
			$level  = 'free' == $_POST['level'] ? $_POST['level'] : (int) $_POST['level'];

			// Keep the period and level data in $_POST.
			$params['level']  = $level;
			$params['period'] = $period;

			// Santitize each values in $_POST
			array_walk_recursive( $params, array( __CLASS__, 'array_walk_sanitize' ) );
			// Update the $_POST data.
			$_POST = $params;

			$doing_ajax    = defined( 'DOING_AJAX' ) && DOING_AJAX ? true : false;
			$ajax_response = array();

			if ( $doing_ajax ) {

				$user_name  = sanitize_text_field( $_POST['user_name'] );
				$user_email = sanitize_email( $_POST['user_email'] );

				$blogname   = sanitize_text_field( $_POST['blogname'] );
				$blog_title = sanitize_text_field( urldecode( $_POST['blog_title'] ) );

				// Process some cleaning up if needed
				do_action( 'prosite_register_blog_pre_validation', $user_name, $user_email, $blogname );

				$blog_validation = wpmu_validate_blog_signup( $blogname, $blog_title );

				// Attempt to create a new user (knowing that it will fail, but it should only have our error)
				if ( ! isset( $_POST['new_blog'] ) ) {
					$validation = wpmu_validate_user_signup( $user_name, $user_email );  // nicer errors, but doesn't deal with custom fields

					$user_check = register_new_user( $user_name, $user_email ); // checks custom fields, but ugly errors

					$user_check->errors = array_merge( $user_check->errors, $validation['errors']->errors );

					$user_check->errors = array_merge( $user_check->errors, $blog_validation['errors']->errors );
				} else {
					$user_check         = new WP_Error();
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
				if ( ( ! empty( $user_check->errors ) && 1 == count( $user_check->errors ) && ! isset( $_POST['new_blog'] ) ) || ( 0 == count( $user_check->errors ) && isset( $_POST['new_blog'] ) ) ) {
					$keys = array_keys( $user_check->errors );

					if ( $keys && ! in_array( 'availability_check_only', $keys ) && ! isset( $_POST['new_blog'] ) ) {
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
						$public      = (int) $_POST['blog_public'];
						$signup_meta = array( 'lang_id' => 1, 'public' => $public );

						// Create the signup
						$meta                        = apply_filters( 'add_signup_meta', $signup_meta );
						$result                      = ProSites_Helper_Registration::signup_blog( $domain, $path, $blog_title, $user_name, $user_email, $meta );
						$blog_data['activation_key'] = $result['activation_key'];

						if ( isset( $result['user_pass'] ) && ! empty( $result['user_pass'] ) ) {
							$blog_data['new_blog_details']['user_pass'] = $result['user_pass'];
						}

						$trial_days   = $psts->get_setting( 'trial_days', 0 );
						$trial_active = ! empty( $trial_days );

						$site_name = '';
						if ( ! is_subdomain_install() ) {
							$site_name = $current_site->domain . $current_site->path . $blogname;
						} else {
							$site_name = $blogname . '.' . ( $site_domain = preg_replace( '|^www\.|', '', $current_site->domain ) );
						}

						$recurring = $psts->get_setting( 'recurring_subscriptions', 1 );

						//Check for 100% off coupons
						if ( $session_coupon = ProSites_Helper_Session::session( 'COUPON_CODE' ) ) {
							$coupon_value = $psts->coupon_value( $session_coupon, 100 );
							$show_finish  = isset( $coupon_value['new_total'] ) && 0 == $coupon_value['new_total'] ? true : false;
						}
						//Check for 0 price Level, Skip the Payment and activate the blog in that case
						$price = $psts->get_level_setting( $level, 'price_' . $period );
						if ( 0 === (int) $price ) {
							$show_finish = true;
						}

						//If site is on trial
						if ( $trial_active ) {

							if ( $recurring ) {
								$blog_data['new_blog_details']['reserved_message'] = sprintf( '<div class="reserved_msg"><h2>' . __( 'Activate your site', 'psts' ) . '</h2>' . __( '<p>Your site <strong>(%s)</strong> has been reserved but is not yet activated.</p><p>Once payment information has been verified your trial period will begin. When your trial ends you will be automatically upgraded to your chosen plan. Your reservation only last for 48 hours upon which your site name will become available again.</p><p>Please use the form below to setup your payment information.</p>', 'psts' ) . '</div>', $site_name );
							} else {
								// Non-recurring sites really should not do anything at checkout other than activate.
								$result  = ProSites_Helper_Registration::activate_blog( $blog_data, true, $period, $level );
								$blog_id = $result['blog_id'];
								if ( isset( $result['password'] ) ) {
									$blog_data['new_blog_details']['user_pass'] = $result['password'];
								}
								ProSites_Helper_Registration::set_trial( $blog_id, 1 );
								//Update Activation Key for blog
								ProSites_Helper_Registration::update_activation_key( $blog_id, $blog_data['activation_key'] );
								$psts->record_stat( $blog_id, 'signup' );
								$ajax_response['show_finish']    = true;
								$ajax_response['finish_content'] = ProSites_View_Front_Gateway::render_payment_submitted( $blog_data, true );
							}

						} elseif ( ! $recurring && $show_finish ) {
							//This is the case for 0 cost plans or if a 100% discount coupon is used
							//Only for Non Recurring Subscriptions

							//Activate the blog
							$result = ProSites_Helper_Registration::activate_blog( $blog_data, false, $period, $level );

							//Set the blog id in session, site_activated is set to true
							$blog_data['new_blog_details']['blog_id']        = $blog_id = $result['blog_id'];
							$blog_data['new_blog_details']['site_activated'] = true;

							if ( isset( $result['password'] ) ) {
								$blog_data['new_blog_details']['user_pass'] = $result['password'];
							}

							//Update Activation Key for blog
							ProSites_Helper_Registration::update_activation_key( $blog_id, $blog_data['activation_key'] );

							//Extend the site for the defined term and set it to non recurring by default
							$psts->extend( $blog_id, $period, ProSites_Gateway_Manual::get_slug(), $level, 0, false, false );

							$psts->record_stat( $blog_id, 'signup' );

							//Formulate the Ajax response for the request
							$ajax_response['show_finish']    = true;
							$ajax_response['finish_content'] = ProSites_View_Front_Gateway::render_payment_submitted( $blog_data, false );
							$ajax_response['show_finish']    = true;

						} else {
							$blog_data['new_blog_details']['reserved_message'] = sprintf( '<div class="reserved_msg"><h2>' . __( 'Activate your site', 'psts' ) . '</h2>' . __( '<p>Your site <strong>(%s)</strong> has been reserved but is not yet activated.</p><p>Once payment has been processed your site will become active with your chosen plan. Your reservation only last for 48 hours upon which your site name will become available again.</p><p>Please use the form below to setup your payment information.</p>', 'psts' ) . '</div>', $site_name );
						}

						// FREE basic site
						if ( 'free' == $blog_data['new_blog_details']['level'] ) {
							if ( isset( $blog_data['new_blog_details']['reserved_message'] ) ) {
								unset( $blog_data['new_blog_details']['reserved_message'] );
							}
							$result                                   = ProSites_Helper_Registration::activate_blog( $blog_data, false, false, false );
							$blog_data['new_blog_details']['blog_id'] = $result['blog_id'];
							if ( isset( $result['password'] ) ) {
								$blog_data['new_blog_details']['user_pass'] = $result['password'];
							}
							$ajax_response['show_finish']    = true;
							$ajax_response['finish_content'] = ProSites_View_Front_Gateway::render_free_confirmation( $blog_data );
						}

						if ( isset( $blog_data['new_blog_details']['reserved_message'] ) ) {
							$ajax_response['reserved_message'] = $blog_data['new_blog_details']['reserved_message'];
						}

					}
					// If WP 4.0+ and user is logged in it will use WP_Session_Tokens, else $_SESSION
					ProSites_Helper_Session::session( 'new_blog_details', $blog_data['new_blog_details'] );
					ProSites_Helper_Session::session( 'activation_key', $blog_data['activation_key'] );

					$ajax_response['gateways_form'] = ProSites_View_Front_Gateway::render_checkout( $blog_data );
				} else {
					// We had registration errors, redraw the form displaying errors
					if ( ! empty( $user_check ) && isset( $user_check->errors ) ) {
						$ajax_response['form']           = ProSites_View_Front_Registration::render_signup_form( $blog_data, $user_check );
						$ajax_response['user_available'] = false;
					}

					// Isolate which standard fields are valid
					$error_keys = array_keys( $user_check->errors );

					foreach ( $error_keys as $key ) {
						if ( preg_match( '/username|user_name/', $key ) ) {
							$username_available = false;
						}
						if ( preg_match( '/email/', $key ) ) {
							$email_available = false;
						}
						if ( preg_match( '/blogname/', $key ) ) {
							$blogname_available = false;
						}
						if ( preg_match( '/blog_title/', $key ) ) {
							$blogtitle_available = false;
						}
					}
				}
				$ajax_response['username_available']   = $username_available;
				$ajax_response['email_available']      = $email_available;
				$ajax_response['blogname_available']   = $blogname_available;
				$ajax_response['blog_title_available'] = $blogtitle_available;

				$response = array(
					'what'   => 'response',
					'action' => 'check_prosite_blog',
					'id'     => 1, // success status
					'data'   => json_encode( $ajax_response ),
				);

				// No longer need ajax session
				ProSites_Helper_Session::unset_session( 'psts_ajax_session_activated' );
				// Unset session only if it was success.
				if ( empty( $user_check->errors ) ) {
					ProSites_Helper_Session::unset_session( 'new_blog_details' );
				}

				// Buffer used to isolate AJAX response from unexpected output
				@ob_end_clean();
				ob_start();
				$xmlResponse = new WP_Ajax_Response( $response );
				$xmlResponse->send();
				ob_end_flush();
			}

		}

		public static function prosite_blog_check_only( $errors = false, $user, $email ) {
			if ( empty( $errors ) ) {
				$errors = new WP_Error();
			}

			$errors->add( 'availability_check_only', __( 'User/Blog availability check only.', 'psts' ) );

			return $errors;
		}

		/**
		 * Update the NBT templates based on the plan.
		 *
		 * To support New Blog Templates:
		 * Update the NBT template selector based on the Pro Sites
		 * plan selected. If a restricted theme/plugin is active on a NBT
		 * template, hide that template from registration form.
		 *
		 * @return strng
		 */
		public static function update_nbt_templates() {

			global $psts;

			$templates = array();

			// Do not continue if NBT and Premium plugin/theme manager modules
			// are not enbled, or the level value is not available from the request.
			if ( $psts->nbt_update_required() && ! empty( $_POST['level'] ) ) {
				$level     = intval( $_POST['level'] );
				$templates = self::get_filtered_nbt_templates( $level );
			}

			// Create new options based on the filtered data.
			$content = '<option value="none">' . __( "None", "psts" ) . '</option>';
			if ( ! empty( $templates ) ) {
				foreach ( $templates as $key => $template ) {
					$content .= '<option value="' . $key . '">' . strip_tags( $template['name'] ) . '</option>';
				}
			}

			wp_send_json( $content );
		}

		/**
		 * Update plans based on the selected NBT template.
		 *
		 * To support New Blog Templates:
		 * Update the available pricing plans when a template
		 * is selected from NBT templates.
		 *
		 * @return strng
		 */
		public static function update_levels_by_template() {

			global $psts;

			$unavailable_levels = array();

			// Do not continue if NBT and Premium plugin/theme manager modules
			// are not enbled, or the template value is not available from the request.
			if ( $psts->nbt_update_required() && ! empty( $_POST['template'] ) ) {
				$template           = intval( $_POST['template'] );
				$unavailable_levels = self::get_filtered_levels_by_template( $template );
			}

			wp_send_json( $unavailable_levels );
		}

		/**
		 * Filter the NBT templates based on the level selected.
		 *
		 * @param $templates All available NBT templates.
		 *
		 * @return array Filtered NBT templates.
		 */
		public static function filter_nbt_signup_templates( $templates ) {

			$level = 0;
			// If level value is available on $_POST.
			if ( ! empty( $_POST['level'] ) ) {
				$level = intval( $_POST['level'] );
			} else {
				// If level value is set in session.
				$session = ProSites_Helper_Session::session( 'new_blog_details' );
				if ( ! empty( $session['level'] ) ) {
					$level = $session['level'];
				}
			}

			// Filter the templates only if valid level is available.
			if ( ! empty( $level ) ) {
				$templates = self::get_filtered_nbt_templates( $level );
			}

			return $templates;
		}

		/**
		 * Get the available NBT templates for the level.
		 *
		 * Get the available templates for NBT based on the
		 * selected level. Remove the templates if restricted
		 * plugins or themes are active within the template.
		 *
		 * @param $level Selected level.
		 *
		 * @return array Filtered templates.
		 */
		public static function get_filtered_nbt_templates( $level ) {

			global $psts;

			// Get the enabled modules in Pro Sites.
			$modules_enabled         = (array) $psts->get_setting( 'modules_enabled' );
			$premium_themes_enabled  = in_array( 'ProSites_Module_PremiumThemes', $modules_enabled );
			$premium_plugins_enabled = in_array( 'ProSites_Module_Plugins', $modules_enabled );
			$plugin_manager_enabled  = in_array( 'ProSites_Module_Plugins_Manager', $modules_enabled );

			// Do not continue if no templates are available if NBT.
			$nbt_model = nbt_get_model();

			// All available NBT templates.
			$templates = $nbt_model->get_templates();
			if ( empty( $templates ) ) {
				return array();
			}

			// Premium enabled themes.
			$premium_themes = $psts->get_setting( 'pt_allowed_themes', array() );
			// Network enabled themes.
			$network_themes = get_site_option( 'allowedthemes', array() );
			// Allowed themes.
			$allowed_themes = array_merge( $premium_themes, $network_themes );
			// Get premium plugins.
			$premium_plugins = $psts->get_setting( 'pp_plugins', array() );
			// Get the available plugins for the selected level.
			$premium_manager_plugins = (array) $psts->get_setting( 'psts_ppm_' . $level, array() );

			// Loop through each templates.
			foreach ( $templates as $key => $template ) {

				// Switch to the template blog.
				switch_to_blog( $template['blog_id'] );

				// If Premium themes module is enabled.
				if ( $premium_themes_enabled ) {
					// Current active theme of the blog.
					$theme_name = get_template();
					// If the current active theme of the blog is not available for this template, remove this template.
					if ( ! array_key_exists( $theme_name, $allowed_themes ) || ( isset( $allowed_themes[ $theme_name ] ) && $allowed_themes[ $theme_name ] > $level ) ) {
						unset( $templates[ $key ] );
					}
				}

				// If Premium plugins module is active.
				if ( $premium_plugins_enabled ) {
					foreach ( $premium_plugins as $plugin => $data ) {
						// If any of the active plugin of the blog is not available for this template, remove this template.
						if ( is_plugin_active( $plugin ) && ( ( isset( $data['level'] ) && $data['level'] > $level ) || ! isset( $data['level'] ) ) ) {
							unset( $templates[ $key ] );
						}
					}
				}

				// If Premium Plugins Manager is active.
				if ( $plugin_manager_enabled ) {
					// Get all active plugins of the blog.
					$get_active_plugins = (array) get_option( 'active_plugins' );
					$extra_plugins      = array_diff( $get_active_plugins, $premium_manager_plugins );
					// If any of the active plugin is not available for the plan, remove the template.
					if ( ! empty( $extra_plugins ) ) {
						unset( $templates[ $key ] );
					}
				}
			}

			// Restore current blog.
			restore_current_blog();

			return $templates;
		}

		/**
		 * Get the UNAVAILABLE plans based on the NBT template selected.
		 *
		 * Get the unavailable plans for based on the selected NBT template.
		 * If restricted plugins or themes are active within the template,
		 * that plan should not be available.
		 *
		 * @param $template_id Selected template.
		 *
		 * @return array Unavailable plans.
		 */
		public static function get_filtered_levels_by_template( $template_id ) {

			global $psts;

			$unavailable_levels = array();

			// Do not continue if no templates are available from NBT.
			$nbt_model = nbt_get_model();
			$template  = $nbt_model->get_template( $template_id );
			if ( empty( $template ) ) {
				return $unavailable_levels;
			}

			// If incase it is main blog, skip.
			if ( is_main_site( $template['blog_id'] ) ) {
				return $unavailable_levels;
			}

			$levels = get_site_option( 'psts_levels' );
			// Do not continue if levels not found.
			if ( empty( $levels ) || ! is_array( $levels ) ) {
				return $unavailable_levels;
			}

			// Get the enabled modules in Pro Sites.
			$modules_enabled         = (array) $psts->get_setting( 'modules_enabled' );
			$premium_themes_enabled  = in_array( 'ProSites_Module_PremiumThemes', $modules_enabled );
			$premium_plugins_enabled = in_array( 'ProSites_Module_Plugins', $modules_enabled );
			$plugin_manager_enabled  = in_array( 'ProSites_Module_Plugins_Manager', $modules_enabled );
			// Get premium plugins.
			$premium_plugins = $psts->get_setting( 'pp_plugins', array() );
			// Premium enabled themes.
			$premium_themes = $psts->get_setting( 'pt_allowed_themes', array() );
			// Network enabled themes.
			$network_themes = get_site_option( 'allowedthemes', array() );
			// Allowed themes.
			$allowed_themes = array_merge( $premium_themes, $network_themes );

			// Switch to the template blog.
			switch_to_blog( $template['blog_id'] );

			foreach ( $levels as $level => $level_data ) {
				// If Premium themes module is enabled.
				if ( $premium_themes_enabled ) {
					// Current active theme of the blog.
					$theme_name = get_template();
					// If the current active theme of the blog is not available for this template, remove this template.
					if ( ! array_key_exists( $theme_name, $allowed_themes ) || ( isset( $allowed_themes[ $theme_name ] ) && $allowed_themes[ $theme_name ] > $level ) ) {
						$unavailable_levels[] = $level;
					}
				}

				// If Premium plugins module is active.
				if ( $premium_plugins_enabled ) {
					foreach ( $premium_plugins as $plugin => $plugin_data ) {
						// If any of the active plugin of the blog is not available for this template, remove this template.
						if ( is_plugin_active( $plugin ) && ( ( isset( $plugin_data['level'] ) && $plugin_data['level'] > $level ) || ! isset( $plugin_data['level'] ) ) ) {
							$unavailable_levels[] = $level;
						}
					}
				}

				// If Premium Plugins Manager is active.
				if ( $plugin_manager_enabled ) {
					// Get the available plugins for the selected level.
					$premium_manager_plugins = (array) $psts->get_setting( 'psts_ppm_' . $level, array() );
					// Get all active plugins of the blog.
					$get_active_plugins = (array) get_option( 'active_plugins' );
					$extra_plugins      = array_diff( $get_active_plugins, $premium_manager_plugins );
					// If any of the active plugin is not avaible for the plan, remove the template.
					if ( ! empty( $extra_plugins ) ) {
						$unavailable_levels[] = $level;
					}
				}
			}

			// Restore current blog.
			restore_current_blog();

			return $unavailable_levels;
		}

		/**
		 * Sanitize given item of an array.
		 *
		 * @param mixed $item
		 */
		public static function array_walk_sanitize( &$item ) {

			$item = sanitize_text_field( $item );
		}

		/**
		 * Add extra html to gateway page in checkout.
		 *
		 * @param string $content Page content.
		 *
		 * @return string $content Page content.
		 */
		public static function prosites_hidden_fields( $content ) {

			$content .= '<input type="hidden" name="prosite_signup" value="1">';

			return $content;
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