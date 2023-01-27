/**
 * @file
 * Provides Intersection Observer API loader for media.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 * @see https://developers.google.com/web/updates/2016/04/intersectionobserver
 */

/* global define, module */
(function (root, factory) {

  'use strict';

  var ns = 'BioMedia';
  var db = root.dBlazy;
  var bio = root.Bio;

  // Inspired by https://github.com/addyosmani/memoize.js/blob/master/memoize.js
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define([ns, db, bio], factory);
  }
  else if (typeof exports === 'object') {
    // Node. Does not work with strict CommonJS, but only CommonJS-like
    // environments that support module.exports, like Node.
    module.exports = factory(ns, db, bio);
  }
  else {
    // Browser globals (root is window).
    root[ns] = factory(ns, db, bio);
  }
})(this, function (ns, $, _bio) {

  'use strict';

  /**
   * Private variables.
   */
  var _doc = document;
  var _data = 'data-';
  var _src = 'src';
  var _srcSet = 'srcset';
  var _dataSrc = _data + _src;
  var _dataSrcset = _data + _srcSet;
  var _imgSources = [_srcSet, _src];
  var _erCounted = 0;
  var _isDeferChecked = false;

  // Inherits Bio prototype.
  var _super = Bio.prototype;
  var fn = BioMedia.prototype = Object.create(_super);
  fn.constructor = BioMedia;

  /**
   * Constructor for BioMedia, Blazy IntersectionObserver for media.
   *
   * @param {object} options
   *   The BioMedia options.
   *
   * @return {object}
   *   The BioMedia instance.
   *
   * @namespace
   */
  function BioMedia(options) {
    var me = _bio.apply($.extend({}, _super, $.extend({}, fn, this)), arguments);

    me.name = ns;

    return me;
  }

  // Extends Bio prototype.
  fn.lazyLoad = function (el, winData) {
    var me = this;
    var opts = me.options;
    var parent = el.parentNode;
    var isBg = $.isBg(el);
    var isPicture = $.equal(parent, 'picture');
    var isImage = $.equal(el, 'img');
    var isVideo = $.equal(el, 'video');
    var isDataset = $.hasAttr(el, _dataSrc);

    // Initializes blur, if any.
    if ($.blur) {
      $.blur(el);
    }

    // PICTURE elements.
    if (isPicture) {
      if (isDataset) {
        $.mapSource(el, _srcSet, true);

        // Tiny controller image inside picture element won't get preloaded.
        $.mapAttr(el, _src, true);
      }

      _erCounted = defer(me, el, true, opts);
    }
    // VIDEO elements.
    else if (isVideo) {
      _erCounted = $.loadVideo(el, true, opts);
    }
    else {
      // IMG or DIV/ block elements got preloaded for better UX with loading.
      // Native doesn't support DIV, fix it.
      if (isImage || isBg) {
        me.loadImage(el, isBg, winData);
      }
      // IFRAME elements, etc.
      else {
        if ($.hasAttr(el, _src)) {
          if ($.attr(el, _dataSrc)) {
            $.mapAttr(el, _src, true);
          }

          _erCounted = defer(me, el, true, opts);
        }
      }
    }
    me.erCount = _erCounted;
  };

  // Compatibility between Native and old data-[SRC|SRSET] approaches.
  fn.loadImage = function (el, isBg, winData) {
    var me = this;
    var opts = me.options;
    var img = new Image();
    var isResimage = $.hasAttr(el, _srcSet);
    var isDataset = $.hasAttr(el, _dataSrc);
    var currSrc = isDataset ? _dataSrc : _src;
    var currSrcset = isDataset ? _dataSrcset : _srcSet;

    var preload = function () {
      if ('decode' in img) {
        img.decoding = 'async';
      }

      if (isBg && $.isFun($.bgUrl)) {
        img.src = $.bgUrl(el, winData);
      }
      else {
        if (isDataset) {
          $.mapAttr(el, _imgSources, false);
        }

        img.src = $.attr(el, currSrc);
      }

      if (isResimage) {
        img.srcset = $.attr(el, currSrcset);
      }
    };

    var load = function (el, ok) {
      if (isBg && $.isFun($.bg)) {
        $.bg(el, winData);
        _erCounted = $.status(el, ok, opts);
      }
      else {
        _erCounted = defer(me, el, ok, opts);
      }
    };

    preload();

    // Preload `img` to have correct event handlers.
    $.decode(img)
      .then(function () {
        load(el, true);
      })
      .catch(function () {
        load(el, isResimage);

        // Allows to re-observe.
        if (!isResimage) {
          el.bhit = false;
        }
      });
  };

  fn.resizing = function (el, winData) {
    var me = this;
    var isBg = $.isBg(el, me.options);

    // Fix dynamic multi-breakpoint background to avoid loaders workarounds.
    if (isBg) {
      me.loadImage(el, isBg, winData);
    }
  };

  // Applies the defer loading as per https://drupal.org/node/3120696.
  function defer(me, el, status, opts) {
    if (!_isDeferChecked) {
      var elms = natively(me, 'defer');
      if (elms) {
        $.each(elms, function (elm) {
          $.attr(elm, 'loading', 'lazy');
        });
      }
      _isDeferChecked = true;
    }

    return $.status(el, status, opts);
  }

  // Since bLazy, which has no supports for Native, is a fallback, it is easier
  // now to work with Native. No more need to hook into load event separately,
  // no deferred invocation till one loaded, no hijacking.
  // No more fights under a single source of truth. It is a total swap.
  // As mentioned in the doc, Native at least Chrome starts loading images
  // 8000px, hardcoded, before they are entering the viewport. Meaning harsh,
  // makes fancy stuffs like blur useless. And bad because blur filter
  // is very expensive, and when they are triggered before visible, will block.
  // @see /admin/help/blazy_ui# NATIVE LAZY LOADING
  // With bIO as the main loader, the game changed, quoted from:
  // https://developer.mozilla.org/en-US/docs/Learn/HTML/Howto/Author_fast-loading_HTML_pages
  // "Note that lazily-loaded images may not be available when the load event is
  // fired. You can determine if a given image is loaded by checking to see if
  // the value of its Boolean complete property is true."
  // Old bLazy relies on onload, meaning too early loaded decision for Native,
  // the reason for our previous deferred invocation, not decoding like what bIO
  // did which is more precise as suggested by the quote.
  // Assumed, untested, fine with combo IO + decoding checks before blur spits.
  // Shortly we are in the right direction to cope with Native vs. data-[SRC].
  // @done recheck IF wrong so to put back https://drupal.org/node/3120696.
  // Almost not wrong, no blur nor `b-loaded` were added till intersected, but
  // added a new `loading:defer` to solve 8000px threshold.
  function natively(me, key) {
    var opts = me.options;

    if (!$.isNativeLazy) {
      return [];
    }

    // ::findAll is already optimized with a single null check, no extra checks.
    // The `a` keyword found in `auto, eager, lazy`, not `defer`.
    key = key || 'a';
    var dataset = $.selector(opts, '[data-src][loading*="' + key + '"]:not(.b-blur)');
    var els = $.findAll(_doc, dataset);

    // We are here if `No JavaScript` is being disabled.
    if (els.length) {
      // Reset attributes, and let supportive browsers lazy load natively.
      $(els).mapAttr(['srcset', 'src'], true)
        // Also supports PICTURE which contains SOURCEs. Excluding VIDEO.
        .mapSource(false, true, false);
    }
    return els;
  }

  // https://caniuse.com/dom-manip-convenience
  // https://developer.mozilla.org/en-US/docs/Web/API/Element/replaceWith
  function webp(me) {
    if ($.webp.isSupported()) {
      return;
    }

    var sel = function (prefix) {
      prefix = prefix || '';
      // IE9 err: :not(picture img)
      return $.selector(me.options, '[' + prefix + 'srcset*=".webp"]');
    };

    var elms = $.findAll(_doc, sel());
    if (!elms.length) {
      elms = $.findAll(_doc, sel('data-'));
    }

    if (elms.length) {
      $.webp.run(elms);
    }
  }

  fn.prepare = function () {
    var me = this;

    // @todo lock it back once AJAX-loaded contents fixed.
    natively(me);

    // Runs after native set to minimize works.
    if ($.webp) {
      webp(me);
    }
  };

  return BioMedia;

});
