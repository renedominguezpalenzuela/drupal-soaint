/**
 * @file
 * Provides CSS3 flex based on Flexbox layout.
 *
 * Credit: https://fjolt.com/article/css-grthis loader id-masonry
 *
 * @todo deprecated this is worse than NativeGrid Masonry. We can't compete
 * against the fully tested Outlayer or GridStack library.
 */

(function ($, Drupal, _doc) {

  'use strict';

  var _context = _doc;
  var _id = 'block-flex';
  var _idOnce = 'b-flex';
  var _isLoading = 'is-b-loading';
  var _element = '.' + _id;
  var _max = 0;
  var _unload = false;
  var _opts = {
    $el: null
  };

  /**
   * Applies height adjustments to each item.
   *
   * @param {HTMLElement} elm
   *   The container HTML element.
   */
  function process(elm) {
    var _box = '.grid';
    var heights = {};
    var box = $.find(elm, _box);

    if (!$.isElm(box)) {
      return;
    }

    var items = $.findAll(elm, _box);

    function init() {
      var parentWith = $.rect(elm).width;
      var boxWith = $.rect(box).width;
      var style = $.computeStyle(box);
      var itemWith = boxWith + parseFloat(style.marginLeft) + parseFloat(style.marginRight);
      var columnWidth = Math.round((1 / (itemWith / parentWith)));

      var processItem = function (item, id) {
        var target = item.target;
        var isResized = $.isUnd(id);

        item = target ? $.closest(target, _box) : item;
        id = isResized ? items.indexOf(item) : id;

        var layout = function () {
          var cn = $.find(item, _box + '__content');
          if (!$.isElm(cn)) {
            return;
          }

          var cr = $.rect(cn);
          var ch = cr.height;

          if (ch < 60) {
            cr = $.rect(item);
            ch = cr.height;
          }

          if (ch < 60) {
            return;
          }

          var curColumn = id % columnWidth;
          var style = $.computeStyle(item);

          if ($.isUnd(heights[curColumn])) {
            heights[curColumn] = 0;
          }

          item.style.height = ch + 'px';
          heights[curColumn] += ch + parseFloat(style.marginBottom);

          // If the item has an item above it, then move it to fill the gap.
          if (id - columnWidth >= 0) {
            var nh = id - columnWidth + 1;
            var itemAbove = $.find(elm, _box + ':nth-of-type(' + nh + ')');
            if ($.isElm(itemAbove)) {
              var prevBottom = $.rect(itemAbove).bottom;
              var currentTop = cr.top - parseFloat(style.marginBottom);

              item.style.top = '-' + (currentTop - prevBottom) + 'px';
            }
          }
        };

        if (isResized || _unload) {
          if (_unload) {
            item.style.height = '';
            item.style.top = '';
          }

          setTimeout(layout, _unload ? 100 : 600);
        }
        else {
          layout();
        }
      };

      // Process on page load.
      $.each(items, processItem);

      function checkHeight() {
        var max = Math.max.apply(null, Object.values(heights));
        if (max < 0) {
          max = _max;
        }

        if (_unload) {
          // Prepare space to avoid jumping jack flash.
          elm.style.height = _max + 360 + 'px';
        }
        else {
          elm.style.height = max + 'px';
        }

        _max = max;
      }

      checkHeight();

      // @todo this breaks initial bricks.
      // var checkResize = function () {
      // Process on resize.
      // me.checkResize(items, processItem, elm);
      // };
    }

    setTimeout(init, _unload ? 1200 : 200);

    if (!_unload) {
      $.addClass(elm, _isLoading);
      setTimeout(function () {
        $.removeClass(elm, _isLoading);
      }, 600);
    }

    _opts.$el = elm;
    _unload = false;
  }

  /**
   * Attaches Blazy behavior to HTML element identified by .block-flex.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyFlex = {
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
