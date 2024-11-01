<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!class_exists( 'WC_PriceWaiter_Integration' ) ):
/**
* New PriceWaiter Integration
*/
class WC_PriceWaiter_Integration extends WC_Integration {
	public function __construct() {
		global $woocommerce;

		$this->id                = 'pricewaiter';
		$this->method_title      = __( 'PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN );
		$this->method_descrption = __( 'Name your price through PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN );
		// $this->cost_plugin    = array();
		$this->messages          = array();

		$this->init_form_fields();
		$this->init_settings();

		$this->api_key           = $this->get_option( 'api_key' );
		$this->setup_complete    = $this->get_option( 'setup_complete' );
		$this->debug             = $this->get_option( 'debug' );

		// integration settings hooks
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );

		// prevent from showing on integrations page or when api user notice is needed
		if( !$this->setup_complete && !( isset( $_GET['tab'] ) && 'integration' === $_GET['tab'] ) && ( !get_option( '_wc_pricewaiter_api_user_status' ) || 'ACTIVE' == get_option( '_wc_pricewaiter_api_user_status' ) ) ) {
			$notice = "<p>
				" . __( 'Don&rsquo;t lose potential customers to the competition. Complete your PriceWaiter configuration now.', WC_PriceWaiter::TEXT_DOMAIN ) . "
			</p>
			<a href=\"" . add_query_arg( array( 'tab' => 'integration', 'section' => 'pricewaiter'), admin_url( 'admin.php?page=wc-settings' ) ) . "\" class=\"button-primary\">
				" . __( 'Configure PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN ) . "
			</a>";

			wc_pricewaiter()->notice_handler->add_notice( $notice, 'update-nag', 'configure-pricewaiter' );
		}
	}

	/**
	*	Add PriceWaiter global settings to settings > 'Integration' tab
	*/
	public function init_form_fields() {
		$this->form_fields = array(
			'api_key' => array(
				'title'       => __( 'API Key', WC_PriceWaiter::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'Enter your PriceWaiter store API key.', WC_PriceWaiter::TEXT_DOMAIN ),
				'desc_tip'    => true,
				'default'     => ''
			)
		);

		$api_key = isset( $_POST[$this->plugin_id . $this->id . '_api_key'] ) ? $_POST[$this->plugin_id . $this->id . '_api_key'] : $this->get_option( 'api_key' );

		// conditionally show fields that depend on API key existing.
		if( $api_key ) {
			$this->form_fields['ecommerce_tracking']   = array(
				'title'             => __( 'eCommerce Tracking', WC_PriceWaiter::TEXT_DOMAIN ),
				'type'              => 'select',
				'options'           => array(
					''       => __( 'Disabled', WC_PriceWaiter::TEXT_DOMAIN ),
					'ua'     => __( 'Univeral Analtyics', WC_PriceWaiter::TEXT_DOMAIN ),
					'ga'     => __( 'Classic Google Analytics', WC_PriceWaiter::TEXT_DOMAIN ),
				),
				'description'       => __( 'Track PriceWaiter transactions with Google analytics eCommerce tracking. Assumes you have UA or GA already installed.', WC_PriceWaiter::TEXT_DOMAIN ),
				'desc_tip'          => true
			);
			$this->form_fields['ecommerce_tracking_object'] = array(
				'title'             => __( 'Analytics Global Object', WC_PriceWaiter::TEXT_DOMAIN ),
				'type'              => 'text',
				'label'             => __( 'Rename PriceWaiter global analytics object', WC_PriceWaiter::TEXT_DOMAIN ),
				'description'       => __( 'If any plugins or custom analytics implementaions are renaming the analytics global object, you\'ll want to rename it here.', WC_PriceWaiter::TEXT_DOMAIN ),
				'default'           => false
			);
			$this->form_fields['customize_button']     = array(
				'title'             => __( 'Customize Button', WC_PriceWaiter::TEXT_DOMAIN ),
				'type'              => 'button_link',
				'custom_attributes' => array(
					'href'   => apply_filters( 'wc_pricewaiter_account_base_url', "https://manage.pricewaiter.com" ) . "/stores/{$api_key}/button",
					'target' => "_blank"
				),
				'description'       => __( 'Customize your button by going to your PriceWaiter account &gt; Widget &gt; Button Settings.', WC_PriceWaiter::TEXT_DOMAIN ),
				'desc_tip'          => true
			);
			$this->form_fields['button_wrapper_style'] = array(
				'title'             => __( 'Button Wrapper Style', WC_PriceWaiter::TEXT_DOMAIN ),
				'type'              => 'textarea',
				'label'             => __( 'Additional Button Wrapper Styles', WC_PriceWaiter::TEXT_DOMAIN ),
				'description'       => __( 'Styles are applied as an inline style attribute to the button wrapper.', WC_PriceWaiter::TEXT_DOMAIN ),
				'default'           => "padding-top: 10px;\nclear: both;"
			);
			$this->form_fields['debug']                = array(
				'title'             => __( 'Debug Log', WC_PriceWaiter::TEXT_DOMAIN ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable Debug Log', WC_PriceWaiter::TEXT_DOMAIN ),
				'description'       => __( 'Enable logging of debug data', WC_PriceWaiter::TEXT_DOMAIN ),
				'desc_tip'          => true,
				'default'           => false
			);
		}
	}

	/**
	* Display content above the admin options fields
	*/
	public function admin_options() {
	?>
		<h2><?php echo $this->method_title; ?></h2>
		<?php WC_PriceWaiter_Integration_Helpers::load_setup_screen(); ?>
		<table class="form-table <?php if (!WC_PriceWaiter_Integration_Helpers::has_configured('wc_api_user')) : ?>wc_pricewaiter_setup_defaults<?php endif; ?>">
		<?php $this->generate_settings_html(); ?>
		</table>
		<!-- Section -->
		<div><input type="hidden" name="section" value="<?php echo $this->id; ?>" /></div>
		<?php
	}

	/*
	*	Customize appearance of button
	*/
	public function generate_button_link_html( $key, $data ) {
		$field = $this->plugin_id . $this->id . '_' . $key;
		$defaults = array(
			'class'             => 'button-secondary',
			'css'               => '',
			'custom_attributes' => array(),
			'desc_tip'          => false,
			'description'       => '',
			'title'             => ''
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row">
				<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<a class="<?php echo esc_attr( $data['class'] ); ?>" title="<?php echo esc_attr( $data['title'] ); ?>" id="<?php echo esc_attr( $field ); ?>" <?php echo $this->get_custom_attribute_html( $data ); ?>><?php echo wp_kses_post( $data['title'] ); ?></a>
					<?php echo $this->get_description_html( $data ); ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/*
	*	Sanitize Settings
	*	And validate complete setup
	*/
	public function sanitize_settings( $settings ) {
		$completed = true;
		if( isset( $settings ) ) {
			// check if required setup is completed
			foreach ($settings as $setting => $value) {
				switch ( $setting ) {
					case 'api_key':
						if( empty( $value ) ){
							$completed = false;
						}
						break;
				}
			}
		}

		// Step one from config.
		if ( !get_option( '_wc_pricewaiter_api_user_id' ) ) {
			$completed = false;
		}

		$settings['setup_complete'] = $completed;

		return $settings;
	}

	/*
	*	Validate api key is set
	*/
	public function validate_api_key_field( $key ) {
		$value = $_POST[$this->plugin_id . $this->id . '_' . $key];

		// Verify API Key
		if( isset( $value ) && strlen( $value ) == 0 ) {
			$this->errors[] = $key;
		}

		return $value;
	}

	/*
	*	Display errors by overriding display_errors();
	*/
	public function display_errors(){
		foreach ( $this->errors as $key => $value ) {
			?>
			<div class="error">
				<p><?php _e( 'Looks like you made a mistake with the '. $value . ' field. Please make sure it is valid.', WC_PriceWaiter::TEXT_DOMAIN ) ?></p>
			</div>
			<?php
		}
	}
}

endif;
