<?php

namespace Drupal\views_migration\Plugin\migrate\views\style_summary\d7;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\views_migration\Plugin\migrate\views\MigrateViewsHandlerPluginBase;

/**
 * The default Migrate Views Style Summary plugin.
 *
 * This plugin is used to prepare the Views `summary` display options for
 * migration when no other migrate plugin exists for the current summary plugin.
 *
 * @MigrateViewsStyleSummary(
 *   id = "d7_default",
 *   core = {7},
 * )
 */
class DefaultStyleSummary extends MigrateViewsHandlerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterHandlerConfig(array &$handler_config) {
    if (isset($handler_config['summary']['format']) && !in_array($handler_config['summary']['format'], $this->pluginList['style'], TRUE)) {
      $message = sprintf("The style summary plugin '%s' view does not exist. Replaced with the 'default_summary' plugin.", $handler_config['summary']['format']);
      $this->saveMessage($message, MigrationInterface::MESSAGE_WARNING);
      $handler_config['summary']['format'] = 'default_summary';
    }
  }

}
