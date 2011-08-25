<?php
/*
Plugin Name: Pro Sites (Feature: Posting Quota)
*/
class ProSites_Module_PostingQuota {

	function ProSites_Module_PostingQuota() {
		$this->__construct();
	}

  function __construct() {
		add_action( 'psts_settings_page', array(&$this, 'settings') );
		add_action( 'admin_notices', array(&$this, 'message') );
		add_filter( 'user_has_cap', array(&$this, 'write_filter'), 10, 3 );
	}

	function write_filter($allcaps, $caps, $args) {
    global $psts;

		if ( !is_pro_blog(false, $psts->get_setting('pq_level', 1)) ) {
	    //limit posts
	    if ( is_numeric($psts->get_setting('pq_posts')) && wp_count_posts('post') >= $psts->get_setting('pq_posts') )
	      unset($allcaps["publish_posts"]);
	    //limit pages
	    if ( is_numeric($psts->get_setting('pq_pages')) && wp_count_posts('page') >= $psts->get_setting('pq_pages') )
	      unset($allcaps["publish_pages"]);
	  }
		return $allcaps;
	}

	function settings() {
  	global $psts;
	  $levels = (array)get_site_option( 'psts_levels' );
		?>
		<div class="postbox">
		  <h3 class='hndle'><span><?php _e('Limit Post/Page Count', 'psts') ?></span> - <span class="description"><?php _e('Allows you to limit the number of posts and/or pages for selected Pro Site levels.', 'psts') ?></span></h3>
		  <div class="inside">
			  <table class="form-table">
          <tr valign="top">
				  <th scope="row"><?php _e('Pro Site Level', 'psts') ?></th>
				  <td>
				  <select name="psts[pq_level]">
						<?php
						foreach ($levels as $level => $value) {
						?><option value="<?php echo $level; ?>"<?php selected($psts->get_setting('pq_level', 1), $level) ?>><?php echo $level . ': ' . esc_attr($value['name']); ?></option><?php
						}
						?>
	        </select><br />
	        <?php _e('Select the minimum level required to remove post or page quotas.', 'psts') ?>
					</td>
				  </tr>
					<tr valign="top">
				  <th scope="row"><?php _e('Post Quota', 'psts'); ?></th>
				  <td>
						<select name="psts[pq_posts]">
							<option value="unlimited"<?php selected($psts->get_setting('pq_posts'), 'unlimited'); ?>><?php _e('Unlimited', 'psts'); ?></option>
						<?php
							$selected = $psts->get_setting('pq_posts');
							for ( $counter = 1; $counter < 1000; $counter++ ) {
								echo '<option value="' . $counter . '"' . ($counter == $selected ? ' selected' : '') . '>' . number_format_i18n($counter) . '</option>' . "\n";
							}
						?>
						</select>
					</td>
				  </tr>
					<tr valign="top">
				  <th scope="row"><?php _e('Post Quota Out Message', 'psts') ?></th>
				  <td><input type="text" name="psts[pq_post_message]" id="pq_post_message" value="<?php echo esc_attr($psts->get_setting('pq_post_message')); ?>" style="width: 95%" />
				  <br /><?php _e('Required - This message is displayed on the add post screen for sites that have used up their quota. "LEVEL" will be replaced with the needed level name.', 'psts') ?></td>
				  </tr>
				  <tr valign="top">
				  <th scope="row"><?php _e('Page Quota', 'psts'); ?></th>
				  <td>
						<select name="psts[pq_pages]">
							<option value="unlimited"<?php selected($psts->get_setting('pq_pages'), 'unlimited'); ?>><?php _e('Unlimited', 'psts'); ?></option>
						<?php
							$selected = $psts->get_setting('pq_pages');
							for ( $counter = 1; $counter < 1000; $counter++ ) {
								echo '<option value="' . $counter . '"' . ($counter == $selected ? ' selected' : '') . '>' . number_format_i18n($counter) . '</option>' . "\n";
							}
						?>
						</select>
					</td>
					</tr>
	      	<tr valign="top">
				  <th scope="row"><?php _e('Page Quota Out Message', 'psts') ?></th>
				  <td><input type="text" name="psts[pq_page_message]" id="pq_page_message" value="<?php echo esc_attr($psts->get_setting('pq_page_message')); ?>" style="width: 95%" />
				  <br /><?php _e('Required - This message is displayed on the add page screen for sites that have used up their quota. "LEVEL" will be replaced with the needed level name.', 'psts') ?></td>
				  </tr>
			  </table>
		  </div>
		</div>
	  <?php
	}

	function message() {
		global $psts, $current_screen;

    if ( is_pro_blog(false, $psts->get_setting('pq_level', 1)) )
      return;

	  if ( in_array( $current_screen->id, array('edit-post', 'post') ) && is_numeric($psts->get_setting('pq_posts')) && wp_count_posts('post') >= $psts->get_setting('pq_posts') ) {
	    $notice = str_replace( 'LEVEL', $psts->get_level_setting($psts->get_setting('pq_level', 1), 'name'), $psts->get_setting('pq_post_message') );
	   	echo '<div class="error"><p><a href="'.$psts->checkout_url().'">' . $notice . '</a></p></div>';
		}
		
		if ( in_array( $current_screen->id, array('edit-page', 'page') ) && is_numeric($psts->get_setting('pq_pages')) && wp_count_posts('page') >= $psts->get_setting('pq_pages') ) {
	    $notice = str_replace( 'LEVEL', $psts->get_level_setting($psts->get_setting('pq_level', 1), 'name'), $psts->get_setting('pq_page_message') );
	   	echo '<div class="error"><p><a href="'.$psts->checkout_url().'">' . $notice . '</a></p></div>';
		}
	}
}

//register the module
psts_register_module( 'ProSites_Module_PostingQuota', __('Post/Page Quota', 'psts'), __('Allows you to limit the number of posts and/or pages for selected Pro Site levels.', 'psts') );
?>