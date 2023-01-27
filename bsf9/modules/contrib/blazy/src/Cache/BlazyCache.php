<?php

namespace Drupal\blazy\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\blazy\Blazy;

/**
 * Provides common cache utility static methods.
 */
class BlazyCache {

  /**
   * Build out image, or anything related, including cache, CSS background, etc.
   */
  public static function file(array &$settings): array {
    $blazies = $settings['blazies'];

    if ($blazies->get('cache.disabled', FALSE)) {
      return [];
    }

    $caches   = [];
    $fallback = $settings['file_tags'] ?? [];
    $tags     = $blazies->get('cache.file.tags', $fallback);

    foreach (['contexts', 'keys', 'tags'] as $key) {
      if ($cache = $blazies->get('cache.' . $key)) {
        if ($key == 'tags' && $tags) {
          $cache = Cache::mergeTags($cache, $tags);
        }
        $caches[$key] = $cache;
      }
    }
    return $caches;
  }

  /**
   * Return the available lightboxes, to be cached to avoid disk lookups.
   */
  public static function lightboxes($root): array {
    $lightboxes = [];
    if (function_exists('colorbox_theme')) {
      $lightboxes[] = 'colorbox';
    }

    // @todo remove deprecated unmaintained photobox.
    // Most lightboxes are unmantained, only supports mostly used, or robust.
    $paths = [
      'photobox' => 'photobox/photobox/jquery.photobox.js',
      'mfp' => 'magnific-popup/dist/jquery.magnific-popup.min.js',
    ];

    foreach ($paths as $key => $path) {
      if (is_file($root . '/libraries/' . $path)) {
        $lightboxes[] = $key;
      }
    }
    return $lightboxes;
  }

  /**
   * Return the cache metadata common for all blazy-related modules.
   */
  public static function metadata(array $build = []): array {
    $manager  = Blazy::service('blazy.manager');
    $settings = $build['settings'] ?? $build;

    // @todo renove after sub-modules, including some fallback settings.
    Blazy::verify($settings);

    $blazies   = $settings['blazies'];
    $namespace = $settings['namespace'] ?? $blazies->get('namespace', 'blazy');
    $max_age   = $manager->configLoad('cache.page.max_age', 'system.performance');
    $max_age   = empty($settings['cache']) ? $max_age : $settings['cache'];
    $id        = $settings['id'] ?? Blazy::getHtmlId($namespace);
    $id        = $blazies->get('css.id', $id);
    $count     = $settings['count'] ?? count($settings);
    $count     = $blazies->get('count', $count);

    // Put them into cxahe.
    $cache             = [];
    $suffixes[]        = $count;
    $cache['tags']     = Cache::buildTags($namespace . ':' . $id, $suffixes, '.');
    $cache['contexts'] = ['languages'];
    $cache['max-age']  = $max_age;
    $cache['keys']     = $blazies->get('cache.keys', [$id]);

    if ($tags = $blazies->get('cache.tags', [])) {
      $cache['tags'] = Cache::mergeTags($cache['tags'], $tags);
    }

    return $cache;
  }

}
