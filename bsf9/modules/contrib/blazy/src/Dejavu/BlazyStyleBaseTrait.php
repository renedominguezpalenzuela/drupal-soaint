<?php

namespace Drupal\blazy\Dejavu;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Render\Markup;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;

/**
 * A Trait common for optional views style plugins.
 */
trait BlazyStyleBaseTrait {

  /**
   * The first Blazy formatter found to get data from for lightbox gallery, etc.
   *
   * @var array
   */
  protected $firstImage;

  /**
   * The dynamic html settings.
   *
   * @var array
   */
  protected $htmlSettings = [];

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * Returns the blazy manager.
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * Prepares commons settings for the style plugins.
   */
  protected function prepareSettings(array &$settings = []) {
    // Do nothing to let extenders modify.
  }

  /**
   * Provides commons settings for the style plugins.
   */
  protected function buildSettings() {
    $view      = $this->view;
    $count     = count($view->result);
    $settings  = $this->options;
    $view_name = $view->storage->id();
    $view_mode = $view->current_display;
    $plugin_id = $this->getPluginId();
    $display   = $view->style_plugin->displayHandler->getPluginId();
    $instance  = str_replace('_', '-', "{$view_name}-{$display}-{$view_mode}");
    $id        = empty($settings['id']) ? '' : $settings['id'];
    $id        = Blazy::getHtmlId("{$plugin_id}-views-{$instance}", $id);
    $settings += BlazyDefault::lazySettings();

    $this->blazyManager()->preSettings($settings);
    $this->prepareSettings($settings);
    $blazies = $settings['blazies'];

    // Prepare needed settings to work with.
    // @todo convert some to blazies, and remove these after sub-modules.
    $settings['id']           = $id;
    $settings['count']        = $count;
    $settings['instance_id']  = $instance;
    $settings['multiple']     = TRUE;
    $settings['plugin_id']    = $settings['view_plugin_id'] = $plugin_id;
    $settings['view_name']    = $view_name;
    $settings['view_display'] = $display;

    $view_info = [
      'display'     => $display,
      'instance_id' => $instance,
      'name'        => $view_name,
      'plugin_id'   => $plugin_id,
      'view_mode'   => $view_mode,
      'is_view'     => TRUE,
    ];

    $blazies->set('cache.keys', [$id, $view_mode, $count], TRUE)
      ->set('cache.tags', $view->getCacheTags() ?: [], TRUE)
      ->set('count', $count)
      ->set('css.id', $id)
      ->set('is.multiple', TRUE)
      ->set('is.views', TRUE)
      ->set('use.ajax', $view->ajaxEnabled())
      ->set('view', $view_info, TRUE);

    if (!empty($this->htmlSettings)) {
      $settings = NestedArray::mergeDeep($settings, $this->htmlSettings);
    }

    $this->blazyManager()->postSettings($settings);

    $this->blazyManager()->getModuleHandler()->alter('blazy_settings_views', $settings, $view);
    $this->blazyManager()->postSettingsAlter($settings);
    return $settings;
  }

  /**
   * Sets dynamic html settings.
   */
  protected function setHtmlSettings(array $settings = []) {
    $this->htmlSettings = $settings;
    return $this;
  }

  /**
   * Returns the first Blazy formatter found, to save image dimensions once.
   *
   * Given 100 images on a page, Blazy will call
   * ImageStyle::transformDimensions() once rather than 100 times and let the
   * 100 images inherit it as long as the image style has CROP in the name.
   */
  public function getFirstImage($row) {
    if (!isset($this->firstImage)) {
      // Fixed for Undefined property: Drupal\views\ViewExecutable::$row_index
      // by Drupal\views\Plugin\views\field\EntityField->prepareItemsByDelta.
      if (!isset($this->view->row_index)) {
        $this->view->row_index = 0;
      }

      $rendered = [];
      if ($row && $render = $this->view->rowPlugin->render($row)) {
        if (isset($render['#view']->field)
          && $fields = $render['#view']->field) {
          foreach ($fields as $field) {
            $options = isset($field->options) ? $field->options : [];
            $id = $options['plugin_id'] ?? '';
            $type = $options['type'] ?? $id;
            $switch = isset($options['media_switch']) || isset($options['settings']['media_switch']);

            if (!$type) {
              continue;
            }

            if (!empty($options['field'])
              && $switch
              && strpos($type, 'blazy') !== FALSE) {
              $name = $options['field'];
            }
          }

          if (isset($name)) {
            // Blazy Views field plugins.
            if (strpos($name, 'blazy_') !== FALSE
            && $field = ($this->view->field[$name] ?? NULL)) {
              $result['rendered'] = $field->render($row);
            }
            else {
              // Blazy, Splide, Slick, etc. field formatters.
              $result = $this->getFieldRenderable($row, 0, $name);
            }

            if ($result
              && is_array($result)
              && isset($result['rendered'])
              && !($result['rendered'] instanceof Markup)) {
              $rendered = $result['rendered']['#build'] ?? [];
            }
          }
        }
      }

      $this->firstImage = $rendered;
    }
    return $this->firstImage;
  }

  /**
   * Returns the renderable array of field containing rendered and raw data.
   */
  public function getFieldRenderable($row, $index, $field_name = '', $multiple = FALSE) {
    // Be sure to not check "Use field template" under "Style settings" to have
    // renderable array to work with, otherwise flattened string!
    if ($field = ($this->view->field[$field_name] ?? NULL)) {
      if (method_exists($field, 'getItems')) {
        $result = $field->getItems($row) ?: [];
        return empty($result) ? [] : ($multiple ? $result : $result[0]);
      }
    }

    return [];
  }

}
