<?php
/**
 * PriceWaiter Admin Helper functions
 *
 * Facilitiate the easiest possible PriceWaiter Signup
 *
 * @author      Sole Graphics
 * @category    Class
 * @package     WooCommerce/Classes
 * @since       1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( 'WC_PriceWaiter_Integration_Helpers' ) ):

	/**
	* Admin PriceWaiter Integration Helpers
	*/
	class WC_PriceWaiter_Integration_Helpers {

		public static $error_messages = array();

		public function __construct() {

			add_action( 'wp_ajax_call_create_api_user_ajax', array( $this, 'create_api_user_ajax' ) );

		}

		/**
		 * Get link to allow user to sign up for PriceWaiter
		 *
		 * @param string sign up url
		 * @param array key value pairs of optional url params
		 * @return string sign up url
		 */
		public static function get_sign_up_url( $base_url, $additional_params = array() ) {
			if ( !$base_url ) {
				$base_url = apply_filters( 'wc_pricewaiter_default_sign_up_base_url', 'https://manage.pricewaiter.com/sign-up' );
			}

			$token = self::get_sign_up_token();

			if ( $token ) {
				$base_url = add_query_arg( array( 'token' => $token ), $base_url );
			}

			$utm_params = array(
				'utm_campaign' => 'signup',
				'utm_source'   => 'woocommerce',
				'utm_medium'   => 'integrations',
				'utm_content'  => site_url(),
				'utm_term'     => ''
			);

			$params = array_merge( $utm_params, $additional_params );

			return add_query_arg( $params, $base_url );
		}

		/**
		 * Get token if it's set, or requests one.
		 *
		 * @return string PriceWaiter sign up token
		 */
		public static function get_sign_up_token() {
			// get wc_pricewaiter_sign_up_token option
			$token = get_option( '_wc_pricewaiter_sign_up_token' );
			if ( $token && !empty( $token ) ) {
				return $token;
			}

			// if empty or not set, request a token
			return self::_request_sign_up_token();
		}

		/**
		 * Sends post request to PriceWaiter with basic store data
		 * to pre-configure a new account.
		 *
		 * @return string newly generated token
		 */
		private static function _request_sign_up_token() {
			$sign_up_api_endpoint = apply_filters( 'wc_pricewaiter_api_sign_up_url', 'https://api.pricewaiter.com/store-signups' );

			$response = wp_remote_post( $sign_up_api_endpoint, array(
				'body'         => self::_get_store_data(),
				'content-type' => 'Content-type: application/x-www-form-urlencoded'
			) );

			$response_body = json_decode( $response['body'] );
			if ( !isset( $response_body->body->token ) ) {
				// no token returned
				return false;
			}

			$token = $response_body->body->token;

			update_option( '_wc_pricewaiter_sign_up_token', $token );

			return $token;
		}

		/**
		 * Build required PriceWaiter store sign up data
		 *
		 * @param array additional arguments to pass to PriceWaiter API
		 * @return array key value pairs to pass to PriceWaiter
		 */
		private static function _get_store_data( $additional_args = array() ) {
			$user     = wp_get_current_user();
			$api_user = get_user_by( 'id', get_option( '_wc_pricewaiter_api_user_id' ) );
			$locale   = new WC_Countries;
			$paypal = new WC_Gateway_Paypal;

			$user_fullname = trim( $user->user_firstname . ' ' . $user->user_lastname );

			if ( !$api_user ) {
				$api_user = new stdClass;
				$api_user->woocommerce_api_consumer_key    = '';
				$api_user->woocommerce_api_consuper_secret = '';
			}

			$full_shipping_countries = $locale->get_shipping_countries();
			$shipping_countries = array();

			foreach ( $full_shipping_countries as $isocode => $countryname ) {
				$shipping_countries[] = $isocode;
			}
			$shipping_country_string = join( ', ', $shipping_countries );

			$store_args = array(
				'platform'                        => 'woocommerce',
				'woo_api_key'                     => $api_user->woocommerce_api_consumer_key,
				'woo_api_secret'                  => $api_user->woocommerce_api_consumer_secret,
				'woo_api_endpoint'                => get_woocommerce_api_url( '' ),
				
				'admin_email'                     => $user->user_email,
				'admin_first_name'                => $user->user_firstname,
				'admin_last_name'                 => $user->user_lastname,
				
				'store_name'                      => get_bloginfo( 'name' ),
				'store_url'                       => get_home_url( null, '/' ),
				'store_country'                   => $locale->get_base_country(),
				'store_state'                     => $locale->get_base_state(),
				'store_city'                      => $locale->get_base_city(),
				'store_zip'                       => $locale->get_base_postcode(),
				'store_shipping_countries'        => $shipping_country_string,
				'store_currency'                  => get_woocommerce_currency(),
				'store_paypal_email'              => $paypal->receiver_email,
				'store_order_callback_url'        => get_home_url( null, '/pricewaiter-api/ipn' ),
				'store_checkout_redirect_method'  => 'POST',
				'store_checkout_redirect_enabled' => 1,
				'store_checkout_redirect_url'     => wc_get_endpoint_url( 'order-received', '', get_permalink( wc_get_page_id( 'checkout' ) ) ) . '?utm_nooverride=1',
				'store_twitter_handle'            => ''
			);

			return array_merge( $store_args, $additional_args );
		}

		/**
		 * Get wp user with login, id or email
		 *
		 * @access private
		 * @param string $handle wordpress user id, login or email
		 * @return mixed false or user stdClass object
		 */
		private function get_wp_user( $handle ) {

			$key = 'login';
			if ( is_numeric( $handle ) ) {
				$key = 'id';
			} elseif ( strstr( $handle, '@' ) ) {
				$key = 'email';
			}

			$user = get_user_by( $key, $handle );

			return $user;

		}

		/**
		 * Get or create the user account for woo REST API use.
		 *
		 * @access private
		 * @param string $login wordpress user id, login or email
		 * @param string $email email address for the new user
		 * @return int user id
		 */
		private function create_wp_administrator( $login = null, $email = null ) {

			// Check that user doesn't exist already
			if ( $user = self::get_wp_user( $login ) ) {
				return $user->ID;
			}

			// Create user
			$pw_user_data = array(
				'user_login'  => $login,
				'user_pass'   => hash( 'md5', $login . date( 'U' ) . mt_rand() ),
				'user_email'  => $email,
				'first_name'  => 'PriceWaiter',
				'last_name'   => '( API User )',
				'role'        => 'administrator',
				'description' => 'This is an "API" user that is needed for PriceWaiter to work with WooCommerce.'
			);

			$user_id = wp_insert_user( $pw_user_data ) ;

			if ( is_wp_error( $user_id ) ) {
				self::$error_messages[] = $user_id->get_error_message();
				return false;
			} else {
				return $user_id;
			}
		}

		/**
		 * Check if Woo REST API is disabled, enable if so.
		 *
		 * @access private
		 * @return mixed
		 */
		private function enable_wc_rest_api() {

			// Ensure the WC REST API is enabled
			if ( false !== get_option( 'woocommerce_api_enabled' ) || 'no' === get_option( 'woocommerce_api_enabled' ) ) {
				return update_option( 'woocommerce_api_enabled', 'yes' );
			}

			return true;

		}

		/**
		 * Grant Woo read_write REST API access to a user
		 *
		 * @access private
		 * @param int $user_id a valid wp admin user id
		 * @return bool
		 */
		private function grant_wc_api_access_to_user( $user_id ) {

			// Use WC Logic for generating API keys & permissions
			// WC_Admin_Profile::generate_api_key( $user_id );

			// Assign API key, secret and permissions for the given user.
			if ( !get_user_meta( $user_id, 'woocommerce_api_consumer_key', true ) ) {
				$consumer_key = 'ck_' . hash( 'md5', $user_id . date( 'U' ) . mt_rand() );
				update_user_meta( $user_id, 'woocommerce_api_consumer_key', $consumer_key );
			}

			if ( !get_user_meta( $user_id, 'woocommerce_api_consumer_secret', true ) ) {
				$consumer_secret = 'cs_' . hash( 'md5', $user_id . date( 'U' ) . mt_rand() );
				update_user_meta( $user_id, 'woocommerce_api_consumer_secret', $consumer_secret );
			}

			update_user_meta( $user_id, 'woocommerce_api_key_permissions', 'read_write' );

			// Flag our api user
			update_option( '_wc_pricewaiter_api_user_id', $user_id );
			update_option( '_wc_pricewaiter_api_user_status', 'ACTIVE' );

			self::update_setup_complete_option();

			return true;

		}

		/**
		 * Check if PriceWaiter is fully configured
		 *
		 * @access public
		 * @return bool
		 */
		public static function is_pw_configured() {
			$checks = array(
				'wc_api_user',
				'pw_api_key'
			);

			foreach ( $checks as $check ) {
				if ( !self::has_configured( $check ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Check if specific PriceWaiter config option is set
		 *
		 * @access public
		 * @return bool
		 */
		public static function has_configured( $key ) {

			switch ( $key ) {
				case "wc_api_user":
					if ( get_option( '_wc_pricewaiter_api_user_id' ) ) {
						return get_option( '_wc_pricewaiter_api_user_status' ) == "ACTIVE" ? true : false;
					} else {
						return false;
					}
				break;

				case "pw_api_key":
					/**
					 * Check if posting the api_key since this check is sometimes fired before
					 * the api_key post data is actually saved as an option.
					 */
					$is_being_configured = isset( $_POST['woocommerce_pricewaiter_api_key'] ) && 0 !== strlen( $_POST['woocommerce_pricewaiter_api_key'] );
					$is_already_set = WC_PriceWaiter()->get_pricewaiter_setting( 'api_key' ) ? true : false;
					return $is_being_configured || $is_already_set ? true : false;
				break;

				default:
					return false;
			}
		}

		/**
		 * Performs a validation check and flags the setup_complete option.
		 * Calling this function when making changes to required settings will
		 * automatically detect and flag the setup_complete option as needed.
		 */
		public static function update_setup_complete_option() {
			$setup_complete = true;
			$pw_settings = get_option( 'woocommerce_pricewaiter_settings' );

			if ( !isset( $pw_settings['api_key'] ) ) {
				$setup_complete = false;
			}

			if ( get_option( '_wc_pricewaiter_api_user_id' ) ) {
				if ( 'ACTIVE' !== get_option( '_wc_pricewaiter_api_user_status' ) ) {
					$setup_complete = false;
				}
			} else {
				$setup_complete = false;
			}

			$pw_settings['setup_complete'] = $setup_complete;

			update_option( 'woocommerce_pricewaiter_settings', $pw_settings );
		}

		public static function load_setup_screen() {

			// Integrations screen styles
			wp_enqueue_style( 'wc-pricewaiter-admin', plugins_url( '/woocommerce-pricewaiter/assets/css/admin.css' ), null, WC_PriceWaiter::VERSION );

			if ( !self::has_configured( 'wc_api_user' ) ) {

				// Our js callbacks
				wp_enqueue_script( 'wc-pricewaiter-integration', plugins_url( '/woocommerce-pricewaiter/assets/js/admin/integration.js' ), array( 'jquery' ), WC_PriceWaiter::VERSION, true );

				// Get administrator list for <select>
				$admin_users = get_users( array(
					'blog_id'     => $GLOBALS['blog_id'],
					'role'        => 'administrator',
					'orderby'     => 'login',
					'order'       => 'ASC',
					'number'      => 100,
					'count_total' => false,
					'fields'      => 'all'
				) );
			}

			if ( !self::is_pw_configured() ) {
				require_once( 'templates/integration_configure.php' );
			}
		}

		/**
		 * Admin ajax callback handler for Woo REST API setup
		 *
		 * @access public
		 * @return mixed
		 */
		public function create_api_user_ajax() {

			$posted = $_POST;
			$response = array(
				'posted' => $posted
			);

			// Reset Error Messages
			self::$error_messages = array();

			// Turn on the REST API
			self::enable_wc_rest_api();

			// Are we creating a new user or granting API access to exsiting
			if ( !empty( $posted['id'] ) && is_numeric( $posted['id'] ) ) {
				$admin_id = $posted['id'];
			} elseif ( !empty( $posted['login'] ) && !empty( $posted['email'] ) ) {
				$admin_id = self::create_wp_administrator( $posted['login'], $posted['email'] );
			}

			if ( is_numeric( $admin_id ) ) {
				self::grant_wc_api_access_to_user( $admin_id );
				wp_send_json_success( $response );
			} else {
				$response['errors'] = implode( '<br>', self::$error_messages );
				wp_send_json_error( $response );
			}
		}
	}

	return new WC_PriceWaiter_Integration_Helpers();

endif;
