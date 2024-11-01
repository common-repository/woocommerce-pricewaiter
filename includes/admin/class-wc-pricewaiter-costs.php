<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_PriceWaiter_Costs {

	public function __construct() {
		/*
		* Default PriceWaiter cost fields
		*/
		wc_pricewaiter()->cost_plugin_fields = array(
			'simple'    => '_wc_cog_cost',
			'variable'  => '_wc_cog_cost_variable',
			'variation' => '_wc_cog_cost'
		);

		wc_pricewaiter()->cost_plugin_fields = apply_filters( 'wc_pricewaiter_default_cost_pluign_fields', wc_pricewaiter()->cost_plugin_fields );

		/**
		* Add cost text box to products
		*/
		add_action( 'woocommerce_product_options_pricing', array( $this, 'add_cost_field_to_simple_product' ) );
		add_action( 'woocommerce_product_options_sku', array( $this, 'add_cost_field_to_variable_product' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_cost_field_to_product_variation' ), 15, 3 );
		/**
		* Save cost text boxes on products
		*/
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_simple_product_cost' ) );
		add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_variable_product_cost' ) );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_product_variation_cost' ) );
	}

	/**
	* PriceWaiter 'cost' fields emulate Cost Of Goods meta fields for ease
	* of transition to that specific plugin.
	*/

	/**
	* Add Cost field to simple product's 'General' tab
	*/
	public function add_cost_field_to_simple_product() {

		$wrapper_class_string = '';

		foreach ( wc_pricewaiter()->supported_product_types as $type ) {
			$wrapper_class_string .= 'show_if_' . $type . ' ';
		}

		woocommerce_wp_text_input(
			array(
				'id'            => '_wc_cog_cost',
				'class'         => 'wc_input_price short',
				'wrapper_class' => $wrapper_class_string,
				'label'         => sprintf( __( 'Cost of Good (%s)', WC_PriceWaiter::TEXT_DOMAIN ), get_woocommerce_currency_symbol() ),
				'data_type'     => 'price',
			)
		);
	}

	/**
	* Add Cost field to variable product's 'General' tab
	*/
	public function add_cost_field_to_variable_product() {
		woocommerce_wp_text_input(
			array(
				'id'            => '_wc_cog_cost_variable',
				'class'         => 'wc_input_price short',
				'wrapper_class' => 'show_if_variable',
				'label'         => sprintf( __( 'Cost of Good (%s)', WC_PriceWaiter::TEXT_DOMAIN ), get_woocommerce_currency_symbol() ),
				'data_type'     => 'price',
				'desc_tip'      => true,
				'description'   => __( 'Default cost for product variations', WC_PriceWaiter::TEXT_DOMAIN ),
			)
		);
	}

	/**
	* Add cost field to product variations under 'variations' tab for each.
	*/
	public function add_cost_field_to_product_variation( $loop, $variation_data, $variation ) {
		$default_cost = get_post_meta( $variation->post_parent, '_wc_cog_cost_variable', true );
		$cost = ( isset( $variation_data['_wc_cog_cost'][0] ) ) ? $variation_data['_wc_cog_cost'][0] : '';

		if ( isset( $variation_data['_wc_cog_default_cost'][0] ) && $variation_data['_wc_cog_default_cost'][0] == 'yes' ) {
			$cost = '';
		}

		?>
			<tr>
				<td>
					<label><?php  printf( __( 'Cost of Good (%s)', WC_PriceWaiter::TEXT_DOMAIN ), esc_html( get_woocommerce_currency_symbol() ) ); ?></label>
					<input type="text" size="6" name="variable_cost_of_good[<?php echo esc_attr( $loop ); ?>]" value="<?php echo esc_attr( $cost ); ?>" class="wc_input_price" placeholder="<?php echo esc_attr( $default_cost ); ?>" >
				</td>
				<td>&nbsp;</td>
			</tr>

		<?php
	}

	/**
	* Save custom cost data.
	*/
	public function save_simple_product_cost( $post_id ) {
		$product_type = empty( $_POST['product-type'] ) ? 'simple' : sanitize_title( stripslashes( $_POST['product-type'] ) );

		if ( $product_type !== 'variable' ) {
			update_post_meta( $post_id, '_wc_cog_cost', stripcslashes( $_POST['_wc_cog_cost'] ) );
		}
	}

	public function save_variable_product_cost( $post_id ) {
		$default_cost = stripcslashes( $_POST['_wc_cog_cost_variable'] );
		update_post_meta( $post_id, '_wc_cog_cost_variable', $default_cost );
	}

	public function save_product_variation_cost( $variation_id ) {
		$default_cost = stripcslashes( $_POST['_wc_cog_cost_variable'] );

		if ( ($i = array_search( $variation_id, $_POST['variable_post_id'] ) ) !== false ) {
			$cost = $_POST['variable_cost_of_good'][$i];

			if ( $cost !== '' ) {
				update_post_meta( $variation_id, '_wc_cog_cost', $cost );
				update_post_meta( $variation_id, '_wc_cog_default_cost', 'no' );
			} else {
				if( $default_cost ) {
					update_post_meta( $variation_id, '_wc_cog_cost', $default_cost );
					update_post_meta( $variation_id, '_wc_cog_default_cost', 'yes' );
				}else{
					update_post_meta( $variation_id, '_wc_cog_cost', '' );
					update_post_meta( $variation_id, '_wc_cog_default_cost', 'no' );
				}
			}
		}
	}
}
