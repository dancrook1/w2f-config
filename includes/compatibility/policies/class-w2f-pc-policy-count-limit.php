<?php
/**
 * W2F_PC_Policy_Count_Limit class
 *
 * Limits the count of items meeting a condition.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Count Limit Policy - limits count of items.
 *
 * Examples:
 * - count(storage where interface=NVMe) <= motherboard.m2_slots
 * - count(ram) <= motherboard.ram_slots
 *
 * @class    W2F_PC_Policy_Count_Limit
 * @version  2.0.0
 */
class W2F_PC_Policy_Count_Limit extends W2F_PC_Policy_Base {

	/**
	 * Get policy type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'count_limit';
	}

	/**
	 * Get configuration fields.
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'count_component' => array(
				'type' => 'component_select',
				'label' => __( 'Component Type', 'w2f-pc-configurator' ),
				'description' => __( 'The component type to count (e.g., storage, ram).', 'w2f-pc-configurator' ),
				'required' => true,
			),
			'count_where_key' => array(
				'type' => 'text',
				'label' => __( 'Filter Key (optional)', 'w2f-pc-configurator' ),
				'description' => __( 'Only count items where this attribute... (e.g., interface).', 'w2f-pc-configurator' ),
				'required' => false,
			),
			'count_where_value' => array(
				'type' => 'text',
				'label' => __( 'Filter Value', 'w2f-pc-configurator' ),
				'description' => __( '...equals this value (e.g., NVMe).', 'w2f-pc-configurator' ),
				'required' => false,
			),
			'limit_key' => array(
				'type' => 'fact_select',
				'label' => __( 'Limit', 'w2f-pc-configurator' ),
				'description' => __( 'The maximum count allowed (e.g., motherboard.m2_slots).', 'w2f-pc-configurator' ),
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
		$count_component = $this->get_config_value( 'count_component' );
		$limit_key = $this->get_config_value( 'limit_key' );

		// Applicable if the component type has products and limit exists.
		return $facts->has_component( $count_component ) && $facts->has( $limit_key );
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
		$count_component = $this->get_config_value( 'count_component' );
		$count_where_key = $this->get_config_value( 'count_where_key', '' );
		$count_where_value = $this->get_config_value( 'count_where_value', '' );
		$limit_key = $this->get_config_value( 'limit_key' );

		// Get the limit.
		$limit = (int) $facts->get( $limit_key, 0 );

		// Count items.
		$count = 0;
		if ( ! empty( $count_where_key ) && ! empty( $count_where_value ) ) {
			// Count with filter.
			$count = $facts->count_products_where( $count_component, $count_where_key, $count_where_value, '=' );
		} else {
			// Count all.
			$count = $facts->count_products( $count_component );
		}

		$passed = $count <= $limit;

		return array(
			'passed' => $passed,
			'message' => $passed ? '' : $this->render_message( $facts, array( 
				'count' => $count,
				'limit' => $limit,
			) ),
			'details' => array(
				'count_component' => $count_component,
				'count_where_key' => $count_where_key,
				'count_where_value' => $count_where_value,
				'limit_key' => $limit_key,
				'count' => $count,
				'limit' => $limit,
			),
			'compared' => array(
				'left' => $count,
				'right' => $limit,
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
		$count_component = $this->get_config_value( 'count_component', 'items' );
		$count_where_value = $this->get_config_value( 'count_where_value', '' );

		$what = $count_where_value ? $count_where_value . ' ' . $count_component : $count_component;

		return sprintf(
			__( 'Too many %s selected ({{count}} selected, only {{limit}} available).', 'w2f-pc-configurator' ),
			$what
		);
	}
}
