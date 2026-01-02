<?php
/**
 * Compatibility rules management page template
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get all configurator products for dropdown.
$configurator_products = wc_get_products( array(
	'type' => 'pc_configurator',
	'limit' => -1,
	'status' => 'publish',
) );

// Get all product categories for dropdown.
$product_categories = get_terms( array(
	'taxonomy' => 'product_cat',
	'hide_empty' => false,
) );

// Get all product attributes for dropdown.
$attribute_taxonomies = wc_get_attribute_taxonomies();
$attributes = array();
foreach ( $attribute_taxonomies as $tax ) {
	$attributes[ 'pa_' . $tax->attribute_name ] = $tax->attribute_label;
}

// Get components from all configurator products - group by component ID for global rules.
$all_components = array();
$component_id_map = array(); // Track which products use each component ID
foreach ( $configurator_products as $config_product ) {
	if ( is_a( $config_product, 'W2F_PC_Product' ) ) {
		$components = $config_product->get_components();
		foreach ( $components as $component_id => $component ) {
			// Store by component_id only (global)
			if ( ! isset( $all_components[ $component_id ] ) ) {
				$all_components[ $component_id ] = array(
					'component_id' => $component_id,
					'component_title' => $component->get_title(),
					'products' => array(),
				);
			}
			// Track which products use this component ID
			$all_components[ $component_id ]['products'][] = array(
				'product_id' => $config_product->get_id(),
				'product_name' => $config_product->get_name(),
			);
		}
	}
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'PC Compatibility Rules', 'w2f-pc-configurator' ); ?></h1>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Compatibility rule saved.', 'w2f-pc-configurator' ); ?></p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Compatibility rule deleted.', 'w2f-pc-configurator' ); ?></p></div>
	<?php endif; ?>

	<div class="w2f-pc-compatibility-rules">
		<div class="rules-list">
			<h2><?php esc_html_e( 'Existing Rules', 'w2f-pc-configurator' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'w2f-pc-configurator' ); ?></th>
						<th><?php esc_html_e( 'Type', 'w2f-pc-configurator' ); ?></th>
						<th><?php esc_html_e( 'Action', 'w2f-pc-configurator' ); ?></th>
						<th><?php esc_html_e( 'Status', 'w2f-pc-configurator' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'w2f-pc-configurator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rules ) ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No compatibility rules found.', 'w2f-pc-configurator' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rules as $rule_id => $rule_data ) : ?>
							<tr>
								<td><?php echo esc_html( $rule_data['name'] ); ?></td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $rule_data['type'] ) ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $rule_data['action'] ) ); ?></td>
								<td><?php echo isset( $rule_data['is_active'] ) && 'yes' === $rule_data['is_active'] ? esc_html__( 'Active', 'w2f-pc-configurator' ) : esc_html__( 'Inactive', 'w2f-pc-configurator' ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=w2f-pc-compatibility&edit=' . $rule_id ) ); ?>"><?php esc_html_e( 'Edit', 'w2f-pc-configurator' ); ?></a> |
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=w2f_pc_delete_compatibility_rule&rule_id=' . $rule_id ), 'w2f-pc-delete-compatibility-rule' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this rule?', 'w2f-pc-configurator' ); ?>');"><?php esc_html_e( 'Delete', 'w2f-pc-configurator' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="rule-form">
			<h2><?php echo $rule ? esc_html__( 'Edit Rule', 'w2f-pc-configurator' ) : esc_html__( 'Add New Rule', 'w2f-pc-configurator' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="w2f-pc-rule-form">
				<?php wp_nonce_field( 'w2f-pc-save-compatibility-rule' ); ?>
				<input type="hidden" name="action" value="w2f_pc_save_compatibility_rule" />
				<?php if ( $rule ) : ?>
					<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule_id ); ?>" />
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th><label for="rule_name"><?php esc_html_e( 'Rule Name', 'w2f-pc-configurator' ); ?></label></th>
						<td><input type="text" id="rule_name" name="rule_name" value="<?php echo $rule ? esc_attr( $rule['name'] ) : ''; ?>" class="regular-text" required /></td>
					</tr>
					<tr>
						<th><label for="rule_type"><?php esc_html_e( 'Rule Type', 'w2f-pc-configurator' ); ?></label></th>
						<td>
							<select id="rule_type" name="rule_type">
								<option value="product_match" <?php selected( $rule ? $rule['type'] : '', 'product_match' ); ?>><?php esc_html_e( 'Product Match', 'w2f-pc-configurator' ); ?></option>
								<option value="category_exclude" <?php selected( $rule ? $rule['type'] : '', 'category_exclude' ); ?>><?php esc_html_e( 'Category Exclude', 'w2f-pc-configurator' ); ?></option>
								<option value="numeric_attribute" <?php selected( $rule ? $rule['type'] : '', 'numeric_attribute' ); ?>><?php esc_html_e( 'Numeric Attribute', 'w2f-pc-configurator' ); ?></option>
								<option value="attribute_match" <?php selected( $rule ? $rule['type'] : '', 'attribute_match' ); ?>><?php esc_html_e( 'Attribute Match', 'w2f-pc-configurator' ); ?></option>
								<option value="spec_match" <?php selected( $rule ? $rule['type'] : '', 'spec_match' ); ?>><?php esc_html_e( 'Spec Match', 'w2f-pc-configurator' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="rule_action"><?php esc_html_e( 'Action', 'w2f-pc-configurator' ); ?></label></th>
						<td>
							<select id="rule_action" name="rule_action">
								<option value="require" <?php selected( $rule ? $rule['action'] : '', 'require' ); ?>><?php esc_html_e( 'Require', 'w2f-pc-configurator' ); ?></option>
								<option value="exclude" <?php selected( $rule ? $rule['action'] : '', 'exclude' ); ?>><?php esc_html_e( 'Exclude', 'w2f-pc-configurator' ); ?></option>
								<option value="warn" <?php selected( $rule ? $rule['action'] : '', 'warn' ); ?>><?php esc_html_e( 'Warn', 'w2f-pc-configurator' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="rule_message"><?php esc_html_e( 'Message', 'w2f-pc-configurator' ); ?></label></th>
						<td><input type="text" id="rule_message" name="rule_message" value="<?php echo $rule ? esc_attr( $rule['message'] ) : ''; ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Optional custom message to display', 'w2f-pc-configurator' ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="rule_is_active"><?php esc_html_e( 'Active', 'w2f-pc-configurator' ); ?></label></th>
						<td><input type="checkbox" id="rule_is_active" name="rule_is_active" value="yes" <?php checked( $rule && isset( $rule['is_active'] ) && 'yes' === $rule['is_active'], true ); ?> /></td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Conditions', 'w2f-pc-configurator' ); ?></label></th>
						<td>
							<div id="rule_conditions">
								<p class="description"><?php esc_html_e( 'Select a rule type to configure conditions.', 'w2f-pc-configurator' ); ?></p>
							</div>
						</td>
					</tr>
				</table>

				<?php submit_button( $rule ? __( 'Update Rule', 'w2f-pc-configurator' ) : __( 'Add Rule', 'w2f-pc-configurator' ) ); ?>
			</form>

			<!-- Rule Preview Section - Always Visible -->
			<div class="w2f-pc-rule-preview" id="w2f-pc-rule-preview">
				<h3><?php esc_html_e( 'Rule Impact Preview', 'w2f-pc-configurator' ); ?></h3>
				<div class="w2f-pc-preview-content">
					<div class="w2f-pc-preview-loading" style="display: none;">
						<span class="spinner is-active"></span>
						<?php esc_html_e( 'Analyzing rule impact...', 'w2f-pc-configurator' ); ?>
					</div>
					<div class="w2f-pc-preview-results">
						<p class="description"><?php esc_html_e( 'Fill in the rule conditions above to see the impact preview.', 'w2f-pc-configurator' ); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	var allComponents = <?php echo json_encode( $all_components ); ?>;
	var productCategories = <?php echo json_encode( array_map( function( $cat ) { return array( 'id' => $cat->term_id, 'name' => $cat->name ); }, $product_categories ) ); ?>;
	var attributes = <?php echo json_encode( $attributes ); ?>;
	
	// Store current conditions from PHP (for editing existing rules).
	var currentConditions = <?php echo isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? json_encode( $rule['conditions'] ) : '{}'; ?>;

	function updateRuleConditions() {
		var ruleType = $('#rule_type').val();
		var conditionsHtml = '';
		
		// Helper function to get condition value safely.
		function getCondition(key) {
			return currentConditions && currentConditions[key] ? currentConditions[key] : '';
		}

		if (ruleType === 'category_exclude') {
			conditionsHtml = '<div class="rule-condition-group">' +
				'<h4><?php esc_html_e( 'Component A (Trigger)', 'w2f-pc-configurator' ); ?></h4>' +
				'<p class="description"><?php esc_html_e( 'Rules apply globally to all PC Configurator products that use this component.', 'w2f-pc-configurator' ); ?></p>' +
				'<p><label><?php esc_html_e( 'Component:', 'w2f-pc-configurator' ); ?> <select name="rule_conditions[component_a]" class="component-select">' +
				'<option value=""><?php esc_html_e( 'Select component...', 'w2f-pc-configurator' ); ?></option>';
			$.each(allComponents, function(componentId, comp) {
				var selected = getCondition('component_a') === componentId ? 'selected' : '';
				var productList = comp.products.map(function(p) { return p.product_name; }).join(', ');
				var label = comp.component_title + ' (used in: ' + productList + ')';
				conditionsHtml += '<option value="' + componentId + '" ' + selected + '>' + label + '</option>';
			});
			conditionsHtml += '</select></label></p>' +
				'<p><label><?php esc_html_e( 'Category:', 'w2f-pc-configurator' ); ?> <select name="rule_conditions[category_a]">' +
				'<option value=""><?php esc_html_e( 'Select category...', 'w2f-pc-configurator' ); ?></option>';
			$.each(productCategories, function(i, cat) {
				var selected = getCondition('category_a') == cat.id ? 'selected' : '';
				conditionsHtml += '<option value="' + cat.id + '" ' + selected + '>' + cat.name + '</option>';
			});
			conditionsHtml += '</select></label></p>' +
				'</div>' +
				'<div class="rule-condition-group">' +
				'<h4><?php esc_html_e( 'Component B (Excluded)', 'w2f-pc-configurator' ); ?></h4>' +
				'<p class="description"><?php esc_html_e( 'Rules apply globally to all PC Configurator products that use this component.', 'w2f-pc-configurator' ); ?></p>' +
				'<p><label><?php esc_html_e( 'Component:', 'w2f-pc-configurator' ); ?> <select name="rule_conditions[component_b]" class="component-select">' +
				'<option value=""><?php esc_html_e( 'Select component...', 'w2f-pc-configurator' ); ?></option>';
			$.each(allComponents, function(componentId, comp) {
				var selected = getCondition('component_b') === componentId ? 'selected' : '';
				var productList = comp.products.map(function(p) { return p.product_name; }).join(', ');
				var label = comp.component_title + ' (used in: ' + productList + ')';
				conditionsHtml += '<option value="' + componentId + '" ' + selected + '>' + label + '</option>';
			});
			conditionsHtml += '</select></label></p>' +
				'<p><label><?php esc_html_e( 'Category:', 'w2f-pc-configurator' ); ?> <select name="rule_conditions[category_b]">' +
				'<option value=""><?php esc_html_e( 'Select category...', 'w2f-pc-configurator' ); ?></option>';
			$.each(productCategories, function(i, cat) {
				var selected = getCondition('category_b') == cat.id ? 'selected' : '';
				conditionsHtml += '<option value="' + cat.id + '" ' + selected + '>' + cat.name + '</option>';
			});
			conditionsHtml += '</select></label></p>' +
				'</div>';
		} else if (ruleType === 'numeric_attribute') {
			conditionsHtml = '<div class="rule-condition-group">' +
				'<h4><?php esc_html_e( 'Component A', 'w2f-pc-configurator' ); ?></h4>' +
				'<p class="description"><?php esc_html_e( 'Rules apply globally to all PC Configurator products that use this component.', 'w2f-pc-configurator' ); ?></p>' +
				'<p><label><?php esc_html_e( 'Component:', 'w2f-pc-configurator' ); ?> <select name="rule_conditions[component_a]" class="component-select">' +
				'<option value=""><?php esc_html_e( 'Select component...', 'w2f-pc-configurator' ); ?></option>';
			$.each(allComponents, function(componentId, comp) {
				var selected = getCondition('component_a') === componentId ? 'selected' : '';
				var productList = comp.products.map(function(p) { return p.product_name; }).join(', ');
				var label = comp.component_title + ' (used in: ' + productList + ')';
				conditionsHtml += '<option value="' + componentId + '" ' + selected + '>' + label + '</option>';
			});
			conditionsHtml += '</select></label></p>' +
				'<p><label><?php esc_html_e( 'Attribute:', 'w2f-pc-configurator' ); ?> <select name="rule_conditions[attribute_a]">' +
				'<option value=""><?php esc_html_e( 'Select attribute...', 'w2f-pc-configurator' ); ?></option>';
			$.each(attributes, function(key, label) {
				var selected = getCondition('attribute_a') === key ? 'selected' : '';
				conditionsHtml += '<option value="' + key + '" ' + selected + '>' + label + '</option>';
			});
			conditionsHtml += '</select></label></p>' +
				'</div>' +
				'<div class="rule-condition-group">' +
				'<h4><?php esc_html_e( 'Component B', 'w2f-pc-configurator' ); ?></h4>' +
				'<p class="description"><?php esc_html_e( 'Compare Component A attribute against Component B attribute.', 'w2f-pc-configurator' ); ?></p>' +
				'<p><label><?php esc_html_e( 'Component:', 'w2f-pc-configurator' ); ?> <select name="rule_conditions[component_b]" class="component-select">' +
				'<option value=""><?php esc_html_e( 'Select component...', 'w2f-pc-configurator' ); ?></option>';
			$.each(allComponents, function(componentId, comp) {
				var selected = getCondition('component_b') === componentId ? 'selected' : '';
				var productList = comp.products.map(function(p) { return p.product_name; }).join(', ');
				var label = comp.component_title + ' (used in: ' + productList + ')';
				conditionsHtml += '<option value="' + componentId + '" ' + selected + '>' + label + '</option>';
			});
			conditionsHtml += '</select></label></p>' +
				'<p><label><?php esc_html_e( 'Attribute:', 'w2f-pc-configurator' ); ?> <select name="rule_conditions[attribute_b]">' +
				'<option value=""><?php esc_html_e( 'Select attribute...', 'w2f-pc-configurator' ); ?></option>';
			$.each(attributes, function(key, label) {
				var selected = getCondition('attribute_b') === key ? 'selected' : '';
				conditionsHtml += '<option value="' + key + '" ' + selected + '>' + label + '</option>';
			});
			conditionsHtml += '</select></label></p>' +
				'</div>' +
				'<div class="rule-condition-group">' +
				'<h4><?php esc_html_e( 'Comparison', 'w2f-pc-configurator' ); ?></h4>' +
				'<p class="description"><?php esc_html_e( 'If Component A attribute is greater than Component B attribute, show error and prevent purchase.', 'w2f-pc-configurator' ); ?></p>' +
				'<p><label><?php esc_html_e( 'Operator:', 'w2f-pc-configurator' ); ?> <select name="rule_conditions[operator]">' +
				'<option value=">" ' + (getCondition('operator') === '>' ? 'selected' : '') + '><?php esc_html_e( 'Greater Than (>)', 'w2f-pc-configurator' ); ?></option>' +
				'<option value=">=" ' + (getCondition('operator') === '>=' ? 'selected' : '') + '><?php esc_html_e( 'Greater Than or Equal (>=)', 'w2f-pc-configurator' ); ?></option>' +
				'<option value="<" ' + (getCondition('operator') === '<' ? 'selected' : '') + '><?php esc_html_e( 'Less Than (<)', 'w2f-pc-configurator' ); ?></option>' +
				'<option value="<=" ' + (getCondition('operator') === '<=' ? 'selected' : '') + '><?php esc_html_e( 'Less Than or Equal (<=)', 'w2f-pc-configurator' ); ?></option>' +
				'<option value="==" ' + (getCondition('operator') === '==' ? 'selected' : '') + '><?php esc_html_e( 'Equal (==)', 'w2f-pc-configurator' ); ?></option>' +
				'<option value="!=" ' + (getCondition('operator') === '!=' ? 'selected' : '') + '><?php esc_html_e( 'Not Equal (!=)', 'w2f-pc-configurator' ); ?></option>' +
				'</select></label></p>' +
				'<p class="description"><strong><?php esc_html_e( 'Example:', 'w2f-pc-configurator' ); ?></strong> <?php esc_html_e( 'If GPU Minimum Wattage > PSU Wattage, show error.', 'w2f-pc-configurator' ); ?></p>' +
				'</div>';
		} else {
			// For other rule types, show basic conditions (can be extended later).
			conditionsHtml = '<p class="description"><?php esc_html_e( 'Configure conditions based on the selected rule type. This interface will be enhanced in future updates.', 'w2f-pc-configurator' ); ?></p>';
		}

		$('#rule_conditions').html(conditionsHtml);
		
		// After updating HTML, restore any saved values from form fields if they exist.
		// This handles the case where the form was submitted but had validation errors.
		$('#rule_conditions').find('select, input').each(function() {
			var $field = $(this);
			var name = $field.attr('name');
			if (name && name.indexOf('rule_conditions[') === 0) {
				// Extract the key from name like "rule_conditions[component_a]"
				var key = name.match(/\[([^\]]+)\]/);
				if (key && key[1] && currentConditions[key[1]]) {
					$field.val(currentConditions[key[1]]);
				}
			}
		});
	}

	$('#rule_type').on('change', function() {
		// Save current form values before changing rule type.
		var formData = {};
		$('#rule_conditions').find('select, input').each(function() {
			var $field = $(this);
			var name = $field.attr('name');
			if (name && name.indexOf('rule_conditions[') === 0) {
				var key = name.match(/\[([^\]]+)\]/);
				if (key && key[1]) {
					formData[key[1]] = $field.val();
				}
			}
		});
		// Merge saved form data with current conditions.
		currentConditions = $.extend({}, currentConditions, formData);
		updateRuleConditions();
	});
	
	// Initial load.
	updateRuleConditions();

	// Rule Preview functionality - Always visible and dynamic.
	var previewTimeout;
	var $previewSection = $('#w2f-pc-rule-preview');
	var $previewResults = $('.w2f-pc-preview-results');
	var $previewLoading = $('.w2f-pc-preview-loading');

	function triggerRulePreview() {
		clearTimeout(previewTimeout);
		previewTimeout = setTimeout(function() {
			// Collect rule data from form.
			var ruleData = {
				name: $('#rule_name').val() || 'Preview Rule',
				type: $('#rule_type').val(),
				action: $('#rule_action').val(),
				conditions: {}
			};

			// Get conditions based on rule type.
			$('#rule_conditions input, #rule_conditions select').each(function() {
				var $field = $(this);
				var name = $field.attr('name');
				if (name && name.startsWith('rule_conditions[')) {
					var key = name.match(/rule_conditions\[(.*?)\]/)[1];
					var value = $field.val();
					// Handle numeric values for numeric_attribute rules.
					if (key === 'value_a' || key === 'value_b') {
						value = parseFloat(value) || 0;
					}
					ruleData.conditions[key] = value;
				}
			});

			// Only preview if we have minimum required data.
			if (!ruleData.type || Object.keys(ruleData.conditions).length === 0) {
				$previewResults.html('<p class="description"><?php esc_html_e( 'Please fill in rule conditions to see preview.', 'w2f-pc-configurator' ); ?></p>');
				return;
			}

			$previewLoading.show();
			$previewResults.empty();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'w2f_pc_preview_rule',
					security: '<?php echo esc_js( wp_create_nonce( 'w2f-pc-admin' ) ); ?>',
					rule_data: ruleData
				},
				success: function(response) {
					$previewLoading.hide();
					if (response.success && response.data) {
						console.log('Preview data:', response.data); // Debug
						displayPreviewResults(response.data);
					} else {
						console.error('Preview error:', response);
						$previewResults.html('<p class="error"><?php esc_html_e( 'Error loading preview: ', 'w2f-pc-configurator' ); ?>' + (response.data && response.data.message ? response.data.message : '<?php esc_html_e( 'Unknown error', 'w2f-pc-configurator' ); ?>') + '</p>');
					}
				},
				error: function(xhr, status, error) {
					$previewLoading.hide();
					console.error('AJAX error:', status, error);
					$previewResults.html('<p class="error"><?php esc_html_e( 'Error loading preview: ', 'w2f-pc-configurator' ); ?>' + error + '</p>');
				}
			});
		}, 300);
	}

	function displayPreviewResults(data) {
		var html = '<div class="w2f-pc-preview-stats">';
		html += '<div class="w2f-pc-preview-stat"><strong>' + data.total_impact + '</strong> <?php esc_html_e( 'Configurators Affected', 'w2f-pc-configurator' ); ?></div>';
		html += '<div class="w2f-pc-preview-stat"><strong>' + data.affected_products.length + '</strong> <?php esc_html_e( 'Products Affected', 'w2f-pc-configurator' ); ?></div>';
		html += '<div class="w2f-pc-preview-stat"><strong>' + data.incompatible_combinations.length + '</strong> <?php esc_html_e( 'Incompatible Combinations', 'w2f-pc-configurator' ); ?></div>';
		html += '</div>';

		// Show affected products.
		if (data.affected_products.length > 0) {
			html += '<div class="w2f-pc-preview-section">';
			html += '<h4><?php esc_html_e( 'Affected Products', 'w2f-pc-configurator' ); ?> (' + data.affected_products.length + ')</h4>';
			html += '<div class="w2f-pc-products-list">';
			
			var productsToShow = data.affected_products.slice(0, 50);
			html += '<ul class="w2f-pc-preview-list">';
			productsToShow.forEach(function(product) {
				var productId = typeof product === 'object' ? product.id : product;
				var productName = typeof product === 'object' ? product.name : 'Product ID: ' + product;
				html += '<li><strong>' + productName + '</strong> <span class="description">(ID: ' + productId + ')</span></li>';
			});
			if (data.affected_products.length > 50) {
				html += '<li class="description">+ ' + (data.affected_products.length - 50) + ' <?php esc_html_e( 'more products', 'w2f-pc-configurator' ); ?></li>';
			}
			html += '</ul>';
			html += '</div>';
			html += '</div>';
		}

		// Show incompatible combinations.
		if (data.incompatible_combinations.length > 0) {
			html += '<div class="w2f-pc-preview-section">';
			html += '<h4><?php esc_html_e( 'Incompatible Product Combinations', 'w2f-pc-configurator' ); ?> (' + data.incompatible_combinations.length + ')</h4>';
			html += '<table class="w2f-pc-incompatible-table">';
			html += '<thead><tr>';
			html += '<th><?php esc_html_e( 'Configurator', 'w2f-pc-configurator' ); ?></th>';
			html += '<th><?php esc_html_e( 'Component A', 'w2f-pc-configurator' ); ?></th>';
			html += '<th><?php esc_html_e( 'Product A', 'w2f-pc-configurator' ); ?></th>';
			html += '<th><?php esc_html_e( 'Component B', 'w2f-pc-configurator' ); ?></th>';
			html += '<th><?php esc_html_e( 'Product B', 'w2f-pc-configurator' ); ?></th>';
			html += '<th><?php esc_html_e( 'Status', 'w2f-pc-configurator' ); ?></th>';
			html += '<th><?php esc_html_e( 'Message', 'w2f-pc-configurator' ); ?></th>';
			html += '</tr></thead>';
			html += '<tbody>';
			
			data.incompatible_combinations.slice(0, 20).forEach(function(combo) {
				var badgeClass = combo.result === 'blocked' ? 'w2f-pc-log-incompatible' : 'w2f-pc-log-warning';
				var badgeText = combo.result === 'blocked' ? '<?php esc_html_e( 'Blocked', 'w2f-pc-configurator' ); ?>' : '<?php esc_html_e( 'Warning', 'w2f-pc-configurator' ); ?>';
				html += '<tr>';
				html += '<td>' + combo.configurator_name + '</td>';
				html += '<td>' + (combo.component_a || '—') + '</td>';
				html += '<td>' + (combo.product_a_name || '—') + (combo.product_a_id ? ' <span class="description">(ID: ' + combo.product_a_id + ')</span>' : '') + '</td>';
				html += '<td>' + (combo.component_b || '—') + '</td>';
				html += '<td>' + (combo.product_b_name || '—') + (combo.product_b_id ? ' <span class="description">(ID: ' + combo.product_b_id + ')</span>' : '') + '</td>';
				html += '<td><span class="w2f-pc-log-badge ' + badgeClass + '">' + badgeText + '</span></td>';
				html += '<td>' + (combo.message || '—') + '</td>';
				html += '</tr>';
			});
			
			html += '</tbody>';
			html += '</table>';
			
			if (data.incompatible_combinations.length > 20) {
				html += '<p class="description"><?php esc_html_e( 'Showing first 20 incompatible combinations. Total:', 'w2f-pc-configurator' ); ?> ' + data.incompatible_combinations.length + '</p>';
			}
			html += '</div>';
		} else if (data.total_impact > 0) {
			html += '<div class="w2f-pc-preview-section">';
			html += '<p class="description"><?php esc_html_e( 'No incompatible combinations found with current rule conditions.', 'w2f-pc-configurator' ); ?></p>';
			html += '</div>';
		}

		$previewResults.html(html);
	}

	// Watch for form changes to trigger preview - debounced.
	$('#w2f-pc-rule-form').on('change input', '#rule_type, #rule_action, #rule_conditions input, #rule_conditions select', triggerRulePreview);
	$('#w2f-pc-rule-form').on('keyup', '#rule_conditions input[type="text"], #rule_conditions input[type="number"]', triggerRulePreview);
	
	// Trigger preview on page load if editing existing rule.
	<?php if ( $rule ) : ?>
		setTimeout(triggerRulePreview, 500);
	<?php endif; ?>
});
</script>

<style>
.w2f-pc-rule-preview {
	background: #fff;
	border: 1px solid #ccd0d4;
	padding: 15px;
	margin: 20px 0;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.w2f-pc-rule-preview h3 {
	margin-top: 0;
	display: flex;
	justify-content: space-between;
	align-items: center;
}
.w2f-pc-preview-toggle {
	cursor: pointer;
}
.w2f-pc-preview-stats {
	display: flex;
	gap: 20px;
	margin: 15px 0;
}
.w2f-pc-preview-stat {
	padding: 10px;
	background: #f0f0f1;
	border-radius: 4px;
	text-align: center;
}
.w2f-pc-preview-stat strong {
	display: block;
	font-size: 24px;
	color: #2271b1;
}
.w2f-pc-preview-list {
	list-style: disc;
	margin-left: 20px;
}
.w2f-pc-preview-list li {
	margin: 5px 0;
}
</style>

<style>
.rule-condition-group {
	margin: 15px 0;
	padding: 15px;
	background: #f9f9f9;
	border: 1px solid #ddd;
}
.rule-condition-group h4 {
	margin-top: 0;
}
.rule-condition-group p {
	margin: 10px 0;
}
.component-select {
	min-width: 300px;
}
</style>
