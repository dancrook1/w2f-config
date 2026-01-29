<?php
/**
 * W2F_PC_Compatibility_Engine class
 *
 * Main compatibility engine that orchestrates Facts, Calculators, Policies, and Adjustments.
 * This replaces the legacy W2F_PC_Compatibility_Manager.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PC Compatibility Engine.
 *
 * @class    W2F_PC_Compatibility_Engine
 * @version  2.0.0
 */
class W2F_PC_Compatibility_Engine {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Compatibility_Engine
	 */
	protected static $_instance = null;

	/**
	 * Attribute Dictionary instance.
	 *
	 * @var W2F_PC_Attribute_Dictionary
	 */
	private $attribute_dictionary;

	/**
	 * Calculator Registry instance.
	 *
	 * @var W2F_PC_Calculator_Registry
	 */
	private $calculator_registry;

	/**
	 * Policy Registry instance.
	 *
	 * @var W2F_PC_Policy_Registry
	 */
	private $policy_registry;

	/**
	 * Adjustment Registry instance.
	 *
	 * @var W2F_PC_Adjustment_Registry
	 */
	private $adjustment_registry;

	/**
	 * Component Type Mapper instance.
	 *
	 * @var W2F_PC_Component_Type_Mapper
	 */
	private $component_mapper;

	/**
	 * Last evaluation trace.
	 *
	 * @var W2F_PC_Evaluation_Trace
	 */
	private $last_trace;

	/**
	 * Main W2F_PC_Compatibility_Engine instance.
	 *
	 * @static
	 * @return W2F_PC_Compatibility_Engine - Main instance
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
		$this->load_dependencies();
		$this->initialize();
	}

	/**
	 * Load required class files.
	 */
	private function load_dependencies() {
		$path = plugin_dir_path( __FILE__ );

		// Core classes.
		require_once $path . 'class-w2f-pc-evaluation-trace.php';
		require_once $path . 'class-w2f-pc-fact-collection.php';
		require_once $path . 'class-w2f-pc-attribute-dictionary.php';
		require_once $path . 'class-w2f-pc-component-type-mapper.php';

		// Calculators.
		require_once $path . 'calculators/class-w2f-pc-calculator-registry.php';
		require_once $path . 'calculators/class-w2f-pc-calculator-base.php';
		require_once $path . 'calculators/class-w2f-pc-calculator-psu-wattage.php';
		require_once $path . 'calculators/class-w2f-pc-calculator-gpu-clearance.php';

		// Policies.
		require_once $path . 'policies/class-w2f-pc-policy-registry.php';
		require_once $path . 'policies/class-w2f-pc-policy-base.php';
		require_once $path . 'policies/class-w2f-pc-policy-match.php';
		require_once $path . 'policies/class-w2f-pc-policy-contains.php';
		require_once $path . 'policies/class-w2f-pc-policy-compare.php';
		require_once $path . 'policies/class-w2f-pc-policy-fit.php';
		require_once $path . 'policies/class-w2f-pc-policy-count-limit.php';
		require_once $path . 'policies/class-w2f-pc-policy-requires-if.php';
		require_once $path . 'policies/class-w2f-pc-policy-warning-range.php';

		// Adjustments.
		require_once $path . 'adjustments/class-w2f-pc-adjustment-registry.php';
		require_once $path . 'adjustments/class-w2f-pc-adjustment.php';
	}

	/**
	 * Initialize the engine components.
	 */
	private function initialize() {
		$this->attribute_dictionary = W2F_PC_Attribute_Dictionary::instance();
		$this->calculator_registry  = W2F_PC_Calculator_Registry::instance();
		$this->policy_registry      = W2F_PC_Policy_Registry::instance();
		$this->adjustment_registry  = W2F_PC_Adjustment_Registry::instance();
		$this->component_mapper     = W2F_PC_Component_Type_Mapper::instance();
	}

	/**
	 * Get the attribute dictionary.
	 *
	 * @return W2F_PC_Attribute_Dictionary
	 */
	public function get_attribute_dictionary() {
		return $this->attribute_dictionary;
	}

	/**
	 * Get the calculator registry.
	 *
	 * @return W2F_PC_Calculator_Registry
	 */
	public function get_calculator_registry() {
		return $this->calculator_registry;
	}

	/**
	 * Get the policy registry.
	 *
	 * @return W2F_PC_Policy_Registry
	 */
	public function get_policy_registry() {
		return $this->policy_registry;
	}

	/**
	 * Get the adjustment registry.
	 *
	 * @return W2F_PC_Adjustment_Registry
	 */
	public function get_adjustment_registry() {
		return $this->adjustment_registry;
	}

	/**
	 * Get the component type mapper.
	 *
	 * @return W2F_PC_Component_Type_Mapper
	 */
	public function get_component_mapper() {
		return $this->component_mapper;
	}

	/**
	 * Get the last evaluation trace.
	 *
	 * @return W2F_PC_Evaluation_Trace|null
	 */
	public function get_last_trace() {
		return $this->last_trace;
	}

	/**
	 * Check compatibility of a build configuration.
	 *
	 * This is the main entry point for validation.
	 *
	 * @param  array $configuration Array of component_id => product_id (or array for multi-select).
	 * @param  W2F_PC_Product|null $configurator_product Optional configurator product context.
	 * @return array {
	 *     @type bool   $valid    Whether the configuration is valid.
	 *     @type array  $errors   Array of blocking error messages.
	 *     @type array  $warnings Array of warning messages.
	 *     @type W2F_PC_Evaluation_Trace $trace Full evaluation trace.
	 * }
	 */
	public function check_compatibility( $configuration, $configurator_product = null ) {
		// Initialize trace.
		$this->last_trace = new W2F_PC_Evaluation_Trace();

		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
			'trace'    => $this->last_trace,
		);

		if ( empty( $configuration ) ) {
			return $result;
		}

		// Step 1: Extract facts from selected products.
		$facts = $this->extract_facts( $configuration, $configurator_product );
		$this->last_trace->set_facts( $facts );

		// Step 2: Apply adjustments to modify derived values.
		$adjustments_result = $this->apply_adjustments( $facts );
		$this->last_trace->set_fired_adjustments( $adjustments_result['fired'] );

		// Step 3: Run calculators to compute derived values.
		$calculator_results = $this->run_calculators( $facts );
		$this->last_trace->set_calculator_results( $calculator_results );

		// Step 4: Evaluate all enabled policies.
		$policy_results = $this->evaluate_policies( $facts, $configuration, $configurator_product );

		foreach ( $policy_results as $policy_result ) {
			$this->last_trace->add_policy_result( $policy_result );

			if ( ! $policy_result['passed'] ) {
				if ( 'block' === $policy_result['severity'] ) {
					$result['valid'] = false;
					$result['errors'][] = $policy_result['message'];
				} else {
					$result['warnings'][] = $policy_result['message'];
				}
			}
		}

		return $result;
	}

	/**
	 * Filter available products for a component based on current configuration.
	 *
	 * @param  string $component_id Component ID to filter products for.
	 * @param  array  $configuration Current configuration (component_id => product_id).
	 * @param  W2F_PC_Product $configurator_product Configurator product.
	 * @return array {
	 *     @type array $compatible_ids   Product IDs that are fully compatible.
	 *     @type array $warning_ids      Product IDs with warnings (keyed by product_id => warning messages).
	 *     @type array $blocked_ids      Product IDs that are blocked (keyed by product_id => block reasons).
	 * }
	 */
	public function filter_products_for_component( $component_id, $configuration, $configurator_product ) {
		$result = array(
			'compatible_ids' => array(),
			'warning_ids'    => array(),
			'blocked_ids'    => array(),
		);

		$component = $configurator_product ? $configurator_product->get_component( $component_id ) : null;
		if ( ! $component ) {
			return $result;
		}

		$available_products = $component->get_option_products();

		foreach ( $available_products as $product_id => $product ) {
			// Test this product against current configuration.
			$test_configuration = $configuration;
			$test_configuration[ $component_id ] = $product_id;

			$compatibility_result = $this->check_compatibility( $test_configuration, $configurator_product );

			if ( $compatibility_result['valid'] && empty( $compatibility_result['warnings'] ) ) {
				$result['compatible_ids'][] = $product_id;
			} elseif ( $compatibility_result['valid'] && ! empty( $compatibility_result['warnings'] ) ) {
				$result['compatible_ids'][] = $product_id;
				$result['warning_ids'][ $product_id ] = $compatibility_result['warnings'];
			} else {
				$result['blocked_ids'][ $product_id ] = $compatibility_result['errors'];
			}
		}

		return $result;
	}

	/**
	 * Get warnings for a specific product in a component.
	 *
	 * @param  string $component_id Component ID.
	 * @param  int    $product_id   Product ID to check.
	 * @param  array  $configuration Current configuration.
	 * @param  W2F_PC_Product $configurator_product Configurator product.
	 * @return array Array of warning messages.
	 */
	public function get_product_warnings( $component_id, $product_id, $configuration, $configurator_product = null ) {
		$test_configuration = $configuration;
		$test_configuration[ $component_id ] = $product_id;

		$result = $this->check_compatibility( $test_configuration, $configurator_product );

		return $result['warnings'];
	}

	/**
	 * Extract facts from configuration products.
	 *
	 * @param  array $configuration Configuration array.
	 * @param  W2F_PC_Product|null $configurator_product Configurator product.
	 * @return W2F_PC_Fact_Collection
	 */
	private function extract_facts( $configuration, $configurator_product = null ) {
		$facts = new W2F_PC_Fact_Collection();

		foreach ( $configuration as $component_id => $product_ids ) {
			// Handle multi-select components.
			$product_ids = is_array( $product_ids ) ? $product_ids : array( $product_ids );

			foreach ( $product_ids as $index => $product_id ) {
				if ( empty( $product_id ) ) {
					continue;
				}

				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				// Determine component type.
				$component_type = $this->component_mapper->get_component_type( $component_id, $product, $configurator_product );

				// Extract facts using attribute dictionary.
				$product_facts = $this->attribute_dictionary->extract_facts_from_product( $product, $component_type );

				// Add facts with component prefix.
				foreach ( $product_facts as $key => $value ) {
					$fact_key = $component_type . '.' . $key;

					// For multi-select, use array of values.
					if ( count( $product_ids ) > 1 ) {
						$existing = $facts->get( $fact_key );
						if ( is_array( $existing ) ) {
							$existing[] = $value;
							$facts->set( $fact_key, $existing );
						} else {
							$facts->set( $fact_key, array( $value ) );
						}
					} else {
						$facts->set( $fact_key, $value );
					}
				}

				// Store product reference.
				$facts->set_product( $component_type, $product_id, $product );
			}
		}

		return $facts;
	}

	/**
	 * Apply adjustments to modify facts/derived values.
	 *
	 * @param  W2F_PC_Fact_Collection $facts Fact collection.
	 * @return array {
	 *     @type array $fired Array of fired adjustment info.
	 * }
	 */
	private function apply_adjustments( $facts ) {
		$fired = array();
		$adjustments = $this->adjustment_registry->get_enabled_adjustments();

		// Sort by priority.
		usort( $adjustments, function( $a, $b ) {
			return $a->get_priority() - $b->get_priority();
		});

		foreach ( $adjustments as $adjustment ) {
			$adjustment_result = $adjustment->apply( $facts );

			if ( $adjustment_result['applied'] ) {
				$fired[] = array(
					'id'          => $adjustment->get_id(),
					'name'        => $adjustment->get_name(),
					'target'      => $adjustment->get_target_key(),
					'delta'       => $adjustment_result['delta'],
					'before'      => $adjustment_result['before'],
					'after'       => $adjustment_result['after'],
					'reason'      => $adjustment->get_reason(),
				);
			}
		}

		return array( 'fired' => $fired );
	}

	/**
	 * Run all calculators to compute derived values.
	 *
	 * @param  W2F_PC_Fact_Collection $facts Fact collection.
	 * @return array Calculator results.
	 */
	private function run_calculators( $facts ) {
		$results = array();
		$calculators = $this->calculator_registry->get_enabled_calculators();

		foreach ( $calculators as $calculator ) {
			$calc_result = $calculator->calculate( $facts );

			// Store derived value in facts.
			$facts->set( $calculator->get_output_key(), $calc_result['value'] );

			$results[] = array(
				'id'         => $calculator->get_id(),
				'name'       => $calculator->get_name(),
				'output_key' => $calculator->get_output_key(),
				'value'      => $calc_result['value'],
				'breakdown'  => $calc_result['breakdown'],
			);
		}

		return $results;
	}

	/**
	 * Evaluate all enabled policies.
	 *
	 * @param  W2F_PC_Fact_Collection $facts Fact collection.
	 * @param  array $configuration Configuration array.
	 * @param  W2F_PC_Product|null $configurator_product Configurator product.
	 * @return array Policy evaluation results.
	 */
	private function evaluate_policies( $facts, $configuration, $configurator_product = null ) {
		$results = array();
		$policies = $this->policy_registry->get_enabled_policies();

		// Sort by priority.
		usort( $policies, function( $a, $b ) {
			return $a->get_priority() - $b->get_priority();
		});

		foreach ( $policies as $policy ) {
			// Check if policy is applicable.
			if ( ! $policy->is_applicable( $facts, $configuration ) ) {
				continue;
			}

			$policy_result = $policy->evaluate( $facts, $configuration, $configurator_product );

			$results[] = array(
				'id'          => $policy->get_id(),
				'name'        => $policy->get_name(),
				'type'        => $policy->get_type(),
				'passed'      => $policy_result['passed'],
				'severity'    => $policy->get_severity(),
				'message'     => $policy_result['message'],
				'details'     => $policy_result['details'],
				'compared'    => $policy_result['compared'] ?? array(),
			);
		}

		return $results;
	}

	/**
	 * Simulate a build for the admin simulator.
	 *
	 * @param  array $configuration Configuration to simulate.
	 * @param  int   $configurator_id Optional configurator product ID.
	 * @return array Full simulation results with trace.
	 */
	public function simulate( $configuration, $configurator_id = 0 ) {
		$configurator_product = null;
		if ( $configurator_id ) {
			$configurator_product = w2f_pc_get_configurator_product( $configurator_id );
		}

		$result = $this->check_compatibility( $configuration, $configurator_product );

		return array(
			'valid'            => $result['valid'],
			'errors'           => $result['errors'],
			'warnings'         => $result['warnings'],
			'facts'            => $this->last_trace->get_facts()->to_array(),
			'adjustments'      => $this->last_trace->get_fired_adjustments(),
			'calculators'      => $this->last_trace->get_calculator_results(),
			'policies'         => $this->last_trace->get_policy_results(),
			'derived_values'   => $this->last_trace->get_derived_values(),
			'human_readable'   => $this->last_trace->get_human_readable_summary(),
		);
	}
}
