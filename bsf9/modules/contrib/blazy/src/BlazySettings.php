<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\NestedArray;

/**
 * Provides settings object.
 *
 * @todo convert settings into BlazySettings instance at blazy:3.+ if you can.
 */
class BlazySettings implements \Countable {

  /**
   * Stores the settings.
   *
   * @var \stdClass[]
   */
  protected $storage = [];

  /**
   * Creates a new BlazySettings instance.
   *
   * @param \stdClass[] $storage
   *   The storage.
   */
  public function __construct(array $storage) {
    $this->storage = $storage;
  }

  /**
   * Counts total items.
   */
  public function count(): int {
    return count($this->storage);
  }

  /**
   * Returns values from a key.
   *
   * @param string $key
   *   The storage key.
   * @param string $default_value
   *   The storage default_value.
   *
   * @return mixed
   *   A mixed value (array, string, bool, null, etc.).
   */
  public function get($key, $default_value = NULL) {
    if (empty($key)) {
      return $this->storage;
    }

    $parts = array_map('trim', explode('.', $key));
    if (count($parts) == 1) {
      return $this->storage[$key] ?? $default_value;
    }
    else {
      $value = NestedArray::getValue($this->storage, $parts, $key_exists);
      return $key_exists ? $value : $default_value;
    }
  }

  /**
   * Returns values from a key.
   *
   * @param string $key
   *   The storage key.
   * @param string $default_value
   *   The storage default_value.
   *
   * @return mixed
   *   Normally bool, but can be mixed values (array, string, bool, null, etc.).
   */
  public function is($key, $default_value = NULL) {
    return $this->get('is.' . $key, $default_value);
  }

  /**
   * Returns TRUE if a feature identified by the key was processed.
   *
   * To verify if the expected workflow is by-passed when the key was missing.
   *
   * @param string $key
   *   The storage key.
   * @param string $default_value
   *   The storage default_value.
   *
   * @return mixed
   *   Normally bool, but can be mixed values (array, string, bool, null, etc.).
   */
  public function was($key, $default_value = NULL) {
    return $this->get('was.' . $key, $default_value);
  }

  /**
   * Sets values for a key.
   */
  public function set($key, $value = NULL, $merge = FALSE): self {
    if (is_array($key) && !isset($value)) {
      foreach ($key as $k => $v) {
        $this->storage[$k] = $v;
      }
      return $this;
    }

    $parts = array_map('trim', explode('.', $key));

    if (is_array($value) && $merge) {
      $value = array_merge((array) $this->get($key, []), $value);
    }

    if (count($parts) == 1) {
      $this->storage[$key] = $value;
    }
    else {
      NestedArray::setValue($this->storage, $parts, $value);
    }
    return $this;
  }

  /**
   * Merges data into a configuration object.
   *
   * @param array $data_to_merge
   *   An array containing data to merge.
   *
   * @return $this
   *   The configuration object.
   */
  public function merge(array $data_to_merge) {
    // Preserve integer keys so that configuration keys are not changed.
    $this->setData(NestedArray::mergeDeepArray([$this->storage, $data_to_merge], TRUE));
    return $this;
  }

  /**
   * Replaces the data of this configuration object.
   *
   * @param array $data
   *   The new configuration data.
   *
   * @return $this
   *   The configuration object.
   */
  public function setData(array $data) {
    $this->storage = $data;
    return $this;
  }

  /**
   * Removes item from this.
   *
   * @param string $key
   *   The key to unset.
   *
   * @return $this
   *   The configuration object.
   */
  public function unset($key) {
    $parts = array_map('trim', explode('.', $key));
    if (count($parts) == 1) {
      unset($this->storage[$key]);
    }
    else {
      NestedArray::unsetValue($this->storage, $parts);
    }
    return $this;
  }

  /**
   * Check if a config by its key exists.
   *
   * @param string $key
   *   The key to check.
   * @param object $group
   *   The BlazySettings as sub-key to check for.
   *
   * @return bool
   *   True if found.
   */
  public function isset($key, $group = NULL) {
    $found = FALSE;
    $parts = array_map('trim', explode('.', $key));
    if (count($parts) == 1) {
      if ($group) {
        $found = isset($group->storage()[$key]);
      }
      else {
        $found = isset($this->storage[$key]);
      }
    }
    else {
      $found = NestedArray::keyExists($parts, $this->storage);
    }
    return $found;
  }

  /**
   * Reset or renew the BlazySettings object.
   *
   * @param array $settings
   *   The settings to reset/ renew the instance.
   * @param bool $filter
   *   A flag to filter out settings.
   *
   * @return \Drupal\blazy\BlazySettings
   *   The new BlazySettings instance.
   */
  public function reset(array &$settings, $filter = FALSE): BlazySettings {
    $data = $this->storage;

    if ($filter) {
      $data = array_filter($data);
    }

    if ($this->is('debug')) {
      $this->rksort($data);
    }

    $instance = new BlazySettings($data);
    $settings['blazies'] = $instance;
    return $instance;
  }

  /**
   * Returns the whole array.
   */
  public function storage(): array {
    return $this->storage;
  }

  /**
   * Sorts recursively.
   */
  private function rksort(&$a): bool {
    if (!is_array($a)) {
      return FALSE;
    }

    ksort($a);
    foreach ($a as $k => $v) {
      $this->rksort($a[$k]);
    }
    return TRUE;
  }

}
