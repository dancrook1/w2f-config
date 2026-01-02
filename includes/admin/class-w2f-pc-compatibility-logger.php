<?php
/**
 * W2F_PC_Compatibility_Logger class
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility logging functionality.
 *
 * @class    W2F_PC_Compatibility_Logger
 * @version  1.0.0
 */
class W2F_PC_Compatibility_Logger {

	/**
	 * Option name for storing logs.
	 */
	const LOG_OPTION_NAME = 'w2f_pc_compatibility_logs';

	/**
	 * Maximum number of log entries to store.
	 */
	const MAX_LOG_ENTRIES = 1000;

	/**
	 * Log a compatibility check.
	 *
	 * @param  array $log_data Log entry data
	 */
	public static function log( $log_data ) {
		$logs = self::get_logs();

		$entry = array(
			'timestamp' => current_time( 'mysql' ),
			'configurator_product_id' => isset( $log_data['configurator_product_id'] ) ? intval( $log_data['configurator_product_id'] ) : 0,
			'configuration' => isset( $log_data['configuration'] ) && is_array( $log_data['configuration'] ) ? $log_data['configuration'] : array(),
			'rule_id' => isset( $log_data['rule_id'] ) ? sanitize_text_field( $log_data['rule_id'] ) : '',
			'rule_name' => isset( $log_data['rule_name'] ) ? sanitize_text_field( $log_data['rule_name'] ) : '',
			'affected_products' => isset( $log_data['affected_products'] ) && is_array( $log_data['affected_products'] ) ? array_map( 'intval', $log_data['affected_products'] ) : array(),
			'result' => isset( $log_data['result'] ) ? sanitize_text_field( $log_data['result'] ) : 'compatible',
			'message' => isset( $log_data['message'] ) ? sanitize_text_field( $log_data['message'] ) : '',
			'component_id' => isset( $log_data['component_id'] ) ? sanitize_text_field( $log_data['component_id'] ) : '',
		);

		$logs[] = $entry;

		// Keep only the last MAX_LOG_ENTRIES entries.
		if ( count( $logs ) > self::MAX_LOG_ENTRIES ) {
			$logs = array_slice( $logs, -self::MAX_LOG_ENTRIES );
		}

		update_option( self::LOG_OPTION_NAME, $logs, false );
	}

	/**
	 * Get all logs.
	 *
	 * @return array
	 */
	public static function get_logs() {
		$logs = get_option( self::LOG_OPTION_NAME, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}
		return $logs;
	}

	/**
	 * Get filtered logs.
	 *
	 * @param  array $filters Filter criteria
	 * @return array
	 */
	public static function get_filtered_logs( $filters = array() ) {
		$logs = self::get_logs();
		$filtered = array();

		$date_from = isset( $filters['date_from'] ) ? sanitize_text_field( $filters['date_from'] ) : '';
		$date_to = isset( $filters['date_to'] ) ? sanitize_text_field( $filters['date_to'] ) : '';
		$configurator_id = isset( $filters['configurator_id'] ) ? intval( $filters['configurator_id'] ) : 0;
		$rule_id = isset( $filters['rule_id'] ) ? sanitize_text_field( $filters['rule_id'] ) : '';
		$result = isset( $filters['result'] ) ? sanitize_text_field( $filters['result'] ) : '';

		foreach ( $logs as $log ) {
			// Date filter.
			if ( $date_from && strtotime( $log['timestamp'] ) < strtotime( $date_from ) ) {
				continue;
			}
			if ( $date_to && strtotime( $log['timestamp'] ) > strtotime( $date_to . ' 23:59:59' ) ) {
				continue;
			}

			// Configurator filter.
			if ( $configurator_id && (int) $log['configurator_product_id'] !== $configurator_id ) {
				continue;
			}

			// Rule filter.
			if ( $rule_id && $log['rule_id'] !== $rule_id ) {
				continue;
			}

			// Result filter.
			if ( $result && $log['result'] !== $result ) {
				continue;
			}

			$filtered[] = $log;
		}

		// Sort by timestamp descending (newest first).
		usort( $filtered, function( $a, $b ) {
			return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
		} );

		return $filtered;
	}

	/**
	 * Clear all logs.
	 */
	public static function clear_logs() {
		delete_option( self::LOG_OPTION_NAME );
	}

	/**
	 * Get compatibility matrix (which products are compatible with which).
	 *
	 * @return array
	 */
	public static function get_compatibility_matrix() {
		$logs = self::get_logs();
		$matrix = array();

		foreach ( $logs as $log ) {
			if ( empty( $log['affected_products'] ) ) {
				continue;
			}

			$configurator_id = $log['configurator_product_id'];
			if ( ! isset( $matrix[ $configurator_id ] ) ) {
				$matrix[ $configurator_id ] = array(
					'compatible' => array(),
					'incompatible' => array(),
					'warnings' => array(),
				);
			}

			foreach ( $log['affected_products'] as $product_id ) {
				if ( 'incompatible' === $log['result'] ) {
					if ( ! in_array( $product_id, $matrix[ $configurator_id ]['incompatible'], true ) ) {
						$matrix[ $configurator_id ]['incompatible'][] = $product_id;
					}
				} elseif ( 'warning' === $log['result'] ) {
					if ( ! in_array( $product_id, $matrix[ $configurator_id ]['warnings'], true ) ) {
						$matrix[ $configurator_id ]['warnings'][] = $product_id;
					}
				} else {
					if ( ! in_array( $product_id, $matrix[ $configurator_id ]['compatible'], true ) ) {
						$matrix[ $configurator_id ]['compatible'][] = $product_id;
					}
				}
			}
		}

		return $matrix;
	}

	/**
	 * Get rule impact summary (which rules affect which products).
	 *
	 * @return array
	 */
	public static function get_rule_impact() {
		$logs = self::get_logs();
		$impact = array();

		foreach ( $logs as $log ) {
			$rule_id = $log['rule_id'];
			if ( empty( $rule_id ) ) {
				continue;
			}

			if ( ! isset( $impact[ $rule_id ] ) ) {
				$impact[ $rule_id ] = array(
					'rule_name' => $log['rule_name'],
					'affected_products' => array(),
					'affected_configurators' => array(),
					'total_checks' => 0,
					'incompatible_count' => 0,
					'warning_count' => 0,
				);
			}

			$impact[ $rule_id ]['total_checks']++;
			
			if ( 'incompatible' === $log['result'] ) {
				$impact[ $rule_id ]['incompatible_count']++;
			} elseif ( 'warning' === $log['result'] ) {
				$impact[ $rule_id ]['warning_count']++;
			}

			foreach ( $log['affected_products'] as $product_id ) {
				if ( ! in_array( $product_id, $impact[ $rule_id ]['affected_products'], true ) ) {
					$impact[ $rule_id ]['affected_products'][] = $product_id;
				}
			}

			$configurator_id = $log['configurator_product_id'];
			if ( $configurator_id && ! in_array( $configurator_id, $impact[ $rule_id ]['affected_configurators'], true ) ) {
				$impact[ $rule_id ]['affected_configurators'][] = $configurator_id;
			}
		}

		return $impact;
	}
}

