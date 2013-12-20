// ----------------------------
// MACAW - Metadata Collection and Workflow
//
// Revision History
//     2010/08/06 JMR - Created, initial coding completed.
// ----------------------------
YAHOO.namespace("macaw"); // This is required in all JS files for some reason
YAHOO.widget.Chart.SWFURL = sBaseUrl+"/inc/swf/charts.swf";

// Some variables that come in handy for not beating up the server

// Image Cache of commonly referenced images or icons that change a lot
var imgSpacer = new Image;
imgSpacer.src = sBaseUrl+'/images/spacer.gif';
var imgMultiSelect = new Image;
imgMultiSelect.src = sBaseUrl+'/images/multiselect.png';
var imgToggleRight = new Image;
imgToggleRight.src = sBaseUrl+'/images/icons/resultset_next.png';
var imgToggleLeft = new Image;
imgToggleLeft.src = sBaseUrl+'/images/icons/resultset_previous.png';
var imgClear = new Image;
imgClear.src = sBaseUrl+'/images/icons/page_white_delete_grey.png';
var imgClearOver = new Image;
imgClearOver.src = sBaseUrl+'/images/icons/page_white_delete.png';

// Make some abbreviations that just make our life easier
var Dom = YAHOO.util.Dom;
var Elem = YAHOO.util.Element;
var Event = YAHOO.util.Event;
var DDM = YAHOO.util.DragDropMgr;
var JSON = YAHOO.lang.JSON;
var Lang = YAHOO.lang;

Event.throwErrors = true;

// Our main object that describes the book we are currently editing
var oBook;

// Library objects used in various places
var Barcode;
var Scanning;
var timeoutAutoSave;

// ----------------------------
// Extend the array object to add a "array.map()" method for more flexible
// array handling activities
// ----------------------------
// if (!Array.prototype.map) {
// 	Array.prototype.map = function(fun /*, thisp*/) {
// 		var len = this.length;
// 		if (typeof fun != "function") {
// 			throw new TypeError();
// 		}
// 		var res = new Array(len);
// 		var thisp = arguments[1];
// 		for (var i = 0; i < len; i++) {
// 			if (i in this) {
// 				res[i] = fun.call(thisp, this[i], i, this);
// 			}
// 		}
//
// 		return res;
// 	};
//
// }

// ----------------------------
// Extend the array object to add a "array.inArray()" method
// to find something in the array.
// ----------------------------
// if (!Array.prototype.inArray) {
// 	Array.prototype.inArray = function(txt) {
// 		var len = this.length;
// 		while (i--) {
// 			if (this[i] == txt) {
// 				return true
// 			}
// 		}
// 		return false;
// 	};
// }

// ----------------------------
// Focus Tracking
//
// When handling keypress for setting page types, we need to know if the focus
// is on something on the page. So we have a function to set and clear a variable
// that tells us which object currently has the focus.
// ----------------------------

var focusObject = null;

function focusOn(o) {
	if (o) {
		focusObject = o.id;
		document.getElementById('foobar').value = focusObject;
	}
}

function focusOff() {
	focusObject = null;
	document.getElementById('foobar').value = focusObject;
}

// ----------------------------
// KeyPress handling
//
// We need to know when shift or ctrl are pressed. So we monitor the keypresses
// and set or unset our boolean variable when that happens. Then we just need to
// check those booleans when handling click, ctrl-click or shift-click events.
// ----------------------------
var keyCtrl = false;
var keyShift = false;
var keyAlt = false;

// ----------------------------
// Function: checkKey()
//
// Event handler - When a key is pressed, we see if it's one we are interested in
// (shift, alt, ctrl) and set flags based on the value. Presumably we can do this
// for arrow keys, too.
//
// Arguments
//    e - the Event that triggered the action
//
// Return Value / Effect
//    Variables keyCtrl, keyShift and/or keyAlt are set appropriately
// ----------------------------
function checkKey(e) {
	// returns true if the Ctrl key was pressed with the last key
	function isCtrl(e) {
		if (window.event) {
			return (window.event.ctrlKey);
		} else {
			return (e.ctrlKey || (e.modifiers==2) || (e.modifiers==3) || (e.modifiers>5));
		}
		return false;
	}

	// returns true if the Alt key was pressed with the last key
	function isAlt(e) {
		if (window.event) {
			return (window.event.altKey);
		} else {
				return (e.altKey || (e.modifiers % 2));
		}
		return false;
	}

	// returns true if the Shift key was pressed with the last key
	function isShift(e) {
		if (window.event) {
			return (window.event.shiftKey);
		} else {
			return (e.shiftKey || (e.modifiers>3));
		}
		return false;
	}

	// Pass control to NEH keystroke handling
	if (oBook) {
		oBook.keyPress(e);
	}
	
	keyAlt = isAlt(e);
	keyCtrl = isCtrl(e);
	keyShift = isShift(e);
}

if (document.layers) {
	document.captureEvents(Event.KEYDOWN);
	document.captureEvents(Event.KEYPRESS);
	document.captureEvents(Event.KEYUP);
}

document.onkeydown = checkKey;
document.onkeypress = checkKey;
document.onkeyup = checkKey;
// ----------------------------
// (end KeyPress handling)
// ----------------------------



// ----------------------------
// Function: isblank()
//
// Tell us whether a field is "blank" or not. That is, is it zero length,
// equal to empty string or is null. All of these are "blank". Did I forget
// antyhing?
//
// Arguments
//    val - The string to check
//
// Return Value / Effect
//    True or false
// ----------------------------
function isBlank(val) {
	if (val == null) { return true; }
	val = String(val);
	for (var i=0;i<val.length;i++) {
		if ((val.charAt(i) != ' ') && (val.charAt(i) != "\t") && (val.charAt(i) != "\n") && (val.charAt(i) != "\r")) {
			return false;
		}
	}
	return true;
}

// ----------------------------
// Function: getKeyCode()
//
// Definitively get the keycode for the key being pressed. Handles browser wierdness.
//
// Arguments
//    e - The event object, we pass in whatever we think we have elsewhere
//
// Return Value / Effect
//    The ASCII Key code
// ----------------------------
function getKeyCode(e) {
	var evt = e || window.event || event;
	var code = evt.which || evt.keyCode || event.charCode;
	return code;
}

var int = function(x) {
	x = (x < 0) ? Math.ceil(x) : Math.floor(x);
	return x;
}

	MessageBox = {
		closeError: null,
		closeWarning: null,
		closeMessage: null,
		init: function() {			
			err = Dom.get('errormessage');
			if (err) {
				MessageBox.closeError = Dom.get("btnCloseError");
				YAHOO.util.Event.addListener(MessageBox.closeError, "click", MessageBox.close, 'error');				
			}
			warn = Dom.get('warning');
			if (warn) {
				MessageBox.closeWarning = new Dom.get("btnCloseWarning");
				YAHOO.util.Event.addListener(MessageBox.closeWarning, "click", MessageBox.close, 'warning');				
			}
			msg = Dom.get('message');
			if (msg) {
				MessageBox.closeMessage = new Dom.get("btnCloseMessage");
				YAHOO.util.Event.addListener(MessageBox.closeMessage, "click", MessageBox.close, 'message');				
			}
		},
		close: function(event,payload) {
			if (payload == 'error') {
				el = MessageBox.closeError;
			}
			if (payload == 'warning') {
				el = MessageBox.closeWarning;
			}
			if (payload == 'message') {
				el = MessageBox.closeMessage;
			}
			YAHOO.util.Event.removeListener(el, "click");
			el.parentElement.parentElement.removeChild(el.parentElement);
		}
	};
(function() {
	
});