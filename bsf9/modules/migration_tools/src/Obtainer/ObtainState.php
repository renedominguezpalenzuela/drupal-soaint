<?php

namespace Drupal\migration_tools\Obtainer;

use Drupal\migration_tools\StringTools;

/**
 * Class ObtainState
 *
 * Contains logic for cleaning and validation a state.
 * as needed to obtain a state.
 */
class ObtainState extends ObtainHtml {

  /**
   * Provides an array of states keyed by Full Name valued by Abreviation.
   *
   * @return array
   *   An array of all 50 states.
   */
  public static function getStates() {
    return [
      'Alabama' => 'AL',
      'Alaska' => 'AK',
      'Arizona' => 'AZ',
      'Arkansas' => 'AR',
      'California' => 'CA',
      'Colorado' => 'CO',
      'Connecticut' => 'CT',
      'Delaware' => 'DE',
      'Florida' => 'FL',
      'Georgia' => 'GA',
      'Hawaii' => 'HI',
      'Idaho' => 'ID',
      'Illinois' => 'IL',
      'Indiana' => 'IN',
      'Iowa' => 'IA',
      'Kansas' => 'KS',
      'Kentucky' => 'KY',
      'Louisiana' => 'LA',
      'Maine' => 'ME',
      'Maryland' => 'MD',
      'Massachusetts' => 'MA',
      'Michigan' => 'MI',
      'Minnesota' => 'MN',
      'Mississippi' => 'MS',
      'Missouri' => 'MO',
      'Montana' => 'MT',
      'Nebraska' => 'NE',
      'Nevada' => 'NV',
      'New Hampshire' => 'NH',
      'New Jersey' => 'NJ',
      'New Mexico' => 'NM',
      'New York' => 'NY',
      'North Carolina' => 'NC',
      'North Dakota' => 'ND',
      'Ohio' => 'OH',
      'Oklahoma' => 'OK',
      'Oregon' => 'OR',
      'Pennsylvania' => 'PA',
      'Rhode Island' => 'RI',
      'South Carolina' => 'SC',
      'South Dakota' => 'SD',
      'Tennessee' => 'TN',
      'Texas' => 'TX',
      'Utah' => 'UT',
      'Vermont' => 'VT',
      'Virginia' => 'VA',
      'Washington' => 'WA',
      'West Virginia' => 'WV',
      'Wisconsin' => 'WI',
      'Wyoming' => 'WY',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function cleanString($string) {
    $string = strip_tags($string);
    // State names can not have html entities.
    $string = html_entity_decode($string, ENT_COMPAT, 'UTF-8');

    // There are also numeric html special chars, let's change those.
    $string = StringTools::decodehtmlentitynumeric($string);

    // Remove white space-like things from the ends and decodes html entities.
    $string = StringTools::superTrim($string);
    // Remove multiple spaces.
    $string = preg_replace('!\s+!', ' ', $string);

    if (strlen($string) == 2) {
      $string = strtoupper($string);
    }
    else {
      $string = ucwords(strtolower($string));
    }

    return $string;
  }

  /**
   * Evaluates $string and if it checks out, returns TRUE.
   *
   * @param string $string
   *   The string to validate.
   *
   * @return bool
   *   TRUE if string can be used as a state name. FALSE if it can't.
   */
  protected function validateString($string) {
    // Run through any evaluations. If it makes it to the end, it is good.
    // Case race, first to evaluate TRUE aborts the text.
    $states = $this->getStates();

    switch (TRUE) {
      // List any cases below that would cause it to fail validation.
      case !parent::validateString($string):
        // A state can not to be less than 2 chars.
      case (strlen($string) < 2):
        // Longest state name in the US is 13 chars.
      case (strlen($string) > 13):
        // States would not be more than two words.
      case (str_word_count($string) > 2):
        // Should be a known state.
      case ((empty($states[$string])) && (!in_array($string, $states))):

        return FALSE;

      default:
        return TRUE;
    }
  }

}
