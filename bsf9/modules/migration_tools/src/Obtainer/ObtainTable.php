<?php

namespace Drupal\migration_tools\Obtainer;

/**
 * Class ObtainTables
 *
 * Contains logic for parsing tables.
 */
class ObtainTable extends ObtainArray {
  /**
   * Find contents from a table and place into an array.
   *
   * @param string $selector
   *   The css selector of the item to search for (the parent item)
   * @param int $table_num
   *   The value of n where the table is the nth table on the page. E.g., 2 for
   *   the second table on a page.
   * @param string $method
   *   What to build array with QP->text() or QP->html().
   *
   * @return array
   *   The table array.
   */
  protected function arrayFindTable($selector, $table_num, $method = 'text') {
    return $this->arrayPluckTable($selector, $table_num, $method, FALSE);
  }

  /**
   * Pluck contents from a table into an array.
   *
   * @param string $selector
   *   The css selector of the item to search for (the parent item)
   * @param int $table_num
   *   The value of n where the table is the nth table on the page. E.g., 2 for
   *   the second table on a page.
   * @param string $method
   *   What to build array with QP->text() or QP->html().
   * @param bool $pluck
   *   (optional) Used internally to declare if the items should be removed.
   *
   * @return array
   *   The table array.
   */
  protected function arrayPluckTable($selector, $table_num, $method = 'text', $pluck = TRUE) {
    $trcount = 0;
    $table_array = [];
    $tables = $this->queryPath->find($selector)->find("table");
    $current_table = 1;
    foreach ($tables as $table) {
      if ($table_num == $current_table) {
        foreach ($table->find("tr") as $tr) {
          $tdcount = 0;
          foreach ($tr->find("td") as $td) {
            if ($method == '') {
              $table_array[$trcount][$tdcount] = clone $td;
            }
            else {
              $table_array[$trcount][$tdcount] = $td->$method();
            }
            $tdcount++;
          }
          $trcount++;
        }
        if ($pluck) {
          $this->setElementToRemove($table);
        }
        break;
      }
      $current_table++;
    }

    return $table_array;
  }
}
