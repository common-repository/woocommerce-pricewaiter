<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WC REST API OrderWrite Maniuplator
 *
 * Handles fixing taxes due to lack of foresight
 * with WC REST API order creation
 *
 * @class       WC_PriceWaiter_Rest_Api_OrderWrite
 * @version     1.0.0
 * @package     PriceWaiter/Classes
 * @category    Class
 * @author      PriceWaiter
 */
class WC_PriceWaiter_Rest_Api_OrderWrite {
	const VERSION = 1;
	public $requested = null;

	/**
	 * Constructor
	 */
	public function __construct() {

		// Setup filter to hook in REST API order creation process
		add_filter( "woocommerce_rest_pre_insert_shop_order_object", array( $this, 'orderwrite_intercept'), 10, 3 );

	}

	/**
	 * Captures REST API orderwrite requst data for later processing.
	 *
	 * @access public
	 * @since 1.0.0
	 * @param object $order WooCommerce order
	 * @param object $request WP REST API Request
	 * @param bool $creating Editing vs updating
	 * @return object
	 */
	public function orderwrite_intercept( $order, $request, $creating ) {

		// Store the original request parameters for later processing
		$this->requested = (object) $request->get_json_params();

		// Cancel emails
		$this->cancel_emails();

		// Hookup the before order object save action for final say on order data maniuplation
		add_action( 'woocommerce_before_order_object_save', array($this, 'correct_taxes_and_totals'), 10, 2 );

		return $order;
	}

	/**
	 * Corrects taxes and totals for REST API order create (Woo wants to auto calculate taxes)
	 * which definately won't line up with amounts actually charged.
	 *
	 * @access public
	 * @since 1.0.0
	 * @param object $order WooCommerce order
	 * @param object $data_store WooCommerce data store
	 * @return object $order Modified
	 */
	public function correct_taxes_and_totals($order, $data_store) {

		// Ensure it's a pricewaiter order we are messing with and we have request object.
		if ( is_object( $this->requested ) && 'pricewaiter' === $this->requested->payment_method ) {

			$sales_tax = ( !empty( $this->requested->total_tax) ) ? $this->requested->total_tax : 0;

			// Remove ALL tax line_items to prevent
			// Woo auto-calculating them
			$order->remove_order_items( 'tax' );

			// Add our exact tax line_item charged (as applicable)
			if ( $sales_tax ) {
				$item = new WC_Order_Item_Tax();
				$item->set_name( 'pw' );
				$item->set_rate( $tax_rate_id );
				$item->set_tax_total( $sales_tax );
				$item->set_shipping_tax_total( 0 );
				$order->add_item( $item );
			}

			// Reset order tax and total
			$order->set_cart_tax( $sales_tax );
			$order->set_total( $this->requested->total );

		}

		return $order;
	}

	/**
	 * Stops WC from sending certin emails.
	 *
	 * @access public
	 * @since 1.0.0
	 * @return null
	 */
	public function cancel_emails() {

		// Don't send new order emails to customers. PW Aleady does this.
		add_filter( 'woocommerce_email_enabled_customer_invoice', function( $enabled, $object ) {
			return false;
		}, 10, 2 );

		add_filter( 'woocommerce_email_enabled_customer_processing_order', function( $enabled, $object ) {
			return false;
		}, 10, 2 );

		return;
	}
}
