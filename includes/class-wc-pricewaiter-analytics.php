<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if (!class_exists( 'WC_PriceWaiter_Analytics' ) ) {

	/**
	 * PriceWaiter Google Analytics eCommerce Integration
	 *
	 * Allows tracking code to be inserted into thank you page
	 * Assumes UA or GA is already installed on the site
	 *
	 * @class   WC_PriceWaiter_Analytics
	 * @since 1.0
	 */
	class WC_PriceWaiter_Analytics {
		protected $debug      = false;

		/**
		 * Init
		 *
		 * @return void
		 */
		public function __construct() {

			// Define user set variables
			$this->ecommerce_tracking = wc_pricewaiter()->get_pricewaiter_setting( 'ecommerce_tracking' );
			$this->ecommerce_tracking_object = trim( wc_pricewaiter()->get_pricewaiter_setting( 'ecommerce_tracking_object' ) );

			// Tracking code
			add_action( 'wp_footer', array( $this, 'pw_tracking_code_display' ), 999999 );

			// Logging
			if ( 'yes' === wc_pricewaiter()->get_pricewaiter_setting( 'debug' ) ) {
				$this->debug = true;
				$this->log   = new WC_Logger;
			}

		}

		/**
		 * Display the tracking codes
		 *
		 * @return string
		 */
		public function pw_tracking_code_display() {

			// Set order from post data.
			$order = $_POST;

			// Run tracking if we can & should
			if ( $this->pw_should_track_order( $order ) ) {
				// Hack prefix our order id's for any filters or easy viewing
				$order['pricewaiter_id'] = 'pricewaiter-' . $order['pricewaiter_id'];

				echo $this->pw_get_ecommerce_tracking_code( $order );
			}
		}

		/**
		 * Logic for should track order
		 *
		 * @return bool
		 */
		protected function pw_should_track_order( $order ) {

			if ( !is_order_received_page() ) {
				return false;
			}

			if ( !$this->ecommerce_tracking ) {
				return false;
			}

			if ( $order['api_key'] !== wc_pricewaiter()->get_pricewaiter_setting( 'api_key' ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Google Analytics PriceWaiter eCommerce tracking
		 *
		 * @param array $order
		 *
		 * @return string
		 */
		protected function pw_get_ecommerce_tracking_code( $order ) {

			if ( 'ua' === $this->ecommerce_tracking ) {

				$ga_object = (!empty($this->ecommerce_tracking_object)) ? $this->ecommerce_tracking_object : 'ga';

				$code = "
	{$ga_object}('require', 'ecommerce', 'ecommerce.js');

	{$ga_object}('ecommerce:addTransaction', {
		'id': '" . esc_js( $order['pricewaiter_id'] ) . "',             // Transaction ID. Required
		'affiliation': '" . esc_js( get_bloginfo( 'name' ) ) . "',      // Affiliation or store name
		'revenue': '" . esc_js( $order['total'] ) . "',                 // Grand Total
		'shipping': '" . esc_js( $order['shipping'] ) . "',             // Shipping
		'tax': '" . esc_js( $order['tax'] ) . "',                       // Tax
		'currency': '" . esc_js( $order['currency'] ) . "'              // Currency
	});
";

				// Order item (singular purchases only)
				$code .= "
	{$ga_object}('ecommerce:addItem', {";

				$code .= "'id': '" . esc_js( $order['pricewaiter_id'] ) . "',";
				$code .= "'name': '" . esc_js( $order['product_name'] ) . "',";
				$code .= "'sku': '" . esc_js( $order['product_sku'] ) . "',";

				// Variations
				if ( isset($order['product_option_count']) ) {
					$out = array();
					for ($i = 0; $i < $order['product_option_count']; $i++) {
						$out[] = $order['product_option_name' . $i] . ':' . $order['product_option_value' . $i];
					}
					$code .= "'category': '" . esc_js( join( " / ", $out) ) . "',";
				}

				$code .= "'price': '" . esc_js( $order['unit_price'] ) . "',";
				$code .= "'quantity': '" . esc_js( $order['quantity'] ) . "'";
				$code .= "});";

				$code .= "
	{$ga_object}('ecommerce:send');      // Send transaction and item data to Google Analytics.";

			} else {

				$ga_object = (!empty($this->ecommerce_tracking_object)) ? $this->ecommerce_tracking_object : '_gaq';

				$code = "
	var {$ga_object} = {$ga_object} || [];

	{$ga_object}.push(
		['_set', 'currencyCode', '" . esc_js( $order['currency'] ) . "']
	);

	{$ga_object}.push(['_addTrans',
		'" . esc_js( $order['pricewaiter_id'] ) . "',           // order ID - required
		'" . esc_js( get_bloginfo( 'name' ) ) . "',             // affiliation or store name
		'" . esc_js( $order['total'] ) . "',                    // total - required
		'" . esc_js( $order['tax'] ) . "',                      // tax
		'" . esc_js( $order['shipping'] ) . "'                  // shipping
	]);
";

				// Order item (singular purchases only)
				$code .= "
	{$ga_object}.push(['_addItem',";

				$code .= "'" . esc_js( $order['pricewaiter_id'] ) . "',";
				$code .= "'" . esc_js( $order['product_sku'] ) . "',";
				$code .= "'" . esc_js( $order['product_name'] ) . "',";

				// Variations
				if ( isset($order['product_option_count']) ) {
					$out = array();
					for ($i = 0; $i < $order['product_option_count']; $i++) {
						$out[] = $order['product_option_name' . $i] . ':' . $order['product_option_value' . $i];
					}
					$code .= "'" . esc_js( join( " / ", $out) ) . "',";
				}

				$code .= "'" . esc_js( $order['unit_price'] ) . "',";
				$code .= "'" . esc_js( $order['quantity'] ) . "'";
				$code .= "]);";

				$code .= "
	{$ga_object}.push(['_trackTrans']); // submits transaction to the Analytics servers
";
			}

			$output = "
<!-- WooCommerce PriceWaiter Google Analytics E-Commerce Integration -->
<script type='text/javascript'>" . apply_filters( 'wc_pricewaiter_ecommerce_tracking_code', $code, $order ) . "</script>
<!-- /WooCommerce PriceWaiter Google Analytics E-Commerce Integration -->
";

			// Debug log
			if ( $this->debug ) {
				$this->log->add( "pricewaiter-analytics", "Tracking Output: \n" . $output );
				$this->log->add( "pricewaiter-analytics", "PriceWaiter Post Data: \n" . print_r( $order, true ) );
			}

			// If it was a test order, don't do anyting
			if ( isset( $order['test'] ) && '1' === $order['test'] ) {
				return '';
			}

			return $output;
		}
	}
}
