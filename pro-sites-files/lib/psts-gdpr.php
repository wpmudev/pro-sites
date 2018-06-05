<?php
/**
 * @package Pro Sites
 * @subpackage GDPR
 * @version 3.5.9.1
 *
 * @author Joel James <joel@incsub.com>
 *
 * @copyright (c) 2018, Incsub (http://incsub.com)
 */


/**
 * Class ProSites_GDPR
 *
 * Class that handles GDPR for Pro Sites.
 */
class ProSites_GDPR {

	/**
	 * ProSites_GDPR initializer.
	 *
	 * Register all hooks for GDPR.
	 */
	public function init() {

		// Add GDPR content to privacy page.
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );

		// Add data to personal data export.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );

		// Remove personal data of the user.
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );

		/**
		 * Filter to disable privacy checkbox.
		 *
		 * @param bool Should add privacy checkbox.
		 */
		if ( apply_filters( 'psts_privacy_check_enabled', true ) ) {

			// Add privacy checkbox to registration form.
			add_action( 'signup_blogform', array( $this, 'add_privacy_policy_confirmation' ) );

			// Verify that privacy terms are agreed by the user.
			add_filter( 'wpmu_validate_blog_signup', array( $this, 'check_privacy_policy_confirmation' ), 15 );
		}
	}


	/**
	 * Add privacy policy content for Pro Sites.
	 *
	 * Uses wp_add_privacy_policy_content
	 *
	 * @since 3.5.9.1
	 *
	 * @return void
	 */
	public function add_privacy_policy_content() {

		// Make sure we don't break things for old versions.
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		// Content.
		$content = __( 'Some details about your registered sites, payment gateway (such as subscription ID, plan ID etc.) are kept in Pro Sites database tables and usermeta.', 'psts' );

		// Add to privacy policy page.
		wp_add_privacy_policy_content(
			'Pro Sites',
			wp_kses_post( wpautop( $content, false ) )
		);
	}

	/**
	 * Add new checkbox to Pro Sites registration.
	 *
	 * New checkbox that asks user to agree to our privacy policy.
	 * The text link will lead to Privacy Policy page.
	 *
	 * @param object $errors WP_Error class.
	 *
	 * @since 3.5.9.1
	 *
	 * @return void
	 */
	public function add_privacy_policy_confirmation( $errors ) {

		// Get privacy policy page id.
		$privacy_link = get_option( 'wp_page_for_privacy_policy', 0 );

		// If privacy page is not setup, do not add.
		if ( empty( $privacy_link ) || 'publish' !== get_post_status( $privacy_link ) ) {
			return;
		}

		// Make sure link is safe.
		$privacy_link = esc_url( get_permalink( $privacy_link ) );

		// Make sure errors parameter is WP_Error object.
		if ( empty( $errors ) ) {
			$errors = new WP_Error();
		}

		$content = '<p>';

		// If error message is found for privacy field, show them too.
		if ( $errmsg = $errors->get_error_message( 'psts_privacy_check' ) ) {
			$content .= '<p class="error">' . $errmsg . '</p>';
		}

		$checked = empty( $_POST['psts_privacy_check'] ) ? false : true;

		$content .= '<input type="checkbox" id="psts_privacy_check" name="psts_privacy_check" value="1" ' . checked( true, $checked, false ) . '/> ';

		/**
		 * Filter to alter privacy checkbox text.
		 *
		 * @param string $content Privacy checkbox text.
		 */
		$content .= apply_filters( 'psts_privacy_check_text', sprintf( __( 'I have read and accept the %sPrivacy and Policy%s' ), '<a href="' . $privacy_link . '" target="_blank">', '</a>' ) );

		$content .= '</p>';

		echo $content;
	}

	/**
	 * Check if user is agreed to the privacy policy.
	 *
	 * Do not allow registration if user is not agreed to the
	 * privacy confirmation. If not checked, set an error message
	 * for the form.
	 *
	 * @param array $result Validation data array.
	 *
	 * @since 3.5.9.1
	 *
	 * @return WP_Error $errors
	 */
	public function check_privacy_policy_confirmation( $result = array() ) {

		// Get privacy policy page id.
		$privacy_link = get_option( 'wp_page_for_privacy_policy', 0 );

		// If privacy page is not setup, do not validate.
		if ( empty( $privacy_link ) || 'publish' !== get_post_status( $privacy_link ) ) {
			return $result;
		}

		// If privacy policy is checked, we are good to go.
		if ( ! empty( $_POST['psts_privacy_check'] ) ) {
			return $result;
		}

		// Make sure errors parameter is WP_Error object.
		if ( empty( $result['errors'] ) ) {
			$result['errors'] = new WP_Error();
		}

		/**
		 * Filter to alter privacy text.
		 *
		 * @param string $error_text Error message.
		 */
		$error_text = apply_filters( 'psts_privacy_check_error_text', __( 'You must read and accept Privacy and Policy.', 'psts' ) );

		// Add error for privacy policy field.
		$result['errors']->add( 'psts_privacy_check', $error_text );

		return $result;
	}

	/**
	 * Register personal data exporter for Pro Sites.
	 *
	 * @param array $exporters Current registered exporters.
	 *
	 * @since 3.5.9.1
	 *
	 * @return array $exporters
	 */
	public function register_exporter( $exporters = array() ) {

		$exporters['psts'] = array(
			'exporter_friendly_name' => __( 'Pro Sites', 'psts' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);

		return $exporters;
	}

	/**
	 * Register eraser for Pro Sites.
	 *
	 * @param array $erasers Current registered erasers.
	 *
	 * @since 3.5.9.1
	 *
	 * @return array $erasers
	 */
	public function register_eraser( $erasers = array() ) {

		$erasers['psts'] = array(
			'eraser_friendly_name' => __( 'Pro Sites', 'psts' ),
			'callback'             => array( $this, 'remove_personal_data' ),
		);

		return $erasers;
	}

	/**
	 * Add export data to the personal data export.
	 *
	 * @param string $email_address User email address.
	 *
	 * @since 3.5.9.1
	 *
	 * @return array
	 */
	public function export_personal_data( $email_address ) {

		// Get the user object.
		$user = get_user_by( 'email', $email_address );
		// Get user profile data including site details.
		$data = $this->user_data( $user->ID );

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase personal data of a user.
	 *
	 * @param string $email_address User email address.
	 *
	 * @since 3.5.9.1
	 *
	 * @return array
	 */
	public function remove_personal_data( $email_address ) {

		// Get the user object.
		$user = get_user_by( 'email', $email_address );
		// Get user profile data including site details.
		$user_blogs    = get_blogs_of_user( $user->ID );
		$items_removed = 0;

		if ( ! empty( $user_blogs ) ) {
			foreach ( $user_blogs as $blog_id => $blog ) {

				// Get the gateway key.
				$gateway = ProSites_Helper_ProSite::get_site_gateway( $blog_id );

				// Attempt to delete blog if required.
				$this->maybe_delete_and_remove( $blog_id, $user->ID );

				// Make sure blog is deleted before deleting Stripe data.
				$blog_data = get_blog_details( $blog_id );

				// If blog is deleted, delete stripe data too.
				if ( 'stripe' === $gateway && ( empty( $blog_data ) || ! empty( $blog_data->deleted ) ) ) {
					$this->delete_stripe_data( $blog_id );
				}

				$items_removed ++;
			}
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => array( __( 'User has been removed from all the sites.', 'psts' ) ),
			'done'           => true,
		);
	}

	/**
	 * Get full data for a user.
	 *
	 * Get all registered sites and payment details
	 * of the user.
	 *
	 * @param int $user_id User id.
	 *
	 * @since 3.5.9.1
	 *
	 * @return array
	 */
	public function user_data( $user_id ) {

		// Get registered blogs of the user.
		$user_blogs = get_blogs_of_user( $user_id );
		$data       = $blog_data = array();
		$i          = 0;

		// If user doesn't have any blogs, no need.
		if ( empty( $user_blogs ) ) {
			return $data;
		}

		// Loop through all blogs and add data.
		foreach ( $user_blogs as $blog_id => $blog ) {

			// Switch to blog.
			switch_to_blog( $blog_id );

			// Continue only if user is the admin.
			if ( ! current_user_can( 'edit_pages' ) ) {
				continue;
			}

			restore_current_blog();

			// Basic details about the blog.
			$blog_data = array(
				array(
					'name'  => __( 'Site Title', 'psts' ),
					'value' => $blog->blogname
				),
				array(
					'name'  => __( 'Site URL', 'psts' ),
					'value' => $blog->siteurl
				),
			);
			// Add payment details also.
			$this->set_payment_data( $blog_id, $blog_data );

			// For only the first item, add a group too.
			if ( $i === 0 ) {
				$data[] = array(
					'group_id'    => 'user_sites_detail',
					'group_label' => __( 'Sites', 'psts' ),
					'item_id'     => "blogs->{$blog_id}",
					'data'        => $blog_data
				);
			} else {
				$data[] = array(
					'item_id' => "blogs->{$blog_id}",
					'data'    => $blog_data
				);
			}
		}

		return $data;
	}

	/**
	 * Maybe delete blog or remove from blog.
	 *
	 * If user is an admin and no other admins users exist,
	 * delete user from the site. Else, remove him from the site.
	 *
	 * @param int $blog_id Blog ID.
	 * @param int $user_id User ID.
	 *
	 * @return void
	 */
	private function maybe_delete_and_remove( $blog_id, $user_id ) {

		// Switch to blog.
		switch_to_blog( $blog_id );

		$users_query = new WP_User_Query( array(
			'role' => 'administrator',
			'exclude' => array( $user_id )
		) );

		$other_admins = $users_query->get_results();

		// Continue only if user is the only admin.
		if ( empty( $other_admins ) ) {

			/**
			 * Filter to enable/disable dropping tables.
			 *
			 * @param bool $should_drop
			 */
			$should_drop = apply_filters( 'psts_should_drop_on_removal', true );

			// We are deleting the site permanently.
			wpmu_delete_blog( $blog_id, $should_drop );
		} else {

			// Else remove user from that site.
			remove_user_from_blog( $user_id, $blog_id );
		}

		// Restore to current site.
		restore_current_blog();
	}

	/**
	 * Set payment details of the blog.
	 *
	 * @param int $blog_id Blog ID.
	 * @param array $blog_data Blog data.
	 *
	 * @since 3.5.9.1
	 *
	 * @return void
	 */
	private function set_payment_data( $blog_id, &$blog_data ) {

		if ( ! is_pro_site() ) {

			// Is it a pro site.
			$blog_data[] = array(
				'name'  => __( 'Pro Sites', 'psts' ),
				'value' => __( 'No', 'psts' ),
			);
		} else {

			// Is it a pro site.
			$blog_data[] = array(
				'name'  => __( 'Pro Sites', 'psts' ),
				'value' => __( 'Yes', 'psts' ),
			);

			// Get the gateway key.
			$gateway = ProSites_Helper_ProSite::get_site_gateway( $blog_id );
			// Get the gateway name.
			$nice_name = ProSites_Helper_Gateway::get_nice_name( $gateway );

			// If we have a gateway name add that.
			if ( ! empty( $nice_name ) ) {
				$blog_data[] = array(
					'name'  => __( 'Gateway', 'psts' ),
					'value' => $nice_name
				);
			}

			// Get customer id and subscription id if stripe.
			if ( 'stripe' === $gateway ) {
				$this->set_stripe_data( $blog_id, $blog_data );
			}
		}
	}

	/**
	 * Set stripe customer id and subscription id.
	 *
	 * We have separate table that stores customer id and
	 * subscription id of the payment. Add them.
	 *
	 * @param int $blog_id Blog ID.
	 * @param array $blog_data Blog data.
	 *
	 * @since 3.5.9.1
	 *
	 * @return void
	 */
	private function set_stripe_data( $blog_id, &$blog_data ) {

		global $wpdb;

		// Get customer id and subscription id.
		$sql    = $wpdb->prepare( "SELECT customer_id, subscription_id FROM {$wpdb->base_prefix}pro_sites_stripe_customers WHERE blog_ID = %s", $blog_id );
		$result = $wpdb->get_row( $sql );

		// Set details to blog data.
		if ( ! empty( $result ) ) {
			// Customer data.
			$blog_data[] = array(
				'name'  => __( 'Customer ID', 'psts' ),
				'value' => $result->customer_id
			);
			// Subscription data.
			$blog_data[] = array(
				'name'  => __( 'Subscription ID', 'psts' ),
				'value' => $result->subscription_id
			);
		}
	}

	/**
	 * Delete stripe customer id and subscription id.
	 *
	 * We have separate table that stores customer id and
	 * subscription id of the payment. Delete them.
	 *
	 * @param int $blog_id Blog ID.
	 *
	 * @since 3.5.9.1
	 *
	 * @return void
	 */
	private function delete_stripe_data( $blog_id ) {

		global $wpdb;

		// Delete the customer id and subscription id.
		$wpdb->delete( $wpdb->base_prefix . 'pro_sites_stripe_customers', array( 'blog_ID' => $blog_id ) );
	}
}

// Run.
$gdpr = new ProSites_GDPR();
$gdpr->init();