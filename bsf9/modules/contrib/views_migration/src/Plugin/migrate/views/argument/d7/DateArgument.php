<?php

namespace Drupal\views_migration\Plugin\migrate\views\argument\d7;

use Drupal\migrate\Plugin\MigrationInterface;

/**
 * The Views Migrate plugin for "date_argument" Argument Handler.
 *
 * @MigrateViewsArgument(
 *   id = "date_argument",
 *   field_ids = {
 *     "date_argument",
 *   },
 *   core = {7},
 * )
 */
class DateArgument extends DefaultArgument {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    $handler_config['plugin_id'] = 'datetime_' . $handler_config['granularity'];
    $handler_config['default_argument_type'] = 'fixed';
    $date_fields = [];
    foreach ($handler_config['date_fields'] as $key1 => $value1) {
      $date_components = explode('.', $key1);
      $date_field = end($date_components);
      $date_fields[$date_field] = $date_field;
    }
    $field = array_keys($date_fields)[0];
    $handler_config['field'] = $field . '_' . $handler_config['granularity'];
    if (mb_substr($field, -6) === '_value') {
      $field = substr($field, 0, -6);
    }
    $table = $this->infoProvider->getViewBaseEntityType() . '__' . $field;
    $handler_config['date_fields'] = $date_fields;
    $handler_config['table'] = $table;

    $this->alterArgumentValidateSettings($handler_config);
    $this->alterSummarySettings($handler_config);
    $message = sprintf("The date_argument plugin doesn't exist in the newer version of Drupal. Modified to be a %s date plugin for %s", $handler_config['granularity'], $field);
    $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
  }

}
