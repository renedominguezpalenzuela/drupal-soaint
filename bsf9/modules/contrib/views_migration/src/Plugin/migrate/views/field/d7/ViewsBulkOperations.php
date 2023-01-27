<?php

namespace Drupal\views_migration\Plugin\migrate\views\field\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The Views Migrate plugin for "views_bulk_operations" Field Handlers.
 *
 * @MigrateViewsField(
 *   id = "views_bulk_operations",
 *   field_ids = {
 *     "views_bulk_operations",
 *   },
 *   core = {7},
 * )
 */
class ViewsBulkOperations extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    $handler_config['plugin_id'] = 'views_bulk_operations_bulk_form';
    $handler_config['table'] = 'views';
    $handler_config['field'] = 'views_bulk_operations_bulk_form';
  }

}
