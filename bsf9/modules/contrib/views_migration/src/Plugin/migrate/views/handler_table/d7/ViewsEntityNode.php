<?php

namespace Drupal\views_migration\Plugin\migrate\views\handler_table\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginBase;
use Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider;
use Drupal\views_migration\Plugin\MigrateViewsHandlerTableInterface;

/**
 * The Migrate Views Handler Table plugin for the views_entity_node table.
 *
 * @MigrateViewsHandlerTable(
 *   id = "views_entity_node",
 *   tables = {
 *     "views_entity_node",
 *   },
 *   core = {7},
 * )
 */
class ViewsEntityNode extends MigrateViewsPluginBase implements MigrateViewsHandlerTableInterface {

  /**
   * {@inheritdoc}
   */
  public function getNewTableValue(SourceHandlerInfoProvider $info_provider) {
    return 'node';
  }

}
