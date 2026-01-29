<?php
/**
 * W2F_PC_Calculator_GPU_Clearance class
 *
 * Calculates maximum GPU length based on case specifications and adjustments.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GPU Clearance Calculator.
 *
 * Calculates maximum GPU length from case spec minus any penalties (like radiator placement).
 *
 * @class    W2F_PC_Calculator_GPU_Clearance
 * @version  2.0.0
 */
class W2F_PC_Calculator_GPU_Clearance extends W2F_PC_Calculator_Base {

	/**
	 * Get calculator name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Maximum GPU Length', 'w2f-pc-configurator' );
	}

	/**
	 * Get calculator description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Calculates the maximum GPU length based on case clearance minus any penalties from radiator placement or other factors.', 'w2f-pc-configurator' );
	}

	/**
	 * Get output key.
	 *
	 * @return string
	 */
	public function get_output_key() {
		return 'derived.max_gpu_length_mm';
	}

	/**
	 * Get default parameters.
	 *
	 * @return array
	 */
	public function get_default_params() {
		return array(
			'base_source' => 'case.max_gpu_length_mm', // Fact key for base clearance.
			'default_max_mm' => 400,                    // Default if no case selected.
			'penalty_key' => 'derived.gpu_clearance_penalty_mm', // Where adjustments store penalties.
		);
	}

	/**
	 * Get parameter definitions for admin UI.
	 *
	 * @return array
	 */
	public function get_param_definitions() {
		return array(
			'base_source' => array(
				'type' => 'select',
				'label' => __( 'Base Clearance Source', 'w2f-pc-configurator' ),
				'description' => __( 'The fact key that provides the base GPU clearance.', 'w2f-pc-configurator' ),
				'default' => 'case.max_gpu_length_mm',
				'options' => array(
					'case.max_gpu_length_mm' => __( 'Case Max GPU Length', 'w2f-pc-configurator' ),
				),
			),
			'default_max_mm' => array(
				'type' => 'number',
				'label' => __( 'Default Maximum (mm)', 'w2f-pc-configurator' ),
				'description' => __( 'Default maximum GPU length if no case is selected.', 'w2f-pc-configurator' ),
				'default' => 400,
				'min' => 200,
				'max' => 600,
				'step' => 10,
			),
			'penalty_key' => array(
				'type' => 'text',
				'label' => __( 'Penalty Fact Key', 'w2f-pc-configurator' ),
				'description' => __( 'Fact key where adjustments store clearance penalties.', 'w2f-pc-configurator' ),
				'default' => 'derived.gpu_clearance_penalty_mm',
			),
		);
	}

	/**
	 * Perform calculation.
	 *
	 * @param  W2F_PC_Fact_Collection $facts Fact collection.
	 * @return array
	 */
	public function calculate( $facts ) {
		$breakdown = array();

		// Get parameters.
		$base_source = $this->get_param( 'base_source', 'case.max_gpu_length_mm' );
		$default_max = $this->get_param( 'default_max_mm', 400 );
		$penalty_key = $this->get_param( 'penalty_key', 'derived.gpu_clearance_penalty_mm' );

		// Get base clearance from case.
		$base_clearance = $facts->get( $base_source, $default_max );

		if ( ! is_numeric( $base_clearance ) || $base_clearance <= 0 ) {
			$base_clearance = $default_max;
		}

		$breakdown[] = array(
			'label' => __( 'Case GPU Clearance', 'w2f-pc-configurator' ),
			'value' => $base_clearance,
			'unit' => 'mm',
			'source' => $facts->has( $base_source ) ? $base_source : 'default',
		);

		// Get total penalty from adjustments.
		$penalty = $facts->get( $penalty_key, 0 );

		if ( $penalty > 0 ) {
			$breakdown[] = array(
				'label' => __( 'Clearance Penalty', 'w2f-pc-configurator' ),
				'value' => -$penalty,
				'unit' => 'mm',
				'source' => $penalty_key,
			);
		}

		// Calculate final clearance.
		$max_gpu_length = $base_clearance - $penalty;

		// Ensure non-negative.
		$max_gpu_length = max( 0, $max_gpu_length );

		return array(
			'value' => $max_gpu_length,
			'base_clearance' => $base_clearance,
			'penalty' => $penalty,
			'breakdown' => $breakdown,
		);
	}
}
