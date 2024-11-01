<?php
/**
 * PriceWaiter Notice Handler
 *
 *
 *
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class WC_PriceWaiter_Notice_Handler {
	private $admin_notices = array();

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'echo_admin_notices' ) );
	}

	public function add_notice( $message, $type, $id = '' ) {
		$this->admin_notices[$id] = array(
			'id'      => $id,
			'type'    => $type,
			'message' => $message
		);
	}

	public function echo_admin_notices() {
		$notice_buffer = '';
		foreach ($this->admin_notices as $id => $data) {
			$notice_buffer .= '<div id="' . $data['id'] . '" class="' . $data['type'] . '">';
			$notice_buffer .= $data['message'];
			$notice_buffer .= '</div>';
		}

		echo $notice_buffer;
	}
}
