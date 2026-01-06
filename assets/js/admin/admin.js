/**
 * PC Configurator Admin JavaScript
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

(function($) {
	'use strict';

	var W2FPCAdmin = {
		componentCounter: 0,
		priceUpdateTimeout: null,

		init: function() {
			this.bindEvents();
			this.initProductSearch();
			this.updateTabSelects();
			this.initSortable();
			this.updatePriceBreakdown();
			// Ensure all existing components are collapsed on page load.
			this.collapseAllComponents();
		},

		bindEvents: function() {
			var self = this;

			// Export configuration button.
			$(document).on('click', '#w2f-pc-export-config', function(e) {
				e.preventDefault();
				self.exportConfiguration();
			});

			// Import configuration button.
			$(document).on('click', '#w2f-pc-import-config', function(e) {
				e.preventDefault();
				$('#w2f-pc-import-file').click();
			});

			// Handle file selection for import.
			$(document).on('change', '#w2f-pc-import-file', function(e) {
				var file = e.target.files[0];
				if (file) {
					var reader = new FileReader();
					reader.onload = function(e) {
						try {
							var importData = JSON.parse(e.target.result);
							self.importConfiguration(importData);
						} catch (error) {
							self.showImportMessage(__('Invalid JSON file. Please select a valid configuration file.', 'w2f-pc-configurator'), 'error');
						}
					};
					reader.readAsText(file);
				}
			});

			// Add component button.
			$(document).on('click', '.add_component', function(e) {
				e.preventDefault();
				self.addComponent();
			});

			// Remove component button.
			$(document).on('click', '.remove_component', function(e) {
				e.preventDefault();
				$(this).closest('.w2f_pc_component').remove();
				self.updateDefaultConfigurationSection();
			});

			// Add tab button.
			$(document).on('click', '.add_tab', function(e) {
				e.preventDefault();
				self.addTab();
			});

			// Remove tab button.
			$(document).on('click', '.remove_tab', function(e) {
				e.preventDefault();
				$(this).closest('.w2f-pc-tab-item').remove();
				self.updateTabSelects();
			});

			// Update tab selects when tabs change.
			$(document).on('input', '.w2f-pc-tab-item input', function() {
				self.updateTabSelects();
			});

			// Update component title display.
			$(document).on('input', '.component_title', function() {
				var $component = $(this).closest('.w2f_pc_component');
				var title = $(this).val() || 'New Component';
				$component.find('.component_title_display').text(title);
			});

			// Component accordion toggle.
			$(document).on('click', '.w2f-pc-component-toggle', function(e) {
				// Don't toggle if clicking on drag handle or remove button.
				if ($(e.target).closest('.component_drag_handle, .remove_component').length) {
					return;
				}
				e.preventDefault();
				var $header = $(this);
				var $component = $header.closest('.w2f_pc_component');
				var $content = $component.find('.w2f-pc-component-content');
				var isExpanded = $header.attr('aria-expanded') === 'true';
				
				$header.attr('aria-expanded', !isExpanded);
				
				if (isExpanded) {
					$content.slideUp(200);
					$header.addClass('collapsed');
				} else {
					$content.slideDown(200);
					$header.removeClass('collapsed');
				}
			});

			// Keyboard support for accordion.
			$(document).on('keydown', '.w2f-pc-component-toggle', function(e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					$(this).trigger('click');
				}
			});

			// Toggle quantity fields when enable quantity checkbox is changed.
			$(document).on('change', '.w2f-pc-enable-quantity-checkbox', function() {
				var $checkbox = $(this);
				var $component = $checkbox.closest('.w2f_pc_component');
				var $quantityFields = $component.find('.w2f-pc-quantity-fields');
				
				if ($checkbox.is(':checked')) {
					$quantityFields.slideDown(200);
				} else {
					$quantityFields.slideUp(200);
				}
			});

			// Save component order on sort.
			$(document).on('sortstop', '.w2f_pc_components', function() {
				self.updateComponentOrder();
			});

			// Ensure empty select fields submit empty arrays.
			$(document).on('submit', '#post', function() {
				$('.w2f_pc_component').each(function() {
					var $component = $(this);
					var componentId = $component.data('component_id') || $component.find('input[name*="[_component_id]"]').val();
					
					if (!componentId) {
						return;
					}

					// Ensure options field exists (even if empty).
					var $optionsSelect = $component.find('select[name*="[options]"]');
					if ($optionsSelect.length && !$optionsSelect.val()) {
						// Add hidden input to ensure empty array is submitted.
						if ($component.find('input[name*="[options][]"]').length === 0) {
							$optionsSelect.after('<input type="hidden" name="w2f_pc_components[' + componentId + '][options][]" value="" />');
						}
					}

					// Ensure categories field exists (even if empty).
					var $categoriesSelect = $component.find('select[name*="[categories]"]');
					if ($categoriesSelect.length && !$categoriesSelect.val()) {
						// Add hidden input to ensure empty array is submitted.
						if ($component.find('input[name*="[categories][]"]').length === 0) {
							$categoriesSelect.after('<input type="hidden" name="w2f_pc_components[' + componentId + '][categories][]" value="" />');
						}
					}
				});
			});
		},

		addComponent: function() {
			// Find the highest existing component number to ensure unique IDs.
			var maxComponentNum = 0;
			$('.w2f_pc_component').each(function() {
				var $component = $(this);
				var componentId = $component.data('component_id') || $component.find('input[name*="[_component_id]"]').val();
				if (componentId && componentId.indexOf('component_') === 0) {
					var num = parseInt(componentId.replace('component_', ''), 10);
					if (num > maxComponentNum) {
						maxComponentNum = num;
					}
				}
			});
			
			this.componentCounter = maxComponentNum + 1;
			var componentId = 'component_' + this.componentCounter;
			var template = $('#tmpl-w2f-pc-component').html();
			template = template.replace(/\{\{component_id\}\}/g, componentId);
			var $newComponent = $(template);
			$('.w2f_pc_components').append($newComponent);
			
			// Keep new component collapsed by default.
			$newComponent.find('.w2f-pc-component-content').hide();
			$newComponent.find('.w2f-pc-component-toggle').attr('aria-expanded', 'false').addClass('collapsed');
			
			this.initProductSearch();
			this.updateTabSelects();
			this.updateDefaultConfigurationSection();
		},

		addTab: function() {
			var $tabItem = $('<p class="form-field w2f-pc-tab-item">' +
				'<input type="text" name="w2f_pc_tabs[]" value="" placeholder="' + w2f_pc_admin_params.i18n.tab_name + '" class="regular-text" />' +
				'<button type="button" class="button remove_tab">' + w2f_pc_admin_params.i18n.remove + '</button>' +
				'</p>');
			$('.w2f_pc_tabs').append($tabItem);
		},

		updateTabSelects: function() {
			var tabs = [];
			$('.w2f-pc-tab-item input').each(function() {
				var tabName = $(this).val().trim();
				if (tabName) {
					tabs.push(tabName);
				}
			});

			// Update all component tab selects.
			$('.component-tab-select').each(function() {
				var $select = $(this);
				var currentValue = $select.val();
				$select.empty();
				$select.append('<option value="">' + (w2f_pc_admin_params.i18n.no_tab || 'No Tab') + '</option>');
				
				$.each(tabs, function(index, tabName) {
					var selected = (currentValue === tabName) ? 'selected="selected"' : '';
					$select.append('<option value="' + tabName + '" ' + selected + '>' + tabName + '</option>');
				});
			});
		},

		initProductSearch: function() {
			var self = this;

			// Initialize regular product search (for available products).
			if (typeof $.fn.selectWoo !== 'undefined') {
				$('.wc-product-search:not(.default-product-search)').selectWoo({
					ajax: {
						url: w2f_pc_admin_params.ajax_url,
						dataType: 'json',
						delay: 250,
						data: function(params) {
							return {
								term: params.term,
								action: 'woocommerce_json_search_products',
								security: w2f_pc_admin_params.nonce
							};
						},
						processResults: function(data) {
							var terms = [];
							if (data) {
								$.each(data, function(id, text) {
									terms.push({
										id: id,
										text: text
									});
								});
							}
							return {
								results: terms
							};
						},
						cache: true
					},
					minimumInputLength: 3
				});
			} else if (typeof $.fn.select2 !== 'undefined') {
				// Fallback to select2 if selectWoo is not available
				$('.wc-product-search:not(.default-product-search)').select2({
					ajax: {
						url: w2f_pc_admin_params.ajax_url,
						dataType: 'json',
						delay: 250,
						data: function(params) {
							return {
								term: params.term,
								action: 'woocommerce_json_search_products',
								security: w2f_pc_admin_params.nonce
							};
						},
						processResults: function(data) {
							var terms = [];
							if (data) {
								$.each(data, function(id, text) {
									terms.push({
										id: id,
										text: text
									});
								});
							}
							return {
								results: terms
							};
						},
						cache: true
					},
					minimumInputLength: 3
				});
			}

			// Initialize default product search (filtered by component options/categories).
			$('.w2f_pc_component').each(function() {
				self.initDefaultProductSearch($(this));
			});

			// Initialize category select (enhanced select)
			if (typeof $.fn.selectWoo !== 'undefined') {
				$('.wc-enhanced-select').selectWoo({
					allowClear: true
				}).on('change', function() {
					// Update default product search when categories change.
					var $component = $(this).closest('.w2f_pc_component');
					self.initDefaultProductSearch($component);
				});
			} else if (typeof $.fn.select2 !== 'undefined') {
				$('.wc-enhanced-select').select2({
					allowClear: true
				}).on('change', function() {
					// Update default product search when categories change.
					var $component = $(this).closest('.w2f_pc_component');
					self.initDefaultProductSearch($component);
				});
			}

			// Update default product search when available products change.
			$('.wc-product-search:not(.default-product-search)').on('change', function() {
				var $component = $(this).closest('.w2f_pc_component');
				self.initDefaultProductSearch($component);
			});
		},

		initDefaultProductSearch: function($component) {
			var $defaultSelect = $component.find('.default-product-search');
			if ($defaultSelect.length === 0) {
				return;
			}

			// Get component options and categories.
			var options = [];
			var categories = [];

			$component.find('.wc-product-search:not(.default-product-search) option:selected').each(function() {
				options.push($(this).val());
			});

			$component.find('.wc-enhanced-select option:selected').each(function() {
				categories.push($(this).val());
			});

			// Destroy existing selectWoo/select2 instance.
			if (typeof $.fn.selectWoo !== 'undefined' && $defaultSelect.hasClass('selectWoo-hidden-accessible')) {
				$defaultSelect.selectWoo('destroy');
			} else if (typeof $.fn.select2 !== 'undefined' && $defaultSelect.hasClass('select2-hidden-accessible')) {
				$defaultSelect.select2('destroy');
			}

			// Initialize with filtered search.
			if (typeof $.fn.selectWoo !== 'undefined') {
				$defaultSelect.selectWoo({
					ajax: {
						url: w2f_pc_admin_params.ajax_url,
						dataType: 'json',
						delay: 250,
						data: function(params) {
							return {
								term: params.term || '',
								action: 'w2f_pc_search_component_products',
								options: options,
								categories: categories,
								security: w2f_pc_admin_params.nonce
							};
						},
						processResults: function(data) {
							var terms = [];
							if (data) {
								$.each(data, function(id, text) {
									terms.push({
										id: id,
										text: text
									});
								});
							}
							return {
								results: terms
							};
						},
						cache: false
					},
					minimumInputLength: 0,
					allowClear: true
				});
			} else if (typeof $.fn.select2 !== 'undefined') {
				$defaultSelect.select2({
					ajax: {
						url: w2f_pc_admin_params.ajax_url,
						dataType: 'json',
						delay: 250,
						data: function(params) {
							return {
								term: params.term || '',
								action: 'w2f_pc_search_component_products',
								options: options,
								categories: categories,
								security: w2f_pc_admin_params.nonce
							};
						},
						processResults: function(data) {
							var terms = [];
							if (data) {
								$.each(data, function(id, text) {
									terms.push({
										id: id,
										text: text
									});
								});
							}
							return {
								results: terms
							};
						},
						cache: false
					},
					minimumInputLength: 0,
					allowClear: true
				});
			}
		},

		initSortable: function() {
			var self = this;
			$('.w2f_pc_components').sortable({
				handle: '.component_drag_handle',
				items: '.w2f_pc_component',
				opacity: 0.6,
				cursor: 'move',
				axis: 'y',
				placeholder: 'w2f-pc-component-placeholder',
				start: function(e, ui) {
					ui.placeholder.height(ui.item.height());
					ui.placeholder.css('background', 'var(--w2f-pc-admin-bg-secondary)');
					ui.placeholder.css('border', '2px dashed var(--w2f-pc-admin-border-color)');
				},
				stop: function(e, ui) {
					self.updateComponentOrder();
				}
			});
		},

		updateComponentOrder: function() {
			// Update component order numbers (for visual reference).
			$('.w2f_pc_component').each(function(index) {
				var $component = $(this);
				var $orderIndicator = $component.find('.component_order');
				if ($orderIndicator.length) {
					$orderIndicator.text((index + 1) + '.');
				}
			});
		},

		updatePriceBreakdown: function() {
			var self = this;
			var $breakdown = $('.w2f-pc-admin-price-breakdown');
			
			if (!$breakdown.length) {
				return;
			}

			// Collect all default product IDs.
			var defaultProducts = [];
			$('.default-product-search').each(function() {
				var $select = $(this);
				var productId = $select.val();
				if (productId && productId !== '0' && productId !== '') {
					defaultProducts.push(parseInt(productId));
				}
			});

			// Get default price.
			var defaultPrice = parseFloat($('#w2f_pc_default_price').val()) || 0;

			// If no default products selected, show zero.
			if (defaultProducts.length === 0) {
				$breakdown.find('.w2f-pc-admin-component-total').text('£0.00');
				$breakdown.find('.w2f-pc-admin-target-price').text('£0.00');
				$breakdown.find('.w2f-pc-admin-discount-row').hide();
				return;
			}

			// Calculate component total via AJAX.
			$.ajax({
				url: w2f_pc_admin_params.ajax_url,
				type: 'POST',
				data: {
					action: 'w2f_pc_admin_calculate_component_total',
					nonce: w2f_pc_admin_params.nonce,
					products: defaultProducts
				},
				success: function(response) {
					if (response.success) {
						var componentTotal = parseFloat(response.data.component_total) || 0;
						var discountPercentage = 0;
						var discountAmount = 0;

						// Calculate discount if default price is set and less than component total.
						if (defaultPrice > 0 && componentTotal > 0 && defaultPrice < componentTotal) {
							discountAmount = componentTotal - defaultPrice;
							discountPercentage = (discountAmount / componentTotal) * 100;
						}

						// Update component total.
						$breakdown.find('.w2f-pc-admin-component-total').text(self.formatPrice(componentTotal));

						// Update target price.
						$breakdown.find('.w2f-pc-admin-target-price').text(self.formatPrice(defaultPrice));

						// Update discount.
						var $discountRow = $breakdown.find('.w2f-pc-admin-discount-row');
						var $discountPercentage = $breakdown.find('.w2f-pc-admin-discount-percentage');
						
						if (discountPercentage > 0) {
							$discountPercentage.text(discountPercentage.toFixed(2) + '%');
							// Update discount amount text.
							var $discountAmount = $discountRow.find('small');
							if ($discountAmount.length) {
								$discountAmount.text('(' + self.formatPrice(discountAmount) + ' discount applied)');
							}
							$discountRow.show();
						} else {
							$discountRow.hide();
						}
					}
				},
				error: function() {
					// Silently fail - price breakdown will show initial values.
				}
			});
		},

		formatPrice: function(price) {
			if (typeof price !== 'number' || isNaN(price)) {
				return '£0.00';
			}
			return '£' + price.toFixed(2);
		},

		collapseAllComponents: function() {
			// Collapse all component accordions on page load.
			$('.w2f_pc_component').each(function() {
				var $component = $(this);
				var $content = $component.find('.w2f-pc-component-content');
				var $header = $component.find('.w2f-pc-component-toggle');
				
				// Only collapse if not already collapsed.
				if ($header.attr('aria-expanded') === 'true') {
					$content.hide();
					$header.attr('aria-expanded', 'false').addClass('collapsed');
				}
			});
		},

		exportConfiguration: function() {
			var self = this;
			var productId = w2f_pc_admin_params.product_id;

			$.ajax({
				url: w2f_pc_admin_params.ajax_url,
				type: 'POST',
				data: {
					action: 'w2f_pc_export_config',
					nonce: w2f_pc_admin_params.nonce,
					product_id: productId
				},
				success: function(response) {
					if (response.success && response.data) {
						// Create a blob and download the file.
						var dataStr = JSON.stringify(response.data, null, 2);
						var dataBlob = new Blob([dataStr], {type: 'application/json'});
						var url = URL.createObjectURL(dataBlob);
						var link = document.createElement('a');
						link.href = url;
						link.download = 'pc-configurator-' + productId + '-' + new Date().getTime() + '.json';
						document.body.appendChild(link);
						link.click();
						document.body.removeChild(link);
						URL.revokeObjectURL(url);
						
						self.showImportMessage('Configuration exported successfully!', 'success');
					} else {
						self.showImportMessage(response.data && response.data.message ? response.data.message : 'Export failed.', 'error');
					}
				},
				error: function() {
					self.showImportMessage('Export failed. Please try again.', 'error');
				}
			});
		},

		importConfiguration: function(importData) {
			var self = this;
			var productId = w2f_pc_admin_params.product_id;

			// Validate import data structure.
			if (!importData.components || !importData.default_configuration || !importData.tabs) {
				self.showImportMessage('Invalid configuration format.', 'error');
				return;
			}

			// Confirm import (will overwrite existing configuration).
			if (!confirm('This will replace your current configuration. Are you sure you want to continue?')) {
				$('#w2f-pc-import-file').val('');
				return;
			}

			$.ajax({
				url: w2f_pc_admin_params.ajax_url,
				type: 'POST',
				data: {
					action: 'w2f_pc_import_config',
					nonce: w2f_pc_admin_params.nonce,
					product_id: productId,
					import_data: JSON.stringify(importData)
				},
				success: function(response) {
					if (response.success) {
						self.showImportMessage(
							'Configuration imported successfully! Please refresh the page to see the changes.',
							'success'
						);
						// Clear file input.
						$('#w2f-pc-import-file').val('');
						// Optionally reload the page after a short delay.
						setTimeout(function() {
							window.location.reload();
						}, 2000);
					} else {
						self.showImportMessage(
							response.data && response.data.message ? response.data.message : 'Import failed.',
							'error'
						);
						$('#w2f-pc-import-file').val('');
					}
				},
				error: function() {
					self.showImportMessage('Import failed. Please try again.', 'error');
					$('#w2f-pc-import-file').val('');
				}
			});
		},

		showImportMessage: function(message, type) {
			var $message = $('#w2f-pc-import-message');
			$message.removeClass('error success').addClass(type);
			$message.html(message);
			$message.fadeIn();
			
			// Auto-hide success messages after 5 seconds.
			if (type === 'success') {
				setTimeout(function() {
					$message.fadeOut();
				}, 5000);
			}
		},

		updateDefaultConfigurationSection: function() {
			// This function will be called to update the default configuration section
			// when components are added or removed. For now, just re-initialize product search.
			this.initProductSearch();
		}
	};

	$(document).ready(function() {
		W2FPCAdmin.init();
	});

})(jQuery);

