<?php

/*
Plugin Name: Pro Sites (Feature: Premium Support)
*/

class ProSites_Module_Support {

	static $user_label;
	static $user_description;

	// Module name for registering
	public static function get_name() {
		return __('Premium Support', 'psts');
	}

	// Module description for registering
	public static function get_description() {
		return __('Allows you to provide a premium direct to email support page for selected Pro Site levels.', 'psts');
	}

	function __construct() {
//		add_action( 'psts_settings_page', array( &$this, 'settings' ) );
		add_action( 'admin_menu', array( &$this, 'plug_page' ), 99 );

		self::$user_label       = __( 'Premium Support', 'psts' );
		self::$user_description = __( 'Include Premium direct to email support', 'psts' );
	}

	function plug_page() {
		global $psts;
		//add it under the pro blogs menu
		if ( ! is_main_site() ) {
			add_submenu_page( 'psts-checkout', $psts->get_setting( 'ps_name' ), $psts->get_setting( 'ps_name' ), 'edit_pages', 'premium-support', array(
				&$this,
				'support_page'
			) );
		}
	}

	function settings() {
		global $psts;
		$levels = (array) get_site_option( 'psts_levels' );
		?>
<!--		<div class="postbox">-->
<!--			<h3 class="hndle" style="cursor:auto;"><span>--><?php //_e( 'Premium Support', 'psts' ) ?><!--</span> --->
<!--				<span class="description">--><?php //_e( 'Allows you to provide a premium direct to email support page for selected Pro Site levels.', 'psts' ) ?><!--</span>-->
<!--			</h3>-->

			<div class="inside">
				<table class="form-table">
					<tr valign="top">
						<th scope="row" class="psts-help-div psts-premium-prosite-level"><?php echo __( 'Pro Site Level', 'psts' ) . $psts->help_text( __( 'Select the minimum level required to use premium support.', 'psts' ) ); ?></th>
						<td>
							<select name="psts[ps_level]" class="chosen">
								<?php
								foreach ( $levels as $level => $value ) {
									?>
									<option value="<?php echo $level; ?>"<?php selected( $psts->get_setting( 'ps_level', 1 ), $level ) ?>><?php echo $level . ': ' . esc_attr( $value['name'] ); ?></option><?php
								}
								?>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="psts-help-div psts-support-email"><?php echo __( 'Support Email', 'psts' ) . $psts->help_text( __( 'The email address to send premium support messages to.', 'psts' ) ); ?></th>
						<td>
							<input type="text" name="psts[ps_email]" id="ps_email" value="<?php echo esc_attr( $psts->get_setting( 'ps_email' ) ); ?>" size="40"/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="psts-help-div psts-rename-feature"><?php echo __( 'Rename Feature', 'psts' ) . $psts->help_text( __( 'Required - No HTML! - Make this short and sweet.', 'psts' ) ); ?></th>
						<td>
							<input type="text" name="psts[ps_name]" id="ps_name" value="<?php echo esc_attr( $psts->get_setting( 'ps_name' ) ); ?>" size="30"/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="psts-help-div psts-premium-support"><?php echo __( 'Premium Support Message', 'psts' ) . $psts->help_text( __( 'The message that is displayed on the Premium Support page. HTML allowed.', 'psts' ) ); ?></th>
						<td>
							<textarea name="psts[ps_message]" id="ps_message" rows="5" style="width: 95%"><?php echo esc_textarea( $psts->get_setting( 'ps_message' ) ); ?></textarea>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="psts-help-div psts-restricted-message"><?php echo __( 'Restricted Message', 'psts' ) . $psts->help_text( __( 'Required - This message is displayed on for sites that don\'t have permissions. "LEVEL" will be replaced with the needed level name.', 'psts' ) ); ?></th>
						<td>
							<input type="text" name="psts[ps_notice]" id="ps_notice" value="<?php echo esc_attr( $psts->get_setting( 'ps_notice' ) ); ?>" style="width: 95%"/>
							<br/><?php ?>
						</td>
					</tr>
				</table>
			</div>
<!--		</div>-->
	<?php
	}

	function support_page() {
		global $current_user, $psts, $blog_id;

		$disabled = '';
		?>
		<div class="wrap">
			<h1><?php echo $psts->get_setting( 'ps_name' ); ?></h1>

			<?php
			if ( isset( $_POST['support-message'] ) && is_supporter() ) {
				$message         = wp_filter_nohtml_kses( stripslashes( trim( $_POST['support-message'] ) ) );
				$support_email   = $psts->get_setting( 'ps_email' );
				$message_headers = "MIME-Version: 1.0\n" . "From: \"{$current_user->display_name}\" <{$current_user->user_email}>\n" . "Content-Type: text/plain; charset=\"" . get_option( 'blog_charset' ) . "\"\n";
				$subject         = sprintf( __( 'Premium Support Request: %s', 'psts' ), get_bloginfo( 'url' ) );
				$message         = sprintf( __( "%s has submitted a new premium support request for the site %s (%s).\nHere is their message:\n_______________________\n\n%s\n\n_______________________\nYou can reply to this email directly.", 'psts' ), $current_user->display_name, get_bloginfo( 'name' ), get_bloginfo( 'url' ), $message );
				$message .= sprintf( __( "Site Address: %s\n", 'psts' ), home_url() );
				$message .= sprintf( __( "Site Admin: %s\n", 'psts' ), admin_url() );

				remove_filter( 'wp_mail_from', 'bp_core_email_from_address_filter' );
				remove_filter( 'wp_mail_from_name', 'bp_core_email_from_name_filter' );
				wp_mail( $support_email, $subject, $message, $message_headers );

				echo '<div id="message" class="updated fade"><p>' . __( 'Your message has been sent! Someone will reply to your email shortly.', 'psts' ) . '</p></div>';
				$disabled = ' disabled="disabled"';
			}
			?>
			<p><?php echo $psts->get_setting( 'ps_message' ); ?></p>

			<h3><?php _e( 'Your Support Question:', 'psts' ) ?></h3>
			<?php
			//show feature message
			if ( ! is_pro_site( false, $psts->get_setting( 'ps_level', 1 ) ) ) {
				$notice = str_replace( 'LEVEL', $psts->get_level_setting( $psts->get_setting( 'ps_level', 1 ), 'name' ), $psts->get_setting( 'ps_notice' ) );
				echo '<div class="error"><p><a href="' . $psts->checkout_url( $blog_id ) . '">' . $notice . '</a></p></div>';
				$disabled = ' disabled="disabled"';
			}
			?>
			<form method="post" action="">
				<textarea name="support-message" type="text" rows="10" wrap="soft" id="support-message" style="width: 100%"<?php echo $disabled; ?>></textarea>

				<p class="submit">
					<input type="submit" value="<?php _e( 'Submit Request &raquo;', 'psts' ) ?>"<?php echo $disabled; ?> />
				</p>
			</form>
		</div>
	<?php
	}

	public static function is_included( $level_id ) {
		switch ( $level_id ) {
			default:
				return false;
		}
	}

	/**
	 * Returns the minimum required level to remove restrictions
	 */
	public static function required_level() {
		global $psts;

		return $psts->get_setting( 'ps_level' );

	}

	public static function get_level_status( $level_id ) {
		global $psts;

		$min_level = $psts->get_setting( 'ps_level', 1 );

		if( $level_id >= $min_level ) {
			return 'tick';
		} else {
			return 'cross';
		}

	}
}