/**
 * Central State Management
 */
const AppState = {
    session: {
        id: null,
        status: null,
        expiryTime: null,
        customer: {
            name: null,
            table: null
        },
        outlet: {
            id: null,
            name: null,
            operatingHours: {
                open: null,
                close: null
            }
        }
    },
    cart: {
        items: [],
        total: {
            amount: 0,
            quantity: 0,
            uniqueItems: 0
        },
        lastUpdate: null
    },
    products: {
        categories: [],
        items: [],
        currentProduct: null,
        currentPackage: null,
        filters: {
            category: null,
            search: ''
        }
    },
    ui: {
        loading: false,
        currentView: 'session',  // 'session' | 'order'
        modal: {
            current: null,
            data: null
        },
        alerts: []
    },
    location: {
        verified: false,
        coordinates: null
    }
};

/**
 * Event Bus for Component Communication
 */
const EventBus = {
    listeners: {},
    
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    },
    
    emit(event, data) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => callback(data));
        }
    },
    
    off(event, callback) {
        if (this.listeners[event]) {
            this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
        }
    }
};

/**
 * API Service Implementation
 */
const ApiService = {
    baseUrl: window.location.origin,
    endpoints: {
        SESSION: '/order/session',
        CREATE_SESSION: '/order/createSession',
        PRODUCT_LIST: '/order/list',
        CART: '/order/cart',
        CART_COUNT: '/order/countCart',
        ADD_TO_CART: '/order/add',
        REMOVE_CART_ITEM: '/order/removeCartItem',
        PROCESS_ORDER: '/order/doneOrder'
    },

    async request(endpoint, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            timeout: 10000
        };

        try {
            const response = await $.ajax({
                url: `${this.baseUrl}${endpoint}`,
                ...defaultOptions,
                ...options
            });

            return this.handleResponse(response);
        } catch (error) {
            throw this.handleError(error);
        }
    },

    handleResponse(response) {
        if (!response.success) {
            throw new Error(response.message || 'Request failed');
        }
        return response;
    },

    handleError(error) {
        if (!navigator.onLine) {
            return new Error('NO_INTERNET');
        }

        if (error.status === 401 || error.status === 403) {
            EventBus.emit('session:expired');
            return new Error('SESSION_EXPIRED');
        }

        return error;
    },

    getUrlParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            outletId: params.get('outletId'),
            tableId: params.get('tableId'),
            brand: params.get('brand')
        };
    },

    // Session APIs
    async validateSession() {
        return this.request(this.endpoints.SESSION, {
            method: 'GET',
            data: this.getUrlParams()
        });
    },

    async createSession(data) {
        return this.request(this.endpoints.CREATE_SESSION, {
            method: 'POST',
            data: JSON.stringify({
                ...this.getUrlParams(),
                ...data
            })
        });
    },

    // Product APIs
    async getProducts(filters = {}) {
        const params = {
            ...this.getUrlParams(),
            ...filters
        };

        return this.request(this.endpoints.PRODUCT_LIST, {
            method: 'GET',
            data: params
        });
    },

    // Cart APIs
    async getCartCount() {
        return this.request(this.endpoints.CART_COUNT, {
            method: 'GET',
            data: this.getUrlParams()
        });
    },

    async getCart() {
        return this.request(this.endpoints.CART, {
            method: 'GET',
            data: this.getUrlParams()
        });
    },

    async addToCart(data) {
        return this.request(this.endpoints.ADD_TO_CART, {
            method: 'POST',
            data: JSON.stringify(data)
        });
    },

    async removeFromCart(data) {
        return this.request(this.endpoints.REMOVE_CART_ITEM, {
            method: 'POST',
            data: JSON.stringify({
                ...this.getUrlParams(),
                ...data
            })
        });
    },

    async processOrder() {
        return this.request(this.endpoints.PROCESS_ORDER, {
            method: 'POST',
            data: JSON.stringify(this.getUrlParams())
        });
    }
};

/**
 * Session Manager Implementation
 */
const SessionManager = {
    config: {
        sessionDuration: 15 * 60 * 1000, // 15 minutes
        warningThreshold: 5 * 60 * 1000, // 5 minutes
        extendInterval: 60 * 1000, // 1 minute
        validation: {
            minNameLength: 3,
            maxNameLength: 50,
            minPasscodeLength: 4,
            maxPasscodeLength: 10
        }
    },

    timers: {
        session: null,
        extend: null
    },

    async init() {
        try {
            await this.setupLocationServices();
            await this.validateInitialSession();
            this.setupEventListeners();
            return true;
        } catch (error) {
            console.error('Session initialization error:', error);
            return false;
        }
    },

    async setupLocationServices() {
        if (!navigator.geolocation) {
            throw new Error('Geolocation not supported');
        }

        try {
            const position = await this.getCurrentPosition();
            AppState.location.coordinates = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude
            };
            AppState.location.verified = true;
            this.updateLocationUI('success');
        } catch (error) {
            this.updateLocationUI('error', error.message);
            throw error;
        }
    },

    getCurrentPosition() {
        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0
            });
        });
    },

    setupEventListeners() {
        $('#session-form').on('submit', this.handleSessionCreate.bind(this));
        $('#resume-form').on('submit', this.handleSessionResume.bind(this));
        
        EventBus.on('session:expired', this.handleSessionExpired.bind(this));
        EventBus.on('session:extend', this.extendSession.bind(this));
    },

    async validateInitialSession() {
        try {
            const response = await ApiService.validateSession();
            
            if (response.success && response.data.session) {
                await this.startSession(response.data);
            } else {
                this.showSessionCreation();
            }
        } catch (error) {
            console.error('Kesalahan validasi sesi:', error);
            this.showSessionCreation();
        }
    },

    // Tambahkan metode untuk menampilkan pembuatan sesi
    showSessionCreation() {
        $('#session-creation').show();
        $('#resume-session').hide();
        $('#active-session').hide();
    },

    async handleSessionCreate(e) {
        e.preventDefault();
        
        try {
            if (!this.validateSessionInput()) {
                return;
            }

            const sessionData = {
                name: $('#customer-name').val().trim(),
                passcode: $('#passcode').val().trim(),
                ...AppState.location.coordinates
            };

            const response = await ApiService.createSession(sessionData);
            
            if (response.success) {
                await this.startSession(response.data);
                await this.showSuccessMessage('Sesi berhasil dibuat');
            }
        } catch (error) {
            console.error('Session creation error:', error);
            this.showErrorMessage(error.message);
        }
    },

    async handleSessionResume(e) {
        e.preventDefault();
        
        try {
            const passcode = $('#resume-passcode').val().trim();
            
            if (!this.validatePasscode(passcode)) {
                return;
            }

            const response = await ApiService.createSession({ passcode });
            
            if (response.success) {
                await this.startSession(response.data);
                await this.showSuccessMessage('Sesi dilanjutkan');
                window.location.reload();
            }
        } catch (error) {
            console.error('Session resume error:', error);
            this.showErrorMessage(error.message);
        }
    },

    validateSessionInput() {
        const name = $('#customer-name').val().trim();
        const passcode = $('#passcode').val().trim();
        const { validation } = this.config;

        if (name.length < validation.minNameLength || name.length > validation.maxNameLength) {
            this.showErrorMessage(`Nama harus antara ${validation.minNameLength} - ${validation.maxNameLength} karakter`);
            return false;
        }

        if (!this.validatePasscode(passcode)) {
            return false;
        }

        return true;
    },

    validatePasscode(passcode) {
        const { validation } = this.config;
        
        if (passcode.length < validation.minPasscodeLength || 
            passcode.length > validation.maxPasscodeLength) {
            this.showErrorMessage(
                `Passcode harus antara ${validation.minPasscodeLength} - ${validation.maxPasscodeLength} karakter`
            );
            return false;
        }

        return true;
    },

    async startSession(data) {
        // Update AppState
        AppState.session = {
            id: data.session.id,
            status: data.session.status,
            expiryTime: data.session.expire_at,
            customer: {
                name: data.session.name,
                table: data.table.number
            },
            outlet: {
                id: data.outlet.id,
                name: data.outlet.name,
                operatingHours: data.outlet.operating_hours
            }
        };

        // Update UI
        this.updateSessionUI();
        
        // Start timers
        this.startSessionTimer(new Date(data.session.expire_at));
        this.startExtendTimer();

        // Change view
        AppState.ui.currentView = 'order';
        this.updateViewVisibility();

        // Initialize order system
        await OrderManager.init();

        EventBus.emit('session:started', data);
    },

    startSessionTimer(expireTime) {
        if (this.timers.session) {
            clearInterval(this.timers.session);
        }

        const updateTimer = () => {
            const now = new Date();
            const timeLeft = expireTime - now;

            if (timeLeft <= 0) {
                this.handleSessionExpired();
                return;
            }

            this.updateTimerDisplay(timeLeft);

            if (timeLeft <= this.config.warningThreshold) {
                this.showSessionWarning(Math.floor(timeLeft / 60000));
            }
        };

        updateTimer();
        this.timers.session = setInterval(updateTimer, 1000);
    },

    startExtendTimer() {
        if (this.timers.extend) {
            clearInterval(this.timers.extend);
        }

        this.timers.extend = setInterval(
            () => EventBus.emit('session:extend'),
            this.config.extendInterval
        );
    },

    async extendSession() {
        try {
            const response = await ApiService.validateSession();
            
            if (response.success && response.data.session) {
                this.startSessionTimer(new Date(response.data.session.expire_at));
            }
        } catch (error) {
            console.error('Session extension error:', error);
        }
    },

    async handleSessionExpired() {
        this.cleanup();
        
        await Swal.fire({
            title: 'Sesi Berakhir',
            text: 'Sesi Anda telah berakhir. Halaman akan dimuat ulang.',
            icon: 'warning',
            confirmButtonText: 'OK',
            allowOutsideClick: false
        });

        window.location.reload();
    },

    cleanup() {
        clearInterval(this.timers.session);
        clearInterval(this.timers.extend);
        EventBus.off('session:expired');
        EventBus.off('session:extend');
    },

    // UI Helper Methods
    updateLocationUI(status, message) {
        const $verification = $('#location-verification');
        const $status = $('#location-status');
        const $progress = $('#location-progress');

        switch (status) {
            case 'checking':
                $verification.removeClass('alert-success alert-danger').addClass('alert-warning');
                $status.text('Memeriksa lokasi...');
                $progress.removeAttr('hidden');
                break;
            
            case 'success':
                $verification.removeClass('alert-warning alert-danger').addClass('alert-success');
                $status.text('Lokasi terverifikasi');
                $progress.attr('hidden', true);
                break;
            
            case 'error':
                $verification.removeClass('alert-warning alert-success').addClass('alert-danger');
                $status.text(message || 'Gagal memverifikasi lokasi');
                $progress.attr('hidden', true);
                break;
        }
    },

    updateSessionUI() {
        const { session } = AppState;
        
        // Update session info
        $('#active-customer').text(session.customer.name);
        $('#active-table').text(session.customer.table);
        $('#session-status').text(this.getStatusText(session.status));
        
        // Hide/show relevant sections
        $('#session-creation, #resume-session').hide();
        $('#active-session').show();
    },

    updateTimerDisplay(timeLeft) {
        const minutes = Math.floor(timeLeft / 60000);
        const seconds = Math.floor((timeLeft % 60000) / 1000);
        
        $('#session-timer').text(
            `${minutes}:${seconds.toString().padStart(2, '0')}`
        );
    },

    updateViewVisibility() {
        const isOrder = AppState.ui.currentView === 'order';
        
        $('#session-page').toggle(!isOrder);
        $('#order-page').toggle(isOrder);
    },

    getStatusText(status) {
        const statusMap = {
            'RESERVED': 'Dipesan',
            'ORDERED': 'Diproses',
            'COMPLETED': 'Selesai'
        };
        return statusMap[status] || status;
    },

    showSessionWarning(minutesLeft) {
        const $warning = $('#session-warning');
        if (!$warning.length) {
            $('#active-session').prepend(`
                <div id="session-warning" class="alert alert-warning">
                    <i class="bi bi-clock me-2"></i>
                    Sesi akan berakhir dalam ${minutesLeft} menit
                </div>
            `);
        } else {
            $warning.find('span').text(minutesLeft);
        }
    },

    showSuccessMessage(message) {
        return Swal.fire({
            title: 'Sukses',
            text: message,
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    },

    showErrorMessage(message) {
        return Swal.fire({
            title: 'Error',
            text: message,
            icon: 'error'
        });
    }
};

/**
 * Product Modal Manager
 */
const ProductModal = {
    config: {
        selectors: {
            modal: '#productModal',
            regularContent: '#regular-product-content',
            packageContent: '#package-product-content',
            modalTitle: '#modal-product-name',
            quantityInput: '.product-qty',
            noteInput: '#product-note',
            addToCartRegular: '#add-to-cart-regular',
            addToCartPackage: '#add-to-cart-package'
        },
        validation: {
            maxNoteLength: 200,
            quantityBuffer: 5
        }
    },

    async init() {
        this.setupEventListeners();
        this.setupModal();
        return true;
    },

    setupEventListeners() {
        $(document).on('click', '.view-product', this.handleProductClick.bind(this));
        
        // Quantity controls
        $(document).on('click', '.decrease-qty', this.handleQuantityDecrease.bind(this));
        $(document).on('click', '.increase-qty', this.handleQuantityIncrease.bind(this));
        $(document).on('change', this.config.selectors.quantityInput, this.handleQuantityChange.bind(this));
        
        // Note input
        $(document).on('input', this.config.selectors.noteInput, this.handleNoteInput.bind(this));
        
        // Add to cart buttons
        $(this.config.selectors.addToCartRegular).on('click', this.handleAddToCartRegular.bind(this));
        $(this.config.selectors.addToCartPackage).on('click', this.handleAddToCartPackage.bind(this));
    },

    setupModal() {
        const $modal = $(this.config.selectors.modal);
        
        $modal.on('show.bs.modal', () => {
            this.updateModalAccessibility(true);
        });

        $modal.on('shown.bs.modal', () => {
            $modal.find('.btn-close').focus();
        });

        $modal.on('hide.bs.modal', () => {
            this.updateModalAccessibility(false);
        });

        $modal.on('hidden.bs.modal', () => {
            this.resetModal();
        });
    },

    updateModalAccessibility(isShowing) {
        const $modal = $(this.config.selectors.modal);
        
        if (isShowing) {
            $modal.find('.modal-dialog').attr('role', 'document');
            $modal.removeAttr('aria-hidden');
            this.trapFocus($modal);
        } else {
            $modal.find('.modal-dialog').removeAttr('role');
            $modal.attr('aria-hidden', 'true');
            this.releaseFocus();
        }
    },

    trapFocus($modal) {
        const $focusableElements = $modal.find(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const $firstFocusable = $focusableElements.first();
        const $lastFocusable = $focusableElements.last();

        $modal.on('keydown.focusTrap', (e) => {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === $firstFocusable[0]) {
                        e.preventDefault();
                        $lastFocusable.focus();
                    }
                } else {
                    if (document.activeElement === $lastFocusable[0]) {
                        e.preventDefault();
                        $firstFocusable.focus();
                    }
                }
            }
        });
    },

    releaseFocus() {
        $(this.config.selectors.modal).off('keydown.focusTrap');
    },

    async handleProductClick(e) {
        e.preventDefault();
        
        try {
            const $card = $(e.currentTarget).closest('.product-card');
            const productId = $card.data('product-id');
            const isPackage = $card.data('is-package') === 1;

            console.log('Product clicked:', { productId, isPackage });

            const product = await this.fetchProductDetails(productId);
            
            if (product) {
                AppState.products.currentProduct = product;
                
                if (isPackage) {
                    await this.showPackageModal(product);
                } else {
                    await this.showRegularModal(product);
                }
            }
        } catch (error) {
            console.error('Product click error:', error);
            this.handleError(error);
        }
    },

    async fetchProductDetails(productId) {
        try {
            console.log('Fetching product details:', { productId });
            
            const response = await ApiService.getProducts({ productId });

            if (!response.success) {
                throw new Error('Failed to fetch product details');
            }

            // First check for single product response
            if (response.data.singleProduct) {
                return response.data.singleProduct;
            }

            // Then look in grouped products
            for (const category of Object.values(response.data.groupedProducts)) {
                const product = category.products.find(
                    p => p.product_id === parseInt(productId)
                );
                if (product) return product;
            }

            throw new Error('Product not found');
        } catch (error) {
            console.error('Product fetch error:', error);
            throw error;
        }
    },

    async showRegularModal(product) {
        const $modal = $(this.config.selectors.modal);
        const $regularContent = $(this.config.selectors.regularContent);
        const $packageContent = $(this.config.selectors.packageContent);

        // Update basic info
        $(this.config.selectors.modalTitle).text(product.product_name);
        $('#modal-product-image').attr('src', this.getProductImageUrl(product.product_pict));
        $('#modal-product-description').text(product.description || 'No description available');
        $('#modal-product-price').text(this.formatPrice(product.price_catalogue));
        $('#modal-product-stock').text(product.current_stock);

        // Setup quantity input
        const $quantityInput = $(this.config.selectors.quantityInput);
        const maxQuantity = Math.min(
            product.current_stock,
            product.current_stock + this.config.validation.quantityBuffer
        );
        
        $quantityInput
            .val(1)
            .attr('max', maxQuantity)
            .prop('disabled', product.current_stock < 1);

        // Update visibility
        $regularContent.show();
        $packageContent.hide();
        $(this.config.selectors.addToCartRegular).show();
        $(this.config.selectors.addToCartPackage).hide();

        // Update subtotal
        this.updateSubtotal();

        // Show modal
        $modal.modal('show');
    },

    async showPackageModal(product) {
        try {
            const packageDetails = await this.fetchPackageDetails(product.product_id);
            AppState.products.currentPackage = packageDetails;

            const $modal = $(this.config.selectors.modal);
            const $regularContent = $(this.config.selectors.regularContent);
            const $packageContent = $(this.config.selectors.packageContent);

            // Update basic info
            $(this.config.selectors.modalTitle).text(product.product_name);
            $('#modal-package-image').attr('src', this.getProductImageUrl(product.product_pict));
            $('#modal-package-description').text(product.description || 'No description available');
            $('#modal-package-base-price').text(this.formatPrice(packageDetails.base_price));

            // Render package sections
            this.renderPackageCategories(packageDetails.categories);
            this.renderPackageProducts(packageDetails.products);
            this.renderExcludedProducts(packageDetails.excluded_products);

            // Update visibility
            $regularContent.hide();
            $packageContent.show();
            $(this.config.selectors.addToCartRegular).hide();
            $(this.config.selectors.addToCartPackage).show();

            // Initialize package summary
            this.updatePackageSummary();

            // Show modal
            $modal.modal('show');
        } catch (error) {
            console.error('Package modal error:', error);
            this.handleError(error);
        }
    },

    handleQuantityDecrease(e) {
        const $input = $(e.currentTarget).siblings('input');
        const currentVal = parseInt($input.val()) || 0;
        const minVal = parseInt($input.attr('min')) || 1;

        if (currentVal > minVal) {
            $input.val(currentVal - 1).trigger('change');
        }
    },

    handleQuantityIncrease(e) {
        const $input = $(e.currentTarget).siblings('input');
        const currentVal = parseInt($input.val()) || 0;
        const maxVal = parseInt($input.attr('max'));

        if (!maxVal || currentVal < maxVal) {
            $input.val(currentVal + 1).trigger('change');
        }
    },

    handleQuantityChange(e) {
        const $input = $(e.target);
        let quantity = parseInt($input.val()) || 0;
        const min = parseInt($input.attr('min')) || 1;
        const max = parseInt($input.attr('max'));

        // Validate quantity
        if (quantity < min) {
            quantity = min;
        } else if (max && quantity > max) {
            quantity = max;
            this.showWarning(`Stok maksimal: ${max}`);
        }

        $input.val(quantity);
        this.updateSubtotal();
    },

    handleNoteInput(e) {
        const $input = $(e.target);
        const maxLength = this.config.validation.maxNoteLength;
        
        if ($input.val().length > maxLength) {
            $input.val($input.val().substring(0, maxLength));
        }
    },

    async handleAddToCartRegular() {
        try {
            const product = AppState.products.currentProduct;
            const quantity = parseInt($(this.config.selectors.quantityInput).val()) || 0;
            const note = $(this.config.selectors.noteInput).val().trim();

            // Validate
            if (quantity < 1) {
                throw new Error('Quantity must be at least 1');
            }

            const data = {
                action: 2, // Regular product
                orderId: AppState.session.id,
                data: [{
                    productId: product.product_id,
                    quantity: quantity,
                    notes: note
                }]
            };

            const response = await ApiService.addToCart(data);

            if (response.success) {
                $(this.config.selectors.modal).modal('hide');
                await OrderManager.updateCartCount();
                await this.showSuccess('Product added to cart');
            } else {
                throw new Error(response.message || 'Failed to add to cart');
            }
        } catch (error) {
            console.error('Add to cart error:', error);
            this.handleError(error);
        }
    },

    async handleAddToCartPackage() {
        try {
            if (!this.validatePackageSelection()) {
                throw new Error('Please complete package requirements');
            }

            const packageItems = this.getSelectedPackageItems();
            
            const data = {
                action: 3, // Package
                orderId: AppState.session.id,
                packageId: AppState.products.currentProduct.product_id,
                products: packageItems
            };

            const response = await ApiService.addToCart(data);

            if (response.success) {
                $(this.config.selectors.modal).modal('hide');
                await OrderManager.updateCartCount();
                await this.showSuccess('Package added to cart');
            } else {
                throw new Error(response.message || 'Failed to add package to cart');
            }
        } catch (error) {
            console.error('Add package error:', error);
            this.handleError(error);
        }
    },

    resetModal() {
        // Reset state
        AppState.products.currentProduct = null;
        AppState.products.currentPackage = null;

        // Reset UI
        $(this.config.selectors.quantityInput).val(1);
        $(this.config.selectors.noteInput).val('');
        $('#package-categories').empty();
        $('#package-products-accordion').empty();
        $('#package-summary').empty();
        $('#package-total').text('');
    },

    validatePackageSelection() {
        const packageDetails = AppState.products.currentPackage;
        
        if (!packageDetails) return false;

        const selectedItems = this.getSelectedPackageItems();
        const categoryTotals = {};

        // Calculate totals per category
        selectedItems.forEach(item => {
            const product = packageDetails.products.find(p => p.product_id === item.productId);
            if (product) {
                categoryTotals[product.category_id] = 
                    (categoryTotals[product.category_id] || 0) + item.quantity;
            }
        });

        // Validate against requirements
        let isValid = true;
        const errors = [];

        packageDetails.categories.forEach(category => {
            const total = categoryTotals[category.id] || 0;
            if (total < category.min_items) {
                isValid = false;
                errors.push(
                    `${category.name} requires ${category.min_items - total} more items`
                );
            }
        });

        if (!isValid) {
            this.showWarning(errors.join('\n'));
        }

        return isValid;
    },

    getSelectedPackageItems() {
        const items = [];
        $('.package-item-qty').each((_, input) => {
            const $input = $(input);
            const quantity = parseInt($input.val()) || 0;
            
            if (quantity > 0) {
                items.push({productId: parseInt($input.data('product-id')),
                    quantity: quantity,
                    notes: $input.closest('.package-item').find('.package-item-note').val()?.trim() || ''
                });
            }
        });
        return items;
    },

    renderPackageCategories(categories) {
        const $container = $('#package-categories').empty();
        
        categories.forEach(category => {
            const $category = $(`
                <div class="package-category mb-3">
                    <h6 class="mb-2">${category.name}</h6>
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

    renderPackageProducts(products) {
        const $accordion = $('#package-products-accordion').empty();
        const { categories } = AppState.products.currentPackage;
        
        const groupedProducts = this.groupProductsByCategory(products);
        
        categories.forEach(category => {
            const categoryProducts = groupedProducts[category.id] || [];
            
            const $section = this.createPackageAccordionSection(category, categoryProducts);
            $accordion.append($section);
        });
    },

    groupProductsByCategory(products) {
        return products.reduce((groups, product) => {
            if (!groups[product.category_id]) {
                groups[product.category_id] = [];
            }
            groups[product.category_id].push(product);
            return groups;
        }, {});
    },

    createPackageAccordionSection(category, products) {
        const sectionId = `category-${category.id}`;
        
        const $section = $(`
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#${sectionId}">
                        ${category.name}
                        <span class="badge bg-primary ms-2">${products.length} items</span>
                    </button>
                </h2>
                <div id="${sectionId}" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                        <div class="row g-3" id="products-${category.id}"></div>
                    </div>
                </div>
            </div>
        `);

        const $productsContainer = $section.find(`#products-${category.id}`);
        
        products.forEach(product => {
            const $productCard = this.createPackageProductCard(product, category);
            $productsContainer.append($productCard);
        });

        return $section;
    },

    createPackageProductCard(product, category) {
        const customPrice = this.getPackageCustomPrice(product.product_id);
        const stock = this.getProductStock(product.product_id);
        
        return $(`
            <div class="col-md-6 package-item">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex mb-2">
                            <img src="${this.getProductImageUrl(product.product_pict)}"
                                 class="rounded me-2" 
                                 style="width: 60px; height: 60px; object-fit: cover;"
                                 alt="${product.product_name}">
                            <div>
                                <h6 class="card-title mb-1">${product.product_name}</h6>
                                <p class="card-text small text-muted mb-0">
                                    Stok: ${stock}
                                </p>
                            </div>
                        </div>
                        <div class="mb-2">
                            ${this.renderProductPrice(product, customPrice)}
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="input-group input-group-sm" style="width: 100px;">
                                <button class="btn btn-outline-secondary decrease-qty" type="button">
                                    <i class="bi bi-dash"></i>
                                </button>
                                <input type="number" 
                                       class="form-control text-center package-item-qty"
                                       value="0" 
                                       min="0" 
                                       max="${stock}"
                                       data-product-id="${product.product_id}"
                                       data-category-id="${category.id}">
                                <button class="btn btn-outline-secondary increase-qty" type="button">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                            <button class="btn btn-sm btn-outline-primary package-item-note-btn"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#note-${product.product_id}">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </div>
                        <div class="collapse mt-2" id="note-${product.product_id}">
                            <textarea class="form-control form-control-sm package-item-note"
                                    rows="2"
                                    placeholder="Tambah catatan..."
                                    maxlength="200"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        `);
    },

    renderProductPrice(product, customPrice) {
        if (customPrice) {
            return `
                <div>
                    <span class="text-decoration-line-through text-muted">
                        ${this.formatPrice(product.price_catalogue)}
                    </span>
                    <span class="text-success ms-2">
                        ${this.formatPrice(customPrice)}
                    </span>
                </div>
            `;
        }
        
        return `<div>${this.formatPrice(product.price_catalogue)}</div>`;
    },

    getPackageCustomPrice(productId) {
        const { custom_prices = {} } = AppState.products.currentPackage || {};
        return custom_prices[productId];
    },

    getProductStock(productId) {
        const product = AppState.products.currentPackage?.products.find(
            p => p.product_id === productId
        );
        return product?.stock || 0;
    },

    renderExcludedProducts(excludedProducts = []) {
        const $section = $('#excluded-products-section');
        const $list = $('#excluded-products-list').empty();

        if (!excludedProducts.length) {
            $section.hide();
            return;
        }

        excludedProducts.forEach(product => {
            $list.append(`
                <div class="col-md-6">
                    <div class="alert alert-warning mb-2">
                        <h6 class="alert-heading mb-1">${product.product_name}</h6>
                        <p class="small mb-0">
                            ${product.exclude_reason || 'Tidak tersedia dalam paket ini'}
                        </p>
                    </div>
                </div>
            `);
        });

        $section.show();
    },

    updatePackageSummary() {
        const packageDetails = AppState.products.currentPackage;
        if (!packageDetails) return;

        const $summary = $('#package-summary').empty();
        let totalAmount = packageDetails.base_price || 0;

        // Base price line
        $summary.append(`
            <div class="d-flex justify-content-between mb-2">
                <span>Harga Dasar Paket</span>
                <span>${this.formatPrice(totalAmount)}</span>
            </div>
        `);

        // Calculate category totals
        const selectedItems = this.getSelectedPackageItems();
        const categoryTotals = this.calculateCategoryTotals(selectedItems);

        // Add category summaries
        packageDetails.categories.forEach(category => {
            const {total, items} = categoryTotals[category.id] || { total: 0, items: 0 };
            const isComplete = items >= category.min_items;

            $summary.append(`
                <div class="d-flex justify-content-between mb-2">
                    <span>${category.name} (${items} item)</span>
                    <div class="text-end">
                        <span class="badge ${isComplete ? 'bg-success' : 'bg-warning'} me-2">
                            ${isComplete ? 'Lengkap' : `Butuh ${category.min_items - items} lagi`}
                        </span>
                        ${this.formatPrice(total)}
                    </div>
                </div>
            `);

            totalAmount += total;
        });

        // Update total
        $('#package-total').text(this.formatPrice(totalAmount));
        
        // Enable/disable add to cart button
        $(this.config.selectors.addToCartPackage).prop(
            'disabled',
            !this.validatePackageSelection()
        );
    },

    calculateCategoryTotals(selectedItems) {
        const packageDetails = AppState.products.currentPackage;
        const totals = {};

        selectedItems.forEach(item => {
            const product = packageDetails.products.find(
                p => p.product_id === item.productId
            );

            if (product) {
                if (!totals[product.category_id]) {
                    totals[product.category_id] = { total: 0, items: 0 };
                }

                const price = this.getPackageCustomPrice(product.product_id) || 
                             product.price_catalogue;

                totals[product.category_id].total += price * item.quantity;
                totals[product.category_id].items += item.quantity;
            }
        });

        return totals;
    },

    updateSubtotal() {
        const product = AppState.products.currentProduct;
        if (!product) return;

        const quantity = parseInt($(this.config.selectors.quantityInput).val()) || 0;
        const price = product.price_catalogue;
        const subtotal = quantity * price;

        $('#product-subtotal').text(this.formatPrice(subtotal));
    },

    showSuccess(message) {
        return Swal.fire({
            title: 'Sukses',
            text: message,
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    },

    showWarning(message) {
        return Swal.fire({
            text: message,
            icon: 'warning',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
    },

    handleError(error) {
        console.error('Modal error:', error);
        Swal.fire({
            title: 'Error',
            text: error.message || 'Terjadi kesalahan. Silakan coba lagi.',
            icon: 'error'
        });
    },

    formatPrice(amount) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    },

    getProductImageUrl(imageName) {
        return `${window.location.origin}/resource/assets-frontend/dist/product/${imageName}`;
    }
};
/**
 * Order Manager Implementation
 * Handles all order-related operations including cart management
 */
const OrderManager = {
    state: {
        retryAttempts: 0,
        maxRetries: 3,
        retryDelay: 1000,
        isProcessing: false,
        lastCartUpdate: null,
        cartRefreshInterval: 60000, // 1 minute
        initialized: false,
        cartData: {
            items: [],
            total: {
                amount: 0,
                items: 0,
                quantity: 0
            }
        }
    },

    config: {
        selectors: {
            noResults: '#no-results-message',
            productListing: '#product-listing',
            productCard: '.product-card',
            productCategory: '.product-category',
            categorySelect: '#category-select',
            productSearch: '#product-search',
            cartModal: '#cart-modal',
            cartButton: '#show-cart',
            orderButton: '#order',
            cartCountBadge: '#count-cart',
            cartTotalItems: '#cart-total-items',
            cartTotalAmount: '#cart-total-amount',
            cartContainer: '#container-cart'
        },
        templates: {
            cartItem: {
                regular: `
                    <tr class="cart-item" data-item-id="{id}">
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="{image}" class="rounded me-2" style="width: 50px; height: 50px; object-fit: cover;" alt="{name}">
                                <div>
                                    <h6 class="mb-0">{name}</h6>
                                    {noteHtml}
                                </div>
                            </div>
                        </td>
                        <td class="text-center">{quantity}</td>
                        <td class="text-end">{price}</td>
                        <td class="text-end">{subtotal}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-danger remove-cart-item" 
                                    data-product-id="{productId}"
                                    data-count="1">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `,
                package: `
                    <tr class="cart-item package-item" data-item-id="{id}">
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="{image}" class="rounded me-2" style="width: 50px; height: 50px; object-fit: cover;" alt="{name}">
                                <div>
                                    <h6 class="mb-0">{name}</h6>
                                    {noteHtml}
                                    <div class="package-items mt-2">
                                        <small class="d-block text-muted mb-1">Isi Paket:</small>
                                        {packageItemsHtml}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">{quantity}</td>
                        <td class="text-end">{price}</td>
                        <td class="text-end">{subtotal}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-danger remove-cart-item" 
                                    data-product-id="{productId}"
                                    data-count="1">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `
            },
            packageItem: `
                <div class="package-item-detail ms-3">
                    <small class="text-muted">
                        {quantity}x {name}
                        {noteHtml}
                    </small>
                </div>
            `,
            note: `
                <small class="text-muted">
                    <i class="bi bi-pencil-square me-1"></i>{note}
                </small>
            `,
            emptyCart: `
                <div class="text-center py-5">
                    <i class="bi bi-cart-x fs-1 text-muted"></i>
                    <p class="mt-3">Keranjang Anda kosong</p>
                </div>
            `,
            noResults: `
                <div class="alert alert-info text-center my-4">
                    <i class="bi bi-search me-2"></i>Tidak ada produk ditemukan
                </div>
            `
        }
    },

    /**
     * Initialize Order Manager
     * @returns {Promise<boolean>} Initialization success status
     */
      async init() {
        console.group('OrderManager Initialization');
        try {
            // Validasi produk sebelum inisialisasi
            await this.validateProductLoading();

            this.setupEventListeners();
            this.initializeView();
            await this.initializeCart();

            this.state.initialized = true;
            console.log('OrderManager initialized successfully');
            return true;
        } catch (error) {
            console.error('OrderManager initialization error:', error);
            this.handleInitializationError(error);
            return false;
        } finally {
            console.groupEnd();
        }
    },

    validateAndLogProducts() {
        const $productCards = $(this.config.selectors.productCard);
        const $productCategories = $(this.config.selectors.productCategory);

        console.log('Product Card Count:', $productCards.length);
        console.log('Product Category Count:', $productCategories.length);

        // Log detail setiap kategori produk
        $productCategories.each((index, category) => {
            const $category = $(category);
            const categoryName = $category.find('h2').text();
            const categoryProducts = $category.find(this.config.selectors.productCard);

            console.log(`Category ${index + 1}:`, {
                name: categoryName,
                productCount: categoryProducts.length
            });
        });
    },


    handleInitializationError(error) {
        console.error('Initialization failed:', error);
        Swal.fire({
            title: 'Kesalahan Inisialisasi',
            text: 'Gagal memuat aplikasi. Silakan refresh halaman.',
            icon: 'error',
            confirmButtonText: 'Muat Ulang'
        }).then(() => {
            window.location.reload();
        });
    },

    addNoResultsPlaceholder() {
        const $productListing = $(this.config.selectors.productListing);
        if (!$(this.config.selectors.noResults).length) {
            $productListing.append(`
                <div id="no-results-message" class="alert alert-info text-center my-4" style="display:none;">
                    <i class="bi bi-search me-2"></i>Tidak ada produk ditemukan
                </div>
            `);
        }
    },

    /**
     * Validate initialization requirements
     * @returns {boolean} Validation result
     */
    validateInitialization() {
        const requiredSelectors = Object.values(this.config.selectors);
        const missingSelectors = requiredSelectors.filter(
            selector => !$(selector).length
        );

        if (missingSelectors.length) {
            console.warn('Elemen yang hilang:', missingSelectors);
            return false;
        }

        // Periksa status sesi
        if (!AppState.session.id) {
            console.error('Tidak ada sesi aktif');
            return false;
        }

        return true;
    },

    /**
     * Setup event listeners for order interactions
     */
    setupEventListeners() {
        $(this.config.selectors.categorySelect).on('change', (e) => {
            const categoryId = $(e.target).val();
            this.handleCategoryFilter(categoryId);
        });

        // Pencarian produk
        $(this.config.selectors.productSearch).on('input', (e) => {
            const searchTerm = $(e.target).val().toLowerCase().trim();
            this.handleProductSearch(searchTerm);
        });

        // Cart interactions
        $(this.config.selectors.cartButton).on('click', () => this.loadCart());
        $(document).on('click', '.remove-cart-item', (e) => this.handleCartItemRemoval(e));
        $(this.config.selectors.orderButton).on('click', () => this.processOrder());

        // Cart refresh on visibility change
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.updateCartCount();
            }
        });

        // Session events
        EventBus.on('session:expired', () => this.handleSessionExpired());
        EventBus.on('cart:updated', () => this.updateCartCount());
    },

    async validateProductLoading() {
        // Tambahkan mekanisme untuk memuat ulang produk jika kosong
        const $productCards = $(this.config.selectors.productCard);
        
        if ($productCards.length === 0) {
            console.warn('Tidak ada produk yang dimuat. Mencoba memuat produk...');
            
            try {
                // Coba memuat produk menggunakan API
                const productsResponse = await ApiService.getProducts();
                
                if (productsResponse.success && productsResponse.data) {
                    this.renderProducts(productsResponse.data);
                } else {
                    throw new Error('Gagal memuat produk');
                }
            } catch (error) {
                console.error('Kesalahan memuat produk:', error);
                this.showProductLoadingError();
            }
        }
    },

    renderProducts(productsData) {
        console.log('Products Data:', productsData);
        const $productListing = $(this.config.selectors.productListing);
        $productListing.empty(); 
    
        if (productsData.groupedProducts) {
            const categories = Object.values(productsData.groupedProducts);
            console.log('Total Categories:', categories.length);
            
            categories.forEach(category => {
                console.log(`Rendering Category: ${category.category_name}`);
                console.log(`Products in Category: ${category.products.length}`);
                
                const categoryHtml = this.renderProductCategory(category);
                $productListing.append(categoryHtml);
            });
        } else {
            console.warn('No grouped products found');
        }
    
        this.initializeView();
    },

    renderProductCategory(category) {
        console.log('Rendering Category:', category.category_name);
        console.log('Products in Category:', category.products.length);
    
        const productHtml = category.products.map(this.renderProductCard).join('');
        
        const categoryHtml = `
            <div class="product-category" data-category-name="${category.category_name}">
                <div class="col-12 pt-2 rounded mb-4" style="background-color: #6b4823">
                    <div class="d-flex justify-content-center">
                        <h2 style="color: #fff">${category.category_name}</h2>
                    </div>
                </div>
                <div class="row mb-5">
                    ${productHtml}
                </div>
            </div>
        `;
        return categoryHtml;
    },

    renderProductCard(product) {
        return `
            <div class="col-6 col-sm-4 pt-3 product-card" 
                 data-product-id="${product.product_id}" 
                 data-category-id="${product.cat_id}">
                <div class="position-relative mb-3">
                    <div class="product-image-container">
                        <img src="${this.getProductImageUrl(product.product_pict)}" 
                             class="img-fluid rounded product-image" 
                             alt="${product.product_name}" />
                    </div>
                    <div class="product-info p-2">
                        <h5 class="product-name fw-bold mb-2">
                            ${product.product_name.toUpperCase()}
                        </h5>
                        <div class="price-tag mb-3">
                            <span class="rounded-circle price-dot">Rp</span>
                            <span class="price-amount">
                                ${product.price_display}<sup>K</sup>
                            </span>
                        </div>
                        <button class="btn btn-primary w-100 view-product" 
                                data-product-id="${product.product_id}">
                            Pilih
                        </button>
                    </div>
                </div>
            </div>
        `;
    },

    showProductLoadingError() {
        const $productListing = $(this.config.selectors.productListing);
        $productListing.html(`
            <div class="alert alert-danger text-center">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Gagal memuat produk. Silakan refresh halaman atau hubungi dukungan.
            </div>
        `);
    },

    /**
     * Initialize cart
     */
    async initializeCart() {
        try {
            await this.updateCartCount(true);
            this.startCartRefresh();
        } catch (error) {
            console.error('Cart initialization error:', error);
            throw error;
        }
    },

    /**
     * Start periodic cart refresh
     */
    startCartRefresh() {
        setInterval(() => {
            if (this.state.initialized && !this.state.isProcessing) {
                this.updateCartCount();
            }
        }, this.state.cartRefreshInterval);
    },

    /**
     * Handle category filter change
     * @param {string} categoryId Selected category ID
     */
    handleCategoryFilter(categoryId) {
        const $productCards = $(this.config.selectors.productCard);
        const $productCategories = $(this.config.selectors.productCategory);

        if (categoryId === 'all') {
            $productCards.show();
            $productCategories.show();
        } else {
            $productCards.hide();
            $productCategories.hide();
            
            $(`.product-card[data-category-id="${categoryId}"]`)
                .show()
                .closest(this.config.selectors.productCategory)
                .show();
        }

        this.toggleNoResults();
        this.setupProductVisibility();
    },

    /**
     * Handle product search
     * @param {string} searchTerm Search keyword
     */
    handleProductSearch(searchTerm) {
        const $productCards = $(this.config.selectors.productCard);
        const $productCategories = $(this.config.selectors.productCategory);

        $productCards.each(function() {
            const $card = $(this);
            const productName = $card.find('.product-name').text().toLowerCase();
            const matches = productName.includes(searchTerm);
            $card.toggle(matches);
        });

        this.toggleNoResults();
        this.setupProductVisibility();
    },

    /**
     * Toggle no results message
     */
    initializeView() {
        this.setupProductVisibility();
        this.toggleNoResults();
    },

    setupProductVisibility() {
        const $productCards = $(this.config.selectors.productCard);
        const $productCategories = $(this.config.selectors.productCategory);
    
        console.log('Product Cards:', $productCards.length);
        console.log('Product Categories:', $productCategories.length);
    
        $productCategories.each(function() {
            const $category = $(this);
            const $categoryHeader = $category.find('h2');
            const categoryName = $categoryHeader.text().trim();
            const visibleProducts = $category.find('.product-card:visible').length;
    
            console.log(`Category: ${categoryName}, Visible Products: ${visibleProducts}`);
    
            // Force show category if it has visible products
            $category.toggle(visibleProducts > 0);
        });
    },

    toggleNoResults() {
        const $noResultsMessage = $(this.config.selectors.noResults);
        const $productCards = $(this.config.selectors.productCard);
        const $productCategories = $(this.config.selectors.productCategory);

        const hasVisibleProducts = $productCards.filter(':visible').length > 0;
        const hasCategoriesWithProducts = $productCategories.filter(function() {
            return $(this).find('.product-card:visible').length > 0;
        }).length > 0;

        console.log('Visible Product Details:', {
            visibleProductCount: $productCards.filter(':visible').length,
            visibleCategoryCount: $productCategories.filter(function() {
                return $(this).find('.product-card:visible').length > 0;
            }).length
        });

        if (!hasVisibleProducts || !hasCategoriesWithProducts) {
            if ($noResultsMessage.length === 0) {
                $(this.config.selectors.productListing).append(`
                    <div id="no-results-message" class="alert alert-info text-center my-4">
                        <i class="bi bi-search me-2"></i>Tidak ada produk ditemukan
                    </div>
                `);
            }
            $(this.config.selectors.noResults).show();
        } else {
            $(this.config.selectors.noResults).hide();
        }
    },

    /**
     * Update cart count with retry mechanism
     * @param {boolean} isInitial Whether this is the initial update
     * @returns {Promise<boolean>} Update success status
     */
    async updateCartCount(isInitial = false) {
        if (!this.state.initialized && !isInitial) return false;

        try {
            const response = await this.fetchWithRetry(async () => {
                return ApiService.getCartCount();
            });

            if (response.success) {
                const cartCount = response.data.metrics.total_items;
                this.updateCartUI(cartCount);
                
                if (response.data.session) {
                    this.handleSessionUpdate(response.data.session);
                }

                this.state.lastCartUpdate = new Date();
                return true;
            }

            throw new Error(response.message || 'Failed to update cart count');
        } catch (error) {
            console.error('Cart count update error:', error);
            if (!isInitial) {
                await this.handleCartError(error);
            }
            return false;
        }
    },

    /**
     * Update cart UI elements
     * @param {number} count Cart item count
     */
    updateCartUI(count) {
        const $badge = $(this.config.selectors.cartCountBadge);
        const currentCount = parseInt($badge.text()) || 0;

        if (currentCount !== count) {
            $badge.text(count);

            // Animate badge if count increased
            if (count > currentCount) {
                $badge
                    .addClass('badge-pop')
                    .one('animationend', () => $badge.removeClass('badge-pop'));
            }
        }
    },

    /**
     * Load cart details
     */
    async loadCart() {
        try {
            this.state.isProcessing = true;
            this.showLoading(true);

            const response = await this.fetchWithRetry(async () => {
                return ApiService.getCart();
            });

            if (response.success) {
                this.state.cartData = response.data;
                this.renderCart(response.data);
                $(this.config.selectors.cartModal).modal('show');
            } else {
                throw new Error(response.message || 'Failed to load cart');
            }
        } catch (error) {
            console.error('Cart loading error:', error);
            await this.handleCartError(error);
        } finally {
            this.state.isProcessing = false;
            this.showLoading(false);
        }
    },

    /**
     * Render cart contents
     * @param {Object} data Cart data
     */
    renderCart(data) {
        const $container = $(this.config.selectors.cartContainer);
        
        if (!data.cart?.items?.length) {
            $container.html(this.config.templates.emptyCart);
            $(this.config.selectors.orderButton).prop('disabled', true);
            return;
        }

        let cartHtml = this.generateCartHeader();
        
        // Render items
        data.cart.items.forEach(item => {
            cartHtml += this.generateCartItemHtml(item);
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
     * Generate cart item HTML
     * @param {Object} item Cart item data
     * @returns {string} Cart item HTML
     */
    generateCartItemHtml(item) {
        const template = item.is_package ? 
            this.config.templates.cartItem.package : 
            this.config.templates.cartItem.regular;

        const noteHtml = item.notes ? 
            this.config.templates.note.replace('{note}', item.notes) : '';

        let packageItemsHtml = '';
        if (item.package_items?.length) {
            packageItemsHtml = item.package_items.map(packageItem => {
                const packageNoteHtml = packageItem.notes ? 
                    `<br>${this.config.templates.note.replace('{note}', packageItem.notes)}` : '';

                return this.config.templates.packageItem
                    .replace('{quantity}', packageItem.quantity)
                    .replace('{name}', packageItem.product_name)
                    .replace('{noteHtml}', packageNoteHtml);
            }).join('');
        }

        return template
            .replace(/{id}/g, item.id)
            .replace(/{productId}/g, item.product_id)
            .replace(/{name}/g, item.product_name)
            .replace(/{image}/g, this.getProductImageUrl(item.product_image))
            .replace(/{quantity}/g, item.quantity)
            .replace(/{price}/g, this.formatPrice(item.unit_price))
            .replace(/{subtotal}/g, this.formatPrice(item.is_package ? 
                item.package_total * item.quantity : 
                item.subtotal))
            .replace(/{noteHtml}/g, noteHtml)
            .replace(/{packageItemsHtml}/g, packageItemsHtml);
    },

    /**
     * Generate cart footer HTML
     * @param {Object} cart Cart data
     * @returns {string} Cart footer HTML
     */
    generateCartFooter(cart) {
        return `
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Total</td>
                                <td class="text-end fw-bold">${this.formatPrice(cart.total_amount)}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        `;
    },

    /**
     * Update cart summary
     * @param {Object} cart Cart data
     */
    updateCartSummary(cart) {
        $(this.config.selectors.cartTotalItems).text(cart.total_items);
        $(this.config.selectors.cartTotalAmount).text(this.formatPrice(cart.total_amount));
    },

    /**
     * Handle cart item removal
     * @param {Event} e Click event
     */
    async handleCartItemRemoval(e) {
        try {
            const $btn = $(e.currentTarget);
            const productId = $btn.data('product-id');
            const count = $btn.data('count') || 1;

            const confirmed = await Swal.fire({
                title: 'Hapus Item?',
                text: 'Item ini akan dihapus dari keranjang Anda',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                reverseButtons: true
            });

            if (!confirmed.isConfirmed) return;

            this.state.isProcessing = true;
            this.showLoading(true);

            const response = await this.fetchWithRetry(async () => {
                return ApiService.removeFromCart({
                    productId: productId,
                    count: count
                });
            });

            if (response.success) {
                await this.loadCart();
                await this.updateCartCount();
                
                await Swal.fire({
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
            console.error('Cart item removal error:', error);
            await this.handleCartError(error);
        } finally {
            this.state.isProcessing = false;
            this.showLoading(false);
        }
    },

    /**
     * Process final order
     */
    async processOrder() {
        try {
            const confirmed = await Swal.fire({
                title: 'Proses Pesanan?',
                text: 'Pesanan Anda akan diproses dan tidak dapat dibatalkan',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Proses',
                cancelButtonText: 'Batal',
                reverseButtons: true
            });

            if (!confirmed.isConfirmed) return;

            this.state.isProcessing = true;
            this.showLoading(true);

            const response = await this.fetchWithRetry(async () => {
                return ApiService.processOrder();
            });

            if (response.success) {
                $(this.config.selectors.cartModal).modal('hide');
                
                await Swal.fire({
                    title: 'Pesanan Berhasil!',
                    html: this.generateOrderConfirmation(response.data),
                    icon: 'success',
                    confirmButtonText: 'OK'
                });

                // Reload page after successful order
                window.location.reload();
            } else {
                throw new Error(response.message || 'Gagal memproses pesanan');
            }
        } catch (error) {
            console.error('Order processing error:', error);
            await this.handleCartError(error);
        } finally {
            this.state.isProcessing = false;
            this.showLoading(false);
        }
    },

    /**
     * Generate order confirmation HTML
     * @param {Object} data Order data
     * @returns {string} Confirmation HTML
     */
    generateOrderConfirmation(data) {
        return `
            <div class="text-start">
                <p class="mb-2">
                    <strong>Nomor Pesanan:</strong>
                    <span class="ms-2">${data.receipt_number}</span>
                </p>
                <p class="mb-2">
                    <strong>Total Item:</strong>
                    <span class="ms-2">${data.summary.total_items}</span>
                </p>
                <p class="mb-2">
                    <strong>Total Pembayaran:</strong>
                    <span class="ms-2">${this.formatPrice(data.summary.total_amount)}</span>
                </p>
                <p class="mb-0 text-muted">
                    <small>
                        <i class="bi bi-info-circle me-1"></i>
                        Pesanan Anda akan segera diproses
                    </small>
                </p>
            </div>
        `;
    },

    /**
     * Handle session update
     * @param {Object} sessionData Session data
     */
    handleSessionUpdate(sessionData) {
        if (sessionData.status !== AppState.session.status) {
            EventBus.emit('session:status_changed', sessionData);
        }

        if (new Date(sessionData.expire_at) > new Date(AppState.session.expiryTime)) {
            EventBus.emit('session:extended', sessionData);
        }

        AppState.session = {
            ...AppState.session,
            ...sessionData
        };
    },

    /**
     * Handle session expiration
     */
    async handleSessionExpired() {
        this.cleanup();
        
        await Swal.fire({
            title: 'Sesi Berakhir',
            text: 'Sesi Anda telah berakhir. Halaman akan dimuat ulang.',
            icon: 'warning',
            confirmButtonText: 'OK',
            allowOutsideClick: false
        });

        window.location.reload();
    },

    /**
     * Fetch with retry mechanism
     * @param {Function} fetchFn Fetch function
     * @returns {Promise} Fetch result
     */
    async fetchWithRetry(fetchFn) {
        let lastError;

        for (let attempt = 0; attempt <= this.state.maxRetries; attempt++) {
            try {
                return await fetchFn();
            } catch (error) {
                console.warn(`Attempt ${attempt + 1} failed:`, error);
                lastError = error;

                if (attempt < this.state.maxRetries) {
                    await this.delay(this.state.retryDelay * Math.pow(2, attempt));
                }
            }
        }

        throw lastError;
    },

    /**
     * Handle cart error
     * @param {Error} error Cart error
     */
    async handleCartError(error) {
        if (!navigator.onLine) {
            await this.showToast('warning', 'Koneksi terputus. Mencoba kembali...');
            return;
        }

        if (error.message === 'SESSION_EXPIRED') {
            await this.handleSessionExpired();
            return;
        }

        await this.showToast('error', error.message || 'Gagal memperbarui keranjang');
    },

    /**
     * Show loading state
     * @param {boolean} show Show/hide loading
     */
    showLoading(show = true) {
        if (show) {
            Swal.showLoading();
        } else {
            Swal.close();
        }
    },

    /**
     * Show toast message
     * @param {string} icon Toast icon
     * @param {string} message Toast message
     */
    showToast(icon, message) {
        return Swal.fire({
            text: message,
            icon: icon,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
    },

    /**
     * Format price in Indonesian Rupiah
     * @param {number} amount Price amount
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
     * Get product image URL
     * @param {string} imageName Image filename
     * @returns {string} Full image URL
     */
    getProductImageUrl(imageName) {
        return `${window.location.origin}/resource/assets-frontend/dist/product/${imageName}`;
    },

    /**
     * Delay helper
     * @param {number} ms Milliseconds to delay
     * @returns {Promise} Delay promise
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    },

    /**
     * Cleanup resources
     */
    cleanup() {
        this.state.initialized = false;
        EventBus.off('session:expired');
        EventBus.off('cart:updated');
    }
};

// Initialize on document ready
$(document).ready(async () => {
    try {
        await SessionManager.init();
        if (AppState.session.id) {
            await OrderManager.init();
            
            // Tambahkan debug tambahan
            console.log('Session ID:', AppState.session.id);
            console.log('Order Manager Initialized');
        } else {
            console.warn('No active session');
        }
    } catch (error) {
        console.error('Kesalahan inisialisasi aplikasi:', error);
        Swal.fire({
            title: 'Kesalahan',
            text: 'Gagal memuat aplikasi. Silakan refresh halaman.',
            icon: 'error',
            confirmButtonText: 'Muat Ulang'
        }).then(() => {
            window.location.reload();
        });
    }
});
// Export modules if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        SessionManager,
        OrderManager,
        ProductModal,
        AppState,
        EventBus,
        ApiService
    };
}