<?php

namespace Drupal\views_migration\Plugin\migrate\views\field\d7;

/**
 * The Migrate plugin for Views Fields representing Boolean Entity Fields.
 *
 * @MigrateViewsField(
 *   id = "entity_field_boolean",
 *   entity_field_types = {
 *     "boolean",
 *   },
 *   core = {7},
 * )
 */
class EntityFieldBoolean extends DefaultEntityField {

  use BooleanTrait;

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    $this->alterHandlerConfigBoolean($handler_config);
    parent::alterHandlerConfig($handler_config);
  }

}
