<?php
/**
 * PriceWaiter API IPN Class
 *
 * Handles requests to the ipn endpoint
 *
 * @author      Sole Graphics
 * @category    API
 * @package     WooCommerce/API
 * @since       1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_PriceWaiter_API_Ipn {
	/** @var string $endpoint the IPN endpoint */
	protected $endpoint   = 'https://api.pricewaiter.com/order/verify';
	protected $debug      = false;
	protected $test_order = false;

	/**
	 * Setup class
	 */
	public function __construct() {
		// Payment listener/API hook
		add_action( 'wc_pricewaiter_api_ipn', array( $this, 'check_ipn_response' ) );

		// Logging
		if ( 'yes' === wc_pricewaiter()->get_pricewaiter_setting( 'debug' ) ) {
			$this->debug = true;
			$this->log   = new WC_Logger;
		}
	}

	/**
	 * Check PriceWaiter IPN validity
	 **/
	public function check_ipn_request_is_valid( $ipn_response ) {
		// Allow custom endpoint for dev
		$this->endpoint = apply_filters( 'wc_pricewaiter_ipn_endpoint', $this->endpoint );

		if ( $this->debug ) {
			$this->log->add( 'pricewaiter-ipn', 'NEW IPN REQUEST ' . date('Y-m-d h:i:s') . ' -----------------------------------' );
			$this->log->add( 'pricewaiter-ipn', 'IPN Endpoint: ' . $this->endpoint );
			$this->log->add( "pricewaiter-ipn", "PriceWaiter Post Data: \n" . print_r( $ipn_response, true ) );
		}

		// Get received values from post data
		$validate_ipn = stripslashes_deep( $ipn_response );

		// Send back post vars to PriceWaiter
		$params = array(
			'body'        => $validate_ipn,
			'sslverify'   => false,
			'timeout'     => 60,
			'httpversion' => '1.1',
			'compress'    => false,
			'decompress'  => false,
			'user-agent'  => 'WooCommerce/' . WC()->version
		);

		// Post back to get a response
		$response = wp_remote_post( $this->endpoint, $params );

		// check to see if the request was valid
		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 && $response['body'] == '1' ) {

			return true;
		}

		if ( $this->debug ) {
			$this->log->add( "pricewaiter-ipn", "IPN Failed. WooCommerce Post Data: \n" . print_r( $params['body'], true ) );
			$this->log->add( "pricewaiter-ipn", "IPN Failed. PriceWaiter Response Data: \n" . print_r( $response, true ) );
		}

		return false;
	}

	/**
	 * Check for PriceWaiter IPN Response
	 */
	public function check_ipn_response() {
		@ob_clean();

		$ipn_response = ! empty( $_POST ) ? $_POST : false;

		// Don't even attempt to process IPN responses for WooCommerce >= 3.0
		if ( version_compare( WC()->version, 3, '>=' ) ) {

			if ( $this->debug ) {
				$this->log->add( 'pricewaiter-ipn', 'PriceWaiter IPN is not supported on WooCommerce 3.0 or greater. Please contact support@pricewaiter.com about upgrading to the REST API.' );
			}

			wp_die( "IPN Requests are not supported by WooCommerce-PriceWaiter for 3.0 and above.", "PriceWaiter IPN", array( 'response' => 410 ) );
		}

		if ( $ipn_response && $this->check_ipn_request_is_valid( $ipn_response ) ) {

			header( 'HTTP/1.1 200 OK' );

			if ( $this->debug ) {
				$this->log->add( 'pricewaiter-ipn', 'Valid IPN Request.' );
			}

			$this->successful_request( $ipn_response );

		} else {

			wp_die( "PriceWaiter IPN Request Failure", "PriceWaiter IPN", array( 'response' => 404 ) );

		}
	}

	/**
	 * Successful Payment via PriceWaiter!
	 *
	 * @param array $posted
	 */
	public function successful_request( $posted ) {
		$posted = stripslashes_deep( $posted );

		// Flag test orders
		if ( $posted['test'] && '1' === $posted['test'] ) {
			$this->test_order = true;
		}

		// Custom holds post ID
		if ( ! empty( $posted['pricewaiter_id'] ) && ! empty( $posted['api_key'] ) ) {

			// Check if this order was already created. Bail if it was
			if ( $this->order_already_exists( $posted['pricewaiter_id'] ) ) {
				if ( $this->debug ) {
					$this->log->add( 'pricewaiter-ipn', 'Order Already Exists. PriceWaiter ID#: ' . $posted['pricewaiter_id'] );
				}

				wp_die( "PriceWaiter Order Already Exists - {$posted['pricewaiter_id']}", "PriceWaiter IPN", array( 'response' => 409 ) );
			}

			// Check if the customer has an account
			$customer = get_user_by( 'email', $posted['buyer_email'] );

            // Filters to customize order totals
            //$this->setup_order_filters();

			// Create the order record
			$order_args = array(
				'status'        => ( $this->test_order ) ? 'cancelled' : 'processing',
				'customer_id'   => ( $customer === false ) ? null : $customer->ID
			);

			$order = wc_create_order($order_args);

			// Ensure order written
			if ( is_wp_error( $order ) ) {
				$this->log->add( 'pricewaiter-ipn', 'Order Creation Failed! Error: ' . $order->get_error_message() );
				exit;
			}

			// Log order creation success
			if ( $this->debug ) {
				$this->log->add( 'pricewaiter-ipn', 'Order Created. Order # ' . $order->id );
			}

			// Handle product line items
			$product = get_product( $posted['metadata__wc_post_id'] );
			$quantity = $posted['quantity'];

			// Variable Products
			$variant_attributes = array();
			if ( $product->variation_id ) {
				$variant = new WC_Product_Variation( $posted['metadata__wc_post_id'] );
				$variant_attributes = $variant->get_variation_attributes();
			}

			$product_args = array(
				'totals' => array(
					'subtotal'     => $product->get_price_excluding_tax( $quantity, $posted['unit_price'] ),
					'total'        => $product->get_price_excluding_tax( $quantity, $posted['unit_price'] ),
					'subtotal_tax' => $posted['tax'],
					'tax'          => $posted['tax'],
                    'tax_data'     => array(
                                        'total'     => array( $posted['tax'] ),
                                        'subtotal'  => array( $posted['tax'] )
                                    )
				),
				'variation' => $variant_attributes
			);

			// Remove all connected actions for adding a product
			// Prevent further down the line from adjusting pricing
			remove_all_actions( 'woocommerce_order_add_product', 1 );

			$order_item_id = $order->add_product( $product, $posted['quantity'], $product_args );

			// FORCE FEED the shipping costs
			if ($posted['shipping'] > 0) {
				$shipping_item_id = wc_add_order_item( $order->id, array(
					'order_item_name' => ( !empty( $posted['shipping_method'] ) ) ? $posted['shipping_method'] : 'Flat Rate',
					'order_item_type' => 'shipping'
				));

				wc_update_order_item_meta( $shipping_item_id, 'taxes', serialize( array() ) );
				wc_update_order_item_meta( $shipping_item_id, 'cost', $posted['shipping'] );
				wc_update_order_item_meta( $shipping_item_id, 'method_id', 'pricewaiter_fixed_shipping' );
			}

			// FORCE FEED the sales tax items
            // USE ADD TAX from abstact - function add_tax() ?
			if ($posted['tax'] > 0) {
				$tax_item_id = wc_add_order_item( $order->id, array(
					'order_item_name' => 'PRICEWAITER-ORDER-CUSTOM-TAX',
					'order_item_type' => 'tax'
				));

				wc_update_order_item_meta( $tax_item_id, 'shipping_tax_amount', 0 );
				wc_update_order_item_meta( $tax_item_id, 'tax_amount', $posted['tax'] );
				wc_update_order_item_meta( $tax_item_id, 'compound', 0 );
				wc_update_order_item_meta( $tax_item_id, 'label', 'Tax (' . $posted['buyer_shipping_state'] . ')' );
				wc_update_order_item_meta( $tax_item_id, 'rate_id', 0 );
			}

			// Create the order
			$address_billing = array(
				'first_name' => $posted['buyer_billing_first_name'],
				'last_name'  => $posted['buyer_billing_last_name'],
				//'company'    => 'WooThemes',
				'email'      => $posted['buyer_email'],
				'phone'      => $posted['buyer_billing_phone'],
				'address_1'  => $posted['buyer_billing_address'],
				'address_2'  => trim($posted['buyer_billing_address2'] . ' ' . $posted['buyer_billing_address3']),
				'city'       => $posted['buyer_billing_city'],
				'state'      => $posted['buyer_billing_state'],
				'postcode'   => $posted['buyer_billing_zip'],
				'country'    => $posted['buyer_billing_country']
			);

			$address_shipping = array(
				'first_name' => $posted['buyer_shipping_first_name'],
				'last_name'  => $posted['buyer_shipping_last_name'],
				//'company'    => 'WooThemes',
				//'email'      => $posted['buyer_email'],
				'phone'      => $posted['buyer_shipping_phone'],
				'address_1'  => $posted['buyer_shipping_address'],
				'address_2'  => trim($posted['buyer_shipping_address2'] . ' ' . $posted['buyer_shipping_address3']),
				'city'       => $posted['buyer_shipping_city'],
				'state'      => $posted['buyer_shipping_state'],
				'postcode'   => $posted['buyer_shipping_zip'],
				'country'    => $posted['buyer_shipping_country']
			);

			// Handle customer data
			$order->set_address( $address_billing, 'billing' );
			$order->set_address( $address_shipping, 'shipping' );

			// Handle totals rows
			$order->set_total( $posted['tax'], 'tax' );
            $order->set_total( 0, 'shipping_tax' );
			$order->set_total( $posted['shipping'], 'shipping' );
			$order->set_total( 0, 'cart_discount' );
			$order->set_total( 0, 'order_discount' );
			$order->set_total( $posted['total'], 'total' );

			// Custom PriceWaiter order meta
			update_post_meta( $order->id, '_wc_pricewaiter_id', $posted['pricewaiter_id'] );
			update_post_meta( $order->id, '_wc_pricewaiter_payment_method', $posted['payment_method'] );

			// Set the transaction id as the one PriceWaiter got from the processor
			update_post_meta( $order->id, '_transaction_id', $posted['transaction_id'] );

			// Set PriceWaiter as the payment method
			update_post_meta( $order->id, '_payment_method', 'PriceWaiter (' . $posted['payment_method'] . ')' );
			update_post_meta( $order->id, '_payment_method_title', 'PriceWaiter' );

			// Reduce Stock level if we're supposed to
			if ( !$this->test_order && apply_filters( 'woocommerce_payment_complete_reduce_order_stock', true, $order->id ) ) {
				$order->reduce_order_stock(); // Payment is complete so reduce stock levels
			}

			if ( $this->test_order ) {
				$order->add_order_note( 'This was a PriceWaiter "test" order.' );

				if ( $this->debug ) {
					$this->log->add( 'pricewaiter-ipn', 'Order Flagged as "TEST" and set to status of "cancelled". Order # ' . $order->id );
				}
			}

            // Delete transients
            wc_delete_shop_order_transients( $order->id );
		}

		exit;
	}

	/**
	 * Check if a PriceWaiter order exists already
	 *
	 * @param string $pricewaiter_id
	 */
	public function order_already_exists( $pricewaiter_id ) {
		$args = array(
			'post_type'      => 'shop_order',
			'post_status'    => 'any',
			'meta_key'       => '_wc_pricewaiter_id',
			'posts_per_page' => '-1',
			'meta_query'     => array(
				array(
					'key'     => '_wc_pricewaiter_id',
					'value'   => $pricewaiter_id,
					'compare' => '='
				)
			)
		);

		$my_query = new WP_Query($args);

		wp_reset_postdata();

		return ($my_query->found_posts) ? true : false;
	}
}
