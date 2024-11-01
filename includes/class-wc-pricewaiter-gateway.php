<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * PriceWaiter Payment Gateway.
 *
 * Provides a PriceWaiter Payment Gateway for REST API order writes.
 * This payment gateway should remain 'disabled' for the public.
 *
 * @class 		WC_PriceWaiter_Gateway_PriceWaiter
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @author 		PriceWaiter
 */
class WC_PriceWaiter_Gateway_PriceWaiter extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'pricewaiter';
		$this->icon               = apply_filters( 'woocommerce_cheque_icon', '' );
		$this->has_fields         = false;
		$this->method_title       = _x( 'PriceWaiter', 'PriceWaiter payment method', 'woocommerce' );
		$this->method_description = __( 'Allows PriceWaiter-based order creation via the REST API. <strong>This option should always be disabled.</strong>', 'woocommerce' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions' );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

	}

	/**
	 * Add the gateways to WooCommerce
	 *
	 * @since 1.0.0
	 */
	public static function add_gateways( $methods ) {

		$methods[] = 'WC_PriceWaiter_Gateway_PriceWaiter';

		return $methods;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'pricewaiter' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PriceWaiter', 'pricewaiter' ),
				'default' => 'no'
			),
			'title' => array(
				'title'       => __( 'Title', 'pricewaiter' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'pricewaiter' ),
				'default'     => _x( 'PriceWaiter', 'PriceWaiter payment method', 'pricewaiter' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'pricewaiter' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'pricewaiter' ),
				'default'     => __( 'Processed by PriceWaiter', 'pricewaiter' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'pricewaiter' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'pricewaiter' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id
	 * @return array
	 */
	// public function process_payment( $order_id ) {

	// 	// Here's where we 'could do stuff.'
	// 	$order = wc_get_order( $order_id );

	// 	return false;
	// }
}
