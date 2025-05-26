// Image Preview Handler
var loadPreview = function (event) {
  var output = document.getElementById('preview')
  if (event.target.files.length > 0) {
    output.src = URL.createObjectURL(event.target.files[0])
    output.onload = function () {
      URL.revokeObjectURL(output.src) // free memory
    }
  }
}

document.addEventListener('DOMContentLoaded', function () {
  // =====================================
  // VARIABEL GLOBAL DAN INISIALISASI
  // =====================================

  // Variabel untuk pagination
  var currentPage = 1
  var totalPages = 1
  var currentKeyword = ''
  var itemsPerPage = 10

  // Ambil brand dan API ID dari form
  var selectedBrand =
    document.querySelector('input[name="product_brand"]')?.value || ''
  var apiId = document.getElementById('api_id')?.value || ''
  var isLegacyProduct = !apiId // Deteksi produk lama

  // Referensi elemen DOM
  var searchInput = document.getElementById('search-products')
  var searchButton = document.getElementById('search-button')
  var productList = document.getElementById('product-list')
  var prevPageBtn = document.getElementById('prev-page')
  var nextPageBtn = document.getElementById('next-page')
  var currentPageSpan = document.getElementById('current-page')
  var totalPagesSpan = document.getElementById('total-pages')

  // =====================================
  // ENHANCED DEBUGGING DAN LOGGING
  // =====================================

  // Enhanced logging untuk debugging
  console.group('🔍 Product Edit Debug Info')
  console.log('Selected Brand:', selectedBrand)
  console.log('API ID dari input hidden:', apiId)
  console.log('Is Legacy Product:', isLegacyProduct)

  // Ambil informasi dari form untuk cross-check
  var productId =
    document.querySelector('input[name="product_id"]')?.value || ''
  var productName = document.getElementById('product_name')?.value || ''
  var productCode = document.getElementById('product_code')?.value || ''

  console.log('Product ID:', productId)
  console.log('Product Name:', productName)
  console.log('Product Code:', productCode)

  // Check apakah ada debug info di DOM
  var debugInfo = document.querySelector('.alert .text-muted:last-child')
  if (debugInfo) {
    console.log('Debug info from template:', debugInfo.textContent.trim())
  }
  console.groupEnd()

  // =====================================
  // UTILITY FUNCTIONS
  // =====================================

  // Format angka dengan pemisah ribuan
  function formatNumber (number) {
    return new Intl.NumberFormat('id-ID').format(number)
  }

  // Fungsi untuk logging yang lebih detail
  function logApiResponse (action, data) {
    console.group(`📡 API Response - ${action}`)
    console.log('Timestamp:', new Date().toISOString())
    console.log('Data:', data)

    var foundCurrentProduct = false
    if (data && data.data && data.data.length > 0) {
      console.log('First product:', data.data[0])
      console.log('Total products in response:', data.data.length)

      // Cari produk yang sedang diedit berdasarkan API ID
      var currentProduct = data.data.find(p => p.product_id == apiId)
      if (currentProduct) {
        console.log('🎯 Current editing product found in API:', currentProduct)
        foundCurrentProduct = true
      } else {
        console.warn('⚠️ Current editing product NOT found in API results')
      }
    }
    console.groupEnd()
    return foundCurrentProduct
  }

  // Show loading state
  function showLoading () {
    if (productList) {
      productList.innerHTML =
        '<tr><td colspan="5" class="text-center p-3">' +
        '<div class="spinner-border spinner-border-sm text-primary" role="status">' +
        '<span class="visually-hidden">Loading...</span>' +
        '</div>' +
        '<span class="ms-2">Memuat produk...</span>' +
        '</td></tr>'
    }
  }

  // Show error state
  function showError (error) {
    if (productList) {
      productList.innerHTML =
        '<tr><td colspan="5" class="text-center p-3">' +
        '<div class="text-danger">' +
        '<i class="fas fa-exclamation-circle me-2"></i> ' +
        'Error memuat produk: ' +
        (error.message || 'Unknown error') +
        '</div>' +
        '<button class="btn btn-sm btn-outline-primary mt-2" onclick="location.reload()">' +
        '<i class="fas fa-refresh me-1"></i> Muat Ulang' +
        '</button>' +
        '</td></tr>'
    }
  }

  // Show empty state
  function showEmpty () {
    if (productList) {
      productList.innerHTML =
        '<tr><td colspan="5" class="text-center p-3">' +
        '<div class="text-muted">' +
        '<i class="fas fa-info-circle me-2"></i> Tidak ada produk ditemukan' +
        '</div>' +
        '</td></tr>'
    }
  }

  // =====================================
  // API FUNCTIONS
  // =====================================

  // Fungsi untuk mencari produk current secara khusus jika tidak ditemukan
  function searchForCurrentProduct () {
    if (!apiId || !selectedBrand) {
      return Promise.resolve(null)
    }

    console.log(
      '🔍 Searching specifically for current product with API ID:',
      apiId
    )

    var searchUrl = window.apiSearchUrl || ''
    searchUrl += '?brand=' + encodeURIComponent(selectedBrand)
    searchUrl += '&keyword=' + encodeURIComponent(apiId) // Search by API ID
    searchUrl += '&page=1&limit=50' // Get more results

    return fetch(searchUrl)
      .then(response => {
        if (!response.ok) {
          throw new Error('Search response not ok: ' + response.status)
        }
        return response.json()
      })
      .then(data => {
        if (data && data.data) {
          var currentProduct = data.data.find(p => p.product_id == apiId)
          if (currentProduct) {
            console.log('✅ Found current product via search:', currentProduct)
            return currentProduct
          }
        }
        console.warn('❌ Current product still not found via search')
        return null
      })
      .catch(error => {
        console.error('❌ Error searching for current product:', error)
        return null
      })
  }

  // Fungsi untuk memuat produk dari API dengan Promise return
  function loadProducts (page, keyword) {
    // Default values
    page = page || 1
    keyword = keyword || ''

    // Hanya lanjutkan jika brand tersedia
    if (!selectedBrand) {
      console.warn('Cannot load products: No brand selected')
      return Promise.reject(new Error('No brand selected'))
    }

    console.log(
      `🔄 Loading products - Page: ${page}, Keyword: "${keyword}", Brand: ${selectedBrand}`
    )

    // Tampilkan loading
    showLoading()

    var url = window.apiSearchUrl || ''
    url += '?brand=' + encodeURIComponent(selectedBrand)
    url += '&page=' + page
    url += '&limit=' + itemsPerPage

    if (keyword && keyword.length > 0) {
      url += '&keyword=' + encodeURIComponent(keyword)
    }

    // Tambahkan current product ID untuk filter duplikat
    if (productId) {
      url += '&current_product_id=' + encodeURIComponent(productId)
    }

    console.log('📞 API Request URL:', url)

    // Return Promise untuk compatibility dengan loadProductsWithValidation
    return fetch(url)
      .then(response => {
        console.log('📥 Response status:', response.status)
        if (!response.ok) {
          throw new Error('Network response was not ok: ' + response.status)
        }
        return response.json()
      })
      .then(data => {
        var foundCurrentProduct = logApiResponse('Product Search', data)

        // Log filter info jika tersedia
        if (data.filter_info) {
          console.group('🔍 Filter Information')
          console.log(
            'Total API Products:',
            data.filter_info.total_api_products
          )
          console.log(
            'Filtered Duplicates:',
            data.filter_info.filtered_duplicates
          )
          console.log(
            'Available Products:',
            data.filter_info.available_products
          )
          console.log(
            'Used API IDs Count:',
            data.filter_info.used_api_ids_count
          )
          console.groupEnd()
        }

        // Update pagination
        updatePagination(data.pagination)

        // Update filter info
        if (data.filter_info) {
          updateFilterInfo(data.filter_info)
        }

        // Render products table
        renderProductsTable(data.data, foundCurrentProduct, page, keyword)

        return data
      })
  }

  // Update pagination controls
  function updatePagination (pagination) {
    if (pagination) {
      currentPage = parseInt(pagination.current)
      totalPages = parseInt(pagination.pages)

      if (currentPageSpan) currentPageSpan.textContent = currentPage
      if (totalPagesSpan) totalPagesSpan.textContent = totalPages

      // Enable/disable pagination buttons
      if (prevPageBtn) prevPageBtn.disabled = currentPage <= 1
      if (nextPageBtn) nextPageBtn.disabled = currentPage >= totalPages
    }
  }

  // Update filter info display
  function updateFilterInfo (filterInfo) {
    var filterInfoSpan = document.getElementById('filter-info')
    if (filterInfoSpan && filterInfo) {
      var infoText = ''
      if (filterInfo.filtered_duplicates > 0) {
        infoText = `(${filterInfo.filtered_duplicates} produk disembunyikan)`
      }
      filterInfoSpan.textContent = infoText
      filterInfoSpan.className =
        filterInfo.filtered_duplicates > 0 ? 'ms-2 text-warning' : 'ms-2'
    }
  }

  // Render products table
  function renderProductsTable (products, foundCurrentProduct, page, keyword) {
    if (!productList) return

    if (!products || products.length === 0) {
      showEmpty()
      return
    }

    var html = ''
    var currentProductFound = false

    for (var i = 0; i < products.length; i++) {
      var product = products[i]
      var productJson = JSON.stringify(product).replace(/'/g, '&#39;')

      // Highlight jika ini adalah produk yang sedang diedit
      var isCurrentProduct = !isLegacyProduct && product.product_id == apiId
      if (isCurrentProduct) {
        currentProductFound = true
        console.log('✅ Found current product in API results:', product)
      }

      var rowClass = isCurrentProduct
        ? 'table-primary product-row'
        : 'product-row'
      var badgeClass = isCurrentProduct ? 'success' : 'primary'
      var iconClass = isCurrentProduct
        ? 'check'
        : isLegacyProduct
        ? 'link'
        : 'sync'

      html += buildProductRow(
        product,
        productJson,
        rowClass,
        badgeClass,
        iconClass,
        isCurrentProduct
      )
    }

    productList.innerHTML = html

    // Inisialisasi event listeners untuk baris produk
    initProductRowListeners()

    // Jika produk current tidak ditemukan di halaman ini dan ini adalah halaman pertama tanpa keyword
    if (!foundCurrentProduct && !isLegacyProduct && page === 1 && !keyword) {
      console.log('🔍 Current product not found in first page, searching...')
      searchForCurrentProduct()
        .then(currentProduct => {
          if (currentProduct && productList) {
            addCurrentProductToTable(currentProduct)
          }
        })
        .catch(error => {
          console.error('Error adding current product:', error)
        })
    }
  }

  // Build product row HTML
  function buildProductRow (
    product,
    productJson,
    rowClass,
    badgeClass,
    iconClass,
    isCurrentProduct
  ) {
    // Tambahkan badge untuk produk yang sedang diedit
    var editingBadge = ''
    if (product.is_current_editing) {
      editingBadge = ' <span class="badge bg-info ms-1">EDITING</span>'
      rowClass += ' border-info'
    } else if (isCurrentProduct) {
      editingBadge = ' <span class="badge bg-success ms-1">CURRENT</span>'
    }

    // Tambahkan indikator ketersediaan
    var availabilityInfo = ''
    if (product.is_available === false) {
      availabilityInfo = ' <span class="badge bg-danger ms-1">USED</span>'
      rowClass += ' table-secondary'
      badgeClass = 'secondary'
    }

    return (
      '<tr class="' +
      rowClass +
      '" data-product=\'' +
      productJson +
      "'>" +
      '<td><span class="badge bg-light text-dark">' +
      product.product_code +
      '</span></td>' +
      '<td>' +
      product.product_name +
      editingBadge +
      availabilityInfo +
      '</td>' +
      '<td>' +
      (product.category_name || '') +
      (product.local_category_id
        ? ' <span class="badge bg-success small">Mapped</span>'
        : '') +
      '</td>' +
      '<td class="text-end">Rp ' +
      formatNumber(product.product_price) +
      '</td>' +
      '<td class="text-center">' +
      '<button type="button" class="btn btn-sm btn-' +
      badgeClass +
      ' select-product"' +
      (product.is_available === false
        ? ' disabled title="Produk sudah digunakan"'
        : '') +
      '>' +
      '<i class="fas fa-' +
      iconClass +
      '"></i>' +
      '</button>' +
      '</td>' +
      '</tr>'
    )
  }

  // Add current product to table header
  function addCurrentProductToTable (currentProduct) {
    var currentProductJson = JSON.stringify(currentProduct).replace(
      /'/g,
      '&#39;'
    )
    var currentProductRow =
      '<tr class="table-warning product-row border-warning" data-product=\'' +
      currentProductJson +
      "' style='border: 2px solid #ffc107 !important;'>" +
      '<td><span class="badge bg-warning text-dark">' +
      currentProduct.product_code +
      '</span></td>' +
      '<td>' +
      currentProduct.product_name +
      ' <span class="badge bg-warning text-dark ms-1">EDITING</span></td>' +
      '<td>' +
      (currentProduct.category_name || '') +
      (currentProduct.local_category_id
        ? ' <span class="badge bg-success small">Mapped</span>'
        : '') +
      '</td>' +
      '<td class="text-end">Rp ' +
      formatNumber(currentProduct.product_price) +
      '</td>' +
      '<td class="text-center">' +
      '<button type="button" class="btn btn-sm btn-warning select-product">' +
      '<i class="fas fa-edit"></i>' +
      '</button>' +
      '</td>' +
      '</tr>'

    // Prepend current product row
    productList.innerHTML = currentProductRow + productList.innerHTML

    // Re-initialize event listeners
    initProductRowListeners()

    console.log('✅ Added current product to table header')
  }

  // Fixed loadProductsWithValidation function
  function loadProductsWithValidation (page, keyword) {
    console.log('🔄 Loading products with validation:', {
      page,
      keyword,
      brand: selectedBrand
    })

    // Call loadProducts and properly handle the Promise
    return loadProducts(page, keyword).catch(function (error) {
      console.error('❌ Error loading products:', error)
      showError(error)
      throw error // Re-throw for upstream handling
    })
  }

  // =====================================
  // FORM UPDATE FUNCTIONS
  // =====================================

  // Fungsi untuk memperbarui form dengan data API
  function updateFormWithApiProduct (productData) {
    console.log('🔄 Updating form with API product:', productData)

    // Validate product data first
    if (!productData || !productData.product_id) {
      console.error('Invalid product data:', productData)
      alert('Data produk tidak valid')
      return
    }

    // Populate the form with product data
    var apiIdInput = document.getElementById('api_id')
    var productCodeInput = document.getElementById('product_code')
    var productNameInput = document.getElementById('product_name')
    var productPriceInput = document.getElementById('product_price')
    var infoElement = document.getElementById('current-product-info')

    // Untuk produk lama, aktifkan field yang sebelumnya readonly
    if (isLegacyProduct) {
      if (productCodeInput) {
        productCodeInput.readOnly = true
        productCodeInput.classList.add('bg-light')
      }
      if (productNameInput) {
        productNameInput.readOnly = true
        productNameInput.classList.add('bg-light')
      }
      if (productPriceInput) {
        productPriceInput.readOnly = true
        productPriceInput.classList.add('bg-light')
      }
    }

    // Update nilai form
    if (apiIdInput) apiIdInput.value = productData.product_id || ''
    if (productCodeInput)
      productCodeInput.value = productData.product_code || ''
    if (productNameInput)
      productNameInput.value = productData.product_name || ''
    if (productPriceInput)
      productPriceInput.value = productData.product_price || 0

    // Update tampilan informasi produk dengan status validasi
    updateProductInfo(productData)

    // Try to find a matching local category based on API category
    updateCategorySelection(productData)

    // Enable tombol submit
    enableSubmitButton()
  }

  // Update product info display
  function updateProductInfo (productData) {
    var infoElement = document.getElementById('current-product-info')
    if (!infoElement) return

    var alertClass = 'alert-success'
    var statusIcon = 'fas fa-check-circle'
    var statusText = 'Data telah diperbarui dari API'

    if (isLegacyProduct) {
      alertClass = 'alert-warning'
      statusIcon = 'fas fa-link'
      statusText = 'Produk ini akan terhubung dengan produk API'
    }

    infoElement.innerHTML =
      '<div class="alert ' +
      alertClass +
      ' mb-0">' +
      '<h6 class="mb-1">' +
      productData.product_name +
      '</h6>' +
      '<div class="small">Kode: ' +
      productData.product_code +
      ' | Kategori: ' +
      (productData.category_name || 'N/A') +
      '</div>' +
      '<div class="fw-bold mt-1">Rp ' +
      formatNumber(productData.product_price) +
      '</div>' +
      '<div class="mt-2 small">' +
      '<i class="' +
      statusIcon +
      ' me-1"></i> ' +
      statusText +
      '</div></div>'
  }

  // Update category selection
  function updateCategorySelection (productData) {
    if (productData.category_name && productData.local_category_id) {
      var catIdSelect = document.getElementById('cat_id')
      if (catIdSelect) {
        catIdSelect.value = productData.local_category_id

        // Attempt to initialize select2 if available
        try {
          if (typeof $ !== 'undefined' && $.fn.select2) {
            $(catIdSelect).trigger('change')
          }
        } catch (e) {
          console.log('Select2 not available or already initialized')
        }
      }
    }
  }

  // Enable submit button
  function enableSubmitButton () {
    var submitButton = document.getElementById('submitButton')
    if (submitButton) {
      submitButton.disabled = false
      if (isLegacyProduct) {
        submitButton.innerHTML =
          '<i class="fas fa-link me-2"></i> Hubungkan & Simpan'
        submitButton.classList.add('btn-warning')
        submitButton.classList.remove('btn-primary')
      }
    }
  }

  // =====================================
  // EVENT LISTENERS
  // =====================================

  // Fungsi untuk menginisialisasi event listeners pada baris produk
  function initProductRowListeners () {
    // Make the table rows clickable
    var productRows = document.querySelectorAll('.product-row')
    if (!productRows.length) return

    productRows.forEach(function (row) {
      row.addEventListener('click', function () {
        // Find the select button in this row and click it
        var selectButton = this.querySelector('.select-product')
        if (selectButton) {
          selectButton.click()
        }
      })
    })

    // Handle select product button
    var selectProductButtons = document.querySelectorAll('.select-product')
    selectProductButtons.forEach(function (button) {
      button.addEventListener('click', function (e) {
        e.stopPropagation() // Prevent triggering row click again

        // Skip jika button disabled
        if (this.disabled) {
          console.warn('Cannot select disabled product')
          return
        }

        // Reset all rows
        productRows.forEach(function (r) {
          r.classList.remove('table-primary', 'table-warning')
        })

        // Highlight the selected row
        var row = this.closest('.product-row')
        row.classList.add('table-primary')

        try {
          var productData = JSON.parse(row.getAttribute('data-product'))
          console.log('🎯 Selected product from API:', productData)

          // Cek apakah produk tersedia untuk dipilih
          if (productData.is_available === false) {
            alert(
              'Produk ini sudah digunakan oleh produk lain dan tidak dapat dipilih.'
            )
            return
          }

          updateFormWithApiProduct(productData)
        } catch (e) {
          console.error('❌ Error parsing product data:', e)
          alert('Error saat memilih produk: ' + e.message)
        }
      })
    })
  }

  // Event listener untuk tombol search
  if (searchButton) {
    searchButton.addEventListener('click', function () {
      currentKeyword = searchInput ? searchInput.value.trim() : ''
      loadProductsWithValidation(1, currentKeyword).catch(error =>
        console.error('Search error:', error)
      )
    })
  }

  // Event listener untuk input search (ketika tekan Enter)
  if (searchInput) {
    searchInput.addEventListener('keypress', function (e) {
      if (e.key === 'Enter') {
        currentKeyword = this.value.trim()
        loadProductsWithValidation(1, currentKeyword).catch(error =>
          console.error('Search error:', error)
        )
        e.preventDefault()
      }
    })
  }

  // Event listener untuk tombol pagination
  if (prevPageBtn) {
    prevPageBtn.addEventListener('click', function () {
      if (currentPage > 1) {
        loadProductsWithValidation(currentPage - 1, currentKeyword).catch(
          error => console.error('Pagination error:', error)
        )
      }
    })
  }

  if (nextPageBtn) {
    nextPageBtn.addEventListener('click', function () {
      if (currentPage < totalPages) {
        loadProductsWithValidation(currentPage + 1, currentKeyword).catch(
          error => console.error('Pagination error:', error)
        )
      }
    })
  }

  // =====================================
  // QUICK SELECTOR DAN UNLINK API
  // =====================================

  // Quick selector untuk produk serupa
  var quickSelectButtons = document.querySelectorAll('.quick-select-product')
  if (quickSelectButtons.length > 0) {
    quickSelectButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        try {
          var productData = JSON.parse(this.getAttribute('data-product'))
          updateFormWithApiProduct(productData)

          // Highlight yang dipilih
          quickSelectButtons.forEach(function (btn) {
            btn.classList.remove('active')
          })
          this.classList.add('active')
        } catch (e) {
          console.error('Error parsing product data:', e)
        }
      })
    })
  }

  // Unlink API functionality
  var unlinkApiBtn = document.getElementById('unlinkApiBtn')
  var confirmUnlinkBtn = document.getElementById('confirmUnlinkBtn')

  if (unlinkApiBtn) {
    unlinkApiBtn.addEventListener('click', function () {
      var unlinkApiModal = new bootstrap.Modal(
        document.getElementById('unlinkApiModal')
      )
      unlinkApiModal.show()
    })
  }

  if (confirmUnlinkBtn) {
    confirmUnlinkBtn.addEventListener('click', function () {
      // Show loading state
      this.innerHTML =
        '<i class="fas fa-spinner fa-spin me-1"></i> Memproses...'
      this.disabled = true

      // Get current product ID from URL or form
      var currentUrl = window.location.pathname
      var pathSegments = currentUrl.split('/')
      var productId = pathSegments[pathSegments.length - 1]

      // Redirect to unlink endpoint
      window.location.href =
        window.location.origin + '/master/products/unlinkFromApi/' + productId
    })
  }

  // =====================================
  // SYNC ALL FUNCTIONALITY
  // =====================================

  // Quick update dari tombol sync all
  var syncAllBtn = document.getElementById('sync-api-btn')
  if (syncAllBtn) {
    syncAllBtn.addEventListener('click', function () {
      // Get current API product data from template variables
      var currentApiProduct = window.currentApiProduct || {}

      console.log('Syncing all data from API:', currentApiProduct)

      // Update form fields
      if (currentApiProduct.product_name) {
        var productNameInput = document.getElementById('product_name')
        if (productNameInput)
          productNameInput.value = currentApiProduct.product_name
      }

      if (currentApiProduct.product_code) {
        var productCodeInput = document.getElementById('product_code')
        if (productCodeInput)
          productCodeInput.value = currentApiProduct.product_code
      }

      if (currentApiProduct.product_price) {
        var productPriceInput = document.getElementById('product_price')
        if (productPriceInput)
          productPriceInput.value = currentApiProduct.product_price
      }

      // Update info display
      var infoElement = document.getElementById('current-product-info')
      if (infoElement && currentApiProduct.product_name) {
        infoElement.innerHTML =
          '<div class="alert alert-success mb-0">' +
          '<h6 class="mb-1">' +
          currentApiProduct.product_name +
          '</h6>' +
          '<div class="small">Kode: ' +
          (currentApiProduct.product_code || '') +
          ' | Kategori: ' +
          (currentApiProduct.category_name || '') +
          '</div>' +
          '<div class="fw-bold mt-1">Rp ' +
          formatNumber(currentApiProduct.product_price || 0) +
          '</div>' +
          '<div class="mt-2 small"><i class="fas fa-check-circle me-1"></i> Data telah diperbarui dari API</div>' +
          '</div>'
      }
    })
  }

  // =====================================
  // INITIALIZATION
  // =====================================

  // Load produk saat halaman dimuat
  if (selectedBrand) {
    console.log('🚀 Auto-loading products for brand:', selectedBrand)
    loadProductsWithValidation(1, '').catch(error =>
      console.error('Initial load error:', error)
    )
  } else {
    console.warn('No brand selected, skipping auto-load')
  }

  // Global error handler
  window.addEventListener('error', function (e) {
    console.error('Global error:', e.error)
  })

  // Global unhandled promise rejection handler
  window.addEventListener('unhandledrejection', function (e) {
    console.error('Unhandled promise rejection:', e.reason)
  })

  // =====================================
  // EXPOSE FUNCTIONS TO GLOBAL SCOPE
  // =====================================

  // Make some functions available globally if needed
  window.editPageFunctions = {
    loadProducts: loadProducts,
    updateFormWithApiProduct: updateFormWithApiProduct,
    searchForCurrentProduct: searchForCurrentProduct,
    formatNumber: formatNumber
  }

  console.log('✅ Edit page JavaScript initialized successfully')
})
