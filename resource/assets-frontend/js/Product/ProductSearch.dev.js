"use strict";

const ProductSearch = {
  state: {
    searchTimeout: null,
    initialized: false,
    minSearchLength: 1,
    containerHTML: null,
    currentCategory: "all",
    productCache: null,
  },

  config: {
    selectors: {
      searchInput: "#product-search",
      productCard: ".product-card",
      productCategory: ".category-header",
      productContainer: ".row.g-4.mb-5",
      productListing: "#product-listing",
      categorySelect: "#category-select",
    },
    debounceTime: 300,
  },

  init() {
    console.group("ProductSearch Initialization");
    try {
      this.validateElements();
      this.cacheInitialState();
      this.initializeProductCache();
      this.bindEvents();
      this.subscribeToFilterChanges();

      this.state.initialized = true;
      console.log("ProductSearch initialized successfully");
      return true;
    } catch (error) {
      console.error("ProductSearch initialization failed:", error);
      return false;
    } finally {
      console.groupEnd();
    }
  },

  validateElements() {
    const $searchInput = $(this.config.selectors.searchInput);
    const $products = $(this.config.selectors.productCard);
    const $categorySelect = $(this.config.selectors.categorySelect);
    const $productListing = $(this.config.selectors.productListing);

    if (!$searchInput.length) throw new Error("Search input not found");
    if (!$products.length) throw new Error("No product cards found");
    if (!$categorySelect.length) throw new Error("Category select not found");
    if (!$productListing.length)
      throw new Error("Product listing container not found");

    console.log(`Found ${$products.length} products and all required elements`);
  },

  initializeProductCache() {
    this.state.productCache = [];

    // Ubah cara mengakses produk
    $(this.config.selectors.productContainer).each((_, container) => {
      const $container = $(container);
      const $category = $container.prev(this.config.selectors.productCategory);

      $container.find(this.config.selectors.productCard).each((_, card) => {
        const $card = $(card);

        this.state.productCache.push({
          element: card,
          container: container,
          category: $category[0],
          name: ($card.data("product-name") || "").toLowerCase(),
          desc: ($card.data("product-desc") || "").toLowerCase(),
          categoryId: $card.data("category-id")?.toString(),
        });
      });
    });

    console.log(
      `Cached ${this.state.productCache.length} products for faster search`
    );
  },

  cacheInitialState() {
    const $productListing = $(this.config.selectors.productListing);
    if ($productListing.length) {
      this.state.containerHTML = $productListing.html();
      console.log("Initial state cached successfully");
    }
  },

  subscribeToFilterChanges() {
    const self = this;
    $(this.config.selectors.categorySelect).on("change", function (e) {
      self.state.currentCategory = $(this).val();
      const searchTerm = $(self.config.selectors.searchInput).val();

      if (searchTerm) {
        self.performSearch(searchTerm);
      } else {
        self.filterByCategory(self.state.currentCategory);
      }
    });
  },

  bindEvents() {
    const $searchInput = $(this.config.selectors.searchInput);
    const self = this;

    $searchInput.on("input", function (e) {
      if (self.state.searchTimeout) {
        clearTimeout(self.state.searchTimeout);
      }

      self.state.searchTimeout = setTimeout(() => {
        const searchTerm = $(this).val();
        self.performSearch(searchTerm);
      }, self.config.debounceTime);
    });

    $searchInput.on("clear", () => this.resetToInitialState());
  },

  performSearch(searchTerm) {
    console.group("Searching products:", searchTerm);

    const term = searchTerm.toLowerCase().trim();

    if (term.length < this.state.minSearchLength) {
      this.resetToInitialState();
      console.groupEnd();
      return;
    }

    // Sembunyikan semua produk dan container terlebih dahulu
    this.hideAllElements();

    // Cari produk yang cocok
    const matchingElements = {
      products: new Set(),
      containers: new Set(),
      categories: new Set(),
    };

    let visibleCount = 0;

    // Proses pencarian
    this.state.productCache.forEach((product) => {
      const matchesCategory =
        this.state.currentCategory === "all" ||
        product.categoryId === this.state.currentCategory;
      const matchesSearch =
        product.name.includes(term) || product.desc.includes(term);

      if (matchesCategory && matchesSearch) {
        matchingElements.products.add(product.element);
        matchingElements.containers.add(product.container);
        matchingElements.categories.add(product.category);
        visibleCount++;
      }
    });

    // Tampilkan elemen yang cocok
    if (visibleCount > 0) {
      matchingElements.products.forEach((product) => {
        $(product).show();
      });

      matchingElements.containers.forEach((container) => {
        $(container).show();
      });

      matchingElements.categories.forEach((category) => {
        $(category).show();
      });
    }

    this.updateSearchResults(visibleCount, term);
    console.log(`Found ${visibleCount} matching products`);
    console.groupEnd();
  },

  hideAllElements() {
    $(this.config.selectors.productCard).hide();
    $(this.config.selectors.productContainer).hide();
    $(this.config.selectors.productCategory).hide();
    $("#search-results, #search-empty-message").remove();
  },

  updateSearchResults(count, term) {
    const $productListing = $(this.config.selectors.productListing);
    const categoryName =
      this.state.currentCategory === "all"
        ? ""
        : ` dalam kategori "${$(this.config.selectors.categorySelect)
            .find("option:selected")
            .text()}"`;

    if (count === 0) {
      const emptyMessage = `
        <div id="search-empty-message" class="alert alert-info text-center my-4">
          <i class="bi bi-search me-2"></i>
          Tidak ditemukan produk dengan kata kunci "${term}"${categoryName}
        </div>`;
      $productListing.append(emptyMessage);
    } else {
      const resultsInfo = `
        <div id="search-results" class="text-end mb-3">
          <small class="text-muted">
            Ditemukan ${count} produk${categoryName}
          </small>
        </div>`;
      $productListing.prepend(resultsInfo);
    }
  },

  resetToInitialState() {
    const $productListing = $(this.config.selectors.productListing);

    if (this.state.containerHTML) {
      $productListing.html(this.state.containerHTML);
      // Reinisialisasi cache setelah reset
      this.initializeProductCache();
    }

    $("#search-results, #search-empty-message").remove();
    $(this.config.selectors.searchInput).val("");

    if (this.state.currentCategory !== "all") {
      this.filterByCategory(this.state.currentCategory);
    }
  },

  filterByCategory(categoryId) {
    this.hideAllElements();

    if (categoryId === "all") {
      $(this.config.selectors.productCard).show();
      $(this.config.selectors.productContainer).show();
      $(this.config.selectors.productCategory).show();
      return;
    }

    const matchingElements = {
      products: new Set(),
      containers: new Set(),
      categories: new Set(),
    };

    this.state.productCache.forEach((product) => {
      if (product.categoryId === categoryId) {
        matchingElements.products.add(product.element);
        matchingElements.containers.add(product.container);
        matchingElements.categories.add(product.category);
      }
    });

    // Tampilkan elemen yang cocok
    matchingElements.products.forEach((product) => {
      $(product).show();
    });

    matchingElements.containers.forEach((container) => {
      $(container).show();
    });

    matchingElements.categories.forEach((category) => {
      $(category).show();
    });
  },
};

$(document).ready(() => {
  try {
    ProductSearch.init();
  } catch (error) {
    console.error('ProductSearch initialization failed:', error);
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Error',
        text: 'Failed to initialize search component. Please refresh the page.',
        icon: 'error'
      });
    } else {
      alert('Failed to initialize search component. Please refresh the page.');
    }
  }
});