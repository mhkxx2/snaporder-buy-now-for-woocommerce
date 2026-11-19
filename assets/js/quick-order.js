/**
 * ShopForge Labs — SnapOrder Buy Now Frontend Controller
 * Scoped vanilla & jQuery JS for Quick Order Modal, Dynamic Shipping Rate Calculator,
 * Variable Product Variations, Coupon System, Stepper, Validation, AJAX and Meta Pixel / GTM Tracking.
 */

(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var params = window.snaporder_quick_order_params;
		if (!params) {
			return;
		}

		// DOM Elements
		var buyNowBtn = document.getElementById('snaporder-buy-now-btn');
		var whatsAppBtn = document.getElementById('snaporder-chat-btn');
		var modal = document.getElementById('snaporder-quick-order-modal');
		var modalCloseBtn = document.getElementById('snaporder-modal-close-btn');
		var form = document.getElementById('snaporder-quick-order-form');
		var successContainer = document.getElementById('snaporder-success-container');
		var errorNotice = document.getElementById('snaporder-error-notice');

		var modalImg = document.getElementById('snaporder-product-img');
		var modalTitle = document.getElementById('snaporder-product-title');
		var modalVariationBadgeWrap = document.getElementById('snaporder-variation-badge-wrap');
		var modalVariationBadge = document.getElementById('snaporder-variation-badge');
		var modalUnitPrice = document.getElementById('snaporder-unit-price');
		var modalAddonsSummary = document.getElementById('snaporder-modal-addons-summary');
		var modalAddonsPills = document.getElementById('snaporder-modal-addons-pills');

		// Sticky Buy Now Elements
		var stickyBar = document.getElementById('kbnw-sticky-bar');
		var stickyBtn = document.getElementById('kbnw-sticky-btn');
		var stickyImg = document.getElementById('kbnw-sticky-img');
		var stickyTitle = document.getElementById('kbnw-sticky-title');
		var stickyPrice = document.getElementById('kbnw-sticky-price');

		var hiddenProductId = document.getElementById('snaporder_product_id');
		var hiddenVariationId = document.getElementById('snaporder_variation_id');
		var hiddenCouponCode = document.getElementById('snaporder_coupon_code');

		var qtyInput = document.getElementById('snaporder_qty');
		var qtyMinus = document.getElementById('snaporder-qty-minus');
		var qtyPlus = document.getElementById('snaporder-qty-plus');

		var summarySubtotal = document.getElementById('snaporder-summary-subtotal');
		var summaryAddonsRow = document.getElementById('snaporder-summary-addons-row');
		var summaryAddonsAmount = document.getElementById('snaporder-summary-addons-amount');
		var summaryDiscountRow = document.getElementById('snaporder-summary-discount-row');
		var summaryDiscountAmount = document.getElementById('snaporder-summary-discount-amount');
		var summaryTotal = document.getElementById('snaporder-summary-total');
		var submitAmount = document.getElementById('snaporder-submit-amount');
		var submitBtn = document.getElementById('snaporder-complete-purchase-btn');

		var shippingLabel = document.getElementById('snaporder-summary-shipping-label');
		var shippingCost = document.getElementById('snaporder-summary-shipping-cost');
		var shippingSpinner = document.getElementById('snaporder-shipping-spinner');
		var shippingNotice = document.getElementById('snaporder-shipping-unavailable-notice');

		var phoneInput = document.getElementById('snaporder_phone');
		var nameInput = document.getElementById('snaporder_name');
		var citySelect = document.getElementById('snaporder_city');
		var addressInput = document.getElementById('snaporder_address');

		var phoneError = document.getElementById('snaporder-error-phone');
		var nameError = document.getElementById('snaporder-error-name');
		var cityError = document.getElementById('snaporder-error-city');
		var addressError = document.getElementById('snaporder-error-address');

		// Coupon DOM Elements
		var couponToggle = document.getElementById('snaporder-coupon-toggle');
		var couponCollapse = document.getElementById('snaporder-coupon-collapse');
		var couponToggleArrow = document.getElementById('snaporder-coupon-toggle-arrow');
		var couponInput = document.getElementById('snaporder_coupon_input');
		var applyCouponBtn = document.getElementById('snaporder-apply-coupon-btn');
		var couponBtnSpinner = document.getElementById('snaporder-coupon-btn-spinner');
		var couponBtnText = document.getElementById('snaporder-coupon-btn-text');
		var couponFeedback = document.getElementById('snaporder-coupon-feedback');
		var appliedCouponPill = document.getElementById('snaporder-applied-coupon-pill');
		var appliedCouponName = document.getElementById('snaporder-applied-coupon-name');
		var appliedCouponDisc = document.getElementById('snaporder-applied-coupon-disc');
		var removeCouponBtn = document.getElementById('snaporder-remove-coupon-btn');

		// State Variables
		var isShippingAvailable = true;
		var shippingDebounceTimer = null;
		var currentShippingCost = 0;
		var currentShippingIsFree = false;
		var appliedCouponCode = '';
		var appliedDiscount = 0;

		// Dynamic Product / Variation State
		var isVariableProduct = Boolean(params.is_variable) || (document.querySelector('form.variations_form') !== null);
		var currentVariationId = 0;
		var currentVariationLabel = '';
		var currentProductPrice = parseFloat(params.product_price) || 0;
		var currentProductName = params.product_name || '';
		var currentProductUrl = params.product_url || '';
		var currentProductImage = params.product_image || '';

		// Helpers
		function getPageQty() {
			var pageInput = document.querySelector('form.cart input[name="quantity"], .single-product input.qty');
			if (pageInput) {
				var val = parseInt(pageInput.value, 10);
				return (!isNaN(val) && val > 0) ? val : 1;
			}
			return 1;
		}

		function formatCurrency(amount) {
			var symbol = params.currency_symbol || 'AED';
			var decimals = typeof params.decimals !== 'undefined' ? parseInt(params.decimals, 10) : 2;
			var decimalSep = params.decimal_sep || '.';
			var thousandSep = typeof params.thousand_sep !== 'undefined' ? params.thousand_sep : ',';
			var pos = params.currency_pos || 'left_space';

			var num = parseFloat(amount || 0).toFixed(decimals);
			var parts = num.split('.');
			if (thousandSep) {
				parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
			}
			var formatted = parts.join(decimalSep);

			switch (pos) {
				case 'left':
					return symbol + formatted;
				case 'left_space':
					return symbol + ' ' + formatted;
				case 'right':
					return formatted + symbol;
				case 'right_space':
				default:
					return formatted + ' ' + symbol;
			}
		}

		function getSelectedAddons() {
			var list = [];
			var total = 0;
			var checkboxes = document.querySelectorAll('.wca-addon-checkbox:checked, input[name="wca_addons[]"]:checked');
			
			if (checkboxes.length > 0) {
				checkboxes.forEach(function (cb) {
					var rawPrice = cb.dataset.price || cb.getAttribute('data-price') || '0';
					var price = parseFloat(rawPrice) || 0;
					var name = cb.dataset.name || cb.getAttribute('data-name') || '';
					if (!name) {
						var chip = cb.closest('.wca-addon-chip');
						if (chip) {
							var nameEl = chip.querySelector('.wca-addon-name');
							if (nameEl) name = nameEl.textContent.trim();
						}
					}
					if (!name) name = 'Add-on';

					list.push({
						id: cb.value || cb.id.replace(/^wca_addon_/, ''),
						name: name,
						price: price
					});
					total += price;
				});
			} else if (window.wca_selected_addons && Array.isArray(window.wca_selected_addons) && window.wca_selected_addons.length > 0) {
				window.wca_selected_addons.forEach(function (item) {
					var p = parseFloat(item.price) || 0;
					list.push({
						id: item.id,
						name: item.name || 'Add-on',
						price: p
					});
					total += p;
				});
			} else if (typeof window.wca_current_addons_total === 'number' && window.wca_current_addons_total > 0) {
				total = window.wca_current_addons_total;
			}

			return { list: list, total: total };
		}

		function updateModalProductDetails() {
			var addons = getSelectedAddons();
			var unitPriceWithAddons = currentProductPrice + addons.total;

			if (modalTitle) {
				modalTitle.textContent = currentProductName;
			}
			if (modalImg && currentProductImage) {
				modalImg.src = currentProductImage;
			}
			if (modalUnitPrice) {
				modalUnitPrice.textContent = formatCurrency(unitPriceWithAddons);
				modalUnitPrice.setAttribute('data-unit-price', unitPriceWithAddons);
			}
			if (modalVariationBadgeWrap && modalVariationBadge) {
				if (currentVariationLabel) {
					modalVariationBadge.textContent = currentVariationLabel;
					modalVariationBadgeWrap.style.display = 'block';
				} else {
					modalVariationBadgeWrap.style.display = 'none';
					modalVariationBadge.textContent = '';
				}
			}

			// Render Add-on Pills in Product Overview
			if (modalAddonsSummary && modalAddonsPills) {
				if (addons.list.length > 0) {
					var pillsHtml = '';
					addons.list.forEach(function (item) {
						pillsHtml += '<span class="snaporder-modal-addon-pill">' +
							'<span class="snaporder-modal-addon-pill-name">' + item.name + '</span>' +
							'<span class="snaporder-modal-addon-pill-price">(+' + formatCurrency(item.price) + ')</span>' +
							'</span>';
					});
					modalAddonsPills.innerHTML = pillsHtml;
					modalAddonsSummary.style.display = 'flex';
				} else {
					modalAddonsPills.innerHTML = '';
					modalAddonsSummary.style.display = 'none';
				}
			}

			if (hiddenVariationId) {
				hiddenVariationId.value = currentVariationId || '';
			}
			if (stickyTitle) {
				stickyTitle.textContent = currentProductName;
			}
			if (stickyImg && currentProductImage) {
				stickyImg.src = currentProductImage;
			}
			if (stickyPrice) {
				stickyPrice.textContent = formatCurrency(unitPriceWithAddons);
			}
		}

		// Listen to WooCommerce & WoodMart standard variation form events
		function initVariationListeners() {
			if (window.jQuery && isVariableProduct) {
				window.jQuery(document).on('found_variation show_variation', 'form.variations_form', function (event, variation) {
					if (!variation) return;

					currentVariationId = variation.variation_id || 0;
					if (typeof variation.display_price !== 'undefined' && variation.display_price !== null) {
						currentProductPrice = parseFloat(variation.display_price);
					}

					// Extract readable attribute label
					var attrLabels = [];
					if (variation.attributes) {
						for (var k in variation.attributes) {
							if (variation.attributes.hasOwnProperty(k) && variation.attributes[k]) {
								var val = variation.attributes[k].replace(/[-_]/g, ' ');
								attrLabels.push(val);
							}
						}
					}
					currentVariationLabel = attrLabels.join(' / ');

					if (variation.image && (variation.image.thumb_src || variation.image.src)) {
						currentProductImage = variation.image.thumb_src || variation.image.src;
					}

					updateModalProductDetails();
					updateLiveTotal();

					if (modal && modal.classList.contains('is-active')) {
						fetchLiveShipping();
					}
				});

				window.jQuery(document).on('reset_data hide_variation', 'form.variations_form', function () {
					currentVariationId = 0;
					currentVariationLabel = '';
					currentProductPrice = parseFloat(params.product_price) || 0;
					currentProductImage = params.product_image || '';

					updateModalProductDetails();
					updateLiveTotal();
				});
			}

			// Also check native change events on variation selects and radios
			var varForm = document.querySelector('form.variations_form');
			if (varForm) {
				var checkPreselected = function () {
					var varInput = varForm.querySelector('input[name="variation_id"]');
					if (varInput && varInput.value) {
						var parsedId = parseInt(varInput.value, 10);
						if (parsedId > 0 && Array.isArray(params.available_variations)) {
							for (var i = 0; i < params.available_variations.length; i++) {
								var v = params.available_variations[i];
								if (v.variation_id === parsedId) {
									currentVariationId = parsedId;
									if (typeof v.display_price !== 'undefined') {
										currentProductPrice = parseFloat(v.display_price);
									}
									if (v.image && (v.image.thumb_src || v.image.src)) {
										currentProductImage = v.image.thumb_src || v.image.src;
									}
									updateModalProductDetails();
									updateLiveTotal();
									break;
								}
							}
						}
					}
				};
				checkPreselected();
				varForm.addEventListener('change', function () {
					setTimeout(checkPreselected, 50);
				});
			}
		}

		initVariationListeners();

		function isOptionSelectionRequired() {
			if (!isVariableProduct) return false;
			var varInput = document.querySelector('form.variations_form input[name="variation_id"]');
			var inputVal = varInput ? parseInt(varInput.value, 10) : 0;
			return (!currentVariationId || currentVariationId <= 0) && (!inputVal || inputVal <= 0);
		}

		function highlightVariationOptions() {
			var varForm = document.querySelector('form.variations_form') || document.querySelector('.variations');
			if (varForm) {
				varForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
				varForm.classList.add('snaporder-highlight-options');
				setTimeout(function () {
					varForm.classList.remove('snaporder-highlight-options');
				}, 1600);
			}
			var notif = document.getElementById('snaporder-quick-notice');
			if (!notif) {
				notif = document.createElement('div');
				notif.id = 'snaporder-quick-notice';
				notif.className = 'snaporder-floating-notice';
				document.body.appendChild(notif);
			}
			notif.textContent = params.i18n.select_options;
			notif.classList.add('show');
			setTimeout(function () {
				notif.classList.remove('show');
			}, 3200);
		}

		function fetchLiveShipping() {
			if (!params.ajax_url || !params.nonce) return;

			var currentQty = parseInt(qtyInput ? qtyInput.value : 1, 10) || 1;
			var selectedCity = citySelect ? citySelect.value : 'Dubai';

			if (shippingSpinner) shippingSpinner.style.display = 'inline-block';
			if (shippingCost) shippingCost.textContent = 'Calculating...';
			if (shippingNotice) {
				shippingNotice.style.display = 'none';
				shippingNotice.textContent = '';
			}

			var payload = new URLSearchParams();
			payload.append('action', 'snaporder_calculate_shipping');
			payload.append('security', params.nonce);
			payload.append('product_id', params.product_id);
			if (currentVariationId > 0) {
				payload.append('variation_id', currentVariationId);
			}
			payload.append('qty', currentQty);
			payload.append('city', selectedCity);
			if (appliedCouponCode) {
				payload.append('coupon_code', appliedCouponCode);
			}
			var addons = getSelectedAddons();
			addons.list.forEach(function (item) {
				payload.append('wca_addons[]', item.id);
			});

			fetch(params.ajax_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: payload.toString()
			})
			.then(function (res) {
				return res.json();
			})
			.then(function (res) {
				if (shippingSpinner) shippingSpinner.style.display = 'none';

				if (!res || !res.success || !res.data || !res.data.available) {
					isShippingAvailable = false;
					currentShippingCost = 0;

					if (shippingCost) shippingCost.textContent = 'Unavailable';
					if (shippingLabel) shippingLabel.textContent = 'Delivery';

					var errMsg = (res && res.data && res.data.message) ? res.data.message : 'Delivery is not available for this location.';
					if (shippingNotice) {
						shippingNotice.textContent = errMsg;
						shippingNotice.style.display = 'block';
					}

					if (submitBtn) submitBtn.disabled = true;
					return;
				}

				var data = res.data;
				isShippingAvailable = true;
				currentShippingCost = parseFloat(data.cost) || 0;
				currentShippingIsFree = Boolean(data.is_free);

				if (shippingLabel) {
					shippingLabel.textContent = data.label ? ('Delivery (' + data.label + ')') : 'Delivery';
				}

				if (shippingCost) {
					if (data.is_free) {
						shippingCost.innerHTML = '<span class="snaporder-free-badge">FREE</span>';
					} else {
						shippingCost.textContent = data.cost_formatted || formatCurrency(currentShippingCost);
					}
				}

				if (summarySubtotal) {
					summarySubtotal.textContent = data.subtotal_formatted || formatCurrency(data.subtotal);
				}

				if (summaryAddonsRow && summaryAddonsAmount) {
					if (data.addons_total && parseFloat(data.addons_total) > 0) {
						summaryAddonsAmount.textContent = data.addons_formatted || ('+ ' + formatCurrency(data.addons_total));
						summaryAddonsRow.style.display = 'flex';
					} else if (addons.total > 0) {
						summaryAddonsAmount.textContent = '+ ' + formatCurrency(addons.total * currentQty);
						summaryAddonsRow.style.display = 'flex';
					} else {
						summaryAddonsRow.style.display = 'none';
					}
				}

				if (summaryDiscountRow && summaryDiscountAmount) {
					if (data.discount && parseFloat(data.discount) > 0) {
						appliedDiscount = parseFloat(data.discount);
						summaryDiscountAmount.textContent = data.discount_formatted || ('- ' + formatCurrency(appliedDiscount));
						summaryDiscountRow.style.display = 'flex';
					} else {
						appliedDiscount = 0;
						summaryDiscountRow.style.display = 'none';
					}
				}

				if (summaryTotal) {
					summaryTotal.textContent = data.total_formatted || formatCurrency(data.total);
				}
				if (submitAmount) {
					submitAmount.textContent = data.total_formatted || formatCurrency(data.total);
				}

				if (shippingNotice) {
					shippingNotice.style.display = 'none';
					shippingNotice.textContent = '';
				}

				if (submitBtn) submitBtn.disabled = false;
			})
			.catch(function () {
				if (shippingSpinner) shippingSpinner.style.display = 'none';
			});
		}

		function triggerShippingDebounce(delay) {
			if (shippingDebounceTimer) {
				clearTimeout(shippingDebounceTimer);
			}
			shippingDebounceTimer = setTimeout(fetchLiveShipping, delay || 180);
		}

		function updateLiveTotal() {
			if (!qtyInput) return;
			var qty = parseInt(qtyInput.value, 10);
			if (isNaN(qty) || qty < 1) {
				qty = 1;
				qtyInput.value = '1';
			}

			var addons = getSelectedAddons();
			var productSubtotal = currentProductPrice * qty;
			var addonsSubtotal = addons.total * qty;
			var combinedSubtotal = productSubtotal + addonsSubtotal;
			var discount = appliedDiscount || 0;
			var total = Math.max(0, combinedSubtotal - discount) + currentShippingCost;

			if (summarySubtotal) summarySubtotal.textContent = formatCurrency(combinedSubtotal);

			if (summaryAddonsRow && summaryAddonsAmount) {
				if (addonsSubtotal > 0) {
					summaryAddonsAmount.textContent = '+ ' + formatCurrency(addonsSubtotal);
					summaryAddonsRow.style.display = 'flex';
				} else {
					summaryAddonsRow.style.display = 'none';
				}
			}

			if (summaryDiscountRow && summaryDiscountAmount) {
				if (discount > 0) {
					summaryDiscountAmount.textContent = '- ' + formatCurrency(discount);
					summaryDiscountRow.style.display = 'flex';
				} else {
					summaryDiscountRow.style.display = 'none';
				}
			}
			if (summaryTotal) summaryTotal.textContent = formatCurrency(total);
			if (submitAmount) submitAmount.textContent = formatCurrency(total);

			triggerShippingDebounce(150);
		}

		function openModal() {
			if (!modal) return;
			var initialQty = getPageQty();
			if (qtyInput) {
				qtyInput.value = initialQty;
			}
			if (submitBtn) {
				submitBtn.disabled = false;
				var spOpen = submitBtn.querySelector('.snaporder-submit-spinner');
				if (spOpen) spOpen.style.display = 'none';
				var stOpen = submitBtn.querySelector('.snaporder-submit-text');
				if (stOpen) stOpen.textContent = params.i18n.complete_order;
			}

			updateModalProductDetails();
			updateLiveTotal();
			fetchLiveShipping();

			// Safe Meta Pixel: AddToCart
			var currentTotal = currentProductPrice * initialQty;
			var trackingProductId = String(currentVariationId || params.product_id);
			if (typeof window.fbq === 'function') {
				try {
					window.fbq('track', 'AddToCart', {
						content_name: currentProductName + (currentVariationLabel ? (' - ' + currentVariationLabel) : ''),
						content_ids: [trackingProductId],
						value: currentTotal,
						currency: params.currency_code || 'AED',
						contents: [{ id: trackingProductId, quantity: initialQty }]
					});
				} catch (e) {}
			}

			// Phase 6: Session-scoped begin_checkout intent deduplication
			window._snaporderCheckoutIntents = window._snaporderCheckoutIntents || {};
			var intentKey = 'qo_' + String(params.product_id) + '_' + String(currentVariationId || 0);

			if (!window._snaporderCheckoutIntents[intentKey]) {
				window._snaporderCheckoutIntents[intentKey] = true;
				if (typeof window.snaporderTrackEvent === 'function') {
					window.snaporderTrackEvent('begin_checkout', {
						currency: params.currency_code || 'AED',
						value: currentTotal,
						items: [{
							item_id: trackingProductId,
							item_name: currentProductName,
							item_brand: params.product_brand || '',
							item_category: params.product_cat || '',
							item_variant: currentVariationLabel || '',
							price: currentProductPrice,
							quantity: initialQty
						}]
					});
				}
			}

			modal.classList.add('is-active');
			document.body.classList.add('snaporder-modal-open');

			if (phoneInput) {
				setTimeout(function () {
					phoneInput.focus();
				}, 150);
			}
		}

		function closeModal() {
			if (!modal) return;
			modal.classList.remove('is-active');
			document.body.classList.remove('snaporder-modal-open');
			if (submitBtn) {
				submitBtn.disabled = false;
				var spClose = submitBtn.querySelector('.snaporder-submit-spinner');
				if (spClose) spClose.style.display = 'none';
				var stClose = submitBtn.querySelector('.snaporder-submit-text');
				if (stClose) stClose.textContent = params.i18n.complete_order;
			}
		}

		// 1. WhatsApp Button Click Handler
		if (whatsAppBtn) {
			whatsAppBtn.addEventListener('click', function (e) {
				e.preventDefault();

				if (isOptionSelectionRequired()) {
					highlightVariationOptions();
					return;
				}

				var qty = getPageQty();
				var addons = getSelectedAddons();
				var unitPrice = currentProductPrice + addons.total;
				var total = unitPrice * qty;
				var prodTitle = currentProductName + (currentVariationLabel ? (' (' + currentVariationLabel + ')') : '');

				// Build Add-ons string if any are selected
				var addonsText = '';
				if (addons.list.length > 0) {
					addonsText = addons.list.map(function (a) {
						return a.name + ' (+' + formatCurrency(a.price) + ')';
					}).join(', ');
				}

				// Safe Meta Pixel: Contact
				var trackingProductId = String(currentVariationId || params.product_id);
				if (typeof window.fbq === 'function') {
					try {
						window.fbq('track', 'Contact', {
							content_name: prodTitle,
							content_ids: [trackingProductId],
							value: total,
							currency: params.currency_code || 'AED'
						});
					} catch (err) {}
				}

				// GTM dataLayer Push
				window.dataLayer = window.dataLayer || [];
				window.dataLayer.push({
					event: 'whatsapp_click',
					product_id: trackingProductId,
					product_name: prodTitle,
					quantity: qty,
					value: total,
					currency: params.currency_code || 'AED'
				});

				// Compile Message
				var template = params.whatsapp_template || "Hi, I want to order this product:\n{product_name}\nQty: {qty}\nPrice: {price} AED\nLink: {link}";
				var msg = template;

				if (addonsText) {
					if (msg.indexOf('{addons}') !== -1) {
						msg = msg.replace(/{addons}/g, addonsText);
					} else {
						if (msg.indexOf('{product_name}') !== -1) {
							msg = msg.replace('{product_name}', '{product_name}\nAdd-ons: ' + addonsText);
						} else {
							msg = 'Add-ons: ' + addonsText + '\n' + msg;
						}
					}
				} else {
					msg = msg.replace(/\{addons\}\n?/g, '');
				}

				msg = msg
					.replace(/{product_name}/g, prodTitle)
					.replace(/{qty}/g, qty)
					.replace(/{price}/g, formatCurrency(unitPrice))
					.replace(/{total_price}/g, formatCurrency(total))
					.replace(/{link}/g, currentProductUrl);

				var cleanPhone = (params.whatsapp_number || '').replace(/[^0-9]/g, '');
				var waUrl = 'https://wa.me/' + cleanPhone + '?text=' + encodeURIComponent(msg);

				window.open(waUrl, '_blank', 'noopener,noreferrer');
			});
		}

		// 2. Buy Now Button Click Handler
		if (buyNowBtn) {
			buyNowBtn.addEventListener('click', function (e) {
				e.preventDefault();

				if (isOptionSelectionRequired()) {
					highlightVariationOptions();
					return;
				}

				openModal();
			});
		}

		// 2b. Sticky Buy Now Scroll Observer & Click Handler
		if (stickyBar) {
			var triggerElement = document.getElementById('snaporder-action-buttons') || document.querySelector('form.cart') || document.querySelector('.single_add_to_cart_button');

			var checkStickyVisibility = function () {
				if (!triggerElement) return;
				var rect = triggerElement.getBoundingClientRect();
				// Show sticky bar when the bottom of the main buy now area scrolls above 30px from viewport top
				if (rect.bottom < 30) {
					stickyBar.classList.add('kbnw-sticky-shown');
					document.body.classList.add('has-kbnw-sticky-bar');
				} else {
					stickyBar.classList.remove('kbnw-sticky-shown');
					document.body.classList.remove('has-kbnw-sticky-bar');
				}
			};

			window.addEventListener('scroll', checkStickyVisibility, { passive: true });
			window.addEventListener('resize', checkStickyVisibility, { passive: true });
			checkStickyVisibility();

			if (stickyBtn) {
				stickyBtn.addEventListener('click', function (e) {
					e.preventDefault();

					if (isOptionSelectionRequired()) {
						var varForm = document.querySelector('form.variations_form, .wd-single-add-cart .variations_form');
						if (varForm) {
							var yOffset = -120;
							var y = varForm.getBoundingClientRect().top + window.pageYOffset + yOffset;
							window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
						}
						highlightVariationOptions();
						return;
					}

					openModal();
				});
			}
		}

		// 3. Modal Close Triggers
		if (modalCloseBtn) {
			modalCloseBtn.addEventListener('click', function () {
				closeModal();
			});
		}

		if (modal) {
			modal.addEventListener('click', function (e) {
				if (e.target === modal) {
					closeModal();
				}
			});
		}

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && modal && modal.classList.contains('is-active')) {
				closeModal();
			}
		});

		// 4. Stepper Interactivity
		if (qtyMinus && qtyInput) {
			qtyMinus.addEventListener('click', function () {
				var cur = parseInt(qtyInput.value, 10) || 1;
				if (cur > 1) {
					qtyInput.value = cur - 1;
					updateLiveTotal();
				}
			});
		}

		if (qtyPlus && qtyInput) {
			qtyPlus.addEventListener('click', function () {
				var cur = parseInt(qtyInput.value, 10) || 1;
				qtyInput.value = cur + 1;
				updateLiveTotal();
			});
		}

		if (qtyInput) {
			qtyInput.addEventListener('input', function () {
				updateLiveTotal();
			});
			qtyInput.addEventListener('change', function () {
				updateLiveTotal();
			});
		}

		// 5. City Dropdown Change -> Immediate Shipping Recalculation
		if (citySelect) {
			citySelect.addEventListener('change', function () {
				fetchLiveShipping();
			});
		}

		// 6. Payment Radio Cards Selection Styling
		var paymentCards = document.querySelectorAll('.snaporder-payment-card');
		paymentCards.forEach(function (card) {
			card.addEventListener('click', function () {
				paymentCards.forEach(function (c) { c.classList.remove('active'); });
				card.classList.add('active');
				var radio = card.querySelector('input[type="radio"]');
				if (radio) {
					radio.checked = true;
				}
			});
		});

		// 7. Coupon Accordion & Application Logic
		if (couponToggle && couponCollapse) {
			couponToggle.addEventListener('click', function () {
				var isExpanded = couponCollapse.style.display !== 'none';
				if (isExpanded) {
					couponCollapse.style.display = 'none';
					couponToggle.setAttribute('aria-expanded', 'false');
					if (couponToggleArrow) couponToggleArrow.textContent = '▾';
				} else {
					couponCollapse.style.display = 'block';
					couponToggle.setAttribute('aria-expanded', 'true');
					if (couponToggleArrow) couponToggleArrow.textContent = '▴';
					if (couponInput) couponInput.focus();
				}
			});
		}

		if (applyCouponBtn && couponInput) {
			var submitCoupon = function () {
				var code = couponInput.value.trim();
				if (!code) {
					if (couponFeedback) {
						couponFeedback.textContent = params.i18n.coupon_empty || 'Please enter a coupon code.';
						couponFeedback.className = 'snaporder-coupon-feedback is-error';
						couponFeedback.style.display = 'block';
					}
					return;
				}

				if (couponBtnSpinner) couponBtnSpinner.style.display = 'inline-block';
				if (couponBtnText) couponBtnText.textContent = params.i18n.applying_coupon || 'Applying...';
				applyCouponBtn.disabled = true;
				if (couponFeedback) {
					couponFeedback.style.display = 'none';
					couponFeedback.textContent = '';
				}

				var payload = new URLSearchParams();
				payload.append('action', 'snaporder_validate_coupon');
				payload.append('security', params.nonce);
				payload.append('coupon_code', code);
				payload.append('product_id', params.product_id);
				if (currentVariationId > 0) {
					payload.append('variation_id', currentVariationId);
				}
				payload.append('qty', qtyInput ? qtyInput.value : '1');
				payload.append('city', citySelect ? citySelect.value : 'Dubai');
				var addons = getSelectedAddons();
				addons.list.forEach(function (item) {
					payload.append('wca_addons[]', item.id);
				});

				fetch(params.ajax_url, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: payload.toString()
				})
				.then(function (res) {
					return res.json();
				})
				.then(function (res) {
					if (couponBtnSpinner) couponBtnSpinner.style.display = 'none';
					if (couponBtnText) couponBtnText.textContent = params.i18n.apply_coupon || 'Apply';
					applyCouponBtn.disabled = false;

					if (!res || !res.success || !res.data) {
						var errMsg = (res && res.data && res.data.message) ? res.data.message : 'Invalid coupon.';
						if (couponFeedback) {
							couponFeedback.textContent = errMsg;
							couponFeedback.className = 'snaporder-coupon-feedback is-error';
							couponFeedback.style.display = 'block';
						}
						return;
					}

					var data = res.data;
					appliedCouponCode = data.coupon_code;
					appliedDiscount = parseFloat(data.discount) || 0;

					if (hiddenCouponCode) {
						hiddenCouponCode.value = appliedCouponCode;
					}

					if (appliedCouponName) appliedCouponName.textContent = appliedCouponCode.toUpperCase();
					if (appliedCouponDisc) appliedCouponDisc.textContent = '(' + (data.discount_formatted || formatCurrency(appliedDiscount)) + ')';

					if (appliedCouponPill) appliedCouponPill.style.display = 'inline-flex';
					if (couponCollapse) couponCollapse.style.display = 'none';
					if (couponToggle) {
						couponToggle.style.display = 'none';
						couponToggle.setAttribute('aria-expanded', 'false');
					}

					if (summarySubtotal) summarySubtotal.textContent = data.subtotal_formatted || formatCurrency(data.subtotal);
					if (summaryDiscountRow && summaryDiscountAmount) {
						summaryDiscountAmount.textContent = data.discount_formatted || ('- ' + formatCurrency(appliedDiscount));
						summaryDiscountRow.style.display = 'flex';
					}
					if (shippingCost) {
						if (data.shipping_cost <= 0) {
							shippingCost.innerHTML = '<span class="snaporder-free-badge">FREE</span>';
						} else {
							shippingCost.textContent = data.shipping_formatted || formatCurrency(data.shipping_cost);
						}
					}
					if (summaryTotal) summaryTotal.textContent = data.total_formatted || formatCurrency(data.total);
					if (submitAmount) submitAmount.textContent = data.total_formatted || formatCurrency(data.total);
				})
				.catch(function () {
					if (couponBtnSpinner) couponBtnSpinner.style.display = 'none';
					if (couponBtnText) couponBtnText.textContent = params.i18n.apply_coupon || 'Apply';
					applyCouponBtn.disabled = false;
					if (couponFeedback) {
						couponFeedback.textContent = params.i18n.error_occurred || 'An error occurred. Please try again.';
						couponFeedback.className = 'snaporder-coupon-feedback is-error';
						couponFeedback.style.display = 'block';
					}
				});
			};

			applyCouponBtn.addEventListener('click', submitCoupon);
			couponInput.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					submitCoupon();
				}
			});
		}

		if (removeCouponBtn) {
			removeCouponBtn.addEventListener('click', function () {
				appliedCouponCode = '';
				appliedDiscount = 0;
				if (hiddenCouponCode) hiddenCouponCode.value = '';
				if (couponInput) couponInput.value = '';
				if (appliedCouponPill) appliedCouponPill.style.display = 'none';
				if (couponToggle) couponToggle.style.display = 'inline-flex';
				if (summaryDiscountRow) summaryDiscountRow.style.display = 'none';
				if (couponFeedback) {
					couponFeedback.style.display = 'none';
					couponFeedback.textContent = '';
				}
				fetchLiveShipping();
			});
		}

		// 8. Client-Side Form Validation & AJAX Submission
		function clearErrors() {
			if (errorNotice) {
				errorNotice.style.display = 'none';
				errorNotice.textContent = '';
			}
			[phoneInput, nameInput, citySelect, addressInput].forEach(function (el) {
				if (el) {
					el.classList.remove('is-invalid');
					var wrapper = el.closest('.snaporder-phone-wrapper');
					if (wrapper) wrapper.classList.remove('is-invalid');
				}
			});
			[phoneError, nameError, cityError, addressError].forEach(function (el) {
				if (el) {
					el.classList.remove('show');
					el.textContent = '';
				}
			});
		}

		function showFieldError(fieldEl, errorEl, message) {
			if (fieldEl) {
				fieldEl.classList.add('is-invalid');
				var wrapper = fieldEl.closest('.snaporder-phone-wrapper');
				if (wrapper) wrapper.classList.add('is-invalid');
			}
			if (errorEl) {
				errorEl.textContent = message;
				errorEl.classList.add('show');
			}
		}

		function validateForm() {
			clearErrors();
			var isValid = true;
			var firstInvalid = null;

			// Phone Validation (UAE Format)
			var rawPhone = phoneInput ? phoneInput.value.trim() : '';
			var cleanPhone = rawPhone.replace(/[\s\-\(\)\.]/g, '');
			var uaeRegex = /^(?:(?:\+971|00971|971|0)?)(5[0-9]{8})$/;
			if (!cleanPhone || !uaeRegex.test(cleanPhone)) {
				showFieldError(phoneInput, phoneError, params.i18n.phone_invalid);
				isValid = false;
				firstInvalid = firstInvalid || phoneInput;
			}

			// Name Validation
			var nameVal = nameInput ? nameInput.value.trim() : '';
			if (!nameVal || nameVal.length < 2) {
				showFieldError(nameInput, nameError, params.i18n.name_required);
				isValid = false;
				firstInvalid = firstInvalid || nameInput;
			}

			// City Validation
			var cityVal = citySelect ? citySelect.value.trim() : '';
			if (!cityVal) {
				showFieldError(citySelect, cityError, params.i18n.city_required);
				isValid = false;
				firstInvalid = firstInvalid || citySelect;
			}

			// Address Validation
			var addrVal = addressInput ? addressInput.value.trim() : '';
			if (!addrVal || addrVal.length < 3) {
				showFieldError(addressInput, addressError, params.i18n.addr_required);
				isValid = false;
				firstInvalid = firstInvalid || addressInput;
			}

			// Variable Product Selection Check
			if (isOptionSelectionRequired()) {
				if (errorNotice) {
					errorNotice.textContent = params.i18n.select_options;
					errorNotice.style.display = 'block';
				}
				isValid = false;
			}

			// Shipping Availability Check
			if (!isShippingAvailable) {
				if (shippingNotice) {
					shippingNotice.textContent = 'Delivery is not available for the selected location.';
					shippingNotice.style.display = 'block';
				}
				isValid = false;
				firstInvalid = firstInvalid || citySelect;
			}

			if (!isValid && firstInvalid) {
				firstInvalid.focus();
			}

			return isValid;
		}

		if (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				if (!validateForm()) {
					return;
				}

				// Disable button and show spinner to prevent duplicate clicks
				if (submitBtn) {
					submitBtn.disabled = true;
					var spinner = submitBtn.querySelector('.snaporder-submit-spinner');
					if (spinner) spinner.style.display = 'inline-block';
					var submitText = submitBtn.querySelector('.snaporder-submit-text');
					if (submitText) submitText.textContent = params.i18n.submitting;
				}

				// Gather Form Data
				var selectedPayment = 'cod';
				var checkedRadio = form.querySelector('input[name="payment_method"]:checked');
				if (checkedRadio) {
					selectedPayment = checkedRadio.value;
				}

				var payload = new URLSearchParams();
				payload.append('action', 'snaporder_quick_order_submit');
				payload.append('security', params.nonce);
				payload.append('product_id', params.product_id);
				if (currentVariationId > 0) {
					payload.append('variation_id', currentVariationId);
				}
				payload.append('qty', qtyInput ? qtyInput.value : '1');
				payload.append('phone', phoneInput ? phoneInput.value.trim() : '');
				payload.append('name', nameInput ? nameInput.value.trim() : '');
				payload.append('city', citySelect ? citySelect.value.trim() : '');
				payload.append('address', addressInput ? addressInput.value.trim() : '');
				payload.append('payment_method', selectedPayment);
				if (appliedCouponCode) {
					payload.append('coupon_code', appliedCouponCode);
				}
				var addons = getSelectedAddons();
				addons.list.forEach(function (item) {
					payload.append('wca_addons[]', item.id);
				});

				// Add robust 25s timeout handling via AbortController
				var controller = typeof window.AbortController !== 'undefined' ? new AbortController() : null;
				var timeoutId = null;
				if (controller) {
					timeoutId = setTimeout(function () {
						controller.abort();
					}, 25000);
				}

				fetch(params.ajax_url, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: payload.toString(),
					signal: controller ? controller.signal : undefined
				})
				.then(function (res) {
					if (timeoutId) clearTimeout(timeoutId);
					return res.json();
				})
				.then(function (res) {
					if (!res || !res.success) {
						var errMsg = (res && res.data && res.data.message) ? res.data.message : params.i18n.error_occurred;
						if (errorNotice) {
							errorNotice.textContent = errMsg;
							errorNotice.style.display = 'block';
						}
						// Re-enable submit button if shipping is available
						if (submitBtn) {
							submitBtn.disabled = !isShippingAvailable;
							var sp = submitBtn.querySelector('.snaporder-submit-spinner');
							if (sp) sp.style.display = 'none';
							var st = submitBtn.querySelector('.snaporder-submit-text');
							if (st) st.textContent = params.i18n.complete_order;
						}
						return;
					}

					var data = res.data;

					// Redirect every successful order to WooCommerce's secure next step.
					// Online orders go to payment; COD orders go to order-received,
					// where the canonical conversion tracker owns the event.
					if (data.redirect_url) {
						if (submitBtn) {
							var stOnline = submitBtn.querySelector('.snaporder-submit-text');
							if (stOnline) stOnline.textContent = data.payment_type === 'online' ? 'Redirecting to payment...' : 'Order placed. Redirecting...';
						}
						window.location.assign(data.redirect_url);
					} else {
						// Backward-compatible fallback for a stale response without redirect_url.
						if (typeof window.fbq === 'function') {
							try {
								window.fbq('track', 'Lead', {
									content_name: data.product_name,
									content_category: 'COD Order',
									value: parseFloat(data.total),
									currency: data.currency || 'AED'
								}, { eventID: 'cod_' + String(data.order_id) });
							} catch (pixelErr) {}
						}

						// Push via canonical SnapOrder Track Event dispatcher
						if (typeof window.snaporderTrackEvent === 'function') {
							window.snaporderTrackEvent('generate_lead', {
								transaction_id: String(data.order_id),
								value: parseFloat(data.total),
								currency: data.currency || 'AED',
								items: [{
									item_id: String(currentVariationId || data.product_id),
									item_name: data.product_name,
									item_brand: data.product_brand || params.product_brand || '',
									item_category: data.product_category || params.product_cat || '',
									item_variant: currentVariationLabel || '',
									price: parseFloat(data.unit_price),
									quantity: parseInt(data.qty, 10)
								}]
							}, {
								lead_type: 'cod_order',
								order_source: 'quick_order',
								transaction_id: String(data.order_id)
							});
						}

						// COD Success Confirmation State
						form.style.display = 'none';
						if (successContainer) {
							successContainer.innerHTML = data.html_success || '';
							successContainer.style.display = 'block';

							var doneBtn = document.getElementById('snaporder-success-done-btn');
							if (doneBtn) {
								doneBtn.addEventListener('click', function () {
									closeModal();
									// Reset form for next view
									form.reset();
									form.style.display = 'flex';
									successContainer.style.display = 'none';
									appliedCouponCode = '';
									appliedDiscount = 0;
									if (hiddenCouponCode) hiddenCouponCode.value = '';
									if (appliedCouponPill) appliedCouponPill.style.display = 'none';
									if (couponToggle) couponToggle.style.display = 'inline-flex';
									if (summaryDiscountRow) summaryDiscountRow.style.display = 'none';
									if (submitBtn) {
										submitBtn.disabled = false;
										var spReset = submitBtn.querySelector('.snaporder-submit-spinner');
										if (spReset) spReset.style.display = 'none';
										var stReset = submitBtn.querySelector('.snaporder-submit-text');
										if (stReset) stReset.textContent = params.i18n.complete_order;
									}
								});
							}
						}
					}
				})
				.catch(function (err) {
					if (timeoutId) clearTimeout(timeoutId);
					var isTimeout = (err && err.name === 'AbortError');
					var errMsg = isTimeout
						? (params.i18n.timeout_error || 'Payment request timed out. Please try again.')
						: (params.i18n.payment_failed || params.i18n.error_occurred);

					if (errorNotice) {
						errorNotice.textContent = errMsg;
						errorNotice.style.display = 'block';
					}
					if (submitBtn) {
						submitBtn.disabled = !isShippingAvailable;
						var spErr = submitBtn.querySelector('.snaporder-submit-spinner');
						if (spErr) spErr.style.display = 'none';
						var stErr = submitBtn.querySelector('.snaporder-submit-text');
						if (stErr) stErr.textContent = params.i18n.complete_order;
					}
				});
			});
		}

		// 8. Real-time Add-on Checkbox & Event Listeners
		document.addEventListener('change', function (e) {
			if (e.target && e.target.classList && e.target.classList.contains('wca-addon-checkbox')) {
				updateModalProductDetails();
				updateLiveTotal();
			}
		});

		if (window.jQuery) {
			window.jQuery(document).on('wca_addons_changed', function () {
				updateModalProductDetails();
				updateLiveTotal();
			});
		}
	});
})();

