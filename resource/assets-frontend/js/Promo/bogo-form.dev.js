document.addEventListener('DOMContentLoaded', function () {
	// Initialize Select2 untuk semua dropdown produk
	try {
		$('.product-select').select2({
			placeholder: "Pilih Produk",
			width: '100%',
			theme: 'bootstrap',
			allowClear: true
		});

		$('.free-product-select').select2({
			placeholder: "Pilih Produk Gratis",
			width: '100%',
			theme: 'bootstrap',
			allowClear: true
		});
	} catch (e) {
		console.log('Select2 initialization error:', e);
	}

	// Fungsi untuk menambahkan BOGO baru
	document.getElementById('addBogoBtn').addEventListener('click', function () {
		// Ambil template BOGO
		const template = document.getElementById('bogo-template');
		const clone = document.importNode(template.content, true);

		// Update nomor BOGO
		const bogoCount = document.querySelectorAll('.bogo-item').length + 1;
		clone.querySelector('.card-header h6').textContent = 'BOGO #' + bogoCount;

		// Tambahkan BOGO ke container
		const container = document.querySelector('.bogo-container');
		container.appendChild(clone);

		// Inisialisasi Select2 untuk dropdown produk baru
		try {
			$(container.lastElementChild).find('.product-select').select2({
				placeholder: "Pilih Produk",
				width: '100%',
				theme: 'bootstrap',
				allowClear: true
			});

			$(container.lastElementChild).find('.free-product-select').select2({
				placeholder: "Pilih Produk Gratis",
				width: '100%',
				theme: 'bootstrap',
				allowClear: true
			});
		} catch (e) {
			console.log('Select2 initialization error:', e);
		}

		// Set up preview update event listeners
		setupBogoPreviewListeners(container.lastElementChild);

		// Sembunyikan pesan "Belum Ada BOGO"
		document.querySelector('.empty-bogo-message').style.display = 'none';
	});

	// Fungsi untuk menghapus BOGO
	document.querySelector('.bogo-container').addEventListener('click', function (e) {
		if (e.target.closest('.remove-bogo-btn')) {
			const bogoItem = e.target.closest('.bogo-item');

			// Konfirmasi hapus
			if (confirm('Apakah Anda yakin ingin menghapus BOGO ini?')) {
				bogoItem.remove();

				// Update nomor BOGO
				const bogos = document.querySelectorAll('.bogo-item');
				bogos.forEach((bogo, index) => {
					bogo.querySelector('.card-header h6').textContent = 'BOGO #' + (index + 1);
				});

				// Tampilkan pesan "Belum Ada BOGO" jika tidak ada BOGO
				if (bogos.length === 0) {
					document.querySelector('.empty-bogo-message').style.display = 'block';
				}
			}
		}
	});

	// Setup listeners untuk semua BOGO items yang sudah ada
	document.querySelectorAll('.bogo-item').forEach(item => {
		setupBogoPreviewListeners(item);
	});

	// Validasi form sebelum submit
	document.getElementById('bogoForm').addEventListener('submit', function (e) {
		const bogos = document.querySelectorAll('.bogo-item');

		// Cek apakah ada BOGO
		if (bogos.length === 0) {
			e.preventDefault();
			alert('Tambahkan minimal satu konfigurasi BOGO');
			return false;
		}

		// Validasi setiap BOGO
		let isValid = true;
		bogos.forEach((bogo, index) => {
			const product = bogo.querySelector('[name="product_id[]"]').value;
			const useSameProduct = bogo.querySelector('.use-same-product').checked;
			const freeProduct = bogo.querySelector('[name="free_product_id[]"]').value;

			if (!product || (!useSameProduct && !freeProduct)) {
				isValid = false;
				alert('BOGO #' + (index + 1) + ': Pilih semua produk yang diperlukan');
			}

			// Jika menggunakan produk yang sama, set nilai free_product_id sama dengan product_id
			if (useSameProduct) {
				const freeProductSelect = bogo.querySelector('[name="free_product_id[]"]');
				freeProductSelect.value = product;
				freeProductSelect.disabled = false; // Enable temporarily for form submission
			}
		});

		if (!isValid) {
			e.preventDefault();
			return false;
		}
	});

	// Fungsi untuk mengaktifkan/menonaktifkan select produk gratis berdasarkan checkbox
	window.toggleFreeProductField = function (checkbox) {
		const bogoItem = checkbox.closest('.bogo-item');
		const freeProductSelect = bogoItem.querySelector('.free-product-select');
		const productSelect = bogoItem.querySelector('.product-select');

		if (checkbox.checked) {
			// Jika checked, gunakan produk yang sama
			freeProductSelect.disabled = true;

			// Update preview
			updateBogoPreview(bogoItem);
		} else {
			// Jika unchecked, aktifkan pilihan produk gratis
			freeProductSelect.disabled = false;

			// Kosongkan nilai
			try {
				$(freeProductSelect).val(null).trigger('change');
			} catch (e) {
				freeProductSelect.value = '';
			}

			// Update preview
			updateBogoPreview(bogoItem);
		}
	};

	// Fungsi untuk setup event listeners untuk preview
	function setupBogoPreviewListeners(bogoItem) {
		const productSelect = bogoItem.querySelector('.product-select');
		const freeProductSelect = bogoItem.querySelector('.free-product-select');
		const buyQuantityInput = bogoItem.querySelector('[name="buy_quantity[]"]');
		const freeQuantityInput = bogoItem.querySelector('[name="free_quantity[]"]');
		const useSameProductCheckbox = bogoItem.querySelector('.use-same-product');

		// Update preview when product is selected
		$(productSelect).on('change', function () {
			updateBogoPreview(bogoItem);

			// If using same product, update the free product value
			if (useSameProductCheckbox.checked) {
				freeProductSelect.value = productSelect.value;
			}
		});

		// Update preview when free product is selected
		$(freeProductSelect).on('change', function () {
			updateBogoPreview(bogoItem);
		});

		// Update preview when quantities change
		buyQuantityInput.addEventListener('input', function () {
			updateBogoPreview(bogoItem);
		});

		freeQuantityInput.addEventListener('input', function () {
			updateBogoPreview(bogoItem);
		});

		// Initial preview update
		updateBogoPreview(bogoItem);
	}

	// Fungsi untuk update preview
	function updateBogoPreview(bogoItem) {
		const productSelect = bogoItem.querySelector('.product-select');
		const freeProductSelect = bogoItem.querySelector('.free-product-select');
		const buyQuantityInput = bogoItem.querySelector('[name="buy_quantity[]"]');
		const freeQuantityInput = bogoItem.querySelector('[name="free_quantity[]"]');
		const useSameProductCheckbox = bogoItem.querySelector('.use-same-product');

		const buyQty = buyQuantityInput.value || 1;
		const freeQty = freeQuantityInput.value || 1;

		// Get product names
		let buyProductName = "produk";
		if (productSelect.selectedIndex > 0) {
			buyProductName = productSelect.options[productSelect.selectedIndex].text;
		}

		let freeProductName = "produk yang sama";
		if (!useSameProductCheckbox.checked && freeProductSelect.selectedIndex > 0) {
			freeProductName = freeProductSelect.options[freeProductSelect.selectedIndex].text;
		} else if (useSameProductCheckbox.checked) {
			freeProductName = buyProductName;
		}

		// Update preview elements
		const buyQtySpan = bogoItem.querySelector('.buy-qty');
		const buyProductSpan = bogoItem.querySelector('.buy-product');
		const freeQtySpan = bogoItem.querySelector('.free-qty');
		const freeProductSpan = bogoItem.querySelector('.free-product');

		buyQtySpan.textContent = buyQty;
		buyProductSpan.textContent = buyProductName;
		freeQtySpan.textContent = freeQty;
		freeProductSpan.textContent = freeProductName;
	}

	// Validasi tambahan sebelum menambahkan BOGO baru
	document.getElementById('addBogoBtn').addEventListener('click', function () {
		const existingBogos = document.querySelectorAll('.bogo-item');

		// Periksa validitas form yang sudah ada sebelum menambahkan yang baru
		let allValid = true;
		existingBogos.forEach((bogo, index) => {
			const product = bogo.querySelector('[name="product_id[]"]').value;
			const useSameProduct = bogo.querySelector('.use-same-product').checked;
			const freeProduct = bogo.querySelector('[name="free_product_id[]"]').value;

			if (!product || (!useSameProduct && !freeProduct)) {
				allValid = false;
			}
		});

		if (!allValid && existingBogos.length > 0) {
			if (!confirm('Beberapa konfigurasi BOGO yang ada belum lengkap. Tetap tambahkan yang baru?')) {
				return;
			}
		}
	});

	// Listener untuk perubahan checkbox "Sama dengan produk yang dibeli"
	document.querySelectorAll('.use-same-product').forEach(checkbox => {
		checkbox.addEventListener('change', function () {
			const bogoItem = this.closest('.bogo-item');
			const freeProductSelect = bogoItem.querySelector('.free-product-select');
			const productSelect = bogoItem.querySelector('.product-select');

			if (this.checked) {
				// Jika checked, set free product sama dengan product yang dibeli
				try {
					$(freeProductSelect).val(productSelect.value).trigger('change');
				} catch (e) {
					freeProductSelect.value = productSelect.value;
				}
			}
		});
	});

	// Fungsi untuk reset form
	window.resetBogoForm = function () {
		if (confirm('Apakah Anda yakin ingin reset form? Semua konfigurasi BOGO akan dihapus.')) {
			const bogoContainer = document.querySelector('.bogo-container');
			while (bogoContainer.firstChild) {
				bogoContainer.removeChild(bogoContainer.firstChild);
			}

			// Tampilkan pesan "Belum Ada BOGO"
			document.querySelector('.empty-bogo-message').style.display = 'block';

			// Tambahkan template default
			document.getElementById('addBogoBtn').click();
		}
	}
});