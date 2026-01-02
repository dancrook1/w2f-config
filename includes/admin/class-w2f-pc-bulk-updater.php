<?php
/**
 * W2F_PC_Bulk_Updater class
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk update functionality for PC Configurator.
 *
 * @class    W2F_PC_Bulk_Updater
 * @version  1.0.0
 */
class W2F_PC_Bulk_Updater {

	/**
	 * Find all configurator products using a specific product ID.
	 *
	 * @param  int $product_id
	 * @return array Array of configurator product IDs and usage details
	 */
	public static function find_product_usage( $product_id ) {
		$usage = array(
			'in_defaults' => array(),
			'in_component_options' => array(),
		);

		$configurator_products = wc_get_products( array(
			'type' => 'pc_configurator',
			'limit' => -1,
			'status' => 'any',
		) );

		foreach ( $configurator_products as $config_product ) {
			if ( ! is_a( $config_product, 'W2F_PC_Product' ) ) {
				continue;
			}

			$config_id = $config_product->get_id();
			$found_in_default = false;
			$found_in_components = false;
			$component_details = array();

			// Check default configuration.
			$default_config = $config_product->get_default_configuration();
			foreach ( $default_config as $component_id => $default_product_id ) {
				if ( (int) $default_product_id === (int) $product_id ) {
					$found_in_default = true;
					$usage['in_defaults'][] = array(
						'configurator_id' => $config_id,
						'configurator_name' => $config_product->get_name(),
						'component_id' => $component_id,
					);
					break;
				}
			}

			// Check component options.
			$components = $config_product->get_components();
			foreach ( $components as $component_id => $component ) {
				$component_data = $component->get_data();
				$options = isset( $component_data['options'] ) ? $component_data['options'] : array();
				
				if ( in_array( (int) $product_id, array_map( 'intval', $options ), true ) ) {
					$found_in_components = true;
					$component_details[] = array(
						'component_id' => $component_id,
						'component_title' => $component->get_title(),
					);
				}
			}

			if ( $found_in_components ) {
				$usage['in_component_options'][] = array(
					'configurator_id' => $config_id,
					'configurator_name' => $config_product->get_name(),
					'components' => $component_details,
				);
			}
		}

		return $usage;
	}

	/**
	 * Update default configurations.
	 *
	 * @param  int $old_product_id
	 * @param  int $new_product_id
	 * @return array Results array with updated count and details
	 */
	public static function update_default_configurations( $old_product_id, $new_product_id ) {
		$results = array(
			'updated' => 0,
			'errors' => array(),
			'details' => array(),
		);

		$configurator_products = wc_get_products( array(
			'type' => 'pc_configurator',
			'limit' => -1,
			'status' => 'any',
		) );

		foreach ( $configurator_products as $config_product ) {
			if ( ! is_a( $config_product, 'W2F_PC_Product' ) ) {
				continue;
			}

			$default_config = $config_product->get_default_configuration();
			$updated = false;
			$updated_components = array();

			foreach ( $default_config as $component_id => $default_product_id ) {
				if ( (int) $default_product_id === (int) $old_product_id ) {
					$default_config[ $component_id ] = (int) $new_product_id;
					$updated = true;
					$updated_components[] = $component_id;
				}
			}

			if ( $updated ) {
				try {
					$config_product->set_default_configuration( $default_config );
					$config_product->save();
					
					// Recalculate default price if needed.
					$new_price = $config_product->calculate_configuration_price( $default_config, false );
					$config_product->set_default_price( $new_price );
					$config_product->save();

					$results['updated']++;
					$results['details'][] = array(
						'configurator_id' => $config_product->get_id(),
						'configurator_name' => $config_product->get_name(),
						'components' => $updated_components,
					);
				} catch ( Exception $e ) {
					$results['errors'][] = sprintf(
						__( 'Error updating %s: %s', 'w2f-pc-configurator' ),
						$config_product->get_name(),
						$e->getMessage()
					);
				}
			}
		}

		return $results;
	}

	/**
	 * Update component options.
	 *
	 * @param  int $old_product_id
	 * @param  int $new_product_id
	 * @return array Results array with updated count and details
	 */
	public static function update_component_options( $old_product_id, $new_product_id ) {
		$results = array(
			'updated' => 0,
			'errors' => array(),
			'details' => array(),
		);

		$configurator_products = wc_get_products( array(
			'type' => 'pc_configurator',
			'limit' => -1,
			'status' => 'any',
		) );

		foreach ( $configurator_products as $config_product ) {
			if ( ! is_a( $config_product, 'W2F_PC_Product' ) ) {
				continue;
			}

			$components_data = $config_product->get_components_data();
			$updated = false;
			$updated_components = array();

			foreach ( $components_data as $component_id => $component_data ) {
				if ( isset( $component_data['options'] ) && is_array( $component_data['options'] ) ) {
					$options = $component_data['options'];
					$key = array_search( (int) $old_product_id, array_map( 'intval', $options ), true );
					
					if ( false !== $key ) {
						$options[ $key ] = (int) $new_product_id;
						$components_data[ $component_id ]['options'] = array_values( array_unique( $options ) );
						$updated = true;
						$updated_components[] = $component_id;
					}
				}
			}

			if ( $updated ) {
				try {
					$config_product->set_components_data( $components_data );
					$config_product->save();

					$results['updated']++;
					$results['details'][] = array(
						'configurator_id' => $config_product->get_id(),
						'configurator_name' => $config_product->get_name(),
						'components' => $updated_components,
					);
				} catch ( Exception $e ) {
					$results['errors'][] = sprintf(
						__( 'Error updating %s: %s', 'w2f-pc-configurator' ),
						$config_product->get_name(),
						$e->getMessage()
					);
				}
			}
		}

		return $results;
	}

	/**
	 * Perform bulk update.
	 *
	 * @param  int    $old_product_id
	 * @param  int    $new_product_id
	 * @param  bool   $update_defaults
	 * @param  bool   $update_options
	 * @return array Combined results
	 */
	public static function bulk_update( $old_product_id, $new_product_id, $update_defaults = true, $update_options = true ) {
		$results = array(
			'defaults' => array(),
			'options' => array(),
			'total_updated' => 0,
			'total_errors' => 0,
		);

		if ( $update_defaults ) {
			$results['defaults'] = self::update_default_configurations( $old_product_id, $new_product_id );
			$results['total_updated'] += $results['defaults']['updated'];
			$results['total_errors'] += count( $results['defaults']['errors'] );
		}

		if ( $update_options ) {
			$results['options'] = self::update_component_options( $old_product_id, $new_product_id );
			$results['total_updated'] += $results['options']['updated'];
			$results['total_errors'] += count( $results['options']['errors'] );
		}

		return $results;
	}
}

