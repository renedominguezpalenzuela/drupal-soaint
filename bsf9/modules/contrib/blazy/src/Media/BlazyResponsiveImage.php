<?php

namespace Drupal\blazy\Media;

use Drupal\Core\Cache\Cache;
use Drupal\blazy\Blazy;
use Drupal\blazy\Theme\BlazyAttribute;

/**
 * Provides responsive image utilities.
 *
 * @todo recap similiraties and make them plugins.
 */
class BlazyResponsiveImage {

  /**
   * The Responsive image styles.
   *
   * @var array
   */
  private static $styles;

  /**
   * Retrieves the breakpoint manager.
   *
   * @return \Drupal\breakpoint\BreakpointManager
   *   The breakpoint manager.
   */
  public static function breakpointManager() {
    return Blazy::service('breakpoint.manager');
  }

  /**
   * Makes Responsive image usable as CSS background image sources.
   *
   * This is per item dependent on URI, the self::dimensions() is global.
   *
   * @todo use resimage.dimensions once BlazyFormatter + BlazyFilter synced,
   * and Picture are checked with its multiple dimensions aka art direction.
   */
  public static function background(array &$attributes, array &$settings): void {
    $blazies = $settings['blazies'];
    $resimage = $blazies->get('resimage.style');

    if (empty($settings['background']) || !$resimage) {
      return;
    }

    if ($styles = self::styles($resimage)) {
      $srcset = $ratios = [];
      $ratios = $blazies->get('ratios', []);

      foreach (array_values($styles['styles']) as $style) {
        $styled = array_merge($settings, BlazyImage::transformDimensions($style, $settings, FALSE));

        // Sort image URLs based on width.
        $data = BlazyImage::background($styled, $style);
        $srcset[$styled['width']] = $data;
        $ratios[$styled['width']] = $data['ratio'];
      }

      // Sort the srcset from small to large image width or multiplier.
      ksort($srcset);
      ksort($ratios);

      // Prevents NestedArray from making these indices.
      $blazies->set('bgs', (object) $srcset)
        ->set('ratios', $ratios)
        ->set('image.ratio', end($ratios));

      // To make compatible with old bLazy (not Bio) which expects no 1px
      // for [data-src], else error, provide a real smallest image. Bio will
      // map it to the current breakpoint later.
      $bg = reset($srcset);
      $unlazy = $blazies->is('undata');
      $old_url = $blazies->get('image.url', $settings['image_url'] ?? '');
      $new_url = $unlazy ? $old_url : $bg['src'];

      // @todo remove.
      // $settings['image_url'] = $new_url;
      $blazies->set('is.unlazy', $unlazy)
        ->set('image.url', $new_url);

      BlazyAttribute::lazy($attributes, $settings);
    }
  }

  /**
   * Sets dimensions once to reduce method calls for Responsive image.
   *
   * Do not limit to preload or fluid, to re-use this for background, etc.
   *
   * @requires Drupal\blazy\Media\Preloader::prepare()
   */
  public static function dimensions(
    array &$settings,
    $resimage = NULL,
    $initial = TRUE
  ): void {
    $blazies = $settings['blazies'];
    $dimensions = $blazies->get('resimage.dimensions', []);
    $resimage = $resimage ?: $blazies->get('resimage.style');

    if ($dimensions || !$resimage) {
      return;
    }

    $styles = self::styles($resimage);
    $names = $ratios = [];

    foreach (array_values($styles['styles']) as $style) {
      $styled = BlazyImage::transformDimensions($style, $settings, $initial);

      // In order to avoid layout reflow, we get dimensions beforehand.
      $width = $styled['width'];
      $height = $styled['height'];

      // @todo merge ratios into dimensions elsewhere.
      $names[$width] = $style->id();
      $ratios[$width] = $ratio = BlazyImage::ratio($styled);
      $dimensions[$width] = [
        'width' => $width,
        'height' => $height,
        'ratio' => $ratio,
      ];
    }

    // Sort the srcset from small to large image width or multiplier.
    ksort($dimensions);
    ksort($names);
    ksort($ratios);

    // Informs individual images that dimensions are already set once.
    // Dynamic aspect ratio is useless without JS.
    $blazies->set('resimage.dimensions', $dimensions)
      ->set('is.dimensions', TRUE)
      ->set('image.ratio', end($ratios))
      ->set('ratios', $ratios)
      ->set('resimage.ids', array_values($names));

    if (!$blazies->get('image.width')) {
      $blazies->set('image', end($dimensions), TRUE);
    }

    // Currently only needed by Preload.
    if ($initial && $resimage && !empty($settings['preload'])) {
      self::sources($settings, $resimage);
    }
  }

  /**
   * Defines the Responsive image id, styles and caches tags.
   */
  public static function define(&$blazies, $resimage) {
    $id = $resimage->id();
    $styles = self::styles($resimage);

    $blazies->set('resimage.id', $id)
      ->set('resimage.caches', $styles['caches'] ?? []);
  }

  /**
   * Returns the Responsive image styles and caches tags.
   *
   * @param object $resimage
   *   The responsive image style entity.
   *
   * @return array|mixed
   *   The responsive image styles and cache tags.
   */
  public static function styles($resimage): array {
    $id = $resimage->id();

    if (!isset(static::$styles[$id])) {
      $cache_tags = $resimage->getCacheTags();
      $image_styles = \blazy()->entityLoadMultiple('image_style', $resimage->getImageStyleIds());

      foreach ($image_styles as $image_style) {
        $cache_tags = Cache::mergeTags($cache_tags, $image_style->getCacheTags());
      }

      static::$styles[$id] = [
        'caches' => $cache_tags,
        'names' => array_keys($image_styles),
        'styles' => $image_styles,
      ];
    }
    return static::$styles[$id];
  }

  /**
   * Modifies fallback image style.
   *
   * Tasks:
   * - Replace core `data:image` GIF with SVG or custom placeholder due to known
   *   issues with GIF, see #2795415. And Views rewrite results, see #2908861.
   * - Provide URL, URI, style from a non-empty fallback, also for Blur, etc.
   *
   * @todo deprecate this when `Image style` has similar `_empty image_` option
   * to reduce complication at Blazy UI, and here.
   */
  public static function fallback(array &$settings, $placeholder): void {
    $blazies = $settings['blazies'];
    $id = '_empty image_';
    $width = $height = 1;
    $data_src = $placeholder;

    // If not enabled via UI, by default, always 1px, or the custom Placeholder.
    // Image style will be prioritized as fallback to have different fallbacks
    // per field relevant for various aspect ratios rather than the one and only
    // fallback for the entire site via Responsive image UI.
    if ($blazies->get('ui.one_pixel') || !empty($settings['image_style'])) {
      return;
    }

    // Mimicks private _responsive_image_image_style_url, #3119527.
    if ($resimage = $blazies->get('resimage.style')) {
      $fallback = $resimage->getFallbackImageStyle();

      if ($fallback == $id) {
        $data_src = $placeholder;
      }
      else {
        $id = $fallback;
        if ($blazy = Blazy::service('blazy.manager')) {
          $uri = $blazies->get('image.uri');

          // @todo use dimensions based on the chosen fallback.
          if ($uri && $style = $blazy->entityLoad($id, 'image_style')) {
            $data_src = BlazyFile::transformRelative($uri, $style);
            $tn_uri = $style->buildUri($uri);

            [
              'width' => $width,
              'height' => $height,
            ] = BlazyImage::transformDimensions($style, $settings);

            $blazies->set('resimage.fallback.style', $style);
            $blazies->set('resimage.fallback.uri', $tn_uri);

            // Prevents double downloadings.
            $placeholder = Placeholder::generate($width, $height);
            if (empty($settings['thumbnail_style'])) {
              $settings['thumbnail_style'] = $id;
            }
          }
        }
      }

      $blazies->set('resimage.fallback.url', $data_src);
    }

    if ($data_src) {
      // The controller `data-src` attribute, might be valid image thumbnail.
      $blazies->set('image.url', $data_src);
      $blazies->set('placeholder.id', $id);
      // The controller `src` attribute, the placeholder: 1px or thumbnail.
      $blazies->set('placeholder.url', $placeholder);
      $blazies->set('placeholder.width', $width);
      $blazies->set('placeholder.height', $height);
    }
  }

  /**
   * Converts settings.responsive_image_style to its entity.
   *
   * Unlike Image style, Responsive image style requires URI detection per item
   * to determine extension which should not use image style, else BOOM:
   * "This image style can not be used for a responsive image style mapping
   * using the 'sizes' attribute. in
   * responsive_image_build_source_attributes() (line 386...".
   *
   * @requires `unstyled` defined
   */
  public static function toStyle(array $settings, $unstyled = FALSE): ?object {
    $blazies    = $settings['blazies'];
    $exist      = $blazies->is('resimage');
    $_style     = $settings['responsive_image_style'] ?? NULL;
    $multiple   = $blazies->is('multistyle');
    $applicable = $exist && $_style;
    $style      = $blazies->get('resimage.style');

    // Multiple is a flag for various styles: Blazy Filter, GridStack, etc.
    // While fields can only have one image style per field.
    if ($applicable && $blazy = Blazy::service('blazy.manager')) {
      if (!$unstyled && (!$style || $multiple)) {
        $style = $blazy->entityLoad($_style, 'responsive_image_style');
      }
    }

    // @todo remove settings after migration and sub-modules.
    return $style ?: ($settings['resimage'] ?? NULL);
  }

  /**
   * Provides Responsive image sources relevant for link preload.
   *
   * @see self::dimensions()
   */
  private static function sources(array &$settings, $style = NULL): array {
    if (!($manager = self::breakpointManager())) {
      return [];
    }

    $blazies = $settings['blazies'];
    if ($sources = $blazies->get('resimage.sources', [])) {
      return $sources;
    }

    $style = $style ?: $blazies->get('resimage.style');
    if (!$style) {
      return [];
    }

    $func = function ($uri) use ($manager, $settings, $blazies, $style) {
      $fallback = NULL;
      $sources = $variables = [];
      $dimensions = $blazies->get('resimage.dimensions', []);
      $end = end($dimensions);

      $variables['uri'] = $uri;
      foreach (['width', 'height'] as $key) {
        $variables[$key] = $end[$key] ?? $settings[$key] ?? NULL;
      }

      $id = $style->getFallbackImageStyle();
      $breakpoints = array_reverse($manager
        ->getBreakpointsByGroup($style->getBreakpointGroup()));
      $function = '_responsive_image_build_source_attributes';
      if (is_callable($function)) {
        $fallback = \_responsive_image_image_style_url($id, $variables['uri']);
        foreach ($style->getKeyedImageStyleMappings() as $bid => $multipliers) {
          if (isset($breakpoints[$bid])) {
            $sources[] = $function($variables, $breakpoints[$bid], $multipliers);
          }
        }
      }

      $blazies->set('resimage.fallback.id', $id)
        ->set('resimage.fallback.url', $fallback);

      return empty($sources) ? [] : [
        'items' => $sources,
        'fallback' => $fallback,
      ];
    };

    $output = [];
    // The URIs are extracted by Preloader::prepare().
    if ($images = $blazies->get('images')) {
      // Preserves indices even if empty to have correct mixed media elsewhere.
      foreach ($images as $image) {
        $uri = $image['uri'] ?? NULL;
        $output[] = empty($uri) ? [] : $func($uri);
      }
    }

    $blazies->set('resimage.sources', $output);

    return $output;
  }

}
