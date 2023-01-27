<?php

namespace Drupal\migration_tools\Obtainer;

/**
 * Class ObtainImage
 *
 * Contains logic for parsing for images in HTML.
 */
class ObtainImage extends ObtainHtml {

  /**
   * Find images in contents of the selector and put each element in an array.
   *
   * @param string $selector
   *   The selector to find.
   *
   * @return array
   *   The array of elements found containing element, src, alt, title, base_uri
   */
  protected function findImages($selector) {
    return $this->pluckImages($selector, FALSE);
  }

  /**
   * Pluck the images in contents of the selector, put each element in an array.
   *
   * @param string $selector
   *   The selector to find.
   * @param bool $pluck
   *   (optional) Used internally to declare if the items should be removed.
   *
   * @return array
   *   The array of elements found containing element, src, alt, title, base_uri
   */
  protected function pluckImages($selector, $pluck = TRUE) {
    $found = [];
    if (!empty($selector)) {
      $element_with_images = $this->queryPath->find($selector);
      // Get images.
      $elements = $element_with_images->find('img');
      foreach ((is_object($elements)) ? $elements : [] as $element) {
        if ($element->hasAttr('src')) {
          $src = $element->attr('src');
          $link_text = trim($element->get(0)->textContent);
          $base_uri = $element->get(0)->baseURI;
          $alt = $element->attr('alt');
          $title = $element->attr('title');

          $found[] = [
            'element' => $element,
            'src' => $src,
            'alt' => $alt,
            'title' => $title,
            'link_text' => $link_text,
            'base_uri' => $base_uri,
          ];
        }
        $this->setCurrentFindMethod("pluckImages($selector" . ')');
      }
      if ($pluck) {
        $this->setElementToRemove($elements);
      }
    }

    return $found;
  }

  /**
   * Validate images array.
   *
   * @param array $images
   *   Array of images.
   *
   * @return array
   *   Array containing only valid images.
   */
  protected function validateImages(array &$images) {
    // Just return all the images here, no validation required.
    return $images;
  }

  /**
   * Evaluates $found array and if it checks out, returns TRUE.
   *
   * This method is misleadingly named since it is processing an array, but
   * must override the string based validateString.
   *
   * @param mixed $found
   *   The array to validate.
   *
   * @return bool
   *   TRUE if array is usuable. FALSE if it isn't.
   */
  protected function validateString($found) {
    // Run through any evaluations. If it makes it to the end, it is good.
    // Case race, first to evaluate TRUE aborts the text.
    switch (TRUE) {
      // List any cases below that would cause it to fail validation.
      case empty($found):
      case !is_array($found):

        return FALSE;

      default:
        return TRUE;
    }
  }

  /**
   * Cleans array and returns it prior to validation.
   *
   * This method is misleadingly named since it is processing an array, but
   * must override the string based cleanString.
   *
   * @param mixed $found
   *   Text to clean and return.
   *
   * @return mixed
   *   The cleaned array.
   */
  public static function cleanString($found) {
    $found = (empty($found)) ? [] : $found;
    // Make sure it is an array, just in case someone uses a string finder.
    $found = (is_array($found)) ? $found : [$found];

    return $found;
  }

}
