'use strict'

const ProductFilter = {
  state: {
    currentCategory: 'all',
    initialized: false,
    containerHTML: null
  },

  config: {
    selectors: {
      categorySelect: '#category-select',
      productCard: '.product-card',
      productCategory: '.category-header',
      productContainer: '.row.g-4.mb-5',
      productListing: '#product-listing'
    },
    templates: {
      emptyState: `
        <div id="filter-empty-message" class="alert alert-info text-center my-4 fade-in">
          <i class="fa fa-info-circle me-2"></i>
          Tidak ada produk dalam kategori ini
        </div>`
    }
  },

  init () {
    console.group('ProductFilter Initialization')
    try {
      // Validasi DOM elements
      this.validateElements()

      // Bind events
      this.bindEvents()

      this.state.initialized = true
      console.log('ProductFilter initialized successfully')
      return true
    } catch (error) {
      console.error('ProductFilter initialization failed:', error)
      return false
    } finally {
      console.groupEnd()
    }
  },

  validateElements () {
    const $products = $(this.config.selectors.productCard)
    const $categories = $(this.config.selectors.categorySelect)

    if (!$products.length) {
      throw new Error('No product cards found')
    }

    if (!$categories.length) {
      throw new Error('Category select not found')
    }

    console.log(
      `Found ${$products.length} products across ${$categories.length} categories`
    )
  },

  bindEvents () {
    $(this.config.selectors.categorySelect).on('change', e => {
      const categoryId = $(e.target).val()
      console.log('Category selected:', categoryId)
      this.filterProducts(categoryId)
    })
  },

  filterProducts (categoryId) {
    console.group('Filtering products')
    console.log('Target category:', categoryId)

    const $products = $(this.config.selectors.productCard)
    const $categories = $(this.config.selectors.productCategory)
    const $containers = $(this.config.selectors.productContainer)

    // Reset visibility first
    $products.parent().show()
    $categories.show()
    $containers.show()

    if (categoryId !== 'all') {
      // Hide non-matching products
      $products.each((_, product) => {
        const $product = $(product)
        const productCategory = $product.data('category-id')

        if (productCategory != categoryId) {
          $product.parent().hide()
        }
      })

      // Hide empty category headers and containers
      $containers.each((_, container) => {
        const $container = $(container)
        const $visibleProducts = $container
          .find('.product-card:visible')
          .parent()

        if ($visibleProducts.length === 0) {
          $container.hide()
          $container.prev(this.config.selectors.productCategory).hide()
        }
      })
    }

    this.updateEmptyState()
    console.groupEnd()
  },

  updateEmptyState () {
    const $visibleProducts = $(`${this.config.selectors.productCard}:visible`)
    const $emptyMessage = $('#empty-products-message')

    if ($visibleProducts.length === 0) {
      if ($emptyMessage.length === 0) {
        const message = `
          <div id="empty-products-message" class="alert alert-info text-center my-4">
            <i class="fa fa-info-circle me-2"></i>
            Tidak ada produk dalam kategori ini
          </div>
        `
        $(this.config.selectors.productContainer).last().after(message)
      } else {
        $emptyMessage.show()
      }
    } else {
      $emptyMessage.hide()
    }
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  try {
    console.group('Component Initialization')

    // PERBAIKAN KRITIS: Pastikan order-page tersembunyi di awal
    $('#order-page').attr('hidden', true)
    console.log('Order page hidden on initial load')

    // PERBAIKAN: Buat penanda sudah diinisialisasi untuk komponen
    window.appComponentsStatus = {
      sessionManagerInitialized: false,
      productSearchInitialized: false,
      productModalInitialized: false,
      orderManagerInitialized: false
    }

    // Berikan waktu bagi DOM untuk siap
    setTimeout(async () => {
      try {
        // Initialize ProductSearch first (ini aman, tidak terkait sesi)
        if (
          typeof ProductSearch !== 'undefined' &&
          !window.appComponentsStatus.productSearchInitialized
        ) {
          await ProductSearch.init()
          window.appComponentsStatus.productSearchInitialized = true
          console.log('ProductSearch initialization complete')
        }

        // KRITIS: Siapkan event listener untuk sesi aktif
        document.addEventListener('sessionActivated', async event => {
          console.log('SessionActivated event received in main.js')

          // Tampilkan order page saat sesi aktif
          $('#order-page').removeAttr('hidden')

          // Initialize ProductModal jika belum
          if (
            !window.appComponentsStatus.productModalInitialized &&
            typeof ProductModal !== 'undefined'
          ) {
            try {
              await ProductModal.init()
              window.appComponentsStatus.productModalInitialized = true
              console.log(
                'ProductModal initialization complete after session activation'
              )
            } catch (error) {
              console.error('Error initializing ProductModal:', error)
            }
          }

          // Initialize OrderManager jika belum
          if (
            !window.appComponentsStatus.orderManagerInitialized &&
            typeof OrderManager !== 'undefined'
          ) {
            try {
              await OrderManager.init()
              window.appComponentsStatus.orderManagerInitialized = true
              console.log(
                'OrderManager initialization complete after session activation'
              )
            } catch (error) {
              console.error('Error initializing OrderManager:', error)
            }
          }
        })

        console.log(
          'Initial components and event listeners initialized successfully'
        )
      } catch (error) {
        console.error('Error during component initialization:', error)
      }
    }, 100)

    console.groupEnd()
  } catch (error) {
    console.error('Error during initialization:', error)
    console.groupEnd()

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Error',
        text: 'Failed to initialize components. Please refresh the page.',
        icon: 'error'
      })
    } else {
      alert('Failed to initialize components. Please refresh the page.')
    }
  }
})
