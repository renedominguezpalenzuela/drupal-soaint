/**
 * @file
 *
 * A launcher for responsive (remote|local) videos, Responsive|Picture images.
 */

(function ($, _d, Drupal, drupalSettings, _win, _doc) {

  'use strict';

  var _context = _doc;
  var _id = 'colorbox';
  var _idOnce = 'b-' + _id;
  var $body = $('body');
  var _element = '[data-' + _id + '-trigger]';
  var cboxTimer;

  /**
   * Blazy Colorbox utility functions.
   *
   * @param {HTMLElement} box
   *   The colorbox HTML element.
   */
  function process(box) {
    var _cbox = drupalSettings.colorbox || {};
    var $box = $(box);
    var media = $box.data('media') || {};
    var isMedia = media.type === 'video';
    var isHtml = media.type === 'rich' && 'html' in media;
    var runtimeOptions = {
      html: isHtml ? media.html : null,
      rel: media.rel || null,
      iframe: isMedia,
      title: function () {
        var $caption = $box.next('.litebox-caption');
        return $caption.length ? $caption.html() : '';
      },
      onComplete: function () {
        removeClasses();
        $body.addClass('colorbox-on colorbox-on--' + media.type);

        if (isMedia || isHtml) {
          resizeBox();
          $body.addClass(isMedia ? 'colorbox-on--media' : 'colorbox-on--html');
        }
      },
      onClosed: function () {
        var $media = $('#cboxContent').find('.media');
        if ($media.length) {
          Drupal.detachBehaviors($media[0]);
        }
        removeClasses();
      }
    };

    /**
     * Remove the custom colorbox classes.
     */
    function removeClasses() {
      $body.removeClass(function (index, css) {
        return (css.match(/(^|\s)colorbox-\S+/g) || []).join(' ');
      });
    }

    /**
     * Resize the responsive|picture image since the library doesn't get it.
     */
    function resizeImage() {
      var t = $(this);
      var w = t.width();
      var h = t.height();
      var p = t.closest('#cboxLoadedContent');
      var pw = p.width();
      var ph = p.height();

      if (h > ph) {
        t.css('top', -(h - ph) / 2);
      }
      else if (h < ph) {
        t.css({
          height: ph,
          width: 'auto'
        });
        t.css('left', -(t.width() - pw) / 2);
      }
      else if (pw > w) {
        $.colorbox.resize({
          innerWidth: w,
          innerHeight: h
        });
      }
    }

    /**
     * Resize the colorbox if any of media types (video, picture, etc.) kick in.
     */
    function resizeBox() {
      _win.clearTimeout(cboxTimer);

      var mw = _cbox.maxWidth;
      var mh = _cbox.maxHeight;

      var o = {
        width: media.width || mw,
        height: media.height || mh
      };

      // DOM ready fix.
      cboxTimer = _win.setTimeout(function () {
        if ($('#cboxOverlay').is(':visible')) {
          var $container = $('#cboxLoadedContent');
          var $iframe = $('.cboxIframe', $container);
          var $media = $('.media--ratio', $container);
          var $picture = $container.find('picture img');
          var $resimage = $container.find('img[srcset]');
          var isResimage = $resimage.length || $picture.length;

          if (isResimage) {
            var $img = $picture.length ? $picture : $resimage;
            _win.setTimeout(function () {
              $img.each(function () {
                if (this.complete) {
                  resizeImage.call(this);
                }
                else {
                  $(this).one('load', resizeImage);
                }
              });
            }, 101);

            o = {
              width: mw || media.width,
              height: mh || media.height
            };
          }

          if (!$iframe.length && $media.length) {
            Drupal.attachBehaviors($media[0]);
          }

          if ($iframe.length || $media.length) {
            // @todo consider to not use colorbox iframe for consistent .media.
            if ($iframe.length) {
              $container.addClass('media media--ratio');
              $iframe.attr('width', o.width).attr('height', o.height).addClass('media__element');
              $container.css({
                paddingBottom: (o.height / o.width) * 100 + '%',
                height: 0
              });
            }
          }
          else {
            $container.removeClass('media media--ratio');
            $container.css({
              paddingBottom: '',
              height: o.height
            }).removeClass('media__element');
          }

          $.colorbox.resize({
            innerWidth: o.width,
            innerHeight: o.height
          });
        }
      }, 10);
    }

    $box.colorbox($.extend({}, _cbox, runtimeOptions));
  }

  /**
   * Attaches blazy colorbox behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyColorbox = {
    attach: function (context) {

      var _cbox = drupalSettings.colorbox;

      // Disable Colorbox for small screens.
      if (_d.isUnd(_cbox) || _cbox.mobiledetect && _d.matchMedia(_cbox.mobiledevicewidth)) {
        return;
      }

      _context = _d.context(context);

      var elms = _d.once(process, _idOnce, _element, _context);
      if (elms.length) {
        $('#colorbox').attr('aria-label', 'color box');
      }
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        _d.once.removeSafely(_idOnce, _element, _context);
      }
    }
  };

})(jQuery, dBlazy, Drupal, drupalSettings, this, this.document);
