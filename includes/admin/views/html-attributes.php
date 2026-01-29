<?php
/**
 * Attribute Dictionary page template
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap w2f-pc-admin">
	<h1><?php esc_html_e( 'Attribute Dictionary', 'w2f-pc-configurator' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Define how WooCommerce product attributes map to typed facts used by the compatibility system. The engine reads ALL product data through this dictionary.', 'w2f-pc-configurator' ); ?>
	</p>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Attribute saved.', 'w2f-pc-configurator' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Attribute deleted.', 'w2f-pc-configurator' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['seeded'] ) ) : ?>
		<?php
		$seed_report = get_transient( 'w2f_pc_seed_report_' . get_current_user_id() );
		?>
		<div class="notice notice-success">
			<p><strong><?php esc_html_e( 'WooCommerce attributes seeded.', 'w2f-pc-configurator' ); ?></strong></p>
			<?php if ( is_array( $seed_report ) ) : ?>
				<p>
					<?php
					printf(
						/* translators: 1: created attributes count, 2: created terms count */
						esc_html__( 'Created %1$d attributes and %2$d terms.', 'w2f-pc-configurator' ),
						(int) count( $seed_report['created_attributes'] ?? array() ),
						(int) array_sum( array_map( 'count', (array) ( $seed_report['created_terms'] ?? array() ) ) )
					);
					?>
				</p>
				<?php if ( ! empty( $seed_report['errors'] ) ) : ?>
					<p><strong><?php esc_html_e( 'Notes:', 'w2f-pc-configurator' ); ?></strong></p>
					<ul style="margin-left: 20px; list-style: disc;">
						<?php foreach ( (array) $seed_report['errors'] as $err ) : ?>
							<li><?php echo esc_html( $err ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<?php if ( isset( $_GET['seed_error'] ) ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'Could not seed WooCommerce attributes. Please check WooCommerce is active.', 'w2f-pc-configurator' ); ?></p></div>
	<?php endif; ?>

	<div class="w2f-pc-two-column">
		<div class="w2f-pc-column-main">
			<h2><?php esc_html_e( 'Defined Attributes', 'w2f-pc-configurator' ); ?></h2>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Key', 'w2f-pc-configurator' ); ?></th>
						<th><?php esc_html_e( 'Source', 'w2f-pc-configurator' ); ?></th>
						<th><?php esc_html_e( 'Data Type', 'w2f-pc-configurator' ); ?></th>
						<th><?php esc_html_e( 'Component Types', 'w2f-pc-configurator' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'w2f-pc-configurator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $attributes ) ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No attributes defined. Add one using the form.', 'w2f-pc-configurator' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $attributes as $key => $attr ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $key ); ?></strong>
									<?php if ( ! empty( $attr['description'] ) ) : ?>
										<br><small><?php echo esc_html( $attr['description'] ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<code><?php echo esc_html( $attr['source_name'] ?? '' ); ?></code>
									<br><small><?php echo esc_html( $attr['source_type'] ?? 'taxonomy' ); ?></small>
								</td>
								<td>
									<?php echo esc_html( $data_types[ $attr['data_type'] ] ?? $attr['data_type'] ); ?>
									<?php if ( ! empty( $attr['unit'] ) ) : ?>
										<br><small><?php echo esc_html( $attr['unit'] ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<?php 
									$types = isset( $attr['component_types'] ) ? $attr['component_types'] : array();
									echo esc_html( implode( ', ', $types ) ); 
									?>
								</td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=w2f-pc-attributes&edit=' . urlencode( $key ) ) ); ?>"><?php esc_html_e( 'Edit', 'w2f-pc-configurator' ); ?></a> |
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=w2f_pc_delete_attribute&key=' . urlencode( $key ) ), 'w2f_pc_delete_attribute' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this attribute?', 'w2f-pc-configurator' ); ?>');"><?php esc_html_e( 'Delete', 'w2f-pc-configurator' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="w2f-pc-column-sidebar">
			<div class="w2f-pc-card">
				<h2><?php esc_html_e( 'Quick Setup', 'w2f-pc-configurator' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Create the WooCommerce global attributes (pa_*) and common term values required by the default compatibility rules.', 'w2f-pc-configurator' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 20px;">
					<?php wp_nonce_field( 'w2f_pc_seed_wc_attributes' ); ?>
					<input type="hidden" name="action" value="w2f_pc_seed_wc_attributes" />
					<label style="display:block; margin: 8px 0;">
						<input type="checkbox" name="include_numeric_common_terms" value="1" checked />
						<?php esc_html_e( 'Seed common numeric values (recommended)', 'w2f-pc-configurator' ); ?>
					</label>
					<?php submit_button( __( 'Seed WooCommerce Attributes/Terms', 'w2f-pc-configurator' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<div class="w2f-pc-card">
				<h2><?php echo $edit_attribute ? esc_html__( 'Edit Attribute', 'w2f-pc-configurator' ) : esc_html__( 'Add Attribute', 'w2f-pc-configurator' ); ?></h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'w2f_pc_save_attribute' ); ?>
					<input type="hidden" name="action" value="w2f_pc_save_attribute" />
					<input type="hidden" name="is_new" value="<?php echo $edit_attribute ? '0' : '1'; ?>" />

					<p>
						<label><strong><?php esc_html_e( 'Attribute Key', 'w2f-pc-configurator' ); ?></strong></label><br>
						<input type="text" name="attribute_key" value="<?php echo esc_attr( $edit_key ); ?>" class="regular-text" required placeholder="e.g., cpu.socket" <?php echo $edit_attribute ? 'readonly' : ''; ?> />
						<br><small><?php esc_html_e( 'Format: component.attribute (e.g., cpu.socket, gpu.power_w)', 'w2f-pc-configurator' ); ?></small>
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Internal Key', 'w2f-pc-configurator' ); ?></strong></label><br>
						<input type="text" name="internal_key" value="<?php echo esc_attr( $edit_attribute['internal_key'] ?? '' ); ?>" class="regular-text" required placeholder="e.g., socket" />
						<br><small><?php esc_html_e( 'Short name used internally (e.g., socket, power_w)', 'w2f-pc-configurator' ); ?></small>
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Source Type', 'w2f-pc-configurator' ); ?></strong></label><br>
						<select name="source_type">
							<option value="taxonomy" <?php selected( ( $edit_attribute['source_type'] ?? 'taxonomy' ), 'taxonomy' ); ?>><?php esc_html_e( 'WC Attribute Taxonomy (pa_*)', 'w2f-pc-configurator' ); ?></option>
							<option value="meta" <?php selected( ( $edit_attribute['source_type'] ?? '' ), 'meta' ); ?>><?php esc_html_e( 'Product Meta', 'w2f-pc-configurator' ); ?></option>
						</select>
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Source Name', 'w2f-pc-configurator' ); ?></strong></label><br>
						<select name="source_name" class="regular-text">
							<option value=""><?php esc_html_e( '-- Select --', 'w2f-pc-configurator' ); ?></option>
							<?php foreach ( $taxonomies as $tax_name => $tax_label ) : ?>
								<option value="<?php echo esc_attr( $tax_name ); ?>" <?php selected( ( $edit_attribute['source_name'] ?? '' ), $tax_name ); ?>>
									<?php echo esc_html( $tax_label ); ?> (<?php echo esc_html( $tax_name ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
						<br><small><?php esc_html_e( 'Or enter custom meta key below:', 'w2f-pc-configurator' ); ?></small>
						<input type="text" name="source_name_custom" value="" class="regular-text" placeholder="_custom_meta_key" />
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Data Type', 'w2f-pc-configurator' ); ?></strong></label><br>
						<select name="data_type">
							<?php foreach ( $data_types as $type_key => $type_label ) : ?>
								<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( ( $edit_attribute['data_type'] ?? 'text' ), $type_key ); ?>>
									<?php echo esc_html( $type_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Unit (for numeric)', 'w2f-pc-configurator' ); ?></strong></label><br>
						<input type="text" name="unit" value="<?php echo esc_attr( $edit_attribute['unit'] ?? '' ); ?>" class="small-text" placeholder="W, mm, MHz" />
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Parse Pattern (optional)', 'w2f-pc-configurator' ); ?></strong></label><br>
						<input type="text" name="parse_pattern" value="<?php echo esc_attr( $edit_attribute['parse_pattern'] ?? '' ); ?>" class="regular-text" placeholder="/(\d+)\s*W?/i" />
						<br><small><?php esc_html_e( 'Regex to extract numeric value from text like "240mm" or "800W"', 'w2f-pc-configurator' ); ?></small>
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Allowed Values (for enum/set)', 'w2f-pc-configurator' ); ?></strong></label><br>
						<textarea name="allowed_values" rows="4" class="regular-text" placeholder="AM5&#10;LGA1700&#10;DDR5"><?php 
							$values = isset( $edit_attribute['allowed_values'] ) ? $edit_attribute['allowed_values'] : array();
							echo esc_textarea( implode( "\n", $values ) ); 
						?></textarea>
						<br><small><?php esc_html_e( 'One value per line', 'w2f-pc-configurator' ); ?></small>
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Component Types', 'w2f-pc-configurator' ); ?></strong></label><br>
						<?php $selected_types = isset( $edit_attribute['component_types'] ) ? $edit_attribute['component_types'] : array(); ?>
						<?php foreach ( $component_types as $type_key => $type_info ) : ?>
							<label style="display: block; margin-bottom: 5px;">
								<input type="checkbox" name="component_types[]" value="<?php echo esc_attr( $type_key ); ?>" <?php checked( in_array( $type_key, $selected_types, true ) ); ?> />
								<?php echo esc_html( $type_info['label'] ); ?>
							</label>
						<?php endforeach; ?>
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Description', 'w2f-pc-configurator' ); ?></strong></label><br>
						<textarea name="description" rows="2" class="regular-text"><?php echo esc_textarea( $edit_attribute['description'] ?? '' ); ?></textarea>
					</p>

					<?php submit_button( $edit_attribute ? __( 'Update Attribute', 'w2f-pc-configurator' ) : __( 'Add Attribute', 'w2f-pc-configurator' ) ); ?>

					<?php if ( $edit_attribute ) : ?>
						<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=w2f-pc-attributes' ) ); ?>"><?php esc_html_e( '&larr; Cancel editing', 'w2f-pc-configurator' ); ?></a></p>
					<?php endif; ?>
				</form>
			</div>
		</div>
	</div>
</div>

<style>
.w2f-pc-admin .w2f-pc-two-column {
	display: flex;
	gap: 20px;
}
.w2f-pc-admin .w2f-pc-column-main {
	flex: 2;
}
.w2f-pc-admin .w2f-pc-column-sidebar {
	flex: 1;
	min-width: 350px;
}
.w2f-pc-admin .w2f-pc-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	padding: 15px;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.w2f-pc-admin .w2f-pc-card h2 {
	margin-top: 0;
}
</style>
