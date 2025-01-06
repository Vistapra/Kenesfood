const OrderManager = {
    /**
     * Application state management
     */
    state: {
        // Cart tracking
        cartCount: 0,
        lastUpdate: null,
        
        // Retry mechanism for API calls
        retryAttempts: 0,
        maxRetries: 3,
        retryDelay: 1000,
        
        // Initialization status
        initialized: false,
        
        // Session tracking
        sessionData: null,
        orderId: null
    },

    /**
     * Configuration settings
     */
    config: {
        // API Endpoints
        endpoints: {
            CART_COUNT: '/order/countCart',
            CART_DETAILS: '/order/cart',
            ADD_TO_CART: '/order/add',
            REMOVE_CART_ITEM: '/order/removeCartItem',
            PROCESS_ORDER: '/order/doneOrder'
        },
        
        // UI Selectors
        selectors: {
            categorySelect: '#category-select',
            productSearch: '#product-search',
            cartButton: '#show-cart',
            cartCountBadge: '#count-cart',
            productListing: '#product-listing',
            cartModal: '#cart-modal',
            orderButton: '#order'
        },
        
        // Validation and UI settings
        validation: {
            minQuantity: 1,
            maxQuantity: 10
        }
    },

    /**
     * Initialize the OrderManager system
     * @returns {Promise<boolean>} Initialization success status
     */
    async init() {
        console.group('OrderManager Initialization');
        try {
            // Validate critical dependencies
            this.validateDependencies();

            // Validate initialization requirements
            if (!this.validateInitialization()) {
                throw new Error('Initialization validation failed');
            }

            // Setup event listeners
            this.setupEventListeners();

            // Initialize cart
            await this.initializeCart();

            // Mark as initialized
            this.state.initialized = true;

            await this.loadCategories();
            this.state.loadCategories = true;

            console.log('OrderManager initialized successfully');
            return true;
        } catch (error) {
            console.error('Initialization Error:', error);
            this.handleInitializationError(error);
            return false;
        } finally {
            console.groupEnd();
        }
    },

    /**
     * Validate critical dependencies
     * @throws {Error} If critical dependencies are missing
     */
    validateDependencies() {
        const dependencies = {
            jQuery: typeof jQuery !== 'undefined',
            Bootstrap: typeof bootstrap !== 'undefined',
            SweetAlert2: typeof Swal !== 'undefined'
        };

        const missingDependencies = Object.entries(dependencies)
            .filter(([_, exists]) => !exists)
            .map(([name]) => name);

        if (missingDependencies.length > 0) {
            throw new Error(`Missing dependencies: ${missingDependencies.join(', ')}`);
        }
    },

    /**
     * Validate initialization requirements
     * @returns {boolean} Validation result
     */
    validateInitialization() {
        const requiredElements = {
            categorySelect: $(this.config.selectors.categorySelect),
            productSearch: $(this.config.selectors.productSearch),
            cartButton: $(this.config.selectors.cartButton),
            cartCountBadge: $(this.config.selectors.cartCountBadge),
            productListing: $(this.config.selectors.productListing),
            cartModal: $(this.config.selectors.cartModal),
            orderButton: $(this.config.selectors.orderButton)
        };

        // Check for missing elements
        const missingElements = Object.entries(requiredElements)
            .filter(([_, $el]) => $el.length === 0)
            .map(([name]) => name);

        if (missingElements.length > 0) {
            console.error('Missing required elements:', missingElements);
            return false;
        }

        // Validate URL parameters
        const params = new URLSearchParams(window.location.search);
        const requiredParams = ['outletId', 'tableId', 'brand'];
        
        const missingParams = requiredParams.filter(param => !params.get(param));
        
        if (missingParams.length > 0) {
            console.error('Missing required URL parameters:', missingParams);
            return false;
        }

        return true;
    },

    /**
     * Setup event listeners for order interactions
     */
    setupEventListeners() {
        // Category and search handlers
        $(this.config.selectors.categorySelect).on('change', this.handleCategoryFilter.bind(this));
        $(this.config.selectors.productSearch).on('input', this.handleProductSearch.bind(this));

        // Cart interactions
        $(this.config.selectors.cartButton).on('click', this.loadCart.bind(this));
        $(document).on('click', '.remove-cart-item', this.handleCartItemRemoval.bind(this));
        $(this.config.selectors.orderButton).on('click', this.processOrder.bind(this));

        // Additional cart tracking
        this.setupCartRefresh();
    },

    /**
     * Handle category filtering
     * @param {Event} e - Category selection event
     */
    handleCategoryFilter(e) {
        const categoryId = $(e.target).val();
        this.filterProducts(categoryId);
    },

    /**
     * Handle product search
     * @param {Event} e - Search input event
     */
    handleProductSearch(e) {
        const searchTerm = $(e.target).val().toLowerCase();
        this.searchProducts(searchTerm);
    },

    /**
     * Initialize cart tracking
     */
    async initializeCart() {
        try {
            await this.updateCartCount(true);
        } catch (error) {
            console.error('Cart Initialization Error:', error);
            this.handleCartError(error);
        }
    },

    /**
     * Setup periodic cart refresh
     */
    setupCartRefresh() {
        // Refresh cart count every minute
        setInterval(() => {
            if (this.state.initialized) {
                this.updateCartCount();
            }
        }, 60000);
    },

    /**
     * Fetch cart count with retry mechanism
     * @param {boolean} [isInitial=false] - Initial cart count fetch
     * @returns {Promise<boolean>} Cart update success status
     */
    async updateCartCount(isInitial = false) {
        if (!this.state.initialized && !isInitial) {
            return false;
        }

        try {
            const params = new URLSearchParams(window.location.search);

            const response = await this.fetchWithRetry(
                `${window.location.origin}${this.config.endpoints.CART_COUNT}`,
                {
                    method: 'GET',
                    data: params.toString()
                }
            );

            if (response.success) {
                const cartCount = response.data.metrics.total_items;
                this.updateCartUI(cartCount);

                // Update session info if available
                if (response.data.session) {
                    this.handleSessionUpdate(response.data.session);
                }

                this.state.cartCount = cartCount;
                this.state.lastUpdate = new Date();
                return true;
            }

            throw new Error(response.message || 'Failed to update cart');
        } catch (error) {
            console.error('Cart Count Update Error:', error);

            if (!isInitial) {
                this.handleCartError(error);
            }

            return false;
        }
    },

    /**
     * Update cart count UI
     * @param {number} count - Cart item count
     */
    updateCartUI(count) {
        const $badge = $(this.config.selectors.cartCountBadge);
        const currentCount = parseInt($badge.text()) || 0;

        if (currentCount !== count) {
            $badge.text(count);

            // Animate badge if count increased
            if (count > currentCount) {
                $badge.addClass('badge-pop');
                setTimeout(() => $badge.removeClass('badge-pop'), 300);
            }
        }
    },

    /**
     * Load cart details
     */
    async loadCart() {
        try {
            this.showLoading(true);
            const params = new URLSearchParams(window.location.search);

            const response = await this.fetchWithRetry(
                `${window.location.origin}${this.config.endpoints.CART_DETAILS}`,
                {
                    method: 'GET',
                    data: params.toString()
                }
            );

            if (response.success) {
                this.renderCart(response.data);
                $(this.config.selectors.cartModal).modal('show');
            } else {
                throw new Error(response.message);
            }
        } catch (error) {
            console.error('Cart Loading Error:', error);
            this.handleCartError(error);
        } finally {
            this.showLoading(false);
        }
    },

    async loadCategories() {
    try {
        const params = new URLSearchParams(window.location.search);
        const response = await $.ajax({
            url: `${window.location.origin}/order/list`,
            data: params.toString(),
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        
        if (response.success && response.data.categories) {
            this.state.categories = response.data.categories;
            this.renderCategories();
        }
    } catch (error) {
        console.error('Failed to load categories:', error);
    }
},

    /**
     * Render cart contents
     * @param {Object} data - Cart data
     */
    renderCart(data) {
        const $container = $('#container-cart');

        if (!data.cart || !data.cart.items || data.cart.items.length === 0) {
            this.renderEmptyCart($container);
            return;
        }

        this.renderCartItems($container, data);
    },

    /**
     * Render empty cart
     * @param {jQuery} $container - Cart container element
     */
    renderEmptyCart($container) {
        $container.html(`
            <div class="text-center py-5">
                <i class="bi bi-cart-x fs-1 text-muted"></i>
                <p class="mt-3">Keranjang Anda kosong</p>
            </div>
        `);
        $(this.config.selectors.orderButton).prop('disabled', true);
    },

    /**
     * Render cart items
     * @param {jQuery} $container - Cart container element
     * @param {Object} data - Cart data
     */
    renderCartItems($container, data) {
        let cartHtml = this.generateCartHeader();

        data.cart.items.forEach(item => {
            cartHtml += this.generateCartItemRow(item);
        });

        cartHtml += this.generateCartFooter(data.cart);

        $container.html(cartHtml);
        this.updateCartSummary(data.cart);
        $(this.config.selectors.orderButton).prop('disabled', false);
    },

    /**
     * Generate cart header HTML
     * @returns {string} Cart header HTML
     */
    generateCartHeader() {
        return `
            <div class="cart-items">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Harga</th>
                                <th class="text-end">Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
        `;
    },

    /**
     * Generate cart item row HTML
     * @param {Object} item - Cart item details
     * @returns {string} Cart item row HTML
     */
    generateCartItemRow(item) {
        const imageUrl = `${window.location.origin}/resource/assets-frontend/dist/product/${item.product_image}`;

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
        `;
    },

    /**
     * Generate package details HTML
     * @param {Array} items - Package items
     * @returns {string} Package details HTML
     */
    generatePackageDetails(items) {
        if (!items || items.length === 0) return '';

        return `
            <div class="package-items mt-2">
                <small class="d-block text-muted mb-1">Isi Paket:</small>
                ${items.map(
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
                ).join('')}
            </div>
        `;
    },

    /**
     * Generate cart footer HTML
     * @param {Object} cart - Cart details
     * @returns {string} Cart footer HTML
     */
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
        `;
    },

    /**
     * Update cart summary
     * @param {Object} cart - Cart details
     */
    updateCartSummary(cart) {
        $('#cart-total-items').text(cart.total_items);
        $('#cart-total-amount').text(this.formatPrice(cart.total_amount));
    },

    /**
     * Remove cart item
     * @param {Event} e - Remove cart item event
     */
    async handleCartItemRemoval(e) {
        try {
            const $btn = $(e.currentTarget);
            const productId = $btn.data('product-id');
            const count = $btn.data('count') || 1;

            // Konfirmasi penghapusan item
            const confirmResult = await Swal.fire({
                title: 'Hapus Item?',
                text: 'Item ini akan dihapus dari keranjang Anda',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                reverseButtons: true
            });

            if (!confirmResult.isConfirmed) return;

            // Tampilkan loading
            Swal.showLoading();

            // Ambil parameter URL
            const params = new URLSearchParams(window.location.search);

            // Kirim permintaan penghapusan
            const response = await this.fetchWithRetry(
                `${window.location.origin}${this.config.endpoints.REMOVE_CART_ITEM}`,
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
            );

            if (response.success) {
                // Muat ulang keranjang
                await this.loadCart();
                
                // Perbarui jumlah item di keranjang
                await this.updateCartCount();
                
                // Tampilkan konfirmasi
                Swal.fire({
                    title: 'Berhasil',
                    text: 'Item berhasil dihapus dari keranjang',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                throw new Error(response.message || 'Gagal menghapus item');
            }
        } catch (error) {
            console.error('Kesalahan saat menghapus item:', error);
            this.handleCartError(error);
        }
    },

    /**
     * Proses pesanan akhir
     */
    async processOrder() {
        try {
            // Ambil parameter URL
            const params = new URLSearchParams(window.location.search);

            // Konfirmasi pemrosesan pesanan
            const confirmResult = await Swal.fire({
                title: 'Proses Pesanan?',
                text: 'Pesanan Anda akan diproses dan tidak dapat dibatalkan',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Proses',
                cancelButtonText: 'Batal',
                reverseButtons: true
            });

            if (!confirmResult.isConfirmed) return;

            // Tampilkan loading
            Swal.showLoading();

            // Kirim permintaan pemrosesan pesanan
            const response = await this.fetchWithRetry(
                `${window.location.origin}${this.config.endpoints.PROCESS_ORDER}`,
                {
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        outletId: params.get('outletId'),
                        tableId: params.get('tableId'),
                        brand: params.get('brand')
                    })
                }
            );

            if (response.success) {
                // Tutup modal keranjang
                $(this.config.selectors.cartModal).modal('hide');

                // Tampilkan konfirmasi pesanan
                await Swal.fire({
                    title: 'Pesanan Berhasil!',
                    html: this.generateOrderConfirmation(response.data),
                    icon: 'success',
                    confirmButtonText: 'OK'
                });

                // Muat ulang halaman
                window.location.reload();
            } else {
                throw new Error(response.message || 'Gagal memproses pesanan');
            }
        } catch (error) {
            console.error('Kesalahan saat memproses pesanan:', error);
            this.handleCartError(error);
        }
    },

    /**
     * Generate konfirmasi pesanan
     * @param {Object} data - Data pesanan
     * @returns {string} HTML konfirmasi
     */
    generateOrderConfirmation(data) {
        return `
            <div class="text-start">
                <p><strong>Nomor Pesanan:</strong> ${data.receipt_number}</p>
                <p><strong>Total Item:</strong> ${data.summary.total_items}</p>
                <p><strong>Total Pembayaran:</strong> ${this.formatPrice(data.summary.total_amount)}</p>
            </div>
        `;
    },

    /**
     * Filter produk berdasarkan kategori
     * @param {string} categoryId - ID kategori
     */
    filterProducts(categoryId) {
        if (categoryId === 'all') {
            $('.product-card').show();
            $('.product-category').show();
        } else {
            $('.product-card').hide();
            $('.product-category').hide();
            
            $(`.product-card[data-category-id="${categoryId}"]`)
                .show()
                .closest('.product-category')
                .show();
        }

        this.toggleNoResults();
    },

    /**
     * Cari produk berdasarkan kata kunci
     * @param {string} searchTerm - Kata kunci pencarian
     */
    searchProducts(searchTerm) {
        $('.product-card').each(function() {
            const $card = $(this);
            const productName = $card.find('.product-name').text().toLowerCase();
            const description = $card
                .find('.product-description')
                .text()
                .toLowerCase();

            // Cari di nama produk dan deskripsi
            const matches = 
                productName.includes(searchTerm) || 
                description.includes(searchTerm);

            $card.toggle(matches);

            // Tampilkan/sembunyikan header kategori
            const $category = $card.closest('.product-category');
            const hasVisibleProducts = 
                $category.find('.product-card:visible').length > 0;
            $category.toggle(hasVisibleProducts);
        });

        // Tampilkan pesan jika tidak ada hasil
        this.toggleNoResults();
    },

    /**
     * Tampilkan/sembunyikan pesan tidak ada hasil
     */
    toggleNoResults() {
        const hasVisibleProducts = $('.product-card:visible').length > 0;
        const $noResults = $('#no-results-message');

        if (!hasVisibleProducts && $noResults.length === 0) {
            $('.product-listing').append(`
                <div id="no-results-message" class="col-12 text-center py-5">
                    <div class="alert alert-info">
                        <i class="bi bi-search me-2"></i>Tidak ada produk ditemukan
                    </div>
                </div>
            `);
        } else if (hasVisibleProducts) {
            $noResults.remove();
        }
    },

    /**
     * Format harga dalam Rupiah
     * @param {number} amount - Jumlah harga
     * @returns {string} Harga yang diformat
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
     * Fetch dengan mekanisme retry
     * @param {string} url - URL permintaan
     * @param {Object} options - Opsi permintaan
     * @param {number} [attempt=0] - Jumlah percobaan
     * @returns {Promise<Object>} Respon dari server
     */
    async fetchWithRetry(url, options = {}, attempt = 0) {
        try {
            const response = await $.ajax({
                url,
                ...options,
                timeout: 5000
            });

            // Periksa struktur respons
            if (typeof response === 'object' && 'success' in response) {
                return response;
            }

            throw new Error('Format respons tidak valid');
        } catch (error) {
            console.warn(`Permintaan gagal (percobaan ${attempt + 1}):`, error);

            const maxRetries = this.state.maxRetries;
            if (attempt < maxRetries) {
                const delay = this.state.retryDelay * Math.pow(2, attempt);

                await new Promise(resolve => setTimeout(resolve, delay));
                return this.fetchWithRetry(url, options, attempt + 1);
            }

            // Pada kegagalan akhir, periksa jenis kesalahan
            if (error.status === 500) {
                const errorMessage = this.parseServerError(error);
                throw new Error(errorMessage || 'Kesalahan server internal');
            }

            throw error;
        }
    },

    /**
     * Parse kesalahan server
     * @param {Object} error - Objek kesalahan
     * @returns {string|null} Pesan kesalahan
     */
    parseServerError(error) {
        try {
            const responseText = error.responseText;

            // Coba ekstrak pesan kesalahan dari output PHP
            if (responseText.includes('Message:')) {
                const messageMatch = responseText.match(/Message:\s+(.*?)\n/);
                if (messageMatch && messageMatch[1]) {
                    return messageMatch[1].trim();
                }
            }

            // Coba parsing sebagai JSON
            const jsonResponse = JSON.parse(responseText);
            if (jsonResponse.message) {
                return jsonResponse.message;
            }

            return null;
        } catch (e) {
            console.error('Kesalahan parsing kesalahan server:', e);
            return null;
        }
    },

    /**
     * Tangani kesalahan inisialisasi
     * @param {Error} error - Kesalahan inisialisasi
     */
    handleInitializationError(error) {
        Swal.fire({
            title: 'Kesalahan Sistem',
            text: 'Gagal memuat sistem pemesanan. Silakan refresh halaman.',
            icon: 'error',
            confirmButtonText: 'Muat Ulang'
        }).then(() => {
            window.location.reload();
        });
    },

    /**
     * Tangani kesalahan cart
     * @param {Error} error - Kesalahan cart
     */
    handleCartError(error) {
        // Periksa kondisi kesalahan spesifik
        if (!navigator.onLine) {
            this.showToast('warning', 'Koneksi jaringan terputus. Mencoba kembali...');
            return;
        }

        if (error.status === 401 || error.status === 403) {
            this.handleSessionExpired();
            return;
        }

        if (error.status === 500) {
            console.error('Detail Kesalahan Server:', error.responseText);
            this.showToast('error', 'Kesalahan server. Silakan coba lagi nanti.');
            return;
        }

        // Penanganan kesalahan generik
        this.showToast('error', error.message || 'Gagal memperbarui keranjang');
    },

    /**
     * Tangani sesi kedaluwarsa
     */
    handleSessionExpired() {
        Swal.fire({
            title: 'Sesi Berakhir',
            text: 'Sesi Anda telah berakhir. Halaman akan dimuat ulang.',
            icon: 'warning',
            confirmButtonText: 'Muat Ulang',
            allowOutsideClick: false
        }).then(() => {
            window.location.reload();
        });
    },

    /**
     * Tampilkan toast
     * @param {string} icon - Ikon toast
     * @param {string} message - Pesan toast
     */
    showToast(icon, message) {
        Swal.fire({
            text: message,
            icon: icon,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
    },

    /**
     * Tampilkan loading
     * @param {boolean} [show=true] - Tampilkan/sembunyikan loading
     */
    showLoading(show = true) {
        if (show) {
            Swal.showLoading();
        } else {
            Swal.close();
        }
    }
};

// Inisialisasi saat dokumen siap
$(document).ready(() => {
    try {
        // Inisialisasi OrderManager
        if (OrderManager.validateInitialization()) {
            OrderManager.init();
            console.log('Aplikasi dimulai dengan sukses');
        }
    } catch (error) {
        console.error('Gagal memulai aplikasi:', error);
    }
});

// Pastikan kompatibilitas modul
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OrderManager;
}