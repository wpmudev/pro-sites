<?php

/*
Plugin Name: Pro Sites (Feature: Restrict XML-RPC & Atom Publishing)
*/

class ProSites_Module_XMLRPC {

	static $user_label;
	static $user_description;

	// Module name for registering
	public static function get_name() {
		return __('Restrict XML-RPC', 'psts');
	}

	// Module description for registering
	public static function get_description() {
		return __('Allows you to only enable XML-RPC for selected Pro Site levels.', 'psts');
	}


	function __construct() {
		if ( ! is_main_site( get_current_blog_id() ) ) {
			add_filter( 'init', array( &$this, 'xmlrpc_check' ) );
			add_action( 'admin_notices', array( &$this, 'message' ) );
		}

		self::$user_label       = __( 'XML RPC', 'psts' );
		self::$user_description = __( 'Can use XML RPC calls', 'psts' );
	}

	//for ads module to allow remote publishing
	function ads_xmlrpc() {
		global $psts;

		if ( function_exists( 'psts_hide_ads' ) && $psts->get_setting( 'ads_xmlrpc' ) && psts_hide_ads() ) {
			return true;
		} else {
			return false;
		}
	}

	function xmlrpc_check() {
		global $psts;

		if ( ! is_pro_site( false, $psts->get_setting( 'xmlrpc_level', 1 ) ) && ! $this->ads_xmlrpc() ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
		} else if ( defined( 'PSTS_FORCE_XMLRPC_ON' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_true' );
		}
	}

	function message() {
		global $psts, $current_screen, $blog_id;

		if ( is_pro_site( false, $psts->get_setting( 'xmlrpc_level', 1 ) ) || $this->ads_xmlrpc() ) {
			return;
		}

		if ( $current_screen->id == 'options-writing' ) {
			$notice = str_replace( 'LEVEL', $psts->get_level_setting( $psts->get_setting( 'xmlrpc_level', 1 ), 'name' ), $psts->get_setting( 'xmlrpc_message' ) );
			echo '<div class="error"><p><a href="' . $psts->checkout_url( $blog_id ) . '">' . $notice . '</a></p></div>';
		}
	}

	function settings() {
		global $psts;
		$levels = (array) get_site_option( 'psts_levels' );
		?>
<!--		<div class="postbox">-->
<!--			<h3 class="hndle" style="cursor:auto;"><span>--><?php //_e( 'Restrict XML-RPC', 'psts' ) ?><!--</span> --->
<!--				<span class="description">--><?php //_e( 'Allows you to only enable XML-RPC for selected Pro Site levels.', 'psts' ) ?><!--</span>-->
<!--			</h3>-->

			<div class="inside">
				<table class="form-table">
					<tr valign="top">
						<th scope="row" class="psts-help-div psts-xml-prolevel"><?php echo __( 'Pro Site Level', 'psts' ) . $psts->help_text( __( 'Select the minimum level required to enable remote publishing.', 'psts' ) ); ?></th>
						<td>
							<select name="psts[xmlrpc_level]" class="chosen">
								<?php
								foreach ( $levels as $level => $value ) {
									?>
									<option value="<?php echo $level; ?>"<?php selected( $psts->get_setting( 'xmlrpc_level', 1 ), $level ) ?>><?php echo $level . ': ' . esc_attr( $value['name'] ); ?></option><?php
								}
								?>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="psts-help-div psts-xml-restricted"><?php echo __( 'Restricted Message', 'psts' ) . $psts->help_text( __( 'Required - This message is displayed on the writing settings screen for sites that don\'t have permissions. "LEVEL" will be replaced with the needed level name.', 'psts' ) ); ?></th>
						<td>
							<input type="text" name="psts[xmlrpc_message]" id="xmlrpc_message" value="<?php echo esc_attr( $psts->get_setting( 'xmlrpc_message' ) ); ?>" style="width: 95%"/>
						</td>
					</tr>
				</table>
			</div>
<!--		</div>-->
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

		return $psts->get_setting( 'xmlrpc_level' );

	}

	public static function get_level_status( $level_id ) {
		global $psts;

		$min_level = $psts->get_setting( 'xmlrpc_level', 1 );

		if( $level_id >= $min_level ) {
			return 'tick';
		} else {
			return 'cross';
		}

	}
}