<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Custom Import Module
 *
 * Provides an interface to local systems to get the bibliographic and
 * item-level metadata for a given installation of Macaw. This contains one
 * function which returns a list of things that are ready to be imported into
 * Macaw.
 *
 *
 * @package metadata
 * @author Joel Richard
 * @version 1.0 admin.php created: 2010-09-20 last-modified: 2010-08-19
 **/

require_once 'JsonStreamingParser/Listener.php';
require_once 'JsonStreamingParser/Parser.php';

class ArrayMaker implements JsonStreamingParser_Listener {
	private $_json; 
	private $_stack;
	private $_key;
	private $_callback; 
	 
	public function set_callback($function) {
		$this->_callback = $function;
	}	
	
	public function get_json() {
		return $this->_json;
	}
	 
	public function start_document() {
		$this->_stack = array(); 
		$this->_key = null;
	}
	 
	public function end_document() {
		// w00t!
	}
	 
	public function start_object() {
		array_push($this->_stack, array());
	}
	 
	public function end_object() {
		$obj = array_pop($this->_stack);
		if (empty($this->_stack)) {
			// doc is DONE!
			$this->_json = $obj;
		} else {
			if (isset($obj['itemid'])) {
 				call_user_func($this->_callback, $obj);
			} else {
				$this->value($obj);
			}
			unset($obj);
		}
	}
	 
	public function start_array() {
		$this->start_object();
	}
	 
	public function end_array() {
		$this->end_object();
	}
	 
	// Key will always be a string
	public function key($key) {
		$obj = array_pop($this->_stack);
		$obj['_key'] = $key;
		array_push($this->_stack, $obj);
	}
	 
	// Note that value may be a string, integer, boolean, null
	public function value($value) {
		$obj = array_pop($this->_stack);
		if (isset($obj['_key'])) {
			$obj[$obj['_key']] = $value;
			unset($obj['_key']);
		} else {
			array_push($obj, $value);
		}
		array_push($this->_stack, $obj);
	}
}


class NEH extends Controller {

	var $CI;
	var $cfg;
	public $error;

	private $access = 'otsX9UjZhLz6zKMX';
	private $secret = 'h796mCRDv73sJmlM';
	private $email = 'thompsonk@si.edu';
	private $pwd   = 'stardust'; // This is needed for our hack to fake a login to IA to see if a bucket exists. 
	private $import_filename = '';
	private $count = 0;
	private $duplicate = 0;
	
	function NEH() {
		$this->CI = get_instance();
		$this->cfg = $this->CI->config->item('macaw');
		$this->_check_custom_table();
	}

	/**
	 * Get new items
	 *
	 * Gets an array of things that are ready to be scanned. The structure of
	 * the resulting object must be the following, but the only required field
	 * in each item is the barcode.
	 *
	 * 		$items = Array(
	 * 				[0] => Array(
	 * 					'barcode'     => '39088010037075',
	 * 					'call_number' => 'Q11 .U52Z',
	 * 					'location'    => 'FISH',
	 * 					'volume'      => 'v 1; part 2',
	 * 					'copyright'   => '1'
	 * 				)
	 * 				[1] => Array(
	 *				  	[ ... ]
	 * 				)
	 * 				[2] => Array(
	 *				  	[ ... ]
	 * 				)
	 *				[ ... ]
	 * 			)
	 * 		)
	 *
	 * If other databases need to be reached, they can be done so from other
	 * functions that you create in this module. It's expected that this will
	 * be necessary.
	 *
	 * @return Array of arrays containing the information for new items.
	 */
	function get_new_items($args) {
	
		// Fix the page table. This has no effect if the table is already adjusted.
		$this->CI->db->query('alter table page alter column filebase type varchar(300);');
		
		// TODO: The first argument should be the name of a file containing information to import
		$filename = '';
		$fname = '';
		if (count($args) <= 0) {
			print "Import file not found!\nUsage: php index.php cron import NEH \"FILENAME\"\n";
			$this->CI->common->save_import_status($fname, 0, 'Import file not found!', 1);
			die;
			
		} else {
			$filename = '/'.implode($args, '/');
			$fname = end($args);
			$destination = $this->cfg['data_directory'].'/import_export/'.$fname;
			copy($filename, $destination);
			$this->import_filename = $destination;
		}

		$listener = new ArrayMaker();
		$listener->set_callback(array($this, 'callback_load_item'));
		
		$stream = fopen($filename, 'r');
		
		try {
			$parser = new JsonStreamingParser_Parser($stream, $listener);
			$parser->parse();  
		} catch (Exception $e) {
			fclose($stream);
			throw $e;
		}
		
		$this->CI->common->save_import_status($this->import_filename, $this->count, 'Finished! ('.$this->duplicate.' duplicates)', 1);
		return array();
	}
	
	function callback_load_item($o) {
		$this->count++;
		
		$added = $this->_setup_identifier($o, $this->import_filename);
		if ($added) {
			foreach ($o['pages'] as $p) {
				$this->_import_page($o, $p);
			}
			$this->CI->common->save_import_status($this->import_filename, $this->count, 'Item added with barcode '.$this->CI->book->barcode.' and id '.$this->CI->book->id);
		}		
	}
	
	function _setup_identifier($json, $fname) {
		// Does the item already exist? For now, we skip doing anything.
		// TODO: Make this update the item.

		$item = array();
		$item['barcode'] = $json['barcode'];
		if ($this->CI->book->exists($json['barcode'])) {
			$this->CI->common->save_import_status($fname, 0, 'Identifier already exists: '.$json['barcode']);		
			echo 'Identifier already exists: '.$json['barcode']."\n";
			$this->duplicate++;
			return 0;

		} else {
			// Call the Book model's ->add() function to add the book to Macaw. This will also create directories.
			$id = $this->CI->book->add($item);
			$this->CI->book->load($json['barcode']);
			$this->CI->book->set_status('scanned', true);
		
			$this->CI->book->set_metadata('itemid', $json['itemid']);
			$this->CI->book->set_metadata('author', $json['author']);
			$this->CI->book->set_metadata('title', $json['title']);
			$this->CI->book->set_metadata('volume', $json['volume']);
			$this->CI->book->set_metadata('publication_details', $json['publication_details']);
			$this->CI->book->set_metadata('copyright', $json['copyright']);
			$this->CI->book->set_metadata('date', $json['date']);
			$this->CI->book->set_metadata('contributing_library', $json['contributor']['contributing_library']);
			$this->CI->book->set_metadata('is_member_library', $json['contributor']['is_member_library']);
			$this->CI->book->set_metadata('subjects', serialize($json['subjects']));
	
			$this->CI->book->update();
			echo 'Item added with barcode '.$json['barcode'].' and id '.$id."\n";
		}
		
		return 1;
	}

	function _import_page($object, $page) {
		$lf = $page['sequence_order'];
		$ht = $page['height'];
		$wd = $page['width'];

		$img = 'http://archive.org/download/'.$object['barcode'].'/page/n'.($lf-1);

		$this->CI->db->trans_start();

		// Add the page
		$data = array(
			'item_id'					=> $this->CI->book->id,
			'filebase'				=> $img,
			'status'					=> 'Processed',
			'bytes'						=> 0,
			'sequence_number'	=> $lf,
			'extension'				=> 'jpg',
			'width'						=> $wd,
			'height'					=> $ht,
			'is_missing'			=> 'false'
		);
		
		$this->CI->db->set($data);
		$this->CI->db->insert('page');
		$page_id = $this->CI->db->insert_id();

		$this->CI->book->set_page_metadata($page_id, 'pageid', $page['pageid']);
		$this->CI->book->set_page_metadata($page_id, 'abbyy_hasillustration', $page['abbyy_hasillustration']);
		$this->CI->book->set_page_metadata($page_id, 'contrast_hasillustration', $page['contrast_hasillustration']);
//		$this->CI->book->set_page_metadata($page_id, 'pixel_depth', $page['pixel_depth']);
		$this->CI->book->set_page_metadata($page_id, 'percent_coverage', $page['percent_coverage']);
		$this->CI->book->set_page_metadata($page_id, 'illustrations', serialize($page['illustrations']));

		$this->CI->db->trans_complete();

	}

	function _translate_pagetype($t) {
		if ($t == 'Title') {
			return 'Title Page';
		} else if ($t == 'Illustrations') {
			return 'Illustration';
		} else if ($t == 'Delete') {
			return 'Suppress';
		} else if ($t == 'Normal') {
			return 'Text';
		}
		return $t;
	}

	// ----------------------------
	// Function: _bucket_exists()
	//
	// Parameters:
	//    $id: The IA identifier we are testing for
	//
	// Makes an attempt to determine whether or not an item exists at internet
	// archive by checking the details page. If we get a 503 error or the string
	// "item cannot be found" appears on the page, then we assume that the s3
	// bucket does not exist. This is used in both making sure we aren't using an
	// identifier that already exists as well as for checking to see if the
	// bucket is created before uploading additional items to it.
	// ----------------------------
	function _bucket_exists($id) {
		$urls = $this->_get_derivative_urls($id);
		return (count($urls) > 0);
	}

	// ----------------------------
	// Function: _get_derivative_urls()
	//
	// Parameters:
	//    $id - The Internet Archive ID of the book in question
	//
	// Gets a list of all of the files on the "HTTP" page for the book.
	// The URL is usually of the form:
	//      http://ia600407.us.archive.org/26/items/Notessurlesmoll00Heud/
	// But we need to get that URL from the main page here:
	//      http://www.archive.org/details/Notessurlesmoll00Heud
	// ----------------------------
	function _get_derivative_urls($id) {
		// Get the content of the details page.
		$matches = array();
		$content = file_get_contents("http://www.archive.org/download/$id");
		if (preg_match_all('<a href="(.*?)">', $content, $matches)) {
			return $matches[1];
		}
		// Just in case, return nothing, which is an error.
		return array();
	}

	function _get_dpi($marc, $pixels) {
		// Get our mods into something we can use. All the info we need is in there.
		$marc = simplexml_load_string($marc);
		$namespaces = $marc->getDocNamespaces();				// Create a new namespace for ease of parsing
		$marc->registerXPathNamespace('ns', $namespaces['']);	// Add the new namespace for XPath

 		$ret = ($marc->xpath("/ns:record/ns:datafield[@tag='300']/ns:subfield[@code='c']"));		
		if (!$ret) {
			return 0;
		}
		$height = $ret[0].'';
		$unit = 'cm';
		// Get the height of the book
		$matches = array();
		if (preg_match('/(\d+) ?(cm|in)/', $height, $matches)) {
			// 45 cm.
			// 35cm.
			$height = $matches[1];
			$unit = $matches[2];
		} elseif (preg_match('/(\d+)-(\d+) ?(cm|in)/', $height, $matches)) {
			// 48-51 cm.
			// 24-26 cm. and atlases of plates (part col.) 42 cm.
			$height = $matches[2];
			$unit = $matches[3];
		} elseif (preg_match('/(\d+) ?x ?(\d+) ?(cm|in)/', $height, $matches)) {
			// 25 x 38 cm.
			// 25x38 cm.
			$height = $matches[1];
			$unit = $matches[3];
		} elseif (preg_match('/(\d+)-(\d+) ?x ?(\d+) ?(cm|in)/', $height, $matches)) {
			// 25-43 x 38 cm.
			// 25-43x38 cm.
			$height = $matches[2];
			$unit = $matches[4];
		} elseif (preg_match('/(\d+)/', $height, $matches)) {
			// Fallback, take the first number we can find.
			$height = $matches[1];
		} else {
			// Last resort, we'll default to returning 450 DPI for this item
			return 450;
		}

		// Get the pixel height of the first page and divide by our size
		print "DPI: $pixels / $height $unit = ";
		if ($unit == 'in') {
			print round($pixels / $height)."\n";
			return round($pixels / $height);
		} else {
			print round($pixels / $height / 0.393700787)."\n";
			return round($pixels / $height / 0.393700787);
		}
	}

	function _check_custom_table() {
		if (!$this->CI->db->table_exists('custom_internet_archive')) {
			$this->CI->load->dbforge();
			$this->CI->dbforge->add_field(array(
				'item_id' =>    array( 'type' => 'int'),
				'identifier' => array( 'type' => 'varchar', 'constraint' => '32' )
			));
			$this->CI->dbforge->create_table('custom_internet_archive');
		}
	}
}

//			SAMPLE JSON OBJECT 
//
// 			Array
// 			(
// 					[_id] => 503f82e09bb1100702b06083
// 					[has_illustration] => Array
// 							(
// 									[gold_standard] => 
// 									[contrast] => 
// 							)
// 			
// 					[scan_id] => mobot31753002245303
// 					[bytes_per_pixel] => 0.11434681054746
// 					[compression] => 0.11434681054746
// 					[page_num] => 93
// 					[abbyy] => Array
// 							(
// 									[coverage_max] => 0
// 									[picture_blocks] => Array
// 											(
// 											)
// 			
// 									[height] => 3027
// 									[total_coverage_sum] => 79
// 									[width] => 1878
// 							)
// 			
// 					[pixel_depth] => 8
// 					[benchmarks] => Array
// 							(
// 									[contrast] => Array
// 											(
// 													[total] => 0.095317
// 											)
// 			
// 							)
// 			
// 					[file_size] => 650028
// 					[ia_page_num] => 92
// 					[scandata_index] => 92
// 					[contrast] => Array
// 							(
// 									[total_time] => 0.91763800000001
// 									[1d_time] => 0.0016839999999547
// 									[image_detected] => 
// 									[max_contiguous] => 0.014
// 							)
// 			
// 			)
