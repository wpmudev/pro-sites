<?php

if ( ! class_exists( 'ProSites_View_Front_Registration' ) ) {
	class ProSites_View_Front_Registration {

		public static function render_registration_header() {
			global $psts;

			if( ( ! isset( $_GET['level']) && ! isset( $_GET['period'] ) ) && ( ! isset( $_POST['level']) && ! isset( $_POST['period'] ) ) ) {
				return false;
			}

			$level = isset( $_GET['level'] ) ? (int) $_GET['level'] : (int) $_POST['level'];
			$period = isset( $_GET['period'] ) ? (int) $_GET['period'] : (int) $_POST['period'];

			$step = isset( $_POST['registration_step'] ) ? (int) $_POST['registration_step'] : 0;
			$step += 1;

			$level_list = get_site_option( 'psts_levels' );
			$level_name = $level_list[ $level ]['name'];
			$prosite_brand = $psts->get_setting( 'rebrand', 'Pro Site' );

			switch( $step ) {
				case 1:
					$content = '<div class="widecolumn"><div class="psts-registration-page mu_register">' .
					           '<h2>' . sprintf( esc_html__( '%s Signup', 'psts' ), $prosite_brand ) . '</h2>' .
					           '<p>' . sprintf( esc_html__( 'You are about to sign up for a "%s" plan with a renewal period of every %d month(s).', 'psts' ), $level_name, $period ) . '</p>' .
					           '<p>' . esc_html__( 'Please enter your username below', 'psts' ) . '</p>' .
					           '</div></div>';
					break;
				case 2:
					$content = '<div class="widecolumn"><div class="psts-registration-page mu_register">' .
					           sprintf( '<h2>' . esc_html__( '%s Signup', 'psts' ) . '</h2>', $prosite_brand ) .
					           '<p>' . sprintf( esc_html__('You are about to sign up for a "%s" plan with a renewal period of every %d month(s).', 'psts' ), $level_name, $period ) . '</p>' .
					           '<p>' . esc_html__( 'Please add your site details below', 'psts' ) . '</p>' .
					           '</div></div>';
					break;
				case 3:
					$content = '<div class="widecolumn"><div class="psts-registration-page mu_register">'
					           . sprintf( '<h2>' . esc_html__( '%s Signup', 'psts' ) . '</h2>', $prosite_brand )
					           . sprintf( esc_html__('Almost finished signing up for a "%s" plan with a renewal period of every %d month(s).', 'psts' ), $level_name, $period ) .
					           '</div></div>';
					break;
			}

			echo $content;

		}

		public static function render_registration_fields( $errors ) {
			global $psts;
			if( ( ! isset( $_GET['level']) && ! isset( $_GET['period'] ) ) && ( ! isset( $_POST['level']) && ! isset( $_POST['period'] ) ) ) {
				return false;
			}

			$step = isset( $_POST['registration_step'] ) ? (int) $_POST['registration_step'] : 0;
			$step += 1;

			$level = isset( $_GET['level'] ) ? (int) $_GET['level'] : (int) $_POST['level'];
			$period = isset( $_GET['period'] ) ? (int) $_GET['period'] : (int) $_POST['period'];

			?>
				<input type="hidden" name="level" value="<?php echo esc_attr( $level ) ?>" />
				<input type="hidden" name="period" value="<?php echo esc_attr( $period ) ?>" />
				<input type="hidden" name="registration_step" value="<?php echo esc_attr( $step ) ?>" />
			<?php

		}

		public static function render_signup_finished() {

			$username = sanitize_text_field( $_POST['user_name'] );
			$email = sanitize_email( $_POST['user_email'] );
			$blog_name = sanitize_text_field( $_POST['blogname'] );
			$blog_id = get_id_from_blogname( $blog_name );
			switch_to_blog( $blog_id );

			$content = '';
			$content = ProSites_View_Front_Checkout::render_checkout_page( $content, $blog_id );

			restore_current_blog();

			echo $content;

			?>
			<div class="psts-registration-final">
				<p>If this is your first site you will receive an activation email at the address provided (<?php echo esc_html( $email ); ?>). Before you can login you will <strong>need to
				activate</strong> your username - <?php echo esc_html( $username ); ?>.</p>
				<p>Check your inbox and click the activation link given. If you do not activate your site within two days, you will have to sign up again.</p>
				<p>If you did not receive your email please wait a little longer; check your spam folder and double check that the email address you provided is correct.</p>
			</div>
			<?php

		}


		public static function render_signup_form( $errors = false ) {
			global $psts;
			$current_site = get_current_site();
			$img_base  = $psts->plugin_url . 'images/';

			if( ! $errors ) {
				$errors = new WP_Error();
			}

			// Avoid rendering the form if its already been done
			if( isset( $_SESSION['new_blog_details'] ) && isset( $_SESSION['new_blog_details']['reserved_message'] ) ) {
				$content = $_SESSION['new_blog_details']['reserved_message'];
//				unset( $_SESSION['new_blog_details']);
//				unset( $_SESSION['upgraded_blog_details']);
				return $content;
			}

			$content = '<div id="prosites-signup-form-checkout" class="hidden">';
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
				$content .= self::render_user_section( $errors, $user_name, $user_email );

				/// DO SOMETHING WITH THIS ----> $active_signup = apply_filters( 'wpmu_active_signup', $active_signup );
				/// AND THIS ---->
				//			do_action( 'preprocess_signup_form' );
				//			if ( is_user_logged_in() && ( $active_signup == 'all' || $active_signup == 'blog' ) )
				//				signup_another_blog($newblogname);
				//			elseif ( is_user_logged_in() == false && ( $active_signup == 'all' || $active_signup == 'user' ) )
				//				signup_user( $newblogname, $user_email );
				//			elseif ( is_user_logged_in() == false && ( $active_signup == 'blog' ) )
				//				_e( 'Sorry, new registrations are not allowed at this time.' );
				//			else
				//				_e( 'You are logged in already. No need to register again!' );

				// BLOG SECTION
				ob_start();
				do_action( 'signup_hidden_fields', 'validate-site' );
				// do_action( 'signup_hidden_fields', 'create-another-site' );
				$content .= ob_get_clean();
				$content .= self::render_blog_section( $errors );

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

		private static function render_user_section( $errors, $user_name, $user_email ) {

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

		private static function render_blog_section( $errors, $blogname = '', $blog_title = '' ) {
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

		private static function show_blog_form( $blogname = '', $blog_title = '', $errors = '' ) {
			$current_site = get_current_site();
			// Blog name
			if ( !is_subdomain_install() )
				echo '<label for="blogname">' . __('Site Name:') . '</label>';
			else
				echo '<label for="blogname">' . __('Site Domain:') . '</label>';

			if ( $errmsg = $errors->get_error_message('blogname') ) { ?>
				<p class="error"><?php echo $errmsg ?></p>
			<?php }

			if ( !is_subdomain_install() )
				echo '<span class="prefix_address">' . $current_site->domain . $current_site->path . '</span><input name="blogname" type="text" id="blogname" value="'. esc_attr($blogname) .'" maxlength="60" /><br />';
			else
				echo '<input name="blogname" type="text" id="blogname" value="'.esc_attr($blogname).'" maxlength="60" /><span class="suffix_address">.' . ( $site_domain = preg_replace( '|^www\.|', '', $current_site->domain ) ) . '</span><br />';

			if ( !is_user_logged_in() ) {
				if ( !is_subdomain_install() )
					$site = $current_site->domain . $current_site->path . __( 'sitename' );
				else
					$site = __( 'domain' ) . '.' . $site_domain . $current_site->path;
				echo '<p>(<strong>' . sprintf( __('Your address will be %s.'), $site ) . '</strong>) ' . __( 'Must be at least 4 characters, letters and numbers only. It cannot be changed, so choose carefully!' ) . '</p>';
			}

			// Blog Title
			?>
			<label for="blog_title"><?php _e('Site Title:') ?></label>
			<?php if ( $errmsg = $errors->get_error_message('blog_title') ) { ?>
				<p class="error"><?php echo $errmsg ?></p>
			<?php }
			echo '<input name="blog_title" type="text" id="blog_title" value="'.esc_attr($blog_title).'" />';
			?>

			<div id="privacy">
        <p class="privacy-intro">
            <label for="blog_public_on"><?php _e('Privacy:') ?></label>
	        <?php _e( 'Allow search engines to index this site.' ); ?>
	        <br style="clear:both" />
            <label class="checkbox" for="blog_public_on">
                <input type="radio" id="blog_public_on" name="blog_public" value="1" <?php if ( !isset( $_POST['blog_public'] ) || $_POST['blog_public'] == '1' ) { ?>checked="checked"<?php } ?> />
                <strong><?php _e( 'Yes' ); ?></strong>
            </label>
            <label class="checkbox" for="blog_public_off">
                <input type="radio" id="blog_public_off" name="blog_public" value="0" <?php if ( isset( $_POST['blog_public'] ) && $_POST['blog_public'] == '0' ) { ?>checked="checked"<?php } ?> />
                <strong><?php _e( 'No' ); ?></strong>
            </label>
        </p>
	</div>

			<?php
			/**
			 * Fires after the site sign-up form.
			 *
			 * @since 3.0.0
			 *
			 * @param array $errors An array possibly containing 'blogname' or 'blog_title' errors.
			 */
			do_action( 'signup_blogform', $errors );
		}


	}

}


