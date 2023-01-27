/**
 * @file
 * This file contains common jQuery replacement methods for vanilla ones to DRY.
 *
 * Cherries by @toddmotto, @cferdinandi, @adamfschwartz, @daniellmb, Cash,
 * underscore.
 *
 * Some dup wrappers are meant to DRY with null checks aka poorman null safety.
 * The rest are convenient to avoid object instantiation ($()) and to preserve
 * old behaviors pre Blazy 2.6 till all codebase are migrated as needed.
 * A few dups are still valid for single vs. chained element loop or queries.
 *
 * @todo use Cash for better DOM queries, or any core libraries when available.
 * @todo remove unneeded dup methods once all codebase migrated.
 * @todo move more DOM methods into blazy.dom.js to make it ditchable for Cash.
 * @todo when IE gone, https://caniuse.com/dom-manip-convenience
 */

/* global define, module */
(function (_win, _doc) {

  'use strict';

  var ns = 'dblazy';
  var extend = Object.assign;
  var _aProto = Array.prototype;
  var _oProto = Object.prototype;
  var _toString = _oProto.toString;
  var _splice = _aProto.splice;
  var _some = _aProto.some;
  var _symbol = typeof Symbol !== 'undefined' && Symbol;
  var _isJq = 'jQuery' in _win;
  var _isCash = 'cash' in _win;
  var _class = 'class';
  var _add = 'add';
  var _remove = 'remove';
  var _has = 'has';
  var _get = 'get';
  var _set = 'set';
  var _width = 'width';
  var _uWidth = 'Width';
  var _clientWidth = 'client' + _uWidth;
  var _scroll = 'scroll';
  var _iterator = 'iterator';
  var _observer = 'Observer';
  var _dashAlphaRe = /-([a-z])/g;
  var _cssVariableRe = /^--/;
  var _wsRe = /[\11\12\14\15\40]+/;
  var _dataOnce = 'data-once';
  var _storage = _win.localStorage;
  var _events = {};
  // The largest integer that can be represented exactly.
  var MAX_ARRAY_INDEX = Math.pow(2, 53) - 1;

  /**
   * Object for public APIs where dBlazy stands for drupalBlazy.
   *
   * @namespace
   *
   * @return {dBlazy}
   *   Returns this instance.
   */
  var dBlazy = function () {
    function dBlazy(selector, ctx) {
      var me = this;

      me.name = ns;

      if (!selector) {
        return;
      }

      if (isMe(selector)) {
        return selector;
      }

      var els = selector;
      if (isStr(selector)) {
        els = findAll(context(ctx), selector);
        if (!els.length) {
          return;
        }
      }
      else if (isFun(selector)) {
        return me.ready(selector);
      }

      if (els.nodeType || els === _win) {
        els = [els];
      }

      var len = me.length = els.length;
      for (var i = 0; i < len; i++) {
        me[i] = els[i];
      }
    }

    dBlazy.prototype.init = function (selector, ctx) {
      var instance = new dBlazy(selector, ctx);

      if (isElm(selector)) {
        if (!selector.idblazy) {
          selector.idblazy = instance;
        }
        return selector.idblazy;
      }

      return instance;
    };

    return dBlazy;
  }();

  // Cache our prototype.
  var fn = dBlazy.prototype;
  // Alias instantiation for a shortcut like jQuery $(selector, context).
  var db = fn.init;
  db.fn = db.prototype = fn;

  fn.length = 0;

  // Ensuring a db collection gets printed as array-like in Chrome's devtools.
  fn.splice = _splice;

  // IE9 knows not this.
  if (_symbol) {
    // Ensuring a db collection is iterable.
    // @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Symbol/iterator
    fn[_symbol[_iterator]] = _aProto[_symbol[_iterator]];
  }

  /**
   * Excecutes chainable callback to avoid unnecessary loop unless required.
   *
   * @private
   *
   * @param {!Function} cb
   *   The calback function.
   *
   * @return {Object}
   *   The current dBlazy collection object.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Optional_chaining
   */
  function chain(cb) {
    var me = this;
    // Ok, this is insanely me.
    me = isMe(me) ? me : db(me);
    var ln = me.length;

    if (isFun(cb)) {
      if (!ln || ln === 1) {
        cb(me[0], 0);
      }
      else {
        me.each(cb);
      }
    }

    return me;
  }

  /**
   * Returns a `toString`-based type tester, based on underscore.js.
   *
   * @private
   *
   * @param {string} name
   *   The name to test for its type.
   *
   * @return {bool}
   *   True if name matches the _toString result.
   */
  function isTag(name) {
    var tag = '[object ' + name + ']';
    return function (obj) {
      return _toString.call(obj) === tag;
    };
  }

  /**
   * Generate a function to obtain property `key` from `obj`.
   *
   * @private
   *
   * @param {string} key
   *   The key to test in an object.
   *
   * @return {mixed}
   *   String, object, undefined.
   */
  function shallowProperty(key) {
    return function (obj) {
      return isNull(obj) ? void 0 : obj[key];
    };
  }

  /**
   * Returns true if the checked property is number.
   *
   * @private
   *
   * @param {function} cb
   *   The callback to test length property.
   *
   * @return {bool}
   *   True if argument is property is number.
   */
  function checkLength(cb) {
    return function (collection) {
      var size = cb(collection);
      return typeof size === 'number' && size >= 0 && size <= MAX_ARRAY_INDEX;
    };
  }

  // Internal helper to obtain the `length` property of an object.
  var getLength = shallowProperty('length');

  /**
   * Returns true if the argument is an array-like object, NodeList, etc.
   *
   * @private
   *
   * @return {bool}
   *   True if argument is an array-like object.
   */
  var isArrayLike = checkLength(getLength);

  /**
   * Retrieve the names of an object's own properties.
   *
   * Delegates to ECMAScript 5's native `Object.keys`.
   *
   * @private
   *
   * @param {mixed} x
   *   The x to test for its properties.
   *
   * @return {array}
   *   The object keys, or empty array.
   */
  function keys(x) {
    return !isObj(x) ? [] : Object.keys(x);
  }

  /**
   * Returns true if the x is a dBlazy.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type.
   *
   * @return {bool}
   *   True if x is an instanceof dBlazy.
   */
  function isMe(x) {
    return x instanceof dBlazy;
  }

  /**
   * True if the supplied argument is an array.
   *
   * @private
   *
   * One of the weird behaviors in JavaScript is the typeof Array is Object.
   *
   * @param {Mixed} x
   *   The x to check for its type.
   *
   * @return {bool}
   *   True if the argument is an instanceof Array.
   *
   * @todo refine, like everything else.
   */
  function isArr(x) {
    // String has length.
    if (isStr(x)) {
      return false;
    }
    return x && (Array.isArray(x) || isArrayLike(x));
  }

  /**
   * Returns true if the x is a boolean.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is an instanceof bool.
   */
  function isBool(x) {
    return x === true || x === false || _toString.call(x) === '[object Boolean]';
  }

  /**
   * Returns true if the x is an Element.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is an instanceof Element.
   */
  function isElm(x) {
    return x && x instanceof Element;
  }

  /**
   * Returns true if the argument is a function.
   *
   * @private
   *
   * @return {bool}
   *   True if argument is an instanceof Function.
   */
  var isFun = isTag('Function');

  /**
   * Returns true if the x is anything falsy.
   *
   * All values are truthy unless they are defined as falsy (i.e., except for
   * false, 0, -0, 0n, "", null, undefined, and NaN).
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if null, undefined, false or empty string or array.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Nullish_coalescing_operator
   * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Logical_NOT
   */
  function isEmpty(x) {
    if (isNull(x) || isUnd(x) || x === false) {
      return true;
    }

    // Skip expensive `toString`-based checks if `obj` has no `.length`.
    var length = getLength(x);
    if (typeof length === 'number' && (isArr(x) || isStr(x))) {
      return length === 0;
    }

    return getLength(keys(x)) === 0;
  }

  /**
   * Returns true if the x is a null.
   *
   * To those curious why this very simple comparasion has a method, check
   * out the minified one. It is called 7 times here, but called once at the
   * minifid one to just 1 character + 7 (`=== null`) = 14, saving many byte
   * codes. Otherwise `=== null` x 7 chracters = 49.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if null.
   */
  function isNull(x) {
    return x === null;
  }

  /**
   * Returns true if the x is a number.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if number.
   */
  function isNum(x) {
    return !isNaN(parseFloat(x)) && isFinite(x);
  }

  /**
   * Returns true if the x is an object.
   *
   * @private
   *
   * One of the weird behaviors in JavaScript is the typeof Array is Object.
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is an instanceof Object.
   */
  function isObj(x) {
    if (!x || typeof x !== 'object') {
      return false;
    }
    // var type = typeof x;
    // return type === 'function' || type === 'object' && !!x;
    var proto = Object.getPrototypeOf(x);
    return isNull(proto) || proto === _oProto;
  }

  /**
   * Returns true if the argument is a string.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type string.
   *
   * @return {bool}
   *   True if argument is a string.
   */
  function isStr(x) {
    return x && typeof x === 'string';
  }

  /**
   * Returns true if the x is undefined.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is undefined.
   */
  function isUnd(x) {
    return typeof x === 'undefined';
  }

  /**
   * Returns true if the x is window.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is window.
   */
  function isWin(x) {
    return !!x && x === x.window;
  }

  /**
   * Returns true if the x is valid for querySelector.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is valid for querySelector.
   *
   * 1: Node.ELEMENT_NODE
   * 9: Node.DOCUMENT_NODE
   * 11: Node.DOCUMENT_FRAGMENT_NODE
   * @see https://developer.mozilla.org/en-US/docs/Web/API/Node/nodeType
   */
  function isDoc(x) {
    return [9, 11].indexOf(!!x && x.nodeType) !== -1;
  }

  /**
   * Returns true if the x is valid for querySelector.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is valid for querySelector.
   *
   * 1: Node.ELEMENT_NODE
   * 9: Node.DOCUMENT_NODE
   * 11: Node.DOCUMENT_FRAGMENT_NODE
   * @see https://developer.mozilla.org/en-US/docs/Web/API/Node/nodeType
   */
  function isQsa(x) {
    return [1, 9, 11].indexOf(!!x && x.nodeType) !== -1;
  }

  /**
   * Returns true if the x is valid for event listener.
   *
   * @private
   *
   * @param {Mixed} x
   *   The x to check for its type truthy.
   *
   * @return {bool}
   *   True if x is valid for event listener.
   */
  function isEvt(x) {
    return isQsa(x) || isWin(x);
  }

  /**
   * A not simple forEach() implementation for Arrays, Objects and NodeLists.
   *
   * @private
   *
   * @param {Array|Object|NodeList} obj
   *   Collection of items to iterate.
   * @param {Function} cb
   *   Callback function for each iteration.
   * @param {Array|Object|NodeList} scope
   *   Object/NodeList/Array that forEach is iterating over (aka `this`).
   *
   * @return {Array}
   *   Returns this collection.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array/forEach
   * @see https://developer.mozilla.org/en-US/docs/Web/API/NodeList/forEach
   * @todo drop for native [].forEach post D10+ when IE gone from planet earth.
   * @todo refactor, unreliable given unexpected properties.
   */
  function each(obj, cb, scope) {
    if (isFun(obj) || isStr(obj) || isBool(obj) || isNum(obj)) {
      return [];
    }

    // Filter out useless empty array.
    if (isArr(obj) && !isUnd(obj.length)) {
      var length = obj.length;
      if (!length || (length === 1 && obj[0] === ' ')) {
        return [];
      }
    }

    // Filter out useless empty object.
    if (isObj(obj) && isEmpty(obj)) {
      return [];
    }

    if (_toString.call(obj) === '[object Object]') {
      for (var prop in obj) {
        if (hasProp(obj, prop)) {
          if (prop === 'length' || prop === 'name') {
            continue;
          }
          if (cb.call(scope, obj[prop], prop, obj) === false) {
            break;
          }
        }
      }
    }
    else if (obj) {
      if (obj instanceof HTMLCollection) {
        obj = _aProto.slice.call(obj);
      }

      var len = obj.length;
      if (len && len === 1 && !isUnd(obj[0])) {
        cb.call(scope, obj[0], 0, obj);
      }
      else {
        // Assumes array, at least non-expected objs were blacklisted above.
        obj.forEach(cb, scope);
      }
    }

    return obj;
  }

  /**
   * A hasOwnProperty wrapper.
   *
   * @private
   *
   * @param {Array|Object|NodeList} obj
   *   Collection of items to iterate.
   * @param {string} prop
   *   The property nane.
   *
   * @return {bool}
   *   Returns true if the property found.
   */
  function hasProp(obj, prop) {
    return _oProto.hasOwnProperty.call(obj, prop);
  }

  /**
   * A simple wrapper for JSON.parse() for string within data-* attributes.
   *
   * @private
   *
   * @param {string} str
   *   The string to convert into JSON object.
   *
   * @return {Object}
   *   The JSON object, or empty in case invalid.
   */
  function parse(str) {
    try {
      return str.length === 0 || str === '1' ? {} : JSON.parse(str);
    }
    catch (e) {
      return {};
    }
  }

  /**
   * Converts string/ element to array.
   *
   * @private
   *
   * @param {Element|string} x
   *   The object to make array.
   *
   * @return {Array}
   *   The resulting array.
   */
  function toArray(x) {
    return isArr(x) ? x : [x];
  }

  function _op(el, op, name, value) {
    return el[op + 'Attribute'](name, value);
  }

  /**
   * A forgiving attribute wrapper with fallback mimicking jQuery.attr method.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string|Object} attr
   *   The attr name, can be a string or object.
   * @param {string} defValue
   *   The default value, can be null or undefined for different intentions.
   * @param {string|bool} withDefault
   *   True if should get with defValue. Or a prefix such as data- for removal.
   *
   * @return {Object|string}
   *   The attribute value, or fallback, for getters, or this for setters.
   */
  function _attr(els, attr, defValue, withDefault) {
    var me = this;
    var _undefined = isUnd(defValue);
    var _obj = isObj(attr);
    var _getter = !_obj && (_undefined || isBool(withDefault));
    var prefix = isStr(withDefault) ? withDefault : '';

    // No defValue defined, or withDefault set, means a getter.
    if (_getter) {
      // @todo figure out multi-element getters. Ok for now, as hardly multiple.
      var el = els && els.length ? els[0] : els;
      if (_undefined) {
        defValue = '';
      }
      return hasAttr(el, attr) ? _op(el, _get, attr) : defValue;
    }

    var chainCallback = function (el) {
      if (!isQsa(el)) {
        return _getter ? '' : me;
      }

      // Passing a key-value pair object means setting multiple attributes once.
      if (isObj(attr)) {
        each(attr, function (value, key) {
          _op(el, _set, prefix + key, value);
        });
      }
      // Since an attribute value null makes no sense, assumes nullify.
      else if (isNull(defValue)) {
        each(toArray(attr), function (value) {
          var name = prefix + value;
          if (hasAttr(el, name)) {
            _op(el, _remove, name);
          }
        });
      }
      else {
        // Else a setter.
        if (attr === 'src') {
          // To minimize unnecessary mutations.
          el.src = defValue;
        }
        else {
          _op(el, _set, attr, defValue);
        }
      }
    };

    return chain.call(els, chainCallback);
  }

  /**
   * Checks if the element has attribute.
   *
   * @private
   *
   * @param {Element} el
   *   The HTML element.
   * @param {string} name
   *   The attribute name.
   *
   * @return {bool}
   *   True if it has the attribute.
   */
  function hasAttr(el, name) {
    return isQsa(el) && _op(el, _has, name);
  }

  /**
   * A removeAttribute wrapper.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string|Array} attr
   *   The attr name, or string array.
   * @param {string} prefix
   *   The attribute prefix if any, normally `data-`.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function removeAttr(els, attr, prefix) {
    return _attr(els, attr, null, prefix || '');
  }

  /**
   * Checks if the element has a class name.
   *
   * @private
   *
   * @param {Element} el
   *   The HTML element.
   * @param {string} names
   *   The class name, can be space-delimited for multiple names.
   *
   * @return {bool}
   *   True if it has the class name.
   */
  function hasClass(el, names) {
    var found = 0;

    if (isQsa(el) && isStr(names)) {
      names = names.trim();
      var _list = el.classList;

      var verify = function (name) {
        if (_list) {
          if (_list.contains(name)) {
            found++;
          }
        }
        if (found === 0) {
          // SVG may fail classList here.
          var check = _attr(el, _class);
          if (check && check.match(name)) {
            found++;
          }
        }
      };

      each(names.trim().split(' '), verify);
    }
    return found > 0;
  }

  /**
   * Toggles a class, or multiple from an element.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} name
   *   The class name, or space-delimited class names.
   * @param {string} op
   *   Whether to add or remove the class.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function toggleClass(els, name, op) {
    var chainCallback = function (el, i) {
      if (isQsa(el)) {
        var _list = el.classList;

        if (isFun(name)) {
          name = name(_op(el, _get, 'class'), i);
        }

        if (_list && isStr(name)) {
          var names = name.trim().split(' ');
          if (isUnd(op)) {
            names.map(function (value) {
              _list.toggle(value);
            });
          }
          else {
            _list[op].apply(_list, names);
          }
        }
      }
    };
    return chain.call(els, chainCallback);
  }

  /**
   * Adds a class, or space-delimited class names to an element.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} name
   *   The class name, or space-delimited class names.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function addClass(els, name) {
    return toggleClass(els, name, _add);
  }

  /**
   * Removes a class, or multiple from an element.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} name
   *   The class name, or space-delimited class names.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function removeClass(els, name) {
    return toggleClass(els, name, _remove);
  }

  /**
   * Checks if a string or element contains substring(s) or children.
   *
   * @private
   *
   * Similar to ES6 ::includes, only for oldies.
   * Cannot use [].every() since it is not about all or nothing.
   *
   * @param {Array|Element|string} str
   *   The source string to test for.
   * @param {Array.<Element>|Array.<string>} substr
   *   The target element(s) or sub-string to check for, can be a string array.
   *
   * @return {bool}
   *   True if it has the needle.
   */
  function contains(str, substr) {
    var found = 0;

    if (isElm(str) && isElm(substr)) {
      return str !== substr && str.contains(substr);
    }

    if (isArr(str)) {
      return str.indexOf(substr) !== -1;
    }

    if (isStr(str)) {
      each(toArray(substr), function (value) {
        if (str.indexOf(value) !== -1) {
          found++;
        }
      });
    }

    return found > 0;
  }

  /**
   * Escapes special (meta) characters.
   *
   * @private
   *
   * @link https://stackoverflow.com/questions/1144783
   *
   * @param {string} string
   *   The original source string.
   *
   * @return {string}
   *   The modified string.
   */
  function escape(string) {
    // $& means the whole matched string.
    return string.replace(/[.*+\-?^${}()|[\]\\]/g, '\\$&');
  }

  /**
   * Checks whether or not a string begins with another string, case-sensitive.
   *
   * @private
   *
   * @param {string} str
   *   The source string to test for.
   * @param {Array.<string>} substr
   *   The target sub-string to check for, can be a string array.
   *
   * @return {bool}
   *   True if it starts with the needle.
   */
  function startsWith(str, substr) {
    var found = 0;

    if (isStr(str)) {
      each(toArray(substr), function (value) {
        if (str.startsWith(value)) {
          found++;
        }
      });
    }
    return found > 0;
  }

  /**
   * Removes extra spaces so to keep readable template.
   *
   * @private
   *
   * @param {string} string
   *   The original source string.
   *
   * @return {string}
   *   The modified string.
   */
  function trimSpaces(string) {
    return string.replace(/\\s+/g, ' ').trim();
  }

  /**
   * A forgiving closest for the lazy.
   *
   * @private
   *
   * @param {Element} el
   *   Starting element.
   * @param {string} selector
   *   Selector to match against (class, ID, data attribute, or tag).
   *
   * @return {Element|Null}
   *   Returns null if no match found, else the element.
   */
  function closest(el, selector) {
    return (isElm(el) && isStr(selector)) ? el.closest(selector) : null;
  }

  /**
   * A forgiving matches for the lazy ala jQuery.
   *
   * @private
   *
   * @param {Element} el
   *   The current element.
   * @param {string} selector
   *   Selector to match against (class, ID, data attribute, or tag).
   *
   * @return {bool}
   *   Returns true if found, else false.
   *
   * @see http://caniuse.com/#feat=matchesselector
   * @see https://developer.mozilla.org/en-US/docs/Web/API/Element/matches
   */
  function is(el, selector) {
    if (isElm(el)) {
      if (isStr(selector)) {
        return el.matches(selector);
      }
      return isElm(selector) && el === selector;
    }
    return false;
  }

  /**
   * Check if an element matches the specified HTML tag.
   *
   * @private
   *
   * @param {Element} el
   *   The element to compare.
   * @param {string|Array.<string>} tags
   *   HTML tag(s) to match against.
   *
   * @return {bool}
   *   Returns true if matches, else false.
   */
  function equal(el, tags) {
    if (!el || !el.nodeName) {
      return false;
    }

    return _some.call(toArray(tags), function (tag) {
      return el.nodeName.toLowerCase() === tag.toLowerCase();
    });
  }

  /**
   * A simple querySelector wrapper.
   *
   * @private
   *
   * The only different from jQuery is if a single element found, it returns
   * the element so to avoid ugly repeats like elms[0], also to preserve
   * common vanilla practice which normally operates on the element directly.
   * Alternatively flag the asArray to any value if an array is expected, or
   * use the shortcut ::findAll() to be clear.
   *
   * To check if the expected element is found:
   *   - use $.isElm(el) which returns a bool.
   *
   * @param {Element} el
   *   The parent HTML element.
   * @param {string} selector
   *   The CSS selector or HTML tag to query.
   * @param {bool|int} asArray
   *   Force returning an array if expected to operate on.
   *
   * @return {?Array.<Element>}
   *   Empty array if not found, else the expected element(s).
   */
  function find(el, selector, asArray) {
    if (isQsa(el)) {
      // Direct descendant.
      var scope = ':scope';
      if (isStr(selector) && startsWith(selector, '>')) {
        if (!contains(selector, scope)) {
          selector = scope + ' ' + selector;
        }
      }
      return isUnd(asArray) && isStr(selector) ? (el.querySelector(selector) || []) : toElms(selector, el);
    }
    return [];
  }

  /**
   * A simple querySelectorAll wrapper.
   *
   * To check if the expected elements are found:
   *   - use regular `els.length`. The length 0 means not found.
   *
   * @private
   *
   * @param {Element} el
   *   The parent HTML element.
   * @param {string} selector
   *   The CSS selector or HTML tag to query.
   *
   * @return {?Array.<Element>}
   *   Empty array if not found, else the expected elements.
   */
  function findAll(el, selector) {
    return find(el, selector, 1);
  }

  /**
   * A simple removeChild wrapper.
   *
   * @private
   *
   * @param {Element} el
   *   The HTML element to remove.
   */
  function remove(el) {
    if (isElm(el)) {
      var cn = parent(el);
      if (cn) {
        cn.removeChild(el);
      }
    }
  }

  /**
   * Returns true if an IE browser.
   *
   * @private
   *
   * @param {Element} el
   *   The element to check for more contextual property/ feature detection.
   *
   * @return {bool}
   *   True if an IE browser.
   */
  function ie(el) {
    return (isElm(el) && el.currentStyle) || !isUnd(_doc.documentMode);
  }

  /**
   * Returns device pixel ratio.
   *
   * @private
   *
   * @return {number}
   *   Returns the device pixel ratio.
   */
  function pixelRatio() {
    return _win.devicePixelRatio || 1;
  }

  /**
   * Returns cross-browser window width.
   *
   * @private
   *
   * @return {number}
   *   Returns the window width.
   */
  function windowWidth() {
    return _win.innerWidth || _doc.documentElement[_clientWidth] || _win.screen[_width];
  }

  /**
   * Returns cross-browser window width and height.
   *
   * @private
   *
   * @return {Object}
   *   Returns the window width and height.
   */
  function windowSize() {
    return {
      width: windowWidth(),
      height: _win.innerHeight || _doc.documentElement.clientHeight
    };
  }

  /**
   * Returns data from the current active window.
   *
   * @private
   *
   * When being resized, the browser gave no data about pixel ratio from desktop
   * to mobile, not vice versa. Unless delayed for 4s+, not less, which is of
   * course unacceptable. Hence why Blazy never claims to support resizing. The
   * best efforts were provided using ResizeObserver since 2.2. including this.
   *
   * @param {Object.<int, Object>} dataset
   *   The dataset object must be keyed by window width.
   * @param {Object.<string, int|bool>} winData
   *   Containing ww: windowWidth, and up: to determine min-width or max-width.
   *
   * @return {Mixed}
   *   Returns data from the current active window.
   */
  function activeWidth(dataset, winData) {
    var mobileFirst = winData.up || false;
    var _k = keys(dataset);
    var xs = _k[0];
    var xl = _k[_k.length - 1];
    var ww = winData.ww || windowWidth();
    var pr = (ww * pixelRatio());
    var rw = mobileFirst ? ww : pr;
    var mw = function (w) {
      // The picture wants <= (approximate), non-picture wants >=, wtf.
      return mobileFirst ? parseInt(w, 10) <= rw : parseInt(w, 10) >= rw;
    };

    var data = _k.filter(mw).map(function (v) {
      return dataset[v];
    })[mobileFirst ? 'pop' : 'shift']();

    return isUnd(data) ? dataset[rw >= xl ? xl : xs] : data;
  }

  /**
   * A simple wrapper for event delegation like jQuery.on().
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} eventName
   *   The event name to trigger.
   * @param {string} selector
   *   Child selector to match against (class, ID, data attribute, or tag).
   * @param {Function} cb
   *   The callback function.
   * @param {Object|bool} params
   *   The optional param passed into a custom event.
   * @param {bool} isCustom
   *   True, if a custom event, a namespaced like (blazy.done), but considered
   *   as a whole since there is no event name `blazy`.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function on(els, eventName, selector, cb, params, isCustom) {
    return toEvent(els, eventName, selector, cb, params, isCustom, _add);
  }

  /**
   * A simple wrapper for event detachment.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} eventName
   *   The event name to trigger.
   * @param {string} selector
   *   Child selector to match against (class, ID, data attribute, or tag).
   * @param {Function} cb
   *   The callback function.
   * @param {Object|bool} params
   *   The optional param passed into a custom event.
   * @param {bool} isCustom
   *   True, if a custom event.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function off(els, eventName, selector, cb, params, isCustom) {
    return toEvent(els, eventName, selector, cb, params, isCustom, _remove);
  }

  /**
   * A simple wrapper for addEventListener once.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} eventName
   *   The event name to remove.
   * @param {Function} cb
   *   The callback function.
   * @param {bool} isCustom
   *   True, if a custom event.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function one(els, eventName, cb, isCustom) {
    return on(els, eventName, cb, {
      once: true
    }, isCustom);
  }

  /**
   * Checks if image is decoded/ completely loaded.
   *
   * @private
   *
   * @param {Image} img
   *   The Image object.
   *
   * @return {bool}
   *   True if the image is loaded.
   */
  function isDecoded(img) {
    // This is working fine, not a culprit.
    return img.decoded || img.complete;
  }

  /**
   * Executes the function once.
   *
   * @private
   *
   * @author Daniel Lamb <dlamb.open.source@gmail.com>
   * @link https://github.com/daniellmb/once.js
   *
   * @param {Function} cb
   *   The executed function.
   *
   * @return {Object}
   *   The function result.
   */
  function _once(cb) {
    var result;
    var ran = false;
    return function proxy() {
      if (ran) {
        return result;
      }
      ran = true;
      result = cb.apply(this, arguments);
      // For garbage collection.
      cb = null;
      return result;
    };
  }

  /**
   * Process arguments, query the DOM if necessary. Adapted from core/once.
   *
   * @private
   *
   * @param {NodeList|Array.<Element>|Element|string} selector
   *   A NodeList, array of elements, or string.
   * @param {Document|Element} ctx
   *   An element to use as context for querySelectorAll.
   *
   * @return {Array.<Element>}
   *   An array of elements to process.
   */
  function toElms(selector, ctx) {
    // Assume selector is an array-like element unless a string.
    var elements = toArray(selector);
    if (isStr(selector)) {
      ctx = context(ctx);
      var check = ctx.querySelector(selector);
      elements = isNull(check) ? [] : ctx.querySelectorAll(selector);
    }

    // Ensures an array is returned and not a NodeList or an Array-like object.
    // https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array/from
    return _aProto.slice.call(elements);
  }

  /**
   * A not simple wrapper for the namespaced [add|remove]EventListener.
   *
   * @private
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} eventName
   *   The event name, optionally namespaced, to add or remove.
   * @param {string|Function} selector
   *   Child selector to delegate (valid CSS selector). Or a callback.
   * @param {Function|Object|bool} cb
   *   The callback function. Or params passed into on/off like.
   * @param {Object|bool} params
   *   The optional param passed into a custom event. Or isCustom for on/off.
   * @param {bool|string} isCustom
   *   Like namespaced, but not, LHS is not native event. Or add/remove op.
   * @param {string|undefined} op
   *   Whether to add or remove the event. Or undefined for on/off like.
   *
   * @return {Object}
   *   This dBlazy object.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/addEventListener
   * @see https://caniuse.com/once-event-listener
   * @see https://github.com/WICG/EventListenerOptions/blob/gh-pages/explainer.md
   * @todo automatically handled by its return value.
   */
  function toEvent(els, eventName, selector, cb, params, isCustom, op) {
    var _cbt = cb;
    var _ie = ie();
    // Event delegation like on/off.
    if (isStr(selector)) {
      var shouldPassive = contains(eventName, ['touchstart', _scroll, 'wheel']);
      if (isUnd(params)) {
        params = _ie ? false : {
          capture: !shouldPassive,
          passive: shouldPassive
        };
      }

      var onEvent = function (e) {
        // @todo handle automatically by its return value.
        // e.preventDefault();
        // e.stopPropagation();
        var t = e.target;

        if (is(t, selector)) {
          _cbt.call(t, e);
        }
        else {
          while (t && t !== this) {
            if (is(t, selector)) {
              _cbt.call(t, e);
              break;
            }
            t = t.parentElement || t.parentNode;
          }
        }
      };

      cb = onEvent;
    }
    else {
      // Shift one argument if selector is expected as a callback function.
      isCustom = params;
      params = _cbt;
      cb = selector;
    }

    var chainCallback = function (el) {
      if (!isEvt(el)) {
        return;
      }

      var defaults = {
        capture: false,
        passive: true
      };

      var _one = false;
      var options = params || false;
      if (isObj(params)) {
        options = extend(defaults, params);
        _one = options.once || false;
      }

      var process = function (e) {
        isCustom = isCustom || startsWith(e, ['blazy.', 'bio.']);
        var add = op === _add;
        var type = (isCustom ? e : e.split('.')[0]).trim();
        cb = cb || _events[e];

        var _cb = cb;
        if (isFun(cb)) {
          // See https://caniuse.com/once-event-listener.
          if (_one && add && _ie) {
            var cbone = function cbone() {
              el.removeEventListener(type, cbone, options);
              _cb.apply(this, arguments);
            };
            cb = cbone;
            add = false;
          }

          el[op + 'EventListener'](type, cb, options);
        }

        // @todo store as namespace to allow easy removal by namespaces.
        if (add) {
          _events[e] = cb;
        }
        else {
          delete _events[e];
        }
      };

      each(eventName.trim().split(' '), process);
    };

    return chain.call(els, chainCallback);
  }

  /**
   * A not simple wrapper for triggering event like jQuery.trigger().
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string} eventName
   *   The event name to trigger.
   * @param {Object} details
   *   The optional detail object passed into a custom event detail property.
   * @param {Object} param
   *   The optional param passed into a custom event.
   *
   * @return {CustomEvent|Event|undefined}
   *   The CustomEvent or Event object to dispatch.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/Guide/Events/Creating_and_triggering_events
   * @see https://developer.mozilla.org/en-US/docs/Web/API/EventTarget/dispatchEvent
   * @see https://developer.mozilla.org/en-US/docs/Web/API/Document/createEvent
   * @todo namespaced event name, and more refined native event.
   */
  function trigger(els, eventName, details, param) {
    var chainCallback = function (el) {
      var event;
      if (!isEvt(el)) {
        return event;
      }

      if (isUnd(details)) {
        event = new Event(eventName);
      }
      else {
        // Bubbles to be caught by ancestors. Cancelable to preventDefault.
        var data = {
          bubbles: true,
          cancelable: true,
          detail: details || {}
        };

        if (isObj(param)) {
          data = extend(data, param);
        }

        event = new CustomEvent(eventName, data);
      }

      el.dispatchEvent(event);
      return event;
    };

    return chain.call(els, chainCallback);
  }

  // Type methods.
  // Wonder why ES6 has alt lambda `=>` for `function`? Compact, to save bytes.
  // Kotlin has useless `fun` due to being compiled back to `function`. But ES6
  // lambda is true savings unless being transpiled. So these stupid abbr are.
  // The contract here is no rigid minds, fun, less bytes. Hail to Linux.
  db.isTag = isTag;
  db.isArr = isArr;
  db.isBool = isBool;
  db.isDoc = isDoc;
  db.isElm = isElm;
  db.isFun = isFun;
  db.isEmpty = isEmpty;
  db.isNull = isNull;
  db.isNum = isNum;
  db.isObj = isObj;
  db.isStr = isStr;
  db.isUnd = isUnd;
  db.isEvt = isEvt;
  db.isQsa = isQsa;
  db.isIo = 'Intersection' + _observer in _win;
  db.isMo = 'Mutation' + _observer in _win;
  db.isRo = 'Resize' + _observer in _win;
  db.isNativeLazy = 'loading' in HTMLImageElement.prototype;
  db.isAmd = typeof define === 'function' && define.amd;
  db.isWin = isWin;
  db._er = -1;
  db._ok = 1;

  // Collection methods.
  db.chain = function (els, cb) {
    return chain.call(els, cb);
  };

  db.each = each;

  db.extend = extend;
  fn.extend = function (plugins, reverse) {
    reverse = reverse || false;
    return reverse ? extend(plugins, fn) : extend(fn, plugins);
  };

  db.hasProp = hasProp;

  db.parse = parse;
  db.toArray = toArray;

  // Attribute methods.
  db.hasAttr = hasAttr;
  db.attr = _attr.bind(db);
  db.removeAttr = removeAttr.bind(db);

  // Class name methods.
  db.hasClass = hasClass;
  db.toggleClass = toggleClass;
  db.addClass = addClass;
  db.removeClass = removeClass;

  // String methods.
  db.contains = contains;
  db.escape = escape;
  db.startsWith = startsWith;
  db.trimSpaces = trimSpaces;

  // DOM query methods.
  db.closest = closest;
  db.is = is;

  // @todo merge with ::is().
  db.equal = equal;
  db.find = find;
  db.findAll = findAll;
  db.remove = remove;

  // Window methods.
  db.ie = ie;
  db.pixelRatio = pixelRatio;
  db.windowWidth = windowWidth;
  db.windowSize = windowSize;
  db.activeWidth = activeWidth;

  // Event methods.
  db.toEvent = toEvent;
  db.on = on;
  db.off = off;
  db.one = one;
  db.trigger = trigger;

  // Image methods.
  db.isDecoded = isDecoded;

  // Similar to core domReady, only public and generic.
  function ready(callback) {
    var cb = function () {
      return setTimeout(callback, 0, db);
    };

    if (_doc.readyState !== 'loading') {
      cb();
    }
    else {
      _doc.addEventListener('DOMContentLoaded', cb);
    }

    return this;
  }

  db.ready = ready.bind(db);

  /**
   * Decodes the image.
   *
   * @param {Image} img
   *   The Image object.
   *
   * @return {Promise}
   *   The Promise object.
   *
   * @see https://caniuse.com/promises
   * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise
   * @see https://github.com/taylorhakes/promise-polyfill
   * @see https://chromestatus.com/feature/5637156160667648
   * @see https://html.spec.whatwg.org/multipage/embedded-content.html#dom-img-decode
   */
  db.decode = function (img) {
    if (isDecoded(img)) {
      return Promise.resolve(img);
    }

    if ('decode' in img) {
      img.decoding = 'async';
      return img.decode();
    }

    return new Promise(function (resolve, reject) {
      img.onload = function () {
        resolve(img);
      };
      img.onerror = reject();
    });
  };

  /**
   * A wrapper for core/once until D9.2 is a minimum.
   *
   * @param {Function} cb
   *   The executed function.
   * @param {string} id
   *   The id of the once call.
   * @param {NodeList|Array.<Element>|Element|string} selector
   *   A NodeList, array of elements, single Element, or a string.
   * @param {Document|Element} ctx
   *   An element to use as context for querySelectorAll.
   *
   * @return {Array.<Element>}
   *   An array of elements to process, or empty for old behavior.
   */
  function onceCompat(cb, id, selector, ctx) {
    var els = [];

    // If a string, assumes find once like core/once.
    if (isStr(cb)) {
      return findOnce(cb, id);
    }

    // Original once.
    if (isUnd(selector)) {
      _once(cb);
    }
    // If extra arguments are provided, assumes regular loop over elements.
    else {
      els = initOnce(id, selector, ctx);
      if (els.length) {
        // Already avoids loop for a single item.
        each(els, cb);
      }
    }

    return els;
  }

  db.once = onceCompat;

  /**
   * A simple wrapper to delay callback function, taken out of blazy library.
   *
   * Alternative to core Drupal.debounce for D7 compatibility, and easy port.
   *
   * @param {Function} cb
   *   The callback function.
   * @param {number} minDelay
   *   The execution delay in milliseconds.
   * @param {Object} scope
   *   The scope of the function to apply to, normally this.
   *
   * @return {Function}
   *   The function executed at the specified minDelay.
   */
  db.throttle = function (cb, minDelay, scope) {
    minDelay = minDelay || 50;
    var lastCall = 0;
    return function () {
      var now = +new Date();
      if (now - lastCall < minDelay) {
        return;
      }
      lastCall = now;
      cb.apply(scope, arguments);
    };
  };

  /**
   * A simple wrapper to delay callback function on window resize.
   *
   * @link https://github.com/louisremi/jquery-smartresize
   *
   * @param {Function} cb
   *   The callback function.
   * @param {number} t
   *   The timeout.
   *
   * @return {ResizeObserver|Function}
   *   The ResizeObserver instance, or callback function.
   */
  db.resize = function (cb, t) {
    // @todo enable later when old projects are updated: lory, extended, etc.
    // if (this.isRo) {
    // return new ResizeObserver(cb);
    // }
    _win.onresize = function (e) {
      clearTimeout(t);
      t = setTimeout(cb.bind(e), 200);
    };
    return cb;
  };

  /**
   * Replaces string occurances to simplify string templating.
   *
   * @param {string} string
   *   The original source string.
   * @param {Object.<string, string>} map
   *   The mapping object.
   *
   * @return {string}
   *   The modified string.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Template_literals
   * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/replaceAll
   * @see https://caniuse.com/mdn-javascript_builtins_string_replaceall
   * @see https://stackoverflow.com/questions/1144783
   * @todo use template string or replaceAll for D10, or D11 at the latest.
   */
  db.template = function (string, map) {
    for (var key in map) {
      if (hasProp(map, key)) {
        string = string.replace(new RegExp(escape('$' + key), 'g'), map[key]);
      }
    }
    return trimSpaces(string);
  };

  /**
   * A simple wrapper for context insanity.
   *
   * Context is unreliable with AJAX contents like product variations, etc.
   * This can be null after Colorbox close, or absurd <script> element, likely
   * arbitrary, etc.
   *
   * @param {Document|Element} ctx
   *   Any element, including weird script element.
   *
   * @return {Element|Document|DocumentFragment}
   *   The Element|Document|DocumentFragment to not fail querySelector, etc.
   *
   * @todo refine core/once expects Element only, or patch it for [1,9,11].
   */
  function context(ctx) {
    // Weirdo: context may be null after Colorbox close.
    ctx = ctx || _doc;

    // Checks if a string is given as a context.
    if (isStr(ctx)) {
      ctx = is(ctx, 'html') ? _doc : _doc.querySelector(ctx);
    }

    // Prevents problematic _doc.documentElement as the context.
    if (is(ctx, 'html')) {
      ctx = _doc;
    }

    // jQuery may pass its array as non-expected context identified by length.
    ctx = toElm(ctx);

    // Absurd <script> elements which have no children may be spit on AJAX.
    if (isQsa(ctx) && ctx.children && ctx.children.length) {
      return ctx;
    }

    // IE9 knows not deprecated HTMLDocument, IE8 does.
    return isDoc(ctx) ? ctx : _doc;
  }

  // Valid elements for querySelector with length: form, select, etc.
  function toElm(el) {
    var isJq = _isJq && el instanceof _win.jQuery;
    var isCash = _isCash && el instanceof _win.cash;
    return el && (isMe(el) || isJq || isCash) ? el[0] : el;
  }

  // Minimum common DOM methods taken and modified from cash.
  // @todo refactor or remove dups when everyone uses cash, or vanilla alike.
  function camelCase(str) {
    return str.replace(_dashAlphaRe, function (match, letter) {
      return letter.toUpperCase();
    });
  }

  function isVar(prop) {
    return _cssVariableRe.test(prop);
  }

  // @see https://developer.mozilla.org/en-US/docs/Web/API/Window/getComputedStyle
  function computeStyle(el, prop, isVariable) {
    if (!isElm(el)) {
      return;
    }

    var _style = getComputedStyle(el, null);
    if (isUnd(prop)) {
      return _style;
    }

    if (isVariable || isVar(prop)) {
      return _style.getPropertyValue(prop) || null;
    }

    return _style[prop] || el.style[prop];
  }

  // https://developer.mozilla.org/en-US/docs/Web/API/Element/getBoundingClientRect
  function rect(el) {
    return isElm(el) ? el.getBoundingClientRect() : {};
  }

  function traverse(el, selector, relative) {
    if (isElm(el)) {
      var target = el[relative];

      if (isUnd(selector)) {
        return target;
      }

      while (target) {
        if (is(target, selector) || equal(target, selector)) {
          return target;
        }
        target = target[relative];
      }
    }
    return null;
  }

  function parent(el, selector) {
    return traverse(el, selector, 'parentElement');
  }

  function prevnext(el, selector, prefix) {
    return traverse(el, selector, prefix + 'ElementSibling');
  }

  function prev(el, selector) {
    return prevnext(el, selector, 'previous');
  }

  function next(el, selector) {
    return prevnext(el, selector, 'next');
  }

  function empty(els) {
    var chainCallback = function (el) {
      if (isElm(el)) {
        while (el.firstChild) {
          el.removeChild(el.firstChild);
        }
      }
    };

    return chain.call(els, chainCallback);
  }

  function index(el, parents) {
    var i = 0;
    if (isElm(el)) {
      if (!isUnd(parents)) {
        each(toArray(parents), function (sel) {
          var check = closest(el, sel);
          if (isElm(check)) {
            el = check;
            return false;
          }
        });
      }

      while (!isNull(el = prev(el))) {
        i++;
      }
    }
    return i;
  }

  db.context = context;
  db.toElm = toElm;
  db.camelCase = camelCase;
  db.isVar = isVar;
  db.computeStyle = computeStyle;
  db.rect = rect;
  db.empty = empty;
  db.parent = parent;
  db.next = next;
  db.prev = prev;
  db.index = index;
  db.keys = keys;

  db.create = function (tagName, attrs, html) {
    var el = _doc.createElement(tagName);

    if (isStr(attrs) || isObj(attrs)) {
      if (isStr(attrs)) {
        el.className = attrs;
      }
      else {
        _attr(el, attrs);
      }
    }

    if (html) {
      html = html.trim();

      el.innerHTML = html;
      if (tagName === 'template') {
        el = el.content.firstChild || el;
      }
    }

    return el;
  };

  // See https://caniuse.com/?search=localstorage
  db.storage = function (key, value, defValue, restore) {
    if (_storage) {
      if (isUnd(value)) {
        return _storage.getItem(key);
      }

      if (isNull(value)) {
        _storage.removeItem(key);
      }
      else {
        try {
          _storage.setItem(key, value);
        }
        catch (e) {
          // Reset if (2 - 10MB) quota is exceeded, if value is growing.
          _storage.removeItem(key);

          // Only makes sense if the value is incremental, not the quota limit.
          if (restore) {
            _storage.setItem(key, value);
          }
        }
      }
    }
    return defValue || false;
  };

  // @todo merge with cash if available.
  // if (_isCash) {
  // fn.extend(cash.fn, true);
  // }
  // Collects base prototypes for clarity.
  var objs = {
    chain: function (cb) {
      return chain.call(this, cb);
    },
    each: function (cb) {
      return each(this, cb);
    },
    ready: function (callback) {
      return ready.call(this, callback);
    }
  };

  // Merge base prototypes.
  fn.extend(objs);

  // @deprecated for shorter ::is(). Hardly used, except lory.
  db.matches = is;

  // @tbd deprecated for db.each to save bytes. Used by many sub-modules.
  db.forEach = each;

  // @tbd deprecated for on/off with shifted arguments. Use on/ off instead.
  db.bindEvent = on.bind(db);

  db.unbindEvent = off.bind(db);

  function _filter(selector, elements, apply) {
    return elements.filter(function (el) {
      var selected = is(el, selector);
      if (selected && apply) {
        apply(el);
      }
      return selected;
    });
  }

  db.filter = _filter;

  // @todo remove all these when min D9.2, or take the least minimum for BC.
  // Be sure to make context Element, or patch it to work with [1,9,11] types
  // which distinguish this from core/once as per 2022/2.
  // When removed and context issue is fixed, it will be just:
  // `db.once = extend(db.once, once);` + `db.once.removeSafely()`.
  function elsOnce(selector, ctx) {
    return findAll(context(ctx), selector);
  }

  function selOnce(id) {
    return '[' + _dataOnce + '~="' + id + '"]';
  }

  function updateOnce(el, opts) {
    var add = opts.add;
    var remove = opts.remove;
    var result = [];

    if (hasAttr(el, _dataOnce)) {
      var ids = _attr(el, _dataOnce).trim().split(_wsRe);
      each(ids, function (id) {
        if (!contains(result, id) && id !== remove) {
          result.push(id);
        }
      });
    }
    if (add && !contains(result, add)) {
      result.push(add);
    }
    var value = result.join(' ');
    _op(el, value === '' ? _remove : _set, _dataOnce, value);
  }

  function initOnce(id, selector, ctx) {
    return _filter(':not(' + selOnce(id) + ')', elsOnce(selector, ctx), function (el) {
      updateOnce(el, {
        add: id
      });
    });
  }

  function findOnce(id, ctx) {
    return elsOnce(!id ? '[' + _dataOnce + ']' : selOnce(id), ctx);
  }

  if (!db.once.find) {
    db.once.find = findOnce;
    db.once.filter = function (id, selector, ctx) {
      return _filter(selOnce(id), elsOnce(selector, ctx));
    };
    db.once.remove = function (id, selector, ctx) {
      return _filter(
        selOnce(id),
        elsOnce(selector, ctx),
        function (el) {
          updateOnce(el, {
            remove: id
          });
        }
      );
    };
    db.once.removeSafely = function (id, selector, ctx) {
      var me = this;
      var jq = _win.jQuery;

      if (me.find(id, ctx).length) {
        me.remove(id, selector, ctx);
      }

      // @todo remove BC for pre core/once when min D9.2:
      if (_isJq && jq && jq.fn && isFun(jq.fn.removeOnce)) {
        jq(selector, context(ctx)).removeOnce(id);
      }
    };
  }

  if (typeof exports !== 'undefined') {
    // Node.js.
    module.exports = db;
  }
  else {
    // Browser.
    _win.dBlazy = db;
  }

})(this, this.document);
