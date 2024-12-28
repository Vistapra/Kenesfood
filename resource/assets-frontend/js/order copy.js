function enlargeCatalogueImage()
{
	let images = document.getElementsByClassName("e-catalogue-image");
	for (let image of images)
	{
		if (image.parentNode)
		{
			image.classList.remove("img-fluid");
			image.classList.remove("custom-block-ek-image");
			let parentElement = image.parentNode;
			parentElement.parentNode.insertBefore(image, parentElement);
			parentElement.remove();
		}
	}
}

function transformCenterElement()
{
	const marginElement = document.getElementsByClassName("custom-center-element");
	for (let element of marginElement)
	{
		element.style.margin = "0";
	}
}

function showHiddenElement(elementId)
{
	const element = document.getElementById(elementId);
}

let isStartSession = false;
let media = window.matchMedia("(max-width: 400px)");

if (media.matches)
{
	enlargeCatalogueImage();
	transformCenterElement();
}

document.addEventListener('DOMContentLoaded', function() {
	const params         = new URLSearchParams(window.location.search);
	const addToCart      = document.getElementById("add-to-cart");
	const categorySelect = document.getElementById('category-select');

	let dProductModal = new bootstrap.Modal(
		"#detail-product",
		{ keyboard: false }
	);
	let cartModal = new bootstrap.Modal(
		"#cart-modal",
		{ keyboard: false }
	);

	// Fetch current session
	$.ajax({
		type: "GET",
		url: window.location.origin + "/order/session?" + params.toString(),
		dataType: "json",
	})
	.done(function (response, textStatus, jqXHR) {
		if(!response.data)
		{
			$("#identity-page").removeAttr("hidden");
			return;
		}
		// Switch page
		$("#customer-name").text(response.data.name);
		$("#identity-page").attr("hidden", true);
		$("#order-page").removeAttr("hidden");
		isStartSession = true;
		
	})
	.fail(function (jqXHR, textStatus, errorThrown) {
		console.log(textStatus);
	});

	let brand = "kopitiam";

	showHiddenElement("order-page");

	if(params.has("category"))
	{
		const categoryId = params.get('category');
		categorySelect.value = categoryId;
	}

	categorySelect.addEventListener('change', function()
	{
		const selectedValue = this.value;
		if(selectedValue == "all")
		{
			if (typeof URLSearchParams !== 'undefined')
			{
				params.delete('category');
				window.location.assign("/order?"+params.toString());
			}
			else
				console.log("Your browser "+
					navigator.appVersion+
					" does not support URLSearchParams");
		}
		else
		{
			if (params.has("category"))
				params.set("category", selectedValue);
			else
				params.append("category", selectedValue);

			window.location.replace("/order?"+params.toString());
		}
	});

	$(".product-image-btn").on("click", function () {
		let productId = $(this).data("product-id");
		$.ajax({
			type: "GET",
			url: window.location.origin
				+ "/apis/product/detail/"
				+ $(this).data("product-id"),
			dataType: "json"
		}).done(function (response, textStatus, jqXHR) {
			if(parseInt(response.data.detail.stock) == 0)
			{
				$("#add-to-cart").attr("hidden", true);
			}
			$("#detail-product-label").text(response.data.detail.product_name);
			$("#detail-product-description").text(response.data.detail.product_desc);
			$("#detail-product-image").attr("src", window.location.origin + "/resource/assets-frontend/dist/product/" + response.data.detail.product_pict);
			$("#product-id").text(response.data.detail.product_id);
		});
		dProductModal.show();
	});

	document.getElementById("detail-product").addEventListener("hide.bs.modal", function(event) {
		$("#add-to-cart").removeAttr("hidden");
	});

	$("#add-to-cart").on("click", function () {
		$.ajax({
			type: "POST",
			url: window.location.origin + "/order",
			data: JSON.stringify
			({
				"outletId": params.get("outletId"),
				"tableId": params.get("tableId"),
				"brand": params.get("brand"),
				"productId": document.getElementById("product-id").innerText,
				"action": "addProduct"
			}),
			dataType: "json"
		})
		.done(function (response, textStatus, jqXHR) {
			dProductModal.hide();
		})
		.fail(function (jqXHR, textStatus, errorThrown) {
			if(jqXHR.status === 422)
			{
				Swal.fire({
					title: "Stok Habis",
					// text: jqXHR.responseText,
					icon: "error"
				})
				.then((result) => {
					if(result.isConfirmed)
					{
						dProductModal.hide();
					}
				});
			}
		});
	});

	$("#show-cart").on("click", function()
	{
		cartModal.show();
	});

	document.getElementById("cart-modal").addEventListener('shown.bs.modal', function()
	{
		$(".action-minus").on("click", function () {
			let productCount = $("#product-count-"+$(this).data("product-id"));
			if(parseInt(productCount.val()) === 0)
			{
				return false;
			}

			$.ajax({
				type: "DELETE",
				url: window.location.origin + "/order",
				data: JSON.stringify({
					"outletId": params.get("outletId"),
					"brand": params.get("brand"),
					"tableId": params.get("tableId"),
					"productId": $(this).data("product-id"),
					"count": 1
				}),
				dataType: "json"
			})
			.done(function (response, textStatus, jqXHR) {
				productCount.val(parseInt(productCount.val()) - 1)
			});
		});

		$(".action-plus").on("click", function () {
			let productCount = $("#product-count-"+$(this).data("product-id"));
			let productStock = $("#product-stock-"+$(this).data("product-id"));

			if (parseInt(productCount.val()) == parseInt(productStock.text()))
			{
				return false;
			}

			$.ajax({
				method: "POST",
				url: window.location.origin + "/order",
				contentType: "application/json",
				data: JSON.stringify
				({
					"outletId": params.get("outletId"),
					"tableId": params.get("tableId"),
					"brand": params.get("brand"),
					"productId": $(this).data("product-id"),
					"action": "addProduct",
				})
			})
			.done(function (response, textStatus, jqXHR) {
				productCount.val(parseInt(productCount.val()) + 1)
			});
		});

		$(".notes").on('change', function() {
			$.ajax({
				method: "POST",
				url: window.location.origin + "/order",
				contentType: "application/json",
				data: JSON.stringify({
					"outletId": params.get("outletId"),
					"tableId": params.get("tableId"),
					"brand": params.get("brand"),
					"productId": $(this).data('product-id'),
					"action": "addNote",
					"notes": $(this).val()
				})
			});
		});
	});

	document.getElementById("cart-modal").addEventListener('show.bs.modal', function()
	{
		$.ajax({
			type: "GET",
			url: window.location.origin + "/order/cart?" + params.toString(),
			dataType: "json"
		})
		.done(function (response, textStatus, jqXHR) {
			$("#container-cart").empty();
			$.each(response.data, function(index, item)
			{
				if(parseInt(item.product_count) === 0)
				{
					return true;
				}
				imageSrc = window.location.origin+"/resource/assets-frontend/dist/product/"+item.product_pict;
				let productRow = document.createElement("div");
				productRow.classList.add("row", "align-items-center");

				let itemNotes = item.notes ?? "";
				
				let productDetail = `
					<div class="row align-items-center">
						<div class="col-3">
							<img src="${imageSrc}"/>
						</div>
						<div class="col-3">
							<p>${item.product_name}</p>
						</div>
						<div class="col-2">
							<button class="btn btn-sm btn-primary action-minus" data-product-id=${item.product_id}><i class="fa-solid fa-minus"></i></button>
						</div>
						<div class="col-2">
							<input class="form-control action-number" type="number" id="product-count-${item.product_id}" data-product-id="${item.product_id}" value="${item.product_count}" min="0" max="${item.product_stock}" />
						</div>
						<div class="col-2">
							<button class="btn btn-sm btn-primary action-plus" data-product-id=${item.product_id}><i class="fa-solid fa-plus"></i></button>
						</div>
					</div>
					<div class="row">
						<div class="col-12">
							<h6>Stock: <span id="product-stock-${item.product_id}">${item.product_stock}</span></h6>
						</div>
					</div>
					<div class="row align-items-center mt-2">
						<div class="col-12">
							<textarea class="form-control notes" data-product-id="${item.product_id}" rows="2" placeholder="Beri Catatan">${itemNotes}</textarea>
						</div>
					</div>
				`;
				$("#container-cart").append(productDetail);
			});
		});
	});

	$(".action-number").on("keydown", function(event) {
		if((event.keyCode >= 48 && event.keyCode <= 57) ||
			(event.keyCode >= 96 && event.keyCode <= 105))
		{
			return;
		}

		event.preventDefault();
	});

	$(document).on("focusin", ".action-number", function()
	{
		$(this).data('val', $(this).val());
	})
	.on("change", ".action-number", function ()
	{
		let productCount = $("#product-count-"+$(this).data("product-id"));
		let productStock = $("#product-stock-"+$(this).data("product-id"));

		if (parseInt($(this).val()) == parseInt(productStock.text()))
		{
			return false;
		}

		$.ajax({
			method: "POST",
			url: window.location.origin + "/order",
			contentType: "application/json",
			data: JSON.stringify
			({
				"outletId": params.get("outletId"),
				"tableId": params.get("tableId"),
				"brand": params.get("brand"),
				"productId": $(this).data("product-id"),
				"action": "addProduct",
				"count": $(this).val()
			})
		})
		.done(function (response, textStatus, jqXHR) {
			
		})
		.fail(function (jqXHR, textStatus, errorThrown) {
			productCount.val($(this).data("val"));
		});
	});

	document.getElementById("order").addEventListener("click", function ()
	{
		$.ajax({
			type: "POST",
			url: window.location.origin + "/order/done",
			data: JSON.stringify
			({
				"outletId": params.get("outletId"),
				"tableId": params.get("tableId"),
				"brand": params.get("brand"),
			}),
			dataType: "json"
		})
		.done(function (response, textStatus, jqXHR) {
			Swal.fire({
				title: "Sukses Order",
				text: "Silahkan lakukan pembayaran",
				icon: "success"
			});
			cartModal.hide();
		})
		.fail(function (jqXHR, textStatus, errorThrown) {
			if(jqXHR.status === 422)
			{
				Swal.fire({
					title: "Stok Habis",
					// text: jqXHR.responseText,
					icon: "error"
				})
				.then((result) => {
					if(result.isConfirmed)
					{
						cartModal.hide();
					}
				});
			}
		});
	});

	$("#submitName").on("click", function () {
		let name = $("#inputCustomerName").val();
		$.ajax({
			type: "POST",
			url: window.location.origin + "/order/session",
			data: JSON.stringify
			({
				"outletId": params.get("outletId"),
				"tableId": params.get("tableId"),
				"brand": params.get("brand"),
				"name": name,
			}),
			dataType: "json"
		})
		.done(function (response, textStatus, jqXHR) {
			$("#customer-name").text(name);
			// Switch page
			$("#identity-page").attr("hidden", true);
			$("#order-page").removeAttr("hidden");

			isStartSession = true;
		});
	});

	window.setInterval(function() {
		if(!isStartSession)
		{
			return;
		}

		$.ajax({
			type: "GET",
			url: window.location.origin + "/order/countCart?"+params.toString(),
			dataType: "json"
		})
		.done(function(response, textStatus, jqXHR) {
			$("#count-cart").text(response.data);
		})
		.fail(function(jqXHR, textStatus, errorThrown) {
			console.log(textStatus);
		});
	}, 3000);
});