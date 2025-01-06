const ProductModal = {
    /**
     * Application state management
     */
    state: {
        // Current selected product details
        currentProduct: null,
        
        // Current package details (if applicable)
        currentPackage: null,
        
        // Selected package items for complex packages
        selectedPackageItems: {},
        
        // Session and order tracking
        sessionData: null,
        orderId: null,
        
        // Validation statuses
        validationStatus: {
            categoriesValid: false,
            stockValid: false
        },
        
        // Tracking validation errors
        errors: [],
        
        // UI state tracking
        ui: {
            loading: false,
            modalVisible: false
        }
    },
  
    /**
     * Configuration settings for product modal
     */
    config: {
        // API endpoints
        endpoints: {
            SESSION_VALIDATION: '/order/session',
            PRODUCT_LIST: '/order/list',
            ADD_TO_CART: '/order/add'
        },
        
        // Validation rules
        validation: {
            minQuantity: 1,
            maxQuantityBuffer: 5
        }
    },
  
    /**
     * Initialize the product modal system
     * Sets up event listeners, validates session, and prepares UI
     * 
     * @returns {Promise<boolean>} Initialization success status
     */
    async init() {
        console.group('ProductModal Initialization');
        try {
            // Validate dependencies
            this.validateDependencies();
  
            // Validate session
            await this.validateSession();
  
            // Setup event listeners
            this.setupEventListeners();
  
            // Setup modal triggers
            this.setupModalTriggers();
  
            console.log('ProductModal initialized successfully');
            return true;
        } catch (error) {
            console.error('ProductModal Initialization Error:', error);
            this.handleInitializationError(error);
            return false;
        } finally {
            console.groupEnd();
        }
    },
  
    /**
     * Validate critical dependencies before initialization
     * @throws {Error} If critical dependencies are missing
     */
    validateDependencies() {
        const dependencies = {
            jQuery: typeof jQuery !== 'undefined',
            Bootstrap: typeof bootstrap !== 'undefined',
            SweetAlert2: typeof Swal !== 'undefined',
            OrderManager: typeof OrderManager !== 'undefined'
        };
  
        const missingDependencies = Object.entries(dependencies)
            .filter(([_, exists]) => !exists)
            .map(([name]) => name);
  
        if (missingDependencies.length > 0) {
            throw new Error(`Missing dependencies: ${missingDependencies.join(', ')}`);
        }
    },
  
    /**
     * Validate active session before product interactions
     * @returns {Promise<boolean>} Session validation result
     */
    async validateSession() {
        try {
            const params = new URLSearchParams(window.location.search);
            const response = await $.ajax({
                url: `${window.location.origin}${this.config.endpoints.SESSION_VALIDATION}`,
                method: 'GET',
                data: params.toString(),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
  
            if (!response.success) {
                throw new Error(response.message || 'Session validation failed');
            }
  
            // Store session details
            this.state.sessionData = response.data;
            this.state.orderId = response.data.session?.id;
  
            if (!this.state.orderId) {
                throw new Error('No active order session');
            }
  
            return true;
        } catch (error) {
            console.error('Session Validation Error:', error);
            this.handleSessionError(error);
            return false;
        }
    },
  
    /**
     * Setup event listeners for product interactions
     */
    setupEventListeners() {
        // Quantity manipulation handlers
        $(document).on('click', '.decrease-qty', this.handleQuantityDecrease.bind(this));
        $(document).on('click', '.increase-qty', this.handleQuantityIncrease.bind(this));
        $(document).on('change', '.product-qty', this.handleQuantityChange.bind(this));
        $(document).on('input', '#product-note', this.handleNoteChange.bind(this));
  
        // Add to cart handlers
        $('#add-to-cart-regular').on('click', this.addRegularToCart.bind(this));
        $('#add-to-cart-package').on('click', this.addPackageToCart.bind(this));
  
        // Modal cleanup
        $('#productModal').on('hidden.bs.modal', this.resetModalState.bind(this));
    },
  
    /**
     * Setup modal trigger events for product selection
     */
    setupModalTriggers() {
        $(document).on('click', '.view-product', async (e) => {
            e.preventDefault();
            const $card = $(e.currentTarget).closest('.product-card');
            const productId = $card.data('product-id');
            const isPackage = $card.data('is-package') === 1;
  
            try {
                await this.openProductModal(productId, isPackage);
            } catch (error) {
                this.handleModalError(error);
            }
        });
    },
  
    /**
     * Open product modal with detailed preparation
     * 
     * @param {number} productId - Selected product ID
     * @param {boolean} isPackage - Flag for package product
     */
    async openProductModal(productId, isPackage) {
        try {
            // Validate session
            await this.validateSession();
  
            // Reset modal state
            this.resetModalState();
  
            // Fetch product details
            const product = await this.fetchProductDetails(productId);
            
            // Prepare modal based on product type
            if (isPackage) {
                await this.preparePackageModal(product);
            } else {
                await this.prepareRegularModal(product);
            }
  
            // Show modal
            $('#productModal').modal('show');
        } catch (error) {
            this.handleModalError(error);
        }
    },
  
    /**
     * Fetch detailed product information
     * 
     * @param {number} productId - Product identifier
     * @returns {Promise<Object>} Product details
     */
    async fetchProductDetails(productId) {
        try {
            const params = new URLSearchParams(window.location.search);
            params.append('productId', productId);
  
            const response = await $.ajax({
                url: `${window.location.origin}${this.config.endpoints.PRODUCT_LIST}`,
                method: 'GET',
                data: params.toString(),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
  
            if (!response.success) {
                throw new Error(response.message || 'Failed to fetch product details');
            }
  
            // Find product in grouped products
            let product = null;
            Object.values(response.data.groupedProducts || {}).forEach(category => {
                const found = category.products.find(p => p.product_id == productId);
                if (found) product = found;
            });
  
            if (!product) {
                throw new Error('Product not found');
            }
  
            return product;
        } catch (error) {
            console.error('Product Details Fetch Error:', error);
            throw error;
        }
    },
  
    /**
     * Prepare regular product modal
     * 
     * @param {Object} product - Product details
     */
    prepareRegularModal(product) {
        // Update modal content
        $('#modal-product-name').text(product.product_name);
        $('#modal-product-image').attr('src', this.getProductImageUrl(product.product_pict));
        $('#modal-product-description').text(product.description || 'No description available');
        $('#modal-product-price').text(this.formatPrice(product.price_catalogue));
        $('#modal-product-stock').text(`${product.current_stock} units`);
  
        // Configure quantity input
        const $quantityInput = $('.product-qty');
        $quantityInput
            .attr('max', Math.min(product.current_stock, this.config.validation.maxQuantityBuffer))
            .val(1)
            .prop('disabled', product.current_stock < 1);
  
        // Store current product
        this.state.currentProduct = product;
  
        // Toggle modal sections
        $('#regular-product-content').show();
        $('#package-product-content').hide();
        $('#add-to-cart-regular').show();
        $('#add-to-cart-package').hide();
  
        // Update subtotal
        this.updateSubtotal();
    },
  
    /**
     * Prepare package product modal
     * 
     * @param {Object} product - Product details
     */
    async preparePackageModal(product) {
        try {
            // Fetch package details
            const packageDetails = await this.fetchPackageDetails(product.product_id);
            
            // Store product and package details
            this.state.currentProduct = product;
            this.state.currentPackage = packageDetails;
  
            // Render package-specific UI
            this.renderPackageCategories();
            this.renderPackageProducts();
            this.renderExcludedProducts();
  
            // Toggle modal sections
            $('#regular-product-content').hide();
            $('#package-product-content').show();
            $('#add-to-cart-regular').hide();
            $('#add-to-cart-package')
                .show()
                .prop('disabled', true);
  
            // Initialize package summary
            this.updatePackageSummary();
        } catch (error) {
            this.handleModalError(error);
        }
    },
  
    // (Existing utility methods remain the same: formatPrice, getProductImageUrl, etc.)
    
    /**
     * Quantity decrease handler
     * @param {Event} e - Click event
     */
    handleQuantityDecrease(e) {
        const $input = $(e.currentTarget).siblings('input');
        const currentVal = parseInt($input.val()) || 0;
        const minVal = parseInt($input.attr('min')) || 1;
  
        if (currentVal > minVal) {
            $input.val(currentVal - 1).trigger('change');
        }
    },
  
    /**
     * Quantity increase handler
     * @param {Event} e - Click event
     */
    handleQuantityIncrease(e) {
        const $input = $(e.currentTarget).siblings('input');
        const currentVal = parseInt($input.val()) || 0;
        const maxVal = parseInt($input.attr('max'));
  
        if (!maxVal || currentVal < maxVal) {
            $input.val(currentVal + 1).trigger('change');
        }
    },
  
    /**
     * Quantity change handler with validation
     * @param {Event} e - Change event
     */
    handleQuantityChange(e) {
        const $input = $(e.target);
        const quantity = parseInt($input.val()) || 0;
        const maxStock = parseInt($input.attr('max')) || 0;
  
        // Validate quantity
        if (quantity < this.config.validation.minQuantity) {
            $input.val(this.config.validation.minQuantity);
        } else if (quantity > maxStock) {
            $input.val(maxStock);
            this.showWarning(`Stok maksimal: ${maxStock}`);
        }
  
        this.updateSubtotal();
    },
  
    /**
     * Add regular product to cart
     */
    async addRegularToCart() {
        try {
            const quantity = parseInt($('.product-qty').val()) || 0;
            const note = $('#product-note').val().trim();
  
            // Validate input
            if (quantity < this.config.validation.minQuantity) {
                throw new Error('Masukkan kuantitas yang valid');
            }
  
            const response = await $.ajax({
                url: `${window.location.origin}${this.config.endpoints.ADD_TO_CART}`,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: 2, // Regular product
                    orderId: this.state.orderId,
                    data: [{
                        productId: this.state.currentProduct.product_id,
                        quantity: quantity,
                        notes: note
                    }]
                })
            });
  
            if (response.success) {
                $('#productModal').modal('hide');
                await OrderManager.updateCartCount();
                this.showSuccess('Produk berhasil ditambahkan ke keranjang');
            } else {
                throw new Error(response.message || 'Gagal menambahkan produk');
            }
        } catch (error) {
            this.handleModalError(error);
        }
    },
  
    // Error handling and utility methods...
    
    /**
     * Show warning toast
     * @param {string} message - Warning message
     */
    showWarning(message) {
        Swal.fire({
            text: message,
            icon: 'warning',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
    },
  
    /**
     * Show success message
     * @param {string} message - Success message
     */
    showSuccess(message) {
        Swal.fire({
            title: 'Sukses',
            text: message,
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    },
  
    /**
     * Handle initialization errors
     * @param {Error} error - Initialization error
     */
    handleInitializationError(error) {
        Swal.fire({
            title: 'Kesalahan Inisialisasi',
            text: 'Gagal memuat sistem produk. Silakan refresh halaman.',
            icon: 'error',
            confirmButtonText: 'Reload Halaman'
        }).then(() => {
            window.location.reload();
        });
    },
  
    /**
     * Handle session errors
     * @param {Error} error - Session error
     */
    handleSessionError(error) {
        Swal.fire({
            title: 'Sesi Berakhir',
            text: 'Sesi Anda telah berakhir. Halaman akan dimuat ulang.',
            icon: 'warning',
            confirmButtonText: 'Reload Halaman',
            allowOutsideClick: false
        }).then(() => {
            window.location.reload();
        });
    },
  
    /**
     * Handle modal-related errors
     * @param {Error} error - Modal interaction error
     */
    handleModalError(error) {
        console.error('Modal Error:', error);
        Swal.fire({
            title: 'Kesalahan',
            text: error.message || 'Terjadi kesalahan tak terduga',
            icon: 'error'
        });
    },
  
    /**
     * Reset modal state
     */
    resetModalState() {
      this.state = {
          currentProduct: null,
          currentPackage: null,
          selectedPackageItems: {},
          sessionData: null,
          orderId: null,
          validationStatus: {
              categoriesValid: false,
              stockValid: false
          },
          errors: [],
          ui: {
              loading: false,
              modalVisible: false
          }
      };
  
      // Reset UI elements
      $('#regular-product-content, #package-product-content').hide();
      $('#add-to-cart-regular, #add-to-cart-package').hide();
      $('.product-qty').val(1);
      $('#product-note').val('');
      $('.validation-messages').empty();
  
      // Reset package-specific elements if they exist
      $('#package-categories').empty();
      $('#package-products-accordion').empty();
      $('#package-summary').empty();
      $('#package-total').text('');
  },
  
  /**
   * Update subtotal for regular products
   */
  updateSubtotal() {
      if (!this.state.currentProduct) return;
  
      const quantity = parseInt($('.product-qty').val()) || 0;
      const price = this.state.currentProduct.price_catalogue || 0;
      const subtotal = quantity * price;
      
      $('#product-subtotal').text(this.formatPrice(subtotal));
  },
  
  /**
   * Format price in Indonesian Rupiah
   * @param {number} amount - Price amount
   * @returns {string} Formatted price
   */
  formatPrice(amount) {
      return new Intl.NumberFormat('id-ID', {
          style: 'currency',
          currency: 'IDR',
          minimumFractionDigits: 0,
          maximumFractionDigits: 0
      }).format(amount);
  },
  
  /**
   * Get full product image URL
   * @param {string} imageName - Product image filename
   * @returns {string} Full image URL
   */
  getProductImageUrl(imageName) {
      return `${window.location.origin}/resource/assets-frontend/dist/product/${imageName}`;
  },
  
  /**
   * Render package categories
   */
  renderPackageCategories() {
      const $container = $('#package-categories').empty();
      const categories = this.state.currentPackage?.categories || [];
  
      categories.forEach(category => {
          const $category = $(`
              <div class="package-category mb-3">
                  <h6>${category.name}</h6>
                  <div class="d-flex align-items-center">
                      <div class="progress flex-grow-1">
                          <div class="progress-bar" role="progressbar" 
                               data-category="${category.id}"
                               style="width: 0%">
                              0/${category.min_items}
                          </div>
                      </div>
                      <span class="badge bg-secondary ms-2">
                          Min: ${category.min_items}
                      </span>
                  </div>
              </div>
          `);
  
          $container.append($category);
      });
  },
  
  /**
   * Render package products
   */
  renderPackageProducts() {
      const $accordion = $('#package-products-accordion').empty();
      const { categories, products } = this.state.currentPackage;
  
      categories.forEach(category => {
          const categoryProducts = products.filter(
              p => p.category_id === category.id
          );
  
          const $section = $(`
              <div class="accordion-item">
                  <h2 class="accordion-header">
                      <button class="accordion-button" type="button" 
                              data-bs-toggle="collapse" 
                              data-bs-target="#category-${category.id}">
                          ${category.name}
                      </button>
                  </h2>
                  <div id="category-${category.id}" 
                       class="accordion-collapse collapse show">
                      <div class="accordion-body">
                          <div class="row g-3" id="products-${category.id}">
                          </div>
                      </div>
                  </div>
              </div>
          `);
  
          const $products = $section.find(`#products-${category.id}`);
  
          categoryProducts.forEach(product => {
              $products.append(this.createPackageProductCard(product, category));
          });
  
          $accordion.append($section);
      });
  },
  
  /**
   * Create package product card
   * @param {Object} product - Product details
   * @param {Object} category - Category details
   * @returns {jQuery} Product card element
   */
  createPackageProductCard(product, category) {
      return $(`
          <div class="col-md-6">
              <div class="card h-100">
                  <div class="card-body">
                      <div class="d-flex mb-2">
                          <img src="${this.getProductImageUrl(product.product_pict)}"
                               class="rounded me-2" 
                               style="width: 60px; height: 60px; object-fit: cover;">
                          <div>
                              <h6 class="card-title mb-1">${product.product_name}</h6>
                              <p class="card-text small text-muted mb-0">
                                  Stok: ${product.stock}
                              </p>
                          </div>
                      </div>
                      <div class="d-flex justify-content-between align-items-center">
                          <div class="price-info">
                              ${this.renderProductPrice(product)}
                          </div>
                          <div class="input-group input-group-sm" style="width: 100px;">
                              <button class="btn btn-outline-secondary decrease-qty" type="button">-</button>
                              <input type="number" class="form-control text-center package-item-qty"
                                     value="0" min="0" max="${product.stock}"
                                     data-product-id="${product.id}"
                                     data-category-id="${category.id}">
                              <button class="btn btn-outline-secondary increase-qty" type="button">+</button>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      `);
  },
  
  /**
   * Render product price with special pricing support
   * @param {Object} product - Product details
   * @returns {string} Formatted price HTML
   */
  renderProductPrice(product) {
      const regularPrice = this.formatPrice(product.price);
  
      if (product.special_price) {
          return `
              <span class="regular-price text-decoration-line-through">
                  ${regularPrice}
              </span>
              <span class="special-price text-success">
                  ${this.formatPrice(product.special_price)}
              </span>
          `;
      }
  
      return `<span class="regular-price">${regularPrice}</span>`;
  },
  
  /**
   * Render excluded products for package
   */
  renderExcludedProducts() {
      const $section = $('#excluded-products-section');
      const $list = $('#excluded-products-list').empty();
  
      const excludedProducts = this.state.currentPackage?.excluded_products || [];
  
      if (excludedProducts.length === 0) {
          $section.hide();
          return;
      }
  
      excludedProducts.forEach(product => {
          $list.append(`
              <div class="col-md-6">
                  <div class="alert alert-warning mb-2">
                      <h6 class="alert-heading">${product.product_name}</h6>
                      <p class="small mb-0">
                          ${product.exclude_reason || 'Tidak tersedia dalam paket ini'}
                      </p>
                  </div>
              </div>
          `);
      });
  
      $section.show();
  },
  
  /**
   * Update package summary and validation
   */
  updatePackageSummary() {
      const $summary = $('#package-summary').empty();
      let totalAmount = this.state.currentPackage.base_price || 0;
      let isValid = true;
  
      // Add base price line
      $summary.append(`
          <div class="d-flex justify-content-between mb-2">
              <span>Harga Dasar Paket</span>
              <span>${this.formatPrice(totalAmount)}</span>
          </div>
      `);
  
      // Summarize each category
      Object.entries(this.state.selectedPackageItems).forEach(
          ([categoryId, items]) => {
              const category = this.state.currentPackage.categories.find(
                  c => c.id === categoryId
              );
              if (!category) return;
  
              const totalItems = Object.values(items).reduce(
                  (sum, qty) => sum + qty,
                  0
              );
              const isComplete = totalItems >= category.min_items;
  
              // Calculate category total
              let categoryTotal = 0;
              Object.entries(items).forEach(([productId, quantity]) => {
                  const product = this.findPackageProduct(productId);
                  if (product) {
                      const price = product.special_price || product.price;
                      categoryTotal += price * quantity;
                  }
              });
  
              totalAmount += categoryTotal;
  
              $summary.append(`
                  <div class="d-flex justify-content-between mb-2">
                      <span>${category.name} (${totalItems} item)</span>
                      <span class="text-end">
                          <span class="badge ${
                              isComplete ? 'bg-success' : 'bg-warning'
                          } me-2">
                              ${
                                  isComplete
                                      ? 'Lengkap'
                                      : `Butuh ${category.min_items - totalItems} lagi`
                              }
                          </span>
                          ${this.formatPrice(categoryTotal)}
                      </span>
                  </div>
              `);
  
              isValid = isValid && isComplete;
          }
      );
  
      // Update total and button state
      $('#package-total').text(this.formatPrice(totalAmount));
      $('#add-to-cart-package').prop('disabled', !isValid);
      this.state.packageTotal = totalAmount;
  },
  
  /**
   * Find package product by ID
   * @param {number} productId - Product identifier
   * @returns {Object|null} Product details or null
   */
  findPackageProduct(productId) {
      return this.state.currentPackage.products.find(p => p.id === productId);
  },
  
  /**
   * Fetch detailed package information
   * @param {number} productId - Product identifier
   * @returns {Promise<Object>} Package details
   */
  async fetchPackageDetails(productId) {
      try {
          const params = new URLSearchParams(window.location.search);
          const response = await $.ajax({
              url: `${window.location.origin}${this.config.endpoints.PRODUCT_LIST}`,
              method: 'GET',
              data: params.toString(),
              headers: {
                  'X-Requested-With': 'XMLHttpRequest'
              }
          });
  
          if (!response.success) {
              throw new Error(response.message || 'Gagal mengambil detail paket');
          }
  
          const packageData = response.data.packages.find(p => p.product_id == productId);
          if (!packageData) {
              throw new Error('Paket tidak ditemukan');
          }
  
          return {
              ...packageData,
              categories: response.data.packageCategories || [],
              products: response.data.products || []
          };
      } catch (error) {
          console.error('Kesalahan Detail Paket:', error);
          throw error;
      }
  },
  
  /**
   * Add package to cart
   */
  async addPackageToCart() {
      try {
          // Validate package
          if (!this.validatePackage()) {
              throw new Error('Lengkapi persyaratan paket');
          }
  
          // Prepare package items
          const packageItems = [];
          Object.entries(this.state.selectedPackageItems).forEach(
              ([categoryId, items]) => {
                  Object.entries(items).forEach(([productId, quantity]) => {
                      packageItems.push({
                          productId: parseInt(productId),
                          quantity: quantity
                      });
                  });
              }
          );
  
          // Send request to add package
          const response = await $.ajax({
              url: `${window.location.origin}${this.config.endpoints.ADD_TO_CART}`,
              method: 'POST',
              contentType: 'application/json',
              data: JSON.stringify({
                  action: 3, // Package action
                  orderId: this.state.orderId,
                  packageId: this.state.currentProduct.product_id,
                  products: packageItems
              })
          });
  
          if (response.success) {
              $('#productModal').modal('hide');
              await OrderManager.updateCartCount();
              this.showSuccess('Paket berhasil ditambahkan ke keranjang');
          } else {
              throw new Error(response.message || 'Gagal menambahkan paket');
          }
      } catch (error) {
          this.handleModalError(error);
      }
  },
  
  /**
   * Validate package selection
   * @returns {boolean} Package validation status
   */
  validatePackage() {
      if (!this.state.currentPackage) return false;
  
      const errors = [];
      let isValid = true;
  
      // Validate each category
      this.state.currentPackage.categories.forEach(category => {
          const selectedItems = this.state.selectedPackageItems[category.id] || {};
          const totalItems = Object.values(selectedItems).reduce(
              (sum, qty) => sum + qty,
              0
          );
  
          if (totalItems < category.min_items) {
              isValid = false;
              errors.push(
                  `${category.name} membutuhkan ${category.min_items - totalItems} item lagi`
              );
          }
      });
  
      // Update state and UI
      this.state.validationStatus.categoriesValid = isValid;
      this.state.errors = errors;
  
      return isValid;
  }
  };
  
  // Initialize on document ready
  $(document).ready(() => {
  // Inisialisasi ProductModal
  try {
      ProductModal.init();
  } catch (error) {
      console.error('Kesalahan inisialisasi ProductModal:', error);
      Swal.fire({
          title: 'Kesalahan Sistem',
          text: 'Gagal memuat sistem produk. Silakan refresh halaman.',
          icon: 'error',
          confirmButtonText: 'Muat Ulang'
      }).then(() => {
          window.location.reload();
      });
  }
  });
  
  // Ensure module compatibility
  if (typeof module !== 'undefined' && module.exports) {
  module.exports = ProductModal;
  }