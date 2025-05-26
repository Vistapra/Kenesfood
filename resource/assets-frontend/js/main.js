if ($(".outlets-page").length) {
  var listGroup = $(".list-group").clone();
  listGroup.addClass("dropdown-list-group");
  listGroup.removeClass("list-group");
  listGroup.appendTo(".dropdown-city-outlet");
}

function changeDropdownText(selectedText) {
  const dropdownButton = document.getElementById("dropdown-list");
  dropdownButton.innerText = selectedText;
}

if ($(".account-container").length) {
  var listGroup = $(".list-group").clone();
  listGroup.addClass("dropdown-list-group");
  listGroup.removeClass("list-group");
  listGroup.appendTo(".dropdown-Account-Member");
}
if ($(".faq-container").length) {
  var listGroup = $(".list-group").clone();
  listGroup.addClass("dropdown-list-group");
  listGroup.removeClass("list-group");
  listGroup.appendTo(".dropdown-faq");
}

if ($(".products-container").length) {
  var listGroups = $(".list-group").clone();
  listGroups.addClass("dropdown-list-product-group");
  listGroups.removeClass("list-group");
  listGroups.appendTo(".dropdown-category-product-list");

  // Fungsi untuk mengaktifkan elemen berdasarkan parameter URL
  function activateTabFromURL() {
    var hash = window.location.hash;
    if (hash) {
      $(".tab-pane").removeClass("active show");
      $(hash).addClass("active show");
      $('.list-group-item[data-bs-toggle="list"]').removeClass("active");
      $('.list-group-item[href="' + hash + '"]').addClass("active");
    }
  }

  const selectOutlet = document.getElementById("selectOutlet");
  const linkWa = document.querySelectorAll(".waLink");
  const alertOutlet = document.querySelector(".alert-outlet");

  linkWa.forEach((klikWA) => {
    klikWA.addEventListener("click", function (event) {
      const selectedValue = selectOutlet.value;
      if (selectedValue === "") {
        event.preventDefault(); // Mencegah pengguna mengikuti tautan jika select belum dipilih

        alertOutlet.innerHTML = `<div class="alert alert-secondary alert-dismissible fade show alert-outlet" role="alert">
          Silahkan Anda Memilih Outlet Dahulu
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
      }
    });
  });

  // Tambahkan event listener untuk mendeteksi perubahan pada select
  selectOutlet.addEventListener("change", function () {
    // Dapatkan nilai terpilih dari select
    const selectedValue = selectOutlet.value;
    // Update atribut href pada elemen a berdasarkan nilai terpilih

    linkWa.forEach((link) => {
      link.href = generateWhatsAppLink(selectedValue);
    });
    alertOutlet.innerHTML = `<div class="alert alert-secondary alert-dismissible fade show alert-outlet" role="alert">
       Anda sedang berada di Outlet <b>${selectedValue}</b>
       <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
     </div>`;
  });

  function generateWhatsAppLink(selectedValue) {
    let phoneNumber;
    switch (selectedValue) {
      case "Travel Spot":
        phoneNumber = "6282337379090";
        break;
      case "Kenes Bakery Wijayakusuma":
        phoneNumber = "6287839912131";
        break;
      case "Kenes Bakery Kabupaten":
        phoneNumber = "6281333252333";
        break;
      case "Kenes Bakery Godean":
        phoneNumber = "6287838005900";
        break;
      case "Kenes Bakery Gejayan":
        phoneNumber = "6285875835330";
        break;
      case "Kenes Bakery Kusumanegara":
        phoneNumber = "6282334555757";
        break;
      case "Kopi Tiam Kusumanegara":
        phoneNumber = "6281930030333";
        break;
      case "Kenes Bakery RS Siloam":
        phoneNumber = "628783847077";
        break;
      case "Kenes Bakery RS Panti Rapih":
        phoneNumber = "6287877616777";
        break;
      case "Kenes Bakery RS UII":
        phoneNumber = "6287785550222";
        break;
      default:
        phoneNumber = ""; // Nomor default jika tidak ada yang dipilih
        break;
    }
    text =
      "Halo Saya ingin Memesan Produk (isi dengan nama produk). Apakah Stok masih tersedia ?";
    return `https://wa.me/${phoneNumber}?text=${text}`;
  }
}

if ($(".location").length) {
  var x = document.getElementById("my-location");
  // var m = document.getElementById("my-location-layar-mobile");

  // if (window.innerWidth <= 1024) {
  //   var x = m;
  // } else {
  //   var x = d;
  // }

  //mendapatkan latitude dan longtitude
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(showPosition, showError);
  } else {
    x.innerHTML = "Geolocation is not supported by this browser.";
  }

  function showPosition(position) {
    var latitude = position.coords.latitude;
    var longitude = position.coords.longitude;
    console.log(latitude);
    console.log(longitude);

    // // Buat permintaan ke Maps API menggunakan AJAX
    // const url = `https://geocode.maps.co/reverse?lat=${latitude}&lon=${longitude}`;
    // const xhr = new XMLHttpRequest();
    // xhr.open("GET", url, true);
    // xhr.onreadystatechange = function () {
    //   if (xhr.readyState === 4) {
    //     if (xhr.status === 200) {
    //       const response = JSON.parse(xhr.responseText);
    //       const locationDetails = response.display_name;
    //       x.innerHTML = `Lokasi: ${locationDetails}`;
    //     } else {
    //       x.innerHTML = "Gagal mendapatkan informasi lokasi.";
    //     }
    //   }
    // };
    // xhr.send();

    const locations = [
      {
        name: "Kenes Travel Spot",
        latitude: -7.7582659,
        longitude: 110.3576855,
        link: "https://goo.gl/maps/CV1ZCtUmEgEpo6Ky9",
      },
      {
        name: "Kenes Bakery Kabupaten",
        latitude: -7.750895,
        longitude: 110.3450154,
        link: "https://goo.gl/maps/9YmDtwafSMeFrq3f8",
      },
      {
        name: "Kenes Bakery Wijayakusuma",
        latitude: -7.7582508,
        longitude: 110.3604753,
        link: "https://goo.gl/maps/9YmDtwafSMeFrq3f8",
      },
      {
        name: "Kopitiam Kusumanegara",
        latitude: -7.8021349905571675,
        longitude: 110.38881951984676,
        link: "https://goo.gl/maps/Bcet9T1Gt9zCebJt9",
      },
      {
        name: "Kenes Bakery Rs Panti Rapih",
        latitude: -7.776330028526438,
        longitude: 110.37769973869428,
        link: "https://goo.gl/maps/wMPFsFPUA9oXo5MBA",
      },
      {
        name: "Kenes Bakery Rs UII",
        latitude: -7.909078210219242,
        longitude: 110.29571073745868,
        link: "https://goo.gl/maps/eAS4EzxhzMpW1TBu8",
      },
      {
        name: "Kenes Bakery Godean",
        latitude: -7.76667451943364,
        longitude: 110.29555585590015,
        link: "https://goo.gl/maps/eAS4EzxhzMpW1TBu8",
      },
      {
        name: "Kenes Bakery Rs Siloam",
        latitude: -7.783160642352611,
        longitude: 110.39082455218922,
        link: "https://goo.gl/maps/H2oE5MmqZPQy5iz78",
      },

      // Tambahkan lokasi lainnya sesuai kebutuhan
    ];

    let nearestLocations = [];
    locations.forEach((location) => {
      const distance = getDistanceFromLatLonInKm(
        latitude,
        longitude,
        location.latitude,
        location.longitude
      );
      nearestLocations.push({ name: location.name, distance: distance });

      // Menentukan class elemen target berdasarkan nama lokasi

      if (location.name == "Kenes Travel Spot") {
        const locationElement = document.querySelectorAll(".jarak34");
        for (let i = 0; i < locationElement.length; i++) {
          locationElement[i].innerHTML = `Jarak: ${distance.toFixed(2)} km`;
        }
      } else if (location.name == "Kenes Bakery Kabupaten") {
        const locationElement = document.querySelectorAll(".jarak37");
        for (let i = 0; i < locationElement.length; i++) {
          locationElement[i].innerHTML = `Jarak: ${distance.toFixed(2)} km`;
        }
      } else if (location.name == "Kenes Bakery Wijayakusuma") {
        const locationElement = document.querySelectorAll(".jarak35");
        for (let i = 0; i < locationElement.length; i++) {
          locationElement[i].innerHTML = `Jarak: ${distance.toFixed(2)} km`;
        }
      } else if (location.name == "Kopitiam Kusumanegara") {
        const locationElement = document.querySelectorAll(".jarak41");
        for (let i = 0; i < locationElement.length; i++) {
          locationElement[i].innerHTML = `Jarak: ${distance.toFixed(2)} km`;
        }
      } else if (location.name == "Kenes Bakery Rs Panti Rapih") {
        const locationElement = document.querySelectorAll(".jarak38");
        for (let i = 0; i < locationElement.length; i++) {
          locationElement[i].innerHTML = `Jarak: ${distance.toFixed(2)} km`;
        }
      } else if (location.name == "Kenes Bakery Rs UII") {
        const locationElement = document.querySelectorAll(".jarak40");
        for (let i = 0; i < locationElement.length; i++) {
          locationElement[i].innerHTML = `Jarak: ${distance.toFixed(2)} km`;
        }
      } else if (location.name == "Kenes Bakery Godean") {
        const locationElement = document.querySelectorAll(".jarak39");
        for (let i = 0; i < locationElement.length; i++) {
          locationElement[i].innerHTML = `Jarak: ${distance.toFixed(2)} km`;
        }
      } else if (location.name == "Kenes Bakery Rs Siloam") {
        const locationElement = document.querySelectorAll(".jarak42");
        for (let i = 0; i < locationElement.length; i++) {
          locationElement[i].innerHTML = `Jarak: ${distance.toFixed(2)} km`;
        }
      }

      location.distance = distance;
    });

    // Urutkan array locations berdasarkan jarak terdekat
    locations.sort((a, b) => a.distance - b.distance);

    // Ambil 3 lokasi terdekat
    const nearestLocation = locations.slice(0, 3);

    nearestLocation.forEach((location) => {
      const link = location.link
        ? `<a href="${location.link}" target="_blank">${location.name}</a>`
        : location.name;
      x.innerHTML += `${link} ${location.distance.toFixed(2)} km<br>`;
    });
  }

  function showError(error) {
    switch (error.code) {
      case error.PERMISSION_DENIED:
        x.innerHTML = "User denied the request for Geolocation.";
        break;
      case error.POSITION_UNAVAILABLE:
        x.innerHTML = "Location information is unavailable.";
        break;
      case error.TIMEOUT:
        x.innerHTML = "The request to get user location timed out.";
        break;
      case error.UNKNOWN_ERROR:
        x.innerHTML = "An unknown error occurred.";
        break;
    }
  }

  function deg2rad(deg) {
    return deg * (Math.PI / 180);
  }

  function getDistanceFromLatLonInKm(lat1, lon1, lat2, lon2) {
    const R = 6371; // Radius of the earth in km
    const dLat = deg2rad(lat2 - lat1);
    const dLon = deg2rad(lon2 - lon1);
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(deg2rad(lat1)) *
        Math.cos(deg2rad(lat2)) *
        Math.sin(dLon / 2) *
        Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    const distance = R * c;
    return distance;
  }

  // Memanggil fungsi activateTabFromURL setelah halaman selesai dimuat
  // activateTabFromURL();
}

function hitungLove() {
  const loveCounter = document.querySelector(".love-counter");

  let count = 0;
  for (let i = 0; i < localStorage.length; i++) {
    const key = localStorage.key(i);
    if (key.startsWith("loveIcon-") && localStorage.getItem(key) === "true") {
      count++;
    }
  }
  loveCounter.innerText = count;

  // Menyembunyikan elemen love counter jika nilainya adalah 0
  if (count === 0) {
    loveCounter.classList.add("hidden");
  } else {
    loveCounter.classList.remove("hidden");
  }
}

class Popup {
  constructor(selector, options) {
    this.modal = document.querySelector(selector);
    this.options = options || {};
  }
  open() {
    this.modal.style.display = "block";
  }

  close() {
    this.modal.style.display = "none";
  }
}

document.addEventListener("DOMContentLoaded", function () {
  if ($(".love-counter").length > 0) {
    hitungLove();
    check_icon_heart();
    Fancybox.bind("[data-fancybox]", {
      //
    });
  }
});

if ($(".modal-promo").length) {
  var modal = new Popup("#card-promo", {
    // Optional: Add options here (e.g., animation, close button, etc.)
  });
  modal.open();

  function closeModal() {
    modal.close();
  }
}

function check_icon_heart() {
  const favoriteButtons = document.querySelectorAll(".best-favorite");

  // Loop melalui setiap tombol "love"
  favoriteButtons.forEach(function (favorite) {
    var productId = favorite.getAttribute("data-product-id");
    var data = {
      product_id: productId,
    };
    var xhr = new XMLHttpRequest();

    // Konfigurasi permintaan
    xhr.open("POST", "check_love_favorite", true);
    xhr.setRequestHeader("Content-Type", "application/json");

    // Definisikan fungsi panggilan kembali
    xhr.onreadystatechange = function () {
      if (xhr.readyState === XMLHttpRequest.DONE) {
        if (xhr.status === 200) {
          try {
            var response = JSON.parse(xhr.responseText);
            if (response.message === "Found") {
              // Aktifkan warna pada ikon yang sesuai dengan product_id
              var icon = document.querySelectorAll(".best-favorite");
              icon.forEach(function (iconFavorite) {
                var cek = iconFavorite.getAttribute("data-product-id");
                if (cek === productId) {
                  iconFavorite.classList.add("active");
                }
              });
            } else {
              console.log(response.message);
            }
          } catch (error) {
            console.log("Kesalahan parsing JSON:", error);
          }
        } else {
          // Terjadi kesalahan HTTP
          console.log("Terjadi kesalahan HTTP: " + xhr.status);
        }
      }
    };

    // xhr.onreadystatechange = function () {
    // 	if (xhr.readyState === XMLHttpRequest.DONE) {
    // 		if (xhr.status === 200) {
    // 			var response = JSON.parse(xhr.responseText);
    // 			if (response.message === "Found") {
    // 				// Aktifkan warna pada ikon yang sesuai dengan product_id
    // 				var icon = document.querySelector(
    // 					'.best-favorite[data-product-id="' + productId + '"]'
    // 				);
    // 				if (icon) {
    // 					icon.classList.add("active");
    // 				}
    // 			} else {
    // 				console.log(response.message);
    // 			}
    // 		} else {
    // 			// Terjadi kesalahan
    // 			console.log("Terjadi kesalahan");
    // 		}
    // 	}
    // };

    xhr.send(JSON.stringify(data));
  });
}

if ($(".favorite-container").length) {
  const loveIcons = document.querySelectorAll(".best-favorite");
  //const member_id = document.getElementById("id_member");
  const member_id = document.getElementById("id_member").value;

  loveIcons.forEach(function (loveIcon) {
    loveIcon.addEventListener("click", function () {
      var icon = this; // Menggunakan 'this' untuk mengambil tombol "love" yang diklik
      if (icon.classList.contains("active")) {
        icon.classList.remove("active");

        // Tambahkan kode untuk menghapus produk dari database di sini (menggunakan AJAX)
        var product_id = icon.getAttribute("data-product-id");

        var data = {
          user_id: member_id,
          product_id: product_id,
        };

        var xhr = new XMLHttpRequest();

        // Konfigurasi permintaan
        xhr.open("POST", "remove_favorite", true);
        xhr.setRequestHeader("Content-Type", "application/json");

        // Definisikan fungsi panggilan kembali
        xhr.onreadystatechange = function () {
          if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
              location.reload(true);
              // Data berhasil dihapus
              console.log("Data berhasil dihapus dari favorit");
            } else {
              // Terjadi kesalahan
              console.log("Terjadi kesalahan saat menghapus data dari favorit");
            }
          }
        };

        // Kirim permintaan
        xhr.send(JSON.stringify(data));
      } else {
        // Produk belum dalam daftar favorit, tambahkan class 'active' dan tambahkan kode untuk menambah produk ke database di sini (menggunakan AJAX)
        icon.classList.add("active");

        var product_id = icon.getAttribute("data-product-id");
        var data = {
          user_id: member_id,
          product_id: product_id,
        };

        var xhr = new XMLHttpRequest();

        // Konfigurasi permintaan
        xhr.open("POST", "save_favorite", true);
        xhr.setRequestHeader("Content-Type", "application/json");

        // Definisikan fungsi panggilan kembali
        xhr.onreadystatechange = function () {
          if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
              // Data berhasil disimpan
              console.log("Data berhasil disimpan sebagai favorit");
            } else {
              // Terjadi kesalahan
              console.log(
                "Terjadi kesalahan saat menyimpan data sebagai favorit"
              );
            }
          }
        };

        // Kirim permintaan
        xhr.send(JSON.stringify(data));
      }
    });
  });

  const cartIcons = document.querySelectorAll(".add-to-cart-button");
  // const product_id = document.getElementById('product_id').value;
  //const member_id = document.getElementById("id_member").value;
  cartIcons.forEach(function (cartIcon) {
    cartIcon.addEventListener("click", function () {
      var product_id = this.getAttribute("data-product-id");
      var product_price = this.getAttribute("data-price");
      var data = {
        user_id: member_id,
        product_id: product_id,
        product_price: product_price,
      };

      // Tampilkan konfirmasi dialog
      alertify.confirm(
        "notifikasi",
        "Add Product from Cart?",
        function () {
          var xhr = new XMLHttpRequest();

          // Konfigurasi permintaan
          xhr.open("POST", "save_cart", true);
          xhr.setRequestHeader("Content-Type", "application/json");

          // Definisikan fungsi panggilan kembali
          xhr.onreadystatechange = function () {
            if (xhr.readyState === XMLHttpRequest.DONE) {
              if (xhr.status === 200) {
                // Data berhasil disimpan
                console.log("Data berhasil disimpan");
              } else {
                // Terjadi kesalahan
                console.log("Terjadi kesalahan saat menyimpan data");
              }
            }
          };
          // Kirim permintaan
          xhr.send(JSON.stringify(data));
        },
        function () {
          // Jika pengguna membatalkan, tidak perlu melakukan apa-apa
          alertify.error("Cancel");
        }
      );
    });
  });
}

if ($(".products-container").length) {
  const loveIcons = document.querySelectorAll(".best-favorite");
  const member_id = document.getElementById("id_member").value;

  loveIcons.forEach(function (loveIcon) {
    loveIcon.addEventListener("click", function () {
      var icon = this; // Menggunakan 'this' untuk mengambil tombol "love" yang diklik
      if (icon.classList.contains("active")) {
        icon.classList.remove("active");

        // Tambahkan kode untuk menghapus produk dari database di sini (menggunakan AJAX)
        var product_id = icon.getAttribute("data-product-id");
        var data = {
          user_id: member_id,
          product_id: product_id,
        };

        var xhr = new XMLHttpRequest();

        // Konfigurasi permintaan
        xhr.open("POST", "remove_favorite", true);
        xhr.setRequestHeader("Content-Type", "application/json");

        // Definisikan fungsi panggilan kembali
        xhr.onreadystatechange = function () {
          if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
              location.reload(true);
              // Data berhasil dihapus
              console.log("Data berhasil dihapus dari favorit");
            } else {
              // Terjadi kesalahan
              console.log("Terjadi kesalahan saat menghapus data dari favorit");
            }
          }
        };

        // Kirim permintaan
        xhr.send(JSON.stringify(data));
      } else {
        // Produk belum dalam daftar favorit, tambahkan class 'active' dan tambahkan kode untuk menambah produk ke database di sini (menggunakan AJAX)
        icon.classList.add("active");

        var product_id = icon.getAttribute("data-product-id");
        var data = {
          user_id: member_id,
          product_id: product_id,
        };

        var xhr = new XMLHttpRequest();

        // Konfigurasi permintaan
        xhr.open("POST", "save_favorite", true);
        xhr.setRequestHeader("Content-Type", "application/json");

        // Definisikan fungsi panggilan kembali
        xhr.onreadystatechange = function () {
          if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
              location.reload(true);
              // Data berhasil disimpan
              console.log("Data berhasil disimpan sebagai favorit");
            } else {
              // Terjadi kesalahan
              console.log(
                "Terjadi kesalahan saat menyimpan data sebagai favorit"
              );
            }
          }
        };

        // Kirim permintaan
        xhr.send(JSON.stringify(data));
      }
    });
  });

  const cartIcons = document.querySelectorAll(".add-to-cart-button");
  // const product_id = document.getElementById('product_id').value;
  //const member_id = document.getElementById("id_member").value;
  cartIcons.forEach(function (cartIcon) {
    cartIcon.addEventListener("click", function () {
      var product_id = this.getAttribute("data-product-id");
      var product_price = this.getAttribute("data-price");
      var data = {
        user_id: member_id,
        product_id: product_id,
        product_price: product_price,
      };

      // Tampilkan konfirmasi dialog
      alertify.confirm(
        "Notifikasi",
        "Add Product from Cart?",
        function () {
          var xhr = new XMLHttpRequest();

          // Konfigurasi permintaan

          xhr.open("POST", "save_cart", true);
          xhr.setRequestHeader("Content-Type", "application/json");

          // Definisikan fungsi panggilan kembali
          xhr.onreadystatechange = function () {
            if (xhr.readyState === XMLHttpRequest.DONE) {
              if (xhr.status === 200) {
                location.reload(true);
                // Data berhasil disimpan
                console.log("Data berhasil disimpan");
              } else {
                // Terjadi kesalahan
                console.log("Terjadi kesalahan saat menyimpan data");
              }
            }
          };
          // Kirim permintaan
          xhr.send(JSON.stringify(data));
        },
        function () {
          // Jika pengguna membatalkan, tidak perlu melakukan apa-apa
          alertify.error("Cancel");
        }
      );
    });
  });
}

if ($(".cart-counter").length) {
  // Di dalam script JavaScript Anda

  const cartBadge = document.getElementById("cart-badge");
  var member_id = cartBadge.getAttribute("data-member-id");
  var purchase_member = member_id !== "" ? member_id : "";

  if (purchase_member) {
    const dataToSend = {
      purchase_member: purchase_member,
    };

    const requestOptions = {
      method: "POST", // HTTP method
      headers: {
        "Content-Type": "application/json", // Specify the content type
        // Add any other headers you may need (e.g., authentication headers)
      },
      body: JSON.stringify(dataToSend), // Convert data to JSON format
    };
    var url = "countCartProducts";

    fetch(url, requestOptions) // Ganti '/your_controller/' sesuai dengan URL controller Anda
      .then((response) => response.json())
      .then((data) => {
        const total_product = data.total_product;
        updateCartBadge(total_product);
      })
      .catch((error) => console.error("Error:", error));

    // Fungsi untuk memperbarui badge keranjang belanja
    function updateCartBadge(total_product) {
      cartBadge.innerText = total_product;
    }
  }
  // Fungsi untuk memuat jumlah produk dari controller
}
if ($(".detail-container").length) {
  const cartIcons = document.querySelectorAll(".add-to-cart-button");
  const member_id = document.getElementById("id_member").value;
  // const product_id = document.getElementById('product_id').value;

  cartIcons.forEach(function (cartIcon) {
    cartIcon.addEventListener("click", function () {
      var product_id = this.getAttribute("data-product-id");
      var product_price = this.getAttribute("data-price");
      var data = {
        user_id: member_id,
        product_id: product_id,
        product_price: product_price,
      };

      // Tampilkan konfirmasi dialog
      alertify.confirm(
        "Notifikasi",
        "Add Product from Cart?",
        function () {
          var xhr = new XMLHttpRequest();

          // Konfigurasi permintaan
          xhr.open("POST", "../save_cart", true);
          xhr.setRequestHeader("Content-Type", "application/json");

          // Definisikan fungsi panggilan kembali
          xhr.onreadystatechange = function () {
            if (xhr.readyState === XMLHttpRequest.DONE) {
              if (xhr.status === 200) {
                location.reload(true);
                // Data berhasil disimpan
                console.log("Data berhasil disimpan");
              } else {
                // Terjadi kesalahan
                console.log("Terjadi kesalahan saat menyimpan data");
              }
            }
          };
          // Kirim permintaan
          xhr.send(JSON.stringify(data));
        },
        function () {
          // Jika pengguna membatalkan, tidak perlu melakukan apa-apa
          alertify.error("Cancel");
        }
      );
    });
  });
}

if ($(".cart-container").length) {
  const removeButtons = document.querySelectorAll(".remove-product");
  const member_id = document.getElementById("id_member").value;
  // const member_id = document.getElementById("id_member").value;

  removeButtons.forEach(function (removeButton) {
    removeButton.addEventListener("click", function () {
      var product_id = this.getAttribute("data-product-id");
      var purchase_id = this.getAttribute("data-purchase-id");
      var data = {
        product_id: product_id,
        purchase_id: purchase_id,
      };

      // Tampilkan konfirmasi dialog
      alertify.confirm(
        "Notifikasi",
        "Remove Product from Cart?",
        function () {
          // Jika pengguna mengkonfirmasi, hapus produk
          var xhr = new XMLHttpRequest();
          xhr.open("POST", "remove_product", true);
          xhr.setRequestHeader("Content-Type", "application/json");

          xhr.onreadystatechange = function () {
            if (xhr.readyState === XMLHttpRequest.DONE) {
              if (xhr.status === 200) {
                location.reload(true);
                // Produk berhasil dihapus
                console.log("Produk berhasil dihapus");
                // Kemungkinan lainnya adalah memperbarui tampilan keranjang setelah produk dihapus
                // Misalnya, dengan memperbarui daftar produk yang ditampilkan.
                // Jika Anda memiliki fungsi yang menghapus produk dari tampilan, Anda bisa memanggilnya di sini.
              } else {
                // Terjadi kesalahan
                console.log("Terjadi kesalahan saat menghapus produk");
              }
            }
          };

          xhr.send(JSON.stringify(data));
        },
        function () {
          // Jika pengguna membatalkan, tidak perlu melakukan apa-apa
          alertify.error("Cancel");
        }
      );
    });
  });

  const cartIcons = document.querySelectorAll(".add-to-cart-button");
  // const product_id = document.getElementById('product_id').value;

  cartIcons.forEach(function (cartIcon) {
    cartIcon.addEventListener("click", function () {
      var product_id = this.getAttribute("data-product-id");
      var product_price = this.getAttribute("data-price");
      var data = {
        user_id: member_id,
        product_id: product_id,
        product_price: product_price,
      };

      // Tampilkan konfirmasi dialog
      alertify.confirm(
        "Notifikasi",
        "Add Product from Cart?",
        function () {
          var xhr = new XMLHttpRequest();

          // Konfigurasi permintaan
          xhr.open("POST", "save_cart", true);
          xhr.setRequestHeader("Content-Type", "application/json");

          // Definisikan fungsi panggilan kembali
          xhr.onreadystatechange = function () {
            if (xhr.readyState === XMLHttpRequest.DONE) {
              if (xhr.status === 200) {
                location.reload(true);
                // Data berhasil disimpan
                console.log("Data berhasil disimpan");
              } else {
                // Terjadi kesalahan
                console.log("Terjadi kesalahan saat menyimpan data");
              }
            }
          };
          // Kirim permintaan
          xhr.send(JSON.stringify(data));
        },
        function () {
          // Jika pengguna membatalkan, tidak perlu melakukan apa-apa
          alertify.error("Cancel");
        }
      );
    });
  });
}

if ($(".prelauncing").length) {
  // JavaScript
  function showTime() {
    // Tanggal target
    const targetDate = new Date("2023-07-31 17:20:00");
    const formattedDate = `${targetDate
      .getDate()
      .toString()
      .padStart(2, "0")}-${(targetDate.getMonth() + 1)
      .toString()
      .padStart(2, "0")}-${targetDate.getFullYear()}`;

    // Tanggal hari ini
    const today = new Date();

    // Hitung selisih antara tanggal target dan tanggal hari ini dalam milisecond
    const timeDiff = targetDate.getTime() - today.getTime();

    // Hitung jumlah hari dari selisih waktu dalam milisecond
    const daysDiff = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));

    document.getElementById("MyDateDisplay").innerText = formattedDate;

    const seconds = Math.floor(timeDiff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);

    const remainingSeconds = seconds % 60;
    const remainingMinutes = minutes % 60;
    const remainingHours = hours % 24;

    const countdownDisplay = document.getElementById("MyClockDisplay");
    if (timeDiff <= 0) {
      // Jika selisih waktu kurang dari atau sama dengan 0 (artinya waktu telah melewati targetDate), hentikan tampilan waktu
      countdownDisplay.innerHTML = `<h5 >Sekarang Produk Bisa Dinikmati di Berbagai Outlet Kenes & Bakery </h5> <div class="clearfix text-center">
        <a class="icon-link icon-link-hover text-capitalize fw-medium mb-2 text-nowrap link-dark" href="outlet.html" style="font-size: 20px;">
            Find Outlet More
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shop" viewBox="0 0 16 16">
                <path d="M2.97 1.35A1 1 0 0 1 3.73 1h8.54a1 1 0 0 1 .76.35l2.609 3.044A1.5 1.5 0 0 1 16 5.37v.255a2.375 2.375 0 0 1-4.25 1.458A2.371 2.371 0 0 1 9.875 8 2.37 2.37 0 0 1 8 7.083 2.37 2.37 0 0 1 6.125 8a2.37 2.37 0 0 1-1.875-.917A2.375 2.375 0 0 1 0 5.625V5.37a1.5 1.5 0 0 1 .361-.976l2.61-3.045zm1.78 4.275a1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0 1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0 1.375 1.375 0 1 0 2.75 0V5.37a.5.5 0 0 0-.12-.325L12.27 2H3.73L1.12 5.045A.5.5 0 0 0 1 5.37v.255a1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0zM1.5 8.5A.5.5 0 0 1 2 9v6h1v-5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v5h6V9a.5.5 0 0 1 1 0v6h.5a.5.5 0 0 1 0 1H.5a.5.5 0 0 1 0-1H1V9a.5.5 0 0 1 .5-.5zM4 15h3v-5H4v5zm5-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1v-3zm3 0h-2v3h2v-3z"/>
              </svg>
        </a>
    </div>`;
    } else if (hours < 12) {
      // Jika selisih waktu kurang dari 12 jam, tampilkan elemen countdownDisplay
      countdownDisplay.innerHTML = `${remainingHours} jam ${remainingMinutes} menit ${remainingSeconds} detik`;
    } else {
      document.getElementById("MyHariDisplay").innerHTML = `${daysDiff} Hari!`;
      // Jika selisih waktu lebih dari atau sama dengan 12 jam, hilangkan elemen countdownDisplay
      countdownDisplay.innerHTML = "";
    }

    if (timeDiff > 0) {
      // Jika selisih waktu masih lebih besar dari 0 (artinya targetDate belum terpenuhi), lanjutkan pemanggilan rekursif showTime()
      setTimeout(showTime, 1000);
    }
  }

  showTime();
}

if ($(".cart-container").length) {
  // Ambil semua elemen dengan kelas "input-group"
  const carts = document.querySelectorAll(".cart-quantity");
  var id_user = document.getElementById("user_id");
  var purchase_member = id_user.value;
  // Tambahkan event listener untuk setiap grup input
  carts.forEach((cart) => {
    const quantityField = cart.querySelector(".quantity-field");
    const buttonMinus = cart.querySelector(".button-minus");
    const buttonPlus = cart.querySelector(".button-plus");
    const price = cart.querySelector(".price-cart");
    const subPrice = cart.querySelector("#sub-price");

    // Fungsi untuk mengurangi kuantitas
    buttonMinus.addEventListener("click", function () {
      product_id = this.getAttribute("data-product-id");
      let currentQuantity = parseInt(quantityField.value);
      if (currentQuantity > 1) {
        quantityField.value = currentQuantity - 1;
        var quantity = quantityField.value;
        let priceNow = parseInt(price.value);
        subPrice.innerHTML = "Rp. " + quantityField.value * priceNow;
        update_cart(product_id, quantity, purchase_member);
      }
    });

    // Fungsi untuk menambah kuantitas
    buttonPlus.addEventListener("click", function () {
      product_id = this.getAttribute("data-product-id");
      let currentQuantity = parseFloat(quantityField.value);
      quantityField.value = currentQuantity + 1;
      var quantity = quantityField.value;
      let priceNow = parseFloat(price.value);
      subPrice.innerHTML = "Rp. " + quantityField.value * priceNow;
      update_cart(product_id, quantity, purchase_member);
    });
    //dalam sini
  });
}

function update_cart(product_id, quantity, purchase_member) {
  var data = {
    product_id: product_id,
    purchase_member: purchase_member,
    qty: quantity,
  };

  var xhr = new XMLHttpRequest();

  // Konfigurasi permintaan
  xhr.open("POST", "update_cart", true);
  xhr.setRequestHeader("Content-Type", "application/json");

  // Definisikan fungsi panggilan kembali
  xhr.onreadystatechange = function () {
    if (xhr.readyState === XMLHttpRequest.DONE) {
      if (xhr.status === 200) {
        // Data berhasil disimpan
        location.reload(true);
        console.log("Data berhasil diedit");
      } else {
        // Terjadi kesalahan
        console.log("Terjadi kesalahan saat menyimpan data");
      }
    }
  };
  // Kirim permintaan
  xhr.send(JSON.stringify(data));
}
if ($(".promo-container").length) {
  if ($(window).width() > 700) {
    $(".accordion-collapse").addClass("show");
  }
}

if ($(".login-container").length) {
  var code;

  //clear the contents of captcha div first
  document.getElementById("captcha").innerHTML = "";
  var charsArray =
    "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
  var lengthOtp = 5;
  var captcha = [];
  for (var i = 0; i < lengthOtp; i++) {
    //below code will not allow Repetition of Characters
    var index = Math.floor(Math.random() * charsArray.length + 1); //get the next character from the array
    if (captcha.indexOf(charsArray[index]) == -1)
      captcha.push(charsArray[index]);
    else i--;
  }
  var canv = document.createElement("canvas");
  canv.id = "captcha";
  canv.width = 100;
  canv.height = 50;
  var ctx = canv.getContext("2d");
  ctx.font = "25px Georgia";
  ctx.strokeText(captcha.join(""), 0, 30);
  //storing captcha so that can validate you can save it somewhere else according to your specific requirements
  code = captcha.join("");
  document.getElementById("captcha").appendChild(canv); // adds the canvas to the body element
  var captcha2 = document.getElementById("captcha2"); // adds the canvas to the body element
  captcha2.value = code;
}
