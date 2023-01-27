<?php

namespace Drupal\views_migration\Plugin\migrate\views\filter\d7;

use Drupal\migrate\Plugin\MigrationInterface;

/**
 * The Views Migrate plugin for "date_filter" Filter Handlers.
 *
 * @MigrateViewsFilter(
 *   id = "date_filter",
 *   field_ids = {
 *     "date_filter"
 *   },
 *   core = {7},
 * )
 */
class DateFilter extends DefaultFilter {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    $base_entity_type = $this->infoProvider->getViewBaseEntityType();
    $handler_config['plugin_id'] = 'datetime';
    $date_fields = [];
    foreach ($handler_config['date_fields'] as $key1 => $value1) {
      $date_components = explode('.', $key1);
      $date_field = end($date_components);
      $date_fields[$date_field] = $date_field;
    }
    $field = array_keys($date_fields)[0];
    $table = $base_entity_type . '__' . $field;
    $table = str_replace('_value', '', $table);
    $handler_config['date_fields'] = $date_fields;
    $handler_config['table'] = $table;
    $handler_config['field'] = $field;
    $this->alterExposeSettings($handler_config);

    $message = sprintf("The date_filter plugin doesn't exist in the newer version of Drupal. Modified to be a datetime plugin for %s", $field);
    $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
  }

}
