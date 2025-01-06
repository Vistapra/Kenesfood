	const OrderManager = {

		state: {
			cartCount: 0,
			lastUpdate: null,
			retryAttempts: 0,
			maxRetries: 3,
			retryDelay: 1000,
			initialized: false
		},
			config: {
				endpoints: {
					// Endpoint untuk operasi keranjang
					CART_DETAILS: '/order/cart', 
					CART_COUNT: '/order/countCart',
					ADD_TO_CART: '/order/add',
					REMOVE_CART_ITEM: '/order/removeCartItem', 
					PROCESS_ORDER: '/order/doneOrder'
				},
				selectors: {
					// Selector kunci untuk modal dan komponen
					cartModal: '#cart-modal',
					cartButton: '#show-cart', 
					cartCountBadge: '#count-cart',
					orderButton: '#order',
					productSearch: '#product-search',
					categorySelect: '#category-select'
				},
				api: {
					// Konfigurasi tambahan untuk API
					baseUrl: window.location.origin,
					timeout: 5000,
					headers: {
						'Content-Type': 'application/json',
						'X-Requested-With': 'XMLHttpRequest'
					}
				},
				messages: {
					// Pesan standar untuk berbagai situasi
					cartEmpty: 'Keranjang Anda kosong',
					orderSuccess: 'Pesanan berhasil diproses',
					orderError: 'Gagal memproses pesanan'
				},
				validation: {
					// Aturan validasi
					minOrderQuantity: 1,
					maxOrderQuantity: 10
				}
			},

			getUrlParams() {
				const params = new URLSearchParams(window.location.search);
				return {
					outletId: params.get('outletId'),
					tableId: params.get('tableId'),
					brand: params.get('brand')
				};
			},		
		
			validateInitialization() {
				// Tambahkan logika dinamis untuk membuat kategori
				const $productCards = $('.product-card');
				const categoriesMap = new Map();
			
				$productCards.each(function() {
					const $card = $(this);
					const categoryId = $card.data('category-id');
					const categoryName = $card.data('category-name') || 'Uncategorized';
			
					if (!categoriesMap.has(categoryId)) {
						categoriesMap.set(categoryId, {
							id: categoryId,
							name: categoryName,
							products: []
						});
					}
					categoriesMap.get(categoryId).products.push($card);
				});
			
				// Render kategori dinamis
				categoriesMap.forEach((category) => {
					const $categorySection = $(`
						<div class="product-category" 
							data-category-id="${category.id}" 
							data-category-name="${category.name}">
							<h3 class="category-title">${category.name}</h3>
							<div class="category-products"></div>
						</div>
					`);
			
					category.products.forEach($product => {
						$categorySection.find('.category-products').append($product);
					});
			
					$('#product-listing').append($categorySection);
				});
			
				console.log('Dynamic Categories Created:', categoriesMap.size);
				return true;
			},

		showLoading(show = true) {
			if (show) {
				Swal.showLoading();
			} else {
				Swal.close();
			}
		},

		init() {
			console.group('OrderManager Initialization')
			try {
				// Validate initialization first
				if (!this.validateInitialization()) {
					throw new Error('Initialization validation failed')
				}

				// Setup event listeners
				this.setupEventListeners()

				// Initialize cart
				this.initializeCart()

				// Mark as initialized
				this.state.initialized = true

				console.log('OrderManager initialized successfully')
			} catch (error) {
				console.error('Initialization failed:', error)
				this.handleInitError(error)
			} finally {
				console.groupEnd()
			}
		},

		validateInitialization() {
			try {
				const requiredElements = {
					categorySelect: {
						element: $('#category-select'),
						errorMsg: 'Category selection dropdown not found'
					},
					productSearch: {
						element: $('#product-search'),
						errorMsg: 'Product search input not found'
					},
					// Cart Elements
					cartButton: {
						element: $('#show-cart'),
						errorMsg: 'Cart button not found'
					},
					cartCountBadge: {
						element: $('#count-cart'),
						errorMsg: 'Cart count badge not found'
					},
					// Product Listing Elements
					productListing: {
						element: $('#product-listing'),
						errorMsg: 'Product listing container not found'
					},
					// Modal Elements
					cartModal: {
						element: $('#cart-modal'),
						errorMsg: 'Cart modal not found'
					},
					orderButton: {
						element: $('#order'),
						errorMsg: 'Order button not found'
					}
				}

				// Validasi elemen dan kumpulkan error
				const missingElements = []
				for (const [key, config] of Object.entries(requiredElements)) {
					if (config.element.length === 0) {
						missingElements.push({
							element: key,
							message: config.errorMsg
						})
					}
				}

				// Jika ada elemen yang hilang, throw error
				if (missingElements.length > 0) {
					throw new Error(
						'Missing required elements:\n' +
						missingElements.map(e => `- ${e.message}`).join('\n')
					)
				}

				// 2. Validasi URL Parameters
				const params = new URLSearchParams(window.location.search)
				const requiredParams = {
					outletId: params.get('outletId'),
					tableId: params.get('tableId'),
					brand: params.get('brand')
				}

				const missingParams = Object.entries(requiredParams)
					.filter(([_, value]) => !value)
					.map(([key]) => key)

				if (missingParams.length > 0) {
					throw new Error(
						'Missing required URL parameters: ' + missingParams.join(', ')
					)
				}

				// 3. Validasi keberadaan produk
				const productCards = $('.product-card')
				const productCategories = $('.product-category')

				console.log('Validation Status:', {
					productCardsCount: productCards.length,
					categoriesCount: productCategories.length
				})

				// 4. Validasi Modal dan Komponen Kunci
				const modalValidation = {
					modalExists: $('#productModal').length > 0,
					regularContent: $('#regular-product-content').length > 0,
					packageContent: $('#package-product-content').length > 0,
					regularCartBtn: $('#add-to-cart-regular').length > 0,
					packageCartBtn: $('#add-to-cart-package').length > 0
				}

				const missingModalComponents = Object.entries(modalValidation)
					.filter(([_, exists]) => !exists)
					.map(([key]) => key)

				if (missingModalComponents.length > 0) {
					throw new Error(
						'Missing modal components: ' + missingModalComponents.join(', ')
					)
				}

				// 5. Validasi State Awal
				if (typeof this.state !== 'object') {
					throw new Error('Invalid state initialization')
				}

				// 6. Validasi Methods yang Diperlukan
				const requiredMethods = [
					'setupEventListeners',
					'initializeCart',
					'filterProducts',
					'searchProducts',
					'loadCart',
					'updateCartCount'
				]

				const missingMethods = requiredMethods.filter(
					method => typeof this[method] !== 'function'
				)

				if (missingMethods.length > 0) {
					throw new Error(
						'Missing required methods: ' + missingMethods.join(', ')
					)
				}

				// Log successful validation
				console.log('Initialization validation completed successfully', {
					elementsValidated: Object.keys(requiredElements).length,
					productsFound: productCards.length,
					categoriesFound: productCategories.length,
					urlParameters: requiredParams,
					modalComponents: modalValidation
				})

				return true
			} catch (error) {
				console.error('Initialization validation failed:', error)

				// Tampilkan error ke user
				Swal.fire({
					title: 'Initialization Error',
					text: error.message,
					icon: 'error',
					confirmButtonText: 'Refresh Page',
					showCancelButton: true,
					cancelButtonText: 'Continue Anyway'
				}).then(result => {
					if (result.isConfirmed) {
						window.location.reload()
					}
				})

				return false
			}
		},

		handleInitError(error) {
			// Log error details
			console.error('Detailed initialization error:', {
				message: error.message,
				stack: error.stack,
				state: this.state
			});

			// Show user-friendly error message
			Swal.fire({
				title: 'System Error',
				text: 'Failed to initialize the ordering system. Please refresh the page.',
				icon: 'error',
				confirmButtonText: 'Refresh Page',
				allowOutsideClick: false
			}).then(() => {
				window.location.reload();
			});
		},

		setupEventListeners() {
			// Category and search handlers
			$('#category-select').on('change', e => {
				const categoryId = $(e.target).val()
				this.filterProducts(categoryId)
			})

			$('#product-search').on('input', e => {
				const searchTerm = $(e.target).val().toLowerCase()
				this.searchProducts(searchTerm)
			})

			// Cart button handler
			$('#show-cart').on('click', () => {
				this.loadCart()
			})

			// Cart removal handler
			$(document).on('click', '.remove-cart-item', e => {
				e.preventDefault()
				const $btn = $(e.currentTarget)
				const productId = $btn.data('product-id')
				const count = $btn.data('count') || 1
				this.removeCartItem(productId, count)
			})

			// Order button handler
			$('#order').on('click', () => {
				this.processOrder()
			})

			// Setup periodic cart check
			this.setupCartRefresh()
		},

		async initializeCart() {
			try {
				await this.updateCartCount(true)
				this.state.initialized = true
			} catch (error) {
				console.error('Failed to initialize cart:', error)
				// Retry initialization after delay
				setTimeout(() => {
					if (!this.state.initialized) {
						this.initializeCart()
					}
				}, 2000)
			}
		},

		setupCartRefresh() {
			// Refresh cart count every minute
			setInterval(() => {
				if (this.state.initialized) {
					this.updateCartCount()
				}
			}, 60000)
		},

		async fetchWithRetry(url, options = {}, attempt = 0) {
			try {
				const response = await $.ajax({
					url,
					...options,
					timeout: 5000
				})

				// Check if response has the expected structure
				if (typeof response === 'object' && 'success' in response) {
					return response
				}

				throw new Error('Invalid response format')
			} catch (error) {
				console.warn(`Request failed (attempt ${attempt + 1}):`, error)

				const maxRetries = this.state.maxRetries
				if (attempt < maxRetries) {
					const delay = this.state.retryDelay * Math.pow(2, attempt)

					await new Promise(resolve => setTimeout(resolve, delay))
					return this.fetchWithRetry(url, options, attempt + 1)
				}

				// On final failure, check error type
				if (error.status === 500) {
					const errorMessage = this.parseServerError(error)
					throw new Error(errorMessage || 'Internal server error')
				}

				throw error
			}
		},

		parseServerError(error) {
			try {
				const responseText = error.responseText

				// Try to extract error message from PHP error output
				if (responseText.includes('Message:')) {
					const messageMatch = responseText.match(/Message:\s+(.*?)\n/)
					if (messageMatch && messageMatch[1]) {
						return messageMatch[1].trim()
					}
				}

				// Try to parse as JSON
				const jsonResponse = JSON.parse(responseText)
				if (jsonResponse.message) {
					return jsonResponse.message
				}

				return null
			} catch (e) {
				console.error('Error parsing server error:', e)
				return null
			}
		},

		async updateCartCount(isInitial = false) {
			if (!this.state.initialized && !isInitial) {
				return
			}

			try {
				const params = new URLSearchParams(window.location.search)

				const response = await this.fetchWithRetry(
					`${window.location.origin}/order/countCart`,
					{
						method: 'GET',
						data: params.toString(),
						headers: {
							'X-Requested-With': 'XMLHttpRequest',
							'Cache-Control': 'no-cache'
						}
					}
				)

				if (response.success) {
					const cartCount = response.data.metrics.total_items
					this.updateCartUI(cartCount)

					// Update session info if available
					if (response.data.session) {
						this.handleSessionUpdate(response.data.session)
					}

					this.state.cartCount = cartCount
					this.state.lastUpdate = new Date()
					return true
				}

				throw new Error(response.message || 'Failed to update cart')
			} catch (error) {
				console.error('Error updating cart count:', error)

				if (!isInitial) {
					this.handleCartError(error)
				}

				// Return false but don't show error for initial silent check
				return false
			}
		},

		updateCartUI(count) {
			const $badge = $('#count-cart')
			const currentCount = parseInt($badge.text()) || 0

			if (currentCount !== count) {
				$badge.text(count)

				// Animate badge if count increased
				if (count > currentCount) {
					$badge.addClass('badge-pop')
					setTimeout(() => $badge.removeClass('badge-pop'), 300)
				}
			}
		},

		handleCartError(error) {
			// Check specific error conditions
			if (!navigator.onLine) {
				this.showToast('warning', 'Network connection lost. Retrying...')
				return
			}

			if (error.status === 401 || error.status === 403) {
				this.handleSessionExpired()
				return
			}

			if (error.status === 500) {
				console.error('Server Error Details:', error.responseText)
				this.showToast('error', 'Server error. Please try again later.')
				return
			}

			// Generic error handler
			this.showToast('error', error.message || 'Failed to update cart')
		},

		handleSessionExpired() {
			Swal.fire({
				title: 'Session Expired',
				text: 'Your session has expired. The page will reload.',
				icon: 'warning',
				confirmButtonText: 'Reload Now',
				allowOutsideClick: false
			}).then(() => {
				window.location.reload()
			})
		},

		handleSessionUpdate(sessionData) {
			// Check session expiration
			const expireTime = new Date(sessionData.expire_at)
			const currentTime = new Date()
			const timeLeft = (expireTime - currentTime) / 1000 / 60 // minutes

			if (timeLeft <= 5) {
				this.showToast(
					'warning',
					`Session expires in ${Math.ceil(timeLeft)} minutes`
				)
			}
		},

		showToast(icon, message) {
			Swal.fire({
				text: message,
				icon: icon,
				toast: true,
				position: 'top-end',
				showConfirmButton: false,
				timer: 3000
			})
		},

		async loadCart() {
			try {
				this.showLoading(true);
				const params = this.getUrlParams();
				
				console.log('Loading cart with params:', params);

				const response = await this.fetchWithRetry(
					`${window.location.origin}${this.config.endpoints.CART_DETAILS}`,
					{
						method: 'GET',
						data: params
					}
				);

				if (response.success) {
					await this.renderCart(response.data);
					const $modal = $(this.config.selectors.cartModal);
					$modal.modal('show');

					// Fix accessibility
					setTimeout(() => {
						$modal.find('.modal-content').focus();
					}, 100);
				} else {
					throw new Error(response.message || 'Failed to load cart');
				}
			} catch (error) {
				console.error('Cart Loading Error:', error);
				this.handleCartError(error);
			} finally {
				this.showLoading(false);
			}
		},
		
		renderCart(data) {
			const $container = $('#container-cart')

			if (!data.cart || !data.cart.items || data.cart.items.length === 0) {
				this.renderEmptyCart($container)
				return
			}

			this.renderCartItems($container, data)
		},

		renderEmptyCart($container) {
			$container.html(`
				<div class="text-center py-5">
					<i class="bi bi-cart-x fs-1 text-muted"></i>
					<p class="mt-3">Your cart is empty</p>
				</div>
			`)
			$('#order').prop('disabled', true)
		},

		renderCartItems($container, data) {
			let cartHtml = this.generateCartHeader()

			data.cart.items.forEach(item => {
				cartHtml += this.generateCartItemRow(item)
			})

			cartHtml += this.generateCartFooter(data.cart)

			$container.html(cartHtml)
			this.updateCartSummary(data.cart)
			$('#order').prop('disabled', false)
		},

		generateCartHeader() {
			return `
				<div class="cart-items">
					<div class="table-responsive">
						<table class="table table-hover">
							<thead>
								<tr>
									<th>Product</th>
									<th class="text-center">Qty</th>
									<th class="text-end">Price</th>
									<th class="text-end">Subtotal</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
			`
		},

		generateCartItemRow(item) {
			const imageUrl = `${window.location.origin}/resource/assets-frontend/dist/product/${item.product_image}`

			return `
				<tr>
					<td>
						<div class="d-flex align-items-center">
							<img src="${imageUrl}" 
								class="rounded me-2" 
								style="width: 50px; height: 50px; object-fit: cover;"
								alt="${item.product_name}">
							<div>
								<h6 class="mb-0">${item.product_name}</h6>
								${item.notes
					? `<small class="text-muted">
										<i class="bi bi-pencil-square me-1"></i>${item.notes}
									</small>`
					: ''
				}
							</div>
						</div>
						${item.is_package
					? this.generatePackageDetails(item.package_items)
					: ''
				}
					</td>
					<td class="text-center">${item.quantity}</td>
					<td class="text-end">${this.formatPrice(item.unit_price)}</td>
					<td class="text-end">
						${this.formatPrice(
					item.is_package
						? item.package_total * item.quantity
						: item.subtotal
				)}
					</td>
					<td class="text-end">
						<button class="btn btn-sm btn-outline-danger remove-cart-item"
								data-product-id="${item.product_id}"
								data-count="1">
							<i class="bi bi-trash"></i>
						</button>
					</td>
				</tr>
			`
		},

		generatePackageDetails(items) {
			if (!items || items.length === 0) return ''

			return `
				<div class="package-items mt-2">
					<small class="d-block text-muted mb-1">Package Contents:</small>
					${items
					.map(
						item => `
						<div class="package-item-detail ms-3">
							<small class="text-muted">
								${item.quantity}x ${item.product_name}
								${item.notes
								? `<br><i class="bi bi-pencil-square me-1"></i>${item.notes}`
								: ''
							}
							</small>
						</div>
					`
					)
					.join('')}
				</div>
			`
		},

		generateCartFooter(cart) {
			return `
						</tbody>
						<tfoot>
							<tr>
								<td colspan="3" class="text-end fw-bold">Total</td>
								<td class="text-end fw-bold">
									${this.formatPrice(cart.total_amount)}
								</td>
								<td></td>
							</tr>
						</tfoot>
					</table>
				</div>
			`
		},

		updateCartSummary(cart) {
			$('#cart-total-items').text(cart.total_items)
			$('#cart-total-amount').text(this.formatPrice(cart.total_amount))
		},

		async removeCartItem(productId, count) {
			try {
				const params = new URLSearchParams(window.location.search)

				const confirmResult = await Swal.fire({
					title: 'Remove Item?',
					text: 'This item will be removed from your cart',
					icon: 'warning',
					showCancelButton: true,
					confirmButtonText: 'Yes, Remove',
					cancelButtonText: 'Cancel',
					reverseButtons: true
				})

				if (!confirmResult.isConfirmed) return

				Swal.showLoading()

				const response = await this.fetchWithRetry(
					`${window.location.origin}/order/removeCartItem`,
					{
						method: 'POST',
						contentType: 'application/json',
						data: JSON.stringify({
							outletId: params.get('outletId'),
							tableId: params.get('tableId'),
							brand: params.get('brand'),
							productId: productId,
							count: count
						})
					}
				)

				if (response.success) {
					await this.loadCart()
					await this.updateCartCount()
					Swal.fire('Success', 'Item removed successfully', 'success')
				} else {
					throw new Error(response.message)
				}
			} catch (error) {
				console.error('Error removing cart item:', error)
				Swal.fire('Error', error.message || 'Failed to remove item', 'error')
			}
		},

		async processOrder() {
			try {
				const params = new URLSearchParams(window.location.search)

				const confirmResult = await Swal.fire({
					title: 'Process Order?',
					text: 'Your order will be processed and cannot be cancelled',
					icon: 'question',
					showCancelButton: true,
					confirmButtonText: 'Yes, Process',
					cancelButtonText: 'Cancel',
					reverseButtons: true
				})

				if (!confirmResult.isConfirmed) return

				Swal.showLoading()

				const response = await this.fetchWithRetry(
					`${window.location.origin}/order/doneOrder`,
					{
						method: 'POST',
						contentType: 'application/json',
						data: JSON.stringify({
							outletId: params.get('outletId'),
							tableId: params.get('tableId'),
							brand: params.get('brand')
						})
					}
				)

				if (response.success) {
					$('#cart-modal').modal('hide')

					await Swal.fire({
						title: 'Order Success!',
						html: this.generateOrderConfirmation(response.data),
						icon: 'success',
						confirmButtonText: 'OK'
					})

					window.location.reload()
				} else {
					throw new Error(response.message)
				}
			} catch (error) {
				console.error('Error processing order:', error)
				Swal.fire('Error', error.message || 'Failed to process order', 'error')
			}
		},

		generateOrderConfirmation(data) {
			return `
				<div class="text-start">
					<p><strong>Order Number:</strong> ${data.receipt_number}</p>
					<p><strong>Total Items:</strong> ${data.summary.total_items}</p>
					<p><strong>Total Payment:</strong> ${this.formatPrice(
				data.summary.total_amount
			)}</p>
				</div>
			`
		},

		filterProducts(categoryId) {
			console.log('Filtering products:', {
				categoryId,
				totalProducts: $('.product-card').length,
				visibleBefore: $('.product-card:visible').length
			});

			if (categoryId === 'all') {
				$('.product-category').show();
				$('.product-card').show();
			} else {
				$('.product-category').hide();
				$('.product-card').each(function() {
					const $card = $(this);
					const cardCategoryId = $card.data('category-id');
					const shouldShow = cardCategoryId === parseInt(categoryId);
					$card.toggle(shouldShow);
					if (shouldShow) {
						$card.closest('.product-category').show();
					}
				});
			}

			console.log('Visible after:', $('.product-card:visible').length);
			this.toggleNoResults();
		},

		searchProducts(searchTerm) {
			$('.product-card').each(function () {
				const $card = $(this)
				const productName = $card.find('.product-name').text().toLowerCase()
				const description = $card
					.find('.product-description')
					.text()
					.toLowerCase()

				// Search in product name and description
				const matches =
					productName.includes(searchTerm) || description.includes(searchTerm)

				$card.toggle(matches)

				// Show/hide category headers based on visible products
				const $category = $card.closest('.product-category')
				const hasVisibleProducts =
					$category.find('.product-card:visible').length > 0
				$category.toggle(hasVisibleProducts)
			})

			// Show no results message if needed
			this.toggleNoResults()
		},

		toggleNoResults() {
			const hasVisibleProducts = $('.product-card:visible').length > 0
			const $noResults = $('#no-results-message')

			if (!hasVisibleProducts && $noResults.length === 0) {
				$('.product-listing').append(`
					<div id="no-results-message" class="col-12 text-center py-5">
						<div class="alert alert-info">
							<i class="bi bi-search me-2"></i>No products found
						</div>
					</div>
				`)
			} else if (hasVisibleProducts) {
				$noResults.remove()
			}
		},

		formatPrice(amount) {
			return new Intl.NumberFormat('id-ID', {
				style: 'currency',
				currency: 'IDR',
				minimumFractionDigits: 0,
				maximumFractionDigits: 0
			}).format(amount)
		},

		// Error Handling Methods
		handleError(error, context = '') {
			console.error(`Error in ${context}:`, error)

			// Check for network errors
			if (!navigator.onLine) {
				this.showErrorMessage(
					'Network Error',
					'Please check your internet connection and try again'
				)
				return
			}

			// Check for session errors
			if (error.status === 401 || error.status === 403) {
				this.handleSessionError()
				return
			}

			// Handle server errors
			if (error.status === 500) {
				this.handleServerError(error)
				return
			}

			// Default error handling
			this.showErrorMessage(
				'Error',
				error.message || 'An unexpected error occurred'
			)
		},

		handleSessionError() {
			Swal.fire({
				title: 'Session Expired',
				text: 'Your session has expired. The page will reload.',
				icon: 'warning',
				confirmButtonText: 'Reload Now',
				allowOutsideClick: false
			}).then(() => {
				window.location.reload()
			})
		},

		handleServerError(error) {
			console.error('Server Error Details:', error.responseText)

			// Check if we can extract a meaningful error message
			let errorMessage = 'An internal server error occurred'
			try {
				const responseText = error.responseText
				if (responseText.includes('Message:')) {
					errorMessage = responseText.split('Message:')[1].split('\n')[0].trim()
				}
			} catch (e) {
				console.error('Error parsing server error:', e)
			}

			this.showErrorMessage('Server Error', errorMessage)
		},

		showErrorMessage(title, message) {
			Swal.fire({
				title: title,
				text: message,
				icon: 'error',
				confirmButtonText: 'OK'
			})
		},

		// Cache Management Methods
		getCacheKey(type, params = {}) {
			return `order_${type}_${JSON.stringify(params)}`
		},

		async getCachedData(key) {
			try {
				const cached = localStorage.getItem(key)
				if (!cached) return null

				const { data, timestamp } = JSON.parse(cached)
				const now = Date.now()

				// Cache expires after 5 minutes
				if (now - timestamp > 5 * 60 * 1000) {
					localStorage.removeItem(key)
					return null
				}

				return data
			} catch (error) {
				console.error('Cache retrieval error:', error)
				return null
			}
		},

		setCacheData(key, data) {
			try {
				const cacheData = {
					data: data,
					timestamp: Date.now()
				}
				localStorage.setItem(key, JSON.stringify(cacheData))
			} catch (error) {
				console.error('Cache storage error:', error)
				// Clear localStorage if it's full
				if (error.name === 'QuotaExceededError') {
					localStorage.clear()
				}
			}
		},

		clearCache() {
			Object.keys(localStorage).forEach(key => {
				if (key.startsWith('order_')) {
					localStorage.removeItem(key)
				}
			})
		},

		// Performance Optimization Methods
		debounce(func, wait) {
			let timeout
			return function executedFunction(...args) {
				const later = () => {
					clearTimeout(timeout)
					func(...args)
				}
				clearTimeout(timeout)
				timeout = setTimeout(later, wait)
			}
		},

		throttle(func, limit) {
			let inThrottle
			return function executedFunction(...args) {
				if (!inThrottle) {
					func(...args)
					inThrottle = true
					setTimeout(() => (inThrottle = false), limit)
				}
			}
		},

		// Lifecycle Methods
		destroy() {
			// Clean up event listeners
			$('#category-select').off('change')
			$('#product-search').off('input')
			$('#show-cart').off('click')
			$(document).off('click', '.remove-cart-item')
			$('#order').off('click')

			// Clear intervals/timeouts
			if (this.cartRefreshInterval) {
				clearInterval(this.cartRefreshInterval)
			}

			// Clear cache if needed
			this.clearCache()

			// Reset state
			this.state = {
				cartCount: 0,
				lastUpdate: null,
				retryAttempts: 0,
				maxRetries: 3,
				retryDelay: 1000,
				initialized: false
			}
		}
	}

	// Initialize with error handling
	$(document).ready(() => {
		try {
			if (OrderManager.validateInitialization()) {
				OrderManager.init();
				console.log('Application started successfully');
			}
		} catch (error) {
			console.error('Failed to start application:', error);
		}
	});