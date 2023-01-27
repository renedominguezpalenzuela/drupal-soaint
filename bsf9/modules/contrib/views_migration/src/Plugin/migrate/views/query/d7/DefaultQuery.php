<?php

namespace Drupal\views_migration\Plugin\migrate\views\query\d7;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\MigrateViewsPluginPluginBase;

/**
 * The default Migrate Views Query plugin.
 *
 * This plugin is used to prepare the Views `query` display options for
 * migration when no other migrate plugin exists for the current query plugin.
 *
 * @MigrateViewsQuery(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultQuery extends MigrateViewsPluginPluginBase {

  /**
   * {@inheritdoc}
   */
  public function prepareDisplayOptions(array &$display_options) {
    if (isset($display_options['query']['type']) && !in_array($display_options['query']['type'], $this->pluginList['query'], TRUE)) {
      $message = sprintf("The query plugin '%s' does not exist. Replaced with the 'views_query' plugin.", $display_options['query']['type']);
      $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
      $display_options['query'] = [
        'type' => 'views_query',
        'options' => [],
      ];
    }
  }

}
