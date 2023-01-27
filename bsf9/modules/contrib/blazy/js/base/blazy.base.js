/**
 * @file
 * Provides base methods to bridge drupal-related codes with generic ones.
 *
 * @todo watch out for Drupal namespace removal, likely becomes under window.
 */

(function ($, Drupal, _win) {

  'use strict';

  $.debounce = function (cb, arg, scope, delay) {
    var _cb = function () {
      cb.call(scope, arg);
    };

    Drupal.debounce(_cb, delay || 201, true);
  };

  $.matchMedia = function (width, minmax) {
    if (_win.matchMedia) {
      if ($.isUnd(minmax)) {
        minmax = 'max';
      }
      var mq = _win.matchMedia('(' + minmax + '-device-width: ' + width + ')');
      return mq.matches;
    }
    return false;
  };

})(dBlazy, Drupal, this);
