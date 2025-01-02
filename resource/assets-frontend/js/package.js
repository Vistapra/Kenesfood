// Initialize package modal
let packageModal = new bootstrap.Modal("#package-modal", { keyboard: false });

// Package state management
let selectedPackage = {
    id: null,
    productId: null,
    categories: {},
    selectedItems: {}
};

// Add package button to product cards that have package options
function addPackageButtons() {
    const products = document.querySelectorAll('.product-image-btn');
    products.forEach(product => {
        const productId = product.dataset.productId;
        
        // Check if product has package options
        $.ajax({
            type: "GET",
            url: `${window.location.origin}/apis/product/packages/${productId}`,
            dataType: "json"
        })
        .done(function(response) {
            if (response.data && response.data.packages && response.data.packages.length > 0) {
                const buttonTemplate = document.getElementById('package-button-template')
                    .innerHTML;
                const packageButton = document.createElement('div');
                packageButton.innerHTML = buttonTemplate;
                packageButton.querySelector('.select-package').dataset.productId = productId;
                product.appendChild(packageButton);
            }
        });
    });
}

// Handle package selection
$(document).on('click', '.select-package', function(e) {
    e.stopPropagation();
    const productId = $(this).data('product-id');
    
    // Reset package state
    selectedPackage = {
        id: null,
        productId: productId,
        categories: {},
        selectedItems: {}
    };
    
    // Fetch package details
    $.ajax({
        type: "GET",
        url: `${window.location.origin}/apis/product/packages/${productId}`,
        dataType: "json"
    })
    .done(function(response) {
        if (response.data && response.data.packages) {
            displayPackageDetails(response.data);
            packageModal.show();
        }
    });
});

// Display package details and options
function displayPackageDetails(data) {
    const packageName = document.getElementById('package-name');
    const packageDescription = document.getElementById('package-description');
    const categoriesContainer = document.getElementById('package-categories');
    const itemsAccordion = document.getElementById('packageItemsAccordion');
    
    packageName.textContent = data.package_name;
    packageDescription.textContent = data.package_description;
    
    // Display package categories
    categoriesContainer.innerHTML = '';
    data.categories.forEach(category => {
        const categoryDiv = document.createElement('div');
        categoryDiv.className = 'mb-3';
        categoryDiv.innerHTML = `
            <h6>${category.name}</h6>
            <p class="text-muted">Select ${category.required_qty} items</p>
        `;
        categoriesContainer.appendChild(categoryDiv);
    });
    
    // Display items by category
    itemsAccordion.innerHTML = '';
    data.categories.forEach((category, index) => {
        const accordionItem = document.createElement('div');
        accordionItem.className = 'accordion-item';
        accordionItem.innerHTML = `
            <h2 class="accordion-header">
                <button class="accordion-button ${index === 0 ? '' : 'collapsed'}" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#category-${category.id}">
                    ${category.name}
                </button>
            </h2>
            <div id="category-${category.id}" 
                 class="accordion-collapse collapse ${index === 0 ? 'show' : ''}"
                 data-bs-parent="#packageItemsAccordion">
                <div class="accordion-body">
                    <div class="row" id="category-items-${category.id}">
                    </div>
                </div>
            </div>
        `;
        itemsAccordion.appendChild(accordionItem);
        
        // Add items to category
        const itemsContainer = accordionItem.querySelector(`#category-items-${category.id}`);
        category.items.forEach(item => {
            const itemDiv = document.createElement('div');
            itemDiv.className = 'col-6 col-md-4 mb-3';
            itemDiv.innerHTML = `
                <div class="card h-100">
                    <img src="${window.location.origin}/resource/assets-frontend/dist/product/${item.product_pict}" 
                         class="card-img-top" 
                         alt="${item.product_name}">
                    <div class="card-body">
                        <h6 class="card-title">${item.product_name}</h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="btn-group">
                                <button type="button" 
                                        class="btn btn-sm btn-outline-secondary package-item-minus"
                                        data-category-id="${category.id}"
                                        data-product-id="${item.product_id}">
                                    -
                                </button>
                                <span class="px-2 package-item-quantity" 
                                      data-category-id="${category.id}"
                                      data-product-id="${item.product_id}">0</span>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-secondary package-item-plus"
                                        data-category-id="${category.id}"
                                        data-product-id="${item.product_id}">
                                    +
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            itemsContainer.appendChild(itemDiv);
        });
    });
}

// Handle package item selection
$(document).on('click', '.package-item-plus, .package-item-minus', function() {
    const categoryId = $(this).data('category-id');
    const productId = $(this).data('product-id');
    const isPlus = $(this).hasClass('package-item-plus');
    
    // Initialize category in selected items if needed
    if (!selectedPackage.selectedItems[categoryId]) {
        selectedPackage.selectedItems[categoryId] = {};
    }
    
    // Update quantity
    const currentQty = selectedPackage.selectedItems[categoryId][productId] || 0;
    const newQty = isPlus ? currentQty + 1 : Math.max(0, currentQty - 1);
    selectedPackage.selectedItems[categoryId][productId] = newQty;
    
    // Update display
    $(`.package-item-quantity[data-category-id="${categoryId}"][data-product-id="${productId}"]`)
        .text(newQty);
    
    updateSelectedItemsSummary();
    validatePackageSelection();
});

// Update selected items summary
function updateSelectedItemsSummary() {
    const summaryList = document.getElementById('selected-package-items');
    summaryList.innerHTML = '';
    
    Object.entries(selectedPackage.selectedItems).forEach(([categoryId, items]) => {
        Object.entries(items).forEach(([productId, quantity]) => {
            if (quantity > 0) {
                const listItem = document.createElement('li');
                listItem.className = 'list-group-item d-flex justify-content-between';
                listItem.innerHTML = `
                    <span>${productId} - Category ${categoryId}</span>
                    <span>Qty: ${quantity}</span>
                `;
                summaryList.appendChild(listItem);
            }
        });
    });
}

// Validate package selection
function validatePackageSelection() {
    const addToCartBtn = document.getElementById('add-package-to-cart');
    let isValid = true;
    
    // Check if each category has the required quantity
    Object.entries(selectedPackage.categories).forEach(([categoryId, requiredQty]) => {
        const selectedQty = Object.values(selectedPackage.selectedItems[categoryId] || {})
            .reduce((sum, qty) => sum + qty, 0);
        if (selectedQty !== requiredQty) {
            isValid = false;
        }
    });
    
    addToCartBtn.disabled = !isValid;
}

// Add package to cart
$('#add-package-to-cart').on('click', function() {
    const params = new URLSearchParams(window.location.search);
    
    $.ajax({
        type: "POST",
        url: `${window.location.origin}/order`,
        contentType: "application/json",
        data: JSON.stringify({
            outletId: params.get("outletId"),
            tableId: params.get("tableId"),
            brand: params.get("brand"),
            action: 2,
            data: [{
                productId: selectedPackage.productId,
                quantity: 1,
                products: Object.entries(selectedPackage.selectedItems).flatMap(([categoryId, items]) =>
                    Object.entries(items).map(([productId, quantity]) => ({
                        productId,
                        quantity,
                        categoryId
                    }))
                )
            }]
        })
    })
    .done(function(response) {
        packageModal.hide();
        Swal.fire({
            title: "Success",
            text: "Package added to cart",
            icon: "success"
        });
    })
    .fail(function(jqXHR) {
        Swal.fire({
            title: "Error",
            text: jqXHR.responseJSON?.message || "Failed to add package to cart",
            icon: "error"
        });
    });
});

// Initialize package features
document.addEventListener('DOMContentLoaded', function() {
    addPackageButtons();
});