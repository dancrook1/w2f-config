<?php
/**
 * W2F_PC_Policy_Base class
 *
 * Base class for all compatibility policies.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Policy Base - abstract base for all policies.
 *
 * @class    W2F_PC_Policy_Base
 * @version  2.0.0
 */
abstract class W2F_PC_Policy_Base {

	/**
	 * Policy ID.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Policy name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Policy description.
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * Whether policy is enabled.
	 *
	 * @var bool
	 */
	protected $enabled;

	/**
	 * Evaluation priority (lower = earlier).
	 *
	 * @var int
	 */
	protected $priority;

	/**
	 * Severity: 'block' or 'warn'.
	 *
	 * @var string
	 */
	protected $severity;

	/**
	 * Message template.
	 *
	 * @var string
	 */
	protected $message_template;

	/**
	 * Policy-specific configuration.
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Constructor.
	 *
	 * @param string $id           Policy ID.
	 * @param array  $policy_config Policy configuration.
	 */
	public function __construct( $id, $policy_config = array() ) {
		$this->id = $id;
		$this->name = isset( $policy_config['name'] ) ? $policy_config['name'] : $id;
		$this->description = isset( $policy_config['description'] ) ? $policy_config['description'] : '';
		$this->enabled = isset( $policy_config['enabled'] ) ? (bool) $policy_config['enabled'] : true;
		$this->priority = isset( $policy_config['priority'] ) ? (int) $policy_config['priority'] : 50;
		$this->severity = isset( $policy_config['severity'] ) ? $policy_config['severity'] : 'block';
		$this->message_template = isset( $policy_config['message'] ) ? $policy_config['message'] : '';
		$this->config = isset( $policy_config['config'] ) ? $policy_config['config'] : array();
	}

	/**
	 * Get policy ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get policy name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get policy description.
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get policy type identifier.
	 *
	 * @return string
	 */
	abstract public function get_type();

	/**
	 * Check if policy is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Get priority.
	 *
	 * @return int
	 */
	public function get_priority() {
		return $this->priority;
	}

	/**
	 * Get severity.
	 *
	 * @return string
	 */
	public function get_severity() {
		return $this->severity;
	}

	/**
	 * Get configuration fields for admin UI.
	 *
	 * @return array
	 */
	abstract public function get_config_fields();

	/**
	 * Check if policy is applicable to the current configuration.
	 *
	 * @param  W2F_PC_Fact_Collection $facts         Fact collection.
	 * @param  array                  $configuration Configuration array.
	 * @return bool
	 */
	abstract public function is_applicable( $facts, $configuration );

	/**
	 * Evaluate the policy.
	 *
	 * @param  W2F_PC_Fact_Collection $facts                Fact collection.
	 * @param  array                  $configuration        Configuration array.
	 * @param  W2F_PC_Product|null    $configurator_product Configurator product.
	 * @return array {
	 *     @type bool   $passed   Whether the policy passed.
	 *     @type string $message  Human-readable message.
	 *     @type array  $details  Additional details for trace.
	 *     @type array  $compared Values that were compared.
	 * }
	 */
	abstract public function evaluate( $facts, $configuration, $configurator_product = null );

	/**
	 * Get a config value.
	 *
	 * @param  string $key     Config key.
	 * @param  mixed  $default Default value.
	 * @return mixed
	 */
	protected function get_config_value( $key, $default = null ) {
		return isset( $this->config[ $key ] ) ? $this->config[ $key ] : $default;
	}

	/**
	 * Render message with variable substitution.
	 *
	 * @param  W2F_PC_Fact_Collection $facts           Fact collection.
	 * @param  array                  $extra_variables Additional variables.
	 * @return string
	 */
	protected function render_message( $facts, $extra_variables = array() ) {
		$message = $this->message_template;

		if ( empty( $message ) ) {
			$message = $this->get_default_message();
		}

		// Replace {{key}} with fact values.
		$message = preg_replace_callback( '/\{\{([^}]+)\}\}/', function( $matches ) use ( $facts, $extra_variables ) {
			$key = trim( $matches[1] );

			// Check extra variables first.
			if ( isset( $extra_variables[ $key ] ) ) {
				$value = $extra_variables[ $key ];
			} else {
				$value = $facts->get( $key, 'N/A' );
			}

			if ( is_array( $value ) ) {
				return implode( ', ', $value );
			}

			return (string) $value;
		}, $message );

		return $message;
	}

	/**
	 * Get default message if none provided.
	 *
	 * @return string
	 */
	protected function get_default_message() {
		return __( 'Compatibility check failed.', 'w2f-pc-configurator' );
	}

	/**
	 * Get full configuration.
	 *
	 * @return array
	 */
	public function get_full_config() {
		return array(
			'type' => $this->get_type(),
			'name' => $this->name,
			'description' => $this->description,
			'enabled' => $this->enabled,
			'priority' => $this->priority,
			'severity' => $this->severity,
			'message' => $this->message_template,
			'config' => $this->config,
		);
	}
}
