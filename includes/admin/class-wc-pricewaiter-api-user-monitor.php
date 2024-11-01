<?php
/**
 * Status handler for the API user assigned to PriceWaiter
 *
 * Performs checks when editing the API user profile to make
 * sure the changes don't break the functionality of the API.
 *
 * Displays admin notice of issue and how to resolve it, then
 * temporarily disables the plugin until the issue is corrected.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_PriceWaiter_API_User_Monitor {

	public function __construct() {
		$this->api_user_status = $this->get_api_user_status();

		/**
		 * A user is being deleted. Check if it's the api user.
		 */
		add_action( 'delete_user', array( $this, 'check_if_api_user_deleted' ) );

		/**
		 * A user is being modified.
		 * Check if it's the API user and if anything important has been modified.
		 */
		add_action( 'edit_user_profile_update', array( $this, 'check_status_of_api_user' ), 50 );

		/**
		 * Create an Admin notice to state what's wrong with the user.
		 *
		 * DELETED         = Ask to go to PW setup and reassign an API user.
		 * MISSING_KEYS    = Go to PW setup and assign API user to regenerate keys.
		 * LOW_PERMISSIONS = Go to PW setup and assign API user to reset permissions.
		 */
		if ( 'ACTIVE' !== $this->api_user_status ) {
			$user_id = get_option( '_wc_pricewaiter_api_user_id' );
			$user_info = get_userdata( $user_id );

			// Hide notices on PriceWaiter settings tab
			if ( !( isset( $_GET['tab'] ) && 'integration' === $_GET['tab'] ) ) {
				switch ( $this->api_user_status ) {
					case 'DELETED':
						wc_pricewaiter()->notice_handler->add_notice(
							'<h3>PriceWaiter is temporarily disabled.</h3>
							<p><strong>The user PriceWaiter associates with the WooCommerce REST API access appears to have been removed.</strong></p>
							<p>Please grant a user WooCommerce REST API access or select a user with access in the PriceWaiter Settings to continue selling more with PriceWaiter.</p>
							<p><a href="' . add_query_arg( array( 'tab' => 'integration', 'section' => 'pricewaiter'), admin_url( 'admin.php?page=wc-settings' ) ) . '" class="button-primary">
							' . __( 'Configure PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN ) . '
							</a></p>',
							'error',
							'admin-user-deleted' );
						break;

					case 'MISSING_KEYS':
						wc_pricewaiter()->notice_handler->add_notice(
							"<h3>PriceWaiter is temporarily disabled.</h3>
							<p>The user (<strong>{$user_info->user_login}</strong>) PriceWaiter associates with the WooCommerce REST API appears to have had the API Key revoked.</strong></p>
							<p>Configure PriceWaiter to resolve this issue. Reselecting <strong>{$user_info->user_login}</strong> from the existing users option will automatically regenerate keys for that user.</p>
							<p><a href=\"" . add_query_arg( array( 'tab' => 'integration', 'section' => 'pricewaiter'), admin_url( 'admin.php?page=wc-settings' ) ) . "\" class=\"button-primary\">
							" . __( 'Configure PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN ) . "</a></p>",
							'error',
							'admin-user-missing-keys' );
						break;

					case 'LOW_PERMISSIONS':
						wc_pricewaiter()->notice_handler->add_notice(
							"<h3>PriceWaiter is temporarily disabled.</h3>
							<p>The user (<strong>{$user_info->user_login}</strong>) PriceWaiter associates with the WooCommerce REST API requires <strong>read/write</strong> permissions.</strong></p>
							<p>Configure PriceWaiter to resolve this issue. Reselecting <strong>{$user_info->user_login}</strong> from the existing users option will automatically reset permissions for that user.</p>
							<p><a href=\"" . add_query_arg( array( 'tab' => 'integration', 'section' => 'pricewaiter'), admin_url( 'admin.php?page=wc-settings' ) ) . "\" class=\"button-primary\">
							" . __( 'Configure PriceWaiter', WC_PriceWaiter::TEXT_DOMAIN ) . "</a></p>",
							'error',
							'admin-user-low-permissions' );
						break;
				}
			}
		}
	}

	/**
	 * Returns known status of the api user
	 *
	 * @return string, ACTIVE, DELETED, MISSING_KEYS, LOW_PERMISSIONS
	 */
	public static function get_api_user_status() {
		return get_option( '_wc_pricewaiter_api_user_status' );
	}

	public function check_if_api_user_deleted( $user_id ) {
		if ( $user_id == get_option( '_wc_pricewaiter_api_user_id' ) ) {
			update_option( '_wc_pricewaiter_api_user_status', 'DELETED' );
			delete_option( '_wc_pricewaiter_api_user_id' );
			WC_PriceWaiter_Integration_Helpers::update_setup_complete_option();
		}
	}

	/*
	 * Check that the proper settings still exist for the API user
	 * If anything is modified outside accepted options remove user
	 * from the PriceWaiter settings.
	 */
	public function check_status_of_api_user( $user_id ) {
		if ( $user_id == get_option( '_wc_pricewaiter_api_user_id' ) ) {
			$api_user = get_userdata( $user_id );
			$user_status = $this->get_api_user_status();
			$api_user_revalidated = false;

			/**
			 * check if user api keys are missing,
			 * or invalid permissions
			 */
			if ( !$api_user->woocommerce_api_consumer_key || !$api_user->woocommerce_api_consumer_secret ) {
				update_option( '_wc_pricewaiter_api_user_status', 'MISSING_KEYS' );
			} else if ( 'read_write' !== $api_user->woocommerce_api_key_permissions ) {
				update_option( '_wc_pricewaiter_api_user_status', 'LOW_PERMISSIONS' );
			}

			if ( "LOW_PERMISSIONS" == $user_status ) {
				if ( 'read_write' == $api_user->woocommerce_api_key_permissions ) {
					update_option( '_wc_pricewaiter_api_user_status', 'ACTIVE' );
				}
			}

			if ( "MISSING_KEYS" == $user_status ) {
				if ( $api_user->woocommerce_api_consumer_key || $api_user->woocommerce_api_consumer_secret ) {
					$api_user_revalidated = true;
				}
				if ( 'read_write' !== $api_user->woocommerce_api_key_permissions ) {
					update_option( '_wc_pricewaiter_api_user_status', 'LOW_PERMISSIONS' );
					$api_user_revalidated = false;
				}

				if ( $api_user_revalidated ) {
					update_option( '_wc_pricewaiter_api_user_status', 'ACTIVE' );
				}
			}

			WC_PriceWaiter_Integration_Helpers::update_setup_complete_option();
		}
	}
}
