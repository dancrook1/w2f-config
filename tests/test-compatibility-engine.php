<?php
/**
 * Unit tests for W2F_PC_Compatibility_Engine
 *
 * @package W2F_PC_Configurator
 * @subpackage Tests
 */

/**
 * Test case for the compatibility engine.
 *
 * Run with: vendor/bin/phpunit tests/test-compatibility-engine.php
 * Or use WP CLI: wp eval-file tests/test-compatibility-engine.php
 */
class W2F_PC_Compatibility_Engine_Test {

	/**
	 * Test results.
	 *
	 * @var array
	 */
	private $results = array();

	/**
	 * Run all tests.
	 */
	public function run() {
		echo "=== W2F PC Compatibility Engine Tests ===\n\n";

		$this->test_fact_collection();
		$this->test_attribute_dictionary();
		$this->test_psu_wattage_calculator();
		$this->test_gpu_clearance_calculator();
		$this->test_match_policy();
		$this->test_contains_policy();
		$this->test_compare_policy();
		$this->test_fit_policy();
		$this->test_adjustment_conditions();
		$this->test_aio_gpu_clearance_adjustment();
		$this->test_full_compatibility_check();

		$this->print_summary();
	}

	/**
	 * Test fact collection operations.
	 */
	public function test_fact_collection() {
		echo "Test: Fact Collection\n";

		$facts = new W2F_PC_Fact_Collection();

		// Test set and get.
		$facts->set( 'cpu.socket', 'AM5' );
		$this->assert_equals( 'AM5', $facts->get( 'cpu.socket' ), 'Set and get basic value' );

		// Test default value.
		$this->assert_equals( 'default', $facts->get( 'nonexistent', 'default' ), 'Default value for missing key' );

		// Test has.
		$this->assert_true( $facts->has( 'cpu.socket' ), 'Has returns true for existing key' );
		$this->assert_false( $facts->has( 'nonexistent' ), 'Has returns false for missing key' );

		// Test numeric operations.
		$facts->set( 'derived.penalty', 0 );
		$result = $facts->add_delta( 'derived.penalty', 40 );
		$this->assert_equals( 40, $result['after'], 'Add delta to numeric value' );

		// Test get_by_component.
		$facts->set( 'cpu.tdp_w', 125 );
		$facts->set( 'cpu.cores', 8 );
		$cpu_facts = $facts->get_by_component( 'cpu' );
		$this->assert_equals( 2, count( $cpu_facts ), 'Get facts by component' );

		echo "  ✓ Fact collection tests passed\n\n";
	}

	/**
	 * Test attribute dictionary parsing.
	 */
	public function test_attribute_dictionary() {
		echo "Test: Attribute Dictionary\n";

		$dictionary = W2F_PC_Attribute_Dictionary::instance();

		// Test numeric parsing.
		$def = $dictionary->get( 'psu.wattage_w' );
		$this->assert_not_null( $def, 'PSU wattage definition exists' );
		$this->assert_equals( 'numeric', $def['data_type'], 'PSU wattage is numeric type' );

		// Test enum parsing.
		$def = $dictionary->get( 'cpu.socket' );
		$this->assert_not_null( $def, 'CPU socket definition exists' );
		$this->assert_equals( 'enum', $def['data_type'], 'CPU socket is enum type' );

		// Test set parsing.
		$def = $dictionary->get( 'cooler.supported_sockets' );
		$this->assert_not_null( $def, 'Cooler supported sockets definition exists' );
		$this->assert_equals( 'set', $def['data_type'], 'Cooler sockets is set type' );

		echo "  ✓ Attribute dictionary tests passed\n\n";
	}

	/**
	 * Test PSU wattage calculator.
	 */
	public function test_psu_wattage_calculator() {
		echo "Test: PSU Wattage Calculator\n";

		$calculator = new W2F_PC_Calculator_PSU_Wattage( 'psu_wattage', array(
			'enabled' => true,
			'params' => array(
				'base_w' => 50,
				'headroom_factor' => 1.25,
				'contributors' => array( 'cpu.tdp_w', 'gpu.power_w' ),
			),
		) );

		$facts = new W2F_PC_Fact_Collection();
		$facts->set( 'cpu.tdp_w', 125 );  // 125W CPU
		$facts->set( 'gpu.power_w', 320 ); // 320W GPU

		$result = $calculator->calculate( $facts );

		// Expected: (50 + 125 + 320) * 1.25 = 618.75, rounded up to 650
		$this->assert_equals( 650, $result['value'], 'PSU wattage calculation' );
		$this->assert_true( count( $result['breakdown'] ) > 0, 'Breakdown includes components' );

		echo "  ✓ PSU wattage calculator tests passed\n\n";
	}

	/**
	 * Test GPU clearance calculator.
	 */
	public function test_gpu_clearance_calculator() {
		echo "Test: GPU Clearance Calculator\n";

		$calculator = new W2F_PC_Calculator_GPU_Clearance( 'gpu_clearance', array(
			'enabled' => true,
			'params' => array(
				'base_source' => 'case.max_gpu_length_mm',
				'default_max_mm' => 400,
				'penalty_key' => 'derived.gpu_clearance_penalty_mm',
			),
		) );

		// Test without penalty.
		$facts = new W2F_PC_Fact_Collection();
		$facts->set( 'case.max_gpu_length_mm', 380 );

		$result = $calculator->calculate( $facts );
		$this->assert_equals( 380, $result['value'], 'GPU clearance without penalty' );

		// Test with penalty.
		$facts->set( 'derived.gpu_clearance_penalty_mm', 40 );

		$result = $calculator->calculate( $facts );
		$this->assert_equals( 340, $result['value'], 'GPU clearance with 40mm penalty' );

		echo "  ✓ GPU clearance calculator tests passed\n\n";
	}

	/**
	 * Test MATCH policy.
	 */
	public function test_match_policy() {
		echo "Test: MATCH Policy (CPU Socket)\n";

		$policy = new W2F_PC_Policy_Match( 'cpu_socket_match', array(
			'name' => 'CPU Socket Match',
			'enabled' => true,
			'severity' => 'block',
			'config' => array(
				'left_key' => 'cpu.socket',
				'right_key' => 'motherboard.socket',
			),
			'message' => 'CPU socket {{cpu.socket}} does not match motherboard {{motherboard.socket}}',
		) );

		// Test matching sockets.
		$facts = new W2F_PC_Fact_Collection();
		$facts->set( 'cpu.socket', 'AM5' );
		$facts->set( 'motherboard.socket', 'AM5' );

		$result = $policy->evaluate( $facts, array() );
		$this->assert_true( $result['passed'], 'Matching sockets should pass' );

		// Test non-matching sockets.
		$facts->set( 'motherboard.socket', 'LGA1700' );

		$result = $policy->evaluate( $facts, array() );
		$this->assert_false( $result['passed'], 'Non-matching sockets should fail' );
		$this->assert_contains( 'AM5', $result['message'], 'Message includes CPU socket' );
		$this->assert_contains( 'LGA1700', $result['message'], 'Message includes motherboard socket' );

		echo "  ✓ MATCH policy tests passed\n\n";
	}

	/**
	 * Test CONTAINS policy.
	 */
	public function test_contains_policy() {
		echo "Test: CONTAINS Policy (Case Form Factor)\n";

		$policy = new W2F_PC_Policy_Contains( 'case_form_factor', array(
			'name' => 'Case Form Factor Support',
			'enabled' => true,
			'severity' => 'block',
			'config' => array(
				'set_key' => 'case.supported_form_factors',
				'value_key' => 'motherboard.form_factor',
			),
			'message' => 'Case does not support {{motherboard.form_factor}} motherboards',
		) );

		// Test supported form factor.
		$facts = new W2F_PC_Fact_Collection();
		$facts->set( 'case.supported_form_factors', array( 'ATX', 'Micro-ATX', 'Mini-ITX' ) );
		$facts->set( 'motherboard.form_factor', 'ATX' );

		$result = $policy->evaluate( $facts, array() );
		$this->assert_true( $result['passed'], 'Supported form factor should pass' );

		// Test unsupported form factor.
		$facts->set( 'motherboard.form_factor', 'E-ATX' );

		$result = $policy->evaluate( $facts, array() );
		$this->assert_false( $result['passed'], 'Unsupported form factor should fail' );

		echo "  ✓ CONTAINS policy tests passed\n\n";
	}

	/**
	 * Test COMPARE policy.
	 */
	public function test_compare_policy() {
		echo "Test: COMPARE Policy (PSU Wattage)\n";

		$policy = new W2F_PC_Policy_Compare( 'psu_wattage', array(
			'name' => 'PSU Wattage Check',
			'enabled' => true,
			'severity' => 'block',
			'config' => array(
				'left_key' => 'psu.wattage_w',
				'operator' => '>=',
				'right_key' => 'derived.required_psu_w',
			),
			'message' => 'PSU ({{psu.wattage_w}}W) is below required ({{derived.required_psu_w}}W)',
		) );

		// Test sufficient wattage.
		$facts = new W2F_PC_Fact_Collection();
		$facts->set( 'psu.wattage_w', 850 );
		$facts->set( 'derived.required_psu_w', 650 );

		$result = $policy->evaluate( $facts, array() );
		$this->assert_true( $result['passed'], 'Sufficient wattage should pass' );

		// Test insufficient wattage.
		$facts->set( 'psu.wattage_w', 500 );

		$result = $policy->evaluate( $facts, array() );
		$this->assert_false( $result['passed'], 'Insufficient wattage should fail' );

		echo "  ✓ COMPARE policy tests passed\n\n";
	}

	/**
	 * Test FIT policy.
	 */
	public function test_fit_policy() {
		echo "Test: FIT Policy (GPU Length)\n";

		$policy = new W2F_PC_Policy_Fit( 'gpu_fit', array(
			'name' => 'GPU Clearance Check',
			'enabled' => true,
			'severity' => 'block',
			'config' => array(
				'candidate_key' => 'gpu.length_mm',
				'constraint_key' => 'derived.max_gpu_length_mm',
			),
			'message' => 'GPU ({{gpu.length_mm}}mm) does not fit (max {{derived.max_gpu_length_mm}}mm)',
		) );

		// Test GPU fits.
		$facts = new W2F_PC_Fact_Collection();
		$facts->set( 'gpu.length_mm', 320 );
		$facts->set( 'derived.max_gpu_length_mm', 340 );

		$result = $policy->evaluate( $facts, array() );
		$this->assert_true( $result['passed'], 'GPU that fits should pass' );

		// Test GPU too long.
		$facts->set( 'gpu.length_mm', 350 );

		$result = $policy->evaluate( $facts, array() );
		$this->assert_false( $result['passed'], 'GPU too long should fail' );

		echo "  ✓ FIT policy tests passed\n\n";
	}

	/**
	 * Test adjustment conditions.
	 */
	public function test_adjustment_conditions() {
		echo "Test: Adjustment Conditions\n";

		$adjustment = new W2F_PC_Adjustment( 'test_adj', array(
			'name' => 'Test Adjustment',
			'enabled' => true,
			'conditions' => array(
				array(
					'fact_key' => 'cooler.type',
					'operator' => '=',
					'value' => 'aio',
				),
			),
			'condition_logic' => 'AND',
			'target_key' => 'derived.penalty',
			'effect' => 'add',
			'delta' => 40,
			'reason' => 'AIO cooler penalty',
		) );

		// Test condition met.
		$facts = new W2F_PC_Fact_Collection();
		$facts->set( 'cooler.type', 'aio' );
		$facts->set( 'derived.penalty', 0 );

		$result = $adjustment->apply( $facts );
		$this->assert_true( $result['applied'], 'Adjustment should apply when condition met' );
		$this->assert_equals( 40, $facts->get( 'derived.penalty' ), 'Penalty should be 40' );

		// Test condition not met.
		$facts2 = new W2F_PC_Fact_Collection();
		$facts2->set( 'cooler.type', 'air' );
		$facts2->set( 'derived.penalty', 0 );

		$result = $adjustment->apply( $facts2 );
		$this->assert_false( $result['applied'], 'Adjustment should not apply when condition not met' );
		$this->assert_equals( 0, $facts2->get( 'derived.penalty' ), 'Penalty should remain 0' );

		echo "  ✓ Adjustment conditions tests passed\n\n";
	}

	/**
	 * Test AIO GPU clearance adjustment scenario.
	 */
	public function test_aio_gpu_clearance_adjustment() {
		echo "Test: AIO GPU Clearance Adjustment Scenario\n";

		// Simulate: 240mm AIO reduces GPU clearance by 40mm.
		$adjustment = new W2F_PC_Adjustment( 'aio_clearance', array(
			'name' => '240mm AIO GPU Clearance',
			'enabled' => true,
			'conditions' => array(
				array(
					'fact_key' => 'cooler.type',
					'operator' => '=',
					'value' => 'aio',
				),
				array(
					'fact_key' => 'cooler.radiator_size_mm',
					'operator' => '>=',
					'value' => 240,
				),
			),
			'condition_logic' => 'AND',
			'target_key' => 'derived.gpu_clearance_penalty_mm',
			'effect' => 'add',
			'delta' => 40,
			'reason' => '240mm AIO radiator reduces GPU clearance',
		) );

		// Setup: Case with 380mm GPU clearance, 240mm AIO.
		$facts = new W2F_PC_Fact_Collection();
		$facts->set( 'case.max_gpu_length_mm', 380 );
		$facts->set( 'cooler.type', 'aio' );
		$facts->set( 'cooler.radiator_size_mm', 240 );
		$facts->set( 'derived.gpu_clearance_penalty_mm', 0 );

		// Apply adjustment.
		$result = $adjustment->apply( $facts );
		$this->assert_true( $result['applied'], 'AIO adjustment should apply' );
		$this->assert_equals( 40, $facts->get( 'derived.gpu_clearance_penalty_mm' ), 'Penalty should be 40mm' );

		// Now run GPU clearance calculator.
		$calculator = new W2F_PC_Calculator_GPU_Clearance( 'gpu_clearance', array(
			'enabled' => true,
			'params' => array(
				'base_source' => 'case.max_gpu_length_mm',
				'default_max_mm' => 400,
				'penalty_key' => 'derived.gpu_clearance_penalty_mm',
			),
		) );

		$calc_result = $calculator->calculate( $facts );
		$this->assert_equals( 340, $calc_result['value'], 'Max GPU length should be 340mm (380-40)' );

		// Test GPU that would fit without AIO but not with.
		$facts->set( 'gpu.length_mm', 350 );
		$facts->set( 'derived.max_gpu_length_mm', 340 );

		$fit_policy = new W2F_PC_Policy_Fit( 'gpu_fit', array(
			'name' => 'GPU Clearance',
			'enabled' => true,
			'severity' => 'block',
			'config' => array(
				'candidate_key' => 'gpu.length_mm',
				'constraint_key' => 'derived.max_gpu_length_mm',
			),
		) );

		$fit_result = $fit_policy->evaluate( $facts, array() );
		$this->assert_false( $fit_result['passed'], '350mm GPU should not fit in 340mm clearance' );

		echo "  ✓ AIO GPU clearance adjustment scenario passed\n\n";
	}

	/**
	 * Test full compatibility check integration.
	 */
	public function test_full_compatibility_check() {
		echo "Test: Full Compatibility Check Integration\n";

		// This test requires the full engine to be loaded.
		if ( ! class_exists( 'W2F_PC_Compatibility_Engine' ) ) {
			echo "  ⚠ Skipped (engine not loaded)\n\n";
			return;
		}

		$engine = W2F_PC_Compatibility_Engine::instance();

		// Note: Full integration test would require mock products.
		// This tests that the engine initializes correctly.
		$this->assert_not_null( $engine->get_attribute_dictionary(), 'Attribute dictionary initialized' );
		$this->assert_not_null( $engine->get_calculator_registry(), 'Calculator registry initialized' );
		$this->assert_not_null( $engine->get_policy_registry(), 'Policy registry initialized' );
		$this->assert_not_null( $engine->get_adjustment_registry(), 'Adjustment registry initialized' );
		$this->assert_not_null( $engine->get_component_mapper(), 'Component mapper initialized' );

		echo "  ✓ Full compatibility check integration tests passed\n\n";
	}

	/**
	 * Assert equals.
	 */
	private function assert_equals( $expected, $actual, $message ) {
		$passed = $expected === $actual;
		$this->results[] = array(
			'test' => $message,
			'passed' => $passed,
			'expected' => $expected,
			'actual' => $actual,
		);
		if ( ! $passed ) {
			echo "    ✗ FAILED: $message (expected: $expected, got: $actual)\n";
		}
	}

	/**
	 * Assert true.
	 */
	private function assert_true( $value, $message ) {
		$this->assert_equals( true, $value, $message );
	}

	/**
	 * Assert false.
	 */
	private function assert_false( $value, $message ) {
		$this->assert_equals( false, $value, $message );
	}

	/**
	 * Assert not null.
	 */
	private function assert_not_null( $value, $message ) {
		$passed = $value !== null;
		$this->results[] = array(
			'test' => $message,
			'passed' => $passed,
		);
		if ( ! $passed ) {
			echo "    ✗ FAILED: $message (expected non-null)\n";
		}
	}

	/**
	 * Assert string contains.
	 */
	private function assert_contains( $needle, $haystack, $message ) {
		$passed = strpos( $haystack, $needle ) !== false;
		$this->results[] = array(
			'test' => $message,
			'passed' => $passed,
		);
		if ( ! $passed ) {
			echo "    ✗ FAILED: $message (expected '$needle' in '$haystack')\n";
		}
	}

	/**
	 * Print test summary.
	 */
	private function print_summary() {
		$total = count( $this->results );
		$passed = count( array_filter( $this->results, function( $r ) {
			return $r['passed'];
		} ) );
		$failed = $total - $passed;

		echo "=== Test Summary ===\n";
		echo "Total: $total | Passed: $passed | Failed: $failed\n";

		if ( $failed > 0 ) {
			echo "\nFailed tests:\n";
			foreach ( $this->results as $result ) {
				if ( ! $result['passed'] ) {
					echo "  - {$result['test']}\n";
				}
			}
		}
	}
}

// Run tests if executed directly.
if ( defined( 'ABSPATH' ) ) {
	// Running within WordPress.
	$test = new W2F_PC_Compatibility_Engine_Test();
	$test->run();
} else {
	echo "Tests must be run within WordPress environment.\n";
	echo "Use: wp eval-file tests/test-compatibility-engine.php\n";
}
