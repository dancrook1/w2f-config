<?php
/**
 * W2F_PC_Attribute_Dictionary class
 *
 * Maps WooCommerce attributes to typed internal facts.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attribute Dictionary - defines how WC attributes map to typed facts.
 *
 * @class    W2F_PC_Attribute_Dictionary
 * @version  2.0.0
 */
class W2F_PC_Attribute_Dictionary {

	/**
	 * The single instance of the class.
	 *
	 * @var W2F_PC_Attribute_Dictionary
	 */
	protected static $_instance = null;

	/**
	 * Option name for storing dictionary.
	 */
	const OPTION_NAME = 'w2f_pc_attribute_dictionary';

	/**
	 * Supported data types.
	 */
	const TYPE_NUMERIC = 'numeric';
	const TYPE_ENUM = 'enum';
	const TYPE_BOOLEAN = 'boolean';
	const TYPE_TEXT = 'text';
	const TYPE_SET = 'set';

	/**
	 * Attribute definitions.
	 *
	 * @var array
	 */
	private $definitions = array();

	/**
	 * Main instance.
	 *
	 * @return W2F_PC_Attribute_Dictionary
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
		$this->load_definitions();
	}

	/**
	 * Load definitions from database.
	 */
	private function load_definitions() {
		$saved = get_option( self::OPTION_NAME, array() );

		if ( ! empty( $saved ) && is_array( $saved ) ) {
			$this->definitions = $saved;
		} else {
			// Load defaults.
			$this->definitions = $this->get_default_definitions();
		}
	}

	/**
	 * Get default attribute definitions for PC components.
	 *
	 * @return array
	 */
	public function get_default_definitions() {
		return array(
			// CPU attributes.
			'cpu.socket' => array(
				'internal_key' => 'socket',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_socket',
				'data_type' => self::TYPE_ENUM,
				'unit' => '',
				'description' => 'CPU socket type (e.g., AM5, LGA1700)',
				'allowed_values' => array( 'AM4', 'AM5', 'LGA1151', 'LGA1200', 'LGA1700', 'LGA1851', 'sTRX4', 'sWRX8' ),
				'component_types' => array( 'cpu' ),
			),
			'cpu.tdp_w' => array(
				'internal_key' => 'tdp_w',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_tdp',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => 'W',
				'description' => 'CPU Thermal Design Power in Watts',
				'parse_pattern' => '/(\d+)\s*W?/i',
				'component_types' => array( 'cpu' ),
			),

			// Motherboard attributes.
			'motherboard.socket' => array(
				'internal_key' => 'socket',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_socket',
				'data_type' => self::TYPE_ENUM,
				'unit' => '',
				'description' => 'Motherboard CPU socket type',
				'allowed_values' => array( 'AM4', 'AM5', 'LGA1151', 'LGA1200', 'LGA1700', 'LGA1851', 'sTRX4', 'sWRX8' ),
				'component_types' => array( 'motherboard' ),
			),
			'motherboard.form_factor' => array(
				'internal_key' => 'form_factor',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_form-factor',
				'data_type' => self::TYPE_ENUM,
				'unit' => '',
				'description' => 'Motherboard form factor',
				'allowed_values' => array( 'ATX', 'Micro-ATX', 'Mini-ITX', 'E-ATX', 'XL-ATX' ),
				'component_types' => array( 'motherboard' ),
			),
			'motherboard.ram_type' => array(
				'internal_key' => 'ram_type',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_memory-type',
				'data_type' => self::TYPE_ENUM,
				'unit' => '',
				'description' => 'Supported RAM type',
				'allowed_values' => array( 'DDR4', 'DDR5' ),
				'component_types' => array( 'motherboard' ),
			),
			'motherboard.ram_slots' => array(
				'internal_key' => 'ram_slots',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_ram-slots',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => '',
				'description' => 'Number of RAM slots',
				'component_types' => array( 'motherboard' ),
			),
			'motherboard.m2_slots' => array(
				'internal_key' => 'm2_slots',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_m2-slots',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => '',
				'description' => 'Number of M.2 NVMe slots',
				'component_types' => array( 'motherboard' ),
			),

			// RAM attributes.
			'ram.type' => array(
				'internal_key' => 'type',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_memory-type',
				'data_type' => self::TYPE_ENUM,
				'unit' => '',
				'description' => 'RAM type (DDR4, DDR5)',
				'allowed_values' => array( 'DDR4', 'DDR5' ),
				'component_types' => array( 'ram' ),
			),
			'ram.speed_mhz' => array(
				'internal_key' => 'speed_mhz',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_memory-speed',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => 'MHz',
				'description' => 'RAM speed in MHz',
				'parse_pattern' => '/(\d+)\s*MHz?/i',
				'component_types' => array( 'ram' ),
			),
			'ram.capacity_gb' => array(
				'internal_key' => 'capacity_gb',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_memory-capacity',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => 'GB',
				'description' => 'Total RAM capacity in GB',
				'parse_pattern' => '/(\d+)\s*GB?/i',
				'component_types' => array( 'ram' ),
			),

			// GPU attributes.
			'gpu.power_w' => array(
				'internal_key' => 'power_w',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_gpu-tdp',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => 'W',
				'description' => 'GPU power consumption in Watts',
				'parse_pattern' => '/(\d+)\s*W?/i',
				'component_types' => array( 'gpu' ),
			),
			'gpu.length_mm' => array(
				'internal_key' => 'length_mm',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_gpu-length',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => 'mm',
				'description' => 'GPU length in millimeters',
				'parse_pattern' => '/(\d+)\s*mm?/i',
				'component_types' => array( 'gpu' ),
			),
			'gpu.min_psu_w' => array(
				'internal_key' => 'min_psu_w',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_min-psu',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => 'W',
				'description' => 'Minimum recommended PSU wattage',
				'parse_pattern' => '/(\d+)\s*W?/i',
				'component_types' => array( 'gpu' ),
			),

			// PSU attributes.
			'psu.wattage_w' => array(
				'internal_key' => 'wattage_w',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_wattage',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => 'W',
				'description' => 'PSU wattage output',
				'parse_pattern' => '/(\d+)\s*W?/i',
				'component_types' => array( 'psu' ),
			),
			'psu.form_factor' => array(
				'internal_key' => 'form_factor',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_psu-form-factor',
				'data_type' => self::TYPE_ENUM,
				'unit' => '',
				'description' => 'PSU form factor',
				'allowed_values' => array( 'ATX', 'SFX', 'SFX-L', 'TFX' ),
				'component_types' => array( 'psu' ),
			),

			// Case attributes.
			'case.max_gpu_length_mm' => array(
				'internal_key' => 'max_gpu_length_mm',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_max-gpu-length',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => 'mm',
				'description' => 'Maximum GPU length the case can fit',
				'parse_pattern' => '/(\d+)\s*mm?/i',
				'component_types' => array( 'case' ),
			),
			'case.max_cooler_height_mm' => array(
				'internal_key' => 'max_cooler_height_mm',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_max-cooler-height',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => 'mm',
				'description' => 'Maximum CPU cooler height',
				'parse_pattern' => '/(\d+)\s*mm?/i',
				'component_types' => array( 'case' ),
			),
			'case.supported_form_factors' => array(
				'internal_key' => 'supported_form_factors',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_supported-form-factors',
				'data_type' => self::TYPE_SET,
				'unit' => '',
				'description' => 'Motherboard form factors the case supports',
				'allowed_values' => array( 'ATX', 'Micro-ATX', 'Mini-ITX', 'E-ATX' ),
				'component_types' => array( 'case' ),
			),
			'case.supported_radiators' => array(
				'internal_key' => 'supported_radiators',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_radiator-support',
				'data_type' => self::TYPE_SET,
				'unit' => 'mm',
				'description' => 'Supported case radiator sizes (e.g., 240, 360)',
				'allowed_values' => array( '120', '140', '240', '280', '360', '420' ),
				'component_types' => array( 'case' ),
			),
			// Alias (clearer naming) for case radiator support.
			'case.supported_radiator_sizes_mm' => array(
				'internal_key' => 'supported_radiator_sizes_mm',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_radiator-support',
				'data_type' => self::TYPE_SET,
				'unit' => 'mm',
				'description' => 'Supported case radiator sizes in mm (e.g., 240, 360)',
				'allowed_values' => array( '120', '140', '240', '280', '360', '420' ),
				'component_types' => array( 'case' ),
			),

			// Cooler attributes.
			'cooler.type' => array(
				'internal_key' => 'type',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_cooler-type',
				'data_type' => self::TYPE_ENUM,
				'unit' => '',
				'description' => 'Cooler type (air, AIO liquid)',
				'allowed_values' => array( 'air', 'aio', 'custom_loop' ),
				'component_types' => array( 'cooler' ),
			),
			'cooler.height_mm' => array(
				'internal_key' => 'height_mm',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_cooler-height',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => 'mm',
				'description' => 'Cooler height for air coolers',
				'parse_pattern' => '/(\d+)\s*mm?/i',
				'component_types' => array( 'cooler' ),
			),
			'cooler.radiator_size_mm' => array(
				'internal_key' => 'radiator_size_mm',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_radiator-size',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => 'mm',
				'description' => 'Radiator size for AIO coolers',
				'parse_pattern' => '/(\d+)\s*mm?/i',
				'component_types' => array( 'cooler' ),
			),
			'cooler.supported_sockets' => array(
				'internal_key' => 'supported_sockets',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_cooler-sockets',
				'data_type' => self::TYPE_SET,
				'unit' => '',
				'description' => 'CPU sockets the cooler supports',
				'allowed_values' => array( 'AM4', 'AM5', 'LGA1151', 'LGA1200', 'LGA1700', 'LGA1851' ),
				'component_types' => array( 'cooler' ),
			),
			'cooler.tdp_w' => array(
				'internal_key' => 'tdp_w',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_cooler-tdp',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => 'W',
				'description' => 'Maximum TDP the cooler can handle',
				'parse_pattern' => '/(\d+)\s*W?/i',
				'component_types' => array( 'cooler' ),
			),

			// Storage attributes.
			'storage.interface' => array(
				'internal_key' => 'interface',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_storage-interface',
				'data_type' => self::TYPE_ENUM,
				'unit' => '',
				'description' => 'Storage interface type',
				'allowed_values' => array( 'NVMe', 'SATA', 'U.2' ),
				'component_types' => array( 'storage' ),
			),
			'storage.capacity_gb' => array(
				'internal_key' => 'capacity_gb',
				'source_type' => 'taxonomy',
				'source_name' => 'pa_storage-capacity',
				'data_type' => self::TYPE_NUMERIC,
				'unit' => 'GB',
				'description' => 'Storage capacity',
				'parse_pattern' => '/(\d+)\s*(?:GB|TB)?/i',
				'parse_multiplier' => array( 'TB' => 1024 ),
				'component_types' => array( 'storage' ),
			),
		);
	}

	/**
	 * Get all attribute definitions.
	 *
	 * @return array
	 */
	public function get_all() {
		return $this->definitions;
	}

	/**
	 * Get definitions for a specific component type.
	 *
	 * @param  string $component_type Component type.
	 * @return array
	 */
	public function get_for_component( $component_type ) {
		$result = array();

		foreach ( $this->definitions as $key => $definition ) {
			if ( isset( $definition['component_types'] ) && in_array( $component_type, $definition['component_types'], true ) ) {
				$result[ $key ] = $definition;
			}
		}

		return $result;
	}

	/**
	 * Get a single definition.
	 *
	 * @param  string $key Definition key.
	 * @return array|null
	 */
	public function get( $key ) {
		return isset( $this->definitions[ $key ] ) ? $this->definitions[ $key ] : null;
	}

	/**
	 * Save a definition.
	 *
	 * @param string $key        Definition key.
	 * @param array  $definition Definition data.
	 */
	public function save( $key, $definition ) {
		$this->definitions[ $key ] = $definition;
		update_option( self::OPTION_NAME, $this->definitions );
	}

	/**
	 * Delete a definition.
	 *
	 * @param string $key Definition key.
	 */
	public function delete( $key ) {
		if ( isset( $this->definitions[ $key ] ) ) {
			unset( $this->definitions[ $key ] );
			update_option( self::OPTION_NAME, $this->definitions );
		}
	}

	/**
	 * Save all definitions.
	 *
	 * @param array $definitions All definitions.
	 */
	public function save_all( $definitions ) {
		$this->definitions = $definitions;
		update_option( self::OPTION_NAME, $this->definitions );
	}

	/**
	 * Reset to defaults.
	 */
	public function reset_to_defaults() {
		$this->definitions = $this->get_default_definitions();
		update_option( self::OPTION_NAME, $this->definitions );
	}

	/**
	 * Extract facts from a WooCommerce product.
	 *
	 * @param  WC_Product $product        Product to extract from.
	 * @param  string     $component_type Component type context.
	 * @return array Extracted facts (key => value).
	 */
	public function extract_facts_from_product( $product, $component_type ) {
		$facts = array();
		$definitions = $this->get_for_component( $component_type );

		foreach ( $definitions as $def_key => $definition ) {
			$value = $this->extract_value( $product, $definition );

			if ( $value !== null ) {
				// Use the internal key for storage.
				$facts[ $definition['internal_key'] ] = $value;
			}
		}

		return $facts;
	}

	/**
	 * Extract a single value from a product based on definition.
	 *
	 * @param  WC_Product $product    Product.
	 * @param  array      $definition Attribute definition.
	 * @return mixed|null Extracted value or null.
	 */
	private function extract_value( $product, $definition ) {
		$raw_value = null;

		// Get raw value based on source type.
		if ( 'taxonomy' === $definition['source_type'] ) {
			$raw_value = $this->get_taxonomy_value( $product, $definition['source_name'] );
		} elseif ( 'meta' === $definition['source_type'] ) {
			$raw_value = $product->get_meta( $definition['source_name'], true );
		}

		if ( empty( $raw_value ) && $raw_value !== '0' && $raw_value !== 0 ) {
			return null;
		}

		// Parse and convert value based on data type.
		return $this->parse_value( $raw_value, $definition );
	}

	/**
	 * Get value from product taxonomy attribute.
	 *
	 * @param  WC_Product $product       Product.
	 * @param  string     $taxonomy_name Taxonomy name (e.g., pa_socket).
	 * @return string|array|null
	 */
	private function get_taxonomy_value( $product, $taxonomy_name ) {
		$terms = wp_get_post_terms( $product->get_id(), $taxonomy_name );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			// Try getting from product attributes.
			$attributes = $product->get_attributes();
			if ( isset( $attributes[ $taxonomy_name ] ) ) {
				$attribute = $attributes[ $taxonomy_name ];
				if ( $attribute->is_taxonomy() ) {
					$attr_terms = wp_get_post_terms( $product->get_id(), $taxonomy_name );
					if ( ! is_wp_error( $attr_terms ) && ! empty( $attr_terms ) ) {
						$terms = $attr_terms;
					}
				} else {
					// Custom attribute.
					$options = $attribute->get_options();
					return ! empty( $options ) ? ( count( $options ) === 1 ? $options[0] : $options ) : null;
				}
			}
		}

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}

		// Return single value or array.
		if ( count( $terms ) === 1 ) {
			return $terms[0]->name;
		}

		return array_map( function( $term ) {
			return $term->name;
		}, $terms );
	}

	/**
	 * Parse and convert a raw value based on definition.
	 *
	 * @param  mixed $raw_value  Raw value.
	 * @param  array $definition Attribute definition.
	 * @return mixed Parsed value.
	 */
	private function parse_value( $raw_value, $definition ) {
		$data_type = $definition['data_type'] ?? self::TYPE_TEXT;

		switch ( $data_type ) {
			case self::TYPE_NUMERIC:
				return $this->parse_numeric( $raw_value, $definition );

			case self::TYPE_BOOLEAN:
				return $this->parse_boolean( $raw_value );

			case self::TYPE_ENUM:
				return $this->parse_enum( $raw_value, $definition );

			case self::TYPE_SET:
				return $this->parse_set( $raw_value, $definition );

			case self::TYPE_TEXT:
			default:
				return is_array( $raw_value ) ? implode( ', ', $raw_value ) : (string) $raw_value;
		}
	}

	/**
	 * Parse a numeric value.
	 *
	 * @param  mixed $value      Raw value.
	 * @param  array $definition Definition.
	 * @return float|null
	 */
	private function parse_numeric( $value, $definition ) {
		if ( is_numeric( $value ) ) {
			return (float) $value;
		}

		if ( ! is_string( $value ) ) {
			return null;
		}

		// Use parse pattern if defined.
		if ( isset( $definition['parse_pattern'] ) ) {
			if ( preg_match( $definition['parse_pattern'], $value, $matches ) ) {
				$number = (float) $matches[1];

				// Apply multiplier if defined (e.g., TB to GB).
				if ( isset( $definition['parse_multiplier'] ) && is_array( $definition['parse_multiplier'] ) ) {
					foreach ( $definition['parse_multiplier'] as $unit => $multiplier ) {
						if ( stripos( $value, $unit ) !== false ) {
							$number *= $multiplier;
							break;
						}
					}
				}

				return $number;
			}
		}

		// Default: extract first number.
		if ( preg_match( '/[\d.]+/', $value, $matches ) ) {
			return (float) $matches[0];
		}

		return null;
	}

	/**
	 * Parse a boolean value.
	 *
	 * @param  mixed $value Raw value.
	 * @return bool
	 */
	private function parse_boolean( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$true_values = array( 'yes', 'true', '1', 'on', 'enabled' );
		return in_array( strtolower( trim( (string) $value ) ), $true_values, true );
	}

	/**
	 * Parse an enum value.
	 *
	 * @param  mixed $value      Raw value.
	 * @param  array $definition Definition.
	 * @return string|null
	 */
	private function parse_enum( $value, $definition ) {
		$value = trim( (string) $value );

		// Validate against allowed values if defined.
		if ( isset( $definition['allowed_values'] ) && is_array( $definition['allowed_values'] ) ) {
			// Case-insensitive match.
			foreach ( $definition['allowed_values'] as $allowed ) {
				if ( strcasecmp( $value, $allowed ) === 0 ) {
					return $allowed; // Return the canonical form.
				}
			}
			// Not in allowed values, but still return the value.
		}

		return $value;
	}

	/**
	 * Parse a set value (multiple enums).
	 *
	 * @param  mixed $value      Raw value.
	 * @param  array $definition Definition.
	 * @return array
	 */
	private function parse_set( $value, $definition ) {
		if ( is_array( $value ) ) {
			$values = $value;
		} else {
			// Split by common delimiters.
			$values = preg_split( '/[,;|]+/', (string) $value );
		}

		$result = array();
		foreach ( $values as $v ) {
			$parsed = $this->parse_enum( $v, $definition );
			if ( $parsed ) {
				$result[] = $parsed;
			}
		}

		return $result;
	}

	/**
	 * Get all supported data types.
	 *
	 * @return array
	 */
	public function get_data_types() {
		return array(
			self::TYPE_NUMERIC => __( 'Numeric', 'w2f-pc-configurator' ),
			self::TYPE_ENUM => __( 'Enum (single value)', 'w2f-pc-configurator' ),
			self::TYPE_BOOLEAN => __( 'Boolean', 'w2f-pc-configurator' ),
			self::TYPE_TEXT => __( 'Text', 'w2f-pc-configurator' ),
			self::TYPE_SET => __( 'Set (multiple values)', 'w2f-pc-configurator' ),
		);
	}

	/**
	 * Get available WooCommerce attribute taxonomies.
	 *
	 * @return array
	 */
	public function get_available_taxonomies() {
		$result = array();
		$taxonomies = wc_get_attribute_taxonomies();

		foreach ( $taxonomies as $tax ) {
			$result[ 'pa_' . $tax->attribute_name ] = $tax->attribute_label;
		}

		return $result;
	}
}
