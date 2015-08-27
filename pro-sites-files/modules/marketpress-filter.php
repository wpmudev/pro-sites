<?php

/*
Plugin Name: Pro Sites (Feature: MarketPress Global Products Filter)
*/

class ProSites_Module_MarketPress_Global {

	static $user_label;
	static $user_description;

	var $pro_sites = false;

	// Module name for registering
	public static function get_name() {
		return __('MarketPress Global Products Filter', 'psts');
	}

	// Module description for registering
	public static function get_description() {
		return __('When enabled, removes non-pro site products from the MarketPress global product lists.', 'psts');
	}

	// This module requires a specific class
	public static function get_class_restriction() {
		return 'MarketPress';
	}

	function __construct() {
		if( is_main_site( get_current_blog_id() ) ) {
			return;
		}
		add_filter( 'mp_list_global_products_results', array( &$this, 'filter' ) );

		self::$user_label       = __( 'Market Press', 'psts' );
		self::$user_description = __( 'Display on MarketPress global product list', 'psts' );
	}

	function filter( $results ) {
		global $wpdb;

		if ( ! $this->pro_sites ) {
			$this->pro_sites = $wpdb->get_col( "SELECT blog_ID FROM {$wpdb->base_prefix}pro_sites WHERE expire > '" . time() . "'" );
		}

		foreach ( $results as $key => $row ) {
			if ( ! in_array( $row->blog_id, $this->pro_sites ) ) {
				unset( $results[ $key ] );
			}
		}

		return $results;
	}

	public static function is_included( $level_id ) {
		switch ( $level_id ) {
			default:
				return false;
		}
	}

}
