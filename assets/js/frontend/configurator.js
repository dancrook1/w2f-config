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
			this.initializeThumbnailPagination();
			this.filterProductsByRules();
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

		bindEvents: function() {
			var self = this;

			// Open configurator modal.
			$(document).on('click', '.w2f-pc-configure-button', function(e) {
				e.preventDefault();
				$('.w2f-pc-modal-overlay').addClass('active');
				$('body').css('overflow', 'hidden');
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

			// Component selection change (dropdown).
			$(document).on('change', '.component-select', function() {
				var componentId = $(this).data('component-id');
				var productId = $(this).val() ? parseInt($(this).val()) : 0;

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
			
			// Quantity input change.
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
				
				// Update quantities object.
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

			// Custom dropdown toggle.
			$(document).on('click', '.w2f-pc-dropdown-selected', function(e) {
				e.stopPropagation();
				var $dropdown = $(this).closest('.w2f-pc-custom-dropdown');
				var $options = $dropdown.find('.w2f-pc-dropdown-options');
				
				// Close other dropdowns.
				$('.w2f-pc-dropdown-selected').not(this).removeClass('active');
				$('.w2f-pc-dropdown-options').not($options).hide();
				
				// Toggle this dropdown.
				$(this).toggleClass('active');
				$options.toggle();
			});

			// Custom dropdown option selection.
			$(document).on('click', '.w2f-pc-dropdown-option', function(e) {
				e.stopPropagation();
				var $option = $(this);
				var $dropdown = $option.closest('.w2f-pc-custom-dropdown');
				var componentId = $dropdown.data('component-id');
				var productId = parseInt($option.data('product-id'));
				var $hiddenInput = $dropdown.find('.component-select');
				
				// Handle "None" option (value 0).
				if (productId === 0 || productId === '0') {
					$hiddenInput.val(0);
					delete self.currentConfiguration[componentId];
					
					// Update display for "None" option.
					var $selected = $dropdown.find('.w2f-pc-dropdown-selected');
					$selected.find('img').remove();
					var $textWrapper = $selected.find('.w2f-pc-dropdown-text-wrapper');
					if ($textWrapper.length) {
						$textWrapper.find('.w2f-pc-dropdown-text').empty().text('None');
					} else {
						$selected.find('.w2f-pc-dropdown-text').empty().text('None');
					}
					// Remove quick view button for "None" option.
					$selected.find('.w2f-pc-dropdown-quick-view').remove();
				} else {
					$hiddenInput.val(productId);
					self.currentConfiguration[componentId] = productId;
					
					// Update selected option.
					$dropdown.find('.w2f-pc-dropdown-option').removeClass('selected');
					$option.addClass('selected');
					
					// Update display - only show product name, not the price.
					var $selected = $dropdown.find('.w2f-pc-dropdown-selected');
					var $optionImage = $option.find('.w2f-pc-dropdown-option-image');
					// Get image source and alt from the option.
					var imageSrc = $optionImage.attr('src');
					var imageAlt = $optionImage.attr('alt');
					
					// Get product name - prioritize data-product-name attribute, then clean text extraction.
					var productName = $option.data('product-name');
					if (!productName) {
						// Fallback: get from option text, but clean it thoroughly.
						var $optionText = $option.find('.w2f-pc-dropdown-option-text');
						if ($optionText.length) {
							// Remove any warning emoji spans first.
							$optionText.find('.w2f-pc-warning-emoji').remove();
							// Get text content and clean it.
							productName = $optionText.text().trim();
							// Remove warning emoji characters and price suffixes.
							productName = productName.replace(/⚠️\s*/g, '').replace(/\u26A0\uFE0F\s*/g, '').replace(/\s*[\(\[].*?[\)\]]\s*$/, '').trim();
						} else {
							// Last resort: use image alt text.
							productName = imageAlt || '';
						}
					}
					
					// Remove ALL images from the selected area (including any duplicates).
					$selected.find('img').remove();
					// Create a fresh image element (don't clone to avoid class conflicts).
					var $newImage = $('<img>', {
						src: imageSrc,
						alt: imageAlt,
						class: 'w2f-pc-dropdown-image'
					});
					// Get or create text wrapper
					var $textWrapper = $selected.find('.w2f-pc-dropdown-text-wrapper');
					var $textSpan = $selected.find('.w2f-pc-dropdown-text');
					
					if (!$textWrapper.length && $textSpan.length) {
						// Create wrapper if it doesn't exist
						$textWrapper = $('<span>', { class: 'w2f-pc-dropdown-text-wrapper' });
						$textSpan.wrap($textWrapper);
						$textSpan = $selected.find('.w2f-pc-dropdown-text');
					}
					
					if ($textSpan.length) {
						// Insert image before wrapper
						if ($textWrapper.length) {
							$textWrapper.before($newImage);
						} else {
							$textSpan.before($newImage);
						}
						// Clear and set text to avoid duplication.
						$textSpan.empty().text(productName);
					}
					
					// Update quick view button if it exists, or create it if it doesn't.
					if ($textWrapper.length) {
						var $quickView = $textWrapper.find('.w2f-pc-dropdown-quick-view');
						if ($quickView.length) {
							$quickView.attr('data-product-id', productId);
						} else {
							// Create quick view button if it doesn't exist.
							var $newQuickView = $('<button>', {
								type: 'button',
								class: 'w2f-pc-quick-view w2f-pc-dropdown-quick-view',
								'data-product-id': productId,
								'aria-label': 'View product details'
							}).html('<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="8" cy="5.5" r="1" fill="currentColor"/><path d="M8 8V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>');
							$textSpan.after($newQuickView);
						}
					}
				}
				
				// Close dropdown.
				$selected.removeClass('active');
				$dropdown.find('.w2f-pc-dropdown-options').hide();
				
				// Trigger change event.
				$hiddenInput.trigger('change');
			});

			// Close dropdowns when clicking outside.
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.w2f-pc-custom-dropdown').length) {
					$('.w2f-pc-dropdown-selected').removeClass('active');
					$('.w2f-pc-dropdown-options').hide();
				}
			});

			// Component selection change (thumbnail radio).
			$(document).on('change', '.component-select-radio', function() {
				var $radio = $(this);
				var componentId = $radio.data('component-id');
				var productId = $radio.val() ? parseInt($radio.val()) : 0;
				var $option = $radio.closest('.w2f-pc-thumbnail-option');
				
				// Handle "None" option (value 0).
				if (productId === 0 || productId === '0') {
					delete self.currentConfiguration[componentId];
				} else if (productId) {
					self.currentConfiguration[componentId] = productId;
				} else {
					delete self.currentConfiguration[componentId];
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
				$component.find('.w2f-pc-thumbnail-option').removeClass('selected');
				$component.find('.selected-indicator').remove();
				
				// Only proceed if this radio is actually checked.
				if (!$radio.is(':checked')) {
					return;
				}
				
				// Add selected class to new option and add indicator with animation.
				$option.css('transform', 'scale(0.95)');
				setTimeout(function() {
					// Double-check this is still the selected option.
					if ($radio.is(':checked') && $option.closest('.w2f-pc-component[data-component-id="' + componentId + '"]').length) {
						$option.addClass('selected').css('transform', 'scale(1)');
						// Remove any existing indicator first.
						$option.find('.selected-indicator').remove();
						var $indicator = $('<span class="selected-indicator">✓</span>').css('opacity', '0');
						$option.find('.thumbnail-image').append($indicator);
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
						$component.find('.w2f-pc-thumbnail-option, .w2f-pc-dropdown-option').removeClass('hidden');
						$component.find('.component-options select option').show();
					} else {
						// Filter thumbnails.
						$component.find('.w2f-pc-thumbnail-option').each(function() {
							var $option = $(this);
							var productName = $option.find('.thumbnail-name').text().toLowerCase();
							if (productName.indexOf(searchTerm) !== -1) {
								$option.removeClass('hidden');
							} else {
								$option.addClass('hidden');
							}
						});
						
						// Filter custom dropdown options.
						$component.find('.w2f-pc-dropdown-option').each(function() {
							var $option = $(this);
							var productName = $option.find('.w2f-pc-dropdown-option-text').text().toLowerCase();
							if (productName.indexOf(searchTerm) !== -1) {
								$option.removeClass('hidden');
							} else {
								$option.addClass('hidden');
							}
						});
					
					// Filter standard dropdown options.
					$component.find('.component-options select option').each(function() {
						var $option = $(this);
						var optionText = $option.text().toLowerCase();
						if (optionText.indexOf(searchTerm) !== -1 || $option.val() === '') {
							$option.show();
						} else {
							$option.hide();
						}
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
		 * Get items per page based on screen size.
		 */
		getItemsPerPage: function($wrapper) {
			var width = $(window).width();
			if (width <= 480) {
				return 4; // 2 columns x 2 rows
			} else if (width <= 768) {
				return 6; // 3 columns x 2 rows
			} else {
				return 12; // 6 columns x 2 rows
			}
		},

		/**
		 * Initialize pagination for thumbnail grids.
		 */
		initializeThumbnailPagination: function() {
			var self = this;
			$('.w2f-pc-thumbnail-wrapper').each(function() {
				var $wrapper = $(this);
				var componentId = $wrapper.data('component-id');
				var $grid = $wrapper.find('.w2f-pc-thumbnail-grid');
				var $options = $grid.find('.w2f-pc-thumbnail-option');
				var $pagination = $wrapper.find('.w2f-pc-thumbnail-pagination');
				var $prevBtn = $pagination.find('.w2f-pc-pagination-prev');
				var $nextBtn = $pagination.find('.w2f-pc-pagination-next');
				var $currentSpan = $pagination.find('.w2f-pc-pagination-current');
				var $totalSpan = $pagination.find('.w2f-pc-pagination-total');
				
				var totalItems = $options.length;
				var itemsPerPage = self.getItemsPerPage($wrapper);
				var totalPages = Math.ceil(totalItems / itemsPerPage);
				
				// Find selected item to determine initial page.
				var $selected = $grid.find('.w2f-pc-thumbnail-option.selected');
				var initialPage = 1;
				if ($selected.length) {
					var selectedIndex = $options.index($selected);
					initialPage = Math.floor(selectedIndex / itemsPerPage) + 1;
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
					// Show all items if only one page.
					$options.addClass('w2f-pc-thumbnail-visible');
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
			var itemsPerPage = $wrapper.data('items-per-page') || 12;
			var $grid = $wrapper.find('.w2f-pc-thumbnail-grid');
			var $options = $grid.find('.w2f-pc-thumbnail-option');
			var $pagination = $wrapper.find('.w2f-pc-thumbnail-pagination');
			var $prevBtn = $pagination.find('.w2f-pc-pagination-prev');
			var $nextBtn = $pagination.find('.w2f-pc-pagination-next');
			var $currentSpan = $pagination.find('.w2f-pc-pagination-current');
			var $totalSpan = $pagination.find('.w2f-pc-pagination-total');
			
			// Hide all options.
			$options.removeClass('w2f-pc-thumbnail-visible');
			
			// Show options for current page.
			var startIndex = (currentPage - 1) * itemsPerPage;
			var endIndex = startIndex + itemsPerPage;
			
			$options.slice(startIndex, endIndex).addClass('w2f-pc-thumbnail-visible');
			
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
			var $options = $grid.find('.w2f-pc-thumbnail-option');
			var $selected = $grid.find('.w2f-pc-thumbnail-option.selected');
			
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
				// Handle standard dropdown selects.
				var $standardSelect = $('.component-select[data-component-id="' + componentId + '"]');
				if ($standardSelect.is('select')) {
					$standardSelect.val(productId);
				}
				
				// Handle custom dropdowns with images.
				var $customDropdown = $('.w2f-pc-custom-dropdown[data-component-id="' + componentId + '"]');
				if ($customDropdown.length) {
					var $hiddenInput = $customDropdown.find('.component-select');
					$hiddenInput.val(productId);
					
					var $selectedOption = $customDropdown.find('.w2f-pc-dropdown-option[data-product-id="' + productId + '"]');
					if ($selectedOption.length) {
						$customDropdown.find('.w2f-pc-dropdown-option').removeClass('selected');
						$selectedOption.addClass('selected');
						
						// Update display - only show product name, not the price.
						var $selected = $customDropdown.find('.w2f-pc-dropdown-selected');
						
						// Get image source and alt from the option.
						var $optionImage = $selectedOption.find('.w2f-pc-dropdown-option-image');
						var imageSrc = $optionImage.attr('src');
						var imageAlt = $optionImage.attr('alt');
						
						// Get product name - prioritize data-product-name attribute, then clean text extraction.
						var productName = $selectedOption.data('product-name');
						if (!productName) {
							// Fallback: get from option text, but clean it thoroughly.
							var $optionText = $selectedOption.find('.w2f-pc-dropdown-option-text');
							if ($optionText.length) {
								// Remove any warning emoji spans first.
								$optionText.find('.w2f-pc-warning-emoji').remove();
								// Get text content and clean it.
								productName = $optionText.text().trim();
								// Remove warning emoji characters and price suffixes.
								productName = productName.replace(/⚠️\s*/g, '').replace(/\u26A0\uFE0F\s*/g, '').replace(/\s*[\(\[].*?[\)\]]\s*$/, '').trim();
							} else {
								// Last resort: use image alt text.
								productName = imageAlt || '';
							}
						}
						
						// Remove ALL images from the selected area (including any duplicates).
						$selected.find('img').remove();
						// Create a fresh image element (don't clone to avoid class conflicts).
						var $newImage = $('<img>', {
							src: imageSrc,
							alt: imageAlt,
							class: 'w2f-pc-dropdown-image'
						});
						// Get or create text wrapper
						var $textWrapper = $selected.find('.w2f-pc-dropdown-text-wrapper');
						var $textSpan = $selected.find('.w2f-pc-dropdown-text');
						
						if (!$textWrapper.length && $textSpan.length) {
							// Create wrapper if it doesn't exist
							$textWrapper = $('<span>', { class: 'w2f-pc-dropdown-text-wrapper' });
							$textSpan.wrap($textWrapper);
							$textSpan = $selected.find('.w2f-pc-dropdown-text');
						}
						
						if ($textSpan.length) {
							// Insert image before wrapper
							if ($textWrapper.length) {
								$textWrapper.before($newImage);
							} else {
								$textSpan.before($newImage);
							}
							// Clear and set text to avoid duplication.
							$textSpan.empty().text(productName);
						}
						
						// Update quick view button if it exists, or create it if it doesn't.
						if ($textWrapper.length) {
							var $quickView = $textWrapper.find('.w2f-pc-dropdown-quick-view');
							if ($quickView.length) {
								$quickView.attr('data-product-id', productId);
							} else {
								// Create quick view button if it doesn't exist.
								var $newQuickView = $('<button>', {
									type: 'button',
									class: 'w2f-pc-quick-view w2f-pc-dropdown-quick-view',
									'data-product-id': productId,
									'aria-label': 'View product details'
								}).html('<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="8" cy="5.5" r="1" fill="currentColor"/><path d="M8 8V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>');
								$textSpan.after($newQuickView);
							}
						}
					}
				}
				
				// Handle thumbnail radio buttons.
				var $radio = $('.component-select-radio[data-component-id="' + componentId + '"][value="' + productId + '"]');
				var $component = self.domCache.components[componentId] || $('.w2f-pc-component[data-component-id="' + componentId + '"]');
				
				// First, remove selected class and indicators from all options in this component.
				$component.find('.w2f-pc-thumbnail-option').removeClass('selected');
				$component.find('.selected-indicator').remove();
				
				// Then set the radio and add selected class to the correct option.
				$radio.prop('checked', true);
				var $option = $radio.closest('.w2f-pc-thumbnail-option');
				if ($option.length) {
					$option.addClass('selected');
					// Add indicator if it doesn't exist.
					if ($option.find('.selected-indicator').length === 0) {
						$option.find('.thumbnail-image').append('<span class="selected-indicator">✓</span>');
					}
				}
				
				// Update relative prices for this component.
				self.updateComponentRelativePrices(componentId);
			});
			
			// Calculate initial price.
			this.calculatePrice();
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
							$priceElement.html(response.data.price_html || self.formatPrice(totalPrice)).css({
								'transform': 'scale(1)',
								'color': ''
							});
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
							
							// Get all product IDs for this component (cache this).
							var allProductIds = [];
							$component.find('.w2f-pc-thumbnail-option').each(function() {
								var productId = parseInt($(this).data('product-id'));
								if (productId) {
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

							// Filter dropdown options.
							$component.find('.w2f-pc-dropdown-option').each(function() {
								var $option = $(this);
								var productId = parseInt($option.data('product-id'));
								var $optionText = $option.find('.w2f-pc-dropdown-option-text');
								
								if (!productId) {
									return; // Skip if no product ID
								}
								
								// Get clean text (remove any existing warning emoji or HTML).
								var currentText = '';
								if ($optionText.length) {
									// Remove any existing warning emoji span first.
									$optionText.find('.w2f-pc-warning-emoji').remove();
									// Get text content, removing warning emoji.
									currentText = $optionText.text().replace(/⚠️\s*/g, '').replace(/\u26A0\uFE0F\s*/g, '').trim();
								}
								
								$option.removeClass('w2f-pc-disabled').css('opacity', '1');
								$option.css('pointer-events', 'auto');
								
								var hasErrors = isFilteringActive && !allowedProductIds.includes(productId);
								
								if (hasErrors) {
									// Reset to clean text without warning.
									if ($optionText.length && currentText) {
										$optionText.text(currentText);
									}
									$option.addClass('w2f-pc-disabled').css('opacity', '0.5');
									$option.css('pointer-events', 'none');
									// Don't show warnings for products with errors.
								} else {
									// Check warnings - handle both string and number keys.
									var hasWarnings = false;
									if (productWarnings[productId] && productWarnings[productId].length > 0) {
										hasWarnings = true;
									} else if (productWarnings[String(productId)] && productWarnings[String(productId)].length > 0) {
										hasWarnings = true;
									}
									
									// Only show warnings for products that are allowed (no errors).
									if (hasWarnings) {
										// Get the actual warnings array (handle both key types).
										var warnings = productWarnings[productId] || productWarnings[String(productId)] || [];
										// Add warning emoji to product name.
										if ($optionText.length && currentText) {
											$optionText.html('<span class="w2f-pc-warning-emoji">⚠️</span> ' + self.escapeHtml(currentText));
										}
									} else {
										// Reset to clean text without warning.
										if ($optionText.length && currentText) {
											$optionText.text(currentText);
										}
									}
								}
							});

							// Filter standard dropdown options.
							$component.find('select.component-select option').each(function() {
								var $option = $(this);
								var productId = parseInt($option.val());
								
								if (!productId) {
									return; // Skip if no product ID
								}
								
								var hasErrors = isFilteringActive && !allowedProductIds.includes(productId);
								
								if (hasErrors) {
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
					$('.w2f-pc-thumbnail-option, .w2f-pc-dropdown-option').removeClass('w2f-pc-disabled').css('opacity', '1').css('pointer-events', 'auto');
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
							$priceElement.html(response.data.price_html || self.formatPrice(totalPrice)).css({
								'transform': 'scale(1)',
								'color': ''
							});
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
						// Try custom dropdown option.
						var $customDropdownOption = $component.find('.w2f-pc-dropdown-option[data-product-id="' + selectedProductId + '"]');
						if ($customDropdownOption.length) {
							optionPrice = parseFloat($customDropdownOption.attr('data-price')) || 0;
						} else {
							// Try standard dropdown option.
							var $select = $component.find('.component-select');
							if ($select.length && $select.is('select')) {
								var $option = $select.find('option[value="' + selectedProductId + '"]');
								if ($option.length) {
									optionPrice = parseFloat($option.attr('data-price')) || 0;
								}
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
				$priceElement.html(self.formatPrice(totalPrice)).css({
					'transform': 'scale(1)',
					'color': ''
				});
			}, 150);
		},

		updateComponentRelativePrices: function(componentId) {
			var self = this;
			var $component = $('.w2f-pc-component[data-component-id="' + componentId + '"]');
			var selectedProductId = this.currentConfiguration[componentId];
			
			// Get the currently selected product's price (absolute price).
			var selectedProductPrice = 0;
			if (selectedProductId) {
				var $selectedOption = $component.find('[data-product-id="' + selectedProductId + '"]');
				if ($selectedOption.length) {
					selectedProductPrice = parseFloat($selectedOption.attr('data-price')) || 0;
				}
			}
			
			// If no product is selected, use the base price (default product price).
			if (selectedProductPrice === 0) {
				selectedProductPrice = parseFloat($component.attr('data-base-price')) || 0;
			}

			// Update thumbnail prices.
			$component.find('.w2f-pc-thumbnail-option').each(function() {
				var $option = $(this);
				var optionPrice = parseFloat($option.attr('data-price')) || 0;
				var relativePrice = optionPrice - selectedProductPrice;
				var $priceSpan = $option.find('.thumbnail-price');
				
				var priceHtml = '';
				if (Math.abs(relativePrice) < 0.01) {
					// Price difference is essentially zero (within rounding).
					priceHtml = '—';
				} else if (relativePrice > 0) {
					priceHtml = '+' + self.formatPrice(relativePrice);
				} else {
					// For negative prices, ensure the minus sign is displayed.
					priceHtml = '-' + self.formatPrice(Math.abs(relativePrice));
				}
				
				$priceSpan.html(priceHtml);
			});

			// Update custom dropdown option prices.
			$component.find('.w2f-pc-dropdown-option').each(function() {
				var $option = $(this);
				var optionPrice = parseFloat($option.attr('data-price')) || 0;
				var relativePrice = optionPrice - selectedProductPrice;
				var $priceSpan = $option.find('.w2f-pc-dropdown-option-price');
				
				var priceHtml = '';
				if (Math.abs(relativePrice) < 0.01) {
					// Price difference is essentially zero (within rounding).
					priceHtml = '—';
				} else if (relativePrice > 0) {
					priceHtml = '+' + self.formatPrice(relativePrice);
				} else {
					// For negative prices, ensure the minus sign is displayed.
					priceHtml = '-' + self.formatPrice(Math.abs(relativePrice));
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
				
				// Format with currency symbol based on position.
				var priceStr = formatted.replace('.', currency.decimal_sep);
				if (currency.position === 'left' || currency.position === 'left_space') {
					return currency.symbol + (currency.position === 'left_space' ? ' ' : '') + priceStr;
				} else {
					return priceStr + (currency.position === 'right_space' ? ' ' : '') + currency.symbol;
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
				
				// Try to get product name from custom dropdown.
				var $customDropdown = $component.find('.w2f-pc-custom-dropdown');
				if ($customDropdown.length) {
					// First try to get from the selected display (most reliable).
					var $selectedDisplay = $customDropdown.find('.w2f-pc-dropdown-selected .w2f-pc-dropdown-text');
					if ($selectedDisplay.length) {
						productName = $selectedDisplay.text().trim();
					}
					// Fallback to selected option if display doesn't have text.
					if (!productName) {
						var $selectedOption = $customDropdown.find('.w2f-pc-dropdown-option.selected');
						if ($selectedOption.length) {
							var optionText = $selectedOption.find('.w2f-pc-dropdown-option-text').text().trim();
							// Remove warning emoji and any price suffixes.
							productName = optionText.replace(/⚠️\s*/g, '').replace(/\s*[\(\[].*?[\)\]]\s*$/, '').trim();
						}
					}
				}
				
				// Try to get product name from standard dropdown.
				if (!productName) {
					var $select = $component.find('.component-select');
					if ($select.length && $select.is('select')) {
						var selectedOption = $select.find('option:selected');
						if (selectedOption.length && selectedOption.val()) {
							var optionText = selectedOption.text().trim();
							// Remove price in parentheses if present (e.g., "Product Name (+£10)")
							productName = optionText.replace(/\s*\([^)]*\)\s*$/, '').trim();
						}
					}
				}
				
				// Try to get product name and image from thumbnail.
				if (!productName) {
					var $thumbnail = $component.find('.w2f-pc-thumbnail-option.selected');
					if ($thumbnail.length) {
						var $nameElement = $thumbnail.find('.thumbnail-name');
						if ($nameElement.length) {
							productName = $nameElement.text().trim();
						}
						var $img = $thumbnail.find('img');
						if ($img.length) {
							productImage = $img.attr('src');
						}
					}
				}

				// Normalize whitespace (multiple spaces to single space).
				componentTitle = componentTitle.replace(/\s+/g, ' ').trim();
				productName = productName.replace(/\s+/g, ' ').trim();

				// If we still don't have a product name, try to get it from the hidden input value.
				if (!productName) {
					var $hiddenInput = $component.find('.component-select[type="hidden"]');
					if ($hiddenInput.length && $hiddenInput.val()) {
						// Try to find the option with this product ID.
						var selectedProductId = parseInt($hiddenInput.val());
						var $option = $component.find('[data-product-id="' + selectedProductId + '"]');
						if ($option.length) {
							// First try data-product-name attribute (most reliable).
							if ($option.attr('data-product-name')) {
								productName = $option.attr('data-product-name').trim();
							} else {
								// Try thumbnail name.
								var $thumbName = $option.find('.thumbnail-name');
								if ($thumbName.length) {
									productName = $thumbName.text().trim();
								} else {
									// Try dropdown option text.
									var $optText = $option.find('.w2f-pc-dropdown-option-text');
									if ($optText.length) {
										productName = $optText.text().trim().replace(/⚠️\s*/g, '').replace(/\s*[\(\[].*?[\)\]]\s*$/, '').trim();
									} else {
										// Try standard select option.
										var $selectOption = $component.find('select.component-select option[value="' + selectedProductId + '"]');
										if ($selectOption.length) {
											// First try data-product-name attribute.
											if ($selectOption.attr('data-product-name')) {
												productName = $selectOption.attr('data-product-name').trim();
											} else {
												var optText = $selectOption.text().trim();
												productName = optText.replace(/\s*\([^)]*\)\s*$/, '').trim();
											}
										}
									}
								}
							}
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
					// Try to get any text from the component that might indicate a selection.
					var $selectedText = $component.find('.w2f-pc-dropdown-selected, .w2f-pc-thumbnail-option.selected');
					if ($selectedText.length) {
						productName = $selectedText.text().trim().replace(/⚠️\s*/g, '').replace(/\s*[\(\[].*?[\)\]]\s*$/, '').trim();
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
						// Update standard dropdown.
						var $standardSelect = $('.component-select[data-component-id="' + componentId + '"]');
						if ($standardSelect.is('select')) {
							$standardSelect.val(productId).trigger('change');
						}
						
						// Update custom dropdown.
						var $customDropdown = $('.w2f-pc-custom-dropdown[data-component-id="' + componentId + '"]');
						if ($customDropdown.length) {
							var $hiddenInput = $customDropdown.find('.component-select');
							$hiddenInput.val(productId);
							
							var $selectedOption = $customDropdown.find('.w2f-pc-dropdown-option[data-product-id="' + productId + '"]');
							if ($selectedOption.length) {
								$customDropdown.find('.w2f-pc-dropdown-option').removeClass('selected');
								$selectedOption.addClass('selected');
								
								// Update display - only show product name, not the price.
								var $selected = $customDropdown.find('.w2f-pc-dropdown-selected');
								
								// Get image source and alt from the option.
								var $optionImage = $selectedOption.find('.w2f-pc-dropdown-option-image');
								var imageSrc = $optionImage.attr('src');
								var imageAlt = $optionImage.attr('alt');
								
								// Get product name - prioritize data-product-name attribute, then clean text extraction.
								var productName = $selectedOption.data('product-name');
								if (!productName) {
									// Fallback: get from option text, but clean it thoroughly.
									var $optionText = $selectedOption.find('.w2f-pc-dropdown-option-text');
									if ($optionText.length) {
										// Remove any warning emoji spans first.
										$optionText.find('.w2f-pc-warning-emoji').remove();
										// Get text content and clean it.
										productName = $optionText.text().trim();
										// Remove warning emoji characters and price suffixes.
										productName = productName.replace(/⚠️\s*/g, '').replace(/\u26A0\uFE0F\s*/g, '').replace(/\s*[\(\[].*?[\)\]]\s*$/, '').trim();
									} else {
										// Last resort: use image alt text.
										productName = imageAlt || '';
									}
								}
								
								// Remove ALL images from the selected area (including any duplicates).
								$selected.find('img').remove();
								// Create a fresh image element (don't clone to avoid class conflicts).
								var $newImage = $('<img>', {
									src: imageSrc,
									alt: imageAlt,
									class: 'w2f-pc-dropdown-image'
								});
								// Get or create text wrapper
								var $textWrapper = $selected.find('.w2f-pc-dropdown-text-wrapper');
								var $textSpan = $selected.find('.w2f-pc-dropdown-text');
								
								if (!$textWrapper.length && $textSpan.length) {
									// Create wrapper if it doesn't exist
									$textWrapper = $('<span>', { class: 'w2f-pc-dropdown-text-wrapper' });
									$textSpan.wrap($textWrapper);
									$textSpan = $selected.find('.w2f-pc-dropdown-text');
								}
								
								if ($textSpan.length) {
									// Insert image before wrapper
									if ($textWrapper.length) {
										$textWrapper.before($newImage);
									} else {
										$textSpan.before($newImage);
									}
									// Clear and set text to avoid duplication.
									$textSpan.empty().text(productName);
								}
								
								// Update quick view button if it exists, or create it if it doesn't.
								if ($textWrapper.length) {
									var $quickView = $textWrapper.find('.w2f-pc-dropdown-quick-view');
									if ($quickView.length) {
										$quickView.attr('data-product-id', productId);
									} else {
										// Create quick view button if it doesn't exist.
										var $newQuickView = $('<button>', {
											type: 'button',
											class: 'w2f-pc-quick-view w2f-pc-dropdown-quick-view',
											'data-product-id': productId,
											'aria-label': 'View product details'
										}).html('<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="8" cy="5.5" r="1" fill="currentColor"/><path d="M8 8V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>');
										$textSpan.after($newQuickView);
									}
								}
								
								$hiddenInput.trigger('change');
							}
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
					// Update standard dropdown.
					var $standardSelect = $('.component-select[data-component-id="' + componentId + '"]');
					if ($standardSelect.is('select')) {
						$standardSelect.val(productId).trigger('change');
					}
					
					// Update custom dropdown.
					var $customDropdown = $('.w2f-pc-custom-dropdown[data-component-id="' + componentId + '"]');
					if ($customDropdown.length) {
						var $hiddenInput = $customDropdown.find('.component-select');
						$hiddenInput.val(productId);
						
						var $selectedOption = $customDropdown.find('.w2f-pc-dropdown-option[data-product-id="' + productId + '"]');
						if ($selectedOption.length) {
							$customDropdown.find('.w2f-pc-dropdown-option').removeClass('selected');
							$selectedOption.addClass('selected');
							
							// Update display - only show product name, not the price.
							var $selected = $customDropdown.find('.w2f-pc-dropdown-selected');
							
							// Get image source and alt from the option.
							var $optionImage = $selectedOption.find('.w2f-pc-dropdown-option-image');
							var imageSrc = $optionImage.attr('src');
							var imageAlt = $optionImage.attr('alt');
							
							// Get product name - prioritize data-product-name attribute, then clean text extraction.
							var productName = $selectedOption.data('product-name');
							if (!productName) {
								// Fallback: get from option text, but clean it thoroughly.
								var $optionText = $selectedOption.find('.w2f-pc-dropdown-option-text');
								if ($optionText.length) {
									// Remove any warning emoji spans first.
									$optionText.find('.w2f-pc-warning-emoji').remove();
									// Get text content and clean it.
									productName = $optionText.text().trim();
									// Remove warning emoji characters and price suffixes.
									productName = productName.replace(/⚠️\s*/g, '').replace(/\u26A0\uFE0F\s*/g, '').replace(/\s*[\(\[].*?[\)\]]\s*$/, '').trim();
								} else {
									// Last resort: use image alt text.
									productName = imageAlt || '';
								}
							}
							
							// Remove ALL images from the selected area (including any duplicates).
							$selected.find('img').remove();
							// Create a fresh image element (don't clone to avoid class conflicts).
							var $newImage = $('<img>', {
								src: imageSrc,
								alt: imageAlt,
								class: 'w2f-pc-dropdown-image'
							});
							// Insert the image before the text span.
							// Get or create text wrapper
							var $textWrapper = $selected.find('.w2f-pc-dropdown-text-wrapper');
							var $textSpan = $selected.find('.w2f-pc-dropdown-text');
							
							if (!$textWrapper.length && $textSpan.length) {
								// Create wrapper if it doesn't exist
								$textWrapper = $('<span>', { class: 'w2f-pc-dropdown-text-wrapper' });
								$textSpan.wrap($textWrapper);
								$textSpan = $selected.find('.w2f-pc-dropdown-text');
							}
							
							if ($textSpan.length) {
								// Insert image before wrapper
								if ($textWrapper.length) {
									$textWrapper.before($newImage);
								} else {
									$textSpan.before($newImage);
								}
								// Clear and set text to avoid duplication.
								$textSpan.empty().text(productName);
							}
							
							// Update quick view button if it exists, or create it if it doesn't.
							if ($textWrapper.length) {
								var $quickView = $textWrapper.find('.w2f-pc-dropdown-quick-view');
								if ($quickView.length) {
									$quickView.attr('data-product-id', productId);
								} else if (productId && productId !== '0') {
									// Create quick view button if it doesn't exist.
									var $newQuickView = $('<button>', {
										type: 'button',
										class: 'w2f-pc-quick-view w2f-pc-dropdown-quick-view',
										'data-product-id': productId,
										'aria-label': 'View product details'
									}).html('<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="8" cy="5.5" r="1" fill="currentColor"/><path d="M8 8V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>');
									$textSpan.after($newQuickView);
								} else {
									// Remove quick view button for "None" option.
									$textWrapper.find('.w2f-pc-dropdown-quick-view').remove();
								}
							}
							
							$hiddenInput.trigger('change');
						}
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
			
			// Add quantities as hidden fields.
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

		closeModal: function() {
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

