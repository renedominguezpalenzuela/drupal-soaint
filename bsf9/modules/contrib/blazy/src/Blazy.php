<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Media\Placeholder;
use Drupal\blazy\Theme\BlazyAttribute;
use Drupal\blazy\Theme\Grid;
use Drupal\blazy\Utility\Check;
use Drupal\blazy\Utility\CheckItem;
use Drupal\blazy\Utility\Path;

/**
 * Provides common blazy utility and a few alias for frequent methods.
 */
class Blazy {

  // @todo remove at blazy:3.0.
  use BlazyDeprecatedTrait;

  /**
   * The blazy HTML ID.
   *
   * @var int
   */
  private static $blazyId;

  /**
   * Provides attachments when not using the provided API.
   */
  public static function attach(array &$variables, array $settings = []): void {
    if ($blazy = self::service('blazy.manager')) {
      $attachments = $blazy->attach($settings) ?: [];
      $variables['#attached'] = self::merge($attachments, $variables, '#attached');
    }
  }

  /**
   * Provides autoplay URL, relevant for lightboxes to save another click.
   */
  public static function autoplay($url): string {
    if (strpos($url, 'autoplay') === FALSE
      || strpos($url, 'autoplay=0') !== FALSE) {
      return strpos($url, '?') === FALSE
        ? $url . '?autoplay=1'
        : $url . '&autoplay=1';
    }
    return $url;
  }

  /**
   * Returns the trusted HTML ID of a single instance.
   */
  public static function getHtmlId($string = 'blazy', $id = ''): string {
    if (!isset(static::$blazyId)) {
      static::$blazyId = 0;
    }

    // Do not use dynamic Html::getUniqueId, otherwise broken AJAX.
    $id = empty($id) ? ($string . '-' . ++static::$blazyId) : $id;
    return Html::getId($id);
  }

  /**
   * Alias for Path::getPath().
   */
  public static function getPath($type, $name, $absolute = FALSE): ?string {
    return Path::getPath($type, $name, $absolute);
  }

  /**
   * Alias for Path::getLibrariesPath().
   */
  public static function getLibrariesPath($name, $base_path = FALSE): ?string {
    return Path::getLibrariesPath($name, $base_path);
  }

  /**
   * Merge data with a new one with an optional key.
   */
  public static function merge(array $data, array $element, $key = NULL): array {
    if ($key) {
      return empty($element[$key])
        ? $data : NestedArray::mergeDeep($element[$key], $data);
    }
    return empty($element)
      ? $data : NestedArray::mergeDeep($data, $element);
  }

  /**
   * Preliminary settings, normally at container/ global level.
   */
  public static function preSettings(array &$settings): void {
    self::verify($settings);

    $blazies = $settings['blazies'];
    if ($blazies->was('initialized')) {
      return;
    }

    // Checks for basic features.
    Check::container($settings);

    // Checks for lightboxes.
    Check::lightboxes($settings);

    // Checks for grids.
    Check::grids($settings);

    // Checks for Image styles, excluding Responsive image.
    BlazyImage::styles($settings);

    // Checks for lazy.
    Check::lazyOrNot($settings);

    // Marks it processed.
    $blazies->set('was.initialized', TRUE);
  }

  /**
   * Modifies the common UI settings inherited down to each item.
   */
  public static function postSettings(array &$settings = []): void {
    // Failsafe, might be called directly at ::attach() outside the workflow.
    self::verify($settings);

    $blazies = $settings['blazies'];
    if (!$blazies->was('initialized')) {
      self::preSettings($settings);
    }
  }

  /**
   * Prepares the essential settings, URI, delta, etc.
   */
  public static function prepare(array &$settings, $item = NULL, $delta = -1): void {
    CheckItem::essentials($settings, $item, $delta);

    if ($settings['blazies']->get('image.uri')) {
      CheckItem::multimedia($settings);
      CheckItem::unstyled($settings);
      CheckItem::insanity($settings);
    }
  }

  /**
   * Blazy is prepared with an URI, provides few attributes as needed.
   */
  public static function prepared(array &$attributes, array &$settings, $item = NULL): void {
    // Prepare image URL and its dimensions, including for rich-media content,
    // such as for local video poster image if a poster URI is provided.
    BlazyImage::prepare($settings, $item);

    // Build thumbnail and optional placeholder based on thumbnail.
    Placeholder::prepare($attributes, $settings);
  }

  /**
   * Preserves crucial blazy specific settings to avoid accidental overrides.
   *
   * To pass the first found Blazy formatter cherry settings into the container,
   * like Blazy Grid which lacks of options like `Media switch` or lightboxes,
   * so that when this is called at the container level, it can populate
   * lightbox gallery attributes if so configured.
   * This way at Views style, the container can have lightbox galleries without
   * extra settings, as long as `Use field template` is disabled under
   * `Style settings`, otherwise flattened out as a string.
   *
   * @see \Drupa\blazy\BlazyManagerBase::isBlazy()
   */
  public static function preserve(array &$parentsets, array &$childsets): void {
    $cherries = BlazyDefault::cherrySettings();

    foreach ($cherries as $key => $value) {
      $fallback = $parentsets[$key] ?? $value;
      // Ensures to respect parent formatter or Views style if provided.
      $parentsets[$key] = isset($childsets[$key]) && empty($fallback)
        ? $childsets[$key]
        : $fallback;
    }

    $parent = $parentsets['blazies'] ?? NULL;
    $child = $childsets['blazies'] ?? NULL;
    if ($parent && $child) {
      // $parent->set('first.settings', array_filter($child));
      // $parent->set('first.item_id', $child->get('item.id'));
      // Hints containers to build relevant lightbox gallery attributes.
      $childbox = $child->get('lightbox.name');
      $parentbox = $parent->get('lightbox.name');

      // Ensures to respect parent formatter or Views style if provided.
      // The moral of this method is only if parent lacks of settings like Grid.
      if ($childbox && !$parentbox) {
        $optionset = $child->get('lightbox.optionset', $childbox);
        $parent->set('lightbox.name', $childbox)
          ->set($childbox, $optionset)
          ->set('is.lightbox', TRUE)
          ->set('switch', $child->get('switch'));
      }

      $parent->set('first', $child->get('first'), TRUE)
        ->set('was.preserve', TRUE);
    }
  }

  /**
   * Reset the BlazySettings per item.
   */
  public static function reset(array &$settings): BlazySettings {
    self::verify($settings);

    // The settings instance must be unique per item.
    $blazies = &$settings['blazies'];
    if (!$blazies->was('reset')) {
      $blazies->reset($settings);
      $blazies->set('was.reset', TRUE);
    }

    return $blazies;
  }

  /**
   * Returns the cross-compat D8 ~ D10 app root.
   */
  public static function root($container) {
    return version_compare(\Drupal::VERSION, '9.0', '<') ? $container->get('app.root') : $container->getParameter('app.root');
  }

  /**
   * Initialize BlazySettings object for convenient, and easy organization.
   */
  public static function settings(array $data = []): BlazySettings {
    return new BlazySettings($data);
  }

  /**
   * Extracts settings from the $build.
   */
  public static function toSettings(array &$build): array {
    $settings = $build;
    if (isset($settings['settings'])) {
      $settings = &$settings['settings'];
    }

    self::verify($settings);
    return $settings;
  }

  /**
   * Returns the translated entity if available.
   */
  public static function translated($entity, $langcode): object {
    if ($langcode && $entity->hasTranslation($langcode)) {
      return $entity->getTranslation($langcode);
    }
    return $entity;
  }

  /**
   * Verify `blazies` exists, in case accessed outside the workflow.
   */
  public static function verify(array &$settings): void {
    if (!isset($settings['blazies']) && !isset($settings['inited'])) {
      $settings += BlazyDefault::htmlSettings();
    }
  }

  /**
   * Retrieves the request stack.
   *
   * @return \Symfony\Component\HttpFoundation\RequestStack
   *   The request stack.
   *
   * @todo remove for Path::requestStack() after sub-modules, if any.
   */
  public static function requestStack() {
    return self::service('request_stack');
  }

  /**
   * Retrieves the currently active route match object.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The currently active route match object.
   *
   * @todo remove for Path::routeMatch() after sub-modules, if any.
   */
  public static function routeMatch() {
    return self::service('current_route_match');
  }

  /**
   * Retrieves the stream wrapper manager service.
   *
   * @return \Drupal\Core\StreamWrapper\StreamWrapperManager
   *   The stream wrapper manager.
   *
   * @todo remove for Path::streamWrapperManager() after sub-modules.
   */
  public static function streamWrapperManager() {
    return self::service('stream_wrapper_manager');
  }

  /**
   * Returns a wrapper to pass tests, or DI where adding params is troublesome.
   */
  public static function service($service) {
    return \Drupal::hasService($service) ? \Drupal::service($service) : NULL;
  }

  /**
   * Alias for hook_config_schema_info_alter() for sub-modules.
   */
  public static function configSchemaInfoAlter(
    array &$definitions,
    $formatter = 'blazy_base',
    array $settings = []
  ): void {
    BlazyAlter::configSchemaInfoAlter($definitions, $formatter, $settings);
  }

  /**
   * Alias for BlazyAttribute::container() for sub-modules.
   */
  public static function containerAttributes(array &$attributes, array $settings): void {
    BlazyAttribute::container($attributes, $settings);
  }

  /**
   * Alias for Grid::build() for sub-modules and easy organization.
   */
  public static function grid(array $items, array $settings): array {
    return Grid::build($items, $settings);
  }

  /**
   * Alias for Grid::attributes() for sub-modules and easy organization.
   */
  public static function gridAttributes(array &$attributes, array $settings): void {
    Grid::attributes($attributes, $settings);
  }

  /**
   * Alias for BlazyFile::transformRelative() for sub-modules.
   */
  public static function transformRelative($uri, $style = NULL, array $options = []): string {
    return BlazyFile::transformRelative($uri, $style, $options);
  }

  /**
   * Alias for BlazyFile::normalizeUri() for sub-modules.
   */
  public static function normalizeUri($path): string {
    return BlazyFile::normalizeUri($path);
  }

  /**
   * Alias for BlazyFile::uri() for sub-modules.
   */
  public static function uri($item, array $settings = []): string {
    return BlazyFile::uri($item, $settings);
  }

  /**
   * Determines which lazyload to use for Slick and Splide.
   *
   * Moved it here to avoid similar issues like `is_preview` complication,
   * and other improvements: `Loading` priority, `No JavaScript: lazy`, etc.
   *
   * @todo refine this based on the new options.
   * @todo remove non configurable settings after sub-modules.
   */
  public static function which(array &$settings, $lazy, $class, $attribute): void {
    // Don't bother if empty.
    if (empty($lazy)) {
      return;
    }

    self::verify($settings);
    $blazies = $settings['blazies'];

    // Bail out if lazy load is disabled, or in sandbox mode.
    if ($blazies->is('nojs') || $blazies->is('sandboxed')) {
      return;
    }

    // Slick only knows plain old image.
    // Splide does know plain (Responsive) image, but not Picture.
    // Blazy knows more: BG, local video, remote video or iframe, (Responsive
    // |Picture) image.
    // Must be re-defined at item level to respect mixed media.
    // @todo local video, iframe, etc. are not covered at container level.
    $use_blazy = $lazy == 'blazy'
      || !empty($settings['blazy'])
      || !empty($settings['background'])
      || !empty($settings['responsive_image_style'])
      || $blazies->is('blazy')
      || $blazies->is('blur');

    // Allows Blazy to take over for advanced features above.
    $lazy = $use_blazy ? 'blazy' : $lazy;

    // Still a check in case the above does not cover, like video, iframe, etc.
    if ($use_blazy) {
      $blazies->set('is.blazy', TRUE);
    }
    else {
      $settings['lazy_class'] = $class;
      $settings['lazy_attribute'] = $attribute;

      $blazies->set('lazy.attribute', $attribute)
        ->set('lazy.class', $class);
    }

    $settings['blazy'] = $use_blazy;
    $settings['lazy'] = $lazy;

    $blazies->set('lazy.id', $lazy);
  }

}
