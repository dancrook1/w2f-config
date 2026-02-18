/**
 * PC Configurator Frontend JavaScript
 *
 * @package  W2F_PC_Configurator
 * @since    1.0.0
 */

(function($) {
	'use strict';

	var W2FPCConfigurator = {
		productId: 0,
		defaultConfiguration: {},
		defaultPrice: 0,
		currentConfiguration: {},
		currentQuantities: {},
		isDefaultConfig: true,
		domCache: {},
		updateTimeout: null,

		init: function() {
			var self = this;
			
			if (typeof w2f_pc_params === 'undefined') {
				return;
			}

			this.productId = w2f_pc_params.product_id;
			this.defaultConfiguration = w2f_pc_params.default_configuration || {};
			this.defaultPrice = parseFloat(w2f_pc_params.default_price) || 0;
			this.currentConfiguration = $.extend({}, this.defaultConfiguration);
			this.currentQuantities = {};
			
			// Ensure tab content divs have IDs and link properly to prevent tabs.js errors
			// This runs before tabs.js initializes to ensure proper structure
			$('.w2f-pc-tabs[data-w2f-pc-tabs="true"]').each(function() {
				var $tabList = $(this);
				$tabList.find('a').each(function() {
					var $tab = $(this);
					var href = $tab.attr('href');
					if (href && href.indexOf('#') === 0) {
						var targetId = href.replace('#', '');
						var $targetContent = $('#' + targetId);
						if ($targetContent.length && !$targetContent.attr('id')) {
							$targetContent.attr('id', targetId);
						}
					}
				});
			});
			
			// Initialize quantities from form inputs.
			$('.w2f-pc-quantity-input').each(function() {
				var $input = $(this);
				var componentId = $input.data('component-id');
				var quantity = parseInt($input.val()) || 1;
				self.currentQuantities[componentId] = quantity;
			});

			// Initialize DOM cache.
			this.initializeDomCache();

			this.bindEvents();
			this.initializeConfiguration();
			this.filterProductsByRules();
			this.initializeSelect2();
			// Initialize thumbnails after Select2 to ensure Select2 doesn't interfere
			this.initializeThumbnailPagination();
			this.checkCompatibility();
			this.calculatePrice();
			this.updateSpecs();
			this.updateSaveLoadButtons();
			
			// Check for saved configuration in URL.
			this.loadConfigurationFromURL();
			
			// Handle window resize for pagination.
			var resizeTimer;
			$(window).on('resize', function() {
				clearTimeout(resizeTimer);
				resizeTimer = setTimeout(function() {
					self.initializeThumbnailPagination();
				}, 250);
			});
		},

		initializeDomCache: function() {
			this.domCache = {
				priceElement: $('.w2f-pc-total-price'),
				specList: $('.w2f-pc-spec-list'),
				components: {}
			};

			// Cache component jQuery objects.
			var self = this;
			$('.w2f-pc-component').each(function() {
				var $component = $(this);
				var componentId = $component.data('component-id');
				if (componentId) {
					self.domCache.components[componentId] = $component;
				}
			});
		},

		debounce: function(func, wait) {
			var self = this;
			return function() {
				var context = this;
				var args = arguments;
				clearTimeout(self.updateTimeout);
				self.updateTimeout = setTimeout(function() {
					func.apply(context, args);
				}, wait);
			};
		},

		/** Initialize SelectWoo/Select2 on configurator dropdown selects. */
		initializeSelect2: function() {
			var self = this;
			var formatPrice = this.formatPrice.bind(this);
			var initSelect = function($select) {
				if ($select.hasClass('select2-hidden-accessible') || $select.hasClass('selectWoo-hidden-accessible')) {
					return;
				}
				// Use body as dropdownParent to avoid positioning issues with modal overflow
				var opts = {
					width: '100%',
					minimumResultsForSearch: Infinity,
					dropdownParent: $(document.body),
					dropdownAutoWidth: false,
					templateResult: function(state) {
						if (!state.id) {
							return state.text || '';
						}
						var $opt = state.element ? $(state.element) : null;
						if (!$opt || !$opt.length) {
							return state.text || '';
						}
						var name = $opt.data('product-name') || $opt.text() || state.text || '';
						var relPrice = $opt.data('relative-price');
						var imgUrl = $opt.data('image-url');
						var priceStr = '';
						if (relPrice !== undefined && relPrice !== '' && !isNaN(parseFloat(relPrice))) {
							var p = parseFloat(relPrice);
							if (p > 0) {
								priceStr = ' +' + formatPrice(p);
							} else if (p < 0) {
								priceStr = ' -' + formatPrice(Math.abs(p));
							} else {
								priceStr = ' —';
							}
						} else {
							priceStr = ' —';
						}
						var $out = $('<span class="w2f-pc-select2-result"></span>');
						if (imgUrl) {
							$out.append($('<img class="w2f-pc-select2-image" src="' + imgUrl + '" alt="" />'));
						}
						$out.append($('<span class="w2f-pc-select2-text">').text(name));
						$out.append($('<span class="w2f-pc-select2-price">').text(priceStr));
						return $out;
					},
					templateSelection: function(state) {
						if (!state.id) {
							return state.text || '';
						}
						var $opt = state.element ? $(state.element) : null;
						if (!$opt || !$opt.length) {
							return state.text || '';
						}
						var name = $opt.data('product-name') || state.text;
						var relPrice = $opt.data('relative-price');
						if (relPrice !== undefined && relPrice !== '' && !isNaN(parseFloat(relPrice))) {
							var p = parseFloat(relPrice);
							if (p > 0) {
								name += ' — +' + formatPrice(p);
							} else if (p < 0) {
								name += ' — -' + formatPrice(Math.abs(p));
							}
						}
						return name;
					}
				};
				if (typeof $.fn.selectWoo !== 'undefined') {
					$select.selectWoo(opts);
					// Ensure dropdown width matches select element (not wider than parent)
					$select.on('select2:open selectWoo:open', function() {
						var $selectEl = $(this);
						var selectWidth = $selectEl.outerWidth() || $selectEl.width();
						setTimeout(function() {
							var $dropdown = $('.select2-dropdown');
							if ($dropdown.length && selectWidth) {
								$dropdown.css({
									'width': selectWidth + 'px',
									'max-width': selectWidth + 'px',
									'min-width': 'auto'
								});
							}
						}, 0);
					});
				} else if (typeof $.fn.select2 !== 'undefined') {
					$select.select2(opts);
					// Ensure dropdown width matches select element (not wider than parent)
					$select.on('select2:open', function() {
						var $selectEl = $(this);
						var selectWidth = $selectEl.outerWidth() || $selectEl.width();
						setTimeout(function() {
							var $dropdown = $('.select2-dropdown');
							if ($dropdown.length && selectWidth) {
								$dropdown.css({
									'width': selectWidth + 'px',
									'max-width': selectWidth + 'px',
									'min-width': 'auto'
								});
							}
						}, 0);
					});
				}
			};
			// Only initialize Select2 on dropdown-mode components (not thumbnail components)
			// Target only selects within dropdown components that are visible
			// Be very specific to avoid affecting thumbnail components
			$('.w2f-pc-component-dropdown').each(function() {
				var $component = $(this);
				// Skip if this is actually a thumbnail component (extra safety)
				if ($component.hasClass('w2f-pc-component-thumbnail')) {
					return;
				}
				// Find Select2 dropdowns within this dropdown component only
				var $sel = $component.find('.w2f-pc-select2-dropdown');
				if (!$sel.length) {
					return;
				}
				$sel.each(function() {
					var $select = $(this);
					// Skip if already initialized
					if ($select.hasClass('select2-hidden-accessible') || $select.hasClass('selectWoo-hidden-accessible')) {
						return;
					}
					// Skip if inside thumbnail mobile dropdown (shouldn't happen, but safety check)
					if ($select.closest('.w2f-pc-thumbnail-mobile-dropdown').length) {
						return;
					}
					// Skip if inside thumbnail component (shouldn't happen, but safety check)
					if ($select.closest('.w2f-pc-component-thumbnail').length) {
						return;
					}
					// Only initialize if element is visible (in active tab)
					if ($select.is(':visible') && $select.closest('.w2f-pc-tab-content.active, .w2f-pc-modal-overlay').length) {
						initSelect($select);
					}
				});
			});
		},

		destroySelect2: function() {
			$('.w2f-pc-select2-dropdown').each(function() {
				var $sel = $(this);
				if (typeof $.fn.selectWoo !== 'undefined' && $sel.hasClass('selectWoo-hidden-accessible')) {
					$sel.selectWoo('destroy');
				} else if (typeof $.fn.select2 !== 'undefined' && $sel.hasClass('select2-hidden-accessible')) {
					$sel.select2('destroy');
				}
			});
		},

		bindEvents: function() {
			var self = this;

			// Open configurator modal.
			$(document).on('click', '.w2f-pc-configure-button', function(e) {
				e.preventDefault();
				$('.w2f-pc-modal-overlay').addClass('active');
				$('body').css('overflow', 'hidden');
				// Re-init Select2 when modal opens (in case it was destroyed on close).
				self.initializeSelect2();
				// Re-init thumbnails when modal opens to ensure they display correctly
				setTimeout(function() {
					self.initializeThumbnailPagination();
				}, 100);
				// Set ARIA tab roles after open so theme tabs.js (which ran at DOMContentLoaded) does not pick these up.
				// Also ensure tabs have proper IDs and aria-controls to prevent tabs.js errors
				$('.w2f-pc-tabs').attr('role', 'tablist');
				$('.w2f-pc-tabs a').each(function() {
					var $a = $(this);
					var targetId = $a.attr('href');
					var $targetContent = $(targetId);
					
					// Set role and aria attributes
					$a.attr('role', 'tab');
					$a.attr('aria-selected', $a.closest('li').hasClass('active') ? 'true' : 'false');
					
					// Set aria-controls only if target content exists
					if ($targetContent.length) {
						$a.attr('aria-controls', targetId.replace('#', ''));
						$targetContent.attr('role', 'tabpanel');
						$targetContent.attr('id', targetId.replace('#', ''));
					}
				});
			});

			// Close configurator modal.
			$(document).on('click', '.w2f-pc-modal-close, .w2f-pc-modal-overlay', function(e) {
				// Only close if clicking overlay or close button, not modal content.
				if ($(e.target).hasClass('w2f-pc-modal-overlay') || $(e.target).hasClass('w2f-pc-modal-close')) {
					e.preventDefault();
					self.closeModal();
				}
			});

			// Prevent modal from closing when clicking inside.
			$(document).on('click', '.w2f-pc-modal-content', function(e) {
				e.stopPropagation();
			});

			// Close modal on Escape key.
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape' && $('.w2f-pc-modal-overlay').hasClass('active')) {
					self.closeModal();
				}
			});

			// Add default configuration to cart.
			$(document).on('click', '.w2f-pc-add-default-to-cart', function(e) {
				e.preventDefault();
				self.addDefaultToCart();
			});

			// Tab switching with animation.
			$(document).on('click', '.w2f-pc-tabs a', function(e) {
				e.preventDefault();
				var $tab = $(this);
				var targetId = $tab.attr('href');
				var $targetContent = $(targetId);
				
				// Don't switch if already active.
				if ($tab.closest('li').hasClass('active')) {
					return;
				}
				
				// Update active tab immediately.
				$('.w2f-pc-tabs li').removeClass('active');
				$tab.closest('li').addClass('active');
				$tab.attr('aria-selected', 'true');
				$('.w2f-pc-tabs a').not($tab).attr('aria-selected', 'false');
				
				// Animate tab content out and in simultaneously.
				var $currentContent = $('.w2f-pc-tab-content.active');
				if ($currentContent.length && !$currentContent.is($targetContent)) {
					// Position old content absolutely so it doesn't take up space
					$currentContent.css({
						'position': 'absolute',
						'opacity': '0',
						'transform': 'translateY(-8px)',
						'pointer-events': 'none'
					});
					
					// Show new content immediately but keep it invisible
					$targetContent.addClass('active').css({
						'opacity': '0',
						'transform': 'translateY(8px)',
						'display': 'block'
					});
					// Re-initialize thumbnails in the new tab to ensure they display
					setTimeout(function() {
						self.initializeThumbnailPagination();
					}, 50);
					
					// Remove old content class and animate new content in
					setTimeout(function() {
						$currentContent.removeClass('active').css({
							'display': 'none',
							'position': '',
							'pointer-events': ''
						});
						
						// Animate new content in.
						setTimeout(function() {
							$targetContent.css({
								'opacity': '1',
								'transform': 'translateY(0)'
							});
						}, 10);
					}, 150);
				} else {
					// First load - no animation needed.
					$('.w2f-pc-tab-content').removeClass('active');
					$targetContent.addClass('active');
				}
			});

			// Component selection change (dropdown/Select2).
			$(document).on('change', '.component-select', function() {
				var $select = $(this);
				var componentId = $select.data('component-id');
				var productId = $select.val() ? parseInt($select.val()) : 0;
				
				// Sync thumbnail selection if this is from mobile dropdown
				// Only sync if the value actually changed to prevent circular loops
				if ($select.closest('.w2f-pc-thumbnail-mobile-dropdown').length) {
					var currentValue = $select.data('last-synced-value');
					if (currentValue !== productId) {
						$select.data('last-synced-value', productId);
						self.syncThumbnailFromMobileDropdown(componentId, productId);
					}
				}

				// Handle "None" option (value 0) for optional components.
				if (productId === 0 || productId === '0') {
					delete self.currentConfiguration[componentId];
				} else if (productId) {
					self.currentConfiguration[componentId] = productId;
				} else {
					delete self.currentConfiguration[componentId];
				}

				// Debounce updates to prevent excessive AJAX calls.
				clearTimeout(self.updateTimeout);
				self.updateTimeout = setTimeout(function() {
					self.updateConfiguration(componentId);
				}, 150);
			});
			
			// Quantity input change (for non-thumbnail quantity inputs).
			$(document).on('change input', '.w2f-pc-quantity-input', function() {
				var $input = $(this);
				var componentId = $input.data('component-id');
				var minQuantity = parseInt($input.data('min-quantity')) || 1;
				var maxQuantity = parseInt($input.data('max-quantity')) || 99;
				var quantity = parseInt($input.val()) || minQuantity;
				
				// Validate min/max.
				if (quantity < minQuantity) {
					quantity = minQuantity;
					$input.val(quantity);
				} else if (quantity > maxQuantity) {
					quantity = maxQuantity;
					$input.val(quantity);
				}
				
				self.currentQuantities[componentId] = quantity;
				
				// Only update if a product is selected for this component.
				if (self.currentConfiguration[componentId]) {
					// Debounce updates to prevent excessive AJAX calls.
					clearTimeout(self.updateTimeout);
					self.updateTimeout = setTimeout(function() {
						self.updateConfiguration(componentId);
					}, 150);
				}
			});

			// Thumbnail quantity button handlers.
			$(document).on('click', '.w2f-pc-qty-btn', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				var $button = $(this);
				var $wrapper = $button.closest('.w2f-pc-thumbnail-quantity');
				var $input = $wrapper.find('.w2f-pc-qty-input');
				
				// Don't proceed if input is disabled.
				if ($input.prop('disabled')) {
					return false;
				}
				
				var minQuantity = parseInt($input.attr('min')) || 1;
				var maxQuantity = parseInt($input.attr('max')) || 99;
				var currentQuantity = parseInt($input.val()) || minQuantity;
				var newQuantity = currentQuantity;
				
				if ($button.hasClass('w2f-pc-qty-plus')) {
					newQuantity = Math.min(currentQuantity + 1, maxQuantity);
				} else if ($button.hasClass('w2f-pc-qty-minus')) {
					newQuantity = Math.max(currentQuantity - 1, minQuantity);
				}
				
				if (newQuantity !== currentQuantity) {
					$input.val(newQuantity);
					self.updateQuantityButtonStates($wrapper, newQuantity, minQuantity, maxQuantity);
					
					// Update currentQuantities.
					var componentId = $input.data('component-id');
					self.currentQuantities[componentId] = newQuantity;
					
					// Trigger update.
					clearTimeout(self.updateTimeout);
					self.updateTimeout = setTimeout(function() {
						self.updateConfiguration(componentId);
					}, 150);
				}
				
				return false;
			});
			
			// Quantity input change handler.
			$(document).on('change input', '.w2f-pc-qty-input', function(e) {
				e.stopPropagation();
				
				var $input = $(this);
				var $wrapper = $input.closest('.w2f-pc-thumbnail-quantity');
				var minQuantity = parseInt($input.attr('min')) || 1;
				var maxQuantity = parseInt($input.attr('max')) || 99;
				var quantity = parseInt($input.val()) || minQuantity;
				
				// Validate.
				if (quantity < minQuantity) {
					quantity = minQuantity;
					$input.val(quantity);
				} else if (quantity > maxQuantity) {
					quantity = maxQuantity;
					$input.val(quantity);
				}
				
				self.updateQuantityButtonStates($wrapper, quantity, minQuantity, maxQuantity);
				
				// Update currentQuantities.
				var componentId = $input.data('component-id');
				var productId = parseInt($input.data('product-id'));
				
				// Only update if this product is selected.
				if (self.currentConfiguration[componentId] === productId) {
					self.currentQuantities[componentId] = quantity;
					
					// Trigger update.
					clearTimeout(self.updateTimeout);
					self.updateTimeout = setTimeout(function() {
						self.updateConfiguration(componentId);
					}, 150);
				}
			});
			
			// Prevent card selection when clicking on quantity controls.
			$(document).on('click', '.w2f-pc-thumbnail-quantity', function(e) {
				e.stopPropagation();
			});

			// Thumbnail card click handler - select when clicking anywhere except quantity controls.
			$(document).on('click', '.w2f-pc-thumbnail-card', function(e) {
				// Ignore clicks on quantity controls.
				if ($(e.target).closest('.w2f-pc-thumbnail-quantity').length) {
					return;
				}
				// Ignore clicks on quick view button.
				if ($(e.target).closest('.w2f-pc-quick-view').length) {
					return;
				}
				
				var $card = $(this);
				var $radio = $card.find('.component-select-radio');
				
				// Set radio button and trigger change.
				$radio.prop('checked', true).trigger('change');
			});
			
			// Handle keyboard navigation for thumbnail cards.
			$(document).on('keydown', '.w2f-pc-thumbnail-card', function(e) {
				if (e.key === 'Enter' || e.key === ' ') {
					// Ignore if focus is on quantity input.
					if ($(e.target).is('.w2f-pc-qty-input')) {
						return;
					}
					e.preventDefault();
					$(this).find('.component-select-radio').prop('checked', true).trigger('change');
				}
			});

			// Component selection change (thumbnail radio).
			$(document).on('change', '.component-select-radio', function() {
				var $radio = $(this);
				var componentId = $radio.data('component-id');
				var productId = $radio.val() ? parseInt($radio.val()) : 0;
				var $card = $radio.closest('.w2f-pc-thumbnail-card');
				
				// Handle "None" option (value 0).
				if (productId === 0 || productId === '0') {
					delete self.currentConfiguration[componentId];
				} else if (productId) {
					self.currentConfiguration[componentId] = productId;
				} else {
					delete self.currentConfiguration[componentId];
				}
				
				// Sync mobile dropdown selection (only if not syncing from dropdown to prevent circular loop)
				if (!$radio.data('syncing-from-dropdown')) {
					self.syncMobileDropdownFromThumbnail(componentId, productId);
				}
				
				// Ensure we have the correct component - use data attribute to be precise.
				var $component = $('.w2f-pc-component[data-component-id="' + componentId + '"]');
				if (!$component.length) {
					$component = self.domCache.components[componentId];
				}
				
				if (!$component || !$component.length) {
					return;
				}

				// Update visual selection - remove selected class and hide all indicators immediately.
				// Clear all selections in this specific component first.
				$component.find('.w2f-pc-thumbnail-card').removeClass('selected');
				$component.find('.selected-indicator').remove();
				
				// Only proceed if this radio is actually checked.
				if (!$radio.is(':checked')) {
					return;
				}
				
				// Add selected class to new card and add indicator with animation.
				$card.css('transform', 'scale(0.95)');
				setTimeout(function() {
					// Double-check this is still the selected option.
					if ($radio.is(':checked') && $card.closest('.w2f-pc-component[data-component-id="' + componentId + '"]').length) {
						$card.addClass('selected').css('transform', 'scale(1)');
						// Remove any existing indicator first.
						$card.find('.selected-indicator').remove();
						var $indicator = $('<span class="selected-indicator">✓</span>').css('opacity', '0');
						$card.find('.thumbnail-image').append($indicator);
						setTimeout(function() {
							$indicator.css('opacity', '1');
						}, 10);
					}
				}, 150);

				if (productId) {
					self.currentConfiguration[componentId] = productId;
				} else {
					delete self.currentConfiguration[componentId];
				}

				// Enable/disable quantity inputs based on selection and sync quantity.
				self.updateThumbnailQuantityInputs(componentId, productId);
				
				// Sync quantity value from input to currentQuantities if product is selected.
				if (productId) {
					var $selectedInput = $component.find('.w2f-pc-qty-input[data-product-id="' + productId + '"]');
					if ($selectedInput.length) {
						var quantity = parseInt($selectedInput.val()) || 1;
						self.currentQuantities[componentId] = quantity;
					}
				}

				// Update specs immediately when thumbnail selection changes
				self.updateSpecs();

				// Debounce updates to prevent excessive AJAX calls.
				clearTimeout(self.updateTimeout);
				self.updateTimeout = setTimeout(function() {
					self.updateConfiguration(componentId);
				}, 150);
			});

			// Share button.
			$(document).on('click', '.w2f-pc-share', function(e) {
				e.preventDefault();
				self.shareConfiguration();
			});

			// Load configuration.
			$(document).on('click', '.w2f-pc-load-config', function(e) {
				e.preventDefault();
				self.loadConfiguration();
			});

			// Reset to default.
			$(document).on('click', '.w2f-pc-reset-config', function(e) {
				e.preventDefault();
				self.resetToDefault();
			});

			// Add to cart form submission.
			$('form.cart').on('submit', function(e) {
				var $form = $(this);
				var $addToCartButton = $form.find('.single_add_to_cart_button');
				
				// Prevent submission if button is disabled (compatibility errors).
				if ($addToCartButton.prop('disabled')) {
					e.preventDefault();
					return false;
				}
				
				// Validate configuration (including warranty).
				var validationErrors = self.validateConfiguration();
				if (validationErrors.length > 0) {
					e.preventDefault();
					var errorMessages = validationErrors.map(function(e) { return e.message; }).join('\n');
					alert(errorMessages);
					return false;
				}
				
				self.addConfigurationToForm();
			});

			// Quick view modal.
			$(document).on('click', '.w2f-pc-quick-view', function(e) {
				e.preventDefault();
				e.stopPropagation();
				var productId = $(this).data('product-id');
				self.showProductDescriptionModal(productId);
			});

			// Thumbnail pagination.
			$(document).on('click', '.w2f-pc-pagination-prev', function(e) {
				e.preventDefault();
				var $wrapper = $(this).closest('.w2f-pc-thumbnail-wrapper');
				var currentPage = $wrapper.data('current-page') || 1;
				if (currentPage > 1) {
					$wrapper.data('current-page', currentPage - 1);
					self.updateThumbnailPage($wrapper);
				}
			});

			$(document).on('click', '.w2f-pc-pagination-next', function(e) {
				e.preventDefault();
				var $wrapper = $(this).closest('.w2f-pc-thumbnail-wrapper');
				var currentPage = $wrapper.data('current-page') || 1;
				var totalPages = $wrapper.data('total-pages') || 1;
				if (currentPage < totalPages) {
					$wrapper.data('current-page', currentPage + 1);
					self.updateThumbnailPage($wrapper);
				}
			});

			// Component search with debouncing.
			var searchTimeout;
			$(document).on('input', '.w2f-pc-search-input', function() {
				var $input = $(this);
				clearTimeout(searchTimeout);
				searchTimeout = setTimeout(function() {
					var searchTerm = $input.val().toLowerCase();
					var componentId = $input.data('component-id');
					var $component = self.domCache.components[componentId] || $('.w2f-pc-component[data-component-id="' + componentId + '"]');
					
					if (searchTerm === '') {
						$component.find('.w2f-pc-thumbnail-card').removeClass('hidden');
						$component.find('.component-options select option').show();
						$component.find('.w2f-pc-thumbnail-wrapper').each(function() {
							self.updateThumbnailPage($(this));
						});
					} else {
						// Filter thumbnails (template uses .w2f-pc-thumbnail-card with .thumbnail-name).
						$component.find('.w2f-pc-thumbnail-card').each(function() {
							var $card = $(this);
							var productName = $card.find('.thumbnail-name').text().toLowerCase();
							if (productName.indexOf(searchTerm) !== -1) {
								$card.removeClass('hidden');
							} else {
								$card.addClass('hidden');
							}
						});
						
						// Filter select dropdown options.
					$component.find('.component-options select option').each(function() {
						var $option = $(this);
						var optionText = $option.text().toLowerCase();
						if (optionText.indexOf(searchTerm) !== -1 || $option.val() === '') {
							$option.show();
						} else {
							$option.hide();
						}
					});
						// Reset thumbnail pagination to first page of filtered results.
						$component.find('.w2f-pc-thumbnail-wrapper').each(function() {
							var $wrap = $(this);
							$wrap.data('current-page', 1);
							self.updateThumbnailPage($wrap);
						});
					}
				}, 300);
			});

			// Summary section toggles.
			$(document).on('click', '.w2f-pc-summary-toggle', function() {
				var $toggle = $(this);
				var $content = $toggle.next('.w2f-pc-summary-content');
				var isExpanded = $toggle.attr('aria-expanded') === 'true';
				
				$toggle.attr('aria-expanded', !isExpanded);
				
				if (isExpanded) {
					$content.removeClass('w2f-pc-summary-content-expanded');
				} else {
					$content.addClass('w2f-pc-summary-content-expanded');
				}
			});
		},

		/**
		 * Sync mobile dropdown selection from thumbnail card selection.
		 */
		syncMobileDropdownFromThumbnail: function(componentId, productId) {
			var $mobileDropdown = $('.w2f-pc-thumbnail-mobile-dropdown[data-component-id="' + componentId + '"]');
			if (!$mobileDropdown.length) {
				return;
			}
			
			var $select = $mobileDropdown.find('select.component-select');
			if ($select.length) {
				// Check if the option exists before setting value
				var $option = $select.find('option[value="' + productId + '"]');
				if ($option.length) {
					// Set value without triggering change to prevent circular sync loop
					// The value is already set by the thumbnail selection, so we just update the select visually
					$select.val(productId);
					// Update the last-synced-value to prevent the change handler from syncing back
					$select.data('last-synced-value', productId);
					// Don't trigger change - this prevents infinite loop with syncThumbnailFromMobileDropdown
				} else if (productId === 0 || productId === '0') {
					// Handle "None" option
					var $noneOption = $select.find('option[value="0"]');
					if ($noneOption.length) {
						$select.val('0');
						$select.data('last-synced-value', '0');
						// Don't trigger change - this prevents infinite loop
					}
				}
			}
		},

		/**
		 * Sync thumbnail card selection from mobile dropdown selection.
		 */
		syncThumbnailFromMobileDropdown: function(componentId, productId) {
			var $thumbnailWrapper = $('.w2f-pc-thumbnail-wrapper[data-component-id="' + componentId + '"]');
			if (!$thumbnailWrapper.length) {
				return;
			}
			
			var $radio = $thumbnailWrapper.find('.component-select-radio[value="' + productId + '"]');
			if ($radio.length && !$radio.is(':checked')) {
				// Set a flag to prevent circular sync
				$radio.data('syncing-from-dropdown', true);
				$radio.prop('checked', true).trigger('change');
				$radio.removeData('syncing-from-dropdown');
			}
		},

		/**
		 * Get items per page based on screen size. Show 8 items per page for case/thumbnail grids.
		 */
		getItemsPerPage: function($wrapper) {
			var width = $(window).width();
			if (width <= 480) {
				return 4;
			}
			return 8;
		},

		/**
		 * Initialize pagination for thumbnail grids.
		 */
		initializeThumbnailPagination: function() {
			var self = this;
			// Find all thumbnail wrappers, including those in inactive tabs
			$('.w2f-pc-thumbnail-wrapper').each(function() {
				var $wrapper = $(this);
				var componentId = $wrapper.data('component-id');
				var $grid = $wrapper.find('.w2f-pc-thumbnail-grid');
				var $cards = $grid.find('.w2f-pc-thumbnail-card');
				
				// Skip if no cards found
				if (!$cards.length) {
					return;
				}
				
				var $pagination = $wrapper.find('.w2f-pc-thumbnail-pagination');
				var $prevBtn = $pagination.find('.w2f-pc-pagination-prev');
				var $nextBtn = $pagination.find('.w2f-pc-pagination-next');
				var $currentSpan = $pagination.find('.w2f-pc-pagination-current');
				var $totalSpan = $pagination.find('.w2f-pc-pagination-total');
				
				// Filter out hidden cards (from search/filtering)
				var $visibleCards = $cards.not('.hidden');
				var totalItems = $visibleCards.length;
				var itemsPerPage = self.getItemsPerPage($wrapper);
				var totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
				
				// Find selected item to determine initial page.
				var $selected = $grid.find('.w2f-pc-thumbnail-card.selected');
				var initialPage = 1;
				if ($selected.length && !$selected.hasClass('hidden')) {
					var selectedIndex = $visibleCards.index($selected);
					if (selectedIndex >= 0) {
						initialPage = Math.floor(selectedIndex / itemsPerPage) + 1;
					}
				}
				
				// Store pagination state.
				$wrapper.data('current-page', initialPage);
				$wrapper.data('total-pages', totalPages);
				$wrapper.data('items-per-page', itemsPerPage);
				
				// Show pagination if more than one page.
				if (totalPages > 1) {
					$pagination.show();
					self.updateThumbnailPage($wrapper);
				} else {
					$pagination.hide();
					// Show all visible items if only one page.
					$visibleCards.addClass('w2f-pc-thumbnail-visible');
				}
			});
		},

		/**
		 * Update visible thumbnails for a specific component.
		 */
		updateThumbnailPage: function($wrapper) {
			var self = this;
			var currentPage = $wrapper.data('current-page') || 1;
			var totalPages = $wrapper.data('total-pages') || 1;
			// Recalculate items per page in case screen size changed (defaults to 8)
			var itemsPerPage = $wrapper.data('items-per-page') || self.getItemsPerPage($wrapper);
			var $grid = $wrapper.find('.w2f-pc-thumbnail-grid');
			var $cards = $grid.find('.w2f-pc-thumbnail-card');
			var $visibleCards = $cards.not('.hidden');
			var $pagination = $wrapper.find('.w2f-pc-thumbnail-pagination');
			var $prevBtn = $pagination.find('.w2f-pc-pagination-prev');
			var $nextBtn = $pagination.find('.w2f-pc-pagination-next');
			var $currentSpan = $pagination.find('.w2f-pc-pagination-current');
			var $totalSpan = $pagination.find('.w2f-pc-pagination-total');
			
			// Hide all cards from pagination visibility.
			$cards.removeClass('w2f-pc-thumbnail-visible');
			
			// Paginate only over non-hidden cards (so search + pagination work together).
			var visibleTotal = $visibleCards.length;
			var totalPagesForVisible = Math.ceil(visibleTotal / itemsPerPage) || 1;
			currentPage = Math.min(currentPage, totalPagesForVisible);
			$wrapper.data('current-page', currentPage);
			totalPages = totalPagesForVisible;
			$wrapper.data('total-pages', totalPages);
			
			var startIndex = (currentPage - 1) * itemsPerPage;
			var endIndex = startIndex + itemsPerPage;
			$visibleCards.slice(startIndex, endIndex).addClass('w2f-pc-thumbnail-visible');
			
			// Update pagination info.
			$currentSpan.text(currentPage);
			$totalSpan.text(totalPages);
			
			// Update button states.
			$prevBtn.prop('disabled', currentPage === 1);
			$nextBtn.prop('disabled', currentPage === totalPages);
		},

		/**
		 * Ensure selected thumbnail is visible by navigating to its page.
		 */
		ensureSelectedThumbnailVisible: function(componentId) {
			var $wrapper = $('.w2f-pc-thumbnail-wrapper[data-component-id="' + componentId + '"]');
			if (!$wrapper.length) {
				return;
			}

			var $grid = $wrapper.find('.w2f-pc-thumbnail-grid');
			var $options = $grid.find('.w2f-pc-thumbnail-card');
			var $selected = $grid.find('.w2f-pc-thumbnail-card.selected');
			
			if (!$selected.length) {
				return;
			}

			var selectedIndex = $options.index($selected);
			var itemsPerPage = this.getItemsPerPage($wrapper);
			var targetPage = Math.floor(selectedIndex / itemsPerPage) + 1;
			var currentPage = $wrapper.data('current-page') || 1;

			if (targetPage !== currentPage) {
				$wrapper.data('current-page', targetPage);
				this.updateThumbnailPage($wrapper);
				
				// Scroll to top of grid for better UX.
				$('html, body').animate({
					scrollTop: $wrapper.offset().top - 100
				}, 300);
			}
		},

		initializeConfiguration: function() {
			// Set default selections.
			var self = this;
			$.each(this.defaultConfiguration, function(componentId, productId) {
				// Handle dropdown selects (including Select2).
				var $standardSelect = $('.component-select[data-component-id="' + componentId + '"]');
				if ($standardSelect.is('select')) {
					$standardSelect.val(productId).trigger('change');
				}
				
				// Handle thumbnail radio buttons.
				var $radio = $('.component-select-radio[data-component-id="' + componentId + '"][value="' + productId + '"]');
				var $component = self.domCache.components[componentId] || $('.w2f-pc-component[data-component-id="' + componentId + '"]');
				
				// Initialize thumbnail quantity inputs.
				if ($component.length) {
					self.updateThumbnailQuantityInputs(componentId, productId);
				}
				
				// First, remove selected class and indicators from all cards in this component.
				$component.find('.w2f-pc-thumbnail-card').removeClass('selected');
				$component.find('.selected-indicator').remove();
				
				// Then set the radio and add selected class to the correct card.
				if ($radio.length) {
					$radio.prop('checked', true);
					var $card = $radio.closest('.w2f-pc-thumbnail-card');
					if ($card.length) {
						$card.addClass('selected');
						// Add indicator if it doesn't exist.
						if ($card.find('.selected-indicator').length === 0) {
							$card.find('.thumbnail-image').append('<span class="selected-indicator">✓</span>');
						}
					}
					// Sync mobile dropdown selection
					self.syncMobileDropdownFromThumbnail(componentId, productId);
				}
				
				// Update relative prices for this component.
				self.updateComponentRelativePrices(componentId);
			});
			
			// Calculate initial price.
			this.calculatePrice();
			// Update specs after initialization to show default selections
			this.updateSpecs();
		},

		updateConfiguration: function(componentId) {
			var self = this;
			self.updateComponentRelativePrices(componentId);
			self.checkIfDefault();
			self.filterProductsByRules();
			
			// Execute compatibility check and price calculation in parallel using jQuery promises.
			var compatibilityPromise = $.Deferred();
			var pricePromise = $.Deferred();
			
			// Wrap checkCompatibility in a promise.
			var $messages = self.domCache.compatibilityMessages || $('.w2f-pc-compatibility-messages');
			if (!$messages.length) {
				$messages = $('.w2f-pc-compatibility-messages');
				self.domCache.compatibilityMessages = $messages;
			}
			$messages.html('<div class="w2f-pc-loading"><span class="w2f-pc-spinner"></span> ' + (w2f_pc_params.i18n.loading || 'Checking compatibility...') + '</div>');
			
			$.ajax({
				url: w2f_pc_params.ajax_url,
				type: 'POST',
				data: {
					action: 'w2f_pc_check_compatibility',
					nonce: w2f_pc_params.nonce,
					product_id: self.productId,
					configuration: self.currentConfiguration,
					quantities: self.currentQuantities
				},
				success: function(response) {
					if (response.success) {
						setTimeout(function() {
							self.displayCompatibilityMessages(response.data);
							compatibilityPromise.resolve();
						}, 100);
					} else {
						compatibilityPromise.resolve();
					}
				},
				error: function() {
					$messages.html('');
					compatibilityPromise.resolve();
				}
			});
			
			// Wrap calculatePrice in a promise.
			var $priceElement = self.domCache.priceElement.length ? self.domCache.priceElement : $('.w2f-pc-total-price');
			$priceElement.css({ 'opacity': '0.5' });
			
			$.ajax({
				url: w2f_pc_params.ajax_url,
				type: 'POST',
				data: {
					action: 'w2f_pc_calculate_price',
					nonce: w2f_pc_params.nonce,
					product_id: self.productId,
					configuration: self.currentConfiguration,
					quantities: self.currentQuantities
				},
				success: function(response) {
					if (response.success && response.data) {
						var totalPrice = parseFloat(response.data.price) || 0;
						$priceElement.css({
							'opacity': '1',
							'transform': 'scale(1.1)',
							'color': 'var(--w2f-pc-color-accent)'
						});
						setTimeout(function() {
							var priceHtml = response.data.price_html || self.formatPrice(totalPrice);
							$priceElement.html(priceHtml).css({
								'transform': 'scale(1)',
								'color': ''
							});
							// Sync header total price
							$('.w2f-pc-header-total-price').html(priceHtml);
							pricePromise.resolve();
						}, 100);
					} else {
						self.calculatePriceFallback();
						pricePromise.resolve();
					}
				},
				error: function() {
					self.calculatePriceFallback();
					pricePromise.resolve();
				}
			});
			
			// Update specs after both complete.
			$.when(compatibilityPromise, pricePromise).done(function() {
				self.updateSpecs();
			});
		},

		checkIfDefault: function() {
			var isDefault = true;
			var defaultConfig = this.defaultConfiguration;

			// Check if all default components are selected and match.
			$.each(defaultConfig, function(componentId, productId) {
				if (!this.currentConfiguration[componentId] || 
					parseInt(this.currentConfiguration[componentId]) !== parseInt(productId)) {
					isDefault = false;
					return false;
				}
			}.bind(this));

			// Check if any extra components are selected.
			$.each(this.currentConfiguration, function(componentId, productId) {
				if (!defaultConfig[componentId] || 
					parseInt(defaultConfig[componentId]) !== parseInt(productId)) {
					isDefault = false;
					return false;
				}
			}.bind(this));

			this.isDefaultConfig = isDefault;
		},

		checkCompatibility: function() {
			var self = this;
			var $messages = self.domCache.compatibilityMessages || $('.w2f-pc-compatibility-messages');
			if (!$messages.length) {
				$messages = $('.w2f-pc-compatibility-messages');
				self.domCache.compatibilityMessages = $messages;
			}
			
			// Show loading state.
			$messages.html('<div class="w2f-pc-loading"><span class="w2f-pc-spinner"></span> ' + (w2f_pc_params.i18n.loading || 'Checking compatibility...') + '</div>');

			$.ajax({
				url: w2f_pc_params.ajax_url,
				type: 'POST',
				data: {
					action: 'w2f_pc_check_compatibility',
					nonce: w2f_pc_params.nonce,
					product_id: this.productId,
					configuration: this.currentConfiguration
				},
				success: function(response) {
					if (response.success) {
						setTimeout(function() {
							self.displayCompatibilityMessages(response.data);
						}, 200);
					}
				},
				error: function() {
					$messages.html('');
				}
			});
		},

		displayCompatibilityMessages: function(data) {
			var $messages = $('.w2f-pc-compatibility-messages');
			var $addToCartButton = $('.single_add_to_cart_button');
			var $summaryWarnings = $('.w2f-pc-summary-warnings');
			
			$messages.empty();
			
			// Clear summary warnings.
			if ($summaryWarnings.length) {
				$summaryWarnings.remove();
			}

			if (!data.valid) {
				$.each(data.errors, function(index, error) {
					$messages.append('<div class="w2f-pc-error">' + error + '</div>');
				});
				// Disable add to cart button when there are errors.
				$addToCartButton.prop('disabled', true).addClass('disabled');
			} else {
				// Enable add to cart button when there are no errors.
				$addToCartButton.prop('disabled', false).removeClass('disabled');
			}

			if (data.warnings && data.warnings.length > 0) {
				// Add warnings to summary area only (not in compatibility messages area).
				var $summary = $('.w2f-pc-summary');
				if ($summary.length) {
					var $warningsSection = $('<div class="w2f-pc-summary-section w2f-pc-summary-warnings">' +
						'<div class="w2f-pc-summary-content" style="max-height: 1000px;">' +
						'<h4 style="margin: 0 0 10px 0; color: #92400e;">⚠️ ' + (w2f_pc_params.i18n.warnings || 'Warnings') + '</h4>' +
						'<ul style="margin: 0; padding-left: 20px;">');
					
					$.each(data.warnings, function(index, warning) {
						$warningsSection.find('ul').append('<li style="margin-bottom: 8px; color: #92400e;">' + warning + '</li>');
					});
					
					$warningsSection.find('.w2f-pc-summary-content').append($warningsSection.find('ul'));
					$warningsSection.append('</div></div>');
					
					// Insert before compatibility messages or at the end of summary.
					var $compatibilityMessages = $summary.find('.w2f-pc-compatibility-messages');
					if ($compatibilityMessages.length) {
						$compatibilityMessages.before($warningsSection);
					} else {
						$summary.find('.w2f-pc-price').after($warningsSection);
					}
				}
			}

			// If no errors or warnings, show success message (optional).
			if (data.valid && (!data.warnings || data.warnings.length === 0)) {
				// Optionally show a success message or leave empty.
			}
		},

		filterProductsByRules: function() {
			var self = this;
			
			// Collect all component IDs.
			var componentIds = [];
			var componentCache = {};
			
			$('.w2f-pc-component').each(function() {
				var $component = $(this);
				var componentId = $component.data('component-id');
				if (componentId) {
					componentIds.push(componentId);
					componentCache[componentId] = $component;
				}
			});

			if (componentIds.length === 0) {
				return;
			}

			// Batch all component filtering into a single AJAX request.
			$.ajax({
				url: w2f_pc_params.ajax_url,
				type: 'POST',
				data: {
					action: 'w2f_pc_get_all_filtered_products',
					nonce: w2f_pc_params.nonce,
					product_id: self.productId,
					component_ids: componentIds,
					configuration: self.currentConfiguration
				},
				success: function(response) {
					if (response.success && response.data && response.data.components) {
						// Process each component's results.
						$.each(response.data.components, function(componentId, componentData) {
							var $component = componentCache[componentId];
							if (!$component || !$component.length) {
								return;
							}

							var allowedProductIds = componentData.product_ids || [];
							var productWarnings = componentData.warnings || {};
							
							// Get all product IDs for this component (thumbnails and/or select options).
							var allProductIds = [];
							$component.find('.w2f-pc-thumbnail-option').each(function() {
								var productId = parseInt($(this).data('product-id'), 10);
								if (productId) {
									allProductIds.push(productId);
								}
							});
							$component.find('select.component-select option').each(function() {
								var productId = parseInt($(this).val(), 10);
								if (productId && allProductIds.indexOf(productId) === -1) {
									allProductIds.push(productId);
								}
							});
							
							// Determine filtering state.
							// If allowedProductIds.length === allProductIds.length, all products are allowed (no filtering).
							// If allowedProductIds.length < allProductIds.length, some products are excluded (filtering active).
							// If allowedProductIds is empty and allProductIds.length > 0, all products are excluded (filtering active).
							var isFilteringActive = false;
							if (Array.isArray(allowedProductIds) && allProductIds.length > 0) {
								// Filtering is active only if some products are excluded.
								isFilteringActive = (allowedProductIds.length < allProductIds.length);
							} else if (Array.isArray(allowedProductIds) && allowedProductIds.length === 0 && allProductIds.length > 0) {
								// Empty allowedProductIds with products available means all are excluded.
								isFilteringActive = true;
							}
							
							// Filter thumbnails - hide only products with errors, show warnings.
							$component.find('.w2f-pc-thumbnail-option').each(function() {
								var $option = $(this);
								var productId = parseInt($option.data('product-id'));
								var $warningIndicator = $option.find('.w2f-pc-warning-indicator');
								var $thumbnailName = $option.find('.thumbnail-name');
								
								// Always reset state first.
								$warningIndicator.remove();
								$option.removeClass('w2f-pc-disabled').css('opacity', '1');
								$option.find('input[type="radio"]').prop('disabled', false);
								
								// Get clean product name (remove any existing warning emoji or HTML).
								var currentName = '';
								if ($thumbnailName.length) {
									// Remove any existing warning emoji span first.
									$thumbnailName.find('.w2f-pc-warning-emoji').remove();
									// Get text content, removing warning emoji.
									currentName = $thumbnailName.text().replace(/⚠️\s*/g, '').replace(/\u26A0\uFE0F\s*/g, '').trim();
								}
								
								// Check if product has errors.
								var hasErrors = isFilteringActive && !allowedProductIds.includes(productId);
								
								if (hasErrors) {
									// Reset to clean name without warning.
									if ($thumbnailName.length && currentName) {
										$thumbnailName.text(currentName);
									}
									$option.addClass('w2f-pc-disabled').css('opacity', '0.5');
									$option.find('input[type="radio"]').prop('disabled', true);
									// Don't show warnings for products with errors.
								} else {
									// Check warnings - handle both string and number keys.
									var hasWarnings = false;
									var warnings = [];
									if (productWarnings[productId] && productWarnings[productId].length > 0) {
										hasWarnings = true;
										warnings = productWarnings[productId];
									} else if (productWarnings[String(productId)] && productWarnings[String(productId)].length > 0) {
										hasWarnings = true;
										warnings = productWarnings[String(productId)];
									}
									
									// Only show warnings for products that are allowed (no errors).
									if (hasWarnings) {
										// Add warning emoji to product name.
										if ($thumbnailName.length && currentName) {
											$thumbnailName.html('<span class="w2f-pc-warning-emoji">⚠️</span> ' + self.escapeHtml(currentName));
										}
										// Also add badge indicator for visual clarity.
										var $indicator = $('<span class="w2f-pc-warning-indicator" title="' + self.escapeHtml(warnings.join('; ')) + '">⚠️</span>');
										$option.find('.thumbnail-image').append($indicator);
									} else {
										// Reset to clean name without warning.
										if ($thumbnailName.length && currentName) {
											$thumbnailName.text(currentName);
										}
									}
								}
							});

							// Filter select dropdown options (including Select2).
							var selectDisabledCount = 0;
							var selectTotalCount = 0;
							$component.find('select.component-select option').each(function() {
								var $option = $(this);
								var productId = parseInt($option.val());
								
								if (!productId) {
									return; // Skip if no product ID
								}
								selectTotalCount++;
								var hasErrors = isFilteringActive && !allowedProductIds.includes(productId);
								
								if (hasErrors) {
									selectDisabledCount++;
									var currentText = $option.text().replace(/⚠️\s*/g, '').replace(/\u26A0\uFE0F\s*/g, '').trim();
									$option.text(currentText);
									$option.prop('disabled', true);
									// Don't show warnings for products with errors.
								} else {
									$option.prop('disabled', false);
									var currentText = $option.text().replace(/⚠️\s*/g, '').replace(/\u26A0\uFE0F\s*/g, '').trim();
									
									// Check warnings - handle both string and number keys.
									var hasWarnings = false;
									if (productWarnings[productId] && productWarnings[productId].length > 0) {
										hasWarnings = true;
									} else if (productWarnings[String(productId)] && productWarnings[String(productId)].length > 0) {
										hasWarnings = true;
									}
									
									// Only show warnings for products that are allowed (no errors).
									if (hasWarnings) {
										$option.text('⚠️ ' + currentText);
									} else {
										$option.text(currentText);
									}
								}
							});
						});
					}
				},
				error: function() {
					// On error, show all products (fail open).
					$('.w2f-pc-thumbnail-option').removeClass('w2f-pc-disabled').css('opacity', '1').css('pointer-events', 'auto');
					$('input[type="radio"]').prop('disabled', false);
					$('select.component-select option').prop('disabled', false);
					$('.w2f-pc-warning-indicator').remove();
				}
			});
		},

		calculatePrice: function() {
			var self = this;
			var $priceElement = self.domCache.priceElement.length ? self.domCache.priceElement : $('.w2f-pc-total-price');

			// Show loading state.
			$priceElement.css({
				'opacity': '0.5'
			});

			// Use AJAX to get accurate price from server (includes tax).
			$.ajax({
				url: w2f_pc_params.ajax_url,
				type: 'POST',
				data: {
					action: 'w2f_pc_calculate_price',
					nonce: w2f_pc_params.nonce,
					product_id: this.productId,
					configuration: self.currentConfiguration,
					quantities: self.currentQuantities
				},
				success: function(response) {
					if (response.success && response.data) {
						var totalPrice = parseFloat(response.data.price) || 0;
						
						// Animate price update (reduced delay).
						$priceElement.css({
							'opacity': '1',
							'transform': 'scale(1.1)',
							'color': 'var(--w2f-pc-color-accent)'
						});
						
						setTimeout(function() {
							var priceHtml = response.data.price_html || self.formatPrice(totalPrice);
							$priceElement.html(priceHtml).css({
								'transform': 'scale(1)',
								'color': ''
							});
							// Sync header total price
							$('.w2f-pc-header-total-price').html(priceHtml);
						}, 100);
					} else {
						// Fallback to client-side calculation if AJAX fails.
						self.calculatePriceFallback();
					}
				},
				error: function() {
					// Fallback to client-side calculation if AJAX fails.
					self.calculatePriceFallback();
				}
			});
		},

		calculatePriceFallback: function() {
			var self = this;
			var totalPrice = 0;

			// Calculate total price by summing absolute prices (which already include tax).
			$('.w2f-pc-component').each(function() {
				var $component = $(this);
				var componentId = $component.data('component-id');
				
				// Get selected option.
				var selectedProductId = self.currentConfiguration[componentId];
				if (selectedProductId) {
					var optionPrice = 0;
					
					// Try thumbnail option first.
					var $selectedThumbnail = $component.find('.w2f-pc-thumbnail-option[data-product-id="' + selectedProductId + '"]');
					if ($selectedThumbnail.length) {
						optionPrice = parseFloat($selectedThumbnail.attr('data-price')) || 0;
					} else {
						// Try select dropdown option (including Select2).
						var $select = $component.find('select.component-select');
						if ($select.length) {
							var $option = $select.find('option[value="' + selectedProductId + '"]');
							if ($option.length) {
								optionPrice = parseFloat($option.attr('data-price')) || 0;
							}
						}
					}
					
					totalPrice += optionPrice;
				}
			});

			var $priceElement = $('.w2f-pc-total-price');

			// Animate price update.
			$priceElement.css({
				'opacity': '1',
				'transform': 'scale(1.1)',
				'color': 'var(--w2f-pc-color-accent)'
			});
			
			setTimeout(function() {
				var priceHtml = self.formatPrice(totalPrice);
				$priceElement.html(priceHtml).css({
					'transform': 'scale(1)',
					'color': ''
				});
				// Sync header total price
				$('.w2f-pc-header-total-price').html(priceHtml);
			}, 150);
		},

		updateComponentRelativePrices: function(componentId) {
			var self = this;
			var $component = $('.w2f-pc-component[data-component-id="' + componentId + '"]');
			var selectedProductId = this.currentConfiguration[componentId];
			var isWarranty = componentId === 'warranty';
			
			// Get the currently selected product's price (absolute price, already discounted if applicable).
			var selectedProductPrice = 0;
			if (selectedProductId) {
				var $selectedOption = $component.find('.w2f-pc-thumbnail-option[data-product-id="' + selectedProductId + '"]');
				if ($selectedOption.length) {
					selectedProductPrice = parseFloat($selectedOption.attr('data-price')) || 0;
				}
				if (selectedProductPrice === 0) {
					var $selectOption = $component.find('select.component-select option[value="' + selectedProductId + '"]');
					if ($selectOption.length) {
						selectedProductPrice = parseFloat($selectOption.attr('data-price')) || 0;
					}
				}
			}
			
			// If no product is selected, use the base price (default product price, already discounted if applicable).
			if (selectedProductPrice === 0) {
				selectedProductPrice = parseFloat($component.attr('data-base-price')) || 0;
			}

			// Update thumbnail prices (using .w2f-pc-thumbnail-card selector).
			$component.find('.w2f-pc-thumbnail-card').each(function() {
				var $option = $(this);
				var optionPrice = parseFloat($option.attr('data-price')) || 0;
				var relativePrice = optionPrice - selectedProductPrice;
				var $priceSpan = $option.find('.thumbnail-price');
				var isSelected = $option.hasClass('selected');
				var productName = $option.attr('data-product-name') || '';
				var isElite = productName.toLowerCase().indexOf('elite') !== -1;
				
				var priceHtml = '';
				
				// For warranty components, always show absolute price.
				if (isWarranty) {
					// For Elite warranty, show discount pricing.
					if (isElite) {
						// Get the regular price from the option's data attribute or use the current price.
						var regularPriceExTax = parseFloat($option.attr('data-regular-price')) || optionPrice;
						// Add tax (assuming 20% VAT for UK - this should match WooCommerce tax calculation).
						var regularPriceWithTax = regularPriceExTax * 1.2;
						priceHtml = '<span class="w2f-pc-price-wrapper">' +
							'<del class="w2f-pc-regular-price">' + self.formatPrice(regularPriceWithTax) + '</del>' +
							'<ins class="w2f-pc-sale-price">' + self.formatPrice(0) + '</ins>' +
							'</span>';
					} else {
						priceHtml = self.formatPrice(optionPrice);
					}
				} else {
					// For other components, show relative price.
					if (Math.abs(relativePrice) < 0.01) {
						// Price difference is essentially zero (within rounding).
						priceHtml = '—';
					} else if (relativePrice > 0) {
						priceHtml = '+' + self.formatPrice(relativePrice);
					} else {
						// For negative prices, ensure the minus sign is displayed.
						priceHtml = '-' + self.formatPrice(Math.abs(relativePrice));
					}
				}
				
				$priceSpan.html(priceHtml);
			});

			// Update standard dropdown option prices.
			$component.find('.component-select option').each(function() {
				var $option = $(this);
				var optionValue = $option.val();
				if (!optionValue) {
					return; // Skip placeholder option.
				}
				
				var optionPrice = parseFloat($option.attr('data-price')) || 0;
				var relativePrice = optionPrice - selectedProductPrice;
				
				// Get the product name - prefer data attribute if available, otherwise extract from text.
				var productName = $option.attr('data-product-name');
				
				// If no data attribute, extract from option text by removing price suffix.
				if (!productName) {
					var optionText = $option.text();
					// Remove any price suffix in parentheses at the end of the string.
					// This handles patterns like: "Product Name (+£X.XX)", "Product Name (-£X.XX)", "Product Name (—)", etc.
					productName = optionText.replace(/\s*\([^)]*\)\s*$/, '').trim();
					
					// Fallback: if no parentheses found or replacement didn't work, try extracting everything before the first parenthesis.
					if (productName === optionText && optionText.indexOf('(') !== -1) {
						var nameMatch = optionText.match(/^([^(]+?)(?:\s*\(|$)/);
						productName = nameMatch ? nameMatch[1].trim() : optionText;
					}
				}
				
				// Format the new relative price.
				var priceSuffix = '';
				if (Math.abs(relativePrice) >= 0.01) {
					if (relativePrice > 0) {
						priceSuffix = ' (+' + self.formatPrice(relativePrice) + ')';
					} else {
						// For negative prices, ensure the minus sign is displayed.
						priceSuffix = ' (-' + self.formatPrice(Math.abs(relativePrice)) + ')';
					}
				}
				
				// Update option text with clean product name and new price.
				$option.text(productName + priceSuffix);
			});
		},

		escapeHtml: function(text) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, function(m) { return map[m]; });
		},

		formatPrice: function(price) {
			// Use WooCommerce price formatting from localized params.
			if (typeof w2f_pc_params !== 'undefined' && w2f_pc_params.currency) {
				var currency = w2f_pc_params.currency;
				var formatted = Math.abs(parseFloat(price)).toFixed(currency.decimals);
				
				// Add thousand separators.
				if (currency.thousand_sep) {
					formatted = formatted.replace(/\B(?=(\d{3})+(?!\d))/g, currency.thousand_sep);
				}
				
				// Decode HTML entities in currency symbol (e.g., &pound; -> £)
				var symbol = currency.symbol;
				if (symbol && symbol.indexOf('&') === 0) {
					var tempDiv = document.createElement('div');
					tempDiv.innerHTML = symbol;
					symbol = tempDiv.textContent || tempDiv.innerText || symbol;
				}
				
				// Format with currency symbol based on position.
				var priceStr = formatted.replace('.', currency.decimal_sep);
				if (currency.position === 'left' || currency.position === 'left_space') {
					return symbol + (currency.position === 'left_space' ? ' ' : '') + priceStr;
				} else {
					return priceStr + (currency.position === 'right_space' ? ' ' : '') + symbol;
				}
			}
			// Fallback formatting.
			return '£' + parseFloat(price).toFixed(2);
		},

		updateSpecs: function() {
			var self = this;
			var $specList = self.domCache.specList.length ? self.domCache.specList : $('.w2f-pc-spec-list');
			$specList.empty();

			var totalComponents = $('.w2f-pc-component').length;
			var selectedCount = Object.keys(this.currentConfiguration).length;

			$.each(this.currentConfiguration, function(componentId, productId) {
				var $component = self.domCache.components[componentId] || $('.w2f-pc-component[data-component-id="' + componentId + '"]');
				
				// Get component title - clone and remove optional span, then get text
				var $titleElement = $component.find('.component-title');
				var componentTitle = '';
				if ($titleElement.length) {
					// Clone the element, remove the optional span, then get text
					componentTitle = $titleElement.clone().find('.component-optional').remove().end().text().trim();
					// Remove any remaining "(Optional)" text
					componentTitle = componentTitle.replace(/\s*\(Optional\)\s*/gi, '').trim();
				}
				
				var productName = '';
				var productImage = '';
				
				// Get product name from Select2 dropdown (for dropdown-mode components)
				// Also check mobile dropdown for thumbnail components
				var $select = $component.find('.component-select');
				if ($select.length && $select.is('select')) {
					var selectedOption = $select.find('option:selected');
					if (selectedOption.length && selectedOption.val()) {
						productName = selectedOption.data('product-name') || '';
						// If no product name in data attribute, extract from text (remove price suffix)
						if (!productName) {
							var optionText = selectedOption.text().trim();
							productName = optionText.replace(/\s*\([^)]*\)\s*$/, '').trim();
						}
						// Get image URL if available
						productImage = selectedOption.data('image-url') || '';
					}
				}
				
				// Try to get product name and image from thumbnail card.
				if (!productName) {
					var $thumbnail = $component.find('.w2f-pc-thumbnail-card.selected');
					if ($thumbnail.length) {
						var $nameElement = $thumbnail.find('.thumbnail-name');
						if ($nameElement.length) {
							productName = $nameElement.text().trim();
						}
						var $img = $thumbnail.find('img');
						if ($img.length) {
							productImage = $img.attr('src');
						}
						// Also try data-product-name attribute
						if (!productName && $thumbnail.attr('data-product-name')) {
							productName = $thumbnail.attr('data-product-name').trim();
						}
					}
				}

				// Normalize whitespace (multiple spaces to single space).
				componentTitle = componentTitle.replace(/\s+/g, ' ').trim();
				productName = productName.replace(/\s+/g, ' ').trim();

				// If we still don't have a product name, try select option by productId (e.g. Select2 may not have updated :selected yet).
				if (!productName && productId) {
					var $selectOption = $component.find('select.component-select option[value="' + productId + '"]');
					if ($selectOption.length) {
						productName = $selectOption.attr('data-product-name');
						if (productName) {
							productName = productName.trim();
						} else {
							var optText = $selectOption.text().trim();
							productName = optText.replace(/\s*\([^)]*\)\s*$/, '').trim();
						}
					}
				}
				
				// If still no product name, try to get it from image alt text or any element with data-product-id.
				if (!productName && productId) {
					var $productElement = $component.find('[data-product-id="' + productId + '"]');
					if ($productElement.length) {
						// Try data-product-name attribute first.
						if ($productElement.attr('data-product-name')) {
							productName = $productElement.attr('data-product-name').trim();
						} else {
							// Try image alt text.
							var $img = $productElement.find('img');
							if ($img.length && $img.attr('alt')) {
								productName = $img.attr('alt').trim();
							}
						}
					}
				}

				// If we have a component title but no product name, use a fallback.
				if (componentTitle && !productName) {
					var $selectedCard = $component.find('.w2f-pc-thumbnail-card.selected');
					if ($selectedCard.length) {
						var $nameElement = $selectedCard.find('.thumbnail-name');
						if ($nameElement.length) {
							productName = $nameElement.text().trim().replace(/⚠️\s*/g, '').replace(/\s*[\(\[].*?[\)\]]\s*$/, '').trim();
						} else if ($selectedCard.attr('data-product-name')) {
							productName = $selectedCard.attr('data-product-name').trim();
						}
					}
				}

				if (productName && componentTitle) {
					// Check if quantity is enabled and get quantity.
					var quantityText = '';
					if (self.currentQuantities[componentId] && self.currentQuantities[componentId] > 1) {
						quantityText = ' (Qty: ' + self.currentQuantities[componentId] + ')';
					}
					
					// Add to spec list.
					$specList.append('<dt>' + self.escapeHtml(componentTitle) + ':</dt>');
					$specList.append('<dd>' + self.escapeHtml(productName) + self.escapeHtml(quantityText) + '</dd>');
				} else if (componentTitle) {
					// If we have a title but no product name, still show the component (might be unselected).
					// Or log for debugging.
					console.warn('Component "' + componentTitle + '" (ID: ' + componentId + ') has no product name extracted. Product ID: ' + productId);
				}
			});
		},

		shareConfiguration: function() {
			var self = this;
			
			// Check if jsPDF is available.
			if (typeof window.jspdf === 'undefined') {
				this.showToast('PDF library not loaded. Please refresh the page and try again.', 'error');
				return;
			}

			try {
				var { jsPDF } = window.jspdf;
				var doc = new jsPDF();
				
				// Get product title from page.
				var productTitle = $('.product_title, .entry-title, h1.product_title').first().text().trim() || 'PC Configuration';
				
				// Get total price.
				var totalPrice = $('.w2f-pc-total-price').text().trim() || 'N/A';
				
				// Get all specs from the summary.
				var specs = [];
				$('.w2f-pc-spec-list dt').each(function() {
					var $dt = $(this);
					var $dd = $dt.next('dd');
					if ($dd.length) {
						specs.push({
							label: $dt.text().replace(':', '').trim(),
							value: $dd.text().trim()
						});
					}
				});
				
				// Set up colors and fonts.
				var primaryColor = [245, 137, 31]; // #f5891f
				var textGray = [100, 100, 100];
				var yPosition = 20;
				
				// Header section.
				doc.setFillColor(...primaryColor);
				doc.rect(0, 0, 210, 40, 'F');
				
				doc.setTextColor(255, 255, 255);
				doc.setFontSize(20);
				doc.setFont(undefined, 'bold');
				doc.text(productTitle, 20, 25);
				
				// Configuration Details section.
				yPosition = 50;
				doc.setTextColor(...primaryColor);
				doc.setFontSize(16);
				doc.setFont(undefined, 'bold');
				doc.text('Configuration Details', 20, yPosition);
				
				yPosition += 10;
				doc.setDrawColor(200, 200, 200);
				doc.setLineWidth(0.5);
				doc.line(20, yPosition, 190, yPosition);
				
				// Add specs.
				yPosition += 8;
				doc.setFontSize(11);
				doc.setFont(undefined, 'normal');
				
				$.each(specs, function(index, spec) {
					if (yPosition > 250) {
						doc.addPage();
						yPosition = 20;
					}
					
					// Component label.
					doc.setTextColor(...textGray);
					doc.setFont(undefined, 'bold');
					doc.text(spec.label + ':', 20, yPosition);
					
					// Product name.
					doc.setTextColor(0, 0, 0);
					doc.setFont(undefined, 'normal');
					var valueLines = doc.splitTextToSize(spec.value, 150);
					doc.text(valueLines, 30, yPosition);
					
					yPosition += (valueLines.length * 6) + 4;
				});
				
				// Price section.
				if (yPosition > 220) {
					doc.addPage();
					yPosition = 20;
				} else {
					yPosition += 10;
				}
				
				doc.setDrawColor(200, 200, 200);
				doc.setLineWidth(0.5);
				doc.line(20, yPosition, 190, yPosition);
				
				yPosition += 10;
				doc.setFillColor(...primaryColor);
				doc.rect(20, yPosition - 5, 170, 15, 'F');
				
				doc.setTextColor(255, 255, 255);
				doc.setFontSize(14);
				doc.setFont(undefined, 'bold');
				doc.text('TOTAL PRICE', 25, yPosition + 5);
				
				var priceWidth = doc.getTextWidth(totalPrice);
				doc.text(totalPrice, 185 - priceWidth, yPosition + 5);
				
				// Footer section.
				yPosition = 270;
				doc.setDrawColor(200, 200, 200);
				doc.setLineWidth(0.2);
				doc.line(20, yPosition, 190, yPosition);
				
				yPosition += 7;
				doc.setTextColor(...textGray);
				doc.setFontSize(9);
				doc.setFont(undefined, 'normal');
				
				// Add date and time.
				var currentDate = new Date();
				var dateString = currentDate.toLocaleDateString() + ' at ' + currentDate.toLocaleTimeString();
				doc.text('Generated on: ' + dateString, 20, yPosition);
				
				// Add website URL.
				var siteUrl = window.location.hostname;
				if (siteUrl) {
					var urlWidth = doc.getTextWidth(siteUrl);
					doc.text(siteUrl, 190 - urlWidth, yPosition);
				}
				
				// Save the PDF.
				var filename = productTitle.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '_configuration.pdf';
				doc.save(filename);
				
				this.showToast('PDF generated successfully!', 'success');
			} catch (e) {
				console.error('PDF generation error:', e);
				this.showToast('Failed to generate PDF. Please try again.', 'error');
			}
		},

		showToast: function(message, type) {
			type = type || 'info';
			var $toast = $('<div class="w2f-pc-toast w2f-pc-toast-' + type + '">' + message + '</div>');
			$('body').append($toast);
			
			// Trigger animation.
			setTimeout(function() {
				$toast.addClass('active');
			}, 10);
			
			// Remove after delay.
			setTimeout(function() {
				$toast.removeClass('active');
				setTimeout(function() {
					$toast.remove();
				}, 300);
			}, 3000);
		},


		showProductDescriptionModal: function(productId) {
			var self = this;
			
			// Remove existing modal if any.
			$('.w2f-pc-description-modal-overlay').remove();
			
			// Fetch product description via AJAX.
			$.ajax({
				url: w2f_pc_params.ajax_url,
				type: 'POST',
				data: {
					action: 'w2f_pc_get_product_description',
					nonce: w2f_pc_params.nonce,
					product_id: productId
				},
				success: function(response) {
					if (response.success && response.data) {
						// Append the HTML directly (it already includes the overlay)
						var $modal = $(response.data.html);
						$('body').append($modal);
						
						// Show modal with animation.
						setTimeout(function() {
							$modal.addClass('active');
						}, 10);
						
						// Close on overlay click.
						$modal.on('click', function(e) {
							if ($(e.target).hasClass('w2f-pc-description-modal-overlay')) {
								self.closeProductDescriptionModal();
							}
						});
						
						// Close on close button click.
						$modal.on('click', '.w2f-pc-description-modal-close', function(e) {
							e.preventDefault();
							self.closeProductDescriptionModal();
						});
						
						// Close on ESC key.
						$(document).on('keydown.w2f-pc-description-modal', function(e) {
							if (e.keyCode === 27) { // ESC key
								self.closeProductDescriptionModal();
								$(document).off('keydown.w2f-pc-description-modal');
							}
						});
					}
				}
			});
		},

		closeProductDescriptionModal: function() {
			var $modal = $('.w2f-pc-description-modal-overlay');
			$modal.removeClass('active');
			setTimeout(function() {
				$modal.remove();
			}, 300);
		},

		loadConfiguration: function() {
			try {
				var savedConfigs = JSON.parse(localStorage.getItem('w2f_pc_configurations') || '[]');
				var savedConfig = savedConfigs.find(function(c) {
					return c.productId === this.productId;
				}.bind(this));

				if (savedConfig && savedConfig.configuration) {
					// Restore configuration.
					this.currentConfiguration = $.extend({}, savedConfig.configuration);
					
					// Update UI.
					$.each(this.currentConfiguration, function(componentId, productId) {
						var $select = $('.component-select[data-component-id="' + componentId + '"]');
						if ($select.length && $select.is('select')) {
							$select.val(productId).trigger('change');
						}
						// Update thumbnail.
						var $radio = $('.component-select-radio[data-component-id="' + componentId + '"][value="' + productId + '"]');
						if ($radio.length) {
							var $component = self.domCache.components[componentId] || $('.w2f-pc-component[data-component-id="' + componentId + '"]');
							// Clear all selections in this component first.
							$component.find('.w2f-pc-thumbnail-option').removeClass('selected');
							$component.find('.selected-indicator').remove();
							// Then set the radio and trigger change.
							$radio.prop('checked', true).trigger('change');
						}
					});

					this.checkCompatibility();
					this.calculatePrice();
					this.updateSpecs();
					this.showToast('Configuration loaded successfully!', 'success');
				} else {
					this.showToast('No saved configuration found.', 'warning');
				}
			} catch (e) {
				this.showToast('Failed to load configuration.', 'error');
			}
		},

		resetToDefault: function() {
			if (confirm(w2f_pc_params.i18n.reset_confirm || 'Reset to default configuration?')) {
				this.currentConfiguration = $.extend({}, this.defaultConfiguration);
				
				// Update UI.
				$.each(this.currentConfiguration, function(componentId, productId) {
					var $standardSelect = $('.component-select[data-component-id="' + componentId + '"]');
					if ($standardSelect.is('select')) {
						$standardSelect.val(productId).trigger('change');
					}
					// Update thumbnail.
					var $radio = $('.component-select-radio[data-component-id="' + componentId + '"][value="' + productId + '"]');
					if ($radio.length) {
						$radio.prop('checked', true).trigger('change');
					}
				});

				this.checkCompatibility();
				this.calculatePrice();
				this.updateSpecs();
				this.showToast('Configuration reset to default.', 'info');
			}
		},

		updateSaveLoadButtons: function() {
			try {
				var savedConfigs = JSON.parse(localStorage.getItem('w2f_pc_configurations') || '[]');
				var hasSaved = savedConfigs.some(function(c) {
					return c.productId === this.productId;
				}.bind(this));

				if (hasSaved) {
					$('.w2f-pc-load-config').show();
				} else {
					$('.w2f-pc-load-config').hide();
				}
			} catch (e) {
				$('.w2f-pc-load-config').hide();
			}

			// Show reset button.
			$('.w2f-pc-reset-config').show();
		},

		loadConfigurationFromURL: function() {
			var urlParams = new URLSearchParams(window.location.search);
			var encodedConfig = urlParams.get('w2f_pc_config');
			
			if (encodedConfig) {
				try {
					var config = JSON.parse(atob(encodedConfig));
					if (config && typeof config === 'object') {
						this.currentConfiguration = $.extend({}, config);
						
						// Update UI.
						$.each(this.currentConfiguration, function(componentId, productId) {
							// Update dropdown.
							$('.component-select[data-component-id="' + componentId + '"]').val(productId).trigger('change');
							
							// Update thumbnail.
							var $radio = $('.component-select-radio[data-component-id="' + componentId + '"][value="' + productId + '"]');
							if ($radio.length) {
								var $component = self.domCache.components[componentId] || $('.w2f-pc-component[data-component-id="' + componentId + '"]');
								// Clear all selections in this component first.
								$component.find('.w2f-pc-thumbnail-option').removeClass('selected');
								$component.find('.selected-indicator').remove();
								// Then set the radio and trigger change.
								$radio.prop('checked', true).trigger('change');
							}
						});

						this.checkCompatibility();
						this.calculatePrice();
						this.updateSpecs();
					}
				} catch (e) {
					console.error('Failed to load configuration from URL:', e);
				}
			}
		},

		validateConfiguration: function() {
			var self = this;
			var errors = [];
			
			// Check if warranty is required and selected.
			var $warrantyComponent = $('.w2f-pc-component[data-component-id="warranty"]');
			if ($warrantyComponent.length) {
				var warrantySelected = this.currentConfiguration['warranty'];
				if (!warrantySelected || warrantySelected === '0' || warrantySelected === 0) {
					errors.push({
						component: 'warranty',
						message: w2f_pc_params.i18n && w2f_pc_params.i18n.warranty_required ? w2f_pc_params.i18n.warranty_required : 'Please select a warranty option.'
					});
				}
			}
			
			// Check other required components.
			$('.w2f-pc-component').each(function() {
				var $component = $(this);
				var componentId = $component.data('component-id');
				
				// Skip warranty as we already checked it.
				if (componentId === 'warranty') {
					return;
				}
				
				var $optional = $component.find('.component-optional');
				if ($optional.length === 0) {
					// Component is required.
					var selected = self.currentConfiguration[componentId];
					if (!selected || selected === '0' || selected === 0) {
						var componentTitle = $component.find('.component-title').text().replace(/\s*\(Optional\).*$/, '').trim();
						errors.push({
							component: componentId,
							message: 'Please select an option for ' + componentTitle + '.'
						});
					}
				}
			});
			
			return errors;
		},

		addConfigurationToForm: function() {
			var self = this;
			// Add configuration as hidden fields.
			$('input[name^="w2f_pc_configuration"]').remove();
			$('input[name^="w2f_pc_configuration_quantity"]').remove();
			
			$.each(this.currentConfiguration, function(componentId, productId) {
				$('form.w2f-pc-configurator-form, form.cart').append(
					$('<input>').attr({
						type: 'hidden',
						name: 'w2f_pc_configuration[' + componentId + ']',
						value: productId
					})
				);
			});
			
			// Add quantities as hidden fields - handle both regular and thumbnail quantity inputs.
			$.each(this.currentQuantities, function(componentId, quantity) {
				// Only add quantity if a product is selected for this component.
				if (self.currentConfiguration[componentId]) {
					$('form.w2f-pc-configurator-form, form.cart').append(
						$('<input>').attr({
							type: 'hidden',
							name: 'w2f_pc_configuration_quantity[' + componentId + ']',
							value: quantity
						})
					);
				}
			});
			
			// Also collect thumbnail quantity inputs.
			$('.w2f-pc-qty-input:not(:disabled)').each(function() {
				var $input = $(this);
				var componentId = $input.data('component-id');
				var productId = parseInt($input.data('product-id'));
				var quantity = parseInt($input.val()) || 1;
				
				// Only add if this product is selected for this component.
				if (self.currentConfiguration[componentId] === productId) {
					$('form.w2f-pc-configurator-form, form.cart').append(
						$('<input>').attr({
							type: 'hidden',
							name: 'w2f_pc_configuration_quantity[' + componentId + ']',
							value: quantity
						})
					);
				}
			});
		},

		addDefaultToCart: function() {
			var self = this;
			var $button = $('.w2f-pc-add-default-to-cart');
			var originalText = $button.text();
			
			// Disable button and show loading.
			$button.prop('disabled', true).html('<span class="w2f-pc-spinner"></span> ' + (w2f_pc_params.i18n.loading || 'Adding...'));

			// Use form submission method (more reliable for custom data).
			this.submitDefaultToCartForm();
			
			// Reset button after a delay (form will redirect or reload).
			setTimeout(function() {
				$button.prop('disabled', false).text(originalText);
			}, 1000);
		},

		updateThumbnailQuantityInputs: function(componentId, selectedProductId) {
			var self = this;
			var $component = $('.w2f-pc-component[data-component-id="' + componentId + '"]');
			var $quantityInputs = $component.find('.w2f-pc-qty-input');
			
			$quantityInputs.each(function() {
				var $input = $(this);
				var productId = parseInt($input.data('product-id'));
				var isSelected = (selectedProductId === productId);
				
				$input.prop('disabled', !isSelected);
				
				// Update button states.
				var $wrapper = $input.closest('.w2f-pc-thumbnail-quantity');
				var minQuantity = parseInt($input.attr('min')) || 1;
				var maxQuantity = parseInt($input.attr('max')) || 99;
				var quantity = parseInt($input.val()) || minQuantity;
				
				if (isSelected) {
					self.updateQuantityButtonStates($wrapper, quantity, minQuantity, maxQuantity);
				} else {
					$wrapper.find('.w2f-pc-qty-minus, .w2f-pc-qty-plus').prop('disabled', true);
				}
			});
		},

		updateQuantityButtonStates: function($wrapper, quantity, minQuantity, maxQuantity) {
			var $minusBtn = $wrapper.find('.w2f-pc-qty-minus');
			var $plusBtn = $wrapper.find('.w2f-pc-qty-plus');
			
			$minusBtn.prop('disabled', quantity <= minQuantity);
			$plusBtn.prop('disabled', quantity >= maxQuantity);
		},

		closeModal: function() {
			this.destroySelect2();
			$('.w2f-pc-modal-overlay').removeClass('active');
			$('body').css('overflow', '');
		},

		submitDefaultToCartForm: function() {
			// Create and submit a form with default configuration.
			var $form = $('<form>').attr({
				method: 'post',
				action: window.location.href
			});
			$form.append($('<input>').attr({ type: 'hidden', name: 'add-to-cart', value: this.productId }));
			$form.append($('<input>').attr({ type: 'hidden', name: 'quantity', value: '1' }));
			$.each(this.defaultConfiguration, function(componentId, productId) {
				$form.append($('<input>').attr({
					type: 'hidden',
					name: 'w2f_pc_configuration[' + componentId + ']',
					value: productId
				}));
			});
			$('body').append($form);
			$form.submit();
		}
	};

	$(document).ready(function() {
		W2FPCConfigurator.init();
	});

	// Note: URL configuration loading is handled in W2FPCConfigurator.loadConfigurationFromURL()
	// which is called from init() to avoid duplicate loading.

})(jQuery);

