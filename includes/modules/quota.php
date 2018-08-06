<?php

/*
Pro Sites (Module: Upload Quota)
*/

class ProSites_Module_Quota {

	static $user_label;
	static $user_description;

	public $checkout_name, $checkout_desc;

	// Module name for registering
	public static function get_name() {
		return __( 'Upload Quota', 'psts' );
	}

	// Module description for registering
	public static function get_description() {
		return __( 'Allows you to give additional upload space to Pro Sites.', 'psts' );
	}

	static function run_critical_tasks() {
		//filter blog and site options
		if ( ! defined( 'PSTS_QUOTA_ALLOW_OVERRIDE' ) ) {
			add_filter( 'pre_option_blog_upload_space', array( get_class(), 'filter' ) );
		}
		add_filter( 'pre_site_option_blog_upload_space', array( get_class(), 'filter' ) );
		add_filter( 'pre_site_option_upload_space_check_disabled', array(
			get_class(),
			'force_network_quota_checkbox'
		) );
	}

	function __construct() {
		if ( ! is_admin() && is_main_site( get_current_blog_id() ) ) {
			return;
		}
		self::$user_label       = __( 'Quota', 'psts' );
		self::$user_description = __( 'Upload quota', 'psts' );

		//add checkout grid text
		$this->checkout_name = __( 'Upload Space', 'psts' ); //can only i18n in _construct()
		$this->checkout_desc = __( 'Get additional upload space for your images, media, and other files.', 'psts' ); //can only i18n in _construct()

		add_action( 'psts_settings_process', array( &$this, 'settings_process' ), 10, 1 );

		if ( ! is_main_site( get_current_blog_id() ) ) {
			//add messages
			add_action( 'activity_box_end', array( &$this, 'message' ), 11 );
			add_action( 'pre-upload-ui', array( &$this, 'message' ), 11 );
			add_action( 'admin_notices', array( &$this, 'out_message' ) );

			add_filter( 'psts_checkout_item-ProSites_Module_Quota', array(
				&$this,
				'checkout_output'
			), 10, 2 ); //note the classname is part of the filter

			// Using footer to make sure it loads after the media grid
			add_action( 'admin_footer', array( &$this, 'add_quota_to_library' ) );
		}
	}

	//get's passed a given level, return string for custom text, or bool for checkmark
	function checkout_output( $null, $level ) {
		global $psts;

		//for free level use core function
		if ( 0 == $level ) {
			return ProSites_Module_Quota::display_space( get_space_allowed() );
		}

		//pro level, get setting
		$quota = $psts->get_level_setting( $level, 'quota' );

		return ProSites_Module_Quota::display_space( $quota );
	}

	function settings_process( $active_tab ) {

		if ( 'upload_quota' != $active_tab ) {
			return false;
		}

		global $psts;

		$post_quota = isset( $_POST['quota'] ) ? (array) $_POST['quota'] : array();
		foreach ( $post_quota as $level => $quota ) {
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
		<!--		<div class="postbox">-->
		<!--			<h3 class="hndle" style="cursor:auto;"><span>--><?php //_e( 'Upload Quota', 'psts' ) ?><!--</span> --->
		<!--				<span class="description">--><?php //_e( 'Allows you to give additional upload space to Pro Sites.', 'psts' ) ?><!--</span>-->
		<!--			</h3>-->

		<div class="inside">
			<table class="form-table">
				<tr valign="top">
					<th scope="row"
					    class="psts-help-div psts-quota-amounts"><?php echo __( 'Quota Amounts', 'psts' ) . $psts->help_text( __( 'Each level should have an identical or progressively higher quota.', 'psts' ) ); ?></th>
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
						?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"
					    class="psts-help-div psts-quota-message"><?php echo __( 'Quota Message', 'psts' ) . $psts->help_text( __( 'Required - This message is displayed on the dashboard and media upload form as an advertisment to upgrade to the next level. "LEVEL" will be replaced with the needed level name, and "SPACE" will be replaced with the extra upload space in the next level.', 'psts' ) ); ?></th>
					<td>
						<input type="text" name="psts[quota_message]" id="quota_message"
						       value="<?php echo esc_attr( $psts->get_setting( "quota_message" ) ); ?>"
						       style="width: 95%"/>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"
					    class="psts-help-div psts-out-of-space"><?php echo __( 'Out of Space Message', 'psts' ) . $psts->help_text( __( 'Required - This message is displayed on the dashboard when out of upload space. "LEVEL" will be replaced with the needed level name, and "SPACE" will be replaced with the extra upload space in the next level.', 'psts' ) ); ?></th>
					<td>
						<input type="text" name="psts[quota_out_message]" id="quota_out_message"
						       value="<?php echo esc_attr( $psts->get_setting( "quota_out_message" ) ); ?>"
						       style="width: 95%"/>
					</td>
				</tr>
			</table>
			<p>
				<span
					class="description"><?php _e( 'NOTE: If you need to be able to override the upload quota on a per site basis when editing the site, add this to your wp-config.php file:', 'psts' ) ?>
					<pre>define('PSTS_QUOTA_ALLOW_OVERRIDE', true);</pre></span><br/></p>
		</div>
		<!--		</div>-->
		<?php
	}

	function quota_select( $level, $selected ) {
		?>
		<select name="quota[<?php echo $level; ?>]" id="quota_<?php echo $level; ?>" class="chosen">
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

	function message( $output = true, $para = 'default' ) {
		global $psts, $blog_id;
		if ( ! is_main_site() && current_user_can( 'edit_pages' ) ) {
			//if override is set don't show this message
			if ( ! defined( 'PSTS_QUOTA_ALLOW_OVERRIDE' ) && get_option( 'blog_upload_space' ) ) {
				return;
			}

			$level = $psts->get_level() + 1;
			if ( $name = $psts->get_level_setting( $level, 'name' ) ) { //only show if there is a higher level
				$space = ProSites_Module_Quota::display_space( $psts->get_level_setting( $level, 'quota' ) );
				$msg   = str_replace( 'LEVEL', $name, $psts->get_setting( 'quota_message' ) );
				$msg   = str_replace( 'SPACE', $space, $msg );
				if ( 'default' == $para ) {
					$msg = '<p><strong><a href="' . $psts->checkout_url( $blog_id ) . '">' . $msg . '</a></strong></p>';
				} else {
					$msg = '<div class="' . esc_attr( $para ) . '"><a href="' . $psts->checkout_url( $blog_id ) . '">' . $msg . '</a></div>';
				}

				if ( $output ) {
					echo $msg;
				} else {
					return $msg;
				}
			}
		}
	}

	function out_message() {
		global $psts, $blog_id, $current_screen;
		//if not on one of media screen
		if ( $current_screen->base !== 'media' && $current_screen->base !== 'upload' ) {
			return;
		}
		if ( ! is_main_site() && current_user_can( 'edit_pages' ) && ! is_upload_space_available() ) {
			$level = $psts->get_level() + 1;
			if ( $name = $psts->get_level_setting( $level, 'name' ) ) { //only show if there is a higher level
				$space = ProSites_Module_Quota::display_space( $psts->get_level_setting( $level, 'quota' ) );
				$msg   = str_replace( 'LEVEL', $name, $psts->get_setting( 'quota_message' ) );
				$msg   = str_replace( 'SPACE', $space, $msg );
				echo '<div class="error"><p><a href="' . $psts->checkout_url( $blog_id ) . '">' . $msg . '</a></p></div>';
			}
		}
	}

	public static function display_space( $space ) {

		$space = apply_filters( 'psts_quota_pre_get_space', $space );

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

	/**
	 * Returns the staring pro level as pro widget is available for all sites
	 */
	public static function required_level() {
		global $psts;

		$levels = ( array ) get_site_option( 'psts_levels' );

		return ! empty( $levels ) ? key( $levels ) : false;

	}

	public static function get_level_status( $level_id ) {
		global $psts;

		$space = $psts->get_level_setting( $level_id, 'quota', get_space_allowed() );

		return array(
			'type'    => 'space',
			'display' => self::display_space( $space ),
			'value'   => $space,
		);

	}

	/**
	 * return the include text level wise
	 *
	 * @param $level
	 */
	public function include_text( $level ) {

		//Return if there is no level specified
		if ( empty( $level ) ) {
			return;
		}

		//Return Upload Quota limit for the specified level
		$levels = (array) get_site_option( 'psts_levels' );

		//Check for level data
		if ( empty( $levels [ $level ] ) ) {
			return;
		}
		$format_limit = ! empty( $levels[ $level ]['quota'] ) && $levels[ $level ]['quota'] > 1024 ? number_format_i18n( $levels[ $level ]['quota'] ) / 1024 . ' GB' : number_format_i18n( $levels[ $level ]['quota'] ) . ' MB';

		//Return upload quota
		return __( 'Upload data upto ', 'psts' ) . $format_limit;

	}

	public function add_quota_to_library() {

		$quota = get_space_allowed();
		$used  = get_space_used();

		if ( $used > $quota ) {
			$percentused = '100';
		} else {
			$percentused = ( $used / $quota ) * 100;
		}
		$used_class  = ( $percentused >= 70 ) ? 'class="warning"' : '';
		$used        = round( $used, 2 );
		$percentused = number_format( $percentused );
		$text        = sprintf(
		/* translators: 1: number of megabytes, 2: percentage */
			__( '%1$s MB (%2$s%%) of %3$s MB used.', 'psts' ),
			number_format_i18n( $used, 2 ),
			$percentused,
			number_format_i18n( $quota, 2 )
		); ?>

	<div id="prosites-media-quota-display" style="display:none;" <?php echo $used_class; ?>>
		<div class="size-text"><?php echo $text; ?> </div><?php echo $this->message( false, 'media-upload' ); ?>
		</div><?php

		global $psts;
		wp_enqueue_style( 'psts-quota-style', $psts->plugin_url . 'assets/css/quota.min.css', $psts->version );
		wp_enqueue_script( 'psts-quota', $psts->plugin_url . 'assets/js/quota.min.js', array( 'jquery' ), $psts->version );
	}

	// Static Hooks
	//changed in 2.0 to filter return as to be non-permanent.
	public static function filter( $space ) {
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

	// Turn on quotas
	public static function force_network_quota_checkbox( $value ) {
		return 0;
	}

}
