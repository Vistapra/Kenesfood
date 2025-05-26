// Utility Functions
function formatNumber (number) {
  return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

function formatDate (date) {
  const options = { day: 'numeric', month: 'short', year: 'numeric' }
  return date.toLocaleDateString('id-ID', options)
}

// Promo Filtering Functions
function filterPromoByBrand () {
  const brand = document.getElementById('brand').value
  const bakeryGroup = document.getElementById('bakery_promos')
  const restoGroup = document.getElementById('resto_promos')
  const kopitiamGroup = document.getElementById('kopitiam_promos')

  if (!bakeryGroup || !restoGroup || !kopitiamGroup) {
    console.error('Brand groups not found')
    return
  }

  // Hide all promo groups
  ;[bakeryGroup, restoGroup, kopitiamGroup].forEach(group => {
    group.style.display = 'none'
  })

  // Show selected brand promos
  const brandGroups = {
    bakery: bakeryGroup,
    resto: restoGroup,
    kopitiam: kopitiamGroup
  }

  if (brandGroups[brand]) {
    brandGroups[brand].style.display = 'block'
  }

  // Reset promo code selection
  const promoSelect = document.getElementById('promo_code')
  promoSelect.value = ''

  // Hide promo details
  const promoDetails = document.getElementById('promo_details')
  if (promoDetails) {
    promoDetails.style.display = 'none'
  }
}

function selectPromo (promoCode, brand) {
  // Set brand
  document.getElementById('brand').value = brand

  // Filter promos by brand
  filterPromoByBrand()

  // Set promo code
  const promoSelect = document.getElementById('promo_code')
  promoSelect.value = promoCode

  // Trigger change event
  const changeEvent = new Event('change')
  promoSelect.dispatchEvent(changeEvent)

  // Update promo details
  updatePromoDetails()

  // Scroll to form
  promoSelect.closest('form').scrollIntoView({ behavior: 'smooth' })
}

// Promo Details and Calculation Functions
function updatePromoDetails () {
  var promoSelect = document.getElementById('promo_code')
  var detailsElement = document.getElementById('promo_details')

  if (!promoSelect || !detailsElement) return

  var selectedOption = promoSelect.options[promoSelect.selectedIndex]
  if (!selectedOption || !selectedOption.value) {
    detailsElement.style.display = 'none'
    return
  }

  try {
    var promoInfo = JSON.parse(selectedOption.getAttribute('data-info'))

    // Update type
    document.getElementById('detail_type').textContent =
      promoInfo.promo_type === 'percentage' ? 'Persentase' : 'Nominal'

    // Update value
    var valueElement = document.getElementById('detail_value')
    if (promoInfo.promo_type === 'percentage') {
      valueElement.textContent = promoInfo.promo_value + '%'
      if (promoInfo.maximum_discount) {
        valueElement.textContent +=
          ' (Maks: Rp ' + formatNumber(promoInfo.maximum_discount) + ')'
      }
    } else {
      valueElement.textContent = 'Rp ' + formatNumber(promoInfo.promo_value)
    }

    // Update minimum order
    document.getElementById('detail_minimum').textContent =
      promoInfo.minimum_order > 0
        ? 'Rp ' + formatNumber(promoInfo.minimum_order)
        : 'Tidak ada minimum'

    // Update maximum discount
    document.getElementById('detail_maximum').textContent =
      promoInfo.maximum_discount
        ? 'Rp ' + formatNumber(promoInfo.maximum_discount)
        : 'Tidak ada maksimum'

    // Update period
    var startDate = new Date(promoInfo.start_date)
    var endDate = new Date(promoInfo.end_date)
    document.getElementById('detail_period').textContent =
      formatDate(startDate) + ' - ' + formatDate(endDate)

    // Update status
    document.getElementById('detail_status').textContent =
      promoInfo.promo_status === 'active' ? 'Aktif' : 'Non-aktif'

    // Show details
    detailsElement.style.display = 'block'
    detailsElement.classList.add('fade-in')
  } catch (error) {
    console.error('Error parsing promo details:', error)
    detailsElement.style.display = 'none'
  }

  // Update calculation
  updateCalculation()
}

function updateCalculation () {
  var promoSelect = document.getElementById('promo_code')
  var orderTotalInput = document.getElementById('order_total')

  if (!promoSelect || !orderTotalInput) return

  var selectedOption = promoSelect.options[promoSelect.selectedIndex]
  if (!selectedOption || !selectedOption.value) return

  try {
    var promoInfo = JSON.parse(selectedOption.getAttribute('data-info'))
    var orderTotal = parseFloat(orderTotalInput.value) || 0
    var minOrder = parseFloat(promoInfo.minimum_order) || 0

    // Check minimum order
    if (orderTotal < minOrder && minOrder > 0) {
      return // Not eligible for discount
    }

    // Calculate discount
    var discount = 0
    var discountLabel = ''

    if (promoInfo.promo_type === 'percentage') {
      discount = orderTotal * (promoInfo.promo_value / 100)
      discountLabel = promoInfo.promo_value + '%'

      // Apply maximum discount if set
      if (
        promoInfo.maximum_discount > 0 &&
        discount > promoInfo.maximum_discount
      ) {
        discount = promoInfo.maximum_discount
        discountLabel +=
          ' (Maks: Rp ' + formatNumber(promoInfo.maximum_discount) + ')'
      }
    } else {
      // Nominal
      discount = parseFloat(promoInfo.promo_value) || 0
      discountLabel = 'Rp ' + formatNumber(promoInfo.promo_value)

      // Ensure discount doesn't exceed order total
      if (discount > orderTotal) {
        discount = orderTotal
      }
    }

    // Update calculation display
    var calcDiscount = document.getElementById('calc_discount')
    var calcFinal = document.getElementById('calc_final')
    var calcDiscountLabel = document.getElementById('calc_discount_label')

    if (calcDiscount && calcFinal && calcDiscountLabel) {
      calcDiscount.textContent = formatNumber(discount)
      calcFinal.textContent = formatNumber(orderTotal - discount)
      calcDiscountLabel.textContent = discountLabel
    }
  } catch (error) {
    console.error('Calculation error:', error)
  }
}

function validateManualCode () {
  var brand = document.getElementById('manual_brand').value
  var code = document.getElementById('manual_code').value
  var total = document.getElementById('manual_total').value
  var resultDiv = document.getElementById('manual_result')

  if (!resultDiv) return

  // Input validation
  if (!code) {
    resultDiv.innerHTML =
      '<div class="alert alert-danger">Masukkan kode promo</div>'
    resultDiv.style.display = 'block'
    return
  }

  // Show loading
  resultDiv.innerHTML =
    '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Memvalidasi...</div>'
  resultDiv.style.display = 'block'

  // Send validation request
  fetch('{site_url("promo/MasterPromo/validatePromoCode")}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body:
      'promo_code=' +
      encodeURIComponent(code) +
      '&order_total=' +
      encodeURIComponent(total) +
      '&brand=' +
      encodeURIComponent(brand)
  })
    .then(function (response) {
      return response.json()
    })
    .then(function (data) {
      if (data.success) {
        resultDiv.innerHTML =
          '<div class="alert alert-success">' +
          '<strong>Promo Valid!</strong><br>' +
          'Diskon: Rp ' +
          formatNumber(data.discount_amount) +
          '<br>' +
          'Total Setelah Diskon: Rp ' +
          formatNumber(data.final_amount) +
          '</div>'
      } else {
        resultDiv.innerHTML =
          '<div class="alert alert-danger">' +
          '<strong>Promo Tidak Valid:</strong> ' +
          data.message +
          '</div>'
      }
    })
    .catch(function (error) {
      console.error('Validation Error:', error)
      resultDiv.innerHTML =
        '<div class="alert alert-danger">Terjadi kesalahan saat memvalidasi</div>'
    })
}

// Bundling Promo Functions
function filterBundlingPromoByBrand () {
  const brand = document.getElementById('bundling_brand').value
  const brandGroups = {
    bakery: document.getElementById('bakery_bundling_promos'),
    resto: document.getElementById('resto_bundling_promos'),
    kopitiam: document.getElementById('kopitiam_bundling_promos')
  }

  // Hide all groups
  Object.values(brandGroups).forEach(group => {
    if (group) group.style.display = 'none'
  })

  // Show selected brand group
  if (brandGroups[brand]) {
    brandGroups[brand].style.display = 'block'
  }

  // Reset promo code
  const promoSelect = document.getElementById('bundling_promo_code')
  if (promoSelect) {
    promoSelect.value = ''
    promoSelect.dispatchEvent(new Event('change'))
  }

  // Hide bundling details
  const detailsElement = document.getElementById('bundling_details')
  if (detailsElement) {
    detailsElement.style.display = 'none'
  }
}

function selectBundlingPromo (promoCode, brand) {
  // Set brand
  document.getElementById('bundling_brand').value = brand

  // Filter promos
  filterBundlingPromoByBrand()

  // Set promo code
  const promoSelect = document.getElementById('bundling_promo_code')
  promoSelect.value = promoCode

  // Trigger change event
  promoSelect.dispatchEvent(new Event('change'))

  // Scroll to form
  promoSelect.closest('form').scrollIntoView({ behavior: 'smooth' })
}

function loadBundlingDetails () {
  var promoSelect = document.getElementById('bundling_promo_code')
  var detailsElement = document.getElementById('bundling_details')
  var rulesContainer = document.getElementById('bundling_rules')

  if (!promoSelect || !detailsElement || !rulesContainer) return

  var selectedOption = promoSelect.options[promoSelect.selectedIndex]
  if (!selectedOption || !selectedOption.value) {
    detailsElement.style.display = 'none'
    return
  }

  // Show loading
  detailsElement.style.display = 'block'
  rulesContainer.innerHTML =
    '<div class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i> Memuat detail bundling...</div>'

  var promoId = selectedOption.getAttribute('data-id')

  fetch('{site_url("promo/MasterPromo/getBundlingDetails")}/' + promoId)
    .then(function (response) {
      return response.json()
    })
    .then(function (data) {
      if (data.success && data.bundles && data.bundles.length > 0) {
        var bundlesHtml = data.bundles
          .map(function (bundle, index) {
            return (
              '<div class="bundling-rule-item">' +
              '<div class="d-flex justify-content-between">' +
              '<div class="fw-medium">Bundle #' +
              (index + 1) +
              '</div>' +
              '</div>' +
              '<div class="mt-2">' +
              '<div class="mb-1">' +
              'Beli <span class="fw-bold">' +
              bundle.min_quantity1 +
              ' x ' +
              bundle.required_product_name1 +
              '</span>' +
              '</div>' +
              '<div class="mb-1">' +
              'dan <span class="fw-bold">' +
              bundle.min_quantity2 +
              ' x ' +
              bundle.required_product_name2 +
              '</span>' +
              '</div>' +
              '<div class="text-success">' +
              '<i class="fas fa-gift me-1"></i> ' +
              'Gratis <span class="fw-bold">' +
              bundle.free_quantity +
              ' x ' +
              bundle.free_product_name +
              '</span>' +
              '</div>' +
              '</div>' +
              '</div>'
            )
          })
          .join('')

        rulesContainer.innerHTML = bundlesHtml
      } else {
        rulesContainer.innerHTML =
          '<div class="alert alert-warning">Tidak ada konfigurasi bundling yang tersedia.</div>'
      }
    })
    .catch(function (error) {
      console.error('Bundling details error:', error)
      rulesContainer.innerHTML =
        '<div class="alert alert-danger">Gagal memuat detail bundling.</div>'
    })
}

// Cart Management Functions
function addCartItem () {
  var container = document.getElementById('cart_items')
  var items = container.querySelectorAll('.cart-item')
  var nextIndex = items.length

  var newItem = document.createElement('div')
  newItem.className = 'cart-item mb-2 p-2 border rounded fade-in'
  newItem.setAttribute('data-index', nextIndex)

  newItem.innerHTML =
    '<div class="row g-2">' +
    '<div class="col-md-6">' +
    '<select class="form-select cart-product" name="cart_items[' +
    nextIndex +
    '][product_id]" required>' +
    '<option value="">Pilih Produk</option>' +
    generateProductOptions() +
    '</select>' +
    '</div>' +
    '<div class="col-md-4">' +
    '<div class="input-group">' +
    '<span class="input-group-text">Qty</span>' +
    '<input type="number" class="form-control cart-quantity" name="cart_items[' +
    nextIndex +
    '][quantity]" min="1" value="1" required>' +
    '</div>' +
    '</div>' +
    '<div class="col-md-2">' +
    '<button type="button" class="btn btn-outline-danger btn-sm w-100 remove-item" onclick="removeCartItem(this)">' +
    '<i class="fas fa-trash"></i>' +
    '</button>' +
    '</div>' +
    '</div>'

  container.appendChild(newItem)

  // Inisialisasi Select2 jika tersedia
  try {
    $(newItem).find('.cart-product').select2({
      placeholder: 'Pilih Produk',
      width: '100%'
    })
  } catch (error) {
    console.warn('Select2 initialization failed:', error)
  }
}

function removeCartItem (button) {
  var cartItem = button.closest('.cart-item')
  var container = document.getElementById('cart_items')
  var items = container.querySelectorAll('.cart-item')

  if (items.length > 1) {
    cartItem.remove()

    // Reindex remaining items
    container.querySelectorAll('.cart-item').forEach(function (item, index) {
      item.setAttribute('data-index', index)

      // Update product select name
      var productSelect = item.querySelector('.cart-product')
      if (productSelect) {
        productSelect.name = 'cart_items[' + index + '][product_id]'
      }

      // Update quantity input name
      var quantityInput = item.querySelector('.cart-quantity')
      if (quantityInput) {
        quantityInput.name = 'cart_items[' + index + '][quantity]'
      }
    })
  } else {
    alert('Minimal satu produk harus ada di keranjang.')
  }
}

function generateProductOptions () {
  var brands = [
    { name: 'Bakery', selector: '.bakery-products' },
    { name: 'Resto', selector: '.resto-products' },
    { name: 'Kopitiam', selector: '.kopitiam-products' }
  ]

  var optgroupHtml = brands
    .map(function (brand) {
      var productElements = document.querySelectorAll(
        brand.selector + ' option'
      )
      var productOptions = Array.prototype.slice
        .call(productElements)
        .filter(function (opt) {
          return opt.value && opt.value !== ''
        })
        .map(function (opt) {
          return (
            '<option value="' +
            opt.value +
            '" data-brand="' +
            brand.name.toLowerCase() +
            '">' +
            opt.text +
            '</option>'
          )
        })
        .join('')

      return (
        '<optgroup label="' + brand.name + '">' + productOptions + '</optgroup>'
      )
    })
    .join('')

  return optgroupHtml
}

// BOGO Specific Functions
function filterBogoPromoByBrand () {
  const brand = document.getElementById('bogo_brand').value
  const brandGroups = {
    bakery: document.getElementById('bakery_bogo_promos'),
    resto: document.getElementById('resto_bogo_promos'),
    kopitiam: document.getElementById('kopitiam_bogo_promos')
  }

  // Hide all groups
  Object.values(brandGroups).forEach(group => {
    if (group) group.style.display = 'none'
  })

  // Show selected brand group
  if (brandGroups[brand]) {
    brandGroups[brand].style.display = 'block'
  }

  // Reset promo code
  const promoSelect = document.getElementById('bogo_promo_code')
  if (promoSelect) {
    promoSelect.value = ''
    promoSelect.dispatchEvent(new Event('change'))
  }

  // Hide BOGO details
  const detailsElement = document.getElementById('bogo_details')
  if (detailsElement) {
    detailsElement.style.display = 'none'
  }
}

function selectBogoPromo (promoCode, brand) {
  // Set brand
  document.getElementById('bogo_brand').value = brand

  // Filter promos
  filterBogoPromoByBrand()

  // Set promo code
  const promoSelect = document.getElementById('bogo_promo_code')
  promoSelect.value = promoCode

  // Trigger change event
  promoSelect.dispatchEvent(new Event('change'))

  // Scroll to form
  promoSelect.closest('form').scrollIntoView({ behavior: 'smooth' })
}

function loadBogoDetails () {
  var promoSelect = document.getElementById('bogo_promo_code')
  var detailsElement = document.getElementById('bogo_details')
  var rulesContainer = document.getElementById('bogo_rules')

  if (!promoSelect || !detailsElement || !rulesContainer) return

  var selectedOption = promoSelect.options[promoSelect.selectedIndex]
  if (!selectedOption || !selectedOption.value) {
    detailsElement.style.display = 'none'
    return
  }

  // Show loading
  detailsElement.style.display = 'block'
  rulesContainer.innerHTML =
    '<div class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i> Memuat detail BOGO...</div>'

  var promoId = selectedOption.getAttribute('data-id')

  fetch('{site_url("promo/MasterPromo/getBogoDetails")}/' + promoId)
    .then(function (response) {
      return response.json()
    })
    .then(function (data) {
      if (data.success && data.bogos && data.bogos.length > 0) {
        var bogosHtml = data.bogos
          .map(function (bogo, index) {
            // Tambahkan perlindungan untuk setiap properti
            var buyQuantity = bogo.buy_quantity || 1
            var freeQuantity = bogo.free_quantity || 1
            var productName = bogo.product_name || 'Produk'
            var freeProductName = bogo.free_product_name || productName
            var maxApplyCount = bogo.max_apply_count
              ? '<div class="text-muted small">Maks. berlaku ' +
                bogo.max_apply_count +
                ' kali per order</div>'
              : ''

            return (
              '<div class="bogo-rule-item">' +
              '<div class="d-flex justify-content-between">' +
              '<div class="fw-medium">BOGO #' +
              (index + 1) +
              '</div>' +
              '</div>' +
              '<div class="mt-2">' +
              '<div class="mb-1">' +
              'Beli <span class="fw-bold">' +
              buyQuantity +
              ' x ' +
              productName +
              '</span>' +
              '</div>' +
              '<div class="text-success">' +
              '<i class="fas fa-gift me-1"></i> ' +
              'Gratis <span class="fw-bold">' +
              freeQuantity +
              ' x ' +
              (bogo.product_id === bogo.free_product_id
                ? 'produk yang sama'
                : freeProductName) +
              '</span>' +
              '</div>' +
              maxApplyCount +
              '</div>' +
              '</div>'
            )
          })
          .join('')

        rulesContainer.innerHTML = bogosHtml
      } else {
        rulesContainer.innerHTML =
          '<div class="alert alert-warning">Tidak ada konfigurasi BOGO yang tersedia.</div>'
      }
    })
    .catch(function (error) {
      console.error('BOGO details error:', error)
      rulesContainer.innerHTML =
        '<div class="alert alert-danger">Gagal memuat detail BOGO.</div>'
    })
}

function addBogoCartItem () {
  var container = document.getElementById('bogo_cart_items')
  var items = container.querySelectorAll('.bogo-cart-item')
  var nextIndex = items.length

  var newItem = document.createElement('div')
  newItem.className = 'bogo-cart-item mb-2 p-2 border rounded fade-in'
  newItem.setAttribute('data-index', nextIndex)

  newItem.innerHTML =
    '<div class="row g-2">' +
    '<div class="col-md-6">' +
    '<select class="form-select bogo-cart-product" name="bogo_cart_items[' +
    nextIndex +
    '][product_id]" required>' +
    '<option value="">Pilih Produk</option>' +
    generateProductOptions() +
    '</select>' +
    '</div>' +
    '<div class="col-md-4">' +
    '<div class="input-group">' +
    '<span class="input-group-text">Qty</span>' +
    '<input type="number" class="form-control bogo-cart-quantity" name="bogo_cart_items[' +
    nextIndex +
    '][quantity]" min="1" value="1" required>' +
    '</div>' +
    '</div>' +
    '<div class="col-md-2">' +
    '<button type="button" class="btn btn-outline-danger btn-sm w-100 remove-bogo-item" onclick="removeBogoCartItem(this)">' +
    '<i class="fas fa-trash"></i>' +
    '</button>' +
    '</div>' +
    '</div>'

  container.appendChild(newItem)

  // Tambahkan perlindungan untuk inisialisasi Select2
  try {
    $(newItem).find('.bogo-cart-product').select2({
      placeholder: 'Pilih Produk',
      width: '100%'
    })
  } catch (error) {
    console.warn('Select2 initialization failed:', error)
  }
}

function removeBogoCartItem (button) {
  var cartItem = button.closest('.bogo-cart-item')
  var container = document.getElementById('bogo_cart_items')
  var items = container.querySelectorAll('.bogo-cart-item')

  if (items.length > 1) {
    cartItem.remove()

    // Reindex remaining items
    container
      .querySelectorAll('.bogo-cart-item')
      .forEach(function (item, index) {
        item.setAttribute('data-index', index)

        // Update product select name
        var productSelect = item.querySelector('.bogo-cart-product')
        if (productSelect) {
          productSelect.name = 'bogo_cart_items[' + index + '][product_id]'
        }

        // Update quantity input name
        var quantityInput = item.querySelector('.bogo-cart-quantity')
        if (quantityInput) {
          quantityInput.name = 'bogo_cart_items[' + index + '][quantity]'
        }
      })
  } else {
    alert('Minimal satu produk harus ada di keranjang.')
  }
}

document.addEventListener('DOMContentLoaded', function () {
  // Inisialisasi event listener untuk berbagai elemen
  var brandSelects = {
    discount: document.getElementById('brand'),
    bundling: document.getElementById('bundling_brand'),
    bogo: document.getElementById('bogo_brand')
  }

  var promoSelects = {
    discount: document.getElementById('promo_code'),
    bundling: document.getElementById('bundling_promo_code'),
    bogo: document.getElementById('bogo_promo_code')
  }

  var filterFunctions = {
    discount: filterPromoByBrand,
    bundling: filterBundlingPromoByBrand,
    bogo: filterBogoPromoByBrand
  }

  var detailFunctions = {
    discount: updatePromoDetails,
    bundling: loadBundlingDetails,
    bogo: loadBogoDetails
  }

  // Setup event listeners for brand and promo selects
  Object.keys(brandSelects).forEach(function (type) {
    var brandSelect = brandSelects[type]
    var promoSelect = promoSelects[type]
    var filterFunc = filterFunctions[type]
    var detailFunc = detailFunctions[type]

    if (brandSelect && filterFunc) {
      brandSelect.addEventListener('change', filterFunc)
    }

    if (promoSelect && detailFunc) {
      promoSelect.addEventListener('change', detailFunc)
    }
  })

  // Initialize first tab content if needed
  const initialTab = document.querySelector('.tab-pane.active')
  if (initialTab) {
    const tabId = initialTab.id
    switch (tabId) {
      case 'discount-test-content':
        updatePromoDetails()
        break
      case 'bundling-test-content':
        loadBundlingDetails()
        break
      case 'bogo-test-content':
        loadBogoDetails()
        break
    }
  }

  // Bind manual validation
  const manualValidateBtn = document.querySelector(
    '[onclick="validateManualCode()"]'
  )
  if (manualValidateBtn) {
    manualValidateBtn.addEventListener('click', validateManualCode)
  }
})
