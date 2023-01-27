/**
 * @file
 * Provides reusable methods across lazyloaders: Bio and bLazy.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy sub-modules.
 *   It is extending dBlazy as a separate plugin depending on $.viewport.
 */

(function ($, _win, _doc) {

  'use strict';

  var _id = 'blazy';
  var _erCounted = 0;
  var _isVisibleClass = 'is-b-visible';
  var _data = 'data-';
  var _dataAnimation = _data + 'animation';
  var _src = 'src';
  var _srcSet = 'srcset';
  var _imgSources = [_srcSet, _src];
  var _bgClass = 'b-bg';

  $._defaults = {
    error: false,
    offset: 100,
    root: _doc,
    success: false,
    selector: '.b-lazy',
    separator: '|',
    container: false,
    containerClass: false,
    errorClass: 'b-error',
    loadInvisible: false,
    successClass: 'b-loaded',
    visibleClass: false,
    validateDelay: 25,
    saveViewportOffsetDelay: 50,

    // @todo recheck IO.module. Slick has data-lazy, and irrelevant for Blazy.
    srcset: 'data-srcset',
    src: 'data-src',
    bgClass: _bgClass,

    // IO specifics.
    isMedia: false,
    parent: '.media',
    disconnect: false,
    intersecting: false,
    observing: false,
    resizing: false,
    mobileFirst: false,
    rootMargin: '0px',
    threshold: [0]
  };

  /**
   * Checks if image or iframe is decoded/ completely loaded.
   *
   * @private
   *
   * @param {Image|Iframe} el
   *   The Image or Iframe element.
   *
   * @return {bool}
   *   True if the image or iframe is loaded.
   */
  $.isCompleted = function (el) {
    if ($.isElm(el)) {
      if ($.equal(el, 'img')) {
        return $.isDecoded(el);
      }
      if ($.equal(el, 'iframe')) {
        var doc = el.contentDocument || el.contentWindow.document;
        return doc.readyState === 'complete';
      }
    }
    return false;
  };

  function is(el, name) {
    el = el.target || el;
    return $.hasClass(el, name);
  }

  $.isBg = function (el, opts) {
    return is(el, opts && opts.bgClass || _bgClass);
  };

  $.isBlur = function (el) {
    return is(el, 'b-blur');
  };

  $.selector = function (opts, suffix) {
    var selector = opts.selector;
    // @todo recheck, troubled for onresize: + ':not(.' + opts.successClass + ')'.
    if (suffix && $.isBool(suffix)) {
      suffix = ':not(.' + opts.successClass + ')';
    }

    suffix = suffix || '';
    return selector + suffix;
  };

  $.success = function (el, status, parent, opts) {
    if ($.isFun(opts.success)) {
      opts.success(el, status, parent, opts);
    }

    if (_erCounted > 0) {
      _erCounted--;
    }
    return _erCounted;
  };

  $.error = function (el, status, parent, opts) {
    if ($.isFun(opts.error)) {
      opts.error(el, status, parent, opts);
    }

    _erCounted++;
    return _erCounted;
  };

  $.status = function (el, ok, opts) {
    // Image decode fails with Responsive image, assumes ok, no side effects.
    return this.loaded(el, ok, null, opts);
  };

  $.loaded = function (el, status, parent, opts) {
    var me = this;
    var cn = $.closest(el, opts.parent) || el;
    var ok = status === $._ok || status === true;
    var successClass = opts.successClass;
    var errorClass = opts.errorClass;
    var isLoaded = 'is-' + successClass;
    var isError = 'is-' + errorClass;

    parent = parent || cn;

    $.addClass(el, ok ? successClass : errorClass);
    // Adds context for effetcs: blur, etc. considering BG, or just media.
    $.addClass(cn, ok ? isLoaded : isError);
    $.removeClass(cn, _isVisibleClass);

    _erCounted = me[ok ? 'success' : 'error'](el, status, parent, opts);

    // Native may already remove `data-[SRC|SRCSET]` early on, except BG/Video.
    if (ok && $.hasAttr(el, _data + _src)) {
      $.removeAttr(el, _imgSources, _data);
    }

    $.trigger(el, _id + '.loaded', {
      status: status
    });

    return _erCounted;
  };

  $.loadVideo = function (el, ok, opts) {
    // Native doesn't support video, fix it.
    $.mapSource(el, _src, true);
    el.load();
    return $.status(el, ok, opts);
  };

  $.onresizing = function (scope, winData) {
    var elms = scope.elms;
    var opts = scope.options;
    // Provides a way to fix dynamic aspect ratio, etc.
    if ($.isFun(opts.resizing)) {
      opts.resizing(scope, elms, winData);
    }

    // If not extending/ overriding, also allows to listen to.
    $.trigger(_win, _id + '.resizing', {
      winData: winData,
      entries: elms
    });
  };

  $.aniElement = function (el) {
    var an = $.closest(el, '[' + _dataAnimation + ']');
    if ($.hasAttr(el, _dataAnimation) && !$.isElm(an)) {
      an = el;
    }
    return an;
  };

})(dBlazy, this, this.document);
