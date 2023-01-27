<?php

namespace Drupal\views_migration\Plugin\migrate\views\field\d7;

/**
 * The Views Migrate plugin for "operations" Field Handlers.
 *
 * @MigrateViewsField(
 *   id = "operations",
 *   field_ids = {
 *     "operations",
 *   },
 *   core = {7},
 * )
 */
class Operations extends DefaultField {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    $base_entity_type = $this->infoProvider->getViewBaseEntityType();
    $handler_config['plugin_id'] = 'entity_operations';
    $handler_config['entity_type'] = $base_entity_type;
    $baseTable = \Drupal::entityTypeManager()->getStorage($base_entity_type)->getBaseTable();
    $handler_config['table'] = $baseTable;
    $this->alterTokenizedSettings($handler_config);
  }

}
