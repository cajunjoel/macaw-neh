// ------------------------------
// CUSTOM METADATA OBJECT
//
// This represents the custom metadata for a single page in our book. This module is
// tricky in that it's purely custom and Macaw can operate without it. A copy of this
// object is created for each page in the book, but there are a few function that are
// run globally. This is tricky business. You'd better know your javascript to use this.
//
// Parameters
//    parent - Just in case we need it, the page object that contains us.
//    data - The data that makes up the entire metadata for this page.
//
// Revision History
//
// ------------------------------

YAHOO.macaw.NEH = function(parent, data) {

	// Intialize the fields that will hold our metadata
	this.data = data;
	this.sequence = data.sequence_number;
	this.parent = parent;
	this.filebase = data.filebase;
	this.pageID = this.parent.parent.pageID;
	this.nofilter = false;
	this.sizeSlider = null;
	
	// These correspond exactly to the id attributes in the php file
	// The "Type" specifier gives clues to render() and unrender() about
	// how to handle different types of fields.
	YAHOO.macaw.NEH.metadataFields = [
		{ id: 'neh_type_i', display_name: 'Type', type: 'checkbox'},
		{ id: 'neh_type_d', display_name: 'Type', type: 'checkbox'},
		{ id: 'neh_type_m', display_name: 'Type', type: 'checkbox'},
		{ id: 'neh_type_p', display_name: 'Type', type: 'checkbox'},
		{ id: 'neh_type_l', display_name: 'Type', type: 'checkbox'},
		{ id: 'neh_color', display_name: 'Color', type: 'radio'},
		{ id: 'no_images', display_name: 'No Img?', type: 'checkbox'},
// 		{ id: 'pageid', display_name: 'pageid', type: 'hidden'},
//		{ id: 'abbyy_hasillustration', display_name: 'ABBYY', type: 'hidden'},
//		{ id: 'contrast_hasillustration', display_name: 'Contrast', type: 'hidden'},
//		{ id: 'pixel_depth', display_name: 'pixel_depth', type: 'hidden'},
//		{ id: 'percent_coverage', display_name: 'percent_coverage', type: 'hidden'}
// 		{ id: 'illustrations', display_name: 'illustrations', type: 'hidden'}
	];

	/* ----------------------------
	 * Function: getTableColumns()
	 *
	 * Returns a description of the data columns used for setting up a YUI data table.
	 * This does not return any data. This may also create functions internally to the
	 * method which can be referred to in the array of objects returned. See the YUI
	 * Data Table documentation on waht this should return.
	 *
	 * EXPERT NOTE: Generally this won't need to be modified unless you have special formatting
	 * you want to apply to the info in the data table.
	 *
	 * Arguments
	 *     None
	 *
	 * Return Value / Effect
	 *     An array of object suitable for YUI Data Table ColumnDefs
	---------------------------- */ 
	this.getTableColumns = function() {
		var cols = [];
		var fields = YAHOO.macaw.NEH.metadataFields;
		for (f in fields) {
			cols.push({
				'key':   fields[f].id,
				'label': fields[f].display_name
			});
		}
		return cols;
	}

	/* ----------------------------
	 * Function: init()
	 *
	 * Massage and create the data structures necessary to make this thing work.
	 * Generally this is a simple task but this particular module has a
	 * couple of special fields that are arrays and must be handled more carefully.
	 *
	 * Arguments
	 *     None
	 *
	 * Return Value / Effect
	 *     A reduced object of the data in the original Page object.
	---------------------------- */ 
	this.init = function() {
		// Copy the data from the data object into ourself.
		var fields = YAHOO.macaw.NEH.metadataFields;
		for (f in fields) {
			if (typeof this.data[fields[f].id] != 'undefined') {
				this[fields[f].id] = this.data[fields[f].id];
			} else {
				this[fields[f].id] = null;
			}
		}
		this.sequence = this.data.sequence_number;
		this.filebase = this.data.filebase;

		// This is really special. We DO NOT want to call this more than once.
		// So we set our own variable onto the oBook because we can
		// (and which is more or less global, as far as we are concerned)
// 		if (!oBook.initialized_NEH) {
// 				var keyIncrement = 10;
// 				var tickSize = 20;
//         YAHOO.macaw.NEH.sizeSlider = YAHOO.widget.Slider.getHorizSlider('slider-bg', 'slider-thumb', 0, 100, 10);
//         YAHOO.macaw.NEH.sizeSlider.animate = false;
//         YAHOO.macaw.NEH.sizeSlider.subscribe("change", function(offset) {
//         	YAHOO.macaw.NEH.resetFilter();
//         });
// 		}
	}

	/* ----------------------------
	 * Function: getData()
	 *
	 * Get the data for this page. We return an object (associative array) of data. The elements
	 * in the object may include simple arrays of data. Since this data is going to be saved in
	 * the database, we can handle these simple arrays, but more complex objects will produce
	 * unexpected results.
	 *
	 * Arguments
	 *     None
	 *
	 * Return Value / Effect
	 *     An object
	---------------------------- */ 
	this.getData = function() {
		var data = {};

		// Create a new object with only the data that we want to save
		// We COULD send in the entire "this" object, but that's messy.
		var fields = YAHOO.macaw.NEH.metadataFields;
		for (f in fields) {
			data[fields[f].id] = this[fields[f].id];
		}

		return data;
	}

	/* ----------------------------
	 * Function: getTableData
	 *
	 * Return the data of this page in a manner suitable for display in a data table. This means
	 * that there is no capability to display anything other than strings. Every item returned in this
	 * object must be a simple value or something like [object Object] will be displayed in the table.
	 *
	 * Arguments
	 *     None
	 *
	 * Return Value / Effect
	 *     A reduced object of the data in the original Page object.
	---------------------------- */ 
	this.getTableData = function() {
		// This is almost the same as getData. If any field is something other than
		// a text field, you may want to handle it differently. The data returned here
		// will be converted to a string.
		var data = {};

		// Create a new object with only the data that we want to save
		// We COULD send in the entire "this" object, but that's messy.
		var fields = YAHOO.macaw.NEH.metadataFields;
		for (f in fields) {
			if (fields[f].type == 'long-text') {
				if (this[fields[f].id] != '' && this[fields[f].id] != null) {
					data[fields[f].id] = '<em>(text)</em>';
				}
			} else {
				data[fields[f].id] = this[fields[f].id];
			}
		}

		return data;
	}

	/* ----------------------------
	 * Function: render()
	 *
	 * Fill the metadata fields with the data from the page. The "this" object
	 * is the currently selected page.
	 *
	 * Arguments
	 *     None
	 *
	 * Return Value / Effect
	 *     The fields are filled in. Or not. Depends on the data.
	---------------------------- */ 
	this.set = function(field, value, mult) {
		// When we are saving multiple records at once, we DO NOT set anything if the field is empty.
//		if (!mult || (mult && !isBlank(value))) {
			this[field] = value;
//		}
	}

	/* ----------------------------
	 * Function: render()
	 *
	 * Fill the metadata fields with the data from the page. The "this" object
	 * is the currently selected page.
	 *
	 * Arguments
	 *     None
	 *
	 * Return Value / Effect
	 *     The fields are filled in. Or not. Depends on the data.
	---------------------------- */ 
	this.render = function() {
		// Fill in the fields on the page.
		// This uses the YUI Dom object.
		var fields = YAHOO.macaw.NEH.metadataFields;
		for (f in fields) {
			// Text boxes and Textareas are easy
			if (fields[f].type == 'text' || fields[f].type == 'long-text') {
				Dom.get(fields[f].id).value = this[fields[f].id]

			// Select boxes are more difficult, but select-one is pretty simple.
			} else if(fields[f].type == 'select-one') {
				var el = Dom.get(fields[f].id);
				el.selectedIndex = -1;
				if (this[fields[f].id] && this[fields[f].id] != null && typeof(this[fields[f].id]) != undefined) {
					for (i=0; i < el.options.length; i++) {
						if (el.options[i].value == this[fields[f].id]) {
							el.selectedIndex = i;
							break;
						}
					}
				}

			} else if(fields[f].type == 'checkbox') {
				var el = Dom.get(fields[f].id);
				if (this[fields[f].id] && this[fields[f].id] != null && typeof(this[fields[f].id]) != undefined) {
					el.checked = true;
				} else {
					el.checked = false;
				}

			} else if(fields[f].type == 'radio') {
				if (this[fields[f].id] && this[fields[f].id] != null && typeof(this[fields[f].id]) != undefined) {
					var el = document.getElementsByName(fields[f].id);
					for (i=0; i < el.length; i++) {
						el[i].checked = false;
						if (el[i].value == this[fields[f].id]) {
							el[i].checked = true;		
						}
					}
				}
			}
		}
	}

	/* ----------------------------
	 * Function: renderMultiple()
	 *
	 * This is used when special handling of the metadata fields is needed when
	 * multiple pages are selected. This could be a class method, but it's not.
	 *
	 * Arguments
	 *     None
	 *
	 * Return Value / Effect
	 *     The fields are filled in. Or not. Depends on the data.
	---------------------------- */ 
	this.renderMultiple = function() {
		// For this demo, this can be left empty (but the function definition is required)
// 		var fields = YAHOO.macaw.NEH.metadataFields;
// 		for (f in fields) {
// 			// Text boxes and Textareas are easy
// 			if(fields[f].type == 'checkbox') {
// 				var el = Dom.get(fields[f].id);
// 
// 				var all_selected = true;
// 				var pg = oBook.pages.arrayHighlighted();
// 				for (var i in pg) {
// 					
// 				}
// 
// 				if (this[fields[f].id] && this[fields[f].id] != null && typeof(this[fields[f].id]) != undefined) {
// 					el.checked = true;
// 				} else {
// 					el.checked = false;
// 				}
// 
// 			} else if(fields[f].type == 'radio') {
// 				if (this[fields[f].id] && this[fields[f].id] != null && typeof(this[fields[f].id]) != undefined) {
// 					var el = document.getElementsByName(fields[f].id);
// 					for (i=0; i < el.length; i++) {
// 						el[i].checked = false;
// 						if (el[i].value == this[fields[f].id]) {
// 							el[i].checked = true;		
// 						}
// 					}
// 				}
// 			}
// 		}

	}

	/* ----------------------------
	 * Function: unrender()
	 *
	 * Empties out the metadata fields since it's called before calling renderMultiple()
	 * and when no pages are seleted. This could be a class method, but it's not.
	 *
	 * Arguments
	 *     None
	 *
	 * Return Value / Effect
	 *     The fields are empty or otherwise initialized
	---------------------------- */ 
	this.unrender = function() {
		// Fill in the fields on the page.
		// This uses the YUI Dom object.
		var fields = YAHOO.macaw.NEH.metadataFields;
		for (f in fields) {
			if (fields[f].type == 'text' || fields[f].type == 'long-text') {
				Dom.get(fields[f].id).value = '';

			} else if(fields[f].type == 'select-one') {
				Dom.get(fields[f].id).selectedIndex = -1;

			} else if(fields[f].type == 'checkbox') {
				Dom.get(fields[f].id).checked = false;

			} else if(fields[f].type == 'radio') {
				var el = document.getElementsByName(fields[f].id);
				for (i=0; i < el.length; i++) {
					el[i].checked = false;
				}
			}
		}
	}

	this.find = function(fld, val) {
		if (typeof val == 'string') {
			if (this[fld] == val) {
				return 1;
			}
		} else if (typeof val == 'object') {
			for (var i = 0; i < val.length; i++) {
				if (val[i] == this[fld]) {
					return 1;
				}
			}
		}
		return 0;
	}
	
	this.keyPress = function(key) {
		var obj = {};
		
		obj.id = '';

		if (key == 'i' || key == 'I') { obj.value = 'Illustration'; obj.name = 'neh_type_i'; }
		if (key == 'd' || key == 'D') { obj.value = 'Diagram/Chart'; obj.name = 'neh_type_d'; }
		if (key == 'm' || key == 'M') { obj.value = 'Map'; obj.name = 'neh_type_m'; }
		if (key == 'p' || key == 'P') { obj.value = 'Photograph'; obj.name = 'neh_type_p'; }
		if (key == 'l' || key == 'L') { obj.value = 'Bookplate'; obj.name = 'neh_type_l'; }
	
		if (key == 'c' || key == 'C') { obj.value = 'Color'; obj.name = 'neh_color'; }
		if (key == 'b' || key == 'B') { obj.value = 'Black/White'; obj.name = 'neh_color'; }

	
		var pg = oBook.pages.arrayHighlighted();
		
		if (obj.value && pg.length > 0) {
			// Check the appropriate radio button or Checkboxk
			var el = document.getElementsByName(obj.name);
			var id = obj.id||obj.name;
			for (i=0; i < el.length; i++) {
				if (el[i].value == obj.value) {
					if (el[i].checked) {
						el[i].checked = false;
						obj.checked = false;
					} else {
						el[i].checked = true;
						obj.checked = true;
					}
				}
			}

			YAHOO.macaw.NEH.metadataChange(obj);
		}
	}
	
}

/* ****************************************** */
/* CLASS MEHTODS                              */
/* These exist apart from individual pages    */
/* ****************************************** */

/* ----------------------------
 * Function: metadataChange()
 *
 * Called when any of the metadata elements are changed in the form. Because this is called from
 * the metadata form AND because one or more pages may be selected, this function when called does
 * not know which pages are selected.
 * objects.
 *
 * Arguments
 *    obj - The object that triggered the change event.
 *
 * Return Value / Effect
 *    The data is set into the proper page object(s)
 *
 ---------------------------- */
YAHOO.macaw.NEH.metadataChange = function(obj) {
	// Get the things that are selected
	var pg = oBook.pages.arrayHighlighted();
	// All of these REPLACE the data that's on the object.
	// Only the Page Type and Pieces will APPEND.
	var i;
	// Set an array to accumulate any pageids we modify
	var page_ids = new Array();
	var multiple = (pg.length > 1);
	var fields = YAHOO.macaw.NEH.metadataFields;

	var id = obj.id||obj.name;

	var save_val = null;
	for (var i in pg) {
		for (f in fields) {
			if (id == fields[f].id) {
				if(fields[f].type == 'checkbox' || fields[f].type == 'radio') {
					if (obj.checked) {
						pg[i].metadata.callFunction('set', fields[f].id, obj.value, multiple);
						save_val = obj.value;
					} else {
						pg[i].metadata.callFunction('set', fields[f].id, null, multiple);
					}
				} else {
					pg[i].metadata.callFunction('set', fields[f].id, obj.value, multiple);
					save_val = obj.value;				
				}
				// This works for both text boxes and textareas
			}
		}
		// Collect the pageids we modify
		page_ids.push(pg[i].pageID);
	}

	// TODO: BIG NOTE. This is terribly convoluted. I think we could eliminate the convoluted-ness of
	// this IF the metadata object's "metadataModules" array was changed into an object. Then we could
	// simply call
	//
	//     pg[i].metadata.modules.SAMPLE.set(fields[f].id, obj.value);
	//
	// We CANNOT do this:  this.set(fields[f].id, obj.value)   (The "this" object refers to something else entirely)
	// We CANNOT do this:  this[fields[f].id] = obj.value      (The "this" object refers to something else entirely)
	// We CANNOT do this:  pg[i].set(fields[f].id, obj.value)

	// Log all the pages that were modified at once to not spam the server
	if (id != 'metadata_form') {
		if (!multiple || (multiple && obj.value)) {
			Scanning.log(page_ids.join('|'), id, save_val);
		}
	}
	
	oBook._updateDataTableRecordset();
}

YAHOO.macaw.NEH.filterPages = function() {
	if (this.nofilter) {
		return;
	}
	values = new Array();
	if (Dom.get('page_type_1').checked) {
		values.push(Dom.get('page_type_1').value);
	}
	if (Dom.get('page_type_2').checked) {
		values.push(Dom.get('page_type_2').value);
	}
	if (Dom.get('page_type_3').checked) {
		values.push(Dom.get('page_type_3').value);
	}
	if (Dom.get('page_type_4').checked) {
		values.push(Dom.get('page_type_4').value);
	}
	if (Dom.get('page_type_5').checked) {
		values.push(Dom.get('page_type_5').value);
	}
	
	if (values.length > 0) {
		// Hide non-illustration pages
		// Get the values we want to find
		oBook.pages.filter('neh_type', values);
	} else {
		// Show all pages
		oBook.pages.filter(null, null);
	}
	
	sortval = null;
	if (Dom.get('sort_1').checked) {
		sortval = Dom.get('sort_1').value;
	}
	if (Dom.get('sort_2').checked) {
		sortval = Dom.get('sort_2').value;
	}
	if (Dom.get('sort_3').checked) {
		sortval = Dom.get('sort_3').value;
	}
	if (sortval != this.currentSort) {
		oBook.pages.sort(sortval);
	}
}

YAHOO.macaw.NEH.resetFilter = function() {

	Dom.get('size-val').innerHTML = (YAHOO.macaw.NEH.sizeSlider.getValue()) + '%';
	
	this.nofilter = true;
	Dom.get('sort_1').checked = true;
	Dom.get('page_type_1').checked = false;
	Dom.get('page_type_2').checked = false;
	Dom.get('page_type_3').checked = false;
	Dom.get('page_type_4').checked = false;
	Dom.get('page_type_5').checked = false;
	Dom.get('page_type_1').checked = false;
	oBook.pages.filter(null, null);
	this.currentSort = 'natural';
	this.nofilter = false;
}

