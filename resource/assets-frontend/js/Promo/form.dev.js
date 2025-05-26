// Fungsi untuk menampilkan/menyembunyikan bidang produk dan kategori
function toggleTargetField (type) {
  if (type === 'product') {
    var productSpecific = document.getElementById('product_specific')
    var productSelector = document.getElementById('product_selector')
    var categorySpecific = document.getElementById('category_specific')

    if (productSpecific && productSpecific.checked) {
      // Tampilkan selector produk
      if (productSelector) productSelector.style.display = 'block'

      // Reset kategori spesifik
      if (categorySpecific) {
        categorySpecific.checked = false
        var categorySelector = document.getElementById('category_selector')
        if (categorySelector) categorySelector.style.display = 'none'
      }

      // Update tampilan dan hitung produk terpilih
      updateSelectedProductsDisplay()
      updateSelectedCount()
    } else if (productSelector) {
      // Sembunyikan selector produk
      productSelector.style.display = 'none'
    }
  } else if (type === 'category') {
    var categorySpecific = document.getElementById('category_specific')
    var categorySelector = document.getElementById('category_selector')
    var productSpecific = document.getElementById('product_specific')

    if (categorySpecific && categorySpecific.checked) {
      // Tampilkan selector kategori
      if (categorySelector) categorySelector.style.display = 'block'

      // Reset produk spesifik
      if (productSpecific) {
        productSpecific.checked = false
        var productSelector = document.getElementById('product_selector')
        if (productSelector) productSelector.style.display = 'none'
      }

      // Update tampilan dan hitung kategori terpilih
      updateSelectedCategoriesDisplay()
      updateSelectedCount()
    } else if (categorySelector) {
      // Sembunyikan selector kategori
      categorySelector.style.display = 'none'
    }
  }
}

// Filter produk berdasarkan merek
function filterProductsByBrand () {
  var brandFilter = document.getElementById('product_brand_filter')
  var searchTerm = document.getElementById('product_search')

  if (!brandFilter || !searchTerm) return

  var brand = brandFilter.value
  var term = searchTerm.value.toLowerCase()

  // Dapatkan semua opsi produk
  var productOptions = document.querySelectorAll('#product_ids option')

  productOptions.forEach(function (option) {
    var text = option.textContent.toLowerCase()
    var optionBrand = option.getAttribute('data-brand')

    // Periksa apakah cocok dengan filter merek dan pencarian
    var matchBrand = brand === 'all' || optionBrand === brand
    var matchSearch = term === '' || text.includes(term)

    // Tampilkan/sembunyikan opsi
    option.style.display = matchBrand && matchSearch ? '' : 'none'
  })
}

// Filter kategori berdasarkan merek
function filterCategoriesByBrand () {
  var brandFilter = document.getElementById('category_brand_filter')
  var searchTerm = document.getElementById('category_search')

  if (!brandFilter || !searchTerm) return

  var brand = brandFilter.value
  var term = searchTerm.value.toLowerCase()

  // Dapatkan semua opsi kategori
  var categoryOptions = document.querySelectorAll('#category_ids option')

  categoryOptions.forEach(function (option) {
    var text = option.textContent.toLowerCase()
    var optionBrand = option.getAttribute('data-brand')

    // Periksa apakah cocok dengan filter merek dan pencarian
    var matchBrand = brand === 'all' || optionBrand === brand
    var matchSearch = term === '' || text.includes(term)

    // Tampilkan/sembunyikan opsi
    option.style.display = matchBrand && matchSearch ? '' : 'none'
  })
}

// Fungsi pencarian produk
function searchProducts () {
  filterProductsByBrand()
}

// Fungsi pencarian kategori
function searchCategories () {
  filterCategoriesByBrand()
}

// Update tampilan produk terpilih
function updateSelectedProductsDisplay () {
  var productSelect = document.getElementById('product_ids')
  var selectedProductsList = document.getElementById('selected_products_list')

  if (!productSelect || !selectedProductsList) return

  // Dapatkan opsi yang dipilih
  var selectedOptions = []
  for (var i = 0; i < productSelect.options.length; i++) {
    if (productSelect.options[i].selected) {
      selectedOptions.push(productSelect.options[i])
    }
  }

  // Bersihkan daftar
  selectedProductsList.innerHTML = ''

  // Jika tidak ada yang dipilih, tampilkan pesan default
  if (selectedOptions.length === 0) {
    selectedProductsList.innerHTML =
      '<div class="no-items-selected">' +
      '<i class="fas fa-info-circle me-2"></i>Belum ada produk yang dipilih' +
      '</div>'
    return
  }

  // Tampilkan setiap produk yang dipilih
  selectedOptions.forEach(function (option) {
    var brand = option.getAttribute('data-brand')
    var itemDiv = document.createElement('div')
    itemDiv.className = 'selected-item'
    itemDiv.innerHTML =
      '<span>' +
      option.textContent +
      '</span>' +
      '<span class="brand-badge brand-' +
      brand +
      '">' +
      brand +
      '</span>' +
      '<span class="delete-btn" data-id="' +
      option.value +
      '" data-type="product">' +
      '<i class="fas fa-times"></i>' +
      '</span>'

    // Tambahkan event listener untuk tombol hapus
    var deleteBtn = itemDiv.querySelector('.delete-btn')
    if (deleteBtn) {
      deleteBtn.addEventListener('click', function () {
        var id = this.getAttribute('data-id')
        var option = productSelect.querySelector('option[value="' + id + '"]')
        if (option) option.selected = false
        updateSelectedProductsDisplay()
        updateSelectedCount()
      })
    }

    selectedProductsList.appendChild(itemDiv)
  })
}

// Update tampilan kategori terpilih
function updateSelectedCategoriesDisplay () {
  var categorySelect = document.getElementById('category_ids')
  var selectedCategoriesList = document.getElementById(
    'selected_categories_list'
  )

  if (!categorySelect || !selectedCategoriesList) return

  // Dapatkan opsi yang dipilih
  var selectedOptions = []
  for (var i = 0; i < categorySelect.options.length; i++) {
    if (categorySelect.options[i].selected) {
      selectedOptions.push(categorySelect.options[i])
    }
  }

  // Bersihkan daftar
  selectedCategoriesList.innerHTML = ''

  // Jika tidak ada yang dipilih, tampilkan pesan default
  if (selectedOptions.length === 0) {
    selectedCategoriesList.innerHTML =
      '<div class="no-items-selected">' +
      '<i class="fas fa-info-circle me-2"></i>Belum ada kategori yang dipilih' +
      '</div>'
    return
  }

  // Tampilkan setiap kategori yang dipilih
  selectedOptions.forEach(function (option) {
    var brand = option.getAttribute('data-brand')
    var itemDiv = document.createElement('div')
    itemDiv.className = 'selected-item'
    itemDiv.innerHTML =
      '<span>' +
      option.textContent +
      '</span>' +
      '<span class="brand-badge brand-' +
      brand +
      '">' +
      brand +
      '</span>' +
      '<span class="delete-btn" data-id="' +
      option.value +
      '" data-type="category">' +
      '<i class="fas fa-times"></i>' +
      '</span>'

    // Tambahkan event listener untuk tombol hapus
    var deleteBtn = itemDiv.querySelector('.delete-btn')
    if (deleteBtn) {
      deleteBtn.addEventListener('click', function () {
        var id = this.getAttribute('data-id')
        var option = categorySelect.querySelector('option[value="' + id + '"]')
        if (option) option.selected = false
        updateSelectedCategoriesDisplay()
        updateSelectedCount()
      })
    }

    selectedCategoriesList.appendChild(itemDiv)
  })
}

// Update hitungan item terpilih
function updateSelectedCount () {
  var productSelect = document.getElementById('product_ids')
  var productCount = document.getElementById('product_count')

  if (productSelect && productCount) {
    var count = 0
    for (var i = 0; i < productSelect.options.length; i++) {
      if (productSelect.options[i].selected) count++
    }
    productCount.textContent = count
  }

  var categorySelect = document.getElementById('category_ids')
  var categoryCount = document.getElementById('category_count')

  if (categorySelect && categoryCount) {
    var count = 0
    for (var i = 0; i < categorySelect.options.length; i++) {
      if (categorySelect.options[i].selected) count++
    }
    categoryCount.textContent = count
  }
}

// Menangani perubahan merek utama
function handleBrandChange () {
  var promoBrand = document.getElementById('promo_brand')
  if (!promoBrand) return

  var brand = promoBrand.value

  // Perbarui filter produk
  var productBrandFilter = document.getElementById('product_brand_filter')
  if (productBrandFilter) {
    productBrandFilter.value = brand
    filterProductsByBrand()
  }

  // Perbarui filter kategori
  var categoryBrandFilter = document.getElementById('category_brand_filter')
  if (categoryBrandFilter) {
    categoryBrandFilter.value = brand
    filterCategoriesByBrand()
  }

  // Perbarui tampilan terpilih
  updateSelectedProductsDisplay()
  updateSelectedCategoriesDisplay()
  updateSelectedCount()

  // Perbarui pratinjau
  updatePromoPreview()
}

// Toggle bidang diskon berdasarkan jenis yang dipilih
function toggleDiscountFields () {
  var percentageType = document.getElementById('type_percentage')
  var percentageField = document.getElementById('percentage_field')
  var nominalField = document.getElementById('nominal_field')
  var maximumDiscountField = document.getElementById('maximum_discount_field')
  var promoTypeHidden = document.getElementById('promo_type_hidden')

  if (
    !percentageType ||
    !percentageField ||
    !nominalField ||
    !maximumDiscountField
  )
    return

  if (percentageType.checked) {
    percentageField.style.display = 'block'
    nominalField.style.display = 'none'
    maximumDiscountField.style.display = 'block'
    if (promoTypeHidden) promoTypeHidden.value = 'percentage'
  } else {
    percentageField.style.display = 'none'
    nominalField.style.display = 'block'
    maximumDiscountField.style.display = 'none'
    if (promoTypeHidden) promoTypeHidden.value = 'nominal'
  }

  // Perbarui pratinjau
  updatePromoPreview()
}

// Toggle bidang kuota berdasarkan checkbox
function toggleQuotaField () {
  var hasQuota = document.getElementById('has_quota')
  var quotaField = document.getElementById('quota_field')
  var quotaInput = document.getElementById('quota')

  if (!hasQuota || !quotaField) return

  if (hasQuota.checked) {
    quotaField.style.display = 'block'
  } else {
    quotaField.style.display = 'none'
    if (quotaInput) quotaInput.value = ''
  }
}

// Format angka dengan pemisah ribuan
function formatNumber (number) {
  if (isNaN(number)) return '0'

  try {
    return new Intl.NumberFormat('id-ID').format(number)
  } catch (e) {
    console.error('Error formatting number:', e)
    return number.toString()
  }
}

// Perbarui pratinjau promo
function updatePromoPreview () {
  // Dapatkan nilai dari formulir
  var promoName = document.getElementById('promo_name')
  var promoCode = document.getElementById('promo_code')
  var promoBrand = document.getElementById('promo_brand')
  var promoStatus = document.getElementById('promo_status')
  var startDate = document.getElementById('start_date')
  var endDate = document.getElementById('end_date')
  var minimumOrder = document.getElementById('minimum_order')

  // Dapatkan jenis promo dan nilainya
  var promoTypeHidden = document.getElementById('promo_type_hidden')
  var percentageValue = document.getElementById('percentage_value')
  var nominalValue = document.getElementById('nominal_value')
  var maximumDiscount = document.getElementById('maximum_discount')

  // Nilai default
  var nameValue = promoName ? promoName.value || 'Nama Promo' : 'Nama Promo'
  var codeValue = promoCode ? promoCode.value || 'KODEPROMOXXX' : 'KODEPROMOXXX'
  var brandValue = promoBrand ? promoBrand.value || 'Brand' : 'Brand'
  var statusValue = promoStatus ? promoStatus.value || 'active' : 'active'
  var startDateValue = startDate ? startDate.value || '' : ''
  var endDateValue = endDate ? endDate.value || '' : ''
  var minOrderValue = minimumOrder ? minimumOrder.value || '0' : '0'
  var promoType = promoTypeHidden ? promoTypeHidden.value : 'percentage'

  // Tentukan nilai promo berdasarkan jenis
  var promoValue = ''

  if (promoType === 'percentage') {
    var percentValue = percentageValue ? percentageValue.value || '0' : '0'
    promoValue = percentValue + '%'

    // Periksa apakah maksimum diskon diatur
    var maxDiscount = maximumDiscount ? maximumDiscount.value : ''
    if (maxDiscount) {
      promoValue += ' (max Rp ' + formatNumber(maxDiscount) + ')'
    }
  } else if (promoType === 'nominal') {
    var nominal = nominalValue ? nominalValue.value || '0' : '0'
    promoValue = 'Rp ' + formatNumber(nominal)
  } else if (promoType === 'bundling') {
    promoValue = 'Bundle Promo'
  } else if (promoType === 'bogo') {
    promoValue = 'Buy One Get One'
  }

  // Format tanggal untuk tampilan
  var formattedPeriod = ''
  if (startDateValue && endDateValue) {
    try {
      var start = new Date(startDateValue)
      var end = new Date(endDateValue)

      var options = { year: 'numeric', month: 'short', day: 'numeric' }
      var startFormatted = start.toLocaleDateString('id-ID', options)
      var endFormatted = end.toLocaleDateString('id-ID', options)

      formattedPeriod = startFormatted + ' - ' + endFormatted
    } catch (e) {
      console.error('Error parsing dates:', e)
      formattedPeriod = 'Periode Promo'
    }
  }

  // Perbarui elemen pratinjau
  var previewName = document.getElementById('preview_name')
  var previewCode = document.getElementById('preview_code')
  var previewBrand = document.getElementById('preview_brand')
  var previewStatus = document.getElementById('preview_status')
  var previewValue = document.getElementById('preview_value')
  var previewMinimum = document.getElementById('preview_minimum')
  var previewPeriod = document.getElementById('preview_period')

  if (previewName) previewName.textContent = nameValue
  if (previewCode) previewCode.textContent = codeValue

  if (previewBrand) {
    var capitalizedBrand =
      brandValue.charAt(0).toUpperCase() + brandValue.slice(1)
    previewBrand.textContent = capitalizedBrand
    previewBrand.className = 'promo-brand brand-' + brandValue
  }

  if (previewStatus) {
    previewStatus.textContent = statusValue === 'active' ? 'Aktif' : 'Non-aktif'
    previewStatus.className =
      'promo-status ' +
      (statusValue === 'active' ? 'status-active' : 'status-inactive')
  }

  if (previewValue) previewValue.textContent = promoValue

  if (previewMinimum) {
    var minOrder = parseFloat(minOrderValue)
    previewMinimum.textContent =
      minOrder > 0 ? 'Rp ' + formatNumber(minOrder) : 'Tidak ada'
  }

  if (previewPeriod) {
    previewPeriod.textContent = formattedPeriod || 'Periode Promo'
  }

  // Perbarui contoh perhitungan
  updateCalculation()
}

// Perbarui contoh perhitungan
function updateCalculation () {
  var sampleAmount = document.getElementById('sample_amount')
  var calcTotal = document.getElementById('calc_total')
  var calcDiscountLabel = document.getElementById('calc_discount_label')
  var calcDiscount = document.getElementById('calc_discount')
  var calcFinal = document.getElementById('calc_final')
  var calcMessage = document.getElementById('calc_message')

  if (
    !sampleAmount ||
    !calcTotal ||
    !calcDiscountLabel ||
    !calcDiscount ||
    !calcFinal ||
    !calcMessage
  )
    return

  var amount = parseFloat(sampleAmount.value || 0)
  var promoTypeHidden = document.getElementById('promo_type_hidden')
  var promoType = promoTypeHidden ? promoTypeHidden.value : 'percentage'

  var discount = 0
  var discountLabel = ''
  var message = ''

  // Format nilai
  calcTotal.textContent = formatNumber(amount)

  // Hitung berdasarkan jenis promo
  if (promoType === 'percentage') {
    var percentValueEl = document.getElementById('percentage_value')
    var maxDiscountEl = document.getElementById('maximum_discount')
    var minOrderEl = document.getElementById('minimum_order')

    var percentValue = percentValueEl
      ? parseFloat(percentValueEl.value || 0)
      : 0
    var maxDiscount = maxDiscountEl ? parseFloat(maxDiscountEl.value || 0) : 0
    var minOrder = minOrderEl ? parseFloat(minOrderEl.value || 0) : 0

    discountLabel = percentValue + '%'

    // Periksa apakah memenuhi order minimum
    if (amount < minOrder) {
      discount = 0
      message =
        '<div class="alert alert-warning">Belum memenuhi minimum order Rp ' +
        formatNumber(minOrder) +
        '</div>'
    } else {
      // Hitung diskon
      discount = amount * (percentValue / 100)

      // Terapkan diskon maksimum jika diatur
      if (maxDiscount > 0 && discount > maxDiscount) {
        discount = maxDiscount
        discountLabel += ' (maks. Rp ' + formatNumber(maxDiscount) + ')'
      }
    }
  } else if (promoType === 'nominal') {
    var nominalValueEl = document.getElementById('nominal_value')
    var minOrderEl = document.getElementById('minimum_order')

    var nominalValue = nominalValueEl
      ? parseFloat(nominalValueEl.value || 0)
      : 0
    var minOrder = minOrderEl ? parseFloat(minOrderEl.value || 0) : 0

    discountLabel = 'Rp ' + formatNumber(nominalValue)

    // Periksa apakah memenuhi order minimum
    if (amount < minOrder) {
      discount = 0
      message =
        '<div class="alert alert-warning">Belum memenuhi minimum order Rp ' +
        formatNumber(minOrder) +
        '</div>'
    } else {
      // Diskon nominal tidak dapat melebihi total
      discount = Math.min(nominalValue, amount)
    }
  } else if (promoType === 'bundling') {
    discountLabel = 'Bundle Promo'
    message =
      '<div class="alert alert-info">Promo bundling memberikan produk gratis, bukan diskon langsung</div>'
  } else if (promoType === 'bogo') {
    discountLabel = 'Buy One Get One'
    message =
      '<div class="alert alert-info">Promo BOGO memberikan produk gratis, bukan diskon langsung</div>'
  }

  // Perbarui tampilan perhitungan
  calcDiscountLabel.textContent = discountLabel
  calcDiscount.textContent = formatNumber(discount)
  calcFinal.textContent = formatNumber(amount - discount)
  calcMessage.innerHTML = message
}

function prepareFormSubmit () {
  // Dapatkan form
  var form = document.getElementById('promoForm')
  if (!form) return false

  // Dapatkan jenis promo
  var promoTypeHidden = document.getElementById('promo_type_hidden')
  var promoType = promoTypeHidden ? promoTypeHidden.value : 'percentage'

  // Tab selection handling
  var bundlingTab = document.getElementById('bundling_tab')
  var bogoTab = document.getElementById('bogo_tab')

  if (
    bundlingTab &&
    bundlingTab.classList.contains('active') &&
    promoTypeHidden
  ) {
    promoType = 'bundling'
    promoTypeHidden.value = 'bundling'
  } else if (
    bogoTab &&
    bogoTab.classList.contains('active') &&
    promoTypeHidden
  ) {
    promoType = 'bogo'
    promoTypeHidden.value = 'bogo'
  }

  // 1. PERSIAPKAN NILAI PROMO
  var promoValue = '0'

  if (promoType === 'percentage') {
    var percentValueEl = document.getElementById('percentage_value')
    promoValue =
      percentValueEl && percentValueEl.value ? percentValueEl.value : '0'
  } else if (promoType === 'nominal') {
    var nominalValueEl = document.getElementById('nominal_value')
    promoValue =
      nominalValueEl && nominalValueEl.value ? nominalValueEl.value : '0'
  }

  // 2. PERBAIKAN UNTUK MAXIMUM_DISCOUNT
  if (promoType === 'percentage') {
    var maximumDiscountEl = document.getElementById('maximum_discount')
    var maximumDiscount =
      maximumDiscountEl && maximumDiscountEl.value
        ? maximumDiscountEl.value
        : ''

    // Pastikan ada input hidden untuk maximum_discount
    var hiddenMaxDiscount = form.querySelector('input[name="maximum_discount"]')
    if (!hiddenMaxDiscount) {
      hiddenMaxDiscount = document.createElement('input')
      hiddenMaxDiscount.type = 'hidden'
      hiddenMaxDiscount.name = 'maximum_discount'
      form.appendChild(hiddenMaxDiscount)
    }
    hiddenMaxDiscount.value = maximumDiscount
  }

  // 3. PERBAIKAN UNTUK MINIMUM_ORDER
  var minimumOrderEl = document.getElementById('minimum_order')
  var minimumOrder =
    minimumOrderEl && minimumOrderEl.value ? minimumOrderEl.value : '0'

  // Pastikan ada input hidden untuk minimum_order
  var hiddenMinOrder = form.querySelector('input[name="minimum_order"]')
  if (!hiddenMinOrder) {
    hiddenMinOrder = document.createElement('input')
    hiddenMinOrder.type = 'hidden'
    hiddenMinOrder.name = 'minimum_order'
    form.appendChild(hiddenMinOrder)
  }
  hiddenMinOrder.value = minimumOrder

  // 4. INPUT TERSEMBUNYI UNTUK PROMO_VALUE
  var hiddenValue = form.querySelector('input[name="promo_value"]')
  if (!hiddenValue) {
    hiddenValue = document.createElement('input')
    hiddenValue.type = 'hidden'
    hiddenValue.name = 'promo_value'
    form.appendChild(hiddenValue)
  }
  hiddenValue.value = promoValue

  // 5. INPUT TERSEMBUNYI UNTUK PROMO_TYPE
  var hiddenType = form.querySelector('input[name="promo_type"][type="hidden"]')
  if (!hiddenType) {
    hiddenType = document.createElement('input')
    hiddenType.type = 'hidden'
    hiddenType.name = 'promo_type'
    form.appendChild(hiddenType)
  }
  hiddenType.value = promoType

  // 6. PERBAIKAN UNTUK NILAI QUOTA
  var hasQuota = document.getElementById('has_quota')
  var quotaEl = document.getElementById('quota')

  if (hasQuota && !hasQuota.checked && quotaEl) {
    quotaEl.value = ''
  }

  console.log('Form submission values:')
  console.log('- promo_type:', promoType)
  console.log('- promo_value:', promoValue)
  console.log('- maximum_discount:', maximumDiscount || 'undefined')
  console.log('- minimum_order:', minimumOrder)

  return validateForm()
}

// Validasi formulir sebelum pengiriman
function validateForm () {
  var isValid = true
  var errorMessage = ''

  // Bidang wajib
  var requiredFields = [
    { id: 'promo_code', name: 'Kode Promo' },
    { id: 'promo_name', name: 'Nama Promo' },
    { id: 'promo_brand', name: 'Brand' },
    { id: 'start_date', name: 'Tanggal Mulai' },
    { id: 'end_date', name: 'Tanggal Selesai' }
  ]

  // Periksa semua bidang wajib
  requiredFields.forEach(function (field) {
    var element = document.getElementById(field.id)
    if (!element || !element.value.trim()) {
      isValid = false
      errorMessage += field.name + ' tidak boleh kosong.<br>'
    }
  })

  // Validasi kode promo
  var promoCodeEl = document.getElementById('promo_code')
  if (
    promoCodeEl &&
    promoCodeEl.value &&
    !/^[A-Za-z0-9]+$/.test(promoCodeEl.value)
  ) {
    isValid = false
    errorMessage += 'Kode Promo hanya boleh mengandung huruf dan angka.<br>'
  }

  // Periksa validitas tanggal
  var startDateEl = document.getElementById('start_date')
  var endDateEl = document.getElementById('end_date')

  if (startDateEl && endDateEl && startDateEl.value && endDateEl.value) {
    var startDate = new Date(startDateEl.value)
    var endDate = new Date(endDateEl.value)

    if (startDate >= endDate) {
      isValid = false
      errorMessage += 'Tanggal Selesai harus setelah Tanggal Mulai.<br>'
    }
  }

  // Validasi tambahan untuk promo diskon
  var promoTypeHidden = document.getElementById('promo_type_hidden')
  var promoType = promoTypeHidden ? promoTypeHidden.value : 'percentage'

  if (promoType === 'percentage') {
    var percentValueEl = document.getElementById('percentage_value')

    if (percentValueEl) {
      var percentValue = parseFloat(percentValueEl.value)

      if (isNaN(percentValue) || percentValue <= 0 || percentValue > 100) {
        isValid = false
        errorMessage += 'Nilai Persentase harus antara 1-100.<br>'
      }
    }

    var maxDiscountEl = document.getElementById('maximum_discount')

    if (maxDiscountEl && maxDiscountEl.value) {
      var maxDiscount = parseFloat(maxDiscountEl.value)

      if (isNaN(maxDiscount) || maxDiscount <= 0) {
        isValid = false
        errorMessage += 'Maksimum Diskon harus lebih besar dari 0.<br>'
      }
    }
  } else if (promoType === 'nominal') {
    var nominalValueEl = document.getElementById('nominal_value')

    if (nominalValueEl) {
      var nominalValue = parseFloat(nominalValueEl.value)

      if (isNaN(nominalValue) || nominalValue < 1000) {
        isValid = false
        errorMessage += 'Nilai Nominal minimal Rp 1.000.<br>'
      }
    }
  }

  // Validasi minimum order
  var minOrderEl = document.getElementById('minimum_order')

  if (minOrderEl && minOrderEl.value) {
    var minOrder = parseFloat(minOrderEl.value)

    if (isNaN(minOrder) || minOrder < 0) {
      isValid = false
      errorMessage += 'Minimum Pembelian tidak boleh negatif.<br>'
    }
  }

  // Validasi kuota
  var hasQuotaEl = document.getElementById('has_quota')
  var quotaEl = document.getElementById('quota')

  if (hasQuotaEl && hasQuotaEl.checked && quotaEl) {
    var quota = parseInt(quotaEl.value)

    if (isNaN(quota) || quota <= 0) {
      isValid = false
      errorMessage += 'Kuota harus lebih besar dari 0.<br>'
    }
  }

  // Periksa pemilihan produk atau kategori
  var productSpecificEl = document.getElementById('product_specific')
  var categorySpecificEl = document.getElementById('category_specific')

  if (productSpecificEl && productSpecificEl.checked) {
    var productSelect = document.getElementById('product_ids')
    var hasSelected = false

    if (productSelect) {
      for (var i = 0; i < productSelect.options.length; i++) {
        if (productSelect.options[i].selected) {
          hasSelected = true
          break
        }
      }

      if (!hasSelected) {
        isValid = false
        errorMessage += 'Pilih minimal satu produk.<br>'
      }
    }
  }

  if (categorySpecificEl && categorySpecificEl.checked) {
    var categorySelect = document.getElementById('category_ids')
    var hasSelected = false

    if (categorySelect) {
      for (var i = 0; i < categorySelect.options.length; i++) {
        if (categorySelect.options[i].selected) {
          hasSelected = true
          break
        }
      }

      if (!hasSelected) {
        isValid = false
        errorMessage += 'Pilih minimal satu kategori.<br>'
      }
    }
  }

  // Tampilkan pesan kesalahan jika validasi gagal
  if (!isValid) {
    var form = document.getElementById('promoForm')

    if (form) {
      // Hapus pesan error yang sudah ada
      var existingAlert = form.querySelector('.alert.alert-danger')
      if (existingAlert) existingAlert.remove()

      // Buat pesan error baru
      var alertDiv = document.createElement('div')
      alertDiv.className = 'alert alert-danger alert-dismissible fade show mt-3'
      alertDiv.setAttribute('role', 'alert')
      alertDiv.innerHTML =
        '<i class="fas fa-exclamation-circle me-2"></i>' +
        errorMessage +
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'

      // Tambahkan ke form
      form.insertBefore(alertDiv, form.firstChild)
    }

    // Scroll ke atas untuk menunjukkan pesan error
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  return isValid
}

// Menangani pergantian tab
function handleTabSwitch () {
  var tabs = document.querySelectorAll('#promoTypeTabs button')
  if (!tabs.length) return

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var tabId = this.id
      var promoTypeHidden = document.getElementById('promo_type_hidden')

      if (!promoTypeHidden) return

      // Perbarui jenis promo berdasarkan tab aktif
      if (tabId === 'discount_tab') {
        // Gunakan jenis diskon yang dipilih saat ini
        var percentageType = document.getElementById('type_percentage')
        if (percentageType) {
          promoTypeHidden.value = percentageType.checked
            ? 'percentage'
            : 'nominal'
        }
      } else if (tabId === 'bundling_tab') {
        promoTypeHidden.value = 'bundling'
      } else if (tabId === 'bogo_tab') {
        promoTypeHidden.value = 'bogo'
      }

      // Perbarui pratinjau
      updatePromoPreview()
    })
  })
}

// Menginisialisasi semua fungsi ketika dokumen siap
document.addEventListener('DOMContentLoaded', function () {
  // Inisialisasi tampilan item terpilih
  updateSelectedProductsDisplay()
  updateSelectedCategoriesDisplay()
  updateSelectedCount()

  // Tambahkan event listener untuk pencarian dan filter
  var productSearch = document.getElementById('product_search')
  var categorySearch = document.getElementById('category_search')
  var productBrandFilter = document.getElementById('product_brand_filter')
  var categoryBrandFilter = document.getElementById('category_brand_filter')

  if (productSearch) productSearch.addEventListener('input', searchProducts)
  if (categorySearch) categorySearch.addEventListener('input', searchCategories)
  if (productBrandFilter)
    productBrandFilter.addEventListener('change', filterProductsByBrand)
  if (categoryBrandFilter)
    categoryBrandFilter.addEventListener('change', filterCategoriesByBrand)

  // Tambahkan event listener untuk perubahan brand utama
  var promoBrand = document.getElementById('promo_brand')
  if (promoBrand) {
    promoBrand.addEventListener('change', handleBrandChange)

    // Inisialisasi filter berdasarkan brand saat ini
    var currentBrand = promoBrand.value
    if (currentBrand) {
      if (productBrandFilter) productBrandFilter.value = currentBrand
      if (categoryBrandFilter) categoryBrandFilter.value = currentBrand
      filterProductsByBrand()
      filterCategoriesByBrand()
    }
  }

  // Tambahkan event listener untuk radio button jenis diskon
  var typePercentage = document.getElementById('type_percentage')
  var typeNominal = document.getElementById('type_nominal')

  if (typePercentage)
    typePercentage.addEventListener('change', toggleDiscountFields)
  if (typeNominal) typeNominal.addEventListener('change', toggleDiscountFields)

  // Tambahkan event listener untuk checkbox kuota
  var hasQuota = document.getElementById('has_quota')
  if (hasQuota) hasQuota.addEventListener('change', toggleQuotaField)

  // Tambahkan event listener untuk bidang promo
  var inputFields = [
    'promo_name',
    'promo_code',
    'promo_brand',
    'promo_status',
    'percentage_value',
    'nominal_value',
    'maximum_discount',
    'minimum_order',
    'start_date',
    'end_date',
    'sample_amount'
  ]

  inputFields.forEach(function (fieldId) {
    var field = document.getElementById(fieldId)
    if (field) {
      if (fieldId === 'sample_amount') {
        field.addEventListener('input', updateCalculation)
      } else {
        field.addEventListener('input', updatePromoPreview)
        field.addEventListener('change', updatePromoPreview)
      }
    }
  })

  // Inisialisasi bidang diskon dan kuota
  toggleDiscountFields()
  toggleQuotaField()

  // Inisialisasi pratinjau
  updatePromoPreview()

  // Inisialisasi event handler tab
  handleTabSwitch()

  // Tambahkan event listener untuk pemilihan produk dan kategori
  var productSpecific = document.getElementById('product_specific')
  var categorySpecific = document.getElementById('category_specific')
  var productIds = document.getElementById('product_ids')
  var categoryIds = document.getElementById('category_ids')

  if (productSpecific)
    productSpecific.addEventListener('change', function () {
      toggleTargetField('product')
    })
  if (categorySpecific)
    categorySpecific.addEventListener('change', function () {
      toggleTargetField('category')
    })

  if (productIds) {
    productIds.addEventListener('change', function () {
      updateSelectedProductsDisplay()
      updateSelectedCount()
    })
  }

  if (categoryIds) {
    categoryIds.addEventListener('change', function () {
      updateSelectedCategoriesDisplay()
      updateSelectedCount()
    })
  }

  // Inisialisasi tampilan jika sudah ada seleksi
  if (productSpecific && productSpecific.checked) toggleTargetField('product')
  if (categorySpecific && categorySpecific.checked)
    toggleTargetField('category')
})
