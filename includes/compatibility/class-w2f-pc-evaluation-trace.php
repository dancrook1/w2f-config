<?php
/**
 * W2F_PC_Evaluation_Trace class
 *
 * Tracks the full evaluation process for debugging and explanation.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluation Trace - tracks all steps of compatibility evaluation.
 *
 * @class    W2F_PC_Evaluation_Trace
 * @version  2.0.0
 */
class W2F_PC_Evaluation_Trace {

	/**
	 * Fact collection snapshot.
	 *
	 * @var W2F_PC_Fact_Collection
	 */
	private $facts;

	/**
	 * Fired adjustments.
	 *
	 * @var array
	 */
	private $fired_adjustments = array();

	/**
	 * Calculator results.
	 *
	 * @var array
	 */
	private $calculator_results = array();

	/**
	 * Policy evaluation results.
	 *
	 * @var array
	 */
	private $policy_results = array();

	/**
	 * Timestamp when evaluation started.
	 *
	 * @var float
	 */
	private $start_time;

	/**
	 * Timestamp when evaluation ended.
	 *
	 * @var float
	 */
	private $end_time;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->start_time = microtime( true );
	}

	/**
	 * Set the facts collection.
	 *
	 * @param W2F_PC_Fact_Collection $facts Facts collection.
	 */
	public function set_facts( $facts ) {
		$this->facts = $facts;
	}

	/**
	 * Get the facts collection.
	 *
	 * @return W2F_PC_Fact_Collection
	 */
	public function get_facts() {
		return $this->facts;
	}

	/**
	 * Set fired adjustments.
	 *
	 * @param array $adjustments Fired adjustments.
	 */
	public function set_fired_adjustments( $adjustments ) {
		$this->fired_adjustments = $adjustments;
	}

	/**
	 * Get fired adjustments.
	 *
	 * @return array
	 */
	public function get_fired_adjustments() {
		return $this->fired_adjustments;
	}

	/**
	 * Set calculator results.
	 *
	 * @param array $results Calculator results.
	 */
	public function set_calculator_results( $results ) {
		$this->calculator_results = $results;
	}

	/**
	 * Get calculator results.
	 *
	 * @return array
	 */
	public function get_calculator_results() {
		return $this->calculator_results;
	}

	/**
	 * Add a policy evaluation result.
	 *
	 * @param array $result Policy result.
	 */
	public function add_policy_result( $result ) {
		$this->policy_results[] = $result;
	}

	/**
	 * Get all policy results.
	 *
	 * @return array
	 */
	public function get_policy_results() {
		return $this->policy_results;
	}

	/**
	 * Get derived values from facts.
	 *
	 * @return array
	 */
	public function get_derived_values() {
		if ( ! $this->facts ) {
			return array();
		}

		$derived = array();
		foreach ( $this->facts->to_array() as $key => $value ) {
			if ( strpos( $key, 'derived.' ) === 0 ) {
				$derived[ $key ] = $value;
			}
		}
		return $derived;
	}

	/**
	 * Mark evaluation as complete.
	 */
	public function complete() {
		$this->end_time = microtime( true );
	}

	/**
	 * Get evaluation duration in milliseconds.
	 *
	 * @return float
	 */
	public function get_duration_ms() {
		$end = $this->end_time ?: microtime( true );
		return ( $end - $this->start_time ) * 1000;
	}

	/**
	 * Generate human-readable summary.
	 *
	 * @return array
	 */
	public function get_human_readable_summary() {
		$summary = array(
			'overview' => array(),
			'adjustments' => array(),
			'calculators' => array(),
			'policies' => array(),
		);

		// Adjustments summary.
		foreach ( $this->fired_adjustments as $adj ) {
			$summary['adjustments'][] = sprintf(
				'%s: %s changed from %s to %s (%s)',
				$adj['name'],
				$adj['target'],
				$this->format_value( $adj['before'] ),
				$this->format_value( $adj['after'] ),
				$adj['reason']
			);
		}

		// Calculators summary.
		foreach ( $this->calculator_results as $calc ) {
			$breakdown_text = '';
			if ( ! empty( $calc['breakdown'] ) ) {
				$parts = array();
				foreach ( $calc['breakdown'] as $item ) {
					$parts[] = sprintf( '%s: %s', $item['label'], $this->format_value( $item['value'] ) );
				}
				$breakdown_text = ' (' . implode( ', ', $parts ) . ')';
			}
			$summary['calculators'][] = sprintf(
				'%s = %s%s',
				$calc['name'],
				$this->format_value( $calc['value'] ),
				$breakdown_text
			);
		}

		// Policies summary.
		$passed_count = 0;
		$failed_count = 0;
		$warning_count = 0;

		foreach ( $this->policy_results as $policy ) {
			$status = $policy['passed'] ? 'PASSED' : ( 'block' === $policy['severity'] ? 'BLOCKED' : 'WARNING' );
			
			if ( $policy['passed'] ) {
				$passed_count++;
			} elseif ( 'block' === $policy['severity'] ) {
				$failed_count++;
			} else {
				$warning_count++;
			}

			$details = '';
			if ( ! empty( $policy['compared'] ) ) {
				$compared = $policy['compared'];
				$details = sprintf(
					' [%s vs %s]',
					$this->format_value( $compared['left'] ?? 'N/A' ),
					$this->format_value( $compared['right'] ?? 'N/A' )
				);
			}

			$summary['policies'][] = array(
				'name' => $policy['name'],
				'status' => $status,
				'message' => $policy['message'],
				'details' => $details,
			);
		}

		$summary['overview'] = array(
			'total_policies' => count( $this->policy_results ),
			'passed' => $passed_count,
			'blocked' => $failed_count,
			'warnings' => $warning_count,
			'adjustments_applied' => count( $this->fired_adjustments ),
			'calculators_run' => count( $this->calculator_results ),
			'duration_ms' => round( $this->get_duration_ms(), 2 ),
		);

		return $summary;
	}

	/**
	 * Format a value for display.
	 *
	 * @param mixed $value Value to format.
	 * @return string
	 */
	private function format_value( $value ) {
		if ( is_array( $value ) ) {
			return '[' . implode( ', ', $value ) . ']';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_null( $value ) ) {
			return 'null';
		}
		return (string) $value;
	}

	/**
	 * Convert trace to array for JSON serialization.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'facts' => $this->facts ? $this->facts->to_array() : array(),
			'adjustments' => $this->fired_adjustments,
			'calculators' => $this->calculator_results,
			'policies' => $this->policy_results,
			'derived' => $this->get_derived_values(),
			'summary' => $this->get_human_readable_summary(),
		);
	}
}
