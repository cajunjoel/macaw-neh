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


/* SPINNER HANDLER */

/**
 * Copyright (c) 2011-2013 Felix Gnass
 * Licensed under the MIT license
 */
(function(root, factory) {

  /* CommonJS */
  if (typeof exports == 'object')  module.exports = factory()

  /* AMD module */
  else if (typeof define == 'function' && define.amd) define(factory)

  /* Browser global */
  else root.Spinner = factory()
}
(this, function() {
  "use strict";

  var prefixes = ['webkit', 'Moz', 'ms', 'O'] /* Vendor prefixes */
    , animations = {} /* Animation rules keyed by their name */
    , useCssAnimations /* Whether to use CSS animations or setTimeout */

  /**
   * Utility function to create elements. If no tag name is given,
   * a DIV is created. Optionally properties can be passed.
   */
  function createEl(tag, prop) {
    var el = document.createElement(tag || 'div')
      , n

    for(n in prop) el[n] = prop[n]
    return el
  }

  /**
   * Appends children and returns the parent.
   */
  function ins(parent /* child1, child2, ...*/) {
    for (var i=1, n=arguments.length; i<n; i++)
      parent.appendChild(arguments[i])

    return parent
  }

  /**
   * Insert a new stylesheet to hold the @keyframe or VML rules.
   */
  var sheet = (function() {
    var el = createEl('style', {type : 'text/css'})
    ins(document.getElementsByTagName('head')[0], el)
    return el.sheet || el.styleSheet
  }())

  /**
   * Creates an opacity keyframe animation rule and returns its name.
   * Since most mobile Webkits have timing issues with animation-delay,
   * we create separate rules for each line/segment.
   */
  function addAnimation(alpha, trail, i, lines) {
    var name = ['opacity', trail, ~~(alpha*100), i, lines].join('-')
      , start = 0.01 + i/lines * 100
      , z = Math.max(1 - (1-alpha) / trail * (100-start), alpha)
      , prefix = useCssAnimations.substring(0, useCssAnimations.indexOf('Animation')).toLowerCase()
      , pre = prefix && '-' + prefix + '-' || ''

    if (!animations[name]) {
      sheet.insertRule(
        '@' + pre + 'keyframes ' + name + '{' +
        '0%{opacity:' + z + '}' +
        start + '%{opacity:' + alpha + '}' +
        (start+0.01) + '%{opacity:1}' +
        (start+trail) % 100 + '%{opacity:' + alpha + '}' +
        '100%{opacity:' + z + '}' +
        '}', sheet.cssRules.length)

      animations[name] = 1
    }

    return name
  }

  /**
   * Tries various vendor prefixes and returns the first supported property.
   */
  function vendor(el, prop) {
    var s = el.style
      , pp
      , i

    prop = prop.charAt(0).toUpperCase() + prop.slice(1)
    for(i=0; i<prefixes.length; i++) {
      pp = prefixes[i]+prop
      if(s[pp] !== undefined) return pp
    }
    if(s[prop] !== undefined) return prop
  }

  /**
   * Sets multiple style properties at once.
   */
  function css(el, prop) {
    for (var n in prop)
      el.style[vendor(el, n)||n] = prop[n]

    return el
  }

  /**
   * Fills in default values.
   */
  function merge(obj) {
    for (var i=1; i < arguments.length; i++) {
      var def = arguments[i]
      for (var n in def)
        if (obj[n] === undefined) obj[n] = def[n]
    }
    return obj
  }

  /**
   * Returns the absolute page-offset of the given element.
   */
  function pos(el) {
    var o = { x:el.offsetLeft, y:el.offsetTop }
    while((el = el.offsetParent))
      o.x+=el.offsetLeft, o.y+=el.offsetTop

    return o
  }

  /**
   * Returns the line color from the given string or array.
   */
  function getColor(color, idx) {
    return typeof color == 'string' ? color : color[idx % color.length]
  }

  // Built-in defaults

  var defaults = {
    lines: 12,            // The number of lines to draw
    length: 7,            // The length of each line
    width: 5,             // The line thickness
    radius: 10,           // The radius of the inner circle
    rotate: 0,            // Rotation offset
    corners: 1,           // Roundness (0..1)
    color: '#000',        // #rgb or #rrggbb
    direction: 1,         // 1: clockwise, -1: counterclockwise
    speed: 1,             // Rounds per second
    trail: 100,           // Afterglow percentage
    opacity: 1/4,         // Opacity of the lines
    fps: 20,              // Frames per second when using setTimeout()
    zIndex: 2e9,          // Use a high z-index by default
    className: 'spinner', // CSS class to assign to the element
    top: 'auto',          // center vertically
    left: 'auto',         // center horizontally
    position: 'relative'  // element position
  }

  /** The constructor */
  function Spinner(o) {
    if (typeof this == 'undefined') return new Spinner(o)
    this.opts = merge(o || {}, Spinner.defaults, defaults)
  }

  // Global defaults that override the built-ins:
  Spinner.defaults = {}

  merge(Spinner.prototype, {

    /**
     * Adds the spinner to the given target element. If this instance is already
     * spinning, it is automatically removed from its previous target b calling
     * stop() internally.
     */
    spin: function(target) {
      this.stop()

      var self = this
        , o = self.opts
        , el = self.el = css(createEl(0, {className: o.className}), {position: o.position, width: 0, zIndex: o.zIndex})
        , mid = o.radius+o.length+o.width
        , ep // element position
        , tp // target position

      if (target) {
        target.insertBefore(el, target.firstChild||null)
        tp = pos(target)
        ep = pos(el)
        css(el, {
          left: (o.left == 'auto' ? tp.x-ep.x + (target.offsetWidth >> 1) : parseInt(o.left, 10) + mid) + 'px',
          top: (o.top == 'auto' ? tp.y-ep.y + (target.offsetHeight >> 1) : parseInt(o.top, 10) + mid)  + 'px'
        })
      }

      el.setAttribute('role', 'progressbar')
      self.lines(el, self.opts)

      if (!useCssAnimations) {
        // No CSS animation support, use setTimeout() instead
        var i = 0
          , start = (o.lines - 1) * (1 - o.direction) / 2
          , alpha
          , fps = o.fps
          , f = fps/o.speed
          , ostep = (1-o.opacity) / (f*o.trail / 100)
          , astep = f/o.lines

        ;(function anim() {
          i++;
          for (var j = 0; j < o.lines; j++) {
            alpha = Math.max(1 - (i + (o.lines - j) * astep) % f * ostep, o.opacity)

            self.opacity(el, j * o.direction + start, alpha, o)
          }
          self.timeout = self.el && setTimeout(anim, ~~(1000/fps))
        })()
      }
      return self
    },

    /**
     * Stops and removes the Spinner.
     */
    stop: function() {
      var el = this.el
      if (el) {
        clearTimeout(this.timeout)
        if (el.parentNode) el.parentNode.removeChild(el)
        this.el = undefined
      }
      return this
    },

    /**
     * Internal method that draws the individual lines. Will be overwritten
     * in VML fallback mode below.
     */
    lines: function(el, o) {
      var i = 0
        , start = (o.lines - 1) * (1 - o.direction) / 2
        , seg

      function fill(color, shadow) {
        return css(createEl(), {
          position: 'absolute',
          width: (o.length+o.width) + 'px',
          height: o.width + 'px',
          background: color,
          boxShadow: shadow,
          transformOrigin: 'left',
          transform: 'rotate(' + ~~(360/o.lines*i+o.rotate) + 'deg) translate(' + o.radius+'px' +',0)',
          borderRadius: (o.corners * o.width>>1) + 'px'
        })
      }

      for (; i < o.lines; i++) {
        seg = css(createEl(), {
          position: 'absolute',
          top: 1+~(o.width/2) + 'px',
          transform: o.hwaccel ? 'translate3d(0,0,0)' : '',
          opacity: o.opacity,
          animation: useCssAnimations && addAnimation(o.opacity, o.trail, start + i * o.direction, o.lines) + ' ' + 1/o.speed + 's linear infinite'
        })

        if (o.shadow) ins(seg, css(fill('#000', '0 0 4px ' + '#000'), {top: 2+'px'}))
        ins(el, ins(seg, fill(getColor(o.color, i), '0 0 1px rgba(0,0,0,.1)')))
      }
      return el
    },

    /**
     * Internal method that adjusts the opacity of a single line.
     * Will be overwritten in VML fallback mode below.
     */
    opacity: function(el, i, val) {
      if (i < el.childNodes.length) el.childNodes[i].style.opacity = val
    }

  })


  function initVML() {

    /* Utility function to create a VML tag */
    function vml(tag, attr) {
      return createEl('<' + tag + ' xmlns="urn:schemas-microsoft.com:vml" class="spin-vml">', attr)
    }

    // No CSS transforms but VML support, add a CSS rule for VML elements:
    sheet.addRule('.spin-vml', 'behavior:url(#default#VML)')

    Spinner.prototype.lines = function(el, o) {
      var r = o.length+o.width
        , s = 2*r

      function grp() {
        return css(
          vml('group', {
            coordsize: s + ' ' + s,
            coordorigin: -r + ' ' + -r
          }),
          { width: s, height: s }
        )
      }

      var margin = -(o.width+o.length)*2 + 'px'
        , g = css(grp(), {position: 'absolute', top: margin, left: margin})
        , i

      function seg(i, dx, filter) {
        ins(g,
          ins(css(grp(), {rotation: 360 / o.lines * i + 'deg', left: ~~dx}),
            ins(css(vml('roundrect', {arcsize: o.corners}), {
                width: r,
                height: o.width,
                left: o.radius,
                top: -o.width>>1,
                filter: filter
              }),
              vml('fill', {color: getColor(o.color, i), opacity: o.opacity}),
              vml('stroke', {opacity: 0}) // transparent stroke to fix color bleeding upon opacity change
            )
          )
        )
      }

      if (o.shadow)
        for (i = 1; i <= o.lines; i++)
          seg(i, -2, 'progid:DXImageTransform.Microsoft.Blur(pixelradius=2,makeshadow=1,shadowopacity=.3)')

      for (i = 1; i <= o.lines; i++) seg(i)
      return ins(el, g)
    }

    Spinner.prototype.opacity = function(el, i, val, o) {
      var c = el.firstChild
      o = o.shadow && o.lines || 0
      if (c && i+o < c.childNodes.length) {
        c = c.childNodes[i+o]; c = c && c.firstChild; c = c && c.firstChild
        if (c) c.opacity = val
      }
    }
  }

  var probe = css(createEl('group'), {behavior: 'url(#default#VML)'})

  if (!vendor(probe, 'transform') && probe.adj) initVML()
  else useCssAnimations = vendor(probe, 'animation')

  return Spinner

}));