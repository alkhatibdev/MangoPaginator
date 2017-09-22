<?php

/**
* Class 	: MP (MangoPagination)
* Version 	: 1.1
* Author 	: Alkhatib Hamad
* DOCS 		: (http://alkhatibhamad.com/MangoPaginator.php)
*/
class MP
{
	public static $connection = null;
	public static $table_name;
	public static $target_page;
	public static $action;
	public static $start;
	public static $limit;
	public static $adjacents;
	public static $condition;
	public static $current_page;
	public static $total_pages;
	public static $theme;
	public static $query;

	var $themes = array('light', 'dark', 'blue');

	function __construct($_connection, $_tb_name, $option = array())
	{
		// Required Setting
		self::$connection 	= $_connection; 
		self::$table_name 	= $_tb_name;
		self::$target_page 	= basename($_SERVER['SCRIPT_FILENAME']);

		// Optional Settings
		self::$adjacents 	= (
								isset($option['adjacents']) 
								&& is_numeric($option['adjacents'])
								&& $option['adjacents'] > 0
							) ? $option['adjacents'] : 3;
		self::$limit 		= (
								isset($option['limit']) 
								&& is_numeric($option['limit'])
								&& $option['limit'] > 0
							) ? $option['limit'] : 6;
		self::$action 		= (
								isset($option['action']) 
								&& $option['action'] != ''
								&& $option['action'] != null
							) ? str_replace(" ", "-", trim($option['action'])) : 'page';
		self::$condition 		= (
								isset($option['condition']) 
								&& $option['condition'] != ''
								&& $option['condition'] != null
							) ? $option['condition'] : '1';
		self::$theme 		= (
								isset($option['theme']) 
								&& $option['theme'] != ''
								&& $option['theme'] != null
								&& in_array(strtolower($option['theme']), $this->themes)
							) ? strtolower($option['theme']) : 'light';
		self::$query 		= (
								isset($option['query']) 
								&& $option['query'] != ''
								&& $option['query'] != null
							) ? $option['query'] : null;

		// Check Connection validity and Count The whole Items in the $table_name
		$this->checkConnection();
		$this->chechCount();

		// Required Setting - Auto
		self::$current_page = $this->setCurrentPage();

		// Set the Item which the page must Start with
		$this->setStartPage(self::$current_page, self::$limit);
	}

	public function checkConnection()
	{
		if(!self::$connection instanceof PDO && !self::$connection instanceof mysqli)
			throw new PDOException("PDO or mysqli Connection Required", 1);
	}

	public function chechCount()
	{ 
		//   First get total number of rows in data table. 
		//   If you have a WHERE clause in your query, make sure you mirror it here.
		
		$con = self::$connection;
		$tbl_name = self::$table_name;
		$condition = self::$condition;

		$query = (self::$query == null) ? "SELECT * FROM $tbl_name WHERE $condition" : self::$query;

		if($con instanceof PDO){
			$statement = $con->prepare($query);
			$statement->execute(array());
			$pages = $statement->rowCount();
		}else{
			$result = mysqli_query($con, $query);
			$pages = mysqli_num_rows($result);
		}

		self::$total_pages = $pages;
	}

	public function setCurrentPage()
	{
		$lastpage = ceil(self::$total_pages/self::$limit);
		$action = self::$action;
		return (
			isset($_GET[$action]) 
			&& is_numeric($_GET[$action]) 
			&& $_GET[$action] <= $lastpage
			&& $_GET[$action] > 0
		) ? $_GET[$action] : 1;
	}

	public function setStartPage($page, $limit)
	{

		if($page) 
			self::$start = ($page - 1) * $limit;  // 2-1 * 10 = 10 //first item to display on this page
		else
			self::$start = 0;		// if no page var is given, set start to 0
	}

	public function getData()
	{
		// Prepare global vars
		$con 		= self::$connection;
		$tbl_name 	= self::$table_name;
		$start 		= self::$start;
		$limit 		= self::$limit;
		$condition = self::$condition;

		$query = (self::$query == null) ? "SELECT * FROM $tbl_name WHERE $condition LIMIT $start, $limit" : self::$query. " LIMIT $start, $limit";

		$data = array();

		/* Get table data */
		if($con instanceof PDO){
			$statement = $con->prepare($query);
			$statement->execute(array());
			$data = $statement->fetchAll();
		}else{
			$result = mysqli_query($con, $query);
			while ($row = mysqli_fetch_array($result)) {
				array_push($data, $row);
			}
		}
		return $data;
	}

	public function pagination()
	{
		// Prepare global vars
		$page 			= self::$current_page;
		$limit 			= self::$limit;
		$total_pages 	= self::$total_pages;
		$adjacents 		= self::$adjacents;
		$targetpage 	= self::$target_page;
		$action 		= self::$action;
		$theme 			= self::$theme;

		/* Setup page vars for display. */
		$prev = $page - 1;		//previous page is page - 1
		$next = $page + 1;		//next page is page + 1
		$lastpage = ceil($total_pages/$limit); //25/3		//lastpage is = total pages / items per page, rounded up.
		$lpm1 = $lastpage - 1;						//last page minus 1
		
		/* 
			Now we apply our rules and draw the pagination object. 
			We're actually saving the code to a variable in case we want to draw it more than once.
		*/
		$pagination = "";
		if($lastpage >= 1)
		{	
			$pagination .= "$this->style \n <div class=\"$theme pagination\">";
			//previous button
			if ($page > 1) 
				$pagination.= "<a href=\"$targetpage?$action=$prev\" id=\"prev\">«</a>";
			else
				$pagination.= "<span class=\"disabled\" id=\"prev\">«</span>";	
			
			//pages	
			if ($lastpage < 7 + ($adjacents * 2))	//not enough pages to bother breaking it up
			{	
				for ($counter = 1; $counter <= $lastpage; $counter++)
				{
					if ($counter == $page)
						$pagination.= "<span class=\"current\">$counter</span>";
					else
						$pagination.= "<a href=\"$targetpage?$action=$counter\">$counter</a>";					
				}
			}
			elseif($lastpage > 5 + ($adjacents * 2))	//enough pages to hide some
			{
				//close to beginning; only hide later pages
				if($page < 1 + ($adjacents * 2))		
				{
					for ($counter = 1; $counter < 4 + ($adjacents * 2); $counter++)
					{
						if ($counter == $page)
							$pagination.= "<span class=\"current\">$counter</span>";
						else
							$pagination.= "<a href=\"$targetpage?$action=$counter\">$counter</a>";					
					}		
				}
				//in middle; hide some front and some back
				elseif($lastpage - ($adjacents * 2) > $page && $page > ($adjacents * 2))
				{
					for ($counter = $page - $adjacents; $counter <= $page + $adjacents; $counter++)
					{
						if ($counter == $page)
							$pagination.= "<span class=\"current\">$counter</span>";
						else
							$pagination.= "<a href=\"$targetpage?$action=$counter\">$counter</a>";					
					}	
				}
				//close to end; only hide early pages
				else
				{
					for ($counter = $lastpage - (2 + ($adjacents * 2)); $counter <= $lastpage; $counter++)
					{
						if ($counter == $page)
							$pagination.= "<span class=\"current\">$counter</span>";
						else
							$pagination.= "<a href=\"$targetpage?$action=$counter\">$counter</a>";					
					}
				}
			}
			
			//next button
			if ($page < $counter - 1) 
				$pagination.= "<a href=\"$targetpage?$action=$next\" id=\"next\">»</a>";
			else
				$pagination.= "<span class=\"disabled\" id=\"next\">»</span>";
			
			$pagination.= "</div>\n";		
		}

		return $pagination;
	}

	/* The Three Themes Style */
	var $style = '<style type="text/css"> .light.pagination {margin-top: 20px; margin-bottom: 20px; text-align: center;} .light.pagination span, .light.pagination a {border-top: 1px solid #ccc; border-bottom: 1px solid #ccc0; border-left: 1px solid #ccc;padding: 5px 10px;	text-decoration: none;	color: #000;box-shadow: 0 0 3px #ccc;border-right: 1px solid transparent;} .light.pagination span:hover, .light.pagination a:hover {background: #00000011;} .light.pagination .current {background: #ccc;} .light.pagination .current:hover {background: #ccc; cursor: default} .light.pagination .skipe:hover  { background: #FFF; cursor: default;} .light.pagination .disabled:hover { cursor: default; background: #FFF;} .light.pagination #prev {  border-radius: 4px 0 0 4px; } .light.pagination #next { border-radius: 0 4px 4px 0;} .dark.pagination {margin-top: 20px; margin-bottom: 20px; text-align: center;} .dark.pagination span, .dark.pagination a {	border-top: 1px solid #ffffff66; border-bottom: 1px solid #ffffff22; border-left: 1px solid #000;	padding: 5px 10px;	text-decoration: none;	color: #FFF;box-shadow: 0 0 3px #000;border-right: 1px solid transparent; background: #555;} .dark.pagination span:hover, .dark.pagination a:hover {background: #333;} .dark.pagination .current {	background: #000;} .dark.pagination .current:hover {background: #000; cursor: default} .dark.pagination .skipe:hover { background: #333; cursor: default;} .dark.pagination .disabled:hover { cursor: default; background: #555;} .dark.pagination #prev {  border-radius: 4px 0 0 4px; } .dark.pagination #next { border-radius: 0 4px 4px 0;} .blue.pagination {	margin-top: 20px; margin-bottom: 20px; text-align: center;} .blue.pagination span, .blue.pagination a { background: #2d88ff; border-top: 1px solid #2d88ff; border-bottom: 1px solid #2d88ff; border-left: 1px solid #2d88ff;padding: 5px 10px;	text-decoration: none;	color: #FFF;box-shadow: 0 0 3px #ccc; border-right: 1px solid transparent;} .blue.pagination span:hover, .blue.pagination a:hover {	background: #2d88ffdd;} .blue.pagination .current {	background: #1b60bc; color: #FFF;} .blue.pagination .current:hover {background: #1b60bc; cursor: default} .blue.pagination .skipe:hover  { background: #FFF; cursor: default;} .blue.pagination .disabled:hover { cursor: default; background: #2d88ff;} .blue.pagination #prev { border-radius: 4px 0 0 4px; } .blue.pagination #next { border-radius: 0 4px 4px 0;} </style>';
}

?>
