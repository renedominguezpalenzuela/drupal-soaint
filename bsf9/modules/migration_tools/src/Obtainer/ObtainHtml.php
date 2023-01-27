<?php

namespace Drupal\migration_tools\Obtainer;

use Drupal\migration_tools\StringTools;

/**
 * Obtains HTML using and stack of finder methods.
 *
 * A finder finds the item but does not remove if from the QueryPath DOM.
 * A plucker finds the item and removes it from the QueryPath DOM if it
 * validates.
 */
class ObtainHtml extends Obtainer {

  /**
   * Finder for nth  selector on the page.
   *
   * @param string $selector
   *   The selector to find.
   * @param int $n
   *   (optional) The depth to find.  Default: first item n=1.
   * @param string $method
   *   (optional) The method to use on the element, text or html. Default: text.
   *
   * @return string
   *   The text found.
   */
  protected function findSelector($selector, $n = 1, $method = 'text') {
    $text = '';
    $n = ($n > 0) ? $n - 1 : 0;
    if (!empty($selector)) {
      $elements = $this->queryPath->find($selector);
      foreach ((is_object($elements)) ? $elements : [] as $i => $element) {
        if ($i == $n) {
          $text = $element->$method();
          $this->setCurrentFindMethod("findSelector($selector, " . ++$n . ')');
          break;
        }
      }
    }

    return $text;
  }

  /**
   * Plucker for nth  selector on the page.
   *
   * @param string $selector
   *   The selector to find.
   * @param int $n
   *   (optional) The depth to find.  Default: first item n=1.
   * @param string $method
   *   (optional) The method to use on the element, text or html. Default: text.
   *
   * @return string
   *   The text found.
   */
  protected function pluckSelector($selector, $n = 1, $method = 'text') {
    $text = '';
    $n = ($n > 0) ? $n - 1 : 0;
    if (!empty($selector)) {
      $elements = $this->queryPath->find($selector);
      foreach ((is_object($elements)) ? $elements : [] as $i => $element) {
        if ($i == $n) {
          $this->setElementToRemove($element);
          $text = $element->$method();
          $this->setCurrentFindMethod("pluckSelector($selector, " . ++$n . ')');
          break;
        }
      }
    }

    return $text;
  }

  /**
   * Plucker for the last occurence of the selector.
   *
   * @param string $selector
   *   The selector to find.
   *
   * @return string
   *   The text found.
   */
  protected function pluckSelectorLast($selector) {
    $text = '';
    if (!empty($selector)) {
      $element = $this->queryPath->find($selector)->last();
      $this->setElementToRemove($element);
      $text = $element->text();
      $this->setCurrentFindMethod("pluckSelectorLast($selector)");
    }
    return $text;
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
   *   (optional) The method to use on the element, text or html. Default: text.
   *
   * @return string
   *   Text contents of the first element to validate.
   */
  protected function pluckAnySelectorUntilValid($selector, $limit = NULL, $method = 'text') {
    foreach ($this->queryPath->find($selector) as $key => $em) {
      if (($limit !== NULL) && ($key == $limit)) {
        break;
      }
      $this->setElementToRemove($em);
      $text = $em->$method();
      $text = $this->cleanString($text);
      if ($this->validateString($text)) {
        $this->setCurrentFindMethod("pluckAnySelectorUntilValid($selector)-i={$key}");
        // Return the original string to avoid double cleanup causing issues.
        return $em->$method();
      }
    }
    // If it made it this far, nothing was found.
    return '';
  }

  /**
   * Plucker crawls $selector elements until valid starting at bottom going up.
   *
   * This is a broad search and  is only as strong as the validation.  It should
   * only be used as a last resort (far down the finder stack).  It will start
   * with the last element found and work backwards until it finds a valid one.
   *
   * @param string $selector
   *   The selector to find.
   * @param string $limit
   *   (optional) The depth level limit for the search. Defaults to NULL.
   *
   * @return string
   *   Text contents of the first element to validate.
   */
  protected function pluckAnySelectorUntilValidDrillUp($selector, $limit = NULL) {
    $elements = $this->queryPath->find($selector);
    // QP either offers count or size depending on version.
    if (method_exists($elements, 'count')) {
      $element_count = $elements->count();
    }
    else {
      $element_count = $elements->size();
    }

    $limit = ($limit === NULL) ? $element_count : $limit;
    $limit = ($element_count > $limit) ? $limit : $element_count;

    $i = 1;
    while ($i <= $limit) {
      $index = $element_count - $i;
      // The get method returns a dom Element which is not the same as a qp
      // and is disconnected from the qp object.
      $dom_element = $elements->get($index, TRUE);
      $text_original = (!empty($dom_element->textContent)) ? $dom_element->textContent : '';
      $text = $this->cleanString($text_original);

      if ($this->validateString($text)) {
        $this->setCurrentFindMethod("pluckAnySelectorUntilValidDrillUp-i={$i}");
        // Get the same element as a queryPath object so it can be removed.
        // Removal is arranged by pluckSelector().
        $element_text = $this->pluckSelector($selector, $index + 1);
        // Return the original string to avoid double cleanup causing issues.
        return $element_text;
      }
      $i++;
    }

    // If it made it this far, nothing was found.
    return '';
  }

  /**
   * Plucker crawls $selector elements and concats them as it goes.
   *
   * @param string $selector
   *   The selector to find.
   * @param string $method
   *   (optional) The method to use on the element, text or html. Default: text.
   * @param string $qp_method
   *   (optional) The QueryPath method to use.
   * @param string $separator
   *   (optional)  The separator to use between the concatenation.
   *
   * @return string
   *   Concatenation of all selector elements' text.
   */
  protected function findAndConcatAnySelector($selector, $method = 'text', $qp_method = 'find', $separator = ' ') {
    return $this->pluckAndConcatAnySelector($selector, $method = 'text', $qp_method = 'find', $separator = ' ', FALSE);
  }

  /**
   * Plucker crawls $selector elements and concats them as it goes.
   *
   * @param string $selector
   *   The selector to find.
   * @param string $method
   *   (optional) The method to use on the element, text or html. Default: text.
   * @param string $qp_method
   *   (optional) The QueryPath method to use.
   * @param string $separator
   *   (optional)  The separator to use between the concatenation.
   * @param bool $pluck
   *   Whether to pluck.
   *
   * @return string
   *   Concatenation of all selector elements' text.
   */
  protected function pluckAndConcatAnySelector($selector, $method = 'text', $qp_method = 'find', $separator = ' ', $pluck = TRUE) {
    $elements = $this->queryPath->{$qp_method}($selector);
    if ($pluck) {
      $this->setElementToRemove($elements);
      $this->setCurrentFindMethod("pluckAndConcatAnySelector($selector)");
    }
    else {
      $this->setCurrentFindMethod("findAndConcatAnySelector($selector)");
    }

    $to_concat = [];
    foreach ($elements as $key => $em) {
      $to_concat[] = $em->{$method}();
    }

    return implode($separator, $to_concat);
  }

  /**
   * Find the content from the last anchor in the breadcrumb chain.
   *
   * @param string $selector
   *   Selector of the breadcrumb container.
   *
   * @return string
   *   The text found.
   */
  protected function findBreadcrumbLastAnchor($selector) {
    $text = '';
    if (!empty($selector)) {
      $breadcrumb = $this->queryPath->find($selector);
      $text = $breadcrumb->find('a')->last()->text();
      // This element makes up a bigger whole, so it is not set to be removed.
      $this->setCurrentFindMethod("findBreadcrumbLastAnchor($selector)");
    }
    return $text;
  }

  /**
   * Find the content from the last non-anchor in the breadcrumb chain.
   *
   * @param string $selector
   *   Selector of the breadcrumb container.
   *
   * @return string
   *   The text found.
   */
  protected function findBreadcrumbLastNonAnchor($selector) {
    $text = '';
    if (!empty($selector)) {
      $breadcrumb = $this->queryPath->find($selector);
      // Clone the breadcrumb so the next operations are non-destructive.
      $clone = clone $breadcrumb;
      $clone->find('a')->remove();
      $text = $clone->first()->text();
      // This element makes up a bigger whole, so it is not set to be removed.
      $this->setCurrentFindMethod("findBreadcrumbLastNonAnchor($selector)");
    }
    return $text;
  }

  /**
   * Find any attribute of any selector.
   *
   * @param string $selector
   *   The selector to find.
   * @param string $attribute
   *   The attribute to find on the selector. Example: alt, title, etc.
   * @param int $depth
   *   (optional) The depth to find.
   *
   * @return string
   *   The text found.
   */
  protected function findSelectorAttribute($selector, $attribute, $depth = 1) {
    if (!empty($selector)) {
      $elements = $this->queryPath->find($selector);
      foreach ((is_object($elements)) ? $elements : [] as $i => $element) {
        $i++;
        if ($i == $depth) {
          $this->setCurrentFindMethod("findSelectorAttribute($selector, $attribute, " . $i . ')');
          return $element->attr("{$attribute}");
        }
      }
    }
    return NULL;
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
   *   (optional) The method to use on the element, text or html. Default: text.
   *
   * @return string
   *   The text found.
   */
  protected function findSelectorNSeparator($selector, $n, $separator, $separator_index = -1, $method = 'text') {
    $text = '';
    if (!empty($selector) && !empty($separator)) {
      $n = ($n > 0) ? $n - 1 : 0;
      $elements = $this->queryPath->find($selector);
      foreach ((is_object($elements)) ? $elements : [] as $i => $element) {
        if ($i == $n) {
          $string = $element->$method();
          $arr = explode($separator, $string);
          // Deal with $selector_index = -1.
          if ($separator_index <= 0) {
            $separator_index = count($arr) - 1;
          }
          // Standardize $selector_index so it starts at 1.
          else {
            $separator_index--;
          }
          if ($separator_index <= count($arr)) {
            $text = $arr[$separator_index];
            $this->setCurrentFindMethod("findSelectorNSeparator($selector, " . ++$n . ", $separator, " . ++$separator_index . ", $method )");
          }
          break;
        }
      }
    }
    return $text;
  }

  /**
   * Plucker for nth xpath on the page.
   *
   * @param string $xpath
   *   The selector to find.
   * @param int $n
   *   (optional) The depth to find.  Default: first item n=1.
   * @param bool $pluck
   *   (optional) Used internally to declare if the items should be removed.
   *
   * @return string
   *   The text found.
   */
  protected function pluckXpath($xpath, $n = 1, $pluck = TRUE) {
    $text = '';
    $n = ($n > 0) ? $n - 1 : 0;
    if (!empty($xpath)) {
      $elements = $this->queryPath->xpath($xpath);
      foreach ((is_object($elements)) ? $elements : [] as $i => $element) {
        if ($i == $n) {
          if ($pluck) {
            $this->setElementToRemove($element);
          }
          $text = $element->text();
          $this->setCurrentFindMethod("pluckXpath($xpath, " . ++$n . ')');
          break;
        }
      }
    }
    return $text;
  }

  /**
   * Finder for nth xpath on the page.
   *
   * @param string $xpath
   *   The selector to find.
   * @param int $n
   *   (optional) The depth to find.  Default: first item n=1.
   *
   * @return string
   *   The text found.
   */
  protected function findXpath($xpath, $n = 1) {
    return $this->pluckXpath($xpath, $n, FALSE);
  }

  /**
   * Pluck the text in a specific row and column in a specific table.
   *
   * @param string $selector
   *   The selector to find.
   * @param int $table_num
   *   The value of n where the table is the nth table on the page. E.g., 2 for
   *   the second table on a page.
   * @param int $row
   *   The row number.
   * @param int $col
   *   The column number.
   * @param string $method
   *   Method to use, text or html.
   *
   * @return string
   *   The found text.
   */
  protected function pluckTableCellContentsFromSelector($selector, $table_num, $row, $col, $method = 'text') {
    $tables = $this->queryPath->find($selector)->find("table");
    $current_table = 1;

    foreach ($tables as $table) {
      if ($current_table == $table_num) {
        $string = $this->extractFromTable($table, $row, $col, $method);
        $this->setCurrentFindMethod("pluckTableContents($table_num, $row, $col)");
        return $string;
      }
      $current_table++;
    }
    return NULL;
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
   *   Method to use, text or html.
   *
   * @return string
   *   The found text.
   */
  protected function pluckTableCellContents($table_num, $row, $col, $method = 'text') {
    $tables = $this->queryPath->find("table");
    $current_table = 1;
    foreach ($tables as $table) {
      if ($current_table == $table_num) {
        $string = $this->extractFromTable($table, $row, $col, $method);
        $this->setCurrentFindMethod("pluckTableContents($table_num, $row, $col)");
        return $string;
      }
      $current_table++;
    }
    return NULL;
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
   *   What to return QP->text() or QP->html().
   *
   * @return string
   *   The text inside of the wanted tr and td.
   */
  protected function extractFromTable($table, $tr_target, $td_target, $method = 'text') {
    $trcount = 1;
    $tdcount = 1;

    foreach ($table->find("tr") as $tr) {
      if ($trcount == $tr_target) {
        foreach ($tr->find("td") as $td) {
          if ($tdcount == $td_target) {
            $this->setElementToRemove($td);
            return $td->$method();
          }
          $tdcount++;
        }
      }
      $trcount++;
    }

    return "";
  }

  /**
   * Pluck something based on sibling relationship.
   *
   * Example use: Plucking text based on a proximate lable.
   * e.g. Data Value: 123
   *
   * @param string $selector
   *   The css selector of the item to search for (the parent item)
   * @param string $needle
   *   The text to search for.
   * @param int $delta
   *   The index of the sibling (nth-child)
   *
   * @return string
   *   Matching text
   */
  protected function pluckFindStringGrabSibling($selector, $needle, $delta) {
    $matched_items = $this->queryPath->find($selector);

    foreach ($matched_items as $item) {
      $candidate_text = $item->get(0, TRUE)->textContent;

      if (strpos($candidate_text, $needle) !== FALSE) {
        return $item->children()->get($delta, TRUE)->textContent;
      }
    }
    return NULL;
  }

  /**
   * Find a sibling underneath a selector, by its sibling depth and selector.
   *
   * This is useful for grabbing a subtitle  if the subtitle follows something
   * specific element but should only be plucked if it has a specific selector.
   *
   * Example:
   * -
   *  job: addSearch
   *  method: pluckSelectorNextSiblingTarget
   *  arguments:
   *    - h1
   *    - 1
   *    - p em
   *    - 1
   *
   * Pass
   *   <h1>Title</h1>
   *   <p><em>I'm a subtitle.</em><p>    <--- Would grab this.
   *
   * Fail
   *   <h1>Title</h1>
   *   <p>I'm <em>not</em> a subtitle.<p>    <--- Would NOT grab this.
   *
   * @param string $selector
   *   The selector to find.
   * @param int $index
   *   The index of which selector look for.
   * @param string $siblingSelector
   *   The selector of the sibling you are trying to grab.
   * @param int $siblingIndex
   *   The index of the sibling you are trying to grab.
   * @param string $method
   *   (optional default:text) What to return QP->text() or QP->html().
   *
   * @return string
   *   Matching string.
   */
  protected function findSelectorNextSiblingTarget($selector, $index, $siblingSelector, $siblingIndex, $method = 'text') {
    $string = $this->pluckSelectorNextSiblingTarget($selector, $index, $siblingSelector, $siblingIndex, $method, FALSE);
    return $string;
  }

  /**
   * Pluck a sibling underneath a selector, by its sibling depth and selector.
   *
   * This is useful for grabbing a subtitle  if the subtitle follows something
   * specific element but should only be plucked if it has a specific selector.
   *
   * Example:
   * -
   *  job: addSearch
   *  method: pluckSelectorNextSiblingTarget
   *  arguments:
   *    - h1
   *    - 1
   *    - p em
   *    - 1
   *
   * Pass
   *   <h1>Title</h1>
   *   <p><em>I'm a subtitle.</em><p>    <--- Would grab this.
   *
   * Fail
   *   <h1>Title</h1>
   *   <p>I'm <em>not</em> a subtitle.<p>    <--- Would NOT grab this.
   *
   * @param string $selector
   *   The selector to find.
   * @param int $index
   *   The index of which selector look for.
   * @param string $siblingSelector
   *   The selector of the sibling you are trying to grab.
   * @param int $siblingIndex
   *   The index of the sibling you are trying to grab.
   * @param string $method
   *   (optional default:text) What to return QP->text() or QP->html().
   * @param bool $pluck
   *   (optional default: TRUE) Determines whether this opperates as a plucker.
   *
   * @return string
   *   Matching string.
   */
  protected function pluckSelectorNextSiblingTarget($selector, $index, $siblingSelector, $siblingIndex, $method = 'text', $pluck = TRUE) {
    $string = '';
    $index = ($index > 0) ? $index - 1 : 0;
    $siblingIndex = ($siblingIndex > 0) ? $siblingIndex - 1 : 0;

    $elements = $this->queryPath->find($selector);
    foreach ((is_object($elements)) ? $elements : [] as $pointerIndex => $element) {
      if ($pointerIndex == $index) {
        // We have the selector at the right depth, proceed to the siblings.
        $siblings = $element->nextAll();
        // Loop through the siblings until we get to the right depth.
        foreach ((is_object($siblings)) ? $siblings : [] as $pointerTargetIndex => $target) {
          if ($pointerTargetIndex == $siblingIndex) {
            // We are now have the target based on sibling count.
            $stringByDepth = $target->$method();
            // As a check, load it by sibling selector to make sure the depth
            // and selector agree.
            $siblingTargetString = $element->nextAll($siblingSelector)->first()->$method();

            // Compare the target with what was retrieved by sibling index.
            // Only if they match should the string be used.
            $string = (strcmp($stringByDepth, $siblingTargetString) == 0) ? $stringByDepth : '';
          }

          if ($pluck) {
            $this->setElementToRemove($target);
            $this->setCurrentFindMethod("pluckSelectorNextSiblingTarget($selector, ++$pointerIndex, $siblingSelector, $siblingIndex, ++$pointerTargetIndex, $method)");
          }
          else {
            $this->setCurrentFindMethod("findSelectorNextSiblingTarget($selector, ++$pointerIndex, $siblingSelector, $siblingIndex, ++$pointerTargetIndex, $method)");
          }
          break;
        }
        break;
      }
    }

    return $string;
  }

  /**
   * Splits text on variations of the br tag.
   *
   * @param string $html
   *   String that needs to be split.
   *
   * @return array
   *   Array containing the results of splitting on br.
   */
  public static function splitOnBr($html) {

    // Normalize variations of the br tag.
    // @codingStandardsIgnoreStart
    $search = [
      '<br>',
      '<br />',
      '<br/>',
    ];
    $html = str_ireplace($search, '<br>', $html);
    $lines = explode('<br>', $html);
    // @codingStandardsIgnoreEnd

    return $lines;
  }

  /**
   * Splits text on variations of the newline character.
   *
   * @param string $text
   *   String that needs to be split.
   *
   * @return array
   *   Array containing the results of splitting on \n.
   */
  public static function splitOnNewline($text) {
    $search = [
      "\r\n",
      "\r",
    ];
    $text = str_ireplace($search, "\n", $text);
    $lines = explode("\n", $text);

    return $lines;
  }

  /**
   * Takes a string, returns anything before a <br> tag and its many variants.
   *
   * @param string $text
   *   The text to break at the first <br> variant.
   * @param string $position
   *   (optional). Determines whether snippet 'before' or 'after' <br> is
   *   returned. Defaults to 'before'.
   *
   * @return string
   *   The string appearing before the <br> or the full string if no <br>.
   */
  public static function trimAtBr($text = '', $position = 'before') {
    $texts = self::splitOnBr($text);

    if ($position == 'before') {
      return $texts[0];
    }

    return $texts[1];
  }

  /**
   * Takes a string, returns anything before a lone <br> tag and its variants.
   *
   * @param string $text
   *   The text to break at the first <br> variant.
   * @param object $qp_element
   *   The query path object that may need things removed from.
   * @param int $max_length
   *   (optional)The maximum length of the text to considered valid. Default 0.
   *
   * @return string
   *   The string appearing before the blank <br> or the full string if no <br>.
   */
  protected function trimAtBrBlank($text, $qp_element, $max_length = 0) {
    $texts = self::splitOnBr($text);
    $trimmed = '';
    $lines_used = 0;
    foreach ($texts as $line_num => $line) {
      if (!empty($line)) {
        $lines_used = $line_num;
        $trimmed .= ' ' . $line;
      }
      else {
        break;
      }
    }
    // Clean string.
    $processed_text = $this->cleanString($trimmed);
    // Evaluate string.
    $valid = $this->validateString($processed_text);
    $length = StringTools::strlen($processed_text);
    if ($valid && ($max_length == 0 || $max_length >= $length)) {
      // It was valid so strip out each line.
      foreach ($texts as $line_num => $line) {
        if ($line_num <= $lines_used) {
          $this->extractAndPutBack($line, $qp_element);
        }
      }
      return $trimmed;
    }
    else {
      return '';
    }
  }

  /**
   * Strips html, truncates to word boundary, and preserves what was left.
   *
   * @param string $text
   *   Html or plain text to be truncated.
   * @param int $length
   *   (optional) The number of characters to truncate to. Defaults to 255.
   * @param int $min_word_length
   *   (optional) Minimum number of characters to consider a word. Default = 2.
   *
   * @return array
   *   - truncated: Plain text that has been truncated.
   *   - remaining: Plain text that was left.
   */
  public static function truncateThisWithoutHTML($text = '', $length = 255, $min_word_length = 2) {
    $text = strip_tags($text);
    $trunc_text = StringTools::truncate($text, $length, TRUE, FALSE, $min_word_length);
    // Check to see if any truncation is made.
    if (strcmp($text, $trunc_text) != 0) {
      // There was truncation, so process it differently.
      // Grab the remaining text by removing $trunc_test.
      $remaining_text = str_replace($trunc_text, '', $text);
    }
    $return = [
      'truncated' => $trunc_text,
      'remaining' => (!empty($remaining_text)) ? $remaining_text : '',
    ];

    return $return;
  }

  /**
   * Extracts, validates a string from html and puts remainder into the source.
   *
   * @param string $string
   *   The string of text to validated and remove.
   * @param object $qp_element
   *   The queryPath element to alter and put back.
   */
  protected function extractAndPutBack($string, $qp_element) {
    // Clean string.
    $processed_text = $this->cleanString($string);
    $valid = $this->validateString($processed_text);
    if ($valid) {
      // The string checks out, remove the original string from the element.
      $full_source = $qp_element->html();
      $new_source = str_replace($string, '', $full_source);
      $qp_element->html($new_source);
    }
  }

}
