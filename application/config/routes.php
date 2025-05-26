<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
*/

// Core System Routes
$route['default_controller'] = 'public/home';
$route['404_override'] = 'errors';
$route['translate_uri_dashes'] = FALSE;

// -------------------------------------------------------------------------
// PUBLIC AREA ROUTES
// -------------------------------------------------------------------------

// Home & Main Pages
$route['home'] = 'public/home';
$route['home/(:any)'] = 'public/home/categories/$1';
$route['home/(:any)'] = 'public/home/index/$1';
$route['banner'] = 'public/banner';
$route['tentang-kami'] = 'public/about';
$route['privacy-policy'] = 'public/privacy';
$route['contact'] = 'public/contact';
$route['faq'] = 'public/faq';

// Products & Categories
$route['products'] = 'public/products';
$route['products/(:num)'] = 'public/products/index/$1';
$route['products/detail_produk'] = 'public/products/detail_produk';
$route['products/category/(:any)'] = 'public/products/detail_kategori/$1';
$route['products/list_product/(:any)'] = 'public/products/detail_sub_kategori/$1';
$route['products/product_detail/(:any)'] = 'public/products/detail_kategori_product/$1';
$route['seasonalcollection'] = 'public/products/seasonal';
$route['products/detail_kategori_product_seasonal/(:any)'] = 'public/products/detail_kategori_product_seasonal/$1';

// Promo & Marketing
// $route['promo'] = 'public/promo';
// $route['promo/(:any)'] = 'public/promo/index/$1';
// $route['ekatalog'] = 'public/ekatalog/index';

// Locations
$route['outlet'] = 'public/outlet';
$route['toko'] = 'public/outlet';

// Career
$route['career'] = 'public/career';
$route['career/pelamar'] = 'public/career/pelamar';

// -------------------------------------------------------------------------
// MEMBER AREA ROUTES
// -------------------------------------------------------------------------

// Authentication
$route['login'] = 'public/login';
$route['login_process'] = 'public/login/login_process';
$route['logout'] = 'public/login/logout';
$route['register'] = 'member/register/register_member';

// Password Recovery
$route['forgot_password'] = 'public/forgot_password';
$route['email_verification'] = 'public/forgot_password/email_verification';
$route['confirmation_password'] = 'public/forgot_password/confirmation_password';
$route['save_new_password'] = 'public/forgot_password/save_new_password';

// Member Dashboard
$route['member'] = 'member/promo';
$route['profile'] = 'member/profile/profile_member';
$route['account'] = 'member/account';
$route['account/detail_history/(:num)'] = 'member/account/detail_history/$1';
$route['account/delete_history/(:num)'] = 'member/account/delete_history/$1';

// -------------------------------------------------------------------------
// MEMBER AUTHENTICATION WITH OTP
// -------------------------------------------------------------------------

// Halaman registrasi
$route['register'] = 'member/register';
$route['member/register'] = 'member/register';

// Route untuk verifikasi nomor telepon
$route['member/register/send_otp'] = 'member/register/send_otp';
$route['member/register/verify_otp'] = 'member/register/verify_otp';

// Route untuk submit data registrasi
$route['member/register/submit_registration'] = 'member/register/submit_registration';

$route['member/auth/login'] = 'member/auth/login';
$route['member/auth/send_otp'] = 'member/auth/send_otp';
$route['member/auth/verify_otp'] = 'member/auth/verify_otp';
$route['member/auth/logout'] = 'member/auth/logout';

// -------------------------------------------------------------------------
// MEMBER DASHBOARD
// -------------------------------------------------------------------------
$route['member/dashboard'] = 'member/dashboard';
$route['member/dashboard/detail_history/(:num)'] = 'member/dashboard/detail_history/$1';

// -------------------------------------------------------------------------
// PRODUCT API ROUTES
// -------------------------------------------------------------------------

// API Search Products
$route['master/products/apiSearchProducts'] = 'master/products/apiSearchProducts';
$route['master/products/syncProductsAndCategories'] = 'master/products/syncProductsAndCategories';

// Product Management
$route['master/products'] = 'master/products';
$route['master/products/index'] = 'master/products/index';
$route['master/products/add'] = 'master/products/add';
$route['master/products/edit/(:num)'] = 'master/products/edit/$1';
$route['master/products/detail/(:num)'] = 'master/products/detail/$1';
$route['master/products/delete/(:num)'] = 'master/products/delete/$1';

// Tambahkan route untuk unlink API
$route['master/products/unlinkFromApi/(:num)'] = 'master/products/unlinkFromApi/$1';

// Product Variant Management
$route['master/products/add_variant/(:num)'] = 'master/products/add_variant/$1';
$route['master/products/edit_variant/(:num)'] = 'master/products/edit_variant/$1';
$route['master/products/delete_varian/(:num)'] = 'master/products/delete_varian/$1';

// Product Sync with API
$route['master/products/sync'] = 'master/products/sync';

// Category Management
$route['master/categories'] = 'master/categories';
$route['master/categories/index'] = 'master/categories/index';
$route['master/categories/add'] = 'master/categories/add';
$route['master/categories/edit/(:num)'] = 'master/categories/edit/$1';
$route['master/categories/detail/(:num)'] = 'master/categories/detail/$1';
$route['master/categories/delete/(:num)'] = 'master/categories/delete/$1';

// Sub-category Management
$route['master/categories/add_sub_cat/(:num)'] = 'master/categories/add_sub_cat/$1';
$route['master/categories/edit_sub_cat/(:num)'] = 'master/categories/edit_sub_cat/$1';
$route['master/categories/delete_sub_cat/(:num)'] = 'master/categories/delete_sub_cat/$1';

// -------------------------------------------------------------------------
// API MRP ROUTES (Access without Administrator Login)
// -------------------------------------------------------------------------

// API Status dan Informasi
$route['api/mrp/ping']['GET'] = 'api_mrp/ping';
$route['api/mrp/status']['GET'] = 'api_mrp/status';
$route['api/mrp/docs']['GET'] = 'api_mrp/docs';
$route['api/mrp/brands']['GET'] = 'api_mrp/brands';
$route['api/mrp/sync_status']['GET'] = 'api_mrp/sync_status';

// API Produk
$route['api/mrp/products']['GET'] = 'api_mrp/products';
$route['api/mrp/product_detail/(:num)']['GET'] = 'api_mrp/product_detail/$1';
$route['api/mrp/search']['GET'] = 'api_mrp/search';
$route['api/mrp/update_product']['POST'] = 'api_mrp/update_product';

// API Kategori
$route['api/mrp/categories']['GET'] = 'api_mrp/categories';
$route['api/mrp/category_detail/(:num)']['GET'] = 'api_mrp/category_detail/$1';
$route['api/mrp/update_category']['POST'] = 'api_mrp/update_category';

// API Log dan Sinkronisasi
$route['api/mrp/log_sync']['POST'] = 'api_mrp/log_sync';

// Promo API Routes
$route['api/promo/sync-vouchers']   = 'apis/PromoApi/syncVouchers';       // Sinkronisasi voucher dari MRP
$route['api/promo/mark-used']       = 'apis/PromoApi/markVoucherAsUsed';  // Menandai voucher sebagai digunakan
$route['api/promo/sync-status']     = 'apis/PromoApi/syncStatus';         // Melihat status sinkronisasi voucher
$route['api/promo/update-status']   = 'apis/PromoApi/updateVoucherStatus'; // Mengubah status aktif/inaktif voucher

// -------------------------------------------------------------------------
// SHOPPING CART & FAVORITE ROUTES
// -------------------------------------------------------------------------

// Cart
$route['cart'] = 'public/cart';
$route['purchase'] = 'public/purchase';
$route['cart/(:num)'] = 'public/cart/index/$1';
$route['cart/redeem'] = 'public/purchase/redeem/';
$route['redeem'] = 'public/purchase/redeem';
$route['cart/update_cart'] = 'public/cart/update_cart';
$route['update_cart'] = 'public/cart/update_cart';
$route['save_cart'] = 'public/cart/save_cart';
$route['cart/save_cart'] = 'public/cart/save_cart';
$route['remove_product'] = 'public/cart/remove_product';
$route['cart/remove_product'] = 'public/cart/remove_product';
$route['countCartProducts'] = 'public/cart/countCartProducts';

// Favorites
$route['favorite'] = 'public/favorite';
$route['save_favorite'] = 'public/favorite/save_favorite';
$route['check_love_favorite'] = 'public/favorite/checkProductFavorite';
$route['remove_favorite'] = 'public/favorite/removeProductFavorite';

// ------------------------------------------------------------------------- 
// PROMO MANAGEMENT ROUTES 
// ------------------------------------------------------------------------- 
$route['promo/MasterPromo'] = 'promo/MasterPromo/index';
$route['promo/MasterPromo/index'] = 'promo/MasterPromo/index';
$route['promo/MasterPromo/create'] = 'promo/MasterPromo/create';
$route['promo/MasterPromo/store'] = 'promo/MasterPromo/store';
$route['promo/MasterPromo/edit/(:num)'] = 'promo/MasterPromo/edit/$1';
$route['promo/MasterPromo/update/(:num)'] = 'promo/MasterPromo/update/$1';
$route['promo/MasterPromo/delete/(:num)'] = 'promo/MasterPromo/delete/$1';
$route['promo/MasterPromo/usage/(:num)'] = 'promo/MasterPromo/usage/$1';
$route['promo/MasterPromo/testPromo'] = 'promo/MasterPromo/testPromo';
$route['promo/MasterPromo/export'] = 'promo/MasterPromo/export';
$route['promo/MasterPromo/validatePromoCode'] = 'promo/MasterPromo/validatePromoCode';

$route['promo/BadgePromo/getActivePromos'] = 'promo/BadgePromo/getActivePromos';

// Promo Application Routes (for order integration)
$route['order/applyPromoToOrder']['POST'] = 'order/order/applyPromoToOrder';
$route['order/removePromo']['POST'] = 'order/order/removePromoFromOrder';
$route['order/validatePromo']['POST'] = 'order/order/validatePromoCode';
$route['order/validatePromoCode'] = 'order/order/validatePromoCode';
$route['order/getPromoSuggestions'] = 'order/order/getPromoSuggestions';


// ------------------------------------------------------------------------- 
// ORDER MANAGEMENT ROUTES - DUAL ACCESS SUPPORT
// -------------------------------------------------------------------------

// Order Routes dengan dukungan parameter kasir
$route['order/getProductDetail']['GET'] = 'order/order/getProductDetail';
$route['order/getPackageDetail/(:num)']['GET'] = 'order/order/getPackageDetail/$1';
$route['order/countCart']['GET'] = 'order/order/countCart';
$route['order/cart']['GET'] = 'order/order/cart';
$route['order/done']['POST'] = 'order/order/doneOrder';

// Session routes - mendukung kasirId parameter
$route['order/session']['GET'] = 'order/order/session';
$route['order/session']['POST'] = 'order/order/createSession';

// Main order route - mendukung baik public maupun kasir access
$route['order']['GET'] = 'order/order/list';

// Order manipulation routes
$route['order/add']['POST'] = 'order/order/add';
$route['order/removeCartItem']['POST'] = 'order/order/removeCartItem';
$route['order/updateQuantity']['POST'] = 'order/order/updateQuantity';

// Session management routes
$route['order/endSession']['POST'] = 'order/order/endSession';
$route['order/updateStatus']['POST'] = 'order/order/updateOrderStatus';

// Notification routes dengan dukungan kasir tracking
$route['order/markSessionsAsRead']['POST'] = 'order/order/markSessionsAsRead';
$route['order/markOrdersAsRead']['POST'] = 'order/order/markOrdersAsRead';
$route['order/check-notifications']['GET'] = 'order/order/checkNotifications';

// Data retrieval routes
$route['order/getData'] = 'order/order/getData';
$route['order/getOrder'] = 'order/order/getOrder';
$route['order/getReceipt'] = 'order/order/getReceipt';

// Waiter Call Routes
$route['order/callWaiter']['POST'] = 'order/order/callWaiter';
$route['order/checkWaiterCalls']['GET'] = 'order/order/checkWaiterCalls';
$route['order/processWaiterCall']['POST'] = 'order/order/processWaiterCall';
$route['order/completeWaiterCall']['POST'] = 'order/order/completeWaiterCall';

// Promo routes dengan dukungan access type validation
$route['order/validatePromoCode'] = 'order/order/validatePromoCode';
$route['order/applyPromoToOrder']['POST'] = 'order/order/applyPromoToOrder';
$route['order/getPromoSuggestions'] = 'order/order/getPromoSuggestions';

// Order History Routes
$route['order/history'] = 'order/orderHistory';
$route['order/history/index'] = 'order/orderHistory/index';
$route['order/history/view/(:num)'] = 'order/orderHistory/view/$1';
$route['order/history/report'] = 'order/orderHistory/report';
$route['order/history/export'] = 'order/orderHistory/export';
$route['order/history/getOrderTimings/(:num)'] = 'order/orderHistory/getOrderTimings/$1';

// Receipt/Struk Routes
$route['order/receipt/print/(:num)'] = 'order/receipt/print/$1';
$route['order/receipt/download/(:num)'] = 'order/receipt/download/$1';
$route['order/receipt/view/(:num)'] = 'order/receipt/view/$1';
$route['order/receipt/getData/(:num)']['GET'] = 'order/receipt/getData/$1';
$route['order/receipt/getItemsData/(:num)']['GET'] = 'order/receipt/getItemsData/$1';

// -------------------------------------------------------------------------
// KASIR SPECIFIC ROUTES - RESTRICTED ACCESS
// -------------------------------------------------------------------------

// Routes khusus yang hanya bisa diakses oleh kasir
$route['kasir/order/priority']['GET'] = 'order/kasir/priorityOrders';
$route['kasir/order/bulkUpdate']['POST'] = 'order/kasir/bulkUpdateOrders';
$route['kasir/dashboard']['GET'] = 'order/kasir/dashboard';
$route['kasir/reports']['GET'] = 'order/kasir/reports';

// Session management khusus kasir
$route['kasir/session/override']['POST'] = 'order/kasir/overrideSession';
$route['kasir/session/forceEnd']['POST'] = 'order/kasir/forceEndSession';


// -------------------------------------------------------------------------
// PACKAGE MANAGEMENT ROUTES
// -------------------------------------------------------------------------

// Package Routes
// $route['master/package'] = 'master/package';
// $route['master/package/index'] = 'master/package/index';
// $route['master/package/detail/(:num)'] = 'master/package/detail/$1';
// $route['master/package/add'] = 'master/package/add';
// $route['master/package/edit/(:num)'] = 'master/package/edit/$1';
// $route['master/package/delete/(:num)'] = 'master/package/delete/$1';
// $route['master/package/add_category/(:any)'] = 'master/package/add_category/$1';
// $route['master/package/edit_category/(:num)'] = 'master/package/edit_category/$1';
// $route['master/package/delete_category/(:num)'] = 'master/package/delete_category/$1';
// $route['master/package/add_product/(:num)'] = 'master/package/add_product/$1';
// $route['master/package/add_product_bulk/(:num)'] = 'master/package/add_product_bulk/$1';
// $route['master/package/edit_product/(:num)'] = 'master/package/edit_product/$1';
// $route['master/package/delete_product/(:num)'] = 'master/package/delete_product/$1';
// $route['master/package/update_product_pricing/(:num)'] = 'master/package/update_product_pricing/$1';
// $route['master/package/clone_package/(:num)'] = 'master/package/clone_package/$1';
// $route['master/package/manage_stock/(:num)'] = 'master/package/manage_stock/$1';
// $route['master/package/update_product_stock/(:num)'] = 'master/package/update_product_stock/$1';

// -------------------------------------------------------------------------
// ADMINISTRATOR ROUTES
// -------------------------------------------------------------------------

// Admin Authentication
$route['administrator'] = 'users/login';
$route['administrator/logout'] = 'users/login/logout';
$route['administrator/login_process'] = 'users/login/login_process';

// Admin Dashboard
$route['administrator/dashboard'] = 'users/data/dashboard';
$route['administrator/profile'] = 'users/data';

/* 
  Commented out routes - these appear to be placeholder routes or routes being developed
  They are preserved here for reference but are currently inactive

// Admin Products Management
// $route['administrator/tambahproduk'] = 'master/tambah_produk';
// $route['administrator/products'] = 'master/products';
// $route['administrator/products/ratings'] = 'master/products/ratings';
// $route['administrator/products/ratings/(:any)'] = 'master/products/ratings/1';

// Admin Categories Management
// $route['administrator/products'] = 'master/categories';
// $route['administrator/products/(:any)'] = 'master/categories/ratings';
// $route['administrator/products/ratings/(:any)'] = 'master/categories/ratings/1';

// Admin User Management
// $route['administrator/users'] = 'master/users';
// $route['administrator/users/(:any)'] = 'master/users/1';
// $route['administrator/users/role/(:any)'] = 'master/users/role/1';

// Admin Settings
// $route['administrator/settings'] = 'settings/applications';
// $route['administrator/menus'] = 'settings/menus';
// $route['administrator/menus/(:any)'] = 'settings/menus/role';
// $route['administrator/menus/(:any)/(:any)'] = 'settings/menus/role/1';
// $route['administrator/roles'] = 'settings/roles';
// $route['administrator/roles/(:any)'] = 'settings/roles/role';
// $route['administrator/roles/(:any)/(:any)'] = 'settings/roles/user/1';
// $route['administrator/outlets'] = 'settings/outlets';
// $route['administrator/preferences'] = 'settings/preferences';
*/