// order.js
function enlargeCatalogueImage () {
    let images = document.getElementsByClassName('e-catalogue-image')
    for (let image of images) {
      if (image.parentNode) {
        image.classList.remove('img-fluid')
        image.classList.remove('custom-block-ek-image')
        let parentElement = image.parentNode
        parentElement.parentNode.insertBefore(image, parentElement)
        parentElement.remove()
      }
    }
  }
  
  function transformCenterElement () {
    const marginElement = document.getElementsByClassName('custom-center-element')
    for (let element of marginElement) {
      element.style.margin = '0'
    }
  }
  
  function showHiddenElement (elementId) {
    const element = document.getElementById(elementId)
  }
  
  let isStartSession = false
  let media = window.matchMedia('(max-width: 400px)')
  
  if (media.matches) {
    enlargeCatalogueImage()
    transformCenterElement()
  }
  
  document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const addToCart = document.getElementById('add-to-cart');
    const categorySelect = document.getElementById('category-select');
    let dProductModal = new bootstrap.Modal('#detail-product', { keyboard: false });
    let cartModal = new bootstrap.Modal('#cart-modal', { keyboard: false });
    let isStartSession = false;
  
    // Fetch current session
    $.ajax({
      type: 'GET',
      url: window.location.origin + '/order/session?' + params.toString(),
      dataType: 'json'
    })
      .done(function (response, textStatus, jqXHR) {
        if (!response.data) {
          $('#identity-page').removeAttr('hidden')
          return
        }
        // Switch page
        $('#customer-name').text(response.data.name)
        $('#identity-page').attr('hidden', true)
        $('#order-page').removeAttr('hidden')
        isStartSession = true
  
        // Initialize package features
        addPackageButtons()
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        console.log(textStatus)
      })
  
    let brand = 'kopitiam'
  
    showHiddenElement('order-page')
  
    if (params.has('category')) {
      const categoryId = params.get('category')
      categorySelect.value = categoryId
    }
  
    // Category change handler
    categorySelect.addEventListener('change', async function() {
        const selectedValue = this.value;
        
        try {
            // Show loading
            Swal.fire({
                title: 'Loading...',
                text: 'Memuat produk',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const response = await $.ajax({
                url: window.location.origin + '/order',
                data: {
                    outletId: params.get('outletId'),
                    tableId: params.get('tableId'),
                    brand: params.get('brand'),
                    category: selectedValue !== 'all' ? selectedValue : null
                },
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.success) {
                // Update product display
                const container = $('#bakery-tab-pane .container .row');
                container.empty();

                if (response.data.products && response.data.products.length > 0) {
                    let categoryID = 0;
                    response.data.products.forEach(prod => {
                        if (categoryID !== prod.cat_id) {
                            categoryID = prod.cat_id;
                            container.append(`
                                <div class="col-12 pt-2 rounded" style="background-color: #ff9924">
                                    <div class="d-flex justify-content-center">
                                        <h2 style="color: #fff">${prod.cat_name}</h2>
                                    </div>
                                </div>
                            `);
                        }

                        container.append(`
                            <div class="col-6 col-sm-4 pt-3 product-image-btn" data-product-id="${prod.product_id}">
                                <div class="d-flex justify-content-center image-catalogue">
                                    <img src="${window.location.origin}/resource/assets-frontend/dist/product/${prod.product_pict}"
                                        data-product-id="${prod.product_id}"
                                        class="img-fluid custom-block-ek-image e-catalogue-image"
                                        alt="Image-${prod.product_name.toUpperCase()}"
                                        style="max-height: 100%; filter: drop-shadow(5px 5px 10px #000000);" />
                                    ${prod.current_stock === 0 ? `
                                        <img src="${window.location.origin}/resource/assets-frontend/dist/katalog/soldout.png"
                                            data-product-id="${prod.product_id}"
                                            class="img-fluid custom-block-ek-image"
                                            alt="Soldout-${prod.product_name.toUpperCase()}"
                                            style="max-height: 3em; max-width: 4em; filter: drop-shadow(5px 5px 10px #000000); position: absolute;" />
                                    ` : ''}
                                </div>
                                <div class="pt-2 text-center fw-bold custom-catalogue-product-name">
                                    ${prod.product_name.toUpperCase()}
                                </div>
                                <hr style="border: 0.1rem solid #725535; opacity: 100; margin: 0.25em;" />
                                <sup><span class="rounded-circle custom-catalogue-dotrp">Rp</span></sup>
                                <span class="custom-catalogue-text">${prod.price_catalogue} <sup>K</sup></span>
                            </div>
                        `);
                    });
                }

                // Reinitialize product click events
                $('.product-image-btn').click(function() {
                    const productId = $(this).data('product-id');
                    $.ajax({
                        type: 'GET',
                        url: `${window.location.origin}/apis/product/detail/${productId}`,
                        dataType: 'json'
                    }).done(function(response) {
                        if (parseInt(response.data.detail.stock) === 0) {
                            $('#add-to-cart').attr('hidden', true);
                        }
                        $('#detail-product-label').text(response.data.detail.product_name);
                        $('#detail-product-description').text(response.data.detail.product_desc);
                        $('#detail-product-image').attr('src', 
                            `${window.location.origin}/resource/assets-frontend/dist/product/${response.data.detail.product_pict}`);
                        $('#product-id').text(response.data.detail.product_id);
                        dProductModal.show();
                    });
                });

                // Update URL without reload
                const url = new URL(window.location);
                if (selectedValue !== 'all') {
                    url.searchParams.set('category', selectedValue);
                } else {
                    url.searchParams.delete('category');
                }
                window.history.pushState({}, '', url);
            }

            Swal.close();
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Gagal memuat produk',
                icon: 'error'
            });
        }
    });
  
    document
      .getElementById('detail-product')
      .addEventListener('hide.bs.modal', function (event) {
        $('#add-to-cart').removeAttr('hidden')
      })
  
    $('#add-to-cart').on('click', function () {
      $.ajax({
        type: 'POST',
        url: window.location.origin + '/order',
        data: JSON.stringify({
          outletId: params.get('outletId'),
          tableId: params.get('tableId'),
          brand: params.get('brand'),
          productId: document.getElementById('product-id').innerText,
          action: 'addProduct'
        }),
        dataType: 'json'
      })
        .done(function (response, textStatus, jqXHR) {
          dProductModal.hide()
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
          if (jqXHR.status === 422) {
            Swal.fire({
              title: 'Stok Habis',
              // text: jqXHR.responseText,
              icon: 'error'
            }).then(result => {
              if (result.isConfirmed) {
                dProductModal.hide()
              }
            })
          }
        })
    })
  
    $('#show-cart').on('click', function () {
      cartModal.show()
    })
  
    // Cart state management
    let cartState = {
      items: [],
      packages: [],
      total: 0
    }
  
    // Update cart modal content
    function updateCartContent (cartData) {

        if (!Array.isArray(cartData)) {
        console.error('Invalid cart data:', cartData);
        return;
    }
    
      const container = document.getElementById('container-cart')
      container.innerHTML = ''
  
      // Sort items into regular items and packages
      const regularItems = cartData.filter(item => !item.parent_id)
      const packages = cartData.filter(
        item => item.package_items && item.package_items.length > 0
      )
  
      // Add regular items
      regularItems.forEach(item => {
        if (!item.package_items) {
          addRegularItemToCart(item)
        }
      })
  
      // Add packages
      packages.forEach(pkg => {
        addPackageToCart(pkg)
      })
  
      updateCartSummary()
    }
  
    // Add regular item to cart
    function addRegularItemToCart (item) {
      const template = document.getElementById('regular-item-template')
      const itemElement = template.cloneNode(true)
      itemElement.hidden = false
  
      // Set item details
      itemElement.querySelector(
        '.cart-item-image'
      ).src = `${window.location.origin}/resource/assets-frontend/dist/product/${item.product_pict}`
      itemElement.querySelector('.cart-item-name').textContent = item.product_name
      itemElement.querySelector(
        '.cart-item-price'
      ).textContent = `Rp ${item.price_catalogue}`
      itemElement.querySelector('.cart-item-stock').textContent =
        item.product_stock
  
      const quantityInput = itemElement.querySelector('.action-number')
      quantityInput.value = item.product_count
      quantityInput.dataset.productId = item.product_id
  
      const noteTextarea = itemElement.querySelector('.notes')
      noteTextarea.value = item.notes || ''
      noteTextarea.dataset.productId = item.product_id
  
      // Set action buttons
      const minusBtn = itemElement.querySelector('.action-minus')
      minusBtn.addEventListener('click', function () {
        let productCount = itemElement.querySelector('.action-number')
        if (parseInt(productCount.value) === 0) return false
  
        $.ajax({
          type: 'DELETE',
          url: window.location.origin + '/order',
          data: JSON.stringify({
            outletId: params.get('outletId'),
            brand: params.get('brand'),
            tableId: params.get('tableId'),
            productId: item.product_id,
            count: 1
          }),
          dataType: 'json'
        }).done(function () {
          productCount.value = parseInt(productCount.value) - 1
          updateCartSummary()
        })
      })
  
      const plusBtn = itemElement.querySelector('.action-plus')
      plusBtn.addEventListener('click', function () {
        let productCount = itemElement.querySelector('.action-number')
        if (parseInt(productCount.value) >= item.product_stock) return false
  
        $.ajax({
          method: 'POST',
          url: window.location.origin + '/order',
          contentType: 'application/json',
          data: JSON.stringify({
            outletId: params.get('outletId'),
            tableId: params.get('tableId'),
            brand: params.get('brand'),
            productId: item.product_id,
            action: 'addProduct'
          })
        }).done(function () {
          productCount.value = parseInt(productCount.value) + 1
          updateCartSummary()
        })
      })
  
      const notes = itemElement.querySelector('.notes')
      notes.addEventListener('change', function () {
        $.ajax({
          method: 'POST',
          url: window.location.origin + '/order',
          contentType: 'application/json',
          data: JSON.stringify({
            outletId: params.get('outletId'),
            tableId: params.get('tableId'),
            brand: params.get('brand'),
            productId: item.product_id,
            action: 'addNote',
            notes: this.value
          })
        })
      })
  
      document.getElementById('container-cart').appendChild(itemElement)
    }
  
    // Add package to cart
    function addPackageToCart (pkg) {
      const template = document.getElementById('package-item-template')
      const packageElement = template.cloneNode(true)
      packageElement.hidden = false
  
      // Set package details
      packageElement.querySelector(
        '.package-image'
      ).src = `${window.location.origin}/resource/assets-frontend/dist/product/${pkg.product_pict}`
      packageElement.querySelector('.package-name').textContent = pkg.product_name
      packageElement.querySelector(
        '.package-price'
      ).textContent = `Rp ${pkg.price_catalogue}`
  
      const quantityInput = packageElement.querySelector('.action-number')
      quantityInput.value = pkg.product_count
      quantityInput.dataset.productId = pkg.product_id
  
      const noteTextarea = packageElement.querySelector('.notes')
      noteTextarea.value = pkg.notes || ''
      noteTextarea.dataset.productId = pkg.product_id
  
      // Set action buttons
      const minusBtn = packageElement.querySelector('.action-minus')
      const plusBtn = packageElement.querySelector('.action-plus')
      minusBtn.dataset.productId = pkg.product_id
      plusBtn.dataset.productId = pkg.product_id
  
      // Add package items
      const packageItemsContainer = packageElement.querySelector('.package-items')
      pkg.package_items.forEach(childItem => {
        addPackageChildItem(packageItemsContainer, childItem)
      })
  
      document.getElementById('container-cart').appendChild(packageElement)
    }
  
    // Add package child item
    function addPackageChildItem (container, childItem) {
      const template = document.getElementById('package-child-template')
      const childElement = template.cloneNode(true)
      childElement.hidden = false
  
      // Set child item details
      childElement.querySelector(
        '.child-item-image'
      ).src = `${window.location.origin}/resource/assets-frontend/dist/product/${childItem.product_pict}`
      childElement.querySelector('.child-item-name').textContent =
        childItem.product_name
      childElement.querySelector('.child-item-category').textContent =
        childItem.package_category_name
      childElement.querySelector('.child-item-quantity').textContent =
        childItem.quantity
  
      const noteTextarea = childElement.querySelector('.child-notes')
      noteTextarea.value = childItem.notes || ''
      noteTextarea.dataset.productId = childItem.product_id
      noteTextarea.dataset.parentId = childItem.parent_id
  
      container.appendChild(childElement)
    }
  
    // Update cart summary
    function updateCartSummary () {
      const totalItems = cartState.items.reduce(
        (sum, item) => sum + item.product_count,
        0
      )
      const totalAmount = cartState.items.reduce(
        (sum, item) => sum + item.price_catalogue * item.product_count,
        0
      )
  
      document.getElementById('cart-total-items').textContent = totalItems
      document.getElementById(
        'cart-total-amount'
      ).textContent = `Rp ${totalAmount.toLocaleString()}`
    }
  
    // Handle notes for package items
    $(document).on('change', '.child-notes', function () {
      const productId = $(this).data('product-id')
      const parentId = $(this).data('parent-id')
      const notes = $(this).val()
  
      $.ajax({
        method: 'POST',
        url: `${window.location.origin}/order`,
        contentType: 'application/json',
        data: JSON.stringify({
          outletId: new URLSearchParams(window.location.search).get('outletId'),
          tableId: new URLSearchParams(window.location.search).get('tableId'),
          brand: new URLSearchParams(window.location.search).get('brand'),
          productId: productId,
          parentId: parentId,
          action: 1,
          notes: notes
        })
      })
    })
  
    // Handle cart modal events
    document
      .getElementById('cart-modal')
      .addEventListener('show.bs.modal', function () {
        $.ajax({
          type: 'GET',
          url: window.location.origin + '/order/cart?' + params.toString(),
          dataType: 'json'
        })
          .done(function (response) {
            cartState.items = response.data
            updateCartContent(response.data)
          })
          .fail(function (jqXHR, textStatus, errorThrown) {
            console.error('Failed to fetch cart data:', textStatus)
            Swal.fire({
              title: 'Error',
              text: 'Failed to load cart items',
              icon: 'error'
            })
          })
      })
  
    $('.action-number').on('keydown', function (event) {
      if (
        (event.keyCode >= 48 && event.keyCode <= 57) ||
        (event.keyCode >= 96 && event.keyCode <= 105)
      ) {
        return
      }
  
      event.preventDefault()
    })
  
    $(document)
      .on('focusin', '.action-number', function () {
        $(this).data('val', $(this).val())
      })
      .on('change', '.action-number', function () {
        let productCount = $('#product-count-' + $(this).data('product-id'))
        let productStock = $('#product-stock-' + $(this).data('product-id'))
  
        if (parseInt($(this).val()) == parseInt(productStock.text())) {
          return false
        }
  
        $.ajax({
          method: 'POST',
          url: window.location.origin + '/order',
          contentType: 'application/json',
          data: JSON.stringify({
            outletId: params.get('outletId'),
            tableId: params.get('tableId'),
            brand: params.get('brand'),
            productId: $(this).data('product-id'),
            action: 'addProduct',
            count: $(this).val()
          })
        })
          .done(function (response, textStatus, jqXHR) {})
          .fail(function (jqXHR, textStatus, errorThrown) {
            productCount.val($(this).data('val'))
          })
      })
  
    document.getElementById('order').addEventListener('click', function () {
      $.ajax({
        type: 'POST',
        url: window.location.origin + '/order/done',
        data: JSON.stringify({
          outletId: params.get('outletId'),
          tableId: params.get('tableId'),
          brand: params.get('brand')
        }),
        dataType: 'json'
      })
        .done(function (response, textStatus, jqXHR) {
          Swal.fire({
            title: 'Sukses Order',
            text: 'Silahkan lakukan pembayaran',
            icon: 'success'
          })
          cartModal.hide()
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
          if (jqXHR.status === 422) {
            Swal.fire({
              title: 'Stok Habis',
              // text: jqXHR.responseText,
              icon: 'error'
            }).then(result => {
              if (result.isConfirmed) {
                cartModal.hide()
              }
            })
          }
        })
    })
  
    $('#submitName').on('click', function () {
      let name = $('#inputCustomerName').val()
      $.ajax({
        type: 'POST',
        url: window.location.origin + '/order/session',
        data: JSON.stringify({
          outletId: params.get('outletId'),
          tableId: params.get('tableId'),
          brand: params.get('brand'),
          name: name
        }),
        dataType: 'json'
      }).done(function (response, textStatus, jqXHR) {
        $('#customer-name').text(name)
        // Switch page
        $('#identity-page').attr('hidden', true)
        $('#order-page').removeAttr('hidden')
  
        isStartSession = true
      })
    })
  
    window.setInterval(function () {
      if (!isStartSession) {
        return
      }
  
      $.ajax({
        type: 'GET',
        url: window.location.origin + '/order/countCart?' + params.toString(),
        dataType: 'json'
      })
        .done(function (response, textStatus, jqXHR) {
          $('#count-cart').text(response.data)
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
          console.log(textStatus)
        })
    }, 3000)
  })
  