<?php
/**
 * Plugin Name: PriceWaiter
 * Plugin URI: https://www.pricewaiter.com
 * Description: Name your price with PriceWaiter
 * Author: PriceWaiter
 * Text Domain: woocommerce-pricewaiter
 * Author URI: https://pricewaiter.com/
 * Version: 1.1.1
 * Requires at least: 1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

	/**
	* New PriceWaiter
	*/
	if ( !class_exists( 'WC_PriceWaiter' ) ) {
		final class WC_PriceWaiter {
			const VERSION              = "1.1.1";
			const PLUGIN_ID            = 'pw';
			const TEXT_DOMAIN          = 'woocommerce-pricewaiter';
			public $wc_minimum_version = '2.2.0'; // Minimum supported version of WooCommerce
			/**
			* Main array key is the plugins primary class.
			* The class is checked to see if plugin is active.
			*
			* Sub array is used to store meta key locations
			* 'product type' => 'post_meta key for stored cost'
			*/
			public $supported_cost_plugins = array(
				'WC_COG' => array(
					'simple'    => '_wc_cog_cost',
					'variable'  => '_wc_cog_cost_variable',
					'variation' => '_wc_cog_cost'
				)
			);
			public $cost_plugin_fields      = array();
			public $cost_plugin_installed   = false;
			public $supported_product_types = array(
				'simple',
				'variable',
				'variation'
			);
			public $pricewaiter_settings;
			protected static $_instance = null;
			public $notice_handler;
			public $api_user_monitor;

			public static function instance() {
				if ( is_null( self::$_instance  ) ) {
					self::$_instance = new self();
				}
				return self::$_instance;
			}

			public function __construct() {
				add_action( 'plugins_loaded', array( $this, 'init' ) );
			}

			public function init() {
				global $wc_minimum_version;

				// We version_compare() after plugins_loaded()
				// to make sure we have access to WC()->version
				if ( !class_exists( 'WooCommerce' ) ) {
					// WooCommerce not active
					add_action( 'admin_notices' , array( $this, 'alert_woocommerce_required' ) );
					return;
				}
				if ( version_compare( WC()->version, $this->wc_minimum_version, '<' ) ) {
					// Unsupported version
					add_action( 'admin_notices' , array( $this, 'alert_woocommerce_minimum_version' ) );
					return;
				}

				$this->pricewaiter_settings    = get_option( 'woocommerce_pricewaiter_settings' );
				$this->supported_product_types = apply_filters( 'wc_pricewaiter_supported_product_types', $this->supported_product_types );
				$this->supported_cost_plugins  = apply_filters( 'wc_pricewaiter_supported_cost_plugins', $this->supported_cost_plugins );

				// Include Required Files
				$this->includes();
				$this->setup_cost_plugin();

				// Init API
				$this->api = new WC_PriceWaiter_API();

				if ( !is_admin() && $this->get_pricewaiter_setting( 'setup_complete' ) ) {
					/**
					* Filter API product response to inject pricewaiter data
					*/
					add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'add_pricewaiter_embed' ) );
					add_filter( 'woocommerce_api_product_response', array( $this, 'wc_product_api_inject_pricewaiter' ), 10, 4 );
				} else if ( is_admin() ) {
					$this->notice_handler   = new WC_PriceWaiter_Notice_Handler();
					$this->api_user_monitor = new WC_PriceWaiter_API_User_Monitor();
					$this->product_settings = new WC_PriceWaiter_Product_Settings();
					add_filter( 'woocommerce_integrations', array( $this, 'add_pricewaiter_integration' ) );
				}



			}

			/**
			 * Include required core files used in admin and on the frontend.
			 */
			private function includes() {
				// API Class
				include_once( 'includes/class-wc-pricewaiter-api.php' );
				require_once( 'includes/class-wc-pricewaiter-product.php' );
				require_once( 'includes/class-wc-pricewaiter-embed.php' );
				require_once( 'includes/class-wc-pricewaiter-analytics.php' );

				$this->analytics = new WC_PriceWaiter_Analytics;

				// WooCommerce 3.0 Compatability
				if ( version_compare( WC()->version, 3, '>=' ) ) {

					// "PriceWaiter" payment gateway for WooCommerce 3.0.x and greater - required for REST order creation
					require_once( 'includes/class-wc-pricewaiter-gateway.php' );
					add_filter( 'woocommerce_payment_gateways', array( 'WC_PriceWaiter_Gateway_PriceWaiter', 'add_gateways' ) );

					// Muck with order taxes and totals since we can't via the REST order creation :_(
					require_once( 'includes/class-wc-pricewaiter-rest-api-orderwrite.php' );
					$this->restapi_orderwrite = new WC_PriceWaiter_Rest_Api_OrderWrite();

				}

				if ( is_admin() ) {
					$this->admin_includes();
				}
			}

			private function admin_includes() {
				require_once( 'includes/admin/class-wc-pricewaiter-notice-handler.php' );
				require_once( 'includes/admin/class-wc-pricewaiter-product-settings.php' );
				require_once( 'includes/admin/class-wc-pricewaiter-integration.php' );
				require_once( 'includes/admin/class-wc-pricewaiter-integration-helpers.php' );
				require_once( 'includes/admin/class-wc-pricewaiter-api-user-monitor.php' );
			}

			public function add_pricewaiter_integration( $integrations ) {
				$integrations[] = 'WC_PriceWaiter_Integration';
				return $integrations;
			}

			private function setup_cost_plugin() {
				/**
				* Check for any supported plugins to use for cost/margin data
				*/
				if ( isset( $this->supported_cost_plugins ) ) {
					foreach ( $this->supported_cost_plugins as $plugin => $meta_fields ) {
						if ( class_exists( $plugin ) ) {
							$this->cost_plugin_installed = true;
							$this->cost_plugin_fields = $meta_fields;
							break;
						}
					}
				}
				$this->cost_plugin_installed = apply_filters( 'wc_pricewaiter_cost_plugin_installed', $this->cost_plugin_installed );
				if ( !$this->cost_plugin_installed ) {
					// No cost plugin installed, build fallback
					include_once( 'includes/admin/class-wc-pricewaiter-costs.php' );
					$this->active_cost_plugin = new WC_PriceWaiter_Costs();
				}
			}

			public function add_pricewaiter_embed() {
				new WC_PriceWaiter_Embed();
			}

			/**
			* Inject pricewaiter data into product api response
			*/
			public function wc_product_api_inject_pricewaiter( $product_data, $product, $fields, $server ) {
				if ( !in_array( $product->product_type, $this->supported_product_types ) ) {
					$product_data['pricewaiter'] = false;
					return $product_data;
				}

				$cost_field = isset( $this->cost_plugin_fields[$product->product_type] ) ? $product->product_type : 'simple';
				$product_data['pricewaiter'] = array(
					'cost'             => get_post_meta( $product->id, $this->cost_plugin_fields[$cost_field], true ),
					'button'           => get_post_meta( $product->id, '_wc_pricewaiter_disabled', true ) == 'yes' ? false : true,
					'conversion_tools' => get_post_meta( $product->id, '_wc_pricewaiter_conversion_tools_disabled', true ) == 'yes' ? false : true
				);

				return $product_data;
			}

			/**
			* Notify Admin WooCommerce plugin is required
			*/
			function alert_woocommerce_required() {
				?>
					<div class="error">
						<h3><?php _e( 'WooCommerce required to continue.', WC_PriceWaiter::TEXT_DOMAIN ); ?></h2>
						<p><?php printf( __( 'WooCommerce v%s or higher is required to use the WooCommerce PriceWaiter plugin.', WC_PriceWaiter::TEXT_DOMAIN ) , $this->wc_minimum_version ); ?></p>
						<p><a href="http://www.woothemes.com/woocommerce/"><?php _e( 'Install WooCommerce to get started.', WC_PriceWaiter::TEXT_DOMAIN ); ?></a></p>
					</div>
				<?php
			}
			/**
			* Notify Admin to update WooCommerce to minimum version
			*/
			public function alert_woocommerce_minimum_version() {
				?>
					<div class="error">
						<h3><?php _e( 'WooCommerce update required to continue.', WC_PriceWaiter::TEXT_DOMAIN ); ?></h3>
						<p><?php printf( __( "It appears you're currently using WooCommerce v%s", WC_PriceWaiter::TEXT_DOMAIN ), WC()->version ); ?></p>
						<p><?php printf( __( "WooCommerce v%s or higher is required to use the WooCommerce PriceWaiter plugin.", WC_PriceWaiter::TEXT_DOMAIN ), $this->wc_minimum_version ); ?></p>
					</div>
				<?php
			}

			public function get_pricewaiter_setting( $key ) {
				return isset( $this->pricewaiter_settings[$key] ) ? $this->pricewaiter_settings[$key] : false;
			}
		}

		function wc_pricewaiter() {
			return WC_PriceWaiter::instance();
		}
		$GLOBALS['wc_pricewaiter'] = wc_pricewaiter();
	}

/**
* This should only fire once after activation
* intended to make sure flush_rewrite_rules fires
* just after the plugin is activated.
* The traditional activation hook fires too soon.
*/
function wc_pricewaiter_after_activation() {
	if ( get_option( 'wc_pricewaiter_flush_activation_rules_flag' ) ) {
		delete_option( 'wc_pricewaiter_flush_activation_rules_flag' );
		flush_rewrite_rules();
	}
}

function wc_pricewaiter_activated() {
	add_option( 'wc_pricewaiter_flush_activation_rules_flag', true );
}

function wc_pricewaiter_deactivated() {
	flush_rewrite_rules();
}

// Priority needs to always be after the api init action
add_action( 'init', 'wc_pricewaiter_after_activation', 20 );
register_activation_hook( __FILE__, 'wc_pricewaiter_activated' );
register_deactivation_hook( __FILE__, 'wc_pricewaiter_deactivated' );
