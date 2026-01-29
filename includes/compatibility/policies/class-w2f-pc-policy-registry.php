<?php
/**
 * W2F_PC_Policy_Registry class
 *
 * Manages policy instances and their configuration.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Policy Registry - manages all compatibility policies.
 *
 * @class    W2F_PC_Policy_Registry
 * @version  2.0.0
 */
class W2F_PC_Policy_Registry {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Policy_Registry
	 */
	protected static $_instance = null;

	/**
	 * Option name for storing policies.
	 */
	const OPTION_NAME = 'w2f_pc_compatibility_policies';

	/**
	 * Available policy types.
	 *
	 * @var array
	 */
	private $policy_types = array();

	/**
	 * Policy instances.
	 *
	 * @var array
	 */
	private $policies = array();

	/**
	 * Saved policy configurations.
	 *
	 * @var array
	 */
	private $saved_policies = array();

	/**
	 * Main instance.
	 *
	 * @return W2F_PC_Policy_Registry
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
		$this->register_policy_types();
		$this->load_policies();
	}

	/**
	 * Register available policy types.
	 */
	private function register_policy_types() {
		$this->policy_types = array(
			'match' => array(
				'class' => 'W2F_PC_Policy_Match',
				'name' => __( 'Match', 'w2f-pc-configurator' ),
				'description' => __( 'Checks if two enum values are equal (e.g., CPU socket matches motherboard socket).', 'w2f-pc-configurator' ),
				'icon' => 'dashicons-yes',
			),
			'contains' => array(
				'class' => 'W2F_PC_Policy_Contains',
				'name' => __( 'Contains', 'w2f-pc-configurator' ),
				'description' => __( 'Checks if a set contains a value (e.g., case supports motherboard form factor).', 'w2f-pc-configurator' ),
				'icon' => 'dashicons-list-view',
			),
			'compare' => array(
				'class' => 'W2F_PC_Policy_Compare',
				'name' => __( 'Compare', 'w2f-pc-configurator' ),
				'description' => __( 'Compares numeric values (e.g., PSU wattage >= required wattage).', 'w2f-pc-configurator' ),
				'icon' => 'dashicons-chart-bar',
			),
			'fit' => array(
				'class' => 'W2F_PC_Policy_Fit',
				'name' => __( 'Fit', 'w2f-pc-configurator' ),
				'description' => __( 'Checks if a component fits within constraints (e.g., GPU fits in case).', 'w2f-pc-configurator' ),
				'icon' => 'dashicons-image-crop',
			),
			'count_limit' => array(
				'class' => 'W2F_PC_Policy_Count_Limit',
				'name' => __( 'Count Limit', 'w2f-pc-configurator' ),
				'description' => __( 'Limits the count of items meeting a condition (e.g., NVMe drives <= M.2 slots).', 'w2f-pc-configurator' ),
				'icon' => 'dashicons-admin-generic',
			),
			'requires_if' => array(
				'class' => 'W2F_PC_Policy_Requires_If',
				'name' => __( 'Requires If', 'w2f-pc-configurator' ),
				'description' => __( 'Requires a component if another is selected (e.g., GPU requires PSU).', 'w2f-pc-configurator' ),
				'icon' => 'dashicons-admin-links',
			),
			'warning_range' => array(
				'class' => 'W2F_PC_Policy_Warning_Range',
				'name' => __( 'Warning Range', 'w2f-pc-configurator' ),
				'description' => __( 'Warns when a value is near a threshold (e.g., PSU within 5% of required).', 'w2f-pc-configurator' ),
				'icon' => 'dashicons-warning',
			),
		);
	}

	/**
	 * Load saved policies from database.
	 */
	private function load_policies() {
		$this->saved_policies = get_option( self::OPTION_NAME, array() );

		if ( empty( $this->saved_policies ) ) {
			$this->saved_policies = $this->get_default_policies();
			update_option( self::OPTION_NAME, $this->saved_policies );
		}

		// Instantiate policies.
		foreach ( $this->saved_policies as $id => $config ) {
			$this->instantiate_policy( $id, $config );
		}
	}

	/**
	 * Get default policies for common PC compatibility.
	 *
	 * @return array
	 */
	public function get_default_policies() {
		return array(
			'cpu_socket_match' => array(
				'type' => 'match',
				'name' => __( 'CPU Socket Compatibility', 'w2f-pc-configurator' ),
				'description' => __( 'Ensures CPU socket matches motherboard socket.', 'w2f-pc-configurator' ),
				'enabled' => true,
				'priority' => 10,
				'severity' => 'block',
				'config' => array(
					'left_key' => 'cpu.socket',
					'right_key' => 'motherboard.socket',
				),
				'message' => __( 'CPU socket ({{cpu.socket}}) is not compatible with motherboard socket ({{motherboard.socket}}).', 'w2f-pc-configurator' ),
			),
			'ram_type_match' => array(
				'type' => 'match',
				'name' => __( 'RAM Type Compatibility', 'w2f-pc-configurator' ),
				'description' => __( 'Ensures RAM type matches motherboard RAM type.', 'w2f-pc-configurator' ),
				'enabled' => true,
				'priority' => 20,
				'severity' => 'block',
				'config' => array(
					'left_key' => 'ram.type',
					'right_key' => 'motherboard.ram_type',
				),
				'message' => __( 'RAM type ({{ram.type}}) is not compatible with motherboard ({{motherboard.ram_type}}).', 'w2f-pc-configurator' ),
			),
			'case_form_factor' => array(
				'type' => 'contains',
				'name' => __( 'Case Form Factor Support', 'w2f-pc-configurator' ),
				'description' => __( 'Ensures case supports motherboard form factor.', 'w2f-pc-configurator' ),
				'enabled' => true,
				'priority' => 30,
				'severity' => 'block',
				'config' => array(
					'set_key' => 'case.supported_form_factors',
					'value_key' => 'motherboard.form_factor',
				),
				'message' => __( 'Case does not support {{motherboard.form_factor}} motherboards.', 'w2f-pc-configurator' ),
			),
			'cooler_socket_support' => array(
				'type' => 'contains',
				'name' => __( 'Cooler Socket Support', 'w2f-pc-configurator' ),
				'description' => __( 'Ensures cooler supports CPU socket.', 'w2f-pc-configurator' ),
				'enabled' => true,
				'priority' => 40,
				'severity' => 'block',
				'config' => array(
					'set_key' => 'cooler.supported_sockets',
					'value_key' => 'cpu.socket',
				),
				'message' => __( 'Cooler does not support {{cpu.socket}} socket.', 'w2f-pc-configurator' ),
			),
			'psu_wattage' => array(
				'type' => 'compare',
				'name' => __( 'PSU Wattage Check', 'w2f-pc-configurator' ),
				'description' => __( 'Ensures PSU provides enough wattage.', 'w2f-pc-configurator' ),
				'enabled' => true,
				'priority' => 50,
				'severity' => 'block',
				'config' => array(
					'left_key' => 'psu.wattage_w',
					'operator' => '>=',
					'right_key' => 'derived.required_psu_w',
				),
				'message' => __( 'PSU wattage ({{psu.wattage_w}}W) is below recommended ({{derived.required_psu_w}}W).', 'w2f-pc-configurator' ),
			),
			'gpu_clearance' => array(
				'type' => 'fit',
				'name' => __( 'GPU Clearance Check', 'w2f-pc-configurator' ),
				'description' => __( 'Ensures GPU fits in the case.', 'w2f-pc-configurator' ),
				'enabled' => true,
				'priority' => 60,
				'severity' => 'block',
				'config' => array(
					'candidate_key' => 'gpu.length_mm',
					'constraint_key' => 'derived.max_gpu_length_mm',
				),
				'message' => __( 'GPU ({{gpu.length_mm}}mm) is too long for case (max {{derived.max_gpu_length_mm}}mm).', 'w2f-pc-configurator' ),
			),
			'nvme_slot_limit' => array(
				'type' => 'count_limit',
				'name' => __( 'NVMe Slot Limit', 'w2f-pc-configurator' ),
				'description' => __( 'Limits NVMe drives to available M.2 slots.', 'w2f-pc-configurator' ),
				'enabled' => true,
				'priority' => 70,
				'severity' => 'block',
				'config' => array(
					'count_component' => 'storage',
					'count_where_key' => 'interface',
					'count_where_value' => 'NVMe',
					'limit_key' => 'motherboard.m2_slots',
				),
				'message' => __( 'Too many NVMe drives selected ({{count}} selected, only {{limit}} M.2 slots available).', 'w2f-pc-configurator' ),
			),
			'psu_headroom_warning' => array(
				'type' => 'warning_range',
				'name' => __( 'PSU Headroom Warning', 'w2f-pc-configurator' ),
				'description' => __( 'Warns when PSU wattage is close to minimum required.', 'w2f-pc-configurator' ),
				'enabled' => true,
				'priority' => 100,
				'severity' => 'warn',
				'config' => array(
					'value_key' => 'psu.wattage_w',
					'threshold_key' => 'derived.required_psu_w',
					'margin_percent' => 10,
				),
				'message' => __( 'PSU wattage ({{psu.wattage_w}}W) is within 10% of recommended ({{derived.required_psu_w}}W). Consider a higher wattage PSU for headroom.', 'w2f-pc-configurator' ),
			),
		);
	}

	/**
	 * Instantiate a policy from config.
	 *
	 * @param string $id     Policy ID.
	 * @param array  $config Policy configuration.
	 */
	private function instantiate_policy( $id, $config ) {
		$type = isset( $config['type'] ) ? $config['type'] : '';

		if ( ! isset( $this->policy_types[ $type ] ) ) {
			return;
		}

		$class = $this->policy_types[ $type ]['class'];

		if ( class_exists( $class ) ) {
			$this->policies[ $id ] = new $class( $id, $config );
		}
	}

	/**
	 * Get all policy types.
	 *
	 * @return array
	 */
	public function get_policy_types() {
		return $this->policy_types;
	}

	/**
	 * Get all policies.
	 *
	 * @return array
	 */
	public function get_all() {
		return $this->policies;
	}

	/**
	 * Get all saved policy configurations.
	 *
	 * @return array
	 */
	public function get_all_configs() {
		return $this->saved_policies;
	}

	/**
	 * Get enabled policies.
	 *
	 * @return array
	 */
	public function get_enabled_policies() {
		$enabled = array();

		foreach ( $this->policies as $id => $policy ) {
			if ( $policy->is_enabled() ) {
				$enabled[ $id ] = $policy;
			}
		}

		return $enabled;
	}

	/**
	 * Get a single policy.
	 *
	 * @param  string $id Policy ID.
	 * @return W2F_PC_Policy_Base|null
	 */
	public function get( $id ) {
		return isset( $this->policies[ $id ] ) ? $this->policies[ $id ] : null;
	}

	/**
	 * Get policy configuration.
	 *
	 * @param  string $id Policy ID.
	 * @return array|null
	 */
	public function get_config( $id ) {
		return isset( $this->saved_policies[ $id ] ) ? $this->saved_policies[ $id ] : null;
	}

	/**
	 * Save a policy.
	 *
	 * @param string $id     Policy ID.
	 * @param array  $config Policy configuration.
	 */
	public function save( $id, $config ) {
		$this->saved_policies[ $id ] = $config;
		update_option( self::OPTION_NAME, $this->saved_policies );
		$this->instantiate_policy( $id, $config );
	}

	/**
	 * Delete a policy.
	 *
	 * @param string $id Policy ID.
	 */
	public function delete( $id ) {
		if ( isset( $this->saved_policies[ $id ] ) ) {
			unset( $this->saved_policies[ $id ] );
			unset( $this->policies[ $id ] );
			update_option( self::OPTION_NAME, $this->saved_policies );
		}
	}

	/**
	 * Save all policies.
	 *
	 * @param array $policies All policy configurations.
	 */
	public function save_all( $policies ) {
		$this->saved_policies = $policies;
		update_option( self::OPTION_NAME, $this->saved_policies );

		// Reinitialize.
		$this->policies = array();
		foreach ( $this->saved_policies as $id => $config ) {
			$this->instantiate_policy( $id, $config );
		}
	}

	/**
	 * Reset to defaults.
	 */
	public function reset_to_defaults() {
		$this->saved_policies = $this->get_default_policies();
		update_option( self::OPTION_NAME, $this->saved_policies );

		$this->policies = array();
		foreach ( $this->saved_policies as $id => $config ) {
			$this->instantiate_policy( $id, $config );
		}
	}

	/**
	 * Create a new policy with a unique ID.
	 *
	 * @param  string $type   Policy type.
	 * @param  string $name   Policy name.
	 * @param  array  $config Policy configuration.
	 * @return string New policy ID.
	 */
	public function create( $type, $name, $config = array() ) {
		$id = sanitize_title( $name ) . '_' . substr( md5( uniqid() ), 0, 6 );

		$full_config = array_merge( array(
			'type' => $type,
			'name' => $name,
			'enabled' => true,
			'priority' => 50,
			'severity' => 'block',
			'config' => array(),
			'message' => '',
		), $config );

		$this->save( $id, $full_config );

		return $id;
	}
}
