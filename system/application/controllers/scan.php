<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Scan Controller
 *
 * MACAW Metadata Collection and Workflow System
 *
 * Monitors the scan progress, shows the reorder/metadata edit page, AJAX
 * to/from the server to save metadata. Also contains functionality for
 * initialization, prepping the scanning server.
 *
 **/

class Scan extends Controller {

	var $cfg;

	/**
	 * Function: Constructor
	 */
	function Scan() {
		parent::Controller();
		$this->cfg = $this->config->item('macaw');
	}


	/**
	 * Display the main scanning page
	 *
	 * The main scanning page is really the /main/ page, so we simply redirect
	 * to that page. There is no index for the scanning page.
	 *
	 * @since Version 1.0
	 */
	function index() {
		redirect($this->config->item('base_url').'main');
	}

	/**
	 * Display the scanning review progress page
	 *
	 * Shows the main scanning monitor page. Determine which scanning server
	 * the user is on and take appropriate action. If the server is identified,
	 * we make sure we can connect to it (via the _test_server() function).
	 * If not identified, guess or ask the user to tell us which server he is
	 * on. Present an error if we can't connect. Later, we will add the
	 * ability to set up a new scanning server.
	 *
	 * @since Version 1.0
	 */
	function monitor() {
		$this->common->check_session();
		// Permission Checking
		if (!$this->user->has_permission('scan')) {
			$this->session->set_userdata('errormessage', 'You do not have permission to access that page.');
			redirect($this->config->item('base_url').'main');
			$this->logging->log('error', 'debug', 'Permission Denied to access '.uri_string());
			return;
		}

		$barcode = $this->session->userdata('barcode');
		// Get our book
		$this->book->load($barcode);
		$this->common->check_missing_metadata($this->book);

		// We found something!
		$data['item_title'] = $this->session->userdata('title');
		$data['ip_address'] = $_SERVER['REMOTE_ADDR'];
		$data['hostname'] = $this->common->_get_host($_SERVER['REMOTE_ADDR']);
		$data['incoming_path'] = $this->cfg['incoming_directory'].'/'.$barcode;
		if ($this->cfg['incoming_directory_remote'] == '') {
			$data['remote_path'] = $this->cfg['incoming_directory'].'/'.$barcode;
		} else {
			$data['remote_path'] = $this->cfg['incoming_directory_remote'].'/'.$barcode;
		}
		$status = $this->book->status;
		if ($status == 'new' || $status == 'scanning') {
			$data['book_has_missing_pages'] = false;
		} else {
			$data['book_has_missing_pages'] = true;		
		}

		// The path can be blank, so we need to handle things properly.
		if ($this->cfg['incoming_directory'] && !file_exists($data['incoming_path'])) {
			// If the folder for the new pages is not there, we add it.
			// Assume that we've already checked for writability in this folder. (which we have, in the Common library)
			mkdir($data['incoming_path']);
			$this->logging->log('access', 'info', 'Created incoming directory: '.$data['incoming_path']);
		}
		$this->logging->log('access', 'info', 'Scanning monitor loaded for '.$barcode.'.');

		$this->load->view('scan/monitor_view', $data);
	}


	/**
	* Upload Pages
	* Uses Yahoo Flash Uploader
	*
	*
	*
	*
	*/
	
	function upload(){
		if (!$this->user->has_permission('scan')) {
			$this->common->ajax_headers();
			echo json_encode(array('errormessage' => 'You do not have permission to access that page.'));
			$this->logging->log('error', 'debug', 'Permission Denied to access '.uri_string());
			return;
		}
			$barcode = $this->session->userdata('barcode');
		// Get our book
		$this->book->load($barcode);
		$this->common->check_missing_metadata($this->book);

		$data['upload_max_filesize'] = ini_get('upload_max_filesize');
		$data['ip_address'] = $_SERVER['REMOTE_ADDR'];
		$data['hostname'] = $this->common->_get_host($_SERVER['REMOTE_ADDR']);
		$data['incoming_path'] = $this->cfg['incoming_directory'].'/'.$barcode;
		$data['remote_path'] = $this->cfg['incoming_directory_remote'].'/'.$barcode;
		$status = $this->book->status;
		if ($status == 'new' || $status == 'scanning') {
			$data['book_has_missing_pages'] = false;
		} else {
			$data['book_has_missing_pages'] = true;		
		}		
		$this->load->view('scan/upload_view', $data);
		
	}
	
	/**
		Start of batch upload of pages.
	*/
	
	function do_batch_upload(){
		//$barcode = $this->session->userdata('barcode');
		$barcode = $_POST["bookid"];
		$incomingpath = $this->cfg['incoming_directory'].'/'.$barcode.'/';
		$remotepath= $this->cfg['incoming_directory_remote'].'/'.$barcode;
		$data['remotepath'] = $remotepath;
		$data['incomingpath'] = $incomingpath;
		foreach ($_FILES as $fieldName => $file) {
			move_uploaded_file($file['tmp_name'], $incomingpath.strip_tags(basename($file['name'])));
			$this->logging->log('book', 'info', 'Uploaded '.$incomingpath.strip_tags(basename($file['name'])), $barcode);
		}
		$this->load->view('scan/monitor_view', $data);
	}
	
	
	/**
	 * Start the import of pages
	 *
	 * AJAX: Called from the "Start Import" button on the scanning monitor page
	 * this simply sets the status of the book to "scanning". The cron job will
	 * actually import the pages and it looks to the status of the book to decide
	 * if it can actually process the pages for the book.
	 *
	 * @since Version 1.0
	 */
	function start_import() {
		if (!$this->user->has_permission('scan')) {
			$this->common->ajax_headers();
			echo json_encode(array('errormessage' => 'You do not have permission to access that page.'));
			$this->logging->log('error', 'debug', 'Permission Denied to access '.uri_string());
			return;
		}

		try {
			$this->book->load($this->session->userdata('barcode'));
			// Reset the counters because it's a good idea. Do this BEFORE we
			// allow the import code to do it's business.
			$this->book->pages_found = 0;
			$this->book->pages_scanned = 0;
			$this->book->scan_time = 0;
			$this->book->update();

			// Set the status to tell the import code it's OK to continue
			// We've moved this into the "cron import_pages" routine (or into the book model itself. It doesn't belong here.)
			// $this->book->set_status('scanning');

			// Try to identify the PHP executable on this system
			$php_exe = PHP_BINDIR.'/php5';		
			if (!file_exists($php_exe)) {
				$php_exe = PHP_BINDIR.'/php';
			}
			
			if (!file_exists($php_exe)) {
				echo json_encode(array('error' => 'Could not find php executable (php or php5) in '.PHP_BINDIR.'.'));
				$this->logging->log('error', 'debug', 'Could not find php executable (php or php5) in '.PHP_BINDIR.'.');
				return;
			}

			$fname = $this->logging->log('cron', 'info', 'Cron job "import_pages" initiated during import pages.');
	
			$this->common->ajax_headers();
			echo json_encode(array('message' => ''));

			// Now we can spawn the cron process.
			system('MACAW_OVERRIDE=1 "'.$php_exe.'" "'.$this->cfg['base_directory'].'/index.php" cron import_pages '.$this->book->barcode.' > /dev/null 2> /dev/null < /dev/null &');
			
		} catch (Exception $e) {
			$this->common->ajax_headers();
			echo json_encode(array('errormessage' => $e->getMessage()));
		}

	}

	/**
	 * Get the progress of the scanning (or initial ingest of scanned pages)
	 *
	 * AJAX: Gets the list of files for this book and their status as to being
	 * scanned and processed. The data comes from the database, which is in
	 * turn populated by the cron job. This also includes information about
	 * how many pages were found and how many are remaining to be imported.
	 *
	 * @since Version 1.0
	 */
	function progress() {
		if (!$this->common->check_session(true)) {
			return;
		}

		$this->common->ajax_headers();
		$bc = $this->session->userdata('barcode');
		$this->book->load($bc);

		// Strip out anything that's Processed. We want them to disappear from
		// the list when they are done.
		$raw_pages = $this->book->get_pages('filebase', 'asc');
		$pages = array();
		$c = 0;
		foreach ($raw_pages as $p) {
			if ($p->status != 'Processed') {
				array_push($pages, $p);
				$c++;
				if ($c == 15) {
					break;
				}
			}
		}

		// If we got no pages, let's see what's on disk.
		if (count($pages) == 0) {
			$incoming = $this->cfg['incoming_directory'];
			$files = get_filenames($incoming.'/'.$bc);
			sort($files);
			$pages = array();
			foreach ($files as $f) {
				$info = get_file_info($incoming.'/'.$bc.'/'.$f, 'size');
				$pages[] = array(
					'filebase' => preg_replace('/\.(.+)$/', '', $f),
					'size' => $info['size'],
					'status' => 'New',
				);
				if (count($pages) >= 20) {
					break;
				}
			} // foreach ($files as $f)
		}
		// Send out results
		echo json_encode(array(
			'Result' => $pages,
			'Pages_Found' => ($this->book->pages_found ? $this->book->pages_found : 0) ,
			'Pages_Imported' => ($this->book->pages_scanned ? $this->book->pages_scanned : 0),
			'Time_Start' => $this->book->scan_time,
			'Time_Now' => time(),
		));
	}

	/**
	 * Finish scanning a book
	 *
	 * Simply mark the current book as finished scanning and redirect
	 * to the review page.
	 *
	 * @since Version 1.0
	 */
	function end_scan() {
		$this->common->check_session();
		// Permission Checking
		if (!$this->user->has_permission('scan')) {
			$this->session->set_userdata('errormessage', 'You do not have permission to access that page.');
			redirect($this->config->item('base_url').'main');
			$this->logging->log('error', 'debug', 'Permission Denied to access '.uri_string());
			return;
		}

		try {
			// Get our book
			$this->book->load($this->session->userdata('barcode'));
			$this->book->set_status('scanning');
			$this->book->set_status('scanned');
			redirect($this->config->item('base_url').'scan/review');
			$this->logging->log('access', 'info', 'Scanning completed for '.$this->session->userdata('barcode').'.');

		} catch (Exception $e) {
			// Set the error and redirect to the main page
			$this->session->set_userdata('errormessage', $e->getMessage());
			$this->logging->log('error', 'debug', 'Error in end_scan()'. $e->getMessage());
			redirect($this->config->item('base_url').'main');
		} // try-catch
	}

	/**
	 * Skip scanning a book
	 *
	 * Somtimes we don't want to scan at all. Usually when we've started scanning, leave the page and then come back.
	 *
	 * @since Version 1.5
	 */
	function skip_scan() {
		$this->common->check_session();
		// Permission Checking
		if (!$this->user->has_permission('scan')) {
			$this->session->set_userdata('errormessage', 'You do not have permission to access that page.');
			redirect($this->config->item('base_url').'main');
			$this->logging->log('error', 'debug', 'Permission Denied to access '.uri_string());
			return;
		}

		try {
			// Get our book
			$this->book->load($this->session->userdata('barcode'));

			$this->book->set_status('scanning');
			$this->book->set_status('scanned');
			redirect($this->config->item('base_url').'scan/review');
			$this->logging->log('access', 'info', 'Scanning completed for '.$this->session->userdata('barcode').'.');

		} catch (Exception $e) {
			// Set the error and redirect to the main page
			$this->session->set_userdata('errormessage', $e->getMessage());
			redirect($this->config->item('base_url').'main');
		} // try-catch
	}


	/**
	 * Finish scanning missing pages
	 *
	 * Simply mark the current book as finished scanning and redirect
	 * to the review page.
	 *
	 * @since Version 1.0
	 */
	function end_missing_scan() {
		$this->common->check_session();
		// Permission Checking
		if (!$this->user->has_permission('scan')) {
			$this->session->set_userdata('errormessage', 'You do not have permission to access that page..');
			redirect($this->config->item('base_url').'main');
			$this->logging->log('error', 'debug', 'Permission Denied to access '.uri_string());
			return;
		}

		try {
			// Get our book
			$this->book->load($this->session->userdata('barcode'));

			$this->book->set_status('scanned');
			redirect($this->config->item('base_url').'scan/review');
			$this->logging->log('access', 'info', 'Scanning completed for '.$this->session->userdata('barcode').'.');

		} catch (Exception $e) {
			// Set the error and redirect to the main page
			$this->session->set_userdata('errormessage', $e->getMessage());
			$this->logging->log('error', 'debug', 'Error in end_missing_scan()'. $e->getMessage());
			redirect($this->config->item('base_url').'main');
		} // try-catch
	}



	function review_item($item_id = null, $page_ids = null) {
		$this->common->check_session();

		// Get the barcode based on the id
		$barcode = $this->book->get_barcode($item_id);
		
		if (!$barcode) {
			$this->session->set_userdata('errormessage', 'That item was not found. (Item ID '.$item_id.')');
			redirect($this->config->item('base_url').'main/listitems');
			return;
		}

		
		if (!$this->book->exists($barcode)) {
			// We don't want to be left with a situation that makes the user thinks they
			// have a valid book when it was invalid. Reset the session variables.
			$this->session->set_userdata('barcode', '');
			$this->session->set_userdata('title', '');
			$this->session->set_userdata('author', '');

			$this->session->set_userdata('errormessage', 'That item was not found. (Item ID '.$item_id.')');
			redirect($this->config->item('base_url').'main/listitems');
			return;
		}


		// Query the database for the barcode
		try {
			// Get the book
			$this->book->load($barcode);
			


			// Do we have permission to view this item? 
			// People with the same org or super admins can access it, everyone else gets denied
			$this->user->load($this->session->userdata('username'));
			if ($this->book->org_id != $this->user->org_id && !$this->user->has_permission('admin')) {
				// Give our response to the user.
				$this->session->set_userdata('errormessage', 'You do not have permission to use that item.');
				redirect($this->config->item('base_url').'main/listitems');
				return;
			}

 			// Set the barcode and other info in the session
			$this->session->set_userdata('barcode', $this->book->barcode);
			$this->session->set_userdata('title', $this->book->get_metadata('title'));
			$this->session->set_userdata('author', $this->book->get_metadata('author'));

			// Redirect to the main activities page
			// Redirect to review, passing in the page ids
			redirect($this->config->item('base_url').'scan/review/#'.$page_ids);
		
		} catch (Exception $e) {
			// We don't want to be left with a situation that makes the user thinks they
			// have a valid book when it was invalid. Reset the session variables.
			$this->session->set_userdata('barcode', '');
			$this->session->set_userdata('title', '');
			$this->session->set_userdata('author', '');

			// Redirect to the main activities page
			$this->session->set_userdata('errormessage', $e->getMessage());
			redirect($this->config->item('base_url').'main/listitems');
			return;
		}
	}
	/**
	 * Show the review page
	 *
	 * Deceptively small, this is where the all the action happens. And all of it
	 * happens in javascript on the page as well as through a few AJAX/JSON calls
	 * to the server. This just shows the page and sets some critical pieces
	 * of data that are needed on the page. The rest happens dynamically.
	 *
	 * @since Version 1.0
	 */
	function review($all = null) {
		$this->common->check_session();
		// Permission Checking
		if (!$this->user->has_permission('scan')) {
			$this->session->set_userdata('errormessage', 'You do not have permission to access that page.');
			redirect($this->config->item('base_url').'main/listitems');
			$this->logging->log('error', 'debug', 'Permission Denied to access '.uri_string());
			return;
		}

		if ($all && !$this->user->has_permission('admin')) {
			$this->session->set_userdata('errormessage', 'You do not have permission to access that page.');
			redirect($this->config->item('base_url').'main/listitems');
			$this->logging->log('error', 'debug', 'Permission Denied to access '.uri_string());
			return;
		}
		
		if (!$all) {
			try {
				// Get our book
				$this->book->load($this->session->userdata('barcode'));
				$this->common->check_missing_metadata($this->book);
				
				// Is the book locked?
				$locked = $this->book->get_metadata('locked-by');
				// If so, report an error and how long it's been locked.
				if ($locked && $locked != $this->session->userdata('username')) {
					$locked_on = $this->book->get_metadata('locked-on');
					$last_saved = $this->book->get_metadata('last-saved-on');
					if (!$last_saved) { 
						$last_saved = date('Y-m-d H:i:s T');
					}
					
					if ((time() - strtotime($last_saved)) > 3600) {
						$this->session->set_userdata(
							'errormessage',
							'This item is in use by '.$locked.' as of '.$locked_on.'. It has been more than an hour since the item was last saved. You may <a href="/scan/break_lock">break the lock</a> to continue editing the item.'
						);
						redirect($this->config->item('base_url').'main/listitems');
						return;
						
					} else {
						$this->session->set_userdata(
							'errormessage',
							'This item is in use by '.$locked.' as of '.$locked_on.'. Please wait or ask them to complete the item.'
						);
						redirect($this->config->item('base_url').'main/listitems');
						return;
					
					}
					
				}
			} catch (Exception $e) {
				// Set the error and redirect to the main page
				$this->session->set_userdata('errormessage', $e->getMessage());
				$this->logging->log('error', 'debug', 'Error in review() '. $e->getMessage());
				redirect($this->config->item('base_url').'main');
			} // try-catch
				
			// If not, lock it.
			$this->book->set_metadata('locked-by', $this->session->userdata('username'));
			$this->book->set_metadata('locked-on', date('Y-m-d H:i:s T'));
			$this->book->update();
			
			$this->book->set_status('reviewing', $this->user->has_permission('admin'));
		} 
		
		$data = array();
		$data['base_directory'] = $this->cfg['base_directory'];
		
		if (!$all) {
			$data['metadata_modules'] = $this->cfg['metadata_modules'];
			$data['ia_identifier'] = $this->book->barcode;
			$data['item_title'] = $this->session->userdata('title');
		} else {
			$data['metadata_modules'] = array('NEH_filter');
			$data['ia_identifier'] = '';
			$data['item_title'] = 'Viewing all page images';	
		}
		$data['all'] = $all;
		
		$this->load->view('scan/review_view', $data);
		$this->logging->log('access', 'info', 'Scanning review begins for '.$this->book->barcode);
		$this->logging->log('book', 'info', 'Scanning review begins.', $this->book->barcode);

	}

	function break_lock() {
		$this->book->load($this->session->userdata('barcode'));
		$this->book->set_metadata('locked-by', null);
		$this->book->set_metadata('locked-on', null);
		$this->book->update();	
		redirect($this->config->item('base_url').'scan/review');
	}

	/**
	 * Get the thumbnails for a book
	 *
	 * AJAX: Gathers an array of objects for the thumbnails and returns JSON.
	 *
	 * @since Version 1.0
	 */
	function get_thumbnails($filter = null) {

		if (!$this->common->check_session(true)) {
			return;
		}

		$this->common->ajax_headers();
		try {
			$this->book->load($this->session->userdata('barcode'));
		} catch (Exception $e) {
			echo json_encode(array(
				'pages' => array(),
				'page_types' => $this->cfg['page_types'],
				'piece_types' => $this->cfg['piece_types'],
			));
			return;
		} // try-catch

		// Get the pages based on the $filter passed in from outside
		$pages = null;

		if ($filter == 'missing') {
			$pages = $this->book->get_pages('', 'asc', 0, true);
		} elseif ($filter == 'non_missing') {
			$pages = $this->book->get_pages('', 'asc', 0, false, true);
		} else {
			$pages = $this->book->get_pages();
		}

		echo json_encode(array(
			'pages' => $pages
// 		   ,'page_types' => $this->cfg['page_types'],
// 			'piece_types' => $this->cfg['piece_types'],
		));
	}


	/**
	 * Get the thumbnails for all books
	 *
	 * AJAX: Gathers an array of objects for the thumbnails and returns JSON.
	 * Example: http://site.com/scan/get_all_thumbnails/field,asc/1000/field=val
	 *
	 * @since Version 1.0
	 */
	function get_all_thumbnails() {	
		$args = func_get_args();
		$pages = $this->book->get_all_pages($args);
		echo json_encode(array(
			'pages' => $pages
		));
	}


	/**
	 * Save metadata and page order
	 *
	 * AJAX: alled from the review page, this saves all of the metadata for all
	 * of the pages in a book. While setting each page's metadata, we also set
	 * the page order for the page. All the stuff that this does happens in a
	 * transaction so we don't lose everything if something goes wrong. Keep in
	 * mind that all of the metadata is wiped out of the database before we save
	 * and so we need to have this transaction in place.
	 *
	 * @since Version 1.0
	 */
	function save_pages() {
		if (!$this->common->check_session(true)) {
			return;
		}

		$this->common->ajax_headers();
		// Get our book
        $this->book->load($this->session->userdata('barcode'));

		// Embedded ampersands in the data cause trouble.
		$data = preg_replace('/\&/i', '&amp;', $this->input->post('data'));
		//$this->logging->log('book', 'info', 'Submitted save data: '.$data, $this->session->userdata('barcode'));		
		// Get the data from the page
		$data = json_decode($data, true);

		// Make sure we got stuff for the current book (??)
		if ($data['item_id'] != $this->book->id) {
			show_error('The ID of the book ('.$data['item_id'].') does not match that of your session ('.$this->book->id.'). Please go back and re-scan the barcode.');
			return;
		}
		// Delete the metadata for the book
		$this->db->trans_start();
		//$this->book->delete_page_metadata(); //No longer deleting all the metadata, only on a page by page basis

		$sequence_count = 1;
		// Cycle through the pages in the book
		foreach ($data['pages'] as $page) {
			if (isset($page['page_id']) && isset($page['metadata'])) {
				 // We only delete the metadta for pages that have content
				  $this->book->delete_page_metadata($page['page_id']);
			}
			//
			// Let's hope the data looks something like this...
			// $data = array(
			//		'item_id' => 124,
			//		'pages'   => array(
			//			array(
			//				'page_id'  => 21734,
			// 				'deleted'  => false,
			//				'metadata' => array(
			//					'page_type'     => array('Text', 'Title Page'),
			//					'piece'         => array('No.),
			//					'piece_text'    => array('12'),
			//					'year'          => '1875',
			//					'future_review' => '1',
			//					'page_side'     => 'Recto (right)',
			//				),
			//			),
			//			array(
			//				'page_id'  => 21734,
			// 				'deleted'  => true,
			//				'metadata' => array(
			//					'page_type'     => array('Text', 'Title Page'),
			//					'piece'         => array('No.),
			//					'piece_text'    => array('12'),
			//					'year'          => '1875',
			//					'future_review' => '1',
			//					'page_side'     => 'Recto (right)',
			//				)
			//			)
			//		)
			//	);
			
			if ($page['deleted']) {
				$this->book->delete_page($page['page_id']);
			} else {
				//$this->logging->log('book', 'info', 'About to save page data for page'.$page['page_id'], $this->session->userdata('barcode'));
				// Assume we have an array of name/value pairs.
				foreach (array_keys($page['metadata']) as $key) {
					$dt = $page['metadata'];
	
					// If an value is an array
					if (is_array($dt[$key])) {
						$c = 1;
						//  cycle through that array of values
						foreach ($dt[$key] as $val) {
							// Save the values and increment the counter as we go
							$this->book->set_page_metadata($page['page_id'], $key, $val, $c);
							$c++;
						}
					} else {
						// Otherwise, just save with a counter of 1.
						$this->book->set_page_metadata($page['page_id'], $key, $dt[$key], 1);
					}
				}
			
				//echo(var_dump($p));
				// Cycle through the array of possible metadata fields
// 				foreach ($this->cfg['page_metadata_fields'] as $m) {
// 					//echo(var_dump($m));
// 					if ($m == 'page_type') { // Is this page type
// 						$c = 1;
// 						// Cycle through the array of page types
// 						foreach ($p->metadata->page_types as $t) {
// 							// Add them to the database
// 							$this->book->set_page_metadata($p->page_id, 'page_type', $t->type, $c);
// 							$c++;
// 						}
// 
// 					} else if ($m == 'piece') { // Is this pieces
// 						$c = 1;
// 						// Cycle through the array of pieces
// 						foreach ($p->metadata->pieces as $t) {
// 							// Add them to the database
// 							$this->book->set_page_metadata($p->page_id, 'piece', $t->type, $c);
// 							$this->book->set_page_metadata($p->page_id, 'piece_text', $t->text, $c);
// 							$c++;
// 						}
// 
// 					} else if ($m == 'piece_text') {
// 						// We do nothing here because we handle piece and piece_text together.
// 						// But we need this placeholder to skip over it entirely.
// 
// 					} else if ($m == 'page_number_implicit') {
// 						if ($p->metadata->$m) {
// 							$this->book->set_page_metadata($p->page_id, $m, $p->metadata->$m);
// 						}
// 					} else {
// 						// Re-add the metadata that was submitted from the user
// 						if (isset($p->metadata->$m)) {
// 							$this->book->set_page_metadata($p->page_id, $m, $p->metadata->$m);
// 						}
// 					} // if ($m == 'page_type') ...
// 				} // foreach ($this->cfg['page_metadata_fields'] as $m)

				// Update sequence Numbers
				// NEH DOESNT NEED THIS
				// $this->book->set_page_sequence($page['page_id'], $sequence_count++);
				if (!$data['inserted_missing']) {
					$this->book->set_missing_flag($page['page_id'], false);
				}
			}

		} // foreach ($data->pages as $p)
		$this->book->set_metadata('last-saved-on', date('Y-m-d H:i:s T'));
		$this->book->update();

		$this->db->trans_complete();

		echo json_encode(array('message' => 'Changes saved!'));
		$this->logging->log('book', 'info', 'Page data saved.', $this->session->userdata('barcode'));
	}

	/**
	 * Finish reviewing a book
	 *
	 * AJAX: Simply mark the current book as finished reviewing and redirect
	 * to the main page. We assume that the save routine was called prior to this.
	 *
	 * @since Version 1.0
	 */
	function end_review() {
		if (!$this->common->check_session(true)) {
			return;
		}

		$this->common->ajax_headers();
		try {
			// Get our book
			$this->book->load($this->session->userdata('barcode'));

			// Does the book need to be QA'ed by someone?
			if ($this->book->needs_qa) {
				// Is the person reviewing the book a QA person?
				if ($this->user->has_permission('QA')) {
					// Only a QA person can finish a book that's marked for QA
					$this->book->set_status('reviewed');
				} else {
					// Leave the book open
					$this->book->set_status('reviewing');
					// Email the QA staff that it needs to be reviewed
					$this->_notify_qa();
				}
			} else {
				// Otherwise, we just treat the book normally and finish it.
				$this->book->set_status('reviewed');
			}
			// Unlock the book
			$this->book->set_metadata('locked-by', null);
			$this->book->set_metadata('locked-on', null);
			$this->book->set_metadata('last-saved-on', null);
			// Set the last edited to the name of the user
			$this->book->set_metadata('last-edited-by', $this->session->userdata('username'));
			$this->book->set_metadata('last-edited-on', date('Y-m-d H:i:s T'));

			$this->book->update();
			$this->session->set_userdata('message', 'Changes saved! ');

			header("Content-Type: application/json");
			echo json_encode(array('redirect' => $this->config->item('base_url').'main/listitems'));
			$this->logging->log('access', 'info', 'Scanning review completed for '.$this->session->userdata('barcode').'.');

		} catch (Exception $e) {
			// Set the error and redirect to the main page
			$this->session->set_userdata('errormessage', $e->getMessage());
			redirect($this->config->item('base_url').'main');
		} // try-catch
	}


	/**
	 * Notify the QA staff that something needs reviewing
	 *
	 * INTERNAL: Called when a book is finished by a non-QA person.
	 *
	 * @since Version 1.4
	 */
	function _notify_qa() {


		// Get a list of all QA users and their email addresses
		$qa_users = array();
		$this->db->where('username in (select username from permission where permission = \'QA\');');
		$this->db->select('email');
		$query = $this->db->get('account');
		foreach ($query->result() as $row) {
			array_push($qa_users, $row->email);
		}
		
		// If we didn't get any QA users, let's send to the admin users
		if (count($qa_users) == 0) {
			$this->db->where('username in (select username from permission where permission = \'admin\');');
			$this->db->select('email');
			$query = $this->db->get('account');
			foreach ($query->result() as $row) {
				array_push($qa_users, $row->email);
			}
		}
		
		if (count($qa_users) > 0) {
			// Generate the message we're going to send
			$this->load->library('email');
	
			$config['protocol'] = 'smtp';
			$config['crlf'] = '\r\n';
			$config['newline'] = '\r\n';
			$config['smtp_host'] = $this->cfg['email_smtp_host'];
			$config['smtp_port'] = $this->cfg['email_smtp_port'];
			if ($this->cfg['email_smtp_user']) { $config['smtp_user'] = $this->cfg['email_smtp_user']; }
			if ($this->cfg['email_smtp_pass']) { $config['smtp_pass'] = $this->cfg['email_smtp_pass']; }
			
			$this->email->initialize($config);
			$this->email->from($this->cfg['admin_email'], 'MACAW Admin');
			$this->email->to($qa_users);
			$this->email->bcc($this->cfg['admin_email']);
			$this->email->subject('[Macaw] QA Notification');
			$this->email->message(
				'This is a message from the MACAW server located at: '.$this->config->item('base_url')."\r\n\r\n".
				'The following item is now ready for QA review: '."\r\n\r\n".
				'Title: '.$this->book->get_metadata('title')."\r\n".
				'Barcode: ' .$this->book->barcode."\r\n".
				'Edited By: '.$this->session->userdata('full_name').' ('.$this->session->userdata('username').')'."\r\n\r\n".
				'To review this item, log into Macaw and enter the barcode above.'
			);
			error_reporting(0);
			if (!$this->email->send()) {
				$this->session->set_userdata('warning', "Warning: Unable to send QA notification email. Please check your SMTP settings.");
			}
			error_reporting(E_ALL & ~E_DEPRECATED);
		} else {
			$this->session->set_userdata('warning', "Warning: Unable to send QA notification email. Could not find a QA or Admin user.");
		}
	}


	/**
	 * Archives items to cold storage
	 *
	 * CLI: Searches for and copied books to cold storage. This can be used any
	 * number of times on one item to accommodate missing pages that were added
	 * since the last archive().
	 *
	 * @since Version 1.???
	 */
	function archive() {

	}

	/**
	 * Display the history for a book
	 *
	 * Shows the history page for the book whose barcode is in the session.
	 * Calls Book.get_history()
	 *
	 * @since Version 1.0
	 */
	function history() {
		$this->common->check_session();
		// Permission Checking
		if (!$this->user->has_permission('scan')) {
			$this->session->set_userdata('errormessage', 'You do not have permission to access that page.');
			redirect($this->config->item('base_url').'main');
			$this->logging->log('error', 'debug', 'Permission Denied to access '.uri_string());
			return;
		}

		$this->book->load($this->session->userdata('barcode'));
		$this->common->check_missing_metadata($this->book);
		$data['item_title'] = $this->session->userdata('title');
		$data['log'] = $this->book->get_history();
		$this->load->view('scan/history_view', $data);
	}

	/**
	 * Hnadle missing pages
	 *
	 * Depending on the argument passed in, we are either 'start'ing the
	 * missing pages process or we are 'insert'ing the missing pages. We may also
	 * 'cancel' the import of missing pages, but only if the import has not
	 * started. Finally, we can 'finish' importing the missing images.
	 *
	 * When we are starting, then we set the book status (back) to scanning
	 * and set the missing_pages flag to true before redirecting to the scanning
	 * monitor page.
	 *
	 * When we are inserting the missing pages, we simply show the missing pages
	 * window. There are no settings to be made.
	 *
	 * When cancelling, the status_code of the item is recalculated from the
	 * information in the database to revert the status back to what it should be.
	 *
	 * When finishing, the book is marked as 'scanned' and we redirect the browser
	 * to the review page.
	 *
	 * @since Version 1.1
	 */
	function missing($arg) {
		if ($arg == 'start') {

			try {
				$this->common->check_session();
				// Set the status of the book to scanning
				$this->book->load($this->session->userdata('barcode'));
				$this->book->update();

				// Redirect to the scan monitor page
				redirect($this->config->item('base_url').'scan/monitor');
				$this->logging->log('access', 'info', 'Started scanning missing pages for '.$this->session->userdata('barcode').'.');
				$this->logging->log('book', 'info', 'Started scanning missing pages.', $this->session->userdata('barcode'));

			} catch (Exception $e) {
				$this->session->set_userdata('errormessage',  $e->getMessage());
				redirect($this->config->item('base_url').'main');
				return;
			}

		} elseif ($arg == 'cancel') {
			$this->common->check_session();

			// Figure out what the status should be based on the dates
			$this->book->load($this->session->userdata('barcode'));
			$this->book->set_status($this->book->get_last_status(), true);
			$this->logging->log('access', 'info', 'Cancelled import of missing pages for '.$this->session->userdata('barcode').'.');
			$this->logging->log('book', 'info', 'Cancelled import of missing pages.', $this->session->userdata('barcode'));
			redirect($this->config->item('base_url').'main');

		} elseif ($arg == 'finish') {
			if (!$this->common->check_session(true)) {
				return;
			}

			$this->common->ajax_headers();
			try {
				// Get our book
				$this->book->load($this->session->userdata('barcode'));
				if ($this->book->status == 'scanning') {
					$this->book->set_status('scanned');
				} 

				header("Content-Type: application/json");
				echo json_encode(array('redirect' => $this->config->item('base_url').'scan/review/'));
				$this->logging->log('access', 'info', 'Missing pages inserted for '.$this->session->userdata('barcode').'.');
				$this->logging->log('book', 'info', 'Missing pages inserted.', $this->session->userdata('barcode'));

			} catch (Exception $e) {
				// Set the error and redirect to the main page
				$this->session->set_userdata('errormessage', $e->getMessage());
				redirect($this->config->item('base_url').'main');
			} // try-catch


		} elseif ($arg == 'insert') {
			$this->common->check_session();
			// Show the insert missing pages
			$this->book->load($this->session->userdata('barcode'));
			$this->common->check_missing_metadata($this->book);
			if ($this->book->status == 'scanning') {
				$this->book->set_status('scanned');
			} 

			$data['item_title'] = $this->session->userdata('title');
			$data['metadata_modules'] = $this->cfg['metadata_modules'];
			$this->load->view('scan/missing_view', $data);
			$this->logging->log('access', 'info', 'Began inserting missing pages for '.$this->session->userdata('barcode').'.');
			$this->logging->log('book', 'info', 'Inserting missing pages begins.', $this->session->userdata('barcode'));
		}
	}
}
