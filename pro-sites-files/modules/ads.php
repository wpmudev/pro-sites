<?php
/*
Plugin Name: Pro Sites (Feature: Ads)
*/
class ProSites_Module_Ads {

	function ProSites_Module_Ads() {
		$this->__construct();
	}

  function __construct() {
    global $psts;
    
		add_action( 'psts_settings_page', array(&$this, 'settings') );
		add_filter( 'psts_settings_filter', array(&$this, 'settings_process') );
		add_action( 'admin_menu', array(&$this, 'plug_page'), 100 );
		add_action( 'psts_extend', array(&$this, 'extend'), 10, 2 );
		add_action( 'psts_withdraw', array(&$this, 'withdraw'), 10, 2 );

    //update install script if necessary
		if ($psts->get_setting('ads_version') != $psts->version) {
			$this->install();
		}
	}

	function install() {
		global $wpdb, $psts;

		$table1 = "CREATE TABLE `{$wpdb->base_prefix}supporter_ads` (
		  `supporter_ads_ID` bigint(20) unsigned NOT NULL auto_increment,
		  `supporter_blog_ID` bigint(20) NOT NULL default '0',
		  `blog_ID` bigint(20) NOT NULL default '0',
		  `expire` bigint(20) NOT NULL default '0',
		  PRIMARY KEY  (`supporter_ads_ID`)
		);";

   	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		dbDelta($table1);

  	$psts->update_setting('ads_version', $psts->version);
	}
	
	function plug_page() {
	  global $psts;
	  //add it under the pro blogs menu
	  if ( !is_main_site() && $psts->get_setting('ads_enable_blogs') ) {
			add_submenu_page('psts-checkout', $psts->get_setting('ads_name'), $psts->get_setting('ads_name'), 'manage_options', 'psts-ads', array(&$this, 'ads_page') );
		}
	}

	function extend($blog_id, $new_expire) {
		global $wpdb;
		$max = $this->max_ad_free($blog_id); //only extend the number of blogs for their level
		$wpdb->query("UPDATE {$wpdb->base_prefix}supporter_ads SET expire = '$new_expire' WHERE supporter_blog_ID = '$blog_id' LIMIT $max");
	}

	function withdraw($blog_id, $new_expire) {
		global $wpdb;
		$wpdb->query("UPDATE {$wpdb->base_prefix}supporter_ads SET expire = '$new_expire' WHERE supporter_blog_ID = '$blog_id'");
	}

	function check($blog_id = null) {
		global $wpdb;

		if ( empty( $blog_id ) ) {
			$blog_id = $wpdb->blogid;
		}

		$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}supporter_ads WHERE blog_ID = '$blog_id' AND expire > '" . time() . "'");
		if ( $count ) {
			return true;
		} else {
			return false;
		}
	}

  function show_ads($blog_id = null) {
		global $wpdb, $psts;

		if ( empty( $blog_id ) ) {
			$blog_id = $wpdb->blogid;
		}
		
		if ( is_main_site($blog_id) ) {
			return false;
		} else {
			if ( is_pro_blog($blog_id, $psts->get_setting('ads_level')) || $this->check($blog_id) ) {
				return false;
			} else {
				return true;
			}
		}
	}

	function hide_ads($blog_id = null) {
  	global $wpdb, $psts;

		if ( empty( $blog_id ) ) {
			$blog_id = $wpdb->blogid;
		}

		if ( is_main_site($blog_id) ) {
			return true;
		} else {
			if ( is_pro_blog($blog_id, $psts->get_setting('ads_level')) || $this->check($blog_id) ) {
				return true;
			} else {
				return false;
			}
		}
	}

	function max_ad_free($blog_id = null) {
  	global $wpdb, $psts;

		if ( empty( $blog_id ) ) {
			$blog_id = $wpdb->blogid;
		}
		
		$ads = is_pro_blog($blog_id) ? $psts->get_level_setting($psts->get_level($blog_id), 'ads') : 0;
		return intval($ads);
	}

  function message() {
	  global $psts, $blog_id;

		$level = $psts->get_level() + 1;
		if ($name = $psts->get_level_setting($level, 'name')) { //only show if there is a higher level
      $ads = $psts->get_level_setting($level, 'ads');
			$msg = str_replace( 'LEVEL', $name, $psts->get_setting('ads_message') );
      $msg = str_replace( 'NUM', $ads, $msg );
	    echo '<div style="background-color: #FFFFE0;border-color: #E6DB55;border-radius: 3px;border-style: solid;border-width: 1px;margin: 10px 0;padding: 0 1em;"><p><strong><a href="'.$psts->checkout_url($blog_id).'">'.$msg.'</a></strong></p></div>';
		}
	}

	function settings_process($settings) {
	  global $psts;

	  foreach ($_POST['ads_levels'] as $level => $num)
	  	$psts->update_level_setting($level, 'ads', $num);
	  	
	  $settings['ads_enable_blogs'] = isset($settings['ads_enable_blogs']) ? 1 : 0;
	  $settings['ads_themes'] = isset($settings['ads_themes']) ? 1 : 0;
	  return $settings;
	}

	function settings() {
	  global $psts;
	  $levels = (array)get_site_option( 'psts_levels' );
		?>
		<div class="postbox">
	    <h3 class='hndle'><span><?php _e('Ads', 'psts') ?></span> - <span class="description"><?php _e('Allows you to disable ads for a Pro Site level, or give a Pro Site level the ability to disable ads on a number of other blogs.', 'psts') ?></span></h3>
	    <div class="inside">
				<table class="form-table">
          <tr valign="top">
				  <th scope="row"><?php _e('Rename Feature', 'psts') ?></th>
				  <td>
				  <input type="text" name="psts[ads_name]" id="ads_name" value="<?php echo esc_attr($psts->get_setting('ads_name')); ?>" size="30" />
				  <br /><?php _e('Required - No HTML! - Make this short and sweet.', 'psts') ?></td>
				  </tr>
					<tr valign="top">
				  <th scope="row"><?php _e('Add Free Level', 'psts') ?></th>
				  <td>
				  <select name="psts[ads_level]">
						<?php
						foreach ($levels as $level => $value) {
						?><option value="<?php echo $level; ?>"<?php selected($psts->get_setting('ads_level', 1), $level) ?>><?php echo $level . ': ' . esc_attr($value['name']); ?></option><?php
						}
						?>
	        </select><br />
	        <?php _e('Select the minimum level required to not show ads on the blog.', 'psts') ?>
					</td>
				  </tr>
				  <tr valign="top">
				  <th scope="row"><?php _e('Enable Additional Ad-Free Sites', 'psts'); ?></th>
				  <td><label><input type="checkbox" name="psts[ads_enable_blogs]" value="1"<?php checked($psts->get_setting('ads_enable_blogs')); ?> /> <?php _e('Allow disabling of ads on other blogs', 'psts'); ?></label></td>
				  </tr>
					<tr valign="top">
				  <th scope="row"><?php _e('Additional Ad-Free Sites', 'psts') ?></th>
				  <td><?php
					foreach ($levels as $level => $data) {
						echo '<label>';
						$this->ads_select($level, @$data['ads']);
					  echo ' ' . $level . ' - ' . $data['name'] . '</label><br />';
					}
					_e('Number of blogs that can have ads disabled in addition to the Pro Site. Each level should have an identical or progressively higher number.', 'psts');
					?>
					</td>
				  </tr>
				  <tr valign="top">
				  <th scope="row"><?php _e('Ads Message', 'psts') ?></th>
				  <td><input type="text" name="psts[ads_message]" id="ads_message" value="<?php echo esc_attr($psts->get_setting( "ads_message" )); ?>" style="width: 95%" />
				  <br /><?php _e('Required - This message is displayed on the Disable Ads page as an advertisment to upgrade to the next level. "LEVEL" will be replaced with the needed level name, and "NUM" will be replaced with the number of blogs that can be disabled in the next level.', 'psts') ?></td>
				  </tr>
					<?php if ( class_exists('ProSites_Module_PremiumThemes') ) { ?>
					<tr valign="top">
					<th scope="row"><?php _e('Enable Premium Themes', 'psts'); ?></th>
					<td><label><input type="checkbox" name="psts[ads_themes]" value="1"<?php checked($psts->get_setting('ads_themes')); ?> /> <?php _e('Make disabling ads also enable the premium themes', 'psts') ?></label>
					<br /><?php _e('This overrides any level limits you add to premium themes. You will want to tell users of this in the Ads Message above.', 'psts') ?>
					</td>
					</tr>
					<?php } ?>
				</table>
		  </div>
		</div>
    <?php
	}

  function ads_select($level, $selected) {
		?>
		<select name="ads_levels[<?php echo $level; ?>]" id="ads_level_<?php echo $level; ?>">
			<?php
			for ( $counter = 0; $counter <= 100; $counter++ ) {
	      echo '<option value="' . $counter . '"' . ($counter == $selected ? ' selected' : '') . '>' . number_format_i18n($counter) . '</option>' . "\n";
			}
		  ?>
		</select>
		<?php
	}

	function ads_page() {
		global $wpdb, $psts;
		
		if (!current_user_can('manage_options')) {
			echo "<p>" . __('Nice Try...', 'psts') . "</p>";  //If accessed properly, this message doesn't appear.
			return;
		}

		$ad_free_blogs_max = $this->max_ad_free();
		$blogs = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}supporter_ads WHERE supporter_blog_ID = '" . $wpdb->blogid . "' ORDER BY supporter_ads_ID DESC", ARRAY_A );
		$ad_free_blogs_current = count( $blogs );
		$ad_free_blogs_remaining = $ad_free_blogs_max - $ad_free_blogs_current;
		$ad_free_blogs_remaining = ($ad_free_blogs_remaining <= 0) ? 0 : $ad_free_blogs_remaining;

		//handle adding new blogs
		if (isset($_POST['submit_process'])) {
    	$expire = $psts->get_expire();
			$blogs = $_POST['blogs'];
			foreach ( $blogs as $blog_id => $value) {
				if ( $ad_free_blogs_remaining > 0 && $value == '1' ) {
					$existing_check = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}supporter_ads WHERE supporter_blog_ID = '" . $wpdb->blogid . "' AND blog_ID = '" . $blog_id . "'");
					if ( $existing_check < 1 ) {
						$ad_free_blogs_remaining--;
						$wpdb->query( "INSERT INTO {$wpdb->base_prefix}supporter_ads (blog_ID, supporter_blog_ID) VALUES ( '" . $blog_id . "', '" . $wpdb->blogid . "' )" );
					}
				}
			}
			$wpdb->query("UPDATE {$wpdb->base_prefix}supporter_ads SET expire = '" . $expire . "' WHERE supporter_blog_ID = '" . $wpdb->blogid . "'");
      echo '<div id="message" class="updated fade"><p>'.__('Sites Added.', 'psts').'</p></div>';
		}
		
		//handle removing blogs
		if (isset($_POST['submit_remove'])) {
			foreach ( (array)$_POST['blogs'] as $blog_id => $value ) {
				if ( $value == '1' ) {
					$wpdb->query( "DELETE FROM {$wpdb->base_prefix}supporter_ads WHERE blog_ID = '" . $blog_id . "' AND supporter_blog_ID = '" . $wpdb->blogid . "'" );
				}
			}
      echo '<div id="message" class="updated fade"><p>'.__('Sites Removed.', 'psts').'</p></div>';
		}
		
		$blogs = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}supporter_ads WHERE supporter_blog_ID = '" . $wpdb->blogid . "' ORDER BY supporter_ads_ID DESC", ARRAY_A );
		$ad_free_blogs_current = count( $blogs );
		$ad_free_blogs_remaining = $ad_free_blogs_max - $ad_free_blogs_current;
		$ad_free_blogs_remaining = ($ad_free_blogs_remaining <= 0) ? 0 : $ad_free_blogs_remaining;
	  ?>
		<div class="wrap">
		<script type="text/javascript">
  	  jQuery(document).ready(function () {
  		  jQuery('input#submit_remove').click(function() {
          var answer = confirm("<?php _e('Are you sure you really want to remove these blogs?', 'psts'); ?>")
          if (answer){
              return true;
          } else {
              return false;
          };
        });
  		});
  	</script>
  	<div id="icon-ms-admin" class="icon32"></div>
    <h2><?php echo $psts->get_setting('ads_name'); ?></h2>
    
		<form method="post" action="">
		<div class="metabox-holder">
    
	    <div class="postbox">
	      <h3 class='hndle'><span><?php _e('Status', 'psts') ?></span></h3>
	      <div class="inside">
    			<?php $this->message(); ?>
					<p>
					<ul>
						<li><?php _e('Maximum blogs', 'psts') ?>: <strong><?php echo $ad_free_blogs_max; ?></strong></li>
	        	<li><?php _e('Currently disabling ads on', 'psts') ?>: <strong><?php echo $ad_free_blogs_current; ?></strong></li>
	        	<li><?php _e('Remaining', 'psts') ?>: <strong><?php echo $ad_free_blogs_remaining; ?></strong></li>
        	</ul>
        	</p>
	      </div>
	    </div>
	    
	    <?php if ( $ad_free_blogs_remaining > 0 && is_pro_blog() ) { ?>
	    <div class="postbox">
	      <h3 class='hndle'><span><?php _e('Find Sites', 'psts') ?></span> - <span class="description"><?php _e('Search for a blog to disable ads on.', 'psts') ?></span></h3>
	      <div class="inside">
          <?php
          $curr_blogs = get_blogs_of_user(get_current_user_id());
          unset($curr_blogs[$wpdb->blogid]); //remove current blog
				  if (!isset($_POST['submit_search']) && $curr_blogs) {
				  ?>
          <h4><?php _e('Choose a blog you are a member of:', 'psts'); ?></h4>
					<table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
						<thead><tr>
							<th scope='col' width='75px'><?php _e('Disable Ads', 'psts'); ?></th>
							<th scope='col'><?php _e('Site', 'psts'); ?></th>
						</tr></thead>
						<tbody id='the-list'>
						<?php
						$class = '';
						foreach ($curr_blogs as $blog_id => $blog) {
	       			//=========================================================//
							echo "<tr class='" . $class . "'>";
							$existing_check = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "supporter_ads WHERE supporter_blog_ID = '" . $wpdb->blogid . "' AND blog_ID = '" . $blog_id . "'");
							if ( $existing_check > 0 ) {
								echo "<td valign='top'><center><input name='blogs[$blog_id]' id='blog_$blog_id' value='1' type='checkbox' disabled='disabled'></center></td>";
							} else {
								echo "<td valign='top'><center><input name='blogs[$blog_id]' id='blog_$blog_id' value='1' type='checkbox'></center></td>";
							}
							if ( $existing_check > 0 ) {
								echo "<td valign='top' style='color:#666666;'><strong>" . $blog->blogname . " (<em>" . $blog->domain . "</em>): " . __('Ads already disabled', 'psts') . "</strong></td>";
							} else {
								echo "<td valign='top'><label for='blog_$blog_id'><strong>" . $blog->blogname . " (<em>" . $blog->domain . "</em>)</strong></label></td>";
							}
							echo "</tr>";
							$class = ('alternate' == $class) ? '' : 'alternate';
							//=========================================================//
						}
						?>
						</tbody></table>
            <p class="submit">
            <input type="submit" name="submit_process" value="<?php _e('Disable Ads', 'psts') ?> &raquo;" />
            </p>
     		<?php } ?>
     		
     		  <h4><?php _e('Search for a blog:', 'psts'); ?></h4>
     			<p><input type="text" name="search" value="" size="30" /><br />
          <?php _e('Enter the blog domain here. Example - for "ablog.edublogs.org" you would search for "ablog".', 'psts') ?>
          </p>
          <p class="submit">
          	<input type="submit" name="submit_search" value="<?php _e('Search', 'psts') ?> &raquo;" />
          </p>
	      </div>
	    </div>
	    <?php } ?>

			<?php if ( isset($_POST['submit_search']) && is_pro_blog() ) { ?>
			
			  <div class="postbox">
		      <h3 class='hndle'><span><?php _e('Search Results', 'psts'); ?></span></h3>
		      <div class="inside">
           <?php
						$query = "SELECT blog_id, domain, path FROM {$wpdb->blogs} WHERE ( domain LIKE '%" . $wpdb->escape($_POST['search']) . "%' OR path LIKE '%" . $wpdb->escape($_POST['search']) . "%' ) AND blog_id != '" . $wpdb->blogid . "' LIMIT 150";
						$blogs = $wpdb->get_results( $query, ARRAY_A );
						if ( count( $blogs ) > 0 ) {
							if ( count( $blogs ) >= 150 ) {
								?>
		            <span class="description"><?php _e('Over 150 blogs were found matching the provided search criteria. If you do not find the blog you are looking for in the selection below please try refining your search.', 'psts') ?></span>
		            <?php
							}
						?>
					 <p>
					 <table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
						<thead><tr>
							<th scope='col' width='75px'><?php _e('Disable Ads', 'psts'); ?></th>
							<th scope='col'><?php _e('Site', 'psts'); ?></th>
						</tr></thead>
						<tbody id='the-list'>
						<?php
						$class = '';
						foreach ($blogs as $blog) {
							$blog_details = get_blog_details( $blog['blog_id'] );

	       			//=========================================================//
							echo "<tr class='" . $class . "'>";
							$existing_check = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "supporter_ads WHERE supporter_blog_ID = '" . $wpdb->blogid . "' AND blog_ID = '" . $blog['blog_id'] . "'");
							if ( $existing_check > 0 ) {
								echo "<td valign='top'><center><input name='blogs[" . $blog['blog_id'] . "]' id='blog_{$blog['blog_id']}' value='1' type='checkbox' disabled='disabled'></center></td>";
							} else {
								echo "<td valign='top'><center><input name='blogs[" . $blog['blog_id'] . "]' id='blog_{$blog['blog_id']}' value='1' type='checkbox'></center></td>";
							}
							if ( $existing_check > 0 ) {
        				echo "<td valign='top' style='color:#666666;'><strong>" . $blog_details->blogname . " (<em>" . $blog_details->domain . "</em>): " . __('Ads already disabled', 'psts') . "</strong></td>";
							} else {
								echo "<td valign='top'><label for='blog_{$blog['blog_id']}'><strong>" . $blog_details->blogname . " (<em>" . $blog_details->domain . "</em>)</strong></label></td>";
							}
							echo "</tr>";
							$class = ('alternate' == $class) ? '' : 'alternate';
							//=========================================================//
						}
						?>
            </tbody></table></p>
            <p class="submit">
            <input type="submit" name="back" value="&laquo; <?php _e('Back', 'psts') ?>" />
            <input type="submit" name="submit_process" value="<?php _e('Disable Ads', 'psts') ?> &raquo;" />
	          <?php } else { ?>
            <p><?php _e('No blogs found matching your search criteria.', 'psts') ?></p>
            <?php } ?>
		      </div>
		    </div>
			
			<?php } else { ?>
			
	      <?php if ( $ad_free_blogs_current > 0 && is_pro_blog() ) { ?>
		    <div class="postbox">
		      <h3 class='hndle'><span><?php _e('Currently Disabled Sites', 'psts'); ?></span></h3>
		      <div class="inside">
					 <p>
					 <table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
						<thead><tr>
							<th scope='col' width='45px'><?php _e('Remove', 'psts'); ?></th>
							<th scope='col'><?php _e('Site', 'psts'); ?></th>
						</tr></thead>
						<tbody id='the-list'>
						<?php
						$class = '';
						foreach ($blogs as $blog) {
							$blog_details = get_blog_details( $blog['blog_ID'] );
							//=========================================================//
							echo "<tr class='" . $class . "'>";
							echo "<td valign='top' style='text-align: center;'><input name='blogs[" . $blog['blog_ID'] . "]' id='blog_rm_{$blog['blog_ID']}' value='1' type='checkbox'></td>";
       				echo "<td valign='top'><label for='blog_rm_{$blog['blog_ID']}'><strong>" . $blog_details->blogname . " (<em>" . $blog_details->domain . "</em>)</strong></label></td>";
							echo "</tr>";
							$class = ('alternate' == $class) ? '' : 'alternate';
							//=========================================================//
						}
						?>
						</tbody>
						</table></p>
	          <p class="submit">
	          <input type="submit" id="submit_remove" name="submit_remove" value="<?php _e('Remove', 'psts') ?> &raquo;" />
	          </p>
		      </div>
		    </div>
		    <?php } ?>
		    
      <?php }

		echo '</div></form></div>';
	}
}

//register the module
psts_register_module( 'ProSites_Module_Ads', __('Advertising', 'psts'), __('Allows you to disable ads for a Pro Site level, or give a Pro Site level the ability to disable ads on a number of other blogs.', 'psts') );


/* Ads functions used by other plugins */
function psts_show_ads($blog_id = null) {
	global $ProSites_Module_Ads;

	if ( isset($ProSites_Module_Ads) && is_object($ProSites_Module_Ads) )
	  return $ProSites_Module_Ads->show_ads($blog_id);
	else
	  return true;
}

function psts_hide_ads($blog_id = null) {
 	global $ProSites_Module_Ads;

	if ( isset($ProSites_Module_Ads) && is_object($ProSites_Module_Ads) )
	  return $ProSites_Module_Ads->hide_ads($blog_id);
	else
	  return false;
}


/* depreciated functions */
function supporter_show_ads($blog_id = null) {
	return psts_show_ads($blog_id);
}

function supporter_hide_ads($blog_id = null) {
  return psts_hide_ads($blog_id);
}
?>