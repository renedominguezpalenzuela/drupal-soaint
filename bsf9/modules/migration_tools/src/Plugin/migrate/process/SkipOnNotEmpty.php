<?php

namespace Drupal\migration_tools\Plugin\migrate\process;

use Drupal\migrate\MigrateSkipProcessException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateSkipRowException;

/**
 * Skips processing the current row when the input value is not empty.
 *
 * The skip_on_not_empty process plugin checks to see if the current input value
 * is not empty (empty string, NULL, FALSE, 0, '0', or an empty array). If so,
 * the further processing of the property or the entire row (depending on the
 * chosen method) is skipped and will not be migrated.
 *
 * Available configuration keys:
 * - method: (optional) What to do if the input value is not empty. Values:
 *   - row: Skips the entire row when a non-empty value is encountered.
 *   - process: Prevents further processing of the input property when the value
 *     is not empty.
 * - message: (optional) A message to be logged in the {migrate_message_*} table
 *   for this row. Messages are only logged for the 'row' method. If not set,
 *   nothing is logged in the message table.
 *
 * Examples:
 *
 * @code
 * process:
 *   field_type_exists:
 *     plugin: skip_on_not_empty
 *     method: row
 *     source: field_ignore
 *     message: 'Field field_ignore value is present so row skipped.'
 * @endcode
 * If 'field_name' is not empty, the row halts and the message 'Field
 * field_ignore value is present so row skipped.' is logged in message table.
 *
 * @code
 * process:
 *   parent:
 *     -
 *       plugin: migration_lookup
 *       migration: d6_taxonomy_term
 *       source: parent_id
 *     -
 *       plugin: skip_on_not_empty
 *       method: process
 *     -
 *       plugin: migration_lookup
 *       migration: node_type
 * @endcode
 * If 'parent' is not empty, further processing of the property is skipped and
 * the next process plugin (migration_lookup) will not be run.
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "skip_on_not_empty"
 * )
 */
class SkipOnNotEmpty extends ProcessPluginBase {

  /**
   * Skips the current row when value is not empty.
   *
   * @param mixed $value
   *   The input value.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration in which this process is being executed.
   * @param \Drupal\migrate\Row $row
   *   The row from the source to process.
   * @param string $destination_property
   *   The destination property currently worked on. This is only used together
   *   with the $row above.
   *
   * @return mixed
   *   The input value, $value, if it is not empty.
   *
   * @throws \Drupal\migrate\MigrateSkipRowException
   *   Thrown if the source property is !empty and the row should be skipped,
   *   records with STATUS_IGNORED status in the map.
   */
  public function row($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!empty($value)) {
      $message = !empty($this->configuration['message']) ? $this->configuration['message'] : '';
      throw new MigrateSkipRowException($message);
    }
    return $value;
  }

  /**
   * Stops processing the current property when value is not empty.
   *
   * @param mixed $value
   *   The input value.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration in which this process is being executed.
   * @param \Drupal\migrate\Row $row
   *   The row from the source to process.
   * @param string $destination_property
   *   The destination property currently worked on. This is only used together
   *   with the $row above.
   *
   * @return mixed
   *   The input value, $value, if it is empty.
   *
   * @throws \Drupal\migrate\MigrateSkipProcessException
   *   Thrown if the source property is !empty and rest of the process should
   *   be skipped.
   */
  public function process($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!empty($value)) {
      throw new MigrateSkipProcessException();
    }
    return $value;
  }

}
