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
			if ( ! empty( self::$last_site ) && self::$last_site->blog_ID = $blog_id ) {
				$site = self::$last_site;
			} else {
				$site = self::get_site( $blog_id );
			}

			if ( ! empty( $site ) ) {
				return ProSites_Helper_Gateway::convert_legacy( $site->gateway );
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
			$blog_id = 0;
			$row     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE activation_key = %d", $activation_key ) );
			if ( $row ) {
				$blog_id = domain_exists( $row->domain, $row->path, $wpdb->siteid );
				// As a fallback, try the site domain
				if ( empty( $blog_id ) ) {
					$domain  = $wpdb->get_var( $wpdb->prepare( "SELECT domain FROM $wpdb->site WHERE id = %d", $wpdb->siteid ) );
					$blog_id = domain_exists( $domain, $row->path, $wpdb->siteid );
				}
			}

			return $blog_id;
		}

		public static function redirect_signup_page() {
			global $pagenow, $psts;
			$show_signup = $psts->get_setting( 'show_signup' );
			$bp_redirect = false;

			if ( class_exists( 'BuddyPress' ) && bp_is_register_page() ) {
				$bp_redirect = true;
			}

			if ( ( 'wp-signup.php' == $pagenow || $bp_redirect ) && $show_signup ) {
				//If Blog templates class exists and Page is redirected to add new user, do not add query args
				if ( class_exists( 'blog_templates' ) ) {
					if ( ! empty( $_GET['blog_template'] ) && 'just_user' == $_GET['blog_template'] ) {
						return;
					}
				}

				//Check if already logged in
				$new_blog = add_query_arg( array( "action" => "new_blog" ), $psts->checkout_url() );
				$new_blog = apply_filters( 'psts_redirect_signup_page_url', $new_blog );
				wp_redirect( $new_blog );
				exit();
			}
		}

		public static function get_blog_info( $blog_id ) {
			global $wpdb, $psts;

			$is_recurring  = $psts->is_blog_recurring( $blog_id );
			$trialing      = ProSites_Helper_Registration::is_trial( $blog_id );
			$trial_message = '';
			if ( $trialing ) {
				// assuming its recurring
				$trial_message = '<div id="psts-general-error" class="psts-warning">' . __( 'You are still within your trial period. Once your trial finishes your account will be automatically charged.', 'psts' ) . '</div>';
			}
			$end_date = date_i18n( get_option( 'date_format' ), $psts->get_expire( $blog_id ) );
			$level_id = $psts->get_level( $blog_id );
			$level    = $psts->get_level_setting( $level_id, 'name' );

			$cancel_info_message = $cancel_info_link = '';

			if ( $is_recurring && ! $psts->is_blog_canceled( $blog_id )  ) {
				$cancel_info_message = '<p class="prosites-cancel-description">' . sprintf( __( 'If you choose to cancel your subscription this site should continue to have %1$s features until %2$s.', 'psts' ), $level, $end_date ) . '</p>';
				$cancel_label        = __( 'Cancel Your Subscription', 'psts' );
				// CSS class of <a> is important to handle confirmations
				$cancel_info_link = '<p class="prosites-cancel-link"><a class="cancel-prosites-plan button" href="' . wp_nonce_url( $psts->checkout_url( $blog_id ) . '&action=cancel', 'psts-cancel' ) . '" title="' . esc_attr( $cancel_label ) . '">' . esc_html( $cancel_label ) . '</a></p>';
			}

			// Get other information from database
			$result       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %d", $blog_id ) );
			$period       = false;
			$last_amount  = false;
			$last_gateway = false;
			if ( $result ) {
				$period       = (int) $result->term;
				$last_amount  = floatval( $result->amount );
				$last_gateway = $result->gateway;
			}

			$args = apply_filters( 'psts_blog_info_args',
				array(
					'level_id'                  => apply_filters( 'psts_blog_info_level_id', $level_id, $blog_id ),
					'level'                     => apply_filters( 'psts_blog_info_level', $level, $blog_id ),
					'expires'                   => apply_filters( 'psts_blog_info_expires', $end_date, $blog_id ),
					'trial'                     => apply_filters( 'psts_blog_info_trial', $trial_message, $blog_id ),
					'recurring'                 => apply_filters( 'psts_blog_info_recurring', $is_recurring, $blog_id ),
					'pending'                   => apply_filters( 'psts_blog_info_pending', '', $blog_id ),
					'complete_message'          => apply_filters( 'psts_blog_info_complete_message', '', $blog_id ),
					'thanks_message'            => apply_filters( 'psts_blog_info_thanks_message', '', $blog_id ),
					'visit_site_message'        => apply_filters( 'psts_blog_info_thanks_message', '', $blog_id ),
					'cancel'                    => apply_filters( 'psts_blog_info_cancelled', false, $blog_id ),
					'cancellation_message'      => apply_filters( 'psts_blog_info_cancellation_message', '', $blog_id ),
					'period'                    => apply_filters( 'psts_blog_info_period', $period, $blog_id ),
					// E.g. Visa, Mastercard, PayPal, etc.
					'payment_type'              => apply_filters( 'psts_blog_info_payment_type', false, $blog_id ),
					// E.g. last 4-digits (ok to leave empty)
					'payment_reminder'          => apply_filters( 'psts_blog_info_payment_reminder', false, $blog_id ),
					// Acceptable: end | start | block
					'payment_reminder_location' => apply_filters( 'psts_blog_info_payment_remind_location', 'end', $blog_id ),
					// If its a credit card, the following can be used for expiry information
					'payment_expire_month'      => apply_filters( 'psts_blog_info_payment_expire_month', false, $blog_id ),
					'payment_expire_year'       => apply_filters( 'psts_blog_info_payment_expire_year', false, $blog_id ),
					'last_payment_date'         => apply_filters( 'psts_blog_info_last_payment_date', false, $blog_id ),
					'last_payment_amount'       => apply_filters( 'psts_blog_info_last_payment_amount', $last_amount, $blog_id ),
					'last_payment_gateway'      => apply_filters( 'psts_blog_info_last_payment_gateway', $last_gateway, $blog_id ),
					'next_payment_date'         => apply_filters( 'psts_blog_info_next_payment_date', false, $blog_id ),
					// Information about cancelling
					'cancel_info'               => apply_filters( 'psts_blog_info_cancel_message', $cancel_info_message, $blog_id ),
					// Best not to change this one...
					'cancel_info_link'          => $cancel_info_link,
					'receipt_form'              => $psts->receipt_form( $blog_id ),
					'all_fields'                => apply_filters( 'psts_blog_info_all_fields', true, $blog_id ),
					'payment_failed'            => apply_filters( 'psts_blog_info_payment_failed', false, $blog_id ),
				),
				$blog_id
			);

			return $args;
		}

		/**
		 * Sets meta for a ProSite
		 *
		 * @param array $meta
		 * @param int $blog_id
		 *
		 * @return bool
		 */
		public static function update_prosite_meta( $blog_id = 0, $meta = array() ) {

			if ( false === $meta || empty( $blog_id ) ) {
				return false;
			}
			global $wpdb;

			$updated = $wpdb->update(
				$wpdb->base_prefix . 'pro_sites',
				array(
					'meta' => maybe_serialize( $meta ),
				),
				array(
					'blog_ID' => $blog_id
				)
			);

			return $updated;
		}

		/**
		 * Fetches meta for a ProSite
		 *
		 * @param int $blog_id
		 *
		 * @return bool|mixed|string
		 */
		public static function get_prosite_meta( $blog_id = 0 ) {
			if ( empty( $blog_id ) ) {
				return false;
			}

			global $wpdb;
			$meta   = false;
			$result = $wpdb->get_row( $wpdb->prepare( "SELECT meta FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %s", $blog_id ) );
			if ( ! empty( $result ) ) {
				$meta = maybe_unserialize( $result->meta );
			}

			return $meta;
		}

		public static function start_session() {
			//Start Session if not there already, required for sign up.
			if ( ! session_id() ) {
				session_start();
			}
		}

		/**
		 *  Get the AJAX url.
		 *
		 *  Fixes potential issue with Domain Mapping plugin.
		 */
		public static function admin_ajax_url() {
			$path   = "admin-ajax.php";
			$scheme = ( is_ssl() || force_ssl_admin() ? 'https' : 'http' );

			if ( class_exists( 'domain_map' ) ) {
				global $dm_map;

				return $dm_map->domain_mapping_admin_url( admin_url( $path, $scheme ), '/', false );
			} else {
				return admin_url( $path, $scheme );
			}

		}

		/**
		 * Update pricing level order, Updates Pro site settings
		 *
		 * @param $levels
		 *
		 */
		public static function update_level_order( $levels ) {
			$data         = array();
			$data['psts'] = array();

			$pricing_levels_order = array();

			foreach ( $levels as $level_code => $level ) {
				$pricing_levels_order[] = $level_code;
			}

			//Get and update psts settings
			// get settings
			$old_settings                         = get_site_option( 'psts_settings' );
			$data['psts']['pricing_levels_order'] = implode( ',', $pricing_levels_order );
			$settings                             = array_merge( $old_settings, apply_filters( 'psts_settings_filter', $data['psts'], 'pricing_table' ) );
			update_site_option( 'psts_settings', $settings );
		}

		/**
		 * Check if blog creation is allowed
		 * @return bool
		 */
		public static function allow_new_blog() {
			global $psts;
			$allow_multiple_blog = $psts->get_setting( 'multiple_signup', false );
			//If Multiple blogs are allowed, let them create
			if ( $allow_multiple_blog ) {
				return true;
			}

			//If not loggedin, ofcourse you can create a new blog
			if ( ! is_user_logged_in() ) {
				return true;
			}
			$user_id = get_current_user_id();

			/**
			 * Role to check for while fetching a ist of blogs for the user
			 *
			 * @param string $role Role of the user
			 *
			 */
			$role  = apply_filters( 'psts_user_role', 'administrator' );
			$blogs = self::get_user_blogs_by_role( $user_id, $role );
			if ( count( $blogs ) == 0 ) {
				return true;
			}

			return false;
		}

		/**
		 * Get blogs for the user, with administrative role
		 *
		 * @see http://wordpress.stackexchange.com/questions/72116/how-can-i-display-all-multisite-blogs-where-this-user-is-administrator
		 *
		 * @param int $user_id
		 * @param string $role
		 *
		 * @return array
		 */
		public static function get_user_blogs_by_role( $user_id, $role ) {

			//If $role is not set to administrator, return the default blog count
			if ( empty( $role ) || is_array( $role ) || 'administrator' != $role ) {
				$count = get_blogs_of_user( $user_id, false );

				return $count;
			}

			//Get the list of blog for which given user is administrator
			$out   = array();
			$regex = '~' . $GLOBALS['wpdb']->base_prefix . '(\d+)_capabilities~';
			$meta  = get_user_meta( $user_id );

			if ( ! $meta ) {
				return array();
			}

			foreach ( $meta as $key => $value ) {
				if ( preg_match( $regex, $key, $matches ) ) {
					$roles = maybe_unserialize( $meta[ $key ][0] );

					// the number is a string
					if ( isset ( $roles[ $role ] ) and 1 === (int) $roles[ $role ] ) {
						$out[] = $matches[1];
					}
				}
			}

			return $out;
		}

		/**
		 * Fetch the Gateway Name for the given blog id
		 *
		 * @param $blog_id
		 *
		 * @return string
		 */
		public static function get_site_gateway( $blog_id ) {
			global $wpdb;
			$sql     = $wpdb->prepare( "SELECT `gateway` FROM {$wpdb->base_prefix}pro_sites WHERE blog_ID = %s", $blog_id );
			$result  = $wpdb->get_row( $sql );
			$gateway = ! empty( $result->gateway ) ? strtolower( $result->gateway ) : '';

			return $gateway;
		}

		/**
		 * Returns the default period
		 *
		 * @return int Default active period
		 *
		 */
		public static function default_period() {
			global $psts;

			$active_periods = (array) $psts->get_setting( 'enabled_periods' );

			if ( ! empty( $active_periods ) && is_array( $active_periods ) ) {
				return $active_periods[0];
			}

			return 1;
		}

		/**
		 * Get the scheme for urls.
		 *
		 * If the multisite setup is on sub domains, make sure we have
		 * wild-card ssl is available before applying https to site_url or
		 * admin_url values.
		 *
		 * @param string $scheme Default scheme.
		 *
		 * @return string $scheme
		 */
		public static function ssl_scheme( $scheme = 'admin' ) {

			global $psts;

			if ( is_ssl() ) {
				// Is multisite using sub domain setup?
				$subdomain = ( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL );
				// Is wildcard setting enabled?
				$wildcard = (bool) $psts->get_setting( 'subsites_ssl', 0 );
				// Decide the scheme.
				if ( $subdomain && ! $wildcard ) {
					$scheme = 'http';
				}
			}

			return $scheme;
		}


		/**
		 * Display signups from wp_signups table
		 *
		 * @param int or WP_User or null $user
		 * @param bool signups_status
		 *
		 * @return html
		 */
		public static function render_user_inactive_signups( $user = null, $signups_status = false ) {

			global $psts;

			$user_signups = self::get_user_signups( $user, $signups_status );
			$date_format = get_option( 'date_format' );
			$time_format = get_option( 'time_format' );
			$out = '';

			if ( ! empty( $user_signups ) ) {
				
				$out = '<header class="psts-inactive-sites-header">';
				$out .= '<h2>' . __( 'Inactive sites', 'psts' ) . '</h2>';
				$out .= '<div class="psts-inactive-sites-description">' . __( 'It seems you have some inactive sites. These will be reserved for 48hours from the reservation date. You can activate by clicking the "Activate" link so you can chose one of our available plans.', 'psts' ) . '</div>';
				$out .= '</header>';
				$out .= '<ul class="psts-user-inactive-signups">';

				foreach( $user_signups as $user_signup ) {

					$activation_url = add_query_arg( array(
						'activate-signup' => $user_signup->signup_id
					), $psts->checkout_url() );

					$expiration_date = apply_filters( 'psts-inactive-signup-expire', date( $date_format . ' ' . $time_format, strtotime( $user_signup->registered . '+48 hours' ) ), $user_signup->registered );

					ob_start();
					?>
					<li class="psts-user-inactive-signup psts-user-signup-%1$d">
						<strong class="signup-title">%2$s</strong>
						<span class="signup-date">%3$s %4$s</span>
						<span class="signup-activation"><a href="%6$s" class="button">%5$s</a></span>
					</li>
					<?php
					$out .= sprintf(
						ob_get_clean(),
						$user_signup->signup_id,
						$user_signup->title,
						__( 'Expires on', 'psts' ),
						$expiration_date,
						__( 'Activate', 'psts' ),
						$activation_url
					);
				}

				$out .= '</ul>';

			}

			return $out;
			
		}

		/**
		 * Get signups from wp_signups table
		 *
		 * @param int or WP_User or null $user
		 * @param bool signups_status
		 *
		 * @return WPDB results
		 */
		public static function get_user_signups( $user = null, $signups_status = false ) {

			global $wpdb;

			$user = self::get_user( $user );

			$user_login = $user->user_login;

			$signups_status_q = "AND NOT active";

			if ( $signups_status ) {
				$signups_status_q = "AND active=1";
			}

			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->signups} WHERE user_login = %s {$signups_status_q}", $user_login ) );
		}


		public static function get_user( $user = null ) {

			if ( ! is_null( $user ) ){

				if( ! $user instanceof WP_User ){
					$user = get_user_by( 'ID', intval( $user ) );
				}

			} else {
				if( ! is_user_logged_in() ) {
					return false;
				}

				$user = get_user_by( 'ID', intval( get_current_user_id() ) );
			}

			return $user;
		}


		public static function set_user_signups_session( $signup_id = null, $user = null ) {

			global $wpdb;

			$user = self::get_user( $user );
			$signup_id = ( is_null( $signup_id ) && isset( $_GET['activate-signup'] ) ) ? intval( $_GET['activate-signup'] ) : false;

			if( ! $signup_id || ! self::allow_new_blog() ) {
				return;
			}

			$signup = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->signups} WHERE signup_id = %d AND user_login='%s'", $signup_id, $user->user_login ) );

			ProSites_Helper_Session::session( 'domain', $signup->domain );
			ProSites_Helper_Session::session( 'meta', $signup->meta );
			ProSites_Helper_Session::session( array( 'new_blog_details', 'user_login' ), $user->user_login );
			ProSites_Helper_Session::session( array( 'new_blog_details', 'user_email' ), $user->user_email );
			ProSites_Helper_Session::session( array( 'new_blog_details', 'title' ), $signup->title );
			ProSites_Helper_Session::session( array( 'new_blog_details', 'domain' ), $signup->domain );
			ProSites_Helper_Session::session( array( 'new_blog_details', 'path' ), $signup->path );
			ProSites_Helper_Session::session( array( 'new_blog_details', 'activation_key' ), $signup->activation_key );
			ProSites_Helper_Session::session( 'activation_key', $signup->activation_key );
		}

		/**
		 * Clear signup sessions.
		 */
		public static function clear_user_sessions() {

			ProSites_Helper_Session::unset_session( 'domain' );
			ProSites_Helper_Session::unset_session( 'meta' );
			ProSites_Helper_Session::unset_session( 'new_blog_details' );
			ProSites_Helper_Session::unset_session( 'activation_key' );
		}
		
	}
}