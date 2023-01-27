<?php

namespace Drupal\migration_tools\Obtainer;

use Drupal\migration_tools\StringTools;

/**
 * Class ObtainCity
 *
 * Contains logic for cleaning and validation a city.
 * as needed to obtain a city.
 */
class ObtainCity extends ObtainHtml {

  /**
   * {@inheritdoc}
   */
  public static function cleanString($string) {
    $string = strip_tags($string);
    // Cities can not have html entities.
    $string = html_entity_decode($string, ENT_COMPAT, 'UTF-8');

    // There are also numeric html special chars, let's change those.
    $string = StringTools::decodehtmlentitynumeric($string);

    // Remove white space-like things from the ends and decodes html entities.
    $string = StringTools::superTrim($string);
    // Remove multiple spaces.
    $string = preg_replace('!\s+!', ' ', $string);

    return $string;
  }

  /**
   * Evaluates $string and if it checks out, returns TRUE.
   *
   * @param string $string
   *   The string to validate.
   *
   * @return bool
   *   TRUE if string can be used as a city. FALSE if it can't.
   */
  protected function validateString($string) {
    // Run through any evaluations. If it makes it to the end, it is good.
    // Case race, first to evaluate TRUE aborts the text.
    switch (TRUE) {
      // List any cases below that would cause it to fail validation.
      case !parent::validateString($string):
        // A city is unlikely to be less than 2 chars.
      case (strlen($string) < 2):
        // Longest city name in the world is 97 chars.
      case (strlen($string) > 97):
        // Cities would not be more than two words.
      case (str_word_count($string) > 2):

        return FALSE;

      default:
        return TRUE;
    }
  }

}
