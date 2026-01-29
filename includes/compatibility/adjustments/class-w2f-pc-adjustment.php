<?php
/**
 * W2F_PC_Adjustment class
 *
 * Represents a single adjustment that modifies derived values.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adjustment - modifies derived values based on conditions.
 *
 * Example: 240mm AIO reduces GPU clearance by 40mm.
 *
 * @class    W2F_PC_Adjustment
 * @version  2.0.0
 */
class W2F_PC_Adjustment {

	/**
	 * Adjustment ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Adjustment name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Description.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * Whether enabled.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Priority (lower = earlier).
	 *
	 * @var int
	 */
	private $priority;

	/**
	 * Conditions array.
	 *
	 * @var array
	 */
	private $conditions;

	/**
	 * Condition logic (AND/OR).
	 *
	 * @var string
	 */
	private $condition_logic;

	/**
	 * Target fact key to modify.
	 *
	 * @var string
	 */
	private $target_key;

	/**
	 * Effect type (add, subtract, multiply, set).
	 *
	 * @var string
	 */
	private $effect;

	/**
	 * Delta value for the effect.
	 *
	 * @var float
	 */
	private $delta;

	/**
	 * Human-readable reason.
	 *
	 * @var string
	 */
	private $reason;

	/**
	 * Constructor.
	 *
	 * @param string $id     Adjustment ID.
	 * @param array  $config Adjustment configuration.
	 */
	public function __construct( $id, $config = array() ) {
		$this->id = $id;
		$this->name = isset( $config['name'] ) ? $config['name'] : $id;
		$this->description = isset( $config['description'] ) ? $config['description'] : '';
		$this->enabled = isset( $config['enabled'] ) ? (bool) $config['enabled'] : true;
		$this->priority = isset( $config['priority'] ) ? (int) $config['priority'] : 50;
		$this->conditions = isset( $config['conditions'] ) ? $config['conditions'] : array();
		$this->condition_logic = isset( $config['condition_logic'] ) ? $config['condition_logic'] : 'AND';
		$this->target_key = isset( $config['target_key'] ) ? $config['target_key'] : '';
		$this->effect = isset( $config['effect'] ) ? $config['effect'] : 'add';
		$this->delta = isset( $config['delta'] ) ? (float) $config['delta'] : 0;
		$this->reason = isset( $config['reason'] ) ? $config['reason'] : '';
	}

	/**
	 * Get ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Check if enabled.
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
	 * Get target key.
	 *
	 * @return string
	 */
	public function get_target_key() {
		return $this->target_key;
	}

	/**
	 * Get reason.
	 *
	 * @return string
	 */
	public function get_reason() {
		return $this->reason;
	}

	/**
	 * Apply the adjustment if conditions are met.
	 *
	 * @param  W2F_PC_Fact_Collection $facts Fact collection.
	 * @return array {
	 *     @type bool  $applied Whether adjustment was applied.
	 *     @type float $delta   Delta that was applied.
	 *     @type mixed $before  Value before adjustment.
	 *     @type mixed $after   Value after adjustment.
	 * }
	 */
	public function apply( $facts ) {
		$result = array(
			'applied' => false,
			'delta' => 0,
			'before' => null,
			'after' => null,
		);

		// Check conditions.
		if ( ! $this->conditions_met( $facts ) ) {
			return $result;
		}

		// Apply the effect.
		$before = $facts->get( $this->target_key, 0 );

		switch ( $this->effect ) {
			case 'add':
				$after = $before + $this->delta;
				break;
			case 'subtract':
				$after = $before - $this->delta;
				break;
			case 'multiply':
				$after = $before * $this->delta;
				break;
			case 'set':
				$after = $this->delta;
				break;
			default:
				return $result;
		}

		$facts->set( $this->target_key, $after );

		return array(
			'applied' => true,
			'delta' => $this->delta,
			'before' => $before,
			'after' => $after,
		);
	}

	/**
	 * Check if conditions are met.
	 *
	 * @param  W2F_PC_Fact_Collection $facts Fact collection.
	 * @return bool
	 */
	private function conditions_met( $facts ) {
		if ( empty( $this->conditions ) ) {
			return true; // No conditions = always apply.
		}

		$results = array();

		foreach ( $this->conditions as $condition ) {
			$results[] = $this->evaluate_condition( $condition, $facts );
		}

		if ( 'OR' === $this->condition_logic ) {
			return in_array( true, $results, true );
		}

		// AND logic.
		return ! in_array( false, $results, true );
	}

	/**
	 * Evaluate a single condition.
	 *
	 * @param  array                  $condition Condition config.
	 * @param  W2F_PC_Fact_Collection $facts     Fact collection.
	 * @return bool
	 */
	private function evaluate_condition( $condition, $facts ) {
		$fact_key = isset( $condition['fact_key'] ) ? $condition['fact_key'] : '';
		$operator = isset( $condition['operator'] ) ? $condition['operator'] : '=';
		$expected = isset( $condition['value'] ) ? $condition['value'] : '';

		if ( empty( $fact_key ) ) {
			return false;
		}

		// Special operator: exists.
		if ( 'exists' === $operator ) {
			return $facts->has( $fact_key );
		}

		$actual = $facts->get( $fact_key );

		if ( $actual === null ) {
			return false;
		}

		// Normalize string values.
		if ( is_string( $actual ) && is_string( $expected ) ) {
			$actual = strtolower( trim( $actual ) );
			$expected = strtolower( trim( $expected ) );
		}

		switch ( $operator ) {
			case '=':
			case '==':
				return $actual == $expected;
			case '!=':
			case '<>':
				return $actual != $expected;
			case '>':
				return $actual > $expected;
			case '>=':
				return $actual >= $expected;
			case '<':
				return $actual < $expected;
			case '<=':
				return $actual <= $expected;
			case 'contains':
				if ( is_array( $actual ) ) {
					return in_array( $expected, array_map( 'strtolower', array_map( 'trim', $actual ) ), true );
				}
				return stripos( (string) $actual, (string) $expected ) !== false;
			default:
				return false;
		}
	}

	/**
	 * Get full configuration.
	 *
	 * @return array
	 */
	public function get_config() {
		return array(
			'name' => $this->name,
			'description' => $this->description,
			'enabled' => $this->enabled,
			'priority' => $this->priority,
			'conditions' => $this->conditions,
			'condition_logic' => $this->condition_logic,
			'target_key' => $this->target_key,
			'effect' => $this->effect,
			'delta' => $this->delta,
			'reason' => $this->reason,
		);
	}
}
