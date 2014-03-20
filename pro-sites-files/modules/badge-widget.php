<?php
/*
Plugin Name: Pro Sites (Feature: Pro Widget)
*/
class ProSites_Module_ProWidget {
  
  static $user_label;
  static $user_description;

  function __construct() {
		add_action( 'psts_settings_page', array(&$this, 'settings') );
    
    self::$user_label       = __('Pro Widget', 'psts');
    self::$user_description = __('Brag about your Pro Level with a widget', 'psts');
		
		if (is_pro_site()) {
			add_action( 'widgets_init', create_function('', 'return register_widget("ProSites_Pro_Widget");') );
		}
	}
	
	function settings() {
    global $psts;
		$levels = (array)get_site_option( 'psts_levels' );
		$images = $psts->get_setting( 'widget_imgs', array() );
		?>
		<div class="postbox">
		  <h3 class="hndle" style="cursor:auto;"><span><?php _e('Pro Widget', 'psts') ?></span> - <span class="description"><?php _e('Allows Pro Sites to put a widget in their sidebar to proudly display their Pro level.', 'psts') ?></span></h3>
		  <div class="inside">
				<span class="description"><?php _e('Enter a url to the badge image file for each corresponding level. It is recommended to use an image with a maximum width of 160px to be compatible with most theme sidebars.', 'psts') ?></span>
			  <table class="form-table">
					<?php
					foreach ($levels as $level => $value) {
					?>
					<tr valign="top">
					<th scope="row"><?php printf(__('%s Image URL:', 'psts'), $level.': '.esc_attr($value['name'])); ?></th>
					<td><input type="text" name="psts[widget_imgs][<?php echo $level; ?>]" value="<?php echo isset($images[$level]) ? esc_url($images[$level]) : ''; ?>" style="width: 95%" /></td>
					</tr>
					<?php
					}
					?>
					<tr valign="top">
					<th scope="row"><?php _e('Link URL (optional)', 'psts'); ?></th>
					<td><span class="description"><?php _e('If you would like the badge to link somewhere (like a page describing Pro Sites) enter the url here.', 'psts') ?></span><br>
					<input type="text" name="psts[widget_imgs][link]" value="<?php echo isset($images['link']) ? esc_url($images['link']) : ''; ?>" style="width: 95%" /></td>
					</tr>
			  </table>
		  </div>
		</div>
	  <?php
	}
  public static function is_included ( $level_id ) {
    switch ( $level_id ) {
      default:
        return FALSE;
    }
  }

}

//Declare the widget class
class ProSites_Pro_Widget extends WP_Widget {

	function ProSites_Pro_Widget() {
		global $psts, $blog_id;
		$widget_ops = array( 'classname' => 'psts_widget', 'description' => sprintf(__('Proudly display your %s status in you sidebar!', 'psts'), $psts->get_level_setting($psts->get_level($blog_id), 'name')) );
		$this->WP_Widget('psts_widget', sprintf(__('%s Widget', 'psts'), $psts->get_setting('rebrand')), $widget_ops);
	}

	function widget( $args, $instance ) {
		global $psts, $blog_id;
		$level = $psts->get_level($blog_id);
		$level_name = $psts->get_level_setting($level, 'name');
		$images = $psts->get_setting( 'widget_imgs', array() );
		
		//if image not set
		//if ( !isset($images[$level]) || empty($images[$level]) )
		//	return;
		
		extract($args);

		echo $before_widget;
		?>
		<center>
		<?php if (!empty($images['link'])) echo '<a href="' . esc_url($images['link']) . '">'; ?>
			<img src="<?php echo esc_url($images[$level]); ?>" alt="<?php printf(__('A proud %s site.', 'psts'), esc_attr($level_name)); ?>" border="0" />
		<?php if (!empty($images['link'])) echo '</a>'; ?>
		</center>
		<?php
		echo $after_widget;
	}
	
	/*
	function update( $new_instance, $old_instance ) {
		return $instance;
	}

	function form( $instance ) {
		
	}
	*/
}

//register the module
psts_register_module( 'ProSites_Module_ProWidget', __('Pro Widget', 'psts'), __('Allows Pro Sites to put a widget in their sidebar to proudly display their Pro level.', 'psts') );
?>