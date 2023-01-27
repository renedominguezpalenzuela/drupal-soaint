<?php

namespace Drupal\slick\Entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Slick configuration entity.
 */
abstract class SlickBase extends ConfigEntityBase implements SlickBaseInterface {

  /**
   * The legacy CTools ID for the configurable optionset.
   *
   * @var string
   */
  protected $name;

  /**
   * The human-readable name for the optionset.
   *
   * @var string
   */
  protected $label;

  /**
   * The weight to re-arrange the order of slick optionsets.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * The plugin instance options.
   *
   * @var array
   */
  protected $options = [];

  /**
   * Overrides Drupal\Core\Entity\Entity::id().
   */
  public function id() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions($group = NULL, $property = NULL) {
    if ($group) {
      if (is_array($group)) {
        return NestedArray::getValue($this->options, (array) $group);
      }
      elseif (isset($property) && isset($this->options[$group])) {
        return $this->options[$group][$property] ?? NULL;
      }
      return $this->options[$group] ?? NULL;
    }

    return $this->options;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings($ansich = FALSE) {
    if ($ansich && isset($this->options['settings'])) {
      return $this->options['settings'];
    }

    // With the Optimized options, all defaults are cleaned out, merge em.
    return isset($this->options['settings']) ? array_merge(self::defaultSettings(), $this->options['settings']) : self::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function setSettings(array $settings = []) {
    $this->options['settings'] = $settings;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($name) {
    return $this->getSettings()[$name] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setSetting($name, $value) {
    $this->options['settings'][$name] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings($group = 'settings') {
    $settings = self::load('default')->options[$group];
    self::removeUnsupportedSettings($settings);
    return $settings;
  }

  /**
   * Remove settings that aren't supported by the active library.
   */
  public static function removeUnsupportedSettings(array &$settings = []) {
    $library = \Drupal::config('slick.settings')->get('library');
    // The `focusOnSelect`is required to sync asNavFor, but removed. Here must
    // be kept for future fix, or less breaking changes due to different logic.
    if ($library == 'accessible-slick') {
      unset($settings['accessibility']);
      unset($settings['focusOnChange']);
    }
    else {
      unset($settings['regionLabel']);
      unset($settings['useGroupRole']);
      unset($settings['instructionsText']);
      unset($settings['useAutoplayToggleButton']);
      unset($settings['pauseIcon']);
      unset($settings['playIcon']);
      unset($settings['arrowsPlacement']);
    }
  }

  /**
   * Load the optionset with a fallback.
   */
  public static function loadWithFallback($id) {
    $optionset = self::load($id);

    // Ensures deleted optionset while being used doesn't screw up.
    if (empty($optionset)) {
      $optionset = self::load('default');
    }
    return $optionset;
  }

}
