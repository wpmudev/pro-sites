<?php

/*
Plugin Name: Pro Sites (Feature: Posting Quota)
*/

class ProSites_Module_PostingQuota {

	static $user_label;
	static $user_description;
	public $is_per_level;

	// Module name for registering
	public static function get_name() {
		return __( 'Post/Page Quotas', 'psts' );
	}

	// Module description for registering
	public static function get_description() {
		return __( 'Allows you to limit the number of post types for selected Pro Site levels.', 'psts' );
	}

	function __construct() {
		global $psts;

		self::$user_label       = __( 'Posting Quotas', 'psts' );
		self::$user_description = __( 'Limited post types', 'psts' );
		$this->is_per_level = $this->is_per_level();
		$blog_id = get_current_blog_id();

		if ( $this->is_per_level ) { //add limits to all pro-sites levels
			if ( ! is_main_site( $blog_id ) ) {
				$this->actions_and_filters();
			}
		}else {
			if ( ! is_main_site( $blog_id ) && ! is_pro_site( $blog_id, $psts->get_setting( 'pq_level', 1 ) ) ) {
				$this->actions_and_filters();
			}
		}
	}
	
	function actions_and_filters(){
		/**
		 * Add warning
		 */
		add_action( 'admin_notices', array( &$this, 'message' ) );
		/**
		 * Check limit before publishing
		 */
		add_filter( 'wp_insert_post_data', array( $this, 'checkPostStatusBeforeSave' ), 10, 2 );
		/**
		 * Remove publish option if limit reached
		 *
		 */
		add_action( 'post_submitbox_misc_actions', array( $this, 'remove_publish_option' ) );

		add_filter( 'wp_handle_upload_prefilter', array( $this, 'limit_media_upload' ) );
	}
	
	/**
	* Checks if quotas per level setting is enabled
	* returns true or false
	*/
	function is_per_level(){
		global $psts;
		
		$per_level = $psts->get_setting('per_level');
		return  ( $per_level == 1 ) ? true : false;
	}
	
	/**
	* Get the quota settings
	* for all levels or one level
	*/
	function get_quota_settings($level){
		global $psts;
		$settings = get_site_option( 'psts_settings' );
		if ( isset ( $settings['levels_quotas']['level'.$level] ) && $this->is_per_level ){ //per level quotas
			$quota_settings = $settings['levels_quotas']['level'.$level]; 
		} elseif ( ! isset ( $settings['levels_quotas']['level'.$level] ) && $this->is_per_level ){//default quotas if not defined for this $level
			$quota_settings = isset ( $settings['levels_quotas']['level_default'] ) ? $settings['levels_quotas']['level_default'] : $this->get_default_quotas();
		} else {
			$quota_settings = $psts->get_setting( "pq_quotas" ); //old single level quotas
		}
		return $quota_settings;
	}
	
	/**
	* Return Default Quotas
	* Used for new levels without quotas set
	* return type: array
	*/
	function get_default_quotas(){
		$post_types = get_post_types( array( 'show_ui' => true ), 'objects', 'and' );
		$defaults = array();
		if ( is_array( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				if ( ! current_user_can( $post_type->cap->publish_posts ) ) continue;
				$defaults[$post_type->name] = array(
					'quota' => 'unlimited',
					'message' => sprintf( __( "You've reached the publishing limit, To publish more %s, please upgrade to LEVEL &raquo;", 'psts' ), $post_type->label )
				);
			}
		}
		return $defaults;
	}
	
	function settings() {
		global $psts;
				
		if ( isset( $_GET['level'] ) ){
			$selected_level = esc_attr($_GET['level']);		
		}else{
			$selected_level = $psts->get_setting( 'pq_level', 1 );
		}		
		$quota_settings = $this->get_quota_settings($selected_level);
		
		$per_level = $psts->get_setting('per_level');
		$per_level = isset($per_level)?$per_level:0;
		?>

		<div class="inside">
			<table class="form-table post-page-quota">
				<tr valign="top">
					<th scope="row" class="pro-site-level psts-quota-prosite-level"><?php echo __( 'Set Quotas Per Level', 'psts' ) . $psts->help_text( __( 'Choose if you want to ser quotas per level on your network. If enabed, each level will have different Quotas', 'psts' ) ); ?></th>
					<td>
						<input type="radio" name="psts[per_level]" class="per_level" value="1" <?php checked( $per_level, 1); ?> /><?php echo __('Yes', 'psts'); ?> <br />
						<input type="radio" name="psts[per_level]" class="per_level" value="0" <?php checked( $per_level, 0); ?> /><?php echo __('No', 'psts'); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="pro-site-level psts-quota-prosite-level">
					<?php
						$label_description = ($per_level==1)? __( 'Select a level to define its Quotas', 'psts' ): __( 'Select the minimum level required to remove quotas', 'psts' );
						echo __( 'Pro Site Level', 'psts' ) . $psts->help_text( $label_description );
					?>
					</th>
					<td>
						<select name="psts[pq_level]" id="pq_level" class="chosen">
							<option value="0" <?php selected( $selected_level, 0 ); ?>><?php _e( 'Default - Free level', 'psts' ); ?></option>
							<?php
							$levels = (array) get_site_option( 'psts_levels' );
							foreach ( $levels as $level => $value ) {
								?>
								<option value="<?php echo $level; ?>"<?php selected( $selected_level, $level ) ?>><?php echo $level . ': ' . esc_attr( $value['name'] ); ?></option><?php
							}
							?>
						</select>
						<!-- Required to store quota settings for each level -->
						<input type="hidden" name="quotas_for_level" value="level<?php echo $selected_level;?>" />
					</td>
				</tr>
				<?php
				$post_types     = get_post_types( array( 'show_ui' => true ), 'objects', 'and' );
				if ( is_array( $post_types ) ) {
					foreach ( $post_types as $post_type ) {
						//Check publish permissions for user
						if ( ! current_user_can( $post_type->cap->publish_posts ) ) {
							continue;
						}
						$quota     = isset( $quota_settings[ $post_type->name ]['quota'] ) ? $quota_settings[ $post_type->name ]['quota'] : 'unlimited';
						$quota_msg = isset( $quota_settings[ $post_type->name ]['message'] ) ? $quota_settings[ $post_type->name ]['message'] : sprintf( __( "You've reached the publishing limit, To publish more %s, please upgrade to LEVEL &raquo;", 'psts' ), $post_type->label );
						?>
						<tr valign="top">
							<th scope="row"><?php printf( __( '%s Quota', 'psts' ), $post_type->label ); ?></th>
						</tr>
						<tr>
							<td><?php printf( __( 'Publish Limit', 'psts' ), $post_type->label ); ?></td>
							<td>
								<select name="psts[pq_quotas][<?php echo $post_type->name; ?>][quota]" class="chosen">
									<option value="unlimited"<?php selected( $quota, 'unlimited' ); ?>><?php _e( 'Unlimited', 'psts' ); ?></option>
									<?php
									for ( $counter = 1; $counter <= 1000; $counter ++ ) {
										echo '<option value="' . $counter . '"' . ( $counter == $quota ? ' selected' : '' ) . '>' . number_format_i18n( $counter ) . '</option>' . "\n";
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<td class="upgrade-message"><?php echo __( 'Upgrade message', 'psts' ) . '<img width="16" height="16" src="' . $psts->plugin_url . 'images/help.png" class="help_tip"><div class="psts-help-text-wrapper period-desc"><div class="psts-help-arrow-wrapper"><div class="psts-help-arrow"></div></div><div class="psts-help-text">' . __( 'Displayed on the respective add post, page or media screen for sites that have used up their quota. "LEVEL" will be replaced with the needed level name', 'psts' ) . '</div></div>'; ?></td>
							<td>
								<input type="text" name="psts[pq_quotas][<?php echo $post_type->name; ?>][message]" value="<?php echo esc_attr( $quota_msg ); ?>" style="width: 90%"/>
							</td>
						</tr>
						<?php
					}
				}
				?>
				<tr valign="top">
                    <th scope="row"><?php echo __( 'Highest Level Notice', 'psts' ); ?></th>
                </tr>
                <tr>
                    <td class="upgrade-message">
						<?php echo __( 'Message', 'psts' ) . '<img width="16" height="16" src="' . $psts->plugin_url . 'images/help.png" class="help_tip"><div class="psts-help-text-wrapper period-desc"><div class="psts-help-arrow-wrapper"><div class="psts-help-arrow"></div></div><div class="psts-help-text">' . __( 'Displayed when you have set limits for the higest level and those limits have been reached. All site owners in the highest level who have reached their limits will see this message.', 'psts' ) . '</div></div>'; ?></td>
                        <td>
                        	<?php
								$message = isset( $quota_settings['highest_level_message'] )? $quota_settings['highest_level_message'] : __('You have reached your publishing limits, no upgrades for this levell. Contact Administrator. &raquo;','psts');
							?>
							<input type="text" name="psts[pq_quotas][highest_level_message]" value="<?php echo esc_attr( $message ); ?>" style="width: 90%"/>
                        </td>
                    </td>
                </tr>
			</table>
		</div>
		<!--		</div>-->
		<?php
	}

	/**
	 * Display a admin message
	 */
	function message() {
		global $psts, $current_screen, $post_type, $blog_id;

		//Get quota settings
		$level = $psts->get_level();		
		$quota_settings = $this->get_quota_settings($level);

		//if limit not set for post type
		if ( ! empty( $post_type ) && ! isset( $quota_settings[ $post_type ] ) ) {
			return;
		}

		//If we are not on post type new screen
		if ( ! empty( $post_type ) && ! in_array( $current_screen->id, array( $post_type, 'edit-' . $post_type ) ) ) {
			return;
		}

		//For media Screen post type variable is not set, if no quota set or it is unlimited
		if ( empty( $post_type ) && ( empty( $quota_settings['attahcment'] ) || $quota_settings['attahcment'] == 'unlimited' ) ) {
			return;
		}

		//Finally if post type was not set, and its not media screen
		if ( empty( $post_type ) && ! in_array( $current_screen->base, array( 'upload', 'media' ) ) ) {
			return;
		}

		$limit = $quota_settings[ $post_type ]['quota'];
		if ( is_numeric( $limit ) && wp_count_posts( $post_type )->publish >= $limit ) {
			
			$levels = get_site_option( 'psts_levels' );
			if ( $level < count( $levels ) ){
				$level   = $this->is_per_level ? $psts->get_level($blog_id) : $psts->get_setting( 'pq_level' );
				if ( $this->is_per_level ) $level += 1;
				$name    = $psts->get_level_setting( $level, 'name' );
				$notice  = str_replace( 'LEVEL', $psts->get_level_setting( $level, 'name' ), @$quota_settings[ $post_type ]['message'] );
				echo '<div class="error"><p><a href="' . $psts->checkout_url( $blog_id ) . '">' . $notice . '</a></p></div>';
			
			}elseif ( count ( $levels ) == $level ){ //highest level gets special message if has limits
				
				$notice = isset( $quota_settings['highest_level_message'] )? $quota_settings['highest_level_message'] : "You have reached your publishing limits, no upgrades for this levell. Contact Administrator. &raquo;";
				echo '<div class="error"><p>' . $notice . '</p></div>';
			}
		}
	}

	public static function is_included( $level_id ) {
		switch ( $level_id ) {
			default:
				return false;
		}
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

		$level = $psts->get_level();		
		$quota_settings = $this->get_quota_settings($level);

		//Return if post does not exists, if there is no limit set for post type or if quota value is unlimited
		if ( ! ( $post = get_post( $postarr['ID'] ) ) || ! isset ( $quota_settings[ $post->post_type ] ) || 'unlimited' == $quota_settings[ $post->post_type ]['quota'] ) {
			return $data;
		}
		$limit = $quota_settings[ $post->post_type ]['quota'];
		if ( 0 >= $limit - wp_count_posts( $post->post_type )->publish ) {
			$data['post_status'] = $post->post_status != 'auto-draft' ? 'draft' : $post->post_status;
		}

		return $data;
	}

	/**
	 * Renders limits in the "Publish" meta box.
	 *
	 * @access public
	 * @global ProSites $psts The instance of ProSites plugin.
	 * @global string $typenow The current post type.
	 */
	public function remove_publish_option() {
		global $psts, $typenow;

		$level = $psts->get_level();
		$quota_settings = $this->get_quota_settings($level);

		/**
		 * Return if no limit is set for the post type or if its unlimited
		 */
		if ( ! isset( $quota_settings[ $typenow ] ) || 'unlimited' == $quota_settings[ $typenow ]['quota'] ) {
			return;
		}

		$exceeded = false;
		$limit    = $quota_settings[ $typenow ]['quota'];

		if ( is_numeric( $limit ) && wp_count_posts( $typenow )->publish >= $limit ) {
			$exceeded = true;
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
			<a id="psts-upgrade" class="button button-primary button-large" href="<?php echo $psts->checkout_url( get_current_blog_id() ); ?>"><?php _e( 'Upgrade Your Account', 'psts' ); ?></a>
			</div><?php
		}
	}

	/**
	 * Returns the minimum required level to remove restrictions
	 */
	public static function required_level() {
		global $psts;

		return $psts->get_setting( 'pq_level' );

	}

	public static function get_level_status( $level_id ) {
		global $psts;

		$min_level = $psts->get_setting( 'pq_level', 1 );

		if ( $level_id >= $min_level ) {
			return 'tick';
		} else {
			return 'cross';
		}

	}

	/**
	 * Return the include text level wise
	 *
	 * @param $level
	 */
	public function include_text( $level ) {

		//Return if there is no level specified
		if ( empty( $level ) ) {
			return;
		}
		global $psts;

		$limits = $text = '';

		//Return Upload posting limit for the specified level
		if ( $this->is_per_level ){
			$quota_settings = $this->get_quota_settings($level);
		}else{
			$required_level = $psts->get_setting( 'pq_level', 1 );
			$quota_settings = (array) $psts->get_setting( "pq_quotas" );
		}

		$text = "<ul>" . __( "Publish Limits: ", 'psts' );
		//If specified level value is same or less than required level, show the limits
		if ( $this->is_per_level ){ 
			foreach ( $quota_settings as $post_type => $limits ) {
				$text .= "<li>" . ucfirst( $post_type ) . ": " . $limits['quota'] . "</li>";
			}
		}else{
			if ( $level <= $required_level ) {
				foreach ( $quota_settings as $post_type => $limits ) {
					$text .= "<li>" . ucfirst( $post_type ) . ": " . $limits['quota'] . "</li>";
				}
			}
		}
		$text .= "</ul>";

		//Return Publish quota
		return $text;

	}

	/**
	 * Return file upload error, if media limit exceeded
	 *
	 * @param $file
	 *
	 * @return mixed
	 */
	function limit_media_upload( $file ) {
		global $psts;
		$level = $psts->get_level();
		$quota_settings = $this->get_quota_settings($level);

		if ( ! $this->media_upload_exceeded() ) {
			return $file;
		} else {
			$levels = get_site_option( 'psts_levels' );
			if ( count( $levels ) > $level ) {
				$level   = $this->is_per_level ? $psts->get_level($blog_id) : $psts->get_setting( 'pq_level' );
				$level += 1;
				$name    = $psts->get_level_setting( $level, 'name' );				
				$message = ! empty( $quota_settings['attachment']['message'] ) ? $quota_settings['attachment']['message'] : __( "You've reached the publishing limit, To publish more Media, please upgrade to LEVEL »", 'psts' );
				if ( ! empty( $name ) ) {
					$message = str_replace( 'LEVEL', $name, $message );
				}
			}elseif ( count ( $levels ) == $level ){
				$message = isset( $quota_settings['highest_level_message'] )? $quota_settings['highest_level_message'] : "You've reached your Media publishing limits. Contact Administrator if you want to publish more Media. &raquo;";
			}
			$file['error'] = $message;
		}

		return $file;
	}

	/**
	 * Get the limit for media uploads
	 * @return bool Returns false, if there is no limit or unlimited, otherwise integer
	 */
	function limit() {
		global $psts;

		//Get quota settings
		$level = $psts->get_level( $blog_id );		
		$quota_settings = $this->get_quota_settings($level);

		//No limit set for media
		if ( empty( $quota_settings['attachment'] ) ) {
			return false;
		}
		$limit = $quota_settings['attachment']['quota'];

		//Unlimited media upload
		if ( $limit == 'unlimited' ) {
			return false;
		}

		return $limit;
	}

	/**
	 * Check if Media Limit is applicable
	 * @return bool Returns false if limit not excedded or no limit, else true
	 */
	function media_upload_exceeded() {

		$limit = $this->limit();

		if ( ! $limit ) {
			return false;
		}

		$attachments_count = wp_count_posts( 'attachment' );
		$attachments_count = ! empty( $attachments_count ) ? $attachments_count->inherit : '';

		//If attachments count exceeded limit
		if ( $limit <= $attachments_count ) {
			return true;
		}
	}
}
