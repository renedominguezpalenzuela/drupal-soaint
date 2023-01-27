<?php

namespace Drupal\views_migration\Plugin\migrate\views\handler_table\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginBase;
use Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider;
use Drupal\views_migration\Plugin\MigrateViewsHandlerTableInterface;

/**
 * The default Migrate Views Handler Table plugin.
 *
 * This plugin is used to provide the new Views Handler Table value when no
 * other migrate plugin exists for the source table value and the Field does
 * not represent an Entity Field.
 *
 * @MigrateViewsHandlerTable(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultHandlerTable extends MigrateViewsPluginBase implements MigrateViewsHandlerTableInterface {

  /**
   * {@inheritdoc}
   */
  public function getNewTableValue(SourceHandlerInfoProvider $info_provider) {
    $handler_config = $info_provider->getSourceData();
    if (empty($handler_config['table'])) {
      return $handler_config['table'];
    }
    // Determine if the source config exist in the new Drupal version. If it
    // does, no table change is required.
    if (isset($this->viewsData[$handler_config['table']][$handler_config['field']])) {
      // The source config exist in the new Drupal version. Nothing more to do.
      return $handler_config['table'];
    }
    if (isset($this->baseTableArray[$handler_config['table']])) {
      $entity_detail = $this->baseTableArray[$handler_config['table']];
      return $entity_detail['data_table'];
    }
    if (isset($this->entityTableArray[$handler_config['table']])) {
      $entity_detail = $this->entityTableArray[$handler_config['table']];
      return $entity_detail['data_table'];
    }
    return $handler_config['table'];
  }

}
