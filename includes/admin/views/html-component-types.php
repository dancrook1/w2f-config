<?php
/**
 * Component Types mapping page template
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
	<h1><?php esc_html_e( 'Component Type Mapping', 'w2f-pc-configurator' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Map WooCommerce product categories to PC component types. This determines how the compatibility system identifies what type of part a product is.', 'w2f-pc-configurator' ); ?>
	</p>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Component mappings saved.', 'w2f-pc-configurator' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'w2f_pc_save_component_mapping' ); ?>
		<input type="hidden" name="action" value="w2f_pc_save_component_mapping" />

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 150px;"><?php esc_html_e( 'Component Type', 'w2f-pc-configurator' ); ?></th>
					<th style="width: 200px;"><?php esc_html_e( 'Description', 'w2f-pc-configurator' ); ?></th>
					<th><?php esc_html_e( 'Mapped Categories', 'w2f-pc-configurator' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Multi-Select', 'w2f-pc-configurator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $component_types as $type_key => $type_info ) : ?>
					<?php $mapped_cats = isset( $mappings[ $type_key ] ) ? $mappings[ $type_key ] : array(); ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $type_info['label'] ); ?></strong>
							<br><code><?php echo esc_html( $type_key ); ?></code>
						</td>
						<td><?php echo esc_html( $type_info['description'] ); ?></td>
						<td>
							<select name="mappings[<?php echo esc_attr( $type_key ); ?>][]" multiple class="w2f-pc-category-select" style="width: 100%; min-height: 100px;">
								<?php foreach ( $categories as $cat_id => $cat_info ) : ?>
									<option value="<?php echo esc_attr( $cat_id ); ?>" <?php selected( in_array( $cat_id, $mapped_cats, true ) ); ?>>
										<?php echo esc_html( $cat_info['name'] ); ?> (<?php echo esc_html( $cat_info['count'] ); ?> products)
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple categories.', 'w2f-pc-configurator' ); ?></p>
						</td>
						<td>
							<?php if ( ! empty( $type_info['multi_select'] ) ) : ?>
								<span class="dashicons dashicons-yes" style="color: green;"></span>
								<?php esc_html_e( 'Yes', 'w2f-pc-configurator' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-no" style="color: gray;"></span>
								<?php esc_html_e( 'No', 'w2f-pc-configurator' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Mappings', 'w2f-pc-configurator' ) ); ?>
	</form>
</div>

<style>
.w2f-pc-admin .w2f-pc-category-select {
	min-height: 80px;
}
</style>
