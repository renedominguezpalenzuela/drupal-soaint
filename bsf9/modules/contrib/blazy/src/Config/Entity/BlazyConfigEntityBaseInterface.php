<?php

namespace Drupal\blazy\Config\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides a common config entity for Slick, Splide, ElevateZoomPLus, etc.
 *
 * This will allow ElevateZoomPLus to support both Slick and Splide.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 */
interface BlazyConfigEntityBaseInterface extends ConfigEntityInterface {

  /**
   * Returns the options by group, or property.
   *
   * @param string $group
   *   The name of setting group: settings, etc.
   * @param string $property
   *   The name of specific property.
   *
   * @return mixed|array|null
   *   Available options by $group, $property, all, or NULL.
   */
  public function getOptions($group = NULL, $property = NULL);

  /**
   * Returns the array of settings.
   *
   * @param string $ansich
   *   Whether to return the settings as is.
   *
   * @return array
   *   The array of settings.
   */
  public function getSettings($ansich = FALSE);

  /**
   * Sets the array of settings.
   *
   * @param array $settings
   *   The new array of settings.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setSettings(array $settings = []);

  /**
   * Returns the value of a setting.
   *
   * @param string $setting_name
   *   The setting name.
   *
   * @return mixed
   *   The setting value.
   */
  public function getSetting($setting_name);

  /**
   * Sets the value of a setting.
   *
   * @param string $setting_name
   *   The setting name.
   * @param string $value
   *   The setting value.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setSetting($setting_name, $value);

  /**
   * Returns available default options under group 'settings'.
   *
   * @param string $group
   *   The name of group: settings, responsives.
   *
   * @return array
   *   The default settings under options.
   */
  public static function defaultSettings($group = 'settings');

}
