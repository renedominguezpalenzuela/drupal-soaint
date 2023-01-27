<?php

namespace Drupal\migration_tools\Obtainer;

use Drupal\migration_tools\StringTools;

/**
 * Class ObtainLocation
 *
 * Contains a collection of stackable finders and plucker that can be arranged
 * as needed to obtain a location suitable for geoLocating.
 */
class ObtainLocation extends ObtainHtml {

  /**
   * {@inheritdoc}
   */
  public static function cleanString($text) {
    $text = strip_tags($text);
    // Locations can not have html entities.
    $text = html_entity_decode($text, ENT_COMPAT, 'UTF-8');

    // There are also numeric html special chars, let's change those.
    $text = StringTools::decodehtmlentitynumeric($text);

    // Remove white space-like things from the ends and decodes html entities.
    $text = StringTools::superTrim($text);
    // Remove multiple spaces.
    $text = preg_replace('!\s+!', ' ', $text);

    return $text;
  }

  /**
   * Evaluates $string and if it checks out, returns TRUE.
   *
   * @param string $string
   *   The string to validate.
   *
   * @return bool
   *   TRUE if string can be used as a location. FALSE if it can't.
   */
  protected function validateString($string) {
    // Run through any evaluations. If it makes it to the end, it is good.
    // Case race, first to evaluate TRUE aborts the text.
    switch (TRUE) {
      // List any cases below that would cause it to fail validation.
      case !parent::validateString($string):
        // A geocodable location is unlikely to be less than 10 chars.
      case (strlen($string) < 10):
        // A geocodeable location is unlikely to be mor than 300 chars.
      case (strlen($string) > 300):
        // @TODO The most accurate validation would be to pass it to the
        // geocoder, but that could result in a huge number hits to the service.
        return FALSE;

      default:
        return TRUE;
    }
  }

}
