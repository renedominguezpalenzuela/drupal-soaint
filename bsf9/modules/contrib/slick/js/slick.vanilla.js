/**
 * @file
 * Provides Slick vanilla where options can be directly injected via data-slick.
 */

(function ($, Drupal, _d) {

  'use strict';

  var _id = 'slick-vanilla';
  var _mounted = _id + '--on';
  // @fixme typo at 3.x, should be BEM modifier: .slick--vanilla.
  var _element = '.' + _id;

  /**
   * Slick utility functions.
   *
   * @param {HTMLElement} elm
   *   The slick HTML element.
   */
  function doSlickVanilla(elm) {
    var $elm = $(elm);
    $elm.slick();
    $elm.addClass(_mounted);
  }

  /**
   * Attaches slick behavior to HTML element identified by .slick-vanilla.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.slickVanilla = {
    attach: function (context) {

      if (_d.context && _d.once.find) {
        context = _d.context(context);
        _d.once(doSlickVanilla, _id, _element, context);
      }
      else {
        // @todo remove post Blazy 2.10.
        // Weirdo: context may be null after Colorbox close.
        context = context || document;

        // jQuery may pass its object as non-expected context identified by length.
        context = 'length' in context ? context[0] : context;
        context = context instanceof HTMLDocument ? context : document;

        // Prevents potential missing due to the newly added sitewide option.
        var elms = context.querySelectorAll(_element + ':not(.' + _mounted + ')');
        if (elms.length) {
          _d.once(_d.forEach(elms, doSlickVanilla));
        }
      }
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload' && _d.once.removeSafely) {
        context = _d.context(context);
        _d.once.removeSafely(_id, _element, context);
      }
    }
  };

})(jQuery, Drupal, dBlazy);
