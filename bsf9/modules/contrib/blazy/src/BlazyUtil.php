<?php

namespace Drupal\blazy;

use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Theme\BlazyAttribute;

/**
 * Provides internal Blazy utilities, called by SlickFilter till removed.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @todo remove this class after sub-modules anytime before 3.x.
 */
class BlazyUtil {

  /**
   * Returns the sanitized attributes for user-defined (UGC Blazy Filter).
   *
   * @todo deprecated at 2.9 and removed < 3.x. Use
   * BlazyAttribute::sanitize() instead.
   */
  public static function sanitize(array $attributes = [], $escaped = TRUE): array {
    return BlazyAttribute::sanitize($attributes, $escaped);
  }

  /**
   * Provides original unstyled image dimensions based on the given image item.
   *
   * @todo deprecated and removed < 3.x. Use BlazyImage::dimensions() instead.
   */
  public static function imageDimensions(array &$settings, $item = NULL, $initial = FALSE) {
    BlazyImage::dimensions($settings, $item, $initial);
  }

  /**
   * A wrapper for ImageStyle::transformDimensions().
   *
   * @todo deprecated and removed < 3.x. Use BlazyImage::transformDimensions()
   * instead.
   */
  public static function transformDimensions($style, array $data, $initial = FALSE) {
    return BlazyImage::transformDimensions($style, $data, $initial);
  }

  /**
   * A wrapper for ::transformRelative() to pass tests anywhere else.
   *
   * @todo deprecated at 2.5 and removed < 3.x. Use
   * BlazyFile::transformRelative() instead.
   */
  public static function transformRelative($uri, $style = NULL) {
    return BlazyFile::transformRelative($uri, $style);
  }

  /**
   * Returns the URI from the given image URL, relevant for unmanaged files.
   *
   * @todo deprecated at 2.5 and removed < 3.x. Use BlazyFile::buildUri()
   * instead.
   */
  public static function buildUri($url) {
    return BlazyFile::buildUri($url);
  }

  /**
   * Determines whether the URI has a valid scheme for file API operations.
   *
   * @todo deprecated at 2.5 and removed < 3.x. Use BlazyFile::isValidUri()
   * instead.
   */
  public static function isValidUri($uri) {
    return BlazyFile::isValidUri($uri);
  }

  /**
   * Generates an SVG Placeholder.
   *
   * @todo deprecated at 2.7 and removed < 3.x. Use Placeholder::generate().
   */
  public static function generatePlaceholder($width, $height): string {
    $width = $width ?: 100;
    $height = $height ?: 100;
    return 'data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D\'http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg\'%20viewBox%3D\'0%200%20' . $width . '%20' . $height . '\'%2F%3E';
  }

}
