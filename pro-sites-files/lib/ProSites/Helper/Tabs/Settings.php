<?php
/**
 * @copyright Incsub (http://incsub.com/)
 *
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,
 * MA 02110-1301 USA
 *
 */

if ( ! class_exists( 'ProSites_Helper_Tabs_Settings' ) ) {
	class ProSites_Helper_Tabs_Settings extends ProSites_Helper_Tabs {

		public static function render( $callback_parent = 'ProSites_Helper_Tabs', $settings_header = array(), $options = array(), $persistent = array() ) {
			parent::render_child( get_class(), $callback_parent, $settings_header, $options, $persistent );
		}

		public static function get_active_tab() {
			return parent::get_active_tab_child( get_class() );
		}

		public static function get_tabs() {

			$section_options = array(
				'header_save_button' => true,
				'button_name'        => 'settings',
			);

			$tabs = array(
				'general'             => array_merge( $section_options, array(
					'title' => __( 'General Settings', 'psts' ),
					'desc'  => array(
						__( 'Setup the basic settings for your Pro Sites network.', 'psts' ),
					),
				) ),
				'email'               => array_merge( $section_options, array(
					'title' => __( 'E-mail Notifications', 'psts' ),
					'desc'  => array(
						__( '"LEVEL", "SITENAME", "SITEURL" and "CHECKOUTURL" will be replaced with their associated values. No HTML allowed.', 'psts' ),
					),
				) ),
				'payment'             => array_merge( $section_options, array(
					'title' => __( 'Currency Settings', 'psts' ),
					'desc'  => array(
						__( 'These preferences affect display only. Your payment gateway of choice may not support every currency listed here.', 'psts' ),
					),
				) ),
				'taxes'               => array_merge( $section_options, array(
					'title' => __( 'TAX Settings', 'psts' ),
					'desc'  => array(
						__( 'Setting up TAX for compliance and legal requirements.', 'psts' ),
					),
				) ),
				'ads'                 => array_merge( $section_options, array(
					'title' => __( 'Advertising', 'psts' ),
					'desc'  => array(
						__( 'Allows you to disable ads for a Pro Site level, or give a Pro Site level the ability to disable ads on a number of other sites.', 'psts' ),
					),
				) ),
				'prowidget'           => array_merge( $section_options, array(
					'title' => __( 'Pro Sites Widget', 'psts' ),
					'desc'  => array(
						__( 'Allows Pro Sites to put a widget in their sidebar to proudly display their Pro level.', 'psts' ),
					),
				) ),
				'buddypress'          => array_merge( $section_options, array(
					'title' => __( 'Limit BuddyPress Features', 'psts' ),
					'desc'  => array(
						__( 'Allows you to limit BuddyPress group creation and messaging to users of a Pro Site.', 'psts' ),
					),
				) ),
				'bulkupgrades'        => array_merge( $section_options, array(
					'title' => __( 'Bulk Upgrades', 'psts' ),
					'desc'  => array(
						__( 'Allows you to sell Pro Site level upgrades in bulk packages.', 'psts' ),
					),
				) ),
				'paytoblog'           => array_merge( $section_options, array(
					'title' => __( 'Pay to Blog', 'psts' ),
					'desc'  => array(
						__( 'Allows you to completely disable a site both front end and back until paid.', 'psts' ),
					),
				) ),
				'throttling'          => array_merge( $section_options, array(
					'title' => __( 'Post/Page Throttling', 'psts' ),
					'desc'  => array(
						__( 'Allows you to limit the number of posts/pages to be published daily/hourly per site.', 'psts' ),
					),
				) ),
				'quotas'              => array_merge( $section_options, array(
					'title' => __( 'Post/Page Quotas', 'psts' ),
					'desc'  => array(
						__( 'Allows you to limit the number of post types for selected minimum Pro Site level.', 'psts' ),
					),
				) ),
				'renaming'            => array_merge( $section_options, array(
					'title' => __( 'Rename Plugin/Theme Features', 'psts' ),
					'desc'  => array(
						__( 'Allows you to rename your Premium Themes and Premium Plugins packages.', 'psts' ),
					),
				) ),
				'support'             => array_merge( $section_options, array(
					'title' => __( 'Premium Support', 'psts' ),
					'desc'  => array(
						__( 'Allows you to provide a premium direct to email support page for selected Pro Site levels.', 'psts' ),
					),
				) ),
				'upload_quota'        => array_merge( $section_options, array(
					'title' => __( 'Upload Quotas', 'psts' ),
					'desc'  => array(
						__( 'Allows you to give additional upload space to Pro Sites.', 'psts' ),
					),
				) ),
				'upgrade_admin_links' => array_merge( $section_options, array(
					'title' => __( 'Upgrade Admin Menu Links', 'psts' ),
					'desc'  => array(
						__( 'Allows you to add custom menu items in admin panel that will encourage admins to get higher level by redirecting to upgrade page.', 'psts' ),
					),
				) ),
				'filters'             => array_merge( $section_options, array(
					'title' => __( 'Unfiltered HTML', 'psts' ),
					'desc'  => array(
						__( 'Allows you to provide the "unfiltered_html" permission to specific user types for selected Pro Site levels.', 'psts' ),
					),
				) ),
				'writing'             => array_merge( $section_options, array(
					'title' => __( 'Limit Publishing', 'psts' ),
					'desc'  => array(
						__( 'Allows you to only enable writing posts and/or pages for selected Pro Site levels.', 'psts' ),
					),
				) ),
				'xmlrpc'              => array_merge( $section_options, array(
					'title' => __( 'Restrict XML-RPC', 'psts' ),
					'desc'  => array(
						__( 'Allows you to only enable XML-RPC for selected Pro Site levels.', 'psts' ),
					),
				) ),
			);

			$page = sanitize_html_class( @$_GET['page'], 'general' );

			foreach ( $tabs as $key => $tab ) {
				$tabs[ $key ]['url'] = sprintf(
					'admin.php?page=%1$s&tab=%2$s',
					esc_attr( $page ),
					esc_attr( $key )
				);
			}

			$tabs = self::remove_disabled_module_tabs( $tabs );

			return apply_filters( 'prosites_settings_tabs', $tabs );
		}

		public static function remove_disabled_module_tabs( $tabs ) {
			global $psts;

			$modules = $psts->get_setting( 'modules_enabled' );
			$modules = ! empty( $modules ) ? $modules : array();

			if ( ! in_array( 'ProSites_Module_Ads', $modules ) ) {
				unset( $tabs['ads'] );
			}
			if ( ! in_array( 'ProSites_Module_BulkUpgrades', $modules ) ) {
				unset( $tabs['bulkupgrades'] );
			}
			if ( ! in_array( 'ProSites_Module_BP', $modules ) ) {
				unset( $tabs['buddypress'] );
			}
			if ( ! in_array( 'ProSites_Module_Writing', $modules ) ) {
				unset( $tabs['writing'] );
			}
			if ( ! in_array( 'ProSites_Module_PayToBlog', $modules ) ) {
				unset( $tabs['paytoblog'] );
			}
			if ( ! in_array( 'ProSites_Module_PostThrottling', $modules ) ) {
				unset( $tabs['throttling'] );
			}
			if ( ! in_array( 'ProSites_Module_PostingQuota', $modules ) ) {
				unset( $tabs['quotas'] );
			}
			if ( ! in_array( 'ProSites_Module_Support', $modules ) ) {
				unset( $tabs['support'] );
			}
			if ( ! in_array( 'ProSites_Module_ProWidget', $modules ) ) {
				unset( $tabs['prowidget'] );
			}
			if ( ! in_array( 'ProSites_Module_XMLRPC', $modules ) ) {
				unset( $tabs['xmlrpc'] );
			}
			if ( ! in_array( 'ProSites_Module_UnfilterHtml', $modules ) ) {
				unset( $tabs['filters'] );
			}
			if ( ! in_array( 'ProSites_Module_Quota', $modules ) ) {
				unset( $tabs['upload_quota'] );
			}
			if ( ! in_array( 'ProSites_Module_PremiumThemes', $modules ) && ! in_array( 'ProSites_Module_Plugins', $modules ) ) {
				unset( $tabs['renaming'] );
			}
			if ( ! in_array( 'ProSites_Module_UpgradeAdminLinks', $modules ) ) {
				unset( $tabs['upgrade_admin_links'] );
			}


			$modules = array(
				'ProSites_Module_Ads',
				'ProSites_Module_BulkUpgrades',
				'ProSites_Module_BP',
				'ProSites_Module_Writing',
				'ProSites_Module_PayToBlog',
				'ProSites_Module_PostThrottling',
				'ProSites_Module_PostingQuota',
				'ProSites_Module_Plugins',
				'ProSites_Module_Support',
				'ProSites_Module_PremiumThemes',
				'ProSites_Module_ProWidget',
				'ProSites_Module_XMLRPC',
				'ProSites_Module_UnfilterHtml',
				'ProSites_Module_Quota',
				'ProSites_Module_UpgradeAdminLinks',
			);


			return $tabs;
		}

	}

}
