<?php

namespace Drupal\migration_tools\Obtainer;

/**
 * Class ObtainBody.
 *
 * Contains a collection of stackable finders that can be arranged
 * as needed to obtain a body or other long html content.
 */
class ObtainBody extends ObtainHtml {

  /**
   * Finder method to find the top body.
   *
   * @return string
   *   The string that was found
   */
  protected function findTopBodyHtml() {
    $element = $this->queryPath->top()->find('body');
    return $element->innerHtml();
  }

  /**
   * Cleans $text and returns it prior to validation.
   *
   * @param string $string
   *   Text to clean and return.
   *
   * @return string
   *   The cleaned text.
   */
  public static function cleanString($string) {
    $string = parent::cleanString($string);
    $remove = [
      "\t",
    ];
    // Replace these with spaces.
    $string = str_ireplace($remove, '', $string);
    return $string;
  }

}
