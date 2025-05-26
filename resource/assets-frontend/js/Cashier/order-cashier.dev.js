let statusTable = []
let openDetailSign = ['1']
let lastFetch = new Date()
let activeNotifications = []
let customerNames = {}
let orderTimes = {}
let orderTimers = {}
let orderTotals = {}
let detailedOrderData = {}
let pollingErrors = 0
const MAX_POLLING_ERRORS = 5
let activeOrders = 0
let notificationRetryCount = 0
const MAX_RETRY_COUNT = 5
let currentFilters = {
  status: 'all',
  search: ''
}
let viewedOrders = new Set()
let viewedSessions = new Set()
let unprocessedWaiterCalls = new Set()
let waiterCallNotificationInterval = null
let ttsEnabled = true
let ttsVolume = 1.0
let ttsRate = 1.0
let ttsPitch = 1.0
let ttsLanguage = 'id-ID'
let ttsQueue = []
let isSpeaking = false
let synth = window.speechSynthesis
let ttsInitialized = false

function saveTableStatusesToStorage () {
  try {
    localStorage.setItem('cashierTableStatuses', JSON.stringify(statusTable))
    localStorage.setItem('cashierCustomerNames', JSON.stringify(customerNames))
    localStorage.setItem('cashierOrderTimes', JSON.stringify(orderTimes))
    localStorage.setItem('cashierOrderTotals', JSON.stringify(orderTotals))
    localStorage.setItem(
      'cashierDetailedOrderData',
      JSON.stringify(detailedOrderData)
    )
    localStorage.setItem(
      'cashierViewedOrders',
      JSON.stringify(Array.from(viewedOrders))
    )
    localStorage.setItem('cashierStatusLastSaved', new Date().toISOString())
    console.log('Table statuses saved to localStorage')
  } catch (error) {
    console.error('Error saving statuses to localStorage:', error)
  }
}

function loadTableStatusesFromStorage () {
  try {
    const savedStatuses = localStorage.getItem('cashierTableStatuses')
    const savedCustomerNames = localStorage.getItem('cashierCustomerNames')
    const savedOrderTimes = localStorage.getItem('cashierOrderTimes')
    const savedOrderTotals = localStorage.getItem('cashierOrderTotals')
    const savedDetailedOrderData = localStorage.getItem(
      'cashierDetailedOrderData'
    )
    const savedViewedOrders = localStorage.getItem('cashierViewedOrders')
    const lastSaved = localStorage.getItem('cashierStatusLastSaved')

    // Cek apakah data tersimpan dan tidak lebih dari 24 jam
    if (savedStatuses && lastSaved) {
      const lastSavedTime = new Date(lastSaved)
      const currentTime = new Date()
      const hoursDifference = (currentTime - lastSavedTime) / (1000 * 60 * 60)

      // Hanya gunakan data yang disimpan kurang dari 24 jam
      if (hoursDifference < 24) {
        statusTable = JSON.parse(savedStatuses)

        if (savedCustomerNames) customerNames = JSON.parse(savedCustomerNames)
        if (savedOrderTimes) orderTimes = JSON.parse(savedOrderTimes)
        if (savedOrderTotals) orderTotals = JSON.parse(savedOrderTotals)
        if (savedDetailedOrderData)
          detailedOrderData = JSON.parse(savedDetailedOrderData)

        // Restore viewed orders set
        if (savedViewedOrders) {
          viewedOrders = new Set(JSON.parse(savedViewedOrders))
        }

        console.log('Loaded table statuses from localStorage:', statusTable)
        return true
      }
    }
    return false
  } catch (error) {
    console.error('Error loading statuses from localStorage:', error)
    return false
  }
}

function initTextToSpeech () {
  if (ttsInitialized) return

  console.group('Text-to-Speech Initialization')

  // Check if browser supports speech synthesis
  if (!('speechSynthesis' in window)) {
    console.error('Browser tidak mendukung Text-to-Speech')
    showToastNotification(
      'Peringatan Fitur',
      'Browser Anda tidak mendukung fitur Text-to-Speech. Notifikasi suara tidak akan berfungsi.',
      'warning'
    )
    ttsEnabled = false
    ttsInitialized = true
    console.groupEnd()
    return
  }

  // Load from config element if exists
  const configElement = document.getElementById('tts-config')
  if (configElement) {
    ttsEnabled = configElement.getAttribute('data-enabled') !== 'false'
    ttsVolume = parseFloat(configElement.getAttribute('data-volume') || '1.0')
    ttsRate = parseFloat(configElement.getAttribute('data-rate') || '1.0')
    ttsPitch = parseFloat(configElement.getAttribute('data-pitch') || '1.0')
    ttsLanguage = configElement.getAttribute('data-lang') || 'id-ID'
  }

  // Try to load from localStorage if available
  try {
    const savedSettings = localStorage.getItem('ttsSettings')
    if (savedSettings) {
      const settings = JSON.parse(savedSettings)
      ttsEnabled = settings.enabled !== false
      ttsVolume = parseFloat(settings.volume || 1.0)
      ttsRate = parseFloat(settings.rate || 1.0)
      ttsPitch = parseFloat(settings.pitch || 1.0)
      ttsLanguage = settings.language || 'id-ID'
    }
  } catch (e) {
    console.warn('Error loading TTS settings from localStorage:', e)
  }

  // Initialize settings UI if elements exist
  const ttsEnabledToggle = document.getElementById('tts-enabled')
  const ttsVolumeSlider = document.getElementById('tts-volume')
  const ttsRateSlider = document.getElementById('tts-rate')
  const ttsPitchSlider = document.getElementById('tts-pitch')
  const ttsLanguageSelect = document.getElementById('tts-language')

  if (ttsEnabledToggle) ttsEnabledToggle.checked = ttsEnabled
  if (ttsVolumeSlider) ttsVolumeSlider.value = ttsVolume
  if (ttsRateSlider) ttsRateSlider.value = ttsRate
  if (ttsPitchSlider) ttsPitchSlider.value = ttsPitch
  if (ttsLanguageSelect) ttsLanguageSelect.value = ttsLanguage

  // Update display values
  updateSettingsDisplayValues()

  // Set up settings button
  const settingsButton = document.getElementById('open-tts-settings')
  if (settingsButton) {
    settingsButton.addEventListener('click', function () {
      const modal = new bootstrap.Modal(
        document.getElementById('ttsSettingsModal')
      )
      modal.show()
    })
  }

  // Set up test button
  const testButton = document.getElementById('test-tts')
  if (testButton) {
    testButton.addEventListener('click', function () {
      speakNotification(
        'Ini adalah tes notifikasi suara. Sistem siap memberikan informasi order baru.',
        'test'
      )
    })
  }

  // Set up save settings button
  const saveButton = document.getElementById('save-tts-settings')
  if (saveButton) {
    saveButton.addEventListener('click', function () {
      saveTtsSettings()
      const modal = bootstrap.Modal.getInstance(
        document.getElementById('ttsSettingsModal')
      )
      if (modal) modal.hide()

      showToastNotification(
        'Pengaturan Disimpan',
        'Pengaturan notifikasi suara telah disimpan',
        'success'
      )
    })
  }

  // Update values on input change
  if (ttsVolumeSlider) {
    ttsVolumeSlider.addEventListener('input', function () {
      document.getElementById('volume-value').textContent =
        Math.round(this.value * 100) + '%'
    })
  }

  if (ttsRateSlider) {
    ttsRateSlider.addEventListener('input', function () {
      document.getElementById('rate-value').textContent =
        Math.round(this.value * 100) + '%'
    })
  }

  if (ttsPitchSlider) {
    ttsPitchSlider.addEventListener('input', function () {
      document.getElementById('pitch-value').textContent =
        Math.round(this.value * 100) + '%'
    })
  }

  ttsInitialized = true
  console.log('Text-to-Speech initialized with settings:', {
    enabled: ttsEnabled,
    volume: ttsVolume,
    rate: ttsRate,
    pitch: ttsPitch,
    language: ttsLanguage
  })

  console.groupEnd()
}

// Update settings display values
function updateSettingsDisplayValues () {
  const volumeValue = document.getElementById('volume-value')
  const rateValue = document.getElementById('rate-value')
  const pitchValue = document.getElementById('pitch-value')

  if (volumeValue) volumeValue.textContent = Math.round(ttsVolume * 100) + '%'
  if (rateValue) rateValue.textContent = Math.round(ttsRate * 100) + '%'
  if (pitchValue) pitchValue.textContent = Math.round(ttsPitch * 100) + '%'
}

// Save TTS settings
function saveTtsSettings () {
  const ttsEnabledToggle = document.getElementById('tts-enabled')
  const ttsVolumeSlider = document.getElementById('tts-volume')
  const ttsRateSlider = document.getElementById('tts-rate')
  const ttsPitchSlider = document.getElementById('tts-pitch')
  const ttsLanguageSelect = document.getElementById('tts-language')

  ttsEnabled = ttsEnabledToggle ? ttsEnabledToggle.checked : ttsEnabled
  ttsVolume = ttsVolumeSlider ? parseFloat(ttsVolumeSlider.value) : ttsVolume
  ttsRate = ttsRateSlider ? parseFloat(ttsRateSlider.value) : ttsRate
  ttsPitch = ttsPitchSlider ? parseFloat(ttsPitchSlider.value) : ttsPitch
  ttsLanguage = ttsLanguageSelect ? ttsLanguageSelect.value : ttsLanguage

  // Save to localStorage
  try {
    localStorage.setItem(
      'ttsSettings',
      JSON.stringify({
        enabled: ttsEnabled,
        volume: ttsVolume,
        rate: ttsRate,
        pitch: ttsPitch,
        language: ttsLanguage
      })
    )
  } catch (e) {
    console.warn('Error saving TTS settings to localStorage:', e)
  }

  // Update config element
  const configElement = document.getElementById('tts-config')
  if (configElement) {
    configElement.setAttribute('data-enabled', ttsEnabled.toString())
    configElement.setAttribute('data-volume', ttsVolume.toString())
    configElement.setAttribute('data-rate', ttsRate.toString())
    configElement.setAttribute('data-pitch', ttsPitch.toString())
    configElement.setAttribute('data-lang', ttsLanguage)
  }
}

function speakNotification (text, type = 'info') {
  if (!ttsInitialized) initTextToSpeech()

  // Skip if TTS is disabled
  if (!ttsEnabled || !('speechSynthesis' in window)) return

  console.log(`Speaking notification (${type}): ${text}`)

  // PERBAIKAN: Pastikan type adalah string valid untuk perbandingan
  const notificationType = type ? type.toString() : 'info'

  // Add to queue
  ttsQueue.push({ text: text, type: notificationType })

  // Process queue if not already speaking
  if (!isSpeaking) {
    processNextTtsItem()
  }

  // PERBAIKAN: Jika ini notifikasi tipe bell dan belum ada loop aktif, mulai loop
  if (
    (notificationType === 'orderBell' ||
      notificationType === 'orderSuccessBell' ||
      notificationType === 'newCustomerBell') &&
    !loopNotificationInterval
  ) {
  }
}

// Process next TTS item in queue
function processNextTtsItem () {
  // If nothing in queue or already speaking, return
  if (ttsQueue.length === 0 || isSpeaking) return

  isSpeaking = true
  const item = ttsQueue.shift()

  const utterance = new SpeechSynthesisUtterance(item.text)
  utterance.volume = ttsVolume
  utterance.rate = ttsRate
  utterance.pitch = ttsPitch
  utterance.lang = ttsLanguage

  // Set voice based on language if available
  const voices = synth.getVoices()
  const languageVoices = voices.filter(voice =>
    voice.lang.startsWith(ttsLanguage.split('-')[0])
  )

  if (languageVoices.length > 0) {
    // Prefer female voice if available (usually clearer for notifications)
    const femaleVoice = languageVoices.find(voice =>
      voice.name.includes('Female')
    )
    utterance.voice = femaleVoice || languageVoices[0]
  }

  // Add event listeners
  utterance.onend = function () {
    console.log('Notification speech completed')
    isSpeaking = false
    processNextTtsItem() // Process next item in queue
  }

  utterance.onerror = function (event) {
    console.error('TTS error:', event)
    isSpeaking = false
    processNextTtsItem() // Try next item in queue
  }

  // Speak the notification
  synth.speak(utterance)
}

// Stop all TTS notifications
function stopAllNotificationSounds () {
  if (synth) {
    synth.cancel() // Cancel any ongoing speech
  }
  ttsQueue = [] // Clear the queue
  isSpeaking = false
}

// Replace old sound functions with TTS equivalents
function playNotificationSound (soundId) {
  // Map sound IDs to notification messages
  const soundMessages = {
    orderBell: 'Perhatian! Ada order baru yang perlu diproses.',
    newCustomerBell: 'Pelanggan baru telah tiba.',
    orderSuccessBell: 'Order baru telah sukses diterima.'
  }

  // Get the appropriate message
  const message = soundMessages[soundId] || 'Notifikasi baru'

  // Use TTS instead of audio
  speakNotification(message, soundId)
}

function stopNotificationSound (soundId) {
  // Stop all TTS for now (we could make it more specific if needed)
  stopAllNotificationSounds()
}

function notifyNewOrder (tableNumbers) {
  const tableText = Array.isArray(tableNumbers)
    ? tableNumbers.join(', ')
    : tableNumbers
  speakNotification(
    `Perhatian! Ada order baru dari meja ${tableText}. Silakan proses order segera.`,
    'orderBell'
  )

  // PERBAIKAN: Set flag untuk loop notification sampai dilihat
  window.activeBellNotification = {
    type: 'orderBell',
    tables: Array.isArray(tableNumbers) ? tableNumbers : [tableNumbers],
    timestamp: new Date().getTime()
  }

  // Start loop notification
  startLoopNotification()
}

// Modifikasi fungsi notifyNewCustomer untuk menerima data customer
function notifyNewCustomer (tableNumbers, customerData = {}) {
  const tableText = Array.isArray(tableNumbers)
    ? tableNumbers.join(', ')
    : tableNumbers

  // Buat pesan yang lebih informatif dengan nama pelanggan
  let message = `Pelanggan baru di meja ${tableText} telah mulai memesan.`

  // Jika ada data customer, tambahkan ke pesan
  if (Object.keys(customerData).length > 0) {
    message = 'Pelanggan baru '
    tableNumbers.forEach((tableId, index) => {
      // Gunakan nama dari parameter atau dari state global, atau default ke "tanpa nama"
      const name =
        customerData[tableId] || customerNames[tableId] || 'tanpa nama'
      message += `meja ${tableId}: ${name}`

      if (index < tableNumbers.length - 1) {
        message += ', '
      }
    })
    message += ' telah mulai memesan.'
  }

  speakNotification(message, 'newCustomerBell')

  // PERBAIKAN: Set flag untuk loop notification sampai dilihat
  window.activeBellNotification = {
    type: 'newCustomerBell',
    tables: tableNumbers,
    timestamp: new Date().getTime()
  }

  // Start loop notification
  startLoopNotification()
}

function notifyWaiterCall (tableId) {
  // Ambil nama customer dari data yang ada
  const customerName = customerNames[tableId] || 'tanpa nama'

  // Gunakan TTS untuk notifikasi
  speakNotification(
    `Panggilan pelayan dari meja ${tableId}. Customer ${customerName} membutuhkan bantuan segera.`,
    'waiterCallBell'
  )

  // PERBAIKAN: Set flag untuk loop notification sampai diproses
  window.activeWaiterCallNotification = {
    type: 'waiterCallBell',
    tables: [parseInt(tableId)],
    calls: [], // Akan diisi oleh checkWaiterCalls
    timestamp: new Date().getTime()
  }

  // Mulai loop notifikasi
  startWaiterCallLoopNotification()
}

function notifyNewWaiterCalls (calls) {
  if (!calls || calls.length === 0) return

  // Group by table
  const tableGroups = {}
  calls.forEach(call => {
    if (!tableGroups[call.table_id]) {
      tableGroups[call.table_id] = []
    }
    tableGroups[call.table_id].push(call)
  })

  const tables = Object.keys(tableGroups)

  // Buat pesan TTS
  let message = `Perhatian! Ada ${
    calls.length
  } panggilan pelayan baru dari meja ${tables.join(', ')}. `

  // Tambahkan detail customer jika tersedia
  tables.forEach((tableId, index) => {
    const customerName = customerNames[tableId] || 'tanpa nama'
    message += `Meja ${tableId}: pelanggan ${customerName}`

    if (index < tables.length - 1) {
      message += ', '
    }
  })

  // Gunakan TTS untuk notifikasi
  speakNotification(message, 'waiterCallBell')

  // PERBAIKAN: Set flag untuk loop notification sampai diproses
  window.activeWaiterCallNotification = {
    type: 'waiterCallBell',
    tables: tables.map(t => parseInt(t)),
    calls: calls.map(c => c.id),
    timestamp: new Date().getTime()
  }

  // Start loop notification untuk panggilan pelayan
  startWaiterCallLoopNotification()
}

function updateOrderStatus (tableId, status) {
  // Validasi input
  if (!tableId || status === undefined) {
    console.error('Parameter tidak valid untuk update status')
    Swal.fire({
      title: 'Kesalahan',
      text: 'Parameter update status tidak valid',
      icon: 'error'
    })
    return
  }

  // Dapatkan parameter URL dengan cara yang lebih robust
  const currentUrl = window.location.href
  const url = new URL(currentUrl)
  const baseUrl = `${url.protocol}//${url.host}`
  const brand = $('#brand').val() || url.searchParams.get('brand')
  const outletId = url.pathname.split('/').pop()

  // Tampilkan loading
  Swal.fire({
    title: 'Memperbarui Status',
    html: '<div class="text-center"><div class="spinner-border text-primary"></div><p class="mt-3">Sedang memproses perubahan status...</p></div>',
    showConfirmButton: false,
    allowOutsideClick: false
  })

  // Kirim request update status
  fetch(`${baseUrl}/order/updateStatus`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: JSON.stringify({
      tableId: tableId,
      outletId: outletId,
      brand: brand,
      status: parseInt(status)
    })
  })
    .then(response => {
      // Pastikan response adalah JSON valid
      const contentType = response.headers.get('content-type')
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error('Response bukan JSON valid')
      }
      return response.json()
    })
    .then(data => {
      if (data.success) {
        // Update status lokal
        const index = parseInt(tableId) - 1
        statusTable[index] = parseInt(status)

        // Update UI di semua tempat
        changeStatusState(
          tableId,
          status,
          null,
          null,
          null,
          data.data?.status_label || ''
        )
        updateTableStatusUI(tableId, status, data.data?.status_label || '')

        // Simpan ke localStorage
        saveTableStatusesToStorage()

        // TAMBAHAN: Jika status adalah CANCELLED (5), dispatch event orderCancelled
        if (parseInt(status) === 5) {
          console.log('Dispatching orderCancelled event for table', tableId)
          document.dispatchEvent(
            new CustomEvent('orderCancelled', {
              detail: {
                tableId: tableId,
                orderId: data.data?.order_id
              }
            })
          )
        }

        // Tampilkan notifikasi sukses
        Swal.fire({
          title: 'Berhasil',
          text: `Status meja ${tableId} berhasil diubah`,
          icon: 'success',
          timer: 2000,
          showConfirmButton: false
        })

        // Refresh status
        setTimeout(pollTableStatus, 500)

        // Muat ulang detail order untuk memastikan status terbaru
        fetchOrderDetails(tableId)
      } else {
        throw new Error(data.message || 'Gagal update status')
      }
    })
    .catch(error => {
      console.error('Kesalahan update status:', error)
      Swal.fire({
        title: 'Kesalahan',
        text: error.message || 'Terjadi kesalahan saat memperbarui status',
        icon: 'error'
      })
    })
}

// Fungsi tambahan untuk memastikan status diupdate di server
function recordOrderStatusToServer (tableId, status, orderId) {
  // Get current URL parameters with better path handling
  const currentUrl = window.location.href
  const url = new URL(currentUrl)
  const baseUrl = `${url.protocol}//${url.host}`

  // Pastikan brand dan outletId diambil dengan benar
  const brand = $('#brand').val() || url.searchParams.get('brand')
  const outletId = url.pathname.split('/').pop()

  // Kirim request ke endpoint recordOrderStatus
  fetch(`${baseUrl}/order/history/recordOrderStatus`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: JSON.stringify({
      tableId: tableId,
      outletId: outletId,
      brand: brand,
      status: parseInt(status),
      orderId: orderId
    })
  })
    .then(response => {
      console.log('Status record request sent to server')
    })
    .catch(error => {
      console.warn('Error recording status to server:', error)
    })
}

function updateTableStatusUI (tableId, status, statusLabel) {
  console.log(
    `Updating UI for table ${tableId} to status ${status} (${statusLabel})`
  )

  changeStatusState(tableId, status)

  const elem = $(`#tableStatus-${tableId}`)
  const tableRow = $(`#tableId-${tableId}`)

  elem.removeClass(
    'badge-info badge-primary badge-success badge-secondary badge-danger badge-warning badge-processing badge-served badge-completed badge-cancelled'
  )

  // Reset status text
  switch (parseInt(status)) {
    case 0: // RESERVED
      elem.addClass('badge-primary')
      elem.text('Dipesan')
      break
    case 1: // ORDERED
      elem.addClass('badge-success')
      elem.text('Order Diproses')
      break
    case 2: // PROCESSING
      elem.addClass('badge-processing')
      elem.text('Sedang Diproses')
      break
    case 3: // SERVED
      elem.addClass('badge-served')
      elem.text('Sudah Diantar')
      break
    case 4: // COMPLETED
      elem.addClass('badge-completed')
      elem.text('Selesai')

      // PERBAIKAN: Setelah 10 detik, hapus data meja ini
      setTimeout(() => {
        statusTable[tableId - 1] = null
        changeStatusState(tableId, null)
        saveTableStatusesToStorage()
      }, 10000)
      break
    case 5: // CANCELLED
      elem.addClass('badge-cancelled')
      elem.text('Dibatalkan')

      // PERBAIKAN: Setelah beberapa detik, hapus data meja ini
      setTimeout(() => {
        statusTable[tableId - 1] = null
        changeStatusState(tableId, null)
        saveTableStatusesToStorage()
      }, 5000)
      break
    default:
      elem.addClass('badge-info')
      elem.text(statusLabel || 'Unknown')
  }

  // Force reload status untuk memastikan status berubah
  console.log(`Force polling table status after UI update`)
  setTimeout(() => {
    pollTableStatus()
  }, 200)
}

function changeStatusState (
  id,
  signal,
  customerName = null,
  orderTime = null,
  orderTotal = null,
  statusLabel = null
) {
  const elem = $('#tableStatus-' + id)
  const tableRow = $('#tableId-' + id)
  const nameCell = $('#tableName-' + id)
  const timeCell = $('#tableTime-' + id)
  const totalCell = $('#tableTotal-' + id)
  const printButton = tableRow.find('.print-receipt')

  // PERBAIKAN: Periksa apakah elemen statusButton ada sebelum mengaksesnya
  const statusButton = tableRow.find('.update-status')
  const hasStatusButton = statusButton.length > 0

  // PERBAIKAN: Log untuk debugging
  console.log(`changeStatusState for table ${id}:`, {
    signal,
    customerName,
    orderTime,
    orderTotal,
    currentStoredCustomerName: customerNames[id],
    hasStatusButton: hasStatusButton,
    detailedOrderData: detailedOrderData[id]
  })

  // Update customer name if provided and not empty
  if (customerName && customerName.trim() !== '') {
    customerNames[id] = customerName
    nameCell.text(customerName)
  } else if (customerNames[id]) {
    // Pertahankan nama yang sudah ada jika parameter kosong
    nameCell.text(customerNames[id])
  }

  // Update order time if provided
  if (orderTime) {
    orderTimes[id] = orderTime
    // Mulai timer jika status aktif (1-3)
    if (
      signal === 1 ||
      signal === 2 ||
      signal === 3 ||
      signal === '1' ||
      signal === '2' ||
      signal === '3'
    ) {
      startOrderTimer(id, orderTime)
    }
  }

  // PERBAIKAN UTAMA: Prioritaskan total dari detail order jika tersedia
  let finalTotal = null

  // Cek apakah ada data detail order yang tersedia
  if (detailedOrderData[id] && detailedOrderData[id].total) {
    // Gunakan total dari detail order (ini nilai yang sudah tepat termasuk diskon)
    finalTotal = parseFloat(detailedOrderData[id].total)
    console.log(
      `Table ${id} - Using total from detailed order data: ${finalTotal}`
    )
  } else if (orderTotal !== null && orderTotal !== undefined) {
    // Jika tidak ada detail, gunakan parameter orderTotal
    let numericTotal = parseFloat(orderTotal)
    console.log(
      `Table ${id} - Raw Total from parameter: ${orderTotal}, Numeric: ${numericTotal}`
    )

    if (!isNaN(numericTotal)) {
      // Simpan total asli ke orderTotals (mungkin belum memperhitungkan diskon)
      orderTotals[id] = numericTotal

      // Jika ada detail data tetapi tidak ada total, coba periksa diskon
      if (detailedOrderData[id]) {
        // Periksa diskon jika ada
        const discountAmount = parseFloat(
          detailedOrderData[id].discount_amount || 0
        )
        const promoType =
          detailedOrderData[id].discount_type ||
          (detailedOrderData[id].discount_code &&
          detailedOrderData[id].discount_code.toUpperCase().includes('BUNDLING')
            ? 'bundling'
            : detailedOrderData[id].discount_code &&
              (detailedOrderData[id].discount_code
                .toUpperCase()
                .includes('BOGO') ||
                (detailedOrderData[id].discount_code
                  .toUpperCase()
                  .includes('BUY') &&
                  detailedOrderData[id].discount_code
                    .toUpperCase()
                    .includes('GET')))
            ? 'bogo'
            : null)

        // Jika bukan bundling/BOGO, diskon harus dikurangkan dari total
        if (
          discountAmount > 0 &&
          promoType !== 'bundling' &&
          promoType !== 'bogo'
        ) {
          numericTotal = Math.max(0, numericTotal - discountAmount)
          console.log(
            `Table ${id} - Applied discount ${discountAmount}, new subtotal: ${numericTotal}`
          )
        }

        // Hitung total dengan pajak
        const tax = numericTotal * 0.1 // 10% pajak
        finalTotal = numericTotal + tax
        console.log(
          `Table ${id} - Calculated final total with tax: ${finalTotal}`
        )
      } else {
        // Tidak ada detail data, gunakan perhitungan standar dengan pajak
        const tax = numericTotal * 0.1 // 10% pajak
        finalTotal = numericTotal + tax
      }
    }
  }

  // Tampilkan total jika tersedia
  if (finalTotal !== null && !isNaN(finalTotal)) {
    totalCell.text(formatCurrency(finalTotal))
    console.log(
      `Table ${id} - Updated total cell to: ${formatCurrency(finalTotal)}`
    )
  }

  // PERBAIKAN: Handle status numerik (0-5) atau string
  // Konversi signal ke string untuk perbandingan yang konsisten
  const signalStr = String(signal).toLowerCase()
  console.log(
    `Changing status for table ${id}: signal=${signal}, type=${typeof signal}, signalStr=${signalStr}`
  )

  // Hapus semua class sebelumnya
  elem.removeClass(
    'badge-info badge-primary badge-success badge-secondary badge-danger badge-warning badge-processing badge-served badge-completed badge-cancelled'
  )
  tableRow.removeClass(
    'has-order has-reservation has-expired has-processing has-served has-completed has-cancelled pulse-animation'
  )

  // PERBAIKAN UTAMA: Simpan status dalam statusTable dalam format NUMERIK konsisten
  // Konversi ke numeric status sebelum disimpan, untuk memastikan konsistensi
  let numericStatus = null

  // PERBAIKAN: Convert status to numeric for consistent storage
  if (signal === 0 || signalStr === '0' || signalStr === 'reserved') {
    numericStatus = 0
  } else if (signal === 1 || signalStr === '1' || signalStr === 'ordered') {
    numericStatus = 1
  } else if (signal === 2 || signalStr === '2' || signalStr === 'processing') {
    numericStatus = 2
  } else if (signal === 3 || signalStr === '3' || signalStr === 'served') {
    numericStatus = 3
  } else if (signal === 4 || signalStr === '4' || signalStr === 'completed') {
    numericStatus = 4
  } else if (signal === 5 || signalStr === '5' || signalStr === 'cancelled') {
    numericStatus = 5
  }

  // Update statusTable dengan nilai numerik
  if (numericStatus !== null) {
    statusTable[id - 1] = numericStatus
  }

  // [Kode status handling yang sudah ada - tidak diubah]
  if (signal === 1 || signalStr === '1' || signalStr === 'ordered') {
    // STATUS_ORDERED - meja dengan order aktif
    elem.addClass('badge-success')
    tableRow.addClass('has-order pulse-animation')
    elem.text('Order Diproses')

    // Mulai timer jika ada orderTime
    if (orderTimes[id]) {
      startOrderTimer(id, orderTimes[id])
    } else {
      startOrderTimer(id) // Mulai timer baru
    }

    // Enable print button
    printButton.prop('disabled', false)

    // PERBAIKAN: Hanya akses statusButton jika ada
    if (hasStatusButton) {
      statusButton.prop('disabled', false)
    }
  } else if (signal === 0 || signalStr === '0' || signalStr === 'reserved') {
    // STATUS_RESERVED - meja dengan reservasi tapi belum order
    elem.addClass('badge-primary')
    tableRow.addClass('has-reservation')
    elem.text('Dipesan')

    // Tampilkan waktu reservasi tanpa timer
    if (orderTimes[id]) {
      timeCell.text(
        new Date(orderTimes[id]).toLocaleTimeString('id-ID', {
          hour: '2-digit',
          minute: '2-digit'
        })
      )
    }

    // Enable viewing details but disable print
    printButton.prop('disabled', true)

    // PERBAIKAN: Hanya akses statusButton jika ada
    if (hasStatusButton) {
      statusButton.prop('disabled', false)
    }
  } else if (signal === 2 || signalStr === '2' || signalStr === 'processing') {
    // STATUS_PROCESSING - meja dengan pesanan sedang diproses
    elem.addClass('badge-processing')
    tableRow.addClass('has-processing')
    elem.text('Sedang Diproses')

    // Lanjutkan timer yang sudah berjalan atau mulai baru
    if (orderTimes[id]) {
      startOrderTimer(id, orderTimes[id])
    }

    // Enable buttons
    printButton.prop('disabled', false)

    // PERBAIKAN: Hanya akses statusButton jika ada
    if (hasStatusButton) {
      statusButton.prop('disabled', false)
    }
  } else if (signal === 3 || signalStr === '3' || signalStr === 'served') {
    // STATUS_SERVED - meja dengan pesanan yang sudah diantar
    elem.addClass('badge-served')
    tableRow.addClass('has-served')
    elem.text('Sudah Diantar')

    // Lanjutkan timer yang sudah berjalan atau mulai baru
    if (orderTimes[id]) {
      startOrderTimer(id, orderTimes[id])
    }

    // Enable buttons
    printButton.prop('disabled', false)

    // PERBAIKAN: Hanya akses statusButton jika ada
    if (hasStatusButton) {
      statusButton.prop('disabled', false)
    }
  } else if (signal === 4 || signalStr === '4' || signalStr === 'completed') {
    // STATUS_COMPLETED - meja dengan pesanan selesai
    elem.addClass('badge-completed')
    tableRow.addClass('has-completed')
    elem.text('Selesai')

    // Hentikan timer dan tampilkan waktu selesai
    stopOrderTimer(id)
    if (orderTimes[id]) {
      const orderDate = new Date(orderTimes[id])
      const completionTime = new Date()
      const duration = completionTime - orderDate
      const minutes = Math.floor(duration / (1000 * 60))
      timeCell.html(
        `<span class="small">Selesai</span><br>Total: ${minutes} menit`
      )
    }

    // Enable print
    printButton.prop('disabled', false)

    // PERBAIKAN: Hanya akses statusButton jika ada
    if (hasStatusButton) {
      statusButton.prop('disabled', false)
    }
  } else if (signal === 5 || signalStr === '5' || signalStr === 'cancelled') {
    // STATUS_CANCELLED - meja dengan pesanan dibatalkan
    elem.addClass('badge-cancelled')
    tableRow.addClass('has-cancelled')
    elem.text('Dibatalkan')

    // Hentikan timer dan tampilkan status dibatalkan
    stopOrderTimer(id)
    timeCell.html(`<span class="text-danger">Dibatalkan</span>`)

    // Disable buttons
    printButton.prop('disabled', true)

    // PERBAIKAN: Hanya akses statusButton jika ada
    if (hasStatusButton) {
      statusButton.prop('disabled', false)
    }
  } else if (!signal && signal !== 0) {
    // Signal null atau undefined - meja kosong
    elem.addClass('badge-info')
    elem.text('Kosong')

    // Hentikan timer jika ada
    stopOrderTimer(id)

    // Reset customer name, time, and total
    nameCell.text('-')
    timeCell.text('-')
    totalCell.text('-')

    // Disable buttons
    printButton.prop('disabled', true)

    // PERBAIKAN: Hanya akses statusButton jika ada
    if (hasStatusButton) {
      statusButton.prop('disabled', true)
    }

    // Reset stored values
    delete customerNames[id]
    delete orderTimes[id]
    delete orderTotals[id]
    delete detailedOrderData[id]

    // Remove from statusTable explicitly
    statusTable[id - 1] = null
  }

  // PERBAIKAN: Simpan perubahan ke localStorage setelah setiap perubahan status
  saveTableStatusesToStorage()

  // Update active orders count
  updateActiveOrdersCount()

  // Apply current filters
  applyFilters()
}

// Tambahkan fungsi untuk menangani notifikasi berulang
let loopNotificationInterval = null

function startLoopNotification () {
  // Hentikan interval yang sudah ada jika masih berjalan
  if (loopNotificationInterval) {
    clearInterval(loopNotificationInterval)
  }

  // Mulai interval baru
  loopNotificationInterval = setInterval(() => {
    // Periksa apakah ada notifikasi aktif
    if (window.activeBellNotification) {
      const { type, tables, timestamp } = window.activeBellNotification
      const currentTime = new Date().getTime()

      // Hanya repeat jika notifikasi belum terlalu lama (max 10 menit)
      if (currentTime - timestamp < 10 * 60 * 1000) {
        // Periksa jika semua tables sudah dilihat
        const allTablesViewed = tables.every(tableId =>
          viewedOrders.has(tableId.toString())
        )

        if (!allTablesViewed) {
          // Buat pesan pengingat
          if (type === 'newCustomerBell') {
            const tablesText = tables.join(', ')
            const message = `Pengingat: Ada pelanggan baru di meja ${tablesText} yang belum dilayani.`
            speakNotification(message, 'bellReminder')
          } else if (type === 'orderBell' || type === 'orderSuccessBell') {
            const unviewedTables = tables.filter(
              tableId => !viewedOrders.has(tableId.toString())
            )
            const message = `Pengingat: Ada ${
              unviewedTables.length
            } order baru di meja ${unviewedTables.join(
              ', '
            )} yang belum dilihat.`
            speakNotification(message, 'bellReminder')
          }
        } else {
          // Semua tabel sudah dilihat, hentikan notifikasi
          stopLoopNotification()
        }
      } else {
        // Notifikasi sudah terlalu lama, hentikan
        stopLoopNotification()
      }
    }
  }, 25000) // Setiap 25 detik

  console.log('Loop notification started')
}

function stopLoopNotification () {
  if (loopNotificationInterval) {
    clearInterval(loopNotificationInterval)
    loopNotificationInterval = null
  }

  window.activeBellNotification = null
  console.log('Loop notification stopped')
}

function startWaiterCallLoopNotification () {
  // Hentikan interval yang sudah ada jika masih berjalan
  if (waiterCallNotificationInterval) {
    clearInterval(waiterCallNotificationInterval)
  }

  // Mulai interval baru
  waiterCallNotificationInterval = setInterval(() => {
    // Periksa apakah ada notifikasi panggilan pelayan aktif
    if (window.activeWaiterCallNotification) {
      const { tables, calls, timestamp } = window.activeWaiterCallNotification
      const currentTime = new Date().getTime()

      // Hanya repeat jika notifikasi belum terlalu lama (max 10 menit)
      if (currentTime - timestamp < 10 * 60 * 1000) {
        // Periksa jika semua panggilan sudah diproses
        const unprocessedCalls = calls.filter(callId =>
          unprocessedWaiterCalls.has(callId)
        )

        if (unprocessedCalls.length > 0) {
          // Buat pesan pengingat
          const message = `Pengingat: Masih ada ${
            unprocessedCalls.length
          } panggilan pelayan dari meja ${tables.join(
            ', '
          )} yang belum diproses.`
          speakNotification(message, 'waiterCallReminder')
        } else {
          // Semua panggilan sudah diproses, hentikan notifikasi
          stopWaiterCallLoopNotification()
        }
      } else {
        // Notifikasi sudah terlalu lama, hentikan
        stopWaiterCallLoopNotification()
      }
    }
  }, 30000) // Setiap 30 detik

  console.log('Waiter call loop notification started')
}

function stopWaiterCallLoopNotification () {
  if (waiterCallNotificationInterval) {
    clearInterval(waiterCallNotificationInterval)
    waiterCallNotificationInterval = null
  }

  window.activeWaiterCallNotification = null
  console.log('Waiter call loop notification stopped')
}

// Format currency helper
function formatCurrency (amount) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount)
}

function updateActiveOrdersCount () {
  // PERBAIKAN: Gunakan logika yang lebih baik untuk menghitung order aktif
  console.log('Updating active orders count. Status table:', statusTable)

  // Pastikan statusTable adalah array
  if (!Array.isArray(statusTable)) {
    console.error('statusTable is not an array:', statusTable)
    activeOrders = 0
    $('#activeOrdersCount').text(activeOrders)
    $('#activityIndicator').hide()
    return
  }

  // PERBAIKAN: Hitung meja dengan status 0-4 (reserved sampai completed)
  activeOrders = statusTable.filter(status => {
    // Konversi status ke number untuk konsistensi
    const numStatus = Number(status)
    // Hanya status 0-4 yang dianggap aktif (status 5 adalah cancelled)
    return numStatus >= 0 && numStatus <= 4
  }).length

  // Update UI
  $('#activeOrdersCount').text(activeOrders)

  // Show/hide activity indicator
  if (activeOrders > 0) {
    $('#activityIndicator').show()
  } else {
    $('#activityIndicator').hide()
  }

  console.log('Active orders count updated:', activeOrders)
}

function getTableStatus (response, textStatus, jqXHR) {
  console.group('Processing Table Status Update')
  let orderRang = false
  let newOrders = []
  let customerUpdates = []
  let newSessions = []

  // DEBUGGING: Log raw response
  console.log('Raw response:', response)
  const respData =
    (response.data && response.data.data) || response.data || response
  console.log('Extracted response data:', respData)

  // PERBAIKAN KRITIS: Ambil data dari level yang benar dalam respons
  const newStatuses = respData.statuses || []
  const customerData = respData.customers || {}
  const orderTimeData = respData.orderTimes || {}
  const orderTotalData = respData.totals || {}
  const serverNewSessions = respData.newSessions || []
  const serverNewOrders = respData.newOrders || []
  const promoData = respData.promos || {} // PERBAIKAN: Dapatkan informasi promo jika ada

  console.log('Server new sessions:', serverNewSessions)
  console.log('Server new orders:', serverNewOrders)
  console.log('Customer data received from server:', respData.customers)
  console.log('Promo data received from server:', promoData)

  // Process server notifications for new sessions
  if (serverNewSessions.length > 0) {
    if (!window.processedNotifications) {
      window.processedNotifications = new Set()
    }
    const sessionCustomers = {}
    serverNewSessions.forEach(session => {
      // Only process if not already processed
      const sessionKey =
        'session_' + session.table_id + '_' + session.created_at
      if (!window.processedNotifications.has(sessionKey)) {
        newSessions.push(session.table_id)
        // PERBAIKAN: Simpan nama customer dari data sesi
        if (session.customer_name) {
          sessionCustomers[session.table_id] = session.customer_name
          // Update langsung state global customerNames
          customerNames[session.table_id] = session.customer_name
          // Update UI secara langsung
          $('#tableName-' + session.table_id).text(session.customer_name)
          console.log(
            `Setting customer name for table ${session.table_id} to "${session.customer_name}"`
          )
        }
        window.processedNotifications.add(sessionKey)
      }
    })

    // Play notification sound for new sessions with TTS
    if (newSessions.length > 0) {
      console.log('Playing notification for new sessions:', newSessions)
      // PERBAIKAN: Berikan data nama customer ke notifikasi
      notifyNewCustomer(newSessions, sessionCustomers)
      showSessionNotification(newSessions, sessionCustomers)
      markSessionsAsRead(newSessions)
    }
  }

  // Process server notifications for new orders
  if (serverNewOrders.length > 0) {
    if (!window.processedNotifications) {
      window.processedNotifications = new Set()
    }
    serverNewOrders.forEach(order => {
      // Only process if not already processed
      const orderKey = 'order_' + order.table_id + '_' + order.created_at
      if (!window.processedNotifications.has(orderKey)) {
        newOrders.push({
          tableId: order.table_id,
          customerName: order.customer_name,
          total: order.total_amount,
          itemsCount: order.items_count
        })
        window.processedNotifications.add(orderKey)
      }
    })

    // Play notification for new orders using TTS
    if (newOrders.length > 0) {
      console.log('Playing notification for new orders:', newOrders)
      // Konstruksi pesan TTS yang lebih detail
      const tableIds = newOrders.map(order => order.tableId)
      const totalItems = newOrders.reduce(
        (sum, order) => sum + (parseInt(order.itemsCount) || 0),
        0
      )
      const ttsMessage = `Perhatian, order baru diterima dari meja ${tableIds.join(
        ', '
      )}. Total ${totalItems} item telah dipesan.`
      speakNotification(ttsMessage, 'orderSuccessBell')
      // Show notification for new orders with enhanced data
      showEnhancedOrderNotification(newOrders)
      // Update server to mark these as read
      markOrdersAsRead(newOrders.map(order => order.tableId))
    }
  }

  const processedStatuses = [...newStatuses]

  if (statusTable.length === 0) {
    // PERBAIKAN: Konversi semua status ke tipe data yang konsisten (number/integer)
    statusTable = processedStatuses.map(status => {
      // Konversi string status ke number
      if (status === 'reserved') return 0
      if (status === 'ordered') return 1
      if (status === 'processing') return 2
      if (status === 'served') return 3
      if (status === 'completed') return 4
      if (status === 'cancelled') return 5
      // Jika null/undefined, pertahankan null
      if (status === null || status === undefined) return null
      // Dalam kasus lain, coba parse sebagai integer
      return parseInt(status, 10)
    })

    console.log('Initialized statusTable:', statusTable)

    // Initial setup of all tables
    statusTable.forEach((element, index) => {
      const tableId = index + 1
      const customerName = customerData[tableId]
      const orderTime = orderTimeData[tableId]
      const orderTotal = orderTotalData[tableId]

      // PERBAIKAN KRITIS: Ambil data promo untuk tabel ini
      const promoInfo = promoData[tableId]

      // PERBAIKAN: Deteksi apakah promo ini bundling/BOGO
      let isBundlingOrBogo = false
      if (promoInfo && promoInfo.code) {
        const promoCode = promoInfo.code.toUpperCase()
        isBundlingOrBogo =
          promoCode.includes('BUNDLING') ||
          promoCode.includes('BUNDLE') ||
          promoCode.includes('BOGO') ||
          (promoCode.includes('BUY') && promoCode.includes('GET'))

        // Jika bundling/BOGO, simpan data promo ke detailedOrderData
        if (isBundlingOrBogo) {
          // Pastikan detailedOrderData[tableId] ada
          detailedOrderData[tableId] = detailedOrderData[tableId] || {}
          // Simpan kode promo
          detailedOrderData[tableId].discount_code = promoInfo.code
          detailedOrderData[tableId].discount_amount = promoInfo.discount
          console.log(
            `Table ${tableId} has BUNDLING/BOGO promo: ${promoInfo.code}`
          )
        }
      }

      changeStatusState(tableId, element, customerName, orderTime, orderTotal)
    })
  } else {
    // Compare with previous state to find changes
    processedStatuses.forEach((element, index) => {
      const tableId = index + 1

      // PERBAIKAN: Konversi ke format konsisten untuk perbandingan
      let newStatus = element
      if (element === 'reserved') newStatus = 0
      else if (element === 'ordered') newStatus = 1
      else if (element === 'processing') newStatus = 2
      else if (element === 'served') newStatus = 3
      else if (element === 'completed') newStatus = 4
      else if (element === 'cancelled') newStatus = 5
      else if (element === null || element === undefined) newStatus = null
      else newStatus = parseInt(element, 10)

      console.log(
        `Table ${tableId}: Previous=${statusTable[index]}, New=${newStatus}`
      )

      if (statusTable[index] !== newStatus) {
        // Status berubah
        // Update status in our tracking
        statusTable[index] = newStatus

        // PERBAIKAN KRITIS: Ambil data promo untuk tabel ini
        const promoInfo = promoData[tableId]

        // PERBAIKAN: Deteksi apakah promo ini bundling/BOGO
        let isBundlingOrBogo = false
        if (promoInfo && promoInfo.code) {
          const promoCode = promoInfo.code.toUpperCase()
          isBundlingOrBogo =
            promoCode.includes('BUNDLING') ||
            promoCode.includes('BUNDLE') ||
            promoCode.includes('BOGO') ||
            (promoCode.includes('BUY') && promoCode.includes('GET'))

          // Jika bundling/BOGO, simpan data promo ke detailedOrderData
          if (isBundlingOrBogo) {
            // Pastikan detailedOrderData[tableId] ada
            detailedOrderData[tableId] = detailedOrderData[tableId] || {}
            // Simpan kode promo
            detailedOrderData[tableId].discount_code = promoInfo.code
            detailedOrderData[tableId].discount_amount = promoInfo.discount
            console.log(
              `Table ${tableId} has BUNDLING/BOGO promo: ${promoInfo.code}`
            )
          }
        }

        // Update UI with new status and data
        const customerName = customerData[tableId]
        const orderTime = orderTimeData[tableId]
        const orderTotal = orderTotalData[tableId]

        changeStatusState(
          tableId,
          newStatus,
          customerName,
          orderTime,
          orderTotal
        )
      } else if (
        newStatus === 0 ||
        newStatus === 1 ||
        newStatus === 2 ||
        newStatus === 3 ||
        newStatus === 4
      ) {
        // Status tidak berubah tapi aktif
        if (newStatus === 1) {
          orderRang = true
        }

        // Check if customer info changed
        const customerName = customerData[tableId]
        if (customerName && customerName !== customerNames[tableId]) {
          customerUpdates.push({
            tableId: tableId,
            oldName: customerNames[tableId] || '-',
            newName: customerName
          })
          // Update with new info but keep status the same
          changeStatusState(tableId, newStatus, customerName)
        }

        // PERBAIKAN KRITIS: Ambil data promo untuk tabel ini
        const promoInfo = promoData[tableId]

        // PERBAIKAN: Deteksi apakah promo ini bundling/BOGO
        let isBundlingOrBogo = false
        if (promoInfo && promoInfo.code) {
          const promoCode = promoInfo.code.toUpperCase()
          isBundlingOrBogo =
            promoCode.includes('BUNDLING') ||
            promoCode.includes('BUNDLE') ||
            promoCode.includes('BOGO') ||
            (promoCode.includes('BUY') && promoCode.includes('GET'))

          // Jika bundling/BOGO, simpan data promo ke detailedOrderData
          if (isBundlingOrBogo) {
            // Pastikan detailedOrderData[tableId] ada
            detailedOrderData[tableId] = detailedOrderData[tableId] || {}
            // Simpan kode promo
            detailedOrderData[tableId].discount_code = promoInfo.code
            detailedOrderData[tableId].discount_amount = promoInfo.discount
            console.log(
              `Table ${tableId} has BUNDLING/BOGO promo: ${promoInfo.code}`
            )
          }
        }

        // Update order time and total if available
        const orderTime = orderTimeData[tableId]
        const orderTotal = orderTotalData[tableId]

        if (orderTime && orderTime !== orderTimes[tableId]) {
          changeStatusState(tableId, newStatus, null, orderTime)
        }

        if (orderTotal && orderTotal !== orderTotals[tableId]) {
          changeStatusState(tableId, newStatus, null, null, orderTotal)
        }
      }
    })
  }

  // Handle notification sounds with TTS
  if (orderRang) {
    // Perubahan di sini: Cek apakah semua order yang aktif sudah dilihat
    const allOrdersViewed = statusTable.every((status, index) => {
      // Jika status adalah 1 (ordered), periksa apakah sudah dilihat
      if (status === 1 || status === '1') {
        return viewedOrders.has((index + 1).toString())
      }
      return true
    })

    // Jika ada order yang belum dilihat, gunakan TTS untuk mengingatkan
    if (!allOrdersViewed) {
      // Identifikasi meja dengan order yang belum dilihat
      const unviewedTables = statusTable
        .map((status, index) => {
          if (
            (status === 1 || status === '1') &&
            !viewedOrders.has((index + 1).toString())
          ) {
            return index + 1
          }
          return null
        })
        .filter(tableId => tableId !== null)

      if (unviewedTables.length > 0) {
        // Gunakan TTS untuk mengingatkan order yang belum dilihat
        const reminderMessage = `Pengingat, masih ada ${
          unviewedTables.length
        } order yang belum dilihat dari meja ${unviewedTables.join(', ')}.`

        // Batasi frekuensi pengingat agar tidak terlalu sering
        const now = Date.now()
        if (
          !window.lastOrderReminder ||
          now - window.lastOrderReminder > 30000
        ) {
          // 30 detik
          speakNotification(reminderMessage, 'orderReminder')
          window.lastOrderReminder = now
        }
      }
    }

    // Show notifications for new orders if not already processed from server
    if (newOrders.length > 0 && serverNewOrders.length === 0) {
      console.log(
        'Showing notifications for orders detected from status change'
      )
      showEnhancedOrderNotification(newOrders)
    }

    // Show notifications for customer updates
    if (customerUpdates.length > 0) {
      // Gunakan TTS untuk update pelanggan
      const updateMessage = `Informasi pelanggan diperbarui pada meja ${customerUpdates
        .map(update => update.tableId)
        .join(', ')}.`
      speakNotification(updateMessage, 'customerUpdate')
      showCustomerUpdateNotification(customerUpdates)
    }
  }

  // PERBAIKAN: Simpan status tabel ke localStorage
  saveTableStatusesToStorage()

  // Update the active orders count
  updateActiveOrdersCount()

  console.groupEnd()
}

function extractPromoInfo (orderData) {
  console.group('🔍 Extracting Promo Information')
  // Initialize default promo info
  const promoInfo = {
    exists: false,
    code: null,
    type: null,
    discount: 0,
    bundleBogoValue: 0 // Field khusus untuk nilai produk gratis bundling/BOGO
  }

  try {
    // Check if response has explicit promo object in different possible formats
    if (orderData.promo) {
      console.log('Found promo in orderData.promo:', orderData.promo)
      promoInfo.exists = true
      promoInfo.code = orderData.promo.code || null
      promoInfo.type = orderData.promo.type || null
      promoInfo.discount = parseFloat(orderData.promo.discount || 0)
    }
    // Check discount_code and discount_amount properties (from the log's structure)
    else if (orderData.discount_code || orderData.discount_amount) {
      console.log('Found promo in discount_code/discount_amount fields:', {
        code: orderData.discount_code,
        amount: orderData.discount_amount
      })
      promoInfo.exists = true
      promoInfo.code = orderData.discount_code || null
      promoInfo.discount = parseFloat(orderData.discount_amount || 0)

      // CRITICAL FIX: Detect bundling/BOGO from code and override type
      if (promoInfo.code) {
        const code = promoInfo.code.toUpperCase()
        if (code.includes('BUNDLING') || code.includes('BUNDLE')) {
          promoInfo.type = 'bundling'
          console.log('Detected BUNDLING promo from code, overriding type')
        } else if (
          code.includes('BOGO') ||
          (code.includes('BUY') && code.includes('GET'))
        ) {
          promoInfo.type = 'bogo'
          console.log('Detected BOGO promo from code, overriding type')
        } else if (
          code.includes('PERSENTASE') ||
          code.includes('PCT') ||
          code.includes('%')
        ) {
          promoInfo.type = 'percentage'
        } else if (code.includes('NOMINAL') || code.includes('DISC')) {
          promoInfo.type = 'nominal'
        } else {
          // Default to nominal if can't determine
          promoInfo.type = 'nominal'
        }
      }
    }

    // Log the extracted info with detected type
    console.log('Extracted promo information:', promoInfo)

    // For bundling/BOGO promos, we may need to calculate item values
    if (
      promoInfo.exists &&
      (promoInfo.type === 'bundling' || promoInfo.type === 'bogo')
    ) {
      console.log('Checking for bundling/BOGO free items')
      // Look for free items in the order
      const freeItems = (orderData.items || []).filter(
        item =>
          item.is_promo_item === 1 ||
          (item.price === 0 && item.subtotal === 0) ||
          (item.notes &&
            (item.notes.toLowerCase().includes('gratis') ||
              item.notes.toLowerCase().includes('free')))
      )

      // Calculate total value of free items
      if (freeItems.length > 0) {
        promoInfo.bundleBogoValue = freeItems.reduce((sum, item) => {
          const originalPrice = parseFloat(
            item.original_price || item.unit_price || 0
          )
          const quantity = parseInt(item.quantity || 1)
          return sum + originalPrice * quantity
        }, 0)
        console.log('Found free items with value:', promoInfo.bundleBogoValue)
      }
    }
  } catch (error) {
    console.error('Error extracting promo information:', error)
  }

  console.groupEnd()
  return promoInfo
}

function renderOrderDetails (orderItems, orderSummary = null) {
  console.group('🎨 Rendering Order Details')
  console.log('Order Items:', orderItems)
  console.log('Order Summary:', orderSummary)

  // Extract order data from summary for more complete information
  const orderData = orderSummary?.order || {}
  console.log('Order data for promo extraction:', orderData)

  // Status label mapping
  const statusLabels = {
    0: { text: 'Dipesan', class: 'bg-primary' },
    1: { text: 'Order Diproses', class: 'bg-success' },
    2: { text: 'Sedang Diproses', class: 'bg-info' },
    3: { text: 'Sudah Diantar', class: 'bg-success' },
    4: { text: 'Selesai', class: 'bg-secondary' },
    5: { text: 'Dibatalkan', class: 'bg-danger' }
  }

  // Get order status from summary if available
  const orderStatus = orderSummary?.status || 1 // Default to ORDERED if not available
  const statusInfo = statusLabels[orderStatus] || {
    text: 'Unknown',
    class: 'bg-light'
  }

  // Validasi struktur array orderItems
  if (!orderItems || !Array.isArray(orderItems) || orderItems.length === 0) {
    $('#detailProductBody').html(`
		<tr>
		  <td colspan="4" class="text-center">
			<div class="alert alert-info mb-0">
			  <i class="fa-solid fa-info-circle me-2"></i> Tidak ada item dalam order ini
			</div>
		  </td>
		</tr>
	  `)

    // Update status display with badge
    const statusHtml = `<span class="badge ${statusInfo.class} p-2">${statusInfo.text}</span>`
    $('#order-status-display').html(statusHtml)

    // Make sure summary is also updated even if there are no items
    if (orderSummary) {
      $('#detail-subtotal').text(formatCurrency(orderSummary.subtotal || 0))
      $('#detail-tax').text(formatCurrency(orderSummary.tax || 0))
      $('#detail-total').text(formatCurrency(orderSummary.total || 0))
    } else {
      $('#detail-subtotal').text(formatCurrency(0))
      $('#detail-tax').text(formatCurrency(0))
      $('#detail-total').text(formatCurrency(0))
    }

    console.groupEnd()
    return
  }

  // Validasi container item sebelum menggunakannya
  const detailProductBody = $('#detailProductBody')
  if (detailProductBody.length === 0) {
    console.error('Error: #detailProductBody container not found!')
    console.groupEnd()
    return
  }

  // Clear previous content
  detailProductBody.empty()

  // Maps for organizing items
  let packages = new Map()
  let packageItems = new Map()
  let regularItems = []

  // Log nama produk untuk debugging
  console.log('Processing ' + orderItems.length + ' order items:')
  orderItems.forEach((item, index) => {
    console.log(`Item ${index + 1}: ${item.product_name || 'Unnamed'}`)
  })

  // First pass: organize items
  orderItems.forEach(item => {
    // Normalisasi properti item
    const normalizedItem = {
      id: item.id || '',
      product_name: item.product_name || item.name || 'Unnamed Product',
      unit_price: parseFloat(item.unit_price || item.price || 0),
      price: parseFloat(item.unit_price || item.price || 0),
      quantity: parseInt(item.quantity || item.qty || 0),
      subtotal:
        parseFloat(item.subtotal || 0) ||
        parseFloat(item.unit_price || item.price || 0) *
          parseInt(item.quantity || item.qty || 0),
      notes: item.notes || '',
      parent_id: item.parent_id || null,
      is_package: item.is_package || Boolean(item.package_id),
      is_package_item: Boolean(item.parent_id),
      is_promo_item:
        item.is_promo_item === 1 ||
        item.is_promo_item === '1' ||
        (parseFloat(item.unit_price || item.price || 0) === 0 &&
          item.notes &&
          (item.notes.toLowerCase().includes('gratis') ||
            item.notes.toLowerCase().includes('promo')))
    }

    // Normalize item properties
    const itemId = normalizedItem.id
    const parentId = normalizedItem.parent_id
    const isPackageItem = normalizedItem.is_package_item
    const isPackage = normalizedItem.is_package

    // For debugging
    console.log(
      `Processing item: ${normalizedItem.product_name}, isPackage: ${isPackage}, isPackageItem: ${isPackageItem}, isPromoItem: ${normalizedItem.is_promo_item}`
    )

    if (isPackageItem) {
      // Store package items by parent ID
      if (!packageItems.has(parentId)) {
        packageItems.set(parentId, [])
      }
      packageItems.get(parentId).push(normalizedItem)
    } else if (isPackage) {
      // Store package header
      packages.set(itemId, normalizedItem)
    } else {
      // Regular item
      regularItems.push(normalizedItem)
    }
  })

  // Update status display with badge
  const statusHtml = `<span class="badge ${statusInfo.class} p-2">${statusInfo.text}</span>`
  $('#order-status-display').html(statusHtml)

  // Variables for our own calculation
  let calculatedSubtotal = 0
  let freeItemsValue = 0 // Track the value of free promo items separately

  // Verifikasi bahwa container ada dan memuat item
  console.log('Container found:', detailProductBody.length > 0)
  console.log('Regular items to render:', regularItems.length)
  console.log('Packages to render:', packages.size)

  // Process regular items
  regularItems.forEach(item => {
    const price = parseFloat(item.unit_price || item.price || 0)
    const quantity = parseInt(item.quantity || 0)
    const subtotal = price * quantity
    const notes = item.notes || ''
    const isPromoItem =
      item.is_promo_item ||
      (price === 0 &&
        notes &&
        (notes.toLowerCase().includes('gratis') ||
          notes.toLowerCase().includes('promo')))

    // If this is a free promo item, track its value but DON'T add to subtotal
    if (isPromoItem && price === 0) {
      // Try to get original price from notes or item data
      const originalPrice = parseFloat(item.original_price || 0)
      if (originalPrice > 0) {
        freeItemsValue += originalPrice * quantity
      }
    } else {
      // Only add non-promo items to the subtotal
      calculatedSubtotal += subtotal
    }

    const itemHTML = `
		<tr class="${isPromoItem ? 'promo-item' : 'regular-item'}">
		  <td>
			${item.product_name}
			${
        notes
          ? `
			  <div class="item-notes text-muted small mt-1">
				<i class="fa-solid fa-comment-dots me-1"></i> ${notes}
			  </div>
			`
          : ''
      }
			${isPromoItem ? '<span class="badge bg-success ms-2">Promo</span>' : ''}
		  </td>
		  <td class="text-center">${quantity}x</td>
		  <td class="text-end">${
        isPromoItem && price === 0 ? 'Gratis' : formatCurrency(price)
      }</td>
		  <td class="text-end">${
        isPromoItem && price === 0 ? 'Gratis' : formatCurrency(subtotal)
      }</td>
		</tr>
	  `
    detailProductBody.append(itemHTML)
  })

  // Process packages
  packages.forEach((packageHeader, packageId) => {
    // Get base package information
    const packageName = packageHeader.product_name
    const packagePrice = parseFloat(
      packageHeader.unit_price || packageHeader.price || 0
    )
    const packageQuantity = parseInt(packageHeader.quantity || 0)
    const packageBaseSubtotal = packagePrice * packageQuantity
    const packageNotes = packageHeader.notes || ''
    const isPromoPackage = packageHeader.is_promo_item || packagePrice === 0

    // Track the full package total (base + items)
    let packageFullTotal = packageBaseSubtotal

    // Add to subtotal if not a promo package
    if (!isPromoPackage) {
      calculatedSubtotal += packageBaseSubtotal
    } else if (packagePrice === 0) {
      // Track free package value
      const originalPrice = parseFloat(packageHeader.original_price || 0)
      if (originalPrice > 0) {
        freeItemsValue += originalPrice * packageQuantity
      }
    }

    // Add package header to the display
    const packageHTML = `
		<tr class="package-header ${isPromoPackage ? 'promo-item' : ''}">
		  <td>
			${packageName}
			${
        packageNotes
          ? `
			  <div class="item-notes text-muted small mt-1">
				<i class="fa-solid fa-comment-dots me-1"></i> ${packageNotes}
			  </div>
			`
          : ''
      }
			${isPromoPackage ? '<span class="badge bg-success ms-2">Promo</span>' : ''}
		  </td>
		  <td class="text-center">${packageQuantity}x</td>
		  <td class="text-end">${
        isPromoPackage && packagePrice === 0
          ? 'Gratis'
          : formatCurrency(packagePrice)
      }</td>
		  <td class="text-end">${
        isPromoPackage && packagePrice === 0
          ? 'Gratis'
          : formatCurrency(packageBaseSubtotal)
      }</td>
		</tr>
	  `
    $('#detailProductBody').append(packageHTML)

    // Process package items if any
    const items = packageItems.get(packageId) || []
    items.forEach(item => {
      const itemPrice = parseFloat(item.unit_price || item.price || 0)
      const itemQuantity = parseInt(item.qty || item.quantity || 0)
      const itemSubtotal = itemPrice * itemQuantity
      const itemNotes = item.notes || ''
      const isPromoItem = item.is_promo_item || itemPrice === 0

      // Add to full package total if not a promo item
      if (!isPromoItem) {
        packageFullTotal += itemSubtotal
        // Add package item subtotal to total calculation only if parent package isn't free
        if (!isPromoPackage) {
          calculatedSubtotal += itemSubtotal
        }
      } else if (itemPrice === 0) {
        // Track free item value
        const originalPrice = parseFloat(item.original_price || 0)
        if (originalPrice > 0) {
          freeItemsValue += originalPrice * itemQuantity
        }
      }

      // Add package item row
      const itemHTML = `
		  <tr class="package-item ${isPromoItem ? 'promo-item' : ''}">
			<td class="ps-4">
			  <i class="fa-solid fa-angle-right me-2 text-muted"></i> ${item.product_name}
			  ${
          itemNotes
            ? `
				<div class="item-notes text-muted small mt-1 ms-4">
				  <i class="fa-solid fa-comment-dots me-1"></i> ${itemNotes}
				</div>
			  `
            : ''
        }
			  ${isPromoItem ? '<span class="badge bg-success ms-2">Promo</span>' : ''}
			</td>
			<td class="text-center">${itemQuantity}x</td>
			<td class="text-end">${
        isPromoItem && itemPrice === 0 ? 'Gratis' : formatCurrency(itemPrice)
      }</td>
			<td class="text-end">${
        isPromoItem && itemPrice === 0 ? 'Gratis' : formatCurrency(itemSubtotal)
      }</td>
		  </tr>
		`
      $('#detailProductBody').append(itemHTML)
    })
  })

  // Extract and process promo information
  let promoInfo
  if (orderSummary?.promo) {
    // First try from orderSummary.promo
    promoInfo = {
      exists: true,
      code: orderSummary.promo.code,
      type: orderSummary.promo.type,
      discount: parseFloat(orderSummary.promo.discount || 0),
      bundleBogoValue: orderSummary.bundleBogoValue || freeItemsValue || 0
    }
  } else {
    // If no promo in orderSummary, extract from the order data
    promoInfo = extractPromoInfo(orderData)
    // Use calculated free items value if not already set
    if (promoInfo.bundleBogoValue === 0 && freeItemsValue > 0) {
      promoInfo.bundleBogoValue = freeItemsValue
    }
  }

  console.log('Final extracted promo information:', promoInfo)
  console.log('Calculated free items value:', freeItemsValue)

  // PERBAIKAN KRITIS: Detect and override bundling/BOGO from promo code
  if (promoInfo && promoInfo.exists && promoInfo.code) {
    const upperCode = promoInfo.code.toUpperCase()
    if (upperCode.includes('BUNDLING') || upperCode.includes('BUNDLE')) {
      if (promoInfo.type !== 'bundling') {
        console.log(
          `Override promo type from ${promoInfo.type} to bundling based on code`
        )
        promoInfo.type = 'bundling'
      }
    } else if (
      upperCode.includes('BOGO') ||
      (upperCode.includes('BUY') && upperCode.includes('GET'))
    ) {
      if (promoInfo.type !== 'bogo') {
        console.log(
          `Override promo type from ${promoInfo.type} to bogo based on code`
        )
        promoInfo.type = 'bogo'
      }
    }
  }

  // Calculate final values
  let finalSubtotal = calculatedSubtotal
  let finalDiscount = 0

  // PERBAIKAN UTAMA: Bedakan perhitungan diskon berdasarkan jenis promo
  if (promoInfo && promoInfo.exists) {
    // PERBAIKAN KRITIS: For bundling/BOGO, don't apply discount to subtotal
    if (promoInfo.type === 'bundling' || promoInfo.type === 'bogo') {
      // For bundling/BOGO, don't use discount amount in calculations
      // Just store it for informational purposes
      finalDiscount = 0
      console.log(
        'BUNDLING/BOGO promo detected - NOT applying discount to subtotal'
      )
    } else {
      // For percentage/nominal, use discount to reduce subtotal
      finalDiscount = promoInfo.discount
      console.log(
        'Percentage/Nominal promo - applying discount of',
        finalDiscount
      )
    }
  }

  // Calculate tax on post-discount amount
  const discountedSubtotal = Math.max(0, finalSubtotal - finalDiscount)
  let finalTax = discountedSubtotal * 0.1 // 10% tax AFTER discount
  let finalTotal = discountedSubtotal + finalTax

  // PERBAIKAN KRITIS: Override with server values if available
  if (orderSummary) {
    if (
      typeof orderSummary.subtotal === 'number' &&
      orderSummary.subtotal > 0
    ) {
      finalSubtotal = orderSummary.subtotal
    }

    if (promoInfo && promoInfo.exists) {
      if (promoInfo.type === 'bundling' || promoInfo.type === 'bogo') {
        // CRUCIAL FIX: For bundling/BOGO, ZERO out the discount
        finalDiscount = 0

        // Override server tax calculation if it was based on discounted amount
        if (typeof orderSummary.tax === 'number') {
          const expectedTax = finalSubtotal * 0.1 // 10% of FULL subtotal
          const serverTax = orderSummary.tax

          // If server tax looks wrong (calculated after discount), use our calculation
          if (Math.abs(serverTax - expectedTax) > 1) {
            // Allow for rounding errors
            console.log(
              'Server tax appears to be based on discounted amount. Overriding with correct tax.'
            )
            finalTax = expectedTax
          } else {
            finalTax = serverTax
          }
        } else {
          finalTax = finalSubtotal * 0.1 // 10% of FULL subtotal
        }

        // Override server total if it was based on discounted amount
        if (typeof orderSummary.total === 'number') {
          const expectedTotal = finalSubtotal + finalTax
          const serverTotal = orderSummary.total

          // If server total looks wrong, use our calculation
          if (Math.abs(serverTotal - expectedTotal) > 1) {
            // Allow for rounding errors
            console.log(
              'Server total appears to be based on discounted amount. Overriding with correct total.'
            )
            finalTotal = expectedTotal
          } else {
            finalTotal = serverTotal
          }
        } else {
          finalTotal = finalSubtotal + finalTax
        }
      } else {
        // For percentage/nominal, use server values
        if (typeof orderSummary.discount === 'number') {
          finalDiscount = orderSummary.discount
        }

        const discountedSubtotal = Math.max(0, finalSubtotal - finalDiscount)

        if (typeof orderSummary.tax === 'number') {
          finalTax = orderSummary.tax
        } else {
          finalTax = discountedSubtotal * 0.1
        }

        if (typeof orderSummary.total === 'number') {
          finalTotal = orderSummary.total
        } else {
          finalTotal = discountedSubtotal + finalTax
        }
      }
    } else {
      // No promo case
      if (typeof orderSummary.tax === 'number') {
        finalTax = orderSummary.tax
      } else {
        finalTax = finalSubtotal * 0.1
      }

      if (typeof orderSummary.total === 'number') {
        finalTotal = orderSummary.total
      } else {
        finalTotal = finalSubtotal + finalTax
      }
    }
  }

  // Log final calculation values for debugging
  console.log('Final calculation values:', {
    promoType: promoInfo?.type,
    subtotal: finalSubtotal,
    discount: finalDiscount, // Always 0 for bundling/BOGO
    discountedSubtotal: Math.max(0, finalSubtotal - finalDiscount),
    tax: finalTax,
    total: finalTotal,
    bundleBogoValue: promoInfo?.bundleBogoValue || freeItemsValue
  })

  // Update summary display
  $('#detail-subtotal').text(formatCurrency(finalSubtotal))

  // Show promo info if present
  if (promoInfo && promoInfo.exists) {
    // Add promo info section
    const promoDisplayText = promoInfo.code ? `(${promoInfo.code})` : ''

    // PERBAIKAN KRITIS: Different UI handling for bundling/BOGO
    if (promoInfo.type === 'bundling' || promoInfo.type === 'bogo') {
      // For bundling/BOGO: Show product value row but NOT as discount
      const bundleValueRow = $('.bundle-value-row')
      const bundleBogoValue = promoInfo.bundleBogoValue || promoInfo.discount

      if (bundleValueRow.length) {
        // Update existing row
        $('#detail-bundle-value').text(formatCurrency(bundleBogoValue))
        bundleValueRow.show()
      } else {
        // Create new row after subtotal
        const subtotalRow = $('#detail-subtotal').closest('tr')
        subtotalRow.after(`
			<tr class="bundle-value-row">
			  <td colspan="3" class="text-end fw-medium text-info">Nilai Produk Gratis ${promoDisplayText}:</td>
			  <td class="text-end text-info">${formatCurrency(bundleBogoValue)}</td>
			</tr>
		  `)
      }

      // Hide regular discount row
      $('.discount-row').hide()
    } else {
      // For percentage/nominal: Show as regular discount
      const discountRow = $('.discount-row')
      if (discountRow.length) {
        // Update existing row
        $('#detail-discount').text(`-${formatCurrency(finalDiscount)}`)
        discountRow.show()
      } else {
        // Create new row after subtotal
        const subtotalRow = $('#detail-subtotal').closest('tr')
        subtotalRow.after(`
			<tr class="discount-row">
			  <td colspan="3" class="text-end fw-medium text-danger">Diskon ${promoDisplayText}:</td>
			  <td class="text-end text-danger">-${formatCurrency(finalDiscount)}</td>
			</tr>
		  `)
      }

      // Hide bundle value row
      $('.bundle-value-row').hide()
    }

    // Add promo info box if not already present
    const promoSection = $('.promo-section')
    if (promoSection.length === 0) {
      // Create promo info box
      const promoTypeText = getPromoTypeDisplayName(promoInfo.type)
      const promoTypeClass = getPromoTypeClass(promoInfo.type)

      // Customize info box based on promo type
      let infoValue
      if (promoInfo.type === 'bundling' || promoInfo.type === 'bogo') {
        const bundleBogoValue = promoInfo.bundleBogoValue || promoInfo.discount
        infoValue = `<span class="badge bg-info">Nilai Produk Gratis: ${formatCurrency(
          bundleBogoValue
        )}</span>`
      } else {
        infoValue = `<span class="badge bg-danger">Hemat ${formatCurrency(
          finalDiscount
        )}</span>`
      }

      const promoBoxHTML = `
		  <div class="promo-section mt-3">
			<div class="alert alert-light border">
			  <div class="d-flex">
				<div class="me-3">
				  <i class="fa fa-tags fs-4 text-${promoTypeClass}"></i>
				</div>
				<div>
				  <div class="d-flex justify-content-between align-items-start">
					<div>
					  <strong>Kode Promo: ${promoInfo.code}</strong>
					  <span class="badge bg-${promoTypeClass} ms-2">${promoTypeText}</span>
					</div>
					<div>
					  ${infoValue}
					</div>
				  </div>
				  ${
            promoInfo.type === 'bundling' || promoInfo.type === 'bogo'
              ? '<small class="d-block text-muted mt-1">*Nilai produk gratis ditampilkan sebagai informasi dan tidak mengurangi total belanja</small>'
              : ''
          }
				</div>
			  </div>
			</div>
		  </div>
		`

      // Find a good place to insert the promo box
      const summarySection = $('.cart-summary, .order-summary')
      if (summarySection.length) {
        summarySection.append(promoBoxHTML)
      } else {
        // Add it before the final details
        $('#detail-total').closest('table').before(promoBoxHTML)
      }
    }
  } else {
    // Hide all promo-related rows if no promo
    $('.discount-row').hide()
    $('.bundle-value-row').hide()
    $('.promo-section').hide()
  }

  // Update tax and total
  $('#detail-tax').text(formatCurrency(finalTax))
  $('#detail-total').text(formatCurrency(finalTotal))

  console.groupEnd()
}

// Bantuan fungsi untuk mengidentifikasi tipe promo berdasarkan kode
function detectPromoTypeFromCode (code) {
  if (!code) return 'nominal' // Default

  const upperCode = code.toUpperCase()

  if (
    upperCode.includes('PERSENTASE') ||
    upperCode.includes('PCT') ||
    upperCode.includes('%')
  ) {
    return 'percentage'
  } else if (upperCode.includes('NOMINAL') || upperCode.includes('DISC')) {
    return 'nominal'
  } else if (upperCode.includes('BUNDLING') || upperCode.includes('BUNDLE')) {
    return 'bundling'
  } else if (
    upperCode.includes('BOGO') ||
    (upperCode.includes('BUY') && upperCode.includes('GET'))
  ) {
    return 'bogo'
  }

  return 'nominal' // Default
}

function showEnhancedOrderNotification (orders) {
  if (!orders.length) return

  // Group orders by table for more compact notifications
  const tableGroups = {}
  orders.forEach(order => {
    if (!tableGroups[order.tableId]) {
      tableGroups[order.tableId] = order
      // Update state customerNames with name from order
      if (order.customerName) {
        customerNames[order.tableId] = order.customerName
        // Update UI directly
        $('#tableName-' + order.tableId).text(order.customerName)
      }
    }
  })

  const tableNumbers = Object.keys(tableGroups).map(Number)

  // Using TTS to read notification
  const totalItems = orders.reduce(
    (sum, order) => sum + parseInt(order.itemsCount || 0),
    0
  )

  // Build TTS message with customer names
  let ttsMessage = `Ada order baru dari meja ${tableNumbers.join(', ')}. `

  // Add customer details
  tableNumbers.forEach((tableId, index) => {
    const order = tableGroups[tableId]
    const customerName =
      order.customerName || customerNames[tableId] || 'tanpa nama'
    ttsMessage += `Meja ${tableId}: ${customerName}`
    if (index < tableNumbers.length - 1) {
      ttsMessage += ', '
    }
  })

  ttsMessage += `. Total ${totalItems} item telah dipesan. Silakan proses order segera.`
  speakNotification(ttsMessage, 'orderSuccessBell')

  // Set flag for loop notification until viewed
  window.activeBellNotification = {
    type: 'orderSuccessBell',
    tables: tableNumbers,
    timestamp: new Date().getTime()
  }

  // Start loop notification
  startLoopNotification()

  // SOLUSI SEDERHANA: Hanya tampilkan tabel detail pesanan per meja
  let notificationHTML = `
	<div class="alert alert-info">
	  <h5 class="mb-3">Detail Pesanan per Meja:</h5>
	  <div class="table-responsive">
		<table class="table table-sm">
		  <thead>
			<tr>
			  <th>Meja</th>
			  <th>Pelanggan</th>
			  <th>Items</th>
			</tr>
		  </thead>
		  <tbody>
			${Object.values(tableGroups)
        .map(function (order) {
          return (
            '<tr>' +
            '<td>' +
            order.tableId +
            '</td>' +
            '<td>' +
            (order.customerName || 'Unknown') +
            '</td>' +
            '<td>' +
            (order.itemsCount || '0') +
            ' item' +
            '</td>' +
            '</tr>'
          )
        })
        .join('')}
		  </tbody>
		</table>
	  </div>
	  <div class="mt-3 text-center">
		<p class="text-muted">Klik "Lihat Detail" untuk melihat detail pesanan lengkap</p>
	  </div>
	</div>`

  // Show notification using SweetAlert
  Swal.fire({
    title: 'Order Baru!',
    html: notificationHTML,
    icon: 'success',
    confirmButtonText: 'Lihat Detail',
    showCancelButton: true,
    cancelButtonText: 'Tutup',
    customClass: {
      container: 'order-notification-modal',
      popup: 'animated bounceIn'
    },
    width: '600px' // Wider modal for better readability
  }).then(function (result) {
    if (result.isConfirmed && tableNumbers.length > 0) {
      // Show detail for the first table
      const tableId = tableNumbers[0]
      fetchOrderDetails(tableId)
    }
  })

  // Also show a toast notification that will disappear automatically
  showToastNotification(
    'Order Baru',
    'Ada pesanan baru dari meja: ' + tableNumbers.join(', '),
    'success'
  )
}

async function fetchOrderDetails (tableId) {
  try {
    // Mark order as viewed
    viewedOrders.add(tableId.toString())

    // Check if we need to stop bell notifications
    if (window.activeBellNotification) {
      const { tables } = window.activeBellNotification
      if (
        tables.includes(parseInt(tableId)) ||
        tables.includes(tableId.toString())
      ) {
        const allTablesViewed = tables.every(t =>
          viewedOrders.has(t.toString())
        )
        if (allTablesViewed) {
          stopLoopNotification()
        }
      }
    }

    // Clear TTS queue if available
    if (typeof stopAllNotificationSounds === 'function') {
      stopAllNotificationSounds()
    }

    // TTS notification try
    if (typeof speakNotification === 'function') {
      setTimeout(() => {
        speakNotification(
          `Menampilkan detail order dari meja ${tableId}.`,
          'viewOrder'
        )
      }, 500)
    }

    console.group('🔍 Fetch Order Details')
    console.log('Table ID:', tableId)

    // Get URL parameters
    const currentUrl = new URL(window.location.href)
    const baseUrl = `${currentUrl.protocol}//${currentUrl.host}`
    const brand =
      $('#brand').val() ||
      currentUrl.searchParams.get('brand') ||
      $('meta[name="brand"]').attr('content') ||
      ''
    const outletId =
      currentUrl.pathname.split('/').filter(Boolean).pop() ||
      $('meta[name="outlet-id"]').attr('content') ||
      ''

    console.log('Request Parameters:', {
      tableId,
      outletId,
      brand,
      currentUrl: currentUrl.href
    })

    // Validate parameters
    if (!tableId || !outletId || !brand) {
      throw new Error(
        `Missing required parameters: tableId, outletId, or brand`
      )
    }

    // URL for request
    const url = new URL(`${baseUrl}/order/getData`, baseUrl)
    url.searchParams.set('action', 'getOrderDetail')
    url.searchParams.set('tableId', tableId)
    url.searchParams.set('outletId', outletId)
    url.searchParams.set('brand', brand)

    console.log('Order Details Request URL:', url.toString())

    // Show loading dialog
    Swal.fire({
      title: 'Memuat Detail Order',
      html: `<div class="text-center">
		  <div class="spinner-border text-primary" role="status">
			<span class="visually-hidden">Loading...</span>
		  </div>
		  <p class="mt-3">Mengambil data order dari meja ${tableId}...</p>
		</div>`,
      allowOutsideClick: false,
      showConfirmButton: false,
      customClass: {
        popup: 'swal-wide'
      }
    })

    // Save tableId for reference
    $('#detailOrder').data('tableId', tableId)

    // Fetch data with timeout
    const controller = new AbortController()
    const timeout = setTimeout(() => controller.abort(), 10000)

    try {
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        },
        signal: controller.signal
      })

      clearTimeout(timeout)

      // Check response format
      const contentType = response.headers.get('content-type')
      if (!contentType || !contentType.includes('application/json')) {
        const responseText = await response.text()
        console.error('Non-JSON response:', responseText.substring(0, 500))

        Swal.fire({
          title: 'Error Format Data',
          html: `<div class="alert alert-danger">
			  <i class="fa-solid fa-circle-exclamation me-2"></i> Server mengembalikan format yang tidak sesuai
			  <br><small>Coba reload halaman dan coba lagi.</small>
			</div>`,
          icon: 'error',
          confirmButtonText: 'OK'
        })

        throw new Error('Server returned non-JSON response')
      }

      // Parse JSON
      const data = await response.json()
      console.log('Order Details Response:', data)

      // Process response data
      const responseData = data.data || data

      if (!responseData.success) {
        Swal.fire({
          title: 'Tidak Ada Order',
          html: `<div class="alert alert-warning">
			  <i class="fa-solid fa-triangle-exclamation me-2"></i> ${
          responseData.message || 'Tidak ada order aktif untuk meja ini'
        }
			</div>`,
          icon: 'warning',
          confirmButtonText: 'OK'
        })

        console.warn('No active order found')
        return
      }

      // Extract order data
      const orderData = responseData.order || {}

      // Log the complete order data for promo debugging
      console.log('Complete Order Data for promo debugging:', orderData)

      // CRITICAL FIX: Check for and correct promo type for bundling/BOGO codes
      const isBundlingCode =
        orderData.discount_code &&
        orderData.discount_code.toUpperCase().includes('BUNDLING')
      const isBogoCode =
        orderData.discount_code &&
        (orderData.discount_code.toUpperCase().includes('BOGO') ||
          (orderData.discount_code.toUpperCase().includes('BUY') &&
            orderData.discount_code.toUpperCase().includes('GET')))

      // Check for promo information
      const hasPromo = !!(
        orderData.discount_code ||
        orderData.discount_amount ||
        (responseData.summary && responseData.summary.promo)
      )

      console.log('Has promo?', hasPromo, {
        discount_code: orderData.discount_code,
        discount_amount: orderData.discount_amount,
        summary_promo: responseData.summary?.promo,
        isBundlingCode: isBundlingCode,
        isBogoCode: isBogoCode
      })

      // Extract and normalize order details
      let orderDetails = []

      if (Array.isArray(responseData.orderDetails)) {
        orderDetails = responseData.orderDetails
      } else if (Array.isArray(responseData.items)) {
        orderDetails = responseData.items
      } else if (orderData && Array.isArray(orderData.items)) {
        orderDetails = orderData.items
      }

      // Map to consistent format
      const normalizedOrderDetails = orderDetails.map(item => {
        return {
          id: item.id || item.item_id || '',
          product_id: item.product_id || '',
          product_name: item.product_name || item.name || 'Unnamed Product',
          quantity: parseInt(item.quantity || item.qty || 0),
          unit_price: parseFloat(item.unit_price || item.price || 0),
          price: parseFloat(item.unit_price || item.price || 0),
          subtotal: parseFloat(
            item.subtotal ||
              parseFloat(item.unit_price || item.price || 0) *
                parseInt(item.quantity || item.qty || 0)
          ),
          notes: item.notes || '',
          parent_id: item.parent_id || null,
          package_id: item.package_id || null,
          is_package:
            item.is_package ||
            (item.package_id !== null && item.package_id !== undefined),
          is_package_item: item.parent_id ? true : false,
          is_promo_item:
            item.is_promo_item === 1 ||
            item.is_promo_item === '1' ||
            (parseFloat(item.unit_price || item.price || 0) === 0 &&
              (item.notes || '').toLowerCase().includes('promo'))
        }
      })

      console.log('Normalized Order Details:', normalizedOrderDetails)

      // Extract promo information
      let promoInfo = null
      let discountAmount = 0

      // First, try to find promo in the order data
      if (orderData.discount_code && orderData.discount_amount) {
        console.log('Found promo info in order data:', {
          code: orderData.discount_code,
          amount: orderData.discount_amount
        })

        let promoType = 'nominal' // Default type

        // CRITICAL FIX: Detect and override bundling/BOGO type based on code
        if (isBundlingCode) {
          promoType = 'bundling'
          console.log('Detected bundling promo from code name')
        } else if (isBogoCode) {
          promoType = 'bogo'
          console.log('Detected BOGO promo from code name')
        } else {
          promoType = detectPromoTypeFromCode(orderData.discount_code)
        }

        promoInfo = {
          code: orderData.discount_code,
          type: promoType,
          discount: parseFloat(orderData.discount_amount || 0)
        }

        discountAmount = parseFloat(orderData.discount_amount || 0)
      }

      // Enhanced summary with promo information
      const orderSummary = responseData.summary || {
        subtotal: null,
        tax: null,
        total: null,
        status: orderData.status || 1 // Default to ORDERED if not provided
      }

      // CRITICAL FIX: Force correct promo type for bundling/BOGO
      if (promoInfo && (isBundlingCode || isBogoCode)) {
        const promoType = isBundlingCode ? 'bundling' : 'bogo'

        // Override promo type in orderSummary
        if (!orderSummary.promo) {
          orderSummary.promo = {
            code: promoInfo.code,
            type: promoType, // Force correct type
            discount: promoInfo.discount
          }
        } else {
          orderSummary.promo.type = promoType // Force correct type
        }

        // For bundling/BOGO, correct the tax calculation (NO DISCOUNT applied)
        const subtotal = parseFloat(orderSummary.subtotal || 0)

        // Do NOT subtract discount for bundling/BOGO
        const tax = subtotal * 0.1 // 10% tax on FULL subtotal
        const total = subtotal + tax

        // Update the summary with correct values
        orderSummary.tax = tax
        orderSummary.total = total

        console.log('Corrected bundling/BOGO summary:', {
          subtotal: subtotal,
          discount: discountAmount, // For information only
          tax: tax,
          total: total,
          promoType: promoType
        })
      } else if (promoInfo) {
        // Add promo information to orderSummary
        orderSummary.promo = promoInfo

        // Normal discount calculation for percentage/nominal
        const subtotal = parseFloat(orderSummary.subtotal || 0)
        const discountedSubtotal = Math.max(0, subtotal - discountAmount)
        const tax = discountedSubtotal * 0.1 // 10% tax on discounted subtotal
        const total = discountedSubtotal + tax

        // Update the summary with values
        orderSummary.tax = tax
        orderSummary.total = total
        orderSummary.discount = discountAmount
      }

      // Pass order data to summary for complete information access
      orderSummary.order = orderData

      console.log('Data for display:', {
        orderDetails: normalizedOrderDetails,
        orderSummary: orderSummary
      })

      // Save order data in global variable for easier access
      detailedOrderData[tableId] = orderData

      // Pre-render items HTML for insertion in SweetAlert
      let itemsHtml = ''

      // If no items, show empty message
      if (!normalizedOrderDetails || normalizedOrderDetails.length === 0) {
        itemsHtml = `
			<tr>
			  <td colspan="4" class="text-center">
				<div class="alert alert-info mb-0">
				  <i class="fa-solid fa-info-circle me-2"></i> Tidak ada item dalam order ini
				</div>
			  </td>
			</tr>
		  `
      } else {
        // Process regular items
        for (const item of normalizedOrderDetails) {
          const price = parseFloat(item.unit_price || item.price || 0)
          const quantity = parseInt(item.quantity || 0)
          const subtotal = price * quantity
          const notes = item.notes || ''

          // Determine if this is a free/promo item
          const isPromoItem = item.is_promo_item || price === 0

          itemsHtml += `
			  <tr class="${isPromoItem ? 'promo-item' : 'regular-item'}">
				<td>
				  ${item.product_name}
				  ${
            notes
              ? `
					<div class="item-notes text-muted small mt-1">
					  <i class="fa-solid fa-comment-dots me-1"></i> ${notes}
					</div>
				  `
              : ''
          }
				  ${isPromoItem ? '<span class="badge bg-success ms-2">Promo</span>' : ''}
				</td>
				<td class="text-center">${quantity}x</td>
				<td class="text-end">${isPromoItem ? 'Gratis' : formatCurrency(price)}</td>
				<td class="text-end">${isPromoItem ? 'Gratis' : formatCurrency(subtotal)}</td>
			  </tr>
			`
        }
      }

      // Render order details HTML
      let detailHtml = `
		  <div class="container-fluid px-0">
			<!-- Customer Info -->
			<div class="bg-light p-3 rounded mb-3">
			  <div class="row">
				<div class="col-6">
				  <p class="mb-1"><strong>Pelanggan:</strong> ${orderData.name || '-'}</p>
				  <p class="mb-0"><strong>Waktu Order:</strong> ${
            orderData.created_at ? formatDateTime(orderData.created_at) : '-'
          } </p>
				</div>
				<div class="col-6 text-end">
				  <p class="mb-1"><strong>Status:</strong> <span class="badge ${getStatusBadgeClass(
            orderData.status
          )}">
					${getStatusLabel(orderData.status)}
				  </span> </p>
				  <p class="mb-0"><strong>Order ID:</strong> ${orderData.id || '-'}</p>
				</div>
			  </div>
			</div>
  
			<!-- Order Items Table -->
			<h6 class="mb-2">Daftar Item</h6>
			<div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
			  <table class="table table-striped table-sm">
				<thead>
				  <tr>
					<th width="50%">Item</th>
					<th width="15%" class="text-center">Qty</th>
					<th width="15%" class="text-end">Harga</th>
					<th width="20%" class="text-end">Subtotal</th>
				  </tr>
				</thead>
				<tbody id="detailProductBody">
				  ${itemsHtml}
				</tbody>
			  </table>
			</div>
  
			<!-- Order Summary -->
			<div class="bg-light p-3 rounded mt-3">
			  <div class="d-flex justify-content-between mb-2">
				<span>Subtotal:</span>
				<span id="detail-subtotal">${formatCurrency(orderSummary.subtotal || 0)}</span>
			  </div>
		`

      // CRITICAL FIX: Different handling for bundling/BOGO vs regular discounts
      if (promoInfo && (isBundlingCode || isBogoCode)) {
        // For bundling/BOGO: Show product value row but NOT as discount
        const bundleBogoValue = discountAmount // Value of free products

        detailHtml += `
			  <!-- Bundle/BOGO Gratis Value Row -->
			  <div class="d-flex justify-content-between mb-2 bundle-value-row">
				<span>Nilai Produk Gratis (${promoInfo.code}):</span>
				<span id="detail-bundle-value" class="text-info">${formatCurrency(
          bundleBogoValue
        )}</span>
			  </div>
		  `

        // Hide discount row for bundling/BOGO
        detailHtml += `
			  <!-- Hide discount row for bundling/BOGO -->
			  <div class="d-flex justify-content-between mb-2 discount-row" style="display:none;">
				<span>Diskon:</span>
				<span id="detail-discount" class="text-danger">-${formatCurrency(0)}</span>
			  </div>
		  `
      } else if (promoInfo) {
        // For percentage/nominal: Show as regular discount
        detailHtml += `
			  <!-- Regular Discount Row -->
			  <div class="d-flex justify-content-between mb-2 discount-row">
				<span>Diskon ${promoInfo.code ? `(${promoInfo.code})` : ''}:</span>
				<span id="detail-discount" class="text-danger">-${formatCurrency(
          discountAmount
        )}</span>
			  </div>
			  
			  <!-- Hide bundle value row for percentage/nominal -->
			  <div class="d-flex justify-content-between mb-2 bundle-value-row" style="display:none;">
				<span>Nilai Produk Gratis:</span>
				<span id="detail-bundle-value" class="text-info">${formatCurrency(0)}</span>
			  </div>
		  `
      } else {
        // No promo case - hide both rows
        detailHtml += `
			  <!-- No promo - hide both rows -->
			  <div class="d-flex justify-content-between mb-2 discount-row" style="display:none;">
				<span>Diskon:</span>
				<span id="detail-discount" class="text-danger">-${formatCurrency(0)}</span>
			  </div>
			  
			  <div class="d-flex justify-content-between mb-2 bundle-value-row" style="display:none;">
				<span>Nilai Produk Gratis:</span>
				<span id="detail-bundle-value" class="text-info">${formatCurrency(0)}</span>
			  </div>
		  `
      }

      // Continue with the rest of the summary
      detailHtml += `
			  <div class="d-flex justify-content-between mb-2">
				<span>Pajak (10%):</span>
				<span id="detail-tax">${formatCurrency(orderSummary.tax || 0)}</span>
			  </div>
			  <div class="d-flex justify-content-between fw-bold text-success">
				<span>Total:</span>
				<span id="detail-total">${formatCurrency(orderSummary.total || 0)}</span>
			  </div>
			</div>
		  </div>
		`

      // Add promo info section if applicable
      if (promoInfo) {
        const promoTypeText = getPromoTypeDisplayName(promoInfo.type)
        const promoTypeClass = getPromoTypeClass(promoInfo.type)

        // CRITICAL FIX: Different info display for bundling/BOGO
        let infoValue
        if (
          isBundlingCode ||
          isBogoCode ||
          promoInfo.type === 'bundling' ||
          promoInfo.type === 'bogo'
        ) {
          infoValue = `<span class="badge bg-info">Nilai Produk Gratis: ${formatCurrency(
            discountAmount
          )}</span>`
        } else {
          infoValue = `<span class="badge bg-danger">Hemat ${formatCurrency(
            discountAmount
          )}</span>`
        }

        detailHtml += `
			<div class="promo-section mt-3">
			  <div class="alert alert-light border">
				<div class="d-flex">
				  <div class="me-3">
					<i class="fa fa-tags fs-4 text-${promoTypeClass}"></i>
				  </div>
				  <div>
					<div class="d-flex justify-content-between align-items-start">
					  <div>
						<strong>Kode Promo: ${promoInfo.code}</strong>
						<span class="badge bg-${promoTypeClass} ms-2">${promoTypeText}</span>
					  </div>
					  <div>
						${infoValue}
					  </div>
					</div>
					${
            isBundlingCode ||
            isBogoCode ||
            promoInfo.type === 'bundling' ||
            promoInfo.type === 'bogo'
              ? '<small class="d-block text-muted mt-1">*Nilai produk gratis ditampilkan sebagai informasi dan tidak mengurangi total belanja</small>'
              : ''
          }
				  </div>
				</div>
			  </div>
			</div>
		  `
      }

      // Add timing section if applicable
      if (orderData.created_at) {
        try {
          const orderDate = new Date(orderData.created_at)
          const now = new Date()
          const diffTime = Math.abs(now - orderDate)
          const diffHours = Math.floor(diffTime / (1000 * 60 * 60))
          const diffMinutes = Math.floor(
            (diffTime % (1000 * 60 * 60)) / (1000 * 60)
          )
          const durationText =
            diffHours > 0
              ? `${diffHours} jam ${diffMinutes} menit`
              : `${diffMinutes} menit`

          // Status timing based on order status
          let statusTime = ''
          let statusClass = ''
          const orderStatus = parseInt(orderData.status || 1)

          if (orderStatus === 1) {
            statusTime = 'Order sedang diproses'
            statusClass = 'text-primary'
          } else if (orderStatus === 2) {
            statusTime = 'Sedang diproses dapur'
            statusClass = 'text-info'
          } else if (orderStatus === 3) {
            statusTime = 'Sudah diantar ke meja'
            statusClass = 'text-success'
          } else if (orderStatus === 4) {
            statusTime = 'Order selesai'
            statusClass = 'text-secondary'
          } else if (orderStatus === 5) {
            statusTime = 'Order dibatalkan'
            statusClass = 'text-danger'
          }

          // Add timing panel
          detailHtml += `
			  <div class="bg-light p-3 rounded mt-3">
				<h6 class="mb-2">Waktu Order</h6>
				<div class="row">
				  <div class="col-6">
					<p class="mb-1"><strong>Mulai:</strong> ${formatDateTime(
            orderData.created_at
          )}</p>
					<p class="mb-0"><strong>Durasi:</strong> ${durationText}</p>
				  </div>
				  <div class="col-6 text-end">
					<p class="mb-0 ${statusClass}"><strong>Status:</strong> ${statusTime}</p>
				  </div>
				</div>
			  </div>
			`
        } catch (timingError) {
          console.warn('Error generating timing panel:', timingError)
        }
      }

      // Add status update buttons if applicable
      if (parseInt(orderData.status || 1) < 5) {
        detailHtml += `
			<div class="mt-3">
			  <h6 class="mb-2">Update Status Order:</h6>
			  <div class="btn-group status-actions" role="group">
				<button type="button" class="btn btn-sm btn-outline-info update-status-btn" 
				  data-status="2" data-table-id="${tableId}"
				  ${parseInt(orderData.status || 1) >= 2 ? 'disabled' : ''}>
				  <i class="fa-solid fa-kitchen-set me-1"></i>Sedang Diproses
				</button>
				<button type="button" class="btn btn-sm btn-outline-success update-status-btn" 
				  data-status="3" data-table-id="${tableId}"
				  ${parseInt(orderData.status || 1) >= 3 ? 'disabled' : ''}>
				  <i class="fa-solid fa-check me-1"></i>Sudah Diantar
				</button>
				<button type="button" class="btn btn-sm btn-outline-secondary update-status-btn" 
				  data-status="4" data-table-id="${tableId}"
				  ${parseInt(orderData.status || 1) >= 4 ? 'disabled' : ''}>
				  <i class="fa-solid fa-flag-checkered me-1"></i>Selesai
				</button>
				<button type="button" class="btn btn-sm btn-outline-danger update-status-btn" 
				  data-status="5" data-table-id="${tableId}">
				  <i class="fa-solid fa-ban me-1"></i>Batalkan
				</button>
			  </div>
			</div>
		  `
      }

      // Show order details modal
      Swal.fire({
        title: `Detail Order - Meja ${tableId}`,
        html: detailHtml,
        icon: null,
        width: '800px',
        showCloseButton: true,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-print me-2"></i>Cetak Struk',
        cancelButtonText: 'Tutup',
        customClass: {
          container: 'swal-wide',
          popup: 'swal-wide'
        },
        onOpen: modalElement => {
          // Set up status button handlers
          $(modalElement).on('click', '.update-status-btn', function () {
            const status = $(this).data('status')
            const tableId = $(this).data('tableId')

            // Confirm status change
            const statusLabels = {
              2: 'Sedang Diproses',
              3: 'Sudah Diantar',
              4: 'Selesai',
              5: 'Dibatalkan'
            }

            Swal.fire({
              title: 'Ubah Status?',
              text: `Apakah Anda yakin ingin mengubah status order meja ${tableId} menjadi "${statusLabels[status]}"?`,
              icon: 'question',
              showCancelButton: true,
              confirmButtonText: 'Ya, Ubah Status',
              cancelButtonText: 'Batal'
            }).then(result => {
              if (result.isConfirmed) {
                updateOrderStatus(tableId, status).then(() => {
                  // Close detail modal
                  Swal.close()
                  // Re-fetch order details to refresh data
                  setTimeout(() => {
                    fetchOrderDetails(tableId)
                  }, 500)
                })
              }
            })
          })
        }
      }).then(result => {
        if (result.isConfirmed) {
          // Print receipt
          printReceipt(tableId)
        }
      })
    } catch (fetchError) {
      if (fetchError.name === 'AbortError') {
        console.error('Request timed out')
        Swal.fire({
          title: 'Timeout',
          html: `<div class="alert alert-danger">
			  <i class="fa-solid fa-clock me-2"></i> Permintaan timeout. Server tidak merespons dalam waktu yang ditentukan.
			</div>`,
          icon: 'error',
          confirmButtonText: 'Coba Lagi',
          showCancelButton: true,
          cancelButtonText: 'Batal'
        }).then(result => {
          if (result.isConfirmed) {
            fetchOrderDetails(tableId)
          }
        })
      } else {
        Swal.fire({
          title: 'Error',
          html: `<div class="alert alert-danger">
			  <i class="fa-solid fa-circle-xmark me-2"></i> Error: ${fetchError.message}
			</div>`,
          icon: 'error',
          confirmButtonText: 'OK'
        })
        throw fetchError
      }
    }
    console.groupEnd()
  } catch (error) {
    console.error('Error fetching order details:', error)

    // Close any open sweet alert
    Swal.close()

    // Show error message
    let errorMessage = 'Gagal memuat detail order'

    if (typeof speakNotification === 'function') {
      try {
        speakNotification(
          `Error, gagal memuat detail order. ${error.message}`,
          'error'
        )
      } catch (ttsError) {
        console.warn('TTS error notification failed:', ttsError)
      }
    }

    if (error.status) {
      errorMessage += ` (Status: ${error.status})`
    }

    if (error.responseJSON) {
      errorMessage += `: ${error.responseJSON.message || ''}`
    } else if (error.message) {
      errorMessage += `: ${error.message}`
    }

    Swal.fire({
      title: 'Error',
      html: `<div class="alert alert-danger">
		  <i class="fa-solid fa-circle-xmark me-2"></i> ${errorMessage}
		</div>`,
      icon: 'error',
      confirmButtonText: 'OK'
    })

    console.groupEnd()
  }
}

function detectPromoTypeFromCode (code) {
  if (!code) return 'nominal' // Default

  const upperCode = code.toUpperCase()

  // Perbaikan kritis: Prioritaskan deteksi BUNDLING dan BOGO
  if (upperCode.includes('BUNDLING') || upperCode.includes('BUNDLE')) {
    return 'bundling'
  }

  if (
    upperCode.includes('BOGO') ||
    (upperCode.includes('BUY') && upperCode.includes('GET'))
  ) {
    return 'bogo'
  }

  if (
    upperCode.includes('PERSENTASE') ||
    upperCode.includes('PCT') ||
    upperCode.includes('%')
  ) {
    return 'percentage'
  }

  if (upperCode.includes('NOMINAL') || upperCode.includes('DISC')) {
    return 'nominal'
  }

  return 'nominal' // Default
}

function getPromoTypeDisplayName (type) {
  switch (type) {
    case 'percentage':
      return 'Persentase'
    case 'nominal':
      return 'Nominal'
    case 'bundling':
      return 'Bundle'
    case 'bogo':
      return 'Buy One Get One'
    default:
      return 'Promo'
  }
}

function getPromoTypeClass (type) {
  switch (type) {
    case 'percentage':
      return 'danger'
    case 'nominal':
      return 'primary'
    case 'bundling':
      return 'info'
    case 'bogo':
      return 'success'
    default:
      return 'secondary'
  }
}

function getStatusLabel (status) {
  const statusId = parseInt(status || 1)
  switch (statusId) {
    case 0:
      return 'Dipesan'
    case 1:
      return 'Order Diproses'
    case 2:
      return 'Sedang Diproses'
    case 3:
      return 'Sudah Diantar'
    case 4:
      return 'Selesai'
    case 5:
      return 'Dibatalkan'
    default:
      return 'Unknown'
  }
}

function getStatusBadgeClass (status) {
  const statusId = parseInt(status || 1)
  switch (statusId) {
    case 0:
      return 'bg-primary'
    case 1:
      return 'bg-success'
    case 2:
      return 'bg-info'
    case 3:
      return 'bg-success'
    case 4:
      return 'bg-secondary'
    case 5:
      return 'bg-danger'
    default:
      return 'bg-light'
  }
}

function printReceipt (tableId) {
  try {
    // Get parameters from URL
    const currentUrl = window.location.href
    const url = new URL(currentUrl)
    const baseUrl =
      url.protocol +
      '//' +
      url.host +
      url.pathname.split('/').slice(0, 4).join('/')
    const brand = $('#brand').val() || url.searchParams.get('brand')
    const outletId = url.pathname.split('/').pop()

    // PERBAIKAN: Cek apakah order data tersedia
    const orderData = detailedOrderData[tableId]
    const orderId = orderData?.id

    // Construct receipt URL
    let receiptUrl = `${baseUrl}/download?action=printReceipt&tableId=${tableId}&outletId=${outletId}&brand=${brand}`

    // PERBAIKAN: Tambahkan sessionId untuk cetak berdasarkan order ID
    if (orderId) {
      receiptUrl += `&sessionId=${orderId}`
    }

    console.log('Opening receipt URL:', receiptUrl)

    // Open receipt in new window
    const receiptWindow = window.open(
      receiptUrl,
      '_blank',
      'width=800,height=600'
    )

    if (!receiptWindow) {
      // If popup blocked, show direct link
      Swal.fire({
        title: 'Pop-up diblokir',
        html: `
			<p>Browser Anda memblokir pop-up untuk struk.</p>
			<p>Anda dapat mencetak struk dengan mengklik tautan di bawah:</p>
			<a href="${receiptUrl}" target="_blank" class="btn btn-primary mt-3">
			  <i class="fa-solid fa-print me-2"></i>Buka Struk
			</a>
		  `,
        icon: 'info',
        showConfirmButton: true,
        confirmButtonText: 'OK'
      })
    }
  } catch (error) {
    console.error('Error printing receipt:', error)
    Swal.fire({
      title: 'Gagal Mencetak',
      text: 'Terjadi kesalahan saat mencetak struk: ' + error.message,
      icon: 'error',
      confirmButtonText: 'OK'
    })
  }
}

function showSessionNotification (tableNumbers, sessionCustomers = {}) {
  if (!tableNumbers || !tableNumbers.length) return

  console.log('SessionCustomers data:', sessionCustomers)

  // Buat pesan detail dengan nama customer
  let detailHtml = '<div class="alert alert-info">'
  tableNumbers.forEach(tableId => {
    // PERBAIKAN: Prioritaskan data dari parameter, lalu dari global state
    const customerName =
      sessionCustomers[tableId] || customerNames[tableId] || 'Tanpa Nama'

    // PERBAIKAN: Update state dan UI jika ada nama dari parameter
    if (sessionCustomers[tableId]) {
      customerNames[tableId] = sessionCustomers[tableId]
      $('#tableName-' + tableId).text(sessionCustomers[tableId])
    }

    detailHtml += `
		<p class="mb-2">
		  <strong>Meja ${tableId}</strong> - Customer: <span class="text-primary">${customerName}</span>
		</p>
	  `
  })
  detailHtml += `
	  <p class="mt-2 mb-0">
		<i class="fas fa-info-circle me-2"></i> Pelanggan telah mulai memesan
	  </p>
	</div>`

  // Gunakan TTS untuk membacakan notifikasi dengan nama customer
  try {
    // PERBAIKAN: Buat string nama customer dari parameter
    const customersText = tableNumbers
      .map(tableId => {
        const name =
          sessionCustomers[tableId] || customerNames[tableId] || 'tanpa nama'
        return `meja ${tableId} dengan nama ${name}`
      })
      .join(', ')

    speakNotification(
      `Sesi baru dimulai untuk ${customersText}. Pelanggan telah mulai memesan.`,
      'newCustomerBell'
    )
  } catch (ttsError) {
    console.warn('TTS notification failed:', ttsError)
  }

  Swal.fire({
    title: 'Sesi Baru!',
    html: detailHtml,
    icon: 'info',
    confirmButtonText: 'Lihat Sekarang',
    showCancelButton: true,
    cancelButtonText: 'Tutup',
    customClass: {
      container: 'session-notification-modal',
      popup: 'animated bounceIn'
    }
  }).then(result => {
    if (result.isConfirmed && tableNumbers.length > 0) {
      // PERBAIKAN: Tandai sesi sebagai sudah dilihat
      tableNumbers.forEach(tableId => {
        viewedSessions.add(tableId.toString())
      })

      // Hentikan loop notifikasi jika ada
      if (
        window.activeBellNotification &&
        window.activeBellNotification.type === 'newCustomerBell'
      ) {
        const { tables } = window.activeBellNotification
        const allSessionsViewed = tables.every(t =>
          viewedSessions.has(t.toString())
        )

        if (allSessionsViewed) {
          stopLoopNotification()
        }
      }

      // Scroll to table in the list
      const tableRow = $('#tableId-' + tableNumbers[0])
      if (tableRow.length) {
        $('html, body').animate({ scrollTop: tableRow.offset().top - 100 }, 500)
        // Highlight the row
        tableRow.addClass('highlight-row')
        setTimeout(() => {
          tableRow.removeClass('highlight-row')
        }, 3000)
      }
    }
  })

  // Show toast notification with customer names
  let toastMessage = 'Sesi baru dimulai untuk '
  toastMessage += tableNumbers
    .map(tableId => {
      const name = customerNames[tableId] || 'tanpa nama'
      return `meja ${tableId} (${name})`
    })
    .join(', ')

  showToastNotification('Sesi Baru Dimulai', toastMessage, 'info')
}

function markSessionsAsRead (tableIds) {
  if (!tableIds.length) return

  console.log('Marking sessions as read for tables:', tableIds)

  // Dapatkan parameter URL dengan cara yang lebih robust
  const currentUrl = new URL(window.location.href)
  const baseUrl = `${currentUrl.protocol}//${currentUrl.host}`
  const brand = $('#brand').val() || currentUrl.searchParams.get('brand') || ''
  const outletId = currentUrl.pathname.split('/').filter(Boolean).pop() || ''

  // Tambahkan error handling yang lebih baik
  $.ajax({
    type: 'POST',
    url: `${baseUrl}/order/markSessionsAsRead`,
    data: JSON.stringify({
      tableIds: tableIds,
      outletId: outletId,
      brand: brand
    }),
    contentType: 'application/json',
    dataType: 'json',
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
    .done(function (response) {
      console.log('Sessions marked as read:', response)
    })
    .fail(function (xhr, status, error) {
      console.error('Failed to mark sessions as read:', {
        status: xhr.status,
        responseText: xhr.responseText,
        error: error
      })
    })
}

function markOrdersAsRead (tableIds) {
  if (!tableIds || tableIds.length === 0) return Promise.resolve()

  console.log('Marking orders as read for tables:', tableIds)

  // Dapatkan parameter URL dengan cara yang lebih robust
  const currentUrl = new URL(window.location.href)
  const baseUrl = `${currentUrl.protocol}//${currentUrl.host}`
  const brand = $('#brand').val() || currentUrl.searchParams.get('brand') || ''

  // Ekstrak outletId dengan cara yang lebih aman
  const pathParts = currentUrl.pathname.split('/').filter(Boolean)
  const outletId = pathParts[pathParts.length - 1] || ''

  // Validasi parameter
  if (!outletId || !brand) {
    console.error('Missing outletId or brand for marking orders as read')
    return Promise.reject(new Error('Missing required parameters'))
  }

  // PERBAIKAN: Log untuk debugging
  console.log('markOrdersAsRead parameters:', {
    tableIds: tableIds,
    outletId: outletId,
    brand: brand,
    baseUrl: baseUrl
  })

  return new Promise((resolve, reject) => {
    $.ajax({
      type: 'POST',
      url: `${baseUrl}/order/markOrdersAsRead`,
      data: JSON.stringify({
        tableIds: tableIds,
        outletId: outletId,
        brand: brand
      }),
      contentType: 'application/json',
      dataType: 'json',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .done(function (response) {
        console.log('Orders marked as read:', response)
        resolve(response)
      })
      .fail(function (xhr, status, error) {
        console.error('Failed to mark orders as read:', {
          status: xhr.status,
          statusText: xhr.statusText,
          responseText: xhr.responseText,
          error: error
        })

        // PERBAIKAN: Menambahkan retry mechanism
        if (retries < 3) {
          console.log(`Retrying markOrdersAsRead (${retries + 1}/3)...`)
          setTimeout(() => {
            markOrdersAsRead(tableIds).then(resolve).catch(reject)
          }, 1000)
        } else {
          // Tampilkan pesan error yang lebih informatif
          Swal.fire({
            icon: 'error',
            title: 'Kesalahan Marking Order',
            html: `
              <p>Gagal menandai order sebagai terbaca.</p>
              <details>
                <summary>Detail Error</summary>
                <pre>${xhr.status}: ${xhr.statusText}</pre>
                <pre>${xhr.responseText}</pre>
              </details>
            `,
            confirmButtonText: 'OK'
          })

          reject(new Error('Failed to mark orders as read'))
        }
      })
  })
}

function showOrderNotification (tableNumbers) {
  // Use SweetAlert for prettier notifications
  Swal.fire({
    title: 'Order Baru!',
    html:
      '<p>Ada pesanan baru dari ' +
      (tableNumbers.length > 1 ? 'meja:' : 'meja') +
      '</p>' +
      '<p class="fs-4 fw-bold text-primary mt-2">' +
      tableNumbers.join(', ') +
      '</p>',
    icon: 'info',
    confirmButtonText: 'Lihat Detail',
    showCancelButton: true,
    cancelButtonText: 'Nanti',
    customClass: {
      container: 'order-notification-modal',
      popup: 'animated bounceIn'
    }
  }).then(function (result) {
    if (result.isConfirmed && tableNumbers.length > 0) {
      // Show detail for the first table
      const tableId = tableNumbers[0]
      fetchOrderDetails(tableId)
    }
  })

  // Also show a toast notification that will disappear automatically
  showToastNotification(
    'Order Baru',
    'Ada pesanan baru dari meja: ' + tableNumbers.join(', '),
    'success'
  )
}

// Show notification for customer updates
function showCustomerUpdateNotification (updates) {
  if (updates.length === 0) return

  const updateMessages = updates.map(function (update) {
    return (
      'Meja ' + update.tableId + ': ' + update.oldName + ' → ' + update.newName
    )
  })

  // Show toast notification
  showToastNotification(
    'Informasi Pelanggan',
    'Detail pelanggan diperbarui:<br>' + updateMessages.join('<br>'),
    'info'
  )
}

// Generic toast notification function
// Generic toast notification function
function showToastNotification (title, message, type) {
  type = type || 'info'

  // Create unique ID for this notification
  const notificationId = 'toast-' + Date.now()

  // Define icons based on type
  const icons = {
    success: 'fa-circle-check',
    info: 'fa-circle-info',
    warning: 'fa-triangle-exclamation',
    error: 'fa-circle-xmark'
  }

  // Define background colors
  const bgColors = {
    success: 'bg-success',
    info: 'bg-info',
    warning: 'bg-warning',
    error: 'bg-danger'
  }

  // Create toast HTML
  const toastHTML =
    '<div class="notification-toast" id="' +
    notificationId +
    '">' +
    '<div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">' +
    '<div class="toast-header ' +
    bgColors[type] +
    ' text-white">' +
    '<i class="fa-solid ' +
    icons[type] +
    ' me-2"></i>' +
    '<strong class="me-auto">' +
    title +
    '</strong>' +
    '<small>baru saja</small>' +
    '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>' +
    '</div>' +
    '<div class="toast-body">' +
    message +
    '</div>' +
    '</div>' +
    '</div>'

  // Add to document
  $('body').append(toastHTML)

  // Keep track of active notifications
  activeNotifications.push(notificationId)

  // Remove after delay
  setTimeout(function () {
    $('#' + notificationId).fadeOut(500, function () {
      $(this).remove()
      // Remove from tracking array
      const index = activeNotifications.indexOf(notificationId)
      if (index > -1) {
        activeNotifications.splice(index, 1)
      }
    })
  }, 5000)
}

function applyFilters () {
  const statusFilter = currentFilters.status
  const searchText = currentFilters.search.toLowerCase()
  let visibleRowCount = 0

  // Loop through all table rows
  $('#tablesListTable tbody tr').each(function () {
    const $row = $(this)
    const tableId = $row.attr('id').replace('tableId-', '')
    const tableStatus = statusTable[tableId - 1]
    const customerName = $row
      .find('#tableName-' + tableId)
      .text()
      .toLowerCase()

    // Determine if row should be visible based on filters
    let showRow = true

    // Apply status filter
    if (statusFilter !== 'all') {
      showRow = showRow && String(tableStatus) === statusFilter
    }

    // Apply search filter
    if (searchText) {
      const rowText = $row.text().toLowerCase()
      showRow = showRow && rowText.includes(searchText)
    }

    // Show/hide row
    if (showRow) {
      $row.show()
      visibleRowCount++
    } else {
      $row.hide()
    }
  })

  // Show/hide empty message
  if (visibleRowCount === 0) {
    $('#emptyTablesMessage').show()
  } else {
    $('#emptyTablesMessage').hide()
  }
}

// Fungsi format tanggal tambahan
function formatDateTime (dateString) {
  try {
    if (!dateString) return '-'

    const date = new Date(dateString)
    if (isNaN(date.getTime())) {
      return dateString // Jika format tanggal invalid, kembalikan string asli
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
}

// Format durasi dari detik ke format hh:mm:ss
function formatDuration (seconds) {
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  const secs = seconds % 60

  return [
    hours.toString().padStart(2, '0'),
    minutes.toString().padStart(2, '0'),
    secs.toString().padStart(2, '0')
  ].join(':')
}

// Format durasi untuk display
function formatDurationDisplay (hours, minutes, seconds) {
  let parts = []

  if (hours > 0) {
    parts.push(`${hours} jam`)
  }

  if (minutes > 0 || hours > 0) {
    parts.push(`${minutes} menit`)
  }

  if (hours === 0 && parts.length < 2) {
    parts.push(`${seconds} detik`)
  }

  return parts.join(' ')
}

function pollTableStatus () {
  // PERBAIKAN: Load saved statuses if array is empty
  if (statusTable.length === 0) {
    const loadedFromStorage = loadTableStatusesFromStorage()

    // Jika berhasil dimuat, perbarui UI untuk setiap meja
    if (loadedFromStorage) {
      statusTable.forEach((element, index) => {
        const tableId = index + 1
        const customerName = customerNames[tableId]
        const orderTime = orderTimes[tableId]
        const orderTotal = orderTotals[tableId]
        changeStatusState(tableId, element, customerName, orderTime, orderTotal)
      })

      // Update hitungan order aktif
      updateActiveOrdersCount()
    }
  }

  // Get current parameters
  const currentUrl = window.location.href
  const url = new URL(currentUrl)
  const baseUrl =
    url.protocol +
    '//' +
    url.host +
    url.pathname.split('/').slice(0, 4).join('/')
  const brand = $('#brand').val() || url.searchParams.get('brand')
  const outletId = url.pathname.split('/').pop()
  const outletParams = new URLSearchParams({
    outletId: outletId,
    brand: brand
  })

  // Update brand selector to match URL if needed
  if ($('#brand').val() !== brand) {
    $('#brand').val(brand)
  }

  // Make API request
  $.ajax({
    type: 'GET',
    url: baseUrl + '/getData?action=getStatusTable&' + outletParams.toString(),
    contentType: 'application/json',
    dataType: 'json',
    timeout: 5000 // 5 second timeout
  })
    .done(function (response, textStatus, jqXHR) {
      // Reset error counter on success
      pollingErrors = 0

      // Update status indicator
      $('#activityIndicator').removeClass('bg-danger').addClass('bg-dark')
      $('#activityIndicator span').text('Memantau aktivitas meja...')

      // PERBAIKAN KRITIS: Log untuk debugging
      console.log('Table status response:', response)

      // Process response
      if (response.success) {
        // PERBAIKAN KRITIS: Pastikan getTableStatus menerima response yang benar
        getTableStatus(response, textStatus, jqXHR)
      } else {
        console.error('Poll response not successful:', response)
      }
    })
    .fail(function (xhr, textStatus, errorThrown) {
      console.error('Polling error:', textStatus, errorThrown)
      pollingErrors++

      // Update status indicator to show error
      $('#activityIndicator').removeClass('bg-dark').addClass('bg-danger')
      $('#activityIndicator span').text(
        'Koneksi error (' + pollingErrors + '/' + MAX_POLLING_ERRORS + ')...'
      )

      // Show warning if too many errors
      if (pollingErrors >= MAX_POLLING_ERRORS) {
        // Show error toast
        showToastNotification(
          'Kesalahan Koneksi',
          'Terjadi masalah saat memperbarui status meja. Coba refresh halaman.',
          'error'
        )

        // Reset error counter
        pollingErrors = Math.floor(MAX_POLLING_ERRORS / 2)
      }
    })
    .always(function () {
      // Schedule next poll with progressive backoff on errors
      const nextPollDelay =
        pollingErrors === 0
          ? 4000
          : Math.min(4000 + pollingErrors * 1000, 15000)
      setTimeout(pollTableStatus, nextPollDelay)
    })
}

// Fungsi tambahan untuk melakukan smart merge status dari server dengan status lokal
function handleSmartStatusMerge (response, textStatus, jqXHR) {
  console.group('Smart Status Merge')
  const respData =
    (response.data && response.data.data) || response.data || response
  const serverStatuses = respData.statuses || []

  console.log('Local Status Table:', statusTable)
  console.log('Server Status Table:', serverStatuses)

  // Jika status lokal tidak ada atau serverStatuses tidak ada, gunakan cara biasa
  if (!statusTable.length || !serverStatuses.length) {
    console.log('Using regular status update due to missing data')
    getTableStatus(response, textStatus, jqXHR)
    console.groupEnd()
    return
  }

  // Simpan lokalLastUpdated dari server jika ada
  const localLastUpdated = respData.localLastUpdated || {}

  // Simpan serverLastUpdated dari server jika ada
  const serverLastUpdated = respData.serverLastUpdated || {}

  // Lakukan merge pintar
  let merged = false
  for (let i = 0; i < serverStatuses.length; i++) {
    const tableId = i + 1
    const localStatus = statusTable[i]
    const serverStatus = serverStatuses[i]

    // Status server null artinya tidak ada data - tidak perlu diupdate
    if (serverStatus === null) continue

    // Jika status lokal tidak ada (null), gunakan status server
    if (localStatus === null || localStatus === undefined) {
      statusTable[i] = serverStatus
      merged = true
      continue
    }

    // Buat numeric status dari localStatus untuk perbandingan
    let localNumericStatus = localStatus
    if (localStatus === 'reserved') localNumericStatus = 0
    else if (localStatus === 'ordered') localNumericStatus = 1
    else if (localStatus === 'processing') localNumericStatus = 2
    else if (localStatus === 'served') localNumericStatus = 3
    else if (localStatus === 'completed') localNumericStatus = 4
    else if (localStatus === 'cancelled') localNumericStatus = 5
    else if (typeof localStatus === 'string')
      localNumericStatus = parseInt(localStatus, 10)

    // Buat numeric status dari serverStatus untuk perbandingan
    let serverNumericStatus = serverStatus
    if (serverStatus === 'reserved') serverNumericStatus = 0
    else if (serverStatus === 'ordered') serverNumericStatus = 1
    else if (serverStatus === 'processing') serverNumericStatus = 2
    else if (serverStatus === 'served') serverNumericStatus = 3
    else if (serverStatus === 'completed') serverNumericStatus = 4
    else if (serverStatus === 'cancelled') serverNumericStatus = 5
    else if (typeof serverStatus === 'string')
      serverNumericStatus = parseInt(serverStatus, 10)

    console.log(
      `Table ${tableId}: Local=${localNumericStatus}, Server=${serverNumericStatus}`
    )

    // Tangani prioritas status
    if (localNumericStatus === serverNumericStatus) {
      // Status sama, tidak perlu diupdate
      continue
    }

    // Dapatkan timestamp update lokal dan server jika ada
    const localUpdated = localLastUpdated[tableId] || 0
    const serverUpdated = serverLastUpdated[tableId] || 0

    // Jika status diupdate berdasarkan waktu terakhir
    if (localUpdated > serverUpdated) {
      // Status lokal lebih baru, pertahankan
      console.log(
        `Table ${tableId}: Keeping local status (${localNumericStatus}) as it's newer`
      )
    } else if (serverUpdated > localUpdated) {
      // Status server lebih baru, update lokal
      console.log(
        `Table ${tableId}: Updating to server status (${serverNumericStatus}) as it's newer`
      )
      statusTable[i] = serverNumericStatus
      merged = true

      recordOrderStatusToServer(tableId, status, orderId)
    } else {
      // Timestamp sama atau tidak ada, prioritaskan status yang lebih maju
      // 5 (cancelled) > 4 (completed) > 3 (served) > 2 (processing) > 1 (ordered) > 0 (reserved)
      if (serverNumericStatus > localNumericStatus) {
        console.log(
          `Table ${tableId}: Updating to higher server status (${serverNumericStatus})`
        )
        statusTable[i] = serverNumericStatus
        merged = true
      }
    }
  }

  // Jika ada perubahan, perbarui UI untuk setiap meja
  if (merged) {
    console.log('Status merged, updating UI')
    statusTable.forEach((element, index) => {
      const tableId = index + 1

      // Dapatkan data dari response untuk update UI
      const customerData = respData.customers || {}
      const orderTimeData = respData.orderTimes || {}
      const orderTotalData = respData.totals || {}

      const customerName = customerData[tableId] || customerNames[tableId]
      const orderTime = orderTimeData[tableId] || orderTimes[tableId]
      const orderTotal = orderTotalData[tableId] || orderTotals[tableId]

      changeStatusState(tableId, element, customerName, orderTime, orderTotal)
    })

    // Update hitungan order aktif
    updateActiveOrdersCount()

    // Simpan perubahan ke localStorage
    saveTableStatusesToStorage()
  } else {
    console.log('No status changes needed')
  }

  // Tetap proses notifikasi dengan getTableStatus
  getTableStatus(response, textStatus, jqXHR)

  console.groupEnd()
}

document.addEventListener('DOMContentLoaded', function () {
  console.log('DOM loaded, initializing orderDetail page')
  initTextToSpeech()

  loadTableStatusesFromStorage()

  window.viewedOrders = new Set()
  window.viewedSessions = new Set()
  window.activeBellNotification = null
  window.activeWaiterCallNotification = null
  window.unprocessedWaiterCalls = new Set()
  window.processedWaiterCalls = new Set()

  // Setup activity indicator
  $('#activityIndicator').css({
    display: 'none' // Initially hidden, shown when there are active orders
  })

  // Get current URL parameters
  const url = new URL(window.location.href)
  const baseUrl =
    url.protocol +
    '//' +
    url.host +
    url.pathname.split('/').slice(0, 4).join('/')
  const brand = url.searchParams.get('brand')
  const outletId = url.pathname.split('/').pop()
  const outletParams = new URLSearchParams({
    outletId: outletId,
    brand: brand
  })

  // Set brand selector to match URL
  $('#brand').val(brand)

  // Handle brand change
  $('#brand').on('change', function () {
    const newBrand = $(this).val()
    const newUrl = new URL(window.location.href)
    newUrl.searchParams.set('brand', newBrand)
    window.location.href = newUrl.toString()
  })

  // Handle status filter change
  $('#statusFilter').on('change', function () {
    currentFilters.status = $(this).val()
    applyFilters()
  })

  // Handle table search
  $('#tableSearch').on('input', function () {
    currentFilters.search = $(this).val().trim()
    applyFilters()
  })

  // Clear search button
  $('#clearSearch').on('click', function () {
    $('#tableSearch').val('')
    currentFilters.search = ''
    applyFilters()
  })

  // Reset filters button
  $('#resetFilters').on('click', function () {
    $('#statusFilter').val('all')
    $('#tableSearch').val('')
    currentFilters.status = 'all'
    currentFilters.search = ''
    applyFilters()
  })

  // Make table rows clickable to view details
  $('.table-record').on('click', function (e) {
    if ($(e.target).closest('button').length === 0) {
      const tableId = $(this).attr('id').replace('tableId-', '')

      // PERBAIKAN: Tambahkan ke set order yang sudah dilihat
      viewedOrders.add(tableId)

      // PERBAIKAN: Hentikan bell jika sedang berbunyi
      const orderBell = document.getElementById('orderBell')
      if (orderBell && orderBell.loop) {
        orderBell.loop = false
        orderBell.pause()
        orderBell.currentTime = 0
      }

      const idx = tableId - 1
      const tableStatus = statusTable[idx]
      console.log(
        `Clicked on table ${tableId}, status=${tableStatus}, type=${typeof tableStatus}`
      )

      if (tableStatus !== null && tableStatus !== undefined) {
        fetchOrderDetails(tableId)
      } else {
        Swal.fire({
          title: 'Meja Kosong',
          text: `Meja ${tableId} saat ini belum memiliki order aktif.`,
          icon: 'info',
          confirmButtonText: 'OK'
        })
      }
    }
  })

  $('.view-order').each(function () {
    $(this).on('click', function (e) {
      e.preventDefault()
      e.stopPropagation()

      const tableId = $(this).data('tableId')

      viewedOrders.add(tableId.toString())

      const orderBell = document.getElementById('orderBell')
      if (orderBell && orderBell.loop) {
        orderBell.loop = false
        orderBell.pause()
        orderBell.currentTime = 0
      }

      const idx = tableId - 1
      const tableStatus = statusTable[idx]

      if (tableStatus !== null && tableStatus !== undefined) {
        fetchOrderDetails(tableId)
      } else {
        Swal.fire({
          title: 'Meja Kosong',
          text: `Meja ${tableId} saat ini belum memiliki order aktif.`,
          icon: 'info',
          confirmButtonText: 'OK'
        })
      }
    })
  })

  $('.print-receipt').each(function () {
    $(this).on('click', function (e) {
      e.preventDefault()
      e.stopPropagation()

      const tableId = $(this).data('tableId')
      console.log('Print receipt clicked for table:', tableId)

      // PERBAIKAN: Hapus validasi status yang membatasi
      printReceipt(tableId)
    })
  })

  // Action Detail button handler
  $('#detailAction').on('click', function () {
    const tableId = $('#detailOrder').data('tableId')
    printReceipt(tableId)
  })

  // Release Data button handler
  $('#releaseData').on('click', function () {
    const orderId = $('#detailOrder').data('orderId')

    // Confirm before delete
    Swal.fire({
      title: 'Hapus Order?',
      text: 'Anda yakin ingin menghapus order ini? Tindakan ini tidak dapat dibatalkan.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, Hapus',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#dc3545'
    }).then(result => {
      if (result.isConfirmed && orderId) {
        // Perform delete
        $.ajax({
          type: 'DELETE',
          url: baseUrl + '/delete/' + orderId
        })
          .done(function (response) {
            console.log('Order deleted:', response)

            // Hide modal
            orderModal.hide()

            // Show success notification
            showToastNotification(
              'Order Dihapus',
              'Order berhasil dihapus dari sistem.',
              'success'
            )

            // Force immediate status refresh
            pollTableStatus()
          })
          .fail(function (error) {
            console.error('Error deleting order:', error)

            // Show error notification
            Swal.fire({
              title: 'Gagal Menghapus',
              text:
                'Terjadi kesalahan saat menghapus order: ' +
                (error.responseJSON?.message ||
                  error.statusText ||
                  'Unknown error'),
              icon: 'error',
              confirmButtonText: 'OK'
            })
          })
      }
    })
  })

  setTimeout(() => {
    speakNotification(
      'Sistem pemantauan order aktif. Siap menerima order baru.',
      'welcome'
    )
  }, 1000)

  // Start polling for table status
  window.processedNotifications = new Set()

  if (statusTable.length > 0) {
    statusTable.forEach((status, index) => {
      const tableId = index + 1
      const customerName = customerNames[tableId]
      const orderTime = orderTimes[tableId]
      const orderTotal = orderTotals[tableId]

      // Update UI for each table
      changeStatusState(tableId, status, customerName, orderTime, orderTotal)
    })
  }

  // Then start polling
  pollTableStatus()

  // Start notification checking process
  checkServerPushNotifications()

  // Hide activity indicator initially
  $('#activityIndicator').hide()

  if ($('#waiterCallsTable').length > 0) {
    console.log('Waiter calls table detected, initializing waiter call system')

    // Load waiter calls initially
    loadWaiterCalls()

    // Start polling
    checkWaiterCalls()

    // Attach event handler to refresh button
    $('#refresh-waiter-calls').on('click', function () {
      console.log('Refresh waiter calls button clicked')
      loadWaiterCalls()

      // Tambahkan pemberitahuan TTS saat refresh
      speakNotification('Memperbarui daftar panggilan pelayan.', 'refresh')
    })
  } else {
    console.log('No waiter calls table found on this page')
  }

  // PERBAIKAN: Simpan state ke localStorage setiap 30 detik
  setInterval(saveTableStatusesToStorage, 30000)

  console.log('orderDetail page initialized successfully')
})

// Fungsi untuk menampilkan notifikasi panggilan pelayan baru
function showWaiterCallNotification (call) {
  const tableNumber = call.table_id

  // Show notification
  Swal.fire({
    title: 'Panggilan Pelayan!',
    html: `
		<div class="alert alert-warning">
		  <i class="fa fa-bell me-2"></i>
		  <strong>Meja ${tableNumber}</strong> membutuhkan bantuan pelayan
		</div>
		<p class="mt-3">Waktu panggilan: ${formatDateTime(call.created_at)}</p>
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
  showToastNotification(
    'Panggilan Pelayan',
    `Meja ${tableNumber} membutuhkan bantuan`,
    'warning'
  )
}

function checkWaiterCalls () {
  const currentUrl = window.location.href
  const url = new URL(currentUrl)
  const origin = url.origin
  const baseUrl = `${origin}/order`
  const brand = $('#brand').val() || url.searchParams.get('brand')
  const outletId = url.pathname.split('/').pop()

  console.log('Checking waiter calls with params:', {
    outletId: outletId,
    brand: brand,
    endpoint: `${baseUrl}/checkWaiterCalls`
  })

  // PERBAIKAN UTAMA: Inisialisasi state jika belum ada
  if (!window.unprocessedWaiterCalls) {
    window.unprocessedWaiterCalls = new Set()
  }

  $.ajax({
    type: 'GET',
    url: `${baseUrl}/checkWaiterCalls`,
    data: {
      outletId: outletId,
      brand: brand
    },
    dataType: 'json',
    success: function (response) {
      if (
        response.success &&
        response.waiterCalls &&
        response.waiterCalls.length > 0
      ) {
        // Periksa panggilan baru dengan status 'new'
        const newCalls = response.waiterCalls.filter(
          call =>
            call.status === 'new' && !window.processedWaiterCalls.has(call.id)
        )

        // Render semua panggilan
        renderWaiterCalls(response.waiterCalls)

        // Update processed calls tracking
        response.waiterCalls.forEach(call => {
          window.processedWaiterCalls = window.processedWaiterCalls || new Set()
          window.processedWaiterCalls.add(call.id)

          // Tambahkan ke unprocessedWaiterCalls jika status 'new'
          if (call.status === 'new') {
            unprocessedWaiterCalls.add(call.id)
          } else {
            // Hapus dari unprocessedWaiterCalls jika sudah diproses
            unprocessedWaiterCalls.delete(call.id)
          }
        })

        // Notifikasi untuk panggilan baru
        if (newCalls.length > 0) {
          handleWaiterCallNotifications(newCalls)
        }
      } else {
        // Tampilkan pesan kosong jika tidak ada panggilan
        $('#waiterCallsBody').html(`
			<tr>
			  <td colspan="5" class="text-center py-4">
				<div class="alert alert-info">
				  <i class="fa-solid fa-info-circle me-2"></i> Tidak ada panggilan pelayan saat ini
				</div>
			  </td>
			</tr>
		  `)
        $('#emptyWaiterCallsMessage').show()
      }
    },
    error: function (xhr, status, error) {
      console.error('Error checking waiter calls:', error)
      $('#waiterCallsBody').html(`
		  <tr>
			<td colspan="5" class="text-center py-4">
			  <div class="alert alert-danger">
				<i class="fa-solid fa-exclamation-circle me-2"></i> Gagal memuat data panggilan pelayan
			  </div>
			</td>
		  </tr>
		`)
    },
    complete: function () {
      // Jadwalkan pemeriksaan berikutnya dengan interval tetap
      setTimeout(checkWaiterCalls, 3000) // Periksa setiap 3 detik
    }
  })
}

function handleWaiterCallNotifications (waiterCalls) {
  if (!waiterCalls || waiterCalls.length === 0) return

  // Kelompokkan berdasarkan meja untuk notifikasi yang lebih efisien
  const tableGroups = {}
  waiterCalls.forEach(call => {
    if (!tableGroups[call.table_id]) {
      tableGroups[call.table_id] = []
    }
    tableGroups[call.table_id].push(call)
  })

  // Proses setiap meja dengan panggilan baru
  Object.keys(tableGroups).forEach(tableId => {
    const callsForTable = tableGroups[tableId]
    const latestCall = callsForTable.reduce((latest, current) =>
      new Date(current.created_at) > new Date(latest.created_at)
        ? current
        : latest
    )

    // Ambil nama customer dari data yang ada
    const customerName = customerNames[tableId] || 'Tanpa Nama'

    // Gunakan TTS untuk panggilan pelayan dengan nama customer
    try {
      if (typeof speakNotification === 'function') {
        speakNotification(
          `Panggilan pelayan dari meja ${tableId}, customer ${customerName} membutuhkan bantuan.`,
          'waiterCallBell'
        )
      }
    } catch (ttsError) {
      console.warn('TTS notification failed:', ttsError)
    }

    // Tampilkan notifikasi dengan SweetAlert
    Swal.fire({
      title: 'Panggilan Pelayan!',
      html: `
		  <div class="alert alert-warning">
			<i class="fa-solid fa-bell me-2"></i>
			<strong>Meja ${tableId}</strong> - Customer: <span class="text-primary">${customerName}</span>
		  </div>
		  <p>Waktu Panggilan: ${formatDateTime(latestCall.created_at)}</p>
		  ${
        callsForTable.length > 1
          ? `
			  <div class="alert alert-info mt-2">
				<i class="fa-solid fa-info-circle me-2"></i>
				Ada ${callsForTable.length} panggilan aktif di meja ini
			  </div>
			  `
          : ''
      }
		`,
      icon: 'warning',
      confirmButtonText: 'Proses',
      showCancelButton: true,
      cancelButtonText: 'Nanti'
    }).then(result => {
      if (result.isConfirmed) {
        // Proses panggilan pelayan
        processWaiterCall(latestCall.id)
      }
    })

    // Tambahkan toast notification dengan nama customer
    showToastNotification(
      'Panggilan Pelayan',
      `Meja ${tableId} - Customer ${customerName} membutuhkan bantuan`,
      'warning'
    )
  })
}

function processWaiterCall (callId) {
  // Update call status to processing
  const currentUrl = window.location.href
  const url = new URL(currentUrl)
  const origin = url.origin
  const baseUrl = `${origin}/order`

  // PERBAIKAN: Hapus dari unprocessedWaiterCalls terlebih dahulu
  unprocessedWaiterCalls.delete(callId)

  // PERBAIKAN: Periksa apakah perlu menghentikan loop notifikasi
  if (window.activeWaiterCallNotification) {
    const { calls } = window.activeWaiterCallNotification

    if (calls.includes(callId)) {
      const remainingUnprocessed = calls.filter(id =>
        unprocessedWaiterCalls.has(id)
      )

      if (remainingUnprocessed.length === 0) {
        // Semua panggilan sudah diproses, hentikan loop notifikasi
        stopWaiterCallLoopNotification()
      }
    }
  }

  // Update call status to processing
  $.ajax({
    type: 'POST',
    url: `${baseUrl}/processWaiterCall`,
    data: JSON.stringify({ callId: callId }),
    contentType: 'application/json',
    success: function (response) {
      if (response.success) {
        // Show success message
        showToastNotification(
          'Panggilan Diproses',
          'Panggilan pelayan sedang diproses',
          'success'
        )

        // Reload waiter calls
        loadWaiterCalls()
      } else {
        showToastNotification(
          'Gagal',
          response.message || 'Gagal memproses panggilan',
          'error'
        )
      }
    },
    error: function (xhr, status, error) {
      console.error('Error processing waiter call:', error)
      showToastNotification(
        'Kesalahan',
        'Gagal memproses panggilan pelayan',
        'error'
      )
    }
  })
}

function loadWaiterCalls () {
  // Get current URL parameters
  const currentUrl = window.location.href
  const url = new URL(currentUrl)

  // PERBAIKAN: Buat baseUrl dengan benar
  const origin = url.origin
  const baseUrl = `${origin}/order`

  const brand = $('#brand').val() || url.searchParams.get('brand')
  const outletId = url.pathname.split('/').pop()

  // Tampilkan loading
  $('#waiterCallsBody').html(`
	  <tr>
		<td colspan="5" class="text-center py-4">
		  <div class="spinner-border text-primary" role="status">
			<span class="visually-hidden">Loading...</span>
		  </div>
		  <p class="mt-2">Memuat data panggilan pelayan...</p>
		</td>
	  </tr>
	`)

  // Make API request
  $.ajax({
    type: 'GET',
    url: `${baseUrl}/checkWaiterCalls`,
    data: {
      outletId: outletId,
      brand: brand
    },
    dataType: 'json',
    success: function (response) {
      if (response.success) {
        renderWaiterCalls(response.waiterCalls || [])
      } else {
        $('#waiterCallsBody').html(`
			<tr>
			  <td colspan="5" class="text-center py-4">
				<div class="alert alert-danger">
				  <i class="fa-solid fa-exclamation-circle me-2"></i>
				  Gagal memuat data panggilan pelayan
				</div>
			  </td>
			</tr>
		  `)
      }
    },
    error: function (xhr, status, error) {
      console.error('Error loading waiter calls:', error)
      console.error('Status:', status)
      console.error('Response:', xhr.responseText.substring(0, 500)) // Log first 500 chars

      $('#waiterCallsBody').html(`
		  <tr>
			<td colspan="5" class="text-center py-4">
			  <div class="alert alert-danger">
				<i class="fa-solid fa-exclamation-circle me-2"></i>
				Gagal memuat data panggilan pelayan: ${error}
			  </div>
			</td>
		  </tr>
		`)
    }
  })
}

function renderWaiterCalls (waiterCalls) {
  console.group('🔔 Rendering Waiter Calls')
  console.log(
    'Total Waiter Calls Received:',
    waiterCalls ? waiterCalls.length : 0
  )

  // Validasi input
  if (!Array.isArray(waiterCalls) || waiterCalls.length === 0) {
    console.log('No waiter calls to render')
    $('#waiterCallsBody').empty()
    $('#emptyWaiterCallsMessage').show()
    console.groupEnd()
    return
  }

  // Sembunyikan pesan kosong
  $('#emptyWaiterCallsMessage').hide()

  // Urutkan panggilan berdasarkan waktu terbaru
  const sortedCalls = waiterCalls
    .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
    // Batasi maksimal 10 panggilan
    .slice(0, 10)

  // PERBAIKAN: Lacak panggilan baru
  let hasNewCalls = false
  const newCalls = []

  // Waktu saat ini untuk menghitung durasi
  const now = new Date()

  // Bangun HTML untuk tabel
  const callsHtml = sortedCalls
    .map(call => {
      // PERBAIKAN: Lacak panggilan baru dengan status 'new'
      if (call.status === 'new' && !unprocessedWaiterCalls.has(call.id)) {
        unprocessedWaiterCalls.add(call.id)
        hasNewCalls = true
        newCalls.push(call)
      }
      // Hitung durasi panggilan
      const createdAt = new Date(call.created_at)
      const durationMs = now - createdAt
      const durationMinutes = Math.floor(durationMs / (1000 * 60))

      // Tentukan status dan warna badge
      const statusConfig = {
        new: {
          text: 'Baru',
          class: 'bg-danger'
        },
        processing: {
          text: 'Diproses',
          class: 'bg-warning'
        },
        completed: {
          text: 'Selesai',
          class: 'bg-success'
        }
      }

      // Gunakan konfigurasi status, default ke info jika tidak dikenali
      const status = statusConfig[call.status] || {
        text: 'Tidak Dikenal',
        class: 'bg-secondary'
      }

      // Tentukan aksi berdasarkan status
      const actionButton =
        call.status === 'new'
          ? `
				  <button class="btn btn-sm btn-outline-primary process-call" 
						  data-call-id="${call.id}">
					  <i class="fa-solid fa-check me-1"></i>Proses
				  </button>
				`
          : call.status === 'processing'
          ? `
				  <button class="btn btn-sm btn-outline-success complete-call" 
						  data-call-id="${call.id}">
					  <i class="fa-solid fa-check-double me-1"></i>Selesai
				  </button>
				`
          : '<span class="text-muted">Tidak Ada Aksi</span>'

      // Template baris tabel
      return `
			  <tr id="waiterCall-${call.id}" 
				  class="waiter-call-row ${call.status === 'new' ? 'table-warning' : ''}">
				  <td class="text-center">${call.table_id}</td>
				  <td class="text-center">
					  ${formatDateTime(call.created_at)}
				  </td>
				  <td class="text-center">
					  <span class="badge ${status.class} p-2">
						  ${status.text}
					  </span>
				  </td>
				  <td class="text-center">
					  ${durationMinutes} menit
				  </td>
				  <td class="text-center">
					  ${actionButton}
				  </td>
			  </tr>
		  `
    })
    .join('')

  $('#waiterCallsBody').html(callsHtml)

  // PERBAIKAN: Notifikasi untuk panggilan baru yang belum diproses
  if (hasNewCalls) {
    notifyNewWaiterCalls(newCalls)
  }

  // Bind event handlers untuk tombol proses dan selesai
  $('.process-call').on('click', function () {
    const callId = $(this).data('callId')
    // PERBAIKAN: Hapus dari unprocessedWaiterCalls saat diproses
    unprocessedWaiterCalls.delete(callId)
    processWaiterCall(callId)
  })

  $('.complete-call').on('click', function () {
    const callId = $(this).data('callId')
    // PERBAIKAN: Hapus dari unprocessedWaiterCalls saat diselesaikan
    unprocessedWaiterCalls.delete(callId)
    completeWaiterCall(callId)
  })

  // Log untuk debugging
  console.log(`Rendered ${sortedCalls.length} waiter calls`)
  console.groupEnd()
}

// Fungsi untuk memproses panggilan pelayan
function processWaiterCall (callId) {
  // PERBAIKAN: Buat baseUrl dengan benar
  const currentUrl = window.location.href
  const url = new URL(currentUrl)
  const origin = url.origin
  const baseUrl = `${origin}/order`

  // Update call status to processing
  $.ajax({
    type: 'POST',
    url: `${baseUrl}/processWaiterCall`,
    data: JSON.stringify({
      callId: callId
    }),
    contentType: 'application/json',
    success: function (response) {
      if (response.success) {
        // Show success message
        showToastNotification(
          'Panggilan Diproses',
          'Panggilan pelayan sedang diproses',
          'success'
        )

        // Reload waiter calls
        loadWaiterCalls()
      } else {
        showToastNotification(
          'Gagal',
          response.message || 'Gagal memproses panggilan',
          'error'
        )
      }
    },
    error: function (xhr, status, error) {
      console.error('Error processing waiter call:', error)
      showToastNotification(
        'Kesalahan',
        'Gagal memproses panggilan pelayan',
        'error'
      )
    }
  })
}

// Fungsi untuk menyelesaikan panggilan pelayan
function completeWaiterCall (callId) {
  // Get current URL parameters
  const currentUrl = window.location.href
  const url = new URL(currentUrl)
  const origin = url.origin
  const baseUrl = `${origin}/order`

  // Confirm completion
  Swal.fire({
    title: 'Selesaikan Panggilan?',
    text: 'Apakah Anda yakin pelayan sudah menyelesaikan panggilan ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Selesai',
    cancelButtonText: 'Batal'
  }).then(result => {
    if (result.isConfirmed) {
      // Send request to complete call
      $.ajax({
        type: 'POST',
        url: `${baseUrl}/completeWaiterCall`,
        data: JSON.stringify({
          callId: callId
        }),
        contentType: 'application/json',
        success: function (response) {
          if (response.success) {
            // Show success message
            showToastNotification(
              'Panggilan Selesai',
              'Panggilan pelayan telah diselesaikan',
              'success'
            )

            // Reload waiter calls
            loadWaiterCalls()
          } else {
            showToastNotification(
              'Gagal',
              response.message || 'Gagal menyelesaikan panggilan',
              'error'
            )
          }
        },
        error: function (xhr, status, error) {
          console.error('Error completing waiter call:', error)
          showToastNotification(
            'Kesalahan',
            'Gagal menyelesaikan panggilan pelayan',
            'error'
          )
        }
      })
    }
  })
}

// Inisialisasi polling untuk cek panggilan pelayan baru
function startWaiterCallPolling () {
  // Check waiter calls initially
  loadWaiterCalls()

  // Set up periodic check every 10 seconds
  setInterval(function () {
    loadWaiterCalls()
  }, 3000)
}

function startOrderTimer (tableId, startTime) {
  // Hentikan timer yang mungkin sudah berjalan untuk meja ini
  stopOrderTimer(tableId)

  // Buat elemen timer jika belum ada
  if (!$(`#timer-${tableId}`).length) {
    $(`#tableTime-${tableId}`).html(
      `<span id="timer-${tableId}">00:00:00</span>`
    )
  }

  // Konversi waktu mulai ke objek Date
  let startDate
  if (startTime) {
    startDate = new Date(startTime)
  } else {
    startDate = new Date()
  }

  // Fungsi untuk memperbarui tampilan timer
  function updateTimer () {
    const now = new Date()
    const diff = now - startDate

    // Hitung jam, menit, detik
    const hours = Math.floor(diff / (1000 * 60 * 60))
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
    const seconds = Math.floor((diff % (1000 * 60)) / 1000)

    // Format tampilan timer
    const displayHours = String(hours).padStart(2, '0')
    const displayMinutes = String(minutes).padStart(2, '0')
    const displaySeconds = String(seconds).padStart(2, '0')

    // Perbarui elemen timer
    $(`#timer-${tableId}`).text(
      `${displayHours}:${displayMinutes}:${displaySeconds}`
    )

    // Tambahkan class warna berdasarkan durasi
    if (hours >= 1) {
      // Lebih dari 1 jam - merah
      $(`#timer-${tableId}`)
        .removeClass('text-success text-warning')
        .addClass('text-danger')
    } else if (minutes >= 30) {
      // Lebih dari 30 menit - kuning
      $(`#timer-${tableId}`)
        .removeClass('text-success text-danger')
        .addClass('text-warning')
    } else {
      // Kurang dari 30 menit - hijau
      $(`#timer-${tableId}`)
        .removeClass('text-warning text-danger')
        .addClass('text-success')
    }
  }

  // Panggil sekali untuk inisialisasi
  updateTimer()

  // Set interval untuk memperbarui timer setiap detik
  orderTimers[tableId] = setInterval(updateTimer, 1000)
}

// Fungsi untuk menghentikan timer
function stopOrderTimer (tableId) {
  if (orderTimers[tableId]) {
    clearInterval(orderTimers[tableId])
    delete orderTimers[tableId]
  }
}

function checkServerPushNotifications () {
  return new Promise((resolve, reject) => {
    try {
      console.group('Check Server Push Notifications')
      console.log('Starting notification check')

      const currentUrl = window.location.href
      const urlParts = new URL(currentUrl)

      const baseUrl = `${window.location.protocol}//${window.location.host}`
      const brand =
        $('#brand').val() || urlParts.searchParams.get('brand') || ''

      const pathParts = window.location.pathname.split('/').filter(Boolean)
      const outletId = pathParts[pathParts.length - 1] || ''

      console.log('Notification Parameters:', {
        baseUrl,
        brand,
        outletId
      })

      if (!outletId || !brand) {
        console.error('❌ Missing Parameters')
        throw new Error('Missing outletId or brand')
      }

      const checkUrl = new URL('/order/check-notifications', baseUrl)
      checkUrl.searchParams.set('outletId', outletId)
      checkUrl.searchParams.set('brand', brand)

      console.log('📡 Checking Notifications URL:', checkUrl.toString())

      fetch(checkUrl.toString(), {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        }
      })
        .then(response => {
          console.log('🌐 Fetch Response Status:', response.status)

          if (!response.ok) {
            // Coba ambil body error untuk debugging
            return response.text().then(errorText => {
              console.error('❌ Error Response Body:', errorText)
              throw new Error(
                `HTTP error! status: ${response.status}, body: ${errorText}`
              )
            })
          }
          return response.json()
        })
        .then(data => {
          console.log('✅ Server Push Notifications:', data)

          resolve(data)

          // PERBAIKAN: Log detail data notifikasi
          console.log('New Sessions:', data.newSessions?.length || 0)
          console.log('New Orders:', data.newOrders?.length || 0)

          // Proses notifikasi sesi baru
          if (data.newSessions && data.newSessions.length > 0) {
            const tableIds = data.newSessions
              .map(session => session.table_id)
              .filter(Boolean)

            if (tableIds.length > 0) {
              console.log(
                'Processing new session notifications for tables:',
                tableIds
              )
              showSessionNotification(tableIds)
              playNotificationSound('newCustomerBell')
              markSessionsAsRead(tableIds)
            }
          }

          // PERBAIKAN: Proses notifikasi order baru dengan lebih teliti
          if (data.newOrders && data.newOrders.length > 0) {
            // Map data order dengan normalisasi nilai
            const orderData = data.newOrders
              .map(order => ({
                tableId: parseInt(order.table_id) || 0,
                customerName: order.customer_name || 'Unknown',
                total: parseFloat(order.total_amount || 0),
                itemsCount: parseInt(order.items_count || 0)
              }))
              .filter(order => order.tableId > 0) // Filter out invalid table IDs

            if (orderData.length > 0) {
              console.log('Processing new order notifications:', orderData)
              showEnhancedOrderNotification(orderData)
              playNotificationSound('orderSuccessBell')
              markOrdersAsRead(orderData.map(order => order.tableId))
            }
          }
        })
        .catch(error => {
          console.error('❌ Notification Check Error:', error)

          // Tambahkan retry untuk notification check
          notificationRetryCount++
          if (notificationRetryCount < MAX_RETRY_COUNT) {
            console.log(
              `Retrying notification check (${notificationRetryCount}/${MAX_RETRY_COUNT})...`
            )
          } else {
            console.error(
              `Maximum notification check retries (${MAX_RETRY_COUNT}) reached.`
            )
            notificationRetryCount = 0 // Reset counter

            // Tampilkan notification error hanya setelah beberapa kali retry
            Swal.fire({
              icon: 'error',
              title: 'Kesalahan Notifikasi',
              html: `
                <p>Gagal memperbarui notifikasi.</p>
                <details>
                  <summary>Error Details</summary>
                  <pre>${error.message}</pre>
                </details>
              `,
              confirmButtonText: 'OK'
            })
          }

          reject(error)
        })
        .finally(() => {
          console.groupEnd()
          // Jadwalkan pemeriksaan berikutnya
          setTimeout(checkServerPushNotifications, 5000)
        })
    } catch (setupError) {
      console.error('❌ Setup Error:', setupError)

      // Reset notification retry counter on setup error
      notificationRetryCount = 0

      // Jadwalkan ulang
      setTimeout(checkServerPushNotifications, 10000)
      reject(setupError)
    }
  })
}

$(document).ready(function () {
  console.log('Initializing improved status update handlers')

  // Hapus binding event lama untuk status-item terlebih dahulu
  $(document).off('click', '.status-item')

  $(document).off('click', '.update-status')

  // Tambahkan handler baru
  $(document).on('click', '.update-status', function (e) {
    e.preventDefault()
    e.stopPropagation()

    const tableId = $(this).data('tableId')
    if (tableId) {
      handleStatusUpdate(tableId)
    } else {
      console.error('Table ID tidak ditemukan pada tombol update status')
    }
    console.log('Update status clicked for table', tableId)

    // Dapatkan status saat ini
    const currentStatus = statusTable[tableId - 1]
    console.log('Current status:', currentStatus)

    // Daftar opsi status
    const statusOptions = [
      { value: 2, text: 'Sedang Diproses', class: 'btn-info' },
      { value: 3, text: 'Sudah Diantar', class: 'btn-success' },
      { value: 4, text: 'Selesai', class: 'btn-secondary' },
      { value: 5, text: 'Batalkan', class: 'btn-danger' }
    ]

    // Filter opsi status berdasarkan status saat ini
    const availableOptions = statusOptions.filter(option => {
      // Jika status sekarang 2, jangan tampilkan opsi 2 lagi
      if (currentStatus == 2 && option.value == 2) return false
      // Jika status sekarang 3, jangan tampilkan opsi 2 dan 3
      if (currentStatus == 3 && (option.value == 2 || option.value == 3))
        return false
      // Jika status sekarang 4, hanya tampilkan opsi 5 (batalkan)
      if (currentStatus == 4) return option.value == 5
      return true
    })

    // Buat HTML untuk opsi status
    let optionsHtml = ''
    availableOptions.forEach(option => {
      optionsHtml += `
        <button type="button" class="btn ${option.class} mb-2 w-100 status-option" data-status="${option.value}">
          ${option.text}
        </button>
      `
    })

    // Tampilkan dialog konfirmasi
    Swal.fire({
      title: `Update Status Meja ${tableId}`,
      html: `
        <div class="mb-3">Pilih status baru:</div>
        <div class="d-grid gap-2">
          ${optionsHtml}
        </div>
      `,
      showCancelButton: true,
      showConfirmButton: false,
      cancelButtonText: 'Batal',
      onRender: () => {
        // Tambahkan event listener untuk tombol status
        $('.status-option').on('click', function () {
          const newStatus = $(this).data('status')
          Swal.close()

          // Konfirmasi perubahan status
          Swal.fire({
            title: 'Konfirmasi',
            text: `Ubah status meja ${tableId} menjadi "${$(this).text()}"?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya',
            cancelButtonText: 'Tidak'
          }).then(result => {
            if (result.isConfirmed) {
              // Tampilkan loading
              Swal.fire({
                title: 'Memproses...',
                text: 'Mohon tunggu...',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                  Swal.showLoading()
                }
              })

              // Lakukan update status
              console.log('Updating table status:', tableId, 'to', newStatus)

              // Dapatkan parameter URL
              const currentUrl = window.location.href
              const url = new URL(currentUrl)
              const baseUrl = `${url.protocol}//${url.host}`
              const brand = $('#brand').val() || url.searchParams.get('brand')
              const outletId = url.pathname.split('/').pop()

              // Kirim request untuk update status
              fetch(`${baseUrl}/order/updateStatus`, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                  tableId: tableId,
                  outletId: outletId,
                  brand: brand,
                  status: parseInt(newStatus)
                })
              })
                .then(response => response.json())
                .then(data => {
                  console.log('Update status response:', data)
                  if (data.success) {
                    // Update UI
                    const idx = parseInt(tableId) - 1
                    statusTable[idx] = parseInt(newStatus)

                    // Update UI langsung
                    changeStatusState(tableId, newStatus)

                    // Simpan ke localStorage
                    saveTableStatusesToStorage()

                    // Tampilkan pesan sukses
                    Swal.fire({
                      title: 'Berhasil',
                      text: 'Status berhasil diupdate',
                      icon: 'success',
                      timer: 2000,
                      showConfirmButton: false
                    })

                    // Refresh status setelah update
                    setTimeout(() => {
                      pollTableStatus()
                    }, 500)
                  } else {
                    throw new Error(data.message || 'Gagal update status')
                  }
                })
                .catch(error => {
                  console.error('Error updating status:', error)
                  Swal.fire({
                    title: 'Error',
                    text:
                      error.message || 'Terjadi kesalahan saat update status',
                    icon: 'error'
                  })
                })
            }
          })
        })
      }
    })
  })
})

function handleStatusUpdate (tableId) {
  // Validasi input tableId
  if (!tableId) {
    console.error('Table ID tidak ditemukan')
    return
  }

  // Dapatkan status saat ini
  const currentStatus = statusTable[tableId - 1]
  console.log(`Status saat ini untuk meja ${tableId}: ${currentStatus}`)

  // Definisi status yang tersedia
  const statusOptions = [
    {
      value: 2,
      label: 'Sedang Diproses',
      description: 'Pesanan sedang diproses oleh dapur'
    },
    {
      value: 3,
      label: 'Sudah Diantar',
      description: 'Pesanan sudah diantar ke meja pelanggan'
    },
    {
      value: 4,
      label: 'Selesai',
      description: 'Pesanan telah selesai dan dibayar'
    },
    {
      value: 5,
      label: 'Dibatalkan',
      description: 'Pesanan dibatalkan'
    }
  ]

  // Filter status yang tersedia berdasarkan status saat ini
  const availableStatuses = statusOptions.filter(status => {
    // Izinkan semua status transisi untuk status awal (0 atau 1)
    if (['0', '1', 0, 1].includes(currentStatus)) return true

    // Hindari pengulangan status yang sama
    if (currentStatus === status.value) return false

    // Batasi transisi status berdasarkan urutan logis
    switch (parseInt(currentStatus)) {
      case 2: // Processing
        return status.value > 2
      case 3: // Served
        return status.value > 3
      case 4: // Completed
        return status.value === 5 // Hanya bisa dibatalkan
      case 5: // Cancelled
        return false // Tidak bisa diubah lagi
      default:
        return true
    }
  })

  // Tampilkan dialog konfirmasi dengan SweetAlert
  Swal.fire({
    title: `Update Status Meja ${tableId}`,
    html: `
            <div class="mb-3">Pilih status baru untuk pesanan:</div>
            <div class="d-grid gap-2">
                ${availableStatuses
                  .map(
                    status => `
                    <button type="button" 
                        class="btn btn-outline-primary status-option" 
                        data-status="${status.value}">
                        ${status.label}
                    </button>
                `
                  )
                  .join('')}
            </div>
        `,
    showCancelButton: true,
    showConfirmButton: false,
    cancelButtonText: 'Batal',
    onRender: function () {
      // Tambahkan event listener untuk setiap tombol status
      $('.status-option').on('click', function () {
        const newStatus = $(this).data('status')

        // Konfirmasi perubahan status
        Swal.fire({
          title: 'Konfirmasi Perubahan Status',
          html: `
                        <p>Anda akan mengubah status meja ${tableId} menjadi:</p>
                        <strong>${$(this).text()}</strong>
                    `,
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Ya, Ubah',
          cancelButtonText: 'Batal'
        }).then(result => {
          if (result.isConfirmed) {
            // Proses update status
            updateOrderStatus(tableId, newStatus)
          }
        })
      })
    }
  })
}

// Definisikan fungsi ini sebagai global window function untuk memastikan bisa diakses
window.handleStatusUpdate = handleStatusUpdate
