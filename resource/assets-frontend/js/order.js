const STATUS = {
    RESERVED: 1,
    ORDERED: 2
};

const CONFIG = {
    AUTO_EXTEND_THRESHOLD: 300000, // 5 minutes in milliseconds
    REFRESH_INTERVAL: 3000,        // 3 seconds in milliseconds
    SESSION_LENGTH: 900000,        // 15 minutes in milliseconds
    MIN_PASSCODE_LENGTH: 4,
    VALID_BRANDS: ['kopitiam', 'bakery', 'resto']
};

// State Management
const state = {
    session: null,
    cart: {
        items: [],
        total: 0,
        regularItems: [],
        packageItems: []
    },
    selectedPackage: null,
    lastActivityTime: Date.now(),
    modals: {},
    currentPackage: null,
    packageSelections: new Map(),
    validationErrors: []
};

// Modal Manager
class ModalManager {
    constructor() {
        this.initializeModals();
        this.setupModalListeners();
    }

    initializeModals() {
        const modalIds = [
            'cart-modal',
            'package-selection-modal',
            'package-browse-modal',
            'add-note-modal',
            'loading-modal',
            'error-modal',
            'connection-modal',
            'operating-hours-modal',
            'confirm-order-modal',
            'order-success-modal'
        ];

        modalIds.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                state.modals[id] = new bootstrap.Modal(element);
                
                // Add event listeners for modal events
                element.addEventListener('shown.bs.modal', () => {
                    this.handleModalShow(id);
                });
                
                element.addEventListener('hidden.bs.modal', () => {
                    this.handleModalHide(id);
                });
            }
        });
    }

    setupModalListeners() {
        // Error modal retry button
        const retryButton = document.getElementById('retry-connection');
        if (retryButton) {
            retryButton.addEventListener('click', () => {
                this.hide('connection-modal');
                window.location.reload();
            });
        }

        // Operating hours modal close handling
        const operatingHoursModal = document.getElementById('operating-hours-modal');
        if (operatingHoursModal) {
            operatingHoursModal.addEventListener('hidden.bs.modal', () => {
                window.location.href = '/';
            });
        }
    }

    handleModalShow(modalId) {
        switch (modalId) {
            case 'cart-modal':
                cartManager.refreshCartDisplay();
                break;
            case 'package-selection-modal':
                packageManager.refreshPackageDisplay();
                break;
            case 'add-note-modal':
                const noteInput = document.getElementById('item-note');
                if (noteInput) noteInput.focus();
                break;
        }
    }

    handleModalHide(modalId) {
        switch (modalId) {
            case 'package-selection-modal':
                state.packageSelections.clear();
                state.currentPackage = null;
                break;
            case 'add-note-modal':
                document.getElementById('item-note').value = '';
                document.getElementById('note-product-id').value = '';
                break;
        }
    }

    show(modalId) {
        if (state.modals[modalId]) {
            state.modals[modalId].show();
        }
    }

    hide(modalId) {
        if (state.modals[modalId]) {
            state.modals[modalId].hide();
        }
    }

    showError(message, title = 'Error') {
        const errorModal = document.getElementById('error-modal');
        if (errorModal) {
            errorModal.querySelector('#error-message').textContent = message;
            errorModal.querySelector('.modal-title').textContent = title;
            this.show('error-modal');
        }
    }

    showLoading(message = 'Processing your request...') {
        const loadingModal = document.getElementById('loading-modal');
        if (loadingModal) {
            loadingModal.querySelector('h5').textContent = message;
            this.show('loading-modal');
        }
    }
}

// Session Manager
class SessionManager {
    constructor() {
        this.sessionCheckInterval = null;
    }

    async initialize(params) {
        if (!this.validateParams(params)) {
            modalManager.showError('Invalid parameters provided');
            return false;
        }

        try {
            const response = await this.checkExistingSession(params);
            if (response.success) {
                this.setupActiveSession(response.data.session);
                return true;
            }
            return false;
        } catch (error) {
            console.error('Session initialization failed:', error);
            modalManager.showError('Failed to initialize session');
            return false;
        }
    }

    validateParams(params) {
        const requiredParams = ['outletId', 'tableId', 'brand'];
        return requiredParams.every(param => params.has(param)) &&
               CONFIG.VALID_BRANDS.includes(params.get('brand'));
    }

    async checkExistingSession(params) {
        try {
            const response = await fetch(`/order/session?outletId=${params.get('outletId')}&tableId=${params.get('tableId')}&brand=${params.get('brand')}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            throw new Error('Failed to check existing session');
        }
    }

    async createNewSession(params, customerData) {
        if (!this.validateCustomerData(customerData)) {
            modalManager.showError('Invalid customer data');
            return false;
        }

        try {
            modalManager.showLoading('Creating your session...');
            
            const response = await fetch('/order/session', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    outletId: params.get('outletId'),
                    tableId: params.get('tableId'),
                    brand: params.get('brand'),
                    name: customerData.name,
                    passcode: customerData.passcode
                })
            });

            modalManager.hide('loading-modal');

            const result = await response.json();
            if (result.success) {
                this.setupActiveSession(result.data.session);
                return true;
            }
            
            modalManager.showError(result.message);
            return false;
        } catch (error) {
            modalManager.hide('loading-modal');
            modalManager.showError('Failed to create session');
            return false;
        }
    }

    validateCustomerData(customerData) {
        return customerData.name?.trim().length > 0 &&
               customerData.passcode?.length >= CONFIG.MIN_PASSCODE_LENGTH;
    }

    setupActiveSession(sessionData) {
        state.session = sessionData;
        this.startSessionMonitoring();
        this.showOrderInterface();
        this.updateUIWithSessionData();
    }

    updateUIWithSessionData() {
        document.getElementById('customer-name').textContent = state.session.name;
        document.querySelector('.table-info').textContent = `Table #${state.session.table_id}`;
    }

    startSessionMonitoring() {
        if (this.sessionCheckInterval) {
            clearInterval(this.sessionCheckInterval);
        }

        this.sessionCheckInterval = setInterval(() => {
            this.checkAndExtendSession();
        }, CONFIG.REFRESH_INTERVAL);
    }

    async checkAndExtendSession() {
        const currentTime = Date.now();
        if (currentTime - state.lastActivityTime >= CONFIG.AUTO_EXTEND_THRESHOLD) {
            await this.extendSession();
            state.lastActivityTime = currentTime;
        }
    }

    async extendSession() {
        if (!state.session) return;
        
        try {
            const response = await fetch('/order/session', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    sessionId: state.session.id
                })
            });
            
            const result = await response.json();
            if (result.success) {
                state.session.expire_at = result.data.expire_at;
            }
        } catch (error) {
            console.error('Failed to extend session:', error);
        }
    }

    showOrderInterface() {
        document.getElementById('identity-page').style.display = 'none';
        document.getElementById('outlet-info').style.display = 'block';
        document.getElementById('order-page').style.display = 'block';
    }

    updateLastActivity() {
        state.lastActivityTime = Date.now();
    }

    cleanup() {
        if (this.sessionCheckInterval) {
            clearInterval(this.sessionCheckInterval);
        }
    }
}

// Cart Manager
class CartManager {
    constructor() {
        this.updateInterval = null;
    }

    initialize() {
        this.setupEventListeners();
        this.startAutoUpdate();
        this.loadInitialCart();
    }

    setupEventListeners() {
        // Add to cart buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('add-to-cart')) {
                this.handleAddToCart(e);
            }
        });

        // Show cart button
        const showCartButton = document.getElementById('show-cart');
        if (showCartButton) {
            showCartButton.addEventListener('click', () => this.showCartModal());
        }

        // Cart quantity controls
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('decrease-qty')) {
                this.handleQuantityChange(e, 'decrease');
            } else if (e.target.classList.contains('increase-qty')) {
                this.handleQuantityChange(e, 'increase');
            }
        });

        // Note buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('add-note')) {
                this.showAddNoteModal(e);
            }
        });

        // Remove item buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-item')) {
                this.handleRemoveItem(e);
            }
        });

        // Place order button
        const placeOrderButton = document.getElementById('place-order');
        if (placeOrderButton) {
            placeOrderButton.addEventListener('click', () => this.handlePlaceOrder());
        }

        // Save note button
        const saveNoteButton = document.getElementById('save-note');
        if (saveNoteButton) {
            saveNoteButton.addEventListener('click', () => this.handleSaveNote());
        }
    }

    async loadInitialCart() {
        await this.updateCart();
    }

    async handleAddToCart(event) {
        const productId = event.target.dataset.productId;
        const quantity = parseInt(event.target.dataset.quantity || '1');
        
        sessionManager.updateLastActivity();
        modalManager.showLoading('Adding to cart...');

        try {
            const response = await fetch('/order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 2,
                    orderId: state.session.id,
                    data: [{
                        productId: productId,
                        quantity: quantity
                    }]
                })
            });

            const result = await response.json();
            modalManager.hide('loading-modal');

            if (result.success) {
                await this.updateCart();
                this.showSuccessToast('Item added to cart');
            } else {
                modalManager.showError(result.message);
            }
        } catch (error) {
            modalManager.hide('loading-modal');
            modalManager.showError('Failed to add item to cart');
        }
    }

    async handleQuantityChange(event, action) {
        const itemContainer = event.target.closest('.cart-item');
        const itemId = itemContainer.dataset.itemId;
        const quantityInput = itemContainer.querySelector('.item-quantity');
        const currentQty = parseInt(quantityInput.value);
        const newQty = action === 'decrease' ? currentQty - 1 : currentQty + 1;

        if (newQty < 1) return;

        sessionManager.updateLastActivity();
        modalManager.showLoading('Updating quantity...');

        try {
            const response = await fetch('/order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 2,
                    orderId: state.session.id,
                    productId: itemId,
                    quantity: newQty
                })
            });

            const result = await response.json();
            modalManager.hide('loading-modal');

            if (result.success) {
                await this.updateCart();
                quantityInput.value = newQty;
                this.updateItemSubtotal(itemContainer, newQty);
            } else {
                modalManager.showError(result.message);
            }
        } catch (error) {
            modalManager.hide('loading-modal');
            modalManager.showError('Failed to update quantity');
        }
    }

    async handleRemoveItem(event) {
        const itemId = event.target.closest('.cart-item').dataset.itemId;
        
        if (confirm('Are you sure you want to remove this item?')) {
            sessionManager.updateLastActivity();
            modalManager.showLoading('Removing item...');

            try {
                const response = await fetch('/order', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        outletId: state.session.outlet_id,
                        tableId: state.session.table_id,
                        brand: state.session.brand,
                        productId: itemId,
                        count: 1
                    })
                });

                const result = await response.json();
                modalManager.hide('loading-modal');

                if (result.success) {
                    await this.updateCart();
                    this.showSuccessToast('Item removed from cart');
                } else {
                    modalManager.showError(result.message);
                }
            } catch (error) {
                modalManager.hide('loading-modal');
                modalManager.showError('Failed to remove item');
            }
        }
    }

    async handlePlaceOrder() {
        if (state.cart.items.length === 0) {
            modalManager.showError('Your cart is empty');
            return;
        }

        modalManager.show('confirm-order-modal');
        this.populateConfirmationModal();
    }

    populateConfirmationModal() {
        const confirmModal = document.getElementById('confirm-order-modal');
        if (!confirmModal) return;

        const regularItems = document.getElementById('confirm-regular-items');
        const packageItems = document.getElementById('confirm-package-items');
        const totalItems = document.getElementById('confirm-total-items');
        const totalAmount = document.getElementById('confirm-total-amount');

        regularItems.textContent = state.cart.regularItems.length;
        packageItems.textContent = state.cart.packageItems.length;
        totalItems.textContent = state.cart.items.length;
        totalAmount.textContent = formatCurrency(state.cart.total);

        // Setup confirm button
        const confirmButton = confirmModal.querySelector('#submit-final-order');
        if (confirmButton) {
            confirmButton.onclick = () => orderManager.placeOrder();
        }
    }

    async updateCart() {
        if (!state.session) return;

        try {
            const response = await fetch(`/order/cart?outletId=${state.session.outlet_id}&tableId=${state.session.table_id}&brand=${state.session.brand}`);
            const result = await response.json();
            
            if (result.success) {
                this.updateCartState(result.data.cart);
                this.updateCartDisplay();
            }
        } catch (error) {
            console.error('Failed to update cart:', error);
        }
    }

    updateCartState(cartData) {
        state.cart.items = cartData.items || [];
        state.cart.total = cartData.total_amount || 0;
        state.cart.regularItems = cartData.items.filter(item => !item.is_package) || [];
        state.cart.packageItems = cartData.items.filter(item => item.is_package) || [];
    }

    updateCartDisplay() {
        // Update cart badge
        const cartCounter = document.getElementById('count-cart');
        if (cartCounter) {
            cartCounter.textContent = state.cart.items.length;
        }

        // Update cart modal if open
        const cartModal = document.getElementById('cart-modal');
        if (cartModal && cartModal.classList.contains('show')) {
            this.refreshCartDisplay();
        }
    }

    refreshCartDisplay() {
        this.populateRegularItems();
        this.populatePackageItems();
        this.updateCartTotals();
    }

    populateRegularItems() {
        const regularItemsList = document.getElementById('regular-items-list');
        if (!regularItemsList) return;

        regularItemsList.innerHTML = '';
        state.cart.regularItems.forEach(item => {
            const row = this.createRegularItemRow(item);
            regularItemsList.appendChild(row);
        });
    }

    createRegularItemRow(item) {
        const row = document.createElement('tr');
        row.className = 'cart-item';
        row.dataset.itemId = item.product_id;

        row.innerHTML = `
            <td>
                <div class="d-flex align-items-center">
                    <img src="${item.product_image}" alt="${item.product_name}" class="product-thumbnail me-2" width="50">
                    <div>${item.product_name}</div>
                </div>
            </td>
            <td>${formatCurrency(item.unit_price)}</td>
            <td>
                <div class="quantity-selector">
                    <div class="input-group input-group-sm">
                        <button class="btn btn-outline-secondary decrease-qty" type="button">-</button>
                        <input type="number" class="form-control text-center item-quantity" value="${item.quantity}" min="1" readonly>
                        <button class="btn btn-outline-secondary increase-qty" type="button">+</button>
                    </div>
                </div>
            </td>
            <td>
                <div class="d-flex align-items-center">
                    <span class="note-text">${item.notes || '-'}</span>
                    <button class="btn btn-sm btn-link add-note">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </td>
            <td>${formatCurrency(item.subtotal)}</td>
            <td>
                <button class="btn btn-sm btn-danger remove-item">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

        return row;
    }

    populatePackageItems() {
        const packageItemsContainer = document.getElementById('package-items-container');
        if (!packageItemsContainer) return;

        packageItemsContainer.innerHTML = '';
        state.cart.packageItems.forEach(packageItem => {
            const packageCard = this.createPackageCard(packageItem);
            packageItemsContainer.appendChild(packageCard);
        });
    }

    createPackageCard(packageItem) {
        const card = document.createElement('div');
        card.className = 'card mb-3 package-item';
        card.dataset.itemId = packageItem.id;

        let packageItemsHtml = '';
        if (packageItem.package_items) {
            packageItemsHtml = packageItem.package_items.map(item => `
                <div class="package-sub-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>${item.product_name}</span>
                        <span>${item.quantity}x</span>
                    </div>
                </div>
            `).join('');
        }

        card.innerHTML = `
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h6 class="card-title mb-0">${packageItem.product_name}</h6>
                    <button class="btn btn-sm btn-danger remove-item">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="package-items-list mb-3">
                    ${packageItemsHtml}
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="quantity-info">
                        Quantity: ${packageItem.quantity}
                    </div>
                    <div class="package-total">
                        Total: ${formatCurrency(packageItem.subtotal)}
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        Notes: ${packageItem.notes || 'No special instructions'}
                    </small>
                </div>
            </div>
        `;

        return card;
    }

    updateCartTotals() {
        const totalItems = document.getElementById('cart-total-items');
        const regularItems = document.getElementById('cart-regular-items');
        const packageItems = document.getElementById('cart-package-items');
        const totalAmount = document.getElementById('cart-total-amount');

        if (totalItems) totalItems.textContent = state.cart.items.length;
        if (regularItems) regularItems.textContent = state.cart.regularItems.length;
        if (packageItems) packageItems.textContent = state.cart.packageItems.length;
        if (totalAmount) totalAmount.textContent = formatCurrency(state.cart.total);
    }

    showAddNoteModal(event) {
        const itemContainer = event.target.closest('.cart-item');
        const itemId = itemContainer.dataset.itemId;
        const currentNote = itemContainer.querySelector('.note-text').textContent;
        
        document.getElementById('note-product-id').value = itemId;
        document.getElementById('item-note').value = currentNote === '-' ? '' : currentNote;
        
        modalManager.show('add-note-modal');
    }

    async handleSaveNote() {
        const productId = document.getElementById('note-product-id').value;
        const note = document.getElementById('item-note').value.trim();
        
        sessionManager.updateLastActivity();
        modalManager.showLoading('Saving note...');

        try {
            const response = await fetch('/order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 1,
                    orderId: state.session.id,
                    productId: productId,
                    notes: note
                })
            });

            const result = await response.json();
            modalManager.hide('loading-modal');

            if (result.success) {
                await this.updateCart();
                modalManager.hide('add-note-modal');
                this.showSuccessToast('Note saved successfully');
            } else {
                modalManager.showError(result.message);
            }
        } catch (error) {
            modalManager.hide('loading-modal');
            modalManager.showError('Failed to save note');
        }
    }

    startAutoUpdate() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }

        this.updateInterval = setInterval(() => {
            this.updateCart();
        }, CONFIG.REFRESH_INTERVAL);
    }

    showSuccessToast(message) {
        // Implementation depends on your toast library
        // For example, using Bootstrap's toast:
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-success border-0';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');

        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        document.body.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();

        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }

    cleanup() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
    }
}

// Order Manager
class OrderManager {
    constructor() {
        this.setupEventListeners();
    }

    setupEventListeners() {
        const submitOrderButton = document.getElementById('submit-final-order');
        if (submitOrderButton) {
            submitOrderButton.addEventListener('click', () => this.placeOrder());
        }
    }

    async placeOrder() {
        if (!state.session || state.cart.items.length === 0) {
            modalManager.showError('Cannot place empty order');
            return;
        }

        modalManager.showLoading('Placing your order...');

        try {
            const response = await fetch('/order/done', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    outletId: state.session.outlet_id,
                    tableId: state.session.table_id,
                    brand: state.session.brand
                })
            });

            const result = await response.json();
            modalManager.hide('loading-modal');

            if (result.success) {
                this.handleOrderSuccess(result.data);
            } else {
                modalManager.showError(result.message);
            }
        } catch (error) {
            modalManager.hide('loading-modal');
            modalManager.showError('Failed to place order');
        }
    }

    handleOrderSuccess(orderData) {
        modalManager.hide('confirm-order-modal');
        
        // Update success modal content
        document.getElementById('receipt-number').textContent = orderData.receipt_number;
        document.getElementById('order-id').textContent = orderData.order_id;
        document.getElementById('final-amount').textContent = formatCurrency(orderData.summary.total_amount);
        document.getElementById('order-time').textContent = formatDateTime(orderData.timing.order_time);

        // Show success modal
        modalManager.show('order-success-modal');

        // Reset cart state
        state.cart = {
            items: [],
            total: 0,
            regularItems: [],
            packageItems: []
        };

        // Redirect after delay
        setTimeout(() => {
            window.location.href = '/';
        }, 5000);
    }
}

// Package Manager
class PackageManager {
    constructor() {
        this.setupEventListeners();
    }

    setupEventListeners() {
        document.getElementById('show-packages').addEventListener('click', () => {
            this.showPackageBrowseModal();
        });

        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('select-package')) {
                this.handlePackageSelection(e);
            } else if (e.target.classList.contains('add-package')) {
                this.handleAddPackageToCart();
            }
        });
    }

    async showPackageBrowseModal() {
        modalManager.show('package-browse-modal');
        await this.loadPackages();
    }

    async loadPackages() {
        try {
            const response = await fetch(`/order/packages?outletId=${state.session.outlet_id}&brand=${state.session.brand}`);
            const result = await response.json();

            if (result.success) {
                this.populatePackageBrowseModal(result.data.packages);
            }
        } catch (error) {
            console.error('Failed to load packages:', error);
            modalManager.showError('Failed to load packages');
        }
    }

    populatePackageBrowseModal(packages) {
        const container = document.querySelector('#package-browse-modal .row');
        if (!container) return;

        container.innerHTML = packages.map(pkg => this.createPackageBrowseCard(pkg)).join('');
    }

    createPackageBrowseCard(packageData) {
        return `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">${packageData.name}</h5>
                        <p class="card-text">${packageData.description}</p>
                        <div class="package-details">
                            <h6>Package Contents:</h6>
                            <ul class="list-unstyled">
                                ${packageData.categories.map(cat => `
                                    <li>
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <span class="fw-bold">${cat.name}:</span>
                                        Choose ${cat.quantity} items
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                        <button class="btn btn-primary w-100 select-package" 
                                data-package-id="${packageData.id}">
                            Select Package
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    async handlePackageSelection(event) {
        const packageId = event.target.dataset.packageId;
        modalManager.hide('package-browse-modal');
        modalManager.show('loading-modal');

        try {
            const response = await fetch(`/order/packages/${packageId}`);
            const result = await response.json();

            modalManager.hide('loading-modal');
            
            if (result.success) {
                this.showPackageSelectionModal(result.data.package);
            } else {
                modalManager.showError(result.message);
            }
        } catch (error) {
            modalManager.hide('loading-modal');
            modalManager.showError('Failed to load package details');
        }
    }

    showPackageSelectionModal(packageData) {
        state.currentPackage = packageData;
        state.packageSelections.clear();
        
        modalManager.show('package-selection-modal');
        this.populatePackageSelectionModal();
    }

    populatePackageSelectionModal() {
        if (!state.currentPackage) return;

        // Update package info
        document.querySelector('.package-name').textContent = state.currentPackage.name;
        document.querySelector('.package-description').textContent = state.currentPackage.description;

        // Clear existing items
        const categoriesContainer = document.getElementById('package-categories');
        categoriesContainer.innerHTML = '';

        // Add categories and their items
        state.currentPackage.categories.forEach(category => {
            const categorySection = this.createCategorySection(category);
            categoriesContainer.appendChild(categorySection);
        });

        // Setup excluded items if any
        this.setupExcludedItems();

        // Initialize package summary
        this.updatePackageSummary();

        // Enable/disable add button based on selections
        this.validatePackageSelections();
    }

    createCategorySection(category) {
        const section = document.createElement('div');
        section.className = 'package-category mb-4';
        section.dataset.categoryId = category.id;

        section.innerHTML = `
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <h6 class="mb-0">${category.name}</h6>
                <div class="selection-count">
                    <span class="selected">0</span>
                    <span class="text-muted">/ ${category.required_qty} items</span>
                </div>
            </div>
            <div class="package-items row g-3">
                ${category.items.map(item => this.createPackageItemCard(item, category)).join('')}
            </div>
            <div class="progress mt-3" style="height: 5px;">
                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
            </div>
        `;

        return section;
    }

    createPackageItemCard(item, category) {
        return `
            <div class="col-md-4">
                <div class="card h-100 selectable-item" data-item-id="${item.id}" data-category-id="${category.id}">
                    <img src="${item.image_url}" class="card-img-top" alt="${item.name}" 
                         style="height: 120px; object-fit: cover;">
                    <div class="card-body">
                        <h6 class="card-title item-name">${item.name}</h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="price">${formatCurrency(item.price)}</span>
                            <div class="quantity-selector d-none">
                                <div class="input-group input-group-sm">
                                    <button class="btn btn-outline-secondary decrease-qty" type="button">-</button>
                                    <input type="number" class="form-control text-center item-quantity" value="0" min="0">
                                    <button class="btn btn-outline-secondary increase-qty" type="button">+</button>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary select-item">Select</button>
                        </div>
                        ${item.stock <= 5 ? `
                            <small class="text-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Only ${item.stock} left
                            </small>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    setupExcludedItems() {
        const excludedSection = document.getElementById('excluded-products-section');
        const excludedList = document.getElementById('excluded-items-list');

        if (!state.currentPackage.excluded_items || state.currentPackage.excluded_items.length === 0) {
            excludedSection.style.display = 'none';
            return;
        }

        excludedSection.style.display = 'block';
        excludedList.innerHTML = state.currentPackage.excluded_items.map(item => `
            <li class="mb-2">
                <i class="fas fa-ban text-danger me-2"></i>
                ${item.name}
            </li>
        `).join('');
    }

    handleItemSelection(event) {
        const itemCard = event.target.closest('.selectable-item');
        const itemId = itemCard.dataset.itemId;
        const categoryId = itemCard.dataset.categoryId;
        const category = state.currentPackage.categories.find(cat => cat.id === parseInt(categoryId));

        if (!category) return;

        const currentSelections = state.packageSelections.get(categoryId) || [];
        const isSelected = itemCard.classList.contains('selected');

        if (isSelected) {
            // Remove selection
            itemCard.classList.remove('selected');
            state.packageSelections.set(categoryId, 
                currentSelections.filter(id => id !== itemId)
            );
        } else {
            // Add selection if within limits
            if (currentSelections.length < category.required_qty) {
                itemCard.classList.add('selected');
                state.packageSelections.set(categoryId, 
                    [...currentSelections, itemId]
                );
            } else {
                this.showMaxSelectionWarning(category.name);
            }
        }

        this.updateCategoryProgress(categoryId);
        this.updatePackageSummary();
        this.validatePackageSelections();
    }

    updateCategoryProgress(categoryId) {
        const categorySection = document.querySelector(`.package-category[data-category-id="${categoryId}"]`);
        const category = state.currentPackage.categories.find(cat => cat.id === parseInt(categoryId));
        const selections = state.packageSelections.get(categoryId) || [];

        const progressBar = categorySection.querySelector('.progress-bar');
        const selectedCount = categorySection.querySelector('.selected');

        const progress = (selections.length / category.required_qty) * 100;
        progressBar.style.width = `${progress}%`;
        selectedCount.textContent = selections.length;
    }

    updatePackageSummary() {
        const summaryContainer = document.querySelector('.package-summary .selected-items-list');
        summaryContainer.innerHTML = '';

        let totalPrice = state.currentPackage.base_price;
        let selectedItems = [];

        state.packageSelections.forEach((itemIds, categoryId) => {
            const category = state.currentPackage.categories.find(cat => cat.id === parseInt(categoryId));
            
            itemIds.forEach(itemId => {
                const item = this.findItemInCategory(itemId, category);
                if (item) {
                    selectedItems.push(item);
                    if (item.additional_price) {
                        totalPrice += item.additional_price;
                    }
                }
            });
        });

        // Update summary display
        summaryContainer.innerHTML = selectedItems.map(item => `
            <div class="selected-item d-flex justify-content-between mb-2">
                <span>${item.name}</span>
                ${item.additional_price ? `<span>${formatCurrency(item.additional_price)}</span>` : ''}
            </div>
        `).join('');

        // Update totals
        document.querySelector('.base-price').textContent = formatCurrency(state.currentPackage.base_price);
        document.querySelector('.additional-price').textContent = formatCurrency(totalPrice - state.currentPackage.base_price);
        document.querySelector('.total-price').textContent = formatCurrency(totalPrice);
    }

    validatePackageSelections() {
        const addPackageButton = document.getElementById('add-package');
        let isValid = true;
        const validationMessages = [];

        state.currentPackage.categories.forEach(category => {
            const selections = state.packageSelections.get(category.id) || [];
            if (selections.length !== category.required_qty) {
                isValid = false;
                validationMessages.push(`Please select ${category.required_qty} items from ${category.name}`);
            }
        });

        this.displayValidationMessages(validationMessages);
        addPackageButton.disabled = !isValid;
    }

    displayValidationMessages(messages) {
        const container = document.getElementById('package-validation-messages');
        container.innerHTML = messages.map(msg => `
            <div class="alert alert-warning mb-2">
                <i class="fas fa-exclamation-circle me-2"></i>
                ${msg}
            </div>
        `).join('');
    }

    async handleAddPackageToCart() {
        if (!this.validatePackageSelections()) return;

        modalManager.showLoading('Adding package to cart...');

        try {
            const packageData = this.collectPackageData();
            
            const response = await fetch('/order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 3,
                    orderId: state.session.id,
                    packageId: state.currentPackage.id,
                    products: packageData
                })
            });

            const result = await response.json();
            modalManager.hide('loading-modal');

            if (result.success) {
                modalManager.hide('package-selection-modal');
                cartManager.updateCart();
                this.showSuccessToast('Package added to cart successfully');
            } else {
                modalManager.showError(result.message);
            }
        } catch (error) {
            modalManager.hide('loading-modal');
            modalManager.showError('Failed to add package to cart');
        }
    }

    collectPackageData() {
        let packageItems = [];
        state.packageSelections.forEach((itemIds, categoryId) => {
            itemIds.forEach(itemId => {
                packageItems.push({
                    productId: itemId,
                    categoryId: parseInt(categoryId),
                    quantity: 1 // Can be modified if quantities are allowed
                });
            });
        });
        return packageItems;
    }

    findItemInCategory(itemId, category) {
        return category.items.find(item => item.id === itemId);
    }

    showMaxSelectionWarning(categoryName) {
        modalManager.showError(`Maximum items already selected for ${categoryName}`);
    }

    showSuccessToast(message) {
        // Reuse the cart manager's toast implementation
        cartManager.showSuccessToast(message);
    }
}

// Utility Functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

function formatDateTime(dateString) {
    return new Date(dateString).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

class CategoryManager {
    constructor() {
        this.init();
    }

    init() {
        this.categorySelect = document.getElementById('category-select');
        if (this.categorySelect) {
            this.setupInitialValue();
            this.setupEventListeners();
        }
    }

    setupInitialValue() {
        const urlParams = new URLSearchParams(window.location.search);
        const category = urlParams.get('category');
        if (category && this.categorySelect) {
            this.categorySelect.value = category;
        }
    }

    setupEventListeners() {
        if (this.categorySelect) {
            this.categorySelect.addEventListener('change', (e) => {
                this.handleCategoryChange(e.target.value);
            });
        }
    }

    handleCategoryChange(categoryId) {
        try {
            const currentUrl = new URL(window.location.href);
            const outletId = currentUrl.searchParams.get('outletId');
            const tableId = currentUrl.searchParams.get('tableId');
            const brand = currentUrl.searchParams.get('brand');

            if (!outletId || !tableId || !brand) {
                console.error('Missing required parameters');
                return;
            }

            // Buat URL baru dengan menyimpan parameter lain
            const newUrl = new URL(`${window.location.origin}/order`);
            currentUrl.searchParams.forEach((value, key) => {
                if (key !== 'category') {
                    newUrl.searchParams.set(key, value);
                }
            });

            // Tambahkan kategori jika dipilih
            if (categoryId && categoryId !== 'all') {
                newUrl.searchParams.set('category', categoryId);
            }

            window.location.href = newUrl.toString();
        } catch (error) {
            console.error('Error handling category change:', error);
        }
    }
}

function filterProductsByCategory(categoryId) {
    const currentUrl = new URL(window.location.href);
    
    if (categoryId && categoryId !== 'all') {
        currentUrl.searchParams.set('category', categoryId);
    } else {
        currentUrl.searchParams.delete('category');
    }
    
    window.location.href = currentUrl.toString();
}

let modalManager, sessionManager, cartManager, packageManager, orderManager, categoryManager;

// Initialize application
document.addEventListener('DOMContentLoaded', async () => {
    const params = new URLSearchParams(window.location.search);
    
    // Initialize managers
    const modalManager = new ModalManager();
    const sessionManager = new SessionManager();
    const cartManager = new CartManager();
    const packageManager = new PackageManager();
    const orderManager = new OrderManager();
	const categoryManager = new CategoryManager();

    // Check existing session
    const sessionExists = await sessionManager.initialize(params);
    if (!sessionExists) {
        document.getElementById('identity-page').style.display = 'block';
    }

    // Setup customer registration form
    document.getElementById('submitCustomerInfo').addEventListener('click', async () => {
        const customerData = {
            name: document.getElementById('inputCustomerName').value,
            passcode: document.getElementById('inputPasscode').value
        };

        if (await sessionManager.createNewSession(params, customerData)) {
            cartManager.initialize();
            packageManager.initialize();
        }
    });

    // Initialize managers if session exists
    if (sessionExists) {
        cartManager.initialize();
        packageManager.initialize();
    }

    // Track user activity
    document.addEventListener('click', () => {
        sessionManager.updateLastActivity();
    });

    document.addEventListener('keypress', () => {
        sessionManager.updateLastActivity();
    });
	

    // Handle category filtering
	document.getElementById('category-select').addEventListener('change', (e) => {
		const categoryId = e.target.value;
		filterProductsByCategory(categoryId);
	});

	document.addEventListener('DOMContentLoaded', () => {
		const urlParams = new URLSearchParams(window.location.search);
		const category = urlParams.get('category');
		
		if (category) {
			const selectElement = document.getElementById('category-select');
			selectElement.value = category;
		}
	});
});