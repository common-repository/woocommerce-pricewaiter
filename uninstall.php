<?php
/**
 * Uninstalling plugin
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

/**
 * Remove any options defined buy the plugin
 */
delete_option( '_wc_pricewaiter_api_user_id' );
delete_option( '_wc_pricewaiter_api_user_status' );
delete_option( '_wc_pricewaiter_sign_up_token' );
delete_option( 'woocommerce_pricewaiter_settings' );
