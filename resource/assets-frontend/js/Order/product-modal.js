/**
 * ProductModal - Handles all product modal functionality
 * Requires: jQuery, Bootstrap 5, SweetAlert2
 */
const ProductModal = {
  debug: true,

  state: {
    currentProduct: null,
    currentPackage: null,
    selectedPackageItems: {},
    packageCategories: {},
    modalInstance: null,
    validationStatus: {
      categoriesValid: false,
      stockValid: false,
    },
    errors: [],
    ui: {
      loading: false,
      modalVisible: false,
    },
  },

  // Initialize modal functionality
  // Fixed init method
  init() {
    console.group("Product Modal Initialization");

    try {
      // Declare product cards first
      const $productCards = $(".product-card");
      const $orderPage = $("#order-page");

      // Log initial visibility status
      console.log("Initial Visibility:", {
        cards: {
          count: $productCards.length,
          display: $productCards.css("display"),
          visible: $productCards.is(":visible"),
          hidden: $productCards.is(":hidden"),
        },
        orderPage: {
          exists: $orderPage.length > 0,
          display: $orderPage.css("display"),
          hidden: $orderPage.is(":hidden"),
        },
      });

      // Force show elements
      $productCards.each(function () {
        const $card = $(this);
        $card.css({
          display: "block",
          visibility: "visible",
          opacity: "1",
        });

        // Show parent category
        $card.closest(".product-category").show();
      });

      // Show order page section
      $orderPage.removeAttr("hidden").show();
      $(".explore-section").removeClass("custom-hidden").show();

      // Log visibility after changes
      console.log("Visibility after changes:", {
        cards: {
          visible: $productCards.is(":visible"),
          hidden: $productCards.is(":hidden"),
        },
        orderPage: {
          visible: $orderPage.is(":visible"),
          hidden: $orderPage.is(":hidden"),
        },
      });

      // Validate initialization
      if (!this.validateInitialization()) {
        throw new Error("Initialization validation failed");
      }

      // Initialize modal instance
      this.modalInstance = new bootstrap.Modal("#productModal");

      // Setup event listeners
      this.setupEventListeners();

      console.log("Product Modal initialized successfully");
      return true;
    } catch (error) {
      console.error("Initialization failed:", error);
      this.handleInitError(error);
      return false;
    } finally {
      console.groupEnd();
    }
  },

  // Fixed validation method
  validateInitialization() {
    // Log categories structure first
    const $categories = $(".product-category");
    console.log("Categories structure:", {
      count: $categories.length,
      elements: $categories.toArray().map((el) => ({
        id: $(el).data("category-id"),
        name: $(el).data("category-name"),
        visible: $(el).is(":visible"),
        products: $(el).find(".product-card").length,
      })),
    });

    // Required DOM elements validation
    const requiredElements = {
      modal: $("#productModal"),
      regularContent: $("#regular-product-content"),
      packageContent: $("#package-product-content"),
      regularAddBtn: $("#add-to-cart-regular"),
      packageAddBtn: $("#add-to-cart-package"),
      productCards: $(".product-card"),
      modalTitle: $("#modal-product-name"),
      modalImage: $("#modal-product-image"),
      quantityInput: $(".product-qty"),
      noteInput: $("#product-note"),
      orderPage: $("#order-page"),
      productListing: $("#product-listing"),
    };

    // Validate all required elements exist
    for (const [key, element] of Object.entries(requiredElements)) {
      if (element.length === 0) {
        console.error(`Missing required element: ${key}`);
        return false;
      }
    }

    // Validate URL parameters
    const params = new URLSearchParams(window.location.search);
    const requiredParams = ["outletId", "tableId", "brand"];

    for (const param of requiredParams) {
      if (!params.get(param)) {
        console.error(`Missing required parameter: ${param}`);
        return false;
      }
    }

    // Additional visibility checks
    if ($("#order-page").is(":hidden")) {
      console.log("Order page was hidden, showing it...");
      $("#order-page").removeAttr("hidden").show();
      $(".custom-hidden").removeClass("custom-hidden");
    }

    // Check if Bootstrap is available
    if (typeof bootstrap === "undefined" || !bootstrap.Modal) {
      console.error("Bootstrap Modal not available");
      return false;
    }

    // Log final validation state
    console.log("Validation completed", {
      elementsPresent: true,
      paramsPresent: true,
      orderPageVisible: $("#order-page").is(":visible"),
      productsVisible: $(".product-card:visible").length,
    });

    return true;
  },

  setupEventListeners() {
    console.log("Setting up event listeners");

    // Log jumlah elemen yang dapat di-click
    console.log("Clickable elements:", {
      productCards: $(".product-card").length,
      viewButtons: $(".view-product").length,
    });

    // Product card click handler
    $(document).on("click", ".product-card .view-product", async (e) => {
      e.preventDefault();
      e.stopPropagation();

      const $card = $(e.target).closest(".product-card");
      const productId = $card.data("product-id");
      const isPackage = $card.data("is-package") === 1;

      console.log("Product clicked:", {
        productId,
        isPackage,
        card: $card,
      });

      try {
        await this.handleProductClick(productId, isPackage);
      } catch (error) {
        console.error("Error handling product click:", error);
        Swal.fire({
          title: "Error",
          text: error.message || "Failed to load product details",
          icon: "error",
        });
      }
    });

    // Regular product quantity handlers
    $(document).on(
      "click",
      ".decrease-qty",
      this.handleQuantityDecrease.bind(this)
    );
    $(document).on(
      "click",
      ".increase-qty",
      this.handleQuantityIncrease.bind(this)
    );
    $(document).on(
      "change",
      ".product-qty",
      this.handleQuantityChange.bind(this)
    );
    $(document).on("input", "#product-note", this.handleNoteChange.bind(this));

    // Add to cart handlers
    $("#add-to-cart-regular").on("click", () => this.addRegularToCart());
    $("#add-to-cart-package").on("click", () => this.addPackageToCart());

    // Package specific handlers
    $(document).on(
      "change",
      ".package-item-qty",
      this.handlePackageItemChange.bind(this)
    );
    $(document).on(
      "change",
      ".package-item-select",
      this.handlePackageItemSelect.bind(this)
    );
    $(document).on(
      "click",
      ".package-category-tab",
      this.handleCategoryTabClick.bind(this)
    );

    // Modal cleanup
    $("#productModal").on("hidden.bs.modal", () => this.resetState());
  },

  async handleProductClick(productId, isPackage) {
    console.group(`Product Click Handler: ${productId}`);
    try {
      this.showLoading(true);

      // Fetch product details
      const productData = await this.fetchProductDetails(productId);
      if (!productData) {
        throw new Error("Failed to load product details");
      }

      // Update state
      this.state.currentProduct = productData;

      if (isPackage) {
        // Handle package product
        const packageData = await this.fetchPackageDetails(productId);
        if (!packageData) {
          throw new Error("Failed to load package details");
        }

        this.state.currentPackage = packageData;
        await this.renderPackageModal();
      } else {
        // Handle regular product
        await this.renderRegularModal();
      }

      this.modalInstance.show();
    } catch (error) {
      console.error("Product click error:", error);
      this.showError(error.message);
    } finally {
      this.showLoading(false);
      console.groupEnd();
    }
  },

  // Tambahkan error handling dan logging di fetchProductDetails
  async fetchProductDetails(productId) {
    try {
        const params = new URLSearchParams(window.location.search);
        params.set('productId', productId);
        params.set('ajax', 'true');

        const response = await $.ajax({
            url: `${window.location.origin}/order/list`,
            method: 'GET',
            data: params.toString(),
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            timeout: 15000
        });

        if (!response.success) {
            throw new Error(response.message || 'Invalid response');
        }

        return this.processProductResponse(response, productId);
    } catch (error) {
        this.handleProductFetchError(error, productId);
        throw error;
    }
},

handleProductFetchError(error, productId) {
    console.error('Product Fetch Error', {
        productId,
        status: error.status,
        responseText: error.responseText,
        message: error.message
    });

    if (error.status === 500) {
        this.showToast('error', 'Internal server error');
    } else if (error.responseText?.includes('<!DOCTYPE html>')) {
        this.showToast('error', 'Unexpected server response');
    } else {
        this.showToast('error', 'Failed to fetch product details');
    }
},

  // Add new helper methods
  async validateSession() {
    try {
      const params = new URLSearchParams(window.location.search);
      const response = await $.ajax({
        url: `${window.location.origin}/order/session`,
        method: "GET",
        data: params.toString(),
      });

      return response.success;
    } catch (error) {
      console.error("Session validation error:", error);
      return false;
    }
  },

  async handleSessionExpired() {
    // Clear any cached data
    localStorage.removeItem("sessionId");

    // Show error to user
    await Swal.fire({
      title: "Session Expired",
      text: "Your session has expired. The page will reload.",
      icon: "warning",
      confirmButtonText: "Reload Now",
      allowOutsideClick: false,
    });

    // Reload page
    window.location.reload();
  },

  async fetchPackageDetails(productId) {
    try {
      const params = new URLSearchParams(window.location.search);

      const response = await $.ajax({
        url: `${window.location.origin}/order/list`,
        method: "GET",
        data: params.toString(),
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      if (!response.success || !response.data?.packages) {
        throw new Error("Invalid response format");
      }

      const packageData = response.data.packages.find(
        (p) => p.product_id == productId
      );
      if (!packageData) {
        throw new Error("Package not found");
      }

      return {
        ...packageData,
        categories: response.data.packageCategories || [],
        products: response.data.products || [],
      };
    } catch (error) {
      console.error("Fetch package error:", error);
      return null;
    }
  },

  async renderRegularModal() {
    const product = this.state.currentProduct;

    // Update modal content
    $("#modal-product-name").text(product.product_name);
    $("#modal-product-image").attr(
      "src",
      this.getProductImageUrl(product.product_pict)
    );
    $("#modal-product-description").text(
      product.description || "No description available"
    );
    $("#modal-product-price").text(this.formatPrice(product.price_catalogue));
    $("#modal-product-stock").text(`${product.current_stock} units`);

    // Setup quantity input
    $(".product-qty")
      .attr("max", product.current_stock)
      .val(1)
      .trigger("change");

    // Show/hide relevant sections
    $("#regular-product-content").show();
    $("#package-product-content").hide();
    $("#add-to-cart-regular").show();
    $("#add-to-cart-package").hide();

    this.updateSubtotal();
  },

  async renderPackageModal() {
    try {
      const { currentPackage: pkg, currentProduct: prod } = this.state;

      // Setup basic package info
      $("#modal-package-image").attr(
        "src",
        this.getProductImageUrl(prod.product_pict)
      );
      $("#modal-package-description").text(
        pkg.description || "No description available"
      );
      $("#modal-package-base-price").text(this.formatPrice(pkg.base_price));

      // Render sections
      this.renderPackageCategories();
      this.renderPackageProducts();
      this.renderExcludedProducts();

      // Show/hide relevant sections
      $("#regular-product-content").hide();
      $("#package-product-content").show();
      $("#add-to-cart-regular").hide();
      $("#add-to-cart-package").show().prop("disabled", true);

      // Initialize summary
      this.updatePackageSummary();
    } catch (error) {
      console.error("Render package modal error:", error);
      throw error;
    }
  },

  renderPackageCategories() {
    const $container = $("#package-categories").empty();

    this.state.currentPackage.categories.forEach((category) => {
      const $category = $(`
          <div class="package-category mb-3">
            <h6>${category.name}</h6>
            <div class="d-flex align-items-center">
              <div class="progress flex-grow-1">
                <div class="progress-bar" role="progressbar" 
                     data-category="${category.id}"
                     style="width: 0%">
                  0/${category.min_items}
                </div>
              </div>
              <span class="badge bg-secondary ms-2">
                Min: ${category.min_items}
              </span>
            </div>
          </div>
        `);

      $container.append($category);
    });
  },

  renderPackageProducts() {
    const $accordion = $("#package-products-accordion").empty();
    const { categories, products } = this.state.currentPackage;

    categories.forEach((category) => {
      const categoryProducts = products.filter(
        (p) => p.category_id === category.id
      );

      const $section = $(`
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button" type="button" 
                      data-bs-toggle="collapse" 
                      data-bs-target="#category-${category.id}">
                ${category.name}
              </button>
            </h2>
            <div id="category-${category.id}" 
                 class="accordion-collapse collapse show">
              <div class="accordion-body">
                <div class="row g-3" id="products-${category.id}">
                </div>
              </div>
            </div>
          </div>
        `);

      const $products = $section.find(`#products-${category.id}`);

      categoryProducts.forEach((product) => {
        $products.append(this.createPackageProductCard(product, category));
      });

      $accordion.append($section);
    });
  },

  createPackageProductCard(product, category) {
    return $(`
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="d-flex mb-2">
                <img src="${this.getProductImageUrl(product.product_pict)}"
                     class="rounded me-2" 
                     style="width: 60px; height: 60px; object-fit: cover;">
                <div>
                  <h6 class="card-title mb-1">${product.product_name}</h6>
                  <p class="card-text small text-muted mb-0">
                    Stock: ${product.stock}
                  </p>
                </div>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <div class="price-info">
                  ${this.renderProductPrice(product)}
                </div>
                <div class="input-group input-group-sm" style="width: 100px;">
                  <button class="btn btn-outline-secondary decrease-qty" type="button">-</button>
                  <input type="number" class="form-control text-center package-item-qty"
                         value="0" min="0" max="${product.stock}"
                         data-product-id="${product.id}"
                         data-category-id="${category.id}">
                  <button class="btn btn-outline-secondary increase-qty" type="button">+</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      `);
  },

  renderProductPrice(product) {
    const regularPrice = this.formatPrice(product.price);

    if (product.special_price) {
      return `
          <span class="regular-price text-decoration-line-through">
            ${regularPrice}
          </span>
          <span class="special-price text-success">
            ${this.formatPrice(product.special_price)}
          </span>
        `;
    }

    return `<span class="regular-price">${regularPrice}</span>`;
  },

  renderExcludedProducts() {
    const $section = $("#excluded-products-section");
    const $list = $("#excluded-products-list").empty();

    const { excluded_products } = this.state.currentPackage;

    if (!excluded_products?.length) {
      $section.hide();
      return;
    }

    excluded_products.forEach((product) => {
      $list.append(`
          <div class="col-md-6">
            <div class="alert alert-warning mb-2">
              <h6 class="alert-heading">${product.product_name}</h6>
              <p class="small mb-0">
                ${product.exclude_reason || "Not available in this package"}
              </p>
            </div>
          </div>
        `);
    });

    $section.show();
  },

  // Event Handlers
  handleQuantityDecrease(e) {
    const $input = $(e.currentTarget).siblings("input");
    const currentVal = parseInt($input.val()) || 0;
    const minVal = parseInt($input.attr("min")) || 1;

    if (currentVal > minVal) {
      $input.val(currentVal - 1).trigger("change");
    }
  },

  handleQuantityIncrease(e) {
    const $input = $(e.currentTarget).siblings("input");
    const currentVal = parseInt($input.val()) || 0;
    const maxVal = parseInt($input.attr("max"));

    if (!maxVal || currentVal < maxVal) {
      $input.val(currentVal + 1).trigger("change");
    }
  },

  handleQuantityChange(e) {
    const $input = $(e.target);
    const quantity = parseInt($input.val()) || 0;
    const maxStock = parseInt($input.attr("max")) || 0;

    // Validate quantity
    if (quantity < 1) {
      $input.val(1);
    } else if (quantity > maxStock) {
      $input.val(maxStock);
      this.showWarning(`Maximum stock available: ${maxStock}`);
    }

    this.updateSubtotal();
  },

  handleNoteChange(e) {
    this.state.currentNote = $(e.target).val().trim();
  },

  handlePackageItemChange(e) {
    const $input = $(e.target);
    const productId = $input.data("product-id");
    const categoryId = $input.data("category-id");
    const quantity = parseInt($input.val()) || 0;

    // Update selected items state
    if (!this.state.selectedPackageItems[categoryId]) {
      this.state.selectedPackageItems[categoryId] = {};
    }

    if (quantity > 0) {
      this.state.selectedPackageItems[categoryId][productId] = quantity;
    } else {
      delete this.state.selectedPackageItems[categoryId][productId];
    }

    this.updatePackageProgress(categoryId);
    this.updatePackageSummary();
    this.validatePackage();
  },

  handlePackageItemSelect(e) {
    const $checkbox = $(e.currentTarget);
    const itemId = $checkbox.data("item-id");
    const categoryId = $checkbox.data("category-id");

    if ($checkbox.is(":checked")) {
      this.addPackageItem(itemId, categoryId);
    } else {
      this.removePackageItem(itemId);
    }

    this.updatePackageValidation();
  },

  handleCategoryTabClick(e) {
    e.preventDefault();
    const $tab = $(e.currentTarget);
    const categoryId = $tab.data("category-id");

    // Update active state
    $(".package-category-tab").removeClass("active");
    $tab.addClass("active");

    // Show relevant products
    $(".package-product-group").hide();
    $(`#category-products-${categoryId}`).show();
  },

  // Package Management Methods
  addPackageItem(itemId, categoryId) {
    if (!this.state.selectedPackageItems[categoryId]) {
      this.state.selectedPackageItems[categoryId] = {};
    }

    this.state.selectedPackageItems[categoryId][itemId] = {
      quantity: 1,
    };

    this.updatePackageUI();
  },

  removePackageItem(itemId) {
    Object.keys(this.state.selectedPackageItems).forEach((categoryId) => {
      if (this.state.selectedPackageItems[categoryId][itemId]) {
        delete this.state.selectedPackageItems[categoryId][itemId];
      }
    });

    this.updatePackageUI();
  },

  updatePackageUI() {
    this.updatePackageProgress();
    this.updatePackageSummary();
    this.validatePackage();
  },

  updatePackageProgress(categoryId = null) {
    const categories = categoryId
      ? [this.state.currentPackage.categories.find((c) => c.id === categoryId)]
      : this.state.currentPackage.categories;

    categories.forEach((category) => {
      if (!category) return;

      const selectedItems = this.state.selectedPackageItems[category.id] || {};
      const totalItems = Object.values(selectedItems).reduce(
        (sum, qty) => sum + qty,
        0
      );
      const progress = Math.min((totalItems / category.min_items) * 100, 100);

      const $progress = $(`.progress-bar[data-category="${category.id}"]`);
      $progress
        .css("width", `${progress}%`)
        .text(`${totalItems}/${category.min_items}`)
        .toggleClass("bg-success", totalItems >= category.min_items)
        .toggleClass(
          "bg-warning",
          totalItems > 0 && totalItems < category.min_items
        );
    });
  },

  updatePackageSummary() {
    const $summary = $("#package-summary").empty();
    let totalAmount = this.state.currentPackage.base_price || 0;
    let isValid = true;

    // Add base price line
    $summary.append(`
        <div class="d-flex justify-content-between mb-2">
          <span>Base Package Price</span>
          <span>${this.formatPrice(totalAmount)}</span>
        </div>
      `);

    // Summarize each category
    Object.entries(this.state.selectedPackageItems).forEach(
      ([categoryId, items]) => {
        const category = this.state.currentPackage.categories.find(
          (c) => c.id === categoryId
        );
        if (!category) return;

        const totalItems = Object.values(items).reduce(
          (sum, qty) => sum + qty,
          0
        );
        const isComplete = totalItems >= category.min_items;

        // Calculate category total
        let categoryTotal = 0;
        Object.entries(items).forEach(([productId, quantity]) => {
          const product = this.findPackageProduct(productId);
          if (product) {
            const price = product.special_price || product.price;
            categoryTotal += price * quantity;
          }
        });

        totalAmount += categoryTotal;

        $summary.append(`
          <div class="d-flex justify-content-between mb-2">
            <span>${category.name} (${totalItems} items)</span>
            <span class="text-end">
              <span class="badge ${
                isComplete ? "bg-success" : "bg-warning"
              } me-2">
                ${
                  isComplete
                    ? "Complete"
                    : `Need ${category.min_items - totalItems} more`
                }
              </span>
              ${this.formatPrice(categoryTotal)}
            </span>
          </div>
        `);

        isValid = isValid && isComplete;
      }
    );

    // Update total and button state
    $("#package-total").text(this.formatPrice(totalAmount));
    $("#add-to-cart-package").prop("disabled", !isValid);
    this.state.packageTotal = totalAmount;
  },

  validatePackage() {
    if (!this.state.currentPackage) return false;

    const errors = [];
    let isValid = true;

    // Validate each category
    this.state.currentPackage.categories.forEach((category) => {
      const selectedItems = this.state.selectedPackageItems[category.id] || {};
      const totalItems = Object.values(selectedItems).reduce(
        (sum, qty) => sum + qty,
        0
      );

      if (totalItems < category.min_items) {
        isValid = false;
        errors.push(
          `${category.name} needs ${category.min_items - totalItems} more items`
        );
      }
    });

    // Update state and UI
    this.state.validationStatus.categoriesValid = isValid;
    this.state.errors = errors;
    this.updateValidationUI();

    return isValid;
  },

  updateValidationUI() {
    const isValid =
      this.state.validationStatus.categoriesValid &&
      this.state.validationStatus.stockValid;

    $("#add-to-cart-package").prop("disabled", !isValid);

    const $messages = $(".validation-messages").empty();
    if (this.state.errors.length > 0) {
      this.state.errors.forEach((error) => {
        $messages.append(`<div class="alert alert-warning">${error}</div>`);
      });
    }
  },

  // Cart Operations
  async addRegularToCart() {
    try {
      const quantity = parseInt($(".product-qty").val()) || 0;
      const note = $("#product-note").val().trim();

      if (quantity < 1) {
        throw new Error("Please enter a valid quantity");
      }

      if (quantity > this.state.currentProduct.current_stock) {
        throw new Error("Quantity exceeds available stock");
      }

      this.showLoading(true);

      const response = await this.sendAddToCartRequest({
        action: 2, // Regular product action
        data: [
          {
            productId: this.state.currentProduct.product_id,
            quantity: quantity,
            notes: note,
          },
        ],
      });

      if (response.success) {
        this.modalInstance.hide();
        await this.updateCartCount();
        this.showSuccess("Product added to cart successfully");
      } else {
        throw new Error(response.message || "Failed to add to cart");
      }
    } catch (error) {
      console.error("Add to cart error:", error);
      this.showError(error.message);
    } finally {
      this.showLoading(false);
    }
  },

  async addPackageToCart() {
    try {
      if (!this.validatePackage()) {
        throw new Error("Please complete all package requirements");
      }

      const packageItems = [];
      Object.entries(this.state.selectedPackageItems).forEach(
        ([categoryId, items]) => {
          Object.entries(items).forEach(([productId, quantity]) => {
            packageItems.push({
              productId: parseInt(productId),
              quantity: quantity,
            });
          });
        }
      );

      this.showLoading(true);

      const response = await this.sendAddToCartRequest({
        action: 3, // Package action
        packageId: this.state.currentProduct.product_id,
        products: packageItems,
      });

      if (response.success) {
        this.modalInstance.hide();
        await this.updateCartCount();
        this.showSuccess("Package added to cart successfully");
      } else {
        throw new Error(response.message || "Failed to add package to cart");
      }
    } catch (error) {
      console.error("Add package to cart error:", error);
      this.showError(error.message);
    } finally {
      this.showLoading(false);
    }
  },

  async sendAddToCartRequest(data) {
    const params = new URLSearchParams(window.location.search);

    return await $.ajax({
      type: "POST",
      url: `${window.location.origin}/order/add`,
      contentType: "application/json",
      data: JSON.stringify({
        ...data,
        orderId: params.get("orderId"),
      }),
    });
  },

  async updateCartCount() {
    try {
      const params = new URLSearchParams(window.location.search);
      const maxRetries = 3;
      let retryCount = 0;

      while (retryCount < maxRetries) {
        try {
          const response = await $.ajax({
            type: "GET",
            url: `${window.location.origin}/order/countCart`,
            data: params.toString(),
            timeout: 5000, // Add timeout
          });

          if (response.success) {
            $("#count-cart").text(response.data.metrics.total_items);
            return true;
          }
          return false;
        } catch (err) {
          retryCount++;
          if (retryCount === maxRetries) {
            throw err;
          }
          // Wait before retry
          await new Promise((resolve) => setTimeout(resolve, 1000));
        }
      }
    } catch (error) {
      console.error("Update cart count error:", error);
      // Show user friendly error
      this.showError("Failed to update cart. Please refresh the page.");
      return false;
    }
  },

  // Utility Methods
  updateSubtotal() {
    const quantity = parseInt($(".product-qty").val()) || 0;
    const price = this.state.currentProduct?.price_catalogue || 0;
    const subtotal = quantity * price;
    $("#product-subtotal").text(this.formatPrice(subtotal));
  },

  findPackageProduct(productId) {
    return this.state.currentPackage.products.find((p) => p.id === productId);
  },

  getProductImageUrl(imageName) {
    return `${window.location.origin}/resource/assets-frontend/dist/product/${imageName}`;
  },

  formatPrice(amount) {
    return new Intl.NumberFormat("id-ID", {
      style: "currency",
      currency: "IDR",
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount);
  },

  showLoading(show = true) {
    if (show) {
      Swal.showLoading();
    } else {
      Swal.close();
    }
  },

  showSuccess(message) {
    Swal.fire({
      title: "Success",
      text: message,
      icon: "success",
      timer: 1500,
      showConfirmButton: false,
    });
  },

  showError(message) {
    Swal.fire({
      title: "Error",
      text: message,
      icon: "error",
    });
  },

  showToast(type, message) {
    Swal.fire({
      toast: true,
      position: "top-end",
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
      icon: type,
      text: message,
    });
  },

  showWarning(message) {
    Swal.fire({
      text: message,
      icon: "warning",
      toast: true,
      position: "top-end",
      showConfirmButton: false,
      timer: 3000,
    });
  },

  // State Management
  resetState() {
    this.state = {
      currentProduct: null,
      currentPackage: null,
      selectedPackageItems: {},
      packageCategories: {},
      modalInstance: this.modalInstance,
      validationStatus: {
        categoriesValid: false,
        stockValid: false,
      },
      errors: [],
      ui: {
        loading: false,
        modalVisible: false,
      },
    };

    // Reset UI elements
    $("#regular-product-content, #package-product-content").hide();
    $("#add-to-cart-regular, #add-to-cart-package").hide();
    $(".product-qty").val(1);
    $("#product-note").val("");
    $(".validation-messages").empty();
  },
};

// Initialize the module
$(() => {
  console.group("Product Modal Initialization");

  try {
    // Check for required dependencies
    if (typeof bootstrap === "undefined") {
      throw new Error("Bootstrap is required but not loaded");
    }
    if (typeof Swal === "undefined") {
      throw new Error("SweetAlert2 is required but not loaded");
    }

    const requiredScripts = {
      jquery: typeof jQuery !== "undefined",
      bootstrap: typeof bootstrap !== "undefined",
      sweetalert: typeof Swal !== "undefined",
    };

    console.log("Dependencies Check:", requiredScripts);
    console.log("DOM Ready state:", document.readyState);
    console.log("jQuery version:", $.fn.jquery);
    console.log("Bootstrap version:", bootstrap.VERSION);

    // Initialize ProductModal
    if (ProductModal.init()) {
      console.log("ProductModal initialized successfully");

      // Log initial state
      console.log("Initial Elements:", {
        modal: $("#productModal").length,
        productCards: $(".product-card").length,
        viewButtons: $(".view-product").length,
      });
    }
  } catch (error) {
    console.error("Initialization Error:", error);

    // Show user-friendly error
    Swal.fire({
      title: "Initialization Error",
      text: "Failed to initialize the product system. Please refresh the page.",
      icon: "error",
      confirmButtonText: "Refresh Page",
      showCancelButton: true,
      cancelButtonText: "Continue Anyway",
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.reload();
      }
    });
  } finally {
    console.groupEnd();
  }
});

// Export module (optional - for module systems)
if (typeof module !== "undefined" && module.exports) {
  module.exports = ProductModal;
}
