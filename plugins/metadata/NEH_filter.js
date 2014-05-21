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

YAHOO.macaw.NEH_filter = function(parent, data) {

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
	YAHOO.macaw.NEH_filter.metadataFields = [
		{ id: 'neh_type_i', display_name: 'Painting/Drawing/Diagram', type: 'checkbox'},
		{ id: 'neh_type_d', display_name: 'Chart/Table', type: 'checkbox'},
		{ id: 'neh_type_m', display_name: 'Map', type: 'checkbox'},
		{ id: 'neh_type_p', display_name: 'Photograph', type: 'checkbox'},
		{ id: 'neh_type_l', display_name: 'Bookplate', type: 'checkbox'},
		{ id: 'neh_color', display_name: 'Color', type: 'radio'},
		{ id: 'no_images', display_name: 'Images?', type: 'checkbox'},
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
		var fields = YAHOO.macaw.NEH_filter.metadataFields;
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
		var fields = YAHOO.macaw.NEH_filter.metadataFields;
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
		if (!oBook.initialized_NEH) {
			var keyIncrement = 10;
			var tickSize = 20;
			YAHOO.macaw.NEH_filter.sizeSlider = YAHOO.widget.Slider.getHorizSlider('slider-bg', 'slider-thumb', 0, 100, 10);
			YAHOO.macaw.NEH_filter.sizeSlider.animate = false;
			YAHOO.macaw.NEH_filter.sizeSlider.subscribe("change", function(offset) {
				YAHOO.macaw.NEH_filter.resetFilter();
			});
			
			Dom.get('metadata_overlay').style.left = '-10000px';
	
			var obtnNext = new YAHOO.widget.Button("btnNextPage");
			obtnNext.on("click", YAHOO.macaw.NEH_filter.pageNext);
	
			var obtnPrev = new YAHOO.widget.Button("btnPrevPage");
			obtnPrev.on("click", YAHOO.macaw.NEH_filter.pagePrev, -1);
			oBook.initialized_NEH = true;
			
			Dom.get('save_buttons').style.display = 'none';
			Dom.get('metadata-title').innerHTML = "Admin Tools";
			Dom.get('metadata-title').style.color = '#4267f0';
		}	
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
		var fields = YAHOO.macaw.NEH_filter.metadataFields;
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
		var fields = YAHOO.macaw.NEH_filter.metadataFields;
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


	this.getFriendlyData = function() {
		// This is almost the same as getData. If any field is something other than
		// a text field, you may want to handle it differently. The data returned here
		// will be converted to a string.
		var data = '';

		// Create a new object with only the data that we want to save
		// We COULD send in the entire "this" object, but that's messy.
		var fields = YAHOO.macaw.NEH_filter.metadataFields;
		for (f in fields) {
			if (fields[f].type != 'long-text') {
				if (this[fields[f].id] != null) {
					data = data + '<strong>' + fields[f].display_name + '</strong>: ' + this[fields[f].id] + '<br>';
				}
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
		if (!mult || (mult && !isBlank(value))) {
			this[field] = value;
		}
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
// 		var fields = YAHOO.macaw.NEH_filter.metadataFields;
// 		for (f in fields) {
// 			// Text boxes and Textareas are easy
// 			if (fields[f].type == 'text' || fields[f].type == 'long-text') {
// 				Dom.get(fields[f].id).value = this[fields[f].id]
// 
// 			// Select boxes are more difficult, but select-one is pretty simple.
// 			} else if(fields[f].type == 'select-one') {
// 				var el = document.getElementById(fields[f].id);
// 				el.selectedIndex = -1;
// 				if (this[fields[f].id] && this[fields[f].id] != null && typeof(this[fields[f].id]) != undefined) {
// 					for (i=0; i < el.options.length; i++) {
// 						if (el.options[i].value == this[fields[f].id]) {
// 							el.selectedIndex = i;
// 							break;
// 						}
// 					}
// 				}
// 			}
// 		}
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
// 		var fields = YAHOO.macaw.NEH_filter.metadataFields;
// 		for (f in fields) {
// 			if (fields[f].type == 'text' || fields[f].type == 'long-text') {
// 				Dom.get(fields[f].id).value = '';
// 			} else if(fields[f].type == 'select-one') {
// 				Dom.get(fields[f].id).selectedIndex = -1;
// 			}
// 		}
	}

	this.find = function(fld, val) {
// 		if (typeof val == 'string') {
// 			if (this[fld] == val) {
// 				return 1;
// 			}
// 		} else if (typeof val == 'object') {
// 			for (var i = 0; i < val.length; i++) {
// 				if (val[i] == this[fld]) {
// 					return 1;
// 				}
// 			}
// 		}
// 		return 0;
	}
	
}

/* ****************************************** */
/* CLASS MEHTODS                              */
/* These exist apart from individual pages    */
/* ****************************************** */
YAHOO.macaw.NEH_filter.pageNext = function() {
	YAHOO.macaw.NEH_filter.pageChange(1);
}

YAHOO.macaw.NEH_filter.pagePrev = function() {
	YAHOO.macaw.NEH_filter.pageChange(-1);
}

YAHOO.macaw.NEH_filter.pageChange = function(inc) {
	if (!oBook.currentDisplayPage) {
		oBook.currentDisplayPage = 1;
	}

	oBook.currentDisplayPage = oBook.currentDisplayPage + inc;
	if (oBook.currentDisplayPage <= 0) {
		oBook.currentDisplayPage = 1;
	}
	Dom.get('current-page').innerHTML = "Current Page "+oBook.currentDisplayPage;
	YAHOO.macaw.NEH_filter.filterPages();
}


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
YAHOO.macaw.NEH_filter.metadataChange = function(obj) {
}

YAHOO.macaw.NEH_filter.filterPages = function() {
	var loadDataCallback = {
		success: function (o){
			oBook.showSpinner(false);
			eval('var r = '+o.responseText.replace(/&amp;/g, '&'));
			if (r.redirect) {
				window.location = r.redirect;
			} else {
				if (r.error) {
					General.showErrorMessage(r.error);
				} else {
					oBook.unrender('thumbs','list');
					oBook.pages = null;
					oBook.pages = new YAHOO.macaw.Pages(oBook, r.pages, Scanning.metadataModules);
					oBook.pages.load();
					oBook.render('thumbs','list');
					oBook.pages.scroll();
				}
			}
		},
		failure: function (o){
			oBook.showSpinner(false);
			General.showErrorMessage('There was a problem retrieving the metadata for the pages. Please try reloaing the page. If it helps, the error was:<blockquote style="font-weight:bold;color:#990000;">'+o.statusText+"</blockquote>");
		},
		scope: this
	};

	
	if (this.nofilter) {
		return;
	}
	values = '';
	if (Dom.get('page_type_1').checked) {
		values += '/filter/neh_type_i='+Dom.get('page_type_1').value
	}
	if (Dom.get('page_type_2').checked) {
		values += '/filter/neh_type_d='+Dom.get('page_type_2').value
	}
	if (Dom.get('page_type_3').checked) {
		values += '/filter/neh_type_m='+Dom.get('page_type_3').value
	}
	if (Dom.get('page_type_4').checked) {
		values += '/filter/neh_type_p='+Dom.get('page_type_4').value
	}
	if (Dom.get('page_type_5').checked) {
		values += '/filter/neh_type_l='+Dom.get('page_type_5').value
	}

	if (Dom.get('neh_color_c').checked) {
		values += '/filter/neh_color='+Dom.get('neh_color_c').value
	}
	if (Dom.get('neh_color_bw').checked) {
		values += '/filter/neh_color='+Dom.get('neh_color_bw').value
	}

	if (Dom.get('no_images').checked) {
		values += '/filter/no_images=none'
	}

	values += '/sort/'+Dom.get('sort').value;
	values += '/user/'+Dom.get('user').value;
	values += '/perpage/'+Dom.get('perpage').value;
	
	if (oBook.currentDisplayPage) {
		values += '/page/'+oBook.currentDisplayPage;
	}
	// Call the URL to get the data
	// alert(sBaseUrl+'/scan/get_all_thumbnails'+values);
	oBook.showSpinner(true);
//	alert(sBaseUrl+'/scan/get_all_thumbnails'+values);
	var transaction = YAHOO.util.Connect.asyncRequest('GET', sBaseUrl+'/scan/get_all_thumbnails'+values, loadDataCallback, null);
}

YAHOO.macaw.NEH_filter.resetFilter = function() {

	Dom.get('size-val').innerHTML = (YAHOO.macaw.NEH_filter.sizeSlider.getValue()) + '%';
	
	this.nofilter = true;
	Dom.get('sort').selectedIndex = 0;
	Dom.get('page_type_1').checked = false;
	Dom.get('page_type_2').checked = false;
	Dom.get('page_type_3').checked = false;
	Dom.get('page_type_4').checked = false;
	Dom.get('page_type_5').checked = false;
	Dom.get('page_type_1').checked = false;
	oBook.pages.filter(null, null);
	this.currentSort = 'natural';
	this.nofilter = false;
	oBook.pages.scroll();
	return false;
}
