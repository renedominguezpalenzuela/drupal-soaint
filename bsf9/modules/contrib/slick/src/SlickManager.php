<?php

namespace Drupal\slick;

use Drupal\Component\Utility\NestedArray;
use Drupal\slick\Entity\Slick;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyGrid;
use Drupal\blazy\BlazyManagerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements BlazyManagerInterface, SlickManagerInterface.
 */
class SlickManager extends BlazyManagerBase implements SlickManagerInterface {

  /**
   * The slick skin manager service.
   *
   * @var \Drupal\slick\SlickSkinManagerInterface
   */
  protected $skinManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->setSkinManager($container->get('slick.skin_manager'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderSlick', 'preRenderSlickWrapper'];
  }

  /**
   * Returns slick skin manager service.
   */
  public function skinManager() {
    return $this->skinManager;
  }

  /**
   * Sets slick skin manager service.
   */
  public function setSkinManager(SlickSkinManagerInterface $skin_manager) {
    $this->skinManager = $skin_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function attach(array $attach = []) {
    $load = parent::attach($attach);

    $this->skinManager->attach($load, $attach);

    $this->moduleHandler->alter('slick_attach', $load, $attach);
    return $load;
  }

  /**
   * {@inheritdoc}
   */
  public function slick(array $build = []) {
    foreach (SlickDefault::themeProperties() as $key) {
      $build[$key] = $build[$key] ?? [];
    }

    return empty($build['items']) ? [] : [
      '#theme'      => 'slick',
      '#items'      => [],
      '#build'      => $build,
      '#pre_render' => [[$this, 'preRenderSlick']],
    ];
  }

  /**
   * Prepare attributes for the known module features, not necessarily users'.
   */
  protected function prepareAttributes(array $build = []) {
    $settings = $build['settings'];
    $attributes = $build['attributes'] ?? [];

    if ($settings['display'] == 'main') {
      Blazy::containerAttributes($attributes, $settings);
    }
    return $attributes;
  }

  /**
   * Builds the Slick instance as a structured array ready for ::renderer().
   */
  public function preRenderSlick(array $element) {
    $build = $element['#build'];
    unset($element['#build']);

    $settings  = &$build['settings'];
    $settings += SlickDefault::htmlSettings();
    $defaults  = Slick::defaultSettings();
    $optionset = $build['optionset'];

    // Adds helper class if thumbnail on dots hover provided.
    if (!empty($settings['thumbnail_effect']) && (!empty($settings['thumbnail_style']) || !empty($settings['thumbnail']))) {
      $dots_class[] = 'slick-dots--thumbnail-' . $settings['thumbnail_effect'];
    }

    // Adds dots skin modifier class if provided.
    if (!empty($settings['skin_dots'])) {
      $dots_class[] = 'slick-dots--' . str_replace('_', '-', $settings['skin_dots']);
    }

    if (isset($dots_class)) {
      $dots_class[] = $optionset->getSetting('dotsClass') ?: 'slick-dots';
      $js['dotsClass'] = implode(" ", $dots_class);
    }

    // Handle some accessible-slick options.
    if ($settings['library'] == 'accessible-slick'
      && $optionset->getSetting('autoplay')
      && $optionset->getSetting('useAutoplayToggleButton')) {
      foreach (['pauseIcon', 'playIcon'] as $setting) {
        if ($classes = trim(strip_tags($optionset->getSetting($setting)) ?: '')) {
          if ($classes != $defaults[$setting]) {
            $js[$setting] = '<span class="' . $classes . '" aria-hidden="true"></span>';
          }
        }
      }
    }

    // Checks for breaking changes: Slick 1.8.1 - 1.9.0 / Accessible Slick.
    // @todo Remove this once the library has permanent solutions.
    if (!empty($settings['breaking'])) {
      if ($optionset->getSetting('rows') == 1) {
        $js['rows'] = 0;
      }
    }

    // Overrides common options to re-use an optionset.
    if ($settings['display'] == 'main') {
      if (!empty($settings['override'])) {
        foreach ($settings['overridables'] as $key => $override) {
          $js[$key] = empty($override) ? FALSE : TRUE;
        }
      }

      // Build the Slick grid if provided.
      if (!empty($settings['grid']) && !empty($settings['visible_items'])) {
        $build['items'] = $this->buildGrid($build['items'], $settings);
      }
    }

    $build['attributes'] = $this->prepareAttributes($build);
    $build['options'] = array_merge($build['options'], (array) ($js ?? []));

    $this->moduleHandler->alter('slick_optionset', $build['optionset'], $settings);

    foreach (SlickDefault::themeProperties() as $key) {
      $element["#$key"] = $build[$key];
    }

    return $element;
  }

  /**
   * Returns items as a grid display.
   */
  public function buildGrid(array $items = [], array &$settings = []) {
    $grids = [];

    // Enforces unslick with less items.
    if (empty($settings['unslick']) && !empty($settings['count'])) {
      $settings['unslick'] = $settings['count'] < $settings['visible_items'];
    }

    // Display all items if unslick is enforced for plain grid to lightbox.
    // Or when the total is less than visible_items.
    if (!empty($settings['unslick'])) {
      $settings['display']      = 'main';
      $settings['current_item'] = 'grid';
      $settings['count']        = 2;

      $grids[0] = $this->buildGridItem($items, 0, $settings);
    }
    else {
      // Otherwise do chunks to have a grid carousel, and also update count.
      $preserve_keys     = !empty($settings['preserve_keys']);
      $grid_items        = array_chunk($items, $settings['visible_items'], $preserve_keys);
      $settings['count'] = count($grid_items);

      foreach ($grid_items as $delta => $grid_item) {
        $grids[] = $this->buildGridItem($grid_item, $delta, $settings);
      }
    }
    return $grids;
  }

  /**
   * Returns items as a grid item display.
   */
  public function buildGridItem(array $items, $delta, array $settings = []) {
    $output = [];

    foreach ($items as $delta => $item) {
      $sets = array_merge($settings, (array) ($item['settings'] ?? []));
      $attrs = (array) ($item['attributes'] ?? []);
      $content_attrs = (array) ($item['content_attributes'] ?? []);
      $sets['current_item'] = 'grid';
      $sets['delta'] = $delta;

      unset($item['settings'], $item['attributes'], $item['content_attributes']);

      if (empty($settings['unslick'])) {
        $attrs['class'][] = 'slide__grid';
      }

      $attrs['class'][] = 'grid--' . $delta;
      foreach (['type', 'media_switch'] as $key) {
        if (!empty($sets[$key])) {
          $value = $sets[$key];
          $attrs['class'][] = 'grid--' . str_replace('_', '-', $value);
          if ($key == 'media_switch' && mb_strpos($value, 'box') !== FALSE) {
            $attrs['class'][] = 'grid--litebox';
          }
        }
      }

      $theme = empty($settings['vanilla']) ? 'slide' : 'vanilla';
      $content = [
        '#theme' => 'slick_' . $theme,
        '#item' => $item,
        '#delta' => $delta,
        '#settings' => $sets,
      ];

      $slide = [
        'content' => $content,
        'attributes' => $attrs,
        'content_attributes' => $content_attrs,
        'settings' => $sets,
      ];

      $output[$delta] = $slide;
      unset($slide);
    }

    $result = $this->grid($output, $settings);

    $result['#attributes']['class'][] = empty($settings['unslick']) ? 'slide__content' : 'slick__grid';

    $build = ['slide' => $result, 'settings' => $settings];

    $this->moduleHandler->alter('slick_grid_item', $build, $settings);
    return $build;
  }

  /**
   * Returns items as a grid display.
   *
   * @todo remove and call self::toGrid() directly post Blazy:2.10.
   */
  public function grid(array $output, array $settings): array {
    // @todo remove check post Blazy 2.10.
    if (method_exists($this, 'toGrid')) {
      return $this->toGrid($output, $settings);
    }

    return BlazyGrid::build($output, $settings);
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $build = []) {
    foreach (SlickDefault::themeProperties() as $key) {
      $build[$key] = $build[$key] ?? [];
    }

    $slick = [
      '#theme'      => 'slick_wrapper',
      '#items'      => [],
      '#build'      => $build,
      '#pre_render' => [[$this, 'preRenderSlickWrapper']],
      // Satisfy CTools blocks as per 2017/04/06: 2804165.
      'items'       => [],
    ];

    $this->moduleHandler->alter('slick_build', $slick, $build['settings']);
    return empty($build['items']) ? [] : $slick;
  }

  /**
   * Prepares js-related options.
   */
  protected function prepareOptions(
    Slick &$optionset,
    array &$options,
    array &$settings
  ) {
    $blazies    = $settings['blazies'] ?? NULL;
    $route_name = $settings['route_name'] ?? '';
    $sandboxed  = !empty($settings['is_preview']);

    if ($blazies) {
      $route_name = $blazies->get('route_name');
      $sandboxed  = $blazies->is('sandboxed');
    }

    // Disable draggable for Layout Builder UI to not conflict with UI sortable.
    $lb = $route_name && strpos($route_name, 'layout_builder.') === 0;
    if ($lb || $sandboxed) {
      $options['draggable'] = FALSE;
    }

    // Supports programmatic options defined within skin definitions to allow
    // addition of options with other libraries integrated with Slick without
    // modifying optionset such as for Zoom, Reflection, Slicebox, Transit, etc.
    if (!empty($settings['skin'])
      && $skins = $this->skinManager->getSkinsByGroup('main')) {
      if (isset($skins[$settings['skin']]['options'])) {
        $options = array_merge($options, $skins[$settings['skin']]['options']);
      }
    }

    $this->moduleHandler->alter('slick_options', $options, $settings, $optionset);

    // Disabled irrelevant options when lacking of slides.
    $this->unslick($options, $settings);
  }

  /**
   * Prepare settings for the known module features, not necessarily users'.
   */
  protected function prepareSettings(array &$element, array &$build) {
    $settings  = &$build['settings'];
    $settings += SlickDefault::htmlSettings();
    $options   = &$build['options'];

    // @todo remove check post Blazy:2.10.
    if (method_exists(Blazy::class, 'verify')) {
      Blazy::verify($settings);
    }

    $optionset = Slick::verifyOptionset($build, $settings['optionset']);
    $blazies   = $settings['blazies'] ?? NULL;
    $slicks    = $settings['slicks'];
    $id        = $settings['id'] ?? NULL;
    $id        = $settings['id'] = Blazy::getHtmlId('slick', $id);
    $thumb_id  = $id . '-thumbnail';
    $count     = $settings['count'] ?? NULL;
    $count     = $count ?: count($build['items']);

    // Additional settings.
    $wheel = $optionset->getSetting('mouseWheel');
    $nav = $slicks->is('nav', !empty($settings['nav']));
    $nav = $nav
      && (empty($settings['vanilla'])
      && !empty($settings['optionset_thumbnail'])
      && isset($build['items'][1]));
    $navpos = $settings['thumbnail_position'] ?? NULL;

    $data = [
      'library'    => $this->configLoad('library', 'slick.settings'),
      'breaking'   => $this->skinManager->isBreaking(),
      'count'      => $count,
      'nav'        => $nav,
      'navpos'     => ($nav && $navpos) ? $navpos : '',
      'vertical'   => $optionset->getSetting('vertical'),
      'mousewheel' => $wheel,
    ];

    foreach ($data as $key => $value) {
      // @todo remove settings after migration.
      $settings[$key] = $value;
      $slicks->set(is_bool($value) ? 'is.' . $key : $key, $value);
    }

    // Few dups are generic and needed by Blazy to interop Slick and Splide.
    if ($blazies) {
      $blazies->set('count', $count)
        ->set('is.nav', $slicks->is('nav'));
    }

    $options['count'] = $count;
    $this->prepareOptions($optionset, $options, $settings);

    if ($slicks->is('nav')) {
      $options['asNavFor'] = "#{$thumb_id}-slider";
      $optionset_tn = Slick::loadWithFallback($settings['optionset_thumbnail']);
      $wheel = $optionset_tn->getSetting('mouseWheel');
      $vertical_tn = $optionset_tn->getSetting('vertical');

      $build['optionset_tn'] = $optionset_tn;
      $settings['vertical_tn'] = $vertical_tn;
      $slicks->set('is.vertical_tn', $vertical_tn);
    }
    else {
      // Pass extra attributes such as those from Commerce product variations to
      // theme_slick() since we have no asNavFor wrapper here.
      if (isset($element['#attributes'])) {
        $build['attributes'] = empty($build['attributes'])
          ? $element['#attributes']
          : NestedArray::mergeDeep($build['attributes'], $element['#attributes']);
      }
    }

    // Supports Blazy multi-breakpoint or lightbox images if provided.
    // Cases: Blazy within Views gallery, or references without direct image.
    $data = $settings['first_image'] ?? [];
    $data = $blazies ? $blazies->get('first.data') : $data;
    if ($data && is_array($data)) {
      $this->isBlazy($settings, $data);
    }

    // Formatters might have checked this, but not views, nor custom works.
    // Why the formatters should check it first? It is so known to children.
    if (empty($settings['_lazy'])) {
      $optionset->whichLazy($settings);
    }

    // @tdo remove settings after migration.
    $settings['mousewheel'] = $wheel;
    $settings['down_arrow'] = $down_arrow = $optionset->getSetting('downArrow');

    $slicks->set('is.mousewheel', $wheel)
      ->set('is.down_arrow', $down_arrow);

    $attachments          = $this->attach($settings);
    $element['#settings'] = $settings;
    $element['#attached'] = empty($build['attached'])
      ? $attachments : NestedArray::mergeDeep($build['attached'], $attachments);
  }

  /**
   * Returns slick navigation with the structured array similar to main display.
   */
  protected function buildNavigation(array &$build, array $thumbs) {
    $settings = $build['settings'];
    foreach (['items', 'options', 'settings'] as $key) {
      $build[$key] = $thumbs[$key] ?? [];
    }

    $settings              = array_merge($settings, $build['settings']);
    $options               = &$build['options'];
    $settings['optionset'] = $settings['optionset_thumbnail'];
    $settings['skin']      = $settings['skin_thumbnail'];
    $settings['display']   = 'thumbnail';
    $build['optionset']    = $build['optionset_tn'];
    $build['settings']     = $settings;
    $options['asNavFor']   = "#" . $settings['id'] . '-slider';

    // Disabled irrelevant options when lacking of slides.
    $this->unslick($options, $settings);

    // The slick thumbnail navigation has the same structure as the main one.
    unset($build['optionset_tn']);
    return $this->slick($build);
  }

  /**
   * One slick_theme() to serve multiple displays: main, overlay, thumbnail.
   */
  public function preRenderSlickWrapper($element) {
    $build = $element['#build'];
    unset($element['#build']);

    // Prepare settings and assets.
    $this->prepareSettings($element, $build);

    // Checks if we have thumbnail navigation.
    $thumbs   = $build['thumb'] ?? [];
    $settings = $build['settings'];
    $slicks   = $settings['slicks'];

    // Prevents unused thumb going through the main display.
    unset($build['thumb']);

    // Build the main Slick.
    $slick[0] = $this->slick($build);

    // Build the thumbnail Slick.
    if ($slicks->is('nav') && $thumbs) {
      $slick[1] = $this->buildNavigation($build, $thumbs);
    }

    // Reverse slicks if thumbnail position is provided to get CSS float work.
    if ($slicks->get('navpos')) {
      $slick = array_reverse($slick);
    }

    // Collect the slick instances.
    $element['#items'] = $slick;
    $element['#cache'] = $this->getCacheMetadata($build);

    unset($build);
    return $element;
  }

  /**
   * Provides a shortcut to attach skins only if required.
   */
  public function attachSkin(array &$load, $attach = []) {
    $this->skinManager->attachSkin($load, $attach);
  }

  /**
   * Returns slick skins registered via SlickSkin plugin, or defaults.
   *
   * @todo TBD; deprecate this at slick:8.x-3.0 for slick:9.x-1.0.
   */
  public function getSkins() {
    return $this->skinManager->getSkins();
  }

  /**
   * Returns available slick skins by group.
   *
   * @todo TBD; deprecate this at slick:8.x-3.0 for slick:9.x-1.0.
   */
  public function getSkinsByGroup($group = '', $option = FALSE) {
    return $this->skinManager->getSkinsByGroup($group, $option);
  }

  /**
   * Disabled irrelevant options when lacking of slides, unslick softly.
   *
   * Unlike `settings.unslick`, this doesn't destroy the markups so that
   * `settings.unslick` can be overriden as needed unless being forced.
   */
  private function unslick(array &$options, array $settings) {
    $slicks = $settings['slicks'];
    if ($slicks->get('count') < 2) {
      $options['arrows'] = FALSE;
      $options['dots'] = FALSE;
      $options['draggable'] = FALSE;
      $options['infinite'] = FALSE;
    }
  }

}
