<?php

/*
Plugin Name: Pro Sites (Feature: Limit Writing)
*/

class ProSites_Module_Writing {

	static $user_label;
	static $user_description;

	// Module name for registering
	public static function get_name() {
		return __('Limit Publishing', 'psts');
	}

	// Module description for registering
	public static function get_description() {
		return __('Allows you to only enable writing posts and/or pages for selected Pro Site levels.', 'psts');
	}

	function __construct() {
		if( is_main_site( get_current_blog_id() ) ) {
			return;
		}
//		add_action( 'psts_settings_page', array( &$this, 'settings' ) );
		add_filter( 'psts_settings_filter', array( &$this, 'settings_process' ), 10, 2 );
		add_action( 'admin_notices', array( &$this, 'message' ) );
		add_filter( 'user_has_cap', array( &$this, 'write_filter' ), 10, 3 );

		self::$user_label       = __( 'Limit Publishing', 'psts' );
		self::$user_description = __( 'Limited post and pages content creation', 'psts' );
	}

	function write_filter( $allcaps, $caps, $args ) {
		global $psts;

		if ( ! is_pro_site( false, $psts->get_setting( 'publishing_level', 1 ) ) ) {
			//limit posts
			if ( $psts->get_setting( 'publishing_posts' ) ) {
				unset( $allcaps["publish_posts"] );
			}
			//limit pages
			if ( $psts->get_setting( 'publishing_pages' ) ) {
				unset( $allcaps["publish_pages"] );
			}
		}

		return $allcaps;
	}

	function settings_process( $settings, $active_tab ) {

		if( 'writing' == $active_tab ) {
			$settings['publishing_posts'] = isset( $settings['publishing_posts'] ) ? 1 : 0;
			$settings['publishing_pages'] = isset( $settings['publishing_pages'] ) ? 1 : 0;
		}

		return $settings;
	}

	function settings() {
		global $psts;
		$levels = (array) get_site_option( 'psts_levels' );
		?>
<!--		<div class="postbox">-->
<!--			<h3 class="hndle" style="cursor:auto;"><span>--><?php //_e( 'Limit Publishing', 'psts' ) ?><!--</span> --->
<!--				<span class="description">--><?php //_e( 'Allows you to only enable writing posts and/or pages for selected Pro Site levels.', 'psts' ) ?><!--</span>-->
<!--			</h3>-->

			<div class="inside">
				<table class="form-table">
					<tr valign="top">
						<th scope="row" class="psts-help-div psts-limit-prolevel"><?php echo __( 'Pro Site Level', 'psts' ) . $psts->help_text( __( 'Select the minimum level required to enable publishing posts or pages.', 'psts' ) ); ?></th>
						<td>
							<select name="psts[publishing_level]" class="chosen">
								<?php
								foreach ( $levels as $level => $value ) {
									?>
									<option value="<?php echo $level; ?>"<?php selected( $psts->get_setting( 'publishing_level', 1 ), $level ) ?>><?php echo $level . ': ' . esc_attr( $value['name'] ); ?></option><?php
								}
								?>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Limit Posts', 'psts' ); ?></th>
						<td>
							<label><input type="checkbox" name="psts[publishing_posts]" value="1"<?php checked( $psts->get_setting( 'publishing_posts' ) ); ?> /> <?php _e( 'Limit', 'psts' ); ?>
							</label></td>
					</tr>
					<tr valign="top">
						<th scope="row" class="psts-help-div psts-post-restricted"><?php echo __( 'Posts Restricted Message', 'psts' ) . $psts->help_text( __( 'Required - This message is displayed on the post screen for sites that don\'t have permissions. "LEVEL" will be replaced with the needed level name.', 'psts' ) ) ?></th>
						<td>
							<input type="text" name="psts[publishing_message_posts]" id="publishing_message_posts" value="<?php echo esc_attr( $psts->get_setting( 'publishing_message_posts' ) ); ?>" style="width: 95%"/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Limit Pages', 'psts' ); ?></th>
						<td>
							<label><input type="checkbox" name="psts[publishing_pages]" value="1"<?php checked( $psts->get_setting( 'publishing_pages' ) ); ?> /> <?php _e( 'Limit', 'psts' ); ?>
							</label></td>
					</tr>
					<tr valign="top">
						<th scope="row" class="psts-help-div psts-page-restricted"><?php echo __( 'Pages Restricted Message', 'psts' ) . $psts->help_text( __( 'Required - This message is displayed on the page screen for sites that don\'t have permissions. "LEVEL" will be replaced with the needed level name.', 'psts' ) ); ?></th>
						<td>
							<input type="text" name="psts[publishing_message_pages]" id="publishing_message_pages" value="<?php echo esc_attr( $psts->get_setting( 'publishing_message_pages' ) ); ?>" style="width: 95%"/>
						</td>
					</tr>
				</table>
			</div>
<!--		</div>-->
	<?php
	}

	function message() {
		global $psts, $current_screen, $blog_id;

		if ( is_pro_site( false, $psts->get_setting( 'publishing_level', 1 ) ) ) {
			return;
		}

		if ( $psts->get_setting( 'publishing_posts' ) && in_array( $current_screen->id, array(
				'edit-post',
				'post'
			) )
		) {
			$notice = str_replace( 'LEVEL', $psts->get_level_setting( $psts->get_setting( 'publishing_level', 1 ), 'name' ), $psts->get_setting( 'publishing_message_posts' ) );
			echo '<div class="error"><p><a href="' . $psts->checkout_url( $blog_id ) . '">' . $notice . '</a></p></div>';
		} else if ( $psts->get_setting( 'publishing_pages' ) && in_array( $current_screen->id, array(
				'edit-page',
				'page'
			) )
		) {
			$notice = str_replace( 'LEVEL', $psts->get_level_setting( $psts->get_setting( 'publishing_level', 1 ), 'name' ), $psts->get_setting( 'publishing_message_pages' ) );
			echo '<div class="error"><p><a href="' . $psts->checkout_url( $blog_id ) . '">' . $notice . '</a></p></div>';
		}
	}

	public static function is_included( $level_id ) {
		switch ( $level_id ) {
			default:
				return false;
		}
	}

	/**
	 * Returns the minimum required level to remove restrictions
	 */
	public static function required_level() {
		global $psts;

		return $psts->get_setting( 'publish_level' );

	}

	public static function get_level_status( $level_id ) {
		global $psts;

		$min_level = $psts->get_setting( 'publishing_level', 1 );

		if( $level_id >= $min_level ) {
			return 'tick';
		} else {
			return 'cross';
		}

	}
}
