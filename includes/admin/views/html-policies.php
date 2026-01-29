<?php
/**
 * Compatibility Policies page template
 *
 * @package  W2F_PC_Configurator
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Build a list of available fact keys for searchable selects.
$w2f_pc_fact_keys = array();
if ( isset( $dictionary ) && $dictionary ) {
	$w2f_pc_fact_keys = array_keys( (array) $dictionary->get_all() );
}

// Add derived keys from calculators + known adjustment targets.
if ( class_exists( 'W2F_PC_Compatibility_Engine' ) ) {
	$calc_registry = W2F_PC_Compatibility_Engine::instance()->get_calculator_registry();
	if ( $calc_registry && method_exists( $calc_registry, 'get_all' ) ) {
		foreach ( (array) $calc_registry->get_all() as $calc ) {
			if ( is_object( $calc ) && method_exists( $calc, 'get_output_key' ) ) {
				$w2f_pc_fact_keys[] = $calc->get_output_key();
			}
		}
	}
}

// Adjustment targets that are commonly referenced.
$w2f_pc_fact_keys[] = 'derived.gpu_clearance_penalty_mm';

$w2f_pc_fact_keys = array_values( array_unique( array_filter( array_map( 'trim', $w2f_pc_fact_keys ) ) ) );
sort( $w2f_pc_fact_keys );

if ( ! function_exists( 'w2f_pc_render_fact_select' ) ) {
	/**
	 * Render a searchable fact select (selectWoo/select2 via wc-enhanced-select).
	 *
	 * @param string $name        Field name.
	 * @param string $current     Current value.
	 * @param array  $fact_keys   Available fact keys.
	 * @param string $placeholder Placeholder.
	 * @param string $css_class   Extra CSS class.
	 */
	function w2f_pc_render_fact_select( $name, $current, $fact_keys, $placeholder = '', $css_class = '' ) {
		$current = (string) $current;
		?>
		<select
			name="<?php echo esc_attr( $name ); ?>"
			class="wc-enhanced-select <?php echo esc_attr( $css_class ); ?>"
			style="width: 100%;"
			data-placeholder="<?php echo esc_attr( $placeholder ); ?>"
		>
			<option value=""></option>
			<?php
			// If current value isn't in list, include it so edits don't break.
			if ( $current && ! in_array( $current, $fact_keys, true ) ) :
				?>
				<option value="<?php echo esc_attr( $current ); ?>" selected="selected"><?php echo esc_html( $current ); ?></option>
				<?php
			endif;
			?>
			<?php foreach ( (array) $fact_keys as $key ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>>
					<?php echo esc_html( $key ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}
}
?>
<div class="wrap w2f-pc-admin">
	<h1><?php esc_html_e( 'Compatibility Policies', 'w2f-pc-configurator' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Policies define compatibility rules using guided templates. Choose a policy type to configure - no complex logic required.', 'w2f-pc-configurator' ); ?>
	</p>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Policy saved.', 'w2f-pc-configurator' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Policy deleted.', 'w2f-pc-configurator' ); ?></p></div>
	<?php endif; ?>

	<div class="w2f-pc-two-column">
		<div class="w2f-pc-column-main">
			<h2><?php esc_html_e( 'Active Policies', 'w2f-pc-configurator' ); ?></h2>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 30px;"><?php esc_html_e( '#', 'w2f-pc-configurator' ); ?></th>
						<th><?php esc_html_e( 'Name', 'w2f-pc-configurator' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'Type', 'w2f-pc-configurator' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'Severity', 'w2f-pc-configurator' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'Status', 'w2f-pc-configurator' ); ?></th>
						<th style="width: 120px;"><?php esc_html_e( 'Actions', 'w2f-pc-configurator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $policies ) ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'No policies defined. Create one using the form.', 'w2f-pc-configurator' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $policies as $policy_id => $policy ) : ?>
							<?php $type_info = isset( $policy_types[ $policy['type'] ] ) ? $policy_types[ $policy['type'] ] : array(); ?>
							<tr>
								<td><?php echo esc_html( $policy['priority'] ?? 50 ); ?></td>
								<td>
									<strong><?php echo esc_html( $policy['name'] ); ?></strong>
									<?php if ( ! empty( $policy['description'] ) ) : ?>
										<br><small><?php echo esc_html( $policy['description'] ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<span class="dashicons <?php echo esc_attr( $type_info['icon'] ?? 'dashicons-admin-generic' ); ?>"></span>
									<?php echo esc_html( $type_info['name'] ?? $policy['type'] ); ?>
								</td>
								<td>
									<span class="w2f-pc-severity <?php echo esc_attr( $policy['severity'] ?? 'block' ); ?>">
										<?php echo 'block' === ( $policy['severity'] ?? 'block' ) ? esc_html__( 'Block', 'w2f-pc-configurator' ) : esc_html__( 'Warn', 'w2f-pc-configurator' ); ?>
									</span>
								</td>
								<td>
									<span class="w2f-pc-status <?php echo ! empty( $policy['enabled'] ) ? 'enabled' : 'disabled'; ?>">
										<?php echo ! empty( $policy['enabled'] ) ? esc_html__( 'On', 'w2f-pc-configurator' ) : esc_html__( 'Off', 'w2f-pc-configurator' ); ?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=w2f-pc-policies&edit=' . urlencode( $policy_id ) ) ); ?>"><?php esc_html_e( 'Edit', 'w2f-pc-configurator' ); ?></a> |
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=w2f_pc_delete_policy&policy_id=' . urlencode( $policy_id ) ), 'w2f_pc_delete_policy' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this policy?', 'w2f-pc-configurator' ); ?>');"><?php esc_html_e( 'Delete', 'w2f-pc-configurator' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Policy Type Reference', 'w2f-pc-configurator' ); ?></h3>
			<div class="w2f-pc-policy-types-grid">
				<?php foreach ( $policy_types as $type_key => $type_info ) : ?>
					<div class="w2f-pc-policy-type-card">
						<h4><span class="dashicons <?php echo esc_attr( $type_info['icon'] ); ?>"></span> <?php echo esc_html( $type_info['name'] ); ?></h4>
						<p><?php echo esc_html( $type_info['description'] ); ?></p>
						<code><?php echo esc_html( $type_key ); ?></code>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="w2f-pc-column-sidebar">
			<div class="w2f-pc-card">
				<h2><?php echo $edit_policy ? esc_html__( 'Edit Policy', 'w2f-pc-configurator' ) : esc_html__( 'Add Policy', 'w2f-pc-configurator' ); ?></h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="w2f-pc-policy-form">
					<?php wp_nonce_field( 'w2f_pc_save_policy' ); ?>
					<input type="hidden" name="action" value="w2f_pc_save_policy" />
					<input type="hidden" name="is_new" value="<?php echo $edit_policy ? '0' : '1'; ?>" />
					<input type="hidden" name="policy_id" value="<?php echo esc_attr( $edit_id ); ?>" />

					<p>
						<label><strong><?php esc_html_e( 'Policy Name', 'w2f-pc-configurator' ); ?></strong></label><br>
						<input type="text" name="policy_name" value="<?php echo esc_attr( $edit_policy['name'] ?? '' ); ?>" class="regular-text" required />
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Policy Type', 'w2f-pc-configurator' ); ?></strong></label><br>
						<select name="policy_type" id="policy_type" class="regular-text">
							<?php foreach ( $policy_types as $type_key => $type_info ) : ?>
								<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( ( $edit_policy['type'] ?? 'match' ), $type_key ); ?>>
									<?php echo esc_html( $type_info['name'] ); ?> - <?php echo esc_html( $type_info['description'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Description', 'w2f-pc-configurator' ); ?></strong></label><br>
						<textarea name="policy_description" rows="2" class="regular-text"><?php echo esc_textarea( $edit_policy['description'] ?? '' ); ?></textarea>
					</p>

					<div class="w2f-pc-policy-config" id="policy-config-fields">
						<!-- Dynamic fields based on policy type -->
						<p class="description"><?php esc_html_e( 'Configure the policy conditions below.', 'w2f-pc-configurator' ); ?></p>

						<!-- MATCH type fields -->
						<div class="policy-type-fields policy-type-match" <?php echo ( $edit_policy['type'] ?? 'match' ) === 'match' ? '' : 'style="display:none;"'; ?>>
							<p>
								<label><strong><?php esc_html_e( 'First Value (Left)', 'w2f-pc-configurator' ); ?></strong></label><br>
								<?php w2f_pc_render_fact_select( 'config[left_key]', ( $edit_policy['config']['left_key'] ?? 'cpu.socket' ), $w2f_pc_fact_keys, 'cpu.socket', 'w2f-pc-fact-select' ); ?>
							</p>
							<p>
								<label><strong><?php esc_html_e( 'Second Value (Right)', 'w2f-pc-configurator' ); ?></strong></label><br>
								<?php w2f_pc_render_fact_select( 'config[right_key]', ( $edit_policy['config']['right_key'] ?? 'motherboard.socket' ), $w2f_pc_fact_keys, 'motherboard.socket', 'w2f-pc-fact-select' ); ?>
							</p>
						</div>

						<!-- CONTAINS type fields -->
						<div class="policy-type-fields policy-type-contains" <?php echo ( $edit_policy['type'] ?? '' ) === 'contains' ? '' : 'style="display:none;"'; ?>>
							<p>
								<label><strong><?php esc_html_e( 'Set (supports multiple values)', 'w2f-pc-configurator' ); ?></strong></label><br>
								<?php w2f_pc_render_fact_select( 'config[set_key]', ( $edit_policy['config']['set_key'] ?? '' ), $w2f_pc_fact_keys, 'case.supported_form_factors', 'w2f-pc-fact-select' ); ?>
							</p>
							<p>
								<label><strong><?php esc_html_e( 'Value to Find', 'w2f-pc-configurator' ); ?></strong></label><br>
								<?php w2f_pc_render_fact_select( 'config[value_key]', ( $edit_policy['config']['value_key'] ?? '' ), $w2f_pc_fact_keys, 'motherboard.form_factor', 'w2f-pc-fact-select' ); ?>
							</p>
						</div>

						<!-- COMPARE type fields -->
						<div class="policy-type-fields policy-type-compare" <?php echo ( $edit_policy['type'] ?? '' ) === 'compare' ? '' : 'style="display:none;"'; ?>>
							<p>
								<label><strong><?php esc_html_e( 'Left Value', 'w2f-pc-configurator' ); ?></strong></label><br>
								<?php w2f_pc_render_fact_select( 'config[left_key]', ( $edit_policy['config']['left_key'] ?? '' ), $w2f_pc_fact_keys, 'psu.wattage_w', 'w2f-pc-fact-select' ); ?>
							</p>
							<p>
								<label><strong><?php esc_html_e( 'Operator', 'w2f-pc-configurator' ); ?></strong></label><br>
								<select name="config[operator]">
									<option value=">=" <?php selected( ( $edit_policy['config']['operator'] ?? '' ), '>=' ); ?>>&gt;= (greater than or equal)</option>
									<option value=">" <?php selected( ( $edit_policy['config']['operator'] ?? '' ), '>' ); ?>>&gt; (greater than)</option>
									<option value="<=" <?php selected( ( $edit_policy['config']['operator'] ?? '' ), '<=' ); ?>>&lt;= (less than or equal)</option>
									<option value="<" <?php selected( ( $edit_policy['config']['operator'] ?? '' ), '<' ); ?>>&lt; (less than)</option>
									<option value="==" <?php selected( ( $edit_policy['config']['operator'] ?? '' ), '==' ); ?>>== (equal)</option>
								</select>
							</p>
							<p>
								<label><strong><?php esc_html_e( 'Right Value', 'w2f-pc-configurator' ); ?></strong></label><br>
								<?php w2f_pc_render_fact_select( 'config[right_key]', ( $edit_policy['config']['right_key'] ?? '' ), $w2f_pc_fact_keys, 'derived.required_psu_w', 'w2f-pc-fact-select' ); ?>
							</p>
						</div>

						<!-- FIT type fields -->
						<div class="policy-type-fields policy-type-fit" <?php echo ( $edit_policy['type'] ?? '' ) === 'fit' ? '' : 'style="display:none;"'; ?>>
							<p>
								<label><strong><?php esc_html_e( 'Candidate Size', 'w2f-pc-configurator' ); ?></strong></label><br>
								<?php w2f_pc_render_fact_select( 'config[candidate_key]', ( $edit_policy['config']['candidate_key'] ?? '' ), $w2f_pc_fact_keys, 'gpu.length_mm', 'w2f-pc-fact-select' ); ?>
							</p>
							<p>
								<label><strong><?php esc_html_e( 'Maximum Constraint', 'w2f-pc-configurator' ); ?></strong></label><br>
								<?php w2f_pc_render_fact_select( 'config[constraint_key]', ( $edit_policy['config']['constraint_key'] ?? '' ), $w2f_pc_fact_keys, 'derived.max_gpu_length_mm', 'w2f-pc-fact-select' ); ?>
							</p>
						</div>

						<!-- COUNT_LIMIT type fields -->
						<div class="policy-type-fields policy-type-count_limit" <?php echo ( $edit_policy['type'] ?? '' ) === 'count_limit' ? '' : 'style="display:none;"'; ?>>
							<p>
								<label><strong><?php esc_html_e( 'Component Type to Count', 'w2f-pc-configurator' ); ?></strong></label><br>
								<select name="config[count_component]">
									<?php foreach ( $component_types as $type_key => $type_info ) : ?>
										<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( ( $edit_policy['config']['count_component'] ?? '' ), $type_key ); ?>>
											<?php echo esc_html( $type_info['label'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</p>
							<p>
								<label><strong><?php esc_html_e( 'Filter Key (optional)', 'w2f-pc-configurator' ); ?></strong></label><br>
								<input type="text" name="config[count_where_key]" value="<?php echo esc_attr( $edit_policy['config']['count_where_key'] ?? '' ); ?>" class="regular-text" placeholder="interface" />
							</p>
							<p>
								<label><strong><?php esc_html_e( 'Filter Value', 'w2f-pc-configurator' ); ?></strong></label><br>
								<input type="text" name="config[count_where_value]" value="<?php echo esc_attr( $edit_policy['config']['count_where_value'] ?? '' ); ?>" class="regular-text" placeholder="NVMe" />
							</p>
							<p>
								<label><strong><?php esc_html_e( 'Limit Key', 'w2f-pc-configurator' ); ?></strong></label><br>
								<input type="text" name="config[limit_key]" value="<?php echo esc_attr( $edit_policy['config']['limit_key'] ?? '' ); ?>" class="regular-text" placeholder="motherboard.m2_slots" />
							</p>
						</div>

						<!-- REQUIRES_IF type fields -->
						<div class="policy-type-fields policy-type-requires_if" <?php echo ( $edit_policy['type'] ?? '' ) === 'requires_if' ? '' : 'style="display:none;"'; ?>>
							<p>
								<label><strong><?php esc_html_e( 'If Component', 'w2f-pc-configurator' ); ?></strong></label><br>
								<select name="config[if_component]">
									<?php foreach ( $component_types as $type_key => $type_info ) : ?>
										<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( ( $edit_policy['config']['if_component'] ?? '' ), $type_key ); ?>>
											<?php echo esc_html( $type_info['label'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</p>
							<p>
								<label><strong><?php esc_html_e( 'Then Require Component', 'w2f-pc-configurator' ); ?></strong></label><br>
								<select name="config[then_component]">
									<?php foreach ( $component_types as $type_key => $type_info ) : ?>
										<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( ( $edit_policy['config']['then_component'] ?? '' ), $type_key ); ?>>
											<?php echo esc_html( $type_info['label'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</p>
						</div>

						<!-- WARNING_RANGE type fields -->
						<div class="policy-type-fields policy-type-warning_range" <?php echo ( $edit_policy['type'] ?? '' ) === 'warning_range' ? '' : 'style="display:none;"'; ?>>
							<p>
								<label><strong><?php esc_html_e( 'Value to Check', 'w2f-pc-configurator' ); ?></strong></label><br>
								<?php w2f_pc_render_fact_select( 'config[value_key]', ( $edit_policy['config']['value_key'] ?? '' ), $w2f_pc_fact_keys, 'psu.wattage_w', 'w2f-pc-fact-select' ); ?>
							</p>
							<p>
								<label><strong><?php esc_html_e( 'Threshold', 'w2f-pc-configurator' ); ?></strong></label><br>
								<?php w2f_pc_render_fact_select( 'config[threshold_key]', ( $edit_policy['config']['threshold_key'] ?? '' ), $w2f_pc_fact_keys, 'derived.required_psu_w', 'w2f-pc-fact-select' ); ?>
							</p>
							<p>
								<label><strong><?php esc_html_e( 'Margin Percent', 'w2f-pc-configurator' ); ?></strong></label><br>
								<input type="number" name="config[margin_percent]" value="<?php echo esc_attr( $edit_policy['config']['margin_percent'] ?? 10 ); ?>" class="small-text" min="1" max="50" />
							</p>
						</div>
					</div>

					<hr>

					<p>
						<label><strong><?php esc_html_e( 'Priority', 'w2f-pc-configurator' ); ?></strong></label><br>
						<input type="number" name="priority" value="<?php echo esc_attr( $edit_policy['priority'] ?? 50 ); ?>" class="small-text" min="1" max="999" />
						<small><?php esc_html_e( 'Lower = evaluated first', 'w2f-pc-configurator' ); ?></small>
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Severity', 'w2f-pc-configurator' ); ?></strong></label><br>
						<select name="severity">
							<option value="block" <?php selected( ( $edit_policy['severity'] ?? 'block' ), 'block' ); ?>><?php esc_html_e( 'Block - prevents purchase', 'w2f-pc-configurator' ); ?></option>
							<option value="warn" <?php selected( ( $edit_policy['severity'] ?? '' ), 'warn' ); ?>><?php esc_html_e( 'Warn - shows warning only', 'w2f-pc-configurator' ); ?></option>
						</select>
					</p>

					<p>
						<label><strong><?php esc_html_e( 'Message Template', 'w2f-pc-configurator' ); ?></strong></label><br>
						<textarea name="message" rows="2" class="regular-text" placeholder="Use {{fact.key}} for variable substitution"><?php echo esc_textarea( $edit_policy['message'] ?? '' ); ?></textarea>
						<small><?php esc_html_e( 'Use {{cpu.socket}}, {{derived.required_psu_w}}, etc.', 'w2f-pc-configurator' ); ?></small>
					</p>

					<p>
						<label>
							<input type="checkbox" name="enabled" value="1" <?php checked( $edit_policy['enabled'] ?? true ); ?> />
							<strong><?php esc_html_e( 'Enabled', 'w2f-pc-configurator' ); ?></strong>
						</label>
					</p>

					<?php submit_button( $edit_policy ? __( 'Update Policy', 'w2f-pc-configurator' ) : __( 'Add Policy', 'w2f-pc-configurator' ) ); ?>

					<?php if ( $edit_policy ) : ?>
						<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=w2f-pc-policies' ) ); ?>"><?php esc_html_e( '&larr; Cancel editing', 'w2f-pc-configurator' ); ?></a></p>
					<?php endif; ?>
				</form>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Ensure enhanced (searchable) selects initialize on this page.
	var initEnhanced = function() {
		var $sels = $('.w2f-pc-fact-select');
		if ($sels.length === 0) return;

		$sels.each(function() {
			var $el = $(this);
			// Avoid double-init.
			if ($el.hasClass('select2-hidden-accessible') || $el.hasClass('selectWoo-hidden-accessible')) {
				return;
			}
			if (typeof $.fn.selectWoo !== 'undefined') {
				$el.selectWoo({ allowClear: true, width: 'resolve' });
			} else if (typeof $.fn.select2 !== 'undefined') {
				$el.select2({ allowClear: true, width: 'resolve' });
			}
		});
	};

	initEnhanced();

	$('#policy_type').on('change', function() {
		var type = $(this).val();
		$('.policy-type-fields').hide();
		$('.policy-type-' + type).show();
		// Some selects may have been hidden on init; re-check after type swap.
		initEnhanced();
	});
});
</script>

<style>
.w2f-pc-admin .w2f-pc-two-column { display: flex; gap: 20px; }
.w2f-pc-admin .w2f-pc-column-main { flex: 2; }
.w2f-pc-admin .w2f-pc-column-sidebar { flex: 1; min-width: 400px; }
.w2f-pc-admin .w2f-pc-card { background: #fff; border: 1px solid #ccd0d4; padding: 15px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
.w2f-pc-admin .w2f-pc-card h2 { margin-top: 0; }
.w2f-pc-admin .w2f-pc-severity { font-size: 11px; padding: 2px 6px; border-radius: 3px; }
.w2f-pc-admin .w2f-pc-severity.block { background: #f8d7da; color: #721c24; }
.w2f-pc-admin .w2f-pc-severity.warn { background: #fff3cd; color: #856404; }
.w2f-pc-admin .w2f-pc-status { font-size: 11px; padding: 2px 6px; border-radius: 3px; }
.w2f-pc-admin .w2f-pc-status.enabled { background: #d4edda; color: #155724; }
.w2f-pc-admin .w2f-pc-status.disabled { background: #e2e3e5; color: #383d41; }
.w2f-pc-admin .w2f-pc-policy-types-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin-top: 15px; }
.w2f-pc-admin .w2f-pc-policy-type-card { background: #f8f9fa; border: 1px solid #dee2e6; padding: 12px; border-radius: 4px; }
.w2f-pc-admin .w2f-pc-policy-type-card h4 { margin: 0 0 8px 0; }
.w2f-pc-admin .w2f-pc-policy-type-card p { margin: 0 0 8px 0; font-size: 13px; }
.w2f-pc-admin .w2f-pc-policy-config { background: #f5f5f5; padding: 15px; border: 1px solid #ddd; margin: 15px 0; }
</style>
