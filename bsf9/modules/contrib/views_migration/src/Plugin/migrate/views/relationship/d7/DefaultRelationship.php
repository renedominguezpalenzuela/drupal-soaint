<?php

namespace Drupal\views_migration\Plugin\migrate\views\relationship\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The default Migrate Views Relationship plugin.
 *
 * This plugin is used to prepare the Views `relationship` display options for
 * migration when no other migrate plugin exists for the current relationship
 * plugin.
 *
 * @MigrateViewsRelationship(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultRelationship extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    $base_entity_type = $this->infoProvider->getViewBaseEntityType();
    $tableMapping = [
      'node_revision' => 'node_field_revision',
    ];
    if (isset($handler_config['table'])) {
      if (isset($tableMapping[$handler_config['table']])) {
        $handler_config['table'] = $tableMapping[$handler_config['table']];
      }
      if (isset($this->baseTableArray[$handler_config['table']])) {
        $entity_detail = $this->baseTableArray[$handler_config['table']];
        $handler_config['table'] = $entity_detail['data_table'];
        $handler_config['entity_type'] = $entity_detail['entity_id'];
        if ($handler_config['field'] == 'term_node_tid') {
          $handler_config['plugin_id'] = 'node_term_data';
          $vids = [];
          if (isset($handler_config['vocabularies'])) {
            foreach ($handler_config['vocabularies'] as $key => $value) {
              if ($value) {
                $vids[] = $key;
              }
            }
          }
          $handler_config['vids'] = $vids;
          unset($handler_config['vocabularies']);
        }
        else {
          $handler_config['plugin_id'] = 'standard';
        }
        if (isset($handler_config['label'])) {
          $handler_config['admin_label'] = $handler_config['label'];
        }
      }
      if (mb_strpos($handler_config['id'], 'reverse_') === 0) {
        $field_name = str_replace([
          'reverse_',
          '_' . $base_entity_type,
        ], '', $handler_config['field']);
        if ($handler_config['table'] == 'file_managed') {
          $handler_config['field'] = 'reverse_' . $field_name . '_' . $base_entity_type;
        }
        else {
          $handler_config['field'] = 'reverse__' . $base_entity_type . '__' . $field_name;
        }
        if (isset($handler_config['label'])) {
          $handler_config['admin_label'] = $handler_config['label'];
        }
        unset($handler_config['label'], $handler_config['ui_name']);
        $handler_config['plugin_id'] = 'entity_reverse';
      }
    }
  }

}
