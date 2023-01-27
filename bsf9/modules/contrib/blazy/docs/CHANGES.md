
***
## <a name="changes"></a>NOTABLE CHANGES
* _Blazy 2.13_, 2022/05/31:
   [#3282785](https://drupal.org/node/3282785), hotdamn fix.
* _Blazy 2.12_, 2022/05/28:
  + Regression fixes for [Optimization](https://drupal.org/node/3257511).
* _Blazy 2.11_, 2022/05/07:
  + Regression fixes for [Optimization](https://drupal.org/node/3257511).
* _Blazy 2.10_, 2022/04/16:
  + Regression fixes for [Optimization](https://drupal.org/node/3257511).
* _Blazy 2.9_, 2022/03/07:
  + [#3268089](https://drupal.org/node/3268089), hotdamn fix.
* _Blazy 2.8_, 2022/03/06:
  + Added `defer` loading as per [#3120696](https://drupal.org/node/3120696).
  + Regression fixes:
    * blur, BG.
    * [#3266748](https://drupal.org/node/3266748)
    * [#3266482](https://drupal.org/node/3266482)
* _Blazy 2.7_, 2022/02/20:
  + If you found these optimization-period releases still have oversight bugs,
    please lock it at Blazy 2.5 till the next hot fix releases. Kindly report
    any uncovered regressions, or issues for quick fixes. It is still a
    need-feedback release. Rest assured, we'll continue breaking this module
    innocently with a hiatus of used-up free-time and less buggier one, till
    this issue [Massive optimization](https://drupal.org/node/3257511) is marked
    as postponed or fixed.
    Thanks for understanding + good spirit for betterment :)
  + Added core D9.2 webp client-side fallback for those who want to support old
    browsers and want modern ones have cleaner native image markups.
  + Added `core/once` compat to save headaches and easy migration when min D9.2.
  + Added `settings.blazies` grouping for sanity and to avoid conflict with
    sub-modules till all settings converted into BlazySettings at 3+.
  + Moved media-related classes/ services into `Drupal\blazy\Media` namespace.
  + Added Magnific Popup as decent replacement for Colorbox and Photobox.
  + [Hot fix](https://drupal.org/node/3263027) for D8 `app.root` compat.
* _Blazy 2.6_, 2022/02/07:
  + [Preloading](https://drupal.org/node/3262804).
  + [Anti-pattern buffer](https://drupal.org/node/3262724).
  + Works absurdly fine at IE9 for core lazy functionality. Not fancy features
    like Blur or Animation, etc. Unless you include some polyfills on your own.
  + [Drupal 10 ready](https://drupal.org/node/3254692).
  + `dBlazy.js` is pluginized, has minimal jQuery replacement methods to DRY.
    Check out `js/components/jquery/blazy.photobox.js` for a sample.
  + `dBlazy.js` removed many old IEs fallback. Some were moved into polyfill
    which can be ditched via Blazy UI to abandon IE supports. Should you need
    to support more, please find and include polyfill into your theme globally.
  + Old bLazy is now a [fallback for IO](https://drupal.org/node/3258851) to
    have a single source of truth to minimize competitions and complications.
    Competition is great to measure survival, but not within a module codebase.
    The library is forked at Blazy 2.6, and no longer required from now on.
    Both lazyloader scripts (IO + bLazy) can be ditched via `No JavaScript`.
  + [Decoupled lazyload JavaScript](https://drupal.org/node/3257512). Now Blazy
    works without JavaScript within/without JavaScript browsers.
    Even [AMP](https://drupal.org/node/3101810) pages.
    Any javascript-related issues might no longer be valid when
    `No JavaScript lazy` enabled. Unless the exceptions are met or for those
    who still support old IEs, and cannot ditch lazyloader script, yet.
  + [Massive optimization](https://drupal.org/node/3257511). Please report any
    uncovered regressions, or issues for quick fixes. Thanks.
