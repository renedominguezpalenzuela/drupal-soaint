<?php

namespace Drupal\slick;

use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;

/**
 * Defines shared plugin default settings for field formatter and Views style.
 *
 * @see FormatterBase::defaultSettings()
 * @see StylePluginBase::defineOptions()
 */
class SlickDefault extends BlazyDefault {

  /**
   * {@inheritdoc}
   */
  public static function baseSettings() {
    return [
      'optionset'       => 'default',
      'override'        => FALSE,
      'overridables'    => [],
      'skin'            => '',
      'skin_arrows'     => '',
      'skin_dots'       => '',
      'use_theme_field' => FALSE,
    ] + parent::baseSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function gridSettings() {
    return [
      'preserve_keys' => FALSE,
      'visible_items' => 0,
    ] + parent::gridSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function imageSettings() {
    return [
      'optionset_thumbnail' => '',
      'skin_thumbnail'      => '',
      'thumbnail_caption'   => '',
      'thumbnail_effect'    => '',
      'thumbnail_position'  => '',
    ] + self::baseSettings() + parent::imageSettings() + self::gridSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function extendedSettings() {
    return [
      'thumbnail' => '',
    ] + self::imageSettings() + parent::extendedSettings();
  }

  /**
   * Returns filter settings.
   */
  public static function filterSettings() {
    $settings = self::imageSettings();
    $unused = self::gridSettings() + [
      'breakpoints' => [],
      'sizes'       => '',
      'grid_header' => '',
    ];
    foreach ($unused as $key => $value) {
      if (isset($settings[$key])) {
        unset($settings[$key]);
      }
    }
    return $settings;
  }

  /**
   * Returns Slick specific settings.
   */
  public static function slicks() {
    return [
      'breaking'      => FALSE,
      'display'       => 'main',
      'library'       => 'slick',
      // 'nav'           => FALSE,
      // 'navpos'        => FALSE,
      'thumbnail_uri' => '',
      'unslick'       => FALSE,
      'vanilla'       => FALSE,
      'vertical'      => FALSE,
      'vertical_tn'   => FALSE,
    ];
  }

  /**
   * Returns HTML or layout related settings to shut up notices.
   *
   * @return array
   *   The default settings.
   */
  public static function htmlSettings() {
    $items = [];
    foreach (self::slicks() as $key => $value) {
      if (is_bool($value)) {
        $items['is'][$key] = $value;
      }
      else {
        $items[$key] = $value;
      }
    }

    return [
      'slicks'    => Blazy::settings($items),
      'item_id'   => 'slide',
      'namespace' => 'slick',
      // @todo remove `+ self::slicks()`.
    ] + self::slicks()
      + self::imageSettings()
      + parent::htmlSettings();
  }

  /**
   * Defines JS options required by theme_slick(), used with optimized option.
   */
  public static function jsSettings() {
    return [
      'asNavFor'        => '',
      'downArrowTarget' => '',
      'downArrowOffset' => '',
      'lazyLoad'        => 'ondemand',
      'prevArrow'       => 'Previous',
      'nextArrow'       => 'Next',
      'pauseIcon'       => 'slick-pause-icon',
      'playIcon'        => 'slick-play-icon',
      'rows'            => 1,
      'slidesPerRow'    => 1,
      'slide'           => '',
      'slidesToShow'    => 1,
      'vertical'        => FALSE,
    ];
  }

  /**
   * Returns slick theme properties.
   */
  public static function themeProperties() {
    return [
      'attached',
      'attributes',
      'items',
      'options',
      'optionset',
      'settings',
    ];
  }

  /**
   * Returns the commonly used path, or just the base path.
   *
   * @todo remove for Blazy::getPath().
   */
  public static function getPath($type, $name, $absolute = FALSE): string {
    return Blazy::getPath($type, $name, $absolute);
  }

}
