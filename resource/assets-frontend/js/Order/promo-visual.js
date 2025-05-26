let cachedPromoData = null

function initPromoVisuals () {
  console.group('🏷️ Initializing Promo Visuals')
  try {
    // Main setup with retry mechanism
    setupPromoVisuals()
      .then(() => {
        console.log('✅ Promo visuals initialized successfully')
        // Setup Product Modal integration - this will enhance product modals when they open
        setupProductModalIntegration()
      })
      .catch(error => {
        console.error('❌ Error initializing promo visuals:', error)
        // Retry once after a delay
        setTimeout(() => {
          console.log('🔄 Retrying promo visuals setup...')
          setupPromoVisuals().catch(e => console.error('❌ Retry failed:', e))
        }, 1500)
      })

    // Set up category change handler to refresh promo badges when category changes
    setupCategoryChangeHandler()
    // Set up search handler for dynamically filtered products
    setupSearchHandler()
  } catch (error) {
    console.error('❌ Critical error in promo visuals initialization:', error)
  } finally {
    console.groupEnd()
  }
}


async function setupPromoVisuals () {
  console.group('🏷️ Setting up Promo Visuals')
  try {
    // 1. Fetch active promos if not already cached
    if (!cachedPromoData) {
      const activePromos = await fetchActivePromos()
      if (!activePromos || !activePromos.length) {
        console.log('ℹ️ No active promos found')
        cachedPromoData = {
          productSpecificPromos: [],
          categorySpecificPromos: [],
          generalPromos: [],
          eligibleProductIds: new Set(),
          eligibleCategoryIds: new Set(),
          promoDetails: {}
        }
        return
      }

      console.log(`✅ Found ${activePromos.length} active promos`)

      // 2. Process and cache promo data
      cachedPromoData = processActivePromos(activePromos)
    }

    // 3. Apply promo visuals to product cards
    await applyPromoVisualsToProducts(cachedPromoData)

    // 4. Return cached data for other functions to use
    return cachedPromoData
  } catch (error) {
    console.error('❌ Error in setupPromoVisuals:', error)
    throw error
  } finally {
    console.groupEnd()
  }
}

async function fetchActivePromos () {
  console.log('🔍 Fetching active promos...')
  const params = new URLSearchParams(window.location.search)
  const brand = params.get('brand')

  if (!brand) {
    console.warn('⚠️ Brand parameter missing from URL')
    return []
  }

  try {
    // Try the dedicated promo endpoint first
    const response = await $.ajax({
      url: `${window.location.origin}/promo/BadgePromo/getActivePromos`,
      method: 'GET',
      data: { brand: brand },
      dataType: 'json',
      timeout: 5000, // 5 second timeout
      error: function (xhr, status, error) {
        console.warn('⚠️ Promo endpoint error:', {
          status: xhr.status,
          statusText: xhr.statusText,
          responseText: xhr.responseText,
          error: error
        })
      }
    })

    if (response.success && response.data) {
      console.log(
        `✅ Retrieved ${response.data.length} promos from dedicated endpoint`
      )
      return response.data
    } else {
      console.warn('⚠️ Promo API returned success=false or no data')
      // Fall back to suggestions endpoint
      return fetchPromoSuggestions()
    }
  } catch (error) {
    console.warn('⚠️ Error fetching from promo endpoint:', error)
    console.log('🔄 Falling back to promo suggestions endpoint...')
    // Fallback: try the suggestions endpoint
    return fetchPromoSuggestions()
  }
}

async function fetchPromoSuggestions () {
  const params = new URLSearchParams(window.location.search)
  const brand = params.get('brand')

  if (!brand) return []

  try {
    const response = await $.ajax({
      url: `${window.location.origin}/order/getPromoSuggestions`,
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({
        brand: brand,
        order_total: 100000, // High value to get all promos
        cart_details: []
      }),
      dataType: 'json',
      timeout: 5000
    })

    if (response.success && response.data && response.data.suggestions) {
      console.log(
        `✅ Retrieved ${response.data.suggestions.length} promos from suggestions endpoint`
      )
      return response.data.suggestions
    }

    return []
  } catch (error) {
    console.error('❌ Error fetching promo suggestions:', error)
    return []
  }
}

function processActivePromos (promos) {
  const result = {
    productSpecificPromos: [], // Promos for specific products
    categorySpecificPromos: [], // Promos for specific categories
    bogoPromos: [], // BOGO promos
    bundlingPromos: [], // Bundling promos
    eligibleProductIds: new Set(), // All eligible product IDs for regular promos
    eligibleCategoryIds: new Set(), // All eligible category IDs
    // Special sets for BOGO and Bundling products
    bogoEligibleProducts: new Set(),
    bundlingEligibleProducts: new Set(),
    promoDetails: {} // Full promo details by code
  }

  console.log('🔍 Processing active promos from database:', promos.length)

  promos.forEach(promo => {
    // Skip invalid promos
    if (!promo.code || !promo.type) {
      console.warn('⚠️ Skipping invalid promo:', promo)
      return
    }

    // Store promo details for later use
    result.promoDetails[promo.code] = promo

    // Handle promo based on type
    switch (promo.type) {
      case 'percentage':
      case 'nominal':
        // (Kode untuk percentage dan nominal tetap sama, tidak diubah)
        // Check if the promo is specific to certain products
        const hasProductSpecific =
          promo.additional_info &&
          promo.additional_info.promo_products &&
          Array.isArray(promo.additional_info.promo_products) &&
          promo.additional_info.promo_products.length > 0

        // Check if the promo is specific to certain categories
        const hasCategorySpecific =
          promo.additional_info &&
          promo.additional_info.promo_categories &&
          Array.isArray(promo.additional_info.promo_categories) &&
          promo.additional_info.promo_categories.length > 0

        if (hasProductSpecific) {
          console.log(
            `Promo ${promo.code} (${promo.type}) applies to specific products:`,
            promo.additional_info.promo_products
          )
          result.productSpecificPromos.push(promo)

          // Add product IDs to eligible set
          promo.additional_info.promo_products.forEach(productId => {
            result.eligibleProductIds.add(String(productId))
          })
        } else if (hasCategorySpecific) {
          console.log(
            `Promo ${promo.code} (${promo.type}) applies to specific categories:`,
            promo.additional_info.promo_categories
          )
          result.categorySpecificPromos.push(promo)

          // Add category IDs to eligible set
          promo.additional_info.promo_categories.forEach(categoryId => {
            result.eligibleCategoryIds.add(String(categoryId))
          })
        }
        break

      case 'bogo':
        console.log(`Processing BOGO promo: ${promo.code}`)
        result.bogoPromos.push(promo)

        // PERBAIKAN: Check if BOGO details are provided in additional_info with logging
        if (promo.additional_info && promo.additional_info.bogo_details) {
          const bogoDetails = promo.additional_info.bogo_details
          console.log('BOGO details found:', bogoDetails)

          // Process BOGO details if available
          bogoDetails.forEach(bogo => {
            // PERBAIKAN: Periksa property yang benar berdasarkan struktur database
            // Bisa menggunakan product_id, atau jika tidak ada, periksa fallback property lain
            const productId = bogo.product_id || bogo.buy_product_id

            if (productId) {
              result.bogoEligibleProducts.add(String(productId))
              console.log(
                `BOGO Promo ${promo.code} applies to product: ${productId}`
              )
            } else {
              console.warn(
                `BOGO item missing product ID in promo ${promo.code}:`,
                bogo
              )
            }
          })
        } else {
          // PERBAIKAN: Jika bogo_details tidak ada, coba cari dari root object
          console.warn(
            `BOGO Promo ${promo.code} does not have bogo_details in additional_info`
          )

          // Fallback: Check if we have direct BOGO data in the promo object
          if (promo.product_id) {
            result.bogoEligibleProducts.add(String(promo.product_id))
            console.log(
              `BOGO Promo ${promo.code} applies to product: ${promo.product_id} (fallback)`
            )
          }
        }
        break

      case 'bundling':
        console.log(`Processing Bundling promo: ${promo.code}`)
        result.bundlingPromos.push(promo)

        // PERBAIKAN: Check if bundling details are provided in additional_info with logging
        if (promo.additional_info && promo.additional_info.bundle_details) {
          const bundleDetails = promo.additional_info.bundle_details
          console.log('Bundle details found:', bundleDetails)

          // Process bundle details if available
          bundleDetails.forEach(bundle => {
            // PERBAIKAN: Gunakan nama properti yang benar sesuai dengan database
            // Periksa berbagai kemungkinan nama properti
            const product1Id = bundle.required_product_id1 || bundle.product1_id
            const product2Id = bundle.required_product_id2 || bundle.product2_id

            if (product1Id) {
              result.bundlingEligibleProducts.add(String(product1Id))
              console.log(`Adding bundling eligible product 1: ${product1Id}`)
            }

            if (product2Id) {
              result.bundlingEligibleProducts.add(String(product2Id))
              console.log(`Adding bundling eligible product 2: ${product2Id}`)
            }

            if (!product1Id && !product2Id) {
              console.warn(
                `Bundle missing product IDs in promo ${promo.code}:`,
                bundle
              )
            }
          })
        } else {
          // PERBAIKAN: Jika bundle_details tidak ada, cari dari root object
          console.warn(
            `Bundling Promo ${promo.code} does not have bundle_details in additional_info`
          )

          // Fallback: try to get products from direct properties if they exist
          if (promo.required_product_id1) {
            result.bundlingEligibleProducts.add(
              String(promo.required_product_id1)
            )
            console.log(
              `Bundling Promo ${promo.code} applies to product: ${promo.required_product_id1} (fallback)`
            )
          }

          if (promo.required_product_id2) {
            result.bundlingEligibleProducts.add(
              String(promo.required_product_id2)
            )
            console.log(
              `Bundling Promo ${promo.code} applies to product: ${promo.required_product_id2} (fallback)`
            )
          }
        }
        break

      default:
        console.log(`Promo ${promo.code} has unknown type: ${promo.type}`)
    }
  })

  console.log(
    '📊 Eligible products for regular promos:',
    Array.from(result.eligibleProductIds)
  )
  console.log('📊 Eligible categories:', Array.from(result.eligibleCategoryIds))
  console.log(
    '📊 BOGO eligible products:',
    Array.from(result.bogoEligibleProducts)
  )
  console.log(
    '📊 Bundling eligible products:',
    Array.from(result.bundlingEligibleProducts)
  )

  console.log('📊 Processed promo data:', {
    productSpecific: result.productSpecificPromos.length,
    categorySpecific: result.categorySpecificPromos.length,
    bogoPromos: result.bogoPromos.length,
    bundlingPromos: result.bundlingPromos.length,
    totalEligibleProducts: result.eligibleProductIds.size,
    totalEligibleCategories: result.eligibleCategoryIds.size,
    totalBogoProducts: result.bogoEligibleProducts.size,
    totalBundlingProducts: result.bundlingEligibleProducts.size
  })

  return result
}

// PERBAIKAN: Fungsi findBestPromoForProduct untuk memperbaiki penanganan BOGO dan Bundling
function findBestPromoForProduct (
  productId,
  categoryId,
  productPrice,
  promoData
) {
  // Track the best promo found
  let bestPromo = null
  let bestDiscount = 0

  console.log(
    `Checking promos for product ID: ${productId}, category ID: ${categoryId}`
  )

  // Check BOGO promos - highest priority if applicable
  if (
    promoData.bogoEligibleProducts &&
    promoData.bogoEligibleProducts.has(String(productId))
  ) {
    console.log(`Product ${productId} is eligible for BOGO promo`)

    promoData.bogoPromos.forEach(promo => {
      // For BOGO, we just use a nominal discount of 1 to ensure the badge shows
      const discount = 1
      if (discount > bestDiscount) {
        bestDiscount = discount
        bestPromo = promo
      }
    })
  }

  // Check bundling promos - also high priority
  if (
    !bestPromo &&
    promoData.bundlingEligibleProducts &&
    promoData.bundlingEligibleProducts.has(String(productId))
  ) {
    console.log(`Product ${productId} is eligible for bundling promo`)

    promoData.bundlingPromos.forEach(promo => {
      // For bundling, we just use a nominal discount of 1 to ensure the badge shows
      const discount = 1
      if (discount > bestDiscount) {
        bestDiscount = discount
        bestPromo = promo
      }
    })
  }

  // Proses untuk percentage dan nominal promo (tidak diubah)
  if (!bestPromo && promoData.eligibleProductIds.has(String(productId))) {
    promoData.productSpecificPromos.forEach(promo => {
      // Ensure this promo actually includes this specific product
      const productIds = promo.additional_info?.promo_products || []
      const stringProductIds = productIds.map(id => String(id))

      if (stringProductIds.includes(String(productId))) {
        console.log(
          `Product ${productId} is eligible for product-specific promo: ${promo.code}`
        )

        const discount = calculatePromoDiscount(promo, productPrice)
        if (discount > bestDiscount) {
          bestDiscount = discount
          bestPromo = promo
        }
      }
    })
  }

  // Check category-specific promos if no product-specific promo found
  if (
    !bestPromo &&
    categoryId &&
    promoData.eligibleCategoryIds.has(String(categoryId))
  ) {
    promoData.categorySpecificPromos.forEach(promo => {
      // Ensure this promo actually includes this specific category
      const categoryIds = promo.additional_info?.promo_categories || []
      const stringCategoryIds = categoryIds.map(id => String(id))

      if (stringCategoryIds.includes(String(categoryId))) {
        console.log(
          `Product ${productId} in category ${categoryId} is eligible for category-specific promo: ${promo.code}`
        )

        const discount = calculatePromoDiscount(promo, productPrice)
        if (discount > bestDiscount) {
          bestDiscount = discount
          bestPromo = promo
        }
      }
    })
  }

  // Return null if no promo applies or the discount is 0
  if (!bestPromo || bestDiscount <= 0) {
    return null
  }

  return {
    promo: bestPromo,
    discountAmount: bestDiscount
  }
}

async function applyPromoVisualsToProducts (promoData) {
  // Get all product cards
  const productCards = document.querySelectorAll('.product-card')
  if (!productCards.length) {
    console.warn('⚠️ No product cards found on page')
    return
  }

  console.log(
    `🔍 Processing ${productCards.length} product cards for promo badges`
  )

  // Process each product card
  productCards.forEach(card => {
    // Check if card already has a promo badge to avoid duplicates
    if (card.querySelector('.promo-badge')) {
      return
    }

    // Skip products without API connection as they can't be ordered
    const apiStatus = card
      .querySelector('.view-product')
      ?.getAttribute('data-api-status')
    if (apiStatus === 'not-connected') {
      return
    }

    // Skip out of stock products
    const stockBadge = card.querySelector('.badge')
    if (stockBadge && stockBadge.textContent.trim().toLowerCase() === 'habis') {
      return
    }

    // Get product data from card attributes
    const productId = card.getAttribute('data-product-id')
    const categoryId = card.getAttribute('data-category-id')

    if (!productId) {
      console.warn('⚠️ Product card missing product-id attribute')
      return
    }

    // Find the best applicable promo for this product
    const bestPromo = findBestPromoForProduct(
      productId,
      categoryId,
      getProductPrice(card),
      promoData
    )

    // Apply promo badge if a valid promo was found
    if (bestPromo && bestPromo.promo) {
      // Add has-promo class to the card for styling
      card.classList.add('has-promo')

      // Add the improved promo badge
      addImprovedPromoBadgeToCard(
        card,
        bestPromo.promo,
        bestPromo.discountAmount
      )
    }
  })
}

function addImprovedPromoBadgeToCard (card, promo, discountAmount) {
  const originalPrice = getProductPrice(card)
  const discountedPrice = Math.max(0, originalPrice - discountAmount)

  // Create badge container if it doesn't exist
  let badgeContainer = card.querySelector('.promo-badge-container')
  if (!badgeContainer) {
    badgeContainer = document.createElement('div')
    badgeContainer.className =
      'promo-badge-container position-absolute top-0 start-0 m-2 z-index-1'

    // Find image container to append the badge
    const imageContainer = card.querySelector('.position-relative')
    if (imageContainer) {
      imageContainer.appendChild(badgeContainer)
    }
  }

  // Configure badge based on promo type
  let badgeClass = ''
  let badgeText = ''
  let badgeIcon = ''

  switch (promo.type) {
    case 'percentage':
      badgeClass = 'bg-danger badge-percentage'
      badgeText = `${promo.value}%`
      badgeIcon = 'fa-percent'
      break
    case 'nominal':
      badgeClass = 'bg-primary badge-nominal'
      badgeText = `DISC`
      badgeIcon = 'fa-tag'
      break
    case 'bundling':
      badgeClass = 'bg-info badge-bundle'
      badgeText = 'BUNDLE'
      badgeIcon = 'fa-boxes-stacked'
      break
    case 'bogo':
      badgeClass = 'bg-success badge-bogo'
      badgeText = 'BOGO'
      badgeIcon = 'fa-gift'
      break
    default:
      badgeClass = 'bg-secondary'
      badgeText = 'PROMO'
      badgeIcon = 'fa-tags'
  }

  // Create badge HTML with improved styling
  const badgeHTML = `
	  <div class="promo-badge">
		<span class="badge ${badgeClass} d-flex align-items-center">
		  <i class="fa ${badgeIcon} me-1"></i> ${badgeText}
		</span>
	  </div>
	`

  // Add badge to container
  badgeContainer.innerHTML = badgeHTML

  // Update price display for discount promos
  if (
    (promo.type === 'percentage' || promo.type === 'nominal') &&
    discountedPrice < originalPrice
  ) {
    // Get price display element
    const priceDisplay = card.querySelector('.price-display')
    if (!priceDisplay) return

    // Add discount class
    priceDisplay.classList.add('has-discount')

    // Format prices
    const originalPriceInK = formatPriceInK(originalPrice)
    const discountedPriceInK = formatPriceInK(discountedPrice)

    // Clear existing price display
    priceDisplay.innerHTML = ''

    // Add discounted price
    const discountedPriceHTML = `
		<div class="discounted-price mb-1">
		  <span class="price-currency text-danger">Rp</span>
		  <span class="price-amount text-danger">${discountedPriceInK}</span>
		</div>
	  `

    // Add original price with strikethrough
    const originalPriceHTML = `
		<div class="original-price">
		  <span class="price-currency text-muted small">Rp</span>
		  <span class="price-amount text-muted text-decoration-line-through small">${originalPriceInK}</span>
		</div>
	  `

    // Update price display
    priceDisplay.innerHTML = discountedPriceHTML + originalPriceHTML

    // Add card-highlight class to card to highlight the promotion
    card.classList.add('has-promo')
  }
}

/**
 * Find the best promo for a specific product
 * Prioritizes product-specific promos over category-specific promos
 * Now properly handles BOGO and Bundling promos too
 */
function findBestPromoForProduct (
  productId,
  categoryId,
  productPrice,
  promoData
) {
  // Track the best promo found
  let bestPromo = null
  let bestDiscount = 0

  console.log(
    `Checking promos for product ID: ${productId}, category ID: ${categoryId}`
  )

  // Check BOGO promos - highest priority if applicable
  if (
    promoData.bogoEligibleProducts &&
    promoData.bogoEligibleProducts.has(String(productId))
  ) {
    console.log(`Product ${productId} is eligible for BOGO promo`)
    promoData.bogoPromos.forEach(promo => {
      // For BOGO, we just use a nominal discount of 1 to ensure the badge shows
      const discount = 1
      if (discount > bestDiscount) {
        bestDiscount = discount
        bestPromo = promo
      }
    })
  }

  // Check bundling promos - also high priority
  if (
    !bestPromo &&
    promoData.bundlingEligibleProducts &&
    promoData.bundlingEligibleProducts.has(String(productId))
  ) {
    console.log(`Product ${productId} is eligible for bundling promo`)
    promoData.bundlingPromos.forEach(promo => {
      // For bundling, we just use a nominal discount of 1 to ensure the badge shows
      const discount = 1
      if (discount > bestDiscount) {
        bestDiscount = discount
        bestPromo = promo
      }
    })
  }

  // Check product-specific promos
  if (!bestPromo && promoData.eligibleProductIds.has(String(productId))) {
    promoData.productSpecificPromos.forEach(promo => {
      // Ensure this promo actually includes this specific product
      const productIds = promo.additional_info?.promo_products || []
      const stringProductIds = productIds.map(id => String(id))

      if (stringProductIds.includes(String(productId))) {
        console.log(
          `Product ${productId} is eligible for product-specific promo: ${promo.code}`
        )
        const discount = calculatePromoDiscount(promo, productPrice)
        if (discount > bestDiscount) {
          bestDiscount = discount
          bestPromo = promo
        }
      }
    })
  }

  // Check category-specific promos if no product-specific promo found
  if (
    !bestPromo &&
    categoryId &&
    promoData.eligibleCategoryIds.has(String(categoryId))
  ) {
    promoData.categorySpecificPromos.forEach(promo => {
      // Ensure this promo actually includes this specific category
      const categoryIds = promo.additional_info?.promo_categories || []
      const stringCategoryIds = categoryIds.map(id => String(id))

      if (stringCategoryIds.includes(String(categoryId))) {
        console.log(
          `Product ${productId} in category ${categoryId} is eligible for category-specific promo: ${promo.code}`
        )
        const discount = calculatePromoDiscount(promo, productPrice)
        if (discount > bestDiscount) {
          bestDiscount = discount
          bestPromo = promo
        }
      }
    })
  }

  // Return null if no promo applies or the discount is 0
  if (!bestPromo || bestDiscount <= 0) {
    return null
  }

  return {
    promo: bestPromo,
    discountAmount: bestDiscount
  }
}

/**
 * Calculate discount amount based on promo type and product price
 * @param {Object} promo Promo object
 * @param {number} originalPrice Original product price
 * @returns {number} Discount amount
 */
function calculatePromoDiscount (promo, originalPrice) {
  if (!promo || originalPrice <= 0) return 0

  // Based on promo type, calculate the discount amount
  switch (promo.type) {
    case 'percentage':
      // Percentage discount
      let percentageDiscount = originalPrice * (promo.value / 100)

      // Apply maximum discount cap if present
      if (
        promo.additional_info?.max_discount &&
        percentageDiscount > promo.additional_info.max_discount
      ) {
        return promo.additional_info.max_discount
      }

      return percentageDiscount

    case 'nominal':
      // Fixed amount discount
      return Math.min(promo.value, originalPrice)

    case 'bundling':
    case 'bogo':
      // Show BOGO/Bundling promos but they don't reduce price directly
      // We return a small value (1) to ensure they show up in badges
      return 1

    default:
      return 0
  }
}

/**
 * Enhanced product modal integration
 */
function setupProductModalIntegration () {
  console.log('🔄 Setting up product modal promo integration')

  // Listen for modal show event
  $(document).on('show.bs.modal', '#productModal', function (event) {
    // Wait for modal content to be populated
    setTimeout(enhanceProductModal, 300)
  })
}

/**
 * Enhanced product modal with better promo display
 */
async function enhanceProductModal () {
  console.group('🔍 Enhancing Product Modal with Promo')
  try {
    // Get modal elements
    const modal = document.getElementById('productModal')
    if (!modal) {
      console.warn('⚠️ Product modal not found')
      return
    }

    // Check if we already enhanced this modal (avoid duplicates)
    if (modal.querySelector('.modal-promo-badge')) {
      console.log('ℹ️ Modal already enhanced with promo info')
      return
    }

    // Get product details from modal
    const productName = modal.querySelector('#modal-product-name')?.textContent
    const productImage = modal.querySelector('#modal-product-image')?.src
    const productPrice = parsePrice(
      modal.querySelector('#modal-product-price')?.textContent
    )

    // Try to find product ID from image URL or other attributes
    let productId = null
    let productElement = modal.querySelector('#modal-product-image')

    if (productElement && productElement.src) {
      // Try to extract product ID from image URL
      const matches = productElement.src.match(/\/product\/([^/]+?)(?:\?|$)/)
      if (matches && matches[1]) {
        // Look for corresponding product card
        const allCards = document.querySelectorAll('.product-card')
        for (const card of allCards) {
          if (card.querySelector('img')?.src === productElement.src) {
            productId = card.getAttribute('data-product-id')
            break
          }
        }
      }
    }

    if (!productId) {
      // Try to find from active button
      const activeButton = document.querySelector(
        '.product-card .view-product[aria-expanded="true"]'
      )
      if (activeButton) {
        productId = activeButton
          .closest('.product-card')
          ?.getAttribute('data-product-id')
      }
    }

    if (!productId || !productPrice) {
      console.warn('⚠️ Cannot identify product ID or price in modal')
      return
    }

    console.log(
      `🔍 Identified product in modal: ${productName} (ID: ${productId})`
    )

    // Ensure we have promo data
    if (!cachedPromoData) {
      await setupPromoVisuals()
    }

    if (!cachedPromoData) {
      console.warn('⚠️ No promo data available')
      return
    }

    // Get category ID from matching product card
    let categoryId = null
    const productCards = document.querySelectorAll('.product-card')
    for (const card of productCards) {
      if (card.getAttribute('data-product-id') === productId) {
        categoryId = card.getAttribute('data-category-id')
        break
      }
    }

    // Find best promo for this product
    const bestPromo = findBestPromoForProduct(
      productId,
      categoryId,
      productPrice,
      cachedPromoData
    )

    if (!bestPromo || !bestPromo.promo) {
      console.log('ℹ️ No applicable promo found for modal product')
      return
    }

    console.log(`✅ Found applicable promo for modal: ${bestPromo.promo.code}`)

    // Add promo badge to modal - IMPROVED VERSION
    addImprovedPromoToProductModal(
      modal,
      bestPromo.promo,
      bestPromo.discountAmount,
      productPrice
    )
  } catch (error) {
    console.error('❌ Error enhancing product modal:', error)
  } finally {
    console.groupEnd()
  }
}

function addImprovedPromoToProductModal (
  modal,
  promo,
  discountAmount,
  originalPrice
) {
  const discountedPrice = Math.max(0, originalPrice - discountAmount)

  // Check if this is a price-affecting promo or special promo type
  const isPriceAffectingPromo =
    (promo.type === 'percentage' || promo.type === 'nominal') &&
    discountedPrice < originalPrice

  // Only add badge to image for non-zero discounts or special promo types
  if (isPriceAffectingPromo || ['bundling', 'bogo'].includes(promo.type)) {
    // Create a clean, simple badge design
    const badgeEl = document.createElement('div')
    badgeEl.className = 'modal-promo-badge position-absolute top-0 end-0 m-2'

    // Set badge style based on promo type
    let badgeClass = ''
    let badgeText = ''

    switch (promo.type) {
      case 'percentage':
        badgeClass = 'bg-danger'
        badgeText = `${promo.value}% OFF`
        break
      case 'nominal':
        badgeClass = 'bg-danger'
        badgeText = `DISKON`
        break
      case 'bundling':
        badgeClass = 'bg-info'
        badgeText = 'BUNDLE'
        break
      case 'bogo':
        badgeClass = 'bg-success'
        badgeText = 'BOGO'
        break
    }

    badgeEl.innerHTML = `<span class="badge ${badgeClass} px-2 py-1">${badgeText}</span>`

    // Add badge to image container
    const imageContainer = modal.querySelector('.position-relative')
    if (imageContainer) {
      imageContainer.appendChild(badgeEl)
    }
  }

  // Update price display for price-affecting promos
  if (isPriceAffectingPromo) {
    const priceElement = modal.querySelector('#modal-product-price')
    if (priceElement) {
      // Format prices nicely
      const formattedOriginalPrice = formatCurrency(originalPrice)
      const formattedDiscountedPrice = formatCurrency(discountedPrice)

      // Create a clean price display with strikethrough for original price
      priceElement.innerHTML = `
		  <div class="d-flex align-items-center">
			<span class="fs-4 fw-bold text-danger me-2">${formattedDiscountedPrice}</span>
			<span class="text-decoration-line-through text-muted">${formattedOriginalPrice}</span>
		  </div>
		`
    }
  }

  // Add clean promo info box
  if (promo.code) {
    const promoInfoHtml = `
		<div class="alert alert-light border mt-3">
		  <div class="d-flex align-items-center">
			<div class="flex-shrink-0">
			  <i class="fa fa-tags fs-4 me-3 text-${
          promo.type === 'percentage' || promo.type === 'nominal'
            ? 'danger'
            : promo.type === 'bundling'
            ? 'info'
            : 'success'
        }"></i>
			</div>
			<div class="flex-grow-1">
			  <div class="d-flex justify-content-between align-items-center">
				<strong>Kode Promo: ${promo.code}</strong>
				${
          isPriceAffectingPromo
            ? `<span class="badge bg-danger ms-2">Hemat ${formatCurrency(
                discountAmount
              )}</span>`
            : ''
        }
			  </div>
			  ${getSimplifiedPromoDescription(promo)}
			</div>
		  </div>
		</div>
	  `

    // Add to an appropriate location in modal
    const subtotalSection = modal
      .querySelector('#product-subtotal')
      ?.closest('.d-flex')
    if (subtotalSection) {
      // Insert before the subtotal section
      subtotalSection.insertAdjacentHTML('beforebegin', promoInfoHtml)

      // Update subtotal value for price-affecting promos
      if (isPriceAffectingPromo) {
        const subtotalElement = modal.querySelector('#product-subtotal')
        if (subtotalElement) {
          // Get current quantity
          const quantityInput = modal.querySelector('.product-qty')
          const quantity = quantityInput
            ? parseInt(quantityInput.value) || 1
            : 1

          // Update subtotal (based on discounted price)
          subtotalElement.textContent = formatCurrency(
            discountedPrice * quantity
          )

          // Update the update function to use discounted price
          const originalUpdateSubtotal = window.ProductModal?.updateSubtotal
          if (
            originalUpdateSubtotal &&
            typeof originalUpdateSubtotal === 'function'
          ) {
            window.ProductModal.updateSubtotal = function () {
              const quantity = this.state.quantity || 1
              const subtotalElement =
                document.querySelector('#product-subtotal')
              if (subtotalElement) {
                subtotalElement.textContent = formatCurrency(
                  discountedPrice * quantity
                )
              }
            }
          }
        }
      }
    }
  }
}

/**
 * Get a simplified description of the promo for the modal view
 */
function getSimplifiedPromoDescription (promo) {
  let description = ''

  // Simple description based on promo type
  switch (promo.type) {
    case 'percentage':
      description = `Diskon ${promo.value}% untuk pembelian produk ini`
      break
    case 'nominal':
      description = `Potongan harga ${formatCurrency(promo.value)}`
      break
    case 'bundling':
      description = `Beli produk paket bundle untuk dapat produk gratis`
      break
    case 'bogo':
      description = `Beli produk ini, dapatkan 1 gratis`
      break
    default:
      description = promo.description || 'Promo spesial'
  }

  return `<p class="mb-0 small text-muted">${description}</p>`
}

function setupCategoryChangeHandler () {
  const categorySelect = document.getElementById('category-select')
  if (categorySelect) {
    // Remove existing handlers to prevent duplicates
    $(categorySelect).off('change.promoVisuals')

    // Add new handler
    $(categorySelect).on('change.promoVisuals', function () {
      // Wait for filter to complete
      setTimeout(() => {
        console.log('🔄 Category changed, refreshing promo visuals')
        // Clear all existing promo badges before adding new ones
        clearExistingPromoBadges()
        // Apply promos to newly filtered products
        applyPromoVisualsToProducts(cachedPromoData)
      }, 300)
    })
  }
}

/**
 * Setup handler for product search
 */
function setupSearchHandler () {
  const searchInput = document.getElementById('product-search')
  if (searchInput) {
    // Remove existing handlers to prevent duplicates
    $(searchInput).off('input.promoVisuals')

    // Add debounced handler for search input
    $(searchInput).on(
      'input.promoVisuals',
      debounce(function () {
        console.log('🔍 Product search changed, refreshing promo visuals')
        // Wait for filter to complete
        setTimeout(() => {
          clearExistingPromoBadges()
          applyPromoVisualsToProducts(cachedPromoData)
        }, 400)
      }, 300)
    )
  }
}

/**
 * Clear all existing promo badges and price modifications
 */
function clearExistingPromoBadges () {
  // Remove promo badges
  document
    .querySelectorAll('.promo-badge, .promo-badge-container')
    .forEach(badge => {
      badge.remove()
    })

  // Reset modified price displays
  document
    .querySelectorAll('.price-display.has-discount')
    .forEach(priceDisplay => {
      // Remove has-discount class
      priceDisplay.classList.remove('has-discount')

      // Remove discounted price
      const discountedPrice = priceDisplay.querySelector('.discounted-price')
      if (discountedPrice) {
        discountedPrice.remove()
      }

      // Restore original price
      const originalPrice = priceDisplay.querySelector('.price-amount')
      if (originalPrice) {
        originalPrice.classList.remove(
          'text-decoration-line-through',
          'text-muted',
          'fs-6'
        )

        // Unwrap from container if needed
        const wrapper = originalPrice.closest('.original-price-wrapper')
        if (wrapper && wrapper.parentNode) {
          wrapper.parentNode.insertBefore(originalPrice, wrapper)
          wrapper.remove()
        }
      }
    })

  // Remove has-promo class from product cards
  document.querySelectorAll('.product-card.has-promo').forEach(card => {
    card.classList.remove('has-promo')
  })
}

/**
 * Helper function to create a debounced function
 * @param {Function} func Function to debounce
 * @param {number} wait Wait time in milliseconds
 * @returns {Function} Debounced function
 */
function debounce (func, wait) {
  let timeout
  return function (...args) {
    clearTimeout(timeout)
    timeout = setTimeout(() => func.apply(this, args), wait)
  }
}

/**
 * Parse price from formatted string
 * @param {string} priceString Formatted price string
 * @returns {number} Price as number
 */
function parsePrice (priceString) {
  if (!priceString) return 0

  // Remove currency symbols, dots, and other non-numeric characters
  const numericString = priceString.replace(/[^\d,-]/g, '').replace(',', '.')
  const price = parseFloat(numericString)

  return isNaN(price) ? 0 : price
}

/**
 * Get product price from card
 * @param {Element} card Product card element
 * @returns {number} Product price
 */
function getProductPrice (card) {
  // Find price element in the card
  const priceElement = card.querySelector('.price-amount')
  if (!priceElement) return 0

  // Parse price from "XXK" format to numeric value
  const priceText = priceElement.innerText.replace(/\s/g, '')
  let priceValue = 0

  if (priceText.includes('K')) {
    // Handle "XXK" format
    priceValue = parseFloat(priceText.replace('K', '')) * 1000
  } else {
    // Handle numeric format
    priceValue = parseFloat(priceText.replace(/[^\d.-]/g, ''))
  }

  return isNaN(priceValue) ? 0 : priceValue
}

/**
 * Format price in currency format
 * @param {number} amount Amount to format
 * @returns {string} Formatted currency string
 */
function formatCurrency (amount) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount)
}

/**
 * Format price in K (thousands) format
 * @param {number} amount Amount to format
 * @returns {string} Formatted price in K format
 */
function formatPriceInK (amount) {
  if (amount >= 1000) {
    return Math.floor(amount / 1000) + 'K'
  }
  return amount.toString()
}

/**
 * Add CSS styles for promo badges
 */
function addPromoBadgeStyles () {
  // Check if styles already exist
  if (document.getElementById('promo-badge-styles')) return

  // Create style element
  const styleElement = document.createElement('style')
  styleElement.id = 'promo-badge-styles'

  // Add CSS
  styleElement.textContent = `
    /* Promo Badge Styles */
    .promo-badge {
      position: relative;
      z-index: 5;
      transition: transform 0.3s ease;
    }
    
    .promo-badge:hover {
      transform: scale(1.05);
    }
    
    .promo-badge .badge {
      font-size: 0.75rem;
      font-weight: 600;
      padding: 0.35rem 0.65rem;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      border-radius: 4px;
    }
    
    /* Promo type-specific styles */
    .badge-percentage {
      background: linear-gradient(135deg, #dc3545, #ff6767) !important;
    }
    
    .badge-nominal {
      background: linear-gradient(135deg, #007bff, #3a9cff) !important;
    }
    
    .badge-bundle {
      background: linear-gradient(135deg, #17a2b8, #4dcedf) !important;
    }
    
    .badge-bogo {
      background: linear-gradient(135deg, #28a745, #5bd778) !important;
    }
    
    /* Discounted price display */
    .price-display.has-discount {
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    
    .discounted-price {
      font-weight: 600;
      animation: fadeIn 0.3s ease-out;
    }
    
    .original-price {
      opacity: 0.7;
      animation: fadeIn 0.3s ease-out;
    }
    
    /* Modal promo info */
    .modal-promo-badge {
      z-index: 5;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(5px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }
    
    /* Animation for newly added promo badges */
    .promo-badge-container {
      animation: fadeIn 0.3s ease-out;
    }
  `

  // Add to document head
  document.head.appendChild(styleElement)
}

/**
 * Initialize enhanced product display
 */
function initEnhancedProductDisplay () {
  console.log('Initializing enhanced product display...')

  // Apply enhanced styling to product cards
  enhanceProductCards()

  // Observe DOM changes to handle dynamically loaded products
  observeProductChanges()
}

/**
 * Apply enhanced styling to product cards
 */
function enhanceProductCards () {
  // Select all product cards
  const productCards = document.querySelectorAll('.product-card')

  productCards.forEach(card => {
    // Get product data
    const productName = card.getAttribute('data-product-name')
    const productDesc = card.getAttribute('data-product-desc')
    const productId = card.getAttribute('data-product-id')
    const categoryId = card.getAttribute('data-category-id')
    const apiStatus = card.getAttribute('data-api-status')

    // Get product elements
    const cardElement = card.querySelector('.card')
    const cardBody = card.querySelector('.card-body')
    const priceDisplay = card.querySelector('.price-display')

    if (!cardElement || !cardBody || !priceDisplay) return

    // Add enhanced styling to card
    cardElement.classList.add('product-card-enhanced')

    // Ensure consistent height for product title
    const titleElement = card.querySelector('.card-title')
    if (titleElement) {
      titleElement.classList.add('product-title-enhanced')
      // Ensure the title is not too long and consistent in height
      if (titleElement.textContent.length > 30) {
        titleElement.setAttribute('title', titleElement.textContent)
        titleElement.classList.add('text-truncate-2-lines')
      }
    }

    // Check if this product has a connected API (can be ordered)
    if (apiStatus === 'connected') {
      card.classList.add('product-card-clickable')
    } else {
      card.classList.add('product-card-disabled')
    }
  })
}

/**
 * Update price display for a product with promo information
 * @param {Element} productCard - The product card element
 * @param {Object} promoInfo - Promotion information object
 */
function updatePriceWithPromo (productCard, promoInfo) {
  const priceDisplay = productCard.querySelector('.price-display')
  if (!priceDisplay) return

  // Get original price from the product card
  const priceAmount = priceDisplay.querySelector('.price-amount')
  if (!priceAmount) return

  // Extract the price value (converting from "XXK" format if needed)
  const originalPriceText = priceAmount.textContent.trim()
  let originalPrice = 0

  if (originalPriceText.includes('K')) {
    // Handle "XXK" format
    originalPrice = parseFloat(originalPriceText.replace('K', '')) * 1000
  } else {
    // Handle numeric format
    originalPrice = parseFloat(originalPriceText.replace(/[^\d.-]/g, ''))
  }

  if (isNaN(originalPrice)) return

  // Calculate discounted price
  let discountedPrice = originalPrice

  if (promoInfo.type === 'percentage') {
    // Percentage discount
    const discountPercentage = promoInfo.value || 0
    discountedPrice = originalPrice * (1 - discountPercentage / 100)
  } else if (promoInfo.type === 'nominal') {
    // Nominal discount
    const discountAmount = promoInfo.discount || 0
    discountedPrice = Math.max(0, originalPrice - discountAmount)
  }

  // Format prices for display
  const formattedOriginalPrice = formatPriceInK(originalPrice)
  const formattedDiscountedPrice = formatPriceInK(discountedPrice)

  // Add discount class
  priceDisplay.classList.add('has-discount')

  // Clear existing price display
  priceDisplay.innerHTML = ''

  // Create new price elements
  const discountedPriceHTML = `
	  <div class="discounted-price mb-1">
		<span class="price-currency text-danger">Rp</span>
		<span class="price-amount text-danger">${formattedDiscountedPrice}</span>
	  </div>
	`

  const originalPriceHTML = `
	  <div class="original-price">
		<span class="price-currency text-muted small">Rp</span>
		<span class="price-amount text-muted text-decoration-line-through small">${formattedOriginalPrice}</span>
	  </div>
	`

  // Update price display
  priceDisplay.innerHTML = discountedPriceHTML + originalPriceHTML

  // Add a promo badge if not already present
  if (!productCard.querySelector('.promo-badge')) {
    addPromoBadgeToCard(productCard, promoInfo)
  }
}

function addPromoBadgeToCard (card, promoInfo) {
  // Create badge container if it doesn't exist
  let badgeContainer = card.querySelector('.promo-badge-container')
  if (!badgeContainer) {
    badgeContainer = document.createElement('div')
    badgeContainer.className =
      'promo-badge-container position-absolute top-0 start-0 m-2 z-index-1'

    // Find image container to append the badge
    const imageContainer = card.querySelector('.position-relative')
    if (imageContainer) {
      imageContainer.appendChild(badgeContainer)
    }
  }

  // Configure badge based on promo type
  let badgeClass = ''
  let badgeText = ''
  let badgeIcon = ''

  switch (promoInfo.type) {
    case 'percentage':
      badgeClass = 'bg-danger badge-percentage'
      badgeText = `${promoInfo.value}%`
      badgeIcon = 'fa-percent'
      break
    case 'nominal':
      badgeClass = 'bg-primary badge-nominal'
      badgeText = `DISC`
      badgeIcon = 'fa-tag'
      break
    case 'bundling':
      badgeClass = 'bg-info badge-bundle'
      badgeText = 'BUNDLE'
      badgeIcon = 'fa-boxes-stacked'
      break
    case 'bogo':
      badgeClass = 'bg-success badge-bogo'
      badgeText = 'BOGO'
      badgeIcon = 'fa-gift'
      break
    default:
      badgeClass = 'bg-secondary'
      badgeText = 'PROMO'
      badgeIcon = 'fa-tags'
  }

  // Create badge HTML with improved styling
  const badgeHTML = `
	  <div class="promo-badge">
		<span class="badge ${badgeClass} d-flex align-items-center">
		  <i class="fa ${badgeIcon} me-1"></i> ${badgeText}
		</span>
	  </div>
	`

  // Add badge to container
  badgeContainer.innerHTML = badgeHTML
}

/**
 * Format price in K (thousands) format
 * @param {number} amount - Amount to format
 * @returns {string} Formatted price
 */
function formatPriceInK (amount) {
  if (amount >= 1000) {
    return Math.floor(amount / 1000) + 'K'
  }
  return amount.toString()
}

/**
 * Setup event listeners for category filter and search
 */
function setupDisplayEventListeners () {
  // Listen for category changes
  const categorySelect = document.getElementById('category-select')
  if (categorySelect) {
    categorySelect.addEventListener('change', function () {
      // Wait for filter to complete before re-applying enhancements
      setTimeout(() => {
        enhanceProductCards()
      }, 300)
    })
  }

  // Listen for search input
  const searchInput = document.getElementById('product-search')
  if (searchInput) {
    searchInput.addEventListener(
      'input',
      debounce(function () {
        // Wait for search filter to complete
        setTimeout(() => {
          enhanceProductCards()
        }, 300)
      }, 300)
    )
  }
}

/**
 * Observe DOM changes to handle dynamically loaded products
 */
function observeProductChanges () {
  // Create an observer instance
  const observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
        // Check if new product cards were added
        const productContainer = document.getElementById('product-listing')
        if (productContainer && mutation.target.contains(productContainer)) {
          enhanceProductCards()
        }
      }
    })
  })

  // Start observing the document with the configured parameters
  observer.observe(document.body, { childList: true, subtree: true })
}

/**
 * Helper function to create a debounced function
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @returns {Function} Debounced function
 */
function debounce (func, wait) {
  let timeout
  return function (...args) {
    clearTimeout(timeout)
    timeout = setTimeout(() => func.apply(this, args), wait)
  }
}

// Integrate with existing promo visual system
if (typeof window.initPromoVisuals === 'function') {
  // Original initialization is kept, we're just enhancing it
  const originalInitPromoVisuals = window.initPromoVisuals

  window.initPromoVisuals = function () {
    // Call the original initialization
    originalInitPromoVisuals.apply(this, arguments)

    // Apply our enhancements after original init
    setTimeout(() => {
      enhanceProductCards()
    }, 500)
  }

  // Also enhance when session is activated
  document.addEventListener('sessionActivated', function () {
    setTimeout(() => {
      enhanceProductCards()
    }, 1000)
  })
}

// Event listeners
document.addEventListener('DOMContentLoaded', function () {
  // Add promo badge styles
  addPromoBadgeStyles()
  // Initialize promo visuals when DOM is loaded
  initPromoVisuals()

  initEnhancedProductDisplay()

  setupDisplayEventListeners()
})

// Re-initialize when session is activated
document.addEventListener('sessionActivated', function () {
  console.log('🔄 Session activated, re-initializing promo visuals')
  setTimeout(initPromoVisuals, 1000)
})

// Initialize on first load
initPromoVisuals()
