/**
 * @file
 * Provides a few disposable polyfills till IE is gone from planet earth.
 *
 * Supports for webp is landed at D9.2. This file relies on core/picturefill
 * which is always included as core/responsive_image polifyll as per 2022/2.
 * This file is a client-side solution, with advantage clean native image markup
 * since it doesn't change IMG into PICTURE till required by old browsers, as
 * alt for HTML/ server-side solutions:
 *   - https://www.drupal.org/project/webp
 *   - https://www.drupal.org/project/imageapi_optimize_webp
 *
 * @see https://www.drupal.org/node/3171135
 * @see https://www.drupal.org/project/drupal/issues/3213491
 * @todo remove if picturefill suffices. FWIW, IE9 works fine with picturefill
 * w/o this fallback. Not tested against other oldies, Safari, etc. So included,
 * but can be ditched as usual via Blazy UI if not needed at all.
 */

(function ($, _win, _doc) {

  'use strict';

  if ($.webp) {
    return;
  }

  var _key = 'bwebp';
  var _dataSrcset = 'data-srcset';
  var _picture = 'picture';
  var _mimeWebp = 'image/webp';
  var _source = 'source';
  var pf = _win.picturefill;

  function isSupported() {
    var support = true;

    // Ensures not locked down when Responsive image is not present, yet.
    // @todo use $.decode for better async.
    if (pf) {
      var check = $.storage(_key);

      if (!$.isNull(check)) {
        return check === 'true';
      }

      // Undefined means supported, due to !pf.supPicture check.
      support = $.isUnd(pf._.supportsType(_mimeWebp));
      $.storage(_key, support);
    }

    return support;
  }

  function markup(img, webps, nowebps, dataset) {
    if (!$.isElm(img)) {
      return false;
    }
    var picture = $.create(_picture);
    var source = $.create(_source);
    var sizes = $.attr(img, 'sizes');
    var webpSrc = webps.join(',').trim();
    var nowebpSrc = nowebps.join(',').trim();
    var check = $.find(picture, _source);

    if (!$.isElm(check)) {
      if (dataset) {
        $.attr(source, _dataSrcset, webpSrc);
        $.attr(img, _dataSrcset, nowebpSrc);
      }
      else {
        source.srcset = webpSrc;
        img.srcset = nowebpSrc;
      }

      if (sizes) {
        source.sizes = sizes;
      }

      source.type = _mimeWebp;

      $.append(picture, source);
      $.append(picture, img);
    }

    return picture;
  }

  function convert(el) {
    var img = _doc.importNode(el, true);
    var webps = [];
    var nowebps = [];
    var dataset = $.attr(img, _dataSrcset);
    var scrset = $.attr(img, 'srcset');

    if (scrset.length || dataset.length) {
      scrset = scrset.length ? scrset : dataset;

      if (scrset.length) {
        $.each(scrset.split(','), function (src) {
          if ($.contains(src, '.webp')) {
            webps.push(src);
          }
          else {
            nowebps.push(src);
          }
        });

        if (webps.length) {
          return markup(img, webps, nowebps, dataset.length);
        }
      }
    }
    return false;
  }

  $.webp = {
    isSupported: isSupported,

    run: function (elms) {
      if (isSupported() || !elms.length) {
        return;
      }

      $.each(elms, function (el) {
        var isImg = $.equal(el, 'img');
        var pic = $.closest(el, _picture);

        if (isImg && $.isNull(pic)) {
          var parent = $.closest(el, '.media') || el.parentNode;
          var picture = convert(el, true);

          if (picture) {
            // Cannot use parent.replaceWith because this is for old browsers.
            // Nor parent.replaceChild(picture, el); due to various features.
            $.append(parent, picture);
            $.remove(el);
          }
        }
      });
    }
  };

})(dBlazy, this, this.document);
