<?php
/**
 * PriceWaiter API
 *
 * Handles WC-PRICEWAITER-API endpoint requests
 *
 * @author      Sole Graphics
 * @category    API
 * @package     WooCommerce/API
 * @since       1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_PriceWaiter_API {
	const VERSION = 1;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		// add query vars
		add_filter( 'query_vars', array( $this, 'add_query_vars'), 0 );

		// register API endpoints
		add_action( 'init', array( $this, 'add_endpoint'), 0 );

		// handle wc-pricewaiter-api endpoint requests
		add_action( 'parse_request', array( $this, 'handle_api_requests' ), 0 );
	}

	/**
	 * add_query_vars function.
	 *
	 * @access public
	 * @since 1.0
	 * @param $vars
	 * @return string[]
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'pricewaiter-api';
		$vars[] = 'pricewaiter-api-method';
		$vars[] = 'pricewaiter-api-version';

		return $vars;
	}

	/**
	 * add_endpoint function.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function add_endpoint() {
		// REST API
		add_rewrite_rule( '^pricewaiter-api/([^/]*)/?','index.php?pricewaiter-api=1&pricewaiter-api-method=$matches[1]','top');

		// WC PriceWaiter API for payment gateway IPNs, etc
		add_rewrite_endpoint( 'pricewaiter-api', EP_ALL );
	}

	/**
	 * API request - Trigger any API requests
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function handle_api_requests() {
		global $wp;

		if ( ! empty( $_GET['pricewaiter-api'] ) ) {
			$wp->query_vars['pricewaiter-api'] = $_GET['pricewaiter-api'];
		}

		if ( ! empty( $_GET['pricewaiter-api-method'] ) ) {
			$wp->query_vars['pricewaiter-api-method'] = $_GET['pricewaiter-api-method'];
		}

		// pricewaiter-api endpoint requests
		if ( ! empty( $wp->query_vars['pricewaiter-api'] ) && ! empty( $wp->query_vars['pricewaiter-api-method'] ) ) {

			// Buffer, we won't want any output here
			ob_start();

			// Get API trigger
			$method = strtolower( esc_attr( $wp->query_vars['pricewaiter-api-method'] ) );

			// Include the class
			include_once( dirname( __FILE__ ) . '/api/class-wc-pricewaiter-api-' . $method . '.php' );

			// Load class if exists
			$apiMethod = "WC_PriceWaiter_API_" . ucfirst( $method );
			if ( class_exists( $apiMethod ) )
				$api_class = new $apiMethod();

			// Trigger actions
			do_action( 'wc_pricewaiter_api_' . $method );

			// Done, clear buffer and exit
			ob_end_clean();
			die('1');
		}
	}
}
