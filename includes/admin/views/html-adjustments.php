<?php
/**
 * Adjustments page template
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
	<h1><?php esc_html_e( 'Adjustments', 'w2f-pc-configurator' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Adjustments modify calculator inputs or derived constraints based on conditions. For example, a 240mm AIO radiator reduces GPU clearance.', 'w2f-pc-configurator' ); ?>
	</p>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Adjustment saved.', 'w2f-pc-configurator' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Adjustment deleted.', 'w2f-pc-configurator' ); ?></p></div>
	<?php endif; ?>

	<div class="w2f-pc-two-column">
		<div class="w2f-pc-column-main">
			<h2><?php esc_html_e( 'Active Adjustments', 'w2f-pc-configurator' ); ?></h2>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 30px;"><?php esc_html_e( '#', 'w2f-pc-configurator' ); ?></th>
						<th><?php esc_html_e( 'Name', 'w2f-pc-configurator' ); ?></th>
						<th style="width: 200px;"><?php esc_html_e( 'Effect', 'w2f-pc-configurator' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'Status', 'w2f-pc-configurator' ); ?></th>
						<th style="width: 120px;"><?php esc_html_e( 'Actions', 'w2f-pc-configurator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $adjustments ) ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No adjustments defined. Create one using the form.', 'w2f-pc-configurator' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $adjustments as $adj_id => $adj ) : ?>
							<tr>
								<td><?php echo esc_html( $adj['priority'] ?? 50 ); ?></td>
								<td>
									<strong><?php echo esc_html( $adj['name'] ); ?></strong>
									<?php if ( ! empty( $adj['description'] ) ) : ?>
										<br><small><?php echo esc_html( $adj['description'] ); ?></small>
									<?php endif; ?>
									<?php if ( ! empty( $adj['reason'] ) ) : ?>
										<br><em><?php echo esc_html( $adj['reason'] ); ?></em>
									<?php endif; ?>
								</td>
								<td>
									<code><?php echo esc_html( $adj['target_key'] ?? '' ); ?></code>
									<br>
									<strong><?php echo esc_html( $adj['effect'] ?? 'add' ); ?></strong>
									<?php echo esc_html( $adj['delta'] ?? 0 ); ?>
								</td>
								<td>
									<span class="w2f-pc-status <?php echo ! empty( $adj['enabled'] ) ? 'enabled' : 'disabled'; ?>">
										<?php echo ! empty( $adj['enabled'] ) ? esc_html__( 'On', 'w2f-pc-configurator' ) : esc_html__( 'Off', 'w2f-pc-configurator' ); ?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=w2f-pc-adjustments&edit=' . urlencode( $adj_id ) ) ); ?>"><?php esc_html_e( 'Edit', 'w2f-pc-configurator' ); ?></a> |
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=w2f_pc_delete_adjustment&adjustment_id=' . urlencode( $adj_id ) ), 'w2f_pc_delete_adjustment' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this adjustment?', 'w2f-pc-configurator' ); ?>');"><?php esc_html_e( 'Delete', 'w2f-pc-configurator' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="w2f-pc-column-sidebar">
			<div class="w2f-pc-card">
				<h2><?php echo $edit_adjustment ? esc_html__( 'Edit Adjustment', 'w2f-pc-configurator' ) : esc_html__( 'Add Adjustment', 'w2f-pc-configurator' ); ?></h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'w2f_pc_save_adjustment' ); ?>
					<input type="hidden" name="action" value="w2f_pc_save_adjustment" />
					<input type="hidden" name="is_new" value="<?php echo $edit_adjustment ? '0' : '1'; ?>" />
					<input type="hidden" name="adjustment_id" value="<?php echo esc_attr( $edit_id ); ?>" />

					<p>
						<label><strong><?php esc_html_e( 'Name', 'w2f-pc-configurator' ); ?></strong></label><br>
						<input type="text" name="adjustment_name" value="<?php echo esc_attr( $edit_adjustment['name'] ?? '' ); ?>" class="regular-text" required />
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Description', 'w2f-pc-configurator' ); ?></strong></label><br>
						<textarea name="adjustment_description" rows="2" class="regular-text"><?php echo esc_textarea( $edit_adjustment['description'] ?? '' ); ?></textarea>
					</p>

					<h3><?php esc_html_e( 'Conditions', 'w2f-pc-configurator' ); ?></h3>
					<p class="description"><?php esc_html_e( 'The adjustment applies when ALL (AND) or ANY (OR) conditions are met.', 'w2f-pc-configurator' ); ?></p>

					<p>
						<label><strong><?php esc_html_e( 'Condition Logic', 'w2f-pc-configurator' ); ?></strong></label><br>
						<select name="condition_logic">
							<option value="AND" <?php selected( ( $edit_adjustment['condition_logic'] ?? 'AND' ), 'AND' ); ?>><?php esc_html_e( 'AND - All conditions must match', 'w2f-pc-configurator' ); ?></option>
							<option value="OR" <?php selected( ( $edit_adjustment['condition_logic'] ?? '' ), 'OR' ); ?>><?php esc_html_e( 'OR - Any condition can match', 'w2f-pc-configurator' ); ?></option>
						</select>
					</p>

					<div class="w2f-pc-conditions-list" id="conditions-list">
						<?php 
						$conditions = isset( $edit_adjustment['conditions'] ) ? $edit_adjustment['conditions'] : array( array() );
						foreach ( $conditions as $i => $condition ) : 
						?>
						<div class="w2f-pc-condition-row">
							<p>
								<input type="text" name="conditions[<?php echo $i; ?>][fact_key]" value="<?php echo esc_attr( $condition['fact_key'] ?? '' ); ?>" placeholder="cooler.type" class="regular-text" />
								<select name="conditions[<?php echo $i; ?>][operator]">
									<option value="=" <?php selected( ( $condition['operator'] ?? '=' ), '=' ); ?>>=</option>
									<option value="!=" <?php selected( ( $condition['operator'] ?? '' ), '!=' ); ?>>!=</option>
									<option value=">" <?php selected( ( $condition['operator'] ?? '' ), '>' ); ?>>&gt;</option>
									<option value=">=" <?php selected( ( $condition['operator'] ?? '' ), '>=' ); ?>>&gt;=</option>
									<option value="<" <?php selected( ( $condition['operator'] ?? '' ), '<' ); ?>>&lt;</option>
									<option value="<=" <?php selected( ( $condition['operator'] ?? '' ), '<=' ); ?>>&lt;=</option>
									<option value="exists" <?php selected( ( $condition['operator'] ?? '' ), 'exists' ); ?>>exists</option>
								</select>
								<input type="text" name="conditions[<?php echo $i; ?>][value]" value="<?php echo esc_attr( $condition['value'] ?? '' ); ?>" placeholder="value" class="regular-text" />
							</p>
						</div>
						<?php endforeach; ?>
					</div>
					<p><button type="button" class="button" id="add-condition"><?php esc_html_e( '+ Add Condition', 'w2f-pc-configurator' ); ?></button></p>

					<h3><?php esc_html_e( 'Effect', 'w2f-pc-configurator' ); ?></h3>

					<p>
						<label><strong><?php esc_html_e( 'Target Key', 'w2f-pc-configurator' ); ?></strong></label><br>
						<input type="text" name="target_key" value="<?php echo esc_attr( $edit_adjustment['target_key'] ?? 'derived.gpu_clearance_penalty_mm' ); ?>" class="regular-text" placeholder="derived.gpu_clearance_penalty_mm" />
						<br><small><?php esc_html_e( 'The derived value to modify', 'w2f-pc-configurator' ); ?></small>
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Effect Type', 'w2f-pc-configurator' ); ?></strong></label><br>
						<select name="effect">
							<option value="add" <?php selected( ( $edit_adjustment['effect'] ?? 'add' ), 'add' ); ?>><?php esc_html_e( 'Add', 'w2f-pc-configurator' ); ?></option>
							<option value="subtract" <?php selected( ( $edit_adjustment['effect'] ?? '' ), 'subtract' ); ?>><?php esc_html_e( 'Subtract', 'w2f-pc-configurator' ); ?></option>
							<option value="multiply" <?php selected( ( $edit_adjustment['effect'] ?? '' ), 'multiply' ); ?>><?php esc_html_e( 'Multiply', 'w2f-pc-configurator' ); ?></option>
							<option value="set" <?php selected( ( $edit_adjustment['effect'] ?? '' ), 'set' ); ?>><?php esc_html_e( 'Set to', 'w2f-pc-configurator' ); ?></option>
						</select>
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Delta Value', 'w2f-pc-configurator' ); ?></strong></label><br>
						<input type="number" name="delta" value="<?php echo esc_attr( $edit_adjustment['delta'] ?? 0 ); ?>" class="small-text" step="any" />
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Reason (for trace)', 'w2f-pc-configurator' ); ?></strong></label><br>
						<input type="text" name="reason" value="<?php echo esc_attr( $edit_adjustment['reason'] ?? '' ); ?>" class="regular-text" placeholder="240mm AIO reduces GPU clearance by 40mm" />
					</p>

					<hr>

					<p>
						<label><strong><?php esc_html_e( 'Priority', 'w2f-pc-configurator' ); ?></strong></label><br>
						<input type="number" name="priority" value="<?php echo esc_attr( $edit_adjustment['priority'] ?? 50 ); ?>" class="small-text" min="1" max="999" />
						<small><?php esc_html_e( 'Lower = applied first', 'w2f-pc-configurator' ); ?></small>
					</p>

					<p>
						<label>
							<input type="checkbox" name="enabled" value="1" <?php checked( $edit_adjustment['enabled'] ?? true ); ?> />
							<strong><?php esc_html_e( 'Enabled', 'w2f-pc-configurator' ); ?></strong>
						</label>
					</p>

					<?php submit_button( $edit_adjustment ? __( 'Update Adjustment', 'w2f-pc-configurator' ) : __( 'Add Adjustment', 'w2f-pc-configurator' ) ); ?>

					<?php if ( $edit_adjustment ) : ?>
						<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=w2f-pc-adjustments' ) ); ?>"><?php esc_html_e( '&larr; Cancel editing', 'w2f-pc-configurator' ); ?></a></p>
					<?php endif; ?>
				</form>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	var conditionIndex = <?php echo count( $conditions ); ?>;
	
	$('#add-condition').on('click', function() {
		var html = '<div class="w2f-pc-condition-row">' +
			'<p>' +
			'<input type="text" name="conditions[' + conditionIndex + '][fact_key]" placeholder="cooler.radiator_size_mm" class="regular-text" />' +
			'<select name="conditions[' + conditionIndex + '][operator]">' +
			'<option value="=">=</option><option value="!=">!=</option><option value=">">&gt;</option><option value=">=">&gt;=</option><option value="<">&lt;</option><option value="<=">&lt;=</option><option value="exists">exists</option>' +
			'</select>' +
			'<input type="text" name="conditions[' + conditionIndex + '][value]" placeholder="value" class="regular-text" />' +
			'</p></div>';
		$('#conditions-list').append(html);
		conditionIndex++;
	});
});
</script>

<style>
.w2f-pc-admin .w2f-pc-two-column { display: flex; gap: 20px; }
.w2f-pc-admin .w2f-pc-column-main { flex: 2; }
.w2f-pc-admin .w2f-pc-column-sidebar { flex: 1; min-width: 400px; }
.w2f-pc-admin .w2f-pc-card { background: #fff; border: 1px solid #ccd0d4; padding: 15px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
.w2f-pc-admin .w2f-pc-card h2 { margin-top: 0; }
.w2f-pc-admin .w2f-pc-status { font-size: 11px; padding: 2px 6px; border-radius: 3px; }
.w2f-pc-admin .w2f-pc-status.enabled { background: #d4edda; color: #155724; }
.w2f-pc-admin .w2f-pc-status.disabled { background: #e2e3e5; color: #383d41; }
.w2f-pc-admin .w2f-pc-conditions-list { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
.w2f-pc-admin .w2f-pc-condition-row { margin-bottom: 5px; }
.w2f-pc-admin .w2f-pc-condition-row select { width: auto; }
</style>
