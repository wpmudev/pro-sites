<?php
/*
Plugin Name: Pro Sites (Feature: Limit Writing)
*/
class ProSites_Module_Writing {

	function ProSites_Module_Writing() {
		$this->__construct();
	}

  function __construct() {
		add_action( 'psts_settings_page', array(&$this, 'settings') );
		add_filter( 'psts_settings_filter', array(&$this, 'settings_process') );
		add_action( 'admin_notices', array(&$this, 'message') );
		add_filter( 'user_has_cap', array(&$this, 'write_filter'), 10, 3 );
	}

	function write_filter($allcaps, $caps, $args) {
    global $psts;

		if ( !is_pro_site(false, $psts->get_setting('publishing_level', 1)) ) {
	    //limit posts
	    if ($psts->get_setting('publishing_posts'))
	      unset($allcaps["publish_posts"]);
	    //limit pages
	    if ($psts->get_setting('publishing_pages'))
	      unset($allcaps["publish_pages"]);
	  }
		return $allcaps;
	}

	function settings_process($settings) {
	  $settings['publishing_posts'] = isset($settings['publishing_posts']) ? 1 : 0;
	  $settings['publishing_pages'] = isset($settings['publishing_pages']) ? 1 : 0;
	  return $settings;
	}

	function settings() {
  	global $psts;
	  $levels = (array)get_site_option( 'psts_levels' );
		?>
		<div class="postbox">
		  <h3 class='hndle'><span><?php _e('Limit Publishing', 'psts') ?></span> - <span class="description"><?php _e('Allows you to only enable writing posts and/or pages for selected Pro Site levels.', 'psts') ?></span></h3>
		  <div class="inside">
			  <table class="form-table">
          <tr valign="top">
				  <th scope="row"><?php _e('Pro Site Level', 'psts') ?></th>
				  <td>
				  <select name="psts[publishing_level]">
						<?php
						foreach ($levels as $level => $value) {
						?><option value="<?php echo $level; ?>"<?php selected($psts->get_setting('publishing_level', 1), $level) ?>><?php echo $level . ': ' . esc_attr($value['name']); ?></option><?php
						}
						?>
	        </select><br />
	        <?php _e('Select the minimum level required to enable publishing posts or pages.', 'psts') ?>
					</td>
				  </tr>
					<tr valign="top">
				  <th scope="row"><?php _e('Limit Posts', 'psts'); ?></th>
				  <td><label><input type="checkbox" name="psts[publishing_posts]" value="1"<?php checked($psts->get_setting('publishing_posts')); ?> /> <?php _e('Limit', 'psts'); ?></label></td>
				  </tr>
					<tr valign="top">
				  <th scope="row"><?php _e('Posts Restricted Message', 'psts') ?></th>
				  <td><input type="text" name="psts[publishing_message_posts]" id="publishing_message_posts" value="<?php echo esc_attr($psts->get_setting('publishing_message_posts')); ?>" style="width: 95%" />
				  <br /><?php _e('Required - This message is displayed on the post screen for sites that don\'t have permissions. "LEVEL" will be replaced with the needed level name.', 'psts') ?></td>
				  </tr>
				  <tr valign="top">
				  <th scope="row"><?php _e('Limit Pages', 'psts'); ?></th>
				  <td><label><input type="checkbox" name="psts[publishing_pages]" value="1"<?php checked($psts->get_setting('publishing_pages')); ?> /> <?php _e('Limit', 'psts'); ?></label></td>
				  </tr>
	      	<tr valign="top">
				  <th scope="row"><?php _e('Pages Restricted Message', 'psts') ?></th>
				  <td><input type="text" name="psts[publishing_message_pages]" id="publishing_message_pages" value="<?php echo esc_attr($psts->get_setting('publishing_message_pages')); ?>" style="width: 95%" />
				  <br /><?php _e('Required - This message is displayed on the page screen for sites that don\'t have permissions. "LEVEL" will be replaced with the needed level name.', 'psts') ?></td>
				  </tr>
			  </table>
		  </div>
		</div>
	  <?php
	}

	function message() {
		global $psts, $current_screen, $blog_id;

    if ( is_pro_site(false, $psts->get_setting('publishing_level', 1)) )
      return;

	  if ( $psts->get_setting('publishing_posts') && in_array( $current_screen->id, array('edit-post', 'post') ) ) {
	    $notice = str_replace( 'LEVEL', $psts->get_level_setting($psts->get_setting('publishing_level', 1), 'name'), $psts->get_setting('publishing_message_posts') );
	   	echo '<div class="error"><p><a href="'.$psts->checkout_url($blog_id).'">' . $notice . '</a></p></div>';
		} else if ( $psts->get_setting('publishing_pages') && in_array( $current_screen->id, array('edit-page', 'page') ) ) {
	    $notice = str_replace( 'LEVEL', $psts->get_level_setting($psts->get_setting('publishing_level', 1), 'name'), $psts->get_setting('publishing_message_pages') );
	   	echo '<div class="error"><p><a href="'.$psts->checkout_url($blog_id).'">' . $notice . '</a></p></div>';
		}
	}
}

//register the module
psts_register_module( 'ProSites_Module_Writing', __('Limit Publishing', 'psts'), __('Allows you to only enable writing posts and/or pages for selected Pro Site levels.', 'psts') );
?>