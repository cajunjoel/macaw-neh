<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Book Model
 *
 * Macaw Metadata Collection and Workflow System - Macaw
 *
 * The book model contains useful functions for getting and saving data about
 * a book. Typically this will save to the database and later XML will be
 * created from the database itself.
 *
 * This represents a book in the database. Function-based to update/get
 * information from the database. All data is returned in JSON format. No
 * HTML is allowed.
 *
 * @package admincontroller
 * @author Joel Richard
 * @version 1.0 admin.php created: 2010-07-07 last-modified: 2010-08-19

	Change History
	Date        By   Note
	------------------------
	2010-07-07  JMR  Created

 **/

require_once(APPPATH.'libraries/Image_IPTC.php');

class Book extends Model {

	/** 
	 * @var string [$id] the ID of the item record
	 * @var string [$barcode] The barcode of the item
	 * @var string [$status] The current status of the item
	 * @var string [$pages_found]
	 * @var string [$pages_scanned]
	 * @var string [$scan_time]
	 * @var string [$needs_qa]
	 * @internal string [$cfg] The Macaw configuration object
	 */
	public $id = '';
	public $org_id = '';
	public $user_id = 0;
	public $barcode = '';
	public $status  = '';
	public $pages_found = '';
	public $pages_scanned = '';
	public $scan_time = '';
	public $needs_qa = '';
	public $last_error = '';
	public $org_name = '';
	var $metadata_array = array();

	public $cfg;

	function Book() {
		// Call the Model constructor
		parent::Model();
		$this->load->helper('file');
		$this->cfg = $this->config->item('macaw');
		$this->CI = get_instance();
		$this->CI->load->library('session');
	}

	/**
	 * Load an item from the database
	 *
	 * Given a barcode, loads a book into memory. (What gets loaded? Book info,
	 * metadata, scanning location, stuff from the database). If the book is
	 * not found in our database, we return an error (presumably so we can
	 * call the /scan/_initialize_book/ function to do some pre-scanning
	 * setup.)
	 *
	 * @param string [$barcode] The barcode of the item in question
	 */
	function load($barcode = '') {
		if (isset($barcode)) {

			// Query the database for the barcode
			$this->db->select('item.*, organization.name as org_name');
			$this->db->where('barcode', "$barcode");
			$this->db->join('organization', 'item.org_id = organization.id', 'left');
			$item = $this->db->get('item');

			// Did we get a record?
			if (!$this->exists($barcode)) {
				// No record, present an error
				$this->last_error = "The barcode \"$barcode\" could not be found.";
				throw new Exception($this->last_error);

			} else {
				// Yes, get the record, but we also need to get other things
				$row = $item->row();

				$this->barcode       = $row->barcode;
				$this->status        = $row->status_code;
				$this->id            = $row->id;
				$this->org_id        = $row->org_id;
				$this->user_id        = $row->user_id;
				$this->org_name      = $row->org_name;
				$this->pages_found   = $row->pages_found;
				$this->pages_scanned = $row->pages_scanned;
				$this->scan_time     = $row->scan_time;
	
				if ($row->needs_qa == 't') { 
					$this->needs_qa = true;
				} else {
					$this->needs_qa = false;
				}
				$this->metadata_array      = $this->_populate_metadata();
			}

		} else {
			// TODO: Raise an error of some sort
			$this->last_error = "The barcode was not supplied.";
			throw new Exception($this->last_error);
		}
	}

	/**
	 * Get the barcode from an item_id
	 *
	 * Returns the barcode if found, null if not found.
	 *
	 * @param string [$id] The ID of the item in question
	 */
	function get_barcode($id) {
			$this->db->select('barcode');
			$this->db->where('id', $id);
			$item = $this->db->get('item');
			if (isset($item)) {
				$row = $item->row();
				return $row->barcode;
			}
			return null;
	}

	/**
	 * Determine whether an item exists
	 *
	 * Query they database for the identiifer. If we get a non-zero
	 * number of rows, the item exists. We do not check to see if there
	 * are more than 1 row because the database is supposed to prevent this.
	 *
	 * @param string [$barcode] The barcode of the item in question
	 */
	function exists($barcode) {
		// Query the database for the barcode
		$this->db->where('barcode', "$barcode");
		$item = $this->db->get('item');

		// Did we get a record?
		if ($item->num_rows() > 0) {
			return true;
		}	
		return false;
	}

	/**
	 * Set the status of a book
	 *
	 * Sets the status of the book as the given status. If the status is
	 * invalid, an error is raised.
	 *
	 * This also manages the date fields for when various statuses are
	 * set, making sure we don't overwrite a date that's already there.
	 *
	 * @param string [$status] The new status for the book
	 * @param boolean [$override] Should we ignore the existing status entirely?
	 */
	function set_status($status = '', $override = false) {
		// Get the status
		$this->db->where('barcode', $this->barcode);
		$q = $this->db->get('item')->result();

		if ($this->_is_valid_status('book', $status)) {
			// Make sure we can move from this status to that status.
			// generally, moving backwards is bad.
			if (!$override) {
				$this->_verify_status_change($q[0]->status_code, $status);
			}
			// Only if it hasn't changed do we update it.
			if ($q[0]->status_code != $status) {
				$this->db->where('barcode', $this->barcode);
				$data = array(
					'status_code' => $status,
				);

				if ($status == 'scanning'  && !$q[0]->date_scanning_start) { $data['date_scanning_start'] = date('Y-m-d H:i:s'); }
				if ($status == 'scanned'   && !$q[0]->date_scanning_end)   { $data['date_scanning_end']   = date('Y-m-d H:i:s'); }
				if ($status == 'reviewing' && !$q[0]->date_review_start)   { $data['date_review_start']   = date('Y-m-d H:i:s'); }
				if ($status == 'reviewed'  && !$q[0]->date_review_end)     { $data['date_review_end']     = date('Y-m-d H:i:s'); }
				if ($status == 'completed' && !$q[0]->date_completed)      { $data['date_completed']      = date('Y-m-d H:i:s'); }
				if ($status == 'exporting' && !$q[0]->date_export_start)   { $data['date_export_start']   = date('Y-m-d H:i:s'); }
				if ($status == 'archived'  && !$q[0]->date_archived)       { $data['date_archived']       = date('Y-m-d H:i:s'); }

				$this->db->set($data);
				$this->db->update('item');
				$this->logging->log('book', 'info', 'Status set to '.$status.'.', $this->barcode);
			}
		} else {
			$this->last_error = 'The status "'.$status.'" is invalid. Is this a misspelling? Please contact tech support. (The current status is '.$q[0]->status_code.'.)';
			throw new Exception($this->last_error);
		}
	}

	/**
	 * Track the status of the various export modules
	 *
	 * Provide a place where the export module can save a status. Only when all
	 * export modules are completed do we set the status of the item to completed.
	 *
	 * Module name is not required, only the status. We figure out the name of the
	 * calling module using debug_backtrace()
	 */
	function set_export_status($status = '') {
		// Get the name of the php file that called us. That's the module name.
		$d = debug_backtrace();
		preg_match('/^\/.+\/(.*?)\.php$/', $d[0]['file'], $m);
		$module_name = $m[1];
		// Can we continue?
		if ($module_name != '' && $status != '') {
			// See if we have a record for this module in the database already
			$this->db->where('item_id', $this->id);
			$this->db->where('export_module', $module_name);
			if ($this->db->count_all_results('item_export_status') > 0) {
				// Update the record to the new status
				$data = array('status_code' => $status);
				$this->db->where('item_id', $this->id);
				$this->db->where('export_module', $module_name);
				$this->db->update('item_export_status', $data);
				$this->set_status('exporting');
			} else {
				// Add the record with the new status
				$data = array(
					'item_id'		=> $this->id,
					'status_code'	=> $status,
					'export_module'	=> $module_name,
					'date'		    => date('Y-m-d H:i:s.u')
				);
				$this->db->insert('item_export_status', $data);
				$this->set_status('exporting');
			}
			$this->logging->log('book', 'info', 'Export status set to '.$status.'.', $this->barcode);

			// Now that we are done, check the statuses of all known modules in the table.
			// If all are 'completed', then we mark the status of the item itself as "completed"
			$this->db->where('item_id', $this->id);
			$this->db->where('status_code', 'completed');
			if ($this->db->count_all_results('item_export_status') >= count($this->cfg['export_modules'])) {
				$this->set_status('completed');
			}
			//echo $this->db->last_query()."\n";
		}
	}

	/**
	 * Get the status of one export modules
	 *
	 * Given the name of an export module, return the MOST RECENT status of that module
	 * or null if there is no status at all in the database.
	 *
	 */
	function get_export_status($module_name = '') {
		if ($module_name != '') {
			// See if we have a record for this module in the database already
			$this->db->where('item_id', $this->id);
			$this->db->where('export_module', $module_name);
			$query = $this->db->get('item_export_status');
			if ($row = $query->result()) {
				return $row[0]->status_code;
			}
		}
		return null;
	}

	/**
	 * Make sure that we can change to a status
	 *
	 * The status codes operate in order. So it's not usually a good idea
	 * to move backwards in the process. Therefore we need to check that
	 * we can move from status A to status B. If we cannot change to the
	 * new status, then we throw an error in the hopes that someone upstream
	 * will catch it. Sucky way to handle errors if you ask me.
	 */
	function _verify_status_change($old_status, $status) {
		switch ($old_status) {
			case 'new':
				if ($status != 'new' && $status != 'scanning' && $status != 'error') {
					$this->last_error = 'This item is new. Please import some pages before moving on.';
					throw new Exception($this->last_error);
				}
				break;
			case 'scanning':
				if ($status != 'scanning' && $status != 'scanned' && $status != 'error') {
					$this->last_error = 'This item\'s images are being imported. This needs to be finished before moving on.';
					throw new Exception($this->last_error);
				}
				break;
			case 'scanned':
				if ($status != 'scanning' && $status != 'scanned' && $status != 'reviewing' && $status != 'error') {
					$this->last_error = 'This item\'s images have been imported. You can only start reviewing it now.';
					throw new Exception($this->last_error);
				}
				break;
			case 'reviewing':
				if ($status != 'scanning' && $status != 'reviewing' && $status != 'reviewed' && $status != 'error') {
					$this->last_error = 'This item is being reviewed. You can only finish revewing it.';
					throw new Exception($this->last_error);
				}
				break;
			case 'reviewed':
				if ($status != 'reviewing' && $status != 'exported' && $status != 'exporting' && !$this->CI->user->has_permission('admin')) {
					$this->last_error = 'Only admins can set status to "'.$status.'" when item has status "reviewed".';
					throw new Exception($this->last_error);				

				} else if ($status != 'reviewing'  && $status != 'reviewed' && $status != 'exporting' && $status != 'error') {
					$this->last_error = 'Cannot set status to "'.$status.'" when item has status "reviewed".';
					throw new Exception($this->last_error);
				}
				break;
			case 'exporting':
				if ($status != 'completed' && $status != 'exporting' && $status != 'error' && !$this->CI->user->has_permission('admin')) {
					$this->last_error = 'This item is being exported. There is nothing more than you should need to do.';
					throw new Exception($this->last_error);
				}
				break;
			case 'completed':
				if ($status != 'archived' && $status != 'completed' && $status != 'error') {
					$this->last_error = 'Cannot set status to "'.$status.'" when item has status "completed".';
					throw new Exception($this->last_error);
				}
				break;
			case 'archived':
				if ($status != 'archived' && $status != 'error') {
					$this->last_error = 'Cannot set status to "'.$status.'" when item has status "archived".';
					throw new Exception($this->last_error);
				}
				break;
			case 'error':
				$this->last_error = 'Cannot set status to "'.$status.'" when item has status "error".';
				throw new Exception($this->last_error);
				break;
		}
	}

	/**
	 * Is the status valid
	 *
	 * Let's just make sure that the status is one of the ones we can use for
	 * a book or item
	 *
	 * @param string [$type] What kind of status are we checking (book|item)
	 * @param string [$s] The status code to check
	 * @return boolean Whether the code was valid or not.
	 */
	function _is_valid_status($type = 'book', $s = '') {
		if ($type == 'book') {
			if ($s == 'new'      || $s == 'scanning'  || $s == 'scanned'  ||
			    $s == 'reviewing' ||  $s == 'reviewed' || $s == 'exporting' ||
			    $s == 'completed' || $s == 'archived' || $s == 'error') {
				return true;
			}
		} elseif ($type == 'page') {
			if ($s == 'New' || $s == 'Pending' || $s == 'Processed') {
				return true;
			}
		}
		return false;
	}


	/**
	 * Get the pages in an item
	 *
	 * Gets a list of all pages in the book, along with their metadata. Used
	 * for both monitoring the list of books and for reviewing/reordering/
	 * editing metadata. The boolean brief modifier can be used to exclude
	 * the metadata for each image, leaving on the filenames and statuses.
	 * We can also opt to get only missing pages or pages that are not marked
	 * as missing. The default is to return all pages.
	 *
	 * @param string [$order] Optional. Which fieldname to sort by
	 * @param string [$dir] Default "asc". Which direction to sort (asc|desc)
	 * @param integer [$limit] Optional. How many records to return
	 * @param boolean [$only_missing] Optional. Whether to return only pages marked as missing.
	 * @param boolean [$no_missing] Optional. Whether to suppress all pages marked as missing.
	 * @return array An array of arrays of rows indexed by fieldname
	 */
	function get_pages($order = '', $dir = 'asc', $limit = 0, $only_missing = false, $no_missing = false) {
		$thumb_path = $this->get_path('thumbnail');
		$preview_path = $this->get_path('preview');
		$scans_path = $this->get_path('original');

		// Get the pages
		$this->db->where('item_id', $this->id);
		if ($only_missing) {
			$this->db->where('is_missing = true and item_id = '.$this->id);
		} elseif ($no_missing) {
			$this->db->where('is_missing = false or is_missing is null and item_id = '.$this->id);
		} else {
			$this->db->where('item_id', $this->id);
		}
		if ($order) {$this->db->order_by($order, $dir);}
		if ($limit) {$this->db->limit($limit);}
		$this->db->order_by('sequence_number');
		$query = $this->db->get('page');
		$pages = $query->result();

		// Get the metadata for this item
		$this->db->where('item_id', $this->id);
		$query2 = $this->db->get('metadata');
		$metadata = $query2->result_array();

		// Merge the data together (don't want to use a crosstab or pivot since it's DB-specific)
		foreach ($pages as $p) {
			if (preg_match('/archive\.org\/download/', $p->filebase)) {
				$p->thumbnail = $p->filebase.'_thumb.'.$p->extension;
				$p->preview = $p->filebase.'_medium.'.$p->extension;
			} else {
				// Take the filebase and convert it into the proper filenames for preview and thumbnail files
				$p->thumbnail = $thumb_path.'/'.$p->filebase.'.'.$this->cfg['thumbnail_format'];
				$p->preview = $preview_path.'/'.$p->filebase.'.'.$this->cfg['preview_format'];
				$p->scan_filename = $p->filebase.'.'.$p->extension;
				$p->scan = $scans_path.'/'.$p->scan_filename;
			}
			// Make a more human readable of "250 K" or "1.5 MB"
			$p->size = ($p->bytes < 1048576
			            ? number_format($p->bytes/1024, 0).' K'
			            : number_format($p->bytes/(1024*1024), 1).' M');

//  			foreach ($this->cfg['page_metadata_fields'] as $md) {
//  				$p->{$md} = ''; // Initialize to blank, it's easier this way.
//  			}
			foreach ($metadata as $row) {
				// TODO: This can't be using names of fields!!
				// It needs to be smarter and make arrays when necessary
				if ($row['page_id'] == $p->id) {
					if (isset($p->{$row['fieldname']})) {
						if (is_array($p->{$row['fieldname']})) {
							array_push($p->{$row['fieldname']}, $row['value'].'');
						} else {
							$x = $p->{$row['fieldname']};
							$p->{$row['fieldname']} = array();
							if ($x != '') {
								array_push($p->{$row['fieldname']}, $x);
							}
							array_push($p->{$row['fieldname']}, $row['value'].'');
						}
					} else {
						$p->{$row['fieldname']} = $row['value'].'';
					}

				}
			}
			$p->is_missing = ($p->is_missing ? $p->is_missing == 't' : false);
		}
		return $pages;
	}

	function get_all_pages() {
		$thumb_path = $this->get_path('thumbnail');
		$preview_path = $this->get_path('preview');
		$scans_path = $this->get_path('original');

		// args = /sort/fieldname/filter/fieldname=value/page/4
		
		$orderby = false;
		$page = 1;
		$perpage = 100;
		$offset = 0;
		
		// Get the PAGE_IDs that correspond to the pages we want
		$args = func_get_args();
		if (count($args) > 0) {
			$args = $args[0];
		}
		
		$q_fields = array('page.id');
		$q_join = array('page');
		$q_orderby = '';
		$q_limit = '';
		
		$fc = 1;
		while (count($args)) {
			$type = array_shift($args);
			if ($type == 'sort') {
				if (preg_match('/=/', $args[0])) {
					$f = array_shift($args);
					$orderby = $f;	
					$f = explode('=', $f);
					$q_orderby = 'ORDER BY '. $this->db->escape_str($f[0]).' '.$this->db->escape_str($f[1]);
					$q_fields[] = $this->db->escape_str($f[0]);
				}
			}
			if ($type == 'user') {
				if ($args[0] == -1) {
					array_shift($args);
				} else {
					if (preg_match('/[0-9]/', $args[0])) {
						$q_join[] = '(select * from metadata where user_id = '.$this->db->escape_str($args[0]).') mu on mu.page_id = page.id';
					}
				}
			}
			if ($type == 'filter') {
				if (preg_match('/=/', $args[0])) {
					$f = explode('=', array_shift($args));
					if ($f[1] == 'Painting-Drawing-Diagram') { $f[1] = 'Painting/Drawing/Diagram'; }
					if ($f[1] == 'Chart-Table') { $f[1] = 'Chart/Table'; }
					if ($f[1] == 'Black-White') { $f[1] = 'Black/White'; }
					$q_join[] = '(select * from metadata where fieldname = \''.$this->db->escape_str($f[0]).'\' and value = \''.$this->db->escape_str($f[1]).'\') m'.$fc.' on m'.$fc.'.page_id = page.id';
					$fc++;
				}				
			}
			if ($type == 'page') {
				if (preg_match('/^[0-9]$/', $args[0])) {
					$page = array_shift($args);
				}
			}
			if ($type == 'perpage') {
				if (preg_match('/^[0-9]$/', $args[0])) {
					$perpage = array_shift($args);
				}
			}
		}
		
		// Get the pages
		if (!$orderby) {
			$q_orderby = 'ORDER BY sequence_number asc';
			$q_fields[] = 'sequence_number';
		}
		if ($page) {
			$q_limit = "LIMIT ".$perpage." OFFSET ".($perpage * ($page-1));
		}

		$query = $this->db->query('SELECT DISTINCT '.implode(', ', $q_fields).' FROM '.implode(' INNER JOIN ', $q_join).' '.$q_orderby.' '.$q_limit);
		$pages = $query->result();
//		print $this->db->last_query()."\n\n";

		$page_ids = array();
		foreach ($pages as $p) {
			$page_ids[] = $p->id;
		}

		// Now that we have a list of page_ids, we can get their records		
		if (!count($page_ids)) {
			return array();
		}
		$this->db->where('id in ('.implode(',', $page_ids).')');
		$query = $this->db->get('page');
		$pages = $query->result();
		// Get the metadata for this item so we can merge it into the page infos.
		$this->db->where('page_id in ('.implode(',', $page_ids).')');
		$query2 = $this->db->get('metadata');
		$metadata = $query2->result();
		
		// Merge the data together (don't want to use a crosstab or pivot since it's DB-specific)
		foreach ($pages as $p) {
			if (preg_match('/archive\.org\/download/', $p->filebase)) {
				$p->thumbnail = $p->filebase.'_thumb.'.$p->extension;
				$p->preview = $p->filebase.'_medium.'.$p->extension;
			} else {
				// Take the filebase and convert it into the proper filenames for preview and thumbnail files
				$p->thumbnail = $thumb_path.'/'.$p->filebase.'.'.$this->cfg['thumbnail_format'];
				$p->preview = $preview_path.'/'.$p->filebase.'.'.$this->cfg['preview_format'];
				$p->scan_filename = $p->filebase.'.'.$p->extension;
				$p->scan = $scans_path.'/'.$p->scan_filename;
			}
			// Make a more human readable of "250 K" or "1.5 MB"
			$p->size = ($p->bytes < 1048576
			            ? number_format($p->bytes/1024, 0).' K'
			            : number_format($p->bytes/(1024*1024), 1).' M');

			foreach ($metadata as $row) {
				// TODO: This can't be using names of fields!!
				// It needs to be smarter and make arrays when necessary

				if ($row->page_id == $p->id) {
					$p->{$row->fieldname} = $row->value.'';
				}
			}
			$p->is_missing = ($p->is_missing ? $p->is_missing == 't' : false);
		}

		// Manually sort here
		if ($orderby == 'sequence_number=asc') {
			function cmp1($a, $b) {
				if ($a->sequence_number == $b->sequence_number) {	return 0;	}
				return ($a->sequence_number < $b->sequence_number ? -1 : 1);
			}
			usort($pages, 'cmp1');

		} elseif ($orderby == 'sequence_number=desc') {
			function cmp1($a, $b) {
				if ($a->sequence_number == $b->sequence_number) {	return 0;	}
				return ($a->sequence_number > $b->sequence_number ? -1 : 1);
			}
			usort($pages, 'cmp1');

		} elseif ($orderby == 'size=asc') {
			function cmp2($a, $b) {
				if ($a->width == $b->width) {	return 0;	}
				return ($a->width < $b->width ? -1 : 1);					
			}				
			usort($pages, 'cmp2');

		} elseif ($orderby == 'size=desc') {
			function cmp3($a, $b) {
				if ($a->width == $b->width) {	return 0;	}
				return ($a->width > $b->width ? -1 : 1);					
			}
			usort($pages, 'cmp3');				
		}

		return $pages;
	}

	/**
	 * Clear all metadata for all pages
	 *
	 * When we are setting the metadata for an item's pages, we need to clear out all of
	 # the existing metadata. So we made a convenience function for it. This clears
	 * all metadata for all pages. Let me repeat. This is a DESTRUCTIVE OPERATION
	 * that clears hundreds if not thousands of records of data from the "metadata"
	 * table.
	 *
	 * @todo Investigate a smarter function that updates and deletes selectively
	 */
	function delete_page_metadata($page_id = 0) {
		// We no longer delete all metadata pages. We only delete them one by one. 
		if ($page_id == 0) { 
			return;
		}
		$this->db->query(
			'delete from metadata
			where item_id = '.$this->book->id.'
			and page_id = '.$this->db->escape($page_id).'
			and page_id is not null and fieldname in (\'neh_type_i\',\'neh_type_d\',\'neh_type_m\',\'neh_type_p\',\'neh_type_l\',\'neh_color\',\'no_images\')'
		);
	}

	function delete_page($page_id) {
		$this->db->query(
			'delete from page
			where item_id = '.$this->db->escape($this->book->id).'
			and id = '.$this->db->escape($page_id)
		);
	}


	/**
	 * Add metadata element for one page
	 *
	 * When we are adding the metadata for the pages in an item, we do it
	 * one by one. There is arguably a faster way of doing this, but this is
	 * the most reliable.
	 *
	 * @param integer [$page] The ID of the page of the item.
	 * @param string [$name] The name of the metadata field.
	 * @param string [$value] The value of the metadata field
	 * @param interger [$counter] Default 1. Used when submitting more than one of the same metadata field.
	 */
	function set_page_metadata($page, $name, $value, $counter = 1) {
		if (isset($value) && $value !== '') {
			// If this doesn't exist, we can add it. See if it doesn't already exist
			$this->CI->db->where('page_id', $page);
			$this->CI->db->where('item_id', $this->id);
			$this->CI->db->where('fieldname', strtolower($name));
			$this->CI->db->where('counter', $counter);
			$count = $this->CI->db->count_all_results('metadata');

			if ($count == 0) {
				$data = array(
					'item_id'   => $this->id,
					'page_id'   => $page,
					'fieldname' => strtolower($name),
					'counter'   => $counter,
					((strlen($value) > 1000) ? 'value_large' : 'value') => $value,
					'user_id'   => $this->session->userdata('id')
				);
				$this->db->insert('metadata', $data);			
			}

		}
	}


	/**
	 * Get the path for a file for one page
	 *
	 * Calculates the path to where the book lives on the processing server. The
	 * config file contains three paths that contain images. The BARCODE in the
	 * path is replaced with the identifying barcode of the current book
	 *
	 * @param string [$type] What kind of path are we getting?
	 *
	 */
	function get_path($type = '') {
		$path = '';
		if ($type == 'thumbnail') {
			$path = $this->cfg['thumbnail_url'];

		} elseif ($type == 'preview') {
			$path = $this->cfg['preview_url'];

		} elseif ($type == 'original') {
			$path = $this->cfg['scans_url'];

		} else {
			$this->last_error = 'Unrecognized path type supplied';
			throw new Exception($this->last_error);
			return;
		}
		$path = preg_replace('/BARCODE/', $this->barcode, $path);
		$path = preg_replace('/^\//', '', $path);
		return $this->config->item('base_url').$path;

	}

	/**
	 * Add a page to the item
	 *
	 * Given a filename, add it to the book, making sure that we haven't
	 * already added it. Parses the filename to remove the extension, thereby
	 * giving us the filebase with which to compare. We also pass in the bytes
	 * since we don't want to have to look it up here (and we may not know how
	 * to find the file in question from this deep in the code.)
	 *
	 * @param string [$filename] The filename of the page we are adding.
	 * @param integer [$width] The width in pixels of the image file.
	 * @param integer [$height] The height in pixels of the image file.
	 * @param integer [$bytes] The size in bytes of the file we are adding.
	 * @param string [$ext] The file extension of the file (this shouldn't be passed in, really)
	 */
	function add_page($filename = '', $width = 0, $height = 0, $bytes = 0, $status = 'Processed') {
		// Create the filebase
		$filebase = preg_replace('/\.(.+)$/', '', $filename);

		// Calculate the file extension.
		$extension = pathinfo($filename, PATHINFO_EXTENSION);

		// See if it already exists
		$this->db->where('filebase', $filebase);
		$this->db->where('item_id', $this->id);
		$this->db->from('page');

		// Well, does it?
		if ($this->db->count_all_results() == 0) {
			// Get the largest sequence that's in the database
			$this->db->select('max(sequence_number) as max_seq');
			$this->db->where('item_id', $this->id);
			$q = $this->db->get('page')->result();
			$max = $q[0]->max_seq;
			if (!$max) {$max = 0;}
			// Page doesn't exist, add it to the database
			$data = array(
				'item_id' => $this->id,
				'filebase' => $filebase,
				'status' => $status,
				'bytes' => $bytes,
				'sequence_number' => $max + 1,
				'extension' => $extension,
				'width' => $width,
				'height'=> $height,
				'is_missing' => (($this->status != 'new' && $this->status != 'scanning') ? 'true' : 'false')
			);
			$this->db->set($data);
			$this->db->insert('page');

			$this->logging->log('book', 'info', 'Added page '.$filename.'.', $this->barcode);

		} else {
			// Entry exists, what do we do here? Update the bytes.
			$data = array(
				'bytes' => $bytes,
				'extension' => $extension,
				'width' => $width,
				'height' => $height,
				'status' => $status
			);
			$this->db->where('filebase', $filebase);
			$this->db->where('item_id', $this->id);
			$this->db->set($data);
			$this->db->update('page');
			// 2011/05/23 JMR - Removed this because it makes too much noise in the log file
			// $this->logging->log('book', 'info', 'Updated bytes for page '.$filename.'.', $this->barcode);
		}
	}

	/**
	 * Update the status of one page
	 *
	 * Updates the status of a page of a book. Presumably called when we are
	 * importing the image and creating derivatives of the file. Since this is called
	 * from a place where we are working with the files for pages, we don't necessarily
	 * know the ID of the page in question, so this function uses the filename and not
	 * the page ID number.
	 *
	 * @param string [$filename] The filename for the page
	 * @param string [$status] The new status of the page. (New|Pending|Processed)(
	 */
	function update_page_status($filename = '', $status = '') {
		if ($this->_is_valid_status('page', $status)) {
			$filebase = preg_replace('/\.(.+)$/', '', $filename);
			$data = array('status' => $status);
			$this->db->where('filebase', $filebase);
			$this->db->where('item_id', $this->id);
			$this->db->set($data);
			$this->db->update('page');
			$this->logging->log('book', 'info', 'Updated page '.$filebase.' status to '.$status.'.', $this->barcode);
		} else {
			$this->last_error = 'Invalid page status: '.$status.'.';
			throw new Exception($this->last_error);
		}
	}

	/**
	 * Set the sequence number for a page
	 *
	 * When we are saving the metadata for the entire book, we also set the order
	 * of the pages. We do this by setting the sequence_number field on the page.
	 * (as an aside, we never change the internal ID number of a page once it's
	 * added to the system). Yes, this function is called a few hundred times when
	 * saving the book, but we're running in a transaction (at least) Keep in mind
	 * that this potentially (likely) creates duplicate sequence numbers while
	 * it's saving. For this reason, we don't have a unique identifier on the table
	 * that includes sequence_number.
	 *
	 * @param integer [$page_id] The ID number of the page in question
	 * @param integer [$seq] The new sequence number of the page.
	 */
	function set_page_sequence($page_id, $seq) {
		try {
			$this->db->where('id', $page_id);
			$this->db->set(array('sequence_number' => $seq));
			$this->db->update('page');
		} catch (Exception $e) {
			$this->logging->log('book', 'info', $e->getMessage(), $this->session->userdata('barcode'));
		}
	}

	/**
	 * Set the missing flag for a page
	 *
	 * When we are saving the metadata for the entire book, sometimes we want
	 * to reset the IS_MISSING flag on the pages, and sometimes we don't. So
	 * we offer this function to allow the calling code to control when the
	 * flag gets reset. Technically this can be used to set the missing flag
	 * but in practice we don't use it.
	 *
	 * @param integer [$page_id] The ID number of the page in question
	 * @param integer [$flag] Whether or not the page is marked as missing
	 */
	function set_missing_flag($page_id, $flag = false) {
		try {
			$this->db->where('id', $page_id);
			$this->db->set(array('is_missing' => ($flag ? 't' : null)));
			$this->db->update('page');
		} catch (Exception $e) {
			$this->logging->log('book', 'info', $e->getMessage(), $this->session->userdata('barcode'));
		}
	}


	/**
	 * Get the history of an item
	 *
	 * Gets the detailed history for a book from the log file.
	 *
	 */
	function get_history() {
		return read_file($this->cfg['logs_directory'].'/books/'.$this->barcode.'.log');
	}

	/**
	 * Get all books in the system
	 *
	 * Returns a list of all barcodes that are in our system when the $all parameter is false.
	 * If the all parameter is true, then a join is done between the item and metadata table
	 * and some metadata is returned. We attempt to return the fields:
	 * 
	 *     item_id, barcode, identifier, name, title, author
	 * 
	 * It is not possible to return 
	 * This function is needed because the search() function demands a search term. 
	 *
	 * @param boolean [$all] Whether or not to return all data from item as well as the standard metadata fields (default: false)
	 */
	function get_all_books($all = false, $org_id = 0) {
		if ($all) {
			$select = 'max(item.id) as item_id';
			$select .= ", max(item.barcode) as barcode";
			$select .= ", max(item.status_code) as status_code";
			$select .= ", max(item.pages_found) as pages_found";
			$select .= ", max(item.pages_scanned) as pages_scanned";
			$select .= ", max(item.scan_time) as scan_time";
			$select .= ", max(item.org_id) as org_id";
			$select .= ", max(item.user_id) as user_id";
			$select .= ", max(organization.name) as org_name";
			$select .= ", max(account.username) as user";
			$select .= ", max(item.date_created) as date_created";
			$select .= ", max(item.date_scanning_start) as date_scanning_start";
			$select .= ", max(item.date_scanning_end) as date_scanning_end";
			$select .= ", max(item.date_review_start) as date_review_start";
			$select .= ", max(item.date_review_end) as date_review_end";
			$select .= ", max(item.date_export_start) as date_export_start";
			$select .= ", max(item.date_completed) as date_completed";
			$select .= ", max(item.date_archived) as date_archived";
			// Max on BOOL doesn't work
			// $select .= ", max(item.missing_pages) as missing_pages";
			// $select .= ", max(item.needs_qa) as needs_qa";

			$count = 1;
			$group = array();
			// Dynamically build the query and the joins for all of the metadata fields in the configration
			// I fully expect this to slow way down when we have thousands of books in the system.
			$this->db->join('account','account.id = item.user_id', 'left outer');
			$this->db->join('organization','organization.id = item.org_id');
			if ($org_id > 0) {
				$this->db->where('item.org_id', $org_id);
			}
			$order_by = 'item.barcode';
			foreach ($this->cfg['metadata_fields'] as $m) {
				if (in_array($m, array('title','name'))) { // See if we can find a field to search on
					$order_by = 'max(m'.$count.'.value)';
				}
				// Max is a bit of a hack, but we don't know what aggregate functions we find in
				// different databases. Fingers crossed this is sufficient.
				$select .= ', max(m'.$count.'.value) as '.$m;
				$this->db->join(
					'(select item_id, coalesce(value, value_large) as value
					   from metadata
					   where fieldname = \''.$m.'\'
					   and page_id is null) m'.$count,
					'item.id = m'.$count.'.item_id',
					'left outer');
				$count++;
			}
						
			$this->db->from('item');
			$this->db->select($select);
			$this->db->group_by('item.id');
			$this->db->order_by($order_by);
			$query = $this->db->get();
// 			print_r($this->db->last_query());
			
			return $query->result();
		} else {
			$this->db->select('barcode');
			$query = $this->db->get('item');
			return $query->result();
		}
	}

	/**
	 * Find books in the system
	 *
	 * Returns a list of all barcodes that are in our system based on a search
	 * term and value that are provided.
	 */
	function search($field = '', $value = '', $order = '', $where = '') {
		if (($field == '' || $value == '') && $where = '') {
			$this->last_error = "Both 'field' and 'value' OR 'where' are required when searching for books.";
			throw new Exception($this->last_error);
		} else {
			if ($where == '') {
				$this->db->where($field, $value);
			} else {
				$this->db->where($where);
			}
			if ($order) {
				$this->db->order_by($order);
			}
			$query = $this->db->get('item');
			return $query->result();
		}
	}

	/**
	 * Add a book to the system
	 *
	 * Creates a new book in the database and loads it. Also calls the subroutine
	 * to get the MARC metadata. The Item metadata is passed into the
	 * function on the four parameters.
	 *
	 * @param string [$info] An array of associative arrays (fieldname and value) of 
	 * whatever metadata to add. e.g, 
	 * 
	 *    array(
	 *      'barcode'    => '390880823743',
	 *      'title'      => 'The Origin of Species',
	 *      'author'     => 'Darwin, Charles',
	 *      'collection' => 'My Fancy Library'
	 *    );
	 * 
	 * This structure only allows for one piece of data for each metadata. However if you want to 
	 * add MULTIPLE metadata elements of the same name (which is entirely possible and allowed), 
	 * the structure must be different with everything enclosed in associative arrays:
	 * 
	 *    array(
	 *       array('fieldname' => 'barcode',    'value' => '390880823743'),
	 *       array('fieldname' => 'title',      'value' => 'The Origin of Species'),
	 *       array('fieldname' => 'author',     'value' => 'Darwin, Charles')
	 *       array('fieldname' => 'collection', 'value' => 'My Fancy Library')
	 *       array('fieldname' => 'collection', 'value' => 'Biodiversity Heritage Library')
     *    );
	 * 
	 * At a minimum, "barcode" must found in the array.
	 *
	 */
	function add($info) {
		if ($info['barcode']) {
			// If we have a barcode, let's make sure it doesn't already exist
			$this->db->where('barcode', $info['barcode']);
			$query = $this->db->get('item');

			if ($query->num_rows > 0) {
				$this->last_error = "The barcode '".$info['barcode']."' already exists.";
				throw new Exception($this->last_error);
			} else {
				if (strlen($info['barcode']) > 128) {
					$this->last_error = "The barcode is too long.";
					throw new Exception($this->last_error);
				}
				// Default this to something in case we don't have it
				if (!array_key_exists('needs_qa', $info)) {
					$info['needs_qa'] = 0;
				}
				// Create the item record in the database
				if (!isset($this->CI->user->username) || !$this->CI->user->username) {
					$this->CI->user->load('admin');
				}
				$data = array(
					'barcode' => $info['barcode'],
					'status_code' => 'new',
					'date_created' => 'now()',
					'org_id' => $this->CI->user->org_id,
					'needs_qa' => (($info['needs_qa'] == 1 || substr(strtolower($info['needs_qa']),0,1) == 'y') ? 't' : 'f')

				);
				$this->db->insert('item', $data);
				$item_id = $this->db->insert_id();

				// This is a simple associative array
				foreach (array_keys($info) as $i) {
					if ($i != 'barcode') {
						// If we got an array of data, we loop through 
						// the items and add them to the metadata.
						if (is_array($info[$i])) {
							$c = 1;
							foreach ($info[$i] as $m) {
								$this->db->insert('metadata', array(
									'item_id'   => $item_id,
									'fieldname' => $i,
									'counter'   => $c++,
									((strlen($m) > 1000) ? 'value_large' : 'value') => ($m.'')
								));									
							}
						} else {
							$this->db->insert('metadata', array(
								'item_id'   => $item_id,
								'fieldname' => $i,
								'counter'   => 1,
								((strlen($info[$i]) > 1000) ? 'value_large' : 'value') => ($info[$i].'')
							));
						}
					}
				}
				
				// Creates the directories to store our files,
				$path = $this->cfg['data_directory'].'/'.$info['barcode'];
				if (!file_exists($path)) { mkdir($path, 0775); }
				if (!file_exists($path.'/scans')) { mkdir($path.'/scans', 0775); }
				if (!file_exists($path.'/thumbs')) { mkdir($path.'/thumbs', 0775); }
				if (!file_exists($path.'/preview')) { mkdir($path.'/preview', 0775); }

				// Create the the marc.xml and item.xml file,
				if ($md = $this->get_metadata('marc_xml')) {
					write_file($path.'/marc.xml', $md);
					chmod($path.'/marc.xml', 0775);
				}
				if ($md = $this->get_metadata('mods_xml')) {
					write_file($path.'/mods.xml', $md);
					chmod($path.'/mods.xml', 0775);
				}

				return $item_id;
			}
		} else {
			$this->last_error = "A barcode was not supplied for the new item.";
			throw new Exception($this->last_error);
		}
	}

	/**
	 * Save the data for an item.
	 *
	 * Saves the data for one item (book). It's assumed that we have already passed the
	 * permission checking and we can save the data. BARCODE is NOT changed through this
     * When saving metadata, the existing metadata is deleted and the new is re-added. 
     * This means that data will be lost if fields are deleted via the set_metadata() 
	 *
	 * @since Version 1.3
	 */
	function update() {
	
		// Update the ITEM table		
		$data = array(
			'pages_found' => $this->pages_found,
			'pages_scanned' => $this->pages_scanned,
			'scan_time' => $this->scan_time,
			'needs_qa' => ($this->needs_qa ? 't' : 'f')
		);

		// Make it so admin doesn't "take" ownership from someone else
		$this->CI->user->load(null, $this->session->userdata('id'));
		if (!$this->CI->user->has_permission('admin') || !isset($this->user_id) || $this->user_id == 0) {
			$data['user_id'] = $this->session->userdata('id');
		}

		// Just in case, let's reset the org_id if it's empty. 
		if (!$this->org_id) {
			$data['org_id'] = $this->CI->user->org_id;
		}
		
		$this->db->where('id', $this->id);
		$this->db->update('item', $data);

		// Save the Metadata
		//   (We delete all of the metadata for the item (where page_id is null)
		//   because we're about to re-add all of the metadata. Wasteful? Probably.)
		$func = function($f) {return "'".$f['fieldname']."'";};
		$this->db->query(
			'delete from metadata
			where item_id = '.$this->book->id.'
			and page_id is null'
		);

		// Re-Add the metadata
		$metadata = $this->get_metadata();
		$array_counts = array();
		foreach ($metadata as $i) {
			if (array_key_exists($i['fieldname'], $array_counts)) {
				$array_counts[$i['fieldname']]++;
			} else {
				$array_counts[$i['fieldname']] = 1;
			}

			$this->db->insert('metadata', array(
				'item_id'   => $this->id,
				'fieldname' => $i['fieldname'],
				'counter'   => $array_counts[$i['fieldname']],
				((strlen($i['value']) > 1000) ? 'value_large' : 'value') => $i['value'].''
			));
		}

		// Update the the marc.xml and item.xml files on disk
		// We're going to assume that the directory exists. If it doesn't
		// we ignore the error. It could be the case that this item has been
		// resurrected and doesn't exist on disk because it's been archived.
		// If the item is current, the directory should have been created when
		// it was first added to the database. And yes, this is a long comment. :)
		$path = $this->cfg['data_directory'].'/'.$this->barcode;
		if ($md = $this->get_metadata('marc_xml')) {
			if (file_exists($path)) {
				write_file($path.'/marc.xml', $md);
				chmod($path.'/marc.xml', 0775);
			}
		}
		if ($md = $this->get_metadata('mods_xml')) {
			if (file_exists($path)) {
				write_file($path.'/mods.xml', $md);
				chmod($path.'/mods.xml', 0775);
			}
		}
	}

	/**
	 * Gets one piece of metadata for the object
	 *
	 * If only KEY is provided, then the value of that element in the
	 * metadata is returned, if it's there. Otherwise an empty string is
	 * returned.
	 *
	 * @param string [$key] The fieldname of the metadata to get or set.
	 * @return string The metadata for that item or an empty string otherwise.
	 */
	function get_metadata($key = '') {
		if ($key != '') {
			$key = strtolower($key);
			$results = array();
			foreach ($this->metadata_array as $i) {
				if ($i['fieldname'] == $key) {
					array_push($results, $i['value']);
				}
			}
			if (count($results) == 0) {
				return null;
			} elseif (count($results) == 1) {
				return $results[0];
			} else {
				return $results;
			}			
		} else {
			return $this->metadata_array;
		}
	}


	/**
	 * Get a list of all metadata fieldnames
	 *
	 * Gathers an array of all of the metadata fieldnames for the object.
	 * If a fieldname is duplicated, it will be returned only once.
	 *
	 * @return string An array of fieldnames (strings).
	 */
	function get_metadata_fieldnames() {
		$results = array();
		foreach ($this->metadata_array as $i) {
			array_push($results, $i['fieldname']);
		}
		return $results;
	}

	/**
	 * Set one metadata for the item
	 *
	 * This sets the value for one metadata field for the item. If the KEY is
	 * not present in the metadata array, it is added. If it already exists, 
	 * its value will be updated to the new VALUE. If OVERWRITE is false, then a
	 * new metadata entry will be created.
	 * 
     * This does not save the data to the database. Use the
	 * update() method for that.
	 *
	 * @param string [$key] The name of the metadata field
	 * @param string [$value] The value to save, may be empty string or null
	 * @param boolean [$overwrite] Whether we should overwrite existing values or not. Defaults to true.
	 */
	function set_metadata($key = '', $value = '', $overwrite = true) {
	
		if ($key != '') {
			$key = strtolower($key);
			$replaced = false;
			if ($overwrite) {
				foreach ($this->metadata_array as &$i) {
					if ($i['fieldname'] == $key) {
						$i['value'] = $value;
						$replaced = true;
						continue;
					}
				}
			}
			if (!$overwrite || !$replaced) {
				array_push(
					$this->metadata_array, 
					// To make our lives easier, we always save the fieldname in lowercase. 
					// Let's hope no one objects. :)
					array('fieldname' => $key, 'value' => $value)
				);
			}
		}
	}
	
	/**
	 * Clear one or all metadata for the item
	 *
	 * Deletes one metadata field from the item, or deletes all metadata from 
	 * the item entirely. This is a destructive operation and the changes are
	 * saved to the database during the update() method. No return value.
	 *
	 * @param string [$key] The name of the metadata field (defaults to empty)
	 * @param boolean [$all] Whether to clear all metadata for the item
	 */
	function unset_metadata($key = '', $all = false) {
		$key = strtolower($key);
		if ($key == '' && $all == true) {
			$this->metadata_array = array();
		} elseif ($key != '') {
			if (array_key_exists($key, $this->metadata_array)) {
				unset($this->metadata_array[$key]);
			}
		}	
	}

	/**
	 * Determine whether any metadata need to be filled in
	 *
	 * Based on the Macaw Configuration parameters "export_modules" and 
	 * "export_required_fields", determine if any are empty or missing and
	 * report back an array of arrays of the form: 
	 *
	 *   array('export_name' => array('fieldname_1','fieldname_2', ... );
	 * 
	 * The calling code needs to figure out what to do with this information.
	 * If all metadata fields are filled in, an empty array is returned.
	 *
	 * If we "strict" checking is specified (which is the default) the field must
	 * be a non-empty value. If strict checking is not desired, then we ignore the 
	 * value of the metadata and only return those fields that are truly missing/
	 * 
	 * @param boolean [$strict] Default true. All metadata must have a non-empty value.
	 * @return array The fields that are missing indexed on export module name
	 */
	function get_missing_metadata($strict = true) {
	 	$return = array();
	 	// Loop through the modules with required field.
		foreach ($this->cfg['export_required_fields'] as $mod => $fields) {
			if (in_array($mod, $this->cfg['export_modules'])) {
				$tmp = array();
				$all_fields = $this->get_metadata_fieldnames();
				// Loop through the required metadata fields
				foreach ($fields as $f) {
					if ($f == 'copyright') { continue; }
					if (in_array($f, $all_fields)) {
						if ($strict) {
							// If we're strict, we demand a non-empty value
							$md = $this->get_metadata($f);
							if (is_null($md) || $md == '') {
								array_push($tmp, $f);
							}
						}
					} else {
						// Strict or not, if the field isn't there, it's missing
						array_push($tmp, $f);
					}						
				}
				if (count($tmp) > 0) {
					$return[$mod] = $tmp;
				}
			}
		}
		return $return;
	}

	/**
	 * Get the XMP XML for the item
	 *
	 * A wrapper method to get the XMP data which will be embedded in an image file.
	 *
	 * @return string The XML for the item
	 * @since version 1.2
	 */
	function xmp_xml() {

		$xml = '<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="XMP Core 4.4.0">'.
				  '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'.
				    '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'.
				      '<dc:title>'.$this->get_metadata('title').'</dc:title>'.
				      '<dc:identifier>'.$this->barcode.'</dc:identifier>'.
				      '<dc:rights>'.($this->get_metadata('copyright') ? "This image is protected by copyright." : "This image is in the public domain.").'</dc:rights>'.
				      '<dc:source>'.$this->get_metadata('xmp_source').'</dc:source>'.
				      '<dc:creator>'.
				        '<rdf:Seq>'.
				          ($this->get_metadata('author') ? '<rdf:li>'.$this->get_metadata('author').'</rdf:li>' : '').
				          '<rdf:li>'.$this->get_contributor().'</rdf:li>'.
				        '</rdf:Seq>'.
				      '</dc:creator>'.
				      '<dc:date>'.$this->get_metadata('year').'</dc:date>'.
				    '</rdf:Description>'.
				  '</rdf:RDF>'.
				'</x:xmpmeta>';
		return $xml;
	}

	/**
	 * Group items by their queues
	 *
	 * Gets a list of the counts of how many items are in each "queue". This is used
	 * for the Dashboard summary widget.
	 *
	 * @return Object Database record with the counts of each queue.
	 * @since version 1.0
	 */
	function get_status_counts() {
		$q = $this->db->query(
			"select (select count(*) from item where status_code = 'new') as new,
			   (select count(*) from item where status_code = 'scanning') as scanning,
			   (select count(*) from item where status_code = 'scanned') as scanned,
			   (select count(*) from item where status_code = 'reviewing') as reviewing,
			   (select count(*) from item where status_code = 'reviewed') as reviewed,
			   (select count(*) from item where status_code = 'exporting') as exporting,
			   (select count(*) from item where status_code = 'completed') as completed,
			   (select count(*) from item where status_code = 'archived') as archived,
			   (select count(*) from item where status_code = 'error') as error,
			   (select count(*) from item) as books,
			   (select count(*) from page) as pages,
			   (select to_char(avg(age(date_review_end, date_scanning_start)),'fmdd') || 'd ' ||
			       to_char(avg(age(date_review_end, date_scanning_start)),'fmhh') || 'h ' ||
			       to_char(avg(age(date_review_end, date_scanning_start)),'fmmi') || 'm' from item) as avg;"
		);
		return $q->row();
	}

	function get_status_counts_neh() {
		$data = new stdClass();

		$q = $this->db->query(
			"select 
			   (select count(*) from metadata where fieldname = 'neh_type_p' and value = 'Photograph') as type_photos,
			   (select count(*) from metadata where fieldname = 'neh_type_i' and value = 'Painting/Drawing/Diagram') as type_illustration,
			   (select count(*) from metadata where fieldname = 'neh_type_d' and value = 'Chart/Table') as type_diagram,
			   (select count(*) from metadata where fieldname = 'neh_type_l' and value = 'Bookplate') as type_bookplate,
			   (select count(*) from metadata where fieldname = 'neh_type_m' and value = 'Map') as type_map,
			   (select count(*) from metadata where fieldname = 'no_images'  and value = 'none') as no_images,
			   (select count(*) from item) as books,
			   (select count(*) from page) as pages,
			   (select count(*) from item where status_code in ('new','scanning','scanned')) as new_items,
			   (select count(*) from item where status_code in ('reviewing')) as in_progress,
			   (select count(*) from item where status_code in ('reviewed','exporting')) as completed,
			   (select count(*) from item where status_code in ('exported','completed')) as exported,
			   (select count(*) from page where item_id in (select id from item where status_code in ('new','scanning','scanned'))) as new_items_pages,
			   (select count(*) from page where item_id in (select id from item where status_code in ('reviewing'))) as in_progress_pages,
			   (select count(*) from page where item_id in (select id from item where status_code in ('reviewed','exporting'))) as completed_pages,
			   (select count(*) from page where item_id in (select id from item where status_code in ('exported','completed'))) as exported_pages,
			   (select date_part('hour', t) || 'h ' || date_part('min', t) || 'm ' || round(date_part('second', t)) || 's ' from (select sum(date_review_end - date_review_start) / count(*) as t from item where date_review_start is not null and date_review_end is not null and (date_review_end - date_review_start) < interval '1 day' and (date_review_end - date_review_start) > interval '20 seconds') as a) as average_time
			   ;"
		);
		$r = $q->row();
		
		$data->type_photo = $r->type_photos;
		$data->type_illustration = $r->type_illustration;
		$data->type_diagram = $r->type_diagram;
		$data->type_bookplate = $r->type_bookplate;
		$data->type_map = $r->type_map;
		$data->no_images = $r->no_images;

		$data->new_items = $r->new_items;
		$data->in_progress = $r->in_progress;
		$data->completed = $r->completed;
		$data->exported = $r->exported;
		$data->total_items = $r->books;
		$data->total_pages = $r->pages;
		$data->pct_complete = round($r->completed_pages / $r->pages * 100, 1);

		$data->pages_new_items = $r->new_items_pages;
		$data->pages_in_progress = $r->in_progress_pages;
		$data->pages_complete = $r->completed_pages;
		$data->pages_exported = $r->exported_pages;

		$data->average_time = $r->average_time;
	
		return $data;
	}

	function get_top_users_neh() {
		$data = array();

		$q = $this->db->query(
			"select max(a.full_name) as full_name, a.username, count(i.id) as items, max(pg.page_count) as pages
			from account a 
			inner join item i on i.user_id = a.id 
			inner join (select count(*) as page_count, a.id as user_id from page p inner join item i on p.item_id = i.id inner join account a on a.id = i.user_id group by a.id) pg on pg.user_id = a.id 
			where i.status_code in ('reviewed','completed')
			group by a.username 
			order by items desc;"
		);
		foreach ($q->result() as $r) {
			$data[] = array(	
				'full_name' => $r->full_name,
				'username' => $r->username,
				'items' =>  $r->items,
				'pages' =>  $r->pages,
			
			);
		}
		return $data;
	}

	function get_last_status() {
		$this->db->where('barcode', $this->barcode);
		$row = $this->db->get('item')->row();
		if ($row->date_archived) {
			return 'archived';
		} elseif ($row->date_completed) {
			return 'completed';
		} elseif ($row->date_review_end) {
			return 'reviewed';
		} elseif ($row->date_review_start) {
			return 'reviewing';
		} elseif ($row->date_scanning_end) {
			return 'scanned';
		} elseif ($row->date_scanning_start) {
			return 'scanning';
		} else {
			return 'new';
		}
	}

	function import_images() {
		if ($this->id > 0) {
			$incoming_dir = $this->cfg['incoming_directory'];
			$scans_dir = $this->cfg['data_directory'].'/'.$this->barcode.'/scans/';
		    $book_dir = $this->cfg['data_directory'].'/'.$this->barcode.'/';
			$modified = false;
			if ($this->check_paths()) {
				if ($this->status == 'new' || $this->status == 'scanning' || $this->status == 'scanned' || $this->status == 'reviewing' || $this->status == 'reviewed') {
					//If it does, scan the files
					// TODO: This is probably broken since PATH is often blank. We should handle it more gracefully.
					$files = get_filenames($incoming_dir.'/'.$this->barcode);
						$this->logging->log('book', 'info', 'EXEC: '.$exec, $this->barcode);
					// Filter out files we want to ignore
					foreach ($files as $f) {
						if (preg_match("/\.(pdf|PDF)$/i", $f)) {							
							$fname = $incoming_dir.'/'.$this->barcode.'/'.$f;	
							$fnamenew = $book_dir.$f;
							rename($fname, $fnamenew );			
							$outname = $incoming_dir.'/'.$this->barcode.'/'.preg_replace('/\.(.+)$/', '', $f).'_%04d.jpg';
							//$outname = $incoming_dir.'/'.$this->barcode.'/single%03d.jpg';
							$this->logging->log('book', 'info', 'About to split  '.$fnamenew.' to '.$outname.' via convert.', $this->barcode);
							//$exec = "convert -quality 100 -density 300x300 $fnamenew $outname";
							$gs = 'gs';
							if (isset($this->cfg['gs_exe'])) {
								$gs = $this->cfg['gs_exe'];
							}
							$exec = "$gs -sDEVICE=jpeg -dJPEGQ=100 -r450x450 -o $outname $fnamenew";
							$this->logging->log('book', 'info', 'EXEC: '.$exec, $this->barcode);
							exec($exec, $output);
							
							$this->logging->log('book', 'info', 'After splitting '.$fnamenew.', "gs" output is '.count($output), $this->barcode);
							// Output from exec is '.$output
							//	foreach ($output as $val){
							//	$this->logging->log('book', 'info', 'Output from exec is '.$val, $this->barcode);
							//	}
							// read page 1
						}
					}
					// Filter out files we want to ignore
					$good_files = array();
					$files = get_filenames($incoming_dir.'/'.$this->barcode);
					foreach ($files as $f) {
						if (preg_match("/\.(tif|tiff|jpg|jpeg|jp2|gif|png|bmp)$/i", $f)) {
							array_push($good_files, $f);
						}
					}
					$files = $good_files;
					natsort($files);
					// Now we can safely process the images
					$this->logging->log('book', 'info', 'There are now '. count($files) .' images to import', $this->barcode);	
					if (count($files)) {
						// Update the Book with the number of pages we found
						$this->pages_found = count($files);
						$this->pages_scanned = 0;
						$this->scan_time = time();
						$this->update();
	
						// Add the pages to the database (or update) with status "Pending".
						// This will make them appear in the list on the monitor page.
						foreach ($files as $f) {
							$fname = $incoming_dir.'/'.$this->barcode.'/'.$f;
							$info = get_file_info($fname, 'size');
							$this->add_page($f, 0, 0, $info['size'], 'Pending');
						} // foreach ($files as $f)
	
						// Then we process the pages, updating them as we find them again.
						$this->logging->log('access', 'info', 'Importing images for barcode '.$this->barcode.'.');
						$count = 1;
						foreach ($files as $f) {
							$fname = $incoming_dir.'/'.$this->barcode.'/'.$f;
							$info = get_file_info($fname, 'size');
	
							// Make sure the file size isn't changing (we check for 3 seconds)
							if ($this->common->is_file_stable($fname, 1, 120)) {
								if (file_exists($scans_dir.$f)) {
									unlink($scans_dir.$f);
								}
								rename($fname, $scans_dir.$f);
							} else {
								$this->logging->log('error', 'debug', 'While importing newly scanned images, file '.$fname.' did not stabilize after 2 minutes. Aborting.');
								$this->update();
								return;
							}
	
							// Create derivatives for the file (/thumbnail/ and /preview/)
							$dim = $this->_process_image($scans_dir, $this->barcode, $f);
	
							// Add the page to the book
							$this->add_page($f, $dim['width'], $dim['height'], $info['size']);
	
							// Update the Book with the number of pages we've processed
							$this->pages_scanned = $count++;
	
							// Log that we saw the file.
							$this->logging->log('book', 'info', 'Created preview and thumbnail for page '.$f.'.', $this->barcode);
	
							$this->update();
	
						} // foreach ($files as $f)
					} // if (count($files))
				} // if ($this->status == 'scanning')
				if ($this->status == 'new' || $this->status == 'scanning') {
					$this->set_status('scanning');
					$this->set_status('scanned');
				}
			} else {
				echo('Check paths failed for item with barcode "'.$this->barcode.'".'."\n");
				// The paths are not all writable or existing. Skip this book
			} // if ($this->check_paths)
		}
	}

	/**
	 * Create derivatives for an individual scan of a page
	 *
	 * INTERNAL: Given a filename, creates whatever derivatives that are immediately
	 * needed by the Macaw. Also loads in whatever metadata we have for the
	 * file. (Bytes)
	 *
	 * @param string [$path] The path to the file on the server
	 * @param string [$barcode] The barcode of the book in question
	 * @param string [$filename] The filename of the scanned page
	 * @since Version 1.0
	 */
	function _process_image($path, $barcode, $filename) {

		// Error handling
		if (!$path) {
			throw new Exception('Path not supplied to _process_file().');
		}
		if (!$filename) {
			throw new Exception('Filename not supplied to _process_file().');
		}

		// Get the base of the filename
		$filebase = preg_replace('/\.(.+)$/', '', $filename);
		$dest = $this->cfg['data_directory'].'/'.$barcode;

		// Create the preview JPEG
		$preview = new Imagick($path.'/'.$filename);
		$this->common->get_largest_image($preview);

		// get the dimensions, we're going to want them later
		$return = array();
		$info = $preview->getImageGeometry();
		$return['width'] = $info['width'];
		$return['height'] = $info['height'];

		// Create the preview image
		$preview->resizeImage(1500, 2000, Imagick::FILTER_UNDEFINED, 1, true);
		$preview->profileImage('xmp', $this->book->xmp_xml());
		try {
			$preview->writeImage($dest.'/preview/'.$filebase.'.jpg');
		} catch (Exception $e) {
			$this->logging->log('book', 'info', 'Exception: '.$e->getMessage(), $this->barcode);
		}

		// Set IPTC Data
		$img = new Image_IPTC($dest.'/preview/'.$filebase.'.jpg');
		$img->setTag('object_name', $this->book->barcode);
		$img->setTag('byline', $this->book->get_metadata('author'), 0);
		$img->setTag('byline', $this->get_contributor(), 1);
		$img->setTag('source', $this->book->get_metadata('xmp_source'));
		$img->setTag('copyright_string', ($this->book->get_metadata('copyright') ? "This image is protected by copyright." : "This image is in the public domain."));
		$img->setTag('special_instructions', $this->book->get_metadata('ia_special_instructions'));
		$img->setTag('created_date', $this->book->get_metadata('year'));
		$img->setTag('digital_created_date', date('Ymd'));
		$img->save();

		// Create the thumbnail image
		$preview->resizeImage(180, 300, Imagick::FILTER_UNDEFINED, 1, true);
		$preview->profileImage('xmp', $this->book->xmp_xml());
		$preview->writeImage($dest.'/thumbs/'.$filebase.'.jpg');

		// Set IPTC Data
		$img = new Image_IPTC($dest.'/thumbs/'.$filebase.'.jpg');
		$img->setTag('object_name', $this->book->barcode);
		$img->setTag('byline', $this->book->get_metadata('author'), 0);
		$img->setTag('byline', $this->get_contributor(), 1);
		$img->setTag('source', $this->book->get_metadata('xmp_source'));
		$img->setTag('copyright_string', ($this->book->get_metadata('copyright') ? "This image is protected by copyright." : "This image is in the public domain."));
		$img->setTag('special_instructions', $this->book->get_metadata('ia_special_instructions'));
		$img->setTag('created_date', $this->book->get_metadata('year'));
		$img->setTag('digital_created_date', date('Ymd'));
		$img->save();

		$preview->clear();
		$preview->destroy();

		return $return;
	}

	function archive() {
		// TODO: Need to be able to track the different statuses across export processes before we code this.
	}

	function check_paths() {
		$this->last_error = '';
		$paths = array();
		array_push($paths, $this->cfg['data_directory'].'/'.$this->barcode);
		array_push($paths, $this->cfg['data_directory'].'/'.$this->barcode.'/scans/');
		array_push($paths, $this->cfg['data_directory'].'/'.$this->barcode.'/thumbs/');
		array_push($paths, $this->cfg['data_directory'].'/'.$this->barcode.'/preview/');

		foreach ($paths as $p) {
			if (!file_exists($p)) {
				$this->last_error = 'Directory not found: '.$p;
				$this->logging->log('error', 'debug', $this->last_error);
			}
			if (!is_writable($p)) {
				$this->last_error = 'Permission denied to write to: '.$p;
				$this->logging->log('error', 'debug', $this->last_error);
			}
		}

		// Did we have an error?
		if ($this->last_error != '') {
			return false;
		}

		return true;
	}

	// Gets a proper contributor or organization name for the book
	//
	// First checks the contributor metadata on the item.
	// Next checks the organizaton name associated to the item.
	// Finally it returns the organization_name from the macaw.php config file.
	//
	// Since Version 1.6

	function get_contributor() {
		$ret = $this->org_name;
		if ($this->get_metadata('contributor')) {
			return $this->get_metadata('contributor');

		} elseif ($ret != 'Default') {
			return $ret;

		} else {
			return $this->cfg['organization_name'];
		}
	}
	

	// Retrieves the metadata for the item
	//
	// This internal function queries the metadata table for the metadata
	// fields for this item.
	//
	// Since Version 1.6

	function _populate_metadata() {
		// Get all the records from the item's metadata records
		// Return an array of things.
		$c = $this->db->query(
			"select lower(fieldname) as fieldname, coalesce(value, value_large) as val
			from metadata
			where item_id = ".$this->id."
			  and page_id is null"
		);
		$results = array();
		foreach ($c->result() as $row) {
			array_push(
				$results, 
				array('fieldname' => $row->fieldname, 'value' => $row->val)
			);
		}

		return $results;
	}

}
