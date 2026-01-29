<?php
/**
 * W2F_PC_Policy_Compare class
 *
 * Compares two numeric values.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compare Policy - numeric comparison.
 *
 * Examples:
 * - psu.wattage_w >= derived.required_psu_w
 * - cooler.tdp_w >= cpu.tdp_w
 *
 * @class    W2F_PC_Policy_Compare
 * @version  2.0.0
 */
class W2F_PC_Policy_Compare extends W2F_PC_Policy_Base {

	/**
	 * Get policy type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'compare';
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
				'label' => __( 'Left Value', 'w2f-pc-configurator' ),
				'description' => __( 'The left side of the comparison (e.g., psu.wattage_w).', 'w2f-pc-configurator' ),
				'required' => true,
			),
			'operator' => array(
				'type' => 'select',
				'label' => __( 'Operator', 'w2f-pc-configurator' ),
				'options' => array(
					'>=' => __( '>= (greater than or equal)', 'w2f-pc-configurator' ),
					'>' => __( '> (greater than)', 'w2f-pc-configurator' ),
					'<=' => __( '<= (less than or equal)', 'w2f-pc-configurator' ),
					'<' => __( '< (less than)', 'w2f-pc-configurator' ),
					'==' => __( '== (equal)', 'w2f-pc-configurator' ),
					'!=' => __( '!= (not equal)', 'w2f-pc-configurator' ),
				),
				'default' => '>=',
				'required' => true,
			),
			'right_key' => array(
				'type' => 'fact_select',
				'label' => __( 'Right Value', 'w2f-pc-configurator' ),
				'description' => __( 'The right side of the comparison (e.g., derived.required_psu_w).', 'w2f-pc-configurator' ),
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
		$operator = $this->get_config_value( 'operator', '>=' );

		$left_value = (float) $facts->get( $left_key, 0 );
		$right_value = (float) $facts->get( $right_key, 0 );

		$passed = $this->compare( $left_value, $operator, $right_value );

		return array(
			'passed' => $passed,
			'message' => $passed ? '' : $this->render_message( $facts ),
			'details' => array(
				'left_key' => $left_key,
				'operator' => $operator,
				'right_key' => $right_key,
				'left_value' => $left_value,
				'right_value' => $right_value,
			),
			'compared' => array(
				'left' => $left_value,
				'right' => $right_value,
				'operator' => $operator,
			),
		);
	}

	/**
	 * Perform numeric comparison.
	 *
	 * @param  float  $left     Left value.
	 * @param  string $operator Operator.
	 * @param  float  $right    Right value.
	 * @return bool
	 */
	private function compare( $left, $operator, $right ) {
		switch ( $operator ) {
			case '>=':
				return $left >= $right;
			case '>':
				return $left > $right;
			case '<=':
				return $left <= $right;
			case '<':
				return $left < $right;
			case '==':
				return abs( $left - $right ) < 0.0001;
			case '!=':
				return abs( $left - $right ) >= 0.0001;
			default:
				return false;
		}
	}

	/**
	 * Get default message.
	 *
	 * @return string
	 */
	protected function get_default_message() {
		$left_key = $this->get_config_value( 'left_key', 'value1' );
		$operator = $this->get_config_value( 'operator', '>=' );
		$right_key = $this->get_config_value( 'right_key', 'value2' );

		return sprintf(
			__( '{{%1$s}} must be %2$s {{%3$s}}.', 'w2f-pc-configurator' ),
			$left_key,
			$operator,
			$right_key
		);
	}
}
