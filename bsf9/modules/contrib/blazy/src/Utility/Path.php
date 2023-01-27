<?php

namespace Drupal\blazy\Utility;

use Drupal\blazy\Blazy;

/**
 * Provides urtl, route, request, stream, or any path-related methods.
 */
class Path {

  /**
   * The AMP page.
   *
   * @var bool
   */
  private static $isAmp;

  /**
   * The preview mode to disable Blazy where JS is not available, or useless.
   *
   * @var bool
   */
  private static $isPreview;

  /**
   * The preview mode to disable interactive elements.
   *
   * @var bool
   */
  private static $isSandboxed;

  /**
   * Retrieves the file url generator service.
   *
   * @return \Drupal\Core\File\FileUrlGenerator
   *   The file url generator.
   *
   * @see https://www.drupal.org/node/2940031
   */
  public static function fileUrlGenerator() {
    return Blazy::service('file_url_generator');
  }

  /**
   * Retrieves the path resolver.
   *
   * @return \Drupal\Core\Extension\ExtensionPathResolver
   *   The path resolver.
   */
  public static function pathResolver() {
    return Blazy::service('extension.path.resolver');
  }

  /**
   * Retrieves the request stack.
   *
   * @return \Symfony\Component\HttpFoundation\RequestStack
   *   The request stack.
   */
  public static function requestStack() {
    return Blazy::service('request_stack');
  }

  /**
   * Retrieves the currently active route match object.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The currently active route match object.
   */
  public static function routeMatch() {
    return Blazy::service('current_route_match');
  }

  /**
   * Retrieves the stream wrapper manager service.
   *
   * @return \Drupal\Core\StreamWrapper\StreamWrapperManager
   *   The stream wrapper manager.
   */
  public static function streamWrapperManager() {
    return Blazy::service('stream_wrapper_manager');
  }

  /**
   * Retrieves the request.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   *
   * @see https://github.com/symfony/symfony/blob/6.0/src/Symfony/Component/HttpFoundation/Request.php
   */
  public static function request() {
    if ($stack = self::requestStack()) {
      return $stack->getCurrentRequest();
    }
    return NULL;
  }

  /**
   * Returns the commonly used path, or just the base path.
   *
   * @todo remove drupal_get_path check when min D9.3.
   */
  public static function getPath($type, $name, $absolute = FALSE): ?string {
    if ($resolver = self::pathResolver()) {
      $path = $resolver->getPath($type, $name);
    }
    else {
      $function = 'drupal_get_path';
      $path = is_callable($function) ? $function($type, $name) : '';
    }
    return $absolute ? \base_path() . $path : $path;
  }

  /**
   * Provides a wrapper to replace deprecated libraries_get_path() at ease.
   */
  public static function getLibrariesPath($name, $base_path = FALSE): ?string {
    if ($finder = Blazy::service('library.libraries_directory_file_finder')) {
      return $finder->find($name);
    }

    $function = 'libraries_get_path';
    return is_callable($function) ? $function($name, $base_path) : '';
  }

  /**
   * Checks if Blazy is in CKEditor preview mode where no JS assets are loaded.
   */
  public static function isPreview(): bool {
    if (!isset(static::$isPreview)) {
      static::$isPreview = self::isAmp() || self::isSandboxed();
    }
    return static::$isPreview;
  }

  /**
   * Checks if Blazy is in AMP pages.
   */
  public static function isAmp(): bool {
    if (!isset(static::$isAmp)) {
      $request = self::request();
      static::$isAmp = $request && $request->query->get('amp');
    }
    return static::$isAmp;
  }

  /**
   * In CKEditor without JS assets, interactive elements must be sandboxed.
   */
  public static function isSandboxed(): bool {
    if (!isset(static::$isSandboxed)) {
      $check = FALSE;
      if ($router = self::routeMatch()) {
        if ($route = $router->getRouteName()) {
          $edits = ['entity_browser.', 'edit_form', 'add_form', '.preview'];
          foreach ($edits as $key) {
            if (mb_strpos($route, $key) !== FALSE) {
              $check = TRUE;
              break;
            }
          }
        }
      }

      static::$isSandboxed = $check;
    }
    return static::$isSandboxed;
  }

}
