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


class NEHFix extends Controller {

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
	
	function NEHFix() {
		$this->CI = get_instance();
		$this->cfg = $this->CI->config->item('macaw');
	}

	function get_new_items($args) {
	
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
		
		$found = $this->_find_identifier($o, $this->import_filename);
		if ($found) {
			$this->CI->db->trans_start();
			foreach ($o['pages'] as $p) {
				$this->_import_page($o, $p);
			}
			$this->CI->db->trans_complete();
			echo "\n";
		}		
	}
	
	function _find_identifier($json, $fname) {
		$item = array();
		$item['barcode'] = $json['barcode'];
		if ($this->CI->book->exists($json['barcode'])) {
			echo 'Identifier exists: '.$json['barcode']."\n";
			$this->CI->book->load($json['barcode']);
			return 1;
		}
		return 0;
	}

	function _import_page($object, $page) {
		$lf = $page['sequence_order'];
		$ht = $page['height'];
		$wd = $page['width'];

		$img = 'http://archive.org/download/'.$object['barcode'].'/page/n'.($lf-1);

		// Knowing the image, get the page
		$this->CI->db->where('filebase', "$img");
		$pgrec = $this->CI->db->get('page');

		// Did we get a record?
		if ($pgrec->num_rows() > 0) {
			// Now see if we have the metadata record for the following items:
			// pageid, abbyy_hasillustration, contrast_hasillustration, percent_coverage, illustrations
			$row = $pgrec->row();

			$fields = array(
				'pageid', 'abbyy_hasillustration', 'contrast_hasillustration', 
				'percent_coverage', 'illustrations', 'height', 'width'
			);
			foreach ($fields as $f) {
				$this->CI->db->where('page_id', $row->id);
				$this->CI->db->where('fieldname', $f);
				$count = $this->CI->db->count_all_results('metadata');

				if ($count == 0) {
					if ($f == 'illustrations') {
						$this->CI->book->set_page_metadata($row->id, $f, serialize($page[$f]));
					} else {
						$this->CI->book->set_page_metadata($row->id, $f, $page[$f]);
					}
					echo ".";					
				}			

			}
		}	
		return true;
	}

}
