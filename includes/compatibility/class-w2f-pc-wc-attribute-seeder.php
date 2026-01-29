<?php
/**
 * W2F_PC_WC_Attribute_Seeder
 *
 * Creates WooCommerce global product attributes (pa_*) and terms required by
 * the default Attribute Dictionary + seeded compatibility rules.
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class W2F_PC_WC_Attribute_Seeder {

	/**
	 * Seed WooCommerce attributes and terms from the dictionary definitions.
	 *
	 * @param W2F_PC_Attribute_Dictionary $dictionary Attribute dictionary instance.
	 * @param array                       $args {
	 *     Optional args.
	 *
	 *     @type bool $include_numeric_common_terms Whether to seed common numeric terms. Default true.
	 * }
	 * @return array Report.
	 */
	public function seed_from_dictionary( $dictionary, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'include_numeric_common_terms' => true,
			)
		);

		$report = array(
			'created_attributes' => array(),
			'existing_attributes' => array(),
			'created_terms' => array(), // taxonomy => [terms...]
			'existing_terms' => array(), // taxonomy => [terms...]
			'errors' => array(),
		);

		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) || ! function_exists( 'wc_create_attribute' ) ) {
			$report['errors'][] = 'WooCommerce attribute functions are not available.';
			return $report;
		}

		$definitions = $dictionary->get_all();

		// Collect required taxonomies and their allowed values (unioned).
		$required = array(); // taxonomy => [label, terms[]]
		foreach ( $definitions as $def ) {
			$source_type = $def['source_type'] ?? 'taxonomy';
			$source_name = $def['source_name'] ?? '';

			if ( 'taxonomy' !== $source_type || empty( $source_name ) ) {
				continue;
			}

			if ( strpos( $source_name, 'pa_' ) !== 0 ) {
				continue;
			}

			if ( ! isset( $required[ $source_name ] ) ) {
				$required[ $source_name ] = array(
					'label' => $this->guess_attribute_label_from_taxonomy( $source_name ),
					'terms' => array(),
				);
			}

			$allowed = isset( $def['allowed_values'] ) && is_array( $def['allowed_values'] ) ? $def['allowed_values'] : array();
			foreach ( $allowed as $term ) {
				$term = trim( (string) $term );
				if ( '' === $term ) {
					continue;
				}
				$required[ $source_name ]['terms'][] = $term;
			}
		}

		// Optionally seed common numeric terms for a few numeric attributes.
		if ( ! empty( $args['include_numeric_common_terms'] ) ) {
			$numeric_seed = $this->get_common_numeric_terms();
			foreach ( $numeric_seed as $taxonomy => $terms ) {
				if ( ! isset( $required[ $taxonomy ] ) ) {
					$required[ $taxonomy ] = array(
						'label' => $this->guess_attribute_label_from_taxonomy( $taxonomy ),
						'terms' => array(),
					);
				}
				$required[ $taxonomy ]['terms'] = array_merge( $required[ $taxonomy ]['terms'], $terms );
			}
		}

		// Normalize term lists.
		foreach ( $required as $taxonomy => $info ) {
			$required[ $taxonomy ]['terms'] = array_values( array_unique( array_filter( array_map( 'trim', (array) $info['terms'] ) ) ) );
		}

		// Create missing global attributes.
		$existing_taxonomies = wc_get_attribute_taxonomies();
		$existing_slugs      = array();
		foreach ( (array) $existing_taxonomies as $tax ) {
			$existing_slugs[] = 'pa_' . $tax->attribute_name;
		}

		foreach ( $required as $taxonomy => $info ) {
			if ( in_array( $taxonomy, $existing_slugs, true ) ) {
				$report['existing_attributes'][] = $taxonomy;
				continue;
			}

			$slug  = substr( $taxonomy, 3 ); // remove "pa_".
			$label = $info['label'];

			$create = wc_create_attribute(
				array(
					'name'         => $label,
					'slug'         => $slug,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => false,
				)
			);

			if ( is_wp_error( $create ) ) {
				$report['errors'][] = sprintf( 'Failed to create attribute %s: %s', $taxonomy, $create->get_error_message() );
				continue;
			}

			$report['created_attributes'][] = $taxonomy;
		}

		// Ensure taxonomies are registered in this request so we can insert terms.
		delete_transient( 'wc_attribute_taxonomies' );
		if ( class_exists( 'WC_Post_Types' ) && method_exists( 'WC_Post_Types', 'register_taxonomies' ) ) {
			WC_Post_Types::register_taxonomies();
		}

		// Insert terms for enum/set attributes.
		foreach ( $required as $taxonomy => $info ) {
			if ( empty( $info['terms'] ) ) {
				continue;
			}

			if ( ! taxonomy_exists( $taxonomy ) ) {
				// If the taxonomy isn't registered yet, skip term creation for now.
				$report['errors'][] = sprintf( 'Taxonomy %s is not registered yet; terms will be created on next request.', $taxonomy );
				continue;
			}

			foreach ( $info['terms'] as $term_name ) {
				if ( term_exists( $term_name, $taxonomy ) ) {
					$report['existing_terms'][ $taxonomy ][] = $term_name;
					continue;
				}

				$insert = wp_insert_term( $term_name, $taxonomy );
				if ( is_wp_error( $insert ) ) {
					$report['errors'][] = sprintf( 'Failed to create term "%s" in %s: %s', $term_name, $taxonomy, $insert->get_error_message() );
					continue;
				}

				$report['created_terms'][ $taxonomy ][] = $term_name;
			}
		}

		// Ensure arrays exist for taxonomies touched.
		foreach ( array_keys( $required ) as $taxonomy ) {
			if ( ! isset( $report['created_terms'][ $taxonomy ] ) ) {
				$report['created_terms'][ $taxonomy ] = array();
			}
			if ( ! isset( $report['existing_terms'][ $taxonomy ] ) ) {
				$report['existing_terms'][ $taxonomy ] = array();
			}
		}

		return $report;
	}

	/**
	 * Guess a user-friendly label from taxonomy name.
	 *
	 * @param string $taxonomy Taxonomy name (e.g. pa_form-factor).
	 * @return string
	 */
	private function guess_attribute_label_from_taxonomy( $taxonomy ) {
		$slug = substr( (string) $taxonomy, 3 ); // remove pa_.
		$slug = str_replace( array( '_', '-' ), ' ', $slug );
		$slug = trim( $slug );

		$label = ucwords( $slug );

		$replacements = array(
			'Tdp' => 'TDP',
			'Gpu' => 'GPU',
			'Cpu' => 'CPU',
			'Psu' => 'PSU',
			'Ram' => 'RAM',
			'M2'  => 'M.2',
			'Aio' => 'AIO',
		);
		$label = strtr( $label, $replacements );

		return $label ? $label : __( 'Attribute', 'w2f-pc-configurator' );
	}

	/**
	 * Optional: seed some common numeric terms to make data entry easier.
	 *
	 * These are safe defaults and can be ignored if you prefer free-text numeric values.
	 *
	 * @return array taxonomy => terms
	 */
	private function get_common_numeric_terms() {
		return array(
			'pa_wattage' => array( '450', '500', '550', '600', '650', '700', '750', '800', '850', '1000', '1200' ),
			'pa_tdp' => array( '35', '45', '65', '95', '105', '125', '170' ),
			'pa_gpu-tdp' => array( '150', '200', '250', '300', '320', '350', '400', '450' ),
			'pa_gpu-length' => array( '200', '240', '270', '300', '320', '340', '360', '380', '400' ),
			'pa_max-gpu-length' => array( '280', '320', '340', '360', '380', '400' ),
			'pa_radiator-size' => array( '120', '140', '240', '280', '360', '420' ),
			'pa_ram-slots' => array( '2', '4', '8' ),
			'pa_m2-slots' => array( '1', '2', '3', '4', '5' ),
			'pa_memory-speed' => array( '2666', '3200', '3600', '5200', '5600', '6000', '6400' ),
			'pa_memory-capacity' => array( '8', '16', '32', '64', '128' ),
			'pa_storage-capacity' => array( '256', '512', '1024', '2048', '4096', '8192' ),
			'pa_min-psu' => array( '450', '550', '650', '750', '850', '1000' ),
			'pa_cooler-height' => array( '145', '155', '165', '175' ),
			'pa_max-cooler-height' => array( '150', '160', '170', '180' ),
		);
	}
}

