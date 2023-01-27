/**
 * @file
 * Provides Intersection Observer API AJAX helper.
 *
 * Blazy IO works fine with AJAX, until using VIS, or alike. Adds a helper.
 * Required to fix for what Native lazy doesn't support Blur, Video, BG.
 * Similar to core responsive_image/ajax fix, only different approach.
 *
 * @todo remove once bio.js plays nice for media, VIS, blocks, or if core/once
 * fixes this type of issue when min D9.2.
 */

(function ($, Drupal) {

  'use strict';

  var _blazy = Drupal.blazy || {};
  var _ajax = Drupal.Ajax || {};
  var _proto = _ajax.prototype;
  var _revTimer;

  // Overrides Drupal.Ajax.prototype.success to re-observe new AJAX contents.
  _proto.success = (function (_ajax) {
    return function (response, status) {
      var me = _blazy.init;
      var opts;

      if (me) {
        opts = _blazy.options;

        clearTimeout(_revTimer);
        // DOM ready fix. Be sure Views "Use field template" is disabled.
        _revTimer = setTimeout(function () {
          var elms = $.findAll(document, $.selector(opts, true));
          if (elms.length) {
            // ::load() means forcing them to load at once, great for small
            // amount of items, bad for large amount.
            // ::revalidate() means re-observe newly loaded AJAX contents
            // without forcing all images to load at once, great for large, bad
            // for small.
            // Unfortunately revalidate() not always work, likely layout reflow.
            me.load(elms, true, opts);
          }
        }, 100);
      }

      return _ajax.apply(this, arguments);
    };
  })(_proto.success);

})(dBlazy, Drupal);
