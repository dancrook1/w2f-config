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
	<div class="options_group" style="border-bottom: 2px solid #ddd; padding-bottom: 15px; margin-bottom: 20px;">
		<h3 style="margin-top: 0;"><?php esc_html_e( 'Import / Export Configuration', 'w2f-pc-configurator' ); ?></h3>
		<p class="form-field">
			<button type="button" id="w2f-pc-export-config" class="button button-secondary">
				<?php esc_html_e( 'Export Configuration', 'w2f-pc-configurator' ); ?>
			</button>
			<button type="button" id="w2f-pc-import-config" class="button button-secondary">
				<?php esc_html_e( 'Import Configuration', 'w2f-pc-configurator' ); ?>
			</button>
			<input type="file" id="w2f-pc-import-file" accept=".json" style="display: none;" />
			<?php echo wc_help_tip( __( 'Export your configuration to use it on another PC Configurator product, or import a configuration from another product.', 'w2f-pc-configurator' ) ); ?>
		</p>
		<div id="w2f-pc-import-message" style="display: none; margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;"></div>
	</div>
	
	<div class="options_group">
		<h3><?php esc_html_e( 'Default Configuration', 'w2f-pc-configurator' ); ?></h3>
		
		<!-- Price Breakdown Display -->
		<div class="w2f-pc-admin-price-breakdown" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
			<h4 style="margin-top: 0;"><?php esc_html_e( 'Price Calculation', 'w2f-pc-configurator' ); ?></h4>
			<table class="widefat" style="margin-bottom: 0;">
				<tbody>
					<tr>
						<td style="width: 50%; padding: 8px 0;">
							<strong><?php esc_html_e( 'Component Total:', 'w2f-pc-configurator' ); ?></strong>
						</td>
						<td style="padding: 8px 0;">
							<span class="w2f-pc-admin-component-total"><?php echo wp_kses_post( wc_price( $component_total ) ); ?></span>
							<small style="display: block; color: #666; margin-top: 4px;">
								<?php esc_html_e( '(Sum of all default component prices)', 'w2f-pc-configurator' ); ?>
							</small>
						</td>
					</tr>
					<tr>
						<td style="padding: 8px 0;">
							<strong><?php esc_html_e( 'Target Price:', 'w2f-pc-configurator' ); ?></strong>
						</td>
						<td style="padding: 8px 0;">
							<span class="w2f-pc-admin-target-price"><?php echo wp_kses_post( wc_price( $default_price ) ); ?></span>
							<small style="display: block; color: #666; margin-top: 4px;">
								<?php esc_html_e( '(Default price set below)', 'w2f-pc-configurator' ); ?>
							</small>
						</td>
					</tr>
					<?php if ( $discount_percentage > 0 ) : ?>
					<tr style="background-color: #fff3cd; border-top: 2px solid #ffc107;">
						<td style="padding: 8px 0;">
							<strong style="color: #856404;"><?php esc_html_e( 'Discount:', 'w2f-pc-configurator' ); ?></strong>
						</td>
						<td style="padding: 8px 0;">
							<span class="w2f-pc-admin-discount-percentage" style="color: #856404; font-weight: bold;">
								<?php echo esc_html( number_format( $discount_percentage, 2 ) ); ?>%
							</span>
							<small style="display: block; color: #856404; margin-top: 4px;">
								<?php 
								$discount_amount = $component_total - $default_price;
								echo esc_html( sprintf( 
									__( '(%s discount applied)', 'w2f-pc-configurator' ), 
									wc_price( $discount_amount ) 
								) ); 
								?>
							</small>
						</td>
					</tr>
					<?php else : ?>
					<tr class="w2f-pc-admin-discount-row" style="display: none;">
						<td style="padding: 8px 0;">
							<strong><?php esc_html_e( 'Discount:', 'w2f-pc-configurator' ); ?></strong>
						</td>
						<td style="padding: 8px 0;">
							<span class="w2f-pc-admin-discount-percentage">0%</span>
						</td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		
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

