/**
 * @file
 * Provides animate extension for dBlazy when using blur or animate.css.
 *
 * Alternative for native Element.animate, only with CSS animation instead.
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Element/animate
 */

(function ($, _win) {

  'use strict';

  var _1px = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
  var _ani = 'animation';
  var _blur = 'blur';
  var _bblur = 'b-' + _blur;
  var _blurKey = 'b' + _blur;
  var _blurStorage = [];
  var _data = 'data-';
  var _isStorage = _win.localStorage;

  /**
   * A simple wrapper to animate anything using animate.css.
   *
   * @param {dBlazy|Array.<Element>|Element} els
   *   The HTML element(s), or dBlazy instance.
   * @param {string|Function} cb
   *   Any custom animation name, fallbacks to [data-animation], or a callback.
   *
   * @return {Object}
   *   This dBlazy object.
   */
  function animate(els, cb) {
    var me = this;

    var chainCallback = function (el) {
      var _set = el.dataset;

      if (!$.isElm(el) || !_set) {
        return me;
      }

      var $el = $(el);
      var animation = _set.animation;

      if ($.isStr(cb)) {
        animation = cb;
      }

      if (!animation) {
        return me;
      }

      var _animated = 'animated';
      var _aniEnd = _ani + 'end.' + animation;
      var _style = el.style;
      var classes = _animated + ' ' + animation;
      var props = [
        _ani,
        _ani + '-duration',
        _ani + '-delay',
        _ani + '-iteration-count'
      ];

      $el.addClass(classes);

      $.each(['Duration', 'Delay', 'IterationCount'], function (key) {
        var _aniKey = _ani + key;
        if (_set && _aniKey in _set) {
          _style[_aniKey] = _set[_aniKey];
        }
      });

      // Supports both BG and regular image.
      var cn = $.closest(el, '.media') || el;
      var bg = $el.hasClass('b-bg');
      var isBlur = animation === _blur;
      var an = el;

      // The animated blur is image not this container, except a background.
      if (isBlur && !bg) {
        an = $.find(cn, 'img:not(.' + _bblur + ')') || an;
      }

      function ended(e) {
        $el.addClass('is-b-' + _animated)
          .removeClass(classes)
          .removeAttr(props, _data);

        $.each(props, function (key) {
          _style.removeProperty(key);
        });

        if ($.isFun(cb)) {
          cb(e);
        }

        if (isBlur) {
          var elBlur = $.find(cn, 'img.' + _bblur);
          if ($.isElm(elBlur)) {
            elBlur.src = _1px;
            $.removeAttr(elBlur, _data + _bblur);
          }
        }
      }

      return $.one(an, _aniEnd, ended, false);
    };

    return $.chain(els, chainCallback);
  }

  $.animate = animate.bind($);
  $.fn.animate = function (animation) {
    return animate(this, animation);
  };

  // https://developer.mozilla.org/en-US/docs/Web/API/HTMLCanvasElement.
  // https://caniuse.com/canvas
  function toDataUri(url, mime, cb) {
    var img = new Image();
    var load = function () {
      var me = this;

      var canvas = $.create('canvas');
      canvas.width = me.naturalWidth;
      canvas.height = me.naturalHeight;

      canvas.getContext('2d')
        .drawImage(me, 0, 0);

      cb(canvas.toDataURL(mime));
    };

    img.src = url;

    $.decode(img)
      .then(function () {
        load.call(img);
      })
      .catch(function () {
        cb(url);
      });
  }

  /**
   * Processes blur element.
   *
   * @param {Element} target
   *   The .b-lazy element, not the .b-blur one.
   */
  function blur(target) {
    var cn = $.aniElement && $.aniElement(target);
    if (!$.isElm(cn)) {
      return;
    }

    var el = $.find(cn, 'img.' + _bblur);
    if (!$.isElm(el)) {
      return;
    }

    var data = $.attr(el, _data + _bblur);
    if (!data) {
      return;
    }

    data = data.split('::');

    var shouldStore = _isStorage && data[0] === '1';
    var isDisabled = data[0] === '-1';
    var bid = data[1];
    var mime = data[2];
    var url = data[3];
    var existing = null;
    var valid = false;
    var stored = $.storage(_blurKey);

    // If the browser is capable, and the client option enabled.
    if (shouldStore) {
      var found = stored && $.contains(stored, bid);

      valid = !stored || !found;

      _blurStorage = stored ? $.parse(stored) : [];

      if (found) {
        $.each(_blurStorage, function (img) {
          var key = $.keys(img)[0];
          if (key === bid) {
            existing = img[bid];
            return false;
          }
        });
      }
    }
    else {
      // Clear, if disabled (-1), or switching to server from client-side (0).
      if (stored) {
        $.storage(_blurKey, null);
      }
    }

    // If client is disabled (-1), use server-side data URI. Clear done above.
    // Run it late, to ensure storages are cleared above as configured.
    if (isDisabled) {
      $.removeAttr(el, _data + _bblur);
      return;
    }

    // We are here when client is being enabled.
    if (existing) {
      el.src = existing;
    }
    else {
      toDataUri(url, mime, function (uri) {
        el.src = uri;

        if (shouldStore && valid) {
          var tmp = {};
          tmp[bid] = uri;

          _blurStorage.push(tmp);

          $.storage(_blurKey, JSON.stringify(_blurStorage));
        }
      });
    }
  }

  $.blur = blur.bind($);

}(dBlazy, this));
