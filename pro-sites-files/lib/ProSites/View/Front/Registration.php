<?php

if ( ! class_exists( 'ProSites_View_Front_Registration' ) ) {
	class ProSites_View_Front_Registration {

		/**
		 * Renders the user/site signup form.
		 *
		 * OR the completed message.
		 *
		 * @param mixed $render_data
		 * @param bool $errors
		 *
		 * @return string
		 */
		public static function render_signup_form( $render_data = array(), $errors = false ) {
			global $psts;
			$current_site = get_current_site();
			$img_base  = $psts->plugin_url . 'images/';

			if( ! $errors ) {
				$errors = new WP_Error();
			}

			// Try going stateless, or check the session
			if( empty( $render_data ) ) {
				$render_data = array();
				$render_data['new_blog_details'] = ProSites_Helper_Session::session( 'new_blog_details' );
				$render_data['transaction_completed'] = ProSites_Helper_Session::session( 'transaction_completed' );
			}

			$content = '';

			/**
			 * Avoid rendering the form if its already been done.
			 *
			 * This means registration is completed. Trial is activated (non-recurring) or user provided
			 * payment information for trial (recurring) or normal recurring plan.
			 */
			if( isset( $render_data['new_blog_details'] ) && isset( $render_data['new_blog_details']['reserved_message'] ) || isset( $render_data['transaction_completed'] ) ) {

				if( isset( $render_data['new_blog_details'] ) ) {
					// This variable is populated by ProSites_Model_Registration::ajax_check_prosite_blog()
					$content .= $render_data['new_blog_details']['reserved_message'];
					// Debugging only.
				//ProSites_Helper_Session::unset_session( 'new_blog_details' );
				//ProSites_Helper_Session::unset_session( 'upgraded_blog_details' );
					return $content;
				} else {
					$content = $render_data['transaction_completed']['message'];
					return $content;
				}
			}

			$content .= '<div id="prosites-signup-form-checkout" class="hidden">';
			$action = '';

			$active_signup = get_site_option( 'registration', 'none' );
			$active_signup = apply_filters( 'wpmu_active_signup', $active_signup );

			// Determine action...
			if ( is_user_logged_in() && ( $active_signup == 'all' || $active_signup == 'blog' ) )
				$action = 'another_blog';
			elseif ( is_user_logged_in() == false && ( $active_signup == 'all' || $active_signup == 'user' ) )
				$action = 'sign_up';
			elseif ( is_user_logged_in() == false && ( $active_signup == 'blog' ) )
				$action = 'no_register';
			else
				$action = 'no_new_blog';

			// WP hook
			// Render regardless if user can sign up
			ob_start();
			do_action( 'preprocess_signup_form' );
			$content .= ob_get_clean();

			if( 'sign_up' == $action || 'another_blog' == $action ) {

				// Need to first check if user can sign up
				// WP hook
				ob_start();
				do_action( 'before_signup_form' );
				$content .= ob_get_clean();

				$user_name = '';
				$user_email = '';

				$content .= '<h2>' . esc_html__( 'Setup your site', 'psts' ) . '</h2>';

				$content .= '<form method="post" id="prosites-user-register">';

				// USER SECTION
				ob_start();
				do_action( 'signup_hidden_fields', 'validate-user' );
				$content .= ob_get_clean();
				$content .= self::render_user_section( $render_data, $errors, $user_name, $user_email );

				// BLOG SECTION
				ob_start();
				do_action( 'signup_hidden_fields', 'validate-site' );
				// do_action( 'signup_hidden_fields', 'create-another-site' );
				$content .= ob_get_clean();
				$content .= self::render_blog_section( $render_data, $errors );

				$content .= '<div><input type="button" id="check-prosite-blog" value="' . esc_attr__( 'Reserve your site', 'psts' ) . '" /></div>';
				$content .= '<div class="hidden" id="registration_processing">
							<img src="' . $img_base . 'loading.gif"> Processing...
							</div>';
				$content .= '</form>';

				// WP hook
				ob_start();
				do_action( 'after_signup_form' );

				$content .= '</div>';

				$content .= ob_get_clean();

			}

			return $content;

		}

		private static function render_user_section( $render_data = array(), $errors, $user_name, $user_email ) {

			$content = '<div>';

			if( ! is_user_logged_in() ) {

				$content .= '<div class="username"><label for="user_name">' . __( 'Username:' ) . '</label>';
				if ( $errmsg = $errors->get_error_message('user_name') ) {
					$content .= '<p class="error">' .$errmsg. '</p>';
				}

				$content .= '<input name="user_name" type="text" id="user_name" value="' . esc_attr( $user_name ) . '" maxlength="60" />';
				$content .= __( '(Must be at least 4 characters, letters and numbers only.)', 'psts' );
				$content .= '</div>';

				$content .= '<div class="email"><label for="user_email">' . __( 'Email&nbsp;Address:', 'psts' ) . '</label>';
				if ( $errmsg = $errors->get_error_message('user_email') ) {
					$content .= '<p class="error">' . $errmsg  . '</p>';
				}

				$content .= '<input name="user_email" type="email" id="user_email" value="' . esc_attr($user_email) . '" maxlength="200" /><br />';
				$content .= __('We send your registration email to this address. (Double-check your email address before continuing.)');
				$content .= '</div>';

				if ( $errmsg = $errors->get_error_message('generic') ) {
					$content .= '<p class="error">' . $errmsg . '</p>';
				}
				ob_start();
				do_action( 'signup_extra_fields', $errors );
				$content .= ob_get_clean();
			} else {
				$user = wp_get_current_user();
				$content .= '<input type="hidden" name="user_name" value="' . $user->user_login . '" />';
				$content .= '<input type="hidden" name="user_email" value="' . $user->user_email . '" />';
				$content .= '<input type="hidden" name="new_blog" value="1" />';
			}

			$content .= '</div>';

			return $content;
		}

		private static function render_blog_section( $render_data = array(), $errors, $blogname = '', $blog_title = '' ) {
			$current_site = get_current_site();
			$content = '<div>';

			// Blog name
//			if ( !is_subdomain_install() ) {
			$content .= '<div class="blogname"><label for="blogname">' . __('Your Site: ') . '</label>';
//			} else {
//				$content .= '<label for="blogname">' . __('Site Domain:') . '</label>';
//			}
			if ( $errmsg = $errors->get_error_message('blogname') ) {
				$content .= '<p class="error">' . $errmsg . '</p>';
			}
			if ( !is_subdomain_install() ) {
				$content .= '<span class="prefix_address">' . $current_site->domain . $current_site->path . '</span><input name="blogname" type="text" id="blogname" value="' . esc_attr( $blogname ) . '" maxlength="60" /></div>';
			} else {
				$content .= '<input name="blogname" type="text" id="blogname" value="' . esc_attr( $blogname ) . '" maxlength="60" /><span class="suffix_address">.' . ( $site_domain = preg_replace( '|^www\.|', '', $current_site->domain ) ) . '</span></div>';
			}

//			if ( !is_user_logged_in() ) {
//				if ( !is_subdomain_install() )
//					$site = $current_site->domain . $current_site->path . __( 'sitename' );
//				else
//					$site = __( 'domain' ) . '.' . $site_domain . $current_site->path;
//				$content .= '<p>(<strong>' . sprintf( __('Your address will be %s.', 'psts' ), $site ) . '</strong>) ' . __( 'Must be at least 4 characters, letters and numbers only. It cannot be changed, so choose carefully!', 'psts' ) . '</p>';
//			}

			$content .= '<div class="blog_title"><label for="blog_title">' . esc_html__('Site Title:', 'psts' ) . '</label>';
			if ( $errmsg = $errors->get_error_message('blog_title') ) {
				$content .= '<p class="error">' . $errmsg . '</p>';
			}
			$content .= '<input name="blog_title" type="text" id="blog_title" value="'.esc_attr( $blog_title ) . '" /></div>';

			$yes_checked = !isset( $_POST['blog_public'] ) || $_POST['blog_public'] == '1' ? 'checked="checked"' : '';
			$no_checked = isset( $_POST['blog_public'] ) && $_POST['blog_public'] == '0' ? 'checked="checked"' : '';

			$content .= '<div id="privacy">
        		<p class="privacy-intro">
            		<label for="blog_public_on">' . esc_html__('Privacy:', 'psts') . '</label> ' .
			            esc_html__( 'Allow search engines to index this site.', 'psts' ) .
			            '<br style="clear:both" />
	                <label class="checkbox" for="blog_public_on">
		                <input type="radio" id="blog_public_on" name="blog_public" value="1" ' . $yes_checked  . '/>
		                <strong>' . esc_html__( 'Yes', 'psts' ) . '</strong>
	                </label>
	                <label class="checkbox" for="blog_public_off">
		                <input type="radio" id="blog_public_off" name="blog_public" value="0" ' . $no_checked  . '/>
		                <strong>' . esc_html__( 'No', 'psts' ) . '</strong>
	                </label>
        		</p>
			</div>';

			ob_start();
			do_action( 'signup_blogform', $errors );
			$content .= ob_get_clean();

			$content .= '</div>';

			return $content;

		}


	}

}