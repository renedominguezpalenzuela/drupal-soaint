<?php

namespace Drupal\views_migration\Plugin\migrate\views\handler_table\d7;

use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginBase;
use Drupal\views_migration\Plugin\migrate\views\SourceHandlerInfoProvider;
use Drupal\views_migration\Plugin\MigrateViewsHandlerTableInterface;

/**
 * The Migrate Views Handler Table plugin for the node_comment_statistics table.
 *
 * @MigrateViewsHandlerTable(
 *   id = "node_comment_statistics",
 *   tables = {
 *     "node_comment_statistics",
 *   },
 *   core = {7},
 * )
 */
class NodeCommentStatistics extends MigrateViewsPluginBase implements MigrateViewsHandlerTableInterface {

  /**
   * {@inheritdoc}
   */
  public function getNewTableValue(SourceHandlerInfoProvider $info_provider) {
    return 'comment_entity_statistics';
  }

}
