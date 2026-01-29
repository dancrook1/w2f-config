<?php
/**
 * Calculators page template
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
	<h1><?php esc_html_e( 'Calculators', 'w2f-pc-configurator' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Calculators compute derived values from component facts. These values are then used by compatibility policies.', 'w2f-pc-configurator' ); ?>
	</p>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Calculator settings saved.', 'w2f-pc-configurator' ); ?></p></div>
	<?php endif; ?>

	<div class="w2f-pc-calculators-grid">
		<?php foreach ( $calculators as $calc_id => $calculator ) : ?>
			<?php 
			$config = $calculator->get_config();
			$type_info = isset( $calculator_types[ $calc_id ] ) ? $calculator_types[ $calc_id ] : array();
			$params = $calculator->get_params();
			$param_defs = $calculator->get_param_definitions();
			?>
			<div class="w2f-pc-card">
				<h2>
					<?php echo esc_html( $calculator->get_name() ); ?>
					<span class="w2f-pc-status <?php echo $calculator->is_enabled() ? 'enabled' : 'disabled'; ?>">
						<?php echo $calculator->is_enabled() ? esc_html__( 'Enabled', 'w2f-pc-configurator' ) : esc_html__( 'Disabled', 'w2f-pc-configurator' ); ?>
					</span>
				</h2>
				<p class="description"><?php echo esc_html( $calculator->get_description() ); ?></p>
				<p><strong><?php esc_html_e( 'Output:', 'w2f-pc-configurator' ); ?></strong> <code><?php echo esc_html( $calculator->get_output_key() ); ?></code></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'w2f_pc_save_calculator' ); ?>
					<input type="hidden" name="action" value="w2f_pc_save_calculator" />
					<input type="hidden" name="calculator_id" value="<?php echo esc_attr( $calc_id ); ?>" />

					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Enabled', 'w2f-pc-configurator' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="enabled" value="1" <?php checked( $calculator->is_enabled() ); ?> />
									<?php esc_html_e( 'Enable this calculator', 'w2f-pc-configurator' ); ?>
								</label>
							</td>
						</tr>
						<?php foreach ( $param_defs as $param_key => $param_def ) : ?>
							<tr>
								<th><label for="param_<?php echo esc_attr( $calc_id . '_' . $param_key ); ?>"><?php echo esc_html( $param_def['label'] ); ?></label></th>
								<td>
									<?php if ( 'number' === $param_def['type'] ) : ?>
										<input type="number" 
											id="param_<?php echo esc_attr( $calc_id . '_' . $param_key ); ?>"
											name="params[<?php echo esc_attr( $param_key ); ?>]" 
											value="<?php echo esc_attr( $params[ $param_key ] ?? $param_def['default'] ); ?>"
											min="<?php echo esc_attr( $param_def['min'] ?? 0 ); ?>"
											max="<?php echo esc_attr( $param_def['max'] ?? 9999 ); ?>"
											step="<?php echo esc_attr( $param_def['step'] ?? 1 ); ?>"
											class="small-text" />
									<?php elseif ( 'multiselect' === $param_def['type'] ) : ?>
										<?php $current_values = isset( $params[ $param_key ] ) ? (array) $params[ $param_key ] : array(); ?>
										<?php foreach ( $param_def['options'] as $opt_value => $opt_label ) : ?>
											<label style="display: block; margin-bottom: 5px;">
												<input type="checkbox" 
													name="params[<?php echo esc_attr( $param_key ); ?>][]" 
													value="<?php echo esc_attr( $opt_value ); ?>"
													<?php checked( in_array( $opt_value, $current_values, true ) ); ?> />
												<?php echo esc_html( $opt_label ); ?>
												<code style="font-size: 11px;"><?php echo esc_html( $opt_value ); ?></code>
											</label>
										<?php endforeach; ?>
									<?php elseif ( 'select' === $param_def['type'] ) : ?>
										<select name="params[<?php echo esc_attr( $param_key ); ?>]">
											<?php foreach ( $param_def['options'] as $opt_value => $opt_label ) : ?>
												<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( ( $params[ $param_key ] ?? '' ), $opt_value ); ?>>
													<?php echo esc_html( $opt_label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									<?php else : ?>
										<input type="text" 
											name="params[<?php echo esc_attr( $param_key ); ?>]" 
											value="<?php echo esc_attr( $params[ $param_key ] ?? $param_def['default'] ?? '' ); ?>"
											class="regular-text" />
									<?php endif; ?>
									<?php if ( ! empty( $param_def['description'] ) ) : ?>
										<p class="description"><?php echo esc_html( $param_def['description'] ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>

					<?php submit_button( __( 'Save Calculator', 'w2f-pc-configurator' ), 'secondary' ); ?>
				</form>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<style>
.w2f-pc-admin .w2f-pc-calculators-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
	gap: 20px;
}
.w2f-pc-admin .w2f-pc-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	padding: 15px 20px;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.w2f-pc-admin .w2f-pc-card h2 {
	margin-top: 0;
	display: flex;
	justify-content: space-between;
	align-items: center;
}
.w2f-pc-admin .w2f-pc-status {
	font-size: 12px;
	padding: 3px 8px;
	border-radius: 3px;
}
.w2f-pc-admin .w2f-pc-status.enabled {
	background: #d4edda;
	color: #155724;
}
.w2f-pc-admin .w2f-pc-status.disabled {
	background: #f8d7da;
	color: #721c24;
}
</style>
