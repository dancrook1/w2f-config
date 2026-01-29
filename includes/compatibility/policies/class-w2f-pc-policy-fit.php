<?php
/**
 * W2F_PC_Policy_Fit class
 *
 * Checks if a candidate value fits within a constraint.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fit Policy - checks if value fits within constraint.
 *
 * Specialized version of Compare for physical fit checks.
 *
 * Examples:
 * - gpu.length_mm <= derived.max_gpu_length_mm
 * - cooler.height_mm <= case.max_cooler_height_mm
 *
 * @class    W2F_PC_Policy_Fit
 * @version  2.0.0
 */
class W2F_PC_Policy_Fit extends W2F_PC_Policy_Base {

	/**
	 * Get policy type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'fit';
	}

	/**
	 * Get configuration fields.
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'candidate_key' => array(
				'type' => 'fact_select',
				'label' => __( 'Candidate Size', 'w2f-pc-configurator' ),
				'description' => __( 'The size/dimension of the component (e.g., gpu.length_mm).', 'w2f-pc-configurator' ),
				'required' => true,
			),
			'constraint_key' => array(
				'type' => 'fact_select',
				'label' => __( 'Maximum Allowed', 'w2f-pc-configurator' ),
				'description' => __( 'The constraint/maximum allowed (e.g., derived.max_gpu_length_mm).', 'w2f-pc-configurator' ),
				'required' => true,
			),
			'tolerance_mm' => array(
				'type' => 'number',
				'label' => __( 'Tolerance (mm)', 'w2f-pc-configurator' ),
				'description' => __( 'Optional tolerance for close fits.', 'w2f-pc-configurator' ),
				'default' => 0,
				'min' => 0,
				'max' => 20,
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
		$candidate_key = $this->get_config_value( 'candidate_key' );
		$constraint_key = $this->get_config_value( 'constraint_key' );

		// Only applicable if both values exist.
		return $facts->has( $candidate_key ) && $facts->has( $constraint_key );
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
		$candidate_key = $this->get_config_value( 'candidate_key' );
		$constraint_key = $this->get_config_value( 'constraint_key' );
		$tolerance = (float) $this->get_config_value( 'tolerance_mm', 0 );

		$candidate_value = (float) $facts->get( $candidate_key, 0 );
		$constraint_value = (float) $facts->get( $constraint_key, PHP_INT_MAX );

		// Component fits if candidate <= constraint + tolerance.
		$passed = $candidate_value <= ( $constraint_value + $tolerance );

		// Calculate how much it exceeds by (for message).
		$excess = $candidate_value - $constraint_value;

		return array(
			'passed' => $passed,
			'message' => $passed ? '' : $this->render_message( $facts, array( 'excess' => round( $excess, 1 ) ) ),
			'details' => array(
				'candidate_key' => $candidate_key,
				'constraint_key' => $constraint_key,
				'candidate_value' => $candidate_value,
				'constraint_value' => $constraint_value,
				'tolerance' => $tolerance,
				'excess' => $excess,
			),
			'compared' => array(
				'left' => $candidate_value,
				'right' => $constraint_value,
				'operator' => '<=',
			),
		);
	}

	/**
	 * Get default message.
	 *
	 * @return string
	 */
	protected function get_default_message() {
		$candidate_key = $this->get_config_value( 'candidate_key', 'size' );
		$constraint_key = $this->get_config_value( 'constraint_key', 'max' );

		return sprintf(
			__( 'Component ({{%1$s}}mm) does not fit (max {{%2$s}}mm).', 'w2f-pc-configurator' ),
			$candidate_key,
			$constraint_key
		);
	}
}
