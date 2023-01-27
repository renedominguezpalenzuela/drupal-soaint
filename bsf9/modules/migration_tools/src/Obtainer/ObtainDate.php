<?php

namespace Drupal\migration_tools\Obtainer;

use Drupal\migration_tools\QpHtml;
use Drupal\migration_tools\StringTools;
use Drupal\migration_tools\Message;

/**
 * Class ObtainDate
 *
 * Contains a collection of stackable finders that can be arranged
 * as needed to obtain a date content.
 */
class ObtainDate extends ObtainHtml {

  /**
   * Finder method to find dates by its accompanying text.
   *
   * @param mixed $selectors
   *   String - single selector to find.
   *   Array - an array of selectors to look for.
   * @param mixed $search_strings
   *   String - single string to search for.
   *   Array - array of strings to search for.
   *   The search string(s) would be adjacent to the date.
   *
   * @return string
   *   The string that was found
   */
  protected function pluckDateFromSelectorWithSearchStrings($selectors, $search_strings) {
    // If single strings are given, convert to arrays.
    $selectors = (array) $selectors;
    $search_strings = (array) $search_strings;

    // Loop through the selectors.
    foreach ($selectors as $selector) {
      // Loop through the search strings.
      foreach ($search_strings as $search_string) {
        // Search for the string.
        $element = QpHtml::matchText($this->queryPath, $selector, $search_string);

        if (!empty($element)) {
          $text = $element->text();

          // Remove accompanying text and clean string.
          $text = str_replace($search_string, '', $text);
          $text = $this->cleanString($text);
          $valid = $this->validateString($text);

          if ($valid) {
            $this->setElementToRemove($element);
            Message::make("pluckDateFromSelectorWithSearchStrings| selector: @selector  search string: @search_string", ['@selector' => $selector, '@search_string' => $search_string], FALSE, 2);

            return $text;
          }
        }
      }
    }

    return '';
  }

  /**
   * Finder method to find dates within text within selector moving start->end.
   *
   * @param string $selector
   *   A selector to search within.
   *
   * @return string
   *   The date string that was found
   */
  protected function findAndFilterForwardDate($selector) {
    $elements = $this->queryPath->find($selector);
    foreach ($elements as $element) {
      $date_formats = [
        // Covers ##/##/##, ##/##/####, ##-##-##, ##-##-####.
        "/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/m",
      ];
      foreach ($date_formats as $date_format) {
        $matches = [];
        $hit = preg_match_all($date_format, $element->text(), $matches, PREG_PATTERN_ORDER);
        if ($hit) {
          foreach ($matches[0] as $key => $match) {
            $text = $this->cleanString($match);
            $valid = $this->validateString($text);

            if ($valid) {
              Message::make("findAndFilterForwardDate| selector: @selector found a date at @key.", ['@selector' => $selector, '@key' => $key], FALSE, 2);

              return $text;
            }
          }
        }
      }
    }
    return '';
  }

  /**
   * Plucker method to pluck dates within text within selector going start->end.
   *
   * @param string $selector
   *   A selector to search within.
   *
   * @return string
   *   The date string that was found
   */
  protected function pluckAndFilterForwardDate($selector) {
    $elements = $this->queryPath->find($selector);

    foreach ($elements as $element) {
      $date_formats = [
        // Covers ##/##/##, ##/##/####, ##-##-##, ##-##-####.
        "/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/m",
      ];
      foreach ($date_formats as $date_format) {
        $matches = [];
        $hit = preg_match_all($date_format, $element->text(), $matches, PREG_PATTERN_ORDER);
        if ($hit) {
          foreach ($matches[0] as $key => $match) {
            $text = $this->cleanString($match);
            $valid = $this->validateString($text);
            if ($valid) {
              $this->setElementToRemove($element);
              Message::make("pluckAndFilterForwardDate| selector: @selector found a date at @key.", ['@selector' => $selector, '@key' => $key], FALSE, 2);

              return $text;
            }
          }
        }
      }
    }
    return '';
  }

  /**
   * Cleans $text and returns it.
   *
   * @param string $text
   *   Text to clean and return.
   *
   * @return string
   *   The cleaned text.
   */
  public static function cleanString($text) {

    // There are also numeric html special chars, let's change those.
    $text = StringTools::decodehtmlentitynumeric($text);

    // We want out titles to be only digits and ascii chars so we can produce
    // clean aliases.
    $text = StringTools::convertNonASCIItoASCII($text);

    // Checking again in case another process rendered it non UTF-8.
    $is_utf8 = mb_check_encoding($text, 'UTF-8');

    if (!$is_utf8) {
      $text = StringTools::fixEncoding($text);
    }

    // Remove some strings that often accompany dates.
    $remove = [
      'FOR',
      'IMMEDIATE',
      'RELEASE',
      'NEWS RELEASE SUMMARY –',
      'CONTACT:',
      'Press Release',
      'news',
      'press',
      'release',
      'updated',
      'update',
      'sunday',
      'monday',
      'tuesday',
      'wednesday',
      'thursday',
      'friday',
      'saturday',
      // Intentional mispellings.
      'thurday',
      'wendsday',
      'firday',
    ];
    // Replace these with nothing.
    $text = str_ireplace($remove, '', $text);

    $remove = [
      '.',
      ',',
      "\n",
    ];
    // Replace these with spaces.
    $text = str_ireplace($remove, ' ', $text);

    // Fix mispellings and abbreviations.
    $replace = [
      'septmber' => 'september',
      'arpil' => 'april',
      'febraury' => 'february',
    ];
    $text = str_ireplace(array_keys($replace), array_values($replace), $text);

    // Remove multiple spaces.
    $text = preg_replace('/\s{2,}/u', ' ', $text);
    // Remove any text following the 4 digit year.
    $years = range(1995, 2050);
    foreach ($years as $year) {
      $pos = strpos($text, (string) $year);
      if ($pos !== FALSE) {
        $text = substr($text, 0, ($pos + 4));
        break;
      }
    }

    // Remove white space-like things from the ends and decodes html entities.
    $text = StringTools::superTrim($text);

    return $text;
  }

  /**
   * Returns array of month names.
   *
   * @return array
   *   One entry for each month name.
   */
  public static function returnMonthNames() {
    return [
      'January',
      'February',
      'March',
      'April',
      'May',
      'June',
      'July',
      'August',
      'September',
      'October',
      'November',
      'December',
    ];
  }

  /**
   * Evaluates $string for presence of more than 1 element from month array.
   *
   * @param string $string
   *   The string to validate.
   *
   * @return bool
   *   TRUE if more than one Month found in string.  FALSE if not.
   */
  public static function searchStringDoubleMonth($string) {
    $months = self::returnMonthNames();
    $count = 0;
    $bmultiple = FALSE;
    foreach ($months as $month) {
      $lower_month = strtolower($month);
      $lower_string = strtolower($string);
      if (strstr($lower_string, $lower_month)) {
        $count++;
        if ($count > 1) {
          $bmultiple = TRUE;
          break;
        }
      }
    }
    return $bmultiple;
  }

  /**
   * Searches for a date range overlapping months, returns single date.
   *
   * Looks specifically for month names as defined in self::returnMonthNames().
   * In the case of multiple months found in $string, returns the latter date.
   * Example: "March 28 - April 4", returns April 4.
   *
   * @param string $string
   *   The string to clean.
   *
   * @return string
   *   Return string includes only 1 month.
   */
  public static function removeMultipleMonthRange($string) {
    $bmultiple = self::searchStringDoubleMonth($string);
    if ($bmultiple === TRUE) {
      $explode = explode('-', $string);
      if (!empty($explode[1])) {
        $latter_date = $explode[1];
        return $latter_date;
      }
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
   *   TRUE if possibleText can be used as a date.  FALSE if it cant.
   */
  protected function validateString($string) {
    // Run through any evaluations.  If it makes it to the end, it is good.
    // Case race, first to evaluate TRUE aborts the text.
    switch (TRUE) {
      // List any cases below that would cause it to fail validation.
      case empty($string):
      case is_object($string):
      case is_array($string):
      case (strlen($string) < 6):
        // If we can't form a date out of it, it must not be a date.
      case !strtotime($string):
        return FALSE;

      default:
        return TRUE;
    }
  }

}
