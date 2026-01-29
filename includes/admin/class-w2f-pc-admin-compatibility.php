<?php
/**
 * W2F_PC_Admin_Compatibility class
 *
 * Admin pages for the new compatibility system.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Compatibility - handles all compatibility admin pages.
 *
 * @class    W2F_PC_Admin_Compatibility
 * @version  2.0.0
 */
class W2F_PC_Admin_Compatibility {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Admin_Compatibility
	 */
	protected static $_instance = null;

	/**
	 * Main instance.
	 *
	 * @return W2F_PC_Admin_Compatibility
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
		add_action( 'admin_menu', array( $this, 'add_admin_menus' ), 20 );
		add_action( 'admin_post_w2f_pc_save_attribute', array( $this, 'handle_save_attribute' ) );
		add_action( 'admin_post_w2f_pc_delete_attribute', array( $this, 'handle_delete_attribute' ) );
		add_action( 'admin_post_w2f_pc_seed_wc_attributes', array( $this, 'handle_seed_wc_attributes' ) );
		add_action( 'admin_post_w2f_pc_save_calculator', array( $this, 'handle_save_calculator' ) );
		add_action( 'admin_post_w2f_pc_save_policy', array( $this, 'handle_save_policy' ) );
		add_action( 'admin_post_w2f_pc_delete_policy', array( $this, 'handle_delete_policy' ) );
		add_action( 'admin_post_w2f_pc_save_adjustment', array( $this, 'handle_save_adjustment' ) );
		add_action( 'admin_post_w2f_pc_delete_adjustment', array( $this, 'handle_delete_adjustment' ) );
		add_action( 'admin_post_w2f_pc_save_component_mapping', array( $this, 'handle_save_component_mapping' ) );
		add_action( 'wp_ajax_w2f_pc_simulate_build', array( $this, 'ajax_simulate_build' ) );
	}

	/**
	 * Add admin menu pages.
	 */
	public function add_admin_menus() {
		// Component Type Mapping.
		add_submenu_page(
			'w2f-pc-configurator',
			__( 'Component Types', 'w2f-pc-configurator' ),
			__( 'Component Types', 'w2f-pc-configurator' ),
			'manage_woocommerce',
			'w2f-pc-component-types',
			array( $this, 'render_component_types_page' )
		);

		// Attribute Dictionary.
		add_submenu_page(
			'w2f-pc-configurator',
			__( 'Attribute Dictionary', 'w2f-pc-configurator' ),
			__( 'Attributes', 'w2f-pc-configurator' ),
			'manage_woocommerce',
			'w2f-pc-attributes',
			array( $this, 'render_attributes_page' )
		);

		// Calculators.
		add_submenu_page(
			'w2f-pc-configurator',
			__( 'Calculators', 'w2f-pc-configurator' ),
			__( 'Calculators', 'w2f-pc-configurator' ),
			'manage_woocommerce',
			'w2f-pc-calculators',
			array( $this, 'render_calculators_page' )
		);

		// Compatibility Policies (replaces old Compatibility Rules).
		add_submenu_page(
			'w2f-pc-configurator',
			__( 'Compatibility Policies', 'w2f-pc-configurator' ),
			__( 'Policies', 'w2f-pc-configurator' ),
			'manage_woocommerce',
			'w2f-pc-policies',
			array( $this, 'render_policies_page' )
		);

		// Adjustments.
		add_submenu_page(
			'w2f-pc-configurator',
			__( 'Adjustments', 'w2f-pc-configurator' ),
			__( 'Adjustments', 'w2f-pc-configurator' ),
			'manage_woocommerce',
			'w2f-pc-adjustments',
			array( $this, 'render_adjustments_page' )
		);

		// Simulator.
		add_submenu_page(
			'w2f-pc-configurator',
			__( 'Build Simulator', 'w2f-pc-configurator' ),
			__( 'Simulator', 'w2f-pc-configurator' ),
			'manage_woocommerce',
			'w2f-pc-simulator',
			array( $this, 'render_simulator_page' )
		);
	}

	/**
	 * Render component types page.
	 */
	public function render_component_types_page() {
		$engine = W2F_PC_Compatibility_Engine::instance();
		$mapper = $engine->get_component_mapper();
		$component_types = $mapper->get_component_types();
		$mappings = $mapper->get_mappings();
		$categories = $mapper->get_available_categories();

		include W2F_PC()->plugin_path() . '/includes/admin/views/html-component-types.php';
	}

	/**
	 * Render attributes page.
	 */
	public function render_attributes_page() {
		$engine = W2F_PC_Compatibility_Engine::instance();
		$dictionary = $engine->get_attribute_dictionary();
		$attributes = $dictionary->get_all();
		$data_types = $dictionary->get_data_types();
		$taxonomies = $dictionary->get_available_taxonomies();
		$mapper = $engine->get_component_mapper();
		$component_types = $mapper->get_component_types();

		$edit_key = isset( $_GET['edit'] ) ? sanitize_text_field( $_GET['edit'] ) : '';
		$edit_attribute = $edit_key ? $dictionary->get( $edit_key ) : null;

		include W2F_PC()->plugin_path() . '/includes/admin/views/html-attributes.php';
	}

	/**
	 * Seed WooCommerce global attributes (pa_*) + term values used by defaults.
	 */
	public function handle_seed_wc_attributes() {
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'w2f_pc_seed_wc_attributes' ) ) {
			wp_die( __( 'Security check failed.', 'w2f-pc-configurator' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permission denied.', 'w2f-pc-configurator' ) );
		}

		$include_numeric = isset( $_POST['include_numeric_common_terms'] ) && '1' === $_POST['include_numeric_common_terms'];

		$seeder_path = W2F_PC()->plugin_path() . '/includes/compatibility/class-w2f-pc-wc-attribute-seeder.php';
		if ( file_exists( $seeder_path ) ) {
			require_once $seeder_path;
		}

		if ( ! class_exists( 'W2F_PC_WC_Attribute_Seeder' ) ) {
			wp_redirect( admin_url( 'admin.php?page=w2f-pc-attributes&seed_error=1' ) );
			exit;
		}

		$engine = W2F_PC_Compatibility_Engine::instance();
		$dictionary = $engine->get_attribute_dictionary();

		$seeder = new W2F_PC_WC_Attribute_Seeder();
		$report = $seeder->seed_from_dictionary(
			$dictionary,
			array(
				'include_numeric_common_terms' => $include_numeric,
			)
		);

		set_transient( 'w2f_pc_seed_report_' . get_current_user_id(), $report, 2 * MINUTE_IN_SECONDS );

		wp_redirect( admin_url( 'admin.php?page=w2f-pc-attributes&seeded=1' ) );
		exit;
	}

	/**
	 * Render calculators page.
	 */
	public function render_calculators_page() {
		$engine = W2F_PC_Compatibility_Engine::instance();
		$registry = $engine->get_calculator_registry();
		$calculator_types = $registry->get_calculator_types();
		$calculators = $registry->get_all();

		include W2F_PC()->plugin_path() . '/includes/admin/views/html-calculators.php';
	}

	/**
	 * Render policies page.
	 */
	public function render_policies_page() {
		$engine = W2F_PC_Compatibility_Engine::instance();
		$registry = $engine->get_policy_registry();
		$policy_types = $registry->get_policy_types();
		$policies = $registry->get_all_configs();
		$dictionary = $engine->get_attribute_dictionary();
		$mapper = $engine->get_component_mapper();
		$component_types = $mapper->get_component_types();

		$edit_id = isset( $_GET['edit'] ) ? sanitize_text_field( $_GET['edit'] ) : '';
		$edit_policy = $edit_id ? $registry->get_config( $edit_id ) : null;

		include W2F_PC()->plugin_path() . '/includes/admin/views/html-policies.php';
	}

	/**
	 * Render adjustments page.
	 */
	public function render_adjustments_page() {
		$engine = W2F_PC_Compatibility_Engine::instance();
		$registry = $engine->get_adjustment_registry();
		$adjustments = $registry->get_all_configs();
		$dictionary = $engine->get_attribute_dictionary();
		$mapper = $engine->get_component_mapper();
		$component_types = $mapper->get_component_types();

		$edit_id = isset( $_GET['edit'] ) ? sanitize_text_field( $_GET['edit'] ) : '';
		$edit_adjustment = $edit_id ? $registry->get_config( $edit_id ) : null;

		include W2F_PC()->plugin_path() . '/includes/admin/views/html-adjustments.php';
	}

	/**
	 * Render simulator page.
	 */
	public function render_simulator_page() {
		$engine = W2F_PC_Compatibility_Engine::instance();
		$mapper = $engine->get_component_mapper();
		$component_types = $mapper->get_component_types();

		// Get configurator products.
		$configurator_products = wc_get_products( array(
			'type' => 'pc_configurator',
			'limit' => -1,
			'status' => 'publish',
		) );

		include W2F_PC()->plugin_path() . '/includes/admin/views/html-simulator.php';
	}

	/**
	 * Handle save attribute.
	 */
	public function handle_save_attribute() {
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'w2f_pc_save_attribute' ) ) {
			wp_die( __( 'Security check failed.', 'w2f-pc-configurator' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permission denied.', 'w2f-pc-configurator' ) );
		}

		$key = isset( $_POST['attribute_key'] ) ? sanitize_text_field( $_POST['attribute_key'] ) : '';
		$is_new = isset( $_POST['is_new'] ) && '1' === $_POST['is_new'];

		if ( empty( $key ) ) {
			wp_redirect( admin_url( 'admin.php?page=w2f-pc-attributes&error=1' ) );
			exit;
		}

		$definition = array(
			'internal_key' => sanitize_text_field( $_POST['internal_key'] ?? '' ),
			'source_type' => sanitize_text_field( $_POST['source_type'] ?? 'taxonomy' ),
			'source_name' => sanitize_text_field( $_POST['source_name'] ?? '' ),
			'data_type' => sanitize_text_field( $_POST['data_type'] ?? 'text' ),
			'unit' => sanitize_text_field( $_POST['unit'] ?? '' ),
			'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
			'parse_pattern' => sanitize_text_field( $_POST['parse_pattern'] ?? '' ),
			'allowed_values' => array_filter( array_map( 'trim', explode( "\n", $_POST['allowed_values'] ?? '' ) ) ),
			'component_types' => isset( $_POST['component_types'] ) ? array_map( 'sanitize_text_field', (array) $_POST['component_types'] ) : array(),
		);

		$engine = W2F_PC_Compatibility_Engine::instance();
		$dictionary = $engine->get_attribute_dictionary();
		$dictionary->save( $key, $definition );

		wp_redirect( admin_url( 'admin.php?page=w2f-pc-attributes&saved=1' ) );
		exit;
	}

	/**
	 * Handle delete attribute.
	 */
	public function handle_delete_attribute() {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'w2f_pc_delete_attribute' ) ) {
			wp_die( __( 'Security check failed.', 'w2f-pc-configurator' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permission denied.', 'w2f-pc-configurator' ) );
		}

		$key = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';

		if ( ! empty( $key ) ) {
			$engine = W2F_PC_Compatibility_Engine::instance();
			$dictionary = $engine->get_attribute_dictionary();
			$dictionary->delete( $key );
		}

		wp_redirect( admin_url( 'admin.php?page=w2f-pc-attributes&deleted=1' ) );
		exit;
	}

	/**
	 * Handle save calculator.
	 */
	public function handle_save_calculator() {
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'w2f_pc_save_calculator' ) ) {
			wp_die( __( 'Security check failed.', 'w2f-pc-configurator' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permission denied.', 'w2f-pc-configurator' ) );
		}

		$calculator_id = sanitize_text_field( $_POST['calculator_id'] ?? '' );

		if ( empty( $calculator_id ) ) {
			wp_redirect( admin_url( 'admin.php?page=w2f-pc-calculators&error=1' ) );
			exit;
		}

		$config = array(
			'enabled' => isset( $_POST['enabled'] ) && '1' === $_POST['enabled'],
			'params' => array(),
		);

		// Handle params based on calculator type.
		if ( isset( $_POST['params'] ) && is_array( $_POST['params'] ) ) {
			foreach ( $_POST['params'] as $key => $value ) {
				if ( $key === 'contributors' && is_array( $value ) ) {
					$config['params'][ $key ] = array_map( 'sanitize_text_field', $value );
				} elseif ( is_numeric( $value ) ) {
					$config['params'][ $key ] = (float) $value;
				} else {
					$config['params'][ $key ] = sanitize_text_field( $value );
				}
			}
		}

		$engine = W2F_PC_Compatibility_Engine::instance();
		$registry = $engine->get_calculator_registry();
		$registry->save_config( $calculator_id, $config );

		wp_redirect( admin_url( 'admin.php?page=w2f-pc-calculators&saved=1' ) );
		exit;
	}

	/**
	 * Handle save policy.
	 */
	public function handle_save_policy() {
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'w2f_pc_save_policy' ) ) {
			wp_die( __( 'Security check failed.', 'w2f-pc-configurator' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permission denied.', 'w2f-pc-configurator' ) );
		}

		$policy_id = sanitize_text_field( $_POST['policy_id'] ?? '' );
		$is_new = isset( $_POST['is_new'] ) && '1' === $_POST['is_new'];

		$policy_config = array(
			'type' => sanitize_text_field( $_POST['policy_type'] ?? 'match' ),
			'name' => sanitize_text_field( $_POST['policy_name'] ?? '' ),
			'description' => sanitize_textarea_field( $_POST['policy_description'] ?? '' ),
			'enabled' => isset( $_POST['enabled'] ) && '1' === $_POST['enabled'],
			'priority' => (int) ( $_POST['priority'] ?? 50 ),
			'severity' => sanitize_text_field( $_POST['severity'] ?? 'block' ),
			'message' => sanitize_text_field( $_POST['message'] ?? '' ),
			'config' => array(),
		);

		// Handle config based on policy type.
		if ( isset( $_POST['config'] ) && is_array( $_POST['config'] ) ) {
			foreach ( $_POST['config'] as $key => $value ) {
				$policy_config['config'][ $key ] = sanitize_text_field( $value );
			}
		}

		$engine = W2F_PC_Compatibility_Engine::instance();
		$registry = $engine->get_policy_registry();

		if ( $is_new && empty( $policy_id ) ) {
			$policy_id = $registry->create( $policy_config['type'], $policy_config['name'], $policy_config );
		} else {
			$registry->save( $policy_id, $policy_config );
		}

		wp_redirect( admin_url( 'admin.php?page=w2f-pc-policies&saved=1' ) );
		exit;
	}

	/**
	 * Handle delete policy.
	 */
	public function handle_delete_policy() {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'w2f_pc_delete_policy' ) ) {
			wp_die( __( 'Security check failed.', 'w2f-pc-configurator' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permission denied.', 'w2f-pc-configurator' ) );
		}

		$policy_id = sanitize_text_field( $_GET['policy_id'] ?? '' );

		if ( ! empty( $policy_id ) ) {
			$engine = W2F_PC_Compatibility_Engine::instance();
			$registry = $engine->get_policy_registry();
			$registry->delete( $policy_id );
		}

		wp_redirect( admin_url( 'admin.php?page=w2f-pc-policies&deleted=1' ) );
		exit;
	}

	/**
	 * Handle save adjustment.
	 */
	public function handle_save_adjustment() {
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'w2f_pc_save_adjustment' ) ) {
			wp_die( __( 'Security check failed.', 'w2f-pc-configurator' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permission denied.', 'w2f-pc-configurator' ) );
		}

		$adjustment_id = sanitize_text_field( $_POST['adjustment_id'] ?? '' );
		$is_new = isset( $_POST['is_new'] ) && '1' === $_POST['is_new'];

		$adjustment_config = array(
			'name' => sanitize_text_field( $_POST['adjustment_name'] ?? '' ),
			'description' => sanitize_textarea_field( $_POST['adjustment_description'] ?? '' ),
			'enabled' => isset( $_POST['enabled'] ) && '1' === $_POST['enabled'],
			'priority' => (int) ( $_POST['priority'] ?? 50 ),
			'condition_logic' => sanitize_text_field( $_POST['condition_logic'] ?? 'AND' ),
			'target_key' => sanitize_text_field( $_POST['target_key'] ?? '' ),
			'effect' => sanitize_text_field( $_POST['effect'] ?? 'add' ),
			'delta' => (float) ( $_POST['delta'] ?? 0 ),
			'reason' => sanitize_text_field( $_POST['reason'] ?? '' ),
			'conditions' => array(),
		);

		// Parse conditions.
		if ( isset( $_POST['conditions'] ) && is_array( $_POST['conditions'] ) ) {
			foreach ( $_POST['conditions'] as $condition ) {
				if ( ! empty( $condition['fact_key'] ) ) {
					$adjustment_config['conditions'][] = array(
						'fact_key' => sanitize_text_field( $condition['fact_key'] ),
						'operator' => sanitize_text_field( $condition['operator'] ?? '=' ),
						'value' => sanitize_text_field( $condition['value'] ?? '' ),
					);
				}
			}
		}

		$engine = W2F_PC_Compatibility_Engine::instance();
		$registry = $engine->get_adjustment_registry();

		if ( $is_new && empty( $adjustment_id ) ) {
			$adjustment_id = $registry->create( $adjustment_config['name'], $adjustment_config );
		} else {
			$registry->save( $adjustment_id, $adjustment_config );
		}

		wp_redirect( admin_url( 'admin.php?page=w2f-pc-adjustments&saved=1' ) );
		exit;
	}

	/**
	 * Handle delete adjustment.
	 */
	public function handle_delete_adjustment() {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'w2f_pc_delete_adjustment' ) ) {
			wp_die( __( 'Security check failed.', 'w2f-pc-configurator' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permission denied.', 'w2f-pc-configurator' ) );
		}

		$adjustment_id = sanitize_text_field( $_GET['adjustment_id'] ?? '' );

		if ( ! empty( $adjustment_id ) ) {
			$engine = W2F_PC_Compatibility_Engine::instance();
			$registry = $engine->get_adjustment_registry();
			$registry->delete( $adjustment_id );
		}

		wp_redirect( admin_url( 'admin.php?page=w2f-pc-adjustments&deleted=1' ) );
		exit;
	}

	/**
	 * Handle save component mapping.
	 */
	public function handle_save_component_mapping() {
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'w2f_pc_save_component_mapping' ) ) {
			wp_die( __( 'Security check failed.', 'w2f-pc-configurator' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permission denied.', 'w2f-pc-configurator' ) );
		}

		$mappings = array();
		if ( isset( $_POST['mappings'] ) && is_array( $_POST['mappings'] ) ) {
			foreach ( $_POST['mappings'] as $type => $category_ids ) {
				$mappings[ sanitize_text_field( $type ) ] = array_map( 'intval', (array) $category_ids );
			}
		}

		$engine = W2F_PC_Compatibility_Engine::instance();
		$mapper = $engine->get_component_mapper();
		$mapper->save_mappings( $mappings );

		wp_redirect( admin_url( 'admin.php?page=w2f-pc-component-types&saved=1' ) );
		exit;
	}

	/**
	 * AJAX: Simulate build.
	 */
	public function ajax_simulate_build() {
		check_ajax_referer( 'w2f-pc-admin', 'security' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'w2f-pc-configurator' ) ) );
		}

		$configuration = isset( $_POST['configuration'] ) ? array_map( 'intval', (array) $_POST['configuration'] ) : array();
		$configurator_id = isset( $_POST['configurator_id'] ) ? (int) $_POST['configurator_id'] : 0;

		if ( empty( $configuration ) ) {
			wp_send_json_error( array( 'message' => __( 'No products selected.', 'w2f-pc-configurator' ) ) );
		}

		$engine = W2F_PC_Compatibility_Engine::instance();
		$result = $engine->simulate( $configuration, $configurator_id );

		wp_send_json_success( $result );
	}
}
