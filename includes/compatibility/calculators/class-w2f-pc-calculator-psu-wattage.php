<?php
/**
 * W2F_PC_Calculator_PSU_Wattage class
 *
 * Calculates required PSU wattage based on component power consumption.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PSU Wattage Calculator.
 *
 * Formula: (sum(contributors) + base_w) * headroom_factor
 *
 * @class    W2F_PC_Calculator_PSU_Wattage
 * @version  2.0.0
 */
class W2F_PC_Calculator_PSU_Wattage extends W2F_PC_Calculator_Base {

	/**
	 * Get calculator name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Required PSU Wattage', 'w2f-pc-configurator' );
	}

	/**
	 * Get calculator description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Calculates the minimum PSU wattage needed based on component power consumption with a safety headroom factor.', 'w2f-pc-configurator' );
	}

	/**
	 * Get output key.
	 *
	 * @return string
	 */
	public function get_output_key() {
		return 'derived.required_psu_w';
	}

	/**
	 * Get default parameters.
	 *
	 * @return array
	 */
	public function get_default_params() {
		return array(
			'base_w' => 50,           // Base system power (motherboard, peripherals).
			'headroom_factor' => 1.25, // 25% headroom.
			'contributors' => array(   // Fact keys that contribute to power calculation.
				'cpu.tdp_w',
				'gpu.power_w',
			),
		);
	}

	/**
	 * Get parameter definitions for admin UI.
	 *
	 * @return array
	 */
	public function get_param_definitions() {
		return array(
			'base_w' => array(
				'type' => 'number',
				'label' => __( 'Base System Power (W)', 'w2f-pc-configurator' ),
				'description' => __( 'Base power consumption for motherboard, peripherals, etc.', 'w2f-pc-configurator' ),
				'default' => 50,
				'min' => 0,
				'max' => 200,
				'step' => 5,
			),
			'headroom_factor' => array(
				'type' => 'number',
				'label' => __( 'Headroom Factor', 'w2f-pc-configurator' ),
				'description' => __( 'Multiplier for safety headroom (e.g., 1.25 = 25% headroom).', 'w2f-pc-configurator' ),
				'default' => 1.25,
				'min' => 1.0,
				'max' => 2.0,
				'step' => 0.05,
			),
			'contributors' => array(
				'type' => 'multiselect',
				'label' => __( 'Power Contributors', 'w2f-pc-configurator' ),
				'description' => __( 'Select which component attributes contribute to total power.', 'w2f-pc-configurator' ),
				'options' => array(
					'cpu.tdp_w' => __( 'CPU TDP', 'w2f-pc-configurator' ),
					'gpu.power_w' => __( 'GPU Power', 'w2f-pc-configurator' ),
					'ram.power_w' => __( 'RAM Power', 'w2f-pc-configurator' ),
					'storage.power_w' => __( 'Storage Power', 'w2f-pc-configurator' ),
					'cooler.power_w' => __( 'Cooler Power', 'w2f-pc-configurator' ),
					'fans.power_w' => __( 'Fan Power', 'w2f-pc-configurator' ),
				),
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
		$total_power = 0;

		// Get parameters.
		$base_w = $this->get_param( 'base_w', 50 );
		$headroom_factor = $this->get_param( 'headroom_factor', 1.25 );
		$contributors = $this->get_param( 'contributors', array() );

		// Add base power.
		$total_power += $base_w;
		$breakdown[] = array(
			'label' => __( 'Base System Power', 'w2f-pc-configurator' ),
			'value' => $base_w,
			'unit' => 'W',
		);

		// Sum up all contributors.
		foreach ( $contributors as $fact_key ) {
			$value = $facts->get( $fact_key, 0 );

			// Handle array values (multi-select components).
			if ( is_array( $value ) ) {
				$value = array_sum( $value );
			}

			if ( $value > 0 ) {
				$total_power += $value;

				// Get human-readable label.
				$label = $this->get_contributor_label( $fact_key );
				$breakdown[] = array(
					'label' => $label,
					'value' => $value,
					'unit' => 'W',
					'source' => $fact_key,
				);
			}
		}

		// Apply headroom factor.
		$required_wattage = $total_power * $headroom_factor;

		$breakdown[] = array(
			'label' => sprintf( __( 'Subtotal (before %s%% headroom)', 'w2f-pc-configurator' ), round( ( $headroom_factor - 1 ) * 100 ) ),
			'value' => $total_power,
			'unit' => 'W',
		);

		$breakdown[] = array(
			'label' => sprintf( __( 'Headroom Factor (%s)', 'w2f-pc-configurator' ), $headroom_factor ),
			'value' => $headroom_factor,
			'unit' => 'x',
		);

		// Round up to nearest 50W for practical recommendations.
		$recommended_wattage = ceil( $required_wattage / 50 ) * 50;

		return array(
			'value' => $recommended_wattage,
			'raw_value' => $required_wattage,
			'breakdown' => $breakdown,
		);
	}

	/**
	 * Get human-readable label for a contributor fact key.
	 *
	 * @param  string $fact_key Fact key.
	 * @return string
	 */
	private function get_contributor_label( $fact_key ) {
		$labels = array(
			'cpu.tdp_w' => __( 'CPU TDP', 'w2f-pc-configurator' ),
			'gpu.power_w' => __( 'GPU Power', 'w2f-pc-configurator' ),
			'ram.power_w' => __( 'RAM Power', 'w2f-pc-configurator' ),
			'storage.power_w' => __( 'Storage Power', 'w2f-pc-configurator' ),
			'cooler.power_w' => __( 'Cooler Power', 'w2f-pc-configurator' ),
			'fans.power_w' => __( 'Fan Power', 'w2f-pc-configurator' ),
		);

		return isset( $labels[ $fact_key ] ) ? $labels[ $fact_key ] : $fact_key;
	}
}
