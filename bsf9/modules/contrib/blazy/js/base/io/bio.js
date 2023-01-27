/**
 * @file
 * Provides Intersection Observer API loader.
 *
 * This file is not loaded when `No JavaScript` enabled, unless exceptions met.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 * @see https://developers.google.com/web/updates/2016/04/intersectionobserver
 * @see https://www.npmjs.com/package/intersection-observer
 * @see https://github.com/w3c/IntersectionObserver
 * @see https://caniuse.com/?search=visualViewport
 * @todo https://developer.mozilla.org/en-US/docs/Web/API/Visual_Viewport_API
 * @todo remove traces of fallback to be taken care of by old bLazy fork.
 */

/* global define, module */
(function (root, factory) {

  'use strict';

  var ns = 'Bio';
  var db = root.dBlazy;

  // Inspired by https://github.com/addyosmani/memoize.js/blob/master/memoize.js
  if (db.isAmd) {
    // AMD. Register as an anonymous module.
    define([ns, db, root], factory);
  }
  else if (typeof exports === 'object') {
    // Node. Does not work with strict CommonJS, but only CommonJS-like
    // environments that support module.exports, like Node.
    module.exports = factory(ns, db, root);
  }
  else {
    // Browser globals (root is window).
    root[ns] = factory(ns, db, root);
  }

}((this || module || {}), function (ns, $, _win) {

  'use strict';

  if ($.isAmd) {
    _win = window;
  }

  /**
   * Private variables.
   */
  var _doc = document;
  var _root = _doc;
  var _winData = {};
  var _bioTick = 0;
  var _ww = 0;
  var _revTick = 0;
  var _counted = 0;
  var _opts = {};
  var _bgClass = 'b-bg';
  var _isVisible = 'is-b-visible';
  var _media = 'media';
  var _parent = '.' + _media;
  var _addClass = 'addClass';
  var _removeClass = 'removeClass';
  var _initialized = false;
  var _resizing = false;
  var _validateDelay = 25;

  // Cache our prototype.
  var fn = Bio.prototype;
  fn.constructor = Bio;

  /**
   * Constructor for Bio, Blazy IntersectionObserver.
   *
   * @param {object} options
   *   The Bio options.
   *
   * @return {Bio}
   *   The Bio instance.
   *
   * @namespace
   */
  function Bio(options) {
    var me = $.extend({}, fn, this);

    me.name = ns;
    me.options = _opts = $.extend({}, $._defaults, options || {});

    _bgClass = _opts.bgClass || _bgClass;
    _validateDelay = _opts.validateDelay || _validateDelay;
    _parent = _opts.parent || _parent;
    _root = _opts.root || _root;

    // DOM ready fix. Ain't a culprit.
    setTimeout(function () {
      me.reinit();
    });

    return me;
  }

  // Prepare prototype to interchange with Blazy as fallback.
  fn.count = 0;
  fn.erCount = 0;
  fn.resizeTick = 0;
  fn.destroyed = false;
  fn.options = {};
  fn.lazyLoad = function (el, winData) {};
  fn.loadImage = function (el, isBg, winData) {};
  fn.resizing = function (el, winData) {};
  fn.prepare = function () {};
  fn.windowData = function () {
    return $.isUnd(_winData.vp) ? $.windowData(this.options, true) : _winData;
  };

  // BC for interchanging with bLazy.
  // @todo merge with bLazy::load.
  fn.load = function (elms, revalidate, opts) {
    var me = this;

    elms = elms && $.toArray(elms);

    // @todo remove once infinite pager regression fixed properly like before.
    if (!$.isUnd(opts)) {
      me.options = $.extend({}, me.options, opts || {});
    }

    // Manually load elements regardless of being disconnected, or not, relevant
    // for Slick slidesToShow > 1 which rebuilds clones of unloaded elements.
    $.each(elms, function (el) {
      if (me.isValid(el) || ($.isElm(el) && revalidate)) {
        intersecting.call(me, el, revalidate);
      }
    });
  };

  fn.isLoaded = function (el) {
    return $.hasClass(el, this.options.successClass);
  };

  fn.isValid = function (el) {
    return $.isElm(el) && !this.isLoaded(el);
  };

  fn.revalidate = function (force) {
    var me = this;

    // Prevents from too many revalidations unless needed.
    if ((force === true || me.count !== _counted) && (_revTick < _counted)) {
      var elms = me.elms = $.findAll(_root, $.selector(me.options));

      if (elms.length) {
        me.observe(true);

        _revTick++;
      }
    }
  };

  fn.destroyQuietly = function (force) {
    var me = this;
    var opts = me.options;

    // Infinite pager like IO wants to keep monitoring infinite contents.
    // Multi-breakpoint BG/ ratio may want to update during resizing.
    if (!me.destroyed && (force || $.isUnd(Drupal.io))) {
      var el = $.find(_doc, $.selector(opts, ':not(.' + opts.successClass + ')'));

      if (!$.isElm(el)) {
        me.destroy(force);
      }
    }
  };

  fn.destroy = function (force) {
    var me = this;
    var opts = me.options;
    var io = me.ioObserver;

    // Do not disconnect if any error found.
    if (me.destroyed || (me.erCounted > 0 && !force)) {
      return;
    }

    // Disconnect when all entries are loaded, if so configured.
    var done = (_bioTick === me.count - 1) && opts.disconnect;
    if (done || force) {
      if (io) {
        io.disconnect();
      }

      $.unload(me);
      me.count = 0;
      me.elms = [];
      me.ioObserver = null;
      me.destroyed = true;
    }
  };

  fn.observe = function (reobserve) {
    var me = this;
    var elms = me.elms;

    // Only initialize the observer if destroyed, and IO.
    if ($.isIo && (me.destroyed || reobserve)) {
      _winData = $.initObserver(me, interact, elms, true);

      me.destroyed = false;
    }

    // Observe as IO, or initialize old bLazy as fallback.
    if (!_initialized || reobserve) {
      $.observe(me, elms, true);

      _initialized = true;
    }
  };

  fn.reinit = function () {
    var me = this;
    me.destroyed = true;

    init(me);
  };

  function intersecting(el, revalidate) {
    var me = this;
    var opts = me.options;
    var count = me.count;
    var io = me.ioObserver;

    if (_bioTick === count - 1) {
      me.destroyQuietly();
    }

    // Unlike ResizeObserver/ infinite pager, IntersectionObserver is done.
    if (io && me.isLoaded(el) && !el.bloaded && opts.isMedia && !revalidate) {
      io.unobserve(el);
      el.bloaded = true;

      _bioTick++;
    }

    // Image may take time to load after being hit, and it may be intersected
    // several times till marked loaded. Ensures it is hit once regardless
    // of being loaded, or not. No real issue with normal images on the page,
    // until having VIS alike which may spit out new images on AJAX request.
    if (!el.bhit || revalidate) {
      // Makes sure to have media loaded beforehand.
      me.lazyLoad(el, _winData);

      _counted++;

      // Marks it hit/ requested. Not necessarily loaded.
      el.bhit = true;
      revalidate = false;
    }

    // If not extending/ overriding, at least provide the option.
    // Currenty IO.module wants to keep watching for infinite pager since it
    // only has a single button/ link to observe so should not be locked.
    // @todo move it back right after ::lazyLoad once IO.module smarter.
    if ($.isFun(opts.intersecting)) {
      opts.intersecting(el, opts);
    }

    // If not extending/ overriding, also allows to listen to.
    $.trigger(el, 'bio.intersecting', {
      options: opts
    });
  }

  // This function is called by two observers: IO and RO.
  function interact(entries) {
    var me = this;
    var opts = me.options;
    var vp = $.vp;
    var ww = $.ww;
    var entry = entries[0];
    var isBlur = $.isBlur(entry);
    var isResizing = $.isResized(me, entry);
    var visibleClass = opts.visibleClass;

    // RO is another abserver.
    if (isResizing) {
      _winData = $.updateViewport(opts);

      $.onresizing(me, _winData);
    }
    else {
      // Stop IO watching if destroyed, unless a visibleClass is defined:
      // Animation, BG color on being visible, infinite pager, or lazyloaded
      // blocks. Infinite pager is a valid sample since it has a single link
      // to observe for infinite click events. Unobserve should be left to them.
      if (me.destroyed && !visibleClass) {
        return;
      }
    }

    // Load each on entering viewport.
    $.each(entries, function (e) {
      var target = e.target;
      var el = target || e;
      var resized = $.isResized(me, e);
      var visible = $.isVisible(e, vp);
      var cn = $.closest(el, _parent) || el;
      var loaded = me.isLoaded(el);

      // To make efficient blur filter via CSS, etc. Blur filter is expensive.
      $[visible && !loaded ? _addClass : _removeClass](cn, _isVisible);

      // For different toggle purposes regardless being loaded, or not.
      // Avoid using the reserved `is-b-visible`, use `is-b-inview`, etc.
      if (visibleClass && $.isStr(visibleClass)) {
        $[visible ? _addClass : _removeClass](cn, visibleClass);
      }

      // The element is being intersected.
      if (visible) {
        intersecting.call(me, el);
      }

      // The element is being resized.
      _resizing = resized && _ww > 0;
      if (_resizing && !isBlur) {
        // Ensures only before settled, or if any different from previous size.
        if (_ww !== ww) {
          me.resizing(el, _winData);
        }
        me.resizeTick++;
      }

      // Provides option such as to animate bg or elements regardless position.
      // See gridstack.parallax.js.
      if ($.isFun(opts.observing)) {
        opts.observing(e, visible, opts);
      }
    });

    _ww = ww;
  }

  // Initializes the IO with fallback to old bLazy.
  function init(me) {
    // Swap data-[SRC|SRCSET] for non-js version once, if not choosing Native.
    // Native lazy markup is triggered by enabling `No JavaScript` lazy option.
    me.prepare();

    var elms = me.elms = $.findAll(_root, $.selector(me.options));
    me.count = elms.length;
    me._raf = [];
    me._queue = [];

    // Observe elements. Old blazy as fallback is also initialized here.
    // IO will unobserve, or disconnect. Old bLazy will self destroy.
    me.observe(true);
  }

  return Bio;

}));
