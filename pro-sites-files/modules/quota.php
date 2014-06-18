<?php

/*
Pro Sites (Module: Upload Quota)
*/

class ProSites_Module_Quota {

	static $user_label;
	static $user_description;

	public $checkout_name, $checkout_desc;

	function __construct() {
		add_action( 'psts_settings_page', array( &$this, 'settings' ) );
		add_action( 'psts_settings_process', array( &$this, 'settings_process' ) );

		//filter blog and site options
		if ( ! defined( 'PSTS_QUOTA_ALLOW_OVERRIDE' ) ) {
			add_filter( 'pre_option_blog_upload_space', array( &$this, 'filter' ) );
		}
		add_filter( 'pre_site_option_blog_upload_space', array( &$this, 'filter' ) );

		//add messages
		add_action( 'activity_box_end', array( &$this, 'message' ), 11 );
		add_action( 'pre-upload-ui', array( &$this, 'message' ), 11 );
		add_action( 'admin_notices', array( &$this, 'out_message' ) );

		self::$user_label = __( 'Quota', 'psts' );
		self::$user_description = __( 'Upload quota', 'psts' );

		//add checkout grid text
		$this->checkout_name = __( 'Upload Space', 'psts' ); //can only i18n in _construct()
		$this->checkout_desc = __( 'Get additional upload space for your images, media, and other files.', 'psts' ); //can only i18n in _construct()
		add_filter( 'psts_checkout_item-ProSites_Module_Quota', array(
				&$this,
				'checkout_output'
			), 10, 2 ); //note the classname is part of the filter
	}

	//get's passed a given level, return string for custom text, or bool for checkmark
	function checkout_output( $null, $level ) {
		global $psts;

		//for free level use core function
		if ( 0 == $level ) {
			return $this->display_space( get_space_allowed() );
		}

		//pro level, get setting
		$quota = $psts->get_level_setting( $level, 'quota' );

		return $this->display_space( $quota );
	}

	//changed in 2.0 to filter return as to be non-permanent.
	function filter( $space ) {
		global $psts;

		//don't filter on network settings page to avoid confusion
		if ( is_network_admin() ) {
			return $space;
		}
		$level = $psts->get_level();
		$quota = $psts->get_level_setting( $level, 'quota' );
		if ( $quota && is_pro_site( false, $level ) ) {
			return $quota;
		} else if ( function_exists( 'psts_hide_ads' ) && psts_hide_ads() && $quota = $psts->get_setting( "quota_upgraded_space" ) ) {
			return $quota;
		} else {
			return $space;
		}
	}

	function settings_process() {
		global $psts;

		foreach ( $_POST['quota'] as $level => $quota ) {
			if ( $level == 0 ) {
				$psts->update_setting( "quota_upgraded_space", $quota );
			} else {
				$psts->update_level_setting( $level, 'quota', $quota );
			}
		}
	}

	function settings() {
		global $psts;
		$levels = (array) get_site_option( 'psts_levels' );
		?>
		<div class="postbox">
			<h3 class="hndle" style="cursor:auto;"><span><?php _e( 'Upload Quota', 'psts' ) ?></span> -
				<span class="description"><?php _e( 'Allows you to give additional upload space to Pro Sites.', 'psts' ) ?></span>
			</h3>

			<div class="inside">
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e( 'Quota Amounts', 'psts' ) ?></th>
						<td><?php
							if ( function_exists( 'psts_ads_upgrade_active' ) && psts_ads_upgrade_active() ) {
								$level = 0;
								echo '<label>';
								$quota = $psts->get_setting( "quota_upgraded_space" );
								$quota = $quota ? $quota : get_site_option( 'blog_upload_space' );
								$this->quota_select( $level, $quota );
								echo ' ' . $level . ' - ' . __( 'Ads Removed (Upgraded)', 'psts' ) . '</label><br />';
							}
							foreach ( $levels as $level => $data ) {
								echo '<label>';
								$quota = isset( $data['quota'] ) ? $data['quota'] : get_site_option( 'blog_upload_space' );
								$this->quota_select( $level, $quota );
								echo ' ' . $level . ' - ' . $data['name'] . '</label><br />';
							}
							_e( 'Each level should have an identical or progressively higher quota.', 'psts' );
							?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Quota Message', 'psts' ) ?></th>
						<td>
							<input type="text" name="psts[quota_message]" id="quota_message" value="<?php echo esc_attr( $psts->get_setting( "quota_message" ) ); ?>" style="width: 95%"/>
							<br/><?php _e( 'Required - This message is displayed on the dashboard and media upload form as an advertisment to upgrade to the next level. "LEVEL" will be replaced with the needed level name, and "SPACE" will be replaced with the extra upload space in the next level.', 'psts' ) ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Out of Space Message', 'psts' ) ?></th>
						<td>
							<input type="text" name="psts[quota_out_message]" id="quota_out_message" value="<?php echo esc_attr( $psts->get_setting( "quota_out_message" ) ); ?>" style="width: 95%"/>
							<br/><?php _e( 'Required - This message is displayed on the dashboard when out of upload space. "LEVEL" will be replaced with the needed level name, and "SPACE" will be replaced with the extra upload space in the next level.', 'psts' ) ?>
						</td>
					</tr>
				</table>
				<span class="description"><?php _e( 'NOTE: If you need to be able to override the upload quota on a per site basis when editing the site, add this to your wp-config.php file:', 'psts' ) ?>
					<pre>define('PSTS_QUOTA_ALLOW_OVERRIDE', true);</pre></span>
			</div>
		</div>
	<?php
	}

	function quota_select( $level, $selected ) {
		?>
		<select name="quota[<?php echo $level; ?>]" id="quota_<?php echo $level; ?>">
			<?php
			for ( $counter = 1; $counter < 10; $counter += 1 ) {
				echo '<option value="' . $counter . '"' . ( $counter == $selected ? ' selected' : '' ) . '>' . number_format_i18n( $counter ) . ' MB</option>' . "\n";
			}
			for ( $counter = 10; $counter < 100; $counter += 5 ) {
				echo '<option value="' . $counter . '"' . ( $counter == $selected ? ' selected' : '' ) . '>' . number_format_i18n( $counter ) . ' MB</option>' . "\n";
			}
			for ( $counter = 100; $counter < 1000; $counter += 50 ) {
				echo '<option value="' . $counter . '"' . ( $counter == $selected ? ' selected' : '' ) . '>' . number_format_i18n( $counter ) . ' MB</option>' . "\n";
			}
			for ( $counter = 1; $counter <= 100; $counter += 1 ) {
				echo '<option value="' . ( $counter * 1024 ) . '"' . ( ( $counter * 1024 ) == $selected ? ' selected' : '' ) . '>' . number_format_i18n( $counter ) . ' GB</option>' . "\n";
			}
			?>
		</select>
	<?php
	}

	function message() {
		global $psts, $blog_id;
		if ( current_user_can( 'edit_pages' ) ) {
			//if override is set don't show this message
			if ( ! defined( 'PSTS_QUOTA_ALLOW_OVERRIDE' ) && get_option( 'blog_upload_space' ) ) {
				return;
			}

			$level = $psts->get_level() + 1;
			if ( $name = $psts->get_level_setting( $level, 'name' ) ) { //only show if there is a higher level
				$space = $this->display_space( $psts->get_level_setting( $level, 'quota' ) );
				$msg   = str_replace( 'LEVEL', $name, $psts->get_setting( 'quota_message' ) );
				$msg   = str_replace( 'SPACE', $space, $msg );
				echo '<p><strong><a href="' . $psts->checkout_url( $blog_id ) . '">' . $msg . '</a></strong></p>';
			}
		}
	}

	function out_message() {
		global $psts, $blog_id;
		if ( current_user_can( 'edit_pages' ) && ! is_upload_space_available() ) {
			$level = $psts->get_level() + 1;
			if ( $name = $psts->get_level_setting( $level, 'name' ) ) { //only show if there is a higher level
				$space = $this->display_space( $psts->get_level_setting( $level, 'quota' ) );
				$msg   = str_replace( 'LEVEL', $name, $psts->get_setting( 'quota_message' ) );
				$msg   = str_replace( 'SPACE', $space, $msg );
				echo '<div class="error"><p><a href="' . $psts->checkout_url( $blog_id ) . '">' . $msg . '</a></p></div>';
			}
		}
	}

	function display_space( $space ) {
		if ( ! $space ) {
			return '0' . __( 'MB', 'psts' );
		}

		if ( $space > 1000 ) {
			$space = number_format( $space / 1024 );
			/* translators: Gigabytes */
			$space .= __( 'GB', 'psts' );
		} else {
			/* translators: Megabytes */
			$space .= __( 'MB', 'psts' );
		}

		return $space;
	}

	public static function is_included( $level_id ) {
		switch ( $level_id ) {
			default:
				return false;
		}
	}
}

//register the module
psts_register_module( 'ProSites_Module_Quota', __( 'Upload Quota', 'psts' ), __( 'Allows you to give additional upload space to Pro Sites.', 'psts' ) );
?>