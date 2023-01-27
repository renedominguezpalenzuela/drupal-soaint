<?php

namespace Drupal\blazy\Utility;

use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazyEntity;
use Drupal\blazy\Media\Preloader;
use Drupal\blazy\Theme\BlazyViews;
use Drupal\blazy\Theme\Grid;
use Drupal\blazy\Theme\Lightbox;

/**
 * Provides feature check methods at container level, or globally.
 *
 * @todo refine, and split them conditionally based on fields like libraries.
 * @todo remove most $settings once migrated and after sub-modules and tests.
 */
class Check {

  /**
   * Modifies asset attachments.
   *
   * @todo move it out of here for all attachments, what folder, Asset?
   */
  public static function attachments(array &$load, array &$attach = []): void {
    Blazy::postSettings($attach);

    if (!($manager = Blazy::service('blazy.manager'))) {
      return;
    }

    $blazies = $attach['blazies'];
    $unblazy = $blazies->is('unblazy', FALSE);
    $unload  = $blazies->get('ui.nojs.lazy', FALSE);

    if ($blazies->is('lightbox')) {
      Lightbox::attach($load, $attach);
    }

    // Always keep Drupal UI config to support dynamic compat features.
    $config = $manager->configLoad('blazy');
    $config['loader'] = !$unload;
    $config['unblazy'] = $unblazy;

    // One is enough due to various formatters negating each others.
    $compat = $blazies->get('libs.compat');

    // Only if `No JavaScript` option is disabled, or has compat.
    // Compat is a loader for Blur, BG, Video which Native doesn't support.
    if ($compat || !$unload) {
      if ($compat) {
        $config['compat'] = $compat;
      }

      // Modern sites may want to forget oldies, respect.
      if (!$unblazy) {
        $load['library'][] = 'blazy/blazy';
      }

      foreach (BlazyDefault::nojs() as $key) {
        if (empty($blazies->get('ui.nojs.' . $key))) {
          $lib = $key == 'lazy' ? 'load' : $key;
          $load['library'][] = 'blazy/' . $lib;
        }
      }
    }

    $load['drupalSettings']['blazy'] = $config;
    $load['drupalSettings']['blazyIo'] = $manager->getIoSettings($attach);

    if ($libs = array_filter($blazies->get('libs', []))) {
      foreach (array_keys($libs) as $lib) {
        $key = str_replace('__', '.', $lib);
        $load['library'][] = 'blazy/' . $key;
      }
    }

    // @todo remove for the above once all components are set to libs.
    foreach (BlazyDefault::components() as $component) {
      $key = str_replace('.', '__', $component);
      if ($blazies->get('libs.' . $key, FALSE)) {
        $load['library'][] = 'blazy/' . $component;
      }
    }

    // Adds AJAX helper to revalidate Blazy/ IO, if using VIS, or alike.
    // @todo remove when VIS detaches behaviors properly like IO.
    if ($blazies->get('use.ajax', FALSE)) {
      $load['library'][] = 'blazy/bio.ajax';
    }

    // Preload.
    if (!empty($attach['preload'])) {
      Preloader::preload($load, $attach);
    }
  }

  /**
   * Checks for root/ container stuffs.
   *
   * @todo remove some settings after sub-modules.
   */
  public static function container(array &$settings): void {
    $blazies      = $settings['blazies'];
    $ui           = $blazies->get('ui');
    $_loading     = $settings['loading'] ?? '';
    $loading      = $settings['loading'] = $_loading ?: 'lazy';
    $is_preview   = $settings['is_preview'] = Path::isPreview();
    $is_amp       = Path::isAmp();
    $is_sandboxed = Path::isSandboxed();
    $is_bg        = !empty($settings['background']);
    $is_unload    = !empty($ui['nojs']['lazy']);
    $is_slider    = $loading == 'slider';
    $is_unloading = $loading == 'unlazy';
    $is_defer     = $loading == 'defer';
    $is_fluid     = ($settings['ratio'] ?? '') == 'fluid';
    $is_static    = $is_preview || $is_amp || $is_sandboxed;
    $is_undata    = $is_static || $is_unloading;
    $is_nojs      = $is_unload || $is_undata;
    $bundles      = $blazies->get('field.target_bundles', []);
    $is_video     = $bundles && in_array('video', $bundles);
    $item_id      = $settings['item_id'] ?? $blazies->get('item.id', 'blazy');
    $namespace    = $settings['namespace'] ?? $blazies->get('namespace', 'blazy');
    $is_resimage  = $blazies->is('resimage')
      || is_callable('responsive_image_get_mime_type');

    // When `defer` is chosen, overrides global `No JavaScript: lazy`, ensures
    // to not affect AMP, CKEditor, or other preview pages where nojs is a must.
    if ($is_nojs && $is_defer) {
      $is_nojs = $is_undata;
    }

    // Compat is anything that Native lazy doesn't support.
    $is_compat = $is_bg
      || $is_fluid
      || $is_video
      || $is_defer
      || $blazies->get('fx')
      || $blazies->get('libs.compat');

    // Some should be refined per item against potential mixed media items.
    // @todo move some into Blazy::prepare() as might be called per item.
    $blazies->set('is.amp', $is_amp)
      ->set('is.bg', $is_bg)
      ->set('is.fluid', $is_fluid)
      ->set('is.nojs', $is_nojs)
      ->set('is.preview', $is_preview)
      ->set('is.resimage', $is_resimage)
      ->set('is.sandboxed', $is_sandboxed)
      ->set('is.slider', $is_slider)
      ->set('is.static', $is_static)
      ->set('is.undata', $is_undata)
      ->set('is.unload', $is_unload)
      ->set('is.unloading', $is_unloading)
      ->set('item.id', $item_id)
      ->set('namespace', $namespace)
      ->set('libs.background', $is_bg)
      ->set('libs.compat', $is_compat)
      ->set('libs.ratio', !empty($settings['ratio']))
      ->set('use.dataset', $is_bg || $is_video)
      ->set('use.loader', !$is_nojs)
      ->set('was.container', TRUE);
  }

  /**
   * Checks for Blazy formatter such as from within a Views style plugin.
   *
   * @see \Drupal\blazy\Blazy::preserve()
   * @see \Drupal\blazy\BlazyManagerInterface::isBlazy()
   */
  public static function blazyOrNot(array &$settings, array $data = []): void {
    // Retrieves Blazy formatter related settings from within Views style.
    if (!$blazies = $settings['blazies'] ?? NULL) {
      return;
    }

    // Allows to remove second parameter later.
    $deprecated = $settings['first_image'] ?? [];
    $data = $data ?: $blazies->get('first.data', $deprecated);
    if (empty($data) || !is_array($data)) {
      return;
    }

    // 1. Blazy formatter within Views styles by supported modules.
    $blazy   = $data['settings'] ?? [];
    $item_id = $blazies->get('item.id');
    $content = $data[$item_id] ?? $data;

    // 2. Blazy Views fields by supported modules.
    // Prevents edge case with unexpected flattened Views results which is
    // normally triggered by checking "Use field template" option.
    if (is_array($content) && ($view = ($content['#view'] ?? NULL))) {
      if ($blazy_field = BlazyViews::viewsField($view)) {
        $blazy = $blazy_field->mergedViewsSettings();
        $settings = array_merge(array_filter($blazy), array_filter($settings));
      }
    }

    // Makes this container aware of Blazy formatter it might contain.
    if ($blazy) {
      Blazy::preserve($settings, $blazy);
    }

    // No longer needed once extracted above, remove.
    $blazies->unset('first.data')
      ->set('was.blazy', TRUE);
  }

  /**
   * Checks for field formatter settings.
   *
   * @todo remove fallback settings after migration and sub-modules.
   */
  public static function fields(array &$build, $items): void {
    $settings = &$build['settings'];
    $entity   = $items->getEntity();

    BlazyEntity::settings($settings, $entity);

    $blazies    = $settings['blazies'];
    $field      = $items->getFieldDefinition();
    $field_name = $field->getName();

    // @todo remove after sub-modules.
    if (!$blazies->get('field')) {
      $blazies->set('field.name', $field->getName())
        ->set('field.type', $field->getType())
        ->set('field.entity_type', $field->getTargetEntityTypeId())
        ->set('field.view_mode', $settings['view_mode'] ?? '');
    }

    $count          = $items->count();
    $field_clean    = str_replace("field_", '', $field_name);
    $entity_type_id = $blazies->get('entity.type_id');
    $entity_id      = $blazies->get('entity.id');
    $bundle         = $blazies->get('entity.bundle');
    $view_mode      = $blazies->get('field.view_mode', 'default');
    $namespace      = $settings['namespace'] ?? $blazies->get('namespace');
    $id             = $settings['id'] ?? '';
    $gallery_id     = "{$namespace}-{$entity_type_id}-{$bundle}-{$field_clean}-{$view_mode}";
    $id             = Blazy::getHtmlId("{$gallery_id}-{$entity_id}", $id);
    $switch         = $settings['media_switch'] ?? $blazies->get('switch');

    // When alignment is mismatched, split them to satisfy linter.
    // Respects linked_field.module expectation.
    $linked    = $blazies->get('field.third_party.linked_field.linked');
    $use_field = !$blazies->is('lightbox') && $linked;

    if ($switch && $blazies->is('lightbox')) {
      $gallery_id = str_replace('_', '-', $gallery_id . '-' . $switch);
      $blazies->set('lightbox.gallery_id', $gallery_id);
    }

    $blazies->set('cache.keys', [$id, $count], TRUE);
    $blazies->set('cache.tags', [$entity_type_id . ':' . $entity_id], TRUE);

    // @todo remove.
    $settings['count'] = $count;
    $settings['id'] = $id;

    $blazies->set('count', $count)
      ->set('css.id', $id)
      ->set('use.theme_field', $use_field || !empty($settings['use_theme_field']))
      ->set('was.field', TRUE);
  }

  /**
   * Checks for grids, also supports Slick which requires no `style`.
   */
  public static function grids(array &$settings): void {
    $blazies  = $settings['blazies'];
    $has_grid = !empty($settings['grid']);
    $is_grid  = $has_grid && !empty($settings['visible_items']);
    $style    = $settings['style'] ?? NULL;
    $style    = $style ?: ($is_grid ? 'grid' : NULL);
    $is_grid  = $is_grid ?: ($style && $has_grid);
    $is_grid  = $settings['_grid'] ?? $blazies->is('grid', $is_grid);

    // Bail out early if not so configured.
    if (!$is_grid) {
      return;
    }

    $blazies->set('is.grid', $is_grid);

    if ($style) {
      foreach (BlazyDefault::grids() as $grid) {
        if ($style == $grid) {
          $key = str_replace('.', '__', $style);
          $blazies->set('libs.' . $key, $grid);
        }
      }

      // Formatters, Views style, not Filters.
      Grid::toNativeGrid($settings);
    }

    $blazies->set('was.grid', TRUE);
  }

  /**
   * Checks lazy insanity given various features/ media types + loading option.
   *
   * To address mixed media, and various options which also affect individual
   * items, see Blazy::preSettings().
   */
  public static function lazyOrNot(array &$settings): void {
    $blazies = $settings['blazies'];

    // Lazy load types: blazy, and slick: ondemand, anticipated, progressive.
    $is_blazy = $blazies->is('blazy', !empty($settings['blazy']));
    $is_blazy = $is_blazy || $blazies->is('bg') || $blazies->get('resimage.id');
    $lazy = $is_blazy ? 'blazy' : $settings['lazy'] ?? 'blazy';
    $lazy = $blazies->get('lazy.id', $lazy ?: 'blazy');
    $lazy = $blazies->is('nojs') ? '' : $lazy;
    $_attribute = $settings['lazy_attribute'] ?? NULL;
    $attribute = $_attribute ?: $blazies->get('lazy.attribute', 'src');
    $_class = $settings['lazy_class'] ?? NULL;
    $class = $_class ?: $blazies->get('lazy.class', 'b-lazy');

    // @todo re-check after sub-modules which were only aware of `is_preview`.
    // Basically tricking overrides by the reversed name due to sub-modules are
    // not updated to the new options `No JavaScript` + `Loading priority`, yet.
    // As known, Splide/ Slick have their own lazy, but might break till further
    // updates. Choosing Blazy as their lazyload method is the solution to be
    // compatible with the mentioned options. Better than sacrificing Native.
    $is_unlazy = empty($lazy);

    $blazies->set('is.blazy', $is_blazy)
      ->set('is.unlazy', $is_unlazy)
      ->set('lazy.id', $lazy)
      ->set('lazy.attribute', $attribute)
      ->set('lazy.class', $class)
      ->set('was.lazy', TRUE);
  }

  /**
   * Checks for lightboxes.
   */
  public static function lightboxes(array &$settings): void {
    $switch = $settings['media_switch'] ?? NULL;

    // Bail out early if not so configured.
    if (!$switch) {
      return;
    }

    $blazies    = $settings['blazies'];
    $lightboxes = $blazies->get('lightbox.plugins', []);
    $lightbox   = in_array($switch, $lightboxes) ? $switch : FALSE;
    $optionset  = empty($settings[$switch]) ? $switch : $settings[$switch];

    // Lightbox is unique, safe to reserve top level key:
    if ($lightbox) {
      // @todo remove settings after migration and sub-modules.
      $settings[$switch] = $optionset;

      // Allows lightboxes to provide its own optionsets, e.g.: ElevateZoomPlus.
      // With an optionset: `elevetazoomplus:responsive`.
      // Without an optionset: `colorbox:colorbox`, etc.
      $blazies->set($switch, $optionset)
        ->set('lightbox.name', $lightbox)
        ->set('lightbox.optionset', $optionset);
    }

    // Richbox is local video inside lightboxes by supported lightboxes.
    $_richbox = $settings['_richbox'] ?? $blazies->is('richbox');
    $richbox  = $blazies->get('colorbox') || $blazies->get('mfp') || $_richbox;

    // (Non-)lightboxes: media player, link to content, image rendered, etc.
    $blazies->set('switch', $switch);
    $blazies->set('libs.media', $switch == 'media');

    // @todo remove settings after migration and sub-modules.
    $settings['lightbox'] = $lightbox;
    $blazies->set('is.lightbox', !empty($lightbox))
      ->set('is.richbox', $richbox)
      ->set('was.lightbox', TRUE);
  }

  /**
   * Checks for settings alter.
   */
  public static function settingsAlter(array &$settings, $entity = NULL): void {
    $blazies = $settings['blazies'];
    $manager = Blazy::service('blazy.manager');

    // Bail out early if not so configured.
    if (!$blazies->is('lightbox') || !$manager) {
      return;
    }

    // Gallery is determined by a view, or overriden by colorbox settings.
    // Might be set by formatters or filters, but not View styles/ fields.
    $gallery_id = $blazies->get('view.instance_id');
    $gallery_id = $blazies->get('lightbox.gallery_id') ?: $gallery_id;
    $is_gallery = !empty($gallery_id);

    // Respects colorbox settings unless for an explicit field/ view gallery.
    if (!$is_gallery
      && $colorbox
      && function_exists('colorbox_theme')) {
      $is_gallery = (bool) $manager->configLoad('custom.slideshow.slideshow', 'colorbox.settings');
    }

    // Re-define based on potential hook_alter().
    if ($is_gallery) {
      $gallery_id = str_replace('_', '-', $gallery_id);
      $blazies->set('lightbox.gallery_id', $gallery_id)
        ->set('is.gallery', TRUE);
    }

    $blazies->set('entity.instance', $entity);
  }

}
