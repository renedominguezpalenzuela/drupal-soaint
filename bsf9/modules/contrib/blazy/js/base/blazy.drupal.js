/**
 * @file
 * Provides shared drupal-related methods normally driven by Drupal UI options.
 *
 * Old bLazy is now IO fallback to reduce competition and complexity
 * and cross-compat better between Native and old approach (data-[SRC|SRCSET]).
 * The reason old bLazy was not designed to cope with Native, Bio is.
 * Native lazy was born (2019) after bLazy ceased 3 years before (2016).
 */

(function ($, Drupal, drupalSettings, _win, _doc) {

  'use strict';

  var _id = 'blazy';
  var _ns = 'Drupal.' + _id;
  var _data = 'data';
  var _bbg = 'b-bg';
  var _dataBg = _data + '-' + _bbg;
  var _dataRatios = _data + '-ratios';
  var _elBlur = '.b-blur';
  var _media = 'media';
  var _elMedia = '.' + _media;
  var _successClass = 'successClass';
  var _eventDone = _id + '.done';
  var _noop = function () {};
  var _extensions = {};

  /**
   * Blazy public properties and methods.
   *
   * @namespace
   */
  Drupal.blazy = {
    context: _doc,
    name: _ns,
    init: null,
    instances: [],
    resizeTick: 0,
    resizeTrigger: false,
    blazySettings: drupalSettings.blazy || {},
    ioSettings: drupalSettings.blazyIo || {},
    options: {},
    clearCompat: _noop,
    clearScript: _noop,
    checkResize: _noop,
    resizing: _noop,
    revalidate: _noop,

    // Enforced since IO (bio.js) makes bLazy a fallback internally since 2.6.
    isIo: function () {
      return true;
    },

    isBlazy: function () {
      return !$.isIo && 'Blazy' in _win;
    },

    isFluid: function (el, cn) {
      return $.equal(el.parentNode, 'picture') && $.hasAttr(cn, _dataRatios);
    },

    isLoaded: function (el) {
      return $.hasClass(el, this.options[_successClass]);
    },

    globals: function () {
      var me = this;
      var commons = {
        isMedia: true,
        success: me.clearing.bind(me),
        error: me.clearing.bind(me),
        resizing: me.resizing.bind(me),
        selector: '.b-lazy',
        parent: _elMedia,
        errorClass: 'b-error',
        successClass: 'b-loaded'
      };

      return $.extend(me.blazySettings, me.ioSettings, commons);
    },

    extend: function (plugins) {
      _extensions = $.extend({}, _extensions, plugins);
    },

    merge: function (opts) {
      var me = this;
      me.options = $.extend({}, me.globals(), me.options, opts || {});
      return me.options;
    },

    run: function (opts) {
      // @see https://www.drupal.org/project/blazy/issues/3258851
      // var els = $.findAll(_doc, '.media--ratio--fluid, .' + _bbg);
      // opts.disconnect = opts.disconnect || (!els.length && $.isUnd(Drupal.io));
      return new BioMedia(opts);
    },

    mount: function (exe) {
      var me = this;

      // This may be set by lazyload script, but not when `No JavaScript` off.
      me.merge();

      // Executes all extensions.
      if (exe) {
        $.each(_extensions, function (fn) {
          if ($.isFun(fn)) {
            fn.call(me);
          }
        });
      }

      return $.extend(me, _extensions);
    },

    selector: function (suffix) {
      suffix = suffix || '';
      var opts = this.options;
      return opts.selector + suffix + ':not(.' + opts[_successClass] + ')';
    },

    clearing: function (el) {
      // While IO has a mechanism to unobserve, bLazy not.
      if (el.bclearing) {
        return;
      }

      var me = this;
      var ie = $.hasClass(el, 'b-responsive') && $.hasAttr(el, _data + '-pfsrc');

      // Clear loading classes. Also supports future delayed Native loading.
      if ($.isFun($.unloading)) {
        $.unloading(el);
      }

      // Provides event listeners for easy overrides without full overrides.
      // Runs before native to allow native use this on its own onload event.
      $.trigger(el, _eventDone, {
        options: me.options
      });

      // With `No JavaScript` on, facilitate both parties: native vs. script.
      // This is to use the same clearing approach for all parties.
      me.clearCompat(el);
      me.clearScript(el);

      // @see http://scottjehl.github.io/picturefill/
      if (_win.picturefill && ie) {
        _win.picturefill({
          reevaluate: true,
          elements: [el]
        });
      }

      el.bclearing = true;
    },

    windowData: function () {
      return this.init ? this.init.windowData() : {};
    },

    // Only do this to fix errors, revalidation.
    load: function (cn) {
      var me = this;

      // DOM ready fix.
      _win.setTimeout(function () {
        // Filterout the failing ones.
        var elms = $.findAll(cn || _doc, me.selector());

        if (elms.length) {
          $.each(elms, me.update.bind(me));
        }
      }, 100);
    },

    update: function (el, delayed, winData) {
      var me = this;
      var opts = me.options;
      var sel = opts.selector;
      var _update = function () {
        if ($.hasAttr(el, _dataBg) && $.isFun($.bg)) {
          $.bg(el, winData || me.windowData());
        }
        else {
          if (me.init) {
            if (!$.hasClass(el, sel.substring(1))) {
              el = $.find(el, sel) || el;
            }
            me.init.load(el, true, opts);
          }
        }
      };

      delayed = delayed || false;
      if (delayed) {
        // DOM ready fix.
        _win.setTimeout(_update, 100);
      }
      else {
        _update();
      }
    },

    // Re-calculate image dimensions which may vary per breakpoint such as for
    // Masonry during resizing. When images are loaded, Flexbox or Native Grid
    // as Masonry might need info about the loaded image dimensions to calculate
    // gaps or positions. Hooking into onload event ensures dimensions correct.
    // @todo move it out to grid-related which requires this.
    rebind: function (root, cb, observer) {
      var me = this;
      var elms = $.findAll(root, me.options.selector + ':not(' + _elBlur + ')');
      var isMe = elms.length;

      if (!isMe) {
        elms = $.findAll(root, 'img:not(' + _elBlur + ')');
      }

      if (elms.length) {
        $.each(elms, function (el) {
          var type = isMe ? _eventDone : 'load';
          $.one(el, type, cb, isMe);

          if (observer) {
            observer.observe(el);
          }
        });
      }
    },

    pad: function (el, cb, delay) {
      var me = this;
      var cn = $.closest(el, _elMedia) || el;

      var check = function () {
        var pad = Math.round(((el.naturalHeight / el.naturalWidth) * 100), 2);

        // Only applies to aspect ratio fluid.
        if (me.isFluid(el, cn)) {
          cn.style.paddingBottom = pad + '%';
        }

        // Any functions which require dimensions setup: blur, bg, ratio, etc.
        if ($.isFun(cb)) {
          cb.call(me, el, cn, pad);
        }
      };

      // Fixed for effect Blur messes up Aspect ratio Fluid calculation.
      setTimeout(check, delay || 0);
    }

  };

}(dBlazy, Drupal, drupalSettings, this, this.document));
