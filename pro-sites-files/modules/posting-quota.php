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
		add_filter( 'psts_settings_filter', array(&$this, 'settings_process') );
		
		add_action( 'admin_notices', array(&$this, 'message') );
		add_filter( 'user_has_cap', array(&$this, 'write_filter'), 10, 3 );
	}

	function write_filter($allcaps, $caps, $args) {
    global $psts;

		if ( !is_pro_site(false, $psts->get_setting('pq_level', 1)) ) {
			$quota_settings = $psts->get_setting("pq_quotas");
			if (is_array($quota_settings)) {
				foreach ($quota_settings as $post_type => $settings) {
					if ( is_numeric(@$settings['quota']) && wp_count_posts($post_type)->publish >= @$settings['quota'] ) {
						$pt_obj = get_post_type_object($post_type);
						unset($allcaps[$pt_obj->cap->publish_posts]);
					}
				}
			}
	  }
		return $allcaps;
	}
	
	function settings_process($settings) {
  	global $psts;
		
		if (is_array($settings['pq_quotas'])) {
			$caps = array();
			foreach ($settings['pq_quotas'] as $post_type => $vars) {
				$pt_obj = get_post_type_object($post_type);
				//check if 
				if (isset($caps[$pt_obj->cap->publish_posts]))
					$settings['pq_quotas'][$post_type]['quota'] = $caps[$pt_obj->cap->publish_posts];
				else
					$caps[$pt_obj->cap->publish_posts] = @$vars['quota'];
			}
		}
		return $settings;
	}
	
	function settings() {
  	global $psts;
		?>
		<div class="postbox">
		  <h3 class='hndle'><span><?php _e('Post/Page Quotas', 'psts') ?></span> - <span class="description"><?php _e('Allows you to limit the number of post types for selected Pro Site levels.', 'psts') ?></span></h3>
		  <div class="inside">
			  <table class="form-table">
          <tr valign="top">
				  <th scope="row"><?php _e('Pro Site Level', 'psts') ?></th>
				  <td>
				  <select name="psts[pq_level]">
						<?php
						$levels = (array)get_site_option( 'psts_levels' );
						foreach ($levels as $level => $value) {
						?><option value="<?php echo $level; ?>"<?php selected($psts->get_setting('pq_level', 1), $level) ?>><?php echo $level . ': ' . esc_attr($value['name']); ?></option><?php
						}
						?>
	        </select><br />
	        <?php _e('Select the minimum level required to remove quotas.', 'psts') ?>
					</td>
				  </tr>
					<?php
					$quota_settings = $psts->get_setting("pq_quotas");
					$post_types = get_post_types(array('show_ui' => true), 'objects', 'and');
					$caps = array();
					foreach ($post_types as $post_type) {
						$quota = isset($quota_settings[$post_type->name]['quota']) ? $quota_settings[$post_type->name]['quota'] : 'unlimited';
						$quota_msg = isset($quota_settings[$post_type->name]['message']) ? $quota_settings[$post_type->name]['message'] : sprintf(__('To publish more %s, please upgrade to LEVEL &raquo;', 'psts'), $post_type->label);
					?>
					<tr valign="top">
				  <th scope="row"><?php printf(__('%s Quota', 'psts'), $post_type->label); ?></th>
				  <td>
						<?php if (isset($caps[$post_type->cap->publish_posts])) { ?>
						<select disabled="disabled"><option><?php printf(__('Same as %s', 'psts'), $caps[$post_type->cap->publish_posts]); ?></option></select>
						<?php } else { ?>
						<select name="psts[pq_quotas][<?php echo $post_type->name; ?>][quota]">
							<option value="unlimited"<?php selected($quota, 'unlimited'); ?>><?php _e('Unlimited', 'psts'); ?></option>
						<?php
							for ( $counter = 1; $counter <= 1000; $counter++ ) {
								echo '<option value="' . $counter . '"' . ($counter == $quota ? ' selected' : '') . '>' . number_format_i18n($counter) . '</option>' . "\n";
							}
						?>
						</select>
						<?php } ?>
						<input type="text" name="psts[pq_quotas][<?php echo $post_type->name; ?>][message]" value="<?php echo esc_attr($quota_msg); ?>" style="width: 90%" />
						<br /><?php _e('Choose the quota and message that is displayed on the add post screen for sites that have used up their quota. "LEVEL" will be replaced with the needed level name.', 'psts') ?>
					</td>
				  </tr>
					<?php
						$caps[$post_type->cap->publish_posts] = $post_type->label;
					}
					?>
			  </table>
		  </div>
		</div>
	  <?php
	}

	function message() {
		global $psts, $current_screen, $post_type, $blog_id;

    if ( is_pro_site(false, $psts->get_setting('pq_level', 1)) )
      return;
		
		if ( in_array( $current_screen->id, array('edit-post', 'post', 'edit-page', 'page') ) ) {
			$quota_settings = $psts->get_setting("pq_quotas");
			if (is_array($quota_settings)) {
				if ( isset($quota_settings[$post_type]) ) {
					if ( is_numeric(@$quota_settings[$post_type]['quota']) && wp_count_posts($post_type)->publish >= @$quota_settings[$post_type]['quota'] ) {
						$notice = str_replace( 'LEVEL', $psts->get_level_setting($psts->get_setting('pq_level', 1), 'name'), @$quota_settings[$post_type]['message'] );
						echo '<div class="error"><p><a href="'.$psts->checkout_url($blog_id).'">' . $notice . '</a></p></div>';	
					}
				}
			}
		}
	}
}

//register the module
psts_register_module( 'ProSites_Module_PostingQuota', __('Post/Page Quotas', 'psts'), __('Allows you to limit the number of post types for selected Pro Site levels.', 'psts') );
?>