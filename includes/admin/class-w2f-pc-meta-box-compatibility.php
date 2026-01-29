<?php
/**
 * W2F_PC_Meta_Box_Compatibility class
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility rules management meta box.
 *
 * @class    W2F_PC_Meta_Box_Compatibility
 * @version  1.0.0
 */
class W2F_PC_Meta_Box_Compatibility {

	/**
	 * Hook in.
	 */
	public static function init() {
		// Save compatibility rules.
		add_action( 'admin_post_w2f_pc_save_compatibility_rule', array( __CLASS__, 'save_compatibility_rule' ) );
		add_action( 'admin_post_w2f_pc_delete_compatibility_rule', array( __CLASS__, 'delete_compatibility_rule' ) );
	}


	/**
	 * Save compatibility rule.
	 */
	public static function save_compatibility_rule() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'w2f-pc-configurator' ) );
		}

		check_admin_referer( 'w2f-pc-save-compatibility-rule' );

		$compatibility_manager = W2F_PC_Compatibility_Manager::instance();
		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( $_POST['rule_id'] ) : uniqid( 'rule_' );

		// Process conditions - handle numeric values and component IDs properly.
		$conditions = array();
		if ( isset( $_POST['rule_conditions'] ) && is_array( $_POST['rule_conditions'] ) ) {
			foreach ( $_POST['rule_conditions'] as $key => $value ) {
				// Handle numeric values (for numeric_attribute rules).
				if ( in_array( $key, array( 'value_a', 'value_b' ), true ) ) {
					$conditions[ $key ] = floatval( $value );
				} else {
					$conditions[ $key ] = sanitize_text_field( $value );
				}
			}
		}

		$rule_data = array(
			'name'      => isset( $_POST['rule_name'] ) ? sanitize_text_field( $_POST['rule_name'] ) : '',
			'type'      => isset( $_POST['rule_type'] ) ? sanitize_text_field( $_POST['rule_type'] ) : 'product_match',
			'action'    => isset( $_POST['rule_action'] ) ? sanitize_text_field( $_POST['rule_action'] ) : 'require',
			'message'   => isset( $_POST['rule_message'] ) ? sanitize_text_field( $_POST['rule_message'] ) : '',
			'is_active' => isset( $_POST['rule_is_active'] ) ? 'yes' : 'no',
			'conditions' => $conditions,
		);

		$compatibility_manager->save_rule( $rule_id, $rule_data );

		wp_safe_redirect( admin_url( 'admin.php?page=w2f-pc-compatibility&saved=1' ) );
		exit;
	}

	/**
	 * Delete compatibility rule.
	 */
	public static function delete_compatibility_rule() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'w2f-pc-configurator' ) );
		}

		check_admin_referer( 'w2f-pc-delete-compatibility-rule' );

		$rule_id = isset( $_GET['rule_id'] ) ? sanitize_text_field( $_GET['rule_id'] ) : '';
		if ( $rule_id ) {
			$compatibility_manager = W2F_PC_Compatibility_Manager::instance();
			$compatibility_manager->delete_rule( $rule_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=w2f-pc-compatibility&deleted=1' ) );
		exit;
	}
}

