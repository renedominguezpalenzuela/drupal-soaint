/**
 * @file
 * Provides Colorbox integration.
 */

(function ($, Drupal, _d) {

  'use strict';

  var _id = 'slick--colorbox';
  var _mounted = _id + '--on';
  var _element = '.' + _id + ':not(.' + _mounted + ')';

  /**
   * Slick Colorbox utility functions.
   *
   * @namespace
   */
  Drupal.slickColorbox = Drupal.slickColorbox || {

    context: null,

    /**
     * Sets method related to Slick methods.
     *
     * @name set
     *
     * @param {string} method
     *   The method to apply to .slick__slider element.
     */
    set: function (method) {
      var $box = $.colorbox.element();
      var $slick = $box.closest('.slick');
      var $slider = $slick.find('> .slick__slider');
      var $clone = $slider.find('.slick-cloned .litebox');
      var total = parseInt($slick.data('slickCount'), 10);
      var $counter = $('#cboxCurrent');
      var curr;

      if (!$slider.length) {
        return;
      }

      // Fixed for unwanted clones with Infinite being enabled.
      // This basically tells Colorbox to not count/ process clones.
      var attach = function (attach) {
        if ($clone.length) {
          $clone.each(function (i, box) {
            $(box)[attach ? 'addClass' : 'removeClass']('cboxElement');
            Drupal[attach ? 'attachBehaviors' : 'detachBehaviors'](box);
          });
        }
      };

      // Cannot use dataSlickIndex which maybe negative with slick clones.
      curr = Math.abs($box.closest('.slick__slide').data('delta'));
      if (isNaN(curr)) {
        curr = 0;
      }

      if (method === 'cbox_load') {
        attach(false);
      }
      else if (method === 'cbox_complete') {
        // Actually only needed at first launch, but no first launch event.
        if ($counter.length) {
          var current = drupalSettings.colorbox.current || false;
          if (current) {
            current = current.replace('{current}', (curr + 1)).replace('{total}', total);
          }
          else {
            current = Drupal.t('@curr of @total', {'@curr': (curr + 1), '@total': total});
          }
          $counter.text(current);
        }
      }
      else if (method === 'cbox_closed') {
        // DOM fix randomly weird messed up DOM (blank slides) after closing.
        window.setTimeout(function () {
          // Not consistent. This issue is somewhere, but not everywhere.
          // Fixes Firefox, IE width recalculation after closing the colorbox.
          $slider.slick('refresh');
          attach(true);
        }, 10);
      }
      else if (method === 'slickPause') {
        $slider.slick(method);
      }
    }
  };

  /**
   * Adds each slide a reliable ordinal to get correct current with clones.
   *
   * @param {HTMLElement} elm
   *   The slick HTML element.
   */
  function doSlickColorbox(elm) {
    var me = this;
    var $elm = $(elm);
    var $slide = $('.slick__slide:not(.slick-cloned)', elm);

    $slide.each(function (j, el) {
      $(el).attr('data-delta', j);
    });

    var $context = $(me.context);

    $context.on('cbox_open', function () {
      me.set('slickPause');
    });

    $context.on('cbox_load', function () {
      me.set('cbox_load');
    });

    $context.on('cbox_complete', function () {
      me.set('cbox_complete');
    });

    $context.on('cbox_closed', function () {
      me.set('cbox_closed');
    });

    $elm.attr('data-slick-count', $slide.length);
    $elm.addClass(_mounted);
  }

  /**
   * Attaches slick behavior to HTML element identified by .slick--colorbox.
   *
   * This is only relevant for when Infinite enabled identified by clones which
   * mess up Colorbox counter. Aside from Firefox, IE width recalculation issue.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.slickColorbox = {
    attach: function (context) {
      var me = Drupal.slickColorbox;

      // Weirdo: context may be null after Colorbox close.
      context = context || document;

      // jQuery may pass its object as non-expected context identified by length.
      context = 'length' in context ? context[0] : context;
      context = context instanceof HTMLDocument ? context : document;

      me.context = context;

      var elms = context.querySelectorAll(_element);
      if (elms.length) {
        _d.once(_d.forEach(elms, doSlickColorbox.bind(me)));
      }
    }
  };

}(jQuery, Drupal, dBlazy));
