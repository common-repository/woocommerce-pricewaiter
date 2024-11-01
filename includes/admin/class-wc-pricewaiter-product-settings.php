<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_PriceWaiter_Product_Settings {
	public function __construct() {
		global $woocommerce;

		// add PriceWaiter Disable field to products under 'General' tab
		add_action( 'woocommerce_product_options_sku', array( $this, 'add_pricewaiter_fields_to_product' ) );

		// save PriceWaiter fields on products
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_pricewaiter' ), 10, 2 );

	}

	/**
	* Create checkbox on variable product in the 'General' tab
	* to toggle the PriceWaiter button on the product page
	*/
	public function add_pricewaiter_fields_to_product() {

		$wrapper_class_string = '';
		foreach ( wc_pricewaiter()->supported_product_types as $type ) {
			$wrapper_class_string .= 'show_if_' . $type . ' ';
		}
		woocommerce_wp_checkbox(
			array(
				'id'            => '_wc_pricewaiter_disabled',
				'class'         => 'checkbox',
				'wrapper_class' => $wrapper_class_string,
				'label'         => __( 'Disable PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN ),
				'description'   => __( 'Hide PriceWaiter button for this product', WC_PriceWaiter::TEXT_DOMAIN ),

			)
		);
		woocommerce_wp_checkbox(
			array(
				'id'            => '_wc_pricewaiter_conversion_tools_disabled',
				'class'         => 'checkbox',
				'wrapper_class' => $wrapper_class_string,
				'label'         => __( 'Disable Conversion Tools', WC_PriceWaiter::TEXT_DOMAIN ),
				'description'   => __( 'Turns off conversion tools set in your PriceWaiter dashboard', WC_PriceWaiter::TEXT_DOMAIN ),
			)
		);
	}

	/**
	* Save Disable PriceWaiter value for product
	*/
	public function save_product_pricewaiter( $post_id ) {
		// Update checkbox for disabling PriceWaiter button
		$checkbox_disabled = isset( $_POST['_wc_pricewaiter_disabled'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_wc_pricewaiter_disabled', $checkbox_disabled );

		// Update checkbox for enabled Conversion Tools
		$checkbox_conversion = isset( $_POST['_wc_pricewaiter_conversion_tools_disabled'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_wc_pricewaiter_conversion_tools_disabled', $checkbox_conversion );
	}

}
