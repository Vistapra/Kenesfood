// Utility Functions
  const ProductManager = {
    // State management
    state: {
      currentProduct: null,
      currentPackage: null,
      selectedPackageItems: {},
      modalInstance: null
    },
  
    // Initialize semua event handlers
    init() {
      this.modalInstance = new bootstrap.Modal('#productModal');
      this.setupEventListeners();
      this.initializeQuantityHandlers();
    },
  
    // Setup event listeners
    setupEventListeners() {
      // Click handler untuk card produk
      $('.product-card').on('click', '.view-product', (e) => {
        e.preventDefault();
        const $card = $(e.target).closest('.product-card');
        const productId = $card.data('product-id');
        const isPackage = $card.data('is-package') === 1;
        
        this.handleProductClick(productId, isPackage);
      });
  
      // Add to cart handlers
      $('#add-to-cart-regular').click(() => this.addRegularToCart());
      $('#add-to-cart-package').click(() => this.addPackageToCart());

      $('#category-select').on('change', (e) => {
        const categoryId = $(e.target).val();
        this.filterProducts(categoryId);
      });
      
      $('#product-search').on('input', (e) => {
        const searchTerm = $(e.target).val().toLowerCase();
        this.searchProducts(searchTerm);
      });
  
      // Package quantity change handler
      $(document).on('change', '.package-item-qty', (e) => {
        this.handlePackageQuantityChange(e);
      });
    },

    filterProducts(categoryId) {
      if (categoryId === 'all') {
        $('.product-card').show(); 
        $('.col-12.pt-2.rounded.mb-4').show(); // Header kategori
        $('.row.mb-5').show(); // Container produk
      } else {
        // Sembunyikan semua header kategori dan container produk
        $('.col-12.pt-2.rounded.mb-4').hide();
        $('.row.mb-5').hide();
        
        // Cari container yang memiliki produk dengan kategori yang dipilih
        $(`.product-card[data-category-id="${categoryId}"]`).each(function() {
          // Tampilkan header kategori dan container dari produk tersebut
          $(this).closest('.row.mb-5').show().prev('.col-12.pt-2.rounded.mb-4').show();
        });
      }
    },
    
    searchProducts(searchTerm) {
      if (!searchTerm) {
        // Jika search kosong, tampilkan semua
        $('.product-card').show();
        $('.col-12.pt-2.rounded.mb-4').show();
        $('.row.mb-5').show();
        return;
      }
     
      // Convert searchTerm ke lowercase untuk case insensitive
      searchTerm = searchTerm.toLowerCase();
     
      // Sembunyikan semua header kategori dan container
      $('.col-12.pt-2.rounded.mb-4').hide();
      $('.row.mb-5').hide();
     
      // Cari produk yang match dengan search term
      $('.product-card').each(function() {
        const productName = $(this).find('.product-name').text().toLowerCase();
        const matches = productName.includes(searchTerm);
        $(this).toggle(matches);
     
        // Jika ada produk yang match, tampilkan header dan container-nya
        if (matches) {
          $(this).closest('.row.mb-5').show()
                 .prev('.col-12.pt-2.rounded.mb-4').show();
        }
      });
     },
  
    // Initialize quantity handlers
    initializeQuantityHandlers() {
      // Decrease quantity
      $(document).on('click', '.decrease-qty, .increase-qty', (e) => {
        const input = $(e.target).siblings('input');
        const isIncrease = $(e.target).hasClass('increase-qty');
        const currentVal = parseInt(input.val());
        const max = parseInt(input.attr('max'));
        
        if(isIncrease && currentVal < max) {
            input.val(currentVal + 1).trigger('change');
        } else if(!isIncrease && currentVal > 1) {
            input.val(currentVal - 1).trigger('change');
        }
    });
  
      // Direct input validation
      $(document).on('change', '.product-qty, .package-item-qty', (e) => {
        const input = $(e.target);
        let val = parseInt(input.val());
        const min = parseInt(input.attr('min')) || 1;
        const max = parseInt(input.attr('max'));
        
        if (isNaN(val) || val < min) val = min;
        if (val > max) val = max;
        
        input.val(val);
      });
    },
  
    // Handle product click
    async handleProductClick(productId, isPackage) {
      try {
        Swal.showLoading();
  
        // Get product details
        const productData = await this.fetchProductDetails(productId);
        
        if (!productData) {
          throw new Error('Gagal memuat detail produk');
        }
  
        this.state.currentProduct = productData;
  
        if (isPackage) {
          // Load package details
          const packageData = await this.fetchPackageDetails(productId);
          this.state.currentPackage = packageData;
          this.resetPackageState();
          this.renderPackageModal();
        } else {
          this.renderRegularModal();
        }
  
        this.modalInstance.show();
      } catch (error) {
        console.error('Error loading product:', error);
        Swal.fire('Error', 'Gagal memuat detail produk', 'error');
      }
    },
  
    // Fetch product details
    async fetchProductDetails(productId) {
      try {
        const response = await $.ajax({
          url: `${window.location.origin}/apis/product/detail/${productId}`,
          method: 'GET'
        });
        return response.success ? response.data.detail : null;
      } catch (error) {
        console.error('Error fetching product:', error);
        return null;
      }
    },
  
    // Fetch package details
    async fetchPackageDetails(productId) {
      try {
        const params = new URLSearchParams(window.location.search);
        const response = await $.ajax({
          url: `${window.location.origin}/order`,
          method: 'GET',
          data: {
            outletId: params.get('outletId'),
            tableId: params.get('tableId'),
            brand: params.get('brand'),
            packageId: productId
          },
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        return response.success ? response.data.package : null;
      } catch (error) {
        console.error('Error fetching package:', error);
        return null;
      }
    },
  
    // Render regular product modal
    renderRegularModal() {
      const product = this.state.currentProduct;
      
      // Update modal UI
      $('#modal-product-name').text(product.product_name);
      $('#modal-product-image').attr('src', 
        `${window.location.origin}/resource/assets-frontend/dist/product/${product.product_pict}`
      );
      $('#modal-product-description').text(product.product_desc);
      $('#modal-product-price').text(this.formatPrice(product.price));
      $('#modal-product-stock').text(product.stock);
      
      // Set max quantity
      $('.product-qty').attr('max', product.stock).val(1);
      
      // Show regular content, hide package content
      $('#regular-product-content').show();
      $('#package-product-content').hide();
      $('#add-to-cart-regular').show();
      $('#add-to-cart-package').hide();
    },
  
    // Render package modal
    renderPackageModal() {
      const pkg = this.state.currentPackage;
      
      // Basic info
      $('#modal-product-name').text(pkg.product_name);
      $('#modal-package-image').attr('src',
        `${window.location.origin}/resource/assets-frontend/dist/product/${pkg.product_pict}`
      );
      $('#modal-package-description').text(pkg.product_desc);
      $('#modal-package-base-price').text(this.formatPrice(pkg.base_price));
  
      // Render categories
      this.renderPackageCategories();
      
      // Render product selection
      this.renderPackageProducts();
      
      // Render excluded products
      this.renderExcludedProducts();
      
      // Show package content, hide regular content
      $('#regular-product-content').hide();
      $('#package-product-content').show();
      $('#add-to-cart-regular').hide();
      $('#add-to-cart-package').show();
      
      // Initial summary update
      this.updatePackageSummary();
    },
  
    // Render package categories
    renderPackageCategories() {
      const categoriesHtml = this.state.currentPackage.categories.map(cat => `
        <div class="badge bg-primary p-2">
          <div>${cat.name}</div>
          <small>Min: ${cat.quantity} item</small>
          <div class="progress mt-1" style="height: 3px;">
            <div class="progress-bar" id="cat-progress-${cat.id}" style="width: 0%"></div>
          </div>
        </div>
      `).join('');
      
      $('#package-categories').html(categoriesHtml);
    },
  
    // Render package products
    renderPackageProducts() {
      const productsHtml = this.state.currentPackage.categories.map(cat => {
        const categoryProducts = this.state.currentPackage.products.filter(
          p => p.package_category_id === cat.id
        );
  
        return `
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button" type="button" 
                      data-bs-toggle="collapse" 
                      data-bs-target="#cat-${cat.id}">
                ${cat.name}
              </button>
            </h2>
            <div id="cat-${cat.id}" class="accordion-collapse collapse show">
              <div class="accordion-body">
                ${this.renderCategoryProducts(categoryProducts)}
              </div>
            </div>
          </div>
        `;
      }).join('');
  
      $('#package-products-accordion').html(productsHtml);
    },
  
    // Render products for each category
    renderCategoryProducts(products) {
      return products.map(product => {
        const customPrice = this.getCustomPrice(product.product_id);
        return `
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
              <strong>${product.product_name}</strong>
              <br>
              <small class="text-muted">
                ${customPrice ? 'Harga Khusus: ' : 'Harga: '}
                ${this.formatPrice(customPrice?.price || product.price)}
              </small>
              <br>
              <small class="text-muted">Stok: ${product.stock}</small>
            </div>
            <div class="input-group" style="width: 120px;">
              <button class="btn btn-sm btn-outline-secondary decrease-qty" type="button">-</button>
              <input type="number" 
                     class="form-control form-control-sm package-item-qty" 
                     value="${this.state.selectedPackageItems[product.product_id] || 0}"
                     min="0" 
                     max="${product.stock}"
                     data-product-id="${product.product_id}"
                     data-category-id="${product.package_category_id}">
              <button class="btn btn-sm btn-outline-secondary increase-qty" type="button">+</button>
            </div>
          </div>
        `;
      }).join('');
    },
  
   // Render excluded products
   renderExcludedProducts() {
    const excluded = this.state.currentPackage.excluded_products;
    if (!excluded?.length) {
      $('#excluded-products-section').hide();
      return;
    }
  
    const html = excluded.map(product => `
      <div class="col-6 col-md-4">
        <div class="card h-100">
          <img src="${window.location.origin}/resource/assets-frontend/dist/product/${product.product_pict}"
               class="card-img-top p-2"
               alt="${product.product_name}"
               style="height: 100px; object-fit: contain;">
          <div class="card-body p-2">
            <small class="card-title d-block text-truncate">
              ${product.product_name}
            </small>
          </div>
        </div>
      </div>
    `).join('');
  
    $('#excluded-products-section').show();
    $('#excluded-products-list').html(html);
  },
  
  // Handle package quantity change
  handlePackageQuantityChange(event) {
    const $input = $(event.target);
    const productId = $input.data('product-id');
    const quantity = parseInt($input.val());
    const categoryId = $input.data('category-id');
  
    // Update selected items
    this.state.selectedPackageItems[productId] = quantity;
  
    // Update category progress
    this.updateCategoryProgress(categoryId);
    
    // Update summary
    this.updatePackageSummary();
  },
  
  // Update category progress
  updateCategoryProgress(categoryId) {
    const category = this.state.currentPackage.categories.find(
      c => c.id === categoryId
    );
    
    const categoryProducts = this.state.currentPackage.products.filter(
      p => p.package_category_id === categoryId
    );
  
    const selectedCount = categoryProducts.reduce((sum, product) => 
      sum + (this.state.selectedPackageItems[product.product_id] || 0), 0
    );
  
    const progress = Math.min((selectedCount / category.quantity) * 100, 100);
    $(`#cat-progress-${categoryId}`).css('width', `${progress}%`);
  },
  
  // Update package summary
  updatePackageSummary() {
    let total = this.state.currentPackage.base_price;
    const items = [];
  
    Object.entries(this.state.selectedPackageItems).forEach(([productId, qty]) => {
      if (qty > 0) {
        const product = this.state.currentPackage.products.find(
          p => p.product_id === parseInt(productId)
        );
        const customPrice = this.getCustomPrice(productId);
        const price = (customPrice?.price || product.price) * qty;
        
        total += price;
        items.push(`
          <div class="d-flex justify-content-between mb-2">
            <span>${qty}x ${product.product_name}</span>
            <span>${this.formatPrice(price)}</span>
          </div>
        `);
      }
    });
  
    // Update summary display
    $('#package-summary').html(items.join(''));
    $('#package-total').text(this.formatPrice(total));
  
    // Enable/disable add button based on validation
    const isValid = this.validatePackageRequirements();
    $('#add-to-cart-package').prop('disabled', !isValid);
  },
  
  // Validate package requirements
  validatePackageRequirements() {
    return this.state.currentPackage.categories.every(cat => {
      const categoryProducts = this.state.currentPackage.products.filter(
        p => p.package_category_id === cat.id
      );
      
      const totalSelected = categoryProducts.reduce((sum, product) => 
        sum + (this.state.selectedPackageItems[product.product_id] || 0), 0
      );
      
      return totalSelected >= cat.quantity;
    });
  },
  
  // Add regular product to cart
  async addRegularToCart() {
    try {
      const quantity = parseInt($('.product-qty').val());
      const notes = $('#product-note').val();
  
      if (quantity < 1) {
        throw new Error('Jumlah produk minimal 1');
      }
  
      Swal.showLoading();
  
      const response = await $.ajax({
        type: 'POST',
        url: `${window.location.origin}/order`,
        contentType: 'application/json',
        data: JSON.stringify({
          action: 2, // Add regular product
          orderId: $('#order-id').val(),
          productId: this.state.currentProduct.product_id,
          quantity: quantity,
          notes: notes
        })
      });
  
      if (response.success) {
        this.modalInstance.hide();
        this.updateCart();
        Swal.fire('Sukses', 'Produk berhasil ditambahkan ke keranjang', 'success');
      } else {
        throw new Error(response.message);
      }
    } catch (error) {
      Swal.fire('Error', error.message || 'Gagal menambahkan produk', 'error');
    }
  },
  
  // Add package to cart
  async addPackageToCart() {
    try {
      if (!this.validatePackageRequirements()) {
        throw new Error('Mohon lengkapi jumlah minimum setiap kategori');
      }
  
      Swal.showLoading();
  
      const packageItems = Object.entries(this.state.selectedPackageItems)
        .filter(([_, qty]) => qty > 0)
        .map(([productId, quantity]) => ({
          productId: parseInt(productId),
          quantity
        }));
  
      const response = await $.ajax({
        type: 'POST',
        url: `${window.location.origin}/order`,
        contentType: 'application/json',
        data: JSON.stringify({
          action: 3, // Add package
          orderId: $('#order-id').val(),
          packageId: this.state.currentPackage.product_id,
          products: packageItems
        })
      });
  
      if (response.success) {
        this.modalInstance.hide();
        this.updateCart();
        Swal.fire('Sukses', 'Paket berhasil ditambahkan ke keranjang', 'success');
      } else {
        throw new Error(response.message);
      }
    } catch (error) {
      Swal.fire('Error', error.message || 'Gagal menambahkan paket', 'error');
    }
  },
  
  // Helper Methods
  formatPrice(price) {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR'
    }).format(price);
  },
  
  getCustomPrice(productId) {
    return this.state.currentPackage?.custom_prices?.find(
      cp => cp.product_id === parseInt(productId)
    );
  },
  
  resetPackageState() {
    this.state.selectedPackageItems = {};
  },
  
  async updateCart() {
    try {
      const params = new URLSearchParams(window.location.search);
      const response = await $.ajax({
        type: 'GET',
        url: `${window.location.origin}/order/countCart?${params.toString()}`
      });
      
      if (response.success) {
        $('#count-cart').text(response.data.metrics.total_items);
      }
    } catch (error) {
      console.error('Error updating cart:', error);
    }
  }
  };
  
  // Initialize when document is ready
  $(document).ready(() => {
  ProductManager.init();
  });