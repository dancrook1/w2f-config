<?php
/**
 * W2F_PC_Policy_Contains class
 *
 * Checks if a set contains a specific value.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contains Policy - checks if set contains value.
 *
 * Examples:
 * - case.supported_form_factors CONTAINS motherboard.form_factor
 * - cooler.supported_sockets CONTAINS cpu.socket
 *
 * @class    W2F_PC_Policy_Contains
 * @version  2.0.0
 */
class W2F_PC_Policy_Contains extends W2F_PC_Policy_Base {

	/**
	 * Get policy type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'contains';
	}

	/**
	 * Get configuration fields.
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'set_key' => array(
				'type' => 'fact_select',
				'label' => __( 'Set', 'w2f-pc-configurator' ),
				'description' => __( 'The set/array to check (e.g., case.supported_form_factors).', 'w2f-pc-configurator' ),
				'required' => true,
			),
			'value_key' => array(
				'type' => 'fact_select',
				'label' => __( 'Value to Find', 'w2f-pc-configurator' ),
				'description' => __( 'The value to look for in the set (e.g., motherboard.form_factor).', 'w2f-pc-configurator' ),
				'required' => true,
			),
		);
	}

	/**
	 * Check if applicable.
	 *
	 * @param  W2F_PC_Fact_Collection $facts         Facts.
	 * @param  array                  $configuration Configuration.
	 * @return bool
	 */
	public function is_applicable( $facts, $configuration ) {
		$set_key = $this->get_config_value( 'set_key' );
		$value_key = $this->get_config_value( 'value_key' );

		// Only applicable if both values exist.
		return $facts->has( $set_key ) && $facts->has( $value_key );
	}

	/**
	 * Evaluate the policy.
	 *
	 * @param  W2F_PC_Fact_Collection $facts                Facts.
	 * @param  array                  $configuration        Configuration.
	 * @param  W2F_PC_Product|null    $configurator_product Configurator.
	 * @return array
	 */
	public function evaluate( $facts, $configuration, $configurator_product = null ) {
		$set_key = $this->get_config_value( 'set_key' );
		$value_key = $this->get_config_value( 'value_key' );

		$set = $facts->get( $set_key, array() );
		$value = $facts->get( $value_key );

		// Ensure set is an array.
		if ( ! is_array( $set ) ) {
			$set = array( $set );
		}

		// Normalize all values for comparison.
		$set_normalized = array_map( function( $v ) {
			return strtolower( trim( (string) $v ) );
		}, $set );

		$value_normalized = strtolower( trim( (string) $value ) );

		$passed = in_array( $value_normalized, $set_normalized, true );

		return array(
			'passed' => $passed,
			'message' => $passed ? '' : $this->render_message( $facts, array( 'set' => implode( ', ', $set ) ) ),
			'details' => array(
				'set_key' => $set_key,
				'value_key' => $value_key,
				'set' => $set,
				'value' => $value,
			),
			'compared' => array(
				'left' => $value,
				'right' => $set,
				'operator' => 'in',
			),
		);
	}

	/**
	 * Get default message.
	 *
	 * @return string
	 */
	protected function get_default_message() {
		$value_key = $this->get_config_value( 'value_key', 'value' );

		return sprintf(
			__( '{{%s}} is not in the supported list.', 'w2f-pc-configurator' ),
			$value_key
		);
	}
}
