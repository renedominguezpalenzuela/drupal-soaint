<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\FormatterInterface;
use Drupal\editor\Entity\Editor;

/**
 * Provides hook_alter() methods for Blazy.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class BlazyAlter {

  /**
   * The blazy library info.
   *
   * @var array
   */
  private static $libraryInfoBuild;

  /**
   * Implements hook_config_schema_info_alter().
   */
  public static function configSchemaInfoAlter(
    array &$definitions,
    $formatter = 'blazy_base',
    array $settings = []
  ): void {
    if (isset($definitions[$formatter])) {
      $mappings = &$definitions[$formatter]['mapping'];
      $settings = $settings ?: BlazyDefault::extendedSettings() + BlazyDefault::gridSettings();
      $settings += BlazyDefault::deprecatedSettings();

      foreach ($settings as $key => $value) {
        // Seems double is ignored, and causes a missing schema, unlike float.
        $type = gettype($value);
        $type = $type == 'double' ? 'float' : $type;
        $mappings[$key]['type'] = $key == 'breakpoints' ? 'mapping' : (is_array($value) ? 'sequence' : $type);

        if (!is_array($value)) {
          $mappings[$key]['label'] = Unicode::ucfirst(str_replace('_', ' ', $key));
        }
      }

      // @todo remove custom breakpoints anytime before 3.x as per #3105243.
      if (isset($mappings['breakpoints'])) {
        foreach (['xs', 'sm', 'md', 'lg', 'xl'] as $breakpoint) {
          $mappings['breakpoints']['mapping'][$breakpoint]['type'] = 'mapping';
          foreach (['breakpoint', 'width', 'image_style'] as $item) {
            $mappings['breakpoints']['mapping'][$breakpoint]['mapping'][$item]['type']  = 'string';
            $mappings['breakpoints']['mapping'][$breakpoint]['mapping'][$item]['label'] = Unicode::ucfirst(str_replace('_', ' ', $item));
          }
        }
      }
    }
  }

  /**
   * Implements hook_library_info_alter().
   */
  public static function libraryInfoAlter(&$libraries, $extension): void {
    // @todo remove if core changed, right below core/drupal for being generic,
    // and dependency-free and a dependency for many other generic ones.
    // @todo watch out for core @todo to remove drupal namespace for debounce.
    $debounce = 'drupal.debounce';
    if ($extension === 'core' && isset($libraries[$debounce])) {
      $libraries[$debounce]['js']['misc/debounce.js'] = ['weight' => -16];
    }

    if ($extension === 'media' && isset($libraries['oembed.frame'])) {
      $libraries['oembed.frame']['dependencies'][] = 'blazy/oembed';
    }
  }

  /**
   * Implements hook_library_info_build().
   */
  public static function libraryInfoBuild() {
    if (!isset(static::$libraryInfoBuild)) {
      // Optional polyfills for IEs, and oldies.
      $polyfills = array_merge(BlazyDefault::polyfills(), BlazyDefault::ondemandPolyfills());
      foreach ($polyfills as $id) {
        // Matches common core polyfills' weight.
        $weight = $id == 'polyfill' ? -21 : -20;
        $weight = $id == 'webp' ? -5.5 : $weight;
        $common = ['minified' => TRUE, 'weight' => $weight];
        $libraries[$id] = [
          'js' => [
            'js/polyfill/blazy.' . $id . '.min.js' => $common,
          ],
        ];

        if ($id == 'webp') {
          $libraries[$id]['dependencies'][] = 'blazy/dblazy';
        }
      }

      // Plugins extending dBlazy.
      foreach (BlazyDefault::plugins() as $id) {
        $base = ['eventify', 'viewport', 'dataset', 'css', 'dom'];
        $base = in_array($id, $base);
        $deps = $base ? ['blazy/dblazy', 'blazy/base'] : ['blazy/xlazy'];
        if ($id == 'xlazy') {
          $deps = ['blazy/viewport', 'blazy/dataset', 'blazy/dom'];
        }

        // @todo problematic weight, basically compat must be present.
        if (in_array($id, ['animate', 'background'])) {
          $deps[] = 'blazy/compat';
        }
        $weight = $base ? -5.6 : -5.5;
        $common = ['minified' => TRUE, 'weight' => $weight];
        $libraries[$id] = [
          'js' => [
            'js/plugin/blazy.' . $id . '.min.js' => $common,
          ],
          'dependencies' => $deps,
        ];
      }

      static::$libraryInfoBuild = $libraries;
    }
    return static::$libraryInfoBuild;
  }

  /**
   * Checks if Entity/Media Embed is enabled.
   */
  public static function isCkeditorApplicable(Editor $editor): bool {
    foreach (['entity_embed', 'media_embed'] as $filter) {
      if (!$editor->isNew()
        && $editor->getFilterFormat()->filters()->has($filter)
        && $editor->getFilterFormat()
          ->filters($filter)
          ->getConfiguration()['status']) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Implements hook_ckeditor_css_alter().
   */
  public static function ckeditorCssAlter(array &$css, Editor $editor): void {
    if (self::isCkeditorApplicable($editor)) {
      $path = Blazy::getPath('module', 'blazy', TRUE);
      $css[] = $path . '/css/components/blazy.media.css';
      $css[] = $path . '/css/components/blazy.preview.css';
      $css[] = $path . '/css/components/blazy.ratio.css';
    }
  }

  /**
   * Provides the third party formatters where full blown Blazy is not worthy.
   *
   * The module doesn't automatically convert the relevant theme to use Blazy,
   * however two attributes are provided: `data-b-lazy` and `data-b-preview`
   * which can be used to override a particular theme to use Blazy.
   *
   * The `data-b-lazy`is a flag indicating Blazy is enabled.
   * The `data-b-preview` is a flag indicating Blazy in CKEditor preview mode
   * via Entity/Media Embed which normally means Blazy should be disabled
   * due to CKEditor not supporting JS assets.
   *
   * @see \Drupal\blazy\Theme\BlazyTheme::blazy()
   * @see \Drupal\blazy\Theme\BlazyTheme::field()
   * @see \Drupal\blazy\Theme\BlazyTheme::fileVideo()
   * @see blazy_preprocess_file_video()
   */
  public static function thirdPartyFormatters(): array {
    $formatters = ['file_video'];
    \blazy()->getModuleHandler()->alter('blazy_third_party_formatters', $formatters);
    return array_unique($formatters);
  }

  /**
   * Implements hook_field_formatter_third_party_settings_form().
   */
  public static function fieldFormatterThirdPartySettingsForm(FormatterInterface $plugin): array {
    if (in_array($plugin->getPluginId(), self::thirdPartyFormatters())) {
      return [
        'blazy' => [
          '#type' => 'checkbox',
          '#title' => 'Blazy',
          '#default_value' => $plugin->getThirdPartySetting('blazy', 'blazy', FALSE),
        ],
      ];
    }
    return [];
  }

  /**
   * Implements hook_field_formatter_settings_summary_alter().
   */
  public static function fieldFormatterSettingsSummaryAlter(&$summary, $context): void {
    $on = $context['formatter']->getThirdPartySetting('blazy', 'blazy', FALSE);
    if ($on && in_array($context['formatter']->getPluginId(), self::thirdPartyFormatters())) {
      $summary[] = 'Blazy';
    }
  }

  /**
   * Implements hook_blazy_settings_alter().
   *
   * @todo remove, likely no-longer relevant since sub-modules re-use the same
   * Blazy::containerAttributes() without being exclusive to `blazy` namespace
   * which was at 1.x, but not 2.x. At 2.x `blazy` is merged into the embedding
   * parent automatically making this irrelevant. Meaning CSS classes are
   * preserved by Blazy containing Views style since 2.x.
   */
  public static function blazySettingsAlter(array &$build, $items): void {
    $settings = &$build['settings'];
    $blazies = $settings['blazies'];

    // Sniffs for Views to allow block__no_wrapper, views_no_wrapper, etc.
    $function = 'views_get_current_view';
    if (is_callable($function) && $view = $function()) {

      $style = $view->style_plugin;
      $display = is_null($style) ? '' : $style->displayHandler->getPluginId();

      $name = $view->storage->id();
      $view_mode = $view->current_display;
      $plugin_id = is_null($style) ? '' : $style->getPluginId();

      $current = [
        'display'     => $display,
        'instance_id' => str_replace('_', '-', "{$name}-{$display}-{$view_mode}"),
        'name'        => $name,
        'plugin_id'   => $plugin_id,
        'view_mode'   => $view_mode,
        'is_view'     => FALSE,
      ];

      // @todo add `formatter` key if the above is proven right.
      $blazies->set('view', $current, TRUE);
    }
  }

}
