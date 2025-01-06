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
        }
    },
    cart: {
        items: [],
        total: 0,
        lastUpdate: null
    },
    ui: {
        loading: false,
        currentView: null,
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
    },
    
    clearAll() {
        this.listeners = {};
    }
};

/**
 * Error Boundary Implementation
 */
class ErrorBoundary {
    static errorTypes = {
        SESSION: 'SESSION_ERROR',
        ORDER: 'ORDER_ERROR',
        PRODUCT: 'PRODUCT_ERROR',
        NETWORK: 'NETWORK_ERROR',
        VALIDATION: 'VALIDATION_ERROR',
        LOCATION: 'LOCATION_ERROR'
    };

    static async handleError(error, context, type) {
        console.error(`[${type}] Error in ${context}:`, error);

        const errorDetails = {
            type,
            context,
            message: error.message,
            stack: error.stack,
            timestamp: new Date().toISOString(),
            sessionId: AppState.session.id,
            customerInfo: AppState.session.customer
        };

        try {
            await this.logError(errorDetails);
        } catch (loggingError) {
            console.error('Failed to log error:', loggingError);
        }

        await this.showErrorMessage(type, error);
    }

    static async logError(errorDetails) {
        try {
            const response = await fetch('/api/log-error', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(errorDetails)
            });

            if (!response.ok) {
                throw new Error('Failed to log error');
            }
        } catch (error) {
            console.error('Error logging failed:', error);
        }
    }

    static async showErrorMessage(type, error) {
        const errorMessages = {
            [this.errorTypes.SESSION]: {
                title: 'Kesalahan Sesi',
                text: 'Terjadi kesalahan pada sesi Anda. Halaman akan dimuat ulang.',
                icon: 'error',
                reload: true
            },
            [this.errorTypes.ORDER]: {
                title: 'Kesalahan Pesanan',
                text: 'Gagal memproses pesanan Anda. Silakan coba lagi.',
                icon: 'error',
                reload: false
            },
            [this.errorTypes.PRODUCT]: {
                title: 'Kesalahan Produk',
                text: 'Gagal memuat informasi produk. Silakan coba lagi.',
                icon: 'warning',
                reload: false
            },
            [this.errorTypes.NETWORK]: {
                title: 'Kesalahan Jaringan',
                text: 'Koneksi terputus. Pastikan Anda terhubung ke internet.',
                icon: 'warning',
                reload: false
            },
            [this.errorTypes.VALIDATION]: {
                title: 'Validasi Gagal',
                text: error.message || 'Data tidak valid. Silakan periksa kembali.',
                icon: 'warning',
                reload: false
            },
            [this.errorTypes.LOCATION]: {
                title: 'Kesalahan Lokasi',
                text: 'Gagal memverifikasi lokasi Anda. Pastikan GPS aktif.',
                icon: 'warning',
                reload: false
            }
        };

        const errorConfig = errorMessages[type] || {
            title: 'Kesalahan',
            text: 'Terjadi kesalahan. Silakan coba lagi.',
            icon: 'error',
            reload: false
        };

        await Swal.fire({
            title: errorConfig.title,
            text: errorConfig.text,
            icon: errorConfig.icon,
            confirmButtonText: errorConfig.reload ? 'Muat Ulang' : 'OK'
        });

        if (errorConfig.reload) {
            window.location.reload();
        }
    }
}

/**
 * API Service Implementation
 */
const ApiService = {
    baseUrl: window.location.origin,
    defaultTimeout: 10000,

    endpoints: {
        SESSION: '/order/session',
        CART: '/order/cart',
        ORDER: '/order/order',
        PRODUCT: '/order/product',
        LOCATION: '/order/verify-location'
    },

    async request(endpoint, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            timeout: this.defaultTimeout
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

        if (error.status === 404) {
            return new Error('RESOURCE_NOT_FOUND');
        }

        if (error.status === 422) {
            return new Error('VALIDATION_FAILED');
        }

        return error;
    },

    async validateSession(params) {
        return this.request(this.endpoints.SESSION, {
            method: 'GET',
            data: params
        });
    },

    async createSession(data) {
        return this.request(this.endpoints.SESSION, {
            method: 'POST',
            data: JSON.stringify(data)
        });
    },

    async getCart(params) {
        return this.request(this.endpoints.CART, {
            method: 'GET',
            data: params
        });
    },

    async updateCart(data) {
        return this.request(this.endpoints.CART, {
            method: 'POST',
            data: JSON.stringify(data)
        });
    },

    async getProducts(params) {
        return this.request(this.endpoints.PRODUCT, {
            method: 'GET',
            data: params
        });
    },

    async verifyLocation(coords) {
        return this.request(this.endpoints.LOCATION, {
            method: 'POST',
            data: JSON.stringify(coords)
        });
    }
};

/**
 * Session Manager Implementation
 */
class SessionManager {
    constructor() {
        this.state = {
            locationVerified: false,
            sessionActive: false,
            sessionTimer: null,
            autoExtendTimer: null,
            lastActivity: Date.now(),
            lastExtend: Date.now()
        };

        this.config = {
            sessionDuration: 15 * 60 * 1000,
            warningThreshold: 5 * 60 * 1000,
            locationRadius: 100,
            validation: {
                minNameLength: 3,
                maxNameLength: 50,
                minPasscodeLength: 4,
                maxPasscodeLength: 10
            },
            extendInterval: 60 * 1000,
            activityEvents: [
                'mousedown',
                'keydown',
                'scroll',
                'touchstart',
                'mousemove',
                'click'
            ]
        };

        this.bindMethods();
    }

    bindMethods() {
        this.handleActivityEvent = this.handleActivityEvent.bind(this);
        this.extendSession = this.extendSession.bind(this);
        this.checkSession = this.checkSession.bind(this);
        this.validateSessionInput = this.validateSessionInput.bind(this);
        this.createSession = this.createSession.bind(this);
        this.resumeSession = this.resumeSession.bind(this);
    }

    async initialize() {
        try {
            await this.setupLocationServices();
            await this.validateInitialSession();
            this.setupEventListeners();
            this.startActivityTracking();

            EventBus.emit('session:initialized');
            
            return true;
        } catch (error) {
            await ErrorBoundary.handleError(
                error,
                'SessionManager.initialize',
                ErrorBoundary.errorTypes.SESSION
            );
            return false;
        }
    }

    async setupLocationServices() {
        if (!navigator.geolocation) {
            throw new Error('Geolocation not supported');
        }

        try {
            const position = await this.getCurrentPosition();
            const locationValid = await this.validateLocation(position);

            if (!locationValid) {
                throw new Error('Location validation failed');
            }

            this.state.locationVerified = true;
            AppState.location.verified = true;
            AppState.location.coordinates = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude
            };

            this.updateLocationStatus('success', 'Lokasi terverifikasi');
        } catch (error) {
            this.updateLocationStatus('error', 'Gagal memverifikasi lokasi');
            throw error;
        }
    }

    getCurrentPosition() {
        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0
            });
        });
    }

    async validateLocation(position) {
        try {
            const response = await ApiService.verifyLocation({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude
            });

            return response.success;
        } catch (error) {
            console.error('Location validation error:', error);
            return false;
        }
    }

    setupEventListeners() {
        this.config.activityEvents.forEach(event => {
            document.addEventListener(event, this.handleActivityEvent, {
                passive: true
            });
        });

        $('#session-form').on('submit', async (e) => {
            e.preventDefault();
            if (await this.validateSessionInput()) {
                await this.createSession();
            }
        });

        $('#resume-form').on('submit', async (e) => {
            e.preventDefault();
            if (await this.validateResumeInput()) {
                await this.resumeSession();
            }
        });

        EventBus.on('session:expired', () => this.handleSessionExpired());
    }

    handleActivityEvent() {
        if (this.state.sessionActive) {
            this.state.lastActivity = Date.now();
            this.checkSessionExtension();
        }
    }

    async checkSessionExtension() {
        const timeSinceLastExtend = Date.now() - this.state.lastExtend;
        if (timeSinceLastExtend >= this.config.extendInterval) {
            await this.extendSession();
        }
    }

    async extendSession() {
        if (!this.state.sessionActive) return;

        try {
            const response = await ApiService.validateSession(this.getSessionParams());

            if (response.success && response.data.session) {
                this.updateSessionTimer(response.data.session.expire_at);
                this.state.lastExtend = Date.now();

                EventBus.emit('session:extended', response.data.session);
            }
        } catch (error) {
            await ErrorBoundary.handleError(
                error,
                'SessionManager.extendSession',
                ErrorBoundary.errorTypes.SESSION
            );
        }
    }

    getSessionParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            outletId: params.get('outletId'),
            tableId: params.get('tableId'),
            brand: params.get('brand')
        };
    }

    async validateSessionInput() {
        const customerName = $('#customer-name').val().trim();
        const passcode = $('#passcode').val().trim();
        const { validation } = this.config;

        if (
            customerName.length < validation.minNameLength ||
            customerName.length > validation.maxNameLength
        ) {
            await ErrorBoundary.handleError(
                new Error(`Nama harus antara ${validation.minNameLength} - ${validation.maxNameLength} karakter`),
                'SessionManager.validateSessionInput',
                ErrorBoundary.errorTypes.VALIDATION
            );
            return false;
        }

        if (
            passcode.length < validation.minPasscodeLength ||
            passcode.length > validation.maxPasscodeLength
        ) {
            await ErrorBoundary.handleError(
                new Error(`Passcode harus antara ${validation.minPasscodeLength} - ${validation.maxPasscodeLength} karakter`),
                'SessionManager.validateSessionInput',
                ErrorBoundary.errorTypes.VALIDATION
            );
            return false;
        }

        return true;
    }

    async validateResumeInput() {
        const passcode = $('#resume-passcode').val().trim();
        const { validation } = this.config;

        if (
            passcode.length < validation.minPasscodeLength ||
            passcode.length > validation.maxPasscodeLength
        ) {
            await ErrorBoundary.handleError(
                new Error(`Passcode harus antara ${validation.minPasscodeLength} - ${validation.maxPasscodeLength} karakter`),
                'SessionManager.validateResumeInput',
                ErrorBoundary.errorTypes.VALIDATION
            );
            return false;
        }

        return true;
    }

    updateLocationStatus(type, message, showProgress = false) {
        const $element = $('#location-verification');
        const $status = $('#location-status');
        const $progress = $('#location-progress');

        $element
            .removeClass('alert-warning alert-danger alert-success')
            .addClass(`alert-${type}`);

        $status.text(message);
        $progress.attr('hidden', !showProgress);
    }

    async validateInitialSession() {
        try {
            const response = await ApiService.validateSession(this.getSessionParams());

            if (!response.success) {
                throw new Error(response.message || 'Session validation failed');
            }

            if (response.data.session) {
                this.startSession(response.data);
            } else {
                this.showSessionCreation();
            }
        } catch (error) {
            await ErrorBoundary.handleError(
                error,
                'SessionManager.validateInitialSession',
                ErrorBoundary.errorTypes.SESSION
            );
        }
    }

    showSessionCreation() {
        $('#session-creation').removeAttr('hidden');
        $('#resume-session').attr('hidden', true);
        $('#active-session').attr('hidden', true);
    }

    async createSession() {
        if (!this.state.locationVerified) {
            await ErrorBoundary.handleError(
                new Error('Location not verified'),
                'SessionManager.createSession',
                ErrorBoundary.errorTypes.LOCATION
            );
            return;
        }

        try {
            AppState.ui.loading = true;
            this.showLoading(true);

            const position = await this.getCurrentPosition();
            const sessionData = {
                ...this.getSessionParams(),
                name: $('#customer-name').val().trim(),
                passcode: $('#passcode').val().trim(),
                latitude: position.coords.latitude,
                longitude: position.coords.longitude
            };

            const response = await ApiService.createSession(sessionData);

            if (response.success) {
                await this.startSession(response.data);
                await Swal.fire({
                    title: 'Sukses',
                    text: 'Sesi berhasil dibuat',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                throw new Error(response.message || 'Failed to create session');
            }
        } catch (error) {
            await ErrorBoundary.handleError(
                error,
                'SessionManager.createSession',
                ErrorBoundary.errorTypes.SESSION
            );
        } finally {
            AppState.ui.loading = false;
            this.showLoading(false);
        }
    }

    async resumeSession() {
        try {
            AppState.ui.loading = true;
            this.showLoading(true);

            const response = await ApiService.createSession({
                ...this.getSessionParams(),
                passcode: $('#resume-passcode').val().trim()
            });

            if (response.success) {
                await this.startSession(response.data);
                await Swal.fire({
                    title: 'Sukses',
                    text: 'Sesi berhasil dilanjutkan',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });

                window.location.reload();
            } else {
                throw new Error('Invalid passcode');
            }
        } catch (error) {
            await ErrorBoundary.handleError(
                error,
                'SessionManager.resumeSession',
                ErrorBoundary.errorTypes.SESSION
            );
        } finally {
            AppState.ui.loading = false;
            this.showLoading(false);
        }
    }

    startSession(sessionData) {
        this.state.sessionActive = true;
        
        // Update AppState
        AppState.session = {
            id: sessionData.session.id,
            status: sessionData.session.status,
            expiryTime: sessionData.session.expire_at,
            customer: {
                name: sessionData.session.name,
                table: sessionData.table.number
            }
        };

        // Update UI
        $('#session-page').attr('hidden', true);
        $('#session-creation').attr('hidden', true);
        $('#resume-session').attr('hidden', true);
        $('#active-session').removeAttr('hidden');
        $('#order-page').removeAttr('hidden');

        // Update session info
        $('#active-customer').text(sessionData.session.name);
        $('#active-table').text(sessionData.table.number);
        $('#session-start').text(this.formatDateTime(sessionData.session.created_at));
        
        // Update session status
        this.updateSessionStatus(sessionData.session.status);
        
        // Start timers
        this.startSessionTimer(new Date(sessionData.session.expire_at));
        this.startActivityTracking();

        // Emit event
        EventBus.emit('session:started', sessionData);
    }

    startSessionTimer(expireTime) {
        if (this.state.sessionTimer) {
            clearInterval(this.state.sessionTimer);
        }

        const updateTimer = () => {
            const now = new Date();
            const timeLeft = expireTime - now;

            if (timeLeft <= 0) {
                clearInterval(this.state.sessionTimer);
                this.handleSessionExpiration();
                return;
            }

            const minutes = Math.floor(timeLeft / 60000);
            const seconds = Math.floor((timeLeft % 60000) / 1000);

            $('#session-timer').text(
                `${minutes}:${seconds.toString().padStart(2, '0')}`
            );

            if (timeLeft <= this.config.warningThreshold && !$('#session-warning').is(':visible')) {
                this.showSessionWarning(minutes);
            }
        };

        updateTimer();
        this.state.sessionTimer = setInterval(updateTimer, 1000);
    }

    startActivityTracking() {
        if (this.state.autoExtendTimer) {
            clearInterval(this.state.autoExtendTimer);
        }

        this.state.autoExtendTimer = setInterval(() => {
            const timeSinceLastActivity = Date.now() - this.state.lastActivity;
            
            if (this.state.sessionActive && timeSinceLastActivity < 60000) {
                this.extendSession();
            }
        }, this.config.extendInterval);
    }

    showSessionWarning(minutesLeft) {
        $('#session-warning')
            .removeAttr('hidden')
            .find('#warning-time')
            .text(minutesLeft);
    }

    async handleSessionExpiration() {
        this.state.sessionActive = false;
        
        clearInterval(this.state.sessionTimer);
        clearInterval(this.state.autoExtendTimer);

        AppState.session = {
            id: null,
            status: null,
            expiryTime: null,
            customer: {
                name: null,
                table: null
            }
        };

        await Swal.fire({
            title: 'Sesi Berakhir',
            text: 'Sesi Anda telah berakhir. Halaman akan dimuat ulang.',
            icon: 'warning',
            confirmButtonText: 'OK',
            allowOutsideClick: false
        });

        window.location.reload();
    }

    formatDateTime(dateString) {
        return new Date(dateString).toLocaleString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    updateSessionStatus(status) {
        const statusMap = {
            'RESERVED': {
                class: 'bg-warning',
                text: 'Dipesan'
            },
            'ORDERED': {
                class: 'bg-success',
                text: 'Diproses'
            },
            'COMPLETED': {
                class: 'bg-info',
                text: 'Selesai'
            }
        };

        const statusInfo = statusMap[status] || {
            class: 'bg-secondary',
            text: status
        };

        $('#session-status')
            .removeClass()
            .addClass(`badge ${statusInfo.class}`)
            .text(statusInfo.text);
    }

    showLoading(show = true) {
        AppState.ui.loading = show;
        $('#loading-overlay').attr('hidden', !show);
    }

    cleanup() {
        clearInterval(this.state.sessionTimer);
        clearInterval(this.state.autoExtendTimer);

        this.config.activityEvents.forEach(event => {
            document.removeEventListener(event, this.handleActivityEvent);
        });

        EventBus.off('session:expired');
    }
}