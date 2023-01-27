<?php

namespace Drupal\views_migration\Plugin\migrate\views\base_table\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginBase;
use Drupal\views_migration\Plugin\MigrateViewsBaseTableInterface;

/**
 * The default Migrate Views Base Table plugin.
 *
 * This plugin is used to provide the new Views Base Table settings when no
 * other migrate plugin exists for the source base table.
 *
 * @MigrateViewsBaseTable(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultBaseTable extends MigrateViewsPluginBase implements MigrateViewsBaseTableInterface {

  /**
   * {@inheritdoc}
   */
  public function getNewBaseTable(string $source_base_table) {
    return $source_base_table;
  }

  /**
   * {@inheritdoc}
   */
  public function getNewSourceBaseTable(string $source_base_table) {
    if (isset($this->baseTableArray[$source_base_table])) {
      $entity_detail = $this->baseTableArray[$source_base_table];
      return $entity_detail['data_table'];
    }
    return $source_base_table;
  }

  /**
   * {@inheritdoc}
   */
  public function getNewSourceBaseField(string $source_base_table) {
    if (isset($this->baseTableArray[$source_base_table])) {
      $entity_detail = $this->baseTableArray[$source_base_table];
      return $entity_detail['entity_keys']['id'];
    }
    return 'nid';
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseTableEntityType(string $source_base_table) {
    if (isset($this->baseTableArray[$source_base_table])) {
      $entity_detail = $this->baseTableArray[$source_base_table];
      return $entity_detail['entity_id'];
    }
    return 'node';
  }

}
