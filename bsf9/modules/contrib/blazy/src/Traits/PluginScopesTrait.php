<?php

namespace Drupal\blazy\Traits;

use Drupal\blazy\BlazySettings;

/**
 * A Trait for plugins, common for Blazy, Splide, Slick, etc.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 */
trait PluginScopesTrait {

  /**
   * The form element scopes.
   *
   * @var array
   */
  protected $scopes = [];

  /**
   * Converts old plugin scopes array into BlazySettings object to interop.
   */
  protected function toPluginScopes(array $scopes = []): BlazySettings {
    $definitions = [];

    if (empty($scopes)) {
      return new BlazySettings($definitions);
    }

    if (isset($scopes['scopes'])) {
      $this->scopes = $scopes['scopes']->storage();
    }
    if ($this->scopes) {
      $scopes = array_merge($this->scopes, $scopes);
    }
    else {
      $this->scopes = $scopes;
    }

    foreach ($scopes as $key => $value) {
      if (is_array($value)) {
        $data[$key] = $value;
        if (isset($scopes['data'])) {
          $definitions['data'] = array_merge($scopes['data'], $data);
        }
        else {
          $definitions['data'] = $data;
        }
      }
      else {
        if (is_bool($value)) {
          $group = strpos($key, '_form') === FALSE ? 'use' : 'form';
          $key = str_replace('_form', '', $key);
          $definitions[$group][$key] = $value;
        }
        else {
          $definitions[$key] = $value;
        }
      }
    }
    return new BlazySettings($definitions);
  }

  /**
   * Modifies the specific plugin settings.
   */
  protected function pluginSettings(&$blazies, array &$settings): void {
    if ($settings['namespace'] == 'blazy') {
      $id = 'blazy';

      $blazies->set('item.id', $id)
        ->set('is.blazy', TRUE)
        ->set('lazy.id', $id)
        ->set('namespace', $id);
    }
  }

}
