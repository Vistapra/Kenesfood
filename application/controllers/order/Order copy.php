<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');
// load base
require_once( APPPATH . 'controllers/base/PublicBase.php' );

// --
class Order extends ApplicationBase
{
    // constructor
    public function __construct()
	{
        // parent constructor
        parent::__construct();
        // load model
		$this->load->model('M_categories');
		$this->load->model('M_Order_Detail');
		$this->load->model('order/M_Order', 'M_Order');
		$this->load->model('M_products');
		$this->load->model("order/cashier/M_Product", "MOC_Product");
		$this->load->model("order/cashier/M_Order_Detail", "MOC_Order_Detail");
		$this->load->model("M_Package");
		$this->load->model("M_Package_Category");
		$this->load->model("settings/M_outlets", "MS_Outlet");
		$this->load->library("libgeneralmap");
    }

	// Checking session for customer
	public function session()
	{
		$this->output->set_content_type('application/json');

		$params = [
			"outlet_id" => $this->input->get('outletId'),
			"table_id" => $this->input->get('tableId'),
			"brand" => $this->input->get('brand')
		];

		$res = [
			"success" => false,
		];

		$params["deleted_at"] = NULL;

		$data = $this->M_Order->getOne($params, [
			"id",
			"name",
			"status",
			"expire_at"
		]);

		if(empty($data)) {
			$res = array_merge($res, [
				"message" => "There is no session yet!"
			]);

			return $this->output
				->set_status_header(404)
				->set_output(json_encode($res));
		}

		$datetime_current = new DateTime();
		$datetime_exp = new DateTime($data["expire_at"]);

		// return $this->output->set_output(json_encode([
		// 	"now" => $datetime_current,
		// 	"exp" => $datetime_exp,
		// 	"hasil" => $datetime_exp <= $datetime_current
		// ]));

		if($datetime_exp <= $datetime_current)
		{
			// Delete order
			$this->M_Order->deleteById($data["id"]);

			$res = array_merge($res, [
				"message" => "There is no session yet!"
			]);
			
			return $this->output
				->set_status_header(404)
				->set_output(json_encode($res));
		}

		$res = array_merge($res, [
			"data" => $data
		]);

		$res["success"] = TRUE;

		return $this->output->set_output(json_encode($res));
	}

	// Start session to order
	public function createSession()
	{
		$this->output->set_content_type('application/json');
		$res["success"] = false;

		$timezone = new DateTimeZone("Asia/Jakarta");
		$datetime = new DateTime("now", $timezone);

		$payload = $this->input->raw_input_stream;
		$payload = $this->security->xss_clean($payload);
		$payload = json_decode($payload);

		$params = [
			"outlet_id" => $payload->outletId,
			"table_id" => $payload->tableId,
			"brand" => $payload->brand,
		];

		// $outletCoordinate = $this->MS_Outlet->getOne([
		// 	"outlet_id" => $params["outlet_id"]
		// ], ["latitude", "longitude"]);

		$outletCoordinate = $this->MS_Outlet->get_detail_outlet($params["outlet_id"]);

		// Verify user location
		// $inRadius = $this->libgeneralmap->inRadius(
		// 	0.1,
		// 	$outletCoordinate,
		// 	[
		// 		[
		// 			"latitude" => $payload->latitude,
		// 			"longitude" => $payload->longitude
		// 		]
		// 	]
		// );

		$inRadius = $this->libgeneralmap->inRadius(
			0.1,
			[
				"latitude" => $outletCoordinate["latitude"],
				"longitude" => $outletCoordinate["longitude"]
			],
			[
				[
					"latitude" => $payload->latitude,
					"longitude" => $payload->longitude
				]
			]
		);

		if(in_array(false, $inRadius))
		{
			return $this->output
				->set_status_header(403)
				->set_output(json_encode(array_merge($res, [
					"code" => "001",
					"message" => "Invalid location!",
				])
			));
		}

		$data = $this->M_Order->getOne(
			array_merge($params, [
				"deleted_at" => null
			])
		);

		if(!empty($data))
		{
			// Verify user
			if(password_verify($payload->passcode, $data["passcode"]))
			{
				$res["success"] = TRUE;

				return $this->output
					->set_status_header(200)
					->set_output(json_encode(array_merge($res, [
						"code" => "001",
						"message" => "Successful",
					])));
			}

			return $this->output
				->set_status_header(422)
				->set_output(json_encode(array_merge(
					$res,
					[
						"code" => "001",
						"message" => "There is an active session"
				])));
		}

		$data = [
			"outlet_id" => $payload->outletId,
			"table_id" => $payload->tableId,
			"brand" => $payload->brand,
			"name" => $payload->name,
			"passcode" => password_hash($payload->passcode, PASSWORD_BCRYPT),
			"status" => $this->M_Order::STATUS_RESERVED,
			"created_at" => $datetime->format("Y-m-d H:i:s"),
			"updated_at" => $datetime->format("Y-m-d H:i:s"),
			"expire_at" => $datetime
				->add(new DateInterval('PT15M'))
				->format("Y-m-d H:i:s"),
		];

		$data = $this->M_Order->insertOrder($data);

		if(!$data["success"])
		{
			return $this->output->set_output(json_encode($data));
		}

		$res = array_merge($data, [
			"code" => "001",
			"message" => "Session has been created!"
		]);

		return $this->output
			->set_status_header(201)
			->set_output(json_encode($res));
	}

    // dashboard
    public function list()
	{
		$tableId       = $this->input->get('tableId');
		$outletId      = $this->input->get('outletId');
        $category      = $this->input->get('category');
        $brandType     = $this->input->get('brand');

        {$this->tsmarty->assign("template_content", "order/order.html");}
        $categories = $this->M_categories->get_catalogue_categories($brandType);

		if(empty($outletId) || empty($tableId))
		{
			return redirect("/");
		}

		switch ($brandType)
		{
			case "kopitiam":
				if ($category)
					$product_mb = $this
						->M_products
						->get_catalogues(
							'catalogue_outlet_filter_category',
							[$category, $brandType]
						);
				else
					$product_mb = $this
						->M_products
						->get_catalogues(
							'get_list_product_outlet',
							[$brandType]
						);
				break;
			default:
				return redirect("order?outletId={$outletId}&tableId={$tableId}&brand=kopitiam");
		}

        // reformatting style for catalogue
        foreach($product_mb as $key => $value)
		{
            $temp = floor($value['price_catalogue']);
            // check if this is not whole number
            if ($temp != $value['price_catalogue'])
                $product_mb[$key]['price_catalogue'] = str_replace(
					'.',
					',',
					strval($value['price_catalogue'])
				);
            else
                $product_mb[$key]['price_catalogue'] = $temp;
        }

        $this->tsmarty->assign('product_mb', $product_mb);
        $this->tsmarty->assign("catalogueCategories", $categories);
  
        // output
        parent::display();
    }

	public function cart()
	{
		$params = [
			$this->input->get("outletId"),
			$this->input->get("brand"),
			$this->input->get("tableId"),
		];

		$data = $this->M_Order->queryDB('getCart', $params);

		$data = [
			"success" => true,
			"message" => "Data has been retrieved",
			"data" => $data
		];

		return $this->output->set_output(json_encode($data));
	}

	public function countCart()
	{
		$params = [
			$this->input->get("outletId"),
			$this->input->get("brand"),
			$this->input->get("tableId"),
		];

		$data = $this->M_Order->queryDB("getCountCart", $params);

		$res = [
			"success" => true,
			"message" => "Data has been retrieved",
			"data" => 0,
		];

		$count = $data[0]->count;

		if(!$count)
			return $this->output->set_output(json_encode($res));

		$res["data"] = $count;

		return $this->output->set_output(json_encode($res));
	}

	public function add()
	{
		$res["success"] = false;
		$this->output = $this->output->set_content_type("application/json");

		$payload = $this->input->raw_input_stream;
		$payload = $this->security->xss_clean($payload);
		$payload = json_decode($payload);

		if(empty($payload->action) || !is_numeric($payload->action))
		{
			$res["message"] = "Action request not found!";

			return $this->output
				->set_status_header(403)
				->set_output(json_encode($res));
		}

		$payload->action = intval($payload->action);

		$order = $this->M_Order->getOne([
			"id" => $payload->orderId
		]);

		if(!$order)
		{
			$res["message"] = "Session has not been set!";

			return $this->output
				->set_status_header(401)
				->set_output(json_encode($res));
		}

		if(intval($order["status"]) === 1)
		{
			$res["message"] = "Order has been placed";

			return $this->output
				->set_status_header(422)
				->set_output(json_encode($res));
		}

		// Add note on product
		if($payload->action === 1)
		{
			$this->M_Order->qBOrderDetailUpdate($params[0], $params[1], [
				"notes" => $payload->notes
			]);
			$res["message"] = "Note has been added!";
		}
		// Add product to cart
		if($payload->action === 2)
		{
			if(empty($payload->data) || !is_array($payload->data))
			{
				$res["message"] = "Malformed body request!";
				return $this->output
					->set_status_header(400)
					->set_output(json_encode($res));
			}

			$res = $this->actionAddProduct($payload, $res, $order);

			if($res["success"])
			{

			}

			return $this->output->set_output(json_encode($res));
		}
		else
		{
			$res["message"] = "Action not found!";

			return $this->output->set_output(json_encode($res));
		}

		$res["success"] = true;
		$res["message"] = "Product has been added on the cart!";
		return $this->output->set_output(json_encode($res));
	}

	public function removeCartItem()
	{
		$payload = $this->input->raw_input_stream;
		$payload = $this->security->xss_clean($payload);
		$payload = json_decode($payload);

		$params = [
			$payload->outletId,
			$payload->tableId,
			$payload->brand
		];

		$order = $this->M_Order->queryDB("getOneByIdentity", $params);

		$params = [
			$order[0]->id,
			$payload->productId
		];

		$productInCart = $this->M_Order->queryDB("getProductInCart", $params);

		$newCount = $productInCart[0]->qty - $payload->count;

		$data = [
			"qty" => $newCount
		];

		if($newCount === 0)
		{
			$data["notes"] = "";
		}
		
		$this->M_Order->qBOrderDetailUpdate($order[0]->id, $payload->productId, $data);

		// Add session time
		$expiredAt = new DateTime($order[0]->expire_at);
		$currentAt = new DateTime();
		$timeAdd = 15 - $expiredAt->diff($currentAt)->format("%i");

		$newExpiredAt = $expiredAt->add(new DateInterval("PT{$timeAdd}M"));

		$data = [
			"expire_at" => $newExpiredAt->format("Y-m-d H:i:s"),
			"updated_at" => $currentAt->format("Y-m-d H:i:s")
		];

		$this->M_Order->qBUpdate($order[0]->id, $data);

		$res = [
			"success" => true,
			"message" => "Data has been deleted!"
		];

		return $this->output->set_output(json_encode($res));
	}

	public function doneOrder()
	{
		$payload = $this->input->raw_input_stream;
		$payload = $this->security->xss_clean($payload);
		$payload = json_decode($payload);

		$params = [
			"outlet_id" => $payload->outletId,
			"table_id" => $payload->tableId,
			"brand" => $payload->brand
		];

		$res["success"] = FALSE;

		$order = $this->M_Order->getOne($params);

		if(!$order)
		{
			$res["message"] = "Order not found!";

			return $this->output
				->set_status_header(404)
				->set_output(json_encode($res));
		}

		$orderDetail = $this->MOC_Order_Detail->getAll([
			"order_id" => $order["id"]
		]);

		$updatedProduct = [];

		foreach($orderDetail as $value)
		{
			$product = $this->MOC_Product->detail($value["product_id"]);

			$newStock = $product["stock"] - $value["qty"];

			if($newStock < 0)
			{
				$res["message"] = "Silahkan periksa kembali. Stock untuk {$product["product_name"]} tidak mencukupi.";

				$this->MOC_Order_Detail->update([
					"order_id" => $order["id"],
					"product_id" => $value["product_id"]
				], [
					"qty" => $value["qty"] + $newStock
				]);

				return $this->output
					->set_status_header(422)
					->set_output(json_encode($res));
			}

			array_push($updatedProduct, [
				"product_id" => $value["product_id"],
				"stock" => $newStock
			]);
		}

		$this->MOC_Product->updateAll('product_id', $updatedProduct);

		$data = $this->M_Order->updateOrder('updateDoneOrder', $params);

		return $this->output->set_output(json_encode($params));
	}

	private function maintainSession($order)
	{
		// Add session time
		$expiredAt = new DateTime($order["expire_at"]);
		$currentAt = new DateTime();
		$timeAdd = 15 - $expiredAt->diff($currentAt)->format("%i");
		
		$newExpiredAt = $expiredAt->add(new DateInterval("PT{$timeAdd}M"));

		$data = [
			"expire_at" => $newExpiredAt->format("Y-m-d H:i:s"),
			"updated_at" => $currentAt->format("Y-m-d H:i:s")
		];

		$this->M_Order->qBUpdate($order["id"], $data);
	}

	private function actionAddProduct($payload, $res, $order) : array
	{
		$packages = [];
		$products = [];
		$updateProducts = [];
		$productIds = [];
		$cartItems = [];
		$currentTimestamp = date("Y-m-d H:i:s");

		// get cart items
		$cartItems = $this->M_Order_Detail->getAll([
			"where" => [
				"order_id" => $order["id"]
			]
		]);

		// Build cart table
		$cartTable = array_reduce($cartItems, function ($carry, $item) {
			$carry[$item['product_id']] = $item;
			return $carry;
		}, []);

		// split package and product. Please refer to api docs.
		foreach($payload->data as $item)
		{
			// both package and product
			if(empty($item->productId))
			{
				$this->output = $this->output->set_status_header(400);

				return array_merge($res, [
					"message" => "Malformed body request",
					"code" => "01"
				]);
			}

			// Must be package
			if(isset($item->products))
			{
				foreach($item->products as $packageItem)
				{
					if(in_array($packageItem->productId, $productIds))
					{
						continue;
					}

					array_push($productIds, $packageItem->productId);
				}

				array_push($packages, $item);

				continue;
			}

			// Must be product
			if(array_key_exists($item->productId, $cartTable))
			{
				// Do not processed if both db and request have same amount
				if(intval($item->quantity === intval($cartTable[$item->productId]["quantity"])))
				{
					continue;
				}

				array_push($updateProducts, [
					"id" => $cartTable[$item->productId]["id"],
					"product_id" => $item->productId,
					"quantity" => intval($item->quantity),
					"updated_at" => $currentTimestamp
				]);
			}
			// insert new record if there is no data on order_details
			else
			{
				array_push($products, [
					"order_id" => $order["id"],
					"product_id" => $item->productId,
					"quantity" => intval($item->quantity),
					"created_at" => $currentTimestamp,
					"updated_at" => $currentTimestamp
				]);
			}

			array_push($productIds, $item->productId);
		}

		if(empty($productIds))
		{
			$res["message"] = "There's no insert or update data!";
			$this->output = $this->output->set_status_header(404);

			return $res;
		}

		// get product stocks
		$productStock = $this->MOC_Product->getAll([
			"where_in" => [
				"product_id" => $productIds
			]
		],
		[
			"product_id",
			"stock"
		]);

		// Prepare stock table
		$stockTable = array_reduce($productStock, function ($carry, $item) {
			$carry[$item['product_id']] = (int) $item['stock'];
			return $carry;
		}, []);

		// proccess rule for update product
		foreach($updateProducts as $key => $item)
		{
			if(!array_key_exists($item["product_id"], $stockTable))
			{
				continue;
			}
			// Delete item
			if($item["quantity"] === 0)
			{
				
			}
			// Stock out
			if($stockTable[$item["product_id"]] === 0 || $item["quantity"] === 0)
			{
				$updateProducts[$key]["quaantity"] = 0;
				continue;
			}

			// set to available stock if insufficient
			if($stockTable[$item["product_id"]] < $item["quantity"])
			{
				$updateProducts[$key]["quantity"] = $stockTable[$item["product_id"]];
			}
		}

		// process rule for insert product
		foreach($products as $key => $item)
		{
			if(!array_key_exists($item["product_id"], $stockTable))
			{
				unset($products[$key]);
				continue;
			}

			// Delete item
			if($item["quantity"] === 0)
			{
				
			}

			// Stock out
			if($stockTable[$item["product_id"]] === 0 || $item["quantity"] === 0)
			{
				unset($products[$key]);
				continue;
			}

			// set to available stock if insufficient
			if($stockTable[$item["product_id"]] < $item["quantity"])
			{
				$products[$key]["quantity"] = $stockTable[$item["product_id"]];
			}
		}

		// Re-arrange products
		$products = array_values($products);

		// Saving product on cart
		if(!empty($products))
		{
			$insertedProduct = $this->M_Order_Detail->insertAll($products);
	
			if(!$insertedProduct)
			{
				$this->output = $this->output->set_status_header(500);
				$res["message"] = $insertedProduct["message"];
				
				return $res;
			}
		}

		// process update product on cart
		if(!empty($updateProducts))
		{
			$updatedProduct = $this->M_Order_Detail->updateAll($updateProducts);
	
			if(!is_numeric($updatedProduct))
			{
				$this->output = $this->output->set_status_header(500);
				$res["message"] = $updatedProduct;
	
				return $res;
			}
		}

		// Processing package
		if(!empty($packages))
		{
			$itemsCategory = [];
			foreach($packages as $key => $item)
			{
				// Get master package rule
				$package = $this->M_Package->getAll([
					"product_id" => $item->productId,
					"deleted_at !=" => NULL
				]);

				if(empty($package))
				{
					unset($packages[$key]);
					continue;
				}

				foreach($package as $itemPackage)
				{
					$itemsCategory[$item->productId][$itemPackage["package_category_id"]]["quantity"] = 0;
					$itemsCategory[$item->productId][$itemPackage["package_category_id"]]["quota"] = intval($itemPackage["quantity"]) * intval($item->quantity);
				}
			}
		}

		// return array_merge($res, ["packages" => $packages, "items" => $itemsCategory]);

		// If there is package exists
		if(count($packages) !== 0) {
			foreach($packages as $key => $value)
			{
				if(empty($value->products))
				{
					$res["message"] = "Anda belum memilih item paket!";
					$res["data"] = $value;
	
					$this->output = $this->output
						->set_status_header(422);

					return $res;
				}

				foreach($value->products as $key1 => $value1)
				{
					$product = $this->MOC_Product->detail($value1->productId);
					$categories = $this->M_Package_Category->rcvSearch($product["package_category_id"]);
					// match product with quota
					foreach($categories as $key2 => $value2) {
						if(array_key_exists($value2["id"], $itemsCategory[$value->productId]))
						{
							if(intval($value1->quantity) > intval($itemsCategory[$value->productId][$value2["id"]]["quota"]))
							{
								unset($packages[$key]->products[$key1]);
							}
						}
					}
				}
			}

			// Save package
			foreach($packages as $key => $value)
			{
				$orderDetail = $this->M_Order_Detail->getOne(["order_id" => $order["id"], "product_id" => $value->productId]);

				// Insert new order package
				if(is_null($orderDetail))
				{
					$inserted = $this->M_Order_Detail->insertOne([
						"order_id" => $order["id"],
						"product_id" => $value->productId,
						"quantity" => $value->quantity,
						"created_at" => $currentTimestamp,
						"updated_at" => $currentTimestamp
					]);

					$orderDetail = ["id" => $inserted];
				}

				// Save or Update package item
				foreach($value->products as $key1 => $value1)
				{
					$itemDetail = $this->M_Order_Detail->getOne(["order_id" => $order["id"], "product_id" => $value1->productId, "parent_id" => $orderDetail["id"]]);
					if(is_null($itemDetail))
					{
						$inserted = $this->M_Order_Detail->insertOne([
							"order_id" => $order["id"],
							"product_id" => $value1->productId,
							"quantity" => $value1->quantity,
							"parent_id" => $orderDetail["id"],
							"created_at" => $currentTimestamp,
							"updated_at" => $currentTimestamp
						]);
					}
					else
					{
						$updated = $this->M_Order_Detail->update([
							"id" => $itemDetail["id"]
						], [
							"quantity" => $value1->quantity,
							"updated_at" => $currentTimestamp
						]);
					}
				}
			}

			$res["success"] = !$res["success"];
			$res["message"] = "Product has been add to cart!";

			// $this->maintainSession($order);

			return $res;

			$productIds = [];
			foreach($payload->products as $item)
			{
				array_push($productIds, $item->productId);
			}

			foreach($payload->products as $item)
			{
				// $detail = $this->M_Order_Detail->getOne([
				// 	"order_id" => $order["id"],
				// 	"product_id" => $item->productId
				// ]);

				// $product = $this->MOC_Product->detail($item->productId);

				if(is_null($product["package_category_id"]))
				{
					$res["message"] = "Invalid product category rule!";
					return $this->output
						->set_status_header(422)
						->set_output(json_encode($res));
				}
				$categories = $this->M_Package_Category->rcvSearch($product["package_category_id"]);
				$res["data"] = $categories;
				return $res;
				// Matching from farthest child node upway to parent node
				foreach($categories as $category)
				{
					
				}
			}
			
			// $$this->M_Order->getOne([
			// 	"id" => $this->
			// ]);
		}

		$product = $this->MOC_Product->detail($payload->productId);

		// Insufficient Stock
		if(intval($product["stock"]) === 0)
		{
			$res["message"] = "Insufficient stock for {$product["product_name"]}";
			$this->output = $this->output
				->set_status_header(422);

			return $res;
		}

		$res["success"] = !$res["success"];
		$res["message"] = "Product has been add to cart!";

		return $res;
		// if($productInCart)
		// {
		// 	$qty = ++$productInCart[0]->qty;
		// 	if(isset($payload->count))
		// 	{
		// 		if($payload->count > 1)
		// 		{
		// 			$qty = $payload->count;
		// 		}
		// 	}
		// 	array_unshift($params, $qty);
		// 	$this->M_Order->updateOrder("updateOrderDetail", $params);
		// }
		// else
		// {
		// 	$params = [
		// 		"order_id" => $order[0]->id,
		// 		"product_id" => $payload->productId,
		// 		"qty" => 1,
		// 		"created_at" => date("Y-m-d H:i:s")
		// 	];
		// 	$this->M_Order->addToCart($params);
		// }
	}
}