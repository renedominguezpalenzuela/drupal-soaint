<?php

namespace Drupal\migration_tools\Obtainer;

/**
 * Class ObtainImageFile
 *
 * Contains logic for parsing for file images in HTML.
 */
class ObtainImageFile extends ObtainImage {

  /**
   * Find images in selector contents, put each element in an array.
   *
   * @param string $selector
   *   The selector to find.
   * @param array $file_extensions
   *   (optional) Array of file extensions to include, defaults to all.
   * @param array $domains_to_include
   *   (optional) Array of domains to include.
   *
   * @return array
   *   The array of elements found.
   */
  protected function findFileImages($selector, array $file_extensions = [], array $domains_to_include = []) {
    return self::pluckFileImages($selector, $file_extensions, $domains_to_include, FALSE);
  }

  /**
   * Pluck file images in selector contents, put each element in an array.
   *
   * @param string $selector
   *   The selector to find.
   * @param array $file_extensions
   *   (optional) Array of file extensions to include, defaults to all.
   * @param array $domains_to_include
   *   (optional) Array of domains to include.
   * @param bool $pluck
   *   If TRUE, will pluck elements.
   *
   * @return array
   *   The array of elements found.
   */
  protected function pluckFileImages($selector, array $file_extensions = [], array $domains_to_include = [], $pluck = TRUE) {
    $images = parent::findImages($selector);
    $valid_images = $images;

    if (!empty($images) && (!empty($file_extensions) || !empty($domains_to_include))) {
      $valid_images = $this->validateImages($images, $file_extensions, $domains_to_include);
      if ($pluck) {
        foreach ($valid_images as $valid_image) {
          $this->setElementToRemove($valid_image['element']);
        }
      }
    }

    return $valid_images;
  }

  /**
   * Validate images array.
   *
   * @param array $images
   *   Array of images.
   * @param array $file_extensions
   *   (optional) Array of file extensions to include, defaults to all.
   * @param array $domains_to_include
   *   (optional) Array of domains to include.
   *
   * @return array
   *   Array containing only valid images.
   */
  protected function validateImages(array &$images, array $file_extensions = [], array $domains_to_include = []) {
    if ($images) {
      foreach ($images as $key => $image) {
        $extension = strtolower(pathinfo($image['src'], PATHINFO_EXTENSION));
        $image_domain = strtolower(parse_url($image['base_uri'], PHP_URL_HOST));
        if (!empty($file_extensions)) {
          if (!in_array($extension, $file_extensions)) {
            unset($images[$key]);
          }
        }
        elseif (!empty($domains_to_include)) {
          if (!in_array($image_domain, $domains_to_include)) {
            unset($images[$key]);
          }
        }
      }
    }

    return $images;
  }

}
