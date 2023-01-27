<?php

namespace Drupal\migration_tools\Obtainer;
use Drupal\migration_tools\StringTools;

/**
 * Class ObtainArray
 *
 * Contains logic for cleaning, validation and custom finders for gathering
 * multiples and returns arrays rather than strings.
 */
class ObtainArray extends ObtainHtml {

  /**
   * Cleans array and returns it prior to validation.
   *
   * This method is misleadingly named since it is processing an array, but
   * must override the string based cleanString.
   *
   * @param mixed $found
   *   Text to clean and return.
   *
   * @return array
   *   The cleaned array.
   */
  public static function cleanString($found) {
    $found = (empty($found)) ? [] : $found;
    // Make sure it is an array, just in case someone uses a string finder.
    $found = (is_array($found)) ? $found : [$found];

    // Only run if the value is not an array.
    $found = array_map(
      function ($value) {
        if (!is_array($value)) {
          return StringTools::superTrim($value);
        }
        else {
          return $value;
        }
      }, $found
    );

    return $found;
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

  // ***************** Array specific finders and pluckers ********************.
  // CAUTION: Since these return arrays rather than strings, they can not be
  // used by string based obtainers. To indicate this, they should all start
  // with the name 'array'.

  /**
   * Find the contents of the selector and put each element in an array.
   *
   * @param string $selector
   *   The selector to find.
   * @param string $method
   *   (optional) The method to use on the element, text or html. Default: text.
   *
   * @return array
   *   The array of elements found.
   */
  protected function arrayFindSelector($selector, $method = 'text') {
    return $this->arrayPluckSelector($selector, $method, FALSE);
  }

  /**
   * Pluck the contents of the selector and put each element in an array.
   *
   * @param string $selector
   *   The selector to find.
   * @param string $method
   *   (optional) The method to use on the element, text or html. Default: text.
   * @param bool $pluck
   *   (optional) Used internally to declare if the items should be removed.
   *
   * @return array
   *   The array of elements found.
   */
  protected function arrayPluckSelector($selector, $method = 'text', $pluck = TRUE) {
    $found = [];
    if (!empty($selector)) {
      $elements = $this->queryPath->find($selector);
      foreach ((is_object($elements)) ? $elements : [] as $element) {
        $found[] = $element->$method();
        $this->setCurrentFindMethod("arrayPluckSelector($selector" . ')');
      }
      if ($pluck) {
        $this->setElementToRemove($elements);
      }
    }

    return $found;
  }

}
