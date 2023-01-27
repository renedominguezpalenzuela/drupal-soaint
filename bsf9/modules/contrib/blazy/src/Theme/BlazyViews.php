<?php

namespace Drupal\blazy\Theme;

use Drupal\Component\Utility\NestedArray;

/**
 * Provides optional Views integration.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class BlazyViews {

  /**
   * Implements hook_views_pre_render().
   */
  public static function viewsPreRender($view): void {
    $loads = [];

    // At least, less aggressive than sitewide hook_library_info_alter().
    // @todo remove when VIS alike added `Drupal.detachBehaviors()` to their JS.
    if ($view->ajaxEnabled()) {
      $loads['library'][] = 'blazy/bio.ajax';
    }

    // Load Blazy library once, not per field, if any Blazy Views field found.
    if ($blazy = self::viewsField($view)) {
      $plugin_id = $view->getStyle()->getPluginId();
      $settings = $blazy->mergedViewsSettings();
      $load = $blazy->blazyManager()->attach($settings);
      $loads = empty($loads) ? $load : NestedArray::mergeDeep($load, $loads);

      $grid = $plugin_id == 'blazy';
      if ($options = $view->getStyle()->options) {
        $grid = empty($options['grid']) ? $grid : TRUE;
      }

      // Prevents dup [data-LIGHTBOX-gallery] if the Views style supports Grid.
      if (!$grid) {
        $view->element['#attributes'] = empty($view->element['#attributes'])
          ? [] : $view->element['#attributes'];
        BlazyAttribute::container($view->element['#attributes'], $settings);
      }
    }

    if ($loads) {
      $view->element['#attached'] = empty($view->element['#attached'])
        ? $loads : NestedArray::mergeDeep($view->element['#attached'], $loads);
    }
  }

  /**
   * Returns one of the Blazy Views fields, if available.
   */
  public static function viewsField($view) {
    foreach (['file', 'media'] as $entity) {
      if (isset($view->field['blazy_' . $entity])) {
        return $view->field['blazy_' . $entity];
      }
    }
    return FALSE;
  }

  /**
   * Implements hook_preprocess_views_view().
   */
  public static function preprocessViewsView(array &$variables, $lightboxes): void {
    preg_match('~blazy--(.*?)-gallery~', $variables['css_class'], $matches);
    $lightbox = $matches[1] ? str_replace('-', '_', $matches[1]) : FALSE;

    // Given blazy--photoswipe-gallery, adds the [data-photoswipe-gallery], etc.
    if ($lightbox && in_array($lightbox, $lightboxes)) {
      $settings['namespace'] = 'blazy';
      $settings['media_switch'] = $matches[1];
      $variables['attributes'] = empty($variables['attributes'])
        ? [] : $variables['attributes'];

      BlazyAttribute::container($variables['attributes'], $settings);
    }
  }

}
