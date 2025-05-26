window.orderManagerState = window.orderManagerState || {
  sessionActive: false,
  lastSessionId: null
}

const OrderManager = {
  state: {
    cart: {
      items: new Map(),
      regularItems: [],
      packageItems: [],
      summary: {
        subtotal: 0,
        tax: 0,
        total: 0,
        regularCount: 0,
        packageCount: 0
      },
      promo: {
        code: null,
        type: null,
        discount: 0,
        message: null,
        isValid: false,
        details: null
      }
    },
    ui: {
      loading: false,
      cartModalVisible: false,
      activeNotifications: [],
      currentView: 'cart',
      promoLoading: false
    },
    animations: {
      duration: 200,
      easing: 'swing'
    },
    currentProduct: null,
    quantity: 1,
    lastUpdate: null
  },

  // Configuration
  config: {
    endpoints: {
      cart: '/order/cart',
      cartCount: '/order/countCart',
      addToCart: '/order/add',
      removeFromCart: '/order/removeCartItem',
      updateQuantity: '/order/updateQuantity',
      processOrder: '/order/doneOrder',
      session: '/order/session',
      validatePromo: '/order/validatePromoCode',
      applyPromoToOrder: '/order/applyPromoToOrder'
    },
    selectors: {
      cartModal: '#cart-modal',
      cartButton: '#show-cart',
      cartCountBadge: '#count-cart',
      cartItemsContainer: '.cart-items',
      regularItemsContainer: '#regular-products .cart-items',
      packageItemsContainer: '#package-products .cart-items',
      checkoutButton: '#checkout-btn',
      emptyCartMessage: '#empty-cart',
      loadingOverlay: '.loading-overlay',
      quantityControls: '.quantity-control',
      cartSummary: '.cart-summary',
      modalTitle: '#modal-product-name',
      modalImage: '#modal-product-image',
      modalPrice: '#modal-product-price',
      modalStock: '#modal-product-stock',
      modalDescription: '#modal-product-description',
      modalQuantity: '.product-qty',
      modalSubtotal: '#product-subtotal',
      modalNotes: '#product-note',
      promoInput: '#promo-code-input',
      promoApplyBtn: '#apply-promo-btn',
      promoSection: '.promo-section',
      promoInfo: '.promo-info',
      discountAmount: '.discount-amount',
      totalAmountAfterDiscount: '.total-after-discount'
    },
    taxRate: 0.1,
    debounceDelay: 300,
    animations: {
      duration: 200,
      easing: 'ease'
    }
  },

  init () {
    console.group('OrderManager Initialization')
    try {
      // Cek mode receipt terlebih dahulu
      const urlParams = new URLSearchParams(window.location.search)
      const isReceiptMode = urlParams.get('receipt') === 'true'

      if (isReceiptMode) {
        console.log('Receipt mode detected, handling receipt first')

        // Handle receipt dan skip inisialisasi lain
        if (this.checkForReceipt()) {
          console.log('Receipt mode handled successfully')
          this.state.initialized = true
          console.groupEnd()
          return true
        }
      }

      // PERBAIKAN KRITIS: Cek status sesi menggunakan kombinasi DOM dan window state
      const activeSessionVisibleDOM =
        $('#active-session').length > 0 && !$('#active-session').is('[hidden]')
      // Gunakan window state jika tersedia atau default ke DOM check
      const sessionActive =
        window.orderManagerState?.sessionActive || activeSessionVisibleDOM

      console.log('OrderManager init - session state check:', {
        activeSessionExists: $('#active-session').length > 0,
        activeSessionVisibleDOM: activeSessionVisibleDOM,
        windowSessionActive: window.orderManagerState?.sessionActive,
        finalSessionActive: sessionActive
      })

      // PERBAIKAN KRITIS: Perbarui status order page berdasarkan status sesi yang sudah disinkronkan
      const orderPageVisible = !$('#order-page').is('[hidden]')
      console.log('OrderManager init - order page visible:', orderPageVisible)

      // PERBAIKAN KRITIS: Periksa apakah ada tampilan receipt
      const receiptVisible =
        $('#order-receipt-view').length > 0 &&
        !$('#order-receipt-view').is('[hidden]')
      console.log('OrderManager init - receipt visible:', receiptVisible)

      // Jika receipt tampil, jangan lakukan inisialisasi lebih lanjut
      if (receiptVisible) {
        console.log('Receipt is visible, skipping OrderManager initialization')
        this.state.initialized = true
        return true
      }

      this.state.sessionActive = sessionActive

      // PERBAIKAN KRITIS: Jika sesi aktif, pastikan order page terlihat
      if (sessionActive && orderPageVisible === false) {
        console.log(
          'Active session detected but order page is hidden, making it visible'
        )
        $('#order-page').removeAttr('hidden')
      }
      // PERBAIKAN KRITIS: JANGAN sembunyikan order page jika window state menunjukkan sesi aktif
      else if (
        !sessionActive &&
        !window.orderManagerState?.sessionActive &&
        orderPageVisible
      ) {
        console.log('No active session but order page is visible, hiding it')
        $('#order-page').attr('hidden', true)
      }

      // Cek apakah ini mode receipt
      if (this.checkForReceipt()) {
        console.log('Receipt mode detected, skipping other initializations')
        this.state.initialized = true
        console.groupEnd()
        return true
      }

      // PERBAIKAN: Perbarui global state
      window.orderManagerState = window.orderManagerState || {}
      window.orderManagerState.sessionActive = this.state.sessionActive

      document.addEventListener('sessionActivated', e => {
        console.log('OrderManager received sessionActivated event', e.detail)
        this.state.sessionActive = true
        window.orderManagerState.sessionActive = true
        window.orderManagerState.lastSessionId = e.detail?.sessionId

        // PERBAIKAN: Pastikan order page terlihat saat sesi aktif
        if ($('#order-page').is('[hidden]')) {
          console.log('Making order page visible after session activation')
          $('#order-page').removeAttr('hidden')
        }

        if (OrderManager.checkForReceipt()) {
          console.log('Receipt mode detected, skipping other initializations')
          return
        }

        // Load cart data with delay to ensure DOM is ready
        setTimeout(() => {
          if (this.state.sessionActive) {
            // PERUBAHAN: Cek flag force refresh
            const forceRefresh = window.forceCartRefresh === true

            this.loadCart(forceRefresh === false).catch(error => {
              if (error.status !== 404) {
                console.error(
                  'Failed to load cart after session activation:',
                  error
                )
              }
            })
          }
        }, 300)
      })

      $('#call-waiter').on('click', function (e) {
        e.preventDefault()
        e.stopPropagation()

        // Get current session parameters from URL
        const urlParams = new URLSearchParams(window.location.search)
        const outletId = urlParams.get('outletId')
        const tableId = urlParams.get('tableId')
        const brand = urlParams.get('brand')

        // Validate required parameters
        if (!outletId || !tableId || !brand) {
          Swal.fire({
            icon: 'error',
            title: 'Kesalahan',
            text: 'Tidak dapat memanggil pelayan. Pastikan Anda sudah memulai sesi.',
            confirmButtonText: 'OK'
          })
          return
        }

        // Show confirmation dialog
        Swal.fire({
          title: 'Panggil Pelayan',
          text: `Anda yakin ingin memanggil pelayan ke Meja ${tableId}?`,
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Ya, Panggil Pelayan',
          cancelButtonText: 'Batal',
          customClass: {
            popup: 'animate__animated animate__bounceIn',
            confirmButton: 'btn btn-primary me-2',
            cancelButton: 'btn btn-outline-secondary'
          }
        }).then(result => {
          if (result.isConfirmed) {
            // Disable button to prevent multiple clicks
            const $button = $('#call-waiter')
            $button.prop('disabled', true)
            $button.addClass('disabled')

            // Show loading state
            Swal.fire({
              title: 'Mengirim Panggilan',
              text: 'Mohon tunggu...',
              icon: 'info',
              showConfirmButton: false,
              allowOutsideClick: false,
              didOpen: () => {
                Swal.showLoading()
              }
            })

            // Make AJAX call to server
            $.ajax({
              url: `${window.location.origin}/order/callWaiter`,
              method: 'POST',
              contentType: 'application/json',
              data: JSON.stringify({
                outletId: outletId,
                tableId: tableId,
                brand: brand
              }),
              success: function (response) {
                if (response.success) {
                  // Successful waiter call
                  Swal.fire({
                    icon: 'success',
                    title: 'Pelayan Dipanggil',
                    text: 'Pelayan akan segera datang ke meja Anda.',
                    confirmButtonText: 'OK',
                    customClass: {
                      confirmButton: 'btn btn-primary'
                    }
                  })
                } else {
                  // Server returned error
                  Swal.fire({
                    icon: 'warning',
                    title: 'Gagal Memanggil Pelayan',
                    text:
                      response.message ||
                      'Terjadi kesalahan saat memanggil pelayan.',
                    confirmButtonText: 'Coba Lagi',
                    customClass: {
                      confirmButton: 'btn btn-primary'
                    }
                  })
                }
              },
              error: function (xhr, status, error) {
                // Network or server error
                console.error('Waiter call error:', error)
                Swal.fire({
                  icon: 'error',
                  title: 'Kesalahan Koneksi',
                  text: 'Tidak dapat menghubungi server. Silakan periksa koneksi internet Anda.',
                  confirmButtonText: 'OK',
                  customClass: {
                    confirmButton: 'btn btn-primary'
                  }
                })
              },
              complete: function () {
                // Re-enable button
                const $button = $('#call-waiter')
                $button.prop('disabled', false)
                $button.removeClass('disabled')
              }
            })
          }
        })
      })

      // PERBAIKAN: Bind events even if we skip loadCart
      this.bindEvents()
      this.bindCartButtonEvents()
      this.bindRemoveEvents()
      this.bindQuantityControls()

      // PERBAIKAN: Perubahan kriteria untuk mem-bypass loadCart
      if (
        (!this.state.sessionActive &&
          !window.orderManagerState?.sessionActive) ||
        receiptVisible
      ) {
        console.log(
          'Session not active or receipt visible, skipping cart loading'
        )
        this.state.initialized = true
        return true
      }

      // Only try to load cart data if session is active
      console.log('Session active, proceeding with cart loading')

      // PERBAIKAN: Kurangi delay untuk load cart
      setTimeout(() => {
        if (
          this.state.sessionActive ||
          window.orderManagerState?.sessionActive
        ) {
          this.loadCart().catch(error => {
            // Only handle non-404 errors
            if (error.status !== 404) {
              console.error('Failed to load cart:', error)
              this.handleError(error)
            } else {
              console.log(
                'Cart not found (404), this is expected for new sessions'
              )
            }
          })
        }
      }, 300)

      $('#cart-modal').on('show.bs.modal', e => {
        console.log('Cart modal is being shown')

        // Cek apakah perlu force refresh
        if (window.forceCartRefresh === true) {
          console.log('Forcing cart refresh before showing modal')

          // Hentikan modal dari terbuka dulu
          e.preventDefault()

          // Tampilkan loading
          this.showLoading(true)

          // Load cart data terlebih dahulu
          this.loadCart(false)
            .then(() => {
              // Data sudah diperbarui, tampilkan modal
              this.showLoading(false)
              window.forceCartRefresh = false
              $('#cart-modal').modal('show')
            })
            .catch(err => {
              console.error('Error refreshing cart:', err)
              this.showLoading(false)
              window.forceCartRefresh = false
              // Tetap tampilkan modal meski ada error
              $('#cart-modal').modal('show')
            })
        }
      })

      if (this.checkForReceipt()) {
        console.log('Receipt mode detected, skipping other initializations')
        this.state.initialized = true
        console.groupEnd()
        return true
      }

      this.state.initialized = true
      console.log('OrderManager initialized successfully')
      return true
    } catch (error) {
      console.error('OrderManager initialization failed:', error)
      this.handleError(error)
      return false
    } finally {
      console.groupEnd()
    }
  },

  initializeRealtimeUpdates () {
    // Gunakan polling sederhana setiap 10 detik untuk mengecek perubahan
    this.realtimeInterval = setInterval(async () => {
      if (!this.state.sessionActive || this.state.ui.loading) return

      try {
        // Ambil status order terbaru
        const params = this.getUrlParams()
        const timestamp = new Date().getTime()

        const response = await $.ajax({
          url: `${window.location.origin}/order/getOrderStatus`,
          method: 'GET',
          data: {
            ...params,
            timestamp: timestamp
          },
          dataType: 'json'
        })

        // Jika ada perubahan dari terakhir kali diketahui
        if (response.success && response.data) {
          const lastUpdated = response.data.updated_at

          // Cek apakah ada perubahan dari state yang kita simpan
          if (this.lastOrderUpdate && this.lastOrderUpdate !== lastUpdated) {
            console.log('Order updated by another user')

            // Tampilkan notifikasi non-intrusif
            this.showNotification(
              'Perubahan Terdeteksi',
              'Order telah diubah oleh pengguna lain. Klik untuk memuat ulang.',
              'info',
              () => {
                // Callback ketika notifikasi diklik: Muat ulang keranjang
                this.loadCart(false)
              }
            )
          }

          // Simpan timestamp update terbaru
          this.lastOrderUpdate = lastUpdated
        }
      } catch (error) {
        console.error('Error checking for order updates:', error)
      }
    }, 10000) // Cek setiap 10 detik
  },

  validateInitialization () {
    const requiredSelectors = [
      'cartModal',
      'cartButton',
      'cartCountBadge',
      'regularItemsContainer',
      'packageItemsContainer',
      'checkoutButton'
    ]

    const missingSelectors = requiredSelectors.filter(selector => {
      const element = document.querySelector(this.config.selectors[selector])
      if (!element) {
        console.error(`Missing required element: ${selector}`)
        return true
      }
      return false
    })

    if (missingSelectors.length > 0) {
      throw new Error(
        `Missing required elements: ${missingSelectors.join(', ')}`
      )
    }

    const urlParams = new URLSearchParams(window.location.search)
    const requiredParams = ['outletId', 'tableId', 'brand']
    const missingParams = requiredParams.filter(param => !urlParams.get(param))

    if (missingParams.length > 0) {
      throw new Error(
        `Missing required URL parameters: ${missingParams.join(', ')}`
      )
    }

    return true
  },

  bindEvents () {
    $(document).ready(() => {
      // === MODAL EVENTS ===
      $(this.config.selectors.cartModal)
        .on('show.bs.modal', () => {
          console.log('Cart modal show event triggered')
        })
        .on('shown.bs.modal', () => {
          console.log('Cart modal visible')
          this.state.ui.cartModalVisible = true
          this.updateCartUI()
          this.showPromoSuggestions()
        })
        .on('hidden.bs.modal', () => {
          console.log('Cart modal hidden')
          this.state.ui.cartModalVisible = false
        })

      // === QUANTITY CONTROLS ===
      this.bindQuantityControls()

      // === CHECKOUT BUTTON ===
      // PERBAIKAN: Hapus handler sebelumnya untuk mencegah duplikasi
      $(this.config.selectors.checkoutButton).off('click')
      $(this.config.selectors.checkoutButton).on(
        'click',
        this.handleCheckout.bind(this)
      )

      // === PROMO BADGE HANDLING ===
      if (
        this.state.cart &&
        this.state.cart.promo &&
        this.state.cart.promo.isValid
      ) {
        this.renderPromoEligibleProducts()
      }

      // PERBAIKAN: Event listener saat promo berhasil diterapkan
      $(document).off('promoApplied')
      $(document).on('promoApplied', (e, promoData) => {
        if (promoData && promoData.isValid) {
          this.renderPromoEligibleProducts()
        }
      })
    })

    // === INITIAL UPDATE ===
    this.updateCartCount()
  },

  bindCartButtonEvents () {
    console.log('Binding cart button events')

    // PERBAIKAN: Hapus semua event handler sebelumnya dengan lebih spesifik
    $(document).off('click', this.config.selectors.cartButton)

    // PERBAIKAN: Gunakan variabel untuk menyimpan handler dan mencegah duplikasi
    this._cartButtonClickHandler = e => {
      e.preventDefault()
      e.stopPropagation()
      console.log('Cart button clicked')

      // Don't proceed if already loading
      if (this.state.ui.loading) {
        console.log('Loading in progress, ignoring cart button click')
        return
      }

      // Start loading state
      this.showLoading(true)

      // Check if force refresh flag is set
      const forceRefresh = window.forceCartRefresh === true
      console.log('Force refresh cart:', forceRefresh)

      // Load cart data before showing modal
      this.loadCart(!forceRefresh) // Pass false when forceRefresh is true
        .then(() => {
          console.log('Cart loaded successfully')
          // Reset force refresh flag
          window.forceCartRefresh = false
          // Hide loading indicator
          this.showLoading(false)
          // Update empty state
          this.updateEmptyState()
          // Show cart modal
          $(this.config.selectors.cartModal).modal('show')
        })
        .catch(error => {
          console.error('Error loading cart:', error)
          // Reset force refresh flag
          window.forceCartRefresh = false
          // Hide loading indicator
          this.showLoading(false)
          // Only show error for non-404 errors
          if (error.status !== 404) {
            this.handleError(error)
          }
          // Reset cart state
          this.resetCartState()
          // Render empty cart
          this.renderCart()
          // Show cart modal anyway
          $(this.config.selectors.cartModal).modal('show')
        })
    }

    // Add new event handler with delegation - PERBAIKAN: simpan reference ke handler
    $(document).on(
      'click',
      this.config.selectors.cartButton,
      this._cartButtonClickHandler
    )

    console.log('Cart button events bound successfully')
  },

  bindRemoveEvents () {
    console.log('Binding remove events - ' + new Date().toISOString())

    // PERBAIKAN: Hapus event handlers lama dan gunakan delegasi
    $(document).off('click', '.remove-item')
    $(document).off('click', '.remove-package')

    // Bind remove item button dengan delegasi event
    $(document).on('click', '.remove-item', async e => {
      e.preventDefault()
      e.stopPropagation()

      console.log('Remove item button clicked')

      const itemId = $(e.currentTarget).data('item-id')
      console.log('Remove item triggered for ID:', itemId)

      if (!itemId) {
        console.error('Item ID tidak ditemukan.')
        return
      }

      try {
        const result = await Swal.fire({
          title: 'Hapus Item',
          text: 'Apakah Anda yakin ingin menghapus item ini?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Ya, Hapus',
          cancelButtonText: 'Batal'
        })

        if (result.isConfirmed) {
          // PERBAIKAN: Tambahkan flag untuk memaksa update UI setelah penghapusan
          window.forceCartUpdate = true

          // Panggil removeCartItem dengan bind yang benar
          const success = await this.removeCartItem.call(
            this,
            itemId,
            'regular'
          )

          if (success) {
            // PERBAIKAN: Update ringkasan setelah penghapusan
            const summary = this.calculateCartSummary()
            this.updateCartSummaryDisplay(summary)

            this.showNotification('Item berhasil dihapus', 'success')
          }
        }
      } catch (error) {
        console.error('Gagal menghapus item:', error)
        this.showNotification('Gagal menghapus item', 'error')
      }
    })

    // Bind remove package button menggunakan metode yang sama
    $(document).on('click', '.remove-package', async e => {
      e.preventDefault()
      e.stopPropagation()

      console.log('Remove package button clicked')

      const packageId = $(e.currentTarget).data('package-id')
      console.log('Remove package triggered for ID:', packageId)

      if (!packageId) {
        console.error('Package ID tidak ditemukan.')
        return
      }

      try {
        const result = await Swal.fire({
          title: 'Hapus Paket',
          text: 'Apakah Anda yakin ingin menghapus paket ini?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Ya, Hapus',
          cancelButtonText: 'Batal'
        })

        if (result.isConfirmed) {
          // PERBAIKAN: Tambahkan flag untuk memaksa update UI setelah penghapusan
          window.forceCartUpdate = true

          // Panggil removeCartItem dengan bind yang benar
          const success = await this.removeCartItem.call(
            this,
            packageId,
            'package'
          )

          if (success) {
            // PERBAIKAN: Update ringkasan setelah penghapusan
            const summary = this.calculateCartSummary()
            this.updateCartSummaryDisplay(summary)

            this.showNotification('Paket berhasil dihapus', 'success')
          }
        }
      } catch (error) {
        console.error('Gagal menghapus paket:', error)
        this.showNotification('Gagal menghapus paket', 'error')
      }
    })

    console.log('Remove events bound successfully')
  },

  bindQuantityControls () {
    console.group('🔢 Binding Cart Quantity Controls')
    // PERBAIKAN: Hapus event handler lama untuk mencegah binding ganda
    $(document).off(
      'click',
      '.cart-item .decrease-qty, .cart-item .increase-qty'
    )

    // Tambahkan handler baru dengan delegasi event
    $(document).on(
      'click',
      '.cart-item .decrease-qty, .cart-item .increase-qty',
      e => {
        e.preventDefault()
        e.stopPropagation()
        console.log('Cart quantity button clicked')

        const button = e.currentTarget
        const itemId = $(button).data('item-id')
        const action = $(button).hasClass('decrease-qty')
          ? 'decrease'
          : 'increase'

        if (itemId) {
          // PERBAIKAN: Gunakan Try-Catch untuk menangani error
          try {
            this.handleQuantityChange.call(this, itemId, action)
          } catch (error) {
            console.error('Error handling cart quantity change:', error)
            this.showNotification('Gagal mengubah kuantitas', 'error')
          }
        } else {
          console.error('Item ID missing from cart quantity button')
        }
      }
    )

    console.log('Cart quantity controls bound successfully')
    console.groupEnd()
  },

  bindPromoEvents () {
    console.log('Binding promo events')

    // Hapus event handler sebelumnya
    $(document).off('click', this.config.selectors.promoApplyBtn)
    $(document).off('keypress', this.config.selectors.promoInput)

    // Bind click event pada tombol apply promo
    $(document).on('click', this.config.selectors.promoApplyBtn, e => {
      e.preventDefault()

      // Ambil kode promo dari input
      const promoCode = $(this.config.selectors.promoInput).val()
      if (!promoCode || promoCode.trim() === '') {
        this.showNotification('Please enter a promo code', 'warning')
        return
      }

      // Validasi promo
      this.validatePromoCode(promoCode)
    })

    // Bind enter key pada input promo
    $(document).on('keypress', this.config.selectors.promoInput, e => {
      if (e.which === 13) {
        e.preventDefault()

        // Trigger click event pada tombol apply promo
        $(this.config.selectors.promoApplyBtn).click()
      }
    })

    // Bind event untuk tombol remove promo (delegasi event)
    $(document).on('click', '.remove-promo', e => {
      e.preventDefault()
      console.log('Remove promo button clicked')
      this.resetPromo()
    })

    console.log('Promo events bound successfully')
  },

  updateCartSummaryDisplay (summary, withAnimation = false) {
    console.group(
      '📊 [UPDATE SUMMARY DISPLAY] Memperbarui tampilan order summary'
    )
    console.log('Raw Summary Data:', summary)

    try {
      // Validate summary
      if (!summary) {
        console.warn('Summary data is undefined or null')
        summary = {
          regularCount: 0,
          packageCount: 0,
          regularSubtotal: 0,
          packageSubtotal: 0,
          subtotal: 0,
          discount: 0,
          bundleBogoDiscount: 0,
          tax: 0,
          total: 0
        }
      }

      // PERBAIKAN: Update with animation if requested
      if (withAnimation) {
        // Regular count with animation
        $('.regular-count').each(function () {
          const $this = $(this)
          const oldValue = parseInt($this.text()) || 0
          const newValue = summary.regularCount || 0

          if (oldValue !== newValue) {
            $this.prop('counter', oldValue).animate(
              { counter: newValue },
              {
                duration: 500,
                easing: 'swing',
                step: function (now) {
                  $this.text(Math.ceil(now))
                }
              }
            )
          }
        })

        // Package count with animation
        $('.package-count').each(function () {
          const $this = $(this)
          const oldValue = parseInt($this.text()) || 0
          const newValue = summary.packageCount || 0

          if (oldValue !== newValue) {
            $this.prop('counter', oldValue).animate(
              { counter: newValue },
              {
                duration: 500,
                easing: 'swing',
                step: function (now) {
                  $this.text(Math.ceil(now))
                }
              }
            )
          }
        })

        // Price animations - highlight changes
        const priceElements = [
          { selector: '.regular-amount', value: summary.regularSubtotal || 0 },
          { selector: '.package-amount', value: summary.packageSubtotal || 0 },
          { selector: '.subtotal-amount', value: summary.subtotal || 0 },
          { selector: '.discount-amount', value: summary.discount || 0 },
          {
            selector: '.bundle-bogo-discount-amount',
            value: summary.bundleBogoDiscount || 0
          }, // New element
          { selector: '.tax-amount', value: summary.tax || 0 },
          { selector: '.total-amount', value: summary.total || 0 }
        ]

        priceElements.forEach(item => {
          const $el = $(item.selector)
          const oldText = $el.text()
          const newText = this.formatCurrency(item.value)

          if (oldText !== newText) {
            $el.fadeOut(200, function () {
              $(this)
                .text(newText)
                .addClass('highlight-change')
                .fadeIn(200, function () {
                  setTimeout(
                    () => $(this).removeClass('highlight-change'),
                    1000
                  )
                })
            })
          }
        })
      } else {
        // Regular update without animation
        $('.regular-count').text(summary.regularCount || 0)
        $('.package-count').text(summary.packageCount || 0)
        $('.regular-amount').text(
          this.formatCurrency(summary.regularSubtotal || 0)
        )
        $('.package-amount').text(
          this.formatCurrency(summary.packageSubtotal || 0)
        )
        $('.subtotal-amount').text(this.formatCurrency(summary.subtotal || 0))

        // PERBAIKAN: Separate regular discount from bundle/BOGO value
        $('.discount-amount').text(this.formatCurrency(summary.discount || 0))
        $('.bundle-bogo-discount-amount').text(
          this.formatCurrency(summary.bundleBogoDiscount || 0)
        )

        $('.tax-amount').text(this.formatCurrency(summary.tax || 0))
        $('.total-amount').text(this.formatCurrency(summary.total || 0))

        // IMPROVEMENT: Show/hide discount rows based on their values
        if (summary.discount > 0) {
          $('.discount-row').show()
        } else {
          $('.discount-row').hide()
        }

        // Show/hide bundle/BOGO discount row
        if (summary.bundleBogoDiscount > 0) {
          $('.bundle-bogo-discount-row').show()
        } else {
          $('.bundle-bogo-discount-row').hide()
        }
      }

      console.log('Summary display updated with values:', {
        regularCount: summary.regularCount,
        packageCount: summary.packageCount,
        regularSubtotal: this.formatCurrency(summary.regularSubtotal),
        packageSubtotal: this.formatCurrency(summary.packageSubtotal),
        subtotal: this.formatCurrency(summary.subtotal),
        discount: this.formatCurrency(summary.discount),
        bundleBogoDiscount: this.formatCurrency(summary.bundleBogoDiscount),
        tax: this.formatCurrency(summary.tax),
        total: this.formatCurrency(summary.total)
      })
    } catch (error) {
      console.error('Error updating summary display:', error)
    } finally {
      console.groupEnd()
    }
  },

  calculateCartSummary (processedItems) {
    console.group(
      '📊 [CALCULATE SUMMARY] Calculating cart summary with improved logic'
    )

    try {
      // Initialize summary object dengan struktur yang konsisten
      const summary = {
        regularCount: 0,
        packageCount: 0,
        regularSubtotal: 0,
        packageSubtotal: 0,
        subtotal: 0,
        discount: 0, // Regular discount (percentage/nominal) - MENGURANGI total
        bundleBogoDiscount: 0, // Nilai produk gratis (informational only) - TIDAK mengurangi total
        tax: 0,
        total: 0
      }

      // Verify state structure
      if (!this.state.cart) {
        console.warn('Cart state is undefined, returning empty summary')
        return summary
      }

      // Calculate regular items
      if (Array.isArray(this.state.cart.regularItems)) {
        summary.regularCount = this.state.cart.regularItems.length

        this.state.cart.regularItems.forEach(item => {
          if (
            item &&
            typeof item.price !== 'undefined' &&
            typeof item.quantity !== 'undefined'
          ) {
            const price = parseFloat(item.price || 0)
            const quantity = parseInt(item.quantity || 0)
            const itemSubtotal = price * quantity

            // PERBAIKAN KRITIS: Deteksi item promo dengan logika yang konsisten
            const isPromoFreeItem = this.isPromoFreeItem(item)

            if (isPromoFreeItem) {
              // Item gratis dari promo (bundling/BOGO) - hanya untuk display, tidak masuk subtotal
              summary.bundleBogoDiscount += this.getOriginalItemValue(
                item,
                itemSubtotal
              )
              console.log(
                `Free promo item: ${
                  item.product_name
                }, original value: ${this.getOriginalItemValue(
                  item,
                  itemSubtotal
                )}`
              )
            } else {
              // Item reguler atau item dengan regular discount - masuk subtotal
              summary.regularSubtotal += itemSubtotal
              console.log(
                `Regular item: ${item.product_name}, subtotal: ${itemSubtotal}`
              )
            }

            item.subtotal = itemSubtotal
          }
        })
      }

      // Calculate package items dengan logika yang sama
      if (Array.isArray(this.state.cart.packageItems)) {
        summary.packageCount = this.state.cart.packageItems.length

        this.state.cart.packageItems.forEach(pkg => {
          if (pkg) {
            let packageTotal = parseFloat(pkg.total || pkg.base_price || 0)

            // PERBAIKAN: Cek apakah package adalah promo gratis
            const isPromoPackage = this.isPromoFreePackage(pkg)

            if (isPromoPackage) {
              summary.bundleBogoDiscount += packageTotal
              console.log(
                `Free promo package: ${pkg.name}, value: ${packageTotal}`
              )
            } else {
              summary.packageSubtotal += packageTotal
              console.log(
                `Regular package: ${pkg.name}, subtotal: ${packageTotal}`
              )
            }
          }
        })
      }

      // Calculate subtotal (semua item yang masuk ke billing)
      summary.subtotal = summary.regularSubtotal + summary.packageSubtotal

      // PERBAIKAN KRITIS: Apply regular discount (percentage/nominal) saja
      if (this.state.cart.promo && this.state.cart.promo.isValid) {
        const promoType = this.state.cart.promo.type || 'unknown'
        const discountAmount = parseFloat(this.state.cart.promo.discount) || 0

        console.log(`Processing promo: ${promoType}, amount: ${discountAmount}`)

        // HANYA regular discount (percentage/nominal) yang mengurangi subtotal
        if (promoType === 'percentage' || promoType === 'nominal') {
          summary.discount = discountAmount
          console.log(`Applied regular discount: ${summary.discount}`)
        }
        // Bundling/BOGO sudah dihandle di level item (bundleBogoDiscount)
      }

      // PERBAIKAN KRITIS: Tax calculation yang konsisten
      // Tax dihitung dari (subtotal - regular discount), bundleBogoDiscount tidak mempengaruhi
      const taxableAmount = Math.max(0, summary.subtotal - summary.discount)
      summary.tax = taxableAmount * 0.1 // 10% tax
      summary.total = taxableAmount + summary.tax

      console.log('Final summary calculation:', {
        subtotal: summary.subtotal,
        regularDiscount: summary.discount,
        bundleBogoDiscount: summary.bundleBogoDiscount,
        taxableAmount: taxableAmount,
        tax: summary.tax,
        total: summary.total
      })

      return summary
    } catch (error) {
      console.error('Error calculating cart summary:', error)
      return this.getEmptyCartSummary()
    } finally {
      console.groupEnd()
    }
  },

  isPromoFreeItem (item) {
    // Item gratis jika:
    // 1. Harga 0 DAN ada penanda promo
    // 2. Atau ada flag is_promo_item dengan tipe bundling/bogo
    return (
      (parseFloat(item.price || 0) === 0 &&
        (item.is_promo_item === 1 ||
          item.promo_type === 'bundling' ||
          item.promo_type === 'bogo' ||
          (item.notes && item.notes.toLowerCase().includes('gratis')))) ||
      (item.is_promo_item === 1 &&
        (item.promo_type === 'bundling' || item.promo_type === 'bogo'))
    )
  },

  isPromoFreePackage (pkg) {
    return (
      pkg.is_promo_item === 1 &&
      (pkg.promo_type === 'bundling' || pkg.promo_type === 'bogo')
    )
  },

  getOriginalItemValue (item, fallbackValue) {
    // Coba ambil harga asli, kalau tidak ada gunakan fallback
    return parseFloat(item.original_price || fallbackValue || 0)
  },

  getEmptyCartSummary () {
    return {
      regularCount: 0,
      packageCount: 0,
      regularSubtotal: 0,
      packageSubtotal: 0,
      subtotal: 0,
      discount: 0,
      bundleBogoDiscount: 0,
      tax: 0,
      total: 0
    }
  },

  // Metode tambahan untuk menjamin konsistensi dan debugging
  recalculateCartSummary () {
    console.group('Recalculating Cart Summary with Detailed Logging')

    try {
      const summary = {
        regularSubtotal: 0,
        packageSubtotal: 0,
        regularCount: 0,
        packageCount: 0,
        subtotal: 0,
        tax: 0,
        total: 0
      }

      // Detailed logging untuk regular items
      if (Array.isArray(this.state.cart.regularItems)) {
        summary.regularCount = this.state.cart.regularItems.length
        summary.regularSubtotal = this.state.cart.regularItems.reduce(
          (total, item) => {
            const itemSubtotal =
              (parseFloat(item.price) || 0) * (parseInt(item.quantity) || 0)
            console.log(
              `Regular Item Calculation: ${item.product_name}, Price: ${item.price}, Quantity: ${item.quantity}, Subtotal: ${itemSubtotal}`
            )
            return total + itemSubtotal
          },
          0
        )
      }

      // Detailed logging untuk package items
      if (Array.isArray(this.state.cart.packageItems)) {
        summary.packageCount = this.state.cart.packageItems.length
        summary.packageSubtotal = this.state.cart.packageItems.reduce(
          (total, pkg) => {
            const packageTotal = parseFloat(pkg.total) || 0
            console.log(
              `Package Calculation: ${pkg.name}, Total: ${packageTotal}`
            )
            return total + packageTotal
          },
          0
        )
      }

      // Perhitungan total dengan logging tambahan
      summary.subtotal = summary.regularSubtotal + summary.packageSubtotal
      summary.tax = summary.subtotal * this.config.taxRate
      summary.total = summary.subtotal + summary.tax

      console.log('Recalculated Summary with Detailed Breakdown:', summary)

      return summary
    } catch (error) {
      console.error('Detailed Recalculation Error:', error)
      return {
        regularSubtotal: 0,
        packageSubtotal: 0,
        regularCount: 0,
        packageCount: 0,
        subtotal: 0,
        tax: 0,
        total: 0
      }
    } finally {
      console.groupEnd()
    }
  },

  resetCartState () {
    console.log('Resetting cart state')

    // Create new empty cart state
    this.state.cart = {
      regularItems: [],
      packageItems: [],
      summary: {
        regularCount: 0,
        packageCount: 0,
        regularSubtotal: 0,
        packageSubtotal: 0,
        subtotal: 0,
        tax: 0,
        total: 0
      }
    }

    // Clear UI containers
    $(this.config.selectors.regularItemsContainer).empty()
    $(this.config.selectors.packageItemsContainer).empty()

    // Update summary display with zeros
    this.updateCartSummaryDisplay(this.state.cart.summary)

    // Update empty state
    this.updateEmptyState()

    // Update cart badge count
    this.updateCartCount()

    console.log('Cart state reset successfully')
  },

  updateCartUI () {
    console.group('Updating Cart UI')

    try {
      // PERBAIKAN: Validasi state
      if (!this.state.cart) {
        console.warn('Cart state is undefined, initializing empty state')
        this.resetCartState()
      }

      // PERBAIKAN: Log current cart state
      console.log(
        'Current cart state:',
        JSON.stringify(this.state.cart, null, 2)
      )

      const regularContainer = $(this.config.selectors.regularItemsContainer)
      const packageContainer = $(this.config.selectors.packageItemsContainer)

      // Validasi containers
      if (regularContainer.length === 0) {
        console.error(
          'Regular container not found:',
          this.config.selectors.regularItemsContainer
        )
      }

      if (packageContainer.length === 0) {
        console.error(
          'Package container not found:',
          this.config.selectors.packageItemsContainer
        )
      }

      // PERBAIKAN: Log container selectors for debugging
      console.log('Container selectors:', {
        regularContainer: this.config.selectors.regularItemsContainer,
        packageContainer: this.config.selectors.packageItemsContainer
      })

      // Render cart directly instead of using Promise.all
      this.renderCart()

      // PERBAIKAN: Update summary after content updates
      const summary = this.calculateCartSummary()
      this.updateCartSummaryDisplay(summary, true) // true = dengan animasi

      // PERBAIKAN: Rebind events for new elements
      setTimeout(() => {
        this.bindQuantityControls()
        this.bindRemoveEvents()
        console.log('Cart UI fully updated')
      }, 300)
    } catch (error) {
      console.error('Error updating cart UI:', error)
      this.handleError(error)
    } finally {
      console.groupEnd()
    }
  },

  updatePackageItemsUI (items) {
    const container = $(this.config.selectors.packageItemsContainer)
    container.empty()

    if (items.length === 0) {
      container.append(this.createEmptyStateElement('No package items'))
      return
    }

    items.forEach(item => {
      container.append(this.createPackageElement(item))
    })
  },

  async loadCart (skipUpdate = false) {
    console.group('🔄 [LOAD CART] Loading cart data')
    console.log('Skip update parameter:', skipUpdate)
    try {
      // Check if session is active
      const activeSession =
        $('#active-session').length > 0 && !$('#active-session').is('[hidden]')
      console.log('Active session detected:', activeSession)

      if (!activeSession) {
        console.warn('No active session found, but continuing anyway')
      }

      // Show loading state
      this.showLoading(true)

      // Get URL parameters
      const params = this.getUrlParams()
      params.timestamp = Date.now() // Add timestamp to prevent caching
      console.log('Request parameters:', params)

      // Validate parameters
      if (!params.outletId || !params.tableId || !params.brand) {
        throw new Error('Missing required URL parameters')
      }

      // Create cart URL
      const cartUrl = `${
        window.location.origin
      }/order/cart?${new URLSearchParams(params)}`

      // Make request with additional headers to prevent caching
      const response = await $.ajax({
        url: cartUrl,
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
          'Cache-Control': 'no-cache, no-store, must-revalidate',
          Pragma: 'no-cache'
        },
        cache: false,
        dataType: 'json'
      })

      console.log('Server response received:', response)

      if (!response.success) {
        throw new Error(response.message || 'Failed to load cart data')
      }

      // Get cart data from response
      const cartData = response.data && (response.data.cart || response.data)
      if (!cartData) {
        console.warn('Cart data is empty or invalid')
        this.resetCartState()
        this.renderCart()
        return response
      }

      // Clear existing cart state
      this.resetCartState()

      // Update cart state with new data
      this.updateCartState(cartData)

      // Render cart UI
      this.renderCart()

      // Update cart count
      this.updateCartCount()

      // Bind promo related events if promo section exists
      this.bindPromoEvents()

      // Show promo suggestions with slight delay for better UX
      setTimeout(() => {
        this.showPromoSuggestions()
      }, 300)

      // Reset force refresh flag if it was set
      if (window.forceCartRefresh === true) {
        window.forceCartRefresh = false
        console.log('Reset forceCartRefresh flag')
      }

      console.log('Cart data loaded and processed successfully')
      return response
    } catch (error) {
      console.error('Error loading cart:', error)
      // Reset cart state on error
      this.resetCartState()
      this.renderCart()
      throw error
    } finally {
      this.showLoading(false)
      console.groupEnd()
    }
  },

  renderCart () {
    console.group('🎨 [RENDER CART] Rendering cart contents')
    try {
      // Make sure cart state exists
      if (!this.state.cart) {
        console.warn('Cart state is undefined, initializing empty state')
        this.resetCartState()
      }

      // Get containers
      const regularContainer = $(this.config.selectors.regularItemsContainer)
      const packageContainer = $(this.config.selectors.packageItemsContainer)

      if (regularContainer.length === 0) {
        console.error('Regular items container not found')
      }

      if (packageContainer.length === 0) {
        console.error('Package items container not found')
      }

      // Extract items from state with validation
      const regularItems = Array.isArray(this.state.cart.regularItems)
        ? this.state.cart.regularItems
        : []
      const packageItems = Array.isArray(this.state.cart.packageItems)
        ? this.state.cart.packageItems
        : []

      console.log(
        `Rendering ${regularItems.length} regular items and ${packageItems.length} package items`
      )

      // Separate promo free items from regular items
      const standardItems = []
      const promoItems = []

      regularItems.forEach(item => {
        // Check if this is a free promo item (price/unit_price is 0 or has promo marker)
        if (
          (item.price === 0 && item.unit_price === 0) ||
          item.is_promo_item ||
          item.promo_type ||
          (item.notes && item.notes.toLowerCase().includes('promo'))
        ) {
          promoItems.push(item)
        } else {
          standardItems.push(item)
        }
      })

      // Clear containers
      regularContainer.empty()
      packageContainer.empty()

      // Render regular items
      if (standardItems.length > 0) {
        standardItems.forEach(item => {
          if (item && item.product_name) {
            console.log(`Rendering regular item: ${item.product_name}`)
            regularContainer.append(this.createRegularItemElement(item))
          } else {
            console.warn('Invalid regular item data:', item)
          }
        })
      } else {
        regularContainer.append(
          this.createEmptyStateElement('No regular items in cart')
        )
      }

      // Render promo free items if any
      if (promoItems.length > 0) {
        // Add a divider and section header for promo items
        regularContainer.append(`
				  <div class="promo-items-header mt-4 mb-3">
					  <div class="d-flex align-items-center">
						  <div class="flex-grow-1"><hr></div>
						  <div class="px-3">
							  <span class="badge bg-success px-3 py-2">
								  <i class="fa fa-gift me-2"></i>Promo Items
							  </span>
						  </div>
						  <div class="flex-grow-1"><hr></div>
					  </div>
				  </div>
			  `)

        // Render each promo item
        promoItems.forEach(item => {
          if (item && item.product_name) {
            console.log(`Rendering promo item: ${item.product_name}`)
            regularContainer.append(this.createFreeProductElement(item))
          }
        })
      }

      // Render package items
      if (packageItems.length > 0) {
        packageItems.forEach(pkg => {
          if (pkg && pkg.name) {
            console.log(`Rendering package: ${pkg.name}`)
            packageContainer.append(this.createPackageElement(pkg))
          } else {
            console.warn('Invalid package data:', pkg)
          }
        })
      } else {
        packageContainer.append(
          this.createEmptyStateElement('No packages in cart')
        )
      }

      // Update cart summary
      const summary = this.calculateCartSummary()
      this.updateCartSummaryDisplay(summary)

      // Update empty state visibility
      this.updateEmptyState()

      // Add promo info if available
      if (this.state.cart.promo && this.state.cart.promo.isValid) {
        this.updatePromoInfoUI(this.state.cart.promo)
      }

      console.log('Cart rendering completed successfully')
    } catch (error) {
      console.error('Error rendering cart:', error)
      // Show error state
      $(this.config.selectors.regularItemsContainer).html(
        this.createEmptyStateElement('Error loading cart items')
      )
      $(this.config.selectors.packageItemsContainer).empty()
      this.updateEmptyState()
    } finally {
      // Rebind event handlers
      setTimeout(() => {
        this.bindQuantityControls()
        this.bindRemoveEvents()
      }, 100)
      console.groupEnd()
    }
  },

  createFreeProductElement (item) {
    // Pastikan bahwa item memiliki tanda sebagai produk promo
    const isPromoItem =
      item.is_promo_item ||
      item.promo_type ||
      (item.price === 0 && item.unit_price === 0)
    return `
		  <div class="cart-item card mb-3 shadow-sm promo-free-item" data-item-id="${
        item.id || ''
      }">
			  <div class="card-body p-3">
				  <div class="row g-3 align-items-center">
					  <!-- Product Image -->
					  <div class="col-3 position-relative">
						  <div class="product-image-wrapper position-relative">
							  <img src="${this.getProductImageUrl(item.product_pict || 'default.png')}" 
								  class="img-fluid rounded object-fit-cover" 
								  style="height: 100px; width: 100%; object-position: center;"
								  alt="${item.product_name || 'Free Item'}"
								  onerror="this.src='${this.getProductImageUrl('default.png')}';">
							  <span class="position-absolute top-0 end-0 m-1 badge bg-success text-white">
								  <i class="fa fa-gift me-1"></i>Gratis
							  </span>
						  </div>
					  </div>
					  <!-- Product Details -->
					  <div class="col-9">
						  <div class="d-flex justify-content-between align-items-start mb-2">
							  <div>
								  <h6 class="mb-1 text-dark">${item.product_name || 'Free Item'}</h6>
								  <small class="text-success">
									  <i class="fa fa-tag me-1"></i>Item Promo
								  </small>
							  </div>
							  <!-- Promo items cannot be removed independently -->
							  <span class="badge bg-info">
								  <i class="fa fa-gift me-1"></i>Promo
							  </span>
						  </div>
						  <!-- Quantity -->
						  <div class="quantity-control d-flex justify-content-between align-items-center">
							  <div class="input-group input-group-sm" style="max-width: 150px;">
								  <span class="form-control text-center">${item.quantity || 1}</span>
							  </div>
							  <!-- Subtotal -->
							  <div class="text-end">
								  <small class="text-muted d-block">Subtotal</small>
								  <span class="fw-bold text-success">Gratis</span>
							  </div>
						  </div>
						  <!-- Notes -->
						  ${
                item.notes
                  ? `
							  <div class="mt-2 bg-light p-2 rounded">
								  <small class="text-muted">
									  <i class="fa fa-clipboard me-2 text-primary"></i>${item.notes}
								  </small>
							  </div>
						  `
                  : ''
              }
					  </div>
				  </div>
			  </div>
		  </div>
	  `
  },

  // Empty State Templates
  createEmptyStateElement (message) {
    return `
			<div class="text-center py-4">
			  <i class="fa fa-cart-arrow-down fs-1 text-muted"></i>
			  <p class="mt-3 text-muted">${message}</p>
			</div>
			`
  },

  createRegularItemElement (item) {
    if (!item || !item.product_name) {
      console.error('Invalid item data:', item)
      return ''
    }

    // PERBAIKAN: Pastikan data yang diperlukan tersedia dan valid
    const safeId = item.id || ''
    const safeName = item.product_name || 'Unnamed Product'
    const safeImage = item.product_pict || 'default.png'
    const safePrice = parseFloat(item.price) || 0
    const safeQuantity = parseInt(item.quantity) || 0
    const safeStock = parseInt(item.stock) || 0
    const safeSubtotal = safePrice * safeQuantity
    const safeNotes = item.notes || ''

    console.log('Creating regular item element:', {
      id: safeId,
      name: safeName,
      price: safePrice,
      quantity: safeQuantity,
      subtotal: safeSubtotal
    })

    // PERBAIKAN: Gunakan data yang valid untuk membuat elemen HTML
    return `
		  <div class="cart-item card mb-3 shadow-sm" data-item-id="${safeId}">
			<div class="card-body p-3">
			  <div class="row g-3 align-items-center">
				<!-- Product Image -->
				<div class="col-3 position-relative">
				  <div class="product-image-wrapper position-relative">
					<img 
					  src="${this.getProductImageUrl(safeImage)}" 
					  class="img-fluid rounded object-fit-cover" 
					  style="height: 100px; width: 100%; object-position: center;"
					  alt="${safeName}"
					  onerror="this.src='${this.getProductImageUrl('default.png')}';"
					>
					<span class="position-absolute top-0 end-0 m-1 badge bg-light text-dark">
					  <i class="fa fa-box me-1"></i>Stok: ${safeStock}
					</span>
				  </div>
				</div>
			  
				<!-- Product Details -->
				<div class="col-9">
				  <div class="d-flex justify-content-between align-items-start mb-2">
					<div>
					  <h6 class="mb-1 text-dark">${safeName}</h6>
					  <small class="text-muted">Rp ${this.formatPrice(safePrice)}</small>
					</div>
					<button type="button" class="btn btn-sm btn-outline-danger remove-item" data-item-id="${safeId}">
					  <i class="fa fa-trash"></i>
					</button>
				  </div>
			  
				  <!-- Quantity Control -->
				  <div class="quantity-control d-flex justify-content-between align-items-center">
					<div class="input-group input-group-sm" style="max-width: 150px;">
					  <button class="btn btn-outline-secondary decrease-qty" type="button" data-item-id="${safeId}" ${
      safeQuantity <= 1 ? 'disabled' : ''
    }>
						<i class="fa fa-minus"></i>
					  </button>
					  <input type="number" class="form-control text-center quantity-input" value="${safeQuantity}" min="1" max="${safeStock}" data-item-id="${safeId}">
					  <button class="btn btn-outline-secondary increase-qty" type="button" data-item-id="${safeId}" ${
      safeQuantity >= safeStock ? 'disabled' : ''
    }>
						<i class="fa fa-plus"></i>
					  </button>
					</div>
					
					<!-- Subtotal -->
					<div class="text-end">
					  <small class="text-muted d-block">Subtotal</small>
					  <span class="fw-bold text-primary">Rp ${this.formatPrice(safeSubtotal)}</span>
					</div>
				  </div>
			  
				  <!-- Notes -->
				  ${
            safeNotes
              ? `
					<div class="mt-2 bg-light p-2 rounded">
					  <small class="text-muted">
						<i class="fa fa-clipboard me-2 text-primary"></i>${safeNotes}
					  </small>
					</div>
				  `
              : ''
          }
				</div>
			  </div>
			</div>
		  </div>
		  `
  },

  createPackageElement (pkg) {
    if (!pkg || !pkg.name) {
      console.error('Invalid package data:', pkg)
      return ''
    }

    console.log('Creating package element:', {
      id: pkg.id,
      name: pkg.name,
      basePrice: pkg.base_price,
      total: pkg.total,
      itemCount: pkg.items?.length || 0
    })

    return `
			<div class="cart-package card mb-3 shadow-sm" data-package-id="${pkg.id || ''}">
			  <!-- Package Header -->
			  <div class="card-header bg-light d-flex justify-content-between align-items-center">
			  <div>
				<h6 class="mb-0">${pkg.name}</h6>
				<span class="badge bg-primary">Paket</span>
			  </div>
			  <button 
				type="button" 
				class="btn btn-sm btn-outline-danger remove-package" 
				data-package-id="${pkg.id || ''}"
			  >
				<i class="fa fa-trash"></i>
			  </button>
			  </div>
		  
			  <!-- Package Items -->
			  <div class="card-body p-0">
			  <ul class="list-group list-group-flush">
				${
          Array.isArray(pkg.items)
            ? pkg.items
                .map(
                  item => `
				<li class="list-group-item d-flex justify-content-between align-items-center">
				  <div>
				  <span class="fw-medium">${item.product_name}</span>
				  <small class="d-block text-muted">
					${item.quantity} x Rp ${this.formatPrice(item.price)}
				  </small>
				  ${
            item.notes
              ? `
					<small class="text-muted mt-1 d-block">
					<i class="fa fa-comment me-2 text-primary"></i>
					${item.notes}
					</small>
				  `
              : ''
          }
				  </div>
				  <span class="fw-bold text-primary">
				  Rp ${this.formatPrice(item.subtotal || item.price * item.quantity)}
				  </span>
				</li>
				`
                )
                .join('')
            : '<li class="list-group-item">No items in package</li>'
        }
			  </ul>
			  </div>
		  
			  <!-- Package Footer -->
			  <div class="card-footer bg-light">
			  <div class="d-flex justify-content-between">
				<span class="text-muted">Harga Dasar</span>
				<span>Rp ${this.formatPrice(pkg.base_price || 0)}</span>
			  </div>
			  <div class="d-flex justify-content-between mt-2">
				<span class="fw-bold">Total Paket</span>
				<span class="fw-bold text-primary">
				Rp ${this.formatPrice(pkg.total || 0)}
				</span>
			  </div>
			  </div>
			</div>
			`
  },

  // Package Item Element - Simple Rendering
  createPackageItemElement (item) {
    return `
			<div class="package-item mb-2">
			  <div class="d-flex justify-content-between align-items-center">
			  <span>${item.product_name}</span>
			  <span>${item.quantity}x</span>
			  </div>
			</div>
			`
  },

  createFreeProductElement (item) {
    // Pastikan bahwa item memiliki tanda sebagai produk promo
    const isPromoItem =
      item.is_promo_item ||
      item.promo_type ||
      (item.price === 0 && item.unit_price === 0)

    return `
	  <div class="cart-item card mb-3 shadow-sm promo-free-item" data-item-id="${
      item.id || ''
    }">
		  <div class="card-body p-3">
			  <div class="row g-3 align-items-center">
				  <!-- Product Image -->
				  <div class="col-3 position-relative">
					  <div class="product-image-wrapper position-relative">
						  <img src="${this.getProductImageUrl(item.product_pict || 'default.png')}" 
							   class="img-fluid rounded object-fit-cover" 
							   style="height: 100px; width: 100%; object-position: center;"
							   alt="${item.product_name || 'Free Item'}"
							   onerror="this.src='${this.getProductImageUrl('default.png')}';">
						  <span class="position-absolute top-0 end-0 m-1 badge bg-success text-white">
							  <i class="fa fa-gift me-1"></i>Gratis
						  </span>
					  </div>
				  </div>
				  <!-- Product Details -->
				  <div class="col-9">
					  <div class="d-flex justify-content-between align-items-start mb-2">
						  <div>
							  <h6 class="mb-1 text-dark">${item.product_name || 'Free Item'}</h6>
							  <small class="text-success">
								  <i class="fa fa-tag me-1"></i>Item Promo
							  </small>
						  </div>
						  <!-- Promo items cannot be removed independently -->
						  <span class="badge bg-info">
							  <i class="fa fa-gift me-1"></i>Promo
						  </span>
					  </div>
					  <!-- Quantity -->
					  <div class="quantity-control d-flex justify-content-between align-items-center">
						  <div class="input-group input-group-sm" style="max-width: 150px;">
							  <span class="form-control text-center">${item.quantity || 1}</span>
						  </div>
						  <!-- Subtotal -->
						  <div class="text-end">
							  <small class="text-muted d-block">Subtotal</small>
							  <span class="fw-bold text-success">Gratis</span>
						  </div>
					  </div>
					  <!-- Notes -->
					  ${
              item.notes
                ? `
					  <div class="mt-2 bg-light p-2 rounded">
						  <small class="text-muted">
							  <i class="fa fa-clipboard me-2 text-primary"></i>${item.notes}
						  </small>
					  </div>
					  `
                : ''
            }
				  </div>
			  </div>
		  </div>
	  </div>
	  `
  },

  // Price Formatting Method
  formatPrice (amount) {
    return new Intl.NumberFormat('id').format(amount)
  },

  // Cart Count Update
  async updateCartCount () {
    try {
      const params = this.getUrlParams()
      const response = await $.ajax({
        url: this.config.endpoints.cartCount,
        method: 'GET',
        data: params
      })

      if (response.success) {
        const newCount = response.data.metrics?.total_items || 0
        const $badge = $(this.config.selectors.cartCountBadge)
        const currentCount = parseInt($badge.text()) || 0

        // Jika ada perubahan jumlah, tambahkan animasi
        if (newCount !== currentCount) {
          if (newCount > currentCount) {
            // Animasi saat bertambah
            $badge.text(newCount)
            this.animateCartBadge()
          } else {
            // Animasi saat berkurang (lebih subtle)
            $badge.fadeOut(200, function () {
              $(this).text(newCount).fadeIn(200)
            })
          }
        }
      }
    } catch (error) {
      console.error('Error updating cart count:', error)
    }
  },
  animateCartBadge () {
    const $badge = $(this.config.selectors.cartCountBadge)
    const $cartBtn = $(this.config.selectors.cartButton)

    // Animasi pada badge
    $badge.removeClass('counter-update')
    setTimeout(() => {
      $badge.addClass('counter-update')
    }, 10)

    // Animasi pada tombol cart
    $cartBtn.addClass('highlight-button')
    setTimeout(() => {
      $cartBtn.removeClass('highlight-button')
    }, 800)
  },

  // Loading State
  showLoading (show = true) {
    if (show) {
      // Tampilkan loading overlay dengan animasi yang lebih halus
      const $overlay = $(this.config.selectors.loadingOverlay)
      if ($overlay.length) {
        $overlay
          .css({
            opacity: 0,
            display: 'flex'
          })
          .animate(
            {
              opacity: 0.7
            },
            300
          )
      }
    } else {
      // Sembunyikan dengan animasi
      $(this.config.selectors.loadingOverlay).fadeOut(300)
    }
    this.state.ui.loading = show
  },

  // Error Handling
  handleError (error) {
    console.error('Error:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.message || 'Something went wrong',
      confirmButtonText: 'OK'
    })
  },

  // Helper Methods
  getUrlParams () {
    const params = new URLSearchParams(window.location.search)
    return {
      outletId: params.get('outletId'),
      tableId: params.get('tableId'),
      brand: params.get('brand')
    }
  },

  getProductImageUrl (imageName) {
    return `${window.location.origin}/resource/assets-frontend/dist/product/${
      imageName || 'default.png'
    }`
  },

  formatCurrency (amount) {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount)
  },

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
      console.warn('Error formatting date:', error)
      return dateString || '-'
    }
  },

  handleQuantityChange (itemId, action, value = null) {
    console.group('Quantity Change Handler')
    console.log('Request details:', { itemId, action, value })

    try {
      // PERBAIKAN: Tampilkan loading indicator
      this.showLoading(true)

      // Find item dengan error handling yang lebih baik
      const item = this.findItemById(itemId)
      if (!item) {
        console.error(`Item ${itemId} not found in state`)
        this.showNotification('Item tidak ditemukan', 'error')
        this.showLoading(false)
        console.groupEnd()
        return
      }

      console.log('Item found:', item)

      // Get previous quantity dengan validasi
      const previousQuantity = parseInt(item.quantity) || 1
      let newQuantity

      // Calculate new quantity
      switch (action) {
        case 'increase':
          newQuantity = previousQuantity + 1
          break
        case 'decrease':
          newQuantity = Math.max(1, previousQuantity - 1)
          break
        case 'direct':
          newQuantity = Math.max(1, parseInt(value) || 1)
          break
        default:
          throw new Error('Invalid action')
      }

      // Validasi stock
      const stock = parseInt(item.stock) || 1
      if (newQuantity > stock) {
        this.showNotification(`Stok hanya tersedia ${stock} item`, 'warning')
        newQuantity = stock
      }

      console.log(`Quantity change: ${previousQuantity} -> ${newQuantity}`)

      // PERBAIKAN: Perbarui nilai di elemen DOM
      this.updateQuantityUI(itemId, newQuantity, item)

      // PERBAIKAN: Selalu perbarui state lokal dulu
      // Update item quantity in state
      if (item) {
        item.quantity = newQuantity
        if (item.price) {
          item.subtotal = parseFloat(item.price) * newQuantity
        }
      }

      // PERBAIKAN: Kirim update ke server di background
      // untuk UI yang lebih responsif
      this.updateServerQuantity(itemId, newQuantity, {
        type: 'regular',
        price: item.price
      })
        .then(response => {
          console.log('Server quantity update success:', response)

          // PERBAIKAN: Handle promo status changes
          if (response.promo_status) {
            console.log('Promo status updated:', response.promo_status)

            // Update promo state if necessary
            if (this.state.cart.promo) {
              this.state.cart.promo.isValid = response.promo_status.valid
              this.state.cart.promo.message = response.promo_status.message
              this.state.cart.promo.discount = response.promo_status.discount

              // Update promo UI
              this.updatePromoInfoUI(this.state.cart.promo)
            }

            // PERBAIKAN: Tampilkan notifikasi tentang perubahan status promo
            if (response.promo_status.valid && !this.state.cart.promo.isValid) {
              // Promo menjadi valid
              this.showNotification(
                'Promo sekarang berlaku untuk keranjang Anda',
                'success'
              )
            } else if (
              !response.promo_status.valid &&
              this.state.cart.promo &&
              this.state.cart.promo.isValid
            ) {
              // Promo tidak lagi valid
              this.showNotification(
                'Promo tidak lagi valid setelah perubahan kuantitas',
                'warning'
              )
              this.resetPromo()
            } else if (
              response.promo_status.valid &&
              response.promo_status.discount !== this.state.cart.promo.discount
            ) {
              // Nilai diskon berubah
              this.showNotification(
                `Nilai diskon promo berubah menjadi ${this.formatCurrency(
                  response.promo_status.discount
                )}`,
                'info'
              )
            }
          }

          // Perbarui ringkasan cart
          const summary = this.calculateCartSummary()
          this.updateCartSummaryDisplay(summary)

          // Perbarui jumlah cart di UI
          this.updateCartCount()

          // PERBAIKAN: Check untuk produk BOGO/Bundling yang perlu diupdate
          if (response.refresh_items && Array.isArray(response.refresh_items)) {
            this.handlePromoItemsUpdate(response.refresh_items)
          }
        })
        .catch(error => {
          console.error('Server quantity update failed:', error)
          this.showNotification('Gagal memperbarui kuantitas', 'error')

          // Kembalikan nilai quantity ke nilai sebelumnya
          if (item) {
            item.quantity = previousQuantity
            if (item.price) {
              item.subtotal = parseFloat(item.price) * previousQuantity
            }
          }

          // Update UI dengan nilai sebelumnya
          this.updateQuantityUI(itemId, previousQuantity, item)

          // Perbarui ringkasan cart
          const summary = this.calculateCartSummary()
          this.updateCartSummaryDisplay(summary)
        })
        .finally(() => {
          this.showLoading(false)

          // PERBAIKAN: Refresh cart setelah semua selesai untuk memastikan data konsisten
          if (window.forceCartRefresh) {
            setTimeout(() => {
              this.loadCart(false).catch(err =>
                console.error(
                  'Error refreshing cart after quantity update:',
                  err
                )
              )
            }, 300)
          }
        })
    } catch (error) {
      console.error('Quantity change error:', error)
      this.showNotification('Gagal mengubah kuantitas', 'error')
      this.showLoading(false)
    }

    console.groupEnd()
  },

  handlePromoItemsUpdate (promoItems) {
    console.group('📦 Handling Promo Items Update')

    if (!Array.isArray(promoItems) || promoItems.length === 0) {
      console.log('No promo items to update')
      console.groupEnd()
      return
    }

    console.log('Promo items to update:', promoItems)

    // Identifikasi item yang akan ditambahkan vs diupdate vs dihapus
    const itemsToAdd = []
    const itemsToUpdate = []
    const itemsToRemove = []

    promoItems.forEach(item => {
      if (item.action === 'add') {
        itemsToAdd.push(item)
      } else if (item.action === 'update') {
        itemsToUpdate.push(item)
      } else if (item.action === 'remove') {
        itemsToRemove.push(item)
      }
    })

    console.log('Items to add:', itemsToAdd.length)
    console.log('Items to update:', itemsToUpdate.length)
    console.log('Items to remove:', itemsToRemove.length)

    // Tangani item yang perlu dihapus
    if (itemsToRemove.length > 0) {
      // Remove items from state first
      itemsToRemove.forEach(item => {
        // Find matching items in regular and package items
        if (this.state.cart.regularItems) {
          this.state.cart.regularItems = this.state.cart.regularItems.filter(
            cartItem => cartItem.id !== item.id
          )
        }

        if (this.state.cart.packageItems) {
          this.state.cart.packageItems.forEach(pkg => {
            if (pkg.items) {
              pkg.items = pkg.items.filter(pkgItem => pkgItem.id !== item.id)
            }
          })
        }
      })

      // Update UI - remove items from DOM
      itemsToRemove.forEach(item => {
        const itemElement = document.querySelector(
          `.cart-item[data-item-id="${item.id}"]`
        )

        if (itemElement) {
          // Add fade-out animation before removing
          $(itemElement).fadeOut(300, function () {
            $(this).remove()
          })
        }
      })

      // Show notification
      if (itemsToRemove.length === 1) {
        this.showNotification('Produk promo gratis telah dihapus', 'info')
      } else {
        this.showNotification(
          `${itemsToRemove.length} produk promo gratis telah dihapus`,
          'info'
        )
      }
    }

    // Tangani item yang perlu diupdate
    if (itemsToUpdate.length > 0) {
      itemsToUpdate.forEach(updateItem => {
        // Update in state
        const updateInArray = items => {
          if (!Array.isArray(items)) return

          const index = items.findIndex(item => item.id === updateItem.id)
          if (index !== -1) {
            // Update quantity and subtotal
            items[index].quantity = updateItem.quantity
            items[index].subtotal = updateItem.subtotal

            // Update UI if possible
            const itemElement = document.querySelector(
              `.cart-item[data-item-id="${updateItem.id}"] .quantity-input`
            )

            if (itemElement) {
              itemElement.value = updateItem.quantity
            }

            // Update subtotal display
            const subtotalElement = document.querySelector(
              `.cart-item[data-item-id="${updateItem.id}"] .subtotal-amount`
            )

            if (subtotalElement) {
              subtotalElement.textContent = this.formatCurrency(
                updateItem.subtotal
              )
            }
          }
        }

        // Look in regularItems
        if (this.state.cart.regularItems) {
          updateInArray(this.state.cart.regularItems)
        }

        // Look in packageItems (for each package's items)
        if (this.state.cart.packageItems) {
          this.state.cart.packageItems.forEach(pkg => {
            if (pkg.items) {
              updateInArray(pkg.items)
            }
          })
        }
      })

      // Flash updated items for visual feedback
      itemsToUpdate.forEach(item => {
        const itemElement = document.querySelector(
          `.cart-item[data-item-id="${item.id}"]`
        )

        if (itemElement) {
          $(itemElement).addClass('item-updated')
          setTimeout(() => {
            $(itemElement).removeClass('item-updated')
          }, 1500)
        }
      })
    }

    // Tangani item yang perlu ditambahkan
    if (itemsToAdd.length > 0) {
      itemsToAdd.forEach(newItem => {
        // PERBAIKAN: Tambahkan item promo ke state
        if (newItem.is_promo_item) {
          // Buat objek item baru
          const promoItem = {
            id: newItem.id,
            product_id: newItem.product_id,
            product_name: newItem.product_name,
            product_pict: newItem.product_pict || 'default.png',
            quantity: newItem.quantity || 1,
            price: 0, // Produk promo selalu gratis
            subtotal: 0,
            notes: newItem.notes || 'Produk promo gratis',
            is_promo_item: 1,
            promo_type: newItem.promo_type
          }

          // Tambahkan ke state
          if (!this.state.cart.regularItems) {
            this.state.cart.regularItems = []
          }

          this.state.cart.regularItems.push(promoItem)

          // Render item baru ke UI
          const regularContainer = $(
            this.config.selectors.regularItemsContainer
          )

          if (regularContainer.length) {
            // Periksa apakah bagian promo sudah ada
            let promoSection = regularContainer.find('.promo-items-header')

            if (promoSection.length === 0) {
              // Tambahkan header promo jika belum ada
              regularContainer.append(`
				<div class="promo-items-header mt-4 mb-3">
				  <div class="d-flex align-items-center">
					<div class="flex-grow-1"><hr></div>
					<div class="px-3">
					  <span class="badge bg-success px-3 py-2">
						<i class="fa fa-gift me-2"></i>Promo Items
					  </span>
					</div>
					<div class="flex-grow-1"><hr></div>
				  </div>
				</div>
			  `)
            }

            // Buat elemen produk promo
            const itemHTML = this.createFreeProductElement(promoItem)

            // Tambahkan dengan animasi
            const $itemElement = $(itemHTML).hide()
            regularContainer.append($itemElement)
            $itemElement.slideDown(300).addClass('highlight-new-item')

            // Hapus highlight setelah beberapa detik
            setTimeout(() => {
              $itemElement.removeClass('highlight-new-item')
            }, 3000)
          }
        }
      })

      // Show notification
      if (itemsToAdd.length === 1) {
        this.showNotification(
          'Produk promo gratis telah ditambahkan',
          'success'
        )
      } else {
        this.showNotification(
          `${itemsToAdd.length} produk promo gratis telah ditambahkan`,
          'success'
        )
      }
    }

    // Update cart summary after all changes
    const summary = this.calculateCartSummary()
    this.updateCartSummaryDisplay(summary, true) // true = dengan animasi

    console.groupEnd()
  },

  async updateServerQuantity (itemId, quantity, info) {
    console.log('Sending quantity update to server:', {
      itemId,
      quantity,
      info
    })

    try {
      // PERBAIKAN: Tambahkan lebih banyak log dan validasi
      if (!itemId) {
        throw new Error('Item ID is required')
      }

      if (quantity < 1) {
        throw new Error('Quantity must be at least 1')
      }

      // PERBAIKAN: Tambahkan parameter URL
      const urlParams = this.getUrlParams()
      const payload = {
        itemId: itemId,
        quantity: quantity,
        type: info?.type || 'regular',
        ...urlParams // Add URL parameters
      }

      console.log('Quantity update payload:', payload)

      const response = await $.ajax({
        url: `${window.location.origin}/order/updateQuantity`,
        method: 'POST',
        data: JSON.stringify(payload),
        contentType: 'application/json',
        dataType: 'json',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })

      console.log('Quantity update server response:', response)

      if (!response.success) {
        throw new Error(response.message || 'Failed to update server')
      }

      return response
    } catch (error) {
      console.error('Server quantity update error:', error)
      // PERBAIKAN: Re-throw error untuk chain promise
      throw error
    }
  },

  updateQuantityUI (itemId, quantity, item) {
    console.group('Updating Quantity UI')
    console.log('Update Data:', { itemId, quantity, item })

    try {
      // Update quantity input
      const quantityInput = document.querySelector(
        `.quantity-input[data-item-id="${itemId}"]`
      )
      if (quantityInput) {
        quantityInput.value = quantity
      }

      // Update quantity buttons state
      const decreaseBtn = document.querySelector(
        `.decrease-qty[data-item-id="${itemId}"]`
      )
      const increaseBtn = document.querySelector(
        `.increase-qty[data-item-id="${itemId}"]`
      )

      if (decreaseBtn) {
        decreaseBtn.disabled = quantity <= 1
      }
      if (increaseBtn) {
        increaseBtn.disabled = quantity >= parseInt(item.stock)
      }

      // Update subtotal
      const subtotalElement = document.querySelector(
        `[data-item-id="${itemId}"] .text-primary`
      )
      if (subtotalElement) {
        const subtotal = quantity * parseFloat(item.price)
        subtotalElement.textContent = this.formatCurrency(subtotal)
      }

      // Update total summary
      const summary = this.recalculateCartSummary()
      this.updateCartSummaryDisplay(summary)

      console.log('UI Updated Successfully')
    } catch (error) {
      console.error('Error updating quantity UI:', error)
    } finally {
      console.groupEnd()
    }
  },

  findItem (itemId) {
    if (!itemId) return null

    try {
      // Check regular items
      if (Array.isArray(this.state.cart.regularItems)) {
        const regularItem = this.state.cart.regularItems.find(
          item => String(item.id) === String(itemId)
        )
        if (regularItem) return { ...regularItem, type: 'regular' }
      }

      // Check package items
      if (Array.isArray(this.state.cart.packageItems)) {
        for (const pkg of this.state.cart.packageItems) {
          if (Array.isArray(pkg.items)) {
            const packageItem = pkg.items.find(
              item => String(item.id) === String(itemId)
            )
            if (packageItem) return { ...packageItem, type: 'package' }
          }
        }
      }

      return null
    } catch (error) {
      console.error('Error finding item:', error)
      return null
    }
  },

  async removeCartItem (id, type) {
    console.group('🛠️ [REMOVE ITEM] Menghapus item dari keranjang')
    console.log(`🔴 Tombol hapus diklik untuk item ID: ${id}, Type: ${type}`)

    try {
      this.showLoading(true)

      // PERBAIKAN: Ambil referensi elemen yang akan dihapus untuk animasi
      const elementSelector =
        type === 'package'
          ? `.cart-package[data-package-id="${id}"]`
          : `.cart-item[data-item-id="${id}"]`

      const $elementToRemove = $(elementSelector)

      if ($elementToRemove.length) {
        console.log(`Element found for removal: ${elementSelector}`)

        // PERBAIKAN: Animasi fade out untuk UX yang lebih baik
        await new Promise(resolve => {
          $elementToRemove.fadeOut(300, function () {
            // Elemen dihapus dari DOM setelah fadeOut
            $(this).remove()
            resolve()
          })
        })
      } else {
        console.warn(`Element not found for removal: ${elementSelector}`)
      }

      // Hapus item dari state
      if (type === 'package') {
        this.state.cart.packageItems = this.state.cart.packageItems.filter(
          pkg => pkg.id !== id
        )
      } else {
        this.state.cart.regularItems = this.state.cart.regularItems.filter(
          item => item.id !== id
        )
      }

      // PERBAIKAN: Update summary secara langsung
      const summary = this.calculateCartSummary()

      // PERBAIKAN UTAMA: Force update order summary UI
      this.updateCartSummaryDisplay(summary)
      this.updateEmptyState()
      this.updateCartCount()

      // PERBAIKAN: Kirim request penghapusan ke server
      const urlParams = this.getUrlParams()
      const payload = {
        itemId: id,
        type: type,
        ...urlParams
      }

      // PERBAIKAN: Kirim ke server dan pastikan UI diperbarui lagi setelah response
      const response = await $.ajax({
        url: `${window.location.origin}/order/removeCartItem`,
        method: 'POST',
        data: JSON.stringify(payload),
        contentType: 'application/json',
        dataType: 'json',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })

      if (response.success) {
        console.log('🟢 [SERVER] Item berhasil dihapus dari server:', response)

        // PERBAIKAN KRITIS: Handle promo status changes jika ada
        if (response.promo_status) {
          console.log('Promo status after removal:', response.promo_status)

          // Update promo state jika ada
          if (this.state.cart.promo) {
            // Update promo info berdasarkan status baru
            this.state.cart.promo.isValid = response.promo_status.valid
            this.state.cart.promo.message = response.promo_status.message
            this.state.cart.promo.discount = response.promo_status.discount

            if (!response.promo_status.valid) {
              // Jika promo menjadi tidak valid, reset state promo
              this.showNotification(
                'Promo tidak lagi valid setelah penghapusan item',
                'warning'
              )
              this.resetPromo()
            } else {
              // Update UI promo jika masih valid
              this.updatePromoInfoUI(this.state.cart.promo)
            }
          }
        }

        // PERBAIKAN KRITIS: Update summary jika ada dalam response
        if (response.summary) {
          console.log('New summary after removal:', response.summary)
          this.updateCartSummaryDisplay(response.summary, true) // true = dengan animasi
        }

        // Force reload cart data untuk memastikan konsistensi
        // antara server dan tampilan UI
        if (response.refresh_cart || window.forceCartRefresh) {
          await this.loadCart(false)
        }
      } else {
        console.error('❌ [SERVER] Gagal menghapus dari server:', response)
        this.showNotification('Gagal menghapus item', 'error')
      }

      console.log('🎯 [SELESAI] UI dan state sudah diperbarui!')
      return true
    } catch (error) {
      console.error('❌ [ERROR] Gagal menghapus item:', error)
      this.showNotification('Gagal menghapus item', 'error')
      return false
    } finally {
      this.showLoading(false)
      console.groupEnd()
    }
  },

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

      // PERBAIKAN: Flag refresh keranjang agar data selalu konsisten
      window.forceCartRefresh = true

      console.log('✅ Cart Addition Successful')
      console.groupEnd()
    } catch (error) {
      console.error('🔴 Cart Addition Error:', error)
      this.showError('Kesalahan', 'Gagal memperbarui keranjang')
      console.groupEnd()
    }
  },

  refreshCartAfterPromoChange (promoStatus) {
    window.forceCartRefresh = true

    // Update status promo lokal
    if (this.state.cart.promo) {
      this.state.cart.promo.isValid = promoStatus.valid
      this.state.cart.promo.discount = promoStatus.discount
      this.state.cart.promo.message = promoStatus.message
    }

    // Jika promo menjadi tidak valid, hapus dari UI
    if (!promoStatus.valid && this.state.cart.promo?.isValid) {
      this.resetPromo()
      this.showNotification('Promo tidak lagi valid', 'warning')
    }

    // Refresh cart setelah delay pendek
    setTimeout(() => {
      this.loadCart(false)
        .then(() => {
          if (this.state.ui.cartModalVisible) {
            this.updateCartUI()
          }
        })
        .catch(err =>
          console.error('Error refreshing cart after promo change:', err)
        )
    }, 300)
  },

  updateCartState (newCartData) {
    console.group('🔄 [UPDATE STATE] Updating cart state')
    try {
      // Validate input data
      if (!newCartData) {
        console.warn('Empty cart data received, initializing empty state')
        this.resetCartState()
        return
      }

      // Extract regular and package items with validation
      let regularItems = []
      if (
        newCartData.regular_items &&
        Array.isArray(newCartData.regular_items)
      ) {
        regularItems = JSON.parse(JSON.stringify(newCartData.regular_items))
        console.log(`Extracted ${regularItems.length} regular items`)
      }

      let packageItems = []
      if (
        newCartData.package_items &&
        Array.isArray(newCartData.package_items)
      ) {
        packageItems = JSON.parse(JSON.stringify(newCartData.package_items))
        console.log(`Extracted ${packageItems.length} package items`)
      }

      // Extract promo data if available
      let promoData = null
      if (newCartData.promo) {
        promoData = {
          code: newCartData.promo.code,
          type: newCartData.promo.type,
          discount: newCartData.promo.discount || 0,
          message: newCartData.promo.message || 'Promo applied',
          isValid: true,
          details: newCartData.promo
        }
        console.log('Extracted promo data:', promoData)
      }

      // Completely replace cart state with deep copies
      this.state.cart = {
        regularItems: regularItems,
        packageItems: packageItems,
        promo: promoData || {
          code: null,
          type: null,
          discount: 0,
          message: null,
          isValid: false,
          details: null
        }
      }

      // Calculate and store summary
      const summary = this.calculateCartSummary()
      this.state.cart.summary = summary

      console.log('Cart state updated successfully:', {
        regularCount: regularItems.length,
        packageCount: packageItems.length,
        subtotal: summary.subtotal,
        total: summary.total,
        promoApplied: promoData ? true : false
      })
    } catch (error) {
      console.error('Failed to update cart state:', error)
      this.resetCartState()
    }
    console.groupEnd()
  },

  refreshCartItemsUI () {
    console.group('🔄 [RENDER UI] Memperbarui tampilan modal keranjang')

    try {
      const regularContainer = $(this.config.selectors.regularItemsContainer)
      const packageContainer = $(this.config.selectors.packageItemsContainer)

      console.log(
        '📌 [SEBELUM UPDATE] Regular Items:',
        this.state.cart.regularItems.length
      )
      console.log(
        '📌 [SEBELUM UPDATE] Package Items:',
        this.state.cart.packageItems.length
      )

      // Kosongkan kontainer
      regularContainer.empty()
      packageContainer.empty()

      // Render ulang regular items
      if (
        this.state.cart.regularItems &&
        this.state.cart.regularItems.length > 0
      ) {
        this.state.cart.regularItems.forEach(item => {
          regularContainer.append(this.createRegularItemElement(item))
        })
      } else {
        regularContainer.append(
          this.createEmptyStateElement('Tidak ada item reguler')
        )
      }

      // Render ulang package items
      if (
        this.state.cart.packageItems &&
        this.state.cart.packageItems.length > 0
      ) {
        this.state.cart.packageItems.forEach(pkg => {
          packageContainer.append(this.createPackageElement(pkg))
        })
      } else {
        packageContainer.append(this.createEmptyStateElement('Tidak ada paket'))
      }

      // PERBAIKAN: Update summary setelah rendering
      const summary = this.calculateCartSummary()
      this.updateCartSummaryDisplay(summary)

      // PERBAIKAN: Update empty state
      this.updateEmptyState()

      // Rebind events untuk elemen-elemen baru
      this.bindRemoveEvents()
      this.bindQuantityControls()

      console.log('✅ [SELESAI] Tampilan modal sudah diperbarui!')
    } catch (error) {
      console.error('Error refreshing cart UI:', error)
    }

    console.groupEnd()
  },

  validatePromoCode (promoCode) {
    console.group('🏷️ [VALIDATE PROMO] Validating promo code')
    console.log('Promo code to validate:', promoCode)
    try {
      // Set loading state
      this.state.ui.promoLoading = true
      this.updatePromoUI(true)

      // Ambil parameter yang diperlukan
      const params = this.getUrlParams()
      const orderTotal = this.state.cart.summary.subtotal || 0

      // Persiapkan payload untuk validasi promo
      const payload = {
        promo_code: promoCode,
        order_total: orderTotal,
        brand: params.brand
      }
      console.log('Validation payload:', payload)

      // Kirim request ke endpoint validasi promo dengan Promise
      return new Promise((resolve, reject) => {
        $.ajax({
          url: `${window.location.origin}${this.config.endpoints.validatePromo}`,
          method: 'POST',
          contentType: 'application/json',
          data: JSON.stringify(payload),
          dataType: 'json',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          success: response => {
            console.log('Promo validation response:', response)

            if (response.success) {
              // Update state promo
              this.state.cart.promo = {
                code: promoCode,
                type:
                  response.promo_type ||
                  response.data?.promo?.promo_type ||
                  'percentage',
                discount: response.discount_amount || 0,
                message: response.message || 'Promo applied successfully',
                isValid: true,
                details: response.data || response.promo
              }

              // Update UI dengan diskon
              this.updateCartWithPromo(this.state.cart.promo)

              // Tampilkan pesan sukses
              this.showPromoSuccess(this.state.cart.promo)

              // Jika promo tipe bundling atau BOGO, tampilkan pesan khusus
              if (
                this.state.cart.promo.type === 'bundling' ||
                this.state.cart.promo.type === 'bogo'
              ) {
                this.showNotification(
                  'Klik "Order" untuk mendapatkan produk gratis dari promo ini',
                  'info'
                )
              }

              resolve(true)
            } else {
              // Reset state promo
              this.state.cart.promo = {
                code: null,
                type: null,
                discount: 0,
                message: response.message || 'Invalid promo code',
                isValid: false,
                details: null
              }

              // Tampilkan pesan error
              this.showPromoError(response.message || 'Invalid promo code')
              resolve(false)
            }
          },
          error: (xhr, status, error) => {
            console.error('Promo validation error:', xhr)
            // Reset state promo dan tampilkan error
            this.state.cart.promo = {
              code: null,
              type: null,
              discount: 0,
              message:
                xhr.responseJSON?.message || 'Error validating promo code',
              isValid: false,
              details: null
            }
            this.showPromoError(
              xhr.responseJSON?.message || 'Error validating promo code'
            )
            reject(xhr)
          },
          complete: () => {
            // Reset loading state
            this.state.ui.promoLoading = false
            this.updatePromoUI(false)
            console.groupEnd()
          }
        })
      })
    } catch (error) {
      console.error('Promo validation error:', error)
      // Reset state promo dan tampilkan error
      this.state.cart.promo = {
        code: null,
        type: null,
        discount: 0,
        message: error.message || 'Error validating promo code',
        isValid: false,
        details: null
      }
      this.showPromoError(error.message || 'Error validating promo code')
      this.state.ui.promoLoading = false
      this.updatePromoUI(false)
      console.groupEnd()
      return Promise.reject(error)
    }
  },

  renderPromoEligibleProducts () {
    console.group('🏷️ Rendering Promo Eligible Products')

    try {
      // Check if promo is active
      if (!this.state.cart.promo || !this.state.cart.promo.isValid) {
        console.log('No active promo, skipping eligible products highlight')
        return
      }

      // Get promo details
      const promo = this.state.cart.promo

      // Extract eligible product IDs and category IDs
      let eligibleProductIds = []
      let eligibleCategoryIds = []

      // Extract from various response structures
      if (promo.details && promo.details.promo) {
        const promoDetails = promo.details.promo

        // Get product-specific promos
        if (
          promoDetails.product_ids &&
          Array.isArray(promoDetails.product_ids)
        ) {
          eligibleProductIds = promoDetails.product_ids
        }

        // Get category-specific promos
        if (
          promoDetails.category_ids &&
          Array.isArray(promoDetails.category_ids)
        ) {
          eligibleCategoryIds = promoDetails.category_ids
        }
      }

      console.log('Eligible product IDs:', eligibleProductIds)
      console.log('Eligible category IDs:', eligibleCategoryIds)

      // If no specific products or categories, all products are eligible
      const hasSpecificEligibility =
        eligibleProductIds.length > 0 || eligibleCategoryIds.length > 0

      // Add promo badge to eligible products
      const productCards = document.querySelectorAll('.product-card')
      productCards.forEach(card => {
        const productId = card.getAttribute('data-product-id')
        const categoryId = card.getAttribute('data-category-id')

        // Remove any existing promo badges
        const existingBadge = card.querySelector('.promo-badge')
        if (existingBadge) {
          existingBadge.remove()
        }

        // Check eligibility
        let isEligible = !hasSpecificEligibility // If no specific eligibility, all are eligible

        if (hasSpecificEligibility) {
          isEligible =
            eligibleProductIds.includes(productId) ||
            eligibleCategoryIds.includes(categoryId)
        }

        if (isEligible) {
          // Create and add promo badge
          const cardBody = card.querySelector('.card-body')
          if (cardBody) {
            const promoBadge = document.createElement('div')
            promoBadge.className =
              'promo-badge position-absolute top-0 start-0 m-2 badge bg-danger'

            let badgeText = 'PROMO'
            if (promo.type === 'percentage') {
              const promoValue = this.getPromoValue(promo)
              badgeText = promoValue ? `${promoValue}% OFF` : 'DISKON'
            } else if (promo.type === 'nominal') {
              badgeText = 'DISKON'
            } else if (promo.type === 'bundling') {
              badgeText = 'BUNDLE'
            } else if (promo.type === 'bogo') {
              badgeText = 'BUY 1 GET 1'
            }

            promoBadge.innerHTML = `<i class="fa fa-tags me-1"></i>${badgeText}`
            cardBody.appendChild(promoBadge)
          }
        }
      })

      console.log('Promo eligible products rendered')
    } catch (error) {
      console.error('Error rendering promo eligible products:', error)
    } finally {
      console.groupEnd()
    }
  },

  getPromoValue (promo) {
    // Cek berbagai kemungkinan lokasi nilai promo
    if (
      promo.details &&
      promo.details.promo &&
      promo.details.promo.promo_value
    ) {
      return promo.details.promo.promo_value
    } else if (promo.details && promo.details.promo_value) {
      return promo.details.promo_value
    } else if (promo.promo_value) {
      return promo.promo_value
    }
    return null
  },

  updateCartWithPromo (promo) {
    console.group('💰 [UPDATE CART WITH PROMO] Applying promo discount to cart')
    console.log('Promo details:', promo)
    try {
      if (!promo || !promo.isValid) {
        this.resetPromoUI()
        return
      }

      // PERBAIKAN: Deteksi jika ini adalah BUNDLING promo berdasarkan kode
      let promoType = promo.type
      if (promo.code && promo.code.toUpperCase().includes('BUNDLING')) {
        console.log(
          `Detected BUNDLING promo from code "${promo.code}" but stored as type "${promoType}". Correcting type.`
        )
        promoType = 'bundling'
      }

      // Ambil summary saat ini
      const summary = {
        ...this.state.cart.summary
      }

      // PERBAIKAN: Perhitungan diskon berbeda berdasarkan tipe promo
      const discountAmount = parseFloat(promo.discount) || 0
      const subtotalBeforeDiscount = summary.subtotal
      let totalAfterDiscount = summary.total

      // Gunakan tipe promo yang sudah dikoreksi untuk penanganan diskon
      if (promoType === 'bundling' || promoType === 'bogo') {
        // Untuk promo bundling/BOGO, simpan nilai produk gratis (informatif saja)
        summary.bundleBogoDiscount = discountAmount
        // Tidak mengurangi subtotal, pajak dan total tetap sama
        console.log('Updated bundling/BOGO promo summary:', {
          originalSubtotal: subtotalBeforeDiscount,
          bundleBogoDiscount: discountAmount,
          tax: summary.tax,
          total: totalAfterDiscount
        })
      } else {
        // Untuk promo persentase/nominal, kurangi diskon dari subtotal
        const discountedSubtotal = Math.max(
          0,
          subtotalBeforeDiscount - discountAmount
        )
        // Hitung ulang pajak dan total
        const tax = discountedSubtotal * 0.1 // 10% tax
        totalAfterDiscount = discountedSubtotal + tax

        // Update summary dengan diskon
        summary.discount = discountAmount
        summary.tax = tax
        summary.total = totalAfterDiscount
        console.log('Updated regular discount promo summary:', {
          originalSubtotal: subtotalBeforeDiscount,
          discount: discountAmount,
          discountedSubtotal: discountedSubtotal,
          tax: tax,
          newTotal: totalAfterDiscount
        })
      }

      // Update state dengan promo type yang sudah dikoreksi
      this.state.cart.promo.type = promoType
      this.state.cart.summary = summary

      // Update UI
      this.updateCartSummaryDisplay(summary, true)
      this.updatePromoInfoUI({ ...promo, type: promoType })
    } catch (error) {
      console.error('Error updating cart with promo:', error)
    } finally {
      console.groupEnd()
    }
  },

  // 5. Metode untuk reset state promo
  resetPromo () {
    this.state.cart.promo = {
      code: null,
      type: null,
      discount: 0,
      message: null,
      isValid: false,
      details: null
    }

    // Reset UI
    this.resetPromoUI()

    // Recalculate summary without discount
    const summary = this.calculateCartSummary()
    this.updateCartSummaryDisplay(summary)
  },

  // 6. Metode untuk mengupdate UI promo section
  updatePromoUI (loading = false) {
    const $promoSection = $(this.config.selectors.promoSection)
    const $promoInput = $(this.config.selectors.promoInput)
    const $applyBtn = $(this.config.selectors.promoApplyBtn)

    if (loading) {
      // Set loading state
      $applyBtn.prop('disabled', true)
      $applyBtn.html(
        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>'
      )
      $promoInput.prop('disabled', true)
    } else {
      // Reset to normal state
      $applyBtn.prop('disabled', false)
      $applyBtn.html('Apply')
      $promoInput.prop('disabled', false)
    }
  },

  updatePromoInfoUI (promo) {
    console.log('Updating promo info UI:', promo)
    // Cari elemen discount dan promo info
    const $promoInfo = $(this.config.selectors.promoInfo)
    const $discountAmount = $(this.config.selectors.discountAmount)

    // Jika promo valid, tampilkan info promo
    if (promo && promo.isValid) {
      let promoTypeText = ''
      let promoIcon = ''

      switch (promo.type) {
        case 'percentage':
          promoTypeText = 'Discount'
          promoIcon = 'fa-percent'
          break
        case 'nominal':
          promoTypeText = 'Nominal Discount'
          promoIcon = 'fa-money-bill'
          break
        case 'bundling':
          promoTypeText = 'Bundle Promo'
          promoIcon = 'fa-cube'
          break
        case 'bogo':
          promoTypeText = 'Buy One Get One'
          promoIcon = 'fa-gift'
          break
        default:
          promoTypeText = 'Discount'
          promoIcon = 'fa-tag'
      }

      const savingsLabel =
        promo.type === 'bundling' || promo.type === 'bogo'
          ? 'Nilai Produk Gratis'
          : 'Penghematan'
      const savingsInfo =
        promo.type === 'bundling' || promo.type === 'bogo'
          ? `<small class="text-info">${savingsLabel}: ${this.formatCurrency(
              promo.discount
            )}</small>`
          : `<small class="text-success">${savingsLabel}: ${this.formatCurrency(
              promo.discount
            )}</small>`

      // PERBAIKAN: Tambahkan keterangan khusus untuk promo bundling/BOGO
      const additionalInfo =
        promo.type === 'bundling' || promo.type === 'bogo'
          ? `<small class="d-block text-muted mt-1">*Produk gratis akan ditambahkan saat checkout</small>`
          : ''

      $promoInfo
        .html(
          `
		  <div class="alert ${
        promo.type === 'bundling' || promo.type === 'bogo'
          ? 'alert-info'
          : 'alert-success'
      } mb-2">
			<div class="d-flex justify-content-between align-items-center">
			  <div>
				<div class="d-flex align-items-center">
				  <span class="badge ${
            promo.type === 'bundling' || promo.type === 'bogo'
              ? 'bg-info'
              : 'bg-success'
          } me-2">
					<i class="fa ${promoIcon} me-1"></i>${promoTypeText}
				  </span>
				  <strong>${promo.code}</strong>
				</div>
				<p class="mb-0 small">${promo.message}</p>
				${savingsInfo}
				${additionalInfo}
			  </div>
			  <button class="btn btn-sm btn-outline-danger remove-promo">
				<i class="fa fa-times"></i>
			  </button>
			</div>
		  </div>
		`
        )
        .show()

      // Update discount amount
      $discountAmount.text(this.formatCurrency(promo.discount))

      // PERBAIKAN: Tampilkan baris diskon sesuai jenis promo
      if (promo.type === 'bundling' || promo.type === 'bogo') {
        // Untuk bundling/BOGO, tampilkan sebagai nilai produk gratis
        $('.bundle-bogo-discount-row').show()
        $('.discount-row').hide()
      } else {
        // Untuk percentage/nominal, tampilkan sebagai diskon reguler
        $('.discount-row').show()
        $('.bundle-bogo-discount-row').hide()
      }

      // Bind event untuk tombol remove promo
      $('.remove-promo').on('click', e => {
        e.preventDefault()
        this.resetPromo()
      })
    } else {
      this.resetPromoUI()
    }
  },

  // 8. Reset UI promo
  resetPromoUI () {
    // Reset promo info
    $(this.config.selectors.promoInfo).html('').hide()

    // Reset discount row dan amount
    $('.discount-row').hide()
    $(this.config.selectors.discountAmount).text(this.formatCurrency(0))

    // Reset input promo
    $(this.config.selectors.promoInput).val('')
  },

  showPromoSuccess (promo) {
    console.group('🎯 Promo Success Handler')
    console.log('Promo data:', promo)

    let message = ''
    let discountText = ''

    try {
      // Extract promo type dengan lebih aman
      const promoType =
        promo.type ||
        (promo.details && promo.details.promo_type) ||
        (promo.details &&
          promo.details.promo &&
          promo.details.promo.promo_type) ||
        'unknown'

      // Extract nilai promo dengan lebih aman
      let promoValue = null

      // Cek struktur promo yang berbeda-beda dalam respons
      if (
        promo.details &&
        promo.details.promo &&
        promo.details.promo.promo_value
      ) {
        promoValue = promo.details.promo.promo_value
      } else if (promo.details && promo.details.promo_value) {
        promoValue = promo.details.promo_value
      } else if (promo.promo_value) {
        promoValue = promo.promo_value
      }

      console.log('Extracted promo type:', promoType)
      console.log('Extracted promo value:', promoValue)

      switch (promoType) {
        case 'percentage':
          discountText = promoValue
            ? `${promoValue}% off`
            : 'Percentage discount'
          break
        case 'nominal':
          discountText = promoValue
            ? this.formatCurrency(promoValue)
            : 'Nominal discount'
          break
        case 'bundling':
          discountText = 'Bundle deal applied'
          break
        case 'bogo':
          discountText = 'Buy One Get One'
          break
        default:
          discountText = 'Discount applied'
      }

      message = `Promo "${promo.code}" applied successfully! ${discountText}`
    } catch (error) {
      console.error('Error in showPromoSuccess:', error)
      message = `Promo "${promo.code}" applied successfully!`
    }

    this.showNotification(message, 'success')
    console.groupEnd()
  },

  // 10. Tampilkan pesan error promo
  showPromoError (message) {
    this.showNotification(`Promo code error: ${message}`, 'error')
  },

  async validateSession () {
    console.group('🔑 [VALIDASI SESI] Memvalidasi sesi aktif')

    try {
      const params = this.getUrlParams()
      console.log('📌 [PARAMETER URL]:', params)

      const response = await $.ajax({
        url: this.config.endpoints.session,
        method: 'GET',
        data: params,
        dataType: 'json',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })

      console.log('🟢 [SERVER RESPONSE]:', response)

      if (!response.success) {
        throw new Error(response.message || 'Sesi tidak valid')
      }

      if (!response.data || !response.data.session) {
        throw new Error('Data sesi tidak ditemukan')
      }

      // Perbaikan: Validasi status sesi yang lebih fleksibel
      const sessionStatus = response.data.session.status
      console.log('📋 Session Details:', {
        id: response.data.session.id,
        status: sessionStatus,
        expireAt: response.data.session.expire_at
      })

      // Konversi status ke string dan validasi
      const normalizedStatus = String(sessionStatus).trim()
      const validStatuses = ['0', 0, '', null, undefined]

      if (!validStatuses.includes(normalizedStatus)) {
        console.warn('Invalid Session Status:', sessionStatus)
        throw new Error('Status sesi tidak valid')
      }

      console.log(
        '✅ [VALIDASI SUKSES] Sesi valid dengan ID:',
        response.data.session.id
      )
      console.groupEnd()

      return response
    } catch (error) {
      console.error('❌ [VALIDASI GAGAL]:', error)
      console.groupEnd()
      throw new Error(
        'Gagal memvalidasi sesi: ' +
          (error.message || 'Kesalahan tidak diketahui')
      )
    }
  },

  // Find package by id
  findPackageById (id) {
    return this.state.cart.packageItems.find(pkg => pkg.id === id)
  },

  findItemById (itemId) {
    if (!itemId) {
      console.warn('Empty item ID provided to findItemById')
      return null
    }

    try {
      // Normalize itemId to string for comparison
      const idStr = String(itemId)
      console.log(`Looking for item with ID: ${idStr}`)
      console.log(
        `Current state has: ${
          this.state.cart.regularItems?.length || 0
        } regular items, ${
          this.state.cart.packageItems?.length || 0
        } package items`
      )

      // Check if cart state exists and has item arrays
      if (!this.state.cart || !this.state.cart.regularItems) {
        console.warn('Cart state is incomplete or missing')
        return null
      }

      // Cari di regular items
      if (Array.isArray(this.state.cart.regularItems)) {
        const regularItem = this.state.cart.regularItems.find(
          item => String(item.id) === idStr
        )

        if (regularItem) {
          console.log('Found item in regular items:', regularItem)
          return regularItem
        }
      }

      // Cari di package items
      if (Array.isArray(this.state.cart.packageItems)) {
        // First check package headers
        const packageItem = this.state.cart.packageItems.find(
          pkg => String(pkg.id) === idStr
        )

        if (packageItem) {
          console.log('Found item in package headers:', packageItem)
          return packageItem
        }

        // Then check items inside packages
        for (const pkg of this.state.cart.packageItems) {
          if (Array.isArray(pkg.items)) {
            const item = pkg.items.find(item => String(item.id) === idStr)

            if (item) {
              console.log('Found item inside package:', item)
              return item
            }
          }
        }
      }

      console.warn(`Item with ID ${idStr} not found in cart`)
      return null
    } catch (error) {
      console.error('Error in findItemById:', error)
      return null
    }
  },

  updateEmptyState () {
    const isEmpty =
      (this.state.cart.regularItems?.length === 0 ||
        !this.state.cart.regularItems) &&
      (this.state.cart.packageItems?.length === 0 ||
        !this.state.cart.packageItems)

    // Update elemen empty state
    const emptyCartElement = document.querySelector(
      this.config.selectors.emptyCartMessage
    )
    if (emptyCartElement) {
      emptyCartElement.style.display = isEmpty ? 'block' : 'none'
    }

    // Nonaktifkan tombol checkout
    const checkoutButton = document.querySelector(
      this.config.selectors.checkoutButton
    )
    if (checkoutButton) {
      checkoutButton.disabled = isEmpty
    }

    // PERBAIKAN: Jika cart kosong, reset summary display ke 0
    if (isEmpty) {
      const emptySummary = {
        regularCount: 0,
        packageCount: 0,
        regularSubtotal: 0,
        packageSubtotal: 0,
        subtotal: 0,
        tax: 0,
        total: 0
      }
      this.updateCartSummaryDisplay(emptySummary)
    }

    // Update cart count
    this.updateCartCount()

    console.log('Empty state updated. Cart is empty:', isEmpty)
  },

  async handleRemovePackage (e) {
    try {
      e.preventDefault()
      const packageId = $(e.currentTarget).data('package-id')

      const result = await Swal.fire({
        title: 'Hapus Paket',
        text: 'Apakah Anda yakin ingin menghapus paket ini beserta semua isinya?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true
      })

      if (result.isConfirmed) {
        await this.removePackage(packageId)
      }
    } catch (error) {
      this.handleError(error)
    }
  },

  async handleCheckout () {
    try {
      // PERBAIKAN: Tambahkan flag untuk mencegah klik ganda
      if (this._isCheckingOut) {
        console.log('Checkout already in progress, ignoring duplicate click')
        return
      }

      this._isCheckingOut = true

      // PERBAIKAN: Nonaktifkan tombol checkout
      const $checkoutButton = $(this.config.selectors.checkoutButton)
      $checkoutButton.prop('disabled', true)
      $checkoutButton.html(
        '<span class="spinner-border spinner-border-sm me-2"></span>Processing...'
      )

      // Validate cart is not empty
      if (
        this.state.cart.regularItems.length === 0 &&
        this.state.cart.packageItems.length === 0
      ) {
        throw new Error('Cart is empty')
      }

      // PERBAIKAN: Validasi status sesi terlebih dahulu
      console.log('Validating session before checkout')
      const sessionResponse = await this.validateSession()
      console.log('Session validation response:', sessionResponse)

      if (
        !sessionResponse.success ||
        !sessionResponse.data ||
        !sessionResponse.data.session
      ) {
        throw new Error('Invalid session or session expired')
      }

      const sessionStatus = sessionResponse.data.session.status
      const lastModified = sessionResponse.data.session.updated_at
      console.log('Current session status:', sessionStatus)

      // Jika status sudah ORDER, tidak bisa checkout lagi
      if (sessionStatus === '1' || sessionStatus === 1) {
        this.showError(
          'Order Already Processed',
          'This order has already been processed. Please start a new session to place a new order.'
        )
        return
      }

      const result = await Swal.fire({
        title: 'Konfirmasi Pesanan',
        text: 'Apakah Anda siap untuk melakukan pemesanan?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, pesan sekarang',
        cancelButtonText: 'Batal',
        reverseButtons: true
      })

      if (result.isConfirmed) {
        // PERBAIKAN: Apply promo code if valid before proceeding with checkout
        if (this.state.cart.promo && this.state.cart.promo.isValid) {
          try {
            // PERBAIKAN: Gunakan Promise untuk menangani error dengan lebih baik
            await this.applyPromoToOrder(
              sessionResponse.data.session.id,
              this.state.cart.promo.code
            )

            // PERBAIKAN: Beri waktu untuk promo diproses
            if (
              this.state.cart.promo.type === 'bundling' ||
              this.state.cart.promo.type === 'bogo'
            ) {
              // Tunjukkan loading
              this.showLoading(true)
              // Refresh cart untuk update dengan item gratis
              window.forceCartRefresh = true
              // Tunggu sebentar untuk refresh cart selesai
              try {
                await this.loadCart(false)
                // Lanjutkan dengan checkout setelah refresh
                this.showLoading(false)
                await this.processCheckout(lastModified)
              } catch (refreshError) {
                console.error('Error refreshing cart:', refreshError)
                this.showLoading(false)

                // PERBAIKAN: Tanyakan kepada user apakah ingin melanjutkan checkout
                const continueResult = await Swal.fire({
                  icon: 'warning',
                  title: 'Kesalahan Refresh',
                  text: 'Gagal merefresh keranjang setelah menerapkan promo. Apakah Anda ingin melanjutkan checkout?',
                  showCancelButton: true,
                  confirmButtonText: 'Ya, Lanjutkan',
                  cancelButtonText: 'Batal'
                })

                if (continueResult.isConfirmed) {
                  await this.processCheckout(lastModified)
                }
              }
              return
            }
          } catch (promoError) {
            // PERBAIKAN: Tangani error 422 dan error lainnya dengan lebih baik
            console.error('Error applying promo:', promoError)

            // Ekstrak pesan error yang lebih jelas
            let errorMessage = 'Unknown error'
            if (promoError.message) {
              errorMessage = promoError.message
            } else if (
              promoError.responseJSON &&
              promoError.responseJSON.message
            ) {
              errorMessage = promoError.responseJSON.message
            }

            // Tampilkan pesan error dan tanyakan user apakah ingin melanjutkan
            const continueWithoutPromo = await Swal.fire({
              title: 'Kesalahan Promo',
              html: `<p>Gagal menerapkan kode promo: ${errorMessage}</p><p>Apakah Anda ingin melanjutkan checkout tanpa promo?</p>`,
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Ya, Lanjutkan Tanpa Promo',
              cancelButtonText: 'Batal'
            })

            if (!continueWithoutPromo.isConfirmed) {
              // Batalkan checkout jika user tidak ingin melanjutkan
              throw new Error('Checkout dibatalkan oleh pengguna')
            }

            // Reset promo state jika user memilih untuk lanjut
            this.state.cart.promo = {
              code: null,
              type: null,
              discount: 0,
              message: null,
              isValid: false,
              details: null
            }
          }
        }

        // Proceed with checkout
        await this.processCheckout(lastModified)
      }
    } catch (error) {
      console.error('Checkout error:', error)

      // PERBAIKAN: Tampilkan pesan error yang lebih jelas
      let errorMessage = error.message || 'An unknown error occurred'
      if (error.responseJSON && error.responseJSON.message) {
        errorMessage = error.responseJSON.message
      }

      this.showError('Checkout Gagal', errorMessage)
    } finally {
      // PERBAIKAN: Reset flag dan kembalikan tombol ke kondisi semula
      this._isCheckingOut = false

      const $checkoutButton = $(this.config.selectors.checkoutButton)
      $checkoutButton.prop('disabled', false)
      $checkoutButton.html('<i class="fas fa-shopping-cart me-2"></i>Checkout')
    }
  },

  async processCheckout (lastModified) {
    try {
      this.showLoading(true)
      console.group('🛒 Checkout Process Debug')
      console.log('Session Validation Start')

      // PERBAIKAN: Persiapkan payload checkout dengan sessionId dan lastModified
      const checkoutPayload = {
        outletId: this.getUrlParams().outletId,
        tableId: this.getUrlParams().tableId,
        brand: this.getUrlParams().brand,
        lastModified: lastModified, // PERBAIKAN: Tambahkan timestamp untuk optimistic concurrency
        // PERBAIKAN: Tambahkan informasi promo jika ada
        promo:
          this.state.cart.promo && this.state.cart.promo.isValid
            ? {
                code: this.state.cart.promo.code,
                type: this.state.cart.promo.type,
                discount: this.state.cart.promo.discount
              }
            : null
      }

      console.log('Checkout Payload:', checkoutPayload)

      // PERBAIKAN: Kirim request dengan timeout yang lebih lama dan retry
      let retries = 0
      const maxRetries = 2
      let response

      while (retries <= maxRetries) {
        try {
          response = await $.ajax({
            url: `${window.location.origin}/order/done`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(checkoutPayload),
            dataType: 'json',
            timeout: 30000, // 30 seconds timeout
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Cache-Control': 'no-cache, no-store, must-revalidate'
            }
          })
          break // Exit loop if successful
        } catch (retryError) {
          retries++
          if (retries > maxRetries) throw retryError
          // Wait before retrying
          await new Promise(resolve => setTimeout(resolve, 2000 * retries))
          console.log(`Retrying checkout (${retries}/${maxRetries})...`)
        }
      }

      console.log('Checkout Response:', response)

      // Validasi respons
      if (!response.success) {
        throw new Error(response.message || 'Checkout gagal')
      }

      // Proses checkout sukses
      await this.handleSuccessfulCheckout(response)
      console.groupEnd()
    } catch (error) {
      console.error('Checkout Error:', error)

      // PERBAIKAN: Handling khusus untuk konflik optimistic concurrency
      if (error.status === 409) {
        // Status 409 Conflict - ada user lain yang sudah mengubah order
        Swal.fire({
          icon: 'warning',
          title: 'Order Telah Berubah',
          text: 'Order ini telah diubah oleh pengguna lain. Silakan muat ulang keranjang Anda untuk melihat perubahan terbaru.',
          confirmButtonText: 'Muat Ulang Keranjang',
          showCancelButton: true,
          cancelButtonText: 'Batal'
        }).then(result => {
          if (result.isConfirmed) {
            // Muat ulang keranjang
            this.loadCart(false).then(() => {
              // Tampilkan modal keranjang dengan data terbaru
              $(this.config.selectors.cartModal).modal('show')
            })
          }
        })
      } else {
        this.handleCheckoutError(error)
      }
    } finally {
      // Sembunyikan loading
      this.showLoading(false)
    }
  },

  validatePromoCode (promoCode) {
    console.group('🏷️ [VALIDATE PROMO] Validating promo code')
    console.log('Promo code to validate:', promoCode)

    try {
      // Set loading state
      this.state.ui.promoLoading = true
      this.updatePromoUI(true)

      // Ambil parameter yang diperlukan
      const params = this.getUrlParams()

      // PERBAIKAN: Lebih baik mengambil detail keranjang untuk validasi yang lebih akurat
      // termasuk harga dan kuantitas
      const cartDetails = []

      // Collect regular items
      if (this.state.cart && this.state.cart.regularItems) {
        this.state.cart.regularItems.forEach(item => {
          if (item.product_id) {
            cartDetails.push({
              product_id: item.product_id,
              price: parseFloat(item.price) || 0,
              quantity: parseInt(item.quantity) || 0,
              subtotal: parseFloat(item.subtotal) || 0
            })
          }
        })
      }

      // Collect package items (include package item prices)
      if (this.state.cart && this.state.cart.packageItems) {
        this.state.cart.packageItems.forEach(pkg => {
          if (pkg.items && Array.isArray(pkg.items)) {
            pkg.items.forEach(item => {
              if (item.product_id) {
                cartDetails.push({
                  product_id: item.product_id,
                  price: parseFloat(item.price) || 0,
                  quantity: parseInt(item.quantity) || 0,
                  subtotal: parseFloat(item.subtotal) || 0
                })
              }
            })
          }
        })
      }

      const orderTotal = this.state.cart.summary.subtotal || 0

      // PERBAIKAN: Buat struktur payload yang lebih informatif untuk validasi
      const payload = {
        promo_code: promoCode,
        order_total: orderTotal,
        brand: params.brand,
        cart_details: cartDetails // Include detailed cart information
      }

      console.log('Validation payload:', payload)
      console.log('Cart details provided:', cartDetails.length, 'items')

      // PERBAIKAN: Gunakan Promise untuk penanganan error yang lebih baik
      return new Promise((resolve, reject) => {
        $.ajax({
          url: `${window.location.origin}${this.config.endpoints.validatePromo}`,
          method: 'POST',
          contentType: 'application/json',
          data: JSON.stringify(payload),
          dataType: 'json',
          timeout: 10000, // 10 detik timeout
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache, no-store, must-revalidate'
          },
          success: response => {
            console.log('Promo validation response:', response)

            if (response.success) {
              // Normalisasi data promo untuk memastikan struktur yang konsisten
              const normalizedPromo = this.normalizePromoData(
                response,
                promoCode
              )

              // PERBAIKAN: Tambahkan log tambahan untuk debugging
              console.log('Normalized promo data:', normalizedPromo)
              console.log('Eligible amount:', response.eligible_amount)
              console.log('Original order total:', response.original_amount)

              // Update state promo
              this.state.cart.promo = normalizedPromo

              // Update UI dengan diskon
              this.updateCartWithPromo(normalizedPromo)

              // Tampilkan pesan sukses
              this.showPromoSuccess(normalizedPromo)

              // Jika promo tipe bundling atau BOGO, tampilkan pesan khusus
              if (
                normalizedPromo.type === 'bundling' ||
                normalizedPromo.type === 'bogo'
              ) {
                this.showNotification(
                  'Klik "Order" untuk mendapatkan produk gratis dari promo ini',
                  'info'
                )
              }

              // PERBAIKAN: Jika promo spesifik untuk produk/kategori, tampilkan info tambahan
              if (response.product_specific === true) {
                this.showNotification(
                  `Promo ini hanya berlaku untuk produk spesifik dengan total belanja Rp ${this.formatPrice(
                    response.eligible_amount
                  )}`,
                  'info'
                )
              }

              // PERBAIKAN: Tampilkan produk yang memenuhi syarat untuk promo
              this.renderPromoEligibleProducts()

              // PERBAIKAN: Trigger custom event untuk memberitahu komponen lain tentang promo
              $(document).trigger('promoApplied', [normalizedPromo])

              resolve(true)
            } else {
              // Reset state promo
              this.state.cart.promo = {
                code: null,
                type: null,
                discount: 0,
                message: response.message || 'Invalid promo code',
                isValid: false,
                details: null
              }

              // PERBAIKAN: Menambahkan informasi spesifik jika ini masalah minimum order untuk produk tertentu
              if (
                response.minimum_order &&
                response.eligible_amount !== undefined
              ) {
                // Ini kasus khusus: kode promo valid tapi belum mencapai minimum order untuk produk spesifik
                this.showPromoError(
                  `Minimum pembelian Rp ${this.formatPrice(
                    response.minimum_order
                  )} untuk produk yang memenuhi syarat promo. Total produk yang memenuhi syarat saat ini: Rp ${this.formatPrice(
                    response.eligible_amount
                  )}`
                )
              } else {
                // Tampilkan pesan error umum
                this.showPromoError(response.message || 'Invalid promo code')
              }

              resolve(false)
            }
          },
          error: (xhr, status, error) => {
            console.error('Promo validation error:', xhr)

            // PERBAIKAN: Tambahkan penanganan respons non-JSON
            let errorMsg = 'Error validating promo code'

            try {
              if (xhr.responseJSON) {
                errorMsg = xhr.responseJSON.message || errorMsg
              } else if (xhr.responseText) {
                // Periksa apakah respons adalah HTML (error page)
                if (xhr.responseText.trim().startsWith('<')) {
                  errorMsg =
                    'Server returned an error page. Your session might have expired.'
                } else {
                  // Coba parse sebagai JSON
                  try {
                    const parsedError = JSON.parse(xhr.responseText)
                    errorMsg = parsedError.message || errorMsg
                  } catch (e) {
                    // Respons bukan JSON yang valid
                    errorMsg = 'Invalid server response'
                  }
                }
              }
            } catch (e) {
              console.error('Error parsing error response:', e)
            }

            // Reset state promo dan tampilkan error
            this.state.cart.promo = {
              code: null,
              type: null,
              discount: 0,
              message: errorMsg,
              isValid: false,
              details: null
            }

            this.showPromoError(errorMsg)
            reject(xhr)
          },
          complete: () => {
            // Reset loading state
            this.state.ui.promoLoading = false
            this.updatePromoUI(false)
            console.groupEnd()
          }
        })
      })
    } catch (error) {
      console.error('Promo validation error:', error)

      // Reset state promo dan tampilkan error
      this.state.cart.promo = {
        code: null,
        type: null,
        discount: 0,
        message: error.message || 'Error validating promo code',
        isValid: false,
        details: null
      }

      this.showPromoError(error.message || 'Error validating promo code')
      this.state.ui.promoLoading = false
      this.updatePromoUI(false)
      console.groupEnd()
      return Promise.reject(error)
    }
  },

  async applyPromoToOrder (orderId, promoCode) {
    console.group('🏷️ [APPLY PROMO] Applying promo to order')
    console.log('Order ID:', orderId)
    console.log('Promo code:', promoCode)

    try {
      // Validasi parameter
      if (!orderId || !promoCode) {
        throw new Error('Order ID and promo code are required')
      }

      // PERBAIKAN: Cek jika promo code mengandung string bundling
      const isBundlingPromo = promoCode.toUpperCase().includes('BUNDLING')

      // Ambil parameter yang diperlukan
      const params = this.getUrlParams()

      // PERBAIKAN: Persiapkan payload dengan format yang benar
      const payload = {
        orderId: orderId,
        promoCode: promoCode,
        brand: params.brand,
        forceBundlingType: isBundlingPromo, // Tambahkan flag untuk server
        // PERBAIKAN: Tambahkan detail order jika diperlukan
        orderDetails: {
          outletId: params.outletId,
          tableId: params.tableId
        }
      }

      console.log('Apply promo payload:', payload)

      // PERBAIKAN: Handle error 422 secara khusus
      try {
        // Kirim request ke server dengan timeout yang lebih panjang
        const response = await $.ajax({
          url: `${window.location.origin}/order/applyPromoToOrder`,
          method: 'POST',
          contentType: 'application/json',
          data: JSON.stringify(payload),
          dataType: 'json',
          timeout: 15000, // 15 detik timeout
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache, no-store, must-revalidate'
          }
        })

        console.log('Apply promo response:', response)

        if (response.success) {
          // Update state dengan promo yang diterapkan
          if (response.data && response.data.promo) {
            // PERBAIKAN: Override tipe promo jika ini adalah promo BUNDLING
            let promoType =
              response.data.promo.promo_type || response.promo_type
            if (isBundlingPromo) {
              console.log(
                `Detected BUNDLING promo from code "${promoCode}". Ensuring type is set to "bundling".`
              )
              promoType = 'bundling'
            }

            this.state.cart.promo = {
              code: promoCode,
              type: promoType,
              discount: response.discount_amount,
              message: response.message || 'Promo applied successfully',
              isValid: true,
              details: response.data.promo
            }

            // Update UI dengan diskon
            const summary = this.calculateCartSummary()
            this.updateCartSummaryDisplay(summary, true)

            // Trigger custom event untuk memperbarui produk yang eligible
            $(document).trigger('promoApplied', [this.state.cart.promo])

            // Tampilkan notifikasi sukses
            this.showNotification(
              'Promo berhasil diterapkan ke order',
              'success'
            )
            console.log('Promo successfully applied to order')
          }
          return response
        } else {
          throw new Error(response.message || 'Failed to apply promo to order')
        }
      } catch (ajaxError) {
        // PERBAIKAN: Ekstrak pesan error dari respons 422
        let errorMessage = 'Failed to apply promo to order'

        if (ajaxError.status === 422 && ajaxError.responseJSON) {
          // Error validasi dari server
          errorMessage = ajaxError.responseJSON.message || 'Invalid promo data'
          console.error(
            'Validation error details:',
            ajaxError.responseJSON.errors || {}
          )
        } else if (ajaxError.responseJSON && ajaxError.responseJSON.message) {
          // Error umum dengan pesan
          errorMessage = ajaxError.responseJSON.message
        }

        throw new Error(errorMessage)
      }
    } catch (error) {
      console.error('Error applying promo to order:', error)
      this.showNotification(
        'Gagal menerapkan promo: ' + (error.message || 'Unknown error'),
        'error'
      )
      throw error
    } finally {
      console.groupEnd()
    }
  },

  // Metode baru untuk mempersiapkan detail keranjang
  prepareCartDetailsForPromo () {
    const cartDetails = []

    // Tambahkan item reguler
    if (this.state.cart && this.state.cart.regularItems) {
      this.state.cart.regularItems.forEach(item => {
        cartDetails.push({
          product_id: item.product_id,
          price: parseFloat(item.price) || 0,
          quantity: parseInt(item.quantity) || 0,
          subtotal: parseFloat(item.subtotal) || 0
        })
      })
    }

    // Tambahkan item paket
    if (this.state.cart && this.state.cart.packageItems) {
      this.state.cart.packageItems.forEach(pkg => {
        if (pkg.items && Array.isArray(pkg.items)) {
          pkg.items.forEach(item => {
            cartDetails.push({
              product_id: item.product_id,
              price: parseFloat(item.price) || 0,
              quantity: parseInt(item.quantity) || 0,
              subtotal: parseFloat(item.subtotal) || 0
            })
          })
        }
      })
    }

    return cartDetails
  },

  normalizePromoData (response, promoCode) {
    console.group('🔄 Normalizing Promo Data')
    console.log('Raw response:', response)

    try {
      // Nilai default
      const normalized = {
        code: promoCode,
        type: 'unknown',
        discount: 0,
        message: 'Promo applied',
        isValid: true,
        details: null
      }

      // Ekstrak tipe promo
      if (response.promo_type) {
        normalized.type = response.promo_type
      } else if (response.data && response.data.promo_type) {
        normalized.type = response.data.promo_type
      } else if (response.promo && response.promo.promo_type) {
        normalized.type = response.promo.promo_type
      } else if (
        response.data &&
        response.data.promo &&
        response.data.promo.promo_type
      ) {
        normalized.type = response.data.promo.promo_type
      }

      // Ekstrak jumlah diskon
      if (typeof response.discount_amount !== 'undefined') {
        normalized.discount = parseFloat(response.discount_amount)
      } else if (
        response.data &&
        typeof response.data.discount_amount !== 'undefined'
      ) {
        normalized.discount = parseFloat(response.data.discount_amount)
      }

      // Ekstrak pesan
      if (response.message) {
        normalized.message = response.message
      }

      // Simpan seluruh data respons untuk referensi
      if (response.data) {
        normalized.details = response.data
      } else if (response.promo) {
        normalized.details = { promo: response.promo }
      } else {
        normalized.details = response
      }

      console.log('Normalized promo data:', normalized)
      return normalized
    } catch (error) {
      console.error('Error normalizing promo data:', error)

      // Return promo minimal sebagai fallback
      return {
        code: promoCode,
        type: 'unknown',
        discount: 0,
        message: 'Promo applied with errors',
        isValid: true,
        details: response
      }
    } finally {
      console.groupEnd()
    }
  },

  async showPromoSuggestions () {
    console.group('🎯 [PROMO SUGGESTIONS] Menampilkan saran promo')
    try {
      // Persiapkan payload dengan item dari keranjang
      const params = this.getUrlParams()
      const cartDetails = this.prepareCartDetailsForPromo()

      // Jika tidak ada item dalam keranjang, tidak perlu menampilkan saran
      if (cartDetails.length === 0) {
        console.log('Cart is empty, no promo suggestions needed')
        return
      }

      console.log('Cart details for promo suggestions:', cartDetails)

      // Ambil subtotal
      const orderTotal = this.state.cart.summary.subtotal || 0

      // Kirim request untuk mendapatkan saran promo
      const response = await $.ajax({
        url: `${window.location.origin}/order/getPromoSuggestions`,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
          brand: params.brand,
          order_total: orderTotal,
          cart_details: cartDetails
        }),
        timeout: 10000,
        dataType: 'json'
      })

      console.log('Promo suggestions response:', response)

      if (
        response.success &&
        response.data &&
        response.data.suggestions.length > 0
      ) {
        // Render saran promo
        this.renderPromoSuggestions(response.data.suggestions)
      } else {
        console.log('No promo suggestions available')
        // Sembunyikan bagian saran promo jika tidak ada saran
        $('.promo-suggestions').hide()
      }
    } catch (error) {
      console.error('Error getting promo suggestions:', error)
    } finally {
      console.groupEnd()
    }
  },

  renderPromoSuggestions (suggestions) {
    console.group('🎯 Rendering Improved Promo Suggestions')
    console.log('Available promotions:', suggestions)
    try {
      // Buat HTML untuk header saran promo
      let suggestionsHTML = ` 
        <div class="promo-suggestions-header mb-3"> 
            <h6 class="d-flex align-items-center mb-2"> 
                <i class="fa fa-tags me-2 text-primary"></i> 
                <span>Promo Tersedia</span> 
            </h6> 
            <p class="text-muted small mb-0">Klik pada kartu promo untuk melihat detail atau menerapkan</p> 
        </div> 
        <div class="promo-suggestions-list"> `

      // Sort suggestions - show eligible first, then almost, then others
      suggestions.sort((a, b) => {
        const eligibilityOrder = {
          eligible: 0,
          almost: 1,
          missing_products: 2
        }
        const orderA = eligibilityOrder[a.eligibility] ?? 999
        const orderB = eligibilityOrder[b.eligibility] ?? 999
        return orderA - orderB
      })

      // Add each suggestion as an interactive card
      suggestions.forEach(suggestion => {
        // Determine badge styling based on eligibility
        let statusClass = 'bg-success'
        let statusIcon = 'fa-check-circle'
        let statusText = 'Siap Digunakan'
        if (suggestion.eligibility === 'almost') {
          statusClass = 'bg-warning text-dark'
          statusIcon = 'fa-exclamation-circle'
          statusText = 'Hampir Memenuhi'
        } else if (suggestion.eligibility === 'missing_products') {
          statusClass = 'bg-info'
          statusIcon = 'fa-info-circle'
          statusText = 'Produk Kurang'
        }

        // Format promo value based on type
        let promoValueText = ''
        let promoTypeClass = ''
        let promoTypeIcon = ''
        switch (suggestion.type) {
          case 'percentage':
            promoValueText = `${suggestion.value}%`
            promoTypeClass = 'bg-danger'
            promoTypeIcon = 'fa-percent'
            if (
              suggestion.additional_info &&
              suggestion.additional_info.max_discount
            ) {
              promoValueText += ` (maks ${this.formatCurrency(
                suggestion.additional_info.max_discount
              )})`
            }
            break
          case 'nominal':
            promoValueText = this.formatCurrency(suggestion.value)
            promoTypeClass = 'bg-primary'
            promoTypeIcon = 'fa-tag'
            break
          case 'bundling':
            promoTypeClass = 'bg-info'
            promoTypeIcon = 'fa-boxes-stacked'
            break
          case 'bogo':
            promoTypeClass = 'bg-success'
            promoTypeIcon = 'fa-gift'
            break
          default:
            promoTypeClass = 'bg-secondary'
            promoTypeIcon = 'fa-tags'
        }

        // Determine action based on eligibility
        const canApply = suggestion.eligibility === 'eligible'
        const hasMissingProducts =
          suggestion.eligibility === 'missing_products' &&
          suggestion.additional_info &&
          suggestion.additional_info.missing_products

        // Create data attributes for missing products if available
        const missingProductsAttr = hasMissingProducts
          ? `data-missing-products='${JSON.stringify(
              suggestion.additional_info.missing_products
            )}'`
          : ''

        // Create a data attribute for applying promo
        const promoCodeAttr = `data-promo-code="${suggestion.code}"`

        // Determine which action to perform when card is clicked
        const cardAction = canApply
          ? 'apply-promo'
          : hasMissingProducts
          ? 'view-products'
          : ''

        // Create the improved promo card
        suggestionsHTML += ` 
                <div class="promo-suggestion-card ${cardAction}" ${promoCodeAttr} ${missingProductsAttr}> 
                    <div class="card border-0 shadow-sm mb-3 promo-card-${
                      suggestion.type
                    }"> 
                        <div class="card-body p-3"> 
                            <div class="d-flex justify-content-between align-items-start mb-2"> 
                                <!-- Promo Header --> 
                                <div class="promo-header"> 
                                    <div class="d-flex align-items-center mb-1"> 
                                        <span class="badge ${promoTypeClass} me-2"> 
                                            <i class="fa ${promoTypeIcon} me-1"></i> 
                                            ${this.getPromoTypeLabel(
                                              suggestion.type
                                            )} 
                                        </span> 
                                        <h6 class="mb-0 fw-bold">${
                                          suggestion.code
                                        }</h6> 
                                        <span class="badge ${statusClass} ms-2"> 
                                            <i class="fa ${statusIcon} me-1"></i> 
                                            ${statusText} 
                                        </span> 
                                    </div> 
                                    <p class="small text-muted mt-2 mb-1">${
                                      suggestion.description
                                    }</p> 
                                    ${
                                      promoValueText
                                        ? `<div class="mt-1 text-primary fw-bold">${promoValueText}</div>`
                                        : ''
                                    } 
                                </div> 
                            </div> 
                            <!-- Promo Details if available --> 
                            ${this.renderAdditionalPromoInfo(suggestion)} 
                            <!-- Promo Action Button --> 
                            <div class="mt-3 text-end"> 
                                ${
                                  canApply
                                    ? `<span class="btn-action apply-promo"> 
                                            <i class="fa fa-check-circle me-1"></i>Terapkan 
                                        </span>`
                                    : hasMissingProducts
                                    ? `<span class="btn-action view-products"> 
                                                <i class="fa fa-eye me-1"></i>Lihat Produk 
                                            </span>`
                                    : `<span class="btn-action disabled"> 
                                                <i class="fa fa-exclamation-triangle me-1"></i>Belum Tersedia 
                                            </span>`
                                } 
                            </div> 
                        </div> 
                    </div> 
                </div> `
      })

      suggestionsHTML += `</div>`

      // Add CSS for improved promo cards
      suggestionsHTML += ` 
        <style> 
            /* Enhanced Promo Suggestions Styling */ 
            .promo-suggestions { 
                margin-bottom: 1.5rem; 
                border-radius: 0.5rem; 
                overflow: hidden; 
            } 
            .promo-suggestion-card { 
                cursor: pointer; 
                transition: transform 0.2s ease, box-shadow 0.2s ease; 
            } 
            .promo-suggestion-card:hover { 
                transform: translateY(-2px); 
            } 
            .promo-suggestion-card .card { 
                border-radius: 0.75rem; 
                transition: all 0.3s ease; 
                border-left: 4px solid transparent; 
            } 
            /* Card styling based on promo type */ 
            .promo-card-percentage { 
                border-left-color: var(--danger) !important; 
            } 
            .promo-card-nominal { 
                border-left-color: var(--primary) !important; 
            } 
            .promo-card-bundling { 
                border-left-color: var(--info) !important; 
            } 
            .promo-card-bogo { 
                border-left-color: var(--success) !important; 
            } 
            /* Action button styling */ 
            .btn-action { 
                display: inline-block; 
                padding: 0.375rem 0.75rem; 
                border-radius: 0.375rem; 
                font-size: 0.875rem; 
                font-weight: 500; 
                transition: all 0.2s ease; 
            } 
            .apply-promo { 
                color: white; 
                background-color: var(--primary); 
            } 
            .apply-promo:hover { 
                background-color: var(--primary-dark); 
            } 
            .view-products { 
                color: var(--primary); 
                background-color: rgba(98, 65, 30, 0.1); 
            } 
            .view-products:hover { 
                background-color: rgba(98, 65, 30, 0.2); 
            } 
            .btn-action.disabled { 
                color: var(--gray-600); 
                background-color: var(--gray-200); 
                cursor: not-allowed; 
            } 
            /* Promo details styling */ 
            .promo-detail-box { 
                background-color: var(--gray-100); 
                border-radius: 0.5rem; 
                padding: 0.75rem; 
                margin-top: 0.75rem; 
                font-size: 0.85rem; 
            } 
            .promo-detail-item { 
                padding: 0.5rem; 
                border-radius: 0.375rem; 
                background-color: white; 
                margin-bottom: 0.5rem; 
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); 
            } 
            .promo-detail-item:last-child { 
                margin-bottom: 0; 
            } 
            /* Missing products modal styling */ 
            .missing-products-list { 
                max-height: 300px; 
                overflow-y: auto; 
                margin-top: 1rem; 
            } 
            .missing-product-item { 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                padding: 0.75rem; 
                border-bottom: 1px solid var(--gray-200); 
            } 
            .missing-product-item:last-child { 
                border-bottom: none; 
            } 
            /* Responsive adjustments */ 
            @media (max-width: 767px) { 
                .promo-header { 
                    flex-direction: column; 
                } 
                .btn-action { 
                    display: block; 
                    text-align: center; 
                    width: 100%; 
                } 
            } 
        </style> `

      // Tampilkan di UI
      $('.promo-suggestions').html(suggestionsHTML).show()

      // Bind event untuk kartu promo yang dapat diklik
      this.bindPromoCardEvents()
      console.log('✅ Promo suggestions rendered successfully')
    } catch (error) {
      console.error('Error rendering promo suggestions:', error)
    } finally {
      console.groupEnd()
    }
  },

  bindPromoCardEvents () {
    // Remove existing event handlers to prevent duplicates
    $(document).off('click', '.promo-suggestion-card.apply-promo')
    $(document).off('click', '.promo-suggestion-card.view-products')

    // Add event for apply promo cards
    $(document).on('click', '.promo-suggestion-card.apply-promo', e => {
      const card = $(e.currentTarget)
      const promoCode = card.data('promo-code')

      if (promoCode) {
        console.log('Applying promo code:', promoCode)
        // Check if we have an input field
        const promoInput = $(this.config.selectors.promoInput)
        if (promoInput.length) {
          promoInput.val(promoCode)
          // Trigger apply button
          $(this.config.selectors.promoApplyBtn).click()
        }
      }
    })

    // Add event for view products cards
    $(document).on('click', '.promo-suggestion-card.view-products', e => {
      const card = $(e.currentTarget)
      const missingProducts = card.data('missing-products')

      if (missingProducts && Array.isArray(missingProducts)) {
        console.log('Showing missing products:', missingProducts)
        // Show missing products modal
        this.showEnhancedMissingProductsModal(missingProducts)
      }
    })
  },

  renderAdditionalPromoInfo (suggestion) {
    let html = ''

    // BOGO details
    if (
      suggestion.type === 'bogo' &&
      suggestion.additional_info &&
      suggestion.additional_info.bogo_details
    ) {
      html += '<div class="promo-detail-box">'
      html +=
        '<h6 class="mb-2 fw-bold small text-success"><i class="fa fa-gift me-2"></i>Detail Produk Gratis</h6>'
      suggestion.additional_info.bogo_details.forEach(bogo => {
        html += `
                <div class="promo-detail-item">
                    <div class="d-flex align-items-center">
                        <div class="me-auto">
                            <span class="fw-bold">${bogo.buy_product_name}</span> × ${bogo.buy_quantity}
                            <i class="fa fa-arrow-right mx-1"></i>
                            <span class="text-success">
                                <i class="fa fa-gift me-1"></i>
                                <span class="fw-bold">${bogo.free_product_name}</span> × ${bogo.free_quantity}
                            </span>
                        </div>
                    </div>
                </div>
            `
      })
      html += '</div>'
    }

    // Bundle details
    if (
      suggestion.type === 'bundling' &&
      suggestion.additional_info &&
      suggestion.additional_info.bundle_details
    ) {
      html += '<div class="promo-detail-box">'
      html +=
        '<h6 class="mb-2 fw-bold small text-info"><i class="fa fa-boxes-stacked me-2"></i>Detail Bundling</h6>'
      suggestion.additional_info.bundle_details.forEach(bundle => {
        html += `
                <div class="promo-detail-item">
                    <div class="d-flex align-items-center">
                        <div class="me-auto">
                            <span class="fw-bold">${bundle.product1_name}</span> × ${bundle.product1_qty}
                            <span class="mx-1">+</span>
                            <span class="fw-bold">${bundle.product2_name}</span> × ${bundle.product2_qty}
                            <i class="fa fa-arrow-right mx-1"></i>
                            <span class="text-success">
                                <i class="fa fa-gift me-1"></i>
                                <span class="fw-bold">${bundle.free_product_name}</span> × ${bundle.free_quantity}
                            </span>
                        </div>
                    </div>
                </div>
            `
      })
      html += '</div>'
    }

    return html
  },

  showEnhancedMissingProductsModal (products) {
    let productsHTML = ''

    // Group products by category if possible
    const groupedProducts = {}
    let hasCategories = false

    products.forEach(product => {
      if (product.category_name) {
        hasCategories = true
        if (!groupedProducts[product.category_name]) {
          groupedProducts[product.category_name] = []
        }
        groupedProducts[product.category_name].push(product)
      } else {
        if (!groupedProducts['Lainnya']) {
          groupedProducts['Lainnya'] = []
        }
        groupedProducts['Lainnya'].push(product)
      }
    })

    if (hasCategories) {
      // Generate HTML for grouped products
      Object.keys(groupedProducts).forEach(category => {
        productsHTML += `
                <div class="missing-products-category mb-3">
                    <h6 class="fw-bold mb-2">${category}</h6>
                    <div class="missing-products-group">
            `

        groupedProducts[category].forEach(product => {
          productsHTML += `
                    <div class="missing-product-item">
                        <div>
                            <strong>${product.name}</strong>
                            ${
                              product.description
                                ? `<small class="d-block text-muted">${product.description}</small>`
                                : ''
                            }
                        </div>
                        <div class="text-primary fw-bold">
                            ${this.formatCurrency(product.price)}
                        </div>
                    </div>
                `
        })

        productsHTML += `
                    </div>
                </div>
            `
      })
    } else {
      // Simple list for ungrouped products
      products.forEach(product => {
        productsHTML += `
                <div class="missing-product-item">
                    <div>
                        <strong>${product.name}</strong>
                        ${
                          product.description
                            ? `<small class="d-block text-muted">${product.description}</small>`
                            : ''
                        }
                    </div>
                    <div class="text-primary fw-bold">
                        ${this.formatCurrency(product.price)}
                    </div>
                </div>
            `
      })
    }

    // Show improved modal with product info
    Swal.fire({
      title: 'Produk Yang Diperlukan',
      html: `
            <div class="text-start">
                <p>Tambahkan produk berikut untuk mendapatkan promo:</p>
                <div class="missing-products-list">
                    ${productsHTML}
                </div>
            </div>
        `,
      confirmButtonText: 'Tutup',
      confirmButtonColor: '#62411e',
      customClass: {
        container: 'missing-products-modal',
        popup: 'rounded-lg',
        header: 'border-bottom pb-3',
        title: 'text-primary',
        content: 'p-0',
        confirmButton: 'btn-primary'
      }
    })
  },
  // Helper untuk mendapatkan label status eligibility
  getEligibilityLabel (eligibility) {
    switch (eligibility) {
      case 'eligible':
        return 'Siap Digunakan'
      case 'almost':
        return 'Hampir Memenuhi'
      case 'missing_products':
        return 'Produk Kurang'
      default:
        return 'Periksa Syarat'
    }
  },

  // Modal untuk menampilkan produk yang kurang
  showMissingProductsModal (products) {
    let productsHTML = ''

    products.forEach(product => {
      productsHTML += `
		  <div class="d-flex justify-content-between align-items-center border-bottom py-2">
			  <div>
				  <strong>${product.name}</strong>
			  </div>
			  <div class="text-primary fw-bold">
				  ${this.formatCurrency(product.price)}
			  </div>
		  </div>
		  `
    })

    Swal.fire({
      title: 'Produk Yang Diperlukan',
      html: `
		  <div class="text-start">
			  <p>Tambahkan produk berikut untuk mendapatkan promo:</p>
			  <div class="missing-products-list mt-3">
				  ${productsHTML}
			  </div>
		  </div>
		  `,
      confirmButtonText: 'Tutup',
      confirmButtonColor: '#3f51b5'
    })
  },

  getPromoTypeLabel (type) {
    switch (type) {
      case 'percentage':
        return 'Diskon %'
      case 'nominal':
        return 'Diskon'
      case 'bundling':
        return 'Bundle'
      case 'bogo':
        return 'Buy 1 Get 1'
      default:
        return 'Promo'
    }
  },

  // Helper untuk mendapatkan class badge sesuai jenis promo
  getPromoBadgeClass (type) {
    switch (type) {
      case 'percentage':
        return 'bg-success'
      case 'nominal':
        return 'bg-primary'
      case 'bundling':
        return 'bg-warning text-dark'
      case 'bogo':
        return 'bg-info'
      default:
        return 'bg-secondary'
    }
  },

  async handleSuccessfulCheckout (response) {
    console.log('Checkout Success Response:', response)
    let summaryData = response.data?.summary || {}
    let subtotal = parseFloat(summaryData.subtotal) || 0
    let discount = parseFloat(response.data?.promo?.amount) || 0
    let tax = parseFloat(summaryData.tax) || 0
    let total = parseFloat(summaryData.total) || 0

    // PERBAIKAN: Deteksi jika ini adalah promo BUNDLING dari kode
    let promoCode = response.data?.promo?.code || null
    let promoType =
      response.data?.promo?.type || this.getPromoTypeFromCode(promoCode)

    // Override tipe jika ini BUNDLING
    if (promoCode && promoCode.toUpperCase().includes('BUNDLING')) {
      console.log(
        `Detected BUNDLING promo from code "${promoCode}". Overriding type to "bundling".`
      )
      promoType = 'bundling'
    }

    // Jika subtotal adalah 0 tapi ada item, hitung ulang
    if (subtotal === 0 && response.data?.items?.length > 0) {
      console.log(
        'Warning: Summary subtotal is 0 but items exist, recalculating values'
      )
      // Hitung ulang subtotal dari item-item
      subtotal = response.data.items.reduce((sum, item) => {
        const itemPrice = parseFloat(item.unit_price || item.price || 0)
        const itemQty = parseInt(item.quantity || 1)
        const itemSubtotal = itemPrice * itemQty
        console.log(
          `Item calculation: ${item.product_name}, Price: ${itemPrice}, Qty: ${itemQty}, Subtotal: ${itemSubtotal}`
        )
        return sum + itemSubtotal
      }, 0)
      console.log('Recalculated subtotal:', subtotal)

      // Hitung ulang tax dan total berdasarkan tipe promo
      if (promoType === 'bundling' || promoType === 'bogo') {
        // Untuk bundling/BOGO: Tax dihitung dari penuh subtotal
        tax = subtotal * 0.1
        total = subtotal + tax
      } else {
        // Untuk percentage/nominal: Tax dihitung setelah diskon
        tax = Math.max(0, subtotal - discount) * 0.1
        total = Math.max(0, subtotal - discount) + tax
      }
      console.log('Recalculated tax:', tax)
      console.log('Recalculated total:', total)
    }

    // PERBAIKAN: Simpan data checkout dengan format yang konsisten
    const checkoutData = {
      orderId: response.data?.order_id,
      receiptNumber: response.data?.receipt_number,
      orderTime: new Date().toISOString(),
      isNewCheckout: true,
      orderDetails: {
        ...response.data,
        // IMPROVEMENT: Pastikan informasi promo tersimpan dengan format yang benar
        promo: response.data.promo
          ? {
              code: response.data.promo.code,
              type: promoType, // Gunakan tipe yang sudah dikoreksi
              discount: parseFloat(response.data.promo.amount) || 0
            }
          : null,
        // PERBAIKAN UTAMA: Pastikan summary berisi nilai yang benar
        summary: {
          subtotal: subtotal,
          discount:
            promoType === 'bundling' || promoType === 'bogo' ? 0 : discount, // Tidak gunakan diskon untuk bundling/BOGO
          tax: tax,
          total: total
        },
        customer: {
          name: response.data?.customer?.name || '-'
        },
        // PERBAIKAN: Tambahkan informasi flag promo ke items dan pastikan subtotal terisi
        items: (response.data?.items || []).map(item => {
          // Jika ada informasi promo pada item atau di level order
          const hasPromo = response.data.promo && response.data.promo.code
          const itemPrice = parseFloat(item.unit_price || item.price || 0)
          const itemQty = parseInt(item.quantity || 1)

          // Deteksi apakah item ini produk gratis dari promo BUNDLING/BOGO
          const isBundleBogoItem =
            item.is_promo_item === '1' ||
            item.is_promo_item === 1 ||
            (item.unit_price === 0 && item.subtotal === 0) ||
            (item.notes &&
              (item.notes.includes('bundling gratis') ||
                item.notes.includes('promo BOGO')))

          return {
            ...item,
            // Pastikan informasi promo pada item terisi dengan benar
            is_promo_item: isBundleBogoItem
              ? 1
              : item.is_promo_item === '1' || item.is_promo_item === 1
              ? 1
              : hasPromo && !item.parent_id
              ? 1
              : 0,
            promo_type: isBundleBogoItem
              ? item.notes && item.notes.includes('BOGO')
                ? 'bogo'
                : 'bundling'
              : item.promo_type || (hasPromo ? promoType : null),
            // Pastikan subtotal item terisi dengan benar
            subtotal: parseFloat(item.subtotal) || itemPrice * itemQty
          }
        })
      },
      // PERBAIKAN: Tambahkan status sesi
      sessionStatus: 'COMPLETED' // Menandai bahwa order sudah selesai
    }

    console.log('Prepared checkout data for localStorage:', checkoutData)
    localStorage.setItem('checkoutData', JSON.stringify(checkoutData))

    // Tutup modal cart
    $(this.config.selectors.cartModal).modal('hide')

    // Tampilkan pesan sukses
    await Swal.fire({
      icon: 'success',
      title: 'Order Berhasil',
      text: `Pesanan Anda telah dikirim ke dapur. Order ID: ${checkoutData.receiptNumber}`,
      confirmButtonText: 'Lihat Struk',
      allowOutsideClick: false,
      allowEscapeKey: false
    })

    this.resetCartState()
    window.orderCompleted = true

    const params = this.getUrlParams()
    window.location.href = `${window.location.pathname}?outletId=${params.outletId}&tableId=${params.tableId}&brand=${params.brand}&receipt=true&status=completed`
  },

  getPromoTypeFromCode (promoCode) {
    if (!promoCode) return null

    const promoCodeUpper = promoCode.toUpperCase()

    if (promoCodeUpper.includes('DISC') || promoCodeUpper.includes('DISKON'))
      return 'nominal'
    if (
      promoCodeUpper.includes('PERSENTASE') ||
      promoCodeUpper.includes('PCT') ||
      promoCodeUpper.includes('%')
    )
      return 'percentage'
    if (promoCodeUpper.includes('BUNDLE') || promoCodeUpper.includes('PKT'))
      return 'bundling'
    if (
      promoCodeUpper.includes('BOGO') ||
      promoCodeUpper.includes('BUY') ||
      promoCodeUpper.includes('GET')
    )
      return 'bogo'

    return 'nominal'
  },

  handleCheckoutError (error) {
    console.group('🚨 Checkout Error Handling')

    // Log full error object
    console.error('Complete Error Object:', error)

    let errorMessage = 'Gagal melakukan checkout'
    let errorDetails = ''
    let technicalDetails = ''

    // Ekstrak pesan error dari berbagai sumber
    if (error.responseJSON) {
      // Backend mengirim detail error
      errorMessage = error.responseJSON.message || errorMessage
      errorDetails = JSON.stringify(error.responseJSON, null, 2)

      // Tambahkan trace jika tersedia
      if (error.responseJSON.trace) {
        technicalDetails = error.responseJSON.trace
      }
    } else if (error.responseText) {
      // Coba parse responseText jika ada
      try {
        const parsedResponse = JSON.parse(error.responseText)
        errorMessage = parsedResponse.message || errorMessage
        errorDetails = JSON.stringify(parsedResponse, null, 2)
      } catch (parseError) {
        errorDetails = error.responseText
      }
    } else if (error.message) {
      errorMessage = error.message
      technicalDetails = error.stack
    }

    // Tampilkan SweetAlert dengan informasi error komprehensif
    Swal.fire({
      icon: 'error',
      title: 'Checkout Gagal',
      html: `
					<div class="text-left">
						<p class="mb-2"><strong>Pesan Kesalahan:</strong> ${errorMessage}</p>
						
						${
              errorDetails
                ? `
							<details class="mt-3">
								<summary>Detail Respons</summary>
								<pre class="text-left mt-2 p-2 bg-light rounded">${errorDetails}</pre>
							</details>
						`
                : ''
            }
		
						${
              technicalDetails
                ? `
							<details class="mt-3">
								<summary>Teknis</summary>
								<pre class="text-left mt-2 p-2 bg-light rounded">${technicalDetails}</pre>
							</details>
						`
                : ''
            }
					</div>
				`,
      confirmButtonText: 'Tutup',
      confirmButtonColor: '#3085d6',
      didOpen: () => {
        // Tambahkan event listener untuk details
        document.querySelectorAll('details').forEach(details => {
          details.addEventListener('toggle', e => {
            console.log(
              `Detail ${e.target.querySelector('summary').textContent} toggled`
            )
          })
        })
      }
    })

    console.groupEnd()
  },

  async endCurrentSession () {
    try {
      // PERBAIKAN: Tampilkan loading
      this.showLoading(true)

      const params = new URLSearchParams(window.location.search)

      // PERBAIKAN: Parameter tambahan untuk memastikan sesi benar-benar berakhir
      const payload = {
        outletId: params.get('outletId'),
        tableId: params.get('tableId'),
        brand: params.get('brand'),
        forceEnd: true, // Flag untuk force end
        timestamp: new Date().getTime() // Timestamp untuk menghindari caching
      }

      console.log('Sending end session request with payload:', payload)

      // Request ke server untuk mengakhiri sesi
      const response = await $.ajax({
        url: `${window.location.origin}/order/endSession`,
        method: 'POST',
        data: JSON.stringify(payload),
        contentType: 'application/json',
        timeout: 10000 // 10 detik timeout
      })

      if (response.success) {
        console.log('Session ended successfully, cleaning up local data')

        // PERBAIKAN: Bersihkan localStorage untuk menghindari konflik
        localStorage.removeItem('checkoutData')
        localStorage.removeItem('cartData')

        // PERBAIKAN: Reset status sesi global
        window.orderManagerState = {
          sessionActive: false,
          lastSessionId: null
        }

        // PERBAIKAN: Gunakan reload dengan force refresh dari server
        window.location.reload(true)

        // PERBAIKAN: Sembunyikan loading setelah reload dimulai
        setTimeout(() => this.showLoading(false), 500)

        return true
      } else {
        throw new Error(response.message || 'Failed to end session')
      }
    } catch (error) {
      console.error('Error ending session:', error)

      // PERBAIKAN: Tampilkan error dengan opsi refresh
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Failed to end session: ' + (error.message || 'Unknown error'),
        confirmButtonText: 'Refresh Page',
        allowOutsideClick: false
      }).then(() => {
        // Force reload pada kasus error
        window.location.reload(true)
      })

      this.showLoading(false)
      return false
    }
  },

  checkForReceipt () {
    // Cek parameter receipt
    const urlParams = new URLSearchParams(window.location.search)
    const isReceiptMode = urlParams.get('receipt') === 'true'

    console.log('Checking for receipt mode:', isReceiptMode)

    if (!isReceiptMode) {
      return false
    }

    // Cek data receipt dari localStorage
    const savedCheckoutData = localStorage.getItem('checkoutData')
    if (savedCheckoutData) {
      try {
        const checkoutData = JSON.parse(savedCheckoutData)
        console.log('Found checkout data in localStorage:', checkoutData)

        this.displayReceiptFromData(checkoutData)
        return true
      } catch (e) {
        console.error('Error parsing checkout data:', e)
      }
    }

    // Fallback: Ambil dari server
    const params = this.getUrlParams()
    $.ajax({
      url: `${window.location.origin}/order/getReceipt`,
      method: 'GET',
      data: params,
      dataType: 'json',
      success: response => {
        console.log('Receipt data received from server:', response)
        if (response.success && response.data) {
          this.displayReceiptDirectly(response.data)
        } else {
          this.displayErrorReceipt('Data struk tidak ditemukan')
        }
      },
      error: (xhr, status, error) => {
        console.error('Error fetching receipt:', error)
        this.displayErrorReceipt('Gagal memuat struk')
      }
    })

    return true
  },

  displayReceiptFromData (checkoutData) {
    console.group('🧾 [RECEIPT] Processing checkout data for receipt (FIXED)')
    console.log('Raw checkout data:', checkoutData)

    // Extract promo info dengan validasi yang ketat
    let promoType = checkoutData.orderDetails?.promo?.type || null
    const promoCode = checkoutData.orderDetails?.promo?.code || null
    const promoDiscount = parseFloat(
      checkoutData.orderDetails?.promo?.discount || 0
    )

    // PERBAIKAN: Koreksi tipe promo dari kode jika diperlukan
    if (
      promoCode &&
      promoCode.toUpperCase().includes('BUNDLING') &&
      promoType !== 'bundling'
    ) {
      console.log(
        `Correcting promo type from "${promoType}" to "bundling" based on code`
      )
      promoType = 'bundling'
    }

    console.log('Promo details (corrected):', {
      promoCode,
      promoDiscount,
      promoType
    })

    // PERBAIKAN KRITIS: Ambil nilai dari summary jika tersedia, atau hitung ulang
    let subtotal = checkoutData.orderDetails?.summary?.subtotal || 0
    let regularDiscount = 0 // Discount yang mengurangi total (percentage/nominal)
    let bundleBogoDiscount = 0 // Nilai produk gratis (informational only)
    let tax = checkoutData.orderDetails?.summary?.tax || 0
    let total = checkoutData.orderDetails?.summary?.total || 0

    // Process items untuk menghitung nilai yang benar
    if (checkoutData.orderDetails?.items?.length > 0) {
      let calculatedSubtotal = 0
      let calculatedBundleBogoDiscount = 0

      checkoutData.orderDetails.items.forEach(item => {
        const price = parseFloat(item.unit_price || item.price || 0)
        const quantity = parseInt(item.quantity || 1)
        const itemValue = price * quantity

        // Deteksi item gratis promo
        const isFreeItem = this.isReceiptPromoFreeItem(item)

        if (isFreeItem) {
          // Item gratis - ambil nilai asli untuk display
          const originalValue = parseFloat(item.original_price || 0) * quantity
          calculatedBundleBogoDiscount +=
            originalValue > 0 ? originalValue : itemValue
          console.log(
            `Free item detected: ${item.product_name}, original value: ${originalValue}`
          )
        } else {
          // Item reguler yang dibayar
          calculatedSubtotal += itemValue
          console.log(`Regular item: ${item.product_name}, value: ${itemValue}`)
        }
      })

      // Update subtotal jika perhitungan lebih akurat
      if (subtotal === 0 && calculatedSubtotal > 0) {
        subtotal = calculatedSubtotal
        bundleBogoDiscount = calculatedBundleBogoDiscount
        console.log('Used calculated values:', { subtotal, bundleBogoDiscount })
      }
    }

    // PERBAIKAN KRITIS: Penanganan discount berdasarkan tipe promo
    if (promoDiscount > 0) {
      if (promoType === 'bundling' || promoType === 'bogo') {
        // Untuk bundling/BOGO, discount adalah nilai produk gratis (tidak mengurangi subtotal)
        if (bundleBogoDiscount === 0) {
          bundleBogoDiscount = promoDiscount
        }
        regularDiscount = 0 // TIDAK ada discount reguler
        console.log(
          'Bundling/BOGO promo - bundle discount:',
          bundleBogoDiscount
        )
      } else {
        // Untuk percentage/nominal, discount mengurangi subtotal
        regularDiscount = promoDiscount
        console.log('Regular promo - discount amount:', regularDiscount)
      }
    }

    // PERBAIKAN KRITIS: Perhitungan tax dan total yang konsisten
    // Tax HANYA dihitung dari (subtotal - regular discount)
    // Bundle/BOGO discount TIDAK mempengaruhi tax
    const taxableAmount = Math.max(0, subtotal - regularDiscount)
    tax = taxableAmount * 0.1 // 10% tax
    total = taxableAmount + tax

    console.log('Final receipt calculation:', {
      subtotal: subtotal,
      regularDiscount: regularDiscount,
      bundleBogoDiscount: bundleBogoDiscount,
      taxableAmount: taxableAmount,
      tax: tax,
      total: total
    })

    // Initialize receipt data dengan nilai yang benar
    const receiptData = {
      sessionId: checkoutData.orderId,
      receiptNumber: checkoutData.receiptNumber,
      customerName: checkoutData.orderDetails?.customer?.name || '-',
      tableId: this.getUrlParams().tableId,
      orderTime: checkoutData.orderTime,
      items: checkoutData.orderDetails?.items || [],
      summary: {
        subtotal: subtotal,
        tax: tax,
        total: total,
        discount: regularDiscount, // Regular discount (mengurangi total)
        bundleBogoDiscount: bundleBogoDiscount, // Bundle/BOGO discount (informational)
        promoCode: promoCode,
        promoType: promoType
      }
    }

    // PERBAIKAN: Template receipt dengan section discount yang benar
    const regularDiscountSection =
      receiptData.summary.discount > 0
        ? `
        <tr class="discount-row">
            <td colspan="3" class="text-end fw-medium" style="color: black;">
                Diskon ${
                  receiptData.summary.promoCode
                    ? `(${receiptData.summary.promoCode})`
                    : ''
                }:
            </td>
            <td class="text-end fw-medium" style="color: black;">
                -${this.formatCurrency(receiptData.summary.discount)}
            </td>
        </tr>
    `
        : ''

    const bundleBogoDiscountSection =
      receiptData.summary.bundleBogoDiscount > 0
        ? `
        <tr class="bundle-bogo-discount-row">
            <td colspan="3" class="text-end fw-medium text-info">
                Nilai Produk Gratis ${
                  receiptData.summary.promoCode
                    ? `(${receiptData.summary.promoCode})`
                    : ''
                }:
            </td>
            <td class="text-end text-info">
                ${this.formatCurrency(receiptData.summary.bundleBogoDiscount)}
            </td>
        </tr>
        <tr>
            <td colspan="4" class="text-center small text-muted fst-italic">
                *Produk gratis tidak mengurangi total pembayaran
            </td>
        </tr>
    `
        : ''

    // PERBAIKAN: Perbarui template receipt dengan section diskon yang konsisten
    const receiptHTML = `
	  <div class="container py-5">
		<div class="receipt-container bg-white shadow-lg rounded-lg mx-auto" style="max-width: 800px;">
		  <!-- Header Receipt -->
		  <div class="receipt-header bg-gradient-primary text-white p-4 rounded-top">
			<div class="d-flex justify-content-between align-items-center">
			  <div>
				<h3 class="mb-0">Order Receipt</h3>
				<p class="mb-0 opacity-75">Thank you for your order</p>
			  </div>
			  <div class="text-end">
				<span class="fs-4 fw-bold">#${receiptData.receiptNumber}</span>
				<div class="receipt-date small mt-1">${this.formatDateTime(
          receiptData.orderTime
        )}</div>
			  </div>
			</div>
		  </div>
		  
		  <!-- Detail Pelanggan & Pesanan -->
		  <div class="receipt-body p-4">
			<div class="row mb-4">
			  <div class="col-md-6">
				<div class="card border-0 bg-light h-100">
				  <div class="card-body">
					<h5 class="card-title border-bottom pb-2 mb-3">
					  <i class="bi bi-person-circle me-2"></i>Customer Details
					</h5>
					<p class="mb-2"><strong>Name:</strong> ${receiptData.customerName}</p>
					<p class="mb-0"><strong>Table:</strong> ${receiptData.tableId}</p>
				  </div>
				</div>
			  </div>
			  <div class="col-md-6">
				<div class="card border-0 bg-light h-100">
				  <div class="card-body">
					<h5 class="card-title border-bottom pb-2 mb-3">
					  <i class="bi bi-info-circle me-2"></i>Order Info
					</h5>
					<p class="mb-2"><strong>Order Time:</strong> ${this.formatDateTime(
            receiptData.orderTime
          )}</p>
					<p class="mb-0"><strong>Status:</strong> <span class="badge bg-success">Completed</span></p>
				  </div>
				</div>
			  </div>
			</div>
			
			<!-- Daftar Item -->
			<div class="mb-4">
			  <h5 class="mb-3 d-flex align-items-center">
				<i class="bi bi-cart-check me-2"></i> Order Items
			  </h5>
			  <div class="table-responsive">
				<table class="table table-hover">
				  <thead class="bg-light">
					<tr>
					  <th class="py-3">Item</th>
					  <th class="py-3 text-center">Qty</th>
					  <th class="py-3 text-end">Price</th>
					  <th class="py-3 text-end">Subtotal</th>
					</tr>
				  </thead>
				  <tbody>
					${this.renderOrderItems(receiptData.items)}
				  </tbody>
				  <tfoot class="border-top">
					<tr>
					  <td colspan="3" class="text-end fw-medium">Subtotal:</td>
					  <td class="text-end">${this.formatCurrency(receiptData.summary.subtotal)}</td>
					</tr>
					${regularDiscountSection}
					${bundleBogoDiscountSection}
					<tr>
					  <td colspan="3" class="text-end fw-medium">Tax (10%):</td>
					  <td class="text-end">${this.formatCurrency(receiptData.summary.tax)}</td>
					</tr>
					<tr class="fw-bold fs-5">
					  <td colspan="3" class="text-end">Total:</td>
					  <td class="text-end text-primary">${this.formatCurrency(
              receiptData.summary.total
            )}</td>
					</tr>
				  </tfoot>
				</table>
			  </div>
			</div>
			
			<!-- IMPROVEMENT: Display promo info based on type -->
			${this.renderPromoInfo(receiptData.summary)}
			
			<!-- Tombol Aksi -->
			<div class="action-buttons d-flex justify-content-center gap-3">
			  <button id="print-btn" class="btn btn-outline-primary btn-lg px-4">
				<i class="bi bi-printer me-2"></i> Print Receipt
			  </button>
			  <button id="new-order-btn" class="btn btn-primary btn-lg px-4">
				<i class="bi bi-plus-circle me-2"></i> Order Baru
			  </button>
			</div>
		  </div>
		  
		  <!-- Footer Receipt -->
		  <div class="receipt-footer bg-light p-3 text-center rounded-bottom border-top">
			<p class="small text-muted mb-0">Thank you for your order! We appreciate your business.</p>
			<p class="small text-muted mb-0">© ${new Date().getFullYear()} Kenesfood. All rights reserved.</p>
		  </div>
		</div>
	  </div>`

    // Tambahkan HTML ke body
    document.body.innerHTML = receiptHTML

    // Tambahkan CSS untuk receipt
    const style = document.createElement('style')
    style.textContent = `
	  body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
	  .receipt-container { transition: all 0.3s ease; }
	  .receipt-container:hover { box-shadow: 0 10px 25px rgba(0,0,0,0.12) !important; }
	  .bg-gradient-primary { background: linear-gradient(135deg, #3f51b5, #2196f3); }
	  .bg-light-success { background-color: rgba(40, 167, 69, 0.1); }
	  .bg-light-info { background-color: rgba(23, 162, 184, 0.1); }
	  .table th, .table td { padding: 0.75rem 1rem; }
	  .table tbody tr { border-bottom: 1px solid rgba(0,0,0,0.05); }
	  .badge { padding: 0.5em 0.75em; }
	  .btn { border-radius: 5px; padding: 0.5rem 1.5rem; font-weight: 500; transition: all 0.3s; }
	  .btn-primary { background-color: #3f51b5; border-color: #3f51b5; }
	  .btn-outline-primary { color: #3f51b5; border-color: #3f51b5; }
	  .btn-primary:hover, .btn-outline-primary:hover { background-color: #303f9f; border-color: #303f9f; }
	  .text-primary { color: #3f51b5 !important; }
	  .text-info { color: #17a2b8 !important; }
	  .discount-row { color: #dc3545; }
	  .bundle-bogo-discount-row { color: #17a2b8; }
	  .promo-item { background-color: rgba(40, 167, 69, 0.05); }
	  @media print {
		body { background-color: white; }
		.receipt-container { box-shadow: none !important; border: none !important; }
		.action-buttons { display: none !important; }
	  }
	`
    document.head.appendChild(style)

    // PERBAIKAN: Tambahkan script untuk event listener
    const printBtn = document.getElementById('print-btn')
    if (printBtn) {
      printBtn.addEventListener('click', () => {
        window.print()
      })
    }

    // Set up endOrderSession function and button behavior
    window.endOrderSession = async () => {
      try {
        const params = new URLSearchParams(window.location.search)
        localStorage.removeItem('checkoutData')
        localStorage.removeItem('cartData')
        localStorage.removeItem('sessionData')
        sessionStorage.removeItem('sessionData')
        window.orderManagerState = { sessionActive: false, lastSessionId: null }

        const response = await fetch(
          `${window.location.origin}/order/endSession`,
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              outletId: params.get('outletId'),
              tableId: params.get('tableId'),
              brand: params.get('brand'),
              forceEnd: true,
              timestamp: Date.now()
            })
          }
        )

        const result = await response.json()
        if (result.success) {
          const baseUrl = window.location.pathname
          const timestamp = Date.now()
          const newParams = new URLSearchParams({
            outletId: params.get('outletId'),
            tableId: params.get('tableId'),
            brand: params.get('brand'),
            _: timestamp
          })
          const newUrl = baseUrl + '?' + newParams.toString()
          window.forceFullReload = true
          setTimeout(() => {
            window.location.replace(newUrl)
            setTimeout(() => {
              window.location.reload(true)
            }, 500)
          }, 100)
        } else {
          throw new Error(result.message || 'Failed to end session')
        }
      } catch (error) {
        console.error('Error ending receipt session:', error)
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Failed to end session. The page will refresh.',
          allowOutsideClick: false
        }).then(() => {
          window.location.reload(true)
        })
      }
    }

    // Attach endOrderSession to new order button
    const newOrderBtn = document.getElementById('new-order-btn')
    if (newOrderBtn) {
      newOrderBtn.addEventListener('click', () => {
        Swal.fire({
          title: 'Memproses...',
          text: 'Sedang menyiapkan order baru',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading()
          }
        })
        this.endOrderSession()
      })
    }

    // Add fade-in animation
    setTimeout(() => {
      const container = document.querySelector('.receipt-container')
      if (container) {
        container.style.opacity = '0'
        container.style.transform = 'translateY(20px)'
        setTimeout(() => {
          container.style.transition = 'all 0.5s ease'
          container.style.opacity = '1'
          container.style.transform = 'translateY(0)'
        }, 100)
      }
    }, 100)

    // Tutup grup log
    console.groupEnd()
  },

  isReceiptPromoFreeItem (item) {
    const price = parseFloat(item.unit_price || item.price || 0)
    const isPromoItem = item.is_promo_item === 1 || item.is_promo_item === '1'
    const promoType = item.promo_type
    const notes = (item.notes || '').toLowerCase()

    return (
      (isPromoItem && (promoType === 'bundling' || promoType === 'bogo')) ||
      (price === 0 && (notes.includes('gratis') || notes.includes('promo')))
    )
  },

  renderPromoInfo (summary) {
    // PERBAIKAN: Periksa diskon dengan lebih teliti
    const hasRegularDiscount = summary.discount && summary.discount > 0
    const hasBundleBogoDiscount =
      summary.bundleBogoDiscount && summary.bundleBogoDiscount > 0

    // Koreksi tipe promo jika kode promo mengandung "BUNDLING"
    let promoType = summary.promoType
    if (
      summary.promoCode &&
      summary.promoCode.toUpperCase().includes('BUNDLING')
    ) {
      promoType = 'bundling'
    }

    if (!hasRegularDiscount && !hasBundleBogoDiscount) {
      return ''
    }

    let regularDiscountInfo = ''
    let bundleBogoDiscountInfo = ''

    if (hasRegularDiscount) {
      // PERBAIKAN: Tambahkan tipe promo yang lebih deskriptif
      const promoTypeText =
        promoType === 'percentage'
          ? 'Percentage Discount'
          : promoType === 'nominal'
          ? 'Fixed Amount Discount'
          : 'Discount'
      regularDiscountInfo = `
		<div class="promo-info bg-light-success rounded p-3 mb-3 border-start border-3 border-success">
		  <div class="d-flex align-items-center">
			<div class="me-3">
			  <i class="bi bi-tag-fill text-success fs-3"></i>
			</div>
			<div>
			  <h6 class="mb-1 text-success">Discount Applied: ${
          summary.promoCode || 'Discount'
        }</h6>
			  <p class="small mb-0">
				${this.getPromoTypeDescription(promoType || 'percentage')}
				<br>
				<strong>Savings: ${this.formatCurrency(summary.discount)}</strong>
			  </p>
			</div>
		  </div>
		</div>
	  `
    }

    if (hasBundleBogoDiscount) {
      bundleBogoDiscountInfo = `
		<div class="promo-info bg-light-info rounded p-3 mb-3 border-start border-3 border-info">
		  <div class="d-flex align-items-center">
			<div class="me-3">
			  <i class="bi bi-gift-fill text-info fs-3"></i>
			</div>
			<div>
			  <h6 class="mb-1 text-info">Free Products Added: ${
          summary.promoCode || 'Promotion'
        }</h6>
			  <p class="small mb-0">
				${this.getPromoTypeDescription(promoType || 'bundling')}
				<br>
				<strong>Free Product Value: ${this.formatCurrency(
          summary.bundleBogoDiscount
        )}</strong>
				<br>
				<span class="text-muted fst-italic small">*Free products value shown for information only and not subtracted from total</span>
			  </p>
			</div>
		  </div>
		</div>
	  `
    }

    return `
	  <div class="promo-section mb-4">
		${regularDiscountInfo}
		${bundleBogoDiscountInfo}
	  </div>
	`
  },

  checkForReceipt () {
    // Cek parameter receipt
    const urlParams = new URLSearchParams(window.location.search)
    const isReceiptMode = urlParams.get('receipt') === 'true'

    console.log('Checking for receipt mode:', isReceiptMode)

    if (!isReceiptMode) {
      return false
    }

    // PERBAIKAN: Cek juga parameter status untuk mengetahui bahwa ini benar-benar receipt dari checkout
    const receiptStatus = urlParams.get('status')
    console.log('Receipt status from URL:', receiptStatus)

    // Cek data receipt dari localStorage
    const savedCheckoutData = localStorage.getItem('checkoutData')
    if (savedCheckoutData) {
      try {
        const checkoutData = JSON.parse(savedCheckoutData)
        console.log('Found checkout data in localStorage:', checkoutData)

        this.displayReceiptFromData(checkoutData)
        return true
      } catch (e) {
        console.error('Error parsing checkout data:', e)
      }
    }

    // Fallback: Ambil dari server dengan retry mechanism
    this.fetchReceiptWithRetry()
    return true
  },

  async fetchReceiptWithRetry (attempt = 1, maxAttempts = 3) {
    const params = this.getUrlParams()

    try {
      console.log(`Fetching receipt data (attempt ${attempt}/${maxAttempts})`)

      const response = await $.ajax({
        url: `${window.location.origin}/order/getReceipt`,
        method: 'GET',
        data: params,
        dataType: 'json',
        timeout: 10000 // 10 seconds timeout
      })

      console.log('Receipt data received from server:', response)

      if (response.success && response.data) {
        this.displayReceiptDirectly(response.data)
      } else {
        this.displayErrorReceipt('Data struk tidak ditemukan')
      }
    } catch (error) {
      console.error(`Error fetching receipt (attempt ${attempt}):`, error)

      // Retry logic with exponential backoff
      if (attempt < maxAttempts) {
        const delay = Math.pow(2, attempt) * 1000 // Exponential backoff
        console.log(`Retrying in ${delay}ms...`)

        setTimeout(() => {
          this.fetchReceiptWithRetry(attempt + 1, maxAttempts)
        }, delay)
      } else {
        console.error('Max retry attempts reached, showing error receipt')
        this.displayErrorReceipt(
          'Gagal memuat struk setelah beberapa kali percobaan'
        )
      }
    }
  },

  async endOrderSession () {
    try {
      console.group('🔄 [END SESSION] Ending Order Session')
      console.log('Starting complete order session termination process')

      // Tampilkan loading yang lebih informatif
      Swal.fire({
        title: 'Memproses...',
        text: 'Menyiapkan sesi baru untuk Anda',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading()
        }
      })

      const params = new URLSearchParams(window.location.search)

      // Ambil semua parameter dari URL dengan lebih komplit
      const payload = {
        outletId: params.get('outletId'),
        tableId: params.get('tableId'),
        brand: params.get('brand'),
        forceEnd: true, // Flag kritis untuk memastikan sesi berakhir
        receiptMode: true, // Menandai bahwa ini dari halaman receipt
        timestamp: Date.now() // Mencegah caching
      }

      console.log('🔴 [END SESSION] Payload:', payload)

      // Gunakan timeout yang lebih panjang dan retry mechanism
      let response
      let attempts = 0
      const maxAttempts = 3

      while (attempts < maxAttempts) {
        try {
          console.log(
            `📡 [END SESSION] Sending request (attempt ${
              attempts + 1
            }/${maxAttempts})`
          )
          response = await $.ajax({
            url: `${window.location.origin}/order/endSession`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            timeout: 15000 // 15 detik timeout
          })

          console.log('✅ [END SESSION] Server response:', response)
          break // Keluar dari loop jika berhasil
        } catch (retryError) {
          attempts++
          console.warn(
            `❌ [END SESSION] Attempt ${attempts} failed:`,
            retryError
          )

          if (attempts >= maxAttempts) throw retryError

          // Wait with exponential backoff
          const waitTime = Math.pow(2, attempts) * 1000
          console.log(`⏱️ [END SESSION] Waiting ${waitTime}ms before retry`)
          await new Promise(resolve => setTimeout(resolve, waitTime))
        }
      }

      if (response && response.success) {
        console.log('✅ [END SESSION] Session ended successfully on server')

        // PERBAIKAN KRITIS: Bersihkan SEMUA data terkait sesi di localStorage
        console.log('🧹 [END SESSION] Cleaning localStorage data')
        localStorage.removeItem('checkoutData')
        localStorage.removeItem('cartData')
        localStorage.removeItem('sessionData')
        sessionStorage.removeItem('sessionData')

        // Reset state global
        console.log('🔄 [END SESSION] Resetting global state')
        window.orderManagerState = {
          sessionActive: false,
          lastSessionId: null
        }

        // Batalkan semua timer atau interval yang mungkin masih berjalan
        if (this.sessionTimer) {
          console.log('⏱️ [END SESSION] Clearing session timer')
          clearInterval(this.sessionTimer)
        }
        if (this.autoExtendTimer) {
          console.log('⏱️ [END SESSION] Clearing auto-extend timer')
          clearInterval(this.autoExtendTimer)
        }

        const baseUrl = window.location.pathname
        const timestamp = Date.now()

        // PERBAIKAN UTAMA: Buat URL baru tanpa parameter receipt dan dengan timestamp baru
        const newParams = new URLSearchParams({
          outletId: params.get('outletId'),
          tableId: params.get('tableId'),
          brand: params.get('brand'),
          _: timestamp // Parameter cache busting
        })

        const newUrl = baseUrl + '?' + newParams.toString()
        console.log('🔀 [END SESSION] Redirecting to:', newUrl)

        // Tambahan log untuk memastikan
        console.log('📝 [END SESSION] Current URL:', window.location.href)
        console.log('📝 [END SESSION] New URL (no receipt param):', newUrl)

        window.forceFullReload = true // Custom flag

        setTimeout(() => {
          console.log('🔄 [END SESSION] Executing redirect now')
          window.location.replace(newUrl) // Replace current history (penting!)

          // Tambahan: Set timeout kedua untuk memastikan reload terjadi
          setTimeout(() => {
            console.log('🔄 [END SESSION] Executing forced reload')
            window.location.reload(true)
          }, 500)
        }, 100)

        console.log('✅ [END SESSION] Redirect command issued')
      } else {
        throw new Error(response?.message || 'Failed to end session')
      }
    } catch (error) {
      console.error('❌ [END SESSION] Error:', error)

      // Tampilkan error yang lebih informatif
      Swal.fire({
        icon: 'error',
        title: 'Kesalahan',
        html: `<p>Gagal mempersiapkan sesi baru:</p>
				 <pre class="text-left text-danger">${error.message || 'Unknown error'}</pre>
				 <p class="mt-3">Halaman akan dimuat ulang otomatis.</p>`,
        confirmButtonText: 'Refresh Halaman',
        allowOutsideClick: false,
        allowEscapeKey: false
      }).then(() => {
        // PERBAIKAN: Clean state dan hard reload
        localStorage.removeItem('checkoutData')

        // PERBAIKAN KRITIS: Redirect ke URL tanpa parameter receipt
        const baseUrl = window.location.pathname
        const params = new URLSearchParams(window.location.search)
        const newParams = new URLSearchParams({
          outletId: params.get('outletId'),
          tableId: params.get('tableId'),
          brand: params.get('brand'),
          _: Date.now()
        })

        // Replace dan reload
        window.location.replace(baseUrl + '?' + newParams.toString())
        setTimeout(() => window.location.reload(true), 100)
      })
    } finally {
      console.log('⏹️ [END SESSION] Process completed')
      console.groupEnd()
    }
  },

  displayErrorReceipt (message) {
    // Kosongkan isi body
    document.body.innerHTML = ''

    // Tambahkan pesan error
    const errorHTML = `
		<div class="container py-5">
		  <div class="alert alert-danger text-center">
			<h4 class="alert-heading">Receipt Error</h4>
			<p class="mb-4">${message}</p>
			<button id="back-btn" class="btn btn-primary">
			  Back to Menu
			</button>
		  </div>
		</div>`

    // Tambahkan HTML ke body
    document.body.innerHTML = errorHTML

    // Tambahkan event listener
    document.getElementById('back-btn').addEventListener('click', () => {
      this.startNewOrder()
    })
  },

  async startNewOrder () {
    try {
      console.group('🔄 [NEW ORDER] Starting new order process')
      console.log('🔍 [NEW ORDER] Current URL: ' + window.location.href)

      // Tampilkan loading state
      Swal.fire({
        title: 'Memproses...',
        text: 'Sedang menyiapkan order baru',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading()
        }
      })

      console.log('🧹 [NEW ORDER] Cleaning up local storage data')
      // Bersihkan localStorage
      localStorage.removeItem('checkoutData')
      localStorage.removeItem('cartData')
      sessionStorage.removeItem('sessionData')

      // Reset state
      console.log('🔄 [NEW ORDER] Resetting global state')
      window.orderManagerState = {
        sessionActive: false,
        lastSessionId: null
      }

      const params = new URLSearchParams(window.location.search)
      console.log(
        '📝 [NEW ORDER] URL Parameters:',
        Object.fromEntries(params.entries())
      )

      // Request ke server untuk mengakhiri sesi dengan metode yang lebih robust
      console.log('📡 [NEW ORDER] Sending endSession request to server')
      const response = await $.ajax({
        url: `${window.location.origin}/order/endSession`,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
          outletId: params.get('outletId'),
          tableId: params.get('tableId'),
          brand: params.get('brand'),
          forceEnd: true, // Flag tambahan untuk memastikan sesi benar-benar berakhir
          timestamp: Date.now(),
          source: 'startNewOrder' // Untuk memudahkan tracking di server
        }),
        timeout: 10000 // Timeout 10 detik
      })

      console.log('✅ [NEW ORDER] Server response:', response)

      if (response.success) {
        // Buat URL baru tanpa parameter receipt dan dengan timestamp baru
        const baseUrl = window.location.pathname
        const timestamp = Date.now()

        // PERBAIKAN UTAMA: Pastikan tidak ada parameter receipt di URL baru
        const newParams = new URLSearchParams({
          outletId: params.get('outletId'),
          tableId: params.get('tableId'),
          brand: params.get('brand'),
          _: timestamp // Parameter untuk mencegah caching
        })

        const newUrl = baseUrl + '?' + newParams.toString()
        console.log('🔀 [NEW ORDER] Redirecting to:', newUrl)

        // Gunakan replace untuk menghapus history & gunakan timeout
        // untuk memastikan console log terekam
        setTimeout(() => {
          console.log('🔄 [NEW ORDER] Executing redirect now')
          window.location.replace(newUrl) // Hapus dari history browser

          // Tambahan: Force reload setelah redirect
          setTimeout(() => {
            console.log('🔄 [NEW ORDER] Executing forced reload')
            window.location.reload(true)
          }, 500)
        }, 100)
      } else {
        throw new Error(response.message || 'Failed to end session')
      }
    } catch (error) {
      console.error('❌ [NEW ORDER] Error:', error)

      Swal.fire({
        icon: 'error',
        title: 'Error',
        html: `<p>Failed to start new order:</p>
				 <pre class="text-danger">${error.message || 'Unknown error'}</pre>
				 <p class="mt-3">Halaman akan dimuat ulang secara otomatis.</p>`,
        confirmButtonText: 'Refresh Page',
        allowOutsideClick: false
      }).then(() => {
        // Force reload dengan parameter bersih
        const baseUrl = window.location.pathname
        const params = new URLSearchParams(window.location.search)

        // Gunakan replace untuk menghapus history
        window.location.replace(
          baseUrl +
            '?' +
            new URLSearchParams({
              outletId: params.get('outletId'),
              tableId: params.get('tableId'),
              brand: params.get('brand'),
              _: Date.now()
            }).toString()
        )

        setTimeout(() => window.location.reload(true), 100)
      })
    } finally {
      console.groupEnd()
    }
  },

  renderOrderItems (items) {
    if (!items || !items.length) {
      return '<tr><td colspan="4" class="text-center py-4">No items found</td></tr>'
    }

    let itemsHtml = ''
    let packageItems = {}

    // Group package items
    items.forEach(item => {
      if (item.parent_id) {
        if (!packageItems[item.parent_id]) {
          packageItems[item.parent_id] = []
        }
        packageItems[item.parent_id].push(item)
      }
    })

    items.forEach(item => {
      // Skip if this is a child item (will be processed with parent)
      if (item.parent_id) return

      // Determine pricing logic
      const quantity = parseInt(item.quantity || item.qty || 1)
      const isPromoItem =
        item.is_promo_item === '1' ||
        item.is_promo_item === 1 ||
        item.promo_type
      const hasOriginalPrice =
        item.original_price && parseFloat(item.original_price) > 0

      // **PERBAIKAN UTAMA**: Gunakan subtotal untuk package, unit_price untuk regular item
      let displayPrice, actualSubtotal

      if (packageItems[item.id]) {
        // Untuk package items, gunakan subtotal yang sudah dihitung
        displayPrice =
          parseFloat(item.subtotal || item.unit_price || 0) / quantity
        actualSubtotal = parseFloat(item.subtotal || 0)
      } else {
        // Untuk regular items
        displayPrice = parseFloat(item.unit_price || item.price || 0)
        actualSubtotal = displayPrice * quantity
      }

      // Handle promo pricing display
      let priceDisplay, subtotalDisplay
      if (isPromoItem && hasOriginalPrice) {
        const originalPrice = parseFloat(item.original_price)
        const originalSubtotal = originalPrice * quantity

        priceDisplay = `
                <div class="price-comparison">
                    <div class="discounted-price text-danger fw-bold">${this.formatCurrency(
                      displayPrice
                    )}</div>
                    <div class="original-price text-muted text-decoration-line-through small">${this.formatCurrency(
                      originalPrice
                    )}</div>
                </div>
            `
        subtotalDisplay = `
                <div class="price-comparison">
                    <div class="discounted-price text-danger fw-bold">${this.formatCurrency(
                      actualSubtotal
                    )}</div>
                    <div class="original-price text-muted text-decoration-line-through small">${this.formatCurrency(
                      originalSubtotal
                    )}</div>
                </div>
            `
      } else if (actualSubtotal === 0 || displayPrice === 0) {
        priceDisplay = 'Gratis'
        subtotalDisplay = 'Gratis'
      } else {
        priceDisplay = this.formatCurrency(displayPrice)
        subtotalDisplay = this.formatCurrency(actualSubtotal)
      }

      const promoClass = isPromoItem ? 'promo-item' : ''
      const promoBadge = isPromoItem
        ? '<span class="badge bg-success ms-2">Promo</span>'
        : ''

      if (packageItems[item.id]) {
        // Package header
        itemsHtml += `
                <tr class="package-header ${promoClass}">
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-primary me-2">Package</span>
                            <strong>${item.product_name}</strong>
                            ${promoBadge}
                        </div>
                        ${
                          item.notes
                            ? `<div class="small text-muted mt-1">${item.notes}</div>`
                            : ''
                        }
                    </td>
                    <td class="text-center">${quantity}</td>
                    <td class="text-end">${priceDisplay}</td>
                    <td class="text-end">${subtotalDisplay}</td>
                </tr>
            `

        // Package items
        packageItems[item.id].forEach(childItem => {
          const childQuantity = parseInt(
            childItem.quantity || childItem.qty || 1
          )
          const childUnitPrice = parseFloat(
            childItem.unit_price || childItem.price || 0
          )
          const childSubtotal = childUnitPrice * childQuantity
          const childIsPromo =
            childItem.is_promo_item === '1' || childItem.is_promo_item === 1
          const childIsFree = childUnitPrice === 0 || childSubtotal === 0

          itemsHtml += `
                    <tr class="package-item bg-light ${
                      childIsPromo ? 'promo-item' : ''
                    }">
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-dash me-2 text-muted"></i>
                                <span>${childItem.product_name}</span>
                                ${
                                  childIsPromo
                                    ? '<span class="badge bg-success ms-2 small">Promo</span>'
                                    : ''
                                }
                            </div>
                            ${
                              childItem.notes
                                ? `<div class="small text-muted mt-1 ms-4">${childItem.notes}</div>`
                                : ''
                            }
                        </td>
                        <td class="text-center">${childQuantity}</td>
                        <td class="text-end">${
                          childIsFree
                            ? 'Gratis'
                            : this.formatCurrency(childUnitPrice)
                        }</td>
                        <td class="text-end">${
                          childIsFree
                            ? 'Gratis'
                            : this.formatCurrency(childSubtotal)
                        }</td>
                    </tr>
                `
        })
      } else {
        // Regular item
        itemsHtml += `
                <tr class="${promoClass}">
                    <td>
                        <div class="d-flex align-items-center">
                            <div>${item.product_name}</div>
                            ${promoBadge}
                        </div>
                        ${
                          item.notes
                            ? `<div class="small text-muted mt-1">${item.notes}</div>`
                            : ''
                        }
                    </td>
                    <td class="text-center">${quantity}</td>
                    <td class="text-end">${priceDisplay}</td>
                    <td class="text-end">${subtotalDisplay}</td>
                </tr>
            `
      }
    })

    return itemsHtml
  },

  getPromoTypeDescription (type) {
    switch (type) {
      case 'percentage':
        return 'Percentage discount applied to your order'
      case 'nominal':
        return 'Fixed amount discount applied to your order'
      case 'bundling':
        return 'Bundle promotion with free products' // Ensure this is consistent with BOGO
      case 'bogo':
        return 'Buy One Get One promotion'
      default:
        return 'Discount applied to your order'
    }
  },

  showNotification (message, type = 'info') {
    console.group('🔔 [NOTIFICATION] Showing notification')
    console.log(`Type: ${type}, Message: ${message}`)

    try {
      // PERBAIKAN: Konfigurasi yang benar untuk SweetAlert Toast
      const Toast = Swal.mixin({
        toast: true,
        // PERBAIKAN: Gunakan 'top-end' sebagai posisi default yang valid
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: toast => {
          toast.addEventListener('mouseenter', Swal.stopTimer)
          toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
      })

      // Style berdasarkan tipe notifikasi
      const typeStyles = {
        success: {
          icon: 'success',
          iconColor: '#fff',
          background: '#28a745',
          color: '#fff'
        },
        error: {
          icon: 'error',
          iconColor: '#fff',
          background: '#dc3545',
          color: '#fff'
        },
        warning: {
          icon: 'warning',
          iconColor: '#343a40',
          background: '#ffc107',
          color: '#343a40'
        },
        info: {
          icon: 'info',
          iconColor: '#fff',
          background: '#17a2b8',
          color: '#fff'
        }
      }

      // Pilih style berdasarkan tipe atau default ke info
      const style = typeStyles[type] || typeStyles.info

      // Tampilkan toast dengan style yang dipilih
      Toast.fire({
        icon: style.icon,
        title: message,
        background: style.background,
        color: style.color,
        iconColor: style.iconColor
      })

      console.log('✅ [NOTIFICATION] Notification displayed successfully')
    } catch (error) {
      console.error('❌ [NOTIFICATION] Error showing notification:', error)
      // Fallback to basic alert if SweetAlert fails
      alert(`${type.toUpperCase()}: ${message}`)
    } finally {
      console.groupEnd()
    }
  },

  async callWaiter () {
    try {
      // Tampilkan loading
      this.showLoading(true)

      // Dapatkan parameter URL
      const params = this.getUrlParams()

      // Kirim request ke server
      $.ajax({
        url: `${window.location.origin}/order/callWaiter`,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
          outletId: params.outletId,
          tableId: params.tableId,
          brand: params.brand
        }),
        dataType: 'json'
      })
        .done(function (response) {
          if (response.success) {
            // Pesan khusus jika panggilan sebelumnya masih dalam proses
            if (response.data && response.data.processing_call_exists) {
              Swal.fire({
                icon: 'info',
                title: 'Panggilan Baru Dibuat',
                html: 'Pelayan sedang dalam perjalanan untuk panggilan sebelumnya.<br>Panggilan baru telah ditambahkan.',
                timer: 4000,
                showConfirmButton: true,
                confirmButtonText: 'OK'
              })
            }
            // Pesan jika ada panggilan yang belum diproses
            else if (response.message.includes('sedang diproses')) {
              Swal.fire({
                icon: 'info',
                title: 'Pelayan dalam Perjalanan',
                text: response.message,
                timer: 3000,
                showConfirmButton: true,
                confirmButtonText: 'OK'
              })
            }
            // Pesan sukses normal
            else {
              Swal.fire({
                icon: 'success',
                title: 'Pelayan Dipanggil',
                text: 'Pelayan akan segera datang ke meja Anda',
                timer: 3000,
                showConfirmButton: false
              })
            }
          } else {
            throw new Error(response.message || 'Gagal memanggil pelayan')
          }
        })
        .fail(function (xhr, status, error) {
          console.error('Error calling waiter:', error)

          Swal.fire({
            icon: 'error',
            title: 'Gagal Memanggil Pelayan',
            text:
              xhr.responseJSON?.message ||
              'Terjadi kesalahan saat memanggil pelayan',
            confirmButtonText: 'OK'
          })
        })
        .always(() => {
          this.showLoading(false)
        })
    } catch (error) {
      console.error('Error calling waiter:', error)
      this.showError('Gagal memanggil pelayan')
      this.showLoading(false)
    }
  },

  // Proses notifikasi dengan penanganan meja yang memiliki beberapa panggilan
  async handleWaiterCallNotifications (waiterCalls) {
    if (!waiterCalls || waiterCalls.length === 0) return

    // Kelompokkan berdasarkan meja untuk notifikasi yang lebih efisien
    const tableGroups = {}
    waiterCalls.forEach(call => {
      if (call.status === 'new') {
        if (!tableGroups[call.table_id]) {
          tableGroups[call.table_id] = []
        }
        tableGroups[call.table_id].push(call)
      }
    })

    // Proses setiap meja dengan panggilan baru
    Object.keys(tableGroups).forEach(tableId => {
      const callsForTable = tableGroups[tableId]

      // Pilih panggilan terbaru untuk ditampilkan
      const latestCall = callsForTable.reduce((latest, current) =>
        new Date(current.created_at) > new Date(latest.created_at)
          ? current
          : latest
      )

      // Gunakan fungsi notifikasi yang sudah ada dengan info tambahan
      showWaiterCallNotification(
        latestCall,
        callsForTable.length > 1 ? callsForTable.length : null
      )

      // Hanya mainkan sound sekali per meja
      playNotificationSound('newCustomerBell')
    })
  },

  // Modifikasi fungsi showWaiterCallNotification untuk mendukung beberapa panggilan
  async showWaiterCallNotification (call, callCount = null) {
    const tableNumber = call.table_id

    // Tambahkan info jumlah panggilan jika ada lebih dari satu
    const multipleCallsInfo = callCount
      ? `<div class="alert alert-info mt-2">
				<i class="fa fa-info-circle me-2"></i>
				Meja ini memiliki ${callCount} panggilan aktif
			  </div>`
      : ''

    // Show notification
    Swal.fire({
      title: 'Panggilan Pelayan!',
      html: `
				<div class="alert alert-warning">
				  <i class="fa fa-bell me-2"></i>
				  <strong>Meja ${tableNumber}</strong> membutuhkan bantuan pelayan
				</div>
				<p class="mt-3">Waktu panggilan: ${formatDateTime(call.created_at)}</p>
				${multipleCallsInfo}
			  `,
      icon: 'warning',
      confirmButtonText: 'Proses',
      showCancelButton: true,
      cancelButtonText: 'Nanti',
      customClass: {
        container: 'waiter-call-notification'
      }
    }).then(result => {
      if (result.isConfirmed) {
        // Mark call as processing
        processWaiterCall(call.id)
      }
    })

    // Also show a toast notification
    const toastMsg = callCount
      ? `Meja ${tableNumber} membutuhkan bantuan (${callCount} panggilan)`
      : `Meja ${tableNumber} membutuhkan bantuan`

    showToastNotification('Panggilan Pelayan', toastMsg, 'warning')
  },

  // Metode untuk fetch ulang data dengan retry
  async fetchWithRetry (url, options, maxRetries = 3) {
    let retries = 0

    while (retries < maxRetries) {
      try {
        const response = await fetch(url, options)
        const data = await response.json()
        return data
      } catch (error) {
        retries++
        if (retries >= maxRetries) {
          throw error
        }
        // Exponential backoff
        await new Promise(resolve =>
          setTimeout(resolve, 1000 * Math.pow(2, retries))
        )
      }
    }
  }
}

$(document).ready(() => {
  // Cek mode receipt terlebih dahulu
  const urlParams = new URLSearchParams(window.location.search)
  const isReceiptMode = urlParams.get('receipt') === 'true'

  if (isReceiptMode) {
    console.log('RECEIPT MODE DETECTED ON DOCUMENT READY')
    // Mode receipt akan dihandle oleh OrderManager.init()
  }

  // Inisialisasi OrderManager
  OrderManager.init()
})
