<?php
/**
 * W2F_PC_Policy_Match class
 *
 * Checks if two enum values are equal.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Match Policy - checks enum equality.
 *
 * Examples:
 * - CPU socket MATCH motherboard socket
 * - RAM type MATCH motherboard RAM type
 *
 * @class    W2F_PC_Policy_Match
 * @version  2.0.0
 */
class W2F_PC_Policy_Match extends W2F_PC_Policy_Base {

	/**
	 * Get policy type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'match';
	}

	/**
	 * Get configuration fields.
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'left_key' => array(
				'type' => 'fact_select',
				'label' => __( 'First Value', 'w2f-pc-configurator' ),
				'description' => __( 'The first value to compare (e.g., cpu.socket).', 'w2f-pc-configurator' ),
				'required' => true,
			),
			'right_key' => array(
				'type' => 'fact_select',
				'label' => __( 'Second Value', 'w2f-pc-configurator' ),
				'description' => __( 'The second value to compare (e.g., motherboard.socket).', 'w2f-pc-configurator' ),
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
		$left_key = $this->get_config_value( 'left_key' );
		$right_key = $this->get_config_value( 'right_key' );

		// Only applicable if both values exist.
		return $facts->has( $left_key ) && $facts->has( $right_key );
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
		$left_key = $this->get_config_value( 'left_key' );
		$right_key = $this->get_config_value( 'right_key' );

		$left_value = $facts->get( $left_key );
		$right_value = $facts->get( $right_key );

		// Normalize for comparison.
		$left_normalized = $this->normalize_value( $left_value );
		$right_normalized = $this->normalize_value( $right_value );

		$passed = $left_normalized === $right_normalized;

		return array(
			'passed' => $passed,
			'message' => $passed ? '' : $this->render_message( $facts ),
			'details' => array(
				'left_key' => $left_key,
				'right_key' => $right_key,
				'left_value' => $left_value,
				'right_value' => $right_value,
			),
			'compared' => array(
				'left' => $left_value,
				'right' => $right_value,
				'operator' => '=',
			),
		);
	}

	/**
	 * Normalize a value for comparison.
	 *
	 * @param  mixed $value Value.
	 * @return string
	 */
	private function normalize_value( $value ) {
		if ( is_array( $value ) ) {
			// For arrays, take first value.
			$value = reset( $value );
		}

		return strtolower( trim( (string) $value ) );
	}

	/**
	 * Get default message.
	 *
	 * @return string
	 */
	protected function get_default_message() {
		$left_key = $this->get_config_value( 'left_key', 'value1' );
		$right_key = $this->get_config_value( 'right_key', 'value2' );

		return sprintf(
			__( '%s does not match %s.', 'w2f-pc-configurator' ),
			'{{' . $left_key . '}}',
			'{{' . $right_key . '}}'
		);
	}
}
