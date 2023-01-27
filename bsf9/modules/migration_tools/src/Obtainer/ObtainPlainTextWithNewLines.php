<?php

namespace Drupal\migration_tools\Obtainer;

use Drupal\migration_tools\StringTools;

/**
 * Obtainer to get text with p's and br's replaced with new lines.
 *
 * Selectors in this class always return plain text. $method parameters all
 * default to 'html' because the code to turn p and br tags into new lines runs
 * on the result of the selector, so if the selector $method is 'text', no
 * new lines will be added (the results will be the same as using ObtainHtml).
 *
 * @package Drupal\migration_tools\Obtainer
 */
class ObtainPlainTextWithNewLines extends ObtainHtml {

  /**
   * Plucker to turn html into text with new lines for nth selector on the page.
   *
   * @param string $selector
   *   The selector to find.
   * @param int $n
   *   (optional) The depth to find.  Default: first item n=1.
   * @param string $method
   *   The method to use on the element, text or html. Default: html.
   *
   *   Note that this is different from the normal default. Selectors
   *   in this class will always return text, but if 'text' is the method,
   *   tags will be removed before they can be turned into new lines.
   *
   * @return string
   *   The text found.
   */
  protected function pluckSelector($selector, $n = 1, $method = "html") {
    return parent::pluckSelector($selector, $n, $method);
  }

  /**
   * Finder to turn html into long plain text for nth selector on the page.
   *
   * @param string $selector
   *   The selector to find.
   * @param int $n
   *   (optional) The depth to find.  Default: first item n=1.
   * @param string $method
   *   The method to use on the element, text or html. Default: html.
   *
   *   Note that this is different from the normal default. Selectors
   *   in this class will always return text, but if 'text' is the method,
   *   tags will be removed before they can be turned into new lines.
   *
   * @return string
   *   The text found.
   */
  protected function findSelector($selector, $n = 1, $method = "html") {
    return parent::findSelector($selector, $n, $method);
  }

  /**
   * Plucker crawls $selector elements until one validates.
   *
   * This is a broad search and should only be used as a last resort.
   *
   * @param string $selector
   *   The selector to find.
   * @param string $limit
   *   (optional) The depth level limit for the search.  Defaults to NULL.
   * @param string $method
   *   The method to use on the element, text or html. Default: html.
   *
   *   Note that this is different from the normal default. Selectors
   *   in this class will always return text, but if 'text' is the method,
   *   tags will be removed before they can be turned into new lines.
   *
   * @return string
   *   Text contents of the first element to validate.
   */
  protected function pluckAnySelectorUntilValid($selector, $limit = NULL, $method = 'html') {
    return parent::pluckAnySelectorUntilValid($selector, $limit, $method);
  }

  /**
   * Find the nth value of the content separated by $separator.
   *
   * This is useful for selecting the last item in a breadcrumb.
   *
   * @param string $selector
   *   The selector to find.
   * @param int $n
   *   The depth to find.  Default: first item n=1.
   * @param string $separator
   *   The text to search for to separate the content string.
   * @param int $separator_index
   *   (optional) The index of item to return. A $separator_index of 1 selects
   *   the first item. A $separator_index of -1 selects the last item.
   *   Default: -1.
   * @param string $method
   *   The method to use on the element, text or html. Default: html.
   *
   *   Note that this is different from the normal default. Selectors
   *   in this class will always return text, but if 'text' is the method,
   *   tags will be removed before they can be turned into new lines.
   *
   * @return string
   *   The text found.
   */
  protected function findSelectorNSeparator($selector, $n, $separator, $separator_index = -1, $method = 'html') {
    return parent::findSelectorNSeparator($selector, $n, $separator, $separator_index, $method);
  }

  /**
   * Pluck the text in a specific row and column in a specific table.
   *
   * @param int $table_num
   *   The value of n where the table is the nth table on the page. E.g., 2 for
   *   the second table on a page.
   * @param int $row
   *   The row number.
   * @param int $col
   *   The column number.
   * @param string $method
   *   The method to use on the element, text or html. Default: html.
   *
   * @return string
   *   The found text.
   */
  protected function pluckTableCellContents($table_num, $row, $col, $method = 'html') {
    return parent::pluckTableCellContents($table_num, $row, $col, $method);
  }

  /**
   * Pluck the text in a specific row and column in a specific table.
   *
   * @param int $table_num
   *   The value of n where the table is the nth table on the page. E.g., 2 for
   *   the second table on a page.
   * @param int $row
   *   The row number.
   * @param int $col
   *   The column number.
   * @param string $method
   *   The method to use on the element, text or html. Default: html.
   *
   * @return string
   *   The found text.
   */
  protected function pluckTableCellContentsFromSelector($selector, $table_num, $row, $col, $method = 'html') {
    return parent::pluckTableCellContentsFromSelector($selector, $table_num, $row, $col, $method);
  }

  /**
   * Extract td contents from a table, and lines it up to be removed.
   *
   * @param object $table
   *   A query path object with a table as the root.
   * @param int $tr_target
   *   Which tr do you want. Starting the count from 1.
   * @param int $td_target
   *   Which td do you want. Starting the count from 1.
   * @param string $method
   *   The method to use on the content, text or html. Default: html.
   *
   * @return string
   *   The text inside of the wanted tr and td.
   */
  protected function extractFromTable($table, $tr_target, $td_target, $method = 'html') {
    return parent::extractFromTable($table, $tr_target, $td_target, $method);
  }

  /**
   * {@inheritdoc}
   */
  public static function cleanString($string) {
    // Two paragraphs in a row should have two new lines between them (not four)
    // so remove closing tags that are immediately followed by opening tags.
    $string = preg_replace('/<\/p>\s*<p[^>]*>/', '<p>', $string);
    // Replace all p tags with two new lines.
    $string = preg_replace('/<\/?p[^>]*>/', "\n\n", $string);

    // Replace br tags in all their forms.
    $sections = self::splitOnBr($string);
    $string = strip_tags(implode(PHP_EOL, $sections));

    // There are also numeric html special chars, let's change those.
    $string = StringTools::decodeHtmlEntityNumeric($string);
    // Checking again in case another process rendered it non UTF-8.
    $is_utf8 = mb_check_encoding($string, 'UTF-8');

    if (!$is_utf8) {
      $string = StringTools::fixEncoding($string);
    }

    // Remove white space-like things from the ends and decodes html entities.
    // This also removes new lines at the beginning and end of the string added
    // by the p tag replacement above.
    $string = StringTools::superTrim($string);

    return $string;
  }

}
