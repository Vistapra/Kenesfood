<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class M_dashboard extends CI_Model
{

	function __construct()
	{
		parent::__construct();
	}

	// get total product
	function get_total_product()
	{
		$sql = "SELECT 
                    COUNT(IF(`product_promote` = 'arrival', 1, NULL)) AS arrival,
                    COUNT(IF(`product_promote` = 'prelaunch', 1, NULL)) AS prelaunch,
                    COUNT(IF(`product_st` = '0', 1, NULL)) AS aktif,
                    COUNT(IF(`created` BETWEEN DATE_SUB(CURRENT_DATE, INTERVAL 10 DAY) AND CURRENT_DATE, 1, NULL)) AS new_added,
                    COUNT(IF(`product_brand` = 'bakery', 1, NULL)) AS bakery,
                    COUNT(IF(`product_brand` = 'kopitiam', 1, NULL)) AS kopitiam,
                    COUNT(IF(`product_brand` = 'resto', 1, NULL)) AS resto,
                    COUNT(*) AS total_product
                FROM data_product";
		$query = $this->db->query($sql);
		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	// get visitor log
	function get_visitor_log()
	{
		$sql = "SELECT 
                    COUNT(IF(DATE(`created`) = CURRENT_DATE, 1, NULL)) AS today,
                    COUNT(IF(DATE(`created`) BETWEEN SUBDATE(CURRENT_DATE, WEEKDAY(CURRENT_DATE)) AND DATE(CURRENT_DATE + INTERVAL (6 - WEEKDAY(CURRENT_DATE)) DAY), 1, NULL)) AS weekly,
                    COUNT(IF(DATE_FORMAT(`created`, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m'), 1, NULL)) AS monthly
                FROM visitor_log";
		$query = $this->db->query($sql);
		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	// get recent orders
	function get_recent_orders($limit = 5)
	{
		$sql = "SELECT o.id, o.name, o.status, o.total_amount, o.created_at, 
                       do.outlet_name 
                FROM orders o 
                LEFT JOIN data_outlet do ON o.outlet_id = do.outlet_id
                WHERE o.deleted_at IS NULL
                ORDER BY o.created_at DESC
                LIMIT ?";
		$query = $this->db->query($sql, array($limit));
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	// get active promos
	function get_active_promos($limit = 5)
	{
		$sql = "SELECT promo_id, promo_code, promo_name, promo_brand, promo_type, 
                       promo_value, start_date, end_date, quota, usage_count
                FROM promos 
                WHERE promo_status = 'active' 
                  AND NOW() BETWEEN start_date AND end_date
                  AND (quota IS NULL OR usage_count < quota)
                ORDER BY end_date ASC
                LIMIT ?";
		$query = $this->db->query($sql, array($limit));
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	// get system notifications (combines order notifications and waiter calls)
	function get_notifications($limit = 5)
	{
		$sql = "SELECT 'order' as type, 
                       CONCAT('Pesanan baru dari ', customer_name, ' di outlet ', outlet_id, ' meja ', table_id) as message, 
                       created_at
                FROM order_notifications
                WHERE status = 'new'
                UNION ALL
                SELECT 'waiter' as type,
                       CONCAT('Panggilan pelayan di outlet ', outlet_id, ' meja ', table_id) as message,
                       created_at
                FROM waiter_calls
                WHERE status = 'new'
                ORDER BY created_at DESC
                LIMIT ?";
		$query = $this->db->query($sql, array($limit));
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	// get product summary stats
	function get_product_summary()
	{
		$sql = "SELECT 
                    COUNT(DISTINCT product_id) as total_products,
                    COUNT(DISTINCT cat_id) as total_categories,
                    MIN(product_price) as min_price,
                    MAX(product_price) as max_price,
                    AVG(product_price) as avg_price,
                    COUNT(DISTINCT product_brand) as total_brands
                FROM data_product
                WHERE product_st = '0'";
		$query = $this->db->query($sql);
		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}
}
