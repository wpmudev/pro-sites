<?php

if ( ! class_exists( 'ProSites_Helper_Integration_BuddyPress' ) ) {

	class ProSites_Helper_Integration_BuddyPress {

		public static function init() {

			// Fix registration
			add_action( 'init', array( get_class(), 'fix_registration' ) );

			// BuddyPress Hooks
			add_action( 'bp_include', array( get_class(), 'buddypress_hooks' ) );

		}

		public static function buddypress_hooks() {
			global $psts;

			//Buddypress Activation emails
			//If pay before blog is disabled, allow blog activation through email
			$show_signup = $psts->get_setting( 'show_signup' );

			if ( ! empty( $show_signup ) ) {
				remove_filter( 'wpmu_signup_blog_notification', 'bp_core_activation_signup_blog_notification', 1, 7 );
				remove_filter( 'update_welcome_user_email', 'bp_core_filter_user_welcome_email' );
				remove_filter( 'update_welcome_email', 'bp_core_filter_blog_welcome_email', 10, 4 );
			}

			add_filter( 'bp_core_get_root_options', array( get_class(), 'remove_site_registration' ) );
			add_filter( 'bp_registration_needs_activation', array( $psts, 'disable_user_activation_mail' ), 10 );
			add_filter( 'bp_core_signup_send_activation_key', array( $psts, 'disable_user_activation_mail' ), 10 );

		}

		/**
		 * Removes ability for site to be created with BuddyPress signup
		 *
		 * Only if allowing signup at checkout
		 *
		 * @param $options
		 *
		 * @return mixed
		 */
		public static function remove_site_registration( $options ) {
			global $psts;

			$show_signup = $psts->get_setting( 'show_signup' );

			if( ! empty( $show_signup ) ) {

				$active_signup = get_site_option( 'registration', 'none' );
				$active_signup = apply_filters( 'wpmu_active_signup', $active_signup );

				if ( 'all' == $active_signup ) {
					$options['registration'] = 'user';
				}
				if ( 'blog' == $active_signup ) {
					$options['registration'] = 'none';
				}
			}

			return $options;
		}

		/**
		 * Fixes the ProSites checkout if BuddyPress registration page is set to checkout page
		 */
		public static function fix_registration() {
			global $psts;

			if( function_exists( 'bp_core_get_directory_page_ids' ) ) {

				$bp_directory_page_ids = bp_core_get_directory_page_ids();

				if( ! empty( $bp_directory_page_ids['register'] ) ) {
					$register_url = get_permalink( $bp_directory_page_ids['register'] );
				}

				if ( bp_is_current_component( 'register' ) && $register_url == $psts->checkout_url() ) {
					remove_action( 'bp_init', 'bp_core_wpsignup_redirect' );
					remove_action( 'bp_screens', 'bp_core_screen_signup' );
				}

			}

		}

	}

}