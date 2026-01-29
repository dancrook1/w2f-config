<?php
/**
 * W2F_PC_Fact_Collection class
 *
 * A typed collection of facts extracted from products.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fact Collection - stores typed values from products.
 *
 * @class    W2F_PC_Fact_Collection
 * @version  2.0.0
 */
class W2F_PC_Fact_Collection {

	/**
	 * Facts storage.
	 * Keys are dot-notation paths like "cpu.socket" or "derived.required_psu_w".
	 *
	 * @var array
	 */
	private $facts = array();

	/**
	 * Product references by component type.
	 *
	 * @var array
	 */
	private $products = array();

	/**
	 * Set a fact value.
	 *
	 * @param string $key   Fact key (e.g., "cpu.socket", "derived.required_psu_w").
	 * @param mixed  $value Fact value.
	 */
	public function set( $key, $value ) {
		$this->facts[ $key ] = $value;
	}

	/**
	 * Get a fact value.
	 *
	 * @param  string $key     Fact key.
	 * @param  mixed  $default Default value if key doesn't exist.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		return isset( $this->facts[ $key ] ) ? $this->facts[ $key ] : $default;
	}

	/**
	 * Check if a fact exists.
	 *
	 * @param  string $key Fact key.
	 * @return bool
	 */
	public function has( $key ) {
		return isset( $this->facts[ $key ] );
	}

	/**
	 * Remove a fact.
	 *
	 * @param string $key Fact key.
	 */
	public function remove( $key ) {
		unset( $this->facts[ $key ] );
	}

	/**
	 * Get all facts for a component type.
	 *
	 * @param  string $component_type Component type (e.g., "cpu", "gpu").
	 * @return array Facts for that component.
	 */
	public function get_by_component( $component_type ) {
		$prefix = $component_type . '.';
		$result = array();

		foreach ( $this->facts as $key => $value ) {
			if ( strpos( $key, $prefix ) === 0 ) {
				$short_key = substr( $key, strlen( $prefix ) );
				$result[ $short_key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Get all derived values.
	 *
	 * @return array Derived values.
	 */
	public function get_derived() {
		return $this->get_by_component( 'derived' );
	}

	/**
	 * Add a numeric delta to an existing value.
	 *
	 * @param string    $key   Fact key.
	 * @param int|float $delta Delta to add (can be negative).
	 * @return array {
	 *     @type mixed $before Value before adjustment.
	 *     @type mixed $after  Value after adjustment.
	 * }
	 */
	public function add_delta( $key, $delta ) {
		$before = $this->get( $key, 0 );
		$after = $before + $delta;
		$this->set( $key, $after );

		return array(
			'before' => $before,
			'after' => $after,
		);
	}

	/**
	 * Store a product reference.
	 *
	 * @param string     $component_type Component type.
	 * @param int        $product_id     Product ID.
	 * @param WC_Product $product        Product object.
	 */
	public function set_product( $component_type, $product_id, $product ) {
		if ( ! isset( $this->products[ $component_type ] ) ) {
			$this->products[ $component_type ] = array();
		}
		$this->products[ $component_type ][ $product_id ] = $product;
	}

	/**
	 * Get products for a component type.
	 *
	 * @param  string $component_type Component type.
	 * @return array Array of WC_Product objects keyed by ID.
	 */
	public function get_products( $component_type ) {
		return isset( $this->products[ $component_type ] ) ? $this->products[ $component_type ] : array();
	}

	/**
	 * Get first product for a component type.
	 *
	 * @param  string $component_type Component type.
	 * @return WC_Product|null
	 */
	public function get_product( $component_type ) {
		$products = $this->get_products( $component_type );
		return ! empty( $products ) ? reset( $products ) : null;
	}

	/**
	 * Check if a component type has any selected product.
	 *
	 * @param  string $component_type Component type.
	 * @return bool
	 */
	public function has_component( $component_type ) {
		return ! empty( $this->products[ $component_type ] );
	}

	/**
	 * Get all component types that have products.
	 *
	 * @return array
	 */
	public function get_selected_component_types() {
		return array_keys( $this->products );
	}

	/**
	 * Count products for a component type.
	 *
	 * @param  string $component_type Component type.
	 * @return int
	 */
	public function count_products( $component_type ) {
		return isset( $this->products[ $component_type ] ) ? count( $this->products[ $component_type ] ) : 0;
	}

	/**
	 * Count products matching a condition.
	 *
	 * @param  string   $component_type Component type.
	 * @param  string   $fact_key       Fact key to check (without component prefix).
	 * @param  mixed    $value          Value to match.
	 * @param  string   $operator       Comparison operator (=, !=, >, <, >=, <=).
	 * @return int
	 */
	public function count_products_where( $component_type, $fact_key, $value, $operator = '=' ) {
		$count = 0;
		$full_key = $component_type . '.' . $fact_key;

		// Get all facts for this component type.
		$component_facts = $this->get_by_component( $component_type );

		// For multi-value facts (arrays).
		$fact_value = $this->get( $full_key );

		if ( is_array( $fact_value ) ) {
			foreach ( $fact_value as $v ) {
				if ( $this->compare_values( $v, $value, $operator ) ) {
					$count++;
				}
			}
		} elseif ( $fact_value !== null && $this->compare_values( $fact_value, $value, $operator ) ) {
			$count = 1;
		}

		return $count;
	}

	/**
	 * Compare two values with an operator.
	 *
	 * @param  mixed  $a        First value.
	 * @param  mixed  $b        Second value.
	 * @param  string $operator Comparison operator.
	 * @return bool
	 */
	private function compare_values( $a, $b, $operator ) {
		// Normalize for comparison.
		if ( is_string( $a ) && is_string( $b ) ) {
			$a = strtolower( trim( $a ) );
			$b = strtolower( trim( $b ) );
		}

		switch ( $operator ) {
			case '=':
			case '==':
				return $a == $b;
			case '===':
				return $a === $b;
			case '!=':
			case '<>':
				return $a != $b;
			case '>':
				return $a > $b;
			case '<':
				return $a < $b;
			case '>=':
				return $a >= $b;
			case '<=':
				return $a <= $b;
			default:
				return false;
		}
	}

	/**
	 * Get sum of a numeric fact across all selected products.
	 *
	 * @param  string $fact_key Fact key (without component prefix).
	 * @return float
	 */
	public function sum_across_components( $fact_key ) {
		$sum = 0;

		foreach ( array_keys( $this->products ) as $component_type ) {
			$full_key = $component_type . '.' . $fact_key;
			$value = $this->get( $full_key, 0 );

			if ( is_array( $value ) ) {
				$sum += array_sum( $value );
			} else {
				$sum += (float) $value;
			}
		}

		return $sum;
	}

	/**
	 * Convert to array for serialization.
	 *
	 * @return array
	 */
	public function to_array() {
		return $this->facts;
	}

	/**
	 * Get all facts as array.
	 *
	 * @return array
	 */
	public function all() {
		return $this->facts;
	}
}
