<?php
/*
Pro Sites (Module: Upload Quota)
*/
class ProSites_Module_Quota {

	function ProSites_Module_Quota() {
		$this->__construct();
	}

	function __construct() {
		add_action( 'psts_settings_page', array(&$this, 'settings') );
		add_action( 'psts_settings_process', array(&$this, 'settings_process') );
		
		//filter blog and site options
		if ( !defined('PSTS_QUOTA_ALLOW_OVERRIDE') )
			add_filter( 'pre_option_blog_upload_space', array(&$this, 'filter') );
		add_filter( 'pre_site_option_blog_upload_space', array(&$this, 'filter') );
		
		//add messages
		add_action( 'activity_box_end', array(&$this, 'message') , 11);
		add_action( 'pre-upload-ui', array(&$this, 'message') , 11);
		add_action( 'admin_notices', array(&$this, 'out_message') );
	}

	//changed in 2.0 to filter return as to be non-permanent.
	function filter($space) {
		global $psts;
    
		//don't filter on network settings page to avoid confusion
		if ( is_network_admin() )
			return $space;

		$quota = $psts->get_level_setting($psts->get_level(), 'quota');
		if ( $quota && is_pro_site(false, $psts->get_level()) ) {
			return $quota;
		} else if ( function_exists('psts_hide_ads') && psts_hide_ads() && $quota = $psts->get_setting( "quota_upgraded_space" ) ) {
			return $quota;
		} else {
			return $space;
		}
	}

	function settings_process() {
		global $psts;
	  
		foreach ($_POST['quota'] as $level => $quota) {
			if ($level == 0) {
				$psts->update_setting("quota_upgraded_space", $quota);
			} else {
				$psts->update_level_setting($level, 'quota', $quota);
			}
		}
	}

	function settings() {
	  global $psts;
	  $levels = (array)get_site_option( 'psts_levels' );
		?>
		<div class="postbox">
		  <h3 class='hndle'><span><?php _e('Upload Quota', 'psts') ?></span> - <span class="description"><?php _e('Allows you to give additional upload space to Pro Sites.', 'psts') ?></span></h3>
		  <div class="inside">
		  <table class="form-table">
			  <tr valign="top">
			  <th scope="row"><?php _e('Quota Amounts', 'psts') ?></th>
			  <td><?php
				if ( function_exists('psts_hide_ads') ) {
					$level = 0;
					echo '<label>';
						$quota = $psts->get_setting( "quota_upgraded_space" );
						$quota = $quota ? $quota : get_site_option('blog_upload_space');
						$this->quota_select($level, $quota);
					echo ' ' . $level . ' - ' . __('Ads Removed (Upgraded)', 'psts') . '</label><br />';
				}
				foreach ($levels as $level => $data) {
					echo '<label>';
					$quota = isset($data['quota']) ? $data['quota'] : get_site_option('blog_upload_space');
					$this->quota_select($level, $quota);
				  echo ' ' . $level . ' - ' . $data['name'] . '</label><br />';
				}
				_e('Each level should have an identical or progressively higher quota.', 'psts');
				?>
				</td>
			  </tr>
			  <tr valign="top">
			  <th scope="row"><?php _e('Quota Message', 'psts') ?></th>
			  <td><input type="text" name="psts[quota_message]" id="quota_message" value="<?php echo esc_attr($psts->get_setting( "quota_message" )); ?>" style="width: 95%" />
			  <br /><?php _e('Required - This message is displayed on the dashboard and media upload form as an advertisment to upgrade to the next level. "LEVEL" will be replaced with the needed level name, and "SPACE" will be replaced with the extra upload space in the next level.', 'psts') ?></td>
			  </tr>
			  <tr valign="top">
			  <th scope="row"><?php _e('Out of Space Message', 'psts') ?></th>
			  <td><input type="text" name="psts[quota_out_message]" id="quota_out_message" value="<?php echo esc_attr($psts->get_setting( "quota_out_message" )); ?>" style="width: 95%" />
			  <br /><?php _e('Required - This message is displayed on the dashboard when out of upload space. "LEVEL" will be replaced with the needed level name, and "SPACE" will be replaced with the extra upload space in the next level.', 'psts') ?></td>
			  </tr>
		  </table>
		  </div>
		</div>
	  <?php
	}

	function quota_select($level, $selected) {
		?>
		<select name="quota[<?php echo $level; ?>]" id="quota_<?php echo $level; ?>">
			<?php
		    for ( $counter = 1; $counter < 10; $counter += 1) {
		      echo '<option value="' . $counter . '"' . ($counter == $selected ? ' selected' : '') . '>' . number_format_i18n($counter) . ' MB</option>' . "\n";
				}
				for ( $counter = 10; $counter < 100; $counter += 5) {
		      echo '<option value="' . $counter . '"' . ($counter == $selected ? ' selected' : '') . '>' . number_format_i18n($counter) . ' MB</option>' . "\n";
				}
				for ( $counter = 100; $counter < 1000; $counter += 50) {
		      echo '<option value="' . $counter . '"' . ($counter == $selected ? ' selected' : '') . '>' . number_format_i18n($counter) . ' MB</option>' . "\n";
				}
				for ( $counter = 1; $counter <= 100; $counter += 1) {
		      echo '<option value="' . ($counter * 1024) . '"' . (($counter * 1024) == $selected ? ' selected' : '') . '>' . number_format_i18n($counter) . ' GB</option>' . "\n";
				}
		  ?>
		</select>
		<?php
	}

	function message() {
	  global $psts, $blog_id;
	  if( current_user_can('edit_pages') ) {
			$level = $psts->get_level() + 1;
			if ($name = $psts->get_level_setting($level, 'name')) { //only show if there is a higher level
        $space = $this->display_space($psts->get_level_setting($level, 'quota'));
				$msg = str_replace( 'LEVEL', $name, $psts->get_setting('quota_message') );
	      $msg = str_replace( 'SPACE', $space, $msg );
		    echo '<p><strong><a href="'.$psts->checkout_url($blog_id).'">'.$msg.'</a></strong></p>';
			}
	  }
	}

	function out_message() {
	  global $psts;
	  if( current_user_can('edit_pages') && !is_upload_space_available() ) {
			$level = $psts->get_level() + 1;
			if ($name = $psts->get_level_setting($level, 'name')) { //only show if there is a higher level
      	$space = $this->display_space($psts->get_level_setting($level, 'quota'));
				$msg = str_replace( 'LEVEL', $name, $psts->get_setting('quota_message') );
	      $msg = str_replace( 'SPACE', $space, $msg );
		    echo '<div class="error"><p><a href="'.$psts->checkout_url($blog_id).'">'.$msg.'</a></p></div>';
			}
	  }
	}
	
	function display_space($space) {
	  if (!$space)
	    return '0' . __( 'MB', 'psts' );
	  
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
}

//register the module
psts_register_module( 'ProSites_Module_Quota', __('Upload Quota', 'psts'), __('Allows you to give additional upload space to Pro Sites.', 'psts') );
?>