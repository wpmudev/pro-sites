<?php
/*
Plugin Name: Pro Sites (Feature: MarketPress Global Products Filter)
*/
class ProSites_Module_MarketPress_Global {
	
	var $pro_sites = false;
	
	function ProSites_Module_MarketPress_Global() {
		$this->__construct();
	}

  function __construct() {
		add_filter( 'mp_list_global_products_results', array(&$this, 'filter') );
	}
	
	function filter($results) {
		global $wpdb;
		
		if (!$this->pro_sites)
			$this->pro_sites = $wpdb->get_col("SELECT blog_ID FROM {$wpdb->base_prefix}pro_sites WHERE expire > '" . time() . "'");
			
    foreach ($results as $key => $row) {
			if ( !in_array($row->blog_id, $this->pro_sites) )
				unset($results[$key]);
		}
		return $results;
	}
	
}

//register the module
if (class_exists('MarketPress'))
	psts_register_module( 'ProSites_Module_MarketPress_Global', __('MarketPress Global Products Filter', 'psts'), __('When enabled, removes non-pro site products from the MarketPress global product lists.', 'psts') );
?>