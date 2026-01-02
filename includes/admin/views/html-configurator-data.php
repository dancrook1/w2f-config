<?php
/**
 * PC Configurator data panel template
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="pc_configurator_data" class="panel woocommerce_options_panel">
	<div class="options_group">
		<h3><?php esc_html_e( 'Default Configuration', 'w2f-pc-configurator' ); ?></h3>
		<p class="form-field">
			<label for="w2f_pc_default_price"><?php esc_html_e( 'Default Price', 'w2f-pc-configurator' ); ?></label>
			<input type="number" id="w2f_pc_default_price" name="w2f_pc_default_price" value="<?php echo esc_attr( $default_price ); ?>" step="0.01" min="0" class="short" />
			<?php echo wc_help_tip( __( 'The starting price for the default configuration. When customers change any component, pricing switches to component-based calculation.', 'w2f-pc-configurator' ) ); ?>
		</p>
	</div>

	<div class="options_group">
		<h3><?php esc_html_e( 'Tabs', 'w2f-pc-configurator' ); ?></h3>
		<p class="toolbar">
			<button type="button" class="button add_tab"><?php esc_html_e( 'Add Tab', 'w2f-pc-configurator' ); ?></button>
		</p>
		<div class="w2f_pc_tabs">
			<?php
			if ( ! empty( $tabs ) && is_array( $tabs ) ) {
				foreach ( $tabs as $index => $tab_name ) {
					?>
					<p class="form-field w2f-pc-tab-item">
						<input type="text" name="w2f_pc_tabs[]" value="<?php echo esc_attr( $tab_name ); ?>" placeholder="<?php esc_attr_e( 'Tab name', 'w2f-pc-configurator' ); ?>" class="regular-text" />
						<button type="button" class="button remove_tab"><?php esc_html_e( 'Remove', 'w2f-pc-configurator' ); ?></button>
					</p>
					<?php
				}
			}
			?>
		</div>
		<?php echo wc_help_tip( __( 'Define tabs to organize components. Components can be assigned to tabs in their settings below.', 'w2f-pc-configurator' ) ); ?>
	</div>

	<div class="options_group">
		<h3><?php esc_html_e( 'Components', 'w2f-pc-configurator' ); ?></h3>
		<p class="toolbar">
			<button type="button" class="button add_component"><?php esc_html_e( 'Add Component', 'w2f-pc-configurator' ); ?></button>
		</p>

		<div class="w2f_pc_components">
			<?php
			if ( ! empty( $components_data ) ) {
				foreach ( $components_data as $component_id => $component_data ) {
					// Make tabs available to component template.
					$component_tabs = isset( $tabs ) ? $tabs : array();
					include W2F_PC()->plugin_path() . '/includes/admin/views/html-component.php';
				}
			}
			?>
		</div>
	</div>
</div>

<script type="text/template" id="tmpl-w2f-pc-component">
	<?php
		$component_id = '{{component_id}}';
		$component_data = array(
			'title'              => '',
			'description'        => '',
			'optional'           => 'no',
			'display_mode'       => 'dropdown',
			'tab'                => '',
			'show_search'        => 'yes',
			'show_dropdown_image' => 'yes',
			'options'            => array(),
			'categories'         => array(),
		);
		// Make tabs available to template.
		$component_tabs = isset( $tabs ) ? $tabs : array();
	include W2F_PC()->plugin_path() . '/includes/admin/views/html-component.php';
	?>
</script>

