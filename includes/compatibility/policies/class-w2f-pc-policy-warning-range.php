<?php
/**
 * W2F_PC_Policy_Warning_Range class
 *
 * Warns when a value is near a threshold.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Warning Range Policy - warns when near threshold.
 *
 * Unlike other policies, this ALWAYS passes (doesn't block) but
 * returns a warning when value is within margin of threshold.
 *
 * Examples:
 * - Warn if PSU wattage within 10% of required
 * - Warn if GPU clearance within 5mm of max
 *
 * @class    W2F_PC_Policy_Warning_Range
 * @version  2.0.0
 */
class W2F_PC_Policy_Warning_Range extends W2F_PC_Policy_Base {

	/**
	 * Get policy type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'warning_range';
	}

	/**
	 * Get configuration fields.
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'value_key' => array(
				'type' => 'fact_select',
				'label' => __( 'Value to Check', 'w2f-pc-configurator' ),
				'description' => __( 'The value to check (e.g., psu.wattage_w).', 'w2f-pc-configurator' ),
				'required' => true,
			),
			'threshold_key' => array(
				'type' => 'fact_select',
				'label' => __( 'Threshold', 'w2f-pc-configurator' ),
				'description' => __( 'The threshold to compare against (e.g., derived.required_psu_w).', 'w2f-pc-configurator' ),
				'required' => true,
			),
			'margin_type' => array(
				'type' => 'select',
				'label' => __( 'Margin Type', 'w2f-pc-configurator' ),
				'options' => array(
					'percent' => __( 'Percentage', 'w2f-pc-configurator' ),
					'absolute' => __( 'Absolute value', 'w2f-pc-configurator' ),
				),
				'default' => 'percent',
			),
			'margin_percent' => array(
				'type' => 'number',
				'label' => __( 'Margin Percent', 'w2f-pc-configurator' ),
				'description' => __( 'Warning margin as percentage (e.g., 10 = warn if within 10%).', 'w2f-pc-configurator' ),
				'default' => 10,
				'min' => 1,
				'max' => 50,
			),
			'margin_absolute' => array(
				'type' => 'number',
				'label' => __( 'Margin Absolute', 'w2f-pc-configurator' ),
				'description' => __( 'Warning margin as absolute value.', 'w2f-pc-configurator' ),
				'default' => 5,
				'min' => 1,
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
		$value_key = $this->get_config_value( 'value_key' );
		$threshold_key = $this->get_config_value( 'threshold_key' );

		// Only applicable if both values exist.
		return $facts->has( $value_key ) && $facts->has( $threshold_key );
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
		$value_key = $this->get_config_value( 'value_key' );
		$threshold_key = $this->get_config_value( 'threshold_key' );
		$margin_type = $this->get_config_value( 'margin_type', 'percent' );
		$margin_percent = (float) $this->get_config_value( 'margin_percent', 10 );
		$margin_absolute = (float) $this->get_config_value( 'margin_absolute', 5 );

		$value = (float) $facts->get( $value_key, 0 );
		$threshold = (float) $facts->get( $threshold_key, 0 );

		// Calculate warning zone.
		if ( 'percent' === $margin_type ) {
			$warning_threshold = $threshold * ( 1 + ( $margin_percent / 100 ) );
			$margin_value = $threshold * ( $margin_percent / 100 );
		} else {
			$warning_threshold = $threshold + $margin_absolute;
			$margin_value = $margin_absolute;
		}

		// Check if value is in warning zone (between threshold and warning_threshold).
		$in_warning_zone = ( $value >= $threshold ) && ( $value <= $warning_threshold );

		// For warning_range, we "pass" if NOT in warning zone.
		// If IN warning zone, we return passed=true but with a warning message.
		// The severity is always 'warn' for this policy type.

		$show_warning = $in_warning_zone;
		$headroom = $value - $threshold;
		$headroom_percent = $threshold > 0 ? ( $headroom / $threshold ) * 100 : 0;

		return array(
			'passed' => ! $show_warning, // Pass means no warning.
			'message' => $show_warning ? $this->render_message( $facts, array(
				'value' => round( $value, 1 ),
				'threshold' => round( $threshold, 1 ),
				'headroom' => round( $headroom, 1 ),
				'headroom_percent' => round( $headroom_percent, 1 ),
				'margin' => round( $margin_value, 1 ),
			) ) : '',
			'details' => array(
				'value_key' => $value_key,
				'threshold_key' => $threshold_key,
				'value' => $value,
				'threshold' => $threshold,
				'warning_threshold' => $warning_threshold,
				'margin_type' => $margin_type,
				'margin_value' => $margin_value,
				'headroom' => $headroom,
				'headroom_percent' => $headroom_percent,
				'in_warning_zone' => $in_warning_zone,
			),
			'compared' => array(
				'left' => $value,
				'right' => $warning_threshold,
				'operator' => 'near',
			),
		);
	}

	/**
	 * Get default message.
	 *
	 * @return string
	 */
	protected function get_default_message() {
		$margin_type = $this->get_config_value( 'margin_type', 'percent' );
		$margin_percent = $this->get_config_value( 'margin_percent', 10 );
		$margin_absolute = $this->get_config_value( 'margin_absolute', 5 );

		$margin_desc = 'percent' === $margin_type
			? sprintf( '%d%%', $margin_percent )
			: sprintf( '%d units', $margin_absolute );

		return sprintf(
			__( 'Value is within %s of threshold. Consider additional headroom.', 'w2f-pc-configurator' ),
			$margin_desc
		);
	}
}
