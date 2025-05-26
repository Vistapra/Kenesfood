class SessionManager {
  constructor () {
    this.debug = true
    this.locationVerified = false
    this.sessionActive = false
    this.sessionTimer = null
    this.params = new URLSearchParams(window.location.search)

    // Parameter URL
    this.outletId = this.params.get('outletId')
    this.tableId = this.params.get('tableId')
    this.brand = this.params.get('brand')
    this.kasirId = this.params.get('kasirId') // Parameter baru untuk kasir

    this.lastActivity = Date.now()
    this.autoExtendTimer = null
    this.activityEvents = [
      'mousedown',
      'keydown',
      'scroll',
      'touchstart',
      'mousemove',
      'click'
    ]

    this.config = {
      sessionDuration: 15 * 60 * 1000, // 15 minutes
      warningThreshold: 5 * 60 * 1000, // 5 minutes
      locationRadius: 100, // meters
      minNameLength: 3,
      minPasscodeLength: 4
    }

    this.initialized = false

    // Deteksi jenis akses
    this.accessType = this.detectAccessType()

    // Bypass geolocation berdasarkan access type
    this.bypassGeolocation = this.shouldBypassGeolocation()

    // Log informasi akses
    if (this.debug) {
      console.log('🔐 Access Type:', this.accessType)
      console.log('📍 Geolocation Bypass:', this.bypassGeolocation)
      if (this.accessType === 'kasir') {
        console.log('👨‍💼 Kasir ID:', this.kasirId)
      }
    }
  }

  /**
   * Initialize the session manager
   */
  async initialize () {
    console.group('🚀 Session Manager Initialization')
    console.log('Access Type:', this.accessType)

    try {
      // 1. Validasi parameter URL
      if (!this.validateURLParameters()) {
        this.showError(
          'Parameter URL Tidak Valid',
          'Silakan pastikan semua parameter yang diperlukan tersedia dan benar.'
        )
        console.groupEnd()
        return false
      }

      // 2. Tampilkan informasi akses di UI
      this.updateAccessTypeUI()

      // 3. Cek parameter receipt
      const urlParams = new URLSearchParams(window.location.search)
      const hasReceiptParam = urlParams.get('receipt') === 'true'
      const hasStatusParam = urlParams.has('status')

      if (hasReceiptParam || hasStatusParam) {
        console.warn('⚠️ Detected receipt parameters, cleaning URL...')
        this.cleanURL()
        return false
      }

      // 4. Kontrol visibilitas halaman
      this.controlPageVisibility()

      // 5. Set flag verifikasi lokasi
      this.locationVerified = false

      // 6. Cek sesi yang sudah ada
      const existingSession = await this.checkForExistingSessionBeforeCreation()

      if (existingSession) {
        this.locationVerified = true
        this.updateLocationStatus('success', 'Sesi aktif ditemukan ✓')

        try {
          const sessionData = await this.getExistingSessionData()
          if (sessionData && sessionData.data && sessionData.data.session) {
            this.startSession(sessionData.data)
            console.log('✅ Existing session started automatically')
          } else {
            $('#session-creation').removeAttr('hidden')
            $('#resume-session').attr('hidden', true)
          }
        } catch (error) {
          console.error('❌ Failed to get session data:', error)
          $('#session-creation').removeAttr('hidden')
          $('#resume-session').attr('hidden', true)
        }

        console.log('🔄 Existing session found, skipping location verification')
        console.groupEnd()
        return true
      }

      console.log('🆕 No existing session found')

      // 7. Update status lokasi untuk public/kasir
      if (this.accessType === 'kasir') {
        this.updateLocationStatus(
          'info',
          'Akses Kasir - Verifikasi lokasi akan dilewati saat membuat sesi'
        )
      } else {
        this.updateLocationStatus(
          'info',
          'Lokasi akan diverifikasi saat membuat sesi'
        )
      }

      // 8. Tampilkan form pembuatan sesi
      $('#session-creation').removeAttr('hidden')
      $('#resume-session').attr('hidden', true)
      $('#order-page').attr('hidden', true)

      this.initializeOrderCancellationListener()

      console.groupEnd()
      return true
    } catch (error) {
      console.error('❌ Session initialization error:', error)
      this.updateLocationStatus(
        'danger',
        'Gagal mempersiapkan sistem: ' + error.message
      )
      $('#order-page').attr('hidden', true)
      console.groupEnd()
      return false
    }
  }

  updateAccessTypeUI () {
    const accessBadge = document.createElement('div')
    accessBadge.className = 'alert alert-info mb-3'

    if (this.accessType === 'kasir') {
      accessBadge.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-person-badge me-2"></i>
                    <div>
                        <strong>Mode Kasir</strong>
                        <div class="small">ID Kasir: ${this.kasirId}</div>
                        <div class="small text-muted">Akses khusus dengan beberapa pembatasan yang dilewati</div>
                    </div>
                </div>
            `
    } else {
      accessBadge.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-globe me-2"></i>
                    <div>
                        <strong>Mode Publik</strong>
                        <div class="small text-muted">Akses umum dengan validasi penuh</div>
                    </div>
                </div>
            `
    }

    // Sisipkan badge sebelum form session-creation
    const sessionCreation = document.getElementById('session-creation')
    if (sessionCreation) {
      sessionCreation.parentNode.insertBefore(accessBadge, sessionCreation)
    }
  }

  detectAccessType () {
    if (this.kasirId && this.kasirId.trim() !== '') {
      return 'kasir'
    }
    return 'public'
  }

  shouldBypassGeolocation () {
    // Kasir bisa bypass geolocation
    if (this.accessType === 'kasir') {
      return true
    }

    // Development mode bypass
    if (
      window.location.hostname === 'localhost' ||
      window.location.hostname === '127.0.0.1'
    ) {
      return true
    }

    return false
  }

  validateURLParameters () {
    const requiredParams = ['outletId', 'tableId', 'brand']

    for (const param of requiredParams) {
      if (!this[param] || this[param].trim() === '') {
        console.error(`❌ Missing required parameter: ${param}`)
        return false
      }
    }

    // Validasi khusus untuk kasir
    if (this.accessType === 'kasir') {
      if (!this.kasirId || this.kasirId.trim() === '') {
        console.error('❌ Missing kasirId for kasir access')
        return false
      }

      // Validasi format kasirId (harus numerik)
      if (!/^\d+$/.test(this.kasirId)) {
        console.error('❌ Invalid kasirId format')
        return false
      }
    }

    return true
  }

  controlPageVisibility () {
    const orderPage = $('#order-page')
    const sessionPage = $('#session-page')
    const sessionCreation = $('#session-creation')
    const activeSession = $('#active-session')
    const resumeSession = $('#resume-session')
    const receiptView = $('#order-receipt-view')

    // Mode receipt - langsung tampilkan receipt
    const urlParams = new URLSearchParams(window.location.search)
    const isReceiptMode = urlParams.get('receipt') === 'true'

    if (isReceiptMode) {
      console.log('📄 Receipt mode detected')
      sessionPage.attr('hidden', true)
      sessionCreation.attr('hidden', true)
      activeSession.attr('hidden', true)
      resumeSession.attr('hidden', true)
      orderPage.attr('hidden', true)
      return
    }

    // Pastikan order page tersembunyi di awal
    if (!orderPage.is('[hidden]')) {
      console.warn('⚠️ Order page visible without active session, hiding it')
      orderPage.attr('hidden', true)
    }

    // Sembunyikan receipt view jika ada
    if (receiptView && !receiptView.is('[hidden]')) {
      receiptView.attr('hidden', true)
    }

    // Tampilkan session page
    sessionPage.removeAttr('hidden')
  }

  /**
   * Listen for order cancellation events
   */
  initializeOrderCancellationListener () {
    console.log('Initializing order cancellation listener')

    document.addEventListener('orderCancelled', event => {
      console.log('Order cancelled event received', event.detail)

      // Jika sesi masih aktif, akhiri sesi
      if (this.sessionActive) {
        console.log(
          'Active session detected, ending it due to order cancellation'
        )
        this.endSession(true)
      } else {
        console.log('No active session to end')
      }
    })
  }

  cleanURL () {
    const cleanParams = new URLSearchParams({
      outletId: this.outletId,
      tableId: this.tableId,
      brand: this.brand,
      _: Date.now()
    })

    // Tambahkan kasirId jika ada
    if (this.accessType === 'kasir' && this.kasirId) {
      cleanParams.set('kasirId', this.kasirId)
    }

    const cleanUrl = window.location.pathname + '?' + cleanParams.toString()
    console.log('🔀 Redirecting to clean URL:', cleanUrl)
    window.location.replace(cleanUrl)
  }

  /**
   * End current session
   */
  async endSession (force = false) {
    console.group('Ending Session')
    console.log('Force end parameter:', force)

    try {
      // Jika tidak ada sesi aktif, tidak perlu melakukan apa-apa
      if (!this.sessionActive) {
        console.log('No active session to end')
        console.groupEnd()
        return
      }

      // Tampilkan loading state
      this.showLoading(true)

      // Hentikan semua timer
      if (this.sessionTimer) {
        clearInterval(this.sessionTimer)
      }

      if (this.autoExtendTimer) {
        clearInterval(this.autoExtendTimer)
      }

      // Siapkan payload untuk endpoint
      const payload = {
        outletId: this.outletId,
        tableId: this.tableId,
        brand: this.brand,
        forceEnd: force // Tambahkan parameter force
      }

      console.log('Sending endSession request with payload:', payload)

      // Kirim request ke server
      const response = await $.ajax({
        type: 'POST',
        url: `${window.location.origin}/order/endSession`,
        data: JSON.stringify(payload),
        contentType: 'application/json',
        dataType: 'json'
      })

      console.log('End session response:', response)

      // Update state session
      this.sessionActive = false
      window.orderManagerState = window.orderManagerState || {}
      window.orderManagerState.sessionActive = false

      // Tampilkan konfirmasi selesai
      Swal.fire({
        title: 'Sesi Berakhir',
        text: force
          ? 'Pesanan Anda telah dibatalkan.'
          : 'Sesi Anda telah berakhir.',
        icon: 'info',
        confirmButtonText: 'OK',
        allowOutsideClick: false,
        allowEscapeKey: false
      }).then(() => {
        // Reset UI
        this.resetUIState()

        // Buat URL baru tanpa parameter tambahan
        const params = new URLSearchParams({
          outletId: this.outletId,
          tableId: this.tableId,
          brand: this.brand,
          _: Date.now() // Tambahkan timestamp untuk mencegah caching
        })

        // Redirect ke URL baru
        window.location.href = `${
          window.location.pathname
        }?${params.toString()}`
      })
    } catch (error) {
      console.error('Error ending session:', error)

      // Sembunyikan loading
      this.showLoading(false)

      // Tampilkan error
      this.showError(
        'Gagal mengakhiri sesi',
        error.responseJSON?.message || error.message
      )

      // Jika force=true, tetap refresh halaman meskipun ada error
      if (force) {
        Swal.fire({
          title: 'Sesi Berakhir',
          text: 'Terjadi kesalahan, tetapi pesanan tetap dibatalkan. Halaman akan dimuat ulang.',
          icon: 'info',
          confirmButtonText: 'OK',
          allowOutsideClick: false,
          allowEscapeKey: false
        }).then(() => {
          // Buat URL baru
          const params = new URLSearchParams({
            outletId: this.outletId,
            tableId: this.tableId,
            brand: this.brand,
            _: Date.now()
          })

          // Redirect ke URL baru
          window.location.href = `${
            window.location.pathname
          }?${params.toString()}`
        })
      }
    } finally {
      // Pastikan loading indicator disembunyikan
      this.showLoading(false)
      console.groupEnd()
    }
  }

  async getExistingSessionData () {
    try {
      const params = new URLSearchParams({
        outletId: this.outletId,
        tableId: this.tableId,
        brand: this.brand
      })

      // Tambahkan kasirId jika access type kasir
      if (this.accessType === 'kasir' && this.kasirId) {
        params.set('kasirId', this.kasirId)
      }

      const response = await $.ajax({
        type: 'GET',
        url: `${window.location.origin}/order/session`,
        data: params.toString(),
        dataType: 'json',
        timeout: 3000
      })

      return response
    } catch (error) {
      console.error('❌ Error fetching session data:', error)
      throw error
    }
  }

  async checkForExistingSessionBeforeCreation () {
    try {
      const params = new URLSearchParams({
        outletId: this.outletId,
        tableId: this.tableId,
        brand: this.brand
      })

      // Tambahkan kasirId jika access type kasir
      if (this.accessType === 'kasir' && this.kasirId) {
        params.set('kasirId', this.kasirId)
      }

      const response = await $.ajax({
        type: 'GET',
        url: `${window.location.origin}/order/session`,
        data: params.toString(),
        dataType: 'json',
        timeout: 3000
      })

      if (response.success && response.data && response.data.session) {
        const session = response.data.session
        const expireTimeStr = session.expire_at

        if (expireTimeStr) {
          const expireTime = new Date(expireTimeStr)
          const currentTime = new Date()

          if (currentTime < expireTime) {
            console.log('✅ Found active session during check')
            return true
          }
        }
      }

      return false
    } catch (error) {
      if (error.status === 404) {
        return false
      }

      console.warn('⚠️ Error checking for existing session:', error)
      return false
    }
  }

  /**
   * Initialize all event listeners
   */
  initializeEventListeners () {
    // Form submission handlers
    $('#session-form').on('submit', e => {
      e.preventDefault()
      if (this.validateSessionInput() && e.target.checkValidity()) {
        this.createSession()
      }
      $(e.target).addClass('was-validated')
    })

    // Activity tracking
    const self = this
    const events = [
      'mousedown',
      'keydown',
      'scroll',
      'touchstart',
      'mousemove',
      'click'
    ]

    events.forEach(function (event) {
      document.addEventListener(
        event,
        function () {
          if (self.sessionActive) {
            self.lastActivity = Date.now()
          }
        },
        { passive: true }
      )
    })
  }

  /**
   * Initialize auto-extend functionality
   */
  initializeAutoExtend () {
    // Hapus timer sebelumnya jika ada
    if (this.autoExtendTimer) {
      clearInterval(this.autoExtendTimer)
    }

    // Periksa dan perpanjang sesi setiap 30 detik
    this.autoExtendTimer = setInterval(() => {
      const timeSinceLastActivity = Date.now() - this.lastActivity

      // Perpanjang sesi jika:
      // 1. Sesi aktif
      // 2. Aktivitas terakhir kurang dari 1 menit yang lalu
      if (this.sessionActive && timeSinceLastActivity < 60000) {
        this.extendSession()
      }
    }, 30000)
  }

  /**
   * Initialize location verification
   */
  async initializeLocation () {
    // If bypassing geolocation, return true immediately
    if (this.bypassGeolocation) {
      this.locationVerified = true
      this.updateLocationStatus(
        'success',
        'Verifikasi lokasi dilewati (mode development)'
      )
      return true
    }

    try {
      if (!navigator.geolocation) {
        this.updateLocationStatus('warning', 'Geolocation tidak didukung')
        return false
      }

      return new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(
          async position => {
            try {
              const locationValid = await this.validateLocation(position)
              if (locationValid) {
                this.locationVerified = true
                this.updateLocationStatus('success', 'Lokasi terverifikasi')
              } else {
                this.updateLocationStatus('warning', 'Lokasi di luar jangkauan')
              }
              resolve(locationValid)
            } catch (error) {
              this.updateLocationStatus('warning', 'Gagal memverifikasi lokasi')
              reject(error)
            }
          },
          error => {
            let message
            switch (error.code) {
              case error.PERMISSION_DENIED:
                message =
                  'Izin lokasi ditolak. Silakan aktifkan izin di browser Anda.'
                break
              case error.POSITION_UNAVAILABLE:
                message = 'Informasi lokasi tidak tersedia'
                break
              case error.TIMEOUT:
                message = 'Waktu permintaan lokasi habis'
                break
              default:
                message = 'Kesalahan tidak diketahui'
            }
            this.updateLocationStatus('warning', message)
            reject(error)
          },
          {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
          }
        )
      })
    } catch (error) {
      console.error('Lokasi Error:', error)
      this.updateLocationStatus('warning', 'Gagal mendapatkan lokasi')
      return false
    }
  }

  /**
   * Validate location with server
   */
  async validateLocation (position) {
    try {
      // Kasir bisa bypass validasi lokasi
      if (this.accessType === 'kasir') {
        console.log('🔓 Location validation bypassed for kasir')
        return true
      }

      console.log('📍 Validating location with server...')
      console.log('Position data:', {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy
      })

      const payload = {
        outletId: this.outletId,
        tableId: this.tableId,
        brand: this.brand,
        name: 'location_check',
        passcode: 'temp_check',
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        verifyLocation: true,
        locationCheckOnly: true,
        accessType: this.accessType
      }

      // Tambahkan kasirId jika ada
      if (this.accessType === 'kasir' && this.kasirId) {
        payload.kasirId = this.kasirId
      }

      console.log('📤 Location validation payload:', payload)

      const response = await $.ajax({
        type: 'POST',
        url: `${window.location.origin}/order/session`,
        data: JSON.stringify(payload),
        contentType: 'application/json'
      })

      console.log('📥 Location validation response:', response)

      if (response.success) {
        console.log('✅ Location verified successfully')
      } else {
        console.warn('⚠️ Location verification failed:', response.message)
      }

      return response.success
    } catch (error) {
      console.error('❌ Location validation error:', error)

      // Handle specific error code 006 (no active session)
      if (error.responseJSON?.code === '006') {
        console.log(
          'ℹ️ No active session found, but location check can proceed'
        )
        return true
      }

      throw error
    }
  }

  /**
   * Create new session
   */
  async createSession () {
    console.group('🆕 Session Creation')
    console.log('Access Type:', this.accessType)

    try {
      this.showLoading(true)

      // 1. Verifikasi lokasi berdasarkan access type
      if (!this.locationVerified && !this.bypassGeolocation) {
        console.log('📍 Verifying location before session creation')
        this.updateLocationStatus('info', 'Memeriksa lokasi Anda...', true)

        if (!navigator.geolocation) {
          this.updateLocationStatus(
            'warning',
            'Geolocation tidak didukung browser ini'
          )
          this.showLoading(false)
          console.groupEnd()
          return
        }

        try {
          const position = await this.getCurrentPosition()
          const locationValid = await this.validateLocation(position)

          if (!locationValid) {
            this.updateLocationStatus(
              'danger',
              'Lokasi Anda terlalu jauh dari outlet. Harap datang ke outlet untuk memesan.'
            )
            this.showLoading(false)
            console.groupEnd()
            return
          }

          this.locationVerified = true
          this.updateLocationStatus('success', 'Lokasi terverifikasi ✓')
        } catch (locationError) {
          this.updateLocationStatus(
            'warning',
            'Gagal memverifikasi lokasi: ' + locationError.message
          )
          console.error('❌ Location validation error:', locationError)
          this.showLoading(false)
          console.groupEnd()
          return
        }
      } else if (this.bypassGeolocation) {
        console.log('🔓 Location verification bypassed for:', this.accessType)
        this.locationVerified = true
        this.updateLocationStatus('success', 'Verifikasi lokasi dilewati ✓')
      }

      // 2. Validasi input
      const customerName = $('#customer-name').val()
      const passcode = $('#passcode').val() || ''

      if (!customerName || customerName.length < this.config.minNameLength) {
        this.showError(
          'Nama terlalu pendek',
          `Nama pelanggan minimal ${this.config.minNameLength} karakter`
        )
        this.showLoading(false)
        console.groupEnd()
        return
      }

      // 3. Dapatkan posisi untuk dikirim ke server
      let position
      try {
        if (this.bypassGeolocation) {
          position = {
            coords: { latitude: 0, longitude: 0, accuracy: 0 }
          }
        } else {
          position = await this.getCurrentPosition()
        }
      } catch (error) {
        if (this.locationVerified) {
          position = {
            coords: { latitude: 0, longitude: 0, accuracy: 0 }
          }
          console.warn(
            '⚠️ Using default coordinates since location is already verified'
          )
        } else {
          this.showError('Gagal mendapatkan lokasi', error.message)
          this.showLoading(false)
          console.groupEnd()
          return
        }
      }

      // 4. Siapkan payload
      const sessionPayload = {
        outletId: this.outletId,
        tableId: this.tableId,
        brand: this.brand,
        name: customerName,
        passcode: passcode,
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        verifyLocation: false,
        accuracy: position.coords.accuracy || null,
        accessType: this.accessType
      }

      // Tambahkan kasirId jika access type kasir
      if (this.accessType === 'kasir' && this.kasirId) {
        sessionPayload.kasirId = this.kasirId
      }

      console.log('📤 Sending session creation request')
      console.log('Payload:', {
        ...sessionPayload,
        latitude: sessionPayload.latitude,
        longitude: sessionPayload.longitude
      })

      // 5. Kirim request ke server
      const response = await $.ajax({
        type: 'POST',
        url: `${window.location.origin}/order/session`,
        data: JSON.stringify(sessionPayload),
        contentType: 'application/json'
      })

      console.log('📥 Create session response:', response)

      // 6. Handle response
      if (response.success) {
        this.startSession(response.data)
        this.showSuccess('Sesi berhasil dibuat')

        // Log informasi access type
        if (response.data.access_info) {
          console.log(
            '🔐 Session created with access info:',
            response.data.access_info
          )
        }
      } else {
        this.showError(
          'Gagal membuat sesi',
          response.message || 'Terjadi kesalahan saat membuat sesi'
        )
      }
    } catch (error) {
      this.handleError('Gagal membuat sesi', error)
    } finally {
      this.showLoading(false)
      console.groupEnd()
    }
  }

  /**
   * Resume existing session
   */
  async resumeSession () {
    try {
      this.showLoading(true)

      // PERBAIKAN: Tidak perlu cek lokasi untuk resume session
      const payload = {
        outletId: this.outletId,
        tableId: this.tableId,
        brand: this.brand,
        passcode: $('#resume-passcode').val() || '', // Passcode bisa kosong
        name: 'temp' // Nilai sementara, akan diganti dengan data sesi yang benar dari response
      }

      // Log payload untuk debugging
      console.log('Resume Session Payload:', payload)

      const response = await $.ajax({
        type: 'POST',
        url: `${window.location.origin}/order/session`,
        data: JSON.stringify(payload),
        contentType: 'application/json'
      })

      if (response.success) {
        // Langsung mulai sesi tanpa reload
        this.startSession(response.data)
        this.showSuccess('Sesi berhasil dilanjutkan')
      } else {
        this.showError('Passcode tidak valid')
      }
    } catch (error) {
      console.error('Session resume error:', error)

      let errorMessage = 'Gagal melanjutkan sesi'
      if (error.responseJSON && error.responseJSON.message) {
        errorMessage = error.responseJSON.message
      } else if (error.status === 400) {
        errorMessage = 'Format data tidak valid. Pastikan passcode benar.'
      }

      this.showError(errorMessage)
      this.handleError('Gagal melanjutkan sesi', error)
    } finally {
      this.showLoading(false)
    }
  }

  /**
   * Start session and initialize timers
   */
  startSession (sessionData) {
    console.group('🚀 Starting Session')
    console.log('Session Data:', sessionData)
    console.log('Access Type:', this.accessType)

    try {
      // Validasi data sesi
      if (!sessionData) {
        console.error('❌ Invalid session data: data is null or undefined')
        throw new Error('Data sesi tidak tersedia')
      }

      // Extract session information
      let session = null
      let sessionId = null
      let tableName = this.tableId
      let expireTime = null
      let createdAt = null
      let statusValue = 0

      // Handle different data formats
      if (sessionData.session) {
        session = sessionData.session
        sessionId = session.id
        statusValue = session.status
        expireTime = session.expire_at
        createdAt = session.created_at
      } else if (sessionData.id) {
        session = sessionData
        sessionId = sessionData.id
        statusValue = sessionData.status
        expireTime = sessionData.expire_at
        createdAt = sessionData.created_at
      } else if (sessionData.session_id) {
        sessionId = sessionData.session_id
        expireTime = sessionData.expire_at || sessionData.timing?.expire_at
        createdAt = sessionData.created_at || sessionData.timing?.created_at
        statusValue = sessionData.status || 0
      }

      // Handle table info
      if (sessionData.table) {
        tableName =
          sessionData.table.number ||
          sessionData.table.table_number ||
          this.tableId
      }

      // Validate minimal required data
      if (!sessionId) {
        console.error('❌ No session ID found in data:', sessionData)
        throw new Error('ID sesi tidak ditemukan')
      }

      // Activate session
      this.sessionActive = true

      // Update window state
      window.orderManagerState = window.orderManagerState || {}
      window.orderManagerState.sessionActive = true
      window.orderManagerState.lastSessionId = sessionId
      window.orderManagerState.accessType = this.accessType
      window.orderManagerState.kasirId = this.kasirId

      console.log('🔧 Element States BEFORE:', {
        'session-page': !$('#session-page').is('[hidden]'),
        'session-creation': !$('#session-creation').is('[hidden]'),
        'resume-session': !$('#resume-session').is('[hidden]'),
        'active-session': !$('#active-session').is('[hidden]'),
        'order-page': !$('#order-page').is('[hidden]')
      })

      // Update UI
      $('#session-creation, #resume-session').attr('hidden', true)
      $('#active-session').removeAttr('hidden')
      $('#order-page').removeAttr('hidden')

      // Update session information
      const customerName =
        session?.name || sessionData.customer?.name || sessionData.name || '-'
      $('#active-customer').text(customerName)
      $('#active-table').text(tableName)

      // Update access type info jika ada
      if (sessionData.access_info || this.accessType === 'kasir') {
        this.updateActiveSessionAccessInfo(sessionData.access_info)
      }

      // Format creation time
      createdAt = createdAt || new Date().toISOString()
      $('#session-start').text(this.formatDateTime(createdAt))

      // Update status
      this.updateSessionStatus(statusValue || 0)

      // Start timers
      if (!expireTime) {
        expireTime = new Date(Date.now() + this.config.sessionDuration)
      } else if (typeof expireTime === 'string') {
        expireTime = new Date(expireTime)
      }

      this.startSessionTimer(expireTime)
      this.startSessionMonitoring()
      this.initializeAutoExtend()

      console.log('🔧 Element States AFTER:', {
        'session-page': !$('#session-page').is('[hidden]'),
        'session-creation': !$('#session-creation').is('[hidden]'),
        'resume-session': !$('#resume-session').is('[hidden]'),
        'active-session': !$('#active-session').is('[hidden]'),
        'order-page': !$('#order-page').is('[hidden]')
      })

      // Dispatch session activated event
      this.dispatchSessionActivatedEvent({
        sessionId: sessionId,
        customerName: customerName,
        tableName: tableName,
        accessType: this.accessType,
        kasirId: this.kasirId
      })

      console.log('✅ Session started successfully')
    } catch (error) {
      console.error('❌ Error starting session:', error)
      this.sessionActive = false
      this.showError('Gagal memulai sesi: ' + error.message)
    }

    console.groupEnd()
  }

  updateActiveSessionAccessInfo (accessInfo) {
    const activeSessionCard = $('#active-session')

    // Cari atau buat bagian access info
    let accessInfoSection = activeSessionCard.find('.access-info-section')

    if (accessInfoSection.length === 0) {
      accessInfoSection = $(`
                <div class="access-info-section mb-3">
                    <div class="alert alert-sm mb-0" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="access-icon me-2"></i>
                            <div class="access-details">
                                <div class="access-type-text"></div>
                                <div class="access-additional-info small text-muted"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `)

      // Sisipkan setelah timer display
      const timerDisplay = activeSessionCard.find('.timer-display')
      if (timerDisplay.length) {
        timerDisplay.after(accessInfoSection)
      } else {
        activeSessionCard.find('.p-4').prepend(accessInfoSection)
      }
    }

    const alertDiv = accessInfoSection.find('.alert')
    const iconElement = accessInfoSection.find('.access-icon')
    const typeText = accessInfoSection.find('.access-type-text')
    const additionalInfo = accessInfoSection.find('.access-additional-info')

    if (this.accessType === 'kasir') {
      alertDiv.removeClass('alert-info alert-success').addClass('alert-warning')
      iconElement.removeClass().addClass('bi bi-person-badge access-icon me-2')
      typeText.html('<strong>Mode Kasir</strong>')

      let kasirName = ''
      if (accessInfo && accessInfo.kasir_name) {
        kasirName = accessInfo.kasir_name
      } else if (
        accessInfo &&
        accessInfo.kasir_info &&
        accessInfo.kasir_info.user_alias
      ) {
        kasirName = accessInfo.kasir_info.user_alias
      }

      const additionalText = `ID: ${this.kasirId}${
        kasirName ? ` | ${kasirName}` : ''
      }`
      additionalInfo.text(additionalText)
    } else {
      alertDiv.removeClass('alert-warning alert-success').addClass('alert-info')
      iconElement.removeClass().addClass('bi bi-globe access-icon me-2')
      typeText.html('<strong>Mode Publik</strong>')
      additionalInfo.text('Akses pelanggan umum')
    }
  }

  dispatchSessionActivatedEvent (sessionDetail = {}) {
    console.log(
      '📡 Dispatching sessionActivated event with details:',
      sessionDetail
    )

    // Update window state
    window.orderManagerState = window.orderManagerState || {}
    window.orderManagerState.sessionActive = true
    window.orderManagerState.lastSessionId = sessionDetail.sessionId
    window.orderManagerState.accessType = sessionDetail.accessType
    window.orderManagerState.kasirId = sessionDetail.kasirId

    // Pastikan order-page terlihat
    if ($('#order-page').is('[hidden]')) {
      console.log('👁️ Order page is hidden, making it visible')
      $('#order-page').removeAttr('hidden').show()
    }

    // Dispatch event with retry mechanism
    const dispatchEventWithRetry = (attempt = 1) => {
      const event = new CustomEvent('sessionActivated', {
        detail: sessionDetail
      })
      document.dispatchEvent(event)
      console.log(`📡 sessionActivated event dispatched (attempt ${attempt})`)

      // Retry if needed
      if (attempt < 3) {
        setTimeout(() => {
          if (!window.orderManagerState.eventProcessed) {
            console.log(`🔄 Retrying event dispatch (attempt ${attempt + 1})`)
            dispatchEventWithRetry(attempt + 1)
          }
        }, 300 * attempt)
      }
    }

    dispatchEventWithRetry()
    console.log('✅ sessionActivated event dispatched successfully')
  }

  /**
   * Event untuk memberitahu komponen lain bahwa sesi aktif
   */
  dispatchSessionActivatedEvent (sessionDetail = {}) {
    console.log(
      'Dispatching sessionActivated event with details:',
      sessionDetail
    )

    // Pastikan window state diperbarui sebelum event dipanggil
    window.orderManagerState = window.orderManagerState || {}
    window.orderManagerState.sessionActive = true
    window.orderManagerState.lastSessionId = sessionDetail.sessionId

    // PERBAIKAN: Pastikan order-page terlihat dengan pendekatan yang lebih tahan gangguan
    if ($('#order-page').is('[hidden]')) {
      console.log('Order page is hidden, making it visible')
      $('#order-page').removeAttr('hidden').show()
    }

    // PERBAIKAN: Coba dispatch event beberapa kali jika perlu untuk memastikan diproses
    const dispatchEventWithRetry = (attempt = 1) => {
      const event = new CustomEvent('sessionActivated', {
        detail: sessionDetail
      })

      document.dispatchEvent(event)
      console.log(`sessionActivated event dispatched (attempt ${attempt})`)

      // Opsional: Retry dispatch jika OrderManager belum merespons dalam waktu tertentu
      if (attempt < 3) {
        setTimeout(() => {
          if (!window.orderManagerState.eventProcessed) {
            console.log(`Retrying event dispatch (attempt ${attempt + 1})`)
            dispatchEventWithRetry(attempt + 1)
          }
        }, 300 * attempt) // Exponential backoff
      }
    }

    dispatchEventWithRetry()

    // Tambahan log untuk konfirmasi
    console.log('sessionActivated event dispatched successfully')
  }

  /**
   * Mendapatkan ID sesi jika ada
   */
  getSessionId () {
    // Implementasi sederhana, dapat diperluas sesuai kebutuhan
    return 'active-session'
  }

  /**
   * Start session timer
   */
  startSessionTimer (expireTime) {
    if (this.sessionTimer) {
      clearInterval(this.sessionTimer)
    }

    const updateTimer = () => {
      const now = new Date()
      const timeLeft = expireTime - now

      if (timeLeft <= 0) {
        clearInterval(this.sessionTimer)
        this.handleSessionExpiration()
        return
      }

      const minutes = Math.floor(timeLeft / 60000)
      const seconds = Math.floor((timeLeft % 60000) / 1000)

      $('#session-timer').text(
        `${minutes}:${seconds.toString().padStart(2, '0')}`
      )

      // Show warning if less than 5 minutes
      if (timeLeft <= this.config.warningThreshold) {
        $('#session-warning')
          .removeAttr('hidden')
          .find('#warning-time')
          .text(minutes)
      }
    }

    updateTimer()
    this.sessionTimer = setInterval(updateTimer, 1000)
  }

  /**
   * Start session monitoring
   */
  startSessionMonitoring () {
    setInterval(async () => {
      if (this.sessionActive) {
        try {
          const response = await $.ajax({
            type: 'GET',
            url: `${
              window.location.origin
            }/order/session?${this.params.toString()}`
          })

          if (!response.success) {
            this.handleSessionExpiration()
          }
        } catch (error) {
          console.error('Session monitoring error:', error)
        }
      }
    }, 30000)
  }

  /**
   * Extend session
   */
  async extendSession () {
    if (!this.sessionActive) return

    try {
      const response = await $.ajax({
        type: 'GET',
        url: `${window.location.origin}/order/session?${this.params.toString()}`
      })

      if (response.success && response.data.session) {
        // Perbarui timer sesi dengan waktu kedaluwarsa baru
        this.startSessionTimer(new Date(response.data.session.expire_at))

        // Sembunyikan peringatan sesi
        $('#session-warning').attr('hidden', true)
      }
    } catch (error) {
      console.error('Session extension error:', error)
    }
  }

  /**
   * Handle session expiration
   */
  handleSessionExpiration () {
    console.log('Session expired, cleaning up...')

    // Reset status sesi
    this.sessionActive = false
    clearInterval(this.sessionTimer)
    clearInterval(this.autoExtendTimer)

    // PERBAIKAN: Dispatch event session expired
    const event = new CustomEvent('sessionExpired')
    document.dispatchEvent(event)

    // PERBAIKAN: Tampilkan pesan kedaluwarsa dengan animasi yang lebih menarik
    Swal.fire({
      title: 'Sesi Berakhir',
      text: 'Sesi Anda telah berakhir. Halaman akan dimuat ulang.',
      icon: 'warning',
      confirmButtonText: 'OK',
      showClass: {
        popup: 'animate__animated animate__fadeInDown'
      },
      hideClass: {
        popup: 'animate__animated animate__fadeOutUp'
      }
    }).then(() => {
      // PERBAIKAN: Sebelum reload, reset state UI dengan animasi
      this.resetUIState()

      // Gunakan setTimeout agar animasi UI sempat terlihat
      setTimeout(() => {
        window.location.reload()
      }, 500)
    })
  }

  /**
   * Reset UI ke kondisi awal
   */
  resetUIState () {
    // PERBAIKAN: Sembunyikan halaman order dan sesi aktif dengan animasi
    $('#order-page').fadeOut(500, function () {
      $(this).attr('hidden', true)
    })

    $('#active-session').slideUp(500, function () {
      $(this).attr('hidden', true)
    })

    // PERBAIKAN: Tampilkan kembali halaman sesi dan form pembuatan sesi
    $('#session-page').fadeIn(500).removeAttr('hidden')

    setTimeout(() => {
      $('#session-creation').fadeIn(500).removeAttr('hidden')
      $('#resume-session').attr('hidden', true)
    }, 300)
  }

  async checkExistingSession () {
    console.group('Checking Existing Session')
    try {
      // Cek mode receipt terlebih dahulu
      const urlParams = new URLSearchParams(window.location.search)
      const isReceiptMode = urlParams.get('receipt') === 'true'

      if (isReceiptMode) {
        console.log('Receipt mode detected, skipping session check')
        console.groupEnd()
        return true
      }

      // Sembunyikan semua tampilan terlebih dahulu
      $(
        '#session-creation, #resume-session, #active-session, #order-receipt-view'
      ).attr('hidden', true)

      // Pastikan halaman sesi terlihat
      $('#session-page').removeAttr('hidden')

      // Pastikan halaman order tersembunyi dulu
      $('#order-page').attr('hidden', true)

      // PERBAIKAN: Tambahkan parameter yang lengkap
      const response = await $.ajax({
        type: 'GET',
        url: `${window.location.origin}/order/session`,
        data: new URLSearchParams(window.location.search).toString(),
        dataType: 'json',
        // Tambahkan timeout untuk menghindari blocking UI
        timeout: 5000,
        // Tambahkan error handling yang lebih baik
        error: function (xhr, status, error) {
          // Jika status 404, itu berarti tidak ada sesi (expected)
          if (xhr.status === 404) {
            console.log('No session found (404), showing creation form')
            $('#session-creation').removeAttr('hidden')
            return false
          }
          throw new Error(`Session check failed: ${error}`)
        }
      })

      console.log('Session check response:', response)

      // PERBAIKAN: Jika tidak ada response atau response error, tampilkan form pembuatan
      if (!response || !response.success) {
        console.log('Invalid response or unsuccessful, showing creation form')
        $('#session-creation').removeAttr('hidden')
        console.groupEnd()
        return false
      }

      // PERBAIKAN: Validasi data sesi dengan lebih ketat
      if (
        !response.data ||
        (!response.data.session && !response.data.session_id)
      ) {
        console.log('No valid session data, showing creation form')
        $('#session-creation').removeAttr('hidden')
        console.groupEnd()
        return false
      }

      // Ekstrak data sesi
      const session = response.data.session || {}

      // PERBAIKAN: Periksa flag is_expired
      if (session.is_expired) {
        console.log(
          'Session is marked as expired in response, showing creation form'
        )
        $('#session-creation').removeAttr('hidden')
        console.groupEnd()
        return false
      }

      const expireTimeStr =
        session.expire_at ||
        response.data.expire_at ||
        response.data.timing?.expire_at
      if (!expireTimeStr) {
        console.warn('No expire time found in session data')
        $('#session-creation').removeAttr('hidden')
        console.groupEnd()
        return false
      }

      const expireTime = new Date(expireTimeStr)
      const currentTime = new Date()

      // Log informasi sesi untuk debugging
      console.log('Session status:', session.status)
      console.log('Expire time:', expireTime)
      console.log('Current time:', currentTime)
      console.log('Session expired:', currentTime > expireTime)

      // Cek apakah sesi sudah kedaluwarsa
      if (currentTime > expireTime) {
        console.log('Session expired by time, showing creation form')
        $('#session-creation').removeAttr('hidden')
        console.groupEnd()
        return false
      }

      // Penanganan status sesi dengan normalisasi
      const statusValue = session.status || response.data.status
      const normalizedStatus = String(statusValue || '').trim()
      console.log('Normalized session status:', normalizedStatus)

      // PERBAIKAN: Penanganan status 3 (CANCELLED)
      if (normalizedStatus === '3') {
        console.log('Session is CANCELLED (3), showing creation form')
        $('#session-creation').removeAttr('hidden')
        console.groupEnd()
        return false
      }

      // PERBAIKAN: Pastikan sesi benar-benar ada dan validasi lebih jelas
      // Jika server mengembalikan data sesi tetapi statusnya kosong/null/undefined,
      // itu berarti sesi sedang dalam proses pembuatan atau tidak valid
      if (normalizedStatus === '' && Object.keys(session).length <= 1) {
        console.log('Session data incomplete or invalid, showing creation form')
        $('#session-creation').removeAttr('hidden')
        console.groupEnd()
        return false
      }

      // Penanganan berbagai status
      if (normalizedStatus === '0' || normalizedStatus === '') {
        // Status RESERVED - langsung mulai sesi (tanpa menampilkan resume form)
        console.log('Session is RESERVED (0), starting session directly')
        this.startSession(response.data)
        console.groupEnd()
        return true
      } else if (normalizedStatus === '1') {
        // Status ORDER - tampilkan receipt
        console.log('Session is ORDERED (1), showing receipt view')
        await this.fetchAndDisplayReceipt(session.id)
        console.groupEnd()
        return true
      } else if (normalizedStatus === '2') {
        // PERBAIKAN: Status EXPIRED - tampilkan form pembuatan baru
        console.log('Session is EXPIRED (2), showing creation form')
        $('#session-creation').removeAttr('hidden')
        console.groupEnd()
        return false
      } else {
        // Status lain - tampilkan active session
        console.log('Session has other status, showing active session')
        this.startSession(response.data)
        console.groupEnd()
        return true
      }
    } catch (error) {
      console.error('Session check error:', error)

      // PERBAIKAN: Selalu tampilkan form pembuatan jika terjadi error
      $('#session-creation').removeAttr('hidden')

      // Handle specific error status
      if (error.status === 410) {
        console.log('Session expired (410 Gone), showing creation form')
        if (error.responseJSON && error.responseJSON.session_id) {
          console.log('Expired session ID:', error.responseJSON.session_id)
        }
      } else if (error.status !== 404) {
        this.showError('Gagal memeriksa status sesi')
      } else {
        console.log('No session found (404 is expected for new tables)')
      }

      console.groupEnd()
      return false
    }
  }
  /**
   * Validate session input
   */
  validateSessionInput () {
    const customerName = $('#customer-name').val()

    if (!customerName || customerName.length < this.config.minNameLength) {
      this.showError(
        `Nama pelanggan minimal ${this.config.minNameLength} karakter`
      )
      return false
    }

    return true
  }

  /**
   * Validate resume session input
   */
  validateResumeInput () {
    const passcode = $('#resume-passcode').val()

    return true
  }

  /**
   * Update session status UI
   */
  updateSessionStatus (status) {
    const statusMap = {
      0: { class: 'bg-warning', text: 'Dipesan' },
      1: { class: 'bg-success', text: 'Diproses' }
    }

    const statusInfo = statusMap[status] || {
      class: 'bg-secondary',
      text: status
    }

    $('#session-status')
      .removeClass()
      .addClass(`badge ${statusInfo.class}`)
      .text(statusInfo.text)
  }
  /**
   * Update location status UI
   */
  updateLocationStatus (type, message, showProgress = false) {
    const element = $('#location-verification')
    element
      .removeClass('alert-warning alert-danger alert-success alert-info')
      .addClass(`alert alert-${type}`)
      .removeAttr('hidden')

    $('#location-status').text(message)
    $('#location-progress').attr('hidden', !showProgress)
  }

  /**
   * Helper method to get current position
   */
  getCurrentPosition () {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) {
        reject(new Error('Browser tidak mendukung geolokasi'))
        return
      }

      this.updateLocationStatus('info', 'Mendapatkan lokasi...', true)

      const timeoutId = setTimeout(() => {
        reject(new Error('Timeout mendapatkan lokasi, silakan coba lagi'))
      }, 15000)

      navigator.geolocation.getCurrentPosition(
        position => {
          clearTimeout(timeoutId)
          console.log('📍 Position retrieved successfully:', {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy
          })
          resolve(position)
        },
        error => {
          clearTimeout(timeoutId)
          reject(error)
        },
        {
          enableHighAccuracy: true,
          timeout: 10000,
          maximumAge: 0
        }
      )
    })
  }

  /**
   * Show loading overlay
   */
  showLoading (show = true) {
    $('#loading-overlay').attr('hidden', !show)
  }

  /**
   * Show error using SweetAlert2
   */
  showError (title, message = null) {
    const errorTitle = title || 'Error'
    const errorMessage = message || title

    Swal.fire({
      title: errorTitle,
      text: errorMessage,
      icon: 'error',
      confirmButtonText: 'OK',
      confirmButtonColor: '#3085d6',
      showClass: {
        popup: 'animate__animated animate__fadeInDown'
      },
      hideClass: {
        popup: 'animate__animated animate__fadeOutUp'
      }
    })
  }

  /**
   * Show success message using SweetAlert2
   */
  showSuccess (message) {
    Swal.fire({
      title: 'Sukses',
      text: message,
      icon: 'success',
      timer: 1500,
      showConfirmButton: false
    })
  }

  /**
   * Handle errors
   */
  handleError (title, error) {
    console.group('Error Handler')
    console.error(`${title}:`, error)

    let errorMessage = ''

    // Extract error details based on error type
    if (error.responseJSON && error.responseJSON.message) {
      errorMessage = error.responseJSON.message
      console.log('Error from response JSON:', errorMessage)
    } else if (error.responseText) {
      try {
        const parsedError = JSON.parse(error.responseText)
        errorMessage = parsedError.message || error.responseText
        console.log('Error from parsed response text:', errorMessage)
      } catch (e) {
        errorMessage = error.responseText
        console.log('Error from raw response text:', errorMessage)
      }
    } else if (error.message) {
      errorMessage = error.message
      console.log('Error from error object:', errorMessage)
    } else {
      errorMessage = 'Terjadi kesalahan yang tidak diketahui'
      console.log('Unknown error type')
    }

    // Show user-friendly error
    this.showError(title, errorMessage)
    console.groupEnd()
  }

  /**
   * Helper untuk mendapatkan parameter URL
   */
  getQueryParam (name) {
    const params = new URLSearchParams(window.location.search)
    return params.get(name)
  }

  formatDateTime (dateString) {
    try {
      if (!dateString) return '-'

      const date = new Date(dateString)
      if (isNaN(date.getTime())) {
        return dateString
      }

      return date.toLocaleString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    } catch (error) {
      console.warn('⚠️ Error formatting date:', error)
      return dateString || '-'
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  console.log('🏁 DOM loaded, initializing SessionManager')

  try {
    // Check if in receipt mode first
    const urlParams = new URLSearchParams(window.location.search)
    const isReceiptMode = urlParams.get('receipt') === 'true'

    if (isReceiptMode) {
      console.log(
        '📄 Receipt mode detected, skipping SessionManager initialization'
      )
      return
    }

    // Create and initialize SessionManager
    const sessionManager = new SessionManager()

    // Initialize event listeners
    sessionManager.initializeEventListeners()

    // Hide order page initially
    $('#order-page').attr('hidden', true)

    // Initialize SessionManager
    sessionManager.initialize()
  } catch (error) {
    console.error('❌ Error instantiating SessionManager:', error)
    alert('Terjadi kesalahan saat memuat aplikasi. Silahkan refresh halaman.')
  }
})
