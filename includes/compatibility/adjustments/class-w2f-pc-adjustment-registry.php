<?php
/**
 * W2F_PC_Adjustment_Registry class
 *
 * Manages adjustment instances and their configuration.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adjustment Registry - manages all adjustments.
 *
 * Adjustments modify calculator inputs or derived constraints based on conditions.
 *
 * @class    W2F_PC_Adjustment_Registry
 * @version  2.0.0
 */
class W2F_PC_Adjustment_Registry {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Adjustment_Registry
	 */
	protected static $_instance = null;

	/**
	 * Option name for storing adjustments.
	 */
	const OPTION_NAME = 'w2f_pc_adjustments';

	/**
	 * Adjustment instances.
	 *
	 * @var array
	 */
	private $adjustments = array();

	/**
	 * Saved adjustment configurations.
	 *
	 * @var array
	 */
	private $saved_adjustments = array();

	/**
	 * Main instance.
	 *
	 * @return W2F_PC_Adjustment_Registry
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
		$this->load_adjustments();
	}

	/**
	 * Load saved adjustments from database.
	 */
	private function load_adjustments() {
		$this->saved_adjustments = get_option( self::OPTION_NAME, array() );

		if ( empty( $this->saved_adjustments ) ) {
			$this->saved_adjustments = $this->get_default_adjustments();
			update_option( self::OPTION_NAME, $this->saved_adjustments );
		}

		// Instantiate adjustments.
		foreach ( $this->saved_adjustments as $id => $config ) {
			$this->adjustments[ $id ] = new W2F_PC_Adjustment( $id, $config );
		}
	}

	/**
	 * Get default adjustments.
	 *
	 * @return array
	 */
	public function get_default_adjustments() {
		return array(
			'aio_240_front_gpu_clearance' => array(
				'name' => __( '240mm AIO Front Mount GPU Clearance', 'w2f-pc-configurator' ),
				'description' => __( 'Reduces GPU clearance when a 240mm AIO is front-mounted.', 'w2f-pc-configurator' ),
				'enabled' => true,
				'priority' => 10,
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
				'reason' => __( 'Front-mounted 240mm+ AIO radiator reduces GPU clearance by 40mm.', 'w2f-pc-configurator' ),
			),
			'aio_360_front_gpu_clearance' => array(
				'name' => __( '360mm AIO Front Mount GPU Clearance', 'w2f-pc-configurator' ),
				'description' => __( 'Reduces GPU clearance when a 360mm AIO is front-mounted.', 'w2f-pc-configurator' ),
				'enabled' => true,
				'priority' => 20,
				'conditions' => array(
					array(
						'fact_key' => 'cooler.type',
						'operator' => '=',
						'value' => 'aio',
					),
					array(
						'fact_key' => 'cooler.radiator_size_mm',
						'operator' => '>=',
						'value' => 360,
					),
				),
				'condition_logic' => 'AND',
				'target_key' => 'derived.gpu_clearance_penalty_mm',
				'effect' => 'add',
				'delta' => 55,
				'reason' => __( 'Front-mounted 360mm AIO radiator reduces GPU clearance by 55mm.', 'w2f-pc-configurator' ),
			),
		);
	}

	/**
	 * Get all adjustments.
	 *
	 * @return array
	 */
	public function get_all() {
		return $this->adjustments;
	}

	/**
	 * Get all saved configurations.
	 *
	 * @return array
	 */
	public function get_all_configs() {
		return $this->saved_adjustments;
	}

	/**
	 * Get enabled adjustments.
	 *
	 * @return array
	 */
	public function get_enabled_adjustments() {
		$enabled = array();

		foreach ( $this->adjustments as $id => $adjustment ) {
			if ( $adjustment->is_enabled() ) {
				$enabled[ $id ] = $adjustment;
			}
		}

		return $enabled;
	}

	/**
	 * Get a single adjustment.
	 *
	 * @param  string $id Adjustment ID.
	 * @return W2F_PC_Adjustment|null
	 */
	public function get( $id ) {
		return isset( $this->adjustments[ $id ] ) ? $this->adjustments[ $id ] : null;
	}

	/**
	 * Get adjustment configuration.
	 *
	 * @param  string $id Adjustment ID.
	 * @return array|null
	 */
	public function get_config( $id ) {
		return isset( $this->saved_adjustments[ $id ] ) ? $this->saved_adjustments[ $id ] : null;
	}

	/**
	 * Save an adjustment.
	 *
	 * @param string $id     Adjustment ID.
	 * @param array  $config Adjustment configuration.
	 */
	public function save( $id, $config ) {
		$this->saved_adjustments[ $id ] = $config;
		update_option( self::OPTION_NAME, $this->saved_adjustments );
		$this->adjustments[ $id ] = new W2F_PC_Adjustment( $id, $config );
	}

	/**
	 * Delete an adjustment.
	 *
	 * @param string $id Adjustment ID.
	 */
	public function delete( $id ) {
		if ( isset( $this->saved_adjustments[ $id ] ) ) {
			unset( $this->saved_adjustments[ $id ] );
			unset( $this->adjustments[ $id ] );
			update_option( self::OPTION_NAME, $this->saved_adjustments );
		}
	}

	/**
	 * Save all adjustments.
	 *
	 * @param array $adjustments All adjustment configurations.
	 */
	public function save_all( $adjustments ) {
		$this->saved_adjustments = $adjustments;
		update_option( self::OPTION_NAME, $this->saved_adjustments );

		// Reinitialize.
		$this->adjustments = array();
		foreach ( $this->saved_adjustments as $id => $config ) {
			$this->adjustments[ $id ] = new W2F_PC_Adjustment( $id, $config );
		}
	}

	/**
	 * Reset to defaults.
	 */
	public function reset_to_defaults() {
		$this->saved_adjustments = $this->get_default_adjustments();
		update_option( self::OPTION_NAME, $this->saved_adjustments );

		$this->adjustments = array();
		foreach ( $this->saved_adjustments as $id => $config ) {
			$this->adjustments[ $id ] = new W2F_PC_Adjustment( $id, $config );
		}
	}

	/**
	 * Create a new adjustment with a unique ID.
	 *
	 * @param  string $name   Adjustment name.
	 * @param  array  $config Adjustment configuration.
	 * @return string New adjustment ID.
	 */
	public function create( $name, $config = array() ) {
		$id = sanitize_title( $name ) . '_' . substr( md5( uniqid() ), 0, 6 );

		$full_config = array_merge( array(
			'name' => $name,
			'description' => '',
			'enabled' => true,
			'priority' => 50,
			'conditions' => array(),
			'condition_logic' => 'AND',
			'target_key' => '',
			'effect' => 'add',
			'delta' => 0,
			'reason' => '',
		), $config );

		$this->save( $id, $full_config );

		return $id;
	}

	/**
	 * Get available effect types.
	 *
	 * @return array
	 */
	public function get_effect_types() {
		return array(
			'add' => __( 'Add', 'w2f-pc-configurator' ),
			'subtract' => __( 'Subtract', 'w2f-pc-configurator' ),
			'multiply' => __( 'Multiply', 'w2f-pc-configurator' ),
			'set' => __( 'Set to', 'w2f-pc-configurator' ),
		);
	}

	/**
	 * Get available comparison operators.
	 *
	 * @return array
	 */
	public function get_operators() {
		return array(
			'=' => __( 'Equals', 'w2f-pc-configurator' ),
			'!=' => __( 'Not equals', 'w2f-pc-configurator' ),
			'>' => __( 'Greater than', 'w2f-pc-configurator' ),
			'>=' => __( 'Greater than or equal', 'w2f-pc-configurator' ),
			'<' => __( 'Less than', 'w2f-pc-configurator' ),
			'<=' => __( 'Less than or equal', 'w2f-pc-configurator' ),
			'contains' => __( 'Contains', 'w2f-pc-configurator' ),
			'exists' => __( 'Exists', 'w2f-pc-configurator' ),
		);
	}
}
