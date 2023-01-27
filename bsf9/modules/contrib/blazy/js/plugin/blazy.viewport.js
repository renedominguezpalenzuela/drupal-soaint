/**
 * @file
 * Provides reusable methods across lazyloaders: Bio and bLazy.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy sub-modules.
 *   It is extending dBlazy as a separate plugin.
 */

(function ($, _win, _doc) {

  'use strict';

  $.ww = 0;
  $.vp = {
    top: 0,
    right: 0,
    bottom: 0,
    left: 0
  };

  /**
   * Returns element visibility.
   *
   * @private
   *
   * @param {Object|Element} el
   *   The bounding rect object, or HTML element to test.
   * @param {Object} vp
   *   The window viewport.
   *
   * @return {bool}
   *   Returns true if visible.
   */
  function isVisible(el, vp) {
    var rect = $.isElm(el) ? $.rect(el) : el;

    return rect.right >= vp.left &&
      rect.bottom >= vp.top &&
      rect.left <= vp.right &&
      rect.top <= vp.bottom;
  }

  $.isVisible = function (e, vp) {
    var target = e.target;
    var el = target || e;
    return $.isIo ? (e.isIntersecting || e.intersectionRatio > 0) : isVisible(el, vp);
  };

  $.isResized = function (scope, e) {
    return (!!e.contentRect || !!scope.resizeTrigger || false);
  };

  /**
   * Returns viewport info.
   *
   * @private
   *
   * @param {Element} offset
   *   The offset defined via UI normally related to header fixed position.
   *
   * @return {Object}
   *   Returns the window viewport info.
   */
  function viewport(offset) {
    offset = offset || 0;
    var size = $.windowSize();
    return {
      top: 0 - offset,
      left: 0 - offset,
      bottom: size.height + offset,
      right: size.width + offset
    };
  }

  $.viewport = viewport;

  // Must be called after initViewport and updateViewport.
  $.windowData = function (opts, init) {
    var me = this;
    var offset = opts.offset || 100;
    var mobileFirst = opts.mobileFirst || false;

    if (init) {
      me.initViewport(opts);
    }

    me.ww = me.vp.right - offset;

    return {
      vp: me.vp,
      ww: me.ww,
      up: mobileFirst
    };
  };

  $.initViewport = function (opts) {
    var me = this;

    me.vp = viewport(opts.offset);

    // me.vp.top = 0 - offset;
    // me.vp.left = 0 - offset;
    return me.vp;
  };

  $.updateViewport = function (opts) {
    var me = this;
    var offset = opts.offset;

    me.vp.bottom = (_win.innerHeight || _doc.documentElement.clientHeight) + offset;
    me.vp.right = (_win.innerWidth || _doc.documentElement.clientWidth) + offset;

    return me.windowData(opts);
  };

})(dBlazy, this, this.document);
