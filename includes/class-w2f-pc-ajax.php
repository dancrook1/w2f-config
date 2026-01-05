<?php
/**
 * W2F_PC_Ajax class
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handlers for PC Configurator.
 *
 * @class    W2F_PC_Ajax
 * @version  1.0.0
 */
class W2F_PC_Ajax {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Ajax
	 */
	protected static $_instance = null;

	/**
	 * Main W2F_PC_Ajax instance.
	 *
	 * @static
	 * @return W2F_PC_Ajax - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// AJAX handlers.
		add_action( 'wp_ajax_w2f_pc_get_component_options', array( $this, 'get_component_options' ) );
		add_action( 'wp_ajax_nopriv_w2f_pc_get_component_options', array( $this, 'get_component_options' ) );

		add_action( 'wp_ajax_w2f_pc_check_compatibility', array( $this, 'check_compatibility' ) );
		add_action( 'wp_ajax_nopriv_w2f_pc_check_compatibility', array( $this, 'check_compatibility' ) );

		add_action( 'wp_ajax_w2f_pc_calculate_price', array( $this, 'calculate_price' ) );
		add_action( 'wp_ajax_nopriv_w2f_pc_calculate_price', array( $this, 'calculate_price' ) );

		add_action( 'wp_ajax_w2f_pc_get_specs', array( $this, 'get_specs' ) );
		add_action( 'wp_ajax_nopriv_w2f_pc_get_specs', array( $this, 'get_specs' ) );

		add_action( 'wp_ajax_w2f_pc_load_configuration', array( $this, 'load_configuration' ) );
		add_action( 'wp_ajax_nopriv_w2f_pc_load_configuration', array( $this, 'load_configuration' ) );

		// Admin AJAX handlers.
		add_action( 'wp_ajax_w2f_pc_search_component_products', array( $this, 'search_component_products' ) );

		add_action( 'wp_ajax_w2f_pc_get_product_tooltip', array( $this, 'get_product_tooltip' ) );
		
		add_action( 'wp_ajax_w2f_pc_admin_calculate_component_total', array( $this, 'admin_calculate_component_total' ) );
		add_action( 'wp_ajax_nopriv_w2f_pc_get_product_tooltip', array( $this, 'get_product_tooltip' ) );

		add_action( 'wp_ajax_w2f_pc_get_product_description', array( $this, 'get_product_description' ) );
		add_action( 'wp_ajax_nopriv_w2f_pc_get_product_description', array( $this, 'get_product_description' ) );

		add_action( 'wp_ajax_w2f_pc_get_filtered_products', array( $this, 'get_filtered_products' ) );
		add_action( 'wp_ajax_nopriv_w2f_pc_get_filtered_products', array( $this, 'get_filtered_products' ) );

		add_action( 'wp_ajax_w2f_pc_get_all_filtered_products', array( $this, 'get_all_filtered_products' ) );
		add_action( 'wp_ajax_nopriv_w2f_pc_get_all_filtered_products', array( $this, 'get_all_filtered_products' ) );

		// Admin AJAX handlers.
		add_action( 'wp_ajax_w2f_pc_preview_rule', array( $this, 'preview_rule' ) );
		
		// Import/Export handlers.
		add_action( 'wp_ajax_w2f_pc_export_config', array( $this, 'export_config' ) );
		add_action( 'wp_ajax_w2f_pc_import_config', array( $this, 'import_config' ) );
	}

	/**
	 * Get component options.
	 */
	public function get_component_options() {
		check_ajax_referer( 'w2f-pc-configurator', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$component_id = isset( $_POST['component_id'] ) ? sanitize_text_field( $_POST['component_id'] ) : '';

		$product = w2f_pc_get_configurator_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'w2f-pc-configurator' ) ) );
		}

		$component = $product->get_component( $component_id );
		if ( ! $component ) {
			wp_send_json_error( array( 'message' => __( 'Invalid component.', 'w2f-pc-configurator' ) ) );
		}

		$options = array();
		foreach ( $component->get_option_products() as $product_id => $option_product ) {
			$options[] = array(
				'id'    => $product_id,
				'name'  => $option_product->get_name(),
				'price' => $option_product->get_price(),
				'image' => $option_product->get_image_id() ? wp_get_attachment_image_url( $option_product->get_image_id(), 'thumbnail' ) : '',
			);
		}

		wp_send_json_success( array( 'options' => $options ) );
	}

	/**
	 * Check compatibility.
	 */
	public function check_compatibility() {
		check_ajax_referer( 'w2f-pc-configurator', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$configuration = isset( $_POST['configuration'] ) && is_array( $_POST['configuration'] ) ? array_map( 'intval', $_POST['configuration'] ) : array();

		$product = w2f_pc_get_configurator_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'w2f-pc-configurator' ) ) );
		}

		$compatibility_manager = W2F_PC_Compatibility_Manager::instance();
		$result = $compatibility_manager->check_compatibility( $configuration, $product );

		wp_send_json_success( $result );
	}

	/**
	 * Calculate price.
	 */
	public function calculate_price() {
		check_ajax_referer( 'w2f-pc-configurator', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$configuration = isset( $_POST['configuration'] ) && is_array( $_POST['configuration'] ) ? array_map( 'intval', $_POST['configuration'] ) : array();
		$quantities = isset( $_POST['quantities'] ) && is_array( $_POST['quantities'] ) ? array_map( 'intval', $_POST['quantities'] ) : array();

		$product = w2f_pc_get_configurator_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'w2f-pc-configurator' ) ) );
		}

		// Explicitly request price including tax for display.
		$price = $product->calculate_configuration_price( $configuration, true, $quantities );
		$is_default = $product->is_default_configuration( $configuration );

		// Price already includes tax from calculate_configuration_price.
		wp_send_json_success( array(
			'price'      => $price,
			'price_html' => wc_price( $price ),
			'is_default' => $is_default,
		) );
	}

	/**
	 * Get aggregated specs.
	 */
	public function get_specs() {
		check_ajax_referer( 'w2f-pc-configurator', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$configuration = isset( $_POST['configuration'] ) && is_array( $_POST['configuration'] ) ? array_map( 'intval', $_POST['configuration'] ) : array();

		$product = w2f_pc_get_configurator_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'w2f-pc-configurator' ) ) );
		}

		$specs = array();
		foreach ( $configuration as $component_id => $product_id ) {
			$selected_product = wc_get_product( $product_id );
			if ( $selected_product ) {
				$specs[ $component_id ] = array(
					'name'  => $selected_product->get_name(),
					'price' => $selected_product->get_price(),
				);
			}
		}

		wp_send_json_success( array( 'specs' => $specs ) );
	}

	/**
	 * Load configuration from share URL.
	 */
	public function load_configuration() {
		check_ajax_referer( 'w2f-pc-configurator', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$encoded_config = isset( $_POST['config'] ) ? sanitize_text_field( $_POST['config'] ) : '';

		if ( empty( $encoded_config ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid configuration.', 'w2f-pc-configurator' ) ) );
		}

		// Decode configuration (simple base64 decode for now).
		$configuration = json_decode( base64_decode( $encoded_config ), true );
		if ( ! is_array( $configuration ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid configuration format.', 'w2f-pc-configurator' ) ) );
		}

		$product = w2f_pc_get_configurator_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'w2f-pc-configurator' ) ) );
		}

		// Validate configuration.
		$components = $product->get_components();
		foreach ( $configuration as $component_id => $product_id ) {
			if ( ! isset( $components[ $component_id ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid component in configuration.', 'w2f-pc-configurator' ) ) );
			}
			$component = $components[ $component_id ];
			if ( ! $component->is_valid_option( $product_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid product in configuration.', 'w2f-pc-configurator' ) ) );
			}
		}

		wp_send_json_success( array( 'configuration' => $configuration ) );
	}

	/**
	 * Search products for component default selector.
	 * This filters products based on component options and categories.
	 */
	public function search_component_products() {
		check_ajax_referer( 'w2f-pc-admin', 'security' );

		$term = isset( $_REQUEST['term'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['term'] ) ) : '';
		$component_options = isset( $_REQUEST['options'] ) && is_array( $_REQUEST['options'] ) ? array_map( 'intval', $_REQUEST['options'] ) : array();
		$component_categories = isset( $_REQUEST['categories'] ) && is_array( $_REQUEST['categories'] ) ? array_map( 'intval', $_REQUEST['categories'] ) : array();

		$products = array();
		$all_product_ids = array();

		// Get products from direct options.
		if ( ! empty( $component_options ) ) {
			$all_product_ids = array_merge( $all_product_ids, $component_options );
		}

		// Get products from categories.
		if ( ! empty( $component_categories ) ) {
			$args = array(
				'status'    => 'publish',
				'limit'     => -1,
				'return'    => 'ids',
				'tax_query' => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $component_categories,
						'operator' => 'IN',
					),
				),
			);
			$category_product_ids = wc_get_products( $args );
			$all_product_ids = array_merge( $all_product_ids, $category_product_ids );
		}

		// Remove duplicates.
		$all_product_ids = array_unique( $all_product_ids );

		// Get product details and filter by search term.
		foreach ( $all_product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product && $product->is_purchasable() ) {
				$product_name = $product->get_formatted_name();
				// If no search term, include all. Otherwise filter.
				if ( empty( $term ) || stripos( $product_name, $term ) !== false ) {
					$products[ $product_id ] = $product_name;
				}
			}
		}

		wp_send_json( $products );
	}

	/**
	 * Get product tooltip data.
	 */
	public function get_product_tooltip() {
		check_ajax_referer( 'w2f-pc-configurator', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product ID.', 'w2f-pc-configurator' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'w2f-pc-configurator' ) ) );
		}

		ob_start();
		include W2F_PC()->plugin_path() . '/templates/single-product/add-to-cart/product-tooltip.php';
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Calculate component total for admin price breakdown.
	 */
	public function admin_calculate_component_total() {
		check_ajax_referer( 'w2f-pc-admin', 'nonce' );

		$products = isset( $_POST['products'] ) && is_array( $_POST['products'] ) ? array_map( 'intval', $_POST['products'] ) : array();

		if ( empty( $products ) ) {
			wp_send_json_success( array( 'component_total' => 0 ) );
		}

		$component_total = 0;
		foreach ( $products as $product_id ) {
			if ( $product_id > 0 ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					// Get price excluding tax (for admin calculation).
					$price = wc_get_price_excluding_tax( $product );
					$component_total += (float) $price;
				}
			}
		}

		wp_send_json_success( array( 'component_total' => $component_total ) );
	}

	/**
	 * Get product description for modal.
	 */
	public function get_product_description() {
		check_ajax_referer( 'w2f-pc-configurator', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product ID.', 'w2f-pc-configurator' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'w2f-pc-configurator' ) ) );
		}

		// Make product available to template.
		ob_start();
		include W2F_PC()->plugin_path() . '/templates/single-product/add-to-cart/product-description-modal.php';
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Get filtered products for a component based on current configuration.
	 */
	public function get_filtered_products() {
		check_ajax_referer( 'w2f-pc-configurator', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$component_id = isset( $_POST['component_id'] ) ? sanitize_text_field( $_POST['component_id'] ) : '';
		$configuration = isset( $_POST['configuration'] ) && is_array( $_POST['configuration'] ) ? array_map( 'intval', $_POST['configuration'] ) : array();

		$product = w2f_pc_get_configurator_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'w2f-pc-configurator' ) ) );
		}

		$compatibility_manager = W2F_PC_Compatibility_Manager::instance();
		$filtered_product_ids = $compatibility_manager->get_filtered_products_for_component( $component_id, $configuration, $product );

		// Also get warnings for each product in this component.
		$component = $product->get_component( $component_id );
		$product_warnings = array();
		if ( $component ) {
			$available_products = $component->get_option_products();
			foreach ( $available_products as $option_product_id => $option_product ) {
				$warnings = $compatibility_manager->get_product_warnings( $component_id, $option_product_id, $configuration, $product );
				if ( ! empty( $warnings ) ) {
					$product_warnings[ $option_product_id ] = $warnings;
				}
			}
		}

		wp_send_json_success( array( 
			'product_ids' => $filtered_product_ids,
			'warnings' => $product_warnings
		) );
	}

	/**
	 * Get filtered products for all components at once (batched).
	 *
	 * @return void
	 */
	public function get_all_filtered_products() {
		check_ajax_referer( 'w2f-pc-configurator', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$component_ids = isset( $_POST['component_ids'] ) && is_array( $_POST['component_ids'] ) ? array_map( 'sanitize_text_field', $_POST['component_ids'] ) : array();
		$configuration = isset( $_POST['configuration'] ) && is_array( $_POST['configuration'] ) ? array_map( 'intval', $_POST['configuration'] ) : array();

		$product = w2f_pc_get_configurator_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'w2f-pc-configurator' ) ) );
		}

		$compatibility_manager = W2F_PC_Compatibility_Manager::instance();
		$results = array();

		foreach ( $component_ids as $component_id ) {
			$filtered_product_ids = $compatibility_manager->get_filtered_products_for_component( $component_id, $configuration, $product );

			// Also get warnings for each product in this component.
			$component = $product->get_component( $component_id );
			$product_warnings = array();
			if ( $component ) {
				$available_products = $component->get_option_products();
				foreach ( $available_products as $option_product_id => $option_product ) {
					$warnings = $compatibility_manager->get_product_warnings( $component_id, $option_product_id, $configuration, $product );
					if ( ! empty( $warnings ) ) {
						$product_warnings[ $option_product_id ] = $warnings;
					}
				}
			}

			$results[ $component_id ] = array(
				'product_ids' => $filtered_product_ids,
				'warnings' => $product_warnings
			);
		}

		wp_send_json_success( array( 'components' => $results ) );
	}

	/**
	 * Preview rule impact (admin only).
	 */
	public function preview_rule() {
		// Check nonce.
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'w2f-pc-admin' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'w2f-pc-configurator' ) ) );
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'w2f-pc-configurator' ) ) );
		}

		$rule_data = isset( $_POST['rule_data'] ) ? $_POST['rule_data'] : array();
		
		if ( empty( $rule_data ) || ! is_array( $rule_data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid rule data.', 'w2f-pc-configurator' ) ) );
		}

		// Sanitize rule data - handle numeric values properly.
		$conditions = isset( $rule_data['conditions'] ) && is_array( $rule_data['conditions'] ) ? $rule_data['conditions'] : array();
		$sanitized_conditions = array();
		foreach ( $conditions as $key => $value ) {
			// Handle numeric values for numeric_attribute rules.
			if ( in_array( $key, array( 'value_a', 'value_b' ), true ) ) {
				$sanitized_conditions[ $key ] = is_numeric( $value ) ? floatval( $value ) : 0;
			} elseif ( is_array( $value ) ) {
				// Handle array values (shouldn't happen, but be safe).
				$sanitized_conditions[ $key ] = array_map( 'sanitize_text_field', $value );
			} elseif ( is_numeric( $value ) ) {
				// Preserve numeric values that aren't explicitly value_a/value_b.
				$sanitized_conditions[ $key ] = floatval( $value );
			} else {
				$sanitized_conditions[ $key ] = sanitize_text_field( $value );
			}
		}

		$rule = array(
			'name' => isset( $rule_data['name'] ) ? sanitize_text_field( $rule_data['name'] ) : '',
			'type' => isset( $rule_data['type'] ) ? sanitize_text_field( $rule_data['type'] ) : 'product_match',
			'action' => isset( $rule_data['action'] ) ? sanitize_text_field( $rule_data['action'] ) : 'require',
			'is_active' => 'yes', // Preview as if active.
			'conditions' => $sanitized_conditions,
		);

		$compatibility_manager = W2F_PC_Compatibility_Manager::instance();
		
		$configurator_products = wc_get_products( array(
			'type' => 'pc_configurator',
			'limit' => -1,
			'status' => 'publish',
		) );

		$preview_results = array(
			'affected_configurators' => array(),
			'affected_products' => array(), // Will contain arrays with id and name
			'incompatible_combinations' => array(),
			'total_impact' => 0,
		);

		// Get component IDs from rule conditions - handle different rule types.
		$conditions = $rule['conditions'];
		$component_a = isset( $conditions['component_a'] ) ? $conditions['component_a'] : '';
		$component_b = isset( $conditions['component_b'] ) ? $conditions['component_b'] : '';
		$target_component = isset( $conditions['target_component'] ) ? $conditions['target_component'] : '';
		$source_component = isset( $conditions['source_component'] ) ? $conditions['source_component'] : '';

		// For category_exclude and other single-component rules, use target_component or source_component.
		if ( empty( $component_a ) && ! empty( $target_component ) ) {
			$component_a = $target_component;
		}
		if ( empty( $component_b ) && ! empty( $source_component ) ) {
			$component_b = $source_component;
		}

		foreach ( $configurator_products as $config_product ) {
			if ( ! is_a( $config_product, 'W2F_PC_Product' ) ) {
				continue;
			}

			$components = $config_product->get_components();
			$rule_applies = false;
			$affected_components = array();

			// Check if components exist in this configurator.
			if ( $component_a && isset( $components[ $component_a ] ) ) {
				$rule_applies = true;
				$affected_components[] = $component_a;
			}
			if ( $component_b && isset( $components[ $component_b ] ) ) {
				$rule_applies = true;
				if ( ! in_array( $component_b, $affected_components, true ) ) {
					$affected_components[] = $component_b;
				}
			}

			// For single-component rules, still apply if at least one component matches.
			if ( ! $rule_applies && ( $component_a || $component_b ) ) {
				// Check if any component matches (for rules that might affect a single component).
				if ( $component_a && isset( $components[ $component_a ] ) ) {
					$rule_applies = true;
					$affected_components[] = $component_a;
				} elseif ( $component_b && isset( $components[ $component_b ] ) ) {
					$rule_applies = true;
					$affected_components[] = $component_b;
				}
			}

			if ( ! $rule_applies ) {
				continue;
			}

			$preview_results['affected_configurators'][] = array(
				'id' => $config_product->get_id(),
				'name' => $config_product->get_name(),
				'components' => $affected_components,
			);

			// Get products from affected components.
			$component_a_products = array();
			$component_b_products = array();

			if ( $component_a && isset( $components[ $component_a ] ) ) {
				$comp_a = $components[ $component_a ];
				$option_products = $comp_a->get_option_products();
				foreach ( $option_products as $product_id => $product ) {
					$component_a_products[ $product_id ] = $product->get_name();
					// Add to affected products with name
					$found = false;
					foreach ( $preview_results['affected_products'] as $existing ) {
						if ( $existing['id'] === $product_id ) {
							$found = true;
							break;
						}
					}
					if ( ! $found ) {
						$preview_results['affected_products'][] = array(
							'id' => $product_id,
							'name' => $product->get_name(),
						);
					}
				}
			}

			if ( $component_b && isset( $components[ $component_b ] ) ) {
				$comp_b = $components[ $component_b ];
				$option_products = $comp_b->get_option_products();
				foreach ( $option_products as $product_id => $product ) {
					$component_b_products[ $product_id ] = $product->get_name();
					// Add to affected products with name
					$found = false;
					foreach ( $preview_results['affected_products'] as $existing ) {
						if ( $existing['id'] === $product_id ) {
							$found = true;
							break;
						}
					}
					if ( ! $found ) {
						$preview_results['affected_products'][] = array(
							'id' => $product_id,
							'name' => $product->get_name(),
						);
					}
				}
			}

			// Test all combinations to find incompatible ones.
			// Only test combinations if we have both components.
			if ( ! empty( $component_a_products ) && ! empty( $component_b_products ) ) {
				foreach ( $component_a_products as $product_a_id => $product_a_name ) {
					foreach ( $component_b_products as $product_b_id => $product_b_name ) {
						$test_config = array(
							$component_a => $product_a_id,
							$component_b => $product_b_id,
						);
						
						$test_result = $compatibility_manager->evaluate_rule( $rule, $test_config, $config_product );
						
						if ( ! $test_result['valid'] || ! empty( $test_result['warnings'] ) ) {
							$preview_results['incompatible_combinations'][] = array(
								'configurator_id' => $config_product->get_id(),
								'configurator_name' => $config_product->get_name(),
								'component_a' => $component_a,
								'component_b' => $component_b,
								'product_a_id' => $product_a_id,
								'product_a_name' => $product_a_name,
								'product_b_id' => $product_b_id,
								'product_b_name' => $product_b_name,
								'result' => ! $test_result['valid'] ? 'blocked' : 'warning',
								'message' => ! empty( $test_result['errors'] ) ? implode( '; ', $test_result['errors'] ) : implode( '; ', $test_result['warnings'] ),
							);
						}
					}
				}
			} elseif ( ! empty( $component_a_products ) && $component_a ) {
				// For single-component rules (like category_exclude), test each product individually.
				foreach ( $component_a_products as $product_a_id => $product_a_name ) {
					$test_config = array(
						$component_a => $product_a_id,
					);
					
					$test_result = $compatibility_manager->evaluate_rule( $rule, $test_config, $config_product );
					
					if ( ! $test_result['valid'] || ! empty( $test_result['warnings'] ) ) {
						$preview_results['incompatible_combinations'][] = array(
							'configurator_id' => $config_product->get_id(),
							'configurator_name' => $config_product->get_name(),
							'component_a' => $component_a,
							'component_b' => '',
							'product_a_id' => $product_a_id,
							'product_a_name' => $product_a_name,
							'product_b_id' => 0,
							'product_b_name' => '',
							'result' => ! $test_result['valid'] ? 'blocked' : 'warning',
							'message' => ! empty( $test_result['errors'] ) ? implode( '; ', $test_result['errors'] ) : implode( '; ', $test_result['warnings'] ),
						);
					}
				}
			}
		}

		$preview_results['total_impact'] = count( $preview_results['affected_configurators'] );

		wp_send_json_success( $preview_results );
	}

	/**
	 * Export configuration.
	 */
	public function export_config() {
		check_ajax_referer( 'w2f-pc-admin', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to export configurations.', 'w2f-pc-configurator' ) ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product ID.', 'w2f-pc-configurator' ) ) );
		}

		$product = w2f_pc_get_configurator_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'w2f-pc-configurator' ) ) );
		}

		// Get all configuration data.
		$export_data = array(
			'version' => '1.0',
			'export_date' => current_time( 'mysql' ),
			'product_id' => $product_id,
			'product_name' => $product->get_name(),
			'components' => $product->get_components_data(),
			'default_configuration' => $product->get_default_configuration(),
			'default_price' => $product->get_default_price(),
			'tabs' => $product->get_tabs(),
		);

		// Return JSON data.
		wp_send_json_success( $export_data );
	}

	/**
	 * Import configuration.
	 */
	public function import_config() {
		check_ajax_referer( 'w2f-pc-admin', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to import configurations.', 'w2f-pc-configurator' ) ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product ID.', 'w2f-pc-configurator' ) ) );
		}

		$product = w2f_pc_get_configurator_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'w2f-pc-configurator' ) ) );
		}

		// Get import data.
		$import_data = isset( $_POST['import_data'] ) ? wp_unslash( $_POST['import_data'] ) : '';
		if ( empty( $import_data ) ) {
			wp_send_json_error( array( 'message' => __( 'No import data provided.', 'w2f-pc-configurator' ) ) );
		}

		// Decode JSON.
		$data = json_decode( $import_data, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => __( 'Invalid JSON data.', 'w2f-pc-configurator' ) ) );
		}

		// Validate data structure.
		if ( ! isset( $data['components'] ) || ! isset( $data['default_configuration'] ) || ! isset( $data['tabs'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid configuration format.', 'w2f-pc-configurator' ) ) );
		}

		// Import components.
		if ( is_array( $data['components'] ) ) {
			$product->set_components_data( $data['components'] );
		}

		// Import default configuration.
		if ( is_array( $data['default_configuration'] ) ) {
			$product->set_default_configuration( $data['default_configuration'] );
		}

		// Import default price.
		if ( isset( $data['default_price'] ) ) {
			$product->set_default_price( floatval( $data['default_price'] ) );
		}

		// Import tabs.
		if ( is_array( $data['tabs'] ) ) {
			$product->set_tabs( $data['tabs'] );
		}

		// Save the product.
		$product->save();

		wp_send_json_success( array(
			'message' => __( 'Configuration imported successfully. Please refresh the page to see the changes.', 'w2f-pc-configurator' ),
			'components_count' => count( $data['components'] ),
			'tabs_count' => count( $data['tabs'] ),
		) );
	}
}

