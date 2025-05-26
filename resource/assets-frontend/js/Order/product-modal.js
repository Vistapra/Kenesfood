function debounce (func, wait) {
  let timeout
  return function executedFunction (...args) {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

const ProductModal = {
  state: {
    debug: true,
    currentProduct: null,
    quantity: 1,
    notes: '',
    isProcessing: false,
    packageData: {
      baseInfo: {
        id: null,
        name: '',
        description: '',
        image: '',
        basePrice: 0
      },
      categories: [],
      selectedProducts: new Map(),
      currentCategory: null,
      customPrices: {},
      excludedProducts: [],
      requirements: {},
      initialized: false,
      validationErrors: [],
      lastValidated: null
    },
    validation: {
      isValid: false,
      messages: [],
      stockValid: true,
      categoryValid: true
    },
    ui: {
      loading: false,
      activeCategory: null,
      modalVisible: false
    }
  },

  // DOM Selectors
  selectors: {
    // Modal elements
    modal: '#productModal',
    modalContent: '.modal-content',
    regularContent: '#regular-product-content',
    packageContent: '#package-product-content',

    // Regular product elements
    productImage: '#modal-product-image',
    productName: '#modal-product-name',
    productDesc: '#modal-product-description',
    productPrice: '#modal-product-price',
    productStock: '#modal-product-stock',
    quantityInput: '.product-qty',
    noteInput: '#product-note',
    subtotalDisplay: '#product-subtotal',

    // Package elements
    packageImage: '#modal-package-image',
    packageDesc: '#modal-package-description',
    packageBasePrice: '#modal-package-base-price',
    packageCategories: '#package-categories',
    packageProducts: '#package-products-grid',
    packageSummary: '#package-summary',
    packageTotal: '#package-total',
    packageProductCard: '.package-product-card',

    // Action buttons
    addToCartRegular: '#add-to-cart-regular',
    addToCartPackage: '#add-to-cart-package',

    // External elements
    productCard: '.product-card',
    cartCountBadge: '#count-cart'
  },

  // Templates
  templates: {
    regularModal: `
	  <div class="modal-dialog modal-lg modal-dialog-centered">
		<div class="modal-content rounded-4 border-0 bg-white shadow-lg overflow-hidden">
		  <!-- Header -->
		  <div class="modal-header border-0 bg-gradient-primary p-4">
			<h5 class="modal-title d-flex align-items-center gap-2 text-white mb-0">
			  <i class="fa-solid fa-bag-shopping fs-4"></i>
			  <span id="modal-product-name"></span>
			</h5>
			<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
		  </div>
		  <!-- Body -->
		  <div class="modal-body p-0">
			<div class="row g-0">
			  <!-- Product Image Column -->
			  <div class="col-lg-5 position-relative">
				<div class="product-preview h-100">
				  <!-- Main Image -->
				  <div class="product-image-wrapper position-relative h-100">
					<img id="modal-product-image" class="img-cover rounded-0" alt="">
					<!-- Stock Badge -->
					<div class="stock-indicator position-absolute top-0 end-0 m-4">
					  <div class="stock-badge bg-white rounded-pill shadow-sm px-3 py-2">
						<div class="d-flex align-items-center gap-2">
						  <i class="fa-solid fa-box text-primary"></i>
						  <span class="fw-medium">Stok: <span id="modal-product-stock" class="text-primary"></span></span>
						</div>
					  </div>
					</div>
				  </div>
				</div>
			  </div>
			  <!-- Product Details Column -->
			  <div class="col-lg-7">
				<div class="product-details p-4 p-lg-5">
				  <!-- Description Section -->
				  <div class="product-description mb-4">
					<span class="badge bg-primary bg-opacity-10 text-white mb-3 px-3 py-2 rounded-pill">Deskripsi Produk</span>
					<p id="modal-product-description" class="text-gray-300 mb-0 product-desc"></p>
				  </div>
				  <!-- Price Section -->
				  <div class="price-section mb-4 py-4 border-top border-bottom">
					<div class="d-flex justify-content-between align-items-center">
					  <span class="text-uppercase small fw-medium text-gray-300">Harga Menu</span>
					  <h3 id="modal-product-price" class="display-6 fw-bold text-primary mb-0 price-value"></h3>
					</div>
				  </div>
				  <!-- Quantity Section -->
				  <div class="quantity-section mb-4">
					<label class="text-uppercase small fw-medium text-gray-300 mb-3">Jumlah Pesanan</label>
					<div class="quantity-control d-inline-flex align-items-center bg-light rounded-pill p-2">
					  <!-- PERBAIKAN: Tambahkan data-item-id yang berisi placeholder -->
					  <button class="btn btn-icon btn-lg btn-primary decrease-qty" data-item-id="">
						<i class="fa fa-minus"></i>
					  </button>
					  <input type="number" class="form-control form-control-lg bg-transparent border-0 text-center product-qty mx-2" 
							 value="1" min="1" data-item-id="" style="width: 80px">
					  <button class="btn btn-icon btn-lg btn-primary increase-qty" data-item-id="">
						<i class="fa fa-plus"></i>
					  </button>
					</div>
				  </div>
				  <!-- Notes Section -->
				  <div class="notes-section mb-4">
					<label class="text-uppercase small fw-medium text-gray-300 mb-3">Catatan Khusus</label>
					<div class="notes-input-wrapper">
					  <textarea id="product-note" class="form-control bg-light border-0" rows="3" placeholder="Tambahkan catatan untuk pesanan Anda..."></textarea>
					</div>
				  </div>
				  <!-- Order Summary -->
				  <div class="order-summary mt-auto">
					<div class="subtotal-section py-4 border-top">
					  <div class="d-flex justify-content-between align-items-center">
						<span class="text-uppercase fw-medium">Total Harga</span>
						<h3 id="product-subtotal" class="display-6 fw-bold text-primary mb-0"></h3>
					  </div>
					</div>
					<!-- Add to Cart Button -->
					<button id="add-to-cart-regular" class="btn btn-primary btn-lg w-100 py-3 mt-3">
					  <div class="d-flex align-items-center justify-content-center gap-2">
						<i class="fas fa-shopping-cart fs-4"></i>
						<span class="fw-medium">Tambahkan ke Keranjang</span>
					  </div>
					</button>
				  </div>
				</div>
			  </div>
			</div>
		  </div>
		</div>
	  </div>`,

    packageModal: `
							<div class="modal-dialog modal-xl modal-dialog-centered">
							<div class="modal-content border-0 shadow-lg">
								<!-- Header yang lebih modern -->
								<div class="modal-header bg-gradient-primary border-0">
								<h5 class="modal-title text-white" id="package-title">
									<i class="fa-solid fa-box me-2"></i>
									<span id="modal-package-name"></span>
								</h5>
								<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
								</div>
								
								<div class="modal-body p-0">
								<div class="row g-0">
									<!-- Panel Informasi Paket -->
									<div class="col-lg-4 bg-light border-end">
									<div class="package-info p-4">
										<!-- Image Container -->
										<div class="position-relative mb-4">
										<img id="modal-package-image" class="img-fluid rounded-lg shadow-sm w-100" style="object-fit: cover; height: 200px;">
										<div class="position-absolute bottom-0 start-0 w-100 p-3 bg-gradient-dark text-white">
											<h5 class="mb-0" id="modal-package-name"></h5>
										</div>
										</div>
						
										<!-- Package Description -->
										<div class="package-description mb-4">
										<p id="modal-package-description" class="text-muted mb-0"></p>
										</div>
						
										<!-- Base Price Card -->
										<div class="price-card bg-white rounded-lg shadow-sm p-3 mb-4">
										<div class="d-flex justify-content-between align-items-center">
											<span class="text-muted">Harga Dasar</span>
											<h4 id="modal-package-base-price" class="mb-0 text-primary"></h4>
										</div>
										</div>
						
										<!-- Progress Categories -->
										<div class="package-progress">
										<h6 class="text-uppercase mb-3">Progress Pemilihan</h6>
										<div id="package-categories"></div>
										</div>
									</div>
									</div>
						
									<!-- Panel Pemilihan Produk -->
									<div class="col-lg-8">
									<div class="p-4">
										<!-- Category Navigation -->
										<div class="category-navigation mb-4">
										<nav class="nav nav-pills nav-fill" id="categoryTabs" role="tablist"></nav>
										</div>
						
										<!-- Products Grid -->
										<div class="tab-content" id="categoryContent">
										<div class="products-grid row g-3" id="package-products-grid"></div>
										</div>
						
										<!-- Package Summary -->
										<div class="package-summary mt-4">
										<div class="card border-0 shadow-sm">
											<div class="card-body">
											<div class="d-flex justify-content-between align-items-center">
												<div>
												<span class="text-muted">Total Paket</span>
												<h3 id="package-total-price" class="mb-0"></h3>
												</div>
												<button id="add-package-to-cart" class="btn btn-primary btn-lg px-4">
			<i class="fas fa-shopping-cart me-2"></i> Tambah ke Keranjang
		</button>
											</div>
											</div>
										</div>
										</div>
									</div>
									</div>
								</div>
								</div>
							</div>
							</div>`,

    packageProductCard: function (product) {
      const isSelected = this.state.packageData.selectedProducts.has(product.id)
      const isDisabled = product.stock <= 0

      return `
									<div class="col-md-6 col-lg-4">
										<div class="package-product-card h-100 bg-white rounded-lg shadow-hover overflow-hidden" 
											data-product-id="${product.id}">
											<!-- Image Container -->
											<div class="position-relative">
												<img src="${this.getProductImageUrl(product.image)}" 
													class="w-100" style="height: 160px; object-fit: cover;">
												<span class="position-absolute top-0 end-0 m-2 badge bg-light text-dark px-3 py-2">
													<i class="fa-solid fa-box me-1"></i>Stok: ${product.stock}
												</span>
												${
                          product.stock === 0
                            ? `
													<div class="position-absolute inset-0 bg-dark bg-opacity-50 d-flex align-items-center justify-content-center">
														<span class="badge bg-danger px-3 py-2">Habis</span>
													</div>
												`
                            : ''
                        }
											</div>
						
											<!-- Product Info -->
											<div class="p-3">
												<h6 class="product-name mb-2 line-clamp-2">${product.name}</h6>
												<p class="product-desc small text-muted mb-3 line-clamp-2">
													${product.description || ''}
												</p>
						
												<!-- Notes Field -->
												<div class="notes-field mb-3">
													<textarea class="form-control form-control-sm" 
														id="notes-${product.id}"
														placeholder="Catatan khusus..."
														rows="2"
														${isDisabled ? 'disabled' : ''}>${product.notes || ''}</textarea>
													<small class="note-counter text-muted">0/255</small>
												</div>
						
												<div class="d-flex justify-content-between align-items-center mt-auto">
													<span class="product-price fw-bold text-primary">
														${this.formatPrice(product.price)}
													</span>
													
													<div class="quantity-control d-flex align-items-center gap-2">
														<button class="btn btn-sm btn-outline-secondary decrease-package-qty"
															data-product-id="${product.id}"
															${isDisabled ? 'disabled' : ''}>
															<i class="fa fa-minus"></i>
														</button>
														<span class="package-qty w-8 text-center font-medium" 
															data-product-id="${product.id}">
															${isSelected ? product.quantity : 0}
														</span>
														<button class="btn btn-sm btn-outline-secondary increase-package-qty"
															data-product-id="${product.id}"
															${isDisabled ? 'disabled' : ''}>
															<i class="fa fa-plus"></i>
														</button>
													</div>
												</div>
											</div>
										</div>
									</div>
								`
    },

    // 2. Perbaikan untuk categoryProgress juga
    categoryProgress: function (category, progress) {
      return `
								<div class="category-progress mb-3">
								<div class="d-flex justify-content-between align-items-center mb-2">
									<span class="small fw-medium">${category.name}</span>
									<span class="badge bg-primary">${progress}%</span>
								</div>
								
								<div class="progress" style="height: 6px;">
									<div class="progress-bar bg-primary" 
										role="progressbar"
										style="width: ${progress}%" 
										aria-valuenow="${progress}" 
										aria-valuemin="0" 
										aria-valuemax="100">
									</div>
								</div>
								</div>
							`
    },

    // 3. Tambahkan juga utility methods yang dibutuhkan
    utils: {
      getProductImageUrl: function (imageName) {
        return `${
          window.location.origin
        }/resource/assets-frontend/dist/product/${imageName || 'default.png'}`
      },

      formatPrice: function (amount) {
        return new Intl.NumberFormat('id-ID', {
          style: 'currency',
          currency: 'IDR',
          minimumFractionDigits: 0,
          maximumFractionDigits: 0
        }).format(amount)
      }
    }
  },

  init () {
    try {
      console.log('ProductModal initialization starting...')

      // PERBAIKAN: Periksa dengan lebih ketat apakah modal sudah diinisialisasi
      if (window.productModalInitialized === true) {
        console.log(
          'ProductModal already initialized, skipping duplicate initialization'
        )
        return true
      }

      // PERBAIKAN: Periksa elemen session-page dan order-page
      if (!$('#session-page').length) {
        console.error('Session page element (#session-page) not found')
        return false
      }

      // PERBAIKAN: Periksa DOM dengan lebih ketat
      const isOrderPageHidden = $('#order-page').is('[hidden]')
      console.log('Order page hidden state:', isOrderPageHidden)

      // PERBAIKAN: Pastikan ORDER PAGE tersembunyi sesuai status sesi
      const sessionActive = !$('#active-session').is('[hidden]')
      console.log('Active session check (DOM-based):', sessionActive)

      // Jika sesi tidak aktif tapi order page terlihat, sembunyikan
      if (!sessionActive && !isOrderPageHidden) {
        console.warn('Order page visible but no active session, hiding it')
        $('#order-page').attr('hidden', true)
      }

      // Update state
      this.state.sessionActive = sessionActive

      // PERBAIKAN: Jika sesi tidak aktif, register listener dan defer initialization
      if (!sessionActive) {
        console.log('Session not active, deferring initialization')

        // Hapus event listener lama jika ada
        document.removeEventListener(
          'sessionActivated',
          this._sessionActivatedHandler
        )

        // Buat handler baru
        this._sessionActivatedHandler = e => {
          console.log('ProductModal received sessionActivated event', e.detail)
          // Update sessionActive state untuk sinkronisasi
          this.state.sessionActive = true

          if (!this.state.initialized) {
            console.log('Initializing ProductModal after session activation')
            this.validateInitialization()
            this.initializeModal()
            this.bindEvents()
            this.state.initialized = true
          }
        }

        // Tambahkan handler baru
        document.addEventListener(
          'sessionActivated',
          this._sessionActivatedHandler
        )
        return true
      }

      // Jika sesi aktif, lanjutkan inisialisasi
      this.validateInitialization()
      this.initializeModal()
      this.bindEvents()
      this.state.initialized = true

      if (this.state.packageData.initialized) {
        this.autoMoveToIncompleteCategory()
      }

      console.log('ProductModal initialized successfully')

      // PERBAIKAN: Set flag untuk menandai modal sudah diinisialisasi
      window.productModalInitialized = true
      return true
    } catch (error) {
      console.error('ProductModal initialization error:', error)
      this.handleInitError(error)
      return false
    }
  },

  // API Endpoints
  endpoints: {
    getProduct: '/order/getProductDetail',
    getPackage: '/order/getPackageDetail',
    addToCart: '/order/add',
    checkStock: '/order/check-stock'
  },
  // Core Methods
  validateInitialization () {
    const isSessionActive = !$('#active-session').is('[hidden]')
    if (!isSessionActive) {
      throw new Error('Cannot initialize product modal without active session')
    }
    // Validate required elements
    const requiredElements = [
      { key: 'modal', selector: this.selectors.modal },
      { key: 'productName', selector: this.selectors.productName },
      { key: 'productPrice', selector: this.selectors.productPrice },
      { key: 'addToCartRegular', selector: this.selectors.addToCartRegular },
      { key: 'cartCountBadge', selector: this.selectors.cartCountBadge }
    ]

    const missingElements = []
    requiredElements.forEach(({ key, selector }) => {
      if (!document.querySelector(selector)) {
        missingElements.push(`${key} (${selector})`)
      }
    })

    if (missingElements.length > 0) {
      throw new Error(
        `Required elements not found: ${missingElements.join(', ')}`
      )
    }

    // Validate URL parameters
    const requiredParams = ['outletId', 'tableId', 'brand']
    const params = new URLSearchParams(window.location.search)
    const missingParams = requiredParams.filter(param => !params.get(param))

    if (missingParams.length > 0) {
      throw new Error(
        `Missing required parameters: ${missingParams.join(', ')}`
      )
    }
  },

  initializeModal () {
    // PERBAIKAN: Cek apakah modal sudah diinisialisasi sebelumnya dengan lebih ketat
    if (this.modalInstance) {
      console.log(
        'Modal instance already exists, cleaning up before reinitializing'
      )
      // Pastikan instance sebelumnya dibersihkan dengan benar
      try {
        this.modalInstance.dispose()
      } catch (e) {
        console.warn('Error disposing previous modal instance:', e)
      }
    }

    const modalElement = document.querySelector(this.selectors.modal)
    if (!modalElement) {
      console.error('Modal element not found:', this.selectors.modal)
      return
    }

    // Destroy any existing modal instance using jQuery jika perlu
    if ($(modalElement).data('bs.modal')) {
      try {
        $(modalElement).modal('dispose')
        console.log('Disposed existing jQuery modal instance')
      } catch (e) {
        console.warn('Error disposing jQuery modal instance:', e)
      }
    }

    // PERBAIKAN: Bersihkan event handler modal yang ada untuk mencegah duplikasi
    $(modalElement).off('show.bs.modal')
    $(modalElement).off('shown.bs.modal')
    $(modalElement).off('hide.bs.modal')
    $(modalElement).off('hidden.bs.modal')

    // Create new modal instance
    try {
      this.modalInstance = new bootstrap.Modal(modalElement, {
        keyboard: false,
        backdrop: 'static'
      })

      // PERBAIKAN: Simpan reference ke handler untuk pembersihan nanti
      this._modalShowHandler = () => {
        console.log('Product modal showing - ONE TIME ONLY')
        this.handleModalShow()
      }

      this._modalHiddenHandler = () => {
        console.log('Product modal hidden - ONE TIME ONLY')
        this.handleModalHide()
      }

      // Add new event listeners
      $(modalElement).on('show.bs.modal', this._modalShowHandler)
      $(modalElement).on('hidden.bs.modal', this._modalHiddenHandler)

      console.log('Modal initialized successfully')
    } catch (error) {
      console.error('Error initializing modal:', error)
    }
  },

  bindEvents () {
    this.bindProductCardEvents()
    this.bindQuantityEvents()
    this.bindNoteEvents()
    this.bindCartEvents()
    this.bindPackageEvents()
  },

  bindProductCardEvents () {
    // Mengubah selector untuk menggunakan kartu produk secara langsung bukan tombol view-product
    document.querySelectorAll(this.selectors.productCard).forEach(card => {
      // Menambahkan kelas untuk menandakan kartu bisa diklik (jika memenuhi syarat)
      const apiConnected = card.getAttribute('data-api-status') === 'connected'
      const hasStock = !card.querySelector('.badge.bg-danger') // Tidak ada badge "Habis"

      // Hanya kartu dengan API terhubung dan memiliki stok yang bisa diklik
      if (apiConnected && hasStock) {
        card.classList.add('product-card-clickable')

        // Hapus event listener lama jika ada (untuk menghindari duplicate)
        card.removeEventListener('click', this._cardClickHandler)

        // Buat handler untuk click event
        this._cardClickHandler = e => {
          // Jangan trigger jika mengklik elemen yang sudah memiliki event handler sendiri
          if (e.target.closest('.btn') !== null) return

          const productId = card.dataset.productId
          this.openProductModal(productId)
        }

        // Tambahkan event listener ke seluruh kartu
        card.addEventListener('click', this._cardClickHandler)
      } else {
        // Untuk kartu yang tidak bisa diklik, tambahkan kelas yang sesuai
        card.classList.add('product-card-disabled')
      }
    })
  },

  bindQuantityEvents () {
    console.group('🔢 Binding Modal Quantity Events')

    try {
      // PERBAIKAN: Gunakan selector yang lebih spesifik
      const modalId = this.selectors.modal

      // PERBAIKAN: Hapus semua event handler sebelumnya dengan lebih spesifik
      $(document).off('click', `${modalId} .decrease-qty`)
      $(document).off('click', `${modalId} .increase-qty`)
      $(document).off('input', `${modalId} .product-qty`)

      // PERBAIKAN: Simpan handler untuk dibersihkan nanti jika diperlukan
      this._quantityHandlers = {
        decrease: e => {
          e.preventDefault()
          e.stopPropagation()
          console.log('Modal decrease button clicked')

          // Perbarui quantity
          if (this.state.quantity > 1) {
            this.state.quantity--
            // Perbarui UI
            this.updateSubtotal()
            this.updateQuantityButtonStates()

            // Update input value
            const quantityInput = document.querySelector(
              `${modalId} .product-qty`
            )
            if (quantityInput) {
              quantityInput.value = this.state.quantity
              // Animasi untuk feedback visual
              $(quantityInput).addClass('quantity-changed')
              setTimeout(
                () => $(quantityInput).removeClass('quantity-changed'),
                500
              )
            }
          }
        },

        increase: e => {
          e.preventDefault()
          e.stopPropagation()
          console.log('Modal increase button clicked')

          // Perbarui quantity
          const maxStock = this.state.currentProduct?.stock || 1
          if (this.state.quantity < maxStock) {
            this.state.quantity++
            // Perbarui UI
            this.updateSubtotal()
            this.updateQuantityButtonStates()

            // Update input value
            const quantityInput = document.querySelector(
              `${modalId} .product-qty`
            )
            if (quantityInput) {
              quantityInput.value = this.state.quantity
              // Animasi untuk feedback visual
              $(quantityInput).addClass('quantity-changed')
              setTimeout(
                () => $(quantityInput).removeClass('quantity-changed'),
                500
              )
            }
          }
        },

        input: e => {
          const rawValue = e.target.value.replace(/[^0-9]/g, '')
          let newQuantity = rawValue ? parseInt(rawValue, 10) : 1
          const maxStock = this.state.currentProduct?.stock || 1

          // Validasi nilai
          newQuantity = Math.min(Math.max(1, newQuantity), maxStock)

          // Update state
          this.state.quantity = newQuantity

          // Update UI
          e.target.value = newQuantity
          this.updateSubtotal()
          this.updateQuantityButtonStates()
        }
      }

      // Bind new event handlers
      $(document).on(
        'click',
        `${modalId} .decrease-qty`,
        this._quantityHandlers.decrease
      )
      $(document).on(
        'click',
        `${modalId} .increase-qty`,
        this._quantityHandlers.increase
      )
      $(document).on(
        'input',
        `${modalId} .product-qty`,
        this._quantityHandlers.input
      )

      console.log('Modal quantity events bound successfully')
    } catch (error) {
      console.error('Error binding quantity events:', error)
    } finally {
      console.groupEnd()
    }
  },

  bindNoteEvents () {
    // Handle notes untuk produk regular
    const noteInput = document.querySelector(this.selectors.noteInput)
    if (noteInput) {
      noteInput.addEventListener('input', e => {
        this.handleNoteChange(e.target.value)
      })
    }

    // Handle notes untuk produk paket
    $(document).on('input', '[id^="notes-"]', e => {
      const productId = e.target.id.replace('notes-', '')
      this.handleNoteChange(e.target.value, productId)
    })
  },

  bindCartEvents () {
    console.group('Binding Cart Events')

    try {
      // PERBAIKAN: Hapus event handler lama untuk mencegah duplikasi
      $(document).off('click', this.selectors.addToCartRegular)

      // PERBAIKAN: Tambahkan flag untuk mencegah klik berulang
      this._isAddingToCart = false

      // PERBAIKAN: Simpan reference ke handler
      this._addToCartHandler = async e => {
        e.preventDefault()

        // PERBAIKAN: Cek flag untuk mencegah klik ganda
        if (this._isAddingToCart) {
          console.log(
            'Add to cart already in progress, ignoring duplicate click'
          )
          return
        }

        this._isAddingToCart = true

        const btn = e.currentTarget
        btn.disabled = true
        btn.innerHTML = `<div class="spinner-border spinner-border-sm me-2"></div>Menambahkan...`

        try {
          // Validasi sesi
          const sessionData = await this.validateSession()

          // Persiapkan data cart
          const cartData = {
            action: 2,
            orderId: sessionData.data.session.id,
            data: [
              {
                product_id: this.state.currentProduct.id,
                quantity: this.state.quantity,
                notes: this.state.notes || ''
              }
            ]
          }

          // Kirim request
          const response = await this.sendAddToCartRequest(cartData)

          // Update UI
          await this.updateCartCount()

          // PERBAIKAN: Tambahkan delay untuk memastikan modal ditutup sebelum cart dibuka
          this.modalInstance.hide()

          // Beri jeda sebelum membuka cart
          setTimeout(() => {
            // Flag untuk refresh cart data
            window.forceCartRefresh = true
            // Buka modal keranjang
            $('#cart-modal').modal('show')
          }, 500)

          this.showSuccess('Produk berhasil ditambahkan')
        } catch (error) {
          this.showError('Gagal Menambahkan', error.message)
        } finally {
          // PERBAIKAN: Reset flag
          this._isAddingToCart = false

          // Reset button state
          btn.disabled = false
          btn.innerHTML = `<i class="bi bi-cart-plus me-2"></i>Tambah ke Keranjang`
        }
      }

      // Handle untuk produk regular
      $(document).on(
        'click',
        this.selectors.addToCartRegular,
        this._addToCartHandler
      )

      // PERBAIKAN: Handle untuk produk paket
      $(document).off('click', this.selectors.addToCartPackage)

      this._addPackageHandler = async e => {
        e.preventDefault()

        // PERBAIKAN: Cek flag untuk mencegah klik ganda
        if (this._isAddingToCart) {
          console.log(
            'Add to cart already in progress, ignoring duplicate click'
          )
          return
        }

        await this.handleAddToCart()
      }

      $(document).on(
        'click',
        this.selectors.addToCartPackage,
        this._addPackageHandler
      )

      console.log('Cart event handlers bound successfully')
    } catch (error) {
      console.error('Error binding cart events:', error)
    } finally {
      console.groupEnd()
    }
  },

  bindPackageProductEvents () {
    // Hapus event listener lama jika ada
    document.querySelectorAll('.select-package-product').forEach(button => {
      button.replaceWith(button.cloneNode(true))
    })

    // Tambahkan event listener baru
    document.querySelectorAll('.select-package-product').forEach(button => {
      button.addEventListener('click', e => {
        const card = e.target.closest('.package-product-card')
        if (card) {
          const productId = card.dataset.productId
          const categoryId = card.dataset.categoryId
          this.handlePackageProductSelection(productId, categoryId)
        }
      })
    })
  },

  bindPackageEvents () {
    console.log('🔄 Binding Package Events')

    const modalElement = document.querySelector(this.selectors.modal)
    if (!modalElement) {
      console.error('❌ Modal element not found')
      return
    }

    const addToCartHandler = async e => {
      const addToCartBtn = e.target.closest('#add-package-to-cart')
      if (!addToCartBtn || this.state.isProcessing) return
      e.preventDefault()

      try {
        this.state.isProcessing = true
        addToCartBtn.disabled = true
        addToCartBtn.innerHTML = `
			<div class="spinner-border spinner-border-sm me-2"></div>
			<span>Menambahkan...</span>
		  `

        // Validate package selection
        const validation = this.validatePackageSelection()
        console.log('📋 Package Validation:', validation)

        if (!validation.isValid) {
          console.warn('❌ Invalid Package Selection')
          this.showPackageValidationErrors(validation.messages)
          return
        }

        // Validate session
        const sessionData = await this.validateSession()
        console.log('🔑 Session Data:', sessionData)

        // Prepare cart data
        const cartData = {
          action: 3,
          orderId: sessionData.data.session.id,
          packageId: this.state.packageData.baseInfo.id,
          products: Array.from(
            this.state.packageData.selectedProducts.values()
          ).map(product => ({
            productId: product.product_id,
            categoryId: product.package_category_id,
            quantity: product.quantity || 1,
            notes: product.notes || '',
            price: parseFloat(product.price || 0)
          })),
          packageContext: {
            basePrice: this.state.packageData.baseInfo.basePrice,
            name: this.state.packageData.baseInfo.name
          }
        }

        console.log('📦 Cart Payload:', cartData)

        // Send request
        const response = await this.sendAddToCartRequest(cartData)
        console.log('✅ Cart Response:', response)

        // Update UI
        await this.updateCartCount()

        // Close modal
        this.modalInstance.hide()

        // PERUBAHAN: Tambahkan timeout sebentar sebelum menampilkan keranjang
        setTimeout(() => {
          // PERUBAHAN: Buka modal keranjang
          $('#cart-modal').modal('show')
        }, 300)

        this.showSuccess('Paket berhasil ditambahkan')
      } catch (error) {
        console.error('🔴 Add to Cart Error:', error)
        this.showError('Gagal menambahkan', error.message)
      } finally {
        this.state.isProcessing = false
        if (addToCartBtn) {
          addToCartBtn.disabled = false
          addToCartBtn.innerHTML = `
			  <i class="fas fa-shopping-cart me-2"></i>
			  <span>Tambah ke Keranjang</span>
			`
        }
      }
    }

    // Remove existing event listener if exists
    if (this._packageEventHandler) {
      modalElement.removeEventListener('click', this._packageEventHandler)
    }

    // Create new debounced handler
    this._packageEventHandler = debounce(addToCartHandler.bind(this), 250)

    // Add new listener
    modalElement.addEventListener('click', this._packageEventHandler)

    console.log('✨ Package Events Bound Successfully')
    modalElement.addEventListener('input', e => {
      if (e.target.id.startsWith('notes-')) {
        const productId = e.target.id.replace('notes-', '')
        const product = this.state.packageData.selectedProducts.get(productId)
        if (product) {
          product.notes = e.target.value.trim()
          this.updateNoteCounter(e.target)
        }
      }
    })

    // Binding untuk quantity control paket
    modalElement.addEventListener('click', e => {
      const target = e.target.closest(
        '.decrease-package-qty, .increase-package-qty'
      )
      if (!target) return

      const productId = target.dataset.productId
      const isDecrease = target.classList.contains('decrease-package-qty')
      this.handlePackageQuantityChange(productId, isDecrease)
    })

    // Binding untuk input quantity paket
    modalElement.addEventListener('input', e => {
      if (e.target.classList.contains('package-qty')) {
        const productId = e.target.dataset.productId
        const value = parseInt(e.target.value)
        this.handleDirectPackageQuantityInput(productId, value)
      }
    })
  },

  // Handler untuk quantity paket
  handlePackageQuantityChange (productId, isDecrease) {
    const product = this.state.packageData.selectedProducts.get(productId)
    if (!product) return

    let newQuantity = product.quantity || 0
    if (isDecrease) {
      newQuantity = Math.max(0, newQuantity - 1)
    } else {
      newQuantity = Math.min(product.stock, newQuantity + 1)
    }

    this.updatePackageProductQuantity(productId, newQuantity)
  },

  // Handler untuk input langsung quantity paket
  handleDirectPackageQuantityInput (productId, value) {
    const product = this.state.packageData.selectedProducts.get(productId)
    if (!product) return

    const newQuantity = Math.min(Math.max(0, value), product.stock)
    this.updatePackageProductQuantity(productId, newQuantity)
  },

  // Update quantity produk paket
  updatePackageProductQuantity (productId, quantity) {
    const product = this.state.packageData.selectedProducts.get(productId)
    if (!product) return

    product.quantity = quantity

    // Update UI
    const quantityInput = document.querySelector(
      `.package-qty[data-product-id="${productId}"]`
    )
    if (quantityInput) {
      quantityInput.value = quantity
    }

    this.updatePackageTotal()
  },

  // Update counter karakter untuk notes
  updateNoteCounter (textarea) {
    const counter = textarea.parentElement.querySelector('.note-counter')
    if (counter) {
      const length = textarea.value.length
      counter.textContent = `${length}/255`
      counter.classList.toggle('text-warning', length > 200)
      counter.classList.toggle('text-danger', length > 255)
    }
  },

  validatePackageSelection () {
    console.group('Package Validation')

    const validation = {
      isValid: true,
      messages: [],
      details: {}
    }

    try {
      // 1. Validasi inisialisasi
      if (!this.state.packageData || !this.state.packageData.initialized) {
        throw new Error('Paket belum diinisialisasi dengan benar')
      }

      // 2. Dapatkan produk terpilih
      const selectedProducts = Array.from(
        this.state.packageData.selectedProducts.values()
      )
      console.log('Selected products:', selectedProducts)

      // 3. Validasi per kategori
      this.state.packageData.categories.forEach(category => {
        const selectedInCategory = selectedProducts.filter(
          p => p.package_category_id === category.id
        )

        console.log(`Validating category ${category.name}:`, {
          selected: selectedInCategory.length,
          required: category.minItems,
          maximum: category.maxItems
        })

        // Validasi minimum items
        if (selectedInCategory.length < category.minItems) {
          validation.isValid = false
          validation.messages.push(
            `Kategori ${category.name} membutuhkan minimal ${category.minItems} item`
          )
          validation.details[category.id] = false
        }

        // Validasi maximum items
        if (selectedInCategory.length > category.maxItems) {
          validation.isValid = false
          validation.messages.push(
            `Kategori ${category.name} maksimal ${category.maxItems} item`
          )
          validation.details[category.id] = false
        }

        // Validasi stok setiap produk
        selectedInCategory.forEach(product => {
          const stockValidation = this.validateProductStock(product)
          if (!stockValidation.valid) {
            validation.isValid = false
            validation.messages.push(stockValidation.message)
            validation.details[category.id] = false
          }
        })
      })

      // 4. Update UI berdasarkan validasi
      this.updateValidationUI(validation)

      console.log('Validation result:', validation)
      return validation
    } catch (error) {
      console.error('Validation error:', error)
      validation.isValid = false
      validation.messages.push(error.message)
      return validation
    } finally {
      console.groupEnd()
    }
  },

  showPackageValidationErrors (messages) {
    Swal.fire({
      icon: 'warning',
      title: 'Validasi Paket Gagal',
      html: messages
        .map(
          msg =>
            `<p class="text-start">
						<i class="fas fa-exclamation-triangle me-2"></i>${msg}
					</p>`
        )
        .join(''),
      confirmButtonText: 'Perbaiki Pilihan',
      didOpen: () => {
        console.warn('Validation Errors:', messages)
      }
    })
  },

  handleNoteChange (value, productId = null) {
    if (productId) {
      // Handle notes untuk produk paket
      const product = this.state.packageData.selectedProducts.get(productId)
      if (product) {
        product.notes = value?.trim() ?? ''
        this.state.packageData.selectedProducts.set(productId, product)
      }
    } else {
      // Handle notes untuk produk regular
      this.state.notes = value?.trim() ?? ''
    }

    // Update counter
    const textarea = document.getElementById(`notes-${productId || ''}`)
    if (textarea) {
      const counter = textarea.nextElementSibling
      if (counter) {
        const length = textarea.value.length
        counter.textContent = `${length}/255`
        counter.classList.toggle('text-warning', length > 200)
        counter.classList.toggle('text-danger', length > 255)
      }
    }
  },

  handleModalShow () {
    this.state.ui.modalVisible = true

    // PERBAIKAN: Tambahkan delay untuk rebind event agar tidak terjadi double firing
    setTimeout(() => {
      // Reset dan rebind event
      this.bindQuantityEvents()

      // Tambahkan animasi saat modal dibuka
      const productImage = document.querySelector(this.selectors.modalImage)
      if (productImage) {
        productImage.classList.add('scale-up-animation')
        setTimeout(() => {
          productImage.classList.remove('scale-up-animation')
        }, 800)
      }

      const productName = document.querySelector(this.selectors.modalTitle)
      if (productName) {
        productName.classList.add('slide-in-animation')
        setTimeout(() => {
          productName.classList.remove('slide-in-animation')
        }, 800)
      }
    }, 300)
  },

  handleModalHide () {
    this.state.ui.modalVisible = false
    this.resetModalState()

    // Clean up event listeners
    const addToCartBtn = document.querySelector('#add-package-to-cart')
    if (addToCartBtn) {
      const newBtn = addToCartBtn.cloneNode(true)
      addToCartBtn.parentNode.replaceChild(newBtn, addToCartBtn)
    }
  },

  resetModalState () {
    this.state.currentProduct = null
    this.state.quantity = 1
    this.state.notes = ''
    this.state.packageData = {
      baseInfo: null,
      categories: [],
      selectedProducts: {},
      customPrices: {},
      excludedProducts: [],
      requirements: {}
    }
  },

  validateApiConnection (product) {
    console.group('API Connection Validation')
    try {
      // Check if product object exists
      if (!product) {
        console.warn('Product object is undefined')
        return {
          valid: false,
          message: 'Produk tidak tersedia'
        }
      }

      // Check if API ID exists
      const apiId = product.api_id || null
      console.log('Product API ID:', apiId)

      // Validate API connection
      if (!apiId) {
        console.warn('Product is not connected to MRP system')
        return {
          valid: false,
          message: 'Produk ini belum terhubung dengan sistem MRP'
        }
      }

      // API ID exists
      return {
        valid: true,
        message: ''
      }
    } catch (error) {
      console.error('Error validating API connection:', error)
      return {
        valid: false,
        message: 'Kesalahan validasi koneksi API'
      }
    } finally {
      console.groupEnd()
    }
  },

  // Modal Actions
  async openProductModal (productId) {
    try {
      this.showLoading(true)
      const product = await this.fetchProductDetails(productId)

      // Add API ID validation check
      const apiValidation = this.validateApiConnection(product)
      if (!apiValidation.valid) {
        // Product doesn't have API ID, show error message
        this.showLoading(false)
        Swal.fire({
          icon: 'warning',
          title: 'Produk Belum Terhubung',
          text: 'Produk ini belum terhubung dengan sistem MRP. Silakan hubungi administrator untuk menghubungkan produk ini.',
          confirmButtonText: 'Tutup'
        })
        return
      }

      // Continue with existing code
      const isPackage =
        product.is_package === true || product.is_package === '1'

      // Use template based on product type
      const modalContent = document.querySelector(this.selectors.modal)
      modalContent.innerHTML = isPackage
        ? this.templates.packageModal
        : this.templates.regularModal

      // Process according to product type
      if (isPackage) {
        await this.handlePackageProduct(product)
      } else {
        await this.handleRegularProduct(product)
      }

      this.modalInstance.show()
    } catch (error) {
      this.handleModalError(error)
    } finally {
      this.showLoading(false)
    }
  },

  async handleRegularProduct (product) {
    try {
      // Reset state produk reguler
      this.resetRegularProductState()

      console.log('Regular Product Data:', product)

      const processedProduct = {
        id: product.product_id,
        name: product.product_name || 'Produk Tidak Bernama',
        description: product.product_desc || 'Tidak ada deskripsi',
        image: product.product_pict || 'default.png',
        price: parseFloat(product.product_price || 0),
        stock: parseInt(product.stock || 0),
        product_id: product.product_id,
        product_price: product.product_price
      }

      // Set current product
      this.state.currentProduct = processedProduct

      // Set quantity default ke 1
      this.state.quantity = 1

      // Reset notes
      this.state.notes = ''

      // Update UI produk reguler
      await this.updateRegularProductUI(processedProduct)

      // PERBAIKAN: Set data-item-id pada elemen quantity control
      // tapi jangan tambahkan event handler di sini
      $('.decrease-qty, .increase-qty, .product-qty').attr(
        'data-item-id',
        processedProduct.id
      )

      // Inisialisasi kontrol kuantitas, tapi event handler akan ditambahkan oleh bindQuantityEvents
      this.initializeQuantityControl(processedProduct.stock)

      // Validasi produk
      this.validateRegularProduct()

      // Update subtotal
      this.updateSubtotal()
    } catch (error) {
      console.error('Error in handleRegularProduct:', error)
      throw error
    }
  },

  resetRegularProductState () {
    // Reset state ke kondisi awal
    this.state.quantity = 1
    this.state.notes = ''
    this.state.currentProduct = null
  },

  validateRegularProduct () {
    const product = this.state.currentProduct
    const quantity = this.state.quantity

    // Validate product exists
    if (!product) {
      this.addValidationError('Produk tidak ditemukan')
      return false
    }

    // Validate API connection
    if (!product.api_id) {
      this.addValidationError('Produk belum terhubung dengan sistem MRP')
      return false
    }

    // Validate stock
    if (quantity > product.stock) {
      this.addValidationError(`Hanya ${product.stock} item tersedia`)
      return false
    }

    // Validate quantity
    if (quantity < 1) {
      this.addValidationError('Kuantitas minimal 1')
      return false
    }

    this.clearValidationErrors()
    return true
  },

  addValidationError (message) {
    this.state.validation.isValid = false
    this.state.validation.messages.push(message)
    this.updateValidationUI()
  },

  clearValidationErrors () {
    this.state.validation.isValid = true
    this.state.validation.messages = []
    this.updateValidationUI()
  },

  updateValidationUI (validation) {
    console.group('🔄 Updating Validation UI')
    console.log('Validation state:', validation)

    try {
      const addToCartBtn = document.querySelector('#add-package-to-cart')
      if (!addToCartBtn) {
        console.warn('⚠️ Add to cart button not found')
        return
      }

      // Update button state without cloning
      addToCartBtn.disabled = !validation.isValid

      // Update button appearance
      addToCartBtn.classList.remove('btn-primary', 'btn-danger')
      addToCartBtn.classList.add(
        validation.isValid ? 'btn-primary' : 'btn-danger'
      )

      // Update tooltip
      if (!validation.isValid) {
        addToCartBtn.setAttribute('title', validation.messages.join('\n'))
        addToCartBtn.setAttribute('data-bs-toggle', 'tooltip')
      } else {
        addToCartBtn.removeAttribute('title')
        addToCartBtn.removeAttribute('data-bs-toggle')
      }

      // Update validation messages container
      const validationContainer = document.querySelector(
        '#package-validation-messages'
      )
      if (validationContainer) {
        if (validation.messages.length > 0) {
          validationContainer.innerHTML = validation.messages
            .map(
              msg => `
								<div class="alert alert-danger d-flex align-items-center">
									<i class="fas fa-exclamation-triangle me-2"></i>
									${msg}
								</div>
							`
            )
            .join('')
          validationContainer.style.display = 'block'
        } else {
          validationContainer.innerHTML = ''
          validationContainer.style.display = 'none'
        }
      }

      // Update category indicators if they exist
      this.updateCategoryProgress()

      console.log('✅ Validation UI Updated')
    } catch (error) {
      console.error('❌ Error updating validation UI:', error)
    } finally {
      console.groupEnd()
    }
  },

  // 4. Tambahkan helper function untuk validasi
  validatePackagePayload (payload) {
    console.group('🔍 Validating Package Payload')

    try {
      // Check required fields
      if (!payload.orderId) throw new Error('Order ID is required')
      if (!payload.packageId) throw new Error('Package ID is required')
      if (!Array.isArray(payload.products))
        throw new Error('Products must be an array')
      if (payload.products.length === 0)
        throw new Error('Products array cannot be empty')

      // Validate each product
      payload.products.forEach((product, index) => {
        if (!product.productId)
          throw new Error(`Product ID missing at index ${index}`)
        if (!product.categoryId)
          throw new Error(`Category ID missing at index ${index}`)
        if (typeof product.quantity !== 'number')
          throw new Error(`Invalid quantity at index ${index}`)
        if (typeof product.price !== 'number')
          throw new Error(`Invalid price at index ${index}`)
      })

      console.log('✅ Payload Valid:', payload)
      return true
    } catch (error) {
      console.error('❌ Payload Validation Error:', error)
      throw error
    } finally {
      console.groupEnd()
    }
  },

  activateCategory (categoryId) {
    try {
      // Temukan tab kategori
      const categoryTab = document.querySelector(`#category-tab-${categoryId}`)
      if (!categoryTab) return

      // Nonaktifkan tab dan konten sebelumnya
      document
        .querySelectorAll('.category-tab, .category-content')
        .forEach(el => {
          el.classList.remove('active', 'show')
        })

      // Aktifkan tab dan konten kategori yang dipilih
      categoryTab.classList.add('active')
      const categoryContent = document.querySelector(
        `#category-content-${categoryId}`
      )
      if (categoryContent) {
        categoryContent.classList.add('show', 'active')
      }

      // Update state kategori saat ini
      this.state.packageData.currentCategory = categoryId

      // Update UI kategori
      this.updateCategoryProgress()
      this.updateCategoryNavigation()
    } catch (error) {
      console.error('Kesalahan mengaktifkan kategori:', error)
    }
  },

  // Tambahkan fungsi updateCategoryUI
  updateCategoryUI (categoryId) {
    try {
      // Reset semua tab dan konten
      document.querySelectorAll('.category-tab').forEach(tab => {
        tab.classList.remove('active')
      })

      document.querySelectorAll('.category-content').forEach(content => {
        content.classList.remove('show', 'active')
      })

      // Aktifkan tab dan konten yang dipilih
      const selectedTab = document.querySelector(`#category-tab-${categoryId}`)
      const selectedContent = document.querySelector(
        `#category-content-${categoryId}`
      )

      if (selectedTab) {
        selectedTab.classList.add('active')
      }
      if (selectedContent) {
        selectedContent.classList.add('show', 'active')
      }

      // Update progress
      this.updateCategoryProgress()
    } catch (error) {
      console.error('Error updating category UI:', error)
    }
  },

  async handlePackageProduct (product) {
    try {
      // Reset state paket
      this.resetPackageState()

      // Log untuk debugging
      console.log('Raw Product Data:', product)

      // Pastikan data paket tersedia
      const packageDetails =
        product.package_details ||
        (await this.fetchPackageDetails(product.product_id))

      console.log('Package Details:', packageDetails)

      // Inisialisasi state paket dengan penanganan yang lebih aman
      this.initializePackageState({
        package: {
          id: product.product_id,
          name: product.product_name,
          description: product.product_desc || '',
          image: product.product_pict || 'default.png',
          basePrice: parseFloat(product.product_price || 0)
        },
        categories: packageDetails.categories || [],
        products: packageDetails.products_by_category || {}
      })

      // Update UI paket
      await this.updatePackageUI({
        package: this.state.packageData.baseInfo,
        categories: this.state.packageData.categories,
        products: this.state.packageData.products
      })

      // Aktifkan kategori pertama
      if (this.state.packageData.categories.length > 0) {
        this.activateCategory(this.state.packageData.categories[0].id)
      }
    } catch (error) {
      console.error('Error in handlePackageProduct:', error)
      throw error
    }
  },

  initializePackageState (packageDetails) {
    console.group('Initializing Package State')
    console.log('Raw Package Details:', packageDetails)

    // Validasi input yang lebih ketat
    if (!packageDetails || !packageDetails.package) {
      console.error('Invalid package details')
      return
    }

    // Fungsi normalisasi produk dengan debugging
    const normalizeProducts = (productsByCategory, categories) => {
      console.log('Normalisasi Produk - Input:', {
        productsByCategory,
        categories
      })

      const processedProducts = {}

      categories.forEach(category => {
        const categoryId = String(category.id || category.package_category_id)

        // Log kategori saat proses
        console.log(`Memproses Kategori: ${categoryId}`)

        const categoryProducts = (productsByCategory[categoryId] || []).map(
          product => {
            const customPrice = parseFloat(
              product.custom_price || product.final_price || 0
            )
            const basePrice = parseFloat(product.product_price || 0)
            const stock = parseInt(product.stock || 0)

            const processedProduct = {
              id: String(product.id || product.product_id),
              product_id: String(product.product_id),
              package_category_id: categoryId,
              custom_price: product.custom_price || '0.00',
              is_default: product.is_default || '0',
              name:
                product.name || product.product_name || 'Produk Tidak Bernama',
              stock: stock,
              description: product.description || product.product_desc || '',
              image: product.image || product.product_pict || 'default.png',
              price: customPrice > 0 ? customPrice : basePrice,
              is_out_of_stock: stock === 0,
              available: stock > 0
            }

            // Log setiap produk yang diproses
            console.log(`Produk Diproses:`, processedProduct)

            return processedProduct
          }
        )

        processedProducts[categoryId] = categoryProducts
      })

      console.log('Produk Terproses:', processedProducts)
      return processedProducts
    }

    // Normalisasi kategori dengan debugging
    const normalizeCategories = (categories, processedProducts) => {
      console.log('Normalisasi Kategori - Input:', {
        categories,
        processedProducts
      })

      const processedCategories = (categories || [])
        .map(category => {
          const categoryId = String(category.id || category.package_category_id)

          const processedCategory = {
            id: categoryId,
            name:
              category.name ||
              category.category_name ||
              'Kategori Tidak Dikenal',
            package_id: category.package_id,
            minItems: parseInt(
              category.minItems ||
                category.min_items ||
                category.required_items ||
                1
            ),
            maxItems: parseInt(
              category.maxItems || category.max_items || category.max_items || 1
            ),
            selectionType:
              category.selectionType || category.selection_type || 'single',
            displayOrder: category.displayOrder || category.display_order || 1,
            hasProducts: (processedProducts[categoryId] || []).length > 0,
            availableProducts: (processedProducts[categoryId] || []).filter(
              p => p.available
            ).length
          }

          // Log setiap kategori yang diproses
          console.log(`Kategori Diproses:`, processedCategory)

          return processedCategory
        })
        // Filter kategori yang memiliki produk tersedia
        .filter(category => category.availableProducts > 0)

      console.log('Kategori Terproses:', processedCategories)
      return processedCategories
    }

    // Proses normalisasi produk dan kategori dengan fallback yang kuat
    const processedProducts = normalizeProducts(
      packageDetails.products_by_category ||
        packageDetails.package_details?.products_by_category ||
        packageDetails.products ||
        {},
      packageDetails.categories ||
        packageDetails.package_details?.categories ||
        []
    )

    const processedCategories = normalizeCategories(
      packageDetails.categories ||
        packageDetails.package_details?.categories ||
        [],
      processedProducts
    )

    // Log tahap akhir
    console.log('Produk Akhir:', processedProducts)
    console.log('Kategori Akhir:', processedCategories)

    // Inisialisasi state paket
    this.state.packageData = {
      baseInfo: {
        id: String(
          packageDetails.package.id || packageDetails.package.product_id
        ),
        name:
          packageDetails.package.name || packageDetails.package.product_name,
        description:
          packageDetails.package.description ||
          packageDetails.package.product_desc ||
          '',
        image:
          packageDetails.package.image ||
          packageDetails.package.product_pict ||
          'default.png',
        basePrice: parseFloat(
          packageDetails.package.basePrice ||
            packageDetails.package.base_price ||
            packageDetails.package.product_price ||
            0
        )
      },
      categories: processedCategories,
      products: processedProducts,
      selectedProducts: new Map(),
      currentCategory: processedCategories[0]?.id || null,
      customPrices: {},
      requirements: processedCategories.reduce((acc, cat) => {
        acc[cat.id] = {
          minItems: cat.minItems,
          maxItems: cat.maxItems,
          selected: 0,
          hasAvailableProducts:
            processedProducts[cat.id]?.some(p => p.available) || false
        }
        return acc
      }, {}),
      initialized: true,
      validationErrors: []
    }

    console.log('State Paket Akhir:', this.state.packageData)
    console.groupEnd()
  },

  // UI Update Methods
  updateRegularProductUI (product) {
    try {
      this.showLoading(true)

      // Update elements
      const elements = {
        image: document.querySelector(this.selectors.productImage),
        name: document.querySelector('#modal-product-name'),
        description: document.querySelector(this.selectors.productDesc),
        price: document.querySelector(this.selectors.productPrice),
        stock: document.querySelector(this.selectors.productStock),
        quantityInput: document.querySelector(this.selectors.quantityInput),
        noteInput: document.querySelector('#product-note'),
        subtotal: document.querySelector(this.selectors.subtotalDisplay),
        decreaseBtn: document.querySelector('.decrease-qty'),
        increaseBtn: document.querySelector('.increase-qty')
      }

      // Validate elements exist
      Object.entries(elements).forEach(([key, element]) => {
        if (!element) {
          throw new Error(`Element not found: ${key}`)
        }
      })

      if (elements.decreaseBtn) {
        elements.decreaseBtn.setAttribute('data-item-id', product.id)
      }

      if (elements.increaseBtn) {
        elements.increaseBtn.setAttribute('data-item-id', product.id)
      }

      if (elements.quantityInput) {
        elements.quantityInput.setAttribute('data-item-id', product.id)
      }

      // Update image with loading state
      if (elements.image) {
        elements.image.style.opacity = '0'
        elements.image.src = this.getProductImageUrl(product.image)
        elements.image.alt = product.name
        elements.image.onload = () => {
          elements.image.style.opacity = '1'
          elements.image.style.transition = 'opacity 0.3s ease'
        }
      }

      // Update text dengan animasi
      const updateTextWithAnimation = (element, value, formatter = null) => {
        element.style.opacity = '0'
        element.style.transform = 'translateY(10px)'

        setTimeout(() => {
          element.textContent = formatter ? formatter(value) : value
          element.style.opacity = '1'
          element.style.transform = 'translateY(0)'
          element.style.transition = 'all 0.3s ease'
        }, 100)
      }

      // Apply updates dengan animasi
      updateTextWithAnimation(elements.name, product.name)
      updateTextWithAnimation(elements.description, product.description)
      updateTextWithAnimation(
        elements.price,
        product.price,
        this.formatPrice.bind(this)
      )
      updateTextWithAnimation(elements.stock, product.stock)

      // Setup notes field dengan validasi
      if (elements.noteInput) {
        elements.noteInput.value = this.state.notes
        elements.noteInput.addEventListener('input', e => {
          this.handleNoteChange(e.target.value)
        })

        // Tambah character counter
        const maxLength = 255
        const counterDiv = document.createElement('div')
        counterDiv.className = 'text-end text-muted small mt-1'
        counterDiv.innerHTML = `<span class="current-length">0</span>/${maxLength} karakter`
        elements.noteInput.parentNode.appendChild(counterDiv)

        elements.noteInput.addEventListener('input', e => {
          const length = e.target.value.length
          const counter = counterDiv.querySelector('.current-length')
          counter.textContent = length
          counter.classList.toggle('text-warning', length > maxLength * 0.8)
          counter.classList.toggle('text-danger', length >= maxLength)
        })
      }

      // Setup quantity input dengan notes handling
      if (elements.quantityInput) {
        elements.quantityInput.value = this.state.quantity
        elements.quantityInput.max = product.stock

        elements.quantityInput.addEventListener('input', e => {
          const value = parseInt(e.target.value) || 1
          const newValue = Math.min(Math.max(value, 1), product.stock)

          if (value !== newValue) {
            e.target.value = newValue
          }

          this.state.quantity = newValue
          this.updateSubtotal()
        })
      }

      // Update subtotal awal
      this.updateSubtotal()
      this.showLoading(false)
    } catch (error) {
      console.error('Error updating regular product UI:', error)
      this.showError('Gagal memuat detail produk', error.message)
      this.showLoading(false)
    }
  },

  // Helper method untuk animasi subtotal
  updateSubtotalWithAnimation (amount) {
    const subtotalElement = document.querySelector(
      this.selectors.subtotalDisplay
    )
    if (subtotalElement) {
      subtotalElement.style.transform = 'scale(1.1)'
      subtotalElement.style.transition = 'transform 0.2s ease'

      setTimeout(() => {
        subtotalElement.textContent = this.formatPrice(amount)
        subtotalElement.style.transform = 'scale(1)'
      }, 100)
    }
  },

  updatePackageUI (packageData) {
    console.group('Update Package UI')
    console.log('Input Package Data:', packageData)

    try {
      // 2. Filter kategori yang memiliki produk
      const validCategories = packageData.categories.filter(category => {
        const categoryProducts = packageData.products[category.id] || []
        return categoryProducts.length > 0
      })

      // 3. Update state dengan hanya kategori valid
      this.state.packageData.categories = validCategories

      // 4. Update informasi dasar paket
      const baseInfoElements = {
        name: document.querySelector('#modal-package-name'),
        image: document.querySelector('#modal-package-image'),
        description: document.querySelector('#modal-package-description'),
        basePrice: document.querySelector('#modal-package-base-price')
      }

      // Update elemen dengan penanganan null yang lebih baik
      if (baseInfoElements.name) {
        baseInfoElements.name.textContent = packageData.package.name || ''
      }
      if (baseInfoElements.image) {
        baseInfoElements.image.src = this.getProductImageUrl(
          packageData.package.image
        )
        baseInfoElements.image.alt = packageData.package.name || ''
      }
      if (baseInfoElements.description) {
        baseInfoElements.description.textContent =
          packageData.package.description || ''
      }
      if (baseInfoElements.basePrice) {
        baseInfoElements.basePrice.textContent = this.formatPrice(
          packageData.package.basePrice
        )
      }

      // 5. Render kategori tabs
      const tabsContainer = document.querySelector('#categoryTabs')
      const contentContainer = document.querySelector('#categoryContent')

      if (tabsContainer && contentContainer) {
        // Clear existing content
        tabsContainer.innerHTML = ''
        contentContainer.innerHTML = ''

        if (validCategories.length === 0) {
          // Handle kasus tidak ada kategori valid
          contentContainer.innerHTML = `
					<div class="alert alert-info m-3">
					<i class="fa fa-info-circle me-2"></i>
					Tidak ada produk tersedia untuk paket ini saat ini
					</div>
				`
          return
        }

        // Render tabs untuk kategori valid
        tabsContainer.innerHTML = validCategories
          .map(
            (category, index) => `
					<li class="nav-item" role="presentation">
					<button class="nav-link ${index === 0 ? 'active' : ''}" 
							id="category-tab-${category.id}"
							data-bs-toggle="tab"
							data-bs-target="#category-content-${category.id}"
							type="button">
						${category.name}
						<span class="badge bg-primary ms-2">${category.minItems} item</span>
					</button>
					</li>
				`
          )
          .join('')

        // Render konten untuk kategori valid
        contentContainer.innerHTML = validCategories
          .map((category, index) => {
            const categoryProducts = (
              packageData.products[category.id] || []
            ).filter(product => product && product.stock > 0)

            return `
					<div class="tab-pane fade ${index === 0 ? 'show active' : ''}"
						id="category-content-${category.id}"
						role="tabpanel">
						<div class="row g-4">
						${categoryProducts
              .map(
                product => `
							<div class="col-md-4">
							<div class="card h-100 package-product-card" 
								data-product-id="${product.id}"
								data-category-id="${category.id}">
								<div class="position-relative">
								<img src="${this.getProductImageUrl(product.image)}"
									class="card-img-top"
									alt="${product.name}"
									style="height: 160px; object-fit: cover;">
								<span class="position-absolute top-0 end-0 m-2 badge bg-light text-dark">
									Stok: ${product.stock}
								</span>
								</div>
								<div class="card-body d-flex flex-column">
								<h6 class="card-title">${product.name}</h6>
								<p class="card-text small text-muted mb-3">
									${product.description || ''}
								</p>
								<div class="mt-auto d-flex justify-content-between align-items-center">
									<span class="fw-bold text-primary">
									${this.formatPrice(product.price)}
									</span>
									<button class="btn btn-sm btn-primary select-package-product">
									Pilih
									</button>
								</div>
								</div>
							</div>
							</div>
						`
              )
              .join('')}
						</div>
					</div>
					`
          })
          .join('')

        // 6. Bind events setelah render
        this.bindPackageProductEvents()
      }
      const addToCartBtn = document.querySelector('#add-package-to-cart')
      if (addToCartBtn) {
        const validation = this.validatePackageSelection()

        console.log('Validation Result:', validation)
        console.log('Button Status:', !validation.isValid)

        addToCartBtn.disabled = !validation.isValid

        // Tambahkan tooltip atau pesan jika validasi gagal
        if (!validation.isValid) {
          addToCartBtn.setAttribute('title', validation.messages.join('\n'))
        }
      }

      // 7. Update progress dan total
      this.updateCategoryProgress()
      this.updateTotalPrice()

      console.log('Package UI updated successfully')
    } catch (error) {
      console.error('Error updating package UI:', error)
      this.showError('Update UI Gagal', error.message)
    } finally {
      console.groupEnd()
    }
  },

  updateTotalPrice () {
    try {
      // Pastikan basePrice selalu ada
      const basePrice =
        this.state.packageData?.package?.basePrice ||
        this.state.packageData?.baseInfo?.basePrice ||
        0

      let additionalPrice = 0

      if (this.state.packageData?.selectedProducts) {
        this.state.packageData.selectedProducts.forEach(product => {
          additionalPrice += product.price || 0
        })
      }

      const totalPrice = basePrice + additionalPrice
      const totalElement = document.querySelector('#package-total-price')

      if (totalElement) {
        totalElement.textContent = this.formatPrice(totalPrice)
      }
    } catch (error) {
      console.error('Error calculating total price:', error)
    }
  },

  renderPackageProduct (product, category) {
    const isSelected = this.state.packageData.selectedProducts.has(product.id)
    const isDisabled = product.stock <= 0 || product.is_out_of_stock

    return `
			<div class="col-md-6 col-lg-4">
				<div class="package-product-card h-100" data-product-id="${product.id}">
					<div class="position-relative">
						<img src="${this.getProductImageUrl(product.image)}" 
							class="card-img-top"
							alt="${product.name}"
							style="height: 160px; object-fit: cover;">
						<span class="position-absolute top-0 end-0 m-2 badge bg-light text-dark">
							Stok: ${product.stock}
						</span>
					</div>
					
					<div class="card-body d-flex flex-column">
						<h6 class="card-title">${product.name}</h6>
						<p class="card-text small text-muted mb-3">
							${product.description || ''}
						</p>
						
						<!-- Notes field ditambahkan -->
						<div class="notes-field mb-3">
							<textarea id="notes-${product.id}" 
								class="form-control form-control-sm" 
								placeholder="Catatan khusus..."
								rows="2"
								${isDisabled ? 'disabled' : ''}
							>${product.notes || ''}</textarea>
							<small class="text-muted note-counter">0/255</small>
						</div>
						
						<div class="mt-auto">
							<div class="d-flex justify-content-between align-items-center mb-2">
								<span class="fw-bold text-primary">
									${this.formatPrice(product.price)}
								</span>
							</div>
							
							<div class="d-flex justify-content-between align-items-center">
								<!-- Quantity control ditambahkan -->
								<div class="quantity-control">
									<button class="btn btn-sm btn-outline-secondary decrease-package-qty" 
										data-product-id="${product.id}"
										${isDisabled ? 'disabled' : ''}>
										<i class="fa fa-minus"></i>
									</button>
									<input type="number" 
										class="form-control form-control-sm package-qty mx-2" 
										style="width: 50px"
										value="${isSelected ? product.quantity : 0}"
										min="0" 
										max="${product.stock}"
										data-product-id="${product.id}"
										${isDisabled ? 'disabled' : ''}>
									<button class="btn btn-sm btn-outline-secondary increase-package-qty"
										data-product-id="${product.id}"
										${isDisabled ? 'disabled' : ''}>
										<i class="fa fa-plus"></i>
									</button>
								</div>
								
								<button class="btn btn-sm ${
                  isSelected ? 'btn-success' : 'btn-primary'
                } select-package-product"
										data-product-id="${product.id}"
										${isDisabled ? 'disabled' : ''}>
									${isSelected ? '<i class="fas fa-check-circle me-1"></i>Terpilih' : 'Pilih'}
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		`
  },

  // Method untuk menampilkan peringatan kategori yang detail
  showDetailedCategoryWarning (category) {
    Swal.fire({
      icon: 'warning',
      title: 'Persyaratan Kategori Belum Terpenuhi',
      html: `
					<div class="category-warning">
						<p>Kategori <strong>${category.name}</strong> membutuhkan minimal <strong>${
        category.minItems
      } item</strong>.</p>
						<p>Saat ini Anda baru memilih <strong>${this.getSelectedProductCountInCategory(
              category.id
            )} item</strong>.</p>
					</div>
				`,
      confirmButtonText: 'Kembali Pilih Item',
      cancelButtonText: 'Lihat Detail',
      showCancelButton: true,
      cancelButtonColor: '#3085d6',
      confirmButtonColor: '#d33',
      didOpen: () => {
        // Tambahkan styling khusus
        const warningContainer = document.querySelector('.category-warning')
        warningContainer.style.textAlign = 'left'
        warningContainer.style.padding = '20px'
      }
    }).then(result => {
      if (result.isConfirmed) {
        // Kembalikan ke kategori yang belum terpenuhi
        const previousCategoryTab = document.querySelector(
          `#category-tab-${category.id}`
        )
        if (previousCategoryTab) {
          previousCategoryTab.click()
        }
      } else {
        // Tampilkan detail kategori
        this.showCategoryDetails(category)
      }
    })
  },

  // Method untuk mendapatkan jumlah produk yang dipilih dalam kategori
  getSelectedProductCountInCategory (categoryId) {
    return Array.from(this.state.packageData.selectedProducts.values()).filter(
      p => p.categoryId === categoryId
    ).length
  },

  updateCategoryNavigation () {
    const categories = this.state.packageData.categories
    const categoryTabs = document.querySelectorAll('.category-tab')

    categoryTabs.forEach((tab, index) => {
      const categoryId = tab.dataset.categoryId
      const selectedInCategory = Array.from(
        this.state.packageData.selectedProducts.values()
      ).filter(p => p.categoryId === categoryId)

      // Tandai kategori yang sudah terpenuhi
      if (selectedInCategory.length >= categories[index].minItems) {
        tab.classList.add('category-completed')
        tab.querySelector('.badge').classList.remove('bg-primary')
        tab.querySelector('.badge').classList.add('bg-success')
      } else {
        tab.classList.remove('category-completed')
        tab.querySelector('.badge').classList.remove('bg-success')
        tab.querySelector('.badge').classList.add('bg-primary')
      }
    })
  },

  handlePackageProductSelection (productId, categoryId) {
    console.group('Product Selection')
    console.log('Product ID:', productId)
    console.log('Category ID:', categoryId)

    try {
      if (!productId || !categoryId) {
        throw new Error('Invalid product or category ID')
      }

      const categoryProducts = this.state.packageData.products[categoryId] || []
      const product = categoryProducts.find(p => p.id === productId)
      const category = this.state.packageData.categories.find(
        c => c.id === categoryId
      )

      if (!product || !category) {
        throw new Error('Product or category not found')
      }

      // Validasi stok
      const stockValidation = this.validateProductStock(product)
      if (!stockValidation.valid) {
        this.showError('Stok Tidak Tersedia', stockValidation.message)
        return
      }

      // Get notes dari input field
      const notesField = document.querySelector(`#notes-${productId}`)
      const notes = notesField ? notesField.value.trim() : ''

      const selectedProducts = this.state.packageData.selectedProducts
      const isAlreadySelected = selectedProducts.has(productId)

      if (isAlreadySelected) {
        selectedProducts.delete(productId)
      } else {
        // Validasi maksimum items per kategori
        const selectedInCategory = Array.from(selectedProducts.values()).filter(
          p => p.package_category_id === categoryId
        )

        if (selectedInCategory.length >= category.maxItems) {
          this.showError(
            'Batas Kategori',
            `Anda hanya dapat memilih maksimal ${category.maxItems} item dalam kategori ${category.name}`
          )
          return
        }

        selectedProducts.set(productId, {
          ...product,
          quantity: 1,
          notes: notes
        })
      }

      // Update UI
      this.updateProductSelectionUI(productId, !isAlreadySelected)
      this.updateCategoryProgress()
      this.updateTotalPrice()

      // PERBAIKAN: Panggil method untuk berpindah ke kategori yang belum lengkap
      this.autoMoveToIncompleteCategory()

      // Validasi paket
      const validation = this.validatePackageSelection()
      console.log('Validation After Selection:', validation)

      // Update button state
      const addToCartBtn = document.querySelector('#add-package-to-cart')
      if (addToCartBtn) {
        addToCartBtn.disabled = !validation.isValid
      }
    } catch (error) {
      console.error('Product Selection Error:', error)
      this.showError('Kesalahan', error.message)
    }
  },

  adjustProductQuantity (productId, amount) {
    const product = this.state.packageData.selectedProducts.get(productId)
    if (!product) return

    const newQuantity = (product.quantity || 1) + amount
    if (newQuantity < 1 || newQuantity > product.stock) return

    product.quantity = newQuantity
    this.state.packageData.selectedProducts.set(productId, product)

    this.updateProductQuantityUI(productId, newQuantity)
    this.updateTotalPrice()
  },

  // Update UI untuk quantity
  updateProductQuantityUI (productId, quantity) {
    const quantityElement = document.querySelector(
      `.package-product-card[data-product-id="${productId}"] .package-qty`
    )
    if (quantityElement) {
      quantityElement.textContent = quantity
    }
  },

  // Fungsi pendukung yang diperlukan
  updateProductSelectionUI (productId, isSelected) {
    const productCard = document.querySelector(
      `.package-product-card[data-product-id="${productId}"]`
    )

    if (productCard) {
      // Update class untuk styling
      productCard.classList.toggle('selected', isSelected)

      // Update tombol
      const selectButton = productCard.querySelector('.select-package-product')
      if (selectButton) {
        selectButton.innerHTML = isSelected
          ? '<i class="fas fa-check-circle me-1"></i>Terpilih'
          : 'Pilih'
        selectButton.classList.toggle('btn-success', isSelected)
        selectButton.classList.toggle('btn-primary', !isSelected)
      }

      // Update badge jika ada
      const selectionBadge = productCard.querySelector('.selection-badge')
      if (selectionBadge) {
        selectionBadge.classList.toggle('d-none', !isSelected)
      }
    }
  },

  isCategoryComplete (categoryId) {
    const category = this.state.packageData.categories.find(
      c => String(c.id) === categoryId
    )
    if (!category) return false

    const selectedInCategory = Array.from(
      this.state.packageData.selectedProducts.values()
    ).filter(p => String(p.categoryId) === categoryId)

    return selectedInCategory.length >= category.minItems
  },

  moveToNextIncompleteCategory (currentCategoryId) {
    const categories = this.state.packageData.categories
    const currentIndex = categories.findIndex(
      c => String(c.id) === currentCategoryId
    )

    // Cari kategori berikutnya yang belum lengkap
    for (let i = currentIndex + 1; i < categories.length; i++) {
      const nextCategory = categories[i]
      if (!this.isCategoryComplete(nextCategory.id)) {
        // Aktifkan tab kategori berikutnya
        const nextTab = document.querySelector(
          `#category-tab-${nextCategory.id}`
        )
        if (nextTab) {
          const tabInstance = new bootstrap.Tab(nextTab)
          tabInstance.show()
        }
        break
      }
    }
  },

  autoMoveToIncompleteCategory () {
    // Temukan kategori pertama yang belum lengkap
    const incompleteCategory = this.state.packageData.categories.find(
      category => {
        const selectedProducts = Array.from(
          this.state.packageData.selectedProducts.values()
        ).filter(p => p.package_category_id === category.id)

        return selectedProducts.length < category.minItems
      }
    )

    // Jika ada kategori yang belum lengkap, aktifkan kategori tersebut
    if (incompleteCategory) {
      const categoryTab = document.querySelector(
        `#category-tab-${incompleteCategory.id}`
      )

      if (categoryTab) {
        categoryTab.click() // Secara otomatis berpindah ke tab kategori yang belum lengkap
      }
    }
  },

  updateCategoryNavigation () {
    const categories = this.state.packageData.categories
    categories.forEach(category => {
      const tab = document.querySelector(`#category-tab-${category.id}`)
      if (tab) {
        const isComplete = this.isCategoryComplete(category.id)
        tab.classList.toggle('category-completed', isComplete)

        const badge = tab.querySelector('.badge')
        if (badge) {
          badge.classList.toggle('bg-success', isComplete)
          badge.classList.toggle('bg-primary', !isComplete)
        }
      }
    })
  },

  // Method untuk menampilkan peringatan kebutuhan kategori
  showCategoryRequirementWarning (category) {
    Swal.fire({
      icon: 'warning',
      title: 'Kategori Belum Lengkap',
      html: `
					<div class="category-warning">
						<p>Anda harus memilih <strong>minimal ${
              category.minItems
            } item</strong> untuk kategori <strong>${
        category.name
      }</strong>.</p>
						<p>Saat ini Anda baru memilih <strong>${this.getSelectedProductCountInCategory(
              category.id
            )} item</strong>.</p>
					</div>
				`,
      confirmButtonText: 'Kembali Pilih',
      didOpen: () => {
        // Tambahkan styling khusus
        const warningContainer = document.querySelector('.category-warning')
        warningContainer.style.textAlign = 'left'
        warningContainer.style.padding = '20px'
      }
    })
  },

  getSelectedProductsForCategory (categoryId) {
    // Pastikan categoryId valid
    if (!categoryId) {
      console.warn('Category ID tidak valid')
      return []
    }

    // Gunakan Array.from untuk konversi Map ke array
    return Array.from(this.state.packageData.selectedProducts.values()).filter(
      product => product.categoryId === categoryId
    )
  },

  resetPackageState () {
    // Reset state paket ke kondisi awal
    this.state.packageData = {
      baseInfo: {
        id: null,
        name: '',
        description: '',
        image: '',
        basePrice: 0
      },
      categories: [],
      selectedProducts: new Map(),
      currentCategory: null,
      customPrices: {},
      excludedProducts: [],
      requirements: {},
      initialized: false,
      validationErrors: []
    }

    // Reset validasi
    this.state.validation = {
      isValid: false,
      messages: [],
      stockValid: true,
      categoryValid: true
    }

    // Reset UI state
    this.state.ui = {
      loading: false,
      activeCategory: null,
      modalVisible: false
    }

    // Log untuk debugging
    console.log('Package state has been reset')
  },

  updateCategoryProgress () {
    console.group('🔄 Updating Category Progress')

    // Validasi container progress
    const progressContainer = document.querySelector('#package-categories')
    if (
      !progressContainer ||
      !this.state.packageData ||
      !this.state.packageData.categories
    ) {
      console.warn('Progress container or package data is missing')
      console.groupEnd()
      return
    }

    // Pastikan categories adalah array yang valid
    const categories = this.state.packageData.categories || []

    // Pastikan selectedProducts adalah Map yang valid
    const selectedProducts =
      this.state.packageData.selectedProducts || new Map()

    console.log('Total Categories:', categories.length)
    console.log('Selected Products Count:', selectedProducts.size)

    // Buat HTML untuk progress kategori
    const progressHTML = categories
      .map(category => {
        // Filter produk yang dipilih untuk kategori ini
        const selectedInCategory = Array.from(selectedProducts.values()).filter(
          product => String(product.package_category_id) === String(category.id)
        )

        // Hitung detail progress
        const selectedCount = selectedInCategory.length
        const minRequired = category.minItems || 0
        const maxAllowed = category.maxItems || 1

        // Hitung persentase progress
        const progress =
          minRequired > 0
            ? Math.min((selectedCount / minRequired) * 100, 100)
            : 0

        // Tentukan status kategori
        const isComplete = selectedCount >= minRequired
        const isOverflow = selectedCount > maxAllowed

        console.log(`Category ${category.name} Progress:`, {
          selectedCount,
          minRequired,
          maxAllowed,
          progress,
          isComplete,
          isOverflow
        })

        // Buat HTML untuk progress kategori
        return `
			  <div class="category-progress mb-3" data-category-id="${category.id}">
				  <div class="d-flex justify-content-between align-items-center mb-2">
					  <div class="d-flex align-items-center">
						  <span class="small fw-medium">${category.name}</span>
						  ${isComplete ? '<i class="fas fa-check-circle text-success ms-2"></i>' : ''}
					  </div>
					  <div class="d-flex align-items-center">
						  <span class="badge ${
                isComplete
                  ? 'bg-success'
                  : isOverflow
                  ? 'bg-danger'
                  : 'bg-primary'
              }">
							  ${selectedCount}/${minRequired} item
						  </span>
					  </div>
				  </div>
				  <div class="progress" style="height: 6px;">
					  <div 
						  class="progress-bar ${
                isComplete
                  ? 'bg-success'
                  : isOverflow
                  ? 'bg-danger'
                  : 'bg-primary'
              }"
						  role="progressbar"
						  style="width: ${progress}%"
						  aria-valuenow="${progress}"
						  aria-valuemin="0"
						  aria-valuemax="100">
					  </div>
				  </div>
				  ${
            selectedCount > 0 && selectedCount < minRequired
              ? `<small class="text-muted mt-1 d-block">
						  Pilih ${minRequired - selectedCount} item lagi
					  </small>`
              : ''
          }
			  </div>
		  `
      })
      .join('')

    // Update konten progress container
    try {
      progressContainer.innerHTML = progressHTML
      console.log('Progress container updated successfully')
    } catch (error) {
      console.error('Error updating progress container:', error)
    }

    // Tambahkan event listener untuk debugging dan interaksi
    this.addProgressContainerEventListeners()

    console.groupEnd()
  },

  // Metode tambahan untuk menambahkan event listener pada progress container
  addProgressContainerEventListeners () {
    const progressContainer = document.querySelector('#package-categories')
    if (!progressContainer) return

    progressContainer.addEventListener('click', event => {
      const progressElement = event.target.closest('.category-progress')
      if (!progressElement) return

      const categoryId = progressElement.dataset.categoryId

      console.log(`Progress for category ${categoryId} clicked`)

      // Jika kategori belum lengkap, tampilkan peringatan atau detail
      const category = this.state.packageData.categories.find(
        cat => String(cat.id) === String(categoryId)
      )

      if (category) {
        const selectedInCategory = Array.from(
          this.state.packageData.selectedProducts.values()
        ).filter(p => String(p.package_category_id) === String(categoryId))

        if (selectedInCategory.length < category.minItems) {
          this.showCategoryRequirementWarning(category)
        }
      }
    })
  },

  // Metode untuk menampilkan peringatan kebutuhan kategori
  showCategoryRequirementWarning (category) {
    const selectedCount = Array.from(
      this.state.packageData.selectedProducts.values()
    ).filter(p => String(p.package_category_id) === String(category.id)).length

    Swal.fire({
      icon: 'warning',
      title: 'Kategori Belum Lengkap',
      html: `
			  <div class="category-warning">
				  <p>Kategori <strong>${category.name}</strong> membutuhkan minimal <strong>${
        category.minItems
      } item</strong>.</p>
				  <p>Saat ini Anda baru memilih <strong>${selectedCount} item</strong>.</p>
				  <small class="text-muted">Pilih ${
            category.minItems - selectedCount
          } item lagi untuk melengkapi kategori.</small>
			  </div>
		  `,
      confirmButtonText: 'Kembali Pilih Item',
      didOpen: () => {
        const warningContainer = document.querySelector('.category-warning')
        warningContainer.style.textAlign = 'left'
        warningContainer.style.padding = '20px'
      }
    })
  },

  formatPrice (amount) {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount)
  },

  getProductImageUrl (imageName) {
    return `${window.location.origin}/resource/assets-frontend/dist/product/${
      imageName || 'default.png'
    }`
  },

  /**
   * Fetch product details from server
   */
  async fetchProductDetails (productId) {
    try {
      // Dapatkan parameter URL
      const urlParams = new URLSearchParams(window.location.search)
      const params = new URLSearchParams({
        productId: productId,
        outletId: urlParams.get('outletId'),
        brand: urlParams.get('brand'),
        tableId: urlParams.get('tableId')
      })

      // Log parameter untuk debugging
      console.group('Fetching Product Details')
      console.log('Params:', Object.fromEntries(params))

      // Gunakan AJAX jQuery untuk menangani respons yang tidak konsisten
      return new Promise((resolve, reject) => {
        $.ajax({
          url: `${window.location.origin}/order/getProductDetail`,
          method: 'GET',
          data: params.toString(),
          dataType: 'json', // Paksa parsing JSON
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json'
          },
          success: function (response) {
            console.log('Respons Server:', response)
            console.groupEnd()

            // Validasi struktur respons
            if (response && response.success) {
              resolve(response.data)
            } else {
              reject(
                new Error(response.message || 'Respons server tidak valid')
              )
            }
          },
          error: function (xhr, status, error) {
            console.error('Detail Error:', {
              status: xhr.status,
              responseText: xhr.responseText,
              error: error
            })
            console.groupEnd()

            // Tangani berbagai jenis kesalahan
            if (xhr.status === 200) {
              try {
                // Ekstrak JSON dari responseText
                const jsonStart = xhr.responseText.indexOf('{')
                const jsonEnd = xhr.responseText.lastIndexOf('}') + 1
                const jsonString = xhr.responseText.substring(
                  jsonStart,
                  jsonEnd
                )
                const parsedResponse = JSON.parse(jsonString)

                if (parsedResponse.success) {
                  resolve(parsedResponse.data)
                } else {
                  reject(
                    new Error(
                      parsedResponse.message || 'Gagal memuat detail produk'
                    )
                  )
                }
              } catch (parseError) {
                reject(
                  new Error('Gagal parsing respons: ' + parseError.message)
                )
              }
            } else {
              reject(new Error('Kesalahan server: ' + xhr.status))
            }
          }
        })
      })
    } catch (error) {
      console.error('Kesalahan pengambilan detail produk:', error)
      throw error
    }
  },

  /**
   * Fetch package details from server
   */
  async fetchPackageDetails (productId) {
    try {
      console.log('Fetching package details for ID:', productId)

      const urlParams = new URLSearchParams(window.location.search)
      const params = {
        outletId: urlParams.get('outletId'),
        brand: urlParams.get('brand'),
        tableId: urlParams.get('tableId')
      }

      const response = await fetch(
        `/order/getPackageDetail/${productId}?${new URLSearchParams(params)}`,
        {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        }
      )

      // Periksa status response
      if (!response.ok) {
        const errorText = await response.text()
        console.error('Error response:', errorText)
        throw new Error(
          `HTTP error! status: ${response.status}, message: ${errorText}`
        )
      }

      // Pastikan response adalah JSON
      const contentType = response.headers.get('content-type')
      if (!contentType || !contentType.includes('application/json')) {
        const errorText = await response.text()
        console.error('Non-JSON response:', errorText)
        throw new Error('Response is not JSON')
      }

      const data = await response.json()
      console.log('Raw package data received:', data)

      if (!data.success) {
        throw new Error(data.message || 'Gagal mengambil detail paket')
      }

      return data
    } catch (error) {
      console.error('Error fetching package details:', error)
      throw error
    }
  },
  /**
   * Send add to cart request
   */
  async sendAddToCartRequest (cartData) {
    try {
      console.group('🛒 Sending Cart Request')
      console.log('Cart Payload:', JSON.stringify(cartData, null, 2))

      // Tambahkan notes handling untuk regular products
      if (cartData.action === 2) {
        cartData.data = cartData.data.map(item => ({
          ...item,
          notes: this.state.notes?.trim() ?? ''
        }))
      }

      // Tambahkan notes handling untuk package products
      if (cartData.action === 3) {
        cartData.products = cartData.products.map(product => ({
          ...product,
          notes: product.notes?.trim() ?? ''
        }))
      }

      const response = await $.ajax({
        url: `${window.location.origin}/order/add`,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(cartData),
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })

      console.log('✅ Server Response:', response)
      console.groupEnd()

      return response
    } catch (error) {
      console.error('❌ Cart Request Error:', error)
      throw new Error(
        error.responseJSON?.message || 'Gagal menambahkan ke keranjang'
      )
    }
  },

  /**
   * Update cart count badge
   */
  async updateCartCount () {
    try {
      const params = new URLSearchParams(window.location.search)
      const response = await this.fetchWithRetry(
        `${window.location.origin}${
          this.config.endpoints.cartCount
        }?${params.toString()}`,
        {
          method: 'GET',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json'
          }
        }
      )

      if (response.success) {
        const badge = document.querySelector(
          this.config.selectors.cartCountBadge
        )
        if (badge) {
          const currentCount = parseInt(badge.textContent) || 0
          const newCount = response.data.metrics.total_items

          badge.textContent = newCount

          if (newCount > currentCount) {
            this.animateCartBadge()
          }
        }
        return true
      }
      return false
    } catch (error) {
      console.error('Error updating cart count:', error)
      return false
    }
  },

  async handleAddToCart () {
    // PERBAIKAN: Tambahkan flag untuk mencegah klik ganda
    if (this._isAddingToCart) {
      console.log('Add to cart already in progress, ignoring duplicate click')
      return
    }

    this._isAddingToCart = true

    try {
      // Show loading state
      this.showLoading(true)

      // Get session data
      const sessionData = await this.validateSession()
      console.log('Session validated successfully')

      if (!sessionData || !sessionData.data || !sessionData.data.session) {
        throw new Error('Invalid session data')
      }

      const sessionId = sessionData.data.session.id

      // Process based on product type
      if (this.state.packageData.initialized) {
        // Package product logic
        const selectedProducts = Array.from(
          this.state.packageData.selectedProducts.values()
        )

        if (selectedProducts.length === 0) {
          throw new Error('No products selected for package')
        }

        // PERBAIKAN: Nonaktifkan tombol selama proses
        const addToCartBtn = document.querySelector('#add-package-to-cart')
        if (addToCartBtn) {
          addToCartBtn.disabled = true
          addToCartBtn.innerHTML = `<div class="spinner-border spinner-border-sm me-2"></div>Menambahkan...`
        }

        // Prepare package payload
        const cartData = {
          action: 3,
          orderId: sessionId,
          packageId: this.state.packageData.baseInfo.id,
          products: selectedProducts.map(product => ({
            productId: product.product_id,
            categoryId: product.package_category_id,
            quantity: product.quantity || 1,
            notes: product.notes || '',
            price: parseFloat(product.price || 0)
          })),
          packageContext: {
            basePrice: this.state.packageData.baseInfo.basePrice,
            name: this.state.packageData.baseInfo.name
          }
        }

        // Send package data to server
        await this.sendAddToCartRequest(cartData)
      } else {
        // Regular product logic
        if (!this.state.currentProduct) {
          throw new Error('No product selected')
        }

        const quantity = parseInt(this.state.quantity)
        if (isNaN(quantity) || quantity < 1) {
          throw new Error('Invalid quantity')
        }

        // PERBAIKAN: Nonaktifkan tombol selama proses
        const addToCartBtn = document.querySelector(
          this.selectors.addToCartRegular
        )
        if (addToCartBtn) {
          addToCartBtn.disabled = true
          addToCartBtn.innerHTML = `<div class="spinner-border spinner-border-sm me-2"></div>Menambahkan...`
        }

        // Prepare regular product payload
        const cartData = {
          action: 2,
          orderId: sessionId,
          data: [
            {
              product_id:
                this.state.currentProduct.id ||
                this.state.currentProduct.product_id,
              quantity: quantity,
              notes: this.state.notes || ''
            }
          ]
        }

        console.log('Sending regular product cart data:', cartData)
        await this.sendAddToCartRequest(cartData)
      }

      // Update cart count
      await this.updateCartCount()

      // PERBAIKAN: Beri jeda sebelum menutup dan membuka modal lain
      // Hide product modal
      this.modalInstance.hide()

      // Force refresh cart next time it's opened
      window.forceCartRefresh = true
      console.log('Set forceCartRefresh flag to true')

      // Show success message
      this.showSuccess('Product added to cart successfully')

      // PERBAIKAN: Beri jeda yang cukup sebelum membuka cart modal
      setTimeout(() => {
        $('#cart-modal').modal('show')
      }, 500)
    } catch (error) {
      console.error('Error adding to cart:', error)
      this.showError('Failed to add product', error.message)
    } finally {
      // Reset loading state
      this.showLoading(false)

      // PERBAIKAN: Reset tombol dan flag
      this._isAddingToCart = false

      // Reset button states
      const regularBtn = document.querySelector(this.selectors.addToCartRegular)
      if (regularBtn) {
        regularBtn.disabled = false
        regularBtn.innerHTML = `<i class="bi bi-cart-plus me-2"></i>Tambah ke Keranjang`
      }

      const packageBtn = document.querySelector('#add-package-to-cart')
      if (packageBtn) {
        packageBtn.disabled = false
        packageBtn.innerHTML = `<i class="fas fa-shopping-cart me-2"></i>Tambah ke Keranjang`
      }
    }
  },

  // Complete replacement for sendAddToCartRequest method in product-modal.js
  async sendAddToCartRequest (cartData) {
    try {
      console.group('🛒 [SEND CART] Sending cart request')
      console.log('Cart payload:', JSON.stringify(cartData, null, 2))

      // Validate cart data
      if (!cartData.orderId) {
        throw new Error('Order ID is required')
      }

      if (cartData.action === 2) {
        // Validate regular product data
        if (!Array.isArray(cartData.data) || cartData.data.length === 0) {
          throw new Error('Product data is required')
        }

        // Ensure notes field is set
        cartData.data = cartData.data.map(item => ({
          ...item,
          notes: item.notes ?? ''
        }))
      } else if (cartData.action === 3) {
        // Validate package data
        if (!cartData.packageId) {
          throw new Error('Package ID is required')
        }

        if (
          !Array.isArray(cartData.products) ||
          cartData.products.length === 0
        ) {
          throw new Error('Package products are required')
        }

        // Ensure notes field is set for all products
        cartData.products = cartData.products.map(product => ({
          ...product,
          notes: product.notes ?? ''
        }))
      } else {
        throw new Error('Invalid action type')
      }

      // Send request with proper headers
      const response = await $.ajax({
        url: `${window.location.origin}/order/add`,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(cartData),
        dataType: 'json',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Cache-Control': 'no-cache, no-store, must-revalidate',
          Pragma: 'no-cache'
        },
        cache: false
      })

      console.log('Server response:', response)

      // Validate response
      if (!response.success) {
        throw new Error(response.message || 'Failed to add to cart')
      }

      // Set global flag to force cart refresh
      window.forceCartRefresh = true
      console.log('Set forceCartRefresh flag to true')

      console.log('Item added to cart successfully')
      return response
    } catch (error) {
      console.error('Cart request error:', error)
      throw error
    } finally {
      console.groupEnd()
    }
  },

  // Tambahan method untuk validasi payload
  validateCartPayload (cartData) {
    // Validasi action
    if (!cartData.action) {
      throw new Error('Action harus ditentukan')
    }

    // Validasi orderId
    if (!cartData.orderId) {
      throw new Error('Order ID tidak valid')
    }

    // Validasi data untuk produk reguler
    if (cartData.action === 2) {
      if (!cartData.data || !Array.isArray(cartData.data)) {
        throw new Error('Data produk harus berupa array')
      }

      cartData.data.forEach((item, index) => {
        if (!item.product_id) {
          throw new Error(`Product ID tidak valid pada index ${index}`)
        }
        if (!item.quantity || item.quantity < 1) {
          throw new Error(`Kuantitas tidak valid pada index ${index}`)
        }
      })
    }

    // Validasi data untuk paket
    if (cartData.action === 3) {
      if (!cartData.packageId) {
        throw new Error('Package ID harus ditentukan')
      }

      if (!cartData.products || !Array.isArray(cartData.products)) {
        throw new Error('Produk paket harus berupa array')
      }

      cartData.products.forEach((product, index) => {
        if (!product.productId) {
          throw new Error(`Product ID tidak valid pada index ${index}`)
        }
        if (!product.quantity || product.quantity < 1) {
          throw new Error(`Kuantitas tidak valid pada index ${index}`)
        }
      })
    }
  },

  updateRegularProductState (product) {
    this.state.currentProduct = {
      product_id: product.product_id,
      product_name: product.product_name,
      product_price: product.product_price,
      stock: product.stock
    }
    this.state.quantity = 1
    this.state.notes = ''
    console.log('Updated regular product state:', this.state.currentProduct)
  },

  handleQuantityChange (value) {
    console.group('Quantity Change Handler')

    let newQuantity = parseInt(value)
    const stock = this.state.currentProduct?.stock || 1

    // Validasi input
    if (isNaN(newQuantity) || newQuantity < 1) {
      newQuantity = 1
    } else if (newQuantity > stock) {
      // Batasi ke stok maksimum
      newQuantity = stock
      // Tampilkan peringatan
      Swal.fire({
        icon: 'warning',
        title: 'Stok Terbatas',
        text: `Stok produk ini hanya tersedia ${stock}`
      })
    }

    console.log('New quantity:', newQuantity)

    // Update state
    this.state.quantity = newQuantity

    // Update UI
    const quantityInput = document.querySelector(this.selectors.quantityInput)
    if (quantityInput) {
      quantityInput.value = newQuantity
    }

    // Update subtotal
    this.updateSubtotal()

    console.groupEnd()
  },

  preparePackageCartPayload (sessionData) {
    console.group('🛒 Preparing Package Cart Payload')

    // Validasi state paket
    if (!this.state.packageData || !this.state.packageData.initialized) {
      console.error('❌ Package State Not Initialized')
      throw new Error('Paket belum diinisialisasi')
    }

    // Ambil produk yang dipilih
    const selectedProducts = Array.from(
      this.state.packageData.selectedProducts.values()
    )

    console.log('📦 Selected Products:', selectedProducts)

    // Validasi produk terpilih
    if (selectedProducts.length === 0) {
      console.warn('⚠️ No Products Selected')
      throw new Error('Belum ada produk yang dipilih')
    }

    // Payload untuk pengiriman
    const payload = {
      action: 3, // Aksi paket
      orderId: sessionData.data.session.id,
      packageId: this.state.packageData.baseInfo.id,
      products: selectedProducts.map(product => ({
        productId: product.product_id,
        categoryId: product.package_category_id,
        quantity: product.quantity || 1,
        price: product.price || 0,
        notes: product.notes || ''
      })),
      packageContext: {
        basePrice: this.state.packageData.baseInfo.basePrice,
        name: this.state.packageData.baseInfo.name
      }
    }

    console.log('🚚 Final Payload:', payload)
    console.groupEnd()

    return payload
  },

  // Metode validasi sesi sebelum menambahkan produk
  async validateSession () {
    return new Promise((resolve, reject) => {
      // Ambil parameter URL dengan metode yang lebih aman
      const params = new URLSearchParams(window.location.search)
      const paramsObject = Object.fromEntries(params.entries())

      // Validasi parameter
      const requiredParams = ['outletId', 'tableId', 'brand']
      const missingParams = requiredParams.filter(param => !paramsObject[param])

      if (missingParams.length > 0) {
        return reject(
          new Error(`Parameter missing: ${missingParams.join(', ')}`)
        )
      }

      $.ajax({
        url: `${window.location.origin}/order/session`,
        method: 'GET',
        data: paramsObject,
        dataType: 'json',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        },
        success: function (response) {
          // Log respons untuk debugging
          console.log('Respons Sesi:', response)

          // Validasi struktur respons
          if (response && response.success) {
            resolve(response)
          } else {
            reject(new Error(response.message || 'Sesi tidak valid'))
          }
        },
        error: function (xhr, status, error) {
          console.error('Error Validasi Sesi:', {
            status: xhr.status,
            responseText: xhr.responseText,
            error: error
          })

          reject(
            new Error(xhr.responseJSON?.message || 'Gagal memvalidasi sesi')
          )
        }
      })
    })
  },

  validateProductStock (product) {
    // Validasi stok dengan log detail
    console.group('Validasi Stok Produk')
    console.log('Produk:', product)

    // Validasi stok yang lebih komprehensif
    const validation = {
      valid: product.stock > 0 && !product.is_out_of_stock,
      message:
        product.stock <= 0
          ? 'Produk habis stok'
          : product.is_out_of_stock
          ? 'Produk tidak tersedia'
          : ''
    }

    console.log('Hasil Validasi:', validation)
    console.groupEnd()

    return validation
  },

  // Metode menangani respons setelah menambahkan ke keranjang
  async handleCartAdditionResponse (response) {
    console.group('🛍️ Handling Cart Addition Response')

    try {
      // Validasi respons
      if (!response || !response.success) {
        console.error('❌ Cart Addition Failed', response)
        throw new Error(
          response?.message || 'Gagal menambahkan paket ke keranjang'
        )
      }

      // Update jumlah item di keranjang
      await this.updateCartCount()

      // Tampilkan pesan sukses
      this.showSuccess('Paket berhasil ditambahkan ke keranjang')

      // Reset state paket
      this.resetPackageState()

      console.log('✅ Cart Addition Successful')
      console.groupEnd()
    } catch (error) {
      console.error('🔴 Cart Addition Error:', error)
      this.showError('Kesalahan', 'Gagal memperbarui keranjang')
      console.groupEnd()
    }
  },

  // Metode menangani error saat menambahkan ke keranjang
  handleCartAdditionError (error) {
    // Log error secara komprehensif
    console.error('Kesalahan Penambahan Keranjang:', error)

    // Tampilkan pesan error yang spesifik
    this.showError(
      'Gagal Menambahkan Produk',
      error.message || 'Terjadi kesalahan saat menambahkan produk'
    )
  },

  // Metode update jumlah item di keranjang
  async updateCartCount () {
    try {
      // Ambil parameter dari URL
      const params = new URLSearchParams(window.location.search)

      // Kirim request hitung keranjang
      const response = await $.ajax({
        url: `${window.location.origin}/order/countCart?${params.toString()}`,
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })

      // Update badge keranjang jika berhasil
      if (response.success) {
        const badge = document.querySelector(this.selectors.cartCountBadge)
        if (badge) {
          badge.textContent = response.data.metrics.total_items
        }
      }
    } catch (error) {
      // Log error update jumlah keranjang
      console.error('Kesalahan Update Jumlah Keranjang:', error)
    }
  },

  // Metode menampilkan loading
  showLoading (show = true) {
    this.state.ui.loading = show
    const addToCartBtn = document.querySelector(
      this.state.packageData.initialized
        ? '#add-package-to-cart'
        : '#add-to-cart-regular'
    )

    if (addToCartBtn) {
      addToCartBtn.disabled = show
      addToCartBtn.innerHTML = show
        ? `<div class="spinner-border spinner-border-sm me-2"></div>Menambahkan...`
        : `<i class="fas fa-shopping-cart me-2"></i>Tambah ke Keranjang`
    }
  },

  /**
   * Initialize quantity control
   */
  initializeQuantityControl (stock) {
    console.log('Initializing quantity control with stock:', stock)

    // PERBAIKAN: Jangan tambahkan event handler di sini, serahkan ke bindQuantityEvents
    const quantityInput = document.querySelector(this.selectors.quantityInput)
    const decreaseBtn = document.querySelector('.decrease-qty')
    const increaseBtn = document.querySelector('.increase-qty')

    // Hanya update atribut dan nilai, bukan event
    if (quantityInput) {
      quantityInput.setAttribute('max', stock)
      quantityInput.value = this.state.quantity

      if (this.state.currentProduct?.id) {
        quantityInput.setAttribute('data-item-id', this.state.currentProduct.id)
      }
    }

    if (decreaseBtn) {
      decreaseBtn.disabled = this.state.quantity <= 1

      if (this.state.currentProduct?.id) {
        decreaseBtn.setAttribute('data-item-id', this.state.currentProduct.id)
      }
    }

    if (increaseBtn) {
      increaseBtn.disabled = this.state.quantity >= stock

      if (this.state.currentProduct?.id) {
        increaseBtn.setAttribute('data-item-id', this.state.currentProduct.id)
      }
    }

    // Update status button
    this.updateQuantityButtonStates()

    // Penting: Panggil bindQuantityEvents untuk setup event handler
    // dengan cara yang aman (setelah semua atribut diset)
    this.bindQuantityEvents()
  },

  /**
   * Update subtotal display
   */
  updateSubtotal () {
    console.group('💰 Updating Subtotal - DEEP DEBUG')

    try {
      console.log('Subtotal Calculation State:', {
        currentProduct: this.state.currentProduct,
        quantity: this.state.quantity
      })

      if (!this.state.currentProduct) {
        console.warn('No current product for subtotal')
        return
      }

      const quantity = this.state.quantity
      const price =
        this.state.currentProduct.price ||
        this.state.currentProduct.product_price

      console.log('Quantity for Subtotal:', quantity)
      console.log('Price for Subtotal:', price)

      const subtotal = quantity * price
      console.log('Calculated Subtotal:', subtotal)

      const subtotalElement = document.querySelector('#product-subtotal')
      if (subtotalElement) {
        subtotalElement.textContent = this.formatPrice(subtotal)
        console.log('Subtotal Updated in UI')
      } else {
        console.warn('Subtotal Element Not Found')
      }
    } catch (error) {
      console.error('Subtotal Update Error:', error)
    } finally {
      console.groupEnd()
    }
  },

  updateQuantityButtonStates () {
    const decreaseBtn = document.querySelector('#productModal .decrease-qty')
    const increaseBtn = document.querySelector('#productModal .increase-qty')
    const quantityInput = document.querySelector('#productModal .product-qty')
    const stock = this.state.currentProduct?.stock || 1

    // PERBAIKAN: Pastikan input value diperbarui sesuai dengan state
    if (quantityInput) {
      // Pastikan nilai quantity di input sama dengan state
      quantityInput.value = this.state.quantity
    }

    if (decreaseBtn) {
      decreaseBtn.disabled = this.state.quantity <= 1
      decreaseBtn.classList.toggle('opacity-50', this.state.quantity <= 1)
    }

    if (increaseBtn) {
      increaseBtn.disabled = this.state.quantity >= stock
      increaseBtn.classList.toggle('opacity-50', this.state.quantity >= stock)
    }

    // TAMBAHAN: Perbarui juga tampilan jumlah pesanan lainnya (jika ada)
    const quantityDisplays = document.querySelectorAll('.quantity-display')
    if (quantityDisplays.length > 0) {
      quantityDisplays.forEach(display => {
        display.textContent = this.state.quantity
      })
    }
  },

  /**
   * Handle success message
   */
  showSuccess (message, options = {}) {
    console.log(`Showing success: ${message}`)

    // PERBAIKAN: Periksa apakah ada dialog yang sedang ditampilkan
    if (Swal.isVisible()) {
      console.log('Dialog already showing, will queue this success message')

      // Simpan ke dalam queue
      this.alertQueue = this.alertQueue || []
      this.alertQueue.push({
        type: 'success',
        message,
        options
      })

      return
    }

    // Default options
    const defaultOptions = {
      icon: 'success',
      title: 'Berhasil',
      text: message,
      timer: 2000,
      showConfirmButton: false,
      allowOutsideClick: false
    }

    // Merge options
    const mergedOptions = { ...defaultOptions, ...options }

    // Show success dialog
    Swal.fire({
      ...mergedOptions,
      didOpen: () => {
        if (options.didOpen) options.didOpen()
      },
      willClose: () => {
        // Reset state setelah alert tertutup
        if (options.willClose) options.willClose()

        // PERBAIKAN: Cek apakah ada alert berikutnya di queue
        if (this.alertQueue && this.alertQueue.length > 0) {
          const nextAlert = this.alertQueue.shift()
          setTimeout(() => {
            if (nextAlert.type === 'success') {
              this.showSuccess(nextAlert.message, nextAlert.options)
            } else if (nextAlert.type === 'error') {
              this.showError(
                nextAlert.title,
                nextAlert.message,
                nextAlert.options
              )
            }
          }, 300)
        }
      }
    })
  },
  // Tambahkan method untuk refresh komponen jika diperlukan
  refreshComponents () {
    // Refresh cart badge atau komponen lain yang perlu diupdate
    this.updateCartCount()
  },

  // Tambahkan fungsi showError
  showError (title, message) {
    Swal.fire({
      icon: 'error',
      title: title,
      text: message,
      confirmButtonText: 'Tutup',
      showCancelButton: true,
      cancelButtonText: 'Laporkan Error',
      reverseButtons: true,
      customClass: {
        confirmButton: 'btn btn-primary',
        cancelButton: 'btn btn-outline-danger'
      }
    }).then(result => {
      if (!result.isConfirmed) {
        // Handle error reporting
        const errorDetails = {
          title: title,
          message: message,
          timestamp: new Date().toISOString(),
          userAgent: navigator.userAgent,
          packageState: this.state.packageData
        }

        console.error('Error Details:', errorDetails)
        // Disini bisa ditambahkan logic untuk mengirim error ke server
      }
    })
  },

  showLoading (show = true) {
    this.state.ui.loading = show
    const modalContent = document.querySelector(
      `${this.selectors.modal} .modal-content`
    )
    if (modalContent) {
      modalContent.classList.toggle('loading', show)
    }
  },

  handleModalError (error) {
    console.error('Product Modal Error:', error)
    Swal.fire({
      icon: 'error',
      title: 'Gagal Memuat Produk',
      text:
        error.message || 'Tidak dapat memuat detail produk. Silakan coba lagi.',
      confirmButtonText: 'Tutup'
    })
  },

  handleInitError (error) {
    console.error('Initialization Error:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.message || 'Failed to initialize product modal',
      confirmButtonText: 'OK'
    })
  }
}

$(document).ready(() => {
  // Hanya inisialisasi ProductModal sekali
  if (!window.productModalInitialized) {
    ProductModal.init()
  }

  // Hanya inisialisasi OrderManager sekali
  if (!window.orderManagerInitialized) {
    OrderManager.init()
    window.orderManagerInitialized = true
  }
})
