<?php
/**
 * W2F_PC_Meta_Box_Product_Data class
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Data tabs/panels for the PC Configurator type.
 *
 * @class    W2F_PC_Meta_Box_Product_Data
 * @version  1.0.0
 */
class W2F_PC_Meta_Box_Product_Data {

	/**
	 * Hook in.
	 */
	public static function init() {
		// Processes and saves type-specific data.
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'process_configurator_data' ) );

		// Creates the admin Components panel tab.
		add_action( 'woocommerce_product_data_tabs', array( __CLASS__, 'configurator_product_data_tabs' ) );

		// Creates the admin Components panel.
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'configurator_data_panel' ) );
	}

	/**
	 * Add PC Configurator tab.
	 *
	 * @param  array $tabs
	 * @return array
	 */
	public static function configurator_product_data_tabs( $tabs ) {
		$tabs['pc_configurator'] = array(
			'label'    => __( 'PC Components', 'w2f-pc-configurator' ),
			'target'   => 'pc_configurator_data',
			'class'    => array( 'show_if_pc_configurator' ),
			'priority' => 25,
		);
		return $tabs;
	}

	/**
	 * PC Configurator data panel.
	 */
	public static function configurator_data_panel() {
		global $post, $thepostid, $product_object;

		if ( ! isset( $product_object ) || ! is_a( $product_object, 'W2F_PC_Product' ) ) {
			$thepostid      = $post->ID;
			$product_object = wc_get_product( $thepostid );
		}

		if ( ! is_a( $product_object, 'W2F_PC_Product' ) ) {
			return;
		}

		$components_data = $product_object->get_components_data();
		$tabs = $product_object->get_tabs();

		include W2F_PC()->plugin_path() . '/includes/admin/views/html-configurator-data.php';
	}

	/**
	 * Process and save configurator data.
	 *
	 * @param WC_Product $product
	 */
	public static function process_configurator_data( $product ) {
		if ( 'pc_configurator' !== $product->get_type() ) {
			return;
		}

		// Save components data.
		// Only process if POST data exists - this ensures we don't accidentally clear components.
		if ( isset( $_POST['w2f_pc_components'] ) && is_array( $_POST['w2f_pc_components'] ) ) {
			$components_data = array();
			foreach ( $_POST['w2f_pc_components'] as $component_id => $component_data ) {
				// Skip if component_id is empty or invalid, or if it's the hidden _component_id field.
				if ( empty( $component_id ) || ! is_string( $component_id ) || '_component_id' === $component_id ) {
					continue;
				}
				
				$sanitized_id = sanitize_key( $component_id );
				
				// Handle options - might be array or might not be set.
				$options = array();
				if ( isset( $component_data['options'] ) ) {
					if ( is_array( $component_data['options'] ) ) {
						$options = array_map( 'intval', $component_data['options'] );
						// Remove empty values.
						$options = array_filter( $options, function( $val ) {
							return $val > 0;
						} );
					} elseif ( ! empty( $component_data['options'] ) ) {
						// Single value.
						$val = intval( $component_data['options'] );
						if ( $val > 0 ) {
							$options = array( $val );
						}
					}
				}
				
				// Handle categories - might be array or might not be set.
				$categories = array();
				if ( isset( $component_data['categories'] ) ) {
					if ( is_array( $component_data['categories'] ) ) {
						$categories = array_map( 'intval', $component_data['categories'] );
						// Remove empty values.
						$categories = array_filter( $categories, function( $val ) {
							return $val > 0;
						} );
					} elseif ( ! empty( $component_data['categories'] ) ) {
						// Single value.
						$val = intval( $component_data['categories'] );
						if ( $val > 0 ) {
							$categories = array( $val );
						}
					}
				}
				
				// Always save the component, even if some fields are empty.
				$components_data[ $sanitized_id ] = array(
					'title'              => isset( $component_data['title'] ) ? sanitize_text_field( wp_unslash( $component_data['title'] ) ) : '',
					'description'        => isset( $component_data['description'] ) ? wp_kses_post( wp_unslash( $component_data['description'] ) ) : '',
					'optional'           => isset( $component_data['optional'] ) ? 'yes' : 'no',
					'display_mode'       => isset( $component_data['display_mode'] ) ? sanitize_text_field( wp_unslash( $component_data['display_mode'] ) ) : 'dropdown',
					'tab'                => isset( $component_data['tab'] ) ? sanitize_text_field( wp_unslash( $component_data['tab'] ) ) : '',
					'show_search'        => isset( $component_data['show_search'] ) ? 'yes' : 'no',
					// Always enable dropdown images for dropdown mode (default behavior).
					'show_dropdown_image' => 'yes',
					'enable_quantity'    => isset( $component_data['enable_quantity'] ) ? 'yes' : 'no',
					'min_quantity'       => isset( $component_data['min_quantity'] ) ? max( 1, intval( $component_data['min_quantity'] ) ) : 1,
					'max_quantity'       => isset( $component_data['max_quantity'] ) ? max( 1, intval( $component_data['max_quantity'] ) ) : 99,
					'options'            => array_values( $options ), // Re-index array.
					'categories'         => array_values( $categories ), // Re-index array.
				);
			}
			
			// Always update components data if POST data exists (even if empty, to allow deletion).
			$product->set_components_data( $components_data );
		} else {
			// If POST data doesn't exist, don't clear existing components.
			// This handles cases where the form might not include component data.
		}

		// Save default configuration.
		if ( isset( $_POST['w2f_pc_default_configuration'] ) && is_array( $_POST['w2f_pc_default_configuration'] ) ) {
			$default_config = array();
			foreach ( $_POST['w2f_pc_default_configuration'] as $component_id => $product_id ) {
				$sanitized_id = sanitize_key( $component_id );
				$product_id = intval( $product_id );
				// Only add if product_id is valid.
				if ( $product_id > 0 ) {
					$default_config[ $sanitized_id ] = $product_id;
				}
			}
			$product->set_default_configuration( $default_config );
		}

		// Save default price.
		if ( isset( $_POST['w2f_pc_default_price'] ) ) {
			$default_price = floatval( $_POST['w2f_pc_default_price'] );
			$product->set_default_price( $default_price );
			
			// Also set the regular price if it's not set, so the product is purchasable.
			if ( $default_price > 0 && empty( $product->get_regular_price() ) ) {
				$product->set_regular_price( $default_price );
			}
		} elseif ( empty( $product->get_regular_price() ) ) {
			// If no default price is set but we have components, set a minimal price.
			$components = $product->get_components();
			if ( ! empty( $components ) ) {
				$product->set_regular_price( '0.01' );
			}
		}

		// Save tabs.
		if ( isset( $_POST['w2f_pc_tabs'] ) && is_array( $_POST['w2f_pc_tabs'] ) ) {
			$tabs = array();
			foreach ( $_POST['w2f_pc_tabs'] as $tab ) {
				$tab_name = sanitize_text_field( wp_unslash( $tab ) );
				if ( ! empty( $tab_name ) ) {
					$tabs[] = $tab_name;
				}
			}
			// Remove duplicates and re-index.
			$tabs = array_values( array_unique( $tabs ) );
			$product->set_tabs( $tabs );
		} else {
			// If no tabs submitted, clear tabs.
			$product->set_tabs( array() );
		}
	}
}

