<?php
/**
 * W2F_PC_Policy_Requires_If class
 *
 * Requires a component if another is selected.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requires If Policy - conditional dependency.
 *
 * Examples:
 * - If GPU selected -> PSU must be selected
 * - If storage selected -> case must have drive bays
 *
 * @class    W2F_PC_Policy_Requires_If
 * @version  2.0.0
 */
class W2F_PC_Policy_Requires_If extends W2F_PC_Policy_Base {

	/**
	 * Get policy type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'requires_if';
	}

	/**
	 * Get configuration fields.
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'if_component' => array(
				'type' => 'component_select',
				'label' => __( 'If Component', 'w2f-pc-configurator' ),
				'description' => __( 'The component that triggers the requirement.', 'w2f-pc-configurator' ),
				'required' => true,
			),
			'if_condition' => array(
				'type' => 'select',
				'label' => __( 'Condition', 'w2f-pc-configurator' ),
				'options' => array(
					'selected' => __( 'Is selected', 'w2f-pc-configurator' ),
					'has_value' => __( 'Has specific value', 'w2f-pc-configurator' ),
				),
				'default' => 'selected',
			),
			'if_attribute' => array(
				'type' => 'text',
				'label' => __( 'Attribute (for has_value)', 'w2f-pc-configurator' ),
				'description' => __( 'Attribute key to check (e.g., type).', 'w2f-pc-configurator' ),
				'required' => false,
			),
			'if_value' => array(
				'type' => 'text',
				'label' => __( 'Value (for has_value)', 'w2f-pc-configurator' ),
				'description' => __( 'Value that triggers the requirement.', 'w2f-pc-configurator' ),
				'required' => false,
			),
			'then_component' => array(
				'type' => 'component_select',
				'label' => __( 'Then Require Component', 'w2f-pc-configurator' ),
				'description' => __( 'The component that must be selected.', 'w2f-pc-configurator' ),
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
		$if_component = $this->get_config_value( 'if_component' );
		$if_condition = $this->get_config_value( 'if_condition', 'selected' );
		$if_attribute = $this->get_config_value( 'if_attribute', '' );
		$if_value = $this->get_config_value( 'if_value', '' );

		// Check if the IF condition is met.
		if ( ! $facts->has_component( $if_component ) ) {
			return false; // Trigger component not selected - rule doesn't apply.
		}

		if ( 'has_value' === $if_condition && ! empty( $if_attribute ) ) {
			$fact_key = $if_component . '.' . $if_attribute;
			$actual_value = $facts->get( $fact_key, '' );

			if ( strtolower( trim( (string) $actual_value ) ) !== strtolower( trim( $if_value ) ) ) {
				return false; // Value doesn't match - rule doesn't apply.
			}
		}

		return true; // IF condition is met, rule applies.
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
		$if_component = $this->get_config_value( 'if_component' );
		$then_component = $this->get_config_value( 'then_component' );

		// Check if required component is selected.
		$passed = $facts->has_component( $then_component );

		return array(
			'passed' => $passed,
			'message' => $passed ? '' : $this->render_message( $facts, array(
				'if_component' => $if_component,
				'then_component' => $then_component,
			) ),
			'details' => array(
				'if_component' => $if_component,
				'if_selected' => $facts->has_component( $if_component ),
				'then_component' => $then_component,
				'then_selected' => $facts->has_component( $then_component ),
			),
			'compared' => array(
				'left' => $if_component . ' selected',
				'right' => $then_component . ' required',
				'operator' => 'requires',
			),
		);
	}

	/**
	 * Get default message.
	 *
	 * @return string
	 */
	protected function get_default_message() {
		$if_component = $this->get_config_value( 'if_component', 'component A' );
		$then_component = $this->get_config_value( 'then_component', 'component B' );

		return sprintf(
			__( 'When %1$s is selected, %2$s is required.', 'w2f-pc-configurator' ),
			$if_component,
			$then_component
		);
	}
}
