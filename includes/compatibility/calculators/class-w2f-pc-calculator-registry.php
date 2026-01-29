<?php
/**
 * W2F_PC_Calculator_Registry class
 *
 * Manages calculator instances and their configuration.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculator Registry - manages all calculators.
 *
 * @class    W2F_PC_Calculator_Registry
 * @version  2.0.0
 */
class W2F_PC_Calculator_Registry {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Calculator_Registry
	 */
	protected static $_instance = null;

	/**
	 * Option name for storing calculator configurations.
	 */
	const OPTION_NAME = 'w2f_pc_calculator_configs';

	/**
	 * Registered calculator types (class names).
	 *
	 * @var array
	 */
	private $calculator_types = array();

	/**
	 * Calculator instances.
	 *
	 * @var array
	 */
	private $calculators = array();

	/**
	 * Calculator configurations.
	 *
	 * @var array
	 */
	private $configs = array();

	/**
	 * Main instance.
	 *
	 * @return W2F_PC_Calculator_Registry
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
		$this->register_default_calculators();
		$this->load_configs();
		$this->initialize_calculators();
	}

	/**
	 * Register default calculator types.
	 */
	private function register_default_calculators() {
		$this->calculator_types = array(
			'psu_wattage' => array(
				'class' => 'W2F_PC_Calculator_PSU_Wattage',
				'name' => __( 'Required PSU Wattage', 'w2f-pc-configurator' ),
				'description' => __( 'Calculates minimum PSU wattage based on component power consumption.', 'w2f-pc-configurator' ),
			),
			'gpu_clearance' => array(
				'class' => 'W2F_PC_Calculator_GPU_Clearance',
				'name' => __( 'GPU Clearance', 'w2f-pc-configurator' ),
				'description' => __( 'Calculates maximum GPU length based on case and adjustments.', 'w2f-pc-configurator' ),
			),
		);
	}

	/**
	 * Load calculator configurations from database.
	 */
	private function load_configs() {
		$saved = get_option( self::OPTION_NAME, array() );

		if ( ! empty( $saved ) && is_array( $saved ) ) {
			$this->configs = $saved;
		} else {
			// Initialize with defaults.
			$this->configs = $this->get_default_configs();
		}
	}

	/**
	 * Get default calculator configurations.
	 *
	 * @return array
	 */
	private function get_default_configs() {
		return array(
			'psu_wattage' => array(
				'enabled' => true,
				'params' => array(
					'base_w' => 50,
					'headroom_factor' => 1.25,
					'contributors' => array( 'cpu.tdp_w', 'gpu.power_w' ),
				),
			),
			'gpu_clearance' => array(
				'enabled' => true,
				'params' => array(
					'base_source' => 'case.max_gpu_length_mm',
					'default_max_mm' => 400,
				),
			),
		);
	}

	/**
	 * Initialize calculator instances.
	 */
	private function initialize_calculators() {
		foreach ( $this->calculator_types as $type_id => $type_info ) {
			$config = isset( $this->configs[ $type_id ] ) ? $this->configs[ $type_id ] : array();
			$class = $type_info['class'];

			if ( class_exists( $class ) ) {
				$this->calculators[ $type_id ] = new $class( $type_id, $config );
			}
		}
	}

	/**
	 * Get all calculator types.
	 *
	 * @return array
	 */
	public function get_calculator_types() {
		return $this->calculator_types;
	}

	/**
	 * Get all calculator instances.
	 *
	 * @return array
	 */
	public function get_all() {
		return $this->calculators;
	}

	/**
	 * Get enabled calculators.
	 *
	 * @return array
	 */
	public function get_enabled_calculators() {
		$enabled = array();

		foreach ( $this->calculators as $id => $calculator ) {
			if ( $calculator->is_enabled() ) {
				$enabled[ $id ] = $calculator;
			}
		}

		return $enabled;
	}

	/**
	 * Get a single calculator.
	 *
	 * @param  string $id Calculator ID.
	 * @return W2F_PC_Calculator_Base|null
	 */
	public function get( $id ) {
		return isset( $this->calculators[ $id ] ) ? $this->calculators[ $id ] : null;
	}

	/**
	 * Get calculator configuration.
	 *
	 * @param  string $id Calculator ID.
	 * @return array
	 */
	public function get_config( $id ) {
		return isset( $this->configs[ $id ] ) ? $this->configs[ $id ] : array();
	}

	/**
	 * Save calculator configuration.
	 *
	 * @param string $id     Calculator ID.
	 * @param array  $config Configuration data.
	 */
	public function save_config( $id, $config ) {
		$this->configs[ $id ] = $config;
		update_option( self::OPTION_NAME, $this->configs );

		// Reinitialize the calculator.
		if ( isset( $this->calculator_types[ $id ] ) ) {
			$class = $this->calculator_types[ $id ]['class'];
			if ( class_exists( $class ) ) {
				$this->calculators[ $id ] = new $class( $id, $config );
			}
		}
	}

	/**
	 * Save all configurations.
	 *
	 * @param array $configs All configurations.
	 */
	public function save_all_configs( $configs ) {
		$this->configs = $configs;
		update_option( self::OPTION_NAME, $this->configs );
		$this->initialize_calculators();
	}

	/**
	 * Reset to defaults.
	 */
	public function reset_to_defaults() {
		$this->configs = $this->get_default_configs();
		update_option( self::OPTION_NAME, $this->configs );
		$this->initialize_calculators();
	}

	/**
	 * Get available contributor fact keys for PSU calculator.
	 *
	 * @return array
	 */
	public function get_available_power_contributors() {
		return array(
			'cpu.tdp_w' => __( 'CPU TDP', 'w2f-pc-configurator' ),
			'gpu.power_w' => __( 'GPU Power', 'w2f-pc-configurator' ),
			'ram.power_w' => __( 'RAM Power', 'w2f-pc-configurator' ),
			'storage.power_w' => __( 'Storage Power', 'w2f-pc-configurator' ),
			'cooler.power_w' => __( 'Cooler Power', 'w2f-pc-configurator' ),
			'fans.power_w' => __( 'Fan Power', 'w2f-pc-configurator' ),
		);
	}
}
