<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
// ***********************************************************
// Macaw Metadata Collection and Workflow System
//
// EXPORT LIBRARY
//
// Each destination with whom we share our book will have an export routine
// which contains functions for sending data to the system, verifying receipt
// of the data, and optionally pulling any derivative data, etc.
// Each Export library corresponds to an entry in the macaw.php file.
//
// Each module has a library with the name "Export_Name.php". The name
// must correspond to one of the items in the export_modules entry in the macaw.php
// configuration file. Each must contain ax export() methox. Other functions
// may be used if necessary.
//
// Each module must set a "completed" status to the export process via the
// Book objects set_export_status() method:
//
// $this->CI->book->set_export_status('completed');
//
// Other statuses are allowed if the exporting happens in multiple steps.
// This module is required to maintan the statuses and eventually set a
// status of 'completed' when it's finished exporting. Once all export
// modules have marked the item as completed, Macaw then proceeds to archive
// and purge the data on its own schedule, if such routines are set up.
//
// Change History
// Date        By   Note
// ------------------------
// 2010-07-07  JMR  Created
// 2011-08-11  JMR  Trimmed down to include only the export() method
//
// ***********************************************************

class NEH extends Controller {

	var $CI;
	var $cfg;

	// ----------------------------
	// Function: CONSTRUCTOR
	//
	// Be sure to rename this from "Export_Generic" to whatever you named the
	// class above. Othwerwise, ugly things will happen. You don't need to edit
	// anything here, either.
	// ----------------------------

	function NEH() {
		$this->CI = get_instance();
		$this->cfg = $this->CI->config->item('macaw');
	}

	// ----------------------------
	// Function: export()
	//
	// Parameters:
	//    $args - An array of items passed from the command line (or URL)
	//            that are specific to this module. The Export Mode
	//            simply passes these in as the were received.
	//
	// Sends everything to the export. This function is called by the
	// Exporter model. The code in this function will be unique for each
	// export destination. If additional files need to be created for expport,
	// they are done here. This function may connect to web servers, send files
	// by FTP or whatever else might need to be done to submit data to a remote
	// system.
	// ----------------------------
	function export($args) {
		// 0. Output the header info for the JSON Output file 
		
		$fh = fopen('/tmp/macaw_neh_export.json', 'w');
		fwrite($fh, '{"items":[');

		// 1. Get all items from the database	
 		$books = $this->CI->book->get_all_books();
		
		$bk = array();
		// 2. Loop through each item's pages 
		foreach ($books as $b) {
			$this->CI->book->load($b->barcode);

 			// 3. Create a structure of the item in memory
 			$bk['itemid'] = $this->CI->book->get_metadata('itemid');
 			$bk['barcode'] = $b->barcode;
 			$bk['author'] = $this->CI->book->get_metadata('author');
 			$bk['title'] = $this->CI->book->get_metadata('title');
 			$bk['volume'] = $this->CI->book->get_metadata('volume');
			$bk['publication_details'] = $this->CI->book->get_metadata('publication_details');
 			$bk['copyright'] = $this->CI->book->get_metadata('copyright');
 			$bk['date'] = $this->CI->book->get_metadata('date'); 			
 			$bk['subjects'] = unserialize($this->CI->book->get_metadata('subjects'));
 			$bk['contributor'] = array();
 			$bk['contributor']['contributing_library'] = $this->CI->book->get_metadata('contributing_library');
 			$bk['contributor']['is_member_library'] = ($this->CI->book->get_metadata('is_member_library') ? true : false);
 			$bk['pages'] = array();

 
 			// Loop through the pages
 			$pages = $this->CI->book->get_pages();

			foreach ($pages as $p) {
				$pg = array();

				$pg['pageid'] = $p->pageid;				
				$pg['sequence_order'] = (int)$p->sequence_number;
//				if (isset($p->abbyy_hasillustration)) {
					$pg['abbyy_hasillustration'] = ($p->abbyy_hasillustration ? true : false);
//				}
//				if (isset($p->contrast_hasillustration)) {
					$pg['contrast_hasillustration'] = ($p->contrast_hasillustration ? true : false);
//				}

				$pg['height'] = (int)$p->height;
				$pg['width'] = (int)$p->width;
				
				if (isset($p->pixel_depth)) {
					$pg['pixel_depth'] = (int)$p->pixel_depth;
				}
				if (isset($p->percent_coverage)) {
					$pg['percent_coverage'] = round($p->percent_coverage,4);
				}
				if (isset($p->illustrations)) {
					$pg['illustrations'] = unserialize($p->illustrations);
				}

				$pg['page_type'] = array();
				if (isset($p->neh_type_i)) {
					$pg['page_type'][] = $p->neh_type_i;
				}
				if (isset($p->neh_type_d)) {
					$pg['page_type'][] = $p->neh_type_d;
				}
				if (isset($p->neh_type_m)) {
					$pg['page_type'][] = $p->neh_type_m;
				}
				if (isset($p->neh_type_p)) {
					$pg['page_type'][] = $p->neh_type_p;
				}
				if (isset($p->neh_type_l)) {
					$pg['page_type'][] = $p->neh_type_l;
				}	
				if (isset($p->neh_color)) {
					$pg['color_or_bw'] = $p->neh_color;
				}
				if (isset($p->no_images)) {
					$pg['no_image_found'] = ($p->no_images == 'none' ? true : false);
				}

				$bk['pages'][] = $pg;
			}
			// 4. Convert the structure to JSON
			// 5. Export the structure to the JSON Output file
			fwrite($fh, json_encode($bk));
			print "Exported: ".$b->barcode."\n";
		}

		// 6. Output the closing info for the JSON Output file
		fwrite($fh, ']}');
		fclose($fh);

	}
}