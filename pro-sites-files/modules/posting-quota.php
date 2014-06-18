<?php

/*
Plugin Name: Pro Sites (Feature: Posting Quota)
*/

class ProSites_Module_PostingQuota {

	static $user_label;
	static $user_description;

	function __construct() {
		add_action( 'psts_settings_page', array( &$this, 'settings' ) );
		add_filter( 'psts_settings_filter', array( &$this, 'settings_process' ) );

		add_action( 'admin_notices', array( &$this, 'message' ) );
		add_filter( 'user_has_cap', array( &$this, 'write_filter' ), 10, 3 );

		self::$user_label       = __( 'Posting Quotas', 'psts' );
		self::$user_description = __( 'Limited post types', 'psts' );
	}

	function write_filter( $allcaps, $caps, $args ) {
		global $psts;

		if ( ! is_pro_site( false, $psts->get_setting( 'pq_level', 1 ) ) ) {
			$quota_settings = $psts->get_setting( "pq_quotas" );
			if ( is_array( $quota_settings ) ) {
				foreach ( $quota_settings as $post_type => $settings ) {
					if ( is_numeric( @$settings['quota'] ) && wp_count_posts( $post_type )->publish >= @$settings['quota'] ) {
						$pt_obj = get_post_type_object( $post_type );
						unset( $allcaps[ $pt_obj->cap->publish_posts ] );
					}
				}
			}
		}

		return $allcaps;
	}

	function settings_process( $settings ) {
		global $psts;

		if ( is_array( $settings['pq_quotas'] ) ) {
			$caps = array();
			foreach ( $settings['pq_quotas'] as $post_type => $vars ) {
				$pt_obj = get_post_type_object( $post_type );
				//check if 
				if ( isset( $caps[ $pt_obj->cap->publish_posts ] ) ) {
					$settings['pq_quotas'][ $post_type ]['quota'] = $caps[ $pt_obj->cap->publish_posts ];
				} else {
					$caps[ $pt_obj->cap->publish_posts ] = @$vars['quota'];
				}
			}
		}

		return $settings;
	}

	function settings() {
		global $psts;
		?>
		<div class="postbox">
			<h3 class="hndle" style="cursor:auto;"><span><?php _e( 'Post/Page Quotas', 'psts' ) ?></span> -
				<span class="description"><?php _e( 'Allows you to limit the number of post types for selected Pro Site levels.', 'psts' ) ?></span>
			</h3>

			<div class="inside">
				<table class="form-table post-page-quota">
					<tr valign="top">
						<th scope="row" class="pro-site-level"><?php echo __( 'Pro Site Level', 'psts' ) . '<img width="16" height="16" src="' . $psts->plugin_url . 'images/help.png" class="help_tip"><div class="psts-help-text-wrapper period-desc"><div class="psts-help-arrow-wrapper"><div class="psts-help-arrow"></div></div><div class="psts-help-text">' . __( 'Select the minimum level required to remove quotas', 'psts' ) . '</div></div>'; ?></th>
						<td>
							<select name="psts[pq_level]" class="chosen">
								<?php
								$levels = (array) get_site_option( 'psts_levels' );
								foreach ( $levels as $level => $value ) {
									?>
									<option value="<?php echo $level; ?>"<?php selected( $psts->get_setting( 'pq_level', 1 ), $level ) ?>><?php echo $level . ': ' . esc_attr( $value['name'] ); ?></option><?php
								}
								?>
							</select>
						</td>
					</tr>
					<?php
					$quota_settings = $psts->get_setting( "pq_quotas" );
					$post_types     = get_post_types( array( 'show_ui' => true ), 'objects', 'and' );
					$caps           = array();
					foreach ( $post_types as $post_type ) {
						$quota     = isset( $quota_settings[ $post_type->name ]['quota'] ) ? $quota_settings[ $post_type->name ]['quota'] : 'unlimited';
						$quota_msg = isset( $quota_settings[ $post_type->name ]['message'] ) ? $quota_settings[ $post_type->name ]['message'] : sprintf( __( 'To publish more %s, please upgrade to LEVEL &raquo;', 'psts' ), $post_type->label );
						?>
						<tr valign="top">
							<th scope="row"><?php printf( __( '%s Quota', 'psts' ), $post_type->label ); ?></th>
						</tr>
						<tr>
							<td><?php printf( __( 'Post Limit', 'psts' ), $post_type->label ); ?></td>
							<td>
								<?php if ( isset( $caps[ $post_type->cap->publish_posts ] ) ) { ?>
									<select disabled="disabled" class="chosen">
										<option><?php printf( __( 'Same as %s', 'psts' ), $caps[ $post_type->cap->publish_posts ] ); ?></option>
									</select>
								<?php } else { ?>
									<select name="psts[pq_quotas][<?php echo $post_type->name; ?>][quota]" class="chosen">
										<option value="unlimited"<?php selected( $quota, 'unlimited' ); ?>><?php _e( 'Unlimited', 'psts' ); ?></option>
										<?php
										for ( $counter = 1; $counter <= 1000; $counter ++ ) {
											echo '<option value="' . $counter . '"' . ( $counter == $quota ? ' selected' : '' ) . '>' . number_format_i18n( $counter ) . '</option>' . "\n";
										}
										?>
									</select>
								<?php } ?>
							</td>
						</tr>
						<tr>
							<td class="upgrade-message"><?php echo __( 'Upgrade message', 'psts' ) . '<img width="16" height="16" src="' . $psts->plugin_url . 'images/help.png" class="help_tip"><div class="psts-help-text-wrapper period-desc"><div class="psts-help-arrow-wrapper"><div class="psts-help-arrow"></div></div><div class="psts-help-text">' . __( 'Displayed on the respective add post, page or media screen for sites that have used up their quota. "LEVEL" will be replaced with the needed level name', 'psts' ) . '</div></div>'; ?></td>
							<td>
								<input type="text" name="psts[pq_quotas][<?php echo $post_type->name; ?>][message]" value="<?php echo esc_attr( $quota_msg ); ?>" style="width: 90%"/>
							</td>
						</tr>
						<?php
						$caps[ $post_type->cap->publish_posts ] = $post_type->label;
					}
					?>
				</table>
			</div>
		</div>
	<?php
	}

	function message() {
		global $psts, $current_screen, $post_type, $blog_id;

		if ( is_pro_site( false, $psts->get_setting( 'pq_level', 1 ) ) ) {
			return;
		}

		if ( in_array( $current_screen->id, array( 'edit-post', 'post', 'edit-page', 'page' ) ) ) {
			$quota_settings = $psts->get_setting( "pq_quotas" );
			if ( is_array( $quota_settings ) ) {
				if ( isset( $quota_settings[ $post_type ] ) ) {
					if ( is_numeric( @$quota_settings[ $post_type ]['quota'] ) && wp_count_posts( $post_type )->publish >= @$quota_settings[ $post_type ]['quota'] ) {
						$notice = str_replace( 'LEVEL', $psts->get_level_setting( $psts->get_setting( 'pq_level', 1 ), 'name' ), @$quota_settings[ $post_type ]['message'] );
						echo '<div class="error"><p><a href="' . $psts->checkout_url( $blog_id ) . '">' . $notice . '</a></p></div>';
					}
				}
			}
		}
	}

	public static function is_included( $level_id ) {
		switch ( $level_id ) {
			default:
				return false;
		}
	}
}

//register the module
psts_register_module( 'ProSites_Module_PostingQuota', __( 'Post/Page Quotas', 'psts' ), __( 'Allows you to limit the number of post types for selected Pro Site levels.', 'psts' ) );
?>