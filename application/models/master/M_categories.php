<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class M_categories extends CI_Model
{

	function __construct()
	{
		// parent constructor
		parent::__construct();
	}

	function get_list_category()
	{
		$sql = "SELECT * 
                FROM data_categories
                WHERE cat_st = '0'
                ORDER BY cat_name ";
		$query = $this->db->query($sql);
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	/**
	 * Get list of categories by brand
	 * 
	 * @param string $brand Brand type
	 * @return array List of categories
	 */
	function get_list_category_by_brand($brand)
	{
		$sql = "SELECT * 
                FROM data_categories
                WHERE cat_st = '0'
                AND cat_brand = ?
                ORDER BY cat_name ";
		$query = $this->db->query($sql, array($brand));
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	// get total data
	public function get_total_data($keyword)
	{
		// conditions
		$conditions = '';
		if ($keyword != NULL) {
			$conditions .= " AND (
                data_categories.cat_code LIKE '%" . $keyword . "%'
                OR data_categories.cat_name LIKE '%" . $keyword . "%'
                OR data_categories.cat_brand LIKE '%" . $keyword . "%'
                OR data_categories.cat_desc LIKE '%" . $keyword . "%'
                OR data_categories.cat_st LIKE '%" . $keyword . "%'
            )";
		}
		$sql = "SELECT COUNT(*)'total'  FROM data_categories 
                WHERE data_categories.cat_parent = 0
                " . $conditions;
		$query = $this->db->query($sql);
		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			$query->free_result();
			return $result['total'];
		} else {
			return 0;
		}
	}

	// get list data
	function get_list_data($params, $keyword)
	{
		// conditions
		$conditions = '';
		if ($keyword != NULL) {
			$conditions .= " AND (
                data_categories.cat_code LIKE '%" . $keyword . "%'
                OR data_categories.cat_name LIKE '%" . $keyword . "%'
                OR data_categories.cat_brand LIKE '%" . $keyword . "%'
                OR data_categories.cat_desc LIKE '%" . $keyword . "%'
                OR data_categories.cat_st LIKE '%" . $keyword . "%'
            )";
		}
		$sql = "SELECT 
                    data_categories.* 
                FROM data_categories 
                WHERE data_categories.cat_parent = 0
                " . $conditions . "
                ORDER BY data_categories.cat_code
                LIMIT ?, ?";
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	// get total data sub_category
	public function get_total_data_sub_category($params, $keyword)
	{
		// conditions
		$conditions = '';
		if ($keyword != NULL) {
			$conditions .= " AND (
                data_categories.cat_code LIKE '%" . $keyword . "%'
                OR data_categories.cat_name LIKE '%" . $keyword . "%'
                OR data_categories.cat_brand LIKE '%" . $keyword . "%'
                OR data_categories.cat_desc LIKE '%" . $keyword . "%'
                OR data_categories.cat_st LIKE '%" . $keyword . "%'
            )";
		}
		$sql = "SELECT COUNT(*)'total'  FROM data_categories 
                WHERE data_categories.cat_parent = ?
                " . $conditions;
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			$query->free_result();
			return $result['total'];
		} else {
			return 0;
		}
	}

	// get list data sub_category
	function get_list_data_sub_category($params, $keyword)
	{
		// conditions
		$conditions = '';
		if ($keyword != NULL) {
			$conditions .= " AND (
                data_categories.cat_code LIKE '%" . $keyword . "%'
                OR data_categories.cat_name LIKE '%" . $keyword . "%'
                OR data_categories.cat_brand LIKE '%" . $keyword . "%'
                OR data_categories.cat_desc LIKE '%" . $keyword . "%'
                OR data_categories.cat_st LIKE '%" . $keyword . "%'
            )";
		}
		$sql = "SELECT 
                    data_categories.* 
                FROM data_categories 
                WHERE data_categories.cat_parent = ?
                " . $conditions . "
                ORDER BY data_categories.cat_code
                LIMIT ?, ?";
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	// get detail category
	public function get_detail_category($params)
	{
		$sql = "SELECT 
                    data_categories.* 
                FROM data_categories 
                WHERE data_categories.cat_id = ?";
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	// get total sub category
	public function get_total_sub_category($params)
	{
		$sql = "SELECT 
                    COUNT(*) AS `total` 
                FROM data_categories 
                WHERE data_categories.cat_parent = ?
                AND data_categories.cat_st = '0'
                AND data_categories.cat_highlight = '1'";
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			$query->free_result();
			return $result['total'];
		} else {
			return 0;
		}
	}

	/**
	 * Find category by API ID (legacy method)
	 *
	 * @param int $api_id API ID
	 * @return array|null Category data or null if not found
	 */
	function find_by_api_id($api_id)
	{
		// Forward to new method with null brand
		return $this->find_by_api_id_and_brand($api_id, null);
	}

	/**
	 * Find category by API ID and brand with improved accuracy
	 *
	 * @param int $api_id API ID
	 * @param string $brand Brand type (can be null)
	 * @return array|null Category data or null if not found
	 */
	function find_by_api_id_and_brand($api_id, $brand = null)
	{
		// Log for debugging
		log_message('debug', "Searching for category with api_id={$api_id} and brand=" . ($brand ? $brand : 'any'));

		// Make sure parameters are valid
		if (empty($api_id)) {
			log_message('debug', "Empty api_id parameter provided");
			return null;
		}

		// Make sure api_id column exists
		if (!$this->db->field_exists('api_id', 'data_categories')) {
			$this->db->query('ALTER TABLE data_categories ADD COLUMN api_id INT NULL');
			log_message('debug', "Added api_id column to data_categories table");
		}

		// Reset any previous query
		$this->db->reset_query();

		// Build the query
		$this->db->select('*')
			->from('data_categories')
			->where('api_id', $api_id)
			->where('cat_st', '0');  // Only active categories

		// Add brand filter if provided
		if (!empty($brand)) {
			$this->db->where('cat_brand', $brand);
		}

		// Order by most recently updated first
		$this->db->order_by('cat_id', 'DESC');

		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			log_message('debug', "Found category: " . json_encode($result));
			return $result;
		}

		log_message('debug', "No category found with api_id={$api_id}" . ($brand ? " and brand={$brand}" : ""));
		return null;
	}

	/**
	 * Find category by name similarity and brand
	 * 
	 * @param string $category_name Category name to search for
	 * @param string $brand Brand filter
	 * @param float $min_similarity Minimum similarity threshold (0-100)
	 * @return array|null Category data or null if no match found
	 */
	function find_by_name_similarity($category_name, $brand, $min_similarity = 70)
	{
		log_message('debug', "Finding category by name similarity: '{$category_name}' for brand '{$brand}'");

		if (empty($category_name) || empty($brand)) {
			log_message('debug', "Missing parameters for name similarity search");
			return null;
		}

		// Get active categories for this brand
		$this->db->where('cat_brand', $brand);
		$this->db->where('cat_st', '0');
		$query = $this->db->get('data_categories');

		if ($query->num_rows() == 0) {
			log_message('debug', "No categories found for brand '{$brand}'");
			return null;
		}

		$categories = $query->result_array();
		$best_match = null;
		$highest_similarity = 0;

		// Normalize the search term
		$normalized_search = $this->_normalize_string($category_name);

		foreach ($categories as $category) {
			// Normalize the category name
			$normalized_name = $this->_normalize_string($category['cat_name']);

			// Calculate string similarity
			$similarity = 0;
			similar_text($normalized_search, $normalized_name, $similarity);

			log_message('debug', "Similarity between '{$category_name}' and '{$category['cat_name']}': {$similarity}%");

			if ($similarity > $min_similarity && $similarity > $highest_similarity) {
				$best_match = $category;
				$highest_similarity = $similarity;
			}
		}

		if ($best_match) {
			log_message('debug', "Found category match by name similarity ({$highest_similarity}%): {$best_match['cat_name']} (ID: {$best_match['cat_id']})");
			return $best_match;
		}

		log_message('debug', "No category found with name similar to '{$category_name}' for brand '{$brand}'");
		return null;
	}

	/**
	 * Find the default category for a brand
	 * 
	 * @param string $brand Brand to find default category for
	 * @return array|null Default category or null if not found
	 */
	function find_default_category($brand)
	{
		log_message('debug', "Finding default category for brand '{$brand}'");

		if (empty($brand)) {
			return null;
		}

		// Try to find a category with "default" or brand name in it
		$this->db->where('cat_brand', $brand);
		$this->db->where('cat_st', '0');
		$this->db->like('cat_name', 'default', 'both');
		$this->db->or_like('cat_name', $brand, 'both');
		$this->db->limit(1);

		$query = $this->db->get('data_categories');

		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			log_message('debug', "Found default category for brand '{$brand}': {$result['cat_name']} (ID: {$result['cat_id']})");
			return $result;
		}

		// If not found, just get the first active category for this brand
		$this->db->where('cat_brand', $brand);
		$this->db->where('cat_st', '0');
		$this->db->order_by('cat_id', 'ASC');
		$this->db->limit(1);

		$query = $this->db->get('data_categories');

		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			log_message('debug', "Using first available category as default for brand '{$brand}': {$result['cat_name']} (ID: {$result['cat_id']})");
			return $result;
		}

		log_message('debug', "No default category found for brand '{$brand}'");
		return null;
	}

	/**
	 * Normalize string for comparison
	 * 
	 * @param string $string String to normalize
	 * @return string Normalized string
	 */
	private function _normalize_string($string)
	{
		if (empty($string)) {
			return '';
		}

		// Convert to lowercase
		$string = strtolower($string);

		// Remove special characters and extra spaces
		$string = preg_replace('/[^\p{L}\p{N}\s]/u', '', $string);
		$string = preg_replace('/\s+/', ' ', $string);
		$string = trim($string);

		return $string;
	}

	// insert categories
	function add_category($params)
	{
		return $this->db->insert('data_categories', $params);
	}

	// update categories
	function update_category($cat_id, $params)
	{
		$this->db->where('cat_id', $cat_id);
		return $this->db->update('data_categories', $params);
	}

	// update categories
	function update_sub_category($cat_parent, $params)
	{
		$this->db->where('cat_parent', $cat_parent);
		return $this->db->update('data_categories', $params);
	}

	function update_cat_sub($cat_parent, $params)
	{
		$sql = "UPDATE data_categories 
        SET data_categories.cat_sub = '1' where data_categories.cat_id= $cat_parent";
		$query = $this->db->query($sql, $params);

		// if ($query->num_rows() > 0) {
		//     $result = $query->result_array();
		//     $query->free_result();
		//     return $result;
		// } else {
		//     return array();
		// }
	}

	// delete categories
	function delete_category($cat_id)
	{
		$this->db->where('cat_id', $cat_id);
		return $this->db->delete('data_categories');
	}

	// delete sub category
	function delete_sub_category($cat_parent)
	{
		$this->db->where('cat_parent', $cat_parent);
		return $this->db->delete('data_categories');
	}

	/** PUBLIC PAGE */

	// get list category highlight
	function get_list_cat_highlight($params)
	{
		$sql = "SELECT * 
                FROM data_categories 
                WHERE cat_highlight = '1'
                AND cat_parent = '0' 
                AND cat_st = '0' 
                AND cat_brand = ?
                AND seasonal_id = '0' 
                ORDER BY cat_no
            ";
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}
	public function search_categories($params = [])
	{
		$limit = isset($params['limit']) ? $params['limit'] : 20;
		$offset = isset($params['offset']) ? $params['offset'] : 0;
		$search = isset($params['search']) ? $params['search'] : '';
		$brand = isset($params['brand']) ? $params['brand'] : '';

		$this->db->select('cat_id, cat_name, cat_code, cat_brand');
		$this->db->from('data_categories');

		// Filter pencarian
		if (!empty($search)) {
			$this->db->group_start();
			$this->db->like('cat_name', $search);
			$this->db->or_like('cat_code', $search);
			$this->db->group_end();
		}

		// Filter brand
		if (!empty($brand)) {
			$this->db->where('cat_brand', $brand);
		}

		// Filter status aktif & bukan sub-kategori
		$this->db->where('cat_st', '0'); // 0 = active
		$this->db->where('cat_sub', '0'); // 0 = bukan sub-kategori

		// Batasi hasil
		$this->db->limit($limit, $offset);
		$this->db->order_by('cat_name', 'ASC');

		$query = $this->db->get();
		return $query->result_array();
	}
}
