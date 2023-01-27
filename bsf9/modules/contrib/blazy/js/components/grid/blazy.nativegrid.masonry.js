/**
 * @file
 * Provides CSS3 Native Grid treated as Masonry based on Grid Layout.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Grid_Layout
 * The two-dimensional Native Grid does not use JS until treated as a Masonry.
 * If you need GridStack kind, avoid inputting numeric value for Grid.
 * Below is the cheap version of GridStack.
 */

(function ($, Drupal, _doc) {

  'use strict';

  var _context = _doc;
  var _id = 'block-nativegrid';
  var _idOnce = 'b-masonry';
  var _isMasonry = 'is-' + _idOnce;
  var _isUnload = 'is-b-unload';
  var _element = '.' + _id + '.' + _isMasonry;
  var _unload = false;

  Drupal.blazy = Drupal.blazy || {};

  var _opts = {
    $el: null,
    gap: 15,
    height: 15,
    rows: 10
  };

  var _heights = [];

  /**
   * Applies the correct span to each grid item.
   *
   * @param {HTMLElement|Event} el
   *   The item HTML element, or event object on blazy.done.
   * @param {int} i
   *   The element index.
   * @param {bool} isResized
   *   If the resize event is triggered.
   */
  function processItem(el, i, isResized) {
    var target = el.target;
    var box = 'target' in el ? $.closest(target, '.grid') : el;

    if (!$.isElm(box)) {
      return;
    }

    var cn = $.find(box, '.grid__content');

    if ($.isElm(cn)) {
      if (_opts.gap === 0) {
        _opts.gap = 0.0001;
      }

      // Once setup, we rely on CSS to make it responsive.
      var layout = function () {
        _heights.push($.outerHeight(cn, true));
        var rect = $.rect(cn);
        var span = Math.ceil((rect.height + _opts.gap) / (_opts.height + _opts.gap));

        // Sets the grid row span based on content and gap height.
        box.style.gridRowEnd = 'span ' + span;

        $.addClass(box, 'is-b-grid');
        setTimeout(function () {
          cn.style.minHeight = '';
          $.addClass(box, 'is-b-layout');
        }, _unload ? 600 : 200);
      };

      if (isResized || _unload) {
        setTimeout(layout, _unload ? 300 : 200);
      }
      else {
        layout();
      }
    }
  }

  /**
   * Applies grid row end to each grid item.
   *
   * @param {HTMLElement} elm
   *   The container HTML element.
   */
  function process(elm) {
    var selector = '.grid:not(.is-b-grid)';
    // The is-b-grid is flag to not re-do with VIS, views infinite scroll/ IO.
    var items = $.findAll(elm, selector);

    var init = function () {
      var style = $.computeStyle(elm);
      var gap = style.getPropertyValue('grid-row-gap');
      var rows = style.getPropertyValue('grid-auto-rows');

      if (gap) {
        _opts.gap = parseInt(gap, 10);
      }
      if (rows) {
        _opts.height = parseInt(rows, 10);
      }

      if (items.length) {
        if (_unload) {
          $.each(items, function (item, i) {
            var cn = $.find(item, '.grid__content');
            if (cn && _heights[i]) {
              cn.style.minHeight = _heights[i] + 'px';
            }
          });
        }

        // Process on page load.
        $.each(items, processItem);

        // Process on resize.
        if (!_unload) {
          Drupal.blazy.checkResize(items, processItem, elm, processItem);
        }

      }
    };

    setTimeout(init, _unload ? 110 : 0);
    _opts.$el = elm;

    // $.addClass(elm, _isMounted);
    if (_unload) {
      $.addClass(elm, _isUnload);
    }
    _unload = false;
  }

  /**
   * Attaches Blazy behavior to HTML element identified by .block-nativegrid.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyNativeGrid = {
    attach: function (context) {

      _context = $.context(context);

      $.once(process, _idOnce, _element, _context);

    },
    detach: function (context, setting, trigger) {
      _unload = trigger === 'unload';
      if (_unload) {
        $.once.removeSafely(_idOnce, _element, _context);
      }
    }

  };

}(dBlazy, Drupal, this.document));
