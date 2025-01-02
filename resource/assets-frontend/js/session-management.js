// session-management.js
class SessionManager {
    constructor() {
        this.locationVerified = false;
        this.sessionActive = false;
        this.sessionTimer = null;
        this.params = new URLSearchParams(window.location.search);
        this.outletId = this.params.get('outletId');
        this.tableId = this.params.get('tableId');
        this.brand = this.params.get('brand');
        this.lastActivity = Date.now();
        this.autoExtendTimer = null;
        
        this.activityEvents = [
            'mousedown', 
            'keydown', 
            'scroll', 
            'touchstart', 
            'mousemove',  
            'click'       
        ];
        
        this.config = {
            sessionDuration: 15 * 60 * 1000, // 15 minutes
            warningThreshold: 5 * 60 * 1000,  // 5 minutes
            locationRadius: 100, // meters
            minNameLength: 3,
            minPasscodeLength: 4
        };

        this.initializeEventListeners();
    }

    /**
     * Initialize the session manager
     */
    async initialize() {
        try {
            this.showLoading(true);
            await this.initializeLocation();
            await this.checkExistingSession();
            this.initializeAutoExtend();
        } catch (error) {
            console.error('Initialization error:', error);
            this.handleError('Gagal menginisialisasi sistem', error);
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Initialize all event listeners
     */
    initializeEventListeners() {
        // Form submission handlers
        $('#session-form').on('submit', (e) => {
            e.preventDefault();
            if (this.validateSessionInput() && e.target.checkValidity()) {
                this.createSession();
            }
            $(e.target).addClass('was-validated');
        });

        $('#resume-form').on('submit', (e) => {
            e.preventDefault();
            if (this.validateResumeInput()) {
                this.resumeSession();
            }
        });

        // Tracking aktivitas untuk perpanjangan sesi
        this.activityEvents.forEach(event => {
            document.addEventListener(event, this.updateLastActivity.bind(this), {
                passive: true  // Optimasi performa
            });
        });
    }

    // Method baru untuk memperbarui aktivitas terakhir
    updateLastActivity() {
        if (this.sessionActive) {
            this.lastActivity = Date.now();
        }
    }

    /**
     * Initialize auto-extend functionality
     */
    initializeAutoExtend() {
        // Hapus timer sebelumnya jika ada
        if (this.autoExtendTimer) {
            clearInterval(this.autoExtendTimer);
        }

        // Periksa dan perpanjang sesi setiap 30 detik
        this.autoExtendTimer = setInterval(() => {
            const timeSinceLastActivity = Date.now() - this.lastActivity;
            
            // Perpanjang sesi jika:
            // 1. Sesi aktif
            // 2. Aktivitas terakhir kurang dari 1 menit yang lalu
            if (this.sessionActive && timeSinceLastActivity < 60000) { 
                this.extendSession();
            }
        }, 30000);
    }

    /**
     * Initialize location verification
     */
    async initializeLocation() {
    try {
        this.updateLocationStatus('warning', 'Memeriksa lokasi...', true);
        const position = await this.getCurrentPosition();
        
        const locationValid = await this.validateLocation(position);
        // Hapus throw error dan ganti dengan return
        if (!locationValid) {
            this.locationVerified = false;
            this.updateLocationStatus('warning', 'Lokasi diluar jangkauan outlet');
            return false;
        }

        this.locationVerified = true;
        this.updateLocationStatus('success', 'Lokasi terverifikasi');
        return true;
    } catch (error) {
        console.error('Location error:', error);
        this.updateLocationStatus('warning', 'Gagal mendapatkan lokasi');
        return false;
    }
}

    /**
     * Validate location with server
     */
    async validateLocation(position) {
    try {
        const response = await $.ajax({
            type: 'POST',
            url: `${window.location.origin}/order/session`,
            data: JSON.stringify({
                outletId: this.outletId,
                tableId: this.tableId,
                brand: this.brand,
                name: "temp",  // Add required fields
                passcode: "temp", // Add required fields
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                verifyLocation: true
            }),
            contentType: 'application/json'
        });
        return response.success;
    } catch (error) {
        console.error('Location validation error:', error);
        if (error.responseJSON?.code === '006') {
            return true;
        }
        throw error;
    }
}

    /**
     * Create new session
     */
    async createSession() {
        if (!this.locationVerified) {
            this.showError('Mohon verifikasi lokasi terlebih dahulu');
            return;
        }
    
        try {
            this.showLoading(true);
            const position = await this.getCurrentPosition();
            
            const response = await $.ajax({
                type: 'POST',
                url: `${window.location.origin}/order/session`,
                data: JSON.stringify({
                    outletId: this.outletId,
                    tableId: this.tableId,
                    brand: this.brand,
                    name: $('#customer-name').val(),
                    passcode: $('#passcode').val(),
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    verifyLocation: true
                }),
                contentType: 'application/json'
            });    

            if (response.success) {
                this.startSession(response.data);
                this.showSuccess('Sesi berhasil dibuat');
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.handleError('Gagal membuat sesi', error);
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Resume existing session
     */
    async resumeSession() {
        try {
            this.showLoading(true);
            
            const response = await $.ajax({
                type: 'POST',
                url: `${window.location.origin}/order/session`,
                data: JSON.stringify({
                    outletId: this.outletId,
                    tableId: this.tableId,
                    brand: this.brand,
                    passcode: $('#resume-passcode').val()
                }),
                contentType: 'application/json'
            });
    
            if (response.success) {
                // Sembunyikan semua elemen terkait sesi sebelumnya
                $('#resume-session').attr('hidden', true);
                $('#session-creation').attr('hidden', true);
                
                this.startSession(response.data);
                this.showSuccess('Sesi berhasil dilanjutkan');
    
                // Optional: reload untuk memastikan UI ter-reset
                window.location.href = `/order?outletId=${this.outletId}&tableId=${this.tableId}&brand=${this.brand}`;
console.log('Navigasi ke:', `/order?outletId=${this.outletId}&tableId=${this.tableId}&brand=${this.brand}`);
            } else {
                this.showError('Passcode tidak valid');
            }
        } catch (error) {
            this.handleError('Gagal melanjutkan sesi', error);
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Start session and initialize timers
     */
    startSession(sessionData) {
    this.sessionActive = true;
    
    // Hide ALL session-related elements first
    $('#session-page').attr('hidden', true);
    $('#session-creation').attr('hidden', true);
    $('#resume-session').attr('hidden', true);
    
    // Show only active session elements
    $('#active-session').removeAttr('hidden');
    $('#order-page').removeAttr('hidden');
    
    // Update session info
    $('#active-customer').text(sessionData.session.name);
    $('#active-table').text(this.tableId);
    $('#session-start').text(this.formatDateTime(sessionData.session.created_at));
    this.updateSessionStatus(sessionData.session.status);
    
    // Start timers
    this.startSessionTimer(new Date(sessionData.session.expire_at));
    this.startSessionMonitoring();
    this.initializeAutoExtend();
}

    /**
     * Start session timer
     */
    startSessionTimer(expireTime) {
        if (this.sessionTimer) {
            clearInterval(this.sessionTimer);
        }

        const updateTimer = () => {
            const now = new Date();
            const timeLeft = expireTime - now;

            if (timeLeft <= 0) {
                clearInterval(this.sessionTimer);
                this.handleSessionExpiration();
                return;
            }

            const minutes = Math.floor(timeLeft / 60000);
            const seconds = Math.floor((timeLeft % 60000) / 1000);

            $('#session-timer').text(
                `${minutes}:${seconds.toString().padStart(2, '0')}`
            );

            // Show warning if less than 5 minutes
            if (timeLeft <= this.config.warningThreshold) {
                $('#session-warning')
                    .removeAttr('hidden')
                    .find('#warning-time')
                    .text(minutes);
            }
        };

        updateTimer();
        this.sessionTimer = setInterval(updateTimer, 1000);
    }

    /**
     * Start session monitoring
     */
    startSessionMonitoring() {
        setInterval(async () => {
            if (this.sessionActive) {
                try {
                    const response = await $.ajax({
                        type: 'GET',
                        url: `${window.location.origin}/order/session?${this.params.toString()}`
                    });

                    if (!response.success) {
                        this.handleSessionExpiration();
                    }
                } catch (error) {
                    console.error('Session monitoring error:', error);
                }
            }
        }, 30000);
    }

    /**
     * Extend session
     */
    async extendSession() {
        if (!this.sessionActive) return;

        try {
            const response = await $.ajax({
                type: 'GET',
                url: `${window.location.origin}/order/session?${this.params.toString()}`
            });

            if (response.success && response.data.session) {
                // Perbarui timer sesi dengan waktu kedaluwarsa baru
                this.startSessionTimer(new Date(response.data.session.expire_at));
                
                // Sembunyikan peringatan sesi
                $('#session-warning').attr('hidden', true);
            }
        } catch (error) {
            console.error('Session extension error:', error);
        }
    }

    /**
     * Handle session expiration
     */
    handleSessionExpiration() {
        this.sessionActive = false;
        clearInterval(this.sessionTimer);
        clearInterval(this.autoExtendTimer);

        Swal.fire({
            title: 'Sesi Berakhir',
            text: 'Sesi Anda telah berakhir. Halaman akan dimuat ulang.',
            icon: 'warning',
            confirmButtonText: 'OK'
        }).then(() => {
            window.location.reload();
        });
    }

    /**
     * Check for existing session
     */
    async checkExistingSession() {
    try {
        const response = await $.ajax({
            type: 'GET',
            url: `${window.location.origin}/order/session?${this.params.toString()}`
        });

        if (response.success && response.data.session) {
            const sessionStatus = response.data.session.status;
            
            // Hide all session elements first
            $('#session-page, #session-creation, #resume-session').attr('hidden', true);
            
            if (sessionStatus === 'RESERVED') {
                // Only show resume for RESERVED status
                $('#resume-session').removeAttr('hidden');
            } else {
                // For other statuses, show active session
                $('#active-session').removeAttr('hidden');
                $('#order-page').removeAttr('hidden');
                this.startSession(response.data);
            }
        } else {
            // No active session, show creation form
            $('#session-creation').removeAttr('hidden');
            $('#resume-session, #active-session').attr('hidden', true);
        }
    } catch (error) {
        console.error('Error checking session:', error);
        this.showError('Gagal memeriksa status sesi');
    }
}

    /**
     * Validate session input
     */
    validateSessionInput() {
        const customerName = $('#customer-name').val();
        const passcode = $('#passcode').val();
        
        if (!customerName || customerName.length < this.config.minNameLength) {
            this.showError(`Nama pelanggan minimal ${this.config.minNameLength} karakter`);
            return false;
        }
        
        if (!passcode || passcode.length < this.config.minPasscodeLength) {
            this.showError(`Passcode minimal ${this.config.minPasscodeLength} karakter`);
            return false;
        }
        
        return true;
    }

    /**
     * Validate resume session input
     */
    validateResumeInput() {
        const passcode = $('#resume-passcode').val();
        
        if (!passcode || passcode.length < this.config.minPasscodeLength) {
            this.showError(`Passcode minimal ${this.config.minPasscodeLength} karakter`);
            return false;
        }
        
        return true;
    }

    /**
     * Update session status UI
     */
    updateSessionStatus(status) {
        const statusMap = {
            'RESERVED': { class: 'bg-warning', text: 'Dipesan' },
            'ORDERED': { class: 'bg-success', text: 'Diproses' },
            'COMPLETED': { class: 'bg-info', text: 'Selesai' }
        };

        const statusInfo = statusMap[status] || { class: 'bg-secondary', text: status };
        
        $('#session-status')
            .removeClass()
            .addClass(`badge ${statusInfo.class}`)
            .text(statusInfo.text);
    }

    /**
     * Update location status UI
     */
    updateLocationStatus(type, message, showProgress = false) {
        const element = $('#location-verification');
        element
            .removeClass('alert-warning alert-danger alert-success')
            .addClass(`alert-${type}`);
        $('#location-status').text(message);
        $('#location-progress').attr('hidden', !showProgress);
    }

    /**
     * Helper method to get current position
     */
    getCurrentPosition() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Browser tidak mendukung geolokasi'));
                return;
            }

            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0
            });
        });
    }

    /**
     * Helper method to format date time
     */
    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    /**
     * Show loading overlay
     */
    showLoading(show = true) {
        $('#loading-overlay').attr('hidden', !show);
    }

    /**
     * Show error using SweetAlert2
     */
    showError(message) {
        Swal.fire({
            title: 'Error',
            text: message,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    }

    /**
     * Show success message using SweetAlert2
     */
    showSuccess(message) {
        Swal.fire({
            title: 'Sukses',
            text: message,
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    }

    /**
     * Handle errors
     */
    handleError(title, error) {
        const errorMessage = error.responseJSON?.message || error.message || title;
        console.error(`${title}:`, error);
        this.showError(errorMessage);
    }
}

// Initialize session management when document is ready
$(document).ready(() => {
    const sessionManager = new SessionManager();
    sessionManager.initialize();
});