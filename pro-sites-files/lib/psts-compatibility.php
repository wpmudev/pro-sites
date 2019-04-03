<?php
/**
 * @package       Pro Sites
 * @subpackage    Compatibility
 * @version       3.6.2
 *
 * @author        Joel James <joel@incsub.com>
 *
 * @copyright (c) 2018, Incsub (http://incsub.com)
 */

/**
 * Class ProSites_Compatibility
 *
 * Class that handles Pro Sites backward compatibility.
 */
class ProSites_Compatibility {

	/**
	 * Initialize and register hooks.
	 *
	 * We will use alternative hooks based on the
	 * version of WP.
	 *
	 * @since 3.6.2
	 */
	public function init() {
		global $wp_version;

		// Hooks available from WP 5.1.
		if ( version_compare( $wp_version, '5.1' ) >= 0 ) {
			add_action( 'wp_insert_site', array( &$this, 'new_blog' ) );
			add_action( 'wp_delete_site', array( &$this, 'delete_blog' ) );
		} else {
			add_action( 'wpmu_new_blog', array( &$this, 'new_blog' ) );
			add_action( 'delete_blog', array( &$this, 'delete_blog' ) );
		}
	}


	/**
	 * Backward compatibility for delete_blog.
	 *
	 * @param int|WP_Site $site Site id or Site.
	 *
	 * @since 3.6.2
	 *
	 * @return void
	 */
	public function delete_blog( $site ) {
		// Get site.
		$site = get_site( $site );
		// Do not continue if not valid.
		if ( empty( $site->id ) ) {
			return;
		}

		// Compatibility hook.
		do_action( 'psts_delete_blog', $site->id, true );
	}

	/**
	 * Backward compatibility for wpmu_new_blog.
	 *
	 * @param int|WP_Site $site Site id or Site.
	 *
	 * @since 3.6.2
	 *
	 * @return void
	 */
	public function new_blog( $site ) {
		// Get site.
		$site = get_site( $site );
		// Do not continue if not valid.
		if ( empty( $site->id ) ) {
			return;
		}

		// Compatibility hook.
		do_action( 'psts_new_blog', $site->id );
	}
}

// Run.
$compatibility = new ProSites_Compatibility();
$compatibility->init();
