<?php
/**
 * W2F_PC_Calculator_Base class
 *
 * Base class for all calculators.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculator Base - abstract base for all calculators.
 *
 * @class    W2F_PC_Calculator_Base
 * @version  2.0.0
 */
abstract class W2F_PC_Calculator_Base {

	/**
	 * Calculator ID.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Configuration.
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Whether calculator is enabled.
	 *
	 * @var bool
	 */
	protected $enabled;

	/**
	 * Parameters.
	 *
	 * @var array
	 */
	protected $params;

	/**
	 * Constructor.
	 *
	 * @param string $id     Calculator ID.
	 * @param array  $config Configuration.
	 */
	public function __construct( $id, $config = array() ) {
		$this->id = $id;
		$this->config = $config;
		$this->enabled = isset( $config['enabled'] ) ? (bool) $config['enabled'] : true;
		$this->params = isset( $config['params'] ) ? $config['params'] : $this->get_default_params();
	}

	/**
	 * Get calculator ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Check if calculator is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Get calculator name.
	 *
	 * @return string
	 */
	abstract public function get_name();

	/**
	 * Get calculator description.
	 *
	 * @return string
	 */
	abstract public function get_description();

	/**
	 * Get output key (where result is stored in facts).
	 *
	 * @return string
	 */
	abstract public function get_output_key();

	/**
	 * Get default parameters.
	 *
	 * @return array
	 */
	abstract public function get_default_params();

	/**
	 * Get parameter definitions for admin UI.
	 *
	 * @return array
	 */
	abstract public function get_param_definitions();

	/**
	 * Perform calculation.
	 *
	 * @param  W2F_PC_Fact_Collection $facts Fact collection.
	 * @return array {
	 *     @type mixed $value     Calculated value.
	 *     @type array $breakdown Calculation breakdown for trace.
	 * }
	 */
	abstract public function calculate( $facts );

	/**
	 * Get a parameter value.
	 *
	 * @param  string $key     Parameter key.
	 * @param  mixed  $default Default value.
	 * @return mixed
	 */
	protected function get_param( $key, $default = null ) {
		return isset( $this->params[ $key ] ) ? $this->params[ $key ] : $default;
	}

	/**
	 * Set parameters.
	 *
	 * @param array $params Parameters.
	 */
	public function set_params( $params ) {
		$this->params = array_merge( $this->get_default_params(), $params );
	}

	/**
	 * Get current parameters.
	 *
	 * @return array
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * Get configuration.
	 *
	 * @return array
	 */
	public function get_config() {
		return array(
			'enabled' => $this->enabled,
			'params' => $this->params,
		);
	}
}
