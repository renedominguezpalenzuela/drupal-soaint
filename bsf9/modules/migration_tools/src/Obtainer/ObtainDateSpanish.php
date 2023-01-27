<?php

namespace Drupal\migration_tools\Obtainer;

/**
 * {@inheritdoc}
 */
class ObtainDateSpanish extends ObtainDate {

  /**
   * Converst spanish $string  to date. If it checks out, returns TRUE.
   *
   * @param string $string
   *   The string to clean.
   *
   * @return string
   *   The cleaned text.
   */
  public static function cleanString($string) {
    $string = self::convertESDateStringToDate($string);
    return parent::cleanString($string);
  }

  /**
   * Converts es date text of the form w m d y to numerical Y-M-D.
   *
   * @param string $date_string
   *   Should look like miércoles, 28 de febrero de 2014.
   *
   * @return string
   *   Date in the form of 2014-02-21
   */
  public static function convertESDateStringToDate($date_string = '') {
    $processed_date = '';
    // Date_string looks like: miércoles, 28 de febrero de 2014.
    // Clean up the string.
    $date_modified = trim(strtolower($date_string));
    $date_modified = str_ireplace('de', ' ', $date_modified);
    // Replace commas with a space.
    $date_modified = preg_replace('/,/', ' ', $date_modified);
    // Replace multiple spaces with a space.
    $date_modified = preg_replace('!\s+!', ' ', $date_modified);
    // Parse out the date.
    $date_array = explode(' ', $date_modified);
    sort($date_array);

    // Make sure only digits in day $date_array[1] and year $date_array[3].
    $day = preg_replace('/[^0-9]/', '', trim($date_array[0]));
    unset($date_array[0]);
    $year = preg_replace('/[^0-9]/', '', trim($date_array[1]));
    unset($date_array[1]);
    // Convert spanish months to numeric.
    $months = [
      'enero' => '01',
      'febrero' => '02',
      'marzo' => '03',
      'abril' => '04',
      'mayo' => '05',
      'junio' => '06',
      'julio' => '07',
      'agosto' => '08',
      'septiembre' => '09',
      'octubre' => '10',
      'noviembre' => '11',
      'diciembre' => '12',
    ];
    $month = '';
    // With any items that remain, see if we have a month.
    foreach (is_array($date_array) ? $date_array : [] as $value) {
      // If the spanish month name is present in the array, use the number.
      if (!empty($months[$value])) {
        $month = $months[$value];
        break;
      }
    }

    if ((!empty($month)) && (!empty($day)) && (!empty($year))) {
      $processed_date = $year . '-' . $month . '-' . $day;
    }

    return $processed_date;
  }

}
