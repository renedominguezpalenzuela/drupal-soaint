<?php

namespace Drupal\views_migration\Plugin\migrate\views\field\d7;

/**
 * The Migrate plugin for core Views Fields using the boolean plugin.
 *
 * @MigrateViewsField(
 *   id = "core_boolean",
 *   field_ids = {
 *     "status",
 *     "sticky",
 *     "promote",
 *   },
 *   core = {7},
 * )
 */
class CoreBoolean extends DefaultField {

  use BooleanTrait;

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    $this->alterHandlerConfigBoolean($handler_config);
    parent::alterHandlerConfig($handler_config);
  }

}
