<?php
/**
 * Build Simulator page template
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
	<h1><?php esc_html_e( 'Build Simulator', 'w2f-pc-configurator' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Test the compatibility system by selecting products for each component type. See the full evaluation trace including facts, adjustments, calculators, and policy results.', 'w2f-pc-configurator' ); ?>
	</p>

	<div class="w2f-pc-simulator-container">
		<div class="w2f-pc-simulator-config">
			<h2><?php esc_html_e( 'Build Configuration', 'w2f-pc-configurator' ); ?></h2>

			<div class="w2f-pc-card">
				<form id="w2f-pc-simulator-form">
					<?php wp_nonce_field( 'w2f-pc-admin', 'security' ); ?>

					<p>
						<label><strong><?php esc_html_e( 'Configurator Product (optional)', 'w2f-pc-configurator' ); ?></strong></label><br>
						<select name="configurator_id" id="configurator_id">
							<option value="0"><?php esc_html_e( '-- None (standalone test) --', 'w2f-pc-configurator' ); ?></option>
							<?php foreach ( $configurator_products as $product ) : ?>
								<option value="<?php echo esc_attr( $product->get_id() ); ?>">
									<?php echo esc_html( $product->get_name() ); ?> (#<?php echo esc_html( $product->get_id() ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<h3><?php esc_html_e( 'Select Products by Component Type', 'w2f-pc-configurator' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Enter product IDs for each component. You can find product IDs in WooCommerce > Products.', 'w2f-pc-configurator' ); ?></p>

					<table class="form-table w2f-pc-simulator-components">
						<?php foreach ( $component_types as $type_key => $type_info ) : ?>
							<tr>
								<th>
									<label for="component_<?php echo esc_attr( $type_key ); ?>">
										<?php echo esc_html( $type_info['label'] ); ?>
									</label>
									<?php if ( ! empty( $type_info['multi_select'] ) ) : ?>
										<span class="description"><?php esc_html_e( '(multi)', 'w2f-pc-configurator' ); ?></span>
									<?php endif; ?>
								</th>
								<td>
									<input type="number" 
										id="component_<?php echo esc_attr( $type_key ); ?>"
										name="configuration[<?php echo esc_attr( $type_key ); ?>]" 
										class="small-text" 
										placeholder="<?php esc_attr_e( 'Product ID', 'w2f-pc-configurator' ); ?>" />
									<span class="w2f-pc-product-name" data-for="<?php echo esc_attr( $type_key ); ?>"></span>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>

					<p>
						<button type="submit" class="button button-primary" id="run-simulation">
							<span class="dashicons dashicons-controls-play" style="margin-top: 3px;"></span>
							<?php esc_html_e( 'Run Simulation', 'w2f-pc-configurator' ); ?>
						</button>
						<button type="button" class="button" id="clear-simulation">
							<?php esc_html_e( 'Clear', 'w2f-pc-configurator' ); ?>
						</button>
					</p>
				</form>
			</div>
		</div>

		<div class="w2f-pc-simulator-results">
			<h2><?php esc_html_e( 'Simulation Results', 'w2f-pc-configurator' ); ?></h2>

			<div class="w2f-pc-card" id="simulation-results">
				<p class="description"><?php esc_html_e( 'Select products and click "Run Simulation" to see results.', 'w2f-pc-configurator' ); ?></p>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#w2f-pc-simulator-form').on('submit', function(e) {
		e.preventDefault();
		
		var $btn = $('#run-simulation');
		var $results = $('#simulation-results');
		
		$btn.prop('disabled', true).text('<?php esc_html_e( 'Running...', 'w2f-pc-configurator' ); ?>');
		$results.html('<p><span class="spinner is-active" style="float:none;"></span> <?php esc_html_e( 'Evaluating configuration...', 'w2f-pc-configurator' ); ?></p>');
		
		// Collect configuration
		var configuration = {};
		$('input[name^="configuration["]').each(function() {
			var name = $(this).attr('name').match(/\[([^\]]+)\]/)[1];
			var value = parseInt($(this).val(), 10);
			if (value > 0) {
				configuration[name] = value;
			}
		});
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'w2f_pc_simulate_build',
				security: $('input[name="security"]').val(),
				configuration: configuration,
				configurator_id: $('#configurator_id').val()
			},
			success: function(response) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play" style="margin-top: 3px;"></span> <?php esc_html_e( 'Run Simulation', 'w2f-pc-configurator' ); ?>');
				
				if (response.success) {
					renderResults(response.data);
				} else {
					$results.html('<div class="notice notice-error"><p>' + (response.data.message || '<?php esc_html_e( 'Error running simulation.', 'w2f-pc-configurator' ); ?>') + '</p></div>');
				}
			},
			error: function() {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play" style="margin-top: 3px;"></span> <?php esc_html_e( 'Run Simulation', 'w2f-pc-configurator' ); ?>');
				$results.html('<div class="notice notice-error"><p><?php esc_html_e( 'AJAX error occurred.', 'w2f-pc-configurator' ); ?></p></div>');
			}
		});
	});
	
	$('#clear-simulation').on('click', function() {
		$('input[name^="configuration["]').val('');
		$('#simulation-results').html('<p class="description"><?php esc_html_e( 'Select products and click "Run Simulation" to see results.', 'w2f-pc-configurator' ); ?></p>');
	});
	
	function renderResults(data) {
		var html = '';
		
		// Overall result
		var statusClass = data.valid ? 'success' : 'error';
		var statusText = data.valid ? '<?php esc_html_e( 'COMPATIBLE', 'w2f-pc-configurator' ); ?>' : '<?php esc_html_e( 'INCOMPATIBLE', 'w2f-pc-configurator' ); ?>';
		
		html += '<div class="w2f-pc-result-header ' + statusClass + '">';
		html += '<h3>' + statusText + '</h3>';
		if (data.errors && data.errors.length > 0) {
			html += '<ul class="errors">';
			data.errors.forEach(function(err) {
				html += '<li>' + escapeHtml(err) + '</li>';
			});
			html += '</ul>';
		}
		if (data.warnings && data.warnings.length > 0) {
			html += '<ul class="warnings">';
			data.warnings.forEach(function(warn) {
				html += '<li>' + escapeHtml(warn) + '</li>';
			});
			html += '</ul>';
		}
		html += '</div>';
		
		// Summary
		if (data.human_readable && data.human_readable.overview) {
			var ov = data.human_readable.overview;
			html += '<div class="w2f-pc-result-section">';
			html += '<h4><?php esc_html_e( 'Summary', 'w2f-pc-configurator' ); ?></h4>';
			html += '<div class="w2f-pc-stats-grid">';
			html += '<div class="stat"><span class="value">' + ov.total_policies + '</span><span class="label"><?php esc_html_e( 'Policies', 'w2f-pc-configurator' ); ?></span></div>';
			html += '<div class="stat success"><span class="value">' + ov.passed + '</span><span class="label"><?php esc_html_e( 'Passed', 'w2f-pc-configurator' ); ?></span></div>';
			html += '<div class="stat error"><span class="value">' + ov.blocked + '</span><span class="label"><?php esc_html_e( 'Blocked', 'w2f-pc-configurator' ); ?></span></div>';
			html += '<div class="stat warning"><span class="value">' + ov.warnings + '</span><span class="label"><?php esc_html_e( 'Warnings', 'w2f-pc-configurator' ); ?></span></div>';
			html += '</div>';
			html += '</div>';
		}
		
		// Facts
		if (data.facts && Object.keys(data.facts).length > 0) {
			html += '<div class="w2f-pc-result-section collapsible">';
			html += '<h4 class="toggle"><?php esc_html_e( 'Facts Extracted', 'w2f-pc-configurator' ); ?> <span class="dashicons dashicons-arrow-down"></span></h4>';
			html += '<div class="content">';
			html += '<table class="widefat"><thead><tr><th><?php esc_html_e( 'Key', 'w2f-pc-configurator' ); ?></th><th><?php esc_html_e( 'Value', 'w2f-pc-configurator' ); ?></th></tr></thead><tbody>';
			for (var key in data.facts) {
				var val = data.facts[key];
				if (Array.isArray(val)) val = val.join(', ');
				html += '<tr><td><code>' + escapeHtml(key) + '</code></td><td>' + escapeHtml(String(val)) + '</td></tr>';
			}
			html += '</tbody></table></div></div>';
		}
		
		// Adjustments
		if (data.adjustments && data.adjustments.length > 0) {
			html += '<div class="w2f-pc-result-section collapsible">';
			html += '<h4 class="toggle"><?php esc_html_e( 'Adjustments Applied', 'w2f-pc-configurator' ); ?> (' + data.adjustments.length + ') <span class="dashicons dashicons-arrow-down"></span></h4>';
			html += '<div class="content">';
			html += '<table class="widefat"><thead><tr><th><?php esc_html_e( 'Name', 'w2f-pc-configurator' ); ?></th><th><?php esc_html_e( 'Target', 'w2f-pc-configurator' ); ?></th><th><?php esc_html_e( 'Change', 'w2f-pc-configurator' ); ?></th><th><?php esc_html_e( 'Reason', 'w2f-pc-configurator' ); ?></th></tr></thead><tbody>';
			data.adjustments.forEach(function(adj) {
				html += '<tr><td>' + escapeHtml(adj.name) + '</td>';
				html += '<td><code>' + escapeHtml(adj.target) + '</code></td>';
				html += '<td>' + adj.before + ' → ' + adj.after + ' (Δ' + adj.delta + ')</td>';
				html += '<td>' + escapeHtml(adj.reason) + '</td></tr>';
			});
			html += '</tbody></table></div></div>';
		}
		
		// Calculators
		if (data.calculators && data.calculators.length > 0) {
			html += '<div class="w2f-pc-result-section collapsible">';
			html += '<h4 class="toggle"><?php esc_html_e( 'Calculator Results', 'w2f-pc-configurator' ); ?> <span class="dashicons dashicons-arrow-down"></span></h4>';
			html += '<div class="content">';
			data.calculators.forEach(function(calc) {
				html += '<div class="w2f-pc-calc-result">';
				html += '<strong>' + escapeHtml(calc.name) + '</strong>: <code>' + escapeHtml(calc.output_key) + '</code> = <strong>' + calc.value + '</strong>';
				if (calc.breakdown && calc.breakdown.length > 0) {
					html += '<ul>';
					calc.breakdown.forEach(function(item) {
						html += '<li>' + escapeHtml(item.label) + ': ' + item.value + (item.unit ? ' ' + item.unit : '') + '</li>';
					});
					html += '</ul>';
				}
				html += '</div>';
			});
			html += '</div></div>';
		}
		
		// Policies
		if (data.policies && data.policies.length > 0) {
			html += '<div class="w2f-pc-result-section">';
			html += '<h4><?php esc_html_e( 'Policy Evaluations', 'w2f-pc-configurator' ); ?></h4>';
			html += '<table class="widefat"><thead><tr><th><?php esc_html_e( 'Policy', 'w2f-pc-configurator' ); ?></th><th><?php esc_html_e( 'Status', 'w2f-pc-configurator' ); ?></th><th><?php esc_html_e( 'Compared', 'w2f-pc-configurator' ); ?></th><th><?php esc_html_e( 'Message', 'w2f-pc-configurator' ); ?></th></tr></thead><tbody>';
			data.policies.forEach(function(p) {
				var statusClass = p.passed ? 'passed' : (p.severity === 'block' ? 'blocked' : 'warned');
				var statusText = p.passed ? '✓' : (p.severity === 'block' ? '✗ BLOCK' : '⚠ WARN');
				html += '<tr class="' + statusClass + '">';
				html += '<td><strong>' + escapeHtml(p.name) + '</strong><br><small>' + escapeHtml(p.type) + '</small></td>';
				html += '<td class="status">' + statusText + '</td>';
				html += '<td>';
				if (p.compared) {
					var left = p.compared.left;
					var right = p.compared.right;
					if (Array.isArray(left)) left = '[' + left.join(', ') + ']';
					if (Array.isArray(right)) right = '[' + right.join(', ') + ']';
					html += '<code>' + escapeHtml(String(left)) + '</code> ' + escapeHtml(p.compared.operator || '?') + ' <code>' + escapeHtml(String(right)) + '</code>';
				}
				html += '</td>';
				html += '<td>' + (p.message ? escapeHtml(p.message) : '-') + '</td>';
				html += '</tr>';
			});
			html += '</tbody></table></div>';
		}
		
		$('#simulation-results').html(html);
		
		// Toggle sections
		$('.w2f-pc-result-section.collapsible .toggle').on('click', function() {
			$(this).next('.content').slideToggle();
			$(this).find('.dashicons').toggleClass('dashicons-arrow-down dashicons-arrow-up');
		});
	}
	
	function escapeHtml(text) {
		if (text === null || text === undefined) return '';
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(text));
		return div.innerHTML;
	}
});
</script>

<style>
.w2f-pc-admin .w2f-pc-simulator-container { display: flex; gap: 20px; }
.w2f-pc-admin .w2f-pc-simulator-config { flex: 1; min-width: 350px; }
.w2f-pc-admin .w2f-pc-simulator-results { flex: 2; }
.w2f-pc-admin .w2f-pc-card { background: #fff; border: 1px solid #ccd0d4; padding: 15px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
.w2f-pc-admin .w2f-pc-simulator-components th { padding: 8px 10px 8px 0; vertical-align: middle; }
.w2f-pc-admin .w2f-pc-simulator-components td { padding: 8px 0; }

.w2f-pc-admin .w2f-pc-result-header { padding: 15px; border-radius: 4px; margin-bottom: 15px; }
.w2f-pc-admin .w2f-pc-result-header.success { background: #d4edda; border: 1px solid #c3e6cb; }
.w2f-pc-admin .w2f-pc-result-header.error { background: #f8d7da; border: 1px solid #f5c6cb; }
.w2f-pc-admin .w2f-pc-result-header h3 { margin: 0 0 10px 0; }
.w2f-pc-admin .w2f-pc-result-header ul { margin: 5px 0; padding-left: 20px; }
.w2f-pc-admin .w2f-pc-result-header ul.errors li { color: #721c24; }
.w2f-pc-admin .w2f-pc-result-header ul.warnings li { color: #856404; }

.w2f-pc-admin .w2f-pc-result-section { margin-bottom: 15px; background: #f8f9fa; border: 1px solid #e9ecef; padding: 10px 15px; }
.w2f-pc-admin .w2f-pc-result-section h4 { margin: 0 0 10px 0; }
.w2f-pc-admin .w2f-pc-result-section.collapsible .toggle { cursor: pointer; }
.w2f-pc-admin .w2f-pc-result-section.collapsible .content { display: none; }

.w2f-pc-admin .w2f-pc-stats-grid { display: flex; gap: 15px; }
.w2f-pc-admin .w2f-pc-stats-grid .stat { text-align: center; padding: 10px 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px; }
.w2f-pc-admin .w2f-pc-stats-grid .stat .value { display: block; font-size: 24px; font-weight: bold; }
.w2f-pc-admin .w2f-pc-stats-grid .stat .label { font-size: 12px; color: #666; }
.w2f-pc-admin .w2f-pc-stats-grid .stat.success .value { color: #28a745; }
.w2f-pc-admin .w2f-pc-stats-grid .stat.error .value { color: #dc3545; }
.w2f-pc-admin .w2f-pc-stats-grid .stat.warning .value { color: #ffc107; }

.w2f-pc-admin .w2f-pc-calc-result { margin-bottom: 10px; padding: 10px; background: #fff; border: 1px solid #ddd; }
.w2f-pc-admin .w2f-pc-calc-result ul { margin: 5px 0 0 20px; }

.w2f-pc-admin tr.passed { background: #d4edda !important; }
.w2f-pc-admin tr.blocked { background: #f8d7da !important; }
.w2f-pc-admin tr.warned { background: #fff3cd !important; }
.w2f-pc-admin td.status { font-weight: bold; white-space: nowrap; }
</style>
