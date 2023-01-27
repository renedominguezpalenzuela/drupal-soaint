/**
 * @file
 * Provides [Intersection|Resize]Observer extensions.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 *
 * @todo remove fallback for bLazy fork.
 */

(function ($, _win) {

  'use strict';

  // Enqueue operations.
  $.enqueue = function (queue, cb, scope) {
    $.each(queue, cb.bind(scope));
    queue.length = 0;
  };

  // @todo remove fallback for direct bLazy.
  $.initObserver = function (scope, cb, elms, withIo) {
    var opts = scope.options || {};
    var queue = scope._queue || [];
    var resizeTrigger;
    var data = 'windowData' in scope ? scope.windowData() : {};

    // Do not fill in the root, else broken. Leave it to browsers.
    var config = {
      rootMargin: opts.rootMargin || '0px',
      threshold: opts.threshold || 0
    };

    elms = $.toArray(elms);

    function _cb(entries) {
      if (!queue.length) {
        var raf = requestAnimationFrame(_enqueue);
        scope._raf.push(raf);
      }

      queue.push(entries);

      // Default to old browsers.
      return false;
    }

    function _enqueue() {
      $.enqueue(queue, cb, scope);
    }

    // IntersectionObserver for modern browsers, else degrades for IE11, etc.
    // @see https://caniuse.com/IntersectionObserver
    if (withIo) {
      var _ioObserve = function () {
        return $.isIo ? new IntersectionObserver(_cb, config) : cb.call(scope, elms);
      };

      scope.ioObserver = _ioObserve();
    }

    // IntersectionObserver for modern browsers, else degrades for IE11, etc.
    // @see https://caniuse.com/ResizeObserver
    // @see https://developer.mozilla.org/en-US/docs/Web/API/ResizeObserver
    var _roObserve = function () {
      resizeTrigger = this;

      // Called once during page load, not called during resizing.
      data = $.isUnd(data.ww) ? $.windowData(opts, true) : scope.windowData();
      return $.isRo ? new ResizeObserver(_cb) : cb.call(scope, elms);
    };

    scope.roObserver = _roObserve();
    scope.resizeTrigger = resizeTrigger;

    return data;
  };

  $.observe = function (scope, elms, withIo) {
    var opts = scope.options || {};
    var ioObserver;
    var roObserver;
    var observe = function (observer) {
      if (observer && elms && elms.length) {
        $.each(elms, function (entry) {
          observer.observe(entry);
        });
      }
    };

    ioObserver = scope.ioObserver;
    roObserver = scope.roObserver;

    if ($.isIo && (ioObserver || roObserver)) {
      // Allows observing resize only.
      if (withIo) {
        observe(ioObserver);
      }

      observe(roObserver);
    }
    else {
      // Blazy was not designed with Native lazy, can be removed via Blazy UI.
      if ('Blazy' in _win) {
        scope.bLazy = new Blazy(opts);
      }
    }
    return scope;
  };

  $.unload = function (scope) {
    var rafs = scope._raf;
    if (rafs && rafs.length) {
      $.each(rafs, function (raf) {
        cancelAnimationFrame(raf);
      });
    }
  };

})(dBlazy, this);
