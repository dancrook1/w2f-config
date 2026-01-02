<?php
/**
 * W2F_PC_Compatibility_Manager class
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global compatibility rules manager.
 *
 * @class    W2F_PC_Compatibility_Manager
 * @version  1.0.0
 */
class W2F_PC_Compatibility_Manager {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Compatibility_Manager
	 */
	protected static $_instance = null;

	/**
	 * Compatibility rules.
	 *
	 * @var array
	 */
	private $rules = array();

	/**
	 * Main W2F_PC_Compatibility_Manager instance.
	 *
	 * @static
	 * @return W2F_PC_Compatibility_Manager - Main instance
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
		$this->load_rules();
	}

	/**
	 * Load compatibility rules from database.
	 */
	private function load_rules() {
		$rules = get_option( 'w2f_pc_compatibility_rules', array() );
		if ( ! is_array( $rules ) ) {
			$rules = array();
		}
		$this->rules = $rules;
	}

	/**
	 * Get all compatibility rules.
	 *
	 * @return array
	 */
	public function get_rules() {
		return $this->rules;
	}

	/**
	 * Get rule by ID.
	 *
	 * @param  string $rule_id
	 * @return array|false
	 */
	public function get_rule( $rule_id ) {
		return isset( $this->rules[ $rule_id ] ) ? $this->rules[ $rule_id ] : false;
	}

	/**
	 * Save compatibility rule.
	 *
	 * @param  string $rule_id
	 * @param  array  $rule_data
	 */
	public function save_rule( $rule_id, $rule_data ) {
		$this->rules[ $rule_id ] = $rule_data;
		update_option( 'w2f_pc_compatibility_rules', $this->rules );
	}

	/**
	 * Delete compatibility rule.
	 *
	 * @param  string $rule_id
	 */
	public function delete_rule( $rule_id ) {
		if ( isset( $this->rules[ $rule_id ] ) ) {
			unset( $this->rules[ $rule_id ] );
			update_option( 'w2f_pc_compatibility_rules', $this->rules );
		}
	}

	/**
	 * Check compatibility of a configuration.
	 *
	 * @param  array $configuration Array of component_id => product_id
	 * @param  W2F_PC_Product $configurator_product
	 * @return array Array with 'valid' boolean and 'errors' array
	 */
	public function check_compatibility( $configuration, $configurator_product = null ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
			'warnings' => array(),
		);

		if ( empty( $configuration ) || empty( $this->rules ) ) {
			return $result;
		}

		foreach ( $this->rules as $rule_id => $rule ) {
			if ( empty( $rule['is_active'] ) || 'yes' !== $rule['is_active'] ) {
				continue;
			}

			$rule_result = $this->evaluate_rule( $rule, $configuration, $configurator_product );
			if ( ! $rule_result['valid'] ) {
				$result['valid'] = false;
				$result['errors'] = array_merge( $result['errors'], $rule_result['errors'] );
			}
			if ( ! empty( $rule_result['warnings'] ) ) {
				$result['warnings'] = array_merge( $result['warnings'], $rule_result['warnings'] );
			}
		}

		return $result;
	}

	/**
	 * Evaluate a single compatibility rule.
	 *
	 * @param  array $rule
	 * @param  array $configuration
	 * @param  W2F_PC_Product $configurator_product
	 * @return array
	 */
	public function evaluate_rule( $rule, $configuration, $configurator_product = null ) {
		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
		);

		$rule_type = isset( $rule['type'] ) ? $rule['type'] : 'product_match';

		switch ( $rule_type ) {
			case 'product_match':
				$result = $this->evaluate_product_match_rule( $rule, $configuration );
				break;
			case 'attribute_match':
				$result = $this->evaluate_attribute_match_rule( $rule, $configuration );
				break;
			case 'spec_match':
				$result = $this->evaluate_spec_match_rule( $rule, $configuration );
				break;
			case 'category_exclude':
				$result = $this->evaluate_category_exclude_rule( $rule, $configuration );
				break;
			case 'numeric_attribute':
				$result = $this->evaluate_numeric_attribute_rule( $rule, $configuration );
				break;
		}

		return $result;
	}

	/**
	 * Evaluate product-to-product rule.
	 *
	 * @param  array $rule
	 * @param  array $configuration
	 * @return array
	 */
	private function evaluate_product_match_rule( $rule, $configuration ) {
		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
		);

		if ( empty( $rule['conditions'] ) ) {
			return $result;
		}

		$conditions = $rule['conditions'];
		$action = isset( $rule['action'] ) ? $rule['action'] : 'require';

		// Check if required products are present.
		if ( 'require' === $action ) {
			$component_a = isset( $conditions['component_a'] ) ? $conditions['component_a'] : '';
			$product_a = isset( $conditions['product_a'] ) ? (int) $conditions['product_a'] : 0;
			$component_b = isset( $conditions['component_b'] ) ? $conditions['component_b'] : '';
			$product_b = isset( $conditions['product_b'] ) ? (int) $conditions['product_b'] : 0;

			if ( ! empty( $component_a ) && ! empty( $product_a ) && isset( $configuration[ $component_a ] ) ) {
				if ( (int) $configuration[ $component_a ] === $product_a ) {
					// Product A is selected, check if Product B is required.
					if ( ! empty( $component_b ) && ! empty( $product_b ) ) {
						if ( ! isset( $configuration[ $component_b ] ) || (int) $configuration[ $component_b ] !== $product_b ) {
							$result['valid'] = false;
							$result['errors'][] = isset( $rule['message'] ) ? $rule['message'] : __( 'Compatibility requirement not met.', 'w2f-pc-configurator' );
						}
					}
				}
			}
		} elseif ( 'exclude' === $action ) {
			// Check if incompatible products are both selected.
			$component_a = isset( $conditions['component_a'] ) ? $conditions['component_a'] : '';
			$product_a = isset( $conditions['product_a'] ) ? (int) $conditions['product_a'] : 0;
			$component_b = isset( $conditions['component_b'] ) ? $conditions['component_b'] : '';
			$product_b = isset( $conditions['product_b'] ) ? (int) $conditions['product_b'] : 0;

			if ( ! empty( $component_a ) && ! empty( $product_a ) && isset( $configuration[ $component_a ] ) ) {
				if ( (int) $configuration[ $component_a ] === $product_a ) {
					if ( ! empty( $component_b ) && ! empty( $product_b ) && isset( $configuration[ $component_b ] ) ) {
						if ( (int) $configuration[ $component_b ] === $product_b ) {
							$result['valid'] = false;
							$result['errors'][] = isset( $rule['message'] ) ? $rule['message'] : __( 'Selected components are incompatible.', 'w2f-pc-configurator' );
						}
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Evaluate attribute-based rule.
	 *
	 * @param  array $rule
	 * @param  array $configuration
	 * @return array
	 */
	private function evaluate_attribute_match_rule( $rule, $configuration ) {
		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
		);

		if ( empty( $rule['conditions'] ) ) {
			return $result;
		}

		$conditions = $rule['conditions'];
		$action = isset( $rule['action'] ) ? $rule['action'] : 'require';

		$component_a = isset( $conditions['component_a'] ) ? $conditions['component_a'] : '';
		$attribute_a = isset( $conditions['attribute_a'] ) ? $conditions['attribute_a'] : '';
		$value_a = isset( $conditions['value_a'] ) ? $conditions['value_a'] : '';
		$component_b = isset( $conditions['component_b'] ) ? $conditions['component_b'] : '';
		$attribute_b = isset( $conditions['attribute_b'] ) ? $conditions['attribute_b'] : '';
		$value_b = isset( $conditions['value_b'] ) ? $conditions['value_b'] : '';

		if ( empty( $component_a ) || empty( $attribute_a ) || empty( $value_a ) ) {
			return $result;
		}

		if ( ! isset( $configuration[ $component_a ] ) ) {
			return $result;
		}

		$product_a = wc_get_product( $configuration[ $component_a ] );
		if ( ! $product_a ) {
			return $result;
		}

		// Get attribute value from product.
		$product_value_a = $this->get_product_attribute_value( $product_a, $attribute_a );

		if ( $product_value_a !== $value_a ) {
			return $result; // Rule doesn't apply.
		}

		// Rule applies, check component B.
		if ( 'require' === $action && ! empty( $component_b ) && ! empty( $attribute_b ) && ! empty( $value_b ) ) {
			if ( ! isset( $configuration[ $component_b ] ) ) {
				$result['valid'] = false;
				$result['errors'][] = isset( $rule['message'] ) ? $rule['message'] : __( 'Compatibility requirement not met.', 'w2f-pc-configurator' );
				return $result;
			}

			$product_b = wc_get_product( $configuration[ $component_b ] );
			if ( ! $product_b ) {
				$result['valid'] = false;
				$result['errors'][] = isset( $rule['message'] ) ? $rule['message'] : __( 'Compatibility requirement not met.', 'w2f-pc-configurator' );
				return $result;
			}

			$product_value_b = $this->get_product_attribute_value( $product_b, $attribute_b );
			if ( $product_value_b !== $value_b ) {
				$result['valid'] = false;
				$result['errors'][] = isset( $rule['message'] ) ? $rule['message'] : __( 'Compatibility requirement not met.', 'w2f-pc-configurator' );
			}
		}

		return $result;
	}

	/**
	 * Evaluate spec-based rule.
	 *
	 * @param  array $rule
	 * @param  array $configuration
	 * @return array
	 */
	private function evaluate_spec_match_rule( $rule, $configuration ) {
		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
		);

		if ( empty( $rule['conditions'] ) ) {
			return $result;
		}

		$conditions = $rule['conditions'];
		$action = isset( $rule['action'] ) ? $rule['action'] : 'require';

		$component_a = isset( $conditions['component_a'] ) ? $conditions['component_a'] : '';
		$spec_a = isset( $conditions['spec_a'] ) ? $conditions['spec_a'] : '';
		$value_a = isset( $conditions['value_a'] ) ? $conditions['value_a'] : '';
		$component_b = isset( $conditions['component_b'] ) ? $conditions['component_b'] : '';
		$spec_b = isset( $conditions['spec_b'] ) ? $conditions['spec_b'] : '';
		$value_b = isset( $conditions['value_b'] ) ? $conditions['value_b'] : '';

		if ( empty( $component_a ) || empty( $spec_a ) || empty( $value_a ) ) {
			return $result;
		}

		if ( ! isset( $configuration[ $component_a ] ) ) {
			return $result;
		}

		$product_a = wc_get_product( $configuration[ $component_a ] );
		if ( ! $product_a ) {
			return $result;
		}

		// Get spec value from product meta.
		$product_spec_a = $product_a->get_meta( $spec_a, true );

		if ( $product_spec_a !== $value_a ) {
			return $result; // Rule doesn't apply.
		}

		// Rule applies, check component B.
		if ( 'require' === $action && ! empty( $component_b ) && ! empty( $spec_b ) && ! empty( $value_b ) ) {
			if ( ! isset( $configuration[ $component_b ] ) ) {
				$result['valid'] = false;
				$result['errors'][] = isset( $rule['message'] ) ? $rule['message'] : __( 'Compatibility requirement not met.', 'w2f-pc-configurator' );
				return $result;
			}

			$product_b = wc_get_product( $configuration[ $component_b ] );
			if ( ! $product_b ) {
				$result['valid'] = false;
				$result['errors'][] = isset( $rule['message'] ) ? $rule['message'] : __( 'Compatibility requirement not met.', 'w2f-pc-configurator' );
				return $result;
			}

			$product_spec_b = $product_b->get_meta( $spec_b, true );
			if ( $product_spec_b !== $value_b ) {
				$result['valid'] = false;
				$result['errors'][] = isset( $rule['message'] ) ? $rule['message'] : __( 'Compatibility requirement not met.', 'w2f-pc-configurator' );
			}
		}

		return $result;
	}

	/**
	 * Evaluate category-based exclusion rule.
	 * Example: If CPU is in "Intel" category, exclude products in "AMD Motherboard" category.
	 *
	 * @param  array $rule
	 * @param  array $configuration
	 * @return array
	 */
	private function evaluate_category_exclude_rule( $rule, $configuration ) {
		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
		);

		if ( empty( $rule['conditions'] ) ) {
			return $result;
		}

		$conditions = $rule['conditions'];
		$action = isset( $rule['action'] ) ? $rule['action'] : 'exclude';

		$component_a = isset( $conditions['component_a'] ) ? $conditions['component_a'] : '';
		$category_a = isset( $conditions['category_a'] ) ? (int) $conditions['category_a'] : 0;
		$component_b = isset( $conditions['component_b'] ) ? $conditions['component_b'] : '';
		$category_b = isset( $conditions['category_b'] ) ? (int) $conditions['category_b'] : 0;

		// Component IDs are now global (not product-specific).
		// Support legacy format "product_id|component_id" for backward compatibility.
		if ( ! empty( $component_a ) && strpos( $component_a, '|' ) !== false ) {
			$parts = explode( '|', $component_a );
			$component_a = isset( $parts[1] ) ? $parts[1] : $component_a;
		}
		if ( ! empty( $component_b ) && strpos( $component_b, '|' ) !== false ) {
			$parts = explode( '|', $component_b );
			$component_b = isset( $parts[1] ) ? $parts[1] : $component_b;
		}

		if ( empty( $component_a ) || empty( $category_a ) || empty( $component_b ) || empty( $category_b ) ) {
			return $result;
		}

		// Check if component A has a product selected.
		if ( ! isset( $configuration[ $component_a ] ) ) {
			return $result;
		}

		$product_a = wc_get_product( $configuration[ $component_a ] );
		if ( ! $product_a ) {
			return $result;
		}

		// Check if product A is in category A.
		$product_a_categories = wp_get_post_terms( $product_a->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $product_a_categories ) || ! in_array( $category_a, $product_a_categories, true ) ) {
			return $result; // Rule doesn't apply.
		}

		// Rule applies - check component B.
		// For category_exclude rules, we want to check if component B products would be incompatible.
		// This allows us to mark individual products in component B with warnings even before they're selected.
		if ( ! isset( $configuration[ $component_b ] ) ) {
			// Component B not selected yet - this will be handled by get_product_warnings when checking individual products.
			// Return a general warning that this selection may limit options (for both warn and exclude actions).
			$result['warnings'][] = isset( $rule['message'] ) ? $rule['message'] : __( 'This selection may limit your options for other components.', 'w2f-pc-configurator' );
			return $result;
		}

		$product_b = wc_get_product( $configuration[ $component_b ] );
		if ( ! $product_b ) {
			return $result;
		}

		// Check if product B is in category B (incompatible).
		$product_b_categories = wp_get_post_terms( $product_b->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $product_b_categories ) && in_array( $category_b, $product_b_categories, true ) ) {
			// For category_exclude rules, always show warnings (not errors) for both "warn" and "exclude" actions.
			// This ensures products are highlighted but remain selectable.
			$result['warnings'][] = isset( $rule['message'] ) ? $rule['message'] : __( 'These components may not be compatible.', 'w2f-pc-configurator' );
		}

		return $result;
	}

	/**
	 * Evaluate numeric attribute-based rule.
	 * Compares two component attributes directly (e.g., GPU Wattage vs PSU Wattage).
	 * Example: If GPU Minimum Wattage > PSU Wattage, show error and prevent purchase.
	 *
	 * @param  array $rule
	 * @param  array $configuration
	 * @return array
	 */
	private function evaluate_numeric_attribute_rule( $rule, $configuration ) {
		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
		);

		if ( empty( $rule['conditions'] ) ) {
			return $result;
		}

		$conditions = $rule['conditions'];
		$action = isset( $rule['action'] ) ? $rule['action'] : 'exclude';

		$component_a = isset( $conditions['component_a'] ) ? $conditions['component_a'] : '';
		$attribute_a = isset( $conditions['attribute_a'] ) ? $conditions['attribute_a'] : '';
		$component_b = isset( $conditions['component_b'] ) ? $conditions['component_b'] : '';
		$attribute_b = isset( $conditions['attribute_b'] ) ? $conditions['attribute_b'] : '';
		$operator = isset( $conditions['operator'] ) ? $conditions['operator'] : '>';

		// Component IDs are now global (not product-specific).
		// Support legacy format "product_id|component_id" for backward compatibility.
		if ( ! empty( $component_a ) && strpos( $component_a, '|' ) !== false ) {
			$parts = explode( '|', $component_a );
			$component_a = isset( $parts[1] ) ? $parts[1] : $component_a;
		}
		if ( ! empty( $component_b ) && strpos( $component_b, '|' ) !== false ) {
			$parts = explode( '|', $component_b );
			$component_b = isset( $parts[1] ) ? $parts[1] : $component_b;
		}

		// Both components and attributes are required.
		if ( empty( $component_a ) || empty( $attribute_a ) || empty( $component_b ) || empty( $attribute_b ) ) {
			return $result;
		}

		// Both components must have products selected.
		if ( ! isset( $configuration[ $component_a ] ) || ! isset( $configuration[ $component_b ] ) ) {
			return $result;
		}

		$product_a = wc_get_product( $configuration[ $component_a ] );
		$product_b = wc_get_product( $configuration[ $component_b ] );

		if ( ! $product_a || ! $product_b ) {
			return $result;
		}

		// Get attribute values from both products.
		$product_value_a = $this->get_product_numeric_attribute_value( $product_a, $attribute_a );
		$product_value_b = $this->get_product_numeric_attribute_value( $product_b, $attribute_b );

		// If either attribute is missing or not numeric, skip this rule.
		if ( false === $product_value_a || false === $product_value_b ) {
			return $result;
		}

		// Compare the two attribute values directly.
		// If Component A attribute [operator] Component B attribute is true, check action.
		$comparison_result = $this->compare_numeric( $product_value_a, $operator, $product_value_b );

		if ( $comparison_result ) {
			// The comparison condition is met.
			$message = isset( $rule['message'] ) && ! empty( $rule['message'] ) 
				? $rule['message'] 
				: sprintf( 
					__( 'Component A %s (%s) is %s Component B %s (%s). This configuration is incompatible.', 'w2f-pc-configurator' ),
					$attribute_a,
					$product_value_a,
					$operator,
					$attribute_b,
					$product_value_b
				);
			
			// For numeric_attribute rules, show warnings for both "warn" and "exclude" actions.
			// This ensures products are highlighted but remain selectable.
			$result['warnings'][] = $message;
		}

		return $result;
	}

	/**
	 * Compare numeric values.
	 *
	 * @param  float  $value1
	 * @param  string $operator (>=, <=, >, <, ==, !=)
	 * @param  float  $value2
	 * @return boolean
	 */
	private function compare_numeric( $value1, $operator, $value2 ) {
		switch ( $operator ) {
			case '>=':
				return $value1 >= $value2;
			case '<=':
				return $value1 <= $value2;
			case '>':
				return $value1 > $value2;
			case '<':
				return $value1 < $value2;
			case '==':
				return abs( $value1 - $value2 ) < 0.0001; // Float comparison.
			case '!=':
				return abs( $value1 - $value2 ) >= 0.0001;
			default:
				return false;
		}
	}

	/**
	 * Get numeric attribute value from product.
	 *
	 * @param  WC_Product $product
	 * @param  string     $attribute_name
	 * @return float|false Returns false if attribute not found or not numeric.
	 */
	private function get_product_numeric_attribute_value( $product, $attribute_name ) {
		// Try product attributes first.
		$attributes = $product->get_attributes();
		if ( isset( $attributes[ $attribute_name ] ) ) {
			$attribute = $attributes[ $attribute_name ];
			$value = '';
			if ( $attribute->is_taxonomy() ) {
				$terms = wp_get_post_terms( $product->get_id(), $attribute_name );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$value = $terms[0]->name; // Get term name, not slug.
				}
			} else {
				$options = $attribute->get_options();
				$value = ! empty( $options ) ? $options[0] : '';
			}
			// Try to extract numeric value from string (e.g., "800W" -> 800).
			$numeric_value = $this->extract_numeric_value( $value );
			if ( false !== $numeric_value ) {
				return $numeric_value;
			}
		}

		// Try product meta.
		$meta_value = $product->get_meta( $attribute_name, true );
		if ( ! empty( $meta_value ) ) {
			$numeric_value = $this->extract_numeric_value( $meta_value );
			if ( false !== $numeric_value ) {
				return $numeric_value;
			}
		}

		return false;
	}

	/**
	 * Extract numeric value from string (e.g., "800W" -> 800, "850" -> 850).
	 *
	 * @param  mixed $value
	 * @return float|false
	 */
	private function extract_numeric_value( $value ) {
		if ( is_numeric( $value ) ) {
			return floatval( $value );
		}
		if ( is_string( $value ) ) {
			// Extract first number from string.
			if ( preg_match( '/[\d.]+/', $value, $matches ) ) {
				return floatval( $matches[0] );
			}
		}
		return false;
	}

	/**
	 * Get filtered product IDs for a component based on current configuration and rules.
	 *
	 * @param  string $component_id
	 * @param  array  $configuration Current configuration (component_id => product_id)
	 * @param  W2F_PC_Product $configurator_product
	 * @return array Array of product IDs that should be available (empty array means all available).
	 */
	public function get_filtered_products_for_component( $component_id, $configuration, $configurator_product = null ) {
		$component = $configurator_product ? $configurator_product->get_component( $component_id ) : null;
		if ( ! $component ) {
			return array();
		}

		// Get all available products for this component.
		$available_products = $component->get_option_products();
		$available_product_ids = array_keys( $available_products );

		if ( empty( $this->rules ) ) {
			return $available_product_ids;
		}

		$filtered_product_ids = array();

		foreach ( $available_products as $product_id => $product ) {
			// Test if this product would be compatible with current configuration.
			$test_configuration = $configuration;
			$test_configuration[ $component_id ] = $product_id;

			$compatibility_result = $this->check_compatibility( $test_configuration, $configurator_product );

			// Include product if it's valid (warnings are allowed, errors are not).
			if ( $compatibility_result['valid'] ) {
				$filtered_product_ids[] = $product_id;
			}
		}

		return $filtered_product_ids;
	}

	/**
	 * Get warnings for a specific product selection in a component.
	 *
	 * @param  string $component_id
	 * @param  int    $product_id
	 * @param  array  $configuration Current configuration (component_id => product_id)
	 * @param  W2F_PC_Product $configurator_product
	 * @return array Array of warning messages (empty if no warnings).
	 */
	public function get_product_warnings( $component_id, $product_id, $configuration, $configurator_product = null ) {
		$warnings = array();

		if ( empty( $this->rules ) ) {
			return $warnings;
		}

		// Test if this product would have warnings with current configuration.
		$test_configuration = $configuration;
		$test_configuration[ $component_id ] = $product_id;

		// Check category_exclude and numeric_attribute rules specifically for this product.
		// This allows us to mark products as incompatible even when the other component is selected.
		foreach ( $this->rules as $rule_id => $rule ) {
			if ( empty( $rule['is_active'] ) || 'yes' !== $rule['is_active'] ) {
				continue;
			}

			if ( 'category_exclude' === $rule['type'] ) {
				$rule_result = $this->evaluate_category_exclude_rule( $rule, $test_configuration );
				if ( ! empty( $rule_result['warnings'] ) ) {
					$warnings = array_merge( $warnings, $rule_result['warnings'] );
				}
			} elseif ( 'numeric_attribute' === $rule['type'] ) {
				// For numeric_attribute rules, check if this product would trigger a warning.
				// The rule requires both components to be selected, which they are in test_configuration.
				$rule_result = $this->evaluate_numeric_attribute_rule( $rule, $test_configuration );
				if ( ! empty( $rule_result['warnings'] ) ) {
					$warnings = array_merge( $warnings, $rule_result['warnings'] );
				}
			}
		}

		// Also check other rule types via check_compatibility.
		$compatibility_result = $this->check_compatibility( $test_configuration, $configurator_product );

		if ( ! empty( $compatibility_result['warnings'] ) ) {
			// Merge warnings, avoiding duplicates.
			foreach ( $compatibility_result['warnings'] as $warning ) {
				if ( ! in_array( $warning, $warnings, true ) ) {
					$warnings[] = $warning;
				}
			}
		}

		return $warnings;
	}

	/**
	 * Get product attribute value.
	 *
	 * @param  WC_Product $product
	 * @param  string     $attribute_name
	 * @return mixed
	 */
	private function get_product_attribute_value( $product, $attribute_name ) {
		// Try product attributes first.
		$attributes = $product->get_attributes();
		if ( isset( $attributes[ $attribute_name ] ) ) {
			$attribute = $attributes[ $attribute_name ];
			if ( $attribute->is_taxonomy() ) {
				$terms = wp_get_post_terms( $product->get_id(), $attribute_name );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					return $terms[0]->slug;
				}
			} else {
				return $attribute->get_options()[0] ?? '';
			}
		}

		// Try product meta.
		return $product->get_meta( $attribute_name, true );
	}
}

