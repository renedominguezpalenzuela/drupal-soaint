/**
 * @file
 * Provides MagnificPopup integration for Image and Media fields.
 *
 * Zoom only works for plain old image, not responsive ones.
 */

(function ($, Drupal, _doc) {

  'use strict';

  var _jq = jQuery;
  var _id = 'mfp';
  var _idOnce = 'b-' + _id;
  var _dataId = 'data-' + _id;
  var _element = '[' + _dataId + '-gallery]';
  var _trigger = '[' + _dataId + '-trigger]';
  var _blazy = Drupal.blazy || {};
  var _canZoom = true;
  var _elClicked;
  var _index = 0;
  var _mp;
  var _context = _doc;

  /**
   * Blazy MagnificPopup utility functions.
   *
   * @param {HTMLElement} box
   *   The [data-mfp-gallery] container HTML element.
   */
  function process(box) {
    var elms = $.findAll(box, _trigger);
    var items = build(elms);
    var $box = $(box);

    function prepare() {
      $box.magnificPopup({
        items: items,
        gallery: {
          enabled: elms.length > 1,
          navigateByImgClick: true,
          tCounter: '%curr%/%total%'
        },
        preloader: true,
        callbacks: {
          beforeClose: function () {
            var currItem = this.currItem;
            if (currItem && currItem.inlineElement) {
              attach(currItem.inlineElement[0]);
            }
          },
          change: function () {
            checkImage(this, true);
          },
          open: function () {
            var $wrap = this.wrap;
            if ($wrap && $wrap.length) {

              // FOUC fix.
              setTimeout(function () {
                $.addClass($wrap[0], 'mfp-on');
              }, 100);
            }
          }
        },

        // This class is for CSS animation below.
        mainClass: 'mfp-with-zoom',

        // Zoom requires anything which has image: (local|remote) video, etc.
        // @todo figure out to disable zoom when having plain HTML or AJAX.
        zoom: {
          enabled: _canZoom,
          duration: 300,
          easing: 'ease-in-out',

          // The "opener" function should return the element from which popup
          // will be zoomed in and to which popup will be scaled down
          // By default it looks for an image tag:
          opener: function (openerElement) {
            checkImage(this);
            // openerElement is the element on which popup was initialized, in
            // this case its <a> tag you don't need to add "opener" option if
            // this code matches your needs, it's default one.
            // @fixme only works at first launch, not when zoom-close repeated.
            return _jq(_elClicked || openerElement.data.el);
          }
        }
      });
    }

    prepare();

    $.on(box, 'click', _trigger, function (e) {
      var el = _elClicked = e.target;

      // Supports Blazy Grid, Splide/ Slick, GridStack/Mason galleries.
      // @todo add options to avoid guessing.
      _index = $.index(el, ['.box', '.grid', '.field__item', 'li', '.slide']);

      setTimeout(function () {
        _mp = $.magnificPopup.instance;

        if (_mp) {
          _mp.goTo(_index);
        }
      });
    }, false);
  }

  function build(elms) {
    var items = [];
    var total = elms.length;

    $.each(elms, function (el, i) {
      var media = $.parse($.attr(el, 'data-media'));
      var caption = el.nextElementSibling;
      var url = $.attr(el, 'href');
      var item = {
        el: _jq(el)
      };
      var boxType = item.boxType = media.boxType;
      var src;
      var style = '';
      var width = media.width;
      var useWidth = false;

      if (boxType === 'image') {
        src = url;
        item.type = 'image';
      }
      else {
        // (Responsive|Picture) image, local video.
        if ('html' in media) {
          useWidth = boxType === 'video';
          src = media.html;
          item.type = 'inline';
        }
        else if (boxType === 'iframe') {
          useWidth = true;
          src = Drupal.theme('blazyMedia', {
            el: el
          });
          item.type = 'inline';
        }

        if (src) {
          if (width && useWidth) {
            style = ' style="width:' + width + 'px;"';
          }

          src = '<div class="mfp-html mfp-html--' + boxType + '"' + style + '><div class="mfp-inner">' + src;
          if (caption) {
            src += '<div class="mfp-bottom-bar"><div class="mfp-title">' + caption.innerHTML + '</div>' + counter((i + 1) + '/' + total) + '</div>';
          }
          src += '</div></div>';
        }
      }

      if (src) {
        item.src = src;
      }

      if (caption) {
        item.title = caption.innerHTML;
      }

      items.push(item);
    });
    return items;
  }

  function counter(text) {
    return '<div class="mfp-counter">' + text + '</div>';
  }

  // Required by zoom.
  function checkImage(mp, add) {
    var $img;
    var content = mp.content;

    if (content && content.length) {
      var el = content[0];
      var img = $.find(el, 'img');
      var exists = $.isElm(img);

      if (!exists) {
        var vid = $.find(el, 'video');
        if ($.isElm(vid)) {
          var poster = $.attr(vid, 'poster');
          if (poster) {
            img = _doc.createElement('img');
            img.decoding = 'async';
            img.src = poster;
          }
        }
      }

      exists = $.isElm(img);
      if (exists) {
        $img = mp.currItem.img = _jq(img);
        // mp.currItem.type = 'image';
        mp.currItem.hasSize = exists;
      }

      if (add) {
        if ($.hasClass(el, 'media media-wrapper mfp-html')) {
          attach(el, true);
        }
      }
    }
    return $img;
  }

  function attach(el, op) {
    var $media = $.hasClass(el, 'media') ? el : $.find(el, '.media');
    if ($.isElm($media)) {
      Drupal.detachBehaviors($media);

      if (op) {
        setTimeout(function () {
          Drupal.attachBehaviors($media);

          if (_blazy) {
            _blazy.load($media);
          }
        });
      }
    }
  }

  /**
   * Attaches blazy magnific popup behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyMagnificPopup = {
    attach: function (context) {

      _context = $.context(context);

      // Converts jQuery.magnificPopup into dBlazy for consistent vanilla JS.
      if (_jq && $.isFun(_jq.fn.magnificPopup) && !$.isFun($.fn.magnificPopup)) {
        var _mfp = _jq.fn.magnificPopup;

        $.fn.magnificPopup = function (options) {
          var me = $(_mfp.apply(this, arguments));

          if ($.isUnd($.magnificPopup)) {
            $.magnificPopup = _jq.magnificPopup;
          }

          return me;
        };
      }

      $.once(process, _idOnce, _element, _context);

    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        $.once.removeSafely(_idOnce, _element, _context);
      }
    }

  };

}(dBlazy, Drupal, this.document));
