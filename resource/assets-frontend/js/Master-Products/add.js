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
  // Variabel untuk pagination
  var currentPage = 1
  var totalPages = 1
  var currentKeyword = ''
  var itemsPerPage = 10

  // PENTING: Mengambil brand dari DOM alih-alih dari template
  var selectedBrand =
    document.querySelector('input[name="product_brand"]')?.value || ''

  // Referensi elemen
  var searchInput = document.getElementById('search-products')
  var searchButton = document.getElementById('search-button')
  var productList = document.getElementById('product-list')
  var prevPageBtn = document.getElementById('prev-page')
  var nextPageBtn = document.getElementById('next-page')
  var currentPageSpan = document.getElementById('current-page')
  var totalPagesSpan = document.getElementById('total-pages')

  // Fungsi untuk memuat produk dari API
  function loadProducts (page, keyword) {
    // Default values
    page = page || 1
    keyword = keyword || ''

    // Tampilkan loading
    if (productList) {
      productList.innerHTML =
        '<tr><td colspan="5" class="text-center p-3"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div><span class="ms-2">Memuat produk...</span></td></tr>'
    }

    var url = window.apiSearchUrl || ''
    url += '?brand=' + encodeURIComponent(selectedBrand)
    url += '&page=' + page
    url += '&limit=' + itemsPerPage

    if (keyword && keyword.length > 0) {
      url += '&keyword=' + encodeURIComponent(keyword)
    }

    // Kirim request AJAX menggunakan XMLHttpRequest untuk kompatibilitas yang lebih baik
    var xhr = new XMLHttpRequest()
    xhr.open('GET', url, true)
    xhr.onreadystatechange = function () {
      if (xhr.readyState === 4) {
        if (xhr.status === 200) {
          try {
            var data = JSON.parse(xhr.responseText)

            // Update pagination
            if (data.pagination) {
              currentPage = parseInt(data.pagination.current)
              totalPages = parseInt(data.pagination.pages)

              if (currentPageSpan) currentPageSpan.textContent = currentPage
              if (totalPagesSpan) totalPagesSpan.textContent = totalPages

              // Enable/disable pagination buttons
              if (prevPageBtn) prevPageBtn.disabled = currentPage <= 1
              if (nextPageBtn) nextPageBtn.disabled = currentPage >= totalPages
            }

            // Perbarui tabel produk
            if (productList && data.data && data.data.length > 0) {
              var html = ''
              for (var i = 0; i < data.data.length; i++) {
                var product = data.data[i]
                var productJson = JSON.stringify(product).replace(/'/g, '&#39;')

                html +=
                  '<tr class="product-row" data-product=\'' +
                  productJson +
                  "'>" +
                  '<td><span class="badge bg-light text-dark">' +
                  product.product_code +
                  '</span></td>' +
                  '<td>' +
                  product.product_name +
                  '</td>' +
                  '<td>' +
                  (product.category_name || '') +
                  '</td>' +
                  '<td class="text-end">Rp ' +
                  formatNumber(product.product_price) +
                  '</td>' +
                  '<td class="text-center">' +
                  '<button type="button" class="btn btn-sm btn-primary select-product">' +
                  '<i class="fas fa-check"></i>' +
                  '</button>' +
                  '</td>' +
                  '</tr>'
              }
              productList.innerHTML = html

              // Inisialisasi event listeners untuk baris produk
              initProductRowListeners()
            } else if (productList) {
              productList.innerHTML =
                '<tr><td colspan="5" class="text-center p-3"><div class="text-muted"><i class="fas fa-info-circle me-2"></i> Tidak ada produk ditemukan</div></td></tr>'
            }
          } catch (e) {
            console.error('Error parsing JSON:', e)
            if (productList) {
              productList.innerHTML =
                '<tr><td colspan="5" class="text-center p-3"><div class="text-danger"><i class="fas fa-exclamation-circle me-2"></i> Error: Respons tidak valid</div></td></tr>'
            }
          }
        } else {
          console.error('Error HTTP:', xhr.status)
          if (productList) {
            productList.innerHTML =
              '<tr><td colspan="5" class="text-center p-3"><div class="text-danger"><i class="fas fa-exclamation-circle me-2"></i> Error ' +
              xhr.status +
              ': ' +
              xhr.statusText +
              '</div></td></tr>'
          }
        }
      }
    }
    xhr.onerror = function () {
      console.error('Network error occurred')
      if (productList) {
        productList.innerHTML =
          '<tr><td colspan="5" class="text-center p-3"><div class="text-danger"><i class="fas fa-exclamation-circle me-2"></i> Error: Koneksi gagal</div></td></tr>'
      }
    }
    xhr.send()
  }

  // Format angka dengan pemisah ribuan
  function formatNumber (number) {
    return new Intl.NumberFormat('id-ID').format(number)
  }

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

        // Reset all rows
        productRows.forEach(function (r) {
          r.classList.remove('table-primary')
        })

        // Highlight the selected row
        var row = this.closest('.product-row')
        row.classList.add('table-primary')

        try {
          var productData = JSON.parse(row.getAttribute('data-product'))

          // Populate the form with product data
          var apiIdInput = document.getElementById('api_id')
          var productCodeInput = document.getElementById('product_code')
          var productNameInput = document.getElementById('product_name')
          var productPriceInput = document.getElementById('product_price')
          var infoElement = document.getElementById('selected-product-info')

          if (apiIdInput) apiIdInput.value = productData.product_id || ''
          if (productCodeInput)
            productCodeInput.value = productData.product_code || ''
          if (productNameInput)
            productNameInput.value = productData.product_name || ''
          if (productPriceInput)
            productPriceInput.value = productData.product_price || 0

          // Update the selected product info display
          if (infoElement) {
            infoElement.innerHTML =
              '<div class="alert alert-success mb-0">' +
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
              '</div>'
          }

          // Handle category selection
          var catIdSelect = document.getElementById('cat_id')
          if (catIdSelect && productData.local_category_id) {
            // If we have a local category ID mapping from API, select it
            catIdSelect.value = productData.local_category_id

            // Attempt to initialize select2 if available
            try {
              $(catIdSelect).trigger('change')
            } catch (e) {
              console.log('Select2 not available or already initialized')
            }
          }

          // Enable the submit button
          var submitButton = document.getElementById('submitButton')
          if (submitButton) submitButton.disabled = false
        } catch (e) {
          console.error('Error parsing product data:', e)
          alert('Error saat memilih produk: ' + e.message)
        }
      })
    })
  }

  // Event listener untuk tombol search
  if (searchButton) {
    searchButton.addEventListener('click', function () {
      currentKeyword = searchInput ? searchInput.value.trim() : ''
      loadProducts(1, currentKeyword)
    })
  }

  // Event listener untuk input search (ketika tekan Enter)
  if (searchInput) {
    searchInput.addEventListener('keypress', function (e) {
      if (e.key === 'Enter') {
        currentKeyword = this.value.trim()
        loadProducts(1, currentKeyword)
        e.preventDefault()
      }
    })
  }

  // Event listener untuk tombol pagination
  if (prevPageBtn) {
    prevPageBtn.addEventListener('click', function () {
      if (currentPage > 1) {
        loadProducts(currentPage - 1, currentKeyword)
      }
    })
  }

  if (nextPageBtn) {
    nextPageBtn.addEventListener('click', function () {
      if (currentPage < totalPages) {
        loadProducts(currentPage + 1, currentKeyword)
      }
    })
  }

  // Load produk saat halaman dimuat, hanya jika ada brand
  if (selectedBrand) {
    loadProducts(1, '')
  }
})
