<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Site\Settings;
use Drupal\file\FileInterface;
use Drupal\blazy\Blazy;
use Drupal\blazy\Utility\Path;

/**
 * Provides file_BLAH BC for D8 - D10+ till D11 rules.
 *
 * @todo recap similiraties and make them plugins.
 * @todo remove deprecated functions post D11, not D10, and when D8 is dropped.
 */
class BlazyFile {

  /**
   * Returns TRUE if an external URL.
   */
  public static function isExternal($uri): bool {
    return $uri && UrlHelper::isExternal($uri);
  }

  /**
   * Returns TRUE if a File entity.
   */
  public static function isFile($file): bool {
    return $file instanceof FileInterface;
  }

  /**
   * Determines whether the URI has a valid scheme for file API operations.
   *
   * @param string $uri
   *   The URI to be tested.
   *
   * @return bool
   *   TRUE if the URI is valid.
   */
  public static function isValidUri($uri): bool {
    if (!empty($uri) && $manager = Path::streamWrapperManager()) {
      return $manager->isValidUri($uri);
    }
    return FALSE;
  }

  /**
   * Creates a relative or absolute web-accessible URL string.
   *
   * @param string $uri
   *   The file uri.
   * @param bool $relative
   *   Whether to return an relative or absolute URL.
   *
   * @return string
   *   Returns an absolute web-accessible URL string.
   */
  public static function createUrl($uri, $relative = FALSE): string {
    if ($gen = Path::fileUrlGenerator()) {
      // @todo recheck ::generateAbsoluteString doesn't return web-accessible
      // protocol as expected, required by getimagesize to work correctly.
      return $relative ? $gen->generateString($uri) : $gen->generateAbsoluteString($uri);
    }

    $function = 'file_create_url';
    return is_callable($function) ? $function($uri) : '';
  }

  /**
   * Transforms an absolute URL of a local file to a relative URL.
   *
   * Blazy Filter or OEmbed may pass mixed (external) URI upstream.
   *
   * @param string $uri
   *   The file uri.
   * @param object $style
   *   The optional image style instance.
   * @param array $options
   *   The options: default url, sanitize.
   *
   * @return string
   *   Returns an absolute URL of a local file to a relative one.
   *
   * @see BlazyOEmbed::getExternalImageItem()
   * @see BlazyFilter::getImageItemFromImageSrc()
   */
  public static function transformRelative($uri, $style = NULL, array $options = []): string {
    $url = $options['url'] ?? '';
    $sanitize = $options['sanitize'] ?? FALSE;

    if (empty($uri)) {
      return $url;
    }

    $data_uri = $url && mb_substr($url, 0, 10) === 'data:image';

    // Returns as is if an external URL: UCG or external OEmbed image URL.
    if (self::isExternal($uri)) {
      $url = $uri;
    }
    else {
      // @todo re-check this based on the need.
      if (($data_uri || empty($url) || $style) && self::isValidUri($uri)) {
        $url = $style ? $style->buildUrl($uri) : self::createUrl($uri);

        if ($gen = Path::fileUrlGenerator()) {
          $url = $gen->transformRelative($url);
        }
        else {
          $function = 'file_url_transform_relative';
          $url = is_callable($function) ? $function($url) : $url;
        }
      }
    }

    // If transform failed, returns default URL, or URI as is.
    $url = $url ?: $uri;

    // Just in case, an attempted kidding gets in the way, relevant for UGC.
    // @todo re-check to completely remove data URI.
    if ($sanitize && !$data_uri) {
      $url = UrlHelper::stripDangerousProtocols($url);
    }

    return $url ?: '';
  }

  /**
   * Returns URI from the given image URL, relevant for unmanaged/ UGC files.
   *
   * Converts `/sites/default/files/image.jpg` into `public://image.jpg`.
   *
   * @todo re-check if core has this type of conversion.
   */
  public static function buildUri($url): ?string {
    if (!self::isExternal($url)
      && $normal_path = UrlHelper::parse($url)['path']) {
      // If the request has a base path, remove it from the beginning of the
      // normal path as it should not be included in the URI.
      $base_path = \Drupal::request()->getBasePath();
      if ($base_path && mb_strpos($normal_path, $base_path) === 0) {
        $normal_path = str_replace($base_path, '', $normal_path);
      }

      $public_path = Settings::get('file_public_path', 'sites/default/files');

      // Only concerns for the correct URI, not image URL which is already being
      // displayed via SRC attribute. Don't bother language prefixes for IMG.
      if ($public_path && mb_strpos($normal_path, $public_path) !== FALSE) {
        $rel_path = str_replace($public_path, '', $normal_path);
        return self::normalizeUri($rel_path);
      }
    }
    return NULL;
  }

  /**
   * Normalizes URI for sub-modules.
   */
  public static function normalizeUri($path): string {
    $uri = $path;
    if ($stream = Path::streamWrapperManager()) {
      $uri = $stream->normalizeUri($path);

      // @todo re-check why scheme is gone since 2.9. It was there <= 2.5.
      if (substr($uri, 0, 2) === '//') {
        $uri = 'public:' . $uri;
      }
    }
    return $uri;
  }

  /**
   * Returns URI from image item, fake or valid one, no problem.
   */
  public static function uri($item, array $settings = []): ?string {
    $uri = NULL;
    if ($item) {
      $file = $item->entity ?? NULL;
      $uri = self::isFile($file) ? $file->getFileUri() : ($item->uri ?? NULL);
    }

    // No file API with unmanaged files here: hard-coded UGC, legacy VEF.
    if (empty($uri) && $settings) {
      // Respects first.uri without image_url such as colorbox/zoom-like.
      if ($blazies = ($settings['blazies'] ?? NULL)) {
        $uri = $blazies->get('image.uri') ?: $blazies->get('first.uri');
      }

      // @todo remove settings once done migration, and after sub-modules.
      $_uri = $settings['uri'] ?? $settings['_uri'] ?? NULL;
      $uri = $_uri ?: $uri;
    }

    return $uri ?: '';
  }

  /**
   * Returns the File entity from any object, or just settings, if applicable.
   *
   * Should be named entity, but for consistency with BlazyImage:item().
   */
  public static function item($object = NULL, array $settings = []): ?object {
    $file = $object;

    // Bail out early if we are given what we want.
    /** @var \Drupal\file\Entity\File $file */
    if (self::isFile($file)) {
      return $file;
    }

    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $object */
    if ($object instanceof EntityReferenceItem) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $object->entity;
    }
    elseif ($object instanceof EntityReferenceFieldItemListInterface) {
      /** @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $object */
      /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $image */
      if ($image = $object->first()) {
        /** @var \Drupal\file\Entity\File $file */
        $file = $image->entity;
      }
    }

    // BlazyFilter without any entity/ formatters associated with.
    // Or any entities: Node, Paragraphs, User, etc. having settings.image.
    if (!self::isFile($file) && $settings) {
      // Extracts File entity from settings.image, the poster image.
      if ($name = $settings['image'] ?? NULL) {
        // With a mix of image and video, image is not always there.
        $file = self::fromField($file, $name, $settings);
      }

      // BlazyFilter without any entity/ formatters associated with.
      // Or legacy VEF with hard-coded image URL without file API.
      if (!self::isFile($file)) {
        $file = self::fromSettings($settings);
      }
    }

    return self::isFile($file) ? $file : NULL;
  }

  /**
   * Returns the File entity from a field name, if applicable.
   *
   * Main image can be separate image item from video thumbnail for highres.
   * Fallback to default thumbnail if any, which has no file API. This used to
   * be for non-media File Entity Reference at 1.x, things changed since then.
   * Some core methods during Blazy 1.x are now gone at 2.x.
   * Re-purposed for Paragraphs, Node, etc. which embeds Media or File.
   *
   * @see BlazyImage::fromField()
   *  The deprecated/ previous approach on this.
   */
  public static function fromField($entity, $name, array $settings): ?object {
    $file = NULL;
    /** @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $field */
    if (isset($entity->{$name}) && $field = $entity->get($name)) {
      if (method_exists($field, 'referencedEntities')) {
        // Two designated types: MediaInterface and FileInterface.
        $reference = $field->referencedEntities()[0] ?? NULL;
        // The first is FileInterface.
        if (self::isFile($reference)) {
          $file = $reference;
        }
        else {
          // The last is MediaInterface, but let the dogs out for now.
          $options = [
            'entity' => $reference,
            'source' => $entity,
            'settings' => $settings,
          ];
          if ($image = BlazyImage::fromContent($options, $name)) {
            $file = $image->entity;
          }
        }
      }
    }
    return $file;
  }

  /**
   * Returns the File entity from settings, if applicable, relevant for Filter.
   */
  public static function fromSettings(array $settings): ?object {
    $file = NULL;
    $blazies = $settings['blazies'] ?? NULL;

    if ($manager = Blazy::service('blazy.manager')) {
      $uri = self::uri(NULL, $settings);
      $uuid = $blazies ? $blazies->get('entity.uuid') : NULL;
      $file = $uuid ? $manager->loadByUuid($uuid, 'file') : NULL;

      if (!$file && self::isValidUri($uri)) {
        if ($files = $manager->loadByProperties(['uri' => $uri], 'file', TRUE)) {
          $file = reset($files);
        }
      }
    }
    return $file;
  }

}
