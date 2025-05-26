document.addEventListener('DOMContentLoaded', function () {
  // Initialize Select2 untuk semua dropdown produk
  try {
    $('.product-select').select2({
      placeholder: 'Pilih Produk',
      width: '100%',
      theme: 'bootstrap',
      allowClear: true
    })
  } catch (e) {
    console.log('Select2 initialization error:', e)
  }

  // Fungsi untuk menambahkan bundle baru
  document
    .getElementById('addBundleBtn')
    .addEventListener('click', function () {
      // Ambil template bundle
      const template = document.getElementById('bundle-template')
      const clone = document.importNode(template.content, true)

      // Update nomor bundle
      const bundleCount = document.querySelectorAll('.bundle-item').length + 1
      clone.querySelector('.card-header h6').textContent =
        'Bundle #' + bundleCount

      // Tambahkan bundle ke container
      const container = document.querySelector('.bundle-container')
      container.appendChild(clone)

      // Inisialisasi Select2 untuk dropdown produk baru
      try {
        $(container.lastElementChild).find('.product-select').select2({
          placeholder: 'Pilih Produk',
          width: '100%',
          theme: 'bootstrap',
          allowClear: true
        })
      } catch (e) {
        console.log('Select2 initialization error:', e)
      }

      // Sembunyikan pesan "Belum Ada Bundle"
      document.querySelector('.empty-bundle-message').style.display = 'none'
    })

  // Fungsi untuk menghapus bundle
  document
    .querySelector('.bundle-container')
    .addEventListener('click', function (e) {
      if (e.target.closest('.remove-bundle-btn')) {
        const bundleItem = e.target.closest('.bundle-item')

        // Konfirmasi hapus
        if (confirm('Apakah Anda yakin ingin menghapus bundle ini?')) {
          bundleItem.remove()

          // Update nomor bundle
          const bundles = document.querySelectorAll('.bundle-item')
          bundles.forEach((bundle, index) => {
            bundle.querySelector('.card-header h6').textContent =
              'Bundle #' + (index + 1)
          })

          // Tampilkan pesan "Belum Ada Bundle" jika tidak ada bundle
          if (bundles.length === 0) {
            document.querySelector('.empty-bundle-message').style.display =
              'block'
          }
        }
      }
    })

  // Validasi form sebelum submit
  document
    .getElementById('bundlingForm')
    .addEventListener('submit', function (e) {
      const bundles = document.querySelectorAll('.bundle-item')

      // Cek apakah ada bundle
      if (bundles.length === 0) {
        e.preventDefault()
        alert('Tambahkan minimal satu bundle promo')
        return false
      }

      // Validasi setiap bundle
      let isValid = true
      bundles.forEach((bundle, index) => {
        const product1 = bundle.querySelector(
          '[name="required_product_id1[]"]'
        ).value
        const product2 = bundle.querySelector(
          '[name="required_product_id2[]"]'
        ).value
        const freeProduct = bundle.querySelector(
          '[name="free_product_id[]"]'
        ).value

        if (!product1 || !product2 || !freeProduct) {
          isValid = false
          alert(
            'Bundle #' + (index + 1) + ': Pilih semua produk yang dibutuhkan'
          )
        }
      })

      if (!isValid) {
        e.preventDefault()
        return false
      }
    })
})
