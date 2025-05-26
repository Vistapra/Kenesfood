// Inisialisasi dan fungsi utama untuk halaman promo
document.addEventListener('DOMContentLoaded', function () {
  // Inisialisasi tooltips
  var tooltipTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
  )
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
  })

  // Inisialisasi Select2 jika tersedia
  try {
    $('.select2-filter-product').select2({
      placeholder: 'Pilih produk untuk filter',
      allowClear: true,
      width: '100%'
    })

    $('.select2-filter-category').select2({
      placeholder: 'Pilih kategori untuk filter',
      allowClear: true,
      width: '100%'
    })
  } catch (e) {
    console.warn('Select2 initialization error:', e)
  }

  // Setup filter form submit
  const filterForm = document.getElementById('filterForm')
  if (filterForm) {
    filterForm.addEventListener('submit', function () {
      // Hapus parameter kosong dari form
      const inputs = this.querySelectorAll('input, select')
      inputs.forEach(input => {
        if (input.value === '' || input.value === null) {
          input.name = '' // Hapus nama untuk mencegah pengiriman parameter kosong
        }
      })
    })
  }

  // Tab untuk filter tipe promo
  const promoTypeTabs = document.getElementById('promoTypeTabs')
  if (promoTypeTabs) {
    promoTypeTabs.addEventListener('shown.bs.tab', function (event) {
      const activeTab = event.target.id
      // Update hidden field atau state jika diperlukan
      if (activeTab === 'discount-promos-tab') {
        console.log('Discount promos tab activated')
        // Lakukan aksi khusus jika diperlukan
      } else if (activeTab === 'bundling-promos-tab') {
        console.log('Bundling promos tab activated')
        // Lakukan aksi khusus jika diperlukan
      } else if (activeTab === 'bogo-promos-tab') {
        console.log('BOGO promos tab activated')
        // Lakukan aksi khusus jika diperlukan
      }
    })
  }

  // Bulk actions
  setupBulkActions()
})

var urlParams = new URLSearchParams(window.location.search)
var tab = urlParams.get('tab')

// Default tab
var activeTabId = 'all-promos-tab'

if (tab) {
  // Cari tab yang sesuai berdasarkan parameter
  switch (tab) {
    case 'mrp':
      activeTabId = 'mrp-vouchers-tab'
      break
    case 'discount':
      activeTabId = 'discount-promos-tab'
      break
    case 'bundling':
      activeTabId = 'bundling-promos-tab'
      break
    case 'bogo':
      activeTabId = 'bogo-promos-tab'
      break
    default:
      activeTabId = 'all-promos-tab'
  }
}

// Aktifkan tab yang sesuai
var tabElement = document.getElementById(activeTabId)
if (tabElement) {
  var tabInstance = new bootstrap.Tab(tabElement)
  tabInstance.show()

  // Aktifkan konten tab terkait
  var targetId = tabElement.getAttribute('data-bs-target')
  var targetContent = document.querySelector(targetId)
  if (targetContent) {
    // Hapus kelas active dari semua konten tab
    document.querySelectorAll('.tab-pane').forEach(function (pane) {
      pane.classList.remove('active', 'show')
    })
    // Tambahkan kelas active ke konten tab yang dipilih
    targetContent.classList.add('active', 'show')
  }
}

var promoTabs = document.querySelectorAll('button[data-bs-toggle="tab"]')
promoTabs.forEach(function (tab) {
  tab.addEventListener('shown.bs.tab', function (event) {
    var targetTabId = event.target.id
    var urlParams = new URLSearchParams(window.location.search)

    // Reset filter ketika pindah tab
    urlParams.delete('page')

    // Set tab yang aktif
    if (targetTabId === 'all-promos-tab') {
      urlParams.delete('tab')
      // Pastikan tidak ada filter MRP
      urlParams.delete('voucher_type')
    } else if (targetTabId === 'mrp-vouchers-tab') {
      urlParams.set('tab', 'mrp')
      // Hapus filter yang tidak relevan untuk MRP
      urlParams.delete('promo_type')
    } else if (targetTabId === 'discount-promos-tab') {
      urlParams.set('tab', 'discount')
      urlParams.set('promo_type', 'discount')
      // Hapus filter yang tidak relevan
      urlParams.delete('voucher_type')
    } else if (targetTabId === 'bundling-promos-tab') {
      urlParams.set('tab', 'bundling')
      urlParams.set('promo_type', 'bundling')
      // Hapus filter yang tidak relevan
      urlParams.delete('voucher_type')
    } else if (targetTabId === 'bogo-promos-tab') {
      urlParams.set('tab', 'bogo')
      urlParams.set('promo_type', 'bogo')
      // Hapus filter yang tidak relevan
      urlParams.delete('voucher_type')
    }

    var newRelativePathQuery =
      window.location.pathname + '?' + urlParams.toString()
    history.pushState(null, '', newRelativePathQuery)
  })
})

// Pastikan semua event handler untuk tombol hapus berfungsi dengan baik
document
  .querySelectorAll('.action-btn[data-bs-toggle="tooltip"]')
  .forEach(function (btn) {
    new bootstrap.Tooltip(btn)
  })

/**
 * Konfirmasi penghapusan promo
 */
function confirmDelete (deleteUrl) {
  const deleteModal = document.getElementById('deleteModal')
  const confirmButton = document.getElementById('confirmDeleteButton')

  if (deleteModal && confirmButton) {
    // Set the confirm button URL
    confirmButton.href = deleteUrl

    // Show the modal
    const modal = new bootstrap.Modal(deleteModal)
    modal.show()
  } else {
    // Fallback jika modal tidak tersedia
    if (confirm('Apakah Anda yakin ingin menghapus promo ini?')) {
      window.location.href = deleteUrl
    }
  }
}

/**
 * Setup bulk actions untuk promo
 */
function setupBulkActions () {
  const bulkActionForm = document.getElementById('bulkActionForm')
  const bulkActionSelect = document.getElementById('bulkAction')
  const checkAllBox = document.getElementById('checkAll')

  if (bulkActionForm && bulkActionSelect) {
    // Submit handler untuk bulk action
    bulkActionForm.addEventListener('submit', function (e) {
      const selectedPromos = document.querySelectorAll(
        'input[name="promo_ids[]"]:checked'
      )

      if (selectedPromos.length === 0) {
        e.preventDefault()
        alert('Silakan pilih minimal satu promo untuk diproses.')
        return false
      }

      const action = bulkActionSelect.value
      let confirmMessage = ''

      switch (action) {
        case 'activate':
          confirmMessage =
            'Aktifkan ' + selectedPromos.length + ' promo yang dipilih?'
          break
        case 'deactivate':
          confirmMessage =
            'Nonaktifkan ' + selectedPromos.length + ' promo yang dipilih?'
          break
        case 'delete':
          confirmMessage =
            'Hapus ' +
            selectedPromos.length +
            ' promo yang dipilih? Tindakan ini tidak dapat dibatalkan.'
          break
        default:
          confirmMessage =
            'Lanjutkan dengan aksi ini untuk ' +
            selectedPromos.length +
            ' promo yang dipilih?'
      }

      if (!confirm(confirmMessage)) {
        e.preventDefault()
        return false
      }
    })

    // Check all handler
    if (checkAllBox) {
      checkAllBox.addEventListener('change', function () {
        const checked = this.checked
        const checkboxes = document.querySelectorAll(
          'input[name="promo_ids[]"]'
        )

        checkboxes.forEach(function (checkbox) {
          checkbox.checked = checked
        })
      })
    }
  }
}

/**
 * Handler untuk mengkloning promo
 */
function clonePromo (promoId) {
  if (confirm('Apakah Anda ingin membuat salinan dari promo ini?')) {
    window.location.href = site_url + 'promo/MasterPromo/clonePromo/' + promoId
  }
}

/**
 * Fungsi utility untuk memformat angka
 */
function formatNumber (number) {
  return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}

/**
 * Fungsi untuk meng-highlight row tertentu (misalnya setelah aksi)
 */
function highlightRow (rowId) {
  const row = document.getElementById(rowId)
  if (row) {
    row.classList.add('highlight-animation')
    setTimeout(() => {
      row.classList.remove('highlight-animation')
    }, 2000)
  }
}

/**
 * Toggle visibilitas filter khusus untuk produk dan kategori
 */
function toggleFilterSection (type) {
  const productSection = document.getElementById('product_selector')
  const categorySection = document.getElementById('category_selector')

  if (type === 'product') {
    productSection.style.display = document.getElementById('product_specific')
      .checked
      ? 'block'
      : 'none'

    // Uncheck category specific jika product specific dicentang
    if (document.getElementById('product_specific').checked) {
      document.getElementById('category_specific').checked = false
      categorySection.style.display = 'none'
    }
  } else if (type === 'category') {
    categorySection.style.display = document.getElementById('category_specific')
      .checked
      ? 'block'
      : 'none'

    // Uncheck product specific jika category specific dicentang
    if (document.getElementById('category_specific').checked) {
      document.getElementById('product_specific').checked = false
      productSection.style.display = 'none'
    }
  }
}

// Fungsi untuk mensinkronkan semua voucher MRP
function syncAllVouchers () {
  // Tampilkan loader
  showLoading('Sinkronisasi voucher MRP...')

  // Panggil API untuk sinkronisasi voucher
  fetch(site_url + 'apis/PromoApi/syncVouchers', {
    method: 'GET',
    headers: {
      Authorization: 'Basic ' + auth_token // Gunakan variabel auth_token yang didefinisikan di template
    }
  })
    .then(function (response) {
      return response.json()
    })
    .then(function (data) {
      // Sembunyikan loader
      hideLoading()

      if (data.status === 'OK') {
        // Tampilkan notifikasi sukses dengan concat string biasa
        var successMessage =
          data.result.added +
          ' voucher ditambahkan, ' +
          data.result.updated +
          ' diperbarui, ' +
          data.result.deactivated +
          ' dinonaktifkan.'

        showNotification('success', 'Sinkronisasi berhasil', successMessage)

        // Reload halaman setelah sukses
        setTimeout(function () {
          window.location.reload()
        }, 2000)
      } else {
        // Tampilkan notifikasi error
        showNotification('error', 'Sinkronisasi gagal', data.message)
      }
    })
    .catch(function (error) {
      // Sembunyikan loader
      hideLoading()
      // Tampilkan notifikasi error
      showNotification(
        'error',
        'Terjadi kesalahan',
        'Tidak dapat terhubung ke server MRP'
      )
      console.error('Error:', error)
    })
}

// Fungsi untuk mensinkronkan voucher MRP tertentu
function syncVoucher (voucherCode) {
  // Tampilkan loader
  showLoading('Sinkronisasi voucher ' + voucherCode + '...')

  // Panggil API untuk sinkronisasi voucher
  fetch(site_url + 'apis/PromoApi/syncVouchers', {
    method: 'GET',
    headers: {
      Authorization: 'Basic ' + auth_token
    }
  })
    .then(function (response) {
      return response.json()
    })
    .then(function (data) {
      // Sembunyikan loader
      hideLoading()

      if (data.status === 'OK') {
        // Tampilkan notifikasi sukses
        showNotification(
          'success',
          'Sinkronisasi berhasil',
          'Voucher ' + voucherCode + ' berhasil disinkronisasi'
        )

        // Reload halaman setelah sukses
        setTimeout(function () {
          window.location.reload()
        }, 2000)
      } else {
        // Tampilkan notifikasi error
        showNotification('error', 'Sinkronisasi gagal', data.message)
      }
    })
    .catch(function (error) {
      // Sembunyikan loader
      hideLoading()
      // Tampilkan notifikasi error
      showNotification(
        'error',
        'Terjadi kesalahan',
        'Tidak dapat terhubung ke server MRP'
      )
      console.error('Error:', error)
    })
}

// Fungsi untuk mengubah status aktif/nonaktif voucher
function toggleVoucherStatus (promoId, currentStatus) {
  var newStatus = currentStatus === 'active' ? 'inactive' : 'active'
  var action = currentStatus === 'active' ? 'menonaktifkan' : 'mengaktifkan'

  // Konfirmasi
  if (!confirm('Apakah Anda yakin ingin ' + action + ' voucher ini?')) {
    return
  }

  // Tampilkan loader
  showLoading(action + ' voucher...')

  // Update status promo
  fetch(site_url + 'apis/PromoApi/updateVoucherStatus', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: 'Basic ' + auth_token
    },
    body: JSON.stringify({
      promo_id: promoId,
      status: newStatus
    })
  })
    .then(function (response) {
      return response.json()
    })
    .then(function (data) {
      // Sembunyikan loader
      hideLoading()

      if (data.status === 'OK') {
        // Tampilkan notifikasi sukses
        showNotification(
          'success',
          'Status berhasil diubah',
          'Voucher berhasil ' + action
        )

        // Reload halaman setelah sukses
        setTimeout(function () {
          window.location.reload()
        }, 2000)
      } else {
        // Tampilkan notifikasi error
        showNotification('error', 'Gagal mengubah status', data.message)
      }
    })
    .catch(function (error) {
      // Sembunyikan loader
      hideLoading()
      // Tampilkan notifikasi error
      showNotification(
        'error',
        'Terjadi kesalahan',
        'Tidak dapat terhubung ke server'
      )
      console.error('Error:', error)
    })
}

// Fungsi helper untuk menampilkan loading
function showLoading (message) {
  // Jika belum ada elemen loading, buat dulu
  if (!document.getElementById('loading-overlay')) {
    const loadingOverlay = document.createElement('div')
    loadingOverlay.id = 'loading-overlay'
    loadingOverlay.classList.add('loading-overlay')

    const loadingContent = document.createElement('div')
    loadingContent.classList.add('loading-content')

    const spinner = document.createElement('div')
    spinner.classList.add('spinner-border', 'text-primary')
    spinner.setAttribute('role', 'status')

    const loadingMessage = document.createElement('p')
    loadingMessage.id = 'loading-message'
    loadingMessage.classList.add('mt-3')

    loadingContent.appendChild(spinner)
    loadingContent.appendChild(loadingMessage)
    loadingOverlay.appendChild(loadingContent)

    document.body.appendChild(loadingOverlay)

    // Tambahkan style untuk overlay loading
    const style = document.createElement('style')
    style.textContent = `
                .loading-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                }
                .loading-content {
                    background-color: white;
                    padding: 30px;
                    border-radius: 10px;
                    text-align: center;
                    box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
                }
            `
    document.head.appendChild(style)
  }

  // Update pesan loading
  document.getElementById('loading-message').textContent = message
  document.getElementById('loading-overlay').style.display = 'flex'
}

// Fungsi helper untuk menyembunyikan loading
function hideLoading () {
  const loadingOverlay = document.getElementById('loading-overlay')
  if (loadingOverlay) {
    loadingOverlay.style.display = 'none'
  }
}

// Fungsi helper untuk menampilkan notifikasi
function showNotification (type, title, message) {
  // Jika belum ada container notifikasi, buat dulu
  if (!document.getElementById('notification-container')) {
    const notifContainer = document.createElement('div')
    notifContainer.id = 'notification-container'
    notifContainer.style.position = 'fixed'
    notifContainer.style.top = '20px'
    notifContainer.style.right = '20px'
    notifContainer.style.zIndex = '9999'
    document.body.appendChild(notifContainer)
  }

  // Buat elemen notifikasi
  const notification = document.createElement('div')
  notification.classList.add('notification', `notification-${type}`)
  notification.innerHTML = `
            <div class="notification-header">
                <span class="notification-title">${title}</span>
                <button type="button" class="notification-close">&times;</button>
            </div>
            <div class="notification-body">
                ${message}
            </div>
        `

  // Tambahkan style untuk notifikasi
  notification.style.backgroundColor =
    type === 'success' ? '#d4edda' : '#f8d7da'
  notification.style.color = type === 'success' ? '#155724' : '#721c24'
  notification.style.padding = '15px'
  notification.style.marginBottom = '10px'
  notification.style.borderRadius = '5px'
  notification.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)'
  notification.style.minWidth = '300px'
  notification.style.maxWidth = '400px'

  // Handle close button
  notification
    .querySelector('.notification-close')
    .addEventListener('click', function () {
      notification.remove()
    })

  // Tambahkan notifikasi ke container
  document.getElementById('notification-container').appendChild(notification)

  // Auto remove setelah 5 detik
  setTimeout(() => {
    notification.remove()
  }, 5000)
}
