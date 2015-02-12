<?php

/**
 * Post Throttling module class.
 *
 * @category ProSites
 * @package Module
 *
 * @since 3.5
 */
class ProSites_Module_PostThrottling {

	const NAME = __CLASS__;

	const OPTION_THROTTLES = 'psts-throttle-info';
	const OPTION_THROTTLE_TYPES = 'throttling_types';
	const OPTION_FREE_PLAN_LIMITS = 'throttling_free';

	const PERIOD_DAILY = 'throttling_day';
	const PERIOD_HOURLY = 'throttling_hour';

	static $user_label;
	static $user_description;

	// Module name for registering
	public static function get_name() {
		return __( 'Post Throttling', 'psts' );
	}

	// Module description for registering
	public static function get_description() {
		return __( 'Allows you to limit the number of posts to be published daily/hourly per site.', 'psts' );
	}

	/**
	 * Constructor.
	 *
	 * @access public
	 */
	public function __construct() {
		// actions
//		add_action( 'psts_settings_page', array( $this, 'renderModuleSettings' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'renderLimitsInformation' ) );
		add_action( 'transition_post_status', array( $this, 'checkTransitionPostStatus' ), 10, 3 );

		// filters
		add_filter( 'psts_settings_filter', array( $this, 'saveModuleSettings' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( $this, 'checkPostStatusBeforeSave' ), 10, 2 );

		//Admin Notice If limit exceeded
		add_action( 'admin_notices', array( &$this, 'message' ) );
		self::$user_label       = __( 'Post Throttling', 'psts' );
		self::$user_description = __( 'Limit Post publishing rate.', 'psts' );
	}

	/**
	 * Returns the array of throttles.
	 *
	 * @static
	 * @access private
	 * @return array The array of throttles.
	 */
	private static function _getThrottles() {
		$throttles = get_option( self::OPTION_THROTTLES );

		// validate throttles expirecy
		$time        = time();
		$need_update = false;
		foreach ( self::_getThrottlingPeriods() as $key => $info ) {
			if ( ! isset( $throttles[ $key ] ) || $throttles[ $key ]['expired'] <= $time ) {
				$need_update       = true;
				$throttles[ $key ] = array(
					'expired' => $time + $info['frame'],
					'ids'     => array(),
				);
			}
		}

		// update throttles
		if ( $need_update ) {
			update_option( self::OPTION_THROTTLES, $throttles );
		}

		return $throttles;
	}

	/**
	 * Returns array of limit periods.
	 *
	 * @static
	 * @access private
	 * @return array The array of limit periods.
	 */
	private static function _getThrottlingPeriods() {
		return array(
			self::PERIOD_HOURLY => array(
				'label' => __( 'Hour Limits', 'psts' ),
				'frame' => HOUR_IN_SECONDS,
			),
			self::PERIOD_DAILY  => array(
				'label' => __( 'Day Limits', 'psts' ),
				'frame' => DAY_IN_SECONDS,
			),
		);
	}

	/**
	 * Returns throttling types array.
	 *
	 * @static
	 * @access private
	 * @global ProSites $psts The instance of ProSites plugin.
	 * @return array The array of throttling types.
	 */
	private static function _getThrottlingTypes() {
		global $psts;

		return $psts->get_setting( self::OPTION_THROTTLE_TYPES, array( 'post' ) );
	}

	/**
	 * Checks if a post could be published.
	 *
	 * @access public
	 * @global ProSites $psts The instance of ProSites plugin.
	 *
	 * @param array $data The array of post data to save.
	 * @param array $postarr The income array of post data.
	 */
	public function checkPostStatusBeforeSave( $data, $postarr ) {
		global $psts;

		if ( ! isset( $postarr['ID'] ) || $data['post_status'] != 'publish' ) {
			return $data;
		}

		if ( ! ( $post = get_post( $postarr['ID'] ) ) || ! in_array( $post->post_type, self::_getThrottlingTypes() ) ) {
			return $data;
		}

		$level     = $psts->get_level();
		$throttles = self::_getThrottles();
		$frees     = $psts->get_setting( self::OPTION_FREE_PLAN_LIMITS );
		foreach ( array_keys( self::_getThrottlingPeriods() ) as $key ) {
			$limit = false;
			if ( $level > 0 ) {
				$limit = (int) $psts->get_level_setting( $level, $key );
			} else {
				$limit = isset( $frees[ $key ] ) ? $frees[ $key ] : 0;
			}

			if ( empty ( $limit ) || 'unlimited' == $limit ) {
				continue;
			}

			if ( 0 >= $limit - count( $throttles[ $key ]['ids'] ) ) {
				$data['post_status'] = $post->post_status != 'auto-draft' ? $post->post_status : 'draft';
				break;
			}
		}

		return $data;
	}

	/**
	 * Checks new status and if it published, then save in appropriate throttles.
	 *
	 * @access public
	 * @global ProSites $psts The instance of ProSites plugin.
	 *
	 * @param string $new_status The new post status.
	 * @param string $old_status The old post status.
	 * @param WP_Post $post The post object.
	 */
	public function checkTransitionPostStatus( $new_status, $old_status, $post ) {
		if ( ! in_array( $post->post_type, self::_getThrottlingTypes() ) || $new_status != 'publish' || $old_status == 'publish' ) {
			return;
		}

		// add post id to each throttle
		$need_update = false;
		$throttles   = self::_getThrottles();
		foreach ( array_keys( self::_getThrottlingPeriods() ) as $key ) {
			if ( ! in_array( $post->ID, $throttles[ $key ]['ids'] ) ) {
				$need_update                = true;
				$throttles[ $key ]['ids'][] = $post->ID;
			}
		}

		// update throttles if need be
		if ( $need_update ) {
			update_option( self::OPTION_THROTTLES, $throttles );
		}
	}

	/**
	 * Renders limits in the "Publish" meta box.
	 *
	 * @access public
	 * @global ProSites $psts The instance of ProSites plugin.
	 * @global string $typenow The current post type.
	 */
	public function renderLimitsInformation() {
		global $psts, $typenow;
		if ( ! in_array( $typenow, self::_getThrottlingTypes() ) ) {
			return;
		}

		$exceeded  = false;
		$level     = $psts->get_level();
		$throttles = self::_getThrottles();
		$frees     = $psts->get_setting( self::OPTION_FREE_PLAN_LIMITS );
		foreach ( self::_getThrottlingPeriods() as $key => $info ) {
			$limit = false;
			if ( $level > 0 ) {
				$limit = (int) $psts->get_level_setting( $level, $key );
			} else {
				$limit = isset( $frees[ $key ] ) ? $frees[ $key ] : 0;
			}
			if ( empty( $limit ) || 'unlimited' == $limit ) {
				continue;
			}

			$remaining = $limit - count( $throttles[ $key ]['ids'] );
			if ( $remaining <= 0 ) {
				$remaining = 0;
				$exceeded  = true;
			} ?>
			<div class="misc-pub-section">
			<code style="float: right"><?php echo human_time_diff( time(), $throttles[ $key ]['expired'] ); ?></code>
			<?php echo $info['label']; ?> : <strong style="color: <?php echo $remaining == 0 ? 'red' : 'green'; ?>">
				<code><?php echo $remaining; ?> / <?php echo $limit; ?></code>
			</strong>
			</div><?php
		}

		if ( $exceeded && get_post()->post_status != 'publish' ) {
			?>
			<style type="text/css">
				#publish {
					display: none;
				}

				#psts-upgrade {
					margin-top: 4px;
					display: block;
					text-align: center;
				}
			</style>

			<div class="misc-pub-section">
			<a id="psts-upgrade" class="button button-primary button-large"
			   href="<?php echo $psts->checkout_url( get_current_blog_id() ); ?>"><?php _e( 'Upgrade Your Account', 'psts' ); ?></a>
			</div><?php
		}
	}

	/**
	 * Filters module settings.
	 *
	 * @access public
	 * @global ProSites $psts The instance of ProSites plugin.
	 *
	 * @param array $settings The array of plugin settings.
	 */
	public function saveModuleSettings( $settings, $active_tab ) {

		if( 'throttling' != $active_tab ) {
			return $settings;
		}

		global $psts;

		// level limits
		$levels  = array_keys( (array) get_site_option( 'psts_levels' ) );
		$periods = array_keys( self::_getThrottlingPeriods() );

		$free             = array();
		$validate_options = array( 'options' => array( 'min_range' => 0, 'default' => 0 ) );
		foreach ( $periods as $key ) {
			$limits = isset( $_POST[ $key ] ) ? (array) $_POST[ $key ] : array();
			foreach ( $levels as $level ) {
				$value = isset( $limits[ $level ] )
					? filter_var( $limits[ $level ], FILTER_VALIDATE_INT, $validate_options )
					: 0;

				$psts->update_level_setting( $level, $key, $value );
			}

			$free[ $key ] = isset( $limits[0] )
				? filter_var( $limits[0], FILTER_VALIDATE_INT, $validate_options )
				: 0;
		}

		$settings[ self::OPTION_FREE_PLAN_LIMITS ] = $free;

		// throttling types
		$types          = isset( $_POST['throttling_types'] ) ? (array) $_POST['throttling_types'] : array();
		$all_types      = get_post_types( array( 'public' => true ) );
		$accepted_types = array();
		foreach ( $types as $type ) {
			if ( in_array( $type, $all_types ) && $type != 'attachment' ) {
				$accepted_types[] = $type;
			}
		}

		$settings[ self::OPTION_THROTTLE_TYPES ] = $accepted_types;

		return $settings;
	}

	/**
	 * Renders module settings section.
	 *
	 * @access public
	 */
	public function renderModuleSettings() {
		?>
		<!--		<div class="postbox">-->
		<!--		<h3 class="hndle" style="cursor:auto;">-->
		<!--			<span>--><?php //_e( 'Post Throttling', 'psts' ); ?><!-- </span> --->
		<!--			<span class="description">--><?php //_e( 'Allows you to limit the number of posts/pages to be published daily/hourly per site.', 'psts' ); ?><!-- </span>-->
		<!--		</h3>-->

		<div class="inside">
			<p><?php echo __( 'Post Throttling module allows you to limit the number of posts published daily or hourly per site, to avoid flooding of posts by a single site.', 'psts' ), ' '; ?></p>
			<table class="form-table"> <?php
				$this->_renderThrottlingTypesSetting();
				$this->_renderLevelLimitsSettings(); ?>
			</table>
		</div>
		<!--		</div>-->
	<?php
	}

	/**
	 * Renders throttling types setting.
	 *
	 * @access private
	 */
	private function _renderThrottlingTypesSetting() {
		global $current_blog;
		$types = self::_getThrottlingTypes(); ?>
		<tr valign="top">
		<th scope="row"><?php echo __( 'Limit', 'psts' ); ?></th>
		<td><?php
			foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type => $object ) {
				if ( $type != 'attachment' ) {
					?>
					<div>
					<label>
						<input type="checkbox" name="throttling_types[]"
						       value="<?php echo esc_attr( $type ); ?>" <?php echo checked( in_array( $type, $types ) ); ?>>
						<?php echo esc_html( $object->label ); ?>
					</label>
					</div><?php
				}
			} ?>
		</td>
		</tr><?php
	}

	/**
	 * Renders level limits settings.
	 *
	 * @access private
	 * @global ProSites $psts The instance of ProSites plugin.
	 */
	private function _renderLevelLimitsSettings() {
		global $psts;

		$free   = $psts->get_setting( 'free_name' );
		$limits = $psts->get_setting( self::OPTION_FREE_PLAN_LIMITS, array() );

		$levels = (array) get_site_option( 'psts_levels' );

		foreach ( self::_getThrottlingPeriods() as $key => $info ) {
			?>
			<tr valign="top">
				<th scope="row"><?php echo $info['label']; ?></th>
			</tr>
			<tr>
				<td><strong><?php _e( 'Level', 'psts' ); ?></strong></td>
				<td><strong><?php _e( 'Limit', 'psts' ); ?></strong></td>
			</tr>
			<tr>
			<td>0 - <?php echo $free; ?></td>
			<td>
				<!--				Free Plan-->
				<div class="plan-th-limits">
					<select name="<?php echo $key . '[0]'; ?>" class="chosen">
						<?php $free_value = isset( $limits[ $key ] ) ? intval( $limits[ $key ] ) : 0; ?>
						<option
							value="unlimited"<?php selected( $free_value, 'unlimited' ); ?>><?php _e( 'Unlimited', 'psts' ); ?></option>
						<?php
						for ( $counter = 1; $counter <= 1000; $counter ++ ) {
							echo '<option value="' . $counter . '"' . ( $counter == $free_value ? ' selected' : '' ) . '>' . number_format_i18n( $counter ) . '</option>' . "\n";
						}
						?>
					</select>
				</div>
			</td>
			</tr><?php

			foreach ( $levels as $level => $data ) {
				$value = isset( $data[ $key ] ) ? intval( $data[ $key ] ) : 0; ?>
				<tr>
				<td><?php echo $level; ?> - <?php echo $data['name']; ?></td>
				<td>
					<div class="plan-th-limits">
						<select name="<?php echo $key . '[' . $level . ']'; ?>" class="chosen">
							<option
								value="unlimited"<?php selected( $value, 'unlimited' ); ?>><?php _e( 'Unlimited', 'psts' ); ?></option>
							<?php
							for ( $counter = 1; $counter <= 1000; $counter ++ ) {
								echo '<option value="' . $counter . '"' . ( $counter == $value ? ' selected' : '' ) . '>' . number_format_i18n( $counter ) . '</option>' . "\n";
							}
							?>
						</select>
					</div>
				</td>
				</tr><?php
			} ?><?php
		}
	}

	/**
	 * Displays a admin notice if Post throttling limit has been exceeded for the site
	 */
	function message() {
		global $psts, $current_screen, $post_type, $blog_id;
		/** Checks If current post type is being limited or not */
		if ( ! in_array( $post_type, self::_getThrottlingTypes() ) ) {
			return;
		}

		if ( in_array( $current_screen->id, array( $post_type, 'edit-' . $post_type ) ) ) {
			$exceeded  = false;
			$level     = $psts->get_level();
			$throttles = self::_getThrottles();
			$frees     = $psts->get_setting( self::OPTION_FREE_PLAN_LIMITS );
			foreach ( self::_getThrottlingPeriods() as $key => $info ) {
				$limit = false;
				if ( $level > 0 ) {
					$limit = (int) $psts->get_level_setting( $level, $key );
				} else {
					$limit = isset( $frees[ $key ] ) ? $frees[ $key ] : 0;
				}
				if ( empty( $limit ) || 'unlimited' == $limit ) {
					continue;
				}

				$remaining = $limit - count( $throttles[ $key ]['ids'] );
				if ( $remaining <= 0 ) {
					$remaining      = 0;
					$exceeded       = true;
					$period         = $key;
					$post_back_time = human_time_diff( time(), $throttles[ $key ]['expired'] );
				}
			}
			if ( $exceeded ) {
				$period_human_form = 'throttling_hour' == $key ? __( 'hourly', 'psts' ) : __( 'daily', 'psts' );
				$upgrade_message   = is_super_admin( $blog_id ) ? 'you can <a href="' . $psts->checkout_url( $blog_id ) . '">' . __( 'upgrade', 'psts' ) . '</a> to continue posting. Contact site admin for more details' : __( 'Contact site admin for more details' );
				$notice            = sprintf( __( 'You have reached the %s publishing limit, you can publish again after %s, or %s.' ), $period_human_form, $post_back_time, $upgrade_message );
				/**
				 * Filter the Post Throttling limit message, display on admin screen if site posting limit is exceeded
				 *
				 * @since 3.5
				 *
				 * @param string $notice The message to be displayed
				 * @param string $period_human_form type of limit exceeded
				 */
				$notice = apply_filters( 'psts_throttling_limit_exceeded', $notice, $period_human_form );
				echo '<div class="error"><p>' . $notice . '</p></div>';
			}
		}

	}

	public static function get_level_status( $level_id ) {
		global $psts;

		$setting_daily = $psts->get_level_setting( $level_id, ProSites_Module_PostThrottling::PERIOD_DAILY );
		$setting_hourly = $psts->get_level_setting( $level_id, ProSites_Module_PostThrottling::PERIOD_HOURLY );

		if( 0 == $setting_daily && 0 == $setting_hourly ) {
			return 'tick';
		} else {
			return 'cross';
		}

	}

}