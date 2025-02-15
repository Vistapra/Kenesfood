<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2011, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * Pagination Class
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Pagination
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/pagination.html
 */
class CI_Pagination {

        var $base_url			= ''; // The page we are linking to
        var $prefix				= ''; // A custom prefix added to the path.
        var $suffix				= ''; // A custom suffix added to the path.

        var $total_rows			= ''; // Total number of items (database results)
        var $per_page			= 10; // Max number of items you want shown per page
        var $num_links			=  2; // Number of "digit" links to show before/after the currently viewed page
        var $cur_page			=  0; // The current page being viewed
        var $first_link			= '<i class="fas fa-angle-double-left"></i>';
        var $next_link			= '<i class="fas fa-chevron-right"></i>';
        var $prev_link			= '<i class="fas fa-chevron-left"></i>';
        var $last_link			= '<i class="fas fa-angle-double-right"></i>';
        var $uri_segment		= 3;
        var $full_tag_open		= '';
        var $full_tag_close		= '';
        var $first_tag_open		= '<li class="page-item">';
        var $first_tag_close    = '</li>';
        var $last_tag_open		= '<li class="page-item">';
        var $last_tag_close		= '</li>';
        var $first_url          = ''; // Alternative URL for the First Page.
        var $cur_tag_open		= '<li class="page-item active"><a class="page-link" href="#" style="color:#fff">';
        var $cur_tag_close		= '</a></li>';
        var $next_tag_open		= '<li class="page-item">';
        var $next_tag_close		= '</li>';
        var $prev_tag_open		= '<li class="page-item">';
        var $prev_tag_close		= '</li>';
        var $num_tag_open		= '<li class="page-item">';
        var $num_tag_close		= '</li>';
        var $page_query_string  = FALSE;
        var $query_string_segment = 'per_page';
        var $display_pages		= TRUE;
        var $anchor_class		= 'page-link';

	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	initialization parameters
	 */
	public function __construct($params = array())
	{
		if (count($params) > 0)
		{
			$this->initialize($params);
		}

		if ($this->anchor_class != '')
		{
			$this->anchor_class = 'class="'.$this->anchor_class.'" ';
		}

		log_message('debug', "Pagination Class Initialized");
	}

	// --------------------------------------------------------------------

	/**
	 * Initialize Preferences
	 *
	 * @access	public
	 * @param	array	initialization parameters
	 * @return	void
	 */
	function initialize($params = array())
	{
		if (count($params) > 0)
		{
			foreach ($params as $key => $val)
			{
				if (isset($this->$key))
				{
					$this->$key = $val;
				}
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Generate the pagination links
	 *
	 * @access	public
	 * @return	string
	 */
	function create_links()
	{
		// If our item count or per-page total is zero there is no need to continue.
		if ($this->total_rows == 0 OR $this->per_page == 0)
		{
			return '';
		}

		// Calculate the total number of pages
		$num_pages = ceil($this->total_rows / $this->per_page);

		// Is there only one page? Hm... nothing more to do here then.
		if ($num_pages == 1)
		{
			return '';
		}

		// Determine the current page number.
		$CI =& get_instance();

		if ($CI->config->item('enable_query_strings') === TRUE OR $this->page_query_string === TRUE)
		{
			if ($CI->input->get($this->query_string_segment) != 0)
			{
				$this->cur_page = $CI->input->get($this->query_string_segment);

				// Prep the current page - no funny business!
				$this->cur_page = (int) $this->cur_page;
			}
		}
		else
		{
			if ($CI->uri->segment($this->uri_segment) != 0)
			{
				$this->cur_page = $CI->uri->segment($this->uri_segment);

				// Prep the current page - no funny business!
				$this->cur_page = (int) $this->cur_page;
			}
		}

		$this->num_links = (int)$this->num_links;

		if ($this->num_links < 1)
		{
			show_error('Your number of links must be a positive number.');
		}

		if ( ! is_numeric($this->cur_page))
		{
			$this->cur_page = 0;
		}

		// Is the page number beyond the result range?
		// If so we show the last page
		if ($this->cur_page > $this->total_rows)
		{
			$this->cur_page = ($num_pages - 1) * $this->per_page;
		}

		$uri_page_number = $this->cur_page;
		$this->cur_page = floor(($this->cur_page/$this->per_page) + 1);

		// Calculate the start and end numbers. These determine
		// which number to start and end the digit links with
		$start = (($this->cur_page - $this->num_links) > 0) ? $this->cur_page - ($this->num_links - 1) : 1;
		$end   = (($this->cur_page + $this->num_links) < $num_pages) ? $this->cur_page + $this->num_links : $num_pages;

		// Is pagination being used over GET or POST?  If get, add a per_page query
		// string. If post, add a trailing slash to the base URL if needed
		if ($CI->config->item('enable_query_strings') === TRUE OR $this->page_query_string === TRUE)
		{
			$this->base_url = rtrim($this->base_url).'&amp;'.$this->query_string_segment.'=';
		}
		else
		{
			$this->base_url = rtrim($this->base_url, '/') .'/';
		}

		// And here we go...
		$output = '';

		// Render the "First" link
		if  ($this->first_link !== FALSE AND $this->cur_page > ($this->num_links + 1))
		{
			$first_url = ($this->first_url == '') ? $this->base_url : $this->first_url;
			$output .= $this->first_tag_open.'<a '.$this->anchor_class.'href="'.$first_url.'">'.$this->first_link.'</a>'.$this->first_tag_close;
		}

		// Render the "previous" link
		if  ($this->prev_link !== FALSE AND $this->cur_page != 1)
		{
			$i = $uri_page_number - $this->per_page;

			if ($i == 0 && $this->first_url != '')
			{
				$output .= $this->prev_tag_open.'<a '.$this->anchor_class.'href="'.$this->first_url.'">'.$this->prev_link.'</a>'.$this->prev_tag_close;
			}
			else
			{
				$i = ($i == 0) ? '' : $this->prefix.$i.$this->suffix;
				$output .= $this->prev_tag_open.'<a '.$this->anchor_class.'href="'.$this->base_url.$i.'">'.$this->prev_link.'</a>'.$this->prev_tag_close;
			}

		}

		// Render the pages
		if ($this->display_pages !== FALSE)
		{
			// Write the digit links
			for ($loop = $start -1; $loop <= $end; $loop++)
			{
				$i = ($loop * $this->per_page) - $this->per_page;

				if ($i >= 0)
				{
					if ($this->cur_page == $loop)
					{
						$output .= $this->cur_tag_open.$loop.$this->cur_tag_close; // Current page
					}
					else
					{
						$n = ($i == 0) ? '' : $i;

						if ($n == '' && $this->first_url != '')
						{
							$output .= $this->num_tag_open.'<a '.$this->anchor_class.'href="'.$this->first_url.'">'.$loop.'</a>'.$this->num_tag_close;
						}
						else
						{
							$n = ($n == '') ? '' : $this->prefix.$n.$this->suffix;

							$output .= $this->num_tag_open.'<a '.$this->anchor_class.'href="'.$this->base_url.$n.'">'.$loop.'</a>'.$this->num_tag_close;
						}
					}
				}
			}
		}

		// Render the "next" link
		if ($this->next_link !== FALSE AND $this->cur_page < $num_pages)
		{
			$output .= $this->next_tag_open.'<a '.$this->anchor_class.'href="'.$this->base_url.$this->prefix.($this->cur_page * $this->per_page).$this->suffix.'">'.$this->next_link.'</a>'.$this->next_tag_close;
		}

		// Render the "Last" link
		if ($this->last_link !== FALSE AND ($this->cur_page + $this->num_links) < $num_pages)
		{
			$i = (($num_pages * $this->per_page) - $this->per_page);
			$output .= $this->last_tag_open.'<a '.$this->anchor_class.'href="'.$this->base_url.$this->prefix.$i.$this->suffix.'">'.$this->last_link.'</a>'.$this->last_tag_close;
		}

		// Kill double slashes.  Note: Sometimes we can end up with a double slash
		// in the penultimate link so we'll kill all double slashes.
		$output = preg_replace("#([^:])//+#", "\\1/", $output);

		// Add the wrapper HTML if exists
		$output = $this->full_tag_open.$output.$this->full_tag_close;

		return $output;
	}
        
        function create_links_dialog_box($offset = 0) {
        $this->anchor_class = 'class="offset"';
        // If our item count or per-page total is zero there is no need to continue.
        if ($this->total_rows == 0 OR $this->per_page == 0) {
            return '';
        }

        // Calculate the total number of pages
        $num_pages = ceil($this->total_rows / $this->per_page);

        // Is there only one page? Hm... nothing more to do here then.
        if ($num_pages == 1) {
            return '';
        }

        // Determine the current page number.
        $CI = & get_instance();

        $this->cur_page = $this->uri_segment;

        // Prep the current page - no funny business!
        $this->cur_page = (int) $this->cur_page;

        $this->num_links = (int) $this->num_links;

        if ($this->num_links < 1) {
            show_error('Your number of links must be a positive number.');
        }

        if (!is_numeric($this->cur_page)) {
            $this->cur_page = 0;
        }

        // Is the page number beyond the result range?
        // If so we show the last page
        if ($this->cur_page > $this->total_rows) {
            $this->cur_page = ($num_pages - 1) * $this->per_page;
        }

//        $uri_page_number = $this->cur_page;
        $uri_page_number = $offset;
        
//		$this->cur_page = floor(($this->cur_page/$this->per_page) + 1);
        // Calculate the start and end numbers. These determine
        // which number to start and end the digit links with
        $start = (($this->cur_page - $this->num_links) > 0) ? $this->cur_page - ($this->num_links - 1) : 1;
        $end = (($this->cur_page + $this->num_links) < $num_pages) ? $this->cur_page + $this->num_links : $num_pages;

        // Is pagination being used over GET or POST?  If get, add a per_page query
        // string. If post, add a trailing slash to the base URL if needed

        $this->base_url = $this->base_url;


        // And here we go...
        $output = '';

        // Render the "First" link
        if ($this->first_link !== FALSE AND $this->cur_page > ($this->num_links + 1)) {
            $first_url = ($this->first_url == '') ? $this->base_url : $this->first_url;
            $output .= $this->first_tag_open . '<a ' . $this->anchor_class . ' id="' . $first_url . '" href="#">' . $this->first_link . '</a>' . $this->first_tag_close;
        }

        // Render the "previous" link

        if ($this->prev_link !== FALSE AND $this->cur_page != 1) {
            $i = $uri_page_number - $this->per_page;
            if ($i == 0 && $this->first_url != '') {
                $output .= $this->prev_tag_open . '<a ' . $this->anchor_class . ' id="' . $first_url . '" href="#">' . $this->prev_link . '</a>' . $this->prev_tag_close;
            } else {
                $i = ($i == 0) ? '' : $this->prefix . $i . $this->suffix;
                $this->base_url . $i = $this->base_url . $i < 0 ? $first_url : $this->base_url . $i;
                $output .= $this->prev_tag_open . '<a ' . $this->anchor_class . ' id="' . $this->base_url . $i . '" href="#">' . $this->prev_link . '</a>' . $this->prev_tag_close;
            }
        }

        // Render the pages
        if ($this->display_pages !== FALSE) {
            // Write the digit links
            for ($loop = $start - 1; $loop <= $end; $loop++) {
                $i = ($loop * $this->per_page) - $this->per_page;

                if ($i >= 0) {
                    if ($this->cur_page == $loop) {
                        $output .= $this->cur_tag_open . $loop . $this->cur_tag_close; // Current page
                    } else {
                        $n = ($i == 0) ? '' : $i;

                        if ($n == '' && $this->first_url != '') {
                            $output .= $this->num_tag_open . '<a ' . $this->anchor_class . ' id="' . $this->first_url . '" href="#">' . $loop . '</a>' . $this->num_tag_close;
                        } else {
                            $n = ($n == '') ? '' : $this->prefix . $n . $this->suffix;

                            $output .= $this->num_tag_open . '<a ' . $this->anchor_class . ' id="' . $this->base_url . $n . '" href="#">' . $loop . '</a>' . $this->num_tag_close;
                        }
                    }
                }
            }
        }

        // Render the "next" link
        if ($this->next_link !== FALSE AND $this->cur_page < $num_pages) {
            $output .= $this->next_tag_open . '<a ' . $this->anchor_class . ' id="' . $this->base_url . $this->prefix . ($this->cur_page * $this->per_page) . $this->suffix . '" href="#">' . $this->next_link . '</a>' . $this->next_tag_close;
        }

        // Render the "Last" link
        if ($this->last_link !== FALSE AND ($this->cur_page + $this->num_links) < $num_pages) {
            $i = (($num_pages * $this->per_page) - $this->per_page);
            $output .= $this->last_tag_open . '<a ' . $this->anchor_class . ' id="' . $this->base_url . $this->prefix . $i . $this->suffix . '" href="#">' . $this->last_link . '</a>' . $this->last_tag_close;
        }

        // Kill double slashes.  Note: Sometimes we can end up with a double slash
        // in the penultimate link so we'll kill all double slashes.
        $output = preg_replace("#([^:])//+#", "\\1/", $output);

        // Add the wrapper HTML if exists
        $output = $this->full_tag_open . $output . $this->full_tag_close;

        return $output;
    }
    
}
// END Pagination Class

/* End of file Pagination.php */
/* Location: ./system/libraries/Pagination.php */