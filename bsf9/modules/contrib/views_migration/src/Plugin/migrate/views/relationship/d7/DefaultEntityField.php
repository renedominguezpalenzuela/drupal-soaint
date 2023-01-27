<?php

namespace Drupal\views_migration\Plugin\migrate\views\relationship\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The default Migrate Views Relationship plugin for Entity Fields.
 *
 * This plugin is used to prepare the Views `relationships` display options for
 * migration if:
 *  - The Handler Field represents an Entity Field.
 *  - The is no Migrate Views Relationship plugin for the Entity Field's type.
 *
 * @MigrateViewsRelationship(
 *   id = "d7_default_entity_field",
 *   core = {7},
 * )
 */
class DefaultEntityField extends MigrateViewsHandlerPluginBase {

  /**
   * Override the parent to declare the correct var type.
   *
   * @var \Drupal\views_migration\Plugin\migrate\views\SourceHandlerEntityFieldInfoProvider
   */
  protected $infoProvider;

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    if (isset($handler_config['table'])) {
      $field_name = $this->infoProvider->getEntityFieldName();
      $handler_config['table'] = $this->infoProvider->getRelationshipEntityType($handler_config) . '__' . $field_name;
      $handler_config['field'] = $field_name;
      if (!isset($handler_config['plugin_id'])) {
        $handler_config['plugin_id'] = 'standard';
      }
      if (isset($handler_config['label'])) {
        $handler_config['admin_label'] = $handler_config['label'];
      }
      $this->alterEntityIdField($handler_config);
    }
  }

}
