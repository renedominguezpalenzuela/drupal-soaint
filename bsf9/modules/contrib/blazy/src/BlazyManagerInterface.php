<?php

namespace Drupal\blazy;

/**
 * Defines re-usable services and functions for blazy plugins.
 *
 * @todo move some non-media methods into BlazyInterface at 3.x, or before.
 */
interface BlazyManagerInterface {

  /**
   * Returns array of needed assets suitable for #attached property.
   *
   * @param array $attach
   *   The settings which determine what library to attach.
   *
   * @return array
   *   The supported libraries.
   */
  public function attach(array $attach = []);

  /**
   * Returns cached data identified by its cache ID, normally alterable data.
   *
   * @param string $cid
   *   The cache ID, als used for the hook_alter.
   * @param array $data
   *   The given data to cache.
   * @param bool $reset
   *   Whether to re-fetch in case not cached yet.
   * @param string $alter
   *   The specific alter for the hook_alter, otherwise $cid.
   * @param array $context
   *   The optional context or info for the hook_alter.
   *
   * @return array
   *   The cache data.
   */
  public function getCachedData(
    $cid,
    array $data = [],
    $reset = FALSE,
    $alter = NULL,
    array $context = []
  ): array;

  /**
   * Returns the supported image effects.
   *
   * @return array
   *   The supported image effects.
   */
  public function getImageEffects(): array;

  /**
   * Returns drupalSettings for IO.
   *
   * @param array $attach
   *   The settings which determine what library to attach.
   *
   * @return object
   *   The supported IO drupalSettings.
   */
  public function getIoSettings(array $attach = []): object;

  /**
   * Alias for Blazy::getLibrariesPath() to get libraries path.
   *
   * @param string $name
   *   The library name.
   * @param bool $base_path
   *   Whether to prefix it with an a base path, deprecated.
   *
   * @return string
   *   The path to library or NULL if not found.
   */
  public function getLibrariesPath($name, $base_path = FALSE): ?string;

  /**
   * Gets the supported lightboxes.
   *
   * @return array
   *   The supported lightboxes.
   */
  public function getLightboxes(): array;

  /**
   * Alias for Blazy::getPath() to get module or theme path.
   *
   * @param string $type
   *   The object type, can be module or theme.
   * @param string $name
   *   The object name.
   * @param bool $absolute
   *   Whether to return an absolute path.
   *
   * @return string
   *   The path to object or NULL if not found.
   */
  public function getPath($type, $name, $absolute = FALSE): ?string;

  /**
   * Provides alterable display styles.
   *
   * @return array
   *   The supported display styles.
   */
  public function getStyles(): array;

  /**
   * Checks for Blazy formatter such as from within a Views style plugin.
   *
   * Ensures the settings traverse up to the container where Blazy is clueless.
   * This allows Blazy Grid, or other Views styles, lacking of UI, to have
   * additional settings extracted from the first Blazy formatter found.
   * Such as media switch/ lightbox. This way the container can add relevant
   * attributes to its container, etc. Also applies to entity references where
   * Blazy is not the main formatter, instead embedded as part of the parent's.
   *
   * This fairly complex logic is intended to reduce similarly complex logic at
   * individual item. But rather than at individual item, it is executed once
   * at the container level. If you have 100 images, this method is executed
   * once, not 100x, as long as you have all image styles cropped, not scaled.
   *
   * Since 2.7 [data-blazy] is just identifier for blazy container, can be empty
   * or used to pass optional JavaScript settings. It used to store aspect
   * ratios, but hardly used, due to complication with Picture which may have
   * irregular aka art-direction aspect ratios.
   *
   * This still needs improvements and a little more simplified version.
   *
   * @param array $settings
   *   The settings being modified.
   * @param array $item
   *   The first item containing settings or item keys.
   *
   * @see \Drupal\blazy\BlazyManager::prepareBuild()
   * @see \Drupal\blazy\Field\BlazyEntityVanillaBase::buildElements()
   */
  public function isBlazy(array &$settings, array $item = []): void;

  /**
   * Prepares shared data common between field formatter and views field.
   *
   * This is to overcome the limitation of self::postSettings().
   *
   * @param array $build
   *   The build data containing settings, etc.
   * @param object $entity
   *   The entity related to the formatter, or views field.
   */
  public function prepareData(array &$build, $entity = NULL): void;

  /**
   * Prepare base preliminary settings.
   *
   * The `fx` sequence: hook_alter > formatters (not implemented yet) > UI.
   * The `_fx` is a special flag such as to temporarily disable till needed.
   * Called by field formatters, views [styles|fields via BlazyEntity],
   * [blazy|splide|slick] filters.
   *
   * @param array $settings
   *   The settings being modified.
   */
  public function preSettings(array &$settings): void;

  /**
   * Modifies the post settings inherited down to each item.
   *
   * @param array $settings
   *   The settings being modified.
   */
  public function postSettings(array &$settings): void;

  /**
   * Returns items wrapped by theme_item_list(), can be a grid, or plain list.
   *
   * Alias for Blazy::grid() for sub-modules and easy organization later.
   *
   * @param array $items
   *   The grid items.
   * @param array $settings
   *   The given settings.
   *
   * @return array
   *   The modified array of grid items.
   */
  public function toGrid(array $items, array $settings): array;

}
