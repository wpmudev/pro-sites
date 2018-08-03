<?php

if ( ! class_exists( 'ProSites_Model_Receipt' ) ) {

	class ProSites_Model_Receipt {

		private $items = array();

		public function __construct() {

		}

		public function add_item( $amount, $description, $currency = '', $positive = null ) {
			ProSites_Model_Receipt::items_add( $this->items, $amount, $description, $currency, $positive );
		}

		public static function items_add( &$items, $amount, $description, $currency = '', $positive = null ) {

			// Force symbol?
			if( null !== $positive ) {
				$amount = $positive ? abs( $amount ) : abs( $amount ) * -1;
			}

			$new_item = array(
				'amount' => $amount,
				'description' => $description,
			);

			if( ! empty( $currency ) ) {
				$new_item['currency'] = $currency;
			}

			$items[] = $new_item;

		}

		public function get_total() {
			return ProSites_Model_Receipt::get_items_total( $this->items );
		}

		public static function get_items_total( $items ) {
			$total = 0;

			foreach( $items as $item ) {
				$total += $item['amount'];
			}

			return $total;
		}

		public function get_items() {
			return $this->items;
		}

	}

}